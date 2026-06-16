<?php
/**
 * Backup Statistics Test & Debug Tool
 * 
 * This page helps diagnose why backup statistics are not showing.
 * Access: http://localhost/rtbackup/test_backup_stats.php
 */

require_once 'config.php';

// Fetch routers
$routers = [];
$result = $conn->query("SELECT * FROM routers ORDER BY id DESC");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $routers[] = $row;
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Backup Statistics Debug</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1d29; color: #e2e8f0; }
        .section { background: #2d3748; padding: 15px; margin: 15px 0; border-radius: 8px; border-left: 4px solid #4169e1; }
        .success { color: #10b981; }
        .error { color: #ef4444; }
        .warning { color: #f59e0b; }
        .info { color: #3b82f6; }
        h2 { color: #fff; margin-top: 0; }
        pre { background: #1a1d29; padding: 10px; border-radius: 4px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #4a5568; }
        th { background: #374151; color: #fff; }
    </style>
</head>
<body>
    <h1>🔍 Backup Statistics Debug Tool</h1>
    
    <div class="section">
        <h2>1. FTP Connection Test</h2>
        <?php
        echo "<p><strong>FTP Server:</strong> {$settings['ftp_server']}</p>";
        echo "<p><strong>FTP User:</strong> {$settings['ftp_user']}</p>";
        
        $ftp_conn = @ftp_connect($settings['ftp_server'], 21, 5);
        if ($ftp_conn) {
            echo "<p class='success'>✓ FTP Connection: SUCCESS</p>";
            
            $login = @ftp_login($ftp_conn, $settings['ftp_user'], $settings['ftp_pass']);
            if ($login) {
                echo "<p class='success'>✓ FTP Login: SUCCESS</p>";
                
                $pasv = @ftp_pasv($ftp_conn, true);
                echo "<p class='success'>✓ Passive Mode: " . ($pasv ? "ENABLED" : "DISABLED") . "</p>";
            } else {
                echo "<p class='error'>✗ FTP Login: FAILED</p>";
            }
        } else {
            echo "<p class='error'>✗ FTP Connection: FAILED</p>";
        }
        ?>
    </div>

    <div class="section">
        <h2>2. Router Directories</h2>
        <?php
        if ($ftp_conn && $login) {
            echo "<p><strong>Total Routers:</strong> " . count($routers) . "</p>";
            
            if (count($routers) > 0) {
                echo "<table>";
                echo "<tr><th>Router Name</th><th>Directory Exists</th><th>Files Found</th></tr>";
                
                // Use ACTIVE mode
                @ftp_pasv($ftp_conn, false);
                
                foreach ($routers as $r) {
                    $router_dir = $r['name'];
                    $files = @ftp_nlist($ftp_conn, $router_dir);
                    
                    $exists = is_array($files) ? "YES" : "NO";
                    $file_count = is_array($files) ? count($files) : 0;
                    
                    $class = $exists === "YES" ? "success" : "error";
                    
                    echo "<tr>";
                    echo "<td>{$router_dir}</td>";
                    echo "<td class='{$class}'>{$exists}</td>";
                    echo "<td>{$file_count}</td>";
                    echo "</tr>";
                }
                
                echo "</table>";
            } else {
                echo "<p class='warning'>⚠ No routers found in database</p>";
            }
        } else {
            echo "<p class='error'>✗ Cannot check directories - FTP not connected</p>";
        }
        ?>
    </div>

    <div class="section">
        <h2>3. Backup Files Scan</h2>
        <?php
        if ($ftp_conn && $login) {
            $total_backups = 0;
            $total_size_bytes = 0;
            $last_backup_time = 0;
            $file_details = [];
            
            // Use ACTIVE mode
            @ftp_pasv($ftp_conn, false);
            
            foreach ($routers as $r) {
                $router_dir = $r['name'];
                $files = @ftp_nlist($ftp_conn, $router_dir);
                
                if (is_array($files)) {
                    foreach ($files as $file) {
                        $filename = basename($file);
                        
                        if ($filename === '.' || $filename === '..') {
                            continue;
                        }
                        
                        if (preg_match('/\.(backup|rsc)$/i', $filename)) {
                            $total_backups++;
                            
                            $full_path = $router_dir . '/' . $filename;
                            $size = @ftp_size($ftp_conn, $full_path);
                            $time = @ftp_mdtm($ftp_conn, $full_path);
                            
                            if ($size !== -1) {
                                $total_size_bytes += $size;
                            }
                            
                            if ($time !== -1 && $time > $last_backup_time) {
                                $last_backup_time = $time;
                            }
                            
                            $file_details[] = [
                                'router' => $router_dir,
                                'filename' => $filename,
                                'size' => $size !== -1 ? $size : 'Unknown',
                                'time' => $time !== -1 ? date('Y-m-d H:i:s', $time) : 'Unknown'
                            ];
                        }
                    }
                }
            }
            
            function formatBytes($bytes) {
                $units = array('B', 'KB', 'MB', 'GB', 'TB');
                $bytes = max($bytes, 0);
                $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
                $pow = min($pow, count($units) - 1);
                $bytes /= pow(1024, $pow);
                return round($bytes, 2) . ' ' . $units[$pow];
            }
            
            echo "<p><strong class='success'>Total Backups Found:</strong> {$total_backups}</p>";
            echo "<p><strong class='success'>Total Size:</strong> " . formatBytes($total_size_bytes) . " ({$total_size_bytes} bytes)</p>";
            echo "<p><strong class='success'>Last Backup:</strong> " . ($last_backup_time > 0 ? date('Y-m-d H:i:s', $last_backup_time) : 'N/A') . "</p>";
            
            if (count($file_details) > 0) {
                echo "<h3>Backup Files:</h3>";
                echo "<table>";
                echo "<tr><th>Router</th><th>Filename</th><th>Size</th><th>Modified</th></tr>";
                
                foreach ($file_details as $file) {
                    echo "<tr>";
                    echo "<td>{$file['router']}</td>";
                    echo "<td>{$file['filename']}</td>";
                    echo "<td>" . (is_numeric($file['size']) ? formatBytes($file['size']) : $file['size']) . "</td>";
                    echo "<td>{$file['time']}</td>";
                    echo "</tr>";
                }
                
                echo "</table>";
            } else {
                echo "<p class='warning'>⚠ No backup files found</p>";
            }
            
            @ftp_close($ftp_conn);
        } else {
            echo "<p class='error'>✗ Cannot scan files - FTP not connected</p>";
        }
        ?>
    </div>

    <div class="section">
        <h2>4. Server Stats API Test</h2>
        <?php
        $stats_url = "http://{$settings['ftp_server']}/server_stats.php";
        echo "<p><strong>API URL:</strong> <a href='{$stats_url}' target='_blank' style='color: #3b82f6;'>{$stats_url}</a></p>";
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 3,
                'ignore_errors' => true
            ]
        ]);
        
        $stats_json = @file_get_contents($stats_url, false, $context);
        
        if ($stats_json) {
            echo "<p class='success'>✓ API Response: SUCCESS</p>";
            
            $server_stats = json_decode($stats_json, true);
            
            if (isset($server_stats['backups'])) {
                echo "<p><strong>API Backup Stats:</strong></p>";
                echo "<pre>" . json_encode($server_stats['backups'], JSON_PRETTY_PRINT) . "</pre>";
            } else {
                echo "<p class='warning'>⚠ API does not include backup stats (update server_stats.php)</p>";
            }
            
            echo "<p><strong>Full API Response:</strong></p>";
            echo "<pre>" . json_encode($server_stats, JSON_PRETTY_PRINT) . "</pre>";
        } else {
            echo "<p class='error'>✗ API Response: FAILED</p>";
            echo "<p class='info'>Make sure server_stats.php is installed on the Ubuntu server at /var/www/html/</p>";
        }
        ?>
    </div>

    <div class="section">
        <h2>5. Recommendations</h2>
        <ul>
            <?php if (!$ftp_conn): ?>
                <li class='error'>✗ Fix FTP connection - check server IP and firewall</li>
            <?php endif; ?>
            
            <?php if ($ftp_conn && !$login): ?>
                <li class='error'>✗ Fix FTP login - check username/password</li>
            <?php endif; ?>
            
            <?php if (count($routers) === 0): ?>
                <li class='warning'>⚠ Add routers to the system</li>
            <?php endif; ?>
            
            <?php if (isset($total_backups) && $total_backups === 0): ?>
                <li class='warning'>⚠ No backup files found - run MikroTik backup script</li>
            <?php endif; ?>
            
            <?php if (!$stats_json): ?>
                <li class='info'>ℹ Install server_stats.php on Ubuntu server for better performance</li>
            <?php endif; ?>
            
            <?php if (isset($total_backups) && $total_backups > 0): ?>
                <li class='success'>✓ Everything looks good! Dashboard should show statistics.</li>
            <?php endif; ?>
        </ul>
        
        <?php
        // Check if directories need to be created
        $need_directories = false;
        if ($ftp_conn && $login && count($routers) > 0) {
            foreach ($routers as $r) {
                $raw_list = @ftp_rawlist($ftp_conn, $r['name']);
                if (!is_array($raw_list)) {
                    $need_directories = true;
                    break;
                }
            }
        }
        ?>
        
        <?php if ($need_directories): ?>
        <div style="margin-top: 20px; padding: 15px; background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 4px;">
            <h3 style="margin-top: 0; color: #92400e;">🔧 Quick Fix Available</h3>
            <p style="color: #78350f; margin: 10px 0;">Some router directories don't exist on the FTP server. Click below to create them automatically:</p>
            <a href="create_ftp_directories.php" style="display: inline-block; padding: 10px 20px; background: #f59e0b; color: #fff; text-decoration: none; border-radius: 5px; font-weight: 600; margin-top: 10px;">
                Create FTP Directories
            </a>
        </div>
        <?php endif; ?>
    </div>

    <p style="text-align: center; margin-top: 30px;">
        <a href="index.php" style="color: #3b82f6; text-decoration: none;">← Back to Dashboard</a>
    </p>
</body>
</html>
