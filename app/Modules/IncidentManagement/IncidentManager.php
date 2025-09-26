<?php
/**
 * Sistema de Gestión de Incidentes y Cambios
 * 
 * Este módulo integra con herramientas como JIRA y ServiceNow para
 * gestionar incidentes y validar cambios en el sistema.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../Core/db.php';

/**
 * Clase para gestionar incidentes y cambios del sistema
 */
class IncidentManager
{
    private mysqli $conn;
    private array $config;
    
    public function __construct(?mysqli $connection = null)
    {
        global $conn;
        $this->conn = $connection ?? $conn ?? DatabaseManager::getConnection();
        $this->config = $this->loadConfiguration();
    }

    /**
     * Crea un nuevo incidente
     */
    public function createIncident(array $incidentData): array
    {
        $incidentId = $this->createLocalIncident($incidentData);
        
        // Intentar crear en sistema externo (JIRA/ServiceNow)
        $externalTicket = null;
        if ($this->config['jira']['enabled']) {
            $externalTicket = $this->createJiraTicket($incidentData);
        } elseif ($this->config['servicenow']['enabled']) {
            $externalTicket = $this->createServiceNowTicket($incidentData);
        }

        if ($externalTicket) {
            $this->updateIncidentExternalRef($incidentId, $externalTicket['key'], $externalTicket['url']);
        }

        return [
            'incident_id' => $incidentId,
            'external_ticket' => $externalTicket,
            'status' => 'created'
        ];
    }

    /**
     * Crea incidente en la base de datos local
     */
    private function createLocalIncident(array $data): int
    {
        $stmt = $this->conn->prepare("
            INSERT INTO incidents (
                title, description, severity, priority, category,
                reported_by, assigned_to, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'open', NOW())
        ");

        $stmt->bind_param(
            "sssssii",
            $data['title'],
            $data['description'],
            $data['severity'],
            $data['priority'],
            $data['category'],
            $data['reported_by'],
            $data['assigned_to']
        );

        $stmt->execute();
        return $this->conn->insert_id;
    }

    /**
     * Crea ticket en JIRA
     */
    private function createJiraTicket(array $incidentData): ?array
    {
        try {
            $jiraConfig = $this->config['jira'];
            
            $payload = [
                'fields' => [
                    'project' => ['key' => $jiraConfig['project_key']],
                    'summary' => $incidentData['title'],
                    'description' => $incidentData['description'],
                    'issuetype' => ['name' => $this->mapSeverityToIssueType($incidentData['severity'])],
                    'priority' => ['name' => $this->mapPriorityToJira($incidentData['priority'])],
                    'customfield_' . $jiraConfig['severity_field'] => $incidentData['severity']
                ]
            ];

            $response = $this->makeHttpRequest(
                $jiraConfig['url'] . '/rest/api/2/issue',
                'POST',
                $payload,
                $this->getJiraHeaders()
            );

            if ($response && isset($response['key'])) {
                return [
                    'key' => $response['key'],
                    'url' => $jiraConfig['url'] . '/browse/' . $response['key']
                ];
            }

        } catch (Exception $e) {
            error_log("JIRA ticket creation failed: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Crea ticket en ServiceNow
     */
    private function createServiceNowTicket(array $incidentData): ?array
    {
        try {
            $snowConfig = $this->config['servicenow'];
            
            $payload = [
                'short_description' => $incidentData['title'],
                'description' => $incidentData['description'],
                'urgency' => $this->mapPriorityToServiceNow($incidentData['priority']),
                'impact' => $this->mapSeverityToServiceNow($incidentData['severity']),
                'category' => $incidentData['category'],
                'caller_id' => $incidentData['reported_by']
            ];

            $response = $this->makeHttpRequest(
                $snowConfig['url'] . '/api/now/table/incident',
                'POST',
                $payload,
                $this->getServiceNowHeaders()
            );

            if ($response && isset($response['result']['number'])) {
                return [
                    'key' => $response['result']['number'],
                    'url' => $snowConfig['url'] . '/nav_to.do?uri=incident.do?sys_id=' . $response['result']['sys_id']
                ];
            }

        } catch (Exception $e) {
            error_log("ServiceNow ticket creation failed: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Actualiza estado de incidente
     */
    public function updateIncidentStatus(int $incidentId, string $status, string $notes = ''): bool
    {
        $stmt = $this->conn->prepare("
            UPDATE incidents 
            SET status = ?, updated_at = NOW() 
            WHERE id = ?
        ");

        $stmt->bind_param("si", $status, $incidentId);
        $success = $stmt->execute();

        if ($success && !empty($notes)) {
            $this->addIncidentNote($incidentId, $notes);
        }

        return $success;
    }

    /**
     * Añade nota a un incidente
     */
    public function addIncidentNote(int $incidentId, string $note, ?int $userId = null): void
    {
        if ($userId === null) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $userId = $_SESSION['usuario_id'] ?? null;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO incident_notes (incident_id, user_id, note, created_at) 
            VALUES (?, ?, ?, NOW())
        ");

        $stmt->bind_param("iis", $incidentId, $userId, $note);
        $stmt->execute();
    }

    /**
     * Obtiene incidentes con filtros
     */
    public function getIncidents(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $whereConditions = [];
        $params = [];
        $types = '';

        if (!empty($filters['status'])) {
            $whereConditions[] = "i.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }

        if (!empty($filters['severity'])) {
            $whereConditions[] = "i.severity = ?";
            $params[] = $filters['severity'];
            $types .= 's';
        }

        if (!empty($filters['assigned_to'])) {
            $whereConditions[] = "i.assigned_to = ?";
            $params[] = $filters['assigned_to'];
            $types .= 'i';
        }

        if (!empty($filters['date_from'])) {
            $whereConditions[] = "i.created_at >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $sql = "
            SELECT 
                i.*, 
                u1.nombre as reported_by_name,
                u2.nombre as assigned_to_name
            FROM incidents i
            LEFT JOIN usuarios u1 ON i.reported_by = u1.id
            LEFT JOIN usuarios u2 ON i.assigned_to = u2.id
            {$whereClause}
            ORDER BY i.created_at DESC
            LIMIT ? OFFSET ?
        ";

        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';

        $stmt = $this->conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Monitorea KPIs críticos del sistema
     */
    public function monitorSystemKPIs(): array
    {
        $kpis = [];

        // KPI: Tiempo de respuesta de la base de datos
        $kpis['database_response_time'] = $this->measureDatabaseResponseTime();

        // KPI: Espacio en disco
        $kpis['disk_usage'] = $this->checkDiskUsage();

        // KPI: Incidentes abiertos críticos
        $kpis['critical_incidents'] = $this->getCriticalIncidentsCount();

        // KPI: Uptime del sistema
        $kpis['system_uptime'] = $this->getSystemUptime();

        // Verificar umbrales y crear alertas si es necesario
        $this->checkKPIThresholds($kpis);

        return $kpis;
    }

    /**
     * Verifica umbrales de KPIs y genera alertas
     */
    private function checkKPIThresholds(array $kpis): void
    {
        $thresholds = $this->config['kpi_thresholds'];

        foreach ($kpis as $kpi => $value) {
            if (isset($thresholds[$kpi]) && $value > $thresholds[$kpi]['critical']) {
                $this->createCriticalAlert($kpi, $value, $thresholds[$kpi]['critical']);
            } elseif (isset($thresholds[$kpi]) && $value > $thresholds[$kpi]['warning']) {
                $this->createWarningAlert($kpi, $value, $thresholds[$kpi]['warning']);
            }
        }
    }

    /**
     * Crea alerta crítica
     */
    private function createCriticalAlert(string $kpi, $value, $threshold): void
    {
        $incidentData = [
            'title' => "KPI Crítico: {$kpi} excede umbral",
            'description' => "El KPI {$kpi} tiene un valor de {$value}, excediendo el umbral crítico de {$threshold}",
            'severity' => 'critical',
            'priority' => 'high',
            'category' => 'performance',
            'reported_by' => 1, // Sistema automático
            'assigned_to' => $this->getDefaultAssignee()
        ];

        $this->createIncident($incidentData);
    }

    /**
     * Realizar petición HTTP
     */
    private function makeHttpRequest(string $url, string $method, array $data, array $headers): ?array
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return json_decode($response, true);
        }

        return null;
    }

    /**
     * Obtiene headers para JIRA
     */
    private function getJiraHeaders(): array
    {
        $auth = base64_encode($this->config['jira']['username'] . ':' . $this->config['jira']['password']);
        
        return [
            'Authorization: Basic ' . $auth,
            'Content-Type: application/json'
        ];
    }

    /**
     * Obtiene headers para ServiceNow
     */
    private function getServiceNowHeaders(): array
    {
        $auth = base64_encode($this->config['servicenow']['username'] . ':' . $this->config['servicenow']['password']);
        
        return [
            'Authorization: Basic ' . $auth,
            'Content-Type: application/json'
        ];
    }

    /**
     * Carga configuración del sistema
     */
    private function loadConfiguration(): array
    {
        return [
            'jira' => [
                'enabled' => $_ENV['JIRA_ENABLED'] ?? false,
                'url' => $_ENV['JIRA_URL'] ?? '',
                'username' => $_ENV['JIRA_USERNAME'] ?? '',
                'password' => $_ENV['JIRA_PASSWORD'] ?? '',
                'project_key' => $_ENV['JIRA_PROJECT_KEY'] ?? 'INCIDENT',
                'severity_field' => $_ENV['JIRA_SEVERITY_FIELD'] ?? '10001'
            ],
            'servicenow' => [
                'enabled' => $_ENV['SERVICENOW_ENABLED'] ?? false,
                'url' => $_ENV['SERVICENOW_URL'] ?? '',
                'username' => $_ENV['SERVICENOW_USERNAME'] ?? '',
                'password' => $_ENV['SERVICENOW_PASSWORD'] ?? ''
            ],
            'kpi_thresholds' => [
                'database_response_time' => ['warning' => 1000, 'critical' => 3000],
                'disk_usage' => ['warning' => 80, 'critical' => 90],
                'critical_incidents' => ['warning' => 5, 'critical' => 10]
            ]
        ];
    }

    /**
     * Mapea severidad a tipo de issue en JIRA
     */
    private function mapSeverityToIssueType(string $severity): string
    {
        $mapping = [
            'critical' => 'Bug',
            'high' => 'Bug',
            'medium' => 'Task',
            'low' => 'Task'
        ];

        return $mapping[$severity] ?? 'Task';
    }

    /**
     * Mapea prioridad a JIRA
     */
    private function mapPriorityToJira(string $priority): string
    {
        $mapping = [
            'critical' => 'Highest',
            'high' => 'High',
            'medium' => 'Medium',
            'low' => 'Low'
        ];

        return $mapping[$priority] ?? 'Medium';
    }

    /**
     * Actualiza referencia externa del incidente
     */
    private function updateIncidentExternalRef(int $incidentId, string $externalKey, string $externalUrl): void
    {
        $stmt = $this->conn->prepare("
            UPDATE incidents 
            SET external_ticket_key = ?, external_ticket_url = ? 
            WHERE id = ?
        ");

        $stmt->bind_param("ssi", $externalKey, $externalUrl, $incidentId);
        $stmt->execute();
    }

    // Métodos de medición de KPIs (implementaciones simplificadas)
    private function measureDatabaseResponseTime(): float
    {
        $start = microtime(true);
        $this->conn->query("SELECT 1");
        return (microtime(true) - $start) * 1000; // en milisegundos
    }

    private function checkDiskUsage(): float
    {
        $bytes = disk_free_space(".");
        $total = disk_total_space(".");
        return (($total - $bytes) / $total) * 100;
    }

    private function getCriticalIncidentsCount(): int
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM incidents WHERE status = 'open' AND severity = 'critical'");
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return (int)$result['count'];
    }

    private function getSystemUptime(): float
    {
        // Implementación simplificada - se puede mejorar
        return 99.9;
    }

    private function getDefaultAssignee(): int
    {
        // Retorna ID del administrador por defecto
        return 1;
    }

    private function createWarningAlert(string $kpi, $value, $threshold): void
    {
        // Log de advertencia
        error_log("WARNING: KPI {$kpi} = {$value} exceeds warning threshold {$threshold}");
    }

    private function mapPriorityToServiceNow(string $priority): string
    {
        $mapping = [
            'critical' => '1',
            'high' => '2', 
            'medium' => '3',
            'low' => '4'
        ];

        return $mapping[$priority] ?? '3';
    }

    private function mapSeverityToServiceNow(string $severity): string
    {
        $mapping = [
            'critical' => '1',
            'high' => '2',
            'medium' => '3', 
            'low' => '3'
        ];

        return $mapping[$severity] ?? '3';
    }
}