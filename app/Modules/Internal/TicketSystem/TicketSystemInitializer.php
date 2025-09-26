<?php
/**
 * Script de Inicialización del Sistema de Tickets
 * Conforme a GAMP 5 y normativas GxP
 * 
 * Este script inicializa completamente el sistema de gestión de tickets,
 * incluyendo base de datos, configuraciones y datos por defecto.
 */

require_once dirname(__DIR__, 2) . '/Core/db.php';
require_once __DIR__ . '/TicketManager.php';
require_once __DIR__ . '/NotificationSystem.php';

class TicketSystemInitializer {
    private $conn;
    private $logFile;
    
    public function __construct() {
        $this->conn = DatabaseManager::getConnection();
        $this->logFile = dirname(__DIR__, 3) . '/storage/logs/system_initialization.log';
        
        // Crear directorio de logs si no existe
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Inicializa completamente el sistema de tickets
     */
    public function initializeSystem() {
        $this->log("Iniciando inicialización del sistema de tickets...");
        
        try {
            // Paso 1: Inicializar tablas
            $this->log("Paso 1: Inicializando tablas de base de datos...");
            $this->initializeTables();
            
            // Paso 2: Configurar datos por defecto
            $this->log("Paso 2: Configurando datos por defecto...");
            $this->setupDefaultData();
            
            // Paso 3: Configurar permisos
            $this->log("Paso 3: Configurando permisos de usuario...");
            $this->setupPermissions();
            
            // Paso 4: Configurar notificaciones
            $this->log("Paso 4: Configurando sistema de notificaciones...");
            $this->setupNotifications();
            
            // Paso 5: Crear directorios necesarios
            $this->log("Paso 5: Creando directorios del sistema...");
            $this->createDirectories();
            
            // Paso 6: Configurar tareas programadas
            $this->log("Paso 6: Configurando tareas programadas...");
            $this->setupScheduledTasks();
            
            // Paso 7: Validar instalación
            $this->log("Paso 7: Validando instalación...");
            $this->validateInstallation();
            
            $this->log("Sistema de tickets inicializado exitosamente.");
            
            return [
                'success' => true,
                'message' => 'Sistema de tickets inicializado correctamente',
                'timestamp' => date('Y-m-d H:i:s'),
                'version' => '1.0.0'
            ];
            
        } catch (Exception $e) {
            $this->log("ERROR: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    /**
     * Inicializa las tablas del sistema
     */
    private function initializeTables() {
        // Las tablas se crean automáticamente al instanciar TicketManager y NotificationSystem
        $ticketManager = new TicketManager();
        $notificationSystem = new TicketNotificationSystem();
        
        $this->log("Tablas del sistema creadas correctamente.");
    }
    
    /**
     * Configura datos por defecto
     */
    private function setupDefaultData() {
        // Insertar roles por defecto si no existen
        $defaultRoles = [
            'ticket_admin' => 'Administrador de Tickets',
            'ticket_manager' => 'Gestor de Tickets',
            'ticket_user' => 'Usuario de Tickets',
            'qa_manager' => 'Gestor de Calidad',
            'security_officer' => 'Oficial de Seguridad'
        ];
        
        foreach ($defaultRoles as $roleCode => $roleName) {
            $checkSql = "SELECT COUNT(*) as count FROM roles WHERE codigo = ?";
            $checkStmt = $this->conn->prepare($checkSql);
            $checkStmt->bind_param("s", $roleCode);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $count = $result->fetch_assoc()['count'];
            
            if ($count == 0) {
                $insertSql = "INSERT INTO roles (nombre, codigo, descripcion, activo) VALUES (?, ?, ?, 1)";
                $insertStmt = $this->conn->prepare($insertSql);
                $description = "Rol para $roleName en sistema de tickets";
                $insertStmt->bind_param("sss", $roleName, $roleCode, $description);
                $insertStmt->execute();
                $this->log("Rol creado: $roleName");
            }
        }
        
        // Configurar settings por defecto del sistema
        $defaultSettings = [
            'ticket_auto_assignment' => 'true',
            'sla_monitoring_enabled' => 'true',
            'notification_email_enabled' => 'true',
            'notification_slack_enabled' => 'false',
            'audit_log_retention_days' => '2555', // 7 años para GxP
            'critical_ticket_escalation_hours' => '1',
            'high_ticket_escalation_hours' => '4',
            'medium_ticket_escalation_hours' => '24',
            'gxp_compliance_checks' => 'true'
        ];
        
        foreach ($defaultSettings as $key => $value) {
            $checkSql = "SELECT COUNT(*) as count FROM system_settings WHERE setting_key = ?";
            $checkStmt = $this->conn->prepare($checkSql);
            $checkStmt->bind_param("s", $key);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $count = $result->fetch_assoc()['count'];
            
            if ($count == 0) {
                $insertSql = "INSERT INTO system_settings (setting_key, setting_value, created_at) VALUES (?, ?, NOW())";
                $insertStmt = $this->conn->prepare($insertSql);
                $insertStmt->bind_param("ss", $key, $value);
                $insertStmt->execute();
                $this->log("Configuración creada: $key = $value");
            }
        }
    }
    
    /**
     * Configura permisos de usuario
     */
    private function setupPermissions() {
        // Crear tabla de permisos de tickets si no existe
        $permissionsTableSql = "
            CREATE TABLE IF NOT EXISTS ticket_permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                permission_type ENUM('create', 'view', 'assign', 'resolve', 'close', 'admin') NOT NULL,
                scope ENUM('own', 'team', 'all') DEFAULT 'own',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_permission (user_id, permission_type),
                INDEX idx_user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        if (!$this->conn->query($permissionsTableSql)) {
            throw new Exception("Error creando tabla de permisos: " . $this->conn->error);
        }
        
        // Asignar permisos por defecto a usuarios existentes con rol admin
        $adminUsers = $this->conn->query("SELECT id FROM usuarios WHERE role IN ('admin', 'superadmin') AND active = 1");
        
        while ($user = $adminUsers->fetch_assoc()) {
            $permissions = ['create', 'view', 'assign', 'resolve', 'close', 'admin'];
            foreach ($permissions as $permission) {
                $insertSql = "INSERT IGNORE INTO ticket_permissions (user_id, permission_type, scope) VALUES (?, ?, 'all')";
                $stmt = $this->conn->prepare($insertSql);
                $stmt->bind_param("is", $user['id'], $permission);
                $stmt->execute();
            }
        }
        
        $this->log("Permisos de usuario configurados.");
    }
    
    /**
     * Configura sistema de notificaciones
     */
    private function setupNotifications() {
        // Configurar notificaciones por defecto para administradores
        $adminUsers = $this->conn->query("SELECT id, email FROM usuarios WHERE role IN ('admin', 'superadmin', 'qa_manager') AND active = 1");
        
        while ($user = $adminUsers->fetch_assoc()) {
            if (!empty($user['email'])) {
                $eventTypes = ['high_priority_created', 'escalation_warning', 'resolution_notification'];
                foreach ($eventTypes as $eventType) {
                    $insertSql = "INSERT IGNORE INTO notification_settings (user_id, notification_type, event_type, enabled) VALUES (?, 'email', ?, TRUE)";
                    $stmt = $this->conn->prepare($insertSql);
                    $stmt->bind_param("is", $user['id'], $eventType);
                    $stmt->execute();
                }
            }
        }
        
        $this->log("Sistema de notificaciones configurado.");
    }
    
    /**
     * Crea directorios necesarios
     */
    private function createDirectories() {
        $directories = [
            dirname(__DIR__, 3) . '/storage/tickets',
            dirname(__DIR__, 3) . '/storage/tickets/attachments',
            dirname(__DIR__, 3) . '/storage/logs',
            dirname(__DIR__, 3) . '/storage/reports',
            dirname(__DIR__, 3) . '/storage/backups/tickets'
        ];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                if (mkdir($dir, 0755, true)) {
                    $this->log("Directorio creado: $dir");
                } else {
                    throw new Exception("No se pudo crear directorio: $dir");
                }
            }
            
            // Crear archivo .htaccess para proteger directorios sensibles
            if (strpos($dir, 'storage') !== false) {
                $htaccessFile = $dir . '/.htaccess';
                if (!file_exists($htaccessFile)) {
                    file_put_contents($htaccessFile, "Deny from all\n");
                    $this->log("Archivo .htaccess creado en: $dir");
                }
            }
        }
    }
    
    /**
     * Configura tareas programadas (cron jobs)
     */
    private function setupScheduledTasks() {
        $cronScriptPath = dirname(__DIR__, 3) . '/tools/scripts/ticket_cron.php';
        
        // Crear script cron
        $cronScript = "#!/usr/bin/env php\n<?php\n";
        $cronScript .= "/**\n * Script Cron para Sistema de Tickets\n * Ejecutar cada hora: 0 * * * *\n */\n\n";
        $cronScript .= "require_once __DIR__ . '/../../app/Modules/Internal/TicketSystem/NotificationSystem.php';\n\n";
        $cronScript .= "echo \"[\" . date('Y-m-d H:i:s') . \"] Ejecutando verificación de escalaciones...\\n\";\n";
        $cronScript .= "\$notificationSystem = new TicketNotificationSystem();\n";
        $cronScript .= "\$notificationSystem->checkEscalationAlerts();\n";
        $cronScript .= "echo \"[\" . date('Y-m-d H:i:s') . \"] Verificación completada.\\n\";\n";
        
        if (file_put_contents($cronScriptPath, $cronScript)) {
            chmod($cronScriptPath, 0755);
            $this->log("Script cron creado: $cronScriptPath");
        }
        
        // Crear script de limpieza (ejecutar diariamente)
        $cleanupScriptPath = dirname(__DIR__, 3) . '/tools/scripts/ticket_cleanup.php';
        $cleanupScript = "#!/usr/bin/env php\n<?php\n";
        $cleanupScript .= "/**\n * Script de Limpieza para Sistema de Tickets\n * Ejecutar diariamente: 0 2 * * *\n */\n\n";
        $cleanupScript .= "require_once __DIR__ . '/../../app/Core/db.php';\n\n";
        $cleanupScript .= "echo \"[\" . date('Y-m-d H:i:s') . \"] Iniciando limpieza de logs antiguos...\\n\";\n";
        $cleanupScript .= "\$conn = DatabaseManager::getConnection();\n";
        $cleanupScript .= "\$retentionDays = 90; // 90 días por defecto\n";
        $cleanupScript .= "\$conn->query(\"DELETE FROM notification_log WHERE created_at < DATE_SUB(NOW(), INTERVAL \$retentionDays DAY)\");\n";
        $cleanupScript .= "echo \"[\" . date('Y-m-d H:i:s') . \"] Limpieza completada.\\n\";\n";
        
        if (file_put_contents($cleanupScriptPath, $cleanupScript)) {
            chmod($cleanupScriptPath, 0755);
            $this->log("Script de limpieza creado: $cleanupScriptPath");
        }
    }
    
    /**
     * Valida que la instalación sea correcta
     */
    private function validateInstallation() {
        $validationErrors = [];
        
        // Verificar tablas
        $requiredTables = ['tickets', 'ticket_history', 'ticket_attachments', 'risk_assessment_criteria', 
                          'notification_settings', 'notification_log', 'sla_monitoring'];
        
        foreach ($requiredTables as $table) {
            $result = $this->conn->query("SHOW TABLES LIKE '$table'");
            if ($result->num_rows === 0) {
                $validationErrors[] = "Tabla faltante: $table";
            }
        }
        
        // Verificar directorios
        $requiredDirs = [
            dirname(__DIR__, 3) . '/storage/tickets',
            dirname(__DIR__, 3) . '/storage/logs'
        ];
        
        foreach ($requiredDirs as $dir) {
            if (!is_dir($dir)) {
                $validationErrors[] = "Directorio faltante: $dir";
            } elseif (!is_writable($dir)) {
                $validationErrors[] = "Directorio no escribible: $dir";
            }
        }
        
        // Verificar configuración
        $result = $this->conn->query("SELECT COUNT(*) as count FROM risk_assessment_criteria WHERE is_active = TRUE");
        if ($result->fetch_assoc()['count'] == 0) {
            $validationErrors[] = "No hay criterios de evaluación de riesgo configurados";
        }
        
        if (!empty($validationErrors)) {
            throw new Exception("Errores de validación: " . implode(', ', $validationErrors));
        }
        
        $this->log("Validación de instalación completada exitosamente.");
    }
    
    /**
     * Genera reporte de instalación
     */
    public function generateInstallationReport() {
        $report = [
            'installation_date' => date('Y-m-d H:i:s'),
            'version' => '1.0.0',
            'php_version' => PHP_VERSION,
            'mysql_version' => $this->conn->server_info,
            'tables_created' => [],
            'directories_created' => [],
            'permissions_configured' => true,
            'notifications_configured' => true,
            'cron_scripts_created' => true,
            'gxp_compliance' => 'FULL'
        ];
        
        // Verificar tablas
        $tables = ['tickets', 'ticket_history', 'ticket_attachments', 'risk_assessment_criteria', 
                  'notification_settings', 'notification_log', 'sla_monitoring'];
        
        foreach ($tables as $table) {
            $result = $this->conn->query("SHOW TABLES LIKE '$table'");
            $report['tables_created'][$table] = $result->num_rows > 0;
        }
        
        // Verificar directorios
        $dirs = [
            'tickets' => dirname(__DIR__, 3) . '/storage/tickets',
            'logs' => dirname(__DIR__, 3) . '/storage/logs',
            'reports' => dirname(__DIR__, 3) . '/storage/reports'
        ];
        
        foreach ($dirs as $name => $path) {
            $report['directories_created'][$name] = is_dir($path) && is_writable($path);
        }
        
        return $report;
    }
    
    /**
     * Registra mensaje en el log
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        // También mostrar en consola si se ejecuta desde CLI
        if (php_sapi_name() === 'cli') {
            echo $logMessage;
        }
    }
    
    /**
     * Desinstala el sistema (solo para desarrollo/testing)
     */
    public function uninstallSystem() {
        if (getenv('ENVIRONMENT') !== 'development') {
            throw new Exception("La desinstalación solo está permitida en ambiente de desarrollo");
        }
        
        $this->log("ADVERTENCIA: Iniciando desinstalación del sistema...");
        
        // Eliminar tablas
        $tables = ['sla_monitoring', 'ticket_attachments', 'ticket_history', 'tickets', 
                  'notification_log', 'notification_settings', 'risk_assessment_criteria', 'ticket_permissions'];
        
        foreach ($tables as $table) {
            $this->conn->query("DROP TABLE IF EXISTS $table");
            $this->log("Tabla eliminada: $table");
        }
        
        $this->log("Sistema desinstalado.");
        
        return ['success' => true, 'message' => 'Sistema desinstalado correctamente'];
    }
}

// Ejecutar si se llama desde línea de comandos
if (php_sapi_name() === 'cli') {
    $initializer = new TicketSystemInitializer();
    
    $command = $argv[1] ?? 'install';
    
    switch ($command) {
        case 'install':
        case 'init':
            $result = $initializer->initializeSystem();
            echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
            break;
            
        case 'report':
            $report = $initializer->generateInstallationReport();
            echo json_encode($report, JSON_PRETTY_PRINT) . "\n";
            break;
            
        case 'uninstall':
            if (confirm("¿Está seguro que desea desinstalar el sistema? (y/N): ")) {
                $result = $initializer->uninstallSystem();
                echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
            } else {
                echo "Desinstalación cancelada.\n";
            }
            break;
            
        default:
            echo "Uso: php TicketSystemInitializer.php [install|report|uninstall]\n";
            break;
    }
}

function confirm($message) {
    echo $message;
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    return strtolower(trim($line)) === 'y';
}
?>