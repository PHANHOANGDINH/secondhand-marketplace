# Ảnh đại diện, ảnh gian hàng và sửa lỗi "Chưa tải được danh mục": kiểm chứng

Ngày 2026-11-03. Chưa commit. Không có migration: cột `users.avatar_path` và `shops.logo_path` đã có sẵn.

## 1. Lỗi "Chưa tải được danh mục"
**Nguyên nhân (đã đo):** `categoryOptions` gọi API riêng cho từng danh mục, nên 54 danh mục tốn 55 request. Trang `/products` và menu mỗi nơi gọi một lần, tổng 110 request, trong khi API công khai giới hạn 120 lượt/phút mỗi IP. Vượt giới hạn thì API trả 429, và menu báo "Chưa tải được danh mục". Trang `/products` có cùng nguyên nhân với thông báo "Chưa tải được lựa chọn bộ lọc".

Lỗi không tái hiện được bằng cách gọi `/categories` một lần (trả 200). Nguyên nhân được suy ra từ số request và giới hạn; không có log 429 của đúng lần bạn gặp.

**Cách sửa:** `GET /categories?all=1` trả toàn bộ cây trong 1 truy vấn; frontend tự dựng cây. Không nới giới hạn. Menu có thêm nút "Tải lại" khi lỗi, và phân biệt "lỗi" với "chưa có danh mục".

**Đo sau khi sửa trên hệ thống thật:** mở `/products` rồi mở menu 6 lần liên tiếp: 54 danh mục, 2 request mỗi lượt (trước đây 110), toàn bộ trả 200.

## 2. Đổi ảnh đại diện
- **Ở đâu:** Tài khoản, Hồ sơ cá nhân. Ảnh hiện ở header, menu tài khoản và trang Quản lý tin; chưa có ảnh thì hiện chữ cái đầu.
- **Cách dùng:** bấm "Chọn ảnh" và chọn; ảnh được lưu ngay, không có bước cắt. Có nút "Xóa ảnh".
- **Giới hạn PHP 2 MB:** trình duyệt tự thu về JPEG vuông 512 px trước khi gửi, nên ảnh điện thoại lớn vẫn dùng được.
- **An toàn:** server chỉ nhận JPEG, PNG, WebP tối đa 2 MB, giải mã rồi vẽ lại, nên siêu dữ liệu như GPS không vào file công khai. Tên file do server tạo. Ảnh cũ bị xóa sau khi lưu xong. Chỉ chủ tài khoản đang hoạt động được đổi.

## 3. Ảnh gian hàng
- **Ở đâu:** Gian hàng của tôi, cùng thao tác như ảnh đại diện. Công khai ở trang gian hàng, danh sách gian hàng và khối người bán trên trang sản phẩm.
- **Quy tắc:** chỉ chủ gian hàng; gian hàng bị khóa không đổi hay xóa được; mỗi lần đổi ghi audit `shop.updated`; `logo_path` vẫn bị chặn ở form sửa gian hàng.
- **Lỗi bắt được khi kiểm thử:** `ProductQuery` chỉ lấy một số cột của `shops` nên trang sản phẩm chưa có ảnh; đã thêm `logo_path`.

## 4. Menu tài khoản
Bấm khối avatar, tên và email ở đầu menu sẽ tới Hồ sơ cá nhân và đóng menu.

## Quyết định người làm tự chọn
Ảnh cắt giữa tự động, không có bước chọn vùng cắt, theo yêu cầu "đơn giản với người dùng". Ảnh chỉ hiện với chính người dùng ở các vị trí trên; chưa hiện cho người khác xem (ví dụ trong chat), vì đó là phạm vi chưa được yêu cầu.

## File
Mới: `AvatarService`, `SquarePicture`, `UploadAvatarRequest`, `AvatarTest`, `ShopLogoTest`, `UserAvatar.vue`, `AvatarPicker.vue`, `PicturePicker.vue`, `Avatar.spec.js`, `NavDrawer.spec.js`.
Sửa: `ProfileController`, `PublicCategoryController`, `CategoryListRequest`, `AuthUserResource`, `routes/api.php`, `CategoryManagementTest`, `ShopService`, `SellerShopController`, `ProductQuery`, `Shop`, `ShopResource`, `ProductResource`, `catalogOptions.js`, `profile.api.js`, `shop.api.js`, `NavDrawer.vue`, `ProfileView.vue`, `SellerShopView.vue`, `ShopView.vue`, `ShopsView.vue`, `ProductView.vue`, `DefaultLayout.vue`, `MyAdsView.vue`.

## Kết quả
| Cổng | Kết quả |
| --- | --- |
| Backend test (`secondhand_marketplace_test`) | PASS, 553/553 |
| `npx vitest run` | PASS, 586/586 (56 file) |
| `pint --test` trên các file đã sửa | PASS |
| `npx oxlint .`, `npx eslint .` | PASS, 0 lỗi |
| `npm run build` | PASS |
| `node scripts/contrast.mjs` | PASS, 58/58 |
| Trình duyệt, menu (hệ thống thật, khách, chỉ đọc) | PASS |
| Trình duyệt, avatar (DB test): chọn ảnh 11,8 MB, hiện 512×512, header đổi ngay, còn sau khi tải lại, xóa được | PASS |
| Không để lại file: số file trong `storage/app/public` | 187 trước, 187 sau |
| DB dev | 30 người dùng, 0 có avatar: không bị ghi |
| Tràn ngang trang hồ sơ ở 360 px | PASS |
| Trình duyệt, ảnh gian hàng (DB test): người bán đổi, khách thấy ở 3 nơi, xóa được, không tràn ở 360 px | PASS, 7/7 bước |
| Trình duyệt, khối đầu menu tài khoản (API giả, 1280 và 360 px) | PASS |

## Còn mở
- Quản trị viên chưa có nút gỡ ảnh gian hàng vi phạm. Hiện chỉ có thể báo cáo hoặc tạm khóa cả gian hàng.
- Trang chi tiết sản phẩm để trống khung ảnh khi file ảnh không tồn tại, trong khi thẻ sản phẩm hiện "Chưa có ảnh". Thấy trên dữ liệu thử; ngoài phạm vi nên chưa sửa.

## Chưa kiểm
Ảnh chụp thật từ điện thoại (gồm ảnh xoay dọc và HEIC); thao tác trên điện thoại thật; tải lên khi mạng chậm.
