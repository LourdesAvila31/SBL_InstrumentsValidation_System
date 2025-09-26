<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/Core/permissions.php';
require_once dirname(__DIR__, 2) . '/Core/db.php';

/**
 * Módulo de autenticación y autorización específico para desarrolladores
 * Implementa RBAC (Role-Based Access Control) para la sección privada del sistema
 */
class DeveloperAuth
{
    private mysqli $conn;
    
    public function __construct(?mysqli $connection = null)
    {
        global $conn;
        $this->conn = $connection ?? $conn ?? DatabaseManager::getConnection();
    }

    /**
     * Verifica si el usuario actual tiene rol de developer
     */
    public function isDeveloper(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $roleAlias = session_role_alias();
        $roleId = $_SESSION['role_id'] ?? null;
        $roleName = $_SESSION['role_name'] ?? '';

        // Verificar por alias de rol
        if ($roleAlias === 'developer' || $roleAlias === 'sistemas') {
            return true;
        }

        // Verificar por ID de rol específico (900, 901, 950 para developers)
        if (is_numeric($roleId) && in_array((int)$roleId, [900, 901, 950], true)) {
            return true;
        }

        // Verificar por nombre de rol
        if (stripos($roleName, 'developer') !== false || stripos($roleName, 'dev') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Verifica si el usuario tiene acceso a funciones específicas de developer
     */
    public function hasPrivateAccess(string $section = ''): bool
    {
        if (!$this->isDeveloper()) {
            return false;
        }

        $requiredPermissions = [
            'dashboard' => ['auditoria_leer', 'reportes_leer'],
            'incidents' => ['auditoria_crear', 'auditoria_leer'],
            'changes' => ['auditoria_crear', 'configuracion_actualizar'],
            'sop' => ['calidad_documentos_leer', 'calidad_documentos_crear'],
            'monitoring' => ['auditoria_leer', 'reportes_leer'],
            'vendors' => ['configuracion_leer', 'configuracion_actualizar'],
            'alerts' => ['notificaciones_leer', 'notificaciones_crear']
        ];

        if (empty($section)) {
            return true; // Acceso general de developer
        }

        if (!isset($requiredPermissions[$section])) {
            return false;
        }

        foreach ($requiredPermissions[$section] as $permission) {
            if (!check_permission($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obtiene información detallada del developer actual
     */
    public function getDeveloperInfo(): ?array
    {
        if (!$this->isDeveloper()) {
            return null;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = $_SESSION['usuario_id'] ?? null;
        if (!$userId) {
            return null;
        }

        $stmt = $this->conn->prepare(
            'SELECT u.id, u.nombre, u.apellidos, u.correo, u.empresa_id, 
                    r.nombre as role_name, r.id as role_id,
                    e.nombre as empresa_nombre
             FROM usuarios u 
             LEFT JOIN roles r ON r.id = u.role_id 
             LEFT JOIN empresas e ON e.id = u.empresa_id 
             WHERE u.id = ? LIMIT 1'
        );
        
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user) {
            return null;
        }

        return [
            'id' => (int)$user['id'],
            'nombre_completo' => trim($user['nombre'] . ' ' . $user['apellidos']),
            'correo' => $user['correo'],
            'empresa_id' => $user['empresa_id'] ? (int)$user['empresa_id'] : null,
            'empresa_nombre' => $user['empresa_nombre'] ?? 'Sistema',
            'role_name' => $user['role_name'],
            'role_id' => (int)$user['role_id'],
            'last_activity' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Registra actividad del developer para auditoría
     */
    public function logDeveloperActivity(string $action, array $details = []): bool
    {
        if (!$this->isDeveloper()) {
            return false;
        }

        $userInfo = $this->getDeveloperInfo();
        if (!$userInfo) {
            return false;
        }

        $logData = [
            'usuario_id' => $userInfo['id'],
            'accion' => $action,
            'seccion' => 'developer_private',
            'detalles' => json_encode($details),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $stmt = $this->conn->prepare(
            'INSERT INTO auditoria_logs (usuario_id, accion, seccion, detalles, ip_address, user_agent, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param(
            'issssss',
            $logData['usuario_id'],
            $logData['accion'],
            $logData['seccion'],
            $logData['detalles'],
            $logData['ip_address'],
            $logData['user_agent'],
            $logData['timestamp']
        );

        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    /**
     * Verifica y actualiza permisos de developer
     */
    public function ensureDeveloperPermissions(): bool
    {
        if (!$this->isDeveloper()) {
            return false;
        }

        $requiredPermissions = [
            'auditoria_leer',
            'auditoria_crear',
            'reportes_leer',
            'configuracion_leer',
            'configuracion_actualizar',
            'calidad_documentos_leer',
            'calidad_documentos_crear',
            'notificaciones_leer',
            'notificaciones_crear'
        ];

        $userInfo = $this->getDeveloperInfo();
        if (!$userInfo) {
            return false;
        }

        $roleId = $userInfo['role_id'];

        foreach ($requiredPermissions as $permissionName) {
            $stmt = $this->conn->prepare(
                'INSERT IGNORE INTO role_permissions (role_id, permission_id)
                 SELECT ?, p.id FROM permissions p WHERE p.nombre = ?'
            );
            
            if ($stmt) {
                $stmt->bind_param('is', $roleId, $permissionName);
                $stmt->execute();
                $stmt->close();
            }
        }

        return true;
    }
}