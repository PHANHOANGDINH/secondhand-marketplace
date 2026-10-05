# PHASE 8 — Checkout / Orders / COD

Ngày kiểm chứng: 2026-09-28. **PHASE 8 COMPLETED**. Không bắt đầu PHASE 9.

## Verification

| Kiểm tra | Kết quả |
|---|---|
| Backend full `php artisan test --compact` | **122 tests / 1.323 assertions PASS**; thêm16 tests, giữ PHASE1–7 |
| Frontend full `npm.cmd run test:unit -- --run --maxWorkers=1` | **90 tests / 8 files PASS**; thêm18 tests |
| Pint | PASS, full `--format agent` và `--dirty --format agent` |
| Lint | PASS, oxlint + ESLint |
| Production build | PASS, Vite8.3.1 / 133 modules |
| Real HTTP cookie/CSRF | **55 checks PASS** |
| Health | GET http://localhost:8000/api/v1/health →200, status=ok |
| Development integrity | Fixture cleanup PASS; counts + SHA-256 toàn bộ rows của16 bảng trước/sau khớp |
| Test isolation | MySQL secondhand_marketplace_test; config + actual database guard trước migrations/tests/worker |

Audit git status/diff và hash baseline trước khi tiếp tục: repository untracked từ trước nên git diff không có tracked patch; dùng inventory/hash để xác định thay đổi. Một Codex chính, không sub-agent. Giữ schema/model/enum/factory/seeder và auth core; không duplicate class/route, không migration mới, không migrate:fresh/seed development. Đọc đúng phần Checkout/Order/Payment và báo cáo PHASE7. Không reset MySQL hoặc thay credentials.

## Endpoints

Prefix `/api/v1`; auth:sanctum + active account + SPA session; mutations có CSRF, giữ rate limiter hiện tại.

| Endpoint | Contract |
|---|---|
| POST `/checkout` | address_id, payment_method=COD, Idempotency-Key UUID;201 mới/200 replay |
| GET `/orders` | Own Orders, overall_status derived, page/per_page |
| GET `/orders/{order}` | Own Order, snapshots, ShopOrders/items, safe Payment summaries |
| GET `/orders/{order}/payments` | Own Order payment attempts, paginated |
| POST `/orders/{order}/shop-orders/{shopOrder}/cancel` | Own Buyer, matching parent, expected_status + reason; trả Order cập nhật |
| GET `/seller/orders` | Own ShopOrders; status/from/to, page/per_page |
| GET `/seller/orders/{shopOrder}` | Own ShopOrder + shipping snapshot; không dữ liệu Shop khác |
| PATCH `/seller/orders/{shopOrder}/status` | expected_status/status; cancel cần reason/failure_category; complete cần cod_collected=true |

Pagination mặc định20/max100; money decimal string, IDs string. Strict field allowlist, không tin frontend totals/shop_id/buyer_id. Buyer/Seller ownership có Policy và recheck dưới lock; Admin không bypass ownership. Seller list/detail không expose Payment toàn Order hoặc ShopOrders khác.

## Checkout, stock và COD

Một transaction checkout toàn Cart: khóa accounts tăng ID →Cart/Address/items→Shop→Category tree→Product tăng ID, đọc lại eligibility/stock/giá; snapshot address/tên/ảnh/giá; tạo Order→mỗi Shop một ShopOrder→OrderItems; trừ stock (0→SOLD); tạo Order-level COD Payment PENDING; audit và clear items; commit. Lỗi bất kỳ rollback cả đơn/payment/stock/cart. Owner lock set thay đổi thì rollback/replan; deadlock retry giới hạn3, hết retry409. Không dùng availability/price từ Cart response.

BCMath giữ precision DECIMAL(15,2), kiểm overflow từng subtotal/tổng, shipping/discount0.00. BR-ORDER-006 được đồng bộ với Product/Cart đã hỗ trợ hai chữ số lẻ, không làm tròn nguyên đồng. Snapshot không đổi khi Product rename/reprice/image update/soft-delete hoặc Address edit/delete. File snapshot tiếp tục được bảo vệ bởi ProductImageService hiện có.

Idempotency unique(buyer_id,checkout_key) + canonical payload SHA-256; cùng key/payload replay trước kiểm empty Cart/address, khác payload409. Buyer/cart lock ngăn double checkout. UI lưu key chưa rõ kết quả theo tài khoản/tab, khóa address của attempt đó và retry cùng key; không tự POST lại bằng key mới khi network/5xx.

Seller: PENDING→CONFIRMED→PROCESSING→SHIPPING→COMPLETED. Buyer hủy PENDING/CONFIRMED; Seller hủy PENDING/CONFIRMED/PROCESSING với lý do OUT_OF_STOCK/DAMAGED/UNFULFILLABLE. Shop ACTIVE mới seller mutation. expected_status stale→409, same-target valid replay không side effect. Hủy khóa accounts→Order/all ShopOrders→Shop→Category→Product→Payment, dùng stock_restored_at và state guard để hoàn tồn đúng một lần, không undelete/unhide. SOLD chỉ ACTIVE nếu revision audit checkout_sold còn khớp updated_at và Shop/owner/category hợp lệ; còn lại HIDDEN. Audit cùng transaction, không secrets hoặc shipping snapshot.

Order không có status column; overall_status derived từ các phần. Totals gốc bất biến khi cancel; payable chỉ cộng phần chưa hủy. COMPLETED nghĩa đã giao/thu đủ COD phần đó; chỉ khi tất cả phần còn lại hoàn tất mới PAID. Hủy một phần cancel PENDING attempt cũ, tạo attempt mới theo payable (có thể PAID nếu phần còn lại đã hoàn tất); không sửa amount của attempt terminal. Mọi phần hủy →CANCELLED, không attempt mới. Payment thuộc Order, hasMany attempts, không Payment riêng từng Shop.

## Frontend và bằng chứng

Routes `/checkout`, `/orders`, `/orders/:id`, `/seller/orders`, `/seller/orders/:id`; Cart chỉ mở checkout khi toàn giỏ hợp lệ. Chọn address/COD, grouped summary, lỗi stock/validation, refresh Cart/badge, Buyer cancel và Seller transitions. Dùng chung OrdersView/OrderView với seller prop, OrderItems/OrderShipping render snapshots. Backend eager loading cho lists, không query live Product để hiển thị snapshot.

Backend tests kiểm empty/invalid/foreign address, injection, fresh prices/overflow, multi-shop, rollback khi audit fail sau Payment, unavailable stock/shop/owner/category, snapshot sau edit/delete, ownership, derived filters, optimistic transitions, COD replacement và cancellation rollback. **Hai process PHP/MySQL thật với barrier**: hai buyer stock1 →201/409; cùng key →201/200 cùng Order; cancel đồng thời →hoàn một lần; complete hai ShopOrder đồng thời →một Payment PAID.

HTTP: cookie jars riêng Buyer/SellerA/SellerB, login/CSRF thật →add Cart→checkout hai Shop→SOLD/empty Cart→replay/conflict→seller fulfillment→partial COD→Buyer cancel phần còn lại→replacement PAID→cancel replay→restore đúng một lần. Có guest401, CSRF419, injection/address422, ownership403, invalid transition409 và snapshot address sau edit. Health và5 SPA entry routes200; UI hành vi kiểm bằng Vitest, không tuyên bố manual browser test. Lần đầu HTTP gặp server đã dừng: cleanup vẫn chạy và hash khớp; đã khởi động lại hidden local servers, lượt cuối55 checks PASS. Fixture nonce riêng và kiểm guard references trước cleanup; baseline16 bảng giữ nguyên, không tạo/xóa file ảnh thật. Auto-increment có thể tăng do fixture, không reset sequence.

## File chính / giới hạn

Backend: CheckoutController/OrderController/SellerOrderController;5 Requests;4 Resources; OrderPolicy/ShopOrderPolicy; CheckoutService/ShopOrderService/OrderLocks/OrderSummary; CheckoutPlanChanged;3 test classes và concurrency worker. Frontend: order.api.js, CheckoutView/OrdersView/OrderView, OrderItems/OrderShipping, orderPresentation, OrderPhase8.spec.js; sửa Cart/nav/router và Cart regression. Cập nhật API/rules/states/README và báo cáo này.

Chỉ COD, không reservation/shipping integration/tax/discount engine/refund/VNPay/review/revenue/admin order dashboard/realtime/chat/auction/Docker. COD collection là xác nhận của Seller, chưa ledger đối soát. Category tree lock phù hợp MVP nhỏ, chưa load benchmark. Shipping exceptions/admin obligations vẫn future. SessionStorage không khả dụng thì retry key chỉ giữ trong memory. Không bắt đầu PHASE9.
