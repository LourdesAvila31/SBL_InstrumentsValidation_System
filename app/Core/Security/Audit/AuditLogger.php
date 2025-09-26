<?php
/**
 * Sistema de Auditoría y Logging - GAMP 5 Compliant
 * 
 * Este módulo implementa un sistema robusto de auditoría que registra:
 * - Todas las actividades de los usuarios
 * - Cambios en datos críticos
 * - Accesos al sistema
 * - Eventos de seguridad
 * 
 * Cumple con los estándares de trazabilidad de GAMP 5 y normativas GxP
 * incluyendo 21 CFR Part 11 para registros electrónicos
 */

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';

/**
 * Registrador de Auditoría para cumplimiento GxP
 */
class AuditLogger
{
    private mysqli $conn;
    private string $applicationId;
    
    // Niveles de criticidad según GAMP 5
    public const LEVEL_INFO = 'INFO';
    public const LEVEL_WARNING = 'WARNING';
    public const LEVEL_ERROR = 'ERROR';
    public const LEVEL_CRITICAL = 'CRITICAL';
    public const LEVEL_SECURITY = 'SECURITY';
    
    // Categorías de eventos según 21 CFR Part 11
    public const CATEGORY_AUTHENTICATION = 'AUTHENTICATION';
    public const CATEGORY_AUTHORIZATION = 'AUTHORIZATION';
    public const CATEGORY_DATA_CREATION = 'DATA_CREATION';
    public const CATEGORY_DATA_MODIFICATION = 'DATA_MODIFICATION';
    public const CATEGORY_DATA_DELETION = 'DATA_DELETION';
    public const CATEGORY_SYSTEM_ACCESS = 'SYSTEM_ACCESS';
    public const CATEGORY_CONFIGURATION = 'CONFIGURATION';
    public const CATEGORY_BACKUP_RESTORE = 'BACKUP_RESTORE';
    public const CATEGORY_SECURITY_EVENT = 'SECURITY_EVENT';
    public const CATEGORY_COMPLIANCE = 'COMPLIANCE';
    
    public function __construct(?mysqli $connection = null, string $applicationId = 'SBL_VALIDATION_SYSTEM')
    {
        global $conn;
        $this->conn = $connection ?? $conn ?? DatabaseManager::getConnection();
        $this->applicationId = $applicationId;
        
        $this->initializeAuditTables();
    }
    
    /**
     * Registra una actividad de auditoría
     */
    public function logActivity(
        ?int $userId,
        string $action,
        array $details = [],
        ?string $ipAddress = null,
        string $level = self::LEVEL_INFO,
        string $category = self::CATEGORY_SYSTEM_ACCESS
    ): bool {
        try {
            $ipAddress = $ipAddress ?? $this->getClientIp();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $sessionId = session_id() ?: null;
            
            // Generar hash de integridad para el registro
            $integrityHash = $this->generateIntegrityHash([
                'user_id' => $userId,
                'action' => $action,
                'details' => $details,
                'ip_address' => $ipAddress,
                'timestamp' => date('Y-m-d H:i:s'),
                'application_id' => $this->applicationId
            ]);
            
            $stmt = $this->conn->prepare("
                INSERT INTO audit_logs (
                    user_id, action, details, ip_address, user_agent, 
                    session_id, level, category, application_id, 
                    integrity_hash, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $detailsJson = json_encode($details, JSON_UNESCAPED_UNICODE);
            
            $stmt->bind_param(
                'isssssssss',
                $userId, $action, $detailsJson, $ipAddress, $userAgent,
                $sessionId, $level, $category, $this->applicationId, $integrityHash
            );
            
            $result = $stmt->execute();
            
            // Log crítico: también escribir a archivo de respaldo
            if ($level === self::LEVEL_CRITICAL || $level === self::LEVEL_SECURITY) {
                $this->writeToSecurityLog($userId, $action, $details, $ipAddress, $level, $category);
            }
            
            return $result;
            
        } catch (Exception $e) {
            // En caso de error, escribir a log de emergencia
            $this->writeEmergencyLog($e->getMessage(), [
                'user_id' => $userId,
                'action' => $action,
                'details' => $details
            ]);
            return false;
        }
    }
    
    /**
     * Registra cambios en datos críticos con diff
     */
    public function logDataChange(
        int $userId,
        string $tableName,
        int $recordId,
        array $oldData,
        array $newData,
        string $reason = ''
    ): bool {
        $changes = $this->calculateChanges($oldData, $newData);
        
        return $this->logActivity(
            $userId,
            'DATA_CHANGE',
            [
                'table' => $tableName,
                'record_id' => $recordId,
                'changes' => $changes,
                'reason' => $reason,
                'change_count' => count($changes)
            ],
            null,
            self::LEVEL_INFO,
            self::CATEGORY_DATA_MODIFICATION
        );
    }
    
    /**
     * Registra eventos de seguridad
     */
    public function logSecurityEvent(
        string $eventType,
        ?int $userId = null,
        array $details = [],
        string $level = self::LEVEL_WARNING
    ): bool {
        return $this->logActivity(
            $userId,
            $eventType,
            array_merge($details, [
                'security_event' => true,
                'detection_time' => microtime(true)
            ]),
            null,
            $level,
            self::CATEGORY_SECURITY_EVENT
        );
    }
    
    /**
     * Registra intentos de acceso fallidos
     */
    public function logFailedLogin(
        string $username,
        string $reason,
        ?string $ipAddress = null
    ): bool {
        return $this->logSecurityEvent(
            'FAILED_LOGIN_ATTEMPT',
            null,
            [
                'username' => $username,
                'reason' => $reason,
                'ip_address' => $ipAddress ?? $this->getClientIp(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ],
            self::LEVEL_WARNING
        );
    }
    
    /**
     * Registra acceso exitoso
     */
    public function logSuccessfulLogin(int $userId, array $userInfo = []): bool
    {
        return $this->logActivity(
            $userId,
            'SUCCESSFUL_LOGIN',
            array_merge($userInfo, [
                'login_time' => date('Y-m-d H:i:s'),
                'session_id' => session_id()
            ]),
            null,
            self::LEVEL_INFO,
            self::CATEGORY_AUTHENTICATION
        );
    }
    
    /**
     * Registra cierre de sesión
     */
    public function logLogout(int $userId, string $logoutType = 'manual'): bool
    {
        return $this->logActivity(
            $userId,
            'LOGOUT',
            [
                'logout_type' => $logoutType,
                'session_duration' => $this->calculateSessionDuration($userId)
            ],
            null,
            self::LEVEL_INFO,
            self::CATEGORY_AUTHENTICATION
        );
    }
    
    /**
     * Obtiene logs de auditoría con filtros
     */
    public function getAuditLogs(
        array $filters = [],
        int $limit = 100,
        int $offset = 0
    ): array {
        $where = ['1=1'];
        $params = [];
        $types = '';
        
        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = ?';
            $params[] = $filters['user_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['action'])) {
            $where[] = 'action LIKE ?';
            $params[] = '%' . $filters['action'] . '%';
            $types .= 's';
        }
        
        if (!empty($filters['level'])) {
            $where[] = 'level = ?';
            $params[] = $filters['level'];
            $types .= 's';
        }
        
        if (!empty($filters['category'])) {
            $where[] = 'category = ?';
            $params[] = $filters['category'];
            $types .= 's';
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= ?';
            $params[] = $filters['date_from'];
            $types .= 's';
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= ?';
            $params[] = $filters['date_to'];
            $types .= 's';
        }
        
        $sql = "
            SELECT 
                al.*, 
                u.usuario as username,
                u.nombre,
                u.apellidos
            FROM audit_logs al
            LEFT JOIN usuarios u ON al.user_id = u.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY al.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $this->conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $row['details'] = json_decode($row['details'], true);
            $logs[] = $row;
        }
        
        return $logs;
    }
    
    /**
     * Verifica la integridad de los logs
     */
    public function verifyLogIntegrity(int $logId): bool
    {
        $stmt = $this->conn->prepare("
            SELECT user_id, action, details, ip_address, integrity_hash, created_at, application_id
            FROM audit_logs
            WHERE id = ?
        ");
        
        $stmt->bind_param('i', $logId);
        $stmt->execute();
        $result = $stmt->get_result();
        $log = $result->fetch_assoc();
        
        if (!$log) {
            return false;
        }
        
        $calculatedHash = $this->generateIntegrityHash([
            'user_id' => $log['user_id'],
            'action' => $log['action'],
            'details' => json_decode($log['details'], true),
            'ip_address' => $log['ip_address'],
            'timestamp' => $log['created_at'],
            'application_id' => $log['application_id']
        ]);
        
        return hash_equals($log['integrity_hash'], $calculatedHash);
    }
    
    /**
     * Genera reporte de auditoría para cumplimiento
     */
    public function generateComplianceReport(
        string $dateFrom,
        string $dateTo,
        array $categories = []
    ): array {
        $categoriesFilter = '';
        $params = [$dateFrom, $dateTo];
        $types = 'ss';
        
        if (!empty($categories)) {
            $placeholders = str_repeat('?,', count($categories) - 1) . '?';
            $categoriesFilter = " AND category IN ($placeholders)";
            $params = array_merge($params, $categories);
            $types .= str_repeat('s', count($categories));
        }
        
        $sql = "
            SELECT 
                category,
                level,
                COUNT(*) as event_count,
                COUNT(DISTINCT user_id) as unique_users,
                MIN(created_at) as first_event,
                MAX(created_at) as last_event
            FROM audit_logs
            WHERE created_at BETWEEN ? AND ?
            $categoriesFilter
            GROUP BY category, level
            ORDER BY category, level
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Inicializa las tablas de auditoría
     */
    private function initializeAuditTables(): void
    {
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS audit_logs (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                action VARCHAR(100) NOT NULL,
                details TEXT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                session_id VARCHAR(128),
                level ENUM('INFO', 'WARNING', 'ERROR', 'CRITICAL', 'SECURITY') DEFAULT 'INFO',
                category VARCHAR(50) DEFAULT 'SYSTEM_ACCESS',
                application_id VARCHAR(50) DEFAULT 'SBL_VALIDATION_SYSTEM',
                integrity_hash VARCHAR(64),
                created_at DATETIME NOT NULL,
                INDEX idx_user_id (user_id),
                INDEX idx_action (action),
                INDEX idx_level (level),
                INDEX idx_category (category),
                INDEX idx_created_at (created_at),
                INDEX idx_ip_address (ip_address),
                FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS audit_log_archive (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                original_id BIGINT NOT NULL,
                user_id INT NULL,
                action VARCHAR(100) NOT NULL,
                details TEXT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                session_id VARCHAR(128),
                level ENUM('INFO', 'WARNING', 'ERROR', 'CRITICAL', 'SECURITY') DEFAULT 'INFO',
                category VARCHAR(50) DEFAULT 'SYSTEM_ACCESS',
                application_id VARCHAR(50) DEFAULT 'SBL_VALIDATION_SYSTEM',
                integrity_hash VARCHAR(64),
                created_at DATETIME NOT NULL,
                archived_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_original_id (original_id),
                INDEX idx_archived_at (archived_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
    
    /**
     * Calcula los cambios entre dos conjuntos de datos
     */
    private function calculateChanges(array $oldData, array $newData): array
    {
        $changes = [];
        
        // Campos modificados
        foreach ($newData as $field => $newValue) {
            $oldValue = $oldData[$field] ?? null;
            if ($oldValue !== $newValue) {
                $changes[] = [
                    'field' => $field,
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                    'change_type' => isset($oldData[$field]) ? 'modified' : 'added'
                ];
            }
        }
        
        // Campos eliminados
        foreach ($oldData as $field => $oldValue) {
            if (!array_key_exists($field, $newData)) {
                $changes[] = [
                    'field' => $field,
                    'old_value' => $oldValue,
                    'new_value' => null,
                    'change_type' => 'removed'
                ];
            }
        }
        
        return $changes;
    }
    
    /**
     * Genera hash de integridad para el registro
     */
    private function generateIntegrityHash(array $data): string
    {
        ksort($data);
        $dataString = json_encode($data);
        return hash('sha256', $dataString . 'SBL_AUDIT_SALT_' . date('Y-m-d'));
    }
    
    /**
     * Calcula la duración de la sesión
     */
    private function calculateSessionDuration(int $userId): ?int
    {
        $stmt = $this->conn->prepare("
            SELECT created_at 
            FROM audit_logs 
            WHERE user_id = ? AND action = 'SUCCESSFUL_LOGIN' 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $loginTime = $result->fetch_assoc();
        
        if ($loginTime) {
            $loginTimestamp = strtotime($loginTime['created_at']);
            return time() - $loginTimestamp;
        }
        
        return null;
    }
    
    /**
     * Escribe en log de seguridad crítico
     */
    private function writeToSecurityLog(
        ?int $userId,
        string $action,
        array $details,
        string $ipAddress,
        string $level,
        string $category
    ): void {
        $logDir = __DIR__ . '/../../../../storage/security_logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0750, true);
        }
        
        $logFile = $logDir . '/security_' . date('Y-m-d') . '.log';
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $userId,
            'action' => $action,
            'details' => $details,
            'ip_address' => $ipAddress,
            'level' => $level,
            'category' => $category
        ];
        
        file_put_contents(
            $logFile,
            json_encode($logEntry) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
    
    /**
     * Escribe en log de emergencia
     */
    private function writeEmergencyLog(string $error, array $context): void
    {
        $logDir = __DIR__ . '/../../../../storage/emergency_logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0750, true);
        }
        
        $logFile = $logDir . '/emergency_' . date('Y-m-d') . '.log';
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'error' => $error,
            'context' => $context
        ];
        
        file_put_contents(
            $logFile,
            json_encode($logEntry) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
    
    /**
     * Obtiene la IP del cliente
     */
    private function getClientIp(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] 
            ?? $_SERVER['HTTP_X_REAL_IP'] 
            ?? $_SERVER['REMOTE_ADDR'] 
            ?? 'unknown';
    }
}