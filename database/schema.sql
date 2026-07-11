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

-- 5. DONATION SLOTS TABLE
CREATE TABLE IF NOT EXISTS `donation_slots` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `slot_date` DATE NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `capacity` INT DEFAULT 5,
  `booked_count` INT DEFAULT 0,
  `status` ENUM('available', 'full', 'cancelled') DEFAULT 'available',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_slot` (`slot_date`, `start_time`, `end_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. DONATION BOOKINGS TABLE
CREATE TABLE IF NOT EXISTS `donation_bookings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `slot_id` INT NOT NULL,
  `donor_id` INT NOT NULL,
  `status` ENUM('scheduled', 'completed', 'cancelled', 'no_show') DEFAULT 'scheduled',
  `booked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`slot_id`) REFERENCES `donation_slots` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`donor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_booking` (`slot_id`, `donor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. BLOOD REQUESTS TABLE
CREATE TABLE IF NOT EXISTS `blood_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `requester_id` INT NOT NULL,
  `blood_group` ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `urgency` ENUM('critical', 'urgent', 'standard') NOT NULL DEFAULT 'standard',
  `hospital_name` VARCHAR(200) NOT NULL,
  `ward` VARCHAR(100) NULL,
  `city` VARCHAR(100) NOT NULL,
  `contact_person` VARCHAR(100) NOT NULL,
  `contact_phone` VARCHAR(20) NOT NULL,
  `contact_email` VARCHAR(150) NULL,
  `notes` TEXT NULL,
  `status` ENUM('pending', 'approved', 'fulfilled', 'cancelled') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed some sample slots for testing (e.g. for July 2026)
INSERT IGNORE INTO `donation_slots` (`slot_date`, `start_time`, `end_time`, `capacity`, `booked_count`, `status`)
VALUES 
('2026-07-15', '09:00:00', '10:00:00', 5, 0, 'available'),
('2026-07-15', '10:00:00', '11:00:00', 5, 0, 'available'),
('2026-07-15', '11:00:00', '12:00:00', 5, 0, 'available'),
('2026-07-16', '14:00:00', '15:00:00', 2, 0, 'available'),
('2026-07-16', '15:00:00', '16:00:00', 2, 0, 'available'),
('2026-07-20', '09:00:00', '11:00:00', 10, 0, 'available');
