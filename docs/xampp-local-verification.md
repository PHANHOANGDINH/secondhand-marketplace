# Windows / XAMPP local-development verification

Date: 2026-10-07. Scope: remove container infrastructure and restore the existing application to local development. Business code, API contracts, Pinia, application routes, authentication rules and payment/order transitions were preserved. The workspace already contained uncommitted UI, password-reset and demo-data work; it was retained.

## Architecture and current configuration

```text
Browser http://localhost:5173 -> Vue/Vite
  /api, /sanctum, /storage, /placeholders -> http://127.0.0.1:8000 Laravel
  Laravel -> 127.0.0.1:3306 / secondhand_marketplace
  uploads -> backend/storage/app/public -> backend/public/storage
  file sessions -> backend/storage/framework/sessions
```

Vite's development proxy keeps browser auth requests same-origin despite the requested different hostnames. `VITE_API_BASE_URL=/`; `VITE_BACKEND_PROXY_TARGET=http://127.0.0.1:8000`. Axios credentials/XSRF handling is unchanged. Backend APP_URL is 127.0.0.1:8000, CORS is exact localhost:5173 with credentials, Sanctum stateful authority is localhost:5173, session domain is null, sessions remain HttpOnly/SameSite=Lax, Secure=false is confined to local HTTP configuration, and trusted proxies remain empty. No CSRF bypass or wildcard CORS/proxy trust was introduced.

Backend DB settings: mysql, 127.0.0.1, 3306, secondhand_marketplace. Existing private username/password, APP_KEY and Sandbox merchant credentials were retained. No real credentials were added to examples. Log mail is the default; SMTP remains env-configurable. Password reset points to localhost:5173/reset-password. Public storage uses its existing Windows junction and normal local files, without volumes.

## Removed files

- `.dockerignore`
- `.env.production.example` (container-only root example)
- `.env.docker-verification` (private, ignored)
- `compose.yaml`
- `backend/.dockerignore`
- `backend/Dockerfile`
- `backend/docker/entrypoint.sh`
- `backend/docker/fpm.conf`
- `backend/docker/healthcheck.sh`
- `backend/docker/php.ini`
- `frontend/Dockerfile`
- `docker/nginx/default.conf.template`
- `docker/verification/browser-smoke.mjs`
- `docker/verification/database.php`
- `docker/verification/http-smoke.py`

15 infrastructure files removed, including 14 tracked files. Both obsolete deployment directories were removed. Sixteen additional ignored/private deployment helper scripts were removed from `.runtime`; historical result logs, hashes and screenshots were preserved. No normal local env, source, migration or test suite was deleted.

The project services/networks were stopped/removed before deleting configuration, including the optional Mailpit service. No application containers remain running for the former project. Named MySQL/storage volumes were retained as archived data; they are not used by the local runtime. No volume wipe or development database reset occurred.

## Files modified / added for this task

Configuration / local helpers:

- `.gitignore`: remove the obsolete production-example exception.
- `backend/.env.example`, `backend/.env.testing.example`: correct backend origin; remove the container proxy assumption from the local example.
- Private `backend/.env`: correct APP_URL; explicit local trusted-proxy/reset URL settings, retaining secrets and DB credentials.
- `backend/serve.ps1`: bind 127.0.0.1:8000 and retain the local upload-limit helper.
- `frontend/.env.example` and private `.env`: relative browser API base and env-driven backend proxy target.
- `frontend/vite.config.js`: exact local host/port, strict port and development proxy.
- `frontend/vitest.config.js`: resolve the environment-driven Vite callback before merging test configuration.

Current documentation:

- `README.md`, `backend/README.md`, `frontend/README.md`
- `docs/deployment-guide.md`, `docs/demo-guide.md`, `docs/api-summary.md`
- `docs/xampp-local-verification.md` (this new report)

Historical reports retain their original results, with a short historical/current-setup notice in `docs/phase-14-verification.md`, `docs/final-verification.md`, `docs/frontend-ui-verification.md`, `docs/password-reset-verification.md`, and `docs/demo-data-pack.md`. Their old deployment commands are historical evidence, not current startup instructions. Earlier phase report bodies were preserved.

No dependency was added. No business source or route changes were made for this task.

## Exact startup order

1. Start XAMPP MySQL on 127.0.0.1:3306, after deliberately resolving any conflicting service without deleting its data.
2. Start Laravel with compatible PHP in terminal 1:

```powershell
cd backend
composer install
# Fresh checkout only: copy .env.example to .env and fill private DB credentials.
# Only if APP_KEY is initially empty:
php artisan key:generate
php artisan config:clear
php artisan migrate:status
# New database / reviewed pending migrations only, with a backup:
php artisan migrate
php artisan storage:link
php artisan view:cache
php artisan serve --host=127.0.0.1 --port=8000
```

3. Start Vue in terminal 2:

```powershell
cd frontend
npm install
# Fresh checkout only: copy .env.example to .env.
npm run dev
```

4. Open http://localhost:5173. Start `php artisan schedule:work` separately when demonstrating scheduled payment reconciliation. Use a compatible PHP CLI on PATH; Apache is not part of this workflow.

For a safe, explicitly selected demo database, `php artisan db:seed --class=DemoMarketplaceSeeder` adds only a deficit below 20 public products. Do not use the full DatabaseSeeder or reset existing development data to prepare a demo. This switch did not seed development; its existing 28 products remain unchanged.

## Regression verification

| Check | Result |
| --- | --- |
| Composer installed-package platform requirements, PHP 8.4.25 | PASS |
| Full backend `php artisan test --compact` | **218 tests, 2,455 assertions PASS** |
| Pint `php vendor/bin/pint --dirty --format agent` | PASS |
| Final full frontend `npm run test:unit -- --run` | **190 tests / 18 files PASS** |
| Frontend `npm run lint` | PASS |
| Frontend `npm run build` | PASS, 184 modules transformed |
| Direct backend and proxied `/api/v1/health` | PASS |
| HTTP auth/domain checks | **71 checks PASS** |
| Public browser checks | **52 checks PASS** |
| Authenticated browser checks | **261 checks PASS** |
| Restart HTTP/session/upload checks | **11 checks PASS** |
| Isolated DB across restart | All 23 table row counts/hashes identical |
| Development DB preservation | All 23 table row counts/SHA-256 identical |
| Existing public upload preservation | File inventory/SHA-256 identical after fixture cleanup |
| Existing migration compatibility | All 18 migrations already applied; no development migrations run |
| Git whitespace check | PASS |

Backend tests use only secondhand_marketplace_test with the existing strict safety guard. The HTTP/browser write verification temporarily served that same isolated database at port 8000 with APP_ENV=local, so normal CSRF middleware remained active. Private test-env credentials/key were passed only to that temporary process; backend/.env continued to designate development. The normal development backend was restored afterwards. The isolated test DB was initially empty apart from migrations; five fictional accounts and their domain fixtures were removed, with migration history retained. The one newly uploaded test image was removed. No development business records or existing upload bytes changed.

Owned local servers are running at 8000/5173 at completion; the isolated headless browser was closed. Private test credential/cookie files were removed. Detailed private results are ignored under `.runtime/xampp-*`; they are not part of source control.

## HTTP / browser coverage

HTTP checks covered direct/proxied health; a real session/XSRF cookie flow; missing-CSRF rejection (419); exact credentialed backend CORS; rejection of an untrusted origin by the allow-origin value; register/login/logout/me; ordinary-user Admin denial; cross-user address/order denial; Seller and Admin reads; draft product creation, valid WebP upload, submit and permitted Admin approval; public product visibility; address/cart; COD checkout and idempotent replay; order reads; allowed Seller fulfillment with COD collection; paid status; and a valid purchase review.

An authorized Sandbox create request produced a **PENDING** payment with an approved **HTTPS qcgateway.zalopay.vn** URL for an isolated order. No gateway payment was completed and no real money moved. The fixture was removed afterwards. Production payment and public callback delivery were not claimed.

Browser registration produced the disposable account, followed by real Login -> Forgot Password -> Laravel log reset link -> reset form -> Login with new password -> Logout. Reset success removed token parameters from the URL; token reuse returned 422. The unchanged backend suite also covers invalid/expired tokens, unknown accounts, rate limiting and password validation.

Public checks covered Home, catalog, product detail/images, Login/Register/Forgot at 1440, 1024, 768 and 390px. Authenticated checks covered 21 Buyer/Account/Seller/Admin pages at all four widths (84 page/viewport visits): account/profile/addresses/cart/checkout/orders/order detail; seller dashboard/products/create/edit/orders/order detail/shop; admin dashboard/users/shops/products/categories/orders/reviews. Navigation and data loading succeeded with no page-level horizontal overflow and no unexpected console/runtime errors. Public image URLs were decoded successfully, including lazily loaded images. Existing homepage animation remained isolated; navigation to ordinary pages worked.

After restarting the isolated backend, Buyer/Seller/Admin session cookies still authenticated, the completed COD order/payment remained available, upload bytes matched their original test hash, and both direct and proxied image URLs returned 200. All isolated table row hashes remained unchanged across the restart.

## Local email / upload notes

MAIL_MAILER=log writes reset email/link to `backend/storage/logs/laravel.log`; keep it private. The first cold mail render on Windows took about 26 seconds and exceeded the unchanged 10-second Axios timeout. `php artisan view:cache` warmed templates; the repeat request completed in about 2 seconds and the browser reset flow passed. This command is now in first-time setup/troubleshooting. No timeout or auth business behavior was changed.

Product image writes/reads were verified through the real local public-storage junction. Use the existing 5 MiB file limit and upload PHP settings from `backend/dev/uploads.ini`; optional `./serve.ps1` applies them. Existing upload rules were not changed. Files persist on the host filesystem across restarts.

## Remaining limitations and final decision

- The server actually owning port 3306 is **MySQL267**, installed under Program Files/MySQL; XAMPP MariaDB 10.4.32 is installed but is not running on that port. It was not started over, swapped with, or populated from the existing server because development data must remain untouched. Exact XAMPP MariaDB compatibility is therefore unverified.
- Installed XAMPP PHP is **8.0.30**, too old for this application. The installed lockfile passes with the existing **PHP 8.4.25 CLI**. Use that compatible CLI alongside XAMPP MySQL; do not downgrade application packages to the bundled old PHP.
- Public ZaloPay callback E2E needs a reachable HTTPS tunnel and configured merchant callback URL; no public callback, wallet/card payment or production payment was performed in this task.
- Cold email rendering should be warmed as documented. SMTP delivery was not tested; local log delivery was tested.
- Production static hosting/reverse proxy/TLS is outside this local workflow. The Vite development proxy is not included in production build output.

**Windows local application workflow: PASS.** Configuration cleanup, regression, HTTP/browser auth/domain flows, local email/uploads and restart persistence passed; development data was preserved.

**Strict XAMPP local-development readiness: FAIL / pending environment setup.** A full XAMPP PASS cannot be claimed while its MySQL is not the verified server on port 3306. Resolve that service/data migration deliberately, use compatible PHP, then re-check health/schema/auth on the actual XAMPP instance. No application defect is being hidden by that environment limitation.
