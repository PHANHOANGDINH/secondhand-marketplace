# Final security summary

Controls inspected during C2; evidence is [C1](final-demo-verification.md) and [testing summary](final-testing-summary.md). This is not formal penetration testing or a production-security guarantee.

| Area | Actual control / boundary |
| --- | --- |
| Authentication/session | Sanctum stateful API/web session; EnsureSpaSession on sensitive flows; active account checks; login/register rotate session; logout invalidates session and regenerates CSRF token |
| CSRF/CORS | csrf-cookie, credentialed Axios XSRF; Laravel 13 accepts trusted same-origin Fetch Metadata then falls back to token checks; exact env origins/stateful domains; Vite same-origin proxy; host-only HttpOnly session, SameSite=Lax |
| Password reset | Broker, generic forgot response including unknown email/delivery failure; hashed password/remember-token rotation; reset-token row lock and single consumption; no API token. Existing session policy preserved, not global session revocation |
| Role/ownership/IDOR | EnsureAdmin plus Policies; owned address/cart/order/product/media/conversation/notification scope; locked writes reauthorize; hidden navigation is only UX |
| Validation/mass assignment | FormRequests/safe allowlists; DomainRequest rejects unknown fields; server owns role/status, current price/winner, totals/time/transitions; actor/parent relationship validation |
| Money/stock | DECIMAL(15,2), BCMath strings; server totals/snapshots; no self-purchase; product/account locks, quantity limits and one-time restoration; ZaloPay positive whole VND |
| Concurrency/retries | Transactions, ordered locks, revalidation, unique constraints, payload-bound checkout keys; one active Auction/product; minimum recalculated under lock; idempotent finalization/settlement/reminder/notification |
| Payment authenticity | KEY1 HMAC-SHA256 create/query requests; KEY2 MAC on exact callback data/hash_equals; app/reference/user/time/amount/currency/transaction checks; unique gateway transaction and locked confirmation |
| Payment conflicts | Verified late money marks reconciliation_required rather than reopening cancelled Order; paid/reconciliation attempts cannot retry. Query uses authenticated request/provider HTTPS; do not claim every query response has a verified MAC |
| Images | JPEG/PNG/WebP MIME/extension, ≤5 MiB/file, ≤8/product, ≤20 megapixels; GD decode/re-encode and memory guard; UUID product paths, parent ownership, normalized primary/order; cleanup retains referenced snapshot files |
| Videos | MP4/WebM MIME/extension/container-header checks; default 50 MiB/3 files; UUID paths; locked parent authorization, scoped delete and READY-primary uniqueness; no antivirus/full codec verification/transcoding |
| Text/injection | Trimmed message ≤1,000/comment ≤500; stored strings and escaped Vue rendering. HTML-looking characters may remain literal text; tags are not claimed stripped. ORM binding and escaped LIKE avoid user SQL interpolation |
| Realtime | Private user channel requires active own web-session and validated subscription; public auction/video payloads safe and visibility-gated. Public IDs are not authorization |
| Notifications | Recipient-scoped rows, recipient/type/event dedup, safe internal targets/payload, private delivery; database authoritative |
| Visibility | ACTIVE stocked Product + active Shop/owner/category ancestry, soft-delete filters; READY videos; historical snapshots retained without republishing unavailable listings |
| Errors/secrets | Generic API 5xx, exception/file/line/trace stripped; DB/APP_KEY/Reverb secret/payment/mail credentials private env. Only public Reverb key in Vite; logs/reset links sensitive |

## Rate limits

[AppServiceProvider](../backend/app/Providers/AppServiceProvider.php) defines limits applied in [routes](../backend/routes/api.php); applicable limits combine.

| Flow | Limit |
| --- | --- |
| Login | 5/min normalized email + IP; 20/min IP |
| Register | 5/hour IP |
| Forgot / reset | 3/min / 5/min normalized email + IP; each 20/min IP |
| Domain writes | 60/min User; safe reads exempt from this limiter |
| Payment/checkout | 12/min User |
| Bid / messaging / upload | 20/min User each |
| Video comment | 10/min User |
| Public reads / callback | 120/min IP |

## Evidence and limits

C1 tested representative Admin/resource IDOR, self-bid/loser checkout, tampered amount, hidden Product/Video, logout and missing-CSRF HTTP attacks. Automated suites cover broader races/payment/token/visibility. [Testing summary](final-testing-summary.md) records exact evidence.

Production needs HTTPS/proxy/cookie/CORS planning, secret management, monitoring, protected logs, backups/restore and traffic/load testing. Static public uploaded files remain accessible to someone holding their URL after metadata is hidden: API visibility is not byte-level revocation. Public bidder labels contain pseudonymous IDs, not names/emails; this is not strong anonymity. No malware scanner, formal penetration test or full WCAG audit is claimed.

### Known functional limits (audit of 2026-10-12)

- **No automatic ZaloPay refund.** The application never calls a refund API. Since D5 an Admin records the outcome of a manual refund (`REFUNDED_OUTSIDE` → payment `REFUNDED`); the money itself is returned outside the application. A paid, unflagged online order still cannot be cancelled in the application.
- **Reconciliation is a recorded decision, not an automated process (D5).** `/admin/payments` lists flagged payments and stores `REFUNDED_OUTSIDE` or `KEEP_PAID` with a mandatory note; partial refunds and re-opening a resolved payment are not modelled.
- **Admin auction cancellation exists since D5** for SCHEDULED/LIVE auctions, including after bids. An ENDED auction cannot be cancelled; a winner who is still inside the checkout window keeps that right unless the product is hidden.
- **Hiding a product never cancels an auction implicitly (D5).** A SCHEDULED/LIVE auction blocks hiding until an Admin cancels it explicitly; a product awaiting winner checkout can be hidden, which notifies the winner and makes checkout fail. Suspending a shop does cancel its SCHEDULED/LIVE auctions in the same transaction.
- **Every write transaction locks the whole `categories` table** (bids, cart writes, checkout, product edits, video comments). Correct, but it serialises writes and will not scale to high concurrency.
- **Scheduler dependency.** Auctions become LIVE/ENDED and ZaloPay attempts are reconciled only while `php artisan schedule:work` (or cron) is running.
