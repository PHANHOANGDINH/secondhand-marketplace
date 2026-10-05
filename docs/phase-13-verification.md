# PHASE 13 — Security + Full System Testing

Verification date: 2026-10-05 (Asia/Saigon).

Status: **PASS — application security hardening and full regression completed within the documented scope**.

Feature freeze observed: security fixes and regression coverage only. No new business features, dependency changes, migrations, development database reset, or PHASE 14 work.

## Audit areas

| Area | Review and verification |
|---|---|
| Authentication / sessions / Sanctum | Cookie-only SPA flow, session regeneration at login/register, logout invalidation and CSRF rotation, generic invalid credentials, suspended/deleted accounts, no token issuance, first-party session middleware. HTTP rejects replay of the authenticated cookie after logout. |
| CSRF / CORS | Stateful origin allowlist, exact credentialed CORS origins and preflight, HttpOnly session cookie, client-readable XSRF cookie, host-only `/` cookies with SameSite=Lax. Missing CSRF rejects writes with 419. Only the signed ZaloPay callback is exempt. Untrusted-origin cookie authentication is rejected. |
| Buyer / Seller / Admin authorization | Policies, middleware, scoped lists and parent-child binding for addresses, carts, products/images, orders/payments, shop orders and reviews. Admin status does not bypass Buyer/Seller ownership. Suspended sessions and self/stale moderation are blocked. |
| Validation / mass assignment | DomainRequest rejects unknown fields; FormRequests enforce allowlists, lengths, enum states, pagination, quantities, decimal prices and UUID idempotency keys. Controllers pass validated or explicitly selected fields. Server assigns ownership, payment amount/reference, stock effects and privileged states. |
| Rate limits | Login: 5/minute per normalized email + IP, now also 20/minute per IP. Register: 5/hour/IP. Domain writes: 60/minute/user. Upload: 20/minute/user. Checkout, payment retry and query now share 12/minute/user. Public reads/callback: 120/minute/IP. HTTP verifies 429 and shared checkout/query budget. |
| Uploads / paths / deletion | JPEG/PNG/WebP only; detected MIME and filename extension checks, 5 MiB/file, at most 8 images, 20 megapixels and processing-memory guard. Images are decoded/re-encoded, stored under generated UUID names; appended payload is removed. Ownership and product-image binding protect changes/deletion. Cleanup accepts only generated paths and preserves order snapshot references. |
| XSS / injection | Frontend source has no v-html, innerHTML, document.write, eval or Function-based execution of user content. Vue interpolation renders comments/descriptions as text; existing frontend tests cover escaped review text. Gateway navigation is HTTPS-only and host-allowlisted on both sides. Raw search SQL uses bindings and literal wildcard escaping; sort/group choices use server allowlists. |
| State transitions / payments | Locked services recheck actor/policy and expected states. Online fulfillment requires verified payment; paid/reconciliation orders cannot be cancelled. Callback checks raw-data HMAC, merchant/buyer/time/reference, amount and transaction ID. Duplicate callbacks, wrong amount/reference/MAC, cross-order transaction replay, uncertain gateway results and retry idempotency retain existing coverage. Gateway calls are outside database transactions and do not follow redirects. |
| Concurrency / transactions | Real MySQL process/barrier coverage for competing stock checkout, replay, one-time cancellation restoration, COD completion, callback/cancellation and concurrent payment retry. Added real races for address creation/default switching/deletion and duplicate review creation/foreign-author rejection. Actor/account/order/product locks and transaction rollback coverage reviewed. |
| Soft deletion / public visibility | Product visibility requires active product/stock, active shop/owner and active category ancestry. Public endpoints omit hidden/deleted records; historical order snapshots remain available only to authorized parties. Archived admin records are read-only; deleted review authors are anonymized. Existing visibility tests retained. |
| Core query behavior | Product/cart/review lists and Seller/Admin dashboards have query-scaling tests, eager relations, SQL aggregates and bounded recent records. Pagination and report date ranges are validated. Buyer order resources attach the already-loaded parent order; review author output uses whenLoaded rather than a lazy query. No additional confirmed N+1 defect was found in the reviewed endpoints. |
| Secrets / errors | Resource allowlists omit passwords, remember tokens, HMAC keys, transaction references and internal payment metadata where inappropriate. ZaloPay example keys remain empty; source scan found no private-key/AWS/OpenAI-secret patterns in audited application/test source. API exception responses now remove debug metadata and redact server failures even with app.debug=true. Server-side exception reporting remains enabled. |

Review used relevant policies, requests, controllers, transaction services, models/resources, API middleware/config, frontend rendering/API/auth code and existing tests/reports. Installed versions were checked from composer.lock: Laravel 13.33.0, Sanctum 4.3.3, PHPUnit 12.5.36 and Pint 1.32.1. No package versions were changed.

## Confirmed issues and fixes

1. **Login throttling bypass by rotating email addresses.** The only limit keyed on normalized email + IP, so a single IP could continue attempting new addresses. A regression reproduced 20 attempts followed by another 422 response. Added a separate 20/minute/IP budget while retaining the 5/minute email/IP limit, with distinct limiter keys. The focused test and real HTTP checks now receive 429; spoofed X-Forwarded-For does not bypass the limit under the current proxy configuration.
2. **Checkout bypassed the stricter payment budget.** Retry/query had a 12/minute payment limit, while checkout could initiate gateway work under only the 60/minute domain-write limit. A regression reproduced a 13th checkout reaching validation. Checkout now uses the same payment limiter as retry/query. Focused tests and HTTP checks verify 429, Retry-After and the shared checkout/query budget; rejected empty checkouts create no orders/payments.
3. **API debug/internal error exposure.** With debug enabled, a test exception exposed its message, file, line and trace. API exception finalization now returns only `{"message":"Server Error"}` for 5xx failures and removes exception/file/line/trace fields from 4xx JSON responses. Status codes and response headers, including Retry-After, are preserved. Validation/business messages remain available. Synthetic 500/503/403/404 cases verify the behavior without adding application routes.

No additional confirmed authorization, upload, payment or state-transition defect required a code change. The new address/review races cover missing regression evidence rather than changing business behavior. The shared test worker now translates expected authorization/not-found exceptions to JSON, and malformed worker output produces a useful test failure. Initial harness failures were resolved before the final run.

## Test results and evidence

| Check | Result |
|---|---|
| Full backend suite | PASS — 192 tests / 2,135 assertions; 0 failures, 207.101s; includes all 5 MySQL concurrency cases |
| Focused security regressions | PASS — 4 tests / 76 assertions |
| New address/review MySQL races | PASS — 2 tests / 29 assertions |
| Complete frontend suite | PASS — 150 tests / 12 files; 0 failures |
| Real HTTP cookie/CSRF/authorization/rate checks | PASS — 139 checks |
| Pint | PASS — explicit six changed PHP paths; dirty mode could not detect Git in this session |
| Frontend lint | PASS — exit 0 |
| Production build | PASS — exit 0; 153 modules |
| Development fixture cleanup | PASS — all 16 table counts/hashes equal before/after HTTP checks |
| Final development snapshot | PASS — all 16 table counts/hashes match the initial PHASE 13 snapshot and HTTP-cleanup snapshot after final backend completion |

Evidence is saved under gitignored `.runtime/`: `phase13-security-before.log`, `phase13-security-after.log`, `phase13-new-concurrency.log`, `phase13-backend-tests.log`, `phase13-pint.log`, `phase13-frontend-results.json`, frontend lint/test/build logs, `phase13-http-results.json`, `phase13-http.log`, and database snapshots. The before-fix run intentionally failed all three initial regressions; it is reproduction evidence, not the final result.

HTTP checks use actual Laravel middleware and separate Buyer/Seller/Admin/guest cookie jars. They include suspended-session denial, admin-role enforcement, address/order/payment/review/image ownership checks, CORS preflight, CSRF rejection, safe resources, invalid transition/replay conflicts, callback wrong MAC, debug metadata suppression, payment/login throttling and logout-cookie replay. Existing guarded nonce-scoped fixture helpers were reused. HTTP did not submit cards or call the live ZaloPay gateway; automated payment verification uses gateway mocks and signed fixtures.

Database safety: PHPUnit and every child worker require the actual MySQL database to be `secondhand_marketplace_test` before running tests. Development snapshots cover users, addresses, shops, categories, products, product_images, carts, cart_items, orders, shop_orders, order_items, payments, reviews, audit_logs, personal_access_tokens and migrations. No development migration or migrate:fresh was run. HTTP fixtures are fully removed with relationship/nonce guards, including after an initial cookie-copy harness failure. Auto-increment sequences may advance; sessions/cache/logs are operational artifacts outside the business-table comparison and were not reset.

## Remaining security and verification limitations

- This is application-level review and regression testing, not a penetration test, dependency-CVE audit, production load test or guarantee that every possible schedule/input was explored.
- HTTPS/proxy deployment was not exercised. Local HTTP cookies are non-Secure. Production still requires APP_DEBUG=false, Secure session cookies, exact trusted frontend/stateful domains, correct trusted-proxy configuration and production secrets. API redaction also works when debug is accidentally enabled, but does not replace deployment configuration or protect non-API debug pages.
- Limits use the configured Laravel cache and observed IP. Multi-node deployment needs a shared limiter store; distributed-source attacks and server-wide resource protection were not tested. Per-IP login caps can affect users sharing a NAT.
- Product image files are intentionally on the public disk. Knowing a retained image URL can permit direct access after catalog hiding/deletion; this phase did not add private media or change snapshot retention. No malware-scanning service was introduced. Image decoding uses the installed GD stack and resource guards.
- Public HTTPS ZaloPay callback E2E, wallet E2E and production payment onboarding remain unverified. Refunds/reconciliation UI and automatic release of abandoned online-order stock remain outside scope; late verified money is flagged for manual reconciliation.
- Real MySQL races test explicit contention and allowed outcomes, not exhaustive concurrency. Account/category locks can serialize related writes; no high-volume performance benchmark or schema/index redesign was introduced.
- Frontend behavior was verified with its complete test suite, lint/build and source review; no browser visual/manual interaction test was performed in PHASE 13. Build emitted a non-blocking plugin timing diagnostic; exit status was 0.

## Changed files

Git has no commits and the project remains untracked, so change reporting uses the saved PHASE 13 SHA256 snapshot of relevant files rather than an unavailable HEAD diff.

- `backend/app/Providers/AppServiceProvider.php` — aggregate login IP limiter.
- `backend/routes/api.php` — shared payment throttle on checkout.
- `backend/bootstrap/app.php` — API exception redaction.
- `backend/tests/Feature/SecurityPhase13Test.php` — four new security regression cases.
- `backend/tests/Feature/CheckoutConcurrencyTest.php` — two new real race cases and worker diagnostics.
- `backend/tests/Support/checkout-worker.php` — address/review operations and expected denial response handling.
- `docs/phase-13-verification.md` — this report.

No frontend application files, dependencies, migrations or business features changed. Additional execution evidence/helpers are in gitignored `.runtime/`.

## Final decision

**PASS** for the audited application scope: all confirmed issues were fixed, 192 backend tests / 2,135 assertions, 150 frontend tests and 139 real HTTP checks passed; Pint, lint and production build passed; all 16 development business/migration table counts and hashes remain unchanged. The deployment/payment/media/testing limitations above remain explicit and are not claimed as verified.
