<?php

/**
 * Sistema de Monitoreo de Salud del Sistema SBL
 * 
 * Proporciona verificación automática de:
 * - Conectividad de base de datos
 * - Estado del sistema de archivos
 * - Funcionalidad de APIs críticas
 * - Servicios externos
 * - Rendimiento general del sistema
 * 
 * @author Sistema SBL
 * @version 1.0
 */

require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/MetricsCollector.php';
require_once __DIR__ . '/AlertManager.php';

class HealthMonitor
{
    private $pdo;
    private $metrics;
    private $alerts;
    private $checks = [];
    private $thresholds;
    
    public function __construct()
    {
        $this->initializeDatabase();
        $this->metrics = new MetricsCollector();
        $this->alerts = new AlertManager();
        $this->loadThresholds();
    }
    
    /**
     * Inicializar conexión a base de datos
     */
    private function initializeDatabase(): void
    {
        try {
            $this->pdo = new PDO(
                "mysql:host=" . (defined('DB_HOST') ? DB_HOST : getenv('DB_HOST')) . ";dbname=" . (defined('DB_NAME') ? DB_NAME : getenv('DB_NAME')) . ";charset=utf8mb4",
                (defined('DB_USER') ? DB_USER : getenv('DB_USER')),
                (defined('DB_PASS') ? DB_PASS : getenv('DB_PASS')),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => 5 // Timeout de 5 segundos para verificaciones
                ]
            );
        } catch (PDOException $e) {
            $this->pdo = null;
        }
    }
    
    /**
     * Cargar umbrales de alerta desde configuración
     */
    private function loadThresholds(): void
    {
        $this->thresholds = [
            'database_response_time' => 2.0, // segundos
            'disk_usage' => 85, // porcentaje
            'memory_usage' => 90, // porcentaje
            'cpu_usage' => 80, // porcentaje
            'api_response_time' => 3.0, // segundos
            'error_rate' => 5, // porcentaje en la última hora
            'concurrent_users' => 100, // usuarios simultáneos
            'queue_size' => 1000 // trabajos pendientes
        ];
    }
    
    /**
     * Ejecutar todas las verificaciones de salud
     */
    public function runHealthChecks(): array
    {
        $start_time = microtime(true);
        
        $this->checks = [
            'timestamp' => date('Y-m-d H:i:s'),
            'overall_status' => 'healthy',
            'checks' => [
                'database' => $this->checkDatabase(),
                'filesystem' => $this->checkFilesystem(),
                'apis' => $this->checkAPIs(),
                'external_services' => $this->checkExternalServices(),
                'system_resources' => $this->checkSystemResources(),
                'application_health' => $this->checkApplicationHealth(),
                'security' => $this->checkSecurity()
            ],
            'metrics' => $this->collectCurrentMetrics(),
            'execution_time' => round((microtime(true) - $start_time) * 1000, 2) // ms
        ];
        
        // Determinar estado general
        $this->determineOverallStatus();
        
        // Guardar resultados
        $this->saveHealthCheckResults();
        
        // Enviar alertas si es necesario
        $this->processAlerts();
        
        return $this->checks;
    }
    
    /**
     * Verificar estado de la base de datos
     */
    private function checkDatabase(): array
    {
        $check = [
            'status' => 'healthy',
            'message' => 'Base de datos operativa',
            'details' => [],
            'response_time' => 0
        ];
        
        try {
            if (!$this->pdo) {
                throw new Exception('No hay conexión a la base de datos');
            }
            
            $start = microtime(true);
            
            // Verificar conectividad básica
            $stmt = $this->pdo->query('SELECT 1');
            $check['details']['connectivity'] = 'OK';
            
            // Verificar tablas críticas
            $critical_tables = ['usuarios', 'empresas', 'instrumentos', 'calibraciones'];
            foreach ($critical_tables as $table) {
                $stmt = $this->pdo->query("SHOW TABLES LIKE '$table'");
                if ($stmt->rowCount() === 0) {
                    throw new Exception("Tabla crítica '$table' no encontrada");
                }
                $check['details']['tables'][$table] = 'OK';
            }
            
            // Verificar rendimiento de consultas
            $stmt = $this->pdo->query('SELECT COUNT(*) FROM usuarios WHERE activo = 1');
            $users_count = $stmt->fetchColumn();
            $check['details']['active_users'] = $users_count;
            
            // Verificar integridad referencial
            $stmt = $this->pdo->query('
                SELECT COUNT(*) as orphans 
                FROM calibraciones c 
                LEFT JOIN instrumentos i ON c.instrumento_id = i.id 
                WHERE i.id IS NULL
            ');
            $orphans = $stmt->fetchColumn();
            if ($orphans > 0) {
                $check['details']['data_integrity'] = "WARNING: $orphans calibraciones huérfanas";
                $check['status'] = 'warning';
            } else {
                $check['details']['data_integrity'] = 'OK';
            }
            
            $check['response_time'] = round((microtime(true) - $start) * 1000, 2);
            
            // Verificar si el tiempo de respuesta excede el umbral
            if ($check['response_time'] > $this->thresholds['database_response_time'] * 1000) {
                $check['status'] = 'warning';
                $check['message'] = 'Base de datos responde lentamente';
            }
            
        } catch (Exception $e) {
            $check['status'] = 'critical';
            $check['message'] = 'Error en base de datos: ' . $e->getMessage();
            $check['details']['error'] = $e->getMessage();
        }
        
        return $check;
    }
    
    /**
     * Verificar sistema de archivos
     */
    private function checkFilesystem(): array
    {
        $check = [
            'status' => 'healthy',
            'message' => 'Sistema de archivos operativo',
            'details' => []
        ];
        
        try {
            // Verificar directorios críticos
            $critical_dirs = [
                'storage' => __DIR__ . '/../../../storage',
                'logs' => __DIR__ . '/../../../storage/logs',
                'uploads' => __DIR__ . '/../../../storage/uploads',
                'temp' => __DIR__ . '/../../../storage/temp'
            ];
            
            foreach ($critical_dirs as $name => $path) {
                if (!is_dir($path)) {
                    throw new Exception("Directorio crítico '$name' no existe: $path");
                }
                
                if (!is_writable($path)) {
                    throw new Exception("Directorio '$name' no es escribible: $path");
                }
                
                $check['details']['directories'][$name] = 'OK';
            }
            
            // Verificar espacio en disco
            $disk_total = disk_total_space(__DIR__);
            $disk_free = disk_free_space(__DIR__);
            $disk_used_percent = round((($disk_total - $disk_free) / $disk_total) * 100, 2);
            
            $check['details']['disk_usage'] = $disk_used_percent . '%';
            $check['details']['disk_free'] = $this->formatBytes($disk_free);
            $check['details']['disk_total'] = $this->formatBytes($disk_total);
            
            if ($disk_used_percent > $this->thresholds['disk_usage']) {
                $check['status'] = 'warning';
                $check['message'] = "Uso de disco alto: $disk_used_percent%";
            }
            
            // Verificar permisos de archivos críticos
            $critical_files = [
                'config' => __DIR__ . '/../db_config.php',
                'auth' => __DIR__ . '/../auth.php'
            ];
            
            foreach ($critical_files as $name => $file) {
                if (!file_exists($file)) {
                    throw new Exception("Archivo crítico '$name' no existe: $file");
                }
                
                if (!is_readable($file)) {
                    throw new Exception("Archivo '$name' no es legible: $file");
                }
                
                $check['details']['files'][$name] = 'OK';
            }
            
        } catch (Exception $e) {
            $check['status'] = 'critical';
            $check['message'] = 'Error en sistema de archivos: ' . $e->getMessage();
            $check['details']['error'] = $e->getMessage();
        }
        
        return $check;
    }
    
    /**
     * Verificar APIs críticas
     */
    private function checkAPIs(): array
    {
        $check = [
            'status' => 'healthy',
            'message' => 'APIs operativas',
            'details' => []
        ];
        
        try {
            $base_url = 'http://localhost/SBL_SISTEMA_INTERNO/public/api';
            $apis_to_test = [
                'usuarios' => '/usuarios.php/stats',
                'calibraciones' => '/calibraciones.php/stats',
                'proveedores' => '/proveedores.php/stats'
            ];
            
            foreach ($apis_to_test as $api_name => $endpoint) {
                $start = microtime(true);
                
                // Simular petición HTTP interna
                $context = stream_context_create([
                    'http' => [
                        'method' => 'GET',
                        'timeout' => 5,
                        'header' => 'Accept: application/json'
                    ]
                ]);
                
                $response = @file_get_contents($base_url . $endpoint, false, $context);
                $response_time = round((microtime(true) - $start) * 1000, 2);
                
                if ($response === false) {
                    $check['details']['apis'][$api_name] = [
                        'status' => 'ERROR',
                        'response_time' => $response_time
                    ];
                    $check['status'] = 'warning';
                } else {
                    $data = json_decode($response, true);
                    $check['details']['apis'][$api_name] = [
                        'status' => 'OK',
                        'response_time' => $response_time,
                        'data_valid' => is_array($data) && isset($data['success'])
                    ];
                    
                    if ($response_time > $this->thresholds['api_response_time'] * 1000) {
                        $check['status'] = 'warning';
                        $check['message'] = 'Algunas APIs responden lentamente';
                    }
                }
            }
            
        } catch (Exception $e) {
            $check['status'] = 'critical';
            $check['message'] = 'Error verificando APIs: ' . $e->getMessage();
            $check['details']['error'] = $e->getMessage();
        }
        
        return $check;
    }
    
    /**
     * Verificar servicios externos
     */
    private function checkExternalServices(): array
    {
        $check = [
            'status' => 'healthy',
            'message' => 'Servicios externos operativos',
            'details' => []
        ];
        
        try {
            // Verificar conectividad a internet
            $external_services = [
                'google_dns' => '8.8.8.8',
                'cloudflare_dns' => '1.1.1.1'
            ];
            
            foreach ($external_services as $service => $host) {
                $start = microtime(true);
                $socket = @fsockopen($host, 53, $errno, $errstr, 3);
                $response_time = round((microtime(true) - $start) * 1000, 2);
                
                if ($socket) {
                    fclose($socket);
                    $check['details']['external'][$service] = [
                        'status' => 'OK',
                        'response_time' => $response_time
                    ];
                } else {
                    $check['details']['external'][$service] = [
                        'status' => 'ERROR',
                        'error' => $errstr,
                        'response_time' => $response_time
                    ];
                    $check['status'] = 'warning';
                    $check['message'] = 'Algunos servicios externos no responden';
                }
            }
            
            // Verificar servicios de email (si están configurados)
            if (defined('MAIL_HOST') && MAIL_HOST) {
                $start = microtime(true);
                $socket = @fsockopen(MAIL_HOST, 587, $errno, $errstr, 5);
                $response_time = round((microtime(true) - $start) * 1000, 2);
                
                if ($socket) {
                    fclose($socket);
                    $check['details']['email'] = [
                        'status' => 'OK',
                        'response_time' => $response_time
                    ];
                } else {
                    $check['details']['email'] = [
                        'status' => 'ERROR',
                        'error' => $errstr
                    ];
                    $check['status'] = 'warning';
                }
            }
            
        } catch (Exception $e) {
            $check['status'] = 'warning';
            $check['message'] = 'Error verificando servicios externos: ' . $e->getMessage();
            $check['details']['error'] = $e->getMessage();
        }
        
        return $check;
    }
    
    /**
     * Verificar recursos del sistema
     */
    private function checkSystemResources(): array
    {
        $check = [
            'status' => 'healthy',
            'message' => 'Recursos del sistema normales',
            'details' => []
        ];
        
        try {
            // Verificar memoria PHP
            $memory_limit = $this->parseBytes(ini_get('memory_limit'));
            $memory_used = memory_get_usage(true);
            $memory_percent = round(($memory_used / $memory_limit) * 100, 2);
            
            $check['details']['memory'] = [
                'used' => $this->formatBytes($memory_used),
                'limit' => $this->formatBytes($memory_limit),
                'percentage' => $memory_percent . '%'
            ];
            
            if ($memory_percent > $this->thresholds['memory_usage']) {
                $check['status'] = 'warning';
                $check['message'] = "Uso de memoria alto: $memory_percent%";
            }
            
            // Verificar límites PHP
            $check['details']['php'] = [
                'version' => PHP_VERSION,
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size')
            ];
            
            // Verificar extensiones críticas
            $required_extensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl'];
            foreach ($required_extensions as $ext) {
                if (!extension_loaded($ext)) {
                    throw new Exception("Extensión PHP crítica no cargada: $ext");
                }
                $check['details']['extensions'][$ext] = 'OK';
            }
            
            // Verificar carga del servidor (si está disponible)
            if (function_exists('sys_getloadavg')) {
                $load = sys_getloadavg();
                $check['details']['server_load'] = [
                    '1min' => $load[0],
                    '5min' => $load[1],
                    '15min' => $load[2]
                ];
                
                // Verificar si la carga es alta (asumiendo 4 cores)
                if ($load[0] > 4) {
                    $check['status'] = 'warning';
                    $check['message'] = 'Carga del servidor alta';
                }
            }
            
        } catch (Exception $e) {
            $check['status'] = 'critical';
            $check['message'] = 'Error verificando recursos: ' . $e->getMessage();
            $check['details']['error'] = $e->getMessage();
        }
        
        return $check;
    }
    
    /**
     * Verificar salud de la aplicación
     */
    private function checkApplicationHealth(): array
    {
        $check = [
            'status' => 'healthy',
            'message' => 'Aplicación funcionando correctamente',
            'details' => []
        ];
        
        try {
            if ($this->pdo) {
                // Verificar usuarios activos recientes
                $stmt = $this->pdo->query('
                    SELECT COUNT(*) FROM usuarios 
                    WHERE activo = 1 AND ultima_conexion > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ');
                $active_users = $stmt->fetchColumn();
                $check['details']['active_users_24h'] = $active_users;
                
                // Verificar calibraciones recientes
                $stmt = $this->pdo->query('
                    SELECT COUNT(*) FROM calibraciones 
                    WHERE fecha_creacion > DATE_SUB(NOW(), INTERVAL 7 DAYS)
                ');
                $recent_calibrations = $stmt->fetchColumn();
                $check['details']['calibrations_7d'] = $recent_calibrations;
                
                // Verificar errores en logs (si existe tabla de logs)
                $stmt = $this->pdo->query("SHOW TABLES LIKE 'system_logs'");
                if ($stmt->rowCount() > 0) {
                    $stmt = $this->pdo->query('
                        SELECT COUNT(*) FROM system_logs 
                        WHERE level = "ERROR" AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                    ');
                    $recent_errors = $stmt->fetchColumn();
                    $check['details']['errors_1h'] = $recent_errors;
                    
                    if ($recent_errors > 10) {
                        $check['status'] = 'warning';
                        $check['message'] = 'Alto número de errores recientes';
                    }
                }
                
                // Verificar sesiones activas
                $session_path = session_save_path() ?: sys_get_temp_dir();
                if (is_dir($session_path)) {
                    $session_files = glob($session_path . '/sess_*');
                    $active_sessions = count($session_files);
                    $check['details']['active_sessions'] = $active_sessions;
                    
                    if ($active_sessions > $this->thresholds['concurrent_users']) {
                        $check['status'] = 'warning';
                        $check['message'] = 'Alto número de sesiones activas';
                    }
                }
            }
            
        } catch (Exception $e) {
            $check['status'] = 'warning';
            $check['message'] = 'Error verificando aplicación: ' . $e->getMessage();
            $check['details']['error'] = $e->getMessage();
        }
        
        return $check;
    }
    
    /**
     * Verificar aspectos de seguridad
     */
    private function checkSecurity(): array
    {
        $check = [
            'status' => 'healthy',
            'message' => 'Configuración de seguridad correcta',
            'details' => []
        ];
        
        try {
            // Verificar configuración PHP de seguridad
            $security_settings = [
                'display_errors' => ['expected' => 'Off', 'current' => ini_get('display_errors')],
                'expose_php' => ['expected' => 'Off', 'current' => ini_get('expose_php')],
                'allow_url_fopen' => ['expected' => 'Off', 'current' => ini_get('allow_url_fopen')],
                'session.cookie_httponly' => ['expected' => '1', 'current' => ini_get('session.cookie_httponly')]
            ];
            
            foreach ($security_settings as $setting => $config) {
                $current = $config['current'] ? 'On' : 'Off';
                $expected = $config['expected'];
                
                if ($current !== $expected) {
                    $check['details']['php_security'][$setting] = "WARNING: $current (esperado: $expected)";
                    $check['status'] = 'warning';
                } else {
                    $check['details']['php_security'][$setting] = 'OK';
                }
            }
            
            // Verificar permisos de archivos sensibles
            $sensitive_files = [
                __DIR__ . '/../db_config.php',
                __DIR__ . '/../../../.env'
            ];
            
            foreach ($sensitive_files as $file) {
                if (file_exists($file)) {
                    $perms = fileperms($file);
                    $perms_octal = substr(sprintf('%o', $perms), -4);
                    
                    // Los archivos de configuración no deberían ser legibles por otros
                    if ($perms_octal[3] !== '0') {
                        $check['details']['file_permissions'][basename($file)] = "WARNING: $perms_octal";
                        $check['status'] = 'warning';
                    } else {
                        $check['details']['file_permissions'][basename($file)] = 'OK';
                    }
                }
            }
            
            // Verificar headers de seguridad (si se puede acceder via HTTP)
            $check['details']['security_headers'] = 'Manual verification required';
            
        } catch (Exception $e) {
            $check['status'] = 'warning';
            $check['message'] = 'Error verificando seguridad: ' . $e->getMessage();
            $check['details']['error'] = $e->getMessage();
        }
        
        return $check;
    }
    
    /**
     * Recopilar métricas actuales
     */
    private function collectCurrentMetrics(): array
    {
        return $this->metrics->getCurrentMetrics();
    }
    
    /**
     * Determinar estado general del sistema
     */
    private function determineOverallStatus(): void
    {
        $critical_count = 0;
        $warning_count = 0;
        
        foreach ($this->checks['checks'] as $check) {
            if ($check['status'] === 'critical') {
                $critical_count++;
            } elseif ($check['status'] === 'warning') {
                $warning_count++;
            }
        }
        
        if ($critical_count > 0) {
            $this->checks['overall_status'] = 'critical';
        } elseif ($warning_count > 0) {
            $this->checks['overall_status'] = 'warning';
        } else {
            $this->checks['overall_status'] = 'healthy';
        }
        
        $this->checks['summary'] = [
            'critical_issues' => $critical_count,
            'warnings' => $warning_count,
            'healthy_checks' => count($this->checks['checks']) - $critical_count - $warning_count
        ];
    }
    
    /**
     * Guardar resultados en base de datos
     */
    private function saveHealthCheckResults(): void
    {
        if (!$this->pdo) {
            return;
        }
        
        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO health_checks (
                    timestamp, overall_status, execution_time, 
                    results_json, created_at
                ) VALUES (?, ?, ?, ?, NOW())
            ');
            
            $stmt->execute([
                $this->checks['timestamp'],
                $this->checks['overall_status'],
                $this->checks['execution_time'],
                json_encode($this->checks)
            ]);
            
        } catch (PDOException $e) {
            // Si no existe la tabla, crearla
            $this->createHealthCheckTable();
        }
    }
    
    /**
     * Crear tabla de health checks si no existe
     */
    private function createHealthCheckTable(): void
    {
        if (!$this->pdo) {
            return;
        }
        
        try {
            $this->pdo->exec('
                CREATE TABLE IF NOT EXISTS health_checks (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    timestamp DATETIME NOT NULL,
                    overall_status ENUM("healthy", "warning", "critical") NOT NULL,
                    execution_time DECIMAL(8,2) NOT NULL,
                    results_json JSON NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_timestamp (timestamp),
                    INDEX idx_status (overall_status)
                )
            ');
            
            // Intentar guardar nuevamente
            $this->saveHealthCheckResults();
            
        } catch (PDOException $e) {
            error_log('Error creando tabla health_checks: ' . $e->getMessage());
        }
    }
    
    /**
     * Procesar alertas basadas en los resultados
     */
    private function processAlerts(): void
    {
        if ($this->checks['overall_status'] !== 'healthy') {
            $this->alerts->sendHealthAlert($this->checks);
        }
    }
    
    /**
     * Obtener historial de health checks
     */
    public function getHealthHistory(int $hours = 24): array
    {
        if (!$this->pdo) {
            return [];
        }
        
        try {
            $stmt = $this->pdo->prepare('
                SELECT * FROM health_checks 
                WHERE timestamp > DATE_SUB(NOW(), INTERVAL ? HOUR)
                ORDER BY timestamp DESC
                LIMIT 100
            ');
            
            $stmt->execute([$hours]);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Formatear bytes en formato legible
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Parsear configuración de bytes de PHP
     */
    private function parseBytes(string $val): int
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = (int) $val;
        
        switch ($last) {
            case 'g':
                $val *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $val *= 1024 * 1024;
                break;
            case 'k':
                $val *= 1024;
                break;
        }
        
        return $val;
    }
    
    /**
     * Verificar si el sistema está saludable
     */
    public function isHealthy(): bool
    {
        $results = $this->runHealthChecks();
        return $results['overall_status'] === 'healthy';
    }
    
    /**
     * Obtener resumen rápido del estado
     */
    public function getQuickStatus(): array
    {
        $results = $this->runHealthChecks();
        
        return [
            'status' => $results['overall_status'],
            'timestamp' => $results['timestamp'],
            'summary' => $results['summary'],
            'execution_time' => $results['execution_time']
        ];
    }
}