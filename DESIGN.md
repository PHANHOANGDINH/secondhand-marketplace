# Passdi — design system

Passdi ("Không dùng nữa? Passdi!") is a Vietnamese second-hand marketplace for buyers, sellers and admins. Direction **"Đời thứ hai"** (second life, chosen 2026-10-20): calm and trustworthy, forest green and mint on a cool off-white, a soft serif for titles, and the idea that every listing is an object starting a new life with someone. The logo is green too: an open box in a round badge, with the hand-lettered "passdi"; everything you can do is forest green. Short pages, the main action always in reach. Every new screen follows this file; do not pull palettes or fonts from outside it.

Source of truth for values: `frontend/src/styles/tokens.css` (token names never change; only values per theme). Contrast of every text/background pair is checked by `node frontend/scripts/contrast.mjs` (must stay all "ok"). The three directions compared before choosing are in `docs/design-directions/index.html`.

## 1. Colour

Use tokens, never hex in components. Light and dark are set on `<html data-theme data-bs-theme>`; Bootstrap follows.

| Token | Light | Dark | Use |
| --- | --- | --- | --- |
| `--bg` | #F3F6F4 | #0B1714 | page, header band |
| `--surface` / `--surface-2` | #FFF / #E8EFEB | #11211D / #172C27 | cards / quiet panels, inputs at rest |
| `--border` | #D5E0DB | #234039 | 1 px structure where a tone step is not enough |
| `--text` / `--muted` | #12302A / #4A615B | #E3F1EC / #9CB9AF | forest ink / secondary |
| `--primary` (+ `--on-primary`) | #1F6F5C (white) | #5CC4A5 (#0B1714) | buttons, links, active nav, focus ring |
| `--primary-soft` / `--accent` | #D7EFE6 | #17362E | mint: soft highlights, chips, tags, selection |
| `--field` (+ `--on-field`, `--on-field-muted`, `--field-edge`) | #12302A | #163A31 | the one large block of colour: home hero, sign-in showcase |
| `--brand` / `--brand-text` | #3A8A5C / #2F7F52 | #3A8A5C / #6FBF8B | logo green: decoration and the "di" of the wordmark |
| `--price` | #12302A | #E3F1EC | amounts: forest ink, semibold, tabular figures |
| `--danger` / `--success` (+ `--on-*`) | #DC2626 / #15803D | #F87171 / #4ADE80 | errors, discounts / paid, success |
| `--inverse` (+ `--on-inverse`) | #12302A | #5CC4A5 | strong pills (banner CTA) |
| `--rating` | #B45309 | #F5B544 | stars |
| `--scrim`, `--shadow-rgb` | — | — | dialog backdrops, shadows of floating layers only |

**Auction area** (`.theme-auction`) is always dark (navy #0E0B1F, neon #C6FF3D, live #FF3D7F) whatever the site theme; it pins every shared token itself.

## 2. Type

- UI and body: **Lexend** 400–700 (`--font-ui`), very legible with Vietnamese marks; body 0.9–1.05 rem, line-height 1.5–1.65.
- Titles, the home headline and big figures: **Fraunces** 500–600 (`--font-display`), tracking about -0.012em, line-height 1.05–1.2. One serif voice, never mixed into body text.
- Money and voucher codes: tabular figures; **JetBrains Mono** only for codes.
- Wordmark: the hand-lettered artwork in `BrandLogo`, "pass" in `--text` and "di" in `--brand-text`; never retyped. The ribbon "Tiếp nối hành trình" of the full logo is not used in small sizes, where it cannot be read.
- No eyebrows above headings and no tracked capitals for labels: captions are sentence case at 0.8 rem.

## 3. Space, shape, motion

- 4 px scale `--space-1…12`; cards pad `--space-5`/`--space-6`, phones `--space-4`.
- Radius: 12 (inputs) · 14 · 20 (cards) · 28 (big panels, dialogs) · 32 (the forest field) · pill for **every button**, chips and the search.
- Controls and tap targets **≥ 44 px** (`--control-height`).
- Focus: `--focus-ring` (3 px green) on every interactive element, visible on keyboard focus. Selection, caret, scrollbars and link underlines are themed from the palette.
- Motion: `--transition-fast` (150 ms) for hover/press; GSAP only for small entrances (home banner, auth, onboarding), always behind `prefers-reduced-motion: no-preference`.

## 4. Layout

- **Home hero**: one rounded forest field holding the Fraunces headline, one plain sentence, the search pill (area inside it) and the live count of listings waiting for a new owner (catalogue total, hidden when unknown). No decorative tiles.

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
