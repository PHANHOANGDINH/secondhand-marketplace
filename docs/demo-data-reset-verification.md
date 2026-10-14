# Dựng lại dữ liệu demo — 34 tỉnh, danh mục đa dạng

Ngày 2026-10-14, PHP 8.4.25 (Herd), Composer 2.10.2. Chủ dự án yêu cầu xoá DB dev và dựng lại với nhiều loại hàng hơn và danh sách tỉnh mới. Lệnh `migrate:fresh` / `db:seed` trên DB dev được phép **cho lần này**; quy tắc cấm trong `CLAUDE.md` vẫn giữ nguyên.

## Kết quả: PASS

| Hạng mục | Kết quả |
| --- | --- |
| Backend đầy đủ | 483 test / 6.331 assertions PASS (DB test); Pint PASS |
| Chạy lại sau khi đổi thứ tự tạo tin | `MarketplaceCatalogSeederTest` + `ShopProvinceTest`: 6 test PASS |
| Frontend | 494 test / 42 file PASS; oxlint, ESLint (không `--fix`), build PASS |
| E2E M1–M3 trên DB test nạp dữ liệu mới | 3/3 PASS, 1440 và 390 px, không tràn ngang, 0 lỗi JS |

## Tỉnh thành: 34 đơn vị

`backend/config/provinces.php` thay 63 tỉnh cũ bằng 34 đơn vị cấp tỉnh có hiệu lực từ 01/07/2025 (Nghị quyết 202/2025/QH15), mã 2 chữ số theo Quyết định 19/2025/QĐ-TTg: 6 thành phố (Hà Nội 01, Hải Phòng 31, Huế 46, Đà Nẵng 48, TP. Hồ Chí Minh 79, Cần Thơ 92) và 28 tỉnh. Ba thành phố nổi bật vẫn là 79, 01, 48.

- API `/provinces`, bộ lọc `province` và kiểm tra `province_code` của gian hàng đều đọc từ file này, nên mã cũ đã sáp nhập (ví dụ 10 — Lào Cai cũ) bị từ chối 422; mã hiện hành của Lào Cai là 15.
- Không cần migration: cột `shops.province_code` không đổi kiểu, DB dev được dựng lại nên không còn mã cũ.
- Test: `ShopProvinceTest` kiểm 34 mục; E2E kiểm 35 lựa chọn (34 + "Toàn quốc").

## Seeder mới `MarketplaceCatalogSeeder`

Dữ liệu ở `backend/resources/demo-marketplace/catalog-vn.json`. Seeder chỉ chạy trên DB dev/test của dự án, bắt buộc có `DEMO_PASSWORD` ≥ 12 ký tự, chạy lại không đổi gì (bỏ qua user/gian hàng/tin đã có).

| Nội dung | Số lượng |
| --- | --- |
| Danh mục cha | 12: Điện tử, Thời trang, Gia dụng, Sách & học tập, Xe cộ, Nội thất, Điện lạnh, Mẹ & bé, Thể thao & dã ngoại, Thú cưng, Đồ văn phòng, Nhạc cụ |
| Danh mục con | 37 (dùng lại các danh mục con đã có như Laptop, Điện thoại, Áo…) |
| Gian hàng | 20, ở 18 tỉnh khác nhau; tài khoản `vn-seller-01…20@example.test`, mật khẩu = `DEMO_PASSWORD` |
| Tin đăng | 137, đủ trạng thái (đang hiển thị, nháp, chờ duyệt, bị từ chối kèm lý do, đã ẩn, hết hàng) |
| Ảnh | 1–2 ảnh mỗi tin, hình minh hoạ SVG tự vẽ theo loại hàng, màu theo danh mục |

Thứ tự tạo tin được xen kẽ giữa các danh mục, và thời gian đăng trải đều 30 ngày theo đúng thứ tự đó, nên tab "Mới nhất" lẫn nhiều loại hàng, tin mới nhất đăng cách đây vài giờ.

Ngoài ra `DemoMarketplaceSeeder` gán tỉnh cho 3 gian hàng demo cũ (79, 01, 48), và trang chủ có thêm icon cho các danh mục mới.

## DB dev

1. Backup trước khi xoá: `.runtime/reset-backup.sql` (37 bảng; trước đó có 15 user, 7 gian hàng, 51 tin, 9 đơn) và file tải lên ở `.runtime/reset-storage-backup/` (`products`, `product-videos`).
2. `migrate:fresh` → `db:seed` → `db:seed --class=FinalDemoSeeder` → `db:seed --class=MarketplaceCatalogSeeder`.
3. Dựng lại lần hai sau khi đổi thứ tự tạo tin; trạng thái ngay trước đó (chỉ có dữ liệu seed) ở `.runtime/reset-backup-2.sql`.

Kết quả trên DB dev: 179 tin (152 đang hiển thị), 25 gian hàng (không gian hàng nào thiếu tỉnh), 12 danh mục cha, 6 phiên đấu giá, 7 đơn, 2 voucher. Ảnh cũ trong `storage/app/public/products` và `product-videos` đã được thay bằng ảnh của dữ liệu mới.

Lưu ý: tất cả tài khoản cũ trên DB dev đã mất; các tài khoản demo được tạo lại với mật khẩu `DEMO_PASSWORD` trong `backend/.env`. Phiên đăng nhập đang mở trên trình duyệt sẽ không còn hợp lệ.

## Việc còn mở

- Hành trình E2E A–H và voucher chưa chạy lại trên dữ liệu mới.
