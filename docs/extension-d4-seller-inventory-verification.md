# D4 — Seller Inventory & Action Center

Ngày kiểm tra: 10/10/2026. Tiếp tục từ worktree D1–D3; không hoàn tác các thay đổi trước đó.

## Phạm vi và định nghĩa

- Nguồn tồn kho duy nhất: `products.stock_quantity`; không thêm bảng hoặc migration.
- `OUT_OF_STOCK`: số lượng ≤ 0; `LOW_STOCK`: 0 < số lượng ≤ ngưỡng; `NORMAL`: lớn hơn ngưỡng. Giao diện dùng “Hết hàng”, “Sắp hết”, “Còn”.
- `backend/config/seller-inventory.php`: mặc định ngưỡng sắp hết **5**, đấu giá sắp kết thúc **60 phút**. Có thể đặt `SELLER_LOW_STOCK_THRESHOLD` và `SELLER_AUCTION_ENDING_SOON_MINUTES`; chạy `php artisan config:clear` sau khi đổi env local.
- Cảnh báo tồn kho chỉ tính sản phẩm chưa xóa, trạng thái ACTIVE/SOLD của gian hàng hiện tại. HIDDEN/DRAFT/PENDING/REJECTED không được cộng vào cảnh báo sắp hết/hết hàng. Gian hàng không ACTIVE vẫn được đọc tồn kho theo policy hiện có nhưng không nhận số cảnh báo tồn kho cần hành động.
- Chờ duyệt/bị từ chối là số sản phẩm PENDING/REJECTED chưa xóa, không phụ thuộc khoảng ngày của báo cáo doanh thu.
- Đơn cần xử lý: ShopOrder PENDING/CONFIRMED/PROCESSING/SHIPPING thuộc gian hàng ACTIVE. COD được tính; ZaloPay phải có thanh toán PAID và không có payment cần reconciliation. Đây là bộ lọc đọc dữ liệu theo điều kiện fulfillment hiện có, không thêm chuyển trạng thái.
- Đấu giá đang diễn ra: LIVE, `starts_at ≤ server_time < ends_at`, do người bán hiện tại sở hữu và sản phẩm thuộc gian hàng đó/chưa xóa. Sắp kết thúc là tập con có `ends_at ≤ server_time + ngưỡng`.

## API

| Endpoint | Hành vi |
| --- | --- |
| GET `/api/v1/seller/action-center` | Thông tin gian hàng, hai ngưỡng, server_time và 7 số đếm hiện tại |
| GET `/api/v1/seller/inventory` | Danh sách phân trang chỉ sản phẩm của gian hàng hiện tại |
| PATCH `/api/v1/seller/products/{product}` | Tái sử dụng endpoint sửa sản phẩm với `stock_quantity`, `expected_updated_at` |
| GET `/api/v1/seller/orders?action_required=1` | Danh sách khớp định nghĩa đơn cần xử lý |
| GET `/api/v1/seller/auctions?live_now=1` | Đấu giá hiện đang LIVE theo thời gian máy chủ |
| GET `/api/v1/seller/auctions?ending_soon=1` | Đấu giá LIVE sắp kết thúc theo ngưỡng cấu hình |

Inventory: `filter=all|low_stock|out_of_stock|active|pending|hidden|rejected`; `sort=stock_asc|stock_desc|newest|updated`; `search` tên sản phẩm tối đa 200 ký tự; `page`, `per_page` (mặc định 20, tối đa 100). Ký tự `%`, `_`, `!` được escape để tìm theo chuỗi, không dùng chúng như wildcard do client cung cấp. Từ chối bộ lọc/thuộc tính ngoài danh sách, kể cả `shop_id` do client gửi.

ManagedProductResource bổ sung `inventory_state`, `stock_editable`, `stock_edit_reason`. Trạng thái nghiệp vụ và trường API cũ được giữ nguyên. Cả hai endpoint mới dùng middleware session/Sanctum/tài khoản ACTIVE/throttle hiện có; policy Shop kiểm tra sở hữu. Người chưa đăng nhập nhận 401, người không có gian hàng nhận 404, stock IDOR nhận 403.

## UI

- `/seller/inventory`: menu Seller, tên gian hàng, tiêu đề “Quản lý tồn kho”, tìm kiếm/bộ lọc/sắp xếp/phân trang. URL lưu các lựa chọn, khôi phục khi điều hướng; bỏ phản hồi tải cũ khi URL thay đổi.
- `SellerActionCenter`: mục “Cần xử lý” trên dashboard, bảy thẻ số thật liên kết đến trang được lọc. Có tải/lỗi/làm mới; số đếm độc lập với khoảng ngày báo cáo hiện có.
- `InventoryBadge`: dùng chung trên danh sách sản phẩm Seller và màn tồn kho; ngưỡng và trạng thái được backend quyết định.
- Sửa nhanh: biểu mẫu inline có nhãn, focus vào số lượng, chặn số âm/phân số/ngoài giới hạn, chống gửi lặp, hiển thị lưu/thành công/lỗi/phiên bản đã thay đổi. Trường hợp đấu giá có giải thích tiếng Việt từ backend.
- Desktop dùng bảng và toolbar gọn; mobile dưới 768 px chuyển các dòng thành thẻ có nhãn cột. Toolbar hai cột ở tablet, một cột trên điện thoại. Không thêm framework, dependency hay animation.

## Sửa tồn kho và tương thích đấu giá

ProductService vẫn dùng transaction, ProductLocks, policy và `expected_updated_at`. Không có endpoint stock riêng và không cập nhật DB trực tiếp từ UI.

Phát hiện đã xác nhận: endpoint sửa sản phẩm trước D4 chưa gọi reservation guard khi đổi tồn kho. D4 gọi lại `FixedPriceEligibility::assertAvailable()` **khi số lượng thực sự thay đổi**, sau khi giữ product lock và trước khi lưu. SCHEDULED/LIVE hoặc ENDED có người thắng chưa tạo Order và còn hạn checkout trả 409: “Sản phẩm đang được giữ cho đấu giá.” Guard đọc khóa cùng cơ chế hiện có; không đổi bidding/settlement/order/payment rules. Retry với số lượng không đổi không tạo mutation mới.

Giữ nguyên quy tắc kiểm duyệt: ACTIVE giảm về 0 → SOLD; ACTIVE/SOLD đổi tồn kho dương → PENDING theo ProductService hiện có. UI giải thích kết quả chờ duyệt. Không tự tái duyệt hay làm sản phẩm công khai. Auction cancelled/không còn reservation không chặn cập nhật. Stock validation server vẫn dùng integer không âm, tối đa unsigned int, cùng kiểm tra timestamp.

Đồng bộ soft-delete ở bộ lọc đấu giá mới: relation Auction.product có `withTrashed` để bảo toàn lịch sử, nên bộ lọc LIVE/ending-soon đặt rõ `products.deleted_at IS NULL`. Regression chứng minh số danh sách và action center cùng loại phiên có sản phẩm xóa mềm; relation lịch sử và API cũ không bị đổi.

## Truy vấn và hiệu năng

Bốn số đếm sản phẩm dùng một aggregate SQL; hai số đếm đấu giá dùng một aggregate SQL; đơn dùng COUNT với EXISTS payment/shop. Không chạy một query cho mỗi thẻ. Inventory dùng eager loading của ProductQuery (ảnh/shop/category/auction claim và tổng hợp review) và phân trang. Regression so sánh một với 20 sản phẩm, số query không tăng tuyến tính; summary có giới hạn tối đa 8 query trong test. Tái sử dụng index theo shop/status/deleted_at và seller hiện có; chưa có bằng chứng cần index/migration mới. Tìm tên chứa chuỗi vẫn cần quét trong tập sản phẩm của gian hàng, không đưa vào search engine mới.

## Demo pack

FinalDemoSeeder bổ sung bốn sản phẩm có ảnh local: sắp hết, hết hàng, chờ duyệt, bị từ chối; thêm một đơn COD PENDING hợp lệ qua Cart/Checkout service cho Seller 1. Seller 1 đã có sản phẩm tồn kho bình thường và LIVE Auction. Các sản phẩm mới đi qua Product/Image/Moderation service, không tạo status giả. Rerun giữ nguyên sản phẩm/progress/password hiện có; slug reserved xung đột bị từ chối.

Fresh demo pack trên CSDL test: **22 sản phẩm, 18 public, 6 Order**, bốn sản phẩm D4 mỗi sản phẩm một ảnh primary. Snapshot toàn bộ seed lần hai giống lần một, kể cả mật khẩu demo dù config password đổi. Không chạy lại FinalDemoSeeder trên CSDL phát triển trong D4 để tránh tự thêm các fixture D2/D3 hoặc thay đổi tiến độ demo hiện tại.

Khi muốn chủ động áp dụng pack local (DEMO_PASSWORD riêng được cấu hình; không dùng CSDL production):

```powershell
cd backend
php artisan db:seed --class=FinalDemoSeeder
```

Không cần `migrate:fresh`. Với demo đã tiến triển, phiên đấu giá và Order hiện có được giữ nguyên; seeder không tái mở phiên đã kết thúc.

## Kiểm thử

Các test mới theo RefreshDatabase, factory, session web/Origin, JSON assertions và Vitest/router/mocking hiện có. `testing-best-practices` không có trong môi trường; yêu cầu D4 cho phép dùng conventions và regression lân cận làm tiêu chuẩn, không sửa AGENTS.md hay giả lập skill.

| Kiểm tra | Kết quả |
| --- | --- |
| Backend tập trung SellerInventory + FinalDemoSeeder | PASS — 18 tests / 215 assertions |
| Backend đầy đủ | PASS — 399 tests / 3.948 assertions |
| Frontend D4 | PASS — 19 tests |
| Frontend đầy đủ | PASS — 413 tests / 33 files |
| Pint | PASS |
| Frontend lint | PASS |
| Production build | PASS |

```powershell
cd backend
php artisan test --compact --filter='SellerInventoryTest|FinalDemoSeederTest'
php artisan test --compact
php vendor/bin/pint --dirty --format agent
cd ../frontend
npm run test:unit -- --run --maxWorkers=4
npm run lint
npm run build
```

Backend suite ép APP_ENV=testing và DB_DATABASE=secondhand_marketplace_test trong phpunit.xml. Không chạy migrate:fresh trên development DB.

## Browser và bảo toàn dữ liệu

Browser thực tại frontend `http://localhost:5173`, backend `http://127.0.0.1:8000`; session/CSRF qua Axios hiện có. Kiểm tra lại sau chỉnh giao diện cuối:

- 1440/1024/768/390: inventory và dashboard có số liệu, không overflow; ảnh fixture tải được. Đã xem ảnh chụp desktop/mobile, thu gọn toolbar desktop.
- Khách vào Seller inventory → Login. Seller 1 xem fixture của mình, thao tác thẻ lọc, sửa stock inline, focus input, thấy PENDING theo quy tắc hiện có.
- Sản phẩm đang giữ cho Auction: không hiện nút sửa stock và PATCH trực tiếp nhận 409.
- Link đơn/đấu giá lọc được và số danh sách khớp action center. Product list có badge tồn kho.
- Buyer không có shop nhận 404. Seller 2 không thấy fixture Seller 1 và PATCH IDOR nhận 403.
- Không có Runtime.exceptionThrown trong các hành trình đã kiểm tra.

Sáu fixture tạm được tạo qua service của ứng dụng, không sửa record người dùng đã có. Đã xóa Product/Auction/Image/audit thuộc fixture và các file ảnh; đóng riêng browser verification, giữ nguyên các service local của người dùng. Hash của **22 bảng business phát triển trước/sau giống hệt**. Kiểm tra riêng `wishlists=0`, `reports=0` giữ nguyên; không còn sản phẩm tạm D4 hay manifest chưa cleanup. Không đổi mật khẩu, không wipe DB, không migration mới. Session/cache phục vụ đăng nhập thử không nằm trong hash business.

## Giới hạn

- Chỉ có dashboard alerts; không thêm notification LOW_STOCK persistent hay số unread message vào aggregate này. Messaging/bell hiện có hoạt động độc lập.
- Số đếm là các truy vấn đọc hiện tại, không phải một snapshot transaction xuyên mọi bảng; trạng thái có thể đổi giữa khi tải dashboard và bấm link. Mutation luôn kiểm tra lại trên server.
- Giữ nguyên moderation sau thay đổi stock; restock không tự xuất bản sản phẩm.
- Seeder D4 đã được chứng minh trên test DB, chưa áp dụng vào development DB. Auction demo đã kết thúc không được tự tái tạo.
- Browser kiểm tra bốn viewport bằng Edge headless, không thay thế thử thiết bị iOS/Android vật lý hay tải production lớn.

## Files thay đổi trong D4

28 files, lấy theo hash đầu D4; không bao gồm thay đổi D1–D3 đã tồn tại.

- `backend/.env.example`
- `backend/app/Http/Controllers/Api/V1/AuctionController.php`
- `backend/app/Http/Controllers/Api/V1/SellerInventoryController.php`
- `backend/app/Http/Controllers/Api/V1/SellerOrderController.php`
- `backend/app/Http/Requests/Domain/AuctionListRequest.php`
- `backend/app/Http/Requests/Domain/SellerInventoryRequest.php`
- `backend/app/Http/Requests/Domain/SellerOrderListRequest.php`
- `backend/app/Http/Resources/ManagedProductResource.php`
- `backend/app/Services/ProductService.php`
- `backend/app/Services/SellerInventoryService.php`
- `backend/config/seller-inventory.php`
- `backend/database/seeders/FinalDemoSeeder.php`
- `backend/routes/api.php`
- `backend/tests/Feature/FinalDemoSeederTest.php`
- `backend/tests/Feature/SellerInventoryTest.php`
- `frontend/src/__tests__/AuctionUI.spec.js`
- `frontend/src/__tests__/SellerInventory.spec.js`
- `frontend/src/api/inventory.api.js`
- `frontend/src/components/seller/InventoryBadge.vue`
- `frontend/src/components/seller/SellerActionCenter.vue`
- `frontend/src/components/seller/SellerShell.vue`
- `frontend/src/router/index.js`
- `frontend/src/views/OrdersView.vue`
- `frontend/src/views/SellerAuctionsView.vue`
- `frontend/src/views/SellerDashboardView.vue`
- `frontend/src/views/SellerInventoryView.vue`
- `frontend/src/views/SellerProductsView.vue`
- `docs/extension-d4-seller-inventory-verification.md`

## Kết luận

**PASS — D4 sẵn sàng demo.** Full backend/frontend, Pint, lint, production build và browser checks đều PASS; dữ liệu phát triển được bảo toàn. Pack demo mở rộng được kiểm thử idempotent trên DB test, có thể chủ động seed local theo hướng dẫn ở trên.

## Cập nhật sau audit — fix wave 2 (12/10/2026)

Quy tắc "ACTIVE/SOLD đổi tồn kho dương → PENDING" ở trên **đã được thay thế**. Khi thay đổi duy nhất là `stock_quantity`:

| Trạng thái hiện tại | Tồn kho mới | Kết quả |
| --- | --- | --- |
| ACTIVE | > 0 | Giữ ACTIVE |
| ACTIVE | 0 | SOLD |
| SOLD | > 0 | ACTIVE nếu đủ điều kiện mở bán lại, ngược lại PENDING |
| SOLD | 0 | Giữ SOLD (không ghi gì) |
| DRAFT, PENDING, REJECTED, HIDDEN | bất kỳ | Giữ nguyên trạng thái |

Điều kiện SOLD → ACTIVE: `published_at` khác null; gian hàng và chủ gian hàng ACTIVE; toàn bộ chuỗi danh mục ACTIVE (`OrderLocks::eligible`); và không có chỉnh sửa nội dung/ảnh nào sau lần Admin duyệt gần nhất (sản phẩm SOLD cho sửa nội dung và ảnh mà không qua duyệt, nên nhập hàng không được phép công khai các chỉnh sửa đó).

Tồn kho đổi cùng lúc với nội dung vẫn theo quy tắc duyệt lại cũ. `expected_updated_at` vẫn bắt buộc (409 khi lệch). Audit ghi `product.stock_adjusted` với lý do `stock: <cũ> → <mới>`.

Khi sản phẩm đang bị phiên đấu giá giữ, **mọi** thao tác của Seller trên sản phẩm và ảnh (không chỉ tồn kho) trả 409; riêng video vẫn thao tác được. Resource trả thêm `auction_locked`. Chi tiết: [fix wave 1–2](fix-wave-1-2-verification.md).
