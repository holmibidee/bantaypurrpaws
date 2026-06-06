-- ─────────────────────────────────────────────────────────────────
-- BantayPurrPaws — RBAC: staff_permissions column
-- Run once via phpMyAdmin or: mysql -u root bantaypurrpaws < database/rbac_permissions.sql
-- ─────────────────────────────────────────────────────────────────

USE bantaypurrpaws;

-- Add a JSON permissions column to users (NULL = inherit role defaults)
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS staff_permissions JSON DEFAULT NULL
    COMMENT 'Null = use role defaults. JSON array of permission keys for staff overrides.'
    AFTER role;

-- ─── Permissions reference (store any subset as a JSON array) ───
-- "manage_reports"      – view + update rescue reports
-- "manage_pets"         – create / edit / delete pet listings
-- "review_adoptions"    – approve / reject adoption applications
-- "view_adoptions"      – view adoption queue (read-only)
-- "manage_users"        – manage regular user accounts
-- "manage_staff"        – create / delete staff accounts (admin-only by default)
-- "post_announcements"  – publish site announcements
-- ────────────────────────────────────────────────────────────────

-- Example: give the default staff seed account limited permissions
UPDATE users
SET staff_permissions = JSON_ARRAY(
    'manage_reports',
    'manage_pets',
    'view_adoptions'
)
WHERE id = 2 AND role = 'staff';
