<?php

namespace App\Core\Auth;

use PDO;
use Exception;

/**
 * Implementación de base de datos para autenticación
 */
class DatabaseAuthProvider implements AuthProviderInterface
{
    private PDO $pdo;
    private AuthLogger $logger;
    private array $config;

    public function __construct(PDO $pdo, AuthLogger $logger, array $config = [])
    {
        $this->pdo = $pdo;
        $this->logger = $logger;
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Autentica un usuario con credenciales
     */
    public function authenticate(string $username, string $password): ?array
    {
        try {
            // Buscar usuario
            $user = $this->findUser($username);
            if (!$user) {
                $this->logger->logFailedLogin($username, 'user_not_found');
                return null;
            }

            // Verificar si la cuenta está bloqueada
            if ($this->isAccountLocked($user['id'])) {
                $this->logger->logFailedLogin($username, 'account_locked');
                return null;
            }

            // Verificar contraseña
            if (!$this->verifyPassword($password, $user['password'])) {
                $this->recordFailedAttempt($user['id']);
                $this->logger->logFailedLogin($username, 'invalid_password');
                return null;
            }

            // Verificar si la cuenta está activa
            if (!$this->isAccountActive($user)) {
                $this->logger->logFailedLogin($username, 'account_inactive');
                return null;
            }

            // Limpiar intentos fallidos y actualizar último login
            $this->clearFailedAttempts($user['id']);
            $this->updateLastLogin($user['id']);

            // Preparar datos del usuario (sin contraseña)
            unset($user['password']);
            
            return $user;

        } catch (Exception $e) {
            $this->logger->logError("Authentication error for user $username", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Busca un usuario por nombre de usuario o email
     */
    private function findUser(string $identifier): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, nombre as username, email, password, activo, 
                       fecha_creacion, ultimo_acceso, empresa_id, 
                       must_change_password, password_expires_at
                FROM usuarios 
                WHERE (nombre = ? OR email = ?) 
                LIMIT 1
            ");
            
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            return $user ?: null;

        } catch (Exception $e) {
            throw new Exception("Failed to find user: " . $e->getMessage());
        }
    }

    /**
     * Verifica si una cuenta está bloqueada
     */
    private function isAccountLocked($userId): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM login_attempts 
                WHERE user_id = ? 
                AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
            ");
            
            $stmt->execute([$userId, $this->config['lockout_time']]);
            $attempts = $stmt->fetchColumn();

            return $attempts >= $this->config['max_attempts'];

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Verifica si una cuenta está activa
     */
    private function isAccountActive(array $user): bool
    {
        // Verificar estado activo
        if (!$user['activo']) {
            return false;
        }

        // Verificar si la contraseña ha expirado
        if ($user['password_expires_at'] && 
            new \DateTime($user['password_expires_at']) < new \DateTime()) {
            return false;
        }

        return true;
    }

    /**
     * Verifica una contraseña
     */
    private function verifyPassword(string $password, string $hash): bool
    {
        // Primero probar con password_verify (recomendado)
        if (password_verify($password, $hash)) {
            return true;
        }

        // Compatibilidad con hashes MD5 antiguos (migración gradual)
        if ($this->config['allow_legacy_hashes'] && md5($password) === $hash) {
            // Rehash con bcrypt para seguridad futura
            $this->upgradePasswordHash($password, $hash);
            return true;
        }

        return false;
    }

    /**
     * Actualiza hash de contraseña a bcrypt
     */
    private function upgradePasswordHash(string $password, string $oldHash): void
    {
        try {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $this->pdo->prepare("
                UPDATE usuarios 
                SET password = ? 
                WHERE password = ?
            ");
            
            $stmt->execute([$newHash, $oldHash]);

        } catch (Exception $e) {
            // Log pero no fallar la autenticación
            $this->logger->logError("Failed to upgrade password hash", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Registra un intento fallido de login
     */
    private function recordFailedAttempt($userId): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO login_attempts (user_id, ip_address, user_agent, attempted_at) 
                VALUES (?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $userId,
                $this->getClientIp(),
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);

        } catch (Exception $e) {
            // No fallar por esto
        }
    }

    /**
     * Limpia intentos fallidos de login
     */
    private function clearFailedAttempts($userId): void
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM login_attempts WHERE user_id = ?");
            $stmt->execute([$userId]);

        } catch (Exception $e) {
            // No fallar por esto
        }
    }

    /**
     * Actualiza la fecha de último login
     */
    private function updateLastLogin($userId): void
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE usuarios 
                SET ultimo_acceso = NOW() 
                WHERE id = ?
            ");
            
            $stmt->execute([$userId]);

        } catch (Exception $e) {
            // No fallar por esto
        }
    }

    /**
     * Obtiene un usuario por ID
     */
    public function getUserById($userId): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, nombre as username, email, activo, 
                       fecha_creacion, ultimo_acceso, empresa_id,
                       must_change_password, password_expires_at
                FROM usuarios 
                WHERE id = ?
            ");
            
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            return $user ?: null;

        } catch (Exception $e) {
            $this->logger->logError("Failed to get user by ID $userId", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Verifica si un usuario existe
     */
    public function userExists(string $identifier): bool
    {
        return $this->findUser($identifier) !== null;
    }

    /**
     * Cambia la contraseña de un usuario
     */
    public function changePassword($userId, string $newPassword): bool
    {
        try {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $expiresAt = null;
            if ($this->config['password_expiry_days'] > 0) {
                $expiresAt = new \DateTime();
                $expiresAt->add(new \DateInterval('P' . $this->config['password_expiry_days'] . 'D'));
            }

            $stmt = $this->pdo->prepare("
                UPDATE usuarios 
                SET password = ?, 
                    must_change_password = 0,
                    password_expires_at = ?,
                    password_changed_at = NOW()
                WHERE id = ?
            ");
            
            $result = $stmt->execute([
                $hash, 
                $expiresAt ? $expiresAt->format('Y-m-d H:i:s') : null,
                $userId
            ]);

            if ($result) {
                $this->logger->logSecurityEvent('PASSWORD_CHANGED', [
                    'user_id' => $userId
                ]);
            }

            return $result;

        } catch (Exception $e) {
            $this->logger->logError("Failed to change password for user $userId", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Bloquea una cuenta de usuario
     */
    public function lockAccount($userId, string $reason = ''): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE usuarios 
                SET activo = 0, 
                    locked_at = NOW(),
                    lock_reason = ?
                WHERE id = ?
            ");
            
            $result = $stmt->execute([$reason, $userId]);

            if ($result) {
                $this->logger->logSecurityEvent('ACCOUNT_LOCKED', [
                    'user_id' => $userId,
                    'reason' => $reason
                ]);
            }

            return $result;

        } catch (Exception $e) {
            $this->logger->logError("Failed to lock account for user $userId", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Desbloquea una cuenta de usuario
     */
    public function unlockAccount($userId): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE usuarios 
                SET activo = 1, 
                    locked_at = NULL,
                    lock_reason = NULL
                WHERE id = ?
            ");
            
            $result = $stmt->execute([$userId]);

            if ($result) {
                $this->clearFailedAttempts($userId);
                $this->logger->logSecurityEvent('ACCOUNT_UNLOCKED', [
                    'user_id' => $userId
                ]);
            }

            return $result;

        } catch (Exception $e) {
            $this->logger->logError("Failed to unlock account for user $userId", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Genera un token de recuperación de contraseña
     */
    public function generatePasswordResetToken($userId): ?string
    {
        try {
            $token = bin2hex(random_bytes(32));
            $expiresAt = new \DateTime();
            $expiresAt->add(new \DateInterval('PT1H')); // 1 hora

            $stmt = $this->pdo->prepare("
                INSERT INTO password_reset_tokens (user_id, token, expires_at, created_at) 
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                token = VALUES(token), 
                expires_at = VALUES(expires_at), 
                created_at = VALUES(created_at)
            ");
            
            $result = $stmt->execute([$userId, $token, $expiresAt->format('Y-m-d H:i:s')]);

            if ($result) {
                $this->logger->logSecurityEvent('PASSWORD_RESET_TOKEN_GENERATED', [
                    'user_id' => $userId
                ]);
                return $token;
            }

            return null;

        } catch (Exception $e) {
            $this->logger->logError("Failed to generate password reset token for user $userId", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Verifica un token de recuperación de contraseña
     */
    public function verifyPasswordResetToken(string $token): ?int
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT user_id 
                FROM password_reset_tokens 
                WHERE token = ? AND expires_at > NOW() AND used_at IS NULL
            ");
            
            $stmt->execute([$token]);
            $userId = $stmt->fetchColumn();

            return $userId ?: null;

        } catch (Exception $e) {
            $this->logger->logError("Failed to verify password reset token", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Marca un token de recuperación como usado
     */
    public function markPasswordResetTokenUsed(string $token): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE password_reset_tokens 
                SET used_at = NOW() 
                WHERE token = ?
            ");
            
            return $stmt->execute([$token]);

        } catch (Exception $e) {
            return false;
        }
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
            'max_attempts' => 5,
            'lockout_time' => 30, // minutos 
            'allow_legacy_hashes' => true,
            'password_expiry_days' => 90,
            'min_password_length' => 8,
            'require_password_complexity' => true
        ];
    }

    /**
     * Obtiene estadísticas de autenticación
     */
    public function getAuthStats(): array
    {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    (SELECT COUNT(*) FROM usuarios WHERE activo = 1) as active_users,
                    (SELECT COUNT(*) FROM usuarios WHERE activo = 0) as inactive_users,
                    (SELECT COUNT(*) FROM usuarios WHERE must_change_password = 1) as users_must_change_password,
                    (SELECT COUNT(*) FROM usuarios WHERE password_expires_at < NOW()) as users_expired_password,
                    (SELECT COUNT(*) FROM login_attempts WHERE attempted_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)) as failed_attempts_24h
            ");
            
            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return [];
        }
    }
}