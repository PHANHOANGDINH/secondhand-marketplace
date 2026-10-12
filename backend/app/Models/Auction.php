<?php

namespace App\Models;

use App\Enums\AuctionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Auction extends Model
{
    use HasFactory;

    protected $guarded = ['*'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return ['winner_checkout_reminded_at' => 'immutable_datetime', 'winner_checkout_expires_at' => 'immutable_datetime', 'settled_at' => 'immutable_datetime', 'winner_order_id' => 'integer', 'start_price' => 'decimal:2', 'current_price' => 'decimal:2', 'bid_increment' => 'decimal:2', 'status' => AuctionStatus::class, 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime'];
    }

    public function settlementState(): ?string
    {
        if ($this->status !== AuctionStatus::ENDED) {
            return null;
        }
        if (! $this->winner_user_id) {
            return 'NO_WINNER';
        }
        if ($this->winner_order_id) {
            return 'ORDER_CREATED';
        }

        return $this->winner_checkout_expires_at?->isFuture() ? 'AWAITING_CHECKOUT' : 'CHECKOUT_EXPIRED';
    }

    public function winnerOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'winner_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id')->withTrashed();
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_user_id')->withTrashed();
    }

    public function winningBid(): BelongsTo
    {
        return $this->belongsTo(Bid::class, 'winning_bid_id');
    }
}
