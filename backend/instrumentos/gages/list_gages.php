<?php
/**
 * API Endpoint: Listar Instrumentos
 * 
 * GET /backend/instrumentos/gages/list_gages.php
 * 
 * Devuelve la lista completa de instrumentos registrados en formato JSON
 * con opciones de filtrado y ordenamiento
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Incluir el manager de instrumentos
    require_once __DIR__ . '/../InstrumentosManager.php';
    
    // Crear instancia del manager
    $manager = new InstrumentosManager();
    
    // Obtener parámetros de filtrado de la URL
    $filters = [];
    
    if (isset($_GET['codigo']) && !empty($_GET['codigo'])) {
        $filters['codigo'] = $_GET['codigo'];
    }
    
    if (isset($_GET['descripcion']) && !empty($_GET['descripcion'])) {
        $filters['descripcion'] = $_GET['descripcion'];
    }
    
    if (isset($_GET['estado']) && !empty($_GET['estado'])) {
        $filters['estado'] = $_GET['estado'];
    }
    
    if (isset($_GET['ubicacion']) && !empty($_GET['ubicacion'])) {
        $filters['ubicacion'] = $_GET['ubicacion'];
    }
    
    // Obtener lista de instrumentos
    $instrumentos = $manager->getInstrumentos($filters);
    
    // Calcular estadísticas
    $estadisticas = [
        'total' => count($instrumentos),
        'activos' => 0,
        'mantenimiento' => 0,
        'calibracion' => 0,
        'vencidos' => 0,
        'proximos_a_vencer' => 0,
        'vigentes' => 0
    ];
    
    foreach ($instrumentos as $instrumento) {
        // Contar por estado
        switch ($instrumento['estado']) {
            case 'ACTIVO':
                $estadisticas['activos']++;
                break;
            case 'MANTENIMIENTO':
                $estadisticas['mantenimiento']++;
                break;
            case 'CALIBRACION':
                $estadisticas['calibracion']++;
                break;
        }
        
        // Contar por estado de calibración
        switch ($instrumento['estado_calibracion']) {
            case 'VENCIDO':
                $estadisticas['vencidos']++;
                break;
            case 'PROXIMO_A_VENCER':
                $estadisticas['proximos_a_vencer']++;
                break;
            case 'VIGENTE':
                $estadisticas['vigentes']++;
                break;
        }
    }
    
    // Preparar respuesta
    $response = [
        'success' => true,
        'message' => 'Lista de instrumentos obtenida exitosamente',
        'data' => $instrumentos,
        'estadisticas' => $estadisticas,
        'filtros_aplicados' => $filters,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Devolver respuesta JSON
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Manejar errores
    http_response_code(500);
    
    $error_response = [
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($error_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    // Registrar error en log
    error_log("Error en list_gages.php: " . $e->getMessage());
}
?>