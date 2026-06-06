-- ============================================================
--  BantayPurrPaws — Auth Enhancements Migration
--  Run AFTER database.sql
-- ============================================================
USE bantaypurrpaws;

-- ──────────────────────────────────────────────────────────
-- 1. Extend users table for Google OAuth
-- ──────────────────────────────────────────────────────────
ALTER TABLE users
    MODIFY COLUMN google_id       VARCHAR(128)  DEFAULT NULL AFTER role,
    MODIFY COLUMN avatar_url      VARCHAR(512)  DEFAULT NULL AFTER google_id,
    MODIFY COLUMN email_verified  TINYINT(1)    NOT NULL DEFAULT 0 AFTER avatar_url,
    MODIFY COLUMN auth_provider   ENUM('local','google') NOT NULL DEFAULT 'local' AFTER email_verified;

-- Allow NULL password for pure-Google accounts
ALTER TABLE users MODIFY COLUMN password VARCHAR(255) DEFAULT NULL;

-- Unique index on google_id (sparse — only non-null rows)
CREATE UNIQUE INDEX   uk_google_id ON users (google_id);

-- ──────────────────────────────────────────────────────────
-- 2. OTP tokens table
-- ──────────────────────────────────────────────────────────
CREATE TABLE   otp_tokens (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    email       VARCHAR(150)  NOT NULL,
    otp_code    CHAR(6)       NOT NULL,
    purpose     ENUM('registration','password_reset','google_link') NOT NULL DEFAULT 'registration',
    expires_at  DATETIME      NOT NULL,
    used        TINYINT(1)    NOT NULL DEFAULT 0,
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_otp_email_purpose (email, purpose),
    INDEX idx_otp_expires (expires_at)
);

-- ──────────────────────────────────────────────────────────
-- 3. Extend notifications: add user_id column so regular
--    users can receive their own notification feed
-- ──────────────────────────────────────────────────────────
SET @has_uid = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'notifications'
      AND COLUMN_NAME  = 'user_id'
);
SET @sql = IF(@has_uid = 0,
    'ALTER TABLE notifications ADD COLUMN user_id INT NULL DEFAULT NULL AFTER application_id,
     ADD FOREIGN KEY fk_notif_user (user_id) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
