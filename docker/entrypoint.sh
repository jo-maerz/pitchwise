#!/bin/sh
set -e
cd /var/www/html

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is empty. Generate one with:" >&2
    echo "  docker compose run --rm --no-deps -e APP_KEY=x app php artisan key:generate --show" >&2
    echo "and put it in .env.docker." >&2
    exit 1
fi

# Spool shared with the omr container (PDF recognition); it runs as another user, so keep it open.
mkdir -p storage/app/private/omr/in storage/app/private/omr/out
chmod 777 storage/app/private/omr storage/app/private/omr/in storage/app/private/omr/out
chown -R www-data:www-data storage bootstrap/cache

# Wait for the database (the PDO check also proves the credentials work).
until su-exec www-data php -r '
    try { new PDO("mysql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT") ?: 3306), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); }
    catch (Throwable $e) { fwrite(STDERR, $e->getMessage()."\n"); exit(1); }
' 2>/dev/null; do
    echo "Waiting for the database..."
    sleep 2
done

su-exec www-data php artisan optimize

if [ "$RUN_MIGRATIONS" = "true" ]; then
    su-exec www-data php artisan migrate --force
    su-exec www-data php artisan storage:link >/dev/null 2>&1 || true
    touch /tmp/ready
fi

# php-fpm needs root for its master process; workers drop to www-data themselves.
if [ "$1" = "php-fpm" ]; then
    exec "$@"
fi
exec su-exec www-data "$@"
