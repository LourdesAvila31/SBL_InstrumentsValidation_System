-- Script de creación de tablas para el sistema Developer Private Section
-- Ejecutar después de las tablas principales del sistema

-- Tabla de incidencias
CREATE TABLE IF NOT EXISTS incidents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    status ENUM('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
    category VARCHAR(100) DEFAULT 'general',
    priority INT DEFAULT 2,
    reporter_id INT,
    assigned_to INT NULL,
    external_reference VARCHAR(100) NULL,
    affected_systems JSON NULL,
    resolution_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    FOREIGN KEY (reporter_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_severity (severity),
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    INDEX idx_assigned (assigned_to)
);

-- Tabla de historial de incidencias
CREATE TABLE IF NOT EXISTS incident_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    incident_id INT NOT NULL,
    user_id INT,
    action VARCHAR(50) NOT NULL,
    details JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_incident (incident_id),
    INDEX idx_created (created_at)
);

-- Tabla de solicitudes de cambio
CREATE TABLE IF NOT EXISTS change_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    change_type ENUM('configuration', 'feature', 'bugfix', 'security', 'maintenance') NOT NULL,
    justification TEXT NOT NULL,
    status ENUM('draft', 'pending_approval', 'approved', 'in_progress', 'implemented', 'rolled_back') DEFAULT 'draft',
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    requester_id INT,
    reviewer_id INT NULL,
    impact_assessment TEXT NULL,
    rollback_plan TEXT NULL,
    testing_plan TEXT NULL,
    affected_systems JSON NULL,
    scheduled_date DATE NULL,
    estimated_duration VARCHAR(50) NULL,
    review_comments TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    FOREIGN KEY (requester_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (reviewer_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_type (change_type),
    INDEX idx_created (created_at)
);

-- Tabla de historial de cambios
CREATE TABLE IF NOT EXISTS change_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    change_id INT NOT NULL,
    user_id INT,
    action VARCHAR(50) NOT NULL,
    details JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (change_id) REFERENCES change_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_change (change_id),
    INDEX idx_created (created_at)
);

-- Tabla de documentos (SOP, AppCare, Handover)
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    document_type ENUM('sop', 'appcare', 'handover', 'manual', 'policy') NOT NULL,
    content LONGTEXT NOT NULL,
    version VARCHAR(20) DEFAULT '1.0',
    status ENUM('draft', 'review', 'approved', 'active', 'archived', 'expired') DEFAULT 'draft',
    department VARCHAR(100) DEFAULT 'IT',
    author_id INT,
    reviewer_id INT NULL,
    last_modified_by INT NULL,
    tags JSON NULL,
    metadata JSON NULL,
    review_cycle_days INT DEFAULT 365,
    effective_date DATE NULL,
    expiry_date DATE NULL,
    review_comments TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    activated_at TIMESTAMP NULL,
    FOREIGN KEY (author_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (reviewer_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (last_modified_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_type (document_type),
    INDEX idx_status (status),
    INDEX idx_department (department),
    INDEX idx_expiry (expiry_date),
    INDEX idx_created (created_at),
    FULLTEXT idx_content (title, content)
);

-- Tabla de historial de documentos
CREATE TABLE IF NOT EXISTS document_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    user_id INT,
    action VARCHAR(50) NOT NULL,
    details JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_document (document_id),
    INDEX idx_created (created_at)
);

-- Tabla de proveedores
CREATE TABLE IF NOT EXISTS vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    legal_name VARCHAR(255) NULL,
    contact_email VARCHAR(255) NOT NULL,
    contact_phone VARCHAR(50) NULL,
    service_type VARCHAR(100) NOT NULL,
    status ENUM('active', 'inactive', 'suspended', 'terminated') DEFAULT 'active',
    address TEXT NULL,
    tax_id VARCHAR(50) NULL,
    website VARCHAR(255) NULL,
    primary_contact_name VARCHAR(255) NULL,
    secondary_contact_name VARCHAR(255) NULL,
    secondary_contact_email VARCHAR(255) NULL,
    notes TEXT NULL,
    risk_level ENUM('low', 'medium', 'high') DEFAULT 'medium',
    certification_info JSON NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_service_type (service_type),
    INDEX idx_risk_level (risk_level),
    INDEX idx_name (name),
    UNIQUE KEY uk_email (contact_email)
);

-- Tabla de contratos con proveedores
CREATE TABLE IF NOT EXISTS vendor_contracts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    contract_number VARCHAR(100) NOT NULL,
    contract_type ENUM('service', 'license', 'maintenance', 'development', 'consulting') NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    value DECIMAL(15, 2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    payment_terms TEXT NULL,
    renewal_terms TEXT NULL,
    termination_clause TEXT NULL,
    deliverables JSON NULL,
    key_milestones JSON NULL,
    contract_manager INT,
    status ENUM('draft', 'active', 'expired', 'terminated') DEFAULT 'active',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    FOREIGN KEY (contract_manager) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_vendor (vendor_id),
    INDEX idx_status (status),
    INDEX idx_dates (start_date, end_date),
    UNIQUE KEY uk_contract_number (contract_number)
);

-- Tabla de SLAs (Service Level Agreements)
CREATE TABLE IF NOT EXISTS vendor_slas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    contract_id INT NULL,
    service_name VARCHAR(255) NOT NULL,
    service_description TEXT NULL,
    availability_target DECIMAL(5, 2) NOT NULL, -- Porcentaje (ej: 99.99)
    response_time_target INT NOT NULL, -- En minutos
    resolution_time_target INT DEFAULT 0, -- En horas
    performance_metrics JSON NULL,
    penalties JSON NULL,
    measurement_period ENUM('daily', 'weekly', 'monthly', 'quarterly') DEFAULT 'monthly',
    reporting_frequency ENUM('daily', 'weekly', 'monthly', 'quarterly') DEFAULT 'monthly',
    escalation_procedures TEXT NULL,
    status ENUM('active', 'breached', 'warning', 'suspended') DEFAULT 'active',
    effective_date DATE NOT NULL,
    review_date DATE NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    FOREIGN KEY (contract_id) REFERENCES vendor_contracts(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_vendor (vendor_id),
    INDEX idx_status (status),
    INDEX idx_effective (effective_date)
);

-- Tabla de métricas de SLA
CREATE TABLE IF NOT EXISTS sla_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sla_id INT NOT NULL,
    measurement_period_start DATE NOT NULL,
    measurement_period_end DATE NOT NULL,
    actual_availability DECIMAL(5, 2) NOT NULL,
    average_response_time INT NOT NULL, -- En minutos
    average_resolution_time INT DEFAULT 0, -- En horas
    incidents_count INT DEFAULT 0,
    breaches_count INT DEFAULT 0,
    performance_score DECIMAL(5, 2) DEFAULT 0,
    additional_metrics JSON NULL,
    notes TEXT NULL,
    recorded_by INT,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sla_id) REFERENCES vendor_slas(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_sla (sla_id),
    INDEX idx_period (measurement_period_start, measurement_period_end),
    INDEX idx_recorded (recorded_at)
);

-- Tabla de auditorías de proveedores
CREATE TABLE IF NOT EXISTS vendor_audits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    audit_type ENUM('compliance', 'security', 'performance', 'financial') NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    scheduled_date DATE NOT NULL,
    actual_date DATE NULL,
    estimated_duration_hours INT DEFAULT 8,
    actual_duration_hours INT NULL,
    audit_scope JSON NULL,
    checklist_items JSON NULL,
    findings JSON NULL,
    recommendations JSON NULL,
    assigned_auditor INT,
    status ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    overall_score DECIMAL(3, 1) NULL, -- 0.0 to 10.0
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_auditor) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_vendor (vendor_id),
    INDEX idx_status (status),
    INDEX idx_scheduled (scheduled_date),
    INDEX idx_type (audit_type)
);

-- Tabla de reglas de alerta
CREATE TABLE IF NOT EXISTS alert_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    alert_type ENUM('system', 'security', 'performance', 'compliance', 'business') NOT NULL,
    severity ENUM('info', 'warning', 'critical') NOT NULL,
    condition_config JSON NOT NULL, -- Configuración de condiciones
    threshold_config JSON NULL, -- Configuración de umbrales
    channels JSON NOT NULL, -- Canales de notificación (email, sms, slack, etc.)
    recipients JSON NULL, -- Lista de destinatarios
    schedule_config JSON NULL, -- Configuración de horarios
    suppression_config JSON NULL, -- Configuración de supresión
    template_subject VARCHAR(255) NULL,
    template_body TEXT NULL,
    enabled BOOLEAN DEFAULT TRUE,
    auto_resolve BOOLEAN DEFAULT FALSE,
    escalation_config JSON NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_type (alert_type),
    INDEX idx_severity (severity),
    INDEX idx_enabled (enabled)
);

-- Tabla de alertas
CREATE TABLE IF NOT EXISTS alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rule_id INT,
    alert_type VARCHAR(50) NOT NULL,
    severity ENUM('info', 'warning', 'critical') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    source_data JSON NULL,
    status ENUM('active', 'acknowledged', 'resolved', 'suppressed') DEFAULT 'active',
    correlation_id VARCHAR(64) NULL, -- Para agrupar alertas relacionadas
    acknowledged_by INT NULL,
    acknowledged_at TIMESTAMP NULL,
    ack_notes TEXT NULL,
    resolved_by INT NULL,
    resolved_at TIMESTAMP NULL,
    resolution TEXT NULL,
    triggered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rule_id) REFERENCES alert_rules(id) ON DELETE SET NULL,
    FOREIGN KEY (acknowledged_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (resolved_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_rule (rule_id),
    INDEX idx_type (alert_type),
    INDEX idx_severity (severity),
    INDEX idx_status (status),
    INDEX idx_triggered (triggered_at),
    INDEX idx_correlation (correlation_id)
);

-- Tabla de métricas del sistema para monitoreo
CREATE TABLE IF NOT EXISTS system_health_checks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    check_type VARCHAR(50) NOT NULL,
    status ENUM('up', 'down', 'degraded') NOT NULL,
    response_time_ms INT NULL,
    details JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (check_type),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
);

-- Tabla de logs de rendimiento de API
CREATE TABLE IF NOT EXISTS api_performance_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    endpoint VARCHAR(255) NOT NULL,
    method VARCHAR(10) NOT NULL,
    response_time_ms INT NOT NULL,
    status_code INT NOT NULL,
    user_id INT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_endpoint (endpoint),
    INDEX idx_response_time (response_time_ms),
    INDEX idx_created (created_at)
);

-- Tabla de recursos del sistema
CREATE TABLE IF NOT EXISTS system_resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cpu_usage DECIMAL(5, 2) NOT NULL, -- Porcentaje
    memory_usage DECIMAL(5, 2) NOT NULL, -- Porcentaje
    disk_usage DECIMAL(5, 2) NOT NULL, -- Porcentaje
    network_io DECIMAL(15, 2) NULL, -- MB/s
    active_connections INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at)
);

-- Tabla de sesiones de usuario para monitoreo
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    session_id VARCHAR(128) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_session (session_id),
    INDEX idx_activity (last_activity),
    UNIQUE KEY uk_session (session_id)
);

-- Tabla de eventos de seguridad
CREATE TABLE IF NOT EXISTS security_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    description TEXT NOT NULL,
    source_ip VARCHAR(45) NULL,
    user_id INT NULL,
    details JSON NULL,
    status ENUM('open', 'investigating', 'resolved', 'false_positive') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_type (event_type),
    INDEX idx_severity (severity),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
);

-- Tabla de auditorías de cumplimiento
CREATE TABLE IF NOT EXISTS compliance_audits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    audit_name VARCHAR(255) NOT NULL,
    audit_type VARCHAR(100) NOT NULL,
    description TEXT NULL,
    due_date DATE NOT NULL,
    status ENUM('pending', 'in_progress', 'completed', 'overdue') DEFAULT 'pending',
    assigned_to INT NULL,
    checklist_items JSON NULL,
    findings JSON NULL,
    score DECIMAL(5, 2) NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (assigned_to) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_due_date (due_date),
    INDEX idx_type (audit_type)
);

-- Tabla de certificaciones
CREATE TABLE IF NOT EXISTS certifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    issuing_authority VARCHAR(255) NOT NULL,
    certificate_number VARCHAR(100) NULL,
    issue_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    status ENUM('active', 'expired', 'suspended', 'renewed') DEFAULT 'active',
    scope TEXT NULL,
    renewal_requirements TEXT NULL,
    documents JSON NULL,
    responsible_person INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (responsible_person) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_expiry (expiry_date),
    INDEX idx_authority (issuing_authority)
);

-- Insertar permisos específicos para desarrolladores
INSERT IGNORE INTO permissions (id, nombre, descripcion) VALUES
(201, 'developer_dashboard_access', 'Acceso al dashboard privado de desarrolladores'),
(202, 'developer_incidents_manage', 'Gestionar incidencias del sistema'),
(203, 'developer_changes_manage', 'Gestionar solicitudes de cambio'),
(204, 'developer_documents_manage', 'Gestionar documentación técnica'),
(205, 'developer_vendors_manage', 'Gestionar proveedores y contratos'),
(206, 'developer_alerts_manage', 'Gestionar alertas automáticas'),
(207, 'developer_monitoring_access', 'Acceso a monitoreo avanzado del sistema'),
(208, 'developer_audit_access', 'Acceso a logs de auditoría del sistema'),
(209, 'developer_security_events', 'Acceso a eventos de seguridad');

-- Asignar permisos al rol Developer
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.id IN (201, 202, 203, 204, 205, 206, 207, 208, 209)
WHERE r.nombre = 'Developer'
  AND NOT EXISTS (
    SELECT 1 FROM role_permissions rp 
    WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );

-- Datos de ejemplo para desarrollo
INSERT IGNORE INTO system_health_checks (check_type, status, response_time_ms, details) VALUES
('database', 'up', 15, '{"connections": 45, "slow_queries": 2}'),
('api', 'up', 120, '{"avg_response": 118, "errors": 0}'),
('storage', 'up', 8, '{"free_space": "85%", "io_wait": 0.2}');

INSERT IGNORE INTO system_resources (cpu_usage, memory_usage, disk_usage, network_io, active_connections) VALUES
(35.5, 68.2, 45.8, 12.5, 156),
(42.1, 71.3, 45.9, 18.7, 162),
(38.7, 69.8, 46.0, 15.2, 158);

-- Crear índices adicionales para mejor rendimiento
CREATE INDEX IF NOT EXISTS idx_auditoria_logs_developer ON auditoria_logs (seccion, created_at) 
WHERE seccion = 'developer_private';

CREATE INDEX IF NOT EXISTS idx_documents_active ON documents (document_type, status, expiry_date) 
WHERE status = 'active';

CREATE INDEX IF NOT EXISTS idx_incidents_open ON incidents (status, severity, created_at) 
WHERE status IN ('open', 'in_progress');

CREATE INDEX IF NOT EXISTS idx_alerts_active ON alerts (status, severity, triggered_at) 
WHERE status = 'active';

-- Crear vistas para facilitar consultas comunes
CREATE OR REPLACE VIEW v_developer_kpis AS
SELECT 
    (SELECT COUNT(*) FROM incidents WHERE status IN ('open', 'in_progress')) as open_incidents,
    (SELECT COUNT(*) FROM incidents WHERE severity = 'critical' AND status != 'closed') as critical_incidents,
    (SELECT COUNT(*) FROM alerts WHERE status = 'active' AND severity = 'critical') as critical_alerts,
    (SELECT COUNT(*) FROM documents WHERE status = 'active' AND expiry_date <= DATE_ADD(NOW(), INTERVAL 30 DAY)) as expiring_documents,
    (SELECT COUNT(*) FROM vendor_slas WHERE status = 'breached') as breached_slas,
    (SELECT COUNT(*) FROM compliance_audits WHERE status = 'overdue') as overdue_audits;

CREATE OR REPLACE VIEW v_system_performance AS
SELECT 
    DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour_bucket,
    AVG(cpu_usage) as avg_cpu,
    AVG(memory_usage) as avg_memory,
    AVG(disk_usage) as avg_disk,
    MAX(cpu_usage) as max_cpu,
    MAX(memory_usage) as max_memory
FROM system_resources 
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY DATE_FORMAT(created_at, '%Y-%m-%d %H')
ORDER BY hour_bucket;

-- Triggers para mantener datos consistentes
DELIMITER //

CREATE TRIGGER IF NOT EXISTS tr_incidents_update_priority 
BEFORE UPDATE ON incidents
FOR EACH ROW
BEGIN
    IF NEW.severity = 'critical' THEN
        SET NEW.priority = 4;
    ELSEIF NEW.severity = 'high' THEN
        SET NEW.priority = 3;
    ELSEIF NEW.severity = 'medium' THEN
        SET NEW.priority = 2;
    ELSE
        SET NEW.priority = 1;
    END IF;
END //

CREATE TRIGGER IF NOT EXISTS tr_documents_version_control
BEFORE INSERT ON documents
FOR EACH ROW
BEGIN
    IF NEW.version IS NULL OR NEW.version = '' THEN
        SET NEW.version = '1.0';
    END IF;
END //

CREATE TRIGGER IF NOT EXISTS tr_alerts_correlation
BEFORE INSERT ON alerts
FOR EACH ROW
BEGIN
    IF NEW.correlation_id IS NULL OR NEW.correlation_id = '' THEN
        SET NEW.correlation_id = MD5(CONCAT(NEW.rule_id, '_', NEW.alert_type, '_', NOW()));
    END IF;
END //

DELIMITER ;

-- Crear procedimientos almacenados para operaciones comunes
DELIMITER //

CREATE PROCEDURE IF NOT EXISTS sp_get_developer_dashboard_data()
BEGIN
    -- KPIs principales
    SELECT * FROM v_developer_kpis;
    
    -- Incidencias recientes
    SELECT i.*, u.nombre as reporter_name 
    FROM incidents i 
    LEFT JOIN usuarios u ON u.id = i.reporter_id 
    WHERE i.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY i.created_at DESC 
    LIMIT 10;
    
    -- Alertas activas
    SELECT * FROM alerts 
    WHERE status = 'active' 
    ORDER BY triggered_at DESC 
    LIMIT 10;
    
    -- Documentos próximos a vencer
    SELECT * FROM documents 
    WHERE status = 'active' 
    AND expiry_date <= DATE_ADD(NOW(), INTERVAL 30 DAY)
    ORDER BY expiry_date ASC 
    LIMIT 10;
END //

CREATE PROCEDURE IF NOT EXISTS sp_create_incident(
    IN p_title VARCHAR(255),
    IN p_description TEXT,
    IN p_severity ENUM('low', 'medium', 'high', 'critical'),
    IN p_reporter_id INT,
    IN p_category VARCHAR(100),
    IN p_affected_systems JSON
)
BEGIN
    DECLARE incident_id INT;
    
    INSERT INTO incidents (title, description, severity, reporter_id, category, affected_systems)
    VALUES (p_title, p_description, p_severity, p_reporter_id, p_category, p_affected_systems);
    
    SET incident_id = LAST_INSERT_ID();
    
    INSERT INTO incident_history (incident_id, user_id, action, details)
    VALUES (incident_id, p_reporter_id, 'created', JSON_OBJECT('severity', p_severity));
    
    SELECT incident_id as id;
END //

DELIMITER ;

-- Comentarios de documentación
ALTER TABLE incidents COMMENT = 'Registro de incidencias del sistema para tracking y resolución';
ALTER TABLE documents COMMENT = 'Documentación técnica incluyendo SOP, AppCare y Handover';
ALTER TABLE vendors COMMENT = 'Registro de proveedores externos y sus datos de contacto';
ALTER TABLE vendor_contracts COMMENT = 'Contratos con proveedores incluyendo términos y condiciones';
ALTER TABLE vendor_slas COMMENT = 'Acuerdos de nivel de servicio con métricas y objetivos';
ALTER TABLE alerts COMMENT = 'Sistema de alertas automáticas del sistema';
ALTER TABLE alert_rules COMMENT = 'Reglas de configuración para alertas automáticas';

COMMIT;