<?php
/**
 * User Registration Page for BDMS
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
$success = null;

// Keep inputs sticky on form errors
$full_name = '';
$email = '';
$phone = '';
$role = 'donor';
$blood_type = '';
$date_of_birth = '';
$weight_kg = '';
$gender = '';
$address = '';
$city = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = "Security token mismatch. Please try again.";
    } else {
        // Retrieve and trim inputs
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $role = trim($_POST['role'] ?? 'donor');
        
        // Donor specific inputs
        $blood_type = trim($_POST['blood_type'] ?? '');
        $date_of_birth = trim($_POST['date_of_birth'] ?? '');
        $weight_kg = trim($_POST['weight_kg'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        
        // 2. Validation Flow (Fail Fast)
        if (empty($full_name) || empty($email) || empty($phone) || empty($password) || empty($confirm_password) || empty($role)) {
            $error = "All basic fields (Full Name, Email, Phone, Password, Confirm Password, and Role) are required.";
        } 
        elseif ($role !== 'donor' && $role !== 'recipient') {
            $error = "Invalid role selection.";
        }
        // Validate donor specific fields if role = donor
        elseif ($role === 'donor' && (empty($blood_type) || empty($date_of_birth) || empty($weight_kg) || empty($gender) || empty($city))) {
            $error = "As a donor, you must provide Blood Type, Date of Birth, Weight, Gender, and City.";
        }
        // Email formatting check
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        }
        // Phone formatting check (Bangladeshi mobile number)
        elseif (!validate_phone($phone)) {
            $error = "Phone number must be a valid Bangladeshi mobile number (e.g., 017XXXXXXXX).";
        }
        // Password strength check
        elseif (!validate_password($password)) {
            $error = "Password must be at least 8 characters long and contain at least one letter and one number.";
        }
        // Password confirm match check
        elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        }
        // Age check for donors
        elseif ($role === 'donor' && !validate_age($date_of_birth, 18)) {
            $error = "You must be at least 18 years old to register as a donor.";
        }
        else {
            try {
                // Email duplicate check
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
                $stmt->execute(['email' => $email]);
                if ($stmt->fetch()) {
                    $error = "Email already in use. Please use a different email or log in.";
                } else {
                    // Phone duplicate check
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = :phone");
                    $stmt->execute(['phone' => $phone]);
                    if ($stmt->fetch()) {
                        $error = "Phone number already in use. Please check the number or use another.";
                    }
                }
                
                // 3. If no errors, process registration
                if ($error === null) {
                    $pdo->beginTransaction();
                    
                    // Build password hash
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Setup values for insert
                    $insert_blood = ($role === 'donor') ? $blood_type : null;
                    $insert_dob = ($role === 'donor') ? $date_of_birth : null;
                    $insert_weight = ($role === 'donor') ? (float)$weight_kg : null;
                    $insert_gender = ($role === 'donor') ? $gender : null;
                    $insert_address = ($role === 'donor') ? $address : null;
                    $insert_city = ($role === 'donor') ? $city : null;
                    
                    // Insert into users
                    $sql = "INSERT INTO users (full_name, email, phone, password_hash, role, blood_type, date_of_birth, weight_kg, gender, address, city, is_verified, status) 
                            VALUES (:full_name, :email, :phone, :password_hash, :role, :blood_type, :date_of_birth, :weight_kg, :gender, :address, :city, 0, 'active')";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        'full_name' => $full_name,
                        'email' => $email,
                        'phone' => $phone,
                        'password_hash' => $password_hash,
                        'role' => $role,
                        'blood_type' => $insert_blood,
                        'date_of_birth' => $insert_dob,
                        'weight_kg' => $insert_weight,
                        'gender' => $insert_gender,
                        'address' => $insert_address,
                        'city' => $insert_city
                    ]);
                    
                    $user_id = $pdo->lastInsertId();
                    
                    // Generate verification token
                    $verification_token = bin2hex(random_bytes(32));
                    $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
                    
                    // Insert into email_verifications
                    $stmt_ver = $pdo->prepare("INSERT INTO email_verifications (user_id, token, expires_at) VALUES (:user_id, :token, :expires_at)");
                    $stmt_ver->execute([
                        'user_id' => $user_id,
                        'token' => $verification_token,
                        'expires_at' => $expires_at
                    ]);
                    
                    // Construct verification link
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
                    $host = $_SERVER['HTTP_HOST'];
                    $dir = dirname($_SERVER['PHP_SELF']);
                    // Clean trailing slash
                    $dir = ($dir === '\\' || $dir === '/') ? '' : $dir;
                    $verify_link = $protocol . $host . $dir . "/verify-email.php?token=" . $verification_token;
                    
                    // Simulate email delivery
                    $body = "Hi {$full_name},\n\nThank you for registering at Blood Donation Management System (BDMS).\n";
                    $body .= "Please click the link below to verify your email address:\n\n";
                    $body .= "{$verify_link}\n\n";
                    $body .= "This link will expire in 24 hours.\n\nBest regards,\nBDMS System";
                    
                    log_simulated_email($email, "Verify Your BDMS Account", $body);
                    
                    // Trigger in-app notification
                    require_once __DIR__ . '/includes/notification_service.php';
                    add_notification(
                        $user_id,
                        "Welcome to BDMS!",
                        "Thank you for registering. Please verify your email using the link sent to your inbox to unlock all features.",
                        'success'
                    );

                    $pdo->commit();
                    
                    set_flash_message('success', 'Registration successful! A verification link has been sent to your email. Please click it to verify your account.');
                    header('Location: login.php');
                    exit;
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "Registration failed: " . $e->getMessage();
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
    <title>Register — BDMS</title>
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>

<div class="split-container">
    <!-- Left Column: Form Panel -->
    <div class="form-panel" style="justify-content: flex-start; padding-top: 40px; padding-bottom: 40px; overflow-y: auto;">
        <div class="auth-wrapper" style="max-width: 480px;">
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
                <h1>Create Account</h1>
                <p>Join the Khulna Blood Donation Network</p>
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

            <!-- Form -->
            <form action="register.php" method="POST" autocomplete="off">
                <?php echo csrf_input(); ?>
                
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" value="<?php echo h($full_name); ?>" placeholder="e.g. Rahim Ahmed" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="<?php echo h($email); ?>" placeholder="e.g. rahim@example.com" required>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="phone" id="phone" name="phone" value="<?php echo h($phone); ?>" placeholder="e.g. 01712345678" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Min. 8 characters" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat password" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="role">Register As</label>
                    <select id="role" name="role" required>
                        <option value="donor" <?php echo ($role === 'donor') ? 'selected' : ''; ?>>Blood Donor</option>
                        <option value="recipient" <?php echo ($role === 'recipient') ? 'selected' : ''; ?>>Recipient (Hospital/Individual)</option>
                    </select>
                </div>

                <!-- Donor Specific Fields -->
                <div id="donor-fields">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="blood_type">Blood Type</label>
                            <select id="blood_type" name="blood_type">
                                <option value="">Select Type</option>
                                <option value="A+" <?php echo ($blood_type === 'A+') ? 'selected' : ''; ?>>A+</option>
                                <option value="A-" <?php echo ($blood_type === 'A-') ? 'selected' : ''; ?>>A-</option>
                                <option value="B+" <?php echo ($blood_type === 'B+') ? 'selected' : ''; ?>>B+</option>
                                <option value="B-" <?php echo ($blood_type === 'B-') ? 'selected' : ''; ?>>B-</option>
                                <option value="AB+" <?php echo ($blood_type === 'AB+') ? 'selected' : ''; ?>>AB+</option>
                                <option value="AB-" <?php echo ($blood_type === 'AB-') ? 'selected' : ''; ?>>AB-</option>
                                <option value="O+" <?php echo ($blood_type === 'O+') ? 'selected' : ''; ?>>O+</option>
                                <option value="O-" <?php echo ($blood_type === 'O-') ? 'selected' : ''; ?>>O-</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender">
                                <option value="">Select Gender</option>
                                <option value="male" <?php echo ($gender === 'male') ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo ($gender === 'female') ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo ($gender === 'other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" value="<?php echo h($date_of_birth); ?>">
                        </div>
                        <div class="form-group">
                            <label for="weight_kg">Weight (kg)</label>
                            <input type="number" step="0.1" id="weight_kg" name="weight_kg" value="<?php echo h($weight_kg); ?>" placeholder="e.g. 68.5">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="city">City</label>
                            <input type="text" id="city" name="city" value="<?php echo h($city); ?>" placeholder="e.g. Khulna">
                        </div>
                        <div class="form-group">
                            <label for="address">Address (Optional)</label>
                            <input type="text" id="address" name="address" value="<?php echo h($address); ?>" placeholder="e.g. Boyra Main Road">
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-primary">Register</button>
            </form>

            <!-- Footer Links -->
            <div class="auth-footer">
                Already have an account? <a href="login.php">Log In</a>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('role');
    const donorFields = document.getElementById('donor-fields');
    
    function toggleDonorFields() {
        if (roleSelect.value === 'donor') {
            donorFields.classList.add('visible');
            // Enable required attributes on donor fields
            document.querySelectorAll('#donor-fields select, #donor-fields input').forEach(el => {
                if (el.id !== 'address') { 
                    el.setAttribute('required', 'required');
                }
            });
        } else {
            donorFields.classList.remove('visible');
            // Disable required attributes
            document.querySelectorAll('#donor-fields select, #donor-fields input').forEach(el => {
                el.removeAttribute('required');
            });
        }
    }
    
    roleSelect.addEventListener('change', toggleDonorFields);
    // Run once on page load to set initial state based on default value or sticky input
    toggleDonorFields();
});
</script>
</body>
</html>
