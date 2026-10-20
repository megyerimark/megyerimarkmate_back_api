# Angular integráció – rövid útmutató

Az Angular frontend forrásai **nem részei** ennek a backend ZIP-nek. A következő API-végpontokat használd a saját Angular projektedből.

## Nyilvános oldalak

- `GET /api/site-settings` – márkanév, Hero-szöveg, kapcsolati információk.
- `GET /api/services` – csak aktív szolgáltatások.
- `GET /api/projects` – csak publikált portfólióprojektek.
- `GET /api/projects/{slug}` – egy referencia részlete.
- `POST /api/quote-requests` – ajánlatkérő.

A quote-formot a `README.md` és `API.md` mezői alapján illeszd a három-/négylépéses konfigurátorhoz. Javasolt mezőnevek: `customer_name`, `email`, `phone`, `website_type`, `design_style`, `colors`, `features`, `inspiration_urls`, `budget_range`, `desired_deadline`, `project_description`, `wants_maintenance`, `privacy_notice_acknowledged`. Hibánál kezelni kell a `422` mezőhibákat és `429` rate-limit választ. **Fontos:** mivel az API Sanctum `statefulApi()` middleware-t használ, Angularból a nyilvános ajánlat beküldése előtt is hívd meg a `GET /sanctum/csrf-cookie` útvonalat, és küldd a `X-XSRF-TOKEN` fejlécet a POST kérésben, máskülönben `419` CSRF-hibát kaphatsz.

## Belépés, admin

**Ajánlott helyi megoldás:** Angular proxy a Laravel felé, hogy relatív URL-eket használhass a beépített XSRF interceptorral. Példa `proxy.conf.json` az Angular projekt gyökerében:

```json
{
  "/api": { "target": "http://localhost:8000", "secure": false, "changeOrigin": false },
  "/admin": { "target": "http://localhost:8000", "secure": false, "changeOrigin": false },
  "/sanctum": { "target": "http://localhost:8000", "secure": false, "changeOrigin": false }
}
```

Indítás: `ng serve --proxy-config proxy.conf.json` (vagy add hozzá a konfigurációt az `angular.json` fájlhoz). SPA fejlesztés közben böngészőben `http://localhost:4200` legyen a forrás és az API-kérések is `/api/...` relatív URL-ekre menjenek. Élesben az API-t/Angular alkalmazást lehetőség szerint ugyanazon eredetről szolgáld ki reverse proxy-val, más esetben teszteld a saját host/cookie/CORS beállításaidat.

A beépített XSRF-kezelés beállítására Angularban (a projekt verziójának megfelelően):

```typescript
provideHttpClient(withXsrfConfiguration({
  cookieName: 'XSRF-TOKEN',
  headerName: 'X-XSRF-TOKEN'
}))
```

SPA-login előtt hívj `GET /sanctum/csrf-cookie` kérést. Utána `POST /admin/login` JSON-ben e-mail/jelszó, minden hitelesített kérésnél a sütiket továbbítani kell. Bejelentkezés után ellenőrizd a `GET /api/admin/me` végpontot. Az `/api/admin/*` összes többi végpontját backend-oldali jogosultság védi, az Angular route guard csak UX.

## Admin képfeltöltés

`POST /api/admin/projects`: `FormData`, benne például `cover_image` (JPEG/PNG/WebP, max. 5MB), `title`, `summary`, `is_demo`, `is_published`. Frissítéskor használhatsz `POST /api/admin/projects/{id}` kérést `_method=PATCH` formmezővel, így a PHP minden feltöltött fájlt fogad. Az API válaszban `cover_image_url` jelenik meg.

A `README.md` tartalmazza a futtatás, SMTP és élesítés követelményeit; az `API.md` felsorolja a teljes útvonalszerződést.
