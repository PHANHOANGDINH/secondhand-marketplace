# Quên mật khẩu bằng mật khẩu tạm thời — verification

Ngày 2026-10-15, PHP 8.4.25 (Herd). Chủ dự án chọn: "Quên mật khẩu" gửi qua mail một mật khẩu tạm, đăng nhập bằng nó thì phải đổi mật khẩu ngay.

## Kết quả: PASS

| Hạng mục | Kết quả |
| --- | --- |
| Backend đầy đủ | 494 test / 6.480 assertions PASS (DB test); Pint PASS |
| Frontend | 510 test / 48 file PASS; oxlint, ESLint (không `--fix`), build PASS |
| E2E (DB test) | P1–P2 (`frontend/e2e/password.mjs`) PASS; M1–M3 PASS, 0 lỗi JS |

## Cách hoạt động

- `POST /api/v1/auth/forgot-password`: nếu email là tài khoản đang hoạt động, tạo mật khẩu tạm ngẫu nhiên 12 ký tự (chữ + số, bỏ ký tự dễ nhầm), lưu **chỉ dạng hash** trong `users.temporary_password` với hạn `temporary_password_expires_at` (mặc định 60 phút, `TEMPORARY_PASSWORD_MINUTES`), rồi gửi mail tiếng Việt `TemporaryPasswordNotification`. Câu trả lời luôn giống nhau dù email có tồn tại hay không; lỗi gửi mail chỉ ghi loại lỗi vào log.
- **Mật khẩu cũ vẫn dùng được.** Người chỉ biết email không thể khoá chủ tài khoản. Đăng nhập bằng mật khẩu cũ sẽ huỷ mật khẩu tạm đang chờ.
- `POST /api/v1/auth/login`: mật khẩu thường sai thì thử mật khẩu tạm còn hạn. Dùng được thì mật khẩu tạm bị xoá ngay (một lần), `must_change_password = true`, có audit `user.temporary_password_used`.
- `PATCH /api/v1/auth/password` (mới): đổi mật khẩu khi đã đăng nhập; cần mật khẩu hiện tại, trừ khi vừa dùng mật khẩu tạm. Cùng quy tắc như đăng ký (≥ 12 ký tự, có chữ và số, ≤ 72 byte), không trùng mật khẩu cũ; xoá cờ, xoay remember token, audit `user.password_changed`.
- Frontend: trang `/account/password` "Đổi mật khẩu" (có trong menu tài khoản). Khi `must_change_password` bật, mọi trang chuyển về trang này, đổi xong quay lại trang định vào.
- Liên kết đặt lại mật khẩu cũ (`/reset-password`) vẫn hoạt động cho các liên kết đã gửi trước đây.

## Gửi mail thật

`MAIL_MAILER=log` (hiện tại) chỉ ghi mail vào `backend/storage/logs/laravel.log`, **kèm mật khẩu tạm**: chỉ dùng khi phát triển. Để gửi tới hộp thư thật, đặt SMTP (ví dụ Gmail + mật khẩu ứng dụng) trong `backend/.env`, chạy `php artisan config:clear` và khởi động lại backend.

## DB dev

Migration `2026_10_15_120000_add_temporary_password_to_users` chỉ thêm 3 cột, `down()` xoá cột (đã thử rollback/migrate lại trên DB test). Trước khi migrate: `.runtime/temp-password-backup.sql`; so sánh từng dòng: chỉ bảng `users` đổi hash vì có thêm cột (giá trị NULL / false), không thêm hay xoá dòng nào.

## Sửa thêm

Banner trang chủ: rời trang đúng lúc banner đang chuyển tin gây `TypeError` (E2E bắt được). Đã chặn và thêm test tái hiện.
