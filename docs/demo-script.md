# Local defense demo script — FINAL C1/C2

Use `http://localhost:5173`. This is a local, fictional demo; do not enter real buyer addresses, payment credentials or real money.

## Preparation

Start the MySQL service on `127.0.0.1:3306`. Use a compatible PHP 8.4 CLI; the bundled XAMPP PHP on this machine is older. Keep the existing private database credentials and APP_KEY.

```powershell
cd backend
composer install
# Fresh checkout only: copy .env.example to .env and configure private values.
# Generate APP_KEY only if initially empty: php artisan key:generate
php artisan config:clear
php artisan migrate:status
# Apply reviewed pending additive migrations; never migrate:fresh.
php artisan migrate
php artisan storage:link
```

Set a private `DEMO_PASSWORD` of at least 12 characters in `backend/.env`, then:

```powershell
php artisan config:clear
php artisan db:seed --class=FinalDemoSeeder --no-interaction
```

The seeder is restricted to local/testing and the project's development/test database names. It never resets existing passwords or progresses existing orders/auctions again. It preserves moderation, stock changes and existing user data. Reserved identifier conflicts fail rather than overwrite a local account. Re-running is additive, **not a reset**. To reset a dedicated demo password, use Forgot Password with `MAIL_MAILER=log` and privately read its link in `backend/storage/logs/laravel.log`; do not publish that token or log. Changing DEMO_PASSWORD affects only newly created accounts.

Four terminals:

```powershell
# 1: backend
cd backend
php artisan serve --host=127.0.0.1 --port=8000

# 2: realtime — use the matching private backend/frontend Reverb env values
cd backend
php artisan reverb:start

# 3: scheduled auction start/finalization/reminders and payment reconciliation
cd backend
php artisan schedule:work

# 4: frontend
cd frontend
npm install
npm run dev
```

If PHP is absent from PATH, use its full compatible executable path. On Windows use `npm.cmd` if PowerShell blocks npm.ps1. For larger video uploads, configure PHP upload/post limits or use the existing `backend/serve.ps1` helper. The demo clip itself is only about 12 KB.

## Accounts

All six use the privately configured password **at first creation**. Seller is a normal USER who owns a shop.

| Presenter role | Email | Identity |
| --- | --- | --- |
| Admin | `c1-admin@example.test` | ADMIN / ACTIVE |
| Seller 1 | `c1-seller-1@example.test` | USER / ACTIVE; C1 Demo · Góc Công Nghệ |
| Seller 2 | `c1-seller-2@example.test` | USER / ACTIVE; C1 Demo · Nhà & Phong Cách |
| Buyer A | `c1-buyer-a@example.test` | USER / ACTIVE |
| Buyer B | `c1-buyer-b@example.test` | USER / ACTIVE |
| Buyer C | `c1-buyer-c@example.test` | USER / ACTIVE |

Login: `http://localhost:5173/login`. Use separate browser profiles/private windows for Buyer A, Buyer B and Seller. Admin login redirects to its dashboard; ordinary Users cannot access Admin by URL.

## Defense run: 7–10 minutes

Target: **9 minutes 15 seconds**, with 45 seconds buffer inside a ten-minute slot. Preparation below is outside presentation time. Detailed journeys afterward are reference steps, not six additional demos to perform in full.

### Prepare before the defense

- Start MySQL and all four processes above; confirm `/api/v1/health`. Open Buyer A, Buyer B, Seller 1 and Admin in separate profiles. Prepare credentials privately, never on projected terminals.
- Check C1 Demo 07 is still available/has stock and Buyer A has a demo address; use another eligible fixed item if previous runs consumed it. Open Messages in Seller's profile.
- Through Seller 1 → Auctions, prepare **a new eligible short auction**, for example C1 Demo 09 if it has no current claim. Set start now and end about **4 minutes after planned presentation start**. Note its Arena link and minimum. Do not edit seeded history/deadlines or recreate an already claimed item. Plan Buyer B as final highest bidder, with the last intended bid submitted by 3:40 in the talk. Keep Scheduler running; synchronization may lag by up to its one-minute cadence.
- Preopen the new Arena in A/B and Video Theater with C1 Demo 07 in a second profile. Keep Buyer B's completed C1 Demo 04 auction Order as fallback. Recheck actual status immediately before starting; if talk starts late, prepare another eligible auction rather than changing server time.
- Optional Sandbox is an explanation/brief pending-payment view; do not spend the short demo waiting for external payment. COD provides deterministic success. Videos are local test-pattern footage, explicitly say so.

| Time | Click / action | What to say | Expected visible result |
| --- | --- | --- | --- |
| 0:00–0:40 | Home → discovery; show Products / Auctions / Video Review header | “Đây là marketplace đồ cũ; giá cố định, đấu giá và video dùng cùng dữ liệu sản phẩm.” | Public discovery, clear navigation and product cards |
| 0:40–2:10 | Login Buyer A → C1 Demo 07 → add one → Cart → Checkout COD → Order | “Server kiểm tra tồn kho, địa chỉ và tính lại giá; Order chia theo Shop khi mua nhiều gian hàng.” | Real Order, saved shipping/product snapshot and COD pending status |
| 2:10–2:50 | Product → Nhắn người bán → send; Seller profile opens Tin nhắn and replies | “Chỉ hai thành viên đọc/gửi; messaging MVP dùng polling khoảng bốn giây.” | Buyer sees Seller reply and product context |
| 2:50–3:50 | New Arena in A/B: A minimum bid → B raises minimum → A's bell | “Giá tối thiểu và thời gian do server quyết định; row lock tuần tự hóa bid. OUTBID được lưu DB trước realtime.” | Price/history update in both profiles, A unread OUTBID notification |
| 3:50–5:20 | Wait actual end/sync; Buyer B → winner checkout → COD → Order | “ENDED khác với đã thanh toán. Chỉ winner checkout trong hạn, giá lấy từ winning bid.” | Winner-only checkout, ordinary Order linked to auction at winning amount |
| 5:20–6:30 | Header Video Review → C1 Demo 07 Theater → A comment, observe second browser; ESC close | “Video local và comment text; DB lưu trước khi Reverb gửi. Clip này là demo test-pattern.” | Real comment appears without reload, native controls and safe Product CTA |
| 6:30–7:30 | Seller Dashboard → Quick Sell inspect/close → Products editor → Orders / Shop | “Seller là USER sở hữu Shop; đăng bán cần moderation, fulfillment thuộc Shop của mình.” | Existing KPIs/form/images/video and owned Orders/status; no extra draft needed |
| 7:30–8:40 | Admin login → Dashboard → Products/Categories → Reviews/Orders; briefly show User direct Admin denial | “Admin API kiểm tra role; URL/ẩn menu không phải bảo mật. Orders ở Admin chỉ đọc.” | Role redirect/menu, management pages; ordinary User friendly 403 |
| 8:40–9:15 | Show testing summary / architecture and payment boundary | “352 backend tests, 355 frontend tests, 166 browser checks PASS. Sandbox create đã kiểm tra; paid callback thực chưa xác nhận.” | Accurate evidence/limitations, no secret or raw log displayed |

### Fallbacks during presentation

- **Auction not finalized yet:** wait one scheduler cycle or, after actual expiry only, run `php artisan auctions:sync`. If time is exhausted, show Buyer B's completed C1 Demo 04 Order and explain this is an existing settlement, not a newly created live result. Do not claim a fresh winner checkout succeeded if it did not.
- **Reverb disconnected:** show connection state and committed REST refresh/notification history; verify service/env before retrying. Messaging reply uses polling independently. Do not claim push was observed if only refresh worked.
- **ZaloPay unavailable:** demonstrate COD. Show the existing pending Sandbox payment behavior and explain HTTPS callback tunnel/provider requirements. A gateway outage or browser return is never PAID; no fabricated callback/status update.
- **Demo item unavailable:** choose another eligible C1 fixed listing; a reserved item deliberately links to Arena. Rerunning the seeder preserves previous progress rather than resetting it.

### C3 rehearsal boundary

C3 checked the current C1 records and real browser navigation/forms in a **read-only rehearsal**: 45 checks across 35 navigations and eight API reads. All 22 captured business tables remained identical. It did not repeat checkout/bids/messages/comments/read-state writes or create a short auction, because release freeze prohibits demo-data changes. Those write/realtime behaviors retain C1's successful evidence. The 9:15 timing is a presenter plan, not an observed fresh end-to-end timed purchase. Creating the new short auction and checking deadlines remain explicit pre-presentation steps; the existing settled C1 Demo 04 Order is the no-write fallback.

## Presentation sequence

### A — Normal purchase (Buyer A, Seller 1)

1. Home → Products; search **C1 Demo 07** (AirPods). Open the listing, images and local video.
2. Add one item → Cart → Checkout. The Buyer already has a fictional demo address. Choose COD.
3. Show the created Buyer Order and its totals, then Seller 1 → Orders for the same ShopOrder.
4. Explain that Seller fulfillment follows Confirmed → Processing → Shipping → Completed; COD collection must be confirmed. Existing completed demo purchases provide valid reviews.

Use C1 Demo 17 (Clean Code) to demonstrate a one-unit listing. C1 Demo 18 is an already completed/SOLD historical listing and is excluded from public purchase pages.

### B — Seller (Seller 1 or Seller 2)

1. Header → **Đăng bán nhanh**. Create a small draft with a repository-owned demo image, or inspect an existing C1 product.
2. Seller → Products → Edit: basic information, image order/primary selection and optional video.
3. Submit for moderation; it is not automatically public. Show dashboard and Orders, then Shop status.

New drafts created during a presentation are intentional demo activity. Do not delete order-linked records to make the screen look pristine.

### C — Admin

1. Login → Dashboard → Users → Shops → Products/Categories → Reviews → Orders.
2. Approve the newly submitted demo draft only through currently valid moderation actions. Show expected-status feedback and account protection.
3. Admin Orders are read-only; fulfillment belongs to the Seller. Published demo reviews have real completed-purchase relationships.

### D — Auction (two Buyers, Seller 1)

Initial seed inventory: C1 Demo 01 starts after 15 minutes; C1 Demo 02/03 are live with valid bidding history; C1 Demo 04 ended with Buyer B's winning COD Order; C1 Demo 05 ended without bids; C1 Demo 06 is cancelled. During final verification, 01 legitimately became LIVE. A standby SCHEDULED auction was prepared through the Seller API on C1 Demo 08 (starts two days after preparation), bringing the current total to seven. Auction dates evolve under server time and the scheduler; rerunning the seeder does not extend them. Check current dates/status in Seller → Auctions before presenting.

1. For live interaction, open C1 Demo 02/03 in two browsers. Buyer A bids at the displayed minimum; Buyer B raises by at least the increment. Show realtime price/history and A's persistent OUTBID notification.
2. For a complete win during a short presentation, **prepare a new auction through Seller → Auctions** on an eligible non-reserved, available demo product. Set start now and end a few minutes ahead. Keep `schedule:work` running.
3. Repeat A → B bidding. Wait for the actual end time and scheduler; use `php artisan auctions:sync` once after expiry if an immediate demo refresh is needed.
4. Only the winner gets checkout. Choose COD → ordinary Buyer Order, linked to that auction and the winning amount. Seller cannot bid and the loser cannot checkout.
5. To show a previously completed settlement without waiting, login Buyer B and use the C1 Demo 04 AUCTION_ORDER_CREATED notification or its existing Order. A settled winner link leads to the original Order rather than creating another.

### E — Video

1. Header → **Video Review**, or Home's Video Review tab.
2. C1 Demo 07 shows fixed-price product context; C1 Demo 03 shows auction context.
3. Open Theater in two authenticated browser profiles. Post a comment in A; B sees it without reloading. Show plain-text display and Seller's confirmed hide action.
4. Use the product/Arena CTA. Close with ESC; original navigation and native video controls remain usable.

Two READY videos reuse the repository's tiny WebM test-pattern clip. They are explicitly labeled **demo media, not authentic reviews of the listed products**. For a richer presentation, Seller can upload a suitable owned local MP4/WebM using the existing editor. No Internet media or transcoding is required.

### F — Messaging

1. Buyer A opens C1 Demo 07 → **Nhắn người bán** → sends a text message.
2. Seller 1 opens **Tin nhắn**, selects the conversation and replies. Lightweight polling delivers the reply.
3. Show product context, unread state and responsive drawer. Existing conversations also include Buyer B/Seller 2 (C1 Demo 11) and Buyer C/Seller 2 (C1 Demo 14).

## Payment boundaries

COD is fully reproducible locally. ZaloPay requires authorized **Sandbox** env credentials; selecting it creates a pending online payment and redirects to the Sandbox gateway. Do not treat the return URL as proof of payment. A remotely delivered callback needs a public HTTPS tunnel targeting the backend and the configured callback URL. Production merchant setup is separate.

Creation for normal and auction Orders is verified where the configured Sandbox is reachable. No card/bank credentials or real completed payment are needed for this defense; signed callback/query, replay, amount/reference and cancellation rules are covered by regression tests.

## Repeatability and data safety

The C1 pack adds 18 dedicated products even if the older catalog already exists; it never replaces those listings. Six active categories are reused when available. It includes two shops, five valid COD Orders (including one auction settlement), two published reviews, two local videos/four comments, real auction read/unread notifications, and three conversations/six messages at first creation.

Use normal UI actions to adjust dedicated demo records. For a clean-room rerun, use the separate guarded test database with tests, or arrange an explicitly isolated database and review the seeder guard before changing it. Do not wipe the development database, hard-delete histories or use migrate:fresh. See [final verification](final-demo-verification.md) for actual results and limitations.
