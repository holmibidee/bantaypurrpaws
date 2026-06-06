-- Pet Adoption feature tables (run after main database.sql)
USE bantaypurrpaws;

CREATE TABLE   pets (
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

CREATE TABLE   pet_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pet_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE
);

CREATE TABLE   adoption_applications (
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

CREATE TABLE   notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    message VARCHAR(255) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES adoption_applications(id) ON DELETE CASCADE
);
