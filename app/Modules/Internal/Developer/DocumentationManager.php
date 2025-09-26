<?php
declare(strict_types=1);

require_once __DIR__ . '/DeveloperAuth.php';

/**
 * Sistema de Gestión de Documentación Operativa
 * Maneja SOP (Standard Operating Procedures), AppCare y Handover
 */
class DocumentationManager
{
    private mysqli $conn;
    private DeveloperAuth $auth;
    
    const DOCUMENT_TYPES = ['sop', 'appcare', 'handover', 'manual', 'policy'];
    const DOCUMENT_STATUSES = ['draft', 'review', 'approved', 'active', 'archived', 'expired'];
    
    public function __construct(?mysqli $connection = null)
    {
        global $conn;
        $this->conn = $connection ?? $conn ?? DatabaseManager::getConnection();
        $this->auth = new DeveloperAuth($this->conn);
    }

    /**
     * Crea un nuevo documento
     */
    public function createDocument(array $documentData): array
    {
        if (!$this->auth->hasPrivateAccess('sop')) {
            throw new Exception('Acceso denegado para crear documentos');
        }

        $requiredFields = ['title', 'document_type', 'content'];
        foreach ($requiredFields as $field) {
            if (empty($documentData[$field])) {
                throw new Exception("Campo requerido faltante: {$field}");
            }
        }

        if (!in_array($documentData['document_type'], self::DOCUMENT_TYPES)) {
            throw new Exception('Tipo de documento inválido');
        }

        $developerInfo = $this->auth->getDeveloperInfo();
        
        $document = [
            'title' => trim($documentData['title']),
            'document_type' => $documentData['document_type'],
            'content' => $documentData['content'],
            'version' => $this->getNextVersion($documentData['title'], $documentData['document_type']),
            'status' => 'draft',
            'author_id' => $developerInfo['id'],
            'department' => $documentData['department'] ?? 'IT',
            'tags' => json_encode($documentData['tags'] ?? []),
            'metadata' => json_encode($documentData['metadata'] ?? []),
            'review_cycle_days' => $documentData['review_cycle_days'] ?? 365,
            'effective_date' => $documentData['effective_date'] ?? null,
            'expiry_date' => $documentData['expiry_date'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $stmt = $this->conn->prepare(
            'INSERT INTO documents (title, document_type, content, version, status, author_id, 
                                  department, tags, metadata, review_cycle_days, effective_date, 
                                  expiry_date, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        if (!$stmt) {
            throw new Exception('Error preparando consulta de documento');
        }

        $stmt->bind_param(
            'sssssississss',
            $document['title'],
            $document['document_type'],
            $document['content'],
            $document['version'],
            $document['status'],
            $document['author_id'],
            $document['department'],
            $document['tags'],
            $document['metadata'],
            $document['review_cycle_days'],
            $document['effective_date'],
            $document['expiry_date'],
            $document['created_at']
        );

        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception('Error creando documento');
        }

        $documentId = $this->conn->insert_id;
        $stmt->close();

        // Crear historial inicial
        $this->addDocumentHistory($documentId, 'created', [
            'version' => $document['version'],
            'author' => $developerInfo['nombre_completo']
        ]);

        $this->auth->logDeveloperActivity('document_created', [
            'document_id' => $documentId,
            'document_type' => $document['document_type'],
            'title' => $document['title'],
            'version' => $document['version']
        ]);

        return array_merge($document, ['id' => $documentId]);
    }

    /**
     * Actualiza un documento existente
     */
    public function updateDocument(int $documentId, array $updateData): bool
    {
        if (!$this->auth->hasPrivateAccess('sop')) {
            throw new Exception('Acceso denegado para actualizar documentos');
        }

        $currentDocument = $this->getDocument($documentId);
        if (!$currentDocument) {
            throw new Exception('Documento no encontrado');
        }

        $developerInfo = $this->auth->getDeveloperInfo();
        $allowedFields = ['content', 'status', 'effective_date', 'expiry_date', 'tags', 'metadata'];
        $updateFields = [];
        $updateValues = [];
        $types = '';

        foreach ($allowedFields as $field) {
            if (isset($updateData[$field])) {
                if ($field === 'status' && !in_array($updateData[$field], self::DOCUMENT_STATUSES)) {
                    throw new Exception('Estado de documento inválido');
                }

                $value = $updateData[$field];
                if (in_array($field, ['tags', 'metadata']) && is_array($value)) {
                    $value = json_encode($value);
                }

                $updateFields[] = "{$field} = ?";
                $updateValues[] = $value;
                $types .= 's';
            }
        }

        // Si hay cambios significativos en el contenido, crear nueva versión
        if (isset($updateData['content']) && $updateData['content'] !== $currentDocument['content']) {
            $updateFields[] = 'version = ?';
            $newVersion = $this->incrementVersion($currentDocument['version']);
            $updateValues[] = $newVersion;
            $types .= 's';

            $updateFields[] = 'last_modified_by = ?';
            $updateValues[] = $developerInfo['id'];
            $types .= 'i';
        }

        if (empty($updateFields)) {
            return true; // No hay cambios
        }

        $updateFields[] = 'updated_at = ?';
        $updateValues[] = date('Y-m-d H:i:s');
        $types .= 's';

        $updateValues[] = $documentId;
        $types .= 'i';

        $sql = 'UPDATE documents SET ' . implode(', ', $updateFields) . ' WHERE id = ?';
        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            throw new Exception('Error preparando actualización de documento');
        }

        $stmt->bind_param($types, ...$updateValues);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            $changes = [];
            foreach ($allowedFields as $field) {
                if (isset($updateData[$field]) && $updateData[$field] !== $currentDocument[$field]) {
                    $changes[$field] = [
                        'from' => $currentDocument[$field],
                        'to' => $updateData[$field]
                    ];
                }
            }

            if (!empty($changes)) {
                $this->addDocumentHistory($documentId, 'updated', [
                    'changes' => $changes,
                    'modifier' => $developerInfo['nombre_completo'],
                    'version' => $newVersion ?? $currentDocument['version']
                ]);

                $this->auth->logDeveloperActivity('document_updated', [
                    'document_id' => $documentId,
                    'changes' => array_keys($changes),
                    'version' => $newVersion ?? $currentDocument['version']
                ]);
            }
        }

        return $result;
    }

    /**
     * Maneja el flujo de revisión de documentos
     */
    public function reviewDocument(int $documentId, string $action, string $comments = ''): bool
    {
        if (!$this->auth->hasPrivateAccess('sop')) {
            throw new Exception('Acceso denegado para revisar documentos');
        }

        if (!in_array($action, ['approve', 'reject', 'request_changes'])) {
            throw new Exception('Acción de revisión inválida');
        }

        $document = $this->getDocument($documentId);
        if (!$document) {
            throw new Exception('Documento no encontrado');
        }

        if ($document['status'] !== 'review') {
            throw new Exception('El documento no está en estado de revisión');
        }

        $developerInfo = $this->auth->getDeveloperInfo();
        $statusMap = [
            'approve' => 'approved',
            'reject' => 'draft',
            'request_changes' => 'draft'
        ];

        $newStatus = $statusMap[$action];

        $stmt = $this->conn->prepare(
            'UPDATE documents 
             SET status = ?, reviewer_id = ?, review_comments = ?, reviewed_at = ?
             WHERE id = ?'
        );

        if (!$stmt) {
            throw new Exception('Error preparando revisión de documento');
        }

        $reviewedAt = date('Y-m-d H:i:s');
        $stmt->bind_param('sissi', $newStatus, $developerInfo['id'], $comments, $reviewedAt, $documentId);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            $this->addDocumentHistory($documentId, "review_{$action}", [
                'reviewer' => $developerInfo['nombre_completo'],
                'comments' => $comments,
                'previous_status' => $document['status'],
                'new_status' => $newStatus
            ]);

            $this->auth->logDeveloperActivity('document_reviewed', [
                'document_id' => $documentId,
                'action' => $action,
                'reviewer' => $developerInfo['nombre_completo']
            ]);

            // Si se aprueba, activar automáticamente si tiene fecha efectiva
            if ($action === 'approve' && $document['effective_date'] && $document['effective_date'] <= date('Y-m-d')) {
                $this->activateDocument($documentId);
            }
        }

        return $result;
    }

    /**
     * Activa un documento aprobado
     */
    public function activateDocument(int $documentId): bool
    {
        if (!$this->auth->hasPrivateAccess('sop')) {
            throw new Exception('Acceso denegado para activar documentos');
        }

        $document = $this->getDocument($documentId);
        if (!$document) {
            throw new Exception('Documento no encontrado');
        }

        if ($document['status'] !== 'approved') {
            throw new Exception('Solo se pueden activar documentos aprobados');
        }

        // Archivar versiones anteriores del mismo documento
        $this->archivePreviousVersions($document['title'], $document['document_type'], $documentId);

        $stmt = $this->conn->prepare(
            'UPDATE documents SET status = ?, activated_at = ? WHERE id = ?'
        );

        if (!$stmt) {
            throw new Exception('Error preparando activación de documento');
        }

        $activatedAt = date('Y-m-d H:i:s');
        $status = 'active';
        $stmt->bind_param('ssi', $status, $activatedAt, $documentId);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            $this->addDocumentHistory($documentId, 'activated', [
                'activated_by' => $this->auth->getDeveloperInfo()['nombre_completo']
            ]);

            $this->auth->logDeveloperActivity('document_activated', [
                'document_id' => $documentId,
                'title' => $document['title'],
                'version' => $document['version']
            ]);

            // Programar revisión automática
            $this->scheduleDocumentReview($documentId);
        }

        return $result;
    }

    /**
     * Obtiene documentos con filtros
     */
    public function getDocuments(array $filters = []): array
    {
        if (!$this->auth->hasPrivateAccess('sop')) {
            throw new Exception('Acceso denegado para ver documentos');
        }

        $where = ['1=1'];
        $params = [];
        $types = '';

        if (!empty($filters['document_type'])) {
            $where[] = 'document_type = ?';
            $params[] = $filters['document_type'];
            $types .= 's';
        }

        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
            $types .= 's';
        }

        if (!empty($filters['department'])) {
            $where[] = 'department = ?';
            $params[] = $filters['department'];
            $types .= 's';
        }

        if (!empty($filters['search'])) {
            $where[] = '(title LIKE ? OR content LIKE ?)';
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ss';
        }

        if (!empty($filters['expiring_soon'])) {
            $where[] = 'expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(NOW(), INTERVAL 30 DAY)';
        }

        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 50;
        $offset = isset($filters['offset']) ? (int)$filters['offset'] : 0;

        $sql = 'SELECT d.*, u1.nombre as author_name, u2.nombre as reviewer_name
                FROM documents d
                LEFT JOIN usuarios u1 ON u1.id = d.author_id
                LEFT JOIN usuarios u2 ON u2.id = d.reviewer_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY d.created_at DESC
                LIMIT ? OFFSET ?';

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Error preparando consulta de documentos');
        }

        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $documents = [];
        while ($row = $result->fetch_assoc()) {
            $row['tags'] = json_decode($row['tags'] ?? '[]', true);
            $row['metadata'] = json_decode($row['metadata'] ?? '{}', true);
            $documents[] = $row;
        }
        $stmt->close();

        return $documents;
    }

    /**
     * Obtiene un documento específico con su historial
     */
    public function getDocument(int $documentId): ?array
    {
        if (!$this->auth->hasPrivateAccess('sop')) {
            throw new Exception('Acceso denegado para ver documento');
        }

        $stmt = $this->conn->prepare(
            'SELECT d.*, u1.nombre as author_name, u2.nombre as reviewer_name
             FROM documents d
             LEFT JOIN usuarios u1 ON u1.id = d.author_id
             LEFT JOIN usuarios u2 ON u2.id = d.reviewer_id
             WHERE d.id = ?'
        );

        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $documentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $document = $result->fetch_assoc();
        $stmt->close();

        if (!$document) {
            return null;
        }

        $document['tags'] = json_decode($document['tags'] ?? '[]', true);
        $document['metadata'] = json_decode($document['metadata'] ?? '{}', true);
        $document['history'] = $this->getDocumentHistory($documentId);
        $document['versions'] = $this->getDocumentVersions($document['title'], $document['document_type']);

        return $document;
    }

    /**
     * Obtiene documentos próximos a vencer
     */
    public function getExpiringDocuments(int $days = 30): array
    {
        if (!$this->auth->hasPrivateAccess('sop')) {
            throw new Exception('Acceso denegado para ver documentos');
        }

        $stmt = $this->conn->prepare(
            'SELECT d.*, u.nombre as author_name
             FROM documents d
             LEFT JOIN usuarios u ON u.id = d.author_id
             WHERE d.status = "active" 
             AND d.expiry_date IS NOT NULL 
             AND d.expiry_date <= DATE_ADD(NOW(), INTERVAL ? DAY)
             ORDER BY d.expiry_date ASC'
        );

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('i', $days);
        $stmt->execute();
        $result = $stmt->get_result();

        $documents = [];
        while ($row = $result->fetch_assoc()) {
            $row['tags'] = json_decode($row['tags'] ?? '[]', true);
            $row['days_until_expiry'] = ceil((strtotime($row['expiry_date']) - time()) / (60 * 60 * 24));
            $documents[] = $row;
        }
        $stmt->close();

        return $documents;
    }

    /**
     * Genera métricas de documentación
     */
    public function getDocumentationMetrics(): array
    {
        if (!$this->auth->hasPrivateAccess('sop')) {
            throw new Exception('Acceso denegado para ver métricas');
        }

        // Documentos por tipo
        $stmt = $this->conn->prepare(
            'SELECT document_type, COUNT(*) as count 
             FROM documents 
             WHERE status != "archived" 
             GROUP BY document_type'
        );
        $stmt->execute();
        $result = $stmt->get_result();
        $byType = [];
        while ($row = $result->fetch_assoc()) {
            $byType[$row['document_type']] = (int)$row['count'];
        }
        $stmt->close();

        // Documentos por estado
        $stmt = $this->conn->prepare(
            'SELECT status, COUNT(*) as count 
             FROM documents 
             GROUP BY status'
        );
        $stmt->execute();
        $result = $stmt->get_result();
        $byStatus = [];
        while ($row = $result->fetch_assoc()) {
            $byStatus[$row['status']] = (int)$row['count'];
        }
        $stmt->close();

        // Documentos próximos a vencer
        $expiringCount = count($this->getExpiringDocuments());

        // Actividad reciente
        $stmt = $this->conn->prepare(
            'SELECT COUNT(*) as recent_updates 
             FROM documents 
             WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'
        );
        $stmt->execute();
        $recentUpdates = (int)$stmt->get_result()->fetch_assoc()['recent_updates'];
        $stmt->close();

        return [
            'by_type' => $byType,
            'by_status' => $byStatus,
            'expiring_soon' => $expiringCount,
            'recent_updates' => $recentUpdates,
            'total_active' => $byStatus['active'] ?? 0,
            'compliance_score' => $this->calculateDocumentationComplianceScore()
        ];
    }

    /**
     * Métodos auxiliares privados
     */
    private function getNextVersion(string $title, string $documentType): string
    {
        $stmt = $this->conn->prepare(
            'SELECT version FROM documents 
             WHERE title = ? AND document_type = ? 
             ORDER BY created_at DESC LIMIT 1'
        );

        if (!$stmt) {
            return '1.0';
        }

        $stmt->bind_param('ss', $title, $documentType);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return '1.0';
        }

        return $this->incrementVersion($row['version']);
    }

    private function incrementVersion(string $version): string
    {
        $parts = explode('.', $version);
        if (count($parts) >= 2) {
            $major = (int)$parts[0];
            $minor = (int)$parts[1];
            $minor++;
            return "{$major}.{$minor}";
        }
        return '1.0';
    }

    private function addDocumentHistory(int $documentId, string $action, array $details = []): void
    {
        $developerInfo = $this->auth->getDeveloperInfo();
        
        $stmt = $this->conn->prepare(
            'INSERT INTO document_history (document_id, user_id, action, details, created_at) 
             VALUES (?, ?, ?, ?, ?)'
        );

        if ($stmt) {
            $detailsJson = json_encode($details);
            $createdAt = date('Y-m-d H:i:s');
            $stmt->bind_param('iisss', $documentId, $developerInfo['id'], $action, $detailsJson, $createdAt);
            $stmt->execute();
            $stmt->close();
        }
    }

    private function getDocumentHistory(int $documentId): array
    {
        $stmt = $this->conn->prepare(
            'SELECT h.*, u.nombre as user_name
             FROM document_history h
             LEFT JOIN usuarios u ON u.id = h.user_id
             WHERE h.document_id = ?
             ORDER BY h.created_at DESC'
        );

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('i', $documentId);
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

    private function getDocumentVersions(string $title, string $documentType): array
    {
        $stmt = $this->conn->prepare(
            'SELECT id, version, status, created_at, author_id
             FROM documents 
             WHERE title = ? AND document_type = ? 
             ORDER BY created_at DESC'
        );

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('ss', $title, $documentType);
        $stmt->execute();
        $result = $stmt->get_result();

        $versions = [];
        while ($row = $result->fetch_assoc()) {
            $versions[] = $row;
        }
        $stmt->close();

        return $versions;
    }

    private function archivePreviousVersions(string $title, string $documentType, int $currentDocumentId): void
    {
        $stmt = $this->conn->prepare(
            'UPDATE documents 
             SET status = "archived" 
             WHERE title = ? AND document_type = ? AND id != ? AND status = "active"'
        );

        if ($stmt) {
            $stmt->bind_param('ssi', $title, $documentType, $currentDocumentId);
            $stmt->execute();
            $stmt->close();
        }
    }

    private function scheduleDocumentReview(int $documentId): void
    {
        // Implementar programación de revisiones automáticas
        // Podría integrar con un sistema de tareas programadas
    }

    private function calculateDocumentationComplianceScore(): float
    {
        // Implementar cálculo de score de cumplimiento de documentación
        // Basado en documentos activos, actualizados, sin vencer
        return 92.5; // Placeholder
    }
}