# UI "chợ tốt" v2 — verification

Ngày 2026-10-14, PHP 8.4.25 (Herd). Bố cục và luồng theo kiểu sàn rao vặt; màu, tên, logo, hình vẽ và câu chữ đều là của "pass lại" (token trong `frontend/src/styles/tokens.css`, tên trong `config/brand.js`). Logic nghiệp vụ, API cũ và test cũ giữ nguyên.

## Kết quả: PASS

| Hạng mục | Kết quả |
| --- | --- |
| Backend đầy đủ | 481 test / 5.726 assertions PASS (trên `secondhand_marketplace_test`); Pint PASS |
| Frontend | 494 test / 42 file PASS; oxlint, ESLint (không `--fix`), build PASS |
| E2E hành trình M1–M3 | 3/3 PASS, 36 ảnh `M-*` ở 1440 và 390 px, không tràn ngang, 0 lỗi JS |
| Stack E2E | PHP :8001, Reverb :8081, Vite :5174, tất cả `APP_ENV=testing` — không đăng nhập vào DB dev |

## Các phần

| Phần | Commit | Nội dung |
| --- | --- | --- |
| P1 | `7d76369` | `shops.province_code` (migration additive, có `down()`), danh sách 63 tỉnh thành, lọc tin đăng theo `province`; admin xem khu vực của gian hàng |
| P2 | `06f49f3` | Header mới, nút chuyển chế độ (người mua / người bán / quản trị), ngăn kéo menu, footer |
| P3 | `9348faa` | Trang chủ: khẩu hiệu + ô tìm kiếm + chọn khu vực, hàng danh mục, 4 tab tin đăng, hộp thoại chọn khu vực |
| P4 | `d314f3f` | `/my-ads` "Quản lý tin" thay `/seller/products` (địa chỉ cũ chuyển hướng, giữ query); 7 tab có số đếm từ một truy vấn tổng hợp; tab nằm trong URL |
| Kiểm chứng | commit này | Hành trình E2E M, hai sửa CSS, tài liệu |

## P4 — chi tiết kỹ thuật

- `GET /api/v1/seller/products/counts`: một câu `SELECT` duy nhất trên `products` (đếm theo từng `ProductStatus`, `AUCTION` bằng `EXISTS` trên đúng tập "đang giữ hàng" của `FixedPriceEligibility::claims()`, `ALL`). Test đếm số truy vấn = 1 và kiểm tra không lộ sản phẩm của gian hàng khác.
- `GET /api/v1/seller/products?auction=1`: lọc các tin đang bị phiên đấu giá giữ. Tham số lạ vẫn trả 422.
- Người chưa có gian hàng: API trả 403 theo policy sẵn có; giao diện hiện "+ Tạo cửa hàng" và không gọi API sản phẩm.
- Quy tắc `auction_locked` giữ nguyên: tin đang bị giữ chỉ có nút "Xem", không có nút sửa/ẩn.
- Nếu API số đếm lỗi, các tab vẫn dùng được, chỉ không hiện số.

## E2E (`frontend/e2e/marketplace.mjs`)

Dữ liệu: nạp `.runtime/p1-backup.sql` vào DB test, chạy `migrate` trên DB test, rồi `FinalDemoSeeder` trên DB test (hai gian hàng demo có khu vực `79` và `01`).

| Hành trình | Kiểm tra |
| --- | --- |
| M1 khách | Khẩu hiệu, 4 tab, hàng danh mục; khách không bị hộp thoại khu vực chen ngang; hộp thoại có 3 thành phố nổi bật + 63 tỉnh thành + "Toàn quốc", tìm được "Hà Nội"; chọn TP. Hồ Chí Minh → mọi thẻ đều thuộc khu vực đó; tải lại vẫn nhớ; tìm kiếm sang `/products?province=79`; ô tìm kiếm thu gọn ở header |
| M2 người bán | Hộp thoại khu vực tự mở đúng một lần sau khi đăng nhập; người dùng thường chỉ thấy 2 chế độ; chế độ được nhớ theo từng người; `/my-ads` có 7 số đếm khớp với DB; tab "Đang đấu giá" chỉ có tin bị giữ và không có nút hành động; trạng thái rỗng có hình vẽ + "Đăng tin"; `/seller/products?tab=draft` chuyển về `/my-ads?tab=draft`; ẩn một tin → DB thành `HIDDEN`, số đếm giảm 1 |
| M3 quản trị | Thấy đủ 3 chế độ; vào khu quản trị bằng lối tắt; trang gian hàng hiện "Khu vực: TP. Hồ Chí Minh"; ngăn kéo menu; footer |

Ảnh: `docs/ui-redesign/e2e/M-*-1440.png` và `M-*-390.png`.

## Lỗi tìm thấy khi chạy E2E và đã sửa

1. **Trang chủ tràn ngang trên điện thoại.** Hàng danh mục cuộn ngang nằm trong lưới không giới hạn cột nên đẩy trang rộng tới 551 px ở màn 390 px. Sửa: `grid-template-columns: minmax(0, 1fr)` cho `.home-hero`.
2. **Bộ kiểm tra tràn ngang bỏ sót lỗi trên.** Khung nhìn giả lập điện thoại tự nở theo nội dung nên `scrollWidth` luôn bằng `innerWidth`. Sửa `lib.mjs`: so với bề rộng đã yêu cầu. Các hành trình A–H và voucher chưa được chạy lại với phép kiểm tra chặt hơn này.
3. **Ô tìm kiếm ở header bị bóp còn ~125 px ở 1440 px** khi đã đăng nhập. Sửa: lối tắt "Quản lý tin" trên header chỉ hiện từ 1500 px (nút chuyển chế độ đã có sẵn lối này).
4. **Vite chết với `EBUSY`** vì theo dõi thư mục hồ sơ Edge trong `frontend/e2e/`. Sửa `go.sh`: hồ sơ nằm ở `%TEMP%/passlai-e2e`.

## DB dev

- Migration P1 đã chạy trên DB dev sau khi `mysqldump` vào `.runtime/p1-backup.sql` (chi tiết ở commit P1).
- Không chạy `migrate:fresh/refresh`, `db:wipe`, `db:seed` trên DB dev trong đợt này.

## Việc còn mở

1. ~~**Gian hàng demo trên DB dev chưa có khu vực**~~ Đã xử lý ngày 2026-10-14: DB dev được dựng lại theo yêu cầu của chủ dự án, mọi gian hàng đều có tỉnh (xem [demo-data-reset-verification.md](demo-data-reset-verification.md)). Ghi chú cũ: (`province_code` là `NULL`) vì `db:seed` đã bị cấm trở lại. Hệ quả: khi demo trên DB dev, chọn một tỉnh sẽ cho danh sách rỗng. Cách xử lý: người bán tự chọn khu vực ở trang "Gian hàng của tôi", hoặc chủ dự án cho phép chạy `FinalDemoSeeder` thêm một lần.
2. Hành trình A–H và voucher nên được chạy lại một lần với phép kiểm tra tràn ngang mới (mục 2 ở trên).
3. Trong lúc dò lỗi Vite, `npx` đã tải một bản Vite vào bộ đệm npm của máy (`npm-cache/_npx`), không vào dự án; thư mục `.vite/` sinh ra ở gốc repo đã được xóa.
