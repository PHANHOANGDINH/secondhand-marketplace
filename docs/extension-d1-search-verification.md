# Extension D1 — Tìm kiếm và khám phá sản phẩm

Ngày kiểm tra: 09/10/2026. Môi trường: Windows/XAMPP MySQL, PHP 8.4, Laravel 13.33, Vue/Vite; frontend http://localhost:5173, backend http://127.0.0.1:8000.

## Phạm vi và chuẩn kiểm thử

D1 mở rộng khám phá sản phẩm công khai. Không sửa auth, Pinia, Checkout, Order, Payment, dịch vụ đấu giá, quyền Seller/Admin, schema hoặc dữ liệu demo. `backend/AGENTS.md` giữ nguyên.

Theo ngoại lệ được người dùng phê duyệt:

> The repository referenced `testing-best-practices`, but that skill was not available in the execution environment. Existing project test conventions and neighboring regression tests were therefore used as the testing standard for D1.

Đã đối chiếu `phpunit.xml`, `Tests\TestCase`, ProductCatalogTest, ReviewTest, các factory Product/Auction/ProductVideo/Review/OrderItem và quy ước Vitest ProductPhase6. Backend sử dụng factory và RefreshDatabase trên **secondhand_marketplace_test**; TestCase từ chối chạy ngoài database test này. Frontend mock API, memory router, flushPromises, fake timers khi kiểm tra debounce và unmount/khôi phục timer sau mỗi test.

## API và truy vấn

`GET /api/v1/products` giữ response Resource/pagination hiện có và bổ sung:

| Tham số | Giá trị / ý nghĩa |
| --- | --- |
| `q` | Từ khóa, tối đa 100 ký tự; giữ tương thích `search` và `keyword`; chỉ một alias có nội dung |
| `category` | ID danh mục công khai, gồm các danh mục con theo giới hạn cấp hiện có |
| `shop` | ID gian hàng công khai |
| `condition` | LIKE_NEW / GOOD / FAIR / POOR; giao diện hiển thị nhãn tiếng Việt |
| `min_price`, `max_price` | Chuỗi DECIMAL(15,2), không âm, tối đa hai chữ số thập phân; min ≤ max |
| `has_video` | `1`: có video READY; `0`: không có video READY; video FAILED không được tính |
| `sale_type` | `fixed`: không có auctionClaim; `auction`: có auctionClaim theo FixedPriceEligibility hiện tại |
| `sort` | newest / oldest / price_asc / price_desc / rating / relevance |
| `page`, `per_page` | Số nguyên dương; per_page ≤ 100, mặc định 20 |

Tất cả bộ lọc kết hợp bằng AND. Từ khóa tìm theo tên, mô tả, tên danh mục và tên gian hàng; nhóm OR luôn nằm trong điều kiện hiển thị công khai. Giá lọc/sắp xếp vẫn là **giá niêm yết của Product**, không đổi thành giá trả đấu giá. Giao diện giải thích rõ điểm này.

Sắp xếp có khóa phụ ID ổn định. Rating dùng aggregate của ReviewService: chỉ review công khai có quan hệ mua hàng hợp lệ. Relevance ưu tiên tên chính xác → tiền tố tên → tên chứa từ khóa → mô tả/danh mục/gian hàng, sau đó mới nhất; không có từ khóa thì dùng thứ tự mới nhất. Từ khóa `0` vẫn được xử lý và xếp hạng.

Liên kết phân trang API giữ query. Query không hợp lệ trả 422 theo DomainRequest; không cho phép client truyền status/order_by hoặc trường không được hỗ trợ. LIKE được bind tham số và escape `%`, `_`, `!` theo nghĩa ký tự, gồm kiểm thử input giống SQL injection.

`GET /api/v1/products/suggestions?q=...`:

- Trim và yêu cầu 2–100 ký tự; dùng throttle public-read hiện có.
- Trả `data.products` (tối đa 5 ProductResource), `data.categories` (tối đa 3 ID/tên), `data.shops` (tối đa 3 ID/tên).
- Danh mục/gian hàng gợi ý phải có sản phẩm đáp ứng toàn bộ điều kiện công khai hiện tại.
- Không trả thông tin chủ shop, dữ liệu moderation, credential hoặc lịch sử tìm kiếm.
- Route suggestions được khai báo trước route chi tiết sản phẩm.

## Giao diện

- ProductSearch dùng chung cho header và danh sách: debounce 300ms, tối thiểu hai ký tự, gợi ý từ dữ liệu thật; ArrowUp/ArrowDown/Enter/Escape, nhãn truy cập tiếng Việt. Chọn sản phẩm đi đến chi tiết; chọn danh mục/gian hàng đi đến danh sách đã lọc.
- Hủy timer khi unmount, bỏ qua response cũ; trạng thái tải/lỗi/không có gợi ý không chặn tìm kiếm thông thường.
- ProductFilters dùng chung cho panel desktop và AccessibleDialog trên mobile dưới 768px. Dialog giữ focus, Escape đóng, trả focus, khóa cuộn nền; không trùng ID trường khi mở.
- URL là nguồn trạng thái: refresh, Back/Forward, chia sẻ URL, phân trang giữ bộ lọc; áp dụng lọc về trang đầu. Giữ query quick-listing hợp lệ của chức năng cũ.
- Chuẩn hóa query sai dạng, sort/enum không hỗ trợ, giá không hợp lệ và khoảng giá đảo bằng BigInt, không dùng float. ID đúng dạng nhưng không công khai được backend xác minh và trả lỗi thân thiện.
- Skeleton, tổng kết quả, nhãn bộ lọc tiếng Việt, xóa lọc, thông báo không tìm thấy và lỗi/thử lại; không hiện lỗi + empty đồng thời hoặc raw exception.
- ProductCard giữ đường dẫn arena và giá đấu giá; nhãn SCHEDULED/LIVE/ENDED dùng bộ nhãn tập trung, tránh ghi “Đang đấu giá” khi đã kết thúc. Không bổ sung CTA mua thường cho sản phẩm bị giữ bởi đấu giá.

Ví dụ demo:

```text
http://localhost:5173/products?q=AirPods&has_video=1&sale_type=fixed&sort=relevance
http://localhost:5173/products?sale_type=auction&sort=oldest
```

## Hiệu năng và giới hạn

Không thêm dependency, search engine hoặc migration/index. Dùng eager loading shop/category/images/auctionClaim, aggregate rating và EXISTS cho video/auction; regression xác nhận số truy vấn không tăng theo số sản phẩm. Index quan hệ hiện có được giữ, gồm product_videos(product_id,status,sort_order,id) và các index auction. LIKE chứa từ khóa trên nhiều trường không được giải quyết bằng cách tùy tiện thêm B-tree index.

Giới hạn còn lại:

- Relevance là thứ hạng SQL đơn giản, không fuzzy search, không bỏ dấu chủ động; việc so khớp dấu/hoa thường phụ thuộc collation MySQL hiện tại.
- Không benchmark tải lớn hoặc EXPLAIN trên tập dữ liệu production; chưa có bằng chứng cần index mới.
- Giá lọc/sắp xếp là giá niêm yết; giá auction trên thẻ có thể khác. sale_type=auction bao gồm trạng thái giữ cho người thắng chưa checkout theo quy tắc có sẵn.
- Helper tải cây danh mục hiện có vẫn gọi API theo từng nhánh. Reload hàng loạt trong kiểm tra tự động có thể chạm public-read 120/phút; không nới rate limit. Kiểm tra lại bằng điều hướng SPA thông thường đã qua.
- Browser QA dùng Edge headless với chiều rộng viewport; không thay thế kiểm tra trên điện thoại thật hoặc screen reader đầy đủ.

## Kiểm chứng

Lệnh thực thi tương đương từ thư mục tương ứng:

```powershell
cd backend
php vendor/bin/phpunit tests/Feature/ProductDiscoveryTest.php tests/Feature/ProductCatalogTest.php
php vendor/bin/phpunit
php vendor/bin/pint --dirty --format agent

cd ../frontend
npm run test:unit -- --run
npm run lint
npm run build
```

| Kiểm tra | Kết quả |
| --- | --- |
| Backend catalog + D1 | PASS — 15 tests, 155 assertions |
| Backend full, lần đầu | PASS — 359 tests, 3.579 assertions |
| Backend full, source cuối cùng | PASS — 360 tests, 3.582 assertions |
| Frontend full | PASS — 370 tests / 30 files, gồm 8 tests D1 mới |
| Pint | PASS |
| Oxlint + ESLint | PASS |
| Production build | PASS |

Backend D1 kiểm tra alias, tên/mô tả/danh mục/shop, literal wildcard/injection, video READY/FAILED, sale type, kết hợp toàn bộ bộ lọc, oldest/relevance/rating, pagination, input sai, giới hạn/redaction gợi ý, shop bị đình chỉ và số truy vấn. ProductCatalogTest hiện có tiếp tục kiểm tra từng lọc, giá DECIMAL, sorts cũ, owner/category ancestor/soft deletion và public visibility.

Frontend D1 kiểm tra URL, kết hợp lọc, clear, sort, pagination/back, normalization giá chính xác, dialog/focus/Escape, skeleton/empty/error, debounce/destinations, response cũ/unmount và auction CTA/lifecycle.

Browser http://localhost:5173:

| Viewport / flow | Kết quả |
| --- | --- |
| 1440 / 1024 / 768 / 390px | PASS — có dữ liệu, không tràn ngang, không ảnh lỗi |
| Mobile dialog | PASS — focus trong dialog, Escape và trả focus, ID duy nhất, áp dụng lọc |
| q=AirPods + video + fixed + relevance | PASS — một sản phẩm phù hợp từ demo hiện có |
| Auction filter | PASS — bốn sản phẩm, bốn CTA đến arena |
| Empty / invalid URL | PASS — thông báo Việt, chuẩn hóa query |
| Header suggestions + keyboard Enter | PASS — chọn AirPods dẫn đến đúng chi tiết sản phẩm |
| Refresh / Back / Forward | PASS — giữ query và trang lọc |
| Giả lập mất kết nối → Tải lại | PASS — lỗi thân thiện, tải thành công sau khôi phục mạng |
| JavaScript exceptions | Không ghi nhận |

## Bảo toàn dữ liệu và file thay đổi

Đối chiếu snapshot read-only trước/sau: **22 bảng business trong secondhand_marketplace giữ nguyên theo ID và SHA-256 từng row**. Không tạo fixture trong development DB, không sửa demo, không chạy migration hoặc migrate:fresh trên development. Fixture kiểm thử chỉ tồn tại trong database test và được cleanup theo cơ chế kiểm thử hiện có. Cache/session phục vụ request không nằm trong snapshot business.

Files D1 (so với trạng thái bắt đầu, không trộn các thay đổi phase trước):

- backend/app/Services/ProductQuery.php
- backend/app/Http/Requests/Domain/PublicProductListRequest.php
- backend/app/Http/Requests/Domain/ProductSuggestionsRequest.php (mới)
- backend/app/Http/Controllers/Api/V1/PublicProductController.php
- backend/routes/api.php
- backend/tests/Feature/ProductDiscoveryTest.php (mới)
- frontend/src/api/product.api.js
- frontend/src/components/ProductCard.vue
- frontend/src/components/ProductFilters.vue (mới)
- frontend/src/components/ProductSearch.vue (mới)
- frontend/src/layouts/DefaultLayout.vue
- frontend/src/utils/discoveryQuery.js (mới)
- frontend/src/views/ProductsView.vue
- frontend/src/__tests__/ProductDiscovery.spec.js (mới)
- docs/extension-d1-search-verification.md (mới)

**Kết luận cuối: PASS — D1 hoàn tất, kiểm thử/regression/build và browser QA đạt; dữ liệu development được bảo toàn. Các giới hạn còn lại được nêu ở trên.**
