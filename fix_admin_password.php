<?php
require_once 'config.php';

echo "<h2>Fixing Admin Password...</h2>";

// Generate correct password hash for 'admin123'
$correct_password = password_hash('admin123', PASSWORD_DEFAULT);

// Update admin user password
$sql = "UPDATE users SET password = '$correct_password' WHERE username = 'admin'";

if ($conn->query($sql) === TRUE) {
    echo "<p style='color: green; font-size: 1.2rem;'>✓ Admin password has been reset successfully!</p>";
    echo "<hr>";
    echo "<p><strong>You can now login with:</strong></p>";
    echo "<ul style='font-size: 1.1rem;'>";
    echo "<li>Username: <strong style='color: #667eea;'>admin</strong></li>";
    echo "<li>Password: <strong style='color: #667eea;'>admin123</strong></li>";
    echo "</ul>";
    echo "<hr>";
    echo "<p><a href='login.php' style='padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block;'>Go to Login Page</a></p>";
    echo "<hr>";
    echo "<p style='color: #999; font-size: 0.9rem;'>⚠️ Delete this file (fix_admin_password.php) after successful login for security.</p>";
} else {
    echo "<p style='color: red;'>✗ Error updating password: " . $conn->error . "</p>";
    echo "<p>Make sure the users table exists and has an admin user.</p>";
}

$conn->close();
?>
