# Laravel backend — local development

See [root README](../README.md#local-development-with-xampp) and [local setup guide](../docs/deployment-guide.md) for prerequisites, private environment configuration and database preservation.

```powershell
composer install
# Fresh checkout only: copy .env.example to .env and fill local DB credentials.
# Generate APP_KEY only if initially empty:
php artisan key:generate
php artisan config:clear
php artisan migrate:status
# Initial DB / reviewed pending migrations only:
php artisan migrate
php artisan storage:link
php artisan view:cache
php artisan serve --host=127.0.0.1 --port=8000
```

Use a compatible PHP 8.4 CLI. Default DB: MySQL at 127.0.0.1:3306, secondhand_marketplace; never reset an existing database. The optional `./serve.ps1` applies project upload limits. Reset mail defaults to the private `storage/logs/laravel.log`. For full tests, configure the separate secondhand_marketplace_test DB using .env.testing.example and run `php artisan test --compact`; run `php vendor/bin/pint --dirty --format agent` for formatting.
