<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auctions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
            $table->decimal('start_price', 15, 2);
            $table->decimal('current_price', 15, 2);
            $table->decimal('bid_increment', 15, 2);
            $table->dateTime('starts_at', 6);
            $table->dateTime('ends_at', 6);
            $table->string('status', 16);
            $table->foreignId('winner_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('winning_bid_id')->nullable();
            $table->unsignedBigInteger('active_product_id')->nullable()->storedAs("CASE WHEN status IN ('SCHEDULED', 'LIVE') THEN product_id ELSE NULL END")->unique();
            $table->timestamps(6);
            $table->index(['status', 'starts_at', 'id']);
            $table->index(['status', 'ends_at', 'id']);
            $table->index(['seller_id', 'created_at', 'id']);
        });
        Schema::create('bids', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('auction_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamps(6);
            $table->unique(['auction_id', 'id']);
            $table->index(['auction_id', 'amount', 'id']);
            $table->index(['user_id', 'created_at', 'id']);
        });
        Schema::table('auctions', function (Blueprint $table): void {
            $table->foreign(['id', 'winning_bid_id'], 'auctions_winning_bid_fk')->references(['auction_id', 'id'])->on('bids')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE auctions ADD CONSTRAINT auctions_status_check CHECK (status IN ('SCHEDULED','LIVE','ENDED','CANCELLED'))");
        DB::statement('ALTER TABLE auctions ADD CONSTRAINT auctions_prices_check CHECK (start_price > 0 AND current_price >= start_price AND bid_increment > 0)');
        DB::statement('ALTER TABLE auctions ADD CONSTRAINT auctions_time_check CHECK (ends_at > starts_at)');
        DB::statement("ALTER TABLE auctions ADD CONSTRAINT auctions_winner_check CHECK ((winner_user_id IS NULL AND winning_bid_id IS NULL) OR (status = 'ENDED' AND winner_user_id IS NOT NULL AND winning_bid_id IS NOT NULL))");
        DB::statement('ALTER TABLE bids ADD CONSTRAINT bids_amount_check CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::table('auctions', fn (Blueprint $table) => $table->dropForeign('auctions_winning_bid_fk'));
        Schema::dropIfExists('bids');
        Schema::dropIfExists('auctions');
    }
};
