<?php
/**
 * Donor Dashboard Stub for BDMS
 */

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/db.php';

// Guard role
checkRole(['donor']);

try {
    // Fetch latest user details from DB
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    // Calculate eligibility
    $last_donation = $user['last_donation_date'] ?? null;
    if ($last_donation) {
        $next_eligible_date = date('Y-m-d', strtotime($last_donation . ' + 90 days'));
        $today = date('Y-m-d');
        $is_eligible = ($today >= $next_eligible_date);
        $next_eligible_display = date('M d, Y', strtotime($next_eligible_date));
    } else {
        $is_eligible = true;
        $next_eligible_display = 'Eligible Now';
    }
    
    if (!$user) {
        // Fallback if user doesn't exist
        header('Location: logout.php');
        exit;
    }
} catch (Exception $e) {
    die("Error loading profile: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Dashboard — BDMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #f1f5f9;
            --nav-bg: #ffffff;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --primary: #dc2626; /* Crimson red for blood donation */
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 24px;
            width: 100%;
            flex: 1;
        }

        /* Header Area */
        .page-header {
            margin-bottom: 32px;
        }
        .page-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin: 0 0 8px 0;
            color: var(--text-main);
        }
        .page-header p {
            color: var(--text-muted);
            margin: 0;
            font-size: 16px;
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 24px;
            align-items: start;
        }
        @media (max-width: 900px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Cards */
        .card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow-sm);
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }
        .card-header h2 {
            font-size: 18px;
            margin: 0;
            font-weight: 600;
        }
        
        /* Profile Info Table */
        .profile-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .profile-list li {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }
        .profile-list li:last-child {
            border-bottom: none;
        }
        .profile-list .label {
            color: var(--text-muted);
            font-weight: 500;
        }
        .profile-list .value {
            font-weight: 600;
            color: var(--text-main);
            text-align: right;
        }
        .blood-badge {
            background: #fee2e2;
            color: #b91c1c;
            padding: 2px 8px;
            border-radius: 6px;
            font-family: 'JetBrains Mono', monospace;
            font-weight: 600;
            font-size: 13px;
        }

        /* Buttons */
        .btn {
            display: inline-block;
            background: var(--primary);
            color: #fff;
            padding: 10px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: background 0.2s;
            text-align: center;
        }
        .btn:hover {
            background: var(--primary-hover);
        }

        /* Placeholder Grids */
        .widgets-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        @media (max-width: 600px) {
            .widgets-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .stat-box {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
        .stat-box .number {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 4px;
        }
        .stat-box .label {
            font-size: 14px;
            color: var(--text-muted);
            font-weight: 500;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
        }
        .empty-state svg {
            width: 48px;
            height: 48px;
            fill: #cbd5e1;
            margin-bottom: 16px;
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
        <a href="donor-dashboard.php" class="active">Dashboard</a>
        <a href="donor-edit-profile.php">Edit Profile</a>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>

<!-- Main Content -->
<main class="container">
    <div class="page-header">
        <h1>Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>!</h1>
        <p>Manage your profile, track your donations, and save lives.</p>
    </div>

    <div class="dashboard-grid">
        
        <!-- Left Column: Profile Overview -->
        <div class="card">
            <div class="card-header">
                <h2>Profile Overview</h2>
                <a href="donor-edit-profile.php" class="btn" style="padding: 6px 12px; font-size: 13px;">Edit</a>
            </div>
            <ul class="profile-list">
                <li>
                    <span class="label">Blood Type</span>
                    <span class="value"><span class="blood-badge"><?php echo htmlspecialchars($user['blood_type']); ?></span></span>
                </div>
                <li>
                    <span class="label">Email</span>
                    <span class="value"><?php echo htmlspecialchars($user['email']); ?></span>
                </li>
                <li>
                    <span class="label">Phone</span>
                    <span class="value"><?php echo htmlspecialchars($user['phone']); ?></span>
                </li>
                <li>
                    <span class="label">Gender</span>
                    <span class="value"><?php echo ucfirst(htmlspecialchars($user['gender'])); ?></span>
                </li>
                <li>
                    <span class="label">Date of Birth</span>
                    <span class="value"><?php echo htmlspecialchars($user['date_of_birth']); ?></span>
                </li>
                <li>
                    <span class="label">Weight</span>
                    <span class="value"><?php echo htmlspecialchars($user['weight_kg']); ?> kg</span>
                </li>
                <li>
                    <span class="label">City</span>
                    <span class="value"><?php echo htmlspecialchars($user['city']); ?></span>
                </li>
                <li>
                    <span class="label">Address</span>
                    <span class="value"><?php echo htmlspecialchars($user['address'] ?? 'Not provided'); ?></span>
                </li>
                <li>
                    <span class="label">Status</span>
                    <span class="value"><?php echo $user['is_verified'] ? 'Verified ✅' : 'Unverified ❌'; ?></span>
                </li>
            </ul>
        </div>

        <!-- Right Column: Placeholder Widgets -->
        <div style="display: flex; flex-direction: column; gap: 24px;">
            
            <div class="widgets-grid">
                <div class="stat-box">
                    <div class="number">0</div>
                    <div class="label">Total Donations</div>
                </div>
                <div class="stat-box">
                    <div class="number" style="font-size: 22px; color: <?php echo $is_eligible ? '#15803d' : 'var(--primary)'; ?>;">
                        <?php echo htmlspecialchars($next_eligible_display); ?>
                    </div>
                    <div class="label">Next Eligible Date</div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>Recent Donation Requests</h2>
                </div>
                <div class="empty-state">
                    <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z"/></svg>
                    <p>No donation requests found in your city yet.</p>
                    <p style="font-size: 13px; color: #94a3b8; margin-top: 4px;">Feature coming soon...</p>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2>Donation History</h2>
                </div>
                <div class="empty-state">
                    <p>You haven't made any recorded donations yet.</p>
                </div>
            </div>

        </div>

    </div>
</main>

</body>
</html>