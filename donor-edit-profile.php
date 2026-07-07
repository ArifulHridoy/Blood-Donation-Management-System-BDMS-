<?php
/**
 * Donor Edit Profile for BDMS
 */

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/db.php';

// Guard role
checkRole(['donor']);

$error = null;
$success = null;

try {
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $blood_type = trim($_POST['blood_type'] ?? '');
        $date_of_birth = trim($_POST['date_of_birth'] ?? '');
        $weight_kg = trim($_POST['weight_kg'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $last_donation_date = trim($_POST['last_donation_date'] ?? '');
        $last_donation_date = !empty($last_donation_date) ? $last_donation_date : null;

        // Basic Validation
        if (empty($full_name) || empty($phone) || empty($blood_type) || empty($date_of_birth) || empty($weight_kg) || empty($gender) || empty($city)) {
            $error = "All fields except Address are required.";
        } else {
            // Check for duplicate phone (excluding current user)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = :phone AND id != :id");
            $stmt->execute(['phone' => $phone, 'id' => $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                $error = "Phone number already in use by another account.";
            } else {
                // Update user
                $updateStmt = $pdo->prepare("
                    UPDATE users 
                    SET full_name = :full_name, phone = :phone, blood_type = :blood_type, 
                        date_of_birth = :date_of_birth, weight_kg = :weight_kg, 
                        gender = :gender, city = :city, address = :address,
                        last_donation_date = :last_donation_date
                    WHERE id = :id
                ");
                $updateStmt->execute([
                    'full_name' => $full_name,
                    'phone' => $phone,
                    'blood_type' => $blood_type,
                    'date_of_birth' => $date_of_birth,
                    'weight_kg' => $weight_kg,
                    'gender' => $gender,
                    'city' => $city,
                    'address' => $address,
                    'last_donation_date' => $last_donation_date,
                    'id' => $_SESSION['user_id']
                ]);
                $success = "Profile updated successfully.";
            }
        }
    }

    // Fetch latest user details from DB
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header('Location: logout.php');
        exit;
    }
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile — BDMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #f1f5f9;
            --nav-bg: #ffffff;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --primary: #dc2626; /* Crimson red */
            --primary-hover: #b91c1c;
            --border: #e2e8f0;
            --radius: 16px;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        }
        * { box-sizing: border-box; }
        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Navbar */
        .navbar {
            background-color: var(--nav-bg);
            border-bottom: 1px solid var(--border);
            padding: 0 32px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .nav-brand {
            font-weight: 700;
            font-size: 20px;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .nav-brand svg {
            width: 24px;
            height: 24px;
            fill: currentColor;
        }
        .nav-links {
            display: flex;
            gap: 24px;
            align-items: center;
        }
        .nav-links a {
            text-decoration: none;
            color: var(--text-muted);
            font-weight: 500;
            font-size: 15px;
            transition: color 0.2s;
        }
        .nav-links a:hover, .nav-links a.active {
            color: var(--text-main);
        }
        .nav-links a.btn-logout {
            background-color: #f1f5f9;
            color: var(--text-main);
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
        }
        .nav-links a.btn-logout:hover {
            background-color: #e2e8f0;
        }

        /* Main Container */
        .container {
            max-width: 700px;
            margin: 0 auto;
            padding: 40px 24px;
            width: 100%;
            flex: 1;
        }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 32px;
            box-shadow: var(--shadow-sm);
        }
        .card-header {
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }
        .card-header h1 {
            font-size: 24px;
            margin: 0;
            font-weight: 700;
        }
        
        .alert {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
            font-weight: 500;
        }
        .alert-error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fca5a5;
        }
        .alert-success {
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #86efac;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-main);
        }
        .form-control {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 15px;
            background: #f8fafc;
            color: var(--text-main);
            transition: all 0.2s ease;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
        }
        .form-row {
            display: flex;
            gap: 20px;
        }
        .form-row > .form-group {
            flex: 1;
        }
        @media (max-width: 600px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }
        
        .btn-group {
            display: flex;
            gap: 16px;
            margin-top: 32px;
        }
        .btn {
            flex: 1;
            padding: 12px 24px;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            border: none;
            transition: all 0.2s ease;
        }
        .btn-primary {
            background: var(--primary);
            color: #fff;
        }
        .btn-primary:hover {
            background: var(--primary-hover);
        }
        .btn-secondary {
            background: #e2e8f0;
            color: var(--text-main);
        }
        .btn-secondary:hover {
            background: #cbd5e1;
        }
        
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            padding-right: 32px;
        }
    </style>
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar">
    <div class="nav-brand">
        <svg viewBox="0 0 32 32">
            <path d="M16 4C16 4 6 14.5 6 20.5C6 26.3 10.5 29 16 29C21.5 29 26 26.3 26 20.5C26 14.5 16 4 16 4Z"/>
        </svg>
        BDMS
    </div>
    <div class="nav-links">
        <a href="donor-dashboard.php">Dashboard</a>
        <a href="donor-edit-profile.php" class="active">Edit Profile</a>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>

<!-- Main Content -->
<main class="container">
    <div class="card">
        <div class="card-header">
            <h1>Edit Profile</h1>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="donor-edit-profile.php">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Blood Type</label>
                    <select name="blood_type" class="form-control" required>
                        <?php 
                        $blood_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                        foreach($blood_types as $bt) {
                            $selected = ($user['blood_type'] === $bt) ? 'selected' : '';
                            echo "<option value=\"$bt\" $selected>$bt</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Gender</label>
                    <select name="gender" class="form-control" required>
                        <option value="male" <?php echo ($user['gender'] === 'male') ? 'selected' : ''; ?>>Male</option>
                        <option value="female" <?php echo ($user['gender'] === 'female') ? 'selected' : ''; ?>>Female</option>
                        <option value="other" <?php echo ($user['gender'] === 'other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-control" value="<?php echo htmlspecialchars($user['date_of_birth']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Weight (kg)</label>
                    <input type="number" name="weight_kg" class="form-control" value="<?php echo htmlspecialchars($user['weight_kg']); ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>City</label>
                    <input type="text" name="city" class="form-control" value="<?php echo htmlspecialchars($user['city']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Last Donation Date</label>
                    <input type="date" name="last_donation_date" class="form-control" value="<?php echo htmlspecialchars($user['last_donation_date'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>Address</label>
                <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($user['address']); ?></textarea>
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="donor-dashboard.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>

</body>
</html>
