-- Run once if profile updates fail (missing columns on older databases)
USE bantaypurrpaws;

ALTER TABLE users
    ADD COLUMN   username VARCHAR(50) DEFAULT NULL;

ALTER TABLE users
    ADD COLUMN   phone_number VARCHAR(20) DEFAULT NULL;

ALTER TABLE users
    ADD COLUMN   profile_picture VARCHAR(512) DEFAULT NULL;

CREATE UNIQUE INDEX   uk_users_username ON users (username);
