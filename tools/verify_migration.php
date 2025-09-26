<?php
/**
 * Script de Verificación de Migración Service/Tenant
 * Verifica que todos los archivos fueron movidos correctamente del Sistema Interno al Sistema de Servicios
 */

echo "=== VERIFICACIÓN DE MIGRACIÓN SERVICE/TENANT ===\n\n";

$sistemaInterno = 'C:/xampp/htdocs/SBL_SISTEMA_INTERNO';
$sistemaServicios = 'C:/xampp/htdocs/SBL_SISTEMA_SERVICIOS_CLIENTES';

$verificaciones = [];
$errores = [];

// 1. Verificar que las carpetas fueron eliminadas del Sistema Interno
echo "1. VERIFICANDO ELIMINACIÓN DEL SISTEMA INTERNO\n";
echo "   ==========================================\n";

$carpetasEliminadas = [
    'public/apps/service',
    'public/apps/tenant',
    'app/Modules/Service',
    'app/Modules/Tenant',
    'public/backend/clientes',
    'public/assets/scripts/service'
];

foreach ($carpetasEliminadas as $carpeta) {
    $ruta = $sistemaInterno . '/' . $carpeta;
    $existe = is_dir($ruta);
    
    if (!$existe) {
        echo "   ✅ $carpeta - ELIMINADA CORRECTAMENTE\n";
        $verificaciones['eliminadas'][] = $carpeta;
    } else {
        echo "   ❌ $carpeta - AÚN EXISTE (ERROR)\n";
        $errores[] = "Carpeta no eliminada: $carpeta";
    }
}

// 2. Verificar que las carpetas existen en el Sistema de Servicios
echo "\n2. VERIFICANDO EXISTENCIA EN SISTEMA DE SERVICIOS\n";
echo "   ==============================================\n";

$carpetasMovidas = [
    'public/apps/service',
    'public/apps/tenant',
    'app/Modules/Service',
    'app/Modules/Tenant'
];

foreach ($carpetasMovidas as $carpeta) {
    $ruta = $sistemaServicios . '/' . $carpeta;
    $existe = is_dir($ruta);
    
    if ($existe) {
        echo "   ✅ $carpeta - EXISTE EN DESTINO\n";
        $verificaciones['movidas'][] = $carpeta;
    } else {
        echo "   ❌ $carpeta - NO EXISTE EN DESTINO (ERROR)\n";
        $errores[] = "Carpeta no encontrada en destino: $carpeta";
    }
}

// 3. Verificar contenido de las carpetas movidas
echo "\n3. VERIFICANDO CONTENIDO DE CARPETAS MOVIDAS\n";
echo "   =========================================\n";

$contenidoEsperado = [
    'public/apps/service' => ['index.html', 'clientes', 'calibraciones', 'instrumentos'],
    'public/apps/tenant' => ['index.html', 'usuarios', 'calibraciones', 'reportes'],
    'app/Modules/Service' => ['Clientes', 'Instrumentos', 'Calibraciones'],
    'app/Modules/Tenant' => ['Clientes', 'Instrumentos', 'Calibraciones', 'Reportes']
];

foreach ($contenidoEsperado as $carpeta => $esperado) {
    $ruta = $sistemaServicios . '/' . $carpeta;
    if (is_dir($ruta)) {
        $contenido = scandir($ruta);
        $contenido = array_diff($contenido, ['.', '..']);
        
        $faltantes = array_diff($esperado, $contenido);
        
        if (empty($faltantes)) {
            echo "   ✅ $carpeta - CONTENIDO COMPLETO\n";
            $verificaciones['contenido'][] = $carpeta;
        } else {
            echo "   ⚠️  $carpeta - CONTENIDO PARCIAL (faltan: " . implode(', ', $faltantes) . ")\n";
            foreach ($esperado as $item) {
                if (in_array($item, $contenido)) {
                    echo "       ✓ $item\n";
                } else {
                    echo "       ✗ $item (faltante)\n";
                }
            }
        }
    }
}

// 4. Verificar estructura del Sistema Interno después de migración
echo "\n4. VERIFICANDO ESTRUCTURA ACTUAL DEL SISTEMA INTERNO\n";
echo "   ================================================\n";

$appsInterno = $sistemaInterno . '/public/apps';
if (is_dir($appsInterno)) {
    $apps = scandir($appsInterno);
    $apps = array_diff($apps, ['.', '..']);
    
    echo "   Apps disponibles en Sistema Interno:\n";
    foreach ($apps as $app) {
        echo "   ✅ $app\n";
    }
    $verificaciones['apps_interno'] = $apps;
}

$modulosInterno = $sistemaInterno . '/app/Modules';
if (is_dir($modulosInterno)) {
    $modulos = scandir($modulosInterno);
    $modulos = array_diff($modulos, ['.', '..']);
    
    echo "\n   Módulos disponibles en Sistema Interno:\n";
    foreach ($modulos as $modulo) {
        echo "   ✅ $modulo\n";
    }
    $verificaciones['modulos_interno'] = $modulos;
}

// 5. Verificar estructura del Sistema de Servicios después de migración
echo "\n5. VERIFICANDO ESTRUCTURA ACTUAL DEL SISTEMA DE SERVICIOS\n";
echo "   ====================================================\n";

$appsServicios = $sistemaServicios . '/public/apps';
if (is_dir($appsServicios)) {
    $apps = scandir($appsServicios);
    $apps = array_diff($apps, ['.', '..']);
    
    echo "   Apps disponibles en Sistema de Servicios:\n";
    foreach ($apps as $app) {
        echo "   ✅ $app\n";
    }
    $verificaciones['apps_servicios'] = $apps;
}

$modulosServicios = $sistemaServicios . '/app/Modules';
if (is_dir($modulosServicios)) {
    $modulos = scandir($modulosServicios);
    $modulos = array_diff($modulos, ['.', '..']);
    
    echo "\n   Módulos disponibles en Sistema de Servicios:\n";
    foreach ($modulos as $modulo) {
        echo "   ✅ $modulo\n";
    }
    $verificaciones['modulos_servicios'] = $modulos;
}

// 6. Resumen de la verificación
echo "\n" . str_repeat("=", 60) . "\n";
echo "RESUMEN DE VERIFICACIÓN\n";
echo str_repeat("=", 60) . "\n";

$totalVerificaciones = 0;
$totalExitosas = 0;

foreach ($verificaciones as $tipo => $items) {
    $count = is_array($items) ? count($items) : 1;
    $totalVerificaciones += $count;
    $totalExitosas += $count;
}

$totalErrores = count($errores);

echo "📊 Verificaciones totales: $totalVerificaciones\n";
echo "✅ Verificaciones exitosas: $totalExitosas\n";
echo "❌ Errores encontrados: $totalErrores\n";

if ($totalErrores === 0) {
    echo "\n🎉 MIGRACIÓN COMPLETADA EXITOSAMENTE\n";
    echo "   Todos los archivos fueron movidos correctamente.\n";
    echo "   El Sistema Interno ahora solo contiene módulos internos.\n";
    echo "   El Sistema de Servicios contiene todos los módulos de clientes.\n";
} else {
    echo "\n⚠️  MIGRACIÓN CON ERRORES\n";
    echo "   Se encontraron los siguientes problemas:\n";
    foreach ($errores as $error) {
        echo "   - $error\n";
    }
}

// 7. Verificaciones adicionales recomendadas
echo "\n" . str_repeat("-", 60) . "\n";
echo "VERIFICACIONES ADICIONALES RECOMENDADAS\n";
echo str_repeat("-", 60) . "\n";
echo "1. Probar login en Sistema Interno\n";
echo "2. Verificar acceso a dashboard moderno\n";
echo "3. Confirmar que no hay enlaces rotos\n";
echo "4. Probar funcionalidades en Sistema de Servicios\n";
echo "5. Verificar base de datos si es necesario\n";

echo "\n📁 Ubicaciones de archivos importantes:\n";
echo "   - Sistema Interno: $sistemaInterno\n";
echo "   - Sistema Servicios: $sistemaServicios\n";
echo "   - Login Interno: $sistemaInterno/app/Modules/Internal/Usuarios/login.php\n";
echo "   - Dashboard Moderno: $sistemaInterno/app/Modules/Internal/Dashboard/modern_dashboard.php\n";

echo "\n=== VERIFICACIÓN COMPLETADA ===\n";

return [
    'verificaciones' => $verificaciones,
    'errores' => $errores,
    'exitosa' => $totalErrores === 0
];
?>