<?php
/**
 * Módulo de Control de Configuración - SBL Sistema Interno
 * 
 * Este módulo maneja el control de cambios y configuraciones del sistema
 * siguiendo estándares GAMP5 y buenas prácticas de Change Control
 * 
 * Funcionalidades:
 * - Gestión de cambios (Change Control)
 * - Versionado de configuraciones
 * - Aprobaciones de cambios
 * - Historial de modificaciones
 * - Control de versiones de documentos
 * - Trazabilidad completa
 * 
 * @author Sistema SBL
 * @version 1.0
 * @since 2025-09-26
 */

// Incluir configuración simplificada
require_once __DIR__ . '/config_simple.php';

class ConfigurationControlManager {
    
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
     * Obtener lista de cambios
     */
    public function getCambios($filters = []) {
        try {
            $sql = "SELECT 
                        c.id,
                        c.codigo_cambio,
                        c.titulo,
                        c.descripcion,
                        c.tipo_cambio,
                        c.categoria,
                        c.prioridad,
                        c.estado,
                        c.justificacion,
                        c.impacto_estimado,
                        c.riesgo_evaluado,
                        c.fecha_solicitud,
                        c.fecha_aprobacion,
                        c.fecha_implementacion,
                        c.usuario_solicita,
                        c.usuario_aprueba,
                        c.usuario_implementa,
                        c.documentos_afectados,
                        us.nombre_completo as solicita_nombre,
                        ua.nombre_completo as aprueba_nombre,
                        ui.nombre_completo as implementa_nombre
                    FROM cambios c
                    LEFT JOIN usuarios us ON c.usuario_solicita = us.id
                    LEFT JOIN usuarios ua ON c.usuario_aprueba = ua.id
                    LEFT JOIN usuarios ui ON c.usuario_implementa = ui.id
                    WHERE c.activo = 1";
            
            $params = [];
            
            // Aplicar filtros
            if (!empty($filters['tipo_cambio'])) {
                $sql .= " AND c.tipo_cambio = :tipo_cambio";
                $params[':tipo_cambio'] = $filters['tipo_cambio'];
            }
            
            if (!empty($filters['estado'])) {
                $sql .= " AND c.estado = :estado";
                $params[':estado'] = $filters['estado'];
            }
            
            if (!empty($filters['prioridad'])) {
                $sql .= " AND c.prioridad = :prioridad";
                $params[':prioridad'] = $filters['prioridad'];
            }
            
            $sql .= " ORDER BY c.fecha_solicitud DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $cambios = $stmt->fetchAll();
            
            // Procesar arrays JSON
            foreach ($cambios as &$cambio) {
                if (!empty($cambio['documentos_afectados'])) {
                    $cambio['documentos_afectados'] = json_decode($cambio['documentos_afectados'], true);
                }
            }
            
            return [
                'success' => true,
                'data' => $cambios,
                'total' => count($cambios)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al obtener cambios: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Crear nueva solicitud de cambio
     */
    public function createCambio($data) {
        try {
            // Validar datos requeridos
            $required = ['titulo', 'descripcion', 'tipo_cambio', 'justificacion'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return [
                        'success' => false,
                        'error' => "El campo '$field' es requerido"
                    ];
                }
            }
            
            // Generar código de cambio
            $codigo = $this->generateChangeCode($data['tipo_cambio']);
            
            $sql = "INSERT INTO cambios (
                        codigo_cambio,
                        titulo,
                        descripcion,
                        tipo_cambio,
                        categoria,
                        prioridad,
                        estado,
                        justificacion,
                        impacto_estimado,
                        riesgo_evaluado,
                        fecha_solicitud,
                        usuario_solicita,
                        documentos_afectados,
                        fecha_creacion,
                        usuario_creacion
                    ) VALUES (
                        :codigo_cambio,
                        :titulo,
                        :descripcion,
                        :tipo_cambio,
                        :categoria,
                        :prioridad,
                        'solicitado',
                        :justificacion,
                        :impacto_estimado,
                        :riesgo_evaluado,
                        NOW(),
                        :usuario_solicita,
                        :documentos_afectados,
                        NOW(),
                        :usuario_creacion
                    )";
            
            $params = [
                ':codigo_cambio' => $codigo,
                ':titulo' => $data['titulo'],
                ':descripcion' => $data['descripcion'],
                ':tipo_cambio' => $data['tipo_cambio'],
                ':categoria' => $data['categoria'] ?? 'general',
                ':prioridad' => $data['prioridad'] ?? 'media',
                ':justificacion' => $data['justificacion'],
                ':impacto_estimado' => $data['impacto_estimado'] ?? null,
                ':riesgo_evaluado' => $data['riesgo_evaluado'] ?? null,
                ':usuario_solicita' => $this->auth->getUserId(),
                ':documentos_afectados' => !empty($data['documentos_afectados']) ? json_encode($data['documentos_afectados']) : null,
                ':usuario_creacion' => $this->auth->getUserId()
            ];
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                $cambioId = $this->db->lastInsertId();
                
                return [
                    'success' => true,
                    'message' => 'Solicitud de cambio creada exitosamente',
                    'id' => $cambioId,
                    'codigo' => $codigo
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Error al crear solicitud de cambio'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al crear cambio: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Actualizar estado de cambio
     */
    public function updateCambio($id, $data) {
        try {
            $updates = [];
            $params = [':id' => $id];
            
            // Campos permitidos para actualización
            $allowed_fields = [
                'estado', 'impacto_estimado', 'riesgo_evaluado', 
                'observaciones_aprobacion', 'plan_implementacion',
                'resultados_implementacion', 'validacion_cambio'
            ];
            
            foreach ($allowed_fields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = :$field";
                    $params[":$field"] = $data[$field];
                }
            }
            
            // Manejo especial según el estado
            if (isset($data['estado'])) {
                switch ($data['estado']) {
                    case 'aprobado':
                        $updates[] = "fecha_aprobacion = NOW()";
                        $updates[] = "usuario_aprueba = :usuario_aprueba";
                        $params[':usuario_aprueba'] = $this->auth->getUserId();
                        break;
                        
                    case 'implementado':
                        $updates[] = "fecha_implementacion = NOW()";
                        $updates[] = "usuario_implementa = :usuario_implementa";
                        $params[':usuario_implementa'] = $this->auth->getUserId();
                        break;
                        
                    case 'cerrado':
                        $updates[] = "fecha_cierre = NOW()";
                        $updates[] = "usuario_cierre = :usuario_cierre";
                        $params[':usuario_cierre'] = $this->auth->getUserId();
                        break;
                }
            }
            
            if (empty($updates)) {
                return [
                    'success' => false,
                    'error' => 'No hay datos para actualizar'
                ];
            }
            
            $updates[] = "fecha_modificacion = NOW()";
            $updates[] = "usuario_modificacion = :usuario_modificacion";
            $params[':usuario_modificacion'] = $this->auth->getUserId();
            
            $sql = "UPDATE cambios SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Cambio actualizado exitosamente'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Error al actualizar cambio'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al actualizar cambio: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener estadísticas de cambios
     */
    public function getEstadisticas() {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_cambios,
                        COUNT(CASE WHEN estado = 'solicitado' THEN 1 END) as solicitados,
                        COUNT(CASE WHEN estado = 'en_revision' THEN 1 END) as en_revision,
                        COUNT(CASE WHEN estado = 'aprobado' THEN 1 END) as aprobados,
                        COUNT(CASE WHEN estado = 'implementado' THEN 1 END) as implementados,
                        COUNT(CASE WHEN estado = 'cerrado' THEN 1 END) as cerrados,
                        COUNT(CASE WHEN estado = 'rechazado' THEN 1 END) as rechazados
                    FROM cambios 
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
    private function generateChangeCode($tipo) {
        $prefijo = 'CHG';
        switch ($tipo) {
            case 'configuracion':
                $prefijo = 'CFG';
                break;
            case 'procedimiento':
                $prefijo = 'PROC';
                break;
            case 'sistema':
                $prefijo = 'SYS';
                break;
            case 'documento':
                $prefijo = 'DOC';
                break;
        }
        
        $year = date('Y');
        $month = date('m');
        
        // Obtener el siguiente número secuencial
        $sql = "SELECT COUNT(*) + 1 as next_num 
                FROM cambios 
                WHERE codigo_cambio LIKE :pattern 
                AND YEAR(fecha_creacion) = :year";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':pattern' => $prefijo . '-' . $year . '%',
            ':year' => $year
        ]);
        $result = $stmt->fetch();
        
        $num = str_pad($result['next_num'], 4, '0', STR_PAD_LEFT);
        
        return $prefijo . '-' . $year . $month . '-' . $num;
    }
}

// Manejo de solicitudes API
if ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $manager = new ConfigurationControlManager();
        $action = $_GET['action'] ?? $_POST['action'] ?? '';
        
        switch ($action) {
            case 'list':
                $filters = [
                    'tipo_cambio' => $_GET['tipo_cambio'] ?? null,
                    'estado' => $_GET['estado'] ?? null,
                    'prioridad' => $_GET['prioridad'] ?? null
                ];
                echo json_encode($manager->getCambios($filters));
                break;
                
            case 'create':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $data = json_decode(file_get_contents('php://input'), true);
                    echo json_encode($manager->createCambio($data));
                } else {
                    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
                }
                break;
                
            case 'update':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $id = $_POST['id'] ?? $_GET['id'] ?? null;
                    $data = json_decode(file_get_contents('php://input'), true);
                    echo json_encode($manager->updateCambio($id, $data));
                } else {
                    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
                }
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