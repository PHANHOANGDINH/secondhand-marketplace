# Giao diện các trang ở chân trang: kiểm chứng

Ngày 2026-11-03. Chưa commit. Chỉ đổi frontend, theo hướng "Đời thứ hai".

## Đã đổi
Sáu trang `/help`, `/safety`, `/contact`, `/about`, `/terms`, `/privacy`, cùng trong `StaticPageView.vue`:
- **Menu chung:** cột bên từ 992 px; dưới đó là một hàng cuộn ngang, tự cuộn tới trang đang xem.
- **Bố cục thẻ** (Trợ giúp, Liên hệ, Giới thiệu): icon, nội dung và liên kết bước tiếp theo.
- **Danh sách** (An toàn mua bán): các dòng trong một khung, có thông báo nổi bật ở đầu trang.
- **Văn bản đánh số** (Quy chế, Bảo mật): dễ dẫn chiếu từng điều.
- **Giới thiệu:** khối màu `--field` như trang chủ.
- **Chân trang:** khung focus ôm sát chữ, không còn kéo hết chiều rộng cột (đúng chỗ trong ảnh chủ dự án gửi).

## Nội dung đã sửa
| Trang | Trước | Sau | Vì sao |
| --- | --- | --- | --- |
| Trợ giúp, Quy chế | Tin chờ quản trị viên duyệt | Tin hiện ngay, quản trị viên xem xét tin bị báo cáo | Khớp `AdminProductsView`, `SellerShopView`, trang chủ |
| Trợ giúp | Không có | Thêm "Xác minh tài khoản" | Tính năng mới |
| Bảo mật | Không có | Thêm "Thư điện tử và mật khẩu": lưu ở dạng băm | Khớp mã nguồn backend |
| An toàn | Không chia sẻ mật khẩu | Thêm cả mã xác minh | Tính năng mới |
| Giới thiệu | Một đoạn mô tả | Bốn thẻ, nội dung chia nhỏ | Theo bố cục mới |

Không thêm số điện thoại, email hay địa chỉ hỗ trợ nào. Trang Liên hệ vẫn nói rõ đây là đồ án học tập, không có tổng đài.

Quy chế và Chính sách bảo mật là văn bản đồ án, chưa qua rà soát pháp lý.

## File
Sửa: `views/StaticPageView.vue`, `components/layout/SiteFooter.vue`.
Mới: `__tests__/StaticPages.spec.js`.

## Kết quả
| Cổng | Kết quả |
| --- | --- |
| `npx vitest run` | PASS, 575/575 (54 file) |
| `npx oxlint .`, `npx eslint .` | PASS, 0 lỗi |
| `npm run build` | PASS |
| `node scripts/contrast.mjs` | PASS, 58/58 (không thêm token) |
| Tràn ngang 360, 820, 1440 px | PASS, cả 6 trang |
| Chế độ tối | Xem ảnh `/about`, `/privacy`; 4 trang còn lại dùng cùng token |

## Chưa kiểm
Thao tác bàn phím thật; điện thoại thật. Liên kết `/my-ads`, `/reports`, `/cart` đòi đăng nhập: khách được chuyển tới trang đăng nhập, luồng này chưa bấm thử trên trình duyệt.
