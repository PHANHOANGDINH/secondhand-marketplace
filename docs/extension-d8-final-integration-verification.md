# D8 Final Integration — verification

Ngày 2026-10-14, PHP 8.4.25 (Herd). Tài liệu này ghi kết quả tích hợp cuối: seeder demo, E2E theo hành trình A–H, regression và các việc còn mở.

## Kết quả: PASS, còn 1 việc chờ quyết định (seeder trên DB dev)

| Hạng mục | Kết quả |
| --- | --- |
| Backend đầy đủ | 475 test / 5.502 assertions PASS; Pint PASS |
| Chạy lại riêng race test | 21 test PASS (`CheckoutConcurrencyTest` 6 gồm voucher, `AuctionConcurrencyTest` 13, `ReportConcurrencyTest` 2) |
| Frontend | 479 test / 39 file PASS; oxlint, ESLint (không `--fix`), build PASS |
| E2E A–H + voucher | 9/9 PASS, 64 ảnh ở 1440 và 390 px, không tràn ngang, 0 lỗi JS |
| DB dev | 28 bảng, 407 dòng: không dòng nào đổi, mất hay thêm trong suốt D8 (so từng dòng bằng `frontend/e2e/rows.php`) |
| `db:seed` trên DB dev | **CHƯA CHẠY** (xem mục "Seeder") |

## E2E

Stack riêng: PHP `:8001` với `APP_ENV=testing`, Reverb riêng `:8081`, Vite `:5174`, hai Edge headless có profile tách biệt. Dữ liệu là bản sao DB dev nạp vào DB test rồi chạy `FinalDemoSeeder`; mật khẩu tài khoản demo trong bản sao được đặt lại bằng giá trị dùng một lần. Script và cách chạy: [frontend/e2e/README.md](../frontend/e2e/README.md). Ảnh: `docs/ui-redesign/e2e/`.

| | Hành trình | Kết quả | Điều được kiểm |
| --- | --- | --- | --- |
| A | Khách → tìm/lọc → chi tiết → giỏ → voucher → COD | PASS | Khách bị chuyển sang đăng nhập rồi quay lại sản phẩm; `PASSLAI10` giảm 10% chặn ở 50.000; đơn 3.450.000 lưu mã; giỏ rỗng sau khi đặt |
| B | Mua ngay + voucher shop | PASS | Checkout chỉ 1 dòng; `CONGNGHE20K` giảm 20.000; món còn lại vẫn trong giỏ |
| C | Seller đăng bán nhanh → admin duyệt → sửa tồn kho → xử lý đơn | PASS | Tải ảnh thật, PENDING → ACTIVE, tồn kho 3 → 5 vẫn ACTIVE, đơn PENDING → CONFIRMED |
| D | Đấu giá 2 trình duyệt | PASS | Giá cập nhật realtime ở trình duyệt kia không cần tải lại, toast bị vượt giá, kết thúc bằng `auctions:sync`, người thắng thanh toán, người thua không có form |
| E | Video theater + bình luận | PASS | Bình luận hiện ở trình duyệt thứ hai không cần tải lại |
| F | Chat, thông báo, wishlist, báo cáo → admin | PASS | Seller thấy tin nhắn; sản phẩm vào danh sách yêu thích; báo cáo chuyển IN_REVIEW |
| G | Admin Action Center | PASS | Đối soát payment → REFUNDED; hủy phiên LIVE; đình chỉ shop làm shop biến mất khỏi trang công khai |
| H | Tour lần đầu | PASS | Mở sau đăng nhập, focus trong hộp thoại, mở lại ở lượt xem trang sau cho tới khi Bỏ qua, xem lại từ menu, ESC, ghi chú một lần ở phòng đấu giá |
| V | Màn quản lý voucher | PASS | Seller tạo và tắt mã; admin thấy mọi mã, mã của shop chỉ xem |

Kết quả trên là của một lượt chạy liền A→H trên dữ liệu vừa nạp lại; V chạy ngay sau đó.

### Lỗi tìm thấy

- **Ứng dụng:** số tiền trong bảng voucher bị xuống dòng trước ký hiệu ₫. Đã sửa bằng CSS (`VouchersView.vue`). Không tìm thấy lỗi chức năng nào trong 9 hành trình.
- **Chỉ thuộc môi trường E2E, không sửa trong ứng dụng:**
  - Môi trường test dùng session `array`, nên stack phải chạy với `SESSION_DRIVER=file`.
  - URL ảnh lấy theo `APP_URL`; stack phải đặt `APP_URL` về địa chỉ Vite của nó thì ảnh mới hiện.
  - Xóa cookie qua CDP đôi khi chưa có hiệu lực ngay; script đăng nhập xóa từng cookie và thử lại.

### Dọn dẹp

Đã dừng backend, Reverb, Vite và hai Edge của stack; xóa file mật khẩu dùng một lần, 100 file session tạo trong lúc chạy (backend dev lúc đó không chạy) và 8 thư mục ảnh của sản phẩm test. DB test sẽ được dựng lại ở lần chạy test backend kế tiếp.

## Seeder

- `FinalDemoSeeder` thêm cho D5/D7: 1 đơn trực tuyến đã hủy có payment chờ đối soát (ghi bằng dòng mới, không qua giỏ hay tồn kho), 5 lượt xem và 3 lần tìm kiếm cho hai người mua demo, cùng 2 mã voucher đã có từ D6. Dữ liệu D4 (sắp hết hàng, đơn cần xử lý) và phiên LIVE/SCHEDULED để admin hủy đã có sẵn trong seeder. `FinalDemoSeederTest` kiểm cả lần chạy thứ hai không tạo thêm gì.
- **Chưa chạy trên DB dev.** Tôi nạp bản sao DB dev vào DB test và chạy thử seeder ở đó. Kết quả: thêm 54 dòng, không xóa dòng nào, nhưng **sửa 2 dòng cũ**: sản phẩm `C1 Demo 07` (tồn kho 8 → 7) và giỏ hàng của một người mua demo (mốc thời gian). Nguyên nhân là phần D4 của seeder, vốn chưa từng chạy trên DB dev, tạo "đơn cần xử lý" qua luồng checkout thật nên trừ tồn kho. Điều kiện được giao là "không sửa/xóa dòng cũ", nên tôi dừng và chờ quyết định.
- Hệ quả hiện tại trên DB dev: chưa có mã voucher demo, chưa có payment chờ đối soát, chưa có dữ liệu D4 và log gợi ý của seeder.

## Tài liệu đã cập nhật

README (chạy bằng Herd PHP 8.4 và `start-dev.ps1`, tài khoản demo, mã voucher demo, số liệu), `final-erd.md` (bảng D2–D7 và voucher), `final-sequences.md` (checkout có voucher), `demo-script.md` (kịch bản ≤ 10 phút theo A–H), `defense-qa.md` (voucher, gợi ý, thứ tự khóa, `has_video`), `final-release-checklist.md` (giới hạn đã biết, checkpoint), `final-testing-summary.md`, `final-project-index.md`.

`start-dev.ps1` (script khởi động 4 tiến trình do chủ dự án viết, trước đó chưa được theo dõi) được commit cùng tài liệu vì README hướng dẫn chạy bằng nó; script không chứa secret. Các sơ đồ Mermaid mới chưa được render thử lại bằng công cụ.

## Việc còn lại

1. Quyết định về seeder trên DB dev: chấp nhận 2 dòng bị sửa nói trên, hay để tôi đổi phần D4 thành chỉ ghi dòng mới như đã làm với payment đối soát.
2. Backend dev ở cổng 8000 không chạy trong suốt D8; cần khởi động lại bằng `start-dev.ps1`.
3. Thay đổi chưa commit ở `backend/.env.example` đặt một giá trị cho `DEMO_PASSWORD`. Tôi không commit file này; nếu đó là mật khẩu thật thì không nên đưa vào file mẫu.
4. Chưa có: hoàn tất thanh toán ZaloPay thật qua callback công khai, kiểm thử tải, thiết bị thật, kiểm tra contrast tự động, font đóng gói sẵn.
