<?php
/**
 * Sistema de Gestión Documental GAMP 5
 * 
 * Maneja documentación GxP con firmas electrónicas, control de versiones
 * y workflows de aprobación conforme a 21 CFR Part 11 y GAMP 5.
 */

require_once dirname(__DIR__, 3) . '/Core/db_config.php';
require_once dirname(__DIR__, 3) . '/Core/permissions.php';

class DocumentManagementSystem
{
    private $db;
    private $usuario_id;
    
    // Tipos de documentos GAMP 5
    const DOCUMENT_TYPES = [
        'URS' => 'User Requirements Specification',
        'FS' => 'Functional Specification',
        'DS' => 'Design Specification',
        'SOP' => 'Standard Operating Procedure',
        'VMP' => 'Validation Master Plan',
        'IQ' => 'Installation Qualification',
        'OQ' => 'Operational Qualification',
        'PQ' => 'Performance Qualification',
        'PROTOCOL' => 'Validation Protocol',
        'REPORT' => 'Validation Report',
        'RISK_ASSESSMENT' => 'Risk Assessment',
        'CHANGE_CONTROL' => 'Change Control',
        'TRAINING' => 'Training Material',
        'PROCEDURE' => 'Procedure',
        'POLICY' => 'Policy Document',
        'MANUAL' => 'User Manual',
        'SPECIFICATION' => 'Technical Specification',
        'CERTIFICATE' => 'Certificate/Calibration',
        'AUDIT_REPORT' => 'Audit Report',
        'CORRECTIVE_ACTION' => 'Corrective Action Plan'
    ];
    
    // Estados del documento
    const DOCUMENT_STATUS = [
        'DRAFT' => 'Borrador',
        'UNDER_REVIEW' => 'En Revisión',
        'APPROVED' => 'Aprobado',
        'EFFECTIVE' => 'Vigente',
        'SUPERSEDED' => 'Superado',
        'RETIRED' => 'Retirado',
        'ARCHIVED' => 'Archivado'
    ];
    
    // Niveles de clasificación
    const CLASSIFICATION_LEVELS = [
        'PUBLIC' => 'Público',
        'INTERNAL' => 'Interno',
        'CONFIDENTIAL' => 'Confidencial',
        'RESTRICTED' => 'Restringido',
        'GXP_CRITICAL' => 'GxP Crítico'
    ];
    
    // Tipos de firma electrónica
    const SIGNATURE_TYPES = [
        'AUTHOR' => 'Autor',
        'REVIEWER' => 'Revisor',
        'APPROVER' => 'Aprobador',
        'QA_APPROVAL' => 'Aprobación QA',
        'TECHNICAL_APPROVAL' => 'Aprobación Técnica',
        'MANAGEMENT_APPROVAL' => 'Aprobación Gerencial'
    ];
    
    // Motivos de revisión
    const REVISION_REASONS = [
        'SCHEDULED_REVIEW' => 'Revisión Programada',
        'CHANGE_CONTROL' => 'Control de Cambios',
        'REGULATORY_UPDATE' => 'Actualización Regulatoria',
        'ERROR_CORRECTION' => 'Corrección de Error',
        'PROCESS_IMPROVEMENT' => 'Mejora de Proceso',
        'AUDIT_FINDING' => 'Hallazgo de Auditoría',
        'DEVIATION_INVESTIGATION' => 'Investigación de Desviación'
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
     * Crear un nuevo documento
     */
    public function createDocument($data)
    {
        try {
            $this->db->beginTransaction();
            
            // Validar datos requeridos
            $required_fields = ['title', 'document_type', 'classification'];
            foreach ($required_fields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    throw new Exception("Campo requerido faltante: $field");
                }
            }
            
            // Generar número de documento único
            $document_number = $this->generateDocumentNumber($data['document_type']);
            
            // Insertar documento principal
            $stmt = $this->db->prepare("
                INSERT INTO gamp5_documents (
                    document_number, title, document_type, classification,
                    description, author_id, department, system_id,
                    retention_period, effective_date, review_date,
                    status, version, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'DRAFT', '1.0', ?, NOW())
            ");
            
            $stmt->execute([
                $document_number,
                $data['title'],
                $data['document_type'],
                $data['classification'],
                $data['description'] ?? '',
                $this->usuario_id,
                $data['department'] ?? '',
                $data['system_id'] ?? null,
                $data['retention_period'] ?? 7, // años por defecto
                $data['effective_date'] ?? null,
                $data['review_date'] ?? null,
                $this->usuario_id
            ]);
            
            $document_id = $this->db->lastInsertId();
            
            // Crear versión inicial
            $this->createDocumentVersion($document_id, [
                'version' => '1.0',
                'content' => $data['content'] ?? '',
                'file_path' => $data['file_path'] ?? null,
                'change_reason' => 'Initial Creation'
            ]);
            
            // Registrar auditoría
            $this->logAuditEvent('DOCUMENT_CREATED', $document_id, [
                'document_number' => $document_number,
                'title' => $data['title'],
                'type' => $data['document_type']
            ]);
            
            $this->db->commit();
            return $document_id;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Crear nueva versión de documento
     */
    public function createDocumentVersion($document_id, $data)
    {
        try {
            // Validar que el documento existe
            $document = $this->getDocument($document_id);
            if (!$document) {
                throw new Exception("Documento no encontrado");
            }
            
            // Calcular nueva versión
            $new_version = $this->calculateNextVersion($document_id, $data['version_type'] ?? 'MINOR');
            
            // Insertar nueva versión
            $stmt = $this->db->prepare("
                INSERT INTO gamp5_document_versions (
                    document_id, version, content, file_path, file_hash,
                    change_reason, change_description, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $file_hash = null;
            if (isset($data['file_path']) && file_exists($data['file_path'])) {
                $file_hash = hash_file('sha256', $data['file_path']);
            }
            
            $stmt->execute([
                $document_id,
                $new_version,
                $data['content'] ?? '',
                $data['file_path'] ?? null,
                $file_hash,
                $data['change_reason'] ?? '',
                $data['change_description'] ?? '',
                $this->usuario_id
            ]);
            
            $version_id = $this->db->lastInsertId();
            
            // Actualizar versión actual del documento
            $stmt = $this->db->prepare("
                UPDATE gamp5_documents 
                SET version = ?, updated_by = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$new_version, $this->usuario_id, $document_id]);
            
            // Registrar auditoría
            $this->logAuditEvent('DOCUMENT_VERSION_CREATED', $document_id, [
                'version' => $new_version,
                'change_reason' => $data['change_reason'] ?? ''
            ]);
            
            return $version_id;
            
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Firmar documento electrónicamente
     */
    public function signDocument($document_id, $signature_data)
    {
        try {
            $this->db->beginTransaction();
            
            // Validar datos de firma
            $required_fields = ['signature_type', 'signature_meaning', 'password'];
            foreach ($required_fields as $field) {
                if (!isset($signature_data[$field]) || empty($signature_data[$field])) {
                    throw new Exception("Campo requerido para firma: $field");
                }
            }
            
            // Validar documento
            $document = $this->getDocument($document_id);
            if (!$document) {
                throw new Exception("Documento no encontrado");
            }
            
            // Verificar contraseña del usuario (21 CFR Part 11)
            if (!$this->verifyUserPassword($signature_data['password'])) {
                throw new Exception("Contraseña incorrecta para firma electrónica");
            }
            
            // Verificar que no exista firma duplicada del mismo tipo
            $stmt = $this->db->prepare("
                SELECT id FROM gamp5_document_signatures 
                WHERE document_id = ? AND signer_id = ? AND signature_type = ? AND version = ?
            ");
            $stmt->execute([$document_id, $this->usuario_id, $signature_data['signature_type'], $document['version']]);
            
            if ($stmt->fetch()) {
                throw new Exception("Ya existe una firma de este tipo para la versión actual");
            }
            
            // Generar hash de la firma
            $signature_hash = $this->generateSignatureHash($document_id, $signature_data);
            
            // Insertar firma electrónica
            $stmt = $this->db->prepare("
                INSERT INTO gamp5_document_signatures (
                    document_id, signer_id, signature_type, signature_meaning,
                    signature_hash, version, ip_address, user_agent,
                    signed_at, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $stmt->execute([
                $document_id,
                $this->usuario_id,
                $signature_data['signature_type'],
                $signature_data['signature_meaning'],
                $signature_hash,
                $document['version'],
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            
            $signature_id = $this->db->lastInsertId();
            
            // Verificar si el documento está completamente firmado
            $this->checkDocumentApprovalStatus($document_id);
            
            // Registrar auditoría
            $this->logAuditEvent('DOCUMENT_SIGNED', $document_id, [
                'signature_type' => $signature_data['signature_type'],
                'signer_id' => $this->usuario_id,
                'version' => $document['version']
            ]);
            
            $this->db->commit();
            return $signature_id;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Iniciar workflow de aprobación
     */
    public function startApprovalWorkflow($document_id, $workflow_data)
    {
        try {
            $this->db->beginTransaction();
            
            // Validar documento
            $document = $this->getDocument($document_id);
            if (!$document) {
                throw new Exception("Documento no encontrado");
            }
            
            // Crear workflow de aprobación
            $stmt = $this->db->prepare("
                INSERT INTO gamp5_document_workflows (
                    document_id, workflow_type, priority, due_date,
                    instructions, created_by, created_at, status
                ) VALUES (?, 'APPROVAL', ?, ?, ?, ?, NOW(), 'ACTIVE')
            ");
            
            $stmt->execute([
                $document_id,
                $workflow_data['priority'] ?? 'MEDIUM',
                $workflow_data['due_date'] ?? null,
                $workflow_data['instructions'] ?? '',
                $this->usuario_id
            ]);
            
            $workflow_id = $this->db->lastInsertId();
            
            // Crear pasos del workflow
            $steps = $workflow_data['steps'] ?? $this->getDefaultApprovalSteps($document['document_type']);
            
            foreach ($steps as $order => $step) {
                $stmt = $this->db->prepare("
                    INSERT INTO gamp5_workflow_steps (
                        workflow_id, step_order, step_type, assignee_id,
                        role_required, due_date, instructions, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDING')
                ");
                
                $stmt->execute([
                    $workflow_id,
                    $order + 1,
                    $step['type'] ?? 'APPROVAL',
                    $step['assignee_id'] ?? null,
                    $step['role_required'] ?? null,
                    $step['due_date'] ?? null,
                    $step['instructions'] ?? ''
                ]);
            }
            
            // Actualizar estado del documento
            $stmt = $this->db->prepare("
                UPDATE gamp5_documents 
                SET status = 'UNDER_REVIEW', updated_by = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$this->usuario_id, $document_id]);
            
            // Registrar auditoría
            $this->logAuditEvent('WORKFLOW_STARTED', $document_id, [
                'workflow_id' => $workflow_id,
                'workflow_type' => 'APPROVAL'
            ]);
            
            $this->db->commit();
            return $workflow_id;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Procesar paso del workflow
     */
    public function processWorkflowStep($workflow_id, $step_id, $action_data)
    {
        try {
            $this->db->beginTransaction();
            
            // Validar paso del workflow
            $stmt = $this->db->prepare("
                SELECT ws.*, w.document_id 
                FROM gamp5_workflow_steps ws
                JOIN gamp5_document_workflows w ON ws.workflow_id = w.id
                WHERE ws.id = ? AND w.id = ?
            ");
            $stmt->execute([$step_id, $workflow_id]);
            $step = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$step) {
                throw new Exception("Paso de workflow no encontrado");
            }
            
            if ($step['status'] !== 'PENDING') {
                throw new Exception("Este paso ya ha sido procesado");
            }
            
            // Procesar acción
            $action = $action_data['action']; // APPROVE, REJECT, REQUEST_CHANGES
            $comments = $action_data['comments'] ?? '';
            
            // Actualizar paso
            $stmt = $this->db->prepare("
                UPDATE gamp5_workflow_steps 
                SET status = ?, completed_by = ?, completed_at = NOW(), 
                    comments = ?, action_taken = ?
                WHERE id = ?
            ");
            
            $status = ($action === 'APPROVE') ? 'COMPLETED' : 'REJECTED';
            $stmt->execute([$status, $this->usuario_id, $comments, $action, $step_id]);
            
            // Si es aprobación, continuar con siguiente paso
            if ($action === 'APPROVE') {
                $this->advanceWorkflow($workflow_id);
            } else {
                // Si es rechazo, cancelar workflow
                $this->cancelWorkflow($workflow_id, $comments);
            }
            
            // Registrar auditoría
            $this->logAuditEvent('WORKFLOW_STEP_PROCESSED', $step['document_id'], [
                'workflow_id' => $workflow_id,
                'step_id' => $step_id,
                'action' => $action,
                'processor_id' => $this->usuario_id
            ]);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Obtener documento por ID
     */
    public function getDocument($document_id)
    {
        $stmt = $this->db->prepare("
            SELECT d.*, u.nombre as author_name
            FROM gamp5_documents d
            LEFT JOIN usuarios u ON d.author_id = u.id
            WHERE d.id = ?
        ");
        $stmt->execute([$document_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener documentos con filtros
     */
    public function getDocuments($filters = [])
    {
        $where_conditions = ['1=1'];
        $params = [];
        
        if (!empty($filters['document_type'])) {
            $where_conditions[] = "d.document_type = ?";
            $params[] = $filters['document_type'];
        }
        
        if (!empty($filters['status'])) {
            $where_conditions[] = "d.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['classification'])) {
            $where_conditions[] = "d.classification = ?";
            $params[] = $filters['classification'];
        }
        
        if (!empty($filters['system_id'])) {
            $where_conditions[] = "d.system_id = ?";
            $params[] = $filters['system_id'];
        }
        
        if (!empty($filters['search'])) {
            $where_conditions[] = "(d.title LIKE ? OR d.document_number LIKE ? OR d.description LIKE ?)";
            $search_term = '%' . $filters['search'] . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        $limit = isset($filters['limit']) ? "LIMIT " . (int)$filters['limit'] : "LIMIT 100";
        $offset = isset($filters['offset']) ? "OFFSET " . (int)$filters['offset'] : "";
        
        $stmt = $this->db->prepare("
            SELECT d.*, u.nombre as author_name,
                   (SELECT COUNT(*) FROM gamp5_document_signatures ds WHERE ds.document_id = d.id AND ds.version = d.version) as signature_count
            FROM gamp5_documents d
            LEFT JOIN usuarios u ON d.author_id = u.id
            WHERE " . implode(' AND ', $where_conditions) . "
            ORDER BY d.updated_at DESC
            $limit $offset
        ");
        
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener versiones de un documento
     */
    public function getDocumentVersions($document_id)
    {
        $stmt = $this->db->prepare("
            SELECT dv.*, u.nombre as created_by_name
            FROM gamp5_document_versions dv
            LEFT JOIN usuarios u ON dv.created_by = u.id
            WHERE dv.document_id = ?
            ORDER BY dv.created_at DESC
        ");
        $stmt->execute([$document_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener firmas de un documento
     */
    public function getDocumentSignatures($document_id, $version = null)
    {
        $where_version = $version ? "AND ds.version = ?" : "";
        $params = [$document_id];
        if ($version) $params[] = $version;
        
        $stmt = $this->db->prepare("
            SELECT ds.*, u.nombre as signer_name, u.email as signer_email
            FROM gamp5_document_signatures ds
            LEFT JOIN usuarios u ON ds.signer_id = u.id
            WHERE ds.document_id = ? $where_version
            ORDER BY ds.signed_at ASC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener workflow activo de un documento
     */
    public function getDocumentWorkflow($document_id)
    {
        $stmt = $this->db->prepare("
            SELECT w.*, 
                   (SELECT COUNT(*) FROM gamp5_workflow_steps ws WHERE ws.workflow_id = w.id) as total_steps,
                   (SELECT COUNT(*) FROM gamp5_workflow_steps ws WHERE ws.workflow_id = w.id AND ws.status = 'COMPLETED') as completed_steps
            FROM gamp5_document_workflows w
            WHERE w.document_id = ? AND w.status = 'ACTIVE'
            ORDER BY w.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$document_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener pasos del workflow
     */
    public function getWorkflowSteps($workflow_id)
    {
        $stmt = $this->db->prepare("
            SELECT ws.*, u1.nombre as assignee_name, u2.nombre as completed_by_name
            FROM gamp5_workflow_steps ws
            LEFT JOIN usuarios u1 ON ws.assignee_id = u1.id
            LEFT JOIN usuarios u2 ON ws.completed_by = u2.id
            WHERE ws.workflow_id = ?
            ORDER BY ws.step_order ASC
        ");
        $stmt->execute([$workflow_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener reportes y métricas
     */
    public function getDocumentMetrics($filters = [])
    {
        $date_filter = "";
        $params = [];
        
        if (!empty($filters['start_date'])) {
            $date_filter .= " AND d.created_at >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $date_filter .= " AND d.created_at <= ?";
            $params[] = $filters['end_date'];
        }
        
        // Documentos por tipo
        $stmt = $this->db->prepare("
            SELECT document_type, COUNT(*) as count
            FROM gamp5_documents d
            WHERE 1=1 $date_filter
            GROUP BY document_type
            ORDER BY count DESC
        ");
        $stmt->execute($params);
        $by_type = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Documentos por estado
        $stmt = $this->db->prepare("
            SELECT status, COUNT(*) as count
            FROM gamp5_documents d
            WHERE 1=1 $date_filter
            GROUP BY status
            ORDER BY count DESC
        ");
        $stmt->execute($params);
        $by_status = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Documentos por clasificación
        $stmt = $this->db->prepare("
            SELECT classification, COUNT(*) as count
            FROM gamp5_documents d
            WHERE 1=1 $date_filter
            GROUP BY classification
            ORDER BY count DESC
        ");
        $stmt->execute($params);
        $by_classification = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Métricas generales
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_documents,
                COUNT(CASE WHEN status = 'EFFECTIVE' THEN 1 END) as effective_documents,
                COUNT(CASE WHEN status = 'UNDER_REVIEW' THEN 1 END) as pending_approval,
                COUNT(CASE WHEN review_date <= CURDATE() THEN 1 END) as due_for_review,
                AVG(DATEDIFF(NOW(), created_at)) as avg_age_days
            FROM gamp5_documents d
            WHERE 1=1 $date_filter
        ");
        $stmt->execute($params);
        $general_metrics = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'by_type' => $by_type,
            'by_status' => $by_status,
            'by_classification' => $by_classification,
            'general' => $general_metrics
        ];
    }

    // Métodos auxiliares privados
    
    private function generateDocumentNumber($document_type)
    {
        $prefix = substr($document_type, 0, 3);
        $year = date('Y');
        
        // Obtener siguiente número secuencial
        $stmt = $this->db->prepare("
            SELECT MAX(CAST(SUBSTRING(document_number, -4) AS UNSIGNED)) as max_num
            FROM gamp5_documents 
            WHERE document_number LIKE ?
        ");
        $stmt->execute(["$prefix-$year-%"]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $next_num = ($result['max_num'] ?? 0) + 1;
        
        return sprintf("%s-%s-%04d", $prefix, $year, $next_num);
    }

    private function calculateNextVersion($document_id, $version_type = 'MINOR')
    {
        $stmt = $this->db->prepare("SELECT version FROM gamp5_documents WHERE id = ?");
        $stmt->execute([$document_id]);
        $current_version = $stmt->fetchColumn();
        
        list($major, $minor) = explode('.', $current_version);
        
        if ($version_type === 'MAJOR') {
            return ($major + 1) . '.0';
        } else {
            return $major . '.' . ($minor + 1);
        }
    }

    private function verifyUserPassword($password)
    {
        $stmt = $this->db->prepare("SELECT password FROM usuarios WHERE id = ?");
        $stmt->execute([$this->usuario_id]);
        $stored_password = $stmt->fetchColumn();
        
        return password_verify($password, $stored_password);
    }

    private function generateSignatureHash($document_id, $signature_data)
    {
        $document = $this->getDocument($document_id);
        $data_to_hash = [
            'document_id' => $document_id,
            'document_number' => $document['document_number'],
            'version' => $document['version'],
            'signer_id' => $this->usuario_id,
            'signature_type' => $signature_data['signature_type'],
            'timestamp' => time()
        ];
        
        return hash('sha256', json_encode($data_to_hash));
    }

    private function checkDocumentApprovalStatus($document_id)
    {
        // Verificar si todas las firmas requeridas están completas
        $required_signatures = $this->getRequiredSignatures($document_id);
        $current_signatures = $this->getDocumentSignatures($document_id);
        
        $signature_types = array_column($current_signatures, 'signature_type');
        
        $all_signed = true;
        foreach ($required_signatures as $required) {
            if (!in_array($required, $signature_types)) {
                $all_signed = false;
                break;
            }
        }
        
        if ($all_signed) {
            $stmt = $this->db->prepare("
                UPDATE gamp5_documents 
                SET status = 'APPROVED', updated_by = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$this->usuario_id, $document_id]);
        }
    }

    private function getRequiredSignatures($document_id)
    {
        $document = $this->getDocument($document_id);
        
        // Definir firmas requeridas por tipo de documento
        $signature_requirements = [
            'SOP' => ['AUTHOR', 'REVIEWER', 'QA_APPROVAL'],
            'PROTOCOL' => ['AUTHOR', 'REVIEWER', 'QA_APPROVAL'],
            'REPORT' => ['AUTHOR', 'REVIEWER', 'QA_APPROVAL'],
            'URS' => ['AUTHOR', 'TECHNICAL_APPROVAL'],
            'POLICY' => ['AUTHOR', 'MANAGEMENT_APPROVAL', 'QA_APPROVAL']
        ];
        
        return $signature_requirements[$document['document_type']] ?? ['AUTHOR', 'APPROVER'];
    }

    private function getDefaultApprovalSteps($document_type)
    {
        $default_steps = [
            'SOP' => [
                ['type' => 'REVIEW', 'role_required' => 'quality_reviewer'],
                ['type' => 'APPROVAL', 'role_required' => 'quality_approver']
            ],
            'PROTOCOL' => [
                ['type' => 'TECHNICAL_REVIEW', 'role_required' => 'technical_reviewer'],
                ['type' => 'QA_REVIEW', 'role_required' => 'quality_reviewer'],
                ['type' => 'FINAL_APPROVAL', 'role_required' => 'quality_approver']
            ]
        ];
        
        return $default_steps[$document_type] ?? [
            ['type' => 'REVIEW', 'role_required' => 'reviewer'],
            ['type' => 'APPROVAL', 'role_required' => 'approver']
        ];
    }

    private function advanceWorkflow($workflow_id)
    {
        // Obtener siguiente paso pendiente
        $stmt = $this->db->prepare("
            SELECT * FROM gamp5_workflow_steps 
            WHERE workflow_id = ? AND status = 'PENDING'
            ORDER BY step_order ASC
            LIMIT 1
        ");
        $stmt->execute([$workflow_id]);
        $next_step = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$next_step) {
            // No hay más pasos - completar workflow
            $this->completeWorkflow($workflow_id);
        }
    }

    private function completeWorkflow($workflow_id)
    {
        $stmt = $this->db->prepare("
            UPDATE gamp5_document_workflows 
            SET status = 'COMPLETED', completed_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$workflow_id]);
        
        // Actualizar estado del documento
        $stmt = $this->db->prepare("
            UPDATE gamp5_documents d
            JOIN gamp5_document_workflows w ON d.id = w.document_id
            SET d.status = 'APPROVED', d.updated_by = ?, d.updated_at = NOW()
            WHERE w.id = ?
        ");
        $stmt->execute([$this->usuario_id, $workflow_id]);
    }

    private function cancelWorkflow($workflow_id, $reason)
    {
        $stmt = $this->db->prepare("
            UPDATE gamp5_document_workflows 
            SET status = 'CANCELLED', cancelled_reason = ?, completed_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$reason, $workflow_id]);
        
        // Regresar documento a borrador
        $stmt = $this->db->prepare("
            UPDATE gamp5_documents d
            JOIN gamp5_document_workflows w ON d.id = w.document_id
            SET d.status = 'DRAFT', d.updated_by = ?, d.updated_at = NOW()
            WHERE w.id = ?
        ");
        $stmt->execute([$this->usuario_id, $workflow_id]);
    }

    private function logAuditEvent($event_type, $document_id, $details = [])
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO gamp5_document_audit_log (
                    document_id, event_type, user_id, event_details,
                    ip_address, user_agent, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $document_id,
                $event_type,
                $this->usuario_id,
                json_encode($details),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            // Log error but don't fail the main operation
            error_log("Error logging audit event: " . $e->getMessage());
        }
    }
}