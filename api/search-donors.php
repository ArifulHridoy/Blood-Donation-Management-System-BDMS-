<?php
/**
 * API: Search Donors
 * Endpoint: GET /api/search-donors.php
 * Content-Type: application/json
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// 1. Authorization Check
start_secure_session();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'recipient'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access. Only authenticated admins and recipients can query this endpoint.'
    ]);
    exit;
}

// 2. HTTP Method Validation
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed. Use GET requests only.'
    ]);
    exit;
}

// 3. Retrieve and Sanitize Query Parameters
$blood_type = trim($_GET['blood_type'] ?? '');
$location = trim($_GET['location'] ?? '');
$availability = trim($_GET['availability'] ?? 'all'); // '1' = available, '0' = unavailable, 'all' = no filter

// 4. Construct SQL Query dynamically
try {
    $sql = "SELECT id, full_name, email, phone, blood_type, date_of_birth, weight_kg, gender, address, city, last_donation_date, is_verified, status,
                   DATEDIFF(CURDATE(), last_donation_date) AS days_since_last_donation,
                   DATE_ADD(last_donation_date, INTERVAL 56 DAY) AS next_eligible_date
            FROM users 
            WHERE role = 'donor'";
    $where_clauses = [];
    $params = [];
    
    // Blood Type filter
    if (!empty($blood_type)) {
        $where_clauses[] = "blood_type = :blood_type";
        $params['blood_type'] = $blood_type;
    }
    
    // Location filter (searches both city and address)
    if (!empty($location)) {
        $where_clauses[] = "(city LIKE :location_city OR address LIKE :location_address)";
        $params['location_city'] = '%' . $location . '%';
        $params['location_address'] = '%' . $location . '%';
    }
    
    // Availability filter
    if ($availability === '1') {
        // Available: active status, email verified, and either no previous donations OR > 56 days since last donation
        $where_clauses[] = "status = 'active'";
        $where_clauses[] = "is_verified = 1";
        $where_clauses[] = "(last_donation_date IS NULL OR last_donation_date <= DATE_SUB(CURDATE(), INTERVAL 56 DAY))";
    } elseif ($availability === '0') {
        // Unavailable: suspended OR unverified OR has donated in the last 56 days
        $where_clauses[] = "(status = 'suspended' OR is_verified = 0 OR (last_donation_date IS NOT NULL AND last_donation_date > DATE_SUB(CURDATE(), INTERVAL 56 DAY)))";
    }
    
    // Combine filters
    if (!empty($where_clauses)) {
        $sql .= " AND " . implode(" AND ", $where_clauses);
    }
    
    $sql .= " ORDER BY full_name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $raw_donors = $stmt->fetchAll();
    
    // 5. Build Rich JSON Results Model in PHP
    $donors = [];
    $now_timestamp = time();
    
    foreach ($raw_donors as $d) {
        // Compute age
        $age = null;
        if (!empty($d['date_of_birth'])) {
            $dob = new DateTime($d['date_of_birth']);
            $today = new DateTime();
            $age = $today->diff($dob)->y;
        }
        
        // Compute donation availability rules
        $is_active = ($d['status'] === 'active');
        $is_verified = ((int)$d['is_verified'] === 1);
        
        $days_since_last_donation = $d['days_since_last_donation'] !== null ? (int)$d['days_since_last_donation'] : null;
        $next_eligible_date = $d['next_eligible_date'];
        
        $has_no_recent_donation = ($days_since_last_donation === null || $days_since_last_donation >= 56);
        $is_available = ($is_active && $is_verified && $has_no_recent_donation);
        
        // Assemble donor entity response
        $donors[] = [
            'id' => (int)$d['id'],
            'full_name' => $d['full_name'],
            'email' => $d['email'],
            'phone' => $d['phone'],
            'blood_type' => $d['blood_type'],
            'date_of_birth' => $d['date_of_birth'],
            'weight_kg' => $d['weight_kg'] !== null ? (float)$d['weight_kg'] : null,
            'gender' => $d['gender'],
            'address' => $d['address'],
            'city' => $d['city'],
            'last_donation_date' => $d['last_donation_date'],
            'is_verified' => (int)$d['is_verified'],
            'status' => $d['status'],
            'age' => $age,
            'is_available' => $is_available,
            'days_since_last_donation' => $days_since_last_donation,
            'next_eligible_date' => $next_eligible_date
        ];
    }
    
    // Output standard JSON response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'count' => count($donors),
        'donors' => $donors
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred on the server: ' . $e->getMessage()
    ]);
}
