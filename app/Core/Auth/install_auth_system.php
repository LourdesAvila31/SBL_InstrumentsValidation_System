<?php

/**
 * Script de instalación para el sistema de autenticación centralizado
 */

require_once __DIR__ . '/../../Core/db_config.php';

class AuthSystemInstaller
{
    private PDO $pdo;
    private array $config;

    public function __construct()
    {
        try {
            $this->pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e) {
            die('Error de conexión: ' . $e->getMessage());
        }
    }

    public function install(): bool
    {
        try {
            echo "🚀 Iniciando instalación del sistema de autenticación centralizado...\n\n";

            $this->createTables();
            $this->createDefaultRoles();
            $this->createDefaultPermissions();
            $this->setupRolePermissions();
            $this->updateExistingUsers();
            $this->createIndexes();

            echo "✅ Sistema de autenticación centralizado instalado correctamente!\n";
            echo "📝 Revisa los logs en storage/logs/auth.log\n";
            
            return true;

        } catch (Exception $e) {
            echo "❌ Error durante la instalación: " . $e->getMessage() . "\n";
            return false;
        }
    }

    private function createTables(): void
    {
        echo "📊 Creando tablas del sistema de autenticación...\n";

        // Tabla de roles
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS roles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) UNIQUE NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_by INT,
                INDEX idx_name (name)
            ) ENGINE=InnoDB
        ");

        // Tabla de permisos
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) UNIQUE NOT NULL,
                description TEXT,
                category VARCHAR(50),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_by INT,
                INDEX idx_name (name),
                INDEX idx_category (category)
            ) ENGINE=InnoDB
        ");

        // Tabla de asignación de roles a usuarios
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS user_roles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                role_name VARCHAR(50) NOT NULL,
                assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                assigned_by INT,
                expires_at TIMESTAMP NULL,
                UNIQUE KEY unique_user_role (user_id, role_name),
                FOREIGN KEY (role_name) REFERENCES roles(name) ON DELETE CASCADE,
                INDEX idx_user_id (user_id),
                INDEX idx_role_name (role_name),
                INDEX idx_expires (expires_at)
            ) ENGINE=InnoDB
        ");

        // Tabla de asignación de permisos a roles
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS role_permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                role_name VARCHAR(50) NOT NULL,
                permission VARCHAR(100) NOT NULL,
                assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_role_permission (role_name, permission),
                FOREIGN KEY (role_name) REFERENCES roles(name) ON DELETE CASCADE,
                FOREIGN KEY (permission) REFERENCES permissions(name) ON DELETE CASCADE,
                INDEX idx_role_name (role_name),
                INDEX idx_permission (permission)
            ) ENGINE=InnoDB
        ");

        // Tabla de permisos directos a usuarios
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS user_permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                permission VARCHAR(100) NOT NULL,
                context JSON,
                granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                granted_by INT,
                expires_at TIMESTAMP NULL,
                UNIQUE KEY unique_user_permission (user_id, permission),
                FOREIGN KEY (permission) REFERENCES permissions(name) ON DELETE CASCADE,
                INDEX idx_user_id (user_id),
                INDEX idx_permission (permission),
                INDEX idx_expires (expires_at)
            ) ENGINE=InnoDB
        ");

        // Tabla de jerarquía de roles
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS role_hierarchy (
                id INT AUTO_INCREMENT PRIMARY KEY,
                parent_role VARCHAR(50) NOT NULL,
                child_role VARCHAR(50) NOT NULL,
                UNIQUE KEY unique_hierarchy (parent_role, child_role),
                FOREIGN KEY (parent_role) REFERENCES roles(name) ON DELETE CASCADE,
                FOREIGN KEY (child_role) REFERENCES roles(name) ON DELETE CASCADE
            ) ENGINE=InnoDB
        ");

        // Tabla de sesiones de usuario
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS user_sessions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                session_id VARCHAR(128) UNIQUE NOT NULL,
                user_id INT NOT NULL,
                ip_address VARCHAR(45),
                user_agent TEXT,
                metadata JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NOT NULL,
                INDEX idx_session_id (session_id),
                INDEX idx_user_id (user_id),
                INDEX idx_expires (expires_at)
            ) ENGINE=InnoDB
        ");

        // Tabla de intentos de login
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS login_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                username VARCHAR(100),
                ip_address VARCHAR(45),
                user_agent TEXT,
                attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                success BOOLEAN DEFAULT FALSE,
                INDEX idx_user_id (user_id),
                INDEX idx_ip_address (ip_address),
                INDEX idx_attempted_at (attempted_at)
            ) ENGINE=InnoDB
        ");

        // Tabla de tokens de recuperación de contraseña
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS password_reset_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(128) UNIQUE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NOT NULL,
                used_at TIMESTAMP NULL,
                INDEX idx_token (token),
                INDEX idx_user_id (user_id),
                INDEX idx_expires (expires_at)
            ) ENGINE=InnoDB
        ");

        // Actualizar tabla de usuarios existente
        $this->pdo->exec("
            ALTER TABLE usuarios 
            ADD COLUMN IF NOT EXISTS must_change_password BOOLEAN DEFAULT FALSE,
            ADD COLUMN IF NOT EXISTS password_expires_at TIMESTAMP NULL,
            ADD COLUMN IF NOT EXISTS password_changed_at TIMESTAMP NULL,
            ADD COLUMN IF NOT EXISTS locked_at TIMESTAMP NULL,
            ADD COLUMN IF NOT EXISTS lock_reason TEXT,
            ADD COLUMN IF NOT EXISTS two_factor_enabled BOOLEAN DEFAULT FALSE,
            ADD COLUMN IF NOT EXISTS two_factor_secret VARCHAR(32)
        ");

        echo "✅ Tablas creadas correctamente\n";
    }

    private function createDefaultRoles(): void
    {
        echo "👥 Creando roles por defecto...\n";

        $defaultRoles = [
            ['superadmin', 'Super Administrador - Acceso completo al sistema'],
            ['admin', 'Administrador - Gestión general del sistema'],
            ['manager', 'Gerente - Supervisión y reportes'],
            ['operator', 'Operador - Operaciones diarias'],
            ['viewer', 'Visualizador - Solo lectura'],
            ['auditor', 'Auditor - Acceso de auditoría'],
            ['technician', 'Técnico - Mantenimiento y calibraciones'],
            ['quality', 'Calidad - Control de calidad'],
            ['guest', 'Invitado - Acceso limitado']
        ];

        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO roles (name, description) 
            VALUES (?, ?)
        ");

        foreach ($defaultRoles as $role) {
            $stmt->execute($role);
        }

        echo "✅ Roles creados correctamente\n";
    }

    private function createDefaultPermissions(): void
    {
        echo "🔐 Creando permisos por defecto...\n";

        $defaultPermissions = [
            // Sistema
            ['system.admin', 'Administración completa del sistema', 'system'],
            ['system.settings', 'Configuración del sistema', 'system'],
            ['system.logs', 'Ver logs del sistema', 'system'],
            ['system.backup', 'Realizar respaldos', 'system'],
            ['system.maintenance', 'Modo mantenimiento', 'system'],

            // Usuarios
            ['users.view', 'Ver usuarios', 'users'],
            ['users.create', 'Crear usuarios', 'users'],
            ['users.edit', 'Editar usuarios', 'users'],
            ['users.delete', 'Eliminar usuarios', 'users'],
            ['users.impersonate', 'Suplantar usuarios', 'users'],

            // Roles y permisos
            ['roles.view', 'Ver roles', 'roles'],
            ['roles.create', 'Crear roles', 'roles'],
            ['roles.edit', 'Editar roles', 'roles'],
            ['roles.delete', 'Eliminar roles', 'roles'],
            ['roles.assign', 'Asignar roles', 'roles'],

            // Calibraciones
            ['calibrations.view', 'Ver calibraciones', 'calibrations'],
            ['calibrations.create', 'Crear calibraciones', 'calibrations'],
            ['calibrations.edit', 'Editar calibraciones', 'calibrations'],
            ['calibrations.delete', 'Eliminar calibraciones', 'calibrations'],
            ['calibrations.approve', 'Aprobar calibraciones', 'calibrations'],

            // Instrumentos
            ['instruments.view', 'Ver instrumentos', 'instruments'],
            ['instruments.create', 'Crear instrumentos', 'instruments'],
            ['instruments.edit', 'Editar instrumentos', 'instruments'],
            ['instruments.delete', 'Eliminar instrumentos', 'instruments'],
            ['instruments.maintenance', 'Mantenimiento de instrumentos', 'instruments'],

            // Reportes
            ['reports.view', 'Ver reportes', 'reports'],
            ['reports.create', 'Crear reportes', 'reports'],
            ['reports.export', 'Exportar reportes', 'reports'],
            ['reports.schedule', 'Programar reportes', 'reports'],

            // Auditoría
            ['audit.view', 'Ver auditorías', 'audit'],
            ['audit.create', 'Crear auditorías', 'audit'],
            ['audit.edit', 'Editar auditorías', 'audit'],
            ['audit.approve', 'Aprobar auditorías', 'audit'],

            // Dashboard
            ['dashboard.view', 'Ver dashboard', 'dashboard'],
            ['dashboard.admin', 'Dashboard administrativo', 'dashboard'],

            // API
            ['api.access', 'Acceso a API', 'api'],
            ['api.admin', 'Administración de API', 'api']
        ];

        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO permissions (name, description, category) 
            VALUES (?, ?, ?)
        ");

        foreach ($defaultPermissions as $permission) {
            $stmt->execute($permission);
        }

        echo "✅ Permisos creados correctamente\n";
    }

    private function setupRolePermissions(): void
    {
        echo "🔗 Configurando permisos de roles...\n";

        $rolePermissions = [
            'superadmin' => ['system.*', 'users.*', 'roles.*', 'calibrations.*', 'instruments.*', 'reports.*', 'audit.*', 'dashboard.*', 'api.*'],
            'admin' => ['system.settings', 'system.logs', 'users.*', 'roles.*', 'calibrations.*', 'instruments.*', 'reports.*', 'dashboard.admin'],
            'manager' => ['users.view', 'calibrations.*', 'instruments.view', 'reports.*', 'dashboard.view'],
            'operator' => ['calibrations.view', 'calibrations.create', 'calibrations.edit', 'instruments.view', 'dashboard.view'],
            'viewer' => ['calibrations.view', 'instruments.view', 'reports.view', 'dashboard.view'],
            'auditor' => ['audit.*', 'calibrations.view', 'instruments.view', 'reports.view', 'dashboard.view'],
            'technician' => ['calibrations.*', 'instruments.*', 'dashboard.view'],
            'quality' => ['calibrations.*', 'instruments.view', 'reports.*', 'audit.view', 'dashboard.view'],
            'guest' => ['dashboard.view']
        ];

        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO role_permissions (role_name, permission) 
            VALUES (?, ?)
        ");

        foreach ($rolePermissions as $role => $permissions) {
            foreach ($permissions as $permission) {
                // Expandir wildcards
                if (strpos($permission, '*') !== false) {
                    $pattern = str_replace('*', '%', $permission);
                    $permStmt = $this->pdo->prepare("SELECT name FROM permissions WHERE name LIKE ?");
                    $permStmt->execute([$pattern]);
                    $expandedPermissions = $permStmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    foreach ($expandedPermissions as $expandedPerm) {
                        $stmt->execute([$role, $expandedPerm]);
                    }
                } else {
                    $stmt->execute([$role, $permission]);
                }
            }
        }

        echo "✅ Permisos de roles configurados correctamente\n";
    }

    private function updateExistingUsers(): void
    {
        echo "👤 Actualizando usuarios existentes...\n";

        // Asignar rol de superadmin al primer usuario admin
        $adminUser = $this->pdo->query("
            SELECT id FROM usuarios 
            WHERE nombre = 'admin' OR email LIKE '%admin%' 
            ORDER BY id ASC 
            LIMIT 1
        ")->fetch();

        if ($adminUser) {
            $stmt = $this->pdo->prepare("
                INSERT IGNORE INTO user_roles (user_id, role_name) 
                VALUES (?, ?)
            ");
            $stmt->execute([$adminUser['id'], 'superadmin']);
            echo "✅ Usuario admin configurado como superadmin\n";
        }

        // Asignar rol viewer a usuarios sin rol
        $this->pdo->exec("
            INSERT IGNORE INTO user_roles (user_id, role_name)
            SELECT u.id, 'viewer'
            FROM usuarios u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            WHERE ur.user_id IS NULL
        ");

        echo "✅ Usuarios sin rol configurados como viewer\n";
    }

    private function createIndexes(): void
    {
        echo "📈 Creando índices adicionales...\n";

        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_usuarios_active ON usuarios(activo)",
            "CREATE INDEX IF NOT EXISTS idx_usuarios_empresa ON usuarios(empresa_id)", 
            "CREATE INDEX IF NOT EXISTS idx_usuarios_ultimo_acceso ON usuarios(ultimo_acceso)",
            "CREATE INDEX IF NOT EXISTS idx_login_attempts_24h ON login_attempts(attempted_at, ip_address)",
            "CREATE INDEX IF NOT EXISTS idx_sessions_active ON user_sessions(expires_at, user_id)"
        ];

        foreach ($indexes as $index) {
            try {
                $this->pdo->exec($index);
            } catch (Exception $e) {
                // Ignorar errores de índices duplicados
            }
        }

        echo "✅ Índices creados correctamente\n";
    }

    public function createConfigFile(): void
    {
        $configContent = '<?php

/**
 * Configuración del sistema de autenticación centralizado
 */

return [
    // Configuración general
    "auth" => [
        "provider" => "database",
        "session_lifetime" => 7200, // 2 horas
        "max_inactive_time" => 1800, // 30 minutos
        "check_ip" => false,
        "enable_2fa" => false
    ],

    // Configuración de contraseñas
    "password" => [
        "min_length" => 8,
        "require_complexity" => true,
        "expiry_days" => 90,
        "history_count" => 5,
        "allow_legacy_hashes" => true
    ],

    // Configuración de bloqueo de cuentas
    "lockout" => [
        "max_attempts" => 5,
        "lockout_time" => 30, // minutos
        "auto_unlock" => true
    ],

    // Configuración de sesiones
    "sessions" => [
        "max_per_user" => 5,
        "cleanup_interval" => 3600, // 1 hora
        "cookie_secure" => false,
        "cookie_httponly" => true
    ],

    // Configuración de logging
    "logging" => [
        "enabled" => true,
        "level" => "INFO",
        "file" => __DIR__ . "/../../storage/logs/auth.log",
        "rotate" => true,
        "max_size" => 10485760 // 10MB
    ],

    // Configuración de roles y permisos
    "rbac" => [
        "enable_hierarchy" => true,
        "cache_ttl" => 300, // 5 minutos
        "strict_context" => false
    ]
];
';

        file_put_contents(__DIR__ . '/auth_config.php', $configContent);
        echo "✅ Archivo de configuración creado: auth_config.php\n";
    }
}

// Ejecutar instalación si se llama directamente
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $installer = new AuthSystemInstaller();
    
    if ($installer->install()) {
        $installer->createConfigFile();
        echo "\n🎉 ¡Instalación completada con éxito!\n";
        echo "📋 Próximos pasos:\n";
        echo "   1. Revisar la configuración en auth_config.php\n";
        echo "   2. Configurar las variables de entorno necesarias\n";
        echo "   3. Probar el sistema de autenticación\n";
        echo "   4. Configurar tareas programadas para limpieza automática\n\n";
    } else {
        echo "\n❌ La instalación falló. Revisa los errores anteriores.\n";
        exit(1);
    }
}