# PHASE 2 verification

> Báo cáo lịch sử trước revision PHASE 3: nội dung Payment theo ShopOrder bên dưới đã bị thay thế theo yêu cầu người dùng. Thiết kế hiện hành: Order hasMany Payments; xem database-design.md và BR-PAYMENT-001…005. Kết quả test PHASE 2 giữ nguyên lịch sử.

Ngày: 2026-09-26, Asia/Saigon. Project: `D:\CN\Web\secondhand-marketplace`.

## Kết quả

**PHASE 2 COMPLETED** — tài liệu requirements/business rules/use cases/database/API/permissions/states đã hoàn chỉnh theo baseline. Regression PHASE 1 vẫn PASS. Chưa bắt đầu PHASE 3.

## Files created

1. [requirements.md](requirements.md): 10 mục yêu cầu, FR/NFR, actors, MVP/future, assumptions và out of scope.
2. [business-rules.md](business-rules.md): rules có ID, checkout transaction/idempotency, stock, snapshots, payment/revenue.
3. [use-cases.md](use-cases.md): Guest/Buyer/Seller/Admin, tiền điều kiện, main/error flows, sơ đồ Mermaid và scenarios A–L.
4. [database-design.md](database-design.md): entities/columns/types/nullability/PK/FK/unique/index/delete, ERD, migration plan trên giấy và future tách riêng.
5. [api-design.md](api-design.md): 5 nhóm API, contract/input/response/status/pagination/search, Sanctum SPA plan và future boundaries.
6. [permissions.md](permissions.md): authorization matrix, policy/scoping, response redaction và audit.
7. [state-transitions.md](state-transitions.md): Shop/Product/ShopOrder/Payment/Auction future, actor/guards/side effects, Order derived states.
8. [phase-2-verification.md](phase-2-verification.md): báo cáo này.

## Files modified

Chỉ [README.md](../README.md): thêm liên kết thiết kế PHASE 2, thay câu dừng PHASE 1 đã lỗi thời bằng phạm vi tài liệu hiện tại. Không biến README thành báo cáo dài. `docs/phase-1-verification.md` giữ nguyên báo cáo lịch sử.

Không sửa source backend/frontend, route health, package manifests/lockfiles hoặc migrations/model/controller. Test/build chỉ cập nhật output/cache/log đã ignored. Repository đã untracked từ PHASE 1 nên git diff riêng không chứng minh được phạm vi; đối chiếu SHA-256 77 file source/config/dependency trước/sau lưu tạm trong .runtime/phase2-baseline.json để kiểm chứng.

## Major architecture decisions

- Một User có role USER/ADMIN; Buyer/Seller là ngữ cảnh. UNIQUE shops.owner_id giữ 0..1 Shop/User kể cả soft-deleted; bỏ unique sau này để mở rộng nhiều Shop, không phải đổi Product/ShopOrder FK.
- Cart dùng UNIQUE(user_id), item UNIQUE(cart_id,product_id), chứa nhiều Shop, không reserve/khóa giá.
- Order tổng checkout → ShopOrder mỗi Shop → OrderItem snapshots. Seller chỉ được xử lý phần mình. Order overall_status/payment_status derived từ các phần, không lưu hai nguồn sự thật.
- Address snapshot columns trên Order; tránh bảng order_addresses khi một checkout dùng chung một địa chỉ. Product image paths bất biến còn reference phải được giữ.
- Checkout đọc lại dữ liệu dưới row locks, giảm stock và clear cart cùng transaction; fail rollback mọi phần. Idempotency-Key scoped Buyer, payload hash và unique constraint chống duplicate checkout.
- Mỗi ShopOrder có Payment COD PENDING riêng; completion/thu COD trong một transaction. Nhiều attempts cho VNPay tương lai vẫn scoped ShopOrder. Không coi một lần thu ở Shop A là đã thu cho Shop B.
- Tiền Money DECIMAL(15,2), VND nguyên đồng, API trả chuỗi; shipping/discount=0 trong MVP, doanh thu Seller là subtotal của COMPLETED.
- Bổ sung một audit_logs append-only để audit moderation/state/admin exception commit cùng dữ liệu, không thêm nhiều bảng history cho mỗi module.

## Database entities

13 entities nghiệp vụ: users, addresses, shops, categories, products, product_images, carts, cart_items, orders, shop_orders, order_items, payments, reviews.

1 entity hỗ trợ có lý do: audit_logs. Tổng 14 trong ERD MVP. Các bảng hạ tầng scaffold Laravel/Sanctum đã có trong source được giữ nguyên, không tính thành module nghiệp vụ mới.

Future riêng: notifications, conversations, messages, auctions, bids; chưa cần conversation_participants cho direct Buyer↔Shop. Không tạo future migrations. Payment allocation/refund records chỉ thiết kế thêm khi cần whole-Order VNPay/partial refunds.

## Order architecture và lịch sử

Mỗi checkout tạo ít nhất một ShopOrder và mỗi ShopOrder có ít nhất một OrderItem/Payment COD khi commit. Unique(order_id,shop_id) ngăn trùng phần shop. Item lưu tên/ảnh/giá/quantity/subtotal; Order lưu địa chỉ/totals. Thay đổi hoặc soft-delete Product/Address không thay history. Hủy giữ totals gốc, tính payable theo phần không hủy; trả stock một lần dựa trạng thái/stock_restored_at và lock.

Soft-delete users/shops/products; Category/Review dùng status. Các references Order/ShopOrder/OrderItem/Payment dùng RESTRICT. CASCADE chỉ dữ liệu tạm/metadata phụ thuộc; không cascade commerce history. Không DELETE API history kể cả Admin.

## Authorization model

Sanctum SPA cookie/session/CSRF dự kiến + backend active-user/role middleware + object policies; route guard frontend chỉ hỗ trợ UX. Seller A sửa ShopOrder B bị403. Buyer chỉ review own completed Item, mỗi Item một review. Admin không bypass state/stock/snapshot, không tự duyệt hàng Shop mình, mọi ngoại lệ có reason/audit.

## API conventions

Public / Authenticated / Seller / Admin / Future, prefix /api/v1; chỉ health đang tồn tại. Single data resource, collection data/links/meta, message/errors cho validation. CRUD HTTP200/201/204; phân biệt401/403/404/409/422/429/500, có400 malformed JSON và419 CSRF. Global Product/Shop slug public; IDs numeric string cho mutation. Pagination default20/max100. Filter và sort whitelist.

## State machines

Product: DRAFT/PENDING/ACTIVE/SOLD/HIDDEN/REJECTED, condition độc lập. ShopOrder: PENDING→CONFIRMED→PROCESSING→SHIPPING→COMPLETED, cancellation branches theo actor/guard; terminal không quay ngược. Shop PENDING/ACTIVE/SUSPENDED/REJECTED. Payment PENDING/PAID/FAILED/CANCELLED/REFUNDED (failed/refund transitions future). Auction future SCHEDULED/ACTIVE/ENDED/CANCELLED.

Order aggregate: PENDING/IN_PROGRESS/COMPLETED/PARTIALLY_COMPLETED/CANCELLED; tiền tổng có PARTIALLY_PAID và số payable/paid/due riêng.

## Scenario consistency review — kiểm tra thiết kế, chưa chạy nghiệp vụ

| Scenario | Kết luận thiết kế | Căn cứ |
|---|---|---|
| A Một Product/một Shop | 1 Order/1 ShopOrder/1 Item/1 COD Payment | BR-ORDER-002, BR-PAYMENT-001 |
| B Ba Shop/checkout | 3 ShopOrders và 3 payments độc lập, không can thiệp chéo | UNIQUE(order_id,shop_id), ShopOrderPolicy |
| C Seller sửa giá | Item/totals snapshot bất biến | BR-ORDER-001/006 |
| D Product soft-delete | Order đọc snapshot, FK Product còn, file ảnh giữ khi có reference | BR-IMAGE-002, FK RESTRICT |
| E Address sửa/xóa | Không FK tới Address, snapshot Order bất biến | BR-ORDER-004 |
| F Seller A sửa ShopOrder B | 403, không ghi dữ liệu | Permissions, BR-ORDER-011 |
| G Review chưa mua | 403; own item chưa completed409, unique item ngăn review trùng | BR-REVIEW-001 |
| H Stock1/hai checkout | Product row lock + recheck; chỉ một commit, không stock âm | BR-ORDER-003, transaction flow |
| I Replay checkout | Trả order cũ200, không trừ stock hai lần | BR-ORDER-005 |
| J Cancel-vs-complete | Serialize theo Order/ShopOrder lock, terminal + optimistic guard | BR-ORDER-009/012 |
| K COD thu một phần | Derived PARTIALLY_PAID; chỉ completed tính revenue, cancelled không tính | BR-PAYMENT-004, BR-REVENUE-001 |
| L Ảnh listing bị xóa | Blob còn referenced bởi snapshot không bị cleanup | BR-IMAGE-002 |

Đã đối chiếu API/input với columns, state actor với matrix, HTTP409 state guard với403 ownership. Khóa planned owner set trước Cart/Shop/Product giúp tránh đảo thứ tự khi hai shop owners mua chéo; nếu lock set đổi phải rollback/retry. MySQL concurrency implementation và tests vẫn là công việc phase Order, không được suy ra PASS runtime từ bảng review này.

## Tests run / Test results

Chạy trong đúng backend/frontend directory và dùng scripts package.json đã đọc:

| Command / check | Kết quả thực tế |
|---|---|
| php artisan test | PASS: 5 tests, 9 assertions (429 ms) |
| npm.cmd run test:unit -- --run | PASS: 1 file, 2 tests (2.03 s) |
| npm.cmd run build | PASS: Vite8.3.1, 83 modules, 340 ms |
| GET http://127.0.0.1:8000/api/v1/health | HTTP200, JSON nguyên trạng status/message |
| GET http://localhost:5173 | HTTP200 |
| Source/config/lockfile hash comparison | 77 file baseline, không thay đổi/thêm/xóa trong backend/frontend source |
| Tài liệu | PASS: 8 tài liệu, 14 entities, 17 FR, 12 NFR, 40 BR không trùng ID; liên kết local/fences/reference hợp lệ; 4 Mermaid blocks được rà source |

Regression tests trên chỉ kiểm PHASE 1. Không chạy migration/seed, không truy cập MySQL credentials, không dùng SQLite để khẳng định concurrency đúng. Mermaid được kiểm tra source/cardinality thủ công, không xuất ảnh binary hoặc tạo Figma file; chưa chạy renderer trực quan.

## Known assumptions

VND/VN; phí ship/discount0, chưa tax/commission; admin duyệt Shop/Product, seller không tự mua; Seller xác nhận giao/thu COD; một checkout một Address; full ShopOrder cancellation, chưa returns; account/shop suspension chặn bán mới, admin xử lý nghĩa vụ cũ. Unique email/slug/Shop owner giữ qua soft delete. Không đổi owner Shop; không email verification/password reset/change credentials ở MVP cơ bản. Xem A-01…A-11 trong requirements.

## Remaining risks / trước PHASE 3

Không có quyết định nghiệp vụ blocking còn bỏ trống cho schema MVP theo baseline đã chọn. Khi bắt đầu PHASE 3 cần:

- Kiểm tra database/tài khoản ứng dụng, quyền và port thực tế trong .env local; không gửi secrets vào chat/README, không migrate:fresh hay xóa DB.
- Kiểm kê scaffold migrations hiện có và quyết định alter users tương thích; FK/UNIQUE/CHECK/decimal/timestamps phải được kiểm trên MySQL đang chạy.
- Dùng các assumptions hiện tại làm baseline; nếu muốn thu phí ship, nhiều Shop/User hoặc thanh toán một lần toàn Order thì điều chỉnh thiết kế trước code liên quan.

Rủi ro vận hành để phase tương ứng xử lý: COD confirmation dựa Seller (cần đối soát nếu public launch); lost/damaged shipment/returns chưa có workflow; ảnh snapshot cần cleanup bảo toàn references; LIKE search cần benchmark; session hostname phải thống nhất ở phase auth; transaction lock/retry/idempotency cần MySQL integration tests. VNPay callback trễ/expiry/refunds và auction settlement phải đặc tả kỹ khi tới future phase.

Composer -V trong phiên quyền người dùng có cảnh báo Git dubious ownership do repo thuộc sandbox; Laravel tests vẫn PASS. Không sửa global Git config; nếu cần chạy Composer lần sau dùng safe.directory chỉ trong environment của process như PHASE 1. Không có lỗi ứng dụng chưa xử lý hoặc dependency thay đổi trong PHASE 2.
