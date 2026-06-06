-- Sensitive data + notification schema fixes
-- Safe to run on existing databases (uses information_schema checks where needed)

USE bantaypurrpaws;

-- Email lookup hash for encrypted addresses
SET @has_email_hash = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email_hash'
);
SET @sql = IF(@has_email_hash = 0,
    'ALTER TABLE `users` ADD COLUMN `email_hash` VARCHAR(64) DEFAULT NULL AFTER `email`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Widen email column for encrypted payloads
ALTER TABLE `users` MODIFY COLUMN `email` VARCHAR(512) NOT NULL;
ALTER TABLE `users` MODIFY COLUMN `phone_number` VARCHAR(512) DEFAULT NULL;

-- Notifications: nullable application_id + user targeting columns
SET @app_nullable = (
    SELECT IS_NULLABLE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'application_id'
    LIMIT 1
);
SET @sql = IF(@app_nullable = 'NO',
    'ALTER TABLE `notifications` MODIFY COLUMN `application_id` INT NULL DEFAULT NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_uid = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'user_id'
);
SET @sql = IF(@has_uid = 0,
    'ALTER TABLE `notifications` ADD COLUMN `user_id` INT NULL DEFAULT NULL AFTER `application_id`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_type = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'notification_type'
);
SET @sql = IF(@has_type = 0,
    'ALTER TABLE `notifications` ADD COLUMN `notification_type` VARCHAR(32) NOT NULL DEFAULT ''adoption'' AFTER `user_id`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_link = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'link_url'
);
SET @sql = IF(@has_link = 0,
    'ALTER TABLE `notifications` ADD COLUMN `link_url` VARCHAR(512) DEFAULT NULL AFTER `message`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Submission tables: widen for encrypted contact/email
ALTER TABLE `adoption_applications` MODIFY COLUMN `email` VARCHAR(512) NOT NULL;
ALTER TABLE `adoption_applications` MODIFY COLUMN `contact_number` VARCHAR(512) NOT NULL;
ALTER TABLE `rescue_reports` MODIFY COLUMN `contact_number` VARCHAR(512) NOT NULL;
