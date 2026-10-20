# REST API – Megyeri Márk | Webfejlesztés

Alap URL helyi környezetben: `http://localhost:8000`.

## Nyilvános végpontok

| Metódus | Útvonal | Funkció |
|---|---|---|
| GET | `/api/services` | Csak aktív szolgáltatások |
| GET | `/api/projects` | Csak publikált referenciák |
| GET | `/api/projects/{slug}` | Egy publikált referencia |
| GET | `/api/site-settings` | Főoldal/kapcsolat publikus tartalmai |
| POST | `/api/quote-requests` | Ajánlatbeküldés (5/perc/IP) |

### Ajánlatkérés POST mezők

| Mező | Típus | Kötelező |
|---|---|---|
| `customer_name` | string (max 120) | igen |
| `email` | e-mail | igen |
| `phone`, `company_name` | string | nem |
| `website_type` | `landing`, `business`, `webshop`, `custom` | igen |
| `design_style` | `dark-premium`, `elegant-minimal`, `modern-creative`, `mixed`, `undecided` | nem |
| `colors` | string[] (max 6) | nem |
| `features` | string[] (max 15) | nem |
| `inspiration_urls` | http(s) URL[] (max 5) | nem |
| `budget_range` | string (max 60) | nem |
| `desired_deadline` | `YYYY-MM-DD`, ma vagy későbbi dátum | nem |
| `project_description` | string (10–5000) | igen |
| `wants_maintenance` | boolean | nem |
| `privacy_notice_acknowledged` | `true` | igen |
| `website` | üres string (honeypot) | nem |

Válasz `201`: `{ "message": "...", "reference": 1 }`. Hibás adatok `422`, túl sok kérés `429`. **Sanctum stateful Angular SPA-ból** a nyilvános ajánlat beküldése előtt is szükséges a CSRF-cookie inicializálása, különben `419` érkezhet.

## Hitelesítés

| Metódus | Útvonal | Funkció |
|---|---|---|
| GET | `/sanctum/csrf-cookie` | CSRF-cookie inicializálás |
| POST | `/admin/login` | Saját admin bejelentkezés (`email`, `password`) |
| POST | `/admin/logout` | Admin kijelentkezés |
| GET | `/api/admin/me` | Bejelentkezett admin profil |
| PUT | `/api/admin/password` | Jelszócsere (`current_password`, `password`, `password_confirmation`) |

A védett végpontokhoz Sanctum/session-cookie és `is_admin=true` kell. API tokenes admin-hozzáférést a rendszer nem bocsát ki.

## Védett admin API

| Metódus | Útvonal | Funkció |
|---|---|---|
| GET | `/api/admin/dashboard` | Összesítő számlálók és legutóbbi ajánlatok |
| GET | `/api/admin/clients?q=&per_page=` | Ügyfelek az ajánlatokból aggregálva |
| GET | `/api/admin/clients/quotes?email=...` | Kiválasztott ügyfél ajánlatai |
| GET | `/api/admin/activities` | Admin tevékenységnapló |
| GET | `/api/admin/quote-requests?status=&q=&per_page=` | Lapozott ajánlatlista |
| GET | `/api/admin/quote-requests/{id}` | Ajánlat részlete |
| PATCH | `/api/admin/quote-requests/{id}` | `status`, `admin_notes`, `quoted_price_huf` |
| DELETE | `/api/admin/quote-requests/{id}` | Ajánlat végleges törlése |
| GET | `/api/admin/services` | Összes szolgáltatás |
| POST | `/api/admin/services` | Új szolgáltatás |
| GET | `/api/admin/services/{id}` | Szolgáltatás részlete |
| PUT/PATCH | `/api/admin/services/{id}` | Szolgáltatás frissítése |
| DELETE | `/api/admin/services/{id}` | Szolgáltatás törlése |
| GET | `/api/admin/projects` | Összes referencia |
| POST | `/api/admin/projects` | Új referencia |
| GET | `/api/admin/projects/{id}` | Referencia részlete |
| PUT/PATCH | `/api/admin/projects/{id}` | Referencia frissítése |
| DELETE | `/api/admin/projects/{id}` | Referencia törlése |
| GET | `/api/admin/site-settings` | Site szövegek/kapcsolat |
| PATCH | `/api/admin/site-settings` | Site szövegek/kapcsolat módosítása |

Státuszok: `new`, `reviewing`, `quoted`, `accepted`, `rejected`, `completed`. Az árajánlat összege **HUF egész szám**, nem euró vagy lebegőpontos érték. Státusz `quoted`-ra váltásakor az első `quoted_at` időpont rögzül. Az adatkezelés végleges törlése külön admin DELETE művelet.

### Szolgáltatás JSON

```json
{
  "title":"Céges weboldal",
  "summary":"Modern vállalkozói megjelenés",
  "description":"Opcionális részletek",
  "features":["Reszponzív", "Kapcsolatfelvétel"],
  "starting_price_huf":199000,
  "is_active":true,
  "sort_order":1
}
```

A `slug` automatikusan, szerveroldalon készül, a létrehozáskor a `title` és `summary` kötelező. Első létrehozáskor a szolgáltatás alapból inaktív.

### Referencia (multipart/form-data vagy JSON kép nélkül)

`title` (kötelező), `summary` (kötelező), `category`, `description`, `technologies[]`, `project_url`, `is_demo`, `is_published`, `is_featured`, `sort_order`, opcionális `cover_image` (JPEG/PNG/WebP, max 5 MB). Cserekor `remove_cover_image=true` törli a képet. A Laravel `PATCH` multipart beküldéshez böngészőből használható `POST` + `_method=PATCH`.

### Beállítások

Publikus értékek és admin PATCH kulcsok: `site_title`, `hero_headline`, `hero_description`, `contact_email`, `contact_phone`, `instagram_url`, `linkedin_url`, `github_url`.

## Biztonság és integráció

- Az API **nem** ad ki adminkapcsolatot, e-mail-értesítési kulcsokat, SMTP-jelszót vagy nem publikált projektet nyilvánosan.
- Angular SPA: először CSRF-cookie, majd login. HTTP-kérések hitelesítési cookie-val, XSRF fejléccel, és az érintett URL-ekhez tartozó megfelelő origin beállításokkal.
- A belépés és az ajánlatküldés rate-limited, de éles forgalomhoz további botvédelem és monitorozás szükséges.
- Az `admin_activities` csak `user_id`, eseménynév és rekord ID mezőket naplóz, nem menti el a jelszót, ügyfélleírást vagy adminmegjegyzést.
