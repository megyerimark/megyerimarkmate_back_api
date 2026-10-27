#!/usr/bin/env bash
# Éles telepítés / frissítés – SSH-n belépve, a backend mappájában futtasd minden új verzió feltöltése után:
#   bash deploy.sh
# Ha a szerveren a „php” parancs régebbi verzió (pl. Rackhost / megosztott tárhely), add meg a 8.4-es PHP útvonalát:
#   PHP=/usr/bin/php8.5 bash deploy.sh        (Rackhost; máshol keresd: which -a php php8.4 php8.5)
set -euo pipefail
cd "$(dirname "$0")"

PHP="${PHP:-php}"
if [ -n "${COMPOSER:-}" ]; then COMPOSER_CMD=("$PHP" "$COMPOSER")
elif [ -f composer.phar ]; then COMPOSER_CMD=("$PHP" composer.phar)
elif command -v composer >/dev/null 2>&1 && head -c 200 "$(command -v composer)" | grep -qa php; then
  # A rendszer composerét a kiválasztott PHP-val futtatjuk (különben a régi alapértelmezett PHP futtatná).
  COMPOSER_CMD=("$PHP" "$(command -v composer)")
else
  echo "▶ Composer nincs a szerveren – letöltöm ide (composer.phar)"
  "$PHP" -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
  "$PHP" composer-setup.php --quiet && rm -f composer-setup.php
  COMPOSER_CMD=("$PHP" composer.phar)
fi

echo "▶ PHP verzió: $("$PHP" -r 'echo PHP_VERSION;') (minimum 8.4 szükséges)"
"$PHP" -r 'exit(version_compare(PHP_VERSION, "8.4.0", ">=") ? 0 : 1);' || {
  echo "✖ PHP 8.4 vagy újabb kell! Keresd meg: which -a php php84 ; ls /opt/alt/php8*/usr/bin/php /opt/*/bin/php* — majd: PHP=<útvonal> bash deploy.sh"; exit 1; }
[ -f .env ] || { echo "✖ Hiányzik a .env (másold át a régi telepítésből, vagy készítsd el a .env.production.example alapján)."; exit 1; }

echo "▶ Karbantartási mód be"
"$PHP" artisan down --retry=30 || true
# Ha bármi elhasal, az oldal ne maradjon karbantartási módban.
trap 'echo "✖ Hiba történt – az oldalt visszakapcsolom. A fenti hibaüzenetet küldd el."; "$PHP" artisan up || true' ERR

echo "▶ Régi, korábbi verziókból itt maradt fájlok törlése (*.before-v*)"
find app resources tests routes config database -name '*.before-v*' -print -delete 2>/dev/null || true

echo "▶ Függőségek (fejlesztői csomagok nélkül)"
"${COMPOSER_CMD[@]}" install --no-dev --prefer-dist --no-interaction --optimize-autoloader

echo "▶ Mentés a frissítés előtt"
"$PHP" artisan portfolio:backup --no-files || echo "  (mentés kihagyva – első telepítésnél normális)"

echo "▶ Adatbázis-migrációk"
"$PHP" artisan migrate --force

echo "▶ Régi gyorsítótárak törlése (route/config/view), majd újraépítés"
"$PHP" artisan optimize:clear
# Csak útvonal- és esemény-gyorsítótár! A config:cache/view:cache abszolút mappautakat rögzít, és megosztott
# tárhelyen (Rackhost) az SSH más útvonalon látja a projektet (/private/...), mint a webszerver
# (/var/www/clients/.../private/...) – a gyorsítótárazott config miatt a weboldal „Server Error”-t adna.
"$PHP" artisan route:cache
"$PHP" artisan event:cache

echo "▶ Képtárhely-link"
"$PHP" artisan storage:link 2>/dev/null || true

echo "▶ Karbantartási mód ki"
"$PHP" artisan up
trap - ERR

echo "✔ Kész. Ellenőrizd az adminban: Fiók és biztonság → Rendszerállapot (v18 legyen)."
echo "  Cron (ha még nincs): * * * * * cd $(pwd) && $PHP artisan schedule:run >> /dev/null 2>&1"
