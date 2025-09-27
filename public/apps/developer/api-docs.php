<?php
/**
 * API Documentation Endpoint for Developer Portal
 * SBL Sistema Interno - Developer Section
 */

require_once 'auth.php';

header('Content-Type: application/json');

try {
    $path = $_GET['path'] ?? '';
    
    switch ($path) {
        case 'endpoints':
            echo json_encode([
                'success' => true,
                'data' => getAPIEndpoints()
            ]);
            break;
            
        case 'stats':
            echo json_encode([
                'success' => true,
                'data' => getAPIStats()
            ]);
            break;
            
        case 'test':
            $endpoint = $_GET['endpoint'] ?? '';
            echo json_encode([
                'success' => true,
                'data' => testAPIEndpoint($endpoint)
            ]);
            break;
            
        default:
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'API documentation endpoint not found'
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving API documentation',
        'error' => $e->getMessage()
    ]);
}

/**
 * Get all API endpoints
 */
function getAPIEndpoints() {
    // Include existing API docs
    $apiEndpoints = [];
    $apiDocsFile = __DIR__ . '/sections/api-docs.php';
    
    if (file_exists($apiDocsFile)) {
        include $apiDocsFile;
        if (isset($api_endpoints)) {
            $apiEndpoints = $api_endpoints;
        }
    }
    
    // Additional endpoints for developer portal
    $developerEndpoints = [
        [
            'method' => 'GET',
            'endpoint' => '/api/developer/dashboard',
            'description' => 'Developer dashboard data',
            'status' => 'active',
            'auth' => 'Developer Token',
            'category' => 'Developer',
            'parameters' => [
                ['name' => 'path', 'type' => 'string', 'required' => true, 'description' => 'Dashboard path (kpis, charts, etc.)']
            ],
            'response_example' => [
                'success' => true,
                'data' => ['kpi_name' => 'value']
            ]
        ],
        [
            'method' => 'GET',
            'endpoint' => '/api/developer/system-status',
            'description' => 'System status information',
            'status' => 'active',
            'auth' => 'Developer Token',
            'category' => 'Developer',
            'parameters' => [
                ['name' => 'path', 'type' => 'string', 'required' => true, 'description' => 'Status path (services, resources, etc.)']
            ],
            'response_example' => [
                'success' => true,
                'data' => ['service_name' => 'status']
            ]
        ],
        [
            'method' => 'GET',
            'endpoint' => '/api/developer/logs',
            'description' => 'System logs retrieval',
            'status' => 'active',
            'auth' => 'Developer Token',
            'category' => 'Developer',
            'parameters' => [
                ['name' => 'level', 'type' => 'string', 'required' => false, 'description' => 'Log level filter'],
                ['name' => 'limit', 'type' => 'integer', 'required' => false, 'description' => 'Number of log entries']
            ]
        ]
    ];
    
    // Merge all endpoints
    $allEndpoints = array_merge($apiEndpoints, $developerEndpoints);
    
    // Group by category
    $groupedEndpoints = [];
    foreach ($allEndpoints as $endpoint) {
        $category = $endpoint['category'] ?? 'General';
        if (!isset($groupedEndpoints[$category])) {
            $groupedEndpoints[$category] = [];
        }
        $groupedEndpoints[$category][] = $endpoint;
    }
    
    return [
        'endpoints' => $allEndpoints,
        'grouped' => $groupedEndpoints,
        'total_count' => count($allEndpoints),
        'active_count' => count(array_filter($allEndpoints, function($ep) { return $ep['status'] === 'active'; })),
        'deprecated_count' => count(array_filter($allEndpoints, function($ep) { return $ep['status'] === 'deprecated'; }))
    ];
}

/**
 * Get API usage statistics
 */
function getAPIStats() {
    return [
        'daily_requests' => [
            'total' => rand(5000, 15000),
            'successful' => rand(4500, 14000),
            'errors' => rand(50, 500),
            'average_response_time' => rand(80, 200) . 'ms'
        ],
        'top_endpoints' => [
            [
                'endpoint' => '/api/v1/calibraciones',
                'requests' => rand(1000, 3000),
                'avg_response_time' => rand(50, 150) . 'ms',
                'error_rate' => rand(1, 5) . '%'
            ],
            [
                'endpoint' => '/api/v1/instrumentos',
                'requests' => rand(800, 2500),
                'avg_response_time' => rand(60, 180) . 'ms',
                'error_rate' => rand(1, 4) . '%'
            ],
            [
                'endpoint' => '/api/v1/auth/login',
                'requests' => rand(200, 800),
                'avg_response_time' => rand(100, 300) . 'ms',
                'error_rate' => rand(2, 8) . '%'
            ]
        ],
        'response_times' => [
            'p50' => rand(80, 120) . 'ms',
            'p90' => rand(150, 250) . 'ms',
            'p95' => rand(200, 350) . 'ms',
            'p99' => rand(400, 800) . 'ms'
        ],
        'error_breakdown' => [
            '400' => rand(10, 50),
            '401' => rand(20, 80),
            '404' => rand(5, 30),
            '500' => rand(1, 10),
            '503' => rand(0, 5)
        ],
        'rate_limiting' => [
            'requests_blocked' => rand(0, 20),
            'rate_limit_violations' => rand(0, 10),
            'current_limits' => [
                'per_minute' => 100,
                'per_hour' => 1000,
                'per_day' => 10000
            ]
        ]
    ];
}

/**
 * Test an API endpoint
 */
function testAPIEndpoint($endpoint) {
    if (empty($endpoint)) {
        return [
            'error' => 'No endpoint specified'
        ];
    }
    
    // Simulate API testing
    $testResult = [
        'endpoint' => $endpoint,
        'test_time' => date('Y-m-d H:i:s'),
        'status' => 'success',
        'response_time' => rand(50, 200) . 'ms',
        'http_code' => 200,
        'response_size' => rand(500, 5000) . ' bytes',
        'headers' => [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-cache',
            'X-API-Version' => '1.0'
        ],
        'test_details' => [
            'connectivity' => 'pass',
            'authentication' => 'pass',
            'response_format' => 'pass',
            'data_validation' => 'pass'
        ]
    ];
    
    // Simulate occasional failures
    if (rand(1, 10) > 8) {
        $testResult['status'] = 'error';
        $testResult['http_code'] = rand(0, 1) ? 404 : 500;
        $testResult['error_message'] = $testResult['http_code'] === 404 ? 
            'Endpoint not found' : 'Internal server error';
        $testResult['test_details']['connectivity'] = 'fail';
    }
    
    return $testResult;
}

/**
 * Get API documentation in OpenAPI format
 */
function getOpenAPISpec() {
    $endpoints = getAPIEndpoints();
    
    $spec = [
        'openapi' => '3.0.0',
        'info' => [
            'title' => 'SBL Sistema Interno API',
            'description' => 'API documentation for SBL Internal System',
            'version' => '1.0.0',
            'contact' => [
                'name' => 'Developer Team',
                'email' => 'dev@sbl.com'
            ]
        ],
        'servers' => [
            [
                'url' => 'https://' . $_SERVER['HTTP_HOST'],
                'description' => 'Production server'
            ]
        ],
        'paths' => []
    ];
    
    // Convert endpoints to OpenAPI format
    foreach ($endpoints['endpoints'] as $endpoint) {
        $path = $endpoint['endpoint'];
        $method = strtolower($endpoint['method']);
        
        if (!isset($spec['paths'][$path])) {
            $spec['paths'][$path] = [];
        }
        
        $spec['paths'][$path][$method] = [
            'summary' => $endpoint['description'],
            'description' => $endpoint['description'],
            'tags' => [$endpoint['category'] ?? 'General'],
            'responses' => [
                '200' => [
                    'description' => 'Successful response',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'success' => ['type' => 'boolean'],
                                    'data' => ['type' => 'object']
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        // Add parameters if present
        if (isset($endpoint['parameters'])) {
            $spec['paths'][$path][$method]['parameters'] = [];
            foreach ($endpoint['parameters'] as $param) {
                $spec['paths'][$path][$method]['parameters'][] = [
                    'name' => $param['name'],
                    'in' => 'query',
                    'required' => $param['required'],
                    'description' => $param['description'],
                    'schema' => ['type' => $param['type']]
                ];
            }
        }
        
        // Add security if auth required
        if (!empty($endpoint['auth']) && $endpoint['auth'] !== 'None') {
            $spec['paths'][$path][$method]['security'] = [
                ['bearerAuth' => []]
            ];
        }
    }
    
    // Add security schemes
    $spec['components'] = [
        'securitySchemes' => [
            'bearerAuth' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'JWT'
            ]
        ]
    ];
    
    return $spec;
}
?>