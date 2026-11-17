# PHP 8.4 CLI for local development: API (artisan serve), Reverb and the scheduler share this image.
# The code is bind-mounted from the host; nothing from the repository is baked in except the upload limits.
FROM php:8.4-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libwebp-dev libfreetype6-dev unzip \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" bcmath exif gd intl pcntl pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

# Same limits as backend/serve.ps1: 50 MiB product and review videos.
COPY backend/dev/uploads.ini /usr/local/etc/php/conf.d/zz-uploads.ini

WORKDIR /var/www/backend
