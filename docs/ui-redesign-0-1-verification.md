# UI redesign đợt 0+1 — verification

Ngày 2026-10-13. Chỉ đổi phần nhìn: không đổi logic, API, route, store, realtime, nhãn tiếng Việt. Backend không bị chạm nên không chạy lại suite backend.

Đợt này có hai commit. `53db5c0` làm theo đề đầu (có lớp motion bằng GSAP). Commit tiếp theo làm theo đề đã sửa: gỡ toàn bộ lớp GSAP đó, chỉ dùng transition CSS ≤ 150 ms, thêm hàng danh mục và thẻ sản phẩm dạng tin đăng. Tài liệu này mô tả trạng thái sau commit thứ hai.

## Kết quả: PASS

| Hạng mục | Kết quả |
| --- | --- |
| vitest | 462 test / 38 file PASS (trước redesign: 456 / 37) |
| oxlint, ESLint (không `--fix`), build | PASS |
| Trình duyệt (Edge headless, khách, chỉ đọc, trên dev server đang chạy) | Trang chủ 1440 và 390 px: không tràn ngang, 0 lỗi JS, font đã nạp, header sticky nền trắng, mọi nút header cao ≥ 44 px, hàng danh mục có 5 mục |
| Ảnh chụp | `docs/screenshots/ui-redesign-1/` (`home-{1440,390}-{top,recommendations}.png`) |

## Tokens

- `frontend/src/styles/tokens.css`: màu light, `.theme-auction`, font, radius 10/12/16/24, thang spacing 4 px, `--transition-fast: 0.15s`; ánh xạ sang `--bs-*` (primary, danger, success `#12805C`, nền, viền, chữ, radius, font), nên Bootstrap utility và các rule cũ đọc `--bs-*` tự đổi theo. Nạp trong `main.js` ngay sau Bootstrap.
- `frontend/src/styles/core.css`: skin dùng chung cho Button, Input, Select, Card, StatusBadge, EmptyState, Skeleton, Dialog, PageHeader, Pager. Ưu tiên viền, bỏ bóng đổ ở thẻ.
- JetBrains Mono chỉ còn dùng cho giá đấu giá và đếm ngược; giá sản phẩm thường dùng Be Vietnam Pro 800.
- Font nạp qua Google Fonts trong `index.html`; không có mạng thì rơi về font hệ thống.

## Header

- Sticky, nền trắng, viền dưới. `BrandLogo.vue` tạm: ô primary bo 10 px, icon thẻ giá viền accent, chữ "pass lại" 800.
- Ô tìm kiếm nền surface-2; gợi ý và debounce giữ nguyên (không sửa `ProductSearch.vue`).
- Icon tin nhắn / thông báo / giỏ 44 px có badge; nút "Đăng bán nhanh" nền primary.
- Hàng danh mục ngang ngay dưới header: "Tất cả" + danh mục gốc từ `GET /categories` (endpoint có sẵn), mỗi mục dẫn tới `/products?category={id}`. Cuộn ngang trên màn hẹp, không sticky, ẩn nếu tải lỗi và ẩn ở khu Seller/Admin.

## ProductCard dạng tin đăng

- Ảnh 1:1 bo 16 px; góc ảnh có nút tim tròn 44 px và nhãn đấu giá (vẫn là link "… · Xem phiên" như cũ, chỉ đổi vị trí).
- Tên 2 dòng, giá 800, `reason_label` màu primary khi có.
- Một dòng muted: tình trạng · gian hàng (vẫn là link) · "x giờ trước" tính từ `published_at` bằng `timeAgo()` mới trong `productPresentation.js`.
- `AppIcon.vue`: icon SVG nét mảnh thay các ký tự `↗ ＋ ⌄ ◇ ♥ ♡ ★` trong header, EmptyState, FavoriteButton, ProductCard.

## Motion

- Không còn mã GSAP nào do redesign thêm: `useMotion.js` và `Motion.spec.js` đã xóa; `AccessibleDialog`, `AuctionCountdown`, `AuctionArenaView`, `VideoDiscovery.spec.js` trở lại đúng nội dung trước redesign.
- Phần mới chỉ dùng transition CSS 150 ms (màu, viền, `transform: scale(0.98)` khi nhấn nút) và tắt khi `prefers-reduced-motion: reduce`.
- GSAP chỉ còn ở `HomeScrollStory.vue`, có từ trước và không bị sửa.

## Test

- Mới: `UiCore.spec.js` (6 test): `timeAgo`, dòng meta và nhãn đấu giá của thẻ, logo/icon/EmptyState không dùng emoji, hàng danh mục (có dữ liệu, lỗi tải, khu Seller/Admin).
- Không test cũ nào phải sửa trong trạng thái cuối; không selector nào test đang dùng bị đổi.

## Lệch so với đề bài, cần biết

- **Nhãn video ở góc ảnh chưa có.** API sản phẩm hiện không trả trường nào cho biết sản phẩm có video, và đề yêu cầu không thêm field. Chỉ có nhãn đấu giá.
- **Tên thương hiệu** mới đổi ở logo header; `<title>`, eyebrow "Secondhand · Sàn mua bán" và thương hiệu khu Seller/Admin chưa đổi.
- **Danger `#E5243B`** chỉ đạt 4,5:1 trên nền trắng tinh; chữ đỏ trên nền khác dùng `--danger-ink #B3172B`.
- **Ảnh trong thẻ** chuyển từ `contain` sang `cover` để lấp đầy ô 1:1, nên ảnh không vuông bị cắt mép. Các nơi khác dùng `ProductPhoto` không đổi.
- **Xếp hạng sao** vẫn hiện thành một dòng nhỏ dưới dòng meta khi sản phẩm có đánh giá; đề không nhắc tới nên tôi giữ.
- `.theme-auction` mới chỉ được định nghĩa, chưa áp vào trang nào.
- Các file CSS cũ (`buyer.css`, `seller.css`, `admin.css`, `auction.css`) còn transition/animation dài hơn 150 ms; chưa rà vì ngoài phạm vi đợt này.

## Chưa làm

- Chỉ chụp và kiểm tra trang chủ với tư cách khách. Các trang khác nhận token và skin mới nhưng chưa được xem lại bằng mắt.
- Chưa đo contrast tự động cho toàn bộ tổ hợp màu; mới kiểm tay các cặp token chính.
- Các ký tự `★ ＋` còn lại ở `AdminRating`, `ReviewForm`, `ProductImagesEditor` và Seller/Admin shell thuộc đợt sau.
