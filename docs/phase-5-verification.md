# PHASE 5 — User Profile, Address, Shop và Category

Ngày nghiệm thu: **27/09/2026** (Asia/Ho_Chi_Minh). Project: `D:\CN\Web\secondhand-marketplace`.

**PHASE 5 COMPLETED.** Backend, Vue UI, tests, lint/build, real HTTP cookie flows và kiểm tra dữ liệu development đều PASS. Chưa bắt đầu PHASE 6.

## 1. Kết quả kiểm chứng

| Hạng mục | Kết quả thực tế |
|---|---|
| Backend toàn bộ PHASE 1–5 | **PASS — 65 tests, 495 assertions** |
| Test bổ sung PHASE 5 | 24 tests trong ProfileAddressTest, ShopManagementTest, CategoryManagementTest |
| Frontend toàn bộ | **PASS — 36 tests trong 5 files**; bổ sung20 tests |
| PHP formatting | PASS — `php vendor/bin/pint --dirty --format agent` |
| Frontend lint | PASS — `npm.cmd run lint` (oxlint + ESLint) |
| Production build | PASS — `npm.cmd run build`, Vite8.3.1, 108 modules |
| PHASE 5 HTTP thật | **PASS — 54 checks**, user/admin với cookie jar riêng, không Bearer |
| PHASE 4 HTTP regression | PASS — register/login/me/logout, CSRF/CORS, invalidate và session replay |
| Health API | PASS — GET http://localhost:8000/api/v1/health →200, contract giữ nguyên |
| SPA | http://localhost:5173 →200; 7 route mới đều được Vite trả SPA entry |
| Development integrity | PASS — counts + SHA-256 của toàn bộ row trong16 bảng nghiệp vụ/lịch sử/token được kiểm tra khớp trước–sau |
| Database test isolation | PASS — TestCase bắt buộc MySQL + môi trường testing + actual/config DB `secondhand_marketplace_test` trước RefreshDatabase |
| Schema | 17 migrations đều Ran; không migration/model/enum mới hoặc duplicate |
| Secrets | PASS — env/runtime/build ignored; source candidates không chứa DB_PASSWORD hoặc APP_KEY thật |

Các lệnh verification chạy tại đúng backend/frontend. `php artisan test --compact` chạy toàn bộ suite, không filter ở lần cuối. Production build đã kiểm tra sau formatter và lint. Không cài dependency mới.

## 2. Audit trước triển khai

Đã chạy git status, đọc source hiện có và requirements/business-rules/use-cases/database-design/api-design/permissions/state-transitions/phase-3-verification/phase-4-verification; đối chiếu Models User/Address/Shop/Category/Product và enums hiện hữu. Đã đọc backend/AGENTS.md; `.ai/rules` không tồn tại. Skill testing-best-practices được file đó nhắc đến không có trong các skill đã cài; tests dựa trên suite PHPUnit/Vitest hiện hữu.

Ở lần kiểm tra process không thấy phiên Codex chính thứ hai đang chạy; chỉ tiến trình chính và các tool runner liên quan. Không dùng sub-agent ghi file. Project từ trước đã có toàn bộ source ở trạng thái untracked; không tạo commit, không reset/clean Git hoặc ghi đè nền tảng schema/auth. Các class PHASE 5 được kiểm tra trùng tên trước khi tạo.

Giữ nguyên Laravel13.33.0, Sanctum4.3.3, PHP8.4, MySQL26.7 và Vue3.5. MySQL/service/root credential/.env không cấu hình lại. Không chạy migrate:fresh, seed hoặc rebuild development trong phase này.

## 3. Endpoints đang hoạt động

Prefix dưới đây là `/api/v1`. JSON single/mutation dùng `{data: ...}` theo contract hiện có; list có data/links/meta. Create201, update200, delete204 body rỗng. IDs JSON là string. Tổng route:list API là28, gồm5 health/auth cũ và23 route records mới.

| Nhóm | Method + path | Quyền / hành vi |
|---|---|---|
| Profile | GET /profile | Current ACTIVE User |
| Profile | PATCH /profile | Chỉ name/phone |
| Address | GET /addresses; POST /addresses | List own, tạo own |
| Address | GET /addresses/{address} | Chỉ owner |
| Address | PATCH /addresses/{address} | Chỉ owner, validated fields |
| Address | DELETE /addresses/{address} | Chỉ owner; promote default khi cần |
| Address | PATCH /addresses/{address}/default | Chỉ owner; transaction |
| Public Shop | GET /shops | ACTIVE Shop và owner ACTIVE chưa soft-delete; search/page/per_page |
| Public Shop | GET /shops/{slug} | Safe resource, hidden→404 |
| Seller Shop | POST /shops | Tạo PENDING, owner=current User; giữ naming PHASE2 |
| Seller Shop | GET /seller/shop | Own non-deleted Shop, kể cả PENDING/REJECTED/SUSPENDED |
| Seller Shop | PATCH /seller/shop | Own name/description; SUSPENDED→403 |
| Seller Shop | POST /seller/shop/submit | REJECTED→PENDING |
| Public Category | GET /categories | Roots mặc định; parent_id lấy children; chỉ public ACTIVE tree |
| Public Category | GET /categories/{slug} | ACTIVE và mọi ancestor ACTIVE |
| Admin Shop | GET /admin/shops | ADMIN; status/search/page/per_page; gồm archived |
| Admin Shop | GET /admin/shops/{shop} | ADMIN; ID binding, đọc cả archived |
| Admin Shop | PATCH /admin/shops/{shop}/status | ADMIN khác owner; expected_status/status/reason |
| Admin Category | GET /admin/categories | ADMIN; mọi trạng thái, parent_id? |
| Admin Category | POST /admin/categories | ADMIN; name/description/parent_id/status/reason |
| Admin Category | PATCH /admin/categories/{category} | ADMIN; thay parent/status/nội dung, reason |
| Admin Category | DELETE /admin/categories/{category} | ADMIN; reason; leaf không Products |

Laravel apiResource còn cho PUT /addresses/{address} dùng cùng handler PATCH; SPA chỉ gửi PATCH. Không thêm POST /seller/shop hoặc category tree endpoint trùng chức năng. Address dùng tên field thật `country_code`; Category không có sort_order.

Private routes: auth:sanctum → EnsureActiveAccount → EnsureSpaSession; mutations phải qua CSRF. Admin thêm EnsureAdmin và Policy. Public reads120/phút/IP, private mutations60/phút/User; auth limiters cũ giữ nguyên. Lists default20/max100, page≥1 và unknown filters→422. Search LIKE binding, escape wildcard, bao gồm trường hợp chuỗi `0`.

## 4. Backend và phân chia trách nhiệm

Controllers mỏng: ProfileController, AddressController, SellerShopController, PublicShopController, AdminShopController, PublicCategoryController, AdminCategoryController, tại `backend/app/Http/Controllers/Api/V1/`.

Form Requests tại `app/Http/Requests/Domain/`: DomainRequest (base allowlist), UpdateProfileRequest, StoreAddressRequest, UpdateAddressRequest, AddressListRequest, StoreShopRequest, UpdateShopRequest, ShopListRequest, ModerateShopRequest, StoreCategoryRequest, UpdateCategoryRequest, CategoryListRequest, ReasonRequest. Chỉ validated fields đi vào model; payload privileged/unknown bị422.

Resources: tái sử dụng AuthUserResource; thêm AddressResource, ShopResource, ManagedShopResource, CategoryResource. Address không trả user_id. Public Shop không trả owner_id, moderation_reason, archived/deleted_at. Private managed Shop thêm owner_id/archived/moderation_reason cho seller/admin; không serialize owner profile.

Policies: AddressPolicy, ShopPolicy, CategoryPolicy. Không có Gate::before cho ADMIN. Profile luôn lấy User hiện tại, không nhận target ID. Service kiểm tra quyền/trạng thái lại dưới lock trước multi-row writes.

Services: AddressService (default invariant), ShopService (unique owner, slug, lifecycle), CategoryService (tree/slug/delete), SlugGenerator (readable unique suffix), AuditService (actor/entity/action/from/to/reason/request_id/time). Không Repository Pattern, không package permissions, không thêm role SELLER.

## 5. Profile và Address invariants

Profile ghi trực tiếp users.name/phone; email hiển thị read-only. Không nhận role/status/password/email/email_verified_at/deleted_at/avatar_path. Update trả AuthUserResource và cập nhật auth store trên Vue.

AddressService khóa row User trước mọi mutation để serialize cả trường hợp User chưa có địa chỉ, rồi khóa/authorize Address khi sửa/xóa. Tất cả unset/set/delete/promote ở cùng DB transaction với retry deadlock giới hạn3. Có địa chỉ thì đúng1 default:

1. Địa chỉ đầu tiên luôn true, kể cả input false.
2. Đặt B default làm các row khác false trong cùng transaction.
3. Xóa default chọn ID nhỏ nhất còn lại.
4. PATCH default=false mà không còn default khác sẽ normalize về ID nhỏ nhất; danh sách rỗng có0 default.
5. User/Admin không đọc/sửa/xóa/set default Address người khác.

Dùng recipient_name, phone, address_line, ward, district nullable, province, country_code=VN, is_default. Không đổi snapshot Order. Test đã kiểm rollback multi-row Address trong transaction.

## 6. Shop lifecycle và authorization

Seller = User có Shop, role vẫn USER/ADMIN. owner_id unique kể cả soft-deleted. Shop mới luôn PENDING; server gán owner và slug. Slug Str::slug, suffix -2/-3 khi trùng, không tái dùng archived slug; đổi name không đổi slug. Unique DB index là defense cuối; collision allocation được retry transaction mới có giới hạn.

| Actor | Transition |
|---|---|
| ADMIN khác owner | PENDING→ACTIVE hoặc REJECTED |
| Owner | REJECTED→PENDING qua submit |
| ADMIN khác owner | ACTIVE→SUSPENDED |
| ADMIN khác owner | SUSPENDED→ACTIVE |

Moderation khóa actor/owner theo ID tăng rồi Shop; recheck role/ACTIVE/ownership, expected_status và allowed transition. Reactivate/approve cần owner ACTIVE chưa soft-delete. Sai quyền403; stale/repeat/invalid transition409; thiếu reason/enum422. Status và audit cùng transaction; audit failure rollback đã được test.

Owner sửa name/description khi PENDING/ACTIVE/REJECTED; SUSPENDED vẫn đọc own Shop nhưng không mutation. Không nhận owner_id/status/slug/deleted_at/logo_path. Public phải đồng thời Shop ACTIVE, owner ACTIVE và cả hai chưa soft-delete. Không tự thay trạng thái Orders khi suspend Shop.

## 7. Category hierarchy

Adjacency list parent_id từ schema hiện có; không Nested Set. Root là cấp1, tối đa3 cấp. CategoryService khóa actor rồi toàn bộ cây MVP theo ID, dựng parent map dự kiến và đi ngược ancestor với visited set cho từng node. Vì validate toàn cây, move subtree không thể âm thầm làm descendant vượt depth. Self-parent, cycle và depth sai trả422 tại parent_id.

Public query lọc ACTIVE ở chính category lẫn mọi ancestor. Parent INACTIVE ẩn con/cháu mà không đổi status riêng của chúng. Delete chỉ leaf không Products, kể cả Product soft-deleted; bị tham chiếu→409, FK RESTRICT giữ nguyên. Không cascade xóa Products. Admin create/update/delete đều reason+audit nguyên tử. Slug unique, backend sinh và ổn định khi đổi tên.

## 8. Frontend

| Route | View / hành vi |
|---|---|
| /account | AccountView cũ, giữ tương thích auth redirect |
| /account/profile | ProfileView: load/save, field errors, success, sync Pinia user |
| /account/addresses | AddressesView: list/create/edit/delete/default, pagination, badge Mặc định |
| /seller/shop | SellerShopView: missing→create; existing→edit/status; suspended read-only; rejected resubmit |
| /shops | ShopsView: search, pagination, active public shops |
| /shops/:slug | ShopView: public details; loading/error/retry; không fake Product |
| /admin/categories | AdminCategoriesView: tree parent selection, CRUD/status, reason, delete conflict409 |
| /admin/shops | AdminShopsView: filters/details, valid actions, reason, expected_status, chặn self moderation ở UX |

API modules profile.api.js/address.api.js/shop.api.js/category.api.js dùng domain.api.js chung với Axios instance PHASE4. Mutations lấy CSRF cookie trước gửi request, không auto retry mutation. State domain nằm trong component, không tạo Pinia store cho từng entity. useFeedback, DomainField, DomainFeedback, CollectionPager tái sử dụng trạng thái loading/error/field errors/success và pagination.

Navigation trong DefaultLayout thêm account/seller/public/admin links. Router requiresAuth/requiresAdmin đợi auth.initialize; guest→login, USER vào admin→home. Session vẫn là nguồn xác thực backend, không localStorage/token auth.

## 9. Test coverage

- ProfileAddressTest: guest/suspended, load/update own profile, role/status/email/password/path injection, format errors; full Address CRUD/default invariant, admin cũng không vượt ownership, rollback multi-row.
- ShopManagementTest: create PENDING, một Shop/owner kể cả archived, slug collision/stability, injection, ownership và direct Policy assertions, public visibility/resource redaction; admin matrix và owner resubmit, stale/invalid/self/inactive owner, audit rollback, literal search0/%/_/!.
- CategoryManagementTest: public roots/children/ancestor visibility, guest/USER denied, ADMIN CRUD, stable unique slug và audit, self/cycle/depth/subtree protection, child/Product soft-delete constraints, strict validation, audit rollback.
- DomainViews.spec.js: Profile load/submit/Pinia/errors; Address list/create/edit/default/delete/errors; Shop create/existing/suspended/rejected and no privileged payload; admin guard, category CRUD/conflict, allowed moderation/self guard.
- domainApi.spec.js: shared Axios/CSRF ordering, đúng POST shops, expected_status, DELETE body reason, public pagination và CSRF failure không gửi mutation.
- Toàn bộ auth PHASE4 tests vẫn PASS: register/login/me/logout, ACTIVE/session/rate limit, frontend auth form/store/guards.

Đã chạy meaningful rollback tests trên MySQL, chưa thực hiện stress test/concurrent worker benchmark. Không diễn giải unit/HTTP tuần tự là bằng chứng tải đồng thời. Không có browser E2E driver trong phiên; giao diện được kiểm bằng Vue mount/Vitest, production compiler và HTTP route fallback, không tuyên bố đã thao tác thủ công trong browser.

## 10. Real HTTP verification

HTTP dùng backend localhost:8000 và Origin localhost:5173, cookie jars độc lập, X-XSRF-TOKEN, không Bearer hoặc thông tin đăng nhập trong output. Fixture accounts duy nhất theo nonce được tạo riêng và cleanup giới hạn đúng email/ID/relations của fixture.

User: login → GET/PATCH profile → chặn role injection và missing CSRF → tạo2 Address → đổi default → xóa default/promote → tạo Shop PENDING → GET seller Shop → duplicate409. Admin không thể đọc Address của user fixture.

Admin: login → tạo root/child Category → cycle422/delete parent409 → parent INACTIVE làm child404 → reject Shop → owner sửa/submit lại → approve → public200 → stale409 → suspend → public404 và owner update403 → reactivate. Public resource không chứa moderation/owner fields. User/admin logout rồi protected GET401. Tổng54 checks PASS kể cả cleanup và data integrity.

PHASE4 real-cookie regression cũng PASS: registration USER/ACTIVE, hash password, me/logout/login, CSRF thiếu/sai419, CORS allowlist/preflight, host-only HttpOnly local cookie, session cũ trước login không authenticate, replay cookie sau logout401; không có personal access token. Fixture PHASE4 cũng đã cleanup.

Dữ liệu development sau cả hai flow và full suite vẫn khớp baseline từng row:

| Bảng | Rows |
|---|---:|
| users / addresses | 9 / 9 |
| shops / categories | 4 / 12 |
| products / product_images | 28 / 56 |
| carts / cart_items | 3 / 6 |
| orders / shop_orders / order_items | 2 / 4 / 4 |
| payments / reviews / audit_logs | 2 / 2 / 2 |
| personal_access_tokens / migrations | 0 / 17 |

Hash được tính trên rows có thứ tự ID và không in dữ liệu riêng tư. Kiểm tra này không so sánh auto-increment counters hoặc session/rate-limit cache; các counter có thể tiến sau fixture insert/delete. Không reset counter hoặc xóa cache/data người dùng. Các helper và bằng chứng cục bộ nằm trong `.runtime/` được ignore, không đưa credential vào source/report.

## 11. Security audit

PASS: server-side ownership; ADMIN middleware + Policy; strict allowlist và validated(); body owner/user/role/status/slug/media injection bị chặn; không unguarded request.all(); public/private resource separation; Category cycle/depth/backend validation; bound escaped LIKE; atomic audit; CSRF+session; pagination/rate limits; no persistent browser auth token. Kiểm tra source không tìm thấy createToken/HasApiTokens/Gate::before bypass hoặc TODO domain còn bỏ dở. Secret scan dùng giá trị env thực trong bộ nhớ, chỉ báo PASS/FAIL, không in secret.

API vẫn dùng cấu hình local HTTP PHASE4. Avatar/logo read-only và chưa có upload handler. Không thiết lập auth architecture khác.

## 12. Files tạo/sửa

Tạo mới backend:

- `app/Http/Controllers/Api/V1/`: ProfileController.php, AddressController.php, SellerShopController.php, PublicShopController.php, AdminShopController.php, PublicCategoryController.php, AdminCategoryController.php.
- `app/Http/Requests/Domain/`: 13 Request classes liệt kê ở mục4.
- `app/Http/Resources/`: AddressResource.php, ShopResource.php, ManagedShopResource.php, CategoryResource.php.
- `app/Policies/`: AddressPolicy.php, ShopPolicy.php, CategoryPolicy.php.
- `app/Services/`: AddressService.php, ShopService.php, CategoryService.php, SlugGenerator.php, AuditService.php.
- `app/Http/Middleware/EnsureAdmin.php`.
- `tests/Feature/`: ProfileAddressTest.php, ShopManagementTest.php, CategoryManagementTest.php.

Sửa backend: `routes/api.php`, `app/Providers/AppServiceProvider.php`. Models/enums/migrations/factories/seeders/auth source và config không thay đổi trong PHASE5.

Tạo mới frontend:

- `src/api/`: domain.api.js, profile.api.js, address.api.js, shop.api.js, category.api.js.
- `src/composables/useFeedback.js`.
- `src/components/`: DomainField.vue, DomainFeedback.vue, CollectionPager.vue.
- `src/views/`: ProfileView.vue, AddressesView.vue, SellerShopView.vue, ShopsView.vue, ShopView.vue, AdminCategoriesView.vue, AdminShopsView.vue.
- `src/__tests__/`: DomainViews.spec.js, domainApi.spec.js.

Sửa frontend: `src/router/index.js`, `src/router/authGuard.js`, `src/layouts/DefaultLayout.vue`. Axios instance, auth store/API/forms và dependencies giữ nguyên.

Docs sửa: README.md, docs/api-design.md, docs/business-rules.md, docs/permissions.md, docs/state-transitions.md. Tạo báo cáo này. Build assets/runtime helper là local ignored artifacts.

## 13. Known limitations

- Chưa avatar upload, Shop logo upload, email/password change.
- Chưa Product CRUD/upload/search hoặc Shop product listing; UI không tạo fake products.
- Không Cart/Checkout/Order business flow/Payment/VNPay/Review/Dashboard/Realtime/Chat/Auction/Docker.
- Category whole-tree locking và parent dropdown tải các trang phù hợp catalog MVP nhỏ; chưa benchmark cho catalog lớn.
- Shop archive/restore chưa có API trong phase này; owner có Shop archived không tạo Shop thứ hai. Admin list/detail vẫn xem được archived.
- Không browser E2E hoặc concurrent load test trong phạm vi verification hiện tại; evidence thực tế được phân biệt ở trên.
