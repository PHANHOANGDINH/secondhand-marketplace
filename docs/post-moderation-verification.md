# Đăng bán không cần duyệt, tự ẩn khi bị báo cáo, bảng chi tiết và phiếu bộ lọc — verification

Ngày 2026-10-14, PHP 8.4.25 (Herd). Quyết định của chủ dự án: đăng tin và mở gian hàng không cần duyệt; admin chỉ xử lý sau, khi có báo cáo.

## Kết quả: PASS

| Hạng mục | Kết quả |
| --- | --- |
| Backend đầy đủ | 490 test / 6.430 assertions PASS (DB test); Pint PASS |
| Frontend | 499 test / 44 file PASS; oxlint, ESLint (không `--fix`), build PASS |
| E2E M1–M3 (DB test, 1440 và 390 px) | 3/3 PASS, không tràn ngang, 0 lỗi JS; ảnh `M-admin-filters-*`, `M-admin-product-detail-*` |

## Nghiệp vụ mới

| Tình huống | Trước | Bây giờ |
| --- | --- | --- |
| Người bán bấm "Đăng bán" | PENDING, chờ admin duyệt | ACTIVE ngay (vẫn kiểm tra đủ ảnh, giá, tồn kho, gian hàng/danh mục) |
| Tạo gian hàng | PENDING, chờ duyệt | ACTIVE ngay |
| Sửa nội dung, ảnh, giá của tin đang bán | Về PENDING | Giữ ACTIVE |
| Nhập thêm hàng cho tin đã bán hết | PENDING nếu chưa được duyệt lại | ACTIVE; HIDDEN nếu gian hàng/danh mục không còn bán được |
| Admin xử lý tin vi phạm | Ẩn (HIDDEN), người bán tự đăng lại được | Gỡ tin (REJECTED) kèm lý do người bán thấy |
| Người bán muốn đưa tin bị gỡ trở lại | `revise` về nháp rồi gửi duyệt | "Yêu cầu mở lại" (REJECTED → PENDING), admin quyết định; `withdraw`/`revise` đã bỏ |
| Nhiều người báo cáo một tin | Chỉ vào hàng chờ | Đủ 3 người khác nhau có báo cáo đang mở thì tin tự tạm ẩn (PENDING), người bán nhận thông báo; admin mở lại hoặc gỡ |
| Gian hàng vi phạm | Admin khoá | Admin khoá (không đổi) |

Chi tiết:
- PENDING giờ có nghĩa "chờ quản trị viên xem xét". Nhãn trên giao diện: "Chờ xem xét", REJECTED là "Bị gỡ".
- Ngưỡng tự ẩn nằm ở `config/moderation.php` (`MODERATION_AUTO_HIDE_REPORTS`, mặc định 3).
- Báo cáo đã bị bác (DISMISSED) không tính vào ngưỡng.
- Tin đang bị phiên đấu giá giữ không tự ẩn, để không cắt ngang người đang trả giá.
- Mọi thay đổi trạng thái vẫn có `expected_status`, lý do, audit log và khoá dòng như trước. Thông báo PRODUCT_HIDDEN gửi sau commit.
- Dữ liệu cũ còn PENDING (gian hàng/tin tạo trước thay đổi) vẫn xử lý được bằng các bước cũ của admin.

Test mới: `ReportAutoHideTest` (3 test). 19 test backend và 7 test frontend mã hoá luồng duyệt cũ đã được viết lại theo luồng mới; không bỏ test nào. `FinalDemoSeeder` (dùng cho E2E) bỏ bước duyệt, tạo tin "chờ xem xét" bằng gỡ rồi xin mở lại.

## Giao diện

- `DetailSheet`: bấm "Xem" mở bảng chi tiết trượt từ phải (toàn màn hình trên điện thoại), đóng bằng nút Đóng, Esc hoặc bấm ra ngoài. Đóng thì bỏ `?inspect=` / `?order=` / `?report=` khỏi địa chỉ. Áp dụng: tin đăng, gian hàng, đơn hàng, đối soát thanh toán, người dùng, đánh giá (admin), báo cáo (admin và người dùng).
- `FilterSheet`: bộ lọc gom thành một "phiếu" thu gọn, ghi số bộ lọc đang áp dụng và từng bộ lọc, bấm để mở form. Áp dụng: các trang admin trên + phiên đấu giá; người bán: đơn bán, tồn kho, đấu giá; công khai: sàn đấu giá, tìm sản phẩm (ô tìm và sắp xếp vẫn hiện sẵn).
- Trang chỉ có một ô tìm kiếm (danh sách gian hàng, Quản lý tin) giữ nguyên ô tìm kiếm hiển thị.
- Hai component render tại chỗ (không teleport) nên mọi nút và ô nhập vẫn thuộc trang đó; test hiện có không cần đổi.

## DB dev

Không có migration.

Ngày 2026-10-15, theo đồng ý của chủ dự án, 7 tin còn PENDING trên DB dev (id 8, 15, 49, 56, 63, 97, 104) được mở bán qua `ProductService::moderate` bằng tài khoản `admin@passlai.local`: mỗi tin được kiểm tra lại điều kiện đăng, có lý do và một dòng audit. Backup trước: `.runtime/open-pending-backup.sql`; so sánh từng dòng (`frontend/e2e/rows.php`): chỉ 7 dòng `products` đổi, thêm 7 dòng `audit_logs`, không xoá dòng nào. Sau đó: 0 tin PENDING, 119 tin ACTIVE.
