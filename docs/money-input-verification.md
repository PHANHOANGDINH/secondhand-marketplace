# Money input and minimum price — verification

Date 2026-10-16. PHP 8.4.25 (Herd).

## Behaviour

- **Typing amounts**: every money field shows thousands grouped with dots while typing (`1.500.000`); a comma starts the fraction (`1.500.000,5`, at most two digits). The API still receives plain amounts (`1500000`, `1500000.5`). The caret stays after the same digit when the text is regrouped. A pasted `500000.00` keeps its decimal point (4+ digits before a single dot is never grouping); `1.25` (e.g. after deleting the last digit of `1.250`) stays the whole number 125.
- Fields: product price and compare-at price (`DomainField type="money"`), voucher amount / max discount / minimum order, auction start price and increment, the bid box in the auction room, the price range filter on the product list. Component `components/ui/MoneyInput.vue`, helpers `utils/moneyInput.js`.
- **Minimum price**: a product price (and its compare-at price) must be **at least 1 ₫**, on create and on edit (`DecimalPrice(minimum: '1')` in `StoreProductRequest`, inherited by `UpdateProductRequest`). Below that the API answers 422 with "Số tiền phải từ 1 ₫ trở lên."; the form shows it under the field and a hint "Tối thiểu 1 ₫" otherwise. Existing listings are not changed; an existing price below 1 ₫ must be raised the next time it is edited. Auction and voucher amounts keep their own rules.

## Checks

| Check | Result |
| --- | --- |
| Backend | `ProductManagementTest` +: 0.99 / 0.01 rejected on create and edit with the Vietnamese message, compare-at 0.50 rejected, 1 accepted as `1.00`; the decimal test now accepts 1.10 and rejects 0.10. Full suite **499/499** (6,548 assertions). pint passed |
| Vitest | **522/522**. New `MoneyInput.spec.js` (formatting, parsing incl. paste and deletion cases, component, DomainField). Updated: quick-bid chip now shows `260.000`; the catalogue filter test types `100.000` / `500.000` |
| oxlint / eslint (no `--fix`) / build | 0 / 0 / OK |
