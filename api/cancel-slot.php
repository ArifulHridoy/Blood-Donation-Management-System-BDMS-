<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$slot_id = $input['slot_id'] ?? null;

if (!$slot_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Slot ID is required']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Find the booking
    $stmt = $pdo->prepare("SELECT id, status FROM donation_bookings WHERE slot_id = :slot_id AND donor_id = :donor_id AND status = 'scheduled' FOR UPDATE");
    $stmt->execute([
        'slot_id' => $slot_id,
        'donor_id' => $_SESSION['user_id']
    ]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'No active booking found for this slot.']);
        exit;
    }

    // Cancel the booking
    $stmt = $pdo->prepare("UPDATE donation_bookings SET status = 'cancelled' WHERE id = :id");
    $stmt->execute(['id' => $booking['id']]);

    // Update the slot to decrement booked_count and ensure status is available
    $stmt = $pdo->prepare("SELECT * FROM donation_slots WHERE id = :id FOR UPDATE");
    $stmt->execute(['id' => $slot_id]);
    $slot = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($slot) {
        $new_count = max(0, $slot['booked_count'] - 1);
        $new_status = ($new_count < $slot['capacity']) ? 'available' : $slot['status'];

        $stmt = $pdo->prepare("UPDATE donation_slots SET booked_count = :count, status = :status WHERE id = :id");
        $stmt->execute([
            'count' => $new_count,
            'status' => $new_status,
            'id' => $slot_id
        ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Your appointment has been successfully cancelled.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error while cancelling.']);
}
