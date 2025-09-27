<?php
/**
 * Módulo de Gestión de Incidentes - SBL Sistema Interno
 * 
 * Este módulo maneja todas las operaciones relacionadas con incidentes,
 * no conformidades y acciones correctivas del sistema
 * 
 * Funcionalidades:
 * - Registro de incidentes
 * - Clasificación y priorización
 * - Asignación de responsables
 * - Seguimiento de resolución
 * - Análisis de causa raíz
 * - Acciones correctivas y preventivas
 * 
 * @author Sistema SBL
 * @version 1.0
 * @since 2025-09-26
 */

// Incluir configuración simplificada
require_once __DIR__ . '/config_simple.php';

class IncidentManagementManager {
    
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
     * Obtener lista de incidentes
     */
    public function getIncidentes($filters = []) {
        try {
            $sql = "SELECT 
                        i.id,
                        i.codigo_incidente,
                        i.titulo,
                        i.descripcion,
                        i.tipo_incidente,
                        i.categoria,
                        i.prioridad,
                        i.severidad,
                        i.estado,
                        i.fecha_ocurrencia,
                        i.fecha_deteccion,
                        i.area_afectada,
                        i.impacto,
                        i.usuario_reporta,
                        i.usuario_asignado,
                        i.fecha_estimada_resolucion,
                        i.fecha_resolucion,
                        i.solucion_aplicada,
                        i.causa_raiz,
                        i.acciones_correctivas,
                        i.acciones_preventivas,
                        ur.nombre_completo as reporta_nombre,
                        ua.nombre_completo as asignado_nombre
                    FROM incidentes i
                    LEFT JOIN usuarios ur ON i.usuario_reporta = ur.id
                    LEFT JOIN usuarios ua ON i.usuario_asignado = ua.id
                    WHERE i.activo = 1";
            
            $params = [];
            
            // Aplicar filtros
            if (!empty($filters['tipo_incidente'])) {
                $sql .= " AND i.tipo_incidente = :tipo_incidente";
                $params[':tipo_incidente'] = $filters['tipo_incidente'];
            }
            
            if (!empty($filters['prioridad'])) {
                $sql .= " AND i.prioridad = :prioridad";
                $params[':prioridad'] = $filters['prioridad'];
            }
            
            if (!empty($filters['estado'])) {
                $sql .= " AND i.estado = :estado";
                $params[':estado'] = $filters['estado'];
            }
            
            if (!empty($filters['usuario_asignado'])) {
                $sql .= " AND i.usuario_asignado = :usuario_asignado";
                $params[':usuario_asignado'] = $filters['usuario_asignado'];
            }
            
            $sql .= " ORDER BY 
                        CASE i.prioridad 
                            WHEN 'critica' THEN 1 
                            WHEN 'alta' THEN 2 
                            WHEN 'media' THEN 3 
                            WHEN 'baja' THEN 4 
                        END ASC,
                        i.fecha_deteccion DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $incidentes = $stmt->fetchAll();
            
            return [
                'success' => true,
                'data' => $incidentes,
                'total' => count($incidentes)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al obtener incidentes: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Crear nuevo incidente
     */
    public function createIncidente($data) {
        try {
            // Validar datos requeridos
            $required = ['titulo', 'descripcion', 'tipo_incidente', 'prioridad', 'area_afectada'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return [
                        'success' => false,
                        'error' => "El campo '$field' es requerido"
                    ];
                }
            }
            
            // Generar código de incidente
            $codigo = $this->generateIncidentCode($data['tipo_incidente']);
            
            // Calcular fecha estimada de resolución según prioridad
            $fecha_estimada = $this->calculateEstimatedResolution($data['prioridad']);
            
            $sql = "INSERT INTO incidentes (
                        codigo_incidente,
                        titulo,
                        descripcion,
                        tipo_incidente,
                        categoria,
                        prioridad,
                        severidad,
                        estado,
                        fecha_ocurrencia,
                        fecha_deteccion,
                        area_afectada,
                        impacto,
                        usuario_reporta,
                        usuario_asignado,
                        fecha_estimada_resolucion,
                        fecha_creacion,
                        usuario_creacion
                    ) VALUES (
                        :codigo_incidente,
                        :titulo,
                        :descripcion,
                        :tipo_incidente,
                        :categoria,
                        :prioridad,
                        :severidad,
                        'abierto',
                        :fecha_ocurrencia,
                        :fecha_deteccion,
                        :area_afectada,
                        :impacto,
                        :usuario_reporta,
                        :usuario_asignado,
                        :fecha_estimada_resolucion,
                        NOW(),
                        :usuario_creacion
                    )";
            
            $params = [
                ':codigo_incidente' => $codigo,
                ':titulo' => $data['titulo'],
                ':descripcion' => $data['descripcion'],
                ':tipo_incidente' => $data['tipo_incidente'],
                ':categoria' => $data['categoria'] ?? null,
                ':prioridad' => $data['prioridad'],
                ':severidad' => $data['severidad'] ?? $data['prioridad'],
                ':fecha_ocurrencia' => $data['fecha_ocurrencia'] ?? date('Y-m-d H:i:s'),
                ':fecha_deteccion' => date('Y-m-d H:i:s'),
                ':area_afectada' => $data['area_afectada'],
                ':impacto' => $data['impacto'] ?? null,
                ':usuario_reporta' => $data['usuario_reporta'] ?? $this->auth->getUserId(),
                ':usuario_asignado' => $data['usuario_asignado'] ?? null,
                ':fecha_estimada_resolucion' => $fecha_estimada,
                ':usuario_creacion' => $this->auth->getUserId()
            ];
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                $incidenteId = $this->db->lastInsertId();
                
                return [
                    'success' => true,
                    'message' => 'Incidente creado exitosamente',
                    'id' => $incidenteId,
                    'codigo' => $codigo
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Error al crear incidente'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al crear incidente: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Actualizar incidente
     */
    public function updateIncidente($id, $data) {
        try {
            $updates = [];
            $params = [':id' => $id];
            
            // Construir consulta de actualización dinámicamente
            $allowed_fields = [
                'titulo', 'descripcion', 'prioridad', 'estado', 'usuario_asignado',
                'solucion_aplicada', 'causa_raiz', 'acciones_correctivas', 'acciones_preventivas'
            ];
            
            foreach ($allowed_fields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = :$field";
                    $params[":$field"] = $data[$field];
                }
            }
            
            // Si se está cerrando el incidente
            if (isset($data['estado']) && in_array($data['estado'], ['resuelto', 'cerrado'])) {
                $updates[] = "fecha_resolucion = NOW()";
                $updates[] = "usuario_resolucion = :usuario_resolucion";
                $params[':usuario_resolucion'] = $this->auth->getUserId();
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
            
            $sql = "UPDATE incidentes SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Incidente actualizado exitosamente'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Error al actualizar incidente'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al actualizar incidente: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener estadísticas de incidentes
     */
    public function getEstadisticas() {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_incidentes,
                        COUNT(CASE WHEN estado = 'abierto' THEN 1 END) as abiertos,
                        COUNT(CASE WHEN estado = 'en_proceso' THEN 1 END) as en_proceso,
                        COUNT(CASE WHEN estado = 'resuelto' THEN 1 END) as resueltos,
                        COUNT(CASE WHEN prioridad = 'critica' AND estado NOT IN ('resuelto', 'cerrado') THEN 1 END) as criticos_abiertos,
                        COUNT(CASE WHEN fecha_estimada_resolucion < CURDATE() AND estado NOT IN ('resuelto', 'cerrado') THEN 1 END) as vencidos,
                        AVG(CASE WHEN fecha_resolucion IS NOT NULL THEN DATEDIFF(fecha_resolucion, fecha_deteccion) END) as tiempo_promedio_resolucion
                    FROM incidentes 
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
    private function generateIncidentCode($tipo) {
        $prefijo = 'INC';
        switch ($tipo) {
            case 'sistema':
                $prefijo = 'INCS';
                break;
            case 'calidad':
                $prefijo = 'INCQ';
                break;
            case 'seguridad':
                $prefijo = 'INCSEC';
                break;
        }
        
        $year = date('Y');
        $month = date('m');
        
        // Obtener el siguiente número secuencial
        $sql = "SELECT COUNT(*) + 1 as next_num 
                FROM incidentes 
                WHERE codigo_incidente LIKE :pattern 
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
    
    private function calculateEstimatedResolution($prioridad) {
        $dias = 30; // Por defecto
        
        switch ($prioridad) {
            case 'critica':
                $dias = 1;
                break;
            case 'alta':
                $dias = 3;
                break;
            case 'media':
                $dias = 7;
                break;
            case 'baja':
                $dias = 15;
                break;
        }
        
        return date('Y-m-d', strtotime("+$dias days"));
    }
}

// Manejo de solicitudes API
if ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $manager = new IncidentManagementManager();
        $action = $_GET['action'] ?? $_POST['action'] ?? '';
        
        switch ($action) {
            case 'list':
                $filters = [
                    'tipo_incidente' => $_GET['tipo_incidente'] ?? null,
                    'prioridad' => $_GET['prioridad'] ?? null,
                    'estado' => $_GET['estado'] ?? null,
                    'usuario_asignado' => $_GET['usuario_asignado'] ?? null
                ];
                echo json_encode($manager->getIncidentes($filters));
                break;
                
            case 'create':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $data = json_decode(file_get_contents('php://input'), true);
                    echo json_encode($manager->createIncidente($data));
                } else {
                    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
                }
                break;
                
            case 'update':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $id = $_POST['id'] ?? $_GET['id'] ?? null;
                    $data = json_decode(file_get_contents('php://input'), true);
                    echo json_encode($manager->updateIncidente($id, $data));
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