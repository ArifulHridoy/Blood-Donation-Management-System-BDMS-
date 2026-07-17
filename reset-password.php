<?php
/**
 * Password Reset Submission Page for BDMS
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

start_secure_session();

// Redirect logged-in users
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'donor';
    header("Location: {$role}-dashboard.php");
    exit;
}

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = null;
$valid_token = false;
$reset_record = null;

if (empty($token)) {
    $error = "Password reset token is missing.";
} else {
    try {
        // Validate token (not expired, not used)
        $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = :token AND expires_at > :now AND used = 0 LIMIT 1");
        $stmt->execute([
            'token' => $token,
            'now' => date('Y-m-d H:i:s')
        ]);
        $reset_record = $stmt->fetch();
        
        if ($reset_record) {
            $valid_token = true;
        } else {
            $error = "Invalid or expired password reset link.";
        }
    } catch (Exception $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    // Verify CSRF
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = "Security token mismatch. Please try again.";
    } else {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Form validations
        if (empty($password) || empty($confirm_password)) {
            $error = "Please fill in all fields.";
        } elseif (!validate_password($password)) {
            $error = "Password must be at least 8 characters long and contain at least one letter and one number.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            try {
                $pdo->beginTransaction();
                
                // Hash the new password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // Update user password
                $stmt_upd = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :user_id");
                $stmt_upd->execute([
                    'hash' => $password_hash,
                    'user_id' => $reset_record['user_id']
                ]);
                
                // Mark token as used
                $stmt_use = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = :id");
                $stmt_use->execute(['id' => $reset_record['id']]);
                
                $pdo->commit();
                
                set_flash_message('success', 'Password reset successful! You can now log in with your new password.');
                header('Location: login.php');
                exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "Failed to reset password: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password — BDMS</title>
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>

<div class="split-container">
    <!-- Left Column: Form Panel -->
    <div class="form-panel">
        <div class="auth-wrapper">
            <!-- Logo -->
            <a href="login.php" class="auth-logo">
                <svg viewBox="0 0 32 32" fill="none">
                    <path d="M16 4C16 4 6 14.5 6 20.5C6 26.3 10.5 29 16 29C21.5 29 26 26.3 26 20.5C26 14.5 16 4 16 4Z" fill="var(--primary)"/>
                    <path d="M6 20.5 L12 20.5 L14.5 16 L17.5 25 L20 20.5 L26 20.5" stroke="#FFFFFF" stroke-width="1.6" fill="none" stroke-linejoin="round" stroke-linecap="round"/>
                </svg>
                <div class="brand-name">BDMS</div>
            </a>

            <!-- Header -->
            <div class="auth-header">
                <h1>New Password</h1>
                <p>Define a secure password for your account</p>
            </div>

            <!-- Error Alert -->
            <?php if ($error && !$valid_token): ?>
                <!-- Severe block error (Token invalid or expired) -->
                <div class="alert-banner alert-error" style="margin-bottom: 24px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <span><?php echo h($error); ?></span>
                </div>
                <div class="auth-footer" style="margin-top: 10px;">
                    Go back to <a href="login.php">Log In</a> or request a <a href="forgot-password.php">New Reset Link</a>.
                </div>
            <?php else: ?>
                <!-- Form field error -->
                <?php if ($error): ?>
                    <div class="alert-banner alert-error">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                        <span><?php echo h($error); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Form -->
                <form action="reset-password.php" method="POST" autocomplete="off">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="token" value="<?php echo h($token); ?>">
                    
                    <div class="form-group">
                        <label for="password">New Password</label>
                        <input type="password" id="password" name="password" placeholder="Min. 8 characters with 1 letter & 1 number" required>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your new password" required>
                    </div>

                    <button type="submit" class="btn-primary">Reset Password</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Media Panel -->
    <div class="media-panel">
        <div class="media-content">
            <h2>Your donation can save up to three lives.</h2>
            <p>Every day, hospitals and clinics require blood units for surgeries, cancer treatments, and emergency rescues. Join our network of life savers today.</p>
            <div class="fact-pill">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex:none;">
                    <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                </svg>
                <span>Free health screening included with every donation</span>
            </div>
        </div>
    </div>
</div>

</body>
</html>
