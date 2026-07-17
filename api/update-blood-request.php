<?php
/**
 * API: Manage/Update Blood Request — BDMS
 * Allows recipients to cancel or mark their requests as fulfilled, and admins to manage them.
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/notification_service.php';

// Only recipients and admins can manage requests
checkRole(['recipient', 'admin']);

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

$request_id = intval($_POST['request_id'] ?? 0);
$new_status = trim($_POST['status'] ?? '');

if ($request_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request ID.']);
    exit;
}

$valid_statuses = ['pending', 'approved', 'fulfilled', 'cancelled'];
if (!in_array($new_status, $valid_statuses)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid status.']);
    exit;
}

try {
    // Check if request exists and if the user has permission to modify it
    $stmt = $pdo->prepare("
        SELECT r.*, u.email AS user_email, u.full_name AS user_name 
        FROM blood_requests r
        JOIN users u ON r.requester_id = u.id
        WHERE r.id = :id LIMIT 1
    ");
    $stmt->execute(['id' => $request_id]);
    $request = $stmt->fetch();

    if (!$request) {
        http_response_code(404);
        echo json_encode(['error' => 'Blood request not found.']);
        exit;
    }

    $is_admin = ($_SESSION['role'] === 'admin');
    $is_owner = ($_SESSION['role'] === 'recipient' && $request['requester_id'] == $_SESSION['user_id']);

    if (!$is_admin && !$is_owner) {
        http_response_code(403);
        echo json_encode(['error' => 'You do not have permission to modify this request.']);
        exit;
    }

    // Recipients can only change their requests to 'cancelled' or 'fulfilled'
    if (!$is_admin) {
        if (!in_array($new_status, ['cancelled', 'fulfilled'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Recipients can only cancel or mark requests as fulfilled.']);
            exit;
        }
    }

    // Update the request status
    $updateStmt = $pdo->prepare("UPDATE blood_requests SET status = :status WHERE id = :id");
    $updateStmt->execute([
        'status' => $new_status,
        'id' => $request_id
    ]);
  
      // Send notification to the requester
    $title = "Blood Request #{$request_id} Updated";
    $msg = "Your blood request has been marked as '{$new_status}'.";
    $link = "request-status.php?id={$request_id}";
    add_notification($request['requester_id'], $title, $msg, 'info', $link);

    $message = "Request #{$request_id} has been marked as {$new_status}.";

    echo json_encode([
        'success' => true,
        'message' => $message,
        'new_status' => $new_status
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
