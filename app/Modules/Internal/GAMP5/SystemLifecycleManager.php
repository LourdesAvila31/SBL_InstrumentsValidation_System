<?php
/**
 * Sistema de Gestión del Ciclo de Vida conforme a GAMP 5
 * 
 * Esta clase gestiona el ciclo de vida completo del sistema computarizado,
 * cubriendo las tres etapas principales de GAMP 5:
 * 1. Diseño del proceso
 * 2. Calificación del proceso  
 * 3. Verificación continua del proceso
 */

require_once dirname(__DIR__, 3) . '/Core/db.php';
require_once dirname(__DIR__, 3) . '/Core/permissions.php';

class SystemLifecycleManager
{
    private $db;
    private $usuario_id;
    
    // Estados del ciclo de vida según GAMP 5
    const LIFECYCLE_STAGES = [
        'PLANNING' => 'Planificación',
        'DESIGN' => 'Diseño del Proceso',
        'DEVELOPMENT' => 'Desarrollo',
        'TESTING' => 'Pruebas',
        'QUALIFICATION' => 'Calificación del Proceso',
        'DEPLOYMENT' => 'Implementación',
        'OPERATION' => 'Operación',
        'MAINTENANCE' => 'Mantenimiento',
        'RETIREMENT' => 'Retiro'
    ];
    
    const LIFECYCLE_STATUS = [
        'NOT_STARTED' => 'No Iniciado',
        'IN_PROGRESS' => 'En Progreso',
        'COMPLETED' => 'Completado',
        'ON_HOLD' => 'En Espera',
        'CANCELLED' => 'Cancelado',
        'UNDER_REVIEW' => 'En Revisión'
    ];

    public function __construct($usuario_id = null)
    {
        $this->db = DatabaseManager::getConnection();
        $this->usuario_id = $usuario_id ?? $_SESSION['usuario_id'] ?? null;
        $this->initializeTables();
    }

    /**
     * Inicializa las tablas necesarias para la gestión del ciclo de vida
     */
    private function initializeTables()
    {
        $queries = [
            // Tabla principal del ciclo de vida del sistema
            "CREATE TABLE IF NOT EXISTS gamp5_system_lifecycle (
                id INT PRIMARY KEY AUTO_INCREMENT,
                system_name VARCHAR(255) NOT NULL,
                system_version VARCHAR(50) NOT NULL,
                current_stage ENUM('PLANNING', 'DESIGN', 'DEVELOPMENT', 'TESTING', 'QUALIFICATION', 'DEPLOYMENT', 'OPERATION', 'MAINTENANCE', 'RETIREMENT') NOT NULL DEFAULT 'PLANNING',
                status ENUM('NOT_STARTED', 'IN_PROGRESS', 'COMPLETED', 'ON_HOLD', 'CANCELLED', 'UNDER_REVIEW') NOT NULL DEFAULT 'NOT_STARTED',
                description TEXT,
                regulatory_requirements JSON,
                risk_classification ENUM('Category 1', 'Category 3', 'Category 4', 'Category 5') NOT NULL,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                completed_at TIMESTAMP NULL,
                INDEX idx_system_stage (current_stage),
                INDEX idx_status (status),
                INDEX idx_created_by (created_by)
            )",
            
            // Tabla de etapas del ciclo de vida con detalles específicos
            "CREATE TABLE IF NOT EXISTS gamp5_lifecycle_stages (
                id INT PRIMARY KEY AUTO_INCREMENT,
                lifecycle_id INT NOT NULL,
                stage ENUM('PLANNING', 'DESIGN', 'DEVELOPMENT', 'TESTING', 'QUALIFICATION', 'DEPLOYMENT', 'OPERATION', 'MAINTENANCE', 'RETIREMENT') NOT NULL,
                status ENUM('NOT_STARTED', 'IN_PROGRESS', 'COMPLETED', 'ON_HOLD', 'CANCELLED', 'UNDER_REVIEW') NOT NULL DEFAULT 'NOT_STARTED',
                start_date TIMESTAMP NULL,
                end_date TIMESTAMP NULL,
                planned_duration INT, -- días
                actual_duration INT, -- días
                deliverables JSON,
                acceptance_criteria JSON,
                validation_report TEXT,
                approved_by INT NULL,
                approved_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (lifecycle_id) REFERENCES gamp5_system_lifecycle(id) ON DELETE CASCADE,
                INDEX idx_lifecycle_stage (lifecycle_id, stage),
                INDEX idx_stage_status (stage, status)
            )",
            
            // Tabla de documentos y entregables por etapa
            "CREATE TABLE IF NOT EXISTS gamp5_lifecycle_documents (
                id INT PRIMARY KEY AUTO_INCREMENT,
                stage_id INT NOT NULL,
                document_type ENUM('URS', 'FRS', 'DS', 'IQ', 'OQ', 'PQ', 'VALIDATION_REPORT', 'CHANGE_CONTROL', 'RISK_ASSESSMENT', 'SOP', 'OTHER') NOT NULL,
                document_name VARCHAR(255) NOT NULL,
                document_path VARCHAR(500),
                version VARCHAR(20) NOT NULL DEFAULT '1.0',
                status ENUM('DRAFT', 'UNDER_REVIEW', 'APPROVED', 'OBSOLETE') NOT NULL DEFAULT 'DRAFT',
                created_by INT NOT NULL,
                reviewed_by INT NULL,
                approved_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (stage_id) REFERENCES gamp5_lifecycle_stages(id) ON DELETE CASCADE,
                INDEX idx_document_type (document_type),
                INDEX idx_document_status (status)
            )",
            
            // Tabla de actividades de verificación continua
            "CREATE TABLE IF NOT EXISTS gamp5_continuous_verification (
                id INT PRIMARY KEY AUTO_INCREMENT,
                lifecycle_id INT NOT NULL,
                verification_type ENUM('PERIODIC_REVIEW', 'CHANGE_IMPACT', 'PERFORMANCE_MONITORING', 'COMPLIANCE_CHECK', 'SECURITY_AUDIT') NOT NULL,
                description TEXT NOT NULL,
                scheduled_date TIMESTAMP NOT NULL,
                completed_date TIMESTAMP NULL,
                status ENUM('SCHEDULED', 'IN_PROGRESS', 'COMPLETED', 'OVERDUE', 'CANCELLED') NOT NULL DEFAULT 'SCHEDULED',
                findings TEXT,
                corrective_actions JSON,
                performed_by INT NULL,
                reviewed_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (lifecycle_id) REFERENCES gamp5_system_lifecycle(id) ON DELETE CASCADE,
                INDEX idx_verification_type (verification_type),
                INDEX idx_scheduled_date (scheduled_date),
                INDEX idx_status (status)
            )"
        ];

        foreach ($queries as $query) {
            if (!$this->db->query($query)) {
                error_log("Error creating GAMP5 table: " . $this->db->error);
            }
        }
    }

    /**
     * Crea un nuevo sistema en el ciclo de vida GAMP 5
     */
    public function createSystemLifecycle($data)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO gamp5_system_lifecycle 
                (system_name, system_version, description, regulatory_requirements, risk_classification, created_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $regulatory_requirements = json_encode($data['regulatory_requirements'] ?? []);
            
            $stmt->bind_param("sssssi", 
                $data['system_name'],
                $data['system_version'],
                $data['description'],
                $regulatory_requirements,
                $data['risk_classification'],
                $this->usuario_id
            );
            
            if ($stmt->execute()) {
                $lifecycle_id = $this->db->insert_id;
                
                // Crear todas las etapas del ciclo de vida
                $this->initializeLifecycleStages($lifecycle_id);
                
                // Registrar en auditoría
                $this->logAuditEvent('SYSTEM_LIFECYCLE_CREATED', [
                    'lifecycle_id' => $lifecycle_id,
                    'system_name' => $data['system_name'],
                    'risk_classification' => $data['risk_classification']
                ]);
                
                return ['success' => true, 'lifecycle_id' => $lifecycle_id];
            }
            
            return ['success' => false, 'error' => 'Error al crear el ciclo de vida del sistema'];
            
        } catch (Exception $e) {
            error_log("Error in createSystemLifecycle: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error interno del servidor'];
        }
    }

    /**
     * Inicializa todas las etapas del ciclo de vida para un sistema
     */
    private function initializeLifecycleStages($lifecycle_id)
    {
        $stages = array_keys(self::LIFECYCLE_STAGES);
        
        foreach ($stages as $stage) {
            $deliverables = $this->getStageDeliverables($stage);
            $acceptance_criteria = $this->getStageAcceptanceCriteria($stage);
            
            $stmt = $this->db->prepare("
                INSERT INTO gamp5_lifecycle_stages 
                (lifecycle_id, stage, deliverables, acceptance_criteria)
                VALUES (?, ?, ?, ?)
            ");
            
            $deliverables_json = json_encode($deliverables);
            $criteria_json = json_encode($acceptance_criteria);
            
            $stmt->bind_param("isss", $lifecycle_id, $stage, $deliverables_json, $criteria_json);
            $stmt->execute();
        }
    }

    /**
     * Obtiene los entregables requeridos para cada etapa según GAMP 5
     */
    private function getStageDeliverables($stage)
    {
        $deliverables = [
            'PLANNING' => [
                'Project Plan',
                'Risk Assessment',
                'Resource Allocation',
                'Timeline Definition'
            ],
            'DESIGN' => [
                'User Requirements Specification (URS)',
                'Functional Requirements Specification (FRS)',
                'Design Specification (DS)',
                'Risk Assessment Update'
            ],
            'DEVELOPMENT' => [
                'System Development',
                'Code Review Documentation',
                'Unit Testing Results',
                'Integration Testing Plan'
            ],
            'TESTING' => [
                'Test Protocols',
                'Test Execution Records',
                'Test Summary Report',
                'Defect Resolution Log'
            ],
            'QUALIFICATION' => [
                'Installation Qualification (IQ)',
                'Operational Qualification (OQ)',
                'Performance Qualification (PQ)',
                'Qualification Summary Report'
            ],
            'DEPLOYMENT' => [
                'Deployment Plan',
                'System Backup',
                'User Training Records',
                'Go-Live Checklist'
            ],
            'OPERATION' => [
                'Standard Operating Procedures (SOPs)',
                'Change Control Procedures',
                'Maintenance Procedures',
                'Incident Response Plan'
            ],
            'MAINTENANCE' => [
                'Maintenance Schedule',
                'Performance Monitoring Reports',
                'Change Control Records',
                'Periodic Review Reports'
            ],
            'RETIREMENT' => [
                'Retirement Plan',
                'Data Migration/Archive Plan',
                'System Decommissioning Report',
                'Final Documentation Archive'
            ]
        ];
        
        return $deliverables[$stage] ?? [];
    }

    /**
     * Obtiene los criterios de aceptación para cada etapa
     */
    private function getStageAcceptanceCriteria($stage)
    {
        $criteria = [
            'PLANNING' => [
                'Project scope clearly defined',
                'Resources allocated and available',
                'Timeline approved by stakeholders',
                'Risk assessment completed and approved'
            ],
            'DESIGN' => [
                'URS approved by business users',
                'FRS technically feasible and complete',
                'Design meets regulatory requirements',
                'Traceability matrix established'
            ],
            'DEVELOPMENT' => [
                'Code follows development standards',
                'All modules unit tested',
                'Code review completed',
                'Integration testing successful'
            ],
            'TESTING' => [
                'All test protocols executed',
                'Test coverage meets requirements',
                'Critical defects resolved',
                'Test summary report approved'
            ],
            'QUALIFICATION' => [
                'IQ protocol executed successfully',
                'OQ demonstrates system functionality',
                'PQ proves system performance',
                'All qualification protocols approved'
            ],
            'DEPLOYMENT' => [
                'System deployed successfully',
                'User training completed',
                'Production data verified',
                'System operational and monitored'
            ],
            'OPERATION' => [
                'SOPs in place and followed',
                'System performance within specifications',
                'Change control process active',
                'Regular monitoring in place'
            ],
            'MAINTENANCE' => [
                'Maintenance activities scheduled',
                'System performance maintained',
                'Changes properly controlled',
                'Periodic reviews conducted'
            ],
            'RETIREMENT' => [
                'Data successfully migrated/archived',
                'System safely decommissioned',
                'Documentation archived',
                'Regulatory notifications completed'
            ]
        ];
        
        return $criteria[$stage] ?? [];
    }

    /**
     * Actualiza el estado de una etapa del ciclo de vida
     */
    public function updateStageStatus($stage_id, $status, $validation_report = null)
    {
        try {
            $updates = ['status = ?'];
            $params = [$status];
            $types = 's';
            
            if ($status === 'IN_PROGRESS' && !$this->getStageStartDate($stage_id)) {
                $updates[] = 'start_date = NOW()';
            }
            
            if ($status === 'COMPLETED') {
                $updates[] = 'end_date = NOW()';
                if ($validation_report) {
                    $updates[] = 'validation_report = ?';
                    $params[] = $validation_report;
                    $types .= 's';
                }
            }
            
            $sql = "UPDATE gamp5_lifecycle_stages SET " . implode(', ', $updates) . " WHERE id = ?";
            $params[] = $stage_id;
            $types .= 'i';
            
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                // Verificar si se debe avanzar al siguiente estado del sistema
                $this->checkAndUpdateSystemStage($stage_id);
                
                // Registrar en auditoría
                $this->logAuditEvent('STAGE_STATUS_UPDATED', [
                    'stage_id' => $stage_id,
                    'new_status' => $status
                ]);
                
                return ['success' => true];
            }
            
            return ['success' => false, 'error' => 'Error al actualizar el estado de la etapa'];
            
        } catch (Exception $e) {
            error_log("Error in updateStageStatus: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error interno del servidor'];
        }
    }

    /**
     * Verifica si el sistema debe avanzar a la siguiente etapa
     */
    private function checkAndUpdateSystemStage($stage_id)
    {
        // Obtener información de la etapa actual
        $stmt = $this->db->prepare("
            SELECT ls.lifecycle_id, ls.stage, ls.status, sl.current_stage
            FROM gamp5_lifecycle_stages ls
            JOIN gamp5_system_lifecycle sl ON ls.lifecycle_id = sl.id
            WHERE ls.id = ?
        ");
        $stmt->bind_param("i", $stage_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result && $result['status'] === 'COMPLETED') {
            $stages = array_keys(self::LIFECYCLE_STAGES);
            $current_index = array_search($result['stage'], $stages);
            
            // Si hay una siguiente etapa y la actual está completa
            if ($current_index !== false && isset($stages[$current_index + 1])) {
                $next_stage = $stages[$current_index + 1];
                
                // Actualizar el sistema a la siguiente etapa
                $update_stmt = $this->db->prepare("
                    UPDATE gamp5_system_lifecycle 
                    SET current_stage = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $update_stmt->bind_param("si", $next_stage, $result['lifecycle_id']);
                $update_stmt->execute();
            }
        }
    }

    /**
     * Obtiene la fecha de inicio de una etapa
     */
    private function getStageStartDate($stage_id)
    {
        $stmt = $this->db->prepare("SELECT start_date FROM gamp5_lifecycle_stages WHERE id = ?");
        $stmt->bind_param("i", $stage_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result['start_date'] ?? null;
    }

    /**
     * Obtiene todos los sistemas en el ciclo de vida
     */
    public function getAllSystemLifecycles($filters = [])
    {
        try {
            $where_conditions = ['1=1'];
            $params = [];
            $types = '';
            
            if (!empty($filters['status'])) {
                $where_conditions[] = 'sl.status = ?';
                $params[] = $filters['status'];
                $types .= 's';
            }
            
            if (!empty($filters['stage'])) {
                $where_conditions[] = 'sl.current_stage = ?';
                $params[] = $filters['stage'];
                $types .= 's';
            }
            
            if (!empty($filters['risk_classification'])) {
                $where_conditions[] = 'sl.risk_classification = ?';
                $params[] = $filters['risk_classification'];
                $types .= 's';
            }
            
            $sql = "
                SELECT 
                    sl.*,
                    u.nombre as created_by_name,
                    COUNT(CASE WHEN ls.status = 'COMPLETED' THEN 1 END) as completed_stages,
                    COUNT(ls.id) as total_stages,
                    ROUND((COUNT(CASE WHEN ls.status = 'COMPLETED' THEN 1 END) / COUNT(ls.id)) * 100, 2) as completion_percentage
                FROM gamp5_system_lifecycle sl
                LEFT JOIN usuarios u ON sl.created_by = u.id
                LEFT JOIN gamp5_lifecycle_stages ls ON sl.id = ls.lifecycle_id
                WHERE " . implode(' AND ', $where_conditions) . "
                GROUP BY sl.id
                ORDER BY sl.created_at DESC
            ";
            
            $stmt = $this->db->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $systems = [];
            while ($row = $result->fetch_assoc()) {
                $row['regulatory_requirements'] = json_decode($row['regulatory_requirements'], true);
                $systems[] = $row;
            }
            
            return ['success' => true, 'data' => $systems];
            
        } catch (Exception $e) {
            error_log("Error in getAllSystemLifecycles: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al obtener los sistemas'];
        }
    }

    /**
     * Obtiene las etapas de un sistema específico
     */
    public function getSystemStages($lifecycle_id)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    ls.*,
                    u1.nombre as approved_by_name,
                    COUNT(ld.id) as document_count,
                    COUNT(CASE WHEN ld.status = 'APPROVED' THEN 1 END) as approved_documents
                FROM gamp5_lifecycle_stages ls
                LEFT JOIN usuarios u1 ON ls.approved_by = u1.id
                LEFT JOIN gamp5_lifecycle_documents ld ON ls.id = ld.stage_id
                WHERE ls.lifecycle_id = ?
                GROUP BY ls.id
                ORDER BY FIELD(ls.stage, " . implode(',', array_map(function($s) { return "'$s'"; }, array_keys(self::LIFECYCLE_STAGES))) . ")
            ");
            
            $stmt->bind_param("i", $lifecycle_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $stages = [];
            while ($row = $result->fetch_assoc()) {
                $row['deliverables'] = json_decode($row['deliverables'], true);
                $row['acceptance_criteria'] = json_decode($row['acceptance_criteria'], true);
                $stages[] = $row;
            }
            
            return ['success' => true, 'data' => $stages];
            
        } catch (Exception $e) {
            error_log("Error in getSystemStages: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al obtener las etapas del sistema'];
        }
    }

    /**
     * Programa una verificación continua
     */
    public function scheduleVerification($lifecycle_id, $verification_data)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO gamp5_continuous_verification 
                (lifecycle_id, verification_type, description, scheduled_date)
                VALUES (?, ?, ?, ?)
            ");
            
            $stmt->bind_param("isss", 
                $lifecycle_id,
                $verification_data['type'],
                $verification_data['description'],
                $verification_data['scheduled_date']
            );
            
            if ($stmt->execute()) {
                $verification_id = $this->db->insert_id;
                
                $this->logAuditEvent('VERIFICATION_SCHEDULED', [
                    'verification_id' => $verification_id,
                    'lifecycle_id' => $lifecycle_id,
                    'type' => $verification_data['type']
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
     * Obtiene las verificaciones programadas y pendientes
     */
    public function getScheduledVerifications($lifecycle_id = null, $overdue_only = false)
    {
        try {
            $where_conditions = ['1=1'];
            $params = [];
            $types = '';
            
            if ($lifecycle_id) {
                $where_conditions[] = 'cv.lifecycle_id = ?';
                $params[] = $lifecycle_id;
                $types .= 'i';
            }
            
            if ($overdue_only) {
                $where_conditions[] = 'cv.scheduled_date < NOW() AND cv.status IN ("SCHEDULED", "IN_PROGRESS")';
            }
            
            $sql = "
                SELECT 
                    cv.*,
                    sl.system_name,
                    u1.nombre as performed_by_name,
                    u2.nombre as reviewed_by_name
                FROM gamp5_continuous_verification cv
                JOIN gamp5_system_lifecycle sl ON cv.lifecycle_id = sl.id
                LEFT JOIN usuarios u1 ON cv.performed_by = u1.id
                LEFT JOIN usuarios u2 ON cv.reviewed_by = u2.id
                WHERE " . implode(' AND ', $where_conditions) . "
                ORDER BY cv.scheduled_date ASC
            ";
            
            $stmt = $this->db->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $verifications = [];
            while ($row = $result->fetch_assoc()) {
                $row['corrective_actions'] = json_decode($row['corrective_actions'], true);
                $verifications[] = $row;
            }
            
            return ['success' => true, 'data' => $verifications];
            
        } catch (Exception $e) {
            error_log("Error in getScheduledVerifications: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al obtener las verificaciones'];
        }
    }

    /**
     * Registra un evento de auditoría
     */
    private function logAuditEvent($event_type, $details)
    {
        try {
            // Aquí se integraría con el sistema de auditoría existente
            $audit_log = [
                'usuario_id' => $this->usuario_id,
                'evento' => $event_type,
                'detalles' => json_encode($details),
                'timestamp' => date('Y-m-d H:i:s'),
                'modulo' => 'GAMP5_LIFECYCLE'
            ];
            
            // Log the audit event (implementation depends on existing audit system)
            error_log("GAMP5 Audit Event: " . json_encode($audit_log));
            
        } catch (Exception $e) {
            error_log("Error logging audit event: " . $e->getMessage());
        }
    }

    /**
     * Genera un reporte del estado del ciclo de vida
     */
    public function generateLifecycleReport($lifecycle_id)
    {
        try {
            // Obtener información del sistema
            $system_info = $this->getSystemLifecycle($lifecycle_id);
            if (!$system_info['success']) {
                return $system_info;
            }
            
            // Obtener etapas
            $stages_info = $this->getSystemStages($lifecycle_id);
            if (!$stages_info['success']) {
                return $stages_info;
            }
            
            // Obtener verificaciones
            $verifications_info = $this->getScheduledVerifications($lifecycle_id);
            if (!$verifications_info['success']) {
                return $verifications_info;
            }
            
            $report = [
                'system' => $system_info['data'],
                'stages' => $stages_info['data'],
                'verifications' => $verifications_info['data'],
                'summary' => [
                    'total_stages' => count($stages_info['data']),
                    'completed_stages' => count(array_filter($stages_info['data'], function($s) { return $s['status'] === 'COMPLETED'; })),
                    'in_progress_stages' => count(array_filter($stages_info['data'], function($s) { return $s['status'] === 'IN_PROGRESS'; })),
                    'pending_verifications' => count(array_filter($verifications_info['data'], function($v) { return $v['status'] === 'SCHEDULED'; })),
                    'overdue_verifications' => count(array_filter($verifications_info['data'], function($v) { 
                        return $v['status'] === 'SCHEDULED' && strtotime($v['scheduled_date']) < time(); 
                    }))
                ],
                'generated_at' => date('Y-m-d H:i:s'),
                'generated_by' => $this->usuario_id
            ];
            
            return ['success' => true, 'data' => $report];
            
        } catch (Exception $e) {
            error_log("Error in generateLifecycleReport: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al generar el reporte'];
        }
    }

    /**
     * Obtiene información de un sistema específico
     */
    private function getSystemLifecycle($lifecycle_id)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    sl.*,
                    u.nombre as created_by_name
                FROM gamp5_system_lifecycle sl
                LEFT JOIN usuarios u ON sl.created_by = u.id
                WHERE sl.id = ?
            ");
            
            $stmt->bind_param("i", $lifecycle_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if ($result) {
                $result['regulatory_requirements'] = json_decode($result['regulatory_requirements'], true);
                return ['success' => true, 'data' => $result];
            }
            
            return ['success' => false, 'error' => 'Sistema no encontrado'];
            
        } catch (Exception $e) {
            error_log("Error in getSystemLifecycle: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al obtener el sistema'];
        }
    }
}