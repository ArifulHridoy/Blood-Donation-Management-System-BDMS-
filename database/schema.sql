-- Blood Donation Management System (BDMS) Schema
CREATE DATABASE IF NOT EXISTS `bdms` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `bdms`;

-- 1. USERS TABLE
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) UNIQUE NOT NULL,
  `phone` VARCHAR(20) UNIQUE NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('donor', 'recipient', 'admin') DEFAULT 'donor',
  `blood_type` ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NULL,
  `date_of_birth` DATE NULL,
  `weight_kg` DECIMAL(5,2) NULL,
  `gender` ENUM('male', 'female', 'other') NULL,
  `address` VARCHAR(255) NULL,
  `city` VARCHAR(100) NULL,
  `last_donation_date` DATE NULL,
  `is_verified` TINYINT(1) DEFAULT 0,
  `status` ENUM('active', 'suspended') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. PASSWORD RESETS TABLE
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `token` VARCHAR(255) UNIQUE NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. LOGIN ATTEMPTS TABLE (brute-force protection)
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email_or_ip` VARCHAR(150) NOT NULL,
  `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `success` TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. EMAIL VERIFICATIONS TABLE
CREATE TABLE IF NOT EXISTS `email_verifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `token` VARCHAR(255) UNIQUE NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SEEDING TEST ACCOUNTS
-- Default Password is 'Password123' (hash: $2y$10$t8/tRVWoETodCMgwI2HPEO9yMx0BJWKPGS5M1Tc5Q.KDJDNB.gkG2)
INSERT INTO `users` (`full_name`, `email`, `phone`, `password_hash`, `role`, `is_verified`, `status`)
VALUES 
('System Administrator', 'admin@bdms.com', '01711111111', '$2y$10$t8/tRVWoETodCMgwI2HPEO9yMx0BJWKPGS5M1Tc5Q.KDJDNB.gkG2', 'admin', 1, 'active')
ON DUPLICATE KEY UPDATE `email`=`email`;

INSERT INTO `users` (`full_name`, `email`, `phone`, `password_hash`, `role`, `is_verified`, `status`)
VALUES 
('Khulna Medical College Hospital', 'hospital@bdms.com', '01722222222', '$2y$10$t8/tRVWoETodCMgwI2HPEO9yMx0BJWKPGS5M1Tc5Q.KDJDNB.gkG2', 'recipient', 1, 'active')
ON DUPLICATE KEY UPDATE `email`=`email`;

-- Seeding a donor with complete donor profile
INSERT INTO `users` (`full_name`, `email`, `phone`, `password_hash`, `role`, `blood_type`, `date_of_birth`, `weight_kg`, `gender`, `address`, `city`, `is_verified`, `status`)
VALUES 
('Rahim Ahmed', 'donor@bdms.com', '01733333333', '$2y$10$t8/tRVWoETodCMgwI2HPEO9yMx0BJWKPGS5M1Tc5Q.KDJDNB.gkG2', 'donor', 'O+', '1995-06-15', 72.50, 'male', '12 Boyra Main Road', 'Khulna', 1, 'active')
ON DUPLICATE KEY UPDATE `email`=`email`;
