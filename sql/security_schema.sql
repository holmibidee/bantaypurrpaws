-- ============================================================
--  BantayPurrPaws — Enterprise Security Schema
--  Run after schema.sql or merge into it
-- ============================================================

USE bantaypurrpaws;

-- Add 'login' purpose to otp_tokens if not already present
-- ALTER TABLE `otp_tokens`
--     MODIFY COLUMN `purpose` ENUM('registration','password_reset','google_link','login','number_match') NOT NULL DEFAULT 'registration';

-- ── Login Attempts ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id`               INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`          INT DEFAULT NULL,
    `email`            VARCHAR(512) NOT NULL,
    `ip_address`       VARCHAR(64) NOT NULL,
    `user_agent`       TEXT DEFAULT NULL,
    `device_type`      VARCHAR(100) DEFAULT NULL,
    `browser`          VARCHAR(100) DEFAULT NULL,
    `os`               VARCHAR(100) DEFAULT NULL,
    `device_fingerprint` VARCHAR(128) DEFAULT NULL,
    `location_country` VARCHAR(100) DEFAULT NULL,
    `location_city`    VARCHAR(100) DEFAULT NULL,
    `risk_level`       ENUM('low','medium','high','critical') NOT NULL DEFAULT 'low',
    `status`           ENUM('pending','approved','denied','otp_sent','verified','failed','blocked','suspicious') NOT NULL DEFAULT 'pending',
    `challenge_token`  VARCHAR(128) DEFAULT NULL,
    `number_shown`     TINYINT DEFAULT NULL,
    `number_options`   VARCHAR(20) DEFAULT NULL,
    `number_matched`   TINYINT(1) DEFAULT NULL,
    `email_action`     ENUM('approved','denied') DEFAULT NULL,
    `is_suspicious`    TINYINT(1) NOT NULL DEFAULT 0,
    `attempts_count`   INT NOT NULL DEFAULT 0,
    `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_la_email`       (`email`(191)),
    INDEX `idx_la_ip`          (`ip_address`),
    INDEX `idx_la_fingerprint` (`device_fingerprint`),
    INDEX `idx_la_status`      (`status`),
    INDEX `idx_la_created`     (`created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

-- ── Trusted Devices ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `trusted_devices` (
    `id`                 INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`            INT NOT NULL,
    `device_fingerprint` VARCHAR(128) NOT NULL,
    `device_name`        VARCHAR(150) DEFAULT NULL,
    `browser`            VARCHAR(100) DEFAULT NULL,
    `os`                 VARCHAR(100) DEFAULT NULL,
    `ip_address`         VARCHAR(64) DEFAULT NULL,
    `last_used_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at`         TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY `uk_td_user_fp` (`user_id`, `device_fingerprint`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- ── Security Events / Audit Log ─────────────────────────────
CREATE TABLE IF NOT EXISTS `security_events` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT DEFAULT NULL,
    `event_type`  VARCHAR(80) NOT NULL,
    `severity`    ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
    `ip_address`  VARCHAR(64) DEFAULT NULL,
    `description` TEXT,
    `metadata`    JSON DEFAULT NULL,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_se_user`    (`user_id`),
    INDEX `idx_se_type`    (`event_type`),
    INDEX `idx_se_created` (`created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

-- ── Active Sessions ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `user_sessions` (
    `id`                 INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`            INT NOT NULL,
    `session_token`      VARCHAR(128) NOT NULL,
    `ip_address`         VARCHAR(64) DEFAULT NULL,
    `device_fingerprint` VARCHAR(128) DEFAULT NULL,
    `user_agent`         TEXT DEFAULT NULL,
    `browser`            VARCHAR(100) DEFAULT NULL,
    `os`                 VARCHAR(100) DEFAULT NULL,
    `last_activity`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at`         TIMESTAMP NOT NULL,
    `is_active`          TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY `uk_session_token` (`session_token`),
    INDEX `idx_us_user` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- ── Blocked IPs ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `blocked_ips` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address`  VARCHAR(64) NOT NULL UNIQUE,
    `reason`      VARCHAR(255) DEFAULT NULL,
    `blocked_by`  INT DEFAULT NULL,
    `risk_score`  INT NOT NULL DEFAULT 0,
    `expires_at`  TIMESTAMP NULL DEFAULT NULL,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`blocked_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

-- ── Account Lockouts ────────────────────────────────────────
ALTER TABLE `users`
    ADD COLUMN `failed_login_count` INT NOT NULL DEFAULT 0,
    ADD COLUMN `locked_until` TIMESTAMP NULL DEFAULT NULL,
    ADD COLUMN `last_login_at` TIMESTAMP NULL DEFAULT NULL,
    ADD COLUMN `last_login_ip` VARCHAR(64) DEFAULT NULL,
    ADD COLUMN `risk_score` INT NOT NULL DEFAULT 0;

-- Allow tables in db.php
-- (Remember to add new tables to DB_ALLOWED_TABLES in db.php)
