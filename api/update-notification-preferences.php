<?php
/**
 * API: Update Notification Preferences — BDMS
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

$notify_email = isset($_POST['notify_email']) ? 1 : 0;
$notify_sms = isset($_POST['notify_sms']) ? 1 : 0;
$notify_blood_requests = isset($_POST['notify_blood_requests']) ? 1 : 0;

try {
    $stmt = $pdo->prepare("UPDATE users SET notify_email = :email, notify_sms = :sms, notify_blood_requests = :blood WHERE id = :id");
    $stmt->execute([
        'email' => $notify_email,
        'sms' => $notify_sms,
        'blood' => $notify_blood_requests,
        'id' => $_SESSION['user_id']
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Preferences updated successfully.'
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
