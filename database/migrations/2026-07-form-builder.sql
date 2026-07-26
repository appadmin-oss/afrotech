-- ================================================================
-- Afrotech Academy — migration: logic-based form builder
--
-- Adds the versioned form-definition store behind /summer and the
-- columns a builder-driven submission needs on the intake record.
--
-- Safe to run on a live database: every statement is additive, and the
-- ALTERs are wrapped so a second run is a no-op rather than an error.
--
-- Import: mysql -u <user> -p <db> < database/migrations/2026-07-form-builder.sql
--     or via cPanel → phpMyAdmin → Import.
-- ================================================================

SET NAMES utf8mb4;

-- ----- Form definitions (one row per version) ------------------
-- Exactly one row per form_key is `live`; at most one is `draft`
-- (the builder's working copy); the rest are `archived` history.
CREATE TABLE IF NOT EXISTS `form_defs` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `form_key`      VARCHAR(60)  NOT NULL,
    `version`       INT UNSIGNED NOT NULL DEFAULT 1,
    `name`          VARCHAR(160) NOT NULL,
    `fields_json`   MEDIUMTEXT   NOT NULL,
    `settings_json` TEXT         NULL DEFAULT NULL,
    `status`        ENUM('draft','live','archived') NOT NULL DEFAULT 'draft',
    `published_by`  INT UNSIGNED NULL DEFAULT NULL,
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `form_defs_key_version` (`form_key`, `version`),
    KEY `form_defs_live` (`form_key`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----- Intake record: builder answers -------------------------
-- `answers_json` holds every non-core answer keyed by field key.
-- `form_version` pins the questions that produced it, so an answer
-- set can always be replayed against the form as it stood.
-- `addons_naira` is the priced add-on total the logic selected; the
-- checkout charges fee_naira, which already includes it.
SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'summer_registrations'
        AND COLUMN_NAME = 'answers_json') = 0,
    'ALTER TABLE `summer_registrations` ADD COLUMN `answers_json` MEDIUMTEXT NULL DEFAULT NULL AFTER `notes`',
    'DO 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'summer_registrations'
        AND COLUMN_NAME = 'form_version') = 0,
    'ALTER TABLE `summer_registrations` ADD COLUMN `form_version` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `answers_json`',
    'DO 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'summer_registrations'
        AND COLUMN_NAME = 'addons_naira') = 0,
    'ALTER TABLE `summer_registrations` ADD COLUMN `addons_naira` INT NOT NULL DEFAULT 0 AFTER `fee_naira`',
    'DO 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----- Campuses setting ---------------------------------------
-- The builder can bind a choice field to this list instead of
-- hard-coded options ("value | label" per line).
INSERT INTO `settings` (`key_name`, `value_text`)
VALUES ('campuses', 'Egbeda | Egbeda — 2 Oremeji Street\nIshefun | Ishefun — 18 Camp Davis Road\nOnline | Online (live classes)')
ON DUPLICATE KEY UPDATE `key_name` = `key_name`;
