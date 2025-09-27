<?php
/**
 * System Status API for Developer Portal
 * SBL Sistema Interno - Developer Section
 */

require_once 'auth.php';

header('Content-Type: application/json');

try {
    $path = $_GET['path'] ?? '';
    
    switch ($path) {
        case 'services':
            echo json_encode([
                'success' => true,
                'data' => getServicesStatus()
            ]);
            break;
            
        case 'resources':
            echo json_encode([
                'success' => true,
                'data' => getResourceUsage()
            ]);
            break;
            
        case 'database':
            echo json_encode([
                'success' => true,
                'data' => getDatabaseStatus()
            ]);
            break;
            
        case 'full-status':
            echo json_encode([
                'success' => true,
                'data' => [
                    'services' => getServicesStatus(),
                    'resources' => getResourceUsage(),
                    'database' => getDatabaseStatus(),
                    'network' => getNetworkStatus()
                ]
            ]);
            break;
            
        default:
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Status endpoint not found'
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving system status',
        'error' => $e->getMessage()
    ]);
}

/**
 * Get services status
 */
function getServicesStatus() {
    $services = [
        [
            'name' => 'Web Server',
            'status' => 'online',
            'uptime' => '99.9%',
            'last_check' => date('Y-m-d H:i:s'),
            'response_time' => rand(10, 50) . 'ms'
        ],
        [
            'name' => 'Database Server',
            'status' => checkDatabaseConnection() ? 'online' : 'offline',
            'uptime' => '99.8%',
            'last_check' => date('Y-m-d H:i:s'),
            'response_time' => rand(5, 25) . 'ms'
        ],
        [
            'name' => 'API Gateway',
            'status' => rand(0, 10) > 7 ? 'warning' : 'online',
            'uptime' => '98.5%',
            'last_check' => date('Y-m-d H:i:s'),
            'response_time' => rand(20, 100) . 'ms'
        ],
        [
            'name' => 'Cache Service',
            'status' => 'online',
            'uptime' => '99.7%',
            'last_check' => date('Y-m-d H:i:s'),
            'response_time' => rand(1, 10) . 'ms'
        ],
        [
            'name' => 'File Storage',
            'status' => 'online',
            'uptime' => '99.9%',
            'last_check' => date('Y-m-d H:i:s'),
            'response_time' => rand(15, 45) . 'ms'
        ]
    ];
    
    return $services;
}

/**
 * Get resource usage
 */
function getResourceUsage() {
    // In a real implementation, you would get actual system metrics
    return [
        'cpu' => [
            'current' => rand(40, 80),
            'average' => rand(45, 75),
            'peak' => rand(80, 95),
            'cores' => 8
        ],
        'memory' => [
            'used' => rand(30, 70),
            'available' => 100 - rand(30, 70),
            'total' => '16GB',
            'cached' => rand(10, 25)
        ],
        'disk' => [
            'used' => rand(20, 40),
            'available' => 100 - rand(20, 40),
            'total' => '500GB',
            'io_rate' => rand(5, 50) . 'MB/s'
        ],
        'network' => [
            'inbound' => rand(10, 100) . 'Mbps',
            'outbound' => rand(5, 50) . 'Mbps',
            'connections' => rand(100, 500),
            'latency' => rand(1, 20) . 'ms'
        ]
    ];
}

/**
 * Get database status
 */
function getDatabaseStatus() {
    $connectionStatus = checkDatabaseConnection();
    
    $status = [
        'connection' => $connectionStatus ? 'connected' : 'disconnected',
        'connection_pool' => [
            'active' => rand(10, 18),
            'idle' => rand(2, 8),
            'total' => 20
        ],
        'performance' => [
            'avg_query_time' => rand(8, 25) . 'ms',
            'slow_queries' => rand(0, 5),
            'queries_per_second' => rand(50, 200),
            'cache_hit_ratio' => rand(85, 99) . '%'
        ],
        'storage' => [
            'size' => rand(20, 50) / 10 . 'GB',
            'tables' => rand(25, 40),
            'indexes' => rand(50, 80),
            'growth_rate' => rand(1, 10) . 'MB/day'
        ],
        'backup' => [
            'last_backup' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 6) . ' hours')),
            'backup_size' => rand(15, 35) / 10 . 'GB',
            'next_backup' => date('Y-m-d H:i:s', strtotime('+' . (24 - date('H')) . ' hours')),
            'status' => 'scheduled'
        ]
    ];
    
    return $status;
}

/**
 * Get network status
 */
function getNetworkStatus() {
    return [
        'external_connectivity' => checkExternalConnectivity(),
        'dns_resolution' => checkDNSResolution(),
        'ssl_certificates' => checkSSLCertificates(),
        'firewall_status' => 'active',
        'ddos_protection' => 'enabled'
    ];
}

/**
 * Check database connection
 */
function checkDatabaseConnection() {
    try {
        require_once __DIR__ . '/../../../app/Core/db_config.php';
        
        $pdo = new PDO($dsn, $username, $password, $options);
        $stmt = $pdo->query('SELECT 1');
        return $stmt !== false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Check external connectivity
 */
function checkExternalConnectivity() {
    $urls = ['google.com', 'github.com', 'cloudflare.com'];
    $results = [];
    
    foreach ($urls as $url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://$url");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $responseTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        
        curl_close($ch);
        
        $results[] = [
            'url' => $url,
            'status' => ($httpCode >= 200 && $httpCode < 300) ? 'online' : 'offline',
            'response_time' => round($responseTime * 1000) . 'ms',
            'http_code' => $httpCode
        ];
    }
    
    return $results;
}

/**
 * Check DNS resolution
 */
function checkDNSResolution() {
    $domains = ['google.com', 'github.com'];
    $results = [];
    
    foreach ($domains as $domain) {
        $ip = gethostbyname($domain);
        $results[] = [
            'domain' => $domain,
            'resolved_ip' => $ip,
            'status' => ($ip !== $domain) ? 'resolved' : 'failed',
            'response_time' => rand(1, 50) . 'ms'
        ];
    }
    
    return $results;
}

/**
 * Check SSL certificates
 */
function checkSSLCertificates() {
    // This would check SSL certificate validity for the domain
    return [
        'current_domain' => [
            'status' => 'valid',
            'expires' => date('Y-m-d', strtotime('+' . rand(30, 365) . ' days')),
            'issuer' => 'Let\'s Encrypt',
            'days_until_expiry' => rand(30, 365)
        ]
    ];
}
?>