<?php
/**
 * Create FTP Directories for All Routers
 * 
 * This script creates FTP backup directories for all routers in the database.
 * Run this once to set up the directory structure.
 * 
 * Access: http://localhost/rtbackup/create_ftp_directories.php
 */

require_once 'config.php';

// Fetch all routers
$routers = [];
$result = $conn->query("SELECT * FROM routers ORDER BY id ASC");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $routers[] = $row;
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Create FTP Directories</title>
    <style>
        body { font-family: 'Arial', sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-bottom: 20px; }
        .success { color: #10b981; background: #d1fae5; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: #ef4444; background: #fee2e2; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .warning { color: #f59e0b; background: #fef3c7; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: #3b82f6; background: #dbeafe; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 12px 24px; background: #4169e1; color: #fff; text-decoration: none; border-radius: 5px; margin: 10px 5px; font-weight: 600; }
        .btn:hover { background: #3557c7; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e5e5e5; }
        th { background: #f8f9fa; font-weight: 600; }
        .status-icon { font-size: 1.2em; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗂️ Create FTP Directories</h1>
        
        <?php if (count($routers) === 0): ?>
            <div class="warning">
                <strong>⚠️ No Routers Found</strong><br>
                Please add routers to the system first before creating directories.
            </div>
            <a href="index.php?page=routers" class="btn">Add Routers</a>
        <?php else: ?>
            <div class="info">
                <strong>ℹ️ About This Tool</strong><br>
                This will create FTP backup directories for all <?= count($routers) ?> routers in your system.
            </div>
            
            <?php
            // Connect to FTP
            $ftp_conn = @ftp_connect($settings['ftp_server'], 21, 5);
            
            if (!$ftp_conn) {
                echo '<div class="error"><strong>✗ FTP Connection Failed</strong><br>Cannot connect to ' . $settings['ftp_server'] . '</div>';
                echo '<a href="test_backup_stats.php" class="btn btn-secondary">Run Diagnostics</a>';
            } else {
                $login = @ftp_login($ftp_conn, $settings['ftp_user'], $settings['ftp_pass']);
                
                if (!$login) {
                    echo '<div class="error"><strong>✗ FTP Login Failed</strong><br>Check username and password in config.php</div>';
                    @ftp_close($ftp_conn);
                } else {
                    @ftp_pasv($ftp_conn, true);
                    
                    echo '<h2>Creating Directories...</h2>';
                    echo '<table>';
                    echo '<tr><th>Router Name</th><th>Directory</th><th>Status</th></tr>';
                    
                    $success_count = 0;
                    $already_exists = 0;
                    $failed_count = 0;
                    
                    foreach ($routers as $r) {
                        $router_name = $r['name'];
                        
                        // Check if directory already exists
                        $raw_list = @ftp_rawlist($ftp_conn, $router_name);
                        
                        echo '<tr>';
                        echo '<td><strong>' . htmlspecialchars($router_name) . '</strong></td>';
                        echo '<td><code>/home/ftpuser/' . htmlspecialchars($router_name) . '</code></td>';
                        
                        if (is_array($raw_list)) {
                            echo '<td class="success"><span class="status-icon">✓</span> Already Exists</td>';
                            $already_exists++;
                        } else {
                            // Try to create directory
                            $created = @ftp_mkdir($ftp_conn, $router_name);
                            
                            if ($created) {
                                // Set permissions (755)
                                @ftp_chmod($ftp_conn, 0755, $router_name);
                                echo '<td class="success"><span class="status-icon">✓</span> Created Successfully</td>';
                                $success_count++;
                            } else {
                                echo '<td class="error"><span class="status-icon">✗</span> Failed to Create</td>';
                                $failed_count++;
                            }
                        }
                        
                        echo '</tr>';
                    }
                    
                    echo '</table>';
                    
                    // Summary
                    echo '<div style="margin-top: 20px;">';
                    
                    if ($success_count > 0) {
                        echo '<div class="success"><strong>✓ Created:</strong> ' . $success_count . ' new directories</div>';
                    }
                    
                    if ($already_exists > 0) {
                        echo '<div class="info"><strong>ℹ️ Already Existed:</strong> ' . $already_exists . ' directories</div>';
                    }
                    
                    if ($failed_count > 0) {
                        echo '<div class="error"><strong>✗ Failed:</strong> ' . $failed_count . ' directories</div>';
                        echo '<div class="warning">';
                        echo '<strong>Possible Solutions:</strong><br>';
                        echo '1. Check FTP user permissions on Ubuntu server<br>';
                        echo '2. Run: <code>sudo chown -R ftpuser:ftpuser /home/ftpuser</code><br>';
                        echo '3. Run: <code>sudo chmod 755 /home/ftpuser</code>';
                        echo '</div>';
                    }
                    
                    if ($success_count > 0 || $already_exists > 0) {
                        echo '<div class="success" style="margin-top: 20px;">';
                        echo '<strong>🎉 All Set!</strong><br>';
                        echo 'FTP directories are ready. Now you can:<br>';
                        echo '1. Run MikroTik backup scripts to upload backups<br>';
                        echo '2. Check the dashboard for statistics';
                        echo '</div>';
                    }
                    
                    echo '</div>';
                    
                    @ftp_close($ftp_conn);
                }
            }
            ?>
            
            <div style="margin-top: 30px; text-align: center;">
                <a href="test_backup_stats.php" class="btn">Run Diagnostics</a>
                <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
