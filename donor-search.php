<?php
/**
 * Donor Search Console with Advanced Filters for BDMS
 */

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/db.php';

// Authorize access (admins and recipients only)
checkRole(['admin', 'recipient']);

// Resolve dashboard link for the navigation bar
$dashboard_link = ($_SESSION['role'] === 'admin') ? 'admin-dashboard.php' : 'recipient-dashboard.php';

// 1. Get filter inputs from GET request
$search = trim($_GET['search'] ?? '');
$blood_type = trim($_GET['blood_type'] ?? '');
$city = trim($_GET['city'] ?? '');
$gender = trim($_GET['gender'] ?? '');
$is_verified = isset($_GET['is_verified']) && $_GET['is_verified'] !== '' ? $_GET['is_verified'] : '';
$status = trim($_GET['status'] ?? '');

// 2. Fetch distinct cities of donors dynamically to build city dropdown filter
try {
    $city_stmt = $pdo->query("
        SELECT DISTINCT city 
        FROM users 
        WHERE role = 'donor' AND city IS NOT NULL AND city != '' 
        ORDER BY city ASC
    ");
    $db_cities = $city_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $db_cities = [];
}

// 3. Build dynamic prepared query
$donors = [];
$error = null;

try {
    $sql = "SELECT id, full_name, email, phone, blood_type, date_of_birth, weight_kg, gender, address, city, is_verified, status FROM users WHERE role = 'donor'";
    $where_clauses = [];
    $params = [];
    
    // Text search query
    if (!empty($search)) {
        $where_clauses[] = "(full_name LIKE :search_name OR email LIKE :search_email OR phone LIKE :search_phone)";
        $params['search_name'] = '%' . $search . '%';
        $params['search_email'] = '%' . $search . '%';
        $params['search_phone'] = '%' . $search . '%';
    }
    
    // Blood Type filter
    if (!empty($blood_type)) {
        $where_clauses[] = "blood_type = :blood_type";
        $params['blood_type'] = $blood_type;
    }
    
    // City filter
    if (!empty($city)) {
        $where_clauses[] = "city = :city";
        $params['city'] = $city;
    }
    
    // Gender filter
    if (!empty($gender)) {
        $where_clauses[] = "gender = :gender";
        $params['gender'] = $gender;
    }
    
    // Verification filter
    if ($is_verified !== '') {
        $where_clauses[] = "is_verified = :is_verified";
        $params['is_verified'] = (int)$is_verified;
    }
    
    // Status filter
    if (!empty($status)) {
        $where_clauses[] = "status = :status";
        $params['status'] = $status;
    }
    
    // Combine clauses
    if (!empty($where_clauses)) {
        $sql .= " AND " . implode(" AND ", $where_clauses);
    }
    
    $sql .= " ORDER BY full_name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $donors = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = "Failed to query database: " . $e->getMessage();
}

/**
 * Computes user age from date of birth
 */
function calculate_age($dob_string) {
    if (empty($dob_string)) return 'N/A';
    try {
        $dob = new DateTime($dob_string);
        $today = new DateTime();
        if ($dob > $today) return 'N/A';
        return $today->diff($dob)->y;
    } catch (Exception $e) {
        return 'N/A';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Donors — BDMS</title>
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
        html, body { margin: 0; padding: 0; min-height: 100vh; }
        
        body {
            background: var(--paper);
            color: var(--ink);
            font-family: 'Inter', sans-serif;
            font-size: 14.5px;
            line-height: 1.5;
            padding: 30px 24px 60px;
            position: relative;
            z-index: 1;
        }
        
        /* ---------- Watermarks ---------- */
        .bg-drops {
            position: fixed;
            inset: 0;
            z-index: -1;
            overflow: hidden;
            pointer-events: none;
        }
        .bg-drops svg { position: absolute; }
        
        /* ---------- Container ---------- */
        .container {
            max-width: 1140px;
            margin: 0 auto;
        }
        
        /* ---------- Top Bar / Navigation ---------- */
        .top-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            border-bottom: 1px solid var(--line);
            padding-bottom: 14px;
        }
        .top-nav h1 {
            font-family: 'Fraunces', serif;
            font-size: 26px;
            margin: 0;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--ink);
            color: #fff;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            transition: opacity 0.15s;
        }
        .back-link:hover { opacity: 0.9; }
        .back-link svg { width: 14px; height: 14px; }
        
        /* ---------- Layout Columns ---------- */
        .search-layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 20px;
            align-items: start;
        }
        @media (max-width: 900px) {
            .search-layout { grid-template-columns: 1fr; }
        }
        
        /* ---------- Card Shells ---------- */
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--line);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .card-header h2 {
            font-family: 'Fraunces', serif;
            font-size: 17.5px;
            margin: 0;
        }
        .results-count {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: var(--muted);
            font-weight: 600;
        }
        
        /* ---------- Filter Sidebar ---------- */
        .filter-form {
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .filter-group label {
            font-family: 'JetBrains Mono', monospace;
            font-size: 10.5px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--muted);
            font-weight: 600;
        }
        
        input[type="text"], select {
            width: 100%;
            padding: 9px 10px;
            border: 1px solid var(--line);
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            background: #FCFBF9;
            color: var(--ink);
            outline: none;
            transition: border-color 0.15s;
        }
        input[type="text"]:focus, select:focus {
            border-color: var(--crimson);
        }
        
        .btn-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 8px;
            margin-top: 10px;
        }
        .btn-submit {
            background: var(--ink);
            color: #fff;
            border: none;
            padding: 10px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13.5px;
            cursor: pointer;
            text-align: center;
        }
        .btn-submit:hover { opacity: 0.9; }
        .btn-reset {
            background: var(--paper);
            border: 1px solid var(--line);
            color: var(--muted);
            padding: 10px 14px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .btn-reset:hover {
            color: var(--crimson);
            border-color: var(--crimson);
        }
        .btn-reset svg { width: 14px; height: 14px; }
        
        /* ---------- Results Table ---------- */
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
            background: #FAF8F5;
        }
        td {
            padding: 14px 20px;
            border-bottom: 1px solid var(--line);
            font-size: 13.5px;
            vertical-align: middle;
        }
        tr:last-child td { border-bottom: none; }
        
        /* Table Chips */
        .type-chip {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 600;
            font-size: 12.5px;
            padding: 3px 8px;
            border-radius: 6px;
            background: var(--rose);
            color: var(--crimson-deep);
            display: inline-block;
        }
        
        .badge {
            font-family: 'Inter', sans-serif;
            font-size: 11.5px;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: 20px;
            display: inline-block;
        }
        .badge-verified { background: var(--teal-tint); color: var(--teal); }
        .badge-unverified { background: var(--paper); color: var(--muted); border: 1px solid var(--line); }
        .badge-active { background: var(--teal-tint); color: var(--teal); }
        .badge-suspended { background: var(--rose); color: var(--crimson-deep); }
        
        /* Contact info styling */
        .contact-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .contact-info span { font-size: 13px; color: var(--ink); font-weight: 500; }
        .contact-info em { font-size: 11.5px; color: var(--muted); font-style: normal; font-family: 'JetBrains Mono', monospace; }
        
        /* Donor Meta Details */
        .donor-meta {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .donor-meta .name {
            font-weight: 600;
            color: var(--ink);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .donor-meta .name svg {
            width: 10px;
            height: 10px;
            fill: var(--crimson);
            display: inline;
        }
        .donor-meta .age-gender {
            font-size: 12px;
            color: var(--muted);
        }
        
        /* ---------- Empty / Error States ---------- */
        .alert-error {
            background: var(--rose);
            color: var(--crimson-deep);
            border: 1px solid rgba(168, 32, 26, 0.12);
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
        }
        .empty-state {
            padding: 60px 40px;
            text-align: center;
            color: var(--muted);
        }
        .empty-state svg {
            width: 48px;
            height: 48px;
            color: var(--line);
            margin-bottom: 12px;
        }
        .empty-state h3 {
            font-family: 'Fraunces', serif;
            font-size: 18px;
            color: var(--ink);
            margin: 0 0 6px;
        }
        .empty-state p {
            font-size: 13.5px;
            margin: 0;
        }
    </style>
</head>
<body>

<!-- Watermark backdrops (25% opacity) -->
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
    <svg style="top:320px; left:-40px;" width="130" height="166" viewBox="0 0 100 128" opacity="0.25" transform="rotate(-15)">
        <path d="M50 4 C50 4 92 62 92 88 C92 110 73 124 50 124 C27 124 8 110 8 88 C8 62 50 4 50 4 Z" fill="url(#dropGrad2)"/>
    </svg>
    <svg style="bottom:10px; right:12%;" width="120" height="154" viewBox="0 0 100 128" opacity="0.25" transform="rotate(8)">
        <path d="M50 4 C50 4 92 62 92 88 C92 110 73 124 50 124 C27 124 8 110 8 88 C8 62 50 4 50 4 Z" fill="url(#dropGrad1)"/>
    </svg>
</div>

<div class="container">
    <!-- Top Nav bar -->
    <div class="top-nav">
        <div>
            <h1>Search Blood Donors</h1>
            <div class="admin-meta" style="font-size: 12.5px; color: var(--muted); margin-top: 4px;">
                Searching across the network database
            </div>
        </div>
        <div>
            <a href="<?php echo h($dashboard_link); ?>" class="back-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                Back to Dashboard
            </a>
        </div>
    </div>
    
    <?php if ($error): ?>
        <div class="alert-error"><?php echo h($error); ?></div>
    <?php endif; ?>
    
    <div class="search-layout">
        <!-- Sidebar Filters Column -->
        <div class="card">
            <div class="card-header">
                <h2>Search Filters</h2>
            </div>
            
            <form action="donor-search.php" method="GET" class="filter-form">
                <!-- Search term -->
                <div class="filter-group">
                    <label for="search">Keyword Search</label>
                    <input type="text" id="search" name="search" value="<?php echo h($search); ?>" placeholder="Name, email, or phone...">
                </div>
                
                <!-- Blood type -->
                <div class="filter-group">
                    <label for="blood_type">Blood Type</label>
                    <select id="blood_type" name="blood_type">
                        <option value="">All Blood Types</option>
                        <option value="A+" <?php echo ($blood_type === 'A+') ? 'selected' : ''; ?>>A+</option>
                        <option value="A-" <?php echo ($blood_type === 'A-') ? 'selected' : ''; ?>>A-</option>
                        <option value="B+" <?php echo ($blood_type === 'B+') ? 'selected' : ''; ?>>B+</option>
                        <option value="B-" <?php echo ($blood_type === 'B-') ? 'selected' : ''; ?>>B-</option>
                        <option value="AB+" <?php echo ($blood_type === 'AB+') ? 'selected' : ''; ?>>AB+</option>
                        <option value="AB-" <?php echo ($blood_type === 'AB-') ? 'selected' : ''; ?>>AB-</option>
                        <option value="O+" <?php echo ($blood_type === 'O+') ? 'selected' : ''; ?>>O+</option>
                        <option value="O-" <?php echo ($blood_type === 'O-') ? 'selected' : ''; ?>>O-</option>
                    </select>
                </div>
                
                <!-- City (Dynamic list) -->
                <div class="filter-group">
                    <label for="city">City Location</label>
                    <select id="city" name="city">
                        <option value="">All Cities</option>
                        <?php foreach ($db_cities as $c): ?>
                            <option value="<?php echo h($c); ?>" <?php echo ($city === $c) ? 'selected' : ''; ?>>
                                <?php echo h($c); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Gender -->
                <div class="filter-group">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender">
                        <option value="">All Genders</option>
                        <option value="male" <?php echo ($gender === 'male') ? 'selected' : ''; ?>>Male</option>
                        <option value="female" <?php echo ($gender === 'female') ? 'selected' : ''; ?>>Female</option>
                        <option value="other" <?php echo ($gender === 'other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                
                <!-- Verification Status -->
                <div class="filter-group">
                    <label for="is_verified">Email Status</label>
                    <select id="is_verified" name="is_verified">
                        <option value="">All Statuses</option>
                        <option value="1" <?php echo ($is_verified === '1') ? 'selected' : ''; ?>>Verified Only</option>
                        <option value="0" <?php echo ($is_verified === '0') ? 'selected' : ''; ?>>Unverified Only</option>
                    </select>
                </div>
                
                <!-- Account Status -->
                <div class="filter-group">
                    <label for="status">Profile Status</label>
                    <select id="status" name="status">
                        <option value="">All Profiles</option>
                        <option value="active" <?php echo ($status === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="suspended" <?php echo ($status === 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                </div>
                
                <!-- Button row -->
                <div class="btn-row">
                    <button type="submit" class="btn-submit">Apply Filters</button>
                    <a href="donor-search.php" class="btn-reset" title="Reset Filters">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polyline points="23 4 23 10 17 10"></polyline>
                            <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                        </svg>
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Results Column -->
        <div class="card">
            <div class="card-header">
                <h2>Donor Search Results</h2>
                <span class="results-count"><?php echo count($donors); ?> matching donors</span>
            </div>
            
            <div style="overflow-x: auto;">
                <?php if (empty($donors)): ?>
                    <div class="empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <h3>No Donors Found</h3>
                        <p>Try modifying your keyword search or adjusting your dropdown filter criteria.</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Donor Profile</th>
                                <th>Blood Type</th>
                                <th>Contact Information</th>
                                <th>Location</th>
                                <th>Verification</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($donors as $d): ?>
                                <tr>
                                    <td>
                                        <div class="donor-meta">
                                            <div class="name">
                                                <!-- If donor has O-, add a crimson blood drop to highlight rare type -->
                                                <?php if ($d['blood_type'] === 'O-' || $d['blood_type'] === 'AB-'): ?>
                                                    <svg viewBox="0 0 32 32" title="Rare Type Emergency Drop">
                                                        <path d="M16 4C16 4 6 14.5 6 20.5C6 26.3 10.5 29 16 29C21.5 29 26 26.3 26 20.5C26 14.5 16 4 16 4Z"/>
                                                    </svg>
                                                <?php endif; ?>
                                                <?php echo h($d['full_name']); ?>
                                            </div>
                                            <div class="age-gender">
                                                Age: <?php echo calculate_age($d['date_of_birth']); ?> · <?php echo ucfirst(h($d['gender'])); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="type-chip"><?php echo h($d['blood_type']); ?></span>
                                    </td>
                                    <td>
                                        <div class="contact-info">
                                            <span><?php echo h($d['phone']); ?></span>
                                            <em><?php echo h($d['email']); ?></em>
                                        </div>
                                    </td>
                                    <td>
                                        <strong style="color: var(--ink);"><?php echo h($d['city']); ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($d['is_verified']): ?>
                                            <span class="badge badge-verified">Verified</span>
                                        <?php else: ?>
                                            <span class="badge badge-unverified">Unverified</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo h($d['status']); ?>">
                                            <?php echo ucfirst(h($d['status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

</body>
</html>
