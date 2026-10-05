# PHASE 12 — ZaloPay V2 Sandbox Payment

Status: **PASS — hoàn tất verification trong phạm vi PHASE 12**. Kiểm chứng ngày 2026-10-05; không bắt đầu PHASE 13.

## Phạm vi và endpoints

COD giữ nguyên. ZaloPay thay phạm vi VNPay dự kiến; Payment vẫn thuộc toàn Order, có lịch sử nhiều attempts.

- `POST /api/v1/checkout`: `payment_method=COD|ZALOPAY`; ZaloPay trả attempt PENDING và `order_url` khi gateway tạo thành công.
- `GET /api/v1/orders/{order}/payments`: Buyer đọc attempts của mình.
- `POST /api/v1/orders/{order}/payments/zalopay`: retry với UUID `Idempotency-Key`, attempt mới khi đủ điều kiện.
- `POST /api/v1/orders/{order}/payments/zalopay/query`: đối chiếu trạng thái gateway; Buyer own, CSRF.
- `POST /api/v1/payments/zalopay/callback`: callback public, xác minh Key2; chỉ route này được miễn CSRF.
- Frontend: checkout COD/ZaloPay Online, `/payment/result`, danh sách attempts và query/retry trong Order detail. Gateway thu thông tin card/account/wallet; ứng dụng không lưu số thẻ/CVV/tài khoản ngân hàng.

## Xác thực và idempotency

Create/query gọi V2 HTTPS server-side, Key1 từ env/config. Create ký HMAC SHA256 theo các trường chuẩn; `app_trans_id` unique bắt đầu ngày Việt Nam, amount/reference lấy từ DB. Amount VND phải nguyên dương, không làm tròn giá lẻ. `preferred_payment_method` hỗ trợ `international_card`, `domestic_card`, `account`, `zalopay_wallet`, `vietqr`; rỗng để Gateway chọn kênh. [Create](https://docs.zalopay.vn/vi/docs/specs/order-create/), [query](https://docs.zalopay.vn/vi/docs/specs/order-query/).

Callback xác minh HMAC Key2 trên chuỗi `data` nguyên bản, identity của app/attempt/buyer, amount và gateway transaction ID. Unique gateway ID ngăn một giao dịch chốt nhiều attempts. Query fallback dùng HTTPS response từ gateway cố định, gắn reference DB; browser redirect không thể đặt PAID. [Callback](https://docs.zalopay.vn/docs/specs/callback-api).

Order lock serialize callback/query/retry/cancellation. External HTTP nằm ngoài DB transaction; create claim một lần, replay checkout/retry cùng key không tạo thêm attempt hoặc charge request. Timeout, create duplicate, processing và thiếu tiền giữ PENDING; explicit create rejection hoặc query xác nhận expiry/không tồn tại sau grace mới cho FAILED/retry. [Official status codes](https://docs.zalopay.vn/docs/developer-tools/knowledge-base/status-codes/).

## Stock và hủy đơn

Checkout trừ stock trong transaction như COD; retry không trừ lần nữa. Attempt failed/expired không tự hoàn stock: giữ stock đến khi Buyer/Seller hủy phần đơn theo workflow hiện có. `stock_restored_at` bảo vệ hoàn đúng một lần.

Hủy một phần online chưa trả tiền vô hiệu hóa attempt toàn Order. Các phần còn lại phải hủy riêng và đặt lại nếu muốn mua; không tính lại amount hoặc thu một phần online. PAID/reconciliation chặn hủy vì refund ngoài scope. Callback thắng race → PAID, hủy trả 409; hủy thắng → stock hoàn một lần, tiền đến trễ chỉ ghi reconciliation, không hồi sinh Order. Attempt liên quan bị hủy có audit trong cùng transaction. Seller chỉ fulfill online sau PAID, không xác nhận thu COD.

`zalopay:reconcile --limit=100` được schedule mỗi phút; scan unresolved attempts có `expires_at >= now−7 days`, bao gồm expiry tương lai. Local chưa chạy scheduler vẫn có nút query. Không tự expiry Order hoặc triển khai reservation/refund framework.

## Kết quả kiểm chứng

| Kiểm tra | Kết quả |
|---|---|
| Backend full regression | PASS — 186 tests / 2,028 assertions; final run 149.163s, 0 failures |
| MySQL concurrency | PASS — 3 tests, có trong final full regression; lượt riêng trước đó ghi nhận 50 assertions; callback/cancel race và simultaneous retry |
| Frontend full regression | PASS — 150 tests / 12 files, 0 failures; giữ kết quả đã hoàn tất, không viết lại hoặc chạy lại ZaloPay tests |
| Pint | PASS — `--format agent` trên 28 PHP files của PHASE 12; `--dirty` không nhận Git trong session hiện tại |
| Frontend lint / production build | PASS / PASS — 153 modules |
| Health API | PASS trong HTTP verification |
| HTTP cookie/session/CSRF | PASS — 73 checks đã lưu; thêm 12 safe checks ở lượt hoàn tất |
| HTTP fixture cleanup / final DB read-only audit | PASS — counts và hashes của cả 16 bảng giống trước/sau và vẫn giống ở lượt hoàn tất |

Automated gateway tests dùng mock, không phụ thuộc Internet: create success/failure, signature/tamper, amount/reference mismatch, duplicate/replay, query success/failure/processing, ownership, paid-order guard, no client PAID, stock và COD regression. HTTP verification kiểm tra auth/CSRF thật, real Sandbox create + backend query trả PENDING chưa trả tiền; signed callback fixtures được mô phỏng để kiểm tra PAID/duplicate/late callback/cancel/Seller fulfillment. **Các callback fixture không phải thanh toán ngân hàng thật.**

Forward migration bổ sung ZALOPAY và metadata attempts đã áp dụng; không chạy `migrate:fresh` development. Snapshot kiểm chứng 15 bảng business gốc không đổi sau migration. Các thay đổi test fixtures được dọn và kiểm chứng riêng bằng 16 table hashes ở trên.

## Sandbox và giới hạn

Người dùng đã xác nhận thủ công **V2 create → Gateway → thanh toán thẻ Sandbox thành công** trước lượt tiếp tục này. Agent xác minh thêm create/query bằng credential public trial trong `.env`, không thực hiện lại bước nhập thẻ hoặc tuyên bố tích hợp callback public đã chạy E2E.

Hai case thẻ **chỉ dùng tại Sandbox**, theo [hướng dẫn chính thức](https://developer.zalopay.vn/v2/start/); không đưa vào UI:

| Case | Ngân hàng / số thẻ test | Chủ thẻ | Ngày phát hành |
|---|---|---|---|
| Thành công | SBI / `9704540000000062` | `NGUYEN VAN A` | `1018` |
| Không đủ tiền | SBI / `9704540000000047` | `NGUYEN VAN A` | `1018` |

Wallet E2E không yêu cầu: public trial wallet bị giới hạn KYC theo kết quả người dùng đã gặp; không tuyên bố mọi tài khoản Sandbox đều bị chặn. Merchant cần hoàn tất onboarding, dùng **credential production riêng** và public HTTPS callback để nhận tiền thật; public trial credentials không phải cấu hình thanh toán thật. Xem [hướng dẫn Sandbox](https://developer.zalopay.vn/v2/start/) và [callback requirements](https://docs.zalopay.vn/vi/docs/faq/).

Late-payment reconciliation hiện là cờ/audit để xử lý thủ công, chưa có màn hình đối soát hoặc refund. Chưa tự giải phóng stock của đơn online bỏ dở; cần hủy theo workflow. Không triển khai refund, payout/commission, realtime, chat, auction hoặc Docker.

## Files chính

- Backend: `config/zalopay.php`, migration `2026_09_30_070530_add_zalopay_payment_attempts.php`, Payment model/enum; `ZaloPayGateway`, `ZaloPayService`, `ZaloPayController`, callback/retry requests, reconcile command.
- Tích hợp hiện có: Checkout/ShopOrder services/controllers/requests; Order/Payment/ShopOrder resources, `OrderSummary`, Admin overview aggregate helper, routes và callback middleware exception.
- Frontend: `payment.api.js`, `PaymentAttempts.vue`, `PaymentResultView.vue`, Checkout/Order views và route; payment tests.
- Tests: `ZaloPayTest`, checkout concurrency/worker và regression liên quan; docs payment/API và báo cáo này. Secret chỉ ở `.env`; example chỉ khai báo tên biến, không credential.

## Final verification evidence và giới hạn kiểm chứng

Lượt tiếp tục đọc git status/diff và kết quả mới nhất trước khi chạy verification. Repository chưa có commit (`master` unborn); toàn project đang untracked, nên `git diff` không có baseline để so sánh. Inventory bên dưới so với SHA256 snapshot `.runtime/phase12-baseline.json` đã lưu trước PHASE 12, không phải diff với Git HEAD. Không xóa file, không reset database, không chạy `migrate:fresh` trên development, không bắt đầu PHASE 13.

- Backend final: `php artisan test --compact`; `.runtime/phase12-final-backend-test.log` ghi 186/186 passed, 2,028 assertions. `tests/TestCase.php`, PHPUnit env và worker đều guard đúng MySQL database `secondhand_marketplace_test`. Full regression bao gồm 17 `ZaloPayTest` cases và 3 concurrency cases; không tái triển khai tests đã hoàn tất.
- Frontend giữ evidence đã PASS trong `.runtime/phase12-frontend-results.json`, `phase12-frontend-tests.log`, `phase12-frontend-lint.log`, `phase12-frontend-build.log`: 150 tests / 12 files, lint exit 0, build exit 0 / 153 modules. Không có sửa frontend sau các kết quả này.
- Pint final: `.runtime/phase12-final-pint.log` ghi passed khi chạy explicit PHASE 12 PHP paths; không phụ thuộc dirty mode vì Git chưa có commit và session formatter không nhận Git.
- HTTP đã hoàn tất: `.runtime/phase12-http-results.json` ghi 73 checks, real Sandbox create/query, callback fixtures mô phỏng, cleanup và database unchanged.
- Safe checks final: `.runtime/phase12-final-safe-results.json` ghi 12/12 PASS. Health 200, csrf-cookie 204, session HttpOnly, XSRF token đọc được bởi client, host-only cookies tại `/`, SameSite=Lax; login thiếu CSRF trả 419; public callback thiếu CSRF nhưng MAC không hợp lệ trả return_code=-1; guest orders 401; frontend `/payment/result` trả app shell. Cookie Secure=false phù hợp local HTTP; chưa kiểm chứng cookie trên production HTTPS.
- DB audit final chỉ đọc: 16 table counts/hashes hiện tại bằng snapshot sau HTTP cleanup. Safe checks không tạo checkout, không gửi thẻ, không gửi signed success callback và không tạo business fixtures.

Callback/cancel conflict đã review lock ordering và assertion hiện có: callback thắng → PAID, cancel 409, stock không hoàn; cancel thắng → CANCELLED, stock hoàn một lần, late payment chỉ reconciliation. Duplicate callback không thêm side effect; replay transaction ID sang order khác bị từ chối. Race thực dùng các PHP processes với barrier và MySQL locks; một lần chạy race không bảo đảm quan sát cả hai thứ tự, nên deterministic feature/HTTP cases đã hoàn tất kiểm chứng riêng cả paid-first và cancellation-first. Không phát hiện issue PHASE 12 mới cần sửa ở lượt hoàn tất.

Manual-safe verification giới hạn ở HTTP responses, cookie attributes và frontend route shell; không có browser automation/visual inspection ở lượt này. Không tuyên bố public HTTPS callback E2E hoặc wallet E2E đã PASS. Xác nhận thanh toán thẻ Sandbox thủ công trước đó của người dùng được giữ nguyên, không thực hiện lại. Refund/reconciliation UI, production onboarding và tự giải phóng reservation vẫn ngoài scope như phần giới hạn phía trên.

## Inventory PHASE 12 so với snapshot trước phase

14 files mới, 26 files sửa, 0 files xóa. Lượt hoàn tất chỉ cập nhật báo cáo này và evidence trong `.runtime/` (gitignored), không bổ sung thay đổi logic ứng dụng.
Files mới:

- `backend/app/Console/Commands/ReconcileZaloPay.php`
- `backend/app/Http/Controllers/Api/V1/ZaloPayController.php`
- `backend/app/Http/Requests/Domain/RetryZaloPayRequest.php`
- `backend/app/Http/Requests/Domain/ZaloPayCallbackRequest.php`
- `backend/app/Services/ZaloPayGateway.php`
- `backend/app/Services/ZaloPayService.php`
- `backend/config/zalopay.php`
- `backend/database/migrations/2026_09_30_070530_add_zalopay_payment_attempts.php`
- `backend/tests/Feature/ZaloPayTest.php`
- `docs/phase-12-verification.md`
- `frontend/src/__tests__/PaymentPhase12.spec.js`
- `frontend/src/api/payment.api.js`
- `frontend/src/components/PaymentAttempts.vue`
- `frontend/src/views/PaymentResultView.vue`

Files sửa:

- `backend/.env.example`
- `backend/app/Enums/PaymentMethod.php`
- `backend/app/Http/Controllers/Api/V1/CheckoutController.php`
- `backend/app/Http/Controllers/Api/V1/SellerOrderController.php`
- `backend/app/Http/Requests/Domain/CheckoutRequest.php`
- `backend/app/Http/Requests/Domain/UpdateShopOrderRequest.php`
- `backend/app/Http/Resources/AdminOrderResource.php`
- `backend/app/Http/Resources/OrderResource.php`
- `backend/app/Http/Resources/PaymentResource.php`
- `backend/app/Http/Resources/ShopOrderResource.php`
- `backend/app/Models/Payment.php`
- `backend/app/Providers/AppServiceProvider.php`
- `backend/app/Services/CheckoutService.php`
- `backend/app/Services/OrderSummary.php`
- `backend/app/Services/ShopOrderService.php`
- `backend/bootstrap/app.php`
- `backend/routes/api.php`
- `backend/routes/console.php`
- `backend/tests/Feature/CheckoutConcurrencyTest.php`
- `backend/tests/Support/checkout-worker.php`
- `docs/api-design.md`
- `docs/business-rules.md`
- `frontend/src/composables/orderPresentation.js`
- `frontend/src/router/index.js`
- `frontend/src/views/CheckoutView.vue`
- `frontend/src/views/OrderView.vue`
