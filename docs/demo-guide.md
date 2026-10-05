# End-to-end demo guide

Use three separate browser profiles or different browsers: **Buyer**, **Seller**, **Admin**. Multiple private windows in the same browser can share a cookie session, so they are not reliable actor isolation. The production-like COD demo origin is **http://localhost:8088**. Choose private passwords; this guide contains no account credentials or merchant keys. Startup/health commands are in [deployment guide](deployment-guide.md).

## Preparation (operator, before presentation)

1. Confirm Nginx/backend/MySQL are healthy and the scheduler is running. A fresh Docker database is empty; final automated verification cleans its temporary accounts/products, so it does not leave demo credentials behind.
2. Register three distinct accounts at `/register`, with privately chosen passwords of at least 12 characters containing letters and numbers. Use separate profiles to avoid session overlap. Do not try to pass role/status in registration.
3. Provision only the intended registered administrator through the operator console. This is a one-time operator action, not a public API/business feature. Run the command below, then type the PHP lines interactively; replace the reserved example email with the actual registered admin email:

```powershell
docker compose --env-file .env.docker-verification exec backend php artisan tinker
```

```php
$admin = App\Models\User::where('email', 'admin-demo@example.test')->firstOrFail();
$admin->forceFill(['role' => 'ADMIN'])->save();
exit
```

4. Reload/login in the Admin window and confirm admin links appear. In `/admin/categories`, create an ACTIVE category with a reason. Do not run development seeders against Docker or change a real user's role merely for a demo.
5. Keep at least one product with stock >=3 and a small valid JPEG/PNG/WebP ready for upload. Actual demo actions create intentional data in the dedicated Docker database; automatic verification fixtures are separate and cleaned. Do not reset the development database for a presentation.

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

## ZaloPay Sandbox branch (separate environment)

**The Docker backend at localhost:8088 runs APP_ENV=production and rejects Sandbox gateway endpoints. Do not weaken that guard for a demo.** Use the local Laravel/Vue setup at localhost:8000/5173 and an intentional disposable demo database (or a known safe local dataset), with the same Buyer/Seller/Admin moderation preparation. Do not copy production keys into this environment.

In a private backend env, provide your authorized Sandbox APP_ID/KEY1/KEY2 and these non-secret values:

```dotenv
APP_ENV=local
APP_URL=http://localhost:8000
FRONTEND_URLS=http://localhost:5173
SANCTUM_STATEFUL_DOMAINS=localhost:5173
ZALOPAY_CREATE_URL=https://sb-openapi.zalopay.vn/v2/create
ZALOPAY_QUERY_URL=https://sb-openapi.zalopay.vn/v2/query
ZALOPAY_RETURN_URL=http://localhost:5173/payment/result
ZALOPAY_EXPIRE_SECONDS=900
# Callback: configure a reachable approved URL if testing provider delivery;
# localhost cannot receive an external ZaloPay callback.
```

Clear local config cache/restart backend after changing env. Configure CALLBACK_URL in private env/merchant settings for delivery tests. Run `php artisan schedule:work` locally if demonstrating scheduled reconciliation; the production-like Docker stack already runs its scheduler.

1. Buyer adds a different available product and chooses ZaloPay at checkout. Select a preferred gateway channel if needed; the browser receives a backend-generated approved HTTPS ZaloPay URL.
2. Complete a **Sandbox** payment only with provider-published test instruments, entered on the gateway; do not put card/wallet secrets in this app, source, slides or screenshots. Refer to the [official Sandbox guide](https://developer.zalopay.vn/v2/start/). Sandbox wallet access may require identification and suitable account permissions.
3. Return to `/payment/result?order=<id>`. The page fetches the owned backend order and queries the gateway; URL `status=PAID` cannot prove payment. After verified PAID, Seller can fulfill without a COD-collected checkbox.
4. For failed/pending payment, show current status, query/retry only when allowed. For reconciliation_required, stop further payment/fulfillment and explain manual handling; no refund UI is implemented.

Earlier PHASE 12 records user-confirmed Sandbox card success and agent create/query checks. PHASE 15 does **not** repeat a live merchant/wallet transaction or claim public callback E2E. If authorized Sandbox setup is unavailable, demonstrate the verified payment UI/regression behavior and label the live branch **not configured**, rather than pretending a redirect or mock callback is a paid transaction.

Production needs separate merchant onboarding/keys and public callback/redirect URLs. Production callbacks require HTTPS/TLS 1.2 or higher per the [provider FAQ](https://docs.zalopay.vn/vi/docs/faq/); local localhost verification does not satisfy that external delivery requirement.

## Presentation checklist

- Verify the exact origin, separate sessions, healthy containers, accounts/category/approved stock/image and today's dashboard range before presenting.
- Keep private env/passwords/merchant consoles out of screen sharing. No default Docker password is published.
- Show COD as the reproducible deployed end-to-end flow. Clearly label Sandbox, production-like infrastructure and unverified live production payments.
- Known exclusions: notifications/chat/auction/refunds/payouts/commissions, automatic abandoned online-stock release and reconciliation UI. See [final verification](final-verification.md) for complete evidence and limitations.
