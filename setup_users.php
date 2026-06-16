<?php
require_once 'config.php';

echo "<h2>Setting up Users Table...</h2>";

// Create users table
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    role VARCHAR(20) DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
)";

if ($conn->query($sql) === TRUE) {
    echo "<p style='color: green;'>✓ Users table created successfully!</p>";
} else {
    echo "<p style='color: red;'>✗ Error creating table: " . $conn->error . "</p>";
}

// Insert default admin user (password: admin123)
$default_password = password_hash('admin123', PASSWORD_DEFAULT);
$sql = "INSERT INTO users (username, password, full_name, email, role) 
        VALUES ('admin', '$default_password', 'Administrator', 'admin@example.com', 'admin')
        ON DUPLICATE KEY UPDATE username=username";

if ($conn->query($sql) === TRUE) {
    echo "<p style='color: green;'>✓ Default admin user created/verified!</p>";
    echo "<p><strong>Default Login Credentials:</strong></p>";
    echo "<ul>";
    echo "<li>Username: <strong>admin</strong></li>";
    echo "<li>Password: <strong>admin123</strong></li>";
    echo "</ul>";
} else {
    echo "<p style='color: red;'>✗ Error creating admin user: " . $conn->error . "</p>";
}

echo "<hr>";
echo "<p><a href='login.php' style='padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px;'>Go to Login Page</a></p>";
echo "<p style='color: #999; font-size: 0.9rem;'>You can delete this file (setup_users.php) after setup is complete.</p>";

$conn->close();
?>
