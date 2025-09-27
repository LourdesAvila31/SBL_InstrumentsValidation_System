<?php

/**
 * Sistema de Monitoreo de Salud Automático
 * Verifica componentes críticos del sistema SBL
 * 
 * @version 1.0
 * @author Sistema SBL
 * @date 2025-09-26
 */

class HealthMonitor
{
    private $checks = [];
    private $alerts = [];
    private $metrics = [];
    private $config;
    private $logFile;

    public function __construct($config = null)
    {
        $this->config = $config ?? $this->getDefaultConfig();
        $this->logFile = __DIR__ . '/../../../storage/logs/health_monitor.log';
        $this->ensureLogDirectory();
    }

    /**
     * Configuración por defecto del monitor
     */
    private function getDefaultConfig(): array
    {
        return [
            'database' => [
                'host' => 'localhost',
                'user' => 'root',
                'password' => '',
                'database' => 'sbl_sistema'
            ],
            'thresholds' => [
                'response_time' => 2000, // ms
                'memory_usage' => 80,    // %
                'disk_usage' => 90,      // %
                'cpu_usage' => 85        // %
            ],
            'alerts' => [
                'email' => true,
                'log' => true,
                'dashboard' => true
            ]
        ];
    }

    /**
     * Ejecutar todas las verificaciones de salud
     */
    public function runHealthCheck(): array
    {
        $startTime = microtime(true);
        
        $this->log('INFO', 'Iniciando verificación de salud del sistema');
        
        // Ejecutar todas las verificaciones
        $results = [
            'timestamp' => date('Y-m-d H:i:s'),
            'overall_status' => 'healthy',
            'checks' => [
                'database' => $this->checkDatabase(),
                'filesystem' => $this->checkFilesystem(),
                'apis' => $this->checkAPIs(),
                'services' => $this->checkServices(),
                'performance' => $this->checkPerformance(),
                'security' => $this->checkSecurity()
            ],
            'metrics' => $this->collectMetrics(),
            'alerts' => $this->alerts,
            'execution_time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms'
        ];

        // Determinar estado general
        $results['overall_status'] = $this->determineOverallStatus($results['checks']);

        // Generar alertas si es necesario
        $this->processAlerts($results);

        $this->log('INFO', 'Verificación de salud completada', [
            'status' => $results['overall_status'],
            'execution_time' => $results['execution_time']
        ]);

        return $results;
    }

    /**
     * Verificar estado de la base de datos
     */
    private function checkDatabase(): array
    {
        $start = microtime(true);
        $result = [
            'status' => 'healthy',
            'message' => 'Base de datos funcionando correctamente',
            'details' => [],
            'response_time' => 0
        ];

        try {
            // Intentar conexión
            $pdo = new PDO(
                "mysql:host={$this->config['database']['host']}",
                $this->config['database']['user'],
                $this->config['database']['password']
            );
            
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Verificar versión de MySQL
            $stmt = $pdo->query("SELECT VERSION() as version");
            $version = $stmt->fetch(PDO::FETCH_ASSOC);
            $result['details']['mysql_version'] = $version['version'];

            // Verificar si la base de datos SBL existe
            $stmt = $pdo->query("SHOW DATABASES LIKE '{$this->config['database']['database']}'");
            $dbExists = $stmt->fetch();
            
            if ($dbExists) {
                $pdo->exec("USE {$this->config['database']['database']}");
                
                // Verificar tablas principales
                $tables = ['usuarios', 'instrumentos', 'calibraciones', 'proveedores'];
                $existingTables = [];
                
                foreach ($tables as $table) {
                    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                    if ($stmt->fetch()) {
                        $existingTables[] = $table;
                    }
                }
                
                $result['details']['existing_tables'] = $existingTables;
                $result['details']['tables_count'] = count($existingTables);
                
                if (count($existingTables) < 2) {
                    $result['status'] = 'warning';
                    $result['message'] = 'Base de datos existe pero faltan tablas principales';
                }
            } else {
                $result['status'] = 'warning';
                $result['message'] = 'Base de datos SBL no existe, usando conexión básica';
                $result['details']['database_exists'] = false;
            }

            $result['details']['connection'] = 'successful';
            
        } catch (Exception $e) {
            $result['status'] = 'error';
            $result['message'] = 'Error de conexión a base de datos: ' . $e->getMessage();
            $result['details']['error'] = $e->getMessage();
            
            $this->addAlert('database_error', 'Error de conexión a base de datos', $e->getMessage());
        }

        $result['response_time'] = round((microtime(true) - $start) * 1000, 2);
        return $result;
    }

    /**
     * Verificar sistema de archivos
     */
    private function checkFilesystem(): array
    {
        $result = [
            'status' => 'healthy',
            'message' => 'Sistema de archivos funcionando correctamente',
            'details' => []
        ];

        try {
            $baseDir = __DIR__ . '/../../..';
            
            // Verificar directorios críticos
            $criticalDirs = [
                'app/Core' => $baseDir . '/app/Core',
                'backend' => $baseDir . '/backend',
                'public' => $baseDir . '/public',
                'storage' => $baseDir . '/storage',
                'sistema-interno' => $baseDir . '/sistema-interno'
            ];

            $missingDirs = [];
            $existingDirs = [];

            foreach ($criticalDirs as $name => $path) {
                if (is_dir($path)) {
                    $existingDirs[] = $name;
                    // Verificar permisos de escritura
                    if (!is_writable($path) && $name === 'storage') {
                        $result['status'] = 'warning';
                        $result['message'] = 'Directorio storage sin permisos de escritura';
                    }
                } else {
                    $missingDirs[] = $name;
                }
            }

            $result['details']['existing_directories'] = $existingDirs;
            $result['details']['missing_directories'] = $missingDirs;

            // Verificar espacio en disco
            $freeSpace = disk_free_space($baseDir);
            $totalSpace = disk_total_space($baseDir);
            $usedPercent = (($totalSpace - $freeSpace) / $totalSpace) * 100;

            $result['details']['disk_usage'] = [
                'free_space' => $this->formatBytes($freeSpace),
                'total_space' => $this->formatBytes($totalSpace),
                'used_percent' => round($usedPercent, 2)
            ];

            if ($usedPercent > $this->config['thresholds']['disk_usage']) {
                $result['status'] = 'warning';
                $result['message'] = 'Espacio en disco bajo: ' . round($usedPercent, 2) . '%';
                $this->addAlert('disk_space', 'Espacio en disco bajo', "Uso: {$usedPercent}%");
            }

            if (!empty($missingDirs)) {
                $result['status'] = 'warning';
                $result['message'] = 'Algunos directorios críticos no existen';
            }

        } catch (Exception $e) {
            $result['status'] = 'error';
            $result['message'] = 'Error verificando sistema de archivos: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Verificar APIs del sistema
     */
    private function checkAPIs(): array
    {
        $result = [
            'status' => 'healthy',
            'message' => 'APIs funcionando correctamente',
            'details' => []
        ];

        $apis = [
            'usuarios' => '/public/api/usuarios.php',
            'calibraciones' => '/public/api/calibraciones.php',
            'proveedores' => '/public/api/proveedores.php'
        ];

        $workingAPIs = [];
        $failedAPIs = [];

        foreach ($apis as $name => $path) {
            $fullPath = __DIR__ . '/../../..' . $path;
            
            if (file_exists($fullPath)) {
                $workingAPIs[] = $name;
                
                // Verificar contenido básico del archivo
                $content = file_get_contents($fullPath);
                if (strpos($content, 'class') !== false && strpos($content, 'API') !== false) {
                    $result['details'][$name] = [
                        'status' => 'available',
                        'file_size' => filesize($fullPath)
                    ];
                } else {
                    $result['details'][$name] = ['status' => 'invalid_content'];
                    $failedAPIs[] = $name;
                }
            } else {
                $failedAPIs[] = $name;
                $result['details'][$name] = ['status' => 'not_found'];
            }
        }

        $result['details']['working_apis'] = $workingAPIs;
        $result['details']['failed_apis'] = $failedAPIs;

        if (!empty($failedAPIs)) {
            $result['status'] = 'warning';
            $result['message'] = 'Algunas APIs no están disponibles: ' . implode(', ', $failedAPIs);
        }

        return $result;
    }

    /**
     * Verificar servicios del sistema
     */
    private function checkServices(): array
    {
        $result = [
            'status' => 'healthy',
            'message' => 'Servicios funcionando correctamente',
            'details' => []
        ];

        // Verificar PHP
        $result['details']['php'] = [
            'version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize')
        ];

        // Verificar extensiones PHP críticas
        $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'curl', 'mbstring'];
        $missingExtensions = [];

        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $missingExtensions[] = $ext;
            }
        }

        $result['details']['php_extensions'] = [
            'required' => $requiredExtensions,
            'missing' => $missingExtensions
        ];

        if (!empty($missingExtensions)) {
            $result['status'] = 'error';
            $result['message'] = 'Faltan extensiones PHP críticas: ' . implode(', ', $missingExtensions);
        }

        // Verificar servidor web
        $result['details']['web_server'] = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';

        return $result;
    }

    /**
     * Verificar rendimiento del sistema
     */
    private function checkPerformance(): array
    {
        $result = [
            'status' => 'healthy',
            'message' => 'Rendimiento dentro de parámetros normales',
            'details' => []
        ];

        // Verificar uso de memoria
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseSize(ini_get('memory_limit'));
        $memoryPercent = ($memoryUsage / $memoryLimit) * 100;

        $result['details']['memory'] = [
            'current_usage' => $this->formatBytes($memoryUsage),
            'memory_limit' => $this->formatBytes($memoryLimit),
            'usage_percent' => round($memoryPercent, 2)
        ];

        if ($memoryPercent > $this->config['thresholds']['memory_usage']) {
            $result['status'] = 'warning';
            $result['message'] = 'Uso alto de memoria: ' . round($memoryPercent, 2) . '%';
            $this->addAlert('memory_usage', 'Uso alto de memoria', "Uso: {$memoryPercent}%");
        }

        // Verificar tiempo de respuesta
        $start = microtime(true);
        
        // Simular operación básica
        for ($i = 0; $i < 1000; $i++) {
            $temp = md5($i);
        }
        
        $responseTime = (microtime(true) - $start) * 1000;
        $result['details']['response_time'] = round($responseTime, 2) . 'ms';

        if ($responseTime > $this->config['thresholds']['response_time']) {
            $result['status'] = 'warning';
            $result['message'] = 'Tiempo de respuesta alto: ' . round($responseTime, 2) . 'ms';
        }

        return $result;
    }

    /**
     * Verificar aspectos de seguridad
     */
    private function checkSecurity(): array
    {
        $result = [
            'status' => 'healthy',
            'message' => 'Configuración de seguridad adecuada',
            'details' => []
        ];

        $warnings = [];

        // Verificar configuración PHP
        if (ini_get('display_errors')) {
            $warnings[] = 'display_errors está habilitado (riesgo de seguridad)';
        }

        if (!ini_get('session.cookie_httponly')) {
            $warnings[] = 'session.cookie_httponly deshabilitado';
        }

        // Verificar archivos sensibles
        $sensitiveFiles = [
            '.env' => __DIR__ . '/../../../.env',
            'config.php' => __DIR__ . '/../../../config.php'
        ];

        foreach ($sensitiveFiles as $name => $path) {
            if (file_exists($path)) {
                $result['details']['sensitive_files'][] = $name;
            }
        }

        $result['details']['security_warnings'] = $warnings;

        if (!empty($warnings)) {
            $result['status'] = 'warning';
            $result['message'] = 'Se detectaron algunas configuraciones de seguridad a revisar';
        }

        return $result;
    }

    /**
     * Recopilar métricas del sistema
     */
    private function collectMetrics(): array
    {
        return [
            'system' => [
                'php_version' => PHP_VERSION,
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
                'uptime' => $this->getSystemUptime()
            ],
            'requests' => [
                'current_time' => microtime(true),
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'System Monitor'
            ]
        ];
    }

    /**
     * Determinar estado general basado en verificaciones individuales
     */
    private function determineOverallStatus(array $checks): string
    {
        $errorCount = 0;
        $warningCount = 0;

        foreach ($checks as $check) {
            switch ($check['status']) {
                case 'error':
                    $errorCount++;
                    break;
                case 'warning':
                    $warningCount++;
                    break;
            }
        }

        if ($errorCount > 0) {
            return 'error';
        } elseif ($warningCount > 2) {
            return 'warning';
        } elseif ($warningCount > 0) {
            return 'warning';
        }

        return 'healthy';
    }

    /**
     * Procesar alertas basadas en resultados
     */
    private function processAlerts(array $results): void
    {
        if ($results['overall_status'] === 'error') {
            $this->addAlert('system_error', 'Error crítico del sistema', 'Se detectaron errores críticos en el sistema');
        } elseif ($results['overall_status'] === 'warning') {
            $this->addAlert('system_warning', 'Advertencia del sistema', 'Se detectaron advertencias en el sistema');
        }

        // Procesar alertas acumuladas
        foreach ($this->alerts as $alert) {
            $this->sendAlert($alert);
        }
    }

    /**
     * Agregar alerta
     */
    private function addAlert(string $type, string $title, string $message): void
    {
        $this->alerts[] = [
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s'),
            'severity' => $this->getAlertSeverity($type)
        ];
    }

    /**
     * Enviar alerta
     */
    private function sendAlert(array $alert): void
    {
        if ($this->config['alerts']['log']) {
            $this->log('ALERT', $alert['title'], $alert);
        }

        // Aquí se pueden agregar otros métodos de envío (email, webhook, etc.)
    }

    /**
     * Obtener severidad de alerta
     */
    private function getAlertSeverity(string $type): string
    {
        $severityMap = [
            'database_error' => 'critical',
            'disk_space' => 'warning',
            'memory_usage' => 'warning',
            'system_error' => 'critical',
            'system_warning' => 'warning'
        ];

        return $severityMap[$type] ?? 'info';
    }

    /**
     * Registrar evento en log
     */
    private function log(string $level, string $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        
        $logEntry = "[$timestamp] [$level] $message";
        if ($contextStr) {
            $logEntry .= " Context: $contextStr";
        }
        $logEntry .= PHP_EOL;

        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Asegurar que el directorio de logs existe
     */
    private function ensureLogDirectory(): void
    {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    /**
     * Formatear bytes a formato legible
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Parsear tamaño de memoria desde string
     */
    private function parseSize(string $size): int
    {
        $size = trim($size);
        $last = strtolower($size[strlen($size) - 1]);
        $size = (int) $size;
        
        switch ($last) {
            case 'g':
                $size *= 1024;
            case 'm':
                $size *= 1024;
            case 'k':
                $size *= 1024;
        }
        
        return $size;
    }

    /**
     * Obtener uptime del sistema (simplificado)
     */
    private function getSystemUptime(): string
    {
        // En Windows/XAMPP esto es más complejo, por ahora retornamos tiempo desde inicio de script
        return 'N/A (Sistema Windows/XAMPP)';
    }

    /**
     * Obtener resumen rápido del estado
     */
    public function getQuickStatus(): array
    {
        $fullCheck = $this->runHealthCheck();
        
        return [
            'status' => $fullCheck['overall_status'],
            'timestamp' => $fullCheck['timestamp'],
            'summary' => [
                'database' => $fullCheck['checks']['database']['status'],
                'filesystem' => $fullCheck['checks']['filesystem']['status'],
                'apis' => $fullCheck['checks']['apis']['status'],
                'performance' => $fullCheck['checks']['performance']['status']
            ],
            'alerts_count' => count($this->alerts)
        ];
    }
}