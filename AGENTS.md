# Project notes

This is the existing Laravel 13 backend for the 'Megyeri Márk | Webfejlesztés' Angular portfolio.
Preserve the original database migrations; make additive migrations instead of reworking migrations already applied by the user.
No public admin registration or client PII in public API resources.
Auth: Sanctum session-cookie SPA flow with CSRF, protected by admin gate. See README.md and API.md.
Run `php artisan test` on a machine with Composer dependencies and pdo_sqlite installed before production use.
Do not run migrate:fresh against a database with customer information.
