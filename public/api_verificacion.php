<?php
/**
 * API de Verificación del Sistema SBL
 * Proporciona endpoints JSON para el panel de diagnóstico
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Obtener acción solicitada
$action = $_GET['action'] ?? 'status';

// Función principal de enrutamiento
switch ($action) {
    case 'infrastructure':
        echo json_encode(checkInfrastructure());
        break;
    case 'database':
        echo json_encode(checkDatabase());
        break;
    case 'modules':
        echo json_encode(checkModules());
        break;
    case 'files':
        echo json_encode(checkFiles());
        break;
    case 'full_report':
        echo json_encode(generateFullReport());
        break;
    default:
        echo json_encode(getSystemStatus());
        break;
}

function checkInfrastructure() {
    $results = [
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => [
            'php' => [
                'version' => PHP_VERSION,
                'status' => version_compare(PHP_VERSION, '7.4.0', '>=') ? 'ok' : 'warning',
                'message' => 'PHP ' . PHP_VERSION . (version_compare(PHP_VERSION, '7.4.0', '>=') ? ' (Compatible)' : ' (Actualización recomendada)')
            ],
            'extensions' => [],
            'server' => [
                'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
                'server_name' => $_SERVER['SERVER_NAME'] ?? 'localhost'
            ],
            'memory' => [
                'limit' => ini_get('memory_limit'),
                'usage' => formatBytes(memory_get_usage(true)),
                'peak' => formatBytes(memory_get_peak_usage(true))
            ]
        ]
    ];
    
    // Verificar extensiones PHP críticas
    $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl', 'curl'];
    foreach ($requiredExtensions as $ext) {
        $results['data']['extensions'][$ext] = [
            'loaded' => extension_loaded($ext),
            'status' => extension_loaded($ext) ? 'ok' : 'error'
        ];
    }
    
    return $results;
}

function checkDatabase() {
    $results = [
        'success' => false,
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => []
    ];
    
    try {
        // Conexión básica a MySQL
        $pdo = new PDO("mysql:host=localhost", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $results['data']['connection'] = [
            'status' => 'ok',
            'message' => 'Conexión a MySQL exitosa'
        ];
        
        // Verificar base de datos específica
        $stmt = $pdo->query("SHOW DATABASES LIKE 'sbl_sistema_interno'");
        $dbExists = $stmt->rowCount() > 0;
        
        $results['data']['database'] = [
            'exists' => $dbExists,
            'name' => 'sbl_sistema_interno',
            'status' => $dbExists ? 'ok' : 'warning'
        ];
        
        if ($dbExists) {
            $pdo = new PDO("mysql:host=localhost;dbname=sbl_sistema_interno", "root", "");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Contar tablas
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $results['data']['tables'] = [
                'count' => count($tables),
                'list' => $tables,
                'status' => count($tables) > 0 ? 'ok' : 'warning'
            ];
            
            // Verificar tablas específicas
            $specificTables = ['instrumentos', 'usuarios', 'auditoria_instrumentos'];
            $results['data']['specific_tables'] = [];
            
            foreach ($specificTables as $table) {
                $exists = in_array($table, $tables);
                $count = 0;
                
                if ($exists) {
                    try {
                        $stmt = $pdo->query("SELECT COUNT(*) as count FROM {$table}");
                        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                    } catch (Exception $e) {
                        $count = 'Error: ' . $e->getMessage();
                    }
                }
                
                $results['data']['specific_tables'][$table] = [
                    'exists' => $exists,
                    'records' => $count,
                    'status' => $exists ? 'ok' : 'warning'
                ];
            }
        }
        
        $results['success'] = true;
        
    } catch (PDOException $e) {
        $results['data']['connection'] = [
            'status' => 'error',
            'message' => 'Error de conexión: ' . $e->getMessage()
        ];
    }
    
    return $results;
}

function checkModules() {
    $results = [
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => []
    ];
    
    // Definir módulos del sistema
    $modules = [
        'instrumentos' => [
            'name' => 'Gestión de Instrumentos',
            'frontend' => '/apps/internal/instrumentos/list_gages.html',
            'backend' => '/backend/instrumentos/gages/list_gages.php',
            'manager' => '/backend/instrumentos/InstrumentosManager.php'
        ],
        'usuarios' => [
            'name' => 'Gestión de Usuarios',
            'frontend' => '/apps/internal/usuarios/index.html'
        ],
        'calibraciones' => [
            'name' => 'Sistema de Calibraciones',
            'frontend' => '/apps/internal/calibraciones/index.html'
        ],
        'proveedores' => [
            'name' => 'Gestión de Proveedores',
            'frontend' => '/apps/internal/proveedores/index.html'
        ],
        'reportes' => [
            'name' => 'Sistema de Reportes',
            'frontend' => '/apps/internal/reportes/index.html'
        ],
        'auditoria' => [
            'name' => 'Sistema de Auditoría',
            'frontend' => '/apps/internal/auditoria/index.html'
        ],
        'calidad' => [
            'name' => 'Control de Calidad',
            'frontend' => '/apps/internal/calidad/index.html'
        ],
        'planeacion' => [
            'name' => 'Sistema de Planeación',
            'frontend' => '/apps/internal/planeacion/index.html'
        ]
    ];
    
    $basePath = 'C:/xampp/htdocs/SBL_SISTEMA_INTERNO/public';
    
    foreach ($modules as $key => $module) {
        $moduleStatus = [
            'name' => $module['name'],
            'key' => $key,
            'files' => [],
            'web_access' => [],
            'overall_status' => 'unknown'
        ];
        
        // Verificar archivos
        foreach (['frontend', 'backend', 'manager'] as $fileType) {
            if (isset($module[$fileType])) {
                $filePath = $basePath . $module[$fileType];
                $exists = file_exists($filePath);
                $moduleStatus['files'][$fileType] = [
                    'path' => $module[$fileType],
                    'exists' => $exists,
                    'status' => $exists ? 'ok' : 'error'
                ];
            }
        }
        
        // Verificar acceso web (para frontend)
        if (isset($module['frontend'])) {
            $url = 'http://localhost' . $module['frontend'];
            $accessible = testWebAccess($url);
            $moduleStatus['web_access']['frontend'] = [
                'url' => $url,
                'accessible' => $accessible,
                'status' => $accessible ? 'ok' : 'error'
            ];
        }
        
        // Verificar API (para backend)
        if (isset($module['backend'])) {
            $url = 'http://localhost' . $module['backend'];
            $accessible = testWebAccess($url);
            $moduleStatus['web_access']['backend'] = [
                'url' => $url,
                'accessible' => $accessible,
                'status' => $accessible ? 'ok' : 'error'
            ];
        }
        
        // Determinar estado general del módulo
        $allFiles = array_column($moduleStatus['files'], 'exists');
        $allWeb = array_column($moduleStatus['web_access'], 'accessible');
        
        $filesOk = empty($allFiles) || !in_array(false, $allFiles);
        $webOk = empty($allWeb) || !in_array(false, $allWeb);
        
        if ($filesOk && $webOk) {
            $moduleStatus['overall_status'] = 'ok';
        } elseif ($filesOk || $webOk) {
            $moduleStatus['overall_status'] = 'warning';
        } else {
            $moduleStatus['overall_status'] = 'error';
        }
        
        $results['data'][$key] = $moduleStatus;
    }
    
    return $results;
}

function checkFiles() {
    $results = [
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => []
    ];
    
    $basePath = 'C:/xampp/htdocs/SBL_SISTEMA_INTERNO';
    $publicPath = $basePath . '/public';
    
    // Archivos críticos del sistema
    $criticalFiles = [
        'Core' => [
            '/app/Core/db.php',
            '/app/Core/auth.php',
            '/app/Core/permissions.php'
        ],
        'Config' => [
            '/backend/instrumentos/config_simple.php'
        ],
        'UI Components' => [
            '/public/apps/internal/sidebar.html',
            '/public/apps/internal/topbar.html'
        ],
        'Assets' => [
            '/public/assets/css/styles.css',
            '/public/assets/js/scripts.js'
        ]
    ];
    
    foreach ($criticalFiles as $category => $files) {
        $results['data'][$category] = [];
        
        foreach ($files as $file) {
            $fullPath = $basePath . $file;
            $exists = file_exists($fullPath);
            $size = $exists ? filesize($fullPath) : 0;
            $readable = $exists ? is_readable($fullPath) : false;
            
            $results['data'][$category][basename($file)] = [
                'path' => $file,
                'exists' => $exists,
                'size' => formatBytes($size),
                'readable' => $readable,
                'status' => $exists && $readable ? 'ok' : 'error'
            ];
        }
    }
    
    // Verificar directorios críticos
    $criticalDirs = [
        '/storage',
        '/storage/logs',
        '/public/apps',
        '/backend'
    ];
    
    $results['data']['Directories'] = [];
    foreach ($criticalDirs as $dir) {
        $fullPath = $basePath . $dir;
        $exists = is_dir($fullPath);
        $writable = $exists ? is_writable($fullPath) : false;
        
        $results['data']['Directories'][basename($dir)] = [
            'path' => $dir,
            'exists' => $exists,
            'writable' => $writable,
            'status' => $exists && $writable ? 'ok' : ($exists ? 'warning' : 'error')
        ];
    }
    
    return $results;
}

function generateFullReport() {
    return [
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'system_info' => [
            'php_version' => PHP_VERSION,
            'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'os' => PHP_OS,
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown'
        ],
        'infrastructure' => checkInfrastructure(),
        'database' => checkDatabase(),
        'modules' => checkModules(),
        'files' => checkFiles(),
        'summary' => generateSummary()
    ];
}

function generateSummary() {
    $infrastructure = checkInfrastructure();
    $database = checkDatabase();
    $modules = checkModules();
    $files = checkFiles();
    
    $summary = [
        'overall_status' => 'ok',
        'issues' => [],
        'recommendations' => [],
        'statistics' => [
            'modules_total' => count($modules['data']),
            'modules_ok' => 0,
            'modules_warning' => 0,
            'modules_error' => 0
        ]
    ];
    
    // Analizar módulos
    foreach ($modules['data'] as $module) {
        switch ($module['overall_status']) {
            case 'ok':
                $summary['statistics']['modules_ok']++;
                break;
            case 'warning':
                $summary['statistics']['modules_warning']++;
                break;
            case 'error':
                $summary['statistics']['modules_error']++;
                break;
        }
    }
    
    // Determinar estado general
    if ($summary['statistics']['modules_error'] > 0) {
        $summary['overall_status'] = 'error';
        $summary['issues'][] = "Hay {$summary['statistics']['modules_error']} módulos con errores críticos";
    } elseif ($summary['statistics']['modules_warning'] > 0) {
        $summary['overall_status'] = 'warning';
        $summary['issues'][] = "Hay {$summary['statistics']['modules_warning']} módulos que requieren atención";
    }
    
    // Base de datos
    if (!$database['success']) {
        $summary['overall_status'] = 'error';
        $summary['issues'][] = 'Problemas de conectividad con la base de datos';
        $summary['recommendations'][] = 'Verificar que MySQL esté ejecutándose y la base de datos esté configurada';
    }
    
    return $summary;
}

function getSystemStatus() {
    return [
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'status' => 'online',
        'message' => 'API de verificación del sistema funcionando correctamente',
        'available_endpoints' => [
            'infrastructure' => '?action=infrastructure',
            'database' => '?action=database',
            'modules' => '?action=modules',
            'files' => '?action=files',
            'full_report' => '?action=full_report'
        ]
    ];
}

function testWebAccess($url) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'user_agent' => 'SBL-System-Checker/1.0'
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    return $response !== false;
}

function formatBytes($size, $precision = 2) {
    if ($size == 0) return '0 B';
    
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $factor = floor(log($size, 1024));
    
    return round($size / pow(1024, $factor), $precision) . ' ' . $units[$factor];
}
?>