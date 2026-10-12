# VIDEO B3 — Persistent realtime Theater comments

Verified locally on 2026-10-09 with Vue/Vite at `http://localhost:5173`, Laravel at `http://127.0.0.1:8000`, XAMPP MySQL and Reverb at `127.0.0.1:8080`. No Docker is required. Existing Product, Auction, Order and Payment behavior is preserved.

## Schema and permissions

Additive migration: `2026_10_08_182727_create_video_comments_table.php`.

`video_comments` contains `id`, `product_video_id`, `user_id`, plain-text `body`, `status`, `created_at` and `updated_at`. Status is constrained to `PUBLISHED` or `HIDDEN`. Foreign keys and indexes cover video/time, video/status/id and author. Deleting a video cascades its comments, preserving the existing video deletion flow; user deletion is restricted by the foreign key (normal user soft deletion remains supported).

Public reading and posting require a READY video whose product satisfies the existing public visibility query, including active shop/category/owner and available product. Posting additionally requires an active authenticated SPA session. Text is trimmed, required and limited to 500 characters. Unknown client fields are rejected; author, video and status are server controlled. Posting is limited to 10 requests per minute per user.

Only the author, that product's active shop owner, or an active ADMIN can hide a comment. Hiding changes status and writes an audit entry; repeated hiding is idempotent. No endpoint edits another author's text or republishes a hidden comment. Authorization is rechecked under transaction locks. A stale moderation request after video deletion returns not found.

## API

| Method | Endpoint | Result |
| --- | --- | --- |
| GET | `/api/v1/videos/{video}/comments` | Published comments, newest ID first; pagination defaults to 20, maximum 50. Supports `page`, `per_page`, `before_id`. |
| POST | `/api/v1/videos/{video}/comments` | `{ "body": "..." }`; persisted comment resource, HTTP 201. |
| PATCH | `/api/v1/video-comments/{comment}` | `{ "status": "HIDDEN" }`; authorized soft moderation, HTTP 204. |

Resources expose comment ID, body, timestamp and author ID/display name. Deleted authors use a neutral display name. REST responses may include current-user `can_manage` and `permissions.can_moderate`. Email, address, credentials and private profile data are excluded. Reads use eager-loaded authors and a shared product/shop context.

## Realtime and UI

Public channel: `video.{videoId}`. `VideoCommentCreated` carries `video_id`, safe comment data and `server_time`; `VideoCommentHidden` carries only video/comment IDs and server time. Events dispatch after the outer database transaction commits, recheck current public visibility, and never broadcast whole User models or personalized permission flags. Rollbacks emit nothing. Broker failure does not invalidate an already persisted REST comment.

`VideoComments.vue` extends the Theater product panel while retaining its product CTA and native video controls. Mobile keeps video, product summary, comments and composer in the existing scrollable dialog. Comments display oldest to newest, with older history loaded using `before_id`.

The labeled textarea shows its 500-character limit. Enter inserts a newline; submitting uses the explicit button. Loading disables repeated submission; validation preserves the draft. Guest login links retain the safe Theater/video return URL. Server-confirmed responses and realtime events reconcile by comment ID; there is no optimistic insertion.

Readers near the bottom follow new comments. Readers scrolled up keep their position and receive a small new-comment button. Older-history loading preserves scroll position. Video switches release the old channel, clear previous comments and ignore stale responses/events. Closing releases the subscription. Reconnection refreshes the latest REST comments and removes duplicates. Confirmed hides and hidden-event tombstones prevent stale responses resurrecting comments.

Keyboard-accessible comment scrolling does not trigger Theater video switching. Existing dialog focus handling and ESC remain intact. The composer and moderation actions have accessible names; announcements are restrained. Lightweight opacity animation is disabled for reduced motion. No dependencies were added.

## Verification

| Check | Result |
| --- | --- |
| Dedicated backend comments tests | PASS — 13 tests, 115 assertions |
| Full backend regression | PASS — 349 tests, 3,428 assertions |
| Full frontend regression | PASS — 353 tests across 28 files |
| Pint | PASS — final `--dirty --format agent --test` check |
| Frontend lint | PASS — oxlint and ESLint |
| Production build | PASS |
| Actual multi-browser checks | PASS — 20 checks |
| Development business records preserved | PASS — 22 table row counts/content hashes match baseline |

Backend tests cover guest reads/write denial, trimming, Unicode length, empty/invalid/oversized text, field tampering, inactive or unavailable contexts, author/shop-owner/admin permissions, wrong-owner IDOR, hidden-history exclusion, deleted-author privacy, pagination, per-user throttling, safe event payloads, after-commit dispatch, rollback silence, idempotent moderation, audit rollback, dispatch-time visibility and stale-parent deletion.

Frontend tests cover loading/empty/error states, guest return navigation, server-confirmed submission, double-submit protection, draft retention, realtime ordering/deduplication, scroll following and new-comment affordance, rapid video switching/stale results, unmount cleanup, reconnect reconciliation, moderation confirmation/permissions, hide-versus-refresh races and keyboard scrolling. Existing auction/shared Echo tests also verify public video channel isolation and subscription reference counting.

Actual Edge browser contexts used two controlled Buyers and a guest with local READY MP4/WebM fixtures. Both Buyers received each other's comments without refreshing; their own POST/realtime response produced one row. HTML remained literal text. Broadcast payloads excluded private fields. Switching videos did not mix channels; close/reopen restored persisted comments. Stopping the actual Reverb server still allowed REST posting; restarting recovered the missed database comment without page reload. A controlled Seller hid a comment and both browsers removed it through realtime. ESC and console checks passed.

Desktop/tablet/mobile checks at 1440, 1024, 768 and 390 pixels confirmed no page-level horizontal overflow and usable product CTA/composer. Screenshots were inspected at desktop and mobile sizes. This uses browser device emulation, not physical-device testing.

## Commands and data preservation

```powershell
# Backend: use the project's configured PHP executable if php is not on PATH.
cd backend
php artisan migrate --path=database/migrations/2026_10_08_182727_create_video_comments_table.php
php artisan serve --host=127.0.0.1 --port=8000
# Separate terminal; retain existing env-driven Reverb configuration.
php artisan reverb:start --host=127.0.0.1 --port=8080

# Tests use the separate guarded secondhand_marketplace_test database.
php artisan test --filter=VideoCommentsTest
php artisan test
php vendor/bin/pint --dirty --format agent

cd ../frontend
npm run dev
npm run test:unit -- --run --maxWorkers=2
npm run lint
npm run build
```

On Windows, `npm.cmd` avoids restricted PowerShell script execution. The development database received only the additive B3 migration. No development `migrate:fresh` was executed. Controlled browser fixtures, comments, moderation audits, accounts, sessions and uploaded media were cleaned afterward. Business row counts and content hashes across 22 tables match the recorded baseline; temporary credential manifests were removed. MySQL auto-increment counters and the migration/schema addition are intentional changes.

## Changed files in this phase

New backend files:

- `backend/database/migrations/2026_10_08_182727_create_video_comments_table.php`
- `backend/database/factories/VideoCommentFactory.php`
- `backend/app/Enums/VideoCommentStatus.php`
- `backend/app/Models/VideoComment.php`
- `backend/app/Policies/VideoCommentPolicy.php`
- `backend/app/Services/VideoCommentService.php`
- `backend/app/Http/Controllers/Api/V1/VideoCommentController.php`
- `backend/app/Http/Requests/Domain/StoreVideoCommentRequest.php`
- `backend/app/Http/Requests/Domain/ListVideoCommentsRequest.php`
- `backend/app/Http/Requests/Domain/HideVideoCommentRequest.php`
- `backend/app/Http/Resources/VideoCommentResource.php`
- `backend/app/Events/VideoCommentBroadcastEvent.php`
- `backend/app/Events/VideoCommentCreated.php`
- `backend/app/Events/VideoCommentHidden.php`
- `backend/tests/Feature/VideoCommentsTest.php`

Backend integration: `backend/routes/api.php`, `backend/app/Providers/AppServiceProvider.php`.

New frontend files: `frontend/src/api/videoComment.api.js`, `frontend/src/composables/useVideoComments.js`, `frontend/src/components/home/VideoComments.vue`, `frontend/src/__tests__/VideoComments.spec.js`.

Frontend integration: `frontend/src/composables/auctionEcho.js`, `frontend/src/components/home/VideoTheater.vue`, `frontend/src/__tests__/AuctionEcho.spec.js`, `frontend/src/__tests__/VideoDiscovery.spec.js`.

Documentation: `docs/video-b3-comments-verification.md`. Other pre-existing workspace changes belong to earlier phases.

## Remaining limitations and decision

- Text comments only; no replies, reactions, attachments or new messaging behavior.
- Reconnect refresh resets the loaded history window to the newest 20 comments; older history can be loaded again.
- Without a configured/running Reverb service, database reads and writes remain available; missed external updates require REST retry/reopen until realtime reconnects. No polling fallback was introduced.
- Public delivery cannot retract text already received before moderation or context becoming unavailable. Subsequent REST reads and new broadcasts apply visibility/status checks.
- Locking prioritizes consistency with existing visibility-sensitive flows; high-volume contention/load testing and physical-device testing were not performed.

**Final decision: PASS — VIDEO B3 is ready for the local demo within the limitations above.**
