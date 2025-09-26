<?php
/**
 * Script de Inicialización del Sistema GAMP 5
 * 
 * Este script configura todas las tablas y datos iniciales necesarios
 * para el funcionamiento del sistema de administración GAMP 5.
 * 
 * Ejecutar una sola vez durante la instalación inicial.
 */

require_once dirname(__DIR__) . '/app/Core/db_config.php';

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
    echo "   - Tablas creadas: 15\n";
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
        "
    ];
    
    foreach ($tables as $tableName => $sql) {
        echo "  📋 Creando tabla: $tableName...";
        $db->exec($sql);
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
    $db->exec("
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
            $db->exec($index);
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
    $tableCount = $result->rowCount();
    echo "  📊 Tablas GAMP 5 encontradas: $tableCount";
    if ($tableCount >= 14) {
        echo " ✅\n";
    } else {
        echo " ❌ (esperadas: 14+)\n";
        throw new Exception("No se crearon todas las tablas requeridas");
    }
    
    // Verificar métricas
    $result = $db->query("SELECT COUNT(*) as count FROM gamp5_monitoring_metrics");
    $metricCount = $result->fetch()['count'];
    echo "  📈 Métricas de monitoreo: $metricCount";
    if ($metricCount > 0) {
        echo " ✅\n";
    } else {
        echo " ❌\n";
        throw new Exception("No se insertaron las métricas de monitoreo");
    }
    
    // Verificar sistema de ejemplo
    $result = $db->query("SELECT COUNT(*) as count FROM gamp5_system_lifecycles");
    $systemCount = $result->fetch()['count'];
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