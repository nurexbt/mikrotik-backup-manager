<?php
/**
 * Backup Rotation - Web Interface
 * 
 * Deletes backup files older than specified retention period
 * Can be run manually or via cron/scheduled task
 * 
 * Access: http://localhost/rtbackup/cleanup_old_backups.php
 * Or via cron: php /path/to/cleanup_old_backups.php
 */

session_start();

// Check if user is logged in (skip check if running from CLI)
if (php_sapi_name() !== 'cli' && (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin')) {
    die('Access denied. Admin privileges required.');
}

require_once 'config.php';

// Configuration
$retention_days = isset($_GET['days']) ? (int)$_GET['days'] : 90;
$dry_run = isset($_GET['dry_run']) && $_GET['dry_run'] === '1';
$cutoff_time = time() - ($retention_days * 24 * 60 * 60);

// Counters
$total_checked = 0;
$total_deleted = 0;
$total_size_freed = 0;
$details = [];

// Connect to FTP
$ftp_conn = @ftp_connect($settings['ftp_server'], 21, 10);
if (!$ftp_conn || !@ftp_login($ftp_conn, $settings['ftp_user'], $settings['ftp_pass'])) {
    die(json_encode(['error' => 'FTP connection failed']));
}

// Use active mode
@ftp_pasv($ftp_conn, false);

// Get all routers
$result = $conn->query("SELECT name FROM routers");
$routers = [];
while ($row = $result->fetch_assoc()) {
    $routers[] = $row['name'];
}

// Process each router directory
foreach ($routers as $router_name) {
    $files = @ftp_nlist($ftp_conn, $router_name);
    
    if (!is_array($files)) {
        continue;
    }
    
    foreach ($files as $file) {
        $filename = basename($file);
        
        // Only process backup files
        if (!preg_match('/\.(backup|rsc)$/i', $filename)) {
            continue;
        }
        
        $total_checked++;
        $full_path = $router_name . '/' . $filename;
        
        // Get file modified time
        $file_time = @ftp_mdtm($ftp_conn, $full_path);
        
        if ($file_time === -1) {
            continue; // Skip if can't get time
        }
        
        // Check if file is older than retention period
        if ($file_time < $cutoff_time) {
            $file_size = @ftp_size($ftp_conn, $full_path);
            $file_age_days = floor((time() - $file_time) / (24 * 60 * 60));
            
            $detail = [
                'router' => $router_name,
                'filename' => $filename,
                'age_days' => $file_age_days,
                'size' => $file_size,
                'date' => date('Y-m-d H:i:s', $file_time),
                'deleted' => false
            ];
            
            // Delete file (unless dry run)
            if (!$dry_run) {
                if (@ftp_delete($ftp_conn, $full_path)) {
                    $total_deleted++;
                    if ($file_size !== -1) {
                        $total_size_freed += $file_size;
                    }
                    $detail['deleted'] = true;
                }
            } else {
                $detail['deleted'] = 'DRY_RUN';
            }
            
            $details[] = $detail;
        }
    }
}

@ftp_close($ftp_conn);

// Format size
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Log the cleanup
$log_entry = [
    'timestamp' => date('Y-m-d H:i:s'),
    'retention_days' => $retention_days,
    'total_checked' => $total_checked,
    'total_deleted' => $total_deleted,
    'space_freed' => formatBytes($total_size_freed),
    'dry_run' => $dry_run
];

// If running from CLI, output JSON
if (php_sapi_name() === 'cli') {
    echo json_encode([
        'success' => true,
        'summary' => $log_entry,
        'details' => $details
    ], JSON_PRETTY_PRINT);
    exit;
}

// Otherwise, show HTML interface
?>
<!DOCTYPE html>
<html>
<head>
    <title>Backup Rotation Cleanup</title>
    <style>
        body { font-family: 'Arial', sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
        .stat-card { background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #4169e1; }
        .stat-value { font-size: 2em; font-weight: 700; color: #4169e1; }
        .stat-label { color: #666; margin-top: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e5e5e5; }
        th { background: #f8f9fa; font-weight: 600; }
        .success { color: #10b981; }
        .warning { color: #f59e0b; }
        .error { color: #ef4444; }
        .btn { display: inline-block; padding: 10px 20px; background: #4169e1; color: #fff; text-decoration: none; border-radius: 5px; margin: 5px; }
        .btn:hover { background: #3557c7; }
        .btn-secondary { background: #6c757d; }
        .btn-warning { background: #f59e0b; }
        .alert { padding: 15px; border-radius: 5px; margin: 15px 0; }
        .alert-info { background: #dbeafe; color: #1e40af; }
        .alert-success { background: #d1fae5; color: #065f46; }
        .alert-warning { background: #fef3c7; color: #92400e; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗑️ Backup Rotation Cleanup</h1>
        
        <?php if ($dry_run): ?>
            <div class="alert alert-warning">
                <strong>⚠️ DRY RUN MODE</strong><br>
                No files were actually deleted. This is a preview of what would be deleted.
            </div>
        <?php else: ?>
            <div class="alert alert-success">
                <strong>✓ Cleanup Completed</strong><br>
                Old backup files have been removed.
            </div>
        <?php endif; ?>
        
        <div class="summary">
            <div class="stat-card">
                <div class="stat-value"><?= $retention_days ?></div>
                <div class="stat-label">Retention Days</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $total_checked ?></div>
                <div class="stat-label">Files Checked</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= count($details) ?></div>
                <div class="stat-label">Old Files Found</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $total_deleted ?></div>
                <div class="stat-label">Files Deleted</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= formatBytes($total_size_freed) ?></div>
                <div class="stat-label">Space Freed</div>
            </div>
        </div>
        
        <?php if (count($details) > 0): ?>
            <h2>Deleted Files (<?= count($details) ?>)</h2>
            <table>
                <tr>
                    <th>Router</th>
                    <th>Filename</th>
                    <th>Age (Days)</th>
                    <th>Size</th>
                    <th>Last Modified</th>
                    <th>Status</th>
                </tr>
                <?php foreach ($details as $detail): ?>
                    <tr>
                        <td><?= htmlspecialchars($detail['router']) ?></td>
                        <td><?= htmlspecialchars($detail['filename']) ?></td>
                        <td><?= $detail['age_days'] ?></td>
                        <td><?= $detail['size'] !== -1 ? formatBytes($detail['size']) : 'Unknown' ?></td>
                        <td><?= $detail['date'] ?></td>
                        <td>
                            <?php if ($detail['deleted'] === true): ?>
                                <span class="success">✓ Deleted</span>
                            <?php elseif ($detail['deleted'] === 'DRY_RUN'): ?>
                                <span class="warning">⚠ Would Delete</span>
                            <?php else: ?>
                                <span class="error">✗ Failed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <div class="alert alert-info">
                <strong>ℹ️ No Old Files Found</strong><br>
                All backup files are within the retention period.
            </div>
        <?php endif; ?>
        
        <h2>Actions</h2>
        <a href="?days=<?= $retention_days ?>&dry_run=1" class="btn btn-warning">Preview Cleanup (Dry Run)</a>
        <a href="?days=90" class="btn">Run Cleanup (90 days)</a>
        <a href="?days=60" class="btn">Run Cleanup (60 days)</a>
        <a href="?days=30" class="btn">Run Cleanup (30 days)</a>
        <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
        
        <h2>Automation</h2>
        <div class="alert alert-info">
            <strong>Setup Automatic Cleanup:</strong><br><br>
            
            <strong>Option 1: Windows Scheduled Task</strong>
            <pre style="background: #1a1d29; color: #e2e8f0; padding: 10px; border-radius: 4px; overflow-x: auto;">
schtasks /create /tn "Backup Cleanup" /tr "php <?= __FILE__ ?>" /sc daily /st 02:00
            </pre>
            
            <strong>Option 2: Ubuntu Cron Job</strong>
            <pre style="background: #1a1d29; color: #e2e8f0; padding: 10px; border-radius: 4px; overflow-x: auto;">
# Edit crontab
crontab -e

# Add this line (runs daily at 2 AM)
0 2 * * * /usr/local/bin/cleanup_old_backups.sh >> /var/log/backup_cleanup.log 2>&1
            </pre>
        </div>
    </div>
</body>
</html>
