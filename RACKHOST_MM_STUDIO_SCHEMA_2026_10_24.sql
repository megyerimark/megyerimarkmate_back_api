-- MM Studio / CRM additive schema patch for MySQL / MariaDB.
-- Run ONLY after a verified database backup and ONLY if artisan migrate has NOT created these tables.
-- Existing quote_requests and client information are not modified.

CREATE TABLE IF NOT EXISTS `studio_tasks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(160) NOT NULL,
  `description` TEXT NULL,
  `due_date` DATE NULL,
  `is_done` TINYINT(1) NOT NULL DEFAULT 0,
  `quote_request_id` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `studio_tasks_is_done_due_date_index` (`is_done`, `due_date`),
  KEY `studio_tasks_quote_request_id_foreign` (`quote_request_id`),
  CONSTRAINT `studio_tasks_quote_request_id_foreign` FOREIGN KEY (`quote_request_id`) REFERENCES `quote_requests` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `studio_client_notes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_email` VARCHAR(254) NOT NULL,
  `body` TEXT NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `studio_client_notes_client_email_created_at_index` (`client_email`(190), `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Keep Laravel's migration tracking consistent with this manual schema patch.
-- Do not insert a second record if the migration was already registered.
SET @studio_patch_batch = (SELECT COALESCE(MAX(batch), 0) + 1 FROM `migrations`);
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_24_140000_create_studio_workspace_tables', @studio_patch_batch
WHERE NOT EXISTS (
  SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_24_140000_create_studio_workspace_tables'
);
