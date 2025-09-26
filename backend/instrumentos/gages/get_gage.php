<?php
/**
 * API Endpoint: Obtener Instrumento Individual
 * 
 * GET /backend/instrumentos/gages/get_gage.php?id=123
 * 
 * Devuelve los datos completos de un instrumento específico
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
    // Verificar que se proporcionó un ID
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('ID de instrumento requerido');
    }
    
    $id = intval($_GET['id']);
    
    if ($id <= 0) {
        throw new Exception('ID de instrumento inválido');
    }
    
    // Incluir el manager de instrumentos
    require_once __DIR__ . '/../InstrumentosManager.php';
    
    // Crear instancia del manager
    $manager = new InstrumentosManager();
    
    // Obtener instrumento
    $instrumento = $manager->getInstrumento($id);
    
    if (!$instrumento) {
        http_response_code(404);
        throw new Exception('Instrumento no encontrado');
    }
    
    // Preparar respuesta
    $response = [
        'success' => true,
        'message' => 'Instrumento obtenido exitosamente',
        'data' => $instrumento,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Devolver respuesta JSON
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Determinar código de respuesta HTTP
    $http_code = http_response_code();
    if ($http_code === false || $http_code === 200) {
        http_response_code(500);
    }
    
    $error_response = [
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($error_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    // Registrar error en log
    error_log("Error en get_gage.php: " . $e->getMessage());
}
?>