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

## 0. Frissítés élesben (minden új verziónál)

A szerveren, a backend mappájában (PHP 8.4 kell):

```bash
bash deploy.sh
```

A szkript karbantartási módba teszi az oldalt, telepíti a függőségeket, mentést készít, **lefuttatja a migrációkat**, újraépíti a gyorsítótárat, majd visszakapcsolja az oldalt. Ha a Pénzügyek vagy a Kész projektek fül hibát ír, szinte mindig a migráció vagy a `php artisan optimize:clear` maradt el.

Ellenőrzés: Admin → Fiók és biztonság → **Rendszerállapot** (`GET /api/admin/system-status`). Itt látszik a backend verziója, a hiányzó migrációk/táblák, a debug mód, HTTPS, e-mail, 2FA, Stripe, cron és mentés állapota.

### Gyorsaság élesben (fontos!)

A `.env`-ben legyen **`CACHE_STORE=file`**. `database` esetén minden kérés kéréskorlátja és a gyorsítótár is a (Rackhoston távoli) MySQL-t terheli – ez lassítja az oldalt. A Rendszerállapot panel figyelmeztet, ha még `database`. Megosztott tárhelyen (Rackhost) **ne** futtass `php artisan optimize` / `config:cache` parancsot: az SSH más útvonalon látja a projektet, mint a webszerver (a `deploy.sh` ezért csak az útvonalakat gyorsítótárazza).

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

### Projektlezárás és kódátadás

1. **Projekt kész** (admin → ajánlat → „Projektlezárás és kódátadás”): az ügyfél végleges e-mailt kap a fennmaradó összeggel (ajánlati ár − befizetett előleg) és a fizetési linkkel. Csak elfogadott projekt zárható le, és csak ha az előleg már beérkezett. Sikertelen e-mail-küldésnél a projekt állapota nem változik. A „Lezárva” státusz a sima státuszmentéssel nem állítható be.
2. **Fennmaradó összeg**: az ügyfél a projektoldalon bankkártyával fizetheti (Stripe Checkout, `metadata.type = final`), vagy átutalás után az admin jelöli „Átutalás beérkezett”-nek. Mindkettő bevételként rögzül a Pénzügyekben („Végszámla”).
3. **Kódátadás**: az „Átadandó kód / csomag” jelölésű feltöltések (ZIP vagy PDF, `PORTFOLIO_DELIVERABLE_MAX_MB`) az ügyfélnek zárolva látszanak. A „Kód átadása” gomb (`POST /api/admin/quote-requests/{id}/handover`) **szerveroldalon is zárolt**, amíg a fennmaradó összeg nincs kifizetve. Átadáskor az ügyfél e-mailt kap, és innentől letöltheti a csomagot.

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
- Az értesítő e-mailek (új ajánlatkérés, ügyfél-visszaigazolás, konzultációfoglalás, ajánlat-elfogadás) a **válasz elküldése után** mennek ki (`defer()`), így a látogatónak nem kell az SMTP-re várnia, és nincs szükség külön queue workerre. Sikertelen küldésnél a már eltárolt adat megmarad, a hiba típusa bekerül a naplóba.
- Az ajánlatküldés, a privát link cseréje, a belépési kód és a jelszó-visszaállítás szándékosan **szinkron**: az állapot csak sikeres kézbesítés után változik.

## 5. Referenciaképek

A képek a `storage/app/public/portfolio` mappába kerülnek; `php artisan storage:link` után a `/storage/...` címen jelenhetnek meg. JPEG, PNG, WebP, maximum 5 MB. Az új projektek alapból **nem publikáltak**, és demónak jelöltek. A demókat ne mutasd be valódi ügyfélreferenciaként.

## 6. Automatizált tesztek

```bash
php artisan test
```

A tesztek SQLite memóriabeli adatbázist használnak (a PHP `pdo_sqlite` bővítménye szükséges); a képfeltöltési teszthez a képfájlok validálását támogató PHP-környezet szükséges. A `SecurityHardeningTest` a kötelező 2FA-t, a fiókonkénti belépéskorlátot, a biztonsági fejléceket, az adatmegőrzési törlést, az oldaltérképet és a mentést ellenőrzi. A GitHub Actions (`.github/workflows/ci.yml`) minden push után lefuttatja a teszteket és a `composer audit` sérülékenység-ellenőrzést.

**PHP-verzió:** a `composer.lock` Symfony 8.1-et rögzít, ezért **PHP 8.4 szükséges** (a tárhelyen is!).

## 7. Biztonság és üzemeltetés

**Hitelesítő alkalmazás (ajánlott).** Admin → Fiók és biztonság → *Hitelesítő alkalmazás*. Bekapcsolva a belépéshez nem e-mailes kód kell, hanem a telefonos app (Google/Microsoft Authenticator, 1Password, Bitwarden) 6 jegyű kódja; ha nincs nálad a telefon, a 10 egyszer használatos **helyreállítási kód** egyike. Így egy feltört e-mail-fiókkal (jelszó-visszaállítás + e-mailes kód) sem vehető át az admin, és a belépés SMTP-hiba esetén is működik. A titok titkosítva (APP_KEY) tárolódik; egy kód nem használható fel kétszer.

**Kötelező kétlépcsős belépés.** `APP_ENV=production` esetén az admin e-mailes 2FA automatikusan kötelező és nem kapcsolható ki (`PORTFOLIO_REQUIRE_ADMIN_2FA`). Ezért élesítés előtt állíts be működő SMTP-t, különben nem tudsz belépni.

**Belépési korlát.** IP-címenként 5 próbálkozás / perc **és** e-mail-címenként 20 / óra, így sok IP-címről sem lehet egy fiók jelszavát próbálgatni.

**Biztonsági fejlécek.** Az `App\Http\Middleware\SecurityHeaders` minden API-válaszhoz beállítja: CSP, HSTS (HTTPS-en), X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy. Ha a tárhely proxy mögött van, add meg a `TRUSTED_PROXIES` értékét.

**Ütemezett feladatok.** Egyetlen cron-sor kell a szerveren (cPanel → Cron Jobs):

```
* * * * * cd /útvonal/a/backendhez && php artisan schedule:run >> /dev/null 2>&1
```

Ez futtatja naponta:
- `portfolio:backup` – adatbázis + feltöltött fájlok mentése `storage/app/backups` alá, a legutóbbi `PORTFOLIO_BACKUP_KEEP` példány megtartásával. Évente legalább egyszer próbáld ki a visszaállítást.
  - **Titkosítás:** `php artisan portfolio:backup-key` → a kiírt `PORTFOLIO_BACKUP_KEY=...` sort tedd a `.env`-be **és a jelszókezelődbe is**. Ezután a mentés `backup-….tar.gz.enc` (XChaCha20-Poly1305, libsodium), titkosítatlan példány nem marad. Visszaállítás: `php artisan portfolio:backup-decrypt <fájl>.tar.gz.enc` (más gépen: `--key="base64:..."`), majd `tar -xzf`.
  - **Külső másolat:** S3-kompatibilis tárhelyre (Backblaze B2 – 10 GB ingyenes, Cloudflare R2). Egyszer helyben: `composer require league/flysystem-aws-s3-v3 "^3.0"`, majd deploy; a `.env`-ben `PORTFOLIO_BACKUP_DISK=backup_offsite` és `BACKUP_S3_*`. Csak titkosított mentés kerül fel; a külső példányokból `PORTFOLIO_BACKUP_DISK_KEEP` marad meg. A Rendszerállapot panel mutatja, sikerült-e.
- `model:prune` – GDPR: a `PORTFOLIO_RETENTION_MONTHS` (alapból 24) hónapja inaktív megkeresések, a hozzájuk tartozó fájlok és jegyzetek, valamint a régi konzultációs időpontok törlése. Elfogadott projektet és élő (aktív/szüneteltetett) karbantartási szerződést nem töröl.
- a 13 hónapnál régebbi anonim statisztikai események törlése.

**Online fizetés (Stripe).** Hivatalos SDK nélkül, közvetlen REST-hívásokkal (`App\Support\Stripe`). Előleg: az ajánlatban megadott % az elfogadás után kártyával (Checkout) vagy átutalással fizethető; a munka a befizetés után indul. **Dupla fizetés ellen:** a nyitott fizetési oldal újrahasznosul (több fül / újratöltés ugyanazt adja), a létrehozás Idempotency-Key-jel és zárral fut, átutalásos jóváíráskor a nyitott kártyás oldal lezárul. Ha mégis érkezik egy második befizetés, a Pénzügyekbe „visszatérítendő” tételként kerül, és e-mailt kapsz a visszatérítésről. Karbantartás: ha a megállapodásnál engedélyezed, az ügyfél a projektoldalán havi előfizetést indíthat, és a Stripe ügyfélportálján kezelheti (kártyacsere, lemondás). A fizetési állapot **csak** az aláírt webhookból frissül (`/api/stripe/webhook`; események: `checkout.session.completed`, `invoice.paid`, `invoice.payment_failed`, `customer.subscription.updated`, `customer.subscription.deleted`); minden befizetés automatikusan bevételként kerül a Pénzügyekbe. **A Stripe-nyugta nem számla** – a számlát a számlázóprogramodban állítsd ki.

**Szerződéskötés.** Az ajánlat elfogadása az ÁSZF-fel együtt elektronikus szerződéskötés: rögzül az ajánlat ujjlenyomata, az ÁSZF-verzió, az időpont, az IP és a böngésző, az ügyfél pedig azonnal visszaigazoló e-mailt kap (Ekertv. 6. §).

**Ügyfélüzenet-értesítés.** Ha az ügyfél ír a projektoldalon, e-mailt kapsz (projektenként legfeljebb 10 percenként egyet), az adminban pedig olvasatlan-jelzés és toast jelenik meg.

**Kész projektek + széf.** Élesített oldalak, domain-/tárhely-/SSL-lejáratok (naponta e-mail-emlékeztető 30, 14, 7, 3, 1 és 0 nappal előtte). A hozzáférési adatokat a böngésző titkosítja külön széfjelszóval (PBKDF2-SHA256 600 000 iteráció → AES-256-GCM); a szerver csak titkosított adatot lát. **Elfelejtett széfjelszó esetén a tartalom nem állítható vissza.**

**Pénzügyek.** Bevétel/kiadás tételek, havi bruttó/nettó, ÁFA (AAM vagy 27%) és becsült átalányadó (SZJA, TB, szocho) 2026-os alapértékekkel, figyelmeztetéssel az AAM- és átalányadó-határ közelében. Ez becslés, nem könyvelés.

**Oldaltérkép.** A `GET /api/sitemap.xml` a statikus oldalak mellett minden publikált referenciát (`/munkak/{slug}`) tartalmaz; a frontend `robots.txt` hivatkozik rá.

**DDoS / árasztás elleni védelem.** Az alkalmazásszintű korlátok (globális 300 kérés/perc/IP + végpontonkénti korlátok) a kisebb támadásokat és a scrapereket fogják meg. Valódi DDoS ellen a szerver elé kell egy szolgáltatás – ajánlott az ingyenes **Cloudflare**: domain DNS-ét a Cloudflare-re irányítod (narancs felhő), SSL mód „Full (strict)”, „Bot Fight Mode” bekapcsolva, támadáskor „Under Attack Mode”. Ezután állítsd be: `TRUSTED_PROXIES=cloudflare` – így a látogató IP-címét (`X-Forwarded-For`) csak a Cloudflare saját címeiről (`bootstrap/cloudflare-ips.php`) fogadjuk el, és a korlátok a valódi IP-re vonatkoznak. **Ne használd a `*` értéket**, ha a szerver Cloudflare nélkül is elérhető: akkor bárki hamis IP-címmel kerülhetné ki a korlátokat. Ha a tárhely engedi, a 80/443-as portot is szűkítsd a Cloudflare címeire.

**Bot-védelem (Cloudflare Turnstile).** Az ajánlatkérés (űrlap és kvíz), az időpontfoglalás, a hírlevél-feliratkozás, az admin belépés és az elfelejtett jelszó űrlap a honeypot és a kéréskorlát mellett Turnstile-ellenőrzést is kap (`App\Http\Middleware\VerifyTurnstile`, alias: `turnstile`). Beállítás:
1. dash.cloudflare.com → **Turnstile** → *Add widget* → domain: `megyerimarkmate.hu` (és `www.`), mód: **Managed**.
2. A `.env`-be: `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY`, `TURNSTILE_HOSTNAMES=megyerimarkmate.hu,www.megyerimarkmate.hu`, majd `bash deploy.sh` (vagy `php artisan optimize:clear`). Megosztott tárhelyen (Rackhost) **ne** futtass `config:cache`-t – lásd fent.
3. A frontend a nyilvános kulcsot a `/api/site-settings` válaszból kapja – ott nincs külön teendő.

Üres titkos kulccsal a védelem ki van kapcsolva (helyi fejlesztés, tesztek). Bekapcsolva a szerver minden érintett kérésnél ellenőrzi a kulcsot a Cloudflare-nél; ha a Cloudflare nem elérhető, a kérést elutasítja (zárt hibakezelés). Más domainen kiállított kulcs nem fogadható el. Helyi kipróbáláshoz a Cloudflare tesztkulcsai: site `1x00000000000000000000AA`, secret `1x0000000000000000000000000000000AA`.

**Időpontfoglalás korlátja.** Egy e-mail-cím legfeljebb 2 jövőbeli konzultációs időpontot foglalhat le, így egy bot nem töltheti meg a naptárat.

**Figyelés.** Javasolt egy ingyenes uptime-figyelő (pl. UptimeRobot) a `/up` címre, valamint hibafigyelő szolgáltatás (pl. Sentry) bekötése.

## 8. Élesítés előtt kötelező ellenőrzések

Kiinduló beállítások: **[.env.production.example](.env.production.example)**.


- HTTPS, erős adminjelszó, `APP_DEBUG=false`, korrekt domain/cookie/CORS beállítások.
- Saját domainről érkező SMTP és e-mail kézbesítési próba.
- Közzétett adatkezelési tájékoztató, GDPR szerinti adatmegőrzési / törlési folyamat.
- Cloudflare Turnstile kulcsok beállítva (`TURNSTILE_*`), Cloudflare mögött `TRUSTED_PROXIES=cloudflare`.
- SPF, DKIM és DMARC rekord a levelező domainhez (különben a visszaigazolók és a belépési kódok spambe kerülnek).
- Valós képeket és ügyfélreferenciákat csak jogos felhasználással publikálj.
- Fájlok/tárhely feltöltési jogosultságok, cron-sor beállítva, első mentés és restore próba.
- Angular admin és ajánlatkérő integrációteszt.

A teljes útvonallista és JSON-mezők: **[API.md](API.md)**.
