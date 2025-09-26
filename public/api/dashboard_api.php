<?php
/**
 * API Endpoints para Dashboard de Gestión de Cambios
 * 
 * Endpoints REST para el panel de administración del sistema de gestión de cambios
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../app/Core/auth.php';
require_once __DIR__ . '/../app/Modules/AdminDashboard/ChangeManagementDashboard.php';

// Verificar autenticación y permisos
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

// Verificar permisos de superadministrador
$permissions = new DeveloperSuperadminPermissions();
if (!$permissions->isDeveloperSuperadmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Permisos insuficientes']);
    exit();
}

// Procesar ruta de la API
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = '/api/dashboard';
$route = str_replace($basePath, '', parse_url($requestUri, PHP_URL_PATH));
$method = $_SERVER['REQUEST_METHOD'];

try {
    $dashboard = new ChangeManagementDashboard();
    
    switch ($route) {
        case '/data':
            if ($method === 'GET') {
                $data = $dashboard->getDashboardData();
                echo json_encode($data);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Método no permitido']);
            }
            break;

        case '/backup/manual':
            if ($method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $type = $input['type'] ?? 'hot';
                
                $result = $dashboard->executeManualBackup($type);
                echo json_encode($result);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Método no permitido']);
            }
            break;

        case (preg_match('#^/alerts/([^/]+)/acknowledge$#', $route, $matches) ? true : false):
            if ($method === 'POST') {
                $alertId = $matches[1];
                
                // Conectar con el sistema de alertas de Node.js
                $response = makeHttpRequest('http://localhost:3001/api/alerts/' . $alertId . '/acknowledge', 'POST');
                
                if ($response) {
                    echo json_encode(['success' => true]);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Error procesando solicitud']);
                }
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Método no permitido']);
            }
            break;

        case (preg_match('#^/alerts/([^/]+)/dismiss$#', $route, $matches) ? true : false):
            if ($method === 'POST') {
                $alertId = $matches[1];
                
                // Conectar con el sistema de alertas de Node.js
                $response = makeHttpRequest('http://localhost:3001/api/alerts/' . $alertId . '/dismiss', 'POST');
                
                if ($response) {
                    echo json_encode(['success' => true]);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Error procesando solicitud']);
                }
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Método no permitido']);
            }
            break;

        case '/change-requests':
            if ($method === 'GET') {
                $filters = $_GET;
                $limit = (int)($_GET['limit'] ?? 50);
                $offset = (int)($_GET['offset'] ?? 0);
                
                global $conn;
                $stmt = $conn->prepare("
                    SELECT 
                        cr.*,
                        u1.nombre as requested_by_name,
                        u2.nombre as approved_by_name
                    FROM change_requests cr
                    LEFT JOIN usuarios u1 ON cr.requested_by = u1.id
                    LEFT JOIN usuarios u2 ON cr.approved_by = u2.id
                    ORDER BY cr.created_at DESC
                    LIMIT ? OFFSET ?
                ");
                
                $stmt->bind_param("ii", $limit, $offset);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                echo json_encode($result);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Método no permitido']);
            }
            break;

        case (preg_match('#^/change-requests/([^/]+)/approve$#', $route, $matches) ? true : false):
            if ($method === 'POST') {
                $requestId = (int)$matches[1];
                $input = json_decode(file_get_contents('php://input'), true);
                $approvalNotes = $input['notes'] ?? '';
                
                $result = $dashboard->approveChangeRequest($requestId, $approvalNotes);
                echo json_encode($result);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Método no permitido']);
            }
            break;

        case (preg_match('#^/change-requests/([^/]+)/reject$#', $route, $matches) ? true : false):
            if ($method === 'POST') {
                $requestId = (int)$matches[1];
                $input = json_decode(file_get_contents('php://input'), true);
                $rejectionReason = $input['reason'] ?? '';
                
                $result = $dashboard->rejectChangeRequest($requestId, $rejectionReason);
                echo json_encode($result);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Método no permitido']);
            }
            break;

        case '/reports/generate':
            if ($method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $dateFrom = $input['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
                $dateTo = $input['date_to'] ?? date('Y-m-d');
                $format = $input['format'] ?? 'json';
                
                $reportData = $dashboard->generateSystemReport($dateFrom, $dateTo);
                
                if ($format !== 'json') {
                    $exportPath = $dashboard->exportReport($reportData, $format);
                    echo json_encode([
                        'success' => true,
                        'file_path' => $exportPath,
                        'download_url' => '/api/dashboard/reports/download?file=' . basename($exportPath)
                    ]);
                } else {
                    echo json_encode($reportData);
                }
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Método no permitido']);
            }
            break;

        case '/reports/download':
            if ($method === 'GET') {
                $fileName = $_GET['file'] ?? '';
                $filePath = dirname(__DIR__) . "/storage/reports/{$fileName}";
                
                if (file_exists($filePath)) {
                    $mimeType = mime_content_type($filePath);
                    header('Content-Type: ' . $mimeType);
                    header('Content-Disposition: attachment; filename="' . $fileName . '"');
                    header('Content-Length: ' . filesize($filePath));
                    readfile($filePath);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Archivo no encontrado']);
                }
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Método no permitido']);
            }
            break;

        case '/audit/search':
            if ($method === 'GET') {
                $filters = $_GET;
                $limit = (int)($_GET['limit'] ?? 100);
                $offset = (int)($_GET['offset'] ?? 0);
                
                require_once __DIR__ . '/../app/Modules/AuditSystem/AuditManager.php';
                $auditManager = new AuditManager();
                
                $result = $auditManager->getAuditHistory($filters, $limit, $offset);
                echo json_encode($result);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Método no permitido']);
            }
            break;

        case '/incidents':
            if ($method === 'GET') {
                $filters = $_GET;
                $limit = (int)($_GET['limit'] ?? 50);
                $offset = (int)($_GET['offset'] ?? 0);
                
                require_once __DIR__ . '/../app/Modules/IncidentManagement/IncidentManager.php';
                $incidentManager = new IncidentManager();
                
                $result = $incidentManager->getIncidents($filters, $limit, $offset);
                echo json_encode($result);
            } elseif ($method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                
                require_once __DIR__ . '/../app/Modules/IncidentManagement/IncidentManager.php';
                $incidentManager = new IncidentManager();
                
                $result = $incidentManager->createIncident($input);
                echo json_encode($result);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Método no permitido']);
            }
            break;

        case (preg_match('#^/incidents/([^/]+)/status$#', $route, $matches) ? true : false):
            if ($method === 'PUT') {
                $incidentId = (int)$matches[1];
                $input = json_decode(file_get_contents('php://input'), true);
                $status = $input['status'] ?? '';
                $notes = $input['notes'] ?? '';
                
                require_once __DIR__ . '/../app/Modules/IncidentManagement/IncidentManager.php';
                $incidentManager = new IncidentManager();
                
                $success = $incidentManager->updateIncidentStatus($incidentId, $status, $notes);
                echo json_encode(['success' => $success]);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Método no permitido']);
            }
            break;

        case '/backups':
            if ($method === 'GET') {
                require_once __DIR__ . '/../app/Modules/BackupSystem/BackupManager.php';
                $backupManager = new BackupManager();
                
                $limit = (int)($_GET['limit'] ?? 50);
                $result = $backupManager->getBackupHistory($limit);
                echo json_encode($result);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Método no permitido']);
            }
            break;

        case '/configurations':
            if ($method === 'GET') {
                require_once __DIR__ . '/../app/Modules/ConfigurationControl/ConfigurationVersionControl.php';
                $configControl = new ConfigurationVersionControl();
                
                $configName = $_GET['config'] ?? null;
                $limit = (int)($_GET['limit'] ?? 50);
                
                if ($configName) {
                    $result = $configControl->getConfigurationHistory($configName, $limit);
                } else {
                    // Lista todas las configuraciones
                    $configs = ['database', 'system', 'security', 'email', 'integrations', 'backup', 'monitoring'];
                    $result = [];
                    
                    foreach ($configs as $config) {
                        $result[$config] = $configControl->getCurrentConfiguration($config);
                    }
                }
                
                echo json_encode($result);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Método no permitido']);
            }
            break;

        case (preg_match('#^/configurations/([^/]+)/save$#', $route, $matches) ? true : false):
            if ($method === 'POST') {
                $configName = $matches[1];
                $input = json_decode(file_get_contents('php://input'), true);
                
                require_once __DIR__ . '/../app/Modules/ConfigurationControl/ConfigurationVersionControl.php';
                $configControl = new ConfigurationVersionControl();
                
                $result = $configControl->saveConfiguration(
                    $configName,
                    $input['config_data'],
                    $input['description'] ?? ''
                );
                
                echo json_encode($result);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Método no permitido']);
            }
            break;

        case (preg_match('#^/configurations/([^/]+)/revert$#', $route, $matches) ? true : false):
            if ($method === 'POST') {
                $configName = $matches[1];
                $input = json_decode(file_get_contents('php://input'), true);
                
                require_once __DIR__ . '/../app/Modules/ConfigurationControl/ConfigurationVersionControl.php';
                $configControl = new ConfigurationVersionControl();
                
                $result = $configControl->revertConfiguration(
                    $configName,
                    $input['commit_hash'],
                    $input['reason'] ?? ''
                );
                
                echo json_encode($result);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Método no permitido']);
            }
            break;

        case '/system/health':
            if ($method === 'GET') {
                // Proxy al servidor de alertas de Node.js
                $response = makeHttpRequest('http://localhost:3001/api/system/status', 'GET');
                
                if ($response) {
                    echo json_encode($response);
                } else {
                    // Fallback con datos básicos de PHP
                    $health = [
                        'status' => 'operational',
                        'timestamp' => date('c'),
                        'metrics' => [
                            'php_memory_usage' => memory_get_usage(true),
                            'php_memory_peak' => memory_get_peak_usage(true)
                        ]
                    ];
                    echo json_encode($health);
                }
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Método no permitido']);
            }
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint no encontrado']);
            break;
    }

} catch (Exception $e) {
    error_log("Dashboard API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}

/**
 * Función auxiliar para hacer peticiones HTTP
 */
function makeHttpRequest(string $url, string $method, array $data = []): ?array
{
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5
    ]);

    if ($method !== 'GET' && !empty($data)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300 && $response !== false) {
        return json_decode($response, true);
    }

    return null;
}