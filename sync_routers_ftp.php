<?php
/**
 * Sync Routers with FTP Directories
 * 
 * This tool helps match database routers with FTP directories
 * and shows which backups belong to which router.
 * 
 * Access: http://localhost/rtbackup/sync_routers_ftp.php
 */

require_once 'config.php';

// Fetch routers from database
$db_routers = [];
$result = $conn->query("SELECT * FROM routers ORDER BY id ASC");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $db_routers[] = $row;
    }
}

// Fetch directories from FTP
$ftp_dirs = [];
$ftp_conn = @ftp_connect($settings['ftp_server'], 21, 5);
if ($ftp_conn && @ftp_login($ftp_conn, $settings['ftp_user'], $settings['ftp_pass'])) {
    // Use ACTIVE mode (passive mode fails)
    @ftp_pasv($ftp_conn, false);
    
    // Get list of items in root directory
    $items = @ftp_nlist($ftp_conn, '.');
    if (is_array($items)) {
        foreach ($items as $item) {
            $name = basename($item);
            
            // Skip special entries and files
            if ($name === '.' || $name === '..' || strpos($name, '.') !== false) {
                continue;
            }
            
            // Count files in directory
            $files = @ftp_nlist($ftp_conn, $name);
            $file_count = 0;
            $backup_count = 0;
            
            if (is_array($files)) {
                foreach ($files as $file) {
                    $filename = basename($file);
                    if ($filename !== '.' && $filename !== '..') {
                        $file_count++;
                        if (preg_match('/\.(backup|rsc)$/i', $filename)) {
                            $backup_count++;
                        }
                    }
                }
            }
            
            $ftp_dirs[] = [
                'name' => $name,
                'file_count' => $file_count,
                'backup_count' => $backup_count
            ];
        }
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Sync Routers with FTP</title>
    <style>
        body { font-family: 'Arial', sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; border-bottom: 2px solid #4169e1; padding-bottom: 10px; }
        .success { color: #10b981; background: #d1fae5; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .error { color: #ef4444; background: #fee2e2; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .warning { color: #f59e0b; background: #fef3c7; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .info { color: #3b82f6; background: #dbeafe; padding: 15px; border-radius: 5px; margin: 15px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e5e5e5; }
        th { background: #f8f9fa; font-weight: 600; color: #333; }
        .matched { background: #d1fae5; }
        .unmatched { background: #fef3c7; }
        .btn { display: inline-block; padding: 10px 20px; background: #4169e1; color: #fff; text-decoration: none; border-radius: 5px; margin: 5px; font-weight: 600; }
        .btn:hover { background: #3557c7; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        .btn-small { padding: 6px 12px; font-size: 0.85em; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0; }
        .card { background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #4169e1; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 0.85em; font-weight: 600; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-info { background: #dbeafe; color: #1e40af; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Sync Routers with FTP Directories</h1>
        <p style="color: #666; margin-bottom: 20px;">This tool helps you match database routers with FTP backup directories.</p>
        
        <div class="grid">
            <div class="card">
                <h3 style="margin-top: 0;">📊 Database Routers</h3>
                <p><strong><?= count($db_routers) ?></strong> routers in database</p>
            </div>
            <div class="card">
                <h3 style="margin-top: 0;">📁 FTP Directories</h3>
                <p><strong><?= count($ftp_dirs) ?></strong> directories on FTP server</p>
            </div>
        </div>

        <h2>Database Routers</h2>
        <?php if (count($db_routers) > 0): ?>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Router Name</th>
                    <th>PPTP Username</th>
                    <th>FTP Directory Status</th>
                    <th>Backups</th>
                </tr>
                <?php foreach ($db_routers as $router): ?>
                    <?php
                    $matched = false;
                    $backup_count = 0;
                    foreach ($ftp_dirs as $dir) {
                        if ($dir['name'] === $router['name']) {
                            $matched = true;
                            $backup_count = $dir['backup_count'];
                            break;
                        }
                    }
                    ?>
                    <tr class="<?= $matched ? 'matched' : 'unmatched' ?>">
                        <td><?= $router['id'] ?></td>
                        <td><strong><?= htmlspecialchars($router['name']) ?></strong></td>
                        <td><?= htmlspecialchars($router['pptp_username']) ?></td>
                        <td>
                            <?php if ($matched): ?>
                                <span class="badge badge-success">✓ Directory Exists</span>
                            <?php else: ?>
                                <span class="badge badge-warning">⚠ Directory Missing</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($matched): ?>
                                <span class="badge badge-info"><?= $backup_count ?> files</span>
                            <?php else: ?>
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <div class="warning">No routers in database</div>
        <?php endif; ?>

        <h2>FTP Directories</h2>
        <?php if (count($ftp_dirs) > 0): ?>
            <table>
                <tr>
                    <th>Directory Name</th>
                    <th>Total Files</th>
                    <th>Backup Files</th>
                    <th>Database Match</th>
                    <th>Action</th>
                </tr>
                <?php foreach ($ftp_dirs as $dir): ?>
                    <?php
                    $matched = false;
                    foreach ($db_routers as $router) {
                        if ($router['name'] === $dir['name']) {
                            $matched = true;
                            break;
                        }
                    }
                    ?>
                    <tr class="<?= $matched ? 'matched' : 'unmatched' ?>">
                        <td><strong><?= htmlspecialchars($dir['name']) ?></strong></td>
                        <td><?= $dir['file_count'] ?></td>
                        <td><span class="badge badge-info"><?= $dir['backup_count'] ?> backups</span></td>
                        <td>
                            <?php if ($matched): ?>
                                <span class="badge badge-success">✓ Matched</span>
                            <?php else: ?>
                                <span class="badge badge-warning">⚠ No Match</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$matched && $dir['backup_count'] > 0): ?>
                                <a href="?add_router=<?= urlencode($dir['name']) ?>" class="btn btn-small">Add to Database</a>
                            <?php else: ?>
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <div class="warning">No directories found on FTP server</div>
        <?php endif; ?>

        <?php
        // Handle add router action
        if (isset($_GET['add_router'])) {
            $router_name = $conn->real_escape_string($_GET['add_router']);
            
            // Check if already exists
            $check = $conn->query("SELECT id FROM routers WHERE name = '$router_name'");
            if ($check->num_rows === 0) {
                // Add with default credentials
                $sql = "INSERT INTO routers (name, pptp_username, pptp_password) VALUES ('$router_name', 'user_$router_name', 'change_me')";
                if ($conn->query($sql)) {
                    echo '<div class="success"><strong>✓ Router Added!</strong><br>Router "' . htmlspecialchars($router_name) . '" has been added to the database. Please update the PPTP credentials.</div>';
                    echo '<script>setTimeout(function(){ window.location.href = "sync_routers_ftp.php"; }, 2000);</script>';
                } else {
                    echo '<div class="error"><strong>✗ Error:</strong> ' . $conn->error . '</div>';
                }
            } else {
                echo '<div class="warning"><strong>⚠ Already Exists:</strong> This router is already in the database.</div>';
            }
        }
        ?>

        <h2>Summary & Recommendations</h2>
        <?php
        $matched_count = 0;
        $missing_dirs = [];
        $orphan_dirs = [];
        
        foreach ($db_routers as $router) {
            $found = false;
            foreach ($ftp_dirs as $dir) {
                if ($dir['name'] === $router['name']) {
                    $found = true;
                    $matched_count++;
                    break;
                }
            }
            if (!$found) {
                $missing_dirs[] = $router['name'];
            }
        }
        
        foreach ($ftp_dirs as $dir) {
            $found = false;
            foreach ($db_routers as $router) {
                if ($router['name'] === $dir['name']) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $orphan_dirs[] = $dir['name'];
            }
        }
        ?>
        
        <div class="info">
            <strong>📊 Statistics:</strong><br>
            • Matched: <?= $matched_count ?> routers<br>
            • Missing Directories: <?= count($missing_dirs) ?><br>
            • Orphan Directories: <?= count($orphan_dirs) ?>
        </div>
        
        <?php if (count($missing_dirs) > 0): ?>
            <div class="warning">
                <strong>⚠ Missing FTP Directories:</strong><br>
                These routers are in the database but don't have FTP directories:<br>
                <ul style="margin: 10px 0;">
                    <?php foreach ($missing_dirs as $dir): ?>
                        <li><code><?= htmlspecialchars($dir) ?></code></li>
                    <?php endforeach; ?>
                </ul>
                <a href="create_ftp_directories.php" class="btn btn-small">Create Missing Directories</a>
            </div>
        <?php endif; ?>
        
        <?php if (count($orphan_dirs) > 0): ?>
            <div class="warning">
                <strong>⚠ Orphan FTP Directories:</strong><br>
                These directories exist on FTP but are not in the database:<br>
                <ul style="margin: 10px 0;">
                    <?php foreach ($orphan_dirs as $dir): ?>
                        <li><code><?= htmlspecialchars($dir) ?></code></li>
                    <?php endforeach; ?>
                </ul>
                <p>You can add them to the database using the "Add to Database" buttons above.</p>
            </div>
        <?php endif; ?>
        
        <?php if ($matched_count === count($db_routers) && $matched_count === count($ftp_dirs)): ?>
            <div class="success">
                <strong>🎉 Perfect Match!</strong><br>
                All routers in the database have matching FTP directories, and all FTP directories have matching database entries.
            </div>
        <?php endif; ?>

        <div style="margin-top: 30px; text-align: center;">
            <a href="test_backup_stats.php" class="btn">Run Diagnostics</a>
            <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
    </div>
</body>
</html>

<?php
if ($ftp_conn) {
    @ftp_close($ftp_conn);
}
?>
