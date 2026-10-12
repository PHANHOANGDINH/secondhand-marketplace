<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVideo;
use App\Models\Report;
use App\Models\User;
use App\Models\VideoComment;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Accept' => 'application/json']);
    }

    private function login(User $user): void
    {
        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->actingAs($user, 'web');
    }

    private function payload(Product $product): array
    {
        return ['target_type' => 'PRODUCT', 'target_id' => $product->id, 'reason' => 'MISLEADING', 'description' => '  Cần kiểm tra thông tin.  '];
    }

    private function transition(Report $report, string $to, string $from = 'PENDING', array $extra = []): TestResponse
    {
        return $this->patchJson('/api/v1/admin/reports/'.$report->id.'/status', ['status' => $to, 'expected_status' => $from, ...$extra]);
    }

    public function test_guests_and_non_admins_cannot_access_protected_operations(): void
    {
        $p = Product::factory()->active()->create();
        $r = Report::factory()->create();
        $this->postJson('/api/v1/reports', $this->payload($p))->assertUnauthorized();
        $this->getJson('/api/v1/my-reports')->assertUnauthorized();
        $this->getJson('/api/v1/admin/reports')->assertUnauthorized();
        $this->login(User::factory()->create());
        $this->getJson('/api/v1/admin/reports')->assertForbidden();
        $this->getJson('/api/v1/admin/reports/'.$r->id)->assertForbidden();
        $this->transition($r, 'DISMISSED')->assertForbidden();
    }

    public function test_create_all_target_types_trims_description_and_changes_no_target_state(): void
    {
        $p = Product::factory()->active()->create();
        $v = ProductVideo::factory()->create(['product_id' => $p->id]);
        $c = VideoComment::factory()->create(['product_video_id' => $v->id]);
        $this->login(User::factory()->create());
        $before = [$p->fresh()->getRawOriginal(), $p->shop->fresh()->getRawOriginal(), $c->fresh()->getRawOriginal()];
        foreach ([['PRODUCT', $p->id], ['SHOP', $p->shop_id], ['VIDEO_COMMENT', $c->id]] as [$type,$id]) {
            $this->postJson('/api/v1/reports', ['target_type' => $type, 'target_id' => $id, 'reason' => 'SPAM', 'description' => '  Thử báo cáo  '])->assertCreated()->assertJsonPath('data.description', 'Thử báo cáo')->assertJsonPath('data.status', 'PENDING')->assertJsonMissingPath('data.admin_note')->assertJsonMissingPath('data.target.context');
        }
        $this->assertSame($before, [$p->fresh()->getRawOriginal(), $p->shop->fresh()->getRawOriginal(), $c->fresh()->getRawOriginal()]);
    }

    public function test_controlled_types_reasons_validation_and_client_tampering(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $p = Product::factory()->active()->create();
        $this->login(User::factory()->create());
        $data = $this->payload($p);
        foreach ([['target_type' => 'App\\Models\\User'], ['target_type' => 'USER'], ['reason' => 'anything'], ['target_id' => 0], ['description' => str_repeat('a', 1001)], ['reason' => 'OTHER', 'description' => '   '], ['handled_by' => 1], ['status' => 'RESOLVED'], ['reporter_user_id' => 1]] as $bad) {
            $this->postJson('/api/v1/reports', array_replace($data, $bad))->assertUnprocessable();
        }
        $this->postJson('/api/v1/reports', array_replace($data, ['target_id' => 9999999]))->assertNotFound();
        $this->postJson('/api/v1/reports', array_replace($data, ['reason' => 'OTHER', 'description' => 'Mô tả khác']))->assertCreated();
    }

    public function test_one_open_report_and_new_report_only_after_terminal_outcome(): void
    {
        $p = Product::factory()->active()->create();
        $u = User::factory()->create();
        $a = User::factory()->admin()->create();
        $this->login($u);
        $this->postJson('/api/v1/reports', $this->payload($p))->assertCreated();
        $r = Report::sole();
        $this->postJson('/api/v1/reports', $this->payload($p))->assertConflict();
        $this->login($a);
        $this->transition($r, 'IN_REVIEW')->assertOk();
        $this->login($u);
        $this->postJson('/api/v1/reports', $this->payload($p))->assertConflict();
        $this->login($a);
        $this->transition($r, 'DISMISSED', 'IN_REVIEW')->assertOk();
        $this->login($u);
        $this->postJson('/api/v1/reports', $this->payload($p))->assertCreated();
        $this->assertDatabaseCount('reports', 2);
    }

    public function test_database_constraint_prevents_competing_open_records(): void
    {
        $r = Report::factory()->create();
        $this->expectException(QueryException::class);
        Report::factory()->create(['reporter_user_id' => $r->reporter_user_id, 'target_type' => $r->target_type, 'target_id' => $r->target_id, 'status' => 'IN_REVIEW']);
    }

    public function test_owner_scoped_list_detail_pagination_and_private_notes(): void
    {
        $u = User::factory()->create();
        $r = Report::factory()->create(['reporter_user_id' => $u->id, 'admin_note' => 'Confidential internal']);
        $other = Report::factory()->create();
        Report::factory()->create(['reporter_user_id' => $u->id, 'created_at' => now()->subDay()]);
        $this->login($u);
        $this->getJson('/api/v1/my-reports?per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.id', (string) $r->id)->assertJsonMissingPath('data.0.admin_note')->assertJsonMissingPath('data.0.reporter');
        $this->getJson('/api/v1/my-reports/'.$other->id)->assertNotFound();
        $this->getJson('/api/v1/my-reports/'.$r->id)->assertOk()->assertJsonMissingPath('data.admin_note');
        $this->getJson('/api/v1/my-reports?reporter_user_id='.$other->reporter_user_id)->assertUnprocessable();
    }

    public function test_admin_filters_details_pagination_and_safe_context(): void
    {
        $r = Report::factory()->create(['reason' => 'SCAM']);
        $s = Report::factory()->create(['target_type' => 'SHOP', 'target_id' => Product::factory()->active()->create()->shop_id, 'reason' => 'SPAM', 'status' => 'DISMISSED']);
        $this->login(User::factory()->admin()->create());
        $this->getJson('/api/v1/admin/reports?status=PENDING&target_type=PRODUCT&reason=SCAM&per_page=1')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', (string) $r->id)->assertJsonMissingPath('data.0.reporter.email');
        $this->getJson('/api/v1/admin/reports/'.$r->id)->assertOk()->assertJsonStructure(['data' => ['admin_note', 'reporter', 'target' => ['context' => ['shop']]]])->assertJsonMissingPath('data.target.context.shop.owner_id');
        $this->getJson('/api/v1/admin/reports?sort=oldest&from='.now()->toDateString().'&to='.now()->toDateString())->assertOk()->assertJsonPath('data.0.id', (string) $r->id);
        foreach (['status=BAD', 'target_type=App%5CModels%5CUser', 'reason=BAD', 'per_page=101', 'page=0', 'from=bad', 'sort=bad'] as $q) {
            $this->getJson('/api/v1/admin/reports?'.$q)->assertUnprocessable();
        }
    }

    public function test_transitions_expected_status_handler_and_confidential_notification(): void
    {
        $r = Report::factory()->create();
        $admin = User::factory()->admin()->create();
        $this->login($admin);
        $this->transition($r, 'RESOLVED')->assertConflict();
        $this->transition($r, 'IN_REVIEW', 'PENDING', ['handled_by' => 999])->assertUnprocessable();
        $this->transition($r, 'IN_REVIEW')->assertOk()->assertJsonPath('data.handler.id', (string) $admin->id);
        $this->transition($r, 'DISMISSED', 'PENDING')->assertConflict();
        $this->transition($r, 'RESOLVED', 'IN_REVIEW', ['admin_note' => '  Confidential details  '])->assertOk()->assertJsonPath('data.admin_note', 'Confidential details');
        $this->assertNotNull($r->fresh()->handled_at);
        $this->transition($r, 'RESOLVED', 'IN_REVIEW', ['admin_note' => 'overwrite'])->assertOk()->assertJsonPath('data.admin_note', 'Confidential details');
        $this->transition($r, 'IN_REVIEW', 'RESOLVED')->assertConflict();
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('audit_logs', 2);
        $this->login(User::findOrFail($r->reporter_user_id));
        $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('data.0.type', 'REPORT_RESULT')->assertJsonPath('data.0.target_route', '/reports')->assertJsonMissingPath('data.0.admin_note');
        $this->getJson('/api/v1/my-reports/'.$r->id)->assertOk()->assertJsonPath('data.status', 'RESOLVED')->assertJsonMissingPath('data.admin_note');
    }

    public function test_dismiss_from_pending_and_retry_notifies_once(): void
    {
        $r = Report::factory()->create();
        $this->login(User::factory()->admin()->create());
        $this->transition($r, 'DISMISSED')->assertOk();
        $this->transition($r, 'DISMISSED')->assertOk();
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_hidden_deleted_inactive_targets_cannot_be_reported_and_history_is_safe(): void
    {
        $u = User::factory()->create();
        $this->login($u);
        $p = Product::factory()->active()->create();
        $this->postJson('/api/v1/reports', $this->payload($p))->assertCreated();
        $r = Report::sole();
        $p->update(['status' => 'HIDDEN', 'name' => 'New private name']);
        $this->postJson('/api/v1/reports', $this->payload($p))->assertNotFound();
        $this->getJson('/api/v1/my-reports/'.$r->id)->assertOk()->assertJsonPath('data.target.available', false)->assertJsonPath('data.target.name', 'Nội dung không còn công khai')->assertJsonPath('data.target.route', null);
        $this->login(User::factory()->admin()->create());
        $this->getJson('/api/v1/admin/reports/'.$r->id)->assertOk()->assertJsonPath('data.target.status', 'HIDDEN');
        $p->delete();
        $this->getJson('/api/v1/admin/reports/'.$r->id)->assertOk()->assertJsonPath('data.target.deleted', true);
        $p->forceDelete();
        $this->getJson('/api/v1/admin/reports/'.$r->id)->assertOk()->assertJsonPath('data.target.available', false);
        $this->assertDatabaseCount('reports', 1);
    }

    public function test_shop_and_video_comment_visibility_and_missing_context(): void
    {
        $p = Product::factory()->active()->create();
        $v = ProductVideo::factory()->create(['product_id' => $p->id]);
        $c = VideoComment::factory()->create(['product_video_id' => $v->id]);
        $u = User::factory()->create();
        $this->login($u);
        $this->postJson('/api/v1/reports', ['target_type' => 'VIDEO_COMMENT', 'target_id' => $c->id, 'reason' => 'HARASSMENT'])->assertCreated();
        $r = Report::sole();
        $c->forceFill(['status' => 'HIDDEN'])->save();
        $this->postJson('/api/v1/reports', ['target_type' => 'VIDEO_COMMENT', 'target_id' => $c->id, 'reason' => 'SPAM'])->assertNotFound();
        $this->getJson('/api/v1/my-reports/'.$r->id)->assertOk()->assertJsonPath('data.target.available', false);
        $p->shop->update(['status' => 'SUSPENDED']);
        $this->postJson('/api/v1/reports', ['target_type' => 'SHOP', 'target_id' => $p->shop_id, 'reason' => 'SCAM'])->assertNotFound();
        $this->login(User::factory()->admin()->create());
        $this->getJson('/api/v1/admin/reports/'.$r->id)->assertOk()->assertJsonPath('data.target.context.body', $c->body)->assertJsonMissingPath('data.target.context.author.email');
        $c->delete();
        $this->getJson('/api/v1/admin/reports/'.$r->id)->assertOk()->assertJsonPath('data.target.available', false);
    }

    public function test_report_submission_is_rate_limited_per_account(): void
    {
        $p = Product::factory()->active()->create();
        $this->login(User::factory()->create());
        $this->postJson('/api/v1/reports', $this->payload($p))->assertCreated();
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/v1/reports', $this->payload($p))->assertConflict();
        }
        $this->postJson('/api/v1/reports', $this->payload($p))->assertStatus(429);
    }

    public function test_admin_list_queries_do_not_grow_per_report(): void
    {
        Report::factory()->create();
        $this->login(User::factory()->admin()->create());
        DB::enableQueryLog();
        $this->getJson('/api/v1/admin/reports')->assertOk();
        $one = count(DB::getQueryLog());
        DB::disableQueryLog();
        Report::factory()->count(8)->create();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/admin/reports')->assertOk();
        $many = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual($one + 1, $many);
    }
}
