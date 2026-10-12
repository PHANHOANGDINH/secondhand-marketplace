# D3 — Báo cáo vi phạm và quy trình xem xét

Ngày xác minh: **10/10/2026**. Kết luận: **PASS — sẵn sàng demo D3.**

## Phạm vi và schema

Bảng cộng thêm `reports` gồm `id`, `reporter_user_id`, `target_type`, `target_id`, `target_name`, `reason`, `description`, `status`, `handled_by`, `handled_at`, `admin_note`, `created_at`, `updated_at` và generated column `open_slot`.

- `reporter_user_id`/`handled_by` có FK tới users, nullable và nullOnDelete để giữ lịch sử khi tài khoản bị xóa cứng.
- Target là bộ type/id được kiểm tra bởi domain service; không có FK cascade tới Product/Shop/VideoComment. Client không được gửi tên class PHP.
- `target_name` là tóm tắt tối thiểu tại thời điểm gửi, dành cho Admin khi đối tượng không còn tồn tại; không lưu snapshot toàn bộ nội dung.
- `open_slot` bằng 1 cho PENDING/IN_REVIEW, NULL cho trạng thái đóng. Unique `(reporter_user_id, target_type, target_id, open_slot)` đảm bảo chỉ một báo cáo mở, kể cả khi có race.
- Index cho type/id, status/created_at/id, reporter/created_at/id, created_at; FK handled_by có index.
- Migration duy nhất áp dụng vào development: `2026_10_09_170316_create_reports_table.php`. Không chạy `migrate:fresh` hay reset DB phát triển.

## Đối tượng, lý do và visibility

| Giá trị nội bộ | UI |
| --- | --- |
| PRODUCT | Sản phẩm |
| SHOP | Gian hàng |
| VIDEO_COMMENT | Bình luận video |

| Lý do | Nhãn tiếng Việt |
| --- | --- |
| SCAM | Có dấu hiệu lừa đảo |
| COUNTERFEIT | Nghi hàng giả / hàng nhái |
| MISLEADING | Thông tin sai lệch |
| PROHIBITED_CONTENT | Nội dung / sản phẩm bị cấm |
| SPAM | Spam / quảng cáo rác |
| HARASSMENT | Quấy rối |
| INAPPROPRIATE_CONTENT | Nội dung không phù hợp |
| OTHER | Lý do khác |

Tài khoản hoạt động đã đăng nhập (USER/Seller/ADMIN) có thể báo cáo đối tượng công khai, kể cả nội dung của chính mình. Product dùng đúng ProductQuery public visibility; Shop dùng ShopService publicQuery; Comment phải PUBLISHED và thuộc video READY có sản phẩm công khai như luồng bình luận hiện có. Description được trim, tối đa 1.000 ký tự; OTHER bắt buộc có mô tả.

Một báo cáo mở trùng trả 409 với thông báo phù hợp. Sau RESOLVED/DISMISSED có thể gửi báo cáo mới. Submission khóa user/target trong transaction, kiểm tra lại và dùng unique generated-slot ở DB. Rate limit: 5 lần/phút và 30 lần/giờ/tài khoản, cộng middleware domain/SPA hiện có; request sai hoặc trùng cũng tiêu thụ quota.

## Lifecycle và tách biệt moderation

| Trạng thái | Nhãn | Bước tiếp theo |
| --- | --- | --- |
| PENDING | Chờ xử lý | IN_REVIEW hoặc DISMISSED |
| IN_REVIEW | Đang xem xét | RESOLVED hoặc DISMISSED |
| RESOLVED | Đã xử lý | Đóng, không mở lại |
| DISMISSED | Không vi phạm | Đóng, không mở lại |

Admin gửi `expected_status`, trạng thái mới và ghi chú tùy chọn. Server khóa Report, kiểm tra quyền/trạng thái, tự ghi handled_by/handled_at cho lần xử lý gần nhất và audit status transition. Client không được tự đặt handler/thời gian. Retry cùng trạng thái là no-op: không đổi ghi chú, không tạo audit/notification lần nữa. Stale expected_status hoặc bước khác không hợp lệ trả 409.

**Trạng thái báo cáo và trạng thái nội dung là hai khái niệm riêng.** Gửi, bắt đầu xem xét, xử lý hoặc bác bỏ báo cáo không tự ẩn sản phẩm/bình luận, đình chỉ shop, sửa bid/auction, tồn kho, giỏ hàng, đơn hàng, thanh toán hoặc doanh thu.

- Product/Shop: shortcut mở đúng đối tượng trong màn Admin moderation hiện có bằng `inspect` query đã được bổ sung ở UI; API/transition moderation cũ giữ nguyên.
- Video comment: nút Ẩn có xác nhận, gọi đúng API hide bình luận hiện có. Sau khi ẩn, Admin vẫn phải xử lý trạng thái báo cáo riêng.
- Admin detail có context chọn lọc: thông tin nội dung, shop/seller hoặc owner, author/video/product; chỉ ID/tên và trường liên quan, không có mật khẩu/email/token/payment data.

## API

| Endpoint | Quyền / kết quả |
| --- | --- |
| `POST /api/v1/reports` | Authenticated active account; target_type, target_id, reason, description; 201 khi tạo, 409 khi đã có báo cáo mở. |
| `GET /api/v1/my-reports` | Chỉ báo cáo của người gọi; phân trang. |
| `GET /api/v1/my-reports/{report}` | Owner-only, IDOR trả 404. |
| `GET /api/v1/admin/reports` | ADMIN; phân trang, filters. |
| `GET /api/v1/admin/reports/{report}` | ADMIN; context và ghi chú nội bộ. |
| `PATCH /api/v1/admin/reports/{report}/status` | ADMIN; status, expected_status, admin_note (max 1.000 ký tự). |

List params: `page`, `per_page` (default 20, max 100), `status`, `target_type`, `reason`, `sort=newest|oldest`, `from`, `to` (YYYY-MM-DD theo ngày máy chủ; to bao gồm cả ngày). Mặc định mới nhất trước, id làm thứ tự phụ ổn định. Không nhận reporter_user_id từ client để đổi scope.

Các endpoint dùng auth Sanctum, active-account, SPA session/CSRF, domain throttle và EnsureAdmin/Policy ở luồng Admin. Không thay đổi auth cũ. My Reports/notification không trả admin_note, handler identity hoặc admin-only context.

Target context được batch theo ba loại, eager-load các quan hệ cần thiết. Số query không tăng theo mỗi report; có regression query-count. Không tải toàn bộ lịch sử vào header/dashboard.

## UI và notification

- Reusable `ReportDialog` tại Product Detail, Shop và từng bình luận Theater; không đặt control lớn vào ProductCard.
- Nội dung tiếng Việt, reason list kiểm soát, validation/loading/success/duplicate/rate-limit/error, nút disabled khi gửi; không hiển thị raw exception từ API.
- Native dialog đặt trên Theater, chỉ render form khi mở; Tab/Shift+Tab vòng trong dialog, Escape đóng và trả focus về trigger; đóng khi account đổi/unmount.
- Guest được chuyển Login theo intended return hiện có; không gửi report guest.
- `/reports`: menu tài khoản “Báo cáo của tôi”; list/detail, filters, pagination, loading/empty/error tách biệt.
- `/admin/reports`: menu “Báo cáo vi phạm”; filtered count từ pagination, table cuộn nội bộ trên mobile, context, bước tiếp theo hợp lệ, ghi chú nội bộ và moderation shortcuts.
- Request cũ bị bỏ qua sau unmount/đổi tài khoản; dữ liệu Admin không được giữ hiển thị cho tài khoản sau.
- RESOLVED/DISMISSED lưu `REPORT_RESULT` vào database notifications trong cùng transaction; broadcast NotificationCreated sau commit, dedup theo report. Không đưa admin note/description nhạy cảm vào payload.
- Offline notification hiện sau đăng nhập lại; CTA “Xem báo cáo” → `/reports`, với safe-target allowlist. Nút mark-read có accessible label ngay cả khi không có product_name.

## Target biến mất

Reports không bị xóa theo target. Admin có thể xem target đã ẩn/xóa mềm với current status/visibility. Nếu xóa cứng, trả summary lịch sử tối thiểu và available=false, không crash. User nhận tên chung “Nội dung không còn công khai”, không có route/context hoặc tên mới riêng tư của đối tượng.

## Demo seeder

FinalDemoSeeder bổ sung ba fixture có marker `[D3 Demo …]`:

- Buyer A: một Product report PENDING.
- Buyer B: một Shop report IN_REVIEW.
- Buyer C: một Comment report DISMISSED (nếu demo video/comment có sẵn).

Dùng domain service; chỉ tạo nếu target còn hợp lệ, không có marker hoặc báo cáo mở sẵn. Không ghi đè báo cáo người dùng hoặc trạng thái đã xử lý khi chạy lại. Test seeder xác minh hai lần chạy cho cùng snapshot, ba trạng thái đúng và mật khẩu không đổi.

Lệnh tùy chọn để nạp bộ demo trên local: `cd backend; php artisan db:seed --class=FinalDemoSeeder`. Cần DEMO_PASSWORD riêng theo quy định seeder hiện có, không ghi credentials vào repo. **Không chạy seeder này lên DB phát triển trong D3**; fixture demo được kiểm tra trong DB test để giữ nguyên dữ liệu phát triển.

## Kết quả kiểm thử

Skill `testing-best-practices` không có trong môi trường. Theo ngoại lệ người dùng đã phê duyệt cho D3, quy ước và regression tests hiện có của repository được dùng làm tiêu chuẩn; không tạo skill giả hoặc sửa AGENTS.md.

| Kiểm tra | Kết quả cuối |
| --- | --- |
| Backend đầy đủ | **384 tests / 3.814 assertions PASS** |
| Backend D3 + concurrency + FinalDemoSeeder | **18 tests / 219 assertions PASS**, nằm trong tổng backend |
| Frontend đầy đủ | **394 tests / 32 files PASS** |
| Frontend D3 mới | 15 tests, nằm trong tổng frontend |
| Pint | **PASS** |
| Frontend lint (oxlint + ESLint) | **PASS** |
| Production build | **PASS** |
| Git diff whitespace check | **PASS** |

Backend có 13 test báo cáo/security/visibility/validation/filters/query-count và hai test worker cạnh tranh trên MySQL: cùng reporter/target chỉ một 201 và một 409; hai Admin xử lý kết quả khác nhau chỉ một 200/một 409 và một audit/notification. Dùng DB test `secondhand_marketplace_test` với guard theo convention hiện có; không trỏ migration/test reset vào DB phát triển.

Frontend bao phủ ba entry points, reason/description, duplicate/429/validation, không gửi trùng khi pending, intended return, My Reports, Admin filters/actions, hide confirmation, HTML được render như text, account isolation, auth guard, safe notification route, focus trap và notification labels. Cập nhật test navigation từ bảy lên tám mục và cấp router cho test shop moderation.

Các lỗi được tìm và sửa trong verification: form dialog đóng được lazy render để không xen vào composer bình luận; Tab wrap tại cuối modal; CTA notification reused ban đầu ghi “Xem phiên” được đổi riêng cho report result. Test harness jsdom có mock native dialog API; browser thật xác minh lại keyboard/modal.

Lệnh từ các thư mục tương ứng:

```powershell
cd backend
php vendor/bin/phpunit
php vendor/bin/phpunit tests/Feature/ReportTest.php tests/Feature/ReportConcurrencyTest.php tests/Feature/FinalDemoSeederTest.php
php vendor/bin/pint --dirty --format agent

cd ../frontend
npm run test:unit -- --run --maxWorkers=4
npm run lint
npm run build
```

Vite/Rolldown có thông tin build timing trên stderr; exit code 0, production assets được tạo. Không thêm dependency/framework mới.

## Browser và DB preservation

Dùng frontend `http://localhost:5173`, backend `http://127.0.0.1:8000`, CSRF/session và backend thật; tài khoản demo có kiểm soát, không in credentials.

- 1440 / 1024 / 768 / 390 px: dialog nằm trong viewport; My Reports/Admin Reports không có page-level horizontal overflow. Table có scroll nội bộ. Xem ảnh desktop/mobile xác nhận form và context đọc được.
- Guest Product report → Login → đúng Product; USER bị chặn Admin API (403) và Admin route (403 view).
- Gửi Product/Shop/Comment report bằng UI; duplicate có thông báo tiếng Việt.
- Tab không thoát modal, Escape trả focus; đóng dialog không để backdrop tồn tại.
- Admin Product: PENDING → IN_REVIEW → RESOLVED, private note, retry không đổi kết quả.
- Shortcut Product/Shop mở đúng moderation detail; không thay đổi target qua shortcut.
- Tạo một bình luận thử mới, báo cáo rồi Admin xác nhận ẩn bằng API hiện có; trạng thái report không tự đổi. Sau đó DISMISSED qua workflow riêng.
- Admin filter RESOLVED hoạt động; logout/login Buyer vẫn đọc được report result và notification từ DB, không thấy internal note/context.
- CTA notification thật “Xem báo cáo” đã điều hướng đến `/reports` và mark-read cho đúng notification thử.
- Lượt browser cuối không có JavaScript exception.
- Đã dọn đúng các report có marker của lượt thử, notification/audit tương ứng và bình luận thử mới. Không ẩn/xóa bình luận demo cũ.
- So sánh SHA256 theo ID trên **22 bảng business cũ** trước/sau D3: giống hoàn toàn. Bảng D2 wishlists vẫn có **0** bản ghi; bảng reports mới còn **0** sau dọn fixtures.
- Thay đổi DB có chủ ý: migration/table reports mới. Session/cache và các số ID đã cấp trong lúc tạo/dọn fixture là dữ liệu vận hành; không phải thay đổi bản ghi business cũ.
- Các service local của người dùng giữ nguyên; chỉ browser headless do D3 tạo được đóng.

Local startup hiện có: start MySQL, `php artisan serve --host=127.0.0.1 --port=8000`, `npm run dev`; `php artisan reverb:start` để nhận notification realtime. D3 không thêm job/scheduler hoặc yêu cầu Docker.

## Giới hạn còn lại

- Không có snapshot đầy đủ của target bị xóa cứng; Admin còn type/id/tên tối thiểu, report description và lịch sử xử lý.
- Không reopen report đã đóng, bulk moderation, appeal hoặc tự xử phạt; ngoài phạm vi D3.
- Realtime notification là tối ưu delivery, không bảo đảm mọi tab nhận tức thời; DB/refresh là nguồn dữ liệu chuẩn. Browser đã kiểm tra persistence sau đăng nhập lại, không mô phỏng mất mạng broadcast riêng cho D3.
- Danh sách report được tải lại bằng bộ lọc/nút Tải lại; không polling/realtime queue Admin.

## File thuộc D3

So với snapshot ngay trước D3; không gom các thay đổi D1/D2/phase cũ đang có trong worktree:

- `backend/app/Enums/ReportReason.php`
- `backend/app/Enums/ReportStatus.php`
- `backend/app/Enums/ReportTargetType.php`
- `backend/app/Models/Report.php`
- `backend/app/Policies/ReportPolicy.php`
- `backend/app/Providers/AppServiceProvider.php`
- `backend/app/Services/ReportService.php`
- `backend/app/Http/Resources/NotificationResource.php`
- `backend/app/Http/Resources/ReportResource.php`
- `backend/app/Http/Requests/Domain/ModerateReportRequest.php`
- `backend/app/Http/Requests/Domain/ReportListRequest.php`
- `backend/app/Http/Requests/Domain/StoreReportRequest.php`
- `backend/app/Http/Controllers/Api/V1/ReportController.php`
- `backend/routes/api.php`
- `backend/database/factories/ReportFactory.php`
- `backend/database/migrations/2026_10_09_170316_create_reports_table.php`
- `backend/database/seeders/FinalDemoSeeder.php`
- `backend/tests/Feature/FinalDemoSeederTest.php`
- `backend/tests/Feature/ReportConcurrencyTest.php`
- `backend/tests/Feature/ReportTest.php`
- `backend/tests/Support/report-worker.php`
- `frontend/src/api/report.api.js`
- `frontend/src/components/ReportDialog.vue`
- `frontend/src/composables/useNotifications.js`
- `frontend/src/layouts/DefaultLayout.vue`
- `frontend/src/router/index.js`
- `frontend/src/utils/reportPresentation.js`
- `frontend/src/views/AdminProductsView.vue`
- `frontend/src/views/AdminShopsView.vue`
- `frontend/src/views/ProductView.vue`
- `frontend/src/views/ReportsView.vue`
- `frontend/src/views/ShopView.vue`
- `frontend/src/__tests__/AdminUI.spec.js`
- `frontend/src/__tests__/DomainViews.spec.js`
- `frontend/src/__tests__/Reports.spec.js`
- `frontend/src/components/admin/AdminShell.vue`
- `frontend/src/components/home/VideoComments.vue`
- `frontend/src/components/notifications/NotificationList.vue`
- `frontend/src/components/ui/StatusBadge.vue`
- `docs/extension-d3-report-verification.md`
