<?php
/**
 * Sistema de Gestión de Tickets con Matriz de Riesgo
 * Conforme a GAMP 5 y normativas GxP
 * 
 * Esta clase maneja la creación, clasificación y gestión de tickets
 * basados en una matriz de riesgo para sistemas computarizados.
 */

require_once dirname(__DIR__, 2) . '/Core/db.php';
require_once dirname(__DIR__, 2) . '/Core/permissions.php';

class TicketManager {
    private $conn;
    private $logFile;
    
    // Matriz de riesgo basada en GAMP 5
    private const RISK_MATRIX = [
        'Crítico' => ['weight' => 5, 'priority' => 1, 'escalation_time' => 1], // 1 hora
        'Alto' => ['weight' => 4, 'priority' => 2, 'escalation_time' => 4],    // 4 horas
        'Medio' => ['weight' => 3, 'priority' => 3, 'escalation_time' => 24],  // 24 horas
        'Bajo' => ['weight' => 2, 'priority' => 4, 'escalation_time' => 72],   // 72 horas
        'Mínimo' => ['weight' => 1, 'priority' => 5, 'escalation_time' => 168] // 1 semana
    ];
    
    // Categorías de tickets basadas en GxP
    private const TICKET_CATEGORIES = [
        'CRITICAL_SYSTEM_FAILURE' => 'Falla Crítica del Sistema',
        'DATA_INTEGRITY_ISSUE' => 'Problema de Integridad de Datos',
        'SECURITY_VULNERABILITY' => 'Vulnerabilidad de Seguridad',
        'REGULATORY_COMPLIANCE' => 'Cumplimiento Regulatorio',
        'PERFORMANCE_ISSUE' => 'Problema de Rendimiento',
        'USER_INTERFACE_BUG' => 'Error de Interfaz de Usuario',
        'FEATURE_REQUEST' => 'Solicitud de Funcionalidad',
        'DOCUMENTATION_ISSUE' => 'Problema de Documentación',
        'TRAINING_REQUEST' => 'Solicitud de Capacitación',
        'OTHER' => 'Otro'
    ];
    
    public function __construct() {
        $this->conn = DatabaseManager::getConnection();
        $this->logFile = dirname(__DIR__, 3) . '/storage/logs/ticket_system.log';
        $this->initializeTables();
    }
    
    /**
     * Inicializa las tablas necesarias para el sistema de tickets
     */
    private function initializeTables() {
        $tables = [
            'tickets' => "
                CREATE TABLE IF NOT EXISTS tickets (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ticket_number VARCHAR(20) UNIQUE NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    description TEXT NOT NULL,
                    category ENUM('CRITICAL_SYSTEM_FAILURE', 'DATA_INTEGRITY_ISSUE', 'SECURITY_VULNERABILITY', 
                                 'REGULATORY_COMPLIANCE', 'PERFORMANCE_ISSUE', 'USER_INTERFACE_BUG', 
                                 'FEATURE_REQUEST', 'DOCUMENTATION_ISSUE', 'TRAINING_REQUEST', 'OTHER') NOT NULL,
                    impact ENUM('Crítico', 'Alto', 'Medio', 'Bajo', 'Mínimo') NOT NULL,
                    probability ENUM('Crítico', 'Alto', 'Medio', 'Bajo', 'Mínimo') NOT NULL,
                    risk_level ENUM('Crítico', 'Alto', 'Medio', 'Bajo', 'Mínimo') NOT NULL,
                    priority INT NOT NULL,
                    status ENUM('Abierto', 'En Progreso', 'En Revisión', 'Resuelto', 'Cerrado', 'Escalado') DEFAULT 'Abierto',
                    severity ENUM('Crítico', 'Alto', 'Medio', 'Bajo', 'Mínimo') NOT NULL,
                    created_by INT NOT NULL,
                    assigned_to INT DEFAULT NULL,
                    escalated_to INT DEFAULT NULL,
                    estimated_resolution_time INT, -- en horas
                    actual_resolution_time INT, -- en horas
                    root_cause TEXT,
                    resolution_description TEXT,
                    corrective_actions TEXT,
                    preventive_actions TEXT,
                    attachments JSON,
                    gxp_classification ENUM('GCP', 'GLP', 'GMP', 'GDP', 'GVP', 'No Aplica') DEFAULT 'No Aplica',
                    regulatory_impact BOOLEAN DEFAULT FALSE,
                    validation_impact BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    resolved_at TIMESTAMP NULL,
                    closed_at TIMESTAMP NULL,
                    INDEX idx_status (status),
                    INDEX idx_risk_level (risk_level),
                    INDEX idx_priority (priority),
                    INDEX idx_created_by (created_by),
                    INDEX idx_assigned_to (assigned_to),
                    INDEX idx_category (category),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            
            'ticket_history' => "
                CREATE TABLE IF NOT EXISTS ticket_history (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ticket_id INT NOT NULL,
                    action_type ENUM('CREATED', 'UPDATED', 'STATUS_CHANGED', 'ASSIGNED', 'ESCALATED', 
                                    'COMMENTED', 'RESOLVED', 'CLOSED', 'REOPENED') NOT NULL,
                    field_name VARCHAR(100),
                    old_value TEXT,
                    new_value TEXT,
                    comment TEXT,
                    performed_by INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
                    INDEX idx_ticket_id (ticket_id),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            
            'ticket_attachments' => "
                CREATE TABLE IF NOT EXISTS ticket_attachments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ticket_id INT NOT NULL,
                    filename VARCHAR(255) NOT NULL,
                    original_filename VARCHAR(255) NOT NULL,
                    file_path VARCHAR(500) NOT NULL,
                    file_size INT NOT NULL,
                    mime_type VARCHAR(100) NOT NULL,
                    uploaded_by INT NOT NULL,
                    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
                    INDEX idx_ticket_id (ticket_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            
            'risk_assessment_criteria' => "
                CREATE TABLE IF NOT EXISTS risk_assessment_criteria (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    criteria_name VARCHAR(100) NOT NULL,
                    category VARCHAR(50) NOT NULL,
                    impact_weight DECIMAL(3,2) NOT NULL,
                    probability_weight DECIMAL(3,2) NOT NULL,
                    description TEXT,
                    gxp_relevance ENUM('GCP', 'GLP', 'GMP', 'GDP', 'GVP', 'General') NOT NULL,
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            "
        ];
        
        foreach ($tables as $tableName => $sql) {
            if (!$this->conn->query($sql)) {
                $this->logError("Error creando tabla $tableName: " . $this->conn->error);
                throw new Exception("Error inicializando tabla $tableName");
            }
        }
        
        $this->insertDefaultRiskCriteria();
    }
    
    /**
     * Inserta criterios de evaluación de riesgo por defecto
     */
    private function insertDefaultRiskCriteria() {
        $criteria = [
            ['Sistema de Gestión de Datos', 'CRITICAL_SYSTEM_FAILURE', 0.9, 0.8, 'Criterios para fallos críticos del sistema', 'General'],
            ['Integridad de Datos', 'DATA_INTEGRITY_ISSUE', 0.95, 0.7, 'Criterios para problemas de integridad de datos', 'GLP'],
            ['Seguridad del Sistema', 'SECURITY_VULNERABILITY', 0.85, 0.6, 'Criterios para vulnerabilidades de seguridad', 'General'],
            ['Cumplimiento Regulatorio', 'REGULATORY_COMPLIANCE', 0.9, 0.5, 'Criterios para cumplimiento regulatorio', 'GMP'],
            ['Rendimiento del Sistema', 'PERFORMANCE_ISSUE', 0.6, 0.7, 'Criterios para problemas de rendimiento', 'General']
        ];
        
        foreach ($criteria as $criterion) {
            $checkSql = "SELECT COUNT(*) as count FROM risk_assessment_criteria WHERE criteria_name = ?";
            $checkStmt = $this->conn->prepare($checkSql);
            $checkStmt->bind_param("s", $criterion[0]);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $count = $result->fetch_assoc()['count'];
            
            if ($count == 0) {
                $insertSql = "INSERT INTO risk_assessment_criteria 
                             (criteria_name, category, impact_weight, probability_weight, description, gxp_relevance) 
                             VALUES (?, ?, ?, ?, ?, ?)";
                $insertStmt = $this->conn->prepare($insertSql);
                $insertStmt->bind_param("ssddss", $criterion[0], $criterion[1], $criterion[2], $criterion[3], $criterion[4], $criterion[5]);
                $insertStmt->execute();
            }
        }
    }
    
    /**
     * Crea un nuevo ticket con evaluación automática de riesgo
     */
    public function createTicket($data) {
        try {
            // Validar datos requeridos
            $this->validateTicketData($data);
            
            // Generar número de ticket único
            $ticketNumber = $this->generateTicketNumber();
            
            // Calcular nivel de riesgo basado en la matriz
            $riskLevel = $this->calculateRiskLevel($data['impact'], $data['probability'], $data['category']);
            $priority = self::RISK_MATRIX[$riskLevel]['priority'];
            
            // Insertar ticket
            $sql = "INSERT INTO tickets (
                ticket_number, title, description, category, impact, probability, 
                risk_level, priority, severity, created_by, gxp_classification, 
                regulatory_impact, validation_impact, estimated_resolution_time
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($sql);
            
            $estimatedTime = self::RISK_MATRIX[$riskLevel]['escalation_time'];
            $regulatoryImpact = isset($data['regulatory_impact']) ? (bool)$data['regulatory_impact'] : false;
            $validationImpact = isset($data['validation_impact']) ? (bool)$data['validation_impact'] : false;
            $gxpClass = $data['gxp_classification'] ?? 'No Aplica';
            
            $stmt->bind_param("sssssssisssbii", 
                $ticketNumber, $data['title'], $data['description'], $data['category'],
                $data['impact'], $data['probability'], $riskLevel, $priority,
                $data['severity'], $data['created_by'], $gxpClass,
                $regulatoryImpact, $validationImpact, $estimatedTime
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Error insertando ticket: " . $stmt->error);
            }
            
            $ticketId = $this->conn->insert_id;
            
            // Registrar en historial
            $this->addToHistory($ticketId, 'CREATED', null, null, json_encode($data), 
                              'Ticket creado', $data['created_by']);
            
            // Asignación automática basada en riesgo
            $this->autoAssignTicket($ticketId, $riskLevel, $data['category']);
            
            // Enviar notificaciones si es necesario
            if (in_array($riskLevel, ['Crítico', 'Alto'])) {
                $this->sendHighPriorityNotification($ticketId, $riskLevel);
            }
            
            $this->logActivity("Ticket $ticketNumber creado con riesgo $riskLevel");
            
            return [
                'success' => true,
                'ticket_id' => $ticketId,
                'ticket_number' => $ticketNumber,
                'risk_level' => $riskLevel,
                'priority' => $priority
            ];
            
        } catch (Exception $e) {
            $this->logError("Error creando ticket: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Calcula el nivel de riesgo basado en impacto, probabilidad y categoría
     */
    private function calculateRiskLevel($impact, $probability, $category) {
        $impactWeight = self::RISK_MATRIX[$impact]['weight'];
        $probabilityWeight = self::RISK_MATRIX[$probability]['weight'];
        
        // Obtener peso específico de la categoría
        $categorySql = "SELECT impact_weight, probability_weight FROM risk_assessment_criteria 
                       WHERE category = ? AND is_active = TRUE LIMIT 1";
        $categoryStmt = $this->conn->prepare($categorySql);
        $categoryStmt->bind_param("s", $category);
        $categoryStmt->execute();
        $categoryResult = $categoryStmt->get_result();
        
        if ($categoryData = $categoryResult->fetch_assoc()) {
            $impactWeight *= $categoryData['impact_weight'];
            $probabilityWeight *= $categoryData['probability_weight'];
        }
        
        // Calcular riesgo compuesto
        $riskScore = ($impactWeight + $probabilityWeight) / 2;
        
        // Mapear score a nivel de riesgo
        if ($riskScore >= 4.5) return 'Crítico';
        if ($riskScore >= 3.5) return 'Alto';
        if ($riskScore >= 2.5) return 'Medio';
        if ($riskScore >= 1.5) return 'Bajo';
        return 'Mínimo';
    }
    
    /**
     * Asignación automática de tickets basada en riesgo y categoría
     */
    private function autoAssignTicket($ticketId, $riskLevel, $category) {
        // Lógica de asignación automática (puede ser personalizada)
        $assignmentRules = [
            'CRITICAL_SYSTEM_FAILURE' => ['role' => 'system_admin', 'team' => 'infrastructure'],
            'DATA_INTEGRITY_ISSUE' => ['role' => 'data_manager', 'team' => 'quality'],
            'SECURITY_VULNERABILITY' => ['role' => 'security_officer', 'team' => 'security'],
            'REGULATORY_COMPLIANCE' => ['role' => 'qa_manager', 'team' => 'quality']
        ];
        
        if (isset($assignmentRules[$category])) {
            $rule = $assignmentRules[$category];
            $assigneeSql = "SELECT id FROM usuarios WHERE role = ? AND active = 1 
                           ORDER BY workload ASC LIMIT 1";
            $assigneeStmt = $this->conn->prepare($assigneeSql);
            $assigneeStmt->bind_param("s", $rule['role']);
            $assigneeStmt->execute();
            $assigneeResult = $assigneeStmt->get_result();
            
            if ($assignee = $assigneeResult->fetch_assoc()) {
                $this->assignTicket($ticketId, $assignee['id'], $_SESSION['usuario_id'] ?? 1);
            }
        }
    }
    
    /**
     * Asigna un ticket a un usuario
     */
    public function assignTicket($ticketId, $assignedTo, $assignedBy) {
        $sql = "UPDATE tickets SET assigned_to = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $assignedTo, $ticketId);
        
        if ($stmt->execute()) {
            $this->addToHistory($ticketId, 'ASSIGNED', 'assigned_to', null, $assignedTo, 
                              "Ticket asignado", $assignedBy);
            return true;
        }
        return false;
    }
    
    /**
     * Actualiza el estado de un ticket
     */
    public function updateTicketStatus($ticketId, $newStatus, $userId, $comment = null) {
        // Validar transición de estado
        if (!$this->isValidStatusTransition($ticketId, $newStatus)) {
            return ['success' => false, 'error' => 'Transición de estado no válida'];
        }
        
        $sql = "UPDATE tickets SET status = ?, updated_at = CURRENT_TIMESTAMP";
        $params = [$newStatus];
        $types = "s";
        
        // Si se está resolviendo o cerrando, registrar timestamp
        if ($newStatus === 'Resuelto') {
            $sql .= ", resolved_at = CURRENT_TIMESTAMP";
        } elseif ($newStatus === 'Cerrado') {
            $sql .= ", closed_at = CURRENT_TIMESTAMP";
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $ticketId;
        $types .= "i";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            $this->addToHistory($ticketId, 'STATUS_CHANGED', 'status', null, $newStatus, 
                              $comment, $userId);
            return ['success' => true];
        }
        
        return ['success' => false, 'error' => 'Error actualizando estado'];
    }
    
    /**
     * Obtiene tickets con filtros y paginación
     */
    public function getTickets($filters = [], $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT t.*, u1.nombre as created_by_name, u2.nombre as assigned_to_name 
                FROM tickets t 
                LEFT JOIN usuarios u1 ON t.created_by = u1.id 
                LEFT JOIN usuarios u2 ON t.assigned_to = u2.id 
                WHERE 1=1";
        
        $params = [];
        $types = "";
        
        // Aplicar filtros
        if (!empty($filters['status'])) {
            $sql .= " AND t.status = ?";
            $params[] = $filters['status'];
            $types .= "s";
        }
        
        if (!empty($filters['risk_level'])) {
            $sql .= " AND t.risk_level = ?";
            $params[] = $filters['risk_level'];
            $types .= "s";
        }
        
        if (!empty($filters['category'])) {
            $sql .= " AND t.category = ?";
            $params[] = $filters['category'];
            $types .= "s";
        }
        
        if (!empty($filters['assigned_to'])) {
            $sql .= " AND t.assigned_to = ?";
            $params[] = $filters['assigned_to'];
            $types .= "i";
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND t.created_at >= ?";
            $params[] = $filters['date_from'];
            $types .= "s";
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND t.created_at <= ?";
            $params[] = $filters['date_to'];
            $types .= "s";
        }
        
        // Ordenar por prioridad y fecha
        $sql .= " ORDER BY t.priority ASC, t.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt = $this->conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $tickets = [];
        while ($row = $result->fetch_assoc()) {
            $tickets[] = $row;
        }
        
        return $tickets;
    }
    
    /**
     * Obtiene estadísticas del sistema de tickets
     */
    public function getTicketStats() {
        $stats = [];
        
        // Tickets por estado
        $statusSql = "SELECT status, COUNT(*) as count FROM tickets GROUP BY status";
        $statusResult = $this->conn->query($statusSql);
        $stats['by_status'] = [];
        while ($row = $statusResult->fetch_assoc()) {
            $stats['by_status'][$row['status']] = $row['count'];
        }
        
        // Tickets por nivel de riesgo
        $riskSql = "SELECT risk_level, COUNT(*) as count FROM tickets GROUP BY risk_level";
        $riskResult = $this->conn->query($riskSql);
        $stats['by_risk'] = [];
        while ($row = $riskResult->fetch_assoc()) {
            $stats['by_risk'][$row['risk_level']] = $row['count'];
        }
        
        // Tickets por categoría
        $categorySql = "SELECT category, COUNT(*) as count FROM tickets GROUP BY category";
        $categoryResult = $this->conn->query($categorySql);
        $stats['by_category'] = [];
        while ($row = $categoryResult->fetch_assoc()) {
            $stats['by_category'][$row['category']] = $row['count'];
        }
        
        // Métricas de tiempo
        $timeSql = "SELECT 
                      AVG(actual_resolution_time) as avg_resolution_time,
                      COUNT(CASE WHEN status = 'Resuelto' THEN 1 END) as resolved_count,
                      COUNT(CASE WHEN resolved_at <= DATE_ADD(created_at, INTERVAL estimated_resolution_time HOUR) THEN 1 END) as on_time_resolution
                    FROM tickets WHERE resolved_at IS NOT NULL";
        $timeResult = $this->conn->query($timeSql);
        $stats['time_metrics'] = $timeResult->fetch_assoc();
        
        return $stats;
    }
    
    /**
     * Valida transiciones de estado válidas
     */
    private function isValidStatusTransition($ticketId, $newStatus) {
        $getCurrentStatusSql = "SELECT status FROM tickets WHERE id = ?";
        $stmt = $this->conn->prepare($getCurrentStatusSql);
        $stmt->bind_param("i", $ticketId);
        $stmt->execute();
        $result = $stmt->get_result();
        $currentStatus = $result->fetch_assoc()['status'];
        
        $validTransitions = [
            'Abierto' => ['En Progreso', 'Escalado', 'Cerrado'],
            'En Progreso' => ['En Revisión', 'Escalado', 'Resuelto'],
            'En Revisión' => ['En Progreso', 'Resuelto', 'Escalado'],
            'Resuelto' => ['Cerrado', 'En Progreso'],
            'Escalado' => ['En Progreso', 'Resuelto'],
            'Cerrado' => ['En Progreso'] // Solo para casos excepcionales
        ];
        
        return in_array($newStatus, $validTransitions[$currentStatus] ?? []);
    }
    
    /**
     * Genera número único de ticket
     */
    private function generateTicketNumber() {
        $prefix = 'TK-' . date('Y') . '-';
        $sql = "SELECT COUNT(*) + 1 as next_number FROM tickets WHERE ticket_number LIKE ?";
        $stmt = $this->conn->prepare($sql);
        $likePattern = $prefix . '%';
        $stmt->bind_param("s", $likePattern);
        $stmt->execute();
        $result = $stmt->get_result();
        $nextNumber = $result->fetch_assoc()['next_number'];
        
        return $prefix . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Valida datos del ticket
     */
    private function validateTicketData($data) {
        $required = ['title', 'description', 'category', 'impact', 'probability', 'severity', 'created_by'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Campo requerido faltante: $field");
            }
        }
        
        if (!array_key_exists($data['category'], self::TICKET_CATEGORIES)) {
            throw new Exception("Categoría de ticket no válida");
        }
        
        $validLevels = ['Crítico', 'Alto', 'Medio', 'Bajo', 'Mínimo'];
        if (!in_array($data['impact'], $validLevels) || 
            !in_array($data['probability'], $validLevels) || 
            !in_array($data['severity'], $validLevels)) {
            throw new Exception("Nivel de impacto, probabilidad o severidad no válido");
        }
    }
    
    /**
     * Añade entrada al historial del ticket
     */
    private function addToHistory($ticketId, $actionType, $fieldName, $oldValue, $newValue, $comment, $userId) {
        $sql = "INSERT INTO ticket_history (ticket_id, action_type, field_name, old_value, new_value, comment, performed_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("isssssi", $ticketId, $actionType, $fieldName, $oldValue, $newValue, $comment, $userId);
        $stmt->execute();
    }
    
    /**
     * Envía notificación para tickets de alta prioridad
     */
    private function sendHighPriorityNotification($ticketId, $riskLevel) {
        // Implementar notificaciones por email/Slack
        $this->logActivity("Notificación enviada para ticket ID $ticketId con riesgo $riskLevel");
    }
    
    /**
     * Registra actividad en el log
     */
    private function logActivity($message) {
        $timestamp = date('Y-m-d H:i:s');
        $userId = $_SESSION['usuario_id'] ?? 'Sistema';
        $logMessage = "[$timestamp] Usuario: $userId - $message\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Registra errores en el log
     */
    private function logError($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] ERROR: $message\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Obtiene historial de un ticket
     */
    public function getTicketHistory($ticketId) {
        $sql = "SELECT th.*, u.nombre as performed_by_name 
                FROM ticket_history th 
                LEFT JOIN usuarios u ON th.performed_by = u.id 
                WHERE th.ticket_id = ? 
                ORDER BY th.created_at DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $ticketId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
        
        return $history;
    }
    
    /**
     * Obtiene categorías de tickets disponibles
     */
    public function getTicketCategories() {
        return self::TICKET_CATEGORIES;
    }
    
    /**
     * Obtiene la matriz de riesgo
     */
    public function getRiskMatrix() {
        return self::RISK_MATRIX;
    }
}
?>