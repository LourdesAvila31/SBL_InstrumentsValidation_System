<?php
// Archivo de diagnóstico del sistema SBL
echo "<!DOCTYPE html>";
echo "<html lang='es'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>Diagnóstico del Sistema SBL</title>";
echo "<style>";
echo "body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }";
echo ".container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }";
echo ".status-ok { color: #28a745; }";
echo ".status-error { color: #dc3545; }";
echo ".status-warning { color: #ffc107; }";
echo ".info-box { background: #f8f9fa; padding: 15px; margin: 10px 0; border-left: 4px solid #007bff; }";
echo ".error-box { background: #f8d7da; padding: 15px; margin: 10px 0; border-left: 4px solid #dc3545; }";
echo ".success-box { background: #d4edda; padding: 15px; margin: 10px 0; border-left: 4px solid #28a745; }";
echo "table { width: 100%; border-collapse: collapse; margin: 15px 0; }";
echo "th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }";
echo "th { background-color: #f8f9fa; font-weight: bold; }";
echo ".btn { display: inline-block; padding: 8px 16px; margin: 5px; text-decoration: none; border-radius: 4px; font-weight: bold; }";
echo ".btn-primary { background: #007bff; color: white; }";
echo ".btn-success { background: #28a745; color: white; }";
echo ".btn-warning { background: #ffc107; color: black; }";
echo "</style>";
echo "</head>";
echo "<body>";

echo "<div class='container'>";
echo "<h1>🔧 Diagnóstico del Sistema SBL</h1>";
echo "<p><strong>Fecha:</strong> " . date('Y-m-d H:i:s') . "</p>";

// Información del servidor
echo "<h2>📊 Información del Servidor</h2>";
echo "<div class='info-box'>";
echo "<strong>Servidor Web:</strong> " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "<strong>PHP Version:</strong> " . PHP_VERSION . "<br>";
echo "<strong>Documento Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "<strong>Script actual:</strong> " . $_SERVER['SCRIPT_FILENAME'] . "<br>";
echo "<strong>URL actual:</strong> " . (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "<br>";
echo "</div>";

// Verificar estructura de directorios
echo "<h2>📁 Estructura de Directorios</h2>";
$directories_to_check = [
    'backend/instrumentos',
    'public/apps/internal',
    'sistema-interno/public/apps/internal',
    'app/Core',
    'storage',
    'docs'
];

echo "<table>";
echo "<tr><th>Directorio</th><th>Estado</th><th>Ruta Completa</th></tr>";

foreach ($directories_to_check as $dir) {
    $full_path = __DIR__ . '/' . $dir;
    $exists = is_dir($full_path);
    $status_class = $exists ? 'status-ok' : 'status-error';
    $status_text = $exists ? '✅ Existe' : '❌ No existe';
    
    echo "<tr>";
    echo "<td>$dir</td>";
    echo "<td class='$status_class'>$status_text</td>";
    echo "<td>" . realpath($full_path) . "</td>";
    echo "</tr>";
}
echo "</table>";

// Verificar archivos clave
echo "<h2>📄 Archivos Clave del Sistema</h2>";
$files_to_check = [
    'index.php' => 'Archivo principal',
    'app/index.php' => 'Índice de aplicación',
    'public/index.php' => 'Public index',
    'sistema-interno/public/apps/internal/instrumentos/list_gages.html' => 'Lista de instrumentos',
    'backend/instrumentos/list_gages.php' => 'API de instrumentos',
    'app/Core/db_config.php' => 'Configuración de BD',
    'test.php' => 'Archivo de prueba'
];

echo "<table>";
echo "<tr><th>Archivo</th><th>Descripción</th><th>Estado</th><th>Tamaño</th></tr>";

foreach ($files_to_check as $file => $description) {
    $full_path = __DIR__ . '/' . $file;
    $exists = file_exists($full_path);
    $status_class = $exists ? 'status-ok' : 'status-error';
    $status_text = $exists ? '✅ Existe' : '❌ No existe';
    $size = $exists ? filesize($full_path) . ' bytes' : 'N/A';
    
    echo "<tr>";
    echo "<td>$file</td>";
    echo "<td>$description</td>";
    echo "<td class='$status_class'>$status_text</td>";
    echo "<td>$size</td>";
    echo "</tr>";
}
echo "</table>";

// Verificar conexión a base de datos
echo "<h2>🗄️ Conexión a Base de Datos</h2>";
try {
    // Intentar cargar configuración de BD
    $db_config_paths = [
        __DIR__ . '/app/Core/db_config.php',
        __DIR__ . '/app/Core/db.php'
    ];
    
    $db_config_found = false;
    foreach ($db_config_paths as $config_path) {
        if (file_exists($config_path)) {
            echo "<div class='success-box'>✅ Archivo de configuración encontrado: $config_path</div>";
            $db_config_found = true;
            break;
        }
    }
    
    if (!$db_config_found) {
        echo "<div class='error-box'>❌ No se encontró archivo de configuración de base de datos</div>";
    }
    
    // Intentar conexión básica a MySQL
    $connection = @new mysqli('localhost', 'root', '', 'test');
    if ($connection->connect_error) {
        echo "<div class='error-box'>❌ Error de conexión MySQL: " . $connection->connect_error . "</div>";
        echo "<div class='info-box'>💡 <strong>Solución:</strong> Asegúrate de que MySQL esté ejecutándose en XAMPP</div>";
    } else {
        echo "<div class='success-box'>✅ Conexión a MySQL exitosa</div>";
        $connection->close();
    }
    
} catch (Exception $e) {
    echo "<div class='error-box'>❌ Error al verificar base de datos: " . $e->getMessage() . "</div>";
}

// URLs recomendadas para probar
echo "<h2>🔗 URLs Recomendadas para Probar</h2>";
$base_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$project_path = '/SBL_SISTEMA_INTERNO';

$recommended_urls = [
    "$project_path/" => "Página principal del proyecto",
    "$project_path/test.php" => "Archivo de prueba básico",
    "$project_path/sistema-interno/public/apps/internal/instrumentos/list_gages.html" => "Lista de instrumentos (ruta correcta)",
    "$project_path/backend/instrumentos/list_gages.php" => "API de instrumentos",
    "$project_path/public/index.php" => "Índice público",
    "$project_path/app/index.php" => "Índice de aplicación"
];

echo "<table>";
echo "<tr><th>URL</th><th>Descripción</th><th>Acción</th></tr>";

foreach ($recommended_urls as $url => $description) {
    echo "<tr>";
    echo "<td><code>$url</code></td>";
    echo "<td>$description</td>";
    echo "<td><a href='$url' target='_blank' class='btn btn-primary'>Probar</a></td>";
    echo "</tr>";
}
echo "</table>";

// Diagnóstico de problemas comunes
echo "<h2>🔍 Diagnóstico de Problemas Comunes</h2>";

echo "<div class='info-box'>";
echo "<h4>❌ Error 404 'Not Found'</h4>";
echo "<p><strong>Problema:</strong> La URL que intentas acceder no existe o está mal formada.</p>";
echo "<p><strong>Causa probable:</strong> La ruta correcta de los archivos es diferente a la que estás usando.</p>";
echo "<p><strong>Solución:</strong></p>";
echo "<ul>";
echo "<li>En lugar de: <code>/public/apps/internal/instrumentos/list_gages.html</code></li>";
echo "<li>Usa: <code>/sistema-interno/public/apps/internal/instrumentos/list_gages.html</code></li>";
echo "<li>O accede directamente desde el índice principal</li>";
echo "</ul>";
echo "</div>";

echo "<div class='info-box'>";
echo "<h4>🔧 Verificaciones de XAMPP</h4>";
echo "<p><strong>1. Apache:</strong> Debe estar ejecutándose (luz verde en XAMPP Control Panel)</p>";
echo "<p><strong>2. MySQL:</strong> Debe estar ejecutándose para funciones de base de datos</p>";
echo "<p><strong>3. Ubicación:</strong> Los archivos deben estar en <code>C:\\xampp\\htdocs\\SBL_SISTEMA_INTERNO\\</code></p>";
echo "<p><strong>4. Permisos:</strong> Verifica que no haya restricciones de permisos en las carpetas</p>";
echo "</div>";

// Recomendaciones finales
echo "<h2>💡 Recomendaciones</h2>";
echo "<div class='success-box'>";
echo "<h4>URLs de Acceso Principales:</h4>";
echo "<ol>";
echo "<li><strong>Página principal:</strong> <a href='$project_path/' target='_blank'>$base_url$project_path/</a></li>";
echo "<li><strong>Sistema interno:</strong> <a href='$project_path/sistema-interno/public/apps/internal/instrumentos/list_gages.html' target='_blank'>Lista de Instrumentos</a></li>";
echo "<li><strong>APIs:</strong> <a href='$project_path/public/api/usuarios.php' target='_blank'>API de Usuarios</a></li>";
echo "</ol>";
echo "</div>";

echo "<div class='info-box'>";
echo "<h4>Próximos Pasos:</h4>";
echo "<ol>";
echo "<li>Verifica que XAMPP esté ejecutándose correctamente</li>";
echo "<li>Prueba las URLs recomendadas arriba</li>";
echo "<li>Si tienes problemas, revisa los logs de Apache en XAMPP</li>";
echo "<li>Una vez funcionando, podemos implementar el sistema de monitoreo automático</li>";
echo "</ol>";
echo "</div>";

echo "</div>";
echo "</body>";
echo "</html>";
?>