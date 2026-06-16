<?php
/**
 * Detailed FTP Connection Test
 * Tests every step of the FTP connection to find the exact issue
 */

require_once 'config.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>FTP Connection Test</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1d29; color: #e2e8f0; }
        .section { background: #2d3748; padding: 15px; margin: 15px 0; border-radius: 8px; }
        .success { color: #10b981; }
        .error { color: #ef4444; }
        .warning { color: #f59e0b; }
        h2 { color: #fff; }
        pre { background: #1a1d29; padding: 10px; border-radius: 4px; overflow-x: auto; white-space: pre-wrap; }
        .step { margin: 10px 0; padding: 10px; background: #374151; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>🔍 Detailed FTP Connection Test</h1>
    
    <div class="section">
        <h2>Configuration</h2>
        <pre>FTP Server: <?= $settings['ftp_server'] ?>
FTP User: <?= $settings['ftp_user'] ?>
FTP Pass: <?= str_repeat('*', strlen($settings['ftp_pass'])) ?></pre>
    </div>

    <div class="section">
        <h2>Step-by-Step Connection Test</h2>
        
        <?php
        $all_success = true;
        
        // Step 1: Connect
        echo '<div class="step">';
        echo '<strong>Step 1: Connecting to FTP server...</strong><br>';
        $ftp_conn = @ftp_connect($settings['ftp_server'], 21, 10);
        if ($ftp_conn) {
            echo '<span class="success">✓ Connection established</span><br>';
            echo 'Connection resource: ' . get_resource_type($ftp_conn);
        } else {
            echo '<span class="error">✗ Connection failed</span><br>';
            echo 'Error: Could not connect to ' . $settings['ftp_server'] . ':21<br>';
            echo 'Possible causes:<br>';
            echo '- Firewall blocking port 21<br>';
            echo '- vsftpd not running<br>';
            echo '- Wrong IP address';
            $all_success = false;
        }
        echo '</div>';
        
        if ($ftp_conn) {
            // Step 2: Login
            echo '<div class="step">';
            echo '<strong>Step 2: Logging in...</strong><br>';
            $login = @ftp_login($ftp_conn, $settings['ftp_user'], $settings['ftp_pass']);
            if ($login) {
                echo '<span class="success">✓ Login successful</span>';
            } else {
                echo '<span class="error">✗ Login failed</span><br>';
                echo 'Error: Invalid username or password<br>';
                echo 'Check credentials in config.php';
                $all_success = false;
            }
            echo '</div>';
            
            if ($login) {
                // Step 3: Get system type
                echo '<div class="step">';
                echo '<strong>Step 3: Getting system type...</strong><br>';
                $systype = @ftp_systype($ftp_conn);
                if ($systype) {
                    echo '<span class="success">✓ System type: ' . $systype . '</span>';
                } else {
                    echo '<span class="warning">⚠ Could not get system type</span>';
                }
                echo '</div>';
                
                // Step 4: Get current directory
                echo '<div class="step">';
                echo '<strong>Step 4: Getting current directory...</strong><br>';
                $pwd = @ftp_pwd($ftp_conn);
                if ($pwd) {
                    echo '<span class="success">✓ Current directory: ' . $pwd . '</span>';
                } else {
                    echo '<span class="error">✗ Could not get current directory</span>';
                    $all_success = false;
                }
                echo '</div>';
                
                // Step 5: Try passive mode OFF
                echo '<div class="step">';
                echo '<strong>Step 5: Testing with PASSIVE MODE OFF...</strong><br>';
                @ftp_pasv($ftp_conn, false);
                $list_active = @ftp_nlist($ftp_conn, '.');
                if (is_array($list_active) && count($list_active) > 0) {
                    echo '<span class="success">✓ Active mode works! Found ' . count($list_active) . ' items</span><br>';
                    echo '<pre>' . print_r($list_active, true) . '</pre>';
                } else {
                    echo '<span class="error">✗ Active mode failed</span><br>';
                    echo 'Result: ' . (is_array($list_active) ? 'Empty array' : 'FALSE') . '<br>';
                }
                echo '</div>';
                
                // Step 6: Try passive mode ON
                echo '<div class="step">';
                echo '<strong>Step 6: Testing with PASSIVE MODE ON...</strong><br>';
                $pasv_result = @ftp_pasv($ftp_conn, true);
                echo 'Passive mode set: ' . ($pasv_result ? 'YES' : 'NO') . '<br>';
                
                $list_passive = @ftp_nlist($ftp_conn, '.');
                if (is_array($list_passive) && count($list_passive) > 0) {
                    echo '<span class="success">✓ Passive mode works! Found ' . count($list_passive) . ' items</span><br>';
                    echo '<pre>' . print_r($list_passive, true) . '</pre>';
                } else {
                    echo '<span class="error">✗ Passive mode failed</span><br>';
                    echo 'Result: ' . (is_array($list_passive) ? 'Empty array' : 'FALSE') . '<br>';
                    echo '<br><strong>This is likely the problem!</strong><br>';
                    echo 'Passive mode is not working. Check:<br>';
                    echo '- Firewall allows ports 40000-40100<br>';
                    echo '- vsftpd.conf has pasv_address=' . $settings['ftp_server'] . '<br>';
                    echo '- vsftpd.conf has pasv_min_port=40000<br>';
                    echo '- vsftpd.conf has pasv_max_port=40100';
                    $all_success = false;
                }
                echo '</div>';
                
                // Step 7: Try ftp_rawlist
                echo '<div class="step">';
                echo '<strong>Step 7: Testing ftp_rawlist()...</strong><br>';
                @ftp_pasv($ftp_conn, true);
                $rawlist = @ftp_rawlist($ftp_conn, '.');
                if (is_array($rawlist) && count($rawlist) > 0) {
                    echo '<span class="success">✓ ftp_rawlist works! Found ' . count($rawlist) . ' items</span><br>';
                    echo '<pre>';
                    foreach ($rawlist as $item) {
                        echo htmlspecialchars($item) . "\n";
                    }
                    echo '</pre>';
                } else {
                    echo '<span class="error">✗ ftp_rawlist failed</span><br>';
                    echo 'Result: ' . (is_array($rawlist) ? 'Empty array' : 'FALSE');
                    $all_success = false;
                }
                echo '</div>';
                
                // Step 8: Try to list a specific router directory
                echo '<div class="step">';
                echo '<strong>Step 8: Testing specific directory (HOME-RT)...</strong><br>';
                
                // First check if directory exists
                $chdir = @ftp_chdir($ftp_conn, 'HOME-RT');
                if ($chdir) {
                    echo '<span class="success">✓ Can change to HOME-RT directory</span><br>';
                    
                    $pwd_new = @ftp_pwd($ftp_conn);
                    echo 'Current directory: ' . $pwd_new . '<br>';
                    
                    $files = @ftp_nlist($ftp_conn, '.');
                    if (is_array($files)) {
                        echo '<span class="success">✓ Can list files: ' . count($files) . ' items</span><br>';
                        echo '<pre>' . print_r($files, true) . '</pre>';
                    } else {
                        echo '<span class="error">✗ Cannot list files in directory</span>';
                    }
                    
                    // Go back to root
                    @ftp_cdup($ftp_conn);
                } else {
                    echo '<span class="error">✗ Cannot change to HOME-RT directory</span><br>';
                    echo 'Directory may not exist or no permissions';
                }
                echo '</div>';
                
                // Step 9: Check PHP FTP functions
                echo '<div class="step">';
                echo '<strong>Step 9: PHP FTP Extension Info...</strong><br>';
                echo 'FTP extension loaded: ' . (extension_loaded('ftp') ? '<span class="success">YES</span>' : '<span class="error">NO</span>') . '<br>';
                echo 'PHP Version: ' . phpversion() . '<br>';
                echo 'FTP functions available: ' . (function_exists('ftp_connect') ? '<span class="success">YES</span>' : '<span class="error">NO</span>');
                echo '</div>';
            }
            
            @ftp_close($ftp_conn);
        }
        ?>
    </div>

    <div class="section">
        <h2>Summary</h2>
        <?php if ($all_success): ?>
            <p class="success"><strong>✓ All tests passed!</strong></p>
            <p>FTP connection is working correctly. The issue must be elsewhere.</p>
        <?php else: ?>
            <p class="error"><strong>✗ Some tests failed</strong></p>
            <p>Review the failed steps above to identify the issue.</p>
        <?php endif; ?>
        
        <h3>Common Solutions:</h3>
        <ol>
            <li><strong>If passive mode fails:</strong>
                <pre>sudo ufw allow 40000:40100/tcp
sudo nano /etc/vsftpd.conf
# Add: pasv_address=<?= $settings['ftp_server'] ?>

sudo systemctl restart vsftpd</pre>
            </li>
            
            <li><strong>If connection fails:</strong>
                <pre>sudo ufw allow 21/tcp
sudo systemctl restart vsftpd</pre>
            </li>
            
            <li><strong>If login fails:</strong>
                <pre>sudo passwd ftpuser
# Set password to: nobody</pre>
            </li>
        </ol>
    </div>

    <p style="text-align: center; margin-top: 30px;">
        <a href="test_backup_stats.php" style="color: #3b82f6;">← Back to Diagnostics</a> |
        <a href="index.php" style="color: #3b82f6;">Dashboard</a>
    </p>
</body>
</html>
