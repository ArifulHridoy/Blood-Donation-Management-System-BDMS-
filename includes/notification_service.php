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
