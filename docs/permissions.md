# Permissions — authorization matrix

> PHASE 11 đã có các chức năng PHASE1–10 cùng Admin Dashboard, User status management, Review moderation và Order overview read-only. Admin role/archive/restore, Payment management và ngoại lệ workflow vẫn future. Shop Owner là User; quyền Admin không cho giả danh Buyer/Seller.

Ký hiệu: Có = được phép theo guards; Riêng = chỉ record của mình; Quản trị = endpoint admin, reason/audit bắt buộc với mutation quan trọng; Không = từ chối. Tất cả mutation yêu cầu tài khoản ACTIVE chưa soft-delete; request vẫn phải pass CSRF/validation/state/stock.

| Action | Guest | User/Buyer | Shop Owner | Admin |
|---|---|---|---|---|
| Xem Product ACTIVE và Shop public | Có | Có | Có | Có |
| Register/login | Có | Không cần khi đã login | Không cần | Không cần |
| Logout/me | Không | Riêng | Riêng | Riêng |
| Sửa profile / CRUD Address | Không | Riêng | Riêng | Riêng; không dùng admin để sửa Address người khác |
| Thêm/sửa/xóa cart item | Không | Riêng | Riêng, cấm tự mua | Riêng, cấm tự mua |
| Checkout | Không | Riêng | Riêng, cấm tự mua | Riêng, cấm tự mua |
| Xem Order Buyer | Không | Riêng | Chỉ Order mình mua | Quản trị read-only overview |
| Hủy ShopOrder với tư cách Buyer | Không | Order của mình, PENDING/CONFIRMED | Tương tự Buyer | Tương tự nếu là Buyer; ngoại lệ qua admin endpoint |
| Tạo Shop | Không | Có nếu chưa có | Không tạo Shop thứ hai | Có nếu chưa có |
| Sửa hồ sơ Shop / submit lại | Không | Không nếu không owner | Riêng | Chỉ Shop mình; moderation riêng |
| Duyệt/tạm ngưng/từ chối Shop | Không | Không | Không | Quản trị, không tự duyệt Shop mình |
| Xem Product chưa public | Không | Không | Riêng qua seller route | Quản trị |
| Tạo/sửa/submit/ẩn/soft-delete Product | Không | Không nếu không owner | Riêng + Shop guards | Chỉ khi là owner; admin chỉ moderate/archive, không sửa giá thay seller |
| Upload/chọn primary/sắp xếp/xóa ảnh | Không | Không | Riêng | Chỉ khi là owner |
| Moderate Product | Không | Không | Không | Quản trị, không tự duyệt hàng mình |
| Xem ShopOrder fulfillment | Không | Xem trong Order mình mua | Riêng Shop; không đọc phần Shop khác | Quản trị |
| Chuyển trạng thái ShopOrder | Không | Chỉ cancel đủ điều kiện | Riêng, forward/cancel guards | Quản trị xử lý thay, reason |
| Xác nhận COD đã thu | Không | Không | Qua complete ShopOrder SHIPPING | Qua complete xử lý thay; không PATCH payment tùy ý |
| Xem payment history | Không | Order của mình, public-safe fields | Không; chỉ xem COD fulfillment phần mình | Quản trị, không trả secret/raw metadata |
| Review Product | Không | OrderItem đã COMPLETED của mình | Như Buyer, không tự review hàng mình | Như Buyer, không bypass chứng cứ mua |
| Sửa Review | Không | Riêng rating/comment | Chỉ review mình viết | Chỉ review mình viết, moderation riêng |
| Ẩn/hiện Review | Không | Không | Không | Quản trị, reason; không sửa rating/comment |
| Quản lý Category | Không | Không | Không | Quản trị |
| Khóa/kích hoạt User | Không | Không | Không | Quản trị status; cấm self/last-active-admin; không role/archive/restore trong PHASE11 |
| Seller revenue | Không | Không nếu không Shop | Riêng Shop | Quản trị overview; dữ liệu Shop khi cần |
| Dashboard toàn hệ thống / audit read | Không | Không | Không | Quản trị |
| Xóa/sửa snapshot Order hoặc Payment history | Không | Không | Không | Không |

## Policy và scoping dự kiến

- `ShopPolicy`: create/update/submit; owner_id từ authenticated user, không nhận từ client. `ProductPolicy`/`ProductImagePolicy`: product.shop.owner_id = user.id và Shop state hợp lệ. Image ownership luôn qua Product, không chỉ so image.id.
- `AddressPolicy`, `CartItemPolicy`: user_id/cart.user_id là current user; route model binding scoped và explicit policy. Không cho truyền cart_id/user_id tùy ý.
- `OrderPolicy`: buyer_id = current user cho buyer route. `ShopOrderPolicy`: buyer path dùng Order.buyer_id, seller path dùng Shop.owner_id. Seller chỉ được payload phần Shop mình, không bao gồm ShopOrders khác hoặc tổng Cart trước checkout.
- `PaymentPolicy`: buyer Order hoặc Admin; Seller không xem attempts toàn Order, response redaction; đổi state chỉ trong action business tương ứng, không `update` mass assignment.
- `ReviewPolicy`: OrderItem.shopOrder.order.buyer_id, ShopOrder COMPLETED, chưa review; update author. Rating/product_id/user_id không nhận ngoài trường cho phép.
- Admin middleware role ADMIN + policies riêng cho moderation/exception, audit reason. Không dùng `Gate::before` trả true cho mọi hành động admin vì sẽ bỏ qua invariants. Role không tự cho phép mua hàng của mình hoặc sửa giá snapshot.
- `UserPolicy`: admin role/status/archive/restore, self/last-active-admin protection dưới lock. User không tự đổi email/role/status trong profile PATCH.

## HTTP authorization và chống rò dữ liệu

Chưa authenticated →401; CSRF fail →419; authenticated nhưng sai owner/action →403; record không tồn tại →404. Public Product/Shop không đủ điều kiện →404 để không expose hàng chờ. Seller A gửi ID tồn tại thuộc Seller B vào seller order endpoint →403 như scenario F; response chỉ message chung, không chứa thông tin đơn. List luôn scope ở query, không lấy toàn bộ rồi lọc trên frontend.

Record soft-deleted mặc định không bind trong public/seller mutation (404); admin archive/restore dùng withTrashed có policy; order history dùng snapshot ngay cả khi Product bị xóa mềm. Buyer không thể đổi buyer_id/order_id/shop_id trong body để vượt policy.

Suspended User bị chặn protected access trừ logout; Shop suspended chặn seller mutations, admin thực hiện nghĩa vụ đơn còn mở. Suspended Shop owner vẫn là Buyer nếu account ACTIVE. Seller fulfillment resource nhận địa chỉ/phone người nhận cần thiết cho ShopOrder mình; không nhận email đăng nhập, địa chỉ đã lưu khác, payment attempts toàn Order hoặc admin audit nội bộ. Public shop owner chỉ có tên hiển thị, không PII.

## Audit boundary

Mọi admin role/status/moderation/archive/restore/exception phải có reason và audit cùng transaction. Không tạo endpoint sửa/xóa audit. Đọc audit chỉ Admin, metadata đã lọc. Permissions vẫn kiểm backend kể cả frontend đã có route guard. Xem [State transitions](state-transitions.md) cho các guards không thể bypass bằng role.

## Phạm vi đã thực thi PHASE 4

Register/login chỉ tạo session web của tài khoản ACTIVE chưa soft-delete. Register luôn USER/ACTIVE; payload tự đặt role/status bị422. Guest me/logout bị401. Me kiểm tra ACTIVE ở mỗi request; tài khoản bị suspended sau login bị403, vẫn có thể logout để hủy session. Vue hiển thị đường đăng xuất cho session này, không vòng lặp tự gọi me/logout.

Cookie/session là nguồn xác thực; route guards chỉ hỗ trợ UX. Backend auth:sanctum bảo vệ me/logout, EnsureSpaSession chặn auth mutation không có stateful session. Không thêm HasApiTokens/createToken để phát token cho SPA; không triển khai admin/seller policies ngoài scope phase này.

## Phạm vi đã thực thi PHASE 5

AddressPolicy kiểm tra cả ACTIVE và user_id cho view/update/delete/setDefault; kể cả ADMIN cũng không đọc hoặc sửa Address người khác. List chỉ query quan hệ addresses của current User. Profile không nhận target user ID và luôn thao tác current User.

ShopPolicy tách view/update/submit own Shop khỏi viewAny/viewAdmin/moderate cho ADMIN. Admin không được sửa Shop người khác qua seller API hoặc tự kiểm duyệt Shop của mình. New Shop owner lấy từ session; protected endpoint không chấp nhận owner/status/slug/media injection. Admin có thể đọc archived, không mutate archived trong phase này.

CategoryPolicy + EnsureAdmin chặn USER ở mọi admin route. Service kiểm lại actor ACTIVE dưới lock trước writes. Không có Gate::before bypass. Private API có auth:sanctum, EnsureActiveAccount, EnsureSpaSession và CSRF; frontend requiresAdmin chỉ phục vụ điều hướng.

Resource whitelist tách public Shop và private managed Shop; Address không trả user_id, public Shop không trả moderation note/owner ID. Các paths avatar/logo không nhận từ client. Audit không lưu toàn bộ profile/address/body hoặc credential.

## Phạm vi đã thực thi PHASE 6

ProductPolicy kiểm tra sở hữu qua Shop cho view/update/delete/submit/hide; ProductImagePolicy kiểm tra qua Product. Shop PENDING/ACTIVE được tạo DRAFT/sửa, chỉ ACTIVE mới submit; Shop SUSPENDED/REJECTED chỉ xem seller data. Không role SELLER, không admin bypass ownership. Admin xem tất cả status và archived qua endpoint riêng, chỉ moderate record chưa xóa, không Shop mình. Public query còn kiểm owner ACTIVE, stock>0 và category ancestors ACTIVE. Mọi mutation revalidate quyền dưới row lock, strict field allowlist; primary/reorder/delete không nhận arbitrary storage path. Audit cùng transaction cho moderation và thay đổi seller/ảnh, không chứa binary/secrets.

## Phạm vi đã thực thi PHASE 11

9 endpoints dashboard/users/reviews/orders có EnsureAdmin + active account + Sanctum/SPA session; policy vẫn kiểm backend. UserPolicy::viewAny/moderate bảo vệ quản lý trạng thái, self/archived bị403; kiểm lại actor/target dưới account locks. ReviewPolicy bổ sung viewAdmin/moderate; OrderPolicy bổ sung viewAdmin, không thay buyer ownership. Không Gate::before bypass.

Mọi User/Review status mutation bắt buộc expected_status + reason, audit cùng transaction. No-op/stale409, extra fields422. Suspend không làm mất lịch sử; phiên đang có bị403 protected requests, vẫn logout được. Role User/Shop không đổi ngầm. Archived User chỉ đọc; không hard-delete.

Admin OrderResource tách khỏi Buyer/Seller actions; không trả shipping PII/Payment attempts hoặc hành động review/cancel/update. User management được đọc email để tìm tài khoản, không sửa email/mật khẩu/role. Dashboard recent users không trả email. Review moderation chỉ status, không sửa nội dung. Các ngoại lệ Admin xử lý ShopOrder còn mở khi Seller bị khóa nêu trong thiết kế chưa có API ở PHASE11.
