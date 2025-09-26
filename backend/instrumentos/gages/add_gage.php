<?php
/**
 * API Endpoint: Agregar Nuevo Instrumento
 * 
 * POST /backend/instrumentos/gages/add_gage.php
 * 
 * Crea un nuevo instrumento en el sistema
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Solo permitir método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Método no permitido. Use POST.'
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
        throw new Exception('No se proporcionaron datos para crear el instrumento');
    }
    
    // Crear instancia del manager
    $manager = new InstrumentosManager();
    
    // Crear instrumento
    $instrumentoId = $manager->crearInstrumento($data);
    
    // Obtener el instrumento recién creado para devolverlo completo
    $instrumentoCreado = $manager->getInstrumento($instrumentoId);
    
    // Preparar respuesta
    $response = [
        'success' => true,
        'message' => 'Instrumento creado exitosamente',
        'data' => $instrumentoCreado,
        'id' => $instrumentoId,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    http_response_code(201); // Created
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Determinar código de respuesta HTTP apropiado
    $http_code = 500;
    $message = $e->getMessage();
    
    if (strpos($message, 'Ya existe') !== false) {
        $http_code = 409; // Conflict
    } elseif (strpos($message, 'requerido') !== false || strpos($message, 'inválida') !== false) {
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
    error_log("Error en add_gage.php: " . $message);
}
?>