-- BantayPurrPaws: Fix rescue_reports status ENUM
-- Run this once to add 'submitted' as a valid status alias (for backward compat)
-- and ensure 'pending' is the canonical initial status.

ALTER TABLE rescue_reports
  MODIFY COLUMN status ENUM('submitted','pending','in_progress','rescued','failed')
  NOT NULL DEFAULT 'pending';

-- Normalize any existing 'submitted' rows to 'pending' (they are the same state)
UPDATE rescue_reports SET status = 'pending' WHERE status = 'submitted';

-- Restore to clean ENUM without 'submitted' (optional, run after normalizing data)
-- ALTER TABLE rescue_reports
--   MODIFY COLUMN status ENUM('pending','in_progress','rescued','failed')
--   NOT NULL DEFAULT 'pending';
