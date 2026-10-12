# Implemented API summary

This is the current application contract, checked against `backend/routes/api.php` and the frontend API clients. Historical design documents also contain future endpoints; they are not all implemented. Base path: `/api/v1`. Local Laravel origin: `http://127.0.0.1:8000`; the browser uses the Vite proxy at `http://localhost:5173`.

## Authentication, payloads and errors

Use cookie sessions, not bearer tokens. First GET `/sanctum/csrf-cookie` (outside `/api/v1`), retain cookies, then send a matching `X-XSRF-TOKEN` on writes. Axios enables credentials/XSRF handling. Laravel 13 also accepts browser Fetch Metadata for same-origin requests; older clients fall back to token validation. CORS permits only configured exact origins; Sanctum requires configured host[:port]. Never send role/status during registration.

Single resources use `{"data": {...}}`; lists use Laravel `data`, `links`, `meta`. Resource IDs and money values are serialized as strings; timestamps generally use ISO format. List pagination defaults to 20, maximum 100 where supported; validated filters vary by endpoint. Health returns `{"status":"ok","message":"Secondhand Marketplace API is running"}` without an envelope. Successful deletion/logout returns 204 without a body.

Common errors: 401 unauthenticated, 403 unauthorized/inactive account, 404 missing/nonpublic record, 409 stale/invalid transition or idempotency conflict, 419 CSRF failure, 422 validation errors (`message`, `errors`), 429 throttled, 5xx generic server error. Unknown/unsafe fields are validated rather than accepted as model attributes. Internal errors and raw gateway secrets/metadata are not returned. Reload stale records after 409; retry uncertain checkout with the same UUID and original payload.

## Routes

Methods/paths below are relative to `/api/v1`, except the CSRF endpoint. All nonpublic groups require an active authenticated session and CSRF on writes. A seller means the owner of the relevant shop. ADMIN permissions never substitute for another buyer/seller's ownership.

| Area | Implemented routes | Access / behavior |
| --- | --- | --- |
| Health/auth | GET `/health`; POST `/auth/register`, `/auth/login`; GET `/auth/me`; POST `/auth/logout` | Health and register/login public; me/logout authenticated. Logout remains available for a blocked session |
| Password reset | POST `/auth/forgot-password`, `/auth/reset-password` | Stateful CSRF-protected requests; generic forgot response, broker token validation, throttling and one-time reset |
| Profile | GET, PATCH `/profile` | Current user; name/phone edits |
| Addresses | GET, POST `/addresses`; GET, PATCH/PUT, DELETE `/addresses/{id}`; PATCH `/addresses/{id}/default` | Current user only; one default address; checkout accepts only an owned address |
| Public shops/categories | GET `/shops`, `/shops/{slug}`, `/categories`, `/categories/{slug}` | Public active catalog/ancestor and owner visibility rules |
| Seller shop | POST `/shops`; GET, PATCH `/seller/shop`; POST `/seller/shop/submit` | Current shop owner; creation enters moderation, rejected shop can resubmit |
| Public products | GET `/products`, `/products/{slug}` | Public visible products; query search/category/shop/condition/price/sort and pagination as exposed by the catalog UI |
| Seller products | GET, POST `/seller/products`; GET, PATCH/PUT, DELETE `/seller/products/{id}` | Owner only; server selects shop/slug/status; soft-delete/workflow rules apply |
| Seller product actions | POST `/seller/products/{id}/submit`, `/hide`, `/withdraw`, `/revise` | Existing state transitions; no arbitrary status PATCH |
| Images | POST `/seller/products/{id}/images`; PATCH `/images/reorder`; PATCH, DELETE `/images/{imageId}` under that product path | Multipart `images[]`; owner/child binding; JPEG/PNG/WebP, max 8 images/product, 5 MiB/file, 20 MP; decoding/re-encoding and generated safe paths |
| Cart | GET, DELETE `/cart`; POST `/cart/items`; PATCH, DELETE `/cart/items/{id}` | Owned cart; product_id/quantity only; quantity 1–99 and availability checks; no own-shop purchase or stock reservation by cart |
| Checkout | POST `/checkout` | `address_id`, `payment_method` COD/ZALOPAY; optional ZaloPay channel array; UUID `Idempotency-Key`; whole cart, server prices/stock/totals; 201 first creation, 200 replay |
| Buyer orders | GET `/orders`, `/orders/{id}`, `/orders/{id}/payments`; POST `/orders/{id}/shop-orders/{partId}/cancel` | Buyer-owned order; cancel includes expected_status/reason, limited states/payment readiness |
| Seller fulfillment | GET `/seller/orders`, `/seller/orders/{partId}`; PATCH `/seller/orders/{partId}/status` | Owned ShopOrder, not aggregate buyer Order; expected_status/status; COD completion requires cod_collected=true; cancellation reason/failure_category |
| Online payment | POST `/orders/{id}/payments/zalopay`; POST `/orders/{id}/payments/zalopay/query` | Buyer ownership; retry requires UUID Idempotency-Key; backend-authoritative state/query and retry eligibility |
| Callback | POST `/payments/zalopay/callback` | Public session/CSRF exemption; validates data/mac/type, MAC, reference/amount and replay/idempotency; response return_code/return_message |
| Reviews | GET `/products/{slug}/reviews`, `/products/{slug}/review-eligibility`; POST `/order-items/{id}/review`; PATCH `/reviews/{id}` | Public review list; other routes authenticated. Completed owned purchase, one review/item, rating 1–5, comment <=2000; only author edits; hidden stays hidden |
| Seller reports | GET `/seller/dashboard`, `/seller/dashboard/revenue` | Current owned shop; from/to/group_by range options, max 366 days; completed merchandise subtotal, not platform commission |
| Admin categories | GET, POST `/admin/categories`; PATCH, DELETE `/admin/categories/{id}` | ADMIN, reason for writes, hierarchy/usage/visibility validation |
| Admin shops/products | GET `/admin/shops`, `/admin/shops/{id}`, `/admin/products`, `/admin/products/{id}`; PATCH corresponding `/{id}/status` | ADMIN moderation, reason/expected_status, no self-approval; historical detail can include soft-deleted records |
| Admin users/reviews | GET `/admin/users`, `/admin/users/{id}`, `/admin/reviews`, `/admin/reviews/{id}`; PATCH corresponding `/{id}/status` | ADMIN status/moderation only; reason/expected_status, self/last-admin guards; no arbitrary role change or review-text editing |
| Admin overview | GET `/admin/dashboard`, `/admin/orders`, `/admin/orders/{id}` | ADMIN read-only summaries/history; no fulfillment override/refund/payment-management endpoint |

For the image row, complete child paths are `/seller/products/{id}/images/reorder` and `/seller/products/{id}/images/{imageId}`. Slugs are public catalog identifiers; numeric IDs identify managed/owned records. Buyer Order ID and seller ShopOrder ID are different resources and must not be interchanged.

## Checkout example and payment behavior

```http
POST /api/v1/checkout
Content-Type: application/json
Idempotency-Key: <new UUID retained for retries>
X-XSRF-TOKEN: <matching cookie token>

{"address_id":1,"payment_method":"COD"}
```

For online payment, use `"payment_method":"ZALOPAY"` and optionally `"preferred_payment_method":["domestic_card","account"]`. Do not send price, stock, owner, shipping snapshot, payment status or totals. The order response contains safe payment attempts/eligible gateway URL. Browser redirect parameters never mark an order paid. Verified backend query/callback is authoritative; ZaloPay fulfillment requires verified payment and no reconciliation flag. COD becomes PAID only after all required collected fulfillment is complete.

Throttle groups include register (5/hour/IP), login (5/minute/email+IP and 20/minute/IP), domain writes, public reads, uploads, and shared checkout/payment retry/query (12/minute/user). Limits use the configured cache/observed IP; distributed deployments need shared stores and correctly scoped ingress IP handling. Full rate/authorization tests are in the backend suite.

## Boundaries

Refund/payout/commission endpoints, email verification, public admin role assignment and admin fulfillment overrides are not implemented. No production account/secret is shown here. Production and Sandbox ZaloPay setup are separate; see [demo guide](demo-guide.md) and [deployment guide](deployment-guide.md).

## Persistent notifications (A5)

All routes below require an active Sanctum cookie session. Writes retain the existing CSRF and domain-write throttling rules. Notification UUIDs identify only the current user's records; another owner's UUID returns 404.

| Method | Path | Result |
| --- | --- | --- |
| GET | `/api/v1/notifications` | Newest-first paginated safe resources; `page`, `per_page` (1–100), `filter=all/unread/auction/orders`; root `unread_count` |
| GET | `/api/v1/notifications/unread-count` | `data.unread_count` |
| POST | `/api/v1/notifications/{uuid}/read` | Owned resource with `read_at`; repeated read preserves the original timestamp |
| POST | `/api/v1/notifications/read-all` | Marks only this owner's unread rows; returns `data.unread_count` |

Types: OUTBID, AUCTION_WON, AUCTION_ENDED, AUCTION_CANCELLED, WINNER_CHECKOUT_EXPIRING, AUCTION_ORDER_CREATED. Resources expose `id`, type, auction/product IDs, product name, plain-text message, local target route, timestamps and, where applicable, decimal winning amount and checkout deadline. No payment metadata, private credentials, recipient identifiers or deduplication key is exposed. `NotificationCreated` uses the existing private `user.{id}` channel after database commit. See [A5 verification](auction-a5-notifications-verification.md).
