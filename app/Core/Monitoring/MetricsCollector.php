<?php

/**
 * Recolector de Métricas del Sistema SBL
 * 
 * Recopila y procesa métricas de rendimiento:
 * - Tiempo de respuesta de APIs
 * - Uso de memoria y recursos
 * - Consultas de base de datos
 * - Actividad de usuarios
 * - Carga del sistema
 * 
 * @author Sistema SBL
 * @version 1.0
 */

class MetricsCollector
{
    private $pdo;
    private $metrics = [];
    private $start_time;
    
    public function __construct()
    {
        $this->start_time = microtime(true);
        $this->initializeDatabase();
    }
    
    /**
     * Inicializar conexión a base de datos
     */
    private function initializeDatabase(): void
    {
        try {
            // Usar las mismas constantes que el sistema principal
            if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
                // Intenta incluir el archivo de configuración si las constantes no están definidas
                $configPath = __DIR__ . '/../../../config/database.php';
                if (file_exists($configPath)) {
                    require_once $configPath;
                }
            }
            if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
                $this->pdo = new PDO(
                    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                    DB_USER,
                    DB_PASS,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    ]
                );
            }
        } catch (PDOException $e) {
            $this->pdo = null;
        }
    }
    
    /**
     * Obtener métricas actuales del sistema
     */
    public function getCurrentMetrics(): array
    {
        $metrics = [
            'timestamp' => date('Y-m-d H:i:s'),
            'performance' => $this->getPerformanceMetrics(),
            'database' => $this->getDatabaseMetrics(),
            'application' => $this->getApplicationMetrics(),
            'system' => $this->getSystemMetrics(),
            'users' => $this->getUserMetrics()
        ];
        
        return $metrics;
    }
    
    /**
     * Métricas de rendimiento general
     */
    private function getPerformanceMetrics(): array
    {
        $metrics = [];
        
        // Tiempo de ejecución actual
        $metrics['execution_time'] = round((microtime(true) - $this->start_time) * 1000, 2);
        
        // Memoria PHP
        $metrics['memory_usage'] = [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'limit' => $this->parseBytes(ini_get('memory_limit')),
            'percentage' => round((memory_get_usage(true) / $this->parseBytes(ini_get('memory_limit'))) * 100, 2)
        ];
        
        // Información de PHP
        $metrics['php'] = [
            'version' => PHP_VERSION,
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'opcache_enabled' => extension_loaded('opcache') && ini_get('opcache.enable')
        ];
        
        // Carga del servidor (si está disponible)
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $metrics['server_load'] = [
                '1min' => round($load[0], 2),
                '5min' => round($load[1], 2),
                '15min' => round($load[2], 2)
            ];
        }
        
        return $metrics;
    }
    
    /**
     * Métricas de base de datos
     */
    private function getDatabaseMetrics(): array
    {
        $metrics = [
            'connection_status' => $this->pdo ? 'connected' : 'disconnected',
            'queries' => [],
            'performance' => []
        ];
        
        if (!$this->pdo) {
            return $metrics;
        }
        
        try {
            // Información de la base de datos
            $stmt = $this->pdo->query('SELECT VERSION() as version');
            $version = $stmt->fetchColumn();
            $metrics['version'] = $version;
            
            // Estadísticas de tablas principales
            $tables = ['usuarios', 'empresas', 'instrumentos', 'calibraciones', 'proveedores'];
            foreach ($tables as $table) {
                $start = microtime(true);
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM $table");
                $stmt->execute();
                $count = $stmt->fetchColumn();
                $query_time = round((microtime(true) - $start) * 1000, 2);
                
                $metrics['tables'][$table] = [
                    'count' => $count,
                    'query_time' => $query_time
                ];
            }
            
            // Consultas lentas (si está habilitado el slow query log)
            try {
                $stmt = $this->pdo->query("SHOW VARIABLES LIKE 'slow_query_log'");
                $slow_log = $stmt->fetch();
                $metrics['slow_query_log'] = $slow_log ? $slow_log['Value'] : 'unknown';
            } catch (Exception $e) {
                $metrics['slow_query_log'] = 'unknown';
            }
            
            // Conexiones activas
            try {
                $stmt = $this->pdo->query("SHOW STATUS LIKE 'Threads_connected'");
                $connections = $stmt->fetch();
                $metrics['active_connections'] = $connections ? (int)$connections['Value'] : 0;
            } catch (Exception $e) {
                $metrics['active_connections'] = 0;
            }
            
            // Uso de caché de consultas (si está habilitado)
            try {
                $stmt = $this->pdo->query("SHOW STATUS LIKE 'Qcache_hits'");
                $cache_hits = $stmt->fetch();
                $stmt = $this->pdo->query("SHOW STATUS LIKE 'Com_select'");
                $selects = $stmt->fetch();
                
                if ($cache_hits && $selects) {
                    $hit_ratio = $cache_hits['Value'] / ($cache_hits['Value'] + $selects['Value']) * 100;
                    $metrics['query_cache_hit_ratio'] = round($hit_ratio, 2);
                }
            } catch (Exception $e) {
                $metrics['query_cache_hit_ratio'] = 0;
            }
            
        } catch (Exception $e) {
            $metrics['error'] = $e->getMessage();
        }
        
        return $metrics;
    }
    
    /**
     * Métricas de la aplicación
     */
    private function getApplicationMetrics(): array
    {
        $metrics = [];
        
        if (!$this->pdo) {
            return ['error' => 'No database connection'];
        }
        
        try {
            // Usuarios activos en las últimas 24 horas
            $stmt = $this->pdo->query('
                SELECT COUNT(*) FROM usuarios 
                WHERE activo = 1 AND ultima_conexion > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ');
            $metrics['active_users_24h'] = $stmt->fetchColumn();
            
            // Usuarios únicos en la última semana
            $stmt = $this->pdo->query('
                SELECT COUNT(DISTINCT id) FROM usuarios 
                WHERE ultima_conexion > DATE_SUB(NOW(), INTERVAL 7 DAYS)
            ');
            $metrics['unique_users_7d'] = $stmt->fetchColumn();
            
            // Calibraciones por estado
            $stmt = $this->pdo->query('
                SELECT estado, COUNT(*) as cantidad 
                FROM calibraciones 
                GROUP BY estado
            ');
            $calibrations_by_status = [];
            while ($row = $stmt->fetch()) {
                $calibrations_by_status[$row['estado']] = $row['cantidad'];
            }
            $metrics['calibrations_by_status'] = $calibrations_by_status;
            
            // Actividad reciente
            $stmt = $this->pdo->query('
                SELECT COUNT(*) FROM calibraciones 
                WHERE fecha_creacion > DATE_SUB(NOW(), INTERVAL 7 DAYS)
            ');
            $metrics['recent_calibrations_7d'] = $stmt->fetchColumn();
            
            // Instrumentos activos
            $stmt = $this->pdo->query('
                SELECT COUNT(*) FROM instrumentos WHERE activo = 1
            ');
            $metrics['active_instruments'] = $stmt->fetchColumn();
            
            // Proveedores activos
            $stmt = $this->pdo->query('
                SELECT COUNT(*) FROM proveedores WHERE activo = 1
            ');
            $metrics['active_providers'] = $stmt->fetchColumn();
            
            // Empresas activas
            $stmt = $this->pdo->query('
                SELECT COUNT(*) FROM empresas WHERE activo = 1
            ');
            $metrics['active_companies'] = $stmt->fetchColumn();
            
            // Próximas calibraciones (30 días)
            $stmt = $this->pdo->query('
                SELECT COUNT(DISTINCT i.id) 
                FROM instrumentos i
                LEFT JOIN calibraciones c ON i.id = c.instrumento_id
                WHERE i.activo = 1 
                AND c.fecha_proxima BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            ');
            $metrics['upcoming_calibrations_30d'] = $stmt->fetchColumn();
            
            // Calibraciones vencidas
            $stmt = $this->pdo->query('
                SELECT COUNT(DISTINCT i.id) 
                FROM instrumentos i
                LEFT JOIN calibraciones c ON i.id = c.instrumento_id
                WHERE i.activo = 1 AND c.fecha_proxima < CURDATE()
            ');
            $metrics['overdue_calibrations'] = $stmt->fetchColumn();
            
        } catch (Exception $e) {
            $metrics['error'] = $e->getMessage();
        }
        
        return $metrics;
    }
    
    /**
     * Métricas del sistema operativo
     */
    private function getSystemMetrics(): array
    {
        $metrics = [];
        
        // Información del sistema
        $metrics['os'] = PHP_OS;
        $metrics['server_software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'unknown';
        
        // Uso de disco
        $disk_total = disk_total_space(__DIR__);
        $disk_free = disk_free_space(__DIR__);
        if ($disk_total && $disk_free) {
            $disk_used = $disk_total - $disk_free;
            $metrics['disk'] = [
                'total' => $disk_total,
                'used' => $disk_used,
                'free' => $disk_free,
                'usage_percentage' => round(($disk_used / $disk_total) * 100, 2)
            ];
        }
        
        // Información de la sesión
        $metrics['session'] = [
            'save_path' => session_save_path(),
            'gc_maxlifetime' => ini_get('session.gc_maxlifetime'),
            'cookie_lifetime' => ini_get('session.cookie_lifetime')
        ];
        
        // Contar archivos de sesión activos
        $session_path = session_save_path() ?: sys_get_temp_dir();
        if (is_dir($session_path)) {
            $session_files = glob($session_path . '/sess_*');
            $metrics['active_sessions'] = count($session_files);
        }
        
        // Información de tiempo
        $metrics['timezone'] = date_default_timezone_get();
        $metrics['server_time'] = date('Y-m-d H:i:s');
        
        return $metrics;
    }
    
    /**
     * Métricas de usuarios
     */
    private function getUserMetrics(): array
    {
        $metrics = [];
        
        if (!$this->pdo) {
            return ['error' => 'No database connection'];
        }
        
        try {
            // Distribución por roles
            $stmt = $this->pdo->query('
                SELECT r.nombre as role, COUNT(u.id) as cantidad
                FROM usuarios u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.activo = 1
                GROUP BY r.id, r.nombre
            ');
            $roles_distribution = [];
            while ($row = $stmt->fetch()) {
                $roles_distribution[$row['role'] ?? 'Sin rol'] = $row['cantidad'];
            }
            $metrics['roles_distribution'] = $roles_distribution;
            
            // Distribución por portales
            $stmt = $this->pdo->query('
                SELECT p.nombre as portal, COUNT(u.id) as cantidad
                FROM usuarios u
                LEFT JOIN portals p ON u.portal_id = p.id
                WHERE u.activo = 1
                GROUP BY p.id, p.nombre
            ');
            $portals_distribution = [];
            while ($row = $stmt->fetch()) {
                $portals_distribution[$row['portal'] ?? 'Sin portal'] = $row['cantidad'];
            }
            $metrics['portals_distribution'] = $portals_distribution;
            
            // Actividad por horas (últimas 24 horas)
            $stmt = $this->pdo->query('
                SELECT HOUR(ultima_conexion) as hora, COUNT(*) as cantidad
                FROM usuarios 
                WHERE ultima_conexion > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY HOUR(ultima_conexion)
                ORDER BY hora
            ');
            $hourly_activity = [];
            while ($row = $stmt->fetch()) {
                $hourly_activity[$row['hora']] = $row['cantidad'];
            }
            $metrics['hourly_activity'] = $hourly_activity;
            
        } catch (Exception $e) {
            $metrics['error'] = $e->getMessage();
        }
        
        return $metrics;
    }
    
    /**
     * Registrar métricas personalizadas
     */
    public function recordMetric(string $name, $value, array $tags = []): void
    {
        $this->metrics[] = [
            'name' => $name,
            'value' => $value,
            'tags' => $tags,
            'timestamp' => time()
        ];
    }
    
    /**
     * Obtener tiempo de respuesta de una operación
     */
    public function measureResponseTime(callable $operation): array
    {
        $start = microtime(true);
        
        try {
            $result = $operation();
            $success = true;
            $error = null;
        } catch (Exception $e) {
            $result = null;
            $success = false;
            $error = $e->getMessage();
        }
        
        $end = microtime(true);
        $response_time = round(($end - $start) * 1000, 2); // ms
        
        return [
            'response_time' => $response_time,
            'success' => $success,
            'result' => $result,
            'error' => $error,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Guardar métricas en base de datos
     */
    public function saveMetrics(array $metrics): void
    {
        if (!$this->pdo) {
            return;
        }
        
        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO system_metrics (
                    metric_name, metric_value, metric_tags, 
                    timestamp, created_at
                ) VALUES (?, ?, ?, ?, NOW())
            ');
            
            foreach ($this->metrics as $metric) {
                $stmt->execute([
                    $metric['name'],
                    json_encode($metric['value']),
                    json_encode($metric['tags']),
                    date('Y-m-d H:i:s', $metric['timestamp'])
                ]);
            }
            
            // También guardar snapshot completo de métricas
            $stmt = $this->pdo->prepare('
                INSERT INTO system_metrics (
                    metric_name, metric_value, metric_tags, 
                    timestamp, created_at
                ) VALUES (?, ?, ?, ?, NOW())
            ');
            
            $stmt->execute([
                'system_snapshot',
                json_encode($metrics),
                json_encode(['type' => 'full_snapshot']),
                date('Y-m-d H:i:s')
            ]);
            
        } catch (PDOException $e) {
            // Si la tabla no existe, crearla
            $this->createMetricsTable();
        }
    }
    
    /**
     * Crear tabla de métricas si no existe
     */
    private function createMetricsTable(): void
    {
        if (!$this->pdo) {
            return;
        }
        
        try {
            $this->pdo->exec('
                CREATE TABLE IF NOT EXISTS system_metrics (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    metric_name VARCHAR(100) NOT NULL,
                    metric_value JSON NOT NULL,
                    metric_tags JSON DEFAULT NULL,
                    timestamp DATETIME NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_metric_name (metric_name),
                    INDEX idx_timestamp (timestamp)
                )
            ');
        } catch (PDOException $e) {
            error_log('Error creando tabla system_metrics: ' . $e->getMessage());
        }
    }
    
    /**
     * Obtener historial de métricas
     */
    public function getMetricsHistory(string $metric_name, int $hours = 24): array
    {
        if (!$this->pdo) {
            return [];
        }
        
        try {
            $stmt = $this->pdo->prepare('
                SELECT * FROM system_metrics 
                WHERE metric_name = ? 
                AND timestamp > DATE_SUB(NOW(), INTERVAL ? HOUR)
                ORDER BY timestamp DESC
                LIMIT 1000
            ');
            
            $stmt->execute([$metric_name, $hours]);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Obtener estadísticas agregadas
     */
    public function getAggregatedStats(string $metric_name, int $hours = 24): array
    {
        if (!$this->pdo) {
            return [];
        }
        
        try {
            $stmt = $this->pdo->prepare('
                SELECT 
                    COUNT(*) as count,
                    AVG(JSON_EXTRACT(metric_value, "$")) as avg_value,
                    MIN(JSON_EXTRACT(metric_value, "$")) as min_value,
                    MAX(JSON_EXTRACT(metric_value, "$")) as max_value,
                    MIN(timestamp) as first_timestamp,
                    MAX(timestamp) as last_timestamp
                FROM system_metrics 
                WHERE metric_name = ? 
                AND timestamp > DATE_SUB(NOW(), INTERVAL ? HOUR)
                AND JSON_VALID(metric_value)
            ');
            
            $stmt->execute([$metric_name, $hours]);
            return $stmt->fetch() ?: [];
            
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Limpiar métricas antiguas
     */
    public function cleanupOldMetrics(int $days = 30): int
    {
        if (!$this->pdo) {
            return 0;
        }
        
        try {
            $stmt = $this->pdo->prepare('
                DELETE FROM system_metrics 
                WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)
            ');
            
            $stmt->execute([$days]);
            return $stmt->rowCount();
            
        } catch (PDOException $e) {
            return 0;
        }
    }
    
    /**
     * Parsear configuración de bytes de PHP
     */
    private function parseBytes(string $val): int
    {
        $val = trim($val);
        if (empty($val)) {
            return 0;
        }
        
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
     * Obtener métricas en tiempo real para API
     */
    public function getRealTimeMetrics(): array
    {
        return [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'execution_time' => round((microtime(true) - $this->start_time) * 1000, 2),
            'timestamp' => microtime(true),
            'date' => date('Y-m-d H:i:s')
        ];
    }
}