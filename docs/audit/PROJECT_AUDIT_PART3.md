# PROJECT AUDIT — PART 3/3 (mục 8–14)

- Tiếp theo `PROJECT_AUDIT_PART1.md` (mục 0–4, có mục lục) và `PROJECT_AUDIT_PART2.md` (mục 5–7).
- Đường dẫn bằng chứng tính từ gốc repo `secondhand-marketplace/`.
- Ký hiệu: ✅ ĐÃ XÁC MINH (thấy trong code / output lệnh) · ⚠️ SUY LUẬN · ❓ KHÔNG TÌM THẤY.

---

## 8. Frontend

### 8.1 Vue Router

Nguồn: `frontend/src/router/index.js`. History mode `createWebHistory` (`:5`). Mọi component đều lazy-load.

| Path | Name | Component | Meta |
| --- | --- | --- | --- |
| `/` | home | HomeView | — |
| `/login` | login | LoginView | guestOnly |
| `/register` | register | RegisterView | guestOnly |
| `/forgot-password` | forgot-password | ForgotPasswordView | — |
| `/reset-password` | reset-password | ResetPasswordView | — |
| `/products` | products | ProductsView | public |
| `/products/:slug` | product | ProductView | public |
| `/shops` | shops | ShopsView | public |
| `/shops/:slug` | shop | ShopView | public |
| `/auctions` | auctions | AuctionsView | public |
| `/auctions/:id` | auction | AuctionArenaView | public |
| `/auctions/:id/checkout` | auction-checkout | AuctionCheckoutView | requiresAuth |
| `/cart` | cart | CartView | requiresAuth |
| `/checkout` | checkout | CheckoutView | requiresAuth |
| `/payment/result` | payment-result | PaymentResultView | requiresAuth |
| `/orders` | orders | OrdersView | requiresAuth |
| `/orders/:id` | order | OrderView | requiresAuth |
| `/wishlist` | wishlist | WishlistView | requiresAuth |
| `/notifications` | notifications | NotificationsView | requiresAuth |
| `/reports` | reports | ReportsView | requiresAuth |
| `/account` | account | AccountView | requiresAuth |
| `/account/profile` | profile | ProfileView | requiresAuth |
| `/account/addresses` | addresses | AddressesView | requiresAuth |
| `/seller/shop` | seller-shop | SellerShopView | requiresAuth |
| `/seller/dashboard` | seller-dashboard | SellerDashboardView | requiresAuth |
| `/seller/inventory` | seller-inventory | SellerInventoryView | requiresAuth |
| `/seller/products` | seller-products | SellerProductsView | requiresAuth |
| `/seller/products/create` | seller-product-create | SellerProductEditView | requiresAuth |
| `/seller/products/:id/edit` | seller-product-edit | SellerProductEditView | requiresAuth |
| `/seller/orders` | seller-orders | OrdersView (props `seller: true`) | requiresAuth |
| `/seller/orders/:id` | seller-order | OrderView (props `seller: true`) | requiresAuth |
| `/seller/auctions` | seller-auctions | SellerAuctionsView | requiresAuth |
| `/seller/auctions/:id` | seller-auction | AuctionArenaView (props `seller: true`) | requiresAuth |
| `/admin/dashboard` | admin-dashboard | AdminDashboardView | requiresAuth, requiresAdmin |
| `/admin/users` | admin-users | AdminUsersView | requiresAuth, requiresAdmin |
| `/admin/shops` | admin-shops | AdminShopsView | requiresAuth, requiresAdmin |
| `/admin/categories` | admin-categories | AdminCategoriesView | requiresAuth, requiresAdmin |
| `/admin/products` | admin-products | AdminProductsView | requiresAuth, requiresAdmin |
| `/admin/reviews` | admin-reviews | AdminReviewsView | requiresAuth, requiresAdmin |
| `/admin/orders` | admin-orders | AdminOrdersView | requiresAuth, requiresAdmin |
| `/admin/reports` | admin-reports | ReportsView (props `admin: true`) | requiresAuth, requiresAdmin |
| `/403` | forbidden | ForbiddenView | requiresAuth |
| `/:pathMatch(.*)*` | not-found | NotFoundView | — |

- Guard toàn cục (`frontend/src/router/index.js:263`, `frontend/src/router/authGuard.js:1-10`): chờ `auth.initialize()`; chưa đăng nhập vào route cần auth → `/login?redirect=…`; không phải admin vào route admin → `/403`; đã đăng nhập vào route `guestOnly` → `/`.
- ✅ **Route `/seller/inventory` bị khai báo trùng hai lần** (`index.js:8-12` và `:14-18`, cùng path và name).
- ✅ Không có meta "phải có shop": mọi route `/seller/*` chỉ cần đăng nhập; việc không có shop do API trả 404/403 và view tự xử lý.
- ✅ Không có trang riêng cho tin nhắn (dùng ngăn kéo `ChatDrawer`) và video (dùng `VideoTheater` trong trang chủ, mở bằng `/?video={id}`).

### 8.2 Pinia stores (`frontend/src/stores/`)

| Store | State chính | Action → API |
| --- | --- | --- |
| `auth.js` | `user`, `initialized`, `loading`, `initializationError`, `sessionBlocked`; getter `isAuthenticated`, `isAdmin` (`user.role === 'ADMIN'`, `:11`) | `initialize`/`fetchUser` → `GET /auth/me`; `login` → `POST /auth/login`; `register` → `POST /auth/register`; `logout` → `POST /auth/logout` |
| `cart.js` | `data`, `loading`, `writing`, `error`; getter `busy`, `count` | `load` → `GET /cart`; `add` → `POST /cart/items`; `update` → `PATCH /cart/items/{id}`; `remove` → `DELETE /cart/items/{id}`; `clear` → `DELETE /cart`. Mỗi lần ghi xong tải lại giỏ; có bộ đếm `revision` chống ghi đè do phản hồi cũ; tự reset khi đổi user (`:24`) |
| `wishlist.js` | map `states`, `pending`, `errors` theo product id, `revision` | `hydrate(product)` (lấy `is_wishlisted` từ API); `toggle` → `POST`/`DELETE /products/{id}/wishlist`; reset khi đổi user (`:13-22`) |
| `counter.js` | `count` | ✅ store mẫu của scaffold, **không được import ở đâu** |

- Trạng thái tin nhắn và thông báo **không** nằm trong Pinia mà dùng `provide/inject` từ `DefaultLayout.vue:24-25` (`provideMessaging`, `provideNotifications`).

### 8.3 API client

- `frontend/src/api/axios.js:3-9`: `baseURL = import.meta.env.VITE_API_BASE_URL`, `withCredentials: true`, `withXSRFToken: true`, `timeout: 10000`, header `Accept: application/json`.
- ✅ **Không có interceptor nào** (request hay response). Lỗi 401/419 không được xử lý tập trung; mỗi nơi tự gọi `domainError()` (`frontend/src/api/domain.api.js:10-22`) hoặc `authError()` (`frontend/src/api/auth.api.js:18-26`).
- CSRF: mọi request ghi gọi `GET /sanctum/csrf-cookie` trước (`domain.api.js:6-8`, `auth.api.js:3-14`) → mỗi thao tác ghi tốn 2 request.
- Dev proxy: Vite chuyển tiếp `/api`, `/sanctum`, `/storage`, `/placeholders` tới `VITE_BACKEND_PROXY_TARGET` (mặc định `http://127.0.0.1:8000`) để cookie cùng origin (`frontend/vite.config.js:10-16`).
- Idempotency key sinh bằng `crypto.randomUUID()`: `frontend/src/views/CheckoutView.vue:83`, `AuctionCheckoutView.vue:106`, `frontend/src/components/PaymentAttempts.vue:22`.
- Tiền: hiển thị bằng regex + `BigInt` + `Intl.NumberFormat('vi-VN')`, không qua `Number` (`frontend/src/composables/productPresentation.js:10-16`).

### 8.4 Echo / Reverb client

- Một module singleton: `frontend/src/composables/auctionEcho.js`. Echo chỉ được tạo khi có `VITE_REVERB_APP_KEY` (`:11,15-18`); broadcaster `reverb`, transport `ws`/`wss` (`:23-32`).
- Authorizer tùy biến: lấy CSRF cookie rồi `POST /api/v1/broadcasting/auth` bằng axios instance chung (`:33-50`).
- `subscribeRealtime(kind, id, handlers, onReady)` (`:68-117`): `kind` ∈ `public` → `auction.{id}`, `video` → `video.{id}`, `private` → `private-user.{id}`; đếm tham chiếu theo consumer; trả về hàm `release`.
- Cleanup: consumer cuối rời kênh thì `stopListening` + `leave` + xóa khỏi map; hết kênh thì `disconnect()` toàn bộ (`:105-116`, `:121-129`). Có `import.meta.hot.dispose` (`:130`).
- Sau khi kết nối lại (`subscribed`), mỗi consumer được gọi `onReady` để tải lại snapshot qua REST (`:80-84`).

| Nơi subscribe | Kênh | Event | Cleanup |
| --- | --- | --- | --- |
| `useAuctionRealtime.js:70-78` (dùng ở `AuctionArenaView.vue:33`) | `auction.{id}` | `AuctionBidPlaced`, `AuctionStatusChanged` | `onScopeDispose` (`:83-88`) + `release()` khi id đổi (`:65`) |
| `useUserRealtime.js:14-18` (dùng ở `components/auction/OutbidToasts.vue:7`) | `private-user.{id}` | `AuctionOutbid` | `onScopeDispose` (`:22-25`) |
| `useNotifications.js:129-143` (cấp từ `DefaultLayout.vue:25`) | `private-user.{id}` | `NotificationCreated` | `onUnmounted` (`:158-166`) + `release()` khi đổi user (`:118`) |
| `useVideoComments.js:154-168` (dùng ở `components/home/VideoComments.vue:25`) | `video.{id}` | `VideoCommentCreated`, `VideoCommentHidden` | `onScopeDispose` (`:181-186`) |

- Đồng hồ đấu giá: client chỉ dùng để hiển thị, đồng bộ theo `server_time` và `performance.now()` (`frontend/src/composables/auctionTimeSync.js:1-28`); tính hợp lệ của bid do server quyết định.
- Tin nhắn **không** dùng Echo: polling `setTimeout(tick, 4000)`, dừng khi tab ẩn (`frontend/src/composables/useMessaging.js:178-191`).

### 8.5 Component dùng chung

| Component | File | Số nơi import (ngoài test) | Ghi chú |
| --- | --- | --- | --- |
| ProductCard | `components/ProductCard.vue` | 3 | thẻ sản phẩm |
| FavoriteButton | `components/FavoriteButton.vue` | 3 | dùng store wishlist |
| ReportDialog | `components/ReportDialog.vue` | 3 | báo cáo sản phẩm/shop/bình luận |
| AccessibleDialog | `components/ui/AccessibleDialog.vue` | 3 | bẫy focus bằng Tab, đóng bằng Escape, đặt `inert` cho phần còn lại (`:15-57`) |
| SkeletonBlock / SkeletonState | `components/ui/` | 1 / 2 | khung chờ tải |
| LoadingState / EmptyState | `components/ui/` | 1 / 20 | |
| StatusBadge | `components/ui/StatusBadge.vue` | 22 | luôn dịch qua `getStatusLabel`; nhãn truyền vào có dạng IN_HOA bị bỏ qua (`:33`) |
| PageHeader | `components/ui/PageHeader.vue` | 33 | |
| ConfirmDelete | `components/ui/ConfirmDelete.vue` | 1 | |
| CollectionPager | `components/CollectionPager.vue` | 18 | phân trang |
| DomainFeedback / DomainField | `components/` | 42 / 10 | hiển thị lỗi/trạng thái và field có lỗi validate |
| AdminShell / SellerShell / AccountNav | `components/admin`, `seller`, `account` | 1 / 1 / 1 | khung điều hướng theo khu vực |
| NotificationBell / NotificationList | `components/notifications/` | 3 / — | |
| ChatDrawer / ChatLauncher / ChatSellerButton | `components/chat/` | — | gắn ở `DefaultLayout.vue:237` |
| AuctionCard / AuctionCountdown / OutbidToasts | `components/auction/` | 2 / — / async | `OutbidToasts` nạp bằng `defineAsyncComponent` (`DefaultLayout.vue:28`) |
| RevenueChart | `components/RevenueChart.vue` | — | Chart.js; chuyển doanh thu sang `Number` chỉ để vẽ (`:13`) |

### 8.6 Localization

- Bảng nhãn tập trung: `frontend/src/utils/labels.js` (trạng thái theo miền, vai trò, phương thức thanh toán, settlement), `frontend/src/utils/validationLabels.js` (dịch lỗi validate; thông điệp tiếng Anh không nhận diện được thay bằng câu chung, `:22-35`), `frontend/src/composables/productPresentation.js:1-9` (tình trạng sản phẩm), `frontend/src/utils/reportPresentation.js`.
- Giá trị không có trong bảng → `Không xác định` (`labels.js:2,39`), không rơi về raw enum.
- Kết quả quét template (`{{ …status }}`, `{{ …role }}`, text node IN_HOA, text node/thuộc tính tiếng Anh, từ `MARKETPLACE`): ✅ **không tìm thấy** trường hợp nào in trực tiếp enum hay tiếng Anh.

Các chỗ còn có thể lộ raw enum / tiếng Anh ra UI:

| # | Vị trí | Nội dung | Mức |
| --- | --- | --- | --- |
| 1 | `frontend/src/views/OrderView.vue:151` | In nguyên `part.cancellation_reason`. Khi seller hủy, backend lưu `"{failure_category}: {reason}"` (`backend/app/Services/ShopOrderService.php:82`) → người mua thấy `OUT_OF_STOCK: …`, `DAMAGED: …`, `UNFULFILLABLE: …` | ✅ lộ raw enum |
| 2 | `frontend/src/components/seller/SellerProductEditor.vue:177`, `frontend/src/views/SellerShopView.vue:63`, `AdminProductsView.vue:197`, `AdminShopsView.vue:184`, `frontend/src/components/AdminModerationPanel.vue:221` | In nguyên `moderation_reason` do admin nhập. Dữ liệu demo dùng tiếng Anh: `'Local C1 demo approved'` (`backend/database/seeders/FinalDemoSeeder.php:97,136`) | ⚠️ phụ thuộc dữ liệu |
| 3 | `frontend/src/components/chat/ChatSellerButton.vue:37` | Lấy `errors.product_id[0]` từ server. Thông điệp hiện có là tiếng Việt (`backend/app/Services/MessagingService.php:27`), nhưng lỗi validate mặc định của Laravel cho field này là tiếng Anh | ⚠️ |
| 4 | `frontend/src/views/ProductsView.vue:221,223` | Chip bộ lọc rơi về `value` (id số) nếu chưa tải được tên danh mục/shop | ⚠️ hiển thị id, không phải enum |
| 5 | `frontend/src/components/notifications/NotificationList.vue:31` | In `row.message` do backend soạn (hiện đều tiếng Việt) | ✅ không lộ |

- ✅ Backend đặt `APP_LOCALE=en` và phần lớn thông điệp `abort()` là tiếng Anh; frontend không hiển thị trực tiếp `response.data.message`, trừ một so khớp chuỗi cứng ở `frontend/src/views/SellerInventoryView.vue:117-120` (so với câu tiếng Việt `'Sản phẩm đang được giữ cho đấu giá.'` từ `backend/app/Services/FixedPriceEligibility.php:22`).

---

## 9. Security review

| Hạng mục | Kết quả | Bằng chứng / ghi chú |
| --- | --- | --- |
| IDOR trên route có `{id}` | **PASS** | Mọi route có tham số đều gọi policy hoặc lọc theo chủ sở hữu (cột "Policy/gate" mục 5). Ví dụ: `OrderController.php:27,34,41-42`; `SellerOrderController.php:41`; `AuctionCheckoutController.php:25`; `ConversationController.php:40`; `NotificationController.php:39`; `ReportController.php:64-65`; `CartService.php:99,146`; `ProductLocks.php:22`. Route con kiểm tra quan hệ cha–con: `ProductImageService.php:132,186`, `ProductVideoService.php:86,120`, `OrderController.php:42` |
| Lộ định danh ở API public | **FAIL (nhẹ)** | `GET /auctions/{id}/bids` (public) trả `user_id` thật của người đặt giá (`backend/app/Http/Resources/BidResource.php:12`); `AuctionResource.php:13` trả `seller_id`, `winner_user_id`. Kênh realtime thì dùng nhãn ẩn danh (`AuctionService.php:112`) → không nhất quán |
| Policy coverage | **PASS** | 15 policy trong `backend/app/Policies/`, dùng auto-discovery (không có `Gate::policy` trong `AppServiceProvider.php`). Không có policy cho Wishlist/Notification/Message/Bid: các route đó lọc theo `user_id`. Policy được gọi lại sau khi khóa row (ví dụ `ShopOrderService.php:25,37`) |
| FormRequest validation | **PASS** | 67 FormRequest; lớp cơ sở `DomainRequest` từ chối **mọi field không khai báo** (`backend/app/Http/Requests/Domain/DomainRequest.php:20-26`). Ngoại lệ dùng `Request` thô ở route chỉ đọc/không có body: `AddressController@show,destroy`, `NotificationController@count`, `ProfileController@show`, `AuthController@me,logout`, `AuctionCheckoutController@show`, các `show` nhận model |
| Mass assignment | **PASS** | `User::$fillable` không có `role`/`status` (`Models/User.php:20`); register `prohibited` các field nhạy cảm (`RegisterRequest.php:36-37`); model mới dùng `guarded = ['*']` + `forceFill`. Lưu ý: `Product`, `Shop`, `Review`, `ShopOrder`, `Payment` có `status` trong fillable — an toàn hiện tại vì chỉ nhận `validated()` và `DomainRequest` chặn field lạ |
| Rate limit | **PASS (có lỗ hổng nhỏ)** | Định nghĩa ở `AppServiceProvider.php:31-59`. Lỗ hổng: limiter `domain` là `Limit::none()` cho mọi GET đã đăng nhập (`:58`); `/health` và `/auth/me` không throttle; callback ZaloPay dùng chung `public-read` 120/phút/IP (`routes/api.php:157`) |
| CSRF / Sanctum stateful | **PASS** | `statefulApi()` (`bootstrap/app.php:27`); `EnsureSpaSession` yêu cầu request first-party có session trên mọi route ghi (`Http/Middleware/EnsureSpaSession.php:13`); chỉ miễn CSRF cho callback ZaloPay (`bootstrap/app.php:29`); guard `web`, không cấp API token (`User` không dùng `HasApiTokens`); session regenerate khi login/register (`AuthController.php:33,46`) |
| CORS | **PASS** | Origin lấy từ `FRONTEND_URLS`, không wildcard, `supports_credentials = true` (`config/cors.php:6,11`). `allowed_methods`/`allowed_headers` là `*` |
| Upload — ảnh | **PASS** | MIME + extension + ≤ 5 MiB + ≤ 20 MP (`UploadProductImagesRequest.php:16,27`); giải mã và mã hóa lại bằng GD, tên file UUID, đường dẫn do server tạo (`ProductImageService.php:31-54`); kiểm tra ước lượng bộ nhớ (`:26-29`) |
| Upload — video | **PASS (có lưu ý)** | MIME + extension + magic bytes + ≤ 50 MiB + tối đa 3 (`UploadProductVideoRequest.php:14`, `ProductVideoService.php:46-66`); tên UUID. Lưu ý: file lưu nguyên bản vào disk `public`, phục vụ tĩnh qua `/storage`, không transcode/quét nội dung, hiển thị ngay (READY) không qua duyệt |
| ZaloPay signature verify | **PASS** | HMAC-SHA256 key2, `hash_equals` (`ZaloPayGateway.php:88-90`); đối chiếu app_id/app_user/app_time/amount/currency (`ZaloPayService.php:82,127-137`); chống phát lại bằng UNIQUE `(method, gateway_transaction_id)` và bắt lỗi 1062 (`ZaloPayController.php:43-47`); `order_url` chỉ nhận https + host `*.zalopay.vn` (`ZaloPayGateway.php:31-38`). ⚠️ `callbackData` so `type !== 1` kiểu chặt (`:85`) → callback gửi `type` dạng chuỗi sẽ bị từ chối |
| Private channel auth | **PASS** | `routes/channels.php:7` (đúng id, ACTIVE, chưa xóa); middleware chỉ nhận `private-user.{n}` (`ValidateBroadcastSubscription.php:14`). Kênh `auction.*`, `video.*` là public theo thiết kế; payload không chứa dữ liệu riêng tư |
| Tiền tính lại ở server | **PASS** | Client chỉ gửi `address_id`, `payment_method`, kênh ưu tiên (`CheckoutRequest.php:14`); giá đọc lại từ row đã khóa, BCMath (`CheckoutService.php:75-77`); giá đấu giá = bid thắng (`AuctionCheckoutService.php:58`); DB có CHECK tổng tiền |
| Raw SQL / bind parameter | **PASS** | Mọi `whereRaw`/`selectRaw`/`orderByRaw` dùng `?` (`ProductQuery.php:37-40,82`, `SellerInventoryService.php:40,75,78`, `SellerDashboardService.php:87`, `AdminUserService.php:26`); ký tự `%`, `_`, `!` được escape. Chuỗi nội suy duy nhất là số nguyên đã ép kiểu trong migration (`…065953…:21-22`) |
| `APP_DEBUG` handling | **PASS** | Mặc định `false` (`config/app.php:42`); với `api/*`: lỗi ≥ 500 luôn trả `{"message":"Server Error"}`, các lỗi khác bị gỡ `exception`/`file`/`line`/`trace` (`bootstrap/app.php:35-51`). `.env` local đang bật `APP_DEBUG=true` |
| Lộ `admin_note` | **PASS** | Chỉ thêm khi URL khớp `api/v1/admin/reports*` (`ReportResource.php:12-17`), route đó nằm sau `EnsureAdmin`; thông báo `REPORT_RESULT` không chứa `admin_note` (`ReportService.php:78`); `NotificationResource.php:12` dùng whitelist |

Phát hiện bảo mật khác:

| # | Phát hiện | Bằng chứng | Mức |
| --- | --- | --- | --- |
| S1 | Mật khẩu demo viết cứng trong source | `backend/database/seeders/DemoUserSeeder.php:24` (literal, không ghi lại giá trị ở đây); tài khoản admin demo dùng chung mật khẩu này | ✅ thấp–trung bình (chỉ chạy ở local/test, `DatabaseSeeder.php:13`) |
| S2 | Disk `local` bật `serve => true` → framework đăng ký `GET`/`PUT /storage/{path}` | `backend/config/filesystems.php:33-36`; route có trong `route:list` | ⚠️ cần xác nhận có chủ ý (URL ký tạm) |
| S3 | Route dev `POST /_boost/browser-logs` | từ `laravel/boost` (require-dev), có trong `route:list` | ⚠️ chỉ tồn tại khi cài dev dependency |
| S4 | `.runtime/` chứa file `*-cookies.txt` của các lần kiểm thử | thư mục đã gitignore (`.gitignore`) nhưng vẫn nằm trên đĩa | ⚠️ không đọc nội dung |
| S5 | Không có xác minh email | xem mục 6 Auth | ✅ |
| S6 | Video và bình luận hiển thị ngay, không duyệt trước; admin không có route gỡ video | `ProductVideoService.php:70`; mục 5 | ✅ |
| S7 | Xử lý báo cáo không khóa/ẩn đối tượng | `ReportService.php:49-68` | ✅ |
| S8 | GET của người dùng đã đăng nhập không bị giới hạn tốc độ | `AppServiceProvider.php:58` | ✅ |

---

## 10. Testing

### 10.1 Backend

- **Không chạy** trong audit này (xem bảng đầu PART1). Số liệu dưới đây đếm bằng grep `public function test` / `#[Test]`.
- 39 file test (38 Feature + 1 Unit), **380 test method**. ⚠️ Log cũ `.runtime/d4-backend-full.log` (ghi ngày 2026-10-10) báo `phpunit passed, tests 399, assertions 3948` — chênh lệch nhiều khả năng do data provider; đây là số liệu lịch sử, không phải kết quả của audit.

| Module | File (`backend/tests/Feature/`) | Số test |
| --- | --- | --- |
| Auth | `AuthTest.php` 16, `PasswordResetTest.php` 11 | 27 |
| Profile/Address | `ProfileAddressTest.php` | 7 |
| Shop | `ShopManagementTest.php` | 10 |
| Category | `CategoryManagementTest.php` | 7 |
| Product + Image | `ProductManagementTest.php` 11, `ProductCatalogTest.php` 7, `ProductImagesTest.php` 8 | 26 |
| Search/Filter | `ProductDiscoveryTest.php` | 8 |
| Cart | `CartTest.php` | 15 |
| Checkout | `CheckoutTest.php` 8, `CheckoutConcurrencyTest.php` 5 | 13 |
| Order/COD | `OrderWorkflowTest.php` | 7 |
| ZaloPay | `ZaloPayTest.php` | 17 |
| Review | `ReviewTest.php` | 14 |
| Seller Dashboard | `SellerDashboardTest.php` | 13 |
| D4 Inventory | `SellerInventoryTest.php` | 15 |
| Admin | `AdminPhase11Test.php` | 18 |
| Messaging | `MessagingTest.php` | 13 |
| Auction | `AuctionTest.php` 17, `AuctionConcurrencyTest.php` 12, `AuctionSettlementTest.php` 16, `AuctionRealtimeTest.php` 10, `AuctionNotificationTest.php` 14 | 69 |
| Video | `ProductVideosTest.php` 16, `VideoDiscoveryTest.php` 5, `VideoCommentsTest.php` 13 | 34 |
| Wishlist | `WishlistTest.php` | 9 |
| Reports | `ReportTest.php` 13, `ReportConcurrencyTest.php` 2 | 15 |
| Seeder | `DemoSeederTest.php` 1, `FinalDemoSeederTest.php` 3, `DemoMarketplacePackTest.php` 8 | 12 |
| Schema/hạ tầng | `DatabaseSchemaTest.php` 19, `SecurityPhase13Test.php` 4, `DeploymentPhase14Test.php` 3, `HealthTest.php` 3, `ExampleTest.php` 1 (+ `Unit/ExampleTest.php` 1) | 31 |

- Test đồng thời thật chạy bằng tiến trình con: `backend/tests/Support/checkout-worker.php`, `auction-worker.php`, `report-worker.php`.
- 29 file dùng `RefreshDatabase`, 6 file dùng `DatabaseMigrations`. 5 file dùng `Storage::fake`.

### 10.2 Cấu hình DB test — đánh giá an toàn

| Lớp bảo vệ | Bằng chứng | Kết luận |
| --- | --- | --- |
| `phpunit.xml` ép `APP_ENV=testing`, `DB_CONNECTION=mysql`, `DB_DATABASE=secondhand_marketplace_test`, `DB_URL=""` — đều `force="true"` | `backend/phpunit.xml:21,26-28` | ✅ |
| `TestCase::createApplication` ném lỗi nếu không phải môi trường `testing`, driver khác `mysql`, hoặc tên DB (cả theo config lẫn `SELECT DATABASE()`) khác `secondhand_marketplace_test` | `backend/tests/TestCase.php:13-18` | ✅ |
| Worker tiến trình con có cùng kiểm tra | `backend/tests/Support/checkout-worker.php:23-24` | ✅ (đã xem 1/3 worker) |
| `DB_DATABASE` trong `.env` khác `secondhand_marketplace_test`; `.env.testing` trùng tên DB test | so sánh chuỗi, không in giá trị | ✅ |

- Kết luận: **AN TOÀN về mặt tách DB** (MySQL riêng, không phải sqlite `:memory:`). Lưu ý: DB test dùng **cùng MySQL server và cùng tài khoản** với DB dev (`DB_HOST`/`DB_USERNAME` không bị ép trong `phpunit.xml`), và `QUEUE_CONNECTION=sync`, `BROADCAST_CONNECTION=null`, `MAIL_MAILER=array` khi test (`phpunit.xml:24,29-30`).

### 10.3 Frontend (đã chạy)

| Lệnh | Kết quả |
| --- | --- |
| `npx vitest run` | ✅ **33 file pass, 413 test pass**, 0 fail, 42,59 s |
| `npx oxlint .` | ✅ exit 0, không có chẩn đoán |
| `npx eslint .` | ✅ exit 0, không có chẩn đoán |
| `npm run build` | ✅ thành công (~1,9 s). Chunk lớn nhất: `SellerDashboardView` 161 kB (gzip 57 kB, chứa Chart.js), `index` 100 kB, `auctionEcho` 95 kB |

Số test theo file spec (`frontend/src/__tests__/`, đếm `it(`/`test(` — tổng grep 383; vitest báo 413 do `it.each`):
`AuctionUI` 29 · `ProductPhase6` 20 · `CartPhase7` 18 · `OrderPhase8` 18 · `VideoDiscovery` 18 · `AdminPhase11` 17 · `DomainViews` 17 · `Notifications` 17 · `PaymentPhase12` 16 · `ReviewPhase9` 16 · `AuctionCheckout` 15 · `ProductVideos` 15 · `VideoComments` 15 · `SellerInventory` 14 · `SellerDashboardPhase10` 13 · `auth` 13 · `QuickListing` 12 · `Reports` 12 · `AuctionRealtime` 10 · `Messaging` 10 · `Wishlist` 9 · `AuctionEcho` 8 · `PasswordRecovery` 8 · `ProductDiscovery` 8 · `VietnameseLocalization` 7 · `AuthForm` 5 · `domainApi` 5 · `AccountSellerUI` 4 · `AdminUI` 4 · `VisualConsistency` 4 · `App` 2 · `FinalPolish` 2 · `HomeScrollStory` 2.

### 10.4 Chỗ thiếu test

| Khu vực | Tình trạng |
| --- | --- |
| End-to-end trình duyệt | ❓ Không có thư mục `e2e/` trong repo (chỉ được `exclude` ở `frontend/vitest.config.js:12`); các script `.runtime/*-browser.mjs` nằm ngoài git |
| Unit test backend | ✅ chỉ có `tests/Unit/ExampleTest.php`; toàn bộ là feature test trên MySQL |
| Notifications cho báo cáo (`REPORT_RESULT`) | ⚠️ không có file riêng; chưa xác minh `ReportTest.php` có bao phủ hay không |
| Lệnh `zalopay:reconcile` | ⚠️ không có file riêng; nếu có thì nằm trong `ZaloPayTest.php` (chưa đọc nội dung test) |
| Tương tác sản phẩm ↔ đấu giá (ẩn/xóa/sửa nội dung khi đang LIVE) | ⚠️ `SellerInventoryTest.php:189` chỉ bao phủ nhánh khóa stock; chưa thấy test cho hide/delete trong lúc đấu giá |
| Hết hạn checkout của người thắng | ✅ có `AuctionSettlementTest.php` (16 test); ⚠️ chưa xác minh từng ca |
| Rate limit | ⚠️ `SecurityPhase13Test.php` chỉ 4 test |

---

## 11. Đối chiếu với mô tả dự án

| # | Khẳng định | Kết quả | Bằng chứng |
| --- | --- | --- | --- |
| 1 | Chỉ có role USER, ADMIN; seller = user sở hữu shop; tối đa 1 shop/user | ✅ khớp | `Enums/UserRole.php:7-8`; CHECK `users_role_enum_check`; `ProductPolicy.php:20,25`; UNIQUE `shops.owner_id`; `ShopService.php:30` |
| 2 | Category tối đa 3 cấp, chống cycle, chặn xóa khi có con hoặc product | ✅ khớp | `CategoryService.php:29-40,79` |
| 3 | Address: một mặc định, đổi trong transaction | ✅ khớp | `AddressService.php:21-28,32-41,46-57` (transaction + khóa row user). Không có ràng buộc DB cho "chỉ một default" |
| 4 | Cart quantity 1–99, không vượt stock, không mua hàng shop mình, checkout đọc lại giá | ✅ khớp | `CartService.php:118,128-131`; CHECK `cart_items_quantity_check`; `CheckoutService.php:70-71,75` |
| 5 | Checkout: transaction + row lock + idempotency + BCMath, không float | ✅ khớp | `CheckoutService.php:34-61,39-43,75-77`; `OrderLocks.php:50-58`. Float duy nhất ở backend là làm tròn điểm rating (`ProductResource.php:16`) |
| 6 | Snapshot order (tên, giá, địa chỉ) | ✅ khớp | `OrderCreator.php:22-26`; `CheckoutService.php:76` (thêm cả đường dẫn ảnh chính) |
| 7 | Auction bid: `SELECT FOR UPDATE`, server time, bid ≥ minimum next bid | ✅ khớp | `AuctionService.php:97,101-105` |
| 8 | Lệnh `auctions:sync` + scheduler | ✅ khớp | `Console/Commands/SyncAuctions.php:15`; `routes/console.php:13`; output `schedule:list` |
| 9 | Settlement NONE / PENDING_CHECKOUT / ORDER_CREATED / EXPIRED, hạn 24h, không chuyển cho người thứ hai | ⚠️ lệch một phần | Tên thực tế: `null` / `NO_WINNER` / `AWAITING_CHECKOUT` / `ORDER_CREATED` / `CHECKOUT_EXPIRED` (`Models/Auction.php:24-36`); là giá trị tính toán, không lưu, không có enum. Hạn 24h ✅ (`config/auctions.php:3`, `AuctionService.php:171`, cấu hình được). Không chuyển người thứ hai ✅ (không có code) |
| 10 | Auction checkout dùng lại Order, quantity 1, giá = winning bid, retry không tạo trùng | ✅ khớp | `AuctionCheckoutService.php:42-46,58,63-65`; UNIQUE `auctions.winner_order_id`, `order_items.auction_id` |
| 11 | Broadcast sau commit | ✅ khớp | `Events/AuctionBroadcastEvent.php:12`, `Events/VideoCommentBroadcastEvent.php:11` (`ShouldDispatchAfterCommit`); `VideoCommentService.php:66,84` (`DB::afterCommit`) |
| 12 | Events: AuctionBidPlaced, AuctionStatusChanged, AuctionOutbid | ✅ khớp (có thêm) | `app/Events/`; thêm `NotificationCreated`, `VideoCommentCreated`, `VideoCommentHidden` |
| 13 | Channels: `auction.{id}`, `private-user.{id}`, `video.{id}` | ⚠️ lệch một phần | Tên kênh khớp (`AuctionBroadcastEvent.php:18`, `AuctionOutbid.php:16`, `VideoCommentBroadcastEvent.php:17`). Nhưng `routes/channels.php:7` chỉ đăng ký `user.{userId}`; hai kênh còn lại là public, không qua authorization |
| 14 | Video: 3 video/product, 50MiB, MP4/WebM | ✅ khớp | `config/product-videos.php:3`; `UploadProductVideoRequest.php:14`; `ProductVideoService.php:54-56,65-66` |
| 15 | Review: phải COMPLETED, 1 review/OrderItem, chỉ PUBLISHED tính rating | ✅ khớp | `ReviewService.php:88,97,23-37`; UNIQUE `reviews.order_item_id` |
| 16 | Revenue = SUM(subtotal) của ShopOrder COMPLETED theo completed_at, timezone VN | ✅ khớp | `SellerDashboardService.php:30-32,49,87`; `SellerReportRange.php:9` |
| 17 | Wishlist unique (user_id, product_id), idempotent, annotate `is_wishlisted` | ✅ khớp | migration `…161815…:16`; `WishlistController.php:35,43`; `ProductQuery.php:24-27`; `ProductResource.php:13` |
| 18 | Report targets PRODUCT / SHOP / VIDEO_COMMENT, 8 reasons, 4 states, admin_note không lộ | ✅ khớp | `Enums/ReportTargetType.php`, `ReportReason.php` (8), `ReportStatus.php` (4); `ReportResource.php:12-17` |
| 19 | Messaging = REST polling | ✅ khớp | `frontend/src/composables/useMessaging.js:178-187` (4 giây); backend không phát event khi gửi tin (`MessagingService.php:71-81`) |
| 20 | Search debounce 300ms, URL state | ✅ khớp | Debounce 300 ms cho **gợi ý** (`frontend/src/components/ProductSearch.vue:26-53`); bộ lọc/từ khóa/trang đồng bộ qua query string (`frontend/src/views/ProductsView.vue:54-56,103,106`; `frontend/src/utils/discoveryQuery.js`). Danh sách sản phẩm chỉ tải lại khi submit/đổi URL, không debounce theo từng phím |
| 21 | Seeder additive + idempotent, đọc `DEMO_PASSWORD` từ env | ⚠️ lệch một phần | `FinalDemoSeeder` ✅ (`:59-61,75-82,113,239`; `config/demo.php:4`). `DemoUserSeeder` additive nhưng dùng mật khẩu literal (`DemoUserSeeder.php:17-24`). `FinalDemoSeeder` không nằm trong `DatabaseSeeder` |

---

## 12. Trạng thái D4 – Seller Inventory & Action Center

**Kết luận: ĐÃ CÓ** (đầy đủ backend, frontend, test, dữ liệu demo, tài liệu). Toàn bộ là file **chưa commit**.

| Hạng mục | Trạng thái | Bằng chứng |
| --- | --- | --- |
| Route API `/seller/inventory` | ✅ có | `backend/routes/api.php:139` → `SellerInventoryController@index` |
| Route API `/seller/action-center` | ✅ có | `backend/routes/api.php:140` → `SellerInventoryController@summary` |
| Service | ✅ có | `backend/app/Services/SellerInventoryService.php` (`inventory` :24-50, `actionableOrders` :52-59, `liveAuctions` :61-70, `summary` :72-86) |
| Low-stock | ✅ có | ngưỡng `SELLER_LOW_STOCK_THRESHOLD` mặc định 5 (`backend/config/seller-inventory.php:4`); bộ lọc `low_stock`/`out_of_stock` (`SellerInventoryService.php:28-34`); `inventory_state` (`ManagedProductResource.php:15`) |
| Quick stock update | ✅ có, **dùng lại** `PATCH /seller/products/{product}` với `stock_quantity` + `expected_updated_at` | `UpdateProductRequest.php:20`; `ProductService.php:46-47`; UI `frontend/src/views/SellerInventoryView.vue:96-129` |
| Cờ cho UI | ✅ có | `stock_editable`, `stock_edit_reason` (`ManagedProductResource.php:16-17`) |
| Bộ lọc đơn/đấu giá cho action center | ✅ có | `SellerOrderController.php:23-24` (`action_required`); `AuctionController.php:45` (`live_now`, `ending_soon`) |
| Route Vue `/seller/inventory` | ✅ có (khai báo trùng 2 lần) | `frontend/src/router/index.js:8-18` |
| View + component | ✅ có | `frontend/src/views/SellerInventoryView.vue`; `frontend/src/components/seller/SellerActionCenter.vue` (gắn vào `SellerDashboardView.vue:96`), `InventoryBadge.vue`; menu `SellerShell.vue:18`; API `frontend/src/api/inventory.api.js` |
| Test | ✅ có | `backend/tests/Feature/SellerInventoryTest.php` (15 test, gồm IDOR `:176`, khóa stock theo claim `:189,205`, số query không tăng theo sản phẩm `:223`); `frontend/src/__tests__/SellerInventory.spec.js` (14) |
| Dữ liệu demo | ✅ có | `FinalDemoSeeder.php:307-343` |
| Tài liệu | ✅ có | `docs/extension-d4-seller-inventory-verification.md` |

### Đánh giá điểm tích hợp

**Stock lock**

- ✅ Sửa stock đi qua `ProductLocks::lock` (khóa users → shop → toàn bộ categories → product, `backend/app/Services/ProductLocks.php:14-24`), cùng thứ tự khóa với checkout (`CheckoutService.php:35-61`) nên không đảo thứ tự khóa trên `products`.
- ✅ Chống ghi đè: `expected_updated_at` phải **bằng chính xác** `updated_at` (độ chính xác micro giây, `ProductService.php:47`). Bất kỳ thay đổi nào trên sản phẩm giữa lúc tải và lúc lưu (có người vừa mua, đổi ảnh…) đều trả 409; UI yêu cầu tải lại (`SellerInventoryView.vue:121-122`).
- ⚠️ Giá trị gửi lên là số tuyệt đối, không phải delta: sau khi tải lại, seller phải tự tính lại số đã bán.
- ✅ **Tác dụng phụ quan trọng:** sửa stock trên sản phẩm ACTIVE (stock mới > 0) hoặc SOLD → trạng thái `PENDING` (`ProductService.php:68`); stock = 0 trên ACTIVE → `SOLD` (`:66-67`). UI có thông báo (`SellerInventoryView.vue:112-113`). Hệ quả: "quick stock update" làm sản phẩm rời sàn cho tới khi admin duyệt.
- ⚠️ Khi shop không ACTIVE/PENDING, seller chỉ đọc được, không sửa (`ProductPolicy.php:35`; test `SellerInventoryTest.php:155`).

**Auction LIVE / chờ checkout so với sửa stock**

- ✅ Sửa stock bị chặn (409, thông điệp tiếng Việt) khi sản phẩm có claim: phiên SCHEDULED, LIVE, hoặc ENDED có người thắng chưa tạo đơn và còn hạn (`ProductService.php:63-64`; `FixedPriceEligibility.php:11-23`). Claim hết hạn hoặc phiên bị hủy thì mở lại (test `:205`).
- ✅ Việc kiểm tra dùng `lockForUpdate` trên row auction (`FixedPriceEligibility.php:22`) trong cùng transaction.
- ✅ **Lỗ hổng còn lại (không thuộc riêng D4 nhưng D4 đặt UI lên trên):** `hide`, `delete`, sửa nội dung, đổi ảnh **không** gọi `assertAvailable` (`ProductService.php:97-114,137-143`; `ProductImageService.php:88-95`). Seller có thể rút sản phẩm khỏi ACTIVE trong lúc LIVE hoặc khi người thắng đang chờ → `AuctionCheckoutService.php:53` trả 409 cho người thắng.
- ⚠️ Trên trang tồn kho, `stock_editable` dựa vào quan hệ `auctionClaim` đã eager-load (`ProductQuery.php:29`; `ManagedProductResource.php:13`). Nếu resource được dùng ở nơi không load quan hệ này thì cờ sẽ là "sửa được" dù có claim; server vẫn chặn.
- ⚠️ Người thắng checkout chỉ trừ 1 đơn vị; trong suốt thời gian claim, **toàn bộ** tồn kho còn lại của sản phẩm cũng không bán được theo giá cố định (`CartService.php:126`, `CheckoutService.php:72`).

**Query aggregate**

- ✅ `summary()` dùng 3 truy vấn gộp, không lặp theo sản phẩm: 1 `SELECT SUM(…)` có điều kiện trên `products` (`SellerInventoryService.php:75`), 1 `COUNT`/`SUM` trên `auctions` (`:77-78`), 1 `COUNT` trên `shop_orders` với `whereHas` (`:85`).
- ✅ Soft-deleted bị loại nhờ global scope của `Product`; cảnh báo tồn kho chỉ tính ACTIVE/SOLD và trả 0 khi shop không ACTIVE (`:82-83`).
- ⚠️ Index: bộ lọc tồn kho lọc theo `(shop_id, status)` rồi `stock_quantity` — index hiện có `(shop_id, status, deleted_at, created_at, id)` dùng được cho phần đầu; không có index trên `stock_quantity`, sắp xếp `stock_asc/desc` và `updated_at` sẽ filesort (chấp nhận được ở quy mô một shop).
- ⚠️ Danh sách tồn kho gọi `ProductQuery::related()` (`SellerInventoryController.php:20`) → mỗi dòng kèm 2 subquery review (`ReviewService.php:34-37`), `withExists` wishlist và eager-load `auctionClaim`/`shop`/`category`/`images`: nặng hơn mức cần cho trang tồn kho.
- ⚠️ `ManagedProductResource` gọi `can('update')` 3 lần và `can('moderate')` 1 lần cho mỗi dòng (`:16-19`); không phát sinh query vì `shop` đã load, nhưng lặp lại.
- ⚠️ Mốc "sắp kết thúc" và "đang LIVE" dùng `now()` của server (`:63,76`), nhất quán với đấu giá; không phụ thuộc cột `status` đã được scheduler cập nhật hay chưa đối với mốc thời gian, nhưng vẫn yêu cầu `status = 'LIVE'` (`:64`) → phiên SCHEDULED đã tới giờ mà scheduler chưa chạy sẽ không được đếm.

---

## 13. Code smell & rủi ro

### 13.1 TODO / FIXME / HACK

- ✅ **Không tìm thấy** chuỗi `TODO`, `FIXME`, `HACK`, `XXX` nào trong `backend/app`, `backend/routes`, `backend/database`, `backend/config`, `backend/tests`, `frontend/src`.

### 13.2 Code chết / không dùng

| Mục | Bằng chứng |
| --- | --- |
| Store mẫu `counter.js` không được import | `frontend/src/stores/counter.js` (grep không có nơi dùng ngoài chính nó) |
| Route Vue trùng lặp | `frontend/src/router/index.js:8-18` |
| `welcome.blade.php` (72 KB) không route nào trả về | `backend/resources/views/welcome.blade.php`; `backend/routes/web.php:5-7` trả JSON |
| Lệnh `inspire` mặc định | `backend/routes/console.php:7-9` |
| `ExampleTest` mặc định (2 file) | `backend/tests/Feature/ExampleTest.php`, `backend/tests/Unit/ExampleTest.php` |
| Enum/giá trị không có luồng dùng | `PaymentMethod::VNPAY`, `PaymentStatus::REFUNDED`, `ProductVideoStatus::Processing`/`Failed`; cột `product_videos.duration_seconds`, `poster_path`, `users.avatar_path`, `shops.logo_path`, `product_images.alt_text`, `orders.discount_amount`/`shipping_fee` (luôn 0), `users.email_verified_at` không có luồng ghi |
| Bảng `personal_access_tokens` không dùng | `User` không có `HasApiTokens` |
| Scaffold Vite phía backend | `backend/vite.config.js`, `backend/package.json`, `backend/resources/js/app.js`, `backend/resources/css/app.css` (backend chỉ phục vụ API) |
| Tham chiếu `e2e/**` không tồn tại | `frontend/vitest.config.js:12` |
| File Docker đã xóa khỏi working tree nhưng còn trong commit | `git status` (mục 1) |

- ✅ Mọi component/composable/util/API module khác trong `frontend/src` đều có ít nhất một nơi import ngoài test.
- ⚠️ Chưa kiểm tra từng hàm `export` trong `frontend/src/api/*.js` có được gọi hay không.

### 13.3 Nghi ngờ N+1 / truy vấn nặng

| # | Vị trí | Mô tả |
| --- | --- | --- |
| 1 | `backend/app/Services/OrderLocks.php:29`, `ProductLocks.php:19`, `AuctionService.php:47`, `CartService.php:119`, `VideoCommentService.php:43`, `CategoryService.php:50,76`, `ProductService.php:29` | `SELECT * FROM categories … FOR UPDATE` toàn bảng trong **mọi** giao dịch ghi (bid, thêm giỏ, checkout, sửa sản phẩm, bình luận video, hủy đơn). Tuần tự hóa toàn bộ thao tác ghi của hệ thống và tải cả bảng mỗi lần |
| 2 | `CheckoutService.php:72-73` | Trong vòng lặp theo cart item: 1 truy vấn claim đấu giá + 1 truy vấn ảnh chính cho mỗi item |
| 3 | `AuctionNotificationService.php:52-54` | Mỗi người từng đặt giá một `firstOrCreate` + broadcast đồng bộ, chạy **bên trong** transaction `finalize()` đang giữ khóa auction; truy cập lười `$auction->product`, `$auction->winningBid` (`:46-47`) |
| 4 | `AuctionBroadcastEvent.php:31-36` | Mỗi event bid chạy thêm 2 truy vấn kiểm tra public trước khi phát |
| 5 | `CartService.php:37-40` | `GET /cart` mở transaction, khóa ghi row user và cart, có thể tạo cart → đọc mà khóa ghi |
| 6 | `ProductQuery.php:29` + `ReviewService.php:23-37` | Mỗi dòng sản phẩm kèm 2 subquery tương quan nhiều tầng `whereHas` để tính rating; áp dụng cho mọi danh sách (public, seller, admin, wishlist, tồn kho) |
| 7 | `ProductQuery.php:37-40` | Tìm kiếm `LIKE '%…%'` trên `name`, `description` (TEXT) và hai `orWhereHas`; không dùng được index |
| 8 | `MessagingService.php:51-60` | Đếm chưa đọc bằng subquery tương quan cho mỗi hội thoại, client gọi lại mỗi 4 giây |
| 9 | `AdminDashboardService.php:23` | `completed_orders`/`cancelled_orders` đếm bằng `whereHas` + `whereDoesntHave` trên toàn bộ `orders` |
| 10 | `SlugGenerator.php:14` | Vòng lặp `exists()` tăng hậu tố; tên trùng nhiều → nhiều truy vấn |
| 11 | `SyncAuctions.php:45-47` | Mỗi phiên cần nhắc một transaction riêng; mỗi phiên đến hạn một lần khóa toàn bảng `categories` (qua `start()`) |

### 13.4 Logic trùng lặp

| Mục | Vị trí |
| --- | --- |
| Kiểm tra "user ACTIVE và chưa xóa" lặp lại ở hầu hết policy và service | `AddressPolicy.php:13`, `AuctionPolicy.php:13`, `CartItemPolicy.php:13`, `ConversationPolicy.php:13`, `OrderPolicy.php:13`, `ProductPolicy.php:15`, `ReportPolicy.php:13`, `ReviewPolicy.php:14,19`, `ShopOrderPolicy.php:13,23`, `ShopPolicy.php:15`, `VideoCommentPolicy.php:16,21`, `CartService.php:25`, `OrderLocks.php:24` |
| Escape `LIKE` (`str_replace(['!','%','_'], …)`) chép lại 7 lần | `AdminShopController.php:26`, `PublicShopController.php:20`, `PublicProductController.php:32`, `ProductQuery.php:35,81`, `SellerInventoryService.php:39`, `AdminUserService.php:25` |
| Dãy khóa users → shop → categories → product viết lại ở 4 nơi | `ProductLocks.php:14-24`, `AuctionService.php:41-63`, `CartService.php:113-120`, `VideoCommentService.php:37-50` |
| Duyệt cây danh mục "tối đa 3 cấp, mọi tổ tiên ACTIVE" có 3 cài đặt | `CategoryService.php:18-27` (SQL), `OrderLocks.php:37-45` (PHP), `AuctionService.php:52-61` (PHP) |
| Trim Unicode bằng regex chép lại | `SendMessageRequest.php:15`, `StoreReportRequest.php:14`, `StoreVideoCommentRequest.php:17`, `VideoCommentService.php:55` |
| Kiểm tra `Idempotency-Key` là UUID | `CheckoutRequest.php:17-23`, `RetryZaloPayRequest.php:17-23` |
| Rule mật khẩu | `RegisterRequest.php:29-37`, `ResetPasswordRequest.php:29-37` |
| Service tự validate lại bằng rule của FormRequest | `AuctionService.php:68,93`, `ProductVideoService.php:45`, `VideoCommentService.php:56` (có chủ ý, vì seeder gọi thẳng service) |
| Chuẩn hóa `normalize()` ảnh và video | `ProductImageService.php:79-86`, `ProductVideoService.php:24-35` |
| Bảng hành động seller theo trạng thái có ở cả hai phía | `ProductService.php:102` và `frontend/src/composables/productPresentation.js:19-26` |
| Một controller phục vụ cả user và admin bằng so khớp URL | `ReportController.php:29,59`; tương tự `ReportResource.php:12`, `ProductVideoResource.php:14`, `ShopOrderResource.php:18`, `ShopListRequest.php:12`, `AuctionListRequest.php:12` — quyết định theo `$request->is(...)` |

### 13.5 Float cho tiền

| Vị trí | Đánh giá |
| --- | --- |
| Backend | ✅ Không dùng float cho tiền. Toàn bộ là chuỗi decimal + BCMath; cast `decimal:2`. `(float)` duy nhất là điểm rating trung bình (`backend/app/Http/Resources/ProductResource.php:16`) |
| `frontend/src/components/RevenueChart.vue:13` | ⚠️ `Number(row.revenue)` để đưa vào Chart.js — chỉ dùng vẽ biểu đồ; mất chính xác khi vượt 2^53 |
| `frontend/src/views/AuctionArenaView.vue:15,149,186` | ✅ giá đặt được parse thành "cents" nguyên (`amountCents`, `parseBidInput`), không dùng float |
| `frontend/src/composables/productPresentation.js:10-16` | ✅ định dạng bằng `BigInt` |
| `frontend/src/utils/discoveryQuery.js:3-4` | ✅ so sánh khoảng giá bằng `BigInt` |

### 13.6 `now()` phía client / múi giờ

| # | Vị trí | Vấn đề |
| --- | --- | --- |
| 1 | `frontend/src/views/SellerAuctionsView.vue:65-68` | `canCancel` so `ends_at` với `Date.now()` của máy khách (chỉ ảnh hưởng hiển thị nút; server vẫn kiểm tra ở `AuctionService.php:153`) |
| 2 | `frontend/src/composables/dashboardPresentation.js:1` | Khoảng ngày mặc định lấy `new Date()` của máy khách rồi quy về `Asia/Ho_Chi_Minh` — đúng múi giờ, nhưng phụ thuộc đồng hồ máy khách |
| 3 | `backend/app/Http/Controllers/Api/V1/SellerOrderController.php:29-33` | Bộ lọc `from`/`to` của đơn seller dùng ranh giới ngày **UTC**, trong khi dashboard doanh thu dùng ngày **VN** (`SellerReportRange.php:9`) → lệch 7 giờ giữa hai màn hình |
| 4 | `backend/app/Http/Controllers/Api/V1/ReportController.php:44-48` | Bộ lọc ngày của báo cáo cũng theo UTC |
| 5 | `backend/app/Services/SellerDashboardService.php:87` | Offset `INTERVAL 7 HOUR` viết cứng, tách rời hằng `SellerReportRange::TIMEZONE` |
| 6 | `backend/app/Services/ZaloPayService.php:25` | Tiền tố `app_trans_id` dùng ngày VN (đúng yêu cầu ZaloPay); `expires_at` dùng UTC — nhất quán nội bộ |
| 7 | `frontend/src/views/SellerInventoryView.vue:140` và các nơi dùng `toLocaleString('vi-VN')` | Hiển thị theo múi giờ của **trình duyệt**, không ép `Asia/Ho_Chi_Minh` |
| 8 | Đồng hồ đấu giá | ✅ không dùng `Date.now()` trực tiếp cho đếm ngược; đồng bộ theo `server_time` (`frontend/src/composables/auctionTimeSync.js:10-23`) |
| 9 | `config/app.php:68` | Timezone ứng dụng `UTC`; cột `conversations.last_message_at`, `reports.handled_at`, `notifications.*` dùng độ chính xác giây, các bảng nghiệp vụ cũ dùng micro giây |

### 13.7 Rủi ro nghiệp vụ / vận hành khác

| # | Rủi ro | Bằng chứng |
| --- | --- | --- |
| R1 | Phần lớn code chưa commit, chỉ 1 commit | `git log`, `git status` (mục 1) |
| R2 | Ẩn/xóa/sửa sản phẩm khi đang đấu giá hoặc chờ người thắng checkout | `ProductService.php:97-114,137-143`; `AuctionCheckoutService.php:53` |
| R3 | Nhập thêm hàng đưa sản phẩm ACTIVE về chờ duyệt | `ProductService.php:68` |
| R4 | Không có hoàn tiền; đơn ZaloPay đã trả tiền không hủy được; `reconciliation_required` không có công cụ xử lý | `ShopOrderService.php:64`; `ZaloPayService.php:161`; mục 5 |
| R5 | Đấu giá/ZaloPay phụ thuộc `schedule:work`/cron; `QUEUE_CONNECTION=sync`; broadcast đồng bộ trong request (timeout 2 s) | `routes/console.php:11,13`; `config/broadcasting.php:8`; `AuctionBroadcastEvent.php:12` |
| R6 | Hết hạn checkout không có hệ quả: không thông báo, không phạt, không trao lại; seller không biết ngoài việc xem trạng thái | `Models/Auction.php:36`; `AuctionNotificationService.php` (không có loại "expired") |
| R7 | File video/ảnh được ghi trước transaction; nếu tiến trình chết giữa chừng sẽ để lại file mồ côi | `ProductVideoService.php:58-78`; `ProductImageService.php:102-124` |
| R8 | Xóa mềm sản phẩm không dọn `cart_items`, `wishlists`, hội thoại; phụ thuộc bộ lọc "public" khi đọc | `ProductService.php:137-143`; `CartService.php:74-77` |
| R9 | Khóa/đình chỉ user hoặc shop không tự xử lý đơn, phiên đấu giá đang mở | `AdminUserService.php:45`; `ShopService.php:82` |
| R10 | Thứ tự khóa trong `ShopOrderService` (users → order → shop_orders → shops → categories → products) khác `CheckoutService` (users → cart → order → address → cart_items → shops → categories → products) ở vị trí `orders`/`shops` | `ShopOrderService.php:29-35,57-59`; `CheckoutService.php:35-61` — ⚠️ có `OrderLocks::transaction` bắt deadlock trả 409, chưa xác minh có xảy ra thực tế |
| R11 | `finalize()` chọn người thắng theo `amount` cao nhất chứ không theo bid mới nhất; do bid luôn tăng nên trùng nhau, nhưng là hai tiêu chí khác với `placeBid` (dùng bid có `id` lớn nhất làm `previous`) | `AuctionService.php:103,170` |
| R12 | Thông báo chỉ có cho đấu giá và kết quả báo cáo | mục 6 Notifications |

---

## 14. Danh sách câu hỏi mở

1. **Git:** vì sao toàn bộ phần sau MVP (đấu giá, video, tin nhắn, wishlist, báo cáo, D4) chưa commit? Có nhánh/remote nào khác không? Việc xóa toàn bộ file Docker là có chủ ý (chuyển sang XAMPP/Herd)?
2. **Settlement:** tài liệu ghi `NONE / PENDING_CHECKOUT / ORDER_CREATED / EXPIRED`, code là `NO_WINNER / AWAITING_CHECKOUT / ORDER_CREATED / CHECKOUT_EXPIRED` và không lưu DB. Bên nào là chuẩn? Có cần enum + cột trạng thái không?
3. **Sản phẩm đang đấu giá:** có chủ ý cho phép seller `hide`/`delete`/sửa nội dung/đổi ảnh khi phiên LIVE hoặc khi người thắng đang chờ checkout không? Nếu không, quy tắc mong muốn là gì (chặn, hay tự hủy phiên)?
4. **D4 – quick stock:** việc nhập thêm hàng đưa sản phẩm ACTIVE về `PENDING` là yêu cầu nghiệp vụ hay tác dụng phụ của rule duyệt lại có sẵn?
5. **Hết hạn checkout đấu giá:** có cần thông báo cho seller/người thắng, ghi nhận vi phạm, hoặc mở lại phiên không?
6. **Hủy đơn đấu giá:** khi ShopOrder của đơn đấu giá bị hủy (buyer được tự hủy ở PENDING/CONFIRMED), phiên đấu giá và sản phẩm nên xử lý thế nào?
7. **ZaloPay:** quy trình xử lý `reconciliation_required` và hoàn tiền nằm ở đâu (thủ công ngoài hệ thống)? `ZALOPAY_CALLBACK_URL` ở môi trường demo có truy cập được từ internet không, hay chỉ dựa vào query/reconcile?
8. **ZaloPay callback:** ZaloPay sandbox gửi `type` dạng số hay chuỗi? Code yêu cầu đúng kiểu integer `1` (`ZaloPayGateway.php:85`).
9. **Scheduler:** môi trường demo chạy `php artisan schedule:work` hay cron? Có giám sát khi scheduler dừng không?
10. **Khóa toàn bảng `categories`:** là lựa chọn có chủ ý để đơn giản hóa tính đúng đắn? Tải dự kiến (số bid đồng thời) là bao nhiêu?
11. **Quyền riêng tư:** việc API public trả `user_id` của người đặt giá, `winner_user_id`, `seller_id` có được chấp nhận không?
12. **Kiểm duyệt:** video và bình luận video lên ngay không qua duyệt, admin không có route gỡ video; xử lý báo cáo không tác động tới đối tượng. Đây là phạm vi đã chốt?
13. **Múi giờ:** bộ lọc ngày của đơn seller và báo cáo dùng UTC, dashboard doanh thu dùng giờ VN — lệch có chủ ý không?
14. **Email:** có cần xác minh email không? SMTP ở demo dùng gì (chỉ thấy tên biến `MAIL_*`)?
15. **`DemoUserSeeder`:** mật khẩu literal trong source có được chấp nhận cho repo public/nộp bài không, hay cần chuyển sang `DEMO_PASSWORD` như `FinalDemoSeeder`?
16. **Disk `local` với `serve => true`** và route `PUT /storage/{path}`: có nhu cầu sử dụng không?
17. **Phiên bản PHP mục tiêu:** `composer.json` ghi `^8.3` nhưng vendor hiện yêu cầu ≥ 8.4.1 và máy có XAMPP PHP 8.0. Môi trường chấm/demo dùng PHP nào?
18. **Backend test:** có muốn chạy lại `php artisan test` trên `secondhand_marketplace_test` để xác nhận con số 380 method / 399 test trong log cũ không? (Audit này không chạy.)
19. **Phí vận chuyển / giảm giá:** hai cột luôn bằng 0 — ngoài phạm vi hay chưa làm?
20. **Thông báo:** có cần thông báo cho đơn hàng thường, duyệt shop/sản phẩm, tin nhắn mới không?
