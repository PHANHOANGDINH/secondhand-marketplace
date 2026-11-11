# Thiết kế lại Trang chủ và Danh sách sản phẩm: kiểm chứng

## Đã đổi
- **ProductCard** (props không đổi): ảnh 4:5, giá màu `--price`, tên 2 dòng, chip tình trạng, "khu vực · thời gian", dòng người bán + sao (ẩn khi 0 đánh giá), trạng thái Đã bán / Hết hàng, chip "Còn n" (2-3), chỉ 1 nhãn góc ảnh (đấu giá ưu tiên hơn video), chữ tối thiểu 12px, vùng chạm 44px cho link shop và nhãn đấu giá. Điểm sao đổi sang dấu phẩy (4,8).
- **ProductPhoto**: prop `fit` ('contain' mặc định, 'cover') và `eager`; bỏ hack `!important` trong card. Các nơi dùng khác không đổi.
- **Lưới**: `.product-grid` 2/3/4 cột (<768 / >=768 / >=992), gutter từ token. `ProductGridSkeleton` mới.
- **ProductsView**: tiêu đề + số kết quả, thanh tìm/sắp xếp, nút "Bộ lọc" (đếm số lọc) mở sheet có sẵn, chip lọc bỏ từng cái + "Xóa tất cả", cột lọc cố định từ 992px, phân trang giữ nguyên.
- **Trang chủ**: hero có nút "Đăng tin" nổi bật (cùng đích với banner), banner xoay ẩn <768, danh mục gọn trên điện thoại, "Tin mới đăng" (HomeTabs, 8 thẻ trên điện thoại), `HomeAuctions` (khu `.theme-auction`, dải cuộn ngang nhỏ gọn trên điện thoại, gọi `listAuctions({status:'LIVE', per_page:4})`, ẩn khi rỗng/lỗi), dải cuối trang gọn. Tab Đấu giá trong HomeTabs dùng cùng dải gọn (`.auction-rail`).
- **Token**: `--success-soft`, `--warning-soft`, `--warning-ink`, `--shadow-bar` (sáng, tối, .theme-auction) + contrast.mjs (thêm 3 cặp).
- Tài liệu: `docs/design-system.md` mục 16.

## File
Sửa: `frontend/src/components/{ProductCard,ProductPhoto}.vue`, `components/home/{HomeHero,HomeTabs,HomeDiscovery}.vue`, `views/{HomeView,ProductsView}.vue`, `components/layout/MobileTabBar.vue`, `styles/{tokens,ui,auction}.css`, `scripts/contrast.mjs`, `__tests__/{UiCore,ProductDiscovery}.spec.js`, `docs/design-system.md`.
Mới: `components/ui/ProductGridSkeleton.vue`, `components/home/HomeAuctions.vue`.

## Kết quả
| Cổng | Kết quả |
| --- | --- |
| `npx vitest run` | PASS, 538/538 (51 file); 2 test markup thẻ cũ được cập nhật, thêm 3 test mới |
| `npx oxlint .` | PASS, 0 lỗi |
| `npx eslint .` | PASS, 0 lỗi |
| `npm run build` | PASS |
| `node scripts/contrast.mjs` | tất cả "ok" (58 cặp) |

## Ảnh chụp (`.runtime/`, Chrome headless, API giả dựng cục bộ, không đụng DB)
`home-redesign-home-390.png` (cả trang), `-home-390-top|feed|auction.png`, `-home-1440.png`, `-home-360-dark.png`, `-products-390-top.png`, `-products-1440.png`, `-products-360-dark.png`.
Đo tự động: không cuộn ngang ở 360/390/1440; không có chữ <12px trong thẻ; mục chạm đã kiểm.

## Còn mở
- Nhãn "Đã xác minh" chưa làm (API chưa có cờ).
- Tiêu đề "Tin mới đăng" đứng trên tab mặc định "Dành cho bạn" (test hiện hành yêu cầu tab này mở đầu); đổi mặc định sang "Mới nhất" là quyết định sản phẩm.
- Home thêm 1 lời gọi `GET /auctions?status=LIVE&per_page=4` (+ mỗi thẻ đấu giá tự lấy ảnh như cũ).
- Chưa kiểm thủ công trên dữ liệu thật; chưa kiểm bàn phím thật trên thiết bị; các link nhỏ ở header/footer (~20-22px) nằm ngoài phạm vi.
- Trang /auctions, shop, wishlist vẫn dùng lưới Bootstrap cũ (thẻ vẫn hiển thị đúng).
