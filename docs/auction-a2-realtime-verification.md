# Auction A2 — Reverb + Echo Verification

Date: 2026-10-07. **PASS** for local realtime infrastructure, security, transactional delivery and regression verification.

## Scope

A1 auction ownership, public visibility, DECIMAL/BCMath pricing, bid minimums, row locking, cancellation and winner rules are unchanged. No Arena UI, auction order/payment integration, Product SOLD transition, Docker or new polling loop was introduced. Reusable frontend infrastructure is ready for A3; existing marketplace pages do not subscribe automatically.

## Migration and data preservation

Reviewed `2026_10_07_152208_create_auctions_and_bids_tables.php`: additive auctions/bids tables, constraints and indexes only; no existing application-table alterations or deletes. Ran `php artisan migrate --no-interaction`; only the pending A1 migration ran, now batch 4 / Ran. No development `migrate:fresh`, reset or unrelated seeding.

Full database tests use the guarded `secondhand_marketplace_test` MySQL database. Browser verification created three temporary users, one shop/category/product/auction and five bids in `secondhand_marketplace`. Cleanup removed only those identified fixtures and their sessions. Before/after counts and SHA-256 hashes of all rows matched across **16 business tables**, including users, products, orders, payments, reviews, messaging and auction tables. Auto-increment counters can advance; the intentional migration entry remains. The temporary browser harness and credential manifest were removed.

## Packages and configuration

- Backend: `laravel/reverb` **1.12.0**, with its required PHP dependencies. Laravel stays **13.33.0**. Compatibility required Guzzle 7.15.5, promises 2.5.3 and PSR-7 2.13.1 instead of their previously locked major versions. Composer validate PASS; full regression PASS.
- Frontend: `laravel-echo` **2.6.1**, `pusher-js` **8.6.0**.
- `config/broadcasting.php`, `config/reverb.php` and both `.env.example` files provide env-driven configuration. Actual local credentials were generated into ignored `.env` files; no real secrets were added to source/examples/docs.

Backend private `.env`:

```dotenv
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
REVERB_ALLOWED_ORIGINS=localhost
```

Fill app ID/key/secret with unique local values before starting Reverb. The key is a public client identifier; the secret stays backend-only. `REVERB_ALLOWED_ORIGINS` contains comma-separated allowed **hostnames**, as consumed by Reverb. Client-origin checks are separate from API CORS and authorization; no wildcard was added.

Frontend private `.env`:

```dotenv
VITE_API_BASE_URL=/
VITE_BACKEND_PROXY_TARGET=http://127.0.0.1:8000
VITE_REVERB_APP_KEY=
VITE_REVERB_HOST=127.0.0.1
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
```

Set the frontend key equal to backend `REVERB_APP_KEY`. Never expose `REVERB_APP_SECRET` through a Vite variable. Existing Axios credentials/XSRF handling and the Vite same-origin API/CSRF proxy are preserved.

## Exact local commands

Use compatible PHP 8.4 and Composer on PATH; XAMPP MySQL must already run. The verification machine used Herd PHP 8.4 CLI because XAMPP's older PHP cannot run the lockfile.

```powershell
cd backend
composer install
php artisan config:clear
php artisan migrate:status
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

In separate terminals:

```powershell
cd backend
php artisan reverb:start
```

```powershell
cd backend
php artisan schedule:work
```

```powershell
cd frontend
npm install
npm run dev
```

Frontend: http://localhost:5173. Backend: http://127.0.0.1:8000. WebSocket: ws://127.0.0.1:8080. The existing `auctions:sync` schedule runs each minute; bid expiry is enforced immediately by server time even before the next scheduler tick. Events dispatch synchronously **after commit**, so no queue worker is required for these events. Restart Reverb after backend env changes and Vite after frontend env changes.

## Channels, events and security

| Channel | Event | Contents / access |
|---|---|---|
| `auction.{id}` | `AuctionBidPlaced` | Auction ID, current price, minimum next bid, bid count, display-safe latest bid ID/label/amount/time, update time and server time. |
| `auction.{id}` | `AuctionStatusChanged` | Auction ID, status, start/end times, price, update time and server time. |
| `private-user.{id}` | `AuctionOutbid` | Auction ID, safe product ID/name, new price, next minimum and server time; only the different previous highest bidder. |

Public payloads are scalar snapshots, not serialized User models: no email, address, session, payment data or real account name. Product/shop public visibility is checked again at public event delivery. Reverb rejects client events (`accept_client_events_from=none`), so clients cannot inject authoritative bid/status events.

`POST /api/v1/broadcasting/auth` runs through existing stateful API middleware, Sanctum, active-account and SPA-session checks, strict channel/socket validation and the existing domain throttle. Only the active, non-deleted session user's own `user.{id}` is authorized. Extra fields, malformed socket IDs, unsupported/foreign channel names and unauthenticated requests are rejected. No authentication/CSRF exemptions were added.

Events implement `ShouldDispatchAfterCommit`, `ShouldBroadcastNow` and `ShouldRescue`: successful outermost commit precedes delivery; rollback, rejected/self/expired bids emit no success event. Network failure is reported by Laravel but does not undo committed bidding or turn a successful REST bid into an error. Lifecycle retries do not rebroadcast unchanged transitions. Outbidding oneself emits no private notification.

## Frontend lifecycle and recovery

`auctionEcho.js` lazily creates one Echo/Pusher connection, shares each channel/listener across consumers, exposes connection state and disconnects after the last consumer releases. Private authorization uses the existing CSRF helper and Axios; stale authorization after unsubscribe/logout is discarded. HMR, scope disposal and route-ID changes clean subscriptions and online listeners.

`useAuctionRealtime(id)` loads the public REST snapshot, receives bid/status events, rejects foreign/stale/duplicate updates and refreshes REST after subscription/reconnection. Generation/request guards discard stale route/unmount responses; 403/404 clears unavailable state. No interval polling is used. `useUserRealtime()` follows the existing authenticated user and clears notification/subscription on logout.

`createAuctionClock()` computes server offset and advances from a monotonic clock, tolerating browser wall-clock jumps. Countdown is display-only; bid acceptance always comes from the backend.

## Verification results

| Verification | Result |
|---|---|
| Backend realtime/security/after-commit | **10 passed / 55 assertions** |
| A1 + A2 auction tests, including real MySQL races | **46 passed / 278 assertions** |
| Complete backend suite | **277 passed / 2,880 assertions** |
| New frontend realtime tests | **16 passed** |
| Complete frontend suite | **241 passed**, 22 files |
| Pint changed PHP files / final check | **PASS** |
| Frontend oxlint + ESLint | **PASS** |
| Frontend production build | **PASS** |
| Composer manifest/lock validation | **PASS** |
| Real two-browser/session checks | **17 passed**, no console errors/uncaught exceptions |
| Development fixture cleanup/content hashes | **PASS**, 16 tables preserved |

Commands:

```powershell
cd backend
php artisan test --compact --filter=AuctionRealtimeTest
php artisan test --compact --filter=Auction
php artisan test --compact
vendor\bin\pint --dirty --test
```

```powershell
cd frontend
npm run test:unit -- --run
npm run lint
npm run build
```

### Live browser checks

Two independent Edge browser profiles logged in through the real Login form as separate temporary buyers. A temporary Vue verification harness imported the actual new composables; no application route/UI was committed for the Arena.

1. A bid 100.00, B bid 110.00, A bid 120.00, B bid 130.00: both browsers updated without refresh/polling; only the previous different highest bidder received private outbid.
2. Two auction consumers per browser shared one subscription. Each connected socket received exactly one public event for each successful live bid.
3. Cross-user private authorization returned HTTP 403. Leaving/re-entering the auction subscription cleared/reloaded state and retained single event delivery.
4. Stopped the verification Reverb process. A bid 140.00 still returned HTTP 201 through REST. Restarted Reverb; both clients reconnected and recovered 140.00 from REST despite the missed event.
5. Expired/finalized only the fixture auction. Both clients received ENDED. A subsequent bid returned 409 with no success broadcast.
6. Unmounted the harness, checked zero console errors/uncaught exceptions, closed verification browsers and removed fixtures/harness.

Unit/regression tests additionally prove outer-transaction rollback/no events, self/below-minimum/expired rejection, lifecycle idempotency, inactive/guest/private-channel denial, malformed authorization, listener cleanup, reconnect callbacks, stale REST responses, clock skew and logout cleanup.

## Troubleshooting and remaining limitations

- Missing/mismatched keys: configure both private env files, clear Laravel config, restart Reverb/Vite. A blank frontend key disables realtime safely while REST remains available.
- Port 8080 in use: choose a free port consistently in backend server/broadcast and frontend env. Port 5173 already had the project Vite process during verification; it was reused rather than stopping an unrelated process.
- Private auth 401/419/403: check SPA session, CSRF cookie, localhost:5173 Sanctum origin and own-user channel. Do not bypass authorization/CSRF to solve it.
- Status not advancing: start `schedule:work`; normal lifecycle dispatch can lag up to the minute tick. Countdown never grants bid eligibility.
- No final Auction Arena, visible outbid notification UI or auction checkout yet, by A2 scope. Subscriptions start only when a consumer uses the composables.
- Delivery is best-effort, not durable/retried: an outage can lose private outbid notifications. REST recovers public auction truth on reconnect; it does not replay missed private notifications. A durable outbox/notification history is outside this phase.
- HTTP/ws and loopback binding are local-demo settings. Production TLS/reverse proxy, process supervision, scaling/load testing and session revocation for already connected sockets are not verified. Private subscription authorization is checked at subscribe time; the supplied composable unsubscribes when its authenticated user logs out.
- npm audit reported existing transitive advisories in `shell-quote` (npm-run-all2) and `source-map-js` (Vue/build tooling), rather than the new Echo/Pusher packages. Broad dependency remediation was not part of A2. This PASS is scoped to the verified local A2 implementation, not a production security certification.

References: [Laravel Reverb](https://laravel.com/framework/docs/13.x/reverb), [Laravel broadcasting](https://laravel.com/framework/docs/broadcasting), [Sanctum SPA authentication](https://laravel.com/framework/docs/13.x/sanctum).

## A2 file inventory

Created: backend `config/broadcasting.php`, `config/reverb.php`, `routes/channels.php`, `app/Events/AuctionBroadcastEvent.php`, `AuctionBidPlaced.php`, `AuctionOutbid.php`, `AuctionStatusChanged.php`, `app/Http/Middleware/ValidateBroadcastSubscription.php`, `tests/Feature/AuctionRealtimeTest.php`; frontend `src/api/auction.api.js`, `src/composables/auctionEcho.js`, `auctionTimeSync.js`, `useAuctionRealtime.js`, `useUserRealtime.js`, `src/__tests__/AuctionEcho.spec.js`, `AuctionRealtime.spec.js`; this report.

Modified for A2: backend `composer.json`, `composer.lock`, `.env.example`, `bootstrap/app.php`, `app/Services/AuctionService.php`, `app/Http/Controllers/Api/V1/AuctionController.php`, `app/Http/Resources/AuctionResource.php`; frontend `package.json`, `package-lock.json`, `.env.example`; root `README.md`. Private ignored `.env` files were configured locally. Existing dirty files from earlier phases are preserved and are not all A2 changes.

**Final decision: PASS — Auction A2 local realtime infrastructure is ready for A3 integration.**
