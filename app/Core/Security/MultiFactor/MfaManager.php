<?php
/**
 * Sistema de Autenticación Multifactor (MFA) - GAMP 5 Compliant
 * 
 * Este módulo implementa autenticación de dos factores utilizando:
 * - TOTP (Time-based One-Time Password) mediante Google Authenticator
 * - SMS (como alternativa)
 * - Códigos de respaldo
 * 
 * Cumple con los estándares de seguridad de GAMP 5 y normativas GxP
 */

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../Audit/AuditLogger.php';

/**
 * Gestor de Autenticación Multifactor
 */
class MfaManager
{
    private mysqli $conn;
    private AuditLogger $auditLogger;
    
    // Configuración TOTP
    private const TOTP_WINDOW = 30; // 30 segundos
    private const TOTP_DIGITS = 6;
    private const BACKUP_CODES_COUNT = 10;
    
    public function __construct(?mysqli $connection = null)
    {
        global $conn;
        $this->conn = $connection ?? $conn ?? DatabaseManager::getConnection();
        $this->auditLogger = new AuditLogger($this->conn);
    }
    
    /**
     * Genera un secreto TOTP para un usuario
     */
    public function generateTotpSecret(int $userId): string
    {
        $secret = $this->generateRandomSecret();
        
        $stmt = $this->conn->prepare("
            INSERT INTO user_mfa_settings (user_id, totp_secret, created_at) 
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                totp_secret = VALUES(totp_secret),
                updated_at = NOW()
        ");
        
        $stmt->bind_param('is', $userId, $secret);
        $stmt->execute();
        
        $this->auditLogger->logActivity(
            $userId,
            'MFA_SECRET_GENERATED',
            ['action' => 'TOTP secret generated'],
            $this->getClientIp()
        );
        
        return $secret;
    }
    
    /**
     * Verifica un código TOTP
     */
    public function verifyTotpCode(int $userId, string $code): bool
    {
        $secret = $this->getUserTotpSecret($userId);
        if (!$secret) {
            return false;
        }
        
        $currentTimestamp = floor(time() / self::TOTP_WINDOW);
        
        // Verificar ventana actual y ±1 ventana para compensar desfase de reloj
        for ($i = -1; $i <= 1; $i++) {
            $timestamp = $currentTimestamp + $i;
            $expectedCode = $this->generateTotpCode($secret, (int)$timestamp);
            
            if (hash_equals($expectedCode, $code)) {
                $this->updateLastMfaVerification($userId);
                
                $this->auditLogger->logActivity(
                    $userId,
                    'MFA_SUCCESS',
                    ['method' => 'TOTP', 'timestamp' => $timestamp],
                    $this->getClientIp()
                );
                
                return true;
            }
        }
        
        $this->auditLogger->logActivity(
            $userId,
            'MFA_FAILED',
            ['method' => 'TOTP', 'code_attempted' => substr($code, 0, 2) . '****'],
            $this->getClientIp()
        );
        
        return false;
    }
    
    /**
     * Genera códigos de respaldo
     */
    public function generateBackupCodes(int $userId): array
    {
        $codes = [];
        
        // Deshabilitar códigos existentes
        $stmt = $this->conn->prepare("
            UPDATE user_backup_codes 
            SET used_at = NOW(), is_active = 0 
            WHERE user_id = ? AND is_active = 1
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        
        // Generar nuevos códigos
        for ($i = 0; $i < self::BACKUP_CODES_COUNT; $i++) {
            $code = $this->generateBackupCode();
            $hashedCode = password_hash($code, PASSWORD_ARGON2ID);
            
            $stmt = $this->conn->prepare("
                INSERT INTO user_backup_codes (user_id, code_hash, created_at) 
                VALUES (?, ?, NOW())
            ");
            $stmt->bind_param('is', $userId, $hashedCode);
            $stmt->execute();
            
            $codes[] = $code;
        }
        
        $this->auditLogger->logActivity(
            $userId,
            'MFA_BACKUP_CODES_GENERATED',
            ['codes_count' => count($codes)],
            $this->getClientIp()
        );
        
        return $codes;
    }
    
    /**
     * Verifica un código de respaldo
     */
    public function verifyBackupCode(int $userId, string $code): bool
    {
        $stmt = $this->conn->prepare("
            SELECT id, code_hash 
            FROM user_backup_codes 
            WHERE user_id = ? AND is_active = 1 AND used_at IS NULL
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            if (password_verify($code, $row['code_hash'])) {
                // Marcar código como usado
                $updateStmt = $this->conn->prepare("
                    UPDATE user_backup_codes 
                    SET used_at = NOW(), is_active = 0 
                    WHERE id = ?
                ");
                $updateStmt->bind_param('i', $row['id']);
                $updateStmt->execute();
                
                $this->updateLastMfaVerification($userId);
                
                $this->auditLogger->logActivity(
                    $userId,
                    'MFA_SUCCESS',
                    ['method' => 'BACKUP_CODE', 'code_id' => $row['id']],
                    $this->getClientIp()
                );
                
                return true;
            }
        }
        
        $this->auditLogger->logActivity(
            $userId,
            'MFA_FAILED',
            ['method' => 'BACKUP_CODE'],
            $this->getClientIp()
        );
        
        return false;
    }
    
    /**
     * Verifica si el usuario tiene MFA configurado
     */
    public function isMfaEnabled(int $userId): bool
    {
        $stmt = $this->conn->prepare("
            SELECT totp_secret 
            FROM user_mfa_settings 
            WHERE user_id = ? AND totp_secret IS NOT NULL
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0;
    }
    
    /**
     * Genera QR code data para configurar authenticator
     */
    public function getQrCodeData(int $userId, string $userEmail): string
    {
        $secret = $this->getUserTotpSecret($userId);
        if (!$secret) {
            $secret = $this->generateTotpSecret($userId);
        }
        
        $issuer = 'SBL Sistema Validacion';
        $label = urlencode($issuer . ':' . $userEmail);
        
        return "otpauth://totp/{$label}?secret={$secret}&issuer=" . urlencode($issuer);
    }
    
    /**
     * Verifica si se requiere MFA para este usuario
     */
    public function requiresMfa(int $userId): bool
    {
        // Verificar política de empresa
        $stmt = $this->conn->prepare("
            SELECT u.empresa_id, e.mfa_required, r.nombre as role_name
            FROM usuarios u
            LEFT JOIN empresas e ON u.empresa_id = e.id
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.id = ?
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (!$user) {
            return false;
        }
        
        // MFA obligatorio para roles críticos
        $criticalRoles = ['Superadministrador', 'Developer', 'Administrador'];
        if (in_array($user['role_name'], $criticalRoles)) {
            return true;
        }
        
        // MFA según política de empresa
        return (bool) $user['mfa_required'];
    }
    
    /**
     * Genera un secreto aleatorio para TOTP
     */
    private function generateRandomSecret(int $length = 32): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        return $secret;
    }
    
    /**
     * Genera código TOTP
     */
    private function generateTotpCode(string $secret, int $timestamp): string
    {
        $key = $this->base32Decode($secret);
        $time = pack('N*', 0) . pack('N*', $timestamp);
        $hash = hash_hmac('sha1', $time, $key, true);
        $offset = ord($hash[19]) & 0xf;
        $code = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % pow(10, self::TOTP_DIGITS);
        
        return str_pad((string)$code, self::TOTP_DIGITS, '0', STR_PAD_LEFT);
    }
    
    /**
     * Decodifica base32
     */
    private function base32Decode(string $secret): string
    {
        $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper($secret);
        $buffer = '';
        
        for ($i = 0; $i < strlen($secret); $i++) {
            $char = $secret[$i];
            if ($char == '=') break;
            $buffer .= str_pad(decbin(strpos($base32chars, $char)), 5, '0', STR_PAD_LEFT);
        }
        
        $result = '';
        for ($i = 0; $i < strlen($buffer); $i += 8) {
            if (strlen(substr($buffer, $i, 8)) == 8) {
                $result .= chr(bindec(substr($buffer, $i, 8)));
            }
        }
        
        return $result;
    }
    
    /**
     * Obtiene el secreto TOTP del usuario
     */
    private function getUserTotpSecret(int $userId): ?string
    {
        $stmt = $this->conn->prepare("
            SELECT totp_secret 
            FROM user_mfa_settings 
            WHERE user_id = ?
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row['totp_secret'] ?? null;
    }
    
    /**
     * Genera un código de respaldo
     */
    private function generateBackupCode(): string
    {
        return sprintf('%04d-%04d', random_int(0, 9999), random_int(0, 9999));
    }
    
    /**
     * Actualiza la última verificación MFA
     */
    private function updateLastMfaVerification(int $userId): void
    {
        $stmt = $this->conn->prepare("
            UPDATE user_mfa_settings 
            SET last_verified_at = NOW() 
            WHERE user_id = ?
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
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