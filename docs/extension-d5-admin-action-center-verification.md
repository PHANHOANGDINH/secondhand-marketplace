# Extension D5 — Admin Action Center (+ wave 3 moderation gaps): báo cáo kiểm chứng

Ngày thực hiện: 12–13/10/2026. Tiếp nối `docs/fix-wave-1-2-verification.md`.
Môi trường: PHP 8.4.25 (Herd), Laravel 13.33.0, MySQL local, Node 24.16.0.

## Cổng điều kiện

| Điều kiện | Kết quả |
| --- | --- |
| `docs/fix-wave-1-2-verification.md` tồn tại, ghi wave 1 + 2 PASS | ✅ |
| `git log` có `fix(wave1)` (`d9b3002`) và `fix(wave2)` (`74638d4`) | ✅ |
| `git status --short` sạch trước khi bắt đầu | ✅ (0 dòng) |

## Kết quả chung

| Hạng mục | Kết quả |
| --- | --- |
| Backend full test (`secondhand_marketplace_test`) | **444 test / 4.950 assertions PASS** |
| Frontend `npx vitest run` | **449 test / 36 file PASS** |
| `vendor/bin/pint --test` | PASS |
| `npx oxlint .`, `npx eslint .` (không `--fix`) | sạch, exit 0 |
| `npm run build` | OK |
| Migration trên DB dev | 2 migration additive, batch 11; dữ liệu cũ **không đổi** |
| Kiểm tra trình duyệt | PASS ở 1440 / 1024 / 768 / 390 px, 9 luồng, 0 lỗi JS (chạy trên DB test, xem bên dưới) |
| Push | không push |

## 2.3 — Kết quả đọc: khi nào `reconciliation_required` được bật

Cờ chỉ được bật ở **một chỗ**: `ZaloPayService::confirm()` (`backend/app/Services/ZaloPayService.php`, nhánh `if (! $safe)`). Hàm này chỉ chạy khi tiền đã được **xác minh**: callback có MAC hợp lệ và khớp `app_id` / `app_user` / `app_time`, hoặc truy vấn trả `return_code = 1`; số tiền khớp đúng `payment.amount`, tiền tệ VND, và `zp_trans_id` chưa từng được ghi cho payment nào.

Tiền được coi là "an toàn" (→ `PAID`) chỉ khi **tất cả** đúng: attempt đang `PENDING`; đơn chưa có attempt `PAID` nào; chưa có attempt nào đang bị gắn cờ; đơn có ShopOrder và mọi ShopOrder còn `PENDING`; số tiền attempt bằng tổng đơn. Ngược lại cờ được bật. Các tình huống cụ thể:

| # | Tình huống | Trạng thái attempt lúc bật cờ |
| --- | --- | --- |
| 1 | Tiền về trên attempt đã hết hạn / bị cổng từ chối (`NOT_SENT_EXPIRED`, `GATEWAY_EXPIRED`, `CREATE_REJECTED`) | `FAILED` |
| 2 | Tiền về trên attempt đã bị hủy vì ShopOrder bị hủy (`ShopOrderService` chuyển attempt PENDING → CANCELLED) | `CANCELLED` |
| 3 | Tiền về trên attempt còn PENDING nhưng đã có ShopOrder không còn PENDING (bị hủy một phần) | `PENDING` |
| 4 | Tiền về khi đơn đã có một attempt khác `PAID` (trả trùng) | `PENDING` / `FAILED` / `CANCELLED` |
| 5 | Tiền về khi đơn đã có một attempt khác đang bị gắn cờ | bất kỳ |
| 6 | Phòng vệ: số tiền attempt khác tổng đơn hoặc đơn không có ShopOrder | bất kỳ |

Khi bật cờ: trạng thái attempt **giữ nguyên**, `gateway_transaction_id` được ghi, `order_url` bị xóa, các attempt PENDING khác của đơn bị chuyển `CANCELLED`, audit `payment.reconciliation_required`. Không tình huống nào bật cờ khi tiền chưa về.

**Quyết định:** mọi tình huống đều là "tiền đã vào nhưng đơn không còn hợp lệ / thừa tiền" → khớp giả định của đề bài, **làm đầy đủ phần resolve**. Không gọi API refund, không tạo giao dịch tiền.

### Thiết kế đã cài

- `POST /admin/payments/{payment}/reconciliation` với `{ outcome, note, expected_status }`.
- `REFUNDED_OUTSIDE` → `status = REFUNDED`. `KEEP_PAID` → `status = PAID`, ghi `paid_at`.
- Ghi `reconciliation_resolved_at / _by / _note`, audit `payment.reconciliation_resolved` (lý do = `<outcome>: <note>`). **Không xóa cờ** `reconciliation_required`.
- `KEEP_PAID` chỉ được chấp nhận khi đơn còn nhận được đúng khoản đó (`PaymentReconciliationService::canKeep`): không có attempt khác đã `PAID`, không ShopOrder nào `CANCELLED`, số tiền bằng tổng đơn. Ngoài ra trả 409 và hướng sang hoàn tiền. Điều kiện này **tôi tự thêm** để `KEEP_PAID` không tạo ra đơn bị thu hai lần hoặc thu tiền cho phần đã hủy.
- Các chỗ chặn chuyển sang "cờ bật **và** chưa resolve" (`Payment::awaitsReconciliation()`):

| Vị trí | Thay đổi |
| --- | --- |
| `ShopOrderService::transition` | chỉ chặn khi còn payment chưa đối soát |
| `OrderResource.reconciliation_required` | `true` chỉ khi còn payment chưa đối soát (frontend người mua dùng trường này) |
| `ShopOrderResource` (`can_cancel_payment`, `payment_ready`) | như trên |
| `SellerInventoryService::actionableOrders` | đơn đã đối soát được tính lại là "cần xử lý" |
| `PaymentResource` | thêm `reconciliation_resolved_at`; `reconciliation_required` của từng payment vẫn là cờ gốc |

- **Cố ý giữ nguyên** các kiểm tra trong `ZaloPayService` (`start`, `sync`, `confirm`, `retry`, `canRetry`): chúng vẫn dựa trên cờ gốc. Hệ quả: đơn có payment đã bị gắn cờ không mở lại thanh toán ZaloPay mới, kể cả sau khi đã hoàn tiền (xem câu hỏi mở).
- `zalopay:reconcile` vốn lọc `reconciliation_required = false` nên không bao giờ truy vấn payment đã gắn cờ, dù đã resolve hay chưa; có test xác nhận không có request HTTP nào được gửi.

## Migration đã thêm (additive)

| Migration | Nội dung |
| --- | --- |
| `2026_10_12_150000_add_reconciliation_resolution_to_payments` | `payments.reconciliation_resolved_at` datetime(6) null; `reconciliation_resolved_by` FK → users, null; `reconciliation_note` varchar(1000) null; index `payments_reconciliation_queue_index` |
| `2026_10_12_150001_add_status_check_to_reports` | CHECK `reports_status_enum_check` (`PENDING`, `IN_REVIEW`, `RESOLVED`, `DISMISSED`); migration tự dừng nếu gặp giá trị lạ |

Kiểm tra dữ liệu trước khi thêm CHECK: bảng `reports` của DB dev **không có dòng nào**, nên không có giá trị lệch.

## Bảo toàn DB dev

| Bước | Kết quả | Bằng chứng |
| --- | --- | --- |
| 1. `mysqldump` | Lần đầu với `--single-transaction` bị từ chối (tài khoản DB thiếu quyền `RELOAD/FLUSH_TABLES`), **chưa có gì được migrate**. Lần hai với `--lock-tables`: thành công, 33 bảng, kết thúc bằng "Dump completed" | `.runtime/d5-backup.sql` (128 KB) |
| 2. Snapshot trước | 24 bảng nghiệp vụ, 389 dòng; lưu danh sách cột, bảng, CHECK, migration | `.runtime/d5-before.json` |
| 3. `php artisan migrate` | 2 migration chạy, batch 11 | output lệnh |
| 4. Snapshot sau | Băm lại **trên đúng các cột có trước đó**: không bảng nào đổi. Khác biệt duy nhất: `payments` +3 cột, +1 CHECK `reports.reports_status_enum_check`, +2 dòng `migrations`. Không bảng/cột/CHECK nào bị xóa | `.runtime/d5-after.json` |
| 5. Snapshot sau kiểm tra trình duyệt | giống bước 4 | `.runtime/d5-after-browser.json` |

Script chỉ đọc: `.runtime/d5/snapshot.php` (từ chối chạy nếu DB không phải `secondhand_marketplace`). Thư mục `.runtime/` bị gitignore.

## Bảng endpoint

Tất cả nằm sau `auth:sanctum` + `EnsureActiveAccount` + `EnsureSpaSession` + `EnsureAdmin` + `throttle:domain`, và gọi thêm policy.

| Method | URI | Controller | Policy | Ghi chú |
| --- | --- | --- | --- | --- |
| GET | `/api/v1/admin/action-center` | `AdminActionCenterController` | `UserPolicy::viewAny` | số đếm tổng hợp + `server_time` + ngưỡng cấu hình |
| GET | `/api/v1/admin/payments` | `AdminPaymentController@index` | `PaymentPolicy::viewAdmin` | chỉ payment từng bị gắn cờ; `reconciliation` = `1` / `open` / `resolved` / `all` |
| GET | `/api/v1/admin/payments/{payment}` | `@show` | `viewAdmin` | 404 nếu payment chưa từng bị gắn cờ |
| POST | `/api/v1/admin/payments/{payment}/reconciliation` | `@resolve` | `PaymentPolicy::reconcile` | `outcome`, `note`, `expected_status` đều bắt buộc |
| GET | `/api/v1/admin/auctions` | `AdminAuctionController@index` | `AuctionPolicy::viewAdmin` | lọc `status`, `settlement`, `overdue=1`, `auction=<id>` |
| POST | `/api/v1/admin/auctions/{auction}/cancel` | `@cancel` | `AuctionPolicy::cancelAsAdmin` | `reason`, `expected_status` bắt buộc |
| GET | `/api/v1/admin/users?flagged=1` | `AdminUserController@index` (có sẵn) | `UserPolicy::viewAny` | thêm filter `flagged` |

Endpoint có sẵn bị đổi hành vi: `PATCH /admin/products/{id}/status` (2.5), `PATCH /admin/shops/{id}/status` (2.6), `PATCH /admin/reports/{id}/status` (2.7), `PATCH /seller/shop` (2.8).

### Số đếm của `action-center`

| Key | Định nghĩa đã cài |
| --- | --- |
| `shops_pending`, `products_pending` | trạng thái PENDING, chưa xóa mềm |
| `reports_open`, `reports_pending`, `reports_in_review` | tổng báo cáo mở và hai số tách theo trạng thái |
| `reports_by_target` | số báo cáo mở theo `PRODUCT` / `SHOP` / `VIDEO_COMMENT` |
| `users_flagged` | số chủ sở hữu có ≥ N báo cáo mở trong M ngày nhắm vào shop, sản phẩm (qua shop) hoặc bình luận video của họ |
| `payments_reconciliation` | cờ bật và chưa resolve |
| `auctions_awaiting_checkout` | ENDED, có người thắng, chưa có đơn, còn hạn |
| `auctions_overdue` | SCHEDULED/LIVE có `ends_at` trước `now − 2 phút` |
| `video_comments_reported` | số bình luận (distinct) có báo cáo mở |

N, M và số phút quá hạn đọc từ `backend/config/admin-action-center.php` (`ADMIN_FLAGGED_MIN_REPORTS=3`, `ADMIN_FLAGGED_WINDOW_DAYS=30`, `ADMIN_AUCTION_OVERDUE_MINUTES=2`, đều có giá trị tối thiểu 1). Toàn bộ dùng một số truy vấn tổng hợp cố định; test xác nhận số truy vấn không đổi khi dữ liệu tăng và không vượt 12 cho cả request.

Lệch nhỏ so với đề bài: đề ghi `reports_open` "tách 2 số"; tôi trả `reports_open` là tổng và thêm hai khóa `reports_pending`, `reports_in_review`.

## Các thay đổi nghiệp vụ

### 2.4 Admin hủy phiên

- `AuctionService::cancelByAdmin`: SCHEDULED/LIVE, kể cả đã có bid và kể cả phiên quá hạn chưa được kết thúc; ENDED/CANCELLED → 409; `expected_status` lệch → 409.
- Dùng chung một hàm riêng `AuctionService::close()` với hủy của seller và đình chỉ shop: đổi `CANCELLED` (cột sinh `active_product_id` tự về NULL), ghi thông báo, phát `AuctionStatusChanged`. Không có logic hủy thứ hai.
- Thông báo `AUCTION_CANCELLED` tới seller và từng bidder (distinct, khóa chống trùng theo người nhận); nội dung kèm lý do rút gọn 120 ký tự. Bid được giữ nguyên.
- Audit `auction.cancelled_by_admin` kèm lý do đầy đủ.
- Tôi tự thêm: admin không hủy được phiên do chính mình bán (403), cùng nguyên tắc với kiểm duyệt shop/sản phẩm hiện có.
- Seller vẫn chỉ hủy được khi 0 bid (có test).

### 2.5 Admin ẩn sản phẩm đang có claim

- Có phiên SCHEDULED/LIVE → 409 `Sản phẩm đang có phiên đấu giá. Hãy hủy phiên trước khi ẩn.`; response kèm `auction_id` để giao diện dẫn link.
- ENDED chờ checkout → cho ẩn; người thắng nhận thông báo loại mới `AUCTION_CHECKOUT_UNAVAILABLE`.
- Đã xác minh `AuctionCheckoutService` từ chối checkout khi sản phẩm không còn `ACTIVE` (409) — không cần thêm kiểm tra; có test cho cả `GET` (`product_available = false`) và `POST`.

### 2.6 Đình chỉ shop

`ShopService::moderate` ACTIVE → SUSPENDED gọi `AuctionService::cancelForSuspendedShop` trong cùng transaction: hủy mọi phiên SCHEDULED/LIVE của shop với lý do "Gian hàng bị tạm đình chỉ", kèm thông báo và audit `auction.cancelled_shop_suspended`. Phiên ENDED không bị đụng. Kích hoạt lại shop không khôi phục phiên.

Lưu ý: sự kiện realtime `AuctionStatusChanged` của các phiên này **không được phát ra kênh public** vì điều kiện `broadcastWhen` sẵn có yêu cầu sản phẩm còn công khai, mà shop vừa bị đình chỉ. Người đang xem Arena nhận thông báo cá nhân và thấy 404 khi tải lại.

### 2.7 Report

- Gửi lại cùng trạng thái với `expected_status` **bằng trạng thái hiện tại** → cập nhật `admin_note`, ghi audit `report.note_updated` (chỉ khi note thực sự đổi). Áp dụng cả cho báo cáo đã đóng.
- Gửi lại một bước chuyển đã xong với `expected_status` cũ vẫn là retry idempotent trả 200 và **không** ghi đè note — giữ đúng hành vi mà hai test hiện có dựa vào.
- `ReportPolicy::moderate` trả 403 khi target thuộc về chính admin (chủ shop của sản phẩm/shop, tác giả bình luận). Resource admin thêm `can_moderate`.
- Thêm CHECK cho `reports.status`.

### 2.8 Shop

`ShopService::update` ghi audit `shop.updated`, lý do dạng `fields: name, description` (không lưu nội dung). Không đổi field thì không ghi. **Không bắt duyệt lại** — quyết định nghiệp vụ theo đề bài.

## Thứ tự khóa

| Luồng | Thứ tự |
| --- | --- |
| Đặt giá / mở phiên / tạo phiên (có sẵn) | users → shop → categories → product → auction → bids |
| Checkout của người thắng (có sẵn) | users → address → shop → categories → product → auction → order |
| Seller sửa sản phẩm/ảnh (wave 2) | users → shop → categories → product → auction |
| **Admin hủy phiên (D5)** | user (admin) → auction |
| **Admin ẩn sản phẩm (D5)** | users → shop → categories → product (`ProductLocks::lock`) → auction |
| **Đình chỉ shop (D5)** | users (admin, chủ shop) → shop → auctions theo id tăng dần |
| **Đối soát (D5)** | users (admin, người mua) → order → shop_orders → payments — cùng thứ tự với `ZaloPayService::confirm` và `ShopOrderService` |

Không luồng nào khóa auction trước product hay shop, nên không tạo chu trình với đặt giá. Đặt giá khóa dòng auction trước khi đọc/ghi bid; admin hủy khóa cùng dòng đó → hai thao tác luôn tuần tự.

Test race trên MySQL thật (`AuctionConcurrencyTest::test_real_mysql_admin_cancel_racing_bids_never_leaves_a_half_state`, 4 vòng, 3 tiến trình mỗi vòng): phiên luôn kết thúc ở CANCELLED; mỗi bid hoặc được lưu trước khi hủy (và người đặt nhận đúng 1 thông báo) hoặc bị từ chối (không có thông báo); `current_price` luôn khớp bid lớn nhất đã lưu.

## Frontend

| Hạng mục | File |
| --- | --- |
| Action Center `/admin` (9 thẻ, liên kết tới danh sách đã lọc, màu cảnh báo khi > 0, banner scheduler, nút làm mới) | `frontend/src/views/AdminActionCenterView.vue` |
| Đối soát `/admin/payments` (bảng, chi tiết, hộp thoại; xác nhận 2 bước cho hoàn tiền) | `frontend/src/views/AdminPaymentsView.vue` |
| Phiên đấu giá `/admin/auctions` (lọc, hủy phiên kèm cảnh báo số bid) | `frontend/src/views/AdminAuctionsView.vue` |
| Điều hướng admin (3 mục mới; `/admin` chỉ active khi khớp chính xác) | `frontend/src/components/admin/AdminShell.vue`, `frontend/src/router/index.js` |
| 409 khi ẩn sản phẩm có phiên: thông báo + link tới phiên | `frontend/src/views/AdminProductsView.vue` |
| Lọc `flagged` cho người dùng | `frontend/src/components/AdminModerationPanel.vue` |
| Báo cáo: sửa note khi giữ trạng thái; ẩn form với báo cáo của chính mình | `frontend/src/views/ReportsView.vue` |
| Nhãn `REFUNDED_OUTSIDE`, `KEEP_PAID`; loại thông báo mới | `frontend/src/utils/labels.js`, `frontend/src/components/notifications/NotificationList.vue`, `frontend/src/composables/useNotifications.js` |

`/admin/dashboard` giữ nguyên. Các trang shop, sản phẩm, báo cáo nhận bộ lọc từ query để link của Action Center mở đúng danh sách đã lọc. Mọi route mới có `requiresAuth` + `requiresAdmin`. Hộp thoại dùng `AccessibleDialog` (bẫy focus, Escape).

## Test

### Số liệu trước / sau

Baseline là lần chạy cuối của wave 2 trên đúng commit `74638d4` (working tree sạch khi bắt đầu D5); tôi không chạy lại baseline.

| | Trước D5 | Sau D5 | Chênh lệch |
| --- | --- | --- | --- |
| Backend test | 420 | 444 | +24 |
| Backend assertions | 4.523 | 4.950 | +427 |
| Frontend test | 431 | 449 | +18 |
| Frontend file | 35 | 36 | +1 |

### Test mới

- `backend/tests/Feature/AdminActionCenterTest.php` — 23 test: ma trận phân quyền cho mọi endpoint mới (guest 401; user, seller, chủ phiên, người mua 403; admin 200); đủ số đếm của action center và ngưỡng cấu hình; đếm truy vấn; hàng đợi thanh toán không lộ `order_url` / MAC / kênh / `gateway_app_*` / `request_key`; 422 khi thiếu `outcome` / `note` / `expected_status`; 409 khi lệch trạng thái, chưa gắn cờ, đã resolve; `REFUNDED_OUTSIDE` và `KEEP_PAID`; fulfilment hết bị chặn sau `KEEP_PAID`; `zalopay:reconcile` không gửi request nào; hủy phiên có bid (thông báo đúng 1 lần mỗi người, audit, bid còn nguyên, sản phẩm được giải phóng); sự kiện và thông báo không phát ra khi rollback; ENDED/CANCELLED/phiên của chính mình; bộ lọc danh sách phiên; hai nhánh 2.5; 2.6; ba sửa đổi 2.7; 2.8.
- `AuctionConcurrencyTest` +1 test race (ở trên); `tests/Support/auction-worker.php` thêm hành động `admin-cancel`.
- `frontend/src/__tests__/AdminActionCenterD5.spec.js` — 18 test: guard route, thẻ và liên kết, banner, làm mới, lỗi tải, điều hướng, hộp thoại đối soát 2 bước, `KEEP_PAID` bị vô hiệu khi không thể, hộp thoại hủy phiên, xung đột 409, link tới phiên đấu giá, lọc `flagged`, sửa note, ẩn điều khiển với báo cáo của chính mình, nhãn tiếng Việt.

### Test cũ đổi kỳ vọng

| Test | Thay đổi | Lý do |
| --- | --- | --- |
| `AuctionClaimGuardTest::test_admin_moderation_of_a_claimed_product_is_unchanged_in_this_wave` → `test_admin_moderation_follows_the_d5_rule_for_claimed_products` | LIVE: 200 → 409; chờ checkout: vẫn 200 | Mục 2.5 thay quy tắc wave 2 |
| `ShopManagementTest::test_admin_transition_matrix_and_owner_resubmit` | số audit 5 → 6 | Mục 2.8: seller sửa mô tả nay ghi `shop.updated` |
| `Reports.spec.js` (bước xử lý) | thêm lựa chọn giữ trạng thái hiện tại ở cuối danh sách | Mục 2.7: sửa note không đổi trạng thái |
| `AdminUI.spec.js` (điều hướng) | 8 → 11 liên kết | Ba mục điều hướng mới |

## Kiểm tra trình duyệt

Chạy trên một **stack tách riêng, không đụng DB dev**: PHP built-in server cổng 8001 với `APP_ENV=testing` (DB `secondhand_marketplace_test`), Vite cổng 5173 proxy sang đó, Edge headless với profile riêng điều khiển qua CDP. Fixture do `.runtime/d5/fixtures.php` tạo (script từ chối chạy ngoài DB test) rồi **xóa sạch** sau khi xong; mật khẩu fixture sinh ngẫu nhiên và đã bị xóa cùng file. Ba tiến trình đã được dừng.

| Kiểm tra | Kết quả |
| --- | --- |
| 1440 / 1024 / 768 / 390 px: Action Center, đối soát, phiên đấu giá, hai hộp thoại | không tràn ngang, không raw enum trong vùng nội dung, hộp thoại nằm trong màn hình, focus nằm trong hộp thoại, Escape đóng được |
| Khách vào `/admin` → trang đăng nhập kèm `redirect=/admin` | ✅ |
| Số đếm và banner scheduler; thẻ dẫn tới danh sách đã lọc | ✅ |
| `/admin/users?flagged=1` | ✅ |
| Hoàn tiền ngoài hệ thống qua 2 bước; payment rời hàng đợi mở | ✅ |
| Giữ khoản thanh toán trong 1 bước; `KEEP_PAID` bị vô hiệu với đơn đã hủy | ✅ |
| Ẩn sản phẩm đang có phiên: thông báo + link mở đúng phiên | ✅ |
| Hủy phiên có 3 bid (cảnh báo, lý do bắt buộc) | ✅ |
| Sửa ghi chú báo cáo không đổi trạng thái | ✅ |
| `/admin/dashboard` cũ và điều hướng mới | ✅ |
| Lỗi JS trong console | 0 |

Kết quả máy đọc được: `.runtime/d5/browser-results.json`; 24 ảnh ở `.runtime/d5/shots/` (không vào git).

Lần chạy đầu tiên của script thất bại vì tôi khởi động PHP built-in server sai thư mục (mọi request trả lỗi 500); đó là lỗi dựng môi trường kiểm thử, không phải lỗi ứng dụng. Sau khi khởi động lại từ `backend/public`, script chạy một lần và PASS toàn bộ. Một ảnh `failure.png` của lần đầu còn nằm trong thư mục ảnh.

### Ảnh chụp (đã commit, chỉ chứa dữ liệu fixture)

| Màn hình | Ảnh |
| --- | --- |
| Action Center | [1440](screenshots/d5/action-center-1440.png) · [1024](screenshots/d5/action-center-1024.png) · [768](screenshots/d5/action-center-768.png) · [390](screenshots/d5/action-center-390.png) |
| Đối soát thanh toán | [danh sách 1440](screenshots/d5/payments-1440.png) · [hộp thoại 390](screenshots/d5/payments-dialog-390.png) · [bước xác nhận 1440](screenshots/d5/payments-confirm-1440.png) |
| Phiên đấu giá | [1440](screenshots/d5/auctions-1440.png) · [390](screenshots/d5/auctions-390.png) · [hộp thoại hủy 768](screenshots/d5/auctions-dialog-768.png) |
| Ẩn sản phẩm bị chặn | [1440](screenshots/d5/products-conflict-1440.png) |

Nút nổi nhỏ ở đáy một số ảnh là công cụ Vue DevTools của Vite dev server, không thuộc ứng dụng.

## Không thực hiện

- **FinalDemoSeeder không được bổ sung.** Phiên để demo hủy đã có sẵn (seeder hiện tạo hai phiên LIVE có bid). Payment cần đối soát thì không thể tạo qua service thật: cần đơn ZaloPay cùng callback đã xác minh, tức phải dựng tay trạng thái cổng thanh toán trong seeder; và tôi không được chạy `db:seed` trên DB dev để kiểm tra nó không phá dữ liệu cũ. Theo điều kiện "nếu không chắc → bỏ qua", tôi bỏ qua. Trang đối soát trên DB dev vì vậy sẽ trống cho tới khi có payment thật bị gắn cờ.
- Không chạy lại baseline backend/frontend (dùng số của commit `74638d4`).
- Không chạy `npm run lint` (script có `--fix`).

## Giới hạn còn lại

- Không có refund tự động; hệ thống chỉ ghi nhận quyết định. Không mô hình hóa hoàn tiền một phần; không mở lại được payment đã resolve.
- Đơn có payment từng bị gắn cờ không tạo được attempt ZaloPay mới, kể cả sau khi đã hoàn tiền.
- Phiên ENDED không hủy được; người thắng còn hạn vẫn giữ quyền checkout trừ khi sản phẩm bị ẩn.
- Đình chỉ shop không phát sự kiện realtime public cho các phiên bị hủy (xem 2.6).
- Trang đối soát chỉ liệt kê payment từng bị gắn cờ, không phải mọi payment.
- `users_flagged` đếm theo báo cáo đang mở; báo cáo đã đóng không tính, và target đã bị xóa cứng không quy được về chủ sở hữu.
- Khóa toàn bảng `categories` và phụ thuộc scheduler: không thay đổi trong D5. Action Center chỉ **phát hiện** scheduler dừng qua `auctions_overdue`.

## Câu hỏi mở

1. **AWAITING_CHECKOUT:** admin có cần hủy được quyền checkout của người thắng mà không phải ẩn sản phẩm không? Hiện phiên ENDED để nguyên theo đề bài.
2. **`KEEP_PAID`:** ba điều kiện tôi thêm (không có attempt PAID khác, không ShopOrder nào bị hủy, số tiền bằng tổng đơn) có đúng ý không? Trường hợp đơn bị hủy một phần hiện chỉ có thể hoàn toàn bộ ngoài hệ thống.
3. **Sau `REFUNDED_OUTSIDE`:** có cho người mua thanh toán lại ZaloPay cho đơn còn PENDING không? Hiện bị chặn; đơn chỉ có thể bị hủy.
4. **`reports_open`:** trả tổng + hai khóa tách có phù hợp, hay cần đúng một đối tượng `{ pending, in_review }`?
5. **Tự xử lý:** tôi chặn admin hủy phiên do chính mình bán và đối soát payment của đơn do chính mình mua. Giữ hay bỏ?
6. **Demo đối soát:** có muốn một seeder riêng dựng sẵn payment bị gắn cờ (bằng cách ghi trực tiếp trạng thái) để demo trang `/admin/payments` không?
7. **Đình chỉ shop:** có cần phát sự kiện realtime cho người đang xem phiên bị hủy dù sản phẩm không còn công khai không?
8. Các câu hỏi còn mở từ wave 1–2 (điều kiện thứ tư của SOLD → ACTIVE, literal trong `UserFactory`, ràng buộc PHP trong `composer.json`) chưa được trả lời.
