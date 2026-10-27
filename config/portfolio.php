<?php

return [
    'admin_email' => env('PORTFOLIO_ADMIN_EMAIL'),
    'frontend_url' => rtrim(env('PORTFOLIO_FRONTEND_URL', env('APP_URL', 'http://localhost:4200')), '/'),
    'mail_enabled' => filter_var(env('PORTFOLIO_MAIL_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'customer_receipt_enabled' => filter_var(env('PORTFOLIO_CUSTOMER_RECEIPT_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    'client_access_days' => max(1, (int) env('PORTFOLIO_CLIENT_ACCESS_DAYS', 90)),

    // Globális API-kéréskorlát IP-címenként és percenként (DDoS / scraper elleni alapvédelem).
    'api_rate_per_minute' => max(30, (int) env('PORTFOLIO_API_RATE_PER_MINUTE', 300)),

    // Kötelező előleg: ajánlat csak legalább ennyi %-os előleggel küldhető ki (0 = nem kötelező).
    'min_deposit_percent' => min(100, max(0, (int) env('PORTFOLIO_MIN_DEPOSIT_PERCENT', 30))),

    // Az Általános Szerződési Feltételek aktuális verziója – az elfogadáskor rögzítjük. ÁSZF-módosításkor növeld!
    'terms_version' => env('PORTFOLIO_TERMS_VERSION', '2026-11'),

    // Éles környezetben az admin kétlépcsős belépése kötelező, és nem kapcsolható ki.
    'require_admin_2fa' => filter_var(
        env('PORTFOLIO_REQUIRE_ADMIN_2FA', env('APP_ENV') === 'production'),
        FILTER_VALIDATE_BOOLEAN,
    ),

    // GDPR – korlátozott tárolhatóság: a lezárt / inaktív megkeresések ennyi hónap után törlődnek.
    // 0 = automatikus törlés kikapcsolva.
    'retention_months' => max(0, (int) env('PORTFOLIO_RETENTION_MONTHS', 24)),

    // Napi mentés (php artisan portfolio:backup). A mentéseket rendszeresen töltsd le a szerverről is!
    'backup_path' => env('PORTFOLIO_BACKUP_PATH', storage_path('app/backups')),
    'backup_keep' => max(1, (int) env('PORTFOLIO_BACKUP_KEEP', 14)),
    // Mentés titkosítása (libsodium XChaCha20-Poly1305). Kulcs: php artisan portfolio:backup-key
    // A kulcsot a szerveren KÍVÜL is őrizd meg (jelszókezelő) – nélküle a mentés nem állítható vissza!
    'backup_key' => env('PORTFOLIO_BACKUP_KEY'),
    // Külső (szerveren kívüli) másolat: egy config/filesystems.php-ban megadott disk neve, pl. „backup_offsite”.
    // Csak titkosított mentés kerül fel. Üresen hagyva csak helyi mentés készül.
    'backup_disk' => env('PORTFOLIO_BACKUP_DISK'),
    'backup_disk_keep' => max(1, (int) env('PORTFOLIO_BACKUP_DISK_KEEP', 30)),
];
