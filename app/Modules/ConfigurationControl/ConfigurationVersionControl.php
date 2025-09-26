<?php
/**
 * Sistema de Control de Versiones para Configuraciones Críticas
 * 
 * Este módulo implementa un sistema de control de versiones para las configuraciones
 * del sistema usando Git y herramientas similares, asegurando que cada cambio de 
 * configuración quede registrado y sea reversible.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../Core/db.php';

/**
 * Clase para gestionar control de versiones de configuraciones
 */
class ConfigurationVersionControl
{
    private mysqli $conn;
    private string $configPath;
    private string $gitRepoPath;
    private array $trackedFiles;
    
    public function __construct(?mysqli $connection = null)
    {
        global $conn;
        $this->conn = $connection ?? $conn ?? DatabaseManager::getConnection();
        $this->configPath = dirname(__DIR__, 3) . '/storage/configurations/';
        $this->gitRepoPath = $this->configPath . '.git/';
        
        $this->trackedFiles = [
            'database' => $this->configPath . 'database.json',
            'system' => $this->configPath . 'system.json',
            'security' => $this->configPath . 'security.json',
            'email' => $this->configPath . 'email.json',
            'integrations' => $this->configPath . 'integrations.json',
            'backup' => $this->configPath . 'backup.json',
            'monitoring' => $this->configPath . 'monitoring.json'
        ];
        
        $this->initializeRepository();
    }

    /**
     * Inicializa el repositorio Git si no existe
     */
    private function initializeRepository(): void
    {
        if (!is_dir($this->configPath)) {
            mkdir($this->configPath, 0755, true);
        }

        if (!is_dir($this->gitRepoPath)) {
            $this->executeGitCommand('init');
            $this->createInitialCommit();
        }

        // Crear archivos de configuración iniciales si no existen
        $this->createInitialConfigurations();
    }

    /**
     * Crea configuraciones iniciales
     */
    private function createInitialConfigurations(): void
    {
        $initialConfigs = [
            'database' => [
                'host' => 'localhost',
                'port' => 3306,
                'name' => 'iso17025',
                'charset' => 'utf8mb4',
                'backup_enabled' => true,
                'version' => '1.0.0'
            ],
            'system' => [
                'name' => 'Sistema ISO 17025',
                'version' => '1.0.0',
                'environment' => 'production',
                'debug_mode' => false,
                'maintenance_mode' => false,
                'max_file_size' => '10MB',
                'session_timeout' => 3600
            ],
            'security' => [
                'password_policy' => [
                    'min_length' => 8,
                    'require_uppercase' => true,
                    'require_lowercase' => true,
                    'require_numbers' => true,
                    'require_symbols' => true
                ],
                'session_security' => [
                    'secure_cookies' => true,
                    'httponly_cookies' => true,
                    'regenerate_id' => true
                ],
                'failed_login_attempts' => 5,
                'lockout_duration' => 900
            ],
            'email' => [
                'service' => 'smtp',
                'host' => 'localhost',
                'port' => 587,
                'encryption' => 'tls',
                'auth_required' => true,
                'from_address' => 'noreply@sistema.com',
                'from_name' => 'Sistema ISO 17025'
            ],
            'integrations' => [
                'jira' => [
                    'enabled' => false,
                    'url' => '',
                    'project_key' => 'INCIDENT'
                ],
                'trello' => [
                    'enabled' => false,
                    'board_id' => ''
                ],
                'asana' => [
                    'enabled' => false,
                    'project_id' => ''
                ]
            ],
            'backup' => [
                'enabled' => true,
                'schedule' => [
                    'daily' => '0 2 * * *',
                    'weekly' => '0 1 * * 0',
                    'monthly' => '0 0 1 * *'
                ],
                'retention' => [
                    'daily' => 7,
                    'weekly' => 4,
                    'monthly' => 12
                ],
                'compression' => true,
                'encryption' => false
            ],
            'monitoring' => [
                'alerts_enabled' => true,
                'email_notifications' => true,
                'real_time_monitoring' => true,
                'thresholds' => [
                    'database_response_time' => 3000,
                    'disk_usage' => 90,
                    'memory_usage' => 90,
                    'cpu_usage' => 90
                ]
            ]
        ];

        foreach ($initialConfigs as $configName => $configData) {
            $filePath = $this->trackedFiles[$configName];
            
            if (!file_exists($filePath)) {
                file_put_contents($filePath, json_encode($configData, JSON_PRETTY_PRINT));
            }
        }
    }

    /**
     * Guarda configuración con control de versiones
     */
    public function saveConfiguration(string $configName, array $configData, string $changeDescription = ''): array
    {
        if (!isset($this->trackedFiles[$configName])) {
            throw new Exception("Configuración no válida: {$configName}");
        }

        $filePath = $this->trackedFiles[$configName];
        
        // Obtener configuración actual para comparación
        $currentConfig = $this->getCurrentConfiguration($configName);
        
        // Validar configuración antes de guardar
        $this->validateConfiguration($configName, $configData);
        
        // Crear backup de la configuración actual
        $backupPath = $this->createConfigurationBackup($configName);
        
        try {
            // Guardar nueva configuración
            file_put_contents($filePath, json_encode($configData, JSON_PRETTY_PRINT));
            
            // Crear commit en Git
            $commitMessage = $changeDescription ?: "Actualización de configuración: {$configName}";
            $commitHash = $this->commitChanges($configName, $commitMessage);
            
            // Registrar cambio en la base de datos
            $changeId = $this->recordConfigurationChange([
                'config_name' => $configName,
                'old_data' => $currentConfig,
                'new_data' => $configData,
                'change_description' => $changeDescription,
                'commit_hash' => $commitHash,
                'backup_path' => $backupPath
            ]);
            
            return [
                'success' => true,
                'change_id' => $changeId,
                'commit_hash' => $commitHash,
                'backup_path' => $backupPath
            ];
            
        } catch (Exception $e) {
            // Revertir cambios en caso de error
            if (file_exists($backupPath)) {
                copy($backupPath, $filePath);
            }
            throw $e;
        }
    }

    /**
     * Obtiene configuración actual
     */
    public function getCurrentConfiguration(string $configName): array
    {
        if (!isset($this->trackedFiles[$configName])) {
            throw new Exception("Configuración no válida: {$configName}");
        }

        $filePath = $this->trackedFiles[$configName];
        
        if (!file_exists($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath);
        return json_decode($content, true) ?? [];
    }

    /**
     * Obtiene historial de cambios de configuración
     */
    public function getConfigurationHistory(string $configName, int $limit = 50): array
    {
        $stmt = $this->conn->prepare("
            SELECT 
                cc.*, 
                u.nombre as changed_by_name,
                u.usuario as changed_by_username
            FROM configuration_changes cc
            LEFT JOIN usuarios u ON cc.changed_by = u.id
            WHERE cc.config_name = ?
            ORDER BY cc.created_at DESC
            LIMIT ?
        ");

        $stmt->bind_param("si", $configName, $limit);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Revierte configuración a una versión anterior
     */
    public function revertConfiguration(string $configName, string $commitHash, string $reason = ''): array
    {
        if (!isset($this->trackedFiles[$configName])) {
            throw new Exception("Configuración no válida: {$configName}");
        }

        $filePath = $this->trackedFiles[$configName];
        
        // Verificar que el commit existe
        if (!$this->commitExists($commitHash)) {
            throw new Exception("Commit no encontrado: {$commitHash}");
        }

        // Obtener configuración actual para backup
        $currentConfig = $this->getCurrentConfiguration($configName);
        
        try {
            // Revertir archivo usando Git
            $this->executeGitCommand("checkout {$commitHash} -- " . basename($filePath));
            
            // Crear nuevo commit para la reversión
            $revertMessage = "Reversión de {$configName} a commit {$commitHash}: {$reason}";
            $newCommitHash = $this->commitChanges($configName, $revertMessage);
            
            // Obtener configuración revertida
            $revertedConfig = $this->getCurrentConfiguration($configName);
            
            // Registrar la reversión
            $changeId = $this->recordConfigurationChange([
                'config_name' => $configName,
                'old_data' => $currentConfig,
                'new_data' => $revertedConfig,
                'change_description' => $revertMessage,
                'commit_hash' => $newCommitHash,
                'is_revert' => true,
                'reverted_from' => $commitHash
            ]);
            
            return [
                'success' => true,
                'change_id' => $changeId,
                'commit_hash' => $newCommitHash,
                'reverted_from' => $commitHash
            ];
            
        } catch (Exception $e) {
            throw new Exception("Error revirtiendo configuración: " . $e->getMessage());
        }
    }

    /**
     * Compara dos versiones de configuración
     */
    public function compareConfigurations(string $configName, string $commitHash1, string $commitHash2): array
    {
        $config1 = $this->getConfigurationAtCommit($configName, $commitHash1);
        $config2 = $this->getConfigurationAtCommit($configName, $commitHash2);
        
        return [
            'added' => array_diff_assoc($config2, $config1),
            'removed' => array_diff_assoc($config1, $config2),
            'modified' => $this->getModifiedKeys($config1, $config2)
        ];
    }

    /**
     * Crea branch para cambios experimentales
     */
    public function createExperimentalBranch(string $branchName, string $description = ''): bool
    {
        try {
            $this->executeGitCommand("checkout -b {$branchName}");
            
            // Registrar branch experimental
            $stmt = $this->conn->prepare("
                INSERT INTO experimental_branches (branch_name, description, created_at) 
                VALUES (?, ?, NOW())
            ");
            
            $stmt->bind_param("ss", $branchName, $description);
            $stmt->execute();
            
            return true;
            
        } catch (Exception $e) {
            error_log("Error creando branch experimental: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fusiona branch experimental con main
     */
    public function mergeExperimentalBranch(string $branchName, string $mergeMessage = ''): array
    {
        try {
            // Cambiar a branch main
            $this->executeGitCommand("checkout main");
            
            // Fusionar branch experimental
            $mergeMessage = $mergeMessage ?: "Fusión de branch experimental: {$branchName}";
            $this->executeGitCommand("merge {$branchName} -m \"{$mergeMessage}\"");
            
            // Obtener hash del commit de fusión
            $commitHash = trim($this->executeGitCommand("rev-parse HEAD"));
            
            // Actualizar estado del branch
            $stmt = $this->conn->prepare("
                UPDATE experimental_branches 
                SET status = 'merged', merged_at = NOW(), merge_commit = ? 
                WHERE branch_name = ?
            ");
            
            $stmt->bind_param("ss", $commitHash, $branchName);
            $stmt->execute();
            
            return [
                'success' => true,
                'commit_hash' => $commitHash,
                'merged_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            throw new Exception("Error fusionando branch: " . $e->getMessage());
        }
    }

    /**
     * Exporta configuraciones actuales
     */
    public function exportConfigurations(?array $configNames = null): string
    {
        $configNames = $configNames ?? array_keys($this->trackedFiles);
        $exportData = [];
        
        foreach ($configNames as $configName) {
            $exportData[$configName] = $this->getCurrentConfiguration($configName);
        }
        
        $exportFileName = 'config_export_' . date('Y-m-d_H-i-s') . '.json';
        $exportPath = $this->configPath . 'exports/' . $exportFileName;
        
        if (!is_dir(dirname($exportPath))) {
            mkdir(dirname($exportPath), 0755, true);
        }
        
        file_put_contents($exportPath, json_encode($exportData, JSON_PRETTY_PRINT));
        
        return $exportPath;
    }

    /**
     * Importa configuraciones desde archivo
     */
    public function importConfigurations(string $importFile, bool $validateOnly = false): array
    {
        if (!file_exists($importFile)) {
            throw new Exception("Archivo de importación no encontrado: {$importFile}");
        }

        $importData = json_decode(file_get_contents($importFile), true);
        if (!$importData) {
            throw new Exception("Archivo de importación no válido");
        }

        $results = [];
        
        foreach ($importData as $configName => $configData) {
            if (!isset($this->trackedFiles[$configName])) {
                $results[$configName] = ['error' => 'Configuración no válida'];
                continue;
            }

            try {
                // Validar configuración
                $this->validateConfiguration($configName, $configData);
                
                if (!$validateOnly) {
                    $result = $this->saveConfiguration(
                        $configName, 
                        $configData, 
                        "Importación desde {$importFile}"
                    );
                    $results[$configName] = $result;
                } else {
                    $results[$configName] = ['valid' => true];
                }
                
            } catch (Exception $e) {
                $results[$configName] = ['error' => $e->getMessage()];
            }
        }
        
        return $results;
    }

    // Métodos auxiliares privados

    private function executeGitCommand(string $command): string
    {
        $fullCommand = "cd {$this->configPath} && git {$command} 2>&1";
        $output = shell_exec($fullCommand);
        
        if ($output === null) {
            throw new Exception("Error ejecutando comando Git: {$command}");
        }
        
        return $output;
    }

    private function createInitialCommit(): void
    {
        $this->executeGitCommand("add .");
        $this->executeGitCommand("commit -m \"Configuración inicial del sistema\"");
    }

    private function commitChanges(string $configName, string $message): string
    {
        $fileName = basename($this->trackedFiles[$configName]);
        
        $this->executeGitCommand("add {$fileName}");
        $this->executeGitCommand("commit -m \"{$message}\"");
        
        return trim($this->executeGitCommand("rev-parse HEAD"));
    }

    private function createConfigurationBackup(string $configName): string
    {
        $filePath = $this->trackedFiles[$configName];
        $backupDir = $this->configPath . 'backups/';
        
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $backupFileName = $configName . '_backup_' . date('Y-m-d_H-i-s') . '.json';
        $backupPath = $backupDir . $backupFileName;
        
        if (file_exists($filePath)) {
            copy($filePath, $backupPath);
        }
        
        return $backupPath;
    }

    private function recordConfigurationChange(array $changeData): int
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $userId = $_SESSION['usuario_id'] ?? null;
        
        $stmt = $this->conn->prepare("
            INSERT INTO configuration_changes (
                config_name, changed_by, old_data, new_data, 
                change_description, commit_hash, backup_path, 
                is_revert, reverted_from, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $oldDataJson = json_encode($changeData['old_data'] ?? []);
        $newDataJson = json_encode($changeData['new_data'] ?? []);
        $isRevert = $changeData['is_revert'] ?? false;
        $revertedFrom = $changeData['reverted_from'] ?? null;
        $backupPath = $changeData['backup_path'] ?? null;

        $stmt->bind_param(
            "sissssiss",
            $changeData['config_name'],
            $userId,
            $oldDataJson,
            $newDataJson,
            $changeData['change_description'],
            $changeData['commit_hash'],
            $backupPath,
            $isRevert,
            $revertedFrom
        );

        $stmt->execute();
        return $this->conn->insert_id;
    }

    private function validateConfiguration(string $configName, array $configData): void
    {
        // Validaciones específicas por tipo de configuración
        switch ($configName) {
            case 'database':
                $this->validateDatabaseConfig($configData);
                break;
            case 'security':
                $this->validateSecurityConfig($configData);
                break;
            case 'email':
                $this->validateEmailConfig($configData);
                break;
            // Agregar más validaciones según sea necesario
        }
    }

    private function validateDatabaseConfig(array $config): void
    {
        $required = ['host', 'port', 'name'];
        
        foreach ($required as $field) {
            if (!isset($config[$field]) || empty($config[$field])) {
                throw new Exception("Campo requerido faltante en configuración de base de datos: {$field}");
            }
        }
        
        if (!is_numeric($config['port']) || $config['port'] < 1 || $config['port'] > 65535) {
            throw new Exception("Puerto de base de datos no válido");
        }
    }

    private function validateSecurityConfig(array $config): void
    {
        if (isset($config['password_policy']['min_length'])) {
            if ($config['password_policy']['min_length'] < 8) {
                throw new Exception("La longitud mínima de contraseña debe ser al menos 8 caracteres");
            }
        }
        
        if (isset($config['failed_login_attempts'])) {
            if ($config['failed_login_attempts'] < 3 || $config['failed_login_attempts'] > 10) {
                throw new Exception("Los intentos de login fallidos deben estar entre 3 y 10");
            }
        }
    }

    private function validateEmailConfig(array $config): void
    {
        if (isset($config['port'])) {
            if (!in_array($config['port'], [25, 465, 587, 2525])) {
                throw new Exception("Puerto de email no estándar: {$config['port']}");
            }
        }
        
        if (isset($config['from_address'])) {
            if (!filter_var($config['from_address'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Dirección de email no válida: {$config['from_address']}");
            }
        }
    }

    private function commitExists(string $commitHash): bool
    {
        try {
            $this->executeGitCommand("cat-file -e {$commitHash}");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    private function getConfigurationAtCommit(string $configName, string $commitHash): array
    {
        $fileName = basename($this->trackedFiles[$configName]);
        
        try {
            $content = $this->executeGitCommand("show {$commitHash}:{$fileName}");
            return json_decode($content, true) ?? [];
        } catch (Exception $e) {
            return [];
        }
    }

    private function getModifiedKeys(array $config1, array $config2): array
    {
        $modified = [];
        
        foreach ($config1 as $key => $value) {
            if (isset($config2[$key]) && $config2[$key] !== $value) {
                $modified[$key] = [
                    'old' => $value,
                    'new' => $config2[$key]
                ];
            }
        }
        
        return $modified;
    }
}