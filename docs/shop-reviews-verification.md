# Shop star ratings and centred report dialog — verification

Date 2026-10-19. PHP 8.4.25 (Herd).

## Behaviour

- **Who rates**: a signed-in, active buyer with at least one **completed** order part at the shop; never the owner. One rating per buyer and shop (1–5 stars, optional comment ≤ 2000), editable; editing keeps a moderation decision (a hidden rating stays hidden).
- **API**: `GET /api/v1/shops/{slug}/reviews` (public, paginated 10, `summary` = average, count, 5→1 distribution of published ratings); `GET /shops/{slug}/reviews/mine` (signed in: `can_review`, `own_shop`, `review`); `PUT /shops/{slug}/reviews` (201 new / 200 edit; 403 with a Vietnamese message when not eligible; 404 for a shop that is not public). Writes lock accounts → shop → rating row in one transaction; the unique `(shop_id, user_id)` key backs it up.
- **Seller card** on product pages now shows the shop's own ratings (`rating_avg` / `rating_count` from `shop_reviews`), the same numbers as the shop page; product reviews keep their own product average.
- **Shop page**: stars + average + count under the shop name (links to the section); "Đánh giá gian hàng" section after the products: big average, 5→1 bars, newest ratings with "Xem thêm", and a centred dialog with a star picker (radio group, arrow keys, 44 px targets) and a comment. Guests are sent to sign in; buyers without a completed order see why they cannot rate yet; owners see no button.
- **Report dialog** ("Báo cáo sản phẩm / gian hàng") is centred on screen again: the product side panel's `> * { margin: 0 }` had removed the native dialog's auto margins.

## Database

New table `shop_reviews` (migration `2026_10_19_100000_create_shop_reviews_table`, additive, `down()` drops it). Development database: dump `.runtime/dev-before-shop-reviews-20261019-095533.sql`, row snapshot before/after: no row added or removed; the only changed rows were 6 `payments` whose `updated_at` the scheduled `zalopay:reconcile` touches every minute (statuses unchanged).

## Checks

| Check | Result |
| --- | --- |
| `ShopReviewTest` (new, 5) | rate once and edit, public list + summary + seller card, eligibility (pending / shipping / cancelled / other shop / owner / suspended account / guest), validation and non-public shops, hidden stays hidden |
| Backend full suite (test DB) | **507/507**; `ProductDiscoveryTest` seller-card test now seeds shop ratings |
| Vitest | **533/533**; new `ShopReviews.spec.js` (6) |
| pint / oxlint / eslint (no `--fix`) / build | passed / 0 / 0 / OK |

Open: admin moderation screen for shop ratings is not built (the `status` column supports hiding; product-review moderation is unchanged).
