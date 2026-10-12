# Final release checklist — v1.0-demo

FINAL C3, 2026-10-09. **Documentation/visual QA: PASS. Local demo/defense: READY within C1 scope. Git release checkpoint: PENDING; no commit/tag/publication was performed.** This checklist is the prepared freeze record, not proof that the working tree is clean or that a `v1.0-demo` tag already exists.

Canonical entry point: [final-project-index.md](final-project-index.md). C3 changed Markdown only; no features, schema, business rules, credentials or demo records changed.

## Application readiness

- [x] C1 verified: 352 backend tests / 3,504 assertions; 355 frontend tests / 28 files; 166 actual browser checks; Pint/lint/build PASS. These are inherited C1 results, not new C3 suite runs.
- [x] FinalDemoSeeder ready: additive/idempotent, six dedicated accounts, two ACTIVE Shops, eighteen Products and valid cross-feature history. C3 inspected current records without reseeding.
- [x] Buyer journey ready: C1 verified real COD purchase; C3 checked C1 Demo 07, cart controls, existing Cart/Checkout state and seeded Order detail without purchasing.
- [x] Seller journey ready: dashboard/products/auctions/orders/shop/editor and Quick Sell inspected; no draft/submission/media mutation.
- [x] Admin journey ready: dedicated ADMIN login redirects correctly and all seven management areas open; no moderation write.
- [x] Auction journey ready: at least two LIVE and one SCHEDULED examples, bid history/form and persistent OUTBID; C1 supplied actual bidding/realtime/winner checkout evidence. New short-auction creation is a pre-presentation step, not performed during C3 freeze. Existing C1 Demo 04 settled Order is the no-write fallback.
- [x] Video journey ready: C1 Demo 07 READY local video, Theater/comment form and published comment history inspected; C1 supplied actual two-browser comment delivery evidence.
- [x] Messaging ready: existing Buyer/Seller conversation/history and navigation confirmed; C1 supplied actual send/reply polling evidence. No send/read-cursor mutation during C3.

## Documentation QA

- [x] All **22 diagrams rendered and visually checked** using existing headless Edge and isolated Mermaid **11.12.0**. No project dependency/lockfile changed.
- [x] Routes checked: **26 important method/path pairs**, all present in actual `artisan route:list --json`; auth/products/cart/checkout/orders/Seller/Admin/messaging/auction/checkout/notifications/video/comments/callback covered.
- [x] README/index/local final-document links checked: **109 local links across 16 documents**, including one local heading anchor, with zero broken links. Canonical index links to this checklist; changed Markdown has no trailing whitespace and `git diff --check` passes.
- [x] Demo script checked: **45 read-only browser checks**, **35 navigations**, **eight API reads** against actual Laravel/MySQL and dedicated C1 accounts. Automated navigation took 46 seconds; this is not an observed timed live 9:15 purchase/bidding demo. Presentation target is **9:15**, with 45 seconds buffer in a ten-minute slot and explicit no-write fallback.
- [x] Defense Q&A checked: frontend navigation vs backend permission, persisted DB/REST vs Reverb, locks/DECIMAL/BCMath, payment verification and limitations remain accurate. No production-scale/penetration/load/payment-completion claim was added.
- [x] States/terminology checked against enums/model/services: Seller is USER owning Shop; lifecycle SCHEDULED/LIVE/ENDED/CANCELLED; settlement diagram uses actual nullable/NO_WINNER/AWAITING_CHECKOUT/ORDER_CREATED/CHECKOUT_EXPIRED values. Conceptual Product eligibility states are explicitly not Product enum values.

### Confirmed diagram/document corrections

Six sequence diagrams initially failed actual Mermaid parsing because message semicolons were treated as statement delimiters. Replaced them with commas/slashes and wrapped long messages. All seven sequences now render.

Two horizontal architecture diagrams measured roughly 2,004 and 2,114 pixels wide; vertical layouts now measure about 520 and 809 pixels wide. Domain ERDs omit repetitive nonessential fields on reference entities. Full cyclic ERD remains about 4,456 pixels wide, explicitly reference-only; use domain views/native pan/zoom for defense instead of scaling the full graph to a projector page.

Lifecycle/Product transition label overlaps were removed. Settlement displays actual API labels with the conceptual mapping retained in prose. Arrow direction and differently labeled FK relationships were reviewed; Buyer/Seller or seller/winner edges are distinct relationships, not accidental duplicates. No clipped node was observed in native-size exports. Long domain/sequence views still require scrolling/zoom in small Markdown viewers.

Rendering followed the [Mermaid JavaScript API approach](https://mermaid.js.org/intro/getting-started.html#_4-calling-the-mermaid-javascript-api); the isolated bundle/HTML/SVG/PNG evidence is in ignored `.runtime`, not application assets or committed uploads. Native Mermaid rendering still depends on the reader's Markdown viewer/version.

## Security and repository hygiene

- [x] Private env/secrets are not tracked/staged in the current reviewed tree. Common secret-assignment/private-key scans of tracked current text found no concrete secret values; this is a targeted hygiene check, not a full historical secret audit.
- [x] No temporary application upload was introduced by C3. C1 temporary fixtures/uploads were cleaned; required tiny test media and repository-owned catalog assets are intentional source files and retained.
- [x] Env files, `.runtime`, vendor/node_modules, frontend dist, application storage/logs are ignored. Tracked storage entries are `.gitignore` placeholders only. No tracked temporary DB/dump/log/dependency directory was found.
- [x] Clean repository state is **described accurately**: the tree is **not clean**. Before adding this report it contained 91 modified, 14 deleted and 168 untracked status entries from earlier authorized phases/C2. Untracked entries include directory groups, not a literal file count. This new report adds one untracked entry. Index contains **zero staged files**.
- [x] Application source preservation: hashes for `backend/app`, `config`, `database`, `routes` and `frontend/src` unchanged, with no new application source files. All **22 captured business-table snapshots remained identical** during C3. Session files are operational auth data outside this business-table comparison.
- [ ] A clean committed/tagged `v1.0-demo` checkpoint exists. Current HEAD is `4faed97` (`Complete secondhand marketplace MVP`); there is no `v1.0-demo` tag. Earlier uncommitted phase work must be reviewed/included before a tag can represent the current feature set. No automatic staging of unrelated work or tagging of the old HEAD was done.

## Freeze handoff

The application feature set remains frozen. This report prepares the review boundary; it does not discard earlier work to force a clean status. To complete a Git checkpoint, review the full existing source/docs/dependency/removal inventory, preserve required owned assets and tiny fixtures, exclude ignored private/generated artifacts, commit the reviewed snapshot, then create an annotated `v1.0-demo` tag on that commit and verify a clean status. No remote push/publication is part of C3.

C3 changed only:

- `docs/final-release-checklist.md` (created)
- `docs/final-sequences.md`, `docs/final-architecture.md`, `docs/final-erd.md`, `docs/auction-state-machines.md`
- `docs/demo-script.md`, `docs/final-project-index.md`, `README.md`

## Known limitations

- ZaloPay Sandbox creation/gateway reachability verified in C1; production payment and final external paid callback/completion/query are not manually verified. COD is the deterministic fallback; public callback testing needs an HTTPS tunnel.
- No production load/stress/multi-node or physical-device testing, formal penetration/WCAG certification or backup-restore certification.
- Local file sessions/uploads and browser-native codecs; no video transcoding/CDN. Demo videos are explicitly tiny test-pattern clips.
- Exact bundled XAMPP MariaDB/older PHP compatibility remains unverified; current demo uses compatible PHP 8.4 and the existing local MySQL service.
- Full ERD intentionally dense/reference-only. C3 dry-run preserves data, so fresh write/realtime execution and observed live presentation timing are not newly claimed.
- Git checkpoint: a local checkpoint commit and the two fix-wave commits now exist (2026-10-12, see [fix wave 1–2 verification](fix-wave-1-2-verification.md)); nothing is pushed or tagged, so this is still not a published/tagged release.

### Known functional limits (audit of 2026-10-12)

- **No automatic ZaloPay refund.** The application never calls a refund API. Since D5 an Admin records the outcome of a manual refund (`REFUNDED_OUTSIDE` → payment `REFUNDED`); the money itself is returned outside the application. A paid, unflagged online order still cannot be cancelled in the application.
- **Reconciliation is a recorded decision, not an automated process (D5).** `/admin/payments` lists flagged payments and stores `REFUNDED_OUTSIDE` or `KEEP_PAID` with a mandatory note; partial refunds and re-opening a resolved payment are not modelled.
- **Admin auction cancellation exists since D5** for SCHEDULED/LIVE auctions, including after bids. An ENDED auction cannot be cancelled; a winner who is still inside the checkout window keeps that right unless the product is hidden.
- **Hiding a product never cancels an auction implicitly (D5).** A SCHEDULED/LIVE auction blocks hiding until an Admin cancels it explicitly; a product awaiting winner checkout can be hidden, which notifies the winner and makes checkout fail. Suspending a shop does cancel its SCHEDULED/LIVE auctions in the same transaction.
- **Every write transaction locks the whole `categories` table** (bids, cart writes, checkout, product edits, video comments). Correct, but it serialises writes and will not scale to high concurrency.
- **Scheduler dependency.** Auctions become LIVE/ENDED and ZaloPay attempts are reconciled only while `php artisan schedule:work` (or cron) is running.

**Decision: PASS for C3 documentation/visual QA and local-demo freeze documentation. Clean Git release checkpoint is NOT COMPLETE until the reviewed snapshot is committed/tagged.** No new application defect requiring a code fix was discovered in this scope.
