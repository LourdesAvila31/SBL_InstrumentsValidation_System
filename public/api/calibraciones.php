<?php

/**
 * API para gestión de calibraciones
 * Endpoints:
 * GET /api/calibraciones - Listar calibraciones
 * GET /api/calibraciones/{id} - Obtener calibración específica
 * POST /api/calibraciones - Crear nueva calibración
 * PUT /api/calibraciones/{id} - Actualizar calibración
 * DELETE /api/calibraciones/{id} - Eliminar calibración
 * GET /api/calibraciones/stats - Estadísticas de calibraciones
 * POST /api/calibraciones/{id}/approve - Aprobar calibración
 * POST /api/calibraciones/{id}/reject - Rechazar calibración
 * GET /api/calibraciones/pending - Calibraciones pendientes
 * GET /api/calibraciones/overdue - Calibraciones vencidas
 */

require_once __DIR__ . '/BaseAPI.php';

class CalibracionesAPI extends BaseAPI
{
    /**
     * Obtener todas las calibraciones
     */
    protected function getAll(): void
    {
        if (!$this->checkPermission('calibraciones_ver')) {
            $this->errorResponse('Sin permisos para ver calibraciones', 403);
        }

        try {
            $base_query = "
                SELECT 
                    c.id,
                    c.instrumento_id,
                    c.tipo,
                    c.fecha_calibracion,
                    c.duracion_horas,
                    c.costo_total,
                    c.periodo,
                    c.fecha_proxima,
                    c.estado,
                    c.resultado_preliminar,
                    c.resultado,
                    c.fecha_liberacion,
                    c.origen_datos,
                    c.observaciones,
                    c.fecha_creacion,
                    i.codigo as instrumento_codigo,
                    i.nombre as instrumento_nombre,
                    i.marca as instrumento_marca,
                    i.modelo as instrumento_modelo,
                    p.nombre as proveedor_nombre,
                    u.nombre as usuario_nombre,
                    u.apellidos as usuario_apellidos,
                    ul.nombre as liberado_por_nombre,
                    ul.apellidos as liberado_por_apellidos,
                    cal.nombre as calibrador_nombre
                FROM calibraciones c
                LEFT JOIN instrumentos i ON c.instrumento_id = i.id
                LEFT JOIN proveedores p ON c.proveedor_id = p.id
                LEFT JOIN usuarios u ON c.usuario_id = u.id
                LEFT JOIN usuarios ul ON c.liberado_por = ul.id
                LEFT JOIN calibradores cal ON c.calibrador_id = cal.id
                WHERE c.empresa_id = ?
            ";

            $params = [$this->empresa_id];

            // Aplicar filtros
            $estado = $this->getParam('estado');
            if ($estado) {
                $base_query .= " AND c.estado = ?";
                $params[] = $estado;
            }

            $tipo = $this->getParam('tipo');
            if ($tipo) {
                $base_query .= " AND c.tipo = ?";
                $params[] = $tipo;
            }

            $instrumento_id = $this->getParam('instrumento_id');
            if ($instrumento_id) {
                $base_query .= " AND c.instrumento_id = ?";
                $params[] = $instrumento_id;
            }

            $proveedor_id = $this->getParam('proveedor_id');
            if ($proveedor_id) {
                $base_query .= " AND c.proveedor_id = ?";
                $params[] = $proveedor_id;
            }

            $fecha_desde = $this->getParam('fecha_desde');
            if ($fecha_desde) {
                $base_query .= " AND c.fecha_calibracion >= ?";
                $params[] = $fecha_desde;
            }

            $fecha_hasta = $this->getParam('fecha_hasta');
            if ($fecha_hasta) {
                $base_query .= " AND c.fecha_calibracion <= ?";
                $params[] = $fecha_hasta;
            }

            $search = $this->getParam('search');
            if ($search) {
                $base_query .= " AND (i.codigo LIKE ? OR i.nombre LIKE ? OR p.nombre LIKE ?)";
                $search_param = '%' . $search . '%';
                $params = array_merge($params, [$search_param, $search_param, $search_param]);
            }

            // Aplicar ordenamiento
            $base_query = $this->applySorting($base_query, [
                'fecha_calibracion', 'fecha_proxima', 'estado', 'tipo', 'costo_total', 'fecha_creacion'
            ]);

            // Si no hay ordenamiento específico, ordenar por fecha de calibración descendente
            if (!$this->getParam('sort_by')) {
                $base_query .= " ORDER BY c.fecha_calibracion DESC";
            }

            // Contar total
            $count_query = str_replace(
                'SELECT c.id, c.instrumento_id, c.tipo, c.fecha_calibracion, c.duracion_horas, c.costo_total, c.periodo, c.fecha_proxima, c.estado, c.resultado_preliminar, c.resultado, c.fecha_liberacion, c.origen_datos, c.observaciones, c.fecha_creacion, i.codigo as instrumento_codigo, i.nombre as instrumento_nombre, i.marca as instrumento_marca, i.modelo as instrumento_modelo, p.nombre as proveedor_nombre, u.nombre as usuario_nombre, u.apellidos as usuario_apellidos, ul.nombre as liberado_por_nombre, ul.apellidos as liberado_por_apellidos, cal.nombre as calibrador_nombre',
                'SELECT COUNT(*)',
                $base_query
            );

            $count_stmt = $this->pdo->prepare($count_query);
            $count_stmt->execute($params);
            $total_count = $count_stmt->fetchColumn();

            // Aplicar paginación
            $pagination_result = $this->applyPagination($base_query);
            
            $stmt = $this->pdo->prepare($pagination_result['query']);
            $stmt->execute($params);
            $calibraciones = $stmt->fetchAll();

            // Agregar información adicional a cada calibración
            foreach ($calibraciones as &$calibracion) {
                // Calcular días hasta próxima calibración
                if ($calibracion['fecha_proxima']) {
                    $dias_proxima = (strtotime($calibracion['fecha_proxima']) - time()) / (60 * 60 * 24);
                    $calibracion['dias_hasta_proxima'] = round($dias_proxima);
                    $calibracion['vencida'] = $dias_proxima < 0;
                    $calibracion['por_vencer'] = $dias_proxima >= 0 && $dias_proxima <= 30;
                }

                // Obtener lecturas si existen
                $stmt_lecturas = $this->pdo->prepare("
                    SELECT COUNT(*) FROM calibraciones_lecturas 
                    WHERE calibracion_id = ?
                ");
                $stmt_lecturas->execute([$calibracion['id']]);
                $calibracion['tiene_lecturas'] = $stmt_lecturas->fetchColumn() > 0;
            }

            $this->logActivity('calibraciones_listado', [
                'total_encontradas' => $total_count,
                'filtros_aplicados' => array_filter([
                    'estado' => $estado,
                    'tipo' => $tipo,
                    'instrumento_id' => $instrumento_id,
                    'proveedor_id' => $proveedor_id,
                    'search' => $search
                ])
            ]);

            $this->paginatedResponse($calibraciones, $pagination_result['pagination'], $total_count);
            
        } catch (Exception $e) {
            $this->errorResponse('Error al obtener calibraciones: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener calibración por ID
     */
    protected function getById(int $id): void
    {
        if (!$this->checkPermission('calibraciones_ver')) {
            $this->errorResponse('Sin permisos para ver calibraciones', 403);
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    c.*,
                    i.codigo as instrumento_codigo,
                    i.nombre as instrumento_nombre,
                    i.marca as instrumento_marca,
                    i.modelo as instrumento_modelo,
                    i.numero_serie as instrumento_serie,
                    p.nombre as proveedor_nombre,
                    p.direccion as proveedor_direccion,
                    u.nombre as usuario_nombre,
                    u.apellidos as usuario_apellidos,
                    ul.nombre as liberado_por_nombre,
                    ul.apellidos as liberado_por_apellidos,
                    cal.nombre as calibrador_nombre,
                    cal.numero_certificado as calibrador_certificado,
                    pat.codigo as patron_codigo,
                    pat.descripcion as patron_descripcion
                FROM calibraciones c
                LEFT JOIN instrumentos i ON c.instrumento_id = i.id
                LEFT JOIN proveedores p ON c.proveedor_id = p.id
                LEFT JOIN usuarios u ON c.usuario_id = u.id
                LEFT JOIN usuarios ul ON c.liberado_por = ul.id
                LEFT JOIN calibradores cal ON c.calibrador_id = cal.id
                LEFT JOIN patrones pat ON c.patron_id = pat.id
                WHERE c.id = ? AND c.empresa_id = ?
            ");
            
            $stmt->execute([$id, $this->empresa_id]);
            $calibracion = $stmt->fetch();

            if (!$calibracion) {
                $this->errorResponse('Calibración no encontrada', 404);
            }

            // Obtener lecturas de la calibración
            $stmt = $this->pdo->prepare("
                SELECT * FROM calibraciones_lecturas 
                WHERE calibracion_id = ? 
                ORDER BY punto_medicion, lectura_numero
            ");
            $stmt->execute([$id]);
            $calibracion['lecturas'] = $stmt->fetchAll();

            // Obtener referencias de la calibración
            $stmt = $this->pdo->prepare("
                SELECT * FROM calibracion_referencias 
                WHERE calibracion_id = ? 
                ORDER BY tipo, etiqueta
            ");
            $stmt->execute([$id]);
            $calibracion['referencias'] = $stmt->fetchAll();

            // Calcular información adicional
            if ($calibracion['fecha_proxima']) {
                $dias_proxima = (strtotime($calibracion['fecha_proxima']) - time()) / (60 * 60 * 24);
                $calibracion['dias_hasta_proxima'] = round($dias_proxima);
                $calibracion['vencida'] = $dias_proxima < 0;
                $calibracion['por_vencer'] = $dias_proxima >= 0 && $dias_proxima <= 30;
            }

            $this->logActivity('calibracion_consulta', ['calibracion_id' => $id]);
            $this->successResponse($calibracion);
            
        } catch (Exception $e) {
            $this->errorResponse('Error al obtener calibración: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Crear nueva calibración
     */
    protected function create(): void
    {
        if (!$this->checkPermission('calibraciones_crear')) {
            $this->errorResponse('Sin permisos para crear calibraciones', 403);
        }

        $this->validateRequired([
            'instrumento_id', 'fecha_calibracion', 'tipo', 'periodo'
        ]);

        try {
            $this->pdo->beginTransaction();

            // Verificar que el instrumento existe y pertenece a la empresa
            $stmt = $this->pdo->prepare("
                SELECT id, nombre FROM instrumentos 
                WHERE id = ? AND empresa_id = ?
            ");
            $stmt->execute([$this->body_params['instrumento_id'], $this->empresa_id]);
            $instrumento = $stmt->fetch();

            if (!$instrumento) {
                $this->errorResponse('Instrumento no válido', 400);
            }

            // Verificar proveedor si se especifica
            if (!empty($this->body_params['proveedor_id'])) {
                $stmt = $this->pdo->prepare("
                    SELECT id FROM proveedores 
                    WHERE id = ? AND empresa_id = ?
                ");
                $stmt->execute([$this->body_params['proveedor_id'], $this->empresa_id]);
                
                if (!$stmt->fetch()) {
                    $this->errorResponse('Proveedor no válido', 400);
                }
            }

            // Calcular fecha próxima si no se especifica
            $fecha_proxima = $this->body_params['fecha_proxima'] ?? null;
            if (!$fecha_proxima) {
                // Obtener periodicidad del instrumento (asumir 1 año por defecto)
                $fecha_calibracion = new DateTime($this->body_params['fecha_calibracion']);
                $fecha_calibracion->add(new DateInterval('P1Y'));
                $fecha_proxima = $fecha_calibracion->format('Y-m-d');
            }

            // Insertar calibración
            $stmt = $this->pdo->prepare("
                INSERT INTO calibraciones (
                    instrumento_id, empresa_id, patron_id, patron_certificado,
                    tipo, fecha_calibracion, duracion_horas, costo_total,
                    periodo, fecha_proxima, proveedor_id, usuario_id,
                    estado, resultado_preliminar, calibrador_id,
                    origen_datos, observaciones, u_value, u_method,
                    u_k, u_coverage, fecha_creacion
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $this->body_params['instrumento_id'],
                $this->empresa_id,
                $this->body_params['patron_id'] ?? null,
                $this->body_params['patron_certificado'] ?? null,
                $this->body_params['tipo'],
                $this->body_params['fecha_calibracion'],
                $this->body_params['duracion_horas'] ?? null,
                $this->body_params['costo_total'] ?? null,
                $this->body_params['periodo'],
                $fecha_proxima,
                $this->body_params['proveedor_id'] ?? null,
                $this->user_id,
                $this->body_params['estado'] ?? 'Pendiente',
                $this->body_params['resultado_preliminar'] ?? null,
                $this->body_params['calibrador_id'] ?? null,
                $this->body_params['origen_datos'] ?? 'manual',
                $this->body_params['observaciones'] ?? null,
                $this->body_params['u_value'] ?? null,
                $this->body_params['u_method'] ?? null,
                $this->body_params['u_k'] ?? null,
                $this->body_params['u_coverage'] ?? null
            ]);

            $calibracion_id = $this->pdo->lastInsertId();

            // Insertar lecturas si se proporcionan
            if (!empty($this->body_params['lecturas']) && is_array($this->body_params['lecturas'])) {
                $stmt_lectura = $this->pdo->prepare("
                    INSERT INTO calibraciones_lecturas (
                        calibracion_id, punto_medicion, lectura_numero,
                        valor_referencia, valor_medido, error, incertidumbre,
                        observaciones
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($this->body_params['lecturas'] as $lectura) {
                    $stmt_lectura->execute([
                        $calibracion_id,
                        $lectura['punto_medicion'] ?? 1,
                        $lectura['lectura_numero'] ?? 1,
                        $lectura['valor_referencia'] ?? null,
                        $lectura['valor_medido'] ?? null,
                        $lectura['error'] ?? null,
                        $lectura['incertidumbre'] ?? null,
                        $lectura['observaciones'] ?? null
                    ]);
                }
            }

            $this->pdo->commit();

            $this->logActivity('calibracion_creada', [
                'calibracion_id' => $calibracion_id,
                'instrumento_id' => $this->body_params['instrumento_id'],
                'instrumento_nombre' => $instrumento['nombre'],
                'tipo' => $this->body_params['tipo']
            ]);

            $this->successResponse([
                'id' => $calibracion_id,
                'message' => 'Calibración creada exitosamente'
            ], 201);
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            $this->errorResponse('Error al crear calibración: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Actualizar calibración
     */
    protected function update(int $id): void
    {
        if (!$this->checkPermission('calibraciones_editar')) {
            $this->errorResponse('Sin permisos para editar calibraciones', 403);
        }

        try {
            // Verificar que la calibración existe
            $stmt = $this->pdo->prepare("
                SELECT id, estado, instrumento_id FROM calibraciones 
                WHERE id = ? AND empresa_id = ?
            ");
            $stmt->execute([$id, $this->empresa_id]);
            $calibracion = $stmt->fetch();

            if (!$calibracion) {
                $this->errorResponse('Calibración no encontrada', 404);
            }

            // No permitir editar calibraciones liberadas sin permisos especiales
            if ($calibracion['estado'] === 'Aprobado' && !$this->checkPermission('calibraciones_editar_aprobadas')) {
                $this->errorResponse('No se pueden editar calibraciones aprobadas', 403);
            }

            $this->pdo->beginTransaction();

            // Construir query de actualización
            $fields = [];
            $params = [];
            
            $allowed_fields = [
                'patron_id', 'patron_certificado', 'tipo', 'fecha_calibracion',
                'duracion_horas', 'costo_total', 'periodo', 'fecha_proxima',
                'proveedor_id', 'resultado_preliminar', 'resultado',
                'calibrador_id', 'observaciones', 'u_value', 'u_method',
                'u_k', 'u_coverage'
            ];

            foreach ($allowed_fields as $field) {
                if (array_key_exists($field, $this->body_params)) {
                    $fields[] = "$field = ?";
                    $params[] = $this->body_params[$field];
                }
            }

            if (!empty($fields)) {
                $params[] = $id;
                $params[] = $this->empresa_id;

                $stmt = $this->pdo->prepare("
                    UPDATE calibraciones 
                    SET " . implode(', ', $fields) . "
                    WHERE id = ? AND empresa_id = ?
                ");
                $stmt->execute($params);
            }

            // Actualizar lecturas si se proporcionan
            if (isset($this->body_params['lecturas']) && is_array($this->body_params['lecturas'])) {
                // Eliminar lecturas existentes
                $stmt = $this->pdo->prepare("DELETE FROM calibraciones_lecturas WHERE calibracion_id = ?");
                $stmt->execute([$id]);

                // Insertar nuevas lecturas
                $stmt_lectura = $this->pdo->prepare("
                    INSERT INTO calibraciones_lecturas (
                        calibracion_id, punto_medicion, lectura_numero,
                        valor_referencia, valor_medido, error, incertidumbre,
                        observaciones
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($this->body_params['lecturas'] as $lectura) {
                    $stmt_lectura->execute([
                        $id,
                        $lectura['punto_medicion'] ?? 1,
                        $lectura['lectura_numero'] ?? 1,
                        $lectura['valor_referencia'] ?? null,
                        $lectura['valor_medido'] ?? null,
                        $lectura['error'] ?? null,
                        $lectura['incertidumbre'] ?? null,
                        $lectura['observaciones'] ?? null
                    ]);
                }
            }

            $this->pdo->commit();

            $this->logActivity('calibracion_actualizada', [
                'calibracion_id' => $id,
                'campos_actualizados' => array_keys($this->body_params)
            ]);

            $this->successResponse(['message' => 'Calibración actualizada exitosamente']);
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            $this->errorResponse('Error al actualizar calibración: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar calibración
     */
    protected function delete(int $id): void
    {
        if (!$this->checkPermission('calibraciones_eliminar')) {
            $this->errorResponse('Sin permisos para eliminar calibraciones', 403);
        }

        try {
            // Verificar que la calibración existe
            $stmt = $this->pdo->prepare("
                SELECT id, estado, instrumento_id FROM calibraciones 
                WHERE id = ? AND empresa_id = ?
            ");
            $stmt->execute([$id, $this->empresa_id]);
            $calibracion = $stmt->fetch();

            if (!$calibracion) {
                $this->errorResponse('Calibración no encontrada', 404);
            }

            // No permitir eliminar calibraciones aprobadas
            if ($calibracion['estado'] === 'Aprobado' && !$this->checkPermission('calibraciones_eliminar_aprobadas')) {
                $this->errorResponse('No se pueden eliminar calibraciones aprobadas', 403);
            }

            $this->pdo->beginTransaction();

            // Eliminar lecturas relacionadas
            $stmt = $this->pdo->prepare("DELETE FROM calibraciones_lecturas WHERE calibracion_id = ?");
            $stmt->execute([$id]);

            // Eliminar referencias relacionadas
            $stmt = $this->pdo->prepare("DELETE FROM calibracion_referencias WHERE calibracion_id = ?");
            $stmt->execute([$id]);

            // Eliminar calibración
            $stmt = $this->pdo->prepare("DELETE FROM calibraciones WHERE id = ? AND empresa_id = ?");
            $stmt->execute([$id, $this->empresa_id]);

            $this->pdo->commit();

            $this->logActivity('calibracion_eliminada', [
                'calibracion_id' => $id,
                'instrumento_id' => $calibracion['instrumento_id']
            ]);

            $this->successResponse(['message' => 'Calibración eliminada exitosamente']);
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            $this->errorResponse('Error al eliminar calibración: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Endpoints personalizados
     */
    protected function handleCustomGet(array $segments): void
    {
        $endpoint = $segments[0] ?? '';

        switch ($endpoint) {
            case 'stats':
                $this->getStats();
                break;
            case 'pending':
                $this->getPending();
                break;
            case 'overdue':
                $this->getOverdue();
                break;
            case 'dashboard':
                $this->getDashboardData();
                break;
            default:
                $this->errorResponse('Endpoint no encontrado', 404);
        }
    }

    protected function handleCustomPost(array $segments): void
    {
        if (count($segments) >= 2 && is_numeric($segments[0])) {
            $calibracion_id = intval($segments[0]);
            $action = $segments[1];

            switch ($action) {
                case 'approve':
                    $this->approveCalibration($calibracion_id);
                    break;
                case 'reject':
                    $this->rejectCalibration($calibracion_id);
                    break;
                default:
                    $this->errorResponse('Acción no encontrada', 404);
            }
        } else {
            $this->errorResponse('Endpoint no encontrado', 404);
        }
    }

    /**
     * Obtener estadísticas de calibraciones
     */
    private function getStats(): void
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN estado = 'Pendiente' THEN 1 ELSE 0 END) as pendientes,
                    SUM(CASE WHEN estado = 'Aprobado' THEN 1 ELSE 0 END) as aprobadas,
                    SUM(CASE WHEN estado = 'Rechazado' THEN 1 ELSE 0 END) as rechazadas,
                    SUM(CASE WHEN fecha_proxima < CURDATE() THEN 1 ELSE 0 END) as vencidas,
                    SUM(CASE WHEN fecha_proxima BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as por_vencer,
                    COALESCE(SUM(costo_total), 0) as costo_total,
                    AVG(duracion_horas) as duracion_promedio
                FROM calibraciones 
                WHERE empresa_id = ?
            ");
            
            $stmt->execute([$this->empresa_id]);
            $stats = $stmt->fetch();

            // Estadísticas por tipo
            $stmt = $this->pdo->prepare("
                SELECT tipo, COUNT(*) as cantidad 
                FROM calibraciones 
                WHERE empresa_id = ? 
                GROUP BY tipo
            ");
            $stmt->execute([$this->empresa_id]);
            $stats['por_tipo'] = $stmt->fetchAll();

            // Estadísticas por mes (últimos 12 meses)
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE_FORMAT(fecha_calibracion, '%Y-%m') as mes,
                    COUNT(*) as cantidad,
                    SUM(costo_total) as costo
                FROM calibraciones 
                WHERE empresa_id = ? 
                AND fecha_calibracion >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(fecha_calibracion, '%Y-%m')
                ORDER BY mes
            ");
            $stmt->execute([$this->empresa_id]);
            $stats['por_mes'] = $stmt->fetchAll();

            $this->successResponse($stats);
            
        } catch (Exception $e) {
            $this->errorResponse('Error al obtener estadísticas: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener calibraciones pendientes
     */
    private function getPending(): void
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    c.id, c.fecha_calibracion, c.tipo,
                    i.codigo as instrumento_codigo,
                    i.nombre as instrumento_nombre,
                    u.nombre as usuario_nombre
                FROM calibraciones c
                LEFT JOIN instrumentos i ON c.instrumento_id = i.id
                LEFT JOIN usuarios u ON c.usuario_id = u.id
                WHERE c.empresa_id = ? AND c.estado = 'Pendiente'
                ORDER BY c.fecha_calibracion DESC
                LIMIT 20
            ");
            
            $stmt->execute([$this->empresa_id]);
            $pending = $stmt->fetchAll();

            $this->successResponse($pending);
            
        } catch (Exception $e) {
            $this->errorResponse('Error al obtener calibraciones pendientes: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener instrumentos con calibraciones vencidas
     */
    private function getOverdue(): void
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    i.id as instrumento_id,
                    i.codigo as instrumento_codigo,
                    i.nombre as instrumento_nombre,
                    MAX(c.fecha_proxima) as ultima_fecha_proxima,
                    DATEDIFF(CURDATE(), MAX(c.fecha_proxima)) as dias_vencido
                FROM instrumentos i
                LEFT JOIN calibraciones c ON i.id = c.instrumento_id
                WHERE i.empresa_id = ? 
                AND i.activo = 1
                AND (
                    c.fecha_proxima < CURDATE() 
                    OR c.fecha_proxima IS NULL
                )
                GROUP BY i.id, i.codigo, i.nombre
                ORDER BY dias_vencido DESC
                LIMIT 50
            ");
            
            $stmt->execute([$this->empresa_id]);
            $overdue = $stmt->fetchAll();

            $this->successResponse($overdue);
            
        } catch (Exception $e) {
            $this->errorResponse('Error al obtener instrumentos vencidos: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Aprobar calibración
     */
    private function approveCalibration(int $calibracion_id): void
    {
        if (!$this->checkPermission('calibraciones_aprobar')) {
            $this->errorResponse('Sin permisos para aprobar calibraciones', 403);
        }

        try {
            $stmt = $this->pdo->prepare("
                UPDATE calibraciones 
                SET estado = 'Aprobado', 
                    liberado_por = ?, 
                    fecha_liberacion = NOW(),
                    resultado = ?
                WHERE id = ? AND empresa_id = ? AND estado = 'Pendiente'
            ");

            $resultado = $this->body_params['resultado'] ?? 'Conforme';
            $affected = $stmt->execute([$this->user_id, $resultado, $calibracion_id, $this->empresa_id]);

            if ($stmt->rowCount() === 0) {
                $this->errorResponse('Calibración no encontrada o no se puede aprobar', 404);
            }

            $this->logActivity('calibracion_aprobada', [
                'calibracion_id' => $calibracion_id,
                'resultado' => $resultado
            ]);

            $this->successResponse(['message' => 'Calibración aprobada exitosamente']);
            
        } catch (Exception $e) {
            $this->errorResponse('Error al aprobar calibración: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Rechazar calibración
     */
    private function rejectCalibration(int $calibracion_id): void
    {
        if (!$this->checkPermission('calibraciones_aprobar')) {
            $this->errorResponse('Sin permisos para rechazar calibraciones', 403);
        }

        $this->validateRequired(['observaciones']);

        try {
            $stmt = $this->pdo->prepare("
                UPDATE calibraciones 
                SET estado = 'Rechazado', 
                    liberado_por = ?, 
                    fecha_liberacion = NOW(),
                    observaciones = ?
                WHERE id = ? AND empresa_id = ? AND estado = 'Pendiente'
            ");

            $affected = $stmt->execute([
                $this->user_id, 
                $this->body_params['observaciones'], 
                $calibracion_id, 
                $this->empresa_id
            ]);

            if ($stmt->rowCount() === 0) {
                $this->errorResponse('Calibración no encontrada o no se puede rechazar', 404);
            }

            $this->logActivity('calibracion_rechazada', [
                'calibracion_id' => $calibracion_id,
                'observaciones' => $this->body_params['observaciones']
            ]);

            $this->successResponse(['message' => 'Calibración rechazada exitosamente']);
            
        } catch (Exception $e) {
            $this->errorResponse('Error al rechazar calibración: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener datos para dashboard
     */
    private function getDashboardData(): void
    {
        try {
            // Resumen general
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_calibraciones,
                    SUM(CASE WHEN estado = 'Pendiente' THEN 1 ELSE 0 END) as pendientes,
                    SUM(CASE WHEN fecha_proxima < CURDATE() THEN 1 ELSE 0 END) as vencidas,
                    SUM(CASE WHEN fecha_proxima BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as por_vencer
                FROM calibraciones 
                WHERE empresa_id = ?
            ");
            $stmt->execute([$this->empresa_id]);
            $resumen = $stmt->fetch();

            // Calibraciones recientes
            $stmt = $this->pdo->prepare("
                SELECT 
                    c.id, c.fecha_calibracion, c.estado,
                    i.codigo as instrumento_codigo,
                    i.nombre as instrumento_nombre
                FROM calibraciones c
                LEFT JOIN instrumentos i ON c.instrumento_id = i.id
                WHERE c.empresa_id = ?
                ORDER BY c.fecha_calibracion DESC
                LIMIT 10
            ");
            $stmt->execute([$this->empresa_id]);
            $recientes = $stmt->fetchAll();

            // Próximas a vencer
            $stmt = $this->pdo->prepare("
                SELECT 
                    i.codigo as instrumento_codigo,
                    i.nombre as instrumento_nombre,
                    MAX(c.fecha_proxima) as fecha_proxima,
                    DATEDIFF(MAX(c.fecha_proxima), CURDATE()) as dias_restantes
                FROM instrumentos i
                LEFT JOIN calibraciones c ON i.id = c.instrumento_id
                WHERE i.empresa_id = ? 
                AND i.activo = 1
                AND c.fecha_proxima BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)
                GROUP BY i.id
                ORDER BY fecha_proxima ASC
                LIMIT 10
            ");
            $stmt->execute([$this->empresa_id]);
            $proximas = $stmt->fetchAll();

            $dashboard = [
                'resumen' => $resumen,
                'calibraciones_recientes' => $recientes,
                'proximas_a_vencer' => $proximas
            ];

            $this->successResponse($dashboard);
            
        } catch (Exception $e) {
            $this->errorResponse('Error al obtener datos del dashboard: ' . $e->getMessage(), 500);
        }
    }
}

// Ejecutar API
$api = new CalibracionesAPI();
$api->handleRequest();