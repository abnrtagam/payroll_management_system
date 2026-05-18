<?php
/**
 * Database Connection Configuration
 * ==================================
 * Uses PDO with prepared statements for all database operations.
 *
 * PDO is configured with:
 *   - ERRMODE_EXCEPTION: throws exceptions on SQL errors instead of silent failures
 *   - FETCH_ASSOC: returns associative arrays by default (column names as keys)
 *   - EMULATE_PREPARES off: uses real MySQL prepared statements for security
 *   - UTF-8 charset: ensures proper character encoding
 *
 * Usage:
 *   require_once __DIR__ . '/../config/db.php';
 *   // $pdo is now available as a PDO instance
 */

// ── Database credentials ──────────────────────────────────────
// Adjust these values if your XAMPP MySQL setup differs.
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'payroll_system');
define('DB_USER', 'root');
define('DB_PASS', '');           // Default XAMPP has no root password

// ── DSN (Data Source Name) ────────────────────────────────────
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    DB_HOST,
    DB_PORT,
    DB_NAME
);

// ── PDO options ───────────────────────────────────────────────
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// ── Create connection ─────────────────────────────────────────
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // In production, log the error instead of displaying it.
    // For this school project, we surface the message for debugging.
    http_response_code(500);
    echo '<h1>Database Connection Error</h1>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}
