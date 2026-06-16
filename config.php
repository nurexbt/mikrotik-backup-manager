<?php
session_start();

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'rtbackup';

mysqli_report(MYSQLI_REPORT_STRICT);

try {
    // Create connection (passing null for database so we can create it)
    $conn = new mysqli($db_host, $db_user, $db_pass, null, 3306);
    
    // Create database if not exists
    $sql = "CREATE DATABASE IF NOT EXISTS `$db_name`";
    if ($conn->query($sql) !== TRUE) {
        die("Error creating database: " . $conn->error);
    }
    
    $conn->select_db($db_name);
    
    // Create routers table
    $sql = "CREATE TABLE IF NOT EXISTS `routers` (
        `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `pptp_username` VARCHAR(255) NOT NULL,
        `pptp_password` VARCHAR(255) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    if ($conn->query($sql) !== TRUE) {
        die("Error creating table: " . $conn->error);
    }
} catch (mysqli_sql_exception $e) {
    die("<div style='font-family: sans-serif; padding: 2rem; background: #fff3cd; color: #856404; border: 1px solid #ffeeba; border-radius: 5px; margin: 2rem; text-align: center;'>
        <h2>Database Connection Failed</h2>
        <p><strong>MySQL is not running!</strong></p>
        <p>Please open your <b>XAMPP Control Panel</b> and click <b>Start</b> next to MySQL.</p>
        <p style='font-size: 0.8em; color: #666; margin-top: 1rem;'>Technical Details: " . $e->getMessage() . "</p>
    </div>");
}



// Global settings
$settings = [
    'ftp_server' => '103.166.230.228', // Public Ubuntu Server IP for FTP access
    'ftp_user' => 'ftpuser',
    'ftp_pass' => 'nobody',
    'pptp_server' => '103.166.230.228' // Public Ubuntu Server IP for PPTP connection
];
?>
