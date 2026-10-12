> Historical verification report. Container infrastructure has since been removed. Results below retain their original context; use the [current local setup](deployment-guide.md) and [local verification](xampp-local-verification.md) for present instructions.

# PHASE 14 — Docker, Nginx and deployment verification

Verification date: 2026-10-05 (Asia/Saigon). Feature freeze retained; deployment configuration and verification only. Development database migrations, resets and seeders were not run.

## Architecture and containers

```text
Browser -> public HTTPS terminator (production) -> Nginx
                                                  |-> Vue production files / SPA fallback
                                                  |-> /storage/* -> public upload volume (read-only)
                                                  |-> /api/*, /sanctum/csrf-cookie, /up
                                                        -> Laravel PHP-FPM -> private MySQL
```

One public origin serves frontend, API and CSRF cookies. Vue uses a relative API base; local frontend `.env` files do not enter the image. Nginx always executes Laravel's fixed `public/index.php` for API requests; it never executes an uploaded PHP file. Dotfiles and direct PHP paths return 404. Public uploads are served directly from the shared volume; private Laravel storage is outside the served alias.

| Service | Image/runtime | Purpose and lifecycle |
| --- | --- | --- |
| `nginx` | Vue built with Node 24, served by Nginx 1.28 Alpine | Static frontend, SPA routing, public uploads, FastCGI reverse proxy; HTTP/API health check; restart unless stopped |
| `backend` | PHP 8.4 FPM Bookworm, Laravel production dependencies | Runs as `www-data`; FPM ping health check; restart unless stopped |
| `mysql` | MySQL 8.4 | Dedicated application database/user; SQL health check; restart unless stopped |
| `migrate` | Same backend image | One-shot `php artisan migrate --force --no-interaction`, after MySQL is healthy; successful completion required before backend startup |
| `scheduler` | Same backend image | Existing Laravel `schedule:work` for ZaloPay reconciliation; starts after backend is healthy; restart unless stopped |

Only Nginx publishes a host port. Production defaults: `127.0.0.1:8080 -> nginx:80`, behind an HTTPS terminator on the same host. Verification: `127.0.0.1:8088 -> nginx:80`. PHP-FPM port 9000 and MySQL ports 3306/33060 are internal. The database network is marked internal. Named volumes are `<project>_mysql_data` and `<project>_laravel_storage`; Laravel storage retains uploads, file sessions and the file cache. Nginx mounts storage read-only.

## Environment and configuration

`.env.production.example` contains empty secret fields. Private `.env.production` and `.env.docker-verification` are gitignored and excluded from Docker build contexts, along with local backend/frontend env files, vendor/node_modules, tests and host storage. Never put credentials in `VITE_*` values.

| Setting | Production requirement | Verification value |
| --- | --- | --- |
| `COMPOSE_PROJECT_NAME` | Unique, stable project name | `marketplace-phase14` |
| `PUBLIC_HOST` | Exact public authority, including a non-default port if used; no scheme or path | `localhost:8088` |
| `PUBLIC_SCHEME`, `PUBLIC_PORT` | `https`, `443` behind the HTTPS terminator | `http`, `8088` |
| `PUBLISH_ADDRESS`, `HTTP_PORT` | Default localhost bind, port 8080; keep ingress restricted to the TLS terminator | `127.0.0.1`, `8088` |
| `SESSION_SECURE_COOKIE` | `true` over public HTTPS | `false` for isolated local HTTP only |
| `APP_KEY` | Private Laravel `base64:` key, generated once and retained across releases | Generated random key; never printed in this report |
| `DB_DATABASE`, `DB_USERNAME` | Separate Docker database/user, independent of host Laravel `.env` | `marketplace_phase14`, `marketplace` |
| `DB_PASSWORD`, `MYSQL_ROOT_PASSWORD` | Distinct, private passwords | Independently generated random values |
| `APP_SUBNET`, `NGINX_INTERNAL_IP` | Non-conflicting subnet and static Nginx address inside it; update both together | `172.30.14.0/24`, `172.30.14.10` |
| `ZALOPAY_APP_ID`, `ZALOPAY_KEY1`, `ZALOPAY_KEY2` | Production merchant credentials if enabling online payment | Empty; COD verification only |
| `ZALOPAY_CREATE_URL`, `ZALOPAY_QUERY_URL` | Official production endpoints supplied by Compose defaults | Production defaults, no outbound merchant transaction |
| `ZALOPAY_CALLBACK_URL` | Reachable public HTTPS URL ending `/api/v1/payments/zalopay/callback` | Merchant delivery not tested |
| `ZALOPAY_RETURN_URL` | Optional override; default `<public origin>/payment/result` | Derived from local public origin |

Compose forces `APP_ENV=production`, `APP_DEBUG=false`, encrypted host-only HttpOnly SameSite=Lax sessions, exact CORS origin and Sanctum authority, stderr logging, file cache/sessions and the existing synchronous queue behavior. Existing production restrictions on ZaloPay sandbox endpoints remain enforced.

Laravel trusts only the configured Nginx address. Nginx rejects a different Host authority and replaces client-supplied forwarded host/protocol/port/IP headers. HTTPS is derived from deployment configuration rather than an arbitrary caller header. New regression tests cover trusted HTTPS forwarding, rejection of untrusted forwarding and the default empty trust list. Forwarded prefixes are not trusted.

Startup caches config, routes and Blade views after runtime environment values are available. The image contains no cached host configuration. `APP_KEY` is mandatory; startup refuses to generate or replace it. Releases use rebuilt/recreated containers because OPcache timestamp checks are disabled. Migration execution is confined to the Docker MySQL service and does not run `migrate:fresh`.

## Exact deployment commands

Run from the repository root, with Docker Desktop's Linux engine / Docker Engine running and Compose supporting health/completion dependencies.

Create the private env file once. This PowerShell example generates three independent secrets without printing them; it refuses to overwrite an existing file:

```powershell
$deploymentEnv = '.env.production' # use '.env.docker-verification' for local verification
if (Test-Path -LiteralPath $deploymentEnv) { throw 'Private env already exists; preserve its key and passwords.' }
$settings = Get-Content .env.production.example
$rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
foreach ($name in @('APP_KEY', 'DB_PASSWORD', 'MYSQL_ROOT_PASSWORD')) {
    $buffer = New-Object byte[] 32
    $rng.GetBytes($buffer)
    $secret = [Convert]::ToBase64String($buffer)
    if ($name -eq 'APP_KEY') { $secret = 'base64:' + $secret }
    $settings = $settings -replace ('^' + $name + '=$'), ($name + '=' + $secret)
}
$rng.Dispose()
[IO.File]::WriteAllLines((Join-Path (Get-Location) $deploymentEnv), $settings, [Text.UTF8Encoding]::new($false))
# Edit public authority, TLS/port settings, project/database names, and merchant settings in the private file.
```

Use the production values from the table and configure a generic existing TLS terminator to forward to `http://127.0.0.1:8080`, preserving the exact configured Host authority. The Compose stack itself serves HTTP on its restricted internal ingress; certificates/TLS termination are not created by this phase.

```powershell
docker compose --env-file .env.production config --quiet
docker compose --env-file .env.production build
docker compose --env-file .env.production up -d --wait --wait-timeout 180
docker compose --env-file .env.production ps -a
docker compose --env-file .env.production exec -T nginx nginx -t
docker compose --env-file .env.production exec -T backend php artisan migrate:status --no-interaction
docker compose --env-file .env.production logs --tail 50 nginx backend mysql migrate scheduler
curl.exe --fail https://marketplace.example.com/api/v1/health
```

`config --quiet` validates without dumping secrets. Do not publish the expanded Compose environment or private env file. For code/env updates, rebuild and run `up -d --force-recreate --wait --wait-timeout 180`, retaining the same project name, volumes and APP_KEY. `down` preserves named volumes; do not add `-v` to routine shutdowns.

## Verification results

**PASS** for the local production-like deployment scope. The deployment is running at `http://localhost:8088` with an empty, migrated verification database after fixture cleanup. Execution logs, row-hash snapshots, private env and cookie jars are gitignored.

| Verification | Result |
| --- | --- |
| `docker compose --env-file .env.docker-verification config --quiet` | PASS; required settings validated without disclosing secrets |
| `docker compose --env-file .env.docker-verification build` | PASS; backend production Composer install and frontend `npm ci` / Vite production build |
| `docker compose --env-file .env.docker-verification up -d --wait --wait-timeout 180` | PASS; MySQL, backend and Nginx healthy; scheduler running; migrate exited 0 |
| Nginx `nginx -t`, migration status, runtime user/environment | PASS; all 18 migrations ran in Docker DB; backend is non-root `www-data`, production |
| Relevant backend suite | **43 tests passed, 419 assertions**: DeploymentPhase14Test, AuthTest, HealthTest, SecurityPhase13Test and ZaloPayTest |
| Complete frontend suite | **150 tests passed, 12 files** |
| Frontend lint | PASS |
| Pint | PASS on all four Phase 14 PHP files; `--dirty` unavailable because Git has no commits, so explicit paths were used |
| Real deployed HTTP and persistence verification | **115 assertions passed across 79 HTTP requests**: 99 initial checks, 7 after restart, 9 after recreation/cleanup |
| Development database safety | PASS; all 16 monitored business/migration table counts and SHA256 row hashes exactly match before/after |

HTTP checks cover Vue HTML and built assets at `/`, `/orders` and `/payment/result`; API health; rejection of `.env` and direct PHP access; credentialed exact-origin CORS; rejection of foreign-origin CORS access; session HttpOnly / XSRF-readable / SameSite=Lax cookies; register/login/logout and authenticated `me`; 419 without a CSRF token or same-origin Fetch Metadata; admin versus buyer authorization; shop/category/product creation and moderation; sanitized PNG upload/read and unauthorized upload denial; resistance to spoofed forwarded headers in public image URLs; address/cart/checkout writes; idempotent checkout replay; buyer/seller cross-user order denial; seller delivery transitions and COD paid status; payment reads; rejection of ZaloPay retry/query on a COD order; callback signature rejection without a session or CSRF token.

Persistence was measured using exact count/row-hash snapshots of all 16 monitored Docker tables, the saved uploaded PNG byte hash, the existing buyer cookie jar and the completed paid order:

1. Restarted MySQL, backend, Nginx and scheduler: all compared rows, image bytes and session/order access survived.
2. Recreated the complete Compose stack with `--force-recreate`, retaining its named volumes: the same comparisons passed; migration rerun reported no pending migrations.
3. Removed all nonce-scoped fixture accounts, category, shop, product/image, address, cart, order/payment and audit data using database/relationship/path guards. Docker table hashes returned to the empty migrated baseline; the removed test image returned 404. Early harness retries were also cleaned before rerunning; no fixtures remain.

Operational sessions/cache files and auto-increment sequences in the isolated Docker database can remain/advance; they are not business fixture rows. No development fixture, migration, reset or seed was needed. Only this phase's Compose project was restarted/recreated; unrelated Docker projects were not changed.

### Reproduce the isolated HTTP/persistence checks

Use a fresh private `.env.docker-verification` with the verification values from the environment table and new secrets. The helper deliberately refuses any database other than `marketplace_phase14` on `mysql` in production mode; it also requires an initially empty business-data baseline. Do not point it at a real deployment database. Python 3 uses only its standard library.

```powershell
docker compose --env-file .env.docker-verification config --quiet
docker compose --env-file .env.docker-verification build
docker compose --env-file .env.docker-verification up -d --wait --wait-timeout 180
New-Item -ItemType Directory -Force .runtime | Out-Null
docker compose --env-file .env.docker-verification cp docker/verification/database.php backend:/tmp/phase14-docker-db.php
python docker/verification/http-smoke.py prepare
docker compose --env-file .env.docker-verification restart mysql backend nginx scheduler
# Wait until ps reports mysql/backend/nginx healthy and scheduler running.
docker compose --env-file .env.docker-verification ps -a
python docker/verification/http-smoke.py persistence
docker compose --env-file .env.docker-verification up -d --force-recreate --wait --wait-timeout 180
# Container-local /tmp helpers disappear on recreation, so copy it again.
docker compose --env-file .env.docker-verification cp docker/verification/database.php backend:/tmp/phase14-docker-db.php
python docker/verification/http-smoke.py cleanup
```

If a prepare assertion fails after accounts were created, use `python docker/verification/http-smoke.py abort` to clean only that nonce's fixtures. After confirmed cleanup, repeated isolated verification can reset its test limiter cache with `docker compose --env-file .env.docker-verification exec -T backend php artisan cache:clear --no-interaction`; do not use that as routine production remediation. The final successful run required no change to application business behavior. Initial harness expectations were corrected for CORS fixed-origin responses, lowercase cookie attributes, Laravel 13 Fetch Metadata, and payment request validation.

Other commands executed (from the indicated directory):

```powershell
# backend/
php artisan test --compact tests/Feature/DeploymentPhase14Test.php tests/Feature/AuthTest.php tests/Feature/HealthTest.php tests/Feature/SecurityPhase13Test.php tests/Feature/ZaloPayTest.php
php vendor/bin/pint --test bootstrap/app.php config/trustedproxy.php tests/Feature/DeploymentPhase14Test.php ../docker/verification/database.php --format agent
# frontend/
npm.cmd run test:unit -- --run
npm.cmd run lint
# repository root: production build also ran inside the frontend Dockerfile
python -m py_compile docker/verification/http-smoke.py
```

On this Windows host, `php` above was `C:\Users\axyz5\.config\herd\bin\php84\php.exe`. PHPUnit's existing guard requires the separate `secondhand_marketplace_test` database, never the development database. The three new proxy tests also passed independently after Pint formatting (3 tests, 8 assertions); these reruns are not added to the 43-test suite count.

## Troubleshooting and operational limits

- Docker commands require a running Linux engine. On Windows, start Docker Desktop before building; no existing unrelated Docker projects or volumes need resetting.
- Missing APP_KEY/password fields fail Compose validation. Retain the original APP_KEY or encrypted sessions become unreadable. MySQL's initialization credentials apply to a new volume only; changing an env password does not rotate the existing database user's password.
- For 502/startup failure, inspect `ps -a`, `logs migrate backend mysql`, and `exec -T nginx nginx -t`. The migration service must exit 0. MySQL health includes authentication as the application user. Recreate Nginx together with backend on releases so its FastCGI upstream is resolved again.
- For 419/login failures, check exact PUBLIC_HOST (including port), Sanctum/CORS origin, stable APP_KEY, cookie settings, and `/sanctum/csrf-cookie`. Secure production cookies require browser HTTPS; local verification deliberately disables Secure only in its private HTTP env. Laravel 13 supports browser same-origin Fetch Metadata and falls back to XSRF tokens; token-negative smoke requests omit that header.
- An unexpected Host returns an empty connection (Nginx 444); use the configured authority in requests and health checks. This is intentional host-injection protection.
- For upload 413, check both Nginx 45 MB request limit and PHP's 5 MB per-file / 45 MB POST limits; application image validation remains stricter. For missing images, confirm the project name and storage volume, and inspect `storage/app/public`. If importing an older root-owned volume, deliberately correct ownership of this application's storage only; do not reset database/storage volumes to address permission problems.
- Subnet conflicts require changing APP_SUBNET and NGINX_INTERNAL_IP together. Local verification port 8088 must be free.
- ZaloPay production needs provisioned merchant credentials and a reachable public HTTPS callback/return origin. Live wallet payment, external callback delivery and TLS/certificate termination remain outside local verification. A blank merchant setup does not enable online payment; COD and existing payment authorization/signature regressions are covered.
- This is a single-host, single-backend deployment. File sessions/cache support this topology; multiple backend nodes need shared stores. An extra upstream TLS proxy changes the observed client IP; configure a narrowly trusted Nginx real-IP allowlist for that ingress before relying on per-client IP limits. Caller-supplied forwarding is deliberately ignored by default.
- Scheduler has a restart policy and was checked running, but no custom heartbeat health endpoint was added. There is no cloud-specific deployment, backup automation, rolling release controller, HA/load benchmark or full browser visual test. Production backups must include both named volumes and the private APP_KEY; restoring a backup was not tested.

## Changed files

The repository has no commits and remains untracked. Existing-file changes are identified against the saved PHASE 14 SHA256 baseline, rather than an unavailable HEAD diff.

- `.gitignore`: retain secret env exclusion while allowing the production example.
- `.dockerignore`, `.env.production.example`, `compose.yaml`: build isolation, private runtime configuration and deployment orchestration.
- `backend/Dockerfile`, `backend/.dockerignore`, `backend/docker/entrypoint.sh`, `backend/docker/healthcheck.sh`, `backend/docker/fpm.conf`, `backend/docker/php.ini`: production PHP runtime, caches and health/security settings.
- `frontend/Dockerfile`, `docker/nginx/default.conf.template`: Vue production build and one-origin reverse proxy/static serving.
- `backend/bootstrap/app.php`, `backend/config/trustedproxy.php`: explicitly scoped proxy trust and forwarded HTTPS handling.
- `backend/tests/Feature/DeploymentPhase14Test.php`: three proxy deployment regressions.
- `docker/verification/http-smoke.py`, `docker/verification/database.php`: reproducible HTTP/persistence checks and guarded isolated-fixture cleanup; these are not application endpoints or shipped backend code.
- `docs/phase-14-verification.md`: deployment commands, verification and limitations.

No business services, frontend application source, dependencies or migrations changed.

## Final decision

**PHASE 14: PASS** for the verified single-host Docker/Nginx topology: images built, isolated stack started healthy, 43 backend tests / 419 assertions and 150 frontend tests passed, 115 deployment assertions passed, Pint/lint/build passed, restart/recreation retained uploads/database/sessions, verification fixtures were cleaned and the monitored development database remained unchanged. Public HTTPS termination, live merchant payment/callback E2E, backup restore and multi-node operation remain explicit deployment limitations.

Reference documentation consulted: [Laravel deployment](https://laravel.com/framework/docs/deployment), [Laravel trusted proxies](https://laravel.com/framework/docs/requests), [Compose startup order](https://docs.docker.com/compose/how-tos/startup-order), and [Compose services](https://docs.docker.com/reference/compose-file/services/). Runtime behavior was verified against the installed Laravel source and actual containers.
