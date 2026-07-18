<?php
/**
 * API: Mark Notification Read — BDMS
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

// Requires logged in user
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

header('Content-Type: application/json');

$csrf_token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Security token mismatch. Please reload.']);
    exit;
}

$notification_id = intval($_POST['notification_id'] ?? 0);
$mark_all = isset($_POST['all']) && $_POST['all'] == 'true';

try {
    if ($mark_all) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $_SESSION['user_id']]);
    } else {
        if ($notification_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid notification ID.']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id");
        $stmt->execute(['id' => $notification_id, 'user_id' => $_SESSION['user_id']]);
    }

    echo json_encode([
        'success' => true
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
