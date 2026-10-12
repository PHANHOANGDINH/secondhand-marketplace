# Video B1 — Product video-review core verification

Date: 2026-10-08. **PASS — ready for a local B1 demo.** Existing Product, Auction, settlement, Order, Payment, auth and image-upload business rules are preserved. No Theater Mode, realtime comments, feed, autoplay-on-hover, likes/follows, transcoding, CDN or livestream.

## Schema and domain

Additive migration: `2026_10_08_161224_create_product_videos_table.php`.

`product_videos`: id, product_id, uploaded_by, nullable title (200)/description (1000 validated characters), unique storage path, detected mime_type, file_size, nullable duration_seconds/poster_path, sort_order, is_primary, status, timestamps. Foreign keys restrict hard deletion; Product soft deletion retains media records, as existing images do. Product gains a videos relation; no catalog-list eager video loading or Product status transition is added.

Statuses: PROCESSING, READY, HIDDEN, FAILED. B1 synchronously validates/stores uploads and produces READY; PROCESSING/FAILED are reserved values, not a claimed processing pipeline. Seller can change READY ↔ HIDDEN only. Other status assignment is rejected. Model mass assignment is guarded; owner, paths, size, MIME, duration and poster are server controlled.

Maximum three videos/product, including hidden records. Mutations reuse existing ProductLocks and Product policy, revalidating ownership/shop/account under transaction locks. Dense sort order and exactly one primary among READY videos are normalized transactionally; no primary exists if all are hidden. A generated unique primary_product_id and CHECK constraints enforce at most one READY primary and forbid non-READY primary. Hide/delete promotes the first remaining READY video; existing primary survives metadata/reordering. Repeated primary/hide/show actions remain valid without duplicate records. There are new video audit actions with unchanged Product status, not new Product moderation rules.

## Upload and storage

- Formats: **video/mp4 (.mp4)** and **video/webm (.webm)** only; detected MIME, original extension, matching container signature and size are checked.
- Default **50 MiB/video**, configurable `PRODUCT_VIDEO_MAX_KB=51200` in private backend env. Three-video count stays fixed for MVP.
- Browser-side type/extension/size checks improve feedback; server remains authoritative. Unknown fields, client paths/uploader/status tampering are rejected.
- Laravel public disk: `storage/app/public/product-videos/{product-id}/{generated-UUID}.mp4|webm`; stream copy, no DB blobs or original filename paths.
- `php artisan storage:link` continues to expose `public/storage`; no cloud storage or shell execution introduced.
- Rejected/rolled-back uploads clean generated unreferenced files. DELETE removes the row in a transaction, normalizes the remaining videos and cleans video/poster only after commit. Cleanup accepts only this Product's generated path and checks other references. Failure warnings are sanitized; operational cleanup may be needed after filesystem failure/process crash.
- Soft-deleted Product remains unavailable to public API; its historical media is retained. No existing Product delete/restore behavior was rewritten.

The previously running PHP defaults were 2M upload / 8M post. `backend/dev/uploads.ini` now permits 50M upload / 60M post / 256M memory. Existing `serve.ps1` applies those settings locally without modifying the system php.ini. Image validation remains 5 MiB, eight images and 20 MP.

```powershell
cd backend
php artisan migrate:status
# Apply reviewed pending additive migrations only; never reset development data:
php artisan migrate
php artisan storage:link
.\serve.ps1
# With an explicit compatible PHP CLI if it is not on PATH:
# .\serve.ps1 -PhpExecutable 'C:\path\to\php.exe'
```

Alternatively configure the same upload/post values privately in php.ini before `php artisan serve --host=127.0.0.1 --port=8000`. Restart backend after changing PHP limits. If raising the env limit, raise PHP post/upload caps accordingly, including multipart overhead. Larger upload limits do not weaken application image/video validation. Frontend remains `npm run dev` at http://localhost:5173; backend http://127.0.0.1:8000. No container commands.

## APIs and permissions

| Method | Path | Access / behavior |
| --- | --- | --- |
| GET | `/api/v1/products/{slug}/videos` | Public; existing ProductQuery visibility, READY videos only, ordered sort/id |
| GET | `/api/v1/seller/products/{product}/videos` | Current owner; all video statuses, server limits |
| POST | `/api/v1/seller/products/{product}/videos` | Editable owned Product; multipart video + optional title/description; 201 safe video resource |
| PATCH | `/api/v1/seller/products/{product}/videos/{video}` | Metadata, is_primary=true, sort_order 0–2 within current list, status READY/HIDDEN |
| DELETE | `/api/v1/seller/products/{product}/videos/{video}` | Editable owner; child binding; 204 and safe file cleanup |

Seller routes retain active Sanctum cookie session, CSRF and existing domain/upload throttle groups. Another seller/buyer cannot mutate; a video under the wrong Product is 404 even for the same shop owner. Inactive owner/suspended shop cannot upload/delete. Seller list uses the existing view permission, so a shop that is read-only can still inspect owned videos.

Public resource contains only id, title, description, url, poster_url, duration_seconds, is_primary, sort_order. Seller adds status, mime_type and file_size; neither resource returns raw filesystem path, uploader identity or internal metadata. Product, Shop, owner, category/ancestor, stock and soft-delete visibility rules come from existing ProductQuery. No special Admin video override was added; existing Product moderation can hide the entire Product and its public video listing.

## Frontend

Reusable `ProductVideosEditor` is integrated into SellerProductEditor and therefore both full editor and Quick Listing. Existing metadata form and image workflow remain first and independent. Save a draft before video upload. Optional title/description, preview, progress/loading, server validation, edit/cancel/save, primary, reorder, hide/show and explicit destructive confirmation are provided. Pending video/metadata changes join the existing Quick Listing unsaved-change warning; busy uploads prevent competing metadata/image actions or dialog dismissal. Preview object URLs and stale requests are cleaned on unmount/Product switch.

Reusable `ProductVideos` mounts only on Product Detail, uses the dedicated public API, hides the section when there are no READY videos and offers error retry independently of normal product/cart behavior. Native controls, playsinline, preload=none; **no autoplay**. Missing poster has a readable play prompt and native player background. Metadata is rendered as text, never raw HTML. No video player/API payload on Product List, no new dependency or UI framework.

Bootstrap/design-system panels/cards, labelled fields/buttons, descriptive player labels, wrapping text and responsive columns are retained. File input/title keyboard Tab order was exercised. No new animations; reduced-motion users receive the same normal static controls. Existing shared StatusBadge gains READY success semantics.

## ffmpeg/poster limitation

Neither ffmpeg nor ffprobe was available on PATH; no dependency was installed. Duration and poster remain null. READY means accepted container/MIME/storage, **not** decoded/transcoded or verified codec compatibility. Signature validation does not replace a media decoder/antivirus. Browser-supported codecs are required; containers alone do not guarantee playback on every browser. Native MP4 and WebM decode were demonstrated in Edge. Cross-browser/mobile-device hardware decoding and long/large recordings are not certified.

## Verification

| Check | Result |
| --- | --- |
| Full backend `php artisan test --compact` | **PASS — 331 tests, 3,271 assertions** |
| Product-video focused backend | **PASS — 16 tests** |
| Full frontend `npm run test:unit -- --run --maxWorkers=2` | **PASS — 320 tests, 26 files** |
| Pint `php vendor/bin/pint --dirty --format agent` | **PASS** |
| Frontend lint | **PASS** |
| Production build | **PASS** |
| Browser | **PASS — 34 checks, zero application console errors/uncaught exceptions** |
| HTTP after PHP-limit restart | **PASS — six checks**, including 3 MiB multipart and file cleanup |
| Development data | **PASS — 21 tables match pre-fixture row counts/content hashes** |

New backend tests cover upload, real MIME/extension/container validation, configurable size/missing-file errors, traversal filenames, max three/failed-upload cleanup, owner/guest/child IDOR, metadata/tampering, primary/order/hide/show/delete normalization, public whitelist/list payload, Product/Shop/owner/category/stock/soft-delete visibility, inactive mutation, upload/delete rollback, arbitrary path protection. Existing auth, bidding/realtime/notifications, settlement/checkout, payments/orders and image regressions run in the full suite.

New frontend tests: 15 cases for load/status/primary, draft/read-only upload restrictions, preview/upload/progress/metadata, type/extension/size/server validation, max three, save/cancel/dirty state, primary/reorder/hide, confirmed delete, retry, preview cleanup, stale Product response, public controls/fallback/plain text/no autoplay, empty and error states. Five early existing tests selected the first form, which became the video form; preserving the existing metadata form first restored those regressions without changing test expectations or business form behavior.

A normalization issue discovered during development (bulk primary reset with stale Eloquent originals) was fixed by synchronizing original is_primary before restoring the selected primary. The generated demo clip initially had a static single frame; animated owned fixtures now have a playable interval. A full test run overlapped fixture regeneration and failed the old literal size assertion; the assertion now checks actual fixture size and the final full suite passes. These were development corrections, not unresolved application failures.

Browser used separate temporary Seller/Buyer accounts through real localhost cookie/CSRF APIs. Uploaded both owned fixtures; changed primary/order and metadata; hid a video and checked public exclusion; restored it; verified Product stays ACTIVE. Editor and public detail checked at 1440/1024/768/390px without horizontal page overflow or broken images. Native MP4 explicitly loaded/played/paused at 320×180 with no autoplay/preload; WebM decoded. Labelled mobile file input and title Tab order work. Another Buyer session's DELETE returned 403. Both videos were deleted through confirmation UI. Final browser pass has no app errors.

After restarting the workspace backend using its local helper, health and real session/CSRF login still worked. A valid generated MP4 with a legal ISO free-padding box was uploaded at 3 MiB, exceeding the former PHP 2 MiB ceiling; stored bytes were accessible, owner DELETE returned 204, and deleted bytes were unavailable. The missing storage response was non-video HTML (403), so this is recorded as file-byte removal rather than a 404 assertion. Filesystem inventory was empty afterward. No 50 MiB HTTP upload/load test is claimed.

## Demo fixtures and cleanup

Two repository-owned synthetic fixtures, produced locally with browser canvas/MediaRecorder: `backend/tests/Fixtures/product-review.webm` (11,591 bytes), `product-review.mp4` (8,189 bytes), about 20 KB total. They show abstract LOCAL DEMO VIDEO/frame text; they are not factual reviews of seeded products. Use them only to demonstrate upload/playback, or upload an authorized real product recording manually. No seeded marketplace video expansion, hotlinks or credentials.

Additive migration was applied to development, without migrate:fresh. Tests remain guarded to `secondhand_marketplace_test`. Browser/HTTP writes used only three random temporary users, one temporary shop/category/product, own files and their video audit entries/sessions. Fixtures and credential manifest were removed; dedicated browser process stopped. The final snapshot matches all 21 business-table row counts/hashes before temporary fixtures, including existing Auction/Order/Payment/Notification/cart/conversation data. Only the new schema/migration row is intentional; auto-increment counters can advance from temporary inserts. Backend/Vite/Reverb remain available locally; backend now uses the upload helper's limits.

## Remaining limitations

- Local public storage is not private media access control. Hidden/inactive/soft-deleted records are excluded from APIs, but a previously known file URL can remain readable until deletion, matching existing public-image storage conventions. Do not upload secrets; protected streaming/signed media is outside B1.
- No poster/duration extraction, transcoding, codec normalization, malware scanning or file-content sanitization beyond format/container validation.
- Soft deletion retains historical media; no retention/orphan-recovery command is introduced. Filesystem failure/process crash may require safe operational cleanup.
- Native controls provide playback; browser/codec support, seeking performance and production static-server Range/streaming configuration need broader deployment testing. Only local Edge playback and 3 MiB upload were exercised.
- No dedicated Admin video moderation UI/API or extra Product moderation transition. Existing Product moderation still governs public discoverability.

## B1 changed files

28 source/docs/assets files, with no dependency changes. The broader dirty Git inventory includes prior phases and is not attributed to B1.

New backend (paths under backend):

- `database/migrations/2026_10_08_161224_create_product_videos_table.php`
- `app/Models/ProductVideo.php`, `app/Enums/ProductVideoStatus.php`, `database/factories/ProductVideoFactory.php`
- `app/Policies/ProductVideoPolicy.php`, `app/Services/ProductVideoService.php`
- `app/Http/Controllers/Api/V1/ProductVideoController.php`
- `app/Http/Requests/Domain/UploadProductVideoRequest.php`, `UpdateProductVideoRequest.php`
- `app/Http/Resources/ProductVideoResource.php`, `config/product-videos.php`
- `tests/Feature/ProductVideosTest.php`
- `tests/Fixtures/product-review.webm`, `tests/Fixtures/product-review.mp4`

Modified backend: `app/Models/Product.php`, `routes/api.php`, `.env.example`, `dev/uploads.ini`.

New frontend: `src/api/productVideo.api.js`, `src/components/ProductVideosEditor.vue`, `src/components/ProductVideos.vue`, `src/__tests__/ProductVideos.spec.js`.

Modified frontend: `src/components/seller/SellerProductEditor.vue`, `src/components/seller/QuickListingDialog.vue`, `src/components/ui/StatusBadge.vue`, `src/views/ProductView.vue`.

Documentation: root `README.md`, this report. Ignored local test/browser logs and fixture helpers are not deliverable source; no real secrets, reset links or passwords were committed.

## Final decision

**VIDEO B1: PASS.** Backend/domain upload and ownership, safe public listing, inline Seller/Buyer video UI, full regressions, local browser/HTTP checks and fixture cleanup are complete. Ready for a local B1 demonstration within the documented codec/public-storage limits.
