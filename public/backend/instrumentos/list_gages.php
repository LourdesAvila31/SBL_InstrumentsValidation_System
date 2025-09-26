<?php
/**
 * API Endpoint - Listar Instrumentos
 * 
 * Endpoint para obtener la lista de instrumentos con filtros opcionales
 * Soporta búsqueda, filtrado y paginación
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Incluir el manager de instrumentos
require_once __DIR__ . '/../../app/Modules/Internal/Instrumentos/InstrumentosManager.php';

try {
    // Verificar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
        exit;
    }
    
    // Crear instancia del manager
    $manager = new InstrumentosManager();
    
    // Obtener filtros de la query string
    $filters = [];
    
    if (!empty($_GET['codigo'])) {
        $filters['codigo'] = $_GET['codigo'];
    }
    
    if (!empty($_GET['descripcion'])) {
        $filters['descripcion'] = $_GET['descripcion'];
    }
    
    if (!empty($_GET['estado'])) {
        $filters['estado'] = $_GET['estado'];
    }
    
    if (!empty($_GET['ubicacion'])) {
        $filters['ubicacion'] = $_GET['ubicacion'];
    }
    
    // Obtener instrumentos
    $instrumentos = $manager->getInstrumentos($filters);
    
    // Preparar respuesta
    $response = [
        'success' => true,
        'data' => $instrumentos,
        'count' => count($instrumentos),
        'filters_applied' => $filters,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Agregar estadísticas rápidas
    $estadisticas = [
        'total' => count($instrumentos),
        'vigentes' => 0,
        'proximos_a_vencer' => 0,
        'vencidos' => 0,
        'sin_programar' => 0
    ];
    
    foreach ($instrumentos as $instrumento) {
        switch ($instrumento['estado_calibracion']) {
            case 'VIGENTE':
                $estadisticas['vigentes']++;
                break;
            case 'PROXIMO_A_VENCER':
                $estadisticas['proximos_a_vencer']++;
                break;
            case 'VENCIDO':
                $estadisticas['vencidos']++;
                break;
            case 'SIN_PROGRAMAR':
                $estadisticas['sin_programar']++;
                break;
        }
    }
    
    $response['estadisticas'] = $estadisticas;
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>