<?php
/**
 * Recipient Dashboard Stub for BDMS
 */

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/db.php';

// Guard role
checkRole(['recipient']);

try {
    // Fetch latest user details from DB
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
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
    <title>Recipient Dashboard — BDMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #181A1B;
            --crimson: #A8201A;
            --crimson-deep: #7A1712;
            --paper: #F7F4EF;
            --card: #FFFFFF;
            --rose: #F3DEDB;
            --teal: #2E7D6B;
            --teal-tint: #DCEEE8;
            --amber: #C97A1E;
            --amber-tint: #F6E6CF;
            --line: #E6E0D6;
            --muted: #7A756C;
            --radius: 14px;
            --shadow: 0 1px 2px rgba(24,26,27,0.04), 0 8px 24px -12px rgba(24,26,27,0.12);
        }
        * { box-sizing: border-box; }
        body {
            background: var(--paper);
            color: var(--ink);
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 40px 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .dashboard-card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            width: 100%;
            max-width: 500px;
            padding: 34px 28px;
            box-shadow: var(--shadow);
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--line);
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        h1 {
            font-family: 'Fraunces', serif;
            font-size: 24px;
            margin: 0;
        }
        .role-badge {
            font-family: 'JetBrains Mono', monospace;
            font-size: 10px;
            background: var(--amber-tint);
            color: var(--amber);
            padding: 3px 8px;
            border-radius: 20px;
            text-transform: uppercase;
            font-weight: 600;
        }
        .welcome-msg {
            margin: 0 0 20px;
            font-size: 15px;
            color: var(--muted);
        }
        .welcome-msg strong {
            color: var(--ink);
        }
        .profile-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        .profile-table th {
            text-align: left;
            font-family: 'JetBrains Mono', monospace;
            font-size: 10.5px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--muted);
            padding: 12px 0;
            border-bottom: 1px solid var(--line);
            font-weight: 500;
        }
        .profile-table td {
            padding: 12px 0;
            border-bottom: 1px solid var(--line);
            font-size: 13.5px;
            font-weight: 500;
        }
        .logout-btn {
            display: block;
            width: 100%;
            background: var(--ink);
            color: #fff;
            border: none;
            padding: 12px;
            border-radius: 9px;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: opacity 0.15s;
        }
        .logout-btn:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>

<div class="dashboard-card">
    <div class="header">
        <h1>Recipient Portal</h1>
        <span class="role-badge">Recipient</span>
    </div>
    
    <p class="welcome-msg">Welcome, <strong><?php echo h($user['full_name']); ?></strong>!</p>
    
    <table class="profile-table">
        <tr>
            <th>Organization/Name</th>
            <td><?php echo h($user['full_name']); ?></td>
        </tr>
        <tr>
            <th>Email Address</th>
            <td><?php echo h($user['email']); ?></td>
        </tr>
        <tr>
            <th>Phone Number</th>
            <td><?php echo h($user['phone']); ?></td>
        </tr>
        <tr>
            <th>Status</th>
            <td><?php echo ucfirst(h($user['status'])); ?></td>
        </tr>
        <tr>
            <th>Email Verified</th>
            <td><?php echo $user['is_verified'] ? 'Yes ✅' : 'No ❌'; ?></td>
        </tr>
    </table>
    
    <a href="logout.php" class="logout-btn">Log Out</a>
</div>

</body>
</html>
