<?php
/**
 * Blood Request Form — BDMS
 * Allows recipients (hospitals/organizations) to submit blood requests.
 */

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/db.php';

// Guard: only recipients can request blood
checkRole(['recipient']);

// Fetch user profile for pre-filling contact fields
try {
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

$success = get_flash_message('success');
$error   = get_flash_message('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Blood — BDMS</title>
    <meta name="description" content="Submit an urgent blood request through the Blood Donation Management System.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        /* ── Design Tokens ── */
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

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: var(--paper);
            color: var(--ink);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 40px 20px 60px;
            position: relative;
            overflow-x: hidden;
        }

        /* ── Typography ── */
        h1, h2, h3 {
            font-family: 'Fraunces', serif;
            font-weight: 600;
            letter-spacing: -0.01em;
        }

        .mono {
            font-family: 'JetBrains Mono', monospace;
        }

        /* ── Floating Blood-Drop Backdrop ── */
        .bg-drops {
            position: fixed;
            inset: 0;
            z-index: -1;
            overflow: hidden;
            pointer-events: none;
        }
        .bg-drops svg { position: absolute; }

        /* ── Page Wrapper ── */
        .page-wrapper {
            width: 100%;
            max-width: 580px;
            position: relative;
            z-index: 10;
        }

        /* ── Back Link ── */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--muted);
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 20px;
            transition: color 0.15s;
        }
        .back-link:hover { color: var(--ink); }
        .back-link svg { width: 16px; height: 16px; }

        /* ── Main Card ── */
        .request-card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 34px 28px 28px;
            box-shadow: var(--shadow);
            animation: fadeSlideUp 0.45s ease-out;
        }

        @keyframes fadeSlideUp {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Header ── */
        .card-header {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 26px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--line);
        }
        .header-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--crimson), var(--crimson-deep));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(168,32,26,0.2);
        }
        .header-icon svg {
            width: 24px;
            height: 24px;
            color: #fff;
        }
        .header-text h1 {
            font-size: 22px;
            color: var(--ink);
            margin-bottom: 4px;
        }
        .header-text p {
            color: var(--muted);
            font-size: 13px;
        }

        /* ── Section Labels ── */
        .section-label {
            font-family: 'JetBrains Mono', monospace;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: var(--crimson);
            font-weight: 600;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--line);
        }

        /* ── Form Elements ── */
        .form-group {
            margin-bottom: 18px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        label {
            display: block;
            font-family: 'JetBrains Mono', monospace;
            font-size: 10.5px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--muted);
            margin-bottom: 6px;
            font-weight: 600;
        }

        label .required-star {
            color: var(--crimson);
            margin-left: 2px;
        }

        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--line);
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 13.5px;
            background: #FCFBF9;
            color: var(--ink);
            transition: border-color 0.15s, box-shadow 0.15s;
            outline: none;
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: var(--crimson);
            box-shadow: 0 0 0 3px rgba(168, 32, 26, 0.1);
        }

        .input-hint {
            font-size: 11px;
            color: var(--muted);
            margin-top: 4px;
        }

        /* ── Urgency Radio Chips ── */
        .urgency-group {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .urgency-chip {
            position: relative;
        }

        .urgency-chip input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .urgency-chip label.chip-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            padding: 14px 8px;
            border: 1.5px solid var(--line);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
            background: #FCFBF9;
            text-transform: none;
            letter-spacing: normal;
            font-family: 'Inter', sans-serif;
        }

        .chip-icon {
            font-size: 20px;
            line-height: 1;
        }

        .chip-text {
            font-weight: 600;
            font-size: 12.5px;
            color: var(--ink);
        }

        .chip-desc {
            font-size: 10px;
            color: var(--muted);
            font-weight: 400;
        }

        .urgency-chip input[type="radio"]:checked + .chip-label {
            border-color: var(--crimson);
            background: var(--rose);
            box-shadow: 0 0 0 3px rgba(168, 32, 26, 0.08);
        }

        /* Urgency-specific highlight colors */
        .urgency-chip.critical input[type="radio"]:checked + .chip-label {
            border-color: #c0392b;
            background: linear-gradient(135deg, #fde2e2, #f8d0d0);
            box-shadow: 0 0 0 3px rgba(192,57,43,0.12);
        }
        .urgency-chip.critical input[type="radio"]:checked + .chip-label .chip-text {
            color: #c0392b;
        }

        .urgency-chip.urgent input[type="radio"]:checked + .chip-label {
            border-color: var(--amber);
            background: linear-gradient(135deg, var(--amber-tint), #f4dfc3);
            box-shadow: 0 0 0 3px rgba(201,122,30,0.12);
        }
        .urgency-chip.urgent input[type="radio"]:checked + .chip-label .chip-text {
            color: var(--amber);
        }

        .urgency-chip.standard input[type="radio"]:checked + .chip-label {
            border-color: var(--teal);
            background: linear-gradient(135deg, var(--teal-tint), #c8e6dc);
            box-shadow: 0 0 0 3px rgba(46,125,107,0.12);
        }
        .urgency-chip.standard input[type="radio"]:checked + .chip-label .chip-text {
            color: var(--teal);
        }

        .urgency-chip label.chip-label:hover {
            border-color: var(--muted);
            transform: translateY(-1px);
        }

        /* ── Blood Group Selector ── */
        .blood-group-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
        }

        .bg-chip {
            position: relative;
        }

        .bg-chip input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .bg-chip label {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 11px 6px;
            border: 1.5px solid var(--line);
            border-radius: 9px;
            cursor: pointer;
            font-family: 'JetBrains Mono', monospace;
            font-size: 14px;
            font-weight: 600;
            color: var(--ink);
            text-transform: none;
            letter-spacing: normal;
            transition: all 0.2s ease;
            background: #FCFBF9;
        }

        .bg-chip label:hover {
            border-color: var(--crimson);
            transform: translateY(-1px);
        }

        .bg-chip input[type="radio"]:checked + label {
            background: linear-gradient(135deg, var(--crimson), var(--crimson-deep));
            color: #fff;
            border-color: var(--crimson);
            box-shadow: 0 4px 12px rgba(168,32,26,0.2);
            transform: scale(1.02);
        }

        /* ── Section Divider ── */
        .section-divider {
            height: 1px;
            background: var(--line);
            margin: 24px 0;
        }

        /* ── Submit Button ── */
        .btn-submit {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            background: linear-gradient(135deg, var(--crimson), var(--crimson-deep));
            color: #FFFFFF;
            border: none;
            padding: 14px;
            border-radius: 10px;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            font-size: 14.5px;
            cursor: pointer;
            transition: opacity 0.15s, transform 0.1s, box-shadow 0.2s;
            text-align: center;
            margin-top: 8px;
            box-shadow: 0 4px 16px rgba(168,32,26,0.2);
        }
        .btn-submit:hover {
            opacity: 0.94;
            box-shadow: 0 6px 20px rgba(168,32,26,0.3);
        }
        .btn-submit:active {
            transform: scale(0.99);
        }
        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .btn-submit svg {
            width: 18px;
            height: 18px;
        }

        /* ── Loading Spinner ── */
        .spinner {
            display: none;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* ── Alert Banners ── */
        .alert-banner {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 14px;
            border-radius: 8px;
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

        /* ── Field Validation ── */
        .field-error input,
        .field-error select,
        .field-error textarea {
            border-color: var(--crimson) !important;
            box-shadow: 0 0 0 3px rgba(168,32,26,0.08) !important;
        }
        .field-error-msg {
            color: var(--crimson);
            font-size: 11px;
            margin-top: 4px;
            display: none;
        }
        .field-error .field-error-msg {
            display: block;
        }

        /* ── Pulse Animation for Critical Urgency ── */
        @keyframes pulse-critical {
            0%, 100% { box-shadow: 0 0 0 0 rgba(192,57,43,0.3); }
            50%      { box-shadow: 0 0 0 8px rgba(192,57,43,0); }
        }
        .urgency-chip.critical input[type="radio"]:checked + .chip-label {
            animation: pulse-critical 2s ease-in-out infinite;
        }

        /* ── Responsive ── */
        @media (max-width: 480px) {
            .request-card { padding: 24px 18px 22px; }
            .urgency-group { grid-template-columns: 1fr; }
            .blood-group-grid { grid-template-columns: repeat(4, 1fr); }
            .form-row { grid-template-columns: 1fr; gap: 0; }
        }
    </style>
</head>
<body>

<!-- Floating blood-drop backdrop -->
<div class="bg-drops">
    <svg width="0" height="0">
        <defs>
            <radialGradient id="dropGrad1" cx="35%" cy="28%" r="75%">
                <stop offset="0%" stop-color="#C4392F"/>
                <stop offset="60%" stop-color="#A8201A"/>
                <stop offset="100%" stop-color="#7A1712"/>
            </radialGradient>
            <radialGradient id="dropGrad2" cx="35%" cy="28%" r="75%">
                <stop offset="0%" stop-color="#B8302A"/>
                <stop offset="65%" stop-color="#8E1C16"/>
                <stop offset="100%" stop-color="#5E120D"/>
            </radialGradient>
        </defs>
    </svg>
    <svg style="top:-20px; right:4%;" width="160" height="205" viewBox="0 0 100 128" opacity="0.25">
        <path d="M50 4 C50 4 92 62 92 88 C92 110 73 124 50 124 C27 124 8 110 8 88 C8 62 50 4 50 4 Z" fill="url(#dropGrad1)"/>
    </svg>
    <svg style="top:250px; left:-40px;" width="130" height="166" viewBox="0 0 100 128" opacity="0.25" transform="rotate(-15)">
        <path d="M50 4 C50 4 92 62 92 88 C92 110 73 124 50 124 C27 124 8 110 8 88 C8 62 50 4 50 4 Z" fill="url(#dropGrad2)"/>
    </svg>
    <svg style="bottom:20px; right:10%;" width="120" height="154" viewBox="0 0 100 128" opacity="0.25" transform="rotate(8)">
        <path d="M50 4 C50 4 92 62 92 88 C92 110 73 124 50 124 C27 124 8 110 8 88 C8 62 50 4 50 4 Z" fill="url(#dropGrad1)"/>
    </svg>
</div>

<div class="page-wrapper">

    <!-- Back navigation -->
    <a href="recipient-dashboard.php" class="back-link" id="back-link">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
        Back to Dashboard
    </a>

    <div class="request-card">

        <!-- Header -->
        <div class="card-header">
            <div class="header-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2C12 2 4 10.5 4 15.5C4 20 7.6 22 12 22C16.4 22 20 20 20 15.5C20 10.5 12 2 12 2Z"/>
                    <path d="M12 8V16M8 12H16" stroke-width="1.8"/>
                </svg>
            </div>
            <div class="header-text">
                <h1>Request Blood</h1>
                <p>Submit a request to find matching donors quickly</p>
            </div>
        </div>

        <!-- Success alert -->
        <?php if ($success): ?>
            <div class="alert-banner alert-success" id="alert-success">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <span><?php echo h($success); ?></span>
            </div>
        <?php endif; ?>

        <!-- Error alert -->
        <?php if ($error): ?>
            <div class="alert-banner alert-error" id="alert-error">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <span><?php echo h($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- JS alert placeholder -->
        <div id="js-alert" style="display:none;"></div>

        <!-- Form -->
        <form id="blood-request-form" method="POST" action="api/submit-blood-request.php" novalidate>
            <?php echo csrf_input(); ?>

            <!-- ─── Blood Details ─── -->
            <div class="section-label">Blood Details</div>

            <!-- Blood Group -->
            <div class="form-group" id="grp-blood-group">
                <label>Blood Group Needed <span class="required-star">*</span></label>
                <div class="blood-group-grid">
                    <?php
                    $blood_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                    foreach ($blood_types as $bt):
                        $btId = 'bg_' . str_replace(['+', '-'], ['pos', 'neg'], $bt);
                    ?>
                    <div class="bg-chip">
                        <input type="radio" name="blood_group" id="<?php echo $btId; ?>" value="<?php echo h($bt); ?>" required>
                        <label for="<?php echo $btId; ?>"><?php echo h($bt); ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <span class="field-error-msg">Please select a blood group</span>
            </div>

            <!-- Quantity -->
            <div class="form-group" id="grp-quantity">
                <label for="quantity">Quantity (Units/Bags) <span class="required-star">*</span></label>
                <input type="number" id="quantity" name="quantity" min="1" max="20" placeholder="e.g. 2" required>
                <span class="input-hint">1 unit ≈ 450 ml of whole blood</span>
                <span class="field-error-msg">Enter a valid quantity (1–20 units)</span>
            </div>

            <!-- Urgency -->
            <div class="form-group" id="grp-urgency">
                <label>Urgency Level <span class="required-star">*</span></label>
                <div class="urgency-group">
                    <div class="urgency-chip critical">
                        <input type="radio" name="urgency" id="urg-critical" value="critical" required>
                        <label for="urg-critical" class="chip-label">
                            <span class="chip-icon">🔴</span>
                            <span class="chip-text">Critical</span>
                            <span class="chip-desc">Within hours</span>
                        </label>
                    </div>
                    <div class="urgency-chip urgent">
                        <input type="radio" name="urgency" id="urg-urgent" value="urgent">
                        <label for="urg-urgent" class="chip-label">
                            <span class="chip-icon">🟠</span>
                            <span class="chip-text">Urgent</span>
                            <span class="chip-desc">Within 24 hrs</span>
                        </label>
                    </div>
                    <div class="urgency-chip standard">
                        <input type="radio" name="urgency" id="urg-standard" value="standard">
                        <label for="urg-standard" class="chip-label">
                            <span class="chip-icon">🟢</span>
                            <span class="chip-text">Standard</span>
                            <span class="chip-desc">Within a week</span>
                        </label>
                    </div>
                </div>
                <span class="field-error-msg">Please select an urgency level</span>
            </div>

            <div class="section-divider"></div>

            <!-- ─── Hospital & Location ─── -->
            <div class="section-label">Hospital & Location</div>

            <div class="form-group" id="grp-hospital">
                <label for="hospital_name">Hospital / Clinic Name <span class="required-star">*</span></label>
                <input type="text" id="hospital_name" name="hospital_name" placeholder="e.g. Khulna Medical College Hospital" required>
                <span class="field-error-msg">Please enter the hospital name</span>
            </div>

            <div class="form-row">
                <div class="form-group" id="grp-ward">
                    <label for="ward">Ward / Department</label>
                    <input type="text" id="ward" name="ward" placeholder="e.g. ICU, Ward 5">
                </div>
                <div class="form-group" id="grp-city">
                    <label for="city">City <span class="required-star">*</span></label>
                    <input type="text" id="city" name="city" value="<?php echo h($user['city'] ?? ''); ?>" placeholder="e.g. Khulna" required>
                    <span class="field-error-msg">Please enter the city</span>
                </div>
            </div>

            <div class="section-divider"></div>

            <!-- ─── Contact Information ─── -->
            <div class="section-label">Contact Information</div>

            <div class="form-group" id="grp-contact-person">
                <label for="contact_person">Contact Person <span class="required-star">*</span></label>
                <input type="text" id="contact_person" name="contact_person" value="<?php echo h($user['full_name']); ?>" placeholder="Full name of point-of-contact" required>
                <span class="field-error-msg">Please enter a contact person</span>
            </div>

            <div class="form-row">
                <div class="form-group" id="grp-contact-phone">
                    <label for="contact_phone">Phone Number <span class="required-star">*</span></label>
                    <input type="tel" id="contact_phone" name="contact_phone" value="<?php echo h($user['phone']); ?>" placeholder="01XXXXXXXXX" required>
                    <span class="field-error-msg">Enter a valid BD phone (01XXXXXXXXX)</span>
                </div>
                <div class="form-group" id="grp-contact-email">
                    <label for="contact_email">Email Address</label>
                    <input type="email" id="contact_email" name="contact_email" value="<?php echo h($user['email']); ?>" placeholder="email@example.com">
                    <span class="field-error-msg">Enter a valid email address</span>
                </div>
            </div>

            <div class="section-divider"></div>

            <!-- ─── Additional Notes ─── -->
            <div class="section-label">Additional Notes</div>

            <div class="form-group">
                <label for="notes">Notes / Special Requirements</label>
                <textarea id="notes" name="notes" placeholder="e.g. Patient is undergoing surgery, need platelets as well…"></textarea>
            </div>

            <!-- Submit -->
            <button type="submit" class="btn-submit" id="btn-submit">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 2L11 13"></path>
                    <path d="M22 2L15 22L11 13L2 9L22 2Z"></path>
                </svg>
                <span id="btn-text">Submit Blood Request</span>
                <div class="spinner" id="btn-spinner"></div>
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('blood-request-form');
    const btnSubmit = document.getElementById('btn-submit');
    const btnText = document.getElementById('btn-text');
    const btnSpinner = document.getElementById('btn-spinner');
    const jsAlert = document.getElementById('js-alert');

    // Validation helpers
    function showFieldError(groupId) {
        const el = document.getElementById(groupId);
        if (el) el.classList.add('field-error');
    }

    function clearFieldErrors() {
        document.querySelectorAll('.field-error').forEach(el => el.classList.remove('field-error'));
    }

    function showAlert(type, message) {
        jsAlert.className = 'alert-banner alert-' + type;
        jsAlert.innerHTML = `
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                ${type === 'success'
                    ? '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline>'
                    : '<circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line>'
                }
            </svg>
            <span>${message}</span>
        `;
        jsAlert.style.display = 'flex';
        jsAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function hideAlert() {
        jsAlert.style.display = 'none';
    }

    // Clear per-field validation on interaction
    document.querySelectorAll('input, select, textarea').forEach(function (el) {
        el.addEventListener('input', function () {
            const grp = this.closest('.form-group');
            if (grp) grp.classList.remove('field-error');
        });
    });

    // Blood-group & urgency radio clear errors on change
    document.querySelectorAll('input[type="radio"]').forEach(function (el) {
        el.addEventListener('change', function () {
            const grp = this.closest('.form-group');
            if (grp) grp.classList.remove('field-error');
        });
    });

    // Client-side validation
    function validate() {
        clearFieldErrors();
        let valid = true;

        // Blood group
        if (!form.querySelector('input[name="blood_group"]:checked')) {
            showFieldError('grp-blood-group');
            valid = false;
        }

        // Quantity
        const qty = parseInt(document.getElementById('quantity').value, 10);
        if (!qty || qty < 1 || qty > 20) {
            showFieldError('grp-quantity');
            valid = false;
        }

        // Urgency
        if (!form.querySelector('input[name="urgency"]:checked')) {
            showFieldError('grp-urgency');
            valid = false;
        }

        // Hospital
        if (!document.getElementById('hospital_name').value.trim()) {
            showFieldError('grp-hospital');
            valid = false;
        }

        // City
        if (!document.getElementById('city').value.trim()) {
            showFieldError('grp-city');
            valid = false;
        }

        // Contact person
        if (!document.getElementById('contact_person').value.trim()) {
            showFieldError('grp-contact-person');
            valid = false;
        }

        // Phone
        const phone = document.getElementById('contact_phone').value.trim();
        if (!/^01[3-9]\d{8}$/.test(phone)) {
            showFieldError('grp-contact-phone');
            valid = false;
        }

        // Email (optional but must be valid if provided)
        const email = document.getElementById('contact_email').value.trim();
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showFieldError('grp-contact-email');
            valid = false;
        }

        return valid;
    }

    // Submit handler — AJAX
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        hideAlert();

        if (!validate()) {
            showAlert('error', 'Please fix the highlighted fields before submitting.');
            return;
        }

        // Show loading state
        btnSubmit.disabled = true;
        btnText.textContent = 'Submitting…';
        btnSpinner.style.display = 'inline-block';

        const formData = new FormData(form);

        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function (response) { return response.json(); })
        .then(function (data) {
            if (data.success) {
                // Redirect to status tracking view instead of staying on the form
                window.location.href = 'request-status.php?id=' + data.request_id + '&success=1';
            } else {
                showAlert('error', data.error || 'Something went wrong. Please try again.');
            }
        })
        .catch(function (err) {
            showAlert('error', 'Network error. Please check your connection and try again.');
        })
        .finally(function () {
            btnSubmit.disabled = false;
            btnText.textContent = 'Submit Blood Request';
            btnSpinner.style.display = 'none';
        });
    });
});
</script>

</body>
</html>
