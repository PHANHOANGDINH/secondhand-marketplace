# Final project documentation index

**FINAL C2: PASS — documentation/defense pack ready.** Feature freeze retains C1's local-demo scope. C2 changes Markdown only: no schema/routes/business code/credentials/development data changes.

## Reading order

| Document | Purpose |
| --- | --- |
| [README](../README.md) | Requirements/env/local setup/migrations/storage/seeder/four processes/tests |
| [System overview](final-system-overview.md) | Purpose/roles/stack/data flow |
| [Module inventory](final-module-inventory.md) | Delivered actors/rules/APIs/security |
| [Architecture](final-architecture.md) | REST, realtime and payment |
| [ERD](final-erd.md) | Business relationships and six domain ERDs |
| [Use cases](final-use-cases.md) | Human/external/Scheduler actors |
| [Sequences](final-sequences.md) | Seven core request/event/transaction flows |
| [Auction states](auction-state-machines.md) | Lifecycle, derived settlement, Product restrictions |
| [Security](final-security-summary.md) | Controls/limits |
| [Testing](final-testing-summary.md) | C1 evidence/limitations |
| [Defense demo](demo-script.md) | 7–10 minute timing/clicks/talking points/results/fallback |
| [Defense Q&A](defense-qa.md) | Actual implementation answers |
| [C1 verification](final-demo-verification.md) | Integration fixes, inventory, browser/data evidence |
| [C3 release checklist](final-release-checklist.md) | Rendered diagram QA, read-only rehearsal, repository/freeze boundary |

## Supporting references

[API summary](api-summary.md), [local troubleshooting](deployment-guide.md), [local verification](xampp-local-verification.md), [password reset](password-reset-verification.md), [messaging](messaging-verification.md), [frontend UI](frontend-ui-verification.md).

Auction: [A1](auction-a1-verification.md), [A2](auction-a2-realtime-verification.md), [A3](auction-a3-ui-verification.md), [A4](auction-a4-settlement-verification.md), [A5](auction-a5-notifications-verification.md).

Video: [B1](video-b1-verification.md), [B2](video-b2-theater-verification.md), [B3](video-b3-comments-verification.md).

Original requirements/database-design/use-cases/state-transitions are historical planning snapshots, with superseded future features/descriptions. Phase reports retain their original scope/counts. Use current code/C2 for defense and C1 for final runtime totals. Older deployment reports are historical, not current startup instructions.

## Reconciliation notes

- Settlement values are exactly `null` / `NO_WINNER` / `AWAITING_CHECKOUT` / `ORDER_CREATED` / `CHECKOUT_EXPIRED`, computed by `Auction::settlementState()` on every read. They are not a database column or enum; older planning names for these states are not used anywhere in code or API.
- Extensions D1 search, D2 wishlist, D3 reports and **D4 Seller Inventory & Action Center are delivered ✅** ([D4 verification](extension-d4-seller-inventory-verification.md)). Post-audit corrections: [fix wave 1–2 verification](fix-wave-1-2-verification.md).
- **D5 Admin Action Center is delivered ✅** ([D5 verification](extension-d5-admin-action-center-verification.md)): aggregate work queue at `/admin`, ZaloPay reconciliation decisions, Admin auction cancellation, the rule that a running auction must be cancelled before its product is hidden, automatic auction cancellation when a shop is suspended, and report moderation fixes.
- **UI redesign (rounds 0–7), seller card stats and buy now are delivered ✅** ([verification](ui-redesign-verification.md)): design tokens, new header and listing cards, dark auction theme, first-time onboarding; `ProductResource` adds `has_video` and `shop.rating_avg` / `rating_count` / `joined_at`; `POST /checkout` accepts an optional `cart_item_ids` selection used by "Mua ngay".
- **D6 Vouchers is delivered ✅** ([D6 verification](extension-d6-vouchers-verification.md)): one PLATFORM code per order and one SHOP code per shop order, percentage or fixed, rounded down to whole đồng with BCMath; proportional split of the platform discount; redemption and release on cancellation; idempotency covers the codes; `POST /checkout/preview`; seller and admin voucher screens. Shop discounts are funded by the shop, platform discounts by the platform; settlement reporting is out of scope.
- **D8 Final Integration is delivered ✅** ([D8 verification](extension-d8-final-integration-verification.md)): demo seeder data for D4–D7, browser journeys A–H on an isolated test stack ([frontend/e2e](../frontend/e2e/README.md)), final regression and documentation; local tag `v1.0-defense`. The seeder was then run once on the development database (54 rows added, two existing rows changed by the real checkout; a second run changed nothing).
- **Passdi brand and dark mode are delivered ✅** ([verification](passdi-brand-dark-mode-verification.md)): Passdi logo, favicon and slogan; colour tokens from the logo with a contrast check; light/dark/system theme without a white flash; the auction area stays dark; demo voucher PASSDI10 (PASSLAI10 switched to INACTIVE, not deleted).
- **UI redesign v3 is delivered ✅** ([verification](ui-redesign-v3-verification.md)): neutral palette with the logo orange for actions, one-row header with the area inside the search, account menu holding modes and theme, phone tab bar, short page headers, sticky order summaries and listing actions, side panels for stock, auction creation, "Bán ngay" and cancellation.
- **D7 Recommendations is delivered ✅** ([scoring formula](recommendation-scoring.md)): `GET /recommendations` and `GET /products/{slug}/similar`, the "Gợi ý cho bạn" home section and the "Sản phẩm tương tự" product-detail section, plus the additive `product_views` and `search_logs` tables. D6 was skipped by decision; D7 was built directly on the D5 commit.
- VNPAY/REFUNDED and video PROCESSING/FAILED schema values do not prove delivered VNPAY/refund/transcoding workflows.
- Messaging polls; Reverb serves auctions/notifications/comments. Expiry is derived rather than a scheduler-written settlement enum.
- Admin Orders read-only; Seller fulfills. Older planning text calling delivered moderation future is superseded. Comment authors can also hide their own comments, alongside authorized Seller/Admin moderation.
- Public API visibility is not static-file revocation; callback MAC differs from authenticated query requests.

These are documentation clarifications, not confirmed new implementation defects; no code fix needed.

## C2 verification/status

Checked current routes, migrations/FKs, models/services/events, scheduler, frontend recovery and C1 evidence. Checked local Markdown links, Mermaid fence/type structure, ERD selected columns against migrations and whitespace. All 13 C2 documents/updates passed local link/fence checks; 22 diagram blocks and 263 selected ERD attribute occurrences matched inspected migration declarations. Mermaid rendering depends on the chosen Markdown viewer; a Mermaid renderer was not executed, so checks cover source structure rather than visual rendering. C1 suites were not rerun for docs-only edits.

Created eleven documents: overview, inventory, ERD, use cases, architecture, sequences, auction states, security, testing, Q&A and index. Updated demo-script and README. Includes **22 Mermaid diagrams**: seven ERDs, two use-case flows, three architecture flows, seven sequences, three auction/Product state diagrams.

**READY FOR LOCAL DEMO/DEFENSE**, with C1 payment/production/physical-device limitations. This is not production readiness.

## C3 visual QA and release freeze

All 22 diagrams were rendered locally in existing Edge using an isolated Mermaid 11.12.0 bundle, without changing application dependencies. Six sequence syntax errors caused by semicolons in messages were corrected; long labels were wrapped. Two architecture layouts were changed from horizontal to vertical. Domain ERDs use compact reference entities, with the wide full ERD explicitly retained as reference. Lifecycle/Product label overlaps were removed; settlement now displays actual API names alongside the conceptual mapping.

C3 checked 26 important route/method pairs and documentation links, audited Q&A/terminology, and completed 45 read-only browser checks/35 navigations/eight API reads. It did not repeat C1 writes or claim a fresh timed end-to-end payment. Application source and all 22 captured business tables remained unchanged. Demo target is now 9:15 with scheduling buffer.

**C3 documentation/visual QA: PASS.** The working tree still contains earlier-phase changes; no commit/tag was created or published. `v1.0-demo` is a prepared release label, not an existing Git checkpoint. See the checklist for exact repository status, hygiene evidence and remaining release steps.
