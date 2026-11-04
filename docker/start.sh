#!/bin/sh
# Démarre le service : « web » (API + site + back-office) ou « worker » (traitement des photos).
set -e
cd /app
php artisan config:cache >/dev/null
php artisan route:cache >/dev/null
php artisan view:cache >/dev/null

case "$1" in
  worker)
    exec php artisan queue:work --sleep=1 --tries=1 --timeout=300 --memory=256 --max-time=3600
    ;;
  release)
    php artisan migrate --force
    exec php artisan db:seed --force
    ;;
  *)
    exec frankenphp php-server --root public/ --listen ":${PORT:-8080}"
    ;;
esac
