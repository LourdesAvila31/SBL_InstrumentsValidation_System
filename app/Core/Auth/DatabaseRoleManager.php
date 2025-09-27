<?php

namespace App\Core\Auth;

use PDO;
use Exception;

/**
 * Implementación de base de datos para gestión de roles
 */
class DatabaseRoleManager implements RoleManagerInterface
{
    private PDO $pdo;
    private AuthLogger $logger;
    private array $config;
    private array $roleCache = [];

    public function __construct(PDO $pdo, AuthLogger $logger, array $config = [])
    {
        $this->pdo = $pdo;
        $this->logger = $logger;
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Asigna un rol a un usuario
     */
    public function assignRole($userId, string $role): bool
    {
        try {
            // Verificar que el rol existe
            if (!$this->roleExists($role)) {
                throw new Exception("Role '$role' does not exist");
            }

            // Verificar que el usuario no tiene ya este rol
            if ($this->userHasRole($userId, $role)) {
                return true; // Ya tiene el rol
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO user_roles (user_id, role_name, assigned_at, assigned_by) 
                VALUES (?, ?, NOW(), ?)
            ");
            
            $assignedBy = $_SESSION['usuario_id'] ?? null;
            $result = $stmt->execute([$userId, $role, $assignedBy]);

            if ($result) {
                $this->logger->logRoleChange($userId, 'assign', $role);
                $this->clearUserRoleCache($userId);
            }

            return $result;

        } catch (Exception $e) {
            $this->logger->logError("Failed to assign role '$role' to user $userId", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Revoca un rol de un usuario
     */
    public function revokeRole($userId, string $role): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM user_roles 
                WHERE user_id = ? AND role_name = ?
            ");
            
            $result = $stmt->execute([$userId, $role]);

            if ($result && $stmt->rowCount() > 0) {
                $this->logger->logRoleChange($userId, 'revoke', $role);
                $this->clearUserRoleCache($userId);
                return true;
            }

            return false;

        } catch (Exception $e) {
            $this->logger->logError("Failed to revoke role '$role' from user $userId", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Verifica si un usuario tiene un rol específico
     */
    public function userHasRole($userId, string $role): bool
    {
        $userRoles = $this->getUserRoles($userId);
        return in_array($role, $userRoles);
    }

    /**
     * Obtiene todos los roles de un usuario
     */
    public function getUserRoles($userId): array
    {
        // Usar caché si está disponible
        if (isset($this->roleCache[$userId])) {
            return $this->roleCache[$userId];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT role_name 
                FROM user_roles 
                WHERE user_id = ? AND (expires_at IS NULL OR expires_at > NOW())
            ");
            
            $stmt->execute([$userId]);
            $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Agregar roles heredados
            $inheritedRoles = $this->getInheritedRoles($roles);
            $allRoles = array_unique(array_merge($roles, $inheritedRoles));

            // Guardar en caché
            $this->roleCache[$userId] = $allRoles;

            return $allRoles;

        } catch (Exception $e) {
            $this->logger->logError("Failed to get user roles for user $userId", [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Obtiene roles heredados basados en jerarquía
     */
    private function getInheritedRoles(array $userRoles): array
    {
        $inherited = [];
        
        foreach ($userRoles as $role) {
            $inherited = array_merge($inherited, $this->getRoleHierarchy($role));
        }

        return array_unique($inherited);
    }

    /**
     * Obtiene la jerarquía de un rol
     */
    private function getRoleHierarchy(string $role): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT parent_role 
                FROM role_hierarchy 
                WHERE child_role = ?
            ");
            
            $stmt->execute([$role]);
            $parents = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $hierarchy = [];
            foreach ($parents as $parent) {
                $hierarchy[] = $parent;
                $hierarchy = array_merge($hierarchy, $this->getRoleHierarchy($parent));
            }

            return $hierarchy;

        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Crea un nuevo rol
     */
    public function createRole(string $roleName, string $description = '', array $permissions = []): bool
    {
        try {
            $this->pdo->beginTransaction();

            // Crear el rol
            $stmt = $this->pdo->prepare("
                INSERT INTO roles (name, description, created_at, created_by) 
                VALUES (?, ?, NOW(), ?)
            ");
            
            $createdBy = $_SESSION['usuario_id'] ?? null;
            $stmt->execute([$roleName, $description, $createdBy]);

            // Asignar permisos si se proporcionan
            if (!empty($permissions)) {
                foreach ($permissions as $permission) {
                    $this->assignPermissionToRole($roleName, $permission);
                }
            }

            $this->pdo->commit();
            
            $this->logger->logRoleChange(null, 'create', $roleName);
            return true;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->logger->logError("Failed to create role '$roleName'", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Elimina un rol
     */
    public function deleteRole(string $roleName): bool
    {
        try {
            $this->pdo->beginTransaction();

            // Verificar que no hay usuarios con este rol
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM user_roles WHERE role_name = ?");
            $stmt->execute([$roleName]);
            
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Cannot delete role '$roleName': users are assigned to this role");
            }

            // Eliminar permisos del rol
            $stmt = $this->pdo->prepare("DELETE FROM role_permissions WHERE role_name = ?");
            $stmt->execute([$roleName]);

            // Eliminar el rol
            $stmt = $this->pdo->prepare("DELETE FROM roles WHERE name = ?");
            $result = $stmt->execute([$roleName]);

            $this->pdo->commit();
            
            if ($result) {
                $this->logger->logRoleChange(null, 'delete', $roleName);
            }

            return $result;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->logger->logError("Failed to delete role '$roleName'", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtiene todos los roles disponibles
     */
    public function getAllRoles(): array
    {
        try {
            $stmt = $this->pdo->query("
                SELECT name, description, created_at, 
                       (SELECT COUNT(*) FROM user_roles ur WHERE ur.role_name = r.name) as user_count
                FROM roles r 
                ORDER BY name
            ");
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            $this->logger->logError("Failed to get all roles", [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Asigna un permiso a un rol
     */
    public function assignPermissionToRole(string $roleName, string $permission): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT IGNORE INTO role_permissions (role_name, permission, assigned_at) 
                VALUES (?, ?, NOW())
            ");
            
            return $stmt->execute([$roleName, $permission]);

        } catch (Exception $e) {
            $this->logger->logError("Failed to assign permission '$permission' to role '$roleName'", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Revoca un permiso de un rol
     */
    public function revokePermissionFromRole(string $roleName, string $permission): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM role_permissions 
                WHERE role_name = ? AND permission = ?
            ");
            
            return $stmt->execute([$roleName, $permission]);

        } catch (Exception $e) {
            $this->logger->logError("Failed to revoke permission '$permission' from role '$roleName'", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtiene todos los permisos de un rol
     */
    public function getRolePermissions(string $roleName): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT permission 
                FROM role_permissions 
                WHERE role_name = ?
            ");
            
            $stmt->execute([$roleName]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);

        } catch (Exception $e) {
            $this->logger->logError("Failed to get permissions for role '$roleName'", [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Verifica si un rol existe
     */
    public function roleExists(string $roleName): bool
    {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM roles WHERE name = ?");
            $stmt->execute([$roleName]);
            return $stmt->fetchColumn() > 0;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Asigna un rol temporal con expiración
     */
    public function assignTemporaryRole($userId, string $role, \DateTime $expiresAt): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO user_roles (user_id, role_name, assigned_at, assigned_by, expires_at) 
                VALUES (?, ?, NOW(), ?, ?)
                ON DUPLICATE KEY UPDATE expires_at = VALUES(expires_at)
            ");
            
            $assignedBy = $_SESSION['usuario_id'] ?? null;
            $result = $stmt->execute([$userId, $role, $assignedBy, $expiresAt->format('Y-m-d H:i:s')]);

            if ($result) {
                $this->logger->logRoleChange($userId, 'assign_temporary', $role);
                $this->clearUserRoleCache($userId);
            }

            return $result;

        } catch (Exception $e) {
            $this->logger->logError("Failed to assign temporary role '$role' to user $userId", [
                'error' => $e->getMessage(),
                'expires_at' => $expiresAt->format('Y-m-d H:i:s')
            ]);
            return false;
        }
    }

    /**
     * Limpia roles expirados
     */
    public function cleanupExpiredRoles(): int
    {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM user_roles 
                WHERE expires_at IS NOT NULL AND expires_at <= NOW()
            ");
            
            $stmt->execute();
            $deletedCount = $stmt->rowCount();

            if ($deletedCount > 0) {
                $this->logger->logSecurityEvent('EXPIRED_ROLES_CLEANUP', [
                    'deleted_count' => $deletedCount
                ]);
                
                // Limpiar toda la caché de roles
                $this->roleCache = [];
            }

            return $deletedCount;

        } catch (Exception $e) {
            $this->logger->logError("Failed to cleanup expired roles", [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Limpia la caché de roles de un usuario
     */
    private function clearUserRoleCache($userId): void
    {
        unset($this->roleCache[$userId]);
    }

    /**
     * Configuración por defecto
     */
    private function getDefaultConfig(): array
    {
        return [
            'cache_ttl' => 300, // 5 minutos
            'enable_hierarchy' => true,
            'auto_cleanup_expired' => true
        ];
    }

    /**
     * Obtiene estadísticas de roles
     */
    public function getRoleStats(): array
    {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    (SELECT COUNT(*) FROM roles) as total_roles,
                    (SELECT COUNT(*) FROM user_roles) as total_assignments,
                    (SELECT COUNT(DISTINCT user_id) FROM user_roles) as users_with_roles,
                    (SELECT COUNT(*) FROM user_roles WHERE expires_at IS NOT NULL AND expires_at > NOW()) as temporary_assignments
            ");
            
            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return [];
        }
    }
}