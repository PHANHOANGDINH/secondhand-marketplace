# D7 — Gợi ý sản phẩm: công thức tính điểm

Tài liệu dùng khi bảo vệ. Mã nguồn: `backend/app/Services/RecommendationService.php`; mọi hằng số nằm trong `backend/config/recommendations.php`; kiểm thử: `backend/tests/Feature/RecommendationTest.php`.

## 1. Ý tưởng trong một câu

Mỗi sản phẩm ứng viên nhận một điểm từ 0 đến 1, là **tổng có trọng số của sáu thành phần**, mỗi thành phần cũng nằm trong 0..1. Sắp xếp giảm dần theo điểm; hai sản phẩm bằng điểm thì sản phẩm có `id` nhỏ hơn đứng trước. Không có số ngẫu nhiên, nên cùng dữ liệu luôn cho cùng thứ tự.

Đây là mô hình **lai nhẹ (hybrid)**: ba thành phần cá nhân hóa theo hành vi của chính người dùng (content-based: danh mục, gian hàng, từ khóa) và ba thành phần chung cho mọi người (đánh giá, độ phổ biến, độ mới). Không huấn luyện mô hình, không ma trận người dùng–sản phẩm, vì dữ liệu của một đồ án quá thưa để collaborative filtering cho kết quả ổn định.

## 2. Dữ liệu đầu vào

| Tín hiệu | Nguồn | Ghi khi nào |
| --- | --- | --- |
| Lượt xem | `product_views (user_id, product_id, viewed_at)` | `GET /products/{slug}` của người đã đăng nhập; mỗi cặp người dùng–sản phẩm tối đa 1 dòng trong 30 phút; không ghi khi xem sản phẩm của chính gian hàng mình |
| Tìm kiếm | `search_logs (user_id, query ≤ 100 ký tự, created_at)` | `GET /products?q=…` (trang 1) của người đã đăng nhập |
| Yêu thích | `wishlists` (đã có) | — |
| Đơn hàng | `order_items` + `shop_orders` (đã có), bỏ phần đơn `CANCELLED` | — |
| Đánh giá | `reviews` trạng thái `PUBLISHED` (đã có) | — |

Khách (chưa đăng nhập) **không bị ghi gì**. Hai bảng mới không có cột IP hay user agent.

## 3. Chọn ứng viên

1. Sản phẩm công khai (`publicVisible`): trạng thái `ACTIVE`, còn hàng, gian hàng và chủ gian hàng đang hoạt động, danh mục đang bật. Vì vậy sản phẩm `SOLD`, `HIDDEN`, hết hàng đều bị loại.
2. Không thuộc gian hàng của chính người dùng.
3. Tối đa **500** ứng viên: 250 sản phẩm phổ biến nhất trong 30 ngày (đơn + yêu thích), phần còn lại là các sản phẩm mới nhất.

Giới hạn 500 giữ cho thời gian tính không phụ thuộc kích thước toàn bộ danh mục.

## 4. Sáu thành phần

Ký hiệu: cửa sổ thời gian = 30 ngày; điểm tương tác của một hành vi: xem = 1, yêu thích = 3, đặt hàng = 5.

| # | Thành phần | Công thức | Trọng số |
| --- | --- | --- | --- |
| 1 | Danh mục (category affinity) | `A / (A + 5)` | 0,30 |
| 2 | Từ khóa | tỉ lệ từ khớp lớn nhất trong 5 lần tìm gần nhất | 0,20 |
| 3 | Độ phổ biến | `min(1, ln(1 + n) / ln(1 + 50))` | 0,15 |
| 4 | Độ mới | `0,5 ^ (tuổi_ngày / 14)` | 0,15 |
| 5 | Gian hàng (shop affinity) | `S / (S + 5)` | 0,10 |
| 6 | Đánh giá Bayesian | `(B − 1) / 4` | 0,10 |

**Điểm = 0,30·danh mục + 0,20·từ khóa + 0,15·phổ biến + 0,15·độ mới + 0,10·gian hàng + 0,10·đánh giá** (tổng trọng số = 1).

### 4.1 Danh mục

- Với mỗi danh mục, cộng điểm tương tác của người dùng trong 30 ngày (xem ×1, yêu thích ×3, đơn ×5).
- `A` của một ứng viên = điểm của **chính danh mục** của nó + **0,5 ×** điểm của các danh mục cùng nhánh: danh mục cha, danh mục con và danh mục anh em (cùng cha). Đây là chỗ "có tính danh mục cha".
- Chuẩn hóa bão hòa `A / (A + 5)`: 5 điểm tương tác cho 0,5; càng nhiều tương tác càng tiến về 1 nhưng không vượt 1. Nhờ đó một lượt xem lẻ chỉ đẩy nhẹ, còn một đơn hàng đẩy mạnh.

Ví dụ: người dùng yêu thích 1 sản phẩm thuộc "Điện thoại" (3 điểm). Ứng viên thuộc "Điện thoại": `3/(3+5) = 0,375`. Ứng viên thuộc "Máy tính bảng" (anh em): `A = 1,5 → 1,5/6,5 = 0,231`.

### 4.2 Gian hàng

Giống danh mục nhưng gom theo gian hàng và không lan sang gian hàng khác: `S / (S + 5)`.

### 4.3 Từ khóa

- Lấy 5 truy vấn **khác nhau** gần nhất của người dùng; đổi về chữ thường; tách thành các từ (chữ và số, từ 2 ký tự trở lên).
- Với mỗi truy vấn: tỉ lệ = số từ xuất hiện trong "tên sản phẩm + tên danh mục" / tổng số từ của truy vấn.
- Thành phần = tỉ lệ lớn nhất trong 5 truy vấn.

Ví dụ: truy vấn "iphone 12", sản phẩm "iPhone 11 cũ" khớp 1/2 từ → 0,5.

### 4.4 Đánh giá Bayesian

`B = (v·R + m·C) / (v + m)` với `v` = số đánh giá của sản phẩm, `R` = điểm trung bình của nó, `m = 5` đánh giá "ảo", `C` = điểm trung bình của toàn bộ ứng viên (nếu chưa ai được đánh giá thì `C = 4,0`).

Lý do: một sản phẩm có 1 đánh giá 5 sao không nên xếp trên sản phẩm có 40 đánh giá trung bình 4,8. Với `m = 5`, sản phẩm ít đánh giá bị kéo về mức trung bình chung. Đổi về 0..1 bằng `(B − 1) / 4` vì thang sao là 1..5.

Ví dụ với `C = 4,0`: 1 đánh giá 5 sao → `B = (5 + 20)/6 = 4,17`; 40 đánh giá 4,8 → `B = (192 + 20)/45 = 4,71`.

### 4.5 Độ phổ biến

`n` = số dòng đơn hàng (không tính đơn hủy) + số lượt yêu thích của sản phẩm trong 30 ngày, của **mọi** người dùng. Dùng `ln(1 + n)` để sản phẩm có 100 lượt không áp đảo sản phẩm có 10 lượt gấp 10 lần; chia cho `ln(51)` để 50 lượt trở lên đạt 1.

Ví dụ: `n = 3` → `ln 4 / ln 51 = 0,353`.

### 4.6 Độ mới

Tuổi tính từ `published_at` (nếu trống thì `created_at`). Giảm theo hàm mũ, **chu kỳ bán rã 14 ngày**: mới đăng = 1; 14 ngày = 0,5; 28 ngày = 0,25.

## 5. Cold start

Khách, hoặc người dùng chưa có lượt xem / yêu thích / đơn hàng nào trong 30 ngày và chưa có lần tìm kiếm nào: chỉ dùng hai thành phần.

**Điểm = 0,60·phổ biến + 0,40·độ mới**

Bốn thành phần còn lại có trọng số 0 (kể cả đánh giá), đúng yêu cầu "chỉ dùng popularity + recency".

## 6. Nhãn lý do (`reason_label`)

Lấy theo thành phần đóng góp lớn nhất, ưu tiên lý do cá nhân:

| Điều kiện | Nhãn |
| --- | --- |
| Danh mục đóng góp lớn nhất trong ba thành phần cá nhân | `Vì bạn quan tâm {tên danh mục người dùng đã tương tác}` |
| Từ khóa đóng góp lớn nhất | `Vì bạn đã tìm “{truy vấn}”` |
| Gian hàng đóng góp lớn nhất | `Vì bạn quan tâm gian hàng {tên gian hàng}` |
| Không có lý do cá nhân; có đơn/yêu thích trong 30 ngày | `Đang được nhiều người quan tâm` |
| Không có lý do cá nhân; có đánh giá và `B ≥ 4` (đóng góp lớn hơn phổ biến) | `Được đánh giá cao` |
| Còn lại, đăng trong 14 ngày | `Mới đăng gần đây` |
| Còn lại | `Có thể bạn sẽ thích` |

## 7. Ví dụ tính tay đầy đủ

Người dùng đã **xem 1 sản phẩm** thuộc "Điện thoại" của gian hàng X. Mọi sản phẩm đều vừa đăng (độ mới = 1), chưa có đánh giá (`B = C = 4,0` → 0,75), chưa có đơn/yêu thích.

| Ứng viên | Danh mục | Gian hàng | Từ khóa | Đánh giá | Phổ biến | Độ mới | Điểm |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Chính sản phẩm đã xem (Điện thoại, gian hàng X) | 0,30 × 1/6 = 0,0500 | 0,10 × 1/6 = 0,0167 | 0 | 0,075 | 0 | 0,15 | **0,2917** |
| Sản phẩm khác thuộc Điện thoại | 0,0500 | 0 | 0 | 0,075 | 0 | 0,15 | **0,2750** |
| Sản phẩm thuộc Máy tính bảng (anh em) | 0,30 × 0,5/5,5 = 0,0273 | 0 | 0 | 0,075 | 0 | 0,15 | **0,2523** |
| Sản phẩm thuộc Sách | 0 | 0 | 0 | 0,075 | 0 | 0,15 | **0,2250** |

Thứ tự này chính là fixture của test `test_order_is_deterministic_and_explained_for_a_fixture`.

## 8. Sản phẩm tương tự

`GET /products/{slug}/similar?limit=8` không dùng điểm số mà dùng bộ lọc và thứ tự cố định:

- Lọc: công khai, khác chính nó, thuộc **cùng danh mục hoặc danh mục cha**, giá trong `[0,5·p ; 1,5·p]` (tính bằng BCMath, bao gồm hai đầu mút).
- Thứ tự: cùng gian hàng trước → cùng danh mục trước danh mục cha → chênh lệch giá nhỏ hơn trước → `id` nhỏ hơn trước.

## 9. Hiệu năng và cache

- `GET /recommendations?limit=12` (đăng nhập không bắt buộc, `limit` 1..24).
- Số truy vấn **cố định**, không tăng theo số sản phẩm: 2 truy vấn chọn id ứng viên, 1 truy vấn nạp ứng viên kèm số liệu, 1 truy vấn danh mục, 1 truy vấn gian hàng của người dùng + 3 truy vấn tín hiệu + 1 truy vấn tìm kiếm (chỉ khi đã đăng nhập), rồi 1 truy vấn nạp kết quả và các eager load cố định. Có test khẳng định điều này.
- Cache **10 phút theo từng người dùng** (khóa `recommendations:v1:{user_id}`; khách dùng chung khóa `guest`). Chỉ cache danh sách `id` + nhãn lý do. Khi trả về, sản phẩm được đọc lại từ DB nên: sản phẩm vừa bị ẩn/bán hết sẽ biến mất ngay, và `is_wishlisted` luôn đúng theo người đang xem.
- Hệ quả của cache: một lượt xem mới chỉ ảnh hưởng tới gợi ý sau tối đa 10 phút.

## 10. Giới hạn đã biết

- Không có collaborative filtering ("người mua X cũng mua Y") và không đa dạng hóa kết quả: nhiều sản phẩm cùng danh mục có thể đứng liền nhau.
- Khớp từ khóa là so chuỗi con và phân biệt dấu tiếng Việt ("dien thoai" không khớp "điện thoại").
- Sản phẩm người dùng đã yêu thích hoặc đang xem vẫn có thể xuất hiện trong gợi ý.
- Trọng số là lựa chọn thiết kế, chưa được hiệu chỉnh bằng A/B test hay dữ liệu thật.

## 11. Kiểm chứng (2026-10-13, PHP 8.4.25)

- Backend: **455 test / 5.059 assertions PASS** (trước D7: 444 / 4.950); 11 test mới trong `RecommendationTest`.
- Frontend: **456 test / 37 file PASS** (trước D7: 449 / 36); 7 test mới trong `Recommendations.spec.js`. Pint, oxlint, ESLint, build PASS.
- Migration `2026_10_13_090000_create_product_views_and_search_logs_tables` chỉ thêm: 2 bảng mới và 2 index trên `wishlists`, `order_items`. `down()` đã được kiểm qua các test dùng `DatabaseMigrations`.
- DB dev: đã sao lưu trước khi migrate; sau migrate, 24 bảng nghiệp vụ giữ nguyên số dòng và mã băm, chỉ có thêm `product_views` và `search_logs`.
- Chưa kiểm tra trên trình duyệt thật cho D7; phần giao diện mới chỉ được phủ bằng test component.
