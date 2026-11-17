<?php

namespace Tests\Feature;

use App\Enums\ReviewStatus;
use App\Models\OrderItem;
use App\Models\Review;
use App\Models\ShopOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** One optional MP4/WebM video per product review, uploaded by its author. */
class ReviewVideoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Accept' => 'application/json']);
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function login(User $user): void
    {
        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->actingAs($user, 'web');
    }

    /** A published review by the buyer of a completed purchase, signed in as that buyer. */
    private function review(): Review
    {
        $item = OrderItem::factory()->for(ShopOrder::factory()->completed())->create();
        $this->login($item->shopOrder->order->buyer);
        $id = $this->postJson('/api/v1/order-items/'.$item->id.'/review', ['rating' => 5, 'comment' => 'Đúng mô tả'])->assertCreated()->assertJsonPath('data.video', null)->json('data.id');

        return Review::findOrFail($id);
    }

    private function clip(string $name = 'clip.webm'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, file_get_contents(__DIR__.'/../Fixtures/product-review.webm'));
    }

    private function upload(Review $review, $file): TestResponse
    {
        return $this->post('/api/v1/reviews/'.$review->id.'/video', ['video' => $file]);
    }

    public function test_the_author_attaches_replaces_and_removes_the_one_video(): void
    {
        $review = $this->review();
        $first = $this->upload($review, $this->clip())->assertOk()->assertJsonPath('data.video.mime_type', 'video/webm')->assertJsonPath('data.rating', 5)->json('data.video');
        $path = $review->refresh()->video_path;
        $this->assertMatchesRegularExpression('#^review-videos/'.$review->id.'/[a-f0-9-]{36}\.webm$#', $path);
        $this->assertStringEndsWith($path, $first['url']);
        Storage::disk('public')->assertExists($path);

        // Shown on the public product page and in the buyer's own purchase list.
        $slug = $review->product->slug;
        $this->getJson('/api/v1/products/'.$slug.'/reviews')->assertOk()->assertJsonPath('data.0.video.url', $first['url']);
        $this->getJson('/api/v1/products/'.$slug.'/review-eligibility')->assertOk()->assertJsonPath('data.0.review.video.url', $first['url']);

        // A second upload replaces the first; the old file is deleted after commit.
        $this->upload($review, $this->clip())->assertOk();
        $replaced = $review->refresh()->video_path;
        $this->assertNotSame($path, $replaced);
        Storage::disk('public')->assertMissing($path);
        Storage::disk('public')->assertExists($replaced);

        // Editing the text keeps the video.
        $this->patchJson('/api/v1/reviews/'.$review->id, ['rating' => 4])->assertOk()->assertJsonPath('data.video.mime_type', 'video/webm');

        $this->deleteJson('/api/v1/reviews/'.$review->id.'/video')->assertOk()->assertJsonPath('data.video', null);
        Storage::disk('public')->assertMissing($replaced);
        $this->assertNull($review->refresh()->video_size);
    }

    public function test_only_the_author_of_an_eligible_review_may_upload(): void
    {
        $review = $this->review();
        $author = $review->user;
        $this->login(User::factory()->create());
        $this->upload($review, $this->clip())->assertForbidden();
        $this->deleteJson('/api/v1/reviews/'.$review->id.'/video')->assertForbidden();
        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->upload($review, $this->clip())->assertUnauthorized();
        $this->login($author);
        $this->postJson('/api/v1/order-items/'.$review->order_item_id.'/review', ['rating' => 5, 'video_path' => 'x.mp4'])->assertUnprocessable();
        $this->assertNull($review->refresh()->video_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_type_container_and_size_are_checked_before_anything_is_kept(): void
    {
        $review = $this->review();
        $bad = [
            UploadedFile::fake()->createWithContent('script.mp4', '<?php echo 1;'),
            UploadedFile::fake()->createWithContent('clip.mov', file_get_contents(__DIR__.'/../Fixtures/product-review.webm')),
            // Real WebM bytes behind an .mp4 name.
            $this->clip('clip.mp4'),
            UploadedFile::fake()->image('photo.jpg'),
        ];
        foreach ($bad as $file) {
            $this->upload($review, $file)->assertUnprocessable()->assertJsonValidationErrors('video');
        }
        $this->post('/api/v1/reviews/'.$review->id.'/video', [])->assertUnprocessable()->assertJsonValidationErrors('video');
        config(['review-videos.max_kilobytes' => 1]);
        $this->upload($review, $this->clip())->assertUnprocessable()->assertJsonValidationErrors('video');
        $this->assertNull($review->refresh()->video_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_hidden_review_hides_its_video_and_admins_see_it_to_moderate(): void
    {
        $review = $this->review();
        $url = $this->upload($review, $this->clip())->assertOk()->json('data.video.url');
        $review->forceFill(['status' => ReviewStatus::HIDDEN])->save();
        $this->getJson('/api/v1/products/'.$review->product->slug.'/reviews')->assertOk()->assertJsonCount(0, 'data');
        $this->login(User::factory()->admin()->create());
        $this->getJson('/api/v1/admin/reviews/'.$review->id)->assertOk()->assertJsonPath('data.video.url', $url);
    }
}
