# Megyeri Márk | Webfejlesztés – Laravel backend

A meglévő **Laravel 13 / PHP 8.3+ / MySQL / Sanctum** projekted kiegészített backendje az Angular portfólió- és ajánlatkérő oldalhoz. A korábban létrehozott migrációkat **nem módosítottam**, csak újakat adtam hozzá. A meglévő `.env` és az adatbázisod **nincs a ZIP-ben**, ezért a saját helyi beállításaidat meg kell őrizned.

## Funkciók

- Nyilvános ajánlatkérés: típus, stílus, színek, funkciók, inspirációs URL-ek, költségkeret, határidő, karbantartás, ügyféladatok.
- Beviteli validáció, 5 kérés / perc korlátozás, egyszerű rejtett honeypot mező, adatkezelési tájékoztató megismerésének visszajelzése.
- Saját adminfiók: nincs nyilvános regisztráció, Sanctum session/cookie hitelesítés, CSRF-védelem, adminjogosultság.
- Admin: ajánlatok keresése, szűrése, lapozása, státusz, árajánlat összege (HUF), belső megjegyzések, végleges törlés.
- Admin: dashboard statisztika, érdeklődők/ügyfelek áttekintése (ajánlatok alapján), tevékenységnapló.
- Admin: szolgáltatások és portfólióprojektek CRUD, publikálás és sorrend, képfeltöltés max. 5 MB, demo-jelölés.
- Nyilvános: csak publikált referenciák és aktív szolgáltatások, weboldal tartalmi beállításai.
- Opcionális automatikus adminértesítés és ügyfél-visszaigazolás, SMTP vagy helyi naplózó mailer.
- PHPUnit feature-tesztek a fő API-folyamatokra.

## 1. Telepítés meglévő projektbe

1. **Először készíts biztonsági mentést** a teljes korábbi projektmappáról és a MySQL adatbázisról.
2. A ZIP tartalmával frissítsd a korábbi Laravel projektedet. **A saját `.env` fájlodat ne írd felül**, és a korábbi `vendor` mappádat / Composer függőségeidet telepítsd a zárolt csomagok alapján.
3. Terminálban a Laravel mappában:

```bash
composer install
php artisan optimize:clear
php artisan migrate
php artisan storage:link
php artisan route:list --path=api
php artisan serve --host=localhost
```

Ne használd a `migrate:fresh` parancsot egy már adatokat tartalmazó adatbázison: az **törölné** az eddigi táblákat.

Ha a helyi `.env` még nincs meg, másold a `.env.example` fájlt `.env` névre és futtasd `php artisan key:generate`, majd add meg a saját MySQL-kapcsolatodat. Meglévő `.env` esetén **ne generálj új APP_KEY-t**, mert a régi munkamenetek és titkosított adatok érvényüket veszíthetik.

A meglévő `.env`-hez add hozzá vagy módosítsd a következőket:

```dotenv
APP_URL=http://localhost:8000
SANCTUM_STATEFUL_DOMAINS=localhost:4200,localhost:8000
CORS_ALLOWED_ORIGINS=http://localhost:4200
SESSION_DRIVER=database
SESSION_DOMAIN=null
PORTFOLIO_ADMIN_EMAIL=te-sajat-cimed@example.com
PORTFOLIO_MAIL_ENABLED=false
PORTFOLIO_CUSTOMER_RECEIPT_ENABLED=true
```

Az `APP_NAME` lehet `"Megyeri Márk | Webfejlesztés"`. Helyi fejlesztéskor az Angular legyen `http://localhost:4200`, a Laravel pedig `http://localhost:8000`. **Ne keverd a `localhost` és `127.0.0.1` neveket a böngészőben.** A MySQL adatbázis gazdája ettől függetlenül maradhat `127.0.0.1`.

## 2. Admin

A korábban létrehozott saját adminfiók megmarad. Ha újra kell készíteni / jelszót kell cserélni terminálból:

```bash
php artisan admin:create
```

A program rejtve kéri a jelszót (legalább 12 karakter). Az adminfelületre **nincs nyilvános regisztráció**. Más fiókok alapértelmezés szerint nem adminok.

**Sanctum SPA flow** (a nyilvános ajánlatbeküldéshez is szükséges a CSRF-cookie a stateful Angular SPA-ból):

1. `GET /sanctum/csrf-cookie` – böngészőből, cookie-k engedélyezésével (`withCredentials: true`).
2. A Laravel által küldött `XSRF-TOKEN` cookie dekódolt értékét küldd `X-XSRF-TOKEN` fejlécben, `POST /admin/login` kéréshez, JSON `{ "email": "...", "password": "..." }` törzzsel.
3. Az Angular `withCredentials: true` opcióval kérheti a `GET /api/admin/me`, majd a további `/api/admin/*` útvonalakat.
4. `POST /admin/logout` ugyanazzal a CSRF-fejléccel és cookie-val.

**Fontos Angular sajátosság:** alapértelmezésben az Angular HttpClient XSRF interceptora nem minden esetben csatolja a fejlécet abszolút cross-origin URL-ekhez. Helyi fejlesztéshez javasolt Angular dev-server proxy, amely az `/api`, `/admin` és `/sanctum` útvonalakat a Laravelhez továbbítja. Így relatív URL-ekkel működik az XSRF interceptor. Élesítéskor szintén a cookie/same-site/CORS szabályokat a tényleges domainekhez kell igazítani.

## 3. Ajánlatbeküldés minta

```bash
curl -i http://localhost:8000/api/quote-requests \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{
    "customer_name":"Teszt Ügyfél",
    "email":"teszt@example.com",
    "website_type":"business",
    "design_style":"dark-premium",
    "colors":["#7950F2"],
    "features":["ajanlatkeres", "kapcsolatfelvetel"],
    "budget_range":"100000-250000",
    "project_description":"Reszponzív céges weboldalt szeretnék.",
    "wants_maintenance":true,
    "privacy_notice_acknowledged":true,
    "website":""
  }'
```

Siker esetén `201 Created`, és egy azonosító (`reference`) érkezik. Minden mezőt a szerver validál, az ügyfél a `status` vagy `admin_notes` értékeit nem tudja felülírni.

## 4. E-mail küldés

A próbaprojektben az e-mail küldés alapból **ki van kapcsolva** (`PORTFOLIO_MAIL_ENABLED=false`).

- Teszteléshez beállítható `MAIL_MAILER=log`, ekkor a levelek a `storage/logs/laravel.log` fájlban jelennek meg.
- Élesítéskor állíts be ellenőrzött SMTP-szolgáltatót (`MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`), és `PORTFOLIO_ADMIN_EMAIL` címet.
- Ezután `PORTFOLIO_MAIL_ENABLED=true`. Az ügyfél-visszaigazolást `PORTFOLIO_CUSTOMER_RECEIPT_ENABLED` vezérli.
- Az e-mailek jelenleg **szinkron** küldődnek. Sikertelen küldésnél a már eltárolt ajánlat megmarad, az API továbbra is 201-es választ küld; a hiba típusa bekerül a Laravel naplóba. Küldési garanciához később queue/outbox feldolgozást is ki lehet építeni.

## 5. Referenciaképek

A képek a `storage/app/public/portfolio` mappába kerülnek; `php artisan storage:link` után a `/storage/...` címen jelenhetnek meg. JPEG, PNG, WebP, maximum 5 MB. Az új projektek alapból **nem publikáltak**, és demónak jelöltek. A demókat ne mutasd be valódi ügyfélreferenciaként.

## 6. Automatizált tesztek

```bash
php artisan test
```

A tesztek SQLite memóriabeli adatbázist használnak (a PHP `pdo_sqlite` bővítménye szükséges); a képfeltöltési teszthez a képfájlok validálását támogató PHP-környezet szükséges. **A csomagot ebben a környezetben PHP szintaxis-ellenőrzésnek vetettem alá, de a teljes tesztfuttatást nem tudtam elvégezni Composer/vendor és SQLite PHP-bővítmény hiányában.** A saját gépeden futtasd le a `php artisan test` parancsot telepítés után.

## 7. Élesítés előtt kötelező ellenőrzések

- HTTPS, erős adminjelszó, `APP_DEBUG=false`, korrekt domain/cookie/CORS beállítások.
- Saját domainről érkező SMTP és e-mail kézbesítési próba.
- Közzétett adatkezelési tájékoztató, GDPR szerinti adatmegőrzési / törlési folyamat.
- CAPTCHA vagy további botvédelem; a honeypot és rate limit önmagukban nem állítják meg az összes spamet.
- Valós képeket és ügyfélreferenciákat csak jogos felhasználással publikálj.
- Fájlok/tárhely feltöltési jogosultságok, adatbázismentés, naplózás, restore próba.
- Angular admin és ajánlatkérő integrációteszt.

A teljes útvonallista és JSON-mezők: **[API.md](API.md)**.
