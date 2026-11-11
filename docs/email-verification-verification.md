# Xác minh email khi đăng ký: kiểm chứng

Ngày 2026-11-03. Chưa commit. Có 1 migration thêm cột (mã xác minh); không tạo hay sửa dòng dữ liệu nào trong DB dev.

## Quyết định từ chủ dự án
Chưa xác minh thì không đăng nhập được · miễn cho tài khoản cũ · xác minh bằng link trong thư hoặc nhập mã trên trang (bổ sung sau) · gửi 1 thư thật để thử.

## Hành vi
- **Đăng ký:** tạo tài khoản, không mở phiên, gửi thư, chuyển tới `/verify-email`. Gửi thư lỗi không làm mất tài khoản; người dùng bấm gửi lại.
- **Đăng nhập khi chưa xác minh:** đúng mật khẩu thì trả 403 `EMAIL_NOT_VERIFIED`, không tạo phiên hay cookie ghi nhớ. Sai mật khẩu vẫn trả lỗi như cũ, nên không lộ trạng thái tài khoản.
- **Link:** có chữ ký, hết hạn sau 60 phút, bấm lại vẫn báo đã xác minh. Trang frontend mới POST lên API, nên bộ quét link trong hộp thư không tự xác minh được. Ghi audit `user.email_verified`. Không tự đăng nhập sau khi xác minh.
- **Mã 6 số:** cùng thư với link; chỉ lưu dạng băm; hết hạn sau 15 phút; sai 5 lần thì mã hỏng. Mỗi email tối đa 5 lần thử/phút và 20 lần/giờ dù đổi IP hay gửi mã mới. Sai mã, hết hạn và tài khoản không tồn tại trả lời giống nhau. Gửi mã mới làm mã cũ mất hiệu lực.
- **Gửi lại:** câu trả lời giống nhau dù tài khoản có tồn tại hay không; giới hạn 3 lần/phút theo email + IP.
- **Tài khoản cũ:** tài khoản tạo trước `EMAIL_VERIFICATION_REQUIRED_FROM` (mặc định `2026-11-02 00:00:00` UTC) được miễn. 30 tài khoản hiện có không bị sửa dữ liệu.

## Quyết định người làm tự chọn, cần chủ dự án biết
- **Mật khẩu tạm từ "Quên mật khẩu" cũng tính là đã xác minh:** mật khẩu này chỉ gửi tới hộp thư đó. Nhờ vậy chủ thật của email vẫn lấy lại được địa chỉ nếu người khác đăng ký trước bằng email của họ.
- **Seeder demo:** 4 seeder giờ đặt `email_verified_at`, để tài khoản demo vẫn đăng nhập được trên DB seed mới. Không chạy seeder nào.
- **Khi triển khai thật:** cần đặt `EMAIL_VERIFICATION_URL` và kiểm tra lại `EMAIL_VERIFICATION_REQUIRED_FROM`.

## File
Mới: migration `2026_11_03_000000_add_email_verification_code_to_users`, `VerifyEmailCodeRequest`, `EmailVerificationController`, `EmailVerificationService`, `VerifyEmailNotification`, `EmailVerificationTest`, `VerifyEmailView.vue`, `VerifyEmail.spec.js`.
Sửa backend: `AuthController`, `User`, `TemporaryPasswordService`, `AppServiceProvider`, `config/auth.php`, `routes/api.php`, 4 seeder, `AuthTest`.
Sửa frontend: `auth.api.js`, `stores/auth.js`, `AuthForm.vue`, `router/index.js`, `config/brand.js`, `DefaultLayout.vue`, `auth.spec.js`, `AuthForm.spec.js`.

## Kết quả
| Cổng | Kết quả |
| --- | --- |
| Backend test (`secondhand_marketplace_test`) | PASS, 534/534 |
| Migration trên DB dev | Sao lưu `.runtime/email-code-backup.sql`; thử rollback trên DB test; 37 bảng giữ nguyên số dòng và hash (bỏ qua 3 cột mới) |
| `pint --test` trên các file đã sửa | PASS |
| `npx vitest run` | PASS, 558/558 (53 file) |
| `npx oxlint .`, `npx eslint .` | PASS, 0 lỗi |
| `npm run build` | PASS |
| `node scripts/contrast.mjs` | PASS, 58/58 cặp |
| Chạy thật trên trình duyệt (UI + API thật, DB test, qua Vite proxy) | PASS, 12/12 bước gồm link và mã |
| Tràn ngang `/verify-email` ở 360 px | PASS (đo sau khi hiệu ứng vào kết thúc) |
| Gửi thư thật qua Gmail SMTP | SMTP nhận thư (bản chưa có mã); chưa xác nhận đã vào hộp thư |

Số người dùng trong DB dev trước và sau khi kiểm tra: 30.

## Chưa kiểm
- Bấm link hoặc nhập mã từ thư thật. Thư thử đã gửi là bản chưa có mã và dùng link mẫu, nên sẽ báo "Liên kết không dùng được"; đó là đúng.
- Đăng ký thật trên DB dev: chưa làm để không tạo dữ liệu rác.
- Thử trên điện thoại thật.

## Giao diện
Bản "Tem giá" đã được hoàn tác theo yêu cầu. Giao diện về đúng bản sao lưu `.runtime/pre-redesign-backup-20261103/`, chỉ thêm `/verify-email` vào danh sách trang xác thực trong `DefaultLayout.vue`. Bản "Tem giá" và canvas được cất ở `.runtime/tem-gia-backup-20261103/`.
