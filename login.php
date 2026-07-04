<?php
/**
 * User Login Page for BDMS
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

start_secure_session();

// If user is already logged in, redirect them to their dashboard
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'donor';
    header("Location: {$role}-dashboard.php");
    exit;
}

$error = null;
$success = get_flash_message('success'); // Check for success flash message (from registration/verification)
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = "Security token mismatch. Please try again.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $ip = get_user_ip();
        
        if (empty($email) || empty($password)) {
            $error = "Please enter both email and password.";
        } else {
            try {
                // 1. Rate-limit check (5+ failed attempts from this email OR IP in last 15 minutes)
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM login_attempts 
                    WHERE (email_or_ip = :email OR email_or_ip = :ip) 
                      AND success = 0 
                      AND attempted_at > NOW() - INTERVAL 15 MINUTE
                ");
                $stmt->execute(['email' => $email, 'ip' => $ip]);
                $failed_attempts = $stmt->fetchColumn();
                
                if ($failed_attempts >= 5) {
                    $error = "Too many failed login attempts. Account/IP blocked. Please try again in 15 minutes.";
                } else {
                    // 2. Look up user by email
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
                    $stmt->execute(['email' => $email]);
                    $user = $stmt->fetch();
                    
                    // 3. Verify credentials
                    if ($user && password_verify($password, $user['password_hash'])) {
                        // Account suspension check
                        if ($user['status'] === 'suspended') {
                            // Log failed login attempt (suspended)
                            $stmt_log = $pdo->prepare("INSERT INTO login_attempts (email_or_ip, success) VALUES (:email, 0)");
                            $stmt_log->execute(['email' => $email]);
                            
                            $error = "Your account is suspended. Please contact the administrator.";
                        } else {
                            // Log successful attempt
                            $stmt_log = $pdo->prepare("INSERT INTO login_attempts (email_or_ip, success) VALUES (:email, 1)");
                            $stmt_log->execute(['email' => $email]);
                            
                            // Regenerate session ID to prevent session fixation
                            session_regenerate_id(true);
                            
                            // Store user details in session
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['role'] = $user['role'];
                            $_SESSION['full_name'] = $user['full_name'];
                            
                            // Redirect user by role
                            $redirect_page = 'donor-dashboard.php';
                            if ($user['role'] === 'admin') {
                                $redirect_page = 'admin-dashboard.php';
                            } elseif ($user['role'] === 'recipient') {
                                $redirect_page = 'recipient-dashboard.php';
                            }
                            
                            header("Location: " . $redirect_page);
                            exit;
                        }
                    } else {
                        // 4. Log failure (log both email and IP for robust block checks)
                        $stmt_log = $pdo->prepare("INSERT INTO login_attempts (email_or_ip, success) VALUES (:email, 0)");
                        $stmt_log->execute(['email' => $email]);
                        
                        $stmt_log_ip = $pdo->prepare("INSERT INTO login_attempts (email_or_ip, success) VALUES (:ip, 0)");
                        $stmt_log_ip->execute(['ip' => $ip]);
                        
                        $error = "Invalid email or password.";
                    }
                }
            } catch (Exception $e) {
                $error = "Login error: " . $e->getMessage();
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
    <title>Log In — BDMS</title>
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
            <a href="<?php echo h($link_url); ?>">Click here to verify/reset link &rarr;</a>
        </div>
    </div>
    <?php unset($_SESSION['debug_email_link']); ?>
<?php endif; ?>

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
            <h1>Welcome Back</h1>
            <p>Log in to manage blood donations</p>
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
                if (isset($_SESSION['debug_email_link']) && strpos($_SESSION['debug_email_link']['subject'], 'Verify') !== false): 
                    $dbg = $_SESSION['debug_email_link'];
                    $lnk = $dbg['link'] ?? '';
                ?>
                    <div style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed rgba(46, 125, 107, 0.3); width: 100%; font-size: 13px;">
                        <strong style="font-family: 'JetBrains Mono', monospace; text-transform: uppercase; color: var(--teal);">[Dev Mode Link]</strong><br>
                        A simulated email was logged in <code>email_logs.txt</code>.<br>
                        <a href="<?php echo h($lnk); ?>" class="mono" style="color: var(--ink); font-weight: 600; text-decoration: underline; word-break: break-all; display: inline-block; margin-top: 6px;"><?php echo h($lnk); ?></a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form action="login.php" method="POST" autocomplete="off">
            <?php echo csrf_input(); ?>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="<?php echo h($email); ?>" placeholder="e.g. rahim@example.com" required>
            </div>

            <div class="form-group" style="position: relative;">
                <div style="display: flex; justify-content: space-between; align-items: baseline;">
                    <label for="password">Password</label>
                    <a href="forgot-password.php" style="font-size: 11.5px; color: var(--crimson); text-decoration: none; font-weight: 500;">Forgot?</a>
                </div>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>

            <button type="submit" class="btn-primary">Log In</button>
        </form>

        <!-- Footer Links -->
        <div class="auth-footer">
            Don't have an account? <a href="register.php">Register</a>
        </div>
    </div>
</div>

</body>
</html>
