-- KIZARÓLAG akkor használd, ha a Rackhoston nem futtatható a `php artisan migrate`.
-- A futtatás ELŐTT készíts teljes MySQL mentést, és ellenőrizd, hogy a két mező még nem létezik.
-- EGYSZER futtatható; NE futtasd, ha az új Laravel migrációk már lefutottak.

ALTER TABLE quote_requests ADD COLUMN portal_token_expires_at TIMESTAMP NULL DEFAULT NULL AFTER portal_token_encrypted;
UPDATE quote_requests SET portal_token_expires_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 90 DAY)
  WHERE portal_token_hash IS NOT NULL AND portal_token_expires_at IS NULL;
ALTER TABLE users ADD COLUMN two_factor_email_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER is_admin;

-- A kézi sémamódosításnak megfelelően jelöljük a migrációkat végrehajtottnak.
SET @security_patch_batch = (SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations);
INSERT INTO migrations (migration, batch) VALUES
  ('2026_10_24_100000_add_client_access_expiry', @security_patch_batch),
  ('2026_10_24_101000_add_admin_email_two_factor', @security_patch_batch);
