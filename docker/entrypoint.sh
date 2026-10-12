#!/bin/sh
set -e
cd /var/www

if [ "$PCOV" = "1" ]; then
    printf 'pcov.enabled=1\n' > /usr/local/etc/php/conf.d/zz-pcov.ini
fi

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache || true


if [ "$#" -gt 0 ]; then
    exec "$@"
fi


if [ -z "$APP_KEY" ] && ! grep -Eq '^APP_KEY=.+' .env 2>/dev/null; then
    [ -f .env ] || cp .env.example .env
    php artisan key:generate --force
fi

php artisan migrate --force
if [ "$SEED_DATABASE" = "true" ]; then
    php artisan db:seed --force
fi

php artisan config:cache

exec php-fpm
