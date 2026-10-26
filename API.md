# API-referencia

Minden válasz JSON (kivéve a letöltéseket, a CSV-exportot és a `sitemap.xml`-t). A sikeres válaszok általában `{ "data": ... }` burkolatban érkeznek, a validációs hibák `422` státusszal és Laravel-formátumú `errors` objektummal.

**Hitelesítés:** az admin a Sanctum SPA-módját használja (munkamenet-süti + `XSRF-TOKEN`). Írási kérés előtt egyszer le kell kérni a `GET /sanctum/csrf-cookie` címet, és a kéréseket `withCredentials`-szel, `X-XSRF-TOKEN` fejléccel kell küldeni. Az Angular `HttpClient` ezt automatikusan intézi.

**Kéréskorlátozás:** a túllépés `429`-es választ ad.

## Nyilvános végpontok

| Metódus | Útvonal | Leírás | Korlát |
|---|---|---|---|
| GET | `/api/services` | Aktív szolgáltatások | 120/perc |
| GET | `/api/projects` | Publikált, nem demó referenciák | 120/perc |
| GET | `/api/projects/{slug}` | Egy publikált referencia | 120/perc |
| GET | `/api/site-settings` | Nyilvános oldalbeállítások | 120/perc |
| GET | `/api/testimonials` | Publikált ügyfélvélemények (max. 12) | 120/perc |
| GET | `/api/sitemap.xml` | Dinamikus oldaltérkép (statikus oldalak + referenciák) | 60/perc |
| POST | `/api/events` | Anonim eseményszámlálás (`page_view`, `cta_click`, …); süti és IP nélkül | 120/perc |
| POST | `/api/quote-requests` | Ajánlatkérés beküldése → `201 { reference }` | 5/perc |
| GET | `/api/consultations/available` | Szabad konzultációs időpontok (2 óra – 60 nap) | 40/perc |
| POST | `/api/consultations/book` | Időpontfoglalás | 5/perc |

## Ügyfélportál (privát link)

A privát kulcs (64 hex karakter) **mindig a kérés törzsében** (`token`) utazik, sosem az URL-ben. A link lejár (`PORTFOLIO_CLIENT_ACCESS_DAYS`), és az admin lecserélheti.

| Metódus | Útvonal | Leírás | Korlát |
|---|---|---|---|
| POST | `/api/client/project` | Projekt / ajánlat adatai | 20/perc |
| POST | `/api/client/project/accept` | Ajánlat elfogadása (`accept_terms: true`), egyszeri | 5/perc |
| POST | `/api/client/project/collaboration` | Üzenetek és dokumentumlista | 20/perc |
| POST | `/api/client/project/messages` | Üzenet küldése (`body`, 2–3000 karakter) | 5/perc |
| POST | `/api/client/project/documents/{id}/download` | Dokumentum letöltése | 30/perc |

## Admin-hitelesítés (`routes/web.php`)

| Metódus | Útvonal | Leírás | Korlát |
|---|---|---|---|
| POST | `/admin/login` | Belépés. `200` = belépve; `202 { requires_two_factor: true }` = kód elküldve | 5/perc/IP és 20/óra/e-mail |
| POST | `/admin/verify-login` | 6 jegyű e-mailes kód ellenőrzése | 5/perc |
| POST | `/admin/resend-login-code` | Új kód kérése | 2/perc |
| POST | `/admin/logout` | Kijelentkezés | – |
| POST | `/admin/forgot-password` | Visszaállító link kérése (a válasz nem árulja el, létezik-e a fiók) | 3/perc/IP és 5/óra/e-mail |
| POST | `/admin/reset-password` | Új jelszó beállítása | 5/perc |

Éles környezetben (`PORTFOLIO_REQUIRE_ADMIN_2FA=true`) a második faktor kötelező: amíg nincs ellenőrizve, minden `/api/admin/*` hívás `403`-at ad.

## Admin API (`/api/admin/*`)

Mindegyikhez szükséges: bejelentkezett admin (`auth:sanctum`, `can:access-admin`) és ellenőrzött 2FA (`admin.2fa`).

| Terület | Végpontok |
|---|---|
| Fiók és biztonság | `GET me` · `PUT password` · `GET security` (`two_factor_email_enabled`, `two_factor_required`) · `PUT security/two-factor` · `GET security/sessions` · `DELETE security/sessions/others` |
| Áttekintés | `GET dashboard` · `GET growth` · `GET activities` · `GET quote-board` |
| Ajánlatkérések | `GET quote-requests` (`q`, `status`, `per_page`) · `GET/PATCH/DELETE quote-requests/{id}` · `POST quote-requests/{id}/send-proposal` · `POST …/rotate-client-access` · `POST …/contacted` · `GET exports/quote-requests` (CSV) |
| Együttműködés | `GET quote-requests/{id}/collaboration` · `POST …/messages` · `POST …/documents` (PDF/JPG/PNG/WebP, max. 10 MB) · `GET …/documents/{doc}/download` · `DELETE …/documents/{doc}` |
| Mérföldkövek | `GET/POST quote-requests/{id}/milestones` · `PATCH/DELETE …/milestones/{m}` |
| Munkaterület | `GET workspace/projects` · `GET workspace/communications` · `GET/POST workspace/tasks` · `PATCH/DELETE workspace/tasks/{id}` · `GET/POST workspace/client-notes` · `DELETE workspace/client-notes/{id}` |
| Ügyfelek | `GET clients` · `GET clients/quotes?email=` |
| Konzultáció | `GET/POST consultations` · `DELETE consultations/{slot}` |
| Karbantartás | `GET/POST maintenance-agreements` · `PATCH maintenance-agreements/{id}` |
| Tartalom | `services`, `projects`, `testimonials` (REST erőforrások) · `GET/PATCH site-settings` |

**Ajánlatkérés állapotai:** `new → reviewing → quoted → accepted → completed`, illetve `rejected`. A `quoted` állapotot csak a `send-proposal` állíthatja be (sikeres e-mail után), az `accepted` állapotot csak az ügyfél. Elküldött ajánlat tartalma nem módosítható csendben.

## Ütemezett feladatok

Lásd a README „Biztonság és üzemeltetés” részét: `portfolio:backup`, `model:prune`, eseménytörlés.
