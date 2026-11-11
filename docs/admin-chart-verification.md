# Biểu đồ doanh thu quản trị và chuyển khu

## Phạm vi

1. Trang `/admin/dashboard` có biểu đồ cột doanh thu theo tháng, 6 tháng gần nhất.
2. Menu chuyển khu trong khu người bán và quản trị: bấm là sang đúng khu.

## Biểu đồ

- API `GET /admin/dashboard` thêm `revenue_by_month`: mỗi tháng có `period`, `revenue`, `completed_orders`.
- Cùng định nghĩa với doanh thu người bán: `subtotal` của phần đơn `COMPLETED`, tính theo `completed_at`, giờ Việt Nam.
- Tiền tính ở DECIMAL trong SQL, trả chuỗi qua `bcadd`. Tháng trống vẫn có trong chuỗi với `0.00`.
- Giao diện dùng lại `RevenueChart.vue`, kèm bảng "Xem số liệu doanh thu". Không thêm thư viện, không có migration.
- Khi 6 tháng không có đơn hoàn tất, trang hiện thông báo "Chưa có đơn hoàn tất trong 6 tháng gần nhất."
- Giới hạn: `total_revenue` là toàn thời gian, biểu đồ chỉ 6 tháng, nên hai số có thể khác nhau. Tiêu đề biểu đồ ghi rõ phạm vi.

Số liệu đọc từ DB dev (chỉ đọc):

| Tháng | Doanh thu | Đơn hoàn tất |
|---|---|---|
| 2026-06 đến 2026-08 | 0 | 0 |
| 2026-09 | 1.590.000 | 3 |
| 2026-10 | 290.000 | 1 |
| 2026-11 | 0 | 0 |

Tổng 1.880.000 ₫, khớp `total_revenue`.

## Chuyển khu

Lỗi: khi đang ở `/seller/*`, `/my-ads` hoặc `/admin/*`, `ModeSwitcher` khóa các lựa chọn khác, và nút chọn không chuyển trang.

| Bấm | Đi tới |
|---|---|
| Dành cho người mua | `/` |
| Dành cho người bán, đã có gian hàng | `/seller/dashboard` |
| Dành cho người bán, chưa có gian hàng | `/seller/shop` |
| Quản trị (chỉ tài khoản admin thấy) | `/admin` |
| Khu đang mở | Ở nguyên trang |

Menu tài khoản ở khu người mua giữ nguyên cách cũ: bấm chỉ đổi nhóm lối tắt, không rời trang. Phân quyền vẫn do route guard và backend quyết định.

## Kiểm chứng

- Backend: 554/554 (`secondhand_marketplace_test`), pint sạch. Test mới kiểm tra ranh giới tháng theo giờ Việt Nam, tháng trống, loại đơn chưa hoàn tất.
- Frontend: vitest 592/592, oxlint 0 lỗi, eslint (không `--fix`) 0 lỗi, build đạt. Spec mới `ModeSwitcher.spec.js`, thêm ca biểu đồ trong `AdminPhase11.spec.js`.
- Chrome với API giả lập:
  - Biểu đồ đúng ở sáng 1440 px, tối 1440 px, sáng 360 px; không tràn ngang ở 360 px.
  - Luồng quản trị → người bán → quản trị → người bán → người mua đi đúng trang, không còn nút bị khóa, menu tự đóng.
  - Luồng này chưa chạy với phiên đăng nhập thật trên backend thật.
- Màu cột: `--primary` đạt tương phản từ 3:1 với nền ở cả hai giao diện. Bộ kiểm tra bảng màu báo FAILED ở cả hai; các tiêu chí đó dành cho bảng màu nhiều nhóm, còn biểu đồ chỉ có một chuỗi và dùng màu thương hiệu, nên không đổi màu.
