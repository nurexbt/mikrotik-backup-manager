<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config.php';

$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Handle AJAX theme change BEFORE any output
if (isset($_POST['ajax_change_theme'])) {
    $theme = $conn->real_escape_string($_POST['theme']);
    $_SESSION['theme'] = $theme;
    echo json_encode(['success' => true]);
    exit;
}

// Handle AJAX logs
if (isset($_GET['ajax_logs'])) {
    $type = $_GET['ajax_logs'];
    if ($type === 'pptp') {
        $logs = shell_exec("grep -iE 'pppd|pptpd' /var/log/syslog | tail -n 50 2>&1");
        if (strpos($logs, 'Permission denied') !== false || empty(trim($logs))) {
            echo "<div style='color: #ef4444; margin-bottom: 1rem;'><i class='fa-solid fa-triangle-exclamation'></i> Permission Denied or No Logs Found</div>";
            echo "<div style='color: #cbd5e1;'>To allow this web page to read the terminal logs, run this command on your Ubuntu server:</div>";
            echo "<div style='color: #fff; padding: 1rem; background: rgba(255,255,255,0.1); border-radius: 4px; margin-top: 0.5rem;'>sudo usermod -aG adm www-data && sudo systemctl restart apache2</div>";
        } else {
            echo htmlspecialchars($logs);
        }
    } elseif ($type === 'ftp') {
        $logs = shell_exec("if [ -f /var/log/vsftpd.log ]; then tail -n 50 /var/log/vsftpd.log 2>&1; else grep -iE 'vsftpd|ftp' /var/log/syslog | tail -n 50 2>&1; fi");
        if (strpos($logs, 'Permission denied') !== false || empty(trim($logs))) {
            echo "<div style='color: #ef4444; margin-bottom: 1rem;'><i class='fa-solid fa-triangle-exclamation'></i> Permission Denied or No Logs Found</div>";
            echo "<div style='color: #cbd5e1;'>To allow this web page to read the FTP logs, run these commands on your Ubuntu server:</div>";
            echo "<div style='color: #fff; padding: 1rem; background: rgba(255,255,255,0.1); border-radius: 4px; margin-top: 0.5rem;'>sudo touch /var/log/vsftpd.log<br>sudo chmod 644 /var/log/vsftpd.log<br>sudo usermod -aG adm www-data<br>sudo systemctl restart apache2</div>";
        } else {
            echo htmlspecialchars($logs);
        }
    }
    exit;
}

// Handle file downloads securely
if (isset($_GET['download']) && isset($_GET['router_id'])) {
    $r_id = (int)$_GET['router_id'];
    $file = basename($_GET['download']); // Prevent directory traversal
    
    $res = $conn->query("SELECT name FROM routers WHERE id = $r_id");
    if ($res->num_rows > 0) {
        $router_name = $res->fetch_assoc()['name'];
        $remote_file = $router_name . "/" . $file;
        
        $ftp_conn = @ftp_connect($settings['ftp_server'], 21, 2);
        if ($ftp_conn && @ftp_login($ftp_conn, $settings['ftp_user'], $settings['ftp_pass'])) {
            $size = @ftp_size($ftp_conn, $remote_file);
            if ($size !== -1) {
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="'.$file.'"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . $size);
                
                // Stream the file directly from FTP to the browser
                @ftp_pasv($ftp_conn, true);
                $stream = fopen('php://output', 'w');
                @ftp_fget($ftp_conn, $stream, $remote_file, FTP_BINARY, 0);
                fclose($stream);
                @ftp_close($ftp_conn);
                exit;
            }
        }
    }
}

// Handle form submissions
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_router') {
            $name = $conn->real_escape_string($_POST['name']);
            $pptp_user = $conn->real_escape_string($_POST['pptp_username']);
            $pptp_pass = $conn->real_escape_string($_POST['pptp_password']);

            $sql = "INSERT INTO routers (name, pptp_username, pptp_password) VALUES ('$name', '$pptp_user', '$pptp_pass')";
            if ($conn->query($sql) === TRUE) {
                $success_msg = "Router saved!";
                
                // Automatically write to the Ubuntu PPTP secrets file
                $secret_file = '/etc/ppp/chap-secrets';
                $secret_line = "\n" . $_POST['pptp_username'] . " * " . $_POST['pptp_password'] . " *";
                
                if (is_writable($secret_file)) {
                    file_put_contents($secret_file, $secret_line, FILE_APPEND);
                    $success_msg = "Success! Router saved & VPN credentials automatically synced!";
                } else {
                    $error_msg = "Router saved, but couldn't auto-sync VPN credentials. You MUST run the permission fix command on Ubuntu!";
                }
                
                // Automatically create FTP backup folder for this router using FTP connection
                // This bypasses www-data permission issues by logging in as the actual FTP user
                $ftp_conn = @ftp_connect($settings['ftp_server'], 21, 2);
                if ($ftp_conn) {
                    $login = @ftp_login($ftp_conn, $settings['ftp_user'], $settings['ftp_pass']);
                    if ($login) {
                        if (@ftp_mkdir($ftp_conn, $name)) {
                            @ftp_chmod($ftp_conn, 0755, $name);
                        }
                    }
                    @ftp_close($ftp_conn);
                }
                
                // Redirect to routers page
                header("Location: ?page=routers&success=1");
                exit;
            } else {
                $error_msg = "Error: " . $conn->error;
            }
        }
        
        // Edit Router
        elseif ($_POST['action'] === 'edit_router') {
            $router_id = (int)$_POST['router_id'];
            $new_name = $conn->real_escape_string($_POST['name']);
            $pptp_user = $conn->real_escape_string($_POST['pptp_username']);
            $pptp_pass = $conn->real_escape_string($_POST['pptp_password']);
            
            // Get old router name for directory rename
            $old_res = $conn->query("SELECT name FROM routers WHERE id = $router_id");
            if ($old_res->num_rows > 0) {
                $old_name = $old_res->fetch_assoc()['name'];
                
                // Update database
                $sql = "UPDATE routers SET name='$new_name', pptp_username='$pptp_user', pptp_password='$pptp_pass' WHERE id=$router_id";
                if ($conn->query($sql) === TRUE) {
                    // Rename FTP directory if name changed
                    if ($old_name !== $new_name) {
                        $ftp_conn = @ftp_connect($settings['ftp_server'], 21, 2);
                        if ($ftp_conn && @ftp_login($ftp_conn, $settings['ftp_user'], $settings['ftp_pass'])) {
                            @ftp_rename($ftp_conn, $old_name, $new_name);
                            @ftp_close($ftp_conn);
                        }
                    }
                    
                    header("Location: ?page=routers&success=2");
                    exit;
                } else {
                    $error_msg = "Error updating router: " . $conn->error;
                }
            }
        }
        
        // Delete Router
        elseif ($_POST['action'] === 'delete_router') {
            $router_id = (int)$_POST['router_id'];
            
            // Get router name for directory deletion
            $res = $conn->query("SELECT name FROM routers WHERE id = $router_id");
            if ($res->num_rows > 0) {
                $router_name = $res->fetch_assoc()['name'];
                
                // Delete from database
                if ($conn->query("DELETE FROM routers WHERE id = $router_id")) {
                    // Delete FTP directory and all backups
                    $ftp_conn = @ftp_connect($settings['ftp_server'], 21, 2);
                    if ($ftp_conn && @ftp_login($ftp_conn, $settings['ftp_user'], $settings['ftp_pass'])) {
                        // Delete all files in directory first
                        $files = @ftp_nlist($ftp_conn, $router_name);
                        if (is_array($files)) {
                            foreach ($files as $file) {
                                $basename = basename($file);
                                if ($basename !== '.' && $basename !== '..') {
                                    @ftp_delete($ftp_conn, $file);
                                }
                            }
                        }
                        // Delete directory
                        @ftp_rmdir($ftp_conn, $router_name);
                        @ftp_close($ftp_conn);
                    }
                    
                    header("Location: ?page=routers&success=3");
                    exit;
                } else {
                    $error_msg = "Error deleting router: " . $conn->error;
                }
            }
        }
        
        // Add User
        elseif ($_POST['action'] === 'add_user') {
            $username = $conn->real_escape_string($_POST['username']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $full_name = $conn->real_escape_string($_POST['full_name']);
            $email = $conn->real_escape_string($_POST['email']);
            $role = $conn->real_escape_string($_POST['role']);
            
            $sql = "INSERT INTO users (username, password, full_name, email, role) VALUES ('$username', '$password', '$full_name', '$email', '$role')";
            if ($conn->query($sql) === TRUE) {
                header("Location: ?page=users&success=1");
                exit;
            } else {
                $error_msg = "Error adding user: " . $conn->error;
            }
        }
        
        // Edit User
        elseif ($_POST['action'] === 'edit_user') {
            $user_id = (int)$_POST['user_id'];
            $username = $conn->real_escape_string($_POST['username']);
            $full_name = $conn->real_escape_string($_POST['full_name']);
            $email = $conn->real_escape_string($_POST['email']);
            $role = $conn->real_escape_string($_POST['role']);
            
            $sql = "UPDATE users SET username='$username', full_name='$full_name', email='$email', role='$role'";
            
            // Update password only if provided
            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $sql .= ", password='$password'";
            }
            
            $sql .= " WHERE id=$user_id";
            
            if ($conn->query($sql) === TRUE) {
                header("Location: ?page=users&success=2");
                exit;
            } else {
                $error_msg = "Error updating user: " . $conn->error;
            }
        }
        
        // Delete User
        elseif ($_POST['action'] === 'delete_user') {
            $user_id = (int)$_POST['user_id'];
            
            // Prevent deleting yourself
            if ($user_id == $_SESSION['user_id']) {
                $error_msg = "You cannot delete your own account!";
            } else {
                if ($conn->query("DELETE FROM users WHERE id = $user_id")) {
                    header("Location: ?page=users&success=3");
                    exit;
                } else {
                    $error_msg = "Error deleting user: " . $conn->error;
                }
            }
        }
    }
}

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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MikroTik Backup Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <?php
    // Get current theme
    $theme = isset($_SESSION['theme']) ? $_SESSION['theme'] : 'brown';
    
    // Define theme colors
    $themes = [
        'brown' => [
            'sidebar_from' => '#5d3a1a',
            'sidebar_to' => '#3d2612',
            'highlight' => '#ff6b35',
            'primary' => '#4169e1'
        ],
        'blue' => [
            'sidebar_from' => '#1e3a8a',
            'sidebar_to' => '#1e40af',
            'highlight' => '#3b82f6',
            'primary' => '#06b6d4'
        ],
        'dark' => [
            'sidebar_from' => '#1f2937',
            'sidebar_to' => '#111827',
            'highlight' => '#f59e0b',
            'primary' => '#8b5cf6'
        ],
        'green' => [
            'sidebar_from' => '#065f46',
            'sidebar_to' => '#064e3b',
            'highlight' => '#10b981',
            'primary' => '#14b8a6'
        ],
        'purple' => [
            'sidebar_from' => '#6b21a8',
            'sidebar_to' => '#581c87',
            'highlight' => '#a855f7',
            'primary' => '#ec4899'
        ],
        'red' => [
            'sidebar_from' => '#991b1b',
            'sidebar_to' => '#7f1d1d',
            'highlight' => '#ef4444',
            'primary' => '#f97316'
        ]
    ];
    
    $current_colors = $themes[$theme];
    ?>
    
    <style>
        /* Dynamic Theme Colors */
        .sidebar {
            background: linear-gradient(180deg, <?= $current_colors['sidebar_from'] ?> 0%, <?= $current_colors['sidebar_to'] ?> 100%) !important;
        }
        
        .title-highlight {
            color: <?= $current_colors['highlight'] ?> !important;
        }
        
        .btn-orange {
            background-color: <?= $current_colors['highlight'] ?> !important;
            border-color: <?= $current_colors['highlight'] ?> !important;
        }
        
        .btn-orange:hover {
            background-color: <?= $current_colors['highlight'] ?>dd !important;
            border-color: <?= $current_colors['highlight'] ?>dd !important;
        }
        
        .btn-primary {
            background-color: <?= $current_colors['primary'] ?> !important;
            border-color: <?= $current_colors['primary'] ?> !important;
        }
        
        .btn-primary:hover {
            background-color: <?= $current_colors['primary'] ?>dd !important;
            border-color: <?= $current_colors['primary'] ?>dd !important;
        }
        
        .nav-link.active {
            border-left-color: <?= $current_colors['highlight'] ?> !important;
        }
        
        .form-control:focus {
            border-color: <?= $current_colors['primary'] ?> !important;
            box-shadow: 0 0 0 3px <?= $current_colors['primary'] ?>1a !important;
        }
        
        /* Theme Selector Styles */
        .theme-option {
            cursor: pointer;
            position: relative;
        }
        
        .theme-option input[type="radio"] {
            display: none;
        }
        
        .theme-preview {
            border: 3px solid #e5e5e5;
            border-radius: 12px;
            padding: 1rem;
            transition: all 0.3s;
            background: #fff;
            position: relative;
        }
        
        .theme-option:hover .theme-preview {
            border-color: #ccc;
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        
        .theme-option.active .theme-preview {
            border-color: <?= $current_colors['highlight'] ?>;
            box-shadow: 0 8px 20px <?= $current_colors['highlight'] ?>40;
        }
        
        .theme-colors {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            height: 60px;
        }
        
        .theme-colors div {
            flex: 1;
            border-radius: 8px;
        }
        
        .theme-name {
            font-weight: 700;
            font-size: 1.05rem;
            color: #333;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .theme-badge {
            position: absolute;
            top: -10px;
            right: -10px;
            background: <?= $current_colors['highlight'] ?>;
            color: #fff;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        /* Compact Theme Cards */
        .theme-card {
            cursor: pointer;
            border: 2px solid #e5e5e5;
            border-radius: 8px;
            padding: 0.75rem;
            transition: all 0.2s;
            background: #fff;
            position: relative;
        }
        
        .theme-card:hover {
            border-color: #ccc;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .theme-card.active {
            border-color: <?= $current_colors['highlight'] ?>;
            box-shadow: 0 4px 12px <?= $current_colors['highlight'] ?>40;
        }
        
        .theme-colors-mini {
            display: flex;
            gap: 0.35rem;
            margin-bottom: 0.6rem;
            height: 35px;
        }
        
        .theme-colors-mini div {
            flex: 1;
            border-radius: 5px;
        }
        
        .theme-name-mini {
            font-weight: 700;
            font-size: 0.75rem;
            color: #333;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .theme-check {
            position: absolute;
            top: -8px;
            right: -8px;
            background: <?= $current_colors['highlight'] ?>;
            color: #fff;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>

    <div class="d-flex">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar" style="width: 260px; flex-shrink: 0;">
        <div class="sidebar-brand">
            <div class="brand-title">MIKROTIK BACKUP</div>
            <div class="brand-subtitle">MANAGER</div>
        </div>
        <ul class="nav flex-column px-2">
            <li class="nav-item">
                <a href="?page=dashboard" class="nav-link <?= $page == 'dashboard' ? 'active' : '' ?>">
                    <i class="fa-solid fa-table-cells-large fa-fw"></i> DASHBOARD
                </a>
            </li>
            <li class="nav-item">
                <a href="?page=routers" class="nav-link <?= $page == 'routers' ? 'active' : '' ?>">
                    <i class="fa-solid fa-server fa-fw"></i> ROUTERS
                </a>
            </li>
            <li class="nav-item">
                <a href="?page=script" class="nav-link <?= $page == 'script' ? 'active' : '' ?>">
                    <i class="fa-solid fa-code fa-fw"></i> GENERATE SCRIPT
                </a>
            </li>
            <li class="nav-item">
                <a href="?page=logs" class="nav-link <?= $page == 'logs' ? 'active' : '' ?>">
                    <i class="fa-solid fa-terminal fa-fw"></i> SERVER LOGS
                </a>
            </li>
            <li class="nav-item">
                <a href="?page=users" class="nav-link <?= $page == 'users' ? 'active' : '' ?>">
                    <i class="fa-solid fa-users fa-fw"></i> USER MANAGEMENT
                </a>
            </li>
            <li class="nav-item">
                <a href="?page=settings" class="nav-link <?= $page == 'settings' ? 'active' : '' ?>">
                    <i class="fa-solid fa-gear fa-fw"></i> SETTINGS
                </a>
            </li>
        </ul>
        
        <!-- User Info & Logout -->
        <div style="position: absolute; bottom: 0; left: 0; right: 0; padding: 1rem 1.25rem; border-top: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.2);">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                <div style="width: 40px; height: 40px; border-radius: 50%; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-user" style="color: #fff; font-size: 1.1rem;"></i>
                </div>
                <div style="flex: 1; min-width: 0;">
                    <div style="color: #fff; font-weight: 600; font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        <?= htmlspecialchars($_SESSION['full_name']) ?>
                    </div>
                    <div style="color: rgba(255,255,255,0.7); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">
                        <?= htmlspecialchars($_SESSION['role']) ?>
                    </div>
                </div>
            </div>
            <a href="logout.php" class="nav-link" style="background: rgba(255,107,53,0.2); border: 1px solid rgba(255,107,53,0.3); border-radius: 6px; padding: 0.6rem 1rem; text-align: center; color: #fff; font-weight: 600; transition: all 0.2s;">
                <i class="fa-solid fa-right-from-bracket fa-fw"></i> LOGOUT
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-grow-1 main-content" style="height: 100vh; overflow-y: auto;">
        <?php if(isset($_GET['success'])): ?>
            <?php if($_GET['success'] == '1' && $page == 'routers'): ?>
                <div class="alert alert-success d-flex" style="display: flex;">
                    <i class="fa-solid fa-check-circle" style="margin-right: 8px;"></i> Router added successfully!
                </div>
            <?php elseif($_GET['success'] == '2' && $page == 'routers'): ?>
                <div class="alert alert-success d-flex" style="display: flex;">
                    <i class="fa-solid fa-check-circle" style="margin-right: 8px;"></i> Router updated successfully!
                </div>
            <?php elseif($_GET['success'] == '3' && $page == 'routers'): ?>
                <div class="alert alert-success d-flex" style="display: flex;">
                    <i class="fa-solid fa-check-circle" style="margin-right: 8px;"></i> Router deleted successfully!
                </div>
            <?php elseif($_GET['success'] == '1' && $page == 'users'): ?>
                <div class="alert alert-success d-flex" style="display: flex;">
                    <i class="fa-solid fa-check-circle" style="margin-right: 8px;"></i> User added successfully!
                </div>
            <?php elseif($_GET['success'] == '2' && $page == 'users'): ?>
                <div class="alert alert-success d-flex" style="display: flex;">
                    <i class="fa-solid fa-check-circle" style="margin-right: 8px;"></i> User updated successfully!
                </div>
            <?php elseif($_GET['success'] == '3' && $page == 'users'): ?>
                <div class="alert alert-success d-flex" style="display: flex;">
                    <i class="fa-solid fa-check-circle" style="margin-right: 8px;"></i> User deleted successfully!
                </div>
            <?php elseif($_GET['success'] == 'theme'): ?>
                <div class="alert alert-success d-flex" style="display: flex;">
                    <i class="fa-solid fa-check-circle" style="margin-right: 8px;"></i> Theme changed successfully!
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <?php if($success_msg): ?>
            <div class="alert alert-success d-flex" style="display: flex;">
                <i class="fa-solid fa-check-circle" style="margin-right: 8px;"></i> <?= $success_msg ?>
            </div>
        <?php endif; ?>
        <?php if($error_msg): ?>
            <div class="alert alert-error d-flex" style="display: flex;">
                <i class="fa-solid fa-circle-exclamation" style="margin-right: 8px;"></i> <?= $error_msg ?>
            </div>
        <?php endif; ?>

        <?php if($page == 'dashboard'): ?>
        <div class="header">
            <h1 class="page-title"><span class="title-main">SYSTEM</span> <span class="title-highlight">OVERVIEW</span></h1>
        </div>
        
        <?php
        // Calculate statistics
        $total_routers = count($routers);
        $total_backups = 0;
        $total_size_bytes = 0;
        $last_backup_time = 0;
        
        // Get backup statistics from FTP - Use ACTIVE MODE (passive fails)
        $ftp_conn = @ftp_connect($settings['ftp_server'], 21, 5);
        if ($ftp_conn && @ftp_login($ftp_conn, $settings['ftp_user'], $settings['ftp_pass'])) {
            // Use ACTIVE mode (passive mode requires ports 40000-40100 which are blocked)
            @ftp_pasv($ftp_conn, false);
            
            foreach($routers as $r) {
                $router_dir = $r['name'];
                
                // Use ftp_nlist instead of ftp_rawlist (rawlist fails in active mode)
                $files = @ftp_nlist($ftp_conn, $router_dir);
                
                if (is_array($files)) {
                    foreach ($files as $file) {
                        $filename = basename($file);
                        
                        // Skip directories and special entries
                        if ($filename === '.' || $filename === '..') {
                            continue;
                        }
                        
                        // Only count backup files (.backup or .rsc)
                        if (preg_match('/\.(backup|rsc)$/i', $filename)) {
                            $total_backups++;
                            
                            // Get file size
                            $full_path = $router_dir . '/' . $filename;
                            $size = @ftp_size($ftp_conn, $full_path);
                            if ($size !== -1) {
                                $total_size_bytes += $size;
                            }
                            
                            // Get last modified time
                            $time = @ftp_mdtm($ftp_conn, $full_path);
                            if ($time !== -1 && $time > $last_backup_time) {
                                $last_backup_time = $time;
                            }
                        }
                    }
                }
            }
            @ftp_close($ftp_conn);
        }
        
        // Format sizes
        function formatBytes($bytes, $precision = 2) {
            $units = array('B', 'KB', 'MB', 'GB', 'TB');
            $bytes = max($bytes, 0);
            $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
            $pow = min($pow, count($units) - 1);
            $bytes /= pow(1024, $pow);
            return round($bytes, $precision) . ' ' . $units[$pow];
        }
        
        // Get server statistics from API endpoint
        $server_stats = null;
        $stats_url = "http://{$settings['ftp_server']}/server_stats.php";
        
        // Try to fetch server stats via HTTP
        $context = stream_context_create([
            'http' => [
                'timeout' => 3,
                'ignore_errors' => true
            ]
        ]);
        
        $stats_json = @file_get_contents($stats_url, false, $context);
        if ($stats_json) {
            $server_stats = json_decode($stats_json, true);
            
            // Use API backup stats if FTP scan failed
            if ($total_backups === 0 && isset($server_stats['backups'])) {
                $total_backups = $server_stats['backups']['total_backups'];
                $total_size_bytes = $server_stats['backups']['total_size_bytes'];
                $last_backup_time = $server_stats['backups']['last_backup_time'];
            }
        }
        
        // Extract stats with fallback values
        $total_space = isset($server_stats['disk']['total_bytes']) ? $server_stats['disk']['total_bytes'] : 0;
        $free_space = isset($server_stats['disk']['free_bytes']) ? $server_stats['disk']['free_bytes'] : 0;
        $used_space = isset($server_stats['disk']['used_bytes']) ? $server_stats['disk']['used_bytes'] : 0;
        
        $cpu_usage = isset($server_stats['cpu']['usage_percent']) ? $server_stats['cpu']['usage_percent'] : 0;
        $cpu_cores = isset($server_stats['cpu']['cores']) ? $server_stats['cpu']['cores'] : 0;
        
        $ram_total = isset($server_stats['ram']['total_bytes']) ? $server_stats['ram']['total_bytes'] : 0;
        $ram_used = isset($server_stats['ram']['used_bytes']) ? $server_stats['ram']['used_bytes'] : 0;
        $ram_free = isset($server_stats['ram']['free_bytes']) ? $server_stats['ram']['free_bytes'] : 0;
        
        $ftp_status = isset($server_stats['ftp_server']['status']) ? $server_stats['ftp_server']['status'] : 'unknown';
        
        // Count active connections
        $active_count = 0;
        $local_dir = "/home/ftpuser/active_connections";
        if (is_dir($local_dir)) {
            $active_files = glob("$local_dir/*.txt");
            $active_count = is_array($active_files) ? count($active_files) : 0;
        }
        ?>
        
        <div style="padding: 1.5rem 2rem;">
            <div class="dashboard-grid">
                <!-- Total Routers Card -->
                <div class="dashboard-card-modern">
                    <div class="card-icon-wrapper" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <i class="fa-solid fa-server card-icon-animated"></i>
                    </div>
                    <div class="card-content">
                        <h3 class="card-value"><?= $total_routers ?></h3>
                        <p class="card-label">Total Routers</p>
                    </div>
                    <div class="card-footer-info">
                        <span class="badge badge-success" style="font-size: 0.7rem;">
                            <i class="fa-solid fa-arrow-up"></i> Active
                        </span>
                    </div>
                </div>

                <!-- Active Connections Card -->
                <div class="dashboard-card-modern">
                    <div class="card-icon-wrapper" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <i class="fa-solid fa-link card-icon-animated"></i>
                    </div>
                    <div class="card-content">
                        <h3 class="card-value"><?= $active_count ?></h3>
                        <p class="card-label">Active Connections</p>
                    </div>
                    <div class="card-footer-info">
                        <span style="font-size: 0.75rem; color: #999;">
                            <i class="fa-solid fa-circle" style="color: #10b981; font-size: 0.5rem;"></i> Live
                        </span>
                    </div>
                </div>

                <!-- Total Backups Card -->
                <div class="dashboard-card-modern">
                    <div class="card-icon-wrapper" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <i class="fa-solid fa-database card-icon-animated"></i>
                    </div>
                    <div class="card-content">
                        <h3 class="card-value"><?= $total_backups ?></h3>
                        <p class="card-label">Total Backups</p>
                    </div>
                    <div class="card-footer-info">
                        <span style="font-size: 0.75rem; color: #999;">
                            <i class="fa-solid fa-file"></i> Files stored
                        </span>
                    </div>
                </div>

                <!-- Total Backup Size Card -->
                <div class="dashboard-card-modern">
                    <div class="card-icon-wrapper" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                        <i class="fa-solid fa-hard-drive card-icon-animated"></i>
                    </div>
                    <div class="card-content">
                        <h3 class="card-value"><?= formatBytes($total_size_bytes) ?></h3>
                        <p class="card-label">Backup Size</p>
                    </div>
                    <div class="card-footer-info">
                        <span style="font-size: 0.75rem; color: #999;">
                            <i class="fa-solid fa-chart-line"></i> Total used
                        </span>
                    </div>
                </div>

                <!-- Storage Used Card -->
                <?php if($total_space): ?>
                <div class="dashboard-card-modern">
                    <div class="card-icon-wrapper" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);">
                        <i class="fa-solid fa-chart-pie card-icon-animated"></i>
                    </div>
                    <div class="card-content">
                        <h3 class="card-value"><?= formatBytes($used_space) ?></h3>
                        <p class="card-label">Storage Used</p>
                    </div>
                    <div class="card-footer-info">
                        <div class="progress-bar-mini">
                            <div class="progress-fill" style="width: <?= round(($used_space / $total_space) * 100) ?>%;"></div>
                        </div>
                        <span style="font-size: 0.7rem; color: #999; margin-top: 0.25rem;">
                            <?= round(($used_space / $total_space) * 100, 1) ?>% of <?= formatBytes($total_space) ?>
                        </span>
                    </div>
                </div>

                <!-- Storage Free Card -->
                <div class="dashboard-card-modern">
                    <div class="card-icon-wrapper" style="background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);">
                        <i class="fa-solid fa-cloud card-icon-animated"></i>
                    </div>
                    <div class="card-content">
                        <h3 class="card-value"><?= formatBytes($free_space) ?></h3>
                        <p class="card-label">Storage Free</p>
                    </div>
                    <div class="card-footer-info">
                        <span class="badge" style="background: #dcfce7; color: #166534; font-size: 0.7rem;">
                            <i class="fa-solid fa-check"></i> Available
                        </span>
                    </div>
                </div>
                <?php endif; ?>

                <!-- FTP Server Status Card -->
                <div class="dashboard-card-modern">
                    <div class="card-icon-wrapper" style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);">
                        <i class="fa-solid fa-server card-icon-animated"></i>
                    </div>
                    <div class="card-content">
                        <h3 class="card-value" style="font-size: 1.5rem; text-transform: capitalize;">
                            <?= $ftp_status === 'online' ? 'Online' : ($ftp_status === 'offline' ? 'Offline' : 'Unknown') ?>
                        </h3>
                        <p class="card-label">FTP Server</p>
                    </div>
                    <div class="card-footer-info">
                        <span style="font-size: 0.75rem; color: #999;">
                            <i class="fa-solid fa-circle" style="color: <?= $ftp_status === 'online' ? '#10b981' : '#ef4444' ?>; font-size: 0.5rem; <?= $ftp_status === 'online' ? 'animation: pulse 2s infinite;' : '' ?>"></i> 
                            <?= $ftp_status === 'online' ? 'Running' : 'Stopped' ?>
                        </span>
                    </div>
                </div>

                <!-- Last Backup Card -->
                <div class="dashboard-card-modern">
                    <div class="card-icon-wrapper" style="background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);">
                        <i class="fa-solid fa-clock card-icon-animated"></i>
                    </div>
                    <div class="card-content">
                        <h3 class="card-value" style="font-size: 1.3rem;">
                            <?= $last_backup_time > 0 ? date('M d, H:i', $last_backup_time) : 'N/A' ?>
                        </h3>
                        <p class="card-label">Last Backup</p>
                    </div>
                    <div class="card-footer-info">
                        <span style="font-size: 0.75rem; color: #999;">
                            <i class="fa-solid fa-history"></i> Recent activity
                        </span>
                    </div>
                </div>

                <!-- Server CPU Card -->
                <div class="dashboard-card-modern">
                    <div class="card-icon-wrapper" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <i class="fa-solid fa-microchip card-icon-animated"></i>
                    </div>
                    <div class="card-content">
                        <h3 class="card-value"><?= $cpu_usage > 0 ? $cpu_usage . '%' : 'N/A' ?></h3>
                        <p class="card-label">Server CPU Usage</p>
                    </div>
                    <div class="card-footer-info">
                        <?php if($cpu_cores > 0): ?>
                        <div class="progress-bar-mini">
                            <div class="progress-fill" style="width: <?= $cpu_usage ?>%; background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);"></div>
                        </div>
                        <span style="font-size: 0.7rem; color: #999;">
                            <?= $cpu_cores ?> Cores Available
                        </span>
                        <?php else: ?>
                        <span style="font-size: 0.75rem; color: #999;">
                            <i class="fa-solid fa-server"></i> Processing
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Server RAM Card -->
                <div class="dashboard-card-modern">
                    <div class="card-icon-wrapper" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <i class="fa-solid fa-memory card-icon-animated"></i>
                    </div>
                    <div class="card-content">
                        <h3 class="card-value"><?= $ram_total > 0 ? formatBytes($ram_used) : 'N/A' ?></h3>
                        <p class="card-label">Server RAM Used</p>
                    </div>
                    <div class="card-footer-info">
                        <?php if($ram_total > 0): ?>
                        <div class="progress-bar-mini">
                            <div class="progress-fill" style="width: <?= round(($ram_used / $ram_total) * 100) ?>%; background: linear-gradient(90deg, #f093fb 0%, #f5576c 100%);"></div>
                        </div>
                        <span style="font-size: 0.7rem; color: #999;">
                            <?= round(($ram_used / $ram_total) * 100, 1) ?>% of <?= formatBytes($ram_total) ?>
                        </span>
                        <?php else: ?>
                        <span style="font-size: 0.75rem; color: #999;">
                            <i class="fa-solid fa-memory"></i> Memory
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Server RAM Free Card -->
                <div class="dashboard-card-modern">
                    <div class="card-icon-wrapper" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <i class="fa-solid fa-memory card-icon-animated"></i>
                    </div>
                    <div class="card-content">
                        <h3 class="card-value"><?= $ram_total > 0 ? formatBytes($ram_free) : 'N/A' ?></h3>
                        <p class="card-label">Server RAM Free</p>
                    </div>
                    <div class="card-footer-info">
                        <span class="badge" style="background: #dcfce7; color: #166534; font-size: 0.7rem;">
                            <i class="fa-solid fa-check"></i> Available
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($page == 'routers'): ?>
        <div class="header">
            <h1 class="page-title"><span class="title-main">MANAGED</span> <span class="title-highlight">ROUTERS</span></h1>
            <button onclick="openAddRouterModal()" class="btn btn-orange"><i class="fa-solid fa-plus"></i> Add Router</button>
        </div>
        
        <div class="card">
            <div class="card-body">
                <div style="padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f0f0f0;">
                    <input type="text" placeholder="Search..." style="padding: 0.5rem 1rem; border: 1px solid #e5e5e5; border-radius: 5px; width: 250px; font-size: 0.9rem;">
                    <div style="display: flex; gap: 1rem; align-items: center;">
                        <span style="color: #666; font-size: 0.9rem;">Show</span>
                        <select style="padding: 0.4rem 0.8rem; border: 1px solid #e5e5e5; border-radius: 5px; font-size: 0.9rem;">
                            <option>10</option>
                            <option>25</option>
                            <option>50</option>
                            <option>100</option>
                        </select>
                        <span style="color: #666; font-size: 0.9rem;">entries</span>
                        <select style="padding: 0.4rem 0.8rem; border: 1px solid #e5e5e5; border-radius: 5px; font-size: 0.9rem;">
                            <option>Columns</option>
                        </select>
                    </div>
                </div>
            <div class="table-container">
                <?php
                // Fetch active connections from the PPTP server in realtime via FTP tracking files
                $active_vpn_users = [];
                $local_dir = "/home/ftpuser/active_connections";
                
                // 1. Try local file system first (fastest, no timeout risk)
                if (is_dir($local_dir)) {
                    $active_files = glob("$local_dir/*.txt");
                    if (is_array($active_files)) {
                        foreach ($active_files as $file) {
                            $content = @file_get_contents($file);
                            $parts = preg_split('/\s+/', trim($content));
                            if (count($parts) >= 4) {
                                $user = trim($parts[0]);
                                $vpn_ip = trim($parts[2]);
                                $active_vpn_users[$user] = $vpn_ip;
                            }
                        }
                    }
                } 
                // 2. Fallback to FTP with short timeout if local dir not accessible
                else {
                    $ftp_conn = @ftp_connect($settings['ftp_server'], 21, 2);
                    if ($ftp_conn && @ftp_login($ftp_conn, $settings['ftp_user'], $settings['ftp_pass'])) {
                        @ftp_pasv($ftp_conn, true);
                        $active_files = @ftp_nlist($ftp_conn, "/active_connections");
                        if (is_array($active_files)) {
                            foreach ($active_files as $file) {
                                if (strpos($file, '.txt') !== false) {
                                    $stream = fopen('php://temp', 'r+');
                                    if (@ftp_fget($ftp_conn, $stream, $file, FTP_ASCII, 0)) {
                                        rewind($stream);
                                        $content = stream_get_contents($stream);
                                        $parts = preg_split('/\s+/', trim($content));
                                        if (count($parts) >= 4) {
                                            $user = trim($parts[0]);
                                            $vpn_ip = trim($parts[2]);
                                            $active_vpn_users[$user] = $vpn_ip;
                                        }
                                    }
                                    fclose($stream);
                                }
                            }
                        }
                        @ftp_close($ftp_conn);
                    }
                }

                // Fallback to local 'last' command if running directly on Ubuntu and FTP fails
                if (empty($active_vpn_users) && function_exists('shell_exec')) {
                    $last_output = @shell_exec("last 2>/dev/null");
                    if ($last_output) {
                        $lines = explode("\n", trim($last_output));
                        foreach ($lines as $line) {
                            if (strpos($line, 'still logged in') !== false && strpos($line, 'ppp') !== false) {
                                $parts = preg_split('/\s+/', $line);
                                if (count($parts) >= 3) {
                                    $user = trim($parts[0]);
                                    $ip = trim($parts[2]);
                                    $active_vpn_users[$user] = $ip;
                                }
                            }
                        }
                    }
                }
                ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Router Name</th>
                            <th>PPTP User</th>
                            <th>Added On</th>
                            <th>Status</th>
                            <th>Remote IP</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($routers) > 0): ?>
                            <?php foreach($routers as $r): ?>
                            <?php 
                                $db_user = trim($r['pptp_username']);
                                $is_connected = isset($active_vpn_users[$db_user]);
                                $remote_ip = $is_connected ? $active_vpn_users[$db_user] : '-';
                            ?>
                            <tr>
                                <td><?= $r['id'] ?></td>
                                <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
                                <td><?= htmlspecialchars($r['pptp_username']) ?></td>
                                <td><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                                <td>
                                    <?php if($is_connected): ?>
                                        <span class="badge badge-success"><i class="fa-solid fa-link"></i> Connected</span>
                                    <?php else: ?>
                                        <span class="badge badge-error"><i class="fa-solid fa-link-slash"></i> Offline</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($is_connected): ?>
                                        <span style="font-family: monospace; color: #4169e1;"><?= htmlspecialchars($remote_ip) ?></span>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <a href="?page=routers&view_backups=<?= $r['id'] ?>" class="btn btn-primary" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">
                                            <i class="fa-solid fa-folder-open"></i> Backups
                                        </a>
                                        <button onclick="openEditRouterModal(<?= $r['id'] ?>, '<?= htmlspecialchars($r['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($r['pptp_username'], ENT_QUOTES) ?>', '<?= htmlspecialchars($r['pptp_password'], ENT_QUOTES) ?>')" class="btn btn-secondary" style="padding: 0.4rem 0.6rem; font-size: 0.85rem;">
                                            <i class="fa-solid fa-edit"></i>
                                        </button>
                                        <button onclick="confirmDelete(<?= $r['id'] ?>, '<?= htmlspecialchars($r['name'], ENT_QUOTES) ?>')" class="btn btn-danger" style="padding: 0.4rem 0.6rem; font-size: 0.85rem;">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 2rem; color: #999;">No routers found. Add one to get started.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div style="padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f0f0f0;">
                <div style="color: #666; font-size: 0.9rem;">
                    Showing 1 to <?= count($routers) ?> of <?= count($routers) ?> entries
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button class="btn" style="padding: 0.4rem 0.8rem; background: #f5f5f5; color: #666; border: 1px solid #e5e5e5;">&lt; Previous</button>
                    <button class="btn" style="padding: 0.4rem 0.8rem; background: #ff6b35; color: #fff; border: 1px solid #ff6b35;">1</button>
                    <button class="btn" style="padding: 0.4rem 0.8rem; background: #f5f5f5; color: #666; border: 1px solid #e5e5e5;">Next &gt;</button>
                </div>
            </div>
            </div>
        </div>

        <!-- Add Router Modal -->
        <div id="addRouterModal" class="modal-overlay" style="display: none;">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fa-solid fa-plus-circle text-primary me-2"></i> Add New Router</h5>
                        <button onclick="closeAddRouterModal()" class="close-btn">&times;</button>
                    </div>
                    <form method="POST" action="?page=routers">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="add_router">
                            
                            <div class="form-group">
                                <label for="router_name">Router Name <span style="color: #dc3545;">*</span></label>
                                <input type="text" id="router_name" name="name" class="form-control" placeholder="e.g., Office-Router-01" required>
                                <small class="form-text">A unique identifier for this router</small>
                            </div>

                            <div class="form-group">
                                <label for="pptp_username">PPTP Username <span style="color: #dc3545;">*</span></label>
                                <input type="text" id="pptp_username" name="pptp_username" class="form-control" placeholder="e.g., router01" required>
                                <small class="form-text">VPN username for authentication</small>
                            </div>

                            <div class="form-group">
                                <label for="pptp_password">PPTP Password <span style="color: #dc3545;">*</span></label>
                                <input type="password" id="pptp_password" name="pptp_password" class="form-control" placeholder="Enter secure password" required>
                                <small class="form-text">VPN password for authentication</small>
                            </div>

                            <div class="alert" style="background-color: #fff3cd; color: #856404; border: 1px solid #ffeaa7; padding: 0.75rem; border-radius: 5px; font-size: 0.85rem;">
                                <i class="fa-solid fa-info-circle"></i> After adding, generate and run the MikroTik script on your router.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" onclick="closeAddRouterModal()" class="btn btn-secondary">
                                <i class="fa-solid fa-times"></i> Cancel
                            </button>
                            <button type="submit" class="btn btn-orange">
                                <i class="fa-solid fa-save"></i> Save Router
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Router Modal -->
        <div id="editRouterModal" class="modal-overlay" style="display: none;">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fa-solid fa-edit text-primary me-2"></i> Edit Router</h5>
                        <button onclick="closeEditRouterModal()" class="close-btn">&times;</button>
                    </div>
                    <form method="POST" action="?page=routers">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="edit_router">
                            <input type="hidden" name="router_id" id="edit_router_id">
                            
                            <div class="form-group">
                                <label for="edit_router_name">Router Name <span style="color: #dc3545;">*</span></label>
                                <input type="text" id="edit_router_name" name="name" class="form-control" placeholder="e.g., Office-Router-01" required>
                                <small class="form-text">A unique identifier for this router</small>
                            </div>

                            <div class="form-group">
                                <label for="edit_pptp_username">PPTP Username <span style="color: #dc3545;">*</span></label>
                                <input type="text" id="edit_pptp_username" name="pptp_username" class="form-control" placeholder="e.g., router01" required>
                                <small class="form-text">VPN username for authentication</small>
                            </div>

                            <div class="form-group">
                                <label for="edit_pptp_password">PPTP Password <span style="color: #dc3545;">*</span></label>
                                <input type="password" id="edit_pptp_password" name="pptp_password" class="form-control" placeholder="Enter secure password" required>
                                <small class="form-text">VPN password for authentication</small>
                            </div>

                            <div class="alert" style="background-color: #fff3cd; color: #856404; border: 1px solid #ffeaa7; padding: 0.75rem; border-radius: 5px; font-size: 0.85rem;">
                                <i class="fa-solid fa-info-circle"></i> If you change the router name, the backup directory will be automatically renamed.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" onclick="closeEditRouterModal()" class="btn btn-secondary">
                                <i class="fa-solid fa-times"></i> Cancel
                            </button>
                            <button type="submit" class="btn btn-orange">
                                <i class="fa-solid fa-save"></i> Update Router
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Form (Hidden) -->
        <form id="deleteRouterForm" method="POST" action="?page=routers" style="display: none;">
            <input type="hidden" name="action" value="delete_router">
            <input type="hidden" name="router_id" id="delete_router_id">
        </form>

        <script>
            function openAddRouterModal() {
                document.getElementById('addRouterModal').style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }

            function closeAddRouterModal() {
                document.getElementById('addRouterModal').style.display = 'none';
                document.body.style.overflow = 'auto';
            }

            function openEditRouterModal(id, name, username, password) {
                document.getElementById('edit_router_id').value = id;
                document.getElementById('edit_router_name').value = name;
                document.getElementById('edit_pptp_username').value = username;
                document.getElementById('edit_pptp_password').value = password;
                document.getElementById('editRouterModal').style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }

            function closeEditRouterModal() {
                document.getElementById('editRouterModal').style.display = 'none';
                document.body.style.overflow = 'auto';
            }

            function confirmDelete(id, name) {
                if (confirm('Are you sure you want to delete router "' + name + '"?\n\nThis will also delete all backup files associated with this router. This action cannot be undone!')) {
                    document.getElementById('delete_router_id').value = id;
                    document.getElementById('deleteRouterForm').submit();
                }
            }

            // Close modal when clicking outside
            document.getElementById('addRouterModal')?.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeAddRouterModal();
                }
            });

            document.getElementById('editRouterModal')?.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeEditRouterModal();
                }
            });

            // Close modal with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeAddRouterModal();
                    closeEditRouterModal();
                }
            });
        </script>

        <?php if(isset($_GET['view_backups'])): 
            $view_id = (int)$_GET['view_backups'];
            $b_res = $conn->query("SELECT name FROM routers WHERE id = $view_id");
            if ($b_res->num_rows > 0) {
                $b_router = $b_res->fetch_assoc()['name'];
                $b_dir = "/home/ftpuser/" . $b_router;
                $backup_files = [];
                
                $ftp_conn = @ftp_connect($settings['ftp_server'], 21, 2);
                if ($ftp_conn && @ftp_login($ftp_conn, $settings['ftp_user'], $settings['ftp_pass'])) {
                    $files = @ftp_nlist($ftp_conn, $b_router);
                    if (is_array($files)) {
                        foreach ($files as $f) {
                            $basename = basename($f);
                            if ($basename !== '.' && $basename !== '..') {
                                $size = @ftp_size($ftp_conn, $f);
                                $time = @ftp_mdtm($ftp_conn, $f);
                                $backup_files[] = [
                                    'name' => $basename,
                                    'size' => $size !== -1 ? $size : 0,
                                    'time' => $time !== -1 ? $time : 0
                                ];
                            }
                        }
                        // Sort newest first
                        usort($backup_files, function($a, $b) {
                            return $b['time'] - $a['time'];
                        });
                    }
                    @ftp_close($ftp_conn);
                }
            }
        ?>
        <div class="modal-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1050; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
            <div class="card shadow" style="width: 95%; max-width: 1000px; max-height: 90vh; display: flex; flex-direction: column;">
                <div class="card-header" style="padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e5e5e5;">
                    <h5 class="mb-0" style="font-size: 1.3rem; font-weight: 700;">
                        <i class="fa-solid fa-folder-open" style="color: #4169e1; margin-right: 0.5rem;"></i> 
                        BACKUPS FOR <?= strtoupper(htmlspecialchars($b_router)) ?>
                    </h5>
                    <a href="?page=routers" class="text-secondary text-decoration-none" style="font-size: 1.5rem; line-height: 1;">
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                </div>
                <div class="card-body" style="padding: 0; overflow: hidden; display: flex; flex-direction: column; flex: 1;">
                
                <?php if(empty($backup_files)): ?>
                    <div style="text-align: center; padding: 3rem 1rem; color: #999;">
                        <i class="fa-solid fa-box-open" style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                        <p style="font-size: 1.1rem; font-weight: 600;">No backups found yet</p>
                        <p style="font-size: 0.9rem; color: #666;">Make sure your router's script is running successfully.</p>
                        <p style="font-size: 0.85rem; margin-top: 1rem; color: #999;">Looking in: <?= htmlspecialchars($b_dir) ?></p>
                    </div>
                <?php else: ?>
                    <!-- Filters Section -->
                    <div style="padding: 1.25rem 1.5rem; background: #f8f9fa; border-bottom: 1px solid #e5e5e5;">
                        <div style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
                            <div style="flex: 1; min-width: 200px;">
                                <input type="text" id="searchInput" placeholder="Search files..." style="width: 100%; padding: 0.6rem 1rem; border: 1px solid #d1d5db; border-radius: 5px; font-size: 0.95rem;" onkeyup="filterBackups()">
                            </div>
                            <div style="min-width: 150px;">
                                <input type="date" id="dateFrom" style="width: 100%; padding: 0.6rem 0.8rem; border: 1px solid #d1d5db; border-radius: 5px; font-size: 0.9rem;" onchange="filterBackups()" placeholder="From Date">
                            </div>
                            <div style="min-width: 150px;">
                                <input type="date" id="dateTo" style="width: 100%; padding: 0.6rem 0.8rem; border: 1px solid #d1d5db; border-radius: 5px; font-size: 0.9rem;" onchange="filterBackups()" placeholder="To Date">
                            </div>
                            <div style="min-width: 120px;">
                                <select id="rowsPerPage" style="width: 100%; padding: 0.6rem 0.8rem; border: 1px solid #d1d5db; border-radius: 5px; font-size: 0.9rem; font-weight: 600;" onchange="changeRowsPerPage()">
                                    <option value="10">10 rows</option>
                                    <option value="25">25 rows</option>
                                    <option value="50">50 rows</option>
                                    <option value="100">100 rows</option>
                                </select>
                            </div>
                            <button onclick="resetFilters()" class="btn btn-secondary" style="padding: 0.6rem 1rem; font-size: 0.9rem;">
                                <i class="fa-solid fa-rotate-right"></i> RESET
                            </button>
                        </div>
                    </div>

                    <!-- Table Section -->
                    <div style="overflow-y: auto; flex: 1;">
                        <table class="table" id="backupsTable" style="margin-bottom: 0;">
                            <thead style="position: sticky; top: 0; background: #fafafa; z-index: 10;">
                                <tr>
                                    <th style="width: 45%;">FILE NAME</th>
                                    <th style="width: 15%;">SIZE</th>
                                    <th style="width: 25%;">DATE</th>
                                    <th style="width: 15%;">ACTION</th>
                                </tr>
                            </thead>
                            <tbody id="backupsTableBody">
                                <?php foreach($backup_files as $f): ?>
                                <tr class="backup-row" data-filename="<?= strtolower(htmlspecialchars($f['name'])) ?>" data-timestamp="<?= $f['time'] ?>">
                                    <td>
                                        <i class="<?= strpos($f['name'], '.rsc') !== false ? 'fa-solid fa-file-code' : 'fa-solid fa-file-zipper' ?>" style="margin-right: 0.5rem; color: <?= strpos($f['name'], '.rsc') !== false ? '#4169e1' : '#10b981' ?>;"></i> 
                                        <strong><?= htmlspecialchars($f['name']) ?></strong>
                                    </td>
                                    <td style="color: #666; font-weight: 600;"><?= round($f['size'] / 1024, 2) ?> KB</td>
                                    <td style="color: #666;"><?= date('M d, Y g:i A', $f['time']) ?></td>
                                    <td>
                                        <a href="?download=<?= urlencode($f['name']) ?>&router_id=<?= $view_id ?>" class="btn btn-primary" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">
                                            <i class="fa-solid fa-download"></i> DOWNLOAD
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Section -->
                    <div style="padding: 1rem 1.5rem; background: #f8f9fa; border-top: 1px solid #e5e5e5; display: flex; justify-content: space-between; align-items: center;">
                        <div style="color: #666; font-size: 0.95rem; font-weight: 600;">
                            Showing <span id="showingStart">1</span> to <span id="showingEnd">10</span> of <span id="totalRows"><?= count($backup_files) ?></span> entries
                        </div>
                        <div id="paginationControls" style="display: flex; gap: 0.5rem;">
                            <!-- Pagination buttons will be generated by JavaScript -->
                        </div>
                    </div>

                    <script>
                        let currentPage = 1;
                        let rowsPerPage = 10;
                        let allRows = [];
                        let filteredRows = [];

                        function initBackupTable() {
                            allRows = Array.from(document.querySelectorAll('.backup-row'));
                            filteredRows = [...allRows];
                            displayPage(1);
                        }

                        function filterBackups() {
                            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
                            const dateFrom = document.getElementById('dateFrom').value;
                            const dateTo = document.getElementById('dateTo').value;

                            filteredRows = allRows.filter(row => {
                                const filename = row.getAttribute('data-filename');
                                const timestamp = parseInt(row.getAttribute('data-timestamp'));
                                
                                // Search filter
                                if (searchTerm && !filename.includes(searchTerm)) {
                                    return false;
                                }

                                // Date filter
                                if (dateFrom) {
                                    const fromDate = new Date(dateFrom).getTime() / 1000;
                                    if (timestamp < fromDate) return false;
                                }
                                if (dateTo) {
                                    const toDate = new Date(dateTo).getTime() / 1000 + 86400; // Add 1 day
                                    if (timestamp > toDate) return false;
                                }

                                return true;
                            });

                            currentPage = 1;
                            displayPage(1);
                        }

                        function changeRowsPerPage() {
                            rowsPerPage = parseInt(document.getElementById('rowsPerPage').value);
                            currentPage = 1;
                            displayPage(1);
                        }

                        function displayPage(page) {
                            currentPage = page;
                            const start = (page - 1) * rowsPerPage;
                            const end = start + rowsPerPage;

                            // Hide all rows
                            allRows.forEach(row => row.style.display = 'none');

                            // Show filtered rows for current page
                            filteredRows.slice(start, end).forEach(row => row.style.display = '');

                            // Update pagination info
                            document.getElementById('showingStart').textContent = filteredRows.length > 0 ? start + 1 : 0;
                            document.getElementById('showingEnd').textContent = Math.min(end, filteredRows.length);
                            document.getElementById('totalRows').textContent = filteredRows.length;

                            // Generate pagination buttons
                            generatePagination();
                        }

                        function generatePagination() {
                            const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
                            const paginationControls = document.getElementById('paginationControls');
                            paginationControls.innerHTML = '';

                            if (totalPages <= 1) return;

                            // Previous button
                            const prevBtn = document.createElement('button');
                            prevBtn.className = 'btn';
                            prevBtn.style.cssText = 'padding: 0.4rem 0.8rem; background: #f5f5f5; color: #666; border: 1px solid #e5e5e5; font-size: 0.9rem; font-weight: 600;';
                            prevBtn.innerHTML = '<i class="fa-solid fa-chevron-left"></i>';
                            prevBtn.disabled = currentPage === 1;
                            prevBtn.onclick = () => displayPage(currentPage - 1);
                            if (currentPage === 1) prevBtn.style.opacity = '0.5';
                            paginationControls.appendChild(prevBtn);

                            // Page numbers
                            const maxButtons = 5;
                            let startPage = Math.max(1, currentPage - Math.floor(maxButtons / 2));
                            let endPage = Math.min(totalPages, startPage + maxButtons - 1);
                            
                            if (endPage - startPage < maxButtons - 1) {
                                startPage = Math.max(1, endPage - maxButtons + 1);
                            }

                            for (let i = startPage; i <= endPage; i++) {
                                const pageBtn = document.createElement('button');
                                pageBtn.className = 'btn';
                                pageBtn.textContent = i;
                                pageBtn.style.cssText = 'padding: 0.4rem 0.8rem; font-size: 0.9rem; font-weight: 600; border: 1px solid #e5e5e5;';
                                
                                if (i === currentPage) {
                                    pageBtn.style.background = '#ff6b35';
                                    pageBtn.style.color = '#fff';
                                    pageBtn.style.borderColor = '#ff6b35';
                                } else {
                                    pageBtn.style.background = '#f5f5f5';
                                    pageBtn.style.color = '#666';
                                }
                                
                                pageBtn.onclick = () => displayPage(i);
                                paginationControls.appendChild(pageBtn);
                            }

                            // Next button
                            const nextBtn = document.createElement('button');
                            nextBtn.className = 'btn';
                            nextBtn.style.cssText = 'padding: 0.4rem 0.8rem; background: #f5f5f5; color: #666; border: 1px solid #e5e5e5; font-size: 0.9rem; font-weight: 600;';
                            nextBtn.innerHTML = '<i class="fa-solid fa-chevron-right"></i>';
                            nextBtn.disabled = currentPage === totalPages;
                            nextBtn.onclick = () => displayPage(currentPage + 1);
                            if (currentPage === totalPages) nextBtn.style.opacity = '0.5';
                            paginationControls.appendChild(nextBtn);
                        }

                        function resetFilters() {
                            document.getElementById('searchInput').value = '';
                            document.getElementById('dateFrom').value = '';
                            document.getElementById('dateTo').value = '';
                            document.getElementById('rowsPerPage').value = '10';
                            rowsPerPage = 10;
                            filterBackups();
                        }

                        // Initialize on load
                        initBackupTable();
                    </script>
                <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <?php if($page == 'logs'): ?>
        <div class="header">
            <h1 class="page-title"><span class="title-main">LIVE SERVER</span> <span class="title-highlight">LOGS</span></h1>
            <div style="display: flex; gap: 1rem;">
                <button id="btn-pptp" class="btn btn-primary" onclick="switchLog('pptp')"><i class="fa-solid fa-network-wired"></i> PPTP VPN LOGS</button>
                <button id="btn-ftp" class="btn btn-secondary" onclick="switchLog('ftp')"><i class="fa-solid fa-file-arrow-up"></i> FTP UPLOAD LOGS</button>
            </div>
        </div>
        
        <div class="card">
            <p id="log-desc" style="color: #94a3b8; margin-bottom: 1.5rem; font-size: 0.9rem;">
                This displays the real-time syslog output from your Ubuntu server specifically filtered for VPN authentication and connection events.
            </p>
            
            <div class="script-box" id="log-container" style="font-size: 0.85rem; color: #a5b4fc; max-height: 500px; overflow-y: auto; background: #000; white-space: pre-wrap;">
                Loading logs...
            </div>
            <div style="margin-top: 1rem; text-align: right; display: flex; justify-content: space-between; align-items: center;">
                <span style="color: #64748b; font-size: 0.8rem;"><i class="fa-solid fa-circle-notch fa-spin"></i> Auto-refreshing every 3 seconds</span>
                <button class="btn btn-secondary" onclick="fetchLogs()"><i class="fa-solid fa-rotate-right"></i> Refresh Now</button>
            </div>
        </div>

        <script>
            let currentLogType = 'pptp';
            let autoScroll = true;

            function switchLog(type) {
                currentLogType = type;
                
                document.getElementById('btn-pptp').className = type === 'pptp' ? 'btn btn-primary' : 'btn btn-secondary';
                document.getElementById('btn-ftp').className = type === 'ftp' ? 'btn btn-primary' : 'btn btn-secondary';
                
                if (type === 'pptp') {
                    document.getElementById('log-desc').innerText = "This displays the real-time syslog output from your Ubuntu server specifically filtered for VPN authentication and connection events.";
                } else {
                    document.getElementById('log-desc').innerText = "This displays the real-time FTP server logs showing router backup uploads and connections.";
                }
                
                fetchLogs();
            }

            function fetchLogs() {
                fetch('?ajax_logs=' + currentLogType)
                    .then(response => response.text())
                    .then(data => {
                        const container = document.getElementById('log-container');
                        
                        // Check if user has scrolled up
                        autoScroll = container.scrollHeight - container.scrollTop === container.clientHeight;
                        
                        container.innerHTML = data;
                        
                        if (autoScroll) {
                            container.scrollTop = container.scrollHeight;
                        }
                    });
            }

            // Initial fetch and set interval for real-time
            fetchLogs();
            setInterval(fetchLogs, 3000);
        </script>
        <?php endif; ?>

        <?php if($page == 'connections'): ?>
        <div class="header">
            <h1 class="page-title">Active PPTP Connections</h1>
        </div>
        
        <div class="card">
            <div style="margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between; background: rgba(15, 23, 42, 0.5); padding: 1rem; border-radius: 0.5rem; border: 1px solid var(--border-color);">
                <div>
                    <h3 style="margin-bottom: 0.2rem; font-size: 1rem;">PPTP Server Status</h3>
                    <p style="color: #94a3b8; font-size: 0.85rem; margin: 0;">Checks if the VPN daemon is actively running on Ubuntu.</p>
                </div>
                <?php
                $status = @trim(shell_exec("systemctl is-active pptpd 2>/dev/null"));
                if ($status === 'active') {
                    echo '<span class="badge badge-success" style="padding: 0.5rem 1rem;"><i class="fa-solid fa-circle-check"></i> ACTIVE & RUNNING</span>';
                } elseif (empty($status) && stripos(PHP_OS, 'WIN') !== false) {
                    echo '<span class="badge" style="padding: 0.5rem 1rem;"><i class="fa-solid fa-circle-question"></i> UNKNOWN (Local Windows)</span>';
                } else {
                    echo '<span class="badge badge-error" style="padding: 0.5rem 1rem;"><i class="fa-solid fa-circle-xmark"></i> OFFLINE / ERROR</span>';
                }
                ?>
            </div>
            
            <p style="color: #94a3b8; margin-bottom: 1.5rem; font-size: 0.9rem;">
                This shows the currently connected MikroTik routers. It reads the live connection logs from your Ubuntu server.
            </p>
            
            <div class="script-box" style="font-size: 0.9rem; line-height: 1.6;">
<?php
// Get realtime connections from FTP tracking
$active_connections_list = [];
$local_dir = "/home/ftpuser/active_connections";

if (is_dir($local_dir)) {
    $active_files = glob("$local_dir/*.txt");
    if (is_array($active_files)) {
        foreach ($active_files as $file) {
            $content = @file_get_contents($file);
            $parts = preg_split('/\s+/', trim($content));
            if (count($parts) >= 5) {
                $user = trim($parts[0]);
                $interface = trim($parts[1]);
                $vpn_ip = trim($parts[2]);
                $time_idx = count($parts) - 2;
                $time = trim($parts[$time_idx]) . ' ' . trim($parts[$time_idx + 1]);
                $public_ip = ($time_idx > 3) ? trim($parts[3]) : 'Unknown';
                $active_connections_list[] = [
                    'user' => $user,
                    'interface' => $interface,
                    'vpn_ip' => $vpn_ip,
                    'public_ip' => $public_ip,
                    'time' => $time
                ];
            }
        }
    }
} else {
    $ftp_conn = @ftp_connect($settings['ftp_server'], 21, 2);
    if ($ftp_conn && @ftp_login($ftp_conn, $settings['ftp_user'], $settings['ftp_pass'])) {
        @ftp_pasv($ftp_conn, true);
        $active_files = @ftp_nlist($ftp_conn, "/active_connections");
        if (is_array($active_files)) {
            foreach ($active_files as $file) {
                if (strpos($file, '.txt') !== false) {
                    $stream = fopen('php://temp', 'r+');
                    if (@ftp_fget($ftp_conn, $stream, $file, FTP_ASCII, 0)) {
                        rewind($stream);
                        $content = stream_get_contents($stream);
                        $parts = preg_split('/\s+/', trim($content));
                        if (count($parts) >= 5) {
                            $user = trim($parts[0]);
                            $interface = trim($parts[1]);
                            $vpn_ip = trim($parts[2]);
                            $time_idx = count($parts) - 2;
                            $time = trim($parts[$time_idx]) . ' ' . trim($parts[$time_idx + 1]);
                            $public_ip = ($time_idx > 3) ? trim($parts[3]) : 'Unknown';
                            $active_connections_list[] = [
                                'user' => $user,
                                'interface' => $interface,
                                'vpn_ip' => $vpn_ip,
                                'public_ip' => $public_ip,
                                'time' => $time
                            ];
                        }
                    }
                    fclose($stream);
                }
            }
        }
        @ftp_close($ftp_conn);
    }
}

if (empty($active_connections_list)) {
    echo "<span style='color: #94a3b8;'>No routers are currently connected via PPTP.</span>";
    if (stripos(PHP_OS, 'WIN') === false) {
        $last_check = @shell_exec("last | grep 'still logged in' | grep 'ppp'");
        if (!empty(trim($last_check))) echo "<div style='margin-top: 10px; color: #cbd5e1;'><i class='fa-solid fa-info-circle'></i> Local connections found, but FTP realtime tracking is not set up yet. Run the updated setup script!</div>";
    }
} else {
    echo "<table class='table' style='margin-bottom: 0;'>";
    echo "<thead><tr><th>Username</th><th>Interface</th><th>VPN IP</th><th>Public IP</th><th>Connected Since</th></tr></thead>";
    echo "<tbody>";
    foreach ($active_connections_list as $conn_info) {
        echo "<tr>";
        echo "<td><strong style='color: var(--primary);'>" . htmlspecialchars($conn_info['user']) . "</strong></td>";
        echo "<td><span class='badge' style='background: rgba(148, 163, 184, 0.1); color: #94a3b8;'>" . htmlspecialchars($conn_info['interface']) . "</span></td>";
        echo "<td><span style='font-family: monospace; color: #10b981;'>" . htmlspecialchars($conn_info['vpn_ip']) . "</span></td>";
        echo "<td>" . htmlspecialchars($conn_info['public_ip']) . "</td>";
        echo "<td>" . htmlspecialchars($conn_info['time']) . "</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
}
?>
            </div>
            
            <div style="margin-top: 2rem;">
                <h3 style="margin-bottom: 0.5rem; font-size: 1rem; color: #cbd5e1;">Recent Connection History</h3>
                <div class="script-box" style="font-size: 0.8rem; color: #64748b; max-height: 200px; overflow-y: auto;">
                    <?php
                    $history = shell_exec("last | grep 'ppp' | head -n 10");
                    if (empty(trim($history))) {
                        echo "No history available yet.";
                    } else {
                        echo htmlspecialchars($history);
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($page == 'add'): ?>
        <div class="header">
            <h1 class="page-title">Add MikroTik Router</h1>
        </div>
        
        <div class="card" style="max-width: 600px;">
            <form action="?page=add" method="POST">
                <input type="hidden" name="action" value="add_router">
                
                <div class="form-group">
                    <label class="form-label">Router Name</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. Access_Router_34">
                </div>
                
                <div class="form-group" style="display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem;">
                    <div style="flex: 1;">
                        <label class="form-label">PPTP Username</label>
                        <input type="text" name="pptp_username" id="pptp_username" class="form-control" required>
                    </div>
                    <div style="flex: 1;">
                        <label class="form-label">PPTP Password</label>
                        <input type="text" name="pptp_password" id="pptp_password" class="form-control" required>
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary" onclick="generateCreds()"><i class="fa-solid fa-wand-magic-sparkles"></i> Auto</button>
                    </div>
                </div>
                
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Router</button>
                </div>
            </form>
        </div>
        <script>
            function generateCreds() {
                const randomStr = Math.random().toString(36).substring(2, 8);
                document.getElementById('pptp_username').value = 'user_' + randomStr;
                document.getElementById('pptp_password').value = Math.random().toString(36).substring(2, 10) + Math.random().toString(36).substring(2, 6);
            }
        </script>
        <?php endif; ?>

        <?php if($page == 'script'): ?>
        <div class="header">
            <h1 class="page-title"><span class="title-main">GENERATE</span> <span class="title-highlight">SCRIPT</span></h1>
        </div>
        
        <div style="padding: 1.5rem 2rem;">
            <div class="card">
                <div class="card-body" style="padding: 2rem;">
                    <div style="margin-bottom: 2rem;">
                        <h3 style="font-size: 1.3rem; font-weight: 700; margin-bottom: 0.5rem; color: #333;">SELECT ROUTER</h3>
                        <p style="color: #666; font-size: 1rem; margin-bottom: 1.5rem;">Choose a router to generate the MikroTik backup script</p>
                        
                        <form action="?page=script" method="GET" style="max-width: 400px;">
                            <input type="hidden" name="page" value="script">
                            <div style="display: flex; gap: 1rem;">
                                <select name="router_id" class="form-control" style="flex: 1; padding: 0.75rem 1rem; font-size: 1rem; font-weight: 500;" onchange="this.form.submit()">
                                    <option value="">-- Choose Router --</option>
                                    <?php foreach($routers as $r): ?>
                                        <option value="<?= $r['id'] ?>" <?= (isset($_GET['router_id']) && $_GET['router_id'] == $r['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($r['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-orange" style="padding: 0.75rem 1.5rem; font-size: 1rem;">
                                    <i class="fa-solid fa-code"></i> GENERATE
                                </button>
                            </div>
                        </form>
                    </div>

                    <?php
                    if (isset($_GET['router_id']) && !empty($_GET['router_id'])) {
                        $r_id = (int)$_GET['router_id'];
                        $r_data = null;
                        foreach($routers as $r) {
                            if($r['id'] == $r_id) {
                                $r_data = $r;
                                break;
                            }
                        }

                        if ($r_data) {
                            $r_name = htmlspecialchars($r_data['name']);
                            
                            // Automatically create FTP backup folder for this router if it was added before this fix
                            $ftp_conn = @ftp_connect($settings['ftp_server'], 21, 2);
                            if ($ftp_conn) {
                                if (@ftp_login($ftp_conn, $settings['ftp_user'], $settings['ftp_pass'])) {
                                    if (@ftp_mkdir($ftp_conn, $r_data['name'])) {
                                        @ftp_chmod($ftp_conn, 0755, $r_data['name']);
                                    }
                                }
                                @ftp_close($ftp_conn);
                            }
                            
                            $p_user = htmlspecialchars($r_data['pptp_username']);
                            $p_pass = htmlspecialchars($r_data['pptp_password']);
                            // The MikroTik router will connect to the FTP server using its public IP
                            // which bypasses any GRE/VPN data tunnel routing blocks.
                            $ftp_ip = $settings['ftp_server'];
                            $ftp_u = $settings['ftp_user'];
                            $ftp_p = $settings['ftp_pass'];
                            $pptp_ip = $settings['pptp_server'];
                            
                            $script_template = <<<'EOT'
/interface pptp-client
add connect-to="{{PPTP_IP}}" disabled=no name="pptp-rtbackup" password="{{P_PASS}}" user="{{P_USER}}" profile=default-encryption

/system script
remove [find name=rtbackup]
add name=rtbackup source={
:log info "STARTING BACKUP";
:global ftpIp "{{FTP_IP}}";
:global ftpU "{{FTP_U}}";
:global ftpP "{{FTP_P}}";
:global rname "{{R_NAME}}";

:global filename;
:global date [/system clock get date];
:global time [/system clock get time];
:global name [/system identity get name];

:global year;
:global month;
:global day;
:global hour [:pick $time 0 2];
:global min [:pick $time 3 5];
:global sec [:pick $time 6 8];

:if ([:pick $date 0 1] ~ "[0-9]") do={
    :set year [:pick $date 0 4];
    :set month [:pick $date 5 7];
    :set day [:pick $date 8 10];
} else={
    :set year [:pick $date 7 11];
    :set day [:pick $date 4 6];
    :local mmm [:pick $date 0 3];
    :local months ("jan","feb","mar","apr","may","jun","jul","aug","sep","oct","nov","dec");
    :set month ([ :find $months $mmm -1 ] + 1);
    :if ($month < 10) do={ :set month ("0" . $month); }
}

:set filename ($name. "-" .$year."-".$month."-".$day."-".$hour.$min.$sec);

/system backup save name=$filename;

:log info "DELAY 3S";
:delay 3s;

:log info "GENERATING RSC";
:global rsc $filename;
/export file=$rsc;
:delay 3s;

:log info "UPLOADING BACKUP TO FTP";
/tool fetch address=$ftpIp src-path="$filename.backup" user=$ftpU password=$ftpP mode=ftp dst-path="$rname/$filename.backup" upload=yes;
:delay 2s;

:log info "UPLOADING RSC TO FTP";
/tool fetch address=$ftpIp src-path="$filename.rsc" user=$ftpU password=$ftpP mode=ftp dst-path="$rname/$filename.rsc" upload=yes;
:delay 2s;

:log info "REMOVING LOCAL BACKUP FILES";
/file remove "$filename.backup";
/file remove "$filename.rsc";

:log info "BACKUP COMPLETED & FILES CLEANED";
}

/system scheduler
remove [find name="rtbackup-sched"]
add name="rtbackup-sched" on-event=rtbackup start-time=00:00:00 interval=1d

/system script run rtbackup
EOT;

                            $script = str_replace(
                                ['{{PPTP_IP}}', '{{P_PASS}}', '{{P_USER}}', '{{R_NAME}}', '{{FTP_IP}}', '{{FTP_U}}', '{{FTP_P}}'],
                                [$pptp_ip, $p_pass, $p_user, $r_name, $ftp_ip, $ftp_u, $ftp_p],
                                $script_template
                            );
                            ?>
                            
                            <div style="border-top: 2px solid #f0f0f0; padding-top: 2rem; margin-top: 2rem;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                    <div>
                                        <h3 style="font-size: 1.3rem; font-weight: 700; margin-bottom: 0.25rem; color: #333;">
                                            <i class="fa-solid fa-terminal" style="color: #4169e1; margin-right: 0.5rem;"></i>
                                            MIKROTIK TERMINAL SCRIPT
                                        </h3>
                                        <p style="color: #666; font-size: 1rem; margin: 0;">
                                            Router: <strong style="color: #ff6b35;"><?= $r_name ?></strong>
                                        </p>
                                    </div>
                                    <button class="btn btn-primary" onclick="copyScript()" style="padding: 0.75rem 1.5rem; font-size: 1rem;">
                                        <i class="fa-regular fa-copy"></i> COPY SCRIPT
                                    </button>
                                </div>
                                
                                <div class="alert" style="background-color: #e0f2fe; color: #075985; border: 1px solid #bae6fd; padding: 1rem; border-radius: 8px; font-size: 0.95rem; margin-bottom: 1rem;">
                                    <i class="fa-solid fa-info-circle" style="margin-right: 0.5rem;"></i>
                                    <strong>Instructions:</strong> Copy the script below and paste it into your MikroTik router's New Terminal window. The script will automatically run and schedule daily backups.
                                </div>
                                
                                <div class="script-box" id="scriptBox" style="position: relative;"><?= $script ?></div>
                                
                                <div style="margin-top: 1.5rem; padding: 1.5rem; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #ff6b35;">
                                    <h4 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; color: #333;">
                                        <i class="fa-solid fa-lightbulb" style="color: #ff6b35; margin-right: 0.5rem;"></i>
                                        WHAT THIS SCRIPT DOES:
                                    </h4>
                                    <ul style="margin: 0; padding-left: 1.5rem; color: #666; font-size: 0.95rem; line-height: 1.8;">
                                        <li>Creates a PPTP VPN connection to the backup server</li>
                                        <li>Generates a system backup (.backup file)</li>
                                        <li>Exports router configuration (.rsc file)</li>
                                        <li>Uploads both files to the FTP server</li>
                                        <li><strong style="color: #10b981;">Automatically removes backup files from MikroTik after successful upload</strong></li>
                                        <li>Schedules automatic daily backups at midnight</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <script>
                                function copyScript() {
                                    const scriptBox = document.getElementById('scriptBox');
                                    const text = scriptBox.innerText;
                                    navigator.clipboard.writeText(text).then(() => {
                                        const btn = event.target.closest('button');
                                        const originalHTML = btn.innerHTML;
                                        btn.innerHTML = '<i class="fa-solid fa-check"></i> COPIED!';
                                        btn.style.background = '#10b981';
                                        setTimeout(() => {
                                            btn.innerHTML = originalHTML;
                                            btn.style.background = '';
                                        }, 2000);
                                    });
                                }
                            </script>
                            <?php
                        }
                    } else {
                        ?>
                        <div style="text-align: center; padding: 3rem 1rem; color: #999;">
                            <i class="fa-solid fa-code" style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                            <p style="font-size: 1.1rem; font-weight: 600;">Please select a router to generate the script</p>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($page == 'users'): ?>
        <div class="header">
            <h1 class="page-title"><span class="title-main">USER</span> <span class="title-highlight">MANAGEMENT</span></h1>
            <button onclick="openAddUserModal()" class="btn btn-orange"><i class="fa-solid fa-user-plus"></i> ADD USER</button>
        </div>
        
        <?php
        // Fetch all users
        $users = [];
        $users_result = $conn->query("SELECT * FROM users ORDER BY id DESC");
        if ($users_result->num_rows > 0) {
            while($row = $users_result->fetch_assoc()) {
                $users[] = $row;
            }
        }
        ?>
        
        <div style="padding: 1.5rem 2rem;">
            <div class="card">
                <div class="card-body">
                    <div style="padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f0f0f0;">
                        <input type="text" placeholder="Search users..." style="padding: 0.5rem 1rem; border: 1px solid #e5e5e5; border-radius: 5px; width: 250px; font-size: 0.9rem;">
                        <div style="color: #666; font-size: 0.9rem; font-weight: 600;">
                            Total Users: <span style="color: #ff6b35;"><?= count($users) ?></span>
                        </div>
                    </div>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Last Login</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($users) > 0): ?>
                                    <?php foreach($users as $u): ?>
                                    <tr>
                                        <td><?= $u['id'] ?></td>
                                        <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                                        <td><?= htmlspecialchars($u['full_name']) ?></td>
                                        <td style="color: #666;"><?= htmlspecialchars($u['email']) ?></td>
                                        <td>
                                            <?php if($u['role'] === 'admin'): ?>
                                                <span class="badge badge-success">ADMIN</span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">USER</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="color: #666; font-size: 0.9rem;">
                                            <?= $u['last_login'] ? date('M d, Y g:i A', strtotime($u['last_login'])) : 'Never' ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 0.5rem;">
                                                <button onclick="openEditUserModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['full_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['email'], ENT_QUOTES) ?>', '<?= $u['role'] ?>')" class="btn btn-secondary" style="padding: 0.4rem 0.6rem; font-size: 0.85rem;">
                                                    <i class="fa-solid fa-edit"></i>
                                                </button>
                                                <?php if($u['id'] != $_SESSION['user_id']): ?>
                                                <button onclick="confirmDeleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')" class="btn btn-danger" style="padding: 0.4rem 0.6rem; font-size: 0.85rem;">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 2rem; color: #999;">No users found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add User Modal -->
        <div id="addUserModal" class="modal-overlay" style="display: none;">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fa-solid fa-user-plus text-primary me-2"></i> Add New User</h5>
                        <button onclick="closeAddUserModal()" class="close-btn">&times;</button>
                    </div>
                    <form method="POST" action="?page=users">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="add_user">
                            
                            <div class="form-group">
                                <label for="user_username">Username <span style="color: #dc3545;">*</span></label>
                                <input type="text" id="user_username" name="username" class="form-control" placeholder="e.g., john.doe" required>
                            </div>

                            <div class="form-group">
                                <label for="user_password">Password <span style="color: #dc3545;">*</span></label>
                                <input type="password" id="user_password" name="password" class="form-control" placeholder="Enter password" required>
                            </div>

                            <div class="form-group">
                                <label for="user_full_name">Full Name <span style="color: #dc3545;">*</span></label>
                                <input type="text" id="user_full_name" name="full_name" class="form-control" placeholder="e.g., John Doe" required>
                            </div>

                            <div class="form-group">
                                <label for="user_email">Email</label>
                                <input type="email" id="user_email" name="email" class="form-control" placeholder="e.g., john@example.com">
                            </div>

                            <div class="form-group">
                                <label for="user_role">Role <span style="color: #dc3545;">*</span></label>
                                <select id="user_role" name="role" class="form-control" required>
                                    <option value="user">User</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" onclick="closeAddUserModal()" class="btn btn-secondary">
                                <i class="fa-solid fa-times"></i> Cancel
                            </button>
                            <button type="submit" class="btn btn-orange">
                                <i class="fa-solid fa-save"></i> Save User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit User Modal -->
        <div id="editUserModal" class="modal-overlay" style="display: none;">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fa-solid fa-user-edit text-primary me-2"></i> Edit User</h5>
                        <button onclick="closeEditUserModal()" class="close-btn">&times;</button>
                    </div>
                    <form method="POST" action="?page=users">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="edit_user">
                            <input type="hidden" name="user_id" id="edit_user_id">
                            
                            <div class="form-group">
                                <label for="edit_user_username">Username <span style="color: #dc3545;">*</span></label>
                                <input type="text" id="edit_user_username" name="username" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label for="edit_user_password">Password</label>
                                <input type="password" id="edit_user_password" name="password" class="form-control" placeholder="Leave blank to keep current password">
                                <small class="form-text">Only fill this if you want to change the password</small>
                            </div>

                            <div class="form-group">
                                <label for="edit_user_full_name">Full Name <span style="color: #dc3545;">*</span></label>
                                <input type="text" id="edit_user_full_name" name="full_name" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label for="edit_user_email">Email</label>
                                <input type="email" id="edit_user_email" name="email" class="form-control">
                            </div>

                            <div class="form-group">
                                <label for="edit_user_role">Role <span style="color: #dc3545;">*</span></label>
                                <select id="edit_user_role" name="role" class="form-control" required>
                                    <option value="user">User</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" onclick="closeEditUserModal()" class="btn btn-secondary">
                                <i class="fa-solid fa-times"></i> Cancel
                            </button>
                            <button type="submit" class="btn btn-orange">
                                <i class="fa-solid fa-save"></i> Update User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete User Form (Hidden) -->
        <form id="deleteUserForm" method="POST" action="?page=users" style="display: none;">
            <input type="hidden" name="action" value="delete_user">
            <input type="hidden" name="user_id" id="delete_user_id">
        </form>

        <script>
            function openAddUserModal() {
                document.getElementById('addUserModal').style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }

            function closeAddUserModal() {
                document.getElementById('addUserModal').style.display = 'none';
                document.body.style.overflow = 'auto';
            }

            function openEditUserModal(id, username, fullName, email, role) {
                document.getElementById('edit_user_id').value = id;
                document.getElementById('edit_user_username').value = username;
                document.getElementById('edit_user_full_name').value = fullName;
                document.getElementById('edit_user_email').value = email;
                document.getElementById('edit_user_role').value = role;
                document.getElementById('edit_user_password').value = '';
                document.getElementById('editUserModal').style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }

            function closeEditUserModal() {
                document.getElementById('editUserModal').style.display = 'none';
                document.body.style.overflow = 'auto';
            }

            function confirmDeleteUser(id, username) {
                if (confirm('Are you sure you want to delete user "' + username + '"?\n\nThis action cannot be undone!')) {
                    document.getElementById('delete_user_id').value = id;
                    document.getElementById('deleteUserForm').submit();
                }
            }

            // Close modals when clicking outside
            document.getElementById('addUserModal')?.addEventListener('click', function(e) {
                if (e.target === this) closeAddUserModal();
            });

            document.getElementById('editUserModal')?.addEventListener('click', function(e) {
                if (e.target === this) closeEditUserModal();
            });
        </script>
        <?php endif; ?>

        <?php if($page == 'settings'): ?>
        <div class="header">
            <h1 class="page-title"><span class="title-main">GLOBAL</span> <span class="title-highlight">SETTINGS</span></h1>
        </div>
        
        <?php
        // Get current theme
        $current_theme = isset($_SESSION['theme']) ? $_SESSION['theme'] : 'brown';
        ?>
        
        <div style="padding: 1.5rem 2rem;">
            <!-- Theme Settings Card -->
            <div class="card" style="margin-bottom: 2rem;">
                <div class="card-body" style="padding: 1.5rem;">
                    <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.25rem; color: #333;">
                        <i class="fa-solid fa-palette" style="color: #ff6b35; margin-right: 0.5rem;"></i>
                        PORTAL THEME
                    </h3>
                    <p style="color: #666; font-size: 0.9rem; margin-bottom: 1.5rem;">Choose your preferred color theme for the portal</p>
                    
                    <div id="themeSelector" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                        <!-- Brown Theme (Default) -->
                        <div class="theme-card <?= $current_theme === 'brown' ? 'active' : '' ?>" data-theme="brown" onclick="changeTheme('brown')">
                            <div class="theme-colors-mini">
                                <div style="background: #5d3a1a;"></div>
                                <div style="background: #ff6b35;"></div>
                                <div style="background: #4169e1;"></div>
                            </div>
                            <div class="theme-name-mini">CLASSIC BROWN</div>
                            <?= $current_theme === 'brown' ? '<div class="theme-check"><i class="fa-solid fa-check"></i></div>' : '' ?>
                        </div>

                        <!-- Blue Theme -->
                        <div class="theme-card <?= $current_theme === 'blue' ? 'active' : '' ?>" data-theme="blue" onclick="changeTheme('blue')">
                            <div class="theme-colors-mini">
                                <div style="background: #1e3a8a;"></div>
                                <div style="background: #3b82f6;"></div>
                                <div style="background: #06b6d4;"></div>
                            </div>
                            <div class="theme-name-mini">OCEAN BLUE</div>
                            <?= $current_theme === 'blue' ? '<div class="theme-check"><i class="fa-solid fa-check"></i></div>' : '' ?>
                        </div>

                        <!-- Dark Theme -->
                        <div class="theme-card <?= $current_theme === 'dark' ? 'active' : '' ?>" data-theme="dark" onclick="changeTheme('dark')">
                            <div class="theme-colors-mini">
                                <div style="background: #1f2937;"></div>
                                <div style="background: #f59e0b;"></div>
                                <div style="background: #8b5cf6;"></div>
                            </div>
                            <div class="theme-name-mini">DARK NIGHT</div>
                            <?= $current_theme === 'dark' ? '<div class="theme-check"><i class="fa-solid fa-check"></i></div>' : '' ?>
                        </div>

                        <!-- Green Theme -->
                        <div class="theme-card <?= $current_theme === 'green' ? 'active' : '' ?>" data-theme="green" onclick="changeTheme('green')">
                            <div class="theme-colors-mini">
                                <div style="background: #065f46;"></div>
                                <div style="background: #10b981;"></div>
                                <div style="background: #14b8a6;"></div>
                            </div>
                            <div class="theme-name-mini">FOREST GREEN</div>
                            <?= $current_theme === 'green' ? '<div class="theme-check"><i class="fa-solid fa-check"></i></div>' : '' ?>
                        </div>

                        <!-- Purple Theme -->
                        <div class="theme-card <?= $current_theme === 'purple' ? 'active' : '' ?>" data-theme="purple" onclick="changeTheme('purple')">
                            <div class="theme-colors-mini">
                                <div style="background: #6b21a8;"></div>
                                <div style="background: #a855f7;"></div>
                                <div style="background: #ec4899;"></div>
                            </div>
                            <div class="theme-name-mini">ROYAL PURPLE</div>
                            <?= $current_theme === 'purple' ? '<div class="theme-check"><i class="fa-solid fa-check"></i></div>' : '' ?>
                        </div>

                        <!-- Red Theme -->
                        <div class="theme-card <?= $current_theme === 'red' ? 'active' : '' ?>" data-theme="red" onclick="changeTheme('red')">
                            <div class="theme-colors-mini">
                                <div style="background: #991b1b;"></div>
                                <div style="background: #ef4444;"></div>
                                <div style="background: #f97316;"></div>
                            </div>
                            <div class="theme-name-mini">CRIMSON RED</div>
                            <?= $current_theme === 'red' ? '<div class="theme-check"><i class="fa-solid fa-check"></i></div>' : '' ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Server Settings Card -->
            <div class="card" style="max-width: 600px;">
                <div class="card-body" style="padding: 2rem;">
                    <h3 style="font-size: 1.3rem; font-weight: 700; margin-bottom: 0.5rem; color: #333;">
                        <i class="fa-solid fa-server" style="color: #4169e1; margin-right: 0.5rem;"></i>
                        SERVER CONFIGURATION
                    </h3>
                    <div class="alert" style="background-color: #fff3cd; color: #856404; border: 1px solid #ffeaa7; padding: 1rem; border-radius: 8px; font-size: 0.95rem; margin-bottom: 2rem;">
                        <i class="fa-solid fa-info-circle"></i> <strong>Note:</strong> Currently these settings are defined in <code>config.php</code>. To make them dynamic, you can store them in a database table.
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 1rem;">FTP SERVER IP</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($settings['ftp_server']) ?>" readonly style="background: #f8f9fa;">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 1rem;">FTP USERNAME</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($settings['ftp_user']) ?>" readonly style="background: #f8f9fa;">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 1rem;">PPTP SERVER IP</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($settings['pptp_server']) ?>" readonly style="background: #f8f9fa;">
                    </div>
                </div>
            </div>
        </div>

        <script>
            function changeTheme(theme) {
                // Update UI immediately - remove all active classes
                document.querySelectorAll('.theme-card').forEach(card => {
                    card.classList.remove('active');
                    const check = card.querySelector('.theme-check');
                    if (check) check.remove();
                });
                
                // Add active class to clicked theme
                const clickedCard = document.querySelector(`.theme-card[data-theme="${theme}"]`);
                if (clickedCard) {
                    clickedCard.classList.add('active');
                    const checkmark = document.createElement('div');
                    checkmark.className = 'theme-check';
                    checkmark.innerHTML = '<i class="fa-solid fa-check"></i>';
                    clickedCard.appendChild(checkmark);
                }

                // Send AJAX request to save theme
                const formData = new FormData();
                formData.append('ajax_change_theme', '1');
                formData.append('theme', theme);

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Reload page to apply new theme colors
                        setTimeout(() => {
                            window.location.reload();
                        }, 300);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to change theme. Please try again.');
                });
            }
        </script>
        <?php endif; ?>

    </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
