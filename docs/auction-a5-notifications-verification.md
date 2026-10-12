# Auction A5 — persistent notifications and lifecycle verification

Date: 2026-10-08. Scope: A1–A4 auction events and existing winner Order flow; no bidding, settlement, stock, Order, Payment or authentication rule changes.

## Architecture and persistence

Laravel's database-notification infrastructure is the source of truth. The additive `notifications` table stores UUID, event type, owner morph, safe JSON data, created/read timestamps and a unique SHA-256 recipient/type/event deduplication key. Auction receives nullable `winner_checkout_reminded_at` plus an indexed reminder candidate query. These two reviewed migrations were applied additively; no development database reset was used.

`AuctionNotificationService` writes inside the existing bid/finalize/cancel/checkout transaction. Only newly inserted records dispatch `NotificationCreated` on the existing authorized private `user.{id}` channel, after the outer transaction commits. Rollbacks do not persist/broadcast. Existing AuctionOutbid remains; public auction events are unchanged. Synchronous broadcast failures are rescued by the existing event infrastructure: persisted records remain available through REST. No queue worker is required.

| Type | Recipients | Idempotency / target |
| --- | --- | --- |
| OUTBID | Only the previous highest bidder; excluded when raising own bid | Accepted new bid ID + recipient; public Arena / “Ra giá lại” |
| AUCTION_WON | Winner | Auction + recipient; winner checkout, decimal amount and deadline |
| AUCTION_ENDED | Seller and distinct losing bidders | Auction + recipient; owned Seller Arena/public Arena. Winner receives AUCTION_WON instead |
| AUCTION_CANCELLED | Seller | Auction + recipient; existing cancellation requires no accepted bids, so no Buyer recipients |
| WINNER_CHECKOUT_EXPIRING | Winner with no Order, inside threshold and before expiry | Auction + recipient and locked reminder flag; winner checkout |
| AUCTION_ORDER_CREATED | Winner and Seller | Auction + recipient; existing Buyer Order and Seller ShopOrder routes |

Safe resources contain ID/type, auction/product IDs, product name, plain-text message, local target and created/read timestamps; winner-related records also contain decimal winning amount and checkout deadline. No card, gateway metadata, credentials, recipient identifiers or deduplication key is returned. Historical product names remain notification context; navigating still enforces current visibility/ownership.

## APIs and frontend

Authenticated active-session endpoints:

- GET `/api/v1/notifications`: newest first, stable ID tie-breaker, page/per_page (1–100), filter all/unread/auction/orders, unread_count.
- GET `/api/v1/notifications/unread-count`.
- POST `/api/v1/notifications/{uuid}/read`: owner-scoped 404 for foreign IDs; repeated read preserves original read_at.
- POST `/api/v1/notifications/read-all`: only current owner, existing CSRF and domain-write throttle.

Shared `provideNotifications`/`useNotifications` context supplies recent rows, authoritative unread count, read/read-all and private-channel lifecycle. The bell appears in Buyer, Seller and Admin shells. Recent dropdown and authenticated `/notifications` page use the existing design system, readable loading/error/empty states, unread styling and All/Unread/Auction/Orders filters. There is no new dependency or UI framework.

Login, reconnect, browser focus/online/visibility refresh reload DB data; missed realtime delivery therefore survives offline usage. Realtime insertion suppresses duplicate UUIDs and reconciles with REST. Owner generation, request sequence and revision checks prevent stale account/read-count responses from overwriting newer state. Logout/unmount releases the subscription/listeners and clears private state. Target whitelist permits only existing Auction, winner checkout and owned order paths; external/malformed targets fall back to Notifications. Vue renders messages as text.

## Lifecycle and scheduler

Server time remains authoritative. Existing start/finalize/cancel methods retain their transaction/lock rules. Finalization retries do not move the winner/deadline or create duplicate notifications. Reminder re-reads status, winner, Order, deadline and reminder flag under the Auction row lock and marks it in the same transaction as persistence. A reminder that commits before checkout may remain in history; a reminder acquiring the lock after Order commit is suppressed. Repeated scheduler/reminder workers do not duplicate records.

Expiry is already derived from A4's stored checkout deadline: expired checkout is rejected under lock, without reserving stock or creating an Order. A5 does not add an expiry business state, second-winner fallback or extra stock mutation.

Local terminals (MySQL first; no container workflow):

```powershell
cd backend
php artisan config:clear
php artisan migrate:status
# Apply reviewed pending additive migrations only:
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

```powershell
cd frontend
npm run dev
```

```powershell
cd backend
php artisan reverb:start
```

```powershell
cd backend
php artisan schedule:work
```

Existing minute schedule runs `auctions:sync --limit=100` without overlapping. One reviewed manual pass: `php artisan auctions:sync --limit=100`. Backend env `AUCTION_WINNER_CHECKOUT_HOURS=24` remains unchanged; new `AUCTION_CHECKOUT_REMINDER_MINUTES=60`, 0 disables reminders. Clear config cache and restart local workers after env changes. Reverb uses configured private backend credentials and matching frontend public key only. Open http://localhost:5173; API http://127.0.0.1:8000.

## Automated verification

| Check | Result |
| --- | --- |
| Full backend, guarded MySQL secondhand_marketplace_test | PASS — 315 tests, 3,147 assertions |
| Notification/realtime/settlement focused regression | PASS — 40 tests, 274 assertions |
| Real MySQL concurrency regression | PASS — 12 tests, 69 assertions |
| Full frontend `npm run test:unit -- --run --maxWorkers=2` | PASS — 305 tests, 25 files |
| Pint `php vendor/bin/pint --dirty --format agent` | PASS — fixes applied, final pass clean |
| Frontend `npm run lint` | PASS |
| Frontend `npm run build` | PASS |
| Additive migration status | PASS — both A5 migrations applied |

New backend coverage: 14 notification tests plus six concurrency scenarios. Ownership/IDOR, guests, count/read/read-all, pagination/filter/validation, offline persistence, safe payload, outbid dedup/self raise, winner/finalization retries, no-bid end/cancel, rollback/no broadcast, reminder threshold/disabled/expired/exactly once, no reminder after Order, repeated scheduler start/finalize/reminder. Real separate PHP workers race finalization, bid near end, expiry vs checkout, reminder vs checkout, duplicate reminders, cancel vs scheduled activation; existing bid/checkout concurrency cases also run.

New frontend coverage: 17 tests for bell/panel/badge, read/read-all, offline DB load/reconnect, realtime insertion/dedup, safe navigation, plain text, filters/states, logout/unmount cleanup, stale owner/count responses, read failure, Escape focus and resize. No broad business refactor.

The first frontend run failed worker-start timeouts with unrestricted parallelism; rerunning with two workers passed. Browser found a resize event incorrectly passed Window to Node.contains; a dedicated resize handler and regression test fixed it. An early mobile keyboard assertion attempted focus on a bell hidden inside closed navigation; corrected verification opens the existing mobile menu first. Final browser run has zero console errors/uncaught exceptions.

## Browser and HTTP verification

36 confirmed checks through localhost:5173, Edge headless with separate browser sessions, actual cookie/CSRF authentication and Reverb:

1. Seller creates a LIVE auction through the existing form.
2. A bids 100,000; B bids 110,000. A receives private NotificationCreated and persistent OUTBID, opens the bell and marks it read.
3. A bids 120,000, leaves the application (channel disconnected); B bids 130,000. A returns/logs in and sees the second OUTBID from DB.
4. Notification page and visible bell checked at 1440/1024/768/390px; panel fits viewport, resize closes safely, no horizontal page overflow. Escape restores focus. No animation introduced.
5. A bids 140,000; only the disposable fixture deadline is shortened and service finalizes it. Winner gets AUCTION_WON once despite retry; Seller/loser get AUCTION_ENDED.
6. Only the fixture winner deadline is shortened to 30 minutes to check a reminder twice: exactly one WINNER_CHECKOUT_EXPIRING persists.
7. Winner notification navigates to authorized checkout. COD creates one existing Order/item at 140,000 VND, quantity one; fixture stock 5 → 4. Winner/Seller each receive AUCTION_ORDER_CREATED; mark-all returns unread count zero.
8. A valid different session cannot mark A's notification (404). Logout + Notifications URL redirects to Login; guest API returns 401.

Fixture evidence contained OUTBID 4, AUCTION_ENDED 2, AUCTION_WON 1, WINNER_CHECKOUT_EXPIRING 1, AUCTION_ORDER_CREATED 2. Cancel/no-bid cases were verified in backend tests, not this browser sequence. Admin shell uses the same tested bell; a separate Admin account browser demonstration was not performed.

## Development data preservation

Tests use the existing guarded dedicated test database, not development. Before temporary fixture creation, the initial snapshot detected independent cart/conversation edits (outside A5); those records were left untouched and recorded, and a fresh read-only snapshot was taken immediately before the fixture session. Cleanup removed only the three random temporary users, their shop/category/product/image, Auction/bids, address, COD Order/item/payment, notifications and their sessions. Temporary credentials manifest was deleted; browser helper processes stopped.

All 20 business-table row counts and content hashes matched the pre-fixture snapshot after cleanup, including the independently changed cart/conversation data. The only intentional development schema changes are the two additive A5 migrations. Auto-increment counters may advance from disposable inserts; migration rows are intentional and sessions are transient. No development migrate:fresh, password reset or unrelated record deletion occurred.

## Remaining limitations

- Scheduler/Reverb must be explicitly started locally. With realtime disabled, DB reload on login/focus/online/panel opening remains available; no notification-specific periodic poll is added.
- No retry outbox/queue or realtime exactly-once guarantee. DB records are authoritative and deduplicated; broadcast loss is recovered by REST.
- No historical A1–A4 event backfill, retention/pruning policy, notification email/push or general event preferences.
- Reminders missed while scheduler is down until after expiry are not sent late. A legitimate pre-checkout reminder remains in history after an Order is created; checkout still returns the original Order.
- Recipient fan-out runs synchronously inside lifecycle transactions. High bidder volumes/multi-node scheduler scaling are not load-tested; existing per-pass limits/unavailable auction deferral behavior remain unchanged.
- Signed ZaloPay/payment behavior was covered by full regression; no live payment or merchant credential changes in A5. Existing local MySQL/PHP runtime limitations remain as documented.
- No fallback bidder, deposits/penalties, wallet, reputation, video review or Theater Mode.

## A5 file inventory

New backend: `app/Events/NotificationCreated.php`; `app/Http/Controllers/Api/V1/NotificationController.php`; `app/Http/Requests/Domain/NotificationListRequest.php`; `app/Http/Resources/NotificationResource.php`; `app/Services/AuctionNotificationService.php`; `tests/Feature/AuctionNotificationTest.php`; `database/migrations/2026_10_08_085059_create_notifications_table.php`; `database/migrations/2026_10_08_085102_add_checkout_reminder_timestamp_to_auctions.php`.

Modified backend: `.env.example`; `config/auctions.php`; `app/Models/Auction.php`; `app/Services/AuctionService.php`; `app/Services/AuctionCheckoutService.php`; `app/Console/Commands/SyncAuctions.php`; `routes/api.php`; `tests/Feature/AuctionConcurrencyTest.php`; `tests/Support/auction-worker.php`.

New frontend: `src/api/notification.api.js`; `src/composables/useNotifications.js`; `src/components/notifications/NotificationBell.vue`; `src/components/notifications/NotificationList.vue`; `src/views/NotificationsView.vue`; `src/styles/notifications.css`; `src/__tests__/Notifications.spec.js`.

Modified frontend: `src/layouts/DefaultLayout.vue`; `src/components/seller/SellerShell.vue`; `src/components/admin/AdminShell.vue`; `src/router/index.js`.

Documentation: `README.md`; `docs/api-summary.md`; `docs/demo-guide.md`; `docs/deployment-guide.md`; this report. Total: 33 A5 source/documentation files, including 16 new files. The wider existing dirty Git inventory contains previous phases and is not attributed to A5. Ignored local logs/browser/fixture helpers are not deliverable source or credential files.

## Decision

**PASS — Auction A5 is ready for a local demo** with Laravel, Vite, Reverb and the scheduler running. Full backend/frontend regressions, Pint/lint/build, focused concurrency, browser/HTTP and fixture cleanup checks passed. Production scale/delivery guarantees remain limited as described above.
