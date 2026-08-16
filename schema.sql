CREATE DATABASE IF NOT EXISTS nosh_softdev;
USE nosh_softdev;

CREATE TABLE IF NOT EXISTS certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_hash VARCHAR(64) NOT NULL UNIQUE,
    previous_hash VARCHAR(64) DEFAULT NULL,
    record_hash VARCHAR(64) NOT NULL,
    is_revoked BOOLEAN NOT NULL DEFAULT 0,
    student_name VARCHAR(255) NOT NULL,
    degree VARCHAR(255) NOT NULL,
    institution VARCHAR(255) NOT NULL,
    issuance_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS institutions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    website VARCHAR(255) NOT NULL,
    rep_name VARCHAR(255) NOT NULL,
    rep_title VARCHAR(255) NOT NULL,
    status ENUM('pending','active') NOT NULL DEFAULT 'pending',
    failed_attempts INT NOT NULL DEFAULT 0,
    locked_until DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution VARCHAR(255) DEFAULT NULL,
    action ENUM('issue','verify','delete','login','login_failed') NOT NULL,
    document_hash VARCHAR(64) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seeded demo accounts (passwords: nosh327 / brac327)
INSERT INTO institutions (name, password_hash, location, email, website, rep_name, rep_title, status) VALUES
('North South University', '$2y$10$WpIw16WDZXSZczxNjiEJ5.sPT4WEgjm8yCl1zq1mWd/jEh3jf.Qwy', 'Dhaka, Bangladesh', 'registrar@northsouth.edu', 'https://www.northsouth.edu', 'Demo Registrar', 'Registrar', 'active'),
('Brac University', '$2y$10$3JC7t1a1L9ZPt/yUeCi48.SKoE2E7.fupleNoCny5.jMGcfuGKkWm', 'Dhaka, Bangladesh', 'registrar@bracu.ac.bd', 'https://www.bracu.ac.bd', 'Demo Registrar', 'Registrar', 'active')
ON DUPLICATE KEY UPDATE name = name;