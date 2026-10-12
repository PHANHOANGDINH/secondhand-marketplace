# Auction A3 — Bidding Arena UI Verification

Date: 2026-10-08. **PASS for the local Auction A3 UI and realtime demo flows.**

## Scope and UI structure

Backend, API contracts, auction/auth/business rules and fixed-price product/cart/checkout flows are unchanged. No new packages, GSAP outside homepage, audio, fake bids/countdowns, winner-to-order/payment, stock reservation, deposit/refund, wallet, reputation or video/theater features were added.

| Route | Purpose |
|---|---|
| `/auctions` | Public discovery, real LIVE/SCHEDULED/ENDED filters, images, prices, bid count, shop context, synced countdown and pagination. |
| `/auctions/:id` | Public Arena, gallery/details and sticky controls, bid form/chips, price/count/log updates and paginated history. |
| `/seller/auctions` | Existing authenticated Seller shell: list, inline creation and confirmed cancellation. |
| `/seller/auctions/:id` | Owner-authorized REST detail, including cancelled/unavailable-public auctions; no bidding controls. |

The public header includes “Sàn đấu giá”; Seller navigation includes “Đấu giá”. Existing routes and guards remain in place. Auction resources expose product summaries only: image/description/condition/category/shop context comes from existing Product APIs. Missing context shows a truthful unavailable state/placeholder.

## Bidding and realtime

The Arena consumes A2 `useAuctionRealtime`. It displays current/start price, server-provided next minimum and safe bid identities (`Người mua #id`, or the server's display-safe label). It never renders HTML from descriptions, messages or bidder fields.

Currency input accepts Vietnamese grouping and decimal-comma notation, or canonical decimal strings. Monetary parsing/chip arithmetic uses **BigInt cents**, never floating-point arithmetic. +50k/+100k/+200k always start from the latest server-provided minimum. The backend remains the final validator; it rechecks locking, eligibility, time and minimum.

The form has submitting/disabled, success, local-format error and backend rejection states. A synchronous busy guard prevents double submit. After rejection, REST refreshes truth and the input receives focus after re-enabling. Guest submit redirects to Login with `/auctions/{id}` as the safe intended return. Seller ownership removes the bid form. Scheduled/terminal auctions have no enabled bidding; display expiry disables the form while retaining the server's status and explaining that server confirmation is pending.

Accepted public events update price/minimum/count and prepend deduplicated latest bids without page refresh or normal live-update polling. Price changes flash for 550 ms; new rows enter with lightweight CSS. Initial price load does not animate. Bid history is scrollable, uses safe identity/absolute timestamps and supports “Xem thêm”. REST refresh signals reload history after reconnect; stale route/request results are discarded.

Two confirmed display gaps were fixed without backend changes:

- A bid can arrive before the initial REST response. The composable now fills missing immutable product/time context from the older REST response while preserving newer bid state.
- ENDED status events do not include the winning user. The Arena refreshes REST for the confirmed winner, and shows a pending-result explanation rather than falsely declaring “no bids” while bids exist.

## Countdown and status

A2's server/monotonic clock drives one-second display updates. SCHEDULED counts toward starts_at; LIVE toward ends_at. Under one minute, “Sắp kết thúc” and danger-emphasis text/border communicate urgency. The final ten seconds have a restrained two-second border pulse. Monospace/tabular numbers prevent digit jitter.

At zero the UI says it is waiting for the server and requests a boundary refresh; it never invents ENDED. Authoritative status events/scheduler remain responsible for lifecycle. ENDED/CANCELLED stop display timers. Ended auctions show final price and only API-confirmed winner ID, or a genuine no-bid result. No payment/order CTA exists.

## Outbid notifications

A lazily loaded `OutbidToasts` component subscribes through `useUserRealtime` in the shared authenticated layout, including Buyer/Seller/Admin areas. Only server private outbid events trigger notices; the backend's A1/A2 self-outbid rule remains unchanged and was verified live.

Toast copy: “Có người vừa trả giá cao hơn bạn cho {product}.” Actions navigate to the appropriate Arena (“Ra giá lại”) or dismiss. Destination IDs are validated; duplicates are ignored, visible notices are capped at three and deduplication memory is bounded. Notices auto-dismiss after 12 seconds, pause on pointer/focus interaction, resume afterward, support Escape and clear on logout/unmount. Text uses `role=status`; no autofocus interrupts the current task. There is no bidding inside the toast.

## Seller management

Creation submits only product_id, start_price, bid_increment, starts_at and ends_at. Local date/time inputs convert to ISO timestamps. No editable status/current-price/winner fields exist.

Choices intersect owned ACTIVE, non-archived, in-stock products with the public shop catalog, thereby excluding hidden/inactive category ancestry. All paginated SCHEDULED/LIVE auctions are checked to exclude products already reserved by an unfinished auction. Successful create/cancel updates this exclusion set; concurrent changes are still validated by the existing backend.

Cancellation is offered only for an apparently unexpired SCHEDULED/LIVE auction with zero bids and requires explicit confirmation. The backend revalidates every request. Backend field validation is shown under the relevant labels and receives focus. Owner-only detail uses the existing Seller endpoint; foreign IDs cannot display data.

## Responsive and accessibility

Verified at **1440, 1024, 768 and 390 px**. Desktop Arena uses roughly 60/40 gallery/control composition; narrower desktop uses balanced columns. Below 900 px the panel is static and placed before gallery/details to keep bidding reachable. Cards and Seller form collapse to one column on phones; history scrolls internally. No page-level horizontal overflow was observed. Existing local product images loaded successfully.

Native form semantics, labeled inputs, required markers, validation descriptions and visible focus are preserved. Price/connection/feedback/toast announcements are restrained; timer ticks do not continuously announce. Urgency includes text, not color alone. The CTA uses existing auction-accent ink for contrast; urgent captions use Bootstrap danger emphasis on the soft surface. Reduced-motion CSS removes price/log/pulse animations and image hover transitions. No interaction depends only on hover.

## Test results

| Check | Result |
|---|---|
| New Auction UI suite | **29 passed** |
| Arena + realtime regression focused suites | **39 passed** |
| Full frontend suite | **271 passed**, 23 files |
| oxlint + ESLint | **PASS** |
| Production build | **PASS** |
| Real multi-browser/responsive/keyboard checks | **48 passed**, zero uncaught exceptions/console.error calls |
| Development data cleanup | **PASS**, 16 business-table row counts/content hashes unchanged |
| Backend changes/tests | No backend source changed in A3; no unnecessary backend tests added/rerun. Prior A2 full baseline: 277 tests / 2,880 assertions PASS. |

Commands:

```powershell
cd frontend
npm run test:unit -- --run src/__tests__/AuctionUI.spec.js src/__tests__/AuctionRealtime.spec.js
npm run test:unit -- --run
npm run lint
npm run build
```

## Real browser verification

Two independent authenticated Edge profiles used real Login, Arena, existing REST endpoints and Reverb, with disposable accounts/product/shop/category/auctions. All accepted bids were written through the real backend; nothing was simulated as auction state.

- A bid 100,000; B saw the new log/price without refresh. B's +50k calculated 160,000 from the updated 110,000 minimum. B bid; A received the new price and private toast. A bid 170,000; the next chip used the new 180,000 minimum.
- Both displayed countdowns matched. A bid again as highest bidder and received no self-outbid toast. A backend-rejected low bid showed error/focus feedback.
- A navigated to Products; B's next bid produced the global toast. “Ra giá lại” returned to the Arena. The next successful bid produced exactly one public event delivery after route re-entry.
- Reverb was stopped deliberately. B's Arena bid still succeeded via REST. Restart/reconnect restored current price and the missed bid-history entry. Normal live bid updates required no polling.
- Fixture expiry/finalization removed bidding on both browsers and showed the REST-confirmed winner. Seller viewing its own auction had no bid form.
- Seller created a real SCHEDULED auction, then confirmed cancellation of that bid-free auction through existing APIs.
- Arena, discovery, Seller form and toast geometry were checked at all four widths; screenshot artifacts were visually reviewed. Native Enter submitted a real bid, and native Escape dismissed a focused toast. Reduced motion suppressed price animation.

All temporary rows, their sessions and the copied repository-owned fixture image were removed. Two separate fixture rounds each restored original counts and SHA-256 row hashes across 16 business tables. Auto-increment counters may advance. No migrations, resets, unrelated seeding or development data deletion occurred in A3.

## Remaining limitations and demo setup

- Keep Laravel, Reverb, Vite and `schedule:work` running as documented in [A2](auction-a2-realtime-verification.md). Scheduler status updates may lag the minute tick; server bid expiry is enforced immediately.
- Discovery cards are REST snapshots with ticking clocks, not per-card realtime subscriptions. Use filter/reload or open Arena for live pricing.
- Public API intentionally excludes CANCELLED/unavailable auctions; public reload may show unavailable. Owner detail remains available through the Seller API and presents its REST snapshot without public realtime subscription.
- Outbid notifications are session-local and best-effort. Missed private notifications are not replayed after outage; REST restores public truth. No notification history/audio or reconnect polling was added.
- Production TLS/proxy/supervision/scaling and full assistive-technology audits remain outside this local UI phase. A3 does not change A2's deployment/security limitations.
- Cleaned fixtures are not permanent demo auctions. Create a real auction from an eligible Seller product before demonstrating live bidding; an empty discovery page is an intentional empty state.

## A3 changed files

Created:

- `frontend/src/views/AuctionsView.vue`, `AuctionArenaView.vue`, `SellerAuctionsView.vue`
- `frontend/src/components/auction/AuctionCard.vue`, `AuctionCountdown.vue`, `OutbidToasts.vue`
- `frontend/src/composables/auctionPresentation.js`, `useAuctionCountdown.js`
- `frontend/src/styles/auction.css`
- `frontend/src/__tests__/AuctionUI.spec.js`
- `docs/auction-a3-ui-verification.md`

Modified:

- `frontend/src/api/auction.api.js`
- `frontend/src/composables/useAuctionRealtime.js`
- `frontend/src/__tests__/AuctionRealtime.spec.js`
- `frontend/src/router/index.js`
- `frontend/src/layouts/DefaultLayout.vue`
- `frontend/src/components/seller/SellerShell.vue`
- `frontend/src/components/ui/StatusBadge.vue`
- `README.md`

Existing changes from earlier phases were preserved; the entire dirty git inventory is not attributed to A3.

**Final decision: PASS — Auction A3 is ready for a local UI/realtime demo. Auction winner order/payment integration remains out of scope.**
