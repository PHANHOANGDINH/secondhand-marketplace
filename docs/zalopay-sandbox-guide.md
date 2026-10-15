# ZaloPay sandbox — thanh toán thật đầu-cuối (callback qua tunnel)

Áp dụng cho môi trường dev (backend `php artisan serve` ở cổng 8000, frontend Vite ở 5173). Không gọi hoàn tiền; không in hay commit key.

## 1. Cấu hình một lần (`backend/.env`, tự nhập, không dán key vào chat)

| Biến | Giá trị |
| --- | --- |
| `ZALOPAY_APP_ID`, `ZALOPAY_KEY1`, `ZALOPAY_KEY2` | bộ khoá **sandbox** của merchant (trang quản lý ZaloPay hoặc bộ demo trong tài liệu ZaloPay) |
| `ZALOPAY_CREATE_URL`, `ZALOPAY_QUERY_URL` | để mặc định `https://sb-openapi.zalopay.vn/v2/create` / `/v2/query` |
| `ZALOPAY_REDIRECT_URL` | để trống = `<FRONTEND_URLS đầu tiên>/payment/result` |
| `ZALOPAY_CALLBACK_URL` | đặt theo bước 2 mỗi buổi demo (để trống = `APP_URL/api/v1/payments/zalopay/callback`, máy ZaloPay không gọi tới được localhost) |

## 2. Mở tunnel cho callback

1. Chạy backend + frontend như thường (`start-dev.ps1`).
2. Cửa sổ mới: `powershell -ExecutionPolicy Bypass -File scripts\zalopay-tunnel.ps1` (cổng khác: `-Port 8001`). Cần `cloudflared` (`winget install --id Cloudflare.cloudflared`).
3. Script in ra `https://<tên-ngẫu-nhiên>.trycloudflare.com` và dòng cần đặt:
   `ZALOPAY_CALLBACK_URL=https://<tên>.trycloudflare.com/api/v1/payments/zalopay/callback`
4. Sửa `.env`, chạy `php artisan config:clear`, khởi động lại backend. Kiểm tra `https://<tên>.trycloudflare.com/up` trả 200.
5. Giữ cửa sổ tunnel mở suốt buổi demo.

## 3. Thẻ test

Dùng thẻ và mã OTP do ZaloPay công bố cho **sandbox** (tài liệu ZaloPay → môi trường Sandbox → thông tin thẻ test; có thẻ ATM nội địa và thẻ quốc tế). Cổng sandbox không trừ tiền thật. Ví ZaloPay sandbox cần ứng dụng ZaloPay bản sandbox nên demo bằng thẻ là dễ nhất. `bank_code` gửi rỗng nên cổng cho chọn phương thức; nếu chọn kênh ở trang đặt hàng thì chỉ kênh đó hiện (`preferred_payment_method`).

## 4. Kịch bản demo (~3 phút)

1. Người mua thêm món vào giỏ → Đặt hàng → chọn **ZaloPay** (có thể áp mã giảm giá: số tiền gửi sang ZaloPay là tổng **sau** voucher, do máy chủ tính).
2. Bấm Xác nhận → chuyển sang cổng sandbox → chọn thẻ test → nhập OTP.
3. ZaloPay gọi callback qua tunnel (cửa sổ backend/`storage/logs/laravel.log` có dòng `zalopay.callback` với `result: 1`, chỉ 8 ký tự đầu của MAC).
4. Cổng chuyển về `/payment/result?order=…`: trang hiện "Đang xác nhận thanh toán…", hỏi máy chủ mỗi 3 giây (máy chủ tự query ZaloPay khi còn PENDING) và ra **Đã thanh toán**. Tham số trên URL không được tin.
5. Mở Đơn mua: trạng thái Đã thanh toán, không còn nút thanh toán lại. Người bán thấy đơn ở Đơn bán.
6. Thử thêm: huỷ ở cổng → trang kết quả ra **Thanh toán không thành công** (sau khi ZaloPay báo hết hạn) hoặc **Đang chờ** với nút **Kiểm tra lại**.

## 5. Callback: hợp đồng đã kiểm

- `POST /api/v1/payments/zalopay/callback`: công khai, không CSRF, không đăng nhập, có throttle.
- Kiểm `HMAC-SHA256(data, key2)` bằng `hash_equals`; đối chiếu app_id, app_trans_id, app_user, app_time, **amount** với payment; xử lý trong transaction và khoá đơn → payment.
- Luôn HTTP 200: `1` ghi nhận, `2` đã xử lý trước đó (cùng `zp_trans_id`), `-1` MAC sai / dữ liệu sai / sai số tiền / không tìm thấy.
- Tiền về cho đơn đã huỷ hoặc lần thanh toán đã kết thúc: không đổi trạng thái, đánh dấu **cần đối soát** (trang Đối soát thanh toán của quản trị).

## 6. Giới hạn

- Quick tunnel đổi URL **mỗi lần chạy**: phải đặt lại `ZALOPAY_CALLBACK_URL` và `config:clear`. Đóng tunnel thì callback không tới được.
- ZaloPay **không gửi lại** callback khi lỗi, nên đừng tin riêng callback: trang kết quả và nút "Kiểm tra lại" query trực tiếp, và lệnh nền `php artisan zalopay:reconcile` (đã lên lịch) đối soát các giao dịch còn treo.
- Chỉ số tiền nguyên VND; giá có phần lẻ phải chọn COD.
- Sandbox chặn trong môi trường production (máy chủ từ chối `sb-openapi` khi `APP_ENV=production`).

## 7. Đã kiểm chứng (2026-10-16)

Qua tunnel Cloudflare thật tới stack DB test (key tạm, không phải key của bạn): `/up` 200; callback hợp lệ → `1` (payment PAID), gửi lại → `2`, sai số tiền → `-1`, MAC sai → `-1`, body sai → `-1`, tất cả HTTP 200; log chỉ có tiền tố MAC. Bước 3–4 (trả bằng thẻ trên cổng sandbox) cần bộ khoá sandbox của bạn và thao tác tay.
