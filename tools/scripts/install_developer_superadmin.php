<?php
/**
 * Script de instalación para el sistema Developer con privilegios de Superadministrador
 * 
 * Este script configura automáticamente:
 * - Tablas de base de datos necesarias
 * - Permisos del rol Developer
 * - Usuario developer por defecto
 * - Configuraciones del sistema
 * - Alertas automáticas
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/db.php';
require_once dirname(__DIR__, 2) . '/app/Core/developer_permissions.php';

class DeveloperSuperadminInstaller
{
    private mysqli $conn;
    private array $results = [];
    
    public function __construct()
    {
        global $conn;
        $this->conn = $conn ?? DatabaseManager::getConnection();
    }
    
    public function install(): array
    {
        echo "=== INSTALACIÓN DEL SISTEMA DEVELOPER SUPERADMIN ===\n\n";
        
        try {
            $this->createDatabaseStructure();
            $this->configureDeveloperRole();
            $this->createDeveloperUser();
            $this->setupSystemConfiguration();
            $this->setupAutomaticAlerts();
            $this->setupDefaultDocuments();
            $this->runSystemValidation();
            
            $this->results['success'] = true;
            $this->results['message'] = 'Sistema Developer Superadmin instalado correctamente';
            
        } catch (Exception $e) {
            $this->results['success'] = false;
            $this->results['error'] = $e->getMessage();
            echo "ERROR: " . $e->getMessage() . "\n";
        }
        
        return $this->results;
    }
    
    private function createDatabaseStructure(): void
    {
        echo "1. Creando estructura de base de datos...\n";
        
        // Leer y ejecutar el script SQL
        $sqlFile = dirname(__DIR__) . '/tools/scripts/developer_superadmin_schema.sql';
        
        if (!file_exists($sqlFile)) {
            throw new Exception("Archivo SQL no encontrado: $sqlFile");
        }
        
        $sql = file_get_contents($sqlFile);
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (empty($statement) || strpos($statement, '--') === 0) {
                continue;
            }
            
            if (!$this->conn->query($statement)) {
                throw new Exception("Error ejecutando SQL: " . $this->conn->error);
            }
        }
        
        echo "✓ Estructura de base de datos creada\n";
        $this->results['database_structure'] = true;
    }
    
    private function configureDeveloperRole(): void
    {
        echo "2. Configurando rol Developer con privilegios de Superadministrador...\n";
        
        // Verificar que el rol Developer existe
        $stmt = $this->conn->prepare("SELECT id FROM roles WHERE nombre = 'Developer' LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
        $role = $result->fetch_assoc();
        $stmt->close();
        
        if (!$role) {
            // Crear el rol Developer si no existe
            $stmt = $this->conn->prepare("INSERT INTO roles (nombre, empresa_id, delegated, portal_id) VALUES ('Developer', NULL, 0, (SELECT id FROM portals WHERE slug = 'internal' LIMIT 1))");
            if (!$stmt->execute()) {
                throw new Exception("Error creando rol Developer: " . $this->conn->error);
            }
            $stmt->close();
            
            // Obtener el ID del rol recién creado
            $stmt = $this->conn->prepare("SELECT id FROM roles WHERE nombre = 'Developer' LIMIT 1");
            $stmt->execute();
            $result = $stmt->get_result();
            $role = $result->fetch_assoc();
            $stmt->close();
        }
        
        $developerRoleId = (int)$role['id'];
        
        // Asignar TODOS los permisos al rol Developer
        $stmt = $this->conn->prepare("
            INSERT IGNORE INTO role_permissions (role_id, permission_id)
            SELECT ?, p.id FROM permissions p
        ");
        $stmt->bind_param('i', $developerRoleId);
        
        if (!$stmt->execute()) {
            throw new Exception("Error asignando permisos al rol Developer: " . $this->conn->error);
        }
        $stmt->close();
        
        // Contar permisos asignados
        $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM role_permissions WHERE role_id = ?");
        $stmt->bind_param('i', $developerRoleId);
        $stmt->execute();
        $result = $stmt->get_result();
        $permissionsCount = $result->fetch_assoc()['total'];
        $stmt->close();
        
        echo "✓ Rol Developer configurado con $permissionsCount permisos\n";
        $this->results['developer_role'] = true;
        $this->results['permissions_count'] = $permissionsCount;
    }
    
    private function createDeveloperUser(): void
    {
        echo "3. Creando usuario developer por defecto...\n";
        
        // Verificar si ya existe un usuario developer
        $stmt = $this->conn->prepare("SELECT id FROM usuarios WHERE usuario = 'developer' LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
        $existingUser = $result->fetch_assoc();
        $stmt->close();
        
        if ($existingUser) {
            echo "✓ Usuario developer ya existe (ID: {$existingUser['id']})\n";
            $this->results['developer_user'] = 'exists';
            return;
        }
        
        // Obtener IDs necesarios
        $roleStmt = $this->conn->prepare("SELECT id FROM roles WHERE nombre = 'Developer' LIMIT 1");
        $roleStmt->execute();
        $roleResult = $roleStmt->get_result();
        $roleId = $roleResult->fetch_assoc()['id'];
        $roleStmt->close();
        
        $portalStmt = $this->conn->prepare("SELECT id FROM portals WHERE slug = 'internal' LIMIT 1");
        $portalStmt->execute();
        $portalResult = $portalStmt->get_result();
        $portalId = $portalResult->fetch_assoc()['id'];
        $portalStmt->close();
        
        // Crear usuario developer
        $hashedPassword = password_hash('Developer!2024', PASSWORD_BCRYPT);
        
        $stmt = $this->conn->prepare("
            INSERT INTO usuarios (usuario, nombre, apellidos, correo, telefono, contrasena, role_id, empresa_id, portal_id, fecha_creacion)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $usuario = 'developer';
        $nombre = 'Developer';
        $apellidos = 'Superadmin';
        $correo = 'developer@ejemplo.com';
        $telefono = '+1-555-DEV-ADMIN';
        $empresaId = 1;
        
        $stmt->bind_param('ssssssiiii',
            $usuario,
            $nombre,
            $apellidos,
            $correo,
            $telefono,
            $hashedPassword,
            $roleId,
            $empresaId,
            $portalId
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Error creando usuario developer: " . $this->conn->error);
        }
        
        $userId = $this->conn->insert_id;
        $stmt->close();
        
        echo "✓ Usuario developer creado (ID: $userId)\n";
        echo "  - Usuario: developer\n";
        echo "  - Contraseña: Developer!2024\n";
        echo "  - Email: developer@ejemplo.com\n";
        
        $this->results['developer_user'] = 'created';
        $this->results['developer_user_id'] = $userId;
    }
    
    private function setupSystemConfiguration(): void
    {
        echo "4. Configurando parámetros del sistema...\n";
        
        $configurations = [
            ['desarrollo', 'debug_mode', 'true', 'Modo de depuración para desarrolladores', 'boolean'],
            ['desarrollo', 'developer_access_enabled', 'true', 'Acceso habilitado para desarrolladores', 'boolean'],
            ['desarrollo', 'developer_dashboard_url', '/apps/internal/developer/dashboard.html', 'URL del dashboard de developer', 'string'],
            ['seguridad', 'developer_session_timeout', '7200', 'Tiempo de sesión extendido para developers (segundos)', 'integer'],
            ['sistema', 'maintenance_notifications', 'developer@ejemplo.com', 'Email para notificaciones de mantenimiento', 'string'],
            ['auditoria', 'log_developer_actions', 'true', 'Registrar todas las acciones de developers', 'boolean'],
            ['alertas', 'critical_system_alerts', 'true', 'Alertas críticas del sistema habilitadas', 'boolean'],
            ['backup', 'auto_backup_before_changes', 'true', 'Backup automático antes de cambios críticos', 'boolean']
        ];
        
        foreach ($configurations as [$categoria, $clave, $valor, $descripcion, $tipo]) {
            $stmt = $this->conn->prepare("
                INSERT INTO configuracion_sistema (categoria, clave, valor, descripcion, tipo_dato, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                valor = VALUES(valor),
                descripcion = VALUES(descripcion),
                updated_at = NOW()
            ");
            
            $stmt->bind_param('sssss', $categoria, $clave, $valor, $descripcion, $tipo);
            $stmt->execute();
            $stmt->close();
        }
        
        echo "✓ Configuraciones del sistema establecidas\n";
        $this->results['system_configuration'] = true;
    }
    
    private function setupAutomaticAlerts(): void
    {
        echo "5. Configurando alertas automáticas...\n";
        
        $alerts = [
            [
                'Sistema sobrecargado',
                'Alertar cuando el sistema tenga alta carga de procesamiento',
                'system_load > 4 OR memory_usage > 90',
                'high',
                60
            ],
            [
                'Base de datos lenta',
                'Alertar cuando las consultas de base de datos sean muy lentas',
                'avg_query_time > 2 OR slow_queries > 50',
                'medium',
                30
            ],
            [
                'Intentos de acceso no autorizado',
                'Alertar sobre múltiples intentos de acceso fallidos',
                'failed_login_attempts > 5 IN last_hour',
                'critical',
                15
            ],
            [
                'Errores del sistema',
                'Alertar sobre errores críticos en logs del sistema',
                'error_count > 10 IN last_hour',
                'high',
                15
            ],
            [
                'Backup fallido',
                'Alertar cuando los backups automáticos fallen',
                'backup_failed = true',
                'critical',
                5
            ]
        ];
        
        foreach ($alerts as [$nombre, $descripcion, $condicion, $nivel, $frecuencia]) {
            $stmt = $this->conn->prepare("
                INSERT INTO alertas_configuracion 
                (nombre, descripcion, condicion, nivel_criticidad, frecuencia_minutos, notificar_desarrollador, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, 1, 1, NOW())
                ON DUPLICATE KEY UPDATE
                descripcion = VALUES(descripcion),
                condicion = VALUES(condicion),
                nivel_criticidad = VALUES(nivel_criticidad),
                frecuencia_minutos = VALUES(frecuencia_minutos)
            ");
            
            $stmt->bind_param('ssssi', $nombre, $descripcion, $condicion, $nivel, $frecuencia);
            $stmt->execute();
            $stmt->close();
        }
        
        echo "✓ Alertas automáticas configuradas\n";
        $this->results['automatic_alerts'] = true;
    }
    
    private function setupDefaultDocuments(): void
    {
        echo "6. Creando documentación por defecto...\n";
        
        $documents = [
            [
                'SOP - Acceso Developer Superadmin',
                'sop',
                $this->getDeveloperSOPContent(),
                '1.0',
                'approved'
            ],
            [
                'Handover - Sistema Developer',
                'handover',
                $this->getDeveloperHandoverContent(),
                '1.0',
                'approved'
            ],
            [
                'AppCare - Mantenimiento Sistema',
                'appcare',
                $this->getDeveloperAppCareContent(),
                '1.0',
                'approved'
            ]
        ];
        
        foreach ($documents as [$titulo, $tipo, $contenido, $version, $estado]) {
            $stmt = $this->conn->prepare("
                INSERT INTO documentos_sistema 
                (titulo, tipo, contenido, version, estado, autor_id, aprobador_id, fecha_aprobacion, created_at)
                VALUES (?, ?, ?, ?, ?, 1, 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                contenido = VALUES(contenido),
                updated_at = NOW()
            ");
            
            $stmt->bind_param('sssss', $titulo, $tipo, $contenido, $version, $estado);
            $stmt->execute();
            $stmt->close();
        }
        
        echo "✓ Documentación por defecto creada\n";
        $this->results['default_documents'] = true;
    }
    
    private function runSystemValidation(): void
    {
        echo "7. Validando instalación...\n";
        
        $validations = [
            'Tablas creadas' => "SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('auditoria_logs', 'configuracion_sistema', 'alertas_configuracion', 'incidentes', 'documentos_sistema')",
            'Usuario developer existe' => "SELECT COUNT(*) as count FROM usuarios WHERE usuario = 'developer'",
            'Permisos asignados' => "SELECT COUNT(*) as count FROM role_permissions rp JOIN roles r ON r.id = rp.role_id WHERE r.nombre = 'Developer'",
            'Configuraciones establecidas' => "SELECT COUNT(*) as count FROM configuracion_sistema WHERE categoria = 'desarrollo'",
            'Alertas configuradas' => "SELECT COUNT(*) as count FROM alertas_configuracion WHERE activa = 1"
        ];
        
        foreach ($validations as $check => $query) {
            $result = $this->conn->query($query);
            $count = $result->fetch_assoc()['count'];
            
            if ($count > 0) {
                echo "✓ $check ($count)\n";
            } else {
                echo "✗ $check (0)\n";
                throw new Exception("Falló la validación: $check");
            }
        }
        
        echo "✓ Sistema validado correctamente\n";
        $this->results['validation'] = true;
    }
    
    private function getDeveloperSOPContent(): string
    {
        return <<<'SOP'
# SOP - Procedimiento de Acceso Developer Superadministrador

## 1. PROPÓSITO
Este procedimiento establece las directrices para el acceso y uso del sistema Developer con privilegios de Superadministrador en el sistema computarizado ISO 17025.

## 2. ALCANCE
Aplica a todos los usuarios con rol "Developer" que requieren acceso total al sistema para tareas de desarrollo, mantenimiento y administración.

## 3. RESPONSABILIDADES
- **Developer**: Uso responsable de los privilegios de acceso total
- **Administrador del Sistema**: Supervisión de actividades de developers
- **Auditoría**: Revisión de logs de acciones críticas

## 4. PROCEDIMIENTO

### 4.1 Acceso al Sistema
1. Ingresar con credenciales de developer
2. Verificar que el sistema muestre privilegios de Superadministrador
3. Acceder al dashboard de developer: `/apps/internal/developer/dashboard.html`

### 4.2 Funciones Disponibles
- **Gestión de Usuarios**: Crear, modificar y eliminar usuarios
- **Configuración del Sistema**: Modificar parámetros avanzados
- **Monitoreo**: Supervisión en tiempo real
- **Auditoría**: Acceso completo a logs
- **Incidentes**: Gestión completa de incidentes
- **Backup**: Crear y restaurar backups
- **Documentación**: Gestionar SOPs, Handover, AppCare

### 4.3 Controles de Seguridad
- Todas las acciones quedan registradas en auditoría
- Alertas automáticas para acciones críticas
- Backup automático antes de cambios importantes

## 5. REGISTROS
Todos los accesos y acciones quedan registrados en:
- Tabla: auditoria_logs
- Dashboard: Sección "Logs de Auditoría"

## 6. REFERENCIAS
- ISO 17025:2017
- Procedimientos de Seguridad del Sistema
- Manual de Usuario Developer
SOP;
    }
    
    private function getDeveloperHandoverContent(): string
    {
        return <<<'HANDOVER'
# Handover - Sistema Developer Superadministrador

## INFORMACIÓN GENERAL
- **Sistema**: Developer Dashboard con privilegios de Superadministrador
- **Versión**: 1.0
- **Fecha de Implementación**: {date}

## CREDENCIALES DE ACCESO
- **Usuario**: developer
- **Contraseña**: Developer!2024
- **URL**: /apps/internal/developer/dashboard.html

## FUNCIONALIDADES PRINCIPALES

### Dashboard Principal
- Estadísticas del sistema en tiempo real
- Monitoreo de salud del sistema
- Alertas críticas activas
- Actividad reciente

### Gestión de Usuarios
- CRUD completo de usuarios
- Asignación de roles y permisos
- Gestión de empresas asociadas

### Configuración del Sistema
- Modificación de parámetros avanzados
- Configuración de alertas automáticas
- Gestión de portales y dominios

### Monitoreo y Auditoría
- Logs de actividad en tiempo real
- Métricas de rendimiento
- Análisis de sesiones activas

## ARCHIVOS IMPORTANTES
- `/app/Core/developer_permissions.php` - Lógica de permisos
- `/app/Modules/Internal/Developer/SuperadminDashboard.php` - API del dashboard
- `/public/apps/internal/developer/dashboard.html` - Frontend
- `/tools/scripts/developer_superadmin_schema.sql` - Esquema de BD

## TABLAS DE BASE DE DATOS
- `auditoria_logs` - Logs de auditoría
- `configuracion_sistema` - Configuraciones
- `alertas_configuracion` - Alertas automáticas
- `incidentes` - Gestión de incidentes
- `documentos_sistema` - Documentación

## PROCEDIMIENTOS DE EMERGENCIA
1. Acceder con usuario developer
2. Revisar alertas críticas
3. Verificar estado del sistema
4. Ejecutar backup de emergencia si es necesario
5. Documentar acciones realizadas

## CONTACTOS
- Soporte Técnico: developer@ejemplo.com
- Administrador del Sistema: admin@ejemplo.com
HANDOVER;
    }
    
    private function getDeveloperAppCareContent(): string
    {
        return <<<'APPCARE'
# AppCare - Mantenimiento Sistema Developer

## MANTENIMIENTO DIARIO
- [ ] Verificar estado general del sistema
- [ ] Revisar alertas críticas pendientes
- [ ] Comprobar espacios de disco disponibles
- [ ] Verificar logs de errores recientes

## MANTENIMIENTO SEMANAL
- [ ] Ejecutar backup completo del sistema
- [ ] Limpiar logs antiguos (>90 días)
- [ ] Verificar rendimiento de la base de datos
- [ ] Revisar sesiones activas y cerrar inactivas
- [ ] Actualizar documentación si es necesario

## MANTENIMIENTO MENSUAL
- [ ] Análisis completo de logs de auditoría
- [ ] Revisión de configuraciones del sistema
- [ ] Verificación de integridad de backups
- [ ] Actualización de alertas automáticas
- [ ] Reporte de actividades del mes

## PROCEDIMIENTOS DE EMERGENCIA

### Sistema No Responde
1. Verificar conexión a base de datos
2. Revisar logs de PHP y Apache
3. Reiniciar servicios si es necesario
4. Notificar a administradores

### Alertas Críticas
1. Identificar la causa raíz
2. Tomar acción correctiva inmediata
3. Documentar la resolución
4. Actualizar procedimientos si es necesario

### Backup y Recuperación
1. Verificar integridad del backup más reciente
2. En caso de pérdida de datos, restaurar desde backup
3. Verificar integridad post-restauración
4. Documentar el incidente

## COMANDOS ÚTILES

### Backup Manual
```bash
mysqldump --host=localhost --user=root sbl_sistema_interno > backup_$(date +%Y%m%d_%H%M%S).sql
```

### Limpiar Logs Antiguos
```sql
DELETE FROM auditoria_logs WHERE fecha_creacion < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

### Verificar Espacio en Disco
```bash
df -h
```

## CONTACTOS DE EMERGENCIA
- Developer Principal: developer@ejemplo.com
- Soporte de BD: dba@ejemplo.com
- Infraestructura: infra@ejemplo.com
APPCARE;
    }
}

// Ejecutar instalación si se llama directamente
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $installer = new DeveloperSuperadminInstaller();
    $result = $installer->install();
    
    echo "\n=== RESUMEN DE INSTALACIÓN ===\n";
    if ($result['success']) {
        echo "✓ INSTALACIÓN COMPLETADA EXITOSAMENTE\n\n";
        echo "Credenciales de acceso:\n";
        echo "- Usuario: developer\n";
        echo "- Contraseña: Developer!2024\n";
        echo "- URL: /apps/internal/developer/dashboard.html\n\n";
        echo "El usuario 'developer' tiene privilegios de Superadministrador completos.\n";
    } else {
        echo "✗ ERROR EN LA INSTALACIÓN\n";
        echo "Error: " . $result['error'] . "\n";
    }
}
?>