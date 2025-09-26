<?php
declare(strict_types=1);

require_once __DIR__ . '/DeveloperAuth.php';

/**
 * Sistema de Gestión de Incidencias y Administración de Cambios
 * Permite registrar, clasificar, hacer seguimiento y aprobar cambios en el sistema
 */
class IncidentChangeManager
{
    private mysqli $conn;
    private DeveloperAuth $auth;
    
    const INCIDENT_SEVERITIES = ['low', 'medium', 'high', 'critical'];
    const INCIDENT_STATUSES = ['open', 'in_progress', 'resolved', 'closed'];
    const CHANGE_TYPES = ['configuration', 'feature', 'bugfix', 'security', 'maintenance'];
    const CHANGE_STATUSES = ['draft', 'pending_approval', 'approved', 'in_progress', 'implemented', 'rolled_back'];
    
    public function __construct(?mysqli $connection = null)
    {
        global $conn;
        $this->conn = $connection ?? $conn ?? DatabaseManager::getConnection();
        $this->auth = new DeveloperAuth($this->conn);
    }

    /**
     * Crea una nueva incidencia
     */
    public function createIncident(array $incidentData): array
    {
        if (!$this->auth->hasPrivateAccess('incidents')) {
            throw new Exception('Acceso denegado para crear incidencias');
        }

        $requiredFields = ['title', 'description', 'severity'];
        foreach ($requiredFields as $field) {
            if (empty($incidentData[$field])) {
                throw new Exception("Campo requerido faltante: {$field}");
            }
        }

        if (!in_array($incidentData['severity'], self::INCIDENT_SEVERITIES)) {
            throw new Exception('Severidad de incidencia inválida');
        }

        $developerInfo = $this->auth->getDeveloperInfo();
        
        $incident = [
            'title' => trim($incidentData['title']),
            'description' => trim($incidentData['description']),
            'severity' => $incidentData['severity'],
            'status' => 'open',
            'reporter_id' => $developerInfo['id'],
            'assigned_to' => $incidentData['assigned_to'] ?? null,
            'category' => $incidentData['category'] ?? 'general',
            'priority' => $this->calculatePriority($incidentData['severity']),
            'external_reference' => $incidentData['external_reference'] ?? null,
            'affected_systems' => json_encode($incidentData['affected_systems'] ?? []),
            'created_at' => date('Y-m-d H:i:s')
        ];

        $stmt = $this->conn->prepare(
            'INSERT INTO incidents (title, description, severity, status, reporter_id, assigned_to, 
                                  category, priority, external_reference, affected_systems, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        if (!$stmt) {
            throw new Exception('Error preparando consulta de incidencia');
        }

        $stmt->bind_param(
            'ssssiisissss',
            $incident['title'],
            $incident['description'],
            $incident['severity'],
            $incident['status'],
            $incident['reporter_id'],
            $incident['assigned_to'],
            $incident['category'],
            $incident['priority'],
            $incident['external_reference'],
            $incident['affected_systems'],
            $incident['created_at']
        );

        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception('Error creando incidencia');
        }

        $incidentId = $this->conn->insert_id;
        $stmt->close();

        // Crear historial inicial
        $this->addIncidentHistory($incidentId, 'created', [
            'severity' => $incident['severity'],
            'reporter' => $developerInfo['nombre_completo']
        ]);

        // Registrar en auditoría
        $this->auth->logDeveloperActivity('incident_created', [
            'incident_id' => $incidentId,
            'severity' => $incident['severity'],
            'title' => $incident['title']
        ]);

        // Enviar alertas automáticas si es crítico
        if ($incident['severity'] === 'critical') {
            $this->sendCriticalIncidentAlert($incidentId);
        }

        return array_merge($incident, ['id' => $incidentId]);
    }

    /**
     * Actualiza una incidencia existente
     */
    public function updateIncident(int $incidentId, array $updateData): bool
    {
        if (!$this->auth->hasPrivateAccess('incidents')) {
            throw new Exception('Acceso denegado para actualizar incidencias');
        }

        // Obtener incidencia actual
        $currentIncident = $this->getIncident($incidentId);
        if (!$currentIncident) {
            throw new Exception('Incidencia no encontrada');
        }

        $allowedFields = ['status', 'assigned_to', 'resolution_notes', 'severity'];
        $updateFields = [];
        $updateValues = [];
        $types = '';

        foreach ($allowedFields as $field) {
            if (isset($updateData[$field])) {
                if ($field === 'status' && !in_array($updateData[$field], self::INCIDENT_STATUSES)) {
                    throw new Exception('Estado de incidencia inválido');
                }
                if ($field === 'severity' && !in_array($updateData[$field], self::INCIDENT_SEVERITIES)) {
                    throw new Exception('Severidad de incidencia inválida');
                }

                $updateFields[] = "{$field} = ?";
                $updateValues[] = $updateData[$field];
                $types .= ($field === 'assigned_to') ? 'i' : 's';
            }
        }

        if (empty($updateFields)) {
            return true; // No hay cambios
        }

        // Agregar campos de actualización
        $updateFields[] = 'updated_at = ?';
        $updateValues[] = date('Y-m-d H:i:s');
        $types .= 's';

        // Si se está cerrando, agregar resolved_at
        if (isset($updateData['status']) && in_array($updateData['status'], ['resolved', 'closed'])) {
            $updateFields[] = 'resolved_at = ?';
            $updateValues[] = date('Y-m-d H:i:s');
            $types .= 's';
        }

        $updateValues[] = $incidentId;
        $types .= 'i';

        $sql = 'UPDATE incidents SET ' . implode(', ', $updateFields) . ' WHERE id = ?';
        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            throw new Exception('Error preparando actualización de incidencia');
        }

        $stmt->bind_param($types, ...$updateValues);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            // Registrar cambios en historial
            $changes = [];
            foreach ($allowedFields as $field) {
                if (isset($updateData[$field]) && $updateData[$field] !== $currentIncident[$field]) {
                    $changes[$field] = [
                        'from' => $currentIncident[$field],
                        'to' => $updateData[$field]
                    ];
                }
            }

            if (!empty($changes)) {
                $this->addIncidentHistory($incidentId, 'updated', ['changes' => $changes]);
                
                $this->auth->logDeveloperActivity('incident_updated', [
                    'incident_id' => $incidentId,
                    'changes' => $changes
                ]);
            }
        }

        return $result;
    }

    /**
     * Crea una nueva solicitud de cambio
     */
    public function createChangeRequest(array $changeData): array
    {
        if (!$this->auth->hasPrivateAccess('changes')) {
            throw new Exception('Acceso denegado para crear solicitudes de cambio');
        }

        $requiredFields = ['title', 'description', 'change_type', 'justification'];
        foreach ($requiredFields as $field) {
            if (empty($changeData[$field])) {
                throw new Exception("Campo requerido faltante: {$field}");
            }
        }

        if (!in_array($changeData['change_type'], self::CHANGE_TYPES)) {
            throw new Exception('Tipo de cambio inválido');
        }

        $developerInfo = $this->auth->getDeveloperInfo();
        
        $change = [
            'title' => trim($changeData['title']),
            'description' => trim($changeData['description']),
            'change_type' => $changeData['change_type'],
            'justification' => trim($changeData['justification']),
            'status' => 'draft',
            'requester_id' => $developerInfo['id'],
            'priority' => $changeData['priority'] ?? 'medium',
            'impact_assessment' => $changeData['impact_assessment'] ?? '',
            'rollback_plan' => $changeData['rollback_plan'] ?? '',
            'testing_plan' => $changeData['testing_plan'] ?? '',
            'affected_systems' => json_encode($changeData['affected_systems'] ?? []),
            'scheduled_date' => $changeData['scheduled_date'] ?? null,
            'estimated_duration' => $changeData['estimated_duration'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $stmt = $this->conn->prepare(
            'INSERT INTO change_requests (title, description, change_type, justification, status, 
                                        requester_id, priority, impact_assessment, rollback_plan, 
                                        testing_plan, affected_systems, scheduled_date, 
                                        estimated_duration, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        if (!$stmt) {
            throw new Exception('Error preparando consulta de cambio');
        }

        $stmt->bind_param(
            'sssssissssssss',
            $change['title'],
            $change['description'],
            $change['change_type'],
            $change['justification'],
            $change['status'],
            $change['requester_id'],
            $change['priority'],
            $change['impact_assessment'],
            $change['rollback_plan'],
            $change['testing_plan'],
            $change['affected_systems'],
            $change['scheduled_date'],
            $change['estimated_duration'],
            $change['created_at']
        );

        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception('Error creando solicitud de cambio');
        }

        $changeId = $this->conn->insert_id;
        $stmt->close();

        // Crear historial inicial
        $this->addChangeHistory($changeId, 'created', [
            'change_type' => $change['change_type'],
            'requester' => $developerInfo['nombre_completo']
        ]);

        $this->auth->logDeveloperActivity('change_request_created', [
            'change_id' => $changeId,
            'change_type' => $change['change_type'],
            'title' => $change['title']
        ]);

        return array_merge($change, ['id' => $changeId]);
    }

    /**
     * Aprueba o rechaza una solicitud de cambio
     */
    public function reviewChangeRequest(int $changeId, string $action, string $comments = ''): bool
    {
        if (!$this->auth->hasPrivateAccess('changes')) {
            throw new Exception('Acceso denegado para revisar cambios');
        }

        if (!in_array($action, ['approve', 'reject'])) {
            throw new Exception('Acción de revisión inválida');
        }

        $change = $this->getChangeRequest($changeId);
        if (!$change) {
            throw new Exception('Solicitud de cambio no encontrada');
        }

        if ($change['status'] !== 'pending_approval') {
            throw new Exception('La solicitud de cambio no está pendiente de aprobación');
        }

        $developerInfo = $this->auth->getDeveloperInfo();
        $newStatus = $action === 'approve' ? 'approved' : 'rejected';

        $stmt = $this->conn->prepare(
            'UPDATE change_requests 
             SET status = ?, reviewer_id = ?, review_comments = ?, reviewed_at = ?
             WHERE id = ?'
        );

        if (!$stmt) {
            throw new Exception('Error preparando revisión de cambio');
        }

        $reviewedAt = date('Y-m-d H:i:s');
        $stmt->bind_param('sissi', $newStatus, $developerInfo['id'], $comments, $reviewedAt, $changeId);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            $this->addChangeHistory($changeId, $action, [
                'reviewer' => $developerInfo['nombre_completo'],
                'comments' => $comments
            ]);

            $this->auth->logDeveloperActivity('change_request_reviewed', [
                'change_id' => $changeId,
                'action' => $action,
                'reviewer' => $developerInfo['nombre_completo']
            ]);

            // Notificar al solicitante
            $this->notifyChangeRequestUpdate($changeId, $action);
        }

        return $result;
    }

    /**
     * Obtiene lista de incidencias con filtros
     */
    public function getIncidents(array $filters = []): array
    {
        if (!$this->auth->hasPrivateAccess('incidents')) {
            throw new Exception('Acceso denegado para ver incidencias');
        }

        $where = ['1=1'];
        $params = [];
        $types = '';

        if (!empty($filters['severity'])) {
            $where[] = 'severity = ?';
            $params[] = $filters['severity'];
            $types .= 's';
        }

        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
            $types .= 's';
        }

        if (!empty($filters['assigned_to'])) {
            $where[] = 'assigned_to = ?';
            $params[] = $filters['assigned_to'];
            $types .= 'i';
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= ?';
            $params[] = $filters['date_from'];
            $types .= 's';
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
            $types .= 's';
        }

        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 50;
        $offset = isset($filters['offset']) ? (int)$filters['offset'] : 0;

        $sql = 'SELECT i.*, u1.nombre as reporter_name, u2.nombre as assigned_name
                FROM incidents i
                LEFT JOIN usuarios u1 ON u1.id = i.reporter_id
                LEFT JOIN usuarios u2 ON u2.id = i.assigned_to
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY i.created_at DESC
                LIMIT ? OFFSET ?';

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Error preparando consulta de incidencias');
        }

        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $incidents = [];
        while ($row = $result->fetch_assoc()) {
            $row['affected_systems'] = json_decode($row['affected_systems'] ?? '[]', true);
            $incidents[] = $row;
        }
        $stmt->close();

        return $incidents;
    }

    /**
     * Obtiene una incidencia específica con su historial
     */
    public function getIncident(int $incidentId): ?array
    {
        if (!$this->auth->hasPrivateAccess('incidents')) {
            throw new Exception('Acceso denegado para ver incidencia');
        }

        $stmt = $this->conn->prepare(
            'SELECT i.*, u1.nombre as reporter_name, u2.nombre as assigned_name
             FROM incidents i
             LEFT JOIN usuarios u1 ON u1.id = i.reporter_id
             LEFT JOIN usuarios u2 ON u2.id = i.assigned_to
             WHERE i.id = ?'
        );

        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $incidentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $incident = $result->fetch_assoc();
        $stmt->close();

        if (!$incident) {
            return null;
        }

        $incident['affected_systems'] = json_decode($incident['affected_systems'] ?? '[]', true);
        $incident['history'] = $this->getIncidentHistory($incidentId);

        return $incident;
    }

    /**
     * Métodos auxiliares privados
     */
    private function calculatePriority(string $severity): int
    {
        $priorityMap = [
            'low' => 1,
            'medium' => 2,
            'high' => 3,
            'critical' => 4
        ];
        return $priorityMap[$severity] ?? 2;
    }

    private function addIncidentHistory(int $incidentId, string $action, array $details = []): void
    {
        $developerInfo = $this->auth->getDeveloperInfo();
        
        $stmt = $this->conn->prepare(
            'INSERT INTO incident_history (incident_id, user_id, action, details, created_at) 
             VALUES (?, ?, ?, ?, ?)'
        );

        if ($stmt) {
            $detailsJson = json_encode($details);
            $createdAt = date('Y-m-d H:i:s');
            $stmt->bind_param('iisss', $incidentId, $developerInfo['id'], $action, $detailsJson, $createdAt);
            $stmt->execute();
            $stmt->close();
        }
    }

    private function addChangeHistory(int $changeId, string $action, array $details = []): void
    {
        $developerInfo = $this->auth->getDeveloperInfo();
        
        $stmt = $this->conn->prepare(
            'INSERT INTO change_history (change_id, user_id, action, details, created_at) 
             VALUES (?, ?, ?, ?, ?)'
        );

        if ($stmt) {
            $detailsJson = json_encode($details);
            $createdAt = date('Y-m-d H:i:s');
            $stmt->bind_param('iisss', $changeId, $developerInfo['id'], $action, $detailsJson, $createdAt);
            $stmt->execute();
            $stmt->close();
        }
    }

    private function getIncidentHistory(int $incidentId): array
    {
        $stmt = $this->conn->prepare(
            'SELECT h.*, u.nombre as user_name
             FROM incident_history h
             LEFT JOIN usuarios u ON u.id = h.user_id
             WHERE h.incident_id = ?
             ORDER BY h.created_at ASC'
        );

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('i', $incidentId);
        $stmt->execute();
        $result = $stmt->get_result();

        $history = [];
        while ($row = $result->fetch_assoc()) {
            $row['details'] = json_decode($row['details'] ?? '{}', true);
            $history[] = $row;
        }
        $stmt->close();

        return $history;
    }

    private function getChangeRequest(int $changeId): ?array
    {
        $stmt = $this->conn->prepare(
            'SELECT * FROM change_requests WHERE id = ?'
        );

        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $changeId);
        $stmt->execute();
        $result = $stmt->get_result();
        $change = $result->fetch_assoc();
        $stmt->close();

        if ($change) {
            $change['affected_systems'] = json_decode($change['affected_systems'] ?? '[]', true);
        }

        return $change;
    }

    private function sendCriticalIncidentAlert(int $incidentId): void
    {
        // Implementar envío de alertas críticas
        // Podría integrar con sistemas de notificación como Slack, email, SMS, etc.
    }

    private function notifyChangeRequestUpdate(int $changeId, string $action): void
    {
        // Implementar notificación de actualización de cambios
    }
}