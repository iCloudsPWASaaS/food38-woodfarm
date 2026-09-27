#!/bin/bash
# Runs migrations/caches once, then hands off to php-fpm.
# Ordered so the app is never serving while the schema is mid-migration.
set -e

cd /var/www/html

# storage:link is idempotent but warns if the link exists
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

# Warm the bytecode cache for the app itself
find storage/framework -type f -name '*.php' -exec chmod 644 {} \;

exec php-fpm --nodaemonize --fpm-config /usr/local/etc/php-fpm.conf
