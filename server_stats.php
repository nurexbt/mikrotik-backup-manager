<?php
/**
 * Server Statistics API
 * 
 * This file should be placed on your Ubuntu FTP server (e.g., /var/www/html/server_stats.php)
 * and accessed via HTTP to get real-time server statistics.
 * 
 * Access: http://192.168.201.1/server_stats.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$stats = [];

// Get CPU Usage
$cpu_load = sys_getloadavg();
$cpu_cores = (int)shell_exec('nproc');
$cpu_usage = 0;

$cpu_stat = shell_exec("top -bn1 | grep 'Cpu(s)' | sed 's/.*, *\\([0-9.]*\\)%* id.*/\\1/' | awk '{print 100 - $1}'");
if ($cpu_stat) {
    $cpu_usage = round((float)trim($cpu_stat), 1);
}

$stats['cpu'] = [
    'usage_percent' => $cpu_usage,
    'cores' => $cpu_cores,
    'load_average' => [
        '1min' => round($cpu_load[0], 2),
        '5min' => round($cpu_load[1], 2),
        '15min' => round($cpu_load[2], 2)
    ]
];

// Get RAM Usage
$mem_info = shell_exec('free -b | grep Mem');
if ($mem_info) {
    $parts = preg_split('/\s+/', trim($mem_info));
    if (count($parts) >= 4) {
        $ram_total = (float)$parts[1];
        $ram_used = (float)$parts[2];
        $ram_free = (float)$parts[3];
        
        $stats['ram'] = [
            'total_bytes' => $ram_total,
            'used_bytes' => $ram_used,
            'free_bytes' => $ram_free,
            'usage_percent' => round(($ram_used / $ram_total) * 100, 1)
        ];
    }
}

// Get Disk Usage for /home/ftpuser
$disk_info = shell_exec('df -B1 /home/ftpuser 2>/dev/null | tail -1');
if ($disk_info) {
    $parts = preg_split('/\s+/', trim($disk_info));
    if (count($parts) >= 4) {
        $disk_total = (float)$parts[1];
        $disk_used = (float)$parts[2];
        $disk_free = (float)$parts[3];
        
        $stats['disk'] = [
            'total_bytes' => $disk_total,
            'used_bytes' => $disk_used,
            'free_bytes' => $disk_free,
            'usage_percent' => round(($disk_used / $disk_total) * 100, 1)
        ];
    }
}

// Get FTP Server Status
$ftp_status = shell_exec('systemctl is-active vsftpd 2>/dev/null');
$stats['ftp_server'] = [
    'status' => trim($ftp_status) === 'active' ? 'online' : 'offline',
    'service' => 'vsftpd'
];

// Get Backup Statistics from FTP directory
$backup_stats = [
    'total_backups' => 0,
    'total_size_bytes' => 0,
    'last_backup_time' => 0
];

$ftp_base_dir = '/home/ftpuser';
if (is_dir($ftp_base_dir)) {
    $router_dirs = glob($ftp_base_dir . '/*', GLOB_ONLYDIR);
    
    foreach ($router_dirs as $router_dir) {
        $dirname = basename($router_dir);
        
        // Skip special directories
        if ($dirname === 'active_connections' || $dirname === '.' || $dirname === '..') {
            continue;
        }
        
        // Scan for backup files
        $backup_files = glob($router_dir . '/*.{backup,rsc}', GLOB_BRACE);
        
        if (is_array($backup_files)) {
            foreach ($backup_files as $file) {
                if (is_file($file)) {
                    $backup_stats['total_backups']++;
                    $backup_stats['total_size_bytes'] += filesize($file);
                    
                    $mtime = filemtime($file);
                    if ($mtime > $backup_stats['last_backup_time']) {
                        $backup_stats['last_backup_time'] = $mtime;
                    }
                }
            }
        }
    }
}

$stats['backups'] = $backup_stats;

// Get Server Uptime
$uptime = shell_exec('uptime -p');
$stats['uptime'] = trim($uptime);

// Get Server Hostname
$stats['hostname'] = trim(shell_exec('hostname'));

// Get Server IP
$stats['ip'] = trim(shell_exec("hostname -I | awk '{print $1}'"));

// Get Current Time
$stats['timestamp'] = time();
$stats['datetime'] = date('Y-m-d H:i:s');

echo json_encode($stats, JSON_PRETTY_PRINT);
?>
