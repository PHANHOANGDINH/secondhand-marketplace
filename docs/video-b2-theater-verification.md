# VIDEO B2 — Homepage Video Discovery + Theater Mode

Verification date: 2026-10-09. Local frontend: `http://localhost:5173`; Laravel: `http://127.0.0.1:8000`. B1 remains the upload/storage source. No Docker, schema changes, new dependencies, transcoding, comments, or changes to purchase, bidding, settlement, authentication or payment rules.

## Architecture and public data

- Homepage keeps its existing five-scene GSAP story and marketplace entry. `HomeDiscovery` adds a switch between the existing auction cards and a real Video Review grid.
- `VideoReviewCard` renders a vertical 9:16 cover, actual product/shop/price and auction status. It uses the video poster, then the product primary image, then a neutral cover. Empty and failed feeds use shared feedback and retry patterns.
- `GET /api/v1/videos?page=1&per_page=12` returns newest READY videos with pagination. `per_page` is bounded to 1–24; invalid pagination and unknown filter fields return 422.
- `GET /api/v1/videos/{id}` rechecks public visibility for Theater and shared URLs. Missing, hidden, processing, failed and unavailable videos return 404. Both endpoints use the existing public-read limiter.
- Visibility reuses `ProductQuery::publicVisible`: active product with stock, active shop and owner, valid active category ancestry, and existing soft-delete rules. The response contains public video fields and the existing public product resource, without upload-owner metadata or raw storage paths.
- Product/shop/category/images are eager loaded; auction context is one batch query. A regression test confirms query count stays constant when the feed grows from three to seven videos.
- Auction context reuses `FixedPriceEligibility::claims()`, including scheduled/live auctions and ended auctions still reserved for winner checkout. The latter displays ENDED and links to the existing auction page; expiry releases the context according to the existing rule.

## Theater, history and safe CTAs

- `VideoTheater` reuses `AccessibleDialog`: approximately 90% dark backdrop, 60/40 desktop media/product composition, centered 9:16 player, native play/pause/mute/volume controls and plain-text descriptions.
- Fixed-price CTA: **Xem sản phẩm**. Scheduled/live auction CTA: **Vào phòng đấu giá**. An ended reserved auction uses **Xem phiên đấu giá**. These navigate to existing pages; no cart mutation, checkout shortcut or own-product purchasing action is introduced.
- Opening first fetches fresh public context and adds `?video={id}` to the current homepage query. Switching replaces that query entry. Back closes an opened Theater; ESC/Close restore the homepage, and a loaded deep link removes only the video parameter when closed.
- Previous/next buttons and ArrowUp/ArrowDown follow loaded feed order. Boundaries are disabled, with no wrapping. Arrow keys inside native media controls retain their normal player behavior.
- The active public context is checked every 15 seconds while the page is visible. Unavailable content removes both player and CTA. API errors offer retry; codec/media errors show a cover fallback while preserving safe navigation.

## Performance and cleanup

- The idle feed renders images only: browser network verification found no video-body requests before intentional preview/open.
- Fine-pointer hover or keyboard focus previews only the active card, muted and playsinline. Touch/coarse pointers and reduced motion do not autoplay. Leaving, losing page focus, hiding the page or leaving the viewport stops the preview.
- Preview and Theater media use `preload="none"`. Only the selected preview or Theater exists; changing/closing pauses old media, removes its source and releases playback.
- Request sequence guards ignore stale feed/detail responses. Component teardown clears its timer, media-query and visibility listeners, observer and media. Homepage GSAP architecture is unchanged.

## Responsive and accessibility verification

- Checked at 1440, 1024, 768 and 390px. Grid uses four/three/two columns, with a one-column fallback below 350px. No page-level horizontal overflow or broken covers was observed.
- Desktop/tablet Theater uses the side-by-side panel; below 768px it uses a full-screen dialog with video above product context. Header/close and previous/next remain available while content scrolls.
- Shared dialog retains background inertness, focus trap, ESC and return to the originating card. Native controlled video is included in focusable controls. Visible focus, button labels and reduced motion were checked.
- Actual Edge/CDP interaction covered hover, tab/arrow/ESC, browser Back, refresh/deep links, product and auction navigation, media failure, hidden-product refresh and route cleanup. Screenshots were inspected at desktop/mobile; the discovery grid was explicitly brought into view at all four sizes.

## Verification results

| Check | Result |
| --- | --- |
| Backend full suite | PASS — 336 tests, 3,311 assertions |
| New backend feed regression | PASS — 5 tests, 40 assertions; included in full suite |
| Frontend full suite | PASS — 337 tests, 27 files |
| New frontend discovery/Theater regression | 17 tests |
| Pint | PASS — final result `passed` |
| Frontend lint | PASS — oxlint and ESLint |
| Production build | PASS — Vite build, 3.76 seconds; advisory plugin timing output only |
| Real browser checks | PASS — 41 checks, no application console errors; includes the post-cleanup empty state |
| Development data and upload cleanup | PASS — all 21 business-table row counts/content hashes preserved; temporary files and credential manifest removed |

Commands, from the respective application directory:

```powershell
# backend (PHP 8.4 on PATH, or use your configured executable)
php artisan test --compact --filter=VideoDiscoveryTest
php artisan test --compact
php vendor/bin/pint --dirty --format agent

# frontend; npm.cmd avoids the Windows npm.ps1 execution-policy restriction
npm.cmd run test:unit -- --run --maxWorkers=2
npm.cmd run lint
npm.cmd run build
```

Browser verification uploaded the two repository-owned B1 MP4/WebM clips through `ProductVideoService` to temporary public products; both actually decoded and played in Edge. A separate temporary auction exercised the real arena CTA. Fixtures, media, credentials and evidence are confined to ignored `.runtime` helpers; none are demo seed data or committed credentials. Fixture cleanup compares row counts and content SHA-256 hashes for 21 business tables against the pre-verification baseline. No development migration or `migrate:fresh` was run.

## Files created/changed in B2

Created:

- `backend/app/Http/Requests/Domain/VideoDiscoveryRequest.php`
- `backend/app/Http/Resources/VideoDiscoveryResource.php`
- `backend/tests/Feature/VideoDiscoveryTest.php`
- `frontend/src/components/home/HomeDiscovery.vue`
- `frontend/src/components/home/VideoReviewCard.vue`
- `frontend/src/components/home/VideoTheater.vue`
- `frontend/src/__tests__/VideoDiscovery.spec.js`
- `docs/video-b2-theater-verification.md`

Refined:

- `backend/app/Http/Controllers/Api/V1/ProductVideoController.php` — public discovery and lookup only; existing seller upload handlers retained.
- `backend/routes/api.php` — two public read routes.
- `frontend/src/api/productVideo.api.js` — discovery and single-video reads.
- `frontend/src/views/HomeView.vue` — isolated discovery integration.
- `frontend/src/components/ui/AccessibleDialog.vue` — explicit attributes/listener forwarding to the teleported backdrop; native video focus support.
- `frontend/src/__tests__/App.spec.js` — isolate existing health tests from discovery, which has its own coverage.
- `backend/app/Services/ProductVideoService.php` — Pint formatting only, no B1 upload or domain changes.

The workspace already contained substantial prior phase changes and untracked B1/Auction/UI files. This inventory describes B2 edits, not the entire historical working-tree status.

## Remaining limitations

- Native codec support varies by browser/device; no transcoding or poster generation is added. Only Edge was used for real playback, with viewport emulation rather than physical phones/tablets. The synthetic verification clips are silent, so audible output was not tested.
- Previous/next operates within the loaded feed page. A deep link outside that page still opens safely, with navigation disabled until the video belongs to the loaded feed.
- Context checks are periodic, so unavailable content may remain visible for up to roughly 15 seconds while the page is active. The existing destination/backend still enforces every transaction rule independently.
- B1 public-storage URLs remain directly accessible to someone who already knows them; discovery visibility is not signed-media revocation. No change to the existing upload/storage security model was made.
- Temporary verification videos are removed after testing. A development database without READY public videos correctly shows the empty state; permanent video demo content must be supplied through the existing seller editor.
- Auto-increment counters, cache and transient anonymous sessions can advance during verification; unrelated business records and upload content must match the recorded baseline.

## Decision

**PASS — VIDEO B2 is complete and ready for a local demo once public READY videos are supplied through the existing seller editor.** All regression, formatting, lint, build and browser checks passed. Temporary verification data/uploads were removed and the 21-table development baseline was preserved.
