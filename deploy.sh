#!/usr/bin/env bash
# Éles telepítés / frissítés – futtasd a backend mappájában minden új verzió feltöltése után:
#   bash deploy.sh
# (cPanelen: Terminál, vagy SSH. Ha nincs terminálod, kérd meg a tárhelyszolgáltatót, vagy futtasd a
#  parancsokat egyenként a cPanel „Cron Jobs” egyszeri feladataként.)
set -euo pipefail
cd "$(dirname "$0")"

echo "▶ PHP verzió: $(php -r 'echo PHP_VERSION;') (minimum 8.4 szükséges)"
php -r 'exit(version_compare(PHP_VERSION, "8.4.0", ">=") ? 0 : 1);' || { echo "✖ PHP 8.4 vagy újabb kell!"; exit 1; }

echo "▶ Karbantartási mód be"
php artisan down --retry=30 || true

echo "▶ Függőségek (fejlesztői csomagok nélkül)"
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

echo "▶ Mentés a frissítés előtt"
php artisan portfolio:backup --no-files || echo "  (mentés kihagyva – első telepítésnél normális)"

echo "▶ Adatbázis-migrációk"
php artisan migrate --force

echo "▶ Régi gyorsítótárak törlése (route/config/view), majd újraépítés"
php artisan optimize:clear
php artisan optimize

echo "▶ Képtárhely-link"
php artisan storage:link 2>/dev/null || true

echo "▶ Karbantartási mód ki"
php artisan up

echo "✔ Kész. Ellenőrizd az adminban: Fiók és biztonság → Rendszerállapot."
