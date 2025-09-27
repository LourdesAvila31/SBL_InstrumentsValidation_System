<?php

namespace App\Core\Auth;

use App\Core\Auth\Contracts\AuthProviderInterface;
use App\Core\Auth\Contracts\RoleManagerInterface;
use App\Core\Auth\Contracts\PermissionManagerInterface;
use App\Core\Auth\Contracts\SessionManagerInterface;
use Exception;

/**
 * Gestor centralizado de autenticación y autorización
 * 
 * Proporciona una interfaz unificada para la autenticación,
 * autorización y gestión de sesiones en todo el sistema SBL
 */
class AuthManager
{
    private $authProvider;
    private $roleManager;
    private $permissionManager;
    private $sessionManager;
    private AuthLogger $logger;
    private array $config;
    private ?array $currentUser = null;
    private ?string $currentSessionId = null;

    public function __construct(
        $authProvider,
        $roleManager,
        $permissionManager,
        $sessionManager,
        AuthLogger $logger,
        array $config = []
    ) {
        $this->authProvider = $authProvider;
        $this->roleManager = $roleManager;
        $this->permissionManager = $permissionManager;
        $this->sessionManager = $sessionManager;
        $this->logger = $logger;
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Autentica un usuario con credenciales
     *
     * @param array $credentials Credenciales (username, password, etc.)
     * @param array $options Opciones adicionales
     * @return array Resultado de la autenticación
     */
    public function login(array $credentials, array $options = []): array
    {
        try {
            // Validar credenciales requeridas
            if (!$this->validateCredentialsFormat($credentials)) {
                $this->logger->logFailedLogin($credentials['username'] ?? 'unknown', 'Invalid credentials format');
                return $this->failureResponse('Formato de credenciales inválido');
            }

            // Intentar autenticar
            $user = $this->authProvider->authenticate($credentials);
            
            if (!$user) {
                $this->logger->logFailedLogin($credentials['username'] ?? 'unknown', 'Invalid credentials');
                return $this->failureResponse('Credenciales inválidas');
            }

            // Verificar si el usuario está activo
            if (!$this->isUserActive($user)) {
                $this->logger->logFailedLogin($user['username'] ?? $user['id'], 'User is inactive');
                return $this->failureResponse('Usuario inactivo');
            }

            // Verificar bloqueos de seguridad
            if ($this->isUserBlocked($user['id'])) {
                $this->logger->logFailedLogin($user['username'] ?? $user['id'], 'User is blocked');
                return $this->failureResponse('Usuario bloqueado temporalmente');
            }

            // Crear sesión
            $sessionId = $this->sessionManager->start($user, $options);
            
            if (!$sessionId) {
                $this->logger->logError('Failed to create session for user: ' . $user['id']);
                return $this->failureResponse('Error interno del sistema');
            }

            // Actualizar último login
            $this->authProvider->updateLastLogin($user['id']);

            // Configurar datos de sesión PHP tradicional (compatibilidad)
            $this->setupLegacySession($user, $sessionId);

            // Establecer usuario actual
            $this->currentUser = $user;
            $this->currentSessionId = $sessionId;

            // Log exitoso
            $this->logger->logSuccessfulLogin($user['username'] ?? $user['id'], $sessionId);

            return $this->successResponse([
                'user' => $this->sanitizeUserData($user),
                'session_id' => $sessionId,
                'expires_at' => $this->calculateSessionExpiry(),
                'permissions' => $this->getUserPermissions($user['id']),
                'roles' => $this->getUserRoles($user['id'])
            ]);

        } catch (Exception $e) {
            $this->logger->logError('Login error: ' . $e->getMessage());
            return $this->failureResponse('Error interno del sistema');
        }
    }

    /**
     * Cierra la sesión del usuario actual
     *
     * @param string|null $sessionId ID de sesión específica (opcional)
     * @return array Resultado de la operación
     */
    public function logout(?string $sessionId = null): array
    {
        try {
            $targetSessionId = $sessionId ?? $this->currentSessionId ?? session_id();
            
            // Obtener datos de sesión antes de destruir
            $sessionData = $this->sessionManager->get($targetSessionId);
            
            // Destruir sesión en el manager
            $this->sessionManager->destroy($targetSessionId);
            
            // Limpiar sesión PHP tradicional si es la actual
            if (!$sessionId || $targetSessionId === session_id()) {
                session_destroy();
                session_start(); // Reiniciar para nueva sesión limpia
            }

            // Limpiar datos actuales
            $this->currentUser = null;
            $this->currentSessionId = null;

            // Log de logout
            if ($sessionData && isset($sessionData['user_id'])) {
                $this->logger->logLogout($sessionData['user_id'], $targetSessionId);
            }

            return $this->successResponse(['message' => 'Sesión cerrada correctamente']);

        } catch (Exception $e) {
            $this->logger->logError('Logout error: ' . $e->getMessage());
            return $this->failureResponse('Error al cerrar sesión');
        }
    }

    /**
     * Verifica si el usuario actual está autenticado
     *
     * @return bool True si está autenticado
     */
    public function check(): bool
    {
        try {
            if ($this->currentUser) {
                return true;
            }

            // Verificar sesión PHP tradicional
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            $sessionId = session_id();
            if (!$sessionId) {
                return false;
            }

            // Verificar en el session manager
            if (!$this->sessionManager->validateSession($sessionId)) {
                return false;
            }

            $sessionInfo = $this->sessionManager->getSessionInfo($sessionId);
            if (!$sessionInfo || !isset($sessionInfo['user_id'])) {
                return false;
            }

            // Cargar datos del usuario
            $user = $this->authProvider->getUserById($sessionInfo['user_id']);
            if (!$user) {
                return false;
            }

            $this->currentUser = $user;
            $this->currentSessionId = $sessionId;

            return true;

        } catch (Exception $e) {
            $this->logger->logError('Auth check error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Alias para check()
     */
    public function isAuthenticated(): bool
    {
        return $this->check();
    }

    /**
     * Obtiene el usuario actual
     */
    public function getCurrentUser(): ?array
    {
        if (!$this->check()) {
            return null;
        }
        return $this->currentUser;
    }

    /**
     * Renueva la sesión actual
     */
    public function renewCurrentSession(): bool
    {
        if (!$this->check()) {
            return false;
        }
        return $this->sessionManager->renewSession($this->currentSessionId);
    }

    /**
     * Obtiene el logger
     */
    public function getLogger(): AuthLogger
    {
        return $this->logger;
    }

    /**
     * Obtiene el usuario actualmente autenticado
     *
     * @return array|null Datos del usuario o null si no está autenticado
     */
    public function user(): ?array
    {
        if (!$this->check()) {
            return null;
        }

        return $this->sanitizeUserData($this->currentUser);
    }

    /**
     * Obtiene el ID del usuario actual
     *
     * @return mixed|null ID del usuario o null
     */
    public function id()
    {
        $user = $this->user();
        return $user['id'] ?? null;
    }

    /**
     * Verifica si el usuario actual tiene un rol específico
     *
     * @param string $role Nombre del rol
     * @return bool True si tiene el rol
     */
    public function hasRole(string $role): bool
    {
        if (!$this->check()) {
            return false;
        }

        return $this->roleManager->hasRole($this->currentUser['id'], $role);
    }

    /**
     * Verifica si el usuario actual tiene un permiso específico
     *
     * @param string $permission Nombre del permiso
     * @param array $context Contexto adicional
     * @return bool True si tiene el permiso
     */
    public function can(string $permission, array $context = []): bool
    {
        if (!$this->check()) {
            return false;
        }

        // Agregar contexto de empresa si existe en sesión
        if (isset($_SESSION['empresa_id']) && !isset($context['empresa_id'])) {
            $context['empresa_id'] = $_SESSION['empresa_id'];
        }

        return $this->permissionManager->hasPermission($this->currentUser['id'], $permission, $context);
    }

    /**
     * Verifica múltiples permisos (requiere todos)
     *
     * @param array $permissions Lista de permisos
     * @param array $context Contexto adicional
     * @return bool True si tiene todos los permisos
     */
    public function canAll(array $permissions, array $context = []): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->can($permission, $context)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Verifica múltiples permisos (requiere al menos uno)
     *
     * @param array $permissions Lista de permisos
     * @param array $context Contexto adicional
     * @return bool True si tiene al menos uno de los permisos
     */
    public function canAny(array $permissions, array $context = []): bool
    {
        foreach ($permissions as $permission) {
            if ($this->can($permission, $context)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Requiere autenticación o lanza excepción
     *
     * @throws AuthenticationException
     */
    public function requireAuth(): void
    {
        if (!$this->check()) {
            throw new AuthenticationException('Usuario no autenticado');
        }
    }

    /**
     * Requiere un rol específico o lanza excepción
     *
     * @param string $role Nombre del rol requerido
     * @throws AuthorizationException
     */
    public function requireRole(string $role): void
    {
        $this->requireAuth();
        
        if (!$this->hasRole($role)) {
            $this->logger->logUnauthorizedAccess($this->currentUser['id'], "Required role: $role");
            throw new AuthorizationException("Rol requerido: $role");
        }
    }

    /**
     * Requiere un permiso específico o lanza excepción
     *
     * @param string $permission Nombre del permiso requerido
     * @param array $context Contexto adicional
     * @throws AuthorizationException
     */
    public function requirePermission(string $permission, array $context = []): void
    {
        $this->requireAuth();
        
        if (!$this->can($permission, $context)) {
            $this->logger->logUnauthorizedAccess($this->currentUser['id'], "Required permission: $permission", $context);
            throw new AuthorizationException("Permiso requerido: $permission");
        }
    }

    /**
     * Obtiene los roles del usuario actual
     *
     * @return array Lista de roles
     */
    public function roles(): array
    {
        if (!$this->check()) {
            return [];
        }

        return $this->getUserRoles($this->currentUser['id']);
    }

    /**
     * Obtiene los permisos del usuario actual
     *
     * @param array $context Contexto adicional
     * @return array Lista de permisos
     */
    public function permissions(array $context = []): array
    {
        if (!$this->check()) {
            return [];
        }

        return $this->getUserPermissions($this->currentUser['id'], $context);
    }

    /**
     * Renueva la sesión actual por seguridad
     *
     * @return bool True si se renovó correctamente
     */
    public function renewSession(): bool
    {
        try {
            if (!$this->currentSessionId) {
                return false;
            }

            $newSessionId = $this->sessionManager->renew($this->currentSessionId);
            
            if ($newSessionId) {
                $this->currentSessionId = $newSessionId;
                session_regenerate_id(true);
                return true;
            }

            return false;

        } catch (Exception $e) {
            $this->logger->logError('Session renewal error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Impersona otro usuario (solo para superadministradores)
     *
     * @param mixed $userId ID del usuario a impersonar
     * @return array Resultado de la operación
     */
    public function impersonate($userId): array
    {
        try {
            // Verificar que el usuario actual sea superadministrador
            if (!$this->hasRole('superadministrador') && !$this->hasRole('developer')) {
                return $this->failureResponse('No autorizado para impersonar usuarios');
            }

            $targetUser = $this->authProvider->findUser($userId);
            if (!$targetUser) {
                return $this->failureResponse('Usuario objetivo no encontrado');
            }

            // Guardar usuario original en sesión
            $_SESSION['impersonating'] = [
                'original_user_id' => $this->currentUser['id'],
                'original_session_id' => $this->currentSessionId,
                'started_at' => time()
            ];

            // Cambiar al usuario objetivo
            $this->currentUser = $targetUser;
            $_SESSION['usuario_id'] = $targetUser['id'];
            $_SESSION['usuario'] = $targetUser['username'] ?? $targetUser['email'];

            $this->logger->logImpersonation($this->currentUser['id'], $targetUser['id']);

            return $this->successResponse([
                'message' => 'Impersonación iniciada',
                'target_user' => $this->sanitizeUserData($targetUser)
            ]);

        } catch (Exception $e) {
            $this->logger->logError('Impersonation error: ' . $e->getMessage());
            return $this->failureResponse('Error al impersonar usuario');
        }
    }

    /**
     * Detiene la impersonación y regresa al usuario original
     *
     * @return array Resultado de la operación
     */
    public function stopImpersonation(): array
    {
        try {
            if (!isset($_SESSION['impersonating'])) {
                return $this->failureResponse('No hay impersonación activa');
            }

            $impersonatingData = $_SESSION['impersonating'];
            unset($_SESSION['impersonating']);

            // Restaurar usuario original
            $originalUser = $this->authProvider->findUser($impersonatingData['original_user_id']);
            if (!$originalUser) {
                return $this->failureResponse('Usuario original no encontrado');
            }

            $this->currentUser = $originalUser;
            $_SESSION['usuario_id'] = $originalUser['id'];
            $_SESSION['usuario'] = $originalUser['username'] ?? $originalUser['email'];

            $this->logger->logStopImpersonation($originalUser['id']);

            return $this->successResponse([
                'message' => 'Impersonación finalizada',
                'original_user' => $this->sanitizeUserData($originalUser)
            ]);

        } catch (Exception $e) {
            $this->logger->logError('Stop impersonation error: ' . $e->getMessage());
            return $this->failureResponse('Error al finalizar impersonación');
        }
    }

    // Métodos privados de utilidad

    private function getDefaultConfig(): array
    {
        return [
            'session_lifetime' => 3600, // 1 hora
            'max_login_attempts' => 5,
            'lockout_duration' => 900, // 15 minutos
            'require_strong_passwords' => true,
            'password_min_length' => 8,
            'session_regenerate_interval' => 300, // 5 minutos
        ];
    }

    private function validateCredentialsFormat(array $credentials): bool
    {
        return isset($credentials['username']) && 
               isset($credentials['password']) && 
               !empty($credentials['username']) && 
               !empty($credentials['password']);
    }

    private function isUserActive(array $user): bool
    {
        return isset($user['activo']) && $user['activo'] == 1;
    }

    private function isUserBlocked($userId): bool
    {
        // Implementar lógica de bloqueo por intentos fallidos
        // Por ahora retorna false
        return false;
    }

    private function setupLegacySession(array $user, string $sessionId): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['usuario_id'] = $user['id'];
        $_SESSION['usuario'] = $user['username'] ?? $user['email'];
        $_SESSION['rol'] = $user['role_name'] ?? $user['rol'] ?? '';
        $_SESSION['role_id'] = $user['role_id'] ?? '';
        $_SESSION['role_name'] = $user['role_name'] ?? '';
        $_SESSION['empresa_id'] = $user['empresa_id'] ?? null;
        $_SESSION['auth_session_id'] = $sessionId;
    }

    private function sanitizeUserData(array $user): array
    {
        // Remover campos sensibles
        $sanitized = $user;
        unset($sanitized['password']);
        unset($sanitized['password_hash']);
        unset($sanitized['remember_token']);
        
        return $sanitized;
    }

    private function calculateSessionExpiry(): string
    {
        return date('Y-m-d H:i:s', time() + $this->config['session_lifetime']);
    }

    private function getUserRoles($userId): array
    {
        try {
            return $this->roleManager->getUserRoles($userId);
        } catch (Exception $e) {
            $this->logger->logError('Error getting user roles: ' . $e->getMessage());
            return [];
        }
    }

    private function getUserPermissions($userId, array $context = []): array
    {
        try {
            return $this->permissionManager->getUserPermissions($userId, $context);
        } catch (Exception $e) {
            $this->logger->logError('Error getting user permissions: ' . $e->getMessage());
            return [];
        }
    }

    private function successResponse(array $data = []): array
    {
        return array_merge(['success' => true], $data);
    }

    private function failureResponse(string $message, array $data = []): array
    {
        return array_merge([
            'success' => false,
            'message' => $message
        ], $data);
    }
}

/**
 * Excepción para errores de autenticación
 */
class AuthenticationException extends Exception {}

/**
 * Excepción para errores de autorización
 */
class AuthorizationException extends Exception {}