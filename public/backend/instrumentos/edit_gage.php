<?php
/**
 * API Endpoint - Actualizar Instrumento
 * 
 * Endpoint para actualizar un instrumento existente
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Incluir el manager de instrumentos
require_once __DIR__ . '/../../app/Modules/Internal/Instrumentos/InstrumentosManager.php';

try {
    // Verificar método HTTP
    if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'POST'])) {
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido. Use PUT o POST']);
        exit;
    }
    
    // Obtener ID del instrumento
    $id = $_GET['id'] ?? $_POST['id'] ?? null;
    
    if (!$id || !is_numeric($id)) {
        http_response_code(400);
        echo json_encode(['error' => 'ID de instrumento requerido y debe ser numérico']);
        exit;
    }
    
    // Obtener datos
    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['error' => 'JSON inválido']);
            exit;
        }
    } else {
        $data = $_POST;
    }
    
    if (empty($data)) {
        http_response_code(400);
        echo json_encode(['error' => 'No se recibieron datos para actualizar']);
        exit;
    }
    
    // Crear instancia del manager
    $manager = new InstrumentosManager();
    
    // Actualizar instrumento
    $resultado = $manager->actualizarInstrumento($id, $data);
    
    if (!$resultado) {
        http_response_code(500);
        echo json_encode(['error' => 'No se pudo actualizar el instrumento']);
        exit;
    }
    
    // Obtener el instrumento actualizado
    $instrumentoActualizado = $manager->getInstrumento($id);
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Instrumento actualizado exitosamente',
        'data' => $instrumentoActualizado,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // Determinar código de error HTTP apropiado
    $httpCode = 500;
    if (strpos($e->getMessage(), 'permisos') !== false) {
        $httpCode = 403;
    } elseif (strpos($e->getMessage(), 'validación') !== false || 
              strpos($e->getMessage(), 'Ya existe') !== false ||
              strpos($e->getMessage(), 'no encontrado') !== false) {
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