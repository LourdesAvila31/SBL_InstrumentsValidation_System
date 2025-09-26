<?php
/**
 * API Endpoint: Eliminar Instrumento
 * 
 * POST /backend/instrumentos/gages/delete_gage.php
 * 
 * Elimina (soft delete) un instrumento del sistema
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Permitir métodos POST y DELETE
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'DELETE'])) {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Método no permitido. Use POST o DELETE.'
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
    
    // También verificar en $_GET para compatibilidad
    if (empty($data) && isset($_GET['id'])) {
        $data = ['id' => $_GET['id']];
    }
    
    if (empty($data)) {
        throw new Exception('No se proporcionaron datos para eliminar el instrumento');
    }
    
    // Verificar que se proporcionó un ID (puede ser único o array de IDs)
    $ids = [];
    
    if (isset($data['id'])) {
        $ids = is_array($data['id']) ? $data['id'] : [$data['id']];
    } elseif (isset($data['ids'])) {
        $ids = is_array($data['ids']) ? $data['ids'] : [$data['ids']];
    } else {
        throw new Exception('ID(s) de instrumento requerido(s)');
    }
    
    // Validar IDs
    $validIds = [];
    foreach ($ids as $id) {
        $numericId = intval($id);
        if ($numericId > 0) {
            $validIds[] = $numericId;
        }
    }
    
    if (empty($validIds)) {
        throw new Exception('ID(s) de instrumento inválido(s)');
    }
    
    // Crear instancia del manager
    $manager = new InstrumentosManager();
    
    $eliminados = [];
    $errores = [];
    
    // Eliminar cada instrumento
    foreach ($validIds as $id) {
        try {
            // Obtener datos del instrumento antes de eliminarlo
            $instrumento = $manager->getInstrumento($id);
            
            if (!$instrumento) {
                $errores[] = "Instrumento ID $id no encontrado";
                continue;
            }
            
            // Eliminar instrumento
            $resultado = $manager->eliminarInstrumento($id);
            
            if ($resultado) {
                $eliminados[] = [
                    'id' => $id,
                    'codigo' => $instrumento['codigo_identificacion'],
                    'descripcion' => $instrumento['descripcion']
                ];
            } else {
                $errores[] = "No se pudo eliminar el instrumento ID $id";
            }
            
        } catch (Exception $e) {
            $errores[] = "Error al eliminar instrumento ID $id: " . $e->getMessage();
        }
    }
    
    // Preparar respuesta
    $response = [
        'success' => count($eliminados) > 0,
        'message' => count($eliminados) > 0 ? 
            'Instrumentos eliminados exitosamente' : 
            'No se pudieron eliminar los instrumentos',
        'eliminados' => $eliminados,
        'total_eliminados' => count($eliminados),
        'errores' => $errores,
        'total_errores' => count($errores),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Código de respuesta HTTP
    if (count($eliminados) > 0 && count($errores) === 0) {
        http_response_code(200); // Todo OK
    } elseif (count($eliminados) > 0 && count($errores) > 0) {
        http_response_code(207); // Multi-Status (algunos exitosos, algunos fallaron)
    } else {
        http_response_code(400); // Bad Request (ninguno eliminado)
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Determinar código de respuesta HTTP apropiado
    $http_code = 500;
    $message = $e->getMessage();
    
    if (strpos($message, 'no encontrado') !== false) {
        $http_code = 404; // Not Found
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
    error_log("Error en delete_gage.php: " . $message);
}
?>