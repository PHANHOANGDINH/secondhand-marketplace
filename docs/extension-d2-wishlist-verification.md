# D2 — Sản phẩm yêu thích: báo cáo xác minh

Ngày xác minh: 09/10/2026. **Kết luận: PASS — sẵn sàng demo D2.**

## Phạm vi và dữ liệu

- Bảng mới `wishlists`: `id`, `user_id`, `product_id`, `created_at` (microsecond); không cần `updated_at`.
- Khóa ngoại tới users/products với cascade khi xóa cứng; unique `(user_id, product_id)`; index `(user_id, created_at, id)` phục vụ danh sách.
- Migration cộng thêm: `2026_10_09_161815_create_wishlists_table.php`. Chỉ migration này được áp dụng vào DB phát triển; không chạy `migrate:fresh`.
- USER, Seller (vai trò USER) và ADMIN đang hoạt động đều có thể lưu sản phẩm công khai, kể cả sản phẩm của chính mình. Đây là thao tác đánh dấu, không phải mua hàng.
- Không giữ tồn kho/giá, không thay đổi giỏ hàng, checkout, đơn hàng, thanh toán, đấu giá hoặc quy tắc video.

## API và bảo vệ truy cập

| Endpoint | Hành vi |
| --- | --- |
| `GET /api/v1/wishlist` | Chỉ tài khoản hiện tại; phân trang `page`, `per_page` (mặc định 20, tối đa 100); mới lưu trước, id làm thứ tự phụ ổn định. Trả ProductResource và `wishlisted_at`. |
| `POST /api/v1/products/{product}/wishlist` | Chỉ sản phẩm đạt predicate công khai hiện có; transaction/row lock, firstOrCreate và unique constraint. Gọi lại trả 200, không tạo bản ghi mới hoặc đổi ngày lưu. |
| `DELETE /api/v1/products/{product}/wishlist` | Chỉ xóa bản ghi của người gọi. Gọi lại hoặc ID không có bản ghi vẫn trả 200/false, không tiết lộ wishlist người khác. Có thể bỏ lưu sản phẩm đã ẩn/xóa mềm. |

Các endpoint dùng auth Sanctum, kiểm tra tài khoản hoạt động, SPA session/CSRF và domain throttle hiện có. Không nhận user_id từ client để chọn chủ wishlist. Không sửa cơ chế xác thực.

Danh sách luôn áp dụng ProductQuery public visibility, bao gồm shop/category/chủ shop/trạng thái sản phẩm/tồn kho theo quy tắc hiện có. Sản phẩm không còn công khai bị loại khỏi phản hồi; dữ liệu seller/user riêng tư không được bổ sung vào resource. Xóa mềm giữ bản ghi nhưng không hiển thị sản phẩm; nếu trở lại công khai, sản phẩm có thể xuất hiện lại.

## UI và hiệu năng

- `FavoriteButton`: nút native có aria-label tiếng Việt, aria-pressed, busy/disabled, thao tác bàn phím và thông báo lỗi. Không nằm trong link sản phẩm.
- Dùng chung tại ProductCard (homepage, D1 search/discovery), chi tiết sản phẩm và panel sản phẩm Video Theater.
- Trang `/wishlist` yêu cầu đăng nhập, dùng ProductCard/CollectionPager, skeleton, lỗi/thử lại và trạng thái rỗng “Bạn chưa lưu sản phẩm nào.”; CTA “Khám phá sản phẩm”. Khi trang cuối hết sản phẩm, trở về trang đầu.
- Menu tài khoản có “Sản phẩm yêu thích” trên desktop/mobile; không thêm mục vào thanh điều hướng chính.
- Khách bấm tim theo luồng Login/intended return hiện có; không có wishlist guest giả trong localStorage.
- Store wishlist riêng chia sẻ trạng thái đã được backend xác nhận; chặn gửi trùng khi đang tải. Không cập nhật lạc quan, nên lỗi giữ nguyên trạng thái trước đó. Đổi tài khoản xóa cache và bỏ qua phản hồi cũ.
- ProductQuery dùng `withExists` có user scope cho `is_wishlisted`, khách nhận false. Eager loading sản phẩm trong video discovery sử dụng cùng annotation. Không gọi API riêng cho mỗi thẻ; test kiểm tra số query không tăng theo số sản phẩm.
- Thẻ đấu giá giữ nguyên Arena CTA; wishlist độc lập với bid, countdown và settlement.

## Demo seeder

FinalDemoSeeder được mở rộng với bốn wishlist cho hai buyer demo, chỉ lưu sản phẩm còn công khai; firstOrCreate giúp chạy lại không trùng. Test seeder chạy hai lần, xác nhận số bản ghi và tính idempotent. Không chạy seeder này trên DB phát triển trong D2, không đổi mật khẩu hoặc ghi đè dữ liệu người dùng.

Nếu muốn nạp wishlist demo cùng bộ dữ liệu demo hiện có: `cd backend` rồi `php artisan db:seed --class=FinalDemoSeeder`. Không cần reset DB.

## Kiểm thử và lệnh

Skill `testing-best-practices` được repository tham chiếu nhưng không có trong môi trường. Theo ngoại lệ đã được người dùng phê duyệt cho D2, quy ước và regression test lân cận của repository được dùng làm tiêu chuẩn kiểm thử.

| Kiểm tra | Kết quả |
| --- | --- |
| Backend đầy đủ | **369 tests / 3.676 assertions PASS** |
| Wishlist + FinalDemoSeeder tập trung | **12 tests / 170 assertions PASS** (nằm trong tổng backend) |
| Frontend đầy đủ | **379 tests / 31 files PASS** |
| Pint | **PASS** sau chuẩn hóa định dạng |
| Frontend lint (oxlint + ESLint) | **PASS** |
| Production build | **PASS** |

Lệnh chạy từ các thư mục tương ứng:

```powershell
cd backend
php vendor/bin/phpunit
php vendor/bin/phpunit tests/Feature/WishlistTest.php tests/Feature/FinalDemoSeederTest.php
php vendor/bin/pint --dirty --format agent

cd ../frontend
npm run test:unit -- --run --maxWorkers=4
npm run lint
npm run build
```

Lượt frontend đầu gặp timeout khởi tạo worker khi máy chạy nhiều tiến trình kiểm thử. Lượt đầy đủ cuối giới hạn bốn worker bằng CLI đã PASS; không đổi cấu hình ứng dụng/test để che lỗi. Backend sử dụng môi trường DB test cô lập theo cấu hình repository.

Backend bổ sung chín tests: auth/IDOR, user/admin/seller, visibility thay đổi sau khi lưu, hidden/deleted/inactive shop/category/owner, thiếu stock, validation, duplicate POST/repeated DELETE, phân trang/thứ tự, unique constraint, annotation và query count. Frontend bổ sung chín tests về trạng thái/nút tim, lỗi, pending request, account isolation, guest return, trang wishlist/loading/empty/error, navigation và sự nhất quán giữa các thẻ. Test VideoDiscovery dùng Pinia đúng với dependency mới của control.

## Trình duyệt thật và bảo toàn DB

Frontend `http://localhost:5173`; Laravel `http://127.0.0.1:8000`; dùng HTTP/CSRF/session thật, không mock API.

- Kiểm tra 1440 / 1024 / 768 / 390 px: không tràn ngang, ảnh tải được, nhãn nút truy cập được và trạng thái tim đúng. Xem ảnh chụp desktop/mobile xác nhận bố cục đọc được.
- Guest từ kết quả D1 có query → Login → quay lại đúng trang/query.
- Lưu bằng UI; POST lặp không tăng số bản ghi hoặc đổi ngày lưu.
- Xóa bằng bàn phím Space → trạng thái rỗng; lưu lại từ chi tiết → Theater thấy cùng trạng thái.
- Lưu sản phẩm đấu giá → wishlist vẫn có Arena CTA đúng.
- Logout, tải trực tiếp `/wishlist` → Login. Không có JavaScript exception trong lượt kiểm tra cuối.
- Dọn toàn bộ bản ghi wishlist được tạo thủ công; bảng mới còn **0** bản ghi sau verification.
- So sánh hash theo từng ID trên **22 bảng business hiện có** với snapshot bảo toàn đã có trước D2: giống hoàn toàn cả trước và sau D2. Người dùng/mật khẩu/sản phẩm/đơn hàng và dữ liệu demo cũ không thay đổi.
- Thay đổi DB phát triển có chủ ý duy nhất ở schema: bảng wishlist và bản ghi migration mới. Session/cache do đăng nhập thử là dữ liệu vận hành, không phải thay đổi business.

## Giới hạn còn lại

- Không có đồng bộ realtime wishlist giữa nhiều tab/thiết bị; tải lại ứng dụng lấy trạng thái mới từ backend.
- Bản ghi của sản phẩm không còn công khai được giữ nhưng không hiển thị. Chưa có màn quản lý riêng cho những bản ghi này.
- Browser smoke dùng buyer demo có kiểm soát; ADMIN/Seller/IDOR và các tổ hợp visibility được xác minh bằng backend tests.
- Không thêm tính năng ngoài D2, dependency hoặc thay đổi hành vi các module ổn định.

## File thuộc D2

Danh sách sau so với snapshot ngay trước D2, không gom các thay đổi D1/phase cũ đang tồn tại trong worktree:

- `backend/app/Models/Product.php`
- `backend/app/Models/Wishlist.php`
- `backend/app/Services/ProductQuery.php`
- `backend/app/Http/Resources/ProductResource.php`
- `backend/app/Http/Requests/Domain/WishlistListRequest.php`
- `backend/app/Http/Controllers/Api/V1/ProductVideoController.php`
- `backend/app/Http/Controllers/Api/V1/WishlistController.php`
- `backend/routes/api.php`
- `backend/database/factories/WishlistFactory.php`
- `backend/database/migrations/2026_10_09_161815_create_wishlists_table.php`
- `backend/database/seeders/FinalDemoSeeder.php`
- `backend/tests/Feature/FinalDemoSeederTest.php`
- `backend/tests/Feature/WishlistTest.php`
- `frontend/src/api/wishlist.api.js`
- `frontend/src/components/FavoriteButton.vue`
- `frontend/src/components/ProductCard.vue`
- `frontend/src/layouts/DefaultLayout.vue`
- `frontend/src/router/index.js`
- `frontend/src/stores/wishlist.js`
- `frontend/src/views/ProductView.vue`
- `frontend/src/views/WishlistView.vue`
- `frontend/src/__tests__/VideoDiscovery.spec.js`
- `frontend/src/__tests__/Wishlist.spec.js`
- `frontend/src/components/home/VideoTheater.vue`
- `docs/extension-d2-wishlist-verification.md`
