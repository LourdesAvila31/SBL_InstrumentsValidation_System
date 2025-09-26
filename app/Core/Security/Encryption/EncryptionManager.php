<?php
/**
 * Sistema de Cifrado de Datos - GAMP 5 Compliant
 * 
 * Este módulo implementa:
 * - Cifrado de datos en tránsito (TLS/SSL)
 * - Cifrado de datos en reposo (AES-256-GCM)
 * - Gestión de claves de cifrado
 * - Cifrado de campos sensibles en base de datos
 * - Validación de certificados SSL
 * 
 * Cumple con estándares de cifrado de GAMP 5 y normativas GxP
 */

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../Audit/AuditLogger.php';

/**
 * Gestor de Cifrado de Datos
 */
class EncryptionManager
{
    private mysqli $conn;
    private AuditLogger $auditLogger;
    private string $masterKey;
    
    // Configuración de cifrado
    private const CIPHER_METHOD = 'aes-256-gcm';
    private const KEY_LENGTH = 32; // 256 bits
    private const IV_LENGTH = 12;  // 96 bits para GCM
    private const TAG_LENGTH = 16; // 128 bits
    
    // Campos que requieren cifrado
    private const ENCRYPTED_FIELDS = [
        'usuarios' => ['telefono', 'direccion', 'documento_identidad'],
        'empresas' => ['telefono', 'direccion', 'nit'],
        'instrumentos' => ['numero_serie', 'observaciones'],
        'calibraciones' => ['observaciones', 'datos_tecnicos']
    ];
    
    public function __construct(?mysqli $connection = null)
    {
        global $conn;
        $this->conn = $connection ?? $conn ?? DatabaseManager::getConnection();
        $this->auditLogger = new AuditLogger($this->conn);
        
        $this->initializeMasterKey();
        $this->initializeEncryptionTables();
    }
    
    /**
     * Cifra datos sensibles usando AES-256-GCM
     */
    public function encrypt(string $data, ?string $context = null): array
    {
        try {
            if (empty($data)) {
                return [
                    'success' => false,
                    'error' => 'No data provided for encryption'
                ];
            }
            
            // Generar IV único para cada operación
            $iv = random_bytes(self::IV_LENGTH);
            
            // Generar clave de cifrado derivada del contexto
            $key = $this->deriveKey($context);
            
            // Cifrar datos
            $ciphertext = openssl_encrypt(
                $data,
                self::CIPHER_METHOD,
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );
            
            if ($ciphertext === false) {
                throw new Exception('Encryption failed: ' . openssl_error_string());
            }
            
            // Combinar todos los componentes
            $encrypted = base64_encode($iv . $tag . $ciphertext);
            
            // Log de auditoría (sin datos sensibles)
            $this->auditLogger->logActivity(
                $_SESSION['usuario_id'] ?? null,
                'DATA_ENCRYPTED',
                [
                    'context' => $context,
                    'data_length' => strlen($data),
                    'cipher_method' => self::CIPHER_METHOD
                ],
                null,
                AuditLogger::LEVEL_INFO,
                AuditLogger::CATEGORY_SECURITY_EVENT
            );
            
            return [
                'success' => true,
                'encrypted_data' => $encrypted
            ];
            
        } catch (Exception $e) {
            $this->auditLogger->logActivity(
                $_SESSION['usuario_id'] ?? null,
                'ENCRYPTION_ERROR',
                [
                    'context' => $context,
                    'error' => $e->getMessage()
                ],
                null,
                AuditLogger::LEVEL_ERROR,
                AuditLogger::CATEGORY_SECURITY_EVENT
            );
            
            return [
                'success' => false,
                'error' => 'Encryption failed'
            ];
        }
    }
    
    /**
     * Descifra datos usando AES-256-GCM
     */
    public function decrypt(string $encryptedData, ?string $context = null): array
    {
        try {
            if (empty($encryptedData)) {
                return [
                    'success' => false,
                    'error' => 'No encrypted data provided'
                ];
            }
            
            // Decodificar datos
            $data = base64_decode($encryptedData);
            if ($data === false) {
                throw new Exception('Invalid base64 encoding');
            }
            
            // Extraer componentes
            $iv = substr($data, 0, self::IV_LENGTH);
            $tag = substr($data, self::IV_LENGTH, self::TAG_LENGTH);
            $ciphertext = substr($data, self::IV_LENGTH + self::TAG_LENGTH);
            
            // Generar clave de cifrado derivada del contexto
            $key = $this->deriveKey($context);
            
            // Descifrar datos
            $plaintext = openssl_decrypt(
                $ciphertext,
                self::CIPHER_METHOD,
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );
            
            if ($plaintext === false) {
                throw new Exception('Decryption failed: ' . openssl_error_string());
            }
            
            // Log de auditoría (sin datos sensibles)
            $this->auditLogger->logActivity(
                $_SESSION['usuario_id'] ?? null,
                'DATA_DECRYPTED',
                [
                    'context' => $context,
                    'data_length' => strlen($plaintext),
                    'cipher_method' => self::CIPHER_METHOD
                ],
                null,
                AuditLogger::LEVEL_INFO,
                AuditLogger::CATEGORY_DATA_MODIFICATION
            );
            
            return [
                'success' => true,
                'decrypted_data' => $plaintext
            ];
            
        } catch (Exception $e) {
            $this->auditLogger->logSecurityEvent(
                'DECRYPTION_ERROR',
                $_SESSION['usuario_id'] ?? null,
                [
                    'context' => $context,
                    'error' => $e->getMessage()
                ],
                AuditLogger::LEVEL_ERROR
            );
            
            return [
                'success' => false,
                'error' => 'Decryption failed'
            ];
        }
    }
    
    /**
     * Cifra campos sensibles antes de inserción en base de datos
     */
    public function encryptSensitiveFields(string $tableName, array $data): array
    {
        if (!isset(self::ENCRYPTED_FIELDS[$tableName])) {
            return $data;
        }
        
        $fieldsToEncrypt = self::ENCRYPTED_FIELDS[$tableName];
        $encryptedData = $data;
        
        foreach ($fieldsToEncrypt as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $result = $this->encrypt($data[$field], $tableName . '_' . $field);
                
                if ($result['success']) {
                    $encryptedData[$field] = $result['encrypted_data'];
                } else {
                    // Log error pero continúa sin cifrar
                    $this->auditLogger->logSecurityEvent(
                        'FIELD_ENCRYPTION_FAILED',
                        $_SESSION['usuario_id'] ?? null,
                        [
                            'table' => $tableName,
                            'field' => $field,
                            'error' => $result['error']
                        ],
                        AuditLogger::LEVEL_WARNING
                    );
                }
            }
        }
        
        return $encryptedData;
    }
    
    /**
     * Descifra campos sensibles después de consulta en base de datos
     */
    public function decryptSensitiveFields(string $tableName, array $data): array
    {
        if (!isset(self::ENCRYPTED_FIELDS[$tableName])) {
            return $data;
        }
        
        $fieldsToDecrypt = self::ENCRYPTED_FIELDS[$tableName];
        $decryptedData = $data;
        
        foreach ($fieldsToDecrypt as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $result = $this->decrypt($data[$field], $tableName . '_' . $field);
                
                if ($result['success']) {
                    $decryptedData[$field] = $result['decrypted_data'];
                } else {
                    // Mantener dato cifrado si no se puede descifrar
                    $this->auditLogger->logSecurityEvent(
                        'FIELD_DECRYPTION_FAILED',
                        $_SESSION['usuario_id'] ?? null,
                        [
                            'table' => $tableName,
                            'field' => $field,
                            'error' => $result['error']
                        ],
                        AuditLogger::LEVEL_WARNING
                    );
                }
            }
        }
        
        return $decryptedData;
    }
    
    /**
     * Verifica que SSL/TLS esté configurado correctamente
     */
    public function verifySslConfiguration(): array
    {
        $results = [];
        
        // Verificar HTTPS
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                || $_SERVER['SERVER_PORT'] == 443;
        
        $results['https_enabled'] = $isHttps;
        
        if ($isHttps) {
            // Verificar certificado SSL
            $context = stream_context_create([
                'ssl' => [
                    'capture_peer_cert' => true,
                    'verify_peer' => true,
                    'verify_peer_name' => true
                ]
            ]);
            
            $hostname = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $connection = @stream_socket_client(
                "ssl://{$hostname}:443",
                $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context
            );
            
            if ($connection) {
                $params = stream_context_get_params($connection);
                $cert = $params['options']['ssl']['peer_certificate'];
                
                $certInfo = openssl_x509_parse($cert);
                $results['certificate_valid'] = true;
                $results['certificate_expires'] = date('Y-m-d H:i:s', $certInfo['validTo_time_t']);
                $results['certificate_issuer'] = $certInfo['issuer']['CN'] ?? 'Unknown';
                
                // Verificar si el certificado expira pronto (30 días)
                $expiresIn = ($certInfo['validTo_time_t'] - time()) / (24 * 60 * 60);
                $results['certificate_expires_soon'] = $expiresIn < 30;
                
                fclose($connection);
            } else {
                $results['certificate_valid'] = false;
                $results['ssl_error'] = $errstr;
            }
        }
        
        // Verificar configuración de base de datos SSL
        $sslResult = $this->conn->query("SHOW STATUS LIKE 'Ssl_cipher'");
        if ($sslResult && $row = $sslResult->fetch_assoc()) {
            $results['database_ssl'] = !empty($row['Value']);
            $results['database_ssl_cipher'] = $row['Value'];
        } else {
            $results['database_ssl'] = false;
        }
        
        // Log de verificación
        $this->auditLogger->logActivity(
            $_SESSION['usuario_id'] ?? null,
            'SSL_VERIFICATION',
            $results,
            null,
            AuditLogger::LEVEL_INFO,
            AuditLogger::CATEGORY_SECURITY_EVENT
        );
        
        return $results;
    }
    
    /**
     * Rota la clave maestra de cifrado
     */
    public function rotateMasterKey(): array
    {
        try {
            $oldMasterKey = $this->masterKey;
            
            // Generar nueva clave maestra
            $newMasterKey = random_bytes(self::KEY_LENGTH);
            $encodedNewKey = base64_encode($newMasterKey);
            
            // Obtener todos los datos cifrados que necesitan re-cifrado
            $dataToReencrypt = $this->getEncryptedData();
            
            $this->conn->begin_transaction();
            
            // Actualizar clave maestra
            $this->updateMasterKey($encodedNewKey);
            $this->masterKey = $newMasterKey;
            
            // Re-cifrar todos los datos
            $reencryptedCount = 0;
            foreach ($dataToReencrypt as $item) {
                if ($this->reencryptData($item, $oldMasterKey)) {
                    $reencryptedCount++;
                }
            }
            
            $this->conn->commit();
            
            $this->auditLogger->logActivity(
                $_SESSION['usuario_id'] ?? null,
                'MASTER_KEY_ROTATED',
                [
                    'reencrypted_items' => $reencryptedCount,
                    'total_items' => count($dataToReencrypt)
                ],
                null,
                AuditLogger::LEVEL_CRITICAL,
                AuditLogger::CATEGORY_SECURITY_EVENT
            );
            
            return [
                'success' => true,
                'reencrypted_items' => $reencryptedCount
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            
            $this->auditLogger->logActivity(
                $_SESSION['usuario_id'] ?? null,
                'MASTER_KEY_ROTATION_FAILED',
                ['error' => $e->getMessage()],
                null,
                AuditLogger::LEVEL_CRITICAL,
                AuditLogger::CATEGORY_SECURITY_EVENT
            );
            
            return [
                'success' => false,
                'error' => 'Key rotation failed'
            ];
        }
    }
    
    /**
     * Obtiene estadísticas de cifrado
     */
    public function getEncryptionStatistics(): array
    {
        $stats = [];
        
        foreach (self::ENCRYPTED_FIELDS as $table => $fields) {
            foreach ($fields as $field) {
                $stmt = $this->conn->prepare("
                    SELECT COUNT(*) as total,
                           SUM(CASE WHEN {$field} IS NOT NULL AND {$field} != '' THEN 1 ELSE 0 END) as encrypted
                    FROM {$table}
                ");
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                
                $stats[$table][$field] = [
                    'total_records' => (int)$result['total'],
                    'encrypted_records' => (int)$result['encrypted'],
                    'encryption_percentage' => $result['total'] > 0 
                        ? round(($result['encrypted'] / $result['total']) * 100, 2) 
                        : 0
                ];
            }
        }
        
        return $stats;
    }
    
    /**
     * Inicializa la clave maestra de cifrado
     */
    private function initializeMasterKey(): void
    {
        $keyFile = __DIR__ . '/../../../../storage/encryption/master.key';
        
        if (!file_exists($keyFile)) {
            // Generar nueva clave maestra
            $masterKey = random_bytes(self::KEY_LENGTH);
            $encodedKey = base64_encode($masterKey);
            
            // Crear directorio si no existe
            $keyDir = dirname($keyFile);
            if (!is_dir($keyDir)) {
                mkdir($keyDir, 0700, true);
            }
            
            // Guardar clave con permisos restrictivos
            file_put_contents($keyFile, $encodedKey);
            chmod($keyFile, 0600);
            
            $this->masterKey = $masterKey;
            
            $this->auditLogger->logActivity(
                null,
                'MASTER_KEY_GENERATED',
                ['key_file' => $keyFile],
                null,
                AuditLogger::LEVEL_CRITICAL,
                AuditLogger::CATEGORY_SECURITY_EVENT
            );
        } else {
            // Cargar clave existente
            $encodedKey = file_get_contents($keyFile);
            $this->masterKey = base64_decode($encodedKey);
        }
    }
    
    /**
     * Deriva una clave específica del contexto
     */
    private function deriveKey(?string $context = null): string
    {
        $salt = 'SBL_ENCRYPTION_SALT_' . ($context ?? 'default');
        return hash_pbkdf2('sha256', $this->masterKey, $salt, 10000, self::KEY_LENGTH, true);
    }
    
    /**
     * Inicializa las tablas de cifrado
     */
    private function initializeEncryptionTables(): void
    {
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS encryption_keys (
                id INT AUTO_INCREMENT PRIMARY KEY,
                key_name VARCHAR(100) NOT NULL UNIQUE,
                encrypted_key TEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                rotated_at DATETIME NULL,
                is_active BOOLEAN DEFAULT TRUE,
                INDEX idx_key_name (key_name),
                INDEX idx_is_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
    
    /**
     * Obtiene datos cifrados que necesitan re-cifrado
     */
    private function getEncryptedData(): array
    {
        $data = [];
        
        foreach (self::ENCRYPTED_FIELDS as $table => $fields) {
            foreach ($fields as $field) {
                $stmt = $this->conn->prepare("
                    SELECT id, {$field} 
                    FROM {$table} 
                    WHERE {$field} IS NOT NULL AND {$field} != ''
                ");
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $data[] = [
                        'table' => $table,
                        'field' => $field,
                        'id' => $row['id'],
                        'encrypted_data' => $row[$field]
                    ];
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Re-cifra datos con nueva clave
     */
    private function reencryptData(array $item, string $oldMasterKey): bool
    {
        try {
            // Descifrar con clave anterior
            $oldKey = hash_pbkdf2('sha256', $oldMasterKey, 'SBL_ENCRYPTION_SALT_' . $item['table'] . '_' . $item['field'], 10000, self::KEY_LENGTH, true);
            
            $data = base64_decode($item['encrypted_data']);
            $iv = substr($data, 0, self::IV_LENGTH);
            $tag = substr($data, self::IV_LENGTH, self::TAG_LENGTH);
            $ciphertext = substr($data, self::IV_LENGTH + self::TAG_LENGTH);
            
            $plaintext = openssl_decrypt($ciphertext, self::CIPHER_METHOD, $oldKey, OPENSSL_RAW_DATA, $iv, $tag);
            
            if ($plaintext === false) {
                return false;
            }
            
            // Cifrar con nueva clave
            $result = $this->encrypt($plaintext, $item['table'] . '_' . $item['field']);
            
            if (!$result['success']) {
                return false;
            }
            
            // Actualizar en base de datos
            $stmt = $this->conn->prepare("UPDATE {$item['table']} SET {$item['field']} = ? WHERE id = ?");
            $stmt->bind_param('si', $result['encrypted_data'], $item['id']);
            
            return $stmt->execute();
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Actualiza la clave maestra en el archivo
     */
    private function updateMasterKey(string $encodedKey): void
    {
        $keyFile = __DIR__ . '/../../../../storage/encryption/master.key';
        file_put_contents($keyFile, $encodedKey);
        chmod($keyFile, 0600);
    }
}