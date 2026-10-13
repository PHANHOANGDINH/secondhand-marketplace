# UI redesign đợt 2→7 — verification

Ngày 2026-10-14. Nối tiếp [đợt 0+1](ui-redesign-0-1-verification.md). Chỉ đổi phần nhìn, trừ đợt 7 (tính năng hướng dẫn lần đầu). Không đổi API, route, store, realtime.

## Bảng đợt

| Đợt | Commit | vitest | oxlint / ESLint / build | Kết quả |
| --- | --- | --- | --- | --- |
| Trước đợt 2: brand config, nhãn video, dòng sao | `f466ce6` | 464 / 38 file | PASS | PASS |
| 2 – Home + listing | `c60c6b1` | 464 / 38 | PASS | PASS |
| 3 – Product + shop | `28adfd6` | 464 / 38 | PASS | PASS (thiếu 3 mục, xem dưới) |
| 4 – Mua hàng | `9743c5d` | 466 / 38 | PASS | PASS (không có voucher) |
| 5 – Đấu giá, video, chat | `50dd6e1` | 466 / 38 | PASS | PASS |
| 6 – Seller + Admin | `d5a12d1` | 466 / 38 | PASS | PASS (không có Vouchers) |
| 7 – Hướng dẫn lần đầu | `91541ff` | 469 / 38 | PASS | PASS |

- Backend: lần chạy đầy đủ gần nhất là sau khi thêm `has_video` (trước đợt 2): **457 test / 5.080 assertions PASS**, Pint PASS. Đợt 2→7 không chạm backend nên không chạy lại.
- Trình duyệt: Edge headless, tư cách khách, chỉ đọc, trên dev server đang chạy. 8 trang × 2 bề rộng (1440, 390): không tràn ngang, 0 lỗi JS. Ảnh ở `docs/ui-redesign/`.
- **Chưa xem bằng mắt**: mọi trang cần đăng nhập (giỏ, thanh toán, đơn hàng, tài khoản, chat, thông báo, Seller, Admin, hướng dẫn lần đầu). Các trang này chỉ được phủ bằng test component.

## Đã làm theo từng đợt

- **Đợt 2.** Trang chủ: banner, lưới danh mục có icon, dải "Đang đấu giá" trên `.theme-auction`, "Gợi ý cho bạn", "Tin mới đăng", khu video. Danh sách sản phẩm: bộ lọc dạng chip thả xuống, đếm kết quả, lọc bằng bottom sheet trên điện thoại.
- **Đợt 3.** Chi tiết sản phẩm: gallery `contain` có đếm ảnh, cột mua hàng sticky, thẻ người bán, khung lưu ý an toàn, khối mô tả và bảng thông tin, thanh mua dính đáy trên điện thoại. Trang gian hàng có thẻ đầu trang.
- **Đợt 4.** Skin cho giỏ, thanh toán, đơn hàng, tài khoản, địa chỉ. Đơn mua có tab theo trạng thái (dùng tham số `overall_status` sẵn có). Chi tiết đơn có timeline dựng từ các mốc thời gian API đã trả.
- **Đợt 5.** Phòng đấu giá, sàn đấu giá và toast bị vượt giá chạy trên `.theme-auction`: giá và đồng hồ JetBrains Mono màu neon, nhãn LIVE hồng, bid log. Thông báo và chat đọc token chung.
- **Đợt 6.** Seller và Admin dùng chung một skin shell (top bar sticky có logo, sidebar, panel, bảng, toolbar). Sản phẩm của tôi và Đơn bán có tab trạng thái gắn vào đúng bộ lọc trạng thái sẵn có.
- **Đợt 7.** `useFirstTime(key)` + `FirstTimeGuide.vue`: tour chào mừng sau khi đăng nhập, ghi chú một lần ở Đăng bán nhanh, phòng đấu giá, trang gian hàng; nút "Bỏ qua"; mục "Xem lại hướng dẫn" trong menu tài khoản. GSAP chỉ còn ở component này (lazy import, `gsap.matchMedia`, `context().revert()`). Đã xóa `HomeScrollStory.vue`.

## Test đã sửa và lý do

| Test | Thay đổi | Lý do |
| --- | --- | --- |
| `App.spec.js` | Đổi chuỗi thương hiệu; stub `HomeHero`, `HomeLiveAuctions` ở test thử lại kết nối | Tên thương hiệu mới; banner gọi API danh mục trước lệnh kiểm tra kết nối mà test coi là lệnh đầu tiên |
| `AdminUI.spec.js` | Kỳ vọng `.admin-brand` là "pass lại" + "Quản trị" | Nhãn khu quản trị lấy từ `brand.js` |
| `OrderPhase8.spec.js` | Thêm 2 test (tab trạng thái, timeline) | Phần mới của đợt 4 và 6 |
| `HomeScrollStory.spec.js` | Xóa | Component bị xóa ở đợt 7 theo yêu cầu gỡ GSAP |
| `UiCore.spec.js`, `Onboarding.spec.js` | Mới (8 và 5 test) | Thẻ sản phẩm, brand, hàng danh mục; hướng dẫn lần đầu |
| `ProductDiscoveryTest.php` | Thêm 2 test | `has_video` đúng/sai và không tăng số query |

Không có test nào bị sửa để che một thay đổi hành vi.

## Quyết định tự chọn (phương án an toàn nhất)

1. **Không có "Mua ngay".** Ứng dụng chưa có luồng mua ngay; thêm vào là thêm logic. Chỉ giữ "Thêm vào giỏ".
2. **Thẻ người bán không có sao và ngày tham gia.** API sản phẩm chỉ trả tên và slug của gian hàng. Thẻ hiện tên, link gian hàng và nút Chat.
3. **Không có voucher** ở thanh toán và không có trang Vouchers cho Seller: tính năng này không tồn tại trong backend.
4. **Tab trạng thái đơn mua** gọi thêm tham số `overall_status` của endpoint sẵn có. Đây là điều khiển mới trên API cũ, không đổi API.
5. **Tab ở Seller** không thay ô chọn trạng thái cũ mà gắn chung một state với nó, để test và hành vi cũ giữ nguyên.
6. **Trang chủ mở sẵn tab "Video đánh giá"** trong khu khám phá, vì đấu giá đã có dải riêng phía trên. Mặc định của component khi dùng nơi khác vẫn là đấu giá.
7. **Banner tĩnh thay scroll story.** Đợt 7 yêu cầu gỡ GSAP ở mọi chỗ khác, nên câu chuyện cuộn 5 cảnh bị bỏ hẳn; chỉ giữ lại hình minh họa.
8. **Tour chào mừng chỉ mở khi vừa đăng nhập trong phiên trang hiện tại**, không mở khi tải lại trang đã đăng nhập. Cờ vẫn chờ cho lần đăng nhập sau hoặc khi bấm "Xem lại hướng dẫn".
9. **Ba hướng dẫn còn lại là ghi chú trong trang, không phải hộp thoại**, để không chặn thao tác chính (đăng tin, ra giá). Focus trap chỉ áp cho tour chào mừng; ghi chú đóng bằng "Bỏ qua" hoặc ESC khi đang focus trong nó.
10. **Tiêu đề trang** là `<trang> · pass lại` cho mọi route; "pass lại · Kênh người bán" và "pass lại · Quản trị" dùng làm nhãn thương hiệu của hai shell.
11. **Animation CSS cũ của đấu giá** (đổi giá 0,55 s, nhịp đồng hồ giây cuối) được giữ: đó là CSS có sẵn, không phải GSAP, và vẫn tắt khi reduced-motion.

## Giới hạn còn lại

- Chưa có ảnh chụp và chưa rà bằng mắt các trang cần đăng nhập. Rủi ro lớn nhất nằm ở Seller/Admin và thanh mua dính đáy trên điện thoại thật.
- Đợt 4 và 6 chủ yếu là đổi skin trên cấu trúc cũ; bố cục thanh toán (địa chỉ, nhóm theo shop, tổng tiền sticky) vốn đã có nên không dựng lại.
- Chưa đo contrast tự động; mới kiểm tay các cặp màu token.
- Font vẫn nạp từ Google Fonts.
- `backend/CLAUDE.md` vẫn mâu thuẫn với `CLAUDE.md` gốc, chưa xử lý.

## Bổ sung 2026-10-14: rà trang đăng nhập, thẻ người bán, Mua ngay

| Phần | Commit | Kết quả |
| --- | --- | --- |
| A – tour mở ở mọi lần vào trang; rà trang đăng nhập | `a4c67f7` | PASS |
| B – `shop.rating_avg`, `rating_count`, `joined_at` | `7ccbbde` | PASS |
| C – Mua ngay (`cart_item_ids` ở checkout) | `018c5d4` | PASS |
| D – D6 Voucher | xem [D6 verification](extension-d6-vouchers-verification.md) | PASS (làm sau, khi đã có đề bài) |

- Số liệu cuối: backend **460 test / 5.137 assertions PASS**, Pint PASS; frontend **472 test / 38 file PASS**; oxlint, ESLint, build PASS.
- Rà trang đăng nhập: stack riêng (PHP :8001 với `APP_ENV=testing`, Vite :5174, Edge headless), fixture tạo bằng script chỉ chạy trên DB test. 27 trang × 2 bề rộng: không tràn ngang, 0 lỗi JS. Tour: focus nằm trong hộp thoại, ESC đóng và lưu cờ, tải lại không mở lại.
- Lỗi hiển thị tìm thấy và đã sửa: nút "Điều hướng gian hàng/quản trị" hiện sai trên desktop; logo trong shell bị viết hoa và xám; nhãn top bar, giá đơn và tổng tiền bị xuống dòng trên điện thoại.
- Dọn dẹp: đã dừng ba tiến trình của stack, xóa fixture trong DB test, xóa 16 file session tạo sau mốc bắt đầu. DB dev: 24 bảng nghiệp vụ không đổi mã băm.
- Mua ngay: checkout cũ chỉ đặt cả giỏ, nên thêm tham số tùy chọn `cart_item_ids`. Hash idempotency có danh sách item (đã sắp xếp); yêu cầu đặt cả giỏ giữ nguyên hash cũ. Item không chọn, kể cả item đang không mua được, không chặn đơn và vẫn nằm trong giỏ.
- Các quyết định 1, 2 và 8 ở trên đã được thay thế: có "Mua ngay", thẻ người bán có sao và tháng tham gia, tour mở ở mọi lần vào trang cho tới khi xem xong hoặc bỏ qua.
