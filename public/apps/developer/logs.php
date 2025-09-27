<?php
/**
 * System Logs API for Developer Portal
 * SBL Sistema Interno - Developer Section
 */

require_once 'auth.php';

header('Content-Type: application/json');

try {
    $path = $_GET['path'] ?? 'recent';
    $level = $_GET['level'] ?? '';
    $limit = (int)($_GET['limit'] ?? 50);
    
    switch ($path) {
        case 'recent':
            echo json_encode([
                'success' => true,
                'data' => getRecentLogs($level, $limit)
            ]);
            break;
            
        case 'search':
            $query = $_GET['query'] ?? '';
            echo json_encode([
                'success' => true,
                'data' => searchLogs($query, $level, $limit)
            ]);
            break;
            
        case 'stats':
            echo json_encode([
                'success' => true,
                'data' => getLogStats()
            ]);
            break;
            
        case 'download':
            downloadLogs();
            break;
            
        default:
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Log endpoint not found'
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving system logs',
        'error' => $e->getMessage()
    ]);
}

/**
 * Get recent system logs
 */
function getRecentLogs($level = '', $limit = 50) {
    $logs = [];
    
    // Simulated log entries for demo
    $logTypes = ['INFO', 'DEBUG', 'WARN', 'ERROR'];
    $components = ['System', 'Database', 'API', 'Auth', 'Cache', 'Storage'];
    $messages = [
        'INFO' => [
            'System startup completed successfully',
            'Database connection established',
            'API request processed',
            'User authentication successful',
            'Cache cleared successfully',
            'Backup operation completed',
            'Configuration updated'
        ],
        'DEBUG' => [
            'Database query executed',
            'Cache hit for key',
            'Session created',
            'File uploaded successfully',
            'Memory usage: %d MB',
            'Processing request ID: %s'
        ],
        'WARN' => [
            'High memory usage detected (%d%%)',
            'Slow query detected (%dms)',
            'Cache miss rate high',
            'Connection pool nearly full',
            'Disk space running low',
            'Rate limit approaching'
        ],
        'ERROR' => [
            'Failed to connect to external service',
            'Database connection lost',
            'Authentication failed',
            'File upload failed',
            'Cache server unreachable',
            'Configuration error detected'
        ]
    ];
    
    for ($i = 0; $i < $limit; $i++) {
        $logLevel = $logTypes[array_rand($logTypes)];
        
        // Skip if level filter is set and doesn't match
        if (!empty($level) && strtolower($logLevel) !== strtolower($level)) {
            continue;
        }
        
        $component = $components[array_rand($components)];
        $messageTemplate = $messages[$logLevel][array_rand($messages[$logLevel])];
        
        // Add random values to message templates
        $message = sprintf($messageTemplate, rand(50, 95), rand(100, 999));
        
        $timestamp = date('Y-m-d H:i:s', strtotime('-' . rand(1, 1440) . ' minutes'));
        
        $logs[] = [
            'id' => $i + 1,
            'timestamp' => $timestamp,
            'level' => $logLevel,
            'component' => $component,
            'message' => $message,
            'file' => '/var/log/' . strtolower($component) . '.log',
            'line' => rand(1, 500),
            'user_id' => rand(1, 100),
            'session_id' => md5(uniqid())
        ];
    }
    
    // Sort by timestamp descending
    usort($logs, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    return [
        'logs' => array_slice($logs, 0, $limit),
        'total_count' => count($logs),
        'filtered_count' => count($logs),
        'level_filter' => $level,
        'limit' => $limit
    ];
}

/**
 * Search logs by query
 */
function searchLogs($query, $level = '', $limit = 50) {
    $allLogs = getRecentLogs('', 1000)['logs'];
    $filteredLogs = [];
    
    foreach ($allLogs as $log) {
        // Level filter
        if (!empty($level) && strtolower($log['level']) !== strtolower($level)) {
            continue;
        }
        
        // Text search
        if (!empty($query)) {
            $searchText = strtolower($log['message'] . ' ' . $log['component']);
            if (strpos($searchText, strtolower($query)) === false) {
                continue;
            }
        }
        
        $filteredLogs[] = $log;
        
        if (count($filteredLogs) >= $limit) {
            break;
        }
    }
    
    return [
        'logs' => $filteredLogs,
        'total_count' => 1000,
        'filtered_count' => count($filteredLogs),
        'query' => $query,
        'level_filter' => $level,
        'limit' => $limit
    ];
}

/**
 * Get log statistics
 */
function getLogStats() {
    return [
        'total_entries' => rand(10000, 50000),
        'entries_today' => rand(500, 2000),
        'level_breakdown' => [
            'INFO' => rand(60, 80),
            'DEBUG' => rand(10, 20),
            'WARN' => rand(5, 15),
            'ERROR' => rand(1, 5)
        ],
        'top_components' => [
            ['name' => 'API', 'count' => rand(1000, 3000)],
            ['name' => 'Database', 'count' => rand(800, 2500)],
            ['name' => 'Auth', 'count' => rand(500, 1500)],
            ['name' => 'System', 'count' => rand(300, 1000)],
            ['name' => 'Cache', 'count' => rand(200, 800)]
        ],
        'error_trends' => [
            'last_hour' => rand(0, 10),
            'last_24h' => rand(5, 50),
            'last_week' => rand(20, 200)
        ],
        'log_files' => [
            ['name' => 'application.log', 'size' => rand(10, 100) . 'MB', 'last_modified' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 60) . ' minutes'))],
            ['name' => 'error.log', 'size' => rand(1, 20) . 'MB', 'last_modified' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 30) . ' minutes'))],
            ['name' => 'access.log', 'size' => rand(50, 200) . 'MB', 'last_modified' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 10) . ' minutes'))],
            ['name' => 'debug.log', 'size' => rand(5, 50) . 'MB', 'last_modified' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 120) . ' minutes'))]
        ]
    ];
}

/**
 * Download logs as file
 */
function downloadLogs() {
    $logs = getRecentLogs('', 1000)['logs'];
    
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="system_logs_' . date('Y-m-d_H-i-s') . '.log"');
    
    foreach ($logs as $log) {
        echo "[{$log['timestamp']}] {$log['level']}: {$log['component']} - {$log['message']}\n";
    }
    
    exit;
}

/**
 * Get real log files (if they exist)
 */
function getRealLogFiles() {
    $logDirectories = [
        __DIR__ . '/../../../storage/logs/',
        '/var/log/',
        $_SERVER['DOCUMENT_ROOT'] . '/logs/'
    ];
    
    $logFiles = [];
    
    foreach ($logDirectories as $dir) {
        if (is_dir($dir)) {
            $files = glob($dir . '*.log');
            foreach ($files as $file) {
                if (is_readable($file)) {
                    $logFiles[] = [
                        'name' => basename($file),
                        'path' => $file,
                        'size' => formatBytes(filesize($file)),
                        'last_modified' => date('Y-m-d H:i:s', filemtime($file)),
                        'lines' => countLines($file)
                    ];
                }
            }
        }
    }
    
    return $logFiles;
}

/**
 * Count lines in a file
 */
function countLines($file) {
    $lineCount = 0;
    $handle = fopen($file, 'r');
    
    if ($handle) {
        while (($line = fgets($handle)) !== false) {
            $lineCount++;
        }
        fclose($handle);
    }
    
    return $lineCount;
}

/**
 * Format bytes to human readable
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>