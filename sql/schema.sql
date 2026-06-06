-- BantayPurrPaws — Full MySQL schema for XAMPP
-- Import via phpMyAdmin or: mysql -u root < sql/schema.sql

CREATE DATABASE IF NOT EXISTS bantaypurrpaws CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bantaypurrpaws;

-- Users
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(512) NOT NULL,
    email_hash VARCHAR(64) DEFAULT NULL,
    password VARCHAR(255) DEFAULT NULL,
    role ENUM('user', 'staff', 'admin') NOT NULL DEFAULT 'user',
    google_id VARCHAR(128) DEFAULT NULL,
    avatar_url VARCHAR(512) DEFAULT NULL,
    email_verified TINYINT(1) NOT NULL DEFAULT 0,
    auth_provider ENUM('local', 'google') NOT NULL DEFAULT 'local',
    username VARCHAR(50) DEFAULT NULL,
    phone_number VARCHAR(20) DEFAULT NULL,
    profile_picture VARCHAR(512) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_users_email_hash (email_hash),
    UNIQUE KEY uk_users_username (username)
);

-- Rescue reports
CREATE TABLE IF NOT EXISTS rescue_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_code VARCHAR(20) NOT NULL UNIQUE,
    reporter_id INT NOT NULL,
    reporter_name VARCHAR(150) NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    animal_type VARCHAR(100) DEFAULT NULL,
    location TEXT NOT NULL,
    description TEXT,
    photo_path VARCHAR(255) DEFAULT NULL,
    status ENUM('pending', 'in_progress', 'rescued', 'failed') NOT NULL DEFAULT 'pending',
    assigned_to INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
);

-- Report activity log
CREATE TABLE IF NOT EXISTS report_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NOT NULL,
    updated_by INT NOT NULL,
    old_status VARCHAR(50) DEFAULT NULL,
    new_status VARCHAR(50) DEFAULT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (report_id) REFERENCES rescue_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Pets
CREATE TABLE IF NOT EXISTS pets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    breed VARCHAR(100) NOT NULL,
    age VARCHAR(50) NOT NULL,
    gender ENUM('Male', 'Female', 'Unknown') NOT NULL DEFAULT 'Unknown',
    vaccination_status VARCHAR(150) DEFAULT NULL,
    health_condition TEXT DEFAULT NULL,
    description TEXT DEFAULT NULL,
    adoption_requirements TEXT DEFAULT NULL,
    rescue_date DATE DEFAULT NULL,
    status ENUM('available', 'adopted') NOT NULL DEFAULT 'available',
    image VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS pet_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pet_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS adoption_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pet_id INT NOT NULL,
    user_id INT NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    contact_number VARCHAR(30) NOT NULL,
    email VARCHAR(150) NOT NULL,
    address TEXT NOT NULL,
    occupation VARCHAR(100) NOT NULL,
    reason_for_adoption TEXT NOT NULL,
    home_type VARCHAR(80) NOT NULL,
    existing_pets ENUM('yes', 'no') NOT NULL,
    agreement TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT DEFAULT NULL,
    user_id INT DEFAULT NULL,
    notification_type VARCHAR(32) NOT NULL DEFAULT 'adoption',
    message VARCHAR(255) NOT NULL,
    link_url VARCHAR(512) DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES adoption_applications(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS otp_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    otp_code CHAR(6) NOT NULL,
    purpose ENUM('registration', 'password_reset', 'google_link') NOT NULL DEFAULT 'registration',
    expires_at DATETIME NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_otp_email_purpose (email, purpose),
    INDEX idx_otp_expires (expires_at)
);

-- Default admin (password: password) — change in production
INSERT IGNORE INTO users (id, full_name, email, password, role, email_verified, auth_provider) VALUES
(1, 'System Administrator', 'anthony.domasig@evsu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, 'local');
