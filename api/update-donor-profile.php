<?php
/**
 * API: Update Donor Profile
 * Endpoint: POST /api/update-donor-profile.php
 * Content-Type: application/json
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// 1. Authorization
start_secure_session();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'donor') {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access. Only authenticated donors can perform this action.'
    ]);
    exit;
}

// 2. Accept POST requests only
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed. Use POST or PUT.'
    ]);
    exit;
}

// 3. Retrieve Data (Support both JSON payload and standard form data)
$input_data = [];
$content_type = $_SERVER["CONTENT_TYPE"] ?? '';

if (strpos($content_type, 'application/json') !== false) {
    $raw_input = file_get_contents('php://input');
    $input_data = json_decode($raw_input, true) ?? [];
} else {
    $input_data = $_POST;
}

// 4. Input Sanitization & Preparation
$full_name = trim($input_data['full_name'] ?? '');
$phone = trim($input_data['phone'] ?? '');
$blood_type = trim($input_data['blood_type'] ?? '');
$date_of_birth = trim($input_data['date_of_birth'] ?? '');
$weight_kg = trim($input_data['weight_kg'] ?? '');
$gender = trim($input_data['gender'] ?? '');
$city = trim($input_data['city'] ?? '');
$address = trim($input_data['address'] ?? '');

// 5. Validation
$errors = [];

if (empty($full_name)) $errors[] = "Full name is required.";

if (empty($phone)) {
    $errors[] = "Phone number is required.";
} elseif (!validate_phone($phone)) {
    $errors[] = "Phone number must be a valid Bangladeshi mobile number (e.g., 017XXXXXXXX).";
}

$valid_blood_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
if (empty($blood_type) || !in_array($blood_type, $valid_blood_types)) {
    $errors[] = "A valid blood type is required.";
}

if (empty($date_of_birth)) {
    $errors[] = "Date of birth is required.";
} elseif (!validate_age($date_of_birth, 18)) {
    $errors[] = "You must be at least 18 years old to be a donor.";
}

if (empty($weight_kg) || !is_numeric($weight_kg) || $weight_kg < 45) {
    $errors[] = "Weight must be at least 45 kg.";
}

$valid_genders = ['male', 'female', 'other'];
if (empty($gender) || !in_array(strtolower($gender), $valid_genders)) {
    $errors[] = "A valid gender is required.";
}

if (empty($city)) $errors[] = "City is required.";

// 6. Return Validation Errors if any
if (!empty($errors)) {
    http_response_code(422); // Unprocessable Entity
    echo json_encode([
        'success' => false,
        'message' => 'Validation failed.',
        'errors' => $errors
    ]);
    exit;
}

try {
    // 7. Check for duplicate phone (must not belong to someone else)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = :phone AND id != :id LIMIT 1");
    $stmt->execute(['phone' => $phone, 'id' => $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        http_response_code(409); // Conflict
        echo json_encode([
            'success' => false,
            'message' => 'Phone number is already registered to another user.'
        ]);
        exit;
    }

    // 8. Execute Database Update
    $updateStmt = $pdo->prepare("
        UPDATE users 
        SET full_name = :full_name, phone = :phone, blood_type = :blood_type, 
            date_of_birth = :date_of_birth, weight_kg = :weight_kg, 
            gender = :gender, city = :city, address = :address,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");

    $updated = $updateStmt->execute([
        'full_name' => $full_name,
        'phone' => $phone,
        'blood_type' => $blood_type,
        'date_of_birth' => $date_of_birth,
        'weight_kg' => $weight_kg,
        'gender' => strtolower($gender),
        'city' => $city,
        'address' => $address,
        'id' => $_SESSION['user_id']
    ]);

    if ($updated) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Profile updated successfully.'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update profile. Please try again.'
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred.',
        // Don't expose detailed error in production, but okay for development/API
        'error_details' => $e->getMessage() 
    ]);
}
