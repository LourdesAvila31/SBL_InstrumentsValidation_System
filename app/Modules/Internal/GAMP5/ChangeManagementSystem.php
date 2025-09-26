<?php
/**
 * Sistema de Gestión de Cambios conforme a GAMP 5
 * 
 * Esta clase gestiona los cambios de manera controlada siguiendo las normativas GxP.
 * Cualquier cambio en el sistema debe ser registrado, evaluado y verificado.
 */

require_once dirname(__DIR__, 2) . '/Core/db_config.php';
require_once dirname(__DIR__, 2) . '/Core/permissions.php';

class ChangeManagementSystem
{
    private $db;
    private $usuario_id;
    
    // Tipos de cambio
    const CHANGE_TYPES = [
        'CONFIGURATION' => 'Cambio de Configuración',
        'SOFTWARE_UPDATE' => 'Actualización de Software',
        'HARDWARE_CHANGE' => 'Cambio de Hardware', 
        'PROCEDURE_CHANGE' => 'Cambio de Procedimiento',
        'DOCUMENTATION' => 'Cambio de Documentación',
        'SECURITY' => 'Cambio de Seguridad',
        'EMERGENCY' => 'Cambio de Emergencia'
    ];
    
    // Categorías de impacto
    const IMPACT_CATEGORIES = [
        'BAJO' => 'Bajo - Sin impacto en funcionalidad crítica',
        'MEDIO' => 'Medio - Impacto limitado en funcionalidad',
        'ALTO' => 'Alto - Impacto significativo en funcionalidad',
        'CRITICO' => 'Crítico - Impacto en calidad del producto o seguridad'
    ];
    
    // Estados del cambio
    const CHANGE_STATUS = [
        'DRAFT' => 'Borrador',
        'SUBMITTED' => 'Enviado',
        'UNDER_REVIEW' => 'En Revisión',
        'APPROVED' => 'Aprobado',
        'REJECTED' => 'Rechazado',
        'IMPLEMENTATION' => 'En Implementación',
        'TESTING' => 'En Pruebas',
        'COMPLETED' => 'Completado',
        'CLOSED' => 'Cerrado',
        'CANCELLED' => 'Cancelado'
    ];
    
    // Niveles de aprobación requeridos por impacto
    const APPROVAL_LEVELS = [
        'BAJO' => ['SUPERVISOR'],
        'MEDIO' => ['SUPERVISOR', 'QUALITY_MANAGER'],
        'ALTO' => ['SUPERVISOR', 'QUALITY_MANAGER', 'TECHNICAL_LEAD'],
        'CRITICO' => ['SUPERVISOR', 'QUALITY_MANAGER', 'TECHNICAL_LEAD', 'REGULATORY_AFFAIRS']
    ];

    public function __construct($usuario_id = null)
    {
        $this->db = DatabaseManager::getConnection();
        $this->usuario_id = $usuario_id ?? $_SESSION['usuario_id'] ?? null;
        $this->initializeTables();
    }

    /**
     * Inicializa las tablas necesarias para la gestión de cambios
     */
    private function initializeTables()
    {
        $queries = [
            // Tabla principal de solicitudes de cambio
            "CREATE TABLE IF NOT EXISTS gamp5_change_requests (
                id INT PRIMARY KEY AUTO_INCREMENT,
                change_number VARCHAR(50) NOT NULL UNIQUE,
                title VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                business_justification TEXT NOT NULL,
                change_type ENUM('CONFIGURATION', 'SOFTWARE_UPDATE', 'HARDWARE_CHANGE', 'PROCEDURE_CHANGE', 'DOCUMENTATION', 'SECURITY', 'EMERGENCY') NOT NULL,
                impact_level ENUM('BAJO', 'MEDIO', 'ALTO', 'CRITICO') NOT NULL,
                urgency ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') NOT NULL DEFAULT 'MEDIUM',
                status ENUM('DRAFT', 'SUBMITTED', 'UNDER_REVIEW', 'APPROVED', 'REJECTED', 'IMPLEMENTATION', 'TESTING', 'COMPLETED', 'CLOSED', 'CANCELLED') NOT NULL DEFAULT 'DRAFT',
                affected_systems JSON,
                affected_documents JSON,
                risk_assessment TEXT,
                implementation_plan TEXT,
                rollback_plan TEXT,
                test_plan TEXT,
                validation_required BOOLEAN DEFAULT FALSE,
                estimated_effort_hours INT,
                estimated_cost DECIMAL(10,2),
                planned_start_date DATE,
                planned_completion_date DATE,
                actual_start_date DATE NULL,
                actual_completion_date DATE NULL,
                created_by INT NOT NULL,
                assigned_to INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_change_number (change_number),
                INDEX idx_status (status),
                INDEX idx_impact_level (impact_level),
                INDEX idx_created_by (created_by)
            )",
            
            // Tabla de aprobaciones
            "CREATE TABLE IF NOT EXISTS gamp5_change_approvals (
                id INT PRIMARY KEY AUTO_INCREMENT,
                change_request_id INT NOT NULL,
                approval_level ENUM('SUPERVISOR', 'QUALITY_MANAGER', 'TECHNICAL_LEAD', 'REGULATORY_AFFAIRS') NOT NULL,
                approver_id INT NULL,
                approval_status ENUM('PENDING', 'APPROVED', 'REJECTED', 'DELEGATED') NOT NULL DEFAULT 'PENDING',
                approval_date TIMESTAMP NULL,
                comments TEXT,
                conditions TEXT,
                delegated_to INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (change_request_id) REFERENCES gamp5_change_requests(id) ON DELETE CASCADE,
                INDEX idx_change_approval (change_request_id),
                INDEX idx_approval_status (approval_status),
                INDEX idx_approver (approver_id)
            )",
            
            // Tabla de implementación de cambios
            "CREATE TABLE IF NOT EXISTS gamp5_change_implementation (
                id INT PRIMARY KEY AUTO_INCREMENT,
                change_request_id INT NOT NULL,
                implementation_step VARCHAR(255) NOT NULL,
                step_description TEXT,
                step_order INT NOT NULL,
                status ENUM('PENDING', 'IN_PROGRESS', 'COMPLETED', 'FAILED', 'SKIPPED') NOT NULL DEFAULT 'PENDING',
                assigned_to INT NULL,
                planned_start_date TIMESTAMP NULL,
                actual_start_date TIMESTAMP NULL,
                planned_completion_date TIMESTAMP NULL,
                actual_completion_date TIMESTAMP NULL,
                evidence_path VARCHAR(500),
                notes TEXT,
                verification_required BOOLEAN DEFAULT FALSE,
                verified_by INT NULL,
                verified_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (change_request_id) REFERENCES gamp5_change_requests(id) ON DELETE CASCADE,
                INDEX idx_change_implementation (change_request_id),
                INDEX idx_step_status (status),
                INDEX idx_step_order (step_order)
            )",
            
            // Tabla de pruebas de cambios
            "CREATE TABLE IF NOT EXISTS gamp5_change_testing (
                id INT PRIMARY KEY AUTO_INCREMENT,
                change_request_id INT NOT NULL,
                test_case_name VARCHAR(255) NOT NULL,
                test_description TEXT,
                test_category ENUM('UNIT', 'INTEGRATION', 'SYSTEM', 'USER_ACCEPTANCE', 'REGRESSION') NOT NULL,
                expected_result TEXT,
                actual_result TEXT,
                test_status ENUM('NOT_EXECUTED', 'PASSED', 'FAILED', 'BLOCKED', 'IN_PROGRESS') NOT NULL DEFAULT 'NOT_EXECUTED',
                priority ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') NOT NULL DEFAULT 'MEDIUM',
                executed_by INT NULL,
                executed_at TIMESTAMP NULL,
                evidence_path VARCHAR(500),
                defects_found TEXT,
                resolution_notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (change_request_id) REFERENCES gamp5_change_requests(id) ON DELETE CASCADE,
                INDEX idx_change_testing (change_request_id),
                INDEX idx_test_status (test_status),
                INDEX idx_test_category (test_category)
            )",
            
            // Tabla de evaluación de impacto
            "CREATE TABLE IF NOT EXISTS gamp5_change_impact_assessment (
                id INT PRIMARY KEY AUTO_INCREMENT,
                change_request_id INT NOT NULL,
                assessment_area ENUM('FUNCTIONALITY', 'PERFORMANCE', 'SECURITY', 'COMPLIANCE', 'DATA_INTEGRITY', 'USER_INTERFACE', 'INTEGRATION', 'INFRASTRUCTURE') NOT NULL,
                impact_description TEXT,
                impact_severity ENUM('NONE', 'LOW', 'MEDIUM', 'HIGH', 'CRITICAL') NOT NULL,
                mitigation_measures TEXT,
                additional_testing_required BOOLEAN DEFAULT FALSE,
                additional_validation_required BOOLEAN DEFAULT FALSE,
                assessed_by INT NOT NULL,
                assessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                reviewed_by INT NULL,
                reviewed_at TIMESTAMP NULL,
                FOREIGN KEY (change_request_id) REFERENCES gamp5_change_requests(id) ON DELETE CASCADE,
                INDEX idx_change_impact (change_request_id),
                INDEX idx_impact_severity (impact_severity),
                INDEX idx_assessment_area (assessment_area)
            )",
            
            // Tabla de configuraciones y su historial
            "CREATE TABLE IF NOT EXISTS gamp5_configuration_history (
                id INT PRIMARY KEY AUTO_INCREMENT,
                change_request_id INT NOT NULL,
                configuration_item VARCHAR(255) NOT NULL,
                configuration_type ENUM('SYSTEM_PARAMETER', 'USER_PERMISSION', 'SECURITY_SETTING', 'INTERFACE_CONFIG', 'DATABASE_CONFIG', 'NETWORK_CONFIG', 'APPLICATION_CONFIG') NOT NULL,
                previous_value TEXT,
                new_value TEXT,
                change_reason TEXT,
                validation_status ENUM('PENDING', 'VALIDATED', 'FAILED') DEFAULT 'PENDING',
                validated_by INT NULL,
                validated_at TIMESTAMP NULL,
                rollback_possible BOOLEAN DEFAULT TRUE,
                rollback_procedure TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (change_request_id) REFERENCES gamp5_change_requests(id) ON DELETE CASCADE,
                INDEX idx_config_change (change_request_id),
                INDEX idx_config_item (configuration_item),
                INDEX idx_config_type (configuration_type)
            )"
        ];

        foreach ($queries as $query) {
            if (!$this->db->query($query)) {
                error_log("Error creating change management table: " . $this->db->error);
            }
        }
    }

    /**
     * Crea una nueva solicitud de cambio
     */
    public function createChangeRequest($data)
    {
        try {
            // Generar número de cambio único
            $change_number = $this->generateChangeNumber($data['change_type']);
            
            $stmt = $this->db->prepare("
                INSERT INTO gamp5_change_requests 
                (change_number, title, description, business_justification, change_type, 
                 impact_level, urgency, affected_systems, affected_documents, risk_assessment,
                 implementation_plan, rollback_plan, test_plan, validation_required,
                 estimated_effort_hours, estimated_cost, planned_start_date, planned_completion_date,
                 created_by, assigned_to)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $affected_systems = json_encode($data['affected_systems'] ?? []);
            $affected_documents = json_encode($data['affected_documents'] ?? []);
            
            $stmt->bind_param("sssssssssssssiidsiii", 
                $change_number,
                $data['title'],
                $data['description'],
                $data['business_justification'],
                $data['change_type'],
                $data['impact_level'],
                $data['urgency'] ?? 'MEDIUM',
                $affected_systems,
                $affected_documents,
                $data['risk_assessment'] ?? '',
                $data['implementation_plan'] ?? '',
                $data['rollback_plan'] ?? '',
                $data['test_plan'] ?? '',
                $data['validation_required'] ?? false,
                $data['estimated_effort_hours'] ?? null,
                $data['estimated_cost'] ?? null,
                $data['planned_start_date'] ?? null,
                $data['planned_completion_date'] ?? null,
                $this->usuario_id,
                $data['assigned_to'] ?? null
            );
            
            if ($stmt->execute()) {
                $change_request_id = $this->db->insert_id;
                
                // Crear aprobaciones requeridas según el nivel de impacto
                $this->createRequiredApprovals($change_request_id, $data['impact_level']);
                
                // Crear evaluación de impacto inicial
                $this->createInitialImpactAssessment($change_request_id, $data);
                
                $this->logAuditEvent('CHANGE_REQUEST_CREATED', [
                    'change_request_id' => $change_request_id,
                    'change_number' => $change_number,
                    'type' => $data['change_type'],
                    'impact_level' => $data['impact_level']
                ]);
                
                return ['success' => true, 'change_request_id' => $change_request_id, 'change_number' => $change_number];
            }
            
            return ['success' => false, 'error' => 'Error al crear la solicitud de cambio'];
            
        } catch (Exception $e) {
            error_log("Error in createChangeRequest: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error interno del servidor'];
        }
    }

    /**
     * Genera un número único para la solicitud de cambio
     */
    private function generateChangeNumber($change_type)
    {
        $prefix = [
            'CONFIGURATION' => 'CHG-CFG',
            'SOFTWARE_UPDATE' => 'CHG-SW',
            'HARDWARE_CHANGE' => 'CHG-HW',
            'PROCEDURE_CHANGE' => 'CHG-PROC',
            'DOCUMENTATION' => 'CHG-DOC',
            'SECURITY' => 'CHG-SEC',
            'EMERGENCY' => 'CHG-EMG'
        ][$change_type] ?? 'CHG';
        
        $year = date('Y');
        $sequence = $this->getNextSequenceNumber($change_type, $year);
        
        return "{$prefix}-{$year}-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Obtiene el siguiente número de secuencia para el año y tipo
     */
    private function getNextSequenceNumber($change_type, $year)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) + 1 as next_seq
            FROM gamp5_change_requests 
            WHERE change_type = ? AND YEAR(created_at) = ?
        ");
        
        $stmt->bind_param("si", $change_type, $year);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return $result['next_seq'];
    }

    /**
     * Crea las aprobaciones requeridas según el nivel de impacto
     */
    private function createRequiredApprovals($change_request_id, $impact_level)
    {
        $required_levels = self::APPROVAL_LEVELS[$impact_level] ?? ['SUPERVISOR'];
        
        foreach ($required_levels as $level) {
            $stmt = $this->db->prepare("
                INSERT INTO gamp5_change_approvals 
                (change_request_id, approval_level)
                VALUES (?, ?)
            ");
            
            $stmt->bind_param("is", $change_request_id, $level);
            $stmt->execute();
        }
    }

    /**
     * Crea evaluación de impacto inicial
     */
    private function createInitialImpactAssessment($change_request_id, $data)
    {
        $assessment_areas = ['FUNCTIONALITY', 'PERFORMANCE', 'SECURITY', 'COMPLIANCE', 'DATA_INTEGRITY'];
        
        foreach ($assessment_areas as $area) {
            $impact_severity = $this->estimateImpactSeverity($area, $data);
            
            $stmt = $this->db->prepare("
                INSERT INTO gamp5_change_impact_assessment 
                (change_request_id, assessment_area, impact_severity, assessed_by)
                VALUES (?, ?, ?, ?)
            ");
            
            $stmt->bind_param("issi", $change_request_id, $area, $impact_severity, $this->usuario_id);
            $stmt->execute();
        }
    }

    /**
     * Estima la severidad del impacto por área
     */
    private function estimateImpactSeverity($area, $data)
    {
        // Lógica simplificada para estimar impacto - en producción sería más sofisticada
        $base_severity = [
            'BAJO' => 'LOW',
            'MEDIO' => 'MEDIUM', 
            'ALTO' => 'HIGH',
            'CRITICO' => 'CRITICAL'
        ][$data['impact_level']] ?? 'MEDIUM';
        
        // Ajustar según el tipo de cambio y área
        if ($data['change_type'] === 'SECURITY' && $area === 'SECURITY') {
            return 'HIGH';
        }
        
        if ($data['change_type'] === 'CONFIGURATION' && $area === 'DATA_INTEGRITY') {
            return 'MEDIUM';
        }
        
        return $base_severity;
    }

    /**
     * Procesa una aprobación
     */
    public function processApproval($approval_id, $approval_data)
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE gamp5_change_approvals 
                SET approval_status = ?, approver_id = ?, approval_date = NOW(),
                    comments = ?, conditions = ?
                WHERE id = ?
            ");
            
            $stmt->bind_param("sissi", 
                $approval_data['approval_status'],
                $this->usuario_id,
                $approval_data['comments'] ?? '',
                $approval_data['conditions'] ?? '',
                $approval_id
            );
            
            if ($stmt->execute()) {
                // Obtener información de la solicitud de cambio
                $change_request = $this->getChangeRequestByApprovalId($approval_id);
                
                if ($change_request) {
                    // Verificar si todas las aprobaciones están completas
                    $this->checkAndUpdateChangeStatus($change_request['id']);
                    
                    $this->logAuditEvent('CHANGE_APPROVAL_PROCESSED', [
                        'approval_id' => $approval_id,
                        'change_request_id' => $change_request['id'],
                        'approval_status' => $approval_data['approval_status']
                    ]);
                }
                
                return ['success' => true];
            }
            
            return ['success' => false, 'error' => 'Error al procesar la aprobación'];
            
        } catch (Exception $e) {
            error_log("Error in processApproval: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error interno del servidor'];
        }
    }

    /**
     * Verifica y actualiza el estado de la solicitud de cambio
     */
    private function checkAndUpdateChangeStatus($change_request_id)
    {
        // Verificar estado de aprobaciones
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_approvals,
                COUNT(CASE WHEN approval_status = 'APPROVED' THEN 1 END) as approved_count,
                COUNT(CASE WHEN approval_status = 'REJECTED' THEN 1 END) as rejected_count,
                COUNT(CASE WHEN approval_status = 'PENDING' THEN 1 END) as pending_count
            FROM gamp5_change_approvals 
            WHERE change_request_id = ?
        ");
        
        $stmt->bind_param("i", $change_request_id);
        $stmt->execute();
        $approval_stats = $stmt->get_result()->fetch_assoc();
        
        $new_status = null;
        
        if ($approval_stats['rejected_count'] > 0) {
            $new_status = 'REJECTED';
        } elseif ($approval_stats['pending_count'] == 0 && $approval_stats['approved_count'] == $approval_stats['total_approvals']) {
            $new_status = 'APPROVED';
        } elseif ($approval_stats['approved_count'] > 0 || $approval_stats['rejected_count'] > 0) {
            $new_status = 'UNDER_REVIEW';
        }
        
        if ($new_status) {
            $update_stmt = $this->db->prepare("
                UPDATE gamp5_change_requests 
                SET status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $update_stmt->bind_param("si", $new_status, $change_request_id);
            $update_stmt->execute();
            
            // Si está aprobado, crear pasos de implementación
            if ($new_status === 'APPROVED') {
                $this->createImplementationSteps($change_request_id);
            }
        }
    }

    /**
     * Crea pasos de implementación para un cambio aprobado
     */
    private function createImplementationSteps($change_request_id)
    {
        // Obtener detalles de la solicitud de cambio
        $change_request = $this->getChangeRequestDetails($change_request_id);
        if (!$change_request['success']) return;
        
        $data = $change_request['data'];
        
        // Crear pasos basados en el tipo de cambio
        $steps = $this->getImplementationStepsForType($data['change_type']);
        
        foreach ($steps as $index => $step) {
            $stmt = $this->db->prepare("
                INSERT INTO gamp5_change_implementation 
                (change_request_id, implementation_step, step_description, step_order, verification_required)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $step_order = $index + 1;
            $stmt->bind_param("issii", 
                $change_request_id,
                $step['name'],
                $step['description'],
                $step_order,
                $step['verification_required'] ?? false
            );
            
            $stmt->execute();
        }
        
        // Crear casos de prueba si es necesario
        if ($data['validation_required'] ?? false) {
            $this->createTestCases($change_request_id, $data['change_type']);
        }
    }

    /**
     * Obtiene pasos de implementación por tipo de cambio
     */
    private function getImplementationStepsForType($change_type)
    {
        $steps = [
            'CONFIGURATION' => [
                ['name' => 'Backup Current Configuration', 'description' => 'Crear respaldo de la configuración actual', 'verification_required' => true],
                ['name' => 'Apply Configuration Changes', 'description' => 'Aplicar los cambios de configuración', 'verification_required' => true],
                ['name' => 'Verify Configuration', 'description' => 'Verificar que la configuración se aplicó correctamente', 'verification_required' => true],
                ['name' => 'Update Documentation', 'description' => 'Actualizar documentación de configuración', 'verification_required' => false]
            ],
            'SOFTWARE_UPDATE' => [
                ['name' => 'System Backup', 'description' => 'Crear respaldo completo del sistema', 'verification_required' => true],
                ['name' => 'Deploy Software Update', 'description' => 'Desplegar actualización de software', 'verification_required' => true],
                ['name' => 'System Testing', 'description' => 'Ejecutar pruebas del sistema', 'verification_required' => true],
                ['name' => 'User Acceptance Testing', 'description' => 'Pruebas de aceptación de usuario', 'verification_required' => true],
                ['name' => 'Documentation Update', 'description' => 'Actualizar documentación del sistema', 'verification_required' => false]
            ],
            'SECURITY' => [
                ['name' => 'Security Assessment', 'description' => 'Evaluación de seguridad pre-cambio', 'verification_required' => true],
                ['name' => 'Implement Security Changes', 'description' => 'Implementar cambios de seguridad', 'verification_required' => true],
                ['name' => 'Security Testing', 'description' => 'Pruebas de seguridad', 'verification_required' => true],
                ['name' => 'Security Validation', 'description' => 'Validación de controles de seguridad', 'verification_required' => true],
                ['name' => 'Security Documentation', 'description' => 'Actualizar documentación de seguridad', 'verification_required' => true]
            ]
        ];
        
        return $steps[$change_type] ?? [
            ['name' => 'Plan Implementation', 'description' => 'Planificar implementación del cambio', 'verification_required' => false],
            ['name' => 'Execute Change', 'description' => 'Ejecutar el cambio', 'verification_required' => true],
            ['name' => 'Verify Change', 'description' => 'Verificar que el cambio fue exitoso', 'verification_required' => true],
            ['name' => 'Update Documentation', 'description' => 'Actualizar documentación relevante', 'verification_required' => false]
        ];
    }

    /**
     * Crea casos de prueba para validación
     */
    private function createTestCases($change_request_id, $change_type)
    {
        $test_cases = [
            'CONFIGURATION' => [
                ['name' => 'Configuration Integrity Test', 'category' => 'SYSTEM', 'description' => 'Verificar integridad de la configuración'],
                ['name' => 'Functionality Test', 'category' => 'USER_ACCEPTANCE', 'description' => 'Verificar funcionalidad después del cambio']
            ],
            'SOFTWARE_UPDATE' => [
                ['name' => 'Regression Test Suite', 'category' => 'REGRESSION', 'description' => 'Suite completa de pruebas de regresión'],
                ['name' => 'New Features Test', 'category' => 'SYSTEM', 'description' => 'Pruebas de nuevas características'],
                ['name' => 'Integration Test', 'category' => 'INTEGRATION', 'description' => 'Pruebas de integración con otros sistemas']
            ],
            'SECURITY' => [
                ['name' => 'Security Controls Test', 'category' => 'SYSTEM', 'description' => 'Verificar controles de seguridad'],
                ['name' => 'Access Control Test', 'category' => 'USER_ACCEPTANCE', 'description' => 'Verificar controles de acceso'],
                ['name' => 'Penetration Test', 'category' => 'SYSTEM', 'description' => 'Pruebas de penetración limitadas']
            ]
        ];
        
        $cases = $test_cases[$change_type] ?? [
            ['name' => 'Basic Functionality Test', 'category' => 'USER_ACCEPTANCE', 'description' => 'Verificar funcionalidad básica del sistema']
        ];
        
        foreach ($cases as $case) {
            $stmt = $this->db->prepare("
                INSERT INTO gamp5_change_testing 
                (change_request_id, test_case_name, test_description, test_category, priority)
                VALUES (?, ?, ?, ?, 'HIGH')
            ");
            
            $stmt->bind_param("isss", 
                $change_request_id,
                $case['name'],
                $case['description'],
                $case['category']
            );
            
            $stmt->execute();
        }
    }

    /**
     * Implementa un paso de cambio
     */
    public function implementStep($step_id, $implementation_data)
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE gamp5_change_implementation 
                SET status = ?, actual_start_date = ?, actual_completion_date = ?,
                    evidence_path = ?, notes = ?
                WHERE id = ?
            ");
            
            $start_date = ($implementation_data['status'] === 'IN_PROGRESS') ? date('Y-m-d H:i:s') : null;
            $completion_date = ($implementation_data['status'] === 'COMPLETED') ? date('Y-m-d H:i:s') : null;
            
            $stmt->bind_param("sssssi", 
                $implementation_data['status'],
                $start_date,
                $completion_date,
                $implementation_data['evidence_path'] ?? '',
                $implementation_data['notes'] ?? '',
                $step_id
            );
            
            if ($stmt->execute()) {
                // Si requiere verificación y está completado, marcarlo para verificación
                if ($implementation_data['status'] === 'COMPLETED') {
                    $this->checkStepVerificationRequirement($step_id);
                }
                
                // Verificar si todos los pasos están completados
                $this->checkImplementationCompletion($step_id);
                
                $this->logAuditEvent('IMPLEMENTATION_STEP_UPDATED', [
                    'step_id' => $step_id,
                    'status' => $implementation_data['status']
                ]);
                
                return ['success' => true];
            }
            
            return ['success' => false, 'error' => 'Error al actualizar el paso de implementación'];
            
        } catch (Exception $e) {
            error_log("Error in implementStep: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error interno del servidor'];
        }
    }

    /**
     * Verifica si un paso requiere verificación
     */
    private function checkStepVerificationRequirement($step_id)
    {
        $stmt = $this->db->prepare("
            SELECT verification_required, change_request_id
            FROM gamp5_change_implementation 
            WHERE id = ? AND verification_required = TRUE
        ");
        
        $stmt->bind_param("i", $step_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result) {
            // Crear notificación o tarea de verificación
            $this->logAuditEvent('VERIFICATION_REQUIRED', [
                'step_id' => $step_id,
                'change_request_id' => $result['change_request_id']
            ]);
        }
    }

    /**
     * Verifica si la implementación está completa
     */
    private function checkImplementationCompletion($step_id)
    {
        // Obtener ID de la solicitud de cambio
        $stmt = $this->db->prepare("SELECT change_request_id FROM gamp5_change_implementation WHERE id = ?");
        $stmt->bind_param("i", $step_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if (!$result) return;
        
        $change_request_id = $result['change_request_id'];
        
        // Verificar si todos los pasos están completados
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_steps,
                COUNT(CASE WHEN status = 'COMPLETED' THEN 1 END) as completed_steps,
                COUNT(CASE WHEN status = 'FAILED' THEN 1 END) as failed_steps
            FROM gamp5_change_implementation 
            WHERE change_request_id = ?
        ");
        
        $stmt->bind_param("i", $change_request_id);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();
        
        if ($stats['total_steps'] == $stats['completed_steps'] && $stats['failed_steps'] == 0) {
            // Todos los pasos completados exitosamente
            $this->updateChangeRequestStatus($change_request_id, 'TESTING');
        } elseif ($stats['failed_steps'] > 0) {
            // Hay pasos fallidos
            $this->updateChangeRequestStatus($change_request_id, 'IMPLEMENTATION');
        }
    }

    /**
     * Actualiza el estado de una solicitud de cambio
     */
    private function updateChangeRequestStatus($change_request_id, $status)
    {
        $stmt = $this->db->prepare("
            UPDATE gamp5_change_requests 
            SET status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->bind_param("si", $status, $change_request_id);
        $stmt->execute();
    }

    /**
     * Registra un cambio de configuración
     */
    public function recordConfigurationChange($change_request_id, $config_data)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO gamp5_configuration_history 
                (change_request_id, configuration_item, configuration_type, 
                 previous_value, new_value, change_reason, rollback_procedure)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param("issssss", 
                $change_request_id,
                $config_data['configuration_item'],
                $config_data['configuration_type'],
                $config_data['previous_value'],
                $config_data['new_value'],
                $config_data['change_reason'],
                $config_data['rollback_procedure'] ?? ''
            );
            
            if ($stmt->execute()) {
                $config_history_id = $this->db->insert_id;
                
                $this->logAuditEvent('CONFIGURATION_CHANGE_RECORDED', [
                    'config_history_id' => $config_history_id,
                    'change_request_id' => $change_request_id,
                    'configuration_item' => $config_data['configuration_item']
                ]);
                
                return ['success' => true, 'config_history_id' => $config_history_id];
            }
            
            return ['success' => false, 'error' => 'Error al registrar el cambio de configuración'];
            
        } catch (Exception $e) {
            error_log("Error in recordConfigurationChange: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error interno del servidor'];
        }
    }

    /**
     * Obtiene detalles de una solicitud de cambio
     */
    public function getChangeRequestDetails($change_request_id)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    cr.*,
                    u1.nombre as created_by_name,
                    u2.nombre as assigned_to_name
                FROM gamp5_change_requests cr
                LEFT JOIN usuarios u1 ON cr.created_by = u1.id  
                LEFT JOIN usuarios u2 ON cr.assigned_to = u2.id
                WHERE cr.id = ?
            ");
            
            $stmt->bind_param("i", $change_request_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if ($result) {
                $result['affected_systems'] = json_decode($result['affected_systems'], true);
                $result['affected_documents'] = json_decode($result['affected_documents'], true);
                return ['success' => true, 'data' => $result];
            }
            
            return ['success' => false, 'error' => 'Solicitud de cambio no encontrada'];
            
        } catch (Exception $e) {
            error_log("Error in getChangeRequestDetails: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error interno del servidor'];
        }
    }

    /**
     * Obtiene solicitud de cambio por ID de aprobación
     */
    private function getChangeRequestByApprovalId($approval_id)
    {
        $stmt = $this->db->prepare("
            SELECT cr.id, cr.change_number, cr.title, cr.status
            FROM gamp5_change_requests cr
            JOIN gamp5_change_approvals ca ON cr.id = ca.change_request_id
            WHERE ca.id = ?
        ");
        
        $stmt->bind_param("i", $approval_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return $result;
    }

    /**
     * Obtiene todas las solicitudes de cambio con filtros
     */
    public function getAllChangeRequests($filters = [])
    {
        try {
            $where_conditions = ['1=1'];
            $params = [];
            $types = '';
            
            if (!empty($filters['status'])) {
                $where_conditions[] = 'cr.status = ?';
                $params[] = $filters['status'];
                $types .= 's';
            }
            
            if (!empty($filters['change_type'])) {
                $where_conditions[] = 'cr.change_type = ?';
                $params[] = $filters['change_type'];
                $types .= 's';
            }
            
            if (!empty($filters['impact_level'])) {
                $where_conditions[] = 'cr.impact_level = ?';
                $params[] = $filters['impact_level'];
                $types .= 's';
            }
            
            if (!empty($filters['created_by'])) {
                $where_conditions[] = 'cr.created_by = ?';
                $params[] = $filters['created_by'];
                $types .= 'i';
            }
            
            $sql = "
                SELECT 
                    cr.*,
                    u1.nombre as created_by_name,
                    u2.nombre as assigned_to_name,
                    COUNT(ca.id) as total_approvals,
                    COUNT(CASE WHEN ca.approval_status = 'APPROVED' THEN 1 END) as approved_count,
                    COUNT(CASE WHEN ca.approval_status = 'PENDING' THEN 1 END) as pending_approvals
                FROM gamp5_change_requests cr
                LEFT JOIN usuarios u1 ON cr.created_by = u1.id
                LEFT JOIN usuarios u2 ON cr.assigned_to = u2.id
                LEFT JOIN gamp5_change_approvals ca ON cr.id = ca.change_request_id
                WHERE " . implode(' AND ', $where_conditions) . "
                GROUP BY cr.id
                ORDER BY cr.created_at DESC
            ";
            
            $stmt = $this->db->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $change_requests = [];
            while ($row = $result->fetch_assoc()) {
                $row['affected_systems'] = json_decode($row['affected_systems'], true);
                $row['affected_documents'] = json_decode($row['affected_documents'], true);
                $change_requests[] = $row;
            }
            
            return ['success' => true, 'data' => $change_requests];
            
        } catch (Exception $e) {
            error_log("Error in getAllChangeRequests: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al obtener las solicitudes de cambio'];
        }
    }

    /**
     * Genera reporte de gestión de cambios
     */
    public function generateChangeManagementReport($date_from, $date_to, $report_type = 'summary')
    {
        try {
            $report_data = [
                'period' => ['from' => $date_from, 'to' => $date_to],
                'summary' => $this->getChangesSummary($date_from, $date_to),
                'by_type' => $this->getChangesByType($date_from, $date_to),
                'by_status' => $this->getChangesByStatus($date_from, $date_to),
                'by_impact' => $this->getChangesByImpact($date_from, $date_to),
                'approval_metrics' => $this->getApprovalMetrics($date_from, $date_to),
                'implementation_metrics' => $this->getImplementationMetrics($date_from, $date_to)
            ];
            
            if ($report_type === 'detailed') {
                $report_data['detailed_changes'] = $this->getDetailedChanges($date_from, $date_to);
            }
            
            return ['success' => true, 'data' => $report_data];
            
        } catch (Exception $e) {
            error_log("Error in generateChangeManagementReport: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al generar el reporte'];
        }
    }

    /**
     * Métodos auxiliares para reportes
     */
    private function getChangesSummary($date_from, $date_to) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_changes,
                COUNT(CASE WHEN status = 'COMPLETED' THEN 1 END) as completed_changes,
                COUNT(CASE WHEN status = 'REJECTED' THEN 1 END) as rejected_changes,
                COUNT(CASE WHEN status IN ('DRAFT', 'SUBMITTED', 'UNDER_REVIEW', 'APPROVED', 'IMPLEMENTATION', 'TESTING') THEN 1 END) as active_changes,
                AVG(CASE WHEN actual_completion_date IS NOT NULL THEN DATEDIFF(actual_completion_date, actual_start_date) END) as avg_implementation_days
            FROM gamp5_change_requests
            WHERE created_at BETWEEN ? AND ?
        ");
        
        $stmt->bind_param("ss", $date_from, $date_to);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    private function getChangesByType($date_from, $date_to) {
        $stmt = $this->db->prepare("
            SELECT change_type, COUNT(*) as count
            FROM gamp5_change_requests
            WHERE created_at BETWEEN ? AND ?
            GROUP BY change_type
        ");
        
        $stmt->bind_param("ss", $date_from, $date_to);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[$row['change_type']] = $row['count'];
        }
        return $data;
    }

    private function getChangesByStatus($date_from, $date_to) {
        $stmt = $this->db->prepare("
            SELECT status, COUNT(*) as count
            FROM gamp5_change_requests
            WHERE created_at BETWEEN ? AND ?
            GROUP BY status
        ");
        
        $stmt->bind_param("ss", $date_from, $date_to);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[$row['status']] = $row['count'];
        }
        return $data;
    }

    private function getChangesByImpact($date_from, $date_to) {
        $stmt = $this->db->prepare("
            SELECT impact_level, COUNT(*) as count
            FROM gamp5_change_requests
            WHERE created_at BETWEEN ? AND ?
            GROUP BY impact_level
        ");
        
        $stmt->bind_param("ss", $date_from, $date_to);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[$row['impact_level']] = $row['count'];
        }
        return $data;
    }

    private function getApprovalMetrics($date_from, $date_to) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_approvals,
                COUNT(CASE WHEN approval_status = 'APPROVED' THEN 1 END) as approved_count,
                COUNT(CASE WHEN approval_status = 'REJECTED' THEN 1 END) as rejected_count,
                COUNT(CASE WHEN approval_status = 'PENDING' THEN 1 END) as pending_count,
                AVG(CASE WHEN approval_date IS NOT NULL THEN DATEDIFF(approval_date, ca.created_at) END) as avg_approval_days
            FROM gamp5_change_approvals ca
            JOIN gamp5_change_requests cr ON ca.change_request_id = cr.id
            WHERE cr.created_at BETWEEN ? AND ?
        ");
        
        $stmt->bind_param("ss", $date_from, $date_to);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    private function getImplementationMetrics($date_from, $date_to) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_steps,
                COUNT(CASE WHEN status = 'COMPLETED' THEN 1 END) as completed_steps,
                COUNT(CASE WHEN status = 'FAILED' THEN 1 END) as failed_steps,
                AVG(CASE WHEN actual_completion_date IS NOT NULL THEN DATEDIFF(actual_completion_date, actual_start_date) END) as avg_step_days
            FROM gamp5_change_implementation ci
            JOIN gamp5_change_requests cr ON ci.change_request_id = cr.id
            WHERE cr.created_at BETWEEN ? AND ?
        ");
        
        $stmt->bind_param("ss", $date_from, $date_to);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    private function getDetailedChanges($date_from, $date_to) {
        $stmt = $this->db->prepare("
            SELECT cr.*, u.nombre as created_by_name
            FROM gamp5_change_requests cr
            LEFT JOIN usuarios u ON cr.created_by = u.id
            WHERE cr.created_at BETWEEN ? AND ?
            ORDER BY cr.created_at DESC
        ");
        
        $stmt->bind_param("ss", $date_from, $date_to);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $changes = [];
        while ($row = $result->fetch_assoc()) {
            $row['affected_systems'] = json_decode($row['affected_systems'], true);
            $row['affected_documents'] = json_decode($row['affected_documents'], true);
            $changes[] = $row;
        }
        return $changes;
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
                'modulo' => 'GAMP5_CHANGE_MANAGEMENT'
            ];
            
            error_log("GAMP5 Change Management Audit Event: " . json_encode($audit_log));
            
        } catch (Exception $e) {
            error_log("Error logging change management audit event: " . $e->getMessage());
        }
    }
}