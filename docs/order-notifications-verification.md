# Thông báo đơn hàng — kiểm chứng

## Yêu cầu
Nhắc người dùng khi đơn được đặt và mỗi khi đơn chuyển trạng thái, qua chuông thông báo sẵn có.

## Thiết kế
Dùng lại hạ tầng thông báo của đấu giá: bảng `notifications` (khóa `dedup_key` chống trùng),
sự kiện `NotificationCreated` (kênh riêng `user.{id}`, chỉ phát **sau commit**), chuông và trang `/notifications`.
Service mới `App\Services\OrderNotificationService` ghi thông báo **trong cùng transaction** của thao tác;
không khóa thêm bảng nào, nên thứ tự khóa hiện có giữ nguyên. Giao dịch rollback thì không có thông báo.

| Sự kiện | Gửi cho | Loại | Nội dung (ví dụ) | Mở tới |
|---|---|---|---|---|
| Đặt đơn COD | Người mua | `ORDER_PLACED` | Đặt hàng thành công! Đơn SHM-… đang chờ người bán xác nhận. | `/orders/{id}` |
| Đặt đơn COD | Chủ từng shop | `ORDER_PLACED` | Bạn có đơn hàng mới SHM-…. Hãy xác nhận đơn sớm nhé. | `/seller/orders/{id}` |
| Đặt đơn ZaloPay | Người mua | `ORDER_PLACED` | Đơn hàng SHM-… đã được tạo. Hãy hoàn tất thanh toán ZaloPay để người bán xử lý đơn. | `/orders/{id}` |
| ZaloPay xác nhận tiền | Người mua + chủ từng shop | `ORDER_PAID` | Thanh toán … thành công / Đơn … đã được thanh toán qua ZaloPay. Hãy xác nhận đơn sớm nhé. | như trên |
| Shop xác nhận | Người mua | `ORDER_STATUS_CHANGED` | {Shop} đã xác nhận đơn SHM-…. | `/orders/{id}` |
| Shop chuẩn bị hàng | Người mua | 〃 | {Shop} đang chuẩn bị hàng cho đơn SHM-…. | 〃 |
| Đang giao | Người mua | 〃 | Đơn SHM-… từ {Shop} đang được giao đến bạn. | 〃 |
| Hoàn tất | Người mua | 〃 | Đơn SHM-… từ {Shop} đã giao thành công. Hãy đánh giá sản phẩm nhé! | 〃 |
| Shop hủy | Người mua | 〃 | {Shop} đã hủy đơn SHM-…. Lý do: … | 〃 |
| Người mua hủy | Chủ shop | 〃 | Người mua đã hủy đơn SHM-…. Lý do: … | `/seller/orders/{id}` |

- Người thực hiện thao tác không nhận thông báo về chính hành động của mình.
- Đơn ZaloPay: người bán chỉ được báo khi tiền đã xác minh (trước đó họ không thể xử lý đơn).
- Đơn đấu giá giữ thông báo cũ `AUCTION_ORDER_CREATED`, không nhận thêm `ORDER_PLACED`; các bước trạng thái sau đó vẫn được báo.
- Chống trùng: khóa theo `người nhận | loại | đơn hoặc shop_order + trạng thái`. Gửi lại cùng thao tác
  (replay idempotent, callback ZaloPay lặp) không tạo thông báo thứ hai.
- Lý do hủy cắt còn 120 ký tự, giống thông báo đấu giá.
- API thông báo trả thêm `order_id`, `shop_order_id`, `order_code`, `order_status`.
- Bộ lọc "Đơn hàng" gồm `OrderNotificationService::TYPES` (4 loại đơn, kể cả `AUCTION_ORDER_CREATED`);
  "Đấu giá" là phần còn lại (giữ nguyên cách cũ).
- Không thay đổi DB schema, không có migration.

## File thay đổi
| File | Thay đổi |
|---|---|
| `backend/app/Services/OrderNotificationService.php` | Mới |
| `backend/app/Services/CheckoutService.php` | Gọi `placed()` sau khi tạo đơn |
| `backend/app/Services/ShopOrderService.php` | Gọi `statusChanged()` sau audit log |
| `backend/app/Services/ZaloPayService.php` | Gọi `paid()` sau `payment.paid` |
| `backend/app/Http/Resources/NotificationResource.php` | Thêm 4 trường đơn hàng |
| `backend/app/Http/Controllers/Api/V1/NotificationController.php` | Bộ lọc dùng `TYPES` |
| `frontend/src/components/notifications/NotificationList.vue` | Nút "Xem đơn hàng" cho 3 loại mới |

## Test mới
- `OrderNotificationTest` (4 test): COD nhiều shop báo người mua + từng chủ shop, phát 3 sự kiện;
  4 bước trạng thái báo người mua đúng câu, replay không trùng, người bán không bị báo việc của mình;
  hủy hai chiều kèm lý do; chuyển trạng thái bị 409 không để lại thông báo.
- `ZaloPayTest::test_seller_is_reminded_only_once_online_payment_is_verified`: người bán không được báo
  khi đơn mới tạo; callback thành công báo cả hai bên; callback lặp không trùng.
- `OrderNotifications.spec.js` (4 test): nhãn "Xem đơn hàng", đường dẫn an toàn, aria-label, sự kiện mở.

## Kiểm chứng
| Lệnh | Kết quả |
|---|---|
| `php artisan test` (DB test) | 560/560 đạt, 6976 assertion |
| `pint --test` | đạt |
| `vitest run` | 598/598 đạt |
| `oxlint src` | 0 lỗi |
| `eslint` (file đã sửa, không `--fix`) | 0 lỗi |
| `npm run build` | thành công |
