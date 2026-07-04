<?php
/**
 * Database Configuration and PDO Connection for BDMS
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'bdms');
define('DB_CHARSET', 'utf8mb4');

try {
    // Construct DSN
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    
    // Connection options
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // Native prepared statements
    ];
    
    // Instantiate PDO
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
} catch (PDOException $e) {
    // In coursework/debug environments we show the error message. 
    // In production we would log it and display a generic message.
    die("Database connection failed: " . $e->getMessage());
}
