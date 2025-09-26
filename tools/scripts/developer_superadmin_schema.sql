-- Script SQL para crear las tablas necesarias para el sistema Developer Superadmin
-- Ejecutar este script para crear las estructuras de base de datos requeridas

-- Tabla para logs de auditoría del developer
CREATE TABLE IF NOT EXISTS auditoria_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    accion VARCHAR(100) NOT NULL,
    descripcion TEXT NOT NULL,
    contexto JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_accion (accion),
    INDEX idx_fecha_creacion (fecha_creacion),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla para configuración del sistema
CREATE TABLE IF NOT EXISTS configuracion_sistema (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoria VARCHAR(50) NOT NULL,
    clave VARCHAR(100) NOT NULL,
    valor TEXT NOT NULL,
    descripcion TEXT NULL,
    tipo_dato ENUM('string', 'integer', 'boolean', 'json', 'text') DEFAULT 'string',
    editable TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT NULL,
    UNIQUE KEY uniq_clave (clave),
    INDEX idx_categoria (categoria),
    FOREIGN KEY (updated_by) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla para configuración de alertas automáticas
CREATE TABLE IF NOT EXISTS alertas_configuracion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT NOT NULL,
    condicion TEXT NOT NULL,
    activa TINYINT(1) DEFAULT 1,
    nivel_criticidad ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    notificar_desarrollador TINYINT(1) DEFAULT 1,
    ultima_ejecucion TIMESTAMP NULL,
    proxima_ejecucion TIMESTAMP NULL,
    frecuencia_minutos INT DEFAULT 60,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_nombre (nombre),
    INDEX idx_activa (activa),
    INDEX idx_nivel_criticidad (nivel_criticidad),
    INDEX idx_proxima_ejecucion (proxima_ejecucion),
    FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla para incidentes del sistema
CREATE TABLE IF NOT EXISTS incidentes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT NOT NULL,
    tipo ENUM('bug', 'feature_request', 'security', 'performance', 'maintenance', 'other') DEFAULT 'bug',
    prioridad ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    estado ENUM('abierto', 'en_progreso', 'resuelto', 'cerrado', 'cancelado') DEFAULT 'abierto',
    asignado_a INT NULL,
    reported_by INT NOT NULL,
    solucion TEXT NULL,
    fecha_resolucion TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_estado (estado),
    INDEX idx_prioridad (prioridad),
    INDEX idx_tipo (tipo),
    INDEX idx_asignado_a (asignado_a),
    INDEX idx_reported_by (reported_by),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (asignado_a) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (reported_by) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla para documentación técnica y SOPs
CREATE TABLE IF NOT EXISTS documentos_sistema (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    tipo ENUM('sop', 'appcare', 'handover', 'manual', 'specification', 'other') NOT NULL,
    contenido LONGTEXT NOT NULL,
    version VARCHAR(20) NOT NULL DEFAULT '1.0',
    estado ENUM('draft', 'review', 'approved', 'archived') DEFAULT 'draft',
    autor_id INT NOT NULL,
    revisor_id INT NULL,
    aprobador_id INT NULL,
    fecha_aprobacion TIMESTAMP NULL,
    tags JSON NULL,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tipo (tipo),
    INDEX idx_estado (estado),
    INDEX idx_version (version),
    INDEX idx_autor_id (autor_id),
    INDEX idx_fecha_aprobacion (fecha_aprobacion),
    FOREIGN KEY (autor_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (revisor_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (aprobador_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla para sesiones activas (monitoreo de usuarios)
CREATE TABLE IF NOT EXISTS sesiones_activas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    session_id VARCHAR(128) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    portal_slug VARCHAR(20) NULL,
    ultima_actividad TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_session_id (session_id),
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_ultima_actividad (ultima_actividad),
    INDEX idx_ip_address (ip_address),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla para backups del sistema
CREATE TABLE IF NOT EXISTS backups_sistema (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_archivo VARCHAR(255) NOT NULL,
    tipo ENUM('full', 'incremental', 'differential') DEFAULT 'full',
    tamano_bytes BIGINT NULL,
    checksum VARCHAR(64) NULL,
    ubicacion TEXT NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    restaurado_en TIMESTAMP NULL,
    restaurado_by INT NULL,
    INDEX idx_tipo (tipo),
    INDEX idx_created_at (created_at),
    INDEX idx_created_by (created_by),
    FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (restaurado_by) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertar configuraciones por defecto del sistema
INSERT INTO configuracion_sistema (categoria, clave, valor, descripcion, tipo_dato) VALUES
('sistema', 'maintenance_mode', 'false', 'Modo de mantenimiento del sistema', 'boolean'),
('sistema', 'max_concurrent_users', '100', 'Máximo número de usuarios concurrentes', 'integer'),
('sistema', 'session_timeout', '3600', 'Tiempo de expiración de sesión en segundos', 'integer'),
('sistema', 'backup_retention_days', '30', 'Días de retención para backups automáticos', 'integer'),
('seguridad', 'password_min_length', '8', 'Longitud mínima de contraseñas', 'integer'),
('seguridad', 'max_login_attempts', '5', 'Máximo número de intentos de login', 'integer'),
('seguridad', 'lockout_duration', '900', 'Duración de bloqueo en segundos', 'integer'),
('alertas', 'email_notifications', 'true', 'Activar notificaciones por email', 'boolean'),
('alertas', 'critical_alert_email', 'developer@ejemplo.com', 'Email para alertas críticas', 'string'),
('monitoreo', 'log_retention_days', '90', 'Días de retención para logs de auditoría', 'integer'),
('monitoreo', 'performance_monitoring', 'true', 'Activar monitoreo de rendimiento', 'boolean'),
('database', 'auto_backup_enabled', 'true', 'Activar backups automáticos de base de datos', 'boolean'),
('database', 'auto_backup_frequency', '24', 'Frecuencia de backup automático en horas', 'integer')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

-- Insertar alertas por defecto
INSERT INTO alertas_configuracion (nombre, descripcion, condicion, nivel_criticidad, created_by) VALUES
('Disco lleno', 'Alertar cuando el espacio en disco esté por agotarse', 'disk_usage > 90', 'high', 1),
('Alta carga del servidor', 'Alertar cuando la carga del servidor sea muy alta', 'server_load > 5', 'medium', 1),
('Muchas consultas lentas', 'Alertar cuando hay muchas consultas lentas en la base de datos', 'slow_queries > 100', 'medium', 1),
('Fallos de autenticación', 'Alertar sobre múltiples fallos de autenticación', 'failed_logins > 10', 'high', 1),
('Sesiones concurrentes altas', 'Alertar cuando hay demasiadas sesiones concurrentes', 'concurrent_sessions > 80', 'medium', 1)
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

-- Insertar permisos específicos para el developer superadmin
INSERT IGNORE INTO permissions (nombre, descripcion) VALUES
('developer_dashboard_access', 'Acceso al dashboard de developer con privilegios de superadministrador'),
('developer_system_config', 'Gestionar configuración avanzada del sistema'),
('developer_user_management', 'Gestión completa de usuarios y permisos'),
('developer_audit_access', 'Acceso completo a logs de auditoría y trazabilidad'),
('developer_incident_management', 'Gestión completa de incidentes del sistema'),
('developer_backup_management', 'Gestión de backups y restauración del sistema'),
('developer_monitoring_access', 'Acceso completo a monitoreo y métricas del sistema'),
('developer_alert_management', 'Configuración y gestión de alertas automáticas'),
('developer_document_management', 'Gestión completa de documentación técnica (SOPs, Handover, AppCare)'),
('developer_database_access', 'Acceso directo a la base de datos para mantenimiento'),
('developer_security_events', 'Acceso a eventos de seguridad y análisis'),
('developer_performance_tuning', 'Acceso a herramientas de optimización y rendimiento');

-- Asignar todos los permisos al rol Developer
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.nombre = 'Developer';

-- Crear índices adicionales para optimizar consultas
CREATE INDEX IF NOT EXISTS idx_auditoria_logs_usuario_fecha ON auditoria_logs(usuario_id, fecha_creacion);
CREATE INDEX IF NOT EXISTS idx_incidentes_estado_prioridad ON incidentes(estado, prioridad);
CREATE INDEX IF NOT EXISTS idx_documentos_tipo_estado ON documentos_sistema(tipo, estado);
CREATE INDEX IF NOT EXISTS idx_alertas_activa_nivel ON alertas_configuracion(activa, nivel_criticidad);

-- Crear vista para estadísticas del dashboard
CREATE OR REPLACE VIEW developer_dashboard_stats AS
SELECT 
    (SELECT COUNT(*) FROM usuarios) as total_usuarios,
    (SELECT COUNT(*) FROM empresas) as total_empresas,
    (SELECT COUNT(*) FROM instrumentos) as total_instrumentos,
    (SELECT COUNT(*) FROM calibraciones WHERE estado = 'pendiente') as calibraciones_pendientes,
    (SELECT COUNT(*) FROM incidentes WHERE estado IN ('abierto', 'en_progreso')) as incidentes_abiertos,
    (SELECT COUNT(*) FROM alertas_configuracion WHERE activa = 1) as alertas_activas,
    (SELECT COUNT(*) FROM auditoria_logs WHERE DATE(fecha_creacion) = CURDATE()) as logs_hoy,
    (SELECT COUNT(*) FROM sesiones_activas WHERE ultima_actividad > DATE_SUB(NOW(), INTERVAL 1 HOUR)) as sesiones_activas_hora;

-- Trigger para limpiar logs antiguos automáticamente
DELIMITER //
CREATE TRIGGER IF NOT EXISTS cleanup_old_audit_logs
AFTER INSERT ON auditoria_logs
FOR EACH ROW
BEGIN
    DECLARE log_retention_days INT DEFAULT 90;
    
    SELECT valor INTO log_retention_days 
    FROM configuracion_sistema 
    WHERE clave = 'log_retention_days' 
    LIMIT 1;
    
    DELETE FROM auditoria_logs 
    WHERE fecha_creacion < DATE_SUB(NOW(), INTERVAL log_retention_days DAY);
END//
DELIMITER ;

-- Trigger para actualizar sesiones activas
DELIMITER //
CREATE TRIGGER IF NOT EXISTS update_active_sessions
AFTER UPDATE ON usuarios
FOR EACH ROW
BEGIN
    IF NEW.ultima_actividad != OLD.ultima_actividad THEN
        UPDATE sesiones_activas 
        SET ultima_actividad = NEW.ultima_actividad 
        WHERE usuario_id = NEW.id;
    END IF;
END//
DELIMITER ;