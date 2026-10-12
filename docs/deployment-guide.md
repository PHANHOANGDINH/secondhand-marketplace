# Local development with XAMPP

The current runtime is Vue/Vite at **http://localhost:5173**, Laravel Artisan at **http://127.0.0.1:8000**, and a MySQL-compatible server at **127.0.0.1:3306** using **secondhand_marketplace**. See [README startup commands](../README.md#local-development-with-xampp). This guide replaces the previous container setup; historical reports retain their original results.

## Startup and configuration

1. Start XAMPP MySQL. Confirm port 3306 is free or already owned by the intended database server. Preserve an existing database before switching services.
2. In `backend`, run `composer install`; create `.env` from `.env.example` only if absent. Configure DB username/password privately. Generate APP_KEY only when initially empty. Run `php artisan config:clear`, `php artisan migrate:status`, and `php artisan storage:link`. Warm mail templates with `php artisan view:cache` to avoid slow first rendering on Windows. Apply ordinary `php artisan migrate` only for an initial database or reviewed pending migrations with a backup.
3. Run `php artisan serve --host=127.0.0.1 --port=8000`.
4. In `frontend`, run `npm install`; create `.env` from `.env.example` only if absent. Run `npm run dev`.
5. Open http://localhost:5173. Check both http://127.0.0.1:8000/api/v1/health and http://localhost:5173/api/v1/health.

Use PHP 8.4 for the installed Composer lockfile; the installed XAMPP PHP 8.0.30 cannot run this Laravel version. A separate PHP CLI alongside XAMPP MySQL is supported. Check `php --version`, `php --ini`, `composer check-platform-reqs`, and `node --version` before starting. Do not alter application dependencies merely to accommodate an old bundled PHP.

## Sanctum / cookies

`VITE_API_BASE_URL=/` and `VITE_BACKEND_PROXY_TARGET=http://127.0.0.1:8000` route browser `/api` and `/sanctum` through Vite. Keep `FRONTEND_URLS=http://localhost:5173`, `SANCTUM_STATEFUL_DOMAINS=localhost:5173`, `SESSION_DOMAIN=null`, `SESSION_DRIVER=file`, `SESSION_SAME_SITE=lax`, and local `SESSION_SECURE_COOKIE=false`. Session cookies remain HttpOnly; the XSRF cookie must remain readable for Axios. Existing credential/XSRF handling is unchanged. Direct cross-host API configuration is unsuitable for this local cookie-auth pair. CORS is still an exact origin allowlist with credentials, not a wildcard.

## Email, storage, scheduled payment queries

Default `MAIL_MAILER=log` writes reset email to `backend/storage/logs/laravel.log`. Follow its localhost:5173 reset link privately. SMTP remains configurable in backend env; no container mail service is needed. Never publish logs/reset links.

`php artisan storage:link` links `backend/public/storage` to `backend/storage/app/public`. Product images are PNG/JPEG/WebP under the existing validation rules. Configure CLI upload limits using `backend/dev/uploads.ini` values; optional `./serve.ps1` applies them. Preserve uploads and the stable APP_KEY across restarts. Existing file sessions live in `storage/framework/sessions`.

For Sandbox, keep merchant keys private and use the V2 Sandbox URLs from the env example. Set `ZALOPAY_RETURN_URL=http://localhost:5173/payment/result`; provider callbacks need a public HTTPS tunnel to port 8000 and the `/api/v1/payments/zalopay/callback` path. Run `php artisan schedule:work` in a separate terminal to enable scheduled reconciliation. Do not fabricate PAID state or weaken verification for demos. Production keys/SMTP/HTTPS are separately provisioned.

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

### SMTP verification — 2026-10-07

| Check | Result |
| --- | --- |
| Gmail preset resolved by installed Laravel/Symfony transport | PASS; automatic STARTTLS enabled |
| smtp.gmail.com:587 connectivity and verified TLS negotiation | PASS; no authentication or email attempted |
| Laravel config cache cleared | PASS |
| Backend auth/password-reset regression | 31 tests / 196 assertions PASS |
| Frontend auth/password-reset regression | 30 tests / 3 files PASS |
| Generic unknown-email response, invalid/expired/reset-reuse cases | PASS in backend regression; not a real SMTP delivery test |
| Real authenticated SMTP reset email / inbox receipt / emailed-link browser reset | NOT RUN — private sender credentials and a controlled recipient have not been provided |

Only `backend/.env.example`, README and this local setup guide were changed for SMTP setup. Private backend/.env still uses the working log fallback; no auth logic, broker, route, token rule, frontend behavior or development account/password was changed. Tests use the separate dedicated test database. The reset URL remains http://localhost:5173/reset-password.

**Configuration/regression: PASS. Full real SMTP end-to-end: FAIL / verification pending.** SMTP authentication and inbox receipt cannot be inferred from connectivity or a generic forgot-password response. Configure the App Password privately in backend/.env and identify a controlled recipient before attempting the requested real-email verification; never send the App Password through chat or commit it.

## Tests and demo data

See [README tests](../README.md#tests) for the full backend/frontend commands. PHPUnit refuses the development database and uses `secondhand_marketplace_test`. The current development database's migrations are already applied; no migration or seeding was run against it during this switch.

On an explicitly selected safe demo database, run `php artisan db:seed --class=DemoMarketplaceSeeder`. It adds only a deficit below 20 visible products and is safe to re-run. Use the [demo script](demo-guide.md); historical data-pack counts describe the former isolated deployment, not this machine's development inventory. Never reset the existing DB to prepare a presentation.

## Troubleshooting

- Port 3306 occupied: inspect `Get-NetTCPConnection -LocalPort 3306 -State Listen` and XAMPP's log; do not terminate an unrelated database or overwrite its data directory. This machine currently uses the existing MySQL267 service, so exact XAMPP MariaDB validation is pending.
- PHP platform errors: use the compatible CLI on PATH; check Composer platform requirements and required extensions. Do not use the installed XAMPP PHP 8.0.30.
- 8000/5173 unavailable: stop your previously started process or resolve the conflict. Vite uses a strict 5173 port to keep origins consistent.
- Proxy connection refused: start Laravel first; check direct health and `VITE_BACKEND_PROXY_TARGET`.
- 419/login failure: use exactly localhost:5173, same-origin API base `/`, obtain `/sanctum/csrf-cookie`, retain cookies, and keep a stable APP_KEY. Clear config cache/restart after env changes. Do not set SameSite=None or disable CSRF to solve hostname mismatch.
- Reset email timeout on first use: run `php artisan view:cache`; this machine initially took 26 seconds to render mail, exceeding the unchanged 10-second Axios timeout. Warm rendering was verified by the browser flow.
- Reset email absent: check MAIL_MAILER, the single log file and notification errors; responses deliberately stay generic even for unknown accounts.
- Image missing: verify the public storage link/target and file existence without deleting existing uploads. Windows may require Developer Mode/admin rights to create a symbolic link; an existing correct junction is valid.
- Upload rejected: distinguish PHP request/file caps from unchanged application validation, MIME, dimensions and ownership requirements.
- Stale frontend: restart Vite after env changes; run lint/tests/build after source changes.
- ZaloPay unavailable: verify Sandbox credentials and environment separately; localhost callbacks cannot arrive from the provider. COD remains the offline demo path.

[Local verification](xampp-local-verification.md) records current results and remaining blockers. Historical container volumes remain archived; project configuration and current startup commands no longer use them.

## Local auction lifecycle and notification workers (A5)

Apply reviewed additive migrations with `php artisan migrate` (preserve existing data; never reset the development DB). Configure the existing backend Reverb credentials privately, match only the public key/host/port in frontend env, then restart Vite after frontend env changes. Start MySQL first. Use separate terminals:

```powershell
cd backend
php artisan config:clear
php artisan serve --host=127.0.0.1 --port=8000
```

```powershell
cd frontend
npm run dev
```

```powershell
cd backend
php artisan reverb:start
```

```powershell
cd backend
php artisan schedule:work
```

Open http://localhost:5173. The existing minute schedule invokes `auctions:sync --limit=100` without overlapping and also reconciles ZaloPay. For a reviewed safe demo database, `php artisan auctions:sync --limit=100` runs one lifecycle pass manually. It starts due scheduled auctions, finalizes expired auctions and sends due winner reminders; notification writes join those transactions, broadcasts run after commit. There is no queue-worker requirement for this synchronous broadcast approach.

Backend env: `AUCTION_WINNER_CHECKOUT_HOURS=24`, `AUCTION_CHECKOUT_REMINDER_MINUTES=60` (0 disables reminders). Run `config:clear` and restart scheduler/backend after changing configuration. A reminder that commits before checkout may remain in history; one that acquires the Auction lock after Order commit is suppressed. Expiry derives from the stored server deadline and does not create an Order or change stock.

If realtime is unavailable, use the bell/page refresh or return/login to load persisted notifications. Verify the notification API session first, then Reverb origin/public key, port 8080 connectivity and private channel authorization. Do not disable CSRF/channel ownership. If expiry reminders are absent, check scheduler, threshold, expiry and existing Order; repeated passes intentionally send at most one. Notifications created before A5 deployment are not backfilled. Delivery latency and retention at high volume are not production-validated. See [A5 report](auction-a5-notifications-verification.md).
