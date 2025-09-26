<?php
/**
 * API Endpoint - Eliminar Instrumento
 * 
 * Endpoint para eliminar (soft delete) un instrumento del sistema
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Incluir el manager de instrumentos
require_once __DIR__ . '/../../app/Modules/Internal/Instrumentos/InstrumentosManager.php';

try {
    // Verificar método HTTP
    if (!in_array($_SERVER['REQUEST_METHOD'], ['DELETE', 'POST'])) {
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido. Use DELETE o POST']);
        exit;
    }
    
    // Obtener ID del instrumento
    $id = $_GET['id'] ?? $_POST['id'] ?? null;
    
    // Si es POST, también verificar si viene en el body JSON
    if (!$id && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($data['id'])) {
            $id = $data['id'];
        }
    }
    
    if (!$id || !is_numeric($id)) {
        http_response_code(400);
        echo json_encode(['error' => 'ID de instrumento requerido y debe ser numérico']);
        exit;
    }
    
    // Crear instancia del manager
    $manager = new InstrumentosManager();
    
    // Obtener datos del instrumento antes de eliminarlo (para auditoría)
    $instrumentoAEliminar = $manager->getInstrumento($id);
    
    if (!$instrumentoAEliminar) {
        http_response_code(404);
        echo json_encode(['error' => 'Instrumento no encontrado']);
        exit;
    }
    
    // Eliminar instrumento
    $resultado = $manager->eliminarInstrumento($id);
    
    if (!$resultado) {
        http_response_code(500);
        echo json_encode(['error' => 'No se pudo eliminar el instrumento']);
        exit;
    }
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Instrumento eliminado exitosamente',
        'data' => [
            'id' => $id,
            'codigo_identificacion' => $instrumentoAEliminar['codigo_identificacion'],
            'descripcion' => $instrumentoAEliminar['descripcion']
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // Determinar código de error HTTP apropiado
    $httpCode = 500;
    if (strpos($e->getMessage(), 'permisos') !== false) {
        $httpCode = 403;
    } elseif (strpos($e->getMessage(), 'no encontrado') !== false) {
        $httpCode = 404;
    }
    
    http_response_code($httpCode);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>