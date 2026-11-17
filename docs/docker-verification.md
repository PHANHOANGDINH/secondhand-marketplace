# Chạy dev bằng Docker — kiểm chứng

## Yêu cầu
Thay 4 cửa sổ của `start-dev.ps1` (API, Reverb, Scheduler, Vite) bằng Docker.
Quyết định của chủ dự án (2026-11-17): **MySQL giữ trên máy**, không chuyển dữ liệu.

## File
| File | Nội dung |
|---|---|
| `docker-compose.yml` | 4 service: `backend` (`artisan serve` 0.0.0.0:8000, 4 worker), `reverb` (:8080), `scheduler` (`schedule:work`), `frontend` (Node 24, `npm run dev`) |
| `docker/php/Dockerfile` | `php:8.4-cli-bookworm` + bcmath, exif, gd (jpeg/webp/freetype), intl, pcntl, pdo_mysql, zip; nạp `backend/dev/uploads.ini` (50M/60M) |
| `.dockerignore` | Chỉ gửi `backend/dev/uploads.ini` vào build; mã nguồn được bind-mount |
| `frontend/vite.config.js` | `host` lấy từ `VITE_DEV_HOST` (mặc định `localhost` như cũ), polling khi `VITE_USE_POLLING=true` |
| `README.md` | Mục "Docker (alternative to the four windows)" |

Không sửa `backend/.env`, `frontend/.env`, `start-dev.ps1`, `php.ini` hay git config. Cách chạy cũ vẫn dùng được.

## Cách ghép với môi trường hiện có
- Biến môi trường thật thắng `.env`, nên compose chỉ ghi đè: `DB_HOST=host.docker.internal`,
  `REVERB_HOST=reverb` / `REVERB_PORT=8080` / `REVERB_SCHEME=http` (Laravel đẩy broadcast sang container Reverb;
  trình duyệt vẫn nối `127.0.0.1:8080` theo `VITE_REVERB_*`), `VITE_BACKEND_PROXY_TARGET=http://backend:8000`.
- User MySQL `secondhand_app` chỉ được cấp cho `127.0.0.1`; Docker Desktop chuyển kết nối `host.docker.internal`
  qua loopback của máy nên đăng nhập được, **không cần cấp quyền mới**.
- `backend/public/storage` là junction của Windows; Docker Desktop đọc nó thành symlink, và thư mục
  `storage/app/public` được mount thêm nên file đọc được trong container.
- `node_modules` của frontend nằm trong volume riêng (gói native Linux khác Windows); chỉ `npm ci` lại khi
  `package-lock.json` đổi (so SHA-1). `backend/vendor` dùng chung với máy.
- Cổng publish trên `127.0.0.1` (không mở ra mạng LAN).

## Chạy thử (2026-11-17)
Docker 29.5.3 / Compose v5.1.4. Image `passdi-php:8.4` build thành công.
Chạy thử với tên project `passdi-smoke` trên cổng phụ 18000/18080/15173 (không đụng tiến trình đang chạy trên máy,
không bật scheduler để tránh chạy song song với scheduler trên máy), sau đó `down -v`.

| Kiểm tra | Kết quả |
|---|---|
| PHP trong container | 8.4.27; đủ 7 extension; `upload_max_filesize=50M`, `post_max_size=60M` |
| Kết nối MySQL trên máy | `php artisan migrate:status` đọc được, 2 migration mới ở trạng thái Ran |
| API trực tiếp `:18000/api/v1/health` | `{"status":"ok",…}` |
| API qua proxy Vite `:15173/api/v1/health` | `{"status":"ok",…}` |
| Ảnh storage qua Vite `/storage/products/…jpg` | 200, `image/jpeg`, 109 KB |
| Reverb | "Starting server on 0.0.0.0:8080"; backend mở được socket tới `reverb:8080` |
| Vite | sẵn sàng sau ~40 giây lần đầu (gồm `npm ci`) |
| Frontend trên máy sau khi sửa `vite.config.js` | vitest 605/605, eslint/oxlint 0 lỗi, build thành công |

Chưa kiểm: chụp màn hình trang qua Chrome headless (treo do kết nối HMR luôn mở); container `scheduler`
chưa chạy thật (lệnh giống hệt cửa sổ 3 của `start-dev.ps1`).
