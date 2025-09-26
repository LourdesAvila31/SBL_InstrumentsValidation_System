<?php
/**
 * Sistema de permisos avanzado para el rol Developer con privilegios de Superadministrador
 * 
 * Este módulo implementa un sistema completo de Role-Based Access Control (RBAC)
 * que permite al usuario con rol 'developer' tener acceso total a todas las 
 * funcionalidades del sistema, incluida la gestión de usuarios, control de 
 * configuraciones avanzadas, supervisión de actividades operativas y acceso 
 * a todos los registros del sistema.
 */

declare(strict_types=1);

require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/db.php';

/**
 * Clase para gestionar permisos específicos de Developer con privilegios de Superadministrador
 */
class DeveloperSuperadminPermissions
{
    private mysqli $conn;
    
    public function __construct(?mysqli $connection = null)
    {
        global $conn;
        $this->conn = $connection ?? $conn ?? DatabaseManager::getConnection();
    }

    /**
     * Verifica si un usuario tiene rol de developer con permisos de superadministrador
     */
    public function isDeveloperSuperadmin(?int $userId = null): bool
    {
        if ($userId === null) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $userId = $_SESSION['usuario_id'] ?? null;
            if (!$userId) {
                return false;
            }
        }

        $roleAlias = session_role_alias();
        return $roleAlias === 'developer';
    }

    /**
     * Obtiene todos los permisos disponibles en el sistema
     */
    public function getAllSystemPermissions(): array
    {
        $stmt = $this->conn->prepare("SELECT nombre, descripcion FROM permissions ORDER BY nombre ASC");
        if (!$stmt) {
            return [];
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $permissions = [];

        while ($row = $result->fetch_assoc()) {
            $permissions[] = [
                'name' => $row['nombre'],
                'description' => $row['descripcion']
            ];
        }

        $stmt->close();
        return $permissions;
    }

    /**
     * Asigna automáticamente todos los permisos del sistema al rol developer
     */
    public function assignAllPermissionsToDeveloper(): bool
    {
        try {
            // Obtener el ID del rol Developer
            $stmt = $this->conn->prepare("SELECT id FROM roles WHERE nombre = 'Developer' LIMIT 1");
            if (!$stmt) {
                return false;
            }

            $stmt->execute();
            $result = $stmt->get_result();
            $role = $result->fetch_assoc();
            $stmt->close();

            if (!$role) {
                return false;
            }

            $developerRoleId = (int)$role['id'];

            // Asignar todos los permisos al rol Developer
            $assignStmt = $this->conn->prepare("
                INSERT IGNORE INTO role_permissions (role_id, permission_id)
                SELECT ?, p.id FROM permissions p
            ");

            if (!$assignStmt) {
                return false;
            }

            $assignStmt->bind_param('i', $developerRoleId);
            $success = $assignStmt->execute();
            $assignStmt->close();

            if ($success) {
                $this->logDeveloperAction('PERMISSIONS_ASSIGNED', 'All system permissions assigned to Developer role');
            }

            return $success;
        } catch (Exception $e) {
            error_log("Error assigning permissions to developer: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica si el developer tiene acceso a una sección específica del sistema
     */
    public function hasAccess(string $section, string $action = 'read'): bool
    {
        if (!$this->isDeveloperSuperadmin()) {
            return false;
        }

        // Los developers con privilegios de superadministrador tienen acceso total
        $allowedSections = [
            'usuarios' => ['create', 'read', 'update', 'delete', 'manage_roles'],
            'configuracion' => ['read', 'update', 'manage_system'],
            'auditoria' => ['read', 'create', 'export', 'manage'],
            'reportes' => ['read', 'create', 'export', 'manage'],
            'documentos' => ['read', 'create', 'update', 'delete', 'publish'],
            'calibraciones' => ['read', 'create', 'update', 'delete', 'approve'],
            'instrumentos' => ['read', 'create', 'update', 'delete', 'manage'],
            'incidentes' => ['read', 'create', 'update', 'resolve', 'manage'],
            'alertas' => ['read', 'create', 'configure', 'manage'],
            'monitoreo' => ['read', 'access', 'configure', 'manage'],
            'sistema' => ['read', 'configure', 'maintain', 'backup'],
            'database' => ['read', 'query', 'backup', 'maintain']
        ];

        if (!isset($allowedSections[$section])) {
            return true; // Acceso total por defecto para desarrolladores
        }

        return in_array($action, $allowedSections[$section], true) || $action === 'read';
    }

    /**
     * Registra acciones realizadas por el developer para auditoría
     */
    public function logDeveloperAction(string $action, string $description, array $context = []): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = $_SESSION['usuario_id'] ?? null;
        if (!$userId) {
            return false;
        }

        $contextJson = json_encode(array_merge($context, [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]));

        $stmt = $this->conn->prepare("
            INSERT INTO auditoria_logs (usuario_id, accion, descripcion, contexto, fecha_creacion)
            VALUES (?, ?, ?, ?, NOW())
        ");

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('isss', $userId, $action, $description, $contextJson);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    /**
     * Obtiene estadísticas del sistema para el dashboard del developer
     */
    public function getSystemStats(): array
    {
        $stats = [];

        try {
            // Total de usuarios
            $result = $this->conn->query("SELECT COUNT(*) as total FROM usuarios");
            $stats['total_usuarios'] = $result ? $result->fetch_assoc()['total'] : 0;

            // Total de empresas
            $result = $this->conn->query("SELECT COUNT(*) as total FROM empresas");
            $stats['total_empresas'] = $result ? $result->fetch_assoc()['total'] : 0;

            // Total de instrumentos
            $result = $this->conn->query("SELECT COUNT(*) as total FROM instrumentos");
            $stats['total_instrumentos'] = $result ? $result->fetch_assoc()['total'] : 0;

            // Calibraciones pendientes
            $result = $this->conn->query("SELECT COUNT(*) as total FROM calibraciones WHERE estado = 'pendiente'");
            $stats['calibraciones_pendientes'] = $result ? $result->fetch_assoc()['total'] : 0;

            // Incidentes abiertos
            $result = $this->conn->query("SELECT COUNT(*) as total FROM incidentes WHERE estado = 'abierto'");
            $stats['incidentes_abiertos'] = $result ? $result->fetch_assoc()['total'] : 0;

            // Alertas activas
            $result = $this->conn->query("SELECT COUNT(*) as total FROM alertas WHERE activa = 1");
            $stats['alertas_activas'] = $result ? $result->fetch_assoc()['total'] : 0;

        } catch (Exception $e) {
            error_log("Error getting system stats: " . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Obtiene logs de actividad reciente del sistema
     */
    public function getRecentActivity(int $limit = 50): array
    {
        $stmt = $this->conn->prepare("
            SELECT al.*, u.nombre as usuario_nombre
            FROM auditoria_logs al
            LEFT JOIN usuarios u ON al.usuario_id = u.id
            ORDER BY al.fecha_creacion DESC
            LIMIT ?
        ");

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $activities = [];
        while ($row = $result->fetch_assoc()) {
            $activities[] = $row;
        }

        $stmt->close();
        return $activities;
    }

    /**
     * Configura alertas automáticas para el sistema
     */
    public function configureSystemAlerts(array $alertConfig): bool
    {
        try {
            foreach ($alertConfig as $alert) {
                $stmt = $this->conn->prepare("
                    INSERT INTO alertas_configuracion (
                        nombre, descripcion, condicion, activa, 
                        nivel_criticidad, notificar_desarrollador,
                        created_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE
                    descripcion = VALUES(descripcion),
                    condicion = VALUES(condicion),
                    activa = VALUES(activa),
                    nivel_criticidad = VALUES(nivel_criticidad),
                    notificar_desarrollador = VALUES(notificar_desarrollador)
                ");

                if ($stmt) {
                    $userId = $_SESSION['usuario_id'] ?? null;
                    $stmt->bind_param('sssiiii', 
                        $alert['nombre'],
                        $alert['descripcion'],
                        $alert['condicion'],
                        $alert['activa'],
                        $alert['nivel_criticidad'],
                        $alert['notificar_desarrollador'],
                        $userId
                    );
                    $stmt->execute();
                    $stmt->close();
                }
            }

            $this->logDeveloperAction('ALERTS_CONFIGURED', 'System alerts configuration updated', $alertConfig);
            return true;

        } catch (Exception $e) {
            error_log("Error configuring system alerts: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene configuración actual del sistema
     */
    public function getSystemConfiguration(): array
    {
        $config = [];

        try {
            $result = $this->conn->query("
                SELECT clave, valor, descripcion 
                FROM configuracion_sistema 
                ORDER BY categoria, clave
            ");

            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $config[$row['clave']] = [
                        'valor' => $row['valor'],
                        'descripcion' => $row['descripcion']
                    ];
                }
            }
        } catch (Exception $e) {
            error_log("Error getting system configuration: " . $e->getMessage());
        }

        return $config;
    }

    /**
     * Actualiza configuración del sistema
     */
    public function updateSystemConfiguration(array $config): bool
    {
        try {
            $this->conn->begin_transaction();

            foreach ($config as $key => $value) {
                $stmt = $this->conn->prepare("
                    UPDATE configuracion_sistema 
                    SET valor = ?, updated_at = NOW(), updated_by = ?
                    WHERE clave = ?
                ");

                if ($stmt) {
                    $userId = $_SESSION['usuario_id'] ?? null;
                    $stmt->bind_param('sis', $value, $userId, $key);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            $this->conn->commit();
            $this->logDeveloperAction('SYSTEM_CONFIG_UPDATED', 'System configuration updated', $config);
            return true;

        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Error updating system configuration: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Función helper para verificar si el usuario actual es developer con permisos de superadministrador
 */
function is_developer_superadmin(): bool
{
    return session_role_alias() === 'developer';
}

/**
 * Función helper para registrar acciones del developer
 */
function log_developer_action(string $action, string $description, array $context = []): bool
{
    $devPerms = new DeveloperSuperadminPermissions();
    return $devPerms->logDeveloperAction($action, $description, $context);
}