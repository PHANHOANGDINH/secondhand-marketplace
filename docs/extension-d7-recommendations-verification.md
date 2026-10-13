# D7 Recommendations — verification

Ngày 2026-10-13, PHP 8.4.25 (Herd). Làm trực tiếp trên commit D5 (`eb0b135`); D6 được bỏ qua theo quyết định của chủ dự án. Công thức tính điểm: [recommendation-scoring.md](recommendation-scoring.md).

## Kết quả: PASS

| Hạng mục | Kết quả |
| --- | --- |
| Backend đầy đủ (`secondhand_marketplace_test`) | 455 test / 5.059 assertions PASS (trước D7: 444 / 4.950) |
| Frontend vitest | 456 test / 37 file PASS (trước D7: 449 / 36) |
| Pint, oxlint, ESLint (không `--fix`), build | PASS |
| DB dev | mysqldump → `.runtime/d7-backup.sql`; snapshot trước/sau: 24 bảng nghiệp vụ không đổi số dòng và mã băm; thêm `product_views`, `search_logs` |
| Trình duyệt thật | CHƯA kiểm tra; giao diện mới chỉ có test component |

## Phạm vi đã giao

- Migration additive `2026_10_13_090000_create_product_views_and_search_logs_tables`: hai bảng mới (không có cột IP) và index `(product_id, created_at)` trên `wishlists`, `order_items`. `down()` rollback được.
- `RecommendationService` (xếp hạng, sản phẩm tương tự), `RecommendationSignals` (ghi lượt xem, tìm kiếm), `config/recommendations.php` (toàn bộ trọng số).
- API: `GET /recommendations?limit=12`, `GET /products/{slug}/similar?limit=8`; `ProductResource` thêm `reason_label` khi có.
- UI: `ProductSuggestions.vue` dùng ở trang chủ ("Gợi ý cho bạn") và trang chi tiết ("Sản phẩm tương tự"); `ProductCard` hiển thị nhãn lý do; skeleton khi tải, ẩn khi rỗng hoặc lỗi.

## Test theo yêu cầu đề bài (`RecommendationTest`, 11 test)

| Yêu cầu | Test |
| --- | --- |
| Thứ tự xác định với fixture, tie-break theo id | `test_order_is_deterministic_and_explained_for_a_fixture` |
| Loại trừ shop của mình, không public, SOLD | `test_own_shop_non_public_and_sold_products_are_excluded` |
| Cold start | `test_cold_start_uses_only_popularity_and_recency` |
| Dedupe lượt xem 30 phút, không ghi cho guest | `test_product_views_are_logged_once_per_window_for_signed_in_users_only` |
| Không ghi log tìm kiếm cho guest | `test_searches_are_logged_for_signed_in_users_only` |
| Số query không tăng theo số sản phẩm | `test_query_count_does_not_grow_with_the_number_of_products` |
| Cache đúng user, không lẫn | `test_cache_is_per_user_and_never_leaks_to_another_user_or_guest` |
| Sản phẩm tương tự | `test_similar_products_share_category_or_parent_within_half_price_and_prefer_the_same_shop` |

Ngoài ra: trọng số tín hiệu và danh mục cha, khớp từ khóa, sản phẩm vừa bị ẩn biến mất khỏi kết quả đã cache. Frontend: 7 test trong `Recommendations.spec.js`; `App.spec.js` chỉ thêm stub cho khu mới.

## Sự cố trong lúc làm

- Lần chạy suite đầu fail 123 test: `down()` không xóa được index đang phục vụ khóa ngoại (MySQL 1553), làm hỏng các test dùng `DatabaseMigrations`. Đã sửa `down()` (tạo lại index khóa ngoại trước khi xóa index ghép). Lúc đó DB dev chưa migrate.
- Một lần chạy lại bị nhiễu do tiến trình test cũ chưa dừng hẳn; đã dừng đúng hai tiến trình đó rồi chạy lại sạch.

## Quyết định đã được chủ dự án xác nhận

1. Log tìm kiếm chỉ ghi ở trang 1; tính điểm dùng 5 truy vấn khác nhau gần nhất.
2. Không ghi lượt xem khi chủ shop xem sản phẩm của chính mình.
3. Danh mục cha, con và anh em nhận một nửa điểm tương tác.
4. Sản phẩm tương tự: chỉ cùng danh mục hoặc danh mục cha trực tiếp.
5. `limit` nhận 1–24 cho cả hai endpoint.
6. Không xóa cache khi có tín hiệu mới (độ trễ tối đa 10 phút).
7. Migrate DB dev theo quy trình backup → snapshot → migrate → snapshot.

## Giới hạn còn lại

- Chưa có kiểm tra trình duyệt ở 4 breakpoint cho hai khu mới.
- Không collaborative filtering, không đa dạng hóa kết quả; khớp từ khóa phân biệt dấu tiếng Việt.
- `backend/CLAUDE.md` vẫn là mẫu mặc định của Laravel Boost và mâu thuẫn với `CLAUDE.md` gốc (xem tin nhắn bàn giao); chưa sửa.
