#!/bin/bash
# Prepares runtime state that cannot be baked into the image, because
# storage/ and .env are supplied by the environment (volume + Coolify vars).
set -e

cd /var/www/html

# The base image ships a .env placeholder for a bare php-fpm app. Laravel needs
# real values from the environment, so drop it if it exists.
rm -f .env

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
         storage/logs storage/app/public bootstrap/cache

# Coolify usually mounts storage/ as a volume owned by root
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

exec "$@"
