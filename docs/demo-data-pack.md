> Historical verification report. Container infrastructure has since been removed. Results below retain their original context; use the [current local setup](deployment-guide.md) and [local verification](xampp-local-verification.md) for present instructions.

# Demo marketplace data pack

## Scope / baseline

Inspected both databases before seeding. The actual deployed catalog at `http://localhost:8088` had **0 public products**. Development `secondhand_marketplace` had **28 older generic `demo-product-*` listings** with existing relationships; they were preserved. The pack was applied only to the isolated Docker database `marketplace_phase14`.

The dedicated `DemoMarketplaceSeeder` now supplies a realistic Vietnamese **catalog-only** pack instead of the old 28-item generic fixture generator. It does not create carts, orders, payments, reviews or audit activity. No schema, API, policy, moderation/checkout rules or frontend behavior changed.

## Shops and categories

| Demo shop | Public slug | Seller email (fictional) | Products |
| --- | --- | --- | ---: |
| Góc Công Nghệ Cũ | demo-pack-tech | demo-pack-tech@example.test | 12 |
| Nhà Nhỏ Tiện Ích | demo-pack-home | demo-pack-home@example.test | 3 |
| Tủ Đồ & Góc Sách | demo-pack-style | demo-pack-style@example.test | 5 |

All pack shops, seller accounts and six categories are ACTIVE. Products are ACTIVE with stock >0 and published_at set, and satisfy the application's existing publicVisible query, including active owner/ancestor rules. New sellers receive random undisclosed passwords, with no admin privilege or preset credentials. To use a seller in the presentation, choose a private password through the existing Forgot Password/Mailpit flow ([password recovery guide](password-reset-verification.md)). Use a separate buyer account; owning a shop does not permit buying its own products.

Fresh-pack category mix:

- Điện thoại & máy tính bảng: **4**
- Laptop & máy tính: **4**
- Phụ kiện công nghệ: **4**
- Đồ gia dụng: **3**
- Thời trang: **3**
- Sách & học tập: **2**

Visible existing categories with canonical slugs are reused without edits. Otherwise, reserved `demo-pack-<group>` categories are created. On a partially populated database, the global public-count cap and preservation of existing inventory take priority over forcing this exact fresh-pack distribution.

## Commands

The backend image must include the new seeder/resources. Existing private env/APP_KEY/database credentials remain unchanged.

```powershell
# Workspace root; existing separate verification database/volumes.
docker compose --env-file .env.docker-verification build backend
docker compose --env-file .env.docker-verification up -d --no-deps backend scheduler
docker compose --env-file .env.docker-verification restart nginx
docker compose --env-file .env.docker-verification exec -T -e DEMO_MARKETPLACE_ENABLED=true backend php artisan db:seed --class=DemoMarketplaceSeeder --force --no-interaction
```

Run the last command again safely. Production-like APP_ENV requires the explicit per-command opt-in as well as Laravel's --force. The seeder is otherwise limited to local/testing. Do not invoke DatabaseSeeder against Docker; its existing local/test-only guard remains in place.

For an intentionally selected local database, from `backend`:

```powershell
php artisan db:seed --class=DemoMarketplaceSeeder --no-interaction
```

Read the public count first. The seeder adds at most `max(0, 20 - current_public_count)` and never blindly adds twenty. At 20 or more existing public records, it adds none. The development database's existing 28 records were not reseeded during this verification.

## Additive / rerun / reset behavior

- Deterministic product slugs `demo-pack-<key>` and deterministic UUID image filenames prevent rerun duplication. The model has no SKU field; no schema field was added.
- Existing product rows, including sold/hidden/soft-deleted items and edited stock/prices/descriptions, are never updated, republished, restocked, restored or deleted. Reserved seller/category/shop conflicts fail rather than overwrite unrelated records. Reserved product slug collisions are skipped.
- New record creation is transactional. No unrelated account receives a role/status/password change. Existing visible categories are reused read-only.
- Reruns restore a missing **referenced pack image file** from its repository asset, without changing image rows, primary selection or user-uploaded files. Deliberately removed image rows are not recreated automatically. Use existing Seller product-image controls to replace/remove images.
- Repeated seeding is additive, not a destructive reset. Use normal Seller/Admin controls to intentionally adjust stock or moderation; seeding will not undo those actions. Do not delete product/order/review relationships to recreate a screenshot.

For a completely clean presentation baseline, create a **new isolated Docker project and volume** instead of erasing the current database. Copy your private verification env to another gitignored private env file and change these non-secret settings (keep private credentials private):

```dotenv
COMPOSE_PROJECT_NAME=marketplace-demo-reset
DB_DATABASE=marketplace_demo_reset
PUBLIC_HOST=localhost:8089
HTTP_PORT=8089
PUBLIC_SCHEME=http
PUBLIC_PORT=8089
SESSION_SECURE_COOKIE=false
APP_SUBNET=172.30.15.0/24
NGINX_INTERNAL_IP=172.30.15.10
MAILPIT_HTTP_PORT=8026
MAILPIT_SMTP_PORT=1026
```

Keep PASSWORD_RESET_URL unset to derive it from the new public origin, or update it to `http://localhost:8089/reset-password`. Use available ports/subnets for your machine.

```powershell
docker compose --env-file .env.demo-reset --profile demo up -d --build
docker compose --env-file .env.demo-reset exec -T -e DEMO_MARKETPLACE_ENABLED=true backend php artisan db:seed --class=DemoMarketplaceSeeder --force --no-interaction
```

Compose performs standard initial migrations on the new database. Existing project/database/upload volumes are retained. **No migrate:fresh, down -v, schema reset or deletion of user records is required.** This separate reset example is an operator option, not an additional deployment created during verification.

## Local image provenance / persistence

The pack includes **24 original, clearly labeled product illustrations**, not actual seller photographs. `backend/resources/demo-marketplace/render_assets.py` draws the artwork without downloaded photos/hotlinks; `catalog.json` supplies the manifest. A few listings have a second illustrative angle. Optional asset regeneration requires Python/Pillow on a developer workstation; production only uses committed WebP files.

```powershell
python backend/resources/demo-marketplace/render_assets.py
```

All files are 960×720 WebP, lightweight and valid under the current MIME/size rules. Exactly one primary image exists per product. Asset files are copied to `storage/app/public/products/<id>/<deterministic-uuid>.webp`; existing ProductImageService path checks are satisfied. Nginx serves them at the same public origin through `/storage`, from the persistent `laravel_storage` volume. Rebuild the backend image after intentionally changing source assets; ordinary reruns never overwrite existing public files.

## Final inventory

Prices and condition descriptions are fictional, plausible demo values, not verified live offers. Every listing explicitly identifies its image as demo illustration.

| Product | VND | Stock | Condition | Images | Stable slug |
| --- | ---: | ---: | --- | ---: | --- |
| iPhone 13 128GB màu xanh | 7.900.000 | 1 | GOOD | 2 | demo-pack-iphone-13 |
| Samsung Galaxy S22 128GB | 6.500.000 | 1 | GOOD | 1 | demo-pack-galaxy-s22 |
| iPad Air 5 Wi-Fi 64GB | 9.900.000 | 1 | LIKE_NEW | 1 | demo-pack-ipad-air-5 |
| Xiaomi Redmi Note 12 128GB | 2.400.000 | 2 | GOOD | 1 | demo-pack-redmi-note-12 |
| MacBook Air M1 8GB / 256GB | 11.900.000 | 1 | GOOD | 2 | demo-pack-macbook-air-m1 |
| Dell Inspiron 15 i5 / 8GB / 256GB | 6.200.000 | 1 | FAIR | 1 | demo-pack-dell-inspiron-15 |
| ThinkPad T14 Ryzen 5 / 16GB / 512GB | 8.900.000 | 1 | GOOD | 1 | demo-pack-thinkpad-t14 |
| ASUS VivoBook 14 i5 / 8GB / 512GB | 7.200.000 | 1 | GOOD | 1 | demo-pack-asus-vivobook |
| AirPods Pro 2 hộp sạc Lightning | 3.100.000 | 1 | GOOD | 2 | demo-pack-airpods-pro-2 |
| Logitech MX Master 3 màu graphite | 1.350.000 | 2 | GOOD | 1 | demo-pack-mx-master-3 |
| Keychron K2 bàn phím cơ brown switch | 1.200.000 | 1 | GOOD | 1 | demo-pack-keychron-k2 |
| Sony WH-1000XM4 màu đen | 3.500.000 | 1 | GOOD | 1 | demo-pack-sony-wh1000xm4 |
| Nồi chiên không dầu Philips 4,1L | 1.150.000 | 1 | GOOD | 1 | demo-pack-air-fryer |
| Máy pha cà phê mini DeLonghi | 1.850.000 | 1 | GOOD | 1 | demo-pack-coffee-maker |
| Quạt đứng Xiaomi Smart Fan 2 | 850.000 | 1 | GOOD | 1 | demo-pack-xiaomi-fan |
| Áo khoác denim xanh size M | 320.000 | 1 | GOOD | 2 | demo-pack-denim-jacket |
| Giày Adidas Stan Smith size 40 | 650.000 | 1 | FAIR | 1 | demo-pack-stan-smith |
| Balo laptop 15,6 inch màu xám | 280.000 | 2 | GOOD | 1 | demo-pack-laptop-backpack |
| Clean Code – sách tiếng Anh đã qua sử dụng | 220.000 | 1 | GOOD | 1 | demo-pack-clean-code |
| Giáo trình Cơ sở dữ liệu | 65.000 | 2 | FAIR | 1 | demo-pack-database-textbook |

## Verification

| Check | Result |
| --- | --- |
| Docker public count, first run | PASS: **0 → 20**, added 20 |
| Second seeder run | PASS: **20 → 20**, added 0 |
| Rerun row hashes | PASS: all 46 Docker tables identical; no duplicates or row edits |
| Unrelated Docker data | PASS: every pre-existing row hash preserved |
| Category / shop mix | PASS: 4/4/4/3/3/2; shops 12/3/5 |
| Public product details | PASS: all 20 through real Nginx/API |
| Images | PASS: 24 local WebP HTTP 200; dimensions/MIME/hash checked; one primary each |
| Browser home/list/detail | PASS: 1440 / 1024 / 390px; actual API, no mocks |
| All product/shop pages | PASS: 20 details and 3 public shop pages |
| Homepage scroll/navigation cleanup | PASS: catalog CTA works; no residual pin spacer after navigation |
| Broken images / console / overflow | PASS: no broken product images, unexpected JavaScript errors or page overflow |
| Docker restart | PASS: 20 products and all 24 image hashes unchanged after MySQL/backend/Nginx/scheduler restart |
| Docker app-container recreation | PASS: same 20 products/24 image hashes and all 46 table snapshots |
| Development database | PASS: all 46 table counts/SHA-256 hashes unchanged; public count remains 28 |
| Seeder regression | PASS: 9 tests, 305 assertions |
| Full backend regression | PASS: 218 tests, 2,455 assertions |
| Full frontend regression | PASS: 190 tests, 18 files |
| Pint | PASS |
| Docker backend build | PASS |

Source change inventory: `DemoMarketplaceSeeder.php`, updated `DemoSeederTest.php`, new `DemoMarketplacePackTest.php`, this report and `demo-guide.md`, plus the catalog manifest, original artwork generator/provenance README and 24 WebP images under `backend/resources/demo-marketplace`. Earlier auth/UI work is preserved. No dependency was added and no frontend source changed.

## Limitations / decision

- This is fictional demo inventory with original illustrations, not authentic item photography or real offers.
- The development database deliberately retains its older 28 public demo products and existing related records.
- Existing moderation/user edits/deletions are respected; reruns cannot force them back to a pristine pack. Use normal UI actions or a new isolated demo project for a clean baseline.
- The pack creates no artificial completed purchases/reviews. Review sections remain honestly empty until a valid demo purchase is fulfilled.

**PASS — the deployed marketplace has the curated 20-product public demo pack and is ready for catalog demonstration.**
