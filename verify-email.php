<?php
/**
 * Email Verification Handler for BDMS
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

start_secure_session();

$token = trim($_GET['token'] ?? '');

if (empty($token)) {
    set_flash_message('error', 'Verification token is missing.');
    header('Location: login.php');
    exit;
}

try {
    // 1. Look up token in database
    $stmt = $pdo->prepare("SELECT * FROM email_verifications WHERE token = :token LIMIT 1");
    $stmt->execute(['token' => $token]);
    $verification = $stmt->fetch();
    
    if (!$verification) {
        set_flash_message('error', 'Invalid email verification link.');
        header('Location: login.php');
        exit;
    }
    
    // 2. Check if token has expired
    $expires_at = new DateTime($verification['expires_at']);
    $now = new DateTime();
    
    if ($now > $expires_at) {
        // Token has expired. Clean up the expired token.
        $stmt_del = $pdo->prepare("DELETE FROM email_verifications WHERE id = :id");
        $stmt_del->execute(['id' => $verification['id']]);
        
        set_flash_message('error', 'Your verification link has expired. Please register again.');
        header('Location: login.php');
        exit;
    }
    
    // 3. Update user and delete token inside a transaction
    $pdo->beginTransaction();
    
    // Update user to verified
    $stmt_user = $pdo->prepare("UPDATE users SET is_verified = 1 WHERE id = :user_id");
    $stmt_user->execute(['user_id' => $verification['user_id']]);
    
    // Delete token
    $stmt_del = $pdo->prepare("DELETE FROM email_verifications WHERE id = :id");
    $stmt_del->execute(['id' => $verification['id']]);
    
    $pdo->commit();
    
    set_flash_message('success', 'Email verified successfully! You can now log in.');
    header('Location: login.php');
    exit;
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash_message('error', 'An error occurred during verification: ' . $e->getMessage());
    header('Location: login.php');
    exit;
}
