-- Afrotech Academy — schema.sql
-- MySQL 5.7+ / MariaDB 10.3+ (as found on cPanel).
-- Import: mysql -u <user> -p <db> < database/schema.sql
--     or via cPanel → phpMyAdmin → Import.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----- Operators (admin RBAC) --------------------------------
-- Every back-office user. `role` drives the Rbac permission map
-- (see src/core/Rbac.php). `staff_id` is the printable house id.
CREATE TABLE IF NOT EXISTS `admin_users` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `staff_id`       VARCHAR(32)  NOT NULL,
    `name`           VARCHAR(120) NOT NULL,
    `username`       VARCHAR(80)  NOT NULL,
    `email`          VARCHAR(160) NOT NULL,
    `password_hash`  VARCHAR(255) NOT NULL,
    `role`           ENUM('super_admin','admin','registrar','instructor','viewer') NOT NULL DEFAULT 'viewer',
    `status`         ENUM('active','suspended') NOT NULL DEFAULT 'active',
    `last_login_at`  DATETIME     NULL DEFAULT NULL,
    `created_by`     INT UNSIGNED NULL DEFAULT NULL,
    `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `admin_users_username` (`username`),
    UNIQUE KEY `admin_users_email`    (`email`),
    UNIQUE KEY `admin_users_staffid`  (`staff_id`),
    KEY `admin_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----- Tracks (the six flier programs) -----------------------
CREATE TABLE IF NOT EXISTS `tracks` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `slug`        VARCHAR(80)  NOT NULL,
    `name`        VARCHAR(120) NOT NULL,
    `icon`        VARCHAR(40)  DEFAULT 'chip',
    `summary`     VARCHAR(400) DEFAULT NULL,
    `outcomes`    TEXT,                       -- JSON array of bullet strings
    `sort`        INT NOT NULL DEFAULT 0,
    `status`      ENUM('active','hidden') NOT NULL DEFAULT 'active',
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `tracks_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----- Courses (LMS catalog) ---------------------------------
CREATE TABLE IF NOT EXISTS `courses` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title`         VARCHAR(200) NOT NULL,
    `slug`          VARCHAR(200) NOT NULL,
    `track_slug`    VARCHAR(80)  DEFAULT NULL,
    `level`         VARCHAR(40)  DEFAULT 'Beginner',
    `age_range`     VARCHAR(40)  DEFAULT '7+',
    `weeks`         INT NOT NULL DEFAULT 4,
    `instructor`    VARCHAR(160) DEFAULT NULL,
    `price_naira`   INT NOT NULL DEFAULT 0,
    `summary`       VARCHAR(500) DEFAULT NULL,
    `body`          MEDIUMTEXT,
    `syllabus_json` TEXT,                       -- JSON array of {title, desc}
    `cover`         VARCHAR(255) DEFAULT NULL,
    `status`        ENUM('draft','published') NOT NULL DEFAULT 'draft',
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `courses_slug` (`slug`),
    KEY `courses_status` (`status`),
    KEY `courses_track`  (`track_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----- Students (learner accounts) ---------------------------
CREATE TABLE IF NOT EXISTS `students` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id`    VARCHAR(32)  NOT NULL,
    `name`          VARCHAR(160) NOT NULL,
    `email`         VARCHAR(160) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL DEFAULT '',
    `phone`         VARCHAR(40)  DEFAULT NULL,
    `guardian_name` VARCHAR(160) DEFAULT NULL,
    `age`           TINYINT UNSIGNED DEFAULT NULL,
    `track_slug`    VARCHAR(80)  DEFAULT NULL,
    `status`        ENUM('active','paused','withdrawn') NOT NULL DEFAULT 'active',
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `students_email`   (`email`),
    UNIQUE KEY `students_studentid` (`student_id`),
    KEY `students_track` (`track_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----- Enrollments (student <-> course) ----------------------
CREATE TABLE IF NOT EXISTS `enrollments` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id`   INT UNSIGNED NOT NULL,
    `course_id`    INT UNSIGNED NOT NULL,
    `progress`     TINYINT UNSIGNED NOT NULL DEFAULT 0,   -- 0..100
    `status`       ENUM('active','completed','dropped') NOT NULL DEFAULT 'active',
    `enrolled_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `enroll_unique` (`student_id`, `course_id`),
    KEY `enroll_course` (`course_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----- Summer school registrations ---------------------------
-- The intake pipeline behind /summer. `reg_code` is the printable
-- id emailed to the family; `status` drives the registrar workflow.
CREATE TABLE IF NOT EXISTS `summer_registrations` (
    `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reg_code`         VARCHAR(32)  NOT NULL,
    `student_name`     VARCHAR(160) NOT NULL,
    `student_age`      TINYINT UNSIGNED NOT NULL,
    `guardian_name`    VARCHAR(160) NOT NULL,
    `email`            VARCHAR(160) NOT NULL,
    `phone`            VARCHAR(40)  NOT NULL,
    `track_slug`       VARCHAR(80)  DEFAULT NULL,
    `track_name`       VARCHAR(120) DEFAULT NULL,
    `location_pref`    VARCHAR(120) DEFAULT NULL,
    `experience`       VARCHAR(40)  DEFAULT 'none',
    `notes`            TEXT,
    `fee_naira`        INT NOT NULL DEFAULT 40000,
    `payment_status`   ENUM('unpaid','paid','waived') NOT NULL DEFAULT 'unpaid',
    `status`           ENUM('pending','confirmed','waitlisted','cancelled') NOT NULL DEFAULT 'pending',
    `source`           VARCHAR(60)  DEFAULT 'web',
    `request_ip`       VARCHAR(45)  DEFAULT NULL,
    `handled_by`       INT UNSIGNED NULL DEFAULT NULL,
    `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `summer_regcode` (`reg_code`),
    KEY `summer_status`  (`status`),
    KEY `summer_email`   (`email`),
    KEY `summer_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----- Editable content blocks (landing-page copy) -----------
CREATE TABLE IF NOT EXISTS `content_blocks` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key_name`   VARCHAR(80)  NOT NULL,
    `value_text` TEXT,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `content_blocks_key` (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----- Contact / general inquiries ---------------------------
CREATE TABLE IF NOT EXISTS `inquiries` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`       VARCHAR(160) NOT NULL,
    `email`      VARCHAR(160) NOT NULL,
    `phone`      VARCHAR(40)  DEFAULT NULL,
    `message`    TEXT NOT NULL,
    `status`     ENUM('new','open','resolved') NOT NULL DEFAULT 'new',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `inquiries_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----- Settings (key/value, typed by the Setting model) -----
-- Everything the flier hard-codes lives here so it's editable in admin:
-- program fee, currency, age label, registration deadline, cohort dates,
-- campuses, payment toggle, etc.
CREATE TABLE IF NOT EXISTS `settings` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key_name`   VARCHAR(80) NOT NULL,
    `value_text` TEXT,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `settings_key` (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----- Promotions (announcement ribbon / countdown banner) ---
CREATE TABLE IF NOT EXISTS `promotions` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title`      VARCHAR(200) NOT NULL,
    `body`       VARCHAR(400) DEFAULT NULL,
    `badge`      VARCHAR(60)  DEFAULT NULL,
    `cta_label`  VARCHAR(80)  DEFAULT NULL,
    `cta_href`   VARCHAR(300) DEFAULT NULL,
    `tone`       ENUM('red','ink','gold') NOT NULL DEFAULT 'red',
    `show_countdown` TINYINT(1) NOT NULL DEFAULT 0,
    `starts_at`  DATETIME NULL DEFAULT NULL,
    `ends_at`    DATETIME NULL DEFAULT NULL,
    `sort`       INT NOT NULL DEFAULT 0,
    `status`     ENUM('active','paused') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `promotions_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----- Discount codes ----------------------------------------
CREATE TABLE IF NOT EXISTS `discount_codes` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code`        VARCHAR(40)  NOT NULL,
    `description` VARCHAR(200) DEFAULT NULL,
    `type`        ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
    `value`       INT NOT NULL DEFAULT 0,          -- percent (0..100) or naira
    `min_amount`  INT NOT NULL DEFAULT 0,
    `max_uses`    INT NULL DEFAULT NULL,           -- NULL = unlimited
    `used_count`  INT NOT NULL DEFAULT 0,
    `starts_at`   DATETIME NULL DEFAULT NULL,
    `ends_at`     DATETIME NULL DEFAULT NULL,
    `status`      ENUM('active','paused') NOT NULL DEFAULT 'active',
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `discount_code` (`code`),
    KEY `discount_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----- Payments ----------------------------------------------
CREATE TABLE IF NOT EXISTS `payments` (
    `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reference`         VARCHAR(60)  NOT NULL,
    `registration_id`   INT UNSIGNED NULL DEFAULT NULL,
    `email`             VARCHAR(160) NOT NULL,
    `provider`          ENUM('paystack','manual') NOT NULL DEFAULT 'paystack',
    `currency`          VARCHAR(8)   NOT NULL DEFAULT 'NGN',
    `base_amount`       INT NOT NULL DEFAULT 0,     -- naira before discount
    `discount_code`     VARCHAR(40)  DEFAULT NULL,
    `discount_amount`   INT NOT NULL DEFAULT 0,     -- naira discounted
    `amount`            INT NOT NULL DEFAULT 0,     -- naira actually charged
    `status`            ENUM('pending','succeeded','failed','abandoned') NOT NULL DEFAULT 'pending',
    `provider_reference` VARCHAR(120) DEFAULT NULL,
    `provider_response` TEXT,
    `verified_at`       DATETIME NULL DEFAULT NULL,
    `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `payments_reference` (`reference`),
    KEY `payments_reg`    (`registration_id`),
    KEY `payments_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
