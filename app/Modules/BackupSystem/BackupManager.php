<?php
/**
 * Sistema de Backup Automático (Caliente y Frío)
 * 
 * Este módulo implementa un sistema completo de backups automáticos
 * tanto en caliente como en frío para bases de datos y archivos del sistema.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../Core/db.php';

/**
 * Clase para gestionar backups automáticos del sistema
 */
class BackupManager
{
    private mysqli $conn;
    private string $backupPath;
    private array $criticalPaths;
    
    public function __construct(?mysqli $connection = null)
    {
        global $conn;
        $this->conn = $connection ?? $conn ?? DatabaseManager::getConnection();
        $this->backupPath = dirname(__DIR__, 3) . '/storage/backups/';
        $this->criticalPaths = [
            'app' => dirname(__DIR__, 2),
            'public' => dirname(__DIR__, 3) . '/public',
            'storage' => dirname(__DIR__, 3) . '/storage',
            'docs' => dirname(__DIR__, 3) . '/docs'
        ];
        
        // Crear directorio de backups si no existe
        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }
    }

    /**
     * Realiza backup completo (base de datos + archivos)
     */
    public function performFullBackup(string $type = 'hot'): array
    {
        $timestamp = date('Y-m-d_H-i-s');
        $backupId = $this->createBackupRecord($type, 'full');
        
        $results = [
            'backup_id' => $backupId,
            'type' => $type,
            'timestamp' => $timestamp,
            'database' => false,
            'files' => false,
            'errors' => []
        ];

        try {
            // Backup de base de datos
            $dbBackupFile = $this->backupPath . "db_backup_{$timestamp}.sql";
            $results['database'] = $this->backupDatabase($dbBackupFile);
            
            // Backup de archivos críticos
            $filesBackupDir = $this->backupPath . "files_backup_{$timestamp}/";
            $results['files'] = $this->backupFiles($filesBackupDir);
            
            // Crear archivo comprimido
            $zipFile = $this->backupPath . "full_backup_{$timestamp}.zip";
            $this->createCompressedBackup($zipFile, [$dbBackupFile, $filesBackupDir]);
            
            $this->updateBackupRecord($backupId, 'completed', $zipFile);
            
        } catch (Exception $e) {
            $results['errors'][] = $e->getMessage();
            $this->updateBackupRecord($backupId, 'failed', null, $e->getMessage());
        }

        return $results;
    }

    /**
     * Backup en caliente de la base de datos
     */
    public function backupDatabase(string $outputFile): bool
    {
        try {
            $host = $this->getDatabaseConfig('host');
            $user = $this->getDatabaseConfig('user');
            $password = $this->getDatabaseConfig('password');
            $database = $this->getDatabaseConfig('database');

            $command = "mysqldump --host={$host} --user={$user} --password={$password} --single-transaction --routines --triggers {$database} > {$outputFile}";
            
            $output = [];
            $returnVar = 0;
            exec($command, $output, $returnVar);
            
            return $returnVar === 0 && file_exists($outputFile);
            
        } catch (Exception $e) {
            error_log("Database backup error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Backup de archivos críticos del sistema
     */
    public function backupFiles(string $outputDir): bool
    {
        try {
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            foreach ($this->criticalPaths as $name => $path) {
                if (is_dir($path)) {
                    $targetPath = $outputDir . $name . '/';
                    $this->copyDirectory($path, $targetPath);
                }
            }

            return true;
            
        } catch (Exception $e) {
            error_log("Files backup error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Copia recursiva de directorios
     */
    private function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $subPath = substr($item->getPathname(), strlen($source) + 1);
            $targetPath = $destination . DIRECTORY_SEPARATOR . $subPath;
            
            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                $targetDir = dirname($targetPath);
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                copy($item->getPathname(), $targetPath);
            }
        }
    }

    /**
     * Crea archivo comprimido del backup
     */
    private function createCompressedBackup(string $zipFile, array $sources): bool
    {
        $zip = new ZipArchive();
        
        if ($zip->open($zipFile, ZipArchive::CREATE) !== TRUE) {
            throw new Exception("Cannot create zip file: {$zipFile}");
        }

        foreach ($sources as $source) {
            if (is_file($source)) {
                $zip->addFile($source, basename($source));
            } elseif (is_dir($source)) {
                $this->addDirectoryToZip($zip, $source, basename($source));
            }
        }

        $zip->close();
        return file_exists($zipFile);
    }

    /**
     * Añade directorio completo al ZIP
     */
    private function addDirectoryToZip(ZipArchive $zip, string $directory, string $localName): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $filePath = $file->getRealPath();
            $relativePath = $localName . DIRECTORY_SEPARATOR . substr($filePath, strlen($directory) + 1);

            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($filePath, $relativePath);
            }
        }
    }

    /**
     * Programa backups automáticos
     */
    public function scheduleAutomaticBackups(): void
    {
        // Backup diario a las 2:00 AM
        $this->createCronJob('0 2 * * *', 'daily_backup');
        
        // Backup semanal los domingos a las 1:00 AM
        $this->createCronJob('0 1 * * 0', 'weekly_backup');
        
        // Backup mensual el primer día del mes a las 0:00
        $this->createCronJob('0 0 1 * *', 'monthly_backup');
    }

    /**
     * Crea un registro de backup en la base de datos
     */
    private function createBackupRecord(string $type, string $scope): int
    {
        $stmt = $this->conn->prepare("
            INSERT INTO backup_logs (type, scope, status, started_at) 
            VALUES (?, ?, 'running', NOW())
        ");
        
        $stmt->bind_param("ss", $type, $scope);
        $stmt->execute();
        
        return $this->conn->insert_id;
    }

    /**
     * Actualiza el registro de backup
     */
    private function updateBackupRecord(int $backupId, string $status, ?string $filePath = null, ?string $errorMessage = null): void
    {
        $stmt = $this->conn->prepare("
            UPDATE backup_logs 
            SET status = ?, file_path = ?, error_message = ?, completed_at = NOW() 
            WHERE id = ?
        ");
        
        $stmt->bind_param("sssi", $status, $filePath, $errorMessage, $backupId);
        $stmt->execute();
    }

    /**
     * Obtiene configuración de la base de datos
     */
    private function getDatabaseConfig(string $key): string
    {
        $config = [
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'user' => $_ENV['DB_USER'] ?? 'root',
            'password' => $_ENV['DB_PASS'] ?? '',
            'database' => $_ENV['DB_NAME'] ?? 'iso17025'
        ];

        return $config[$key] ?? '';
    }

    /**
     * Crea trabajo cron para backups automáticos
     */
    private function createCronJob(string $schedule, string $jobName): void
    {
        $scriptPath = __DIR__ . "/backup_scheduler.php";
        $cronCommand = "{$schedule} php {$scriptPath} --job={$jobName}";
        
        // Registrar en el sistema de cron del servidor
        // Nota: Esto requiere permisos adecuados en el servidor
        $output = shell_exec("(crontab -l 2>/dev/null; echo '{$cronCommand}') | crontab -");
    }

    /**
     * Restaura backup desde archivo
     */
    public function restoreBackup(string $backupFile): bool
    {
        try {
            if (!file_exists($backupFile)) {
                throw new Exception("Backup file not found: {$backupFile}");
            }

            // Extraer backup
            $extractPath = $this->backupPath . 'restore_' . time() . '/';
            $this->extractBackup($backupFile, $extractPath);

            // Restaurar base de datos
            $sqlFile = $extractPath . 'db_backup_*.sql';
            $sqlFiles = glob($sqlFile);
            
            if (!empty($sqlFiles)) {
                $this->restoreDatabase($sqlFiles[0]);
            }

            // Log de restauración
            $this->logRestoration($backupFile);

            return true;
            
        } catch (Exception $e) {
            error_log("Backup restoration error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Extrae archivo de backup
     */
    private function extractBackup(string $backupFile, string $extractPath): void
    {
        $zip = new ZipArchive();
        
        if ($zip->open($backupFile) === TRUE) {
            $zip->extractTo($extractPath);
            $zip->close();
        } else {
            throw new Exception("Cannot extract backup file: {$backupFile}");
        }
    }

    /**
     * Restaura base de datos desde archivo SQL
     */
    private function restoreDatabase(string $sqlFile): void
    {
        $host = $this->getDatabaseConfig('host');
        $user = $this->getDatabaseConfig('user');
        $password = $this->getDatabaseConfig('password');
        $database = $this->getDatabaseConfig('database');

        $command = "mysql --host={$host} --user={$user} --password={$password} {$database} < {$sqlFile}";
        
        $output = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new Exception("Database restoration failed");
        }
    }

    /**
     * Registra operación de restauración
     */
    private function logRestoration(string $backupFile): void
    {
        $stmt = $this->conn->prepare("
            INSERT INTO backup_logs (type, scope, status, file_path, started_at, completed_at) 
            VALUES ('restore', 'full', 'completed', ?, NOW(), NOW())
        ");
        
        $stmt->bind_param("s", $backupFile);
        $stmt->execute();
    }

    /**
     * Obtiene historial de backups
     */
    public function getBackupHistory(int $limit = 50): array
    {
        $stmt = $this->conn->prepare("
            SELECT * FROM backup_logs 
            ORDER BY started_at DESC 
            LIMIT ?
        ");
        
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Limpia backups antiguos
     */
    public function cleanOldBackups(int $daysToKeep = 30): int
    {
        $cutoffDate = date('Y-m-d', strtotime("-{$daysToKeep} days"));
        $pattern = $this->backupPath . "*backup*{$cutoffDate}*";
        
        $oldFiles = glob($pattern);
        $deletedCount = 0;

        foreach ($oldFiles as $file) {
            if (unlink($file)) {
                $deletedCount++;
            }
        }

        return $deletedCount;
    }
}