<?php
require_once __DIR__ . '/config/db.php';

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0;");
    $pdo->exec("DROP TABLE IF EXISTS `donation_bookings`");
    $pdo->exec("DROP TABLE IF EXISTS `donation_slots`");
    $pdo->exec("DROP TABLE IF EXISTS `notifications`");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS `donation_slots` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `slot_date` DATE NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `capacity` INT DEFAULT 5,
  `booked_count` INT DEFAULT 0,
  `status` ENUM('available', 'full', 'cancelled') DEFAULT 'available',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_slot` (`slot_date`, `start_time`, `end_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `donation_bookings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `slot_id` INT NOT NULL,
  `donor_id` INT NOT NULL,
  `status` ENUM('scheduled', 'completed', 'cancelled', 'no_show') DEFAULT 'scheduled',
  `booked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`slot_id`) REFERENCES `donation_slots` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`donor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_booking` (`slot_id`, `donor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `blood_requests` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $pdo->exec("INSERT IGNORE INTO `donation_slots` (`slot_date`, `start_time`, `end_time`, `capacity`, `booked_count`, `status`)
VALUES 
('2026-07-15', '09:00:00', '10:00:00', 5, 0, 'available'),
('2026-07-15', '10:00:00', '11:00:00', 5, 0, 'available'),
('2026-07-15', '11:00:00', '12:00:00', 5, 0, 'available'),
('2026-07-16', '14:00:00', '15:00:00', 2, 0, 'available'),
('2026-07-16', '15:00:00', '16:00:00', 2, 0, 'available'),
('2026-07-20', '09:00:00', '11:00:00', 10, 0, 'available');");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('info', 'success', 'warning', 'error', 'blood_request') DEFAULT 'info',
  `link` VARCHAR(255) NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    echo "Migration completed successfully.\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
