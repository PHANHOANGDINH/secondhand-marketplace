# UI redesign v3 (neutral + orange, simpler flows) — verification

Date 2026-10-16. Frontend only: no API, store, route or backend change. Choices made with the user: neutral palette with the logo orange for actions, a one-row header, all four flow groups, delivered in one go.

## Colours (`styles/tokens.css`, same token names)

| | Light | Dark |
| --- | --- | --- |
| page / card / panel | #F8F8F7 / #FFF / #F1F1EF | #0F1012 / #17181B / #1F2024 |
| border / text / muted | #E4E4E1 / #18181B / #63636B | #2C2D33 / #F4F4F5 / #A1A1AA |
| button, link (text on it) | #C2410C (#FFF) | #FB923C (#18181B) |
| price | #C2410C | #FDBA74 |
| soft orange (hero, active chips) | #FFF1E8 | #2A1A10 |

The header is now a plain surface (no orange band); orange is kept for the logo, buttons, prices and the home hero tint. `node frontend/scripts/contrast.mjs`: **46/46** pairs pass in both themes. The auction area keeps its own dark palette.

## Layout and flows

- **Header, one row** (`layouts/DefaultLayout.vue`): menu · logo · search pill with the area button *inside* it and a round submit · wishlist, bell, chat, cart · "Quản lý tin" (≥ 1500 px) · "Quản trị" · **Đăng tin** · account. The search takes the remaining width and never squeezes the area label onto the icon — the overlap in the reported screenshot. Under 992 px the search drops to its own row.
- **Account menu**: name/e-mail, the mode switcher drawn open as a segmented control (Mua / Bán / Quản trị) with its selling or admin shortcuts, personal links in two columns, the three theme choices, "Xem lại hướng dẫn" and sign-out. The header sun/moon button now only shows for guests.
- **Phones**: a bottom tab bar (`components/layout/MobileTabBar.vue`): Trang chủ, Đấu giá, **Đăng tin**, Tin nhắn (unread badge) or Tìm kiếm, Tài khoản/Đăng nhập. Seller and admin top bars collapse to one row of icons with an icon menu button.
- **Short page headers** (`PageHeader`): title and actions on one row; the slogan eyebrow is gone (also from dialogs), and the "Không gian người bán / Quản trị" eyebrow is hidden inside those areas.
- **Checkout, cart, auction checkout**: the summary stays beside the form on wide screens; under 992 px the total and the main button ride along at the bottom of the screen.
- **Đăng tin / sửa tin**: for a new listing the text fields come first (a draft must exist before photos), the save button sticks to the bottom of the form, and the preview with "Đăng bán" stays in view on wide screens. "Tải lại dữ liệu" only shows after an error.
- **Quản lý tin**: 72 px rows, status tabs stick under the header on wide screens.
- **Kho hàng**: stock is edited in a side panel next to the list, with − / + buttons, instead of a form at the top of the page.
- **Đấu giá của tôi**: create form, "Bán ngay" and "Huỷ phiên" open in side panels instead of at the top/bottom of the page.
- **Địa chỉ, Danh mục (admin)**: the form stays beside the list while it scrolls; on phones "Sửa" scrolls to the form.
- **One dialog at a time**: the first-visit area picker no longer opens on top of the quick-listing dialog.
- Tables: compact rows, small uppercase headers.

## Checks

| Check | Result |
| --- | --- |
| Vitest | **518/518**. Updated for the new structure: mode switcher inside the account menu (Layout), search submit selector (UiCore), wishlist link in the account links (Wishlist), stock "Lưu" button (SellerInventory), PageHeader without default eyebrow (UiCore) |
| oxlint / eslint (no `--fix`) / build / contrast | 0 / 0 / OK / 46 of 46 |
| E2E `theme.mjs` on the test-DB stack | **T1–T4 PASS**, 48 screenshots (`docs/ui-redesign/e2e/T-*`, `R-*`, light + dark, 1440 + 390), **0** horizontal overflow, **0** JS errors, **0** low-contrast texts, **0** light boxes in dark |

The first E2E run found and led to fixes for: seller/admin top bars 469 px wide on phones (an old `width: 100%` rule), the area picker opening over the quick-listing dialog, duplicated buyer links in the account menu, the cart link showing in the phone summary bar.

## Incident during E2E clean-up (resolved)

The test seed writes catalogue illustrations to `storage/app/public/products/{id}/vn-*.svg`, the same folder the development site uses. This time the test product IDs overlapped development ones, so **14 development images in 54 folders were overwritten and 19 extra files were added**. They were regenerated from `MarketplaceCatalogSeeder::illustration()` for the development product at each ID (reading the development database only, no DB writes) and the extra files removed. Afterwards **all 182 catalogue images of the 137 development products match exactly** and no image row points to a missing file. `frontend/e2e/README.md` now describes the check.

## Open

- Isolating test storage (a separate public disk root for the E2E stack) would remove this risk; not changed without approval.
- Seller and admin top bars on phones hide the sun/moon button for lack of room; the theme can be changed from the marketplace account menu.

## Follow-up: details first, "Sửa" opens the form

- **Gian hàng của tôi**: name, status, introduction and area as details; "Sửa thông tin" opens the form in a side panel (Lưu / Hủy). A new shop still fills the form on the page; a suspended shop shows details only.
- **Hồ sơ**: details (e-mail, name, phone); "Sửa hồ sơ" switches to the form, "Hủy thay đổi" restores and closes it.
- **Địa chỉ**: the list only; "Thêm địa chỉ" (page header) or "Sửa" on a card opens the form in a side panel.
- **Danh mục (admin)**: the table only; "Thêm danh mục" or "Sửa" opens the form in a side panel, and "Xóa" asks for the reason in a side panel instead of under the table.
- "Tải lại" buttons on these pages only show after an error.
- Checks: vitest 518/518 (DomainViews updated to press "Sửa"/"Thêm" first), oxlint 0, eslint 0, build OK; screenshots `docs/ui-redesign/e2e/S-*` (1440 + 390, no overflow, no JS error) taken without reseeding; storage file list and timestamps unchanged afterwards.
