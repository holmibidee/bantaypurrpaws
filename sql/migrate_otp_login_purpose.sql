-- ============================================================
--  Migration: add 'login' to otp_tokens.purpose ENUM
--
--  Run this once against your existing database if you are
--  upgrading from a version that did NOT include 'login'.
--
--  MySQL silently stored an empty string '' when purpose='login'
--  was inserted before this migration, causing all login OTPs
--  to fail verification with "invalid".  This migration fixes
--  the column definition AND cleans up any orphaned '' rows.
-- ============================================================

ALTER TABLE `otp_tokens`
  MODIFY COLUMN `purpose`
    ENUM(
      'registration',
      'login',
      'password_reset',
      'google_link',
      'profile_update',
      'email_change_current',
      'email_change_new',
      'staff_invite'
    ) NOT NULL DEFAULT 'registration';

-- Clean up any rows that were silently stored with an empty purpose
-- (these are broken OTP records from before the fix and can be discarded).
DELETE FROM `otp_tokens` WHERE `purpose` = '';
