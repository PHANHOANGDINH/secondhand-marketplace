# Deployment guide

Current verified deployment: **http://localhost:8088**, project `marketplace-phase14`, separate database `marketplace_phase14`. This is production-like infrastructure with local HTTP, not a publicly secured payment deployment. [PHASE 14 verification](phase-14-verification.md) records the initial container build; [final verification](final-verification.md) records the final frontend and regression checks.

## Local Docker setup

Prerequisites: Docker's Linux engine, Compose, free host port 8088 and a non-conflicting private subnet. Run from the repository root. Do not overwrite the existing private `.env.docker-verification`; its APP_KEY/passwords must remain stable for existing volumes/sessions.

For a new checkout, create that file once with this PowerShell block. It writes secrets privately without printing them; no real secret belongs in a committed example:

```powershell
$envFile = '.env.docker-verification'
if (Test-Path -LiteralPath $envFile) { throw 'Preserve the existing private env and key.' }
$settings = Get-Content .env.production.example
$values = @{
    COMPOSE_PROJECT_NAME = 'marketplace-phase14'
    PUBLIC_HOST = 'localhost:8088'
    PUBLIC_SCHEME = 'http'
    PUBLIC_PORT = '8088'
    PUBLISH_ADDRESS = '127.0.0.1'
    HTTP_PORT = '8088'
    SESSION_SECURE_COOKIE = 'false'
    DB_DATABASE = 'marketplace_phase14'
    ZALOPAY_CALLBACK_URL = ''
}
$rng = [Security.Cryptography.RandomNumberGenerator]::Create()
foreach ($name in @('APP_KEY', 'DB_PASSWORD', 'MYSQL_ROOT_PASSWORD')) {
    $buffer = New-Object byte[] 32
    $rng.GetBytes($buffer)
    $secret = [Convert]::ToBase64String($buffer)
    if ($name -eq 'APP_KEY') { $secret = 'base64:' + $secret }
    $values[$name] = $secret
}
$rng.Dispose()
foreach ($name in $values.Keys) {
    $settings = $settings -replace ('^' + $name + '=.*$'), ($name + '=' + $values[$name])
}
[IO.File]::WriteAllLines((Join-Path (Get-Location) $envFile), $settings, [Text.UTF8Encoding]::new($false))
```

Leave merchant credentials blank for the reproducible COD demo. The production image intentionally refuses Sandbox gateway endpoints. All browser traffic must use the exact configured authority `localhost:8088`; 127.0.0.1:8088 is not an interchangeable public Host.

```powershell
docker compose --env-file .env.docker-verification config --quiet
docker compose --env-file .env.docker-verification build
docker compose --env-file .env.docker-verification up -d --wait --wait-timeout 180
docker compose --env-file .env.docker-verification ps -a
docker compose --env-file .env.docker-verification exec -T nginx nginx -t
docker compose --env-file .env.docker-verification exec -T backend php artisan migrate:status --no-interaction
curl.exe --fail http://localhost:8088/api/v1/health
```

Open localhost:8088 in a browser. Fresh databases contain no default accounts or seeded products; use [demo preparation](demo-guide.md). `config --quiet` validates without dumping expanded credentials.

## Topology, health and persistence

| Container | Port / lifecycle |
| --- | --- |
| nginx | Only published port, localhost:8088 -> 80; Vue SPA/static files, API FastCGI and public upload alias; API health check |
| backend | Internal PHP-FPM 9000; non-root www-data; FPM ping check |
| mysql | Internal 3306/33060 on a private database network; application-user SQL health check |
| migrate | Ordinary `migrate --force`, once after MySQL healthy, exits 0; backend waits for success |
| scheduler | Existing `schedule:work` reconciliation job, starts after backend healthy; running status/restart policy, no heartbeat endpoint |

Long-running containers restart unless stopped. Named `marketplace-phase14_mysql_data` retains MySQL; `marketplace-phase14_laravel_storage` retains uploads, file sessions/cache and storage files. Nginx mounts the latter read-only and serves only `storage/app/public`, not private storage. No host DB port is published or used. App/schema changes require backup planning; ordinary migrations can still change the Docker schema, so inspect new migrations before production upgrades.

Backend startup builds config/route/view caches from runtime env, never a copied host cache. OPcache does not check timestamps; rebuild/recreate after source/env changes. Frontend uses relative API URLs; Nginx rewrites SPA URLs to index.html and sends `/api/*`, `/sanctum/csrf-cookie`, `/up` to Laravel's fixed index.php. Dotfiles/direct PHP paths are blocked. Public uploaded PNG/JPEG/WebP remain accessible across restart/recreation.

```powershell
# Release/update, preserving project name/key/volumes:
docker compose --env-file .env.docker-verification build
docker compose --env-file .env.docker-verification up -d --force-recreate --wait --wait-timeout 180
# Ordinary restart; wait for health before testing a write:
docker compose --env-file .env.docker-verification restart mysql backend nginx scheduler
docker compose --env-file .env.docker-verification ps -a
# Stop without deleting named volumes:
docker compose --env-file .env.docker-verification down
```

Do not add `-v`, run migrate:fresh or regenerate APP_KEY to troubleshoot. Volume identity depends on project name. Backups must cover both named volumes plus the private key/env; backup restore/HA are not verified here. MySQL initialization credentials apply only when creating its volume; env changes alone do not rotate an existing user's password.

## Production HTTPS and merchant configuration

Use private `.env.production` from `.env.production.example` with independent credentials and a stable key; the initial secret-generation example is in [PHASE 14](phase-14-verification.md#exact-deployment-commands). Set PUBLIC_HOST to the exact deployed host, PUBLIC_SCHEME=https, PUBLIC_PORT=443 and SESSION_SECURE_COOKIE=true. Default ingress is 127.0.0.1:8080 behind a generic HTTPS terminator on the same host, preserving the configured Host. Certificates/TLS termination are outside Compose.

Compose enforces production/debug=false, encrypted HttpOnly host-only SameSite=Lax sessions, exact FRONTEND_URLS/Sanctum authority and a narrowly trusted Nginx IP. Nginx replaces caller-supplied forwarded host/scheme/port/IP; Laravel trusts only that proxy. To preserve client IP behind an additional ingress, configure a narrowly trusted Nginx real-IP allowlist for that known ingress; do not blindly trust arbitrary X-Forwarded-For. Subnet/IP changes must update APP_SUBNET and NGINX_INTERNAL_IP together.

Enable production ZaloPay only with provisioned production APP_ID/KEY1/KEY2, production create/query URLs, a public HTTPS CALLBACK_URL ending `/api/v1/payments/zalopay/callback`, and an owned RETURN_URL (default public origin + `/payment/result`). Keep keys outside build contexts/source/frontend. Provider callbacks require HTTPS/TLS 1.2+ ([provider FAQ](https://docs.zalopay.vn/vi/docs/faq/)). Sandbox setup belongs to the separate local environment in the demo guide. A paid redirect is not evidence of payment; backend signed callback/query verifies it.

```powershell
docker compose --env-file .env.production config --quiet
docker compose --env-file .env.production build
docker compose --env-file .env.production up -d --wait --wait-timeout 180
curl.exe --fail https://marketplace.example.com/api/v1/health
```

No live production money/callback, TLS certificate or cloud deployment was performed in this phase. File sessions/cache support one backend; multiple nodes need shared stores. API health proves Laravel routing; MySQL has a separate health check, so inspect both.

## Reproduce HTTP/persistence verification

Python 3 standard library is sufficient. Use only an empty, disposable `marketplace_phase14` database; the guarded helper rejects any other database/host/env. This is a test procedure, not a demo seed or an operation to run against real customer data.

```powershell
$env:VERIFICATION_LABEL = 'phase15'
New-Item -ItemType Directory -Force .runtime | Out-Null
docker compose --env-file .env.docker-verification cp docker/verification/database.php backend:/tmp/phase14-docker-db.php
python docker/verification/http-smoke.py prepare
# Optional browser page checks while fixtures/cookie jars exist (see below).
# Capture read-side cart initialization before the persistence baseline:
python docker/verification/http-smoke.py checkpoint
docker compose --env-file .env.docker-verification restart mysql backend nginx scheduler
# Wait until mysql/backend/nginx are healthy and scheduler is running.
docker compose --env-file .env.docker-verification ps -a
python docker/verification/http-smoke.py persistence
docker compose --env-file .env.docker-verification up -d --force-recreate --wait --wait-timeout 180
docker compose --env-file .env.docker-verification cp docker/verification/database.php backend:/tmp/phase14-docker-db.php
python docker/verification/http-smoke.py cleanup
Remove-Item Env:VERIFICATION_LABEL
```

The verifier tests critical API writes/authorization, cookie/CSRF behavior, exact-origin CORS, catalog/upload/checkout/COD/reviews/dashboards and count/hash/image/session persistence. Browser navigation submits no forms, but the existing cart GET initializes an empty cart for a user without one; `checkpoint` verifies ownership, an unchanged buyer cart and unchanged other tables before saving the pre-restart snapshot. Run checkpoint once after the optional initial browser pass. It removes only guarded nonce-scoped fixtures and confirms the empty business baseline after cleanup. If preparation fails after creating accounts, use the same label and `python docker/verification/http-smoke.py abort`. After verified cleanup, a repeat isolated run can clear its test rate-limit cache with `exec -T backend php artisan cache:clear --no-interaction`; that is not routine production remediation. Private cookie jars/results are gitignored under `.runtime/`.

Optional Windows headless Edge verification requires installed Edge and Node 24 (or a Node version with built-in WebSocket). Run between prepare and checkpoint, using a fresh isolated profile; the script reads only prepared fixture cookies, checks 32 deployed pages and closes its isolated browser:

```powershell
$profilePath = Join-Path (Get-Location) '.runtime\phase15-browser-profile'
Start-Process -FilePath 'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe' -WindowStyle Hidden -ArgumentList @('--headless=new','--remote-debugging-address=127.0.0.1','--remote-debugging-port=9223',('--user-data-dir=' + $profilePath),'--no-first-run','--disable-background-networking','about:blank')
# Wait for localhost:9223/json/version to respond, then:
node docker/verification/browser-smoke.mjs
```

## Troubleshooting

- Engine unavailable: start Docker Desktop's Linux engine. Leave unrelated projects/volumes alone.
- Build/config failure: fill required private key/password/public-host settings; inspect logs without publishing secrets. Images exclude all local env/vendor/storage files.
- 502/unhealthy startup: `ps -a`, `logs --tail 50 migrate backend mysql nginx scheduler`, `nginx -t`; migration must exit 0 and MySQL application-user health must pass.
- Blank/outdated UI: rebuild frontend image and recreate Nginx; local Vite changes do not update a deployed image.
- 419/login failure: exact origin/authority, stable APP_KEY, `/sanctum/csrf-cookie`, credentials/XSRF and Secure-cookie settings; HTTP verification uses Secure=false only locally.
- Empty connection: Nginx 444 rejects wrong Host. Use localhost:8088, not 127.0.0.1, for this stack.
- Upload 413/missing image: Nginx 45 MB request cap, PHP 5 MB/file and 45 MB POST, stricter image validator; inspect the correct storage volume/public directory. Correct imported storage ownership deliberately rather than deleting volumes.
- Gateway unavailable: distinguish absent merchant setup, production/Sandbox mismatch and actual provider failure. Keep COD available; never fake PAID or weaken callback verification.
- Port/subnet conflict: choose a free HTTP_PORT and adjust PUBLIC_HOST/PORT consistently; adjust subnet/static Nginx IP together.
