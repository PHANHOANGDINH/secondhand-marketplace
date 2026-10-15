# Auction "Bán ngay" — verification

Date 2026-10-16, PHP 8.4.25 (Herd). No migration; no change to bidding, finalization rules, checkout or money handling.

## Behaviour

While an auction is **LIVE** and has at least one bid, its seller can press **Bán ngay** on "Đấu giá của tôi". The page reads the current highest bid (bids only go up, so it is the newest one) and asks for confirmation with the amount and an optional note. On confirmation:

- `POST /api/v1/seller/auctions/{id}/accept-bid` `{ expected_status: "LIVE", expected_bid_id, reason? }`.
- The auction becomes **ENDED** at once; the highest bid wins; `ends_at` is set to the moment of the sale; the winner gets the usual checkout window (`auctions.winner_checkout_hours`) and the same notifications as a normal end (AUCTION_WON with the checkout link, AUCTION_ENDED for the seller and the other bidders). `AuctionStatusChanged` updates open arenas; further bids get 409.
- Nothing is sold or reserved beyond what a normal end does: the winner still checks out with the winning bid price.
- Audit log `auction.accepted_by_seller`, LIVE → ENDED, actor = seller, reason = the note or "Người bán chốt bán ở giá … ₫.".

Refused with **409** and no change: a newer bid than the one confirmed, a status other than LIVE (or a stale `expected_status`), no bids, `ends_at` already passed, product or shop no longer available. 403 for anyone but the auction's seller (other sellers, bidders, admins); 401 when signed out; 422 for missing fields or a note over 500 characters. After a 409 the page closes the panel, reloads the list and says that a new bid arrived or the auction ended.

Concurrency: the service takes locks in the same order as bidding (accounts, shop, categories, product, auction row, bids), so a bid and a sale serialize on the auction row; finalization shares the private `AuctionService::end()` and stays idempotent afterwards.

## Checks

| Check | Result |
| --- | --- |
| `AuctionSellNowTest` (new, 5 tests) | happy path (winner, ends_at, checkout window, broadcast, audit, notifications, checkout opens, later bid 409, finalizer no-op), default reason, newer bid / stale status / no bids / expiry → 409 without audit, no double sale, authorization and validation |
| Auction test group | 98/98 |
| Backend full suite (test DB) | **499/499** (6,530 assertions) |
| Vitest | 518/518 (`AuctionUI.spec.js` +2: confirm sends the bid it read and the trimmed note; button only for LIVE with bids; 409 closes the panel with a clear message) |
| pint / oxlint / eslint (no `--fix`) / build | passed / 0 / 0 / OK |

Docs: `auction-state-machines.md` (new LIVE → ENDED transition and rules), `api-summary.md` (endpoint row).
