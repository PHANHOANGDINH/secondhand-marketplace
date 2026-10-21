# Passdi: đặc tả mô hình dữ liệu mức khái niệm (ERD ký hiệu Chen/Merise)

Đặc tả này đủ để vẽ lại sơ đồ bằng công cụ khác (draw.io, PowerDesigner, Visio, StarUML, Lucidchart, AI sinh hình) mà không cần mở CSDL.

- **Nguồn:** CSDL `secondhand_marketplace`, đọc ngày 2026-10-21.
- **Bản vẽ mẫu:** các file `*.png` cùng thư mục.

---

## 1. Quy ước vẽ

1. **Thực thể** là hình chữ nhật có hai phần:
   - phần đầu ghi tên thực thể in hoa (ví dụ `NGUOI_DUNG`), có thể kèm tên bảng in nghiêng (`users`);
   - phần thân ghi danh sách thuộc tính, mỗi dòng một thuộc tính.
2. **Khóa chính** được gạch chân. Mọi thực thể đều có khóa chính `id`.
3. **Không ghi khóa ngoại trong thực thể.** Mỗi khóa ngoại được vẽ thành một mối kết hợp (mục 4).
4. **Mối kết hợp** là hình bầu dục, ghi tên bằng động từ tiếng Việt. Nối mỗi đầu của mối kết hợp với một thực thể bằng một đường thẳng không mũi tên.
5. **Mối kết hợp có thuộc tính** vẫn là hình bầu dục: tên ở trên, kẻ một vạch ngang, thuộc tính ở dưới.
6. **Bản số `min,max`** ghi sát đầu thực thể trên đường nối: số lần một thể hiện của thực thể đó tham gia mối kết hợp.
   - Các giá trị dùng: `0,1`, `1,1`, `0,n`, `1,n`, `2,2`.
   - Ví dụ: `NGUOI_DUNG (0,1) — Sở hữu — (1,1) GIAN_HANG` nghĩa là mỗi người dùng có không hoặc một gian hàng, và mỗi gian hàng thuộc đúng một người dùng.
7. **Mối kết hợp đệ quy** (một thực thể với chính nó): vẽ hai đường riêng và ghi vai trò trước bản số, ví dụ `cha 0,n` và `con 0,1`.
8. **Chuyên biệt hóa:** từ thực thể cha kẻ xuống một vòng tròn ghi `d` (rời nhau), rồi từ vòng tròn tỏa ra các thực thể con. Ghi nhãn "Phân loại theo role".
9. **Thực thể xuất hiện ở nhiều sơ đồ:** chỉ ghi đủ thuộc tính ở sơ đồ chính, các sơ đồ khác ghi `id`, vài thuộc tính chính và `…`.
10. **Màu gợi ý (không bắt buộc):**
    - thực thể nền trắng, đầu xanh bạc hà `#D7EFE6`, viền `#12302A`;
    - mối kết hợp nền `#FFF7E8`, viền cam `#B45309`;
    - đường nối `#1F6F5C`.

---

## 2. Thực thể (25)

Mỗi dòng có dạng `thuộc tính : kiểu — ý nghĩa`. Ký hiệu `?` nghĩa là được phép rỗng. Thuộc tính `created_at` / `updated_at` có thể lược bớt khi vẽ, trừ các chỗ ghi "nên giữ".

### Nhóm A · Tài khoản, gian hàng, sản phẩm (sơ đồ 1)

**NGUOI_DUNG** (`users`): tài khoản trên sàn. Người mua và người bán đều là người dùng; người bán là người dùng có gian hàng.
- <u>id</u> : bigint — mã người dùng
- name : varchar(100) — họ tên
- email : varchar(254), duy nhất — email đăng nhập
- email_verified_at? : timestamp — thời điểm xác minh email
- password : varchar(255) — mật khẩu đã băm
- temporary_password? : varchar(255) — mật khẩu tạm (đã băm) khi quên mật khẩu
- temporary_password_expires_at? : timestamp — hạn mật khẩu tạm
- must_change_password : boolean — bắt buộc đổi mật khẩu ở lần đăng nhập tới
- phone? : varchar(20) — số điện thoại
- avatar_path? : varchar(512) — ảnh đại diện
- role : varchar(16) — `USER` | `ADMIN`
- status : varchar(16) — `ACTIVE` | `SUSPENDED`
- deleted_at? : timestamp — xóa mềm

Chuyên biệt hóa theo `role` (cùng một bảng):
- **THANH_VIEN** (`role = USER`): mua, bán, nhắn tin, đấu giá.
- **QUAN_TRI_VIEN** (`role = ADMIN`): duyệt, xử lý báo cáo, đối soát thanh toán.

**DIA_CHI** (`addresses`): sổ địa chỉ nhận hàng của người dùng.
- <u>id</u> : bigint
- recipient_name : varchar(100) — tên người nhận
- phone : varchar(20)
- address_line : varchar(255) — số nhà, đường
- ward : varchar(100) — phường/xã
- district? : varchar(100) — quận/huyện
- province : varchar(100) — tỉnh/thành
- country_code : char(2) — mã quốc gia (`VN`)
- is_default : boolean — địa chỉ mặc định

**GIAN_HANG** (`shops`): cửa hàng của người bán.
- <u>id</u> : bigint
- name : varchar(150)
- slug : varchar(180), duy nhất — đường dẫn thân thiện
- description? : text
- province_code? : varchar(2) — mã tỉnh của gian hàng
- logo_path? : varchar(512)
- status : varchar(16) — `PENDING` | `ACTIVE` | `SUSPENDED` | `REJECTED`
- moderation_reason? : varchar(1000) — lý do từ chối/khóa
- deleted_at? : timestamp — xóa mềm

**DANH_MUC** (`categories`): danh mục sản phẩm, dạng cây.
- <u>id</u> : bigint
- name : varchar(100)
- slug : varchar(150), duy nhất
- description? : text
- status : varchar(16) — `ACTIVE` | `INACTIVE`

**SAN_PHAM** (`products`): tin đăng bán đồ cũ.
- <u>id</u> : bigint
- name : varchar(200)
- slug : varchar(240), duy nhất
- description : text
- price : decimal(15,2) — giá bán, lớn hơn 0
- compare_at_price? : decimal(15,2) — giá gốc để so sánh
- stock_quantity : int — tồn kho
- condition : varchar(16) — `LIKE_NEW` | `GOOD` | `FAIR` | `POOR`
- status : varchar(16) — `DRAFT` | `PENDING` | `ACTIVE` | `SOLD` | `HIDDEN` | `REJECTED`
- moderation_reason? : varchar(1000)
- published_at? : datetime — thời điểm được duyệt đăng
- deleted_at? : timestamp — xóa mềm

**ANH_SAN_PHAM** (`product_images`)
- <u>id</u> : bigint
- image_path : varchar(512)
- alt_text? : varchar(255)
- is_primary : boolean — ảnh đại diện
- sort_order : smallint — thứ tự hiển thị

**VIDEO_SAN_PHAM** (`product_videos`)
- <u>id</u> : bigint
- title? : varchar(200)
- description? : text
- path : varchar(255), duy nhất — tệp video
- mime_type : varchar(32)
- file_size : bigint — dung lượng (byte)
- duration_seconds? : int — thời lượng
- poster_path? : varchar(255) — ảnh bìa
- sort_order : int
- is_primary : boolean — video chính, mỗi sản phẩm tối đa 1
- status : varchar(16) — `PROCESSING` | `READY` | `HIDDEN` | `FAILED`

**BINH_LUAN_VIDEO** (`video_comments`)
- <u>id</u> : bigint
- body : text — nội dung
- status : varchar(16) — `PUBLISHED` | `HIDDEN`
- created_at : timestamp (nên giữ)

### Nhóm B · Giỏ hàng, đơn hàng, thanh toán, voucher (sơ đồ 2)

**GIO_HANG** (`carts`): mỗi người dùng có tối đa một giỏ.
- <u>id</u> : bigint
- created_at : datetime
- updated_at : datetime

**DON_HANG** (`orders`): một lần thanh toán của người mua; có thể gồm hàng của nhiều gian hàng.
- <u>id</u> : bigint
- order_code : varchar(32), duy nhất — mã đơn hiển thị
- checkout_key : char(36) — khóa chống đặt trùng (duy nhất theo người mua)
- request_hash : char(64) — băm nội dung yêu cầu đặt hàng
- Địa chỉ giao hàng, chụp lại tại lúc đặt:
  - shipping_recipient_name : varchar(100)
  - shipping_phone : varchar(20)
  - shipping_address_line : varchar(255)
  - shipping_ward : varchar(100)
  - shipping_district? : varchar(100)
  - shipping_province : varchar(100)
  - shipping_country_code : char(2)
- subtotal : decimal(15,2) — tổng tiền hàng
- shipping_fee : decimal(15,2) — phí vận chuyển
- discount_amount : decimal(15,2) — tổng giảm giá
- platform_voucher_code? : varchar(20) — mã voucher sàn đã áp
- total_amount : decimal(15,2) — số tiền phải trả
- currency : char(3) — `VND`
- payment_method : varchar(16) — `COD` | `ZALOPAY` | `VNPAY`
- created_at : datetime (nên giữ)

**DON_SHOP** (`shop_orders`): phần đơn của một gian hàng, có vòng đời riêng.
- <u>id</u> : bigint
- status : varchar(16) — `PENDING` | `CONFIRMED` | `PROCESSING` | `SHIPPING` | `COMPLETED` | `CANCELLED`
- subtotal : decimal(15,2)
- shipping_fee : decimal(15,2)
- discount_amount : decimal(15,2)
- discount_shop : decimal(15,2) — phần giảm do voucher shop
- discount_platform : decimal(15,2) — phần giảm do voucher sàn được phân bổ
- voucher_code? : varchar(20) — voucher shop đã áp
- total_amount : decimal(15,2)
- confirmed_at? : datetime
- processing_at? : datetime
- shipping_at? : datetime
- completed_at? : datetime
- cancelled_at? : datetime
- cancellation_reason? : varchar(1000)
- stock_restored_at? : datetime — thời điểm đã hoàn tồn kho khi hủy

**CHI_TIET_DON** (`order_items`): một dòng hàng; chụp lại tên, ảnh, giá lúc đặt.
- <u>id</u> : bigint
- product_name : varchar(200)
- product_image : varchar(512)
- product_price : decimal(15,2)
- quantity : smallint
- subtotal : decimal(15,2)

**THANH_TOAN** (`payments`): mỗi lần thử thanh toán của một đơn.
- <u>id</u> : bigint
- attempt_number : int — lần thử thứ mấy (duy nhất theo đơn)
- method : varchar(16) — `COD` | `ZALOPAY` | `VNPAY`
- amount : decimal(15,2)
- currency : char(3)
- status : varchar(16) — `PENDING` | `PAID` | `FAILED` | `CANCELLED` | `REFUNDED`
- transaction_reference : varchar(64), duy nhất — mã giao dịch phía sàn
- gateway_transaction_id? : varchar(100) — mã giao dịch phía cổng
- paid_at? : datetime
- failed_at? : datetime
- failure_code? : varchar(100)
- request_key? : char(36) — khóa chống tạo trùng
- order_url? : text — liên kết thanh toán của cổng
- preferred_payment_method? : json
- gateway_app_id? : int
- gateway_app_time? : bigint
- gateway_requested_at? : datetime
- expires_at? : datetime — hạn thanh toán
- reconciliation_required : boolean — cần đối soát tay
- reconciliation_resolved_at? : datetime
- reconciliation_note? : varchar(1000)

**VOUCHER** (`vouchers`): mã giảm giá của sàn hoặc của gian hàng.
- <u>id</u> : bigint
- code : varchar(20), duy nhất
- scope : varchar(16) — `PLATFORM` | `SHOP`
- type : varchar(16) — `PERCENT` | `FIXED`
- value : decimal(15,2) — phần trăm hoặc số tiền
- min_order : decimal(15,2) — giá trị đơn tối thiểu
- max_discount? : decimal(15,2) — mức giảm tối đa
- usage_limit? : int — tổng lượt dùng
- per_user_limit? : int — lượt dùng mỗi người
- used_count : int — đã dùng
- starts_at : datetime
- ends_at : datetime
- status : varchar(16) — `ACTIVE` | `INACTIVE`

**LUOT_DUNG_VOUCHER** (`voucher_redemptions`)
- <u>id</u> : bigint
- discount_amount : decimal(15,2) — số tiền thực giảm
- released_at? : datetime — thời điểm trả lại lượt khi đơn hủy
- created_at : datetime (nên giữ)

### Nhóm C · Đấu giá, đánh giá, báo cáo (sơ đồ 3)

**PHIEN_DAU_GIA** (`auctions`)
- <u>id</u> : bigint
- start_price : decimal(15,2) — giá khởi điểm
- current_price : decimal(15,2) — giá hiện tại
- bid_increment : decimal(15,2) — bước giá
- starts_at : datetime
- ends_at : datetime
- status : varchar(16) — `SCHEDULED` | `LIVE` | `ENDED` | `CANCELLED`
- winner_checkout_expires_at? : timestamp — hạn người thắng thanh toán
- winner_checkout_reminded_at? : timestamp — đã nhắc người thắng
- settled_at? : timestamp — thời điểm chốt phiên

**LUOT_TRA_GIA** (`bids`)
- <u>id</u> : bigint
- amount : decimal(15,2)
- created_at : timestamp (nên giữ)

**DANH_GIA** (`reviews`): đánh giá sản phẩm sau khi mua; mỗi dòng hàng được đánh giá tối đa 1 lần.
- <u>id</u> : bigint
- rating : tinyint — 1 đến 5 sao
- comment? : text
- status : varchar(16) — `PUBLISHED` | `HIDDEN`
- moderation_reason? : varchar(1000)
- created_at : datetime (nên giữ)

**BAO_CAO** (`reports`): báo cáo vi phạm.
- <u>id</u> : bigint
- target_type : varchar(20) — `PRODUCT` | `SHOP` | `VIDEO_COMMENT`
- target_id : bigint — mã đối tượng bị báo cáo
- target_name : varchar(255) — tên đối tượng lúc báo cáo
- reason : varchar(30) — `SCAM` | `COUNTERFEIT` | `MISLEADING` | `PROHIBITED_CONTENT` | `SPAM` | `HARASSMENT` | `INAPPROPRIATE_CONTENT` | `OTHER`
- description? : text
- status : varchar(20) — `PENDING` | `IN_REVIEW` | `RESOLVED` | `DISMISSED`
- handled_at? : timestamp
- admin_note? : text

### Nhóm D · Nhắn tin, thông báo, hành vi, nhật ký (sơ đồ 4)

**CUOC_TRO_CHUYEN** (`conversations`): hội thoại giữa một người mua và một người bán, có thể gắn với một sản phẩm.
- <u>id</u> : bigint
- context_key : varchar(80), duy nhất — khóa ngữ cảnh (cặp người mua, người bán, sản phẩm)
- last_message_at? : datetime
- created_at : timestamp

**TIN_NHAN** (`messages`)
- <u>id</u> : bigint
- body : varchar(1000)
- created_at : timestamp (nên giữ)

**THONG_BAO** (`notifications`)
- <u>id</u> : char(36) (UUID)
- type : varchar(255) — loại thông báo
- dedup_key : char(64), duy nhất — chống gửi trùng
- data : text (JSON) — nội dung
- read_at? : timestamp — đã đọc
- created_at : timestamp

**LUOT_XEM** (`product_views`): lịch sử xem sản phẩm, dùng cho gợi ý.
- <u>id</u> : bigint
- viewed_at : datetime

**LICH_SU_TIM_KIEM** (`search_logs`)
- <u>id</u> : bigint
- query : varchar(100) — từ khóa
- created_at : datetime

**NHAT_KY** (`audit_logs`): nhật ký kiểm toán mọi chuyển trạng thái quan trọng.
- <u>id</u> : bigint
- entity_type : varchar(40) — `orders`, `shop_orders`, `payments`, `products`, `shops`, `vouchers`, `users`
- entity_id : bigint
- action : varchar(80) — hành động
- from_status? : varchar(32)
- to_status? : varchar(32)
- reason? : varchar(1000)
- request_id? : char(36) — mã yêu cầu HTTP
- created_at : datetime

---

## 3. Mối kết hợp có thuộc tính (4, thay cho bảng nối)

| # | Tên | Đầu 1 (bản số) | Đầu 2 (bản số) | Thuộc tính | Bảng | Ràng buộc |
| --- | --- | --- | --- | --- | --- | --- |
| K1 | Chứa | GIO_HANG (0,n) | SAN_PHAM (0,n) | quantity | cart_items | duy nhất (cart_id, product_id) |
| K2 | Đánh giá (gian hàng) | NGUOI_DUNG (0,n) | GIAN_HANG (0,n) | rating, comment, status, moderation_reason | shop_reviews | mỗi người đánh giá một gian hàng tối đa 1 lần; chỉ người đã mua xong |
| K3 | Yêu thích | NGUOI_DUNG (0,n) | SAN_PHAM (0,n) | created_at | wishlists | duy nhất (user_id, product_id) |
| K4 | Tham gia | NGUOI_DUNG (0,n) | CUOC_TRO_CHUYEN (2,2) | last_read_message_id | conversation_participants | duy nhất (conversation_id, user_id) |

---

## 4. Mối kết hợp nhị phân (không thuộc tính)

Cột "Khóa ngoại" cho biết mối kết hợp được suy ra từ cột nào. Mỗi dòng vẽ thành `Đầu 1 —(bản số)— [Tên] —(bản số)— Đầu 2`.

### Sơ đồ 1

| # | Tên | Đầu 1 (bản số) | Đầu 2 (bản số) | Khóa ngoại |
| --- | --- | --- | --- | --- |
| R1 | Có | NGUOI_DUNG (0,n) | DIA_CHI (1,1) | addresses.user_id |
| R2 | Sở hữu | NGUOI_DUNG (0,1) | GIAN_HANG (1,1) | shops.owner_id (duy nhất) |
| R3 | Đăng bán | GIAN_HANG (0,n) | SAN_PHAM (1,1) | products.shop_id |
| R4 | Thuộc | DANH_MUC (0,n) | SAN_PHAM (1,1) | products.category_id |
| R5 | Là con của (đệ quy) | DANH_MUC vai "cha" (0,n) | DANH_MUC vai "con" (0,1) | categories.parent_id |
| R6 | Có ảnh | SAN_PHAM (0,n) | ANH_SAN_PHAM (1,1) | product_images.product_id |
| R7 | Có video | SAN_PHAM (0,n) | VIDEO_SAN_PHAM (1,1) | product_videos.product_id |
| R8 | Tải lên | NGUOI_DUNG (0,n) | VIDEO_SAN_PHAM (1,1) | product_videos.uploaded_by |
| R9 | Có (bình luận) | VIDEO_SAN_PHAM (0,n) | BINH_LUAN_VIDEO (1,1) | video_comments.product_video_id |
| R10 | Viết (bình luận) | NGUOI_DUNG (0,n) | BINH_LUAN_VIDEO (1,1) | video_comments.user_id |

Sơ đồ 1 còn có K2 (Đánh giá gian hàng) và chuyên biệt hóa NGUOI_DUNG → THANH_VIEN / QUAN_TRI_VIEN.

### Sơ đồ 2

| # | Tên | Đầu 1 (bản số) | Đầu 2 (bản số) | Khóa ngoại |
| --- | --- | --- | --- | --- |
| R11 | Có (giỏ) | NGUOI_DUNG (0,1) | GIO_HANG (1,1) | carts.user_id (duy nhất) |
| R12 | Đặt | NGUOI_DUNG (0,n) | DON_HANG (1,1) | orders.buyer_id |
| R13 | Tách thành | DON_HANG (1,n) | DON_SHOP (1,1) | shop_orders.order_id |
| R14 | Nhận (đơn) | GIAN_HANG (0,n) | DON_SHOP (1,1) | shop_orders.shop_id |
| R15 | Gồm | DON_SHOP (1,n) | CHI_TIET_DON (1,1) | order_items.shop_order_id |
| R16 | Được mua | SAN_PHAM (0,n) | CHI_TIET_DON (1,1) | order_items.product_id |
| R17 | Có (thanh toán) | DON_HANG (0,n) | THANH_TOAN (1,1) | payments.order_id |
| R18 | Đối soát | NGUOI_DUNG/QUAN_TRI_VIEN (0,n) | THANH_TOAN (0,1) | payments.reconciliation_resolved_by |
| R19 | Phát hành | GIAN_HANG (0,n) | VOUCHER (0,1) | vouchers.shop_id (rỗng = voucher sàn) |
| R20 | Tạo (voucher) | NGUOI_DUNG (0,n) | VOUCHER (1,1) | vouchers.created_by |
| R21 | Được dùng | VOUCHER (0,n) | LUOT_DUNG_VOUCHER (1,1) | voucher_redemptions.voucher_id |
| R22 | Dùng | NGUOI_DUNG (0,n) | LUOT_DUNG_VOUCHER (1,1) | voucher_redemptions.user_id |
| R23 | Áp dụng cho | DON_HANG (0,n) | LUOT_DUNG_VOUCHER (1,1) | voucher_redemptions.order_id |
| R24 | Giảm cho | DON_SHOP (0,n) | LUOT_DUNG_VOUCHER (0,1) | voucher_redemptions.shop_order_id |

Sơ đồ 2 còn có K1 (Chứa).

### Sơ đồ 3

| # | Tên | Đầu 1 (bản số) | Đầu 2 (bản số) | Khóa ngoại |
| --- | --- | --- | --- | --- |
| R25 | Được đấu giá | SAN_PHAM (0,n) | PHIEN_DAU_GIA (1,1) | auctions.product_id (tối đa 1 phiên đang mở mỗi sản phẩm) |
| R26 | Mở phiên | NGUOI_DUNG (0,n) | PHIEN_DAU_GIA (1,1) | auctions.seller_id |
| R27 | Thắng | NGUOI_DUNG (0,n) | PHIEN_DAU_GIA (0,1) | auctions.winner_user_id |
| R28 | Nhận (lượt trả) | PHIEN_DAU_GIA (0,n) | LUOT_TRA_GIA (1,1) | bids.auction_id |
| R29 | Trả giá | NGUOI_DUNG (0,n) | LUOT_TRA_GIA (1,1) | bids.user_id |
| R30 | Giá thắng | PHIEN_DAU_GIA (0,1) | LUOT_TRA_GIA (0,1) | auctions.winning_bid_id |
| R31 | Chốt thành | PHIEN_DAU_GIA (0,1) | DON_HANG (0,1) | auctions.winner_order_id (duy nhất) |
| R32 | Sinh ra | PHIEN_DAU_GIA (0,1) | CHI_TIET_DON (0,1) | order_items.auction_id (duy nhất) |
| R33 | Viết (đánh giá) | NGUOI_DUNG (0,n) | DANH_GIA (1,1) | reviews.user_id |
| R34 | Có (đánh giá) | SAN_PHAM (0,n) | DANH_GIA (1,1) | reviews.product_id |
| R35 | Được đánh giá | CHI_TIET_DON (0,1) | DANH_GIA (1,1) | reviews.order_item_id (duy nhất) |
| R36 | Gửi (báo cáo) | NGUOI_DUNG (0,n) | BAO_CAO (0,1) | reports.reporter_user_id (rỗng nếu tài khoản đã xóa) |
| R37 | Xử lý | NGUOI_DUNG/QUAN_TRI_VIEN (0,n) | BAO_CAO (0,1) | reports.handled_by |

### Sơ đồ 4

| # | Tên | Đầu 1 (bản số) | Đầu 2 (bản số) | Khóa ngoại |
| --- | --- | --- | --- | --- |
| R38 | Là người mua | NGUOI_DUNG (0,n) | CUOC_TRO_CHUYEN (1,1) | conversations.buyer_id |
| R39 | Là người bán | NGUOI_DUNG (0,n) | CUOC_TRO_CHUYEN (1,1) | conversations.seller_id |
| R40 | Hỏi về | SAN_PHAM (0,n) | CUOC_TRO_CHUYEN (0,1) | conversations.product_id |
| R41 | Chứa (tin nhắn) | CUOC_TRO_CHUYEN (0,n) | TIN_NHAN (1,1) | messages.conversation_id |
| R42 | Gửi (tin nhắn) | NGUOI_DUNG (0,n) | TIN_NHAN (1,1) | messages.sender_id |
| R43 | Nhận (thông báo) | NGUOI_DUNG (0,n) | THONG_BAO (1,1) | notifications.notifiable_id (đa hình, hiện chỉ User) |
| R44 | Xem | NGUOI_DUNG (0,n) | LUOT_XEM (1,1) | product_views.user_id |
| R45 | Được xem | SAN_PHAM (0,n) | LUOT_XEM (1,1) | product_views.product_id |
| R46 | Tìm kiếm | NGUOI_DUNG (0,n) | LICH_SU_TIM_KIEM (1,1) | search_logs.user_id |
| R47 | Thực hiện | NGUOI_DUNG (0,n) | NHAT_KY (0,1) | audit_logs.actor_id (rỗng = hệ thống) |

Sơ đồ 4 còn có K3 (Yêu thích) và K4 (Tham gia).

**Tổng: 25 thực thể, 4 mối kết hợp có thuộc tính, 47 mối kết hợp nhị phân, 1 chuyên biệt hóa.**

---

## 5. Liên kết đa hình (ghi chú, không vẽ đường)

- **BAO_CAO** trỏ tới SAN_PHAM, GIAN_HANG hoặc BINH_LUAN_VIDEO qua cặp (`target_type`, `target_id`). Nếu muốn vẽ, dùng ba đường nét đứt "Về" tới ba thực thể đó, bản số BAO_CAO (1,1), đối tượng (0,n), kèm ghi chú "chỉ một trong ba".
- **NHAT_KY** trỏ tới nhiều loại thực thể qua (`entity_type`, `entity_id`). Chỉ ghi chú, không vẽ đường.

## 6. Không đưa vào sơ đồ

- **9 bảng hạ tầng Laravel:** `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations`, `sessions`, `password_reset_tokens`, `personal_access_tokens`.
- **Cột kỹ thuật chỉ để ép ràng buộc duy nhất:**
  - `auctions.active_product_id`
  - `product_videos.primary_product_id`
  - `reports.open_slot`
  - `users.remember_token`

## 7. Bố cục gợi ý cho từng sơ đồ

- **Sơ đồ 1:**
  - NGUOI_DUNG ở giữa bên trái, DIA_CHI phía trên nó;
  - chuyên biệt hóa ngay dưới NGUOI_DUNG;
  - GIAN_HANG và DANH_MUC ở hàng trên, SAN_PHAM ở giữa;
  - ảnh, video, bình luận xếp thành cột bên phải.
- **Sơ đồ 2:**
  - hàng giữa từ trái sang phải: THANH_TOAN – DON_HANG – DON_SHOP – CHI_TIET_DON – SAN_PHAM;
  - NGUOI_DUNG và GIAN_HANG ở trên, GIO_HANG ở góc phải trên;
  - VOUCHER và LUOT_DUNG_VOUCHER ở dưới.
- **Sơ đồ 3:**
  - PHIEN_DAU_GIA ở giữa, NGUOI_DUNG ở trên;
  - SAN_PHAM bên trái, LUOT_TRA_GIA bên phải;
  - DON_HANG và CHI_TIET_DON ở dưới, DANH_GIA dưới cùng;
  - BAO_CAO ở cột phải.
- **Sơ đồ 4:**
  - NGUOI_DUNG ở tâm, các thực thể tỏa xung quanh;
  - nhắn tin ở trái, sản phẩm và lượt xem ở phải;
  - thông báo, tìm kiếm, nhật ký ở dưới.

## 8. Câu lệnh mẫu để giao cho công cụ AI vẽ

> Vẽ sơ đồ thực thể – mối kết hợp mức khái niệm theo ký hiệu Chen/Merise cho hệ thống chợ đồ cũ Passdi, đúng theo đặc tả đính kèm:
> - thực thể là hình chữ nhật có danh sách thuộc tính, khóa chính gạch chân, không ghi khóa ngoại;
> - mối kết hợp là hình bầu dục ghi động từ tiếng Việt;
> - bản số `min,max` ghi sát thực thể;
> - mối kết hợp có thuộc tính ghi thuộc tính dưới vạch ngang trong hình bầu dục;
> - chuyên biệt hóa NGUOI_DUNG thành THANH_VIEN / QUAN_TRI_VIEN bằng vòng tròn "d".
>
> Chia thành 4 sơ đồ theo mục 4 và bố cục theo mục 7.
