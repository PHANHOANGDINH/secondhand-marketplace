# Local marketplace demo guide

Use **http://localhost:5173** with Laravel at **http://127.0.0.1:8000**, following [local startup](deployment-guide.md). Use separate browser profiles for Buyer, Seller and Admin. Choose private passwords; this guide publishes no usable credentials.

## Preparation

1. Start the configured MySQL server, Laravel, then Vue. Check health through Vite. Inspect the catalog first; use `php artisan db:seed --class=DemoMarketplaceSeeder` only on an explicitly selected safe demo database when a product deficit exists. The existing development database is not reset or changed by setup.
2. Prepare your own Buyer, Seller and administrator accounts on a disposable demo database. Registration never accepts role/status. An authorized operator may provision only the intended administrator through `php artisan tinker` in that database; do not change a real user's role for a presentation.
3. Confirm an ACTIVE category/shop/product and sufficient stock. Keep a valid small PNG/JPEG/WebP ready for the existing image editor. Never use the full DatabaseSeeder or migrate:fresh against existing development data.
4. Interactive demo actions intentionally change data; use a separate demo DB if development records must remain untouched. The earlier [data-pack report](demo-data-pack.md) describes an isolated historical catalog; it is not a claim that those 20 records were copied into development.

## Seller and Admin: publish a listing (3–4 minutes)

1. Seller: `/account/profile`, update display name/phone; `/seller/shop`, create a shop. Explain that a seller is an ordinary user owning a shop and new shops await moderation.
2. Admin: `/admin/shops`, select that shop, approve ACTIVE with a reason. Administrator must not approve their own shop.
3. Seller: `/seller/products/create`, choose the category, condition, decimal price and stock. Save DRAFT; on the edit page upload a valid image and set its order/primary image as desired. Submit for review.
4. Admin: `/admin/products`, approve the pending product with a reason. Seller reloads and opens its public product/shop page. Show search/filtering at `/products` and the public gallery. Pending/hidden records do not appear in public catalog.

## Buyer: profile, address, cart and COD (3 minutes)

1. Buyer: login, `/account/profile`, then `/account/addresses`; add a Vietnamese delivery address and mark it default. Show validation and default selection.
2. `/products`, open the Seller's product and add quantity 1. `/cart` shows shop grouping, current prices and availability. The seller cannot purchase their own product.
3. `/checkout`: select the owned address, choose COD, confirm once. Show order code, stored delivery snapshot, shop part and pending payment in `/orders/:id`. Refresh to demonstrate session/order persistence. Do not create a new checkout request when a previous result is uncertain; use its existing retry action.
4. Optional cancellation branch: place a second small COD order and cancel its PENDING part with a reason; show retained history and returned stock. Do not cancel the main order needed for fulfillment/review.

## Seller fulfillment, review and dashboards (3 minutes)

1. Seller: `/seller/orders`, open the correct **ShopOrder** part. Advance PENDING -> CONFIRMED -> PROCESSING -> SHIPPING -> COMPLETED. For COD completion, explicitly confirm collected payment. Reload after a stale-status conflict rather than submitting an outdated state.
2. Buyer: refresh `/orders/:id`; show completed fulfillment and paid COD. Create a 1–5-star review for that completed purchase; optionally edit the review. Show the public product's review list/aggregate.
3. Seller: `/seller/dashboard`, choose a date range including today. Show completed merchandise revenue/chart/table and recent orders; shipping/discount are currently zero, and revenue is not commission/payout.
4. Admin: `/admin/dashboard`, `/admin/orders` (read-only), `/admin/users` and `/admin/reviews`. Hide/unhide the demo review with a reason and show public aggregate changes. Account suspension may be shown only on a disposable account; never suspend the presenting admin. Finish with categories/shop/product management.

## ZaloPay Sandbox

In a private backend env, provide your authorized Sandbox APP_ID/KEY1/KEY2 and these non-secret values:

```dotenv
APP_ENV=local
APP_URL=http://127.0.0.1:8000
FRONTEND_URLS=http://localhost:5173
SANCTUM_STATEFUL_DOMAINS=localhost:5173
ZALOPAY_CREATE_URL=https://sb-openapi.zalopay.vn/v2/create
ZALOPAY_QUERY_URL=https://sb-openapi.zalopay.vn/v2/query
ZALOPAY_RETURN_URL=http://localhost:5173/payment/result
ZALOPAY_EXPIRE_SECONDS=900
# Callback: configure a reachable approved URL if testing provider delivery;
# localhost cannot receive an external ZaloPay callback.
```

Clear local config cache/restart backend after changing env. Configure CALLBACK_URL in private env/merchant settings for delivery tests. Run `php artisan schedule:work` locally if demonstrating scheduled reconciliation; there is no background scheduler unless you start one.

1. Buyer adds a different available product and chooses ZaloPay at checkout. Select a preferred gateway channel if needed; the browser receives a backend-generated approved HTTPS ZaloPay URL.
2. Complete a **Sandbox** payment only with provider-published test instruments, entered on the gateway; do not put card/wallet secrets in this app, source, slides or screenshots. Refer to the [official Sandbox guide](https://developer.zalopay.vn/v2/start/). Sandbox wallet access may require identification and suitable account permissions.
3. Return to `/payment/result?order=<id>`. The page fetches the owned backend order and queries the gateway; URL `status=PAID` cannot prove payment. After verified PAID, Seller can fulfill without a COD-collected checkbox.
4. For failed/pending payment, show current status, query/retry only when allowed. For reconciliation_required, stop further payment/fulfillment and explain manual handling; no refund UI is implemented.

Sandbox and Production use separate authorized merchant credentials/endpoints. A public HTTPS tunnel (such as Cloudflare Tunnel) to backend port 8000 is needed for provider callback delivery; localhost is unreachable externally. Sandbox success is not a live production payment. If credentials are absent, clearly label the gateway branch unconfigured and use COD; do not fake PAID or bypass signed verification.

## Presentation checklist

- Use localhost:5173 consistently, separate sessions, approved stock/images and a dashboard date range including today.
- Keep passwords, env files, reset links and merchant credentials out of screen sharing.
- Show COD as the offline end-to-end flow; show actual Sandbox only when authorized and configured.
- Buyer/seller text messaging, auction Arena, winner checkout and persistent in-app notifications are available. Refund/payout/commission functionality remains out of scope. Local verification and current limitations are in [xampp-local-verification.md](xampp-local-verification.md).

## Auction notifications demo (A5)

Use disposable, authorized Seller and two Buyer accounts in separate browser sessions. Start Laravel, Vite, Reverb and the scheduler using the local deployment guide. Seller creates a valid LIVE auction. Buyer A bids; B outbids A. A sees the existing realtime outbid toast and a persistent bell entry with “Ra giá lại”. Mark it read and show the badge update.

Let A bid again, disconnect/close A's app, and let B outbid A. Reopen/login as A: the missed OUTBID loads from the database. Repeated realtime delivery must not produce duplicate rows. Finish bidding before the configured server deadline; the scheduler finalizes the auction, the winner receives AUCTION_WON and the Seller/losing bidders receive AUCTION_ENDED. Use the winner notification to reach winner-only checkout and create COD Order. Buyer and Seller receive AUCTION_ORDER_CREATED; the notification targets their existing order pages.

A single reminder is eligible inside `AUCTION_CHECKOUT_REMINDER_MINUTES` before the winner claim deadline, only while no Order exists. It is not sent after expiry. Configure shorter claim/reminder windows only on an isolated demo database if demonstrating this live; do not edit real auction deadlines or user passwords. Restore/clean only disposable fixtures. Historical verification used an explicitly isolated temporary auction whose deadline was shortened for the check.
