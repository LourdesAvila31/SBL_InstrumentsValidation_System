<?php

/**
 * Clase base para todas las APIs del sistema SBL
 */

require_once __DIR__ . '/../../app/Core/db_config.php';

abstract class BaseAPI
{
    protected $pdo;
    protected $request_method;
    protected $request_uri;
    protected $query_params;
    protected $body_params;
    protected $user_id;
    protected $empresa_id;
    
    public function __construct()
    {
        // Configurar headers CORS
        $this->setupCORS();
        
        // Manejar preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
        
        // Inicializar conexión a BD
        $this->initializeDatabase();
        
        // Parsear request
        $this->parseRequest();
        
        // Verificar autenticación
        $this->authenticate();
    }
    
    /**
     * Configurar headers CORS
     */
    private function setupCORS(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 86400');
    }
    
    /**
     * Inicializar conexión a base de datos
     */
    private function initializeDatabase(): void
    {
        try {
            $this->pdo = new PDO(
                "mysql:host=" . constant('DB_HOST') . ";dbname=" . constant('DB_NAME') . ";charset=utf8mb4",
                constant('DB_USER'),
                constant('DB_PASS'),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            $this->errorResponse('Error de conexión a la base de datos', 500);
        }
    }
    
    /**
     * Parsear request HTTP
     */
    private function parseRequest(): void
    {
        $this->request_method = $_SERVER['REQUEST_METHOD'];
        $this->request_uri = $_SERVER['REQUEST_URI'];
        
        // Parsear query parameters
        $this->query_params = $_GET;
        
        // Parsear body parameters
        if (in_array($this->request_method, ['POST', 'PUT', 'PATCH'])) {
            $input = file_get_contents('php://input');
            if (!empty($input)) {
                $this->body_params = json_decode($input, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->errorResponse('JSON inválido en el body', 400);
                }
            } else {
                $this->body_params = $_POST;
            }
        }
    }
    
    /**
     * Verificar autenticación del usuario
     */
    private function authenticate(): void
    {
        session_start();
        
        if (!isset($_SESSION['usuario_id'])) {
            $this->errorResponse('Autenticación requerida', 401);
        }
        
        $this->user_id = $_SESSION['usuario_id'];
        $this->empresa_id = $_SESSION['empresa_id'] ?? null;
        
        // Verificar que el usuario sigue siendo válido
        $stmt = $this->pdo->prepare("
            SELECT id, activo, empresa_id 
            FROM usuarios 
            WHERE id = ?
        ");
        $stmt->execute([$this->user_id]);
        $user = $stmt->fetch();
        
        if (!$user || !$user['activo']) {
            session_destroy();
            $this->errorResponse('Sesión inválida', 401);
        }
        
        if (!$this->empresa_id) {
            $this->empresa_id = $user['empresa_id'];
            $_SESSION['empresa_id'] = $this->empresa_id;
        }
    }
    
    /**
     * Verificar permisos del usuario
     */
    protected function checkPermission(string $permission): bool
    {
        // Implementación básica - puede expandirse con el sistema de auth
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM usuarios u
            JOIN roles r ON u.role_id = r.id
            WHERE u.id = ? AND r.permisos LIKE ?
        ");
        $stmt->execute([$this->user_id, "%$permission%"]);
        
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Obtener parámetro del body o query
     */
    protected function getParam(string $key, $default = null)
    {
        return $this->body_params[$key] ?? $this->query_params[$key] ?? $default;
    }
    
    /**
     * Validar parámetros requeridos
     */
    protected function validateRequired(array $required_params): void
    {
        $missing = [];
        
        foreach ($required_params as $param) {
            if (!isset($this->body_params[$param]) || 
                (is_string($this->body_params[$param]) && trim($this->body_params[$param]) === '')) {
                $missing[] = $param;
            }
        }
        
        if (!empty($missing)) {
            $this->errorResponse('Parámetros requeridos faltantes: ' . implode(', ', $missing), 400);
        }
    }
    
    /**
     * Aplicar filtros de paginación
     */
    protected function applyPagination(string $base_query): array
    {
        $page = max(1, intval($this->getParam('page', 1)));
        $limit = min(100, max(1, intval($this->getParam('limit', 20))));
        $offset = ($page - 1) * $limit;
        
        $paginated_query = $base_query . " LIMIT $limit OFFSET $offset";
        
        return [
            'query' => $paginated_query,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'offset' => $offset
            ]
        ];
    }
    
    /**
     * Aplicar filtros de ordenamiento
     */
    protected function applySorting(string $query, array $allowed_fields): string
    {
        $sort_by = $this->getParam('sort_by');
        $sort_order = strtoupper($this->getParam('sort_order', 'ASC'));
        
        if ($sort_by && in_array($sort_by, $allowed_fields)) {
            $sort_order = in_array($sort_order, ['ASC', 'DESC']) ? $sort_order : 'ASC';
            $query .= " ORDER BY $sort_by $sort_order";
        }
        
        return $query;
    }
    
    /**
     * Log de actividad de API
     */
    protected function logActivity(string $action, array $data = []): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO api_logs (
                    usuario_id, empresa_id, endpoint, action, data, 
                    ip_address, user_agent, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $this->user_id,
                $this->empresa_id,
                $this->request_uri,
                $action,
                json_encode($data),
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            // Log error pero no fallar la operación principal
            error_log("Error logging API activity: " . $e->getMessage());
        }
    }
    
    /**
     * Respuesta de éxito
     */
    protected function successResponse($data = null, int $status_code = 200): void
    {
        http_response_code($status_code);
        
        $response = [
            'success' => true,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * Respuesta de error
     */
    protected function errorResponse(string $message, int $status_code = 400, array $details = []): void
    {
        http_response_code($status_code);
        
        $response = [
            'success' => false,
            'error' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if (!empty($details)) {
            $response['details'] = $details;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * Respuesta con paginación
     */
    protected function paginatedResponse(array $data, array $pagination, int $total_count): void
    {
        $response = [
            'success' => true,
            'data' => $data,
            'pagination' => [
                'page' => $pagination['page'],
                'limit' => $pagination['limit'],
                'total' => $total_count,
                'pages' => ceil($total_count / $pagination['limit'])
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * Manejo principal de la request
     */
    public function handleRequest(): void
    {
        try {
            $path_info = $_SERVER['PATH_INFO'] ?? '';
            $segments = array_filter(explode('/', $path_info));
            
            switch ($this->request_method) {
                case 'GET':
                    if (empty($segments)) {
                        $this->getAll();
                    } elseif (isset($segments[0]) && is_numeric($segments[0])) {
                        $this->getById(intval($segments[0]));
                    } else {
                        $this->handleCustomGet($segments);
                    }
                    break;
                
                case 'POST':
                    if (empty($segments)) {
                        $this->create();
                    } else {
                        $this->handleCustomPost($segments);
                    }
                    break;
                
                case 'PUT':
                    if (isset($segments[0]) && is_numeric($segments[0])) {
                        $this->update(intval($segments[0]));
                    } else {
                        $this->errorResponse('ID requerido para actualización', 400);
                    }
                    break;
                
                case 'DELETE':
                    if (isset($segments[0]) && is_numeric($segments[0])) {
                        $this->delete(intval($segments[0]));
                    } else {
                        $this->errorResponse('ID requerido para eliminación', 400);
                    }
                    break;
                
                default:
                    $this->errorResponse('Método no permitido', 405);
            }
        } catch (Exception $e) {
            error_log("API Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            $this->errorResponse('Error interno del servidor', 500);
        }
    }
    
    // Métodos abstractos que deben implementar las clases hijas
    abstract protected function getAll(): void;
    abstract protected function getById(int $id): void;
    abstract protected function create(): void;
    abstract protected function update(int $id): void;
    abstract protected function delete(int $id): void;
    
    // Métodos opcionales para endpoints personalizados
    protected function handleCustomGet(array $segments): void
    {
        $this->errorResponse('Endpoint no encontrado', 404);
    }
    
    protected function handleCustomPost(array $segments): void
    {
        $this->errorResponse('Endpoint no encontrado', 404);
    }
}