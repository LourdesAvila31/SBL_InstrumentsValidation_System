<?php
/**
 * Módulo de AdminDashboard - SBL Sistema Interno
 * 
 * Este módulo maneja todas las operaciones del panel de administración
 * incluyendo estadísticas del sistema, gestión de usuarios y configuraciones
 * 
 * Funcionalidades:
 * - Dashboard con estadísticas generales
 * - Gestión de usuarios y roles
 * - Configuraciones del sistema
 * - Monitoreo de actividad
 * - Reportes ejecutivos
 * 
 * @author Sistema SBL
 * @version 1.0
 * @since 2025-09-26
 */

// Incluir configuración simplificada
require_once __DIR__ . '/config_simple.php';

class AdminDashboardManager {
    
    private $db;
    private $auth;
    
    public function __construct() {
        $this->db = new Database();
        $this->auth = new Auth();
        
        // Verificar autenticación y permisos de administrador
        if (!$this->auth->isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['error' => 'No autorizado. Debe iniciar sesión.']);
            exit;
        }
        
        if (!$this->auth->isAdmin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Acceso denegado. Se requieren permisos de administrador.']);
            exit;
        }
    }
    
    /**
     * Obtener estadísticas generales del dashboard
     * 
     * @return array Estadísticas del sistema
     */
    public function getDashboardStats() {
        try {
            $stats = [
                'usuarios' => $this->getUserStats(),
                'instrumentos' => $this->getInstrumentStats(),
                'calibraciones' => $this->getCalibrationStats(),
                'incidentes' => $this->getIncidentStats(),
                'backups' => $this->getBackupStats(),
                'sistema' => $this->getSystemStats()
            ];
            
            return [
                'success' => true,
                'data' => $stats,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al obtener estadísticas: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener estadísticas de usuarios
     */
    private function getUserStats() {
        $sql = "SELECT 
                    COUNT(*) as total_usuarios,
                    COUNT(CASE WHEN activo = 1 THEN 1 END) as usuarios_activos,
                    COUNT(CASE WHEN DATE(ultimo_login) = CURDATE() THEN 1 END) as usuarios_hoy,
                    COUNT(CASE WHEN role = 'admin' THEN 1 END) as administradores
                FROM usuarios";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetch();
    }
    
    /**
     * Obtener estadísticas de instrumentos
     */
    private function getInstrumentStats() {
        $sql = "SELECT 
                    COUNT(*) as total_instrumentos,
                    COUNT(CASE WHEN estado = 'calibrado' THEN 1 END) as calibrados,
                    COUNT(CASE WHEN estado = 'vencido' THEN 1 END) as vencidos,
                    COUNT(CASE WHEN fecha_proxima_calibracion <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as proximos_vencer
                FROM instrumentos 
                WHERE activo = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetch();
    }
    
    /**
     * Obtener estadísticas de calibraciones
     */
    private function getCalibrationStats() {
        $sql = "SELECT 
                    COUNT(*) as total_calibraciones,
                    COUNT(CASE WHEN DATE(fecha_calibracion) = CURDATE() THEN 1 END) as calibraciones_hoy,
                    COUNT(CASE WHEN DATE(fecha_calibracion) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as calibraciones_semana,
                    COUNT(CASE WHEN resultado = 'aprobado' THEN 1 END) as calibraciones_aprobadas
                FROM calibraciones";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetch();
    }
    
    /**
     * Obtener estadísticas de incidentes
     */
    private function getIncidentStats() {
        $sql = "SELECT 
                    COUNT(*) as total_incidentes,
                    COUNT(CASE WHEN estado = 'abierto' THEN 1 END) as incidentes_abiertos,
                    COUNT(CASE WHEN estado = 'resuelto' THEN 1 END) as incidentes_resueltos,
                    COUNT(CASE WHEN prioridad = 'alta' AND estado != 'resuelto' THEN 1 END) as incidentes_criticos
                FROM incidentes";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetch();
    }
    
    /**
     * Obtener estadísticas de backups
     */
    private function getBackupStats() {
        $sql = "SELECT 
                    COUNT(*) as total_backups,
                    MAX(fecha_backup) as ultimo_backup,
                    COUNT(CASE WHEN estado = 'exitoso' THEN 1 END) as backups_exitosos,
                    COUNT(CASE WHEN estado = 'fallido' THEN 1 END) as backups_fallidos
                FROM backups 
                WHERE DATE(fecha_backup) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetch();
    }
    
    /**
     * Obtener estadísticas del sistema
     */
    private function getSystemStats() {
        return [
            'version_sistema' => '1.0',
            'tiempo_activo' => $this->getSystemUptime(),
            'espacio_disco' => $this->getDiskSpace(),
            'memoria_uso' => $this->getMemoryUsage(),
            'conexiones_activas' => $this->getActiveConnections()
        ];
    }
    
    /**
     * Obtener tiempo de actividad del sistema
     */
    private function getSystemUptime() {
        // Simulación de uptime - en producción se podría obtener del servidor
        return "24 días, 15 horas";
    }
    
    /**
     * Obtener espacio en disco
     */
    private function getDiskSpace() {
        $total = disk_total_space(__DIR__);
        $free = disk_free_space(__DIR__);
        $used = $total - $free;
        
        return [
            'total' => $this->formatBytes($total),
            'usado' => $this->formatBytes($used),
            'libre' => $this->formatBytes($free),
            'porcentaje_uso' => round(($used / $total) * 100, 2)
        ];
    }
    
    /**
     * Obtener uso de memoria
     */
    private function getMemoryUsage() {
        return [
            'uso_actual' => $this->formatBytes(memory_get_usage(true)),
            'pico_memoria' => $this->formatBytes(memory_get_peak_usage(true))
        ];
    }
    
    /**
     * Obtener conexiones activas
     */
    private function getActiveConnections() {
        $sql = "SHOW STATUS LIKE 'Threads_connected'";
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();
        return $result['Value'] ?? 0;
    }
    
    /**
     * Formatear bytes a formato legible
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Obtener usuarios del sistema
     * 
     * @param array $filters Filtros opcionales
     * @return array Lista de usuarios
     */
    public function getUsuarios($filters = []) {
        try {
            $sql = "SELECT 
                        u.id,
                        u.username,
                        u.email,
                        u.nombre_completo,
                        u.role,
                        u.activo,
                        u.ultimo_login,
                        u.fecha_registro,
                        u.fecha_modificacion
                    FROM usuarios u 
                    WHERE 1=1";
            
            $params = [];
            
            // Aplicar filtros
            if (!empty($filters['activo'])) {
                $sql .= " AND u.activo = :activo";
                $params[':activo'] = $filters['activo'];
            }
            
            if (!empty($filters['role'])) {
                $sql .= " AND u.role = :role";
                $params[':role'] = $filters['role'];
            }
            
            if (!empty($filters['search'])) {
                $sql .= " AND (u.username LIKE :search OR u.email LIKE :search OR u.nombre_completo LIKE :search)";
                $params[':search'] = '%' . $filters['search'] . '%';
            }
            
            $sql .= " ORDER BY u.nombre_completo ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $usuarios = $stmt->fetchAll();
            
            // Ocultar información sensible
            foreach ($usuarios as &$usuario) {
                unset($usuario['password']);
            }
            
            return [
                'success' => true,
                'data' => $usuarios,
                'total' => count($usuarios)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al obtener usuarios: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Crear nuevo usuario
     * 
     * @param array $data Datos del usuario
     * @return array Resultado de la operación
     */
    public function createUsuario($data) {
        try {
            // Validar datos requeridos
            $required = ['username', 'email', 'password', 'nombre_completo', 'role'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return [
                        'success' => false,
                        'error' => "El campo '$field' es requerido"
                    ];
                }
            }
            
            // Verificar que el usuario no exista
            $sql = "SELECT id FROM usuarios WHERE username = :username OR email = :email";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':username' => $data['username'],
                ':email' => $data['email']
            ]);
            
            if ($stmt->fetch()) {
                return [
                    'success' => false,
                    'error' => 'Ya existe un usuario con ese nombre de usuario o email'
                ];
            }
            
            // Crear usuario
            $sql = "INSERT INTO usuarios (
                        username, 
                        email, 
                        password, 
                        nombre_completo, 
                        role, 
                        activo, 
                        fecha_registro, 
                        usuario_registro
                    ) VALUES (
                        :username, 
                        :email, 
                        :password, 
                        :nombre_completo, 
                        :role, 
                        1, 
                        NOW(), 
                        :usuario_registro
                    )";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':username' => $data['username'],
                ':email' => $data['email'],
                ':password' => password_hash($data['password'], PASSWORD_DEFAULT),
                ':nombre_completo' => $data['nombre_completo'],
                ':role' => $data['role'],
                ':usuario_registro' => $this->auth->getUsername()
            ]);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Usuario creado exitosamente',
                    'id' => $this->db->lastInsertId()
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Error al crear usuario'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al crear usuario: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Actualizar usuario
     * 
     * @param int $id ID del usuario
     * @param array $data Datos a actualizar
     * @return array Resultado de la operación
     */
    public function updateUsuario($id, $data) {
        try {
            // Verificar que el usuario existe
            $sql = "SELECT id FROM usuarios WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id]);
            
            if (!$stmt->fetch()) {
                return [
                    'success' => false,
                    'error' => 'Usuario no encontrado'
                ];
            }
            
            $updates = [];
            $params = [':id' => $id];
            
            // Construir consulta de actualización dinámicamente
            if (isset($data['nombre_completo'])) {
                $updates[] = "nombre_completo = :nombre_completo";
                $params[':nombre_completo'] = $data['nombre_completo'];
            }
            
            if (isset($data['email'])) {
                $updates[] = "email = :email";
                $params[':email'] = $data['email'];
            }
            
            if (isset($data['role'])) {
                $updates[] = "role = :role";
                $params[':role'] = $data['role'];
            }
            
            if (isset($data['activo'])) {
                $updates[] = "activo = :activo";
                $params[':activo'] = $data['activo'];
            }
            
            if (!empty($data['password'])) {
                $updates[] = "password = :password";
                $params[':password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            
            if (empty($updates)) {
                return [
                    'success' => false,
                    'error' => 'No hay datos para actualizar'
                ];
            }
            
            $updates[] = "fecha_modificacion = NOW()";
            $updates[] = "usuario_modificacion = :usuario_modificacion";
            $params[':usuario_modificacion'] = $this->auth->getUsername();
            
            $sql = "UPDATE usuarios SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Usuario actualizado exitosamente'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Error al actualizar usuario'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al actualizar usuario: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Eliminar usuario (desactivar)
     * 
     * @param int $id ID del usuario
     * @return array Resultado de la operación
     */
    public function deleteUsuario($id) {
        try {
            // No permitir eliminar el propio usuario
            if ($id == $this->auth->getUserId()) {
                return [
                    'success' => false,
                    'error' => 'No puedes eliminarte a ti mismo'
                ];
            }
            
            $sql = "UPDATE usuarios SET 
                        activo = 0, 
                        fecha_modificacion = NOW(), 
                        usuario_modificacion = :usuario_modificacion 
                    WHERE id = :id";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':id' => $id,
                ':usuario_modificacion' => $this->auth->getUsername()
            ]);
            
            if ($result && $stmt->rowCount() > 0) {
                return [
                    'success' => true,
                    'message' => 'Usuario desactivado exitosamente'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Usuario no encontrado'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al eliminar usuario: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener logs del sistema
     * 
     * @param array $filters Filtros opcionales
     * @return array Logs del sistema
     */
    public function getSystemLogs($filters = []) {
        try {
            $sql = "SELECT 
                        l.id,
                        l.nivel,
                        l.mensaje,
                        l.usuario,
                        l.ip_address,
                        l.user_agent,
                        l.fecha_registro
                    FROM system_logs l 
                    WHERE 1=1";
            
            $params = [];
            
            // Aplicar filtros
            if (!empty($filters['nivel'])) {
                $sql .= " AND l.nivel = :nivel";
                $params[':nivel'] = $filters['nivel'];
            }
            
            if (!empty($filters['fecha_desde'])) {
                $sql .= " AND DATE(l.fecha_registro) >= :fecha_desde";
                $params[':fecha_desde'] = $filters['fecha_desde'];
            }
            
            if (!empty($filters['fecha_hasta'])) {
                $sql .= " AND DATE(l.fecha_registro) <= :fecha_hasta";
                $params[':fecha_hasta'] = $filters['fecha_hasta'];
            }
            
            if (!empty($filters['usuario'])) {
                $sql .= " AND l.usuario LIKE :usuario";
                $params[':usuario'] = '%' . $filters['usuario'] . '%';
            }
            
            $sql .= " ORDER BY l.fecha_registro DESC LIMIT 1000";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $logs = $stmt->fetchAll();
            
            return [
                'success' => true,
                'data' => $logs,
                'total' => count($logs)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al obtener logs: ' . $e->getMessage()
            ];
        }
    }
}

// Manejo de solicitudes API
if ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $manager = new AdminDashboardManager();
        $action = $_GET['action'] ?? $_POST['action'] ?? '';
        
        switch ($action) {
            case 'stats':
                echo json_encode($manager->getDashboardStats());
                break;
                
            case 'usuarios':
                $filters = [
                    'activo' => $_GET['activo'] ?? null,
                    'role' => $_GET['role'] ?? null,
                    'search' => $_GET['search'] ?? null
                ];
                echo json_encode($manager->getUsuarios($filters));
                break;
                
            case 'create_usuario':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $data = json_decode(file_get_contents('php://input'), true);
                    echo json_encode($manager->createUsuario($data));
                } else {
                    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
                }
                break;
                
            case 'update_usuario':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $id = $_POST['id'] ?? $_GET['id'] ?? null;
                    $data = json_decode(file_get_contents('php://input'), true);
                    echo json_encode($manager->updateUsuario($id, $data));
                } else {
                    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
                }
                break;
                
            case 'delete_usuario':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $id = $_POST['id'] ?? $_GET['id'] ?? null;
                    echo json_encode($manager->deleteUsuario($id));
                } else {
                    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
                }
                break;
                
            case 'logs':
                $filters = [
                    'nivel' => $_GET['nivel'] ?? null,
                    'fecha_desde' => $_GET['fecha_desde'] ?? null,
                    'fecha_hasta' => $_GET['fecha_hasta'] ?? null,
                    'usuario' => $_GET['usuario'] ?? null
                ];
                echo json_encode($manager->getSystemLogs($filters));
                break;
                
            default:
                echo json_encode([
                    'success' => false,
                    'error' => 'Acción no válida'
                ]);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}
?>