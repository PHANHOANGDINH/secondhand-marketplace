<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\ProductVideoStatus;
use App\Models\Product;
use App\Models\ProductVideo;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ProductVideosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Accept' => 'application/json']);
    }

    private function login(User $user): void
    {
        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->actingAs($user, 'web');
    }

    private function product(): Product
    {
        $p = Product::factory()->active()->create();
        $this->login($p->shop->owner);

        return $p;
    }

    private function path(Product $p): string
    {
        return '/api/v1/seller/products/'.$p->id.'/videos';
    }

    private function file(string $name = 'review.webm'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, file_get_contents(__DIR__.'/../Fixtures/product-review.webm'));
    }

    private function upload(Product $p): ProductVideo
    {
        $this->post($this->path($p), ['video' => $this->file(), 'title' => 'Video review'])->assertCreated();

        return $p->videos()->latest('id')->firstOrFail();
    }

    public function test_upload_streams_safe_file_and_preserves_existing_product_rules(): void
    {
        $p = $this->product();
        $updated = $p->updated_at->toISOString();
        $v = $this->upload($p);
        $this->assertMatchesRegularExpression('#^product-videos/'.$p->id.'/[a-f0-9-]{36}\.webm$#', $v->path);
        Storage::disk('public')->assertExists($v->path);
        $this->assertSame('video/webm', $v->mime_type);
        $this->assertSame(filesize(__DIR__.'/../Fixtures/product-review.webm'), $v->file_size);
        $this->assertTrue($v->is_primary);
        $this->assertSame(ProductVideoStatus::Ready, $v->status);
        $this->assertNull($v->poster_path);
        $this->assertNull($v->duration_seconds);
        $this->assertSame(ProductStatus::ACTIVE, $p->refresh()->status);
        $this->assertSame($updated, $p->updated_at->toISOString());
        $this->assertSame(5, $p->stock_quantity);
    }

    public function test_guests_and_other_sellers_cannot_read_upload_edit_or_delete(): void
    {
        $p = $this->product();
        $v = $this->upload($p);
        $this->login(User::factory()->create());
        $this->getJson($this->path($p))->assertForbidden();
        $this->post($this->path($p), ['video' => $this->file()])->assertForbidden();
        $this->patchJson($this->path($p).'/'.$v->id, ['title' => 'Other'])->assertForbidden();
        $this->deleteJson($this->path($p).'/'.$v->id)->assertForbidden();
        Auth::forgetGuards();
        $this->app['session']->flush();
        $this->getJson($this->path($p))->assertUnauthorized();
        $this->post($this->path($p), ['video' => $this->file()])->assertUnauthorized();
        Storage::disk('public')->assertExists($v->path);
    }

    public function test_child_video_binding_cannot_cross_products_even_for_same_seller(): void
    {
        $p = $this->product();
        $v = $this->upload($p);
        $other = Product::factory()->create(['shop_id' => $p->shop_id]);
        $this->patchJson($this->path($other).'/'.$v->id, ['title' => 'Wrong product'])->assertNotFound();
        $this->deleteJson($this->path($other).'/'.$v->id)->assertNotFound();
    }

    public function test_invalid_mime_extension_and_container_are_rejected_without_files(): void
    {
        $p = $this->product();
        foreach ([UploadedFile::fake()->createWithContent('script.mp4', '<?php echo "bad";?>'), $this->file('review.php'), $this->file('review.mp4'), UploadedFile::fake()->create('broken.mp4', 5, 'video/mp4')] as $file) {
            $this->post($this->path($p), ['video' => $file])->assertUnprocessable()->assertJsonValidationErrors('video');
        }
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('product_videos', 0);
    }

    public function test_mp4_container_header_and_extension_are_supported_without_transcoding(): void
    {
        $p = $this->product();
        $file = new UploadedFile(__DIR__.'/../Fixtures/product-review.mp4', 'review.mp4', null, null, true);
        $this->post($this->path($p), ['video' => $file])->assertCreated()->assertJsonPath('data.mime_type', 'video/mp4');
    }

    public function test_configurable_size_limit_and_missing_file_validation(): void
    {
        $p = $this->product();
        config(['product-videos.max_kilobytes' => 1]);
        $this->post($this->path($p), ['video' => $this->file()])->assertUnprocessable()->assertJsonValidationErrors('video');
        $this->post($this->path($p), [])->assertUnprocessable()->assertJsonValidationErrors('video');
        $this->assertDatabaseCount('product_videos', 0);
    }

    public function test_traversal_client_filename_never_becomes_the_storage_path(): void
    {
        $p = $this->product();
        $this->post($this->path($p), ['video' => $this->file('../../private/review.webm')])->assertCreated();
        $v = $p->videos()->sole();
        $this->assertStringNotContainsString('..', $v->path);
        $this->assertStringNotContainsString('private', $v->path);
        Storage::disk('public')->assertExists($v->path);
    }

    public function test_three_video_limit_cleans_the_rejected_upload(): void
    {
        $p = $this->product();
        for ($i = 0; $i < 3; $i++) {
            $this->upload($p);
        }$this->post($this->path($p), ['video' => $this->file()])->assertUnprocessable()->assertJsonValidationErrors('video');
        $this->assertCount(3, Storage::disk('public')->allFiles());
        $this->assertSame(1, $p->videos()->where('is_primary', true)->count());
    }

    public function test_primary_reorder_hide_unhide_and_delete_keep_invariants(): void
    {
        $p = $this->product();
        $a = $this->upload($p);
        $b = $this->upload($p);
        $c = $this->upload($p);
        $base = $this->path($p);
        $this->patchJson($base.'/'.$b->id, ['is_primary' => true])->assertOk()->assertJsonPath('data.is_primary', true);
        $this->patchJson($base.'/'.$c->id, ['sort_order' => 0])->assertOk();
        $this->assertSame([$c->id, $a->id, $b->id], $p->videos()->orderBy('sort_order')->pluck('id')->all());
        $this->patchJson($base.'/'.$b->id, ['status' => 'HIDDEN'])->assertOk()->assertJsonPath('data.is_primary', false);
        $this->assertTrue($c->refresh()->is_primary);
        $this->patchJson($base.'/'.$c->id, ['status' => 'HIDDEN'])->assertOk();
        $this->patchJson($base.'/'.$a->id, ['status' => 'HIDDEN'])->assertOk();
        $this->assertSame(0, $p->videos()->where('is_primary', true)->count());
        $this->patchJson($base.'/'.$b->id, ['status' => 'READY'])->assertOk()->assertJsonPath('data.is_primary', true);
        $this->deleteJson($base.'/'.$b->id)->assertNoContent();
        Storage::disk('public')->assertMissing($b->path);
        $this->assertSame([0, 1], $p->videos()->orderBy('sort_order')->pluck('sort_order')->all());
    }

    public function test_metadata_and_action_validation_do_not_accept_unsafe_fields(): void
    {
        $p = $this->product();
        $v = $this->upload($p);
        $base = $this->path($p).'/'.$v->id;
        $this->patchJson($base, ['title' => str_repeat('x', 201)])->assertUnprocessable();
        $this->patchJson($base, ['description' => str_repeat('x', 1001)])->assertUnprocessable();
        foreach ([['path' => '../../secret'], ['uploaded_by' => 999], ['status' => 'PROCESSING'], ['is_primary' => false], ['sort_order' => 2], ['is_primary' => true, 'status' => 'HIDDEN'], []] as $data) {
            $this->patchJson($base, $data)->assertUnprocessable();
        }
        $this->patchJson($base, ['title' => 'Updated', 'description' => '<b>Plain text</b>'])->assertOk()->assertJsonPath('data.description', '<b>Plain text</b>');
        $this->assertTrue($v->refresh()->is_primary);
    }

    public function test_public_only_ready_videos_and_safe_resource_fields(): void
    {
        $p = $this->product();
        $a = $this->upload($p);
        $b = $this->upload($p);
        $this->patchJson($this->path($p).'/'.$b->id, ['status' => 'HIDDEN'])->assertOk();
        $response = $this->getJson('/api/v1/products/'.$p->slug.'/videos')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', (string) $a->id);
        $this->assertEqualsCanonicalizing(['id', 'title', 'description', 'url', 'poster_url', 'duration_seconds', 'is_primary', 'sort_order'], array_keys($response->json('data.0')));
        $list = $this->getJson('/api/v1/products')->assertOk();
        $this->assertArrayNotHasKey('videos', $list->json('data.0'));
    }

    public function test_product_shop_owner_category_stock_and_soft_delete_public_visibility(): void
    {
        $p = $this->product();
        $this->upload($p);
        $url = '/api/v1/products/'.$p->slug.'/videos';
        foreach (['DRAFT', 'PENDING', 'HIDDEN', 'REJECTED', 'SOLD'] as $status) {
            $p->update(['status' => $status]);
            $this->getJson($url)->assertNotFound();
        }$p->update(['status' => 'ACTIVE', 'stock_quantity' => 0]);
        $this->getJson($url)->assertNotFound();
        $p->update(['stock_quantity' => 5]);
        $p->shop->update(['status' => 'SUSPENDED']);
        $this->getJson($url)->assertNotFound();
        $p->shop->update(['status' => 'ACTIVE']);
        $p->category->update(['status' => 'INACTIVE']);
        $this->getJson($url)->assertNotFound();
        $p->category->update(['status' => 'ACTIVE']);
        $p->shop->owner->forceFill(['status' => 'SUSPENDED'])->save();
        $this->getJson($url)->assertNotFound();
        $p->shop->owner->forceFill(['status' => 'ACTIVE'])->save();
        $p->delete();
        $this->getJson($url)->assertNotFound();
        $this->getJson($this->path($p))->assertNotFound();
    }

    public function test_inactive_seller_or_suspended_shop_cannot_mutate(): void
    {
        $p = $this->product();
        $v = $this->upload($p);
        $p->shop->update(['status' => 'SUSPENDED']);
        $this->post($this->path($p), ['video' => $this->file()])->assertForbidden();
        $this->deleteJson($this->path($p).'/'.$v->id)->assertForbidden();
        $p->shop->update(['status' => 'ACTIVE']);
        $p->shop->owner->forceFill(['status' => 'SUSPENDED'])->save();
        $this->login($p->shop->owner->fresh());
        $this->post($this->path($p), ['video' => $this->file()])->assertForbidden();
    }

    public function test_upload_transaction_failure_removes_uncommitted_file(): void
    {
        $p = $this->product();
        $audit = Mockery::mock(AuditService::class);
        $audit->shouldReceive('record')->andThrow(new \RuntimeException('audit unavailable'));
        $this->instance(AuditService::class, $audit);
        $this->post($this->path($p), ['video' => $this->file()])->assertServerError();
        $this->assertDatabaseCount('product_videos', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_delete_transaction_failure_keeps_the_file_and_row(): void
    {
        $p = $this->product();
        $v = $this->upload($p);
        $audit = Mockery::mock(AuditService::class);
        $audit->shouldReceive('record')->andThrow(new \RuntimeException('audit unavailable'));
        $this->instance(AuditService::class, $audit);
        $this->deleteJson($this->path($p).'/'.$v->id)->assertServerError();
        $this->assertDatabaseHas('product_videos', ['id' => $v->id]);
        Storage::disk('public')->assertExists($v->path);
    }

    public function test_delete_never_removes_arbitrary_stored_paths(): void
    {
        $p = $this->product();
        $v = $this->upload($p);
        Storage::disk('public')->put('keep.txt', 'unrelated');
        $v->forceFill(['path' => 'keep.txt'])->save();
        $this->deleteJson($this->path($p).'/'.$v->id)->assertNoContent();
        Storage::disk('public')->assertExists('keep.txt');
    }
}
