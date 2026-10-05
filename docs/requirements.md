# Requirements — Sàn thương mại điện tử thanh lý đồ cũ

> PHASE 2: đặc tả thiết kế, chưa triển khai. Hiện chỉ health API và trang kiểm tra kết nối của PHASE 1 hoạt động. Quy tắc chi tiết: [Business rules](business-rules.md); schema: [Database](database-design.md); quyền: [Permissions](permissions.md).

## 1. Giới thiệu hệ thống

Marketplace nhiều người bán. Vue SPA gọi Laravel REST API qua Axios; Eloquent làm việc với MySQL. Một User vừa mua hàng vừa bán qua Shop sở hữu; Buyer/Seller là vai trò nghiệp vụ, không phải hai loại tài khoản.

## 2. Mục tiêu

Cho phép đăng bán đồ cũ có kiểm duyệt, tìm kiếm, đặt hàng nhiều shop và theo dõi từng phần đơn độc lập. Giữ nguyên lịch sử giá/địa chỉ, tránh bán vượt tồn kho và ngăn truy cập chéo shop. Thiết kế đủ cụ thể để viết migration, policy, service và feature test ở các phase sau.

## 3. Phạm vi

MVP dùng VND, COD, mỗi User tối đa một Shop và một Cart. Một checkout lấy toàn bộ Cart, tách theo Shop; hủy theo toàn bộ ShopOrder, chưa hủy từng dòng. Tài liệu không đồng nghĩa các API đã tồn tại. Không tạo migration/model/controller hoặc Vue screen trong PHASE 2.

## 4. Actors

| Actor | Vai trò |
|---|---|
| Guest | Đăng ký/đăng nhập, xem dữ liệu công khai |
| User / Buyer | Profile, Address, Cart, checkout, đơn của mình, review |
| User / Seller | User sở hữu Shop; quản lý Shop, Product và ShopOrder của Shop đó |
| Admin | System role ADMIN; quản trị, kiểm duyệt và xử lý ngoại lệ có lý do/audit |

System role chỉ USER hoặc ADMIN. Shop ownership không trao quyền admin. Admin có thể mua hàng/tạo Shop theo cùng quy tắc User; không được bypass tồn kho, snapshot hoặc state machine.

## 5. Functional Requirements

| ID | Yêu cầu và tiêu chí chấp nhận |
|---|---|
| FR-AUTH-001 | Register/login/logout/me bằng Sanctum SPA cookie/session/CSRF; sai credential trả lỗi chung; logout vô hiệu hóa session; không lưu access token localStorage |
| FR-USER-001 | User đọc/sửa name, phone, avatar của mình; không tự đổi role/status; tài khoản bị khóa không thực hiện mutation |
| FR-ADDRESS-001 | CRUD địa chỉ của mình, tối đa một địa chỉ mặc định; địa chỉ dùng trước đó bị sửa/xóa không tác động đơn |
| FR-SHOP-001 | Một User tạo tối đa một Shop PENDING; owner chỉnh sửa, admin duyệt/tạm ngưng/từ chối với lý do |
| FR-CATEGORY-001 | Admin quản lý cây parent_id không có chu trình; danh mục vô hiệu hóa không được dùng để bán mới |
| FR-PRODUCT-001 | Owner tạo/sửa/ẩn/xóa mềm Product của Shop; condition độc lập status, giá và tồn kho được validate; admin kiểm duyệt |
| FR-IMAGE-001 | Upload nhiều ảnh hợp lệ, sắp xếp/chọn ảnh chính; không lưu binary trong DB; ảnh snapshot đơn phải còn truy cập được |
| FR-SEARCH-001 | Public tìm keyword, category, condition, price range, shop; sort newest/price_asc/price_desc; phân trang có giới hạn và thứ tự ổn định |
| FR-CART-001 | Cart nhiều Shop, một dòng/product; thêm/sửa/xóa quantity của mình, kiểm tra trạng thái/stock; Cart không giữ chỗ |
| FR-CHECKOUT-001 | Backend đọc lại giá/tồn kho dưới lock trong transaction; tạo Order, ShopOrders, snapshots, COD payment của Order; giảm stock, xóa cart nguyên tử và chống checkout lặp |
| FR-ORDER-001 | Buyer xem Order và từng ShopOrder; hủy toàn bộ ShopOrder PENDING/CONFIRMED; không truy cập đơn của Buyer khác |
| FR-PAYMENT-001 | Order hasMany Payments; một COD attempt PENDING cho toàn checkout. Chỉ tổng hợp PAID khi mọi phần chưa hủy đã COMPLETED và thu đủ. Seller revenue vẫn theo ShopOrder COMPLETED |
| FR-REVIEW-001 | Buyer review đúng OrderItem đã COMPLETED, một review/item, rating 1–5; sửa nội dung của mình, admin ẩn/hiện có audit |
| FR-SELLER-001 | Seller xem và chuyển trạng thái ShopOrder sở hữu; không thay đổi ShopOrder bên khác hoặc snapshot/giá của đơn |
| FR-REVENUE-001 | Seller dashboard đếm sản phẩm/đơn, biểu đồ doanh thu theo ngày hoàn tất và đơn gần nhất; doanh thu = subtotal của ShopOrder COMPLETED |
| FR-ADMIN-001 | Quản lý User/Shop/Category/Product/Review; xem đơn/payment và thống kê; không xóa hay sửa tùy ý lịch sử thương mại |
| FR-AUDIT-001 | Ghi actor, hành động, đối tượng, trạng thái trước/sau, lý do, thời gian cho moderation, chuyển trạng thái đơn/payment; bảo vệ audit khỏi sửa/xóa qua API |

## 6. Non-functional Requirements

| ID | Mục tiêu thực tế và cách kiểm chứng ở phase triển khai |
|---|---|
| NFR-MAINT-001 | Controller mỏng; Form Request, API Resource, Policy; nghiệp vụ trong Service/Action, transaction boundary rõ; không thêm Repository Pattern mặc định |
| NFR-SEC-001 | Hash password bằng Laravel; session regenerate sau login; CSRF mutation cho Sanctum SPA, cookies HttpOnly/Secure ở HTTPS production, credentials CORS chỉ origin tin cậy; không disable CSRF để chữa lỗi |
| NFR-SEC-002 | Backend authorize từng object, query scope theo chủ thể, validation và mass-assignment allowlist; ORM/binding, sort whitelist; không ghép SQL từ input |
| NFR-SEC-003 | Rate limits ban đầu: login 5/phút theo IP+email chuẩn hóa, register 5/giờ/IP, public read 120/phút/IP, mutation 60/phút/User, checkout 5/phút/User, upload 20/phút/User; điều chỉnh theo kiểm thử |
| NFR-SEC-004 | Ảnh JPEG/PNG/WebP, MIME thực + decode, tối đa 5 MiB/ảnh, tối đa 8 ảnh/product và 20 megapixel/ảnh; tên server sinh; không SVG/script; giới hạn tổng request; kiểm thử file giả extension |
| NFR-SEC-005 | Không expose .env, password hash, session/token, payment secrets, raw metadata/log; DTO public không có PII; lỗi 500 không có stack trace ở production |
| NFR-CONS-001 | Tiền DECIMAL(15,2), API chuỗi hai số lẻ; Product PHASE 6 nhận tối đa2 số lẻ, Order/Payment chưa triển khai; decimal arithmetic, không FLOAT; checkout/cancel/complete dùng transaction và lock có thứ tự; kiểm thử 2 buyers tranh stock=1 |
| NFR-PERF-001 | per_page mặc định 20/tối đa 100; eager loading tránh N+1; mục tiêu local listing p95 dưới 1 giây với 10.000 Product và 10 request đồng thời, đo trên cấu hình máy ghi trong báo cáo; chưa phải SLA |
| NFR-UI-001 | Vue responsive ở 360px và desktop, keyboard navigation, label/form errors, trạng thái loading/empty/error, tiền và ngày định dạng nhất quán |
| NFR-AUDIT-001 | Audit mutation quan trọng commit cùng dữ liệu; timestamps UTC, UI/report nhóm ngày Asia/Ho_Chi_Minh; không lưu secret/địa chỉ đầy đủ trong audit |
| NFR-TEST-001 | Feature tests cho auth, policies, validation, giá snapshot, checkout rollback/idempotency/overselling, COD, reviews; MySQL thật cho kiểm thử lock/FK/check, không dựa SQLite để kết luận concurrency |
| NFR-AVAIL-001 | Phù hợp đồ án một server local, health endpoint, log lỗi, hướng dẫn backup/restore trước demo; không cam kết HA hoặc uptime doanh nghiệp |

Security là yêu cầu thiết kế cho phase sau, không tuyên bố đã có triển khai. Khi tới auction, transaction/lock phía backend phải quyết định bid/winner; WebSocket chỉ phát thông tin sau commit.

## 7. MVP

FR-AUTH đến FR-AUDIT ở trên. Thứ tự thực hiện giữ nguyên roadmap: schema/model/seed → auth → User/Shop/Category → Product/Image/Search → Cart → Order/COD → Review → Seller → Admin. Mỗi module có kiểm thử trước khi sang module tiếp theo.

## 8. Future Scope

VNPay theo Order và nhiều payment attempt; notifications → chat Buyer/Seller → auctions/bids; Reverb/Echo, Docker/Nginx/deployment sau core ổn định. Không đưa future tables vào migration MVP. Xem phần Future trong database/API/state docs.

## 9. Assumptions

| ID | Quyết định baseline |
|---|---|
| A-01 | VND, chưa VAT/commission/coupon; shipping_fee và discount_amount = 0.00 ở MVP. Hiển thị rõ miễn phí vận chuyển ở checkout, không tự suy ra phí thực tế |
| A-02 | Shop mới phải admin duyệt. Product phải duyệt; sửa nội dung/giá/danh mục/condition/ảnh của hàng đã duyệt đưa về PENDING |
| A-03 | Buyer không được mua sản phẩm Shop chính mình, kể cả Admin đang mua dưới vai trò User |
| A-04 | Seller xác nhận đã giao và thu COD; admin được xử lý thay khi có bằng chứng/lý do. Chưa tích hợp hãng vận chuyển hay đối soát ngân hàng |
| A-05 | Không đổi người nhận/địa chỉ sau checkout; hủy phần đơn còn đủ điều kiện và đặt lại. Không đổi chủ Shop ở MVP |
| A-06 | Không guest cart/checkout; không stock reservation riêng. Hủy trả stock đúng một lần; không hỗ trợ partial item cancellation/returns ở MVP |
| A-07 | Payment thuộc Order, không có shop_order_id. Nhiều attempts cho toàn checkout; COD chưa thu xong vẫn PENDING. Tiền COD thu từng phần suy ra từ ShopOrders COMPLETED, không phải Payment riêng |
| A-08 | Shop/User bị khóa không nhận đơn mới; admin hoàn tất/hủy nghĩa vụ đang mở theo state machine. Không tự hủy hàng loạt khi suspension |
| A-09 | Một User giữ tối đa một Shop kể cả soft-deleted; khôi phục record cũ thay vì tạo thêm. Email/slug vẫn giữ unique khi soft-delete |
| A-11 | Admin không tự duyệt Shop/Product mình sở hữu; dùng admin khác cho moderation. Account admin demo không cần có Shop |
| A-10 | Chưa cần email verification/password reset/đổi email/password trong MVP auth cơ bản; đánh giá bổ sung trước public deployment |

Các assumption là lựa chọn đủ để tiếp tục thiết kế, không phải blocker cần người dùng xác nhận ngay. Các giá trị vận hành có thể điều chỉnh trước phase triển khai tương ứng.

## 10. Out of Scope

Phase 2 không code business logic, migration, schema thực, auth, CRUD, UI chức năng, VNPay, Reverb hoặc Docker. MVP không có fulfillment integration, thuế/commission, voucher, refund/return workflow, chat nhóm, nhiều Shop/User, đa tiền tệ, khách vãng lai checkout. Không lưu dữ liệu thẻ. Tài liệu future chỉ chỉ ra điểm mở rộng và invariant cần giữ.
