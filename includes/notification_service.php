<?php
/**
 * Notification Service for BDMS
 * Handles creation, retrieval, and status updates of user notifications.
 */

require_once __DIR__ . '/../config/db.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Add a notification for a user
 */
function add_notification($userId, $title, $message, $type = 'info', $link = null) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, link)
            VALUES (:user_id, :title, :message, :type, :link)
        ");
        return $stmt->execute([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'link' => $link
        ]);
    } catch (PDOException $e) {
        error_log("Failed to add notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Retrieve notifications for a user
 */
function get_notifications($userId, $limit = 10, $unreadOnly = false) {
    global $pdo;
    try {
        $sql = "SELECT * FROM notifications WHERE user_id = :user_id";
        if ($unreadOnly) {
            $sql .= " AND is_read = 0";
        }
        $sql .= " ORDER BY created_at DESC LIMIT :limit";
        
        $stmt = $pdo->prepare($sql);
        // Bind parameters safely
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Failed to fetch notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Mark a specific notification as read
 */
function mark_as_read($userId, $notificationId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE id = :id AND user_id = :user_id
        ");
        return $stmt->execute([
            'id' => $notificationId,
            'user_id' => $userId
        ]);
    } catch (PDOException $e) {
        error_log("Failed to mark notification read: " . $e->getMessage());
        return false;
    }
}

/**
 * Mark all notifications of a user as read
 */
function mark_all_read($userId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE user_id = :user_id
        ");
        return $stmt->execute(['user_id' => $userId]);
    } catch (PDOException $e) {
        error_log("Failed to mark all notifications read: " . $e->getMessage());
        return false;
    }
}

/**
 * Notify all matching active donors in a city about a new blood request
 */
function notify_matching_donors($requestId, $bloodGroup, $city) {
    global $pdo;
    try {
        // Find active, verified donors with matching blood type in the same city
        $stmt = $pdo->prepare("
            SELECT id, email, full_name 
            FROM users 
            WHERE role = 'donor' 
              AND blood_type = :blood_type 
              AND city = :city 
              AND status = 'active'
        ");
        $stmt->execute([
            'blood_type' => $bloodGroup,
            'city' => $city
        ]);
        $donors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($donors as $donor) {
            $title = "Urgent matching request in {$city}";
            $message = "A recipient needs {$bloodGroup} blood at a local hospital in {$city}. Check if you can schedule a donation.";
            $link = "donor-dashboard.php";
            add_notification($donor['id'], $title, $message, 'blood_request', $link);
        }
        return count($donors);
    } catch (PDOException $e) {
        error_log("Failed to notify matching donors: " . $e->getMessage());
        return 0;
    }
}

/**
 * Trigger automatic daily reminders for a user (eligibility and upcoming bookings)
 */
function check_and_trigger_reminders($userId) {
    global $pdo;
    try {
        // 1. Check eligibility
        $stmt = $pdo->prepare("SELECT role, last_donation_date FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && $user['role'] === 'donor') {
            $last_donation = $user['last_donation_date'] ?? null;
            $is_eligible = false;
            if ($last_donation) {
                $next_eligible_date = date('Y-m-d', strtotime($last_donation . ' + 56 days'));
                $today = date('Y-m-d');
                $is_eligible = ($today >= $next_eligible_date);
            } else {
                $is_eligible = true;
            }
            
            if ($is_eligible) {
                // Check if we already sent a reminder in the last 7 days to avoid spam
                $stmt_check = $pdo->prepare("
                    SELECT COUNT(*) FROM notifications 
                    WHERE user_id = :user_id 
                      AND title = 'Blood Donation Reminder' 
                      AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                ");
                $stmt_check->execute(['user_id' => $userId]);
                if ($stmt_check->fetchColumn() == 0) {
                    add_notification(
                        $userId,
                        "Blood Donation Reminder",
                        "It has been more than 56 days since your last donation. You are eligible to save lives again! Click to schedule.",
                        'info',
                        'donor-schedule.php'
                    );
                }
            }
        }
        
        // 2. Check upcoming bookings (today or tomorrow)
        $stmt_booking = $pdo->prepare("
            SELECT b.id, s.slot_date, s.start_time 
            FROM donation_bookings b 
            JOIN donation_slots s ON b.slot_id = s.id 
            WHERE b.donor_id = :user_id 
              AND b.status = 'scheduled' 
              AND s.slot_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 1 DAY)
        ");
        $stmt_booking->execute(['user_id' => $userId]);
        $bookings = $stmt_booking->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($bookings as $b) {
            $bookingDate = date('M d, Y', strtotime($b['slot_date']));
            $bookingTime = date('h:i A', strtotime($b['start_time']));
            // Check if we sent a reminder for this booking in the last 24 hours
            $title = "Upcoming Donation Reminder";
            $stmt_check_book = $pdo->prepare("
                SELECT COUNT(*) FROM notifications 
                WHERE user_id = :user_id 
                  AND title = :title 
                  AND message LIKE :msg_like
                  AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $stmt_check_book->execute([
                'user_id' => $userId,
                'title' => $title,
                'msg_like' => "%{$bookingDate}%"
            ]);
            if ($stmt_check_book->fetchColumn() == 0) {
                add_notification(
                    $userId,
                    $title,
                    "Reminder: You have a scheduled blood donation appointment on {$bookingDate} at {$bookingTime}. Please eat well and stay hydrated!",
                    'warning',
                    'donor-dashboard.php'
                );
            }
        }
    } catch (PDOException $e) {
        error_log("Failed to run reminders check: " . $e->getMessage());
    }
}

