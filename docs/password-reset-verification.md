> Historical verification report. Container infrastructure has since been removed. Results below retain their original context; use the [current local setup](deployment-guide.md) and [local verification](xampp-local-verification.md) for present instructions.

# Forgot / reset password verification

## Scope and implementation

Added password recovery to the existing cookie/session Sanctum SPA. Existing register/login/logout/me controllers, auth store, account rules and database schema are unchanged. No new frontend dependency.

- `POST /api/v1/auth/forgot-password`: validated, normalized email; Laravel Password Broker email delivery. Identical HTTP 200/message for known, unknown, deleted, broker-throttled accounts and delivery failures. Invalid input still returns normal 422 validation.
- `POST /api/v1/auth/reset-password`: email, token, password and password_confirmation. Broker validates the hashed, 60-minute token; success hashes the password, rotates the remember token, consumes the reset token and emits Laravel's PasswordReset event. Token consumption is serialized with a database row lock/transaction.
- Existing first-party session/CSRF middleware applies to both endpoints. Forgot requests allow 3/minute per normalized email+IP; reset allows 5/minute. Each also has an independent 20/minute IP cap. Broker generation throttle remains 60 seconds. Limits use the existing cache store.
- Reset responses never contain the token. Invalid/expired/reused/wrong-reference tokens use the same 422 token error. Unsafe role/status fields are prohibited. Password policy matches registration: at least 12 characters, letters and numbers, at most 72 bytes (and 128 input characters).
- Reset does not sign the user in, change account status/role, or invalidate existing sessions. Existing session/auth rules are preserved; remember-token rotation follows Laravel's reset pattern.
- Reset email URLs come from trusted `PASSWORD_RESET_URL` configuration, never request Host or client redirect parameters.
- New `/forgot-password` and `/reset-password` pages reuse PageHeader, DomainField/DomainFeedback and auth styles. Login has a “Quên mật khẩu?” link. Reset clears password fields, strips token/email query parameters after success and offers a Login button. The reset page sets `no-referrer` while mounted.

References: [Laravel Password Broker](https://laravel.com/framework/docs/13.x/passwords), [Mailpit Docker](https://mailpit.axllent.org/docs/install/docker/), [Mailpit health checks](https://mailpit.axllent.org/docs/integration/healthcheck/).

## Local/demo email setup

Mailpit is an optional `demo` Compose profile, with SMTP and web ports bound to IPv4 loopback. It is not started by the default production command.

Add these non-secret settings to a private deployment env file (keep its existing APP_KEY/database settings):

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_SCHEME=smtp
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=no-reply@example.test
# Optional: Compose defaults to PUBLIC_SCHEME://PUBLIC_HOST/reset-password.
PASSWORD_RESET_URL=http://localhost:8088/reset-password
MAILPIT_HTTP_PORT=8025
MAILPIT_SMTP_PORT=1025
```

```powershell
docker compose --env-file .env.docker-verification --profile demo build backend nginx
docker compose --env-file .env.docker-verification --profile demo up -d
docker compose --env-file .env.docker-verification --profile demo ps
```

These commands use the existing isolated verification MySQL database/volume, not the development database. For another installation, substitute its private env filename and separate project/database settings. Standard `migrate --force` exists in Compose for initial setup; no reset/fresh migration is required for this feature.

Open the app at `http://localhost:8088` and Mailpit at `http://localhost:8025`. Use a disposable registered demo email, choose Forgot Password, open its Mailpit message, follow Reset Password, enter/confirm a compliant new password, then sign in. Unknown emails show the same message and produce no email. Wait at least 60 seconds before requesting another link for the same account; repeated attempts can return 429.

For Laravel running outside Docker, use `MAIL_HOST=127.0.0.1`, `MAIL_PORT=1025`, and `PASSWORD_RESET_URL=http://localhost:5173/reset-password` with Mailpit running. Rebuild/recreate Compose services when env changes because entrypoint caches configuration. Clear local Laravel config cache with `php artisan config:clear --no-interaction` if necessary.

## Production SMTP

Configure private `MAIL_MAILER=smtp`, MAIL_HOST, MAIL_PORT, MAIL_SCHEME (`smtp` with STARTTLS or `smtps` where required), MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS and optional PASSWORD_RESET_URL. Compose derives the reset URL from the deployed public origin by default. Keep HTTPS, stable APP_KEY and existing secure-cookie/proxy settings. Never commit mail credentials. The example's `log` fallback is not actual email delivery; production must select/configure a real transport. Do not enable the demo profile on a public deployment.

## Automated verification

Executed on 2026-10-06:

| Check | Result |
| --- | --- |
| Full backend suite | PASS — 210 tests, 2,232 assertions |
| Recovery security tests (included above) | PASS — 15 cases |
| Full frontend suite | PASS — 190 tests in 18 files |
| Recovery frontend tests (included above) | PASS — 11 cases |
| Pint, `--dirty --format agent` | PASS |
| Frontend lint (Oxlint + ESLint) | PASS |
| Frontend production build | PASS |
| Docker backend/frontend build | PASS — both production images built and deployed |
| Actual browser email → reset → new-password login | PASS — 22 deployed checks including fixture cleanup |

Commands from the appropriate backend/frontend directories:

```powershell
php artisan test --compact --filter=PasswordResetTest --no-interaction
php artisan test --compact --no-interaction
php vendor/bin/pint --dirty --format agent
npm.cmd run test:unit -- --run
npm.cmd run lint
npm.cmd run build
```

Backend tests enforce the dedicated `secondhand_marketplace_test` MySQL database before any fixture/migration operations. Coverage includes generic unknown/deleted/throttled emails, sanitized delivery failures, hashed token storage, trusted URL generation, expired/invalid/wrong-email/reused tokens, registration-equivalent password validation, privilege-field rejection, CSRF, rate limits, preserved session identity/account status and successful new-password login.

## Browser/deployment results

Verified against the real deployed app at `http://localhost:8088` and real Mailpit at `http://localhost:8025`, with no API mocks:

- Login link → Forgot Password form → generic success → SMTP delivery → Mailpit inbox/message → actual emailed SPA reset URL → confirmed new password → Login → account/me HTTP 200.
- Old password returns 422; consumed token returns 422; plain HTTP requests without CSRF tokens/fetch-metadata protection return 419 for both recovery endpoints. Laravel 13 also accepts trusted same-origin browser requests via its existing Sec-Fetch-Site protection; this framework behavior is preserved.
- Forgot/reset pages checked at 1440px, 1024px and 390px: no page overflow, readable forms and usable actions. Desktop/mobile screenshots visually inspected. Missing-token route offers a new-link action. No unexpected JavaScript console errors/exceptions.
- The first deployed run exposed persistent no-referrer policy after navigating away from reset. Fixed by explicitly restoring the previous/default policy on unmount; successful new-password login followed by authenticated GET `/api/v1/auth/me` verifies Sanctum continues to recognize the session.
- Backend, Nginx, MySQL and Mailpit are healthy; scheduler is running. Mailpit web/SMTP ports are loopback-only.
- Disposable accounts, reset-token rows and captured fixture emails were cleaned. Final row counts and SHA-256 snapshots match the baseline across all 17 checked Docker tables, including password_reset_tokens. Existing development DB was not used for fixtures or migration commands; tests enforce their separate test DB.
- Verification artifacts and the browser profile are gitignored under `.runtime`; no generated credentials or reset tokens are committed. Dedicated verification browser was closed.

## Remaining limitations

- Production SMTP deliverability, provider credentials, SPF/DKIM/DMARC and real public HTTPS require deployment-specific setup; local Mailpit verifies application mail delivery only.
- Delivery is synchronous under the existing sync queue configuration. Generic responses prevent response-body/status enumeration, but SMTP latency is not a guarantee of constant response timing.
- Reset links necessarily carry a secret query token until success. The page prevents referrer forwarding once mounted and strips the URL on success; operators should keep access logs/private email capture protected and avoid logging query tokens at external proxies.
- Existing sessions remain valid by design. This feature does not introduce global session revocation or change suspended-account login rules.
- File-backed limits retain the existing single-instance scope; multi-instance production would need a shared rate-limit cache.
- Mailpit mail is ephemeral and untrusted demo-only content. Stop the profile when finished; do not publish its unauthenticated UI beyond loopback.
- Expired token housekeeping uses Laravel's existing `auth:clear-resets` command if desired; expiration is enforced regardless of pruning.

## Decision

**PASS — password recovery is ready for local/demo use.** Production email requires the SMTP setup and operational limits listed above.


## Task file inventory

Earlier UI changes in the workspace were preserved. This auth task created/changed these 17 source/config/report files:

- `backend/app/Http/Controllers/Api/V1/Auth/PasswordResetController.php` (new)
- `backend/app/Http/Requests/Auth/ForgotPasswordRequest.php` (new)
- `backend/app/Http/Requests/Auth/ResetPasswordRequest.php` (new)
- `backend/app/Providers/AppServiceProvider.php`
- `backend/config/auth.php`
- `backend/routes/api.php`
- `backend/.env.example`
- `backend/tests/Feature/PasswordResetTest.php` (new)
- `frontend/src/api/auth.api.js`
- `frontend/src/components/AuthForm.vue`
- `frontend/src/router/index.js`
- `frontend/src/views/ForgotPasswordView.vue` (new)
- `frontend/src/views/ResetPasswordView.vue` (new)
- `frontend/src/__tests__/PasswordRecovery.spec.js` (new)
- `compose.yaml`
- `.env.production.example`
- `docs/password-reset-verification.md` (new)

The existing gitignored `.env.docker-verification` was configured with non-secret Mailpit SMTP settings for the local demonstration. Existing private APP_KEY/database credentials were retained. No new npm/composer package or schema migration was added.
