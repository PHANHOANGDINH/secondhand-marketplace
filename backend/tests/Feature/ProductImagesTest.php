<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\AuditService;
use App\Services\ProductImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ProductImagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Accept' => 'application/json']);
    }

    private function loginAs(User $user): void
    {
        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->actingAs($user, 'web');
    }

    private function emptyProduct(): Product
    {
        $product = Product::factory()->create();
        $product->images()->delete();
        $this->loginAs($product->shop->owner);

        return $product;
    }

    private function upload(Product $product, int $count = 1): TestResponse
    {
        return $this->post('/api/v1/seller/products/'.$product->id.'/images', ['images' => array_map(fn () => UploadedFile::fake()->image('photo.png', 20, 20), range(1, $count))]);
    }

    public function test_upload_generated_files_primary_set_reorder_and_delete(): void
    {
        $product = $this->emptyProduct();
        $base = '/api/v1/seller/products/'.$product->id.'/images';
        $this->upload($product, 3)->assertCreated()->assertJsonPath('data.0.is_primary', true)->assertJsonPath('data.1.is_primary', false);
        $images = $product->images()->orderBy('id')->get();
        foreach ($images as $image) {
            Storage::disk('public')->assertExists($image->image_path);
            $this->assertMatchesRegularExpression('#^products/'.$product->id.'/[a-f0-9-]{36}\.png$#', $image->image_path);
        }
        $this->patchJson($base.'/'.$images[1]->id, ['is_primary' => true])->assertOk();
        $this->assertSame(1, $product->images()->where('is_primary', true)->count());
        $ids = [$images[2]->id, $images[0]->id, $images[1]->id];
        $this->patchJson($base.'/reorder', ['image_ids' => $ids])->assertOk()->assertJsonPath('data.0.id', (string) $images[2]->id);
        $this->deleteJson($base.'/'.$images[1]->id)->assertNoContent();
        Storage::disk('public')->assertMissing($images[1]->image_path);
        $this->assertTrue($images[2]->refresh()->is_primary);
        $this->deleteJson($base.'/'.$images[2]->id)->assertNoContent();
        $this->deleteJson($base.'/'.$images[0]->id)->assertNoContent();
        $this->assertSame(0, $product->images()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_jpeg_png_webp_are_decoded_and_reencoded_without_appended_payload(): void
    {
        $product = $this->emptyProduct();
        foreach (['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'] as $extension => $mime) {
            $file = UploadedFile::fake()->image('original.'.$extension, 16, 16);
            $payload = '<?php echo "untrusted payload"; ?>';
            $file = UploadedFile::fake()->createWithContent('original.'.$extension, file_get_contents($file->getRealPath()).$payload);
            $this->post('/api/v1/seller/products/'.$product->id.'/images', ['images' => [$file]])->assertCreated();
            $image = $product->images()->latest('id')->first();
            $bytes = Storage::disk('public')->get($image->image_path);
            $this->assertStringNotContainsString($payload, $bytes);
            $this->assertSame($mime, getimagesizefromstring($bytes)['mime']);
        }
        $this->assertSame(1, $product->images()->where('is_primary', true)->count());
    }

    public function test_non_owner_and_cross_product_image_ids_are_rejected(): void
    {
        $product = $this->emptyProduct();
        $this->upload($product)->assertCreated();
        $image = $product->images()->first();
        $other = Product::factory()->create();
        $this->loginAs($other->shop->owner);
        $this->upload($product)->assertForbidden();
        $this->assertFalse(Gate::forUser($other->shop->owner)->allows('update', $image));
        $base = '/api/v1/seller/products/'.$other->id.'/images';
        $this->patchJson($base.'/'.$image->id, ['is_primary' => true])->assertNotFound();
        $this->deleteJson($base.'/'.$image->id)->assertNotFound();
        $this->patchJson($base.'/reorder', ['image_ids' => [$image->id]])->assertUnprocessable();
        Storage::disk('public')->assertExists($image->image_path);
    }

    public function test_mime_extension_size_and_pixel_validation(): void
    {
        $product = $this->emptyProduct();
        $url = '/api/v1/seller/products/'.$product->id.'/images';
        foreach ([UploadedFile::fake()->createWithContent('script.php', '<?php echo 1;'), UploadedFile::fake()->createWithContent('pretend.jpg', '<svg xmlns="http://www.w3.org/2000/svg"/>'), UploadedFile::fake()->createWithContent('script.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'), UploadedFile::fake()->image('large.png')->size(5121), UploadedFile::fake()->image('executable.php')] as $file) {
            $this->post($url, ['images' => [$file]])->assertUnprocessable()->assertJsonValidationErrors('images.0');
        }
        $small = UploadedFile::fake()->image('tiny.png', 1, 1);
        $bytes = file_get_contents($small->getRealPath());
        $bytes = substr_replace($bytes, pack('NN', 5000, 4001), 16, 8);
        $bytes = substr_replace($bytes, pack('N', crc32(substr($bytes, 12, 17))), 29, 4);
        $this->post($url, ['images' => [UploadedFile::fake()->createWithContent('huge.png', $bytes)]])->assertUnprocessable()->assertJsonValidationErrors('images.0');
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_max_eight_images_and_invalid_reorder_preserve_invariant(): void
    {
        $product = $this->emptyProduct();
        $this->upload($product, 8)->assertCreated();
        $this->upload($product)->assertUnprocessable();
        $this->assertSame(8, $product->images()->count());
        $this->assertCount(8, Storage::disk('public')->allFiles());
        $ids = $product->images()->pluck('id')->all();
        $base = '/api/v1/seller/products/'.$product->id.'/images';
        $this->patchJson($base.'/reorder', ['image_ids' => [$ids[0], $ids[0]]])->assertUnprocessable();
        $this->patchJson($base.'/reorder', ['image_ids' => [$ids[0]]])->assertUnprocessable();
        $this->patchJson($base.'/'.$ids[0], ['is_primary' => false])->assertUnprocessable();
        $this->assertSame(1, $product->images()->where('is_primary', true)->count());
    }

    public function test_database_failure_cleans_files_and_rolls_back_all_metadata(): void
    {
        $product = $this->emptyProduct();
        $this->mock(AuditService::class)->shouldReceive('record')->once()->andThrow(new \RuntimeException('audit unavailable'));
        try {
            app(ProductImageService::class)->upload($product->shop->owner, $product, [UploadedFile::fake()->image('first.jpg'), UploadedFile::fake()->image('second.png')]);
            $this->fail('Expected failure');
        } catch (\RuntimeException $error) {
            $this->assertSame('audit unavailable', $error->getMessage());
        }
        $this->assertSame(0, $product->images()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_image_changes_trigger_review_and_snapshot_reference_keeps_file(): void
    {
        $product = $this->emptyProduct();
        $this->upload($product)->assertCreated();
        $image = $product->images()->first();
        $item = OrderItem::factory()->for($product)->create(['product_image' => $image->image_path]);
        $product->update(['status' => ProductStatus::ACTIVE]);
        $this->deleteJson('/api/v1/seller/products/'.$product->id.'/images/'.$image->id)->assertNoContent();
        $this->assertSame(ProductStatus::PENDING, $product->refresh()->status);
        Storage::disk('public')->assertExists($image->image_path);
        $this->assertSame($image->image_path, $item->refresh()->product_image);
    }

    public function test_primary_delete_failure_preserves_files_and_default(): void
    {
        $product = $this->emptyProduct();
        $this->upload($product, 2)->assertCreated();
        $primary = $product->images()->where('is_primary', true)->first();
        $this->mock(AuditService::class)->shouldReceive('record')->once()->andThrow(new \RuntimeException('audit unavailable'));
        try {
            app(ProductImageService::class)->delete($product->shop->owner, $product, $primary);
            $this->fail('Expected failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('audit unavailable', $e->getMessage());
        }
        $this->assertSame(2, $product->images()->count());
        $this->assertTrue($primary->refresh()->is_primary);
        Storage::disk('public')->assertExists($primary->image_path);
    }
}
