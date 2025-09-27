<?php

namespace App\Core\Auth;

use PDO;
use Exception;

/**
 * Implementación de base de datos para gestión de permisos
 */
class DatabasePermissionManager implements PermissionManagerInterface
{
    private PDO $pdo;
    private AuthLogger $logger;
    private array $config;
    private array $permissionCache = [];

    public function __construct(PDO $pdo, AuthLogger $logger, array $config = [])
    {
        $this->pdo = $pdo;
        $this->logger = $logger;
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Otorga un permiso a un usuario
     */
    public function grantPermission($userId, string $permission, array $context = []): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT IGNORE INTO user_permissions (user_id, permission, context, granted_at, granted_by) 
                VALUES (?, ?, ?, NOW(), ?)
            ");
            
            $grantedBy = $_SESSION['usuario_id'] ?? null;
            $contextJson = !empty($context) ? json_encode($context) : null;
            
            $result = $stmt->execute([$userId, $permission, $contextJson, $grantedBy]);

            if ($result) {
                $this->logger->logPermissionChange($userId, 'grant', $permission, $context);
                $this->clearUserPermissionCache($userId);
            }

            return $result;

        } catch (Exception $e) {
            $this->logger->logError("Failed to grant permission '$permission' to user $userId", [
                'error' => $e->getMessage(),
                'context' => $context
            ]);
            return false;
        }
    }

    /**
     * Revoca un permiso de un usuario
     */
    public function revokePermission($userId, string $permission): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM user_permissions 
                WHERE user_id = ? AND permission = ?
            ");
            
            $result = $stmt->execute([$userId, $permission]);

            if ($result && $stmt->rowCount() > 0) {
                $this->logger->logPermissionChange($userId, 'revoke', $permission);
                $this->clearUserPermissionCache($userId);
                return true;
            }

            return false;

        } catch (Exception $e) {
            $this->logger->logError("Failed to revoke permission '$permission' from user $userId", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Verifica si un usuario tiene un permiso específico
     */
    public function userHasPermission($userId, string $permission, array $context = []): bool
    {
        // Obtener todos los permisos del usuario
        $userPermissions = $this->getUserPermissions($userId);
        
        // Verificar permiso directo
        if (in_array($permission, $userPermissions)) {
            return $this->checkPermissionContext($userId, $permission, $context);
        }

        // Verificar permisos con wildcards
        foreach ($userPermissions as $userPermission) {
            if ($this->matchesWildcard($userPermission, $permission)) {
                return $this->checkPermissionContext($userId, $userPermission, $context);
            }
        }

        return false;
    }

    /**
     * Obtiene todos los permisos de un usuario (directos y por roles)
     */
    public function getUserPermissions($userId): array
    {
        // Usar caché si está disponible
        if (isset($this->permissionCache[$userId])) {
            return $this->permissionCache[$userId];
        }

        try {
            // Permisos directos del usuario
            $directPermissions = $this->getDirectUserPermissions($userId);
            
            // Permisos por roles
            $rolePermissions = $this->getRoleBasedPermissions($userId);

            // Combinar y deduplicar
            $allPermissions = array_unique(array_merge($directPermissions, $rolePermissions));

            // Guardar en caché
            $this->permissionCache[$userId] = $allPermissions;

            return $allPermissions;

        } catch (Exception $e) {
            $this->logger->logError("Failed to get user permissions for user $userId", [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Obtiene permisos directos del usuario
     */
    private function getDirectUserPermissions($userId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT permission 
                FROM user_permissions 
                WHERE user_id = ? AND (expires_at IS NULL OR expires_at > NOW())
            ");
            
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);

        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Obtiene permisos basados en roles del usuario
     */
    private function getRoleBasedPermissions($userId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT rp.permission 
                FROM user_roles ur
                JOIN role_permissions rp ON ur.role_name = rp.role_name
                WHERE ur.user_id = ? 
                AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
            ");
            
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);

        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Verifica el contexto de un permiso
     */
    private function checkPermissionContext($userId, string $permission, array $requiredContext): bool
    {
        if (empty($requiredContext)) {
            return true; // No hay contexto requerido
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT context 
                FROM user_permissions 
                WHERE user_id = ? AND permission = ?
            ");
            
            $stmt->execute([$userId, $permission]);
            $contextData = $stmt->fetchColumn();

            if (!$contextData) {
                return true; // Sin contexto específico, se permite
            }

            $permissionContext = json_decode($contextData, true);
            if (!$permissionContext) {
                return true;
            }

            // Verificar que el contexto requerido coincide
            foreach ($requiredContext as $key => $value) {
                if (!isset($permissionContext[$key]) || $permissionContext[$key] !== $value) {
                    return false;
                }
            }

            return true;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Verifica si un permiso coincide con un patrón wildcard
     */
    private function matchesWildcard(string $pattern, string $permission): bool
    {
        // Convertir patrón a regex
        $regex = str_replace(['*', '?'], ['.*', '.'], $pattern);
        return preg_match("/^{$regex}$/", $permission) === 1;
    }

    /**
     * Crea un nuevo permiso
     */
    public function createPermission(string $permissionName, string $description = '', string $category = ''): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT IGNORE INTO permissions (name, description, category, created_at, created_by) 
                VALUES (?, ?, ?, NOW(), ?)
            ");
            
            $createdBy = $_SESSION['usuario_id'] ?? null;
            return $stmt->execute([$permissionName, $description, $category, $createdBy]);

        } catch (Exception $e) {
            $this->logger->logError("Failed to create permission '$permissionName'", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Elimina un permiso
     */
    public function deletePermission(string $permissionName): bool
    {
        try {
            $this->pdo->beginTransaction();

            // Eliminar asignaciones de usuarios
            $stmt = $this->pdo->prepare("DELETE FROM user_permissions WHERE permission = ?");
            $stmt->execute([$permissionName]);

            // Eliminar asignaciones de roles
            $stmt = $this->pdo->prepare("DELETE FROM role_permissions WHERE permission = ?");
            $stmt->execute([$permissionName]);

            // Eliminar el permiso
            $stmt = $this->pdo->prepare("DELETE FROM permissions WHERE name = ?");
            $result = $stmt->execute([$permissionName]);

            $this->pdo->commit();
            
            if ($result) {
                $this->logger->logPermissionChange(null, 'delete', $permissionName);
                // Limpiar toda la caché
                $this->permissionCache = [];
            }

            return $result;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->logger->logError("Failed to delete permission '$permissionName'", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtiene todos los permisos disponibles
     */
    public function getAllPermissions(): array
    {
        try {
            $stmt = $this->pdo->query("
                SELECT name, description, category, created_at,
                       (SELECT COUNT(*) FROM user_permissions up WHERE up.permission = p.name) as direct_assignments,
                       (SELECT COUNT(*) FROM role_permissions rp WHERE rp.permission = p.name) as role_assignments
                FROM permissions p 
                ORDER BY category, name
            ");
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            $this->logger->logError("Failed to get all permissions", [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Otorga un permiso temporal con expiración
     */
    public function grantTemporaryPermission($userId, string $permission, \DateTime $expiresAt, array $context = []): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO user_permissions (user_id, permission, context, granted_at, granted_by, expires_at) 
                VALUES (?, ?, ?, NOW(), ?, ?)
                ON DUPLICATE KEY UPDATE expires_at = VALUES(expires_at)
            ");
            
            $grantedBy = $_SESSION['usuario_id'] ?? null;
            $contextJson = !empty($context) ? json_encode($context) : null;
            
            $result = $stmt->execute([
                $userId, 
                $permission, 
                $contextJson, 
                $grantedBy, 
                $expiresAt->format('Y-m-d H:i:s')
            ]);

            if ($result) {
                $this->logger->logPermissionChange($userId, 'grant_temporary', $permission, $context);
                $this->clearUserPermissionCache($userId);
            }

            return $result;

        } catch (Exception $e) {
            $this->logger->logError("Failed to grant temporary permission '$permission' to user $userId", [
                'error' => $e->getMessage(),
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                'context' => $context
            ]);
            return false;
        }
    }

    /**
     * Limpia permisos expirados
     */
    public function cleanupExpiredPermissions(): int
    {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM user_permissions 
                WHERE expires_at IS NOT NULL AND expires_at <= NOW()
            ");
            
            $stmt->execute();
            $deletedCount = $stmt->rowCount();

            if ($deletedCount > 0) {
                $this->logger->logSecurityEvent('EXPIRED_PERMISSIONS_CLEANUP', [
                    'deleted_count' => $deletedCount
                ]);
                
                // Limpiar toda la caché
                $this->permissionCache = [];
            }

            return $deletedCount;

        } catch (Exception $e) {
            $this->logger->logError("Failed to cleanup expired permissions", [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Verifica múltiples permisos de una vez
     */
    public function userHasAnyPermission($userId, array $permissions, array $context = []): bool
    {
        foreach ($permissions as $permission) {
            if ($this->userHasPermission($userId, $permission, $context)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verifica que el usuario tenga todos los permisos
     */
    public function userHasAllPermissions($userId, array $permissions, array $context = []): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->userHasPermission($userId, $permission, $context)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Obtiene permisos por categoría
     */
    public function getPermissionsByCategory(string $category): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT name, description 
                FROM permissions 
                WHERE category = ? 
                ORDER BY name
            ");
            
            $stmt->execute([$category]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Limpia la caché de permisos de un usuario
     */
    private function clearUserPermissionCache($userId): void
    {
        unset($this->permissionCache[$userId]);
    }

    /**
     * Configuración por defecto
     */
    private function getDefaultConfig(): array
    {
        return [
            'cache_ttl' => 300, // 5 minutos
            'enable_wildcards' => true,
            'auto_cleanup_expired' => true,
            'strict_context_matching' => false
        ];
    }

    /**
     * Obtiene estadísticas de permisos
     */
    public function getPermissionStats(): array
    {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    (SELECT COUNT(*) FROM permissions) as total_permissions,
                    (SELECT COUNT(*) FROM user_permissions) as direct_assignments,
                    (SELECT COUNT(*) FROM role_permissions) as role_assignments,
                    (SELECT COUNT(DISTINCT user_id) FROM user_permissions) as users_with_direct_permissions,
                    (SELECT COUNT(*) FROM user_permissions WHERE expires_at IS NOT NULL AND expires_at > NOW()) as temporary_permissions
            ");
            
            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return [];
        }
    }
}