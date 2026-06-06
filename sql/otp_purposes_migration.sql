-- BantayPurrPaws — Migration
-- Compatible with MySQL 5.7+ and MariaDB 10.x
-- Run once in phpMyAdmin or: mysql -u root -p bantaypurrpaws < sql/otp_purposes_migration.sql

-- ── 1. Extend OTP purposes ENUM ───────────────────────────
ALTER TABLE `otp_tokens`
    MODIFY COLUMN `purpose` ENUM(
        'registration',
        'password_reset',
        'google_link',
        'profile_update',
        'email_change_current',
        'email_change_new',
        'staff_invite'
    ) NOT NULL DEFAULT 'registration';

-- ── 2. Staff invite tokens table ──────────────────────────
CREATE TABLE IF NOT EXISTS `staff_invites` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `email`       VARCHAR(150) NOT NULL,
    `token`       VARCHAR(64)  NOT NULL UNIQUE,
    `permissions` LONGTEXT     DEFAULT NULL,
    `expires_at`  DATETIME     NOT NULL,
    `used`        TINYINT(1)   NOT NULL DEFAULT 0,
    `created_by`  INT          DEFAULT NULL,
    `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_invite_token` (`token`),
    INDEX `idx_invite_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 3. Add staff_permissions to users (safe, no IF NOT EXISTS) ──
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'users'
      AND COLUMN_NAME  = 'staff_permissions'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `staff_permissions` LONGTEXT DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 4. Add schedule_date to adoption_applications ─────────
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'adoption_applications'
      AND COLUMN_NAME  = 'schedule_date'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `adoption_applications` ADD COLUMN `schedule_date` DATE DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 5. Add schedule_time to adoption_applications ─────────
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'adoption_applications'
      AND COLUMN_NAME  = 'schedule_time'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `adoption_applications` ADD COLUMN `schedule_time` TIME DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 6. Make old required fields nullable (backward compat) ─
ALTER TABLE `adoption_applications`
    MODIFY COLUMN `address`              TEXT          DEFAULT NULL,
    MODIFY COLUMN `reason_for_adoption`  TEXT          DEFAULT NULL,
    MODIFY COLUMN `home_type`            VARCHAR(80)   DEFAULT NULL,
    MODIFY COLUMN `email`                VARCHAR(150)  DEFAULT NULL;

-- ── 7. Extend pets status ENUM ────────────────────────────
ALTER TABLE `pets`
    MODIFY COLUMN `status` ENUM('available','pending_adoption','adopted')
    NOT NULL DEFAULT 'available';
