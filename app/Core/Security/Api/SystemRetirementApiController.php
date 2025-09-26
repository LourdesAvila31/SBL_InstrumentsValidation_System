<?php
/**
 * Controlador API para Sistema de Retiro de Sistema Computarizado - GAMP 5
 * 
 * Endpoints REST para gestionar el proceso de retiro del sistema conforme a GAMP 5:
 * - POST /retirement/initiate - Iniciar proceso de retiro
 * - POST /retirement/{id}/migrate - Ejecutar migración de datos
 * - POST /retirement/{id}/destroy - Ejecutar destrucción de datos
 * - POST /retirement/{id}/verify - Verificación post-retiro  
 * - GET /retirement/{id}/report - Generar reporte de retiro
 * - GET /retirement/{id}/status - Estado del proceso
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../permissions.php';
require_once __DIR__ . '/../SystemRetirement/SystemRetirementManager.php';
require_once __DIR__ . '/../Audit/AuditLogger.php';

class SystemRetirementApiController
{
    private SystemRetirementManager $retirementManager;
    private AuditLogger $auditLogger;
    
    public function __construct()
    {
        $this->retirementManager = new SystemRetirementManager();
        $this->auditLogger = new AuditLogger();
        
        // Verificar permisos de desarrollador/superadministrador
        if (!$this->hasRetirementPermissions()) {
            $this->sendError(403, 'Acceso denegado. Se requieren permisos de administrador para gestión de retiro de sistema.');
        }
    }
    
    /**
     * Maneja las peticiones API
     */
    public function handleRequest(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $pathParts = explode('/', trim($path, '/'));
        
        try {
            switch ($method) {
                case 'POST':
                    $this->handlePost($pathParts);
                    break;
                case 'GET':
                    $this->handleGet($pathParts);
                    break;

                default:
                    $this->sendError(405, 'Método no permitido');
            }
        } catch (Exception $e) {
            $this->auditLogger->logActivity(
                $_SESSION['usuario_id'] ?? null,
                'RETIREMENT_API_ERROR',
                ['method' => $method, 'path' => $path, 'error' => $e->getMessage()],
                null,
                AuditLogger::LEVEL_ERROR
            );
            
            $this->sendError(500, 'Error interno del servidor: ' . $e->getMessage());
        }
    }
    
    /**
     * Maneja peticiones POST
     */
    private function handlePost(array $pathParts): void
    {
        if (count($pathParts) < 2 || $pathParts[0] !== 'retirement') {
            $this->sendError(404, 'Endpoint no encontrado');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->sendError(400, 'JSON inválido en el cuerpo de la petición');
        }
        
        $action = $pathParts[1];
        
        switch ($action) {
            case 'initiate':
                $this->initiateRetirement($input);
                break;
            
            default:
                if (count($pathParts) >= 3 && is_numeric($pathParts[1])) {
                    $retirementId = (int)$pathParts[1];
                    $subAction = $pathParts[2];
                    
                    switch ($subAction) {
                        case 'migrate':
                            $this->migrateData($retirementId, $input);
                            break;
                        case 'destroy':
                            $this->destroyData($retirementId, $input);
                            break;
                        case 'verify':
                            $this->verifyRetirement($retirementId);
                            break;
                        case 'approve':
                            $this->approveRetirement($retirementId, $input);
                            break;
                        default:
                            $this->sendError(404, 'Acción no encontrada');
                    }
                } else {
                    $this->sendError(404, 'Endpoint no encontrado');
                }
        }
    }
    
    /**
     * Maneja peticiones GET
     */
    private function handleGet(array $pathParts): void
    {
        if (count($pathParts) < 2 || $pathParts[0] !== 'retirement') {
            $this->sendError(404, 'Endpoint no encontrado');
        }
        
        if ($pathParts[1] === 'processes') {
            $this->getRetirementProcesses();
            return;
        }
        
        if (is_numeric($pathParts[1])) {
            $retirementId = (int)$pathParts[1];
            
            if (count($pathParts) >= 3) {
                $subAction = $pathParts[2];
                
                switch ($subAction) {
                    case 'status':
                        $this->getRetirementStatus($retirementId);
                        break;
                    case 'report':
                        $this->getRetirementReport($retirementId);
                        break;
                    case 'audit-trail':
                        $this->getRetirementAuditTrail($retirementId);
                        break;
                    default:
                        $this->sendError(404, 'Endpoint no encontrado');
                }
            } else {
                $this->getRetirementDetails($retirementId);
            }
        } else {
            $this->sendError(404, 'Endpoint no encontrado');
        }
    }
    
    /**
     * Inicia proceso de retiro
     */
    private function initiateRetirement(array $input): void
    {
        // Validar entrada
        $requiredFields = ['systemName', 'retirementReason', 'deactivationDate'];
        foreach ($requiredFields as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                $this->sendError(400, "Campo requerido faltante: {$field}");
            }
        }
        
        // Validar fecha
        $date = DateTime::createFromFormat('Y-m-d', $input['deactivationDate']);
        if (!$date || $date <= new DateTime()) {
            $this->sendError(400, 'La fecha de desactivación debe ser una fecha futura válida');
        }
        
        $retirementPlan = [
            'systemName' => $input['systemName'],
            'retirementReason' => $input['retirementReason'],
            'deactivationDate' => $input['deactivationDate'],
            'dataMigrationRequired' => $input['dataMigrationRequired'] ?? false,
            'dataDestructionRequired' => $input['dataDestructionRequired'] ?? false,
            'estimatedDuration' => $input['estimatedDuration'] ?? null,
            'stakeholders' => $input['stakeholders'] ?? [],
            'riskAssessment' => $input['riskAssessment'] ?? ''
        ];
        
        $result = $this->retirementManager->initiateSystemRetirement($retirementPlan);
        
        if ($result['success']) {
            $this->sendSuccess($result, 201);
        } else {
            $this->sendError(500, $result['error']);
        }
    }
    
    /**
     * Ejecuta migración de datos
     */
    private function migrateData(int $retirementId, array $input): void
    {
        if (!isset($input['dataSources']) || !is_array($input['dataSources'])) {
            $this->sendError(400, 'Se requiere especificar fuentes de datos para migración');
        }
        
        $migrationConfig = [
            'dataSources' => $input['dataSources'],
            'targetPath' => $input['targetPath'] ?? '/tmp/migration',
            'compressionEnabled' => $input['compressionEnabled'] ?? true,
            'encryptionEnabled' => $input['encryptionEnabled'] ?? true,
            'verificationRequired' => $input['verificationRequired'] ?? true
        ];
        
        $result = $this->retirementManager->migrateData($retirementId, $migrationConfig);
        
        if ($result['success']) {
            $this->sendSuccess($result);
        } else {
            $this->sendError(500, $result['error']);
        }
    }
    
    /**
     * Ejecuta destrucción de datos
     */
    private function destroyData(int $retirementId, array $input): void
    {
        if (!isset($input['destructionTargets']) || !is_array($input['destructionTargets'])) {
            $this->sendError(400, 'Se requiere especificar objetivos de destrucción');
        }
        
        $destructionConfig = [
            'destructionTargets' => $input['destructionTargets'],
            'method' => $input['method'] ?? 'SECURE_WIPE',
            'passes' => $input['passes'] ?? 3,
            'verificationRequired' => $input['verificationRequired'] ?? true,
            'certificateRequired' => $input['certificateRequired'] ?? true
        ];
        
        // Validar método de destrucción
        $validMethods = ['SECURE_WIPE', 'CRYPTOGRAPHIC_ERASE', 'PHYSICAL_DESTRUCTION'];
        if (!in_array($destructionConfig['method'], $validMethods)) {
            $this->sendError(400, 'Método de destrucción no válido');
        }
        
        $result = $this->retirementManager->destroySensitiveData($retirementId, $destructionConfig);
        
        if ($result['success']) {
            $this->sendSuccess($result);
        } else {
            $this->sendError(500, $result['error']);
        }
    }
    
    /**
     * Ejecuta verificación post-retiro
     */
    private function verifyRetirement(int $retirementId): void
    {
        $result = $this->retirementManager->performPostRetirementVerification($retirementId);
        
        if ($result['success']) {
            $this->sendSuccess($result);
        } else {
            $this->sendError(500, $result['error']);
        }
    }
    
    /**
     * Aprueba proceso de retiro
     */
    private function approveRetirement(int $retirementId, array $input): void
    {
        $approvalType = $input['approvalType'] ?? 'FINAL_APPROVAL';
        $comments = $input['comments'] ?? '';
        $digitalSignature = $input['digitalSignature'] ?? '';
        
        // Registrar aprobación
        global $conn;
        $sql = "INSERT INTO retirement_approvals (
            retirement_id, approval_type, approved_by, comments, digital_signature
        ) VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $this->sendError(500, 'Error al preparar registro de aprobación');
        }
        
        $userId = $_SESSION['usuario_id'] ?? 0;
        $stmt->bind_param('isiss', $retirementId, $approvalType, $userId, $comments, $digitalSignature);
        
        if ($stmt->execute()) {
            $this->auditLogger->logActivity(
                $userId,
                'RETIREMENT_APPROVED',
                [
                    'retirement_id' => $retirementId,
                    'approval_type' => $approvalType
                ],
                null,
                AuditLogger::LEVEL_CRITICAL
            );
            
            $this->sendSuccess([
                'approved' => true,
                'approval_id' => $conn->insert_id,
                'message' => 'Proceso de retiro aprobado correctamente'
            ]);
        } else {
            $this->sendError(500, 'Error al registrar aprobación');
        }
    }
    
    /**
     * Obtiene lista de procesos de retiro
     */
    private function getRetirementProcesses(): void
    {
        global $conn;
        
        $sql = "SELECT p.*, 
                       u.nombre as created_by_name,
                       COUNT(a.id) as approvals_count
                FROM system_retirement_processes p
                LEFT JOIN usuarios u ON p.created_by = u.id
                LEFT JOIN retirement_approvals a ON p.id = a.retirement_id
                GROUP BY p.id
                ORDER BY p.created_at DESC";
        
        $result = $conn->query($sql);
        if (!$result) {
            $this->sendError(500, 'Error al consultar procesos de retiro');
        }
        
        $processes = [];
        while ($row = $result->fetch_assoc()) {
            $processes[] = $row;
        }
        
        $this->sendSuccess(['processes' => $processes]);
    }
    
    /**
     * Obtiene estado del proceso de retiro
     */
    private function getRetirementStatus(int $retirementId): void
    {
        global $conn;
        
        $sql = "SELECT * FROM system_retirement_processes WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $retirementId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $process = $result->fetch_assoc();
        if (!$process) {
            $this->sendError(404, 'Proceso de retiro no encontrado');
        }
        
        // Obtener progreso detallado
        $progress = $this->getRetirementProgress($retirementId);
        
        $this->sendSuccess([
            'process' => $process,
            'progress' => $progress
        ]);
    }
    
    /**
     * Obtiene reporte de retiro
     */
    private function getRetirementReport(int $retirementId): void
    {
        $result = $this->retirementManager->generateRetirementReport($retirementId);
        
        if ($result['success']) {
            // Configurar headers para descarga de reporte
            $format = $_GET['format'] ?? 'json';
            
            if ($format === 'pdf') {
                $this->generatePdfReport($result['report']);
            } else {
                $this->sendSuccess($result);
            }
        } else {
            $this->sendError(500, $result['error']);
        }
    }
    
    /**
     * Obtiene trail de auditoría del retiro
     */
    private function getRetirementAuditTrail(int $retirementId): void
    {
        global $conn;
        
        $sql = "SELECT * FROM audit_logs 
                WHERE event_data LIKE ? 
                ORDER BY timestamp DESC";
        
        $stmt = $conn->prepare($sql);
        $searchPattern = '%"retirement_id":' . $retirementId . '%';
        $stmt->bind_param('s', $searchPattern);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $auditTrail = [];
        while ($row = $result->fetch_assoc()) {
            $row['event_data'] = json_decode($row['event_data'], true);
            $auditTrail[] = $row;
        }
        
        $this->sendSuccess(['audit_trail' => $auditTrail]);
    }
    
    /**
     * Obtiene detalles del proceso de retiro
     */
    private function getRetirementDetails(int $retirementId): void
    {
        global $conn;
        
        // Obtener proceso principal
        $sql = "SELECT * FROM system_retirement_processes WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $retirementId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $process = $result->fetch_assoc();
        if (!$process) {
            $this->sendError(404, 'Proceso de retiro no encontrado');
        }
        
        // Obtener resultados de migración
        $migrationResults = $this->retirementManager->getMigrationResults($retirementId);
        
        // Obtener resultados de destrucción
        $destructionResults = $this->retirementManager->getDestructionResults($retirementId);
        
        // Obtener resultados de verificación
        $verificationResults = $this->retirementManager->getVerificationResults($retirementId);
        
        // Obtener aprobaciones
        $approvals = $this->retirementManager->getApprovals($retirementId);
        
        $this->sendSuccess([
            'process' => $process,
            'migration_results' => $migrationResults,
            'destruction_results' => $destructionResults,
            'verification_results' => $verificationResults,
            'approvals' => $approvals
        ]);
    }
    
    /**
     * Obtiene progreso del proceso de retiro
     */
    private function getRetirementProgress(int $retirementId): array
    {
        $steps = [
            'PLANNING' => ['completed' => false, 'percentage' => 0],
            'DATA_MIGRATION' => ['completed' => false, 'percentage' => 0],
            'DATA_DESTRUCTION' => ['completed' => false, 'percentage' => 0],
            'VERIFICATION' => ['completed' => false, 'percentage' => 0],
            'COMPLETED' => ['completed' => false, 'percentage' => 0]
        ];
        
        global $conn;
        
        // Obtener estado actual
        $sql = "SELECT status FROM system_retirement_processes WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $retirementId);
        $stmt->execute();
        $result = $stmt->get_result();
        $currentStatus = $result->fetch_assoc()['status'] ?? '';
        
        // Calcular progreso basado en estado
        $statusOrder = array_keys($steps);
        $currentIndex = array_search($currentStatus, $statusOrder);
        
        if ($currentIndex !== false) {
            for ($i = 0; $i <= $currentIndex; $i++) {
                $steps[$statusOrder[$i]]['completed'] = true;
                $steps[$statusOrder[$i]]['percentage'] = 100;
            }
        }
        
        return [
            'current_status' => $currentStatus,
            'overall_percentage' => $currentIndex !== false ? (($currentIndex + 1) / count($steps)) * 100 : 0,
            'steps' => $steps
        ];
    }
    
    /**
     * Genera reporte PDF
     */
    private function generatePdfReport(array $reportData): void
    {
        // Configurar headers para PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="retirement_report_' . $reportData['retirement_id'] . '.pdf"');
        
        // Generar contenido PDF simple (en implementación real usar librería como TCPDF)
        $pdfContent = "Sistema de Retiro - Reporte\n\n";
        $pdfContent .= "ID de Proceso: " . $reportData['retirement_id'] . "\n";
        $pdfContent .= "Sistema: " . $reportData['process_details']['system_name'] . "\n";
        $pdfContent .= "Estado: " . $reportData['process_details']['status'] . "\n";
        $pdfContent .= "Generado: " . $reportData['generated_at'] . "\n\n";
        
        echo $pdfContent;
    }
    
    /**
     * Verifica permisos para gestión de retiro
     */
    private function hasRetirementPermissions(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $roleAlias = session_role_alias();
        return in_array($roleAlias, ['developer', 'superadministrador', 'administrador']);
    }
    
    /**
     * Envía respuesta de éxito
     */
    private function sendSuccess(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Envía respuesta de error
     */
    private function sendError(int $statusCode, string $message): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}

// Instanciar y ejecutar controlador
$controller = new SystemRetirementApiController();
$controller->handleRequest();