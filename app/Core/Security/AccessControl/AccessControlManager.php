<?php
/**
 * Sistema de Control de Accesos Avanzado con JWT - GAMP 5 Compliant
 * 
 * Este módulo implementa:
 * - Autenticación con JWT (JSON Web Tokens)
 * - RBAC (Role-Based Access Control) avanzado
 * - Control de sesiones
 * - Políticas de acceso granulares
 * - Bloqueo de cuentas por intentos fallidos
 * 
 * Cumple con estándares de control de acceso de GAMP 5 y normativas GxP
 */

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../Audit/AuditLogger.php';
require_once __DIR__ . '/../MultiFactor/MfaManager.php';
require_once __DIR__ . '/../Password/PasswordManager.php';

/**
 * Gestor de Control de Accesos con JWT
 */
class AccessControlManager
{
    private mysqli $conn;
    private AuditLogger $auditLogger;
    private MfaManager $mfaManager;
    private PasswordManager $passwordManager;
    
    // Configuración JWT
    private const JWT_SECRET_KEY = 'SBL_JWT_SECRET_2024_GAMP5_COMPLIANT';
    private const JWT_ALGORITHM = 'HS256';
    private const JWT_EXPIRY_TIME = 3600; // 1 hora
    private const REFRESH_TOKEN_EXPIRY = 604800; // 7 días
    
    // Configuración de bloqueo de cuentas
    private const MAX_FAILED_ATTEMPTS = 5;
    private const LOCKOUT_DURATION = 1800; // 30 minutos
    private const PROGRESSIVE_DELAY = true; // Retraso progresivo
    
    // Niveles de acceso según GAMP 5
    private const ACCESS_LEVELS = [
        'READ_ONLY' => 1,
        'OPERATOR' => 2,
        'SUPERVISOR' => 3,
        'ADMINISTRATOR' => 4,
        'SUPERADMIN' => 5,
        'DEVELOPER' => 6
    ];
    
    public function __construct(?mysqli $connection = null)
    {
        global $conn;
        $this->conn = $connection ?? $conn ?? DatabaseManager::getConnection();
        $this->auditLogger = new AuditLogger($this->conn);
        $this->mfaManager = new MfaManager($this->conn);
        $this->passwordManager = new PasswordManager($this->conn);
        
        $this->initializeAccessControlTables();
    }
    
    /**
     * Autentica un usuario y genera tokens JWT
     */
    public function authenticate(
        string $username,
        string $password,
        ?string $mfaCode = null,
        bool $rememberMe = false
    ): array {
        try {
            $clientIp = $this->getClientIp();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            
            // Verificar si la cuenta está bloqueada
            $lockoutStatus = $this->checkAccountLockout($username, $clientIp);
            if ($lockoutStatus['locked']) {
                $this->auditLogger->logFailedLogin(
                    $username,
                    'Account locked - ' . $lockoutStatus['reason'],
                    $clientIp
                );
                
                return [
                    'success' => false,
                    'error' => 'account_locked',
                    'message' => $lockoutStatus['message'],
                    'unlock_time' => $lockoutStatus['unlock_time']
                ];
            }
            
            // Buscar usuario
            $user = $this->getUserByUsername($username);
            if (!$user) {
                $this->recordFailedAttempt($username, $clientIp, 'Invalid username');
                return [
                    'success' => false,
                    'error' => 'invalid_credentials',
                    'message' => 'Credenciales inválidas'
                ];
            }
            
            // Verificar si la cuenta está activa
            if (!$user['activo']) {
                $this->auditLogger->logFailedLogin($username, 'Account disabled', $clientIp);
                return [
                    'success' => false,
                    'error' => 'account_disabled',
                    'message' => 'Cuenta deshabilitada'
                ];
            }
            
            // Verificar contraseña
            if (!$this->passwordManager->verifyPassword($password, $user['password'])) {
                $this->recordFailedAttempt($username, $clientIp, 'Invalid password');
                return [
                    'success' => false,
                    'error' => 'invalid_credentials',
                    'message' => 'Credenciales inválidas'
                ];
            }
            
            // Verificar si necesita cambiar contraseña
            $passwordStatus = $this->passwordManager->needsPasswordChange($user['id']);
            if ($passwordStatus['needs_change'] && $passwordStatus['reason'] !== 'warning') {
                return [
                    'success' => false,
                    'error' => 'password_change_required',
                    'message' => $passwordStatus['message'],
                    'user_id' => $user['id']
                ];
            }
            
            // Verificar MFA si está habilitado
            $requiresMfa = $this->mfaManager->requiresMfa($user['id']);
            $mfaEnabled = $this->mfaManager->isMfaEnabled($user['id']);
            
            if ($requiresMfa && $mfaEnabled) {
                if (empty($mfaCode)) {
                    return [
                        'success' => false,
                        'error' => 'mfa_required',
                        'message' => 'Se requiere código de autenticación de dos factores',
                        'user_id' => $user['id'],
                        'temp_token' => $this->generateTempToken($user['id'])
                    ];
                }
                
                $mfaValid = $this->mfaManager->verifyTotpCode($user['id'], $mfaCode) 
                         || $this->mfaManager->verifyBackupCode($user['id'], $mfaCode);
                
                if (!$mfaValid) {
                    $this->recordFailedAttempt($username, $clientIp, 'Invalid MFA code');
                    return [
                        'success' => false,
                        'error' => 'invalid_mfa',
                        'message' => 'Código de autenticación inválido'
                    ];
                }
            }
            
            // Autenticación exitosa - generar tokens
            $accessToken = $this->generateAccessToken($user);
            $refreshToken = $this->generateRefreshToken($user['id'], $rememberMe);
            
            // Limpiar intentos fallidos
            $this->clearFailedAttempts($username, $clientIp);
            
            // Actualizar último acceso
            $this->updateLastLogin($user['id'], $clientIp, $userAgent);
            
            // Iniciar sesión
            session_start();
            $_SESSION['usuario_id'] = $user['id'];
            $_SESSION['usuario'] = $user['usuario'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['rol'] = $user['role_name'];
            $_SESSION['empresa_id'] = $user['empresa_id'];
            $_SESSION['access_token'] = $accessToken;
            
            // Log de auditoría
            $this->auditLogger->logSuccessfulLogin($user['id'], [
                'username' => $username,
                'role' => $user['role_name'],
                'empresa_id' => $user['empresa_id'],
                'mfa_used' => $requiresMfa && $mfaEnabled,
                'remember_me' => $rememberMe
            ]);
            
            // Verificar advertencias de contraseña
            $warnings = [];
            if ($passwordStatus['reason'] === 'warning') {
                $warnings[] = $passwordStatus['message'];
            }
            
            return [
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['usuario'],
                    'nombre' => $user['nombre'],
                    'apellidos' => $user['apellidos'],
                    'role' => $user['role_name'],
                    'empresa_id' => $user['empresa_id'],
                    'permissions' => $this->getUserPermissions($user['id'])
                ],
                'tokens' => [
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'expires_in' => self::JWT_EXPIRY_TIME
                ],
                'warnings' => $warnings
            ];
            
        } catch (Exception $e) {
            $this->auditLogger->logActivity(
                null,
                'AUTHENTICATION_ERROR',
                [
                    'username' => $username,
                    'error' => $e->getMessage(),
                    'ip' => $clientIp
                ],
                null,
                AuditLogger::LEVEL_ERROR,
                AuditLogger::CATEGORY_AUTHENTICATION
            );
            
            return [
                'success' => false,
                'error' => 'internal_error',
                'message' => 'Error interno del sistema'
            ];
        }
    }
    
    /**
     * Valida un token JWT de acceso
     */
    public function validateAccessToken(string $token): array
    {
        try {
            $payload = $this->decodeJWT($token);
            
            if (!$payload) {
                return [
                    'valid' => false,
                    'error' => 'invalid_token'
                ];
            }
            
            // Verificar expiración
            if ($payload['exp'] < time()) {
                return [
                    'valid' => false,
                    'error' => 'token_expired'
                ];
            }
            
            // Verificar que el usuario sigue activo
            $user = $this->getUserById($payload['user_id']);
            if (!$user || !$user['activo']) {
                return [
                    'valid' => false,
                    'error' => 'user_inactive'
                ];
            }
            
            // Verificar que la sesión sigue válida
            if (!$this->isSessionValid($payload['session_id'])) {
                return [
                    'valid' => false,
                    'error' => 'session_invalid'
                ];
            }
            
            return [
                'valid' => true,
                'user_id' => $payload['user_id'],
                'session_id' => $payload['session_id'],
                'permissions' => $payload['permissions'] ?? []
            ];
            
        } catch (Exception $e) {
            return [
                'valid' => false,
                'error' => 'token_error'
            ];
        }
    }
    
    /**
     * Refresca un token de acceso usando el refresh token
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        try {
            $tokenData = $this->getRefreshTokenData($refreshToken);
            
            if (!$tokenData || $tokenData['expires_at'] < date('Y-m-d H:i:s')) {
                return [
                    'success' => false,
                    'error' => 'invalid_refresh_token'
                ];
            }
            
            $user = $this->getUserById($tokenData['user_id']);
            if (!$user || !$user['activo']) {
                return [
                    'success' => false,
                    'error' => 'user_inactive'
                ];
            }
            
            // Generar nuevo access token
            $accessToken = $this->generateAccessToken($user);
            
            // Actualizar refresh token usage
            $this->updateRefreshTokenUsage($refreshToken);
            
            $this->auditLogger->logActivity(
                $user['id'],
                'TOKEN_REFRESHED',
                ['refresh_token_id' => $tokenData['id']],
                null,
                AuditLogger::LEVEL_INFO,
                AuditLogger::CATEGORY_AUTHENTICATION
            );
            
            return [
                'success' => true,
                'access_token' => $accessToken,
                'expires_in' => self::JWT_EXPIRY_TIME
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'internal_error'
            ];
        }
    }
    
    /**
     * Verifica permisos específicos del usuario
     */
    public function checkPermission(int $userId, string $permission): bool
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count
            FROM usuarios u
            JOIN roles r ON u.role_id = r.id
            JOIN role_permissions rp ON r.id = rp.role_id
            JOIN permissions p ON rp.permission_id = p.id
            WHERE u.id = ? AND p.nombre = ? AND u.activo = 1
        ");
        
        $stmt->bind_param('is', $userId, $permission);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return (int)$row['count'] > 0;
    }
    
    /**
     * Cierra sesión y revoca tokens
     */
    public function logout(int $userId, ?string $refreshToken = null): bool
    {
        try {
            // Revocar refresh token específico o todos
            if ($refreshToken) {
                $this->revokeRefreshToken($refreshToken);
            } else {
                $this->revokeAllUserTokens($userId);
            }
            
            // Destruir sesión PHP
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            
            $this->auditLogger->logLogout($userId, 'manual');
            
            return true;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Obtiene estadísticas de seguridad de acceso
     */
    public function getSecurityStatistics(): array
    {
        // Intentos fallidos recientes (últimas 24 horas)
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count 
            FROM failed_login_attempts 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute();
        $failedAttempts = $stmt->get_result()->fetch_assoc()['count'];
        
        // Cuentas bloqueadas
        $stmt = $this->conn->prepare("
            SELECT COUNT(DISTINCT username) as count 
            FROM failed_login_attempts 
            WHERE locked_until > NOW()
        ");
        $stmt->execute();
        $lockedAccounts = $stmt->get_result()->fetch_assoc()['count'];
        
        // Sesiones activas
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count 
            FROM user_sessions 
            WHERE expires_at > NOW() AND is_active = 1
        ");
        $stmt->execute();
        $activeSessions = $stmt->get_result()->fetch_assoc()['count'];
        
        // Usuarios con MFA habilitado
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count 
            FROM user_mfa_settings 
            WHERE totp_secret IS NOT NULL
        ");
        $stmt->execute();
        $mfaUsers = $stmt->get_result()->fetch_assoc()['count'];
        
        return [
            'failed_attempts_24h' => (int)$failedAttempts,
            'locked_accounts' => (int)$lockedAccounts,
            'active_sessions' => (int)$activeSessions,
            'mfa_enabled_users' => (int)$mfaUsers
        ];
    }
    
    /**
     * Inicializa las tablas de control de acceso
     */
    private function initializeAccessControlTables(): void
    {
        // Tabla de intentos fallidos
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS failed_login_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(100) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                reason VARCHAR(255),
                locked_until DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_username (username),
                INDEX idx_ip_address (ip_address),
                INDEX idx_locked_until (locked_until),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Tabla de refresh tokens
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS refresh_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(255) NOT NULL UNIQUE,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_used_at DATETIME NULL,
                is_revoked BOOLEAN DEFAULT FALSE,
                user_agent TEXT,
                ip_address VARCHAR(45),
                INDEX idx_user_id (user_id),
                INDEX idx_token (token),
                INDEX idx_expires_at (expires_at),
                FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Tabla de sesiones de usuario
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS user_sessions (
                id VARCHAR(128) PRIMARY KEY,
                user_id INT NOT NULL,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_activity DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                is_active BOOLEAN DEFAULT TRUE,
                INDEX idx_user_id (user_id),
                INDEX idx_expires_at (expires_at),
                INDEX idx_last_activity (last_activity),
                FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Tabla de configuración MFA
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS user_mfa_settings (
                user_id INT PRIMARY KEY,
                totp_secret VARCHAR(32),
                last_verified_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Tabla de códigos de respaldo MFA
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS user_backup_codes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                code_hash VARCHAR(255) NOT NULL,
                used_at DATETIME NULL,
                is_active BOOLEAN DEFAULT TRUE,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
    
    /**
     * Verifica el estado de bloqueo de una cuenta
     */
    private function checkAccountLockout(string $username, string $ip): array
    {
        $stmt = $this->conn->prepare("
            SELECT locked_until, COUNT(*) as attempts
            FROM failed_login_attempts 
            WHERE (username = ? OR ip_address = ?) 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            GROUP BY locked_until
            ORDER BY locked_until DESC
            LIMIT 1
        ");
        
        $stmt->bind_param('ss', $username, $ip);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row && $row['locked_until'] && $row['locked_until'] > date('Y-m-d H:i:s')) {
            return [
                'locked' => true,
                'reason' => 'Too many failed attempts',
                'message' => 'Cuenta bloqueada por intentos fallidos',
                'unlock_time' => $row['locked_until']
            ];
        }
        
        return ['locked' => false];
    }
    
    // Métodos auxiliares adicionales continúan...
    
    private function getUserByUsername(string $username): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT u.*, r.nombre as role_name 
            FROM usuarios u 
            LEFT JOIN roles r ON u.role_id = r.id 
            WHERE u.usuario = ?
        ");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc() ?: null;
    }
    
    private function getUserById(int $userId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT u.*, r.nombre as role_name 
            FROM usuarios u 
            LEFT JOIN roles r ON u.role_id = r.id 
            WHERE u.id = ?
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc() ?: null;
    }
    
    private function getUserPermissions(int $userId): array
    {
        $stmt = $this->conn->prepare("
            SELECT p.nombre 
            FROM usuarios u
            JOIN roles r ON u.role_id = r.id
            JOIN role_permissions rp ON r.id = rp.role_id
            JOIN permissions p ON rp.permission_id = p.id
            WHERE u.id = ?
        ");
        
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $permissions = [];
        while ($row = $result->fetch_assoc()) {
            $permissions[] = $row['nombre'];
        }
        
        return $permissions;
    }
    
    private function generateAccessToken(array $user): string
    {
        $payload = [
            'iss' => 'SBL_Validation_System',
            'aud' => 'SBL_Users',
            'iat' => time(),
            'exp' => time() + self::JWT_EXPIRY_TIME,
            'user_id' => $user['id'],
            'username' => $user['usuario'],
            'role' => $user['role_name'],
            'empresa_id' => $user['empresa_id'],
            'session_id' => session_id(),
            'permissions' => $this->getUserPermissions($user['id'])
        ];
        
        return $this->encodeJWT($payload);
    }
    
    private function generateRefreshToken(int $userId, bool $rememberMe): string
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + ($rememberMe ? self::REFRESH_TOKEN_EXPIRY * 4 : self::REFRESH_TOKEN_EXPIRY));
        
        $stmt = $this->conn->prepare("
            INSERT INTO refresh_tokens (user_id, token, expires_at, user_agent, ip_address) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ipAddress = $this->getClientIp();
        
        $stmt->bind_param('issss', $userId, $token, $expiresAt, $userAgent, $ipAddress);
        $stmt->execute();
        
        return $token;
    }
    
    private function generateTempToken(int $userId): string
    {
        $payload = [
            'iss' => 'SBL_Validation_System',
            'aud' => 'SBL_MFA',
            'iat' => time(),
            'exp' => time() + 300, // 5 minutos
            'user_id' => $userId,
            'type' => 'mfa_temp'
        ];
        
        return $this->encodeJWT($payload);
    }
    
    private function encodeJWT(array $payload): string
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => self::JWT_ALGORITHM]);
        $payload = json_encode($payload);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, self::JWT_SECRET_KEY, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }
    
    private function decodeJWT(string $token): ?array
    {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return null;
        }
        
        $header = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[0])), true);
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
        $signature = str_replace(['-', '_'], ['+', '/'], $parts[2]);
        
        $expectedSignature = hash_hmac('sha256', $parts[0] . "." . $parts[1], self::JWT_SECRET_KEY, true);
        $expectedSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($expectedSignature));
        
        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }
        
        return $payload;
    }
    
    private function recordFailedAttempt(string $username, string $ip, string $reason): void
    {
        $stmt = $this->conn->prepare("
            INSERT INTO failed_login_attempts (username, ip_address, reason) 
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param('sss', $username, $ip, $reason);
        $stmt->execute();
        
        // Verificar si se debe bloquear la cuenta
        $this->checkAndApplyLockout($username, $ip);
    }
    
    private function checkAndApplyLockout(string $username, string $ip): void
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count 
            FROM failed_login_attempts 
            WHERE (username = ? OR ip_address = ?) 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        
        $stmt->bind_param('ss', $username, $ip);
        $stmt->execute();
        $result = $stmt->get_result();
        $attempts = $result->fetch_assoc()['count'];
        
        if ($attempts >= self::MAX_FAILED_ATTEMPTS) {
            $lockoutUntil = date('Y-m-d H:i:s', time() + self::LOCKOUT_DURATION);
            
            $stmt = $this->conn->prepare("
                UPDATE failed_login_attempts 
                SET locked_until = ? 
                WHERE (username = ? OR ip_address = ?) 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            
            $stmt->bind_param('sss', $lockoutUntil, $username, $ip);
            $stmt->execute();
            
            $this->auditLogger->logSecurityEvent(
                'ACCOUNT_LOCKED',
                null,
                [
                    'username' => $username,
                    'ip_address' => $ip,
                    'attempts' => $attempts,
                    'locked_until' => $lockoutUntil
                ],
                AuditLogger::LEVEL_WARNING
            );
        }
    }
    
    private function clearFailedAttempts(string $username, string $ip): void
    {
        $stmt = $this->conn->prepare("
            DELETE FROM failed_login_attempts 
            WHERE (username = ? OR ip_address = ?) 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->bind_param('ss', $username, $ip);
        $stmt->execute();
    }
    
    private function updateLastLogin(int $userId, string $ip, string $userAgent): void
    {
        $stmt = $this->conn->prepare("
            UPDATE usuarios 
            SET ultima_ip = ?, last_login = NOW() 
            WHERE id = ?
        ");
        $stmt->bind_param('si', $ip, $userId);
        $stmt->execute();
        
        // Crear/actualizar sesión
        $sessionId = session_id();
        $expiresAt = date('Y-m-d H:i:s', time() + self::JWT_EXPIRY_TIME);
        
        $stmt = $this->conn->prepare("
            INSERT INTO user_sessions (id, user_id, ip_address, user_agent, expires_at) 
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                last_activity = NOW(), 
                expires_at = VALUES(expires_at),
                is_active = TRUE
        ");
        
        $stmt->bind_param('sisss', $sessionId, $userId, $ip, $userAgent, $expiresAt);
        $stmt->execute();
    }
    
    private function getRefreshTokenData(string $token): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT * FROM refresh_tokens 
            WHERE token = ? AND is_revoked = FALSE
        ");
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc() ?: null;
    }
    
    private function updateRefreshTokenUsage(string $token): void
    {
        $stmt = $this->conn->prepare("
            UPDATE refresh_tokens 
            SET last_used_at = NOW() 
            WHERE token = ?
        ");
        $stmt->bind_param('s', $token);
        $stmt->execute();
    }
    
    private function revokeRefreshToken(string $token): void
    {
        $stmt = $this->conn->prepare("
            UPDATE refresh_tokens 
            SET is_revoked = TRUE 
            WHERE token = ?
        ");
        $stmt->bind_param('s', $token);
        $stmt->execute();
    }
    
    private function revokeAllUserTokens(int $userId): void
    {
        $stmt = $this->conn->prepare("
            UPDATE refresh_tokens 
            SET is_revoked = TRUE 
            WHERE user_id = ?
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        
        // También desactivar sesiones
        $stmt = $this->conn->prepare("
            UPDATE user_sessions 
            SET is_active = FALSE 
            WHERE user_id = ?
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
    }
    
    private function isSessionValid(string $sessionId): bool
    {
        $stmt = $this->conn->prepare("
            SELECT is_active 
            FROM user_sessions 
            WHERE id = ? AND expires_at > NOW()
        ");
        $stmt->bind_param('s', $sessionId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row && $row['is_active'];
    }
    
    private function getClientIp(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] 
            ?? $_SERVER['HTTP_X_REAL_IP'] 
            ?? $_SERVER['REMOTE_ADDR'] 
            ?? 'unknown';
    }
}