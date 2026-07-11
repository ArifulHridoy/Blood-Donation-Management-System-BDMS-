<?php
/**
 * Request Status Tracking — BDMS
 * Allows requesters to track the status of their blood request.
 */

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/db.php';

// Guard role
checkRole(['recipient']);

$request_id = intval($_GET['id'] ?? 0);
$is_success = isset($_GET['success']) && $_GET['success'] == '1';

if ($request_id <= 0) {
    die("Invalid Request ID.");
}

try {
    // Fetch request details
    $stmt = $pdo->prepare("SELECT * FROM blood_requests WHERE id = :id AND requester_id = :requester_id LIMIT 1");
    $stmt->execute(['id' => $request_id, 'requester_id' => $_SESSION['user_id']]);
    $req = $stmt->fetch();
    
    if (!$req) {
        die("Request not found or you don't have permission to view it.");
    }
} catch (Exception $e) {
    die("Error loading request: " . $e->getMessage());
}

$status_colors = [
    'pending' => 'amber',
    'approved' => 'blue',
    'fulfilled' => 'teal',
    'cancelled' => 'gray'
];
$color = $status_colors[$req['status']] ?? 'gray';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Status #<?php echo $request_id; ?> — BDMS</title>
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
            --gray: #64748b;
            --gray-tint: #f1f5f9;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: var(--paper);
            color: var(--ink);
            font-family: 'Inter', sans-serif;
            padding: 40px 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13.5px;
            color: var(--muted);
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 24px;
            transition: color 0.15s;
        }
        .back-link:hover { color: var(--ink); }
        .back-link svg { width: 16px; height: 16px; }

        .status-card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 30px;
            box-shadow: var(--shadow);
        }
        
        /* Alert */
        .alert-success {
            background: var(--teal-tint);
            color: var(--teal);
            border: 1px solid rgba(46,125,107,0.12);
            padding: 14px 18px;
            border-radius: 10px;
            font-size: 13.5px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 24px;
            line-height: 1.5;
        }
        .alert-success svg { width: 20px; height: 20px; flex-shrink: 0; margin-top: 1px; }

        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--line);
        }
        
        .req-id {
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 8px;
        }
        
        .header h1 {
            font-family: 'Fraunces', serif;
            font-size: 28px;
            margin-bottom: 16px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            text-transform: capitalize;
        }
        .bg-amber { background: var(--amber-tint); color: var(--amber); }
        .bg-blue { background: var(--blue-tint); color: var(--blue); }
        .bg-teal { background: var(--teal-tint); color: var(--teal); }
        .bg-gray { background: var(--gray-tint); color: var(--gray); }

        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .detail-label {
            font-family: 'JetBrains Mono', monospace;
            font-size: 10.5px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
            font-weight: 500;
        }
        .detail-value {
            font-size: 14.5px;
            font-weight: 500;
        }
        .blood-tag {
            background: linear-gradient(135deg, var(--crimson), var(--crimson-deep));
            color: #fff;
            padding: 2px 8px;
            border-radius: 6px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            font-weight: 700;
        }

        /* Timeline */
        .timeline {
            margin: 30px 0;
            position: relative;
        }
        .timeline::before {
            content: '';
            position: absolute;
            top: 0; left: 15px;
            height: 100%; width: 2px;
            background: var(--line);
            z-index: 1;
        }
        
        .timeline-item {
            position: relative;
            padding-left: 44px;
            margin-bottom: 24px;
            z-index: 2;
        }
        .timeline-item:last-child { margin-bottom: 0; }
        
        .t-dot {
            position: absolute;
            left: 8px; top: 2px;
            width: 16px; height: 16px;
            border-radius: 50%;
            background: var(--line);
            border: 3px solid var(--card);
        }
        
        .timeline-item.active .t-dot {
            background: var(--teal);
            box-shadow: 0 0 0 3px var(--teal-tint);
        }
        
        .timeline-item.current .t-dot {
            background: var(--blue);
            box-shadow: 0 0 0 3px var(--blue-tint);
        }

        .t-title { font-weight: 600; font-size: 14px; margin-bottom: 4px; }
        .t-desc { font-size: 12.5px; color: var(--muted); }

        /* Actions */
        .actions {
            display: flex;
            gap: 12px;
            margin-top: 30px;
            padding-top: 24px;
            border-top: 1px solid var(--line);
        }
        .btn {
            flex: 1;
            padding: 12px;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            font-size: 13.5px;
            cursor: pointer;
            border: none;
            text-align: center;
            transition: opacity 0.15s;
        }
        .btn:hover { opacity: 0.9; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        
        .btn-outline {
            background: transparent;
            border: 1px solid var(--line);
            color: var(--ink);
        }
        .btn-outline:hover { background: #f8f9fa; }
        
        .btn-teal { background: var(--teal); color: #fff; }
        
        /* Loading Overlay */
        .loading-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(247,244,239,0.8);
            backdrop-filter: blur(4px);
            z-index: 1000;
            display: none;
            align-items: center; justify-content: center;
            flex-direction: column; gap: 16px;
        }
        .spinner {
            width: 30px; height: 30px;
            border: 3px solid var(--line);
            border-top-color: var(--crimson);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>

<!-- JS Alert Target -->
<div id="loading" class="loading-overlay">
    <div class="spinner"></div>
    <div style="font-weight: 500; font-size: 14px; color: var(--ink);">Updating status...</div>
</div>

<div class="container">
    <a href="recipient-dashboard.php" class="back-link">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
        Back to Dashboard
    </a>

    <?php if ($is_success): ?>
        <div class="alert-success">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            <div>
                <strong>Request Submitted Successfully!</strong><br>
                Your request has been logged. We've sent a confirmation email to <?php echo h($req['contact_email'] ?: 'your registered email'); ?>.
            </div>
        </div>
    <?php endif; ?>

    <div class="status-card">
        <div class="header">
            <div class="req-id">Request ID: #<?php echo str_pad($req['id'], 5, '0', STR_PAD_LEFT); ?></div>
            <h1>Status Tracking</h1>
            <div class="status-badge bg-<?php echo $color; ?>">
                <?php echo ucfirst(h($req['status'])); ?>
            </div>
        </div>

        <div class="details-grid">
            <div class="detail-item">
                <span class="detail-label">Blood Group</span>
                <span class="detail-value"><span class="blood-tag"><?php echo h($req['blood_group']); ?></span></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Quantity</span>
                <span class="detail-value"><?php echo h($req['quantity']); ?> Units</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Urgency</span>
                <span class="detail-value" style="text-transform: capitalize;"><?php echo h($req['urgency']); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Hospital</span>
                <span class="detail-value"><?php echo h($req['hospital_name']); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Submitted On</span>
                <span class="detail-value"><?php echo date('M d, Y h:i A', strtotime($req['created_at'])); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Contact Person</span>
                <span class="detail-value"><?php echo h($req['contact_person']); ?> (<?php echo h($req['contact_phone']); ?>)</span>
            </div>
        </div>

        <!-- Simulated Progress Timeline -->
        <div class="timeline">
            <?php 
            // Determine active states for UI timeline
            $s_submit = true; // always true
            $s_approve = in_array($req['status'], ['approved', 'fulfilled']);
            $s_fulfill = ($req['status'] === 'fulfilled');
            $s_cancel = ($req['status'] === 'cancelled');
            ?>

            <div class="timeline-item <?php echo $s_submit ? 'active' : ''; ?>">
                <div class="t-dot"></div>
                <div class="t-title">Request Submitted</div>
                <div class="t-desc">Your request was received by the system.</div>
            </div>
            
            <?php if ($s_cancel): ?>
                <div class="timeline-item current">
                    <div class="t-dot" style="background: var(--gray); box-shadow: 0 0 0 3px var(--gray-tint);"></div>
                    <div class="t-title">Request Cancelled</div>
                    <div class="t-desc">This request has been cancelled.</div>
                </div>
            <?php else: ?>
                <div class="timeline-item <?php echo $s_approve ? 'active' : ($req['status'] === 'pending' ? 'current' : ''); ?>">
                    <div class="t-dot"></div>
                    <div class="t-title">Processing & Matching</div>
                    <div class="t-desc">We are verifying details and notifying eligible donors.</div>
                </div>
                
                <div class="timeline-item <?php echo $s_fulfill ? 'active' : ($s_approve && !$s_fulfill ? 'current' : ''); ?>">
                    <div class="t-dot"></div>
                    <div class="t-title">Fulfilled</div>
                    <div class="t-desc">The required blood has been successfully arranged.</div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($req['status'] === 'pending' || $req['status'] === 'approved'): ?>
            <div class="actions">
                <button class="btn btn-outline" onclick="updateStatus('cancelled')">Cancel Request</button>
                <button class="btn btn-teal" onclick="updateStatus('fulfilled')">Mark as Fulfilled</button>
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- Add hidden CSRF token -->
<input type="hidden" id="csrf" value="<?php echo h(generate_csrf_token()); ?>">

<script>
function updateStatus(newStatus) {
    if (!confirm('Are you sure you want to mark this request as ' + newStatus + '?')) return;
    
    document.getElementById('loading').style.display = 'flex';
    
    const formData = new URLSearchParams();
    formData.append('request_id', <?php echo $request_id; ?>);
    formData.append('status', newStatus);
    formData.append('csrf_token', document.getElementById('csrf').value);
    
    fetch('api/update-blood-request.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'request-status.php?id=<?php echo $request_id; ?>';
        } else {
            alert('Error: ' + data.error);
            document.getElementById('loading').style.display = 'none';
        }
    })
    .catch(err => {
        alert('Network error. Try again.');
        document.getElementById('loading').style.display = 'none';
    });
}
</script>

</body>
</html>
