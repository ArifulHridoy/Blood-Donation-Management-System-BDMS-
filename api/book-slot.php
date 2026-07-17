<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/notification_service.php';

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

    // Check if slot exists and has capacity
    $stmt = $pdo->prepare("SELECT * FROM donation_slots WHERE id = :id FOR UPDATE");
    $stmt->execute(['id' => $slot_id]);
    $slot = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$slot || $slot['status'] !== 'available' || $slot['booked_count'] >= $slot['capacity']) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Slot is not available or full.']);
        exit;
    }

    // Check donor eligibility (minimum gap of 56 days)
    $stmt = $pdo->prepare("SELECT last_donation_date FROM users WHERE id = :id FOR SHARE");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && !empty($user['last_donation_date'])) {
        $last_donation = new DateTime($user['last_donation_date']);
        $slot_date = new DateTime($slot['slot_date']);
        $interval = $last_donation->diff($slot_date);
        
        // If slot date is before or equal to last donation date, or the gap is less than 56 days
        if ($interval->invert == 1 || $interval->days < 56) {
            $pdo->rollBack();
            $eligible_date = $last_donation->modify('+56 days')->format('M d, Y');
            echo json_encode(['success' => false, 'message' => 'You are not eligible to donate on this date. You can donate again on or after ' . $eligible_date . '.']);
            exit;
        }
    }

    // Check if already booked by this user
    $stmt = $pdo->prepare("SELECT id FROM donation_bookings WHERE slot_id = :slot_id AND donor_id = :donor_id AND status = 'scheduled'");
    $stmt->execute([
        'slot_id' => $slot_id,
        'donor_id' => $_SESSION['user_id']
    ]);
    if ($stmt->fetch()) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'You have already booked this slot.']);
        exit;
    }

    // Book the slot
    $stmt = $pdo->prepare("INSERT INTO donation_bookings (slot_id, donor_id) VALUES (:slot_id, :donor_id)");
    $stmt->execute([
        'slot_id' => $slot_id,
        'donor_id' => $_SESSION['user_id']
    ]);

    // Update booked_count
    $new_count = $slot['booked_count'] + 1;
    $new_status = ($new_count >= $slot['capacity']) ? 'full' : 'available';

    $stmt = $pdo->prepare("UPDATE donation_slots SET booked_count = :count, status = :status WHERE id = :id");
    $stmt->execute([
        'count' => $new_count,
        'status' => $new_status,
        'id' => $slot_id
    ]);

    $pdo->commit();

    // Trigger in-app notifications
    // 1. Notify the donor
    $title = "Donation Appointment Booked";
    $msg = "You successfully booked a donation slot on " . date('M d, Y', strtotime($slot['slot_date'])) . " at " . date('h:i A', strtotime($slot['start_time'])) . ".";
    add_notification($_SESSION['user_id'], $title, $msg, 'success', 'donor-dashboard.php');

    // 2. Notify all active admins
    $adminStmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'");
    $admins = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($admins as $adminId) {
        $donorName = $_SESSION['user_name'] ?? 'A donor';
        add_notification($adminId, "New Booking Alert", "{$donorName} booked a slot for " . date('M d, Y', strtotime($slot['slot_date'])) . ".", 'info', 'admin-dashboard.php');
    }

    echo json_encode(['success' => true, 'message' => 'Slot booked successfully.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error while booking.']);
}
