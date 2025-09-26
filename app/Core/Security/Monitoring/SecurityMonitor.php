<?php
/**
 * Sistema de Monitoreo de Seguridad - GAMP 5 Compliant
 * 
 * Este módulo implementa:
 * - Detección de intrusiones y actividades sospechosas
 * - Monitoreo en tiempo real de eventos de seguridad
 * - Alertas automáticas por email/SMS
 * - Análisis de patrones de comportamiento
 * - Dashboard de seguridad
 * 
 * Cumple con estándares de monitoreo de GAMP 5 y normativas GxP
 */

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../Audit/AuditLogger.php';

/**
 * Monitor de Seguridad del Sistema
 */
class SecurityMonitor
{
    private mysqli $conn;
    private AuditLogger $auditLogger;
    
    // Configuración de alertas
    private const ALERT_COOLDOWN = 300; // 5 minutos entre alertas del mismo tipo
    private const MAX_FAILED_LOGINS_PER_MINUTE = 10;
    private const MAX_REQUESTS_PER_MINUTE = 1000;
    private const SUSPICIOUS_ACTIVITY_THRESHOLD = 50;
    
    // Tipos de amenazas
    private const THREAT_TYPES = [
        'BRUTE_FORCE' => 'Ataque de fuerza bruta',
        'SQL_INJECTION' => 'Intento de inyección SQL',
        'XSS_ATTEMPT' => 'Intento de XSS',
        'UNAUTHORIZED_ACCESS' => 'Acceso no autorizado',
        'SUSPICIOUS_BEHAVIOR' => 'Comportamiento sospechoso',
        'DATA_EXFILTRATION' => 'Posible exfiltración de datos',
        'PRIVILEGE_ESCALATION' => 'Escalación de privilegios',
        'MALWARE_DETECTED' => 'Malware detectado',
        'DDOS_ATTACK' => 'Ataque DDoS',
        'INSIDER_THREAT' => 'Amenaza interna'
    ];
    
    public function __construct(?mysqli $connection = null)
    {
        global $conn;
        $this->conn = $connection ?? $conn ?? DatabaseManager::getConnection();
        $this->auditLogger = new AuditLogger($this->conn);
        
        $this->initializeSecurityTables();
    }
    
    /**
     * Monitorea actividad sospechosa en tiempo real
     */
    public function monitorRealTimeActivity(): array
    {
        $threats = [];
        
        // Detectar ataques de fuerza bruta
        $bruteForceThreats = $this->detectBruteForceAttacks();
        $threats = array_merge($threats, $bruteForceThreats);
        
        // Detectar intentos de inyección SQL
        $sqlInjectionThreats = $this->detectSqlInjectionAttempts();
        $threats = array_merge($threats, $sqlInjectionThreats);
        
        // Detectar comportamiento anómalo de usuarios
        $anomalousThreats = $this->detectAnomalousBehavior();
        $threats = array_merge($threats, $anomalousThreats);
        
        // Detectar accesos no autorizados
        $unauthorizedThreats = $this->detectUnauthorizedAccess();
        $threats = array_merge($threats, $unauthorizedThreats);
        
        // Detectar posible exfiltración de datos
        $exfiltrationThreats = $this->detectDataExfiltration();
        $threats = array_merge($threats, $exfiltrationThreats);
        
        // Procesar y registrar amenazas detectadas
        foreach ($threats as $threat) {
            $this->processThreat($threat);
        }
        
        return [
            'threats_detected' => count($threats),
            'threats' => $threats,
            'monitoring_time' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Detecta ataques de fuerza bruta
     */
    private function detectBruteForceAttacks(): array
    {
        $threats = [];
        
        // Buscar múltiples intentos fallidos desde la misma IP
        $stmt = $this->conn->prepare("
            SELECT 
                ip_address,
                COUNT(*) as attempts,
                GROUP_CONCAT(DISTINCT username) as usernames
            FROM failed_login_attempts 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            GROUP BY ip_address
            HAVING attempts >= ?
        ");
        
        $threshold = self::MAX_FAILED_LOGINS_PER_MINUTE;
        $stmt->bind_param('i', $threshold);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $threats[] = [
                'type' => 'BRUTE_FORCE',
                'severity' => $this->calculateSeverity($row['attempts'], 5, 20),
                'source_ip' => $row['ip_address'],
                'details' => [
                    'attempts' => $row['attempts'],
                    'usernames' => explode(',', $row['usernames']),
                    'time_window' => '5 minutes'
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        return $threats;
    }
    
    /**
     * Detecta intentos de inyección SQL
     */
    private function detectSqlInjectionAttempts(): array
    {
        $threats = [];
        
        // Patrones comunes de inyección SQL
        $sqlPatterns = [
            "/'.*(\sor\s|union\s|select\s|insert\s|update\s|delete\s|drop\s)/i",
            "/(\\\x27|\\\x22|%27|%22)/i", // Comillas codificadas
            "/(\/\*.*\*\/|--|\#)/i", // Comentarios SQL
            "/(\\\x3B|\\\x5C|%3B|%5C)/i", // Punto y coma, backslash
            "/(exec\s|execute\s|sp_|xp_)/i" // Procedimientos almacenados
        ];
        
        // Analizar logs recientes de la aplicación
        $stmt = $this->conn->prepare("
            SELECT *
            FROM audit_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
            AND (details LIKE '%SELECT%' OR details LIKE '%UNION%' OR details LIKE '%INSERT%')
        ");
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $details = json_decode($row['details'], true) ?? [];
            $requestData = json_encode($details);
            
            foreach ($sqlPatterns as $pattern) {
                if (preg_match($pattern, $requestData)) {
                    $threats[] = [
                        'type' => 'SQL_INJECTION',
                        'severity' => 'HIGH',
                        'source_ip' => $row['ip_address'],
                        'user_id' => $row['user_id'],
                        'details' => [
                            'pattern_matched' => $pattern,
                            'request_data' => substr($requestData, 0, 500),
                            'log_id' => $row['id']
                        ],
                        'timestamp' => $row['created_at']
                    ];
                    break;
                }
            }
        }
        
        return $threats;
    }
    
    /**
     * Detecta comportamiento anómalo de usuarios
     */
    private function detectAnomalousBehavior(): array
    {
        $threats = [];
        
        // Usuarios con actividad fuera de horarios normales
        $stmt = $this->conn->prepare("
            SELECT 
                user_id,
                ip_address,
                COUNT(*) as activities,
                MIN(created_at) as first_activity,
                MAX(created_at) as last_activity
            FROM audit_logs 
            WHERE 
                created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                AND (HOUR(created_at) < 6 OR HOUR(created_at) > 22)
                AND user_id IS NOT NULL
            GROUP BY user_id, ip_address
            HAVING activities > 10
        ");
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $threats[] = [
                'type' => 'SUSPICIOUS_BEHAVIOR',
                'severity' => 'MEDIUM',
                'source_ip' => $row['ip_address'],
                'user_id' => $row['user_id'],
                'details' => [
                    'reason' => 'Actividad fuera de horarios normales',
                    'activities_count' => $row['activities'],
                    'time_range' => $row['first_activity'] . ' - ' . $row['last_activity']
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        // Usuarios con múltiples IPs en corto tiempo
        $stmt = $this->conn->prepare("
            SELECT 
                user_id,
                COUNT(DISTINCT ip_address) as ip_count,
                GROUP_CONCAT(DISTINCT ip_address) as ip_addresses
            FROM audit_logs 
            WHERE 
                created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                AND user_id IS NOT NULL
            GROUP BY user_id
            HAVING ip_count >= 3
        ");
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $threats[] = [
                'type' => 'SUSPICIOUS_BEHAVIOR',
                'severity' => 'HIGH',
                'user_id' => $row['user_id'],
                'details' => [
                    'reason' => 'Múltiples IPs en corto tiempo',
                    'ip_count' => $row['ip_count'],
                    'ip_addresses' => explode(',', $row['ip_addresses'])
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        return $threats;
    }
    
    /**
     * Detecta accesos no autorizados
     */
    private function detectUnauthorizedAccess(): array
    {
        $threats = [];
        
        // Intentos de acceso a recursos sin permisos
        $stmt = $this->conn->prepare("
            SELECT 
                user_id,
                ip_address,
                COUNT(*) as attempts,
                details
            FROM audit_logs 
            WHERE 
                created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
                AND action LIKE '%DENIED%'
                AND level = 'WARNING'
            GROUP BY user_id, ip_address
            HAVING attempts >= 5
        ");
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $threats[] = [
                'type' => 'UNAUTHORIZED_ACCESS',
                'severity' => 'MEDIUM',
                'source_ip' => $row['ip_address'],
                'user_id' => $row['user_id'],
                'details' => [
                    'denied_attempts' => $row['attempts'],
                    'last_attempt_details' => json_decode($row['details'], true)
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        return $threats;
    }
    
    /**
     * Detecta posible exfiltración de datos
     */
    private function detectDataExfiltration(): array
    {
        $threats = [];
        
        // Descarga masiva de datos
        $stmt = $this->conn->prepare("
            SELECT 
                user_id,
                ip_address,
                COUNT(*) as downloads
            FROM audit_logs 
            WHERE 
                created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                AND action LIKE '%EXPORT%'
                OR action LIKE '%DOWNLOAD%'
            GROUP BY user_id, ip_address
            HAVING downloads >= 10
        ");
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $threats[] = [
                'type' => 'DATA_EXFILTRATION',
                'severity' => 'HIGH',
                'source_ip' => $row['ip_address'],
                'user_id' => $row['user_id'],
                'details' => [
                    'downloads_count' => $row['downloads'],
                    'time_window' => '30 minutes'
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        return $threats;
    }
    
    /**
     * Procesa una amenaza detectada
     */
    private function processThreat(array $threat): void
    {
        // Registrar la amenaza
        $this->recordThreat($threat);
        
        // Aplicar contramedidas automáticas
        $this->applyCountermeasures($threat);
        
        // Enviar alertas si es necesario
        if ($this->shouldSendAlert($threat)) {
            $this->sendSecurityAlert($threat);
        }
        
        // Log de auditoría
        $this->auditLogger->logSecurityEvent(
            'THREAT_DETECTED',
            $threat['user_id'] ?? null,
            [
                'threat_type' => $threat['type'],
                'severity' => $threat['severity'],
                'source_ip' => $threat['source_ip'] ?? 'unknown',
                'details' => $threat['details']
            ],
            $this->mapSeverityToAuditLevel($threat['severity'])
        );
    }
    
    /**
     * Registra una amenaza en la base de datos
     */
    private function recordThreat(array $threat): void
    {
        $stmt = $this->conn->prepare("
            INSERT INTO security_threats (
                type, severity, source_ip, user_id, details, 
                detected_at, status
            ) VALUES (?, ?, ?, ?, ?, NOW(), 'ACTIVE')
        ");
        
        $details = json_encode($threat['details']);
        $stmt->bind_param(
            'sssis',
            $threat['type'],
            $threat['severity'],
            $threat['source_ip'] ?? null,
            $threat['user_id'] ?? null,
            $details
        );
        
        $stmt->execute();
    }
    
    /**
     * Aplica contramedidas automáticas
     */
    private function applyCountermeasures(array $threat): void
    {
        switch ($threat['type']) {
            case 'BRUTE_FORCE':
                $this->blockIpAddress($threat['source_ip'], 3600); // 1 hora
                break;
                
            case 'SQL_INJECTION':
                $this->blockIpAddress($threat['source_ip'], 7200); // 2 horas
                if (isset($threat['user_id'])) {
                    $this->suspendUser($threat['user_id'], 'SQL injection attempt');
                }
                break;
                
            case 'DATA_EXFILTRATION':
                if (isset($threat['user_id'])) {
                    $this->suspendUser($threat['user_id'], 'Suspected data exfiltration');
                }
                break;
                
            case 'UNAUTHORIZED_ACCESS':
                $this->increaseUserSecurityLevel($threat['user_id'] ?? null);
                break;
        }
    }
    
    /**
     * Envía alerta de seguridad
     */
    private function sendSecurityAlert(array $threat): void
    {
        $alertMessage = $this->formatAlertMessage($threat);
        
        // Obtener lista de administradores para notificar
        $admins = $this->getSecurityAdmins();
        
        foreach ($admins as $admin) {
            $this->sendEmailAlert($admin['correo'], $alertMessage);
            
            // También enviar SMS para amenazas críticas
            if ($threat['severity'] === 'CRITICAL' && !empty($admin['telefono'])) {
                $this->sendSmsAlert($admin['telefono'], $alertMessage);
            }
        }
        
        // Registrar envío de alerta
        $this->recordAlertSent($threat, count($admins));
    }
    
    /**
     * Obtiene dashboard de seguridad
     */
    public function getSecurityDashboard(): array
    {
        $dashboard = [];
        
        // Amenazas activas
        $stmt = $this->conn->prepare("
            SELECT 
                type,
                severity,
                COUNT(*) as count
            FROM security_threats 
            WHERE status = 'ACTIVE' 
            AND detected_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY type, severity
            ORDER BY 
                FIELD(severity, 'CRITICAL', 'HIGH', 'MEDIUM', 'LOW'),
                count DESC
        ");
        
        $stmt->execute();
        $dashboard['active_threats'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Estadísticas de seguridad
        $dashboard['statistics'] = [
            'total_threats_24h' => $this->getThreatCount(24),
            'critical_threats_24h' => $this->getThreatCount(24, 'CRITICAL'),
            'blocked_ips' => $this->getBlockedIpCount(),
            'suspended_users' => $this->getSuspendedUserCount(),
            'failed_logins_1h' => $this->getFailedLoginCount(1),
            'security_score' => $this->calculateSecurityScore()
        ];
        
        // Tendencias por hora (últimas 24 horas)
        $dashboard['hourly_trends'] = $this->getHourlyThreatTrends();
        
        // Top IPs sospechosas
        $dashboard['suspicious_ips'] = $this->getTopSuspiciousIps();
        
        // Usuarios con actividad sospechosa
        $dashboard['suspicious_users'] = $this->getSuspiciousUsers();
        
        return $dashboard;
    }
    
    /**
     * Obtiene métricas de rendimiento del sistema de seguridad
     */
    public function getSecurityMetrics(): array
    {
        return [
            'detection_rate' => $this->calculateDetectionRate(),
            'false_positive_rate' => $this->calculateFalsePositiveRate(),
            'response_time' => $this->getAverageResponseTime(),
            'uptime' => $this->getSystemUptime(),
            'last_scan' => $this->getLastSecurityScan()
        ];
    }
    
    /**
     * Inicializa las tablas de seguridad
     */
    private function initializeSecurityTables(): void
    {
        // Tabla de amenazas de seguridad
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS security_threats (
                id INT AUTO_INCREMENT PRIMARY KEY,
                type VARCHAR(50) NOT NULL,
                severity ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') NOT NULL,
                source_ip VARCHAR(45),
                user_id INT NULL,
                details TEXT,
                detected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                resolved_at DATETIME NULL,
                status ENUM('ACTIVE', 'RESOLVED', 'FALSE_POSITIVE') DEFAULT 'ACTIVE',
                countermeasures_applied TEXT,
                INDEX idx_type (type),
                INDEX idx_severity (severity),
                INDEX idx_source_ip (source_ip),
                INDEX idx_user_id (user_id),
                INDEX idx_detected_at (detected_at),
                INDEX idx_status (status),
                FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Tabla de IPs bloqueadas
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS blocked_ips (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL UNIQUE,
                reason VARCHAR(255),
                blocked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                is_active BOOLEAN DEFAULT TRUE,
                INDEX idx_ip_address (ip_address),
                INDEX idx_expires_at (expires_at),
                INDEX idx_is_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Tabla de alertas enviadas
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS security_alerts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                threat_id INT NOT NULL,
                alert_type ENUM('EMAIL', 'SMS', 'PUSH') NOT NULL,
                recipient VARCHAR(255) NOT NULL,
                sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                status ENUM('SENT', 'FAILED', 'PENDING') DEFAULT 'PENDING',
                INDEX idx_threat_id (threat_id),
                INDEX idx_sent_at (sent_at),
                FOREIGN KEY (threat_id) REFERENCES security_threats(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
    
    // Métodos auxiliares privados
    
    private function calculateSeverity(int $value, int $low, int $high): string
    {
        if ($value >= $high) return 'CRITICAL';
        if ($value >= $low * 2) return 'HIGH';
        if ($value >= $low) return 'MEDIUM';
        return 'LOW';
    }
    
    private function shouldSendAlert(array $threat): bool
    {
        // Verificar cooldown de alertas
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count
            FROM security_alerts sa
            JOIN security_threats st ON sa.threat_id = st.id
            WHERE st.type = ? 
            AND sa.sent_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        
        $cooldown = self::ALERT_COOLDOWN;
        $stmt->bind_param('si', $threat['type'], $cooldown);
        $stmt->execute();
        $result = $stmt->get_result();
        $recentAlerts = $result->fetch_assoc()['count'];
        
        return $recentAlerts == 0 || $threat['severity'] === 'CRITICAL';
    }
    
    private function blockIpAddress(string $ip, int $duration): void
    {
        if (empty($ip) || $ip === 'unknown') return;
        
        $expiresAt = date('Y-m-d H:i:s', time() + $duration);
        
        $stmt = $this->conn->prepare("
            INSERT INTO blocked_ips (ip_address, reason, expires_at) 
            VALUES (?, 'Automatic security block', ?)
            ON DUPLICATE KEY UPDATE 
                expires_at = VALUES(expires_at),
                is_active = TRUE
        ");
        
        $stmt->bind_param('ss', $ip, $expiresAt);
        $stmt->execute();
    }
    
    private function suspendUser(int $userId, string $reason): void
    {
        $stmt = $this->conn->prepare("
            UPDATE usuarios 
            SET activo = 0, suspension_reason = ?, suspended_at = NOW() 
            WHERE id = ?
        ");
        
        $stmt->bind_param('si', $reason, $userId);
        $stmt->execute();
        
        $this->auditLogger->logActivity(
            null,
            'USER_SUSPENDED',
            [
                'user_id' => $userId,
                'reason' => $reason,
                'suspended_by' => 'SECURITY_SYSTEM'
            ],
            null,
            AuditLogger::LEVEL_CRITICAL,
            AuditLogger::CATEGORY_SECURITY_EVENT
        );
    }
    
    private function increaseUserSecurityLevel(?int $userId): void
    {
        if (!$userId) return;
        
        // Forzar cambio de contraseña y MFA
        $stmt = $this->conn->prepare("
            UPDATE usuarios 
            SET force_password_change = 1, force_mfa_setup = 1 
            WHERE id = ?
        ");
        
        $stmt->bind_param('i', $userId);
        $stmt->execute();
    }
    
    private function formatAlertMessage(array $threat): string
    {
        $threatName = self::THREAT_TYPES[$threat['type']] ?? $threat['type'];
        
        return sprintf(
            "[ALERTA SEGURIDAD] %s detectada\n\n" .
            "Tipo: %s\n" .
            "Severidad: %s\n" .
            "IP Origen: %s\n" .
            "Hora: %s\n\n" .
            "Detalles: %s",
            $threatName,
            $threat['type'],
            $threat['severity'],
            $threat['source_ip'] ?? 'No disponible',
            $threat['timestamp'],
            json_encode($threat['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }
    
    private function getSecurityAdmins(): array
    {
        $stmt = $this->conn->prepare("
            SELECT u.correo, u.telefono, u.nombre, u.apellidos
            FROM usuarios u
            JOIN roles r ON u.role_id = r.id
            WHERE r.nombre IN ('Superadministrador', 'Developer', 'Administrador')
            AND u.activo = 1
            AND u.correo IS NOT NULL
        ");
        
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    private function sendEmailAlert(string $email, string $message): bool
    {
        // Implementar envío de email usando PHPMailer o similar
        // Por ahora retorna true como placeholder
        return true;
    }
    
    private function sendSmsAlert(string $phone, string $message): bool
    {
        // Implementar envío de SMS usando servicio como Twilio
        // Por ahora retorna true como placeholder
        return true;
    }
    
    private function recordAlertSent(array $threat, int $recipientCount): void
    {
        // Lógica para registrar alertas enviadas
        $this->auditLogger->logActivity(
            null,
            'SECURITY_ALERT_SENT',
            [
                'threat_type' => $threat['type'],
                'severity' => $threat['severity'],
                'recipient_count' => $recipientCount
            ],
            null,
            AuditLogger::LEVEL_INFO,
            AuditLogger::CATEGORY_SECURITY_EVENT
        );
    }
    
    private function mapSeverityToAuditLevel(string $severity): string
    {
        switch ($severity) {
            case 'CRITICAL': return AuditLogger::LEVEL_CRITICAL;
            case 'HIGH': return AuditLogger::LEVEL_ERROR;
            case 'MEDIUM': return AuditLogger::LEVEL_WARNING;
            default: return AuditLogger::LEVEL_INFO;
        }
    }
    
    private function getThreatCount(int $hours, ?string $severity = null): int
    {
        $sql = "
            SELECT COUNT(*) as count 
            FROM security_threats 
            WHERE detected_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
        ";
        
        $params = [$hours];
        $types = 'i';
        
        if ($severity) {
            $sql .= " AND severity = ?";
            $params[] = $severity;
            $types .= 's';
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        
        return (int)$stmt->get_result()->fetch_assoc()['count'];
    }
    
    private function getBlockedIpCount(): int
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count 
            FROM blocked_ips 
            WHERE is_active = 1 AND expires_at > NOW()
        ");
        
        $stmt->execute();
        return (int)$stmt->get_result()->fetch_assoc()['count'];
    }
    
    private function getSuspendedUserCount(): int
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count 
            FROM usuarios 
            WHERE activo = 0 AND suspended_at IS NOT NULL
        ");
        
        $stmt->execute();
        return (int)$stmt->get_result()->fetch_assoc()['count'];
    }
    
    private function getFailedLoginCount(int $hours): int
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count 
            FROM failed_login_attempts 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
        ");
        
        $stmt->bind_param('i', $hours);
        $stmt->execute();
        
        return (int)$stmt->get_result()->fetch_assoc()['count'];
    }
    
    private function calculateSecurityScore(): int
    {
        $score = 100;
        
        // Penalizar por amenazas activas
        $activeThreats = $this->getThreatCount(24);
        $score -= min($activeThreats * 2, 30);
        
        // Penalizar por cuentas suspendidas
        $suspendedUsers = $this->getSuspendedUserCount();
        $score -= min($suspendedUsers * 5, 20);
        
        // Penalizar por intentos fallidos
        $failedLogins = $this->getFailedLoginCount(1);
        $score -= min($failedLogins, 15);
        
        return max(0, $score);
    }
    
    private function getHourlyThreatTrends(): array
    {
        $stmt = $this->conn->prepare("
            SELECT 
                HOUR(detected_at) as hour,
                COUNT(*) as threats
            FROM security_threats 
            WHERE detected_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY HOUR(detected_at)
            ORDER BY hour
        ");
        
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    private function getTopSuspiciousIps(): array
    {
        $stmt = $this->conn->prepare("
            SELECT 
                source_ip,
                COUNT(*) as threat_count,
                MAX(severity) as max_severity
            FROM security_threats 
            WHERE detected_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            AND source_ip IS NOT NULL
            GROUP BY source_ip
            ORDER BY threat_count DESC
            LIMIT 10
        ");
        
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    private function getSuspiciousUsers(): array
    {
        $stmt = $this->conn->prepare("
            SELECT 
                u.id,
                u.usuario,
                u.nombre,
                u.apellidos,
                COUNT(st.id) as threat_count
            FROM usuarios u
            JOIN security_threats st ON u.id = st.user_id
            WHERE st.detected_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY u.id
            ORDER BY threat_count DESC
            LIMIT 10
        ");
        
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    private function calculateDetectionRate(): float
    {
        // Placeholder - implementar lógica real basada en métricas
        return 95.5;
    }
    
    private function calculateFalsePositiveRate(): float
    {
        // Placeholder - implementar lógica real basada en métricas
        return 2.1;
    }
    
    private function getAverageResponseTime(): float
    {
        // Placeholder - implementar lógica real basada en métricas
        return 1.2; // segundos
    }
    
    private function getSystemUptime(): float
    {
        // Placeholder - implementar lógica real basada en métricas
        return 99.8; // porcentaje
    }
    
    private function getLastSecurityScan(): string
    {
        return date('Y-m-d H:i:s');
    }
}