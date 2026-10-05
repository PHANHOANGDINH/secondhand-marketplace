# API design — /api/v1

> PHASE 11 bổ sung Admin Dashboard, User status management, Review moderation và Order overview chỉ đọc vào PHASE1–10. PHASE 12 bổ sung ZaloPay V2, giữ COD; phạm vi VNPay dự kiến đã được thay thế. Refund, role/archive management, admin ShopOrder mutation và các endpoint future bên dưới chưa triển khai. Xem [báo cáo PHASE 11](phase-11-verification.md) và [kiểm chứng PHASE 12](phase-12-verification.md). Giữ Form Request + Policy + Service + API Resource và Sanctum SPA auth.

## 1. Quy ước chung

Base path `/api/v1`; resource IDs là chuỗi số ở JSON. Public Product/Shop detail dùng `{slug}` global unique; route seller/admin dùng `{product}`, `{shop}` ID. Tất cả request JSON có `Accept: application/json`, `Content-Type: application/json`; upload dùng multipart/form-data. Tiền là string fixed decimal hai số lẻ; Product PHASE 6 nhận tối đa 2 số lẻ trong DECIMAL(15,2); timestamps ISO-8601 UTC. Không nhận extra sensitive fields như owner_id/buyer_id/role/price ở checkout; validation allowlist và explicit reject fields trái contract.

Pagination: page>=1, per_page mặc định20/tối đa100. List có `data`, `links`, `meta` theo Laravel paginator (current_page,from,to,last_page,path,per_page,total). Không wrap thêm success/code/result. Tất cả sort có id tie-break; giới hạn có thể cập nhật theo đo tải. Query filter unknown/sai type →422. Single resource `{"data":{...}}`; action không resource `{"message":"..."}`; DELETE/logout thành công204 body rỗng.

Health là ngoại lệ tương thích PHASE 1: giữ nguyên `{"status":"ok","message":"Secondhand Marketplace API is running"}`, không thêm data envelope, không yêu cầu auth/DB.

### Sanctum SPA — triển khai PHASE 4

GET `/sanctum/csrf-cookie` (ngoài /api/v1 theo Sanctum) → POST auth/register hoặc login với cookie/X-XSRF-TOKEN → session regenerate → GET auth/me. Authenticated API dùng auth:sanctum + active-account middleware; stateful API middleware/CORS/session/cookie cùng hostname đúng cấu hình. Axios dùng withCredentials/withXSRFToken và auth helper lấy CSRF cookie trước login/register/logout; không tự đọc document.cookie. Không phát access token localStorage. Production HTTPS, HttpOnly session cookie, bảo vệ CSRF cho mutations; callback gateway future sẽ có cơ chế xác minh riêng. Tham khảo [Laravel authentication](https://laravel.com/framework/docs/13.x/authentication) và [SPA CSRF](https://laravel.com/framework/docs/13.x/csrf).

## 2. Public API

| Method + path (prefix /api/v1) | Input / output | Auth / success |
|---|---|---|
| GET /health | Contract PHASE1 ở trên | Public,200; đã có |
| GET /categories | parent_id optional, mặc định roots; chỉ ACTIVE dưới ancestors ACTIVE, paginated | Public,200 |
| GET /categories/{slug} | Category ACTIVE và mọi ancestor ACTIVE | Public,200/404 |
| GET /shops | search? theo tên, page/per_page; Shop và owner ACTIVE chưa soft-delete | Public,200 |
| GET /products | Các filters ở §7, paginated Product summary | Public,200 |
| GET /products/{slug} | Product detail, Shop/category summary, ảnh; average_rating/review_count từ reviews PUBLISHED hợp lệ | Public,200/404 |
| GET /products/{slug}/reviews | PUBLISHED reviews paginated, author display name; Product phải public | Public,200/404 |
| GET /shops/{slug} | Public Shop; hàng của shop qua GET products?shop={id} | Public,200/404 |
| POST /auth/register | name,email,password,password_confirmation; role do server gán USER | Guest+CSRF,201 User và session |
| POST /auth/login | email,password,remember? boolean; lỗi credential message chung | Guest+CSRF,200 User và session |

Auth input: name 2–100, email hợp lệ <=254 normalized, password tối thiểu12 ký tự có chữ và số, không quá72 byte (giới hạn bcrypt hiện tại), confirmation khớp; User hashed cast thực hiện hash một lần. Rate limit theo NFR. Không đăng ký email trùng record soft-deleted.

## 3. Authenticated API — User/Buyer

| Method + path | Input / scope / output | Success |
|---|---|---|
| GET /auth/me | Current User; không password/hash/token | 200 |
| POST /auth/logout | Invalidate session + regenerate CSRF token | 204 |
| GET /profile | Current User; tái sử dụng AuthUserResource | 200 |
| PATCH /profile | name?,phone?,avatar_path không nhận từ client; avatar qua upload riêng | 200 User |
| POST /profile/avatar | file ảnh cùng MIME/size policy, server sinh path | 200 User |
| GET /addresses | Own addresses, paginated | 200 |
| POST /addresses | recipient_name,phone,address_line,ward,district?,province,country_code=VN,is_default? | 201 Address |
| GET /addresses/{address} | Own only; không trả user_id | 200 |
| PATCH /addresses/{address}/default | Đặt địa chỉ của mình làm mặc định trong transaction | 200 Address |
| PATCH /addresses/{address} | Các trường address cho phép, own only | 200 Address |
| DELETE /addresses/{address} | Own; không ảnh hưởng snapshot Order | 204 |
| GET /cart | Current Cart resource với items, Product current price, unavailable reason, totals hiển thị | 200 |
| POST /cart/items | product_id,quantity (delta 1–99), upsert dưới Cart lock; không cart_id | 201 nếu dòng mới,200 nếu tăng dòng |
| PATCH /cart/items/{item} | quantity tuyệt đối 1–99, own Cart | 200 CartItem |
| DELETE /cart/items/{item} | Own Cart, kể cả item unavailable | 204 |
| DELETE /cart | Xóa toàn bộ items của own Cart, giữ Cart; gọi lặp vẫn204 | 204 |
| POST /checkout | address_id,payment_method=COD\|ZALOPAY; preferred_payment_method[] tùy chọn cho ZALOPAY; Idempotency-Key UUID header; không giá/totals | 201 Order với payment attempt; replay200 |
| GET /orders | Own Orders, overall_status? derived, page/per_page | 200 paginated |
| GET /orders/{order} | Own Order với snapshots, ShopOrders/items và safe payment summary | 200 |
| GET /orders/{order}/payments | Own Order payment attempts, không gateway secrets | 200 paginated |
| POST /orders/{order}/shop-orders/{shopOrder}/cancel | expected_status,reason; nested relation phải đúng Order | 200 Order đã tổng hợp lại |
| POST /order-items/{orderItem}/review | rating integer1–5,comment?<=2000; own completed Item | 201 Review |
| PATCH /reviews/{review} | Own rating/comment, không product_id/user_id/status | 200 Review |
| GET /products/{slug}/review-eligibility | Authenticated Buyer; own completed OrderItems của Product public, review riêng + can_review/can_edit_review; page/per_page | 200 paginated |

Không endpoint DELETE review trong MVP; author sửa, admin moderate. Profile email/password change chưa thuộc MVP. Address không arbitrary user_id. Order list pagination không serialize toàn bộ item graphs; detail endpoint mới trả đầy đủ.

### Cart implementation PHASE 7

Năm endpoints Cart dùng auth:sanctum + active-account + stateful session/CSRF theo architecture hiện tại. Cart được tạo khi cần dưới buyer row lock; chỉ đọc/sửa cart hiện tại. POST tăng quantity dòng cũ (200), tạo dòng mới (201); PATCH đặt quantity tuyệt đối (200); DELETE204. JSON POST chỉ product_id/quantity, PATCH chỉ quantity; cart_id/user_id/price/subtotal/field lạ bị422. Quantity1–99, tổng sau cộng cũng≤99; vượt stock hoặc sản phẩm mất khả dụng→409, own-shop/foreign-item→403.

GET trả `{data:{id,items,item_count,total_quantity,subtotal,available_subtotal,has_unavailable_items}}`. Mỗi item có id/quantity/unit_price/subtotal/available/unavailable_reason/max_quantity, Product summary (id/name/slug/condition/is_public), Shop summary (id/name/slug), primary_image qua ProductImageResource. POST/PATCH trả một CartItemResource, frontend GET lại Cart để đồng bộ tổng/badge. Không trả owner_id/moderation_reason hoặc internal paths.

Availability tái sử dụng ProductQuery::publicVisible (cả owner và category ancestors). Lý do: PRODUCT_UNAVAILABLE, INSUFFICIENT_STOCK, OWN_SHOP; không tiết lộ lý do moderation. Item unavailable giữ nguyên cho phép remove/clear; quantity thiếu stock có thể giảm xuống max_quantity hiện tại. Product soft-deleted vẫn có summary tối thiểu qua quan hệ withTrashed, không có public link hoạt động.

unit_price luôn từ Product hiện tại; subtotal mỗi dòng = bcmul(price,quantity,2). Tổng subtotal gồm toàn bộ dòng còn có giá, kể cả unavailable; available_subtotal chỉ gồm dòng available. Mọi amount là chuỗi decimal, không lưu giá/tổng vào cart_items. GET eager load quan hệ, không query từng item. Frontend tự group items theo Shop; badge là total_quantity kể cả unavailable. Cart không reserve/decrement stock; Checkout PHASE 8 đọc và kiểm tra lại giá, tồn kho, trạng thái và giới hạn tổng tiền.

## 4. Seller API — cùng User, ownership bắt buộc

| Method + path | Input / scope / output | Success |
|---|---|---|
| POST /shops | name,description?,logo chưa upload; owner=current User, status=PENDING | 201 Shop |
| GET /seller/shop | Own Shop kể cả PENDING/REJECTED/SUSPENDED (account vẫn ACTIVE) | 200/404 |
| PATCH /seller/shop | name,description?; không owner_id/slug/status | 200 Shop |
| POST /seller/shop/logo | file hợp lệ, server path | 200 Shop |
| POST /seller/shop/submit | REJECTED→PENDING, hồ sơ hợp lệ | 200 Shop |
| GET /seller/products | Own Shop, status?,search? (keyword alias),page/per_page | 200 paginated |
| POST /seller/products | name,description,category_id,price,stock_quantity,condition; server DRAFT/Shop | 201 Product |
| GET /seller/products/{product} | Own, cả chưa public, không soft-deleted | 200 |
| PATCH /seller/products/{product} | Editable fields; stock edit cần expected_updated_at | 200 Product |
| POST /seller/products/{product}/submit | DRAFT/HIDDEN→PENDING | 200 Product |
| POST /seller/products/{product}/withdraw | PENDING→DRAFT | 200 Product |
| POST /seller/products/{product}/revise | REJECTED→DRAFT | 200 Product |
| POST /seller/products/{product}/hide | ACTIVE/SOLD→HIDDEN | 200 Product |
| DELETE /seller/products/{product} | Soft-delete own; giữ history/ảnh snapshot | 204 |
| POST /seller/products/{product}/images | images[] multipart, tối đa 8 ảnh/Product, mỗi≤5MiB/20MP, JPEG/PNG/WebP | 201 collection images |
| PATCH /seller/products/{product}/images/{image} | is_primary=true hoặc sort_order; không đổi path trực tiếp | 200 Image |
| PATCH /seller/products/{product}/images/reorder | image_ids là toàn bộ IDs hiện tại, không trùng/khác Product; transaction | 200 collection images |
| DELETE /seller/products/{product}/images/{image} | Chọn primary thay thế trong transaction; hết ảnh→PENDING nếu đang ACTIVE/HIDDEN, không publish | 204 |
| GET /seller/orders | Own ShopOrders, status?,from?,to?,page/per_page | 200 paginated |
| GET /seller/orders/{shopOrder} | Own ShopOrder/items, address snapshot cần giao | 200 |
| PATCH /seller/orders/{shopOrder}/status | expected_status,status; cancel cần reason + failure_category=OUT_OF_STOCK/DAMAGED/UNFULFILLABLE; complete cần cod_collected=true | 200 ShopOrder |
| GET /seller/dashboard | from/to theo ngày Việt Nam; tồn sản phẩm hiện tại, đơn tạo trong kỳ, doanh thu hoàn tất trong kỳ và5 recent orders | 200 summary |
| GET /seller/dashboard/revenue | from/to, group_by=day/month; SUM subtotal COMPLETED theo completed_at; chuỗi ngày/tháng có kỳ rỗng bằng0 | 200 revenue series |

Product body: name<=200, description<=10000, price chuỗi DECIMAL(15,2)>0, tối đa13 số nguyên và2 số lẻ, stock_quantity integer>=0 <=2^32−1, condition whitelist, category tồn tại/được phép. Không cho arbitrary status trong Product CRUD; actions/state table quyết định status. PATCH stock sold>0 đưa về PENDING, ACTIVE stock=0 do service đưa SOLD. Reorder dùng PATCH images/reorder với toàn bộ image_ids; PATCH từng ảnh hỗ trợ is_primary=true hoặc một sort_order trong dãy hiện tại (không gửi cả hai). Mọi image mutation dùng cùng moderation rule với nội dung Product.

## 5. Admin API

Mọi path `/admin/*` cần ADMIN ACTIVE và policy, mutation nhận `reason` <=1000, không dùng unbounded fill(request.all()). Các danh sách đều phân trang theo quy ước.

| Method + path | Purpose / allowed fields | Success |
|---|---|---|
| GET /admin/users; GET /admin/users/{user} | search(name/email),role,status,page/per_page; gồm archived read-only; safe profile | 200 |
| PATCH /admin/users/{user}/status | expected_status,status,reason; ACTIVE↔SUSPENDED, không role/email/password | 200 |
| DELETE /admin/users/{user} | Soft-delete, reason body; không self/last-admin/open obligations | 204 |
| POST /admin/users/{user}/restore | reason; restore SUSPENDED, admin xét active riêng | 200 |
| GET /admin/shops; GET /admin/shops/{shop} | List: status?,search?,page/per_page; gồm cả archived; detail ID | 200 |
| PATCH /admin/shops/{shop}/status | expected_status,status,reason; legal transitions, không self-approve | 200 |
| DELETE /admin/shops/{shop}; POST /admin/shops/{shop}/restore | reason; archive only no open orders; restore PENDING | 204 / 200 |
| GET /admin/categories | Toàn bộ trạng thái, parent_id? | 200 |
| POST /admin/categories | name,parent_id?,description?,status?=ACTIVE,reason; slug server sinh | 201 |
| PATCH /admin/categories/{category} | name,parent_id?,description?,status?,reason; slug ổn định | 200 |
| DELETE /admin/categories/{category} | reason, chỉ leaf không có Products | 204 |
| GET /admin/products; GET /admin/products/{product} | status/search/shop/category filters, page/per_page; gồm cả archived, moderation details; chưa có deleted filter | 200 |
| PATCH /admin/products/{product}/status | expected_status,status,reason; approve/reject/hide, không sửa giá | 200 |
| DELETE /admin/products/{product}; POST /admin/products/{product}/restore | reason; soft-delete / restore DRAFT | 204 / 200 |
| GET /admin/reviews; GET /admin/reviews/{review} | status,product(ID),rating,page/per_page; gồm HIDDEN và Product archived | 200 |
| PATCH /admin/reviews/{review}/status | expected_status,status PUBLISHED/HIDDEN,reason; không sửa comment/rating | 200 |
| GET /admin/orders; GET /admin/orders/{order} | Read-only; overall_status,shop_order_status,buyer(ID),from/to,page/per_page | 200 |
| PATCH /admin/shop-orders/{shopOrder}/status | expected_status,status,reason, cod_collected?; shipping cancel cần return_verified=true và reason bằng chứng | 200 |
| GET /admin/payments | order/status/date filters, safe attempts | 200 |
| GET /admin/dashboard | All-time counts/revenue,5 recent orders/users; completed merchandise subtotal, không phải commission revenue | 200 |
| GET /admin/audit-logs | entity_type/entity_id/actor/date filters | 200 |

PHASE 11 chưa implement admin DELETE/restore User/Shop/Product, đổi role, PATCH admin ShopOrder, GET admin payments/audit-logs trong bảng trên; các dòng đó vẫn là thiết kế future.

Delete body được validate Form Request; frontend phải gửi qua Axios `data`. Không có admin delete Order/Payment, sửa snapshot hay PATCH Payment PAID tùy ý. API hành chính cần audit ngay cả các thay đổi không đổi state.

## 6. Response examples và HTTP statuses

Single resource (rút gọn trường để minh họa, không phải bỏ snapshot):

```json
{"data":{"id":"100","order_code":"SHM-DEMO-100","total_amount":"600000.00","payable_amount":"400000.00","cod_collected_amount":"100000.00","amount_due":"300000.00","overall_status":"IN_PROGRESS","payment_status":"PENDING","shop_orders":[{"id":"101","shop_id":"1","status":"COMPLETED","subtotal":"100000.00"},{"id":"102","shop_id":"2","status":"CANCELLED","subtotal":"200000.00"},{"id":"103","shop_id":"3","status":"SHIPPING","subtotal":"300000.00"}]}}
```

Collection:

```json
{"data":[],"links":{"first":"http://localhost:8000/api/v1/products?page=1","last":"http://localhost:8000/api/v1/products?page=1","prev":null,"next":null},"meta":{"current_page":1,"from":null,"to":null,"last_page":1,"path":"http://localhost:8000/api/v1/products","per_page":20,"total":0}}
```

Action / validation / generic error (ba response độc lập):

```json
{"message":"Shop submitted for review."}
```

```json
{"message":"The given data was invalid.","errors":{"quantity":["Quantity must be between 1 and 99."]}}
```

```json
{"message":"Stock or order state has changed. Please refresh and try again."}
```

| Status | Khi dùng |
|---|---|
| 200 OK | Read/update/action resource, checkout replay |
| 201 Created | Create resource/checkout mới |
| 204 No Content | Delete/logout thành công, không body |
| 400 Bad Request | JSON malformed; không dùng thay tất cả validation |
| 401 Unauthenticated | Thiếu/hết hạn session |
| 403 Forbidden | Sai quyền/owner, Seller A cố cập nhật ShopOrder B, tự mua hàng mình |
| 404 Not Found | Không tồn tại hoặc public record không được public |
| 409 Conflict | Hết stock, state conflict, duplicate review/Shop, reused key khác payload, FK delete conflict |
| 413 Payload Too Large | Tổng upload bị web server/PHP giới hạn |
| 419 CSRF/session expired | SPA mutation thiếu/sai CSRF; frontend refresh cookie/login thích hợp, không tự replay checkout key mới |
| 422 Validation Error | Sai input/enum/rating/quantity, cart rỗng, credential không hợp lệ; errors theo field |
| 429 Too Many Requests | Rate limit, Retry-After |
| 500 Internal Server Error | Lỗi bất ngờ; log server, response message chung, không stack/secrets |

Enum/action guard conflict là409, forbidden actor là403; không trả422 cho cùng lỗi authorization ở endpoint khác. Cart GET với item unavailable vẫn200 để Buyer có thể xóa; checkout bị item unavailable409. Rating review item chưa completed409; item không thuộc buyer403.

## 7. Search/filter/sort contract

Ví dụ:

```http
GET /api/v1/products?search=laptop&category=3&condition=GOOD&min_price=1000000&max_price=5000000&shop=12&sort=price_asc&page=1&per_page=20
```

| Parameter | Validation / semantics |
|---|---|
| search (keyword alias) | Không gửi cả hai; trim, tối đa100, tìm name/description LIKE bằng binding, escape % và _ như ký tự literal |
| category | ID active category; bao gồm descendants active; không tồn tại →422 |
| condition | Một trong LIKE_NEW/GOOD/FAIR/POOR |
| min_price,max_price | Chuỗi decimal >=0, tối đa2 số lẻ trong DECIMAL(15,2), min<=max; inclusive |
| shop | Shop ID hợp lệ public; không public →422 message chung |
| sort | newest = created_at DESC,id DESC; price_asc = price ASC,id ASC; price_desc = price DESC,id DESC |
| page,per_page | Positive integer, 20 default, max100 |

Không cho public status filter để lộ pending/hidden; giá/stock/status luôn đọc DB. Không nhận field tùy ý làm ORDER BY. Query Product detail/link slug nhưng filter shop/category dùng ID; resource trả cả id và slug để frontend xây URL.

ProductResource trả id/name/slug/description/price/stock_quantity/condition, images/primary_image, shop và category chỉ id/name/slug, created_at/published_at. ManagedProductResource dùng chung payload, thêm status/moderation_reason/updated_at/archived/can_edit/can_moderate/shop_status cho seller/admin. ProductImageResource chỉ id/url/is_primary/sort_order. Public detail không đủ availability →404; seller sai owner →403. Admin expected_status stale hoặc transition sai →409; reason bắt buộc. Admin không tự duyệt sản phẩm mình. Image upload throttle 20 requests/phút/tài khoản; file không hợp lệ →422, tổng POST vượt giới hạn PHP →413.

## 8. Future API — không implement/MVP migration

| Group | Endpoint dự kiến | Boundary |
|---|---|---|
| Notifications | GET /notifications; PATCH /notifications/{id}/read | Own recipient; broadcast private authorized |
| Chat | POST /conversations; GET /conversations; GET/POST /conversations/{id}/messages | Buyer và owner Shop; optional Product context; pagination/cursor messages khi triển khai |
| Auctions | GET /auctions; GET /auctions/{id}; POST /auctions/{id}/bids | Server time/stock/sale_mode/lock/authorization; mọi amount verified server |
| Seller auction | POST /seller/products/{product}/auction | Own eligible Product, AUCTION sale mode, schedule guard |

Các contract notification/chat/auction còn phải chi tiết tại phase tương ứng; không tạo dummy routes. Docker/Deployment không có API business. Không thay endpoint health PHASE 1. Phạm vi gateway PHASE 12 dùng ZaloPay V2 theo contract bên dưới; không có route VNPay.

## Payment revision PHASE 3

Payment thuộc Order, không ShopOrder. Với COD, Seller complete chỉ xác nhận giao/thu tiền phần mình; backend Order aggregate action mới chốt Payment PAID. Một phần hủy cần replacement COD attempt giữ lịch sử amount; BR-PAYMENT-005. ZaloPay PHASE 12 thu toàn Order bằng các endpoint sau, không phí riêng mỗi shop. Seller không có API đọc payment attempts toàn Order.

### ZaloPay V2 implementation PHASE 12

| Endpoint `/api/v1` | Contract | Kết quả |
|---|---|---|
| POST /orders/{order}/payments/zalopay | Buyer own; Idempotency-Key UUID; preferred_payment_method[] tùy chọn, không amount/status | OrderResource; attempt mới nếu attempt trước FAILED và Order còn đủ điều kiện; replay cùng key/payload không tạo thêm |
| POST /orders/{order}/payments/zalopay/query | Buyer own; body rỗng; backend gọi query với reference DB | OrderResource với trạng thái đã đối chiếu |
| GET /orders/{order}/payments | Buyer own, pagination hiện có | Safe PaymentResource attempts |
| POST /payments/zalopay/callback | Public server callback; data string, mac, type=1; Key2 verification | return_code=1 khi đã tiếp nhận/idempotent; -1 nếu chữ ký/identity/amount/reference không hợp lệ |

Checkout/retry/query giữ Sanctum SPA session, active account, ownership, CSRF và throttle. Chỉ callback được miễn CSRF và trim chuỗi để xác minh HMAC trên `data` nguyên bản; callback vẫn validate payload, verify Key2 và throttle. Create/query dùng Key1 server-side; không serialize khóa, chữ ký, raw payload hoặc gateway transaction reference. Amount VND nguyên dương từ DB; giá lẻ bị 422, có thể dùng COD. Gateway credentials/config chỉ qua env/config.

`preferred_payment_method` là mảng allowlist: `international_card`, `domestic_card`, `account`, `zalopay_wallet`, `vietqr`; mảng rỗng để Gateway hiển thị các kênh khả dụng. Frontend chỉ chọn COD hoặc ZaloPay Online rồi chuyển tới `payments[].order_url` do backend kiểm tra HTTPS host ZaloPay. Không nhập/lưu thông tin thẻ trên ứng dụng. `/payment/result?order=<id>` là frontend route; query string chỉ xác định Order cần tải, không quyết định PAID. Trang gọi API để lấy trạng thái thực.

PaymentResource thêm `order_url` (chỉ PENDING, chưa hết hạn và không reconciliation), `expires_at`, `reconciliation_required`. OrderResource thêm `can_retry_payment` và `reconciliation_required`; trạng thái online lấy từ attempts đã xác minh, không suy ra từ giao hàng. Seller chỉ tiến workflow online sau PAID và không dùng `cod_collected`.

Create/query chạy ngoài transaction; cập nhật trạng thái dưới Order lock. Timeout/trạng thái chưa rõ/thiếu tiền giữ PENDING; query xác nhận terminal mới cho retry. Retry UUID tạo attempt mới, không thay attempt cũ và không đụng stock. Hủy online chưa trả tiền giữ workflow hoàn stock đúng một lần; hủy một ShopOrder vô hiệu hóa attempt toàn Order, các phần còn lại phải hủy riêng trước khi đặt lại. PAID/reconciliation không cho hủy trong phase này. Late callback sau hủy ghi reconciliation và chặn fulfillment/retry; không hồi sinh đơn. Không tự hoàn stock khi attempt hết hạn.

`php artisan zalopay:reconcile --limit=100` được schedule mỗi phút, query các attempts chưa giải quyết có expiry trong 7 ngày gần nhất hoặc tương lai; mỗi lần tối đa100 theo mặc định. Cần chạy Laravel scheduler ở môi trường vận hành; nút kiểm tra trạng thái vẫn hoạt động khi local chưa chạy scheduler. Không có refund endpoint.

## Auth implementation PHASE 4

Local frontend http://localhost:5173, backend http://localhost:8000; SANCTUM_STATEFUL_DOMAINS=localhost:5173; không trộn 127.0.0.1 trong browser URL. Database vẫn DB_HOST=127.0.0.1. Laravel 13 statefulApi(), Sanctum dùng PreventRequestForgery hiện hành, không thêm CSRF exceptions.

Register trả201 với message và data; login/me trả200 với data; logout trả204 rỗng. AuthUserResource allowlist: id string, name, email, phone, avatar_path, role/status enum values, email_verified_at, created_at. Không password/remember_token/deleted_at. role/status/email_verified_at/remember_token từ đăng ký bị prohibited; các field ngoài allowlist không được lưu. Duplicate email kể cả soft-deleted trả422.

Login sai mật khẩu, email không tồn tại, suspended hoặc soft-deleted đều trả422 với errors.email chung. User đã có session gọi login/register với payload hợp lệ trả409, không tự chuyển tài khoản. Me guest trả401; me suspended trả403; logout vẫn được phép cho suspended session. Mutating auth endpoints yêu cầu stateful first-party session, không dùng chúng làm token API.

Login throttle 5/phút với key hash email lowercase/trim + IP; register 5/giờ/IP, gồm cả request validation thất bại. Hết quota trả429/Retry-After. CSRF thiếu/sai trong SPA cross-origin cùng site trả419. UI không tự replay vô hạn: lần thử lại chủ động lấy CSRF cookie mới.

CORS allowlist đúng một origin localhost:5173 và credentials=true. Thư viện CORS hiện tại có thể trả Access-Control-Allow-Origin cố định bằng origin đã cấu hình ngay cả cho request origin khác; browser vẫn từ chối vì không khớp. Negative tests kiểm tra không wildcard/không phản chiếu untrusted origin, thay vì giả định header luôn bị bỏ hoàn toàn.

## PHASE 5 — chi tiết contract đang thực thi

- Giữ `POST /shops` đã chốt để tạo gian hàng; không tạo thêm POST /seller/shop. `/seller/shop` đọc/sửa gian hàng của tài khoản hiện tại, `/submit` chỉ REJECTED→PENDING.
- Profile chỉ nhận name (2–100) và phone nullable (≤20, kiểm tra format cơ bản). Email/password/role/status/email_verified_at/deleted_at/avatar_path bị từ chối422 khi gửi trong body.
- Address dùng `country_code=VN`, district nullable, không có field `country`. Address đầu tiên luôn mặc định; thay default/delete dùng User lock. PATCH default không cần body. PUT /addresses/{address} là alias update do apiResource của Laravel; SPA dùng PATCH.
- Shop name2–150, description nullable≤10000; logo_path/owner_id/slug/status/deleted_at không nhận từ owner. Slug unique và giữ nguyên khi đổi tên; collision dùng hậu tố -2, -3, kể cả slug shop đã soft-delete.
- Public ShopResource không trả owner_id/moderation_reason/archived/deleted_at. ManagedShopResource chỉ dùng private seller/admin, thêm owner_id, archived boolean và moderation_reason để giải thích quyết định. Không có API sửa/xóa audit.
- Moderation luôn nhận expected_status/status/reason (reason≤1000). Thiếu/sai enum→422; sai quyền→403; stale hoặc transition trái phép→409. Không admin moderation shop của chính mình.
- Category name2–100, description nullable≤10000, parent_id nullable, status ACTIVE/INACTIVE. Create/update/delete đều cần reason≤1000. Không nhận slug hoặc sort_order (schema không có sort_order). API public list roots mặc định, `parent_id` lấy children, detail bằng slug; không thêm tree endpoint thứ hai.
- Public danh mục kiểm tra cả ancestor ACTIVE. Cây tối đa3 cấp tính cả root; cycle/self-parent/depth vượt giới hạn→422 ở parent_id. Delete có child hoặc Product kể cả soft-deleted→409. Category slug ổn định sau tạo.
- Lists dùng paginator data/links/meta, page≥1, per_page20/max100; unknown filters→422. Search Shop dùng binding và escape wildcard; chuỗi `0`, `%`, `_`, `!` được tìm như text.
- Public reads giới hạn120/phút/IP, private mutations60/phút/User. Auth limiters giữ nguyên. Session/CSRF/ACTIVE bắt buộc cho private APIs; ADMIN middleware + Policy kiểm tra ở backend.
- Single/mutation trả data resource theo convention đã chốt; create201, update200, delete204 rỗng. Chưa triển khai media/Product APIs liệt kê ở các mục future.

### Checkout / Orders implementation PHASE 8

Tám endpoints đang chạy: POST /checkout; GET /orders; GET /orders/{order}; GET /orders/{order}/payments; POST /orders/{order}/shop-orders/{shopOrder}/cancel; GET /seller/orders; GET /seller/orders/{shopOrder}; PATCH /seller/orders/{shopOrder}/status. Tất cả auth:sanctum + active account + SPA session + throttle; mutations CSRF. Pagination20 mặc định/max100; Buyer list lọc overall_status derived, Seller list status/from/to. Buyer cancel trả OrderResource để UI lấy totals/payment sau hủy.

OrderResource gồm shipping_address snapshot, shop_orders/items, payments safe summary, overall_status, payable_amount, cod_collected_amount, amount_due và payment_status. Seller chỉ nhận một ShopOrder với items, shipping_address cần giao và can_update; không có các phần đơn khác hoặc Payment toàn Order. OrderItem trả product_name/product_image_url/product_price/quantity/subtotal từ snapshot, không đọc lại Product price/name. Không expose checkout_key/request_hash/gateway reference hoặc owner internals.

Idempotency-Key UUID bắt buộc, normalize lowercase; payload hash canonical address_id dạng string + COD. Cùng key/payload trả200, kể cả Cart đã rỗng hoặc address sau đó bị xóa; khác payload409. New checkout201. Thiếu/sai field, Cart rỗng, address không thuộc Buyer, amount overflow422; unavailable/stock/stale state409; foreign ownership403. Checkout toàn Cart, không silently bỏ dòng lỗi. Money giữ hai chữ số lẻ theo DECIMAL/BCMath.

### Review implementation PHASE 9

POST /order-items/{orderItem}/review nhận rating integer1–5 và comment nullable≤2000; backend derive user_id/product_id/order_item_id, gán PUBLISHED. Unique OrderItem kể cả HIDDEN; chưa COMPLETED/duplicate409, foreign Buyer/own Shop403, validation/injection422. PATCH /reviews/{review} chỉ tác giả sửa rating/comment; giữ status/moderation_reason/linkage. Không DELETE trong MVP, chưa Admin moderation API/UI.

GET /products/{slug}/reviews là public, chỉ Product public theo ProductQuery và reviews PUBLISHED của purchase COMPLETED có buyer/product reference đúng, không tự mua Shop mình. Trả id/rating/comment/status/author.name/created_at/updated_at; không user_id/email/order_item_id/moderation_reason. Author đã soft-delete hiển thị “Người mua”. OrderItem private có review và can_review/can_edit_review; Seller Order response không có review private của Buyer. Product SOLD/hidden/soft-deleted vẫn đánh giá/sửa qua Order đã hoàn tất, nhưng public endpoint404 theo visibility hiện tại.

Pagination20 mặc định/max100, newest ID trước; UI10/trang. ProductResource average_rating là number làm tròn2 chữ số (null khi chưa có), review_count integer. COUNT/AVG trong SQL dùng cùng predicate với public list, không cột aggregate/cache, không N+1. Eligibility là endpoint private có pagination, không liệt kê OrderItems của User khác. Form gửi rating/comment, không HTML rendering của comment.

### Seller dashboard — PHASE 10

Hai endpoint GET ở trên dùng auth:sanctum + active account + SPA session, throttle domain và ShopPolicy::view. Shop suy ra từ User, không nhận shop_id/user_id; Admin chỉ đọc Shop mình. Guest401, chưa có Shop hoặc Shop đã soft-delete404; owner của Shop PENDING/SUSPENDED/REJECTED vẫn đọc thống kê. Unknown query fields hoặc date/group sai→422.

`from` và `to` cùng có hoặc cùng bỏ, YYYY-MM-DD, from≤to, tối đa366 ngày tính cả hai đầu; hỗ trợ2000-01-01..9998-12-31. Mặc định30 ngày lịch Việt Nam gồm hôm nay. `group_by` chỉ ở revenue endpoint, day mặc định hoặc month. Chuyển khoảng ngày Asia/Ho_Chi_Minh thành UTC `[from 00:00, to+1 ngày 00:00)` trước khi lọc cột; nhóm completed_at theo UTC+07, không cần bảng timezone MySQL. Tháng đầu/cuối chỉ tính phần nằm trong khoảng chọn.

Dashboard `data` gồm:

- `shop:{id,name,status}`, `range:{from,to,timezone}`.
- `total_products, active_products, pending_products, sold_products, product_counts`: số sản phẩm hiện tại, không soft-delete, không áp dụng date filter; product_counts gồm mọi ProductStatus.
- `total_orders, pending_orders, completed_orders, cancelled_orders, order_counts`: ShopOrders có created_at trong kỳ, đếm theo trạng thái hiện tại; order_counts gồm mọi ShopOrderStatus.
- `total_revenue` chuỗi decimal2 và `completed_in_range`: subtotal/count của COMPLETED có completed_at trong kỳ, kể cả đơn tạo trước kỳ. Vì phạm vi khác, completed_in_range có thể khác completed_orders.
- `recent_orders`: tối đa5 phần đơn tạo trong kỳ, created_at DESC rồi id DESC; mỗi row `{id,code,buyer:{name},total_amount,status,created_at}`. code là Order.order_code; id là ShopOrder.id để mở Seller order detail. Không email/phone/address/payment; Buyer đã xóa hiển thị “Người mua”.

Revenue `data` gồm `range`, `group_by`, `total_revenue` (decimal string), `completed_orders` (theo completion date), `series:[{period,revenue,completed_orders}]`. period là YYYY-MM-DD hoặc YYYY-MM, tăng dần, lấp kỳ rỗng bằng revenue="0.00"/count0. Không payment join, không cộng shipping/commission. COMPLETED thiếu completed_at không được tính. SQL COUNT/SUM giữ decimal; chỉ tải aggregate và5 recent records đã eager load. Mỗi response trong một read transaction; hai endpoint không phải một snapshot chung và không realtime.

### Admin management — PHASE 11

Tất cả9 endpoints mới dùng auth:sanctum + EnsureActiveAccount + EnsureSpaSession + EnsureAdmin + throttle domain và policy. Guest401, USER403; ADMIN ACTIVE được đọc. UserPolicy không cấp bypass cho Buyer/Seller actions. Lists newest ID trước, pagination20/max100, strict filter allowlist; unknown fields422.

Dashboard không nhận query filters, số liệu toàn thời gian: total_users/active_users, total_shops/active_shops/pending_shops, total_products/active_products/pending_products loại soft-delete; active là status của chính record. total_orders là số Order, completed_orders/cancelled_orders dùng overall status suy ra từ ShopOrders (mọi phần cùng COMPLETED/CANCELLED). total_revenue là SQL SUM(subtotal) chỉ COMPLETED ShopOrders, không join Payments, không shipping; cả phần COMPLETED của Order chưa hoàn tất toàn bộ vẫn được cộng. Vì không lọc ngày, completed_at null không loại một record COMPLETED khỏi tổng toàn thời gian. Tiền là decimal string2 số lẻ. Recent lists tối đa5, newest ID trước, users chỉ id/name/role/status/created_at; orders dùng overview resource, không Payment attempts/shipping PII.

User list/detail trả id/name/email/role/status/archived/can_moderate/created_at, không password/remember_token/addresses. Archived được đọc nhưng không đổi status. PATCH chỉ `{expected_status,status,reason}`; cùng trạng thái hoặc stale409; self/archived403. Reason required≤1000. Actor/target accounts khóa theo ID và recheck policy dưới lock, chặn self/last-active-admin và stale actor bị suspended. Audit `user.status_changed` cùng transaction, failure rollback; không đổi email/password/role. Auth cũ tiếp tục kiểm ACTIVE mỗi request: suspended403 protected access, logout vẫn204, login mới422 generic credentials. Restore ACTIVE không xóa lịch sử hoặc thay trạng thái Shop/Order/Review.

Review list/detail gồm rating/comment/status/moderation_reason, author id/name và Product summary (cả archived). PATCH chỉ đổi PUBLISHED↔HIDDEN với expected_status/reason; stale/same409, injection rating/comment/user_id/product_id422. Actor account và Review row lock, recheck Admin; audit `review.moderated` cùng transaction. Không sửa nội dung. Predicate public Reviews/average_rating/review_count hiện có tự loại HIDDEN và vẫn kiểm purchase hợp lệ khi unhide. Reason nội bộ không ra public.

Order overview mặc định mọi thời gian. `overall_status` theo OrderStatus enum, `shop_order_status` theo ShopOrderStatus enum (Order chứa ít nhất một phần khớp, không nhân đôi), `buyer` là ID. Nếu lọc ngày, from/to cùng có, YYYY-MM-DD,2000-01-01..9998-12-31, from≤to, tối đa366 ngày inclusive Việt Nam theo Order.created_at. Response whitelist Buyer id/name, snapshot totals, overall_status, ShopOrder summaries; detail có OrderItem name/price/quantity/subtotal/image snapshot. Không địa chỉ/phone/email Buyer, payment attempts hoặc review/cancel/fulfillment actions; không admin workflow mutation/refund. Soft-delete User/Shop/Product không làm mất lịch sử.
