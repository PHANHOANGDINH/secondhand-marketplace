# UI redesign v4 — direction "Đời thứ hai" — verification

Date 2026-10-21. Frontend only (no API, store, route or backend change). Process: the 4-step UI workflow (orient → build → polish → verify).

## Direction

Three directions were mocked in `docs/design-directions/index.html` (A "Tem giá chợ", B "Đời thứ hai", C "Chợ đêm"); the user chose **B**. `DESIGN.md` now describes it: forest ink #12302A and mint #D7EFE6 on #F3F6F4, buttons #1F6F5C (dark theme #5CC4A5 on #0B1714), Lexend for the interface, Fraunces for titles and big figures, the logo keeps its orange, auction area still always dark.

## What changed

- **Tokens** (`styles/tokens.css`): new light and dark values for every existing token, plus `--field`, `--on-field`, `--on-field-muted`, `--field-edge` and `--font-display`; radii 12/14/20/28, buttons fully round. `scripts/contrast.mjs`: **52/52** pairs pass (added the field pairs).
- **Home hero**: one rounded forest field with the Fraunces headline "Mỗi món đồ cũ là một đời mới cho ai đó.", a plain sentence, the search pill (area inside) and the live number of listings waiting for a new owner (catalogue total; hidden when unknown). Decorative tiles removed.
- **Type**: Fraunces on page titles, product title, dialog and sheet titles, the sign-in headline; balanced wrapping on headings; tabular figures for money.
- **House rules**: no eyebrows above headings, no tracked capitals for labels (sidebar captions and record captions now sentence case; voucher codes keep capitals), no decorative gradients (onboarding art and sign-in panel are flat mint), glyph "icons" (＋, ✕) replaced by the SVG icon set; browser surfaces themed (selection, caret, scrollbars, link underlines).
- **Fixes found while checking**: the revenue chart could keep its desktop width for a frame on phones (1106 px overflow on the seller dashboard); the chart box now clips. Staged photo previews carry width and height.

## Checks

| Check | Result |
| --- | --- |
| Vitest | 535/535 (Home hero headline test updated) |
| oxlint / eslint (no `--fix`) / build / contrast | 0 / 0 / OK / 52 of 52 |
| web-design-guidelines on the changed files | 3 findings (heading wrap ×2, image dimensions), all fixed; promo carousel pauses on hover/focus |
| E2E `theme.mjs` (test DB) | T1–T4 PASS, 48 screenshots light + dark at 1440 and 390, 0 overflow, 0 JS errors, 0 low-contrast texts, 0 light boxes on dark pages |
| Shared storage | full sha1 snapshot before seeding; 60 overwritten development illustrations regenerated, 10 extra files, 107 test folders and 4 test videos removed; afterwards **identical to before (187 files)** |

Open: the seeded product illustrations still use their own pastel hues (they are content, not theme); the checkout keeps "01 · / 02 ·" because the steps are a real sequence.
