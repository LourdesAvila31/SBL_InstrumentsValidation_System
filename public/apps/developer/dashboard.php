<?php
/**
 * Developer Portal Dashboard API
 * SBL Sistema Interno - Developer Section
 */

require_once 'auth.php';

header('Content-Type: application/json');

try {
    $path = $_GET['path'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($path) {
        case 'developer-info':
            echo json_encode([
                'success' => true,
                'data' => getDeveloperUserInfo()
            ]);
            break;
            
        case 'kpis':
            echo json_encode([
                'success' => true,
                'data' => getDashboardKPIs()
            ]);
            break;
            
        case 'charts':
            echo json_encode([
                'success' => true,
                'data' => getChartData()
            ]);
            break;
            
        case 'system-health':
            echo json_encode([
                'success' => true,
                'data' => getSystemHealth()
            ]);
            break;
            
        case 'recent-activity':
            echo json_encode([
                'success' => true,
                'data' => getRecentActivity()
            ]);
            break;
            
        default:
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Endpoint not found'
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error' => $e->getMessage()
    ]);
}

/**
 * Get developer user information
 */
function getDeveloperUserInfo() {
    $user = getDeveloperUser();
    
    return [
        'nombre_completo' => $user['name'],
        'usuario' => $user['username'],
        'rol' => $user['role'],
        'last_login' => date('Y-m-d H:i:s', $user['last_activity']),
        'permissions' => getDeveloperPermissions()
    ];
}

/**
 * Get developer permissions
 */
function getDeveloperPermissions() {
    return [
        'view_system_status' => true,
        'access_api_docs' => true,
        'manage_database' => true,
        'view_logs' => true,
        'manage_backups' => true,
        'system_configuration' => true,
        'security_settings' => true,
        'gamp5_validation' => true
    ];
}

/**
 * Get dashboard KPIs
 */
function getDashboardKPIs() {
    return [
        'incidents' => [
            'open_incidents' => getOpenIncidentsCount(),
            'status' => getIncidentsStatus(),
            'trend' => 'stable'
        ],
        'performance' => [
            'uptime_percentage' => getSystemUptime(),
            'status' => 'good',
            'avg_response_time' => getAverageResponseTime()
        ],
        'security' => [
            'security_alerts' => getSecurityAlertsCount(),
            'status' => 'good',
            'last_scan' => date('Y-m-d H:i:s', strtotime('-2 hours'))
        ],
        'compliance' => [
            'compliance_score' => getComplianceScore(),
            'status' => 'good',
            'last_audit' => date('Y-m-d', strtotime('-1 week'))
        ]
    ];
}

/**
 * Get chart data for dashboard
 */
function getChartData() {
    return [
        'performance_trend' => getPerformanceTrend(),
        'incident_timeline' => getIncidentTimeline(),
        'user_activity' => getUserActivityData(),
        'system_health' => getSystemHealthTimeline()
    ];
}

/**
 * Get system health metrics
 */
function getSystemHealth() {
    return [
        'services' => [
            ['name' => 'Web Server', 'status' => 'online', 'uptime' => '99.9%'],
            ['name' => 'Database', 'status' => 'online', 'uptime' => '99.8%'],
            ['name' => 'API Gateway', 'status' => 'warning', 'uptime' => '98.5%'],
            ['name' => 'Cache Service', 'status' => 'online', 'uptime' => '99.7%'],
            ['name' => 'File Storage', 'status' => 'online', 'uptime' => '99.9%']
        ],
        'resources' => [
            'cpu' => rand(40, 80),
            'memory' => rand(30, 70),
            'disk' => rand(20, 40),
            'network' => rand(10, 30)
        ],
        'database' => [
            'connection_pool' => rand(10, 20) . '/20',
            'query_response_avg' => rand(8, 25) . 'ms',
            'last_backup' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 6) . ' hours')),
            'index_health' => 'optimal'
        ]
    ];
}

/**
 * Get recent system activity
 */
function getRecentActivity() {
    $activities = [
        [
            'type' => 'user_registration',
            'message' => 'New user registration: ' . generateRandomEmail(),
            'timestamp' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 10) . ' minutes')),
            'severity' => 'info'
        ],
        [
            'type' => 'database_backup',
            'message' => 'Database backup completed successfully',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-' . rand(10, 30) . ' minutes')),
            'severity' => 'success'
        ],
        [
            'type' => 'high_cpu',
            'message' => 'High CPU usage detected (' . rand(80, 95) . '%)',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-' . rand(30, 120) . ' minutes')),
            'severity' => 'warning'
        ],
        [
            'type' => 'system_update',
            'message' => 'System update applied successfully',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-' . rand(2, 8) . ' hours')),
            'severity' => 'success'
        ],
        [
            'type' => 'security_scan',
            'message' => 'Security scan completed - no threats detected',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-' . rand(4, 12) . ' hours')),
            'severity' => 'success'
        ]
    ];
    
    return array_slice($activities, 0, 10);
}

/**
 * Helper functions for generating mock data
 */
function getOpenIncidentsCount() {
    return rand(0, 15);
}

function getIncidentsStatus() {
    $count = getOpenIncidentsCount();
    if ($count == 0) return 'good';
    if ($count <= 5) return 'attention';
    if ($count <= 10) return 'warning';
    return 'critical';
}

function getSystemUptime() {
    return number_format(rand(9950, 9999) / 100, 1);
}

function getAverageResponseTime() {
    return rand(80, 150) . 'ms';
}

function getSecurityAlertsCount() {
    return rand(0, 5);
}

function getComplianceScore() {
    return rand(85, 99);
}

function getPerformanceTrend() {
    $data = [];
    $base_time = strtotime('-6 hours');
    
    for ($i = 0; $i < 12; $i++) {
        $time = $base_time + ($i * 1800); // 30 minutes intervals
        $data[] = [
            'time' => date('H:i', $time),
            'response_time' => rand(80, 150),
            'cpu' => rand(40, 80),
            'memory' => rand(30, 70)
        ];
    }
    
    return $data;
}

function getIncidentTimeline() {
    $data = [];
    
    for ($i = 6; $i >= 0; $i--) {
        $date = date('M d', strtotime("-$i days"));
        $total = rand(0, 10);
        $critical = rand(0, min(3, $total));
        
        $data[] = [
            'date' => $date,
            'total' => $total,
            'critical' => $critical,
            'resolved' => max(0, $total - rand(0, 2))
        ];
    }
    
    return $data;
}

function getUserActivityData() {
    $data = [];
    $base_time = strtotime('today');
    
    for ($i = 0; $i < 24; $i++) {
        $hour = str_pad($i, 2, '0', STR_PAD_LEFT) . ':00';
        $users = rand(10, 200);
        
        // Simulate business hours pattern
        if ($i >= 8 && $i <= 18) {
            $users = rand(50, 200);
        } elseif ($i >= 19 && $i <= 22) {
            $users = rand(20, 100);
        } else {
            $users = rand(5, 50);
        }
        
        $data[] = [
            'hour' => $hour,
            'users' => $users
        ];
    }
    
    return $data;
}

function getSystemHealthTimeline() {
    $data = [];
    $base_time = strtotime('-2 hours');
    
    for ($i = 0; $i < 24; $i++) {
        $timestamp = $base_time + ($i * 300); // 5 minute intervals
        
        $data[] = [
            'timestamp' => date('Y-m-d H:i:s', $timestamp),
            'cpu' => rand(40, 80),
            'memory' => rand(30, 70),
            'disk' => rand(20, 40)
        ];
    }
    
    return $data;
}

function generateRandomEmail() {
    $domains = ['sbl.com', 'company.com', 'test.com'];
    $names = ['user', 'test', 'admin', 'operator'];
    
    return $names[array_rand($names)] . rand(100, 999) . '@' . $domains[array_rand($domains)];
}
?>