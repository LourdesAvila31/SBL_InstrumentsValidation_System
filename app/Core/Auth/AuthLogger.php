<?php

namespace App\Core\Auth;

use Exception;

/**
 * Logger especializado para eventos de autenticación y autorización
 */
class AuthLogger
{
    private string $logPath;
    private string $logLevel;
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->logPath = $this->config['log_path'];
        $this->logLevel = $this->config['log_level'];
        
        // Crear directorio de logs si no existe
        if (!is_dir(dirname($this->logPath))) {
            mkdir(dirname($this->logPath), 0755, true);
        }
    }

    /**
     * Registra un login exitoso
     */
    public function logSuccessfulLogin(string $username, string $sessionId): void
    {
        $this->log('INFO', 'LOGIN_SUCCESS', [
            'username' => $username,
            'session_id' => $sessionId,
            'ip_address' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    }

    /**
     * Registra un intento de login fallido
     */
    public function logFailedLogin(string $username, string $reason): void
    {
        $this->log('WARNING', 'LOGIN_FAILED', [
            'username' => $username,
            'reason' => $reason,
            'ip_address' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    }

    /**
     * Registra un logout
     */
    public function logLogout($userId, string $sessionId): void
    {
        $this->log('INFO', 'LOGOUT', [
            'user_id' => $userId,
            'session_id' => $sessionId,
            'ip_address' => $this->getClientIp()
        ]);
    }

    /**
     * Registra acceso no autorizado
     */
    public function logUnauthorizedAccess($userId, string $resource, array $context = []): void
    {
        $this->log('WARNING', 'UNAUTHORIZED_ACCESS', [
            'user_id' => $userId,
            'resource' => $resource,
            'context' => $context,
            'ip_address' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    }

    /**
     * Registra inicio de impersonación
     */
    public function logImpersonation($originalUserId, $targetUserId): void
    {
        $this->log('WARNING', 'IMPERSONATION_START', [
            'original_user_id' => $originalUserId,
            'target_user_id' => $targetUserId,
            'ip_address' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    }

    /**
     * Registra fin de impersonación
     */
    public function logStopImpersonation($originalUserId): void
    {
        $this->log('INFO', 'IMPERSONATION_STOP', [
            'original_user_id' => $originalUserId,
            'ip_address' => $this->getClientIp()
        ]);
    }

    /**
     * Registra cambios de roles o permisos
     */
    public function logPermissionChange($userId, string $action, string $permission, array $context = []): void
    {
        $this->log('INFO', 'PERMISSION_CHANGE', [
            'user_id' => $userId,
            'action' => $action, // 'grant', 'revoke'
            'permission' => $permission,
            'context' => $context,
            'changed_by' => $_SESSION['usuario_id'] ?? 'system'
        ]);
    }

    /**
     * Registra cambios de roles
     */
    public function logRoleChange($userId, string $action, string $role): void
    {
        $this->log('INFO', 'ROLE_CHANGE', [
            'user_id' => $userId,
            'action' => $action, // 'assign', 'revoke'
            'role' => $role,
            'changed_by' => $_SESSION['usuario_id'] ?? 'system'
        ]);
    }

    /**
     * Registra errores del sistema de autenticación
     */
    public function logError(string $message, array $context = []): void
    {
        $this->log('ERROR', 'AUTH_ERROR', [
            'message' => $message,
            'context' => $context,
            'ip_address' => $this->getClientIp(),
            'trace' => $this->config['include_trace'] ? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5) : null
        ]);
    }

    /**
     * Registra eventos de seguridad críticos
     */
    public function logSecurityEvent(string $eventType, array $data): void
    {
        $this->log('CRITICAL', 'SECURITY_EVENT', [
            'event_type' => $eventType,
            'data' => $data,
            'ip_address' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    }

    /**
     * Registra actividad de sesiones
     */
    public function logSessionActivity(string $sessionId, string $activity, array $data = []): void
    {
        $this->log('DEBUG', 'SESSION_ACTIVITY', [
            'session_id' => $sessionId,
            'activity' => $activity,
            'data' => $data,
            'ip_address' => $this->getClientIp()
        ]);
    }

    /**
     * Método principal de logging
     */
    private function log(string $level, string $event, array $data): void
    {
        try {
            // Verificar si el nivel debe ser registrado
            if (!$this->shouldLog($level)) {
                return;
            }

            $logEntry = [
                'timestamp' => date('Y-m-d H:i:s'),
                'level' => $level,
                'event' => $event,
                'data' => $data,
                'request_id' => $this->getRequestId()
            ];

            // Formatear según configuración
            $formattedEntry = $this->formatLogEntry($logEntry);

            // Escribir al archivo
            $this->writeToFile($formattedEntry);

            // Enviar a base de datos si está configurado
            if ($this->config['database_logging']) {
                $this->writeToDatabase($logEntry);
            }

            // Enviar alertas críticas si es necesario
            if ($level === 'CRITICAL' && $this->config['alert_on_critical']) {
                $this->sendCriticalAlert($logEntry);
            }

        } catch (Exception $e) {
            // Fallar silenciosamente para no interrumpir el flujo de la aplicación
            error_log("AuthLogger error: " . $e->getMessage());
        }
    }

    /**
     * Verifica si un nivel debe ser registrado
     */
    private function shouldLog(string $level): bool
    {
        $levels = ['DEBUG' => 0, 'INFO' => 1, 'WARNING' => 2, 'ERROR' => 3, 'CRITICAL' => 4];
        $currentLevel = $levels[$this->logLevel] ?? 1;
        $messageLevel = $levels[$level] ?? 1;

        return $messageLevel >= $currentLevel;
    }

    /**
     * Formatea una entrada de log
     */
    private function formatLogEntry(array $entry): string
    {
        switch ($this->config['format']) {
            case 'json':
                return json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
            
            case 'structured':
                return sprintf(
                    "[%s] %s.%s: %s\n",
                    $entry['timestamp'],
                    $entry['level'],
                    $entry['event'],
                    json_encode($entry['data'])
                );
            
            default: // 'simple'
                return sprintf(
                    "[%s] %s: %s - %s\n",
                    $entry['timestamp'],
                    $entry['level'],
                    $entry['event'],
                    $this->formatDataForSimpleLog($entry['data'])
                );
        }
    }

    /**
     * Formatea datos para log simple
     */
    private function formatDataForSimpleLog(array $data): string
    {
        $parts = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $parts[] = "$key=$value";
        }
        return implode(', ', $parts);
    }

    /**
     * Escribe al archivo de log
     */
    private function writeToFile(string $formattedEntry): void
    {
        // Rotar archivo si es necesario
        if ($this->config['rotate_logs'] && file_exists($this->logPath)) {
            $fileSize = filesize($this->logPath);
            if ($fileSize > $this->config['max_file_size']) {
                $this->rotateLogFile();
            }
        }

        file_put_contents($this->logPath, $formattedEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Rota el archivo de log
     */
    private function rotateLogFile(): void
    {
        $backupPath = $this->logPath . '.' . date('Y-m-d-H-i-s');
        rename($this->logPath, $backupPath);

        // Limpiar archivos antiguos
        $this->cleanupOldLogFiles();
    }

    /**
     * Limpia archivos de log antiguos
     */
    private function cleanupOldLogFiles(): void
    {
        $logDir = dirname($this->logPath);
        $logBasename = basename($this->logPath);
        $files = glob($logDir . '/' . $logBasename . '.*');
        
        if (count($files) > $this->config['max_backup_files']) {
            // Ordenar por fecha y eliminar los más antiguos
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            $filesToDelete = array_slice($files, 0, count($files) - $this->config['max_backup_files']);
            foreach ($filesToDelete as $file) {
                unlink($file);
            }
        }
    }

    /**
     * Escribe al log de base de datos
     */
    private function writeToDatabase(array $entry): void
    {
        try {
            // Implementar según la configuración de base de datos
            // Por ahora, método stub
        } catch (Exception $e) {
            error_log("Database logging error: " . $e->getMessage());
        }
    }

    /**
     * Envía alerta crítica
     */
    private function sendCriticalAlert(array $entry): void
    {
        // Implementar notificaciones críticas (email, SMS, Slack, etc.)
        // Por ahora, método stub
    }

    /**
     * Obtiene la IP del cliente
     */
    private function getClientIp(): string
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Tomar la primera IP si hay múltiples
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Genera o recupera un ID único para la petición
     */
    private function getRequestId(): string
    {
        static $requestId = null;
        
        if ($requestId === null) {
            $requestId = uniqid('req_', true);
        }
        
        return $requestId;
    }

    /**
     * Configuración por defecto
     */
    private function getDefaultConfig(): array
    {
        return [
            'log_path' => __DIR__ . '/../../../../storage/logs/auth.log',
            'log_level' => 'INFO',
            'format' => 'structured', // 'simple', 'structured', 'json'
            'rotate_logs' => true,
            'max_file_size' => 10 * 1024 * 1024, // 10MB
            'max_backup_files' => 5,
            'database_logging' => false,
            'alert_on_critical' => false,
            'include_trace' => false
        ];
    }

    /**
     * Obtiene estadísticas de logs de autenticación
     */
    public function getStats(int $days = 7): array
    {
        // Implementar análisis de logs para estadísticas
        return [
            'total_logins' => 0,
            'failed_logins' => 0,
            'unique_users' => 0,
            'unique_ips' => 0,
            'security_events' => 0,
            'period_days' => $days
        ];
    }
}