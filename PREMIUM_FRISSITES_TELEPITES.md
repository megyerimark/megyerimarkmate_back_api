# Megyeri Márk Máté – prémium ügyfélélmény (2026. október 21.)

Ez a csomag **a felhasználó által feltöltött** Angular 20 + Laravel 13 projektekre épül. A ZIP-ek teljes projektmappákat tartalmaznak. Az adatbázist, `.env` fájlt és a valódi SMTP-hozzáféréseket **nem** tartalmazzák.

## Elkészült változtatások

1. **Tervezőasztal → ajánlatkérő**: a céges weboldal / online foglalás / egyedi rendszer irány, a grafit / világos / éjkék vizuális stílus és foglalási igény továbbvitele URL-paraméterekkel, kizárólag engedélyezett értékek alapján. Az ügyfél módosíthatja a kitöltött választásokat.
2. **Valódi e-mailes ajánlatküldés**: adminoldali ajánlati összeg, részletes tartalom és érvényességi dátum. Az „Ajánlat elküldve” státusz nem állítható be pusztán státuszváltással; a külön küldés gomb csak működő e-mail-szállító konfigurációval működik. Küldési hiba esetén nem állítja elküldöttnek.
3. **Privát ügyfélfelület**: az ügyfél visszaigazoló e-mailben hosszú, véletlenszerű privát hivatkozást kaphat; követheti az ajánlat státuszát, megtekintheti a mentett ajánlatot, elfogadhatja azt és böngészőből nyomtathatja / PDF-be mentheti. A hivatkozásban levő azonosító **URL-fragmentumban** van, a backendnek a lekérdezés törzsében adjuk át. Az adatbázis hash és Laravelrel titkosított formában tárolja, az admin API-kban nem látszik.
4. **Projektmérföldkövek**: adminból felvehetők, módosítható státusszal és tervezett dátummal. Elfogadott vagy lezárt ajánlatnál az ügyfél csak a saját projektjének állomásait láthatja.
5. **Valódi konzultációfoglalás**: az admin 30/45/60 perces szabad időpontokat tesz közzé; az ügyfél foglalhat, az adatbázis és tranzakció védi a foglaltságot. A backend UTC-ben tárol, a felület Budapest időzónáját jelzi. Lefoglalt időpont nem törölhető egyszerűen az adminból. Az e-mailes visszaigazolás SMTP-konfigurációt igényel.
6. **Kezdeti keresőbarát fejlesztések**: útvonalankénti meta leírás, canonical, Open Graph, sitemap, robots.txt és privát / admin oldalak noindex jelzése. Egy SPA esetén az SSR/prerender később javíthat a feltérképezésen.
7. **Adatkezelési tervezet kibővítése** a konzultációfoglalásra és az ajánlatkövetésre. **A tájékoztató továbbra is tervezet**, éles adatgyűjtés előtt tényleges adatokkal és jogi ellenőrzéssel véglegesítendő.

### A demókkal kapcsolatos döntés

A demómunkák **nem kerültek ki a főoldalra, a portfólióba vagy a navigációba**. A feltöltött projekt `/public/demos` fájljait érintetlenül megőriztem. Ezek az állományok az eredeti struktúrából közvetlen URL-en elérhetővé válhatnak, ha az egész `public` mappát feltöltöd a tárhelyre. Ha az a cél, hogy **senki se érhesse el a demókat**, az éles Angular build elkészítése előtt hagyd ki / távolítsd el a `public/demos` mappát a telepítésből. A `robots.txt` tiltás önmagában **nem** hozzáférésvédelem.

## Biztonságos telepítés meglévő projektre

**Először legyen biztonsági mentés az Angular és Laravel mappákról, valamint az adatbázisról!**

1. A `megyerim_ang-main-premium.zip` fájlt csomagold ki külön helyre, majd **az Angular projekted megfelelő fájljait** frissítsd vele. A helyi `node_modules` mappa maradhat; vagy újratelepítheted az npm csomagokat.
2. A `megyerimarkmate_back_api-main-premium.zip` fájlt csomagold ki külön, és másold a megfelelő módosított fájlokat a Laravel projektedbe. **A meglévő `.env`-et, `storage` feltöltéseket és `vendor`-t ne töröld.**
3. Az **eredeti** Laravel projekt mappájában futtasd:

```bash
composer install
php artisan optimize:clear
php artisan migrate
php artisan route:list --path=api
php artisan test
php artisan serve --host=localhost --port=8000
```

4. Az Angular mappában, másik terminálban:

```bash
npm ci
npm run build
npm start
```

5. Böngésző: `http://localhost:4200`. A meglévő `proxy.conf.json` a Laravel szerverhez irányítja a kéréseket. Helyben **mindkét oldalt localhost néven** használd a CSRF/cookie-konfiguráció miatt.

**Soha ne futtasd `php artisan migrate:fresh` parancsot éles, adatokat tartalmazó adatbázison!** Ne generálj új `APP_KEY`-t a már működő projektben: a hozzáférési tokenek titkosítása az alkalmazás kulcsát használja.

## E-mail és domain beállítása

A Laravel `.env` fájlban a már meglévő értékek mellé:

```dotenv
# helyi fejlesztés
PORTFOLIO_FRONTEND_URL=http://localhost:4200
PORTFOLIO_ADMIN_EMAIL=sajat-valodi-email@example.com
PORTFOLIO_MAIL_ENABLED=false
PORTFOLIO_CUSTOMER_RECEIPT_ENABLED=true
MAIL_MAILER=log
```

Éles SMTP beállítása után (valós szolgáltató adataival):

```dotenv
PORTFOLIO_FRONTEND_URL=https://megyerimarkmate.hu
PORTFOLIO_MAIL_ENABLED=true
MAIL_MAILER=smtp
MAIL_HOST=valodi-smtp-szerver
MAIL_PORT=587
MAIL_USERNAME=valodi-felhasznalo
MAIL_PASSWORD=valodi-titkos-jelszo
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=valodi-felado@megyerimarkmate.hu
MAIL_FROM_NAME="Megyeri Márk Máté | Webfejlesztés"
```

Az SMTP-adatokat ne küldd el chatben, és ne commitold Gitbe. A Laravel-verzióhoz illő mail transport opcióit a szolgáltató dokumentációjával ellenőrizd. A `PORTFOLIO_FRONTEND_URL`-nek a **tényleges éles domainre** kell mutatnia; a sablon a korábban használt `megyerimarkmate.hu` domaint feltételezi. Módosítás után `php artisan config:clear`.

**Fontos:** `MAIL_MAILER=log` mellett a program nem jelöli elküldöttnek az ajánlatot, és nem küld titkos ügyféllinket a logmailerre. SMTP-üzenet elfogadása nem jelent garantált kézbesítést; éles környezetben SPF/DKIM/DMARC, kézbesítési teszt és hibakezelés szükséges.

## Az admin és ügyfélfolyamat kipróbálása

1. A főoldali tervezőasztalon válaszd az **Időpontfoglalás** és **Éjkék** kombinációt, majd kattints az „Ezzel a tervvel kérek ajánlatot” linkre. Az ajánlatkérőben a céges weboldal, kreatív stílus és időpontfoglalás funkciónak meg kell jelennie.
2. Küldj teszt ajánlatkérést. Az adminban találod a beérkezett adatokat; aktív SMTP és visszaigazolás mellett az ügyfél privát követőlinket kap e-mailben.
3. Az adminban írd be és **mentsd el** az ajánlat összegét, részletes tartalmát és érvényességét. Csak ezután kattints az **„Ajánlat tényleges elküldése e-mailben”** gombra. Ellenőrizd a valódi postaládában a kézbesítést.
4. Az ügyfél a privát linken megtekinti a tartalmat, és kifejezett megerősítéssel elfogadhatja. Az admin ezután mérföldköveket rögzíthet; azok csak a megfelelő ügyfél felületén jelennek meg.
5. Az admin **Konzultációk** menüjében vegyél fel időpontot. Nyisd meg a `/konzultacio` oldalt, foglald le. A foglalt slot többé nem jelenhet meg szabadként, és az adminban a megfelelő ügyfélhez tartozik.
6. A privát felületet próbáld ki hibás tokennel, jelszó nélküli admin API hívással, mobilon és mindkét témában is.

## Még szükséges élesítés előtt

- Teljes Angular build, Laravel PHPUnit tesztek és tényleges böngészős végponttól végpontig tartó tesztek.
- **Adatvédelmi tájékoztató véglegesítése**, megőrzési és törlési szabályok, valós adatkezelői adatok.
- HTTPS, helyes Sanctum/CORS/cookie beállítások és DNS/domain konfiguráció.
- E-mail-kézbesítés, anti-spam és biztonsági felülvizsgálat, backup/restore próba.
- Az ajánlat elfogadásának szerződéses/jogi szövegét a tényleges vállalási feltételeidhez igazítani.
- Production monitoring, e-mail hibák kezelése, tartós e-mail-küldési sor (outbox/queue) és teljesítménymérés a tényleges szerveren.

## A csomagon elvégzett ellenőrzések

- **PHP**: összes módosított és meglévő `.php` állomány `php -l` szintaxisellenőrzése, hiba nélkül.
- **TypeScript**: minden frontend `.ts` állomány TypeScript parser alapú szintaxisellenőrzése, hiba nélkül.
- **PHPUnit és Angular build**: a jelenlegi végrehajtási környezetben **nem futtatható**, mert a feltöltött projektekben nincs Composer `vendor` / npm `node_modules`, és az npm registry hálózati kapcsolata nem érhető el. A fent felsorolt parancsokat a saját gépeden feltétlenül futtasd le. Ezért a csomag **fejlesztői implementáció, nem készre tesztelt éles kiadás**.
