<?php

/**
 * API para gestión de usuarios
 * Endpoints:
 * GET /api/usuarios - Listar usuarios
 * GET /api/usuarios/{id} - Obtener usuario específico
 * POST /api/usuarios - Crear nuevo usuario
 * PUT /api/usuarios/{id} - Actualizar usuario
 * DELETE /api/usuarios/{id} - Eliminar usuario
 * GET /api/usuarios/profile - Obtener perfil del usuario actual
 * PUT /api/usuarios/profile - Actualizar perfil del usuario actual
 * POST /api/usuarios/{id}/toggle-status - Activar/desactivar usuario
 */

require_once __DIR__ . '/BaseAPI.php';

class UsuariosAPI extends BaseAPI
{
    /**
     * Obtener todos los usuarios
     */
    protected function getAll(): void
    {
        // Verificar permisos
        if (!$this->checkPermission('usuarios_ver')) {
            $this->errorResponse('Sin permisos para ver usuarios', 403);
        }

        try {
            // Query base
            $base_query = "
                SELECT 
                    u.id,
                    u.usuario,
                    u.correo,
                    u.nombre,
                    u.apellidos,
                    u.puesto,
                    u.activo,
                    u.sso,
                    u.fecha_creacion,
                    e.nombre as empresa_nombre,
                    r.nombre as rol_nombre,
                    p.nombre as portal_nombre
                FROM usuarios u
                LEFT JOIN empresas e ON u.empresa_id = e.id
                LEFT JOIN roles r ON u.role_id = r.id
                LEFT JOIN portals p ON u.portal_id = p.id
                WHERE u.empresa_id = ?
            ";

            // Aplicar filtros
            $params = [$this->empresa_id];
            
            // Filtro por estado
            $activo = $this->getParam('activo');
            if ($activo !== null) {
                $base_query .= " AND u.activo = ?";
                $params[] = $activo ? 1 : 0;
            }
            
            // Filtro por rol
            $role_id = $this->getParam('role_id');
            if ($role_id) {
                $base_query .= " AND u.role_id = ?";
                $params[] = $role_id;
            }
            
            // Filtro por búsqueda
            $search = $this->getParam('search');
            if ($search) {
                $base_query .= " AND (u.nombre LIKE ? OR u.apellidos LIKE ? OR u.correo LIKE ? OR u.usuario LIKE ?)";
                $search_param = '%' . $search . '%';
                $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
            }

            // Aplicar ordenamiento
            $base_query = $this->applySorting($base_query, [
                'nombre', 'apellidos', 'correo', 'usuario', 'puesto', 'fecha_creacion', 'activo'
            ]);

            // Contar total
            $count_query = str_replace(
                'SELECT u.id, u.usuario, u.correo, u.nombre, u.apellidos, u.puesto, u.activo, u.sso, u.fecha_creacion, e.nombre as empresa_nombre, r.nombre as rol_nombre, p.nombre as portal_nombre',
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
            $usuarios = $stmt->fetchAll();

            // Log de actividad
            $this->logActivity('usuarios_listado', [
                'total_encontrados' => $total_count,
                'filtros' => array_filter([
                    'activo' => $activo,
                    'role_id' => $role_id,
                    'search' => $search
                ])
            ]);

            $this->paginatedResponse($usuarios, $pagination_result['pagination'], $total_count);
            
        } catch (Exception $e) {
            $this->errorResponse('Error al obtener usuarios: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener usuario por ID
     */
    protected function getById(int $id): void
    {
        if (!$this->checkPermission('usuarios_ver')) {
            $this->errorResponse('Sin permisos para ver usuarios', 403);
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    u.id,
                    u.usuario,
                    u.correo,
                    u.nombre,
                    u.apellidos,
                    u.puesto,
                    u.empresa_id,
                    u.role_id,
                    u.portal_id,
                    u.activo,
                    u.sso,
                    u.fecha_creacion,
                    e.nombre as empresa_nombre,
                    r.nombre as rol_nombre,
                    r.permisos as rol_permisos,
                    p.nombre as portal_nombre
                FROM usuarios u
                LEFT JOIN empresas e ON u.empresa_id = e.id
                LEFT JOIN roles r ON u.role_id = r.id
                LEFT JOIN portals p ON u.portal_id = p.id
                WHERE u.id = ? AND u.empresa_id = ?
            ");
            
            $stmt->execute([$id, $this->empresa_id]);
            $usuario = $stmt->fetch();

            if (!$usuario) {
                $this->errorResponse('Usuario no encontrado', 404);
            }

            // Obtener historial de accesos recientes
            $stmt = $this->pdo->prepare("
                SELECT fecha_acceso, ip_address 
                FROM usuario_accesos 
                WHERE usuario_id = ? 
                ORDER BY fecha_acceso DESC 
                LIMIT 5
            ");
            $stmt->execute([$id]);
            $usuario['accesos_recientes'] = $stmt->fetchAll();

            $this->logActivity('usuario_consulta', ['usuario_id' => $id]);
            $this->successResponse($usuario);
            
        } catch (Exception $e) {
            $this->errorResponse('Error al obtener usuario: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Crear nuevo usuario
     */
    protected function create(): void
    {
        if (!$this->checkPermission('usuarios_crear')) {
            $this->errorResponse('Sin permisos para crear usuarios', 403);
        }

        // Validar parámetros requeridos
        $this->validateRequired([
            'usuario', 'correo', 'nombre', 'puesto', 'role_id', 'contrasena'
        ]);

        try {
            // Verificar que el usuario y correo no existan
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM usuarios 
                WHERE (usuario = ? OR correo = ?) AND empresa_id = ?
            ");
            $stmt->execute([
                $this->body_params['usuario'],
                $this->body_params['correo'],
                $this->empresa_id
            ]);
            
            if ($stmt->fetchColumn() > 0) {
                $this->errorResponse('El usuario o correo ya existe', 409);
            }

            // Verificar que el rol existe y pertenece a la empresa
            $stmt = $this->pdo->prepare("
                SELECT id FROM roles 
                WHERE id = ? AND (empresa_id = ? OR empresa_id IS NULL)
            ");
            $stmt->execute([$this->body_params['role_id'], $this->empresa_id]);
            
            if (!$stmt->fetch()) {
                $this->errorResponse('Rol no válido', 400);
            }

            // Hashear contraseña
            $password_hash = password_hash($this->body_params['contrasena'], PASSWORD_DEFAULT);

            // Insertar usuario
            $stmt = $this->pdo->prepare("
                INSERT INTO usuarios (
                    usuario, correo, nombre, apellidos, puesto, contrasena,
                    empresa_id, role_id, portal_id, activo, sso, fecha_creacion
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $this->body_params['usuario'],
                $this->body_params['correo'],
                $this->body_params['nombre'],
                $this->body_params['apellidos'] ?? '',
                $this->body_params['puesto'],
                $password_hash,
                $this->empresa_id,
                $this->body_params['role_id'],
                $this->body_params['portal_id'] ?? null,
                $this->body_params['activo'] ?? 1,
                $this->body_params['sso'] ?? 0
            ]);

            $new_id = $this->pdo->lastInsertId();

            $this->logActivity('usuario_creado', [
                'usuario_id' => $new_id,
                'usuario' => $this->body_params['usuario'],
                'correo' => $this->body_params['correo']
            ]);

            $this->successResponse([
                'id' => $new_id,
                'message' => 'Usuario creado exitosamente'
            ], 201);
            
        } catch (Exception $e) {
            $this->errorResponse('Error al crear usuario: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Actualizar usuario
     */
    protected function update(int $id): void
    {
        if (!$this->checkPermission('usuarios_editar')) {
            $this->errorResponse('Sin permisos para editar usuarios', 403);
        }

        try {
            // Verificar que el usuario existe y pertenece a la empresa
            $stmt = $this->pdo->prepare("
                SELECT id, usuario, correo FROM usuarios 
                WHERE id = ? AND empresa_id = ?
            ");
            $stmt->execute([$id, $this->empresa_id]);
            $current_user = $stmt->fetch();

            if (!$current_user) {
                $this->errorResponse('Usuario no encontrado', 404);
            }

            // Verificar duplicados si se cambia usuario o correo
            if (isset($this->body_params['usuario']) || isset($this->body_params['correo'])) {
                $new_usuario = $this->body_params['usuario'] ?? $current_user['usuario'];
                $new_correo = $this->body_params['correo'] ?? $current_user['correo'];

                $stmt = $this->pdo->prepare("
                    SELECT COUNT(*) FROM usuarios 
                    WHERE (usuario = ? OR correo = ?) AND empresa_id = ? AND id != ?
                ");
                $stmt->execute([$new_usuario, $new_correo, $this->empresa_id, $id]);
                
                if ($stmt->fetchColumn() > 0) {
                    $this->errorResponse('El usuario o correo ya existe', 409);
                }
            }

            // Construir query de actualización dinámicamente
            $fields = [];
            $params = [];
            
            $allowed_fields = [
                'usuario', 'correo', 'nombre', 'apellidos', 'puesto', 
                'role_id', 'portal_id', 'activo', 'sso'
            ];

            foreach ($allowed_fields as $field) {
                if (isset($this->body_params[$field])) {
                    $fields[] = "$field = ?";
                    $params[] = $this->body_params[$field];
                }
            }

            // Manejar cambio de contraseña
            if (isset($this->body_params['contrasena']) && !empty($this->body_params['contrasena'])) {
                $fields[] = "contrasena = ?";
                $params[] = password_hash($this->body_params['contrasena'], PASSWORD_DEFAULT);
            }

            if (empty($fields)) {
                $this->errorResponse('No hay campos para actualizar', 400);
            }

            $params[] = $id;
            $params[] = $this->empresa_id;

            $stmt = $this->pdo->prepare("
                UPDATE usuarios 
                SET " . implode(', ', $fields) . "
                WHERE id = ? AND empresa_id = ?
            ");

            $stmt->execute($params);

            $this->logActivity('usuario_actualizado', [
                'usuario_id' => $id,
                'campos_actualizados' => array_keys($this->body_params)
            ]);

            $this->successResponse(['message' => 'Usuario actualizado exitosamente']);
            
        } catch (Exception $e) {
            $this->errorResponse('Error al actualizar usuario: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar usuario
     */
    protected function delete(int $id): void
    {
        if (!$this->checkPermission('usuarios_eliminar')) {
            $this->errorResponse('Sin permisos para eliminar usuarios', 403);
        }

        try {
            // Verificar que el usuario existe
            $stmt = $this->pdo->prepare("
                SELECT usuario FROM usuarios 
                WHERE id = ? AND empresa_id = ?
            ");
            $stmt->execute([$id, $this->empresa_id]);
            $usuario = $stmt->fetch();

            if (!$usuario) {
                $this->errorResponse('Usuario no encontrado', 404);
            }

            // No permitir eliminar el usuario actual
            if ($id == $this->user_id) {
                $this->errorResponse('No puedes eliminar tu propio usuario', 400);
            }

            // Verificar si tiene calibraciones asociadas
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM calibraciones 
                WHERE usuario_id = ? OR liberado_por = ?
            ");
            $stmt->execute([$id, $id]);
            
            if ($stmt->fetchColumn() > 0) {
                $this->errorResponse('No se puede eliminar el usuario porque tiene calibraciones asociadas', 409);
            }

            // Eliminar usuario
            $stmt = $this->pdo->prepare("
                DELETE FROM usuarios 
                WHERE id = ? AND empresa_id = ?
            ");
            $stmt->execute([$id, $this->empresa_id]);

            $this->logActivity('usuario_eliminado', [
                'usuario_id' => $id,
                'usuario' => $usuario['usuario']
            ]);

            $this->successResponse(['message' => 'Usuario eliminado exitosamente']);
            
        } catch (Exception $e) {
            $this->errorResponse('Error al eliminar usuario: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Endpoints personalizados
     */
    protected function handleCustomGet(array $segments): void
    {
        $endpoint = $segments[0] ?? '';

        switch ($endpoint) {
            case 'profile':
                $this->getProfile();
                break;
            case 'roles':
                $this->getRoles();
                break;
            default:
                $this->errorResponse('Endpoint no encontrado', 404);
        }
    }

    protected function handleCustomPost(array $segments): void
    {
        if (count($segments) >= 2 && is_numeric($segments[0])) {
            $user_id = intval($segments[0]);
            $action = $segments[1];

            switch ($action) {
                case 'toggle-status':
                    $this->toggleUserStatus($user_id);
                    break;
                default:
                    $this->errorResponse('Acción no encontrada', 404);
            }
        } else {
            $this->errorResponse('Endpoint no encontrado', 404);
        }
    }

    /**
     * Obtener perfil del usuario actual
     */
    private function getProfile(): void
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    u.id, u.usuario, u.correo, u.nombre, u.apellidos, u.puesto,
                    u.fecha_creacion, e.nombre as empresa_nombre, r.nombre as rol_nombre
                FROM usuarios u
                LEFT JOIN empresas e ON u.empresa_id = e.id
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.id = ?
            ");
            
            $stmt->execute([$this->user_id]);
            $profile = $stmt->fetch();

            $this->successResponse($profile);
            
        } catch (Exception $e) {
            $this->errorResponse('Error al obtener perfil: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener roles disponibles
     */
    private function getRoles(): void
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, nombre, descripcion 
                FROM roles 
                WHERE empresa_id = ? OR empresa_id IS NULL
                ORDER BY nombre
            ");
            
            $stmt->execute([$this->empresa_id]);
            $roles = $stmt->fetchAll();

            $this->successResponse($roles);
            
        } catch (Exception $e) {
            $this->errorResponse('Error al obtener roles: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Activar/desactivar usuario
     */
    private function toggleUserStatus(int $user_id): void
    {
        if (!$this->checkPermission('usuarios_editar')) {
            $this->errorResponse('Sin permisos para cambiar estado de usuarios', 403);
        }

        try {
            // Obtener estado actual
            $stmt = $this->pdo->prepare("
                SELECT activo, usuario FROM usuarios 
                WHERE id = ? AND empresa_id = ?
            ");
            $stmt->execute([$user_id, $this->empresa_id]);
            $user = $stmt->fetch();

            if (!$user) {
                $this->errorResponse('Usuario no encontrado', 404);
            }

            if ($user_id == $this->user_id) {
                $this->errorResponse('No puedes cambiar tu propio estado', 400);
            }

            $new_status = $user['activo'] ? 0 : 1;

            $stmt = $this->pdo->prepare("
                UPDATE usuarios 
                SET activo = ? 
                WHERE id = ? AND empresa_id = ?
            ");
            $stmt->execute([$new_status, $user_id, $this->empresa_id]);

            $this->logActivity('usuario_cambio_estado', [
                'usuario_id' => $user_id,
                'usuario' => $user['usuario'],
                'estado_anterior' => $user['activo'],
                'estado_nuevo' => $new_status
            ]);

            $this->successResponse([
                'message' => 'Estado del usuario cambiado exitosamente',
                'nuevo_estado' => $new_status ? 'activo' : 'inactivo'
            ]);
            
        } catch (Exception $e) {
            $this->errorResponse('Error al cambiar estado: ' . $e->getMessage(), 500);
        }
    }
}

// Ejecutar API
$api = new UsuariosAPI();
$api->handleRequest();