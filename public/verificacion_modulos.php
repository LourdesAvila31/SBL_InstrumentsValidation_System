<?php
/**
 * Verificación Específica de Módulos - SBL Sistema Interno
 * Script para verificar módulo por módulo de forma detallada
 */

class ModuleVerifier {
    private $baseUrl = 'http://localhost';
    private $basePath = 'C:/xampp/htdocs/SBL_SISTEMA_INTERNO';
    private $publicPath = 'C:/xampp/htdocs/SBL_SISTEMA_INTERNO/public';
    private $results = [];
    private $pdo = null;
    
    public function __construct() {
        // Verificar conexión a base de datos
        try {
            $this->pdo = new PDO("mysql:host=localhost;dbname=sbl_sistema_interno", "root", "");
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            $this->pdo = null;
        }
    }
    
    public function verifyModule($moduleName, $config) {
        echo "<div class='module-verification'>";
        echo "<h2>🔍 Verificando Módulo: {$config['name']}</h2>";
        
        $moduleResults = [
            'name' => $config['name'],
            'key' => $moduleName,
            'tests' => []
        ];
        
        // 1. Verificar estructura de archivos
        $this->verifyFileStructure($moduleName, $config, $moduleResults);
        
        // 2. Verificar conectividad web
        $this->verifyWebConnectivity($moduleName, $config, $moduleResults);
        
        // 3. Verificar base de datos
        $this->verifyDatabase($moduleName, $config, $moduleResults);
        
        // 4. Verificar funcionalidad específica
        $this->verifyModuleSpecificFunction($moduleName, $config, $moduleResults);
        
        $this->results[$moduleName] = $moduleResults;
        echo "</div>";
        
        return $moduleResults;
    }
    
    private function verifyFileStructure($moduleName, $config, &$results) {
        echo "<h3>📁 Estructura de Archivos</h3>";
        
        // Verificar directorio del módulo
        $moduleDir = $this->publicPath . "/apps/internal/{$moduleName}";
        $dirExists = is_dir($moduleDir);
        $this->addTest($results, "dir_exists", $dirExists, 
                      "Directorio del módulo existe: {$moduleDir}",
                      "Directorio del módulo NO existe: {$moduleDir}");
        
        if ($dirExists) {
            // Listar archivos en el directorio
            $files = scandir($moduleDir);
            $relevantFiles = array_filter($files, function($file) {
                return !in_array($file, ['.', '..']) && 
                       (pathinfo($file, PATHINFO_EXTENSION) == 'html' || 
                        pathinfo($file, PATHINFO_EXTENSION) == 'js' ||
                        pathinfo($file, PATHINFO_EXTENSION) == 'css');
            });
            
            echo "<div class='file-list'>";
            echo "<p><strong>Archivos encontrados:</strong></p>";
            echo "<ul>";
            foreach ($relevantFiles as $file) {
                echo "<li>{$file}</li>";
            }
            echo "</ul></div>";
            
            $this->addTest($results, "files_count", count($relevantFiles) > 0,
                          "Encontrados " . count($relevantFiles) . " archivos relevantes",
                          "No se encontraron archivos relevantes en el módulo");
        }
        
        // Verificar archivos específicos si están definidos
        if (isset($config['required_files'])) {
            foreach ($config['required_files'] as $file) {
                $filePath = $this->publicPath . $file;
                $exists = file_exists($filePath);
                $this->addTest($results, "required_file_" . basename($file), $exists,
                              "Archivo requerido existe: {$file}",
                              "Archivo requerido NO existe: {$file}");
            }
        }
    }
    
    private function verifyWebConnectivity($moduleName, $config, &$results) {
        echo "<h3>🌐 Conectividad Web</h3>";
        
        // Verificar página principal del módulo
        if (isset($config['main_page'])) {
            $url = $this->baseUrl . $config['main_page'];
            $accessible = $this->testUrl($url);
            $this->addTest($results, "main_page_access", $accessible,
                          "Página principal accesible: {$config['main_page']}",
                          "Página principal NO accesible: {$config['main_page']}");
        }
        
        // Verificar APIs si están definidas
        if (isset($config['apis'])) {
            foreach ($config['apis'] as $apiName => $apiUrl) {
                $url = $this->baseUrl . $apiUrl;
                $accessible = $this->testUrl($url);
                $this->addTest($results, "api_" . $apiName, $accessible,
                              "API {$apiName} accesible: {$apiUrl}",
                              "API {$apiName} NO accesible: {$apiUrl}");
            }
        }
    }
    
    private function verifyDatabase($moduleName, $config, &$results) {
        echo "<h3>🗄️ Base de Datos</h3>";
        
        if (!$this->pdo) {
            $this->addTest($results, "db_connection", false,
                          "Conexión a base de datos establecida",
                          "No se puede conectar a la base de datos");
            return;
        }
        
        $this->addTest($results, "db_connection", true,
                      "Conexión a base de datos establecida",
                      "No se puede conectar a la base de datos");
        
        // Verificar tablas específicas del módulo
        if (isset($config['tables'])) {
            foreach ($config['tables'] as $table) {
                try {
                    $stmt = $this->pdo->query("SHOW TABLES LIKE '{$table}'");
                    $exists = $stmt->rowCount() > 0;
                    $this->addTest($results, "table_" . $table, $exists,
                                  "Tabla '{$table}' existe en la base de datos",
                                  "Tabla '{$table}' NO existe en la base de datos");
                    
                    if ($exists) {
                        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM {$table}");
                        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                        echo "<p>📊 Tabla '{$table}' contiene {$count} registros</p>";
                    }
                } catch (PDOException $e) {
                    $this->addTest($results, "table_" . $table, false,
                                  "Tabla '{$table}' accesible",
                                  "Error al acceder tabla '{$table}': " . $e->getMessage());
                }
            }
        }
    }
    
    private function verifyModuleSpecificFunction($moduleName, $config, &$results) {
        echo "<h3>⚙️ Funcionalidad Específica</h3>";
        
        // Funcionalidad específica según el módulo
        switch ($moduleName) {
            case 'instrumentos':
                $this->verifyInstrumentosFunction($results);
                break;
            case 'usuarios':
                $this->verifyUsuariosFunction($results);
                break;
            case 'calibraciones':
                $this->verifyCalibracionesFunction($results);
                break;
            default:
                echo "<p>ℹ️ No hay verificaciones específicas definidas para este módulo</p>";
        }
    }
    
    private function verifyInstrumentosFunction(&$results) {
        // Verificar API de instrumentos funcional
        $apiUrl = $this->baseUrl . "/backend/instrumentos/gages/list_gages.php";
        $response = $this->getApiResponse($apiUrl);
        
        if ($response) {
            $data = json_decode($response, true);
            $isValid = isset($data['success']) && $data['success'];
            $this->addTest($results, "api_response_valid", $isValid,
                          "API responde con formato JSON válido",
                          "API no responde con formato válido");
            
            if ($isValid && isset($data['data'])) {
                $count = count($data['data']);
                $this->addTest($results, "api_data_available", $count > 0,
                              "API retorna {$count} instrumentos",
                              "API no retorna datos de instrumentos");
            }
        } else {
            $this->addTest($results, "api_response_valid", false,
                          "API responde correctamente",
                          "API no responde o hay error de conexión");
        }
    }
    
    private function verifyUsuariosFunction(&$results) {
        // Verificar tabla de usuarios
        if ($this->pdo) {
            try {
                $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM usuarios WHERE status = 'active'");
                $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                $this->addTest($results, "active_users", $count > 0,
                              "Hay {$count} usuarios activos en el sistema",
                              "No hay usuarios activos en el sistema");
            } catch (PDOException $e) {
                $this->addTest($results, "active_users", false,
                              "Consulta de usuarios exitosa",
                              "Error al consultar usuarios: " . $e->getMessage());
            }
        }
    }
    
    private function verifyCalibracionesFunction(&$results) {
        // Verificar tablas relacionadas con calibraciones
        if ($this->pdo) {
            try {
                $stmt = $this->pdo->query("SHOW TABLES LIKE '%calibra%'");
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $count = count($tables);
                $this->addTest($results, "calibration_tables", $count > 0,
                              "Encontradas {$count} tablas relacionadas con calibraciones",
                              "No se encontraron tablas de calibraciones");
            } catch (PDOException $e) {
                $this->addTest($results, "calibration_tables", false,
                              "Búsqueda de tablas exitosa",
                              "Error al buscar tablas: " . $e->getMessage());
            }
        }
    }
    
    private function testUrl($url) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'user_agent' => 'SBL-Module-Verifier/1.0'
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        return $response !== false;
    }
    
    private function getApiResponse($url) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'user_agent' => 'SBL-Module-Verifier/1.0'
            ]
        ]);
        
        return @file_get_contents($url, false, $context);
    }
    
    private function addTest(&$results, $testKey, $passed, $successMsg, $failMsg) {
        $results['tests'][$testKey] = [
            'passed' => $passed,
            'message' => $passed ? $successMsg : $failMsg
        ];
        
        $status = $passed ? 'pass' : 'fail';
        $icon = $passed ? '✅' : '❌';
        echo "<div class='test-result {$status}'>{$icon} " . ($passed ? $successMsg : $failMsg) . "</div>";
    }
    
    public function generateSummary() {
        echo "<div class='verification-summary'>";
        echo "<h2>📊 Resumen de Verificación por Módulos</h2>";
        
        $totalModules = count($this->results);
        $healthyModules = 0;
        
        foreach ($this->results as $moduleName => $result) {
            $totalTests = count($result['tests']);
            $passedTests = count(array_filter($result['tests'], function($test) {
                return $test['passed'];
            }));
            
            $healthPercentage = $totalTests > 0 ? round(($passedTests / $totalTests) * 100) : 0;
            
            if ($healthPercentage >= 75) $healthyModules++;
            
            echo "<div class='module-summary'>";
            echo "<h3>{$result['name']} ({$moduleName})</h3>";
            echo "<p>Pruebas pasadas: {$passedTests}/{$totalTests} ({$healthPercentage}%)</p>";
            
            $statusClass = $healthPercentage >= 80 ? 'healthy' : ($healthPercentage >= 60 ? 'warning' : 'critical');
            echo "<div class='health-indicator {$statusClass}'>";
            echo "Estado: " . ($healthPercentage >= 80 ? '🟢 Saludable' : ($healthPercentage >= 60 ? '🟡 Necesita atención' : '🔴 Crítico'));
            echo "</div>";
            echo "</div>";
        }
        
        echo "<div class='overall-summary'>";
        echo "<h3>Resumen General</h3>";
        echo "<p>Módulos saludables: {$healthyModules}/{$totalModules}</p>";
        $overallHealth = $totalModules > 0 ? round(($healthyModules / $totalModules) * 100) : 0;
        echo "<p>Salud general del sistema: {$overallHealth}%</p>";
        echo "</div>";
        
        echo "</div>";
    }
}

// CSS para el reporte
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .module-verification { background: white; margin: 20px 0; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .test-result { padding: 8px; margin: 5px 0; border-radius: 4px; }
    .test-result.pass { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
    .test-result.fail { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
    .file-list { background: #f8f9fa; padding: 10px; border-radius: 4px; margin: 10px 0; }
    .module-summary { background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 6px; }
    .health-indicator { padding: 10px; border-radius: 4px; font-weight: bold; }
    .health-indicator.healthy { background: #d4edda; color: #155724; }
    .health-indicator.warning { background: #fff3cd; color: #856404; }
    .health-indicator.critical { background: #f8d7da; color: #721c24; }
    .verification-summary { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
    .overall-summary { background: #e9ecef; padding: 15px; border-radius: 6px; margin-top: 20px; }
    h1, h2, h3 { color: #2c3e50; }
</style>";

echo "<h1>🔍 Verificación Detallada por Módulos - SBL Sistema Interno</h1>";

// Configuración de módulos a verificar
$modulesConfig = [
    'instrumentos' => [
        'name' => 'Gestión de Instrumentos',
        'main_page' => '/apps/internal/instrumentos/list_gages.html',
        'apis' => [
            'list' => '/backend/instrumentos/gages/list_gages.php',
            'get' => '/backend/instrumentos/gages/get_gage.php',
            'setup' => '/backend/instrumentos/setup_instrumentos_table.php'
        ],
        'tables' => ['instrumentos', 'auditoria_instrumentos'],
        'required_files' => [
            '/apps/internal/instrumentos/list_gages.html',
            '/backend/instrumentos/InstrumentosManager.php'
        ]
    ],
    'usuarios' => [
        'name' => 'Gestión de Usuarios',
        'main_page' => '/apps/internal/usuarios/index.html',
        'tables' => ['usuarios', 'roles', 'permisos']
    ],
    'calibraciones' => [
        'name' => 'Sistema de Calibraciones',
        'main_page' => '/apps/internal/calibraciones/index.html',
        'tables' => ['calibraciones', 'programas_calibracion']
    ],
    'proveedores' => [
        'name' => 'Gestión de Proveedores',
        'main_page' => '/apps/internal/proveedores/index.html',
        'tables' => ['proveedores']
    ],
    'auditoria' => [
        'name' => 'Sistema de Auditoría',
        'main_page' => '/apps/internal/auditoria/index.html',
        'tables' => ['auditoria_logs', 'auditoria_cambios']
    ],
    'reportes' => [
        'name' => 'Sistema de Reportes',
        'main_page' => '/apps/internal/reportes/index.html'
    ]
];

// Ejecutar verificación
$verifier = new ModuleVerifier();

foreach ($modulesConfig as $moduleKey => $moduleConfig) {
    $verifier->verifyModule($moduleKey, $moduleConfig);
}

// Generar resumen
$verifier->generateSummary();

echo "<div style='text-align: center; margin: 40px 0; padding: 20px; background: #e3f2fd; border-radius: 8px;'>";
echo "<h3>✅ Verificación Completada</h3>";
echo "<p>Fecha: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>Servidor: " . $_SERVER['SERVER_NAME'] . " | PHP: " . PHP_VERSION . "</p>";
echo "</div>";
?>