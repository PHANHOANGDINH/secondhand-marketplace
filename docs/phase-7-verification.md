# PHASE 7 — Shopping Cart

Ngày kiểm chứng: 2026-09-27. **PHASE 7 COMPLETED**. Không bắt đầu PHASE 8.

## Kết quả

| Kiểm tra | Kết quả |
|---|---|
| Backend `php artisan test --compact` toàn bộ | **106 tests / 1.035 assertions PASS**, thêm15 Cart tests, giữ regression PHASE1–6 |
| Frontend `npm.cmd run test:unit -- --run --maxWorkers=1` | **72 tests / 7 files PASS**, thêm16 Cart tests; Product detail regression cập nhật cho nút Cart thật |
| Pint | PASS — `php vendor/bin/pint --format agent` và `--dirty --format agent` |
| Lint | PASS — `npm.cmd run lint` (oxlint + ESLint) |
| Production build | PASS — `npm.cmd run build`, Vite8.3.1, 126 modules |
| HTTP / MySQL | **49 checks PASS**, cookie/CSRF thật và hai process MySQL đồng thời |
| Health API | GET http://localhost:8000/api/v1/health →200, status=ok, contract giữ nguyên |
| Frontend route | http://localhost:5173/cart →SPA entry200; route guard và UI hành vi kiểm bằng Vitest |
| Development integrity | Counts + SHA-256 toàn bộ rows trong16 bảng trước/sau fixture khớp; không tạo/xóa file ảnh |
| Test isolation | MySQL `secondhand_marketplace_test`, guard config + actual DB trước RefreshDatabase |
| Source/security audit | Không duplicate class/route; schema/model/enum/factory/seeder/auth core không đổi; secret scan PASS |

Đã audit git status, Cart/CartItem/Product/Shop, ProductQuery và đúng các phần Cart trong docs. Chỉ một tiến trình Codex chính; không dùng sub-agent. Đọc backend/AGENTS.md; `.ai/rules` và skill testing-best-practices không có trong môi trường, dùng PHPUnit/Vitest hiện hữu. Repository đã untracked từ trước; không commit/reset/clean, không cài dependency, không migration mới hoặc migrate:fresh/seed development. MySQL/.env giữ nguyên.

## Endpoints

Prefix `/api/v1`; cả năm route dùng auth:sanctum, active-account, stateful SPA session và throttle hiện tại. Mutations dùng CSRF.

| Endpoint | Hành vi / response |
|---|---|
| GET `/cart` | Tạo Cart nếu chưa có; chỉ Cart hiện tại;200 CartResource |
| POST `/cart/items` | product_id + quantity;201 dòng mới,200 khi tăng quantity dòng có sẵn |
| PATCH `/cart/items/{item}` | quantity tuyệt đối;200 CartItemResource |
| DELETE `/cart/items/{item}` | Xóa item của mình kể cả unavailable;204 |
| DELETE `/cart` | Xóa toàn bộ items của mình, giữ Cart; gọi lặp vẫn204 |

## Rules và response

Mỗi User có một Cart; khóa buyer/Cart để tạo và cập nhật an toàn, unique(cart_id,product_id) giữ nguyên. Thêm cùng Product tăng quantity, không tạo dòng trùng. Quantity1–99 theo schema/docs, tổng sau cộng cũng≤99 và≤stock hiện tại. PATCH kiểm tra lại quantity/stock/availability. Product phải public theo ProductQuery: ACTIVE, stock>0, chưa xóa, Shop/owner ACTIVE chưa xóa, Category và ancestors ACTIVE. Cấm hàng của Shop mình kể cả Admin; foreign item→403. Không role SELLER hoặc quyền Admin bypass ownership.

Strict field allowlist từ chối cart_id/user_id/price/subtotal và các field ngoài contract (422). Stock/availability conflict→409; actor/ownership→403; guest→401. Add/update dùng transaction, lock accounts theo ID →Cart→Shop→Category→Product tương thích Product writers, kiểm tra lại dưới lock. Clear/remove chỉ scope Cart của current user. Cart là dữ liệu tạm, không tạo AuditLog thương mại hoặc Order/Payment.

GET có items, item_count, total_quantity, subtotal, available_subtotal và has_unavailable_items. Item gồm id/quantity/unit_price/subtotal/available/unavailable_reason/max_quantity, Product summary, Shop summary, primary_image qua Resource hiện có; không owner_id/moderation_reason/internal path. Giá luôn đọc Product hiện tại, `bcmul(price,quantity,2)` và `bcadd` để cộng; trả chuỗi decimal, không float, không thêm cột giá/subtotal. Tests gồm0.10,999999.99 và giá lớn với quantity99.

## Unavailable và nhiều Shop

GET giữ item đã hide/suspend/out-of-stock/soft-delete, không tự xóa hoặc tự giảm quantity. Reasons: PRODUCT_UNAVAILABLE, INSUFFICIENT_STOCK, OWN_SHOP. Summary tối thiểu của Product soft-deleted vẫn đọc qua withTrashed, không có link public hoạt động. Có thể remove/clear; khi stock giảm còn dương, UI cho giảm về max_quantity hợp lệ. Quan hệ eager load và availability query batch; test query count không tăng theo số dòng.

API trả danh sách items phẳng, mỗi item có Shop summary; frontend group theo Shop. `subtotal` là tổng hiện tại của tất cả dòng có giá, **gồm unavailable**; `available_subtotal` chỉ cộng các dòng available. UI ghi rõ và hiển thị riêng khi có cảnh báo. Badge là tổng quantity, gồm unavailable. Cart không reserve hoặc trừ stock; Checkout phase sau phải đọc lại toàn bộ giá/trạng thái/stock và kiểm tra giới hạn tổng tiền.

## Frontend và HTTP

`/cart` có ảnh/fallback, tên, giá hiện tại, sửa quantity, subtotal, remove, clear, totals, cảnh báo và nút tải lại. ProductView (trang detail hiện có) thêm AddToCart: guest→login, authenticated→API, hiển thị validation/stock errors và success. CartLink badge nằm trong navigation authenticated. Pinia store chỉ lưu trong memory, backend là nguồn dữ liệu; refresh sau mutation, reset khi logout/đổi tài khoản, bỏ response cũ, không tự retry POST khi refresh lỗi. Không có nút Checkout hoạt động.

HTTP dùng buyer/other với cookie jars riêng: login/CSRF →auto-create →add/increment →multi-shop totals →PATCH →remove/clear. Đã kiểm injection422, CSRF419, guest401, foreign ownership403, own-shop403, stock409, giá mới, PENDING/hidden/suspended giữ nguyên item và cảnh báo; clear không ảnh hưởng Cart khác. Hai process với cùng Cart, stock1: một add thành công, một409, chỉ một dòng quantity1 và không decrement stock. Logout và cleanup fixture nonce riêng; snapshot16 bảng chứng minh baseline giữ nguyên. Không ghi password/cookie/secret vào báo cáo; không tuyên bố manual browser testing.

## File chính và giới hạn

Thêm CartController; StoreCartItemRequest/UpdateCartItemRequest; CartService; CartResource/CartItemResource; CartItemPolicy; CartTest. Frontend thêm cart.api.js, stores/cart.js, CartView.vue, AddToCart.vue, CartLink.vue, CartPhase7.spec.js. Sửa routes/api.php, frontend router/DefaultLayout/ProductView/ProductPhase6.spec.js, README và docs Cart API/business rules; thêm báo cáo này. Tổng **15 file mới, 8 file sửa**, không xóa source.

Chưa Checkout/Order creation/Payment/VNPay/Review/Revenue/Realtime/Chat/Auction/Docker. Giá và availability chỉ là thời điểm đọc, không giữ hàng; chưa đồng bộ realtime giữa tab/thiết bị, cần tải lại giỏ khi dữ liệu bên ngoài đổi. Không có guest cart hoặc source of truth localStorage. Không bắt đầu PHASE 8.
