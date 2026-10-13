# Secondhand Marketplace

A Vietnamese secondhand marketplace with Buyer, Seller and Admin interfaces. The application uses Vue 3/Vite, Bootstrap, Pinia, Chart.js and homepage-only GSAP; Laravel 13/Sanctum provides the JSON API, cookie authentication and MySQL persistence.

Canonical documentation entry point: [Final project index](docs/final-project-index.md), including the [release checklist](docs/final-release-checklist.md).

## Architecture

```text
Browser http://localhost:5173
  -> Vite (Vue SPA; development proxy /api, /sanctum, /storage, /placeholders)
     -> Laravel http://127.0.0.1:8000
        -> MySQL 127.0.0.1:3306 / secondhand_marketplace
        -> backend/storage/app/public (uploads)
        -> backend/storage/framework/sessions (file sessions)
```

The browser makes same-origin API requests through Vite. This avoids cross-site cookies between `localhost` and `127.0.0.1` while retaining Sanctum, CSRF, host-only HttpOnly session cookies and SameSite=Lax. Axios credentials/XSRF behavior is unchanged. The backend is also directly accessible at its stated URL.

## LOCAL DEVELOPMENT WITH XAMPP

Prerequisites: XAMPP MySQL/MariaDB, a compatible **PHP 8.4 CLI** for the installed lockfile, Composer 2, Node **22.18+ (22.x) or 24.12+**, and npm. Enable PHP PDO MySQL, fileinfo, GD, OpenSSL, DOM/XML and the other extensions checked by Composer. XAMPP's bundled PHP can be older than Laravel supports: check `php --version` and `composer check-platform-reqs`; use a compatible separate CLI instead of downgrading the application. Apache is not needed for Artisan/Vite.

### PHP runtime: 8.4.1 or newer is required

The installed `composer.lock` resolves packages that need **PHP ≥ 8.4.1**; `vendor/composer/platform_check.php` stops every Artisan command on anything older. `composer.json` still says `^8.3`, but the lockfile is what runs.

- **XAMPP's bundled PHP 8.0 cannot run this backend.** `php artisan …` fails immediately with "Your Composer dependencies require a PHP version >= 8.4.1".
- Recommended: use the PHP 8.4 CLI shipped with Laravel Herd and call it explicitly when `php` on PATH is the XAMPP one, for example `& "$env:USERPROFILE\.config\herd\bin\php84\php.exe" artisan serve --host=127.0.0.1 --port=8000`. `backend/serve.ps1 -PhpExecutable <path>` accepts the same executable.
- Alternative: replace XAMPP's `php` directory with a PHP 8.4 build (keep the required extensions enabled) so that `php --version` reports 8.4.1 or newer.
- **MySQL can stay on XAMPP** (or any existing local MySQL service on `127.0.0.1:3306`); only the PHP CLI has to be 8.4.

### The four processes

A working local system needs MySQL plus these four long-running processes, each in its own terminal:

| # | Directory | Command | Purpose |
| --- | --- | --- | --- |
| 1 | `backend/` | `php artisan serve --host=127.0.0.1 --port=8000` | JSON API |
| 2 | `backend/` | `php artisan reverb:start` | WebSocket server for auctions, notifications and video comments |
| 3 | `backend/` | `php artisan schedule:work` | Runs `auctions:sync` and `zalopay:reconcile` every minute |
| 4 | `frontend/` | `npm run dev` | Vue SPA and development proxy |

> **`schedule:work` is not optional.** Without it, auctions never switch to LIVE or ENDED on their own (no winner is finalized, no checkout reminder is sent) and unresolved ZaloPay attempts are never reconciled. Bids outside the time window are still rejected by the API, but the stored auction status stays stale until the scheduler runs.

### One command on Windows

`start-dev.ps1` in the repository root checks that MySQL answers on port 3306, then opens the four processes above in four PowerShell windows using the Herd PHP 8.4 CLI and opens `http://localhost:5173`. Edit the two paths at the top of the script if the repository or Herd is installed elsewhere.

```powershell
powershell -ExecutionPolicy Bypass -File .\start-dev.ps1
```

### First-time setup

Start XAMPP MySQL. Create `secondhand_marketplace` only if it does not exist. Configure a local database user with access to this database; keep its username/password in private `backend/.env`. Do not replace an existing database or existing env/key.

```powershell
cd backend
composer install
# Only if .env is missing:
Copy-Item .env.example .env
# Only if APP_KEY is empty on this initial setup:
php artisan key:generate
php artisan config:clear
php artisan migrate:status
# For a new database / reviewed pending migrations only (back up existing data first):
php artisan migrate
php artisan storage:link
# Warm mail templates to avoid a slow first reset email on Windows:
php artisan view:cache
composer check-platform-reqs
```

```powershell
cd frontend
npm install
# Only if .env is missing:
Copy-Item .env.example .env
```

### Exact startup order

1. Start **XAMPP MySQL** on `127.0.0.1:3306`. If another MySQL service already owns that port, resolve it deliberately and preserve its data; do not start a second server or move/delete database files blindly.
2. Start Laravel in terminal 1:

```powershell
cd backend
php artisan serve --host=127.0.0.1 --port=8000
```

3. Start Vue in terminal 2:

```powershell
cd frontend
npm run dev
```

4. Open **http://localhost:5173**. API health: **http://127.0.0.1:8000/api/v1/health**, or **http://localhost:5173/api/v1/health** through the proxy.

### Auction realtime, Arena, winner checkout and notifications (A2–A5)

Auction A1, A4 and A5 additive migrations must be applied with `php artisan migrate` (never `migrate:fresh` on development data). Configure private backend `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` and the matching **public key only** in frontend `VITE_REVERB_APP_KEY`. Restart Vite after changing its env. Reverb uses `127.0.0.1:8080` locally; Laravel and Vite keep their existing ports.

In two additional terminals:

```powershell
cd backend
php artisan config:clear
php artisan reverb:start
```

```powershell
cd backend
php artisan schedule:work
```

The scheduler starts/finalizes due auctions. Broadcasts run after commit without a queue worker; REST remains authoritative. A3 provides public discovery at `/auctions`, the realtime Arena at `/auctions/:id` and Seller management at `/seller/auctions`. A4 adds winner-only checkout at `/auctions/:id/checkout`, using the existing COD/ZaloPay Order flow. Configure `AUCTION_WINNER_CHECKOUT_HOURS=24` in the backend to set the claim window. Scheduled/live auctions and unexpired winner claims block fixed-price buying; after the winner Order reserves one unit, remaining stock can be sold normally. See [Auction A2 verification](docs/auction-a2-realtime-verification.md) for configuration and channels, and [Auction A3 UI verification](docs/auction-a3-ui-verification.md) for the Arena and Seller flows. See [Auction A4 settlement verification](docs/auction-a4-settlement-verification.md) for checkout, stock, exclusivity, tests and limitations.

A5 adds persistent auction/order notifications at `/notifications` and a bell in Buyer, Seller and Admin headers. The database is authoritative; private Reverb delivery happens after commit. Offline notifications reload on login, reconnect or returning to the app. Set `AUCTION_CHECKOUT_REMINDER_MINUTES=60` for a single pre-expiry reminder (`0` disables it). The same `schedule:work` process starts/finalizes auctions and checks reminders every minute. Keep it running during demos. See [Auction A5 verification](docs/auction-a5-notifications-verification.md) for recipients, deduplication, race checks and limitations.

For the default 50 MiB product-video limit, configure the CLI's `php.ini` with `upload_max_filesize=50M`, `post_max_size=60M`, `memory_limit=256M`, then restart the backend. Image validation remains 5 MiB/file and 8 images/product. The optional `backend/serve.ps1` applies `backend/dev/uploads.ini` to the normal CLI configuration and starts the same host/port. Application image/type/count rules remain unchanged.

Run `php artisan schedule:work` in another backend terminal when testing scheduled ZaloPay reconciliation. Stop local processes with Ctrl+C; neither process needs Apache or a container.

## Environment variables

| Setting | Local value / purpose |
| --- | --- |
| APP_ENV / APP_URL | `local` / `http://127.0.0.1:8000` |
| APP_KEY | Generate once; retain it for existing encrypted data/sessions |
| DB_CONNECTION / DB_HOST / DB_PORT | `mysql` / `127.0.0.1` / `3306` |
| DB_DATABASE | `secondhand_marketplace` |
| DB_USERNAME / DB_PASSWORD | Your private local database credentials; examples contain no password |
| FRONTEND_URLS | `http://localhost:5173`; exact credentialed CORS allowlist |
| SANCTUM_STATEFUL_DOMAINS | `localhost:5173` |
| SESSION_DRIVER / SESSION_DOMAIN | `file` / `null` (host-only) |
| SESSION_SECURE_COOKIE / SESSION_SAME_SITE | `false` for local HTTP / `lax` |
| TRUSTED_PROXIES | Empty for direct local serving |
| VITE_API_BASE_URL | `/`; do not point browser cookie-auth requests across hostnames |
| VITE_BACKEND_PROXY_TARGET | `http://127.0.0.1:8000` (Vite development only) |
| MAIL_MAILER / LOG_CHANNEL / LOG_STACK | `log` / `stack` / `single` |
| PASSWORD_RESET_URL | `http://localhost:5173/reset-password` |
| ZALOPAY_APP_ID / KEY1 / KEY2 | Private authorized Sandbox credentials; never frontend variables |
| ZALOPAY_CREATE_URL / QUERY_URL | Sandbox V2 endpoints from backend env example |
| ZALOPAY_RETURN_URL | `http://localhost:5173/payment/result` |
| ZALOPAY_CALLBACK_URL | Public HTTPS callback URL when testing delivery; local URLs cannot receive provider callbacks |

Copy examples only for fresh setup. Retain existing DB credentials, APP_KEY and merchant keys. After backend env changes run `php artisan config:clear` and restart; restart Vite after frontend env changes. Production SMTP remains configurable with `MAIL_MAILER=smtp`, `MAIL_SCHEME`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME` in a private env.

## Optional Gmail SMTP for password reset

The default private local env can keep `MAIL_MAILER=log`. To send real email, configure these values **only in `backend/.env`** after obtaining credentials for a sender you control:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_SCHEME=smtp
MAIL_USERNAME=your-controlled-sender@gmail.com
MAIL_PASSWORD="your-google-app-password"
MAIL_FROM_ADDRESS=your-controlled-sender@gmail.com
MAIL_FROM_NAME="Secondhand Marketplace"
PASSWORD_RESET_URL=http://localhost:5173/reset-password
```

The values above are placeholders, not credentials. Use a **Google App Password**, never your normal Gmail account password. Enable 2-Step Verification on an eligible account and generate an App Password privately; some organizational/security policies prevent App Passwords. See [Google App Password instructions](https://support.google.com/accounts/answer/185833) and [Google SMTP settings](https://support.google.com/mail/answer/7104828?hl=en). The sender should match MAIL_USERNAME unless an authorized sending alias is configured.

The installed Laravel mail configuration already reads all these variables. `MAIL_SCHEME=smtp` with port 587 uses Symfony's automatic **STARTTLS** negotiation; `tls` is not a valid scheme here. `smtps` is implicit TLS, usually port 465, and is not this 587 configuration. Keep certificate verification and automatic TLS enabled; do not disable either to bypass connection errors. [Symfony Mailer TLS behavior](https://symfony.com/doc/current/mailer.html#tls-peer-verification) describes the transport. Remove an old `MAIL_URL` override if present before using individual SMTP env variables. PHP OpenSSL and outbound access to smtp.gmail.com:587 are required.

After changing private env values:

```powershell
cd backend
php artisan config:clear
php artisan view:cache
# Restart an already running backend, then:
php artisan serve --host=127.0.0.1 --port=8000
```

Use Login -> Forgot Password at http://localhost:5173 with a controlled demo recipient that has an account in the selected safe demo database. Confirm Inbox/Spam receipt, open the localhost:5173 reset link, choose a new application password and log in. Reusing that link must fail. An unknown address must receive the same generic API message without receiving a reset email. SMTP acceptance alone does not prove inbox receipt; the recipient must confirm it. Do not change existing development account emails/passwords solely to demonstrate delivery; use a disposable account/database.

For developers without SMTP credentials, set `MAIL_MAILER=log`, clear config cache and restart. Reset email/link then appears in private `backend/storage/logs/laravel.log`. This is an explicit fallback, not an automatic SMTP-to-log substitution. Generic forgot-password success is not proof of delivery: transport errors intentionally preserve that response, so inspect sanitized notification errors privately. Never publish App Passwords, reset links, env files, SMTP exception details or logs.

Troubleshooting: verify sender/App Password eligibility and authentication, port 587 connectivity, TLS/CA trust and the intended mailer; check spam and account-provider sending limits. Gmail-account password changes can revoke App Passwords. Do not use the normal Gmail password or turn off TLS verification.

SMTP setup and regression checks passed; authenticated delivery/inbox verification is pending private credentials and a controlled recipient. See [SMTP verification](docs/deployment-guide.md#smtp-verification--2026-10-07) for exact results.

## Password reset, uploads and payment testing

Login → “Quên mật khẩu?” sends a generic response. With the default log mailer, find the reset email/link in **`backend/storage/logs/laravel.log`**, open the localhost:5173 reset link and return to Login after resetting. Keep log files private: reset links are sensitive. No Mailpit service is required. The broker handles token expiry and one-time use; normal responses contain no token.

Product uploads use Laravel's **public** disk explicitly. `php artisan storage:link` exposes `storage/app/public` at `public/storage`; on Windows the link can be a junction. Files persist on the local filesystem across backend restarts. Preserve that directory and existing files. Do not expose private storage or logs. Existing avatar/shop-logo capabilities are unchanged.

COD is the reproducible offline payment demo. ZaloPay uses authorized **Sandbox** credentials locally; production keys/endpoints are separate. For callback delivery, use a public HTTPS tunnel (for example Cloudflare Tunnel) targeting the backend at `http://127.0.0.1:8000`, then set the callback URL ending `/api/v1/payments/zalopay/callback`. Trust only an explicitly configured proxy if one is actually needed; never set a wildcard just to enable local auth. The backend verifies gateway MAC, reference, amount and idempotency. A return URL does not prove payment. Real production payment/TLS hosting is outside this local workflow.

## Demo data and presentation

Use the additive **FinalDemoSeeder** for the complete local C1 demo. Configure a private `DEMO_PASSWORD` (at least 12 characters) in `backend/.env`; no usable password is committed. It creates six isolated `c1-*@example.test` accounts: one Admin, two Sellers and three Buyers. Existing accounts/passwords and unrelated records are preserved; reruns do not reset presentation progress.

```powershell
cd backend
php artisan config:clear
php artisan db:seed --class=FinalDemoSeeder --no-interaction
```

The older `DatabaseSeeder` / `DemoUserSeeder` accounts (`admin@example.com`, `seller@example.com`, …) read the same private `DEMO_PASSWORD`; no password literal is committed, seeding stops with a clear error when the variable is missing or shorter than 12 characters, and accounts that already exist keep their current password.

The pack contains two ACTIVE shops, 18 dedicated products using current categories/local owned artwork, valid auctions/bids and winner settlement, COD Orders with lawful fulfillment, published purchase-linked reviews, small local demo videos/comments, persistent notifications and Buyer/Seller conversations. Older catalog listings remain in place. The media is labeled test-pattern demo footage, not an authentic review. The earlier catalog-only DemoMarketplaceSeeder remains available for its original deficit-filling use.

Demo accounts (all use the private `DEMO_PASSWORD` set when they were first seeded):

| Role | Email |
| --- | --- |
| Admin | `c1-admin@example.test` |
| Seller 1 (C1 Demo · Góc Công Nghệ) | `c1-seller-1@example.test` |
| Seller 2 (C1 Demo · Nhà & Phong Cách) | `c1-seller-2@example.test` |
| Buyers | `c1-buyer-a@example.test`, `c1-buyer-b@example.test`, `c1-buyer-c@example.test` |

Demo voucher codes created by the current seeder: **`PASSLAI10`** (platform, 10 %, at most 50,000 ₫, order from 200,000 ₫) and **`CONGNGHE20K`** (shop "Góc Công Nghệ", 20,000 ₫, shop order from 100,000 ₫). The seeder also adds one payment awaiting reconciliation and view/search signals so the admin payments page and "Gợi ý cho bạn" are not empty. A database seeded with an older version of the seeder does not have them until the seeder is run again.

Keep four terminals running (start MySQL first):

```powershell
# backend/ — terminal 1
php artisan serve --host=127.0.0.1 --port=8000
# backend/ — terminal 2
php artisan reverb:start
# backend/ — terminal 3
php artisan schedule:work
# frontend/ — terminal 4
npm run dev
```

Open `http://localhost:5173`. [Presentation script and accounts](docs/demo-script.md) gives exact preparation and Buyer/Seller/Admin/Auction/Video/Messaging journeys. [Final demo verification](docs/final-demo-verification.md) records integration, tests, security and limitations. Password recovery remains available with log mail; reset links are private. Never use migrate:fresh to reset a presentation. A new short auction must be prepared through Seller UI when demonstrating winner checkout live; seeding does not extend old deadlines.

## Tests

Backend tests strictly require a separate MySQL database named **`secondhand_marketplace_test`** and APP_ENV=testing. Create it and grant a configured test user access; copy `backend/.env.testing.example` to `.env.testing` only if missing, fill private credentials/key, and retain the development database separately. Do not redirect tests to the development DB.

```powershell
cd backend
php artisan config:clear
php artisan test --compact
php vendor/bin/pint --dirty --format agent
```

```powershell
cd frontend
npm run test:unit -- --run
npm run lint
npm run build
```

`npm run build` creates static files in `frontend/dist`; the Vite development proxy is not bundled into production output. Production hosting needs a separately configured API/reverse proxy; `vite preview` is not this documented authenticated development workflow.

## Project structure and features

- `backend/`: Laravel API, policies, validation, services, migrations, seeders and PHPUnit tests.
- `frontend/`: Vue pages/layouts, API clients, Pinia stores, shared UI, Vite configuration and Vitest tests.
- `docs/`: API summary, current local setup/demo guide and historical verification reports.

Implemented: registration/login/logout/session/profile, forgot/reset password, owned addresses, public product/shop/category discovery, seller shop/product/image workflows, moderation, cart, transactional idempotent checkout, COD, ZaloPay V2 Sandbox integration, buyer/seller orders, reviews, Seller/Admin dashboards and administration. Buyer/seller text messaging, persistent in-app auction notifications and auction backend/realtime infrastructure are also available. Homepage Video Review discovery, Theater mode and persistent realtime plain-text comments are implemented. The realtime auction Arena and Seller auction management are implemented. Winner checkout creates one ordinary Order/ShopOrder/OrderItem at the winning bid price and reuses COD/ZaloPay payment attempts; refund, payout and commission remain out of scope.

## Status and limitations

Extension status: D1 search/filter ✅, D2 wishlist ✅, D3 reports ✅, **D4 Seller Inventory & Action Center ✅** (`/seller/inventory`, action center on the Seller dashboard; see [D4 verification](docs/extension-d4-seller-inventory-verification.md)). The post-audit fix waves are recorded in [fix wave 1–2 verification](docs/fix-wave-1-2-verification.md). **D5 Admin Action Center ✅** (`/admin`, payment reconciliation at `/admin/payments`, auction oversight and Admin cancellation at `/admin/auctions`; see [D5 verification](docs/extension-d5-admin-action-center-verification.md)). D5 adds two additive migrations: run `php artisan migrate` after pulling (three nullable columns on `payments`, one CHECK on `reports`). Optional thresholds: `ADMIN_FLAGGED_MIN_REPORTS=3`, `ADMIN_FLAGGED_WINDOW_DAYS=30`, `ADMIN_AUCTION_OVERDUE_MINUTES=2`. **D7 Recommendations ✅** ("Gợi ý cho bạn" on the home page, "Sản phẩm tương tự" on product detail; formula in [recommendation scoring](docs/recommendation-scoring.md)). D7 adds one additive migration (`product_views`, `search_logs`, two indexes): run `php artisan migrate` after pulling. Weights live in `backend/config/recommendations.php`. D6 was skipped by decision.

Current setup is Windows/local development. [Local setup and troubleshooting](docs/deployment-guide.md), [API summary](docs/api-summary.md), and [local verification](docs/xampp-local-verification.md) are current references. Previous deployment/phase reports are historical; their removed infrastructure commands are not current setup instructions. Archived container data volumes were retained during removal to avoid deleting previously seeded data.

This machine's XAMPP PHP is 8.0.30 and its MariaDB is not the server currently owning port 3306. Verification uses the compatible PHP 8.4 CLI and existing MySQL service, leaving the development database unchanged. Switch the database service only with a deliberate backup/migration plan. Exact XAMPP MariaDB compatibility remains unverified. Public callback delivery needs a tunnel; live merchant payments, TLS production hosting, backup restore and multi-node operation are not verified. File sessions/storage are local to one machine; reconciliation requiring manual intervention has no dedicated UI.

## Product video reviews (B1)

Apply the additive Product Video migration with `php artisan migrate`; never reset development data. Seller product editor and Quick Listing include optional MP4/WebM uploads after saving a draft, title/description, primary/order, hide/show and confirmed deletion. Public Product Detail shows READY videos only when the existing Product/Shop visibility rules pass. Product Lists never load video players. B1 does not change Product moderation, Auction, Order or Payment behavior.

Default: three videos/product, 50 MiB each. `PRODUCT_VIDEO_MAX_KB=51200` is configurable in private backend env. PHP's upload/post caps must also allow it. The bundled helper keeps the normal CLI configuration and applies local limits:

```powershell
cd backend
.\serve.ps1
# If PHP is not on PATH, pass your compatible PHP 8.4 executable:
# .\serve.ps1 -PhpExecutable 'C:\path\to\php.exe'
```

Alternatively configure the same limits privately in php.ini before `php artisan serve --host=127.0.0.1 --port=8000`. Restart backend after changes. Keep `php artisan storage:link` pointing to `storage/app/public`. No ffmpeg dependency is added: poster/duration remain nullable, native browser codec support applies. See [Video B1 verification](docs/video-b1-verification.md) for APIs, permissions, tests, demo fixtures and storage limitations.

## Final documentation and defense pack

Start with the [final project index](docs/final-project-index.md). It separates current implementation from historical planning/verification reports.

- [System overview](docs/final-system-overview.md) and [module inventory](docs/final-module-inventory.md): roles, delivered modules, rules and API groups.
- [Architecture](docs/final-architecture.md), [ERD](docs/final-erd.md), [use cases](docs/final-use-cases.md), [sequences](docs/final-sequences.md) and [auction states](docs/auction-state-machines.md): current Mermaid diagrams grounded in code/migrations.
- [Security](docs/final-security-summary.md) and [testing summary](docs/final-testing-summary.md): controls, C1 totals and explicit external/production limits.
- [7–10 minute defense demo](docs/demo-script.md) and [Q&A](docs/defense-qa.md): preparation, clicks/talking points/results and fallbacks.

Current automated totals after D8 Final Integration (2026-10-14): **475 backend tests / 5,502 assertions**, **479 frontend tests / 39 files**, Pint/lint/build PASS, the race suites re-run separately (21 tests), and **browser journeys A–H plus the voucher screens PASS** at 1440/390 px on an isolated stack bound to the test database ([D8 verification](docs/extension-d8-final-integration-verification.md), [how to run them](frontend/e2e/README.md)). **D8 ✅**; local tag `v1.0-defense`. **D6 Vouchers ✅** (shop and platform codes at checkout, `/seller/vouchers`, `/admin/vouchers`; rules, lock order and tests in [D6 verification](docs/extension-d6-vouchers-verification.md)); it adds one additive migration, so run `php artisan migrate` after pulling. Before D6, after the UI redesign, seller card stats and buy now, the totals were 460 / 5,137 and 472 / 38, plus a browser pass at 1440/390 px (public pages on the dev server as a guest; signed-in pages on an isolated stack bound to the test database). See [UI redesign verification](docs/ui-redesign-verification.md). After D7 the totals were 455 / 5,059 and 456 / 37. After D5 the totals were 444 / 4,950 and 449 / 36, with a D5 browser pass at 1440/1024/768/390 px on an isolated test stack. Historical C1 local-demo baseline: **352 backend tests / 3,504 assertions**, **355 frontend tests / 28 files**, **166 actual browser checks**, Pint/lint/build PASS. C2 is documentation-only and does not imply another regression run or production certification. Sandbox creation is verified; completed real payment/callback remains unverified manually. No real password/secret is included in the defense material.
