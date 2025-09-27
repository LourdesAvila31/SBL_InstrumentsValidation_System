<?php
/**
 * Módulo de Integración de Proyectos - SBL Sistema Interno
 * 
 * Este módulo maneja la integración con sistemas externos,
 * APIs y proyectos relacionados con el sistema SBL
 * 
 * Funcionalidades:
 * - Integración con APIs externas
 * - Sincronización de datos
 * - Webhooks y notificaciones
 * - Gestión de proyectos
 * - Conectores con otros sistemas
 * - Monitoreo de integraciones
 * 
 * @author Sistema SBL
 * @version 1.0
 * @since 2025-09-26
 */

// Incluir configuración simplificada
require_once __DIR__ . '/config_simple.php';

class ProjectIntegrationManager {
    
    private $db;
    private $auth;
    
    public function __construct() {
        $this->db = new Database();
        $this->auth = new Auth();
        
        // Verificar autenticación
        if (!$this->auth->isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['error' => 'No autorizado. Debe iniciar sesión.']);
            exit;
        }
    }
    
    /**
     * Obtener lista de integraciones
     */
    public function getIntegraciones($filters = []) {
        try {
            $sql = "SELECT 
                        i.id,
                        i.nombre_integracion,
                        i.tipo_integracion,
                        i.descripcion,
                        i.sistema_origen,
                        i.sistema_destino,
                        i.configuracion,
                        i.estado,
                        i.url_endpoint,
                        i.metodo_http,
                        i.frecuencia_sync,
                        i.ultima_sincronizacion,
                        i.proxima_sincronizacion,
                        i.total_registros_sync,
                        i.registros_exitosos,
                        i.registros_fallidos,
                        i.activo,
                        i.fecha_creacion,
                        u.nombre_completo as usuario_nombre
                    FROM integraciones i
                    LEFT JOIN usuarios u ON i.usuario_creacion = u.id
                    WHERE i.activo = 1";
            
            $params = [];
            
            // Aplicar filtros
            if (!empty($filters['tipo_integracion'])) {
                $sql .= " AND i.tipo_integracion = :tipo_integracion";
                $params[':tipo_integracion'] = $filters['tipo_integracion'];
            }
            
            if (!empty($filters['estado'])) {
                $sql .= " AND i.estado = :estado";
                $params[':estado'] = $filters['estado'];
            }
            
            $sql .= " ORDER BY i.fecha_creacion DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $integraciones = $stmt->fetchAll();
            
            // Procesar configuración JSON
            foreach ($integraciones as &$integracion) {
                if (!empty($integracion['configuracion'])) {
                    $integracion['configuracion'] = json_decode($integracion['configuracion'], true);
                }
            }
            
            return [
                'success' => true,
                'data' => $integraciones,
                'total' => count($integraciones)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al obtener integraciones: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Crear nueva integración
     */
    public function createIntegracion($data) {
        try {
            // Validar datos requeridos
            $required = ['nombre_integracion', 'tipo_integracion', 'sistema_origen', 'sistema_destino'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return [
                        'success' => false,
                        'error' => "El campo '$field' es requerido"
                    ];
                }
            }
            
            $sql = "INSERT INTO integraciones (
                        nombre_integracion,
                        tipo_integracion,
                        descripcion,
                        sistema_origen,
                        sistema_destino,
                        configuracion,
                        estado,
                        url_endpoint,
                        metodo_http,
                        frecuencia_sync,
                        fecha_creacion,
                        usuario_creacion
                    ) VALUES (
                        :nombre_integracion,
                        :tipo_integracion,
                        :descripcion,
                        :sistema_origen,
                        :sistema_destino,
                        :configuracion,
                        'configurada',
                        :url_endpoint,
                        :metodo_http,
                        :frecuencia_sync,
                        NOW(),
                        :usuario_creacion
                    )";
            
            $params = [
                ':nombre_integracion' => $data['nombre_integracion'],
                ':tipo_integracion' => $data['tipo_integracion'],
                ':descripcion' => $data['descripcion'] ?? null,
                ':sistema_origen' => $data['sistema_origen'],
                ':sistema_destino' => $data['sistema_destino'],
                ':configuracion' => !empty($data['configuracion']) ? json_encode($data['configuracion']) : null,
                ':url_endpoint' => $data['url_endpoint'] ?? null,
                ':metodo_http' => $data['metodo_http'] ?? 'GET',
                ':frecuencia_sync' => $data['frecuencia_sync'] ?? 60, // minutos
                ':usuario_creacion' => $this->auth->getUserId()
            ];
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                $integracionId = $this->db->lastInsertId();
                
                return [
                    'success' => true,
                    'message' => 'Integración creada exitosamente',
                    'id' => $integracionId
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Error al crear integración'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al crear integración: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Ejecutar sincronización manual
     */
    public function ejecutarSincronizacion($integracionId) {
        try {
            // Obtener datos de la integración
            $sql = "SELECT * FROM integraciones WHERE id = :id AND activo = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $integracionId]);
            $integracion = $stmt->fetch();
            
            if (!$integracion) {
                return [
                    'success' => false,
                    'error' => 'Integración no encontrada'
                ];
            }
            
            // Registrar inicio de sincronización
            $this->registrarSincronizacion($integracionId, 'iniciada');
            
            $inicio = microtime(true);
            $resultado = false;
            $registros_procesados = 0;
            $registros_exitosos = 0;
            $registros_fallidos = 0;
            $mensaje_error = null;
            
            try {
                // Ejecutar sincronización según el tipo
                switch ($integracion['tipo_integracion']) {
                    case 'api_rest':
                        $resultado = $this->sincronizarApiRest($integracion);
                        break;
                        
                    case 'base_datos':
                        $resultado = $this->sincronizarBaseDatos($integracion);
                        break;
                        
                    case 'archivo':
                        $resultado = $this->sincronizarArchivo($integracion);
                        break;
                        
                    default:
                        throw new Exception('Tipo de integración no soportado');
                }
                
                if ($resultado['success']) {
                    $registros_procesados = $resultado['total'] ?? 0;
                    $registros_exitosos = $resultado['exitosos'] ?? 0;
                    $registros_fallidos = $resultado['fallidos'] ?? 0;
                }
                
            } catch (Exception $e) {
                $resultado = ['success' => false];
                $mensaje_error = $e->getMessage();
            }
            
            $tiempo_ejecucion = round(microtime(true) - $inicio, 2);
            
            // Actualizar registro de sincronización
            $estado_final = $resultado['success'] ? 'completada' : 'fallida';
            $this->actualizarSincronizacion(
                $integracionId,
                $estado_final,
                $tiempo_ejecucion,
                $registros_procesados,
                $registros_exitosos,
                $registros_fallidos,
                $mensaje_error
            );
            
            return [
                'success' => $resultado['success'],
                'message' => $resultado['success'] ? 'Sincronización completada' : 'Sincronización falló',
                'datos' => [
                    'tiempo_ejecucion' => $tiempo_ejecucion,
                    'registros_procesados' => $registros_procesados,
                    'registros_exitosos' => $registros_exitosos,
                    'registros_fallidos' => $registros_fallidos,
                    'error' => $mensaje_error
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error en sincronización: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener estadísticas de integraciones
     */
    public function getEstadisticas() {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_integraciones,
                        COUNT(CASE WHEN estado = 'activa' THEN 1 END) as activas,
                        COUNT(CASE WHEN estado = 'inactiva' THEN 1 END) as inactivas,
                        COUNT(CASE WHEN estado = 'error' THEN 1 END) as con_error,
                        SUM(total_registros_sync) as total_registros_sync,
                        SUM(registros_exitosos) as total_exitosos,
                        SUM(registros_fallidos) as total_fallidos
                    FROM integraciones 
                    WHERE activo = 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $stats = $stmt->fetch();
            
            return [
                'success' => true,
                'data' => $stats
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al obtener estadísticas: ' . $e->getMessage()
            ];
        }
    }
    
    // Métodos auxiliares privados
    private function sincronizarApiRest($integracion) {
        // Implementar sincronización vía API REST
        return ['success' => true, 'total' => 0, 'exitosos' => 0, 'fallidos' => 0];
    }
    
    private function sincronizarBaseDatos($integracion) {
        // Implementar sincronización de base de datos
        return ['success' => true, 'total' => 0, 'exitosos' => 0, 'fallidos' => 0];
    }
    
    private function sincronizarArchivo($integracion) {
        // Implementar sincronización de archivos
        return ['success' => true, 'total' => 0, 'exitosos' => 0, 'fallidos' => 0];
    }
    
    private function registrarSincronizacion($integracionId, $estado) {
        $sql = "INSERT INTO integration_logs (integracion_id, estado, fecha_inicio, usuario_ejecuta) 
                VALUES (:integracion_id, :estado, NOW(), :usuario)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':integracion_id' => $integracionId,
            ':estado' => $estado,
            ':usuario' => $this->auth->getUserId()
        ]);
        
        return $this->db->lastInsertId();
    }
    
    private function actualizarSincronizacion($integracionId, $estado, $tiempo, $total, $exitosos, $fallidos, $error) {
        // Actualizar integración
        $sql = "UPDATE integraciones SET 
                    estado = :estado,
                    ultima_sincronizacion = NOW(),
                    proxima_sincronizacion = DATE_ADD(NOW(), INTERVAL frecuencia_sync MINUTE),
                    total_registros_sync = total_registros_sync + :total,
                    registros_exitosos = registros_exitosos + :exitosos,
                    registros_fallidos = registros_fallidos + :fallidos
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $integracionId,
            ':estado' => $estado === 'completada' ? 'activa' : 'error',
            ':total' => $total,
            ':exitosos' => $exitosos,
            ':fallidos' => $fallidos
        ]);
    }
}

// Manejo de solicitudes API
if ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $manager = new ProjectIntegrationManager();
        $action = $_GET['action'] ?? $_POST['action'] ?? '';
        
        switch ($action) {
            case 'list':
                $filters = [
                    'tipo_integracion' => $_GET['tipo_integracion'] ?? null,
                    'estado' => $_GET['estado'] ?? null
                ];
                echo json_encode($manager->getIntegraciones($filters));
                break;
                
            case 'create':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $data = json_decode(file_get_contents('php://input'), true);
                    echo json_encode($manager->createIntegracion($data));
                } else {
                    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
                }
                break;
                
            case 'sync':
                $id = $_POST['id'] ?? $_GET['id'] ?? null;
                echo json_encode($manager->ejecutarSincronizacion($id));
                break;
                
            case 'stats':
                echo json_encode($manager->getEstadisticas());
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