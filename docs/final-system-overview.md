# Final system overview

FINAL C2, 2026-10-09. Feature-frozen implementation; local demo readiness is backed by [C1 verification](final-demo-verification.md). This describes delivered behavior, not a production certification.

## Purpose and actors

A Vietnamese secondhand marketplace where people discover items, contact a shop owner, buy at fixed price or bid in an auction, and inspect product videos. Seller and Admin dashboards manage existing workflows.

Database roles are **USER** and **ADMIN**. Buyer is a purchasing User. Seller is a User owning a Shop, not an additional permanent role; one owner has at most one Shop, including archived records. A Seller can buy elsewhere but cannot buy/bid on their own product. Guests discover public content but cannot send messages, bid, checkout or comment. Admin moderation does not grant arbitrary Buyer/Seller ownership.

## Delivered modules

Authentication/password reset; Profile/Address; Shop/Category; Product/Images; Cart/Checkout; Order/ShopOrder; COD and ZaloPay V2 Sandbox; purchase-linked Reviews; Seller reporting; Admin management; text Messaging; Auction Arena and winner checkout; persistent Notifications; Product Video discovery; Theater Mode; realtime Video Comments. See the [module inventory](final-module-inventory.md) for rules, APIs and security.

## Technology and runtime

| Layer | Current implementation |
| --- | --- |
| Browser | Vue 3, Vite, Vue Router, Pinia, Bootstrap, Axios; Chart.js dashboards and homepage-only GSAP |
| API | Laravel 13 REST JSON API, FormRequests, Policies, Resources and domain services; compatible PHP 8.4 CLI |
| Identity | Sanctum first-party cookie sessions and CSRF; Laravel Password Broker |
| Persistence | MySQL, foreign keys/checks/unique indexes, transactions and row locks; DECIMAL with BCMath |
| Realtime | Events after commit → Reverb → Echo; public auction/video channels and private user notifications |
| Messaging | REST plus approximately four-second polling while active |
| Payment | COD; env-configured ZaloPay V2 Sandbox create/query and verified callback |
| Files | Public disk at `backend/storage/app/public`; `public/storage` link |
| Background | Scheduler invokes auction synchronization/reminders and payment reconciliation every minute |

## Request and data flow

Vue sends Axios requests through Vite's same-origin development proxy. Laravel middleware establishes the stateful session, checks authentication/activity/role and throttles relevant endpoints. FormRequests validate allowed input; Policies check ownership. Services revalidate mutable conditions under locks, write MySQL and return safe Resources. Committed events update online browsers; REST/MySQL remain authoritative after disconnects. Files are separate from DB rows and require preserving the public storage directory.

Local frontend is `http://localhost:5173`, Laravel is `http://127.0.0.1:8000`, MySQL is `127.0.0.1:3306`, and Reverb listens on `127.0.0.1:8080`. Browser requests use Vite proxy paths `/api`, `/sanctum`, `/storage` and `/placeholders`, avoiding cross-host cookie assumptions. A production build does not include that proxy.

Start MySQL and four processes: Artisan serve, Reverb, schedule:work and Vite. [README](../README.md) and [demo script](demo-script.md) contain commands, env setup, storage:link, additive migrations and FinalDemoSeeder. The verified machine uses a compatible separate PHP CLI and existing MySQL service; older bundled XAMPP PHP/exact MariaDB compatibility are not verified.

## Scope boundaries

No wallet, payouts, refunds, commissions, deposits/penalties, fallback winner, recommendation engine, livestream, video transcoding/CDN, attachments or group messaging. Production load, multi-node deployment, backup restore, physical devices and completed external Sandbox payment are not certified. [Testing summary](final-testing-summary.md) separates automated and manual evidence.

Sources: [routes](../backend/routes/api.php), [models](../backend/app/Models), [services](../backend/app/Services), [scheduler](../backend/routes/console.php), [C1 report](final-demo-verification.md).
