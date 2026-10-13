<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Creating and maintaining vouchers: sellers for their own shop, admins for the platform. */
class VoucherManagementService
{
    /** Terms that can no longer change once a voucher has been used. */
    private const TERMS = ['code', 'type', 'value', 'min_order', 'max_discount', 'per_user_limit', 'starts_at'];

    public function __construct(private AuditService $audit) {}

    public function query(): Builder
    {
        return Voucher::query()->with('shop:id,name,slug,owner_id,deleted_at')->withExists('redemptions as has_redemptions')->orderByDesc('id');
    }

    public function create(User $actor, ?Shop $shop, array $data): Voucher
    {
        try {
            return DB::transaction(function () use ($actor, $shop, $data): Voucher {
                $voucher = Voucher::create([
                    ...array_intersect_key($data, array_flip(['code', 'type', 'value', 'min_order', 'max_discount', 'usage_limit', 'per_user_limit', 'starts_at', 'ends_at', 'status'])),
                    'scope' => $shop ? Voucher::SHOP : Voucher::PLATFORM, 'shop_id' => $shop?->id, 'created_by' => $actor->id,
                ]);
                $this->audit->record($actor, 'vouchers', $voucher->id, 'voucher.created', null, $voucher->status, $voucher->code);

                return $this->query()->findOrFail($voucher->id);
            }, 3);
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                throw ValidationException::withMessages(['code' => ['Mã này đã tồn tại.']]);
            }
            throw $e;
        }
    }

    public function update(User $actor, Voucher $voucher, array $data): Voucher
    {
        return DB::transaction(function () use ($actor, $voucher, $data): Voucher {
            $locked = Voucher::whereKey($voucher->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $locked);
            if (array_key_exists('status', $data)) {
                abort_unless($locked->status === $data['expected_status'], 409, 'Trạng thái mã đã thay đổi. Hãy tải lại trước khi thử lại.');
            }
            $changes = array_intersect_key($data, array_flip([...self::TERMS, 'usage_limit', 'ends_at', 'status']));
            if ($locked->redemptions()->exists()) {
                foreach (self::TERMS as $field) {
                    if (array_key_exists($field, $changes) && ! $this->same($locked, $field, $changes[$field])) {
                        throw ValidationException::withMessages([$field => ['Mã đã được sử dụng: chỉ đổi được trạng thái, ngày kết thúc và giới hạn lượt dùng.']]);
                    }
                }
                $changes = array_intersect_key($changes, array_flip(['usage_limit', 'ends_at', 'status']));
            }
            $next = (clone $locked)->fill($changes);
            if ($next->usage_limit !== null && $next->usage_limit < $locked->used_count) {
                throw ValidationException::withMessages(['usage_limit' => ['Giới hạn lượt dùng không được thấp hơn số lượt đã dùng ('.$locked->used_count.').']]);
            }
            if ($next->ends_at->lte($next->starts_at)) {
                throw ValidationException::withMessages(['ends_at' => ['Ngày kết thúc phải sau ngày bắt đầu.']]);
            }
            if ($next->type === Voucher::PERCENT && bccomp((string) $next->value, '100', 2) > 0) {
                throw ValidationException::withMessages(['value' => ['Phần trăm giảm phải từ 1 đến 100.']]);
            }
            if ($next->type === Voucher::FIXED && $next->max_discount !== null) {
                throw ValidationException::withMessages(['max_discount' => ['Mức giảm tối đa chỉ dùng cho mã giảm theo phần trăm.']]);
            }
            $before = $locked->status;
            $locked->fill($changes)->save();
            $this->audit->record($actor, 'vouchers', $locked->id, 'voucher.updated', $before, $locked->status, 'fields: '.implode(', ', array_keys($changes)));

            return $this->query()->findOrFail($locked->id);
        }, 3);
    }

    /** "Delete" keeps the row (orders point at it) and switches it off. Idempotent. */
    public function deactivate(User $actor, Voucher $voucher): Voucher
    {
        return DB::transaction(function () use ($actor, $voucher): Voucher {
            $locked = Voucher::whereKey($voucher->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('delete', $locked);
            if ($locked->status !== Voucher::INACTIVE) {
                $locked->update(['status' => Voucher::INACTIVE]);
                $this->audit->record($actor, 'vouchers', $locked->id, 'voucher.deactivated', Voucher::ACTIVE, Voucher::INACTIVE, $locked->code);
            }

            return $this->query()->findOrFail($locked->id);
        }, 3);
    }

    private function same(Voucher $voucher, string $field, mixed $value): bool
    {
        $current = $voucher->$field;

        return match (true) {
            in_array($field, ['value', 'min_order', 'max_discount'], true) => ($current === null) === ($value === null) && ($value === null || bccomp((string) $current, (string) $value, 2) === 0),
            $field === 'starts_at' => $current->equalTo($value),
            $field === 'per_user_limit' => ($current === null ? null : (int) $current) === ($value === null ? null : (int) $value),
            default => (string) $current === (string) $value,
        };
    }
}
