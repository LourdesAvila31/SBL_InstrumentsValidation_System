<?php
/**
 * Script de verificación de rutas del Dashboard Moderno
 * Verifica que todas las rutas configuradas en el dashboard existan
 */

$basePath = __DIR__ . '/../../..';
$publicPath = $basePath . '/public';

// Configuración de rutas a verificar
$routesToVerify = [
    'dashboard' => 'apps/internal/index.html',
    'instrumentos' => 'apps/internal/instrumentos/list_gages.html',
    'planeacion' => 'apps/internal/planeacion/list_planning.html',
    'calibraciones' => 'apps/internal/calibraciones/list_calibrations.html',
    'reportes' => 'apps/internal/reportes/reports.html',
    'usuarios' => 'apps/internal/usuarios/list_users.html',
    'api_tokens' => 'apps/internal/configuracion/api_tokens.html',
    'calidad' => 'apps/internal/calidad/index.html',
    'gamp5' => 'gamp5_dashboard.html',
    'developer' => 'apps/internal/developer/selector.html',
    'admin' => 'admin_dashboard.html',
    'ayuda' => 'apps/internal/ayuda/help_center.html'
];

$results = [];
$errors = [];

echo "=== VERIFICACIÓN DE RUTAS DEL DASHBOARD MODERNO ===\n\n";

foreach ($routesToVerify as $section => $route) {
    $fullPath = $publicPath . '/' . $route;
    $exists = file_exists($fullPath);
    
    $results[$section] = [
        'route' => $route,
        'full_path' => $fullPath,
        'exists' => $exists
    ];
    
    if ($exists) {
        echo "✅ {$section}: {$route}\n";
    } else {
        echo "❌ {$section}: {$route} - ARCHIVO NO ENCONTRADO\n";
        $errors[] = $section;
    }
}

echo "\n=== RESUMEN ===\n";
echo "Total de rutas verificadas: " . count($routesToVerify) . "\n";
echo "Rutas existentes: " . (count($routesToVerify) - count($errors)) . "\n";
echo "Rutas faltantes: " . count($errors) . "\n";

if (!empty($errors)) {
    echo "\n=== RUTAS FALTANTES ===\n";
    foreach ($errors as $section) {
        echo "- {$section}: {$routesToVerify[$section]}\n";
    }
    
    echo "\n=== RECOMENDACIONES ===\n";
    echo "1. Verificar que los archivos existan en las rutas especificadas\n";
    echo "2. Actualizar las rutas en modern_dashboard.php si han cambiado\n";
    echo "3. Crear los archivos faltantes si es necesario\n";
}

// Verificar también rutas especiales
echo "\n=== VERIFICACIÓN DE RUTAS ESPECIALES ===\n";

$specialRoutes = [
    'service_portal' => 'index.php',
    'logout' => 'backend/usuarios/logout.php'
];

foreach ($specialRoutes as $name => $route) {
    $fullPath = $publicPath . '/' . $route;
    $exists = file_exists($fullPath);
    
    if ($exists) {
        echo "✅ {$name}: {$route}\n";
    } else {
        echo "❌ {$name}: {$route} - ARCHIVO NO ENCONTRADO\n";
    }
}

echo "\n=== VERIFICACIÓN COMPLETADA ===\n";

// Retornar resultados para uso programático
return [
    'results' => $results,
    'errors' => $errors,
    'total' => count($routesToVerify),
    'missing' => count($errors)
];
?>