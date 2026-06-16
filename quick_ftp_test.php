<?php
/**
 * Quick FTP Test - Non-blocking version
 */

require_once 'config.php';

// Set shorter timeout
ini_set('default_socket_timeout', 5);

$results = [];

// Test 1: Connection
$results['connection'] = false;
$ftp_conn = @ftp_connect($settings['ftp_server'], 21, 5);
if ($ftp_conn) {
    $results['connection'] = true;
    
    // Test 2: Login
    $results['login'] = @ftp_login($ftp_conn, $settings['ftp_user'], $settings['ftp_pass']);
    
    if ($results['login']) {
        // Test 3: Passive mode OFF
        @ftp_pasv($ftp_conn, false);
        $results['active_mode'] = @ftp_nlist($ftp_conn, '.');
        
        // Test 4: Passive mode ON
        @ftp_pasv($ftp_conn, true);
        $results['passive_mode'] = @ftp_nlist($ftp_conn, '.');
        
        // Test 5: Raw list
        $results['rawlist'] = @ftp_rawlist($ftp_conn, '.');
    }
    
    @ftp_close($ftp_conn);
}

header('Content-Type: application/json');
echo json_encode([
    'connection' => $results['connection'] ? 'SUCCESS' : 'FAILED',
    'login' => isset($results['login']) && $results['login'] ? 'SUCCESS' : 'FAILED',
    'active_mode' => isset($results['active_mode']) && is_array($results['active_mode']) ? count($results['active_mode']) . ' items' : 'FAILED',
    'passive_mode' => isset($results['passive_mode']) && is_array($results['passive_mode']) ? count($results['passive_mode']) . ' items' : 'FAILED',
    'rawlist' => isset($results['rawlist']) && is_array($results['rawlist']) ? count($results['rawlist']) . ' items' : 'FAILED',
    'active_list' => isset($results['active_mode']) && is_array($results['active_mode']) ? $results['active_mode'] : null,
    'passive_list' => isset($results['passive_mode']) && is_array($results['passive_mode']) ? $results['passive_mode'] : null,
    'raw_list' => isset($results['rawlist']) && is_array($results['rawlist']) ? $results['rawlist'] : null
], JSON_PRETTY_PRINT);
?>
