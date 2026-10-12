# Fix wave 1 + wave 2 — báo cáo kiểm chứng

Ngày thực hiện: 12/10/2026. Nguồn yêu cầu: audit read-only cùng ngày (`docs/audit/PROJECT_AUDIT_PART1..3.md`).
Môi trường: PHP 8.4.25 (Herd), Laravel 13.33.0, MySQL local, Node 24.16.0. Không dùng PHP 8.0 của XAMPP.

## Kết quả chung

| Hạng mục | Kết quả |
| --- | --- |
| Backend full test (`secondhand_marketplace_test`) | **420 test / 4.523 assertions PASS** (baseline 399 / 3.948) |
| Frontend `npx vitest run` | **431 test / 35 file PASS** (baseline 413 / 33) |
| `vendor/bin/pint --test` | PASS |
| `npx oxlint .`, `npx eslint .` (không `--fix`) | sạch, exit 0 |
| `npm run build` | OK |
| DB dev `secondhand_marketplace` | **không đổi** (25 bảng, 416 dòng, hash trùng trước/sau) |
| Migration | không cần, không tạo |
| Kiểm tra trình duyệt 1440/390 px | **BỎ QUA** (lý do ở mục "Không thực hiện") |
| Push | không push |

Commit (chưa push):

| Commit | Nội dung |
| --- | --- |
| `e452b6b` | `checkpoint: MVP + A1–A5 auction, B1–B3 video, messaging, notifications, D1–D4 (pre-fix wave)` |
| `d9b3002` | `fix(wave1): router dup, demo seeder password, cancel reason labels, server-time cancel, docs sync` |
| commit kế tiếp (xem `git log -1`) | `fix(wave2): auction-claim guards for seller product actions; stock-only updates keep products listed` |

Lưu ý về thứ tự: commit wave 1 được tạo **ngay sau khi wave 1 đã tự kiểm chứng xong và trước khi sửa code wave 2**, để hai wave không lẫn file vào nhau (`docs/auction-state-machines.md`, `README.md` được cả hai wave chạm tới). Vì vậy phần "số liệu test thật" của mục 1.8 và chính báo cáo này nằm trong commit wave 2.

## Baseline (trước khi sửa)

| Bước | Kết quả | Bằng chứng |
| --- | --- | --- |
| `git status --short \| wc -l` | 343 dòng | — |
| Snapshot DB dev | 25 bảng, 416 dòng; chụp 2 lần liên tiếp cho hash giống nhau (không có tiến trình nào đang ghi) | `.runtime/fix12-before.json` |
| Backend full | 399 test / 3.948 assertions PASS, 441 s; **không có test fail ở baseline** | `.runtime/fix12-backend-baseline.log` |
| Frontend | 413 test / 33 file PASS | `.runtime/fix12-frontend-baseline.log` |

Snapshot dùng script chỉ đọc `.runtime/fix12-snapshot.php`: `SELECT *` theo thứ tự khóa chính rồi băm SHA-256 từng bảng; script từ chối chạy nếu DB không phải `secondhand_marketplace`.

## Bảng thực hiện

| Mục | Trạng thái | File đã sửa | Test thêm / sửa |
| --- | --- | --- | --- |
| 1.1 Checkpoint commit | ĐÃ LÀM | — (402 đường dẫn được stage) | — |
| 1.2 README môi trường | ĐÃ LÀM | `README.md` | — |
| 1.3 Router trùng | ĐÃ LÀM | `frontend/src/router/index.js` | `frontend/src/__tests__/FixWave1.spec.js` (route name/path duy nhất) |
| 1.4 DemoUserSeeder | ĐÃ LÀM | `backend/database/seeders/DemoUserSeeder.php`, `README.md` | `backend/tests/Feature/DemoSeederTest.php` (sửa 1, thêm 1) |
| 1.5 Lý do hủy đơn | ĐÃ LÀM | `frontend/src/utils/labels.js`, `frontend/src/views/OrderView.vue` | `FixWave1.spec.js` (unit + render buyer/seller) |
| 1.6 Đồng hồ SellerAuctionsView | ĐÃ LÀM | `frontend/src/views/SellerAuctionsView.vue` | `FixWave1.spec.js` (3 test lệch đồng hồ) |
| 1.7 Dọn `counter.js` | ĐÃ LÀM | xóa `frontend/src/stores/counter.js` | — |
| 1.8 Đồng bộ tài liệu | ĐÃ LÀM | `README.md`, `docs/auction-state-machines.md`, `docs/final-project-index.md`, `docs/final-module-inventory.md`, `docs/final-security-summary.md`, `docs/final-release-checklist.md`, `docs/final-testing-summary.md` | — |
| 2.1 Chặn thao tác seller khi có claim | ĐÃ LÀM | `backend/app/Services/ProductService.php`, `backend/app/Services/ProductImageService.php`, `backend/app/Http/Resources/ManagedProductResource.php`, `frontend/src/composables/productPresentation.js`, `frontend/src/components/seller/AuctionLockNotice.vue` (mới), `frontend/src/views/SellerProductsView.vue`, `frontend/src/views/SellerInventoryView.vue`, `frontend/src/components/seller/SellerProductEditor.vue`, `docs/auction-state-machines.md` | `backend/tests/Feature/AuctionClaimGuardTest.php` (mới, 8 test), `frontend/src/__tests__/FixWave2.spec.js` (mới) |
| 2.2 Chỉ đổi tồn kho không bắt duyệt lại | ĐÃ LÀM (có 1 điều kiện bổ sung, xem bên dưới) | `backend/app/Services/ProductService.php`, `frontend/src/views/SellerInventoryView.vue`, `frontend/src/components/seller/SellerProductEditor.vue`, `docs/extension-d4-seller-inventory-verification.md` | `backend/tests/Feature/StockOnlyUpdateTest.php` (mới, 12 test), sửa `SellerInventoryTest.php`, `ProductManagementTest.php`, `FixWave2.spec.js` |

## Chi tiết wave 1

### 1.1 Checkpoint

- `git add -n .` liệt kê 402 đường dẫn; không có `.env`, `.env.testing`, `frontend/.env`, `*.log`, `.runtime/`, `frontend/dist/`, `node_modules/`, `vendor/`, file cookie. Chỉ có các file `.env.example`.
- Đã so khớp **giá trị thật** của `ZALOPAY_KEY1`, `ZALOPAY_KEY2`, `REVERB_APP_SECRET`, `REVERB_APP_KEY`, `DEMO_PASSWORD`, `APP_KEY`, `DB_PASSWORD` (đọc từ `.env`, không in ra) với toàn bộ file sắp stage: không trùng. `MAIL_PASSWORD`/`MAIL_USERNAME` đang là giá trị rỗng/placeholder nên bỏ qua.
- Lưu ý: literal mật khẩu demo cũ đã nằm trong lịch sử từ commit `4faed97` và trong checkpoint; wave 1 gỡ nó khỏi seeder ở commit sau. Nếu repo sẽ công khai thì coi literal đó là đã lộ.

### 1.2 README

Thêm hai mục vào phần cài đặt local: yêu cầu PHP ≥ 8.4.1 (do `composer.lock`; PHP 8.0 của XAMPP không chạy được; dùng Herd PHP 8.4 hoặc nâng PHP của XAMPP; MySQL vẫn dùng XAMPP được) và bảng 4 tiến trình `serve`, `reverb:start`, `schedule:work`, `npm run dev`, kèm cảnh báo thiếu `schedule:work` thì đấu giá không tự LIVE/ENDED và ZaloPay không reconcile. README vốn viết bằng tiếng Anh nên phần bổ sung giữ tiếng Anh.

### 1.4 DemoUserSeeder

- Đọc `config('demo.password')`; không phải chuỗi ≥ 12 ký tự thì ném `RuntimeException` (cùng thông báo với `FinalDemoSeeder`) trước khi tạo bất kỳ user nào.
- User đã tồn tại vẫn được bỏ qua, không đổi mật khẩu — test chạy seed lần hai với mật khẩu khác và xác nhận hash cũ còn nguyên.
- `backend/.env.example` đã có sẵn tên biến `DEMO_PASSWORD`; `.env.testing.example` không cần thêm vì test tự đặt `config(['demo.password' => …])`.
- **Chưa xử lý (ngoài phạm vi):** `backend/database/factories/UserFactory.php:21` vẫn dùng một literal làm mật khẩu mặc định cho factory trong test. Xem câu hỏi mở.

### 1.5 Lý do hủy đơn

- `formatCancellationReason(text)`: nhận diện tiền tố `OUT_OF_STOCK|DAMAGED|UNFULFILLABLE` ở **đầu chuỗi**, hiển thị `Hết hàng — <lý do>`; không có tiền tố thì trả nguyên văn; tiền tố lạ giữ nguyên.
- Grep toàn `frontend/src`: `cancellation_reason` chỉ được hiển thị ở `OrderView.vue` (dùng chung cho buyer và seller). Trang admin orders **không** hiển thị trường này và `AdminOrderResource` cũng không trả về nó, nên không có chỗ thứ ba cần sửa.
- Backend không đổi cách lưu.

### 1.6 Đồng hồ server

`SellerAuctionsView` dùng lại `createAuctionClock()` trong `composables/auctionTimeSync.js`, đồng bộ bằng `server_time` của response danh sách (dự phòng: `server_time` của dòng đầu). Không tạo cơ chế mới. Backend vẫn là lớp chặn thật.

### 1.8 Tài liệu

- Settlement: bảng và sơ đồ Mermaid trong `auction-state-machines.md` chỉ còn giá trị thật `null / NO_WINNER / AWAITING_CHECKOUT / ORDER_CREATED / CHECKOUT_EXPIRED`, ghi rõ là giá trị tính toán, không phải cột DB hay enum; `final-project-index.md` sửa tương ứng. Sau khi sửa, grep `docs/` (trừ `docs/audit/`) và README không còn `PENDING_CHECKOUT`, `NONE`, `EXPIRED` trong ngữ cảnh settlement. `final-sequences.md` và `final-erd.md` không chứa các tên này nên không sửa.
- D4 ✅: thêm vào `README.md`, `final-project-index.md`, và một dòng module trong `final-module-inventory.md`. Repo không có file "lộ trình" riêng; trạng thái D1–D4 và D5 được ghi ở README và project index.
- "Giới hạn đã biết": thêm 6 gạch đầu dòng vào `final-security-summary.md` và `final-release-checklist.md`.
- Số liệu test: `final-testing-summary.md` và README ghi số chạy thật hôm nay, giữ số C1 làm số liệu lịch sử.

## Chi tiết wave 2

### 2.1 Chặn theo claim đấu giá

Định nghĩa claim giữ nguyên `FixedPriceEligibility::claims()`. Lời gọi `assertAvailable()` được đặt ngay sau `ProductLocks::lock()` trong cùng transaction ở:

| Thao tác | Vị trí |
| --- | --- |
| `update` (mọi field, kể cả request không làm đổi field nào) | `ProductService::update` |
| `submit`, `hide`, `withdraw`, `revise` | `ProductService::action` |
| `destroy` | `ProductService::delete` |
| Ảnh: upload, set primary/đổi vị trí, reorder, delete | `ProductImageService::upload/update/reorder/delete` |

Kết quả: HTTP 409, message `Sản phẩm đang được giữ cho đấu giá.`, DB và file storage không đổi (ảnh đã ghi tạm trước transaction được dọn bởi nhánh `catch` sẵn có).

**`submit` / `withdraw` / `revise` có trạng thái nào vừa có claim vừa hợp lệ không?**

- Claim chỉ được tạo trên sản phẩm ACTIVE. Sau wave này seller không còn tự đưa sản phẩm rời ACTIVE khi có claim, nên đường duy nhất là Admin kiểm duyệt `ACTIVE → HIDDEN`.
- `submit` (từ HIDDEN): **có thể xảy ra** sau khi Admin ẩn sản phẩm đang đấu giá → đã chặn.
- `withdraw` (từ PENDING) và `revise` (từ REJECTED): không có đường nghiệp vụ nào dẫn tới trạng thái đó khi còn claim. Vẫn chặn đồng nhất (cùng một dòng code trong `action()`), và có test ép trạng thái trực tiếp để xác nhận.

**Ngoại lệ có chủ đích**

- Video (upload, sửa metadata, ẩn/hiện, xóa) **không** bị chặn: không đổi trạng thái sản phẩm. Có test xác nhận, đã ghi vào `docs/auction-state-machines.md`.
- Admin moderation giữ nguyên (test xác nhận Admin vẫn ẩn được sản phẩm đang LIVE) → hạng mục D5.

**Thứ tự khóa đã xác minh (đọc code)**

| Luồng | Thứ tự khóa |
| --- | --- |
| `AuctionService::placeBid`, `start`, `create` (qua `context()`) | users → shop → categories (toàn bảng) → product → auction → (bids) |
| `AuctionCheckoutService::checkout` | users → address → shop → categories → product → auction → order |
| `CheckoutService::checkout` | users → cart → order cũ → address → cart items → shops → categories → products → auction (qua `assertAvailable`) |
| `AuctionService::finalize` | auction → bids (không khóa product) |
| `AuctionService::cancel` | user → auction (không khóa product) |
| **Seller sửa sản phẩm/ảnh (mới)** | users → shop → categories → product (`ProductLocks::lock`) → **auction** (`assertAvailable`, `lockForUpdate`) |

Guard mới khóa auction **sau** product, trùng thứ tự với đặt giá, mở phiên và checkout của người thắng. Không luồng nào khóa auction rồi mới khóa product, nên không tạo chu trình khóa mới. Nhánh đổi tồn kho trước đây đã khóa theo đúng thứ tự này.

**Frontend**

- `ManagedProductResource` thêm `auction_locked` (bool), lấy từ quan hệ `auctionClaim`. Mọi nơi tạo resource này (`SellerProductController@index/show`, `SellerInventoryController@index`, `AdminProductController`) đều đi qua `ProductQuery::related()` đã eager-load `auctionClaim` → không thêm N+1; test đếm query xác nhận danh sách 20 sản phẩm (một nửa có phiên) không tốn thêm query so với 2 sản phẩm.
- `sellerActions()` trả mảng rỗng khi `auction_locked`. Component mới `AuctionLockNotice` hiển thị "Sản phẩm đang được giữ cho đấu giá nên tạm thời không thể chỉnh sửa." kèm liên kết `/seller/auctions/{id}` khi có id hợp lệ.
- `SellerProductsView`: ẩn nút chuyển trạng thái/xóa, nhãn "Xem" thay cho "Xem / Sửa". `SellerInventoryView`: thay nút cập nhật tồn kho bằng thông báo. `SellerProductEditor`: vô hiệu hóa form và trình sửa ảnh, ẩn nút lưu; trình sửa video vẫn dùng được.

### 2.2 Chỉ đổi tồn kho

Áp dụng khi sau `fill()` chỉ có `stock_quantity` thay đổi (gửi kèm các field nội dung **không đổi giá trị** vẫn tính là chỉ đổi tồn kho — đây là cách trình sửa sản phẩm gửi dữ liệu).

| Trạng thái | Tồn kho mới | Kết quả | Test |
| --- | --- | --- | --- |
| ACTIVE | > 0 | ACTIVE | `test_active_listing_stays_active_when_only_stock_changes` |
| ACTIVE | 0 | SOLD | `test_active_listing_becomes_sold_at_zero_stock` |
| SOLD | > 0, đủ điều kiện | ACTIVE, mua được ngay | `test_sold_listing_reopens_and_is_immediately_purchasable` |
| SOLD | > 0, không đủ điều kiện | PENDING | `test_sold_listing_returns_to_review_when_it_cannot_safely_reopen` |
| SOLD | 0 | SOLD, không ghi gì | `test_sold_listing_with_zero_stock_is_left_untouched` |
| DRAFT / PENDING / REJECTED / HIDDEN | bất kỳ | giữ nguyên | `test_other_statuses_keep_their_status_for_any_stock` |
| Tồn kho + nội dung | — | theo quy tắc cũ | `test_stock_combined_with_content_keeps_the_review_rules` |

Điều kiện SOLD → ACTIVE theo yêu cầu: `published_at` khác null; shop và chủ shop ACTIVE; chuỗi danh mục ACTIVE — dùng lại `OrderLocks::eligible()`, không viết logic mới.

**Điều kiện bổ sung thứ tư (lệch so với yêu cầu, cần xác nhận).** Khi cài đặt, tôi phát hiện ba điều kiện trên mở ra một đường lách kiểm duyệt:

- Sản phẩm SOLD cho phép sửa nội dung (`ProductService::update` chỉ đưa ACTIVE/HIDDEN về PENDING) và đổi ảnh (`ProductImageService::changed` cũng vậy) **mà không đổi trạng thái**.
- Trước đây bước nhập hàng `SOLD → PENDING` chính là điểm chặn. Với quy tắc mới, seller có thể: bán hết → sửa tên/mô tả/ảnh tùy ý → nhập 1 đơn vị → sản phẩm lên sàn ngay với nội dung chưa ai duyệt.

Vì vậy `restockable()` còn yêu cầu: không có bản ghi audit `product.updated`, `product.images_uploaded`, `product.image_updated`, `product.images_reordered`, `product.image_deleted` nào sau lần `product.moderated → ACTIVE` gần nhất. Vi phạm thì rơi về PENDING (hành vi cũ), tức chỉ **chặt hơn** yêu cầu, không nới lỏng. Sau khi Admin duyệt lại, lần bán hết kế tiếp mở bán lại trực tiếp được. Test: `test_sold_listing_edited_without_review_cannot_reopen_through_a_restock`. Hạn chế đã biết: bản ghi `product.updated` do đổi tồn kho **trước** wave này cũng được tính là "có chỉnh sửa", nên vài sản phẩm cũ có thể vẫn về PENDING ở lần nhập hàng đầu tiên.

**Giữ nguyên:** optimistic lock `expected_updated_at` (409 khi lệch, 422 khi thiếu); chặn theo claim (2.1); IDOR (seller khác và user không có shop nhận 403).

**Audit:** chỉ đổi tồn kho ghi `product.stock_adjusted`, `reason = "stock: <cũ> → <mới>"`, `from_status`/`to_status` đúng thực tế. Thay đổi lẫn lộn vẫn ghi `product.updated` như cũ.

**Tương tác với hủy đơn hoàn kho (`ShopOrderService`)**

- Logic "safe restore" chỉ chạy khi sản phẩm **đang SOLD**. Nếu seller đã nhập hàng (ACTIVE) thì nhánh đó bị bỏ qua, tồn kho được cộng thêm, trạng thái giữ ACTIVE — không bị đẩy sang HIDDEN.
- Test: checkout làm SOLD → seller nhập 5 → buyer hủy → tồn kho 7, ACTIVE, hiển thị công khai (`test_cancellation_after_a_restock_adds_stock_and_keeps_the_listing_active`).
- Chiều ngược: SOLD → hủy trước khi nhập → ACTIVE, tồn kho khôi phục, sau đó nhập tiếp vẫn ACTIVE (`test_cancellation_before_any_restock_restores_the_sold_listing`).
- Thêm: nhập hàng → bán hết lần hai → hủy → vẫn khôi phục ACTIVE (`test_sold_out_again_after_a_restock_still_restores_safely_on_cancellation`).

**Frontend:** thông báo sau khi lưu ở `SellerInventoryView` — giữ trạng thái: "Đã cập nhật tồn kho."; SOLD → ACTIVE: "Đã cập nhật tồn kho. Sản phẩm đã mở bán lại."; về PENDING: thông báo chờ duyệt như cũ. Dòng trợ giúp của form và ghi chú trong `SellerProductEditor` được viết lại theo quy tắc mới.

## Test đổi kỳ vọng

| Test | Trước | Sau | Lý do |
| --- | --- | --- | --- |
| `SellerInventoryTest::test_quick_stock_reuses_existing_moderation_and_conflict_guards` → đổi tên thành `test_quick_stock_keeps_approved_listings_on_sale_and_keeps_conflict_guards` | ACTIVE → 0 → SOLD; SOLD → 2 → PENDING | Sản phẩm đã duyệt: ACTIVE → 6 giữ ACTIVE; phiên bản cũ → 409; → 0 → SOLD; → 2 → ACTIVE. Sản phẩm chưa từng duyệt (`published_at` null): SOLD → 2 → PENDING | Quy tắc 2.2. Phần 409 khi `expected_updated_at` lệch được giữ |
| `ProductManagementTest::test_material_and_stock_edits_follow_review_and_optimistic_rules` | ACTIVE đổi tồn kho 9 → PENDING | ACTIVE đổi tồn kho 9 → ACTIVE. Bước cuối SOLD → 2 vẫn PENDING | Quy tắc 2.2. Bước cuối vẫn PENDING vì test đã đổi tên sản phẩm mà chưa có lần duyệt nào sau đó (điều kiện bổ sung) |
| `DemoSeederTest::test_seed_is_coherent_and_repeatable` | không cần cấu hình | đặt `demo.password`; kiểm tra thêm mật khẩu không bị ghi đè khi seed lại | Mục 1.4 |

Không test nào khác phải sửa. `FinalDemoSeederTest` và toàn bộ bộ test đấu giá/checkout pass không đổi.

## Số liệu trước / sau

| | Baseline | Sau wave 1 + 2 | Chênh lệch |
| --- | --- | --- | --- |
| Backend test | 399 | 420 | +21 (seeder 1, claim guard 8, stock 12) |
| Backend assertions | 3.948 | 4.523 | +575 |
| Backend thời gian | 441 s | 409 s | — |
| Frontend test | 413 | 431 | +18 (wave 1: 8, wave 2: 10) |
| Frontend file | 33 | 35 | +2 |

## Snapshot DB dev

| | Trước (`.runtime/fix12-before.json`) | Sau (`.runtime/fix12-after.json`) |
| --- | --- | --- |
| Số bảng | 25 | 25 |
| Tổng số dòng | 416 | 416 |
| Bảng có hash khác | — | **không có** |

25 bảng gồm 24 bảng nghiệp vụ và `migrations`. Thư mục `.runtime/` bị gitignore nên các file này không vào commit.

## Không thực hiện

- **Kiểm tra trình duyệt (mục 5 của kiểm chứng cuối): bỏ qua.** Để xem ba màn hình yêu cầu cần dữ liệu ở trạng thái cụ thể (sản phẩm SOLD đã duyệt, sản phẩm đang bị phiên đấu giá giữ, đơn bị seller hủy). Tạo các fixture đó nghĩa là ghi vào DB dev — bị cấm — hoặc dựng một bộ server riêng trỏ sang DB test. Tôi không dựng bộ đó trong phiên này. Hành vi giao diện được kiểm bằng test component (Vitest) thay cho trình duyệt thật; **chưa có xác nhận trực quan ở 1440 px và 390 px**.
- Không chạy `npm run lint` (script có `--fix`); chạy `oxlint`/`eslint` không sửa file.

## Chuyển sang D5

- Admin hủy phiên đấu giá.
- Admin ẩn sản phẩm đang đấu giá: tự hủy/đóng phiên hoặc chặn thao tác (hiện vẫn ẩn được, phiên không tự xử lý, người thắng có thể không checkout được).
- Refund ZaloPay và UI xử lý `reconciliation_required`.
- Khóa toàn bảng `categories` trong mọi giao dịch ghi.
- Hệ quả khi hết hạn checkout của người thắng (thông báo, xử lý).

## Câu hỏi mở

1. **Điều kiện thứ tư của SOLD → ACTIVE** (không có chỉnh sửa nội dung/ảnh chưa duyệt) có được chấp nhận không? Nếu muốn đúng ba điều kiện như yêu cầu ban đầu thì cần một cách khác để chặn việc sửa nội dung/ảnh khi SOLD (ví dụ đưa SOLD vào nhóm "sửa nội dung thì về PENDING") — đó là thay đổi hành vi ngoài phạm vi wave này.
2. `UserFactory` còn literal mật khẩu mặc định cho test. Có cần thay bằng giá trị ngẫu nhiên/cấu hình không? Literal cũ của seeder cũng đã nằm trong lịch sử git.
3. `composer.json` ghi `php: ^8.3` trong khi lockfile cần ≥ 8.4.1. Có nâng ràng buộc lên `^8.4` cho khớp không? (Không sửa vì ngoài phạm vi.)
4. Có cần một lượt kiểm tra trình duyệt thật cho ba màn hình trên, chạy trên bộ server tách riêng trỏ DB test không?
5. Request `PATCH` sản phẩm không làm đổi field nào trước đây trả 200; nay trả 409 nếu sản phẩm đang bị phiên đấu giá giữ. Hành vi này theo đúng yêu cầu "update với bất kỳ field nào", nêu ra để người tích hợp biết.
