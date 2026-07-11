<?php
/**
 * API: Submit Blood Request — BDMS
 * Receives blood request form data and inserts into the database.
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

// Only recipients may submit blood requests
checkRole(['recipient']);

// Only accept POST + AJAX
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

header('Content-Type: application/json');

// CSRF verification
$csrf_token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Security token mismatch. Please reload and try again.']);
    exit;
}

// Collect & sanitize inputs
$blood_group    = trim($_POST['blood_group'] ?? '');
$quantity       = intval($_POST['quantity'] ?? 0);
$urgency        = trim($_POST['urgency'] ?? '');
$hospital_name  = trim($_POST['hospital_name'] ?? '');
$ward           = trim($_POST['ward'] ?? '');
$city           = trim($_POST['city'] ?? '');
$contact_person = trim($_POST['contact_person'] ?? '');
$contact_phone  = trim($_POST['contact_phone'] ?? '');
$contact_email  = trim($_POST['contact_email'] ?? '');
$notes          = trim($_POST['notes'] ?? '');

// Server-side validation
$errors = [];

$valid_blood_groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
if (!in_array($blood_group, $valid_blood_groups)) {
    $errors[] = 'Invalid blood group selected.';
}

if ($quantity < 1 || $quantity > 20) {
    $errors[] = 'Quantity must be between 1 and 20 units.';
}

$valid_urgencies = ['critical', 'urgent', 'standard'];
if (!in_array($urgency, $valid_urgencies)) {
    $errors[] = 'Invalid urgency level.';
}

if (empty($hospital_name)) {
    $errors[] = 'Hospital name is required.';
}

if (empty($city)) {
    $errors[] = 'City is required.';
}

if (empty($contact_person)) {
    $errors[] = 'Contact person name is required.';
}

if (!validate_phone($contact_phone)) {
    $errors[] = 'Invalid phone number. Must be a valid Bangladeshi number (01XXXXXXXXX).';
}

if (!empty($contact_email) && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email address.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['error' => implode(' ', $errors)]);
    exit;
}

// Insert into database
try {
    $stmt = $pdo->prepare("
        INSERT INTO blood_requests 
            (requester_id, blood_group, quantity, urgency, hospital_name, ward, city, contact_person, contact_phone, contact_email, notes, status)
        VALUES 
            (:requester_id, :blood_group, :quantity, :urgency, :hospital_name, :ward, :city, :contact_person, :contact_phone, :contact_email, :notes, 'pending')
    ");

    $stmt->execute([
        'requester_id'   => $_SESSION['user_id'],
        'blood_group'    => $blood_group,
        'quantity'        => $quantity,
        'urgency'         => $urgency,
        'hospital_name'   => $hospital_name,
        'ward'            => $ward,
        'city'            => $city,
        'contact_person'  => $contact_person,
        'contact_phone'   => $contact_phone,
        'contact_email'   => $contact_email,
        'notes'           => $notes,
    ]);

    $requestId = $pdo->lastInsertId();

    // Send confirmation notification
    if (!empty($contact_email)) {
        $subject = "Blood Request #$requestId Received";
        $body = "Dear $contact_person,\n\nWe have received your $urgency blood request for $quantity units of $blood_group blood at $hospital_name.\n\nTrack your request status here: http://localhost/Blood-Donation-Management-System-BDMS--main/request-status.php?id=$requestId\n\nThank you,\nBDMS Team";
        log_simulated_email($contact_email, $subject, $body);
    }

    echo json_encode([
        'success' => true,
        'message' => "Blood request #$requestId submitted successfully! We'll match you with available donors.",
        'request_id' => $requestId
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
