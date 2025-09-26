#!/usr/bin/env php
<?php
/**
 * Instalador Automático del Sistema de Gestión de Cambios
 * 
 * Este script instala y configura automáticamente todos los componentes
 * del sistema de gestión de cambios, auditoría, backup e incidentes.
 */

echo "🛡️  INSTALADOR DEL SISTEMA DE GESTIÓN DE CAMBIOS\n";
echo "================================================\n\n";

class ChangeManagementInstaller
{
    private array $config;
    private mysqli $conn;
    private string $basePath;
    
    public function __construct()
    {
        $this->basePath = dirname(__DIR__);
        $this->config = $this->loadConfiguration();
    }

    /**
     * Ejecuta la instalación completa
     */
    public function install(): void
    {
        echo "🚀 Iniciando instalación del sistema...\n\n";

        try {
            $this->checkRequirements();
            $this->setupDatabase();
            $this->createDirectories();
            $this->setupGitRepository();
            $this->installNodeJSDependencies();
            $this->createConfigurationFiles();
            $this->setupCronJobs();
            $this->initializeDefaultData();
            $this->testSystemComponents();
            
            echo "\n✅ ¡Instalación completada exitosamente!\n";
            $this->displayPostInstallationInstructions();
            
        } catch (Exception $e) {
            echo "\n❌ Error durante la instalación: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    /**
     * Verifica requisitos del sistema
     */
    private function checkRequirements(): void
    {
        echo "🔍 Verificando requisitos del sistema...\n";

        // Verificar PHP
        if (version_compare(PHP_VERSION, '8.0.0', '<')) {
            throw new Exception("Se requiere PHP 8.0 o superior. Versión actual: " . PHP_VERSION);
        }
        echo "  ✓ PHP " . PHP_VERSION . " OK\n";

        // Verificar extensiones PHP
        $requiredExtensions = ['mysqli', 'json', 'curl', 'zip'];
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                throw new Exception("Extensión PHP requerida no encontrada: {$ext}");
            }
            echo "  ✓ Extensión {$ext} OK\n";
        }

        // Verificar Node.js
        $nodeVersion = trim(shell_exec('node --version 2>/dev/null') ?: '');
        if (empty($nodeVersion)) {
            throw new Exception("Node.js no está instalado. Se requiere Node.js 16 o superior.");
        }
        echo "  ✓ Node.js {$nodeVersion} OK\n";

        // Verificar npm
        $npmVersion = trim(shell_exec('npm --version 2>/dev/null') ?: '');
        if (empty($npmVersion)) {
            throw new Exception("npm no está instalado.");
        }
        echo "  ✓ npm {$npmVersion} OK\n";

        // Verificar Git
        $gitVersion = trim(shell_exec('git --version 2>/dev/null') ?: '');
        if (empty($gitVersion)) {
            throw new Exception("Git no está instalado.");
        }
        echo "  ✓ {$gitVersion} OK\n";

        // Verificar MySQL
        $mysqlVersion = trim(shell_exec('mysql --version 2>/dev/null') ?: '');
        if (empty($mysqlVersion)) {
            echo "  ⚠️  MySQL client no encontrado, pero continuando...\n";
        } else {
            echo "  ✓ MySQL disponible\n";
        }

        echo "  ✅ Todos los requisitos verificados\n\n";
    }

    /**
     * Configura la base de datos
     */
    private function setupDatabase(): void
    {
        echo "🗄️  Configurando base de datos...\n";

        try {
            $this->conn = new mysqli(
                $this->config['db_host'],
                $this->config['db_user'],
                $this->config['db_pass']
            );

            if ($this->conn->connect_error) {
                throw new Exception("Error conectando a MySQL: " . $this->conn->connect_error);
            }

            // Crear base de datos si no existe
            $dbName = $this->config['db_name'];
            $this->conn->query("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->conn->select_db($dbName);

            echo "  ✓ Base de datos '{$dbName}' configurada\n";

            // Ejecutar esquema de la base de datos
            $schemaFile = $this->basePath . '/app/Modules/Database/change_management_schema.sql';
            if (file_exists($schemaFile)) {
                $schema = file_get_contents($schemaFile);
                
                // Ejecutar cada statement
                $statements = explode(';', $schema);
                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if (!empty($statement) && !preg_match('/^--/', $statement)) {
                        $this->conn->query($statement);
                    }
                }

                echo "  ✓ Esquema de base de datos aplicado\n";
            }

        } catch (Exception $e) {
            throw new Exception("Error configurando base de datos: " . $e->getMessage());
        }

        echo "  ✅ Base de datos configurada exitosamente\n\n";
    }

    /**
     * Crea directorios necesarios
     */
    private function createDirectories(): void
    {
        echo "📁 Creando estructura de directorios...\n";

        $directories = [
            'storage/backups',
            'storage/configurations',
            'storage/configurations/backups',
            'storage/configurations/exports',
            'storage/logs',
            'storage/reports',
            'app/Modules/AlertSystem/logs',
            'app/Modules/AlertSystem/public'
        ];

        foreach ($directories as $dir) {
            $fullPath = $this->basePath . '/' . $dir;
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0755, true);
                echo "  ✓ Directorio creado: {$dir}\n";
            } else {
                echo "  ✓ Directorio existe: {$dir}\n";
            }
        }

        echo "  ✅ Estructura de directorios lista\n\n";
    }

    /**
     * Configura repositorio Git para configuraciones
     */
    private function setupGitRepository(): void
    {
        echo "🔧 Configurando repositorio Git para configuraciones...\n";

        $configPath = $this->basePath . '/storage/configurations';
        
        if (!is_dir($configPath . '/.git')) {
            chdir($configPath);
            
            shell_exec('git init 2>/dev/null');
            shell_exec('git config user.name "Sistema ISO 17025" 2>/dev/null');
            shell_exec('git config user.email "sistema@empresa.com" 2>/dev/null');
            
            // Crear .gitignore
            file_put_contents('.gitignore', "backups/\nexports/\n*.tmp\n");
            
            echo "  ✓ Repositorio Git inicializado\n";
        } else {
            echo "  ✓ Repositorio Git ya existe\n";
        }

        chdir($this->basePath);
        echo "  ✅ Repositorio Git configurado\n\n";
    }

    /**
     * Instala dependencias de Node.js
     */
    private function installNodeJSDependencies(): void
    {
        echo "📦 Instalando dependencias de Node.js...\n";

        $alertSystemPath = $this->basePath . '/app/Modules/AlertSystem';
        
        if (is_dir($alertSystemPath)) {
            chdir($alertSystemPath);
            
            echo "  🔄 Ejecutando npm install...\n";
            $output = shell_exec('npm install 2>&1');
            
            if (strpos($output, 'error') !== false) {
                throw new Exception("Error instalando dependencias de Node.js: " . $output);
            }
            
            echo "  ✓ Dependencias de Node.js instaladas\n";
        }

        chdir($this->basePath);
        echo "  ✅ Node.js configurado exitosamente\n\n";
    }

    /**
     * Crea archivos de configuración
     */
    private function createConfigurationFiles(): void
    {
        echo "⚙️  Creando archivos de configuración...\n";

        // Archivo .env para Node.js
        $envContent = $this->generateEnvFile();
        file_put_contents($this->basePath . '/app/Modules/AlertSystem/.env', $envContent);
        echo "  ✓ Archivo .env creado\n";

        // Configuración de PHP
        $phpConfigContent = $this->generatePHPConfig();
        file_put_contents($this->basePath . '/app/config.php', $phpConfigContent);
        echo "  ✓ Configuración PHP creada\n";

        // Configuración del servidor web
        $htaccessContent = $this->generateHtaccessFile();
        file_put_contents($this->basePath . '/public/.htaccess', $htaccessContent);
        echo "  ✓ Archivo .htaccess creado\n";

        echo "  ✅ Archivos de configuración creados\n\n";
    }

    /**
     * Configura trabajos cron
     */
    private function setupCronJobs(): void
    {
        echo "⏰ Configurando trabajos cron...\n";

        $cronJobs = [
            "0 2 * * * php {$this->basePath}/app/Modules/BackupSystem/backup_scheduler.php --job=daily_backup",
            "0 1 * * 0 php {$this->basePath}/app/Modules/BackupSystem/backup_scheduler.php --job=weekly_backup",
            "0 0 1 * * php {$this->basePath}/app/Modules/BackupSystem/backup_scheduler.php --job=monthly_backup",
            "0 3 * * * php {$this->basePath}/maintenance/cleanup_old_logs.php",
            "*/5 * * * * curl -s http://localhost:3001/api/system/health > /dev/null"
        ];

        // Crear archivo de cron jobs
        $cronFile = $this->basePath . '/cron_jobs.txt';
        file_put_contents($cronFile, implode("\n", $cronJobs) . "\n");

        echo "  ✓ Archivo de cron jobs creado: {$cronFile}\n";
        echo "  ⚠️  Para activar los cron jobs, ejecute: crontab {$cronFile}\n";

        echo "  ✅ Cron jobs configurados\n\n";
    }

    /**
     * Inicializa datos por defecto
     */
    private function initializeDefaultData(): void
    {
        echo "📊 Inicializando datos por defecto...\n";

        // Crear usuario developer si no existe
        $stmt = $this->conn->prepare("SELECT id FROM usuarios WHERE usuario = 'developer' LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $hashedPassword = password_hash('developer123', PASSWORD_DEFAULT);
            $stmt = $this->conn->prepare("
                INSERT INTO usuarios (usuario, password_hash, nombre, email, rol, estado, created_at) 
                VALUES ('developer', ?, 'Desarrollador Sistema', 'developer@sistema.com', 'developer', 'activo', NOW())
            ");
            $stmt->bind_param("s", $hashedPassword);
            $stmt->execute();
            echo "  ✓ Usuario developer creado (password: developer123)\n";
        } else {
            echo "  ✓ Usuario developer ya existe\n";
        }

        // Inicializar configuraciones por defecto
        require_once $this->basePath . '/app/Modules/ConfigurationControl/ConfigurationVersionControl.php';
        $configControl = new ConfigurationVersionControl();
        echo "  ✓ Configuraciones inicializadas\n";

        echo "  ✅ Datos por defecto inicializados\n\n";
    }

    /**
     * Prueba componentes del sistema
     */
    private function testSystemComponents(): void
    {
        echo "🧪 Probando componentes del sistema...\n";

        // Probar conexión a base de datos
        if ($this->conn->ping()) {
            echo "  ✓ Conexión a base de datos OK\n";
        } else {
            throw new Exception("Error en conexión a base de datos");
        }

        // Probar sistema de auditoría
        try {
            require_once $this->basePath . '/app/Modules/AuditSystem/AuditManager.php';
            $auditManager = new AuditManager();
            echo "  ✓ Sistema de auditoría OK\n";
        } catch (Exception $e) {
            throw new Exception("Error en sistema de auditoría: " . $e->getMessage());
        }

        // Probar sistema de backup
        try {
            require_once $this->basePath . '/app/Modules/BackupSystem/BackupManager.php';            $backupManager = new BackupManager();
            echo "  ✓ Sistema de backup OK\n";
        } catch (Exception $e) {
            throw new Exception("Error en sistema de backup: " . $e->getMessage());
        }

        // Probar sistema de incidentes
        try {
            require_once $this->basePath . '/app/Modules/IncidentManagement/IncidentManager.php';
            $incidentManager = new IncidentManager();
            echo "  ✓ Sistema de incidentes OK\n";
        } catch (Exception $e) {
            throw new Exception("Error en sistema de incidentes: " . $e->getMessage());
        }

        echo "  ✅ Todos los componentes probados exitosamente\n\n";
    }

    /**
     * Muestra instrucciones post-instalación
     */
    private function displayPostInstallationInstructions(): void
    {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "🎉 INSTALACIÓN COMPLETADA - INSTRUCCIONES FINALES\n";
        echo str_repeat("=", 60) . "\n\n";

        echo "📋 PASOS SIGUIENTES:\n\n";

        echo "1. 🌐 SERVIDOR WEB:\n";
        echo "   - Configurar servidor web para apuntar a: {$this->basePath}/public\n";
        echo "   - URL del sistema: http://tu-dominio.com\n";
        echo "   - URL del dashboard: http://tu-dominio.com/admin_dashboard.html\n\n";

        echo "2. 🔔 SISTEMA DE ALERTAS:\n";
        echo "   - Iniciar servidor de Node.js:\n";
        echo "     cd {$this->basePath}/app/Modules/AlertSystem\n";
        echo "     npm start\n";
        echo "   - El servidor se ejecutará en: http://localhost:3001\n\n";

        echo "3. ⏰ CRON JOBS:\n";
        echo "   - Activar cron jobs ejecutando:\n";
        echo "     crontab {$this->basePath}/cron_jobs.txt\n\n";

        echo "4. 🔐 ACCESO AL SISTEMA:\n";
        echo "   - Usuario: developer\n";
        echo "   - Contraseña: developer123\n";
        echo "   - ⚠️  CAMBIAR LA CONTRASEÑA DESPUÉS DEL PRIMER ACCESO\n\n";

        echo "5. 🔧 CONFIGURACIÓN ADICIONAL:\n";
        echo "   - Editar variables de entorno en: {$this->basePath}/app/Modules/AlertSystem/.env\n";
        echo "   - Configurar integrations (JIRA, Trello, etc.) desde el dashboard\n";
        echo "   - Configurar notificaciones por email\n\n";

        echo "6. 📊 MONITOREO:\n";
        echo "   - Dashboard: http://tu-dominio.com/admin_dashboard.html\n";
        echo "   - API de salud: http://localhost:3001/api/system/status\n";
        echo "   - Logs: {$this->basePath}/storage/logs/\n\n";

        echo "📚 DOCUMENTACIÓN:\n";
        echo "   - Manual de usuario: {$this->basePath}/docs/\n";
        echo "   - API Documentation: Ver archivos en /api/\n\n";

        echo "🆘 SOPORTE:\n";
        echo "   - En caso de problemas, revisar logs en storage/logs/\n";
        echo "   - Verificar que todos los servicios estén ejecutándose\n\n";

        echo str_repeat("=", 60) . "\n";
        echo "✨ El Sistema de Gestión de Cambios ISO 17025 está listo!\n";
        echo str_repeat("=", 60) . "\n\n";
    }

    /**
     * Carga configuración de instalación
     */
    private function loadConfiguration(): array
    {
        return [
            'db_host' => $_ENV['DB_HOST'] ?? 'localhost',
            'db_user' => $_ENV['DB_USER'] ?? 'root',
            'db_pass' => $_ENV['DB_PASS'] ?? '',
            'db_name' => $_ENV['DB_NAME'] ?? 'iso17025',
            'app_url' => $_ENV['APP_URL'] ?? 'http://localhost:8000',
            'node_port' => $_ENV['NODE_PORT'] ?? '3001'
        ];
    }

    /**
     * Genera archivo .env para Node.js
     */
    private function generateEnvFile(): string
    {
        return "# Configuración del Sistema de Alertas
NODE_ENV=production
PORT={$this->config['node_port']}

# Base de datos
DB_HOST={$this->config['db_host']}
DB_USER={$this->config['db_user']}
DB_PASS={$this->config['db_pass']}
DB_NAME={$this->config['db_name']}

# Email (configurar según su proveedor)
EMAIL_SERVICE=gmail
EMAIL_USER=
EMAIL_PASS=

# URLs
APP_URL={$this->config['app_url']}
WEBHOOK_BASE_URL={$this->config['app_url']}/webhooks

# Integraciones (opcional)
JIRA_ENABLED=false
JIRA_URL=
JIRA_USERNAME=
JIRA_PASSWORD=
JIRA_PROJECT_KEY=

TRELLO_ENABLED=false
TRELLO_API_KEY=
TRELLO_TOKEN=
TRELLO_BOARD_ID=

ASANA_ENABLED=false
ASANA_TOKEN=
ASANA_PROJECT_ID=

SERVICENOW_ENABLED=false
SERVICENOW_URL=
SERVICENOW_USERNAME=
SERVICENOW_PASSWORD=

MONDAY_ENABLED=false
MONDAY_TOKEN=
MONDAY_BOARD_ID=

# Seguridad
SESSION_SECRET=sistema-iso17025-" . bin2hex(random_bytes(16)) . "
";
    }

    /**
     * Genera configuración PHP
     */
    private function generatePHPConfig(): string
    {
        return "<?php
/**
 * Configuración del Sistema ISO 17025
 * Generado automáticamente por el instalador
 */

return [
    'database' => [
        'host' => '{$this->config['db_host']}',
        'user' => '{$this->config['db_user']}',
        'password' => '{$this->config['db_pass']}',
        'name' => '{$this->config['db_name']}'
    ],
    
    'app' => [
        'url' => '{$this->config['app_url']}',
        'name' => 'Sistema ISO 17025',
        'version' => '1.0.0',
        'environment' => 'production'
    ],
    
    'alerts' => [
        'node_server' => 'http://localhost:{$this->config['node_port']}',
        'enabled' => true
    ],
    
    'features' => [
        'audit_system' => true,
        'backup_system' => true,
        'incident_management' => true,
        'configuration_control' => true,
        'project_integration' => true,
        'real_time_monitoring' => true
    ]
];
";
    }

    /**
     * Genera archivo .htaccess
     */
    private function generateHtaccessFile(): string
    {
        return "# Sistema ISO 17025 - Configuración Apache
RewriteEngine On

# Redireccionar API calls
RewriteRule ^api/dashboard/(.*) api/dashboard_api.php [QSA,L]

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection \"1; mode=block\"
Header always set Strict-Transport-Security \"max-age=31536000; includeSubDomains\"

# Cache static assets
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css \"access plus 1 month\"
    ExpiresByType application/javascript \"access plus 1 month\"
    ExpiresByType image/png \"access plus 1 year\"
    ExpiresByType image/jpg \"access plus 1 year\"
    ExpiresByType image/jpeg \"access plus 1 year\"
    ExpiresByType image/gif \"access plus 1 year\"
    ExpiresByType image/ico \"access plus 1 year\"
    ExpiresByType image/icon \"access plus 1 year\"
    ExpiresByType text/plain \"access plus 1 month\"
    ExpiresByType application/pdf \"access plus 1 month\"
</IfModule>

# Compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
</IfModule>
";
    }
}

// Ejecutar instalación
if (php_sapi_name() === 'cli') {
    $installer = new ChangeManagementInstaller();
    $installer->install();
} else {
    echo "Este script debe ejecutarse desde la línea de comandos.\n";
    exit(1);
}