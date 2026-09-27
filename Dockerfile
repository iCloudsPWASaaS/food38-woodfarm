# syntax=docker/dockerfile:1
#
# Single-image Laravel deploy: php-fpm + nginx in one container.
# Port 8000 (nginx) -> php-fpm 9000 over the loopback interface.
#
# Why the extra steps vs. the "php-fpm + nginx" minimum:
#   - composer install : vendor/ is gitignored, so it is NOT in the repo and
#                        must be built in the image or the app cannot boot.
#   - npm ci / mix     : public/ output is committed, but rebuilding it here
#                        guarantees the deployed JS matches resources/.
#   - no ARG for secrets: DB_PASSWORD etc. are runtime env vars (Coolify), so
#                        they are never baked into a build layer.

# ---------- Assets ----------
FROM node:20-alpine AS assets
WORKDIR /app

# Lockfile is committed and webpack is pinned; a plain `npm install` resolves
# webpack 5.111.x, which drops lib/SizeFormatHelpers and breaks laravel-mix 6.
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY webpack.mix.js postcss.config.js tailwind.config.js ./
COPY resources/ ./resources/

# mix writes mix-manifest.json + hashed assets into public/, so it must exist.
RUN mkdir -p public && npm run production


# ---------- Runtime ----------
FROM php:8.2-fpm-bookworm

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update && apt-get install -y --no-install-recommends \
      nginx \
      libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev \
      libicu-dev unzip curl ca-certificates \
 && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" \
      pdo_mysql bcmath opcache exif gd intl zip \
 && rm -rf /var/lib/apt/lists/*

# php-fpm listens on 9000; nginx proxies to it. Runs as www-data via the pool
# config below, and nginx workers drop privileges in its own config.
RUN printf '%s\n' \
 '[www]' \
 'user = www-data' \
 'group = www-data' \
 'listen = 127.0.0.1:9000' \
 'listen.owner = www-data' \
 'listen.group = www-data' \
 'pm = dynamic' \
 'pm.max_children = 20' \
 'pm.start_servers = 2' \
 'pm.min_spare_servers = 2' \
 'pm.max_spare_servers = 4' \
 > /usr/local/etc/php-fpm.d/zzz-app.conf

RUN printf '%s\n' \
 'user www-data;' \
 'pid /run/nginx.pid;' \
 'events { worker_connections 1024; }' \
 'http {' \
 '  include /etc/nginx/mime.types;' \
 '  default_type application/octet-stream;' \
 '  sendfile on;' \
 '  access_log /dev/stdout;' \
 '  error_log /dev/stderr warn;' \
 '  client_body_temp_path /tmp/client_temp;' \
 '  proxy_temp_path /tmp/proxy_temp;' \
 '  fastcgi_temp_path /tmp/fastcgi_temp;' \
 '  uwsgi_temp_path /tmp/uwsgi_temp;' \
 '  scgi_temp_path /tmp/scgi_temp;' \
 '  include /etc/nginx/conf.d/*.conf;' \
 '}' \
 > /etc/nginx/nginx.conf

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Composer binary only; extensions come from the PHP base above, so the
# platform check sees PHP 8.2 with gd/exif/zip present. (The composer:2 image
# ships PHP 8.5 and would fail on the PHP 8.2 caps in the lock file.)
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini

WORKDIR /var/www/html

# Manifest first so this layer caches until dependencies actually change.
COPY composer.json composer.lock ./
# --no-scripts: artisan package:discover needs the app source, which is copied
# in the next step. --no-autoloader: the real classmap is dumped further down,
# once the full source tree exists.
RUN composer install \
      --no-dev \
      --no-scripts \
      --no-autoloader \
      --prefer-dist \
      --no-interaction

# .dockerignore excludes vendor/ and node_modules/, so this cannot clobber the
# composer output above.
COPY . .

# Image-built assets take precedence over the committed public/ output.
COPY --from=assets /app/public/ ./public/

# Deferred from `composer install --no-autoloader`: vendor/autoload.php must
# exist and the classmap should cover the real source tree.
RUN composer dump-autoload --optimize --no-dev --no-scripts \
 && rm -f public/service-account-file.json error_log public/error_log \
 && rm -rf .git node_modules \
 && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
            storage/logs storage/app/public bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache /var/www/html

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/start-container.sh /usr/local/bin/start-container.sh
RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/start-container.sh

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/local/bin/start-container.sh"]
