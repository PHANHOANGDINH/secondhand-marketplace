# D6 Vouchers — verification

Ngày 2026-10-14, PHP 8.4.25 (Herd). Các quy tắc nghiệp vụ do chủ dự án chốt trong đề bài D6; tài liệu này ghi cách hiện thực, thứ tự khóa, kết quả kiểm chứng và những chỗ tôi tự quyết.

## Kết quả: PASS

| Hạng mục | Kết quả |
| --- | --- |
| Backend đầy đủ (`secondhand_marketplace_test`) | 475 test / 5.493 assertions PASS (trước D6: 460 / 5.137) |
| Pint | PASS |
| Frontend vitest | 479 test / 39 file PASS (trước D6: 472 / 38) |
| oxlint, ESLint (không `--fix`), build | PASS |
| DB dev | mysqldump → `.runtime/d6-backup.sql`; snapshot trước/sau: 24 bảng nghiệp vụ không đổi số dòng và mã băm; thêm 2 bảng, 4 cột, 9 CHECK |
| Trình duyệt thật | CHƯA kiểm tra cho D6; giao diện mới chỉ có test component |

## Schema (additive, `2026_10_14_090000_create_vouchers_tables`)

- `vouchers`, `voucher_redemptions`: đúng danh sách cột trong đề; thêm CHECK cho enum, `SHOP ⇔ shop_id`, `value > 0`, PERCENT ≤ 100, `max_discount` chỉ cho PERCENT, `ends_at > starts_at`, giới hạn ≥ 1. Index `(voucher_id, user_id, released_at)`.
- `shop_orders`: thêm `discount_shop`, `discount_platform` (mặc định 0), `voucher_code` (snapshot). Cột `discount_amount` có sẵn được giữ làm tổng hai khoản, kèm CHECK `discount_amount = discount_shop + discount_platform`. Trước khi thêm CHECK đã xác minh trên DB dev: 0/9 `shop_orders` và 0 `orders` có `discount_amount ≠ 0`.
- `orders`: dùng lại `discount_amount` (tổng giảm) và `total_amount` (sau giảm) có sẵn; thêm `platform_voucher_code` (snapshot).
- `down()` rollback được: bộ test `DatabaseMigrations` (6 test race checkout, có test voucher) chạy qua rollback này.

## Cách tính (BCMath, làm tròn xuống đồng)

1. Mã SHOP: base = subtotal của ShopOrder. PERCENT = `floor(min(base × value / 100, max_discount, base))`; FIXED = `floor(min(value, base))`.
2. Mã PLATFORM: base = Σ(subtotal − giảm SHOP). `min_order` so với base của chính mã đó.
3. Chia PLATFORM: `share_i = floor(D × base_i / base)`; số đồng dư cộng vào ShopOrder tạo sau cùng (id lớn nhất). Tổng các phần chia luôn bằng D.
4. Ví dụ có trong test: hai shop 100.000 và 200.000; mã shop 10% tối đa 5.000 → 5.000; mã sàn 10.000 trên base 95.000 + 200.000 → 3.220 và 6.779, dư 1 → 6.780. Tổng đơn 285.000.

## Thứ tự khóa

- **Checkout:** tài khoản (theo id) → giỏ → đơn cùng idempotency key → địa chỉ → cart item → shop → danh mục → sản phẩm → ảnh chính → **voucher (theo id tăng dần)**. Sau khi khóa voucher mới kiểm tra lại toàn bộ điều kiện, rồi tạo đơn, ghi redemption và tăng `used_count`.
- **Hủy ShopOrder:** tài khoản → đơn → các ShopOrder → shop → danh mục → sản phẩm → payment → **voucher (theo id)**.
- Voucher luôn được khóa cuối ở cả hai luồng nên không tạo chu trình khóa với các luồng cũ.
- Preview không khóa và không ghi gì.

## Quy tắc ↔ test (`VoucherTest`, 14 test; `CheckoutConcurrencyTest`, 1 test)

| Quy tắc | Test |
| --- | --- |
| 1 đấu giá từ chối mã | `test_auction_checkout_rejects_vouchers` |
| 2, 3, 5, 9, 11 tính, chia, snapshot | `test_shop_and_platform_vouchers_price_split_and_snapshot_an_order` |
| 3, 5 làm tròn, số dư | `test_discounts_round_down_to_whole_dong_and_never_exceed_the_base` |
| 4 đơn tối thiểu | `test_minimum_order_is_checked_against_each_vouchers_own_base` |
| 6 không dùng được | `test_unusable_vouchers_are_rejected_with_a_vietnamese_reason_and_nothing_is_written` |
| 7 idempotency | `test_idempotency_covers_the_voucher_codes` |
| 7 race `usage_limit = 1` | `test_real_mysql_voucher_with_one_use_left_is_redeemed_by_exactly_one_concurrent_checkout` (4 tiến trình → đúng 1 đơn; hủy từ 2 tiến trình → release 1 lần) |
| Mua ngay + voucher | `test_buy_now_with_vouchers_prices_only_the_selected_lines` |
| 8 hủy và release | `test_cancellation_releases_shop_vouchers_per_part_and_the_platform_voucher_only_when_all_parts_are_cancelled` |
| 9, 10 ZaloPay, doanh thu | `test_zalopay_is_charged_the_discounted_total_and_seller_revenue_stays_the_subtotal` |
| 12, CRUD, IDOR | `test_sellers_manage_only_their_own_shop_vouchers`, `test_a_used_voucher_keeps_its_terms_and_admins_manage_platform_vouchers` |
| D7 với đơn có mã | `test_recommendations_still_count_orders_placed_with_a_voucher` |
| Không N+1, rate limit | `test_voucher_lists_and_preview_use_a_constant_number_of_queries_and_preview_is_rate_limited` |
| Preview | `test_preview_requires_sign_in_and_validates_its_input` |
| Seeder demo | `FinalDemoSeederTest` (2 mã `PASSLAI10`, `CONGNGHE20K`, chạy lại không tạo thêm) |

Frontend: `Vouchers.spec.js` (7 test) cho ô mã ở checkout, tổng tiền, Mua ngay, chi tiết đơn, màn quản lý của seller và admin.

## API

- `POST /checkout` và `POST /checkout/preview`: thêm `platform_voucher` (chuỗi) và `shop_vouchers` (`{shop_id: mã}`). Lỗi của checkout trả 422 theo khóa `platform_voucher` hoặc `shop_vouchers.{shop_id}`; preview trả 200 kèm `errors` theo từng mã, bằng tiếng Việt.
- `GET|POST /seller/vouchers`, `PATCH|DELETE /seller/vouchers/{id}`: chỉ mã của gian hàng mình; id của shop khác trả 404.
- `GET|POST /admin/vouchers`, `PATCH|DELETE /admin/vouchers/{id}`: admin xem mọi mã, chỉ tạo/sửa mã PLATFORM.
- Preview giới hạn 30 lần/phút mỗi người dùng.

## Ghi chú kế toán (quy tắc 10)

Doanh thu seller trên dashboard vẫn là `SUM(subtotal)` của ShopOrder hoàn tất, không trừ mã. Phần giảm SHOP do gian hàng chịu, phần giảm PLATFORM do sàn chịu. Hai con số này được lưu ở `shop_orders.discount_shop` và `discount_platform`, nhưng **báo cáo đối soát giữa sàn và gian hàng nằm ngoài phạm vi D6**.

## Chỗ tôi tự quyết (ngoài đề bài)

1. **Mỗi ShopOrder sau giảm phải lớn hơn 0 ₫.** Bảng `payments` có CHECK `amount > 0`, nên mã làm một ShopOrder về 0 bị từ chối với lỗi tiếng Việt thay vì tạo đơn 0 đồng.
2. **Số dư khi chia PLATFORM** vào ShopOrder cuối; nếu ShopOrder đó không còn đủ base thì phần còn lại chuyển sang ShopOrder ngay trước. Trường hợp này chỉ xảy ra khi base của shop cuối nhỏ hơn số đồng dư.
3. **Mã không phân biệt hoa thường khi nhập**; hash idempotency dùng mã đã viết hoa.
4. **Đổi `status` của mã cần `expected_status`** (409 khi lệch). `DELETE` chuyển mã sang `INACTIVE` và gọi lại nhiều lần vẫn cho cùng kết quả. Không yêu cầu `reason` cho thao tác trên mã.
5. **"Đã có redemption"** tính cả redemption đã release: mã từng được dùng thì không đổi được `code`, `type`, `value`, `min_order`, `max_discount`, `per_user_limit`, `starts_at`.
6. **Đấu giá** từ chối cả `cart_item_ids`. Trước D6, tham số này (thêm ở phần Mua ngay) bị bỏ qua lặng lẽ ở checkout đấu giá vì request đấu giá kế thừa request thường; nay trả 422.
7. **Factory `ShopOrder`** tự gán `discount_shop = discount_amount` để các fixture cũ chỉ đặt `discount_amount` vẫn thỏa CHECK mới.
8. **Người bán thấy cả phần giảm từ mã sàn** trên chi tiết đơn bán của mình.

## Giới hạn

- Chưa có kiểm tra trình duyệt cho ô mã ở checkout và hai trang quản lý mã.
- Không có mã miễn phí vận chuyển, mã theo danh mục/sản phẩm, hay nhiều mã PLATFORM cộng dồn.
- Giỏ hàng chưa hiển thị mã; mã chỉ nhập ở bước thanh toán.
- `FinalDemoSeeder` đã thêm hai mã demo nhưng chưa chạy trên DB dev (cấm `db:seed`), nên DB dev hiện chưa có mã nào.
