# Passdi — Đặc tả hệ thống thiết kế (chi tiết component)

> **Quan hệ với `DESIGN.md`:** `DESIGN.md` ở thư mục gốc vẫn là bản tổng quan thương hiệu (hướng "Đời thứ hai", màu, font, nguyên tắc). File này là **đặc tả chi tiết cho component và màn hình**. Hai file phải luôn nhất quán: đổi giá trị token thì sửa `frontend/src/styles/tokens.css` rồi cập nhật cả hai; nếu mâu thuẫn, `tokens.css` là nguồn sự thật về giá trị, `DESIGN.md` là nguồn sự thật về hướng thương hiệu.
>
> **Quy ước:** tên token, class, thuộc tính viết tiếng Anh. Mục gắn nhãn **(đề xuất)** chưa có trong code. Mọi cặp màu có tỉ lệ tương phản đã tính bằng công thức WCAG 2.x; các cặp đã có trong code được kiểm bởi `node frontend/scripts/contrast.mjs`, nên thêm các cặp đề xuất vào script đó khi triển khai.

## 1. Nguyên tắc

1. **Ảnh là nhân vật chính.** Đồ cũ được mua vì ảnh thật. Ảnh chiếm phần lớn diện tích thẻ và trang chi tiết; chữ và màu phục vụ ảnh, không cạnh tranh.
2. **Tin cậy trước, trang trí sau.** Tình trạng món đồ, người bán, cách thanh toán an toàn luôn nhìn thấy mà không phải cuộn hay mở menu.
3. **Đăng tin dễ như chụp ảnh.** Đường vào "Đăng tin" luôn có mặt; form đăng chia bước, ảnh đứng đầu.
4. **Một hành động chính mỗi màn hình**, luôn trong tầm ngón cái trên điện thoại.
5. **Ấm áp nhưng điềm tĩnh.** Xanh rừng/bạc hà làm nền, logo cũng màu xanh. Giá dùng màu mực (không đỏ cả bảng giá); đỏ chỉ cho giảm giá và lỗi.
6. **Mobile-first từ 360 px**, không cuộn ngang, mục tiêu chạm ≥ 44 px.
7. **Không dựa vào màu đơn thuần**: trạng thái luôn có chữ (và icon nếu cần).
8. **Dùng token, không dùng hex** trong component (ngoại lệ: logo SVG, lớp phủ trắng trên ảnh).

## 2. Màu

Bộ màu giữ nguyên "Đời thứ hai". Giá trị lấy từ `tokens.css`.

### 2.1 Nền, chữ, viền

| Token | Sáng | Tối | Vai trò |
| --- | --- | --- | --- |
| `--bg` | #F3F6F4 | #0B1714 | nền trang |
| `--surface` | #FFFFFF | #11211D | thẻ, panel |
| `--surface-2` | #E8EFEB | #172C27 | panel phụ, ô trống ảnh, input nghỉ |
| `--surface-skeleton` | #E1EAE5 | #1B332D | skeleton |
| `--border` | #D5E0DB | #234039 | đường phân cấu trúc (trang trí, không dùng làm viền input) |
| `--text` | #12302A | #E3F1EC | chữ chính |
| `--muted` | #4A615B | #9CB9AF | chữ phụ |
| `--field-border` **(đề xuất)** | #7C918B | #5B7D73 | viền input/checkbox/radio, đạt 3:1 (WCAG 1.4.11) |

Ghi chú: `--border` #D5E0DB trên #F3F6F4 chỉ đạt **1,24:1**, đủ để phân khối nhưng **không đủ** làm ranh giới của ô nhập. Viền input dùng `--field-border` (3,34:1 trên trắng; 3,67:1 trên #11211D ở nền tối).

### 2.2 Màu chủ đạo và phụ

| Token | Sáng | Tối | Vai trò |
| --- | --- | --- | --- |
| `--primary` / `--on-primary` | #1F6F5C / #FFF | #5CC4A5 / #0B1714 | nút chính, link, tab hiện tại, focus |
| `--primary-hover` | #175A4A | #7DD3B9 | hover |
| `--primary-ink` | #155446 | #8EE0C5 | chữ trên nền mint |
| `--primary-soft` = `--accent` | #D7EFE6 | #17362E | chip, vùng nhấn nhẹ |
| `--inverse` / `--on-inverse` | #12302A / #FFF | #5CC4A5 / #0B1714 | pill mạnh ("Đăng tin") |
| `--field` / `--on-field` | #12302A / #EAF5F0 | #163A31 / #E3F1EC | khối màu lớn duy nhất (hero, màn đăng nhập) |
| `--brand` / `--brand-text` | #3A8A5C / #2F7F52 | #3A8A5C / #6FBF8B | xanh của logo: trang trí và chữ "di" |
| `--price` | #12302A | #E3F1EC | số tiền |
| `--rating` | #B45309 | #F5B544 | sao |

### 2.3 Ngữ nghĩa (semantic)

| Token | Sáng | Tối | Dùng cho |
| --- | --- | --- | --- |
| `--success` / `--success-ink` | #15803D / #166534 | #4ADE80 / #86EFAC | đã thanh toán, "Như mới", xác minh |
| `--danger` / `--danger-ink` | #DC2626 / #B91C1C | #F87171 / #FCA5A5 | lỗi, giá giảm, huỷ |
| `--warning-soft` **(đề xuất)** | #FEF3C7 | #3A2A0E | nền cảnh báo nhẹ, "Khá" |
| `--warning-ink` **(đề xuất)** | #7A4306 | #F5B544 | chữ cảnh báo (hiện `--warning` mượn `--bs-warning`, chưa có bản ink) |
| `--success-soft` **(đề xuất, tên hoá `--bs-success-bg-subtle`)** | #E6F4EA | #16301F | nền chip thành công |
| `--info-soft` / `--info-ink` **(đề xuất)** | #E3EEFB / #1E4E8C | #14263F / #9CC4F5 | thông tin trung tính (đang giao, đã gửi) |

### 2.4 Tương phản đã tính

| Cặp | Tỉ lệ | Yêu cầu |
| --- | --- | --- |
| `--text` #12302A / `--bg` #F3F6F4 | 13,02 | 4,5 đạt |
| `--muted` #4A615B / trắng | 6,66 | 4,5 đạt |
| `--muted` / `--surface-2` #E8EFEB | 5,70 | 4,5 đạt |
| `--primary` #1F6F5C / trắng | 6,02 | 4,5 đạt |
| `--primary` / `--bg` | 5,54 | 4,5 đạt |
| `--primary` / `--primary-soft` #D7EFE6 | 4,98 | 4,5 đạt (chỉ cho chữ ≥ 14 px đậm; chữ nhỏ dùng `--primary-ink`: 7,28) |
| `--danger-ink` #B91C1C / trắng | 6,47 | 4,5 đạt |
| `--success-ink` #166534 / `--success-soft` #E6F4EA | 6,28 | 4,5 đạt |
| `--warning-ink` #7A4306 / `--warning-soft` #FEF3C7 | 7,16 | 4,5 đạt (đề xuất) |
| `--info-ink` #1E4E8C / `--info-soft` #E3EEFB | 7,09 | 4,5 đạt (đề xuất) |
| tối: `--muted` / `--surface-2` | 6,99 | đạt |
| tối: `--warning-ink` #F5B544 / #3A2A0E | 7,63 | đạt (đề xuất) |
| tối: `--success-ink` / #16301F | 10,13 | đạt |
| tối: `--danger-ink` / #3B1D1D | 8,02 | đạt |
| tối: `--info-ink` #9CC4F5 / #14263F | 8,43 | đạt (đề xuất) |
| `--field-border` sáng #7C918B / trắng | 3,34 | 3,0 đạt (đề xuất) |
| `--field-border` tối #5B7D73 / #11211D | 3,67 | 3,0 đạt (đề xuất) |

### 2.5 Khu đấu giá

`.theme-auction` luôn tối (navy #0E0B1F, neon #C6FF3D, live #FF3D7F), tự ghim mọi token dùng chung. Giữ nguyên; không dùng neon/live ở khu thường.

## 3. Chữ

Font: **Lexend** (`--font-ui`) cho giao diện và nội dung; **Fraunces** (`--font-display`) chỉ cho tiêu đề trang, headline hero, con số lớn; **JetBrains Mono** chỉ cho mã (voucher, mã đơn); **Baloo 2 800** chỉ cho wordmark.

Mobile dùng cột "Mobile"; từ 768 px trở lên dùng cột "Desktop". Đơn vị rem với gốc 16 px.

| Vai trò | Font | Mobile | Desktop | Weight | Line-height | Ghi chú |
| --- | --- | --- | --- | --- | --- | --- |
| display (hero) | Fraunces | 2rem / 32px | 3rem / 48px | 600 | 1,1 | tracking -0,012em, chỉ ở hero |
| h1 (tiêu đề trang) | Fraunces | 1.6rem / 25.6px | 2rem / 32px | 600 | 1,2 | một h1 mỗi trang |
| h2 (khối) | Fraunces | 1.3rem / 21px | 1.5rem / 24px | 600 | 1,25 | |
| h3 | Lexend | 1.1rem / 17.6px | 1.15rem / 18.4px (`--font-size-section`) | 600 | 1,35 | |
| h4 / tên trong thẻ | Lexend | 0.95rem / 15.2px | 1rem / 16px | 600 | 1,4 | tối đa 2 dòng |
| body | Lexend | 1rem / 16px | 1rem | 400 | 1,6 | mô tả tin, đọc dài. **Tối thiểu 16 px cho nội dung chính** |
| body-ui (`--font-size-body`) | Lexend | 0.9rem / 14.4px | 0.9rem | 400–500 | 1,5 | nhãn, bảng, form |
| small (`--font-size-small`) | Lexend | 0.8rem / 12.8px | 0.8rem | 400–600 | 1,45 | meta |
| caption | Lexend | 0.75rem / 12px | 0.75rem | 500 | 1,4 | **sàn tối thiểu: không nhỏ hơn 12 px** (code hiện còn 0.68–0.72rem ở vài chỗ, xem audit) |
| price (thẻ) | Lexend | 1.15rem / 18.4px | 1.25rem / 20px | 700 | 1,2 | `font-variant-numeric: tabular-nums`, màu `--price` |
| price (trang chi tiết) | Fraunces | 1.75rem / 28px | 2.25rem / 36px | 600 | 1,1 | tabular-nums |

Quy tắc: câu thường (sentence case), không viết hoa rải chữ, không "eyebrow" trên tiêu đề. Tiền: `1.500.000 ₫` (dấu chấm nghìn, ký hiệu ₫ sau số).

## 4. Khoảng cách, bo góc, đổ bóng

**Spacing** (bội 4 px; token `--space-N`): 1 = 4 px, 2 = 8, 3 = 12, 4 = 16, 5 = 20, 6 = 24, 8 = 32, 10 = 40, 12 = 48. Gutter lưới: 12 px (mobile), 16 px (≥ 768), 24 px (≥ 1200). Lề trang: 16 px mobile, 24 px tablet, container max 1320 px. Padding thẻ: `--space-3` trong thẻ sản phẩm; `--space-4` mobile / `--space-5–6` desktop cho panel.

**Radius** (token hiện có): `--radius-sm` 12 (input), `--radius-md` 14, `--radius-lg` 20 (ảnh trong thẻ, panel), `--radius-xl` 28 (thẻ lớn, dialog), 32 (`--field`), `--radius-pill` 999 (nút, chip, search). **Quy tắc lồng nhau:** radius con = radius cha − padding (ảnh trong thẻ: thẻ 28, padding 8 → ảnh 20). Không dùng giá trị rời (0.65rem, 0.9rem, 0.85rem, 18px) — gộp về token.

**Shadow:** viền mang cấu trúc, bóng chỉ cho lớp nổi.

| Token | Giá trị | Dùng |
| --- | --- | --- |
| `--shadow-card` | none | thẻ thường |
| `--shadow-hover` | `0 1px 0 var(--border)` | hover thẻ (hiện chỉ đổi viền) |
| `--shadow-dialog` | `0 24px 64px rgb(var(--shadow-rgb) / 18%)` (tối 55%) | dialog, sheet, menu |
| `--shadow-bar` **(đề xuất)** | `0 -4px 16px rgb(var(--shadow-rgb) / 6%)` | thanh dưới (tab bar, buy bar); hiện là giá trị cứng trong `MobileTabBar.vue` |

## 5. Lưới và breakpoint

Mobile-first (viết CSS cho 360 px trước, `min-width` để mở rộng; hiện code phần lớn dùng `max-width` — khi sửa dần chuyển sang mobile-first).

| Tên | Từ | Cột thẻ sản phẩm | Điều hướng |
| --- | --- | --- | --- |
| xs | 360 | **2 cột** (gutter 12) | tab bar dưới, header gọn |
| sm | 576 | 2 | tab bar |
| md | 768 | 3 | header một hàng, ẩn tab bar |
| lg | 992 | 4 | + sidebar lọc cố định ở trang danh sách |
| xl | 1200 | 4–5 | hiện wishlist, "Quản trị" |
| xxl | 1500 | 5 | hiện "Quản lý tin" |

Hai điểm gãy chính đã có: 767 (điện thoại) và 991 (tablet). Giữ. Không cuộn ngang từ 360 px; kiểm tra 360 / 390 / 820 / 1440. Danh sách mobile 2 cột; tin có tên dài vẫn cắt 2 dòng.

## 6. Nút

Mọi nút là **pill** (`--radius-pill`), cao tối thiểu **44 px** (`--control-height`), padding ngang 20 px, chữ 0.9rem/600, động từ ("Đăng tin", "Mua ngay").

| Biến thể | Nền / chữ | Dùng |
| --- | --- | --- |
| primary | `--primary` / `--on-primary` | hành động chính, một cái mỗi vùng nhìn |
| inverse | `--inverse` / `--on-inverse` | "Đăng tin" ở header, CTA trên hero |
| secondary (outline) | trong suốt, viền 1,5 px `--primary`, chữ `--primary` | hành động phụ ("Nhắn người bán") |
| tonal | `--primary-soft` / `--primary-ink` | hành động nhẹ (Lưu, Yêu thích) |
| danger | `--danger` / `--on-danger` | xoá, huỷ (luôn qua xác nhận) |
| ghost / link | trong suốt, chữ `--primary` | thao tác thứ cấp trong dòng |

Kích cỡ: **lg** 52 px (CTA trang chi tiết, thanh dưới), **md** 44 px (mặc định), **sm** 36 px chỉ cho desktop dày đặc (bảng admin), **không dùng sm trên mobile cho thao tác chính**; nếu vẫn cần, vùng chạm tăng bằng padding bao ngoài ≥ 44 px.

Trạng thái:

| State | Quy tắc |
| --- | --- |
| hover | nền → `--primary-hover` (outline: nền `--primary-soft`); transition `--transition-fast` |
| focus-visible | `outline: var(--focus-ring)` (3 px), `outline-offset: 2px`; không bao giờ `outline: none` |
| active | thu nhẹ `transform: scale(.98)`, nền tối hơn một bước |
| disabled | `opacity: .5`, `cursor: not-allowed`, kèm lý do bằng chữ gần nút ("Cần chọn tình trạng") |
| loading | giữ nguyên bề rộng, spinner 16 px thay icon, chữ "Đang đăng…", `aria-busy="true"`, chặn bấm lặp |

Icon-only: `aria-label` bắt buộc, vùng chạm 44×44.

## 7. Input và form

- Cao ≥ 44 px, radius `--radius-sm`, nền `--surface`, viền 1 px `--field-border` **(đề xuất)**, chữ 16 px trên mobile (tránh iOS tự phóng to).
- Nhãn luôn hiện phía trên (`label for`), 0.85rem/600; placeholder không thay nhãn. Dấu "*" kèm chữ "(bắt buộc)" cho trình đọc màn hình.
- Gợi ý (hint) dưới ô, màu `--muted` 0.8rem. Lỗi: viền `--danger`, icon + chữ `--danger-ink`, `aria-describedby` trỏ tới lỗi, `aria-invalid="true"`; không chỉ đổi màu.
- Focus: viền `--primary` + `--focus-ring`.
- Tiền: dùng `MoneyInput` (hiển thị `1.500.000`, gửi số thuần). Chọn tình trạng: **chip radio** (xem §10), không dùng select cho nhóm ≤ 5 lựa chọn.
- Checkbox/radio 24 px, vùng bấm cả dòng ≥ 44 px.
- Form dài chia bước (xem §9 – "Đăng tin"), lưu nháp tự động, thanh hành động dính đáy (đã có `seller-submit-bar`).

## 8. Thẻ sản phẩm (ProductCard) — photo-first

### 8.1 Giải phẫu (từ trên xuống)

1. **Ảnh**, tỉ lệ **4:5** (đề xuất; hiện 1:1) — `object-fit: cover`, radius `--radius-lg`, nền `--surface-2`. 4:5 cho đồ cầm tay/thời trang/sách chiếm nhiều diện tích hơn, vẫn đủ cho đồ điện tử; giữ 1:1 ở lưới dày desktop ≥ 1200 nếu cần.
   - Góc trên trái: **nhãn trạng thái** (tối đa 1): "Đấu giá" / "Có video" (chip pill 12 px/700).
   - Góc trên phải: nút yêu thích 44×44 (nền `--surface` 90%).
   - Góc dưới trái (trên gradient `rgb(0 0 0 / 55%)→0`): số ảnh (icon + số). Chữ trắng trên gradient là ngoại lệ duy nhất cho `#fff`.
2. **Giá** (`price`, 1.15rem/700, `--price`), kế bên giá gốc gạch ngang `--muted` và chip `-xx%` (`--danger`). **Giá không đỏ** trừ khi đang giảm.
3. **Tên** (h4 15 px/600, 2 dòng, `--text`).
4. **Tình trạng** (chip, §10) + (nếu có) nhãn "Còn n" khi tồn kho ≤ 3.
5. **Dòng meta cuối**: `Khu vực · thời gian đăng` (0.75rem `--muted`, một dòng, cắt `…`). Tên shop và sao đưa xuống dòng riêng: `★ 4,8 (23) · Tên shop` chỉ hiện khi có đánh giá; có huy hiệu "Đã xác minh" nhỏ (icon 14 px) cạnh tên shop khi shop đã xác minh.

Thứ tự ưu tiên thị giác: **ảnh → giá → tên → tình trạng → khu vực/thời gian → shop/sao**. Thẻ hiện tại đúng phần lớn nhưng gom tình trạng, shop, khu vực, sao, thời gian vào một đoạn meta 12 px dễ rối.

### 8.2 Kích thước

Mobile 2 cột: rộng ≈ (360 − 2×16 − 12) / 2 = 158 px → ảnh 158×198 px. Padding trong thẻ `--space-2` ở mobile, `--space-3` từ 768. Không dùng thẻ có viền + bóng cùng lúc; trạng thái nghỉ: không viền, nền `--surface`; hover/focus-within: viền `--border`.

### 8.3 Trạng thái

| State | Biểu hiện |
| --- | --- |
| default | như trên |
| hover / focus-within | viền `--border` (150 ms); desktop có thể phóng ảnh 1,03 trong khung (tôn trọng reduced-motion) |
| yêu thích đã bật | tim đầy `--danger`, `aria-pressed="true"` |
| đã bán / hết | ảnh phủ `rgb(18 48 42 / 55%)` + chip "Đã bán" ở giữa; không còn nút mua, thẻ vẫn bấm xem được |
| đấu giá | chip "Đấu giá" trên ảnh; dòng giá thành "Giá hiện tại" + đồng hồ đếm ngược (`--font-money`) |
| ảnh lỗi/thiếu | `product-placeholder.svg`, `object-fit: cover`, vẫn đúng tỉ lệ |

### 8.4 Skeleton

Khối ảnh đúng tỉ lệ 4:5 (`--surface-skeleton`, radius `--radius-lg`), 3 thanh: giá (40% rộng, cao 18 px), tên (90% và 60%, cao 14 px), meta (50%, cao 12 px). Shimmer chỉ khi `prefers-reduced-motion: no-preference`; số skeleton bằng số thẻ dự kiến mỗi trang (8 mobile = 4 hàng).

## 9. Điều hướng và luồng chính

### Header
Một hàng: menu, logo (con dấu + wordmark), ô tìm kiếm pill có nút khu vực bên trong, icon, **"Đăng tin"** (inverse, luôn thấy từ md), menu tài khoản. Mobile: logo + "Đăng tin" nhỏ + ô tìm kiếm hàng riêng. Mọi nút ≥ 44 px (đang còn 34–38 px, xem audit). Thanh danh mục chip cuộn ngang dưới header, có gợi ý cuộn (mờ cạnh phải).

### Thanh điều hướng dưới (mobile ≤ 767)
5 mục: **Trang chủ · Đấu giá · Đăng tin · Tin nhắn · Tài khoản** (đã có trong `MobileTabBar.vue`). Tinh chỉnh **(đề xuất)**: "Đăng tin" nằm giữa, nút nổi `--primary` 48×48 (hiện 36×28), nhô nhẹ lên trên thanh; nhãn tối thiểu 0.7rem (12 px khuyến nghị); khách chưa đăng nhập thay "Tin nhắn" bằng "Tìm kiếm" như hiện tại; mọi thứ dính đáy (buy bar) nằm trên tab bar bằng biến `--tabbar-height` **(đề xuất)** thay vì số cứng. Chỉ hiện ở trang duyệt, ẩn khi mở bàn phím/nhập form dài.

### Luồng "Đăng tin" (đề xuất — thay form một trang 5 mục)
Bước 1 **Ảnh** (kéo thả/chụp, tối thiểu 1, khuyên 3–5, ảnh đầu là ảnh bìa, sắp xếp được) → Bước 2 **Mô tả**: tên, danh mục, **tình trạng (chip radio với mô tả ngắn từng mức)** → Bước 3 **Giá** (MoneyInput + gợi ý khoảng giá, số lượng, khu vực) → Bước 4 **Xem trước & đăng** (thẻ xem trước đúng như người mua thấy). Thanh tiến trình "Bước 2/4" dùng `aria-current="step"`; nút "Tiếp tục" dính đáy, "Lưu nháp" luôn có; mỗi bước validate riêng, tự lưu nháp. Dùng lại `SellerProductEditor` và `AccessibleDialog`/trang riêng, không viết lại logic.

## 10. Chip và huy hiệu

Chip: pill, cao 28 px (chip chỉ hiển thị) hoặc 44 px vùng chạm (chip lọc/chọn), chữ 0.75–0.8rem/600, icon 14 px. Nền soft + chữ ink (đã tính ở §2.4).

**Tình trạng món đồ (condition)** — chữ luôn hiện, không chỉ màu:

| Mức | Nền | Chữ |
| --- | --- | --- |
| Mới / Như mới | `--success-soft` | `--success-ink` |
| Tốt | `--primary-soft` | `--primary-ink` |
| Khá | `--warning-soft` | `--warning-ink` |
| Cũ / Cần sửa | `--surface-2` | `--muted` |

**Trạng thái đơn/tin** dùng `StatusBadge` (chữ + chấm màu): chờ (`--warning-*`), đang xử lý (`--info-*`), hoàn tất (`--success-*`), huỷ/lỗi (`--danger` nền nhạt, chữ `--danger-ink`), nháp (`--surface-2`/`--muted`). Chip lọc đang chọn: nền `--primary`, chữ `--on-primary`, có dấu tick.

## 11. Thành phần tin cậy (đề xuất)

| Thành phần | Mô tả | Vị trí |
| --- | --- | --- |
| Huy hiệu "Đã xác minh" | icon khiên-tick 14–16 px + chữ; `--primary-soft`/`--primary-ink`. Chỉ hiện khi backend có cờ xác minh thật; **không hiện giả** | thẻ (cạnh tên shop), hàng người bán, trang shop |
| Sao + số đánh giá | `StarRating` + `4,8 (23)`; ẩn khi chưa có đánh giá (không hiện "0") | thẻ, trang chi tiết |
| Hàng người bán | avatar 40 px, tên, xác minh, sao, "Tham gia 03/2025", "Phản hồi trong ~1 giờ" nếu có dữ liệu, nút "Nhắn người bán" | trang chi tiết, ngay dưới giá |
| Khối "Mua an toàn" | tối đa 3 gạch đầu dòng ngắn: thanh toán qua Passdi/COD kiểm hàng, kiểm tra tình trạng khi nhận, nút "Báo cáo tin"; nền `--surface-2`, icon khiên `--primary` | trang chi tiết (đã có "Lưu ý an toàn giao dịch", rút gọn, đưa lên sát nút mua) |
| Thời gian + khu vực | luôn hiện trên thẻ | thẻ |
| Đếm ảnh/video | tăng niềm tin về mô tả | thẻ, gallery |

Quy tắc: mọi tín hiệu tin cậy phải dựa trên dữ liệu từ API; thiếu dữ liệu thì ẩn, không bịa.

## 12. Trạng thái rỗng, lỗi, tải

- **Loading:** skeleton đúng hình dạng nội dung (không spinner toàn trang). Nút đang gửi dùng trạng thái loading (§6). Ảnh `loading="lazy"` trừ ảnh đầu trang (LCP) `fetchpriority="high"`.
- **Empty:** `EmptyState` = icon/minh hoạ nhỏ + một câu nói rõ lý do + **một hành động kế tiếp** ("Xoá bộ lọc", "Đăng tin đầu tiên", "Xem tin mới"). Giọng ngắn, không đổ lỗi.
- **Error:** thông báo cụ thể ("Không tải được tin. Kiểm tra mạng rồi thử lại.") + nút "Tải lại"; `role="alert"` cho lỗi gửi form; lỗi từng ô gắn với ô đó. Trang 404/403 có đường về trang chủ và ô tìm kiếm.
- **Thành công:** toast `role="status"`, tự tắt 4–5 s, có nút đóng; không che tab bar.

## 13. Khả năng tiếp cận

- Tương phản chữ ≥ 4,5:1 (chữ lớn ≥ 18,66 px đậm / 24 px ≥ 3:1); thành phần UI và biểu tượng có nghĩa ≥ 3:1. Cả sáng và tối.
- Focus nhìn thấy trên mọi phần tử tương tác (`--focus-ring`); thứ tự tab theo thứ tự thị giác; dialog giữ focus và trả focus; `Esc` đóng.
- Mọi ảnh sản phẩm có `alt` có nghĩa (tên + đặc điểm), ảnh trang trí `alt=""`; icon-only có `aria-label`.
- Mọi input có `label`; lỗi liên kết `aria-describedby`; nhóm radio dùng `fieldset/legend`.
- Mục tiêu chạm ≥ 44×44 px, khoảng cách giữa các mục ≥ 8 px.
- Không truyền đạt chỉ bằng màu; trạng thái luôn có chữ.
- Tôn trọng `prefers-reduced-motion` (GSAP và shimmer chỉ khi `no-preference`); `prefers-color-scheme` làm mặc định cho chủ đề.
- Phóng to 200% và chữ 16 px gốc không vỡ bố cục; không `user-scalable=no`.
- Heading theo thứ tự (một h1/trang); landmark `header/nav/main/footer`; liên kết "Bỏ qua tới nội dung".

## 14. Nên và không nên

**Nên**
- Dùng token; thêm token mới vào `tokens.css` cho cả sáng và tối trước khi dùng.
- Cho ảnh chiếm ≥ 60% chiều cao thẻ; giá nổi nhất sau ảnh.
- Hiện tình trạng, khu vực, thời gian ngay trên thẻ.
- Một nút chính mỗi vùng; "Đăng tin" luôn trong tầm tay.
- Chia form dài thành bước, lưu nháp.
- Kiểm tra sáng/tối, 360/390/820/1440 px, bàn phím.

**Không**
- Không dùng hex, font hay palette ngoài file này/`DESIGN.md`.
- Không tô giá đỏ cho mọi tin; không dùng màu cam.
- Không dùng chữ nhỏ hơn 12 px, không nút dưới 44 px cho hành động chính trên mobile.
- Không bịa dữ liệu tin cậy (xác minh, phản hồi nhanh) khi API chưa có.
- Không ép ảnh về `object-fit: contain` trong thẻ (tạo khoảng trống xám), không méo ảnh.
- Không hai dialog cùng lúc; không form mở sẵn khi chỉ cần xem.
- Không dùng viền `--border` làm ranh giới duy nhất của ô nhập.
- Không mix Fraunces vào đoạn văn nội dung.

## 15. Danh sách mục đề xuất (chưa có trong code)

Token mới: `--field-border`, `--warning-soft`, `--warning-ink`, `--success-soft`, `--info-soft`, `--info-ink`, `--shadow-bar`, `--tabbar-height`. Component/hành vi mới: thẻ 4:5 và thứ tự thông tin mới (§8), huy hiệu "Đã xác minh", hàng người bán, khối "Mua an toàn" rút gọn (§11), luồng đăng tin 4 bước (§9), nút "Đăng tin" nổi 48 px ở tab bar, chip radio tình trạng, thang chữ có sàn 12 px, trạng thái "Đã bán" trên thẻ, lưới mobile-first.

## 16. Đã triển khai (trang chủ và danh sách sản phẩm)

- Token mới đã vào `tokens.css` (sáng/tối/.theme-auction) và `scripts/contrast.mjs`: `--success-soft`, `--warning-soft`, `--warning-ink`, `--shadow-bar` (đang dùng ở `MobileTabBar`). Chưa làm: `--field-border`, `--info-*`, `--tabbar-height`.
- `ProductCard`: ảnh 4:5 (`ProductPhoto fit="cover"`, không còn `!important`), giá `--price`, tên 2 dòng, chip tình trạng (Như mới = success, Tốt = primary, Đã sử dụng nhiều = warning, Cần sửa chữa = trung tính), dòng "khu vực · thời gian", dòng người bán riêng (sao ẩn khi chưa có đánh giá), "Đã bán"/"Hết hàng" (status SOLD hoặc stock 0), chip "Còn n" khi 2–3. Chưa có huy hiệu "Đã xác minh" vì API chưa có cờ.
- Lưới `.product-grid` (ui.css): 2 cột, 3 cột từ 768, 4 cột từ 992. `ProductGridSkeleton` đúng tỉ lệ 4:5.
- Danh sách: chip lọc bấm để bỏ từng bộ lọc; từ 992 bộ lọc là cột bên trái, dưới 992 mở bằng nút "Bộ lọc" (sheet).
- Trang chủ: hero có nút "Đăng tin", banner xoay ẩn trên điện thoại, "Tin mới đăng" (tab dạng chip, 8 thẻ trên điện thoại), khu đấu giá tối `HomeAuctions` (dải cuộn ngang trên điện thoại, lưới từ 768).
