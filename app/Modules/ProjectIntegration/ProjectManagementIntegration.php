<?php
/**
 * Integración con Herramientas de Gestión de Proyectos
 * 
 * Este módulo integra con Trello, Asana y otras herramientas de gestión de proyectos
 * para gestionar tareas relacionadas con administración de cambios y resolución de incidentes.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../Core/db.php';

/**
 * Clase para integrar con herramientas de gestión de proyectos
 */
class ProjectManagementIntegration
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
     * Crea tarea en Trello
     */
    public function createTrelloCard(array $cardData): ?array
    {
        if (!$this->config['trello']['enabled']) {
            return null;
        }

        try {
            $url = "https://api.trello.com/1/cards";
            
            $params = [
                'key' => $this->config['trello']['api_key'],
                'token' => $this->config['trello']['token'],
                'idList' => $cardData['list_id'] ?? $this->config['trello']['default_list'],
                'name' => $cardData['title'],
                'desc' => $cardData['description'],
                'due' => $cardData['due_date'] ?? null,
                'pos' => 'bottom'
            ];

            if (!empty($cardData['members'])) {
                $params['idMembers'] = implode(',', $cardData['members']);
            }

            if (!empty($cardData['labels'])) {
                $params['idLabels'] = implode(',', $cardData['labels']);
            }

            $response = $this->makeHttpRequest($url, 'POST', $params);
            
            if ($response && isset($response['id'])) {
                // Guardar referencia local
                $this->saveProjectTask([
                    'platform' => 'trello',
                    'external_id' => $response['id'],
                    'title' => $cardData['title'],
                    'url' => $response['shortUrl'],
                    'status' => 'open'
                ]);

                return $response;
            }

        } catch (Exception $e) {
            error_log("Error creando card en Trello: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Crea tarea en Asana
     */
    public function createAsanaTask(array $taskData): ?array
    {
        if (!$this->config['asana']['enabled']) {
            return null;
        }

        try {
            $url = "https://app.asana.com/api/1.0/tasks";
            
            $payload = [
                'data' => [
                    'name' => $taskData['title'],
                    'notes' => $taskData['description'],
                    'projects' => [$taskData['project_id'] ?? $this->config['asana']['default_project']],
                    'due_on' => $taskData['due_date'] ?? null
                ]
            ];

            if (!empty($taskData['assignee'])) {
                $payload['data']['assignee'] = $taskData['assignee'];
            }

            $response = $this->makeHttpRequest(
                $url, 
                'POST', 
                $payload, 
                $this->getAsanaHeaders()
            );
            
            if ($response && isset($response['data']['gid'])) {
                // Guardar referencia local
                $this->saveProjectTask([
                    'platform' => 'asana',
                    'external_id' => $response['data']['gid'],
                    'title' => $taskData['title'],
                    'url' => "https://app.asana.com/0/0/{$response['data']['gid']}",
                    'status' => 'open'
                ]);

                return $response['data'];
            }

        } catch (Exception $e) {
            error_log("Error creando tarea en Asana: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Crea tarea en Monday.com
     */
    public function createMondayItem(array $itemData): ?array
    {
        if (!$this->config['monday']['enabled']) {
            return null;
        }

        try {
            $url = "https://api.monday.com/v2";
            
            $query = '
                mutation ($boardId: Int!, $itemName: String!, $columnValues: JSON!) {
                    create_item (
                        board_id: $boardId,
                        item_name: $itemName,
                        column_values: $columnValues
                    ) {
                        id
                        name
                        url
                    }
                }
            ';

            $variables = [
                'boardId' => (int)($itemData['board_id'] ?? $this->config['monday']['default_board']),
                'itemName' => $itemData['title'],
                'columnValues' => json_encode([
                    'text' => $itemData['description'],
                    'status' => ['label' => 'Working on it'],
                    'date' => $itemData['due_date'] ?? null
                ])
            ];

            $payload = [
                'query' => $query,
                'variables' => $variables
            ];

            $response = $this->makeHttpRequest(
                $url,
                'POST',
                $payload,
                $this->getMondayHeaders()
            );

            if ($response && isset($response['data']['create_item'])) {
                $item = $response['data']['create_item'];
                
                $this->saveProjectTask([
                    'platform' => 'monday',
                    'external_id' => $item['id'],
                    'title' => $itemData['title'],
                    'url' => $item['url'],
                    'status' => 'open'
                ]);

                return $item;
            }

        } catch (Exception $e) {
            error_log("Error creando item en Monday.com: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Crea tarea desde incidente
     */
    public function createTaskFromIncident(int $incidentId, string $platform = null): array
    {
        $incident = $this->getIncident($incidentId);
        if (!$incident) {
            throw new Exception("Incidente no encontrado: {$incidentId}");
        }

        $taskData = [
            'title' => "Incidente #{$incidentId}: {$incident['title']}",
            'description' => $this->formatIncidentDescription($incident),
            'due_date' => $this->calculateDueDate($incident['severity']),
            'priority' => $incident['priority']
        ];

        $results = [];
        $platforms = $platform ? [$platform] : $this->getEnabledPlatforms();

        foreach ($platforms as $platformName) {
            switch ($platformName) {
                case 'trello':
                    $result = $this->createTrelloCard($taskData);
                    if ($result) {
                        $results['trello'] = $result;
                        $this->linkIncidentToTask($incidentId, 'trello', $result['id']);
                    }
                    break;

                case 'asana':
                    $result = $this->createAsanaTask($taskData);
                    if ($result) {
                        $results['asana'] = $result;
                        $this->linkIncidentToTask($incidentId, 'asana', $result['gid']);
                    }
                    break;

                case 'monday':
                    $result = $this->createMondayItem($taskData);
                    if ($result) {
                        $results['monday'] = $result;
                        $this->linkIncidentToTask($incidentId, 'monday', $result['id']);
                    }
                    break;
            }
        }

        return $results;
    }

    /**
     * Sincroniza estado de tareas
     */
    public function syncTaskStatuses(): array
    {
        $results = ['synced' => 0, 'errors' => []];
        
        $tasks = $this->getActiveProjectTasks();

        foreach ($tasks as $task) {
            try {
                $externalStatus = $this->getExternalTaskStatus($task['platform'], $task['external_id']);
                
                if ($externalStatus && $externalStatus !== $task['status']) {
                    $this->updateTaskStatus($task['id'], $externalStatus);
                    $results['synced']++;
                }

            } catch (Exception $e) {
                $results['errors'][] = "Error sincronizando tarea {$task['id']}: " . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Obtiene estado de tarea externa
     */
    private function getExternalTaskStatus(string $platform, string $externalId): ?string
    {
        switch ($platform) {
            case 'trello':
                return $this->getTrelloCardStatus($externalId);
            case 'asana':
                return $this->getAsanaTaskStatus($externalId);
            case 'monday':
                return $this->getMondayItemStatus($externalId);
            default:
                return null;
        }
    }

    /**
     * Obtiene estado de card en Trello
     */
    private function getTrelloCardStatus(string $cardId): ?string
    {
        try {
            $url = "https://api.trello.com/1/cards/{$cardId}";
            $params = [
                'key' => $this->config['trello']['api_key'],
                'token' => $this->config['trello']['token'],
                'fields' => 'closed,dueComplete'
            ];

            $response = $this->makeHttpRequest($url, 'GET', $params);
            
            if ($response) {
                if ($response['closed']) {
                    return 'completed';
                } elseif ($response['dueComplete']) {
                    return 'completed';
                } else {
                    return 'open';
                }
            }

        } catch (Exception $e) {
            error_log("Error obteniendo estado de Trello card: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Genera reporte de productividad
     */
    public function generateProductivityReport(string $dateFrom, string $dateTo): array
    {
        $stmt = $this->conn->prepare("
            SELECT 
                platform,
                COUNT(*) as total_tasks,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
                AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_completion_time
            FROM project_tasks 
            WHERE created_at BETWEEN ? AND ?
            GROUP BY platform
        ");

        $stmt->bind_param("ss", $dateFrom, $dateTo);
        $stmt->execute();
        
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Calcular métricas adicionales
        foreach ($results as &$result) {
            $result['completion_rate'] = $result['total_tasks'] > 0 ? 
                round(($result['completed_tasks'] / $result['total_tasks']) * 100, 2) : 0;
        }

        return $results;
    }

    /**
     * Configura webhooks para sincronización automática
     */
    public function setupWebhooks(): array
    {
        $results = [];

        // Webhook para Trello
        if ($this->config['trello']['enabled']) {
            $results['trello'] = $this->setupTrelloWebhook();
        }

        // Webhook para Asana
        if ($this->config['asana']['enabled']) {
            $results['asana'] = $this->setupAsanaWebhook();
        }

        return $results;
    }

    /**
     * Configura webhook de Trello
     */
    private function setupTrelloWebhook(): bool
    {
        try {
            $url = "https://api.trello.com/1/webhooks";
            
            $params = [
                'key' => $this->config['trello']['api_key'],
                'token' => $this->config['trello']['token'],
                'callbackURL' => $this->config['webhook_base_url'] . '/trello',
                'idModel' => $this->config['trello']['board_id'],
                'description' => 'Sistema ISO 17025 - Sync Webhook'
            ];

            $response = $this->makeHttpRequest($url, 'POST', $params);
            return $response && isset($response['id']);

        } catch (Exception $e) {
            error_log("Error configurando webhook de Trello: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Procesa webhook de actualización
     */
    public function processWebhook(string $platform, array $data): bool
    {
        try {
            switch ($platform) {
                case 'trello':
                    return $this->processTrelloWebhook($data);
                case 'asana':
                    return $this->processAsanaWebhook($data);
                default:
                    return false;
            }
        } catch (Exception $e) {
            error_log("Error procesando webhook de {$platform}: " . $e->getMessage());
            return false;
        }
    }

    // Métodos auxiliares privados

    private function loadConfiguration(): array
    {
        return [
            'trello' => [
                'enabled' => $_ENV['TRELLO_ENABLED'] ?? false,
                'api_key' => $_ENV['TRELLO_API_KEY'] ?? '',
                'token' => $_ENV['TRELLO_TOKEN'] ?? '',
                'board_id' => $_ENV['TRELLO_BOARD_ID'] ?? '',
                'default_list' => $_ENV['TRELLO_DEFAULT_LIST'] ?? ''
            ],
            'asana' => [
                'enabled' => $_ENV['ASANA_ENABLED'] ?? false,
                'token' => $_ENV['ASANA_TOKEN'] ?? '',
                'default_project' => $_ENV['ASANA_DEFAULT_PROJECT'] ?? ''
            ],
            'monday' => [
                'enabled' => $_ENV['MONDAY_ENABLED'] ?? false,
                'token' => $_ENV['MONDAY_TOKEN'] ?? '',
                'default_board' => $_ENV['MONDAY_DEFAULT_BOARD'] ?? ''
            ],
            'webhook_base_url' => $_ENV['WEBHOOK_BASE_URL'] ?? 'https://sistema.com/webhooks'
        ];
    }

    private function makeHttpRequest(string $url, string $method, array $data = [], array $headers = []): ?array
    {
        $ch = curl_init();
        
        $defaultHeaders = ['Content-Type: application/json'];
        $headers = array_merge($defaultHeaders, $headers);

        curl_setopt_array($ch, [
            CURLOPT_URL => $method === 'GET' ? $url . '?' . http_build_query($data) : $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30
        ]);

        if ($method !== 'GET' && !empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return json_decode($response, true);
        }

        return null;
    }

    private function getAsanaHeaders(): array
    {
        return [
            'Authorization: Bearer ' . $this->config['asana']['token'],
            'Content-Type: application/json'
        ];
    }

    private function getMondayHeaders(): array
    {
        return [
            'Authorization: ' . $this->config['monday']['token'],
            'Content-Type: application/json'
        ];
    }

    private function saveProjectTask(array $taskData): int
    {
        $stmt = $this->conn->prepare("
            INSERT INTO project_tasks (platform, external_id, title, url, status, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");

        $stmt->bind_param(
            "sssss",
            $taskData['platform'],
            $taskData['external_id'],
            $taskData['title'],
            $taskData['url'],
            $taskData['status']
        );

        $stmt->execute();
        return $this->conn->insert_id;
    }

    private function getIncident(int $incidentId): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM incidents WHERE id = ?");
        $stmt->bind_param("i", $incidentId);
        $stmt->execute();
        
        $result = $stmt->get_result()->fetch_assoc();
        return $result ?: null;
    }

    private function formatIncidentDescription(array $incident): string
    {
        return "**Incidente del Sistema ISO 17025**\n\n" .
               "**Descripción:** {$incident['description']}\n" .
               "**Severidad:** {$incident['severity']}\n" .
               "**Prioridad:** {$incident['priority']}\n" .
               "**Categoría:** {$incident['category']}\n" .
               "**Fecha de creación:** {$incident['created_at']}\n\n" .
               "Este incidente requiere atención inmediata según los procedimientos establecidos.";
    }

    private function calculateDueDate(string $severity): string
    {
        $hours = match($severity) {
            'critical' => 4,
            'high' => 24,
            'medium' => 72,
            'low' => 168
        };

        return date('Y-m-d', strtotime("+{$hours} hours"));
    }

    private function getEnabledPlatforms(): array
    {
        $enabled = [];
        
        if ($this->config['trello']['enabled']) $enabled[] = 'trello';
        if ($this->config['asana']['enabled']) $enabled[] = 'asana';
        if ($this->config['monday']['enabled']) $enabled[] = 'monday';
        
        return $enabled;
    }

    private function linkIncidentToTask(int $incidentId, string $platform, string $externalId): void
    {
        $stmt = $this->conn->prepare("
            INSERT INTO incident_tasks (incident_id, platform, external_id, created_at) 
            VALUES (?, ?, ?, NOW())
        ");

        $stmt->bind_param("iss", $incidentId, $platform, $externalId);
        $stmt->execute();
    }

    private function getActiveProjectTasks(): array
    {
        $stmt = $this->conn->prepare("
            SELECT * FROM project_tasks 
            WHERE status IN ('open', 'in_progress') 
            AND updated_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    private function updateTaskStatus(int $taskId, string $status): void
    {
        $stmt = $this->conn->prepare("
            UPDATE project_tasks 
            SET status = ?, updated_at = NOW() 
            WHERE id = ?
        ");

        $stmt->bind_param("si", $status, $taskId);
        $stmt->execute();
    }

    private function processTrelloWebhook(array $data): bool
    {
        // Implementar lógica específica de Trello
        return true;
    }

    private function processAsanaWebhook(array $data): bool
    {
        // Implementar lógica específica de Asana
        return true;
    }

    private function getAsanaTaskStatus(string $taskId): ?string
    {
        // Implementar consulta a Asana API
        return 'open';
    }

    private function getMondayItemStatus(string $itemId): ?string
    {
        // Implementar consulta a Monday.com API
        return 'open';
    }

    private function setupAsanaWebhook(): bool
    {
        // Implementar configuración de webhook de Asana
        return true;
    }
}