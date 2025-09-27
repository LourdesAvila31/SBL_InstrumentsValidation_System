<?php

/**
 * API endpoints para el sistema de autenticación centralizado
 */

require_once __DIR__ . '/../../../Core/Auth/AuthFactory.php';

use App\Core\Auth\AuthFactory;

class AuthAPI
{
    private $authManager;
    private $middleware;

    public function __construct()
    {
        // Configurar headers para API
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        // Manejar preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
            /**
             * Elimina un rol
             */
            private function deleteRole($roleId): void
            {
                $this->requirePermission('roles.delete');
        
                $success = $this->authManager->roleManager->deleteRole($roleId);
        
                if ($success) {
                    $this->successResponse(['message' => 'Rol eliminado exitosamente']);
                } else {
                    $this->errorResponse('Error al eliminar rol', 500);
                }
            }
        
            /**
             * Elimina un permiso
             */
            private function deletePermission($permissionId): void
            {
                $this->requirePermission('roles.delete');
        
                $success = $this->authManager->permissionManager->deletePermission($permissionId);
        
                if ($success) {
                    $this->successResponse(['message' => 'Permiso eliminado exitosamente']);
                } else {
                    $this->errorResponse('Error al eliminar permiso', 500);
                }
            }
        
            /**
             * Elimina un usuario
             */
            private function deleteUser($userId): void
            {
                $this->requirePermission('users.delete');
        
                $success = $this->authManager->authProvider->deleteUser($userId);
        
                if ($success) {
                    $this->successResponse(['message' => 'Usuario eliminado exitosamente']);
                } else {
                    $this->errorResponse('Error al eliminar usuario', 500);
                }
            }
        }

        try {
            // Inicializar sistema de autenticación
            $authSystem = AuthFactory::createAuthSystem();
            $this->authManager = $authSystem['auth'];
            $this->middleware = $authSystem['middleware'];

        } catch (Exception $e) {
            $this->errorResponse('Sistema de autenticación no disponible', 500);
        }
    }

    /**
     * Maneja las peticiones API
     */
    public function handleRequest(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = $_SERVER['REQUEST_URI'];
        $path = parse_url($uri, PHP_URL_PATH);
        
        // Remover prefijo de API
        $endpoint = str_replace('/api/auth', '', $path);
        $segments = array_filter(explode('/', $endpoint));
        $segments = array_values($segments);

        try {
            // Rutear según endpoint
            switch ($method) {
                case 'POST':
                    $this->handlePost($segments);
                    break;
                
                case 'GET':
                    $this->handleGet($segments);
                    break;
                
                case 'PUT':
                    $this->handlePut($segments);
                    break;
                
                case 'DELETE':
                    $this->handleDelete($segments);
                    break;
                
                default:
                    $this->errorResponse('Método no permitido', 405);
            }

        } catch (Exception $e) {
    /**
     * Maneja peticiones DELETE
     */
    private function handleDelete(array $segments): void
    {
        $endpoint = $segments[0] ?? '';

        switch ($endpoint) {
            case 'roles':
                if (isset($segments[1])) {
                    $this->deleteRole($segments[1]);
                } else {
                    $this->errorResponse('ID de rol requerido', 400);
                }
                break;

            case 'permissions':
                if (isset($segments[1])) {
                    $this->deletePermission($segments[1]);
                } else {
                    $this->errorResponse('ID de permiso requerido', 400);
                }
                break;

            case 'users':
                if (isset($segments[1])) {
                    $this->deleteUser($segments[1]);
                } else {
                    $this->errorResponse('ID de usuario requerido', 400);
                }
                break;

            default:
                $this->errorResponse('Endpoint no encontrado', 404);
        }
    }

    /**
     * Maneja peticiones GET
     */
    private function handleGet(array $segments): void
    {
     * Maneja peticiones POST
     */
    private function handlePost(array $segments): void
    {
        $endpoint = $segments[0] ?? '';

        switch ($endpoint) {
            case 'login':
                $this->login();
                break;
            
            case 'logout':
                $this->logout();
                break;
            
            case 'roles':
                $this->createRole();
                break;
            
            case 'permissions':
                $this->createPermission();
                break;
            
            case 'users':
                if (isset($segments[1]) && $segments[1] === 'assign-role') {
                    $this->assignRole();
                } elseif (isset($segments[1]) && $segments[1] === 'grant-permission') {
                    $this->grantPermission();
                } else {
                    $this->errorResponse('Endpoint no encontrado', 404);
                }
                break;
            
            case 'password-reset':
                $this->requestPasswordReset();
                break;
            
            default:
                $this->errorResponse('Endpoint no encontrado', 404);
        }
    }

    /**
     * Maneja peticiones GET
     */
    private function handleGet(array $segments): void
    {
        $endpoint = $segments[0] ?? '';

        switch ($endpoint) {
            case 'user':
                $this->getCurrentUser();
                break;
            
            case 'check':
                $this->checkAuth();
                break;
            
            case 'roles':
                if (isset($segments[1])) {
                    $this->getRoleDetails($segments[1]);
                } else {
                    $this->getAllRoles();
                }
                break;
            
            case 'permissions':
                if (isset($segments[1])) {
                    $this->getPermissionDetails($segments[1]);
                } else {
                    $this->getAllPermissions();
                }
                break;
            
            case 'users':
                $userId = $segments[1] ?? null;
                if ($userId && isset($segments[2])) {
                    if ($segments[2] === 'roles') {
                        $this->getUserRoles($userId);
                    } elseif ($segments[2] === 'permissions') {
                        $this->getUserPermissions($userId);
                    } elseif ($segments[2] === 'sessions') {
                        $this->getUserSessions($userId);
                    }
                } else {
                    $this->errorResponse('Endpoint no encontrado', 404);
                }
                break;
            
            case 'stats':
                $this->getStats();
                break;
            
            default:
                $this->errorResponse('Endpoint no encontrado', 404);
        }
    }

    /**
     * Login de usuario
     */
    private function login(): void
    {
        $data = $this->getJsonInput();
        
        if (!isset($data['username']) || !isset($data['password'])) {
            $this->errorResponse('Username y password requeridos', 400);
        }

        $result = $this->authManager->login($data['username'], $data['password']);
        
        if ($result['success']) {
            $this->successResponse([
                'message' => 'Login exitoso',
                'user' => $result['user'],
                'session_id' => session_id()
            ]);
        } else {
            $this->errorResponse($result['message'], 401);
        }
    }

    /**
     * Logout de usuario
     */
    private function logout(): void
    {
        $this->requireAuth();
        
        $result = $this->authManager->logout();
        
        if ($result['success']) {
            $this->successResponse(['message' => 'Logout exitoso']);
        } else {
            $this->errorResponse($result['message'], 500);
        }
    }

    /**
     * Obtiene usuario actual
     */
    private function getCurrentUser(): void
    {
        $this->requireAuth();
        
        $user = $this->authManager->getCurrentUser();
        $this->successResponse([
            'user' => $user,
            'permissions' => $this->authManager->getUserPermissions($user['id']),
            'roles' => $this->authManager->getUserRoles($user['id'])
        ]);
    }

    /**
     * Verifica autenticación
     */
    private function checkAuth(): void
    {
        $isAuthenticated = $this->authManager->isAuthenticated();
        
        $this->successResponse([
            'authenticated' => $isAuthenticated,
            'user' => $isAuthenticated ? $this->authManager->getCurrentUser() : null
        ]);
    }

    /**
     * Obtiene todos los roles
     */
    private function getAllRoles(): void
    {
        $this->requirePermission('roles.view');
        
        $roles = $this->authManager->roleManager->getAllRoles();
        $this->successResponse(['roles' => $roles]);
    }

    /**
     * Crea un nuevo rol
     */
    private function createRole(): void
    {
        $this->requirePermission('roles.create');
        
        $data = $this->getJsonInput();
        
        if (!isset($data['name'])) {
            $this->errorResponse('Nombre del rol requerido', 400);
        }

        $success = $this->authManager->roleManager->createRole(
            $data['name'],
            $data['description'] ?? '',
            $data['permissions'] ?? []
        );

        if ($success) {
            $this->successResponse(['message' => 'Rol creado exitosamente']);
        } else {
            $this->errorResponse('Error al crear rol', 500);
        }
    }

    /**
     * Obtiene todos los permisos
     */
    private function getAllPermissions(): void
    {
        $this->requirePermission('roles.view');
        
        $permissions = $this->authManager->permissionManager->getAllPermissions();
        $this->successResponse(['permissions' => $permissions]);
    }

    /**
     * Obtiene detalles de un rol específico
     */
    private function getRoleDetails($roleId): void
    {
        $this->requirePermission('roles.view');

        $role = $this->authManager->roleManager->getRoleById($roleId);

        if ($role) {
            $this->successResponse(['role' => $role]);
        } else {
            $this->errorResponse('Rol no encontrado', 404);
        }
    }

    /**
     * Obtiene detalles de un permiso específico
     */
    private function getPermissionDetails($permissionId): void
    {
        $this->requirePermission('roles.view');

        $permission = $this->authManager->permissionManager->getPermissionById($permissionId);

        if ($permission) {
            $this->successResponse(['permission' => $permission]);
        } else {
            $this->errorResponse('Permiso no encontrado', 404);
        }
    }

    /**
     * Crea un nuevo permiso
     */
    private function createPermission(): void
    {
        $this->requirePermission('roles.create');
        
        $data = $this->getJsonInput();
        
        if (!isset($data['name'])) {
            $this->errorResponse('Nombre del permiso requerido', 400);
        }

        $success = $this->authManager->permissionManager->createPermission(
            $data['name'],
            $data['description'] ?? '',
            $data['category'] ?? ''
        );

        if ($success) {
            $this->successResponse(['message' => 'Permiso creado exitosamente']);
        } else {
            $this->errorResponse('Error al crear permiso', 500);
        }
    }

    /**
     * Asigna rol a usuario
     */
    private function assignRole(): void
    {
        $this->requirePermission('roles.assign');
        
        $data = $this->getJsonInput();
        
        if (!isset($data['user_id']) || !isset($data['role'])) {
            $this->errorResponse('user_id y role requeridos', 400);
        }

        $success = $this->authManager->roleManager->assignRole($data['user_id'], $data['role']);

        if ($success) {
            $this->successResponse(['message' => 'Rol asignado exitosamente']);
        } else {
            $this->errorResponse('Error al asignar rol', 500);
        }
    }

    /**
     * Otorga permiso a usuario
     */
    private function grantPermission(): void
    {
        $this->requirePermission('roles.assign');
        
        $data = $this->getJsonInput();
        
        if (!isset($data['user_id']) || !isset($data['permission'])) {
            $this->errorResponse('user_id y permission requeridos', 400);
        }

        $success = $this->authManager->permissionManager->grantPermission(
            $data['user_id'], 
            $data['permission'],
            $data['context'] ?? []
        );

        if ($success) {
            $this->successResponse(['message' => 'Permiso otorgado exitosamente']);
        } else {
            $this->errorResponse('Error al otorgar permiso', 500);
        }
    }

    /**
     * Obtiene roles de usuario
     */
    private function getUserRoles($userId): void
    {
        $this->requirePermission('users.view');
        
        $roles = $this->authManager->roleManager->getUserRoles($userId);
        $this->successResponse(['roles' => $roles]);
    }

    /**
     * Obtiene permisos de usuario
     */
    private function getUserPermissions($userId): void
    {
        $this->requirePermission('users.view');
        
        $permissions = $this->authManager->permissionManager->getUserPermissions($userId);
        $this->successResponse(['permissions' => $permissions]);
    }

    /**
     * Obtiene sesiones de usuario
     */
    private function getUserSessions($userId): void
    {
        $this->requirePermission('users.view');
        
        $sessions = $this->authManager->sessionManager->getUserSessions($userId);
        $this->successResponse(['sessions' => $sessions]);
    }

    /**
     * Solicita reset de contraseña
     */
    private function requestPasswordReset(): void
    {
        $data = $this->getJsonInput();
        
        if (!isset($data['email'])) {
            $this->errorResponse('Email requerido', 400);
        }

        // Buscar usuario por email
        $user = $this->authManager->authProvider->findUserByEmail($data['email']);
        
        if (!$user) {
            // Por seguridad, no revelar si el email existe
            $this->successResponse(['message' => 'Si el email existe, se enviará un enlace de recuperación']);
            return;
        }

        $token = $this->authManager->authProvider->generatePasswordResetToken($user['id']);
        
        if ($token) {
            // Aquí enviarías el email con el token
            // $this->sendPasswordResetEmail($user['email'], $token);
            
            $this->successResponse(['message' => 'Enlace de recuperación enviado']);
        } else {
            $this->errorResponse('Error al generar token de recuperación', 500);
        }
    }

    /**
     * Obtiene estadísticas del sistema
     */
    private function getStats(): void
    {
        $this->requirePermission('system.admin');
        
        $stats = [
            'users' => $this->authManager->authProvider->getAuthStats(),
            'roles' => $this->authManager->roleManager->getRoleStats(),
            'permissions' => $this->authManager->permissionManager->getPermissionStats(),
            'sessions' => $this->authManager->sessionManager->getSessionStats()
        ];

        $this->successResponse(['stats' => $stats]);
    }

    /**
     * Requiere autenticación
     */
    private function requireAuth(): void
    {
        if (!$this->authManager->isAuthenticated()) {
            $this->errorResponse('Autenticación requerida', 401);
        }
    }

    /**
     * Requiere permiso específico
     */
    private function requirePermission(string $permission): void
    {
        $this->requireAuth();
        
        if (!$this->authManager->hasPermission($permission)) {
            $this->errorResponse('Permisos insuficientes', 403);
        }
    }

    /**
     * Obtiene datos JSON del request
     */
    private function getJsonInput(): array
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->errorResponse('JSON inválido', 400);
        }
        
        return $data ?? [];
    }

    /**
     * Respuesta de éxito
     */
    private function successResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        echo json_encode([
            'success' => true,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }

    /**
     * Respuesta de error
     */
    private function errorResponse(string $message, int $statusCode = 400): void
    {
        http_response_code($statusCode);
        echo json_encode([
            'success' => false,
            'error' => $message,
            'code' => $statusCode,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
}

// Ejecutar API si se accede directamente
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $api = new AuthAPI();
    $api->handleRequest();
}