# v20 – biztonsági frissítés (backend + frontend)

## Élesítés (sorrendben)

1. **Backend** feltöltése, majd a szerveren: `bash deploy.sh` (2 új migráció fut le).
2. **Mentéskulcs** a szerveren: `php artisan portfolio:backup-key` → a kiírt `PORTFOLIO_BACKUP_KEY=...` sort írd be a `.env`-be, **és mentsd el a jelszókezelődbe is**. Kulcs nélkül egy titkosított mentés nem állítható vissza!
3. **Frontend** helyben: `npm ci && npm run build`, majd a `dist/.../browser` tartalmát töltsd fel (az új `.htaccess`-szel együtt).
4. Admin → Fiók és biztonság → **Hitelesítő alkalmazás** → beállítás. A 10 helyreállítási kódot mentsd el.
5. Admin → Rendszerállapot: ellenőrizd, hogy mindenhol v20 van, és a „Titkosított mentés” zöld.
6. A böngésző konzoljában (F12) ellenőrizd, hogy nincs-e CSP-hiba a főoldalon, a véleményeknél (Trustindex) és az adminban.

Opcionális: külső mentés S3-kompatibilis tárhelyre (Backblaze B2 / Cloudflare R2), lásd README 7. pont.

## Mi változott

| Terület | Javítás |
|---|---|
| Stripe előleg | A nyitott fizetési oldal újrahasznosul (több fül vagy újratöltés sem nyit új fizetést). A létrehozás zárral és `Idempotency-Key`-jel fut. Ha az összeg változik, a régi oldal lezárul. Átutalásos jóváíráskor a kártyás oldal lezárul. A webhook sorzárolással dolgozik. Egy mégis befutó második fizetés „visszatérítendő” tételként kerül a Pénzügyekbe, és e-mailt küld. |
| 2FA | Hitelesítő alkalmazás (TOTP, RFC 6238) és 10 egyszer használatos helyreállítási kód. Bekapcsolva nincs e-mailes kód, így egy feltört e-mail-fiók nem elég az admin átvételéhez, és SMTP nélkül is be lehet lépni. A titok titkosítva tárolódik, a kód nem játszható vissza. |
| Mentések | libsodium titkosítás (`.tar.gz.enc`), `portfolio:backup-key` és `portfolio:backup-decrypt` parancs, opcionális külső feltöltés (csak titkosítva), rotációval. Mindkettő megjelenik a Rendszerállapotban. |
| CSP | A frontend `.htaccess`-ben a Report-Only helyett kényszerített CSP. |
| Hibaüzenet | A „futtasd: php artisan migrate” teendőt csak az admin látja, a nyilvános látogató általános üzenetet kap. |
| CSV | A hírlevél-export is védett az Excel képlet-injektálás ellen (közös `Csv::safe`). |
| Kód | `Mailer::canSend()` (13 ismétlés helyett) és `ClientAccess::quote()` (3 ismétlés helyett). |
| Elfogadás → fizetés | Az elfogadás válasza most `payment_status`-t is küld (`redirect` / `failed` / `transfer_only` / `none`). Ha a fizetési oldal nem nyílt meg, az ügyféloldal egyszer magától újrapróbálja, és ha ez sem sikerül, látható hibaüzenetet ír ki. A Stripe utolsó hibája (üzenet + hibás mező) megjelenik az Admin → Rendszerállapot „Stripe” sorában, így nem kell a `laravel.log`-ban keresni. |
| Rendszerállapot | Javítva: a cron-ellenőrzés eddig mindig „rendben”-t mutatott, ha volt életjel az elmúlt napban (Carbon 3 előjeles különbség). |
| CI | Backend GitHub Actions (`.github/workflows/ci.yml`): PHP 8.4, tesztek, `composer audit`. Ha a repódban már van ilyen fájl (a `.gitattributes` kizárja a zipből, ezért nem látszott), vesd össze a kettőt. |
| Frontend | TOTP beállítás és belépés, kb. 12 MB nem használt kép törölve a `public/demos` alól (az eredetik a `design-sources` mappában vannak). |

Tesztek: 103/103 (19 új: dupla fizetés, elfogadás utáni fizetés, TOTP, titkosított/külső mentés).
