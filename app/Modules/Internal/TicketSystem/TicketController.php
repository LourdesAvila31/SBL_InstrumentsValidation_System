<?php
/**
 * API Controller para Sistema de Gestión de Tickets
 * Conforme a GAMP 5 y normativas GxP
 */

require_once dirname(__DIR__, 2) . '/Core/permissions.php';
require_once __DIR__ . '/TicketManager.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

header('Content-Type: application/json');

try {
    $ticketManager = new TicketManager();
    
    switch ($action) {
        case 'create':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $data['created_by'] = $_SESSION['usuario_id'];
                
                $result = $ticketManager->createTicket($data);
                echo json_encode($result);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Método no permitido']);
            }
            break;
            
        case 'list':
            if ($method === 'GET') {
                $filters = [
                    'status' => $_GET['status'] ?? null,
                    'risk_level' => $_GET['risk_level'] ?? null,
                    'category' => $_GET['category'] ?? null,
                    'assigned_to' => $_GET['assigned_to'] ?? null,
                    'date_from' => $_GET['date_from'] ?? null,
                    'date_to' => $_GET['date_to'] ?? null
                ];
                
                $page = (int)($_GET['page'] ?? 1);
                $limit = (int)($_GET['limit'] ?? 20);
                
                $tickets = $ticketManager->getTickets($filters, $page, $limit);
                echo json_encode(['success' => true, 'data' => $tickets]);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Método no permitido']);
            }
            break;
            
        case 'update':
            if ($method === 'PUT') {
                $ticketId = (int)($_GET['id'] ?? 0);
                if (!$ticketId) {
                    throw new Exception('ID de ticket requerido');
                }
                
                $data = json_decode(file_get_contents('php://input'), true);
                
                if (isset($data['status'])) {
                    $result = $ticketManager->updateTicketStatus(
                        $ticketId, 
                        $data['status'], 
                        $_SESSION['usuario_id'],
                        $data['comment'] ?? null
                    );
                    echo json_encode($result);
                } else {
                    echo json_encode(['error' => 'Datos de actualización requeridos']);
                }
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Método no permitido']);
            }
            break;
            
        case 'assign':
            if ($method === 'PUT') {
                $ticketId = (int)($_GET['id'] ?? 0);
                $data = json_decode(file_get_contents('php://input'), true);
                
                if (!$ticketId || !isset($data['assigned_to'])) {
                    throw new Exception('ID de ticket y usuario asignado requeridos');
                }
                
                $success = $ticketManager->assignTicket(
                    $ticketId, 
                    $data['assigned_to'], 
                    $_SESSION['usuario_id']
                );
                
                echo json_encode(['success' => $success]);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Método no permitido']);
            }
            break;
            
        case 'history':
            if ($method === 'GET') {
                $ticketId = (int)($_GET['id'] ?? 0);
                if (!$ticketId) {
                    throw new Exception('ID de ticket requerido');
                }
                
                $history = $ticketManager->getTicketHistory($ticketId);
                echo json_encode(['success' => true, 'data' => $history]);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Método no permitido']);
            }
            break;
            
        case 'stats':
            if ($method === 'GET') {
                $stats = $ticketManager->getTicketStats();
                echo json_encode(['success' => true, 'data' => $stats]);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Método no permitido']);
            }
            break;
            
        case 'categories':
            if ($method === 'GET') {
                $categories = $ticketManager->getTicketCategories();
                echo json_encode(['success' => true, 'data' => $categories]);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Método no permitido']);
            }
            break;
            
        case 'risk-matrix':
            if ($method === 'GET') {
                $matrix = $ticketManager->getRiskMatrix();
                echo json_encode(['success' => true, 'data' => $matrix]);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Método no permitido']);
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Acción no encontrada']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>