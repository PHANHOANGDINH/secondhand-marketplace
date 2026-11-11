<?php

namespace Tests\Feature;

use App\Enums\CategoryStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeaders(['Origin' => 'http://localhost:5173']);
    }

    private function loginAs(User $user): void
    {
        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->actingAs($user, 'web');
    }

    private function data(array $extra = []): array
    {
        return array_replace(['name' => 'Test Category', 'reason' => 'Catalog maintenance'], $extra);
    }

    public function test_public_roots_children_and_hidden_ancestors(): void
    {
        $root = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $root->id]);
        $leaf = Category::factory()->create(['parent_id' => $child->id]);
        Category::factory()->create(['status' => CategoryStatus::INACTIVE]);
        $this->getJson('/api/v1/categories')->assertOk()->assertJsonCount(1, 'data');
        $all = $this->getJson('/api/v1/categories?all=1')->assertOk()->json('data');
        $this->assertContains((string) $root->id, array_column($all, 'id'));
        $this->assertContains((string) $child->id, array_column($all, 'id'));
        $this->getJson('/api/v1/categories?parent_id='.$root->id)->assertOk()->assertJsonPath('data.0.id', (string) $child->id);
        $this->getJson('/api/v1/categories/'.$leaf->slug)->assertOk();
        $root->update(['status' => CategoryStatus::INACTIVE]);
        $this->getJson('/api/v1/categories/'.$leaf->slug)->assertNotFound();
        $this->getJson('/api/v1/categories?parent_id='.$child->id)->assertJsonCount(0, 'data');
    }

    public function test_guest_and_user_cannot_administer_categories(): void
    {
        $category = Category::factory()->create();
        $url = '/api/v1/admin/categories';
        $this->postJson($url, $this->data())->assertUnauthorized();
        $user = User::factory()->create();
        $this->loginAs($user);
        $this->getJson($url)->assertForbidden();
        $this->postJson($url, $this->data())->assertForbidden();
        $this->patchJson($url.'/'.$category->id, $this->data())->assertForbidden();
        $this->deleteJson($url.'/'.$category->id, ['reason' => 'Test'])->assertForbidden();
        foreach (['update', 'delete'] as $ability) {
            $this->assertFalse(Gate::forUser($user)->allows($ability, $category));
        }
    }

    public function test_admin_create_update_delete_with_stable_unique_slug_and_audit(): void
    {
        $admin = User::factory()->admin()->create();
        $this->loginAs($admin);
        $first = $this->postJson('/api/v1/admin/categories', $this->data())->assertCreated()->json('data');
        $second = $this->postJson('/api/v1/admin/categories', $this->data())->assertCreated()->assertJsonPath('data.slug', 'test-category-2')->json('data');
        $this->patchJson('/api/v1/admin/categories/'.$first['id'], $this->data(['name' => 'Renamed', 'status' => 'INACTIVE']))->assertOk()->assertJsonPath('data.slug', $first['slug']);
        $this->getJson('/api/v1/admin/categories')->assertJsonCount(2, 'data');
        $this->deleteJson('/api/v1/admin/categories/'.$second['id'], ['reason' => 'Unused'])->assertNoContent();
        $this->assertDatabaseCount('audit_logs', 4);
        $this->assertDatabaseMissing('categories', ['id' => $second['id']]);
    }

    public function test_self_cycle_depth_and_subtree_depth_are_rejected(): void
    {
        $this->loginAs(User::factory()->admin()->create());
        $root = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $root->id]);
        $leaf = Category::factory()->create(['parent_id' => $child->id]);
        foreach ([$root->id, $child->id, $leaf->id] as $parent) {
            $this->patchJson('/api/v1/admin/categories/'.$root->id, ['parent_id' => $parent, 'reason' => 'Move'])->assertUnprocessable()->assertJsonValidationErrors('parent_id');
        }
        $this->postJson('/api/v1/admin/categories', $this->data(['parent_id' => $leaf->id]))->assertUnprocessable()->assertJsonValidationErrors('parent_id');
        $other = Category::factory()->create();
        $this->patchJson('/api/v1/admin/categories/'.$root->id, ['parent_id' => $other->id, 'reason' => 'Move subtree'])->assertUnprocessable();
        $this->assertNull($root->refresh()->parent_id);
    }

    public function test_cannot_delete_referenced_category_even_with_soft_deleted_products(): void
    {
        $this->loginAs(User::factory()->admin()->create());
        $parent = Category::factory()->create();
        Category::factory()->create(['parent_id' => $parent->id]);
        $this->deleteJson('/api/v1/admin/categories/'.$parent->id, ['reason' => 'Delete'])->assertConflict();
        $product = Product::factory()->create();
        $product->delete();
        $this->deleteJson('/api/v1/admin/categories/'.$product->category_id, ['reason' => 'Delete'])->assertConflict();
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_category_rejects_invalid_parent_injected_slug_and_missing_reason(): void
    {
        $this->loginAs(User::factory()->admin()->create());
        $this->postJson('/api/v1/admin/categories', $this->data(['parent_id' => 9999, 'slug' => 'arbitrary']))->assertUnprocessable()->assertJsonValidationErrors(['parent_id', 'slug']);
        $this->postJson('/api/v1/admin/categories', ['name' => 'No reason'])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->getJson('/api/v1/admin/categories?per_page=101')->assertUnprocessable();
        $this->getJson('/api/v1/categories?unknown=yes')->assertUnprocessable();
    }

    public function test_category_change_and_audit_are_atomic(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        $name = $category->name;
        $this->mock(AuditService::class)->shouldReceive('record')->once()->andThrow(new \RuntimeException('audit unavailable'));
        try {
            app(CategoryService::class)->save($admin, $category, $this->data(['name' => 'Changed']));
            $this->fail('Expected failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('audit unavailable', $e->getMessage());
        }
        $this->assertSame($name, $category->refresh()->name);
    }
}
