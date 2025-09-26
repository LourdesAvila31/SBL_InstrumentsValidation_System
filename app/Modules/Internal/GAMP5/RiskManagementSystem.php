<?php
/**
 * Sistema de Gestión de Riesgos conforme a GAMP 5
 * 
 * Este módulo implementa un enfoque basado en riesgos para la validación
 * de sistemas computerizados, siguiendo las directrices GAMP 5 y ICH Q9.
 * Gestiona la identificación, evaluación, control y revisión de riesgos.
 */

require_once dirname(__DIR__, 2) . '/Core/db_config.php';
require_once dirname(__DIR__, 2) . '/Core/permissions.php';

class RiskManagementSystem
{
    private $db;
    private $usuario_id;
    
    // Categorías de riesgo según GAMP 5
    const RISK_CATEGORIES = [
        'PATIENT_SAFETY' => 'Seguridad del Paciente',
        'PRODUCT_QUALITY' => 'Calidad del Producto',
        'DATA_INTEGRITY' => 'Integridad de Datos',
        'REGULATORY_COMPLIANCE' => 'Cumplimiento Regulatorio',
        'BUSINESS_CONTINUITY' => 'Continuidad del Negocio',
        'SYSTEM_AVAILABILITY' => 'Disponibilidad del Sistema',
        'SECURITY' => 'Seguridad',
        'PERFORMANCE' => 'Rendimiento'
    ];
    
    // Niveles de probabilidad
    const PROBABILITY_LEVELS = [
        1 => 'Muy Improbable (< 1%)',
        2 => 'Improbable (1-10%)',
        3 => 'Posible (10-50%)',
        4 => 'Probable (50-90%)',
        5 => 'Muy Probable (> 90%)'
    ];
    
    // Niveles de severidad
    const SEVERITY_LEVELS = [
        1 => 'Insignificante',
        2 => 'Menor',
        3 => 'Moderado',
        4 => 'Mayor',
        5 => 'Catastrófico'
    ];
    
    // Niveles de detectabilidad
    const DETECTABILITY_LEVELS = [
        1 => 'Muy Alta (Casi Seguro)',
        2 => 'Alta (Probable)',
        3 => 'Moderada (Posible)',
        4 => 'Baja (Improbable)',
        5 => 'Muy Baja (Casi Imposible)'
    ];
    
    // Estrategias de mitigación
    const MITIGATION_STRATEGIES = [
        'AVOID' => 'Evitar - Eliminar el riesgo',
        'REDUCE' => 'Reducir - Disminuir probabilidad/impacto',
        'TRANSFER' => 'Transferir - Compartir el riesgo',
        'ACCEPT' => 'Aceptar - Asumir el riesgo residual'
    ];

    public function __construct($usuario_id = null)
    {
        $this->db = DatabaseManager::getConnection();
        $this->usuario_id = $usuario_id ?? $_SESSION['usuario_id'] ?? null;
        $this->initializeTables();
    }

    /**
     * Inicializa las tablas necesarias para la gestión de riesgos
     */
    private function initializeTables()
    {
        $queries = [
            // Tabla principal de evaluaciones de riesgo
            "CREATE TABLE IF NOT EXISTS gamp5_risk_assessments (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            
            // Tabla de riesgos identificados
            "CREATE TABLE IF NOT EXISTS gamp5_risk_items (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            
            // Tabla de medidas de mitigación
            "CREATE TABLE IF NOT EXISTS gamp5_risk_mitigations (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            
            // Tabla de riesgo residual (después de mitigación)
            "CREATE TABLE IF NOT EXISTS gamp5_residual_risks (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            
            // Tabla de revisiones periódicas de riesgo
            "CREATE TABLE IF NOT EXISTS gamp5_risk_reviews (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            
            // Tabla de matriz de riesgos (configuración)
            "CREATE TABLE IF NOT EXISTS gamp5_risk_matrix_config (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        ];

        foreach ($queries as $query) {
            try {
                $this->db->exec($query);
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'already exists') === false) {
                    throw $e;
                }
            }
        }
        
        // Insertar configuración de matriz de riesgos por defecto
        $this->insertDefaultRiskMatrix();
    }

    /**
     * Inserta la configuración por defecto de la matriz de riesgos
     */
    private function insertDefaultRiskMatrix()
    {
        $defaultMatrix = [
            ['Risk Matrix GAMP5', 1, 25, 1, 25, 1, 25, 'LOW', '#28a745', 'Monitoreo rutinario', false],
            ['Risk Matrix GAMP5', 26, 60, 1, 60, 1, 60, 'MEDIUM', '#ffc107', 'Revisión y controles adicionales', false],
            ['Risk Matrix GAMP5', 61, 100, 1, 100, 1, 100, 'HIGH', '#fd7e14', 'Acción inmediata requerida', true],
            ['Risk Matrix GAMP5', 101, 125, 1, 125, 1, 125, 'CRITICAL', '#dc3545', 'Acción crítica inmediata', true]
        ];

        $stmt = $this->db->prepare("
            INSERT IGNORE INTO gamp5_risk_matrix_config 
            (matrix_name, probability_min, probability_max, severity_min, severity_max, 
             detectability_min, detectability_max, risk_level, color_code, action_required, approval_required) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($defaultMatrix as $config) {
            $stmt->execute($config);
        }
    }

    /**
     * Crea una nueva evaluación de riesgos
     */
    public function createRiskAssessment($data)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO gamp5_risk_assessments 
                (assessment_name, system_name, gamp_category, assessment_scope, 
                 business_process, regulatory_requirements, assessment_date, 
                 risk_owner, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $data['assessment_name'],
                $data['system_name'],
                $data['gamp_category'],
                $data['assessment_scope'],
                $data['business_process'] ?? '',
                json_encode($data['regulatory_requirements'] ?? []),
                $data['assessment_date'],
                $data['risk_owner'],
                $this->usuario_id
            ]);

            $assessment_id = $this->db->lastInsertId();

            // Registrar en audit log
            $this->logActivity('RISK_ASSESSMENT_CREATED', "Nueva evaluación de riesgos creada: {$data['assessment_name']}", [
                'assessment_id' => $assessment_id,
                'system_name' => $data['system_name']
            ]);

            return [
                'success' => true,
                'assessment_id' => $assessment_id,
                'message' => 'Evaluación de riesgos creada exitosamente'
            ];

        } catch (Exception $e) {
            error_log("Error creating risk assessment: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al crear la evaluación de riesgos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Agrega un nuevo riesgo a una evaluación
     */
    public function addRiskItem($assessment_id, $data)
    {
        try {
            // Calcular nivel de riesgo basado en el score
            $risk_score = $data['probability_score'] * $data['severity_score'] * $data['detectability_score'];
            $risk_level = $this->calculateRiskLevel($risk_score);
            $acceptability = $this->determineAcceptability($risk_level, $data['risk_category']);

            $stmt = $this->db->prepare("
                INSERT INTO gamp5_risk_items 
                (assessment_id, risk_id, risk_title, risk_description, risk_category,
                 process_step, failure_mode, potential_causes, current_controls,
                 probability_score, severity_score, detectability_score, 
                 risk_level, acceptability) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $assessment_id,
                $data['risk_id'],
                $data['risk_title'],
                $data['risk_description'],
                $data['risk_category'],
                $data['process_step'] ?? '',
                $data['failure_mode'] ?? '',
                $data['potential_causes'] ?? '',
                $data['current_controls'] ?? '',
                $data['probability_score'],
                $data['severity_score'],
                $data['detectability_score'],
                $risk_level,
                $acceptability
            ]);

            $risk_item_id = $this->db->lastInsertId();

            // Si el riesgo es inaceptable, crear automáticamente una mitigación pendiente
            if ($acceptability === 'UNACCEPTABLE') {
                $this->createAutoMitigation($risk_item_id, $risk_level);
            }

            // Registrar en audit log
            $this->logActivity('RISK_ITEM_ADDED', "Nuevo riesgo agregado: {$data['risk_title']}", [
                'assessment_id' => $assessment_id,
                'risk_item_id' => $risk_item_id,
                'risk_level' => $risk_level,
                'risk_score' => $risk_score
            ]);

            return [
                'success' => true,
                'risk_item_id' => $risk_item_id,
                'risk_score' => $risk_score,
                'risk_level' => $risk_level,
                'acceptability' => $acceptability,
                'message' => 'Riesgo agregado exitosamente'
            ];

        } catch (Exception $e) {
            error_log("Error adding risk item: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al agregar el riesgo: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Crea una medida de mitigación para un riesgo
     */
    public function createMitigation($risk_item_id, $data)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO gamp5_risk_mitigations 
                (risk_item_id, mitigation_strategy, mitigation_description, 
                 implementation_approach, responsible_person, target_completion_date,
                 cost_estimate, priority, verification_required) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $risk_item_id,
                $data['mitigation_strategy'],
                $data['mitigation_description'],
                $data['implementation_approach'] ?? '',
                $data['responsible_person'],
                $data['target_completion_date'],
                $data['cost_estimate'] ?? null,
                $data['priority'] ?? 'MEDIUM',
                $data['verification_required'] ?? true
            ]);

            $mitigation_id = $this->db->lastInsertId();

            // Registrar en audit log
            $this->logActivity('MITIGATION_CREATED', "Nueva mitigación creada para riesgo ID: $risk_item_id", [
                'mitigation_id' => $mitigation_id,
                'strategy' => $data['mitigation_strategy']
            ]);

            return [
                'success' => true,
                'mitigation_id' => $mitigation_id,
                'message' => 'Medida de mitigación creada exitosamente'
            ];

        } catch (Exception $e) {
            error_log("Error creating mitigation: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al crear la medida de mitigación: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Calcula el riesgo residual después de la implementación de mitigaciones
     */
    public function calculateResidualRisk($risk_item_id, $data)
    {
        try {
            $residual_score = $data['post_mitigation_probability'] * 
                            $data['post_mitigation_severity'] * 
                            $data['post_mitigation_detectability'];
            
            $residual_level = $this->calculateRiskLevel($residual_score);
            $residual_acceptability = $this->determineAcceptability($residual_level, 'GENERAL');

            $stmt = $this->db->prepare("
                INSERT INTO gamp5_residual_risks 
                (risk_item_id, post_mitigation_probability, post_mitigation_severity,
                 post_mitigation_detectability, residual_risk_level, residual_acceptability,
                 justification, approval_required, review_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $approval_required = ($residual_acceptability === 'UNACCEPTABLE') || ($residual_level === 'CRITICAL');
            $review_date = date('Y-m-d', strtotime('+6 months'));

            $stmt->execute([
                $risk_item_id,
                $data['post_mitigation_probability'],
                $data['post_mitigation_severity'],
                $data['post_mitigation_detectability'],
                $residual_level,
                $residual_acceptability,
                $data['justification'] ?? '',
                $approval_required,
                $review_date
            ]);

            $residual_id = $this->db->lastInsertId();

            // Registrar en audit log
            $this->logActivity('RESIDUAL_RISK_CALCULATED', "Riesgo residual calculado para riesgo ID: $risk_item_id", [
                'residual_id' => $residual_id,
                'residual_score' => $residual_score,
                'residual_level' => $residual_level
            ]);

            return [
                'success' => true,
                'residual_id' => $residual_id,
                'residual_score' => $residual_score,
                'residual_level' => $residual_level,
                'residual_acceptability' => $residual_acceptability,
                'approval_required' => $approval_required,
                'message' => 'Riesgo residual calculado exitosamente'
            ];

        } catch (Exception $e) {
            error_log("Error calculating residual risk: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al calcular el riesgo residual: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Programa una revisión periódica de riesgos
     */
    public function scheduleRiskReview($assessment_id, $data)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO gamp5_risk_reviews 
                (assessment_id, review_date, review_type, review_trigger,
                 reviewer_id, next_review_date) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $next_review = $this->calculateNextReviewDate($data['review_type']);

            $stmt->execute([
                $assessment_id,
                $data['review_date'],
                $data['review_type'],
                $data['review_trigger'] ?? '',
                $data['reviewer_id'],
                $next_review
            ]);

            $review_id = $this->db->lastInsertId();

            // Registrar en audit log
            $this->logActivity('RISK_REVIEW_SCHEDULED', "Revisión de riesgos programada para evaluación ID: $assessment_id", [
                'review_id' => $review_id,
                'review_type' => $data['review_type']
            ]);

            return [
                'success' => true,
                'review_id' => $review_id,
                'next_review_date' => $next_review,
                'message' => 'Revisión de riesgos programada exitosamente'
            ];

        } catch (Exception $e) {
            error_log("Error scheduling risk review: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al programar la revisión de riesgos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Genera un reporte de evaluación de riesgos
     */
    public function generateRiskAssessmentReport($assessment_id, $report_type = 'FULL')
    {
        try {
            // Obtener datos de la evaluación
            $assessment = $this->getRiskAssessmentDetails($assessment_id);
            if (!$assessment['success']) {
                return $assessment;
            }

            $report_data = [
                'assessment' => $assessment['data'],
                'risk_summary' => $this->getRiskSummary($assessment_id),
                'risk_matrix' => $this->getRiskMatrix($assessment_id),
                'mitigation_status' => $this->getMitigationStatus($assessment_id),
                'residual_risks' => $this->getResidualRiskSummary($assessment_id),
                'recommendations' => $this->generateRecommendations($assessment_id),
                'generated_at' => date('Y-m-d H:i:s'),
                'generated_by' => $this->usuario_id,
                'report_type' => $report_type
            ];

            // Registrar generación del reporte
            $this->logActivity('RISK_REPORT_GENERATED', "Reporte de evaluación de riesgos generado", [
                'assessment_id' => $assessment_id,
                'report_type' => $report_type
            ]);

            return [
                'success' => true,
                'data' => $report_data,
                'message' => 'Reporte generado exitosamente'
            ];

        } catch (Exception $e) {
            error_log("Error generating risk assessment report: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al generar el reporte: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene el dashboard de gestión de riesgos
     */
    public function getRiskDashboard()
    {
        try {
            $dashboard_data = [
                'overview' => $this->getRiskOverview(),
                'high_priority_risks' => $this->getHighPriorityRisks(),
                'mitigation_status' => $this->getOverallMitigationStatus(),
                'review_schedule' => $this->getUpcomingReviews(),
                'risk_trends' => $this->getRiskTrends(),
                'compliance_metrics' => $this->getComplianceMetrics()
            ];

            return [
                'success' => true,
                'data' => $dashboard_data,
                'timestamp' => date('Y-m-d H:i:s')
            ];

        } catch (Exception $e) {
            error_log("Error getting risk dashboard: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al obtener el dashboard de riesgos: ' . $e->getMessage()
            ];
        }
    }

    // Métodos auxiliares privados

    /**
     * Calcula el nivel de riesgo basado en el score
     */
    private function calculateRiskLevel($risk_score)
    {
        if ($risk_score <= 25) return 'LOW';
        if ($risk_score <= 60) return 'MEDIUM';
        if ($risk_score <= 100) return 'HIGH';
        return 'CRITICAL';
    }

    /**
     * Determina la aceptabilidad del riesgo
     */
    private function determineAcceptability($risk_level, $risk_category)
    {
        // Criterios más estrictos para seguridad del paciente y calidad del producto
        $strict_categories = ['PATIENT_SAFETY', 'PRODUCT_QUALITY', 'DATA_INTEGRITY'];
        
        if (in_array($risk_category, $strict_categories)) {
            switch ($risk_level) {
                case 'LOW': return 'ACCEPTABLE';
                case 'MEDIUM': return 'TOLERABLE';
                case 'HIGH':
                case 'CRITICAL': return 'UNACCEPTABLE';
            }
        } else {
            switch ($risk_level) {
                case 'LOW':
                case 'MEDIUM': return 'ACCEPTABLE';
                case 'HIGH': return 'TOLERABLE';
                case 'CRITICAL': return 'UNACCEPTABLE';
            }
        }
        
        return 'TOLERABLE';
    }

    /**
     * Crea una mitigación automática para riesgos inaceptables
     */
    private function createAutoMitigation($risk_item_id, $risk_level)
    {
        $strategy = ($risk_level === 'CRITICAL') ? 'AVOID' : 'REDUCE';
        $priority = ($risk_level === 'CRITICAL') ? 'URGENT' : 'HIGH';
        $target_date = date('Y-m-d', strtotime('+30 days'));

        $stmt = $this->db->prepare("
            INSERT INTO gamp5_risk_mitigations 
            (risk_item_id, mitigation_strategy, mitigation_description, 
             responsible_person, target_completion_date, priority, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'PLANNED')
        ");

        $stmt->execute([
            $risk_item_id,
            $strategy,
            'Mitigación automática generada - Requiere definición detallada',
            $this->usuario_id,
            $target_date,
            $priority
        ]);
    }

    /**
     * Calcula la próxima fecha de revisión
     */
    private function calculateNextReviewDate($review_type)
    {
        switch ($review_type) {
            case 'PERIODIC':
                return date('Y-m-d', strtotime('+6 months'));
            case 'TRIGGERED':
                return date('Y-m-d', strtotime('+3 months'));
            case 'POST_INCIDENT':
                return date('Y-m-d', strtotime('+1 month'));
            case 'REGULATORY':
                return date('Y-m-d', strtotime('+12 months'));
            default:
                return date('Y-m-d', strtotime('+6 months'));
        }
    }

    /**
     * Obtiene resumen general de riesgos
     */
    private function getRiskOverview()
    {
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) as total_assessments,
                SUM(CASE WHEN status = 'ACTIVE' THEN 1 ELSE 0 END) as active_assessments,
                (SELECT COUNT(*) FROM gamp5_risk_items WHERE risk_level = 'CRITICAL') as critical_risks,
                (SELECT COUNT(*) FROM gamp5_risk_items WHERE risk_level = 'HIGH') as high_risks,
                (SELECT COUNT(*) FROM gamp5_risk_mitigations WHERE status = 'PLANNED') as pending_mitigations
            FROM gamp5_risk_assessments
        ");

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene riesgos de alta prioridad
     */
    private function getHighPriorityRisks()
    {
        $stmt = $this->db->query("
            SELECT ri.*, ra.system_name, ra.assessment_name
            FROM gamp5_risk_items ri
            JOIN gamp5_risk_assessments ra ON ri.assessment_id = ra.id
            WHERE ri.risk_level IN ('CRITICAL', 'HIGH') 
            AND ri.acceptability = 'UNACCEPTABLE'
            ORDER BY ri.initial_risk_score DESC
            LIMIT 10
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Registra actividad en el audit log
     */
    private function logActivity($action, $description, $additional_data = [])
    {
        // Implementar logging según el sistema de auditoría existente
        // Por ahora, log básico
        error_log("GAMP5 Risk Management - $action: $description - User: {$this->usuario_id} - " . json_encode($additional_data));
    }

    // Métodos adicionales para obtener datos específicos del dashboard
    private function getRiskAssessmentDetails($assessment_id) { /* Implementar */ return ['success' => true, 'data' => []]; }
    private function getRiskSummary($assessment_id) { /* Implementar */ return []; }
    private function getRiskMatrix($assessment_id) { /* Implementar */ return []; }
    private function getMitigationStatus($assessment_id) { /* Implementar */ return []; }
    private function getResidualRiskSummary($assessment_id) { /* Implementar */ return []; }
    private function generateRecommendations($assessment_id) { /* Implementar */ return []; }
    private function getOverallMitigationStatus() { /* Implementar */ return []; }
    private function getUpcomingReviews() { /* Implementar */ return []; }
    private function getRiskTrends() { /* Implementar */ return []; }
    private function getComplianceMetrics() { /* Implementar */ return []; }
}
?>