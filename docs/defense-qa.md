# Defense Q&A

Answers describe delivered behavior. Production suggestions are future work.

| Question | Answer / anchor |
| --- | --- |
| Why Vue + Laravel? | Vue supports reactive marketplace/dashboards; Laravel provides session auth, validation, Policies, ORM, transactions and events. Backend services own rules. |
| Why REST? | Explicit resources make ownership/validation/pagination/errors testable and rebuild authoritative state after missed events. [Routes](../backend/routes/api.php). |
| Why Sanctum? | First-party SPA uses cookie sessions/CSRF instead of storing bearer tokens in browser storage. Local Vite proxy keeps requests same-origin. |
| Why Reverb instead of polling? | Auction price/OUTBID and Theater comments benefit from push; Laravel events/Echo integrate directly. Notifications still persist; messaging MVP deliberately uses four-second polling. |
| How prevent two users winning? | Bid locks the auction and rechecks minimum/time; finalizer locks the same row, picks highest accepted bid once and returns persisted result on retries. Unique auction-linked item/Order references prevent duplicate settlement. [AuctionService](../backend/app/Services/AuctionService.php). |
| Why DECIMAL/BCMath? | Binary floats can miscompare money. DECIMAL(15,2), decimal strings and bcadd/bcmul/bccomp preserve supported precision; ZaloPay requires whole VND. |
| How avoid overselling? | Checkout locks/revalidates/decrements stock inside Order transaction; zero becomes SOLD. Payload-bound UUID retries reuse Order; cancellation restores once. [CheckoutService](../backend/app/Services/CheckoutService.php). |
| How protect Admin? | Sanctum/activity/session + EnsureAdmin/Policies; Guest 401/login, User 403; direct URL never grants access. Admin Orders read-only. |
| How prevent IDOR? | Scope IDs to actor/participants or authorize Policies, including nested media/ShopOrder relationships; reauthorize locked writes. Hidden links are only UX. |
| Why separate settlement? | ENDED means bidding stopped, not paid/purchased. It may have no winner, await checkout, have Order or expired claim. State is derived. [States](auction-state-machines.md). |
| Winner does not checkout? | Deadline expires; new winner checkout denied, fixed-price claim released. No fallback/penalty/deposit; ending alone does not mark Product SOLD. |
| Realtime disconnects? | DB persists; auction/comments/notifications reconcile through REST on reconnect/resume and dedup. Countdown cannot replace server time. Best effort delivery, not exactly-once event log; messaging polling pauses while inactive. |
| How secure ZaloPay? | KEY1 signs create/query; KEY2 verifies exact callback MAC/hash_equals. Reference/app/user/time/amount and unique gateway ID checked under locks. Return URL cannot mark PAID; late conflicting money requires reconciliation. |
| Query same MAC as callback? | No. Query request uses KEY1 and provider HTTPS; callback data uses KEY2. Confirmation checks returned fields/amount/transaction; do not claim independently signed query responses without verification. |
| Why ShopOrders? | One cart spans Shops: Order holds Buyer/address/payment, ShopOrders hold individual fulfillment, items preserve snapshots. Auction checkout uses same creator for one item/Shop. |
| Are reviews purchase-linked? | One review/item; Buyer matches original Order/completed ShopOrder and cannot own Shop. Published summaries use matching predicate; demo follows real transitions. |
| Secure uploads? | Image MIME/extension/size/pixels + GD re-encoding; video MIME/extension/container/size; UUID paths, parent Policy/scoped cleanup. No antivirus/transcoding/static-URL revocation. |
| Seller also Buyer? | Yes. Seller is Shop-owning User, not third role; may buy/bid elsewhere, never own listing. |
| Why persistent notifications? | Offline history: recipient/event dedup persists before private broadcast; read/unread API uses DB. Existing scope is auction lifecycle/auction Order creation, not all generic Order events. |
| Remaining limits? | External payment completion/inbox, production/load/physical devices/backup certification; local sessions/files; no refund/payout/wallet/fallback/transcoding. Demo clips are labeled test-pattern media. |
| Production improvements? | HTTPS/proxy/cookies, managed secrets, supervised runtime/Reverb/scheduler, shared sessions/files if scaling, monitoring/backups, scanning/moderation, load/security/accessibility tests. Proposals, not delivered freeze changes. |
| Evidence? | C1: 352 backend/3,504 assertions; 355 frontend/28 files; 166 actual browser checks/four widths; Pint/lint/build PASS. Sandbox creation confirmed, completed paid callback not claimed. [Testing](final-testing-summary.md). |
