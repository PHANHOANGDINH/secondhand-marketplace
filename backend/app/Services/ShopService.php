<?php

namespace App\Services;

use App\Enums\ShopStatus;
use App\Enums\UserStatus;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

class ShopService
{
    public function __construct(private SlugGenerator $slugs, private AuditService $audit, private SquarePicture $pictures) {}

    public function publicQuery(): Builder
    {
        return Shop::where('status', ShopStatus::ACTIVE)->whereHas('owner', fn (Builder $q) => $q->where('status', UserStatus::ACTIVE));
    }

    public function create(User $user, array $data): Shop
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return DB::transaction(function () use ($user, $data): Shop {
                    $owner = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                    Gate::forUser($owner)->authorize('create', Shop::class);
                    abort_if(Shop::withTrashed()->where('owner_id', $owner->id)->exists(), 409, 'You already have a shop.');
                    $shop = $owner->shop()->create([...$data, 'slug' => $this->slugs->generate(Shop::withTrashed(), $data['name'], 180), 'status' => ShopStatus::ACTIVE]);
                    // Shops open at once; admins suspend a shop when reports show it misbehaves.
                    $this->audit->record($owner, 'shops', $shop->id, 'shop.created', null, ShopStatus::ACTIVE->value, null);

                    return $shop;
                }, 3);
            } catch (UniqueConstraintViolationException) {
                // Retry in a new transaction after a competing slug allocation.
            }
        }
        abort(409, 'Shop creation conflicted. Please retry.');
    }

    public function update(User $user, Shop $shop, array $data): Shop
    {
        return DB::transaction(function () use ($user, $shop, $data): Shop {
            $owner = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $locked = Shop::whereKey($shop->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($owner)->authorize('update', $locked);
            $locked->fill($data);
            $changed = array_keys($locked->getDirty());
            if ($changed) {
                $locked->save();
                // Editing shop details does not trigger re-moderation; the audit names the fields, not their text.
                $this->audit->record($owner, 'shops', $locked->id, 'shop.updated', $locked->status->value, $locked->status->value, 'fields: '.implode(', ', $changed));
            }

            return $locked;
        }, 3);
    }

    public function replaceLogo(User $user, Shop $shop, UploadedFile $file): Shop
    {
        $path = $this->pictures->store($file, 'shop-logos/'.$shop->id);
        try {
            [$locked, $previous] = $this->swapLogo($user, $shop, $path);
        } catch (Throwable $exception) {
            $this->pictures->discard($path);
            throw $exception;
        }
        $this->pictures->discard($previous);

        return $locked;
    }

    public function removeLogo(User $user, Shop $shop): Shop
    {
        [$locked, $previous] = $this->swapLogo($user, $shop, null);
        $this->pictures->discard($previous);

        return $locked;
    }

    /** @return array{0: Shop, 1: ?string} the saved shop and the path it no longer uses */
    private function swapLogo(User $user, Shop $shop, ?string $path): array
    {
        return DB::transaction(function () use ($user, $shop, $path): array {
            $owner = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $locked = Shop::whereKey($shop->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($owner)->authorize('update', $locked);
            $previous = $locked->logo_path;
            if ($previous !== $path) {
                $locked->forceFill(['logo_path' => $path])->save();
                $this->audit->record($owner, 'shops', $locked->id, 'shop.updated', $locked->status->value, $locked->status->value, 'fields: logo_path');
            }

            return [$locked, $previous];
        }, 3);
    }

    public function submit(User $user, Shop $shop): Shop
    {
        return DB::transaction(function () use ($user, $shop): Shop {
            $owner = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $locked = Shop::whereKey($shop->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($owner)->authorize('submit', $locked);
            abort_unless($locked->status === ShopStatus::REJECTED, 409, 'Only rejected shops can be submitted again.');
            $locked->update(['status' => ShopStatus::PENDING]);
            $this->audit->record($owner, 'shops', $locked->id, 'shop.submitted', ShopStatus::REJECTED->value, ShopStatus::PENDING->value, null);

            return $locked;
        }, 3);
    }

    public function moderate(User $user, Shop $shop, array $data): Shop
    {
        return DB::transaction(function () use ($user, $shop, $data): Shop {
            $accounts = User::withTrashed()->whereIn('id', [$user->id, $shop->owner_id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $locked = Shop::whereKey($shop->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($accounts[$user->id])->authorize('moderate', $locked);
            $from = $locked->status->value;
            $allowed = ['PENDING' => ['ACTIVE', 'REJECTED'], 'ACTIVE' => ['SUSPENDED'], 'SUSPENDED' => ['ACTIVE'], 'REJECTED' => []];
            abort_unless($data['expected_status'] === $from && in_array($data['status'], $allowed[$from], true), 409, 'Shop status has changed or transition is invalid.');
            if ($data['status'] === ShopStatus::ACTIVE->value) {
                $owner = $accounts[$locked->owner_id];
                abort_unless($owner->status === UserStatus::ACTIVE && ! $owner->trashed(), 409, 'Shop owner is not active.');
            }
            $locked->update(['status' => $data['status'], 'moderation_reason' => $data['reason']]);
            $this->audit->record($accounts[$user->id], 'shops', $locked->id, 'shop.moderated', $from, $data['status'], $data['reason']);
            if ($data['status'] === ShopStatus::SUSPENDED->value) {
                // Resolved lazily: AuctionService depends on this service through ProductQuery.
                app(AuctionService::class)->cancelForSuspendedShop($accounts[$user->id], $locked, 'Gian hàng bị tạm đình chỉ');
            }

            return $locked;
        }, 3);
    }
}
