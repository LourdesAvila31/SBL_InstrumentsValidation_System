<?php
declare(strict_types=1);

require_once __DIR__ . '/DeveloperAuth.php';

/**
 * Sistema de Gestión de Proveedores
 * Maneja contratos, SLAs y auditorías de proveedores externos
 */
class VendorManager
{
    private mysqli $conn;
    private DeveloperAuth $auth;
    
    const VENDOR_STATUSES = ['active', 'inactive', 'suspended', 'terminated'];
    const CONTRACT_TYPES = ['service', 'license', 'maintenance', 'development', 'consulting'];
    const SLA_STATUSES = ['active', 'breached', 'warning', 'suspended'];
    
    public function __construct(?mysqli $connection = null)
    {
        global $conn;
        $this->conn = $connection ?? $conn ?? DatabaseManager::getConnection();
        $this->auth = new DeveloperAuth($this->conn);
    }

    /**
     * Registra un nuevo proveedor
     */
    public function createVendor(array $vendorData): array
    {
        if (!$this->auth->hasPrivateAccess('vendors')) {
            throw new Exception('Acceso denegado para crear proveedores');
        }

        $requiredFields = ['name', 'contact_email', 'service_type'];
        foreach ($requiredFields as $field) {
            if (empty($vendorData[$field])) {
                throw new Exception("Campo requerido faltante: {$field}");
            }
        }

        $developerInfo = $this->auth->getDeveloperInfo();
        
        $vendor = [
            'name' => trim($vendorData['name']),
            'legal_name' => trim($vendorData['legal_name'] ?? $vendorData['name']),
            'contact_email' => trim($vendorData['contact_email']),
            'contact_phone' => trim($vendorData['contact_phone'] ?? ''),
            'service_type' => $vendorData['service_type'],
            'status' => 'active',
            'address' => trim($vendorData['address'] ?? ''),
            'tax_id' => trim($vendorData['tax_id'] ?? ''),
            'website' => trim($vendorData['website'] ?? ''),
            'primary_contact_name' => trim($vendorData['primary_contact_name'] ?? ''),
            'secondary_contact_name' => trim($vendorData['secondary_contact_name'] ?? ''),
            'secondary_contact_email' => trim($vendorData['secondary_contact_email'] ?? ''),
            'notes' => trim($vendorData['notes'] ?? ''),
            'risk_level' => $vendorData['risk_level'] ?? 'medium',
            'certification_info' => json_encode($vendorData['certifications'] ?? []),
            'created_by' => $developerInfo['id'],
            'created_at' => date('Y-m-d H:i:s')
        ];

        $stmt = $this->conn->prepare(
            'INSERT INTO vendors (name, legal_name, contact_email, contact_phone, service_type, 
                                status, address, tax_id, website, primary_contact_name, 
                                secondary_contact_name, secondary_contact_email, notes, 
                                risk_level, certification_info, created_by, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        if (!$stmt) {
            throw new Exception('Error preparando consulta de proveedor');
        }

        $stmt->bind_param(
            'ssssssssssssssssis',
            $vendor['name'],
            $vendor['legal_name'],
            $vendor['contact_email'],
            $vendor['contact_phone'],
            $vendor['service_type'],
            $vendor['status'],
            $vendor['address'],
            $vendor['tax_id'],
            $vendor['website'],
            $vendor['primary_contact_name'],
            $vendor['secondary_contact_name'],
            $vendor['secondary_contact_email'],
            $vendor['notes'],
            $vendor['risk_level'],
            $vendor['certification_info'],
            $vendor['created_by'],
            $vendor['created_at']
        );

        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception('Error creando proveedor');
        }

        $vendorId = $this->conn->insert_id;
        $stmt->close();

        $this->auth->logDeveloperActivity('vendor_created', [
            'vendor_id' => $vendorId,
            'name' => $vendor['name'],
            'service_type' => $vendor['service_type']
        ]);

        return array_merge($vendor, ['id' => $vendorId]);
    }

    /**
     * Crea un nuevo contrato con proveedor
     */
    public function createContract(array $contractData): array
    {
        if (!$this->auth->hasPrivateAccess('vendors')) {
            throw new Exception('Acceso denegado para crear contratos');
        }

        $requiredFields = ['vendor_id', 'contract_type', 'start_date', 'end_date', 'value'];
        foreach ($requiredFields as $field) {
            if (empty($contractData[$field])) {
                throw new Exception("Campo requerido faltante: {$field}");
            }
        }

        if (!in_array($contractData['contract_type'], self::CONTRACT_TYPES)) {
            throw new Exception('Tipo de contrato inválido');
        }

        $developerInfo = $this->auth->getDeveloperInfo();
        
        $contract = [
            'vendor_id' => (int)$contractData['vendor_id'],
            'contract_number' => $contractData['contract_number'] ?? $this->generateContractNumber(),
            'contract_type' => $contractData['contract_type'],
            'title' => trim($contractData['title']),
            'description' => trim($contractData['description'] ?? ''),
            'start_date' => $contractData['start_date'],
            'end_date' => $contractData['end_date'],
            'value' => (float)$contractData['value'],
            'currency' => $contractData['currency'] ?? 'USD',
            'payment_terms' => trim($contractData['payment_terms'] ?? ''),
            'renewal_terms' => trim($contractData['renewal_terms'] ?? ''),
            'termination_clause' => trim($contractData['termination_clause'] ?? ''),
            'deliverables' => json_encode($contractData['deliverables'] ?? []),
            'key_milestones' => json_encode($contractData['key_milestones'] ?? []),
            'contract_manager' => $contractData['contract_manager'] ?? $developerInfo['id'],
            'status' => 'active',
            'created_by' => $developerInfo['id'],
            'created_at' => date('Y-m-d H:i:s')
        ];

        $stmt = $this->conn->prepare(
            'INSERT INTO vendor_contracts (vendor_id, contract_number, contract_type, title, 
                                         description, start_date, end_date, value, currency, 
                                         payment_terms, renewal_terms, termination_clause, 
                                         deliverables, key_milestones, contract_manager, 
                                         status, created_by, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        if (!$stmt) {
            throw new Exception('Error preparando consulta de contrato');
        }

        $stmt->bind_param(
            'issssssdsssssssiis',
            $contract['vendor_id'],
            $contract['contract_number'],
            $contract['contract_type'],
            $contract['title'],
            $contract['description'],
            $contract['start_date'],
            $contract['end_date'],
            $contract['value'],
            $contract['currency'],
            $contract['payment_terms'],
            $contract['renewal_terms'],
            $contract['termination_clause'],
            $contract['deliverables'],
            $contract['key_milestones'],
            $contract['contract_manager'],
            $contract['status'],
            $contract['created_by'],
            $contract['created_at']
        );

        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception('Error creando contrato');
        }

        $contractId = $this->conn->insert_id;
        $stmt->close();

        $this->auth->logDeveloperActivity('contract_created', [
            'contract_id' => $contractId,
            'vendor_id' => $contract['vendor_id'],
            'contract_type' => $contract['contract_type'],
            'value' => $contract['value']
        ]);

        return array_merge($contract, ['id' => $contractId]);
    }

    /**
     * Crea un acuerdo de nivel de servicio (SLA)
     */
    public function createSLA(array $slaData): array
    {
        if (!$this->auth->hasPrivateAccess('vendors')) {
            throw new Exception('Acceso denegado para crear SLAs');
        }

        $requiredFields = ['vendor_id', 'service_name', 'availability_target', 'response_time_target'];
        foreach ($requiredFields as $field) {
            if (empty($slaData[$field])) {
                throw new Exception("Campo requerido faltante: {$field}");
            }
        }

        $developerInfo = $this->auth->getDeveloperInfo();
        
        $sla = [
            'vendor_id' => (int)$slaData['vendor_id'],
            'contract_id' => $slaData['contract_id'] ?? null,
            'service_name' => trim($slaData['service_name']),
            'service_description' => trim($slaData['service_description'] ?? ''),
            'availability_target' => (float)$slaData['availability_target'],
            'response_time_target' => (int)$slaData['response_time_target'], // en minutos
            'resolution_time_target' => (int)($slaData['resolution_time_target'] ?? 0), // en horas
            'performance_metrics' => json_encode($slaData['performance_metrics'] ?? []),
            'penalties' => json_encode($slaData['penalties'] ?? []),
            'measurement_period' => $slaData['measurement_period'] ?? 'monthly',
            'reporting_frequency' => $slaData['reporting_frequency'] ?? 'monthly',
            'escalation_procedures' => trim($slaData['escalation_procedures'] ?? ''),
            'status' => 'active',
            'effective_date' => $slaData['effective_date'] ?? date('Y-m-d'),
            'review_date' => $slaData['review_date'] ?? date('Y-m-d', strtotime('+1 year')),
            'created_by' => $developerInfo['id'],
            'created_at' => date('Y-m-d H:i:s')
        ];

        $stmt = $this->conn->prepare(
            'INSERT INTO vendor_slas (vendor_id, contract_id, service_name, service_description, 
                                    availability_target, response_time_target, resolution_time_target, 
                                    performance_metrics, penalties, measurement_period, 
                                    reporting_frequency, escalation_procedures, status, 
                                    effective_date, review_date, created_by, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        if (!$stmt) {
            throw new Exception('Error preparando consulta de SLA');
        }

        $stmt->bind_param(
            'iissdisssssssssiss',
            $sla['vendor_id'],
            $sla['contract_id'],
            $sla['service_name'],
            $sla['service_description'],
            $sla['availability_target'],
            $sla['response_time_target'],
            $sla['resolution_time_target'],
            $sla['performance_metrics'],
            $sla['penalties'],
            $sla['measurement_period'],
            $sla['reporting_frequency'],
            $sla['escalation_procedures'],
            $sla['status'],
            $sla['effective_date'],
            $sla['review_date'],
            $sla['created_by'],
            $sla['created_at']
        );

        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception('Error creando SLA');
        }

        $slaId = $this->conn->insert_id;
        $stmt->close();

        $this->auth->logDeveloperActivity('sla_created', [
            'sla_id' => $slaId,
            'vendor_id' => $sla['vendor_id'],
            'service_name' => $sla['service_name'],
            'availability_target' => $sla['availability_target']
        ]);

        return array_merge($sla, ['id' => $slaId]);
    }

    /**
     * Registra métricas de rendimiento de SLA
     */
    public function recordSLAMetrics(int $slaId, array $metricsData): bool
    {
        if (!$this->auth->hasPrivateAccess('vendors')) {
            throw new Exception('Acceso denegado para registrar métricas');
        }

        $sla = $this->getSLA($slaId);
        if (!$sla) {
            throw new Exception('SLA no encontrado');
        }

        $metrics = [
            'sla_id' => $slaId,
            'measurement_period_start' => $metricsData['period_start'],
            'measurement_period_end' => $metricsData['period_end'],
            'actual_availability' => (float)$metricsData['actual_availability'],
            'average_response_time' => (int)$metricsData['average_response_time'],
            'average_resolution_time' => (int)($metricsData['average_resolution_time'] ?? 0),
            'incidents_count' => (int)($metricsData['incidents_count'] ?? 0),
            'breaches_count' => (int)($metricsData['breaches_count'] ?? 0),
            'performance_score' => $this->calculatePerformanceScore($sla, $metricsData),
            'additional_metrics' => json_encode($metricsData['additional_metrics'] ?? []),
            'notes' => trim($metricsData['notes'] ?? ''),
            'recorded_by' => $this->auth->getDeveloperInfo()['id'],
            'recorded_at' => date('Y-m-d H:i:s')
        ];

        $stmt = $this->conn->prepare(
            'INSERT INTO sla_metrics (sla_id, measurement_period_start, measurement_period_end, 
                                    actual_availability, average_response_time, average_resolution_time, 
                                    incidents_count, breaches_count, performance_score, 
                                    additional_metrics, notes, recorded_by, recorded_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        if (!$stmt) {
            throw new Exception('Error preparando consulta de métricas');
        }

        $stmt->bind_param(
            'issdiiiiidsiss',
            $metrics['sla_id'],
            $metrics['measurement_period_start'],
            $metrics['measurement_period_end'],
            $metrics['actual_availability'],
            $metrics['average_response_time'],
            $metrics['average_resolution_time'],
            $metrics['incidents_count'],
            $metrics['breaches_count'],
            $metrics['performance_score'],
            $metrics['additional_metrics'],
            $metrics['notes'],
            $metrics['recorded_by'],
            $metrics['recorded_at']
        );

        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            // Actualizar estado del SLA si hay incumplimientos
            $this->updateSLAStatus($slaId, $metrics);

            $this->auth->logDeveloperActivity('sla_metrics_recorded', [
                'sla_id' => $slaId,
                'period' => $metrics['measurement_period_start'] . ' to ' . $metrics['measurement_period_end'],
                'performance_score' => $metrics['performance_score']
            ]);
        }

        return $result;
    }

    /**
     * Programa una auditoría de proveedor
     */
    public function scheduleVendorAudit(array $auditData): array
    {
        if (!$this->auth->hasPrivateAccess('vendors')) {
            throw new Exception('Acceso denegado para programar auditorías');
        }

        $requiredFields = ['vendor_id', 'audit_type', 'scheduled_date'];
        foreach ($requiredFields as $field) {
            if (empty($auditData[$field])) {
                throw new Exception("Campo requerido faltante: {$field}");
            }
        }

        $developerInfo = $this->auth->getDeveloperInfo();
        
        $audit = [
            'vendor_id' => (int)$auditData['vendor_id'],
            'audit_type' => $auditData['audit_type'], // compliance, security, performance, financial
            'title' => trim($auditData['title']),
            'description' => trim($auditData['description'] ?? ''),
            'scheduled_date' => $auditData['scheduled_date'],
            'estimated_duration_hours' => (int)($auditData['estimated_duration_hours'] ?? 8),
            'audit_scope' => json_encode($auditData['audit_scope'] ?? []),
            'checklist_items' => json_encode($auditData['checklist_items'] ?? []),
            'assigned_auditor' => $auditData['assigned_auditor'] ?? $developerInfo['id'],
            'status' => 'scheduled',
            'priority' => $auditData['priority'] ?? 'medium',
            'created_by' => $developerInfo['id'],
            'created_at' => date('Y-m-d H:i:s')
        ];

        $stmt = $this->conn->prepare(
            'INSERT INTO vendor_audits (vendor_id, audit_type, title, description, scheduled_date, 
                                      estimated_duration_hours, audit_scope, checklist_items, 
                                      assigned_auditor, status, priority, created_by, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        if (!$stmt) {
            throw new Exception('Error preparando consulta de auditoría');
        }

        $stmt->bind_param(
            'issssisssssiss',
            $audit['vendor_id'],
            $audit['audit_type'],
            $audit['title'],
            $audit['description'],
            $audit['scheduled_date'],
            $audit['estimated_duration_hours'],
            $audit['audit_scope'],
            $audit['checklist_items'],
            $audit['assigned_auditor'],
            $audit['status'],
            $audit['priority'],
            $audit['created_by'],
            $audit['created_at']
        );

        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception('Error programando auditoría');
        }

        $auditId = $this->conn->insert_id;
        $stmt->close();

        $this->auth->logDeveloperActivity('vendor_audit_scheduled', [
            'audit_id' => $auditId,
            'vendor_id' => $audit['vendor_id'],
            'audit_type' => $audit['audit_type'],
            'scheduled_date' => $audit['scheduled_date']
        ]);

        return array_merge($audit, ['id' => $auditId]);
    }

    /**
     * Obtiene lista de proveedores con filtros
     */
    public function getVendors(array $filters = []): array
    {
        if (!$this->auth->hasPrivateAccess('vendors')) {
            throw new Exception('Acceso denegado para ver proveedores');
        }

        $where = ['1=1'];
        $params = [];
        $types = '';

        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
            $types .= 's';
        }

        if (!empty($filters['service_type'])) {
            $where[] = 'service_type = ?';
            $params[] = $filters['service_type'];
            $types .= 's';
        }

        if (!empty($filters['risk_level'])) {
            $where[] = 'risk_level = ?';
            $params[] = $filters['risk_level'];
            $types .= 's';
        }

        if (!empty($filters['search'])) {
            $where[] = '(name LIKE ? OR legal_name LIKE ? OR contact_email LIKE ?)';
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'sss';
        }

        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 50;
        $offset = isset($filters['offset']) ? (int)$filters['offset'] : 0;

        $sql = 'SELECT v.*, u.nombre as created_by_name
                FROM vendors v
                LEFT JOIN usuarios u ON u.id = v.created_by
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY v.name ASC
                LIMIT ? OFFSET ?';

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Error preparando consulta de proveedores');
        }

        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $vendors = [];
        while ($row = $result->fetch_assoc()) {
            $row['certifications'] = json_decode($row['certification_info'] ?? '[]', true);
            unset($row['certification_info']);
            $vendors[] = $row;
        }
        $stmt->close();

        return $vendors;
    }

    /**
     * Obtiene métricas de gestión de proveedores
     */
    public function getVendorMetrics(): array
    {
        if (!$this->auth->hasPrivateAccess('vendors')) {
            throw new Exception('Acceso denegado para ver métricas');
        }

        // Proveedores por estado
        $stmt = $this->conn->prepare(
            'SELECT status, COUNT(*) as count FROM vendors GROUP BY status'
        );
        $stmt->execute();
        $result = $stmt->get_result();
        $byStatus = [];
        while ($row = $result->fetch_assoc()) {
            $byStatus[$row['status']] = (int)$row['count'];
        }
        $stmt->close();

        // Contratos próximos a vencer
        $stmt = $this->conn->prepare(
            'SELECT COUNT(*) as expiring_contracts 
             FROM vendor_contracts 
             WHERE end_date <= DATE_ADD(NOW(), INTERVAL 60 DAY) AND status = "active"'
        );
        $stmt->execute();
        $expiringContracts = (int)$stmt->get_result()->fetch_assoc()['expiring_contracts'];
        $stmt->close();

        // SLAs en incumplimiento
        $stmt = $this->conn->prepare(
            'SELECT COUNT(*) as breached_slas 
             FROM vendor_slas 
             WHERE status = "breached"'
        );
        $stmt->execute();
        $breachedSLAs = (int)$stmt->get_result()->fetch_assoc()['breached_slas'];
        $stmt->close();

        // Auditorías pendientes
        $stmt = $this->conn->prepare(
            'SELECT COUNT(*) as pending_audits 
             FROM vendor_audits 
             WHERE status = "scheduled" AND scheduled_date <= DATE_ADD(NOW(), INTERVAL 30 DAY)'
        );
        $stmt->execute();
        $pendingAudits = (int)$stmt->get_result()->fetch_assoc()['pending_audits'];
        $stmt->close();

        // Valor total de contratos activos
        $stmt = $this->conn->prepare(
            'SELECT SUM(value) as total_contract_value 
             FROM vendor_contracts 
             WHERE status = "active"'
        );
        $stmt->execute();
        $totalContractValue = (float)($stmt->get_result()->fetch_assoc()['total_contract_value'] ?? 0);
        $stmt->close();

        return [
            'vendors_by_status' => $byStatus,
            'total_vendors' => array_sum($byStatus),
            'expiring_contracts' => $expiringContracts,
            'breached_slas' => $breachedSLAs,
            'pending_audits' => $pendingAudits,
            'total_contract_value' => $totalContractValue,
            'vendor_risk_distribution' => $this->getVendorRiskDistribution(),
            'sla_performance_summary' => $this->getSLAPerformanceSummary()
        ];
    }

    /**
     * Métodos auxiliares privados
     */
    private function generateContractNumber(): string
    {
        $year = date('Y');
        $stmt = $this->conn->prepare(
            'SELECT COUNT(*) as count FROM vendor_contracts WHERE YEAR(created_at) = ?'
        );
        $stmt->bind_param('s', $year);
        $stmt->execute();
        $count = (int)$stmt->get_result()->fetch_assoc()['count'] + 1;
        $stmt->close();
        
        return sprintf('CON-%s-%04d', $year, $count);
    }

    private function calculatePerformanceScore(array $sla, array $metrics): float
    {
        $availabilityScore = min(100, ($metrics['actual_availability'] / $sla['availability_target']) * 100);
        $responseTimeScore = min(100, ($sla['response_time_target'] / max(1, $metrics['average_response_time'])) * 100);
        
        // Peso: 60% disponibilidad, 40% tiempo de respuesta
        return round(($availabilityScore * 0.6) + ($responseTimeScore * 0.4), 2);
    }

    private function updateSLAStatus(int $slaId, array $metrics): void
    {
        $newStatus = 'active';
        
        if ($metrics['breaches_count'] > 0) {
            $newStatus = 'breached';
        } elseif ($metrics['performance_score'] < 85) {
            $newStatus = 'warning';
        }

        if ($newStatus !== 'active') {
            $stmt = $this->conn->prepare('UPDATE vendor_slas SET status = ? WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('si', $newStatus, $slaId);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    private function getSLA(int $slaId): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM vendor_slas WHERE id = ?');
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $slaId);
        $stmt->execute();
        $result = $stmt->get_result();
        $sla = $result->fetch_assoc();
        $stmt->close();

        if ($sla) {
            $sla['performance_metrics'] = json_decode($sla['performance_metrics'] ?? '[]', true);
            $sla['penalties'] = json_decode($sla['penalties'] ?? '[]', true);
        }

        return $sla;
    }

    private function getVendorRiskDistribution(): array
    {
        $stmt = $this->conn->prepare(
            'SELECT risk_level, COUNT(*) as count FROM vendors WHERE status = "active" GROUP BY risk_level'
        );
        $stmt->execute();
        $result = $stmt->get_result();
        
        $distribution = [];
        while ($row = $result->fetch_assoc()) {
            $distribution[$row['risk_level']] = (int)$row['count'];
        }
        $stmt->close();

        return $distribution;
    }

    private function getSLAPerformanceSummary(): array
    {
        $stmt = $this->conn->prepare(
            'SELECT 
                AVG(performance_score) as avg_performance,
                COUNT(CASE WHEN performance_score >= 95 THEN 1 END) as excellent_count,
                COUNT(CASE WHEN performance_score >= 85 AND performance_score < 95 THEN 1 END) as good_count,
                COUNT(CASE WHEN performance_score < 85 THEN 1 END) as poor_count
             FROM sla_metrics 
             WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)'
        );
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return [
            'average_performance' => round((float)($result['avg_performance'] ?? 0), 2),
            'excellent_performers' => (int)($result['excellent_count'] ?? 0),
            'good_performers' => (int)($result['good_count'] ?? 0),
            'poor_performers' => (int)($result['poor_count'] ?? 0)
        ];
    }
}