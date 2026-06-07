-- Default admin account
-- Email: anthony.domasig@evsu.edu.ph
-- Password: Admin@1234 (change this after first login!)

INSERT INTO `users` (
    `full_name`,
    `email`,
    `password`,
    `role`,
    `email_verified`,
    `auth_provider`,
    `failed_login_count`,
    `risk_score`
) VALUES (
    'System Administrator',
    'anthony.domasig@evsu.edu.ph',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin',
    1,
    'local',
    0,
    0
);