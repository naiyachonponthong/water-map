#!/usr/bin/env bash
# อัปเดตเวอร์ชันบนเซิร์ฟเวอร์: ./deploy/deploy.sh
set -euo pipefail
cd "$(dirname "$0")/.."

php artisan down --render="errors::503" --retry=30 || true

git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan optimize:clear
php artisan optimize            # config, route, view, event cache
php artisan storage:link 2>/dev/null || true

# ให้ queue worker และ Reverb โหลดโค้ดใหม่
php artisan queue:restart
php artisan reverb:restart 2>/dev/null || true

php artisan up
echo "deploy เสร็จ $(git rev-parse --short HEAD)"
