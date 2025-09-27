<?php
/**
 * Módulo de Sistema de Alertas - SBL Sistema Interno
 * 
 * Este módulo maneja todas las alertas del sistema, notificaciones
 * automáticas y gestión de eventos críticos
 * 
 * Funcionalidades:
 * - Gestión de alertas automáticas
 * - Notificaciones por email y SMS  
 * - Configuración de reglas de alertas
 * - Dashboard de alertas activas
 * - Historial de alertas
 * - Escalamiento de alertas críticas
 * 
 * @author Sistema SBL
 * @version 1.0
 * @since 2025-09-26
 */

// Incluir configuración simplificada
require_once __DIR__ . '/config_simple.php';

class AlertSystemManager {
    
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
     * Obtener todas las alertas
     * 
     * @param array $filters Filtros opcionales
     * @return array Lista de alertas
     */
    public function getAlertas($filters = []) {
        try {
            $sql = "SELECT 
                        a.id,
                        a.tipo,
                        a.prioridad,
                        a.titulo,
                        a.mensaje,
                        a.origen,
                        a.referencia_id,
                        a.estado,
                        a.fecha_creacion,
                        a.fecha_leida,
                        a.fecha_resuelta,
                        a.usuario_asignado,
                        a.usuario_resolucion,
                        a.datos_adicionales,
                        a.activo,
                        u1.nombre_completo as asignado_nombre,
                        u2.nombre_completo as resolucion_nombre
                    FROM alertas a
                    LEFT JOIN usuarios u1 ON a.usuario_asignado = u1.id
                    LEFT JOIN usuarios u2 ON a.usuario_resolucion = u2.id
                    WHERE a.activo = 1";
            
            $params = [];
            
            // Aplicar filtros
            if (!empty($filters['tipo'])) {
                $sql .= " AND a.tipo = :tipo";
                $params[':tipo'] = $filters['tipo'];
            }
            
            if (!empty($filters['prioridad'])) {
                $sql .= " AND a.prioridad = :prioridad";
                $params[':prioridad'] = $filters['prioridad'];
            }
            
            if (!empty($filters['estado'])) {
                $sql .= " AND a.estado = :estado";
                $params[':estado'] = $filters['estado'];
            }
            
            if (!empty($filters['origen'])) {
                $sql .= " AND a.origen = :origen";
                $params[':origen'] = $filters['origen'];
            }
            
            if (!empty($filters['usuario_asignado'])) {
                $sql .= " AND a.usuario_asignado = :usuario_asignado";
                $params[':usuario_asignado'] = $filters['usuario_asignado'];
            }
            
            if (!empty($filters['fecha_desde'])) {
                $sql .= " AND DATE(a.fecha_creacion) >= :fecha_desde";
                $params[':fecha_desde'] = $filters['fecha_desde'];
            }
            
            if (!empty($filters['fecha_hasta'])) {
                $sql .= " AND DATE(a.fecha_creacion) <= :fecha_hasta";
                $params[':fecha_hasta'] = $filters['fecha_hasta'];
            }
            
            // Ordenar por prioridad y fecha
            $sql .= " ORDER BY 
                        CASE a.prioridad 
                            WHEN 'critica' THEN 1 
                            WHEN 'alta' THEN 2 
                            WHEN 'media' THEN 3 
                            WHEN 'baja' THEN 4 
                        END ASC,
                        a.fecha_creacion DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $alertas = $stmt->fetchAll();
            
            // Procesar datos adicionales JSON
            foreach ($alertas as &$alerta) {
                if (!empty($alerta['datos_adicionales'])) {
                    $alerta['datos_adicionales'] = json_decode($alerta['datos_adicionales'], true);
                }
            }
            
            return [
                'success' => true,
                'data' => $alertas,
                'total' => count($alertas)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al obtener alertas: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Crear nueva alerta
     * 
     * @param array $data Datos de la alerta
     * @return array Resultado de la operación
     */
    public function createAlerta($data) {
        try {
            // Validar datos requeridos
            $required = ['tipo', 'prioridad', 'titulo', 'mensaje'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return [
                        'success' => false,
                        'error' => "El campo '$field' es requerido"
                    ];
                }
            }
            
            // Validar valores permitidos
            $tipos_validos = ['sistema', 'calibracion', 'instrumento', 'usuario', 'backup', 'seguridad'];
            $prioridades_validas = ['baja', 'media', 'alta', 'critica'];
            
            if (!in_array($data['tipo'], $tipos_validos)) {
                return [
                    'success' => false,
                    'error' => 'Tipo de alerta no válido'
                ];
            }
            
            if (!in_array($data['prioridad'], $prioridades_validas)) {
                return [
                    'success' => false,
                    'error' => 'Prioridad de alerta no válida'
                ];
            }
            
            $sql = "INSERT INTO alertas (
                        tipo,
                        prioridad,
                        titulo,
                        mensaje,
                        origen,
                        referencia_id,
                        estado,
                        usuario_asignado,
                        datos_adicionales,
                        fecha_creacion,
                        usuario_creacion
                    ) VALUES (
                        :tipo,
                        :prioridad,
                        :titulo,
                        :mensaje,
                        :origen,
                        :referencia_id,
                        'pendiente',
                        :usuario_asignado,
                        :datos_adicionales,
                        NOW(),
                        :usuario_creacion
                    )";
            
            $params = [
                ':tipo' => $data['tipo'],
                ':prioridad' => $data['prioridad'],
                ':titulo' => $data['titulo'],
                ':mensaje' => $data['mensaje'],
                ':origen' => $data['origen'] ?? 'manual',
                ':referencia_id' => $data['referencia_id'] ?? null,
                ':usuario_asignado' => $data['usuario_asignado'] ?? null,
                ':datos_adicionales' => !empty($data['datos_adicionales']) ? json_encode($data['datos_adicionales']) : null,
                ':usuario_creacion' => $this->auth->getUserId()
            ];
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                $alertaId = $this->db->lastInsertId();
                
                // Enviar notificaciones si es necesario
                $this->processAlertNotifications($alertaId, $data);
                
                return [
                    'success' => true,
                    'message' => 'Alerta creada exitosamente',
                    'id' => $alertaId
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Error al crear alerta'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al crear alerta: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Actualizar estado de alerta
     * 
     * @param int $id ID de la alerta
     * @param array $data Datos a actualizar
     * @return array Resultado de la operación
     */
    public function updateAlerta($id, $data) {
        try {
            // Verificar que la alerta existe
            $sql = "SELECT id, estado FROM alertas WHERE id = :id AND activo = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id]);
            $alerta = $stmt->fetch();
            
            if (!$alerta) {
                return [
                    'success' => false,
                    'error' => 'Alerta no encontrada'
                ];
            }
            
            $updates = [];
            $params = [':id' => $id];
            
            // Construir consulta de actualización dinámicamente
            if (isset($data['estado'])) {
                $estados_validos = ['pendiente', 'en_proceso', 'resuelta', 'cerrada', 'ignorada'];
                if (!in_array($data['estado'], $estados_validos)) {
                    return [
                        'success' => false,
                        'error' => 'Estado de alerta no válido'
                    ];
                }
                
                $updates[] = "estado = :estado";
                $params[':estado'] = $data['estado'];
                
                // Actualizar fechas según el estado
                if ($data['estado'] === 'resuelta' || $data['estado'] === 'cerrada') {
                    $updates[] = "fecha_resuelta = NOW()";
                    $updates[] = "usuario_resolucion = :usuario_resolucion";
                    $params[':usuario_resolucion'] = $this->auth->getUserId();
                }
            }
            
            if (isset($data['usuario_asignado'])) {
                $updates[] = "usuario_asignado = :usuario_asignado";
                $params[':usuario_asignado'] = $data['usuario_asignado'];
            }
            
            if (isset($data['notas_resolucion'])) {
                $updates[] = "notas_resolucion = :notas_resolucion";
                $params[':notas_resolucion'] = $data['notas_resolucion'];
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
            
            $sql = "UPDATE alertas SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Alerta actualizada exitosamente'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Error al actualizar alerta'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al actualizar alerta: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Marcar alerta como leída
     * 
     * @param int $id ID de la alerta
     * @return array Resultado de la operación
     */
    public function markAsRead($id) {
        try {
            $sql = "UPDATE alertas SET 
                        fecha_leida = NOW(),
                        usuario_lectura = :usuario_lectura
                    WHERE id = :id AND activo = 1";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':id' => $id,
                ':usuario_lectura' => $this->auth->getUserId()
            ]);
            
            if ($result && $stmt->rowCount() > 0) {
                return [
                    'success' => true,
                    'message' => 'Alerta marcada como leída'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Alerta no encontrada'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al marcar alerta: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Eliminar alerta (marcar como inactiva)
     * 
     * @param int $id ID de la alerta
     * @return array Resultado de la operación
     */
    public function deleteAlerta($id) {
        try {
            $sql = "UPDATE alertas SET 
                        activo = 0,
                        fecha_eliminacion = NOW(),
                        usuario_eliminacion = :usuario_eliminacion
                    WHERE id = :id";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':id' => $id,
                ':usuario_eliminacion' => $this->auth->getUserId()
            ]);
            
            if ($result && $stmt->rowCount() > 0) {
                return [
                    'success' => true,
                    'message' => 'Alerta eliminada exitosamente'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Alerta no encontrada'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al eliminar alerta: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener estadísticas de alertas
     * 
     * @return array Estadísticas
     */
    public function getEstadisticas() {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_alertas,
                        COUNT(CASE WHEN estado = 'pendiente' THEN 1 END) as pendientes,
                        COUNT(CASE WHEN estado = 'en_proceso' THEN 1 END) as en_proceso,
                        COUNT(CASE WHEN estado = 'resuelta' THEN 1 END) as resueltas,
                        COUNT(CASE WHEN prioridad = 'critica' AND estado NOT IN ('resuelta', 'cerrada') THEN 1 END) as criticas_activas,
                        COUNT(CASE WHEN prioridad = 'alta' AND estado NOT IN ('resuelta', 'cerrada') THEN 1 END) as altas_activas,
                        COUNT(CASE WHEN DATE(fecha_creacion) = CURDATE() THEN 1 END) as alertas_hoy,
                        COUNT(CASE WHEN fecha_leida IS NULL AND estado NOT IN ('resuelta', 'cerrada') THEN 1 END) as no_leidas
                    FROM alertas 
                    WHERE activo = 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $estadisticas = $stmt->fetch();
            
            // Estadísticas por tipo
            $sql_tipos = "SELECT 
                            tipo,
                            COUNT(*) as cantidad,
                            COUNT(CASE WHEN estado NOT IN ('resuelta', 'cerrada') THEN 1 END) as activas
                          FROM alertas 
                          WHERE activo = 1 
                          GROUP BY tipo";
            
            $stmt = $this->db->prepare($sql_tipos);
            $stmt->execute();
            $por_tipo = $stmt->fetchAll();
            
            return [
                'success' => true,
                'data' => [
                    'resumen' => $estadisticas,
                    'por_tipo' => $por_tipo
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al obtener estadísticas: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Verificar alertas automáticas
     * Esta función se ejecuta periódicamente para generar alertas automáticas
     */
    public function checkAutomaticAlerts() {
        try {
            $alertas_generadas = [];
            
            // Verificar instrumentos próximos a vencer
            $alertas_generadas = array_merge($alertas_generadas, $this->checkInstrumentosVencimiento());
            
            // Verificar backups fallidos
            $alertas_generadas = array_merge($alertas_generadas, $this->checkBackupsFallidos());
            
            // Verificar espacio en disco
            $alertas_generadas = array_merge($alertas_generadas, $this->checkEspacioDisco());
            
            // Verificar incidentes sin resolver
            $alertas_generadas = array_merge($alertas_generadas, $this->checkIncidentesSinResolver());
            
            return [
                'success' => true,
                'message' => 'Verificación de alertas automáticas completada',
                'alertas_generadas' => count($alertas_generadas),
                'detalle' => $alertas_generadas
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error en verificación automática: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Verificar instrumentos próximos a vencer
     */
    private function checkInstrumentosVencimiento() {
        $alertas = [];
        
        try {
            // Instrumentos que vencen en 30 días
            $sql = "SELECT id, codigo_identificacion, descripcion, fecha_proxima_calibracion
                    FROM instrumentos 
                    WHERE activo = 1 
                    AND fecha_proxima_calibracion <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                    AND fecha_proxima_calibracion > CURDATE()
                    AND id NOT IN (
                        SELECT referencia_id 
                        FROM alertas 
                        WHERE tipo = 'calibracion' 
                        AND origen = 'automatico'
                        AND referencia_id IS NOT NULL
                        AND estado NOT IN ('resuelta', 'cerrada')
                        AND activo = 1
                    )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $instrumentos = $stmt->fetchAll();
            
            foreach ($instrumentos as $instrumento) {
                $dias_restantes = (new DateTime($instrumento['fecha_proxima_calibracion']))->diff(new DateTime())->days;
                
                $prioridad = 'media';
                if ($dias_restantes <= 7) {
                    $prioridad = 'alta';
                } elseif ($dias_restantes <= 15) {
                    $prioridad = 'media';
                } else {
                    $prioridad = 'baja';
                }
                
                $data = [
                    'tipo' => 'calibracion',
                    'prioridad' => $prioridad,
                    'titulo' => 'Calibración próxima a vencer',
                    'mensaje' => "El instrumento {$instrumento['codigo_identificacion']} - {$instrumento['descripcion']} requiere calibración en {$dias_restantes} días.",
                    'origen' => 'automatico',
                    'referencia_id' => $instrumento['id'],
                    'datos_adicionales' => [
                        'instrumento_id' => $instrumento['id'],
                        'codigo' => $instrumento['codigo_identificacion'],
                        'fecha_vencimiento' => $instrumento['fecha_proxima_calibracion'],
                        'dias_restantes' => $dias_restantes
                    ]
                ];
                
                $result = $this->createAlerta($data);
                if ($result['success']) {
                    $alertas[] = "Alerta de calibración creada para {$instrumento['codigo_identificacion']}";
                }
            }
            
        } catch (Exception $e) {
            error_log("Error verificando vencimientos: " . $e->getMessage());
        }
        
        return $alertas;
    }
    
    /**
     * Verificar backups fallidos
     */
    private function checkBackupsFallidos() {
        $alertas = [];
        
        try {
            // Backups fallidos en las últimas 24 horas
            $sql = "SELECT COUNT(*) as fallidos 
                    FROM backups 
                    WHERE estado = 'fallido' 
                    AND fecha_backup >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result['fallidos'] > 0) {
                // Verificar si ya existe una alerta similar
                $sql_check = "SELECT COUNT(*) as existe 
                              FROM alertas 
                              WHERE tipo = 'backup' 
                              AND estado NOT IN ('resuelta', 'cerrada')
                              AND DATE(fecha_creacion) = CURDATE()
                              AND activo = 1";
                
                $stmt = $this->db->prepare($sql_check);
                $stmt->execute();
                $check = $stmt->fetch();
                
                if ($check['existe'] == 0) {
                    $data = [
                        'tipo' => 'backup',
                        'prioridad' => 'alta',
                        'titulo' => 'Backups fallidos detectados',
                        'mensaje' => "Se han detectado {$result['fallidos']} backups fallidos en las últimas 24 horas.",
                        'origen' => 'automatico',
                        'datos_adicionales' => [
                            'backups_fallidos' => $result['fallidos'],
                            'periodo' => '24 horas'
                        ]
                    ];
                    
                    $result = $this->createAlerta($data);
                    if ($result['success']) {
                        $alertas[] = "Alerta de backups fallidos creada";
                    }
                }
            }
            
        } catch (Exception $e) {
            error_log("Error verificando backups: " . $e->getMessage());
        }
        
        return $alertas;
    }
    
    /**
     * Verificar espacio en disco
     */
    private function checkEspacioDisco() {
        $alertas = [];
        
        try {
            $total = disk_total_space(__DIR__);
            $free = disk_free_space(__DIR__);
            $used_percent = (($total - $free) / $total) * 100;
            
            if ($used_percent > 90) {
                // Verificar si ya existe una alerta similar
                $sql_check = "SELECT COUNT(*) as existe 
                              FROM alertas 
                              WHERE tipo = 'sistema' 
                              AND titulo LIKE '%espacio en disco%'
                              AND estado NOT IN ('resuelta', 'cerrada')
                              AND DATE(fecha_creacion) = CURDATE()
                              AND activo = 1";
                
                $stmt = $this->db->prepare($sql_check);
                $stmt->execute();
                $check = $stmt->fetch();
                
                if ($check['existe'] == 0) {
                    $prioridad = $used_percent > 95 ? 'critica' : 'alta';
                    
                    $data = [
                        'tipo' => 'sistema',
                        'prioridad' => $prioridad,
                        'titulo' => 'Espacio en disco bajo',
                        'mensaje' => "El espacio en disco está al {$used_percent}% de capacidad.",
                        'origen' => 'automatico',
                        'datos_adicionales' => [
                            'porcentaje_usado' => round($used_percent, 2),
                            'espacio_libre' => $this->formatBytes($free),
                            'espacio_total' => $this->formatBytes($total)
                        ]
                    ];
                    
                    $result = $this->createAlerta($data);
                    if ($result['success']) {
                        $alertas[] = "Alerta de espacio en disco creada";
                    }
                }
            }
            
        } catch (Exception $e) {
            error_log("Error verificando espacio en disco: " . $e->getMessage());
        }
        
        return $alertas;
    }
    
    /**
     * Verificar incidentes sin resolver
     */
    private function checkIncidentesSinResolver() {
        $alertas = [];
        
        try {
            // Incidentes críticos sin resolver por más de 4 horas
            $sql = "SELECT COUNT(*) as criticos 
                    FROM incidentes 
                    WHERE prioridad = 'alta' 
                    AND estado NOT IN ('resuelto', 'cerrado')
                    AND fecha_creacion <= DATE_SUB(NOW(), INTERVAL 4 HOUR)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result['criticos'] > 0) {
                // Verificar si ya existe una alerta similar
                $sql_check = "SELECT COUNT(*) as existe 
                              FROM alertas 
                              WHERE tipo = 'sistema' 
                              AND titulo LIKE '%incidentes sin resolver%'
                              AND estado NOT IN ('resuelta', 'cerrada')
                              AND DATE(fecha_creacion) = CURDATE()
                              AND activo = 1";
                
                $stmt = $this->db->prepare($sql_check);
                $stmt->execute();
                $check = $stmt->fetch();
                
                if ($check['existe'] == 0) {
                    $data = [
                        'tipo' => 'sistema',
                        'prioridad' => 'alta',
                        'titulo' => 'Incidentes críticos sin resolver',
                        'mensaje' => "Hay {$result['criticos']} incidentes críticos sin resolver por más de 4 horas.",
                        'origen' => 'automatico',
                        'datos_adicionales' => [
                            'incidentes_criticos' => $result['criticos'],
                            'tiempo_limite' => '4 horas'
                        ]
                    ];
                    
                    $result = $this->createAlerta($data);
                    if ($result['success']) {
                        $alertas[] = "Alerta de incidentes sin resolver creada";
                    }
                }
            }
            
        } catch (Exception $e) {
            error_log("Error verificando incidentes: " . $e->getMessage());
        }
        
        return $alertas;
    }
    
    /**
     * Procesar notificaciones de alerta
     */
    private function processAlertNotifications($alertaId, $data) {
        try {
            // Solo procesar notificaciones para alertas críticas o altas
            if (!in_array($data['prioridad'], ['critica', 'alta'])) {
                return;
            }
            
            // Aquí se implementaría el envío de notificaciones
            // Email, SMS, Slack, etc.
            
            // Log de notificación enviada
            error_log("Notificación procesada para alerta ID: $alertaId");
            
        } catch (Exception $e) {
            error_log("Error procesando notificaciones: " . $e->getMessage());
        }
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
}

// Manejo de solicitudes API
if ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $manager = new AlertSystemManager();
        $action = $_GET['action'] ?? $_POST['action'] ?? '';
        
        switch ($action) {
            case 'list':
                $filters = [
                    'tipo' => $_GET['tipo'] ?? null,
                    'prioridad' => $_GET['prioridad'] ?? null,
                    'estado' => $_GET['estado'] ?? null,
                    'origen' => $_GET['origen'] ?? null,
                    'usuario_asignado' => $_GET['usuario_asignado'] ?? null,
                    'fecha_desde' => $_GET['fecha_desde'] ?? null,
                    'fecha_hasta' => $_GET['fecha_hasta'] ?? null
                ];
                echo json_encode($manager->getAlertas($filters));
                break;
                
            case 'create':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $data = json_decode(file_get_contents('php://input'), true);
                    echo json_encode($manager->createAlerta($data));
                } else {
                    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
                }
                break;
                
            case 'update':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $id = $_POST['id'] ?? $_GET['id'] ?? null;
                    $data = json_decode(file_get_contents('php://input'), true);
                    echo json_encode($manager->updateAlerta($id, $data));
                } else {
                    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
                }
                break;
                
            case 'mark_read':
                $id = $_POST['id'] ?? $_GET['id'] ?? null;
                echo json_encode($manager->markAsRead($id));
                break;
                
            case 'delete':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $id = $_POST['id'] ?? $_GET['id'] ?? null;
                    echo json_encode($manager->deleteAlerta($id));
                } else {
                    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
                }
                break;
                
            case 'stats':
                echo json_encode($manager->getEstadisticas());
                break;
                
            case 'check_automatic':
                echo json_encode($manager->checkAutomaticAlerts());
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