<?php
/**
 * Dashboard para Developer con permisos de Superadministrador
 * 
 * Este dashboard proporciona una interfaz completa de administración y monitoreo
 * para usuarios con rol de developer que tienen privilegios de superadministrador.
 */

require_once dirname(__DIR__, 2) . '/Core/permissions.php';
require_once dirname(__DIR__, 2) . '/Core/developer_permissions.php';
require_once dirname(__DIR__, 2) . '/Core/db.php';
require_once __DIR__ . '/../TicketSystem/TicketManager.php';
require_once __DIR__ . '/../TicketSystem/ReportingSystem.php';

// Verificar autenticación y permisos
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

$devPerms = new DeveloperSuperadminPermissions();
if (!$devPerms->isDeveloperSuperadmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado. Se requieren permisos de developer.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'dashboard';

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'dashboard':
            if ($method === 'GET') {
                $stats = $devPerms->getSystemStats();
                $recentActivity = $devPerms->getRecentActivity(20);
                $systemConfig = $devPerms->getSystemConfiguration();
                
                // Integrar métricas del sistema de tickets
                $ticketManager = new TicketManager();
                $ticketStats = $ticketManager->getTicketStats();
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'stats' => $stats,
                        'recent_activity' => $recentActivity,
                        'ticket_system' => [
                            'total_tickets' => array_sum($ticketStats['by_status']),
                            'critical_tickets' => $ticketStats['by_risk']['Crítico'] ?? 0,
                            'high_risk_tickets' => $ticketStats['by_risk']['Alto'] ?? 0,
                            'open_tickets' => $ticketStats['by_status']['Abierto'] ?? 0,
                            'escalated_tickets' => $ticketStats['by_status']['Escalado'] ?? 0,
                            'sla_compliance' => $ticketStats['time_metrics']['compliance_rate'] ?? 0
                        ],
                        'system_status' => [
                            'database_connection' => check_database_connection(),
                            'disk_space' => get_disk_usage(),
                            'memory_usage' => get_memory_usage(),
                            'php_version' => PHP_VERSION,
                            'mysql_version' => get_mysql_version()
                        ],
                        'system_config' => $systemConfig
                    ],
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            }
            break;

        case 'users':
            if ($method === 'GET') {
                echo json_encode(getAllUsers());
            } elseif ($method === 'POST') {
                $userData = json_decode(file_get_contents('php://input'), true);
                echo json_encode(createUser($userData));
            } elseif ($method === 'PUT') {
                $userData = json_decode(file_get_contents('php://input'), true);
                echo json_encode(updateUser($userData));
            } elseif ($method === 'DELETE') {
                $userId = $_GET['id'] ?? null;
                echo json_encode(deleteUser($userId));
            }
            break;

        case 'system_config':
            if ($method === 'GET') {
                echo json_encode([
                    'success' => true,
                    'data' => $devPerms->getSystemConfiguration()
                ]);
            } elseif ($method === 'POST') {
                $config = json_decode(file_get_contents('php://input'), true);
                $result = $devPerms->updateSystemConfiguration($config);
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Configuración actualizada correctamente' : 'Error al actualizar configuración'
                ]);
            }
            break;

        case 'alerts':
            if ($method === 'GET') {
                echo json_encode(getSystemAlerts());
            } elseif ($method === 'POST') {
                $alertsConfig = json_decode(file_get_contents('php://input'), true);
                $result = $devPerms->configureSystemAlerts($alertsConfig);
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Alertas configuradas correctamente' : 'Error al configurar alertas'
                ]);
            }
            break;

        case 'audit_logs':
            if ($method === 'GET') {
                $limit = (int)($_GET['limit'] ?? 100);
                $logs = $devPerms->getRecentActivity($limit);
                echo json_encode([
                    'success' => true,
                    'data' => $logs
                ]);
            }
            break;

        case 'incidents':
            if ($method === 'GET') {
                echo json_encode(getIncidents());
            } elseif ($method === 'POST') {
                $incidentData = json_decode(file_get_contents('php://input'), true);
                echo json_encode(createIncident($incidentData));
            } elseif ($method === 'PUT') {
                $incidentData = json_decode(file_get_contents('php://input'), true);
                echo json_encode(updateIncident($incidentData));
            }
            break;

        case 'backup':
            if ($method === 'POST') {
                echo json_encode(createSystemBackup());
            }
            break;

        case 'monitoring':
            if ($method === 'GET') {
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'system_health' => getSystemHealthMetrics(),
                        'performance_metrics' => getPerformanceMetrics(),
                        'active_sessions' => getActiveSessions(),
                        'database_metrics' => getDatabaseMetrics()
                    ]
                ]);
            }
            break;

        case 'ticket_system_dashboard':
            if ($method === 'GET') {
                $ticketManager = new TicketManager();
                $reportingSystem = new TicketReportingSystem();
                
                $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
                $dateTo = $_GET['date_to'] ?? date('Y-m-t');
                
                $dashboard = [
                    'stats' => $ticketManager->getTicketStats(),
                    'executive_report' => $reportingSystem->generateExecutiveReport($dateFrom, $dateTo),
                    'recent_critical_tickets' => $ticketManager->getTickets([
                        'risk_level' => 'Crítico',
                        'date_from' => date('Y-m-d', strtotime('-7 days'))
                    ], 1, 10)
                ];
                
                echo json_encode([
                    'success' => true,
                    'data' => $dashboard
                ]);
            }
            break;

        case 'ticket_reports':
            if ($method === 'GET') {
                $reportingSystem = new TicketReportingSystem();
                $reportType = $_GET['type'] ?? 'executive';
                $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
                $dateTo = $_GET['date_to'] ?? date('Y-m-t');
                
                switch ($reportType) {
                    case 'executive':
                        $report = $reportingSystem->generateExecutiveReport($dateFrom, $dateTo);
                        break;
                    case 'audit':
                        $includeDetails = $_GET['include_details'] === 'true';
                        $report = $reportingSystem->generateAuditReport($dateFrom, $dateTo, $includeDetails);
                        break;
                    default:
                        $report = ['error' => 'Tipo de reporte no válido'];
                }
                
                echo json_encode([
                    'success' => !isset($report['error']),
                    'data' => $report
                ]);
            } elseif ($method === 'POST') {
                // Exportar reporte
                $reportingSystem = new TicketReportingSystem();
                $requestData = json_decode(file_get_contents('php://input'), true);
                
                $format = $requestData['format'] ?? 'html';
                $reportType = $requestData['type'] ?? 'executive';
                $reportData = $requestData['data'] ?? [];
                
                if ($format === 'pdf') {
                    $output = $reportingSystem->exportToPDF($reportData, $reportType);
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: attachment; filename="reporte-tickets-' . date('Y-m-d') . '.pdf"');
                } else {
                    $output = $reportingSystem->generateHTMLReport($reportData, $reportType);
                    header('Content-Type: text/html');
                }
                
                echo $output;
                exit;
            }
            break;

        case 'ticket_system_health':
            if ($method === 'GET') {
                $ticketManager = new TicketManager();
                
                // Verificar salud del sistema de tickets
                $healthChecks = [
                    'database_tables' => checkTicketSystemTables(),
                    'notification_system' => checkNotificationSystem(),
                    'sla_monitoring' => checkSLAMonitoring(),
                    'recent_activity' => checkRecentTicketActivity(),
                    'compliance_status' => checkGxPCompliance()
                ];
                
                $overallHealth = array_reduce($healthChecks, function($carry, $check) {
                    return $carry && $check['status'] === 'ok';
                }, true);
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'overall_health' => $overallHealth ? 'ok' : 'warning',
                        'checks' => $healthChecks,
                        'last_check' => date('Y-m-d H:i:s')
                    ]
                ]);
            }
            break;

        case 'critical_alerts':
            if ($method === 'GET') {
                $ticketManager = new TicketManager();
                
                // Obtener alertas críticas del sistema de tickets
                $criticalTickets = $ticketManager->getTickets([
                    'risk_level' => 'Crítico',
                    'status' => 'Abierto'
                ], 1, 20);
                
                $escalatedTickets = $ticketManager->getTickets([
                    'status' => 'Escalado'
                ], 1, 20);
                
                $overdueTickets = getOverdueTickets();
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'critical_open' => $criticalTickets,
                        'escalated' => $escalatedTickets,
                        'overdue' => $overdueTickets,
                        'alert_count' => count($criticalTickets) + count($escalatedTickets) + count($overdueTickets)
                    ]
                ]);
            }
            break;

        case 'initialize_ticket_system':
            if ($method === 'POST') {
                try {
                    // Inicializar sistema de tickets
                    $ticketManager = new TicketManager();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Sistema de tickets inicializado correctamente',
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Error inicializando sistema de tickets: ' . $e->getMessage()
                    ]);
                }
            }
            break;

        default:
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Acción no encontrada'
            ]);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor',
        'message' => $e->getMessage()
    ]);
    
    // Log del error
    $devPerms->logDeveloperAction('ERROR', 'Dashboard error: ' . $e->getMessage(), [
        'action' => $action,
        'method' => $method,
        'trace' => $e->getTraceAsString()
    ]);
}

// Funciones auxiliares

function check_database_connection(): bool
{
    global $conn;
    return $conn && $conn->ping();
}

function get_disk_usage(): array
{
    $bytes = disk_free_space(".");
    $total = disk_total_space(".");
    return [
        'free' => $bytes,
        'total' => $total,
        'used' => $total - $bytes,
        'percentage' => round((($total - $bytes) / $total) * 100, 2)
    ];
}

function get_memory_usage(): array
{
    return [
        'current' => memory_get_usage(true),
        'peak' => memory_get_peak_usage(true),
        'limit' => ini_get('memory_limit')
    ];
}

function get_mysql_version(): string
{
    global $conn;
    if ($conn) {
        return $conn->server_info;
    }
    return 'Unknown';
}

function getAllUsers(): array
{
    global $conn;
    
    $query = "
        SELECT u.id, u.usuario, u.nombre, u.apellidos, u.correo, u.telefono,
               r.nombre as role_name, e.nombre as empresa_nombre,
               u.fecha_creacion, u.ultima_actividad
        FROM usuarios u
        LEFT JOIN roles r ON u.role_id = r.id
        LEFT JOIN empresas e ON u.empresa_id = e.id
        ORDER BY u.fecha_creacion DESC
    ";
    
    $result = $conn->query($query);
    $users = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
    
    return [
        'success' => true,
        'data' => $users
    ];
}

function createUser(array $userData): array
{
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO usuarios (usuario, nombre, apellidos, correo, telefono, contrasena, role_id, empresa_id, portal_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $hashedPassword = password_hash($userData['password'], PASSWORD_BCRYPT);
        
        $stmt->bind_param('ssssssiii',
            $userData['usuario'],
            $userData['nombre'],
            $userData['apellidos'],
            $userData['correo'],
            $userData['telefono'],
            $hashedPassword,
            $userData['role_id'],
            $userData['empresa_id'],
            $userData['portal_id']
        );
        
        $success = $stmt->execute();
        $stmt->close();
        
        if ($success) {
            log_developer_action('USER_CREATED', 'Usuario creado: ' . $userData['usuario'], $userData);
        }
        
        return [
            'success' => $success,
            'message' => $success ? 'Usuario creado correctamente' : 'Error al crear usuario'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

function updateUser(array $userData): array
{
    global $conn;
    
    try {
        $sql = "
            UPDATE usuarios 
            SET nombre = ?, apellidos = ?, correo = ?, telefono = ?, role_id = ?, empresa_id = ?
        ";
        
        $params = [$userData['nombre'], $userData['apellidos'], $userData['correo'], 
                  $userData['telefono'], $userData['role_id'], $userData['empresa_id']];
        $types = 'ssssii';
        
        if (!empty($userData['password'])) {
            $sql .= ", contrasena = ?";
            $params[] = password_hash($userData['password'], PASSWORD_BCRYPT);
            $types .= 's';
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $userData['id'];
        $types .= 'i';
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        $success = $stmt->execute();
        $stmt->close();
        
        if ($success) {
            log_developer_action('USER_UPDATED', 'Usuario actualizado: ID ' . $userData['id'], $userData);
        }
        
        return [
            'success' => $success,
            'message' => $success ? 'Usuario actualizado correctamente' : 'Error al actualizar usuario'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

function deleteUser(?string $userId): array
{
    if (!$userId) {
        return [
            'success' => false,
            'message' => 'ID de usuario requerido'
        ];
    }
    
    global $conn;
    
    try {
        $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->bind_param('i', $userId);
        
        $success = $stmt->execute();
        $stmt->close();
        
        if ($success) {
            log_developer_action('USER_DELETED', 'Usuario eliminado: ID ' . $userId);
        }
        
        return [
            'success' => $success,
            'message' => $success ? 'Usuario eliminado correctamente' : 'Error al eliminar usuario'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

function getSystemAlerts(): array
{
    global $conn;
    
    $query = "
        SELECT * FROM alertas_configuracion 
        ORDER BY nivel_criticidad DESC, created_at DESC
    ";
    
    $result = $conn->query($query);
    $alerts = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $alerts[] = $row;
        }
    }
    
    return [
        'success' => true,
        'data' => $alerts
    ];
}

function getIncidents(): array
{
    global $conn;
    
    $query = "
        SELECT i.*, u.nombre as reported_by_name
        FROM incidentes i
        LEFT JOIN usuarios u ON i.reported_by = u.id
        ORDER BY i.created_at DESC
        LIMIT 100
    ";
    
    $result = $conn->query($query);
    $incidents = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $incidents[] = $row;
        }
    }
    
    return [
        'success' => true,
        'data' => $incidents
    ];
}

function createIncident(array $incidentData): array
{
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO incidentes (titulo, descripcion, tipo, prioridad, estado, reported_by, created_at)
            VALUES (?, ?, ?, ?, 'abierto', ?, NOW())
        ");
        
        $userId = $_SESSION['usuario_id'] ?? null;
        
        $stmt->bind_param('ssssi',
            $incidentData['titulo'],
            $incidentData['descripcion'],
            $incidentData['tipo'],
            $incidentData['prioridad'],
            $userId
        );
        
        $success = $stmt->execute();
        $stmt->close();
        
        if ($success) {
            log_developer_action('INCIDENT_CREATED', 'Incidente creado: ' . $incidentData['titulo'], $incidentData);
        }
        
        return [
            'success' => $success,
            'message' => $success ? 'Incidente creado correctamente' : 'Error al crear incidente'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

function updateIncident(array $incidentData): array
{
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            UPDATE incidentes 
            SET titulo = ?, descripcion = ?, tipo = ?, prioridad = ?, estado = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->bind_param('sssssi',
            $incidentData['titulo'],
            $incidentData['descripcion'],
            $incidentData['tipo'],
            $incidentData['prioridad'],
            $incidentData['estado'],
            $incidentData['id']
        );
        
        $success = $stmt->execute();
        $stmt->close();
        
        if ($success) {
            log_developer_action('INCIDENT_UPDATED', 'Incidente actualizado: ID ' . $incidentData['id'], $incidentData);
        }
        
        return [
            'success' => $success,
            'message' => $success ? 'Incidente actualizado correctamente' : 'Error al actualizar incidente'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

function createSystemBackup(): array
{
    try {
        $backupDir = dirname(__DIR__, 3) . '/storage/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = $backupDir . "/backup_developer_{$timestamp}.sql";
        
        // Crear backup de la base de datos
        global $conn;
        $dbName = 'sbl_sistema_interno';
        
        $command = "mysqldump --host=localhost --user=root --password= {$dbName} > {$backupFile}";
        exec($command, $output, $returnVar);
        
        $success = $returnVar === 0 && file_exists($backupFile);
        
        if ($success) {
            log_developer_action('BACKUP_CREATED', 'Backup del sistema creado: ' . basename($backupFile));
        }
        
        return [
            'success' => $success,
            'message' => $success ? 'Backup creado correctamente' : 'Error al crear backup',
            'file' => $success ? basename($backupFile) : null
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

function getSystemHealthMetrics(): array
{
    global $conn;
    
    $metrics = [];
    
    try {
        // Verificar conexiones de base de datos
        $result = $conn->query("SHOW STATUS LIKE 'Threads_connected'");
        if ($result && $row = $result->fetch_assoc()) {
            $metrics['db_connections'] = (int)$row['Value'];
        }
        
        // Verificar procesos lentos
        $result = $conn->query("SHOW STATUS LIKE 'Slow_queries'");
        if ($result && $row = $result->fetch_assoc()) {
            $metrics['slow_queries'] = (int)$row['Value'];
        }
        
        // Tamaño de la base de datos
        $result = $conn->query("
            SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024) as db_size_mb
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
        ");
        if ($result && $row = $result->fetch_assoc()) {
            $metrics['database_size_mb'] = (int)$row['db_size_mb'];
        }
        
    } catch (Exception $e) {
        error_log("Error getting system health metrics: " . $e->getMessage());
    }
    
    return $metrics;
}

function getPerformanceMetrics(): array
{
    return [
        'php_memory_usage' => memory_get_usage(true),
        'php_memory_peak' => memory_get_peak_usage(true),
        'server_load' => sys_getloadavg(),
        'uptime' => uptime()
    ];
}

function getActiveSessions(): array
{
    // Implementar lógica para obtener sesiones activas
    return [
        'total_sessions' => session_count(),
        'developer_sessions' => developer_session_count()
    ];
}

function getDatabaseMetrics(): array
{
    global $conn;
    
    $metrics = [];
    
    try {
        $result = $conn->query("SHOW STATUS");
        while ($row = $result->fetch_assoc()) {
            if (in_array($row['Variable_name'], [
                'Queries', 'Questions', 'Connections', 'Uptime'
            ])) {
                $metrics[strtolower($row['Variable_name'])] = $row['Value'];
            }
        }
    } catch (Exception $e) {
        error_log("Error getting database metrics: " . $e->getMessage());
    }
    
    return $metrics;
}

function uptime(): string
{
    return shell_exec('uptime') ?: 'N/A';
}

function session_count(): int
{
    // Implementar conteo de sesiones activas
    return 0; // Placeholder
}

function developer_session_count(): int
{
    // Implementar conteo de sesiones de desarrolladores
    return 0; // Placeholder
}

// Funciones de verificación de salud del sistema de tickets

function checkTicketSystemTables() {
    global $conn;
    
    $requiredTables = ['tickets', 'ticket_history', 'ticket_attachments', 'risk_assessment_criteria', 
                      'notification_settings', 'notification_log', 'sla_monitoring'];
    $missingTables = [];
    
    foreach ($requiredTables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows === 0) {
            $missingTables[] = $table;
        }
    }
    
    return [
        'status' => empty($missingTables) ? 'ok' : 'error',
        'message' => empty($missingTables) ? 'Todas las tablas están presentes' : 'Tablas faltantes: ' . implode(', ', $missingTables),
        'missing_tables' => $missingTables
    ];
}

function checkNotificationSystem() {
    $logFile = dirname(__DIR__, 3) . '/storage/logs/notifications.log';
    $lastHour = date('Y-m-d H:i:s', strtotime('-1 hour'));
    
    $status = 'ok';
    $message = 'Sistema de notificaciones operativo';
    
    if (!file_exists($logFile)) {
        $status = 'warning';
        $message = 'Archivo de log de notificaciones no encontrado';
    } elseif (!is_writable($logFile)) {
        $status = 'error';
        $message = 'Archivo de log de notificaciones no es escribible';
    }
    
    return [
        'status' => $status,
        'message' => $message,
        'log_file' => $logFile
    ];
}

function checkSLAMonitoring() {
    global $conn;
    
    try {
        $result = $conn->query("SELECT COUNT(*) as count FROM sla_monitoring WHERE next_check_at < NOW()");
        $overdueChecks = $result->fetch_assoc()['count'];
        
        $status = $overdueChecks > 0 ? 'warning' : 'ok';
        $message = $overdueChecks > 0 ? "$overdueChecks verificaciones SLA pendientes" : 'Monitoreo SLA actualizado';
        
        return [
            'status' => $status,
            'message' => $message,
            'overdue_checks' => $overdueChecks
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Error verificando monitoreo SLA: ' . $e->getMessage()
        ];
    }
}

function checkRecentTicketActivity() {
    global $conn;
    
    try {
        $result = $conn->query("SELECT COUNT(*) as count FROM tickets WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $recentTickets = $result->fetch_assoc()['count'];
        
        return [
            'status' => 'ok',
            'message' => "$recentTickets tickets creados en las últimas 24 horas",
            'recent_count' => $recentTickets
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Error verificando actividad reciente: ' . $e->getMessage()
        ];
    }
}

function checkGxPCompliance() {
    global $conn;
    
    try {
        // Verificar tickets resueltos sin documentación adecuada
        $result = $conn->query("
            SELECT COUNT(*) as count 
            FROM tickets 
            WHERE status IN ('Resuelto', 'Cerrado') 
            AND (regulatory_impact = TRUE OR validation_impact = TRUE)
            AND (root_cause IS NULL OR root_cause = '' OR corrective_actions IS NULL OR corrective_actions = '')
        ");
        $nonCompliantTickets = $result->fetch_assoc()['count'];
        
        $status = $nonCompliantTickets > 0 ? 'warning' : 'ok';
        $message = $nonCompliantTickets > 0 ? 
            "$nonCompliantTickets tickets GxP sin documentación completa" : 
            'Cumplimiento GxP satisfactorio';
        
        return [
            'status' => $status,
            'message' => $message,
            'non_compliant_count' => $nonCompliantTickets
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Error verificando cumplimiento GxP: ' . $e->getMessage()
        ];
    }
}

function getOverdueTickets() {
    global $conn;
    
    try {
        $result = $conn->query("
            SELECT t.*, u1.nombre as created_by_name, u2.nombre as assigned_to_name,
                   sm.sla_hours,
                   TIMESTAMPDIFF(HOUR, t.created_at, NOW()) as elapsed_hours
            FROM tickets t
            LEFT JOIN usuarios u1 ON t.created_by = u1.id
            LEFT JOIN usuarios u2 ON t.assigned_to = u2.id
            LEFT JOIN sla_monitoring sm ON t.id = sm.ticket_id
            WHERE t.status NOT IN ('Resuelto', 'Cerrado')
            AND TIMESTAMPDIFF(HOUR, t.created_at, NOW()) > sm.sla_hours
            ORDER BY elapsed_hours DESC
            LIMIT 20
        ");
        
        return $result->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}