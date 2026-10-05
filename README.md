# Secondhand Marketplace

A Vietnamese marketplace for buying and selling used products. Buyers browse moderated listings, manage addresses, check out with COD or ZaloPay, track orders and review completed purchases. Shop owners manage products, uploads, fulfillment and revenue. Administrators moderate listings/reviews, manage categories/account status and view system/order dashboards.

Feature freeze is active. See [final verification](docs/final-verification.md), [demo guide](docs/demo-guide.md), [deployment guide](docs/deployment-guide.md) and [implemented API summary](docs/api-summary.md).

## Architecture and stack

```text
Browser -> Nginx -> Vue production SPA
                 -> /storage/* -> persistent public uploads
                 -> /api/* and /sanctum/csrf-cookie -> Laravel PHP-FPM -> MySQL
```

Frontend: Vue 3, Vue Router, Pinia, Axios, Bootstrap 5, Chart.js and Vite. Backend: Laravel 13, Sanctum 4, Eloquent and MySQL. Testing/quality: PHPUnit, Vitest, Pint, ESLint and Oxlint. Stateful cookie sessions and CSRF protect authenticated writes; policies enforce ownership. Stock/payment totals are server controlled. Auth tokens are not stored in browser storage.

Local development: Vue at http://localhost:5173 and Laravel at http://localhost:8000. Docker: one origin, currently http://localhost:8088, a separate MySQL database and persistent volumes. Production requires HTTPS termination and production merchant credentials; local Docker verification deliberately uses HTTP.

## Prerequisites

- PHP 8.4 recommended (Composer requires ^8.3), Composer 2, normal Laravel extensions plus PDO MySQL, BCMath, Fileinfo and GD with JPEG/PNG/WebP.
- Node.js ^22.18.0 or >=24.12.0 and npm; Docker uses Node 24.
- MySQL and a limited application user. Tests require a separate database named `secondhand_marketplace_test`.
- Docker Engine/Desktop with Linux containers and Docker Compose for deployment; Docker uses MySQL 8.4.
- Git. Examples use PowerShell; use `npm` instead of `npm.cmd` on other platforms.

## Local development setup

From `backend/`:

```powershell
composer install
# First setup only, when .env does not exist:
Copy-Item .env.example .env
php artisan key:generate
```

Preserve an existing private `.env` and APP_KEY. Create the intended development database/restricted user and fill DB_HOST/PORT/DATABASE/USERNAME/PASSWORD. Example defaults use `secondhand_marketplace` on 127.0.0.1:3306. After confirming the connection:

```powershell
php artisan config:clear
php artisan migrate
php artisan storage:link
.\serve.ps1
```

`serve.ps1` starts localhost:8000 with project-local upload limits; supply `-PhpExecutable <php.exe path>` if needed. On other platforms: `php -d upload_max_filesize=5M -d post_max_size=45M -d memory_limit=256M artisan serve --host=localhost --port=8000 --no-reload`.

From `frontend/` in another terminal:

```powershell
npm.cmd ci
# Only if a private frontend .env does not exist:
Copy-Item .env.example .env
npm.cmd run dev -- --host=localhost --port=5173 --strictPort
```

Open localhost:5173. Use **localhost** consistently for browser origins; DB_HOST may remain 127.0.0.1. VITE_API_BASE_URL defaults to http://localhost:8000; CORS/Sanctum must match the frontend origin/authority. Restart servers after env changes. Do not run `migrate:fresh` against development data.

## Docker deployment setup

Follow the [deployment guide](docs/deployment-guide.md) to create the gitignored `.env.docker-verification` with new independent secrets and localhost:8088 settings. Preserve an existing key/env/volumes.

```powershell
# Repository root
 docker compose --env-file .env.docker-verification config --quiet
 docker compose --env-file .env.docker-verification build
 docker compose --env-file .env.docker-verification up -d --wait --wait-timeout 180
 docker compose --env-file .env.docker-verification ps -a
 curl.exe --fail http://localhost:8088/api/v1/health
```

Only Nginx publishes a host port. Compose runs MySQL, a one-shot ordinary migration service, PHP-FPM, the existing reconciliation scheduler and Vue/Nginx. Named volumes retain MySQL data and uploads/sessions/cache. Routine shutdown uses `down`, without `-v`. Fresh Docker databases contain no default accounts or seeded products.

Production uses a private `.env.production` from `.env.production.example`, exact public authority, HTTPS termination, Secure cookies and stable secrets. Sandbox endpoints are rejected with APP_ENV=production. The deployment guide covers exact commands, proxy trust, ingress and troubleshooting.

## Environment variables

| Area | Requirements |
| --- | --- |
| Laravel | APP_ENV, APP_DEBUG, APP_KEY, APP_URL; production debug false, private stable key |
| Database | DB_CONNECTION=mysql, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD; development/test/Docker connections remain separate |
| SPA auth | FRONTEND_URLS exact origin; SANCTUM_STATEFUL_DOMAINS host[:port] without scheme; SESSION_DOMAIN=null, SESSION_SECURE_COOKIE=true over HTTPS, SameSite=Lax |
| Proxy | TRUSTED_PROXIES empty for direct serving; Compose trusts only its static Nginx IP |
| Frontend | VITE_API_BASE_URL for development; Docker uses a relative base; never put secrets in VITE variables |
| Docker | COMPOSE_PROJECT_NAME, PUBLIC_HOST/SCHEME/PORT, PUBLISH_ADDRESS, HTTP_PORT, APP_SUBNET, NGINX_INTERNAL_IP; independent DB_PASSWORD/MYSQL_ROOT_PASSWORD |
| ZaloPay | ZALOPAY_APP_ID, ZALOPAY_KEY1/KEY2, ZALOPAY_CREATE_URL/QUERY_URL/RETURN_URL/CALLBACK_URL/EXPIRE_SECONDS |

Backend `.env.example` has secret-free Sandbox URLs, localhost:5173 return and 900-second expiry; keys are empty. Root `.env.production.example` supplies production endpoints and empty credentials with configurable callback/return URLs. Set every payment endpoint/expiry explicitly when enabling it. Never replace a production credential with a Sandbox key.

## Test commands

Create `backend/.env.testing` from `.env.testing.example` only if absent. Provision `secondhand_marketplace_test`, its private credentials and first-time test APP_KEY (`php artisan key:generate --env=testing`). The test harness checks the actual MySQL database name before rebuilding the **test** schema; SQLite is not a substitute.

```powershell
# backend/
php artisan config:clear
php artisan test --compact
php vendor/bin/pint --test --format agent
# frontend/
npm.cmd run test:unit -- --run
npm.cmd run lint
npm.cmd run build
```

Docker smoke/persistence tests use Python 3 standard-library helpers under `docker/verification/`, only against `marketplace_phase14`; fixtures are nonce-scoped and cleaned. Exact commands and final totals are in the deployment/final-verification guides.

## Accounts and demo preparation

Register accounts with privately chosen passwords: at least 12 characters including letters/numbers, at most 72 bytes. Registration grants USER/ACTIVE. A seller is a user owning a shop; there is no public SELLER/Admin role selector. Only an operator provisions an administrator; see the demo guide. No real passwords, keys or private account credentials are published here.

Optional local-only seeders include reserved examples `admin@example.com`, `seller@example.com`, `buyer@example.com`. Their fixed development credentials are unsuitable for production and must be replaced if those accounts are used. Seeders refuse production/Docker databases. Seeding was not run during final verification. Fresh registration and the documented moderation flow are preferred for demonstrations.

## Project structure

```text
backend/                Laravel requests, policies, services, resources, routes and tests
frontend/src/           Vue views/components, API helpers, stores, router and tests
docker/nginx/           Static/API/public-storage Nginx template
docker/verification/    Isolated HTTP/persistence and fixture cleanup helpers
compose.yaml            MySQL, migration, backend, scheduler and frontend/Nginx
.env.production.example Secret-free production configuration template
docs/                   Current guides and historical design/phase evidence
.runtime/               Gitignored logs, private verification state and cookie jars
```

## Features and known limitations

Implemented: auth/profile/default addresses; shop approval; hierarchical categories; searchable/filterable paginated catalog; seller product lifecycle and sanitized image uploads; cart; whole-cart COD/ZaloPay checkout/idempotency; stock/order concurrency guards; buyer cancellation/history; seller fulfillment; purchase-verified reviews/moderation; seller revenue charts; admin status/category/shop/product/review management, read-only orders/system dashboards; scheduled ZaloPay reconciliation; Docker persistence.

Not implemented: notifications, chat, auctions, refunds, payouts, commissions, password recovery, email verification, OAuth/2FA, cloud storage or reconciliation management UI. Shipping/discount are zero. Late/ambiguous online money requires manual reconciliation; expired online reservations are not automatically released. Retained upload URLs remain public when listings become hidden. File sessions/cache suit a single backend; multi-node stores, public TLS, merchant onboarding, live callback/wallet E2E, backup restore and production load testing need separate work.

Historical design/requirements/state/permission files may include proposed or superseded scope; they are not an endpoint contract. The current [API summary](docs/api-summary.md) and [final verification](docs/final-verification.md) describe shipped behavior. Phase 12 records earlier user-confirmed Sandbox card payment; the final run does not claim a new live payment.
