# Passdi brand and dark mode — verification

Date 2026-10-15, PHP 8.4.25 (Herd). Logic, API and enums unchanged; only branding, colour tokens, theme switching and two demo voucher rows.

## Brand

- `config/brand.js`: name **Passdi**, slogan **"Không dùng nữa? Passdi!"**; page titles are `<page> · Passdi`.
- `BrandLogo.vue`: the orange loop tile (SVG from the brief) plus the wordmark `pa`**`ss`**`di` in Baloo 2 800, "ss" in `--brand-text`. Props `size` (px), `variant` (`full` | `mark`); `compact` still works (34 px). At 32 px and below the arrowheads are dropped and the loop stroke is 6. The root is `role="img"` with `aria-label="Passdi"`.
- `public/favicon.svg` = the simplified mark; `public/apple-touch-icon.png` (180×180) is the full mark, exported from the SVG with headless Edge. `index.html` links both, keeps `favicon.ico` as a fallback, adds `theme-color` for light and dark, and loads Baloo 2 800.
- Backend: the temporary-password mail falls back to "Passdi"; the seeded admin is "Ban quản trị Passdi" (new seeds only).
- Vouchers: both seeders create **PASSDI10** (same terms as before). **PASSLAI10** is no longer created; when it exists and is ACTIVE a seeder run switches it to INACTIVE and keeps the row. `MarketplaceSeeder` does this even when the rest of its data is already present.
- Kept on purpose: storage keys `passlai:*`, the `@passlai.local` / `@example.test` accounts and the E2E profile folder (renaming them would sign people out or break data). The older verification documents still say "pass lại" / PASSLAI10 because they record what was true then.

## Colours (`styles/tokens.css`, names unchanged)

| Token | Light | Dark |
| --- | --- | --- |
| bg / surface / surface-2 | #FBF8F3 / #FFF / #F4EFE7 | #17130F / #211C17 / #2B251F |
| border / text / muted | #EADFCB / #2B2B2E / #6B6660 | #3A322A / #F4EFE7 / #A89F94 |
| brand (large areas, icons) | #F26B3A | #F26B3A |
| primary / hover / text on it | #C2410C / #9A3412 / #FFF | #F26B3A / #FF8A5C / #17130F |
| price | #C2410C | #FF8A5C |
| danger / success | #DC2626 / #15803D | #F87171 / #4ADE80 |

New tokens: `--brand`, `--brand-soft`, `--brand-text`, `--on-primary`, `--on-danger`, `--on-success`, `--price`, `--inverse` / `--inverse-hover` / `--on-inverse` (dark pills; orange in dark), `--band-ink-muted`, `--band-glass(-strong)`, `--rating`, `--shadow-rgb`, `--scrim`.

`node frontend/scripts/contrast.mjs`: **46/46 pairs pass** (≥ 4.5:1 for text, ≥ 3:1 for the large "ss" and icons) in both themes.

## Dark mode

- `<html data-theme="dark" data-bs-theme="dark">`; Bootstrap 5.3 follows, our `:root` aliases win over its dark values because tokens load after it.
- `composables/useTheme.js`: `system | light | dark`, default system (`prefers-color-scheme`, live), stored as `passdi:theme` with try/catch. "system" removes the key.
- An inline script at the top of `index.html` applies the same rule before any stylesheet or the app loads, so a dark page never flashes white.
- `ThemeToggle` (44 px, sun/moon, `aria-label`, `aria-pressed`) in the marketplace header (beside the logo on phones), the seller and admin top bars and the auth pages; `ThemeChoices` (Theo hệ thống / Sáng / Tối) in the account menu.
- Hard-coded colours moved to tokens: buttons and active nav items (`--on-primary`), danger badges, the "Đăng tin" pill and banner CTA (`--inverse`), scrims of dialogs/sheets/drawers, shadows, the header band chips, the auth panel gradient, the area-picker art, the first-time note, rating stars, `ProductPhoto` background. Bootstrap `.text-secondary` now reads the muted token, and `.text-bg-primary/success/danger` take their text colour per theme. Overlays on photos and videos stay black/white on purpose.
- `RevenueChart` reads `--primary`, `--border` and `--muted` and redraws when the theme changes.
- The auction area (`.theme-auction`) pins every shared token to dark values, so it looks the same whatever the site theme.

## Checks

| Check | Result |
| --- | --- |
| Vitest | **516/516** (49 files). New `Theme.spec.js`: useTheme (system default, live OS change, stored choice, blocked storage), the inline script run against `index.html` (no flash, works without storage or matchMedia), header toggle and the three menu choices, BrandLogo full/mark/≤32 px, favicon and touch icon |
| oxlint / eslint (no `--fix`) / build | 0 / 0 / OK |
| Backend (test DB) | **494/494** (6,481 assertions); `MarketplaceSeederTest` also checks PASSLAI10 → INACTIVE on re-seed; pint passed |

### E2E (isolated stack on `secondhand_marketplace_test`)

`bash frontend/e2e/go.sh theme.mjs` — **T1–T4 PASS**, 34 screenshots in `docs/ui-redesign/e2e/T-*` (light and dark, 1440 and 390): home, product detail, checkout, `/my-ads`, seller dashboard, admin dashboard, admin payments (dark), login, the account menu (dark) and the auction list under the light theme.

- T1: no stored choice → the theme follows an emulated OS light/dark from the first paint; the toggle is 44 px, flips to dark, is stored and survives a reload.
- In-page audit on every capture: **0** text elements below 4.5:1 (3:1 large) against the colour actually behind them, **0** light boxes larger than 30×30 px on dark pages (photos and videos excluded). The first run found and led to fixes for: `.text-secondary` (Bootstrap grey), white text on `text-bg-success` in dark, the favourite button text, the light "Đăng tin" pill and admin mode pill in dark, and a 7 px overflow at 390 caused by the new header button.
- No horizontal overflow, no uncaught JS errors. The intermittent 1106 px "overflow" seen on the seller dashboard in earlier runs comes from the Vue devtools overlay of the dev server, not the page.
- Clean-up: stack stopped, `secret.json` / `data.json` / `theme-report.json` and Edge profiles deleted, session files and the 159 product folders + 4 videos the test seed wrote removed (storage back to 137/2 entries).

## Open

- The development database was **not** re-seeded (not allowed): its PASSLAI10 is still ACTIVE, PASSDI10 does not exist and the admin is still named "Ban quản trị pass lại". Running `MarketplaceSeeder` once (with permission) fixes both voucher rows; the admin's name can be changed on its profile page.
