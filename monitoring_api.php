<?php

/**
 * API de Monitoreo de Salud del Sistema SBL
 * Proporciona endpoints REST para obtener estado de salud, métricas y alertas
 * 
 * @version 1.0
 * @author Sistema SBL
 * @date 2025-09-26
 */

require_once __DIR__ . '/app/Core/Monitor/HealthMonitor.php';
require_once __DIR__ . '/app/Core/Monitor/AlertManager.php';

class MonitoringAPI
{
    private $healthMonitor;
    private $alertManager;
    private $allowedIPs;

    public function __construct()
    {
        $this->healthMonitor = new HealthMonitor();
        $this->alertManager = new AlertManager();
        $this->allowedIPs = ['127.0.0.1', '::1', 'localhost']; // IPs permitidas para acceso
    }

    /**
     * Manejar petición de API
     */
    public function handleRequest(): void
    {
        // Verificar acceso
        if (!$this->isAccessAllowed()) {
            $this->errorResponse('Acceso no autorizado', 403);
        }

        // Configurar headers CORS
        $this->setCORSHeaders();

        // Obtener endpoint y método
        $endpoint = $_GET['endpoint'] ?? 'status';
        $method = $_SERVER['REQUEST_METHOD'];

        // Routing
        try {
            switch ($endpoint) {
                case 'status':
                    $this->handleStatus($method);
                    break;
                    
                case 'health':
                    $this->handleHealth($method);
                    break;
                    
                case 'metrics':
                    $this->handleMetrics($method);
                    break;
                    
                case 'alerts':
                    $this->handleAlerts($method);
                    break;
                    
                case 'config':
                    $this->handleConfig($method);
                    break;
                    
                default:
                    $this->errorResponse('Endpoint no encontrado', 404);
            }
        } catch (Exception $e) {
            $this->errorResponse('Error interno del servidor: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Endpoint de estado rápido
     */
    private function handleStatus(string $method): void
    {
        if ($method !== 'GET') {
            $this->errorResponse('Método no permitido', 405);
        }

        $status = $this->healthMonitor->getQuickStatus();
        $this->successResponse($status);
    }

    /**
     * Endpoint de salud completa
     */
    private function handleHealth(string $method): void
    {
        if ($method !== 'GET') {
            $this->errorResponse('Método no permitido', 405);
        }

        $includeDetails = $_GET['details'] ?? 'true';
        $includeDetails = filter_var($includeDetails, FILTER_VALIDATE_BOOLEAN);

        if ($includeDetails) {
            $health = $this->healthMonitor->runHealthCheck();
        } else {
            $health = $this->healthMonitor->getQuickStatus();
        }

        // Procesar alertas basadas en datos de salud
        if (isset($health['checks'])) {
            $this->alertManager->processHealthAlerts($health);
        }

        $this->successResponse($health);
    }

    /**
     * Endpoint de métricas
     */
    private function handleMetrics(string $method): void
    {
        if ($method !== 'GET') {
            $this->errorResponse('Método no permitido', 405);
        }

        $metricsType = $_GET['type'] ?? 'all';
        
        $metrics = [
            'timestamp' => date('Y-m-d H:i:s'),
            'system' => [
                'php_version' => PHP_VERSION,
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
                'memory_limit' => ini_get('memory_limit'),
                'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
                'uptime' => time() - $_SERVER['REQUEST_TIME']
            ]
        ];

        switch ($metricsType) {
            case 'system':
                $response = ['system' => $metrics['system']];
                break;
                
            case 'database':
                $healthData = $this->healthMonitor->runHealthCheck();
                $response = [
                    'database' => $healthData['checks']['database'] ?? null
                ];
                break;
                
            case 'performance':
                $healthData = $this->healthMonitor->runHealthCheck();
                $response = [
                    'performance' => $healthData['checks']['performance'] ?? null
                ];
                break;
                
            case 'all':
            default:
                $healthData = $this->healthMonitor->runHealthCheck();
                $response = array_merge($metrics, [
                    'health_summary' => [
                        'overall_status' => $healthData['overall_status'],
                        'database_status' => $healthData['checks']['database']['status'] ?? 'unknown',
                        'apis_status' => $healthData['checks']['apis']['status'] ?? 'unknown',
                        'filesystem_status' => $healthData['checks']['filesystem']['status'] ?? 'unknown'
                    ]
                ]);
                break;
        }

        $this->successResponse($response);
    }

    /**
     * Endpoint de alertas
     */
    private function handleAlerts(string $method): void
    {
        switch ($method) {
            case 'GET':
                $this->getAlerts();
                break;
                
            case 'POST':
                $this->createAlert();
                break;
                
            case 'PUT':
                $this->acknowledgeAlert();
                break;
                
            case 'DELETE':
                $this->deleteAlert();
                break;
                
            default:
                $this->errorResponse('Método no permitido', 405);
        }
    }

    /**
     * Obtener alertas
     */
    private function getAlerts(): void
    {
        $action = $_GET['action'] ?? 'list';
        
        switch ($action) {
            case 'list':
                $alerts = $this->alertManager->getActiveAlerts();
                $this->successResponse([
                    'alerts' => $alerts,
                    'count' => count($alerts)
                ]);
                break;
                
            case 'stats':
                $stats = $this->alertManager->getAlertStats();
                $this->successResponse($stats);
                break;
                
            default:
                $this->errorResponse('Acción no válida', 400);
        }
    }

    /**
     * Crear alerta manual
     */
    private function createAlert(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['type'], $input['title'], $input['message'])) {
            $this->errorResponse('Datos requeridos faltantes: type, title, message', 400);
        }

        $type = $input['type'];
        $title = $input['title'];
        $message = $input['message'];
        $context = $input['context'] ?? [];
        $severity = $input['severity'] ?? 'warning';

        $result = $this->alertManager->processAlert($type, $title, $message, $context, $severity);

        if ($result) {
            $this->successResponse(['message' => 'Alerta creada exitosamente']);
        } else {
            $this->errorResponse('Error creando alerta', 500);
        }
    }

    /**
     * Reconocer alerta
     */
    private function acknowledgeAlert(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['alert_id'])) {
            $this->errorResponse('ID de alerta requerido', 400);
        }

        $alertId = $input['alert_id'];
        $acknowledgedBy = $input['acknowledged_by'] ?? 'api_user';

        $result = $this->alertManager->acknowledgeAlert($alertId, $acknowledgedBy);

        if ($result) {
            $this->successResponse(['message' => 'Alerta reconocida exitosamente']);
        } else {
            $this->errorResponse('Alerta no encontrada', 404);
        }
    }

    /**
     * Limpiar alertas antiguas
     */
    private function deleteAlert(): void
    {
        $action = $_GET['action'] ?? 'cleanup';
        
        if ($action === 'cleanup') {
            $maxAge = (int)($_GET['max_age_days'] ?? 7);
            $removed = $this->alertManager->cleanupOldAlerts($maxAge);
            
            $this->successResponse([
                'message' => 'Limpieza completada',
                'removed_count' => $removed
            ]);
        } else {
            $this->errorResponse('Acción no válida', 400);
        }
    }

    /**
     * Endpoint de configuración
     */
    private function handleConfig(string $method): void
    {
        if ($method !== 'GET') {
            $this->errorResponse('Método no permitido', 405);
        }

        $config = [
            'monitoring' => [
                'health_check_interval' => 30, // segundos
                'alert_cooldown' => [
                    'critical' => 300,
                    'warning' => 900,
                    'info' => 1800
                ],
                'thresholds' => [
                    'memory_warning' => 80,
                    'memory_critical' => 90,
                    'disk_warning' => 85,
                    'disk_critical' => 95,
                    'response_time_warning' => 2000,
                    'response_time_critical' => 5000
                ]
            ],
            'api' => [
                'version' => '1.0',
                'endpoints' => [
                    'status' => 'GET /monitoring_api.php?endpoint=status',
                    'health' => 'GET /monitoring_api.php?endpoint=health[&details=true|false]',
                    'metrics' => 'GET /monitoring_api.php?endpoint=metrics[&type=all|system|database|performance]',
                    'alerts' => 'GET|POST|PUT|DELETE /monitoring_api.php?endpoint=alerts',
                    'config' => 'GET /monitoring_api.php?endpoint=config'
                ]
            ],
            'system_info' => [
                'php_version' => PHP_VERSION,
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
                'current_time' => date('Y-m-d H:i:s')
            ]
        ];

        $this->successResponse($config);
    }

    /**
     * Verificar si el acceso está permitido
     */
    private function isAccessAllowed(): bool
    {
        $clientIP = $this->getClientIP();
        
        // Permitir acceso desde localhost/127.0.0.1
        if (in_array($clientIP, $this->allowedIPs)) {
            return true;
        }
        
        // En producción, aquí se pueden agregar más validaciones
        // como API keys, tokens JWT, etc.
        
        return false;
    }

    /**
     * Obtener IP del cliente
     */
    private function getClientIP(): string
    {
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                return trim($ips[0]);
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Configurar headers CORS
     */
    private function setCORSHeaders(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Content-Type: application/json; charset=utf-8');
        
        // Manejar preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }

    /**
     * Respuesta exitosa
     */
    private function successResponse($data, int $code = 200): void
    {
        http_response_code($code);
        echo json_encode([
            'success' => true,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s'),
            'execution_time' => round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2) . 'ms'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Respuesta de error
     */
    private function errorResponse(string $message, int $code = 400): void
    {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message,
            'timestamp' => date('Y-m-d H:i:s'),
            'code' => $code
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Ejecutar API si se accede directamente
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $api = new MonitoringAPI();
    $api->handleRequest();
}
?>