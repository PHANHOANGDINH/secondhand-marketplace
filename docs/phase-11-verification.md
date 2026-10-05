# PHASE 11 — Admin Dashboard, Users, Reviews, Order Overview

Ngày kiểm chứng: 2026-09-30. **PHASE 11 COMPLETED**. Không bắt đầu PHASE 12.

| Kiểm tra | Kết quả |
|---|---|
| Backend full `php artisan test --compact` | **167 tests / 1.780 assertions PASS**, thêm18 tests /195 assertions |
| Frontend full `npm.cmd run test:unit -- --run --maxWorkers=1` | **134 tests /11 files PASS**, thêm15 tests |
| Pint full + `--dirty --format agent` | PASS |
| Frontend lint / production build | PASS; Vite8.3.1,150 modules |
| Real HTTP cookie/CSRF | **75 checks PASS** |
| Health | GET http://localhost:8000/api/v1/health →200/status=ok |
| Fixture cleanup / development integrity | PASS: count + SHA-256 rows của16 bảng trước/sau khớp |

## Endpoints

Prefix `/api/v1/admin`, chỉ ADMIN ACTIVE qua Sanctum SPA session, EnsureAdmin và policy:

| API | Contract |
|---|---|
| GET /dashboard | All-time metrics và5 recent orders/users |
| GET /users | search tên/email, status, role, page/per_page |
| GET /users/{user} | Safe account profile; archived read-only |
| PATCH /users/{user}/status | expected_status,status,reason; ACTIVE↔SUSPENDED |
| GET /reviews | status, product ID, rating, page/per_page |
| GET /reviews/{review} | Review + author/Product summary, kể cả HIDDEN/archived Product |
| PATCH /reviews/{review}/status | expected_status,status,reason; PUBLISHED↔HIDDEN |
| GET /orders | overall_status, shop_order_status, buyer ID, from/to, page/per_page |
| GET /orders/{order} | Read-only ShopOrder/OrderItem snapshots |

Guest401, USER403. Pagination20/max100; newest ID trước; invalid/unknown fields422. Không admin role bypass vào Buyer/Seller endpoints.

## Business behavior

Dashboard: total/active Users; total/active/pending Shops và Products không soft-delete. Active products là status ACTIVE của chính record, không phải public-visibility count. Total Orders giữ lịch sử; completed/cancelled Orders phải có mọi ShopOrder cùng trạng thái tương ứng. **Revenue = SQL SUM(shop_orders.subtotal), chỉ COMPLETED**, không shipping hoặc Payment attempts; phần hoàn tất của Order chưa hoàn tất toàn bộ vẫn đóng góp. Thống kê toàn thời gian, không date filter; không loại legacy COMPLETED có completed_at null. Không gọi số này là commission revenue. DECIMAL string2 số lẻ, SQL COUNT/SUM; bounded recent records eager load, không N+1 hoặc load mọi Order để sum PHP. Recent users không email/credential; recent orders không shipping PII/Payments.

User status: required reason≤1000, expected_status; same/stale409, self/archived403. Actor và target account khóa theo ID, recheck Admin ACTIVE dưới lock; bảo vệ self/last-active-admin và actor đã bị suspend nhưng còn object cũ. Chỉ đổi status, không email/password/role; không DELETE/restore archive API. `user.status_changed` audit cùng transaction; lỗi audit rollback. Login mới của suspended User thất bại422 generic credentials; session hiện có bị403 ở protected access, logout vẫn204. Order/Review/history giữ nguyên; không cascade Shop/Order statuses.

Review: chỉ hide/unhide, không sửa rating/comment/reference. Actor/Review lock, optimistic status và audit `review.moderated` cùng transaction. Public list/average_rating/review_count dùng predicate có sẵn, tự loại HIDDEN; unhide vẫn kiểm purchase hợp lệ. Reason nội bộ không ra public, Buyer sửa HIDDEN không republish.

Orders: mặc định mọi thời gian. overall status tái sử dụng OrderSummary; shop_order_status dùng EXISTS nên không duplicate Order nhiều Shop. Date range theo Order.created_at Việt Nam, from/to cùng có,2000-01-01..9998-12-31, from≤to,≤366 ngày inclusive. Detail giữ snapshot tên/giá/quantity/subtotal/image dù User/Shop/Product soft-delete. AdminOrderResource không Buyer/Seller action, shipping PII hoặc Payment attempts; không thay workflow/cancel/refund.

## UI và bằng chứng

Routes `/admin/dashboard`, `/admin/users`, `/admin/reviews`, `/admin/orders` dùng guard/navigation hiện có. Dashboard có cards và recent lists; không thêm chart khi chưa có time-series contract. User/Review pages dùng chung AdminModerationPanel cho filter/pagination/detail/reason/status action; không credential hoặc content editor. Order detail panel chỉ đọc, mở từ recent-order link. Vue interpolation escape nội dung Review; error/empty/validation/conflict và reload hiển thị rõ.

Backend18 tests cover auth toàn bộ routes, current counts và overall Order states, multi-shop/Payments không double count, decimal0.10 và large SUM, recent limit/privacy, User filters/pagination/archived, transitions/self/stale actor/injection, suspend/login/session/history, audit failure rollback, review hide/unhide/public aggregate, Review history, order filters/date boundaries/snapshots/read-only, N+1 query bounds. Full regression giữ PHASE1–10, gồm checkout stock concurrency/rollback/COD và Seller Revenue.

Frontend15 tests cover dashboard metrics/revenue, empty/error/retry, User filters/pagination/status/self/archived/conflict/validation, Review filters/hide/unhide/escaped comment, Order filter/detail/snapshot/deep link/404, all4 guards, non-admin/in-flight logout và API CSRF payload. UI kiểm bằng Vitest và production build, không tuyên bố manual browser test.

HTTP thật bằng Buyer/Seller/Admin cookie jars: guest401/USER403, CSRF419, self-suspend403, role/content injection422, stale409; suspend chặn session và login mới, restore login được; Review hide/unhide cập nhật public rating ngay; Order overview2 Shop, đúng snapshots và PATCH405. Fixture revenue tăng chính xác1450000.10, không cộng shipping/Payment attempts;4 audit entries hợp lệ. Cleanup nonce-scoped với guard references, count/hash16 bảng khớp baseline. Không xóa seed records, không tạo/xóa ảnh thật; auto-increment có thể tăng, không reset sequence.

## Source và limitations

Tạo4 Admin Controllers,5 Requests,4 Resources, UserPolicy, AdminDashboardService/AdminUserService/AdminOrderQuery, AdminPhase11Test; frontend admin.api.js,4 views, AdminModerationPanel, AdminPhase11.spec.js. Mở rộng ReviewService/ReviewPolicy/OrderPolicy; nối routes/nav và cập nhật API/rules/permissions/README. Không duplicate class/route, không migration/dependency mới; schema/models/enums/factories/seeders/auth core nguyên vẹn. Test guard vẫn chỉ cho configured/actual MySQL `secondhand_marketplace_test`; không migrate:fresh development. Audit source bằng git status/diff và hash baseline vì repository untracked sẵn.

Chưa role/archive/restore management, Admin fulfillment exceptions, audit-log UI/API, time-series Admin charts/export, VNPay/refund/commission/payout/realtime/chat/auction/Docker. Admin Order overview chỉ đọc; xử lý nghĩa vụ đơn còn mở khi Seller bị khóa vẫn là giới hạn của phase này. Không bắt đầu PHASE12.
