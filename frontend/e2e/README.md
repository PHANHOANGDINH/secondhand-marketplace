# E2E journeys A–H

Browser journeys driven over the Chrome DevTools Protocol with two headless Edge instances. They run on an **isolated stack bound to the test database** and never touch the development database.

| Journey | What it does |
| --- | --- |
| A | Guest searches and filters, opens a product, is sent to sign in, adds to cart, applies a platform voucher, pays COD |
| B | Buy now with a shop voucher; the other cart line stays in the cart |
| C | Seller quick listing with an image → admin approves → seller edits stock → seller confirms an order |
| D | Two browsers bid in realtime, outbid toast, auction ends, winner checks out, loser cannot |
| E | Video theater, comment appears in the second browser without a reload |
| F | Chat buyer → seller, wishlist, notifications, product report → admin takes it |
| G | Admin action center: reconcile a payment, cancel a live auction, suspend a shop |
| H | First-time tour: opens after sign-in, reopens until skipped, replay, ESC, one-time auction note |
| V | Voucher management screens for seller and admin (`vouchers.mjs`) |

Every screenshot is taken at 1440 and 390 px; the run fails the report on horizontal overflow or any uncaught JS error.

## Run

All commands use the Herd PHP from `CLAUDE.md`. Ports: backend 8001, Reverb 8081, Vite 5174.

1. Put data into the **test** database. `load_test.php` refuses any other database:
   `APP_ENV=testing php frontend/e2e/load_test.php <dump.sql>` (a dump of the demo data), then
   `APP_ENV=testing DEMO_PASSWORD=<any 12+ chars> php artisan db:seed --class=FinalDemoSeeder`.
2. Create `frontend/e2e/secret.json` with `{"password": "<throwaway>"}` and run `APP_ENV=testing php frontend/e2e/prepare.php`. It sets that password on the `c1-*` demo accounts **in the test database** and writes `data.json`.
3. Start the stack (three terminals), all with `APP_ENV=testing`:
   - backend, from `backend/public`: `SESSION_DRIVER=file BROADCAST_CONNECTION=reverb APP_URL=http://localhost:5174 SANCTUM_STATEFUL_DOMAINS=localhost:5174 FRONTEND_URLS=http://localhost:5174 REVERB_APP_ID=e2e REVERB_APP_KEY=e2ekey REVERB_APP_SECRET=e2esecret REVERB_HOST=127.0.0.1 REVERB_PORT=8081 REVERB_SCHEME=http php -S 127.0.0.1:8001 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`
   - Reverb, from `backend`: same `REVERB_*` variables, `php artisan reverb:start --host=127.0.0.1 --port=8081`
   - Vite, from `frontend`: `VITE_BACKEND_PROXY_TARGET=http://127.0.0.1:8001 VITE_REVERB_APP_KEY=e2ekey VITE_REVERB_HOST=127.0.0.1 VITE_REVERB_PORT=8081 VITE_REVERB_SCHEME=http npx vite --port 5174`
4. `bash frontend/e2e/go.sh journeys.mjs` (all journeys, in order) and `bash frontend/e2e/go.sh vouchers.mjs`. Pass journey letters to run a subset, e.g. `go.sh journeys.mjs D E`.

Journeys change data (orders, a suspended shop, a cancelled auction): reload the data (step 1–2) before a second full run. Journey D creates its own auction and ends it by moving `ends_at` and running `auctions:sync` once.

## Clean up

Stop the three processes and Edge, delete `secret.json`, and remove what the stack wrote outside the database: session files in `backend/storage/framework/sessions` created during the run and the uploaded image folders of the test products in `backend/storage/app/public/products`. The next backend test run rebuilds the test database.

`rows.php` takes a row-level snapshot (`id → sha256`) of whichever database the environment points to and compares two snapshots; it is how "only additions, no changed rows" is checked.
