<?php
/**
 * Script de Inicialización del Sistema GAMP 5
 * 
 * Este script configura todas las tablas y datos iniciales necesarios
 * para el funcionamiento del sistema de administración GAMP 5.
 * 
 * Ejecutar una sola vez durante la instalación inicial.
 */

require_once __DIR__ . '/app/Core/db_config.php';

try {
    $db = DatabaseManager::getConnection();
    
    echo "🚀 Iniciando configuración del Sistema GAMP 5...\n\n";
    
    // Crear todas las tablas del sistema
    createGAMP5Tables($db);
    
    // Insertar datos iniciales
    insertInitialData($db);
    
    // Crear índices para optimización
    createIndexes($db);
    
    // Verificar la instalación
    verifyInstallation($db);
    
    echo "\n✅ Sistema GAMP 5 configurado exitosamente!\n";
    echo "📋 Resumen de la instalación:\n";
    echo "   - Tablas creadas: 33\n";
    echo "   - Datos iniciales: Insertados\n";
    echo "   - Índices: Creados\n";
    echo "   - Estado: ✅ LISTO PARA USAR\n\n";
    echo "🔗 Acceso al sistema: /public/gamp5_dashboard.html\n";
    
} catch (Exception $e) {
    echo "❌ Error durante la configuración: " . $e->getMessage() . "\n";
    echo "📝 Verifique la conexión a la base de datos y permisos.\n";
    exit(1);
}

/**
 * Crea todas las tablas necesarias para el sistema GAMP 5
 */
function createGAMP5Tables($db) {
    echo "📊 Creando tablas del sistema GAMP 5...\n";
    
    $tables = [
        // Tablas del Sistema de Ciclo de Vida
        'gamp5_system_lifecycles' => "
            CREATE TABLE IF NOT EXISTS gamp5_system_lifecycles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                system_name VARCHAR(255) NOT NULL,
                description TEXT,
                system_type ENUM('SOFTWARE', 'HARDWARE', 'INFRASTRUCTURE', 'PROCESS', 'COMBINATION') NOT NULL,
                gamp_category ENUM('1', '2', '3', '4', '5') NOT NULL,
                risk_classification ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') NOT NULL,
                regulatory_requirements JSON,
                business_requirements TEXT,
                technical_requirements TEXT,
                current_stage ENUM('PLANNING', 'DESIGN', 'BUILD', 'TEST', 'DEPLOY', 'OPERATE', 'MONITOR', 'CHANGE', 'RETIREMENT') DEFAULT 'PLANNING',
                status ENUM('ACTIVE', 'INACTIVE', 'SUSPENDED', 'COMPLETED') DEFAULT 'ACTIVE',
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_system_name (system_name),
                INDEX idx_current_stage (current_stage),
                INDEX idx_status (status),
                INDEX idx_created_by (created_by)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        'gamp5_lifecycle_stages' => "
            CREATE TABLE IF NOT EXISTS gamp5_lifecycle_stages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                lifecycle_id INT NOT NULL,
                stage_name ENUM('PLANNING', 'DESIGN', 'BUILD', 'TEST', 'DEPLOY', 'OPERATE', 'MONITOR', 'CHANGE', 'RETIREMENT') NOT NULL,
                stage_description TEXT,
                deliverables JSON,
                acceptance_criteria TEXT,
                status ENUM('NOT_STARTED', 'IN_PROGRESS', 'COMPLETED', 'ON_HOLD', 'CANCELLED') DEFAULT 'NOT_STARTED',
                started_at TIMESTAMP NULL,
                completed_at TIMESTAMP NULL,
                validation_report TEXT,
                approved_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (lifecycle_id) REFERENCES gamp5_system_lifecycles(id) ON DELETE CASCADE,
                INDEX idx_lifecycle_stage (lifecycle_id, stage_name),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        'gamp5_verification_schedules' => "
            CREATE TABLE IF NOT EXISTS gamp5_verification_schedules (
                id INT AUTO_INCREMENT PRIMARY KEY,
                lifecycle_id INT NOT NULL,
                verification_type ENUM('PERIODIC', 'MILESTONE', 'REGULATORY', 'CHANGE_CONTROL') NOT NULL,
                verification_frequency ENUM('WEEKLY', 'MONTHLY', 'QUARTERLY', 'ANNUALLY', 'AS_NEEDED') NOT NULL,
                next_verification_date DATE NOT NULL,
                responsible_user_id INT NOT NULL,
                verification_checklist JSON,
                status ENUM('SCHEDULED', 'IN_PROGRESS', 'COMPLETED', 'OVERDUE', 'CANCELLED') DEFAULT 'SCHEDULED',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (lifecycle_id) REFERENCES gamp5_system_lifecycles(id) ON DELETE CASCADE,
                INDEX idx_next_verification (next_verification_date),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        // Tablas del Sistema de Calificación
        'gamp5_qualifications' => "
            CREATE TABLE IF NOT EXISTS gamp5_qualifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                system_name VARCHAR(255) NOT NULL,
                qualification_type ENUM('IQ', 'OQ', 'PQ') NOT NULL,
                description TEXT,
                planned_start_date DATE NOT NULL,
                planned_end_date DATE NOT NULL,
                actual_start_date DATE NULL,
                actual_end_date DATE NULL,
                status ENUM('PLANNING', 'IN_PROGRESS', 'COMPLETED', 'ON_HOLD', 'CANCELLED') DEFAULT 'PLANNING',
                responsible_person VARCHAR(255) NOT NULL,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_qualification_type (qualification_type),
                INDEX idx_status (status),
                INDEX idx_system_name (system_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        'gamp5_qualification_steps' => "
            CREATE TABLE IF NOT EXISTS gamp5_qualification_steps (
                id INT AUTO_INCREMENT PRIMARY KEY,
                qualification_id INT NOT NULL,
                step_number INT NOT NULL,
                step_description TEXT NOT NULL,
                expected_result TEXT,
                actual_result TEXT,
                status ENUM('NOT_STARTED', 'IN_PROGRESS', 'PASSED', 'FAILED', 'NOT_APPLICABLE') DEFAULT 'NOT_STARTED',
                executed_by INT NULL,
                executed_at TIMESTAMP NULL,
                evidence_documents JSON,
                comments TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (qualification_id) REFERENCES gamp5_qualifications(id) ON DELETE CASCADE,
                UNIQUE KEY unique_step (qualification_id, step_number),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        'gamp5_qualification_deviations' => "
            CREATE TABLE IF NOT EXISTS gamp5_qualification_deviations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                qualification_id INT NOT NULL,
                step_id INT NULL,
                deviation_number VARCHAR(50) NOT NULL UNIQUE,
                description TEXT NOT NULL,
                impact_assessment TEXT,
                corrective_action TEXT,
                preventive_action TEXT,
                status ENUM('OPEN', 'INVESTIGATING', 'RESOLVED', 'CLOSED') DEFAULT 'OPEN',
                severity ENUM('MINOR', 'MAJOR', 'CRITICAL') NOT NULL,
                reported_by INT NOT NULL,
                assigned_to INT NULL,
                target_closure_date DATE,
                actual_closure_date DATE NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (qualification_id) REFERENCES gamp5_qualifications(id) ON DELETE CASCADE,
                FOREIGN KEY (step_id) REFERENCES gamp5_qualification_steps(id) ON DELETE SET NULL,
                INDEX idx_deviation_number (deviation_number),
                INDEX idx_status (status),
                INDEX idx_severity (severity)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        // Tablas del Sistema de Validación
        'gamp5_system_validations' => "
            CREATE TABLE IF NOT EXISTS gamp5_system_validations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                system_name VARCHAR(255) NOT NULL,
                validation_type ENUM('COMPUTER_SYSTEM', 'ANALYTICAL_METHOD', 'CLEANING', 'PROCESS', 'EQUIPMENT') NOT NULL,
                description TEXT,
                validation_protocol TEXT,
                acceptance_criteria TEXT,
                test_scenarios JSON,
                status ENUM('DRAFT', 'APPROVED', 'IN_EXECUTION', 'COMPLETED', 'FAILED') DEFAULT 'DRAFT',
                criticality ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') NOT NULL,
                gxp_applicable BOOLEAN DEFAULT TRUE,
                validation_date DATE NULL,
                revalidation_due_date DATE NULL,
                created_by INT NOT NULL,
                approved_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_validation_type (validation_type),
                INDEX idx_status (status),
                INDEX idx_revalidation_due (revalidation_due_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        'gamp5_validation_executions' => "
            CREATE TABLE IF NOT EXISTS gamp5_validation_executions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                validation_id INT NOT NULL,
                execution_date DATE NOT NULL,
                executed_by INT NOT NULL,
                test_results JSON,
                overall_result ENUM('PASS', 'FAIL', 'PARTIAL') NOT NULL,
                deviations_count INT DEFAULT 0,
                recommendations TEXT,
                next_steps TEXT,
                report_generated BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (validation_id) REFERENCES gamp5_system_validations(id) ON DELETE CASCADE,
                INDEX idx_execution_date (execution_date),
                INDEX idx_overall_result (overall_result)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        // Tablas del Sistema de Gestión de Cambios
        'gamp5_change_requests' => "
            CREATE TABLE IF NOT EXISTS gamp5_change_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                change_number VARCHAR(50) NOT NULL UNIQUE,
                title VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                change_type ENUM('CONFIGURATION', 'SOFTWARE', 'HARDWARE', 'PROCESS', 'DOCUMENTATION') NOT NULL,
                impact_level ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') NOT NULL,
                business_justification TEXT,
                technical_details TEXT,
                affected_systems JSON,
                status ENUM('DRAFT', 'SUBMITTED', 'UNDER_REVIEW', 'APPROVED', 'REJECTED', 'IMPLEMENTED', 'CLOSED') DEFAULT 'DRAFT',
                priority ENUM('LOW', 'NORMAL', 'HIGH', 'URGENT') DEFAULT 'NORMAL',
                requested_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_change_number (change_number),
                INDEX idx_status (status),
                INDEX idx_impact_level (impact_level)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        'gamp5_change_approvals' => "
            CREATE TABLE IF NOT EXISTS gamp5_change_approvals (
                id INT AUTO_INCREMENT PRIMARY KEY,
                change_request_id INT NOT NULL,
                approval_level ENUM('TECHNICAL', 'BUSINESS', 'QUALITY', 'REGULATORY', 'FINAL') NOT NULL,
                approver_user_id INT NOT NULL,
                decision ENUM('PENDING', 'APPROVED', 'REJECTED', 'NEEDS_INFO') DEFAULT 'PENDING',
                comments TEXT,
                conditions TEXT,
                approved_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (change_request_id) REFERENCES gamp5_change_requests(id) ON DELETE CASCADE,
                INDEX idx_change_approval (change_request_id, approval_level),
                INDEX idx_decision (decision)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        'gamp5_change_implementations' => "
            CREATE TABLE IF NOT EXISTS gamp5_change_implementations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                change_request_id INT NOT NULL,
                step_number INT NOT NULL,
                step_description TEXT NOT NULL,
                assigned_to INT NOT NULL,
                planned_start_date DATE,
                planned_end_date DATE,
                actual_start_date DATE NULL,
                actual_end_date DATE NULL,
                status ENUM('NOT_STARTED', 'IN_PROGRESS', 'COMPLETED', 'ON_HOLD', 'CANCELLED') DEFAULT 'NOT_STARTED',
                validation_required BOOLEAN DEFAULT FALSE,
                rollback_plan TEXT,
                implementation_notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (change_request_id) REFERENCES gamp5_change_requests(id) ON DELETE CASCADE,
                INDEX idx_change_step (change_request_id, step_number),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        // Tablas del Sistema de Monitoreo
        'gamp5_monitoring_metrics' => "
            CREATE TABLE IF NOT EXISTS gamp5_monitoring_metrics (
                id INT AUTO_INCREMENT PRIMARY KEY,
                metric_name VARCHAR(255) NOT NULL,
                description TEXT,
                metric_type ENUM('PERFORMANCE', 'AVAILABILITY', 'SECURITY', 'COMPLIANCE', 'QUALITY') NOT NULL,
                measurement_unit VARCHAR(50),
                target_value DECIMAL(10,2),
                warning_threshold DECIMAL(10,2),
                critical_threshold DECIMAL(10,2),
                monitoring_frequency ENUM('REAL_TIME', 'HOURLY', 'DAILY', 'WEEKLY', 'MONTHLY') NOT NULL,
                is_active BOOLEAN DEFAULT TRUE,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_metric_type (metric_type),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        'gamp5_monitoring_data' => "
            CREATE TABLE IF NOT EXISTS gamp5_monitoring_data (
                id INT AUTO_INCREMENT PRIMARY KEY,
                metric_id INT NOT NULL,
                recorded_value DECIMAL(10,2) NOT NULL,
                recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                source_system VARCHAR(255),
                additional_data JSON,
                alert_triggered BOOLEAN DEFAULT FALSE,
                FOREIGN KEY (metric_id) REFERENCES gamp5_monitoring_metrics(id) ON DELETE CASCADE,
                INDEX idx_metric_time (metric_id, recorded_at),
                INDEX idx_recorded_at (recorded_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        'gamp5_system_alerts' => "
            CREATE TABLE IF NOT EXISTS gamp5_system_alerts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                alert_type ENUM('PERFORMANCE', 'AVAILABILITY', 'SECURITY', 'COMPLIANCE', 'QUALITY', 'SYSTEM') NOT NULL,
                severity ENUM('INFO', 'WARNING', 'MINOR', 'MAJOR', 'CRITICAL') NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                source_system VARCHAR(255),
                related_metric_id INT NULL,
                triggered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                acknowledged_at TIMESTAMP NULL,
                acknowledged_by INT NULL,
                resolved_at TIMESTAMP NULL,
                resolved_by INT NULL,
                status ENUM('ACTIVE', 'ACKNOWLEDGED', 'RESOLVED', 'CLOSED') DEFAULT 'ACTIVE',
                resolution_notes TEXT,
                FOREIGN KEY (related_metric_id) REFERENCES gamp5_monitoring_metrics(id) ON DELETE SET NULL,
                INDEX idx_severity (severity),
                INDEX idx_status (status),
                INDEX idx_triggered_at (triggered_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        // Tablas del Sistema de Gestión de Riesgos
        'gamp5_risk_assessments' => "
            CREATE TABLE IF NOT EXISTS gamp5_risk_assessments (
                id INT PRIMARY KEY AUTO_INCREMENT,
                assessment_name VARCHAR(255) NOT NULL,
                system_name VARCHAR(255) NOT NULL,
                gamp_category ENUM('1', '2', '3', '4', '5') NOT NULL,
                assessment_scope TEXT NOT NULL,
                business_process TEXT,
                regulatory_requirements JSON,
                assessment_date DATE NOT NULL,
                assessment_version VARCHAR(20) DEFAULT '1.0',
                status ENUM('DRAFT', 'IN_REVIEW', 'APPROVED', 'IMPLEMENTED', 'CLOSED') DEFAULT 'DRAFT',
                risk_owner INT NOT NULL,
                created_by INT NOT NULL,
                approved_by INT NULL,
                approved_date DATE NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_system_name (system_name),
                INDEX idx_status (status),
                INDEX idx_gamp_category (gamp_category)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        'gamp5_risk_items' => "
            CREATE TABLE IF NOT EXISTS gamp5_risk_items (
                id INT PRIMARY KEY AUTO_INCREMENT,
                assessment_id INT NOT NULL,
                risk_id VARCHAR(50) NOT NULL,
                risk_title VARCHAR(255) NOT NULL,
                risk_description TEXT NOT NULL,
                risk_category ENUM('PATIENT_SAFETY', 'PRODUCT_QUALITY', 'DATA_INTEGRITY', 'REGULATORY_COMPLIANCE', 'BUSINESS_CONTINUITY', 'SYSTEM_AVAILABILITY', 'SECURITY', 'PERFORMANCE') NOT NULL,
                process_step VARCHAR(255),
                failure_mode TEXT,
                potential_causes TEXT,
                current_controls TEXT,
                probability_score INT NOT NULL CHECK (probability_score BETWEEN 1 AND 5),
                severity_score INT NOT NULL CHECK (severity_score BETWEEN 1 AND 5),
                detectability_score INT NOT NULL CHECK (detectability_score BETWEEN 1 AND 5),
                initial_risk_score INT GENERATED ALWAYS AS (probability_score * severity_score * detectability_score) STORED,
                risk_level ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') NOT NULL,
                acceptability ENUM('ACCEPTABLE', 'UNACCEPTABLE', 'TOLERABLE') NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (assessment_id) REFERENCES gamp5_risk_assessments(id) ON DELETE CASCADE,
                UNIQUE KEY unique_risk (assessment_id, risk_id),
                INDEX idx_risk_level (risk_level),
                INDEX idx_risk_category (risk_category)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        'gamp5_risk_mitigations' => "
            CREATE TABLE IF NOT EXISTS gamp5_risk_mitigations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                risk_item_id INT NOT NULL,
                mitigation_strategy ENUM('AVOID', 'REDUCE', 'TRANSFER', 'ACCEPT') NOT NULL,
                mitigation_description TEXT NOT NULL,
                implementation_approach TEXT,
                responsible_person INT NOT NULL,
                target_completion_date DATE NOT NULL,
                actual_completion_date DATE NULL,
                status ENUM('PLANNED', 'IN_PROGRESS', 'COMPLETED', 'VERIFIED', 'CANCELLED') DEFAULT 'PLANNED',
                effectiveness_review TEXT,
                cost_estimate DECIMAL(10,2),
                priority ENUM('LOW', 'MEDIUM', 'HIGH', 'URGENT') DEFAULT 'MEDIUM',
                verification_required BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (risk_item_id) REFERENCES gamp5_risk_items(id) ON DELETE CASCADE,
                INDEX idx_status (status),
                INDEX idx_priority (priority),
                INDEX idx_target_date (target_completion_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        'gamp5_residual_risks' => "
            CREATE TABLE IF NOT EXISTS gamp5_residual_risks (
                id INT PRIMARY KEY AUTO_INCREMENT,
                risk_item_id INT NOT NULL,
                post_mitigation_probability INT NOT NULL CHECK (post_mitigation_probability BETWEEN 1 AND 5),
                post_mitigation_severity INT NOT NULL CHECK (post_mitigation_severity BETWEEN 1 AND 5),
                post_mitigation_detectability INT NOT NULL CHECK (post_mitigation_detectability BETWEEN 1 AND 5),
                residual_risk_score INT GENERATED ALWAYS AS (post_mitigation_probability * post_mitigation_severity * post_mitigation_detectability) STORED,
                residual_risk_level ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') NOT NULL,
                residual_acceptability ENUM('ACCEPTABLE', 'UNACCEPTABLE', 'TOLERABLE') NOT NULL,
                justification TEXT,
                approval_required BOOLEAN DEFAULT FALSE,
                approved_by INT NULL,
                approved_date DATE NULL,
                review_date DATE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (risk_item_id) REFERENCES gamp5_risk_items(id) ON DELETE CASCADE,
                INDEX idx_residual_level (residual_risk_level),
                INDEX idx_review_date (review_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        'gamp5_risk_reviews' => "
            CREATE TABLE IF NOT EXISTS gamp5_risk_reviews (
                id INT PRIMARY KEY AUTO_INCREMENT,
                assessment_id INT NOT NULL,
                review_date DATE NOT NULL,
                review_type ENUM('PERIODIC', 'TRIGGERED', 'POST_INCIDENT', 'REGULATORY') NOT NULL,
                review_trigger TEXT,
                reviewer_id INT NOT NULL,
                review_findings TEXT,
                new_risks_identified INT DEFAULT 0,
                risks_closed INT DEFAULT 0,
                risks_modified INT DEFAULT 0,
                overall_risk_trend ENUM('IMPROVING', 'STABLE', 'DETERIORATING') NOT NULL,
                recommendations TEXT,
                next_review_date DATE NOT NULL,
                status ENUM('SCHEDULED', 'IN_PROGRESS', 'COMPLETED', 'APPROVED') DEFAULT 'SCHEDULED',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (assessment_id) REFERENCES gamp5_risk_assessments(id) ON DELETE CASCADE,
                INDEX idx_review_date (review_date),
                INDEX idx_next_review (next_review_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        'gamp5_risk_matrix_config' => "
            CREATE TABLE IF NOT EXISTS gamp5_risk_matrix_config (
                id INT PRIMARY KEY AUTO_INCREMENT,
                matrix_name VARCHAR(100) NOT NULL,
                probability_min INT NOT NULL,
                probability_max INT NOT NULL,
                severity_min INT NOT NULL,
                severity_max INT NOT NULL,
                detectability_min INT NOT NULL,
                detectability_max INT NOT NULL,
                risk_level ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') NOT NULL,
                color_code VARCHAR(7) NOT NULL,
                action_required TEXT,
                approval_required BOOLEAN DEFAULT FALSE,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        // Tablas del Sistema de Gestión Documental
        'gamp5_documents' => "
            CREATE TABLE IF NOT EXISTS gamp5_documents (
                id INT PRIMARY KEY AUTO_INCREMENT,
                document_number VARCHAR(50) NOT NULL UNIQUE,
                title VARCHAR(255) NOT NULL,
                document_type ENUM('URS', 'FS', 'DS', 'SOP', 'VMP', 'IQ', 'OQ', 'PQ', 'PROTOCOL', 'REPORT', 
                                  'RISK_ASSESSMENT', 'CHANGE_CONTROL', 'TRAINING', 'PROCEDURE', 'POLICY', 
                                  'MANUAL', 'SPECIFICATION', 'CERTIFICATE', 'AUDIT_REPORT', 'CORRECTIVE_ACTION') NOT NULL,
                classification ENUM('PUBLIC', 'INTERNAL', 'CONFIDENTIAL', 'RESTRICTED', 'GXP_CRITICAL') NOT NULL DEFAULT 'INTERNAL',
                description TEXT,
                author_id INT NOT NULL,
                department VARCHAR(100),
                system_id INT,
                retention_period INT DEFAULT 7,
                effective_date DATE,
                review_date DATE,
                status ENUM('DRAFT', 'UNDER_REVIEW', 'APPROVED', 'EFFECTIVE', 'SUPERSEDED', 'RETIRED', 'ARCHIVED') NOT NULL DEFAULT 'DRAFT',
                version VARCHAR(20) NOT NULL DEFAULT '1.0',
                created_by INT NOT NULL,
                updated_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_document_number (document_number),
                INDEX idx_document_type (document_type),
                INDEX idx_status (status),
                INDEX idx_classification (classification),
                INDEX idx_author (author_id),
                INDEX idx_system (system_id),
                INDEX idx_review_date (review_date),
                FOREIGN KEY (author_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
                FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE RESTRICT,
                FOREIGN KEY (updated_by) REFERENCES usuarios(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        'gamp5_document_versions' => "
            CREATE TABLE IF NOT EXISTS gamp5_document_versions (
                id INT PRIMARY KEY AUTO_INCREMENT,
                document_id INT NOT NULL,
                version VARCHAR(20) NOT NULL,
                content LONGTEXT,
                file_path VARCHAR(500),
                file_hash VARCHAR(64),
                change_reason ENUM('SCHEDULED_REVIEW', 'CHANGE_CONTROL', 'REGULATORY_UPDATE', 'ERROR_CORRECTION', 
                                  'PROCESS_IMPROVEMENT', 'AUDIT_FINDING', 'DEVIATION_INVESTIGATION') NOT NULL,
                change_description TEXT,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_document_version (document_id, version),
                INDEX idx_created_date (created_at),
                FOREIGN KEY (document_id) REFERENCES gamp5_documents(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        'gamp5_document_signatures' => "
            CREATE TABLE IF NOT EXISTS gamp5_document_signatures (
                id INT PRIMARY KEY AUTO_INCREMENT,
                document_id INT NOT NULL,
                signer_id INT NOT NULL,
                signature_type ENUM('AUTHOR', 'REVIEWER', 'APPROVER', 'QA_APPROVAL', 'TECHNICAL_APPROVAL', 'MANAGEMENT_APPROVAL') NOT NULL,
                signature_meaning TEXT NOT NULL,
                signature_hash VARCHAR(64) NOT NULL,
                version VARCHAR(20) NOT NULL,
                ip_address VARCHAR(45),
                user_agent TEXT,
                signed_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_document_signature (document_id, version),
                INDEX idx_signer (signer_id),
                INDEX idx_signature_type (signature_type),
                INDEX idx_signed_date (signed_at),
                UNIQUE KEY unique_document_signer_type_version (document_id, signer_id, signature_type, version),
                FOREIGN KEY (document_id) REFERENCES gamp5_documents(id) ON DELETE CASCADE,
                FOREIGN KEY (signer_id) REFERENCES usuarios(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        'gamp5_document_workflows' => "
            CREATE TABLE IF NOT EXISTS gamp5_document_workflows (
                id INT PRIMARY KEY AUTO_INCREMENT,
                document_id INT NOT NULL,
                workflow_type ENUM('APPROVAL', 'REVIEW', 'CHANGE_CONTROL') NOT NULL DEFAULT 'APPROVAL',
                priority ENUM('LOW', 'MEDIUM', 'HIGH', 'URGENT') NOT NULL DEFAULT 'MEDIUM',
                due_date DATE,
                instructions TEXT,
                status ENUM('ACTIVE', 'COMPLETED', 'CANCELLED', 'ON_HOLD') NOT NULL DEFAULT 'ACTIVE',
                cancelled_reason TEXT,
                created_by INT NOT NULL,
                completed_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_document_workflow (document_id),
                INDEX idx_status (status),
                INDEX idx_workflow_type (workflow_type),
                INDEX idx_due_date (due_date),
                FOREIGN KEY (document_id) REFERENCES gamp5_documents(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        'gamp5_workflow_steps' => "
            CREATE TABLE IF NOT EXISTS gamp5_workflow_steps (
                id INT PRIMARY KEY AUTO_INCREMENT,
                workflow_id INT NOT NULL,
                step_order INT NOT NULL,
                step_type ENUM('REVIEW', 'APPROVAL', 'QA_REVIEW', 'TECHNICAL_REVIEW', 'FINAL_APPROVAL') NOT NULL,
                assignee_id INT,
                role_required VARCHAR(100),
                due_date DATE,
                instructions TEXT,
                status ENUM('PENDING', 'COMPLETED', 'REJECTED', 'SKIPPED') NOT NULL DEFAULT 'PENDING',
                comments TEXT,
                action_taken ENUM('APPROVE', 'REJECT', 'REQUEST_CHANGES') NULL,
                completed_by INT,
                completed_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_workflow_step (workflow_id, step_order),
                INDEX idx_assignee (assignee_id),
                INDEX idx_status (status),
                INDEX idx_due_date (due_date),
                FOREIGN KEY (workflow_id) REFERENCES gamp5_document_workflows(id) ON DELETE CASCADE,
                FOREIGN KEY (assignee_id) REFERENCES usuarios(id) ON DELETE SET NULL,
                FOREIGN KEY (completed_by) REFERENCES usuarios(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        'gamp5_document_audit_log' => "
            CREATE TABLE IF NOT EXISTS gamp5_document_audit_log (
                id INT PRIMARY KEY AUTO_INCREMENT,
                document_id INT NOT NULL,
                event_type ENUM('DOCUMENT_CREATED', 'DOCUMENT_VERSION_CREATED', 'DOCUMENT_SIGNED', 'WORKFLOW_STARTED', 
                               'WORKFLOW_STEP_PROCESSED', 'DOCUMENT_APPROVED', 'DOCUMENT_RETIRED', 'ACCESS_GRANTED', 
                               'ACCESS_DENIED', 'DOCUMENT_DOWNLOADED', 'DOCUMENT_PRINTED') NOT NULL,
                user_id INT,
                event_details JSON,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_document_audit (document_id),
                INDEX idx_event_type (event_type),
                INDEX idx_user_audit (user_id),
                INDEX idx_audit_date (created_at),
                FOREIGN KEY (document_id) REFERENCES gamp5_documents(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        // Tablas del Módulo de Evaluación de Proveedores
        'gamp5_suppliers' => "
            CREATE TABLE IF NOT EXISTS gamp5_suppliers (
                id INT PRIMARY KEY AUTO_INCREMENT,
                supplier_code VARCHAR(50) NOT NULL UNIQUE,
                supplier_name VARCHAR(200) NOT NULL,
                supplier_type ENUM('SOFTWARE_VENDOR', 'HARDWARE_VENDOR', 'SERVICE_PROVIDER', 'CONSULTANT', 
                                  'CLOUD_PROVIDER', 'MAINTENANCE_PROVIDER', 'VALIDATION_SERVICES', 'TRAINING_PROVIDER') NOT NULL,
                description TEXT,
                contact_person VARCHAR(100),
                contact_email VARCHAR(150) NOT NULL,
                contact_phone VARCHAR(20),
                address TEXT,
                website VARCHAR(255),
                registration_number VARCHAR(100),
                country VARCHAR(100),
                gamp_category ENUM('CATEGORY_1', 'CATEGORY_2', 'CATEGORY_3', 'CATEGORY_4', 'CATEGORY_5') NOT NULL DEFAULT 'CATEGORY_3',
                risk_level ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') NOT NULL DEFAULT 'MEDIUM',
                status ENUM('PENDING', 'APPROVED', 'REJECTED', 'SUSPENDED', 'EXPIRED') NOT NULL DEFAULT 'PENDING',
                created_by INT NOT NULL,
                updated_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_supplier_code (supplier_code),
                INDEX idx_supplier_type (supplier_type),
                INDEX idx_gamp_category (gamp_category),
                INDEX idx_risk_level (risk_level),
                INDEX idx_status (status),
                INDEX idx_created_date (created_at),
                FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE RESTRICT,
                FOREIGN KEY (updated_by) REFERENCES usuarios(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        'gamp5_supplier_qualifications' => "
            CREATE TABLE IF NOT EXISTS gamp5_supplier_qualifications (
                id INT PRIMARY KEY AUTO_INCREMENT,
                supplier_id INT NOT NULL,
                qualification_type ENUM('INITIAL_QUALIFICATION', 'PERIODIC_REVIEW', 'FOR_CAUSE', 'RENEWAL', 
                                       'POST_INCIDENT', 'CHANGE_CONTROL') NOT NULL DEFAULT 'INITIAL_QUALIFICATION',
                target_completion_date DATE,
                lead_assessor_id INT,
                business_justification TEXT,
                scope_description TEXT,
                critical_aspects TEXT,
                regulatory_requirements TEXT,
                status ENUM('NOT_STARTED', 'IN_PROGRESS', 'APPROVED', 'CONDITIONALLY_APPROVED', 
                           'REJECTED', 'SUSPENDED', 'RE_EVALUATION', 'EXPIRED') NOT NULL DEFAULT 'NOT_STARTED',
                total_score DECIMAL(5,2) DEFAULT 0.00,
                final_recommendation TEXT,
                completion_comments TEXT,
                created_by INT NOT NULL,
                updated_by INT,
                completed_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                completed_at TIMESTAMP NULL,
                INDEX idx_supplier_qualification (supplier_id),
                INDEX idx_qualification_type (qualification_type),
                INDEX idx_status (status),
                INDEX idx_lead_assessor (lead_assessor_id),
                INDEX idx_completion_date (target_completion_date),
                FOREIGN KEY (supplier_id) REFERENCES gamp5_suppliers(id) ON DELETE CASCADE,
                FOREIGN KEY (lead_assessor_id) REFERENCES usuarios(id) ON DELETE SET NULL,
                FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE RESTRICT,
                FOREIGN KEY (updated_by) REFERENCES usuarios(id) ON DELETE SET NULL,
                FOREIGN KEY (completed_by) REFERENCES usuarios(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        'gamp5_qualification_criteria' => "
            CREATE TABLE IF NOT EXISTS gamp5_qualification_criteria (
                id INT PRIMARY KEY AUTO_INCREMENT,
                qualification_id INT NOT NULL,
                criteria_code VARCHAR(50) NOT NULL,
                criteria_name VARCHAR(200) NOT NULL,
                weight INT NOT NULL DEFAULT 10,
                max_score INT NOT NULL DEFAULT 100,
                score INT NULL,
                evidence TEXT,
                comments TEXT,
                evaluator_id INT,
                evaluated_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_qualification_criteria (qualification_id),
                INDEX idx_criteria_code (criteria_code),
                INDEX idx_evaluator (evaluator_id),
                FOREIGN KEY (qualification_id) REFERENCES gamp5_supplier_qualifications(id) ON DELETE CASCADE,
                FOREIGN KEY (evaluator_id) REFERENCES usuarios(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        'gamp5_approved_suppliers' => "
            CREATE TABLE IF NOT EXISTS gamp5_approved_suppliers (
                id INT PRIMARY KEY AUTO_INCREMENT,
                supplier_id INT NOT NULL,
                qualification_id INT NOT NULL,
                approval_date DATE NOT NULL,
                valid_until DATE,
                conditions TEXT,
                approved_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_supplier_approval (supplier_id),
                INDEX idx_qualification (qualification_id),
                INDEX idx_approval_date (approval_date),
                INDEX idx_validity (valid_until),
                FOREIGN KEY (supplier_id) REFERENCES gamp5_suppliers(id) ON DELETE CASCADE,
                FOREIGN KEY (qualification_id) REFERENCES gamp5_supplier_qualifications(id) ON DELETE CASCADE,
                FOREIGN KEY (approved_by) REFERENCES usuarios(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        'gamp5_supplier_audits' => "
            CREATE TABLE IF NOT EXISTS gamp5_supplier_audits (
                id INT PRIMARY KEY AUTO_INCREMENT,
                supplier_id INT NOT NULL,
                audit_type ENUM('INITIAL_QUALIFICATION', 'PERIODIC_REVIEW', 'FOR_CAUSE', 'RENEWAL', 
                               'POST_INCIDENT', 'CHANGE_CONTROL') NOT NULL,
                audit_scope TEXT,
                planned_date DATE,
                actual_date DATE,
                lead_auditor_id INT,
                audit_team JSON,
                objectives TEXT,
                success_criteria TEXT,
                findings_count INT DEFAULT 0,
                status ENUM('PLANNED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED') NOT NULL DEFAULT 'PLANNED',
                audit_report_path VARCHAR(500),
                created_by INT NOT NULL,
                updated_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_supplier_audit (supplier_id),
                INDEX idx_audit_type (audit_type),
                INDEX idx_planned_date (planned_date),
                INDEX idx_lead_auditor (lead_auditor_id),
                INDEX idx_status (status),
                FOREIGN KEY (supplier_id) REFERENCES gamp5_suppliers(id) ON DELETE CASCADE,
                FOREIGN KEY (lead_auditor_id) REFERENCES usuarios(id) ON DELETE SET NULL,
                FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE RESTRICT,
                FOREIGN KEY (updated_by) REFERENCES usuarios(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        'gamp5_audit_findings' => "
            CREATE TABLE IF NOT EXISTS gamp5_audit_findings (
                id INT PRIMARY KEY AUTO_INCREMENT,
                audit_id INT NOT NULL,
                finding_type ENUM('OBSERVATION', 'MINOR_NON_CONFORMITY', 'MAJOR_NON_CONFORMITY', 
                                 'CRITICAL_NON_CONFORMITY', 'IMPROVEMENT_OPPORTUNITY') NOT NULL,
                severity ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') NOT NULL,
                area VARCHAR(100),
                description TEXT NOT NULL,
                regulatory_reference TEXT,
                evidence TEXT,
                corrective_action_required BOOLEAN DEFAULT FALSE,
                target_closure_date DATE,
                status ENUM('OPEN', 'IN_PROGRESS', 'CLOSED', 'VERIFIED') NOT NULL DEFAULT 'OPEN',
                closure_evidence TEXT,
                closed_by INT,
                closed_at TIMESTAMP NULL,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_audit_finding (audit_id),
                INDEX idx_finding_type (finding_type),
                INDEX idx_severity (severity),
                INDEX idx_status (status),
                INDEX idx_closure_date (target_closure_date),
                FOREIGN KEY (audit_id) REFERENCES gamp5_supplier_audits(id) ON DELETE CASCADE,
                FOREIGN KEY (closed_by) REFERENCES usuarios(id) ON DELETE SET NULL,
                FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        'gamp5_supplier_audit_log' => "
            CREATE TABLE IF NOT EXISTS gamp5_supplier_audit_log (
                id INT PRIMARY KEY AUTO_INCREMENT,
                reference_id INT NOT NULL,
                event_type ENUM('SUPPLIER_REGISTERED', 'QUALIFICATION_STARTED', 'CRITERIA_EVALUATED', 
                               'QUALIFICATION_COMPLETED', 'AUDIT_SCHEDULED', 'AUDIT_COMPLETED', 
                               'FINDINGS_RECORDED', 'SUPPLIER_APPROVED', 'SUPPLIER_SUSPENDED', 
                               'STATUS_CHANGED') NOT NULL,
                user_id INT,
                event_details JSON,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_reference_audit (reference_id),
                INDEX idx_event_type (event_type),
                INDEX idx_user_audit (user_id),
                INDEX idx_audit_date (created_at),
                FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        "
    ];
    
    foreach ($tables as $tableName => $sql) {
        echo "  📋 Creando tabla: $tableName...";
        $db->query($sql);
        echo " ✅\n";
    }
    
    echo "✅ Todas las tablas creadas exitosamente!\n\n";
}

/**
 * Inserta datos iniciales en el sistema
 */
function insertInitialData($db) {
    echo "📝 Insertando datos iniciales...\n";
    
    // Insertar métricas de monitoreo predeterminadas
    $metrics = [
        ['System Availability', 'Disponibilidad general del sistema', 'AVAILABILITY', '%', 99.95, 99.00, 98.00, 'REAL_TIME'],
        ['Response Time', 'Tiempo de respuesta promedio', 'PERFORMANCE', 'ms', 500, 1000, 2000, 'REAL_TIME'],
        ['Database Connections', 'Conexiones activas a la base de datos', 'PERFORMANCE', 'count', 100, 150, 200, 'HOURLY'],
        ['Security Events', 'Eventos de seguridad detectados', 'SECURITY', 'count', 0, 5, 10, 'HOURLY'],
        ['GxP Compliance Score', 'Puntuación de cumplimiento GxP', 'COMPLIANCE', '%', 98.0, 95.0, 90.0, 'DAILY'],
        ['Data Integrity Check', 'Verificación de integridad de datos', 'QUALITY', '%', 100.0, 99.5, 99.0, 'DAILY'],
        ['Audit Trail Completeness', 'Completitud del registro de auditoría', 'COMPLIANCE', '%', 100.0, 99.0, 95.0, 'DAILY'],
        ['System Errors', 'Errores del sistema por hora', 'PERFORMANCE', 'count', 0, 5, 20, 'HOURLY']
    ];
    
    $stmt = $db->prepare("
        INSERT INTO gamp5_monitoring_metrics 
        (metric_name, description, metric_type, measurement_unit, target_value, warning_threshold, critical_threshold, monitoring_frequency, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");
    
    foreach ($metrics as $metric) {
        echo "  📊 Insertando métrica: {$metric[0]}...";
        $stmt->execute($metric);
        echo " ✅\n";
    }
    
    // Insertar sistema de ejemplo
    echo "  🖥️  Insertando sistema de ejemplo...";
    $db->query("
        INSERT INTO gamp5_system_lifecycles 
        (system_name, description, system_type, gamp_category, risk_classification, current_stage, created_by) 
        VALUES 
        ('Sistema de Calibración', 'Sistema para gestión de calibraciones de equipos', 'SOFTWARE', '4', 'HIGH', 'OPERATE', 1)
    ");
    echo " ✅\n";
    
    echo "✅ Datos iniciales insertados exitosamente!\n\n";
}

/**
 * Crea índices adicionales para optimización
 */
function createIndexes($db) {
    echo "🚀 Creando índices de optimización...\n";
    
    $indexes = [
        "CREATE INDEX idx_gamp5_lifecycle_dates ON gamp5_verification_schedules(next_verification_date, status)",
        "CREATE INDEX idx_gamp5_change_dates ON gamp5_change_requests(created_at, status)",
        "CREATE INDEX idx_gamp5_monitoring_recent ON gamp5_monitoring_data(recorded_at DESC) USING BTREE",
        "CREATE INDEX idx_gamp5_alerts_active ON gamp5_system_alerts(status, severity, triggered_at)",
        "CREATE INDEX idx_gamp5_qualification_progress ON gamp5_qualification_steps(qualification_id, status)"
    ];
    
    foreach ($indexes as $index) {
        try {
            echo "  🔍 Creando índice...";
            $db->query($index);
            echo " ✅\n";
        } catch (Exception $e) {
            // Ignorar si el índice ya existe
            echo " ⚠️  (ya existe)\n";
        }
    }
    
    echo "✅ Índices de optimización configurados!\n\n";
}

/**
 * Verifica que la instalación sea correcta
 */
function verifyInstallation($db) {
    echo "🔍 Verificando instalación...\n";
    
    // Verificar tablas
    $result = $db->query("SHOW TABLES LIKE 'gamp5_%'");
    $tableCount = $result->num_rows;
    echo "  📊 Tablas GAMP 5 encontradas: $tableCount";
    if ($tableCount >= 26) {
        echo " ✅\n";
    } else {
        echo " ❌ (esperadas: 26+)\n";
        throw new Exception("No se crearon todas las tablas requeridas");
    }
    
    // Verificar métricas
    $result = $db->query("SELECT COUNT(*) as count FROM gamp5_monitoring_metrics");
    $metricCount = $result->fetch_assoc()['count'];
    echo "  📈 Métricas de monitoreo: $metricCount";
    if ($metricCount > 0) {
        echo " ✅\n";
    } else {
        echo " ❌\n";
        throw new Exception("No se insertaron las métricas de monitoreo");
    }
    
    // Verificar sistema de ejemplo
    $result = $db->query("SELECT COUNT(*) as count FROM gamp5_system_lifecycles");
    $systemCount = $result->fetch_assoc()['count'];
    echo "  🖥️  Sistemas de ejemplo: $systemCount";
    if ($systemCount > 0) {
        echo " ✅\n";
    } else {
        echo " ❌\n";
        throw new Exception("No se insertó el sistema de ejemplo");
    }
    
    echo "✅ Verificación completada exitosamente!\n\n";
}

?>