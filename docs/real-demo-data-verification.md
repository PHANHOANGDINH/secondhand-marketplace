# Thay dữ liệu mẫu bằng dữ liệu demo mới: kiểm chứng

Ngày 2026-11-03. Thao tác trên DB dev và file ảnh, không đổi mã nguồn. Chưa commit.

## Quyết định từ chủ dự án
Sản phẩm có thật với ảnh giấy phép mở · giữ admin, Nguyễn Minh Anh, Gmail #52 và 54 danh mục · xóa đơn, đấu giá, đánh giá, tin nhắn cũ rồi tạo lại ít đơn và đấu giá · khoảng 40 tin.

## Đã xóa
28 tài khoản mẫu còn lại, 20 gian hàng, 138 sản phẩm (gồm "sơ đồ erd"), 18 đơn hàng và thanh toán, 6 phiên đấu giá, đánh giá, tin nhắn, 2 voucher, 3 video, 74 dòng audit của các tài khoản đã xóa, 183 file ảnh sản phẩm cũ.

## Hiện có
| Loại | Số lượng |
| --- | --- |
| Tài khoản | 11: 3 giữ lại, 5 người bán và 3 người mua hư cấu (`@demo.passdi.local`) |
| Gian hàng | 6: Góc Táo Cũ giữ lại và 5 gian hàng hư cấu |
| Tin đăng | 40: 33 đang bán, 7 đã có đơn; 4 trong số đang bán là tin đấu giá |
| Đơn hàng | 7, tạo qua luồng đặt hàng thật: 4 hoàn tất, 1 đang giao, 1 đang xử lý, 1 chờ xác nhận |
| Đấu giá | 4: 3 đang diễn ra (hết hạn sau 8 đến 12 ngày), 1 sắp bắt đầu; 6 lượt ra giá |

## "Dữ liệu thật" nghĩa là gì ở đây
- **Thật:** tên mẫu sản phẩm và ảnh. Ảnh có giấy phép mở, ghi nguồn ở từng tin và trong `docs/demo-data-credits.md`.
- **Tham khảo:** Honda Wave lấy theo mức phổ biến khoảng 8,7 triệu đồng trên mạng giữa năm 2026; iPhone và MacBook ước lượng từ giá của mẫu gần nhất (iPhone 11, MacBook Air M1), không phải đúng mẫu đang đăng; giá các món khác là ước lượng của người làm, chưa đối chiếu.
- **Hư cấu:** người bán, người mua, địa chỉ, số điện thoại, mô tả tình trạng. Mỗi tin ghi rõ "Tin minh họa cho đồ án".
- **Không dùng:** tin đăng, ảnh hay thông tin người bán thật từ các sàn rao vặt.

## An toàn
1. Sao lưu: `.runtime/before-real-demo-20261103/` gồm `dev-before.sql` và `public-disk-before.tgz` (189 file).
2. Diễn tập 2 lần trên DB test chứa bản sao DB dev, ghi file vào thư mục tạm; lần đầu tìm ra lỗi ở chính phép kiểm tra khóa ngoại nhiều cột, đã sửa.
3. Kết quả sau khi chạy thật: hash không đổi với 3 tài khoản giữ lại, gian hàng 1, danh mục, địa chỉ, audit giữ lại, file avatar và logo; không có dòng trỏ tới dữ liệu đã xóa; 40 ảnh đều có file.
4. Gửi mail và broadcast bị tắt trong lúc nhập, không có thư nào gửi đi.

Script: `.runtime/real-demo/reset-and-import.php`. Không dùng `db:wipe`, `db:seed`, `migrate:fresh`.

## Kiểm tra trên web thật (khách, chỉ đọc)
Trang chủ, danh sách, chi tiết sản phẩm, gian hàng, sàn đấu giá, phòng đấu giá ở 1440 px và trang chủ ở 390 px: hiển thị đúng, không tràn ngang.

## Chưa kiểm
Đăng nhập bằng tài khoản hư cấu trên web thật; thao tác người bán và quản trị trên dữ liệu mới.

## Lưu ý
- Tài khoản hư cấu dùng cùng mật khẩu với Nguyễn Minh Anh.
- Tab Video và phần đánh giá hiện trống; không tạo đánh giá giả.
- Mô tả gian hàng Góc Táo Cũ giữ nguyên từ dữ liệu mẫu, trong đó có câu "được kiểm tra" chưa có căn cứ.
- Đấu giá chạy theo thời gian thật nên sẽ kết thúc sau 8 đến 12 ngày.
- Chạy các seeder cũ sẽ đưa dữ liệu mẫu trở lại.
