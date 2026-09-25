<?php
// ==========================================================
// DATABASE CONNECTION CONFIGURATION (db_connect.php)
// ==========================================================

$host = "localhost";
$user = "root";
$pass = ""; 
$db   = "spms";
$port = 3307;

// CRITICAL: Force MySQLi to throw real exceptions on errors
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = mysqli_connect($host, $user, $pass, $db, $port);
    mysqli_set_charset($conn, "utf8mb4");
} catch (Exception $e) {
    die("<div style='background: #ffebee; color: #c62828; padding: 20px; font-family: monospace; border: 1px solid #ef9a9a;'>
        <strong>Database Connection Error:</strong> " . $e->getMessage() . "
        </div>");
}
?>