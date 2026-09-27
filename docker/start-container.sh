#!/bin/bash
# Prepares the app once (storage link, caches, migrations, queue), then runs
# php-fpm in the background and nginx in the foreground as PID 1's child.
set -e

cd /var/www/html

# nginx needs /run writable when it starts as www-data
mkdir -p /run/nginx /tmp/client_temp 2>/dev/null || true

# storage:link is idempotent but warns if the link already exists
php artisan storage:link --force 2>/dev/null || php artisan storage:link || true

# Queue jobs (image downloads, notifications) - QUEUE_CONNECTION=database
if [ "${RUN_QUEUE_WORKER:-true}" = "true" ]; then
  php artisan config:clear >/dev/null 2>&1 || true
  php artisan queue:work --sleep=3 --tries=3 --max-time=3600 \
    >> storage/logs/queue.log 2>&1 &
  echo "queue worker started (pid $!)"
fi

# Config/route/view caches must be rebuilt for the production environment
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Migrations last: anything cached above can depend on the schema
php artisan migrate --force

find storage/framework -type f -name '*.php' -exec chmod 644 {} \; 2>/dev/null || true

# php-fpm in the background (nodaemonize would block), nginx in the foreground
# so the container stays alive and receives signals.
php-fpm --daemonize

exec nginx -g 'daemon off;'
