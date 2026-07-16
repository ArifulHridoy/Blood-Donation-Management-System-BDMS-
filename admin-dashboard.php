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
    
    // 2. Fetch stats metrics
    $total_donors = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'donor'")->fetchColumn();
    $pending_requests = $pdo->query("SELECT COUNT(*) FROM blood_requests WHERE status = 'pending'")->fetchColumn();
    $completed_donations = $pdo->query("SELECT COUNT(*) FROM donation_bookings WHERE status = 'completed'")->fetchColumn();
    $total_recipients = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'recipient'")->fetchColumn();
    
    // 3. Fetch recent blood requests
    $recent_requests = $pdo->query("
        SELECT r.*, u.full_name AS requester_name 
        FROM blood_requests r
        JOIN users u ON r.requester_id = u.id
        ORDER BY r.created_at DESC LIMIT 5
    ")->fetchAll();
    
    // 4. Fetch recent bookings
    $recent_bookings = $pdo->query("
        SELECT b.*, u.full_name AS donor_name, s.slot_date, s.start_time
        FROM donation_bookings b
        JOIN users u ON b.donor_id = u.id
        JOIN donation_slots s ON b.slot_id = s.id
        ORDER BY b.booked_at DESC LIMIT 5
    ")->fetchAll();
    
    // 5. Fetch recent registrations
    $recent_registrations = $pdo->query("
        SELECT * FROM users 
        ORDER BY created_at DESC LIMIT 5
    ")->fetchAll();
    
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
        
        /* Statuses & Urgencies for Requests & Bookings */
        .status-pending { background: var(--amber-tint); color: var(--amber); }
        .status-approved, .status-scheduled { background: var(--teal-tint); color: var(--teal); }
        .status-fulfilled, .status-completed { background: var(--teal-tint); color: var(--teal); }
        .status-cancelled, .status-no_show { background: #E6E0D6; color: var(--muted); }
        
        .urgency-badge {
            font-size: 11px;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: 12px;
            display: inline-block;
        }
        .urgency-critical { background: var(--rose); color: var(--crimson-deep); }
        .urgency-urgent { background: var(--amber-tint); color: var(--amber); }
        .urgency-standard { background: var(--teal-tint); color: var(--teal); }
        
        /* Stats Border Highlights */
        .stat-donors { border-top: 3px solid var(--teal); }
        .stat-pending { border-top: 3px solid var(--crimson); }
        .stat-completed { border-top: 3px solid var(--teal); }
        .stat-recipients { border-top: 3px solid var(--amber); }
        
        /* Multi-column Grid Layout */
        .dashboard-columns {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            align-items: start;
        }
        @media (max-width: 850px) {
            .dashboard-columns {
                grid-template-columns: 1fr;
            }
        }
        
        /* Side Card Quick Links List */
        .quick-links {
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding: 20px;
        }
        .quick-link-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--paper);
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 10px 14px;
            color: var(--ink);
            text-decoration: none;
            font-weight: 600;
            font-size: 13.5px;
            transition: all 0.15s;
        }
        .quick-link-btn:hover {
            border-color: var(--crimson);
            background: #fff;
            color: var(--crimson);
        }
        .quick-link-btn svg {
            width: 16px;
            height: 16px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2.2;
        }
        
        .compact-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding: 20px;
        }
        .compact-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13px;
        }
        
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
        <div style="display: flex; gap: 10px; align-items: center;">
            <a href="donor-search.php" class="logout-link" style="background: var(--crimson);">Search Donors</a>
            <a href="logout.php" class="logout-link">Log Out</a>
        </div>
    </div>
    
    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card stat-donors">
            <div class="label">Total Donors</div>
            <div class="value"><?php echo $total_donors; ?></div>
        </div>
        <div class="stat-card stat-pending">
            <div class="label">Pending Requests</div>
            <div class="value"><?php echo $pending_requests; ?></div>
        </div>
        <div class="stat-card stat-completed">
            <div class="label">Completed Donations</div>
            <div class="value"><?php echo $completed_donations; ?></div>
        </div>
        <div class="stat-card stat-recipients">
            <div class="label">Total Recipients</div>
            <div class="value"><?php echo $total_recipients; ?></div>
        </div>
    </div>
    
    <!-- Multi-Column Layout -->
    <div class="dashboard-columns">
        
        <!-- Left Column: Key Activities -->
        <div>
            <!-- Recent Blood Requests -->
            <div class="section-card">
                <div class="section-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <h2>Recent Blood Requests</h2>
                    <span style="font-size:12px; color:var(--muted); font-weight:500;">Latest entries</span>
                </div>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Hospital</th>
                                <th>Blood Group</th>
                                <th>Quantity</th>
                                <th>Urgency</th>
                                <th>Status</th>
                                <th>Date Requested</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_requests)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--muted); padding: 24px;">No blood requests registered in the system.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_requests as $req): ?>
                                    <tr>
                                        <td><strong><?php echo h($req['hospital_name']); ?></strong></td>
                                        <td><span style="font-weight:700; color:var(--crimson);"><?php echo h($req['blood_group']); ?></span></td>
                                        <td class="mono"><?php echo (int)$req['quantity']; ?> bag(s)</td>
                                        <td>
                                            <span class="urgency-badge urgency-<?php echo h($req['urgency']); ?>">
                                                <?php echo ucfirst(h($req['urgency'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo h($req['status']); ?>">
                                                <?php echo ucfirst(h($req['status'])); ?>
                                            </span>
                                        </td>
                                        <td style="font-size:12px; color:var(--muted);">
                                            <?php echo date('d M Y', strtotime($req['created_at'])); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Recent Booking Appointments -->
            <div class="section-card">
                <div class="section-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <h2>Recent Donation Bookings</h2>
                    <span style="font-size:12px; color:var(--muted); font-weight:500;">Scheduled visits</span>
                </div>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Donor</th>
                                <th>Scheduled Date</th>
                                <th>Time Slot</th>
                                <th>Status</th>
                                <th>Date Booked</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_bookings)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--muted); padding: 24px;">No donation bookings found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_bookings as $b): ?>
                                    <tr>
                                        <td><strong><?php echo h($b['donor_name']); ?></strong></td>
                                        <td class="mono"><?php echo date('d M Y', strtotime($b['slot_date'])); ?></td>
                                        <td class="mono" style="font-size:12.5px;">
                                            <?php echo date('h:i A', strtotime($b['start_time'])); ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo h($b['status']); ?>">
                                                <?php echo ucfirst(h($b['status'])); ?>
                                            </span>
                                        </td>
                                        <td style="font-size:12px; color:var(--muted);">
                                            <?php echo date('d M Y', strtotime($b['booked_at'])); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Right Column: Timeline & Shortcuts -->
        <div>
            <!-- Recent Registrations -->
            <div class="section-card">
                <div class="section-header">
                    <h2>Recent Signups</h2>
                </div>
                <div class="compact-list">
                    <?php if (empty($recent_registrations)): ?>
                        <p style="text-align: center; color: var(--muted); margin: 0; padding: 12px 0;">No registered users found.</p>
                    <?php else: ?>
                        <?php foreach ($recent_registrations as $r): ?>
                            <div class="compact-item">
                                <div>
                                    <strong style="color:var(--ink);"><?php echo h($r['full_name']); ?></strong>
                                    <div style="font-size: 11px; color: var(--muted); margin-top: 2px;">
                                        <?php echo date('d M Y', strtotime($r['created_at'])); ?>
                                    </div>
                                </div>
                                <span class="role-chip role-<?php echo h($r['role']); ?>">
                                    <?php echo ucfirst(h($r['role'])); ?>
                                </span>
                            </div>
                            <?php if (next($recent_registrations)): ?>
                                <div style="border-bottom: 1px solid var(--line); margin: 8px 0;"></div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Link Actions -->
            <div class="section-card">
                <div class="section-header">
                    <h2>Console Actions</h2>
                </div>
                <div class="quick-links">
                    <a href="donor-search.php" class="quick-link-btn">
                        <svg viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        Search Blood Donors
                    </a>
                </div>
            </div>
        </div>
        
    </div>
</div>

</body>
</html>
