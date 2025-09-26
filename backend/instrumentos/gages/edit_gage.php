<?php
/**
 * API Endpoint: Editar Instrumento
 * 
 * POST /backend/instrumentos/gages/edit_gage.php
 * 
 * Actualiza un instrumento existente
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Permitir métodos POST y PUT
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'])) {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Método no permitido. Use POST o PUT.'
    ]);
    exit;
}

try {
    // Incluir el manager de instrumentos
    require_once __DIR__ . '/../InstrumentosManager.php';
    
    // Obtener datos del cuerpo de la petición
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Si no hay datos JSON, intentar obtener de $_POST
    if (!$data) {
        $data = $_POST;
    }
    
    if (empty($data)) {
        throw new Exception('No se proporcionaron datos para actualizar el instrumento');
    }
    
    // Verificar que se proporcionó un ID
    if (!isset($data['id']) || empty($data['id'])) {
        throw new Exception('ID de instrumento requerido');
    }
    
    $id = intval($data['id']);
    
    if ($id <= 0) {
        throw new Exception('ID de instrumento inválido');
    }
    
    // Crear instancia del manager
    $manager = new InstrumentosManager();
    
    // Actualizar instrumento
    $resultado = $manager->actualizarInstrumento($id, $data);
    
    if (!$resultado) {
        throw new Exception('No se pudo actualizar el instrumento');
    }
    
    // Obtener el instrumento actualizado para devolverlo completo
    $instrumentoActualizado = $manager->getInstrumento($id);
    
    // Preparar respuesta
    $response = [
        'success' => true,
        'message' => 'Instrumento actualizado exitosamente',
        'data' => $instrumentoActualizado,
        'id' => $id,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Determinar código de respuesta HTTP apropiado
    $http_code = 500;
    $message = $e->getMessage();
    
    if (strpos($message, 'no encontrado') !== false) {
        $http_code = 404; // Not Found
    } elseif (strpos($message, 'Ya existe') !== false) {
        $http_code = 409; // Conflict
    } elseif (strpos($message, 'requerido') !== false || strpos($message, 'inválido') !== false) {
        $http_code = 400; // Bad Request
    } elseif (strpos($message, 'permisos') !== false) {
        $http_code = 403; // Forbidden
    } elseif (strpos($message, 'autorizado') !== false) {
        $http_code = 401; // Unauthorized
    }
    
    http_response_code($http_code);
    
    $error_response = [
        'success' => false,
        'error' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($error_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    // Registrar error en log
    error_log("Error en edit_gage.php: " . $message);
}
?>