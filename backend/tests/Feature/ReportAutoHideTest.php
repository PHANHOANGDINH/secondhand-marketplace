<?php

namespace Tests\Feature;

use App\Events\NotificationCreated;
use App\Models\Auction;
use App\Models\Product;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ReportAutoHideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Accept' => 'application/json']);
    }

    private function login(User $user): void
    {
        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->actingAs($user, 'web');
    }

    private function report(Product $product, ?User $user = null): User
    {
        $user ??= User::factory()->create();
        $this->login($user);
        $this->postJson('/api/v1/reports', ['target_type' => 'PRODUCT', 'target_id' => $product->id, 'reason' => 'MISLEADING', 'description' => 'Ảnh không đúng sản phẩm.'])->assertCreated();

        return $user;
    }

    public function test_a_live_listing_is_hidden_for_admin_review_once_three_different_users_report_it(): void
    {
        $product = Product::factory()->active()->create();
        Event::fake([NotificationCreated::class]);
        $first = $this->report($product);
        $this->report($product);
        $this->assertSame('ACTIVE', $product->refresh()->status->value);
        // The same user cannot count twice while their report is open.
        $this->login($first);
        $this->postJson('/api/v1/reports', ['target_type' => 'PRODUCT', 'target_id' => $product->id, 'reason' => 'SPAM'])->assertConflict();
        $this->assertSame('ACTIVE', $product->refresh()->status->value);

        $this->report($product);
        $product->refresh();
        $this->assertSame('PENDING', $product->status->value);
        $this->assertStringContainsString('3 người dùng báo cáo', $product->moderation_reason);
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'products', 'entity_id' => $product->id, 'action' => 'product.auto_hidden', 'from_status' => 'ACTIVE', 'to_status' => 'PENDING']);
        $notice = DB::table('notifications')->where('notifiable_id', $product->shop->owner_id)->where('type', 'PRODUCT_HIDDEN')->sole();
        $this->assertSame('/my-ads?tab=pending', json_decode($notice->data, true)['target_route']);
        Event::assertDispatchedTimes(NotificationCreated::class, 1);
        $this->getJson('/api/v1/products/'.$product->slug)->assertNotFound();

        // The admin decides: reopen it, or take it down.
        $this->login(User::factory()->admin()->create());
        $this->getJson('/api/v1/admin/products?status=PENDING')->assertOk()->assertJsonPath('data.0.id', (string) $product->id);
        $this->patchJson('/api/v1/admin/products/'.$product->id.'/status', ['expected_status' => 'PENDING', 'status' => 'ACTIVE', 'reason' => 'Báo cáo không đúng'])->assertOk()->assertJsonPath('data.status', 'ACTIVE');
    }

    public function test_dismissed_reports_do_not_count_and_held_or_hidden_listings_are_left_alone(): void
    {
        $product = Product::factory()->active()->create();
        $this->report($product);
        $this->report($product);
        Report::where('target_id', $product->id)->oldest('id')->first()->forceFill(['status' => 'DISMISSED'])->save();
        $this->report($product);
        $this->assertSame('ACTIVE', $product->refresh()->status->value);

        // A listing held by a running auction is left for the admin, so bidders are not cut off.
        $held = Product::factory()->active()->create();
        Auction::factory()->create(['product_id' => $held->id]);
        foreach (range(1, 3) as $step) {
            $this->report($held);
        }
        $this->assertSame('ACTIVE', $held->refresh()->status->value);
        $this->assertSame(0, DB::table('notifications')->where('type', 'PRODUCT_HIDDEN')->count());
    }

    public function test_the_threshold_is_configurable(): void
    {
        config(['moderation.auto_hide_reports' => 1]);
        $product = Product::factory()->active()->create();
        $this->report($product);
        $this->assertSame('PENDING', $product->refresh()->status->value);
    }
}
