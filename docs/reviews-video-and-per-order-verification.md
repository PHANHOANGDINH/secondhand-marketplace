# Đánh giá: video cho đánh giá sản phẩm, mỗi đơn hoàn tất một đánh giá gian hàng — kiểm chứng

## Quyết định của chủ sản phẩm (2026-11-17)
- Video chỉ cho **đánh giá sản phẩm**, tối đa **1 video ≤ 50 MB** (MP4/WebM), không bắt buộc.
- Đánh giá gian hàng: **một đánh giá cho mỗi đơn hoàn tất** (trước: một người một đánh giá cho mỗi shop).
  Đồng ý bỏ ràng buộc unique `(shop_id, user_id)` — migration không thuần thêm, xem phần Rollback.
- Kèm theo: sửa thanh tìm kiếm trang `/products` trên desktop (nút tải lại bị rơi xuống dòng dưới).

## Video đánh giá sản phẩm
- Cột mới (nullable) trên `reviews`: `video_path`, `video_mime_type`, `video_size`.
- `POST /api/v1/reviews/{id}/video` (multipart `video`, throttle `upload`) gắn hoặc thay video;
  `DELETE /api/v1/reviews/{id}/video` gỡ video. Chỉ tác giả (policy `update` của đánh giá).
- Kiểm tra: `mimetypes:video/mp4,video/webm`, đuôi `mp4|webm`, `max` theo `config/review-videos.php`
  (`REVIEW_VIDEO_MAX_KB`, mặc định 51200), rồi đọc chữ ký file (MP4 `ftyp`, WebM EBML + `webm`) và đuôi phải khớp,
  như video sản phẩm. Lưu ở disk `public`: `review-videos/{review_id}/{uuid}.{ext}`.
- Gắn video đi qua `ReviewService::save()` (cùng thứ tự khóa: tài khoản → order → shop_order → shop → product
  → order_item → review). Lỗi trong transaction thì xóa file vừa lưu; file cũ chỉ bị xóa **sau commit**.
- `ReviewResource` và `AdminReviewResource` trả `video: {url, mime_type, size} | null`. Đánh giá bị ẩn không còn
  trong danh sách công khai nên video cũng không hiện; admin vẫn xem được trong màn kiểm duyệt.
- Giao diện: `ReviewForm` có ô chọn video (chặn sai loại / > 50 MB trước khi gửi), xem video hiện có, ô
  "Xóa video này". Lưu đánh giá trước, rồi tải video; nếu tải lỗi, lần thử lại sửa đánh giá đã lưu thay vì tạo
  đánh giá thứ hai. Video hiện ở danh sách đánh giá công khai, phần "Đánh giá từ đơn đã nhận" và màn admin.
- Giới hạn PHP: CLI của Herd đang là `upload_max_filesize=2M`, `post_max_size=8M`. Để tải video 50 MB cần chạy
  backend bằng `backend/serve.ps1` (nạp `backend/dev/uploads.ini`) như README hướng dẫn cho video sản phẩm.

## Đánh giá gian hàng: mỗi đơn hoàn tất một lần
- Cột mới `shop_reviews.shop_order_id` (FK `shop_orders`, unique, nullable cho dữ liệu cũ); thay unique
  `(shop_id, user_id)` bằng index thường.
- Migration gắn mỗi đánh giá cũ vào đơn hoàn tất mới nhất của người đó ở shop (DB dev: 1/1 đánh giá đã gắn).
- API: `POST /shops/{slug}/reviews` đánh giá **đơn hoàn tất mới nhất chưa được đánh giá** (201); hết đơn → 409
  "Bạn đã đánh giá mọi đơn hoàn tất ở gian hàng này."; chưa có đơn hoàn tất → 403 như cũ.
  `PATCH /shop-reviews/{id}` sửa (chỉ tác giả, shop phải đang công khai, giữ trạng thái kiểm duyệt).
  `GET .../reviews/mine` trả `can_review`, `pending_count`, `reviews[]` (mọi trạng thái). Bỏ `PUT`.
- Khóa: tài khoản → shop → shop_order (`lockForUpdate`) → review; unique `shop_order_id` chặn trùng khi chạy song song.
- Giao diện: nút "Đánh giá gian hàng" khi còn đơn chưa đánh giá, dòng nhắc "Bạn còn N đơn hoàn tất chưa đánh giá.",
  nút "Sửa" trên từng đánh giá của mình.

## Migration trên DB dev
| Bước | Kết quả |
|---|---|
| Sao lưu | `.runtime/reviews-backup.sql` ("Dump completed") |
| Snapshot trước/sau | `.runtime/reviews-snapshot-{before,after}.json`: 38/38 bảng, số dòng và hash không đổi (bỏ qua 4 cột mới); `migrations` 37 → 39 |
| Rollback | Trên `secondhand_marketplace_test`: `--step=2` xóa 4 cột, khôi phục unique; migrate lại thành công |

`down()` của migration đánh giá gian hàng **dừng có báo lỗi** nếu đã có người có nhiều đánh giá cho cùng một shop,
vì khôi phục unique khi đó buộc phải xóa dữ liệu; cần quyết định giữ đánh giá nào trước khi rollback.

## Test
- `ReviewVideoTest` (4): gắn / thay (xóa file cũ) / sửa chữ giữ video / gỡ; chỉ tác giả; chặn file giả,
  đuôi sai, WebM đặt tên .mp4, ảnh, thiếu file, quá cỡ — không để lại file; ẩn đánh giá ẩn video, admin vẫn thấy.
- `ShopReviewTest` (viết lại, 6): đánh giá từng đơn, đơn mới nhất trước, 409 khi hết, sửa từng cái, đơn mới mở
  thêm lượt; chỉ tác giả sửa; shop bị đình chỉ → 404; giữ trạng thái ẩn khi sửa; các ca không đủ điều kiện như cũ.
- Frontend: `ReviewVideo.spec.js` (6), `ShopReviews.spec.js` (cập nhật, 7).

## Kiểm chứng
| Lệnh | Kết quả |
|---|---|
| `php artisan test` (DB test) | 565/565 đạt, 7057 assertion |
| `pint --test` | đạt (sau khi định dạng lại `ShopReviewTest.php`: thiếu dòng trống giữa method) |
| `vitest run` | 605/605 đạt |
| `oxlint src` / `eslint` (file đã sửa, không `--fix`) | 0 lỗi / 0 lỗi |
| `npm run build` | thành công |
| Thanh tìm kiếm `/products` 1440px (Chrome headless) | một hàng: ô tìm, Tìm kiếm, Sắp xếp, nút tải lại |
