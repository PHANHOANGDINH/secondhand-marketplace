# PHASE 4 — Authentication end-to-end

Ngày nghiệm thu: 26/09/2026. Project: `D:\CN\Web\secondhand-marketplace`.

**PHASE 4 COMPLETED.** Register, login, me và logout đã hoạt động bằng Sanctum SPA session/cookie/CSRF. Chưa bắt đầu PHASE 5.

## Kết quả nghiệm thu

| Hạng mục | Kết quả |
|---|---|
| Sanctum SPA configuration | PASS — Laravel 13.33.0, Sanctum 4.3.3, statefulApi() |
| Frontend | PASS — http://localhost:5173; Vite thực tế đã nạp API URL localhost |
| Backend | PASS — http://localhost:8000 |
| MySQL | Giữ DB_HOST=127.0.0.1, port3306; không reset credential/schema/data |
| Register | PASS — 201, USER/ACTIVE, hash password, đăng nhập và regenerate session |
| Login | PASS — 200, web guard, regenerate session; optional remember |
| Me | PASS — 200 đúng current user; guest401; suspended403 |
| Logout | PASS — 204, logout web guard, invalidate session, regenerate CSRF token |
| CSRF | PASS — cookie bootstrap204; mutation thiếu/sai token419 trong HTTP integration |
| CORS credentials | PASS — allowlist localhost:5173, credentials=true, preflight204 |
| Session | PASS — file driver local, host-only, HttpOnly, SameSite=lax, Secure=false cho HTTP |
| Login rate limit | PASS — 5/phút/email normalized+IP; vượt quota429 với Retry-After |
| Register rate limit | PASS — 5/giờ/IP |
| Backend regression | PASS — **41 tests, 267 assertions**, gồm PHASE 1–4 |
| Frontend unit tests | PASS — **16 tests trong 3 files** |
| Frontend lint | PASS — oxlint và ESLint |
| Frontend production build | PASS — Vite8.3.1, 92 modules, frontend/dist |
| PHP format | PASS — Pint --dirty --format agent |
| Real cookie flow | PASS — cả register và login; cookie jar xuyên suốt; không Bearer |
| Health API | PASS — HTTP200, JSON contract PHASE 1 giữ nguyên |
| Git/secrets | PASS — env files ignored, source candidates không chứa DB password/APP_KEY thật |

## Kiến trúc và cấu hình

Vue → Axios withCredentials/withXSRFToken → Sanctum CSRF → web session cookie → Laravel authentication → MySQL users. Pinia chỉ giữ user trong bộ nhớ; reload gọi me để khôi phục. Không token endpoint, createToken, HasApiTokens cho SPA hoặc persisted browser storage.

Đã đối chiếu API với code vendor đang cài và [Laravel authentication 13.x](https://laravel.com/framework/docs/13.x/authentication), [CSRF 13.x](https://laravel.com/framework/docs/13.x/csrf). Bootstrap dùng `$middleware->statefulApi()`, không thêm Http/Kernel.php. Sanctum config dùng PreventRequestForgery của Laravel 13 thay alias cũ ValidateCsrfToken; không cấu hình CSRF exceptions. Laravel 13 có kiểm tra request origin bên cạnh token; SPA hai port là cross-origin same-site, flow kiểm thử dùng Sec-Fetch-Site=same-site và X-XSRF-TOKEN.

SANCTUM_STATEFUL_DOMAINS chỉ localhost:5173. FRONTEND_URLS chỉ http://localhost:5173. Session file hiện hữu phù hợp local nên giữ nguyên, không đổi sang database hoặc thêm migration. SESSION_DOMAIN=null tạo host-only cookie, SESSION_SECURE_COOKIE=false, SameSite=lax, HttpOnly session. Test environment vẫn dùng array session và MySQL test riêng.

CORS paths: api/* và sanctum/csrf-cookie; supports_credentials=true, không wildcard origin. Với một configured origin, thư viện có thể gửi cố định Access-Control-Allow-Origin=localhost:5173 cho request origin khác; browser vẫn từ chối vì không khớp origin. Các negative assertions kiểm tra header không wildcard và không phản chiếu origin không tin cậy; không giả định header phải vắng mặt.

## Endpoints và backend

| Method/path | Bảo vệ | Thành công |
|---|---|---|
| GET /sanctum/csrf-cookie | Framework web/CSRF bootstrap | 204 và cookies |
| POST /api/v1/auth/register | First-party stateful session, CSRF, throttle register, RegisterRequest | 201 message + data |
| POST /api/v1/auth/login | First-party stateful session, CSRF, throttle login, LoginRequest | 200 data |
| GET /api/v1/auth/me | auth:sanctum, EnsureActiveAccount | 200 data |
| POST /api/v1/auth/logout | auth:sanctum, first-party session, CSRF; suspended vẫn được logout | 204 |

AuthController gồm bốn thao tác, dùng web guard chuẩn Laravel. Register allowlist name/email/password, gán UserRole::USER/UserStatus::ACTIVE ở server, sử dụng hashed cast hiện có; không hash hai lần. Unique email trong database vẫn bảo vệ race đăng ký, trả validation email khi trùng.

RegisterRequest: name 2–100, email valid <=254 trim/lowercase unique kể cả soft-deleted, password tối thiểu12 ký tự có chữ và số, confirmation khớp. Giới hạn 72 byte bảo vệ đầu vào bcrypt, làm rõ mức128 ký tự dự kiến ở thiết kế cũ. role/status/email_verified_at/remember_token prohibited; input ngoài allowlist không được persist.

LoginRequest: email/password required, remember boolean optional. Sai password, email không tồn tại, SUSPENDED và soft-deleted dùng chung lỗi422/errors.email. Không thêm enum/status mới hoặc email verification blocking. Login/register khi đã có session với payload hợp lệ trả409, tránh tự chuyển tài khoản.

AuthUserResource chỉ trả id string, name, email, phone, avatar_path, role/status enum values, email_verified_at, created_at. Không password, remember_token, deleted_at. EnsureSpaSession trả419 cho auth mutation không được Sanctum nhận diện first-party; EnsureActiveAccount trả403 cho me của session suspended. Logout vẫn dùng web guard, không xóa personal access tokens.

RateLimiter được cấu hình trong AppServiceProvider, login key hash email lowercase/trim + IP, register key IP; gồm cả request validation thất bại. Tests dùng array cache để quota độc lập giữa các test applications.

## Vue SPA

- auth.api.js: getCsrfCookie/register/login/logout/me. Lấy CSRF trước login/register/logout; Axios tự quản lý XSRF header. Không đọc document.cookie thủ công trong ứng dụng.
- auth store: user, initialized/loading, initializationError, sessionBlocked; isAuthenticated/isAdmin; initialize/fetchUser/register/login/logout. Concurrent initialization chỉ gọi me một lần.
- 401 từ me là guest; không interceptor gọi me/logout lặp. 403 đánh dấu sessionBlocked, giữ đường logout rõ ràng. Logout lỗi network/419 giữ trạng thái để người dùng thử lại, thành công hoặc401 mới clear user.
- 419 hiển thị yêu cầu thử lại, lần submit sau lấy cookie mới; không automatic replay vô hạn. 429/network/422 có message; validation errors hiển thị theo field.
- Router đợi initialize: guest /account → /login; authenticated /login hoặc /register → /. Register/login thành công → /account.
- LoginView/RegisterView dùng AuthForm Bootstrap, label/autocomplete, loading/disabled submit, validation feedback, remember checkbox cho login. Password fields được clear sau submit thành công hoặc lỗi. AccountView hiển thị name/email/role/status và logout; DefaultLayout có navigation phù hợp guest/session.

## Bằng chứng kiểm thử

Backend AuthTest bổ sung 16 tests trên secondhand_marketplace_test: registration/hash/session/normalization; chống role/status escalation; invalid và duplicate email, byte limit password; valid login/remember/session rotation; generic invalid/suspended/deleted error; private routes401; safe resource; logout; suspended logout; rate limits; cookie/CORS attributes; CSRF middleware và non-stateful rejection. Toàn bộ suite 41 tests/267 assertions PASS, gồm 25 tests PHASE 1–3.

Feature tests thông thường của Laravel bỏ qua CSRF. Có một test bind middleware để bỏ riêng bypass trong test và chứng minh request thiếu token419. Đây vẫn là framework test, không được coi là browser end-to-end.

Frontend tests mock HTTP, không cần backend thật: CSRF-before-login/register, successful/failed login state, me restore/401, initialize concurrency, logout thành công/lỗi, suspended logout, router guards, form server validation, registration redirect và clearing password. Tổng16 tests PASS; lint/build PASS.

### HTTP cookie integration thực tế

Đã chạy qua server local đang phục vụ ứng dụng, dùng cookie jar in-memory, Origin/Referer http://localhost:5173, không Authorization/Bearer:

1. Health và frontend localhost200; module Axios do Vite phục vụ trỏ đúng localhost:8000.
2. GET /sanctum/csrf-cookie204; có XSRF và host-only HttpOnly session; credential CORS đúng.
3. Login mutation thiếu hoặc sai XSRF header →419.
4. Register fixture với mật khẩu ngẫu nhiên trong memory →201 USER; me200 đúng user; logout204; me401.
5. Bootstrap CSRF lại → login200 → me200 với resource an toàn.
6. Cookie jar anonymous trước login không được nâng quyền sau login: replay me401.
7. Logout204 → me401; replay cookie jar authenticated cũ cũng401.
8. CORS preflight204; origin không tin cậy không được cấp quyền đọc theo CORS.
9. Kiểm tra DB bằng Hash::check: mật khẩu đã hash, role USER/status ACTIVE; personal_access_tokens vẫn0. Chỉ xóa user probe vừa tạo, kiểm tra đúng email/ID và không có business relations trước cleanup.

Không lưu/in cookie values hay plaintext credential của integration probe. Cookie jar/password chỉ ở memory; probe user đã cleanup. Đây là HTTP integration tương thích luồng browser cookie, không phải automation click/type của browser engine.

## File thay đổi

Backend mới: `app/Http/Controllers/Api/V1/Auth/AuthController.php`, `app/Http/Requests/Auth/{RegisterRequest,LoginRequest}.php`, `app/Http/Resources/AuthUserResource.php`, `app/Http/Middleware/{EnsureActiveAccount,EnsureSpaSession}.php`, `tests/Feature/AuthTest.php`.

Backend cập nhật: bootstrap/app.php, config/sanctum.php, config/cors.php, routes/api.php, app/Providers/AppServiceProvider.php, tests/Feature/HealthTest.php; .env/.env.testing local và các env examples chỉnh URL/cookie config. Không đổi migrations/User model/schema.

Frontend mới: src/api/auth.api.js, src/stores/auth.js, src/router/authGuard.js, src/components/AuthForm.vue, src/components/LogoutButton.vue, src/views/LoginView.vue, RegisterView.vue, AccountView.vue, src/__tests__/auth.spec.js, AuthForm.spec.js. Cập nhật router/index.js, layouts/DefaultLayout.vue, .env và .env.example. Axios instance hiện có đã đúng withCredentials/withXSRFToken nên giữ nguyên.

Docs: README.md, api-design.md, permissions.md và báo cáo này. Không thêm dependencies. Source project vẫn untracked từ baseline trước; chưa stage/commit. backend/.env, backend/.env.testing, frontend/.env bị ignore; examples và lockfiles là source công khai không chứa credential thật.

## Giới hạn

Chỉ auth core local. Chưa password reset, email verification, social login, 2FA, profile edit, business CRUD, marketplace policies, checkout/payment, dashboards, chat/auction/Reverb hoặc Docker. Production cần cấu hình HTTPS/Secure cookie, domains/session storage và vận hành riêng. File session hiện tại phù hợp một máy local.

Các HTTP/browser policy checks ở trên không thay thế kiểm thử UI trên nhiều browser trước production. PHASE 5 chưa bắt đầu.
