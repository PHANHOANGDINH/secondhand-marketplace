# UI redesign đợt 0+1 — verification

Ngày 2026-10-13. Chỉ đổi phần nhìn: không đổi logic, API, route, store, realtime, nhãn tiếng Việt. Backend không bị chạm nên không chạy lại suite backend.

## Kết quả: PASS

| Hạng mục | Kết quả |
| --- | --- |
| vitest | 462 test / 38 file PASS (trước: 456 / 37) |
| oxlint, ESLint (không `--fix`), build | PASS |
| Trình duyệt (Edge headless, khách, chỉ đọc, trên dev server đang chạy) | Trang chủ 1440 và 390 px: không tràn ngang, 0 lỗi JS, font Be Vietnam Pro đã nạp, header sticky nền trắng, mọi nút header cao ≥ 44 px |
| Ảnh chụp | `docs/screenshots/ui-redesign-1/` (4 file: `home-{1440,390}-{top,recommendations}.png`) |

## Đợt 0 — nền tảng

- `frontend/src/styles/tokens.css`: toàn bộ token (màu light, `.theme-auction`, font, radius 10/12/16/24, thang spacing 4 px) và ánh xạ sang biến `--bs-*`, nên Bootstrap utility và các rule cũ đang đọc `--bs-*` tự đổi màu. Nạp trong `main.js` ngay sau Bootstrap.
- `frontend/src/styles/core.css`: skin dùng chung cho Button, Input, Card, StatusBadge, EmptyState, Skeleton, Dialog, PageHeader, Pager. Ưu tiên viền, bỏ bóng đổ ở thẻ.
- `frontend/src/composables/useMotion.js`: preset `fadeUp`, `pressScale`, `flashValue`, `countPulse`, `dialogIn` và `fadeOutCopy`.
  - Chỉ chạy khi `prefers-reduced-motion: no-preference`, qua `gsap.matchMedia`; không có `matchMedia` thì coi như không animate.
  - Mỗi preset nằm trong `gsap.context()` và được `revert()` ở `onUnmounted`.
  - Chỉ animate `opacity`/transform, 0,2–0,4 s. GSAP được nạp nền; chưa nạp xong thì nội dung hiện ngay.
- Font nạp qua Google Fonts trong `index.html`; máy không có mạng sẽ dùng font hệ thống.

## Đợt 1 — thành phần

- Header: `BrandLogo.vue` (ô primary bo 10 px, icon thẻ giá viền accent, chữ "pass lại" 800), ô tìm kiếm nền surface-2, icon thông báo/tin nhắn/giỏ 44 px có badge, nút "Đăng bán nhanh" nền primary.
- `ProductCard`: ảnh 1:1 bo 16 px, chip tình trạng, nút tim tròn 44 px, `reason_label` màu primary, giá đậm 800 bằng JetBrains Mono.
- `AppIcon.vue`: icon SVG nét mảnh thay các ký tự `↗ ＋ ⌄ ◇ ♥ ♡ ★` trong header, EmptyState, FavoriteButton, ProductCard.
- Motion đã gắn: nhấn nút (toàn trang, một listener ủy quyền), lưới gợi ý sản phẩm (`fadeUp`, ScrollTrigger `once`), mở/đóng `AccessibleDialog`, giá hiện tại ở trang đấu giá (`flashValue`), đồng hồ ở những giây cuối (`countPulse`).
- `HomeScrollStory`: không sửa file; màu đổi theo token vì nó đọc `--bs-*`.

## Test

- Mới: `Motion.spec.js` (6 test, mock `gsap`): không animate khi reduced-motion hoặc thiếu `matchMedia`; preset chỉ dùng transform/opacity trong 0,2–0,6 s; `revert` khi unmount; nhấn nút không chặn click; bản sao khi đóng dialog là inert và không có `id`/`role`.
- Sửa 1 test cũ: `VideoDiscovery.spec.js` mock `useMotion`, vì test đó đếm mọi lần gọi `matchMedia` và lớp motion mới cũng gọi `matchMedia`. Hành vi component không đổi.
- Không selector nào test đang dùng bị đổi.

## Lệch so với đề bài, cần biết

- **Đóng dialog**: dialog vẫn đóng ngay lập tức như cũ; hiệu ứng đóng chạy trên một bản sao inert, không nhận click, tự xóa sau 0,2 s. Làm vậy để animation không trì hoãn sự kiện `dismiss`.
- **Tên thương hiệu** trên header đổi thành "pass lại" theo đề; tiêu đề trang, `<title>`, eyebrow "Secondhand · Sàn mua bán" và thương hiệu trong khu Seller/Admin chưa đổi.
- **Danger `#E5243B`** chỉ đạt 4,5:1 trên nền trắng tinh; chữ đỏ trên nền khác dùng `--danger-ink #B3172B`.
- **Ảnh trong thẻ sản phẩm** chuyển từ `contain` sang `cover` để lấp đầy ô 1:1, nên ảnh không vuông bị cắt mép. Các nơi khác dùng `ProductPhoto` không đổi.
- `.theme-auction` mới chỉ được định nghĩa, chưa áp vào trang nào.

## Chưa làm

- Chỉ chụp và kiểm tra trang chủ với tư cách khách. Các trang khác (sản phẩm, giỏ, đơn, Seller, Admin, đấu giá) nhận token và skin mới nhưng chưa được xem lại bằng mắt.
- `fadeUp` mới gắn cho khu gợi ý/tương tự; lưới ở `/products`, gian hàng, yêu thích chưa gắn.
- Seller/Admin shell, trang đấu giá và các ký tự `★ ＋` còn lại ở `AdminRating`, `ReviewForm`, `ProductImagesEditor` thuộc đợt sau.
- Chưa đo contrast tự động cho toàn bộ tổ hợp màu; mới kiểm tay các cặp token chính.
