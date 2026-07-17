<?php
/**
 * Admin Dashboard Stub for BDMS
 */

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/db.php';

// Guard role
checkRole(['admin']);

try {
    // 1. Fetch latest admin details from DB
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        header('Location: logout.php');
        exit;
    }
    
    // 2. Fetch stats
    $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $total_donors = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'donor'")->fetchColumn();
    $total_recipients = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'recipient'")->fetchColumn();
    $failed_attempts = $pdo->query("SELECT COUNT(*) FROM login_attempts WHERE success = 0")->fetchColumn();
    
    // 3. Fetch latest users
    $stmt_users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 10");
    $users = $stmt_users->fetchAll();
    
} catch (Exception $e) {
    die("Error loading admin console: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel — BDMS</title>
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
            padding: 30px;
            min-height: 100vh;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            border-bottom: 1px solid var(--line);
            padding-bottom: 14px;
        }
        h1 {
            font-family: 'Fraunces', serif;
            font-size: 26px;
            margin: 0;
        }
        .admin-meta {
            font-size: 13.5px;
            color: var(--muted);
        }
        .admin-meta strong {
            color: var(--ink);
        }
        .role-badge {
            font-family: 'JetBrains Mono', monospace;
            font-size: 10px;
            background: var(--rose);
            color: var(--crimson-deep);
            padding: 3px 8px;
            border-radius: 20px;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }
        .stat-card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 18px;
            box-shadow: var(--shadow);
        }
        .stat-card .label {
            font-family: 'JetBrains Mono', monospace;
            font-size: 10.5px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--muted);
        }
        .stat-card .value {
            font-family: 'Fraunces', serif;
            font-size: 28px;
            font-weight: 600;
            margin-top: 6px;
        }
        
        /* Layout section */
        .section-card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 24px;
        }
        .section-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--line);
        }
        .section-header h2 {
            font-family: 'Fraunces', serif;
            font-size: 18px;
            margin: 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            text-align: left;
            font-family: 'JetBrains Mono', monospace;
            font-size: 10.5px;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--muted);
            font-weight: 500;
            padding: 12px 20px;
            border-bottom: 1px solid var(--line);
        }
        td {
            padding: 12px 20px;
            border-bottom: 1px solid var(--line);
            font-size: 13.5px;
        }
        tr:last-child td {
            border-bottom: none;
        }
        
        .role-chip {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 4px;
        }
        .role-donor { background: var(--teal-tint); color: var(--teal); }
        .role-recipient { background: var(--amber-tint); color: var(--amber); }
        .role-admin { background: var(--rose); color: var(--crimson-deep); }
        
        .status-badge {
            font-size: 11.5px;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: 12px;
        }
        .status-active { background: var(--teal-tint); color: var(--teal); }
        .status-suspended { background: var(--rose); color: var(--crimson-deep); }
        
        .logout-link {
            display: inline-block;
            background: var(--ink);
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            text-decoration: none;
            transition: opacity 0.15s;
        }
        .logout-link:hover { opacity: 0.9; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div>
            <h1>Admin Panel</h1>
            <div class="admin-meta">Logged in as: <strong><?php echo h($admin['full_name']); ?></strong></div>
        </div>
        <div style="display: flex; gap: 15px; align-items: center;">
            <a href="donor-search.php" class="logout-link" style="background: var(--crimson);">Search Donors</a>
            <?php include_once __DIR__ . '/includes/notification_bell.php'; ?>
            <a href="logout.php" class="logout-link">Log Out</a>
        </div>
    </div>
    
    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="label">Total Accounts</div>
            <div class="value"><?php echo $total_users; ?></div>
        </div>
        <div class="stat-card">
            <div class="label">Total Donors</div>
            <div class="value"><?php echo $total_donors; ?></div>
        </div>
        <div class="stat-card">
            <div class="label">Total Recipients</div>
            <div class="value"><?php echo $total_recipients; ?></div>
        </div>
        <div class="stat-card">
            <div class="label">Blocked Attempts</div>
            <div class="value"><?php echo $failed_attempts; ?></div>
        </div>
    </div>
    
    <!-- User List section -->
    <div class="section-card">
        <div class="section-header">
            <h2>Registered Users</h2>
        </div>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Blood Grp</th>
                        <th>Eligible</th>
                        <th>Verified</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--muted); padding: 20px;">No registered users found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><strong><?php echo h($u['full_name']); ?></strong></td>
                                <td><?php echo h($u['email']); ?></td>
                                <td class="mono"><?php echo h($u['phone']); ?></td>
                                <td>
                                    <span class="role-chip role-<?php echo h($u['role']); ?>">
                                        <?php echo ucfirst(h($u['role'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($u['role'] === 'donor' && !empty($u['blood_type'])): ?>
                                        <span style="font-weight: 600; color: var(--crimson);"><?php echo h($u['blood_type']); ?></span>
                                    <?php else: ?>
                                        <span style="color: var(--muted);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($u['role'] === 'donor') {
                                        $last_don = $u['last_donation_date'] ?? null;
                                        if ($last_don) {
                                            $next_date = date('Y-m-d', strtotime($last_don . ' + 90 days'));
                                            $today = date('Y-m-d');
                                            echo ($today >= $next_date) ? '<span style="color: #15803d; font-weight: 600;">Yes</span>' : '<span style="color: var(--crimson); font-weight: 600;">No</span>';
                                        } else {
                                            echo '<span style="color: #15803d; font-weight: 600;">Yes</span>';
                                        }
                                    } else {
                                        echo '<span style="color: var(--muted);">-</span>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo $u['is_verified'] ? 'Yes' : 'No'; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo h($u['status']); ?>">
                                        <?php echo ucfirst(h($u['status'])); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>
