<?php
/**
 * API: Update User Status (Activate/Suspend) — BDMS
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

// Only admins can manage users
checkRole(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

header('Content-Type: application/json');

$csrf_token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Security token mismatch. Please reload and try again.']);
    exit;
}

$user_id = intval($_POST['user_id'] ?? 0);
$new_status = trim($_POST['status'] ?? '');

if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid user ID.']);
    exit;
}

$valid_statuses = ['active', 'suspended'];
if (!in_array($new_status, $valid_statuses)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid status.']);
    exit;
}

// Prevent self suspension
if ($user_id === intval($_SESSION['user_id'])) {
    http_response_code(400);
    if (!empty($_POST['redirect'])) {
        set_flash_message('error', "You cannot suspend or modify your own administrator account status.");
        header('Location: ' . $_POST['redirect']);
        exit;
    }
    echo json_encode(['error' => 'You cannot modify your own administrator account status.']);
    exit;
}

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found.']);
        exit;
    }

    // Update the status
    $updateStmt = $pdo->prepare("UPDATE users SET status = :status WHERE id = :id");
    $updateStmt->execute([
        'status' => $new_status,
        'id' => $user_id
    ]);

    $message = "Account status for '" . h($user['full_name']) . "' has been updated to " . ucfirst($new_status) . ".";
    if (!empty($_POST['redirect'])) {
        set_flash_message('success', $message);
        header('Location: ' . $_POST['redirect']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'new_status' => $new_status
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
