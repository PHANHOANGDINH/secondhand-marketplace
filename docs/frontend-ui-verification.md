> Historical verification report. Container infrastructure has since been removed. Results below retain their original context; use the [current local setup](deployment-guide.md) and [local verification](xampp-local-verification.md) for present instructions.

# Frontend UI verification — Flow 5

Date: 2026-10-06. Deployment origin: `http://localhost:8088`.

## Scope and final design system

This pass preserves the existing marketplace, Account, Seller and Admin layouts. Changes are limited to frontend presentation and regression coverage. Backend, API contracts, stores, routes, authentication, authorization, payment/order handlers and moderation rules were not edited. No dependency was added.

Bootstrap variables remain the color source of truth: primary for main actions, success for successful outcomes, warning for pending/unavailable states, danger for failures/destructive actions, secondary text for supporting information, and existing body/tertiary backgrounds and border colors for surfaces. No new accent palette was introduced.

`src/styles/ui.css` provides the common control foundation; `buyer.css`, `seller.css` and `admin.css` retain their page-specific layouts. Duplicate button/form/focus/reduced-motion foundations were removed from the area styles.

| Element | Final convention |
| --- | --- |
| Typography | Existing Bootstrap system font; marketplace page titles remain larger than dense Seller/Admin titles. Shared PageHeader, strong section headings, readable secondary text. |
| Buttons | Normal controls: 42 px minimum height, 0.88 rem text, weight 600, 0.65 rem radius. Small controls: 36 px minimum height and 0.8 rem text. Homepage storytelling retains its own cinematic composition. |
| Forms | 44 px minimum input/select height, 0.9 rem control text, 0.82 rem labels at weight 600; helper/error text at 0.8 rem with 1.6 line height. Existing required fields have decorative required markers; native required/validation behavior is preserved. |
| Cards | Existing restrained borders/shadows and approximately 0.9–1 rem radii; existing compact dashboard spacing is retained. |
| Actions | Save/create/checkout/confirmation retain primary emphasis. Buyer, Seller and Admin filter/report controls use secondary outline buttons. Existing destructive confirmation actions retain danger styling. |
| Focus | Shared 3 px primary outline with 3 px offset on links, buttons, inputs, selects, textareas and disclosure summaries. |
| Feedback | Existing DomainFeedback, EmptyState and LoadingState are retained/reused. Empty orders and public reviews are hidden during loading/errors, including stale results from retries. |

StatusBadge preserves the original status values and labels:

| Meaning | Status examples | Tone |
| --- | --- | --- |
| Success | ACTIVE, PUBLISHED, PAID, COMPLETED | Success |
| Pending/unavailable | PENDING, PROCESSING, HIDDEN, SUSPENDED | Warning |
| Failure/cancellation | REJECTED, FAILED, CANCELLED | Danger |
| Informational | DRAFT, REFUNDED and unspecified statuses | Neutral |
| Fulfillment progress | CONFIRMED, SHIPPING, IN_PROGRESS | Primary |

## Confirmed fixes and reviewed flows

- Auth forms now use the existing PageHeader and shared spacing/control/label styling, without changing login/registration behavior.
- Public shop list/detail pages now match the marketplace header, filter, card and empty-state presentation. ShopProducts reuses EmptyState.
- The header's active/hover link selector now excludes button links. Previously the guest “Đăng bán” CTA could use primary-colored text on its primary background while login was active.
- Required markers were added to existing required DomainField/auth controls without changing any field constraints.
- Status colors and filter button hierarchy are consistent across areas.
- Stale empty order/public-review messages no longer compete with loading/error feedback.
- Existing completed layouts, state transition actions, upload behavior and checkout/payment behavior were preserved.

Reviewed journey inventory:

| Area | Screens/states |
| --- | --- |
| Homepage | Five-scene story, normal content after the story, CTA navigation, reduced-motion static fallback and SPA cleanup. |
| Public/Buyer | Products, product detail/gallery/reviews, shops/shop detail, cart, COD/ZaloPay checkout choices, payment results, order list/detail and review form. |
| Account | Account overview, profile, addresses, inline deletion confirmation/cancel/focus behavior. |
| Seller | Dashboard/KPIs/revenue chart, product list, create/edit form and image controls, order list/detail and shop form. |
| Admin | Dashboard, users list/detail, shops/products moderation presentation, categories, read-only orders/detail, reviews list/detail. |

## Verification method and data preservation

The production frontend is served by the existing Docker Nginx container on localhost:8088. Only the frontend/Nginx image/container was rebuilt/replaced; backend and MySQL services were retained.

Live public requests checked homepage, product/shop empty lists, login and registration. Populated authenticated visual screens, payment states and simulated failures used **browser-only intercepted responses**, based on existing frontend test shapes. Fixture responses are not included in the application build. No real moderation, checkout, registration, upload or other business write was sent in this pass. Both development and Docker databases and uploads were left untouched; there are no database fixtures to clean.

The existing business/security frontend tests still cover handlers and contracts. This visual pass does not claim a fresh live gateway/payment/moderation integration test.

## Responsive and accessibility checks

Viewports: **1440, 1024, 768 and 390 px**. Edge/Chromium headless viewport emulation was used.

- Page-level horizontal overflow and loaded image URLs checked across the listed screens, including tables, forms, galleries, dashboard metrics and charts.
- Form labels and accessible names checked for inputs/selects/textareas; existing icon-only image controls keep their explicit names.
- Address confirmation focuses the safe cancel action; Escape dismisses and restores trigger focus.
- Seller/Admin mobile navigation opens/closes and closes after route selection.
- SPA links are exercised between marketplace, cart/checkout, Account, Seller and Admin screens.
- Homepage animation is removed after SPA navigation; reduced-motion mode keeps all five scenes readable and static.
- Header CTA text/focus visibility and settled layout stability checked. This is a focused contrast review, not a formal WCAG certification or screen-reader audit.
- Lazy images are decoded before declaring an image broken. Browser screenshots and local machine-readable results are stored under the gitignored `.runtime` directory.

## Commands and final results

Commands run from the repository root unless indicated:

```powershell
cd frontend
npm.cmd run test:unit -- --run --maxWorkers=2
npm.cmd run lint
npm.cmd run build
cd ..
docker compose --env-file .env.docker-verification build nginx
docker compose --env-file .env.docker-verification up -d --no-deps nginx
```

The existing private Docker verification env file is not published and contains no credentials in this report. No migration command was run.

| Check | Result |
| --- | --- |
| Full frontend suite | PASS — 179 tests across 17 files; four meaningful consistency/accessibility/feedback regressions added. |
| Frontend lint | PASS — Oxlint and ESLint. |
| Production build | PASS — 180 modules transformed; no new dependencies. Shared CSS bundle: 263.99 kB raw / 36.85 kB gzip; GSAP remains isolated to the homepage. |
| Docker frontend build | PASS — frontend image rebuilt successfully. |
| Broad browser matrix | PASS — 186 screen/state/viewport checks on the final image across 1440, 1024, 768 and 390 px. |
| Focused SPA/mobile/contrast checks | PASS — 45 additional screen/viewport checks; actual SPA links, pin cleanup, responsive navigation, confirmation/focus, CTA visibility, reduced motion and settled layouts. |
| git diff --check | PASS. |

One earlier parallel run exceeded the existing 5-second timeout on a catalog test while Docker was building. Complete reruns with two workers passed without changing tests or timeout configuration.

## Files changed in this pass

All source paths below are relative to `frontend/src`:

- Added `styles/ui.css` and `__tests__/VisualConsistency.spec.js`.
- Refined `styles/buyer.css`, `styles/seller.css`, `styles/admin.css` and `layouts/DefaultLayout.vue`.
- Refined `components/AuthForm.vue`, `components/DomainField.vue`, `components/ProductReviews.vue`, `components/ShopProducts.vue`, `components/AdminModerationPanel.vue` and `components/ui/StatusBadge.vue`.
- Refined `views/ShopsView.vue`, `views/ShopView.vue`, `views/ProductsView.vue`, `views/OrdersView.vue`, `views/SellerDashboardView.vue`, `views/SellerProductsView.vue`, `views/AdminOrdersView.vue`, `views/AdminProductsView.vue` and `views/AdminShopsView.vue`.
- Added this report, `docs/frontend-ui-verification.md`.

Earlier Buyer/Account/Seller/Admin work remains in the workspace. Package changes visible in git status belong to earlier work; this pass added no package.

## Known remaining limitations

- Authenticated populated browser checks use visual fixtures; fresh live state-changing flows and real ZaloPay Sandbox checkout are outside this final presentation-only verification. Gateway credentials/configuration and prepared real demo data are still required for an end-to-end live payment demo. No production payment is claimed.
- Admin shop owner display depends on existing API fields and may show an ID. Admin order transaction/payment details are unavailable in the existing Admin API; the explanatory fallback remains visible.
- Physical devices, Safari/Firefox and a complete assistive-technology audit were not exercised.
- During frontend replacement, already cached HTML/open tabs can reference removed hashed chunks. The dedicated verification browser cache was cleared for the final image. Hard-refresh/reopen the demo after deployment; retaining old assets or changing deployment cache policy would require deployment work beyond this frontend-only scope.
- Loading/error states intentionally preserve existing business behavior; no new retry/pagination/metrics/moderation capabilities were added.

## Decision

Final visual verification: **PASS** — 179 tests across 17 files, lint, production build, Docker frontend build, and **231 browser screen/state/viewport checks** passed. No unexpected application console errors, broken images, page-level horizontal overflow, stuck homepage pin or backdrop remained in the verified flows.

READY FOR DEMO: **YES for the frontend UI**, using a fresh/reloaded page after deployment. A populated live transaction demo requires prepared authorized accounts/data and working Sandbox configuration. Live payment/production readiness is not certified by these browser-only visual fixtures.
