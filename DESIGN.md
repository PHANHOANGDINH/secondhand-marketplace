# Passdi — design system

Passdi ("Không dùng nữa? Passdi!") is a Vietnamese second-hand marketplace for buyers, sellers and admins. The UI should feel **calm, trustworthy and quick**: neutral surfaces, one warm orange for what you can do and what it costs, short pages where the main action is always in reach. Every new screen follows this file; do not pull palettes or fonts from outside it.

Source of truth for values: `frontend/src/styles/tokens.css` (token names never change; only values per theme). Contrast of every text/background pair is checked by `node frontend/scripts/contrast.mjs` (must stay all "ok").

## 1. Colour

Use tokens, never hex in components. Light and dark are set on `<html data-theme data-bs-theme>`; Bootstrap follows.

| Token | Light | Dark | Use |
| --- | --- | --- | --- |
| `--bg` | #F8F8F7 | #0F1012 | page |
| `--surface` / `--surface-2` | #FFF / #F1F1EF | #17181B / #1F2024 | cards / quiet panels, inputs at rest |
| `--border` | #E4E4E1 | #2C2D33 | 1 px structure (borders carry structure, not shadows) |
| `--text` / `--muted` | #18181B / #63636B | #F4F4F5 / #A1A1AA | body / secondary |
| `--brand` | #F26B3A | #F26B3A | logo orange: large areas and icons only, never small white text on it |
| `--brand-soft` | #FFF1E8 | #2A1A10 | home hero tint, onboarding art, soft highlights |
| `--primary` (+ `--on-primary`) | #C2410C (white) | #FB923C (#18181B) | buttons, links, active nav, focus ring |
| `--price` | #C2410C | #FDBA74 | every amount of money |
| `--danger` / `--success` (+ `--on-*`) | #DC2626 / #15803D | #F87171 / #4ADE80 | errors, discounts / paid, success |
| `--inverse` (+ `--on-inverse`) | #18181B | #FB923C | strong pills (e.g. banner CTA); orange in dark so no light box appears |
| `--rating` | #B45309 | #F5B544 | stars |
| `--scrim`, `--shadow-rgb` | — | — | dialog backdrops, shadows of floating layers |

**Auction area** (`.theme-auction`) is always dark (navy #0E0B1F, neon #C6FF3D, live #FF3D7F) whatever the site theme; it pins every shared token itself.

## 2. Type

- UI: **Be Vietnam Pro** 400–800 (`--font-ui`); body 0.9 rem, line-height 1.5–1.7.
- Money and codes: **JetBrains Mono** (`--font-money`) where figures must align.
- Wordmark only: **Baloo 2 800**, "pa**ss**di" with "ss" in `--brand-text`.
- Page title 1.35–1.75 rem/800, section 1.15 rem; uppercase eyebrows only outside the seller/admin shells.

## 3. Space, shape, motion

- 4 px scale `--space-1…12`; cards pad `--space-5`/`--space-6`, phones `--space-4`.
- Radius: 10 (controls) · 12 · 16 (cards) · 24 (big panels, dialogs) · pill (chips, header search, primary pills).
- Controls and tap targets **≥ 44 px** (`--control-height`).
- Focus: `--focus-ring` (3 px orange) on every interactive element, visible on keyboard focus.
- Motion: `--transition-fast` (150 ms) for hover/press; GSAP only for small entrances (home banner, auth, onboarding), always behind `prefers-reduced-motion: no-preference`.

## 4. Layout

- Breakpoints: **≤ 767** phone (bottom tab bar, one column), **≤ 991** tablet (search on its own row, side panels full-screen), **≥ 1200** shows wishlist and "Quản trị", **≥ 1500** shows "Quản lý tin" in the header. No horizontal scroll from 360 px.
- **Header: one row** — menu, logo, search pill with the area button inside it, icons, "Đăng tin", account menu. Modes (Mua / Bán / Quản trị), personal links and theme live in the account menu; guests get the sun/moon button.
- **Phones**: `MobileTabBar` (Trang chủ, Đấu giá, Đăng tin, Tin nhắn, Tài khoản); anything fixed to the bottom sits above it.
- Seller and admin areas: shell with left sidebar; on phones a one-row icon top bar.

## 5. Interaction patterns (reuse, don't reinvent)

- **Main action always in reach**: order summaries and the product buy form stick (side column on desktop, bottom bar on tablet/phone); long forms keep their save bar sticky.
- **Details first, "Sửa" opens the form** (shop, profile, addresses, categories) — no form open by default.
- **Side panel (`DetailSheet`)** for editing or confirming one row of a list (stock, auction create / "Bán ngay" / cancel, admin records) instead of forms at the top or bottom of the page.
- **Centred dialog (`AccessibleDialog`, native `<dialog>` for reports)** for short, focused tasks; **one dialog at a time**.
- **Money input (`MoneyInput`)**: shows `1.500.000`, comma for the fraction, sends plain numbers.
- **Stars**: `StarRating` to show, `StarPicker` (radio group, arrow keys) to choose.
- Status is never colour alone: badge text + colour (`StatusBadge`).
- Every list/page has loading (skeleton), empty (`EmptyState` with the next step), error (message + "Tải lại") states.
- Server truth only: payment results, eligibility and prices are re-read from the API, never trusted from the URL.

## 6. Voice

Vietnamese, short, concrete: verbs on buttons ("Đăng tin", "Bán ngay", "Kiểm tra lại"), say what happens next, explain why something is disabled. API enums stay English; labels are translated in `utils/labels`.

## 7. Before calling UI work done

Contrast script all ok · keyboard path and visible focus · `alt` on images, `label` on inputs · 44 px targets · light **and** dark checked · 360 / 390 / 820 / 1440 px without overflow · `npx vitest run`, `npx oxlint .`, `npx eslint .` (never `npm run lint`), `npm run build` clean.
