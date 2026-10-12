<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Events\VideoCommentCreated;
use App\Events\VideoCommentHidden;
use App\Http\Resources\VideoCommentResource;
use App\Models\Product;
use App\Models\ProductVideo;
use App\Models\User;
use App\Models\VideoComment;
use App\Services\AuditService;
use App\Services\VideoCommentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class VideoCommentsTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        config(['broadcasting.default' => 'null']);
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Accept' => 'application/json']);
    }

    private function video(): ProductVideo
    {
        return ProductVideo::factory()->for(Product::factory()->active())->create();
    }

    private function login(User $user): void
    {
        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->actingAs($user, 'web');
    }

    private function url(ProductVideo $video): string
    {
        return '/api/v1/videos/'.$video->id.'/comments';
    }

    private function comment(ProductVideo $video, ?User $user = null): VideoComment
    {
        return VideoComment::factory()->create(['product_video_id' => $video->id, 'user_id' => ($user ?? User::factory()->create())->id]);
    }

    public function test_guest_reads_only_safe_published_data_and_cannot_write(): void
    {
        $v = $this->video();
        $c = $this->comment($v);
        $this->comment($v)->forceFill(['status' => 'HIDDEN'])->save();
        $r = $this->getJson($this->url($v))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.can_manage', false)->assertJsonPath('permissions.can_moderate', false);
        $this->assertSame(['id', 'body', 'created_at', 'author', 'can_manage'], array_keys($r->json('data.0')));
        $this->assertSame(['id', 'name'], array_keys($r->json('data.0.author')));
        $this->assertStringNotContainsString($c->user->email, $r->getContent());
        $this->postJson($this->url($v), ['body' => 'Hello'])->assertUnauthorized();
        $this->patchJson('/api/v1/video-comments/'.$c->id, ['status' => 'HIDDEN'])->assertUnauthorized();
    }

    public function test_post_trims_and_stores_html_as_plain_text_without_private_fields(): void
    {
        $v = $this->video();
        $u = User::factory()->create();
        $this->login($u);
        $r = $this->postJson($this->url($v), ['body' => " \u{00A0}<script>alert(1)</script> \n"])->assertCreated()->assertJsonPath('data.body', '<script>alert(1)</script>')->assertJsonPath('data.can_manage', true);
        $this->assertStringNotContainsString($u->email, $r->getContent());
        $this->assertDatabaseHas('video_comments', ['user_id' => $u->id, 'status' => 'PUBLISHED', 'body' => '<script>alert(1)</script>']);
    }

    public function test_validation_rejects_empty_long_nonstring_and_tampering(): void
    {
        $v = $this->video();
        $this->login(User::factory()->create());
        foreach ([[], ['body' => ''], ['body' => " \n\u{00A0} "], ['body' => str_repeat('a', 501)], ['body' => ['x']], ['body' => 'ok', 'user_id' => 10], ['body' => 'ok', 'status' => 'HIDDEN'], ['body' => 'ok', 'product_video_id' => 99]] as $data) {
            $this->postJson($this->url($v), $data)->assertUnprocessable();
        }
        $this->assertDatabaseCount('video_comments', 0);
    }

    public function test_invalid_video_and_product_shop_owner_category_context_rejects_reads_and_posts(): void
    {
        $this->login(User::factory()->create());
        foreach (['HIDDEN', 'FAILED', 'PROCESSING', 'product', 'shop', 'owner', 'category', 'stock', 'deleted'] as $state) {
            $v = $this->video();
            $this->comment($v);
            if (in_array($state, ['HIDDEN', 'FAILED', 'PROCESSING'], true)) {
                $v->forceFill(['is_primary' => false, 'status' => $state])->save();
            } else {
                $p = $v->product;
                match ($state) {
                    'product' => $p->update(['status' => 'HIDDEN']), 'shop' => $p->shop->update(['status' => 'SUSPENDED']),
                    'owner' => $p->shop->owner->forceFill(['status' => 'SUSPENDED'])->save(), 'category' => $p->category->update(['status' => 'INACTIVE']),
                    'stock' => $p->update(['stock_quantity' => 0]), 'deleted' => $p->delete(),
                };
            }
            $this->getJson($this->url($v))->assertNotFound();
            $this->postJson($this->url($v), ['body' => 'test'])->assertNotFound();
        }
        $this->postJson('/api/v1/videos/999999999/comments', ['body' => 'test'])->assertNotFound();
    }

    public function test_author_own_seller_admin_moderation_and_idor_without_text_editing(): void
    {
        $v = $this->video();
        $author = User::factory()->create();
        $c = $this->comment($v, $author);
        foreach ([User::factory()->create(), $this->video()->product->shop->owner] as $stranger) {
            $this->login($stranger);
            $this->patchJson('/api/v1/video-comments/'.$c->id, ['status' => 'HIDDEN'])->assertForbidden();
        }
        $admin = User::factory()->create();
        $admin->forceFill(['role' => UserRole::ADMIN])->save();
        foreach ([$author, $v->product->shop->owner, $admin] as $actor) {
            $c = $this->comment($v, $author);
            $this->login($actor);
            $this->patchJson('/api/v1/video-comments/'.$c->id, ['status' => 'HIDDEN', 'body' => 'Changed'])->assertUnprocessable();
            $this->patchJson('/api/v1/video-comments/'.$c->id, ['status' => 'PUBLISHED'])->assertUnprocessable();
            $this->patchJson('/api/v1/video-comments/'.$c->id, ['status' => 'HIDDEN'])->assertNoContent();
            $this->assertSame('HIDDEN', $c->refresh()->status->value);
            $this->assertSame('Sản phẩm còn hoạt động tốt không?', $c->body);
            $this->assertNotContains((string) $c->id, collect($this->getJson($this->url($v))->json('data'))->pluck('id')->all());
        }
        $this->patchJson('/api/v1/video-comments/99999999', ['status' => 'HIDDEN'])->assertNotFound();
    }

    public function test_current_user_permissions_deleted_author_and_inactive_user(): void
    {
        $v = $this->video();
        $author = User::factory()->create();
        $c = $this->comment($v, $author);
        $this->login($v->product->shop->owner);
        $this->getJson($this->url($v))->assertJsonPath('permissions.can_moderate', true)->assertJsonPath('data.0.can_manage', true);
        $author->delete();
        $this->getJson($this->url($v))->assertJsonPath('data.0.author.name', 'Người dùng')->assertJsonPath('data.0.author.id', null);
        $user = User::factory()->create();
        $user->forceFill(['status' => 'SUSPENDED'])->save();
        $this->login($user);
        $this->postJson($this->url($v), ['body' => 'Hi'])->assertForbidden();
        $this->patchJson('/api/v1/video-comments/'.$c->id, ['status' => 'HIDDEN'])->assertForbidden();
    }

    public function test_pagination_and_stable_older_cursor(): void
    {
        $v = $this->video();
        $cs = VideoComment::factory()->count(3)->create(['product_video_id' => $v->id]);
        $this->getJson($this->url($v).'?per_page=2')->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', (string) $cs[2]->id)->assertJsonPath('meta.total', 3);
        $this->getJson($this->url($v).'?per_page=2&page=2')->assertJsonCount(1, 'data');
        $this->comment($v);
        $this->getJson($this->url($v).'?before_id='.$cs[1]->id)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', (string) $cs[0]->id);
        $this->getJson($this->url($v).'?per_page=51')->assertUnprocessable();
    }

    public function test_rate_limit_is_per_account(): void
    {
        $v = $this->video();
        $this->login(User::factory()->create());
        for ($i = 0; $i < 10; $i++) {
            $this->postJson($this->url($v), ['body' => 'Comment '.$i])->assertCreated();
        }
        $this->postJson($this->url($v), ['body' => 'Too many'])->assertTooManyRequests();
        $this->login(User::factory()->create());
        $this->postJson($this->url($v), ['body' => 'Different account'])->assertCreated();
    }

    public function test_created_event_waits_for_outer_commit_and_has_safe_payload(): void
    {
        $v = $this->video();
        $u = User::factory()->create();
        Event::fake([VideoCommentCreated::class]);
        DB::beginTransaction();
        $c = app(VideoCommentService::class)->create($u, $v, 'Hello');
        Event::assertNotDispatched(VideoCommentCreated::class);
        DB::commit();
        Event::assertDispatched(VideoCommentCreated::class, function (VideoCommentCreated $e) use ($v, $c, $u): bool {
            $p = $e->broadcastWith();
            $this->assertSame('video.'.$v->id, $e->broadcastOn()[0]->name);
            $this->assertSame(['video_id', 'comment', 'server_time'], array_keys($p));
            $this->assertSame(['id', 'body', 'created_at', 'author'], array_keys($p['comment']));
            $this->assertSame((string) $c->id, $p['comment']['id']);
            $this->assertStringNotContainsString($u->email, json_encode($p));
            $this->assertSame(0, DB::transactionLevel());

            return true;
        });
    }

    public function test_outer_rollback_and_rejected_post_emit_nothing(): void
    {
        $v = $this->video();
        $u = User::factory()->create();
        Event::fake([VideoCommentCreated::class]);
        DB::beginTransaction();
        app(VideoCommentService::class)->create($u, $v, 'Will rollback');
        DB::rollBack();
        Event::assertNotDispatched(VideoCommentCreated::class);
        $this->assertDatabaseCount('video_comments', 0);
        $this->login($u);
        $this->postJson($this->url($v), ['body' => ' '])->assertUnprocessable();
        Event::assertNotDispatched(VideoCommentCreated::class);
    }

    public function test_hide_is_idempotent_after_commit_and_audit_failure_rolls_back(): void
    {
        $v = $this->video();
        $u = User::factory()->create();
        $c = $this->comment($v, $u);
        Event::fake([VideoCommentHidden::class]);
        $s = app(VideoCommentService::class);
        DB::beginTransaction();
        $s->hide($u, $c);
        Event::assertNotDispatched(VideoCommentHidden::class);
        DB::commit();
        $s->hide($u, $c->refresh());
        Event::assertDispatchedTimes(VideoCommentHidden::class, 1);
        Event::assertDispatched(VideoCommentHidden::class, function (VideoCommentHidden $e): bool {
            $this->assertSame(['video_id', 'comment_id', 'server_time'], array_keys($e->broadcastWith()));

            return true;
        });
        $fresh = $this->comment($v, $u);
        $audit = Mockery::mock(AuditService::class);
        $audit->shouldReceive('record')->andThrow(new \RuntimeException('Rollback'));
        $this->app->instance(AuditService::class, $audit);
        try {
            app(VideoCommentService::class)->hide($u, $fresh);
            $this->fail('Expected rollback');
        } catch (\RuntimeException $e) {
            $this->assertSame('Rollback', $e->getMessage());
        }
        $this->assertSame('PUBLISHED', $fresh->refresh()->status->value);
        Event::assertDispatchedTimes(VideoCommentHidden::class, 1);
    }

    public function test_delivery_guards_hidden_context_and_comment_and_video_delete_cascades(): void
    {
        $v = $this->video();
        $c = $this->comment($v);
        $p = ['video_id' => (string) $v->id, 'comment' => (new VideoCommentResource($c))->publicData(), 'server_time' => now()->toISOString()];
        $e = new VideoCommentCreated($p);
        $this->assertTrue($e->broadcastWhen());
        $c->forceFill(['status' => 'HIDDEN'])->save();
        $this->assertFalse($e->broadcastWhen());
        $c->forceFill(['status' => 'PUBLISHED'])->save();
        $v->forceFill(['is_primary' => false, 'status' => 'HIDDEN'])->save();
        $this->assertFalse($e->broadcastWhen());
        $v->delete();
        $this->assertDatabaseMissing('video_comments', ['id' => $c->id]);
    }

    public function test_stale_moderation_after_video_deletion_fails_as_not_found(): void
    {
        $video = $this->video();
        $user = User::factory()->create();
        $comment = $this->comment($video, $user);
        $video->delete();
        $this->expectException(ModelNotFoundException::class);
        app(VideoCommentService::class)->hide($user, $comment);
    }
}
