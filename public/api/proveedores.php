<?php

/**
 * API para gestión de proveedores
 * Endpoints:
 * GET /api/proveedores - Listar proveedores
 * GET /api/proveedores/{id} - Obtener proveedor específico
 * POST /api/proveedores - Crear nuevo proveedor
 * PUT /api/proveedores/{id} - Actualizar proveedor
 * DELETE /api/proveedores/{id} - Eliminar proveedor
 * GET /api/proveedores/stats - Estadísticas de proveedores
 * POST /api/proveedores/{id}/toggle-status - Cambiar estado activo/inactivo
 * GET /api/proveedores/active - Obtener solo proveedores activos
 */

require_once __DIR__ . '/BaseAPI.php';

class ProveedoresAPI extends BaseAPI
{
    /**
     * Obtener todos los proveedores
     */
    protected function getAll(): void
    {
        if (!$this->checkPermission('proveedores_ver')) {
            $this->errorResponse('Sin permisos para ver proveedores', 403);
        }

        try {
            $base_query = "
                SELECT 
                    p.id,
                    p.nombre,
                    p.direccion,
                    p.telefono,
                    p.email,
                    p.contacto_principal,
                    p.servicios_ofrecidos,
                    p.certificaciones,
                    p.activo,
                    p.notas,
                    p.fecha_registro,
                    p.fecha_actualizacion,
                    COUNT(c.id) as total_calibraciones,
                    MAX(c.fecha_calibracion) as ultima_calibracion,
                    AVG(c.costo_total) as costo_promedio
                FROM proveedores p
                LEFT JOIN calibraciones c ON p.id = c.proveedor_id
                WHERE p.empresa_id = ?
            ";

            $params = [$this->empresa_id];

            // Aplicar filtros
            $activo = $this->getParam('activo');
            if ($activo !== null) {
                $base_query .= " AND p.activo = ?";
                $params[] = $activo;
            }

            $search = $this->getParam('search');
            if ($search) {
                $base_query .= " AND (p.nombre LIKE ? OR p.direccion LIKE ? OR p.email LIKE ? OR p.contacto_principal LIKE ?)";
                $search_param = '%' . $search . '%';
                $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
            }

            $servicio = $this->getParam('servicio');
            if ($servicio) {
                $base_query .= " AND p.servicios_ofrecidos LIKE ?";
                $params[] = '%' . $servicio . '%';
            }

            // Agrupar para las funciones agregadas
            $base_query .= " GROUP BY p.id, p.nombre, p.direccion, p.telefono, p.email, p.contacto_principal, p.servicios_ofrecidos, p.certificaciones, p.activo, p.notas, p.fecha_registro, p.fecha_actualizacion";

            // Aplicar ordenamiento
            $base_query = $this->applySorting($base_query, [
                'nombre', 'fecha_registro', 'total_calibraciones', 'ultima_calibracion', 'activo'
            ]);

            // Si no hay ordenamiento específico, ordenar por nombre
            if (!$this->getParam('sort_by')) {
                $base_query .= " ORDER BY p.nombre ASC";
            }

            // Contar total (sin GROUP BY para obtener el conteo correcto)
            $count_query = "
                SELECT COUNT(DISTINCT p.id)
                FROM proveedores p
                LEFT JOIN calibraciones c ON p.id = c.proveedor_id
                WHERE p.empresa_id = ?
            ";
            
            $count_params = [$this->empresa_id];
            
            if ($activo !== null) {
                $count_query .= " AND p.activo = ?";
                $count_params[] = $activo;
            }
            
            if ($search) {
                $count_query .= " AND (p.nombre LIKE ? OR p.direccion LIKE ? OR p.email LIKE ? OR p.contacto_principal LIKE ?)";
                $search_param = '%' . $search . '%';
                $count_params = array_merge($count_params, [$search_param, $search_param, $search_param, $search_param]);
            }
            
            if ($servicio) {
                $count_query .= " AND p.servicios_ofrecidos LIKE ?";
                $count_params[] = '%' . $servicio . '%';
            }

            $count_stmt = $this->pdo->prepare($count_query);
            $count_stmt->execute($count_params);
            $total_count = $count_stmt->fetchColumn();

            // Aplicar paginación
            $pagination_result = $this->applyPagination($base_query);
            
            $stmt = $this->pdo->prepare($pagination_result['query']);
            $stmt->execute($params);
            $proveedores = $stmt->fetchAll();

            // Procesar datos adicionales
            foreach ($proveedores as &$proveedor) {
                // Convertir servicios y certificaciones de texto a array
                $proveedor['servicios_array'] = $proveedor['servicios_ofrecidos'] 
                    ? explode(',', $proveedor['servicios_ofrecidos']) 
                    : [];
                
                $proveedor['certificaciones_array'] = $proveedor['certificaciones'] 
                    ? explode(',', $proveedor['certificaciones']) 
                    : [];

                // Formatear fecha de última calibración
                if ($proveedor['ultima_calibracion']) {
                    $proveedor['dias_desde_ultima'] = (time() - strtotime($proveedor['ultima_calibracion'])) / (60 * 60 * 24);
                    $proveedor['dias_desde_ultima'] = round($proveedor['dias_desde_ultima']);
                }

                // Formatear costo promedio
                $proveedor['costo_promedio'] = $proveedor['costo_promedio'] ? floatval($proveedor['costo_promedio']) : 0;
            }

            $this->logActivity('proveedores_listado', [
                'total_encontrados' => $total_count,
                'filtros_aplicados' => array_filter([
                    'activo' => $activo,
                    'search' => $search,
                    'servicio' => $servicio
                ])
            ]);

            $this->paginatedResponse($proveedores, $pagination_result['pagination'], $total_count);
            
        } catch (Exception $e) {
            $this->errorResponse('Error al obtener proveedores: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener proveedor por ID
     */
    protected function getById(int $id): void
    {
        if (!$this->checkPermission('proveedores_ver')) {
            $this->errorResponse('Sin permisos para ver proveedores', 403);
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    p.*,
                    COUNT(c.id) as total_calibraciones,
                    MAX(c.fecha_calibracion) as ultima_calibracion,
                    AVG(c.costo_total) as costo_promedio,
                    SUM(CASE WHEN c.estado = 'Aprobado' THEN 1 ELSE 0 END) as calibraciones_aprobadas,
                    SUM(CASE WHEN c.estado = 'Pendiente' THEN 1 ELSE 0 END) as calibraciones_pendientes
                FROM proveedores p
                LEFT JOIN calibraciones c ON p.id = c.proveedor_id
                WHERE p.id = ? AND p.empresa_id = ?
                GROUP BY p.id
            ");
            
            $stmt->execute([$id, $this->empresa_id]);
            $proveedor = $stmt->fetch();

            if (!$proveedor) {
                $this->errorResponse('Proveedor no encontrado', 404);
            }

            // Procesar datos adicionales
            $proveedor['servicios_array'] = $proveedor['servicios_ofrecidos'] 
                ? explode(',', $proveedor['servicios_ofrecidos']) 
                : [];
            
            $proveedor['certificaciones_array'] = $proveedor['certificaciones'] 
                ? explode(',', $proveedor['certificaciones']) 
                : [];

            if ($proveedor['ultima_calibracion']) {
                $proveedor['dias_desde_ultima'] = (time() - strtotime($proveedor['ultima_calibracion'])) / (60 * 60 * 24);
                $proveedor['dias_desde_ultima'] = round($proveedor['dias_desde_ultima']);
            }

            $proveedor['costo_promedio'] = $proveedor['costo_promedio'] ? floatval($proveedor['costo_promedio']) : 0;

            // Obtener historial reciente de calibraciones
            $stmt = $this->pdo->prepare("
                SELECT 
                    c.id,
                    c.fecha_calibracion,
                    c.costo_total,
                    c.estado,
                    c.tipo,
                    i.codigo as instrumento_codigo,
                    i.nombre as instrumento_nombre
                FROM calibraciones c
                LEFT JOIN instrumentos i ON c.instrumento_id = i.id
                WHERE c.proveedor_id = ? AND c.empresa_id = ?
                ORDER BY c.fecha_calibracion DESC
                LIMIT 10
            ");
            $stmt->execute([$id, $this->empresa_id]);
            $proveedor['calibraciones_recientes'] = $stmt->fetchAll();

            // Obtener estadísticas por tipo de servicio
            $stmt = $this->pdo->prepare("
                SELECT 
                    c.tipo,
                    COUNT(*) as cantidad,
                    AVG(c.costo_total) as costo_promedio,
                    AVG(c.duracion_horas) as duracion_promedio
                FROM calibraciones c
                WHERE c.proveedor_id = ? AND c.empresa_id = ?
                GROUP BY c.tipo
                ORDER BY cantidad DESC
            ");
            $stmt->execute([$id, $this->empresa_id]);
            $proveedor['estadisticas_por_tipo'] = $stmt->fetchAll();

            $this->logActivity('proveedor_consulta', ['proveedor_id' => $id]);
            $this->successResponse($proveedor);
            
        } catch (Exception $e) {
            $this->errorResponse('Error al obtener proveedor: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Crear nuevo proveedor
     */
    protected function create(): void
    {
        if (!$this->checkPermission('proveedores_crear')) {
            $this->errorResponse('Sin permisos para crear proveedores', 403);
        }

        $this->validateRequired(['nombre']);

        try {
            // Verificar que no exista otro proveedor con el mismo nombre
            $stmt = $this->pdo->prepare("
                SELECT id FROM proveedores 
                WHERE nombre = ? AND empresa_id = ?
            ");
            $stmt->execute([$this->body_params['nombre'], $this->empresa_id]);
            
            if ($stmt->fetch()) {
                $this->errorResponse('Ya existe un proveedor con ese nombre', 400);
            }

            // Verificar email único si se proporciona
            if (!empty($this->body_params['email'])) {
                $stmt = $this->pdo->prepare("
                    SELECT id FROM proveedores 
                    WHERE email = ? AND empresa_id = ?
                ");
                $stmt->execute([$this->body_params['email'], $this->empresa_id]);
                
                if ($stmt->fetch()) {
                    $this->errorResponse('Ya existe un proveedor con ese email', 400);
                }

                // Validar formato de email
                if (!filter_var($this->body_params['email'], FILTER_VALIDATE_EMAIL)) {
                    $this->errorResponse('Formato de email inválido', 400);
                }
            }

            // Procesar servicios y certificaciones (convertir arrays a strings)
            $servicios_ofrecidos = '';
            if (!empty($this->body_params['servicios_ofrecidos'])) {
                if (is_array($this->body_params['servicios_ofrecidos'])) {
                    $servicios_ofrecidos = implode(',', $this->body_params['servicios_ofrecidos']);
                } else {
                    $servicios_ofrecidos = $this->body_params['servicios_ofrecidos'];
                }
            }

            $certificaciones = '';
            if (!empty($this->body_params['certificaciones'])) {
                if (is_array($this->body_params['certificaciones'])) {
                    $certificaciones = implode(',', $this->body_params['certificaciones']);
                } else {
                    $certificaciones = $this->body_params['certificaciones'];
                }
            }

            // Insertar proveedor
            $stmt = $this->pdo->prepare("
                INSERT INTO proveedores (
                    empresa_id, nombre, direccion, telefono, email,
                    contacto_principal, servicios_ofrecidos, certificaciones,
                    activo, notas, fecha_registro, fecha_actualizacion
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            $stmt->execute([
                $this->empresa_id,
                $this->body_params['nombre'],
                $this->body_params['direccion'] ?? null,
                $this->body_params['telefono'] ?? null,
                $this->body_params['email'] ?? null,
                $this->body_params['contacto_principal'] ?? null,
                $servicios_ofrecidos,
                $certificaciones,
                $this->body_params['activo'] ?? 1,
                $this->body_params['notas'] ?? null
            ]);

            $proveedor_id = $this->pdo->lastInsertId();

            $this->logActivity('proveedor_creado', [
                'proveedor_id' => $proveedor_id,
                'nombre' => $this->body_params['nombre'],
                'servicios' => $servicios_ofrecidos
            ]);

            $this->successResponse([
                'id' => $proveedor_id,
                'message' => 'Proveedor creado exitosamente'
            ], 201);
            
        } catch (Exception $e) {
            $this->errorResponse('Error al crear proveedor: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Actualizar proveedor
     */
    protected function update(int $id): void
    {
        if (!$this->checkPermission('proveedores_editar')) {
            $this->errorResponse('Sin permisos para editar proveedores', 403);
        }

        try {
            // Verificar que el proveedor existe
            $stmt = $this->pdo->prepare("
                SELECT id, nombre FROM proveedores 
                WHERE id = ? AND empresa_id = ?
            ");
            $stmt->execute([$id, $this->empresa_id]);
            $proveedor = $stmt->fetch();

            if (!$proveedor) {
                $this->errorResponse('Proveedor no encontrado', 404);
            }

            // Verificar nombre único si se está cambiando
            if (!empty($this->body_params['nombre']) && $this->body_params['nombre'] !== $proveedor['nombre']) {
                $stmt = $this->pdo->prepare("
                    SELECT id FROM proveedores 
                    WHERE nombre = ? AND empresa_id = ? AND id != ?
                ");
                $stmt->execute([$this->body_params['nombre'], $this->empresa_id, $id]);
                
                if ($stmt->fetch()) {
                    $this->errorResponse('Ya existe otro proveedor con ese nombre', 400);
                }
            }

            // Verificar email único si se proporciona
            if (!empty($this->body_params['email'])) {
                if (!filter_var($this->body_params['email'], FILTER_VALIDATE_EMAIL)) {
                    $this->errorResponse('Formato de email inválido', 400);
                }

                $stmt = $this->pdo->prepare("
                    SELECT id FROM proveedores 
                    WHERE email = ? AND empresa_id = ? AND id != ?
                ");
                $stmt->execute([$this->body_params['email'], $this->empresa_id, $id]);
                
                if ($stmt->fetch()) {
                    $this->errorResponse('Ya existe otro proveedor con ese email', 400);
                }
            }

            // Construir query de actualización
            $fields = [];
            $params = [];
            
            $allowed_fields = [
                'nombre', 'direccion', 'telefono', 'email', 'contacto_principal',
                'activo', 'notas'
            ];

            foreach ($allowed_fields as $field) {
                if (array_key_exists($field, $this->body_params)) {
                    $fields[] = "$field = ?";
                    $params[] = $this->body_params[$field];
                }
            }

            // Procesar servicios_ofrecidos
            if (array_key_exists('servicios_ofrecidos', $this->body_params)) {
                $servicios = '';
                if (is_array($this->body_params['servicios_ofrecidos'])) {
                    $servicios = implode(',', $this->body_params['servicios_ofrecidos']);
                } else {
                    $servicios = $this->body_params['servicios_ofrecidos'] ?? '';
                }
                $fields[] = "servicios_ofrecidos = ?";
                $params[] = $servicios;
            }

            // Procesar certificaciones
            if (array_key_exists('certificaciones', $this->body_params)) {
                $certificaciones = '';
                if (is_array($this->body_params['certificaciones'])) {
                    $certificaciones = implode(',', $this->body_params['certificaciones']);
                } else {
                    $certificaciones = $this->body_params['certificaciones'] ?? '';
                }
                $fields[] = "certificaciones = ?";
                $params[] = $certificaciones;
            }

            if (!empty($fields)) {
                $fields[] = "fecha_actualizacion = NOW()";
                $params[] = $id;
                $params[] = $this->empresa_id;

                $stmt = $this->pdo->prepare("
                    UPDATE proveedores 
                    SET " . implode(', ', $fields) . "
                    WHERE id = ? AND empresa_id = ?
                ");
                $stmt->execute($params);
            }

            $this->logActivity('proveedor_actualizado', [
                'proveedor_id' => $id,
                'campos_actualizados' => array_keys($this->body_params)
            ]);

            $this->successResponse(['message' => 'Proveedor actualizado exitosamente']);
            
        } catch (Exception $e) {
            $this->errorResponse('Error al actualizar proveedor: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar proveedor
     */
    protected function delete(int $id): void
    {
        if (!$this->checkPermission('proveedores_eliminar')) {
            $this->errorResponse('Sin permisos para eliminar proveedores', 403);
        }

        try {
            // Verificar que el proveedor existe
            $stmt = $this->pdo->prepare("
                SELECT id, nombre FROM proveedores 
                WHERE id = ? AND empresa_id = ?
            ");
            $stmt->execute([$id, $this->empresa_id]);
            $proveedor = $stmt->fetch();

            if (!$proveedor) {
                $this->errorResponse('Proveedor no encontrado', 404);
            }

            // Verificar si el proveedor tiene calibraciones asociadas
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM calibraciones 
                WHERE proveedor_id = ? AND empresa_id = ?
            ");
            $stmt->execute([$id, $this->empresa_id]);
            $calibraciones_count = $stmt->fetchColumn();

            if ($calibraciones_count > 0) {
                $this->errorResponse('No se puede eliminar el proveedor porque tiene calibraciones asociadas', 400);
            }

            // Eliminar proveedor
            $stmt = $this->pdo->prepare("DELETE FROM proveedores WHERE id = ? AND empresa_id = ?");
            $stmt->execute([$id, $this->empresa_id]);

            $this->logActivity('proveedor_eliminado', [
                'proveedor_id' => $id,
                'nombre' => $proveedor['nombre']
            ]);

            $this->successResponse(['message' => 'Proveedor eliminado exitosamente']);
            
        } catch (Exception $e) {
            $this->errorResponse('Error al eliminar proveedor: ' . $e->getMessage(), 500);
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
            case 'active':
                $this->getActiveProviders();
                break;
            case 'services':
                $this->getAvailableServices();
                break;
            default:
                $this->errorResponse('Endpoint no encontrado', 404);
        }
    }

    protected function handleCustomPost(array $segments): void
    {
        if (count($segments) >= 2 && is_numeric($segments[0])) {
            $proveedor_id = intval($segments[0]);
            $action = $segments[1];

            switch ($action) {
                case 'toggle-status':
                    $this->toggleProviderStatus($proveedor_id);
                    break;
                default:
                    $this->errorResponse('Acción no encontrada', 404);
            }
        } else {
            $this->errorResponse('Endpoint no encontrado', 404);
        }
    }

    /**
     * Obtener estadísticas de proveedores
     */
    private function getStats(): void
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as activos,
                    SUM(CASE WHEN activo = 0 THEN 1 ELSE 0 END) as inactivos
                FROM proveedores 
                WHERE empresa_id = ?
            ");
            
            $stmt->execute([$this->empresa_id]);
            $stats = $stmt->fetch();

            // Estadísticas de calibraciones por proveedor
            $stmt = $this->pdo->prepare("
                SELECT 
                    p.nombre,
                    COUNT(c.id) as total_calibraciones,
                    SUM(c.costo_total) as costo_total,
                    AVG(c.costo_total) as costo_promedio
                FROM proveedores p
                LEFT JOIN calibraciones c ON p.id = c.proveedor_id
                WHERE p.empresa_id = ? AND p.activo = 1
                GROUP BY p.id, p.nombre
                HAVING total_calibraciones > 0
                ORDER BY total_calibraciones DESC
                LIMIT 10
            ");
            $stmt->execute([$this->empresa_id]);
            $stats['top_proveedores'] = $stmt->fetchAll();

            // Servicios más ofrecidos
            $stmt = $this->pdo->prepare("
                SELECT servicios_ofrecidos FROM proveedores 
                WHERE empresa_id = ? AND servicios_ofrecidos IS NOT NULL AND servicios_ofrecidos != ''
            ");
            $stmt->execute([$this->empresa_id]);
            $servicios_raw = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $servicios_count = [];
            foreach ($servicios_raw as $servicios) {
                $servicios_array = explode(',', $servicios);
                foreach ($servicios_array as $servicio) {
                    $servicio = trim($servicio);
                    if ($servicio) {
                        $servicios_count[$servicio] = ($servicios_count[$servicio] ?? 0) + 1;
                    }
                }
            }
            arsort($servicios_count);
            $stats['servicios_populares'] = array_slice($servicios_count, 0, 10, true);

            $this->successResponse($stats);
            
        } catch (Exception $e) {
            $this->errorResponse('Error al obtener estadísticas: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener solo proveedores activos (para selects)
     */
    private function getActiveProviders(): void
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    id, nombre, contacto_principal, telefono, email,
                    servicios_ofrecidos
                FROM proveedores 
                WHERE empresa_id = ? AND activo = 1
                ORDER BY nombre ASC
            ");
            
            $stmt->execute([$this->empresa_id]);
            $proveedores = $stmt->fetchAll();

            // Procesar servicios para cada proveedor
            foreach ($proveedores as &$proveedor) {
                $proveedor['servicios_array'] = $proveedor['servicios_ofrecidos'] 
                    ? explode(',', $proveedor['servicios_ofrecidos']) 
                    : [];
            }

            $this->successResponse($proveedores);
            
        } catch (Exception $e) {
            $this->errorResponse('Error al obtener proveedores activos: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener servicios disponibles (únicos)
     */
    private function getAvailableServices(): void
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT servicios_ofrecidos FROM proveedores 
                WHERE empresa_id = ? AND servicios_ofrecidos IS NOT NULL AND servicios_ofrecidos != ''
            ");
            
            $stmt->execute([$this->empresa_id]);
            $servicios_raw = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $servicios_unicos = [];
            foreach ($servicios_raw as $servicios) {
                $servicios_array = explode(',', $servicios);
                foreach ($servicios_array as $servicio) {
                    $servicio = trim($servicio);
                    if ($servicio && !in_array($servicio, $servicios_unicos)) {
                        $servicios_unicos[] = $servicio;
                    }
                }
            }
            
            sort($servicios_unicos);
            $this->successResponse($servicios_unicos);
            
        } catch (Exception $e) {
            $this->errorResponse('Error al obtener servicios: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Cambiar estado activo/inactivo del proveedor
     */
    private function toggleProviderStatus(int $proveedor_id): void
    {
        if (!$this->checkPermission('proveedores_editar')) {
            $this->errorResponse('Sin permisos para cambiar estado de proveedores', 403);
        }

        try {
            // Obtener estado actual
            $stmt = $this->pdo->prepare("
                SELECT activo, nombre FROM proveedores 
                WHERE id = ? AND empresa_id = ?
            ");
            $stmt->execute([$proveedor_id, $this->empresa_id]);
            $proveedor = $stmt->fetch();

            if (!$proveedor) {
                $this->errorResponse('Proveedor no encontrado', 404);
            }

            $nuevo_estado = $proveedor['activo'] ? 0 : 1;

            $stmt = $this->pdo->prepare("
                UPDATE proveedores 
                SET activo = ?, fecha_actualizacion = NOW()
                WHERE id = ? AND empresa_id = ?
            ");
            $stmt->execute([$nuevo_estado, $proveedor_id, $this->empresa_id]);

            $this->logActivity('proveedor_estado_cambiado', [
                'proveedor_id' => $proveedor_id,
                'nombre' => $proveedor['nombre'],
                'estado_anterior' => $proveedor['activo'],
                'estado_nuevo' => $nuevo_estado
            ]);

            $this->successResponse([
                'message' => 'Estado del proveedor actualizado exitosamente',
                'nuevo_estado' => $nuevo_estado
            ]);
            
        } catch (Exception $e) {
            $this->errorResponse('Error al cambiar estado del proveedor: ' . $e->getMessage(), 500);
        }
    }
}

// Ejecutar API
$api = new ProveedoresAPI();
$api->handleRequest();