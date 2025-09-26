-- ==================================================================
-- ESQUEMA DE BASE DE DATOS PARA SISTEMA DE GESTIÓN DE CAMBIOS
-- Sistema ISO 17025 - Gestión completa de auditoría, backup, 
-- incidentes y control de versiones
-- ==================================================================

-- Tabla para logs de auditoría de usuarios
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action_type ENUM('CREATE', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT', 'VIEW', 'EXPORT', 'IMPORT') NOT NULL,
    table_name VARCHAR(100) NOT NULL,
    record_id INT NULL,
    old_data JSON NULL,
    new_data JSON NULL,
    description TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    session_id VARCHAR(255) NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user_id (user_id),
    INDEX idx_action_type (action_type),
    INDEX idx_table_name (table_name),
    INDEX idx_timestamp (timestamp),
    
    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para logs de backups
CREATE TABLE IF NOT EXISTS backup_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('hot', 'cold', 'restore') NOT NULL,
    scope ENUM('full', 'database', 'files', 'incremental') NOT NULL,
    status ENUM('running', 'completed', 'failed', 'cancelled') NOT NULL,
    file_path VARCHAR(500) NULL,
    file_size BIGINT NULL,
    error_message TEXT NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_by INT NULL,
    
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_started_at (started_at),
    
    FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para gestión de incidentes
CREATE TABLE IF NOT EXISTS incidents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    priority ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    category VARCHAR(100) NOT NULL,
    status ENUM('open', 'in_progress', 'resolved', 'closed', 'cancelled') DEFAULT 'open',
    reported_by INT NOT NULL,
    assigned_to INT NULL,
    external_ticket_key VARCHAR(100) NULL,
    external_ticket_url VARCHAR(500) NULL,
    resolution TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    
    INDEX idx_severity (severity),
    INDEX idx_status (status),
    INDEX idx_assigned_to (assigned_to),
    INDEX idx_created_at (created_at),
    
    FOREIGN KEY (reported_by) REFERENCES usuarios(id) ON DELETE RESTRICT,
    FOREIGN KEY (assigned_to) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para notas de incidentes
CREATE TABLE IF NOT EXISTS incident_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    incident_id INT NOT NULL,
    user_id INT NOT NULL,
    note TEXT NOT NULL,
    is_internal BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_incident_id (incident_id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    
    FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para alertas del sistema
CREATE TABLE IF NOT EXISTS system_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alert_id VARCHAR(100) UNIQUE NOT NULL,
    type ENUM('system_metric', 'business_kpi', 'security', 'maintenance') NOT NULL,
    level ENUM('info', 'warning', 'critical') NOT NULL,
    metric VARCHAR(100) NULL,
    value DECIMAL(10,2) NULL,
    threshold DECIMAL(10,2) NULL,
    message TEXT NOT NULL,
    acknowledged BOOLEAN DEFAULT FALSE,
    acknowledged_by INT NULL,
    acknowledged_at TIMESTAMP NULL,
    dismissed BOOLEAN DEFAULT FALSE,
    dismissed_by INT NULL,
    dismissed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_type (type),
    INDEX idx_level (level),
    INDEX idx_acknowledged (acknowledged),
    INDEX idx_created_at (created_at),
    
    FOREIGN KEY (acknowledged_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (dismissed_by) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para tareas de gestión de proyectos
CREATE TABLE IF NOT EXISTS project_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    platform ENUM('trello', 'asana', 'monday', 'jira') NOT NULL,
    external_id VARCHAR(100) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    url VARCHAR(500) NULL,
    status ENUM('open', 'in_progress', 'completed', 'cancelled') DEFAULT 'open',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_platform_external (platform, external_id),
    INDEX idx_platform (platform),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    
    FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para vincular incidentes con tareas
CREATE TABLE IF NOT EXISTS incident_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    incident_id INT NOT NULL,
    platform ENUM('trello', 'asana', 'monday', 'jira') NOT NULL,
    external_id VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_incident_platform (incident_id, platform, external_id),
    INDEX idx_incident_id (incident_id),
    
    FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para cambios de configuración
CREATE TABLE IF NOT EXISTS configuration_changes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_name VARCHAR(100) NOT NULL,
    changed_by INT NULL,
    old_data JSON NULL,
    new_data JSON NOT NULL,
    change_description TEXT NULL,
    commit_hash VARCHAR(40) NOT NULL,
    backup_path VARCHAR(500) NULL,
    is_revert BOOLEAN DEFAULT FALSE,
    reverted_from VARCHAR(40) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_config_name (config_name),
    INDEX idx_changed_by (changed_by),
    INDEX idx_commit_hash (commit_hash),
    INDEX idx_created_at (created_at),
    
    FOREIGN KEY (changed_by) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para branches experimentales
CREATE TABLE IF NOT EXISTS experimental_branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT NULL,
    created_by INT NULL,
    status ENUM('active', 'merged', 'abandoned') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    merged_at TIMESTAMP NULL,
    merge_commit VARCHAR(40) NULL,
    
    INDEX idx_branch_name (branch_name),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    
    FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para métricas del sistema
CREATE TABLE IF NOT EXISTS system_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metric_name VARCHAR(100) NOT NULL,
    metric_value DECIMAL(12,4) NOT NULL,
    metric_unit VARCHAR(20) NULL,
    tags JSON NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_metric_name (metric_name),
    INDEX idx_recorded_at (recorded_at),
    INDEX idx_metric_name_time (metric_name, recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para configuración de umbrales de alertas
CREATE TABLE IF NOT EXISTS alert_thresholds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metric_name VARCHAR(100) UNIQUE NOT NULL,
    warning_threshold DECIMAL(10,2) NOT NULL,
    critical_threshold DECIMAL(10,2) NOT NULL,
    enabled BOOLEAN DEFAULT TRUE,
    notification_emails TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_metric_name (metric_name),
    INDEX idx_enabled (enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para historial de sesiones de usuario
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    logout_type ENUM('manual', 'timeout', 'forced') NULL,
    
    INDEX idx_user_id (user_id),
    INDEX idx_session_id (session_id),
    INDEX idx_started_at (started_at),
    
    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para configuración de integraciones
CREATE TABLE IF NOT EXISTS integration_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    integration_name VARCHAR(100) UNIQUE NOT NULL,
    enabled BOOLEAN DEFAULT FALSE,
    config_data JSON NOT NULL,
    api_key_encrypted TEXT NULL,
    last_sync TIMESTAMP NULL,
    sync_status ENUM('success', 'error', 'pending') NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_integration_name (integration_name),
    INDEX idx_enabled (enabled),
    INDEX idx_last_sync (last_sync)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para seguimiento de cambios en el sistema
CREATE TABLE IF NOT EXISTS change_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    category ENUM('configuration', 'feature', 'bugfix', 'security', 'maintenance') NOT NULL,
    priority ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    status ENUM('draft', 'submitted', 'approved', 'rejected', 'implemented', 'cancelled') DEFAULT 'draft',
    requested_by INT NOT NULL,
    approved_by INT NULL,
    implemented_by INT NULL,
    impact_assessment TEXT NULL,
    rollback_plan TEXT NULL,
    testing_notes TEXT NULL,
    implementation_date TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_status (status),
    INDEX idx_category (category),
    INDEX idx_priority (priority),
    INDEX idx_requested_by (requested_by),
    INDEX idx_created_at (created_at),
    
    FOREIGN KEY (requested_by) REFERENCES usuarios(id) ON DELETE RESTRICT,
    FOREIGN KEY (approved_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (implemented_by) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================================
-- DATOS INICIALES Y CONFIGURACIÓN
-- ==================================================================

-- Insertar umbrales por defecto para métricas críticas
INSERT IGNORE INTO alert_thresholds (metric_name, warning_threshold, critical_threshold, notification_emails) VALUES
('database_response_time', 1000.00, 3000.00, 'admin@sistema.com'),
('disk_usage', 80.00, 90.00, 'admin@sistema.com,sysadmin@sistema.com'),
('memory_usage', 80.00, 90.00, 'admin@sistema.com'),
('cpu_usage', 80.00, 90.00, 'admin@sistema.com'),
('active_incidents', 5.00, 10.00, 'admin@sistema.com,manager@sistema.com'),
('failed_backups', 1.00, 3.00, 'admin@sistema.com,backup@sistema.com');

-- Insertar configuraciones de integración por defecto
INSERT IGNORE INTO integration_configs (integration_name, enabled, config_data) VALUES
('jira', FALSE, '{"url": "", "project_key": "INCIDENT", "username": "", "custom_fields": {}}'),
('trello', FALSE, '{"api_key": "", "token": "", "board_id": "", "default_list": ""}'),
('asana', FALSE, '{"token": "", "workspace_id": "", "project_id": ""}'),
('monday', FALSE, '{"token": "", "board_id": "", "group_id": ""}'),
('email_notifications', TRUE, '{"smtp_host": "localhost", "smtp_port": 587, "encryption": "tls", "from_address": "sistema@empresa.com"}');

-- ==================================================================
-- VISTAS ÚTILES PARA REPORTES
-- ==================================================================

-- Vista para resumen de auditoría por usuario
CREATE OR REPLACE VIEW audit_summary AS
SELECT 
    u.id as user_id,
    u.nombre as user_name,
    u.usuario as username,
    COUNT(al.id) as total_actions,
    COUNT(CASE WHEN al.action_type = 'CREATE' THEN 1 END) as creates,
    COUNT(CASE WHEN al.action_type = 'UPDATE' THEN 1 END) as updates,
    COUNT(CASE WHEN al.action_type = 'DELETE' THEN 1 END) as deletes,
    MAX(al.timestamp) as last_activity
FROM usuarios u
LEFT JOIN audit_logs al ON u.id = al.user_id
WHERE u.estado = 'activo'
GROUP BY u.id, u.nombre, u.usuario;

-- Vista para resumen de incidentes
CREATE OR REPLACE VIEW incident_summary AS
SELECT 
    severity,
    status,
    COUNT(*) as count,
    AVG(TIMESTAMPDIFF(HOUR, created_at, COALESCE(resolved_at, NOW()))) as avg_resolution_time_hours
FROM incidents
GROUP BY severity, status;

-- Vista para métricas de backup
CREATE OR REPLACE VIEW backup_metrics AS
SELECT 
    type,
    scope,
    status,
    COUNT(*) as total_backups,
    AVG(file_size) as avg_file_size,
    MAX(completed_at) as last_backup,
    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_count
FROM backup_logs
WHERE started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY type, scope, status;

-- ==================================================================
-- PROCEDIMIENTOS ALMACENADOS ÚTILES
-- ==================================================================

DELIMITER //

-- Procedimiento para limpiar logs antiguos
CREATE PROCEDURE IF NOT EXISTS CleanOldLogs(IN days_to_keep INT)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Limpiar audit_logs antiguos
    DELETE FROM audit_logs WHERE timestamp < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
    
    -- Limpiar system_alerts resueltas antiguas
    DELETE FROM system_alerts 
    WHERE dismissed = 1 AND dismissed_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
    
    -- Limpiar métricas del sistema antiguas (mantener solo 90 días)
    DELETE FROM system_metrics WHERE recorded_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
    
    -- Limpiar sesiones de usuario antiguas
    DELETE FROM user_sessions WHERE ended_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    COMMIT;
END //

-- Procedimiento para generar reporte de actividad
CREATE PROCEDURE IF NOT EXISTS GenerateActivityReport(
    IN start_date DATETIME,
    IN end_date DATETIME
)
BEGIN
    SELECT 
        'audit_activity' as report_type,
        DATE(timestamp) as date,
        action_type,
        COUNT(*) as count
    FROM audit_logs
    WHERE timestamp BETWEEN start_date AND end_date
    GROUP BY DATE(timestamp), action_type
    
    UNION ALL
    
    SELECT 
        'incidents' as report_type,
        DATE(created_at) as date,
        CONCAT(severity, '_', status) as action_type,
        COUNT(*) as count
    FROM incidents
    WHERE created_at BETWEEN start_date AND end_date
    GROUP BY DATE(created_at), severity, status
    
    UNION ALL
    
    SELECT 
        'backups' as report_type,
        DATE(started_at) as date,
        CONCAT(type, '_', status) as action_type,
        COUNT(*) as count
    FROM backup_logs
    WHERE started_at BETWEEN start_date AND end_date
    GROUP BY DATE(started_at), type, status
    
    ORDER BY date DESC, report_type;
END //

DELIMITER ;

-- ==================================================================
-- ÍNDICES ADICIONALES PARA OPTIMIZACIÓN
-- ==================================================================

-- Índices compuestos para consultas comunes
CREATE INDEX idx_audit_user_action_time ON audit_logs(user_id, action_type, timestamp);
CREATE INDEX idx_incident_status_severity ON incidents(status, severity, created_at);
CREATE INDEX idx_alerts_level_acknowledged ON system_alerts(level, acknowledged, created_at);
CREATE INDEX idx_config_changes_name_time ON configuration_changes(config_name, created_at);
CREATE INDEX idx_metrics_name_time_composite ON system_metrics(metric_name, recorded_at, metric_value);

-- ==================================================================
-- COMENTARIOS FINALES
-- ==================================================================

/*
Este esquema de base de datos proporciona:

1. AUDITORÍA COMPLETA:
   - Seguimiento de todas las acciones de usuarios
   - Historial de cambios con datos antes/después
   - Información de sesión y ubicación

2. GESTIÓN DE BACKUPS:
   - Registro de todos los backups realizados
   - Seguimiento del estado y errores
   - Métricas de tamaño y tiempo

3. GESTIÓN DE INCIDENTES:
   - Sistema completo de tickets
   - Integración con herramientas externas
   - Seguimiento de resolución

4. SISTEMA DE ALERTAS:
   - Alertas en tiempo real
   - Configuración de umbrales
   - Historial de reconocimientos

5. GESTIÓN DE PROYECTOS:
   - Integración con múltiples plataformas
   - Sincronización de estados
   - Vinculación con incidentes

6. CONTROL DE CONFIGURACIONES:
   - Versionado con Git
   - Historial de cambios
   - Capacidad de reversión

7. MÉTRICAS Y MONITOREO:
   - Recopilación de métricas del sistema
   - Análisis de tendencias
   - Reportes automáticos

8. OPTIMIZACIÓN:
   - Índices estratégicos para rendimiento
   - Vistas para consultas comunes
   - Procedimientos para mantenimiento

El esquema está diseñado para ser escalable, mantenible y compatible
con los estándares de calidad ISO 17025.
*/