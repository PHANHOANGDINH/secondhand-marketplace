# Final testing summary

Verified **FINAL C1 on 2026-10-09**, reused for documentation-only C2. No application code changed; these are inherited results, not a new suite run. Evidence: [C1 verification](final-demo-verification.md).

| Verification | Confirmed result |
| --- | --- |
| Backend full | **352 tests / 3,504 assertions PASS** |
| Focused C1, included in full total | 3 tests / 78 assertions PASS |
| Frontend full | **355 tests / 28 files PASS** |
| Actual multi-browser | **166 checks PASS**, zero application console errors/uncaught exceptions |
| Responsive viewports | **1440 / 1024 / 768 / 390 px** |
| Pint / lint / production build | PASS / PASS / PASS |
| Seeder/data/cleanup | Identical rerun across 22 business tables; original rows unchanged; temporary records/uploads removed |

**Current automated totals — D6 Vouchers, run on 2026-10-14 with PHP 8.4.25:** backend **475 tests / 5,493 assertions PASS**, frontend **479 tests / 39 files PASS**, Pint, oxlint, ESLint and production build PASS. Includes a real-MySQL race (four processes, one use left) and rollback of the new migration. No browser pass was run for D6. Details: [D6 verification](extension-d6-vouchers-verification.md).

**Previous totals — UI redesign, seller card stats, buy now; run on 2026-10-14 with PHP 8.4.25:** backend **460 tests / 5,137 assertions PASS**, frontend **472 tests / 38 files PASS**, Pint, oxlint, ESLint and production build PASS. Browser pass at 1440/390 px: public pages as a guest on the dev server, signed-in pages (cart, checkout, orders, account, seller, admin, first-time tour) on an isolated stack bound to the test database, fixtures removed afterwards. Details: [UI redesign verification](ui-redesign-verification.md).

**Previous totals — D7, run on 2026-10-13 with PHP 8.4.25:** backend **455 tests / 5,059 assertions PASS**, frontend **456 tests / 37 files PASS**, Pint, oxlint, ESLint and production build PASS. No browser pass was run for D7. Details: [recommendation scoring](recommendation-scoring.md).

**Previous totals — D5, run on 2026-10-13 with PHP 8.4.25:** backend **444 tests / 4,950 assertions PASS**, frontend **449 tests / 36 files PASS**, Pint, oxlint, ESLint and production build PASS; a D5 browser pass (4 viewports, 9 flows, zero JS exceptions) ran on an isolated stack bound to the test database. Details: [D5 verification](extension-d5-admin-action-center-verification.md).

**Previous totals — fix wave 1–2, run on 2026-10-12 with PHP 8.4.25:** backend **420 tests / 4,523 assertions PASS** (pre-fix baseline on the same day: 399 / 3,948), frontend **431 tests / 35 files PASS** (baseline 413 / 33), Pint, oxlint, ESLint and production build PASS. The development database was hashed before and after (25 tables, 416 rows) and is unchanged. No new browser pass was run for these waves; the 166 browser checks above remain C1 evidence. Details: [fix wave 1–2 verification](fix-wave-1-2-verification.md).

Do not add the three focused tests again to 352. Browser checks are assertions across journeys/viewports, not 166 independently isolated E2E cases.

## Representative coverage

| Category | Evidence / scope |
| --- | --- |
| Unit/feature/integration | Money/model/domain behavior through services and HTTP tests against guarded MySQL test DB; actual local browser/API integration separately |
| Security | Session/CSRF, invalid/expired/reused reset tokens, generic unknown email, Admin/ownership/IDOR, tampering and raw-error suppression |
| Concurrency | Stock/checkout, cancellation/payment, concurrent bid minimum, finalization/expiry/checkout/reminder/scheduler races; controlled workers, not throughput measurement |
| Auction | Decimal increments, self-bid, time limits, winner/no-bid/idempotency, winner-only Order/price snapshot |
| Realtime | Payload/channel auth, after-commit/outage behavior, frontend dedup/reconnect; real two-browser bids/OUTBID and Theater comments |
| Messaging/notifications | Participant/owner IDOR, history/read/unread/persistence/dedup, polling cleanup and bell/navigation; actual Buyer/Seller polling reply |
| Video/content | Local MIME/container/size/ownership/visibility; hidden resources denied, plain text/moderation; browser native codec scope |
| Payment | Real normal/auction COD Orders; real ZaloPay Sandbox create → PENDING/gateway URL; callback/query/replay/reference/amount/cancellation automated tests |
| Responsive/accessibility | Four emulated widths, no settled overflow, focus/ESC, reduced motion, dialog/header cleanup, no stuck GSAP; targeted checks, not WCAG certification |
| Demo E2E | Home/Product/Cart/Checkout/Order, Account, Seller/Admin, timed multi-account Auction/winner checkout, Notifications, Theater and Messaging |

## Reproduction

Privately configure `backend/.env.testing` for **secondhand_marketplace_test**, APP_ENV=testing. TestCase checks configured and actual DB names; never repoint tests to development.

```powershell
# backend/
php artisan config:clear
php artisan test --compact
php vendor/bin/pint --dirty --format agent
# frontend/
npm run test:unit -- --run
npm run lint
npm run build
```

Use compatible PHP 8.4 and `npm.cmd` when Windows wrappers require it. Startup/preparation: [demo script](demo-script.md). No development migrate:fresh.

## Unverified boundaries

Final real paid ZaloPay callback/payment completion and external paid-status query were **not manually verified**. Public HTTPS tunnel/controlled Sandbox instruments are needed for that optional demo; automated tests are not external success. Real SMTP inbox receipt depends on controlled credentials/recipient. No production load/stress, multi-node deployment, physical-device, formal penetration/full contrast audit or backup-restore certification.

**PASS for local demo scope.** C2 checks documentation/schema/link/diagram sources; see [project index](final-project-index.md).
