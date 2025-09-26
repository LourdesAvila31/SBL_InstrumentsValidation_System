#!/usr/bin/env php
<?php
/**
 * Programador de Backups Automáticos
 * 
 * Script para ejecutar backups programados desde cron jobs
 */

require_once __DIR__ . '/BackupManager.php';

// Obtener argumentos de línea de comandos
$options = getopt("", ["job:"]);
$jobType = $options['job'] ?? 'daily';

try {
    $backupManager = new BackupManager();
    
    switch ($jobType) {
        case 'daily_backup':
            echo "Iniciando backup diario...\n";
            $result = $backupManager->performFullBackup('hot');
            break;
            
        case 'weekly_backup':
            echo "Iniciando backup semanal...\n";
            $result = $backupManager->performFullBackup('cold');
            break;
            
        case 'monthly_backup':
            echo "Iniciando backup mensual...\n";
            $result = $backupManager->performFullBackup('cold');
            // Limpiar backups antiguos
            $cleaned = $backupManager->cleanOldBackups(90);
            echo "Backups antiguos eliminados: {$cleaned}\n";
            break;
            
        default:
            echo "Tipo de trabajo no reconocido: {$jobType}\n";
            exit(1);
    }
    
    if (!empty($result['errors'])) {
        echo "Errores durante el backup:\n";
        foreach ($result['errors'] as $error) {
            echo "- {$error}\n";
        }
        exit(1);
    }
    
    echo "Backup completado exitosamente. ID: {$result['backup_id']}\n";
    echo "Base de datos: " . ($result['database'] ? 'OK' : 'FALLÓ') . "\n";
    echo "Archivos: " . ($result['files'] ? 'OK' : 'FALLÓ') . "\n";
    
} catch (Exception $e) {
    echo "Error fatal durante el backup: " . $e->getMessage() . "\n";
    exit(1);
}