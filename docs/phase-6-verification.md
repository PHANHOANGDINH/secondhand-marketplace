# PHASE 6 — Product, Images, Search và Moderation

Ngày kiểm chứng: 2026-09-27. **PHASE 6 COMPLETED**. Không bắt đầu PHASE 7.

## Kết quả

| Kiểm tra | Kết quả |
|---|---|
| `php artisan test --compact` toàn bộ | **91 tests / 819 assertions PASS**, gồm 26 test Product mới và regression Auth/Profile/Address/Shop/Category/Health |
| `php vendor/bin/pint --test --format agent` | PASS toàn backend |
| `npm.cmd run test:unit -- --run --maxWorkers=1` | **56 tests / 6 files PASS**, thêm 20 test Product/Seller/Images/Admin; một worker để ổn định Windows |
| `npm.cmd run lint` | PASS oxlint + ESLint |
| `npm.cmd run build` | PASS Vite 8.3.1, 121 modules |
| HTTP cookie/session thật | **39 checks PASS**; seller/admin cookie jars riêng, CSRF, không Bearer |
| Health API | GET http://localhost:8000/api/v1/health →200, status=ok, contract giữ nguyên |
| SPA | http://localhost:5173 và cả 6 route mới trả SPA entry 200 |
| Data integrity | Counts + SHA-256 toàn bộ rows trong 16 bảng trước/sau HTTP khớp; tập file public disk không đổi sau cleanup |
| Isolation | TestCase chặn trước RefreshDatabase nếu không đúng MySQL `secondhand_marketplace_test`; development giữ `secondhand_marketplace` |
| Source audit | Schema/model/enum/factory/seeder/auth core không đổi; không duplicate class/route; env ignored và secret scan PASS |

Đã audit git status, process, source PHASE 5, Product/ProductImage/Shop/Category và enums trước khi thêm code. Chỉ thấy một tiến trình Codex chính; không dùng agent ghi file song song. Repository đã untracked từ baseline; không commit/reset/clean. Không cấu hình lại MySQL, không migration mới hoặc migrate:fresh/seed development. Không thêm dependency.

## Endpoint chính

Prefix `/api/v1`; tổng 46 API route records, gồm 18 route Product mới. IDs JSON là string; Resource `{data}`, pagination `data/links/meta`.

| Nhóm | Method + path |
|---|---|
| Public | GET `/products`, GET `/products/{slug}` |
| Seller CRUD | GET/POST `/seller/products`; GET/PATCH/DELETE `/seller/products/{product}` (PUT alias của PATCH) |
| Seller actions | POST `/seller/products/{product}/submit`, `/hide`, `/withdraw`, `/revise` |
| Upload | POST `/seller/products/{product}/images`, multipart `images[]` |
| Primary / vị trí | PATCH `/seller/products/{product}/images/{image}` với `is_primary=true` **hoặc** `sort_order` |
| Reorder | PATCH `/seller/products/{product}/images/reorder`, toàn bộ `image_ids` |
| Xóa ảnh | DELETE `/seller/products/{product}/images/{image}` |
| Admin | GET `/admin/products`, GET `/admin/products/{product}`, PATCH `/admin/products/{product}/status` |

## Lifecycle, ownership và catalog

- Seller là User sở hữu Shop. Create gán Shop hiện tại và DRAFT; reject shop_id/status/owner_id/slug/deleted_at ngoài allowlist. Seller chỉ xem/sửa/xóa/submit/hide hàng của mình. DELETE là soft delete, giữ ảnh và references lịch sử.
- Giữ rule chặt hơn của docs: Shop PENDING/ACTIVE được tạo/sửa; SUSPENDED/REJECTED chỉ xem. Submit cần Shop/owner ACTIVE, category và ancestors ACTIVE, stock>0, dữ liệu đủ, ít nhất một ảnh và đúng một primary.
- DRAFT/HIDDEN → PENDING khi submit; PENDING → ACTIVE/REJECTED bởi Admin. REJECTED → DRAFT qua revise rồi sửa/submit lại. PENDING → DRAFT qua withdraw. ACTIVE/SOLD → HIDDEN qua hide. Docs cho phép sửa PENDING và giữ PENDING.
- Sửa nội dung/giá/category/condition/ảnh ACTIVE/HIDDEN → PENDING. Theo rule cũ, ACTIVE thay stock dương cũng → PENDING; stock=0 → SOLD; SOLD restock dương → PENDING. Stock PATCH cần expected_updated_at, stale dưới lock →409. Seller không tự ACTIVE.
- Public cần Product ACTIVE, stock>0, Product/Shop chưa soft-delete, Shop/owner ACTIVE chưa xóa và Category/ancestors ACTIVE. Direct URL private →404. Admin đọc mọi status và archived qua API riêng; không tự duyệt Shop mình.
- Admin gửi expected_status/status/reason; stale hoặc transition sai →409. Approve kiểm tra lại điều kiện dưới lock. Audit append-only cùng transaction cho approve/reject/hide và các mutation seller/ảnh; lỗi audit rollback DB. Không log secrets/binary.
- Slug **global unique, ổn định**, hậu tố khi collision, giữ chỗ kể cả soft-delete. Giá DECIMAL(15,2) nhận chuỗi tối đa2 số lẻ; bcadd/bccomp, không float. Tests giữ chính xác `0.10`, `999999.99`, `9999999999999.99`.
- Search name/description bằng LIKE binding, escape wildcard; `search` là chính, `keyword` alias (không gửi cả hai). Filter category ID gồm descendants tối đa3 cấp; một condition allowlist; min_price/max_price inclusive; shop ID public. Sort newest/price_asc/price_desc với id tie-break. Page mặc định20, max100; input sai/unknown →422. Eager load shop/category/images; test listing không tăng query theo số row.

## Ảnh và an toàn

Public disk lưu `products/{id}/{uuid}.{jpg|png|webp}`; DB chỉ image_path, Resource sinh URL. `public/storage` là junction đúng public disk. Placeholder frontend local; seed SVG cũ giữ như asset tĩnh, không được upload SVG.

JPEG/PNG/WebP: MIME thực + extension + GD decode/re-encode, max **5 MiB/file**, **8 ảnh/Product**, 20MP và memory guard. Không tin tên/path browser; SVG/executable/path injection bị chặn. Ảnh đầu primary; đổi primary transaction; xóa primary promote sort_order/id nhỏ nhất; xóa cuối còn0 an toàn. Reorder đủ toàn bộ IDs, không trùng/khác Product, normalize vị trí0..n−1 dưới lock.

Store file trước DB, lỗi DB/audit cleanup file mới. Delete bảo toàn invariant DB trước, cleanup sau commit; giữ file còn ProductImage/OrderItem snapshot tham chiếu. Cleanup lỗi được log, chưa có retry nền. Tests dùng Storage::fake; có rollback/file cleanup và loại appended payload cho cả JPEG/PNG/WebP.

`backend/serve.ps1` dùng `backend/dev/uploads.ini`: upload=5M, POST=42M, RAM=256M chỉ cho server local, không sửa PHP toàn máy. HTTP đã upload đúng5MiB thành công, file URL200 và re-encode bỏ padding; vượt5MiB trả422. Production cần cấu hình giới hạn server tương ứng.

## Frontend và HTTP thật

Routes: `/products`, `/products/:slug`, `/seller/products`, `/seller/products/create`, `/seller/products/:id/edit`, `/admin/products`; `/shops/:slug` hiển thị catalog của Shop. Có card/gallery/placeholder, search form và filters lưu URL, pagination, empty/error states. Seller form không có shop/status/slug selector; create DRAFT → edit → ảnh → submit. UI ảnh preview/upload/delete/primary/Lên/Xuống. Admin lọc/xem/approve/reject/hide và hiển thị stale conflict. Guard USER không vào admin; backend vẫn authoritative. Không fake nút mua hàng.

HTTP: login seller/admin bằng Sanctum CSRF + cookie → GET seller Shop → DRAFT → thiếu ảnh422 → upload thật → PENDING (guest404) → Admin list/detail/approve ACTIVE → Guest filter chính xác và slug200 → Seller hide → Guest404. Có status injection422, thiếu CSRF419, USER admin403, stale moderation409, logout và cleanup fixture. Accounts/password/cookies không lưu trong báo cáo. Kiểm chứng UI bằng Vitest và production build; không tuyên bố đã thao tác browser thủ công.

## File chính đã thêm/sửa

- Controllers: PublicProductController, SellerProductController, ProductImageController, AdminProductController trong `backend/app/Http/Controllers/Api/V1/`.
- Requests trong `backend/app/Http/Requests/Domain/`: StoreProductRequest, UpdateProductRequest, PublicProductListRequest, SellerProductListRequest, AdminProductListRequest, ModerateProductRequest, UploadProductImagesRequest, UpdateProductImageRequest, ReorderProductImagesRequest.
- Resources: ProductResource, ManagedProductResource (dùng chung seller/admin), ProductImageResource. Policies: ProductPolicy, ProductImagePolicy.
- Services: ProductService, ProductImageService, ProductQuery, ProductLocks, DecimalPrice. Sửa `routes/api.php`, AppServiceProvider (upload throttle); CategoryService chỉ format theo Pint.
- Backend tests mới: ProductManagementTest, ProductCatalogTest, ProductImagesTest. ProfileAddressTest/CategoryManagementTest chỉ format. Thêm `backend/serve.ps1`, `backend/dev/uploads.ini`.
- Frontend: `api/product.api.js`, `api/catalogOptions.js`, `composables/productPresentation.js`; ProductsView, ProductView, SellerProductsView, SellerProductEditView, AdminProductsView; ProductCard, ProductGallery, ProductPhoto, ProductImagesEditor, ShopProducts; ProductPhase6.spec.js và `public/product-placeholder.svg`.
- Sửa frontend router, DefaultLayout, ShopView; README và docs api-design/business-rules/state-transitions/permissions/requirements/database-design; thêm báo cáo này. Tổng **44 file mới, 15 file sửa**, không xóa file source.

## Known limitations

Chưa có Cart, Checkout, Order creation, Payment/VNPay, Review, Realtime, Chat, Auction, Docker hoặc dashboard ngoài scope. Chưa cloud storage, thumbnails/optimization và cleanup retry nền; không có admin Product restore. Search LIKE phù hợp MVP, chưa benchmark mục tiêu tải10.000 sản phẩm; category/Shop options tải phân trang toàn bộ cho bộ dữ liệu nhỏ hiện tại. Chưa bắt đầu PHASE 7.
