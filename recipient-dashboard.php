<?php
/**
 * Recipient Dashboard for BDMS
 * Full-featured dashboard for hospitals/organizations to manage blood requests.
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

    // Fetch blood request stats for this recipient
    $stats = [];

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM blood_requests WHERE requester_id = :id");
    $stmt->execute(['id' => $user['id']]);
    $stats['total'] = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM blood_requests WHERE requester_id = :id AND status = 'pending'");
    $stmt->execute(['id' => $user['id']]);
    $stats['pending'] = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM blood_requests WHERE requester_id = :id AND status = 'fulfilled'");
    $stmt->execute(['id' => $user['id']]);
    $stats['fulfilled'] = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM blood_requests WHERE requester_id = :id AND urgency = 'critical' AND status = 'pending'");
    $stmt->execute(['id' => $user['id']]);
    $stats['critical'] = $stmt->fetchColumn();

    // Fetch recent blood requests
    $stmt = $pdo->prepare("SELECT * FROM blood_requests WHERE requester_id = :id ORDER BY created_at DESC LIMIT 8");
    $stmt->execute(['id' => $user['id']]);
    $recent_requests = $stmt->fetchAll();

} catch (Exception $e) {
    die("Error loading dashboard: " . $e->getMessage());
}

$success = get_flash_message('success');
$error   = get_flash_message('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recipient Dashboard — BDMS</title>
    <meta name="description" content="Manage blood requests and track donations through the BDMS Recipient Dashboard.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        /* ── Design Tokens ── */
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

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: var(--paper);
            color: var(--ink);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
        }

        h1, h2, h3 {
            font-family: 'Fraunces', serif;
            font-weight: 600;
            letter-spacing: -0.01em;
        }

        .mono {
            font-family: 'JetBrains Mono', monospace;
        }

        /* ── Navbar ── */
        .navbar {
            background: var(--card);
            border-bottom: 1px solid var(--line);
            padding: 0 32px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 1px 3px rgba(24,26,27,0.04);
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .nav-brand svg {
            width: 30px;
            height: 30px;
        }

        .nav-brand-name {
            font-family: 'Fraunces', serif;
            font-weight: 700;
            font-size: 20px;
            color: var(--ink);
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-link {
            text-decoration: none;
            color: var(--muted);
            font-weight: 500;
            font-size: 13.5px;
            padding: 8px 14px;
            border-radius: 8px;
            transition: all 0.15s;
        }

        .nav-link:hover {
            color: var(--ink);
            background: rgba(24,26,27,0.04);
        }

        .nav-link.active {
            color: var(--crimson-deep);
            background: var(--rose);
            font-weight: 600;
        }

        .nav-link-cta {
            text-decoration: none;
            color: #fff;
            background: linear-gradient(135deg, var(--crimson), var(--crimson-deep));
            font-weight: 600;
            font-size: 13px;
            padding: 8px 16px;
            border-radius: 8px;
            transition: opacity 0.15s, box-shadow 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 8px rgba(168,32,26,0.15);
        }

        .nav-link-cta:hover {
            opacity: 0.92;
            box-shadow: 0 4px 12px rgba(168,32,26,0.25);
        }

        .nav-link-cta svg {
            width: 15px;
            height: 15px;
        }

        .nav-logout {
            text-decoration: none;
            color: var(--muted);
            font-weight: 500;
            font-size: 13px;
            padding: 8px 14px;
            border-radius: 8px;
            transition: all 0.15s;
            border: 1px solid var(--line);
        }

        .nav-logout:hover {
            color: var(--crimson);
            border-color: var(--crimson);
            background: var(--rose);
        }

        /* ── Main Container ── */
        .container {
            max-width: 1120px;
            margin: 0 auto;
            padding: 32px 28px 60px;
        }

        /* ── Hero Welcome ── */
        .hero {
            background: linear-gradient(135deg, var(--crimson) 0%, var(--crimson-deep) 60%, #5A100B 100%);
            border-radius: var(--radius);
            padding: 32px 34px;
            color: #fff;
            margin-bottom: 28px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(122,23,18,0.2);
            animation: fadeSlideUp 0.5s ease-out;
        }

        @keyframes fadeSlideUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .hero::before {
            content: '';
            position: absolute;
            top: -40px;
            right: -20px;
            width: 200px;
            height: 260px;
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 128'%3E%3Cpath d='M50 4 C50 4 92 62 92 88 C92 110 73 124 50 124 C27 124 8 110 8 88 C8 62 50 4 50 4 Z' fill='rgba(255,255,255,0.06)'/%3E%3C/svg%3E") no-repeat center;
            background-size: contain;
            pointer-events: none;
        }

        .hero::after {
            content: '';
            position: absolute;
            bottom: -30px;
            left: 40%;
            width: 140px;
            height: 180px;
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 128'%3E%3Cpath d='M50 4 C50 4 92 62 92 88 C92 110 73 124 50 124 C27 124 8 110 8 88 C8 62 50 4 50 4 Z' fill='rgba(255,255,255,0.04)'/%3E%3C/svg%3E") no-repeat center;
            background-size: contain;
            pointer-events: none;
            transform: rotate(15deg);
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-greeting {
            font-size: 13px;
            opacity: 0.8;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: .08em;
            font-family: 'JetBrains Mono', monospace;
            margin-bottom: 6px;
        }

        .hero h1 {
            font-size: 26px;
            margin-bottom: 6px;
            font-weight: 700;
        }

        .hero-sub {
            font-size: 14px;
            opacity: 0.75;
        }

        .hero-meta {
            display: flex;
            gap: 20px;
            margin-top: 18px;
        }

        .hero-meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12.5px;
            opacity: 0.85;
        }

        .hero-meta-item svg {
            width: 14px;
            height: 14px;
            opacity: 0.7;
        }

        /* ── Stats Grid ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 28px;
            animation: fadeSlideUp 0.55s ease-out;
        }

        .stat-card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(24,26,27,0.1);
        }

        .stat-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 14px;
        }

        .stat-icon svg {
            width: 20px;
            height: 20px;
        }

        .stat-icon.teal    { background: var(--teal-tint);  color: var(--teal); }
        .stat-icon.amber   { background: var(--amber-tint); color: var(--amber); }
        .stat-icon.crimson { background: var(--rose);       color: var(--crimson); }
        .stat-icon.ink     { background: #E8E6E2;          color: var(--ink); }

        .stat-label {
            font-family: 'JetBrains Mono', monospace;
            font-size: 10.5px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--muted);
            font-weight: 500;
            margin-bottom: 6px;
        }

        .stat-value {
            font-family: 'Fraunces', serif;
            font-size: 28px;
            font-weight: 700;
            color: var(--ink);
        }

        /* ── Content Grid (2-column) ── */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            align-items: start;
            animation: fadeSlideUp 0.6s ease-out;
        }

        /* ── Section Cards ── */
        .section-card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 22px;
            border-bottom: 1px solid var(--line);
        }

        .section-header h2 {
            font-size: 17px;
            margin: 0;
        }

        .section-header .view-all {
            font-size: 12.5px;
            color: var(--crimson);
            text-decoration: none;
            font-weight: 600;
            transition: opacity 0.15s;
        }

        .section-header .view-all:hover {
            opacity: 0.7;
        }

        /* ── Requests Table ── */
        .requests-table {
            width: 100%;
            border-collapse: collapse;
        }

        .requests-table th {
            text-align: left;
            font-family: 'JetBrains Mono', monospace;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--muted);
            font-weight: 500;
            padding: 12px 22px;
            border-bottom: 1px solid var(--line);
            background: #FCFBF9;
        }

        .requests-table td {
            padding: 14px 22px;
            border-bottom: 1px solid var(--line);
            font-size: 13.5px;
        }

        .requests-table tbody tr {
            transition: background 0.1s;
        }

        .requests-table tbody tr:hover {
            background: #FDFCFA;
        }

        .requests-table tbody tr:last-child td {
            border-bottom: none;
        }

        .blood-badge {
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--crimson), var(--crimson-deep));
            color: #fff;
            padding: 3px 9px;
            border-radius: 6px;
            display: inline-block;
        }

        .urgency-pill {
            font-size: 11px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .urgency-pill.critical {
            background: #fde2e2;
            color: #c0392b;
        }

        .urgency-pill.urgent {
            background: var(--amber-tint);
            color: var(--amber);
        }

        .urgency-pill.standard {
            background: var(--teal-tint);
            color: var(--teal);
        }

        .status-chip {
            font-size: 11px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
        }

        .status-chip.pending   { background: var(--amber-tint); color: var(--amber); }
        .status-chip.approved  { background: #dbeafe;           color: #2563eb; }
        .status-chip.fulfilled { background: var(--teal-tint);  color: var(--teal); }
        .status-chip.cancelled { background: #f1f1ef;           color: var(--muted); }

        .empty-state {
            text-align: center;
            padding: 48px 24px;
            color: var(--muted);
        }

        .empty-state-icon {
            width: 56px;
            height: 56px;
            background: var(--rose);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
        }

        .empty-state-icon svg {
            width: 26px;
            height: 26px;
            color: var(--crimson);
        }

        .empty-state p {
            font-size: 14px;
            margin-bottom: 4px;
        }

        .empty-state .sub {
            font-size: 12.5px;
            color: #a09a90;
        }

        .empty-state .btn-empty {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 18px;
            background: var(--ink);
            color: #fff;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 9px;
            font-weight: 600;
            font-size: 13.5px;
            transition: opacity 0.15s;
        }

        .empty-state .btn-empty:hover { opacity: 0.9; }
        .empty-state .btn-empty svg { width: 16px; height: 16px; }

        /* ── Sidebar Panels ── */
        .sidebar-stack {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* Quick Actions */
        .quick-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 18px 20px;
        }

        .action-tile {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            padding: 14px 16px;
            border-radius: 10px;
            border: 1px solid var(--line);
            background: #FCFBF9;
            transition: all 0.2s ease;
        }

        .action-tile:hover {
            border-color: var(--crimson);
            background: var(--rose);
            transform: translateX(3px);
        }

        .action-icon {
            width: 38px;
            height: 38px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .action-icon svg {
            width: 18px;
            height: 18px;
        }

        .action-icon.red    { background: var(--rose);       color: var(--crimson); }
        .action-icon.teal   { background: var(--teal-tint);  color: var(--teal); }
        .action-icon.amber  { background: var(--amber-tint); color: var(--amber); }

        .action-text {
            flex: 1;
        }

        .action-text .title {
            font-size: 13.5px;
            font-weight: 600;
            color: var(--ink);
        }

        .action-text .desc {
            font-size: 11.5px;
            color: var(--muted);
            margin-top: 1px;
        }

        .action-arrow {
            color: var(--muted);
            transition: transform 0.2s;
        }

        .action-tile:hover .action-arrow {
            transform: translateX(3px);
            color: var(--crimson);
        }

        .action-arrow svg {
            width: 16px;
            height: 16px;
        }

        /* Profile Card */
        .profile-list {
            list-style: none;
            padding: 0;
        }

        .profile-list li {
            display: flex;
            justify-content: space-between;
            padding: 11px 22px;
            border-bottom: 1px solid var(--line);
            font-size: 13px;
        }

        .profile-list li:last-child {
            border-bottom: none;
        }

        .profile-list .label {
            color: var(--muted);
            font-weight: 500;
            font-family: 'JetBrains Mono', monospace;
            font-size: 10.5px;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .profile-list .value {
            font-weight: 600;
            color: var(--ink);
            text-align: right;
            font-size: 13px;
        }

        /* ── Alert Banners ── */
        .alert-banner {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 20px;
            line-height: 1.4;
            animation: fadeSlideUp 0.3s ease-out;
        }

        .alert-error {
            background: var(--rose);
            color: var(--crimson-deep);
            border: 1px solid rgba(168,32,26,0.12);
        }

        .alert-success {
            background: var(--teal-tint);
            color: var(--teal);
            border: 1px solid rgba(46,125,107,0.12);
        }

        .alert-banner svg {
            width: 16px;
            height: 16px;
            flex: none;
        }

        /* ── Responsive ── */
        @media (max-width: 960px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .navbar {
                padding: 0 16px;
            }
            .container {
                padding: 24px 16px 40px;
            }
        }

        @media (max-width: 580px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }
            .hero {
                padding: 24px 20px;
            }
            .hero h1 { font-size: 22px; }
            .hero-meta {
                flex-direction: column;
                gap: 8px;
            }
            .nav-links {
                gap: 4px;
            }
            .nav-link { display: none; }
        }
    </style>
</head>
<body>

<!-- ── Navbar ── -->
<nav class="navbar">
    <a href="recipient-dashboard.php" class="nav-brand">
        <svg viewBox="0 0 32 32" fill="none">
            <path d="M16 4C16 4 6 14.5 6 20.5C6 26.3 10.5 29 16 29C21.5 29 26 26.3 26 20.5C26 14.5 16 4 16 4Z" fill="#A8201A"/>
            <path d="M6 20.5 L12 20.5 L14.5 16 L17.5 25 L20 20.5 L26 20.5" stroke="#F7F4EF" stroke-width="1.6" fill="none" stroke-linejoin="round" stroke-linecap="round"/>
        </svg>
        <span class="nav-brand-name">BDMS</span>
    </a>
    <div class="nav-links">
        <a href="recipient-dashboard.php" class="nav-link active" id="nav-dashboard">Dashboard</a>
        <a href="blood-request.php" class="nav-link-cta" id="nav-request-blood">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            New Request
        </a>
        <a href="logout.php" class="nav-logout" id="nav-logout">Log Out</a>
    </div>
</nav>

<!-- ── Main Content ── -->
<main class="container">

    <!-- Alerts -->
    <?php if ($success): ?>
        <div class="alert-banner alert-success">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            <span><?php echo h($success); ?></span>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert-banner alert-error">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            <span><?php echo h($error); ?></span>
        </div>
    <?php endif; ?>

    <!-- Hero Welcome Banner -->
    <div class="hero">
        <div class="hero-content">
            <div class="hero-greeting">Recipient Portal</div>
            <h1>Welcome back, <?php echo h($user['full_name']); ?></h1>
            <p class="hero-sub">Manage your blood requests and connect with donors.</p>
            <div class="hero-meta">
                <span class="hero-meta-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    Member since <?php echo date('M Y', strtotime($user['created_at'])); ?>
                </span>
                <span class="hero-meta-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                    <?php echo h($user['phone']); ?>
                </span>
                <span class="hero-meta-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                    <?php echo h($user['email']); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon ink">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
            </div>
            <div class="stat-label">Total Requests</div>
            <div class="stat-value"><?php echo $stats['total']; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon amber">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            </div>
            <div class="stat-label">Pending</div>
            <div class="stat-value"><?php echo $stats['pending']; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon teal">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            </div>
            <div class="stat-label">Fulfilled</div>
            <div class="stat-value"><?php echo $stats['fulfilled']; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon crimson">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            </div>
            <div class="stat-label">Critical Active</div>
            <div class="stat-value"><?php echo $stats['critical']; ?></div>
        </div>
    </div>

    <!-- Content Grid -->
    <div class="content-grid">

        <!-- Left: Recent Requests Table -->
        <div class="section-card">
            <div class="section-header">
                <h2>Recent Blood Requests</h2>
            </div>
            <?php if (empty($recent_requests)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2C12 2 4 10.5 4 15.5C4 20 7.6 22 12 22C16.4 22 20 20 20 15.5C20 10.5 12 2 12 2Z"/>
                        </svg>
                    </div>
                    <p>No blood requests yet</p>
                    <p class="sub">Submit your first request to find matching donors.</p>
                    <a href="blood-request.php" class="btn-empty">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Create First Request
                    </a>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="requests-table">
                        <thead>
                            <tr>
                                <th>Blood</th>
                                <th>Qty</th>
                                <th>Urgency</th>
                                <th>Hospital</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_requests as $req): ?>
                            <tr>
                                <td><span class="blood-badge"><?php echo h($req['blood_group']); ?></span></td>
                                <td><?php echo h($req['quantity']); ?> unit<?php echo $req['quantity'] > 1 ? 's' : ''; ?></td>
                                <td>
                                    <span class="urgency-pill <?php echo h($req['urgency']); ?>">
                                        <?php 
                                        $icons = ['critical' => '🔴', 'urgent' => '🟠', 'standard' => '🟢'];
                                        echo ($icons[$req['urgency']] ?? '') . ' ' . ucfirst(h($req['urgency']));
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo h($req['hospital_name']); ?></td>
                                <td><span class="status-chip <?php echo h($req['status']); ?>"><?php echo ucfirst(h($req['status'])); ?></span></td>
                                <td style="color: var(--muted); font-size: 12.5px;"><?php echo date('M d, Y', strtotime($req['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Sidebar -->
        <div class="sidebar-stack">

            <!-- Quick Actions -->
            <div class="section-card">
                <div class="section-header">
                    <h2>Quick Actions</h2>
                </div>
                <div class="quick-actions">
                    <a href="blood-request.php" class="action-tile" id="action-new-request">
                        <div class="action-icon red">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 2C12 2 4 10.5 4 15.5C4 20 7.6 22 12 22C16.4 22 20 20 20 15.5C20 10.5 12 2 12 2Z"/>
                                <path d="M12 8V16M8 12H16" stroke-width="1.8"/>
                            </svg>
                        </div>
                        <div class="action-text">
                            <div class="title">New Blood Request</div>
                            <div class="desc">Submit a new request for blood</div>
                        </div>
                        <span class="action-arrow">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </span>
                    </a>
                    <a href="blood-request.php?urgency=critical" class="action-tile" id="action-emergency">
                        <div class="action-icon amber">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                                <line x1="12" y1="9" x2="12" y2="13"></line>
                                <line x1="12" y1="17" x2="12.01" y2="17"></line>
                            </svg>
                        </div>
                        <div class="action-text">
                            <div class="title">Emergency Request</div>
                            <div class="desc">Critical — needed within hours</div>
                        </div>
                        <span class="action-arrow">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </span>
                    </a>
                    <a href="logout.php" class="action-tile" id="action-logout">
                        <div class="action-icon teal">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                <polyline points="16 17 21 12 16 7"></polyline>
                                <line x1="21" y1="12" x2="9" y2="12"></line>
                            </svg>
                        </div>
                        <div class="action-text">
                            <div class="title">Log Out</div>
                            <div class="desc">End your current session</div>
                        </div>
                        <span class="action-arrow">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </span>
                    </a>
                </div>
            </div>

            <!-- Account Info -->
            <div class="section-card">
                <div class="section-header">
                    <h2>Account Info</h2>
                </div>
                <ul class="profile-list">
                    <li>
                        <span class="label">Organization</span>
                        <span class="value"><?php echo h($user['full_name']); ?></span>
                    </li>
                    <li>
                        <span class="label">Email</span>
                        <span class="value"><?php echo h($user['email']); ?></span>
                    </li>
                    <li>
                        <span class="label">Phone</span>
                        <span class="value"><?php echo h($user['phone']); ?></span>
                    </li>
                    <li>
                        <span class="label">Status</span>
                        <span class="value"><?php echo ucfirst(h($user['status'])); ?></span>
                    </li>
                    <li>
                        <span class="label">Verified</span>
                        <span class="value"><?php echo $user['is_verified'] ? '✅ Yes' : '❌ No'; ?></span>
                    </li>
                </ul>
            </div>

        </div>

    </div>

</main>

</body>
</html>
