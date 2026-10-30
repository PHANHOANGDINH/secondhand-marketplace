# Buyer / Seller messaging MVP verification

Verified: 2026-10-07 (Asia/Saigon). Decision: **PASS for the scoped REST/polling MVP**.

## Scope and architecture

- One buyer and one seller per conversation; optional product context.
- `conversations` stores the buyer, seller, optional product and `last_message_at`. A unique internal context key prevents duplicate buyer/seller/product conversations, including product-free conversations.
- `conversation_participants` stores the two participants and each participant's monotonic `last_read_message_id`.
- `messages` stores the authenticated sender, plain-text body and timestamps. History is paginated, with ID cursors for stable older-history loading and incremental polling.
- Existing Sanctum session, CSRF, active-account and sensitive-write middleware remain in force. No changes to existing authentication, order, payment or moderation rules.
- Conversation Policy denies non-participants, including unrelated ADMIN accounts. Buyer and seller identities cannot be supplied through message payloads.
- Frontend messaging state is provided by the existing layout through a composable. Existing Pinia stores and frontend routes are unchanged.
- `ChatLauncher`, `ChatSellerButton` and `ChatDrawer` provide authenticated navigation, product entry and the conversation UI. Seller and Admin shells also expose the authenticated launcher.

## API

All endpoints require an authenticated active SPA session. Writes use the existing CSRF helper.

| Method | Endpoint | Input / behavior |
| --- | --- | --- |
| GET | `/api/v1/conversations` | `page`, `per_page` (1–50). Only the current user's conversations; response includes aggregate `unread_total` across all pages. |
| POST | `/api/v1/conversations` | `product_id`, or `seller_id` for product-free chat. Product owner is derived server-side; seller must own an active public shop. Returns 201 when created, 200 when reused. |
| GET | `/api/v1/conversations/{conversation}/messages` | `page`, `per_page` (1–50, default 30), optional `before_id` or `after_id`. Latest-first by default; ascending for incremental `after_id` polling. |
| POST | `/api/v1/conversations/{conversation}/messages` | `body`: required trimmed string, maximum 1,000 Unicode characters. Returns the persisted message. |
| POST | `/api/v1/conversations/{conversation}/read` | `last_message_id`: highest fetched message ID to acknowledge. Must belong to this conversation; 0 is a no-op. Never moves the checkpoint backwards. |

Unknown payload/query fields are rejected. Conversation creation and message sending share a limit of 20 attempts per authenticated user per minute, in addition to the existing sensitive-write limit. Rejected attempts can consume the limiter.

Message bodies are stored as literal plain text, including any HTML-like characters. Vue interpolation escapes the text; messaging does not use `v-html`, rich-text rendering or automatic HTML interpretation.

## Visibility and concurrency

- Prevents self-chat for product-based and seller-based entry points.
- Starting a product conversation uses the existing public product query: product, shop, owner and category visibility rules all apply.
- Existing participants retain history and sending access after product hiding, stock/status changes or soft deletion. Unavailable product context becomes `null`; its title, slug and price are not returned by conversation resources.
- Participant summaries expose only ID and display name, without email/password or internal context keys.
- Creation serializes on the buyer row; database uniqueness provides an additional duplicate constraint.
- Sending and read acknowledgments serialize on the conversation row. Acknowledgments cannot consume messages newer than the supplied checkpoint or reference another conversation.
- Related users/latest messages are eager-loaded; visible product contexts are fetched in a batch. The list query-count regression passed.
- Locks run inside database transactions, following [Laravel 13 query-builder guidance](https://laravel.com/framework/docs/13.x/queries#pessimistic-locking).

## Frontend behavior

- Product detail has **Nhắn người bán**. Guests are sent to Login with a safe internal product return URL; after authentication the product return opens the conversation and removes the one-time chat marker.
- Authenticated navigation has **Tin nhắn** and an unread badge.
- Desktop/tablet: right-hand drawer with conversation list, product context, chronological bubbles, timestamps and text composer.
- Mobile below 768px: full-screen drawer, separate conversation/list presentations and a back-to-conversations action.
- Loading, empty, retry/error and sending states; draft preserved on failed sends; duplicate submit disabled while sending.
- Polls every four seconds after requests complete. Incremental messages use `after_id`; older history uses `before_id`. No overlapping history requests for the current selection.
- Hidden document pauses polling. Navigation closes the active drawer; logout clears messaging data; unmount clears timers/listeners and stale asynchronous results are ignored.
- Dialog labels, textarea label/helper, unread label, Escape dismissal, keyboard focus wrapping/restoration and scroll-lock cleanup. No motion dependency or extra UI framework.

## Verification results

| Check | Result |
| --- | --- |
| New backend messaging feature tests | **13 passed, 147 assertions** |
| Full backend regression | **231 passed, 2,602 assertions** |
| New frontend messaging tests | **10 passed** |
| Full frontend regression | **210 passed across 19 files** |
| Laravel Pint | **PASS** |
| Frontend lint | **PASS** |
| Frontend production build | **PASS** |
| Browser UI at localhost:5173, 1440 / 1024 / 768 / 390px | **PASS** |
| Mobile full-screen dimensions / list return | **PASS** |
| Plain-text rendering / sending / Escape / scroll unlock | **PASS** |
| Page-level horizontal overflow / browser JavaScript errors | **None observed in the checked flow** |
| Live anonymous GET `/api/v1/conversations` at 127.0.0.1:8000 | **401, access denied** |
| Registered messaging API routes | **All five confirmed** |
| Additive development migration | **PASS** |
| Existing development table checksums | **Unchanged, except the intentional migration ledger entry** |

Backend regressions execute against the guarded `secondhand_marketplace_test` database. Tests cover guest denial, participant permissions, USER/ADMIN IDOR, self-chat, repeated creation, optional product context, unknown fields, Unicode trimming/length, literal text, sender derivation, unread totals/checkpoints, stable history cursors, product redaction, active-account restrictions and rate limiting.

Frontend tests cover safe guest return, automatic authenticated return, drawer/unread display, escaped bubbles, older/new-message cursors, sending/loading/failure states, hidden-tab pause/resume, timer cleanup, stale response isolation, logout/navigation closure, focus restoration, list retry and use of the existing CSRF helper.

Browser layout checks used the real Vite app and controlled REST fixtures through headless Edge. All API/CSRF requests in that browser check were intercepted; it did not create development database fixtures. These checks verify rendering and interactions, rather than a live two-account backend E2E session. Backend API behavior is verified separately by the MySQL feature suite.

## Migration and local startup

The additive migration was applied to `secondhand_marketplace`:

```powershell
cd backend
php artisan migrate --path=database/migrations/2026_10_07_085854_create_messaging_tables.php --force --no-interaction
```

Before/after checksums were captured for 49 existing tables. Their contents remained unchanged except for the intentional migration ledger update. The new `conversations`, `conversation_participants` and `messages` tables were empty after migration; no development messaging fixtures were inserted. No `migrate:fresh` was run against the development database.

Normal startup:

```powershell
# Start MySQL first, then in separate terminals:
cd backend
php artisan serve --host=127.0.0.1 --port=8000

cd frontend
npm run dev
```

Open `http://localhost:5173`. For another checkout, run ordinary `php artisan migrate --no-interaction` to apply pending additive migrations.

Verification commands:

```powershell
cd backend
php artisan test --compact tests/Feature/MessagingTest.php
php artisan test --compact
php vendor/bin/pint --dirty --format agent

cd frontend
npm run test:unit -- --run src/__tests__/Messaging.spec.js
npm run test:unit -- --run
npm run lint
npm run build
```

## Demo flow

1. Sign in as an existing Buyer demo account and open another user's public product.
2. Click **Nhắn người bán** and send a text message.
3. In a separate browser/profile, sign in as that product's Seller and open **Tin nhắn**.
4. Observe the unread badge, select the conversation and reply. The Buyer sees the reply after polling.
5. Check older history, mobile back navigation and Escape/close behavior.
6. For the guest path, sign out, open a public product and click **Nhắn người bán**; sign in and confirm return to that product's conversation.

See `demo-guide.md` for existing demo accounts. This report does not introduce credentials or seed orders/reviews.

## Changed files for this extension

Backend:

- `backend/database/migrations/2026_10_07_085854_create_messaging_tables.php`
- `backend/app/Models/Conversation.php`
- `backend/app/Models/ConversationParticipant.php`
- `backend/app/Models/Message.php`
- `backend/app/Policies/ConversationPolicy.php`
- `backend/app/Services/MessagingService.php`
- `backend/app/Http/Controllers/Api/V1/ConversationController.php`
- `backend/app/Http/Requests/Domain/ConversationListRequest.php`
- `backend/app/Http/Requests/Domain/StartConversationRequest.php`
- `backend/app/Http/Requests/Domain/MessageListRequest.php`
- `backend/app/Http/Requests/Domain/SendMessageRequest.php`
- `backend/app/Http/Requests/Domain/ReadConversationRequest.php`
- `backend/app/Http/Resources/ConversationResource.php`
- `backend/app/Http/Resources/MessageResource.php`
- `backend/database/factories/ConversationFactory.php`
- `backend/database/factories/MessageFactory.php`
- `backend/tests/Feature/MessagingTest.php`
- `backend/routes/api.php`
- `backend/app/Providers/AppServiceProvider.php`

Frontend:

- `frontend/src/api/messaging.api.js`
- `frontend/src/composables/useMessaging.js`
- `frontend/src/components/chat/ChatLauncher.vue`
- `frontend/src/components/chat/ChatSellerButton.vue`
- `frontend/src/components/chat/ChatDrawer.vue`
- `frontend/src/layouts/DefaultLayout.vue`
- `frontend/src/components/seller/SellerShell.vue`
- `frontend/src/components/admin/AdminShell.vue`
- `frontend/src/views/ProductView.vue`
- `frontend/src/__tests__/Messaging.spec.js`

Documentation: `docs/messaging-verification.md`. Local logs/browser helper artifacts stay under ignored `.runtime/`.

## Remaining limitations

- Realtime via Reverb was added later (see `docs/realtime-websocket-verification.md`); REST polling remains only as the fallback while the private channel is not subscribed. No push notifications or typing indicator.
- No attachments, images, voice/video, group chat, blocking or reporting, as scoped.
- No offline send queue or idempotency key for message POST retries after an ambiguous network failure.
- Browser checks used controlled API fixtures; a live two-account browser E2E run and physical-device keyboard/viewport testing remain unperformed.
- Sequential duplicate creation and read/send checkpoint behavior are tested. Independent-process messaging race/load testing was not performed; transaction locks and unique constraints enforce the implemented invariants.

**Final decision: PASS for the requested messaging MVP.**
