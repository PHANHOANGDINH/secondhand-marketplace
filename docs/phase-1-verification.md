# PHASE 1 — Project initialization

Ngày kiểm tra: 2026-09-26 (Asia/Saigon).

## Kết quả

| Kiểm tra | Kết quả |
|---|---|
| Laravel 13.33.0 / PHP 8.4.25 | PASS |
| GET http://127.0.0.1:8000/api/v1/health | HTTP 200, JSON đúng contract |
| CORS localhost:5173 với credentials | PASS; origin không được phép không có allow-origin |
| Vue 3.5.43 / Vite 8.3.1 | HTTP 200 tại http://localhost:5173; Vue module được transform thành công |
| Frontend production build | PASS |
| Laravel Feature + Unit tests | 5 tests, 9 assertions, PASS |
| Frontend Vitest 5.0.2 | 2 tests PASS: API thành công, lỗi mạng và thử lại |
| ESLint / Oxlint | PASS |
| Laravel Pint | PASS |
| composer validate --strict | PASS |
| pdo_mysql | PASS |
| Git repository + ignore rules | PASS |

## Packages chính

Backend: Laravel 13.33.0, Sanctum 4.3.3, PHPUnit 12.5.36.
Frontend: Vue 3.5.43, Vite 8.3.1, Vue Router 5.3.1, Pinia 4.0.3, Axios 1.20.0, Bootstrap 5.3.8, Chart.js 4.5.1, Vitest 5.0.2, ESLint 10.11.0, Prettier 3.9.6.
Phiên bản dependency được khóa trong composer.lock và package-lock.json.

## File quan trọng

- backend/bootstrap/app.php: API prefix api/v1.
- backend/routes/api.php: public GET /health.
- backend/routes/web.php: JSON thông tin API, không dùng Blade cho giao diện chính.
- backend/config/cors.php: origins local cụ thể, cho phép credentials.
- backend/.env.example: MySQL dự kiến, cache/session file, queue sync.
- backend/composer.json: Sanctum; dev script chỉ chạy API server, không gọi Vite trong backend.
- backend/tests/Feature/HealthTest.php: contract JSON và CORS.
- frontend/src/api/axios.js: base URL từ env, credentials, XSRF, timeout.
- frontend/src/main.js: Vue, Pinia, Router và Bootstrap CSS.
- frontend/src/App.vue, layouts/DefaultLayout.vue, views/HomeView.vue: giao diện tối giản và kiểm tra API.
- frontend/src/router/index.js: route trang chủ.
- frontend/src/__tests__/App.spec.js: HTTP spy, status thành công/lỗi/thử lại.
- frontend/vitest.config.js, eslint.config.js: Vitest globals và lint globals cho test.
- frontend/.env.example: URL backend public, không chứa secret.
- .gitignore, README.md: ignore secrets/dependencies và hướng dẫn chạy.

## Lỗi đã xử lý và giới hạn

- PATH của phiên Codex chưa thấy Herd; chạy bằng đường dẫn PHP Herd và PATH tạm thời cho process. Không sửa PATH Windows.
- Npm tải dependencies gặp ECONNRESET ở lần đầu; chạy lại thành công, audit không phát hiện vulnerability tại thời điểm cài.
- Git báo ownership khác nhau giữa sandbox và tài khoản dùng Herd; các command Composer dùng safe.directory chỉ trong environment của process, không sửa global Git config.
- Vitest 4.1.11 từ scaffold và bản stable 5.0.2 đều chuyển bare import vitest thành đường dẫn frontend/vitest trên Windows. Dùng globals: true và vi.spyOn thay cho import API test/hoisted mock; giữ đầy đủ assertions. Không sửa node_modules hay vô hiệu hóa test.
- Đã kiểm tra HTTP, build và component tests bằng jsdom; chưa thực hiện browser E2E (ngoài phạm vi PHASE 1).
- MySQL267 đang chạy; chỉ xác nhận driver, chưa kiểm tra đăng nhập DB, chưa tạo schema, chưa chạy migration.
- Sanctum mới cài package/API support. Cookie authentication, CSRF flow và auth routes thuộc phase sau; cần thống nhất hostname frontend/backend khi thực hiện.
- Không có blocker còn lại cho PHASE 1. Không triển khai PHASE 2.
