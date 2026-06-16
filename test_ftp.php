<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config.php';

$test_results = [];

// Test 1: FTP Connection
$test_results['connection'] = [
    'name' => 'FTP Server Connection',
    'status' => 'testing',
    'message' => ''
];

$ftp_conn = @ftp_connect($settings['ftp_server'], 21, 10);
if ($ftp_conn) {
    $test_results['connection']['status'] = 'success';
    $test_results['connection']['message'] = "Successfully connected to {$settings['ftp_server']}:21";
    
    // Test 2: FTP Login
    $test_results['login'] = [
        'name' => 'FTP Login',
        'status' => 'testing',
        'message' => ''
    ];
    
    $login = @ftp_login($ftp_conn, $settings['ftp_user'], $settings['ftp_pass']);
    if ($login) {
        $test_results['login']['status'] = 'success';
        $test_results['login']['message'] = "Successfully logged in as {$settings['ftp_user']}";
        
        // Test 3: Passive Mode
        $test_results['passive'] = [
            'name' => 'Passive Mode',
            'status' => 'testing',
            'message' => ''
        ];
        
        $passive = @ftp_pasv($ftp_conn, true);
        if ($passive) {
            $test_results['passive']['status'] = 'success';
            $test_results['passive']['message'] = "Passive mode enabled successfully";
        } else {
            $test_results['passive']['status'] = 'warning';
            $test_results['passive']['message'] = "Could not enable passive mode (may still work)";
        }
        
        // Test 4: List Directory
        $test_results['list'] = [
            'name' => 'List Root Directory',
            'status' => 'testing',
            'message' => ''
        ];
        
        $files = @ftp_nlist($ftp_conn, ".");
        if ($files !== false) {
            $test_results['list']['status'] = 'success';
            $test_results['list']['message'] = "Found " . count($files) . " items in root directory";
            $test_results['list']['files'] = $files;
        } else {
            $test_results['list']['status'] = 'error';
            $test_results['list']['message'] = "Could not list directory contents";
        }
        
        // Test 5: Create Test Directory
        $test_results['mkdir'] = [
            'name' => 'Create Test Directory',
            'status' => 'testing',
            'message' => ''
        ];
        
        $test_dir = "test_" . time();
        if (@ftp_mkdir($ftp_conn, $test_dir)) {
            $test_results['mkdir']['status'] = 'success';
            $test_results['mkdir']['message'] = "Successfully created directory: $test_dir";
            
            // Test 6: Upload Test File
            $test_results['upload'] = [
                'name' => 'Upload Test File',
                'status' => 'testing',
                'message' => ''
            ];
            
            $test_content = "Test backup file created at " . date('Y-m-d H:i:s');
            $temp_file = tempnam(sys_get_temp_dir(), 'ftp_test_');
            file_put_contents($temp_file, $test_content);
            
            $remote_file = "$test_dir/test.txt";
            if (@ftp_put($ftp_conn, $remote_file, $temp_file, FTP_BINARY)) {
                $test_results['upload']['status'] = 'success';
                $test_results['upload']['message'] = "Successfully uploaded test file to $remote_file";
                
                // Verify file size
                $size = @ftp_size($ftp_conn, $remote_file);
                if ($size !== -1) {
                    $test_results['upload']['message'] .= " (Size: $size bytes)";
                }
                
                // Clean up test file
                @ftp_delete($ftp_conn, $remote_file);
            } else {
                $test_results['upload']['status'] = 'error';
                $test_results['upload']['message'] = "Failed to upload test file";
            }
            
            unlink($temp_file);
            
            // Clean up test directory
            @ftp_rmdir($ftp_conn, $test_dir);
        } else {
            $test_results['mkdir']['status'] = 'error';
            $test_results['mkdir']['message'] = "Could not create test directory (check permissions)";
        }
        
    } else {
        $test_results['login']['status'] = 'error';
        $test_results['login']['message'] = "Login failed with username: {$settings['ftp_user']}";
    }
    
    @ftp_close($ftp_conn);
} else {
    $test_results['connection']['status'] = 'error';
    $test_results['connection']['message'] = "Could not connect to {$settings['ftp_server']}:21";
}

// Test 7: Check Router Directories
$test_results['routers'] = [
    'name' => 'Router Backup Directories',
    'status' => 'info',
    'message' => '',
    'directories' => []
];

$routers_result = $conn->query("SELECT * FROM routers");
if ($routers_result->num_rows > 0) {
    $ftp_conn = @ftp_connect($settings['ftp_server'], 21, 10);
    if ($ftp_conn && @ftp_login($ftp_conn, $settings['ftp_user'], $settings['ftp_pass'])) {
        while ($router = $routers_result->fetch_assoc()) {
            $dir_info = [
                'name' => $router['name'],
                'exists' => false,
                'files' => 0
            ];
            
            $files = @ftp_nlist($ftp_conn, $router['name']);
            if ($files !== false) {
                $dir_info['exists'] = true;
                $dir_info['files'] = count(array_filter($files, function($f) {
                    return basename($f) !== '.' && basename($f) !== '..';
                }));
            }
            
            $test_results['routers']['directories'][] = $dir_info;
        }
        @ftp_close($ftp_conn);
    }
    $test_results['routers']['message'] = "Checked " . count($test_results['routers']['directories']) . " router directories";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FTP Connection Test - MikroTik Backup Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #f5f5f5;
            padding: 2rem;
        }
        .test-card {
            background: #fff;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .test-item {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 8px;
            border-left: 4px solid #ccc;
        }
        .test-item.success {
            background: #d4edda;
            border-left-color: #28a745;
        }
        .test-item.error {
            background: #f8d7da;
            border-left-color: #dc3545;
        }
        .test-item.warning {
            background: #fff3cd;
            border-left-color: #ffc107;
        }
        .test-item.info {
            background: #d1ecf1;
            border-left-color: #17a2b8;
        }
        .test-icon {
            font-size: 1.5rem;
            margin-right: 1rem;
        }
        .config-box {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            font-family: monospace;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="container" style="max-width: 900px;">
        <div class="test-card">
            <h1 style="margin-bottom: 0.5rem;">
                <i class="fa-solid fa-network-wired" style="color: #4169e1;"></i>
                FTP CONNECTION DIAGNOSTIC
            </h1>
            <p style="color: #666; margin-bottom: 2rem;">Testing FTP server connectivity and upload functionality</p>
            
            <div class="config-box">
                <strong>Current FTP Configuration:</strong><br>
                Server: <?= htmlspecialchars($settings['ftp_server']) ?>:21<br>
                Username: <?= htmlspecialchars($settings['ftp_user']) ?><br>
                Password: <?= str_repeat('*', strlen($settings['ftp_pass'])) ?>
            </div>
        </div>

        <?php foreach ($test_results as $key => $test): ?>
            <?php if ($key === 'routers'): ?>
                <div class="test-card">
                    <h3><i class="fa-solid fa-folder" style="color: #ff6b35;"></i> <?= $test['name'] ?></h3>
                    <p style="color: #666;"><?= $test['message'] ?></p>
                    
                    <?php if (!empty($test['directories'])): ?>
                        <table class="table" style="margin-top: 1rem;">
                            <thead>
                                <tr>
                                    <th>Router Name</th>
                                    <th>Directory Exists</th>
                                    <th>Backup Files</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($test['directories'] as $dir): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($dir['name']) ?></strong></td>
                                    <td>
                                        <?php if ($dir['exists']): ?>
                                            <span style="color: #28a745;"><i class="fa-solid fa-check-circle"></i> Yes</span>
                                        <?php else: ?>
                                            <span style="color: #dc3545;"><i class="fa-solid fa-times-circle"></i> No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $dir['files'] ?> files</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="test-item <?= $test['status'] ?>">
                    <div style="display: flex; align-items: center;">
                        <div class="test-icon">
                            <?php if ($test['status'] === 'success'): ?>
                                <i class="fa-solid fa-check-circle" style="color: #28a745;"></i>
                            <?php elseif ($test['status'] === 'error'): ?>
                                <i class="fa-solid fa-times-circle" style="color: #dc3545;"></i>
                            <?php elseif ($test['status'] === 'warning'): ?>
                                <i class="fa-solid fa-exclamation-triangle" style="color: #ffc107;"></i>
                            <?php else: ?>
                                <i class="fa-solid fa-info-circle" style="color: #17a2b8;"></i>
                            <?php endif; ?>
                        </div>
                        <div style="flex: 1;">
                            <h5 style="margin: 0;"><?= $test['name'] ?></h5>
                            <p style="margin: 0.25rem 0 0 0; color: #666;"><?= $test['message'] ?></p>
                            
                            <?php if (isset($test['files']) && is_array($test['files'])): ?>
                                <details style="margin-top: 0.5rem;">
                                    <summary style="cursor: pointer; color: #4169e1;">Show files (<?= count($test['files']) ?>)</summary>
                                    <ul style="margin-top: 0.5rem; font-size: 0.9rem;">
                                        <?php foreach ($test['files'] as $file): ?>
                                            <li><?= htmlspecialchars($file) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </details>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <div class="test-card">
            <h3><i class="fa-solid fa-lightbulb" style="color: #ffc107;"></i> Troubleshooting Tips</h3>
            <ul style="line-height: 2;">
                <li><strong>Connection Failed:</strong> Check if FTP server is running and firewall allows port 21</li>
                <li><strong>Login Failed:</strong> Verify FTP username and password in config.php</li>
                <li><strong>Upload Failed:</strong> Check FTP user has write permissions to the directory</li>
                <li><strong>Directory Not Found:</strong> Router backup folders are created automatically when adding routers</li>
                <li><strong>MikroTik Script:</strong> Make sure the router can reach the PPTP server (<?= $settings['pptp_server'] ?>)</li>
            </ul>
            
            <div style="margin-top: 2rem; padding-top: 2rem; border-top: 2px solid #e5e5e5;">
                <a href="index.php" class="btn btn-primary" style="margin-right: 1rem;">
                    <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
                </a>
                <a href="test_ftp.php" class="btn btn-secondary">
                    <i class="fa-solid fa-rotate-right"></i> Run Test Again
                </a>
            </div>
        </div>
    </div>
</body>
</html>
