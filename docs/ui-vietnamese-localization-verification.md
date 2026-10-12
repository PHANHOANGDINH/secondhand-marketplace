# Xác minh Việt hóa giao diện

Ngày 09/10/2026 — **PASS cho nội dung giao diện do ứng dụng kiểm soát.** Không bổ sung tính năng, không thay đổi quy tắc nghiệp vụ. Nội dung tự do của người dùng/dữ liệu minh họa và mã định danh được giữ nguyên; báo cáo không khẳng định mọi nội dung dữ liệu đều là tiếng Việt.

## Phạm vi và thay đổi đã xác nhận

Đã rà soát các màn hình công khai, xác thực/khôi phục mật khẩu, tài khoản/địa chỉ, sản phẩm, giỏ hàng/đặt hàng, đơn hàng/thanh toán/đánh giá, tin nhắn/thông báo, người bán, quản trị, đấu giá và video/bình luận. Bao gồm nhãn truy cập, tiêu đề, điều hướng, bộ lọc, bảng, nút, mô tả, trạng thái tải/trống/lỗi, xác nhận và giao diện di động.

- Thay nhãn “Admin”, “Marketplace”, “Seller workspace”, “Video Review”, “Bidding Arena”, “Checkout”, “ZaloPay Online” bằng tiếng Việt tự nhiên. Tên thương hiệu Secondhand và ZaloPay được giữ nguyên.
- `src/utils/labels.js` tập trung nhãn trạng thái theo ngữ cảnh, vai trò, phương thức thanh toán và kết quả đấu giá. `StatusBadge` dùng nhãn tiếng Việt; trạng thái lạ trả về “Không xác định”, kể cả khóa kế thừa của đối tượng.
- Bộ lọc và lựa chọn vai trò có nhãn tiếng Việt nhưng vẫn giữ `value="ACTIVE"`, `value="USER"`, v.v. Đã quan sát yêu cầu thật của bộ lọc quản trị gửi `status=ACTIVE`.
- Hai mô-đun trình bày đơn hàng/đấu giá dùng lại ánh xạ tập trung. Điều kiện sản phẩm không nhận biết cũng có nhãn dự phòng tiếng Việt.
- `src/utils/validationLabels.js` Việt hóa thông báo kiểm tra dữ liệu tại biên trình bày; giữ nguyên tên trường và các lỗi. Các quy tắc quen thuộc giữ thông tin bắt buộc/độ dài/xác nhận; thông báo không nhận biết có lời nhắc tiếng Việt an toàn. Không đưa nguyên thông báo ngoại lệ/xung đột máy chủ lên giao diện.
- Thay phần hiển thị nút chọn ảnh/video mặc định bằng nút và trạng thái lựa chọn tiếng Việt. Giữ nguyên đầu vào tệp, định dạng, số lượng, bộ chọn tệp, xử lý thay đổi và điều kiện vô hiệu hóa. Nút có `type="button"`, tên truy cập rõ ràng, không gửi biểu mẫu khi chọn tệp.
- Giá giữ bộ định dạng chính xác hiện có (`5.500.000 ₫`); ngày/giờ giữ định dạng `vi-VN`. Nút tăng giá nhanh dùng số và ký hiệu tiền Việt thay hậu tố `k`.

## Ánh xạ tiêu biểu

| Ngữ cảnh / giá trị nội bộ | Nhãn hiển thị |
|---|---|
| ACTIVE | Đang hoạt động |
| PENDING: gian hàng/sản phẩm | Chờ duyệt |
| PENDING: đơn hàng | Chờ xử lý |
| PENDING: thanh toán | Chờ thanh toán |
| SUSPENDED / REJECTED / DRAFT / SOLD | Tạm đình chỉ / Bị từ chối / Bản nháp / Đã bán |
| SHIPPING / COMPLETED / CANCELLED | Đang giao hàng / Hoàn thành / Đã hủy |
| PAID / FAILED: thanh toán | Đã thanh toán / Thanh toán thất bại |
| SCHEDULED / LIVE / ENDED | Sắp diễn ra / Đang đấu giá / Đã kết thúc |
| null: kết quả đấu giá | Không phát sinh |
| NO_WINNER / AWAITING_CHECKOUT | Không có người thắng / Chờ người thắng hoàn tất đơn |
| ORDER_CREATED / CHECKOUT_EXPIRED | Đã tạo đơn hàng / Hết hạn thanh toán |
| PROCESSING / READY / FAILED: video | Đang xử lý / Sẵn sàng / Xử lý thất bại |
| PUBLISHED / HIDDEN: đánh giá/bình luận | Đã hiển thị / Đã ẩn |
| USER / ADMIN | Người dùng / Quản trị viên |
| Giá trị không nhận biết | Không xác định |

Dùng các giá trị thực tế của dự án: đơn bán là `SHIPPING`, không tạo thêm `SHIPPED`; thanh toán dùng `PAID`, không tạo thêm `SUCCESS`; kết quả đấu giá dùng các giá trị trong bảng, không thêm enum từ ví dụ yêu cầu. Mọi giá trị nội bộ giữ nguyên.

## Kiểm tra tự động

Chạy tại `frontend`:

```powershell
npm run test:unit -- --run
npm run lint
npm run build
```

| Kiểm tra cuối | Kết quả |
|---|---|
| Toàn bộ frontend | **362/362 test, 29/29 file đạt** |
| Test mới | 7 test: ngữ cảnh trạng thái, giá trị lạ, kết quả đấu giá thực tế, giá trị gửi từ biểu mẫu, thông báo kiểm tra dữ liệu an toàn, lỗi xung đột/tiền tệ, bộ chọn tệp |
| Lint | PASS: oxlint + ESLint |
| Production build | PASS |
| Kiểm tra khoảng trắng | PASS; không có khoảng trắng cuối dòng trên các file sửa |
| Rà soát mẫu Vue qua cây cú pháp | Không còn từ tiếng Anh/enum mục tiêu trong văn bản tĩnh và thuộc tính hiển thị được rà soát |

Các test cũ chỉ cập nhật kỳ vọng văn bản hiển thị; dữ liệu giả enum/API và các assertion kiểm tra payload/ủy quyền/trạng thái vẫn giữ nguyên. Không thêm thư viện dịch, không đổi package/lockfile. Không chạy lại backend test/Pint vì backend không thay đổi.

## Kiểm tra trình duyệt thật

Giao diện tại `http://localhost:5173`, Laravel tại `http://127.0.0.1:8000`. Dùng Edge riêng và các tài khoản minh họa C1 có sẵn; không ghi thông tin đăng nhập vào báo cáo.

- **140 trạng thái trang/ảnh chụp** ở 1440, 1024, 768, 390 px; kiểm tra lại **22 trạng thái đã tải dữ liệu** sau các sửa cuối. Tổng cộng **162 lần kiểm tra trạng thái trang**, **345 assertion trình duyệt**. Đã xem các bảng ảnh tổng hợp và ảnh gốc đại diện, đồng thời kiểm tra văn bản/nhãn/lựa chọn và kích thước DOM.
- Công khai: trang chủ, danh sách/chi tiết sản phẩm, danh sách/phòng đấu giá, video và bình luận; đăng nhập/đăng ký/quên/đặt lại mật khẩu và trang không tìm thấy.
- Người mua: tài khoản/hồ sơ/địa chỉ, giỏ hàng/đặt hàng, đơn mua/chi tiết, thông báo, khung tin nhắn và bảng thông báo. Không gửi tin nhắn, đánh dấu đã đọc hoặc đặt đơn.
- Người bán: tổng quan, sản phẩm, biểu mẫu sửa/ảnh/video, đơn bán, đấu giá, gian hàng. Kiểm tra lại biểu mẫu đã tải xong và nhãn chọn tệp ở cả bốn kích thước; không lưu/tải lên/gửi duyệt.
- Quản trị: cả bảy khu vực và chi tiết người dùng/gian hàng/sản phẩm/đơn hàng/đánh giá. Nhãn bộ lọc, vai trò, trạng thái và phần giải thích doanh thu đã Việt hóa; không kiểm duyệt dữ liệu.
- Kiểm tra đường dẫn thực tế `/403` bằng tài khoản người mua mở trang quản trị; thông báo quyền truy cập bằng tiếng Việt. Kiểm tra kết quả COD qua `/payment/result?order=...`; không dùng kết quả giả từ trình chuyển hướng.
- **Không có tràn ngang cấp trang ở các trạng thái kiểm tra**, không có lỗi JavaScript được ghi nhận. Bàn phím có thể đưa focus vào điều khiển; chế độ giảm chuyển động giữ câu chuyện trang chủ đọc được và không ghim.
- Lượt duyệt công khai dồn dập ban đầu chạm giới hạn đọc hiện có, nên một số ảnh thể hiện trạng thái lỗi/thử lại tiếng Việt. Giữ nguyên giới hạn; kiểm tra lại sản phẩm/phòng đấu giá/video ở 768/390 px đã tải thành công, lượt bổ sung không có phản hồi 429.
- Chỉ có POST đăng nhập/đăng xuất và xác thực kênh riêng; không có yêu cầu ghi nghiệp vụ. Phiên kiểm tra đã đăng xuất. Bằng chứng cục bộ trong `.runtime/vi-*` được bỏ qua bởi Git.

## Bảo toàn phạm vi

Hash backend/app, config, database và routes giữ nguyên so với baseline trước lượt Việt hóa; không thêm file backend. Hash router/Pinia giữ nguyên. Hai file API frontend chỉ sửa hàm thông báo lỗi; URL, payload, thao tác đọc/ghi và CSRF không thay đổi. Không đổi GSAP, Reverb, quyền truy cập hoặc các quy tắc đấu giá/đơn hàng/thanh toán/kiểm duyệt.

So sánh snapshot chỉ đọc của **22 bảng nghiệp vụ: giống hệt trước và sau kiểm tra**. Không tạo/xóa dữ liệu minh họa, không chạy migration hay `migrate:fresh`. Cookie/phiên/cache giới hạn truy cập là dữ liệu vận hành bình thường khi đăng nhập/duyệt.

## Nội dung giữ nguyên và giới hạn

- Giữ Secondhand, ZaloPay, VietQR, tên thương hiệu/sản phẩm, JPEG/PNG/WebP, MP4/WebM và đơn vị kỹ thuật MiB. Đây là tên riêng/định dạng/đơn vị, không phải nhãn miền nghiệp vụ chưa dịch.
- Giữ nguyên tên người dùng, mô tả, bình luận, tin nhắn, lý do kiểm duyệt và mã đơn. Một số dữ liệu minh họa cũ có tiếng Anh, ví dụ lý do `Local C1 demo approved` hoặc mã `DEMO-COMPLETED`; chúng là nội dung/mã định danh, không phải trạng thái chưa ánh xạ. Không tự dịch nội dung tự do vì có thể làm sai nghĩa và yêu cầu giữ nguyên dữ liệu.
- Hộp thoại hệ điều hành, thông báo kiểm tra HTML gốc, menu/tooltip trình phát và công cụ phát triển của trình duyệt phụ thuộc ngôn ngữ trình duyệt/hệ điều hành. Nút chọn tệp và các thông báo kiểm tra dữ liệu do ứng dụng hiển thị đã Việt hóa. Chưa kiểm tra thiết bị vật lý.
- Không thực hiện mới các giao dịch thanh toán bên ngoài hay thao tác kiểm duyệt để kiểm tra bản dịch; các trạng thái đó được bao phủ bằng frontend regression hiện có.
- Cây Git vẫn chứa thay đổi từ các giai đoạn trước; lượt này không commit/tag/push và không xóa thay đổi cũ.

## Danh sách file thay đổi trong lượt này

**71 file frontend** gồm 49 file nguồn/trình bày, 21 file test (20 cập nhật, một mới) và `frontend/index.html`; thêm báo cáo này thành **72 file**. Danh sách tính theo hash trước lượt Việt hóa, không gộp thay đổi lịch sử của các giai đoạn trước.

- `frontend/src/api/auth.api.js`
- `frontend/src/api/domain.api.js`
- `frontend/src/components/AdminModerationPanel.vue`
- `frontend/src/components/AuthForm.vue`
- `frontend/src/components/PaymentAttempts.vue`
- `frontend/src/components/ProductImagesEditor.vue`
- `frontend/src/components/ProductVideos.vue`
- `frontend/src/components/ProductVideosEditor.vue`
- `frontend/src/composables/auctionPresentation.js`
- `frontend/src/composables/orderPresentation.js`
- `frontend/src/composables/productPresentation.js`
- `frontend/src/composables/useFeedback.js`
- `frontend/src/composables/useMessaging.js`
- `frontend/src/composables/useVideoComments.js`
- `frontend/src/layouts/DefaultLayout.vue`
- `frontend/src/utils/labels.js`
- `frontend/src/utils/validationLabels.js`
- `frontend/src/views/AccountView.vue`
- `frontend/src/views/AdminCategoriesView.vue`
- `frontend/src/views/AdminDashboardView.vue`
- `frontend/src/views/AdminOrdersView.vue`
- `frontend/src/views/AdminProductsView.vue`
- `frontend/src/views/AdminShopsView.vue`
- `frontend/src/views/AuctionArenaView.vue`
- `frontend/src/views/AuctionCheckoutView.vue`
- `frontend/src/views/CheckoutView.vue`
- `frontend/src/views/ForbiddenView.vue`
- `frontend/src/views/ForgotPasswordView.vue`
- `frontend/src/views/HomeView.vue`
- `frontend/src/views/OrderView.vue`
- `frontend/src/views/ProductsView.vue`
- `frontend/src/views/ProfileView.vue`
- `frontend/src/views/ResetPasswordView.vue`
- `frontend/src/views/SellerAuctionsView.vue`
- `frontend/src/views/SellerDashboardView.vue`
- `frontend/src/views/SellerProductsView.vue`
- `frontend/src/views/SellerShopView.vue`
- `frontend/src/views/ShopView.vue`
- `frontend/src/__tests__/AdminPhase11.spec.js`
- `frontend/src/__tests__/AdminUI.spec.js`
- `frontend/src/__tests__/App.spec.js`
- `frontend/src/__tests__/AuctionCheckout.spec.js`
- `frontend/src/__tests__/auth.spec.js`
- `frontend/src/__tests__/AuthForm.spec.js`
- `frontend/src/__tests__/CartPhase7.spec.js`
- `frontend/src/__tests__/DomainViews.spec.js`
- `frontend/src/__tests__/Messaging.spec.js`
- `frontend/src/__tests__/OrderPhase8.spec.js`
- `frontend/src/__tests__/PasswordRecovery.spec.js`
- `frontend/src/__tests__/PaymentPhase12.spec.js`
- `frontend/src/__tests__/ProductPhase6.spec.js`
- `frontend/src/__tests__/ProductVideos.spec.js`
- `frontend/src/__tests__/QuickListing.spec.js`
- `frontend/src/__tests__/ReviewPhase9.spec.js`
- `frontend/src/__tests__/SellerDashboardPhase10.spec.js`
- `frontend/src/__tests__/VideoComments.spec.js`
- `frontend/src/__tests__/VideoDiscovery.spec.js`
- `frontend/src/__tests__/VietnameseLocalization.spec.js`
- `frontend/src/__tests__/VisualConsistency.spec.js`
- `frontend/src/components/admin/AdminShell.vue`
- `frontend/src/components/chat/ChatSellerButton.vue`
- `frontend/src/components/home/HomeDiscovery.vue`
- `frontend/src/components/home/HomeScrollStory.vue`
- `frontend/src/components/home/VideoReviewCard.vue`
- `frontend/src/components/home/VideoTheater.vue`
- `frontend/src/components/seller/SellerProductEditor.vue`
- `frontend/src/components/seller/SellerShell.vue`
- `frontend/src/components/ui/AccessibleDialog.vue`
- `frontend/src/components/ui/PageHeader.vue`
- `frontend/src/components/ui/StatusBadge.vue`
- `frontend/index.html`
- `docs/ui-vietnamese-localization-verification.md`

**Kết luận: PASS — giao diện do ứng dụng kiểm soát đã Việt hóa; backend, dữ liệu và quy tắc nghiệp vụ được bảo toàn. Những nội dung bên ngoài phạm vi bản dịch được nêu rõ ở trên.**
