<?php

namespace App\Services;

use App\Enums\CategoryStatus;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CategoryService
{
    public function __construct(private SlugGenerator $slugs, private AuditService $audit) {}

    public function publicQuery(): Builder
    {
        return Category::where('status', CategoryStatus::ACTIVE)->where(function (Builder $query): void {
            $query->whereNull('parent_id')->orWhereHas('parent', function (Builder $parent): void {
                $parent->where('status', CategoryStatus::ACTIVE)->where(function (Builder $ancestors): void {
                    $ancestors->whereNull('parent_id')->orWhereHas('parent', fn (Builder $root) => $root->where('status', CategoryStatus::ACTIVE)->whereNull('parent_id'));
                });
            });
        });
    }

    private function validateTree(array $parents): void
    {
        foreach (array_keys($parents) as $id) {
            $seen = [];
            $cursor = $id;
            while ($cursor !== null) {
                if (isset($seen[$cursor]) || count($seen) >= 3 || ! array_key_exists($cursor, $parents)) {
                    throw ValidationException::withMessages(['parent_id' => ['Category hierarchy must be acyclic and at most three levels deep.']]);
                }
                $seen[$cursor] = true;
                $cursor = $parents[$cursor];
            }
        }
    }

    public function save(User $user, ?Category $category, array $data): Category
    {
        return DB::transaction(function () use ($user, $category, $data): Category {
            $actor = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize($category ? 'update' : 'create', $category ?? Category::class);
            // Lock the small category tree, including the insertion range, before validating moves.
            $tree = Category::orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $locked = $category ? $tree->get($category->id) : new Category;
            abort_unless($locked, 404);
            $parents = $tree->map(fn (Category $item) => $item->parent_id)->all();
            $parents[$locked->id ?? 0] = array_key_exists('parent_id', $data) ? ($data['parent_id'] === null ? null : (int) $data['parent_id']) : $locked->parent_id;
            $this->validateTree($parents);
            $from = $locked->status?->value;
            $reason = $data['reason'];
            unset($data['reason']);
            if (! $category) {
                $data['slug'] = $this->slugs->generate(Category::query(), $data['name'], 150);
                $data['status'] ??= CategoryStatus::ACTIVE;
            }
            $locked->fill($data)->save();
            $this->audit->record($actor, 'categories', $locked->id, $category ? 'category.updated' : 'category.created', $from, $locked->status->value, $reason);

            return $locked;
        }, 3);
    }

    public function delete(User $user, Category $category, string $reason): void
    {
        try {
            DB::transaction(function () use ($user, $category, $reason): void {
                $actor = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('delete', $category);
                $tree = Category::orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $locked = $tree->get($category->id);
                abort_unless($locked, 404);
                abort_if($locked->children()->exists() || $locked->products()->withTrashed()->exists(), 409, 'Category has children or products. Deactivate it instead.');
                $this->audit->record($actor, 'categories', $locked->id, 'category.deleted', $locked->status->value, null, $reason);
                $locked->delete();
            }, 3);
        } catch (QueryException $error) {
            if (($error->errorInfo[1] ?? null) === 1451) {
                abort(409, 'Category is referenced and cannot be deleted.');
            }
            throw $error;
        }
    }
}
