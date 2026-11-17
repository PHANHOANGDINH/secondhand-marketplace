# Chi tiết địa chỉ (không bắt buộc) — kiểm chứng

## Yêu cầu
Thêm vào form "Thêm địa chỉ" một ô **Chi tiết địa chỉ (không bắt buộc)**, giống Shopee/TikTok Shop:
tòa nhà, số tầng, số phòng, mốc gần đó.

## Thiết kế
- Cột mới `addresses.address_detail` VARCHAR(255) NULL, đặt sau `address_line`.
- Đơn hàng lưu bản sao riêng `orders.shipping_address_detail` VARCHAR(255) NULL, như các trường
  `shipping_*` khác, để người bán thấy khi giao và để sửa địa chỉ đã lưu không làm đổi đơn cũ.
- API: `address_detail` là `nullable|string|max:255` khi tạo; `sometimes|nullable|...` khi sửa.
  Chuỗi rỗng thành `null`. `AddressResource` và `shipping_address` của đơn đều trả về trường này.
- Hiển thị: chi tiết đứng trước số nhà/đường, như cách ghi địa chỉ ở Việt Nam:
  "Phòng 502, tòa A, 12 Trần Phú, Phường …, Tỉnh …".

## File thay đổi
| Phần | File |
|---|---|
| Migration (additive, `down()` xóa 2 cột) | `backend/database/migrations/2026_11_17_000000_add_address_detail_to_addresses_and_orders.php` |
| Model | `Address.php`, `Order.php` (fillable) |
| Validation | `StoreAddressRequest.php`, `UpdateAddressRequest.php` |
| Resource / snapshot | `AddressResource.php`, `OrderCreator.php`, `OrderSummary.php` |
| Form + danh sách | `frontend/src/views/AddressesView.vue` |
| Ô nhập | `frontend/src/components/DomainField.vue` (thêm prop `placeholder`) |
| Đơn hàng | `frontend/src/components/OrderShipping.vue` |
| Nhãn lỗi | `frontend/src/utils/validationLabels.js` |

Form: "Địa chỉ" có gợi ý "Số nhà, tên đường"; ô mới có gợi ý "Tòa nhà, số tầng, số phòng, mốc gần đó…",
không có dấu `*`, không `required`. Giá trị được `trim()`; để trống thì gửi `null`.

## Test mới
- `ProfileAddressTest::test_address_detail_is_optional_editable_and_length_limited`: không gửi → `null`;
  gửi → trả đúng; sửa trường khác giữ nguyên; gửi rỗng → `null`; 256 ký tự hoặc mảng → 422.
- `CheckoutTest::test_multi_shop_checkout_...`: đơn mang `shipping_address.address_detail`; sửa và xóa
  địa chỉ sau khi đặt không đổi bản sao trên đơn.
- `DomainViews.spec.js`: ô tùy chọn, nhãn đúng, giá trị cũ được nạp khi sửa, gửi đã trim, hiển thị trước đường;
  `OrderShipping` hiện chi tiết khi có, bỏ qua khi `null`.

## Migration trên DB dev
| Bước | Kết quả |
|---|---|
| Sao lưu | `.runtime/address-detail-backup.sql` (mysqldump `--single-transaction --set-gtid-purged=OFF`, "Dump completed") |
| Snapshot trước/sau | `.runtime/address-detail-snapshot-{before,after}.json`: 38/38 bảng, số dòng và hash không đổi (bỏ qua 2 cột mới); `migrations` 36 → 37 |
| Rollback | Thử trên `secondhand_marketplace_test`: `down()` xóa cả 2 cột, migrate lại thành công |

Ghi chú: mysqldump mặc định cần quyền FLUSH mà tài khoản `secondhand_app` không có; thêm
`--set-gtid-purged=OFF` thì không cần. Cảnh báo "column_masking_policy" là bảng hệ thống, không ảnh hưởng bản dump.

## Kiểm chứng
| Lệnh | Kết quả |
|---|---|
| `php artisan test` (DB test) | 555/555 đạt, 6908 assertion |
| `pint --test` | đạt |
| `vitest run` | 594/594 đạt |
| `oxlint src` | 0 lỗi |
| `eslint` (file đã sửa, không `--fix`) | 0 lỗi |
| `npm run build` | thành công |
