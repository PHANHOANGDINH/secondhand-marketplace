# PHASE 9 — Product Reviews

Ngày kiểm chứng: 2026-09-28. **PHASE 9 COMPLETED**. Không bắt đầu PHASE10.

| Kiểm tra | Kết quả |
|---|---|
| Backend full `php artisan test --compact` | **136 tests / 1.460 assertions PASS**, thêm14 Review tests |
| Frontend full `npm.cmd run test:unit -- --run --maxWorkers=1` | **106 tests / 9 files PASS**, thêm16 Review tests |
| Pint / lint / production build | PASS; Vite8.3.1 / 137 modules |
| Real HTTP cookie/CSRF | **51 checks PASS** |
| Health | GET http://localhost:8000/api/v1/health →200/status=ok |
| Development integrity | Fixture cleanup PASS; counts + SHA-256 rows của16 bảng trước/sau khớp |

Đã audit git status và inventory/hash baseline vì repository untracked từ trước. Đọc đúng Review rules/API, báo cáo PHASE8 và models liên quan. Một Codex chính, không sub-agent. Schema/model/enum/factory/seeder/auth core giữ nguyên; không migration mới, không migrate:fresh development. Test database vẫn secondhand_marketplace_test với guard config/actual DB. Giữ regression PHASE1–8, gồm checkout concurrency/rollback/COD.

## Endpoints

Prefix `/api/v1`:

- `POST /order-items/{orderItem}/review`: auth + CSRF,201; rating1–5, comment nullable≤2000.
- `PATCH /reviews/{review}`: auth + CSRF,200; tác giả sửa rating/comment.
- `GET /products/{slug}/reviews`: public, reviews PUBLISHED hợp lệ của Product public; pagination20/max100, newest ID trước.
- `GET /products/{slug}/review-eligibility`: auth, own completed OrderItems/review/can_review/can_edit_review, cùng pagination; phục vụ form Product detail.

Không DELETE theo contract MVP hiện có. Không thêm Seller role hoặc đổi Sanctum architecture. Form Request allowlist từ chối user_id/product_id/order_item_id/status/moderation_reason; liên kết do server derive. Foreign Buyer/own Shop→403; chưa COMPLETED/trùng Review→409; validation422. ReviewService khóa accounts→Order→ShopOrder→Shop→Product→OrderItem→Review, kiểm tra lại dưới lock; unique(order_item_id) kể cả HIDDEN. Tác giả sửa HIDDEN không tự publish.

## Summary, history và UI

ProductResource có average_rating (number,2 số lẻ; null khi chưa có) và review_count. SQL COUNT/AVG dùng cùng predicate với list: PUBLISHED, purchase COMPLETED, đúng Buyer/Product reference, không own Shop. Không cột aggregate hoặc cache. Eager loading authors/private reviews và query-count tests bảo vệ N+1. Public chỉ author display name, không email/user ID/internal reason; author soft-delete dùng “Người mua”.

Product SOLD/hidden/soft-delete vẫn giữ Review và có thể đánh giá/sửa từ Order history, nhưng public reviews404 nếu Product không public. Buyer OrderItem có action metadata/review riêng; Seller không nhận private Review metadata. ProductReviews, ReviewEditor/ReviewForm dùng Vue text interpolation, không render HTML comment. `/products/:slug` có average/count/list/pagination và form cho item đủ điều kiện; `/orders/:id` có “Đánh giá”/“Sửa đánh giá” và trạng thái đã review. Eligibility phân trang riêng, response cũ bị bỏ khi logout/chuyển Product.

## Bằng chứng / files

Backend tests: guest, nonbuyer/foreign Item, mọi state chưa COMPLETED, own Shop, inconsistent reference, duplicate kể cả HIDDEN, validation/injection, edit ownership, no DELETE, suspended author, public list/order/pagination, average/count thay đổi sau edit và bỏ HIDDEN, Product history, deleted-author privacy, eligibility và query count. Frontend tests: load/empty/error/pagination, average/count, eligible/ineligible form, create/edit/validation/conflict, Order action, aggregate refresh, logout race và comment HTML được escape.

HTTP bằng3 cookie jars riêng: Buyer checkout →PENDING bị từ chối review→Seller hoàn tất COD→Buyer tạo/sửa→guest thấy average/count mới. Kiểm guest401, CSRF419, foreign403, rating/injection422, duplicate409, DELETE405, HIDDEN không republish/không tính aggregate, Product soft-delete404 nhưng Order history còn review. Cleanup chỉ fixture nonce với guard references; baseline16 bảng không thay đổi, không tạo/xóa file ảnh. Auto-increment có thể tăng, không reset sequence. UI hành vi được kiểm bằng Vitest; không tuyên bố manual browser test.

File chính: ReviewController, StoreReviewRequest/UpdateReviewRequest/ReviewListRequest, ReviewPolicy, ReviewResource, ReviewService, ReviewTest; ProductQuery/ProductResource, OrderSummary/OrderItemResource/ShopOrderResource và routes. Frontend review.api.js, ProductReviews/ReviewEditor/ReviewForm, ProductView/OrderView/OrderItems, ReviewPhase9.spec.js; cập nhật docs/API/rules/states/README.

Giới hạn: chưa Admin Review moderation API/UI, review images, seller replies, edit version conflict hoặc realtime. HIDDEN hiện có vẫn được tôn trọng. Không revenue/dashboard mở rộng/VNPay/chat/auction/Docker; không bắt đầu PHASE10.
