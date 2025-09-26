<?php
/**
 * Limpiador de Logs Antiguos
 * Mantiene el sistema optimizado eliminando logs antiguos según políticas configuradas
 */

class LogCleaner
{
    private array $config;
    private mysqli $conn;
    private string $basePath;

    public function __construct()
    {
        $this->basePath = dirname(__DIR__);
        $config = require $this->basePath . '/app/config.php';
        
        $this->conn = new mysqli(
            $config['database']['host'],
            $config['database']['user'],
            $config['database']['password'],
            $config['database']['name']
        );
        
        if ($this->conn->connect_error) {
            throw new Exception("Database connection failed: " . $this->conn->connect_error);
        }

        $this->config = [
            'retention_days' => [
                'audit_logs' => 365,        // 1 año
                'system_logs' => 90,        // 3 meses
                'backup_logs' => 180,       // 6 meses
                'incident_logs' => 730,     // 2 años (compliance)
                'alert_logs' => 30,         // 1 mes
                'session_logs' => 30        // 1 mes
            ],
            'max_file_size_mb' => 100,
            'compress_old_logs' => true
        ];
    }

    /**
     * Ejecuta la limpieza completa
     */
    public function cleanAll(): void
    {
        echo "🧹 Iniciando limpieza de logs antiguos...\n";
        
        $this->cleanDatabaseLogs();
        $this->cleanFileSystemLogs();
        $this->compressOldLogs();
        $this->optimizeDatabase();
        
        echo "✅ Limpieza completada exitosamente\n";
    }

    /**
     * Limpia logs en base de datos
     */
    private function cleanDatabaseLogs(): void
    {
        echo "🗄️  Limpiando logs de base de datos...\n";

        foreach ($this->config['retention_days'] as $logType => $retentionDays) {
            $deleted = $this->cleanTableLogs($logType, $retentionDays);
            if ($deleted > 0) {
                echo "  ✓ {$logType}: {$deleted} registros eliminados\n";
            }
        }
    }

    /**
     * Limpia logs de una tabla específica
     */
    private function cleanTableLogs(string $logType, int $retentionDays): int
    {
        $tableMap = [
            'audit_logs' => 'audit_logs',
            'system_logs' => 'system_alerts',
            'backup_logs' => 'backup_history',
            'incident_logs' => 'incidents',
            'alert_logs' => 'system_alerts',
            'session_logs' => 'user_sessions'
        ];

        if (!isset($tableMap[$logType])) {
            return 0;
        }

        $table = $tableMap[$logType];
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

        // Para logs de auditoría, mantener siempre los críticos
        if ($logType === 'audit_logs') {
            $stmt = $this->conn->prepare("
                DELETE FROM {$table} 
                WHERE created_at < ? 
                AND severity NOT IN ('critical', 'security_violation')
                AND action NOT IN ('login', 'logout', 'password_change', 'permission_change')
            ");
        } else {
            $stmt = $this->conn->prepare("DELETE FROM {$table} WHERE created_at < ?");
        }

        $stmt->bind_param("s", $cutoffDate);
        $stmt->execute();
        
        return $stmt->affected_rows;
    }

    /**
     * Limpia archivos de log del sistema de archivos
     */
    private function cleanFileSystemLogs(): void
    {
        echo "📁 Limpiando archivos de log...\n";

        $logDirectories = [
            $this->basePath . '/storage/logs',
            $this->basePath . '/app/Modules/AlertSystem/logs'
        ];

        foreach ($logDirectories as $logDir) {
            if (is_dir($logDir)) {
                $this->cleanLogDirectory($logDir);
            }
        }
    }

    /**
     * Limpia directorio de logs
     */
    private function cleanLogDirectory(string $directory): void
    {
        $files = glob($directory . '/*.log');
        $totalCleaned = 0;
        $totalCompressed = 0;

        foreach ($files as $file) {
            $fileAge = (time() - filemtime($file)) / (24 * 3600); // días
            $fileSizeMB = filesize($file) / (1024 * 1024);

            // Eliminar archivos muy antiguos
            if ($fileAge > 90) {
                unlink($file);
                $totalCleaned++;
                continue;
            }

            // Comprimir archivos grandes o antiguos
            if (($fileSizeMB > $this->config['max_file_size_mb'] || $fileAge > 30) 
                && !str_ends_with($file, '.gz')) {
                
                if ($this->compressLogFile($file)) {
                    $totalCompressed++;
                }
            }
        }

        if ($totalCleaned > 0 || $totalCompressed > 0) {
            $dirName = basename($directory);
            echo "  ✓ {$dirName}: {$totalCleaned} eliminados, {$totalCompressed} comprimidos\n";
        }
    }

    /**
     * Comprime un archivo de log
     */
    private function compressLogFile(string $file): bool
    {
        $compressedFile = $file . '.gz';
        
        if (file_exists($compressedFile)) {
            return false;
        }

        $data = file_get_contents($file);
        $compressed = gzencode($data, 9);
        
        if (file_put_contents($compressedFile, $compressed)) {
            unlink($file);
            return true;
        }
        
        return false;
    }

    /**
     * Comprime logs antiguos
     */
    private function compressOldLogs(): void
    {
        if (!$this->config['compress_old_logs']) {
            return;
        }

        echo "🗜️  Comprimiendo logs antiguos...\n";

        $backupDir = $this->basePath . '/storage/backups';
        if (is_dir($backupDir)) {
            $backupFiles = glob($backupDir . '/*.sql');
            $compressed = 0;

            foreach ($backupFiles as $file) {
                $fileAge = (time() - filemtime($file)) / (24 * 3600);
                
                if ($fileAge > 7 && !str_ends_with($file, '.gz')) {
                    if ($this->compressLogFile($file)) {
                        $compressed++;
                    }
                }
            }

            if ($compressed > 0) {
                echo "  ✓ Backups: {$compressed} archivos comprimidos\n";
            }
        }
    }

    /**
     * Optimiza tablas de la base de datos
     */
    private function optimizeDatabase(): void
    {
        echo "⚡ Optimizando base de datos...\n";

        $tables = [
            'audit_logs',
            'backup_history', 
            'incidents',
            'system_alerts',
            'user_sessions',
            'change_requests'
        ];

        foreach ($tables as $table) {
            $this->conn->query("OPTIMIZE TABLE {$table}");
        }

        // Actualizar estadísticas
        $this->conn->query("ANALYZE TABLE " . implode(', ', $tables));

        echo "  ✓ Tablas optimizadas y estadísticas actualizadas\n";
    }

    /**
     * Genera reporte de limpieza
     */
    public function generateCleanupReport(): array
    {
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'database_stats' => [],
            'filesystem_stats' => [],
            'space_saved' => 0
        ];

        // Estadísticas de tablas
        foreach (['audit_logs', 'backup_history', 'incidents', 'system_alerts'] as $table) {
            $result = $this->conn->query("SELECT COUNT(*) as count FROM {$table}");
            $count = $result->fetch_assoc()['count'];
            $report['database_stats'][$table] = $count;
        }

        // Estadísticas de archivos
        $logDirs = [
            'storage/logs' => $this->basePath . '/storage/logs',
            'alert_logs' => $this->basePath . '/app/Modules/AlertSystem/logs'
        ];

        foreach ($logDirs as $name => $path) {
            if (is_dir($path)) {
                $files = glob($path . '/*');
                $totalSize = 0;
                
                foreach ($files as $file) {
                    if (is_file($file)) {
                        $totalSize += filesize($file);
                    }
                }

                $report['filesystem_stats'][$name] = [
                    'files' => count($files),
                    'size_mb' => round($totalSize / (1024 * 1024), 2)
                ];
            }
        }

        return $report;
    }
}

// Script principal
if (php_sapi_name() === 'cli') {
    try {
        $cleaner = new LogCleaner();
        
        // Generar reporte antes de la limpieza
        $reportBefore = $cleaner->generateCleanupReport();
        
        // Ejecutar limpieza
        $cleaner->cleanAll();
        
        // Generar reporte después de la limpieza
        $reportAfter = $cleaner->generateCleanupReport();
        
        // Mostrar estadísticas finales
        echo "\n📊 ESTADÍSTICAS DE LIMPIEZA:\n";
        echo "============================\n";
        
        foreach ($reportBefore['database_stats'] as $table => $beforeCount) {
            $afterCount = $reportAfter['database_stats'][$table];
            $deleted = $beforeCount - $afterCount;
            
            if ($deleted > 0) {
                echo "  {$table}: {$deleted} registros eliminados\n";
            }
        }
        
        echo "\n✨ Limpieza completada exitosamente\n";
        
    } catch (Exception $e) {
        echo "❌ Error durante la limpieza: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    echo "Este script debe ejecutarse desde línea de comandos.\n";
}