# PHASE 3 verification

Ngày kiểm tra: 26/09/2026. Project: `D:\CN\Web\secondhand-marketplace`.

**PHASE 3 COMPLETED.** Nghiệm thu nền tảng dữ liệu; chưa bắt đầu PHASE 4.

## Kết quả

| Hạng mục | Kết quả / bằng chứng |
|---|---|
| Development DB | PASS — MySQL 26.7.0, secondhand_marketplace, 127.0.0.1:3306, secondhand_app |
| Testing DB | PASS — secondhand_marketplace_test, MySQL thật; không SQLite |
| Charset/collation | utf8mb4 / utf8mb4_unicode_ci |
| Audit filesystem | PASS — git status, danh sách và nội dung migrations/models/enums/factories/seeders/tests đã đọc trước khi tiếp tục |
| Trùng source | PASS — 17 migration files, 14 models, 11 enums; không trùng tên bảng/class/enum |
| migrate:fresh --seed | PASS — sau audit; guard kiểm tra APP_ENV local, driver, tên cấu hình, SELECT DATABASE(), số dòng và marker dữ liệu demo trước khi chạy |
| migrate:status | PASS — cả 17 migrations Ran, batch 1 |
| Backend | PASS — 25 tests, 161 assertions; gồm hồi quy PHASE 1 và tests dữ liệu PHASE 3 |
| Frontend unit tests | PASS — 1 test file, 2 tests |
| Frontend production build | PASS — Vite 8.3.1, 83 modules; xuất frontend/dist |
| PHP format | PASS — Pint --dirty --format agent và Pint toàn project |
| Health API | PASS — HTTP 200 thực tế tại /api/v1/health, JSON contract không đổi |
| API scope | PASS — route:list --path=api --except-vendor chỉ có GET/HEAD api/v1/health |
| Secrets | PASS — .env/.env.testing bị Git ignore; examples không chứa credential; scan source ứng viên Git không thấy DB password hoặc APP_KEY thật |

MySQL đã được khôi phục ở bước trước bằng init-file, chỉ reset root@localhost. Service dùng `C:\ProgramData\MySQL\MySQL Server 26.7\my.ini`, data thực tế trong thư mục `Data` của cùng đường dẫn. Đã dừng mysqld thủ công sạch sẽ, xóa file init và khởi động lại MySQL267. Không reinstall, initialize, xóa data directory, đổi port hoặc dùng skip-grant-tables. Khi tiếp tục theo yêu cầu mới, không thực hiện lại các bước MySQL đã hoàn tất. File init chứa ALTER USER được xác nhận không còn tồn tại.

Application account là secondhand_app@127.0.0.1, chỉ có quyền trên hai database project; Laravel không dùng root. Credential thật không được ghi trong báo cáo này.

## Audit và hợp nhất

Git hiện hiển thị source ở trạng thái untracked từ lúc khởi tạo; chưa tạo commit. Vì chưa có baseline commit, audit dùng nội dung thực tế, LastWriteTime và danh sách file, không suy diễn thay đổi từ git diff.

Source schema/models/enums và fixtures đã tồn tại. Không tạo thêm bản trùng, không chạy lại generator để ghi đè các phần đúng. Hai tests DatabaseSchemaTest và DemoSeederTest còn là scaffold đã được hoàn thiện tại chỗ. Tài liệu Category được sửa để không tuyên bố CHECK self-parent chưa có trong schema. Pint chỉ chuẩn hóa định dạng/imports. Migration framework được giữ, users được mở rộng trong migration ban đầu của project mới; không tạo bảng users thứ hai.

Laravel Boost đã được cài theo hướng dẫn project, là dependency development. Composer lockfile ghi nhận phiên bản; không thêm module nghiệp vụ.

## Schema, models và enums

14 entities có model và factory: User, Address, Shop, Category, Product, ProductImage, Cart, CartItem, Order, ShopOrder, OrderItem, Payment, Review, AuditLog. AuditLog phục vụ audit moderation/transition đã được thiết kế ở PHASE 2; chỉ có created_at.

11 string-backed enums: UserRole, UserStatus, ShopStatus, CategoryStatus, ProductCondition, ProductStatus, OrderStatus, ShopOrderStatus, PaymentMethod, PaymentStatus, ReviewStatus. Các cột persisted dùng enum casts và VARCHAR với CHECK whitelist; OrderStatus mô tả aggregate tương lai, không có cột Order.status.

Models khai báo fillable cụ thể, typed relationships, datetime/bool/integer/decimal casts và HasFactory. User ẩn password/remember_token, hash password và không cho mass-assign role/status qua fillable. Chỉ User, Shop, Product có SoftDeletes.

Quan hệ được kiểm thử bằng graph thật: User→addresses/shop/cart; Shop→products; Category→parent/children; Product→images; Cart→items; Order→shopOrders/payments; ShopOrder→orderItems; OrderItem→review. Factory Review suy ra đúng buyer/product từ OrderItem; OrderItem mặc định tạo Product cùng Shop với ShopOrder.

### Payment

Order hasMany Payments, Payment belongsTo Order; payments không có shop_order_id, ShopOrder không có payments relationship. UNIQUE(order_id,attempt_number) cho nhiều attempts; transaction_reference unique và (method,gateway_transaction_id) unique. COD demo tạo một PENDING attempt toàn Order, completed demo có một PAID attempt. Seller revenue theo ShopOrder COMPLETED, không cộng các payment attempts.

Tài liệu requirements/business-rules/database-design/api-design/permissions/state-transitions/use-cases đã đồng bộ. Báo cáo PHASE 2 giữ lịch sử, có banner nêu thiết kế Payment cũ đã bị thay thế.

### Constraints, indexes và lịch sử

- UNIQUE email, shop owner, cart user, cart/product, order code, buyer/checkout key, order/shop, shop order/product, review/order item và payment attempts/references.
- Tiền DECIMAL(15,2), cast decimal:2; phép tính fixture dùng BCMath, không float. Kiểm thử round-trip giá trị lớn và kiểu cột MySQL thực tế.
- CHECK rating 1..5, quantity 1..99, price/amount dương, attempt_number từ 1, công thức total/subtotal và enum whitelist. Stock là unsigned integer.
- Index cho public product theo category/shop/status/deleted_at/date hoặc price; seller fulfillment và revenue theo shop/status/completed_at; history, payment attempts, category tree, review và audit. Tests đọc metadata index/FK trực tiếp từ MySQL.
- FK ON UPDATE RESTRICT. Order buyer, ShopOrder order/shop, OrderItem product/shop order và Payment order dùng ON DELETE RESTRICT; tests chứng minh hard-delete buyer/shop/product có history bị chặn.
- CASCADE dành cho dữ liệu phụ thuộc: Address/Cart của User, CartItems, ProductImages. Tests kiểm tra Cart→CartItems và Product→ProductImages.
- Soft-delete không xóa order history; historical belongsTo dùng withTrashed khi phù hợp. Order snapshot địa chỉ, OrderItem snapshot tên/ảnh/giá/quantity/subtotal; chỉnh sửa Product/Address không làm đổi snapshot.

## Dữ liệu mẫu

| Bảng | Số dòng |
|---|---:|
| users | 9 |
| addresses | 9 |
| shops | 4 |
| categories | 12 |
| products | 28 |
| product_images | 56 |
| carts | 3 |
| cart_items | 6 |
| orders | 2 |
| shop_orders | 4 |
| order_items | 4 |
| payments | 2 |
| reviews | 2 |
| audit_logs | 2 |

Một admin; demo emails: admin@example.com, seller@example.com, buyer@example.com, seller2@example.com, seller3@example.com, seller4@example.com, user1@example.com, user2@example.com, user3@example.com. Không ghi mật khẩu trong báo cáo.

Category có 3 roots và 9 children. Mỗi product có hai ảnh placeholder, đúng một primary. Hai orders đều có hai ShopOrders; một completed, một pending. Buyer khác seller; reviews thuộc phần completed và đúng buyer. Totals Order/ShopOrder/OrderItem/Payment khớp. Seed chạy trong transaction, giới hạn local/testing, chạy lại không nhân đôi record hoặc trừ stock lần nữa; tests xác minh số dòng và stock sau hai lần seed.

## Kiểm thử và giới hạn

Backend dùng RefreshDatabase trên database test riêng. TestCase chặn trước trait migration nếu môi trường, driver, tên database cấu hình hoặc SELECT DATABASE() không đúng. Transaction rollback được kiểm tra trên InnoDB. Development vẫn giữ dữ liệu demo sau tests. Không có mock DB để thay thế kiểm tra FK/CHECK/UNIQUE.

Các lệnh nghiệm thu: migrate:fresh --seed, migrate:status, artisan test --compact, Pint, npm run test:unit -- --run, npm run build và HTTP GET health. Tổng 25 backend tests bao gồm graph relationships, casts, snapshot, soft/hard delete, duplicate constraints, tiền, index/FK, CHECK, rollback và seeder idempotency.

PHASE 3 chưa triển khai services/policies/controllers nghiệp vụ: cycle category, một default address/primary image, review eligibility liên bảng, immutable snapshots qua API, stock locking, tổng hợp Order status/COD, allocation attempt dưới lock và chống thanh toán active trùng cần kiểm thử ở phase nghiệp vụ. DB constraints chỉ bảo vệ những invariant cùng row và các FK/UNIQUE đã nêu. Không tuyên bố đã có checkout, auth, VNPay, chat, auction, Reverb hoặc Vue feature screens.

Placeholder SVG là asset tĩnh trong source; không mở tính năng upload SVG. Tài khoản demo chỉ phục vụ local/testing. Bộ kiểm thử cần MySQL đang chạy và .env.testing hợp lệ.
