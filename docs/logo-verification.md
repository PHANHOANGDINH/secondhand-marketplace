# Đổi logo: kiểm chứng

Ngày 2026-11-03. Chưa commit. Chỉ đổi frontend và tài liệu thiết kế.

## Đã đổi
- **Logo:** con dấu hộp mở và chữ "passdi" lấy từ ảnh chủ dự án gửi. Con dấu nằm trên đĩa giấy đặc; máy bay giấy bay ra ngoài vòng nên không cắt tròn. Chữ "pass" và "di" là hai mặt nạ màu, đổi theo giao diện sáng và tối.
- **Kích thước:** logo thường từ 40 lên 52 px, logo nhỏ từ 34 lên 44 px.
- **Favicon:** `favicon.png`, `favicon.ico`, `apple-touch-icon.png`.
- **Màu thương hiệu:** `--brand` và `--brand-text` đổi từ cam sang xanh lá của logo, để không còn sót cam của logo cũ. Việc này cũng đổi màu các hình trang trí trong banner và hướng dẫn lần đầu. Khu đấu giá vẫn giữ nút cam như trước.
- **Topbar người bán và quản trị ở điện thoại:** nhãn chế độ co lại và cắt bớt ở màn ≤ 420 px, để logo to hơn không đẩy nút menu ra ngoài.

Tài liệu đã cập nhật: `DESIGN.md`, `docs/design-system.md`.

## Quyết định người làm tự chọn
Dải băng "Tiếp nối hành trình" không đưa vào logo, vì ở cỡ header chữ trên băng chỉ cao khoảng 6 px, không đọc được. Slogan trong `config/brand.js` giữ nguyên.

Logo cũ được lưu ở `.runtime/old-logo-20261103/`.

## Kết quả
| Cổng | Kết quả |
| --- | --- |
| `npx vitest run` | PASS, 586/586; 2 kỳ vọng kích thước trong `Theme.spec.js` được cập nhật |
| `npx oxlint .`, `npx eslint .` | PASS, 0 lỗi |
| `npm run build` | PASS |
| `node scripts/contrast.mjs` | PASS, 58/58 |
| Tràn ngang ở 360, 390, 768, 820, 992, 1200, 1280, 1440 px (API giả) | PASS sau khi sửa topbar người bán ở 360 px |
| Xem ảnh: trang chủ, người bán ở 360 px, bản thử logo trên nền sáng và tối | Đạt |
| Backend | Không chạy lại: không đổi file backend |

## Chưa kiểm
- Favicon trên tab thật và iPhone; trình duyệt có thể giữ icon cũ trong bộ nhớ đệm.
- Chất lượng khi in hoặc phóng rất lớn: ảnh gốc chỉ rộng 1024 px. Nếu có bản vector (SVG) thì nên thay.
