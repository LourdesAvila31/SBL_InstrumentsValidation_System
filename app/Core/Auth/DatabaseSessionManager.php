<?php

namespace App\Core\Auth;

use Exception;

/**
 * Implementación de base de datos para gestión de sesiones
 */
class DatabaseSessionManager implements SessionManagerInterface
{
    private \PDO $pdo;
    private AuthLogger $logger;
    private array $config;

    public function __construct(\PDO $pdo, AuthLogger $logger, array $config = [])
    {
        $this->pdo = $pdo;
        $this->logger = $logger;
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Inicia una nueva sesión
     */
    public function startSession($userId, array $metadata = []): string
    {
        try {
            $sessionId = $this->generateSessionId();
            $ipAddress = $this->getClientIp();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $expiresAt = new \DateTime();
            $expiresAt->add(new \DateInterval('PT' . $this->config['session_lifetime'] . 'S'));

            $stmt = $this->pdo->prepare("
                INSERT INTO user_sessions (
                    session_id, user_id, ip_address, user_agent, metadata, 
                    created_at, updated_at, expires_at
                ) VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?)
            ");

            $metadataJson = !empty($metadata) ? json_encode($metadata) : null;
            
            $stmt->execute([
                $sessionId,
                $userId,
                $ipAddress,
                $userAgent,
                $metadataJson,
                $expiresAt->format('Y-m-d H:i:s')
            ]);

            // Configurar sesión PHP
            session_id($sessionId);
            session_start();
            
            $_SESSION['usuario_id'] = $userId;
            $_SESSION['session_started'] = time();
            $_SESSION['last_activity'] = time();

            $this->logger->logSessionActivity($sessionId, 'session_started', [
                'user_id' => $userId,
                'ip_address' => $ipAddress,
                'metadata' => $metadata
            ]);

            return $sessionId;

        } catch (Exception $e) {
            $this->logger->logError("Failed to start session for user $userId", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Valida una sesión existente
     */
    public function validateSession(string $sessionId): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT user_id, expires_at, ip_address, updated_at 
                FROM user_sessions 
                WHERE session_id = ? AND expires_at > NOW()
            ");
            
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$session) {
                return false;
            }

            // Verificar IP si está configurado
            if ($this->config['check_ip'] && $session['ip_address'] !== $this->getClientIp()) {
                $this->logger->logSecurityEvent('IP_MISMATCH', [
                    'session_id' => $sessionId,
                    'stored_ip' => $session['ip_address'],
                    'current_ip' => $this->getClientIp()
                ]);
                return false;
            }

            // Verificar inactividad
            $lastActivity = new \DateTime($session['updated_at']);
            $inactiveTime = time() - $lastActivity->getTimestamp();
            
            if ($inactiveTime > $this->config['max_inactive_time']) {
                $this->destroySession($sessionId);
                return false;
            }

            // Actualizar última actividad
            $this->updateSessionActivity($sessionId);

            return true;

        } catch (Exception $e) {
            $this->logger->logError("Failed to validate session $sessionId", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Actualiza la actividad de la sesión
     */
    public function updateSessionActivity(string $sessionId): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE user_sessions 
                SET updated_at = NOW() 
                WHERE session_id = ?
            ");
            
            return $stmt->execute([$sessionId]);

        } catch (Exception $e) {
            $this->logger->logError("Failed to update session activity for $sessionId", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Renueva una sesión (extiende su tiempo de vida)
     */
    public function renewSession(string $sessionId): bool
    {
        try {
            $expiresAt = new \DateTime();
            $expiresAt->add(new \DateInterval('PT' . $this->config['session_lifetime'] . 'S'));

            $stmt = $this->pdo->prepare("
                UPDATE user_sessions 
                SET expires_at = ?, updated_at = NOW() 
                WHERE session_id = ?
            ");
            
            $result = $stmt->execute([$expiresAt->format('Y-m-d H:i:s'), $sessionId]);

            if ($result) {
                $this->logger->logSessionActivity($sessionId, 'session_renewed');
            }

            return $result;

        } catch (Exception $e) {
            $this->logger->logError("Failed to renew session $sessionId", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Destruye una sesión específica
     */
    public function destroySession(string $sessionId): bool
    {
        try {
            // Obtener información de la sesión antes de eliminarla
            $stmt = $this->pdo->prepare("SELECT user_id FROM user_sessions WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            $userId = $stmt->fetchColumn();

            // Eliminar de la base de datos
            $stmt = $this->pdo->prepare("DELETE FROM user_sessions WHERE session_id = ?");
            $result = $stmt->execute([$sessionId]);

            // Destruir sesión PHP si es la actual
            if (session_id() === $sessionId) {
                session_destroy();
            }

            if ($result) {
                $this->logger->logSessionActivity($sessionId, 'session_destroyed', [
                    'user_id' => $userId
                ]);
            }

            return $result;

        } catch (Exception $e) {
            $this->logger->logError("Failed to destroy session $sessionId", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Destruye todas las sesiones de un usuario
     */
    public function destroyUserSessions($userId, string $exceptSessionId = null): int
    {
        try {
            $sql = "DELETE FROM user_sessions WHERE user_id = ?";
            $params = [$userId];

            if ($exceptSessionId) {
                $sql .= " AND session_id != ?";
                $params[] = $exceptSessionId;
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $deletedCount = $stmt->rowCount();

            if ($deletedCount > 0) {
                $this->logger->logSessionActivity('multiple', 'user_sessions_destroyed', [
                    'user_id' => $userId,
                    'count' => $deletedCount,
                    'except_session' => $exceptSessionId
                ]);
            }

            return $deletedCount;

        } catch (Exception $e) {
            $this->logger->logError("Failed to destroy user sessions for user $userId", [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Obtiene información de una sesión
     */
    public function getSessionInfo(string $sessionId): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT s.*, u.nombre as username, u.email 
                FROM user_sessions s
                LEFT JOIN usuarios u ON s.user_id = u.id
                WHERE s.session_id = ?
            ");
            
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($session && $session['metadata']) {
                $session['metadata'] = json_decode($session['metadata'], true);
            }

            return $session ?: null;

        } catch (Exception $e) {
            $this->logger->logError("Failed to get session info for $sessionId", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Obtiene todas las sesiones activas de un usuario
     */
    public function getUserSessions($userId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT session_id, ip_address, user_agent, created_at, updated_at, expires_at, metadata
                FROM user_sessions 
                WHERE user_id = ? AND expires_at > NOW()
                ORDER BY updated_at DESC
            ");
            
            $stmt->execute([$userId]);
            $sessions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Decodificar metadata
            foreach ($sessions as &$session) {
                if ($session['metadata']) {
                    $session['metadata'] = json_decode($session['metadata'], true);
                }
            }

            return $sessions;

        } catch (Exception $e) {
            $this->logger->logError("Failed to get user sessions for user $userId", [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Limpia sesiones expiradas
     */
    public function cleanupExpiredSessions(): int
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM user_sessions WHERE expires_at <= NOW()");
            $stmt->execute();
            $deletedCount = $stmt->rowCount();

            if ($deletedCount > 0) {
                $this->logger->logSessionActivity('cleanup', 'expired_sessions_cleaned', [
                    'count' => $deletedCount
                ]);
            }

            return $deletedCount;

        } catch (Exception $e) {
            $this->logger->logError("Failed to cleanup expired sessions", [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Genera un ID de sesión seguro
     */
    private function generateSessionId(): string
    {
        return bin2hex(random_bytes(32));
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
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Configuración por defecto
     */
    private function getDefaultConfig(): array
    {
        return [
            'session_lifetime' => 7200, // 2 horas
            'max_inactive_time' => 1800, // 30 minutos
            'check_ip' => false,
            'auto_cleanup' => true,
            'max_sessions_per_user' => 5
        ];
    }

    /**
     * Limita el número de sesiones por usuario
     */
    public function limitUserSessions($userId): bool
    {
        try {
            if ($this->config['max_sessions_per_user'] <= 0) {
                return true; // Sin límite
            }

            $stmt = $this->pdo->prepare("
                SELECT session_id 
                FROM user_sessions 
                WHERE user_id = ? AND expires_at > NOW()
                ORDER BY updated_at ASC
            ");
            
            $stmt->execute([$userId]);
            $sessions = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            if (count($sessions) <= $this->config['max_sessions_per_user']) {
                return true;
            }

            // Eliminar sesiones más antiguas
            $sessionsToDelete = array_slice($sessions, 0, count($sessions) - $this->config['max_sessions_per_user']);
            
            foreach ($sessionsToDelete as $sessionId) {
                $this->destroySession($sessionId);
            }

            return true;

        } catch (Exception $e) {
            $this->logger->logError("Failed to limit user sessions for user $userId", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtiene estadísticas de sesiones
     */
    public function getSessionStats(): array
    {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(*) as total_sessions,
                    COUNT(DISTINCT user_id) as unique_users,
                    AVG(TIMESTAMPDIFF(MINUTE, created_at, updated_at)) as avg_session_duration,
                    COUNT(CASE WHEN expires_at > NOW() THEN 1 END) as active_sessions,
                    COUNT(CASE WHEN expires_at <= NOW() THEN 1 END) as expired_sessions
                FROM user_sessions
            ");
            
            return $stmt->fetch(\PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return [];
        }
    }
}