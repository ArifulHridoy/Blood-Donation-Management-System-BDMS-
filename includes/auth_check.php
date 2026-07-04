<?php
/**
 * Auth Check Guard for Protected Pages in BDMS
 */

require_once __DIR__ . '/functions.php';

// Enforce secure session initialization
start_secure_session();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // If request is AJAX, return JSON response
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    // Otherwise, redirect to login
    set_flash_message('error', 'Please log in to access this page.');
    header('Location: login.php');
    exit;
}

/**
 * Guard function to restrict access to specific roles.
 * Must be called immediately after including auth_check.php on protected pages.
 * 
 * @param array $allowed_roles Array of roles allowed to access (e.g. ['admin', 'recipient'])
 */
function checkRole(array $allowed_roles) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
        http_response_code(403);
        
        // Output a clean, styled error page rather than just plain text
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Forbidden — BDMS</title>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
            <style>
                :root {
                    --ink: #181A1B;
                    --paper: #F7F4EF;
                    --card: #FFFFFF;
                    --line: #E6E0D6;
                    --crimson: #A8201A;
                    --muted: #7A756C;
                    --rose: #F3DEDB;
                    --crimson-deep: #7A1712;
                }
                body {
                    background: var(--paper);
                    color: var(--ink);
                    font-family: 'Inter', sans-serif;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    margin: 0;
                    padding: 20px;
                }
                .error-card {
                    background: var(--card);
                    border: 1px solid var(--line);
                    border-radius: 14px;
                    padding: 40px 30px;
                    max-width: 460px;
                    text-align: center;
                    box-shadow: 0 8px 24px -12px rgba(24,26,27,0.12);
                }
                .error-icon {
                    width: 64px;
                    height: 64px;
                    background: var(--rose);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 20px;
                }
                .error-icon svg {
                    width: 32px;
                    height: 32px;
                    color: var(--crimson-deep);
                }
                h1 {
                    font-family: 'Fraunces', serif;
                    font-size: 24px;
                    margin: 0 0 10px;
                }
                p {
                    color: var(--muted);
                    font-size: 14.5px;
                    line-height: 1.6;
                    margin: 0 0 25px;
                }
                .btn {
                    display: inline-block;
                    background: var(--ink);
                    color: #fff;
                    text-decoration: none;
                    padding: 10px 24px;
                    border-radius: 8px;
                    font-weight: 600;
                    font-size: 13.5px;
                    transition: opacity 0.15s;
                }
                .btn:hover {
                    opacity: 0.9;
                }
            </style>
        </head>
        <body>
            <div class="error-card">
                <div class="error-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                </div>
                <h1>Access Denied</h1>
                <p>You do not have the required permissions to access this page. Please log in with a different account or return to your dashboard.</p>
                <a href="logout.php" class="btn">Log Out & Switch User</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}
