# Auction A1 — Backend Core Verification

Date: 2026-10-07. Decision: **PASS for the backend core implementation and isolated regression verification.**

## Scope and environment

Laravel 13.33.0, Sanctum 4.3.3, PHP 8.4.25, PHPUnit 12.5.36 and Pint 1.32.1. Existing MySQL-compatible database connection and dedicated `secondhand_marketplace_test` database were used. No dependencies or frontend files were changed in A1.

The existing fixed-price APIs, checkout, payments, authentication and Product/Shop/User models were preserved. No auction order/payment integration, stock reservation, Product SOLD transition, realtime event or WebSocket/UI was added.

## Schema

Migration: `backend/database/migrations/2026_10_07_152208_create_auctions_and_bids_tables.php`.

- `auctions`: product/seller foreign keys; `start_price`, `current_price`, `bid_increment` as DECIMAL(15,2); microsecond start/end/timestamps; status; nullable winner and winning bid.
- `bids`: auction/user foreign keys, DECIMAL(15,2) amount and microsecond timestamps.
- Foreign keys restrict hard deletion of referenced products, users and bid history. A composite winning-bid foreign key ensures the selected bid belongs to its auction.
- CHECK constraints enforce supported statuses, positive amounts/increment, current price at least start price, end after start and consistent nullable winner fields.
- Internal generated `active_product_id` equals product_id only for SCHEDULED/LIVE. Its unique index enforces one unfinished auction per product, including concurrent create attempts. ENDED/CANCELLED rows permit subsequent auctions.
- Indexes cover seller/history, lifecycle due times, bid history/user and highest-bid selection.

Migration up/down paths were exercised by the test suites on the dedicated test database. The read-only development migration status check reports **Pending**. No development migration, migrate:fresh, reset, seeding or fixture writes were performed. This report does not claim that the new endpoints are ready to query an unmigrated development database.

Apply the additive migration when installing A1, without refreshing existing tables:

```powershell
cd backend
php artisan migrate --path=database/migrations/2026_10_07_152208_create_auctions_and_bids_tables.php --no-interaction
```

Use a compatible PHP 8.4 CLI with BCMath and PDO MySQL; the bundled XAMPP PHP 8.0 CLI is insufficient for the installed Laravel version.

## API

All paths below have prefix `/api/v1`.

| Method | Path | Access / result |
| --- | --- | --- |
| POST | /seller/auctions | Active authenticated product owner; create, 201 |
| GET | /seller/auctions | Active authenticated user; own auctions only, paginated |
| GET | /seller/auctions/{auction} | Seller ownership required |
| POST | /seller/auctions/{auction}/cancel | Seller ownership and valid cancellation required |
| GET | /auctions | Publicly visible, non-cancelled auctions, paginated |
| GET | /auctions/{auction} | Publicly visible product/shop context required |
| GET | /auctions/{auction}/bids | Same public visibility requirement; paginated, newest first |
| POST | /auctions/{auction}/bids | Active authenticated non-seller; validated bid, 201 |

Creation accepts only `product_id`, decimal-string `start_price`, decimal-string `bid_increment`, `starts_at`, `ends_at`. Bid creation accepts only decimal-string `amount`. The existing strict DomainRequest rejects unknown fields. Use ISO 8601 dates with explicit timezone; creation normalizes timestamps to UTC, matching the current application timezone.

Auction resources expose string amounts/IDs, minimal product context, status, winner identifiers and `server_time`. Collection responses also provide top-level `server_time`; bid responses include it. No passwords, emails, authentication tokens or internal generated key are returned. Bid histories expose bidder IDs without account profile data.

Existing Sanctum SPA, active-account and session/CSRF middleware protect authenticated endpoints. Existing public-read/domain throttles are reused; bids additionally have a per-user limit of 20 requests/minute. Seller management IDOR and self-bidding return 403; unavailable public contexts return 404. Lifecycle conflicts return 409; invalid amounts/fields return 422; unauthenticated writes return 401.

## Rules, locking and lifecycle

- Only the current active Shop owner can create an auction. Product must be ACTIVE, not soft-deleted, in stock; Shop and owner must be active/not soft-deleted; Category and every ancestor must be active in the existing maximum-three-level hierarchy.
- Seller/current price/status/winner are assigned on the server. All money validation and calculations use the existing DecimalPrice rule, string decimals, BCMath and DECIMAL database columns. JSON float/numeric money, scientific notation, nonpositive values, excessive precision and overflow are rejected.
- First accepted bid is at least start_price. Every later bid is at least current_price + bid_increment, including exact minimum increments. One buyer may bid repeatedly.
- Bid transaction locks accounts, Shop, Categories, Product and Auction in the existing marketplace lock order. It then revalidates current account/product eligibility, LIVE status, start <= server time < end, current price and minimum bid. The bid and price update commit together, with deadlock retries.
- Bid-history checks use locking reads after the auction lock, avoiding stale MySQL repeatable-read snapshots for concurrent first bids. Concurrent equal bids yield one accepted bid and one 422 rejection; two sufficiently different bids can both succeed sequentially if the second remains valid against the updated price.
- Creation derives LIVE when its start is already due, otherwise SCHEDULED. `AuctionService::start()` safely starts due scheduled auctions after revalidation; it never starts early or resurrects terminal states.
- Cancellation is deliberately conservative: owning active seller, SCHEDULED/LIVE, before end and **no accepted bids**. Cancel retries are idempotent; cancellations after any accepted bid/expiry/ended outcome fail.
- `AuctionService::finalize()` locks the auction and selects the highest accepted bid. No bids yields ENDED with null winner. Repeated calls preserve winner and updated_at; CANCELLED remains CANCELLED. Early finalization fails. Product stock/status, orders and payments are unchanged.

Run lifecycle synchronization:

```powershell
cd backend
php artisan auctions:sync --limit=100
php artisan schedule:work
```

`routes/console.php` schedules synchronization every minute with overlap protection. The bounded command finalizes expired SCHEDULED/LIVE auctions and starts due valid SCHEDULED auctions. Invalid/unavailable starts are deferred. Service checks use actual current time after row locks; bids are rejected after expiry even if the scheduled sync has not yet marked the auction ENDED. Public GETs do not mutate lifecycle state.

## Verification results

| Check | Result |
| --- | --- |
| Focused auction suite | **36 passed**, 223 assertions |
| Full backend regression after Pint | **267 passed**, 2,825 assertions, approximately 107 seconds |
| Pint | Formatted A1 files; final run clean |
| Route inventory | All **8** requested endpoints present |
| Development migration status | Read-only check: Pending; development DB untouched |

Commands executed with the available PHP 8.4 CLI (substitute the compatible local PHP executable for `php`):

```powershell
cd backend
php artisan test --compact --filter=Auction
php vendor/bin/pint --dirty --format agent
php artisan test --compact
php artisan route:list --path=auctions --except-vendor
php artisan migrate:status --path=database/migrations/2026_10_07_152208_create_auctions_and_bids_tables.php --no-interaction
```

Coverage includes first/higher/exact-increment bids, below-minimum rejection, self-bidding, seller IDOR, authentication/account status, before-start/exact-end/after-end checks, hidden/deleted/out-of-stock products, inactive/deleted Shops and sellers, inactive Category ancestry, tampered server fields, decimal correctness/overflow, paginated public history, rate limits, cancellation rules, start/sync timing, winner/no-winner finalization, retries, uniqueness and unchanged fixed-price data.

Four concurrency tests launch independent PHP processes with committed fixtures and a synchronization barrier, using actual database locks: equal-price first/later bids, duplicate auction creation, bid versus cancellation and duplicate finalization. Worker/bootstrap database guards refuse any database except `secondhand_marketplace_test`; fixture/barrier files are cleaned by test teardown/finally blocks.

Logs: ignored `.runtime/auction-a1-focused.log`, `.runtime/auction-a1-full.log`, `.runtime/auction-a1-pint.log` and `.runtime/auction-a1-pint-final.log`.

## A1 files

Created: AuctionStatus; Auction/Bid models and factories; the auctions/bids migration; AuctionPolicy; AuctionService; AuctionController; StoreAuctionRequest/StoreBidRequest/AuctionListRequest; AuctionResource/BidResource; SyncAuctions; AuctionTest/AuctionConcurrencyTest; tests/Support/auction-worker.php; this report.

Modified: backend/routes/api.php, backend/routes/console.php, backend/app/Providers/AppServiceProvider.php. Pre-existing workspace changes from earlier phases were preserved.

## Remaining limitations and decision

- Development installation still requires the forward migration and a running scheduler; no development schema or data was changed during verification.
- Minute-based synchronization can delay a scheduled LIVE/ENDED status update. Bid eligibility still uses authoritative server time.
- A1 leaves the fixed-price listing and inventory behavior intact. An auction does not reserve inventory, withdraw a fixed-price listing or create an obligation/order/payment. Unavailable context blocks further public access/bids; accepted bid history remains available to the seller and finalization preserves the recorded winner. Transactional sale/fulfillment coordination belongs to a later phase.
- No auction UI, realtime delivery, bid request idempotency key, proxy bidding, anti-sniping, payment or winner fulfillment exists in A1. A repeated bid request is evaluated as a new bid against the updated minimum; finalization and cancellation retries are idempotent.
- The existing broad Category locking convention is retained for coherent eligibility during moderation and can serialize writes across auctions; high-load throughput has not been benchmarked.
- HTTP feature tests exercise middleware and API authorization; no live-browser auction flow was claimed in this backend-only phase.

**Final: PASS — Auction A1 backend core and full existing backend regression.**
