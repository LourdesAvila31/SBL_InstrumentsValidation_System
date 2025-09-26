<?php
/**
 * Sistema de Evaluación de Proveedores GAMP 5
 * 
 * Gestiona la evaluación, calificación y seguimiento de proveedores
 * conforme a GAMP 5 y normativas GxP para supply chain validation.
 */

require_once dirname(__DIR__, 3) . '/Core/db_config.php';
require_once dirname(__DIR__, 3) . '/Core/permissions.php';

class SupplierAssessmentModule
{
    private $db;
    private $usuario_id;
    
    // Categorías GAMP 5 para proveedores
    const GAMP_CATEGORIES = [
        'CATEGORY_1' => 'Categoría 1 - Infraestructura (Sistemas Operativos)',
        'CATEGORY_2' => 'Categoría 2 - Firmware (Controladores de hardware)',
        'CATEGORY_3' => 'Categoría 3 - Software No Configurable (COTS)',
        'CATEGORY_4' => 'Categoría 4 - Software Configurable',
        'CATEGORY_5' => 'Categoría 5 - Software Personalizado'
    ];
    
    // Tipos de proveedores
    const SUPPLIER_TYPES = [
        'SOFTWARE_VENDOR' => 'Proveedor de Software',
        'HARDWARE_VENDOR' => 'Proveedor de Hardware',
        'SERVICE_PROVIDER' => 'Proveedor de Servicios',
        'CONSULTANT' => 'Consultor/Integrador',
        'CLOUD_PROVIDER' => 'Proveedor Cloud',
        'MAINTENANCE_PROVIDER' => 'Proveedor de Mantenimiento',
        'VALIDATION_SERVICES' => 'Servicios de Validación',
        'TRAINING_PROVIDER' => 'Proveedor de Capacitación'
    ];
    
    // Estados de calificación
    const QUALIFICATION_STATUS = [
        'NOT_STARTED' => 'No Iniciado',
        'IN_PROGRESS' => 'En Progreso',
        'APPROVED' => 'Aprobado',
        'CONDITIONALLY_APPROVED' => 'Aprobado Condicionalmente',
        'REJECTED' => 'Rechazado',
        'SUSPENDED' => 'Suspendido',
        'RE_EVALUATION' => 'Re-evaluación Requerida',
        'EXPIRED' => 'Expirado'
    ];
    
    // Niveles de riesgo del proveedor
    const RISK_LEVELS = [
        'LOW' => 'Bajo',
        'MEDIUM' => 'Medio', 
        'HIGH' => 'Alto',
        'CRITICAL' => 'Crítico'
    ];
    
    // Tipos de auditoría
    const AUDIT_TYPES = [
        'INITIAL_QUALIFICATION' => 'Calificación Inicial',
        'PERIODIC_REVIEW' => 'Revisión Periódica',
        'FOR_CAUSE' => 'Por Causa Específica',
        'RENEWAL' => 'Renovación',
        'POST_INCIDENT' => 'Post-Incidente',
        'CHANGE_CONTROL' => 'Control de Cambios'
    ];
    
    // Criterios de evaluación
    const EVALUATION_CRITERIA = [
        'QUALITY_SYSTEM' => 'Sistema de Calidad',
        'TECHNICAL_CAPABILITY' => 'Capacidad Técnica',
        'REGULATORY_COMPLIANCE' => 'Cumplimiento Regulatorio',
        'SECURITY_CONTROLS' => 'Controles de Seguridad',
        'CHANGE_MANAGEMENT' => 'Gestión de Cambios',
        'DOCUMENTATION' => 'Documentación',
        'SUPPORT_CAPABILITY' => 'Capacidad de Soporte',
        'BUSINESS_CONTINUITY' => 'Continuidad del Negocio',
        'DATA_INTEGRITY' => 'Integridad de Datos',
        'VALIDATION_SUPPORT' => 'Soporte de Validación'
    ];

    public function __construct($usuario_id = null)
    {
        try {
            $this->db = DatabaseManager::getPDOConnection();
            $this->usuario_id = $usuario_id;
            
            if (!$this->db) {
                throw new Exception("Conexión a base de datos no disponible");
            }
        } catch (Exception $e) {
            throw new Exception("Error de conexión a base de datos: " . $e->getMessage());
        }
    }

    /**
     * Registrar un nuevo proveedor
     */
    public function registerSupplier($data)
    {
        try {
            $this->db->beginTransaction();
            
            // Validar datos requeridos
            $required_fields = ['supplier_name', 'supplier_type', 'contact_email'];
            foreach ($required_fields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    throw new Exception("Campo requerido faltante: $field");
                }
            }
            
            // Generar código único de proveedor
            $supplier_code = $this->generateSupplierCode($data['supplier_type']);
            
            // Insertar proveedor
            $stmt = $this->db->prepare("
                INSERT INTO gamp5_suppliers (
                    supplier_code, supplier_name, supplier_type, description,
                    contact_person, contact_email, contact_phone, address,
                    website, registration_number, country, gamp_category,
                    risk_level, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $supplier_code,
                $data['supplier_name'],
                $data['supplier_type'],
                $data['description'] ?? '',
                $data['contact_person'] ?? '',
                $data['contact_email'],
                $data['contact_phone'] ?? '',
                $data['address'] ?? '',
                $data['website'] ?? '',
                $data['registration_number'] ?? '',
                $data['country'] ?? '',
                $data['gamp_category'] ?? 'CATEGORY_3',
                $data['risk_level'] ?? 'MEDIUM',
                $this->usuario_id
            ]);
            
            $supplier_id = $this->db->lastInsertId();
            
            // Registrar auditoría
            $this->logAuditEvent('SUPPLIER_REGISTERED', $supplier_id, [
                'supplier_code' => $supplier_code,
                'supplier_name' => $data['supplier_name'],
                'supplier_type' => $data['supplier_type']
            ]);
            
            $this->db->commit();
            return $supplier_id;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Iniciar proceso de calificación de proveedor
     */
    public function startQualificationProcess($supplier_id, $qualification_data)
    {
        try {
            $this->db->beginTransaction();
            
            // Validar que el proveedor existe
            $supplier = $this->getSupplier($supplier_id);
            if (!$supplier) {
                throw new Exception("Proveedor no encontrado");
            }
            
            // Crear registro de calificación
            $stmt = $this->db->prepare("
                INSERT INTO gamp5_supplier_qualifications (
                    supplier_id, qualification_type, target_completion_date,
                    lead_assessor_id, business_justification, scope_description,
                    critical_aspects, regulatory_requirements, status,
                    created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'IN_PROGRESS', ?, NOW())
            ");
            
            $stmt->execute([
                $supplier_id,
                $qualification_data['qualification_type'] ?? 'INITIAL_QUALIFICATION',
                $qualification_data['target_completion_date'] ?? null,
                $qualification_data['lead_assessor_id'] ?? $this->usuario_id,
                $qualification_data['business_justification'] ?? '',
                $qualification_data['scope_description'] ?? '',
                $qualification_data['critical_aspects'] ?? '',
                $qualification_data['regulatory_requirements'] ?? '',
                $this->usuario_id
            ]);
            
            $qualification_id = $this->db->lastInsertId();
            
            // Crear criterios de evaluación por defecto
            $this->createDefaultEvaluationCriteria($qualification_id, $supplier['gamp_category']);
            
            // Registrar auditoría
            $this->logAuditEvent('QUALIFICATION_STARTED', $supplier_id, [
                'qualification_id' => $qualification_id,
                'qualification_type' => $qualification_data['qualification_type'] ?? 'INITIAL_QUALIFICATION'
            ]);
            
            $this->db->commit();
            return $qualification_id;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Realizar evaluación de criterios
     */
    public function performCriteriaEvaluation($qualification_id, $evaluation_data)
    {
        try {
            $this->db->beginTransaction();
            
            foreach ($evaluation_data as $criteria_id => $evaluation) {
                // Actualizar evaluación del criterio
                $stmt = $this->db->prepare("
                    UPDATE gamp5_qualification_criteria 
                    SET score = ?, evidence = ?, comments = ?, 
                        evaluator_id = ?, evaluated_at = NOW(), updated_at = NOW()
                    WHERE id = ? AND qualification_id = ?
                ");
                
                $stmt->execute([
                    $evaluation['score'],
                    $evaluation['evidence'] ?? '',
                    $evaluation['comments'] ?? '',
                    $this->usuario_id,
                    $criteria_id,
                    $qualification_id
                ]);
            }
            
            // Calcular score total
            $total_score = $this->calculateQualificationScore($qualification_id);
            
            // Actualizar calificación
            $stmt = $this->db->prepare("
                UPDATE gamp5_supplier_qualifications 
                SET total_score = ?, updated_by = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$total_score, $this->usuario_id, $qualification_id]);
            
            // Registrar auditoría
            $this->logAuditEvent('CRITERIA_EVALUATED', $qualification_id, [
                'total_score' => $total_score,
                'criteria_count' => count($evaluation_data)
            ]);
            
            $this->db->commit();
            return $total_score;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Completar calificación y determinar resultado
     */
    public function completeQualification($qualification_id, $completion_data)
    {
        try {
            $this->db->beginTransaction();
            
            // Obtener calificación actual
            $qualification = $this->getQualification($qualification_id);
            if (!$qualification) {
                throw new Exception("Calificación no encontrada");
            }
            
            // Determinar estado basado en score y criterios
            $recommendation = $this->determineQualificationRecommendation($qualification_id);
            
            // Actualizar calificación
            $stmt = $this->db->prepare("
                UPDATE gamp5_supplier_qualifications 
                SET status = ?, final_recommendation = ?, 
                    completion_comments = ?, completed_by = ?, 
                    completed_at = NOW(), updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $completion_data['status'] ?? $recommendation['status'],
                $recommendation['recommendation'],
                $completion_data['completion_comments'] ?? '',
                $this->usuario_id,
                $qualification_id
            ]);
            
            // Si es aprobado, crear registro en lista de proveedores aprobados
            if (($completion_data['status'] ?? $recommendation['status']) === 'APPROVED') {
                $this->addToApprovedSuppliers($qualification['supplier_id'], $qualification_id);
            }
            
            // Actualizar estado del proveedor
            $this->updateSupplierStatus($qualification['supplier_id'], $completion_data['status'] ?? $recommendation['status']);
            
            // Registrar auditoría
            $this->logAuditEvent('QUALIFICATION_COMPLETED', $qualification['supplier_id'], [
                'qualification_id' => $qualification_id,
                'final_status' => $completion_data['status'] ?? $recommendation['status'],
                'total_score' => $qualification['total_score']
            ]);
            
            $this->db->commit();
            return [
                'status' => $completion_data['status'] ?? $recommendation['status'],
                'recommendation' => $recommendation['recommendation'],
                'score' => $qualification['total_score']
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Crear auditoría de proveedor
     */
    public function createSupplierAudit($supplier_id, $audit_data)
    {
        try {
            $this->db->beginTransaction();
            
            // Crear auditoría
            $stmt = $this->db->prepare("
                INSERT INTO gamp5_supplier_audits (
                    supplier_id, audit_type, audit_scope, planned_date,
                    lead_auditor_id, audit_team, objectives,
                    success_criteria, status, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'PLANNED', ?, NOW())
            ");
            
            $stmt->execute([
                $supplier_id,
                $audit_data['audit_type'],
                $audit_data['audit_scope'] ?? '',
                $audit_data['planned_date'],
                $audit_data['lead_auditor_id'] ?? $this->usuario_id,
                json_encode($audit_data['audit_team'] ?? []),
                $audit_data['objectives'] ?? '',
                $audit_data['success_criteria'] ?? '',
                $this->usuario_id
            ]);
            
            $audit_id = $this->db->lastInsertId();
            
            // Registrar auditoría
            $this->logAuditEvent('AUDIT_SCHEDULED', $supplier_id, [
                'audit_id' => $audit_id,
                'audit_type' => $audit_data['audit_type'],
                'planned_date' => $audit_data['planned_date']
            ]);
            
            $this->db->commit();
            return $audit_id;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Registrar hallazgos de auditoría
     */
    public function recordAuditFindings($audit_id, $findings_data)
    {
        try {
            $this->db->beginTransaction();
            
            foreach ($findings_data as $finding) {
                $stmt = $this->db->prepare("
                    INSERT INTO gamp5_audit_findings (
                        audit_id, finding_type, severity, area,
                        description, regulatory_reference, evidence,
                        corrective_action_required, target_closure_date,
                        created_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $audit_id,
                    $finding['finding_type'],
                    $finding['severity'],
                    $finding['area'],
                    $finding['description'],
                    $finding['regulatory_reference'] ?? '',
                    $finding['evidence'] ?? '',
                    $finding['corrective_action_required'] ? 1 : 0,
                    $finding['target_closure_date'] ?? null,
                    $this->usuario_id
                ]);
            }
            
            // Actualizar auditoría con hallazgos
            $stmt = $this->db->prepare("
                UPDATE gamp5_supplier_audits 
                SET findings_count = ?, updated_by = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([count($findings_data), $this->usuario_id, $audit_id]);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Obtener proveedores con filtros
     */
    public function getSuppliers($filters = [])
    {
        $where_conditions = ['1=1'];
        $params = [];
        
        if (!empty($filters['supplier_type'])) {
            $where_conditions[] = "s.supplier_type = ?";
            $params[] = $filters['supplier_type'];
        }
        
        if (!empty($filters['gamp_category'])) {
            $where_conditions[] = "s.gamp_category = ?";
            $params[] = $filters['gamp_category'];
        }
        
        if (!empty($filters['risk_level'])) {
            $where_conditions[] = "s.risk_level = ?";
            $params[] = $filters['risk_level'];
        }
        
        if (!empty($filters['status'])) {
            $where_conditions[] = "s.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $where_conditions[] = "(s.supplier_name LIKE ? OR s.supplier_code LIKE ? OR s.description LIKE ?)";
            $search_term = '%' . $filters['search'] . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        $limit = isset($filters['limit']) ? "LIMIT " . (int)$filters['limit'] : "LIMIT 100";
        $offset = isset($filters['offset']) ? "OFFSET " . (int)$filters['offset'] : "";
        
        $stmt = $this->db->prepare("
            SELECT s.*, u.nombre as created_by_name,
                   (SELECT COUNT(*) FROM gamp5_supplier_qualifications sq WHERE sq.supplier_id = s.id) as qualification_count,
                   (SELECT COUNT(*) FROM gamp5_supplier_audits sa WHERE sa.supplier_id = s.id) as audit_count,
                   (SELECT status FROM gamp5_supplier_qualifications sq WHERE sq.supplier_id = s.id ORDER BY created_at DESC LIMIT 1) as last_qualification_status
            FROM gamp5_suppliers s
            LEFT JOIN usuarios u ON s.created_by = u.id
            WHERE " . implode(' AND ', $where_conditions) . "
            ORDER BY s.updated_at DESC
            $limit $offset
        ");
        
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener proveedor por ID
     */
    public function getSupplier($supplier_id)
    {
        $stmt = $this->db->prepare("
            SELECT s.*, u.nombre as created_by_name
            FROM gamp5_suppliers s
            LEFT JOIN usuarios u ON s.created_by = u.id
            WHERE s.id = ?
        ");
        $stmt->execute([$supplier_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener calificaciones de un proveedor
     */
    public function getSupplierQualifications($supplier_id)
    {
        $stmt = $this->db->prepare("
            SELECT sq.*, u1.nombre as created_by_name, u2.nombre as lead_assessor_name
            FROM gamp5_supplier_qualifications sq
            LEFT JOIN usuarios u1 ON sq.created_by = u1.id
            LEFT JOIN usuarios u2 ON sq.lead_assessor_id = u2.id
            WHERE sq.supplier_id = ?
            ORDER BY sq.created_at DESC
        ");
        $stmt->execute([$supplier_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener métricas del módulo
     */
    public function getSupplierMetrics($filters = [])
    {
        $date_filter = "";
        $params = [];
        
        if (!empty($filters['start_date'])) {
            $date_filter .= " AND s.created_at >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $date_filter .= " AND s.created_at <= ?";
            $params[] = $filters['end_date'];
        }
        
        // Proveedores por tipo
        $stmt = $this->db->prepare("
            SELECT supplier_type, COUNT(*) as count
            FROM gamp5_suppliers s
            WHERE 1=1 $date_filter
            GROUP BY supplier_type
            ORDER BY count DESC
        ");
        $stmt->execute($params);
        $by_type = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Proveedores por categoría GAMP
        $stmt = $this->db->prepare("
            SELECT gamp_category, COUNT(*) as count
            FROM gamp5_suppliers s
            WHERE 1=1 $date_filter
            GROUP BY gamp_category
            ORDER BY count DESC
        ");
        $stmt->execute($params);
        $by_gamp_category = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Proveedores por nivel de riesgo
        $stmt = $this->db->prepare("
            SELECT risk_level, COUNT(*) as count
            FROM gamp5_suppliers s
            WHERE 1=1 $date_filter
            GROUP BY risk_level
            ORDER BY count DESC
        ");
        $stmt->execute($params);
        $by_risk_level = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calificaciones por estado
        $stmt = $this->db->prepare("
            SELECT sq.status, COUNT(*) as count
            FROM gamp5_supplier_qualifications sq
            JOIN gamp5_suppliers s ON sq.supplier_id = s.id
            WHERE 1=1 $date_filter
            GROUP BY sq.status
            ORDER BY count DESC
        ");
        $stmt->execute($params);
        $qualifications_by_status = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Métricas generales
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_suppliers,
                COUNT(CASE WHEN status = 'APPROVED' THEN 1 END) as approved_suppliers,
                COUNT(CASE WHEN status = 'IN_PROGRESS' THEN 1 END) as pending_qualification,
                COUNT(CASE WHEN risk_level = 'CRITICAL' THEN 1 END) as critical_risk_suppliers,
                AVG(DATEDIFF(NOW(), created_at)) as avg_age_days
            FROM gamp5_suppliers s
            WHERE 1=1 $date_filter
        ");
        $stmt->execute($params);
        $general_metrics = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'by_type' => $by_type,
            'by_gamp_category' => $by_gamp_category,
            'by_risk_level' => $by_risk_level,
            'qualifications_by_status' => $qualifications_by_status,
            'general' => $general_metrics
        ];
    }

    // Métodos auxiliares privados
    
    private function generateSupplierCode($supplier_type)
    {
        $prefix = substr($supplier_type, 0, 3);
        $year = date('Y');
        
        // Obtener siguiente número secuencial
        $stmt = $this->db->prepare("
            SELECT MAX(CAST(SUBSTRING(supplier_code, -4) AS UNSIGNED)) as max_num
            FROM gamp5_suppliers 
            WHERE supplier_code LIKE ?
        ");
        $stmt->execute(["$prefix-$year-%"]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $next_num = ($result['max_num'] ?? 0) + 1;
        
        return sprintf("%s-%s-%04d", $prefix, $year, $next_num);
    }

    private function createDefaultEvaluationCriteria($qualification_id, $gamp_category)
    {
        $criteria_weights = $this->getCriteriaWeights($gamp_category);
        
        foreach (self::EVALUATION_CRITERIA as $criteria_key => $criteria_name) {
            $stmt = $this->db->prepare("
                INSERT INTO gamp5_qualification_criteria (
                    qualification_id, criteria_code, criteria_name, 
                    weight, max_score, created_at
                ) VALUES (?, ?, ?, ?, 100, NOW())
            ");
            
            $weight = $criteria_weights[$criteria_key] ?? 10;
            $stmt->execute([$qualification_id, $criteria_key, $criteria_name, $weight]);
        }
    }

    private function getCriteriaWeights($gamp_category)
    {
        // Pesos diferentes según categoría GAMP
        $weights = [
            'CATEGORY_1' => [
                'QUALITY_SYSTEM' => 15,
                'TECHNICAL_CAPABILITY' => 20,
                'REGULATORY_COMPLIANCE' => 10,
                'SECURITY_CONTROLS' => 15,
                'CHANGE_MANAGEMENT' => 5,
                'DOCUMENTATION' => 10,
                'SUPPORT_CAPABILITY' => 15,
                'BUSINESS_CONTINUITY' => 10
            ],
            'CATEGORY_5' => [
                'QUALITY_SYSTEM' => 20,
                'TECHNICAL_CAPABILITY' => 15,
                'REGULATORY_COMPLIANCE' => 15,
                'SECURITY_CONTROLS' => 10,
                'CHANGE_MANAGEMENT' => 15,
                'DOCUMENTATION' => 15,
                'SUPPORT_CAPABILITY' => 10,
                'VALIDATION_SUPPORT' => 20
            ]
        ];
        
        return $weights[$gamp_category] ?? array_fill_keys(array_keys(self::EVALUATION_CRITERIA), 10);
    }

    private function calculateQualificationScore($qualification_id)
    {
        $stmt = $this->db->prepare("
            SELECT SUM(score * weight / 100) as weighted_score,
                   SUM(weight) as total_weight
            FROM gamp5_qualification_criteria 
            WHERE qualification_id = ? AND score IS NOT NULL
        ");
        $stmt->execute([$qualification_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['total_weight'] > 0) {
            return round(($result['weighted_score'] / $result['total_weight']) * 100, 2);
        }
        
        return 0;
    }

    private function determineQualificationRecommendation($qualification_id)
    {
        $qualification = $this->getQualification($qualification_id);
        $score = $qualification['total_score'] ?? 0;
        
        if ($score >= 85) {
            return [
                'status' => 'APPROVED',
                'recommendation' => 'Proveedor aprobado. Cumple con todos los criterios requeridos.'
            ];
        } elseif ($score >= 70) {
            return [
                'status' => 'CONDITIONALLY_APPROVED', 
                'recommendation' => 'Proveedor aprobado condicionalmente. Requiere seguimiento en áreas específicas.'
            ];
        } elseif ($score >= 50) {
            return [
                'status' => 'RE_EVALUATION',
                'recommendation' => 'Requiere re-evaluación. Deficiencias significativas identificadas.'
            ];
        } else {
            return [
                'status' => 'REJECTED',
                'recommendation' => 'No cumple con los criterios mínimos requeridos.'
            ];
        }
    }

    private function getQualification($qualification_id)
    {
        $stmt = $this->db->prepare("SELECT * FROM gamp5_supplier_qualifications WHERE id = ?");
        $stmt->execute([$qualification_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function addToApprovedSuppliers($supplier_id, $qualification_id)
    {
        $stmt = $this->db->prepare("
            INSERT INTO gamp5_approved_suppliers (
                supplier_id, qualification_id, approval_date,
                valid_until, approved_by, created_at
            ) VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 2 YEAR), ?, NOW())
        ");
        $stmt->execute([$supplier_id, $qualification_id, $this->usuario_id]);
    }

    private function updateSupplierStatus($supplier_id, $status)
    {
        $supplier_status = ($status === 'APPROVED' || $status === 'CONDITIONALLY_APPROVED') ? 'APPROVED' : 'PENDING';
        
        $stmt = $this->db->prepare("
            UPDATE gamp5_suppliers 
            SET status = ?, updated_by = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$supplier_status, $this->usuario_id, $supplier_id]);
    }

    private function logAuditEvent($event_type, $reference_id, $details = [])
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO gamp5_supplier_audit_log (
                    reference_id, event_type, user_id, event_details,
                    ip_address, user_agent, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $reference_id,
                $event_type,
                $this->usuario_id,
                json_encode($details),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            // Log error but don't fail the main operation
            error_log("Error logging supplier audit event: " . $e->getMessage());
        }
    }
}