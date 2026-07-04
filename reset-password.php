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

<!-- Watermark backdrop -->
<div class="bg-drops">
    <!-- SVG Definitions -->
    <svg width="0" height="0">
        <defs>
            <radialGradient id="dropGrad1" cx="35%" cy="28%" r="75%">
                <stop offset="0%" stop-color="#C4392F"/>
                <stop offset="60%" stop-color="#A8201A"/>
                <stop offset="100%" stop-color="#7A1712"/>
            </radialGradient>
            <radialGradient id="dropGrad2" cx="35%" cy="28%" r="75%">
                <stop offset="0%" stop-color="#B8302A"/>
                <stop offset="65%" stop-color="#8E1C16"/>
                <stop offset="100%" stop-color="#5E120D"/>
            </radialGradient>
        </defs>
    </svg>

    <!-- Faint floating blood drop shapes -->
    <svg style="top:-20px; right:4%;" width="160" height="205" viewBox="0 0 100 128" opacity="0.25">
        <path d="M50 4 C50 4 92 62 92 88 C92 110 73 124 50 124 C27 124 8 110 8 88 C8 62 50 4 50 4 Z" fill="url(#dropGrad1)"/>
    </svg>
    <svg style="top:250px; left:-40px;" width="130" height="166" viewBox="0 0 100 128" opacity="0.25" transform="rotate(-15)">
        <path d="M50 4 C50 4 92 62 92 88 C92 110 73 124 50 124 C27 124 8 110 8 88 C8 62 50 4 50 4 Z" fill="url(#dropGrad2)"/>
    </svg>
    <svg style="bottom:20px; right:10%;" width="120" height="154" viewBox="0 0 100 128" opacity="0.25" transform="rotate(8)">
        <path d="M50 4 C50 4 92 62 92 88 C92 110 73 124 50 124 C27 124 8 110 8 88 C8 62 50 4 50 4 Z" fill="url(#dropGrad1)"/>
    </svg>
</div>

<div class="auth-container">
    <div class="auth-card">
        <!-- Logo -->
        <div class="auth-logo">
            <svg viewBox="0 0 32 32" fill="none">
                <path d="M16 4C16 4 6 14.5 6 20.5C6 26.3 10.5 29 16 29C21.5 29 26 26.3 26 20.5C26 14.5 16 4 16 4Z" fill="#A8201A"/>
                <path d="M6 20.5 L12 20.5 L14.5 16 L17.5 25 L20 20.5 L26 20.5" stroke="#F7F4EF" stroke-width="1.6" fill="none" stroke-linejoin="round" stroke-linecap="round"/>
            </svg>
            <div class="brand-name">BDMS</div>
        </div>

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

</body>
</html>
