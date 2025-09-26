<?php
/**
 * API Endpoint - Obtener Instrumento Individual
 * 
 * Endpoint para obtener los datos de un instrumento específico por ID
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
    
    // Obtener ID del instrumento
    $id = $_GET['id'] ?? null;
    
    if (!$id || !is_numeric($id)) {
        http_response_code(400);
        echo json_encode(['error' => 'ID de instrumento requerido y debe ser numérico']);
        exit;
    }
    
    // Crear instancia del manager
    $manager = new InstrumentosManager();
    
    // Obtener instrumento
    $instrumento = $manager->getInstrumento($id);
    
    if (!$instrumento) {
        http_response_code(404);
        echo json_encode(['error' => 'Instrumento no encontrado']);
        exit;
    }
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'data' => $instrumento,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>