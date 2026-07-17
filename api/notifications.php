<?php
/**
 * API: Manage Notifications
 * Allows fetching notifications and marking them as read.
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/notification_service.php';

// Enforce login
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Fetch notifications
    $unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] == '1';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    if ($limit <= 0 || $limit > 50) {
        $limit = 10;
    }
    
    $notifications = get_notifications($userId, $limit, $unreadOnly);
    
    // Format notifications for display
    $formatted = [];
    foreach ($notifications as $notification) {
        $formatted[] = [
            'id' => $notification['id'],
            'title' => $notification['title'],
            'message' => $notification['message'],
            'type' => $notification['type'],
            'link' => $notification['link'],
            'is_read' => (bool)$notification['is_read'],
            'created_at' => $notification['created_at'],
            'time_ago' => time_elapsed_string($notification['created_at'])
        ];
    }
    
    echo json_encode(['success' => true, 'notifications' => $formatted]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Parse input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $action = $input['action'] ?? '';
    
    if ($action === 'mark_read') {
        $notificationId = intval($input['id'] ?? 0);
        if ($notificationId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid notification ID']);
            exit;
        }
        
        $success = mark_as_read($userId, $notificationId);
        echo json_encode(['success' => $success]);
        exit;
    }
    
    if ($action === 'mark_all_read') {
        $success = mark_all_read($userId);
        echo json_encode(['success' => $success]);
        exit;
    }
    
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
    exit;
}

// Utility to print friendly time elapsed
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
