<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once dirname(__DIR__, 3) . '/app/Modules/Internal/Developer/DeveloperAuth.php';
require_once dirname(__DIR__, 3) . '/app/Modules/Internal/Developer/DocumentationManager.php';

function respondJson(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function getJsonInput(): array
{
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?? [];
}

function handleError(Exception $e): void
{
    error_log('Developer Documents API Error: ' . $e->getMessage());
    respondJson([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}

try {
    $auth = new DeveloperAuth();
    
    if (!$auth->isDeveloper()) {
        respondJson([
            'success' => false,
            'error' => 'Acceso denegado. Se requiere rol de developer.'
        ], 403);
    }

    $manager = new DocumentationManager();
    $method = $_SERVER['REQUEST_METHOD'];
    $path = $_GET['path'] ?? '';
    $id = $_GET['id'] ?? null;

    switch ($method) {
        case 'GET':
            if ($id) {
                $document = $manager->getDocument((int)$id);
                respondJson([
                    'success' => true,
                    'data' => $document
                ]);
            } else {
                switch ($path) {
                    case 'list':
                        $filters = [
                            'document_type' => $_GET['document_type'] ?? null,
                            'status' => $_GET['status'] ?? null,
                            'department' => $_GET['department'] ?? null,
                            'search' => $_GET['search'] ?? null,
                            'expiring_soon' => $_GET['expiring_soon'] ?? null,
                            'limit' => $_GET['limit'] ?? 50,
                            'offset' => $_GET['offset'] ?? 0
                        ];
                        $documents = $manager->getDocuments(array_filter($filters));
                        respondJson([
                            'success' => true,
                            'data' => $documents
                        ]);
                        break;

                    case 'expiring':
                        $days = $_GET['days'] ?? 30;
                        $documents = $manager->getExpiringDocuments((int)$days);
                        respondJson([
                            'success' => true,
                            'data' => $documents
                        ]);
                        break;

                    case 'metrics':
                        $metrics = $manager->getDocumentationMetrics();
                        respondJson([
                            'success' => true,
                            'data' => $metrics
                        ]);
                        break;

                    default:
                        respondJson([
                            'success' => false,
                            'error' => 'Endpoint no encontrado'
                        ], 404);
                }
            }
            break;

        case 'POST':
            $data = getJsonInput();
            
            switch ($path) {
                case 'create':
                    $document = $manager->createDocument($data);
                    respondJson([
                        'success' => true,
                        'data' => $document
                    ], 201);
                    break;

                default:
                    respondJson([
                        'success' => false,
                        'error' => 'Endpoint no encontrado'
                    ], 404);
            }
            break;

        case 'PUT':
            if (!$id) {
                respondJson([
                    'success' => false,
                    'error' => 'ID requerido para actualización'
                ], 400);
            }

            $data = getJsonInput();
            
            switch ($path) {
                case 'update':
                    $result = $manager->updateDocument((int)$id, $data);
                    respondJson([
                        'success' => $result,
                        'message' => $result ? 'Documento actualizado' : 'Error al actualizar'
                    ]);
                    break;

                case 'review':
                    if (!isset($data['action']) || !in_array($data['action'], ['approve', 'reject', 'request_changes'])) {
                        respondJson([
                            'success' => false,
                            'error' => 'Acción de revisión no válida'
                        ], 400);
                    }
                    
                    $result = $manager->reviewDocument(
                        (int)$id, 
                        $data['action'], 
                        $data['comments'] ?? ''
                    );
                    respondJson([
                        'success' => $result,
                        'message' => $result ? 'Documento revisado' : 'Error al revisar'
                    ]);
                    break;

                case 'activate':
                    $result = $manager->activateDocument((int)$id);
                    respondJson([
                        'success' => $result,
                        'message' => $result ? 'Documento activado' : 'Error al activar'
                    ]);
                    break;

                default:
                    respondJson([
                        'success' => false,
                        'error' => 'Endpoint no encontrado'
                    ], 404);
            }
            break;

        default:
            respondJson([
                'success' => false,
                'error' => 'Método no permitido'
            ], 405);
    }

} catch (Exception $e) {
    handleError($e);
}