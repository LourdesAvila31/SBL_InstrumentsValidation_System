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
require_once dirname(__DIR__, 3) . '/app/Modules/Internal/Developer/IncidentChangeManager.php';

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
    error_log('Developer Incidents API Error: ' . $e->getMessage());
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

    $manager = new IncidentChangeManager();
    $method = $_SERVER['REQUEST_METHOD'];
    $path = $_GET['path'] ?? '';
    $id = $_GET['id'] ?? null;

    switch ($method) {
        case 'GET':
            if ($id) {
                if ($path === 'incidents') {
                    $incident = $manager->getIncident((int)$id);
                    respondJson([
                        'success' => true,
                        'data' => $incident
                    ]);
                }
            } else {
                switch ($path) {
                    case 'incidents':
                        $filters = [
                            'severity' => $_GET['severity'] ?? null,
                            'status' => $_GET['status'] ?? null,
                            'assigned_to' => $_GET['assigned_to'] ?? null,
                            'date_from' => $_GET['date_from'] ?? null,
                            'date_to' => $_GET['date_to'] ?? null,
                            'limit' => $_GET['limit'] ?? 50,
                            'offset' => $_GET['offset'] ?? 0
                        ];
                        $incidents = $manager->getIncidents(array_filter($filters));
                        respondJson([
                            'success' => true,
                            'data' => $incidents
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
                case 'incidents':
                    $incident = $manager->createIncident($data);
                    respondJson([
                        'success' => true,
                        'data' => $incident
                    ], 201);
                    break;

                case 'changes':
                    $change = $manager->createChangeRequest($data);
                    respondJson([
                        'success' => true,
                        'data' => $change
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
                case 'incidents':
                    $result = $manager->updateIncident((int)$id, $data);
                    respondJson([
                        'success' => $result,
                        'message' => $result ? 'Incidencia actualizada' : 'Error al actualizar'
                    ]);
                    break;

                case 'changes':
                    if (isset($data['action']) && in_array($data['action'], ['approve', 'reject'])) {
                        $result = $manager->reviewChangeRequest(
                            (int)$id, 
                            $data['action'], 
                            $data['comments'] ?? ''
                        );
                        respondJson([
                            'success' => $result,
                            'message' => $result ? 'Cambio revisado' : 'Error al revisar'
                        ]);
                    } else {
                        respondJson([
                            'success' => false,
                            'error' => 'Acción no válida'
                        ], 400);
                    }
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