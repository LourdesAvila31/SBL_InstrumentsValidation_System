<?php
/**
 * Verificación Completa del Sistema SBL - Módulos Internos
 * 
 * Este script verifica la funcionalidad de todos los módulos del sistema interno
 * incluyendo conexiones, APIs, base de datos y interfaces web
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Verificación Completa - SBL Sistema Interno</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 20px; background: #f8f9fa; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { color: #2c3e50; text-align: center; margin-bottom: 30px; border-bottom: 3px solid #3498db; padding-bottom: 15px; }
        h2 { color: #34495e; border-left: 4px solid #3498db; padding-left: 15px; margin-top: 30px; }
        h3 { color: #2980b9; margin-top: 25px; }
        .module-section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .status { padding: 8px 15px; border-radius: 20px; font-weight: bold; display: inline-block; margin: 5px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .test-item { margin: 10px 0; padding: 10px; border-left: 3px solid #ddd; }
        .test-pass { border-left-color: #28a745; background: #f8fff9; }
        .test-fail { border-left-color: #dc3545; background: #fff8f8; }
        .test-skip { border-left-color: #ffc107; background: #fffdf5; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .nav-links { background: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .nav-links a { margin-right: 15px; color: #007bff; text-decoration: none; }
        .nav-links a:hover { text-decoration: underline; }
        .summary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; text-align: center; margin: 20px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 Verificación Completa - SBL Sistema Interno</h1>
        <div class='summary'>
            <h3>Estado General del Sistema</h3>
            <p>Fecha: " . date('Y-m-d H:i:s') . " | Servidor: " . $_SERVER['SERVER_NAME'] . "</p>
        </div>";

// Variables para el resumen
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;
$skippedTests = 0;

function testStatus($condition, $successMsg, $failMsg, $isSkipped = false) {
    global $totalTests, $passedTests, $failedTests, $skippedTests;
    $totalTests++;
    
    if ($isSkipped) {
        $skippedTests++;
        echo "<div class='test-item test-skip'>⚠️ <strong>OMITIDO:</strong> $failMsg</div>";
        return false;
    }
    
    if ($condition) {
        $passedTests++;
        echo "<div class='test-item test-pass'>✅ <strong>ÉXITO:</strong> $successMsg</div>";
        return true;
    } else {
        $failedTests++;
        echo "<div class='test-item test-fail'>❌ <strong>ERROR:</strong> $failMsg</div>";
        return false;
    }
}

function testConnection($url, $description, $expectedContent = null) {
    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'SBL-System-Checker/1.0'
            ]
        ]);
        
        $content = @file_get_contents($url, false, $context);
        
        if ($content === false) {
            return testStatus(false, "", "No se pudo conectar a $description ($url)");
        }
        
        if ($expectedContent && strpos($content, $expectedContent) === false) {
            return testStatus(false, "", "$description responde pero no contiene contenido esperado");
        }
        
        return testStatus(true, "$description está accesible y funcionando", "");
    } catch (Exception $e) {
        return testStatus(false, "", "$description falló: " . $e->getMessage());
    }
}

// ============================================================================
// 1. VERIFICACIÓN DE INFRAESTRUCTURA BASE
// ============================================================================
echo "<div class='module-section'>
        <h2>🏗️ Infraestructura Base</h2>";

// Verificar PHP
testStatus(version_compare(PHP_VERSION, '7.4.0', '>='), 
          "PHP versión " . PHP_VERSION . " (compatible)", 
          "PHP versión " . PHP_VERSION . " (requiere 7.4+)");

// Verificar extensiones PHP necesarias
$requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl'];
foreach ($requiredExtensions as $ext) {
    testStatus(extension_loaded($ext), 
              "Extensión PHP '$ext' está disponible", 
              "Extensión PHP '$ext' NO está disponible");
}

// Verificar MySQL
try {
    $pdo = new PDO("mysql:host=localhost", "root", "");
    testStatus(true, "Conexión a MySQL servidor exitosa", "");
    
    // Verificar base de datos
    $stmt = $pdo->query("SHOW DATABASES LIKE 'sbl_sistema_interno'");
    $dbExists = $stmt->rowCount() > 0;
    testStatus($dbExists, 
              "Base de datos 'sbl_sistema_interno' existe", 
              "Base de datos 'sbl_sistema_interno' NO existe");
    
    if ($dbExists) {
        $pdo = new PDO("mysql:host=localhost;dbname=sbl_sistema_interno", "root", "");
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        testStatus(count($tables) > 0, 
                  "Base de datos contiene " . count($tables) . " tablas", 
                  "Base de datos está vacía");
    }
    
} catch (PDOException $e) {
    testStatus(false, "", "Error de conexión MySQL: " . $e->getMessage());
}

echo "</div>";

// ============================================================================
// 2. VERIFICACIÓN DE MÓDULOS PRINCIPALES
// ============================================================================
echo "<div class='module-section'>
        <h2>📋 Módulos Principales</h2>";

// Estructura de módulos esperada
$modules = [
    'instrumentos' => [
        'name' => 'Gestión de Instrumentos',
        'backend' => '/backend/instrumentos/gages/list_gages.php',
        'frontend' => '/apps/internal/instrumentos/list_gages.html',
        'setup' => '/backend/instrumentos/setup_instrumentos_table.php'
    ],
    'usuarios' => [
        'name' => 'Gestión de Usuarios',
        'frontend' => '/apps/internal/usuarios/index.html',
        'backend' => null // Por verificar
    ],
    'calibraciones' => [
        'name' => 'Sistema de Calibraciones',
        'frontend' => '/apps/internal/calibraciones/index.html',
        'backend' => null
    ],
    'proveedores' => [
        'name' => 'Gestión de Proveedores',
        'frontend' => '/apps/internal/proveedores/index.html',
        'backend' => null
    ],
    'reportes' => [
        'name' => 'Sistema de Reportes',
        'frontend' => '/apps/internal/reportes/index.html',
        'backend' => null
    ],
    'auditoria' => [
        'name' => 'Sistema de Auditoría',
        'frontend' => '/apps/internal/auditoria/index.html',
        'backend' => null
    ],
    'calidad' => [
        'name' => 'Control de Calidad',
        'frontend' => '/apps/internal/calidad/index.html',
        'backend' => null
    ],
    'planeacion' => [
        'name' => 'Sistema de Planeación',
        'frontend' => '/apps/internal/planeacion/index.html',
        'backend' => null
    ]
];

foreach ($modules as $moduleKey => $module) {
    echo "<h3>📦 {$module['name']}</h3>";
    
    // Verificar frontend
    if ($module['frontend']) {
        $frontendPath = "C:/xampp/htdocs/SBL_SISTEMA_INTERNO/public" . $module['frontend'];
        testStatus(file_exists($frontendPath), 
                  "Frontend del módulo existe: {$module['frontend']}", 
                  "Frontend del módulo NO existe: {$module['frontend']}");
        
        // Probar acceso web al frontend
        testConnection("http://localhost" . $module['frontend'], 
                      "Frontend web de {$module['name']}");
    }
    
    // Verificar backend
    if ($module['backend']) {
        testConnection("http://localhost" . $module['backend'], 
                      "Backend API de {$module['name']}", 
                      '"success"');
    } else {
        testStatus(false, "", "Backend de {$module['name']} no implementado", true);
    }
    
    // Verificar setup específico
    if (isset($module['setup'])) {
        testConnection("http://localhost" . $module['setup'], 
                      "Script de setup de {$module['name']}");
    }
}

echo "</div>";

// ============================================================================
// 3. VERIFICACIÓN DE APIS Y ENDPOINTS
// ============================================================================
echo "<div class='module-section'>
        <h2>🌐 APIs y Endpoints</h2>";

// APIs de Instrumentos (las que sabemos que funcionan)
$apis = [
    'GET /backend/instrumentos/gages/list_gages.php' => 'Lista de instrumentos',
    'GET /backend/instrumentos/gages/get_gage.php?id=1' => 'Obtener instrumento específico',
    'GET /backend/instrumentos/setup_instrumentos_table.php' => 'Setup de instrumentos'
];

foreach ($apis as $endpoint => $description) {
    $url = "http://localhost" . str_replace('GET ', '', $endpoint);
    testConnection($url, $description);
}

// Verificar otras APIs potenciales
$potentialApis = [
    '/backend/usuarios/list.php',
    '/backend/calibraciones/list.php',
    '/backend/proveedores/list.php',
    '/backend/reportes/generate.php'
];

echo "<h3>🔍 APIs Potenciales</h3>";
foreach ($potentialApis as $api) {
    $fullPath = "C:/xampp/htdocs/SBL_SISTEMA_INTERNO/public" . $api;
    testStatus(file_exists($fullPath), 
              "API encontrada: $api", 
              "API no encontrada: $api", 
              !file_exists($fullPath));
}

echo "</div>";

// ============================================================================
// 4. VERIFICACIÓN DE COMPONENTES COMPARTIDOS
// ============================================================================
echo "<div class='module-section'>
        <h2>🔧 Componentes Compartidos</h2>";

// Verificar archivos de componentes compartidos
$sharedComponents = [
    '/apps/internal/sidebar.html' => 'Menú lateral compartido',
    '/apps/internal/topbar.html' => 'Barra superior compartida',
    '/apps/internal/index.html' => 'Página principal interna',
    '/assets/css/styles.css' => 'Estilos principales',
    '/assets/js/scripts.js' => 'Scripts principales'
];

foreach ($sharedComponents as $component => $description) {
    $fullPath = "C:/xampp/htdocs/SBL_SISTEMA_INTERNO/public" . $component;
    testStatus(file_exists($fullPath), 
              "$description existe", 
              "$description NO existe");
    
    if (file_exists($fullPath) && (strpos($component, '.html') || strpos($component, '.css') || strpos($component, '.js'))) {
        $url = "http://localhost" . $component;
        testConnection($url, "Acceso web a $description");
    }
}

echo "</div>";

// ============================================================================
// 5. VERIFICACIÓN DE CONFIGURACIÓN Y SEGURIDAD
// ============================================================================
echo "<div class='module-section'>
        <h2>🔒 Configuración y Seguridad</h2>";

// Verificar archivos de configuración
$configFiles = [
    'app/Core/db.php' => 'Configuración de base de datos',
    'app/Core/auth.php' => 'Sistema de autenticación',
    'app/Core/permissions.php' => 'Sistema de permisos',
    'backend/instrumentos/config_simple.php' => 'Configuración simple instrumentos'
];

foreach ($configFiles as $file => $description) {
    $fullPath = "C:/xampp/htdocs/SBL_SISTEMA_INTERNO/" . $file;
    testStatus(file_exists($fullPath), 
              "$description existe", 
              "$description NO existe");
}

// Verificar permisos de directorios críticos
$criticalDirs = [
    'storage' => 'Directorio de almacenamiento',
    'storage/logs' => 'Directorio de logs',
    'storage/uploads' => 'Directorio de uploads'
];

foreach ($criticalDirs as $dir => $description) {
    $fullPath = "C:/xampp/htdocs/SBL_SISTEMA_INTERNO/" . $dir;
    $exists = is_dir($fullPath);
    testStatus($exists, 
              "$description existe", 
              "$description NO existe");
    
    if ($exists) {
        $writable = is_writable($fullPath);
        testStatus($writable, 
                  "$description es escribible", 
                  "$description NO es escribible");
    }
}

echo "</div>";

// ============================================================================
// 6. VERIFICACIÓN DE FUNCIONALIDADES AVANZADAS
// ============================================================================
echo "<div class='module-section'>
        <h2>⚡ Funcionalidades Avanzadas</h2>";

// Verificar scripts de gestión del sistema
$systemScripts = [
    'setup_gamp5_system.php' => 'Setup GAMP5',
    'verify_gamp5_system.php' => 'Verificación GAMP5', 
    'final_gamp5_verification.php' => 'Verificación final GAMP5',
    'install_change_management_system.php' => 'Sistema de gestión de cambios',
    'install_system_retirement.php' => 'Sistema de retiro'
];

foreach ($systemScripts as $script => $description) {
    $fullPath = "C:/xampp/htdocs/SBL_SISTEMA_INTERNO/" . $script;
    testStatus(file_exists($fullPath), 
              "$description existe", 
              "$description NO existe");
              
    if (file_exists($fullPath)) {
        testConnection("http://localhost/SBL_SISTEMA_INTERNO/" . $script, 
                      "Acceso web a $description");
    }
}

echo "</div>";

// ============================================================================
// RESUMEN FINAL
// ============================================================================
$successRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 1) : 0;

echo "<div class='summary'>
        <h2>📊 Resumen de Verificación</h2>
        <div class='grid'>
            <div class='card'>
                <h3>✅ Pruebas Exitosas</h3>
                <h2 style='color: #28a745; margin: 0;'>$passedTests</h2>
            </div>
            <div class='card'>
                <h3>❌ Pruebas Fallidas</h3>
                <h2 style='color: #dc3545; margin: 0;'>$failedTests</h2>
            </div>
            <div class='card'>
                <h3>⚠️ Pruebas Omitidas</h3>
                <h2 style='color: #ffc107; margin: 0;'>$skippedTests</h2>
            </div>
            <div class='card'>
                <h3>📈 Tasa de Éxito</h3>
                <h2 style='color: " . ($successRate >= 80 ? '#28a745' : ($successRate >= 60 ? '#ffc107' : '#dc3545')) . "; margin: 0;'>$successRate%</h2>
            </div>
        </div>
        
        <div style='margin-top: 20px;'>
            <h3>Estado General: ";

if ($successRate >= 90) {
    echo "<span class='status success'>🎉 EXCELENTE - Sistema completamente funcional</span>";
} elseif ($successRate >= 75) {
    echo "<span class='status warning'>⚠️ BUENO - Sistema funcional con mejoras menores</span>";
} elseif ($successRate >= 50) {
    echo "<span class='status warning'>🔧 REGULAR - Sistema requiere correcciones</span>";
} else {
    echo "<span class='status error'>🚨 CRÍTICO - Sistema requiere intervención inmediata</span>";
}

echo "</h3>
        </div>
      </div>";

// Enlaces de navegación
echo "<div class='nav-links'>
        <h3>🔗 Enlaces Útiles del Sistema</h3>
        <a href='/backend/instrumentos/setup_instrumentos_table.php' target='_blank'>Setup Instrumentos</a>
        <a href='/backend/instrumentos/gages/list_gages.php' target='_blank'>API Instrumentos</a>
        <a href='/apps/internal/instrumentos/list_gages.html' target='_blank'>Interfaz Instrumentos</a>
        <a href='/apps/internal/index.html' target='_blank'>Portal Interno</a>
        <a href='/SBL_SISTEMA_INTERNO/test.php' target='_blank'>Test Diagnóstico</a>
      </div>";

echo "    </div>
    </body>
    </html>";
?>