-- BantayPurrPaws — OTP purposes migration
-- Widens the purpose column from ENUM to VARCHAR(60) so any purpose string works.
-- Safe to run multiple times.

ALTER TABLE `otp_tokens`
    MODIFY COLUMN `purpose` VARCHAR(60) NOT NULL DEFAULT 'registration';

-- Staff invite tokens table (if not already present)
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

-- Ensure staff_permissions column on users
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `staff_permissions` LONGTEXT DEFAULT NULL;

-- Ensure schedule columns on adoption_applications
ALTER TABLE `adoption_applications`
    ADD COLUMN IF NOT EXISTS `schedule_date` DATE DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `schedule_time` TIME DEFAULT NULL;
