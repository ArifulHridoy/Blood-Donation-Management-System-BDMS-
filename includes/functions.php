<?php
/**
 * Core Helper and Security Functions for BDMS
 */

// Start secure session helper
function start_secure_session() {
    if (session_status() === PHP_SESSION_NONE) {
        // Enforce cookie HTTPOnly and Strict Mode for session security
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_strict_mode', 1);
        
        // If running over HTTPS, enforce secure cookie (allow HTTP for local XAMPP setup)
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            ini_set('session.cookie_secure', 1);
        }
        
        session_start();
    }
}

// Output Escaping (XSS Prevention)
function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// Generate CSRF Token
function generate_csrf_token() {
    start_secure_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF Token
function verify_csrf_token($token) {
    start_secure_session();
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Generate Hidden CSRF Input Field
function csrf_input() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . h($token) . '">';
}

// Validate Bangladeshi Phone Number
function validate_phone($phone) {
    return preg_match('/^01[3-9]\d{8}$/', $phone) === 1;
}

// Validate Password Strength
function validate_password($password) {
    // Password >= 8 characters, at least 1 letter, and 1 number
    return (strlen($password) >= 8 && preg_match('/[a-zA-Z]/', $password) && preg_match('/\d/', $password));
}

// Validate Age is >= 18
function validate_age($dob_string, $min_age = 18) {
    try {
        $dob = new DateTime($dob_string);
        $today = new DateTime();
        
        // Calculate age
        $age = $today->diff($dob)->y;
        
        // If date of birth is in future or age is too low, return false
        if ($dob > $today || $age < $min_age) {
            return false;
        }
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Log/Simulate Email Delivery
function log_simulated_email($to, $subject, $body) {
    $log_file = dirname(__DIR__) . '/email_logs.txt';
    $timestamp = date('Y-m-d H:i:s');
    
    $log_content = "==================================================\n";
    $log_content .= "TIMESTAMP: {$timestamp}\n";
    $log_content .= "TO       : {$to}\n";
    $log_content .= "SUBJECT  : {$subject}\n";
    $log_content .= "--------------------------------------------------\n";
    $log_content .= "{$body}\n";
    $log_content .= "==================================================\n\n";
    
    file_put_contents($log_file, $log_content, FILE_APPEND);
    
    // Extract link from body
    $link = '';
    if (preg_match('/https?:\/\/[^\s]+/', $body, $matches)) {
        $link = trim($matches[0]);
    }
    
    // Store in session so we can display a developer debug banner on the next page load
    $_SESSION['debug_email_link'] = [
        'to' => $to,
        'subject' => $subject,
        'body' => $body,
        'link' => $link
    ];
}

// Flash Messages
function set_flash_message($type, $message) {
    start_secure_session();
    $_SESSION['flash'][$type] = $message;
}

// Retrieve flash message (if it exists) and clear it from session
function get_flash_message($type) {
    start_secure_session();
    if (isset($_SESSION['flash'][$type])) {
        $message = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $message;
    }
    return null;
}

function has_flash_message($type) {
    start_secure_session();
    return isset($_SESSION['flash'][$type]);
}

// Get User IP Address
function get_user_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}
