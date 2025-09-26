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
require_once dirname(__DIR__, 3) . '/app/Modules/Internal/Developer/DeveloperDashboard.php';

function respondJson(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function handleError(Exception $e): void
{
    error_log('Developer Dashboard API Error: ' . $e->getMessage());
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

    $dashboard = new DeveloperDashboard();
    $method = $_SERVER['REQUEST_METHOD'];
    $path = $_GET['path'] ?? 'kpis';

    switch ($method) {
        case 'GET':
            switch ($path) {
                case 'kpis':
                    $kpis = $dashboard->getSystemKPIs();
                    respondJson([
                        'success' => true,
                        'data' => $kpis
                    ]);
                    break;

                case 'charts':
                    $charts = $dashboard->getRealTimeChartData();
                    respondJson([
                        'success' => true,
                        'data' => $charts
                    ]);
                    break;

                case 'developer-info':
                    $info = $auth->getDeveloperInfo();
                    respondJson([
                        'success' => true,
                        'data' => $info
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