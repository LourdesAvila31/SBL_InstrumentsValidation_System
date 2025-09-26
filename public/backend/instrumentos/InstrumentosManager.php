<?php
/**
 * Módulo de Gestión de Instrumentos - SBL Sistema Interno
 * 
 * Este módulo maneja todas las operaciones CRUD para instrumentos de medición
 * según estándares ISO 17025 y NOM-059
 * 
 * Funcionalidades:
 * - Listar instrumentos registrados
 * - Agregar nuevos instrumentos 
 * - Editar instrumentos existentes
 * - Eliminar instrumentos
 * - Buscar y filtrar instrumentos
 * - Gestión de estado de calibración
 * 
 * @author Sistema SBL
 * @version 1.0
 * @since 2025-09-25
 */

// Incluir configuración simplificada
require_once __DIR__ . '/config_simple.php';

class InstrumentosManager {
    
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
     * Obtener lista de todos los instrumentos
     * 
     * @param array $filters Filtros opcionales
     * @return array Lista de instrumentos
     */
    public function getInstrumentos($filters = []) {
        try {
            $sql = "SELECT 
                        i.id,
                        i.codigo_identificacion,
                        i.descripcion,
                        i.marca,
                        i.modelo,
                        i.numero_serie,
                        i.rango_medicion,
                        i.resolucion,
                        i.incertidumbre,
                        i.ubicacion,
                        i.estado,
                        i.fecha_ultima_calibracion,
                        i.fecha_proxima_calibracion,
                        i.proveedor_calibracion,
                        i.certificado_calibracion,
                        i.observaciones,
                        i.fecha_registro,
                        i.usuario_registro,
                        i.fecha_modificacion,
                        i.usuario_modificacion,
                        i.activo
                    FROM instrumentos i 
                    WHERE i.activo = 1";
            
            $params = [];
            
            // Aplicar filtros
            if (!empty($filters['codigo'])) {
                $sql .= " AND i.codigo_identificacion LIKE :codigo";
                $params[':codigo'] = '%' . $filters['codigo'] . '%';
            }
            
            if (!empty($filters['descripcion'])) {
                $sql .= " AND i.descripcion LIKE :descripcion";
                $params[':descripcion'] = '%' . $filters['descripcion'] . '%';
            }
            
            if (!empty($filters['estado'])) {
                $sql .= " AND i.estado = :estado";
                $params[':estado'] = $filters['estado'];
            }
            
            if (!empty($filters['ubicacion'])) {
                $sql .= " AND i.ubicacion LIKE :ubicacion";
                $params[':ubicacion'] = '%' . $filters['ubicacion'] . '%';
            }
            
            // Ordenar por fecha de próxima calibración
            $sql .= " ORDER BY i.fecha_proxima_calibracion ASC, i.codigo_identificacion ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            $instrumentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Agregar indicadores de estado
            foreach ($instrumentos as &$instrumento) {
                $instrumento['estado_calibracion'] = $this->determinarEstadoCalibracion($instrumento);
                $instrumento['dias_hasta_calibracion'] = $this->calcularDiasHastaCalibracion($instrumento['fecha_proxima_calibracion']);
            }
            
            return $instrumentos;
            
        } catch (Exception $e) {
            error_log("Error al obtener instrumentos: " . $e->getMessage());
            throw new Exception("Error al obtener la lista de instrumentos");
        }
    }
    
    /**
     * Obtener un instrumento específico por ID
     * 
     * @param int $id ID del instrumento
     * @return array|null Datos del instrumento
     */
    public function getInstrumento($id) {
        try {
            $sql = "SELECT * FROM instrumentos WHERE id = :id AND activo = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id]);
            
            $instrumento = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($instrumento) {
                $instrumento['estado_calibracion'] = $this->determinarEstadoCalibracion($instrumento);
                $instrumento['dias_hasta_calibracion'] = $this->calcularDiasHastaCalibracion($instrumento['fecha_proxima_calibracion']);
            }
            
            return $instrumento;
            
        } catch (Exception $e) {
            error_log("Error al obtener instrumento ID $id: " . $e->getMessage());
            throw new Exception("Error al obtener el instrumento");
        }
    }
    
    /**
     * Crear un nuevo instrumento
     * 
     * @param array $data Datos del instrumento
     * @return int ID del instrumento creado
     */
    public function crearInstrumento($data) {
        try {
            // Verificar permisos
            if (!$this->auth->hasPermission('instrumentos_crear')) {
                throw new Exception("No tiene permisos para crear instrumentos");
            }
            
            // Validar datos requeridos
            $this->validarDatosInstrumento($data);
            
            // Verificar que el código no exista
            if ($this->existeCodigoInstrumento($data['codigo_identificacion'])) {
                throw new Exception("Ya existe un instrumento con el código: " . $data['codigo_identificacion']);
            }
            
            $sql = "INSERT INTO instrumentos (
                        codigo_identificacion,
                        descripcion,
                        marca,
                        modelo,
                        numero_serie,
                        rango_medicion,
                        resolucion,
                        incertidumbre,
                        ubicacion,
                        estado,
                        fecha_ultima_calibracion,
                        fecha_proxima_calibracion,
                        proveedor_calibracion,
                        certificado_calibracion,
                        observaciones,
                        fecha_registro,
                        usuario_registro,
                        activo
                    ) VALUES (
                        :codigo_identificacion,
                        :descripcion,
                        :marca,
                        :modelo,
                        :numero_serie,
                        :rango_medicion,
                        :resolucion,
                        :incertidumbre,
                        :ubicacion,
                        :estado,
                        :fecha_ultima_calibracion,
                        :fecha_proxima_calibracion,
                        :proveedor_calibracion,
                        :certificado_calibracion,
                        :observaciones,
                        NOW(),
                        :usuario_registro,
                        1
                    )";
            
            $params = [
                ':codigo_identificacion' => $data['codigo_identificacion'],
                ':descripcion' => $data['descripcion'],
                ':marca' => $data['marca'] ?? '',
                ':modelo' => $data['modelo'] ?? '',
                ':numero_serie' => $data['numero_serie'] ?? '',
                ':rango_medicion' => $data['rango_medicion'] ?? '',
                ':resolucion' => $data['resolucion'] ?? '',
                ':incertidumbre' => $data['incertidumbre'] ?? '',
                ':ubicacion' => $data['ubicacion'] ?? '',
                ':estado' => $data['estado'] ?? 'ACTIVO',
                ':fecha_ultima_calibracion' => $data['fecha_ultima_calibracion'] ?? null,
                ':fecha_proxima_calibracion' => $data['fecha_proxima_calibracion'] ?? null,
                ':proveedor_calibracion' => $data['proveedor_calibracion'] ?? '',
                ':certificado_calibracion' => $data['certificado_calibracion'] ?? '',
                ':observaciones' => $data['observaciones'] ?? '',
                ':usuario_registro' => $_SESSION['usuario_id']
            ];
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            $instrumentoId = $this->db->lastInsertId();
            
            // Registrar en log de auditoría
            $this->registrarAuditoria('CREATE', $instrumentoId, 'Instrumento creado', $data);
            
            return $instrumentoId;
            
        } catch (Exception $e) {
            error_log("Error al crear instrumento: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Actualizar un instrumento existente
     * 
     * @param int $id ID del instrumento
     * @param array $data Nuevos datos del instrumento
     * @return bool True si se actualizó correctamente
     */
    public function actualizarInstrumento($id, $data) {
        try {
            // Verificar permisos
            if (!$this->auth->hasPermission('instrumentos_editar')) {
                throw new Exception("No tiene permisos para editar instrumentos");
            }
            
            // Verificar que el instrumento existe
            $instrumentoActual = $this->getInstrumento($id);
            if (!$instrumentoActual) {
                throw new Exception("Instrumento no encontrado");
            }
            
            // Validar datos
            $this->validarDatosInstrumento($data);
            
            // Verificar código único (excluyendo el actual)
            if ($this->existeCodigoInstrumento($data['codigo_identificacion'], $id)) {
                throw new Exception("Ya existe otro instrumento con el código: " . $data['codigo_identificacion']);
            }
            
            $sql = "UPDATE instrumentos SET
                        codigo_identificacion = :codigo_identificacion,
                        descripcion = :descripcion,
                        marca = :marca,
                        modelo = :modelo,
                        numero_serie = :numero_serie,
                        rango_medicion = :rango_medicion,
                        resolucion = :resolucion,
                        incertidumbre = :incertidumbre,
                        ubicacion = :ubicacion,
                        estado = :estado,
                        fecha_ultima_calibracion = :fecha_ultima_calibracion,
                        fecha_proxima_calibracion = :fecha_proxima_calibracion,
                        proveedor_calibracion = :proveedor_calibracion,
                        certificado_calibracion = :certificado_calibracion,
                        observaciones = :observaciones,
                        fecha_modificacion = NOW(),
                        usuario_modificacion = :usuario_modificacion
                    WHERE id = :id";
            
            $params = [
                ':id' => $id,
                ':codigo_identificacion' => $data['codigo_identificacion'],
                ':descripcion' => $data['descripcion'],
                ':marca' => $data['marca'] ?? '',
                ':modelo' => $data['modelo'] ?? '',
                ':numero_serie' => $data['numero_serie'] ?? '',
                ':rango_medicion' => $data['rango_medicion'] ?? '',
                ':resolucion' => $data['resolucion'] ?? '',
                ':incertidumbre' => $data['incertidumbre'] ?? '',
                ':ubicacion' => $data['ubicacion'] ?? '',
                ':estado' => $data['estado'] ?? 'ACTIVO',
                ':fecha_ultima_calibracion' => $data['fecha_ultima_calibracion'] ?? null,
                ':fecha_proxima_calibracion' => $data['fecha_proxima_calibracion'] ?? null,
                ':proveedor_calibracion' => $data['proveedor_calibracion'] ?? '',
                ':certificado_calibracion' => $data['certificado_calibracion'] ?? '',
                ':observaciones' => $data['observaciones'] ?? '',
                ':usuario_modificacion' => $_SESSION['usuario_id']
            ];
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);
            
            // Registrar en log de auditoría
            $this->registrarAuditoria('UPDATE', $id, 'Instrumento actualizado', $data, $instrumentoActual);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Error al actualizar instrumento ID $id: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Eliminar un instrumento (soft delete)
     * 
     * @param int $id ID del instrumento
     * @return bool True si se eliminó correctamente
     */
    public function eliminarInstrumento($id) {
        try {
            // Verificar permisos
            if (!$this->auth->hasPermission('instrumentos_eliminar')) {
                throw new Exception("No tiene permisos para eliminar instrumentos");
            }
            
            // Verificar que el instrumento existe
            $instrumento = $this->getInstrumento($id);
            if (!$instrumento) {
                throw new Exception("Instrumento no encontrado");
            }
            
            // Soft delete
            $sql = "UPDATE instrumentos SET 
                        activo = 0,
                        fecha_modificacion = NOW(),
                        usuario_modificacion = :usuario_modificacion
                    WHERE id = :id";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':id' => $id,
                ':usuario_modificacion' => $_SESSION['usuario_id']
            ]);
            
            // Registrar en log de auditoría
            $this->registrarAuditoria('DELETE', $id, 'Instrumento eliminado', [], $instrumento);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Error al eliminar instrumento ID $id: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Validar datos del instrumento
     */
    private function validarDatosInstrumento($data) {
        $errores = [];
        
        if (empty($data['codigo_identificacion'])) {
            $errores[] = "El código de identificación es requerido";
        }
        
        if (empty($data['descripcion'])) {
            $errores[] = "La descripción es requerida";
        }
        
        if (!empty($data['fecha_ultima_calibracion']) && !$this->validarFecha($data['fecha_ultima_calibracion'])) {
            $errores[] = "Fecha de última calibración inválida";
        }
        
        if (!empty($data['fecha_proxima_calibracion']) && !$this->validarFecha($data['fecha_proxima_calibracion'])) {
            $errores[] = "Fecha de próxima calibración inválida";
        }
        
        if (!empty($errores)) {
            throw new Exception("Errores de validación: " . implode(", ", $errores));
        }
    }
    
    /**
     * Verificar si existe un código de instrumento
     */
    private function existeCodigoInstrumento($codigo, $excludeId = null) {
        $sql = "SELECT id FROM instrumentos WHERE codigo_identificacion = :codigo AND activo = 1";
        $params = [':codigo' => $codigo];
        
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Determinar estado de calibración del instrumento
     */
    private function determinarEstadoCalibracion($instrumento) {
        if (empty($instrumento['fecha_proxima_calibracion'])) {
            return 'SIN_PROGRAMAR';
        }
        
        $fechaProxima = new DateTime($instrumento['fecha_proxima_calibracion']);
        $fechaActual = new DateTime();
        $diasDiferencia = $fechaActual->diff($fechaProxima)->days;
        
        if ($fechaProxima < $fechaActual) {
            return 'VENCIDO';
        } elseif ($diasDiferencia <= 30) {
            return 'PROXIMO_A_VENCER';
        } else {
            return 'VIGENTE';
        }
    }
    
    /**
     * Calcular días hasta próxima calibración
     */
    private function calcularDiasHastaCalibracion($fechaProxima) {
        if (empty($fechaProxima)) {
            return null;
        }
        
        $fecha = new DateTime($fechaProxima);
        $hoy = new DateTime();
        $diferencia = $hoy->diff($fecha);
        
        return $fecha >= $hoy ? $diferencia->days : -$diferencia->days;
    }
    
    /**
     * Validar formato de fecha
     */
    private function validarFecha($fecha) {
        $d = DateTime::createFromFormat('Y-m-d', $fecha);
        return $d && $d->format('Y-m-d') === $fecha;
    }
    
    /**
     * Registrar auditoría
     */
    private function registrarAuditoria($accion, $instrumentoId, $descripcion, $datosNuevos = [], $datosAnteriores = []) {
        try {
            $sql = "INSERT INTO auditoria_instrumentos (
                        instrumento_id,
                        usuario_id,
                        accion,
                        descripcion,
                        datos_anteriores,
                        datos_nuevos,
                        fecha_auditoria,
                        ip_address
                    ) VALUES (
                        :instrumento_id,
                        :usuario_id,
                        :accion,
                        :descripcion,
                        :datos_anteriores,
                        :datos_nuevos,
                        NOW(),
                        :ip_address
                    )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':instrumento_id' => $instrumentoId,
                ':usuario_id' => $_SESSION['usuario_id'],
                ':accion' => $accion,
                ':descripcion' => $descripcion,
                ':datos_anteriores' => json_encode($datosAnteriores),
                ':datos_nuevos' => json_encode($datosNuevos),
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
        } catch (Exception $e) {
            // No fallar si la auditoría falla, solo registrar el error
            error_log("Error al registrar auditoría: " . $e->getMessage());
        }
    }
}
?>