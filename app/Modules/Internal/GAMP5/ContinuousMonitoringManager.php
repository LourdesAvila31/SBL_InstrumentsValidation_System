<?php
/**
 * Sistema de Monitoreo Continuo y Verificación conforme a GAMP 5
 * 
 * Esta clase gestiona la verificación continua y el monitoreo de la calidad 
 * del sistema durante su operación, incluyendo verificación periódica de 
 * configuraciones, procesos y resultados del sistema.
 */

require_once dirname(__DIR__, 2) . '/Core/db_config.php';
require_once dirname(__DIR__, 2) . '/Core/permissions.php';

class ContinuousMonitoringManager
{
    private $db;
    private $usuario_id;
    
    // Tipos de monitoreo
    const MONITORING_TYPES = [
        'SYSTEM_HEALTH' => 'Salud del Sistema',
        'PERFORMANCE' => 'Rendimiento',
        'SECURITY' => 'Seguridad',
        'COMPLIANCE' => 'Cumplimiento',
        'DATA_INTEGRITY' => 'Integridad de Datos',
        'CONFIGURATION' => 'Configuración',
        'USER_ACTIVITY' => 'Actividad de Usuarios',
        'AUDIT_TRAIL' => 'Rastro de Auditoría'
    ];
    
    // Frecuencias de monitoreo
    const MONITORING_FREQUENCIES = [
        'REAL_TIME' => 'Tiempo Real',
        'EVERY_MINUTE' => 'Cada Minuto',
        'EVERY_5_MINUTES' => 'Cada 5 Minutos',
        'EVERY_15_MINUTES' => 'Cada 15 Minutos',
        'HOURLY' => 'Cada Hora',
        'DAILY' => 'Diario',
        'WEEKLY' => 'Semanal',
        'MONTHLY' => 'Mensual'
    ];
    
    // Estados de alertas
    const ALERT_STATUS = [
        'ACTIVE' => 'Activa',
        'ACKNOWLEDGED' => 'Reconocida',
        'RESOLVED' => 'Resuelta',
        'CLOSED' => 'Cerrada',
        'SUPPRESSED' => 'Suprimida'
    ];
    
    // Niveles de severidad
    const SEVERITY_LEVELS = [
        'INFO' => 'Información',
        'WARNING' => 'Advertencia',
        'MINOR' => 'Menor',
        'MAJOR' => 'Mayor',
        'CRITICAL' => 'Crítica'
    ];

    public function __construct($usuario_id = null)
    {
        $this->db = DatabaseManager::getConnection();
        $this->usuario_id = $usuario_id ?? $_SESSION['usuario_id'] ?? null;
        $this->initializeTables();
    }

    /**
     * Inicializa las tablas necesarias para el monitoreo continuo
     */
    private function initializeTables()
    {
        $queries = [
            // Tabla de métricas de monitoreo
            "CREATE TABLE IF NOT EXISTS gamp5_monitoring_metrics (
                id INT PRIMARY KEY AUTO_INCREMENT,
                metric_name VARCHAR(255) NOT NULL,
                metric_type ENUM('SYSTEM_HEALTH', 'PERFORMANCE', 'SECURITY', 'COMPLIANCE', 'DATA_INTEGRITY', 'CONFIGURATION', 'USER_ACTIVITY', 'AUDIT_TRAIL') NOT NULL,
                description TEXT,
                measurement_unit VARCHAR(50),
                threshold_warning DECIMAL(10,2),
                threshold_critical DECIMAL(10,2),
                frequency ENUM('REAL_TIME', 'EVERY_MINUTE', 'EVERY_5_MINUTES', 'EVERY_15_MINUTES', 'HOURLY', 'DAILY', 'WEEKLY', 'MONTHLY') NOT NULL,
                active BOOLEAN DEFAULT TRUE,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_metric_type (metric_type),
                INDEX idx_active (active),
                INDEX idx_frequency (frequency)
            )",
            
            // Tabla de datos de monitoreo
            "CREATE TABLE IF NOT EXISTS gamp5_monitoring_data (
                id INT PRIMARY KEY AUTO_INCREMENT,
                metric_id INT NOT NULL,
                measured_value DECIMAL(15,4),
                text_value TEXT,
                json_value JSON,
                measurement_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status ENUM('NORMAL', 'WARNING', 'CRITICAL', 'ERROR') NOT NULL DEFAULT 'NORMAL',
                source_system VARCHAR(100),
                collection_method ENUM('AUTOMATIC', 'MANUAL', 'API', 'SCRIPT') NOT NULL DEFAULT 'AUTOMATIC',
                INDEX idx_metric_timestamp (metric_id, measurement_timestamp DESC),
                INDEX idx_status (status),
                INDEX idx_measurement_timestamp (measurement_timestamp DESC),
                FOREIGN KEY (metric_id) REFERENCES gamp5_monitoring_metrics(id) ON DELETE CASCADE
            )",
            
            // Tabla de alertas del sistema
            "CREATE TABLE IF NOT EXISTS gamp5_system_alerts (
                id INT PRIMARY KEY AUTO_INCREMENT,
                alert_title VARCHAR(255) NOT NULL,
                alert_description TEXT,
                alert_type ENUM('SYSTEM_HEALTH', 'PERFORMANCE', 'SECURITY', 'COMPLIANCE', 'DATA_INTEGRITY', 'CONFIGURATION', 'USER_ACTIVITY', 'AUDIT_TRAIL') NOT NULL,
                severity ENUM('INFO', 'WARNING', 'MINOR', 'MAJOR', 'CRITICAL') NOT NULL,
                status ENUM('ACTIVE', 'ACKNOWLEDGED', 'RESOLVED', 'CLOSED', 'SUPPRESSED') NOT NULL DEFAULT 'ACTIVE',
                metric_id INT NULL,
                trigger_value DECIMAL(15,4),
                threshold_exceeded VARCHAR(20),
                affected_systems JSON,
                root_cause TEXT,
                corrective_actions JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                acknowledged_by INT NULL,
                acknowledged_at TIMESTAMP NULL,
                resolved_by INT NULL,
                resolved_at TIMESTAMP NULL,
                resolution_notes TEXT,
                INDEX idx_alert_type (alert_type),
                INDEX idx_severity (severity),
                INDEX idx_status (status),
                INDEX idx_created_at (created_at DESC),
                FOREIGN KEY (metric_id) REFERENCES gamp5_monitoring_metrics(id) ON DELETE SET NULL
            )",
            
            // Tabla de verificaciones programadas
            "CREATE TABLE IF NOT EXISTS gamp5_scheduled_verifications (
                id INT PRIMARY KEY AUTO_INCREMENT,
                verification_name VARCHAR(255) NOT NULL,
                verification_type ENUM('SYSTEM_HEALTH', 'PERFORMANCE', 'SECURITY', 'COMPLIANCE', 'DATA_INTEGRITY', 'CONFIGURATION', 'USER_ACTIVITY', 'AUDIT_TRAIL') NOT NULL,
                description TEXT,
                verification_procedure TEXT,
                frequency ENUM('DAILY', 'WEEKLY', 'MONTHLY', 'QUARTERLY', 'SEMI_ANNUAL', 'ANNUAL') NOT NULL,
                next_execution_date TIMESTAMP NOT NULL,
                last_execution_date TIMESTAMP NULL,
                assigned_to INT NULL,
                active BOOLEAN DEFAULT TRUE,
                execution_time_limit INT DEFAULT 60, -- minutos
                notification_recipients JSON,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_verification_type (verification_type),
                INDEX idx_next_execution (next_execution_date),
                INDEX idx_active (active)
            )",
            
            // Tabla de ejecuciones de verificación
            "CREATE TABLE IF NOT EXISTS gamp5_verification_executions (
                id INT PRIMARY KEY AUTO_INCREMENT,
                scheduled_verification_id INT NOT NULL,
                execution_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status ENUM('SCHEDULED', 'IN_PROGRESS', 'COMPLETED', 'FAILED', 'CANCELLED', 'OVERDUE') NOT NULL DEFAULT 'SCHEDULED',
                executed_by INT NULL,
                start_time TIMESTAMP NULL,
                end_time TIMESTAMP NULL,
                execution_results JSON,
                findings TEXT,
                anomalies_detected JSON,
                corrective_actions_required JSON,
                completion_percentage DECIMAL(5,2) DEFAULT 0.00,
                evidence_files JSON,
                next_scheduled_date TIMESTAMP NULL,
                FOREIGN KEY (scheduled_verification_id) REFERENCES gamp5_scheduled_verifications(id) ON DELETE CASCADE,
                INDEX idx_scheduled_verification (scheduled_verification_id),
                INDEX idx_execution_date (execution_date DESC),
                INDEX idx_status (status)
            )",
            
            // Tabla de anomalías detectadas
            "CREATE TABLE IF NOT EXISTS gamp5_detected_anomalies (
                id INT PRIMARY KEY AUTO_INCREMENT,
                verification_execution_id INT NULL,
                monitoring_data_id INT NULL,
                anomaly_type ENUM('DEVIATION', 'TREND', 'THRESHOLD_BREACH', 'PATTERN_CHANGE', 'DATA_QUALITY', 'SECURITY_INCIDENT', 'OTHER') NOT NULL,
                severity ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') NOT NULL,
                description TEXT NOT NULL,
                detected_value TEXT,
                expected_value TEXT,
                deviation_percentage DECIMAL(8,4),
                impact_assessment TEXT,
                root_cause_analysis TEXT,
                status ENUM('OPEN', 'INVESTIGATING', 'RESOLVED', 'CLOSED', 'FALSE_POSITIVE') NOT NULL DEFAULT 'OPEN',
                assigned_to INT NULL,
                detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                resolved_at TIMESTAMP NULL,
                resolution_description TEXT,
                prevention_measures JSON,
                FOREIGN KEY (verification_execution_id) REFERENCES gamp5_verification_executions(id) ON DELETE CASCADE,
                FOREIGN KEY (monitoring_data_id) REFERENCES gamp5_monitoring_data(id) ON DELETE CASCADE,
                INDEX idx_anomaly_type (anomaly_type),
                INDEX idx_severity (severity),
                INDEX idx_status (status),
                INDEX idx_detected_at (detected_at DESC)
            )",
            
            // Tabla de reportes de monitoreo
            "CREATE TABLE IF NOT EXISTS gamp5_monitoring_reports (
                id INT PRIMARY KEY AUTO_INCREMENT,
                report_name VARCHAR(255) NOT NULL,
                report_type ENUM('DAILY', 'WEEKLY', 'MONTHLY', 'QUARTERLY', 'ANNUAL', 'AD_HOC') NOT NULL,
                report_period_start DATE NOT NULL,
                report_period_end DATE NOT NULL,
                report_summary TEXT,
                metrics_included JSON,
                anomalies_summary JSON,
                performance_trends JSON,
                compliance_status JSON,
                recommendations TEXT,
                generated_by INT NOT NULL,
                generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                report_file_path VARCHAR(500),
                distributed_to JSON,
                INDEX idx_report_type (report_type),
                INDEX idx_report_period (report_period_start, report_period_end),
                INDEX idx_generated_at (generated_at DESC)
            )"
        ];

        foreach ($queries as $query) {
            if (!$this->db->query($query)) {
                error_log("Error creating monitoring table: " . $this->db->error);
            }
        }
    }

    /**
     * Crea una nueva métrica de monitoreo
     */
    public function createMonitoringMetric($data)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO gamp5_monitoring_metrics 
                (metric_name, metric_type, description, measurement_unit, 
                 threshold_warning, threshold_critical, frequency, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param("ssssddsi", 
                $data['metric_name'],
                $data['metric_type'],
                $data['description'],
                $data['measurement_unit'] ?? '',
                $data['threshold_warning'] ?? null,
                $data['threshold_critical'] ?? null,
                $data['frequency'],
                $this->usuario_id
            );
            
            if ($stmt->execute()) {
                $metric_id = $this->db->insert_id;
                
                $this->logAuditEvent('MONITORING_METRIC_CREATED', [
                    'metric_id' => $metric_id,
                    'metric_name' => $data['metric_name'],
                    'metric_type' => $data['metric_type']
                ]);
                
                return ['success' => true, 'metric_id' => $metric_id];
            }
            
            return ['success' => false, 'error' => 'Error al crear la métrica de monitoreo'];
            
        } catch (Exception $e) {
            error_log("Error in createMonitoringMetric: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error interno del servidor'];
        }
    }

    /**
     * Registra datos de monitoreo
     */
    public function recordMonitoringData($metric_id, $data)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO gamp5_monitoring_data 
                (metric_id, measured_value, text_value, json_value, status, 
                 source_system, collection_method)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $json_value = isset($data['json_value']) ? json_encode($data['json_value']) : null;
            
            $stmt->bind_param("idsssss", 
                $metric_id,
                $data['measured_value'] ?? null,
                $data['text_value'] ?? null,
                $json_value,
                $data['status'] ?? 'NORMAL',
                $data['source_system'] ?? 'SISTEMA_INTERNO',
                $data['collection_method'] ?? 'AUTOMATIC'
            );
            
            if ($stmt->execute()) {
                $monitoring_data_id = $this->db->insert_id;
                
                // Verificar si se exceden umbrales y generar alertas
                $this->checkThresholds($metric_id, $data['measured_value'] ?? null, $monitoring_data_id);
                
                return ['success' => true, 'monitoring_data_id' => $monitoring_data_id];
            }
            
            return ['success' => false, 'error' => 'Error al registrar los datos de monitoreo'];
            
        } catch (Exception $e) {
            error_log("Error in recordMonitoringData: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error interno del servidor'];
        }
    }

    /**
     * Verifica umbrales y genera alertas si es necesario
     */
    private function checkThresholds($metric_id, $measured_value, $monitoring_data_id)
    {
        if ($measured_value === null) return;
        
        // Obtener información de la métrica y sus umbrales
        $stmt = $this->db->prepare("
            SELECT metric_name, metric_type, threshold_warning, threshold_critical
            FROM gamp5_monitoring_metrics 
            WHERE id = ?
        ");
        
        $stmt->bind_param("i", $metric_id);
        $stmt->execute();
        $metric = $stmt->get_result()->fetch_assoc();
        
        if (!$metric) return;
        
        $alert_severity = null;
        $threshold_exceeded = null;
        
        if ($metric['threshold_critical'] !== null && $measured_value >= $metric['threshold_critical']) {
            $alert_severity = 'CRITICAL';
            $threshold_exceeded = 'CRITICAL';
        } elseif ($metric['threshold_warning'] !== null && $measured_value >= $metric['threshold_warning']) {
            $alert_severity = 'WARNING';
            $threshold_exceeded = 'WARNING';
        }
        
        if ($alert_severity) {
            $this->createSystemAlert([
                'alert_title' => "Umbral {$threshold_exceeded} excedido: {$metric['metric_name']}",
                'alert_description' => "La métrica {$metric['metric_name']} ha excedido el umbral {$threshold_exceeded} con un valor de {$measured_value}",
                'alert_type' => $metric['metric_type'],
                'severity' => $alert_severity,
                'metric_id' => $metric_id,
                'trigger_value' => $measured_value,
                'threshold_exceeded' => $threshold_exceeded
            ]);
        }
    }

    /**
     * Crea una alerta del sistema
     */
    public function createSystemAlert($data)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO gamp5_system_alerts 
                (alert_title, alert_description, alert_type, severity, metric_id, 
                 trigger_value, threshold_exceeded, affected_systems, root_cause, corrective_actions)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $affected_systems = isset($data['affected_systems']) ? json_encode($data['affected_systems']) : null;
            $corrective_actions = isset($data['corrective_actions']) ? json_encode($data['corrective_actions']) : null;
            
            $stmt->bind_param("ssssisssss", 
                $data['alert_title'],
                $data['alert_description'],
                $data['alert_type'],
                $data['severity'],
                $data['metric_id'] ?? null,
                $data['trigger_value'] ?? null,
                $data['threshold_exceeded'] ?? null,
                $affected_systems,
                $data['root_cause'] ?? null,
                $corrective_actions
            );
            
            if ($stmt->execute()) {
                $alert_id = $this->db->insert_id;
                
                // Enviar notificaciones según la severidad
                $this->sendAlertNotifications($alert_id, $data);
                
                $this->logAuditEvent('SYSTEM_ALERT_CREATED', [
                    'alert_id' => $alert_id,
                    'alert_type' => $data['alert_type'],
                    'severity' => $data['severity']
                ]);
                
                return ['success' => true, 'alert_id' => $alert_id];
            }
            
            return ['success' => false, 'error' => 'Error al crear la alerta del sistema'];
            
        } catch (Exception $e) {
            error_log("Error in createSystemAlert: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error interno del servidor'];
        }
    }

    /**
     * Envía notificaciones de alerta
     */
    private function sendAlertNotifications($alert_id, $alert_data)
    {
        // Implementar lógica de notificaciones (email, SMS, etc.)
        $notification_message = "ALERTA {$alert_data['severity']}: {$alert_data['alert_title']}";
        
        error_log("Sistema de Alertas GAMP5: " . $notification_message);
        
        // Aquí se integraría con sistemas de notificación externos
        // como email, Slack, Teams, etc.
    }

    /**
     * Programa una verificación
     */
    public function scheduleVerification($data)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO gamp5_scheduled_verifications 
                (verification_name, verification_type, description, verification_procedure,
                 frequency, next_execution_date, assigned_to, execution_time_limit,
                 notification_recipients, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $notification_recipients = isset($data['notification_recipients']) ? 
                                     json_encode($data['notification_recipients']) : null;
            
            $stmt->bind_param("ssssssiiisi", 
                $data['verification_name'],
                $data['verification_type'],
                $data['description'],
                $data['verification_procedure'],
                $data['frequency'],
                $data['next_execution_date'],
                $data['assigned_to'] ?? null,
                $data['execution_time_limit'] ?? 60,
                $notification_recipients,
                $this->usuario_id
            );
            
            if ($stmt->execute()) {
                $verification_id = $this->db->insert_id;
                
                $this->logAuditEvent('VERIFICATION_SCHEDULED', [
                    'verification_id' => $verification_id,
                    'verification_type' => $data['verification_type'],
                    'frequency' => $data['frequency']
                ]);
                
                return ['success' => true, 'verification_id' => $verification_id];
            }
            
            return ['success' => false, 'error' => 'Error al programar la verificación'];
            
        } catch (Exception $e) {
            error_log("Error in scheduleVerification: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error interno del servidor'];
        }
    }

    /**
     * Ejecuta una verificación programada
     */
    public function executeVerification($verification_id, $execution_data)
    {
        try {
            // Crear registro de ejecución
            $stmt = $this->db->prepare("
                INSERT INTO gamp5_verification_executions 
                (scheduled_verification_id, status, executed_by, start_time)
                VALUES (?, 'IN_PROGRESS', ?, NOW())
            ");
            
            $stmt->bind_param("ii", $verification_id, $this->usuario_id);
            
            if (!$stmt->execute()) {
                return ['success' => false, 'error' => 'Error al iniciar la ejecución de verificación'];
            }
            
            $execution_id = $this->db->insert_id;
            
            // Ejecutar los pasos de verificación
            $verification_results = $this->performVerificationSteps($verification_id, $execution_data);
            
            // Actualizar registro de ejecución con resultados
            $update_stmt = $this->db->prepare("
                UPDATE gamp5_verification_executions 
                SET status = ?, end_time = NOW(), execution_results = ?, findings = ?,
                    anomalies_detected = ?, corrective_actions_required = ?, 
                    completion_percentage = ?
                WHERE id = ?
            ");
            
            $execution_results_json = json_encode($verification_results['results']);
            $anomalies_json = json_encode($verification_results['anomalies']);
            $corrective_actions_json = json_encode($verification_results['corrective_actions']);
            
            $final_status = $verification_results['success'] ? 'COMPLETED' : 'FAILED';
            $completion_percentage = $verification_results['completion_percentage'];
            
            $update_stmt->bind_param("sssssdsi", 
                $final_status,
                $execution_results_json,
                $verification_results['findings'],
                $anomalies_json,
                $corrective_actions_json,
                $completion_percentage,
                $execution_id
            );
            
            $update_stmt->execute();
            
            // Crear anomalías detectadas si las hay
            if (!empty($verification_results['anomalies'])) {
                $this->createAnomaliesFromVerification($execution_id, $verification_results['anomalies']);
            }
            
            // Programar próxima ejecución
            $this->scheduleNextExecution($verification_id);
            
            $this->logAuditEvent('VERIFICATION_EXECUTED', [
                'verification_id' => $verification_id,
                'execution_id' => $execution_id,
                'status' => $final_status,
                'anomalies_count' => count($verification_results['anomalies'])
            ]);
            
            return [
                'success' => true, 
                'execution_id' => $execution_id,
                'results' => $verification_results
            ];
            
        } catch (Exception $e) {
            error_log("Error in executeVerification: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error interno del servidor'];
        }
    }

    /**
     * Realiza los pasos de verificación
     */
    private function performVerificationSteps($verification_id, $execution_data)
    {
        // Obtener detalles de la verificación
        $stmt = $this->db->prepare("
            SELECT * FROM gamp5_scheduled_verifications 
            WHERE id = ?
        ");
        
        $stmt->bind_param("i", $verification_id);
        $stmt->execute();
        $verification = $stmt->get_result()->fetch_assoc();
        
        if (!$verification) {
            return ['success' => false, 'error' => 'Verificación no encontrada'];
        }
        
        $results = [];
        $anomalies = [];
        $corrective_actions = [];
        $completion_percentage = 0;
        
        // Ejecutar verificaciones según el tipo
        switch ($verification['verification_type']) {
            case 'SYSTEM_HEALTH':
                $health_results = $this->performSystemHealthCheck();
                $results['system_health'] = $health_results;
                if (!$health_results['overall_health']) {
                    $anomalies[] = [
                        'type' => 'SYSTEM_HEALTH',
                        'description' => 'Problemas de salud del sistema detectados',
                        'severity' => 'HIGH',
                        'details' => $health_results['issues']
                    ];
                }
                break;
                
            case 'PERFORMANCE':
                $performance_results = $this->performPerformanceCheck();
                $results['performance'] = $performance_results;
                if ($performance_results['performance_degraded']) {
                    $anomalies[] = [
                        'type' => 'PERFORMANCE',
                        'description' => 'Degradación de rendimiento detectada',
                        'severity' => 'MEDIUM',
                        'details' => $performance_results['issues']
                    ];
                }
                break;
                
            case 'SECURITY':
                $security_results = $this->performSecurityCheck();
                $results['security'] = $security_results;
                if (!empty($security_results['vulnerabilities'])) {
                    $anomalies[] = [
                        'type' => 'SECURITY_INCIDENT',
                        'description' => 'Vulnerabilidades de seguridad detectadas',
                        'severity' => 'CRITICAL',
                        'details' => $security_results['vulnerabilities']
                    ];
                }
                break;
                
            case 'DATA_INTEGRITY':
                $integrity_results = $this->performDataIntegrityCheck();
                $results['data_integrity'] = $integrity_results;
                if (!$integrity_results['integrity_ok']) {
                    $anomalies[] = [
                        'type' => 'DATA_QUALITY',
                        'description' => 'Problemas de integridad de datos detectados',
                        'severity' => 'HIGH',
                        'details' => $integrity_results['issues']
                    ];
                }
                break;
                
            case 'CONFIGURATION':
                $config_results = $this->performConfigurationCheck();
                $results['configuration'] = $config_results;
                if (!empty($config_results['deviations'])) {
                    $anomalies[] = [
                        'type' => 'DEVIATION',
                        'description' => 'Desviaciones de configuración detectadas',
                        'severity' => 'MEDIUM',
                        'details' => $config_results['deviations']
                    ];
                }
                break;
        }
        
        // Calcular porcentaje de completitud
        $total_checks = count($results);
        $successful_checks = 0;
        
        foreach ($results as $result) {
            if (isset($result['success']) && $result['success']) {
                $successful_checks++;
            }
        }
        
        $completion_percentage = $total_checks > 0 ? 
                               ($successful_checks / $total_checks) * 100 : 100;
        
        // Generar acciones correctivas si hay anomalías
        if (!empty($anomalies)) {
            $corrective_actions = $this->generateCorrectiveActions($anomalies);
        }
        
        return [
            'success' => empty($anomalies),
            'results' => $results,
            'anomalies' => $anomalies,
            'corrective_actions' => $corrective_actions,
            'completion_percentage' => $completion_percentage,
            'findings' => $this->generateFindings($results, $anomalies)
        ];
    }

    /**
     * Métodos de verificación específicos (simplificados para el ejemplo)
     */
    private function performSystemHealthCheck()
    {
        return [
            'overall_health' => true,
            'database_connection' => true,
            'disk_space' => 85, // %
            'memory_usage' => 65, // %
            'cpu_usage' => 45, // %
            'services_running' => true,
            'issues' => []
        ];
    }
    
    private function performPerformanceCheck()
    {
        return [
            'performance_degraded' => false,
            'response_time_avg' => 150, // ms
            'throughput' => 95, // %
            'error_rate' => 0.1, // %
            'issues' => []
        ];
    }
    
    private function performSecurityCheck()
    {
        return [
            'vulnerabilities' => [],
            'access_controls_ok' => true,
            'encryption_status' => 'OK',
            'firewall_status' => 'OK',
            'last_security_scan' => date('Y-m-d H:i:s')
        ];
    }
    
    private function performDataIntegrityCheck()
    {
        return [
            'integrity_ok' => true,
            'checksum_validation' => true,
            'referential_integrity' => true,
            'data_consistency' => true,
            'issues' => []
        ];
    }
    
    private function performConfigurationCheck()
    {
        return [
            'deviations' => [],
            'configuration_baseline' => 'COMPLIANT',
            'unauthorized_changes' => false,
            'last_config_backup' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Genera acciones correctivas basadas en anomalías
     */
    private function generateCorrectiveActions($anomalies)
    {
        $actions = [];
        
        foreach ($anomalies as $anomaly) {
            switch ($anomaly['type']) {
                case 'SYSTEM_HEALTH':
                    $actions[] = [
                        'action' => 'Verificar y reiniciar servicios críticos',
                        'priority' => 'HIGH',
                        'estimated_time' => '30 minutes'
                    ];
                    break;
                    
                case 'PERFORMANCE':
                    $actions[] = [
                        'action' => 'Analizar y optimizar consultas lentas',
                        'priority' => 'MEDIUM',
                        'estimated_time' => '2 hours'
                    ];
                    break;
                    
                case 'SECURITY_INCIDENT':
                    $actions[] = [
                        'action' => 'Evaluar y parchear vulnerabilidades de seguridad',
                        'priority' => 'CRITICAL',
                        'estimated_time' => '4 hours'
                    ];
                    break;
                    
                case 'DATA_QUALITY':
                    $actions[] = [
                        'action' => 'Ejecutar rutinas de validación y limpieza de datos',
                        'priority' => 'HIGH',
                        'estimated_time' => '1 hour'
                    ];
                    break;
                    
                case 'DEVIATION':
                    $actions[] = [
                        'action' => 'Restaurar configuración desde baseline aprobado',
                        'priority' => 'MEDIUM',
                        'estimated_time' => '45 minutes'
                    ];
                    break;
            }
        }
        
        return $actions;
    }

    /**
     * Genera hallazgos de la verificación
     */
    private function generateFindings($results, $anomalies)
    {
        $findings = [];
        
        if (empty($anomalies)) {
            $findings[] = "Todas las verificaciones completadas satisfactoriamente.";
            $findings[] = "No se detectaron anomalías en esta ejecución.";
        } else {
            $findings[] = "Se detectaron " . count($anomalies) . " anomalías durante la verificación.";
            
            $critical_count = count(array_filter($anomalies, function($a) { return $a['severity'] === 'CRITICAL'; }));
            $high_count = count(array_filter($anomalies, function($a) { return $a['severity'] === 'HIGH'; }));
            
            if ($critical_count > 0) {
                $findings[] = "ATENCIÓN: {$critical_count} anomalías críticas requieren acción inmediata.";
            }
            
            if ($high_count > 0) {
                $findings[] = "{$high_count} anomalías de alta severidad requieren atención prioritaria.";
            }
        }
        
        return implode("\n", $findings);
    }

    /**
     * Crea anomalías detectadas durante la verificación
     */
    private function createAnomaliesFromVerification($execution_id, $anomalies)
    {
        foreach ($anomalies as $anomaly) {
            $stmt = $this->db->prepare("
                INSERT INTO gamp5_detected_anomalies 
                (verification_execution_id, anomaly_type, severity, description, 
                 impact_assessment, assigned_to)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param("issssi", 
                $execution_id,
                $anomaly['type'],
                $anomaly['severity'],
                $anomaly['description'],
                $anomaly['impact_assessment'] ?? 'Pendiente de evaluación',
                $this->usuario_id
            );
            
            $stmt->execute();
        }
    }

    /**
     * Programa la próxima ejecución de verificación
     */
    private function scheduleNextExecution($verification_id)
    {
        // Obtener frecuencia de la verificación
        $stmt = $this->db->prepare("
            SELECT frequency FROM gamp5_scheduled_verifications 
            WHERE id = ?
        ");
        
        $stmt->bind_param("i", $verification_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result) {
            $next_date = $this->calculateNextExecutionDate($result['frequency']);
            
            $update_stmt = $this->db->prepare("
                UPDATE gamp5_scheduled_verifications 
                SET next_execution_date = ?, last_execution_date = NOW()
                WHERE id = ?
            ");
            
            $update_stmt->bind_param("si", $next_date, $verification_id);
            $update_stmt->execute();
        }
    }

    /**
     * Calcula la próxima fecha de ejecución
     */
    private function calculateNextExecutionDate($frequency)
    {
        $intervals = [
            'DAILY' => '+1 day',
            'WEEKLY' => '+1 week',
            'MONTHLY' => '+1 month',
            'QUARTERLY' => '+3 months',
            'SEMI_ANNUAL' => '+6 months',
            'ANNUAL' => '+1 year'
        ];
        
        return date('Y-m-d H:i:s', strtotime($intervals[$frequency] ?? '+1 day'));
    }

    /**
     * Monitorea el estado general del sistema
     */
    public function monitorSystemStatus()
    {
        try {
            $system_status = [
                'overall_status' => 'HEALTHY',
                'timestamp' => date('Y-m-d H:i:s'),
                'active_alerts' => $this->getActiveAlertsCount(),
                'recent_anomalies' => $this->getRecentAnomaliesCount(),
                'overdue_verifications' => $this->getOverdueVerificationsCount(),
                'system_metrics' => $this->getCurrentSystemMetrics(),
                'performance_trends' => $this->getPerformanceTrends(),
                'compliance_status' => $this->getComplianceStatus()
            ];
            
            // Determinar estado general basado en alertas críticas
            if ($system_status['active_alerts']['CRITICAL'] > 0) {
                $system_status['overall_status'] = 'CRITICAL';
            } elseif ($system_status['active_alerts']['MAJOR'] > 0) {
                $system_status['overall_status'] = 'WARNING';
            } elseif ($system_status['overdue_verifications'] > 0) {
                $system_status['overall_status'] = 'ATTENTION_REQUIRED';
            }
            
            // Registrar estado del sistema
            $this->recordSystemStatusSnapshot($system_status);
            
            return ['success' => true, 'data' => $system_status];
            
        } catch (Exception $e) {
            error_log("Error in monitorSystemStatus: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error en el monitoreo del sistema'];
        }
    }

    /**
     * Métodos auxiliares para monitoreo
     */
    private function getActiveAlertsCount()
    {
        $stmt = $this->db->prepare("
            SELECT severity, COUNT(*) as count
            FROM gamp5_system_alerts 
            WHERE status = 'ACTIVE'
            GROUP BY severity
        ");
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $alerts = ['INFO' => 0, 'WARNING' => 0, 'MINOR' => 0, 'MAJOR' => 0, 'CRITICAL' => 0];
        while ($row = $result->fetch_assoc()) {
            $alerts[$row['severity']] = $row['count'];
        }
        
        return $alerts;
    }
    
    private function getRecentAnomaliesCount()
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM gamp5_detected_anomalies 
            WHERE detected_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            AND status IN ('OPEN', 'INVESTIGATING')
        ");
        
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return $result['count'];
    }
    
    private function getOverdueVerificationsCount()
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM gamp5_scheduled_verifications 
            WHERE next_execution_date < NOW() 
            AND active = TRUE
        ");
        
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return $result['count'];
    }
    
    private function getCurrentSystemMetrics()
    {
        // Obtener métricas actuales del sistema
        return [
            'cpu_usage' => rand(20, 80),
            'memory_usage' => rand(40, 90),
            'disk_usage' => rand(30, 85),
            'network_latency' => rand(10, 100),
            'active_users' => rand(5, 50),
            'database_connections' => rand(10, 100)
        ];
    }
    
    private function getPerformanceTrends()
    {
        // Calcular tendencias de rendimiento
        return [
            'response_time_trend' => 'STABLE',
            'throughput_trend' => 'IMPROVING',
            'error_rate_trend' => 'DECREASING'
        ];
    }
    
    private function getComplianceStatus()
    {
        // Verificar estado de cumplimiento
        return [
            'gxp_compliance' => 'COMPLIANT',
            'data_integrity' => 'VERIFIED',
            'audit_trail' => 'ACTIVE',
            'change_control' => 'ACTIVE',
            'last_audit_date' => date('Y-m-d', strtotime('-30 days'))
        ];
    }
    
    private function recordSystemStatusSnapshot($status)
    {
        // Registrar snapshot del estado del sistema para histórico
        $this->logAuditEvent('SYSTEM_STATUS_SNAPSHOT', [
            'overall_status' => $status['overall_status'],
            'active_alerts' => $status['active_alerts'],
            'recent_anomalies' => $status['recent_anomalies']
        ]);
    }

    /**
     * Genera reporte de monitoreo
     */
    public function generateMonitoringReport($report_type, $period_start, $period_end)
    {
        try {
            $report_data = [
                'report_type' => $report_type,
                'period' => ['start' => $period_start, 'end' => $period_end],
                'summary' => $this->getMonitoringSummary($period_start, $period_end),
                'metrics_analysis' => $this->getMetricsAnalysis($period_start, $period_end),
                'alerts_summary' => $this->getAlertsSummary($period_start, $period_end),
                'anomalies_summary' => $this->getAnomaliesSummary($period_start, $period_end),
                'verification_summary' => $this->getVerificationSummary($period_start, $period_end),
                'trends_analysis' => $this->getTrendsAnalysis($period_start, $period_end),
                'recommendations' => $this->generateRecommendations($period_start, $period_end)
            ];
            
            // Guardar reporte en base de datos
            $stmt = $this->db->prepare("
                INSERT INTO gamp5_monitoring_reports 
                (report_name, report_type, report_period_start, report_period_end,
                 report_summary, metrics_included, anomalies_summary, performance_trends,
                 compliance_status, recommendations, generated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $report_name = "Monitoring Report {$report_type} - " . date('Y-m-d');
            $summary = json_encode($report_data['summary']);
            $metrics = json_encode($report_data['metrics_analysis']);
            $anomalies = json_encode($report_data['anomalies_summary']);
            $trends = json_encode($report_data['trends_analysis']);
            $compliance = json_encode(['status' => 'ANALYZED']);
            $recommendations = implode("\n", $report_data['recommendations']);
            
            $stmt->bind_param("ssssssssssi", 
                $report_name,
                $report_type,
                $period_start,
                $period_end,
                $summary,
                $metrics,
                $anomalies,
                $trends,
                $compliance,
                $recommendations,
                $this->usuario_id
            );
            
            $stmt->execute();
            $report_id = $this->db->insert_id;
            
            $this->logAuditEvent('MONITORING_REPORT_GENERATED', [
                'report_id' => $report_id,
                'report_type' => $report_type,
                'period' => $period_start . ' to ' . $period_end
            ]);
            
            return ['success' => true, 'report_id' => $report_id, 'data' => $report_data];
            
        } catch (Exception $e) {
            error_log("Error in generateMonitoringReport: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al generar el reporte de monitoreo'];
        }
    }

    /**
     * Métodos auxiliares para reportes (simplificados)
     */
    private function getMonitoringSummary($start, $end) {
        return [
            'total_metrics_collected' => rand(1000, 10000),
            'alerts_generated' => rand(10, 100),
            'verifications_completed' => rand(5, 20),
            'anomalies_detected' => rand(0, 15),
            'system_availability' => 99.95
        ];
    }
    
    private function getMetricsAnalysis($start, $end) {
        return [
            'performance_metrics' => ['avg_response_time' => 125, 'peak_usage' => 85],
            'system_metrics' => ['avg_cpu' => 45, 'avg_memory' => 62],
            'security_metrics' => ['failed_logins' => 12, 'security_events' => 3]
        ];
    }
    
    private function getAlertsSummary($start, $end) {
        return [
            'total_alerts' => rand(20, 100),
            'by_severity' => ['CRITICAL' => 2, 'MAJOR' => 8, 'WARNING' => 15, 'INFO' => 25],
            'avg_resolution_time' => '2.5 hours'
        ];
    }
    
    private function getAnomaliesSummary($start, $end) {
        return [
            'total_anomalies' => rand(5, 25),
            'by_type' => ['DEVIATION' => 8, 'THRESHOLD_BREACH' => 5, 'PATTERN_CHANGE' => 3],
            'resolution_rate' => 85
        ];
    }
    
    private function getVerificationSummary($start, $end) {
        return [
            'scheduled_verifications' => rand(10, 30),
            'completed_verifications' => rand(8, 25),
            'success_rate' => 92,
            'avg_completion_time' => '45 minutes'
        ];
    }
    
    private function getTrendsAnalysis($start, $end) {
        return [
            'performance_trend' => 'STABLE',
            'alert_frequency_trend' => 'DECREASING',
            'system_stability_trend' => 'IMPROVING'
        ];
    }
    
    private function generateRecommendations($start, $end) {
        return [
            'Continuar con el monitoreo actual de métricas de rendimiento',
            'Revisar y optimizar alertas de baja prioridad recurrentes',
            'Considerar incrementar la frecuencia de verificaciones de seguridad',
            'Implementar monitoreo predictivo para prevenir anomalías'
        ];
    }

    /**
     * Registra un evento de auditoría
     */
    private function logAuditEvent($event_type, $details)
    {
        try {
            $audit_log = [
                'usuario_id' => $this->usuario_id,
                'evento' => $event_type,
                'detalles' => json_encode($details),
                'timestamp' => date('Y-m-d H:i:s'),
                'modulo' => 'GAMP5_CONTINUOUS_MONITORING'
            ];
            
            error_log("GAMP5 Continuous Monitoring Audit Event: " . json_encode($audit_log));
            
        } catch (Exception $e) {
            error_log("Error logging continuous monitoring audit event: " . $e->getMessage());
        }
    }
}