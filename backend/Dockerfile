FROM php:8.4-fpm-bookworm AS runtime

RUN apt-get update && apt-get install -y --no-install-recommends \
    libfcgi-bin libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev \
    libonig-dev libzip-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" bcmath gd mbstring pdo_mysql zip opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative --no-scripts \
    && mkdir -p storage/app/public storage/app/private storage/framework/cache/data \
       storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && php artisan package:discover --no-interaction \
    && ln -s /var/www/html/storage/app/public public/storage \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/php.ini /usr/local/etc/php/conf.d/production.ini
COPY docker/fpm.conf /usr/local/etc/php-fpm.d/zz-marketplace.conf
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/laravel-entrypoint
COPY --chmod=755 docker/healthcheck.sh /usr/local/bin/laravel-healthcheck

USER www-data
EXPOSE 9000
HEALTHCHECK --interval=15s --timeout=5s --start-period=30s --retries=5 CMD ["laravel-healthcheck"]
ENTRYPOINT ["laravel-entrypoint"]
CMD ["php-fpm", "-F"]
