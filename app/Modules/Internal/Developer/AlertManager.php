<?php
declare(strict_types=1);

require_once __DIR__ . '/DeveloperAuth.php';

/**
 * Sistema de Automatización de Alertas
 * Configura y envía alertas automáticas basadas en eventos del sistema
 */
class AlertManager
{
    private mysqli $conn;
    private DeveloperAuth $auth;
    
    const ALERT_TYPES = ['system', 'security', 'performance', 'compliance', 'business'];
    const ALERT_SEVERITIES = ['info', 'warning', 'critical'];
    const ALERT_CHANNELS = ['email', 'sms', 'slack', 'webhook', 'database'];
    const ALERT_STATUSES = ['active', 'acknowledged', 'resolved', 'suppressed'];
    
    public function __construct(?mysqli $connection = null)
    {
        global $conn;
        $this->conn = $connection ?? $conn ?? DatabaseManager::getConnection();
        $this->auth = new DeveloperAuth($this->conn);
    }

    /**
     * Crea una nueva regla de alerta
     */
    public function createAlertRule(array $ruleData): array
    {
        if (!$this->auth->hasPrivateAccess('alerts')) {
            throw new Exception('Acceso denegado para crear reglas de alerta');
        }

        $requiredFields = ['name', 'alert_type', 'condition', 'severity'];
        foreach ($requiredFields as $field) {
            if (empty($ruleData[$field])) {
                throw new Exception("Campo requerido faltante: {$field}");
            }
        }

        if (!in_array($ruleData['alert_type'], self::ALERT_TYPES)) {
            throw new Exception('Tipo de alerta inválido');
        }

        if (!in_array($ruleData['severity'], self::ALERT_SEVERITIES)) {
            throw new Exception('Severidad de alerta inválida');
        }

        $developerInfo = $this->auth->getDeveloperInfo();
        
        $rule = [
            'name' => trim($ruleData['name']),
            'description' => trim($ruleData['description'] ?? ''),
            'alert_type' => $ruleData['alert_type'],
            'severity' => $ruleData['severity'],
            'condition' => $ruleData['condition'], // JSON con condiciones
            'threshold_config' => json_encode($ruleData['thresholds'] ?? []),
            'channels' => json_encode($ruleData['channels'] ?? ['database']),
            'recipients' => json_encode($ruleData['recipients'] ?? []),
            'schedule_config' => json_encode($ruleData['schedule'] ?? ['always' => true]),
            'suppression_config' => json_encode($ruleData['suppression'] ?? []),
            'template_subject' => trim($ruleData['template_subject'] ?? ''),
            'template_body' => trim($ruleData['template_body'] ?? ''),
            'enabled' => $ruleData['enabled'] ?? true,
            'auto_resolve' => $ruleData['auto_resolve'] ?? false,
            'escalation_config' => json_encode($ruleData['escalation'] ?? []),
            'created_by' => $developerInfo['id'],
            'created_at' => date('Y-m-d H:i:s')
        ];

        $stmt = $this->conn->prepare(
            'INSERT INTO alert_rules (name, description, alert_type, severity, condition_config, 
                                    threshold_config, channels, recipients, schedule_config, 
                                    suppression_config, template_subject, template_body, enabled, 
                                    auto_resolve, escalation_config, created_by, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        if (!$stmt) {
            throw new Exception('Error preparando consulta de regla de alerta');
        }

        $stmt->bind_param(
            'ssssssssssssiississ',
            $rule['name'],
            $rule['description'],
            $rule['alert_type'],
            $rule['severity'],
            $rule['condition'],
            $rule['threshold_config'],
            $rule['channels'],
            $rule['recipients'],
            $rule['schedule_config'],
            $rule['suppression_config'],
            $rule['template_subject'],
            $rule['template_body'],
            $rule['enabled'],
            $rule['auto_resolve'],
            $rule['escalation_config'],
            $rule['created_by'],
            $rule['created_at']
        );

        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception('Error creando regla de alerta');
        }

        $ruleId = $this->conn->insert_id;
        $stmt->close();

        $this->auth->logDeveloperActivity('alert_rule_created', [
            'rule_id' => $ruleId,
            'name' => $rule['name'],
            'alert_type' => $rule['alert_type'],
            'severity' => $rule['severity']
        ]);

        return array_merge($rule, ['id' => $ruleId]);
    }

    /**
     * Procesa eventos del sistema y evalúa reglas de alerta
     */
    public function processSystemEvent(array $eventData): array
    {
        $triggeredAlerts = [];
        
        // Obtener reglas activas que podrían aplicar
        $rules = $this->getActiveAlertRules($eventData['event_type'] ?? 'system');
        
        foreach ($rules as $rule) {
            if ($this->evaluateCondition($rule, $eventData)) {
                $alert = $this->triggerAlert($rule, $eventData);
                if ($alert) {
                    $triggeredAlerts[] = $alert;
                }
            }
        }

        return $triggeredAlerts;
    }

    /**
     * Dispara una alerta basada en una regla
     */
    public function triggerAlert(array $rule, array $eventData): ?array
    {
        // Verificar si la alerta está suprimida
        if ($this->isAlertSuppressed($rule, $eventData)) {
            return null;
        }

        // Verificar horario de activación
        if (!$this->isInSchedule($rule)) {
            return null;
        }

        $alert = [
            'rule_id' => $rule['id'],
            'alert_type' => $rule['alert_type'],
            'severity' => $rule['severity'],
            'title' => $this->renderTemplate($rule['template_subject'], $eventData),
            'message' => $this->renderTemplate($rule['template_body'], $eventData),
            'source_data' => json_encode($eventData),
            'status' => 'active',
            'triggered_at' => date('Y-m-d H:i:s'),
            'correlation_id' => $this->generateCorrelationId($rule, $eventData)
        ];

        $stmt = $this->conn->prepare(
            'INSERT INTO alerts (rule_id, alert_type, severity, title, message, source_data, 
                               status, triggered_at, correlation_id) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        if (!$stmt) {
            return null;
        }

        $stmt->bind_param(
            'issssssss',
            $alert['rule_id'],
            $alert['alert_type'],
            $alert['severity'],
            $alert['title'],
            $alert['message'],
            $alert['source_data'],
            $alert['status'],
            $alert['triggered_at'],
            $alert['correlation_id']
        );

        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }

        $alertId = $this->conn->insert_id;
        $stmt->close();

        $alert['id'] = $alertId;

        // Enviar notificaciones
        $this->sendAlertNotifications($rule, $alert);

        // Registrar en auditoría
        $this->auth->logDeveloperActivity('alert_triggered', [
            'alert_id' => $alertId,
            'rule_id' => $rule['id'],
            'severity' => $alert['severity'],
            'correlation_id' => $alert['correlation_id']
        ]);

        return $alert;
    }

    /**
     * Envía notificaciones de alerta a través de los canales configurados
     */
    public function sendAlertNotifications(array $rule, array $alert): bool
    {
        $channels = json_decode($rule['channels'], true) ?? [];
        $recipients = json_decode($rule['recipients'], true) ?? [];
        $success = true;

        foreach ($channels as $channel) {
            try {
                switch ($channel) {
                    case 'email':
                        $this->sendEmailAlert($alert, $recipients);
                        break;
                    case 'sms':
                        $this->sendSMSAlert($alert, $recipients);
                        break;
                    case 'slack':
                        $this->sendSlackAlert($alert, $recipients);
                        break;
                    case 'webhook':
                        $this->sendWebhookAlert($alert, $recipients);
                        break;
                    case 'database':
                        // Ya guardado en base de datos
                        break;
                }
            } catch (Exception $e) {
                $success = false;
                error_log("Error enviando alerta por {$channel}: " . $e->getMessage());
            }
        }

        return $success;
    }

    /**
     * Reconoce una alerta
     */
    public function acknowledgeAlert(int $alertId, string $notes = ''): bool
    {
        if (!$this->auth->hasPrivateAccess('alerts')) {
            throw new Exception('Acceso denegado para reconocer alertas');
        }

        $developerInfo = $this->auth->getDeveloperInfo();
        
        $stmt = $this->conn->prepare(
            'UPDATE alerts 
             SET status = "acknowledged", acknowledged_by = ?, acknowledged_at = ?, ack_notes = ?
             WHERE id = ? AND status = "active"'
        );

        if (!$stmt) {
            throw new Exception('Error preparando reconocimiento de alerta');
        }

        $acknowledgedAt = date('Y-m-d H:i:s');
        $stmt->bind_param('issi', $developerInfo['id'], $acknowledgedAt, $notes, $alertId);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            $this->auth->logDeveloperActivity('alert_acknowledged', [
                'alert_id' => $alertId,
                'acknowledged_by' => $developerInfo['nombre_completo'],
                'notes' => $notes
            ]);
        }

        return $result;
    }

    /**
     * Resuelve una alerta
     */
    public function resolveAlert(int $alertId, string $resolution = ''): bool
    {
        if (!$this->auth->hasPrivateAccess('alerts')) {
            throw new Exception('Acceso denegado para resolver alertas');
        }

        $developerInfo = $this->auth->getDeveloperInfo();
        
        $stmt = $this->conn->prepare(
            'UPDATE alerts 
             SET status = "resolved", resolved_by = ?, resolved_at = ?, resolution = ?
             WHERE id = ? AND status IN ("active", "acknowledged")'
        );

        if (!$stmt) {
            throw new Exception('Error preparando resolución de alerta');
        }

        $resolvedAt = date('Y-m-d H:i:s');
        $stmt->bind_param('issi', $developerInfo['id'], $resolvedAt, $resolution, $alertId);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            $this->auth->logDeveloperActivity('alert_resolved', [
                'alert_id' => $alertId,
                'resolved_by' => $developerInfo['nombre_completo'],
                'resolution' => $resolution
            ]);
        }

        return $result;
    }

    /**
     * Obtiene alertas con filtros
     */
    public function getAlerts(array $filters = []): array
    {
        if (!$this->auth->hasPrivateAccess('alerts')) {
            throw new Exception('Acceso denegado para ver alertas');
        }

        $where = ['1=1'];
        $params = [];
        $types = '';

        if (!empty($filters['status'])) {
            $where[] = 'a.status = ?';
            $params[] = $filters['status'];
            $types .= 's';
        }

        if (!empty($filters['severity'])) {
            $where[] = 'a.severity = ?';
            $params[] = $filters['severity'];
            $types .= 's';
        }

        if (!empty($filters['alert_type'])) {
            $where[] = 'a.alert_type = ?';
            $params[] = $filters['alert_type'];
            $types .= 's';
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'a.triggered_at >= ?';
            $params[] = $filters['date_from'];
            $types .= 's';
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'a.triggered_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
            $types .= 's';
        }

        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 100;
        $offset = isset($filters['offset']) ? (int)$filters['offset'] : 0;

        $sql = 'SELECT a.*, r.name as rule_name, u1.nombre as acknowledged_by_name, u2.nombre as resolved_by_name
                FROM alerts a
                LEFT JOIN alert_rules r ON r.id = a.rule_id
                LEFT JOIN usuarios u1 ON u1.id = a.acknowledged_by
                LEFT JOIN usuarios u2 ON u2.id = a.resolved_by
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY a.triggered_at DESC
                LIMIT ? OFFSET ?';

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Error preparando consulta de alertas');
        }

        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $alerts = [];
        while ($row = $result->fetch_assoc()) {
            $row['source_data'] = json_decode($row['source_data'] ?? '{}', true);
            $alerts[] = $row;
        }
        $stmt->close();

        return $alerts;
    }

    /**
     * Obtiene métricas de alertas
     */
    public function getAlertMetrics(): array
    {
        if (!$this->auth->hasPrivateAccess('alerts')) {
            throw new Exception('Acceso denegado para ver métricas de alertas');
        }

        // Alertas por severidad (últimas 24h)
        $stmt = $this->conn->prepare(
            'SELECT severity, COUNT(*) as count 
             FROM alerts 
             WHERE triggered_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             GROUP BY severity'
        );
        $stmt->execute();
        $result = $stmt->get_result();
        $bySeverity = [];
        while ($row = $result->fetch_assoc()) {
            $bySeverity[$row['severity']] = (int)$row['count'];
        }
        $stmt->close();

        // Alertas por estado
        $stmt = $this->conn->prepare(
            'SELECT status, COUNT(*) as count 
             FROM alerts 
             WHERE triggered_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY status'
        );
        $stmt->execute();
        $result = $stmt->get_result();
        $byStatus = [];
        while ($row = $result->fetch_assoc()) {
            $byStatus[$row['status']] = (int)$row['count'];
        }
        $stmt->close();

        // Tiempo promedio de resolución
        $stmt = $this->conn->prepare(
            'SELECT AVG(TIMESTAMPDIFF(MINUTE, triggered_at, resolved_at)) as avg_resolution_minutes
             FROM alerts 
             WHERE status = "resolved" AND resolved_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
        );
        $stmt->execute();
        $avgResolution = (float)($stmt->get_result()->fetch_assoc()['avg_resolution_minutes'] ?? 0);
        $stmt->close();

        // Top reglas que más alertas generan
        $stmt = $this->conn->prepare(
            'SELECT r.name, COUNT(a.id) as alert_count
             FROM alerts a
             JOIN alert_rules r ON r.id = a.rule_id
             WHERE a.triggered_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY r.id, r.name
             ORDER BY alert_count DESC
             LIMIT 10'
        );
        $stmt->execute();
        $result = $stmt->get_result();
        $topRules = [];
        while ($row = $result->fetch_assoc()) {
            $topRules[] = [
                'rule_name' => $row['name'],
                'alert_count' => (int)$row['alert_count']
            ];
        }
        $stmt->close();

        return [
            'by_severity_24h' => $bySeverity,
            'by_status_7d' => $byStatus,
            'avg_resolution_minutes' => round($avgResolution, 2),
            'top_alert_rules' => $topRules,
            'active_alerts' => $byStatus['active'] ?? 0,
            'critical_alerts_24h' => $bySeverity['critical'] ?? 0
        ];
    }

    /**
     * Métodos auxiliares privados
     */
    private function getActiveAlertRules(string $eventType): array
    {
        $stmt = $this->conn->prepare(
            'SELECT * FROM alert_rules 
             WHERE enabled = 1 AND (alert_type = ? OR alert_type = "system")
             ORDER BY severity DESC'
        );

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('s', $eventType);
        $stmt->execute();
        $result = $stmt->get_result();

        $rules = [];
        while ($row = $result->fetch_assoc()) {
            $row['condition_config'] = json_decode($row['condition_config'] ?? '{}', true);
            $row['threshold_config'] = json_decode($row['threshold_config'] ?? '{}', true);
            $row['channels'] = json_decode($row['channels'] ?? '[]', true);
            $row['recipients'] = json_decode($row['recipients'] ?? '[]', true);
            $row['schedule_config'] = json_decode($row['schedule_config'] ?? '{}', true);
            $row['suppression_config'] = json_decode($row['suppression_config'] ?? '{}', true);
            $rules[] = $row;
        }
        $stmt->close();

        return $rules;
    }

    private function evaluateCondition(array $rule, array $eventData): bool
    {
        $condition = $rule['condition_config'];
        $thresholds = $rule['threshold_config'];

        // Implementar lógica de evaluación de condiciones
        // Esta es una implementación simplificada
        foreach ($thresholds as $field => $threshold) {
            if (isset($eventData[$field])) {
                $value = $eventData[$field];
                
                if (isset($threshold['min']) && $value < $threshold['min']) {
                    return true;
                }
                if (isset($threshold['max']) && $value > $threshold['max']) {
                    return true;
                }
                if (isset($threshold['equals']) && $value == $threshold['equals']) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isAlertSuppressed(array $rule, array $eventData): bool
    {
        $suppression = $rule['suppression_config'];
        
        if (!empty($suppression['duration_minutes'])) {
            $stmt = $this->conn->prepare(
                'SELECT COUNT(*) as count FROM alerts 
                 WHERE rule_id = ? AND correlation_id = ? 
                 AND triggered_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)'
            );
            
            if ($stmt) {
                $correlationId = $this->generateCorrelationId($rule, $eventData);
                $stmt->bind_param('isi', $rule['id'], $correlationId, $suppression['duration_minutes']);
                $stmt->execute();
                $count = (int)$stmt->get_result()->fetch_assoc()['count'];
                $stmt->close();
                
                return $count > 0;
            }
        }

        return false;
    }

    private function isInSchedule(array $rule): bool
    {
        $schedule = $rule['schedule_config'];
        
        if (isset($schedule['always']) && $schedule['always']) {
            return true;
        }

        // Implementar lógica de horarios más compleja si es necesario
        return true;
    }

    private function renderTemplate(string $template, array $eventData): string
    {
        $rendered = $template;
        
        foreach ($eventData as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $rendered = str_replace($placeholder, (string)$value, $rendered);
        }

        return $rendered;
    }

    private function generateCorrelationId(array $rule, array $eventData): string
    {
        $key = $rule['id'] . '_' . ($eventData['source'] ?? 'system') . '_' . ($eventData['identifier'] ?? 'default');
        return md5($key);
    }

    private function sendEmailAlert(array $alert, array $recipients): void
    {
        // Implementar envío de email
        // Podría usar PHPMailer o similar
    }

    private function sendSMSAlert(array $alert, array $recipients): void
    {
        // Implementar envío de SMS
        // Podría integrar con Twilio o similar
    }

    private function sendSlackAlert(array $alert, array $recipients): void
    {
        // Implementar envío a Slack
        // Usar webhook de Slack
    }

    private function sendWebhookAlert(array $alert, array $recipients): void
    {
        // Implementar envío por webhook
        // Hacer POST a URLs configuradas
    }
}