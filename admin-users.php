<?php
/**
 * User Accounts Management Panel — BDMS
 */

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/db.php';

// Guard role
checkRole(['admin']);

$success_msg = get_flash_message('success');
$error_msg = get_flash_message('error');

try {
    // 1. Fetch latest admin details
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        header('Location: logout.php');
        exit;
    }
    
    // 2. Build Filter Queries
    $where = [];
    $params = [];
    
    if (!empty($_GET['q'])) {
        $where[] = "(full_name LIKE :q OR email LIKE :q OR phone LIKE :q)";
        $params['q'] = '%' . $_GET['q'] . '%';
    }
    if (!empty($_GET['role']) && in_array($_GET['role'], ['donor', 'recipient', 'admin'])) {
        $where[] = "role = :role";
        $params['role'] = $_GET['role'];
    }
    if (!empty($_GET['status']) && in_array($_GET['status'], ['active', 'suspended'])) {
        $where[] = "status = :status";
        $params['status'] = $_GET['status'];
    }
    
    $sql = "SELECT * FROM users";
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY created_at DESC";
    
    $stmt_users = $pdo->prepare($sql);
    $stmt_users->execute($params);
    $users = $stmt_users->fetchAll();
    
} catch (Exception $e) {
    die("Error loading accounts manager: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users — BDMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #1E293B;            /* Slate 800 */
            --crimson: #D32F2F;        /* Medical Red */
            --crimson-deep: #B71C1C;   /* Dark Red */
            --paper: #F8FAFC;          /* Slate 50 */
            --card: #FFFFFF;
            --rose: #FEE2E2;           /* Red 100 */
            --teal: #2E7D32;           /* Success Green */
            --teal-tint: #E8F5E9;      /* Green 50 */
            --amber: #F57C00;          /* Warning Orange */
            --amber-tint: #FFF3E0;     /* Warning 50 */
            --line: #E2E8F0;           /* Slate 200 */
            --muted: #64748B;          /* Slate 500 */
            --radius: 12px;
            --shadow: 0 1px 2px rgba(0, 0, 0, 0.05), 0 8px 24px -12px rgba(0, 0, 0, 0.08);
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
        
        /* Filter form grid */
        .filter-form {
            padding: 20px;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 12px;
            align-items: end;
        }
        @media (max-width: 750px) {
            .filter-form {
                grid-template-columns: 1fr;
            }
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
            display: inline-block;
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
            <h1>Manage Users</h1>
            <div class="admin-meta">Logged in as: <strong><?php echo h($admin['full_name']); ?></strong></div>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <a href="admin-dashboard.php" class="logout-link" style="background: var(--teal);">Back to Dashboard</a>
            <a href="logout.php" class="logout-link">Log Out</a>
        </div>
    </div>
    
    <?php if ($success_msg): ?>
        <div style="background: var(--teal-tint); color: var(--teal); border: 1px solid var(--teal); padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; font-size: 14px;">
            ✓ <?php echo h($success_msg); ?>
        </div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div style="background: var(--rose); color: var(--crimson-deep); border: 1px solid var(--crimson); padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; font-size: 14px;">
            ⚠ <?php echo h($error_msg); ?>
        </div>
    <?php endif; ?>
    
    <!-- Search and Filters Form -->
    <div class="section-card">
        <form method="GET" class="filter-form">
            <div>
                <label style="font-size:12px; font-weight:600; color:var(--muted); display:block; margin-bottom:6px;">Search keyword</label>
                <input type="text" name="q" value="<?php echo h($_GET['q'] ?? ''); ?>" placeholder="Name, email, or phone..." style="width:100%; padding: 8px 12px; border: 1px solid var(--line); border-radius: 8px; font-size:13.5px; background:var(--paper);">
            </div>
            <div>
                <label style="font-size:12px; font-weight:600; color:var(--muted); display:block; margin-bottom:6px;">Filter by Role</label>
                <select name="role" style="width:100%; padding: 8px 12px; border: 1px solid var(--line); border-radius: 8px; font-size:13.5px; background:var(--paper); cursor:pointer;">
                    <option value="">All Roles</option>
                    <option value="donor" <?php echo (($_GET['role'] ?? '') === 'donor') ? 'selected' : ''; ?>>Donor</option>
                    <option value="recipient" <?php echo (($_GET['role'] ?? '') === 'recipient') ? 'selected' : ''; ?>>Recipient</option>
                    <option value="admin" <?php echo (($_GET['role'] ?? '') === 'admin') ? 'selected' : ''; ?>>Admin</option>
                </select>
            </div>
            <div>
                <label style="font-size:12px; font-weight:600; color:var(--muted); display:block; margin-bottom:6px;">Account Status</label>
                <select name="status" style="width:100%; padding: 8px 12px; border: 1px solid var(--line); border-radius: 8px; font-size:13.5px; background:var(--paper); cursor:pointer;">
                    <option value="">All Statuses</option>
                    <option value="active" <?php echo (($_GET['status'] ?? '') === 'active') ? 'selected' : ''; ?>>Active</option>
                    <option value="suspended" <?php echo (($_GET['status'] ?? '') === 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                </select>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="submit" class="logout-link" style="background:var(--ink); cursor:pointer; height:38px; padding: 0 16px;">Filter</button>
                <a href="admin-users.php" class="logout-link" style="background:transparent; border:1px solid var(--line); color:var(--ink); height:38px; padding: 8px 16px; display:inline-flex; align-items:center;">Reset</a>
            </div>
        </form>
    </div>
    
    <!-- User List Section -->
    <div class="section-card">
        <div class="section-header">
            <h2>User Accounts (<?php echo count($users); ?> found)</h2>
        </div>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email / Phone</th>
                        <th>Role</th>
                        <th>Blood Group</th>
                        <th>Verification</th>
                        <th>Status</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--muted); padding: 24px;">No user accounts found matching your filters.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td>
                                    <strong><?php echo h($u['full_name']); ?></strong>
                                    <div style="font-size: 11px; color: var(--muted); margin-top: 2px;">
                                        ID: #<?php echo $u['id']; ?> · Registered: <?php echo date('d M Y', strtotime($u['created_at'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <div><?php echo h($u['email']); ?></div>
                                    <div class="mono" style="font-size:12px; color:var(--muted); margin-top:1px;"><?php echo h($u['phone']); ?></div>
                                </td>
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
                                    <?php if ($u['is_verified']): ?>
                                        <span style="color: var(--teal); font-weight: 600;">✓ Verified</span>
                                    <?php else: ?>
                                        <span style="color: var(--muted);">Unverified</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo h($u['status']); ?>">
                                        <?php echo ucfirst(h($u['status'])); ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <?php if ($u['id'] == $_SESSION['user_id']): ?>
                                        <span style="color:var(--muted); font-size:12px; font-weight:600; padding-right:8px;">Self</span>
                                    <?php else: ?>
                                        <?php if ($u['status'] === 'active'): ?>
                                            <form action="api/update-user-status.php" method="POST" style="margin:0; display:inline-block;">
                                                <?php echo csrf_input(); ?>
                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                <input type="hidden" name="status" value="suspended">
                                                <input type="hidden" name="redirect" value="../admin-users.php">
                                                <button type="submit" class="logout-link" style="padding: 4px 8px; font-size: 11px; background:transparent; color:var(--crimson); border:1px solid var(--crimson); border-radius:4px; font-weight:600; cursor:pointer;">Suspend</button>
                                            </form>
                                        <?php else: ?>
                                            <form action="api/update-user-status.php" method="POST" style="margin:0; display:inline-block;">
                                                <?php echo csrf_input(); ?>
                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                <input type="hidden" name="status" value="active">
                                                <input type="hidden" name="redirect" value="../admin-users.php">
                                                <button type="submit" class="logout-link" style="padding: 4px 8px; font-size: 11px; background:var(--teal); border-radius:4px; font-weight:600; cursor:pointer;">Activate</button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
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
