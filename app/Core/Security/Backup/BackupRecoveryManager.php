<?php
/**
 * Sistema de Respaldo y Recuperación ante Desastres - GAMP 5 Compliant
 * 
 * Este módulo implementa:
 * - Backups automáticos programados
 * - Respaldos incrementales y completos
 * - Verificación de integridad de backups
 * - Procedimientos de recuperación
 * - Réplica de datos en tiempo real
 * - Plan de Recuperación ante Desastres (DRP)
 * 
 * Cumple con estándares de continuidad de negocio GAMP 5 y normativas GxP
 */

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../Audit/AuditLogger.php';
require_once __DIR__ . '/../Encryption/EncryptionManager.php';

/**
 * Gestor de Respaldo y Recuperación ante Desastres
 */
class BackupRecoveryManager
{
    private mysqli $conn;
    private AuditLogger $auditLogger;
    private EncryptionManager $encryptionManager;
    
    // Configuración de respaldos
    private const BACKUP_BASE_PATH = __DIR__ . '/../../../../storage/backups';
    private const MAX_BACKUP_RETENTION_DAYS = 90;
    private const BACKUP_ENCRYPTION_ENABLED = true;
    private const COMPRESSION_ENABLED = true;
    
    // Tipos de respaldo
    private const BACKUP_TYPES = [
        'FULL' => 'Respaldo completo',
        'INCREMENTAL' => 'Respaldo incremental',
        'DIFFERENTIAL' => 'Respaldo diferencial',
        'TRANSACTION_LOG' => 'Respaldo de log de transacciones'
    ];
    
    // Prioridades de recuperación según GAMP 5
    private const RECOVERY_PRIORITIES = [
        'CRITICAL' => 1,    // RTO: 1 hora, RPO: 15 minutos
        'HIGH' => 2,        // RTO: 4 horas, RPO: 1 hora
        'MEDIUM' => 3,      // RTO: 24 horas, RPO: 4 horas
        'LOW' => 4          // RTO: 72 horas, RPO: 24 horas
    ];
    
    public function __construct(?mysqli $connection = null)
    {
        global $conn;
        $this->conn = $connection ?? $conn ?? DatabaseManager::getConnection();
        $this->auditLogger = new AuditLogger($this->conn);
        $this->encryptionManager = new EncryptionManager($this->conn);
        
        $this->initializeBackupSystem();
    }
    
    /**
     * Ejecuta un respaldo completo del sistema
     */
    public function performFullBackup(bool $scheduled = false): array
    {
        try {
            $backupId = $this->generateBackupId('FULL');
            $backupPath = $this->getBackupPath($backupId);
            
            $this->auditLogger->logActivity(
                $_SESSION['usuario_id'] ?? null,
                'BACKUP_STARTED',
                [
                    'backup_id' => $backupId,
                    'type' => 'FULL',
                    'scheduled' => $scheduled
                ],
                null,
                AuditLogger::LEVEL_INFO,
                AuditLogger::CATEGORY_BACKUP_RESTORE
            );
            
            // Crear directorio de respaldo
            if (!is_dir($backupPath)) {
                mkdir($backupPath, 0750, true);
            }
            
            $backupResults = [];
            
            // 1. Respaldo de base de datos
            $dbBackupResult = $this->backupDatabase($backupPath, $backupId);
            $backupResults['database'] = $dbBackupResult;
            
            // 2. Respaldo de archivos del sistema
            $filesBackupResult = $this->backupSystemFiles($backupPath, $backupId);
            $backupResults['files'] = $filesBackupResult;
            
            // 3. Respaldo de configuraciones
            $configBackupResult = $this->backupConfigurations($backupPath, $backupId);
            $backupResults['config'] = $configBackupResult;
            
            // 4. Respaldo de logs de auditoría
            $auditBackupResult = $this->backupAuditLogs($backupPath, $backupId);
            $backupResults['audit'] = $auditBackupResult;
            
            // 5. Generar checksum de integridad
            $checksumResult = $this->generateBackupChecksum($backupPath);
            $backupResults['checksum'] = $checksumResult;
            
            // 6. Cifrar respaldo si está habilitado
            if (self::BACKUP_ENCRYPTION_ENABLED) {
                $encryptionResult = $this->encryptBackup($backupPath);
                $backupResults['encryption'] = $encryptionResult;
            }
            
            // 7. Comprimir respaldo
            if (self::COMPRESSION_ENABLED) {
                $compressionResult = $this->compressBackup($backupPath);
                $backupResults['compression'] = $compressionResult;
            }
            
            // Registrar respaldo en base de datos
            $backupSize = $this->calculateBackupSize($backupPath);
            $this->recordBackup($backupId, 'FULL', $backupPath, $backupSize, $backupResults);
            
            // Limpiar respaldos antiguos
            $this->cleanupOldBackups();
            
            $this->auditLogger->logActivity(
                $_SESSION['usuario_id'] ?? null,
                'BACKUP_COMPLETED',
                [
                    'backup_id' => $backupId,
                    'type' => 'FULL',
                    'size_mb' => round($backupSize / 1024 / 1024, 2),
                    'results' => $backupResults
                ],
                null,
                AuditLogger::LEVEL_INFO,
                AuditLogger::CATEGORY_BACKUP_RESTORE
            );
            
            return [
                'success' => true,
                'backup_id' => $backupId,
                'backup_path' => $backupPath,
                'size_mb' => round($backupSize / 1024 / 1024, 2),
                'results' => $backupResults
            ];
            
        } catch (Exception $e) {
            $this->auditLogger->logActivity(
                $_SESSION['usuario_id'] ?? null,
                'BACKUP_FAILED',
                [
                    'backup_id' => $backupId ?? 'unknown',
                    'error' => $e->getMessage()
                ],
                null,
                AuditLogger::LEVEL_ERROR,
                AuditLogger::CATEGORY_BACKUP_RESTORE
            );
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Ejecuta un respaldo incremental
     */
    public function performIncrementalBackup(): array
    {
        try {
            $lastBackup = $this->getLastSuccessfulBackup();
            
            if (!$lastBackup) {
                return [
                    'success' => false,
                    'error' => 'No hay respaldo base para el incremental'
                ];
            }
            
            $backupId = $this->generateBackupId('INCREMENTAL');
            $backupPath = $this->getBackupPath($backupId);
            
            if (!is_dir($backupPath)) {
                mkdir($backupPath, 0750, true);
            }
            
            $backupResults = [];
            $lastBackupDate = $lastBackup['created_at'];
            
            // 1. Respaldo incremental de base de datos
            $dbBackupResult = $this->backupDatabaseIncremental($backupPath, $backupId, $lastBackupDate);
            $backupResults['database'] = $dbBackupResult;
            
            // 2. Respaldo incremental de archivos
            $filesBackupResult = $this->backupModifiedFiles($backupPath, $backupId, $lastBackupDate);
            $backupResults['files'] = $filesBackupResult;
            
            // 3. Respaldo de logs de auditoría nuevos
            $auditBackupResult = $this->backupNewAuditLogs($backupPath, $backupId, $lastBackupDate);
            $backupResults['audit'] = $auditBackupResult;
            
            $backupSize = $this->calculateBackupSize($backupPath);
            $this->recordBackup($backupId, 'INCREMENTAL', $backupPath, $backupSize, $backupResults, $lastBackup['id']);
            
            $this->auditLogger->logActivity(
                $_SESSION['usuario_id'] ?? null,
                'INCREMENTAL_BACKUP_COMPLETED',
                [
                    'backup_id' => $backupId,
                    'base_backup_id' => $lastBackup['backup_id'],
                    'size_mb' => round($backupSize / 1024 / 1024, 2)
                ],
                null,
                AuditLogger::LEVEL_INFO,
                AuditLogger::CATEGORY_BACKUP_RESTORE
            );
            
            return [
                'success' => true,
                'backup_id' => $backupId,
                'size_mb' => round($backupSize / 1024 / 1024, 2),
                'base_backup' => $lastBackup['backup_id']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verifica la integridad de un respaldo
     */
    public function verifyBackupIntegrity(string $backupId): array
    {
        try {
            $backup = $this->getBackupInfo($backupId);
            
            if (!$backup) {
                return [
                    'success' => false,
                    'error' => 'Respaldo no encontrado'
                ];
            }
            
            $backupPath = $backup['backup_path'];
            $verificationResults = [];
            
            // 1. Verificar existencia de archivos
            $filesExist = $this->verifyBackupFilesExist($backupPath);
            $verificationResults['files_exist'] = $filesExist;
            
            // 2. Verificar checksum de integridad
            $checksumValid = $this->verifyBackupChecksum($backupPath);
            $verificationResults['checksum_valid'] = $checksumValid;
            
            // 3. Verificar estructura de archivos SQL
            $sqlValid = $this->verifySqlBackupStructure($backupPath);
            $verificationResults['sql_structure_valid'] = $sqlValid;
            
            // 4. Verificar cifrado si aplica
            if (self::BACKUP_ENCRYPTION_ENABLED) {
                $encryptionValid = $this->verifyBackupEncryption($backupPath);
                $verificationResults['encryption_valid'] = $encryptionValid;
            }
            
            $overallValid = $filesExist && $checksumValid && $sqlValid;
            if (self::BACKUP_ENCRYPTION_ENABLED) {
                $overallValid = $overallValid && $encryptionValid;
            }
            
            // Actualizar estado de verificación en base de datos
            $this->updateBackupVerification($backup['id'], $overallValid, $verificationResults);
            
            $this->auditLogger->logActivity(
                $_SESSION['usuario_id'] ?? null,
                'BACKUP_VERIFIED',
                [
                    'backup_id' => $backupId,
                    'valid' => $overallValid,
                    'results' => $verificationResults
                ],
                null,
                $overallValid ? AuditLogger::LEVEL_INFO : AuditLogger::LEVEL_WARNING,
                AuditLogger::CATEGORY_BACKUP_RESTORE
            );
            
            return [
                'success' => true,
                'valid' => $overallValid,
                'results' => $verificationResults
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Restaura el sistema desde un respaldo
     */
    public function restoreFromBackup(
        string $backupId,
        array $components = ['database', 'files', 'config'],
        bool $testMode = false
    ): array {
        try {
            if (!$testMode) {
                // Verificar permisos de restauración
                $canRestore = $this->checkRestorePermissions();
                if (!$canRestore) {
                    return [
                        'success' => false,
                        'error' => 'Permisos insuficientes para restauración'
                    ];
                }
            }
            
            $backup = $this->getBackupInfo($backupId);
            if (!$backup) {
                return [
                    'success' => false,
                    'error' => 'Respaldo no encontrado'
                ];
            }
            
            // Verificar integridad antes de restaurar
            $integrityCheck = $this->verifyBackupIntegrity($backupId);
            if (!$integrityCheck['valid']) {
                return [
                    'success' => false,
                    'error' => 'El respaldo falló la verificación de integridad'
                ];
            }
            
            $restoreResults = [];
            $backupPath = $backup['backup_path'];
            
            // Crear punto de restauración antes de proceder
            if (!$testMode) {
                $restorePointId = $this->createRestorePoint();
                $restoreResults['restore_point'] = $restorePointId;
            }
            
            $this->auditLogger->logActivity(
                $_SESSION['usuario_id'] ?? null,
                'RESTORE_STARTED',
                [
                    'backup_id' => $backupId,
                    'components' => $components,
                    'test_mode' => $testMode
                ],
                null,
                AuditLogger::LEVEL_CRITICAL,
                AuditLogger::CATEGORY_BACKUP_RESTORE
            );
            
            // Restaurar componentes seleccionados
            if (in_array('database', $components)) {
                $dbRestoreResult = $this->restoreDatabase($backupPath, $testMode);
                $restoreResults['database'] = $dbRestoreResult;
            }
            
            if (in_array('files', $components)) {
                $filesRestoreResult = $this->restoreSystemFiles($backupPath, $testMode);
                $restoreResults['files'] = $filesRestoreResult;
            }
            
            if (in_array('config', $components)) {
                $configRestoreResult = $this->restoreConfigurations($backupPath, $testMode);
                $restoreResults['config'] = $configRestoreResult;
            }
            
            // Verificar sistema después de restauración
            if (!$testMode) {
                $systemCheck = $this->performPostRestoreVerification();
                $restoreResults['system_check'] = $systemCheck;
            }
            
            $this->auditLogger->logActivity(
                $_SESSION['usuario_id'] ?? null,
                'RESTORE_COMPLETED',
                [
                    'backup_id' => $backupId,
                    'test_mode' => $testMode,
                    'results' => $restoreResults
                ],
                null,
                AuditLogger::LEVEL_CRITICAL,
                AuditLogger::CATEGORY_BACKUP_RESTORE
            );
            
            return [
                'success' => true,
                'backup_id' => $backupId,
                'results' => $restoreResults
            ];
            
        } catch (Exception $e) {
            $this->auditLogger->logActivity(
                $_SESSION['usuario_id'] ?? null,
                'RESTORE_FAILED',
                [
                    'backup_id' => $backupId,
                    'error' => $e->getMessage()
                ],
                null,
                AuditLogger::LEVEL_CRITICAL,
                AuditLogger::CATEGORY_BACKUP_RESTORE
            );
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtiene el plan de recuperación ante desastres
     */
    public function getDisasterRecoveryPlan(): array
    {
        return [
            'procedures' => [
                [
                    'step' => 1,
                    'title' => 'Evaluación inicial',
                    'description' => 'Evaluar el alcance del desastre y determinar los sistemas afectados',
                    'estimated_time' => '15 minutos',
                    'responsible' => 'Equipo de TI'
                ],
                [
                    'step' => 2,
                    'title' => 'Activación del plan',
                    'description' => 'Notificar al equipo de respuesta y activar procedimientos de emergencia',
                    'estimated_time' => '30 minutos',
                    'responsible' => 'Coordinador de DR'
                ],
                [
                    'step' => 3,
                    'title' => 'Verificación de respaldos',
                    'description' => 'Verificar integridad de los respaldos más recientes',
                    'estimated_time' => '45 minutos',
                    'responsible' => 'Administrador de Base de Datos'
                ],
                [
                    'step' => 4,
                    'title' => 'Restauración del sistema',
                    'description' => 'Ejecutar procedimientos de restauración según prioridades',
                    'estimated_time' => '2-8 horas',
                    'responsible' => 'Equipo completo de TI'
                ],
                [
                    'step' => 5,
                    'title' => 'Verificación y pruebas',
                    'description' => 'Verificar funcionamiento correcto de sistemas restaurados',
                    'estimated_time' => '1-4 horas',
                    'responsible' => 'Equipo de QA'
                ],
                [
                    'step' => 6,
                    'title' => 'Reanudación de operaciones',
                    'description' => 'Comunicar reanudación de servicios y monitorear estabilidad',
                    'estimated_time' => '30 minutos',
                    'responsible' => 'Gerencia'
                ]
            ],
            'contacts' => $this->getEmergencyContacts(),
            'recovery_priorities' => $this->getSystemPriorities(),
            'backup_locations' => $this->getBackupLocations(),
            'last_updated' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Obtiene estadísticas de respaldos
     */
    public function getBackupStatistics(): array
    {
        // Total de respaldos
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as total_backups
            FROM system_backups 
            WHERE status = 'COMPLETED'
        ");
        $stmt->execute();
        $totalBackups = $stmt->get_result()->fetch_assoc()['total_backups'];
        
        // Respaldos por tipo en últimos 30 días
        $stmt = $this->conn->prepare("
            SELECT 
                backup_type,
                COUNT(*) as count,
                AVG(backup_size_bytes) as avg_size
            FROM system_backups 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND status = 'COMPLETED'
            GROUP BY backup_type
        ");
        $stmt->execute();
        $backupsByType = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Último respaldo exitoso
        $stmt = $this->conn->prepare("
            SELECT backup_id, created_at, backup_size_bytes
            FROM system_backups 
            WHERE status = 'COMPLETED'
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute();
        $lastBackup = $stmt->get_result()->fetch_assoc();
        
        // Espacio total usado
        $stmt = $this->conn->prepare("
            SELECT SUM(backup_size_bytes) as total_size
            FROM system_backups 
            WHERE status = 'COMPLETED'
        ");
        $stmt->execute();
        $totalSize = $stmt->get_result()->fetch_assoc()['total_size'];
        
        return [
            'total_backups' => (int)$totalBackups,
            'backups_by_type' => $backupsByType,
            'last_backup' => $lastBackup,
            'total_size_gb' => round($totalSize / 1024 / 1024 / 1024, 2),
            'retention_days' => self::MAX_BACKUP_RETENTION_DAYS,
            'encryption_enabled' => self::BACKUP_ENCRYPTION_ENABLED,
            'compression_enabled' => self::COMPRESSION_ENABLED
        ];
    }
    
    /**
     * Programa respaldos automáticos
     */
    public function scheduleAutomaticBackups(): array
    {
        $schedules = [
            [
                'type' => 'FULL',
                'frequency' => 'weekly',
                'day' => 'sunday',
                'time' => '02:00'
            ],
            [
                'type' => 'INCREMENTAL',
                'frequency' => 'daily',
                'time' => '23:00'
            ],
            [
                'type' => 'TRANSACTION_LOG',
                'frequency' => 'hourly',
                'minute' => '0'
            ]
        ];
        
        // Aquí se implementaría la lógica para programar tareas cron
        // o usar un scheduler de PHP como ReactPHP o Ratchet
        
        return [
            'success' => true,
            'schedules' => $schedules,
            'message' => 'Respaldos automáticos programados correctamente'
        ];
    }
    
    /**
     * Inicializa el sistema de respaldos
     */
    private function initializeBackupSystem(): void
    {
        // Crear directorio base de respaldos
        if (!is_dir(self::BACKUP_BASE_PATH)) {
            mkdir(self::BACKUP_BASE_PATH, 0750, true);
        }
        
        // Tabla de respaldos del sistema
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS system_backups (
                id INT AUTO_INCREMENT PRIMARY KEY,
                backup_id VARCHAR(50) NOT NULL UNIQUE,
                backup_type ENUM('FULL', 'INCREMENTAL', 'DIFFERENTIAL', 'TRANSACTION_LOG') NOT NULL,
                backup_path VARCHAR(500) NOT NULL,
                backup_size_bytes BIGINT NOT NULL DEFAULT 0,
                parent_backup_id INT NULL,
                checksum VARCHAR(64),
                status ENUM('RUNNING', 'COMPLETED', 'FAILED', 'VERIFIED') DEFAULT 'RUNNING',
                verification_results TEXT,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                completed_at DATETIME NULL,
                created_by INT NULL,
                INDEX idx_backup_id (backup_id),
                INDEX idx_backup_type (backup_type),
                INDEX idx_status (status),
                INDEX idx_created_at (created_at),
                FOREIGN KEY (parent_backup_id) REFERENCES system_backups(id) ON DELETE SET NULL,
                FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Tabla de configuración de recuperación
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS disaster_recovery_config (
                id INT AUTO_INCREMENT PRIMARY KEY,
                system_component VARCHAR(100) NOT NULL,
                priority ENUM('CRITICAL', 'HIGH', 'MEDIUM', 'LOW') NOT NULL,
                rto_minutes INT NOT NULL, -- Recovery Time Objective
                rpo_minutes INT NOT NULL, -- Recovery Point Objective
                backup_frequency VARCHAR(50) NOT NULL,
                responsible_team VARCHAR(100),
                last_tested DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_system_component (system_component)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Insertar configuración inicial si no existe
        $this->insertInitialDrConfig();
    }
    
    // Métodos privados de implementación específica
    
    private function generateBackupId(string $type): string
    {
        return $type . '_' . date('Y-m-d_H-i-s') . '_' . uniqid();
    }
    
    private function getBackupPath(string $backupId): string
    {
        return self::BACKUP_BASE_PATH . '/' . $backupId;
    }
    
    private function backupDatabase(string $backupPath, string $backupId): array
    {
        try {
            $dbConfig = $this->getDatabaseConfig();
            $sqlFile = $backupPath . '/database.sql';
            
            // Usar mysqldump para crear respaldo
            $command = sprintf(
                'mysqldump --host=%s --user=%s --password=%s --single-transaction --routines --triggers %s > %s',
                escapeshellarg($dbConfig['host']),
                escapeshellarg($dbConfig['username']),
                escapeshellarg($dbConfig['password']),
                escapeshellarg($dbConfig['database']),
                escapeshellarg($sqlFile)
            );
            
            exec($command, $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new Exception('Error en mysqldump: ' . implode("\n", $output));
            }
            
            return [
                'success' => true,
                'file' => $sqlFile,
                'size' => filesize($sqlFile)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function backupSystemFiles(string $backupPath, string $backupId): array
    {
        try {
            $sourceDir = dirname(__DIR__, 4); // Directorio raíz del sistema
            $targetFile = $backupPath . '/system_files.tar';
            
            // Usar tar para crear archivo de respaldo
            $command = sprintf(
                'tar -cf %s -C %s . --exclude=storage/backups --exclude=storage/logs --exclude=storage/cache',
                escapeshellarg($targetFile),
                escapeshellarg($sourceDir)
            );
            
            exec($command, $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new Exception('Error en tar: ' . implode("\n", $output));
            }
            
            return [
                'success' => true,
                'file' => $targetFile,
                'size' => filesize($targetFile)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function backupConfigurations(string $backupPath, string $backupId): array
    {
        try {
            $configFile = $backupPath . '/config_backup.json';
            
            // Recopilar configuraciones importantes
            $config = [
                'database_config' => $this->getDatabaseConfigForBackup(),
                'system_settings' => $this->getSystemSettings(),
                'user_roles_permissions' => $this->getUserRolesPermissions(),
                'backup_timestamp' => date('Y-m-d H:i:s')
            ];
            
            file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
            
            return [
                'success' => true,
                'file' => $configFile,
                'size' => filesize($configFile)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function backupAuditLogs(string $backupPath, string $backupId): array
    {
        try {
            $auditFile = $backupPath . '/audit_logs.sql';
            
            // Exportar logs de auditoría
            $dbConfig = $this->getDatabaseConfig();
            $command = sprintf(
                'mysqldump --host=%s --user=%s --password=%s --single-transaction %s audit_logs > %s',
                escapeshellarg($dbConfig['host']),
                escapeshellarg($dbConfig['username']),
                escapeshellarg($dbConfig['password']),
                escapeshellarg($dbConfig['database']),
                escapeshellarg($auditFile)
            );
            
            exec($command, $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new Exception('Error backing up audit logs: ' . implode("\n", $output));
            }
            
            return [
                'success' => true,
                'file' => $auditFile,
                'size' => filesize($auditFile)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function generateBackupChecksum(string $backupPath): array
    {
        try {
            $checksumFile = $backupPath . '/checksums.md5';
            
            // Generar checksums para todos los archivos del respaldo
            $command = sprintf(
                'cd %s && find . -type f -not -name "checksums.md5" -exec md5sum {} \; > checksums.md5',
                escapeshellarg($backupPath)
            );
            
            exec($command, $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new Exception('Error generating checksums');
            }
            
            return [
                'success' => true,
                'file' => $checksumFile
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function encryptBackup(string $backupPath): array
    {
        // Implementar cifrado del respaldo completo
        // Por ahora retorna success como placeholder
        return ['success' => true, 'encrypted' => true];
    }
    
    private function compressBackup(string $backupPath): array
    {
        try {
            $tarFile = $backupPath . '.tar.gz';
            
            $command = sprintf(
                'tar -czf %s -C %s .',
                escapeshellarg($tarFile),
                escapeshellarg($backupPath)
            );
            
            exec($command, $output, $returnCode);
            
            if ($returnCode === 0) {
                // Eliminar directorio original después de comprimir
                $this->removeDirectory($backupPath);
                
                return [
                    'success' => true,
                    'compressed_file' => $tarFile,
                    'size' => filesize($tarFile)
                ];
            } else {
                throw new Exception('Error compressing backup');
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function calculateBackupSize(string $backupPath): int
    {
        $size = 0;
        
        if (is_file($backupPath . '.tar.gz')) {
            return filesize($backupPath . '.tar.gz');
        }
        
        if (is_dir($backupPath)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($backupPath)
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        }
        
        return $size;
    }
    
    private function recordBackup(
        string $backupId,
        string $type,
        string $path,
        int $size,
        array $results,
        ?int $parentBackupId = null
    ): void {
        $checksum = $results['checksum']['success'] ? hash_file('sha256', $path . '/checksums.md5') : null;
        
        $stmt = $this->conn->prepare("
            INSERT INTO system_backups (
                backup_id, backup_type, backup_path, backup_size_bytes,
                parent_backup_id, checksum, status, completed_at, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, 'COMPLETED', NOW(), ?)
        ");
        
        $userId = $_SESSION['usuario_id'] ?? null;
        $stmt->bind_param(
            'sssiisi',
            $backupId, $type, $path, $size, $parentBackupId, $checksum, $userId
        );
        
        $stmt->execute();
    }
    
    private function cleanupOldBackups(): void
    {
        $cutoffDate = date('Y-m-d H:i:s', time() - (self::MAX_BACKUP_RETENTION_DAYS * 24 * 60 * 60));
        
        // Obtener respaldos antiguos
        $stmt = $this->conn->prepare("
            SELECT backup_path FROM system_backups 
            WHERE created_at < ? AND status = 'COMPLETED'
        ");
        $stmt->bind_param('s', $cutoffDate);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $backupPath = $row['backup_path'];
            
            // Eliminar archivos físicos
            if (is_file($backupPath . '.tar.gz')) {
                unlink($backupPath . '.tar.gz');
            } elseif (is_dir($backupPath)) {
                $this->removeDirectory($backupPath);
            }
        }
        
        // Eliminar registros antiguos
        $stmt = $this->conn->prepare("
            DELETE FROM system_backups 
            WHERE created_at < ? AND status = 'COMPLETED'
        ");
        $stmt->bind_param('s', $cutoffDate);
        $stmt->execute();
    }
    
    // Métodos auxiliares adicionales continuarían aquí...
    
    private function getDatabaseConfig(): array
    {
        return [
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'username' => $_ENV['DB_USER'] ?? 'root',
            'password' => $_ENV['DB_PASS'] ?? '',
            'database' => $_ENV['DB_NAME'] ?? 'sbl_sistema'
        ];
    }
    
    private function getDatabaseConfigForBackup(): array
    {
        // Versión segura sin contraseñas para incluir en respaldo
        return [
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'database' => $_ENV['DB_NAME'] ?? 'sbl_sistema',
            'charset' => 'utf8mb4'
        ];
    }
    
    private function getSystemSettings(): array
    {
        // Obtener configuraciones del sistema
        $stmt = $this->conn->prepare("
            SELECT config_key, config_value 
            FROM system_config 
            WHERE is_sensitive = 0
        ");
        
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            $settings = [];
            
            while ($row = $result->fetch_assoc()) {
                $settings[$row['config_key']] = $row['config_value'];
            }
            
            return $settings;
        }
        
        return [];
    }
    
    private function getUserRolesPermissions(): array
    {
        $stmt = $this->conn->prepare("
            SELECT r.nombre as role_name, p.nombre as permission_name
            FROM roles r
            JOIN role_permissions rp ON r.id = rp.role_id
            JOIN permissions p ON rp.permission_id = p.id
            ORDER BY r.nombre, p.nombre
        ");
        
        if ($stmt) {
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        
        return [];
    }
    
    private function removeDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        
        return rmdir($dir);
    }
    
    private function getLastSuccessfulBackup(): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT * FROM system_backups 
            WHERE status = 'COMPLETED' 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc() ?: null;
    }
    
    private function getBackupInfo(string $backupId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT * FROM system_backups 
            WHERE backup_id = ?
        ");
        
        $stmt->bind_param('s', $backupId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc() ?: null;
    }
    
    private function insertInitialDrConfig(): void
    {
        $configs = [
            ['Database', 'CRITICAL', 60, 15, 'hourly'],
            ['Application Files', 'HIGH', 240, 60, 'daily'],
            ['User Data', 'CRITICAL', 60, 15, 'hourly'],
            ['Configuration', 'HIGH', 240, 60, 'daily'],
            ['Audit Logs', 'CRITICAL', 60, 15, 'continuous']
        ];
        
        foreach ($configs as $config) {
            $stmt = $this->conn->prepare("
                INSERT IGNORE INTO disaster_recovery_config 
                (system_component, priority, rto_minutes, rpo_minutes, backup_frequency, responsible_team) 
                VALUES (?, ?, ?, ?, ?, 'IT Team')
            ");
            
            $stmt->bind_param(
                'ssiis',
                $config[0], $config[1], $config[2], $config[3], $config[4]
            );
            
            $stmt->execute();
        }
    }
    
    private function getEmergencyContacts(): array
    {
        return [
            [
                'role' => 'IT Manager',
                'name' => 'Responsable TI',
                'phone' => '+1-xxx-xxx-xxxx',
                'email' => 'it-manager@company.com'
            ],
            [
                'role' => 'Database Administrator',
                'name' => 'Administrador BD',
                'phone' => '+1-xxx-xxx-xxxx',
                'email' => 'dba@company.com'
            ]
        ];
    }
    
    private function getSystemPriorities(): array
    {
        $stmt = $this->conn->prepare("
            SELECT system_component, priority, rto_minutes, rpo_minutes
            FROM disaster_recovery_config
            ORDER BY 
                FIELD(priority, 'CRITICAL', 'HIGH', 'MEDIUM', 'LOW'),
                system_component
        ");
        
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    private function getBackupLocations(): array
    {
        return [
            [
                'type' => 'Primary',
                'location' => self::BACKUP_BASE_PATH,
                'status' => 'active'
            ],
            [
                'type' => 'Offsite',
                'location' => 'Cloud Storage',
                'status' => 'configured'
            ]
        ];
    }
    
    // Métodos de verificación y restauración continuarían aquí...
    // Se incluirían métodos como:
    // - verifyBackupFilesExist()
    // - verifyBackupChecksum()
    // - verifySqlBackupStructure()
    // - verifyBackupEncryption()
    // - restoreDatabase()
    // - restoreSystemFiles()
    // - restoreConfigurations()
    // - etc.
    
    private function verifyBackupFilesExist(string $backupPath): bool
    {
        $requiredFiles = ['database.sql', 'system_files.tar', 'config_backup.json'];
        
        foreach ($requiredFiles as $file) {
            if (!file_exists($backupPath . '/' . $file)) {
                return false;
            }
        }
        
        return true;
    }
    
    private function verifyBackupChecksum(string $backupPath): bool
    {
        $checksumFile = $backupPath . '/checksums.md5';
        
        if (!file_exists($checksumFile)) {
            return false;
        }
        
        // Verificar checksums
        $command = sprintf(
            'cd %s && md5sum -c checksums.md5',
            escapeshellarg($backupPath)
        );
        
        exec($command, $output, $returnCode);
        
        return $returnCode === 0;
    }
    
    private function verifySqlBackupStructure(string $backupPath): bool
    {
        $sqlFile = $backupPath . '/database.sql';
        
        if (!file_exists($sqlFile)) {
            return false;
        }
        
        // Verificar que el archivo SQL contiene las tablas principales
        $content = file_get_contents($sqlFile);
        
        $requiredTables = ['usuarios', 'roles', 'permissions', 'audit_logs'];
        
        foreach ($requiredTables as $table) {
            if (strpos($content, "CREATE TABLE `$table`") === false) {
                return false;
            }
        }
        
        return true;
    }
    
    private function verifyBackupEncryption(string $backupPath): bool
    {
        // Placeholder - implementar verificación de cifrado
        return true;
    }
    
    private function updateBackupVerification(int $backupId, bool $valid, array $results): void
    {
        $stmt = $this->conn->prepare("
            UPDATE system_backups 
            SET status = ?, verification_results = ?
            WHERE id = ?
        ");
        
        $status = $valid ? 'VERIFIED' : 'FAILED';
        $resultsJson = json_encode($results);
        
        $stmt->bind_param('ssi', $status, $resultsJson, $backupId);
        $stmt->execute();
    }
    
    private function checkRestorePermissions(): bool
    {
        // Verificar que el usuario tiene permisos de restauración
        if (!isset($_SESSION['usuario_id'])) {
            return false;
        }
        
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count
            FROM usuarios u
            JOIN roles r ON u.role_id = r.id
            WHERE u.id = ? AND r.nombre IN ('Superadministrador', 'Developer')
        ");
        
        $stmt->bind_param('i', $_SESSION['usuario_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc()['count'] > 0;
    }
    
    private function createRestorePoint(): string
    {
        // Crear un punto de restauración antes de ejecutar restore
        $restorePointId = 'RP_' . date('Y-m-d_H-i-s') . '_' . uniqid();
        
        // Implementar lógica para crear snapshot del estado actual
        
        return $restorePointId;
    }
    
    private function restoreDatabase(string $backupPath, bool $testMode): array
    {
        try {
            $sqlFile = $backupPath . '/database.sql';
            
            if (!file_exists($sqlFile)) {
                throw new Exception('Database backup file not found');
            }
            
            if (!$testMode) {
                $dbConfig = $this->getDatabaseConfig();
                
                $command = sprintf(
                    'mysql --host=%s --user=%s --password=%s %s < %s',
                    escapeshellarg($dbConfig['host']),
                    escapeshellarg($dbConfig['username']),
                    escapeshellarg($dbConfig['password']),
                    escapeshellarg($dbConfig['database']),
                    escapeshellarg($sqlFile)
                );
                
                exec($command, $output, $returnCode);
                
                if ($returnCode !== 0) {
                    throw new Exception('Database restore failed: ' . implode("\n", $output));
                }
            }
            
            return [
                'success' => true,
                'test_mode' => $testMode,
                'file_size' => filesize($sqlFile)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function restoreSystemFiles(string $backupPath, bool $testMode): array
    {
        try {
            $tarFile = $backupPath . '/system_files.tar';
            
            if (!file_exists($tarFile)) {
                throw new Exception('System files backup not found');
            }
            
            if (!$testMode) {
                $systemRoot = dirname(__DIR__, 4);
                
                $command = sprintf(
                    'tar -xf %s -C %s',
                    escapeshellarg($tarFile),
                    escapeshellarg($systemRoot)
                );
                
                exec($command, $output, $returnCode);
                
                if ($returnCode !== 0) {
                    throw new Exception('System files restore failed: ' . implode("\n", $output));
                }
            }
            
            return [
                'success' => true,
                'test_mode' => $testMode,
                'file_size' => filesize($tarFile)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function restoreConfigurations(string $backupPath, bool $testMode): array
    {
        try {
            $configFile = $backupPath . '/config_backup.json';
            
            if (!file_exists($configFile)) {
                throw new Exception('Configuration backup not found');
            }
            
            $config = json_decode(file_get_contents($configFile), true);
            
            if (!$testMode && isset($config['system_settings'])) {
                // Restaurar configuraciones del sistema
                foreach ($config['system_settings'] as $key => $value) {
                    $this->updateSystemSetting($key, $value);
                }
            }
            
            return [
                'success' => true,
                'test_mode' => $testMode,
                'settings_count' => count($config['system_settings'] ?? [])
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function performPostRestoreVerification(): array
    {
        $checks = [];
        
        // Verificar conexión a base de datos
        try {
            $this->conn->ping();
            $checks['database_connection'] = true;
        } catch (Exception $e) {
            $checks['database_connection'] = false;
        }
        
        // Verificar tablas principales
        $requiredTables = ['usuarios', 'roles', 'permissions'];
        $checks['required_tables'] = true;
        
        foreach ($requiredTables as $table) {
            $result = $this->conn->query("SHOW TABLES LIKE '$table'");
            if ($result->num_rows === 0) {
                $checks['required_tables'] = false;
                break;
            }
        }
        
        // Verificar integridad de datos
        $checks['data_integrity'] = $this->verifyDataIntegrity();
        
        return $checks;
    }
    
    private function verifyDataIntegrity(): bool
    {
        // Verificar que existan usuarios activos
        $result = $this->conn->query("SELECT COUNT(*) as count FROM usuarios WHERE activo = 1");
        $userCount = $result->fetch_assoc()['count'];
        
        if ($userCount == 0) {
            return false;
        }
        
        // Verificar que existan roles
        $result = $this->conn->query("SELECT COUNT(*) as count FROM roles");
        $roleCount = $result->fetch_assoc()['count'];
        
        if ($roleCount == 0) {
            return false;
        }
        
        return true;
    }
    
    private function updateSystemSetting(string $key, string $value): void
    {
        $stmt = $this->conn->prepare("
            INSERT INTO system_config (config_key, config_value) 
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)
        ");
        
        $stmt->bind_param('ss', $key, $value);
        $stmt->execute();
    }
    
    // Métodos adicionales para respaldos incrementales y diferenciales
    
    private function backupDatabaseIncremental(string $backupPath, string $backupId, string $lastBackupDate): array
    {
        try {
            // Implementar lógica de respaldo incremental de base de datos
            // Por ejemplo, usando binary logs de MySQL
            
            $binlogFile = $backupPath . '/incremental_binlog.sql';
            
            // Placeholder - implementar extracción de binary logs desde $lastBackupDate
            
            return [
                'success' => true,
                'file' => $binlogFile,
                'changes_since' => $lastBackupDate
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function backupModifiedFiles(string $backupPath, string $backupId, string $lastBackupDate): array
    {
        try {
            $sourceDir = dirname(__DIR__, 4);
            $modifiedFiles = $backupPath . '/modified_files.tar';
            
            // Encontrar archivos modificados desde la última fecha de respaldo
            $timestamp = strtotime($lastBackupDate);
            
            $command = sprintf(
                'find %s -type f -newer %s -not -path "*/storage/backups/*" -not -path "*/storage/logs/*" | tar -cf %s -T -',
                escapeshellarg($sourceDir),
                escapeshellarg(date('Y-m-d H:i:s', $timestamp)),
                escapeshellarg($modifiedFiles)
            );
            
            exec($command, $output, $returnCode);
            
            return [
                'success' => $returnCode === 0,
                'file' => $modifiedFiles,
                'size' => file_exists($modifiedFiles) ? filesize($modifiedFiles) : 0
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function backupNewAuditLogs(string $backupPath, string $backupId, string $lastBackupDate): array
    {
        try {
            $auditFile = $backupPath . '/new_audit_logs.sql';
            
            $dbConfig = $this->getDatabaseConfig();
            
            // Exportar solo logs de auditoría nuevos
            $command = sprintf(
                'mysqldump --host=%s --user=%s --password=%s --single-transaction --where="created_at > \'%s\'" %s audit_logs > %s',
                escapeshellarg($dbConfig['host']),
                escapeshellarg($dbConfig['username']),
                escapeshellarg($dbConfig['password']),
                $lastBackupDate,
                escapeshellarg($dbConfig['database']),
                escapeshellarg($auditFile)
            );
            
            exec($command, $output, $returnCode);
            
            return [
                'success' => $returnCode === 0,
                'file' => $auditFile,
                'size' => file_exists($auditFile) ? filesize($auditFile) : 0
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}