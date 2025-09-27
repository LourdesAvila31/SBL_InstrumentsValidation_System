<?php
/**
 * Módulo de Sistema de Auditoría - SBL Sistema Interno
 * 
 * Este módulo maneja todas las auditorías internas, seguimiento de cambios
 * y cumplimiento de normativas ISO 17025 y GAMP5
 * 
 * Funcionalidades:
 * - Registro de auditorías internas
 * - Seguimiento de hallazgos y no conformidades
 * - Planes de acción correctiva
 * - Reportes de cumplimiento
 * - Historial de cambios (Change Control)
 * - Documentación de evidencias
 * 
 * @author Sistema SBL
 * @version 1.0
 * @since 2025-09-26
 */

// Incluir configuración simplificada
require_once __DIR__ . '/config_simple.php';

class AuditSystemManager {
    
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
     * Obtener todas las auditorías
     * 
     * @param array $filters Filtros opcionales
     * @return array Lista de auditorías
     */
    public function getAuditorias($filters = []) {
        try {
            $sql = "SELECT 
                        a.id,
                        a.codigo_auditoria,
                        a.tipo_auditoria,
                        a.alcance,
                        a.objetivo,
                        a.fecha_inicio,
                        a.fecha_fin,
                        a.estado,
                        a.auditor_lider,
                        a.equipo_auditor,
                        a.areas_auditadas,
                        a.normas_aplicables,
                        a.observaciones,
                        a.fecha_creacion,
                        a.usuario_creacion,
                        u.nombre_completo as auditor_nombre
                    FROM auditorias a
                    LEFT JOIN usuarios u ON a.auditor_lider = u.id
                    WHERE a.activo = 1";
            
            $params = [];
            
            // Aplicar filtros
            if (!empty($filters['tipo_auditoria'])) {
                $sql .= " AND a.tipo_auditoria = :tipo_auditoria";
                $params[':tipo_auditoria'] = $filters['tipo_auditoria'];
            }
            
            if (!empty($filters['estado'])) {
                $sql .= " AND a.estado = :estado";
                $params[':estado'] = $filters['estado'];
            }
            
            if (!empty($filters['auditor_lider'])) {
                $sql .= " AND a.auditor_lider = :auditor_lider";
                $params[':auditor_lider'] = $filters['auditor_lider'];
            }
            
            if (!empty($filters['fecha_desde'])) {
                $sql .= " AND DATE(a.fecha_inicio) >= :fecha_desde";
                $params[':fecha_desde'] = $filters['fecha_desde'];
            }
            
            if (!empty($filters['fecha_hasta'])) {
                $sql .= " AND DATE(a.fecha_fin) <= :fecha_hasta";
                $params[':fecha_hasta'] = $filters['fecha_hasta'];
            }
            
            $sql .= " ORDER BY a.fecha_inicio DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $auditorias = $stmt->fetchAll();
            
            // Procesar arrays JSON
            foreach ($auditorias as &$auditoria) {
                if (!empty($auditoria['equipo_auditor'])) {
                    $auditoria['equipo_auditor'] = json_decode($auditoria['equipo_auditor'], true);
                }
                if (!empty($auditoria['areas_auditadas'])) {
                    $auditoria['areas_auditadas'] = json_decode($auditoria['areas_auditadas'], true);
                }
                if (!empty($auditoria['normas_aplicables'])) {
                    $auditoria['normas_aplicables'] = json_decode($auditoria['normas_aplicables'], true);
                }
            }
            
            return [
                'success' => true,
                'data' => $auditorias,
                'total' => count($auditorias)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al obtener auditorías: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Crear nueva auditoría
     * 
     * @param array $data Datos de la auditoría
     * @return array Resultado de la operación
     */
    public function createAuditoria($data) {
        try {
            // Validar datos requeridos
            $required = ['tipo_auditoria', 'alcance', 'objetivo', 'fecha_inicio', 'auditor_lider'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return [
                        'success' => false,
                        'error' => "El campo '$field' es requerido"
                    ];
                }
            }
            
            // Generar código de auditoría
            $codigo = $this->generateAuditCode($data['tipo_auditoria']);
            
            $sql = "INSERT INTO auditorias (
                        codigo_auditoria,
                        tipo_auditoria,
                        alcance,
                        objetivo,
                        fecha_inicio,
                        fecha_fin,
                        estado,
                        auditor_lider,
                        equipo_auditor,
                        areas_auditadas,
                        normas_aplicables,
                        observaciones,
                        fecha_creacion,
                        usuario_creacion
                    ) VALUES (
                        :codigo_auditoria,
                        :tipo_auditoria,
                        :alcance,
                        :objetivo,
                        :fecha_inicio,
                        :fecha_fin,
                        'planificada',
                        :auditor_lider,
                        :equipo_auditor,
                        :areas_auditadas,
                        :normas_aplicables,
                        :observaciones,
                        NOW(),
                        :usuario_creacion
                    )";
            
            $params = [
                ':codigo_auditoria' => $codigo,
                ':tipo_auditoria' => $data['tipo_auditoria'],
                ':alcance' => $data['alcance'],
                ':objetivo' => $data['objetivo'],
                ':fecha_inicio' => $data['fecha_inicio'],
                ':fecha_fin' => $data['fecha_fin'] ?? null,
                ':auditor_lider' => $data['auditor_lider'],
                ':equipo_auditor' => !empty($data['equipo_auditor']) ? json_encode($data['equipo_auditor']) : null,
                ':areas_auditadas' => !empty($data['areas_auditadas']) ? json_encode($data['areas_auditadas']) : null,
                ':normas_aplicables' => !empty($data['normas_aplicables']) ? json_encode($data['normas_aplicables']) : null,
                ':observaciones' => $data['observaciones'] ?? null,
                ':usuario_creacion' => $this->auth->getUserId()
            ];
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                $auditoriaId = $this->db->lastInsertId();
                
                return [
                    'success' => true,
                    'message' => 'Auditoría creada exitosamente',
                    'id' => $auditoriaId,
                    'codigo' => $codigo
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Error al crear auditoría'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al crear auditoría: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Generar código único de auditoría
     */
    private function generateAuditCode($tipo) {
        $prefijo = 'AUD';
        switch ($tipo) {
            case 'interna':
                $prefijo = 'AI';
                break;
            case 'externa':
                $prefijo = 'AE';
                break;
            case 'certificacion':
                $prefijo = 'AC';
                break;
            case 'seguimiento':
                $prefijo = 'AS';
                break;
        }
        
        $year = date('Y');
        $month = date('m');
        
        // Obtener el siguiente número secuencial
        $sql = "SELECT COUNT(*) + 1 as next_num 
                FROM auditorias 
                WHERE codigo_auditoria LIKE :pattern 
                AND YEAR(fecha_creacion) = :year";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':pattern' => $prefijo . '-' . $year . '%',
            ':year' => $year
        ]);
        $result = $stmt->fetch();
        
        $num = str_pad($result['next_num'], 3, '0', STR_PAD_LEFT);
        
        return $prefijo . '-' . $year . $month . '-' . $num;
    }
    
    /**
     * Obtener hallazgos de auditoría
     * 
     * @param array $filters Filtros opcionales
     * @return array Lista de hallazgos
     */
    public function getHallazgos($filters = []) {
        try {
            $sql = "SELECT 
                        h.id,
                        h.auditoria_id,
                        h.codigo_hallazgo,
                        h.tipo_hallazgo,
                        h.severidad,
                        h.descripcion,
                        h.evidencia,
                        h.requisito_incumplido,
                        h.area_afectada,
                        h.responsable_area,
                        h.fecha_deteccion,
                        h.estado,
                        h.fecha_limite_respuesta,
                        h.accion_correctiva,
                        h.fecha_implementacion,
                        h.verificacion_eficacia,
                        h.fecha_cierre,
                        a.codigo_auditoria,
                        u.nombre_completo as responsable_nombre
                    FROM audit_hallazgos h
                    LEFT JOIN auditorias a ON h.auditoria_id = a.id
                    LEFT JOIN usuarios u ON h.responsable_area = u.id
                    WHERE h.activo = 1";
            
            $params = [];
            
            // Aplicar filtros
            if (!empty($filters['auditoria_id'])) {
                $sql .= " AND h.auditoria_id = :auditoria_id";
                $params[':auditoria_id'] = $filters['auditoria_id'];
            }
            
            if (!empty($filters['tipo_hallazgo'])) {
                $sql .= " AND h.tipo_hallazgo = :tipo_hallazgo";
                $params[':tipo_hallazgo'] = $filters['tipo_hallazgo'];
            }
            
            if (!empty($filters['severidad'])) {
                $sql .= " AND h.severidad = :severidad";
                $params[':severidad'] = $filters['severidad'];
            }
            
            if (!empty($filters['estado'])) {
                $sql .= " AND h.estado = :estado";
                $params[':estado'] = $filters['estado'];
            }
            
            $sql .= " ORDER BY h.severidad DESC, h.fecha_deteccion DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $hallazgos = $stmt->fetchAll();
            
            return [
                'success' => true,
                'data' => $hallazgos,
                'total' => count($hallazgos)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al obtener hallazgos: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Crear nuevo hallazgo
     * 
     * @param array $data Datos del hallazgo
     * @return array Resultado de la operación
     */
    public function createHallazgo($data) {
        try {
            // Validar datos requeridos
            $required = ['auditoria_id', 'tipo_hallazgo', 'severidad', 'descripcion', 'area_afectada'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return [
                        'success' => false,
                        'error' => "El campo '$field' es requerido"
                    ];
                }
            }
            
            // Generar código de hallazgo
            $codigo = $this->generateFindingCode($data['auditoria_id'], $data['tipo_hallazgo']);
            
            // Calcular fecha límite de respuesta según severidad
            $fecha_limite = $this->calculateResponseDeadline($data['severidad']);
            
            $sql = "INSERT INTO audit_hallazgos (
                        auditoria_id,
                        codigo_hallazgo,
                        tipo_hallazgo,
                        severidad,
                        descripcion,
                        evidencia,
                        requisito_incumplido,
                        area_afectada,
                        responsable_area,
                        fecha_deteccion,
                        estado,
                        fecha_limite_respuesta,
                        fecha_creacion,
                        usuario_creacion
                    ) VALUES (
                        :auditoria_id,
                        :codigo_hallazgo,
                        :tipo_hallazgo,
                        :severidad,
                        :descripcion,
                        :evidencia,
                        :requisito_incumplido,
                        :area_afectada,
                        :responsable_area,
                        :fecha_deteccion,
                        'abierto',
                        :fecha_limite_respuesta,
                        NOW(),
                        :usuario_creacion
                    )";
            
            $params = [
                ':auditoria_id' => $data['auditoria_id'],
                ':codigo_hallazgo' => $codigo,
                ':tipo_hallazgo' => $data['tipo_hallazgo'],
                ':severidad' => $data['severidad'],
                ':descripcion' => $data['descripcion'],
                ':evidencia' => $data['evidencia'] ?? null,
                ':requisito_incumplido' => $data['requisito_incumplido'] ?? null,
                ':area_afectada' => $data['area_afectada'],
                ':responsable_area' => $data['responsable_area'] ?? null,
                ':fecha_deteccion' => $data['fecha_deteccion'] ?? date('Y-m-d'),
                ':fecha_limite_respuesta' => $fecha_limite,
                ':usuario_creacion' => $this->auth->getUserId()
            ];
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                $hallazgoId = $this->db->lastInsertId();
                
                return [
                    'success' => true,
                    'message' => 'Hallazgo creado exitosamente',
                    'id' => $hallazgoId,
                    'codigo' => $codigo
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Error al crear hallazgo'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al crear hallazgo: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Generar código único de hallazgo
     */
    private function generateFindingCode($auditoriaId, $tipo) {
        // Obtener código de auditoría
        $sql = "SELECT codigo_auditoria FROM auditorias WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $auditoriaId]);
        $auditoria = $stmt->fetch();
        
        $prefijo = 'HAL';
        switch ($tipo) {
            case 'no_conformidad_mayor':
                $prefijo = 'NCM';
                break;
            case 'no_conformidad_menor':
                $prefijo = 'NCm';
                break;
            case 'observacion':
                $prefijo = 'OBS';
                break;
            case 'oportunidad_mejora':
                $prefijo = 'OM';
                break;
        }
        
        // Obtener el siguiente número secuencial para esta auditoría
        $sql = "SELECT COUNT(*) + 1 as next_num 
                FROM audit_hallazgos 
                WHERE auditoria_id = :auditoria_id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':auditoria_id' => $auditoriaId]);
        $result = $stmt->fetch();
        
        $num = str_pad($result['next_num'], 2, '0', STR_PAD_LEFT);
        
        return $auditoria['codigo_auditoria'] . '-' . $prefijo . '-' . $num;
    }
    
    /**
     * Calcular fecha límite de respuesta según severidad
     */
    private function calculateResponseDeadline($severidad) {
        $dias = 30; // Por defecto
        
        switch ($severidad) {
            case 'critica':
                $dias = 7;
                break;
            case 'alta':
                $dias = 15;
                break;
            case 'media':
                $dias = 30;
                break;
            case 'baja':
                $dias = 60;
                break;
        }
        
        return date('Y-m-d', strtotime("+$dias days"));
    }
    
    /**
     * Obtener estadísticas de auditoría
     * 
     * @return array Estadísticas
     */
    public function getEstadisticas() {
        try {
            // Estadísticas de auditorías
            $sql_auditorias = "SELECT 
                                COUNT(*) as total_auditorias,
                                COUNT(CASE WHEN estado = 'planificada' THEN 1 END) as planificadas,
                                COUNT(CASE WHEN estado = 'en_proceso' THEN 1 END) as en_proceso,
                                COUNT(CASE WHEN estado = 'completada' THEN 1 END) as completadas,
                                COUNT(CASE WHEN YEAR(fecha_inicio) = YEAR(CURDATE()) THEN 1 END) as este_año
                              FROM auditorias 
                              WHERE activo = 1";
            
            $stmt = $this->db->prepare($sql_auditorias);
            $stmt->execute();
            $auditorias_stats = $stmt->fetch();
            
            // Estadísticas de hallazgos
            $sql_hallazgos = "SELECT 
                               COUNT(*) as total_hallazgos,
                               COUNT(CASE WHEN estado = 'abierto' THEN 1 END) as abiertos,
                               COUNT(CASE WHEN estado = 'en_proceso' THEN 1 END) as en_proceso,
                               COUNT(CASE WHEN estado = 'cerrado' THEN 1 END) as cerrados,
                               COUNT(CASE WHEN severidad = 'critica' AND estado != 'cerrado' THEN 1 END) as criticos_abiertos,
                               COUNT(CASE WHEN fecha_limite_respuesta < CURDATE() AND estado != 'cerrado' THEN 1 END) as vencidos
                             FROM audit_hallazgos 
                             WHERE activo = 1";
            
            $stmt = $this->db->prepare($sql_hallazgos);
            $stmt->execute();
            $hallazgos_stats = $stmt->fetch();
            
            return [
                'success' => true,
                'data' => [
                    'auditorias' => $auditorias_stats,
                    'hallazgos' => $hallazgos_stats
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al obtener estadísticas: ' . $e->getMessage()
            ];
        }
    }
}

// Manejo de solicitudes API
if ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $manager = new AuditSystemManager();
        $action = $_GET['action'] ?? $_POST['action'] ?? '';
        
        switch ($action) {
            case 'auditorias':
                $filters = [
                    'tipo_auditoria' => $_GET['tipo_auditoria'] ?? null,
                    'estado' => $_GET['estado'] ?? null,
                    'auditor_lider' => $_GET['auditor_lider'] ?? null,
                    'fecha_desde' => $_GET['fecha_desde'] ?? null,
                    'fecha_hasta' => $_GET['fecha_hasta'] ?? null
                ];
                echo json_encode($manager->getAuditorias($filters));
                break;
                
            case 'create_auditoria':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $data = json_decode(file_get_contents('php://input'), true);
                    echo json_encode($manager->createAuditoria($data));
                } else {
                    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
                }
                break;
                
            case 'hallazgos':
                $filters = [
                    'auditoria_id' => $_GET['auditoria_id'] ?? null,
                    'tipo_hallazgo' => $_GET['tipo_hallazgo'] ?? null,
                    'severidad' => $_GET['severidad'] ?? null,
                    'estado' => $_GET['estado'] ?? null
                ];
                echo json_encode($manager->getHallazgos($filters));
                break;
                
            case 'create_hallazgo':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $data = json_decode(file_get_contents('php://input'), true);
                    echo json_encode($manager->createHallazgo($data));
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