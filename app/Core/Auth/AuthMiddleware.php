<?php

namespace App\Core\Auth;

use Exception;

/**
 * Middleware para verificación de autenticación y autorización
 */
class AuthMiddleware
{
    private AuthManager $authManager;
    private array $config;

    public function __construct(AuthManager $authManager, array $config = [])
    {
        $this->authManager = $authManager;
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Middleware principal de autenticación
     */
    public function authenticate(): callable
    {
        return function($request, $response, $next) {
            try {
                // Verificar sesión activa
                if (!$this->authManager->isAuthenticated()) {
                    return $this->handleUnauthenticated($request, $response);
                }

                // Actualizar actividad de sesión
                $this->authManager->renewCurrentSession();

                // Continuar con la siguiente capa
                return $next($request, $response);

            } catch (Exception $e) {
                return $this->handleError($request, $response, $e);
            }
        };
    }

    /**
     * Middleware de autorización por permisos
     */
    public function requirePermission(string $permission, array $context = []): callable
    {
        return function($request, $response, $next) use ($permission, $context) {
            try {
                // Verificar autenticación primero
                if (!$this->authManager->isAuthenticated()) {
                    return $this->handleUnauthenticated($request, $response);
                }

                // Verificar permiso específico
                if (!$this->authManager->hasPermission($permission, $context)) {
                    return $this->handleUnauthorized($request, $response, $permission);
                }

                return $next($request, $response);

            } catch (Exception $e) {
                return $this->handleError($request, $response, $e);
            }
        };
    }

    /**
     * Middleware de autorización por rol
     */
    public function requireRole(string $role): callable
    {
        return function($request, $response, $next) use ($role) {
            try {
                if (!$this->authManager->isAuthenticated()) {
                    return $this->handleUnauthenticated($request, $response);
                }

                if (!$this->authManager->hasRole($role)) {
                    return $this->handleUnauthorized($request, $response, "role:$role");
                }

                return $next($request, $response);

            } catch (Exception $e) {
                return $this->handleError($request, $response, $e);
            }
        };
    }

    /**
     * Middleware para verificar múltiples permisos (cualquiera)
     */
    public function requireAnyPermission(array $permissions, array $context = []): callable
    {
        return function($request, $response, $next) use ($permissions, $context) {
            try {
                if (!$this->authManager->isAuthenticated()) {
                    return $this->handleUnauthenticated($request, $response);
                }

                $hasAnyPermission = false;
                foreach ($permissions as $permission) {
                    if ($this->authManager->hasPermission($permission, $context)) {
                        $hasAnyPermission = true;
                        break;
                    }
                }

                if (!$hasAnyPermission) {
                    return $this->handleUnauthorized($request, $response, implode('|', $permissions));
                }

                return $next($request, $response);

            } catch (Exception $e) {
                return $this->handleError($request, $response, $e);
            }
        };
    }

    /**
     * Middleware para verificar múltiples permisos (todos)
     */
    public function requireAllPermissions(array $permissions, array $context = []): callable
    {
        return function($request, $response, $next) use ($permissions, $context) {
            try {
                if (!$this->authManager->isAuthenticated()) {
                    return $this->handleUnauthenticated($request, $response);
                }

                foreach ($permissions as $permission) {
                    if (!$this->authManager->hasPermission($permission, $context)) {
                        return $this->handleUnauthorized($request, $response, $permission);
                    }
                }

                return $next($request, $response);

            } catch (Exception $e) {
                return $this->handleError($request, $response, $e);
            }
        };
    }

    /**
     * Middleware condicional basado en callback
     */
    public function requireCondition(callable $condition, string $failureReason = 'access_denied'): callable
    {
        return function($request, $response, $next) use ($condition, $failureReason) {
            try {
                if (!$this->authManager->isAuthenticated()) {
                    return $this->handleUnauthenticated($request, $response);
                }

                $user = $this->authManager->getCurrentUser();
                if (!$condition($user, $request)) {
                    return $this->handleUnauthorized($request, $response, $failureReason);
                }

                return $next($request, $response);

            } catch (Exception $e) {
                return $this->handleError($request, $response, $e);
            }
        };
    }

    /**
     * Middleware para limitar acceso por IP
     */
    public function requireIpWhitelist(array $allowedIps): callable
    {
        return function($request, $response, $next) use ($allowedIps) {
            $clientIp = $this->getClientIp($request);
            
            if (!$this->isIpAllowed($clientIp, $allowedIps)) {
                return $this->handleIpBlocked($request, $response, $clientIp);
            }

            return $next($request, $response);
        };
    }

    /**
     * Middleware de rate limiting por usuario
     */
    public function rateLimit(int $maxRequests, int $windowSeconds): callable
    {
        return function($request, $response, $next) use ($maxRequests, $windowSeconds) {
            try {
                if (!$this->authManager->isAuthenticated()) {
                    return $this->handleUnauthenticated($request, $response);
                }

                $userId = $this->authManager->getCurrentUser()['id'];
                
                if ($this->isRateLimited($userId, $maxRequests, $windowSeconds)) {
                    return $this->handleRateLimited($request, $response);
                }

                return $next($request, $response);

            } catch (Exception $e) {
                return $this->handleError($request, $response, $e);
            }
        };
    }

    /**
     * Middleware para verificar empresa/tenant
     */
    public function requireCompany($companyId = null): callable
    {
        return function($request, $response, $next) use ($companyId) {
            try {
                if (!$this->authManager->isAuthenticated()) {
                    return $this->handleUnauthenticated($request, $response);
                }

                $user = $this->authManager->getCurrentUser();
                $userCompanyId = $user['empresa_id'] ?? null;

                // Si no se especifica empresa, usar la del usuario
                $requiredCompanyId = $companyId ?? $userCompanyId;

                if ($userCompanyId !== $requiredCompanyId) {
                    return $this->handleUnauthorized($request, $response, 'company_mismatch');
                }

                return $next($request, $response);

            } catch (Exception $e) {
                return $this->handleError($request, $response, $e);
            }
        };
    }

    /**
     * Maneja peticiones no autenticadas
     */
    private function handleUnauthenticated($request, $response): array
    {
        $this->authManager->logout(); // Limpiar sesión inválida

        if ($this->isApiRequest($request)) {
            return [
                'status' => 401,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode([
                    'error' => 'Unauthorized',
                    'message' => 'Authentication required',
                    'code' => 'AUTH_REQUIRED'
                ])
            ];
        }

        // Redirigir a login para peticiones web
        return [
            'status' => 302,
            'headers' => ['Location' => $this->config['login_url']],
            'body' => ''
        ];
    }

    /**
     * Maneja acceso no autorizado
     */
    private function handleUnauthorized($request, $response, string $resource): array
    {
        // Log del intento no autorizado
        $user = $this->authManager->getCurrentUser();
        $this->authManager->getLogger()->logUnauthorizedAccess(
            $user['id'] ?? null, 
            $resource,
            [
                'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
                'method' => $_SERVER['REQUEST_METHOD'] ?? ''
            ]
        );

        if ($this->isApiRequest($request)) {
            return [
                'status' => 403,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode([
                    'error' => 'Forbidden',
                    'message' => 'Access denied to resource: ' . $resource,
                    'code' => 'ACCESS_DENIED'
                ])
            ];
        }

        // Página de acceso denegado para peticiones web
        return [
            'status' => 403,
            'headers' => ['Content-Type' => 'text/html'],
            'body' => $this->renderAccessDeniedPage($resource)
        ];
    }

    /**
     * Maneja errores del middleware
     */
    private function handleError($request, $response, Exception $e): array
    {
        $this->authManager->getLogger()->logError("Middleware error: " . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);

        if ($this->isApiRequest($request)) {
            return [
                'status' => 500,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode([
                    'error' => 'Internal Server Error',
                    'message' => 'Authentication middleware error',
                    'code' => 'MIDDLEWARE_ERROR'
                ])
            ];
        }

        return [
            'status' => 500,
            'headers' => ['Content-Type' => 'text/html'],
            'body' => '<h1>Authentication Error</h1><p>Please try again later.</p>'
        ];
    }

    /**
     * Maneja IPs bloqueadas
     */
    private function handleIpBlocked($request, $response, string $clientIp): array
    {
        $this->authManager->getLogger()->logSecurityEvent('IP_BLOCKED', [
            'ip_address' => $clientIp,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? ''
        ]);

        return [
            'status' => 403,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'error' => 'Forbidden',
                'message' => 'Access denied from this IP address',
                'code' => 'IP_BLOCKED'
            ])
        ];
    }

    /**
     * Maneja rate limiting
     */
    private function handleRateLimited($request, $response): array
    {
        return [
            'status' => 429,
            'headers' => [
                'Content-Type' => 'application/json',
                'Retry-After' => '60'
            ],
            'body' => json_encode([
                'error' => 'Too Many Requests',
                'message' => 'Rate limit exceeded',
                'code' => 'RATE_LIMITED'
            ])
        ];
    }

    /**
     * Verifica si es una petición API
     */
    private function isApiRequest($request): bool
    {
        $contentType = $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        return strpos($contentType, 'application/json') !== false ||
               strpos($accept, 'application/json') !== false ||
               strpos($uri, '/api/') !== false;
    }

    /**
     * Obtiene la IP del cliente
     */
    private function getClientIp($request): string
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Verifica si una IP está permitida
     */
    private function isIpAllowed(string $clientIp, array $allowedIps): bool
    {
        foreach ($allowedIps as $allowedIp) {
            if ($this->matchIpPattern($clientIp, $allowedIp)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verifica si una IP coincide con un patrón
     */
    private function matchIpPattern(string $ip, string $pattern): bool
    {
        // IP exacta
        if ($ip === $pattern) {
            return true;
        }

        // Rango CIDR
        if (strpos($pattern, '/') !== false) {
            list($network, $mask) = explode('/', $pattern);
            $networkLong = ip2long($network);
            $ipLong = ip2long($ip);
            $maskLong = ~((1 << (32 - $mask)) - 1);
            
            return ($ipLong & $maskLong) === ($networkLong & $maskLong);
        }

        // Wildcard
        if (strpos($pattern, '*') !== false) {
            $pattern = str_replace('*', '.*', preg_quote($pattern, '/'));
            return preg_match('/^' . $pattern . '$/', $ip) === 1;
        }

        return false;
    }

    /**
     * Verifica rate limiting
     */
    private function isRateLimited($userId, int $maxRequests, int $windowSeconds): bool
    {
        // Implementación simple con archivo (en producción usar Redis)
        $cacheFile = sys_get_temp_dir() . "/rate_limit_$userId.cache";
        
        if (!file_exists($cacheFile)) {
            file_put_contents($cacheFile, json_encode([
                'count' => 1,
                'window_start' => time()
            ]));
            return false;
        }

        $data = json_decode(file_get_contents($cacheFile), true);
        $now = time();

        // Reset ventana si ha pasado el tiempo
        if ($now - $data['window_start'] > $windowSeconds) {
            $data = ['count' => 1, 'window_start' => $now];
            file_put_contents($cacheFile, json_encode($data));
            return false;
        }

        // Incrementar contador
        $data['count']++;
        file_put_contents($cacheFile, json_encode($data));

        return $data['count'] > $maxRequests;
    }

    /**
     * Renderiza página de acceso denegado
     */
    private function renderAccessDeniedPage(string $resource): string
    {
        return '
<!DOCTYPE html>
<html>
<head>
    <title>Acceso Denegado</title>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; text-align: center; margin-top: 100px; }
        .error { color: #d32f2f; }
        .resource { color: #666; font-style: italic; }
    </style>
</head>
<body>
    <h1 class="error">Acceso Denegado</h1>
    <p>No tienes permisos para acceder a este recurso.</p>
    <p class="resource">Recurso: ' . htmlspecialchars($resource) . '</p>
    <a href="/">Volver al inicio</a>
</body>
</html>';
    }

    /**
     * Configuración por defecto
     */
    private function getDefaultConfig(): array
    {
        return [
            'login_url' => '/login.php',
            'cache_permissions' => true,
            'log_access_attempts' => true
        ];
    }
}