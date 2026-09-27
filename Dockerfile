# syntax=docker/dockerfile:1

# ---------- Assets ----------
FROM node:20-alpine AS assets

WORKDIR /app

# package-lock.json is gitignored, so use `npm install` (not `npm ci`).
# Only the manifest is copied first so this layer caches across code edits.
COPY package.json ./
RUN npm install --no-audit --no-fund

COPY webpack.mix.js postcss.config.js tailwind.config.js ./
COPY resources/ ./resources/
RUN npm run production


# ---------- PHP dependencies ----------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
# --no-scripts: artisan package:discover needs the app source + .env, which
# aren't present yet. It runs later, at runtime, via start-container.sh.
RUN composer install \
      --no-dev \
      --no-scripts \
      --no-autoloader \
      --prefer-dist \
      --no-interaction


# ---------- Runtime ----------
FROM php:8.2-fpm-bookworm

# Pinned to 8.2: vonage/client-core caps at ~8.2 and lcobucci/clock at ~8.2.
# Both are production dependencies, so 8.3+ fails `composer install`.
ENV PHP_VERSION=8.2

# libpq-dev  -> pdo_pgsql      (postgres, if you switch DBs later)
# libzip-dev -> ext-zip        (phpspreadsheet, medialibrary)
# libicu-dev -> ext-intl       (voku/portable-ascii)
# libpng/jpeg/freetype -> gd   (medialibrary image conversions)
# libxslt1-dev -> ext-dom, ext-xml, ext-xmlreader, ext-xmlwriter
RUN apt-get update && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libxslt1-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        dom \
        exif \
        gd \
        intl \
        opcache \
        pdo_mysql \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/php/www.conf /usr/local/etc/php/conf.d/zz-www.conf

WORKDIR /var/www/html

COPY --from=vendor /app/vendor/ ./vendor/
COPY . .

# Assets built in the earlier stage overwrite the committed public/ output.
COPY --from=assets /app/public/ ./public/

# The image must not carry secrets or local-only state.
RUN rm -f public/service-account-file.json error_log public/error_log \
    && rm -rf .git node_modules \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/start-container.sh /usr/local/bin/start-container.sh
RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/start-container.sh

USER www-data

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/local/bin/start-container.sh"]
