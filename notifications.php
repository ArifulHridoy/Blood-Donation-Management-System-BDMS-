<?php
/**
 * Notifications and Preferences UI — BDMS
 */

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/db.php';

// Allow any logged in user
checkRole(['donor', 'recipient', 'admin']);

try {
    $user_id = $_SESSION['user_id'];
    
    // Fetch user preferences
    $stmt = $pdo->prepare("SELECT notify_email, notify_sms, notify_blood_requests FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $user_id]);
    $prefs = $stmt->fetch();
    
    // Fetch notifications
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = :id ORDER BY created_at DESC LIMIT 50");
    $stmt->execute(['id' => $user_id]);
    $notifications = $stmt->fetchAll();
    
    // Count unread
    $unread_count = 0;
    foreach ($notifications as $n) {
        if (!$n['is_read']) $unread_count++;
    }
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$back_link = $_SESSION['role'] . '-dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications — BDMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #181A1B;
            --paper: #F7F4EF;
            --card: #FFFFFF;
            --line: #E6E0D6;
            --muted: #7A756C;
            --radius: 14px;
            --shadow: 0 1px 2px rgba(24,26,27,0.04), 0 8px 24px -12px rgba(24,26,27,0.12);
            --crimson: #A8201A;
            --crimson-deep: #7A1712;
            
            --teal: #2E7D6B;
            --teal-tint: #DCEEE8;
            --amber: #C97A1E;
            --amber-tint: #F6E6CF;
            --blue: #2563eb;
            --blue-tint: #dbeafe;
            --rose: #F3DEDB;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: var(--paper);
            color: var(--ink);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
        }
        
        /* Navbar */
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
        }
        .nav-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }
        .nav-brand svg { width: 30px; height: 30px; }
        .nav-brand-name {
            font-family: 'Fraunces', serif;
            font-weight: 700;
            font-size: 20px;
            color: var(--ink);
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
        .nav-link:hover { color: var(--ink); background: rgba(24,26,27,0.04); }

        .container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        .page-header h1 {
            font-family: 'Fraunces', serif;
            font-size: 28px;
            margin-bottom: 8px;
        }
        .page-header p {
            color: var(--muted);
            font-size: 14px;
        }

        .layout-grid {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 24px;
            align-items: start;
        }

        /* Panels */
        .panel {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        .panel-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--line);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .panel-header h2 {
            font-size: 16px;
            font-weight: 600;
            font-family: 'Fraunces', serif;
        }

        /* Preferences form */
        .pref-list {
            padding: 10px 24px 24px;
        }
        .pref-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 0;
            border-bottom: 1px solid var(--line);
        }
        .pref-item:last-child { border-bottom: none; }
        .pref-info .title { font-weight: 600; font-size: 14px; margin-bottom: 4px; }
        .pref-info .desc { font-size: 12.5px; color: var(--muted); line-height: 1.4; }
        
        /* Toggle Switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
            flex-shrink: 0;
        }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: var(--line);
            transition: .3s;
            border-radius: 24px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        input:checked + .slider { background-color: var(--teal); }
        input:checked + .slider:before { transform: translateX(20px); }

        /* Notification List */
        .mark-read-btn {
            font-size: 12.5px;
            color: var(--blue);
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: opacity 0.15s;
        }
        .mark-read-btn:hover { opacity: 0.7; }
        
        .noti-list {
            list-style: none;
        }
        .noti-item {
            display: flex;
            gap: 16px;
            padding: 20px 24px;
            border-bottom: 1px solid var(--line);
            transition: background 0.2s;
        }
        .noti-item:last-child { border-bottom: none; }
        .noti-item.unread { background: #FCFBF9; }
        .noti-item:hover { background: #FDFCFA; }
        
        .noti-icon {
            width: 40px; height: 40px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .noti-icon svg { width: 20px; height: 20px; }
        
        .noti-icon.info { background: var(--blue-tint); color: var(--blue); }
        .noti-icon.success { background: var(--teal-tint); color: var(--teal); }
        .noti-icon.warning { background: var(--amber-tint); color: var(--amber); }
        .noti-icon.error { background: var(--rose); color: var(--crimson); }
        .noti-icon.blood_request { background: var(--rose); color: var(--crimson-deep); }
        
        .noti-content { flex: 1; }
        .noti-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 6px;
        }
        .noti-title {
            font-weight: 600;
            font-size: 14px;
            color: var(--ink);
        }
        .noti-time {
            font-size: 11px;
            color: var(--muted);
            font-family: 'JetBrains Mono', monospace;
            white-space: nowrap;
        }
        .noti-message {
            font-size: 13.5px;
            color: #555;
            line-height: 1.5;
        }
        .noti-link {
            display: inline-block;
            margin-top: 10px;
            font-size: 13px;
            font-weight: 600;
            color: var(--crimson);
            text-decoration: none;
        }
        .noti-link:hover { text-decoration: underline; }
        
        .unread-dot {
            width: 8px; height: 8px;
            background: var(--blue);
            border-radius: 50%;
            margin-left: 8px;
            display: inline-block;
        }
        
        .empty-state {
            padding: 40px 20px;
            text-align: center;
            color: var(--muted);
        }
        
        /* Save Indicator */
        #save-status {
            font-size: 12px;
            color: var(--teal);
            font-weight: 500;
            opacity: 0;
            transition: opacity 0.3s;
        }

        @media (max-width: 768px) {
            .layout-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="<?php echo htmlspecialchars($back_link); ?>" class="nav-brand">
        <svg viewBox="0 0 32 32" fill="none">
            <path d="M16 4C16 4 6 14.5 6 20.5C6 26.3 10.5 29 16 29C21.5 29 26 26.3 26 20.5C26 14.5 16 4 16 4Z" fill="#A8201A"/>
            <path d="M6 20.5 L12 20.5 L14.5 16 L17.5 25 L20 20.5 L26 20.5" stroke="#F7F4EF" stroke-width="1.6" fill="none" stroke-linejoin="round" stroke-linecap="round"/>
        </svg>
        <span class="nav-brand-name">BDMS</span>
    </a>
    <a href="<?php echo htmlspecialchars($back_link); ?>" class="nav-link">Return to Dashboard</a>
</nav>

<div class="container">
    <div class="page-header">
        <h1>Notifications</h1>
        <p>Manage your alerts and view your history.</p>
    </div>

    <div class="layout-grid">
        <!-- Preferences Panel -->
        <div class="panel">
            <div class="panel-header">
                <h2>Preferences</h2>
                <span id="save-status">Saved</span>
            </div>
            <form id="pref-form" class="pref-list">
                <input type="hidden" name="csrf_token" value="<?php echo h(generate_csrf_token()); ?>">
                
                <div class="pref-item">
                    <div class="pref-info">
                        <div class="title">Email Alerts</div>
                        <div class="desc">Receive updates via email.</div>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="notify_email" <?php echo $prefs['notify_email'] ? 'checked' : ''; ?> onchange="savePreferences()">
                        <span class="slider"></span>
                    </label>
                </div>
                
                <div class="pref-item">
                    <div class="pref-info">
                        <div class="title">SMS Alerts</div>
                        <div class="desc">Receive urgent texts.</div>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="notify_sms" <?php echo $prefs['notify_sms'] ? 'checked' : ''; ?> onchange="savePreferences()">
                        <span class="slider"></span>
                    </label>
                </div>

                <?php if ($_SESSION['role'] === 'donor'): ?>
                <div class="pref-item">
                    <div class="pref-info">
                        <div class="title">Blood Requests</div>
                        <div class="desc">Notify me when someone needs my blood type.</div>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="notify_blood_requests" <?php echo $prefs['notify_blood_requests'] ? 'checked' : ''; ?> onchange="savePreferences()">
                        <span class="slider"></span>
                    </label>
                </div>
                <?php else: ?>
                    <input type="hidden" name="notify_blood_requests" value="<?php echo $prefs['notify_blood_requests'] ? 'on' : ''; ?>">
                <?php endif; ?>
            </form>
        </div>

        <!-- History Panel -->
        <div class="panel">
            <div class="panel-header">
                <h2>Recent Notifications <?php echo $unread_count > 0 ? "<span style='color:var(--blue);'>({$unread_count} unread)</span>" : ""; ?></h2>
                <?php if ($unread_count > 0): ?>
                    <button class="mark-read-btn" onclick="markRead('all')">Mark all as read</button>
                <?php endif; ?>
            </div>
            
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    You don't have any notifications yet.
                </div>
            <?php else: ?>
                <ul class="noti-list">
                    <?php foreach ($notifications as $n): ?>
                        <?php 
                        $iconSvg = '';
                        switch($n['type']) {
                            case 'success': $iconSvg = '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline>'; break;
                            case 'warning': $iconSvg = '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line>'; break;
                            case 'error': $iconSvg = '<circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line>'; break;
                            case 'blood_request': $iconSvg = '<path d="M12 2C12 2 4 10.5 4 15.5C4 20 7.6 22 12 22C16.4 22 20 20 20 15.5C20 10.5 12 2 12 2Z"/>'; break;
                            default: $iconSvg = '<circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line>'; break;
                        }
                        ?>
                        <li class="noti-item <?php echo $n['is_read'] ? '' : 'unread'; ?>" id="noti-<?php echo $n['id']; ?>">
                            <div class="noti-icon <?php echo h($n['type']); ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?php echo $iconSvg; ?></svg>
                            </div>
                            <div class="noti-content">
                                <div class="noti-header">
                                    <div class="noti-title">
                                        <?php echo h($n['title']); ?>
                                        <?php if (!$n['is_read']): ?><span class="unread-dot"></span><?php endif; ?>
                                    </div>
                                    <div class="noti-time"><?php echo date('M d, H:i', strtotime($n['created_at'])); ?></div>
                                </div>
                                <div class="noti-message"><?php echo nl2br(h($n['message'])); ?></div>
                                <?php if (!empty($n['link'])): ?>
                                    <a href="<?php echo htmlspecialchars($n['link']); ?>" class="noti-link">View Details &rarr;</a>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
let saveTimeout;
function savePreferences() {
    const form = document.getElementById('pref-form');
    const formData = new FormData(form);
    
    // Checkboxes only send data if checked, so we construct URLSearchParams to handle this
    const data = new URLSearchParams();
    data.append('csrf_token', formData.get('csrf_token'));
    if (form.querySelector('[name="notify_email"]').checked) data.append('notify_email', '1');
    if (form.querySelector('[name="notify_sms"]').checked) data.append('notify_sms', '1');
    
    const bloodReqNode = form.querySelector('[name="notify_blood_requests"]');
    if (bloodReqNode && (bloodReqNode.checked || bloodReqNode.value === 'on')) {
        data.append('notify_blood_requests', '1');
    }

    fetch('api/update-notification-preferences.php', {
        method: 'POST',
        body: data
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            const status = document.getElementById('save-status');
            status.style.opacity = '1';
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(() => { status.style.opacity = '0'; }, 2000);
        } else {
            alert('Failed to save preferences: ' + res.error);
        }
    });
}

function markRead(id) {
    const data = new URLSearchParams();
    data.append('csrf_token', document.querySelector('[name="csrf_token"]').value);
    
    if (id === 'all') {
        data.append('all', 'true');
    } else {
        data.append('notification_id', id);
    }
    
    fetch('api/mark-notification-read.php', {
        method: 'POST',
        body: data
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            window.location.reload();
        }
    });
}
</script>

</body>
</html>
