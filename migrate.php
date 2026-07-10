<?php
require_once __DIR__ . '/config/db.php';

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0;");
    $pdo->exec("DROP TABLE IF EXISTS `donation_bookings`");
    $pdo->exec("DROP TABLE IF EXISTS `donation_slots`");
    
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

    $pdo->exec("INSERT IGNORE INTO `donation_slots` (`slot_date`, `start_time`, `end_time`, `capacity`, `booked_count`, `status`)
VALUES 
('2026-07-15', '09:00:00', '10:00:00', 5, 0, 'available'),
('2026-07-15', '10:00:00', '11:00:00', 5, 0, 'available'),
('2026-07-15', '11:00:00', '12:00:00', 5, 0, 'available'),
('2026-07-16', '14:00:00', '15:00:00', 2, 0, 'available'),
('2026-07-16', '15:00:00', '16:00:00', 2, 0, 'available'),
('2026-07-20', '09:00:00', '11:00:00', 10, 0, 'available');");

    echo "Migration completed successfully.\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
