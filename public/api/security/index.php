<?php
/**
 * Archivo de enrutamiento para API de Seguridad
 * 
 * Este archivo maneja todas las peticiones API del sistema de seguridad
 * Debe ser incluido desde el archivo principal de API o configurado en .htaccess
 */

declare(strict_types=1);

// Configurar headers de seguridad y CORS
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// Manejo de CORS para requests preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 86400'); // 24 horas
    http_response_code(200);
    exit;
}

// Verificar que la petición es para el API de seguridad
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = '/api/security';

if (strpos($requestUri, $basePath) !== 0) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => [
            'message' => 'Endpoint not found',
            'code' => 404
        ]
    ]);
    exit;
}

// Incluir dependencias
require_once __DIR__ . '/../../Core/db.php';
require_once __DIR__ . '/SecurityApiController.php';

try {
    // Inicializar controlador y manejar request
    $controller = new SecurityApiController();
    $controller->handleRequest();
    
} catch (Exception $e) {
    // Log del error (en producción esto iría a un archivo de log)
    error_log("Security API Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'message' => 'Internal server error',
            'code' => 500
        ],
        'timestamp' => date('c')
    ]);
}