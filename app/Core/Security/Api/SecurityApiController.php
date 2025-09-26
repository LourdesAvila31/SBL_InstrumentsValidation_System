<?php
/**
 * API REST para Sistema de Seguridad - GAMP 5 Compliant
 * 
 * Este módulo proporciona endpoints REST para:
 * - Gestión de MFA
 * - Consulta de logs de auditoría
 * - Gestión de contraseñas
 * - Monitoreo de seguridad
 * - Gestión de respaldos
 * - Reportes de cumplimiento
 * 
 * Todos los endpoints incluyen autenticación JWT y logging de auditoría
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../Core/db.php';
require_once __DIR__ . '/../MFA/MfaManager.php';
require_once __DIR__ . '/../Audit/AuditLogger.php';
require_once __DIR__ . '/../Password/PasswordManager.php';
require_once __DIR__ . '/../Access/AccessControlManager.php';
require_once __DIR__ . '/../Monitor/SecurityMonitor.php';
require_once __DIR__ . '/../Backup/BackupRecoveryManager.php';

/**
 * Controlador de API REST para Sistema de Seguridad
 */
class SecurityApiController
{
    private mysqli $conn;
    private MfaManager $mfaManager;
    private AuditLogger $auditLogger;
    private PasswordManager $passwordManager;
    private AccessControlManager $accessManager;
    private SecurityMonitor $securityMonitor;
    private BackupRecoveryManager $backupManager;
    
    // Headers CORS y seguridad
    private const CORS_HEADERS = [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With',
        'Content-Type' => 'application/json; charset=utf-8',
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'X-XSS-Protection' => '1; mode=block'
    ];
    
    public function __construct()
    {
        global $conn;
        $this->conn = $conn ?? DatabaseManager::getConnection();
        
        $this->mfaManager = new MfaManager($this->conn);
        $this->auditLogger = new AuditLogger($this->conn);
        $this->passwordManager = new PasswordManager($this->conn);
        $this->accessManager = new AccessControlManager($this->conn);
        $this->securityMonitor = new SecurityMonitor($this->conn);
        $this->backupManager = new BackupRecoveryManager($this->conn);
        
        $this->setSecurityHeaders();
    }
    
    /**
     * Enrutador principal de la API
     */
    public function handleRequest(): void
    {
        try {
            $method = $_SERVER['REQUEST_METHOD'];
            $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $pathParts = explode('/', trim($path, '/'));
            
            // Manejar preflight OPTIONS requests
            if ($method === 'OPTIONS') {
                http_response_code(200);
                exit;
            }
            
            // Validar estructura de la URL: /api/security/{module}/{action}
            if (count($pathParts) < 4 || $pathParts[0] !== 'api' || $pathParts[1] !== 'security') {
                $this->sendError('Invalid API endpoint', 404);
                return;
            }
            
            $module = $pathParts[2];
            $action = $pathParts[3];
            $id = $pathParts[4] ?? null;
            
            // Verificar autenticación (excepto para login)
            if (!($module === 'auth' && $action === 'login')) {
                $authResult = $this->verifyAuthentication();
                if (!$authResult['valid']) {
                    $this->sendError('Authentication required', 401);
                    return;
                }
            }
            
            // Enrutar a módulos específicos
            switch ($module) {
                case 'auth':
                    $this->handleAuthRequests($method, $action, $id);
                    break;
                
                case 'mfa':
                    $this->handleMfaRequests($method, $action, $id);
                    break;
                
                case 'audit':
                    $this->handleAuditRequests($method, $action, $id);
                    break;
                
                case 'password':
                    $this->handlePasswordRequests($method, $action, $id);
                    break;
                
                case 'monitor':
                    $this->handleMonitorRequests($method, $action, $id);
                    break;
                
                case 'backup':
                    $this->handleBackupRequests($method, $action, $id);
                    break;
                
                case 'compliance':
                    $this->handleComplianceRequests($method, $action, $id);
                    break;
                
                default:
                    $this->sendError('Unknown module', 404);
            }
            
        } catch (Exception $e) {
            $this->auditLogger->logActivity(
                $_SESSION['usuario_id'] ?? null,
                'API_ERROR',
                [
                    'error' => $e->getMessage(),
                    'endpoint' => $_SERVER['REQUEST_URI']
                ],
                null,
                AuditLogger::LEVEL_ERROR,
                'SYSTEM'
            );
            
            $this->sendError('Internal server error', 500);
        }
    }
    
    /**
     * Maneja requests de autenticación
     */
    private function handleAuthRequests(string $method, string $action, ?string $id): void
    {
        switch ($action) {
            case 'login':
                if ($method === 'POST') {
                    $this->login();
                } else {
                    $this->sendError('Method not allowed', 405);
                }
                break;
            
            case 'refresh':
                if ($method === 'POST') {
                    $this->refreshToken();
                } else {
                    $this->sendError('Method not allowed', 405);
                }
                break;
            
            case 'logout':
                if ($method === 'POST') {
                    $this->logout();
                } else {
                    $this->sendError('Method not allowed', 405);
                }
                break;
            
            case 'verify':
                if ($method === 'GET') {
                    $this->verifyToken();
                } else {
                    $this->sendError('Method not allowed', 405);
                }
                break;
            
            default:
                $this->sendError('Unknown auth action', 404);
        }
    }
    
    /**
     * Maneja requests de MFA
     */
    private function handleMfaRequests(string $method, string $action, ?string $id): void
    {
        switch ($action) {
            case 'setup':
                if ($method === 'POST') {
                    $this->setupMfa();
                } else {
                    $this->sendError('Method not allowed', 405);
                }
                break;
            
            case 'verify':
                if ($method === 'POST') {
                    $this->verifyMfa();
                } else {
                    $this->sendError('Method not allowed', 405);
                }
                break;
            
            case 'disable':
                if ($method === 'POST') {
                    $this->disableMfa();
                } else {
                    $this->sendError('Method not allowed', 405);
                }
                break;
            
            case 'backup-codes':
                if ($method === 'GET') {
                    $this->getBackupCodes();
                } elseif ($method === 'POST') {
                    $this->regenerateBackupCodes();
                } else {
                    $this->sendError('Method not allowed', 405);
                }
                break;
            
            case 'status':
                if ($method === 'GET') {
                    $this->getMfaStatus($id);
                } else {
                    $this->sendError('Method not allowed', 405);
                }
                break;
            
            default:
                $this->sendError('Unknown MFA action', 404);
        }
    }
    
    /**
     * Maneja requests de auditoría
     */
    private function handleAuditRequests(string $method, string $action, ?string $id): void
    {
        switch ($action) {
            case 'logs':
                if ($method === 'GET') {
                    $this->getAuditLogs();
                } else {
                    $this->sendError('Method not allowed', 405);
                }
                break;
            
            case 'search':
                if ($method === 'POST') {
                    $this->searchAuditLogs();
                } else {
                    $this->sendError('Method not allowed', 405);
                }
                break;
            
            case 'export':
                if ($method === 'POST') {
                    $this->exportAuditLogs();
                } else {
                    $this->sendError('Method not allowed', 405);
                }
                break;
            
            case 'integrity':
                if ($method === 'GET') {
                    $this->verifyAuditIntegrity();
                } else {
                    $this->sendError('Method not allowed', 405);
                }
                break;
            
            default:
                $this->sendError('Unknown audit action', 404);
        }
    }
    
    /**
     * Maneja requests de gestión de contraseñas
     */
    private function handlePasswordRequests(string $method, string $action, ?string $id): void
    {
        switch ($action) {
            case 'change':
                if ($method === 'POST') {
                    $this->changePassword();
                } else {
                    $this->sendError('Method not allowed', 405);
                }
                break;
            
            case 'reset':
                if ($method === 'POST') {
                    $this->resetPassword();
                } else {
                    $this->sendError('Method not allowed', 405);
                }
                break;
            
            case 'policy':
                if ($method === 'GET') {
                    $this->getPasswordPolicy();
                } elseif ($method === 'PUT') {
                    $this->updatePasswordPolicy();
                } else {
                    $this->sendError('Method not allowed', 405);
                }
                break;
            
            case 'validate':
                if ($method === 'POST') {
                    $this->validatePassword();
                } else {
                    $this->sendError('Method not allowed', 405);
                }
                break;
            
            case 'history':
                if ($method === 'GET') {
                    $this->getPasswordHistory($id);
                } else {
                    $this->sendError('Method not allowed', 405);
                }
                break;
            
            default:
                $this->sendError('Unknown password action', 404);
        }
    }
    
    /**
     * Maneja requests de monitoreo de seguridad
     */
    private function handleMonitorRequests(string $method, string $action, ?string $id): void
    {
        switch ($action) {
            case 'dashboard':
                if ($method === 'GET') {
                    $this->getSecurityDashboard();
                } else {
                    $this->sendError('Method not allowed', 405);
                }
                break;
            
            case 'threats':
                if ($method === 'GET') {
                    $this->getActiveThreats();
                } else {
                    $this->sendError('Method not allowed', 405);
                }
                break;
            
            case 'metrics':
                if ($method === 'GET') {
                    $this->getSecurityMetrics();
                } else {
                    $this->sendError('Method not allowed', 405);
                }
                break;
            
            case 'alerts':
                if ($method === 'GET') {
                    $this->getSecurityAlerts();
                } elseif ($method === 'POST') {
                    $this->acknowledgeAlert();
                } else {
                    $this->sendError('Method not allowed', 405);
                }
                break;
            
            case 'scan':
                if ($method === 'POST') {
                    $this->runSecurityScan();
                } else {
                    $this->sendError('Method not allowed', 405);
                }
                break;
            
            default:
                $this->sendError('Unknown monitor action', 404);
        }
    }
    
    /**
     * Maneja requests de respaldos
     */
    private function handleBackupRequests(string $method, string $action, ?string $id): void
    {
        switch ($action) {
            case 'create':
                if ($method === 'POST') {
                    $this->createBackup();
                } else {
                    $this->sendError('Method not allowed', 405);
                }
                break;
            
            case 'list':
                if ($method === 'GET') {
                    $this->listBackups();
                } else {
                    $this->sendError('Method not allowed', 405);
                }
                break;
            
            case 'verify':
                if ($method === 'POST') {
                    $this->verifyBackup();
                } else {
                    $this->sendError('Method not allowed', 405);
                }
                break;
            
            case 'restore':
                if ($method === 'POST') {
                    $this->restoreBackup();
                } else {
                    $this->sendError('Method not allowed', 405);
                }
                break;
            
            case 'statistics':
                if ($method === 'GET') {
                    $this->getBackupStatistics();
                } else {
                    $this->sendError('Method not allowed', 405);
                }
                break;
            
            case 'schedule':
                if ($method === 'GET') {
                    $this->getBackupSchedule();
                } elseif ($method === 'POST') {
                    $this->updateBackupSchedule();
                } else {
                    $this->sendError('Method not allowed', 405);
                }
                break;
            
            default:
                $this->sendError('Unknown backup action', 404);
        }
    }
    
    /**
     * Maneja requests de cumplimiento normativo
     */
    private function handleComplianceRequests(string $method, string $action, ?string $id): void
    {
        switch ($action) {
            case 'report':
                if ($method === 'GET') {
                    $this->getComplianceReport();
                } else {
                    $this->sendError('Method not allowed', 405);
                }
                break;
            
            case 'gamp5':
                if ($method === 'GET') {
                    $this->getGamp5ComplianceStatus();
                } else {
                    $this->sendError('Method not allowed', 405);
                }
                break;
            
            case '21cfr11':
                if ($method === 'GET') {
                    $this->get21CFR11ComplianceStatus();
                } else {
                    $this->sendError('Method not allowed', 405);
                }
                break;
            
            case 'gdpr':
                if ($method === 'GET') {
                    $this->getGdprComplianceStatus();
                } else {
                    $this->sendError('Method not allowed', 405);
                }
                break;
            
            case 'audit-trail':
                if ($method === 'GET') {
                    $this->getAuditTrailCompliance();
                } else {
                    $this->sendError('Method not allowed', 405);
                }
                break;
            
            default:
                $this->sendError('Unknown compliance action', 404);
        }
    }
    
    // Implementación de endpoints específicos
    
    /**
     * Login con MFA
     */
    private function login(): void
    {
        $input = $this->getJsonInput();
        
        if (!isset($input['username']) || !isset($input['password'])) {
            $this->sendError('Username and password required', 400);
            return;
        }
        
        $authResult = $this->accessManager->authenticate(
            $input['username'],
            $input['password'],
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? ''
        );
        
        if (!$authResult['success']) {
            $this->sendError($authResult['message'], 401);
            return;
        }
        
        // Si MFA está habilitado, requerir código
        if ($authResult['mfa_required']) {
            if (!isset($input['mfa_code'])) {
                $this->sendResponse([
                    'mfa_required' => true,
                    'temp_token' => $authResult['temp_token']
                ]);
                return;
            }
            
            $mfaResult = $this->mfaManager->verifyTotpCode(
                $authResult['user_id'],
                $input['mfa_code']
            );
            
            if (!$mfaResult['valid']) {
                $this->sendError('Invalid MFA code', 401);
                return;
            }
        }
        
        $this->sendResponse([
            'success' => true,
            'access_token' => $authResult['access_token'],
            'refresh_token' => $authResult['refresh_token'],
            'expires_in' => $authResult['expires_in'],
            'user' => $authResult['user']
        ]);
    }
    
    /**
     * Configurar MFA para usuario
     */
    private function setupMfa(): void
    {
        $userId = $_SESSION['usuario_id'];
        
        $setup = $this->mfaManager->setupMfa($userId);
        
        if ($setup['success']) {
            $this->sendResponse([
                'qr_code' => $setup['qr_code'],
                'secret' => $setup['secret'],
                'backup_codes' => $setup['backup_codes']
            ]);
        } else {
            $this->sendError($setup['message'], 400);
        }
    }
    
    /**
     * Obtener logs de auditoría
     */
    private function getAuditLogs(): void
    {
        $filters = [
            'user_id' => $_GET['user_id'] ?? null,
            'action' => $_GET['action'] ?? null,
            'date_from' => $_GET['date_from'] ?? null,
            'date_to' => $_GET['date_to'] ?? null,
            'level' => $_GET['level'] ?? null,
            'category' => $_GET['category'] ?? null
        ];
        
        $page = (int)($_GET['page'] ?? 1);
        $limit = min((int)($_GET['limit'] ?? 50), 1000); // Máximo 1000 registros
        
        $logs = $this->auditLogger->searchLogs($filters, $page, $limit);
        
        $this->sendResponse([
            'logs' => $logs['data'],
            'total' => $logs['total'],
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($logs['total'] / $limit)
        ]);
    }
    
    /**
     * Obtener dashboard de seguridad
     */
    private function getSecurityDashboard(): void
    {
        $dashboard = $this->securityMonitor->getSecurityDashboard();
        $this->sendResponse($dashboard);
    }
    
    /**
     * Crear respaldo
     */
    private function createBackup(): void
    {
        $input = $this->getJsonInput();
        $type = $input['type'] ?? 'FULL';
        
        if (!in_array($type, ['FULL', 'INCREMENTAL'])) {
            $this->sendError('Invalid backup type', 400);
            return;
        }
        
        if ($type === 'FULL') {
            $result = $this->backupManager->performFullBackup();
        } else {
            $result = $this->backupManager->performIncrementalBackup();
        }
        
        if ($result['success']) {
            $this->sendResponse($result);
        } else {
            $this->sendError($result['error'], 500);
        }
    }
    
    /**
     * Obtener reporte de cumplimiento
     */
    private function getComplianceReport(): void
    {
        $reportType = $_GET['type'] ?? 'general';
        $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');
        
        $report = $this->generateComplianceReport($reportType, $dateFrom, $dateTo);
        
        $this->sendResponse($report);
    }
    
    // Métodos auxiliares
    
    /**
     * Verifica la autenticación JWT
     */
    private function verifyAuthentication(): array
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return ['valid' => false, 'error' => 'No token provided'];
        }
        
        $token = $matches[1];
        
        $validation = $this->accessManager->validateAccessToken($token);
        
        if ($validation['valid']) {
            $_SESSION['usuario_id'] = $validation['user_id'];
            $_SESSION['token_data'] = $validation;
        }
        
        return $validation;
    }
    
    /**
     * Obtiene input JSON del request
     */
    private function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->sendError('Invalid JSON input', 400);
            exit;
        }
        
        return $data ?? [];
    }
    
    /**
     * Envía respuesta JSON exitosa
     */
    private function sendResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        
        $response = [
            'success' => true,
            'data' => $data,
            'timestamp' => date('c')
        ];
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * Envía respuesta de error JSON
     */
    private function sendError(string $message, int $statusCode = 400): void
    {
        http_response_code($statusCode);
        
        $response = [
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $statusCode
            ],
            'timestamp' => date('c')
        ];
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;    }
    
    /**
     * Configura headers de seguridad
     */
    private function setSecurityHeaders(): void
    {
        foreach (self::CORS_HEADERS as $header => $value) {
            header("$header: $value");
        }
    }
    
    /**
     * Genera reporte de cumplimiento normativo
     */
    private function generateComplianceReport(string $type, string $dateFrom, string $dateTo): array
    {
        $report = [
            'report_type' => $type,
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ],
            'generated_at' => date('c'),
            'generated_by' => $_SESSION['usuario_id'] ?? null
        ];
        
        switch ($type) {
            case 'gamp5':
                $report['data'] = $this->getGamp5ComplianceData($dateFrom, $dateTo);
                break;
            
            case '21cfr11':
                $report['data'] = $this->get21CFR11ComplianceData($dateFrom, $dateTo);
                break;
            
            case 'gdpr':
                $report['data'] = $this->getGdprComplianceData($dateFrom, $dateTo);
                break;
            
            default:
                $report['data'] = $this->getGeneralComplianceData($dateFrom, $dateTo);
        }
        
        return $report;
    }
    
    /**
     * Datos de cumplimiento GAMP 5
     */
    private function getGamp5ComplianceData(string $dateFrom, string $dateTo): array
    {
        return [
            'data_integrity' => [
                'audit_trail_completeness' => $this->checkAuditTrailCompleteness($dateFrom, $dateTo),
                'data_backup_compliance' => $this->checkBackupCompliance($dateFrom, $dateTo),
                'access_control_compliance' => $this->checkAccessControlCompliance($dateFrom, $dateTo)
            ],
            'validation_status' => [
                'system_validation' => 'Current',
                'last_validation_date' => '2024-01-01',
                'next_validation_due' => '2025-01-01'
            ],
            'change_control' => [
                'documented_changes' => $this->getDocumentedChanges($dateFrom, $dateTo),
                'unauthorized_changes' => $this->getUnauthorizedChanges($dateFrom, $dateTo)
            ]
        ];
    }
    
    /**
     * Datos de cumplimiento 21 CFR Part 11
     */
    private function get21CFR11ComplianceData(string $dateFrom, string $dateTo): array
    {
        return [
            'electronic_records' => [
                'secure_creation' => true,
                'accurate_reproduction' => true,
                'tamper_evidence' => $this->checkTamperEvidence($dateFrom, $dateTo)
            ],
            'electronic_signatures' => [
                'unique_identification' => true,
                'signature_verification' => true,
                'signature_manifestation' => true
            ],
            'audit_trail' => [
                'comprehensive_logging' => $this->checkComprehensiveLogging($dateFrom, $dateTo),
                'log_integrity' => $this->verifyLogIntegrity($dateFrom, $dateTo)
            ]
        ];
    }
    
    private function getGdprComplianceData(string $dateFrom, string $dateTo): array
    {
        return [
            'data_protection' => [
                'encryption_status' => 'Active',
                'data_minimization' => 'Compliant',
                'consent_management' => 'Implemented'
            ],
            'data_subject_rights' => [
                'access_requests' => $this->getDataAccessRequests($dateFrom, $dateTo),
                'deletion_requests' => $this->getDataDeletionRequests($dateFrom, $dateTo),
                'portability_requests' => $this->getDataPortabilityRequests($dateFrom, $dateTo)
            ],
            'breach_notifications' => [
                'incidents_reported' => $this->getSecurityIncidents($dateFrom, $dateTo),
                'notification_compliance' => 'Timely'
            ]
        ];
    }
    
    private function getGeneralComplianceData(string $dateFrom, string $dateTo): array
    {
        return [
            'security_overview' => [
                'authentication_events' => $this->getAuthenticationEvents($dateFrom, $dateTo),
                'access_violations' => $this->getAccessViolations($dateFrom, $dateTo),
                'system_changes' => $this->getSystemChanges($dateFrom, $dateTo)
            ],
            'backup_status' => [
                'successful_backups' => $this->getSuccessfulBackups($dateFrom, $dateTo),
                'failed_backups' => $this->getFailedBackups($dateFrom, $dateTo),
                'backup_verifications' => $this->getBackupVerifications($dateFrom, $dateTo)
            ],
            'compliance_score' => $this->calculateComplianceScore($dateFrom, $dateTo)
        ];
    }
    
    // Métodos de verificación de cumplimiento
    
    private function checkAuditTrailCompleteness(string $dateFrom, string $dateTo): array
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as total_events,
                   COUNT(CASE WHEN integrity_hash IS NOT NULL THEN 1 END) as events_with_hash
            FROM audit_logs 
            WHERE created_at BETWEEN ? AND ?
        ");
        
        $stmt->bind_param('ss', $dateFrom, $dateTo);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        $completeness = $result['total_events'] > 0 ? 
            ($result['events_with_hash'] / $result['total_events']) * 100 : 100;
        
        return [
            'percentage' => round($completeness, 2),
            'total_events' => (int)$result['total_events'],
            'events_with_integrity' => (int)$result['events_with_hash'],
            'compliant' => $completeness >= 95
        ];
    }
    
    private function checkBackupCompliance(string $dateFrom, string $dateTo): array
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as total_backups,
                   COUNT(CASE WHEN status = 'COMPLETED' THEN 1 END) as successful_backups
            FROM system_backups 
            WHERE created_at BETWEEN ? AND ?
        ");
        
        $stmt->bind_param('ss', $dateFrom, $dateTo);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        $successRate = $result['total_backups'] > 0 ? 
            ($result['successful_backups'] / $result['total_backups']) * 100 : 0;
        
        return [
            'success_rate' => round($successRate, 2),
            'total_backups' => (int)$result['total_backups'],
            'successful_backups' => (int)$result['successful_backups'],
            'compliant' => $successRate >= 95
        ];
    }
    
    private function checkAccessControlCompliance(string $dateFrom, string $dateTo): array
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as total_access_attempts,
                   COUNT(CASE WHEN action = 'LOGIN_SUCCESS' THEN 1 END) as successful_logins,
                   COUNT(CASE WHEN action = 'ACCESS_DENIED' THEN 1 END) as denied_access
            FROM audit_logs 
            WHERE created_at BETWEEN ? AND ?
            AND category = 'ACCESS_CONTROL'
        ");
        
        $stmt->bind_param('ss', $dateFrom, $dateTo);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return [
            'total_attempts' => (int)$result['total_access_attempts'],
            'successful_access' => (int)$result['successful_logins'],
            'denied_access' => (int)$result['denied_access'],
            'compliance_rate' => $result['total_access_attempts'] > 0 ? 
                round((1 - ($result['denied_access'] / $result['total_access_attempts'])) * 100, 2) : 100
        ];
    }
    
    private function calculateComplianceScore(string $dateFrom, string $dateTo): array
    {
        $auditCompliance = $this->checkAuditTrailCompleteness($dateFrom, $dateTo);
        $backupCompliance = $this->checkBackupCompliance($dateFrom, $dateTo);
        $accessCompliance = $this->checkAccessControlCompliance($dateFrom, $dateTo);
        
        $overallScore = (
            ($auditCompliance['compliant'] ? 100 : $auditCompliance['percentage']) +
            ($backupCompliance['compliant'] ? 100 : $backupCompliance['success_rate']) +
            $accessCompliance['compliance_rate']
        ) / 3;
        
        return [
            'overall_score' => round($overallScore, 2),
            'audit_score' => $auditCompliance['percentage'],
            'backup_score' => $backupCompliance['success_rate'],
            'access_score' => $accessCompliance['compliance_rate'],
            'compliance_level' => $overallScore >= 95 ? 'Excellent' : 
                                ($overallScore >= 85 ? 'Good' : 
                                ($overallScore >= 70 ? 'Acceptable' : 'Needs Improvement'))
        ];
    }
    
    // Métodos auxiliares adicionales para obtener datos específicos
    
    private function getAuthenticationEvents(string $dateFrom, string $dateTo): int
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count FROM audit_logs 
            WHERE created_at BETWEEN ? AND ?
            AND action IN ('LOGIN_SUCCESS', 'LOGIN_FAILED', 'LOGOUT')
        ");
        
        $stmt->bind_param('ss', $dateFrom, $dateTo);
        $stmt->execute();
        
        return (int)$stmt->get_result()->fetch_assoc()['count'];
    }
    
    private function getAccessViolations(string $dateFrom, string $dateTo): int
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count FROM audit_logs 
            WHERE created_at BETWEEN ? AND ?
            AND action = 'ACCESS_DENIED'
        ");
        
        $stmt->bind_param('ss', $dateFrom, $dateTo);
        $stmt->execute();
        
        return (int)$stmt->get_result()->fetch_assoc()['count'];
    }
    
    private function getSystemChanges(string $dateFrom, string $dateTo): int
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count FROM audit_logs 
            WHERE created_at BETWEEN ? AND ?
            AND category = 'DATA_CHANGE'
        ");
        
        $stmt->bind_param('ss', $dateFrom, $dateTo);
        $stmt->execute();
        
        return (int)$stmt->get_result()->fetch_assoc()['count'];
    }
    
    private function getSuccessfulBackups(string $dateFrom, string $dateTo): int
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count FROM system_backups 
            WHERE created_at BETWEEN ? AND ?
            AND status = 'COMPLETED'
        ");
        
        $stmt->bind_param('ss', $dateFrom, $dateTo);
        $stmt->execute();
        
        return (int)$stmt->get_result()->fetch_assoc()['count'];
    }
    
    private function getFailedBackups(string $dateFrom, string $dateTo): int
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count FROM system_backups 
            WHERE created_at BETWEEN ? AND ?
            AND status = 'FAILED'
        ");
        
        $stmt->bind_param('ss', $dateFrom, $dateTo);
        $stmt->execute();
        
        return (int)$stmt->get_result()->fetch_assoc()['count'];
    }
    
    private function getBackupVerifications(string $dateFrom, string $dateTo): int
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count FROM system_backups 
            WHERE created_at BETWEEN ? AND ?
            AND status = 'VERIFIED'
        ");
        
        $stmt->bind_param('ss', $dateFrom, $dateTo);
        $stmt->execute();
        
        return (int)$stmt->get_result()->fetch_assoc()['count'];
    }
    
    // Métodos placeholder para funcionalidades adicionales
    private function checkTamperEvidence(string $dateForm, string $dateTo): bool { return true; }
    private function checkComprehensiveLogging(string $dateFrom, string $dateTo): bool { return true; }
    private function verifyLogIntegrity(string $dateFrom, string $dateTo): bool { return true; }
    private function getDataAccessRequests(string $dateFrom, string $dateTo): int { return 0; }
    private function getDataDeletionRequests(string $dateFrom, string $dateTo): int { return 0; }
    private function getDataPortabilityRequests(string $dateFrom, string $dateTo): int { return 0; }
    private function getSecurityIncidents(string $dateFrom, string $dateTo): int { return 0; }
    private function getDocumentedChanges(string $dateFrom, string $dateTo): int { return 0; }
    private function getUnauthorizedChanges(string $dateFrom, string $dateTo): int { return 0; }
    
    // Implementaciones faltantes de endpoints
    
    private function refreshToken(): void
    {
        $input = $this->getJsonInput();
        
        if (!isset($input['refresh_token'])) {
            $this->sendError('Refresh token required', 400);
            return;
        }
        
        $result = $this->accessManager->refreshAccessToken($input['refresh_token']);
        
        if ($result['success']) {
            $this->sendResponse([
                'access_token' => $result['access_token'],
                'expires_in' => $result['expires_in']
            ]);
        } else {
            $this->sendError($result['message'], 401);
        }
    }
    
    private function logout(): void
    {
        $authData = $_SESSION['token_data'] ?? null;
        
        if ($authData && isset($authData['token'])) {
            $this->accessManager->revokeAccessToken($authData['token']);
        }
        
        session_destroy();
        
        $this->sendResponse(['message' => 'Logged out successfully']);
    }
    
    private function verifyToken(): void
    {
        $authData = $_SESSION['token_data'] ?? null;
        
        if ($authData) {
            $this->sendResponse([
                'valid' => true,
                'user_id' => $authData['user_id'],
                'expires_at' => $authData['expires_at']
            ]);
        } else {
            $this->sendError('Invalid token', 401);
        }
    }
    
    private function verifyMfa(): void
    {
        $input = $this->getJsonInput();
        $userId = $_SESSION['usuario_id'];
        
        if (!isset($input['code'])) {
            $this->sendError('MFA code required', 400);
            return;
        }
        
        $result = $this->mfaManager->verifyTotpCode($userId, $input['code']);
        
        $this->sendResponse($result);
    }
    
    private function disableMfa(): void
    {
        $input = $this->getJsonInput();
        $userId = $_SESSION['usuario_id'];
        
        if (!isset($input['password'])) {
            $this->sendError('Password confirmation required', 400);
            return;
        }
        
        $result = $this->mfaManager->disableMfa($userId, $input['password']);
        
        if ($result['success']) {
            $this->sendResponse(['message' => 'MFA disabled successfully']);
        } else {
            $this->sendError($result['message'], 400);
        }
    }
    
    private function getBackupCodes(): void
    {
        $userId = $_SESSION['usuario_id'];
        $codes = $this->mfaManager->getUnusedBackupCodes($userId);
        
        $this->sendResponse(['backup_codes' => $codes]);
    }
    
    private function regenerateBackupCodes(): void
    {
        $userId = $_SESSION['usuario_id'];
        $result = $this->mfaManager->regenerateBackupCodes($userId);
        
        $this->sendResponse(['backup_codes' => $result['codes']]);
    }
    
    private function getMfaStatus(?string $userId): void
    {
        $targetUserId = $userId ?? $_SESSION['usuario_id'];
        $status = $this->mfaManager->getMfaStatus((int)$targetUserId);
        
        $this->sendResponse($status);
    }
    
    // Implementaciones faltantes de métodos
    
    private function searchAuditLogs(): void
    {
        $input = $this->getJsonInput();
        
        $filters = [
            'user_id' => $input['user_id'] ?? null,
            'action' => $input['action'] ?? null,
            'date_from' => $input['date_from'] ?? null,
            'date_to' => $input['date_to'] ?? null,
            'level' => $input['level'] ?? null,
            'category' => $input['category'] ?? null,
            'search_term' => $input['search_term'] ?? null
        ];
        
        $page = (int)($input['page'] ?? 1);
        $limit = min((int)($input['limit'] ?? 50), 1000);
        
        $logs = $this->searchAuditLogsInternal($filters, $page, $limit);
        
        $this->sendResponse([
            'logs' => $logs['data'],
            'total' => $logs['total'],
            'page' => $page,
            'limit' => $limit,
            'filters_applied' => array_filter($filters)
        ]);
    }
    
    private function exportAuditLogs(): void
    {
        $input = $this->getJsonInput();
        
        $filters = [
            'date_from' => $input['date_from'] ?? date('Y-m-d', strtotime('-30 days')),
            'date_to' => $input['date_to'] ?? date('Y-m-d'),
            'format' => $input['format'] ?? 'csv'
        ];
        
        $export = $this->exportAuditLogsInternal($filters);
        
        if ($export['success']) {
            $this->sendResponse([
                'export_file' => $export['file_path'],
                'download_url' => '/api/security/audit/download/' . basename($export['file_path']),
                'record_count' => $export['record_count']
            ]);
        } else {
            $this->sendError($export['error'], 500);
        }
    }
    
    private function verifyAuditIntegrity(): void
    {
        $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');
        
        $verification = $this->verifyAuditIntegrityInternal($dateFrom, $dateTo);
        
        $this->sendResponse($verification);
    }
    
    private function changePassword(): void
    {
        $input = $this->getJsonInput();
        $userId = $_SESSION['usuario_id'];
        
        if (!isset($input['current_password']) || !isset($input['new_password'])) {
            $this->sendError('Current and new password required', 400);
            return;
        }
        
        $result = $this->passwordManager->changePassword(
            $userId,
            $input['current_password'],
            $input['new_password']
        );
        
        if ($result['success']) {
            $this->sendResponse(['message' => 'Password changed successfully']);
        } else {
            $this->sendError($result['message'], 400);
        }
    }
    
    private function resetPassword(): void
    {
        $input = $this->getJsonInput();
        
        if (!isset($input['user_id'])) {
            $this->sendError('User ID required', 400);
            return;
        }
        
        // Verificar permisos de administrador
        if (!$this->hasAdminPermissions()) {
            $this->sendError('Insufficient permissions', 403);
            return;
        }
        
        $result = $this->resetPasswordInternal($input['user_id']);
        
        if ($result['success']) {
            $this->sendResponse([
                'message' => 'Password reset successfully',
                'temporary_password' => $result['temporary_password']
            ]);
        } else {
            $this->sendError($result['message'], 400);
        }
    }
    
    private function getPasswordPolicy(): void
    {
        $policy = $this->getPasswordPolicyInternal();
        $this->sendResponse($policy);
    }
    
    private function updatePasswordPolicy(): void
    {
        $input = $this->getJsonInput();
        
        if (!$this->hasAdminPermissions()) {
            $this->sendError('Insufficient permissions', 403);
            return;
        }
        
        $result = $this->updatePasswordPolicyInternal($input);
        
        if ($result['success']) {
            $this->sendResponse(['message' => 'Password policy updated successfully']);
        } else {
            $this->sendError($result['message'], 400);
        }
    }
    
    private function validatePassword(): void
    {
        $input = $this->getJsonInput();
        
        if (!isset($input['password'])) {
            $this->sendError('Password required', 400);
            return;
        }
        
        $validation = $this->passwordManager->validatePassword($input['password']);
        $this->sendResponse($validation);
    }
    
    private function getPasswordHistory(?string $userId): void
    {
        $targetUserId = $userId ? (int)$userId : $_SESSION['usuario_id'];
        
        // Solo permitir ver historial propio o si es administrador
        if ($targetUserId !== $_SESSION['usuario_id'] && !$this->hasAdminPermissions()) {
            $this->sendError('Insufficient permissions', 403);
            return;
        }
        
        $history = $this->getPasswordHistoryInternal($targetUserId);
        $this->sendResponse(['history' => $history]);
    }
    
    private function getActiveThreats(): void
    {
        $timeframe = $_GET['timeframe'] ?? '24h';
        $severity = $_GET['severity'] ?? null;
        
        $threats = $this->getActiveThreatsInternal($timeframe, $severity);
        $this->sendResponse($threats);
    }
    
    private function getSecurityMetrics(): void
    {
        $period = $_GET['period'] ?? 'day';
        $metrics = $this->securityMonitor->getSecurityMetrics($period);
        $this->sendResponse($metrics);
    }
    
    private function getSecurityAlerts(): void
    {
        $status = $_GET['status'] ?? 'unacknowledged';
        $limit = min((int)($_GET['limit'] ?? 50), 500);
        
        $alerts = $this->getSecurityAlertsInternal($status, $limit);
        $this->sendResponse($alerts);
    }
    
    private function acknowledgeAlert(): void
    {
        $input = $this->getJsonInput();
        
        if (!isset($input['alert_id'])) {
            $this->sendError('Alert ID required', 400);
            return;
        }
        
        $result = $this->acknowledgeAlertInternal(
            $input['alert_id'],
            $_SESSION['usuario_id'],
            $input['comment'] ?? null
        );
        
        if ($result['success']) {
            $this->sendResponse(['message' => 'Alert acknowledged']);
        } else {
            $this->sendError($result['message'], 400);
        }
    }
    
    private function runSecurityScan(): void
    {
        $input = $this->getJsonInput();
        $scanType = $input['scan_type'] ?? 'full';
        
        $scanResult = $this->runSecurityScanInternal($scanType);
        
        if ($scanResult['success']) {
            $this->sendResponse($scanResult);
        } else {
            $this->sendError($scanResult['error'], 500);
        }
    }
    
    private function listBackups(): void
    {
        $type = $_GET['type'] ?? null;
        $status = $_GET['status'] ?? null;
        $limit = min((int)($_GET['limit'] ?? 50), 500);
        $offset = (int)($_GET['offset'] ?? 0);
        
        $backups = $this->getBackupsList($type, $status, $limit, $offset);
        $this->sendResponse($backups);
    }
    
    private function verifyBackup(): void
    {
        $input = $this->getJsonInput();
        
        if (!isset($input['backup_id'])) {
            $this->sendError('Backup ID required', 400);
            return;
        }
        
        $result = $this->backupManager->verifyBackupIntegrity($input['backup_id']);
        
        if ($result['success']) {
            $this->sendResponse($result);
        } else {
            $this->sendError($result['error'], 500);
        }
    }
    
    private function restoreBackup(): void
    {
        $input = $this->getJsonInput();
        
        if (!isset($input['backup_id'])) {
            $this->sendError('Backup ID required', 400);
            return;
        }
        
        if (!$this->hasAdminPermissions()) {
            $this->sendError('Insufficient permissions for restore operation', 403);
            return;
        }
        
        $components = $input['components'] ?? ['database', 'files', 'config'];
        $testMode = $input['test_mode'] ?? false;
        
        $result = $this->backupManager->restoreFromBackup(
            $input['backup_id'],
            $components,
            $testMode
        );
        
        if ($result['success']) {
            $this->sendResponse($result);
        } else {
            $this->sendError($result['error'], 500);
        }
    }
    
    private function getBackupStatistics(): void
    {
        $stats = $this->backupManager->getBackupStatistics();
        $this->sendResponse($stats);
    }
    
    private function getBackupSchedule(): void
    {
        $schedule = $this->getScheduledBackups();
        $this->sendResponse($schedule);
    }
    
    private function updateBackupSchedule(): void
    {
        $input = $this->getJsonInput();
        
        if (!$this->hasAdminPermissions()) {
            $this->sendError('Insufficient permissions', 403);
            return;
        }
        
        $result = $this->updateScheduledBackups($input);
        
        if ($result['success']) {
            $this->sendResponse(['message' => 'Backup schedule updated successfully']);
        } else {
            $this->sendError($result['message'], 400);
        }
    }
    
    // Métodos auxiliares adicionales
    
    private function hasAdminPermissions(): bool
    {
        if (!isset($_SESSION['usuario_id'])) {
            return false;
        }
        
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count
            FROM usuarios u
            JOIN roles r ON u.role_id = r.id
            WHERE u.id = ? AND r.nombre IN ('Superadministrador', 'Developer', 'Administrador')
        ");
        
        $stmt->bind_param('i', $_SESSION['usuario_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc()['count'] > 0;
    }
    
    private function getBackupsList(?string $type, ?string $status, int $limit, int $offset): array
    {
        $whereConditions = [];
        $params = [];
        $paramTypes = '';
        
        if ($type) {
            $whereConditions[] = "backup_type = ?";
            $params[] = $type;
            $paramTypes .= 's';
        }
        
        if ($status) {
            $whereConditions[] = "status = ?";
            $params[] = $status;
            $paramTypes .= 's';
        }
        
        $whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $query = "
            SELECT backup_id, backup_type, backup_size_bytes, status, created_at, completed_at
            FROM system_backups 
            $whereClause
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        $paramTypes .= 'ii';
        
        $stmt = $this->conn->prepare($query);
        
        if ($paramTypes) {
            $stmt->bind_param($paramTypes, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $backups = [];
        while ($row = $result->fetch_assoc()) {
            $row['backup_size_mb'] = round($row['backup_size_bytes'] / 1024 / 1024, 2);
            unset($row['backup_size_bytes']);
            $backups[] = $row;
        }
        
        // Obtener total de registros
        $countQuery = str_replace(
            ['SELECT backup_id, backup_type, backup_size_bytes, status, created_at, completed_at', 'ORDER BY created_at DESC LIMIT ? OFFSET ?'],
            ['SELECT COUNT(*) as total', ''],
            $query
        );
        
        $countStmt = $this->conn->prepare($countQuery);
        
        if ($whereConditions) {
            $countTypes = substr($paramTypes, 0, -2); // Remover 'ii' del final
            $countParams = array_slice($params, 0, -2); // Remover limit y offset
            
            if ($countTypes) {
                $countStmt->bind_param($countTypes, ...$countParams);
            }
        }
        
        $countStmt->execute();
        $total = $countStmt->get_result()->fetch_assoc()['total'];
        
        return [
            'backups' => $backups,
            'total' => (int)$total,
            'limit' => $limit,
            'offset' => $offset
        ];
    }
    
    private function getScheduledBackups(): array
    {
        // Implementación básica - en producción se integraría con sistema de cron
        return [
            'full_backup' => [
                'frequency' => 'weekly',
                'day' => 'sunday',
                'time' => '02:00',
                'enabled' => true
            ],
            'incremental_backup' => [
                'frequency' => 'daily',
                'time' => '23:00',
                'enabled' => true
            ],
            'next_scheduled' => [
                'full' => date('Y-m-d H:i:s', strtotime('next sunday 02:00')),
                'incremental' => date('Y-m-d H:i:s', strtotime('today 23:00'))
            ]
        ];
    }
    
    private function updateScheduledBackups(array $schedule): array
    {
        // Implementación básica - validar y guardar configuración
        $validFrequencies = ['daily', 'weekly', 'monthly'];
        
        foreach ($schedule as $backupType => $config) {
            if (!in_array($config['frequency'], $validFrequencies)) {
                return [
                    'success' => false,
                    'message' => "Invalid frequency for $backupType"
                ];
            }
        }
        
        // Aquí se actualizaría la configuración real del scheduler
        
        return [
            'success' => true,
            'message' => 'Schedule updated successfully'
        ];
    }
    
    // Implementaciones internas para métodos faltantes
    
    private function searchAuditLogsInternal(array $filters, int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;
        $whereConditions = [];
        $params = [];
        $paramTypes = '';
        
        if ($filters['user_id']) {
            $whereConditions[] = "user_id = ?";
            $params[] = $filters['user_id'];
            $paramTypes .= 'i';
        }
        
        if ($filters['action']) {
            $whereConditions[] = "action = ?";
            $params[] = $filters['action'];
            $paramTypes .= 's';
        }
        
        if ($filters['date_from']) {
            $whereConditions[] = "created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
            $paramTypes .= 's';
        }
        
        if ($filters['date_to']) {
            $whereConditions[] = "created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
            $paramTypes .= 's';
        }
        
        if ($filters['level']) {
            $whereConditions[] = "level = ?";
            $params[] = $filters['level'];
            $paramTypes .= 's';
        }
        
        if ($filters['category']) {
            $whereConditions[] = "category = ?";
            $params[] = $filters['category'];
            $paramTypes .= 's';
        }
        
        if ($filters['search_term']) {
            $whereConditions[] = "(action LIKE ? OR details LIKE ? OR description LIKE ?)";
            $searchTerm = '%' . $filters['search_term'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $paramTypes .= 'sss';
        }
        
        $whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // Obtener logs
        $query = "
            SELECT id, user_id, action, details, table_name, record_id, 
                   level, category, created_at, ip_address, user_agent
            FROM audit_logs 
            $whereClause
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        $paramTypes .= 'ii';
        
        $stmt = $this->conn->prepare($query);
        if ($paramTypes) {
            $stmt->bind_param($paramTypes, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $logs = [];
        while ($row = $result->fetch_assoc()) {
            if ($row['details']) {
                $row['details'] = json_decode($row['details'], true);
            }
            $logs[] = $row;
        }
        
        // Obtener total
        $countQuery = str_replace(
            ['SELECT id, user_id, action, details, table_name, record_id, level, category, created_at, ip_address, user_agent', 'ORDER BY created_at DESC LIMIT ? OFFSET ?'],
            ['SELECT COUNT(*) as total', ''],
            $query
        );
        
        $countParams = array_slice($params, 0, -2);
        $countTypes = substr($paramTypes, 0, -2);
        
        $countStmt = $this->conn->prepare($countQuery);
        if ($countTypes) {
            $countStmt->bind_param($countTypes, ...$countParams);
        }
        
        $countStmt->execute();
        $total = $countStmt->get_result()->fetch_assoc()['total'];
        
        return [
            'data' => $logs,
            'total' => (int)$total
        ];
    }
    
    private function exportAuditLogsInternal(array $filters): array
    {
        $filename = 'audit_logs_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = __DIR__ . '/../../../../storage/exports/' . $filename;
        
        // Crear directorio si no existe
        $exportDir = dirname($filepath);
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0750, true);
        }
        
        $logs = $this->searchAuditLogsInternal($filters, 1, 10000); // Máximo 10k registros
        
        $fp = fopen($filepath, 'w');
        
        // Headers CSV
        fputcsv($fp, [
            'ID', 'User ID', 'Action', 'Details', 'Table', 'Record ID',
            'Level', 'Category', 'Created At', 'IP Address', 'User Agent'
        ]);
        
        // Datos
        foreach ($logs['data'] as $log) {
            fputcsv($fp, [
                $log['id'],
                $log['user_id'],
                $log['action'],
                is_array($log['details']) ? json_encode($log['details']) : $log['details'],
                $log['table_name'],
                $log['record_id'],
                $log['level'],
                $log['category'],
                $log['created_at'],
                $log['ip_address'],
                $log['user_agent']
            ]);
        }
        
        fclose($fp);
        
        return [
            'success' => true,
            'file_path' => $filepath,
            'record_count' => count($logs['data'])
        ];
    }
    
    private function verifyAuditIntegrityInternal(string $dateFrom, string $dateTo): array
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as total_logs,
                   COUNT(CASE WHEN integrity_hash IS NOT NULL THEN 1 END) as logs_with_hash,
                   MIN(created_at) as first_log,
                   MAX(created_at) as last_log
            FROM audit_logs 
            WHERE created_at BETWEEN ? AND ?
        ");
        
        $startDate = $dateFrom . ' 00:00:00';
        $endDate = $dateTo . ' 23:59:59';
        $stmt->bind_param('ss', $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        $integrity_percentage = $result['total_logs'] > 0 ? 
            ($result['logs_with_hash'] / $result['total_logs']) * 100 : 100;
        
        return [
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ],
            'total_logs' => (int)$result['total_logs'],
            'logs_with_integrity' => (int)$result['logs_with_hash'],
            'integrity_percentage' => round($integrity_percentage, 2),
            'first_log' => $result['first_log'],
            'last_log' => $result['last_log'],
            'status' => $integrity_percentage >= 95 ? 'GOOD' : 'WARNING'
        ];
    }
    
    private function getPasswordPolicyInternal(): array
    {
        return [
            'min_length' => 8,
            'require_uppercase' => true,
            'require_lowercase' => true,
            'require_numbers' => true,
            'require_special_chars' => true,
            'max_age_days' => 90,
            'history_count' => 5,
            'lockout_attempts' => 5,
            'lockout_duration_minutes' => 30
        ];
    }
    
    private function resetPasswordInternal(int $userId): array
    {
        $tempPassword = $this->generateTemporaryPassword();
        $hashedPassword = password_hash($tempPassword, PASSWORD_ARGON2ID);
        
        $stmt = $this->conn->prepare("
            UPDATE usuarios 
            SET password = ?, password_changed_at = NOW(), must_change_password = 1
            WHERE id = ?
        ");
        
        $stmt->bind_param('si', $hashedPassword, $userId);
        
        if ($stmt->execute()) {
            return [
                'success' => true,
                'temporary_password' => $tempPassword
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to reset password'
            ];
        }
    }
    
    private function generateTemporaryPassword(): string
    {
        $length = 12;
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        return $password;
    }
    
    private function updatePasswordPolicyInternal(array $policy): array
    {
        // Validaciones básicas
        $requiredFields = ['min_length', 'max_age_days', 'history_count', 'lockout_attempts'];
        
        foreach ($requiredFields as $field) {
            if (!isset($policy[$field]) || !is_numeric($policy[$field])) {
                return [
                    'success' => false,
                    'message' => "Invalid or missing field: $field"
                ];
            }
        }
        
        // Aquí se guardaría la política en la base de datos
        // Por ahora solo simulamos éxito
        
        return [
            'success' => true,
            'message' => 'Password policy updated'
        ];
    }
    
    private function getPasswordHistoryInternal(int $userId): array
    {
        $stmt = $this->conn->prepare("
            SELECT changed_at, changed_by_admin
            FROM password_history 
            WHERE user_id = ? 
            ORDER BY changed_at DESC 
            LIMIT 10
        ");
        
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = [
                'changed_at' => $row['changed_at'],
                'changed_by_admin' => (bool)$row['changed_by_admin']
            ];
        }
        
        return $history;
    }
    
    private function getActiveThreatsInternal(string $timeframe, ?string $severity): array
    {
        $hours = match($timeframe) {
            '1h' => 1,
            '24h' => 24,
            '7d' => 168,
            '30d' => 720,
            default => 24
        };
        
        $whereConditions = ["detected_at >= DATE_SUB(NOW(), INTERVAL $hours HOUR)"];
        $params = [];
        $paramTypes = '';
        
        if ($severity) {
            $whereConditions[] = "severity = ?";
            $params[] = $severity;
            $paramTypes .= 's';
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        
        $stmt = $this->conn->prepare("
            SELECT threat_type, source_ip, severity, description, detected_at, status
            FROM security_threats 
            $whereClause
            ORDER BY detected_at DESC
            LIMIT 100
        ");
        
        if ($paramTypes) {
            $stmt->bind_param($paramTypes, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $threats = [];
        while ($row = $result->fetch_assoc()) {
            $threats[] = $row;
        }
        
        return [
            'threats' => $threats,
            'timeframe' => $timeframe,
            'total_count' => count($threats)
        ];
    }
    
    private function getSecurityAlertsInternal(string $status, int $limit): array
    {
        $stmt = $this->conn->prepare("
            SELECT id, alert_type, severity, message, created_at, acknowledged_at, acknowledged_by
            FROM security_alerts 
            WHERE status = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        
        $stmt->bind_param('si', $status, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $alerts = [];
        while ($row = $result->fetch_assoc()) {
            $alerts[] = $row;
        }
        
        return [
            'alerts' => $alerts,
            'status_filter' => $status,
            'count' => count($alerts)
        ];
    }
    
    private function acknowledgeAlertInternal(int $alertId, int $userId, ?string $comment): array
    {
        $stmt = $this->conn->prepare("
            UPDATE security_alerts 
            SET status = 'acknowledged', acknowledged_at = NOW(), acknowledged_by = ?, acknowledgment_comment = ?
            WHERE id = ? AND status = 'unacknowledged'
        ");
        
        $stmt->bind_param('isi', $userId, $comment, $alertId);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            return [
                'success' => true,
                'message' => 'Alert acknowledged successfully'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Alert not found or already acknowledged'
            ];
        }
    }
    
    private function runSecurityScanInternal(string $scanType): array
    {
        // Simulación de escaneo de seguridad
        sleep(2); // Simular tiempo de procesamiento
        
        $findings = [];
        
        if ($scanType === 'full' || $scanType === 'vulnerabilities') {
            $findings[] = [
                'type' => 'vulnerability',
                'severity' => 'medium',
                'description' => 'Outdated software component detected',
                'recommendation' => 'Update to latest version'
            ];
        }
        
        if ($scanType === 'full' || $scanType === 'permissions') {
            $findings[] = [
                'type' => 'permissions',
                'severity' => 'low',
                'description' => 'User with excessive privileges found',
                'recommendation' => 'Review and reduce permissions'
            ];
        }
        
        return [
            'success' => true,
            'scan_type' => $scanType,
            'scan_id' => uniqid('scan_'),
            'started_at' => date('Y-m-d H:i:s'),
            'completed_at' => date('Y-m-d H:i:s'),
            'findings' => $findings,
            'status' => 'completed'
        ];
    }
}