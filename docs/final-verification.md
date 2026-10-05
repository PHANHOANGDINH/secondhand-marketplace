# Final verification — PHASE 15

Date: 2026-10-05, Asia/Saigon. Feature freeze preserved. This phase reviews UI/demo usability, updates current documentation and verifies the complete application and production-like Docker deployment. It adds no new business feature, schema, dependency or payment capability.

## Final architecture

```text
Browser -> Nginx (localhost:8088; public HTTPS terminator required in production)
             |-> Vue 3 / Router / Pinia / Bootstrap / Chart.js production SPA
             |-> public upload alias (shared storage mounted read-only)
             |-> Laravel 13 / Sanctum cookie session / PHP 8.4 FPM
                      -> MySQL 8.4 private Docker database
```

Compose services: nginx, backend, mysql, one-shot migrate, scheduler. Only Nginx publishes localhost:8088; PHP-FPM/MySQL remain internal. Named MySQL/storage volumes retain business data, uploads, file sessions/cache. Laravel caches config/routes/views on startup and runs non-root; debug is false. Fixed Nginx routing/host checks and explicitly trusted proxy IP protect deployment origin/HTTPS handling. Local development remains localhost:5173/8000 and uses an independent host database.

## Feature inventory and UI review

| Flow | Shipped behavior / review scope |
| --- | --- |
| Register/login | Cookie auth, CSRF, validation, logout, session restoration; login now resumes a protected destination including payment order query; unsafe external destinations fall back to account |
| Profile/address | Name/phone editing, owned address CRUD/default, validation/loading/error states |
| Shop creation | Ordinary USER owns a shop; pending moderation, rejected resubmission, suspended read-only state |
| Seller products | Owner management, draft/edit, safe uploads/gallery/primary/order, submit/moderation/lifecycle states |
| Public browsing | Visible catalog/shop/category rules, search/filter/pagination, product detail/reviews; homepage links directly to catalog/shops |
| Cart/checkout | Owned cart/current availability, whole-cart server-priced checkout, UUID idempotency, owned delivery address, COD/ZaloPay choice |
| Buyer orders | Owned aggregate order/history, payment attempts, allowed cancellation, stored snapshots and completed-purchase reviews |
| Reviews | One review per completed owned item, author editing, public aggregation, ADMIN hide/unhide |
| Seller fulfillment/reports | Own ShopOrder transitions, COD-collected confirmation, verified online-payment gates, revenue chart/table/date range and recent orders |
| Admin | Role-gated menus/routes; category/shop/product/review/user-status management, dashboard and read-only order history; no impersonation/fulfillment override |
| Payment return | Owned backend query is authoritative; forged redirect status ignored; malformed/missing order now gives a clear recovery message/link |
| Navigation/recovery | Public/authenticated/admin menus resolve to real routes; unknown URLs render a NotFound page with home/catalog links |

Reviewed current views, shared feedback/forms, API clients, router guards and menus, supported by existing complete UI/regression tests and live checks. Buyer/Seller/Admin navigation reflects capabilities: seller links are available to authenticated users because shop creation is a USER capability; backend ownership remains authoritative. Existing loading/error/empty states were retained unless a confirmed problem required a fix.

## Confirmed issues and fixes

1. README still claimed PHASE 11, future payments/Docker and conflicting historical setup. Replaced it with current architecture/setup/env/tests/features and explicit limitations; added implementation API, demo and deployment guides. Removed the erroneous PHASE 14 report claim that Vite build performed a typecheck (this JavaScript app has no typecheck script).
2. Protected-page login discarded its destination, especially `/payment/result?order=...`. Router guard now preserves the internal destination; auth form resumes it and retains it between login/register. Guest add-to-cart returns to its product after login; no automatic cart mutation is introduced. Tests reject external/protocol-relative/backslash destinations and retain admin authorization.
3. Unknown URL rendered no view. Added a catch-all recovery view linked to existing home/catalog routes.
4. Invalid payment result order was reported as a network failure. Added explicit validation/recovery before any backend request; existing backend ownership/payment truth handling remains intact.
5. Homepage displayed framework diagnostics rather than a marketplace introduction/action. Replaced that copy with shopping links and user-facing connection/retry status.
6. Backend env example left Sandbox endpoint/return/expiry fields empty, overriding meaningful defaults when configuring a new demo. Added explicit non-secret local Sandbox URLs, local return, 900-second expiry and empty direct-serving proxy trust; merchant keys remain blank. Private env and production guard were not changed.
7. Address/public-shop/admin lists displayed an empty-collection claim alongside a failed loading alert. Empty messages now wait for a successful load; a regression test checks the error/empty distinction.

No notification/chat/auction/refund/payout/commission features were introduced. Ten meaningful new frontend cases cover recovery, destination safety, role navigation and error/empty distinction; existing related assertions were updated for the new destination behavior.

## Test totals and deployment status

| Check | Final result |
| --- | --- |
| Complete backend regression | **195 tests passed, 2,141 assertions**; real MySQL guarded test database, including concurrency/payment/security cases |
| Complete frontend regression | **160 tests passed, 13 files** |
| Pint, entire backend | PASS |
| Frontend Oxlint/ESLint | PASS |
| Frontend production Vite build | PASS |
| Docker Compose build/recreate/start | PASS; final frontend deployed at http://localhost:8088 |
| Nginx API/cookie/authorization/demo-flow HTTP verification | **143 checks passed across 103 HTTP requests**: 125 initial, 2 browser checkpoint, 7 restart, 9 recreation/cleanup |
| Headless Edge browser page navigation | **32 pages passed, zero JavaScript exceptions**; guest/Buyer/Seller/Admin sessions, no form submissions |
| Restart/recreation persistence and cleanup | PASS; all monitored rows, PNG bytes, paid order and authenticated session survived; all fixture rows/image cleaned |
| Development DB safety | PASS; all 16 monitored table counts and SHA256 row hashes unchanged |

Logs and private fixture/cookie snapshots are gitignored under `.runtime/phase15-*`. Full backend counts include all tests once; reruns are not added. Initial guard test expectations were updated to include the preserved destination; the final frontend suite passed in full. An isolated HTTP harness review-state expectation was corrected from PUBLIC to the implemented PUBLISHED enum; partial fixtures were cleaned before the final run. No application defect was inferred from that harness error.

Live HTTP coverage includes health, production Vue assets/SPA shell, protected guest/role/ownership checks, CORS/cookie/CSRF behavior, profile/address, category/shop/product moderation, valid/unauthorized image upload, public image origin and spoofed-forwarded-header resistance, cart/idempotent COD checkout, buyer/seller order reads and completion/payment, invalid online callback signature and COD online-payment denial, purchase reviews/edit/hide/unhide, seller reports and all admin core lists/dashboard/read-only order detail. Production merchant credentials remain intentionally absent.

The final Edge pass (Edge 154) loaded actual built Vue pages for guest catalog/shop/product/not-found/login redirect; Buyer account/profile/addresses/cart/checkout/orders/detail/reviews/payment-return/recovery and denied-admin redirect; Seller shop/products/create/edit/orders/detail/dashboard; all seven Admin pages including order detail selection. It submitted no forms; critical writes were exercised separately by HTTP. Zero uncaught JavaScript exceptions were observed. This is browser rendering/navigation evidence, not a claim of visual inspection of every control.

Executed commands (backend/frontend working directories as in README): `php artisan test --compact`, `php vendor/bin/pint --test --format agent`, `npm.cmd run test:unit -- --run`, `npm.cmd run lint`, `npm.cmd run build`. On this host PHP was `C:\Users\axyz5\.config\herd\bin\php84\php.exe`. Docker and HTTP/persistence commands are reproduced in [deployment guide](deployment-guide.md).

## Database, upload and session safety

Only the dedicated Docker `marketplace_phase14` database/volumes were used for live writes; no development migration/fresh/seed operation was run. Full PHPUnit and its worker guard require the actual database `secondhand_marketplace_test` before test-schema reset. Development counts/SHA256 snapshots monitor all 16 business/migration tables. Docker verification uses nonce-scoped users/products/orders/reviews/audits and guarded relationship/path cleanup. Its stored snapshots compare exact rows, uploaded image bytes, paid order and logged-in cookie session across restart and complete container recreation.

Browser navigation initialized two expected empty fixture carts through the existing cart GET. The initial persistence comparison detected only that difference (carts 1 -> 3); checkpoint then verified all carts belong to fixture users, the original buyer cart and every other table are unchanged. A new pre-restart snapshot was taken and restart repeated; both restart and final recreation comparisons passed exactly. Cleanup returned all 16 Docker business/migration table snapshots to the empty migrated baseline and image GET to 404. Docker test limiter cache was cleared after cleanup so demo registration is not blocked by verification quotas. Operational session/cache files and Docker auto-increment sequences can remain; no business fixture row remains.

## Known limitations and demo readiness

- **COD demo/submission** uses the verified production-like localhost:8088 stack. Fresh Docker data is intentionally empty after automated cleanup; follow the short operator account/category/shop/product preparation in [demo guide](demo-guide.md). No default production credentials/passwords are published.
- **Sandbox demonstration is separate**: local Laravel/Vue config and authorized Sandbox credentials are prerequisites. Production backend intentionally rejects Sandbox endpoints. PHASE 12 retains earlier user-confirmed Sandbox card success/create/query evidence; PHASE 15 does not claim a new live wallet/card transaction, merchant callback delivery or real payment. A redirect alone is never payment proof.
- Public HTTPS termination/certificates, production merchant onboarding/callback TLS, live production payment, wallet E2E, backup restore and production load testing remain unverified. The present local HTTP cookies are non-Secure intentionally; public production must use Secure cookies/exact origins and restricted TLS ingress.
- Single-host/file session/cache topology; multi-node shared stores and additional-ingress client-IP allowlists require configuration. Health has separate app/database checks; scheduler lacks a custom heartbeat endpoint.
- Shipping/discount remain zero. No refunds/payouts/commissions/notifications/chat/auction, password recovery/email verification/OAuth/2FA, reconciliation management UI or automatic expired-online reservation release. Late/ambiguous money requires manual reconciliation.
- Public upload URLs can remain readable after catalog hiding/deletion; no private media/malware service/cloud storage was added. Backend GD validation/re-encoding remains tested.
- Browser checks complement component/HTTP tests but do not constitute an accessibility audit, visual review of every screen, provider wallet test or exhaustive production concurrency/load test.
- Git has no commits and the whole project remains untracked. Changed-file inventory is measured against the saved pre-PHASE-15 relevant-file SHA256 baseline, rather than an unavailable HEAD diff. Dependencies/private secrets/runtime logs are not included in the submission source inventory.

## Final changed files

**33 PHASE 15 files**, measured against the initial relevant-file hashes and explicit newly created files. Historical phase reports retain original scope/results except the documented build-description correction. No dependencies, migrations, private env, backend business services or authorization policies changed.

- `README.md`.
- `backend/.env.example`.
- `docs/api-summary.md`, `docs/demo-guide.md`, `docs/deployment-guide.md`, `docs/final-verification.md`, `docs/phase-14-verification.md`.
- `docker/verification/browser-smoke.mjs`, `docker/verification/database.php`, `docker/verification/http-smoke.py`.
- `frontend/src/components/AddToCart.vue`, `frontend/src/components/AdminModerationPanel.vue`, `frontend/src/components/AuthForm.vue`.
- `frontend/src/router/authGuard.js`, `frontend/src/router/index.js`.
- `frontend/src/views/AddressesView.vue`, `frontend/src/views/AdminCategoriesView.vue`, `frontend/src/views/AdminOrdersView.vue`, `frontend/src/views/AdminProductsView.vue`, `frontend/src/views/AdminShopsView.vue`, `frontend/src/views/HomeView.vue`, `frontend/src/views/NotFoundView.vue`, `frontend/src/views/PaymentResultView.vue`, `frontend/src/views/ShopsView.vue`.
- `frontend/src/__tests__/AdminPhase11.spec.js`, `frontend/src/__tests__/App.spec.js`, `frontend/src/__tests__/AuthForm.spec.js`, `frontend/src/__tests__/auth.spec.js`, `frontend/src/__tests__/CartPhase7.spec.js`, `frontend/src/__tests__/DomainViews.spec.js`, `frontend/src/__tests__/FinalPolish.spec.js`, `frontend/src/__tests__/PaymentPhase12.spec.js`, `frontend/src/__tests__/SellerDashboardPhase10.spec.js`.

Current documentation relative links resolve. Git status/diff and ignore checks were read: the repository still has no HEAD commits/all project files are untracked; real env and `.runtime/` are ignored. Final changed-file hashes/logs are retained in `.runtime/phase15-final-files.json` and related logs. No commit, staging, publishing or cloud deployment was performed.

## Final decision

**PHASE 15: PASS. READY FOR DEMO/SUBMISSION** for the shipped application and documented COD demo at localhost:8088, after the short operator account/listing preparation. Full backend 195 tests / 2,141 assertions, frontend 160 tests, 143 HTTP/persistence checks, 32 browser page checks, Pint/lint/build and final container health passed; test fixtures are cleaned and development data is unchanged. ZaloPay Sandbox requires its separately documented authorized local configuration; live production payment/TLS/onboarding and the limitations above are not claimed complete.
