<?php
/**
 * 🔍 VERIFICACIÓN DE ESTADO - SBL Sistema Interno
 * 
 * Este script verifica el estado actual del sistema después de la migración
 * y muestra qué componentes existen y cuáles necesitan ser implementados.
 */

echo "🔍 VERIFICACIÓN DE ESTADO - SBL Sistema Interno\n";
echo "=================================================\n\n";

// Base paths
$basePath = __DIR__ . '/..';
$appPath = $basePath . '/app';
$publicPath = $basePath . '/public';

// Secciones del dashboard moderno
$dashboardSections = [
    'instrumentos' => [
        'name' => 'Instrumentos',
        'backend_path' => '/backend/instrumentos/',
        'module_path' => '/app/Modules/Internal/Instrumentos/',
        'frontend_path' => '/apps/internal/instrumentos/',
        'priority' => 'ALTA'
    ],
    'planeacion' => [
        'name' => 'Planeación',
        'backend_path' => '/backend/planeacion/',
        'module_path' => '/app/Modules/Internal/Planeacion/',
        'frontend_path' => '/apps/internal/planeacion/',
        'priority' => 'ALTA'
    ],
    'calibraciones' => [
        'name' => 'Calibraciones',
        'backend_path' => '/backend/calibraciones/',
        'module_path' => '/app/Modules/Internal/Calibraciones/',
        'frontend_path' => '/apps/internal/calibraciones/',
        'priority' => 'ALTA'
    ],
    'reportes' => [
        'name' => 'Reportes',
        'backend_path' => '/backend/reportes/',
        'module_path' => '/app/Modules/Internal/Reportes/',
        'frontend_path' => '/apps/internal/reportes/',
        'priority' => 'MEDIA'
    ],
    'usuarios' => [
        'name' => 'Usuarios',
        'backend_path' => '/backend/usuarios/',
        'module_path' => '/app/Modules/Internal/Usuarios/',
        'frontend_path' => '/apps/internal/usuarios/',
        'priority' => 'ALTA'
    ],
    'configuracion' => [
        'name' => 'Configuración',
        'backend_path' => '/backend/configuracion/',
        'module_path' => '/app/Modules/Internal/Configuracion/',
        'frontend_path' => '/apps/internal/administracion/',
        'priority' => 'MEDIA'
    ],
    'calidad' => [
        'name' => 'Calidad',
        'backend_path' => '/backend/calidad/',
        'module_path' => '/app/Modules/Internal/Calidad/',
        'frontend_path' => '/apps/internal/calidad/',
        'priority' => 'MEDIA'
    ],
    'ayuda' => [
        'name' => 'Centro de Ayuda',
        'backend_path' => '/backend/ayuda/',
        'module_path' => '/app/Modules/Internal/Ayuda/',
        'frontend_path' => '/apps/internal/ayuda/',
        'priority' => 'BAJA'
    ]
];

// Verificar estado actual
$results = [];
$totalSections = count($dashboardSections);
$completeSections = 0;
$incompleteSections = 0;

echo "📊 ANÁLISIS POR SECCIÓN\n";
echo "------------------------\n\n";

foreach ($dashboardSections as $key => $section) {
    echo "🔹 {$section['name']} (Prioridad: {$section['priority']})\n";
    
    // Verificar frontend
    $frontendPath = $basePath . $section['frontend_path'];
    $frontendExists = is_dir($frontendPath);
    echo "   Frontend: " . ($frontendExists ? "✅ EXISTE" : "❌ FALTA") . " - {$section['frontend_path']}\n";
    
    // Verificar backend
    $backendPath = $basePath . $section['backend_path'];
    $backendExists = is_dir($backendPath);
    echo "   Backend:  " . ($backendExists ? "✅ EXISTE" : "❌ FALTA") . " - {$section['backend_path']}\n";
    
    // Verificar módulo PHP
    $modulePath = $basePath . $section['module_path'];
    $moduleExists = is_dir($modulePath);
    echo "   Módulo:   " . ($moduleExists ? "✅ EXISTE" : "❌ FALTA") . " - {$section['module_path']}\n";
    
    // Contar archivos si existe
    $fileCount = 0;
    if ($backendExists) {
        $files = glob($backendPath . '/*.php');
        $fileCount = count($files);
        echo "   Archivos: {$fileCount} archivos PHP\n";
    }
    
    if ($moduleExists) {
        $moduleFiles = glob($modulePath . '/*.php');
        $moduleFileCount = count($moduleFiles);
        echo "   Módulo:   {$moduleFileCount} archivos PHP\n";
    }
    
    // Determinar estado
    $isComplete = $frontendExists && $backendExists && $moduleExists && $fileCount > 0;
    if ($isComplete) {
        echo "   Estado:   ✅ COMPLETO\n";
        $completeSections++;
    } else {
        echo "   Estado:   🔴 INCOMPLETO\n";
        $incompleteSections++;
    }
    
    echo "\n";
    
    $results[$key] = [
        'section' => $section,
        'frontend' => $frontendExists,
        'backend' => $backendExists,
        'module' => $moduleExists,
        'file_count' => $fileCount,
        'complete' => $isComplete
    ];
}

// Resumen general
echo "📈 RESUMEN GENERAL\n";
echo "==================\n";
echo "Total de secciones: {$totalSections}\n";
echo "Secciones completas: {$completeSections} ✅\n";
echo "Secciones incompletas: {$incompleteSections} 🔴\n";
$completionPercentage = round(($completeSections / $totalSections) * 100, 1);
echo "Porcentaje de completitud: {$completionPercentage}%\n\n";

// Verificar archivos críticos del sistema
echo "🔧 ARCHIVOS CRÍTICOS DEL SISTEMA\n";
echo "==================================\n";

$criticalFiles = [
    '/app/Modules/Internal/Dashboard/modern_dashboard.php' => 'Dashboard Moderno',
    '/app/Core/auth.php' => 'Sistema de Autenticación',
    '/app/Core/permissions.php' => 'Sistema de Permisos',
    '/public/index.php' => 'Punto de entrada principal',
    '/app/Modules/Internal/Usuarios/login.php' => 'Login interno'
];

foreach ($criticalFiles as $file => $description) {
    $fullPath = $basePath . $file;
    $exists = file_exists($fullPath);
    echo ($exists ? "✅" : "❌") . " {$description}: {$file}\n";
}

echo "\n";

// Análisis por prioridad
echo "🎯 ANÁLISIS POR PRIORIDAD\n";
echo "=========================\n";

$priorities = ['ALTA' => [], 'MEDIA' => [], 'BAJA' => []];
foreach ($results as $key => $result) {
    $priority = $result['section']['priority'];
    $priorities[$priority][] = $result;
}

foreach ($priorities as $priority => $sections) {
    echo "🔸 PRIORIDAD {$priority}:\n";
    $incomplete = array_filter($sections, function($s) { return !$s['complete']; });
    $complete = array_filter($sections, function($s) { return $s['complete']; });
    
    echo "   Completas: " . count($complete) . "/" . count($sections) . "\n";
    
    if (!empty($incomplete)) {
        echo "   Pendientes:\n";
        foreach ($incomplete as $section) {
            echo "   - {$section['section']['name']}\n";
        }
    }
    echo "\n";
}

// Recomendaciones de implementación
echo "💡 RECOMENDACIONES DE IMPLEMENTACIÓN\n";
echo "====================================\n";

$highPriorityIncomplete = array_filter($results, function($r) {
    return !$r['complete'] && $r['section']['priority'] === 'ALTA';
});

if (!empty($highPriorityIncomplete)) {
    echo "🚨 IMPLEMENTAR PRIMERO (Prioridad Alta):\n";
    foreach ($highPriorityIncomplete as $key => $result) {
        $section = $result['section'];
        echo "1. {$section['name']}\n";
        echo "   - Crear: {$section['module_path']}\n";
        echo "   - Crear: {$section['backend_path']}\n";
        if (!$result['frontend']) {
            echo "   - Verificar: {$section['frontend_path']}\n";
        }
        echo "\n";
    }
}

// Comandos sugeridos
echo "🛠️ COMANDOS SUGERIDOS PARA CONTINUAR\n";
echo "====================================\n";

$firstIncomplete = array_values($highPriorityIncomplete)[0] ?? null;
if ($firstIncomplete) {
    $section = $firstIncomplete['section'];
    echo "# Comenzar con: {$section['name']}\n";
    echo "mkdir \"" . $basePath . $section['module_path'] . "\"\n";
    echo "mkdir \"" . $basePath . $section['backend_path'] . "\"\n";
    echo "\n";
    echo "# Luego implementar archivos base:\n";
    echo "# - list_{$key}.php\n";
    echo "# - add_{$key}.php\n";
    echo "# - edit_{$key}.php\n";
    echo "# - get_{$key}.php\n";
}

echo "\n✅ Verificación completada.\n";
echo "📋 Consulta docs/todo_instructions.md para el plan detallado.\n";