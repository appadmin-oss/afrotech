-- ================================================================
-- Afrotech Academy — migration: payment confirmation
--
-- A payment can be confirmed from three directions — the gateway
-- callback the parent's browser follows, the server-to-server
-- webhook, and an operator confirming a bank transfer by hand — and
-- any two can arrive at once. These columns make the confirmation
-- idempotent and auditable:
--
--   confirmed_via    which path actually flipped it to succeeded
--   receipt_sent_at  claimed once, so a re-verified payment cannot
--                    email a second receipt
--
-- Safe to run on a live database and re-runnable: every ALTER is
-- guarded, so a second import is a no-op rather than an error.
--
-- Import: mysql -u <user> -p <db> < database/migrations/2026-07-payment-confirmation.sql
-- ================================================================

SET NAMES utf8mb4;

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments'
        AND COLUMN_NAME = 'confirmed_via') = 0,
    "ALTER TABLE `payments` ADD COLUMN `confirmed_via` ENUM('callback','webhook','operator','manual') NULL DEFAULT NULL AFTER `verified_at`",
    'DO 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments'
        AND COLUMN_NAME = 'confirmed_by') = 0,
    'ALTER TABLE `payments` ADD COLUMN `confirmed_by` INT UNSIGNED NULL DEFAULT NULL AFTER `confirmed_via`',
    'DO 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments'
        AND COLUMN_NAME = 'receipt_sent_at') = 0,
    'ALTER TABLE `payments` ADD COLUMN `receipt_sent_at` DATETIME NULL DEFAULT NULL AFTER `confirmed_by`',
    'DO 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Operator note for a hand-confirmed transfer (teller name, date paid, etc).
SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments'
        AND COLUMN_NAME = 'confirmation_note') = 0,
    'ALTER TABLE `payments` ADD COLUMN `confirmation_note` VARCHAR(300) NULL DEFAULT NULL AFTER `receipt_sent_at`',
    'DO 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
