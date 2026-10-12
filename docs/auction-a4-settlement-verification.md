# Auction A4 — winner checkout and order/payment verification

Verified: 2026-10-08. Decision: **PASS for the A4 MVP scope**.

## Scope and lifecycle

Existing auction states remain SCHEDULED, LIVE, ENDED and CANCELLED. Payment status stays on the existing Order/Payment architecture.

The additive migration adds nullable `auctions.winner_checkout_expires_at`, unique nullable `auctions.winner_order_id` (Order FK), and `auctions.settled_at`. `settled_at` records Order creation, not payment completion. A unique nullable `order_items.auction_id` FK provides traceability and prevents more than one winning OrderItem for an auction. Existing fixed-price items keep a null auction reference.

Derived settlement states:

| State | Meaning |
| --- | --- |
| NO_WINNER | ENDED without bids; no checkout claim or Order |
| AWAITING_CHECKOUT | ENDED with a winner, no Order, future checkout deadline |
| ORDER_CREATED | Winner Order already exists; cancellation never reopens this auction for a second winning Order |
| CHECKOUT_EXPIRED | Winner deadline passed without an Order; no stock was reserved |

Finalization selects the existing highest valid Bid and starts the claim window once. `AUCTION_WINNER_CHECKOUT_HOURS=24` configures the default 24 hours after server finalization. Repeated finalization preserves the original deadline. Pre-A4 ended winners receive a deadline based on their original `updated_at`, rather than being granted a fresh window during migration. No-winner finalization creates neither Order nor Payment and does not alter stock.

Expiry is derived from server time on reads and checked again under locks on writes. It does not need a cleanup job or mutate stock. The existing `auctions:sync` scheduler still starts/finalizes due auctions; it must run for prompt finalization. Server timestamps, including microseconds at the expiry boundary, are authoritative.

## Fixed-price exclusivity

`FixedPriceEligibility` centralizes Scheduled/Live and unexpired unfulfilled winner claims. Cart add/update and fixed-price checkout perform current locking reads after locking the Product. Cart snapshots batch-load reservations and retain affected items with `AUCTION_RESERVED`; the UI explains why they cannot be purchased. Seller auction creation uses the same rule. Seller eligibility also excludes active winner claims.

**Interpretation of the two conflicting stock requirements:** A4 asks both to block fixed-price buying after a winner Order exists and to keep remaining stock purchasable. This implementation follows the more specific one-unit/remaining-stock requirement: Scheduled/Live/awaiting-checkout reserves the Product against fixed-price purchases; creating the winning Order consumes exactly one unit and closes that auction claim permanently. If stock remains positive, fixed-price purchasing and another auction can use the remaining units. Stock zero retains existing SOLD behavior. Cancelling the winning Order restores its unit through existing rules, but never allows a second winning Order for that auction.

## API and frontend

| Endpoint | Access and behavior |
| --- | --- |
| GET `/api/v1/auctions/{auction}/checkout` | Active authenticated winner only; auction, winning DB price, product context, own addresses, deadline, remaining seconds, payment methods, availability and existing winner Order |
| POST `/api/v1/auctions/{auction}/checkout` | Winner only; `address_id`, `payment_method`, optional existing ZaloPay channels; UUID `Idempotency-Key` header; existing SPA/session and payment throttles |

FormRequest rejects extra fields including price, product, quantity, seller, winner and auction overrides. Policy rejects guests, losers and sellers. The service rechecks winner, ended status, deadline, current product/shop ownership, account/category/shop/product eligibility, stock, own address and the winning Bid relation inside the transaction.

Creation returns 201; same-payload retries return the original Order with 200, even with a different UUID or after the deadline. Changed payloads or keys already used for another checkout return 409. GET remains available to the winner for an expired/already-ordered claim, including when the product later stops being publicly visible. Public auction responses expose settlement state/deadline, not the private Order/shipping/payment details.

`/auctions/:id/checkout` is authenticated and preserves safe intended-return through Login. Arena displays the winner CTA only to the winner; sellers and losers have no checkout action. The focused checkout shows immutable quantity one and winning price, local date/time deadline, server-synchronized countdown, owned shipping address, COD/ZaloPay and total. It handles expiration, unavailability, unauthorized access, pending submit, validation errors, existing Order and uncertain network retries. Uncertain requests retain their key/payload in session storage; checking them remains possible after deadline/stock changes because an Order may already have been created. Stale responses cannot redirect a different logged-in user.

Existing Buyer/Seller OrderItems display the reusable `AuctionOrderSource` label and appropriate link. Admin Orders show a plain auction reference without inventing an Admin auction route. Payment pending/failure/retry/completion stays in the existing Order/payment-result screens.

## Order, stock and payments

The existing construction block was extracted unchanged from `CheckoutService` into `OrderCreator`; both fixed-price and auction checkout reuse it. Winning checkout creates one Order, one ShopOrder, one OrderItem with quantity one and `product_price`/subtotal/total from the locked winning Bid. Address snapshots, zero MVP shipping/discount, BCMath totals, audit records, SOLD marking and initial Payment creation remain shared.

Locks follow the existing account → address/shop → category → product → auction order. Seller/buyer accounts and Product locks serialize checkout against auction creation and fixed-price purchase. Order creation, stock decrement and winner Order linkage commit atomically. Existing `ShopOrderService` handles cancellation and restores stock once; it was not changed.

COD starts PENDING and is not marked PAID at checkout. ZaloPay reuses `ZaloPayService` and `ZaloPayGateway`: the attempt is PENDING, gateway HTTP runs after the transaction, callbacks/query verification and retry rules remain unchanged. A redirect never marks payment PAID. Decimal bids remain exact for COD; the existing gateway requires whole positive VND, so fractional winning prices offer COD only and server-side ZaloPay requests are rejected without creating an Order.

## Automated verification

| Check | Final result |
| --- | --- |
| Full backend | **295 passed; 3,033 assertions** |
| A4 backend additions | 16 settlement/security/payment tests + 2 real MySQL concurrency tests |
| Earlier focused backend run | 85 passed; 744 assertions, before the final two payment tests were added |
| Full frontend | **288 passed; 24 test files** |
| A4 frontend additions | 15 checkout/source/retry tests + 2 Arena winner tests |
| Pint | PASS; applied formatting with `--dirty --format agent` |
| Frontend lint | PASS: oxlint and ESLint |
| Frontend production build | PASS; no new dependency; Echo chunk remains 94.46 kB / 26.51 kB gzip |
| Git diff whitespace | PASS |

Backend covers configured/idempotent finalization, winning-price decimals, snapshots, stock, COD, same/different-key retries, changed payload, fixed/auction key collision, guest/loser/seller IDOR, foreign address, forbidden fields, deadline boundary, expiry release, Live/Scheduled/claim cart exclusion, retained cart rows, new auction exclusion, no winner, hidden product/inactive shop, SOLD/cancel/restock once, remaining fixed stock, ZaloPay retries without new Order/stock, wrong-amount signed callback, valid/replayed callback and fractional-price rejection.

Separate PHP workers use committed fixtures and barriers against `secondhand_marketplace_test`:

- Parallel winner checkout with different keys: statuses 201/200, same Order ID, one OrderItem, one decrement.
- Fixed-price checkout racing winner checkout for the last unit: winner 201, fixed checkout 409; one Order, one item, stock zero.

Existing A1 bid/finalization races, A2 broadcast/auth tests and the complete fixed-price/payment/auth/messaging regression remain passing. The A3 seller cancellation test now freezes its fixture clock so its dated auction does not expire based on the time the suite is run.

## Browser and HTTP verification

**40 confirmed checks**, zero application console errors/uncaught exceptions, at frontend `http://localhost:5173`, backend `http://127.0.0.1:8000`, Reverb `127.0.0.1:8080`. Two independent Edge profiles used real SPA sessions and Reverb connections.

- Seller creates a LIVE auction through the actual form; two Buyers bid 100,000 → 110,000 → 120,000 VND; controlled fixture finalization emits the existing committed end event and the winner CTA appears.
- Loser has no winner CTA and receives 403 from checkout GET. Winner loads only their own shipping address and the deadline.
- COD confirmation creates an ordinary Buyer Order with auction source; revisiting checkout links to that same Order. The Order is visible in Seller orders/detail with source context.
- Repeat auction/bidding/winner flow with real configured ZaloPay Sandbox: winning Order/payment amount 120,000 VND; PENDING attempt receives a gateway URL and the browser reaches `qcgateway.zalopay.vn/pay/v2/`. No card/bank data was entered and no real payment was executed.
- Read-only DB evidence: two distinct auction Orders, exactly one winning item per auction, one COD and one ZaloPay PENDING payment, fixture stock 5 → 3. Further HTTP retry returns the original Order with 200 and does not create another payment or decrement stock.
- Winner POST with a forged price returns 422; Seller checkout GET returns 403; guest API GET returns 401; guest checkout route redirects to Login with the correct return destination.
- Existing Laravel 13 `PreventRequestForgery` accepts a browser-verified same-origin request through Fetch Metadata. A stateful HTTP POST with the real session cookies but neither verified-origin metadata nor a CSRF token returns 419. The normal Axios/XSRF request flow succeeds; no auth/CSRF configuration was weakened.
- Checkout and Buyer Order verified at 1440/1024/768/390 px: no page-level horizontal overflow, local images load, desktop/mobile screenshots inspected. Keyboard navigation reaches the address-management link. Lazy Order images were verified after scrolling them into view.

Ignored local evidence: `.runtime/auction-a4-backend-full.log`, `auction-a4-frontend-full.log`, `auction-a4-pint.log`, `auction-a4-lint.log`, `auction-a4-build.log`, `auction-a4-browser-results.json`, `auction-a4-manual-order-evidence.json`, screenshots and `auction-a4-db-preservation.json`.

## Development data preservation

No `migrate:fresh` was used on the development database. Only the targeted additive A4 migration was applied. Before migration/fixtures, row counts and SHA-256 content fingerprints were captured for **19 business tables**. Comparisons exclude the new nullable A4 columns and migration bookkeeping.

Temporary Seller/two Buyers, address, shop, category, product, local image, auctions, bids, carts, Orders/parts/items/payments and their audit records were removed by explicit fixture IDs. Matching fixture sessions and image file were removed, and the ignored random-credential manifest was deleted. All 19 original row counts/content hashes match. Existing unrelated user/development records were not updated. Schema/migration bookkeeping and auto-increment counters intentionally advance.

## Commands

Use the compatible PHP CLI configured for this Laravel application (the verification used PHP 8.4, not XAMPP PHP 8.0).

```powershell
cd backend
php artisan migrate --path=database/migrations/2026_10_08_065953_add_winner_settlement_to_auctions_and_order_items.php
# If changing AUCTION_WINNER_CHECKOUT_HOURS in an environment with cached config:
php artisan config:clear
php artisan schedule:work
# One-shot lifecycle processing:
php artisan auctions:sync
php artisan test --compact
php vendor/bin/pint --dirty --format agent
cd ../frontend
npm run test:unit -- --run
npm run lint
npm run build
```

Run Laravel/Vite/Reverb using the existing README local setup. A public HTTPS tunnel is still needed for a real remote ZaloPay callback to localhost.

## Limitations and decision

- Sandbox gateway creation/redirect was verified live. A live paid callback/query and a completed COD delivery were not exercised manually; signed payment verification, replay, cancellation and fulfillment behavior are covered by automated tests.
- Auction reservations apply to the Product during an active claim. Once the winning Order reserves one unit, remaining stock is released as documented above.
- Existing public visibility rules hide a zero-stock/SOLD product and its public Arena; the winner checkout/Order and Seller auction views remain available through authorized routes.
- Winner state uses the existing committed AuctionStatusChanged event plus authoritative REST refresh. No separate optional AuctionWon broadcast or offline notification replay was added.
- There is no second-highest fallback, deposit, penalty, reputation, payout or refund workflow. Existing paid-online cancellation/refund limitations remain unchanged.
- The workspace already contained previous-phase changes. The inventory below names A4 work only; earlier Docker removals/auth/messaging/UI changes were retained.

**Final: PASS for A4 winner checkout/order/payment MVP, with the stated remaining-stock interpretation and live-payment verification limits.**

## A4 changed-file inventory

Backend:

- `backend/.env.example`
- `backend/config/auctions.php`
- `backend/database/migrations/2026_10_08_065953_add_winner_settlement_to_auctions_and_order_items.php`
- `backend/app/Models/Auction.php`
- `backend/app/Models/OrderItem.php`
- `backend/app/Policies/AuctionPolicy.php`
- `backend/app/Http/Controllers/Api/V1/AuctionCheckoutController.php`
- `backend/app/Http/Requests/Domain/AuctionCheckoutRequest.php`
- `backend/app/Http/Resources/AuctionResource.php`
- `backend/app/Http/Resources/OrderItemResource.php`
- `backend/app/Services/AuctionCheckoutService.php`
- `backend/app/Services/AuctionService.php`
- `backend/app/Services/FixedPriceEligibility.php`
- `backend/app/Services/OrderCreator.php`
- `backend/app/Services/CheckoutService.php`
- `backend/app/Services/CartService.php`
- `backend/routes/api.php`
- `backend/tests/Feature/AuctionSettlementTest.php`
- `backend/tests/Feature/AuctionConcurrencyTest.php`
- `backend/tests/Support/auction-worker.php`
- `backend/tests/Feature/AuctionRealtimeTest.php` — formatting only from required Pint run

Frontend:

- `frontend/src/api/auction.api.js`
- `frontend/src/router/index.js`
- `frontend/src/composables/auctionPresentation.js`
- `frontend/src/views/AuctionCheckoutView.vue`
- `frontend/src/views/AuctionArenaView.vue`
- `frontend/src/views/SellerAuctionsView.vue`
- `frontend/src/views/CartView.vue`
- `frontend/src/views/OrderView.vue`
- `frontend/src/views/AdminOrdersView.vue`
- `frontend/src/components/OrderItems.vue`
- `frontend/src/components/auction/AuctionOrderSource.vue`
- `frontend/src/__tests__/AuctionCheckout.spec.js`
- `frontend/src/__tests__/AuctionUI.spec.js`

Documentation:

- `README.md`
- `docs/auction-a4-settlement-verification.md`
