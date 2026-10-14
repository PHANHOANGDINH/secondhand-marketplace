# Thẻ sản phẩm: 4 cột, giảm giá, đấu giá, voucher — verification

Ngày 2026-10-14, PHP 8.4.25 (Herd).

## Kết quả: PASS

| Hạng mục | Kết quả |
| --- | --- |
| Backend đầy đủ | 487 test / 6.417 assertions PASS (DB test); Pint PASS |
| Frontend | 497 test / 43 file PASS; oxlint, ESLint (không `--fix`), build PASS |
| E2E M1–M3 (DB test, 1440 và 390 px) | 3/3 PASS, không tràn ngang, 0 lỗi JS; ảnh `docs/ui-redesign/e2e/M-cards-*.png` |

## Thay đổi

- **Trang chủ:** lưới tin 4 cột (3 cột dưới 1200 px, 2 cột trên điện thoại), thẻ to hơn.
- **Giá trước khi giảm (`products.compare_at_price`):**
  - Migration chỉ thêm cột (DECIMAL 15,2, NULL), `down()` xoá cột; đã thử rollback rồi migrate lại trên DB test.
  - Server bắt buộc giá trước khi giảm cao hơn giá bán (422 `compare_at_price`), kể cả khi người bán chỉ đổi giá bán.
  - Đổi giá trước khi giảm thì tin đang bán quay về chờ duyệt, như khi đổi giá, để tránh "giá ảo".
  - Chỉ để hiển thị: đơn hàng vẫn tính từ `price` do server tính lại.
  - `discount_percent` tính bằng BCMath, làm tròn xuống.
  - Form đăng tin có ô "Giá trước khi giảm (không bắt buộc)"; xem trước hiển thị giá gạch ngang.
- **Voucher gian hàng trên thẻ:**
  - Lấy voucher tốt nhất đang dùng được của gian hàng (ACTIVE, trong thời hạn, còn lượt), ưu tiên giảm cố định giá trị lớn nhất.
  - Không gửi mã code ra API công khai; trang sản phẩm ghi "nhắn người bán để nhận mã".
  - Nạp bằng một truy vấn cho cả trang; test xác nhận số truy vấn không tăng khi thêm gian hàng có voucher.
- **Đấu giá trên thẻ:** API trả thêm `starts_at`/`ends_at`. Thẻ ghi "Đang đấu giá · còn 2 ngày", "Sắp đấu giá · mở sau 3 giờ" hoặc "Đấu giá đã kết thúc · chờ người thắng thanh toán". Thẻ đang đấu giá hiện giá đấu hiện tại, không hiện giảm giá.
- **Trang sản phẩm:** cùng các thông tin trên.
- **Seeder:** cứ 5 tin thì 1 tin có giá trước khi giảm (khoảng +20%, làm tròn 10.000 ₫), thêm voucher TUDO10 (10%, tối đa 30.000 ₫) cho "Tủ Đồ Secondhand". Chỉ áp dụng cho lần seed sau.

## DB dev

- Trước khi migrate: `.runtime/discount-backup.sql`, snapshot từng dòng `.runtime/discount-rows-before.json` / `-after.json`.
- So sánh: không thêm, không xoá dòng nào; chỉ bảng `products` đổi hash (137 dòng) vì có thêm cột mới, giá trị NULL.
- Dữ liệu DB dev **không** được sửa: chưa tin nào có giá trước khi giảm và chỉ có voucher GOCTAO50K, nên trên DB dev thẻ mới chỉ hiện nhãn voucher (gian hàng "Góc Táo Cũ") và nhãn đấu giá.
- Các file ảnh/video của DB dev vẫn đủ (kiểm 0 file thiếu) sau khi dọn file do lần chạy E2E tạo.
