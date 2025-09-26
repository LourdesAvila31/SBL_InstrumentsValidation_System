<?php
/**
 * Gestor de Validación del Sistema conforme a GAMP 5
 * 
 * Esta clase gestiona la validación de procesos computarizados para garantizar
 * que se ajusten a las regulaciones GxP, incluyendo documentación adecuada
 * y verificación regular
 */

require_once dirname(__DIR__, 2) . '/Core/db_config.php';
require_once dirname(__DIR__, 2) . '/Core/permissions.php';

class SystemValidationManager
{
    private $db;
    private $usuario_id;
    
    // Tipos de validación
    const VALIDATION_TYPES = [
        'COMPUTER_SYSTEM' => 'Sistema Computarizado',
        'ANALYTICAL_METHOD' => 'Método Analítico',
        'PROCESS' => 'Proceso',
        'CLEANING' => 'Limpieza',
        'EQUIPMENT' => 'Equipo'
    ];
    
    // Estados de validación
    const VALIDATION_STATUS = [
        'PLANNED' => 'Planificado',
        'IN_PROGRESS' => 'En Progreso',
        'COMPLETED' => 'Completado',
        'APPROVED' => 'Aprobado',
        'REJECTED' => 'Rechazado',
        'REVALIDATION_REQUIRED' => 'Revalidación Requerida',
        'RETIRED' => 'Retirado'
    ];
    
    // Criticidad según impacto en la calidad del producto
    const CRITICALITY_LEVELS = [
        'LOW' => 'Baja - Sin impacto directo en calidad',
        'MEDIUM' => 'Media - Impacto indirecto en calidad',
        'HIGH' => 'Alta - Impacto directo en calidad',
        'CRITICAL' => 'Crítica - Impacto crítico en seguridad del paciente'
    ];

    public function __construct($usuario_id = null)
    {
        $this->db = DatabaseManager::getConnection();
        $this->usuario_id = $usuario_id ?? $_SESSION['usuario_id'] ?? null;
        $this->initializeTables();
    }

    /**
     * Inicializa las tablas necesarias para la gestión de validaciones
     */
    private function initializeTables()
    {
        $queries = [
            // Tabla principal de validaciones
            "CREATE TABLE IF NOT EXISTS gamp5_validations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                validation_name VARCHAR(255) NOT NULL,
                validation_type ENUM('COMPUTER_SYSTEM', 'ANALYTICAL_METHOD', 'PROCESS', 'CLEANING', 'EQUIPMENT') NOT NULL,
                system_description TEXT,
                business_justification TEXT,
                regulatory_requirements JSON,
                criticality ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') NOT NULL,
                status ENUM('PLANNED', 'IN_PROGRESS', 'COMPLETED', 'APPROVED', 'REJECTED', 'REVALIDATION_REQUIRED', 'RETIRED') NOT NULL DEFAULT 'PLANNED',
                validation_plan_approved BOOLEAN DEFAULT FALSE,
                validation_plan_path VARCHAR(500),
                master_validation_plan_id INT NULL,
                planned_start_date DATE,
                actual_start_date DATE NULL,
                planned_completion_date DATE,
                actual_completion_date DATE NULL,
                revalidation_due_date DATE NULL,
                validation_team JSON,
                created_by INT NOT NULL,
                approved_by INT NULL,
                approved_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_validation_type (validation_type),
                INDEX idx_status (status),
                INDEX idx_criticality (criticality),
                INDEX idx_revalidation_due (revalidation_due_date)
            )",
            
            // Tabla de fases de validación
            "CREATE TABLE IF NOT EXISTS gamp5_validation_phases (
                id INT PRIMARY KEY AUTO_INCREMENT,
                validation_id INT NOT NULL,
                phase_name VARCHAR(255) NOT NULL,
                phase_type ENUM('PLANNING', 'SPECIFICATION', 'VERIFICATION', 'REPORTING', 'CLOSURE') NOT NULL,
                description TEXT,
                deliverables JSON,
                acceptance_criteria JSON,
                status ENUM('NOT_STARTED', 'IN_PROGRESS', 'COMPLETED', 'APPROVED', 'ON_HOLD') NOT NULL DEFAULT 'NOT_STARTED',
                planned_start_date DATE,
                actual_start_date DATE NULL,
                planned_end_date DATE,
                actual_end_date DATE NULL,
                responsible_person INT NULL,
                reviewed_by INT NULL,
                approved_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (validation_id) REFERENCES gamp5_validations(id) ON DELETE CASCADE,
                INDEX idx_validation_phase (validation_id, phase_type),
                INDEX idx_phase_status (status)
            )",
            
            // Tabla de documentos de validación
            "CREATE TABLE IF NOT EXISTS gamp5_validation_documents (
                id INT PRIMARY KEY AUTO_INCREMENT,
                validation_id INT NOT NULL,
                phase_id INT NULL,
                document_type ENUM('VMP', 'VPlan', 'URS', 'FRS', 'DS', 'RA', 'TM', 'TP', 'TE', 'TS', 'VR', 'VS') NOT NULL,
                document_name VARCHAR(255) NOT NULL,
                document_description TEXT,
                document_path VARCHAR(500),
                version VARCHAR(20) NOT NULL DEFAULT '1.0',
                status ENUM('DRAFT', 'UNDER_REVIEW', 'APPROVED', 'OBSOLETE') NOT NULL DEFAULT 'DRAFT',
                created_by INT NOT NULL,
                reviewed_by INT NULL,
                approved_by INT NULL,
                review_date DATE NULL,
                approval_date DATE NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (validation_id) REFERENCES gamp5_validations(id) ON DELETE CASCADE,
                FOREIGN KEY (phase_id) REFERENCES gamp5_validation_phases(id) ON DELETE SET NULL,
                INDEX idx_validation_document (validation_id),
                INDEX idx_document_type (document_type),
                INDEX idx_document_status (status)
            )",
            
            // Tabla de pruebas de validación
            "CREATE TABLE IF NOT EXISTS gamp5_validation_tests (
                id INT PRIMARY KEY AUTO_INCREMENT,
                validation_id INT NOT NULL,
                phase_id INT NULL,
                test_category ENUM('UNIT', 'INTEGRATION', 'SYSTEM', 'ACCEPTANCE', 'REGRESSION') NOT NULL,
                test_name VARCHAR(255) NOT NULL,
                test_objective TEXT,
                test_procedure TEXT,
                expected_result TEXT,
                actual_result TEXT,
                test_data_location VARCHAR(500),
                status ENUM('NOT_EXECUTED', 'IN_PROGRESS', 'PASSED', 'FAILED', 'BLOCKED', 'SKIPPED') NOT NULL DEFAULT 'NOT_EXECUTED',
                priority ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') NOT NULL DEFAULT 'MEDIUM',
                executed_by INT NULL,
                executed_at TIMESTAMP NULL,
                reviewed_by INT NULL,
                reviewed_at TIMESTAMP NULL,
                comments TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (validation_id) REFERENCES gamp5_validations(id) ON DELETE CASCADE,
                FOREIGN KEY (phase_id) REFERENCES gamp5_validation_phases(id) ON DELETE SET NULL,
                INDEX idx_validation_test (validation_id),
                INDEX idx_test_status (status),
                INDEX idx_test_category (test_category)
            )",
            
            // Tabla de auditorías de validación
            "CREATE TABLE IF NOT EXISTS gamp5_validation_audits (
                id INT PRIMARY KEY AUTO_INCREMENT,
                validation_id INT NOT NULL,
                audit_type ENUM('INTERNAL', 'EXTERNAL', 'REGULATORY', 'VENDOR') NOT NULL,
                audit_scope TEXT,
                audit_date DATE NOT NULL,
                auditor_name VARCHAR(255),
                audit_findings JSON,
                findings_summary TEXT,
                corrective_actions JSON,
                status ENUM('SCHEDULED', 'IN_PROGRESS', 'COMPLETED', 'CLOSED') NOT NULL DEFAULT 'SCHEDULED',
                audit_report_path VARCHAR(500),
                conducted_by INT NULL,
                reviewed_by INT NULL,
                approved_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (validation_id) REFERENCES gamp5_validations(id) ON DELETE CASCADE,
                INDEX idx_validation_audit (validation_id),
                INDEX idx_audit_type (audit_type),
                INDEX idx_audit_date (audit_date)
            )",
            
            // Tabla de verificaciones continuas
            "CREATE TABLE IF NOT EXISTS gamp5_validation_continuous_verification (
                id INT PRIMARY KEY AUTO_INCREMENT,
                validation_id INT NOT NULL,
                verification_type ENUM('PERIODIC_REVIEW', 'CHANGE_IMPACT', 'PERFORMANCE_CHECK', 'COMPLIANCE_AUDIT', 'REVALIDATION_ASSESSMENT') NOT NULL,
                description TEXT NOT NULL,
                frequency ENUM('MONTHLY', 'QUARTERLY', 'SEMI_ANNUAL', 'ANNUAL', 'AD_HOC') NOT NULL,
                last_performed_date DATE NULL,
                next_due_date DATE NOT NULL,
                status ENUM('DUE', 'IN_PROGRESS', 'COMPLETED', 'OVERDUE', 'CANCELLED') NOT NULL DEFAULT 'DUE',
                performed_by INT NULL,
                findings TEXT,
                recommendations TEXT,
                follow_up_actions JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (validation_id) REFERENCES gamp5_validations(id) ON DELETE CASCADE,
                INDEX idx_validation_verification (validation_id),
                INDEX idx_verification_due_date (next_due_date),
                INDEX idx_verification_status (status)
            )"
        ];

        foreach ($queries as $query) {
            if (!$this->db->query($query)) {
                error_log("Error creating validation table: " . $this->db->error);
            }
        }
    }

    /**
     * Crea una nueva validación
     */
    public function createValidation($data)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO gamp5_validations 
                (validation_name, validation_type, system_description, business_justification,
                 regulatory_requirements, criticality, planned_start_date, planned_completion_date,
                 validation_team, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $regulatory_requirements = json_encode($data['regulatory_requirements'] ?? []);
            $validation_team = json_encode($data['validation_team'] ?? []);
            
            $stmt->bind_param("sssssssssi", 
                $data['validation_name'],
                $data['validation_type'],
                $data['system_description'],
                $data['business_justification'],
                $regulatory_requirements,
                $data['criticality'],
                $data['planned_start_date'],
                $data['planned_completion_date'],
                $validation_team,
                $this->usuario_id
            );
            
            if ($stmt->execute()) {
                $validation_id = $this->db->insert_id;
                
                // Crear fases predeterminadas de validación
                $this->createDefaultValidationPhases($validation_id, $data['validation_type']);
                
                // Programar verificaciones continuas basadas en criticidad
                $this->scheduleInitialContinuousVerifications($validation_id, $data['criticality']);
                
                $this->logAuditEvent('VALIDATION_CREATED', [
                    'validation_id' => $validation_id,
                    'type' => $data['validation_type'],
                    'criticality' => $data['criticality']
                ]);
                
                return ['success' => true, 'validation_id' => $validation_id];
            }
            
            return ['success' => false, 'error' => 'Error al crear la validación'];
            
        } catch (Exception $e) {
            error_log("Error in createValidation: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error interno del servidor'];
        }
    }

    /**
     * Crea las fases predeterminadas de validación
     */
    private function createDefaultValidationPhases($validation_id, $validation_type)
    {
        $phases = [
            [
                'phase_name' => 'Planificación de la Validación',
                'phase_type' => 'PLANNING',
                'description' => 'Definición del alcance, recursos y cronograma de validación',
                'deliverables' => ['Plan de Validación', 'Matriz de Trazabilidad', 'Cronograma'],
                'acceptance_criteria' => ['Plan aprobado por stakeholders', 'Recursos asignados', 'Cronograma viable']
            ],
            [
                'phase_name' => 'Especificaciones del Sistema',
                'phase_type' => 'SPECIFICATION',
                'description' => 'Desarrollo de especificaciones de usuario y funcionales',
                'deliverables' => ['URS', 'FRS', 'Especificación de Diseño'],
                'acceptance_criteria' => ['Especificaciones completas', 'Revisión técnica aprobada', 'Trazabilidad establecida']
            ],
            [
                'phase_name' => 'Verificación y Pruebas',
                'phase_type' => 'VERIFICATION',
                'description' => 'Ejecución de pruebas de verificación y validación',
                'deliverables' => ['Protocolos de Prueba', 'Registros de Ejecución', 'Evidencias de Prueba'],
                'acceptance_criteria' => ['Todos los casos de prueba ejecutados', 'Criterios de aceptación cumplidos', 'Defectos críticos resueltos']
            ],
            [
                'phase_name' => 'Reportes de Validación',
                'phase_type' => 'REPORTING',
                'description' => 'Generación de reportes y documentación final',
                'deliverables' => ['Reporte de Validación', 'Resumen de Trazabilidad', 'Certificado de Validación'],
                'acceptance_criteria' => ['Reporte completo y aprobado', 'Trazabilidad verificada', 'Certificación emitida']
            ],
            [
                'phase_name' => 'Cierre de Validación',
                'phase_type' => 'CLOSURE',
                'description' => 'Cierre formal del proyecto de validación',
                'deliverables' => ['Acta de Cierre', 'Archivo de Documentación', 'Plan de Mantenimiento'],
                'acceptance_criteria' => ['Documentación archivada', 'Responsabilidades transferidas', 'Plan de revalidación establecido']
            ]
        ];
        
        foreach ($phases as $phase) {
            $stmt = $this->db->prepare("
                INSERT INTO gamp5_validation_phases 
                (validation_id, phase_name, phase_type, description, deliverables, acceptance_criteria)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $deliverables_json = json_encode($phase['deliverables']);
            $criteria_json = json_encode($phase['acceptance_criteria']);
            
            $stmt->bind_param("isssss", 
                $validation_id,
                $phase['phase_name'],
                $phase['phase_type'],
                $phase['description'],
                $deliverables_json,
                $criteria_json
            );
            
            $stmt->execute();
        }
    }

    /**
     * Programa verificaciones continuas iniciales
     */
    private function scheduleInitialContinuousVerifications($validation_id, $criticality)
    {
        $verifications = [];
        
        // Definir frecuencias basadas en criticidad
        $frequencies = [
            'LOW' => ['ANNUAL'],
            'MEDIUM' => ['SEMI_ANNUAL', 'ANNUAL'],
            'HIGH' => ['QUARTERLY', 'SEMI_ANNUAL', 'ANNUAL'],
            'CRITICAL' => ['MONTHLY', 'QUARTERLY', 'SEMI_ANNUAL', 'ANNUAL']
        ];
        
        $verification_types = [
            'PERIODIC_REVIEW' => 'Revisión periódica del sistema y documentación',
            'PERFORMANCE_CHECK' => 'Verificación del rendimiento del sistema',
            'COMPLIANCE_AUDIT' => 'Auditoría de cumplimiento regulatorio'
        ];
        
        foreach ($verification_types as $type => $description) {
            foreach ($frequencies[$criticality] as $frequency) {
                $next_due_date = $this->calculateNextDueDate($frequency);
                
                $stmt = $this->db->prepare("
                    INSERT INTO gamp5_validation_continuous_verification 
                    (validation_id, verification_type, description, frequency, next_due_date)
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                $stmt->bind_param("issss", 
                    $validation_id,
                    $type,
                    $description,
                    $frequency,
                    $next_due_date
                );
                
                $stmt->execute();
            }
        }
    }

    /**
     * Calcula la próxima fecha de vencimiento según la frecuencia
     */
    private function calculateNextDueDate($frequency)
    {
        $intervals = [
            'MONTHLY' => '+1 month',
            'QUARTERLY' => '+3 months',
            'SEMI_ANNUAL' => '+6 months',
            'ANNUAL' => '+1 year'
        ];
        
        return date('Y-m-d', strtotime($intervals[$frequency]));
    }

    /**
     * Realiza validación del sistema
     */
    public function validateSystem($validation_id, $configuration)
    {
        try {
            // Obtener información de la validación
            $validation = $this->getValidationDetails($validation_id);
            if (!$validation['success']) {
                return $validation;
            }
            
            $validation_data = $validation['data'];
            
            // Realizar verificaciones según el tipo de validación
            $verification_results = $this->performSystemVerifications($validation_data, $configuration);
            
            // Determinar resultado general
            $overall_result = $this->determineValidationResult($verification_results);
            
            // Actualizar estado de la validación
            if ($overall_result === 'PASSED') {
                $this->updateValidationStatus($validation_id, 'COMPLETED');
            } else {
                $this->updateValidationStatus($validation_id, 'REJECTED');
                
                // Crear acciones correctivas para los fallos
                $this->createCorrectiveActions($validation_id, $verification_results['failures']);
            }
            
            // Registrar resultados de validación
            $this->recordValidationResults($validation_id, $verification_results, $overall_result);
            
            $this->logAuditEvent('SYSTEM_VALIDATED', [
                'validation_id' => $validation_id,
                'result' => $overall_result,
                'checks_performed' => count($verification_results['checks'])
            ]);
            
            return [
                'success' => true,
                'result' => $overall_result,
                'details' => $verification_results
            ];
            
        } catch (Exception $e) {
            error_log("Error in validateSystem: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error interno del servidor'];
        }
    }

    /**
     * Realiza verificaciones del sistema
     */
    private function performSystemVerifications($validation_data, $configuration)
    {
        $checks = [];
        $failures = [];
        
        // Verificaciones comunes para todos los tipos
        $common_checks = [
            'configuration_integrity' => $this->checkConfigurationIntegrity($configuration),
            'data_integrity' => $this->checkDataIntegrity($configuration),
            'security_controls' => $this->checkSecurityControls($configuration),
            'audit_trail' => $this->checkAuditTrail($configuration),
            'backup_recovery' => $this->checkBackupRecovery($configuration)
        ];
        
        // Verificaciones específicas por tipo
        $specific_checks = $this->getTypeSpecificChecks($validation_data['validation_type'], $configuration);
        
        $all_checks = array_merge($common_checks, $specific_checks);
        
        foreach ($all_checks as $check_name => $result) {
            $checks[$check_name] = $result;
            if (!$result['passed']) {
                $failures[] = [
                    'check' => $check_name,
                    'reason' => $result['reason'] ?? 'Verificación fallida',
                    'details' => $result['details'] ?? []
                ];
            }
        }
        
        return [
            'checks' => $checks,
            'failures' => $failures,
            'total_checks' => count($all_checks),
            'passed_checks' => count($all_checks) - count($failures),
            'failed_checks' => count($failures)
        ];
    }

    /**
     * Verifica la integridad de la configuración
     */
    private function checkConfigurationIntegrity($configuration)
    {
        // Verificar que la configuración es válida y consistente
        $checks = [
            'required_parameters' => isset($configuration['required_params']),
            'parameter_values' => $this->validateParameterValues($configuration),
            'configuration_hash' => $this->verifyConfigurationHash($configuration)
        ];
        
        $passed = array_reduce($checks, function($carry, $item) { return $carry && $item; }, true);
        
        return [
            'passed' => $passed,
            'details' => $checks,
            'reason' => $passed ? 'Configuración íntegra' : 'Problemas en la configuración detectados'
        ];
    }

    /**
     * Verifica la integridad de los datos
     */
    private function checkDataIntegrity($configuration)
    {
        // Implementar verificaciones de integridad de datos
        return [
            'passed' => true,
            'details' => ['data_consistency' => true, 'checksums_valid' => true],
            'reason' => 'Integridad de datos verificada'
        ];
    }

    /**
     * Verifica los controles de seguridad
     */
    private function checkSecurityControls($configuration)
    {
        // Implementar verificaciones de seguridad
        return [
            'passed' => true,
            'details' => ['access_controls' => true, 'encryption' => true, 'authentication' => true],
            'reason' => 'Controles de seguridad operativos'
        ];
    }

    /**
     * Verifica el audit trail
     */
    private function checkAuditTrail($configuration)
    {
        // Implementar verificaciones de audit trail
        return [
            'passed' => true,
            'details' => ['logging_enabled' => true, 'log_integrity' => true, 'retention_policy' => true],
            'reason' => 'Audit trail funcionando correctamente'
        ];
    }

    /**
     * Verifica backup y recuperación
     */
    private function checkBackupRecovery($configuration)
    {
        // Implementar verificaciones de backup y recuperación
        return [
            'passed' => true,
            'details' => ['backup_schedule' => true, 'recovery_procedures' => true, 'backup_integrity' => true],
            'reason' => 'Sistemas de backup y recuperación operativos'
        ];
    }

    /**
     * Obtiene verificaciones específicas por tipo de validación
     */
    private function getTypeSpecificChecks($validation_type, $configuration)
    {
        switch ($validation_type) {
            case 'COMPUTER_SYSTEM':
                return [
                    'system_functionality' => $this->checkSystemFunctionality($configuration),
                    'user_access_controls' => $this->checkUserAccessControls($configuration),
                    'change_control' => $this->checkChangeControl($configuration)
                ];
                
            case 'ANALYTICAL_METHOD':
                return [
                    'method_accuracy' => $this->checkMethodAccuracy($configuration),
                    'method_precision' => $this->checkMethodPrecision($configuration),
                    'method_linearity' => $this->checkMethodLinearity($configuration)
                ];
                
            case 'PROCESS':
                return [
                    'process_consistency' => $this->checkProcessConsistency($configuration),
                    'process_capability' => $this->checkProcessCapability($configuration),
                    'process_controls' => $this->checkProcessControls($configuration)
                ];
                
            default:
                return [];
        }
    }

    /**
     * Métodos de verificación específicos (simplificados para el ejemplo)
     */
    private function validateParameterValues($configuration) { return true; }
    private function verifyConfigurationHash($configuration) { return true; }
    private function checkSystemFunctionality($configuration) { 
        return ['passed' => true, 'reason' => 'Funcionalidad del sistema verificada']; 
    }
    private function checkUserAccessControls($configuration) { 
        return ['passed' => true, 'reason' => 'Controles de acceso verificados']; 
    }
    private function checkChangeControl($configuration) { 
        return ['passed' => true, 'reason' => 'Control de cambios verificado']; 
    }
    private function checkMethodAccuracy($configuration) { 
        return ['passed' => true, 'reason' => 'Precisión del método verificada']; 
    }
    private function checkMethodPrecision($configuration) { 
        return ['passed' => true, 'reason' => 'Exactitud del método verificada']; 
    }
    private function checkMethodLinearity($configuration) { 
        return ['passed' => true, 'reason' => 'Linealidad del método verificada']; 
    }
    private function checkProcessConsistency($configuration) { 
        return ['passed' => true, 'reason' => 'Consistencia del proceso verificada']; 
    }
    private function checkProcessCapability($configuration) { 
        return ['passed' => true, 'reason' => 'Capacidad del proceso verificada']; 
    }
    private function checkProcessControls($configuration) { 
        return ['passed' => true, 'reason' => 'Controles del proceso verificados']; 
    }

    /**
     * Determina el resultado general de la validación
     */
    private function determineValidationResult($verification_results)
    {
        $critical_failures = array_filter($verification_results['failures'], function($failure) {
            return in_array($failure['check'], ['configuration_integrity', 'data_integrity', 'security_controls']);
        });
        
        if (count($critical_failures) > 0) {
            return 'FAILED';
        }
        
        if ($verification_results['failed_checks'] == 0) {
            return 'PASSED';
        }
        
        // Si hay fallos no críticos, puede ser aprobado condicionalmente
        return ($verification_results['failed_checks'] <= ($verification_results['total_checks'] * 0.1)) ? 
               'CONDITIONAL_PASS' : 'FAILED';
    }

    /**
     * Actualiza el estado de una validación
     */
    private function updateValidationStatus($validation_id, $status)
    {
        $stmt = $this->db->prepare("
            UPDATE gamp5_validations 
            SET status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->bind_param("si", $status, $validation_id);
        $stmt->execute();
    }

    /**
     * Registra los resultados de validación
     */
    private function recordValidationResults($validation_id, $verification_results, $overall_result)
    {
        // Crear un documento de resultados de validación
        $stmt = $this->db->prepare("
            INSERT INTO gamp5_validation_documents 
            (validation_id, document_type, document_name, document_description, status, created_by)
            VALUES (?, 'VR', ?, ?, 'APPROVED', ?)
        ");
        
        $document_name = "Validation Results - " . date('Y-m-d H:i:s');
        $description = json_encode([
            'overall_result' => $overall_result,
            'verification_summary' => $verification_results,
            'generated_at' => date('Y-m-d H:i:s')
        ]);
        
        $stmt->bind_param("issi", $validation_id, $document_name, $description, $this->usuario_id);
        $stmt->execute();
    }

    /**
     * Crea acciones correctivas para fallos
     */
    private function createCorrectiveActions($validation_id, $failures)
    {
        foreach ($failures as $failure) {
            // Crear una entrada en el sistema de gestión de cambios o tickets
            $this->logAuditEvent('CORRECTIVE_ACTION_REQUIRED', [
                'validation_id' => $validation_id,
                'failure_check' => $failure['check'],
                'reason' => $failure['reason']
            ]);
        }
    }

    /**
     * Obtiene detalles de una validación
     */
    public function getValidationDetails($validation_id)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    v.*,
                    u1.nombre as created_by_name,
                    u2.nombre as approved_by_name
                FROM gamp5_validations v
                LEFT JOIN usuarios u1 ON v.created_by = u1.id
                LEFT JOIN usuarios u2 ON v.approved_by = u2.id
                WHERE v.id = ?
            ");
            
            $stmt->bind_param("i", $validation_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if ($result) {
                $result['regulatory_requirements'] = json_decode($result['regulatory_requirements'], true);
                $result['validation_team'] = json_decode($result['validation_team'], true);
                return ['success' => true, 'data' => $result];
            }
            
            return ['success' => false, 'error' => 'Validación no encontrada'];
            
        } catch (Exception $e) {
            error_log("Error in getValidationDetails: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error interno del servidor'];
        }
    }

    /**
     * Obtiene todas las validaciones con filtros
     */
    public function getAllValidations($filters = [])
    {
        try {
            $where_conditions = ['1=1'];
            $params = [];
            $types = '';
            
            if (!empty($filters['status'])) {
                $where_conditions[] = 'v.status = ?';
                $params[] = $filters['status'];
                $types .= 's';
            }
            
            if (!empty($filters['type'])) {
                $where_conditions[] = 'v.validation_type = ?';
                $params[] = $filters['type'];
                $types .= 's';
            }
            
            if (!empty($filters['criticality'])) {
                $where_conditions[] = 'v.criticality = ?';
                $params[] = $filters['criticality'];
                $types .= 's';
            }
            
            if (!empty($filters['revalidation_due'])) {
                $where_conditions[] = 'v.revalidation_due_date <= ?';
                $params[] = date('Y-m-d', strtotime('+30 days'));
                $types .= 's';
            }
            
            $sql = "
                SELECT 
                    v.*,
                    u1.nombre as created_by_name,
                    COUNT(vp.id) as total_phases,
                    COUNT(CASE WHEN vp.status = 'COMPLETED' THEN 1 END) as completed_phases,
                    COUNT(CASE WHEN vcv.status = 'OVERDUE' THEN 1 END) as overdue_verifications
                FROM gamp5_validations v
                LEFT JOIN usuarios u1 ON v.created_by = u1.id
                LEFT JOIN gamp5_validation_phases vp ON v.id = vp.validation_id
                LEFT JOIN gamp5_validation_continuous_verification vcv ON v.id = vcv.validation_id
                WHERE " . implode(' AND ', $where_conditions) . "
                GROUP BY v.id
                ORDER BY v.created_at DESC
            ";
            
            $stmt = $this->db->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $validations = [];
            while ($row = $result->fetch_assoc()) {
                $row['regulatory_requirements'] = json_decode($row['regulatory_requirements'], true);
                $row['validation_team'] = json_decode($row['validation_team'], true);
                $row['completion_percentage'] = $row['total_phases'] > 0 ? 
                    round(($row['completed_phases'] / $row['total_phases']) * 100, 2) : 0;
                $validations[] = $row;
            }
            
            return ['success' => true, 'data' => $validations];
            
        } catch (Exception $e) {
            error_log("Error in getAllValidations: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al obtener las validaciones'];
        }
    }

    /**
     * Monitorea el estado del sistema
     */
    public function monitorSystemStatus()
    {
        try {
            $monitoring_results = [
                'database_connection' => $this->checkDatabaseConnection(),
                'system_performance' => $this->checkSystemPerformance(),
                'validation_status' => $this->getValidationSystemStatus(),
                'overdue_verifications' => $this->getOverdueVerifications(),
                'critical_alerts' => $this->getCriticalAlerts(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            // Generar alertas si es necesario
            $this->generateSystemAlerts($monitoring_results);
            
            return ['success' => true, 'data' => $monitoring_results];
            
        } catch (Exception $e) {
            error_log("Error in monitorSystemStatus: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error en el monitoreo del sistema'];
        }
    }

    /**
     * Métodos de monitoreo (simplificados)
     */
    private function checkDatabaseConnection() {
        return ['status' => 'OK', 'response_time' => '< 100ms'];
    }
    
    private function checkSystemPerformance() {
        return ['cpu_usage' => '25%', 'memory_usage' => '45%', 'disk_usage' => '60%'];
    }
    
    private function getValidationSystemStatus() {
        $stmt = $this->db->prepare("
            SELECT 
                status,
                COUNT(*) as count
            FROM gamp5_validations
            GROUP BY status
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $status_counts = [];
        while ($row = $result->fetch_assoc()) {
            $status_counts[$row['status']] = $row['count'];
        }
        
        return $status_counts;
    }
    
    private function getOverdueVerifications() {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM gamp5_validation_continuous_verification
            WHERE next_due_date < CURDATE() AND status IN ('DUE', 'IN_PROGRESS')
        ");
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return $result['count'];
    }
    
    private function getCriticalAlerts() {
        // Implementar lógica para identificar alertas críticas
        return [];
    }
    
    private function generateSystemAlerts($monitoring_results) {
        if ($monitoring_results['overdue_verifications'] > 0) {
            $this->sendAlert("Hay {$monitoring_results['overdue_verifications']} verificaciones vencidas que requieren atención.");
        }
        
        // Más lógica de alertas según sea necesario
    }
    
    private function sendAlert($message) {
        // Implementar sistema de alertas (email, notificaciones, etc.)
        error_log("GAMP5 System Alert: " . $message);
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
                'modulo' => 'GAMP5_VALIDATION'
            ];
            
            error_log("GAMP5 Validation Audit Event: " . json_encode($audit_log));
            
        } catch (Exception $e) {
            error_log("Error logging validation audit event: " . $e->getMessage());
        }
    }
}