<?php
/**
 * Forgot Password Request Page for BDMS
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

$error = null;
$success = null;
$email = '';
$email_for_debug = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = "Security token mismatch. Please try again.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $email_for_debug = $email;
        
        if (empty($email)) {
            $error = "Please enter your email address.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            try {
                // Check if user exists
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
                $stmt->execute(['email' => $email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Generate reset token (random 32 byte hex, expires in 1 hour)
                    $token = bin2hex(random_bytes(32));
                    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    // Insert reset request
                    $stmt_ins = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at, used) VALUES (:user_id, :token, :expires_at, 0)");
                    $stmt_ins->execute([
                        'user_id' => $user['id'],
                        'token' => $token,
                        'expires_at' => $expires_at
                    ]);
                    
                    // Construct reset link
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
                    $host = $_SERVER['HTTP_HOST'];
                    $dir = dirname($_SERVER['PHP_SELF']);
                    $dir = ($dir === '\\' || $dir === '/') ? '' : $dir;
                    $reset_link = $protocol . $host . $dir . "/reset-password.php?token=" . $token;
                    
                    // Simulate email sending
                    $body = "Hi {$user['full_name']},\n\nWe received a request to reset your password for your BDMS account.\n";
                    $body .= "Please click the link below to set a new password:\n\n";
                    $body .= "{$reset_link}\n\n";
                    $body .= "This link will expire in 1 hour and can only be used once.\n\nIf you did not request this, please ignore this email.\n\nBest regards,\nBDMS System";
                    
                    log_simulated_email($email, "Reset Your BDMS Password", $body);
                }
                
                // Show generic message to prevent account enumeration
                $success = "If the email address matches an active account, a password reset link has been sent.";
                $email = ''; // Clear sticky input on success
                
            } catch (Exception $e) {
                $error = "Reset request failed: " . $e->getMessage();
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
    <title>Forgot Password — BDMS</title>
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>

<!-- Developer email simulation debug banner -->
<?php
if (isset($_SESSION['debug_email_link'])):
    $link_data = $_SESSION['debug_email_link'];
    preg_match('/https?:\/\/[^\s]+/', $link_data['body'], $matches);
    $link_url = $matches[0] ?? '#';
?>
    <div class="debug-banner">
        <div>
            <strong>[SIMULATED EMAIL]</strong> Sent to: <code><?php echo h($link_data['to']); ?></code><br>
            Subject: <em><?php echo h($link_data['subject']); ?></em><br>
            <a href="<?php echo h($link_url); ?>">Click here to reset password &rarr;</a>
        </div>
    </div>
    <?php unset($_SESSION['debug_email_link']); ?>
<?php endif; ?>

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
                <h1>Reset Password</h1>
                <p>Enter your email to request a reset link</p>
            </div>

            <!-- Error Alert -->
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

            <!-- Success Alert -->
            <?php if ($success): ?>
                <div class="alert-banner alert-success" style="flex-direction: column; align-items: flex-start; gap: 10px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 18px; height: 18px; flex: none;">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                        <span><?php echo h($success); ?></span>
                    </div>
                    <?php 
                    if (isset($_SESSION['debug_email_link']) && $_SESSION['debug_email_link']['to'] === $email_for_debug): 
                        $dbg = $_SESSION['debug_email_link'];
                        $lnk = $dbg['link'] ?? '';
                    ?>
                        <div style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed rgba(22, 101, 52, 0.3); width: 100%; font-size: 13px;">
                            <strong style="font-family: 'JetBrains Mono', monospace; text-transform: uppercase; color: var(--success);">[Dev Mode Link]</strong><br>
                            A simulated email was logged in <code>email_logs.txt</code>.<br>
                            <a href="<?php echo h($lnk); ?>" class="mono" style="color: var(--text-primary); font-weight: 600; text-decoration: underline; word-break: break-all; display: inline-block; margin-top: 6px;"><?php echo h($lnk); ?></a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <form action="forgot-password.php" method="POST" autocomplete="off">
                <?php echo csrf_input(); ?>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="<?php echo h($email); ?>" placeholder="e.g. rahim@example.com" required>
                </div>

                <button type="submit" class="btn-primary">Send Reset Link</button>
            </form>

            <!-- Footer Links -->
            <div class="auth-footer">
                Remember your password? <a href="login.php">Log In</a>
            </div>
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
