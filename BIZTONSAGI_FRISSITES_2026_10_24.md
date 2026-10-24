# Megyeri Márk | Webfejlesztés — biztonsági frissítés

Ez a csomag a meglévő Laravel 13 backend teljes, módosított forrása. Nem tartalmazza a saját `.env`, `vendor`, ügyféladatbázis és feltöltött fájlok tartalmát. Az eredeti migrációk megmaradtak, a módosítás két új, **additív** migrációt ad hozzá.

## Mi változott?

- A privát ügyféllinkek alapértelmezésben 90 napig érvényesek. Az új link kiküldésekor az előző érvényét veszti. A meglévő linkek a migrációkor kapnak új, 90 napos lejáratot.
- A tokenlejárat az ügyféladatok, üzenetek, dokumentumletöltések és ajánlatelfogadás minden végpontján érvényesül.
- A jelszó módosításakor a rendszer törli az admin többi adatbázis-munkamenetét és személyes API-tokenjeit.
- Admin biztonsági végpontok: `/api/admin/security`, `/api/admin/security/sessions`, `/api/admin/security/sessions/others`, `/api/admin/security/two-factor`.
- Opcionális, e-mailes kétlépcsős adminbelépés. Alapból **kikapcsolva**, mert valós SMTP-szolgáltatás kell hozzá. A kód 10 percig él és legfeljebb 5 próbálkozás engedélyezett.
- A publikus egyedi projektlekérdezésből is kimaradnak a demóprojektek.
- Bővült a biztonsági tesztkészlet és az alapértelmezett HTTP válaszfejlécek köre.

## Telepítés Macen

1. Készíts mentést a teljes backendmappáról, a MySQL adatbázisról, az `.env` fájlról és a `storage/app` tartalmáról.
2. Másold a ZIP-ben lévő fájlokat a saját Laravel-projektedre, a **saját `.env` és `storage` tartalmának felülírása nélkül**. Ne töröld a projektmappát.
3. A Laravel mappában futtasd: `composer install --no-dev --optimize-autoloader` (éles buildhez), vagy helyben `composer install`.
4. Helyi fejlesztői adatbázison futtasd: `php artisan migrate` — **ne** `migrate:fresh`.
5. Teszteld: `php artisan test`, majd `php artisan route:list`.
6. Élesítés után: `php artisan optimize:clear` és megfelelő PHP 8.3+ / MySQL konfiguráció.

## Rackhost: ha nincs PHP parancssor

Ez nem feltétlenül jelenti, hogy a webes PHP nem működik. Az éles PHP verziójának legalább 8.3-nak kell lennie, és a webhely PHP-kéréseit a Laravel `public/index.php` fájljához kell irányítani. **Soha ne tedd nyilvánosan elérhetővé a Laravel projekt gyökerét vagy `.env` fájlját.**

Ha éles környezetben sem Composer, sem Artisan nem futtatható:

1. A szükséges `vendor` függőségeket a megfelelő PHP-platformon készítsd el és töltsd fel.
2. A két új adatbázis-migráció helyett egyszer, teljes adatbázismentés után alkalmazható a `RACKHOST_SCHEMA_PATCH_2026_10_24.sql` tartalma phpMyAdminon vagy a MySQL kliensben. **Ne futtasd, ha ugyanazokat a migrációkat Artisan már alkalmazta.**
3. A Laravel PHP belépési pontot és az Angular API-útvonalakat a tárhely Apache szabályaiban állítsd be. A frontend `.htaccess` önmagában nem helyettesít backend-routingot.
4. Ellenőrizd az `/up`, `/api/site-settings`, `/sanctum/csrf-cookie`, `/admin/login`, `/admin/forgot-password`, `/admin/verify-login` és `/api/admin/me` végpontokat a megfelelő HTTP-módszerrel.

## Éles `.env` ellenőrzőlista

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://megyerimarkmate.hu
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SANCTUM_STATEFUL_DOMAINS=megyerimarkmate.hu,www.megyerimarkmate.hu
CORS_ALLOWED_ORIGINS=https://megyerimarkmate.hu,https://www.megyerimarkmate.hu
PORTFOLIO_FRONTEND_URL=https://megyerimarkmate.hu
PORTFOLIO_CLIENT_ACCESS_DAYS=90
PORTFOLIO_MAIL_ENABLED=true
```

Ezek minták: a tényleges domíniozást és tárhelykialakítást ellenőrizni kell. A `SESSION_COOKIE` / `SESSION_DOMAIN` értékét a konfigurációhoz igazítsd. **Meglévő rendszerben soha ne cseréld le véletlenül az `APP_KEY` értékét.**

## Kétlépcsős bejelentkezés

Mielőtt bekapcsolod, próbáld ki a valós SMTP-t. Ezután az admin „Fiók és jelszó / Biztonsági központ” menüjében add meg a jelenlegi jelszavadat, és engedélyezd az e-mailes kódot. A következő belépéskor hatjegyű kód érkezik e-mailben. Ha az SMTP szolgáltató később kiesik, a belépés is akadályozott lehet — ezért tarts fenn biztonságos üzemeltetői helyreállítási eljárást.

## Amihez további éles beállítás / döntés kell

- Az adatkezelési tájékoztató még **tervezet**. A tényleges adatkezelői adataid, szolgáltatóid, jogalapok és megőrzési határidők nélkül nem lehet jogszerű kész szövegként megjelölni. Ezt az éles forgalom előtt véglegesítsd.
- A fájlok automatikus vírusellenőrzése, egy külső botvédelmi szolgáltatás, teljes CSP-szabályzat és a fizetési integráció környezeti/külső szolgáltatói beállítást igényel.
- Az ajánlatküldés jelenleg szinkron SMTP-t használ. Nagyobb forgalomnál megbízható queue + outbox és időzített feldolgozás indokolt; shared hoston ehhez cron/worker lehetőség kell.
- Legalább napi mentés, rendszeres visszaállítási próba és függőségellenőrzés (`composer audit --locked`, `npm audit`) szükséges.

## Biztonságos frissítés

Ne használd `rsync --delete` kapcsolóval a frissítő csomagokat, amíg nem ellenőrizted a célmappákat. Különösen a `.env`, a `storage`, a `public/storage` hivatkozás, az éles `vendor` és a meglévő ügyféladatok megőrzése fontos.
