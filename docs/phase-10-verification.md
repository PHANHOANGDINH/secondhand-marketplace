# PHASE 10 — Seller Dashboard + Revenue/Statistics

Ngày kiểm chứng: 2026-09-30. **PHASE 10 COMPLETED**. Không bắt đầu PHASE 11.

| Kiểm tra | Kết quả |
|---|---|
| Backend full `php artisan test --compact` | **149 tests / 1.585 assertions PASS**; thêm13 tests /125 assertions |
| Frontend full `npm.cmd run test:unit -- --run --maxWorkers=1` | **119 tests /10 files PASS**; thêm13 tests |
| Pint | full + `--dirty --format agent` PASS |
| Frontend lint / production build | PASS; Vite8.3.1,144 modules |
| Real HTTP cookie/CSRF | **45 checks PASS** |
| Health | GET http://localhost:8000/api/v1/health →200, status=ok |
| Fixture cleanup / development integrity | PASS: count + SHA-256 rows của16 bảng trước/sau khớp; report requests không thay đổi dữ liệu |

## API và phạm vi số liệu

- GET `/api/v1/seller/dashboard`: current product counts (total/active/pending/sold và mọi trạng thái), order counts (total/pending/completed/cancelled và mọi trạng thái), total_revenue/completed_in_range,5 recent ShopOrders.
- GET `/api/v1/seller/dashboard/revenue`: `from`, `to`, `group_by=day|month`; trả range/timezone,total_revenue,completed_orders,series gồm period/revenue/completed_orders; kỳ rỗng bằng0.
- Auth Sanctum SPA + active account + ShopPolicy ownership; guest401, chưa có Shop/Shop soft-delete404. Shop PENDING/SUSPENDED/REJECTED vẫn đọc được nếu owner ACTIVE. Admin không vượt ownership. shop_id/user_id/unknown fields bị422; không đổi auth architecture.
- **Doanh thu = SQL SUM(shop_orders.subtotal), chỉ status COMPLETED, lọc/nhóm completed_at.** Không cộng shipping fee hoặc Payment attempts, chưa commission/payout. Tiền từ MySQL DECIMAL, API fixed decimal string2 số lẻ; BCMath chỉ chuẩn hóa, không cộng full orders trong PHP. SQL COUNT theo trạng thái; recent orders eager load Order/Buyer và giới hạn5, không N+1.
- **Products là tồn hiện tại** không soft-delete, không lọc ngày. **Orders/recent là đơn tạo trong kỳ** theo created_at; đếm trạng thái hiện tại. **Revenue là đơn hoàn tất trong kỳ** theo completed_at, kể cả đơn tạo trước kỳ; completed_in_range có thể khác completed_orders ở dashboard. UI ghi rõ ba phạm vi.
- Range mặc định30 ngày lịch gồm hôm nay ở Asia/Ho_Chi_Minh; from/to cùng có/cùng bỏ, YYYY-MM-DD, from≤to, tối đa366 ngày bao gồm hai đầu, hỗ trợ2000-01-01..9998-12-31. Boundaries UTC `[from 00:00, to+1 ngày 00:00)`; nhóm bằng UTC+07 không cần MySQL timezone tables. Tháng đầu/cuối chỉ cộng phần trong range. COMPLETED thiếu completed_at không tính revenue.
- Recent orders sort created_at DESC/id DESC: ShopOrder.id, Order.order_code, Buyer display name, total_amount,status,created_at. Không email/phone/address/payment. Buyer soft-delete hiển thị “Người mua”.

## Frontend và kiểm chứng

`/seller/dashboard` có auth guard, nav link, cards, presets7/30 ngày/tháng hiện tại, custom from/to và nhóm ngày/tháng, Chart.js4.5.1, bảng doanh thu, recent-order links, loading/empty/error/422/no-Shop states. Chart tọa độ dùng number để vẽ; tổng tiền/tooltip/bảng giữ decimal string gốc và format bằng BigInt. Không client shop selector. Response cũ bị bỏ khi logout/đổi tài khoản/unmount.

Backend tests cover ownership/guest/no-Shop/Admin/suspended account; product/order state counts; multi-shop order không trộn; Payment attempts/fees không cộng; decimal0.10 và SUM vượt giới hạn một row; UTC microsecond boundaries, day/month/partial months/zero fill; range validation/default/leap-year366 days; completed_at khác created_at/null; recent limit/order/privacy; query-count không tăng theo số đơn. Full suite giữ PHASE1–9, bao gồm checkout concurrency, rollback, cancel stock/COD và Reviews.

Frontend tests cover metrics/scopes/recent links, chart dataset/tooltip/table/update/destroy, Vietnam calendar presets, custom month filter/API params, empty/error/retry/validation/no-Shop, route auth, stale account responses. Chart.js được mock trong Vitest; production build bundle thật. Không tuyên bố manual browser verification.

HTTP dùng3 cookie jars riêng (Buyer không Shop,2 Seller), login qua CSRF cookie; thiếu CSRF419, guest401/no-Shop404. Fixture riêng có multi-shop Order, COMPLETED/PENDING/CANCELLED, shipping và nhiều Payment attempts. Đã kiểm chứng doanh thu tháng9=0.10, tháng10=1250000.00 của Seller A; Seller B riêng200000.00; groupday/month, UTC→Việt Nam, date limits/injection422, recent privacy, default30days. Baseline16 bảng chụp trước prepare và so lại sau cleanup nonce-scoped có guard foreign references; không động seed records/files ảnh. Auto-increment có thể tăng; không reset sequence. Report requests read-only được so hash riêng.

## Files và giới hạn

Tạo: SellerDashboardController; SellerDashboardRequest/SellerRevenueRequest; SellerDashboardResource/SellerRevenueResource; SellerDashboardService/SellerReportRange; SellerDashboardTest; sellerDashboard.api.js; dashboardPresentation.js; RevenueChart.vue; SellerDashboardView.vue; SellerDashboardPhase10.spec.js; báo cáo này. Sửa routes API/router/nav, business-rules.md, api-design.md, README.md. Không duplicate class/route; không migration/dependency/schema/model/enum/factory/seeder/auth core changes; không migrate:fresh development. Test guard vẫn xác minh configured và actual DB `secondhand_marketplace_test`. Repository baseline untracked, dùng git status/diff kèm hash inventory để audit; không xóa source có sẵn.

Giới hạn: chưa commission/payout/Admin dashboard/VNPay/realtime/chat/auction/Docker. Không revenue cache/table riêng hoặc export. Mỗi response dùng read transaction; dashboard và revenue là2 request, không bảo đảm cùng một snapshot nếu dữ liệu đổi giữa hai request. Bấm “Xem” để tải lại. Không bắt đầu PHASE11.
