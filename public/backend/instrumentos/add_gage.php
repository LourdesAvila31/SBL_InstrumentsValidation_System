<?php
/**
 * API Endpoint - Crear Instrumento
 * 
 * Endpoint para crear un nuevo instrumento en el sistema
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Incluir el manager de instrumentos
require_once __DIR__ . '/../../app/Modules/Internal/Instrumentos/InstrumentosManager.php';

try {
    // Verificar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido. Use POST']);
        exit;
    }
    
    // Obtener datos del POST
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Si no hay JSON, intentar obtener de $_POST
    if (json_last_error() !== JSON_ERROR_NONE) {
        $data = $_POST;
    }
    
    if (empty($data)) {
        http_response_code(400);
        echo json_encode(['error' => 'No se recibieron datos']);
        exit;
    }
    
    // Crear instancia del manager
    $manager = new InstrumentosManager();
    
    // Crear instrumento
    $instrumentoId = $manager->crearInstrumento($data);
    
    // Obtener el instrumento creado para devolverlo
    $instrumentoCreado = $manager->getInstrumento($instrumentoId);
    
    // Respuesta exitosa
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Instrumento creado exitosamente',
        'data' => $instrumentoCreado,
        'id' => $instrumentoId,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // Determinar código de error HTTP apropiado
    $httpCode = 500;
    if (strpos($e->getMessage(), 'permisos') !== false) {
        $httpCode = 403;
    } elseif (strpos($e->getMessage(), 'validación') !== false || 
              strpos($e->getMessage(), 'Ya existe') !== false) {
        $httpCode = 400;
    }
    
    http_response_code($httpCode);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>