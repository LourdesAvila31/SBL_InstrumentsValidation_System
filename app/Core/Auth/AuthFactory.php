<?php

namespace App\Core\Auth;

use PDO;
use Exception;

/**
 * Factory para crear e instanciar el sistema de autenticación centralizado
 */
class AuthFactory
{
    private static ?AuthManager $instance = null;
    private static array $config = [];
    private static ?PDO $pdo = null;

    /**
     * Crea una instancia del sistema de autenticación
     */
    public static function create(array $config = []): AuthManager
    {
        if (self::$instance === null) {
            self::$config = array_merge(self::getDefaultConfig(), $config);
            self::$instance = self::buildAuthManager();
        }

        return self::$instance;
    }

    /**
     * Obtiene la instancia singleton
     */
    public static function getInstance(): ?AuthManager
    {
        return self::$instance;
    }

    /**
     * Resetea la instancia (útil para testing)
     */
    public static function reset(): void
    {
        self::$instance = null;
        self::$pdo = null;
        self::$config = [];
    }

    /**
     * Configura la conexión a la base de datos
     */
    public static function setDatabase(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    /**
     * Carga configuración desde archivo
     */
    public static function loadConfig(string $configFile): array
    {
        if (!file_exists($configFile)) {
            throw new Exception("Config file not found: $configFile");
        }

        $config = require $configFile;
        if (!is_array($config)) {
            throw new Exception("Config file must return an array");
        }

        return $config;
    }

    /**
     * Crea el AuthManager con todas sus dependencias
     */
    private static function buildAuthManager(): AuthManager
    {
        try {
            // Obtener conexión PDO
            $pdo = self::getPDO();

            // Crear logger
            $logger = self::createLogger();

            // Crear componentes
            $authProvider = self::createAuthProvider($pdo, $logger);
            $roleManager = self::createRoleManager($pdo, $logger);
            $permissionManager = self::createPermissionManager($pdo, $logger);
            $sessionManager = self::createSessionManager($pdo, $logger);

            // Crear AuthManager
            return new AuthManager(
                $authProvider,
                $roleManager,
                $permissionManager,
                $sessionManager,
                $logger,
                self::$config
            );

        } catch (Exception $e) {
            throw new Exception("Failed to build AuthManager: " . $e->getMessage());
        }
    }

    /**
     * Obtiene o crea la conexión PDO
     */
    private static function getPDO(): PDO
    {
        if (self::$pdo === null) {
            $dbConfig = self::$config['database'] ?? [];
            
            if (empty($dbConfig)) {
                // Intentar cargar configuración por defecto
                self::loadDefaultDatabaseConfig();
                $dbConfig = self::$config['database'];
            }

            self::$pdo = new PDO(
                self::buildDSN($dbConfig),
                $dbConfig['username'],
                $dbConfig['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ]
            );
        }

        return self::$pdo;
    }

    /**
     * Construye el DSN para PDO
     */
    private static function buildDSN(array $config): string
    {
        $driver = $config['driver'] ?? 'mysql';
        
        switch ($driver) {
            case 'mysql':
                return sprintf(
                    "mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
                    $config['host'],
                    $config['port'] ?? 3306,
                    $config['database']
                );
            
            case 'pgsql':
                return sprintf(
                    "pgsql:host=%s;port=%s;dbname=%s",
                    $config['host'],
                    $config['port'] ?? 5432,
                    $config['database']
                );
            
            case 'sqlite':
                return "sqlite:" . $config['database'];
                
            default:
                throw new Exception("Unsupported database driver: $driver");
        }
    }

    /**
     * Carga configuración de base de datos por defecto
     */
    private static function loadDefaultDatabaseConfig(): void
    {
        // Intentar cargar desde db_config.php
        $configFile = __DIR__ . '/../../db_config.php';
        if (file_exists($configFile)) {
            require_once $configFile;
            
            if (defined('DB_HOST')) {
                self::$config['database'] = [
                    'driver' => 'mysql',
                    'host' => DB_HOST,
                    'database' => DB_NAME,
                    'username' => DB_USER,
                    'password' => DB_PASS,
                    'port' => DB_PORT ?? 3306
                ];
                return;
            }
        }

        // Usar variables de entorno
        self::$config['database'] = [
            'driver' => $_ENV['DB_DRIVER'] ?? 'mysql',
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'database' => $_ENV['DB_NAME'] ?? 'sbl_sistema_interno',
            'username' => $_ENV['DB_USER'] ?? 'root',
            'password' => $_ENV['DB_PASS'] ?? '',
            'port' => $_ENV['DB_PORT'] ?? 3306
        ];
    }

    /**
     * Crea el logger
     */
    private static function createLogger(): AuthLogger
    {
        $logConfig = self::$config['logging'] ?? [];
        return new AuthLogger($logConfig);
    }

    /**
     * Crea el proveedor de autenticación
     */
    private static function createAuthProvider(PDO $pdo, AuthLogger $logger): DatabaseAuthProvider
    {
        $authConfig = self::$config['auth'] ?? [];
        return new DatabaseAuthProvider($pdo, $logger, $authConfig);
    }

    /**
     * Crea el gestor de roles
     */
    private static function createRoleManager(PDO $pdo, AuthLogger $logger): DatabaseRoleManager
    {
        $rbacConfig = self::$config['rbac'] ?? [];
        return new DatabaseRoleManager($pdo, $logger, $rbacConfig);
    }

    /**
     * Crea el gestor de permisos
     */
    private static function createPermissionManager(PDO $pdo, AuthLogger $logger): DatabasePermissionManager
    {
        $rbacConfig = self::$config['rbac'] ?? [];
        return new DatabasePermissionManager($pdo, $logger, $rbacConfig);
    }

    /**
     * Crea el gestor de sesiones
     */
    private static function createSessionManager(PDO $pdo, AuthLogger $logger): DatabaseSessionManager
    {
        $sessionConfig = self::$config['sessions'] ?? [];
        return new DatabaseSessionManager($pdo, $logger, $sessionConfig);
    }

    /**
     * Crea el middleware de autenticación
     */
    public static function createMiddleware(?AuthManager $authManager = null): AuthMiddleware
    {
        $auth = $authManager ?? self::getInstance();
        if (!$auth) {
            throw new Exception("AuthManager not initialized. Call AuthFactory::create() first.");
        }

        $middlewareConfig = self::$config['middleware'] ?? [];
        return new AuthMiddleware($auth, $middlewareConfig);
    }

    /**
     * Configuración por defecto
     */
    private static function getDefaultConfig(): array
    {
        return [
            // Configuración de autenticación
            'auth' => [
                'provider' => 'database',
                'max_attempts' => 5,
                'lockout_time' => 30,
                'allow_legacy_hashes' => true,
                'password_expiry_days' => 90
            ],

            // Configuración de contraseñas
            'password' => [
                'min_length' => 8,
                'require_complexity' => true,
                'expiry_days' => 90,
                'history_count' => 5
            ],

            // Configuración de sesiones
            'sessions' => [
                'lifetime' => 7200, // 2 horas
                'max_inactive_time' => 1800, // 30 minutos
                'check_ip' => false,
                'max_per_user' => 5,
                'cleanup_interval' => 3600
            ],

            // Configuración de roles y permisos
            'rbac' => [
                'enable_hierarchy' => true,
                'cache_ttl' => 300,
                'strict_context' => false,
                'enable_wildcards' => true
            ],

            // Configuración de logging
            'logging' => [
                'enabled' => true,
                'level' => 'INFO',
                'log_path' => __DIR__ . '/../../../storage/logs/auth.log',
                'format' => 'structured',
                'rotate_logs' => true,
                'max_file_size' => 10485760, // 10MB
                'max_backup_files' => 5
            ],

            // Configuración de middleware
            'middleware' => [
                'login_url' => '/login.php',
                'cache_permissions' => true,
                'log_access_attempts' => true
            ]
        ];
    }

    /**
     * Valida la configuración
     */
    private static function validateConfig(array $config): bool
    {
        $required = ['database'];
        
        foreach ($required as $key) {
            if (!isset($config[$key])) {
                throw new Exception("Missing required config key: $key");
            }
        }

        // Validar configuración de base de datos
        $dbRequired = ['host', 'database', 'username'];
        foreach ($dbRequired as $key) {
            if (!isset($config['database'][$key])) {
                throw new Exception("Missing required database config key: $key");
            }
        }

        return true;
    }

    /**
     * Crea un conjunto completo de herramientas de autenticación
     */
    public static function createAuthSystem(array $config = []): array
    {
        $authManager = self::create($config);
        $middleware = self::createMiddleware($authManager);

        return [
            'auth' => $authManager,
            'middleware' => $middleware,
            'config' => self::$config
        ];
    }

    /**
     * Crea helper functions globales
     */
    public static function createGlobalHelpers(): void
    {
        if (!function_exists('auth')) {
            function auth(): ?AuthManager {
                return AuthFactory::getInstance();
            }
        }

        if (!function_exists('user')) {
            function user(): ?array {
                $auth = AuthFactory::getInstance();
                return $auth ? $auth->getCurrentUser() : null;
            }
        }

        if (!function_exists('can')) {
            function can(string $permission, array $context = []): bool {
                $auth = AuthFactory::getInstance();
                return $auth ? $auth->hasPermission($permission, $context) : false;
            }
        }

        if (!function_exists('hasRole')) {
            function hasRole(string $role): bool {
                $auth = AuthFactory::getInstance();
                return $auth ? $auth->hasRole($role) : false;
            }
        }
    }

    /**
     * Información del sistema
     */
    public static function getSystemInfo(): array
    {
        return [
            'version' => '1.0.0',
            'components' => [
                'AuthManager',
                'AuthLogger', 
                'DatabaseAuthProvider',
                'DatabaseRoleManager',
                'DatabasePermissionManager', 
                'DatabaseSessionManager',
                'AuthMiddleware'
            ],
            'features' => [
                'Role-based access control (RBAC)',
                'Permission-based authorization',
                'Session management',
                'Audit logging',
                'Middleware integration',
                'Password policies',
                'Account lockout',
                'Rate limiting'
            ],
            'config' => self::$config
        ];
    }
}