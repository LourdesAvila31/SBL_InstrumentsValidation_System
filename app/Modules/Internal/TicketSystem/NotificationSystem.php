<?php
/**
 * Sistema de Notificaciones para Tickets
 * Conforme a GAMP 5 y normativas GxP
 * 
 * Maneja el envío de notificaciones automáticas para tickets de alto riesgo
 * y alertas de escalación basadas en SLA definidos.
 */

require_once dirname(__DIR__, 2) . '/Core/db.php';

class TicketNotificationSystem {
    private $conn;
    private $logFile;
    
    // Configuración de notificaciones
    private const NOTIFICATION_CONFIG = [
        'email' => [
            'enabled' => true,
            'smtp_host' => 'localhost',
            'smtp_port' => 587,
            'smtp_user' => '',
            'smtp_pass' => '',
            'from_email' => 'noreply@sistema-tickets.com',
            'from_name' => 'Sistema de Tickets GxP'
        ],
        'slack' => [
            'enabled' => false, // Configurar según necesidades
            'webhook_url' => '',
            'channel' => '#tickets-criticos'
        ]
    ];
    
    // Plantillas de notificación
    private const EMAIL_TEMPLATES = [
        'high_priority_created' => [
            'subject' => '[URGENTE] Nuevo Ticket de Alto Riesgo: {{ticket_number}}',
            'body' => '
                <h2>Ticket de Alto Riesgo Creado</h2>
                <p><strong>Número de Ticket:</strong> {{ticket_number}}</p>
                <p><strong>Título:</strong> {{title}}</p>
                <p><strong>Nivel de Riesgo:</strong> <span style="color: {{risk_color}};">{{risk_level}}</span></p>
                <p><strong>Categoría:</strong> {{category}}</p>
                <p><strong>Creado por:</strong> {{created_by}}</p>
                <p><strong>Fecha:</strong> {{created_at}}</p>
                
                <h3>Descripción:</h3>
                <p>{{description}}</p>
                
                <h3>Evaluación de Riesgo:</h3>
                <ul>
                    <li><strong>Impacto:</strong> {{impact}}</li>
                    <li><strong>Probabilidad:</strong> {{probability}}</li>
                    <li><strong>Severidad:</strong> {{severity}}</li>
                </ul>
                
                {{#if gxp_impact}}
                <div style="background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0;">
                    <strong>⚠️ IMPACTO REGULATORIO IDENTIFICADO</strong>
                    <p>Este ticket tiene impacto en cumplimiento GxP: {{gxp_classification}}</p>
                </div>
                {{/if}}
                
                <p><strong>Tiempo estimado de resolución:</strong> {{estimated_time}} horas</p>
                
                <p style="color: #dc3545;"><strong>Se requiere acción inmediata según políticas GAMP 5.</strong></p>
                
                <p><a href="{{ticket_url}}" style="background-color: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Ver Ticket</a></p>
            '
        ],
        
        'escalation_warning' => [
            'subject' => '[ESCALACIÓN] Ticket {{ticket_number}} próximo a vencer SLA',
            'body' => '
                <h2>Advertencia de Escalación</h2>
                <p><strong>Número de Ticket:</strong> {{ticket_number}}</p>
                <p><strong>Título:</strong> {{title}}</p>
                <p><strong>Estado Actual:</strong> {{status}}</p>
                <p><strong>Tiempo transcurrido:</strong> {{elapsed_time}} horas</p>
                <p><strong>Tiempo límite SLA:</strong> {{sla_time}} horas</p>
                <p><strong>Tiempo restante:</strong> {{remaining_time}} horas</p>
                
                <div style="background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; margin: 10px 0;">
                    <strong>⚠️ ATENCIÓN REQUERIDA</strong>
                    <p>Este ticket está próximo a exceder el SLA establecido.</p>
                </div>
                
                <p><a href="{{ticket_url}}" style="background-color: #fd7e14; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Gestionar Ticket</a></p>
            '
        ],
        
        'status_change' => [
            'subject' => 'Actualización Ticket {{ticket_number}}: {{new_status}}',
            'body' => '
                <h2>Actualización de Ticket</h2>
                <p><strong>Número de Ticket:</strong> {{ticket_number}}</p>
                <p><strong>Título:</strong> {{title}}</p>
                <p><strong>Estado Anterior:</strong> {{old_status}}</p>
                <p><strong>Nuevo Estado:</strong> {{new_status}}</p>
                <p><strong>Actualizado por:</strong> {{updated_by}}</p>
                <p><strong>Fecha:</strong> {{updated_at}}</p>
                
                {{#if comment}}
                <h3>Comentario:</h3>
                <p>{{comment}}</p>
                {{/if}}
                
                <p><a href="{{ticket_url}}" style="background-color: #0d6efd; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Ver Ticket</a></p>
            '
        ],
        
        'resolution_notification' => [
            'subject' => 'Ticket {{ticket_number}} - Resuelto',
            'body' => '
                <h2>Ticket Resuelto</h2>
                <p><strong>Número de Ticket:</strong> {{ticket_number}}</p>
                <p><strong>Título:</strong> {{title}}</p>
                <p><strong>Resuelto por:</strong> {{resolved_by}}</p>
                <p><strong>Tiempo de resolución:</strong> {{resolution_time}} horas</p>
                <p><strong>Fecha de resolución:</strong> {{resolved_at}}</p>
                
                <h3>Solución Aplicada:</h3>
                <p>{{resolution_description}}</p>
                
                {{#if root_cause}}
                <h3>Causa Raíz:</h3>
                <p>{{root_cause}}</p>
                {{/if}}
                
                {{#if corrective_actions}}
                <h3>Acciones Correctivas:</h3>
                <p>{{corrective_actions}}</p>
                {{/if}}
                
                {{#if preventive_actions}}
                <h3>Acciones Preventivas:</h3>
                <p>{{preventive_actions}}</p>
                {{/if}}
                
                <div style="background-color: #d1ecf1; border: 1px solid #bee5eb; padding: 10px; margin: 10px 0;">
                    <strong>✅ CONFORMIDAD GxP</strong>
                    <p>La resolución ha sido documentada conforme a normativas GAMP 5 y GxP.</p>
                </div>
                
                <p><a href="{{ticket_url}}" style="background-color: #198754; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Ver Detalles</a></p>
            '
        ]
    ];
    
    public function __construct() {
        $this->conn = DatabaseManager::getConnection();
        $this->logFile = dirname(__DIR__, 3) . '/storage/logs/notifications.log';
        $this->createNotificationTables();
    }
    
    /**
     * Crear tablas para el sistema de notificaciones
     */
    private function createNotificationTables() {
        $tables = [
            'notification_settings' => "
                CREATE TABLE IF NOT EXISTS notification_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    notification_type ENUM('email', 'slack', 'in_app') NOT NULL,
                    event_type ENUM('high_priority_created', 'escalation_warning', 'status_change', 
                                   'resolution_notification', 'assignment', 'all') NOT NULL,
                    enabled BOOLEAN DEFAULT TRUE,
                    settings JSON,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_user_notification (user_id, notification_type, event_type),
                    INDEX idx_user_id (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            
            'notification_log' => "
                CREATE TABLE IF NOT EXISTS notification_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ticket_id INT,
                    user_id INT,
                    notification_type ENUM('email', 'slack', 'in_app') NOT NULL,
                    event_type ENUM('high_priority_created', 'escalation_warning', 'status_change', 
                                   'resolution_notification', 'assignment') NOT NULL,
                    recipient VARCHAR(255) NOT NULL,
                    subject VARCHAR(500),
                    message TEXT,
                    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
                    sent_at TIMESTAMP NULL,
                    error_message TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_ticket_id (ticket_id),
                    INDEX idx_user_id (user_id),
                    INDEX idx_status (status),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            
            'sla_monitoring' => "
                CREATE TABLE IF NOT EXISTS sla_monitoring (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ticket_id INT NOT NULL,
                    sla_hours INT NOT NULL,
                    warning_sent BOOLEAN DEFAULT FALSE,
                    escalation_sent BOOLEAN DEFAULT FALSE,
                    next_check_at TIMESTAMP NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
                    INDEX idx_ticket_id (ticket_id),
                    INDEX idx_next_check (next_check_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            "
        ];
        
        foreach ($tables as $tableName => $sql) {
            if (!$this->conn->query($sql)) {
                $this->logError("Error creando tabla $tableName: " . $this->conn->error);
            }
        }
    }
    
    /**
     * Envía notificación para ticket de alta prioridad
     */
    public function sendHighPriorityNotification($ticketId) {
        $ticket = $this->getTicketDetails($ticketId);
        if (!$ticket) return false;
        
        // Solo enviar para tickets de riesgo Alto o Crítico
        if (!in_array($ticket['risk_level'], ['Alto', 'Crítico'])) {
            return true;
        }
        
        // Obtener destinatarios basados en configuración
        $recipients = $this->getNotificationRecipients('high_priority_created', $ticket);
        
        foreach ($recipients as $recipient) {
            $this->sendNotification(
                $ticketId,
                $recipient['user_id'],
                $recipient['type'],
                'high_priority_created',
                $recipient['address'],
                $this->buildNotificationContent('high_priority_created', $ticket)
            );
        }
        
        // Configurar monitoreo SLA
        $this->setupSLAMonitoring($ticketId, $ticket['risk_level']);
        
        return true;
    }
    
    /**
     * Envía notificación de cambio de estado
     */
    public function sendStatusChangeNotification($ticketId, $oldStatus, $newStatus, $comment = null, $updatedBy = null) {
        $ticket = $this->getTicketDetails($ticketId);
        if (!$ticket) return false;
        
        $ticket['old_status'] = $oldStatus;
        $ticket['new_status'] = $newStatus;
        $ticket['comment'] = $comment;
        $ticket['updated_by'] = $updatedBy;
        
        $recipients = $this->getNotificationRecipients('status_change', $ticket);
        
        foreach ($recipients as $recipient) {
            $this->sendNotification(
                $ticketId,
                $recipient['user_id'],
                $recipient['type'],
                'status_change',
                $recipient['address'],
                $this->buildNotificationContent('status_change', $ticket)
            );
        }
        
        return true;
    }
    
    /**
     * Envía notificación de resolución
     */
    public function sendResolutionNotification($ticketId) {
        $ticket = $this->getTicketDetails($ticketId);
        if (!$ticket) return false;
        
        $recipients = $this->getNotificationRecipients('resolution_notification', $ticket);
        
        foreach ($recipients as $recipient) {
            $this->sendNotification(
                $ticketId,
                $recipient['user_id'],
                $recipient['type'],
                'resolution_notification',
                $recipient['address'],
                $this->buildNotificationContent('resolution_notification', $ticket)
            );
        }
        
        return true;
    }
    
    /**
     * Verifica y envía alertas de escalación
     */
    public function checkEscalationAlerts() {
        $sql = "SELECT sm.*, t.ticket_number, t.title, t.status, t.risk_level, t.created_at
                FROM sla_monitoring sm
                JOIN tickets t ON sm.ticket_id = t.id
                WHERE sm.next_check_at <= NOW() 
                AND t.status NOT IN ('Resuelto', 'Cerrado')
                ORDER BY sm.next_check_at ASC";
        
        $result = $this->conn->query($sql);
        
        while ($row = $result->fetch_assoc()) {
            $this->processEscalationCheck($row);
        }
    }
    
    /**
     * Procesa verificación de escalación para un ticket
     */
    private function processEscalationCheck($monitoringData) {
        $ticketId = $monitoringData['ticket_id'];
        $slaHours = $monitoringData['sla_hours'];
        $createdAt = new DateTime($monitoringData['created_at']);
        $now = new DateTime();
        $elapsedHours = $now->diff($createdAt)->h + ($now->diff($createdAt)->days * 24);
        
        // Calcular tiempo restante
        $remainingHours = $slaHours - $elapsedHours;
        
        // Enviar advertencia a 75% del SLA si no se ha enviado
        if (!$monitoringData['warning_sent'] && $remainingHours <= ($slaHours * 0.25)) {
            $this->sendEscalationWarning($ticketId, $elapsedHours, $slaHours, $remainingHours);
            
            // Marcar advertencia como enviada
            $updateSql = "UPDATE sla_monitoring SET warning_sent = TRUE WHERE id = ?";
            $stmt = $this->conn->prepare($updateSql);
            $stmt->bind_param("i", $monitoringData['id']);
            $stmt->execute();
        }
        
        // Enviar escalación si se excedió el SLA
        if (!$monitoringData['escalation_sent'] && $remainingHours <= 0) {
            $this->sendEscalationAlert($ticketId, $elapsedHours, $slaHours);
            
            // Marcar escalación como enviada
            $updateSql = "UPDATE sla_monitoring SET escalation_sent = TRUE WHERE id = ?";
            $stmt = $this->conn->prepare($updateSql);
            $stmt->bind_param("i", $monitoringData['id']);
            $stmt->execute();
        }
        
        // Actualizar próxima verificación (cada hora)
        $nextCheck = $now->add(new DateInterval('PT1H'));
        $updateSql = "UPDATE sla_monitoring SET next_check_at = ? WHERE id = ?";
        $stmt = $this->conn->prepare($updateSql);
        $stmt->bind_param("si", $nextCheck->format('Y-m-d H:i:s'), $monitoringData['id']);
        $stmt->execute();
    }
    
    /**
     * Configura monitoreo SLA para un ticket
     */
    private function setupSLAMonitoring($ticketId, $riskLevel) {
        $slaHours = [
            'Crítico' => 1,
            'Alto' => 4,
            'Medio' => 24,
            'Bajo' => 72,
            'Mínimo' => 168
        ][$riskLevel] ?? 24;
        
        $nextCheck = new DateTime();
        $nextCheck->add(new DateInterval('PT1H')); // Primera verificación en 1 hora
        
        $sql = "INSERT INTO sla_monitoring (ticket_id, sla_hours, next_check_at) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE sla_hours = VALUES(sla_hours), next_check_at = VALUES(next_check_at)";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("iis", $ticketId, $slaHours, $nextCheck->format('Y-m-d H:i:s'));
        $stmt->execute();
    }
    
    /**
     * Obtiene detalles completos del ticket
     */
    private function getTicketDetails($ticketId) {
        $sql = "SELECT t.*, u1.nombre as created_by_name, u1.email as created_by_email,
                       u2.nombre as assigned_to_name, u2.email as assigned_to_email
                FROM tickets t
                LEFT JOIN usuarios u1 ON t.created_by = u1.id
                LEFT JOIN usuarios u2 ON t.assigned_to = u2.id
                WHERE t.id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $ticketId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    /**
     * Obtiene destinatarios para un tipo de notificación
     */
    private function getNotificationRecipients($eventType, $ticket) {
        $recipients = [];
        
        // Siempre notificar al creador del ticket
        if ($ticket['created_by_email']) {
            $recipients[] = [
                'user_id' => $ticket['created_by'],
                'type' => 'email',
                'address' => $ticket['created_by_email']
            ];
        }
        
        // Notificar al asignado si existe
        if ($ticket['assigned_to'] && $ticket['assigned_to_email']) {
            $recipients[] = [
                'user_id' => $ticket['assigned_to'],
                'type' => 'email',
                'address' => $ticket['assigned_to_email']
            ];
        }
        
        // Para tickets críticos, notificar a administradores
        if (in_array($ticket['risk_level'], ['Crítico', 'Alto'])) {
            $adminSql = "SELECT id, email FROM usuarios WHERE role IN ('admin', 'superadmin', 'qa_manager') AND active = 1";
            $adminResult = $this->conn->query($adminSql);
            
            while ($admin = $adminResult->fetch_assoc()) {
                $recipients[] = [
                    'user_id' => $admin['id'],
                    'type' => 'email',
                    'address' => $admin['email']
                ];
            }
        }
        
        return array_unique($recipients, SORT_REGULAR);
    }
    
    /**
     * Construye el contenido de la notificación usando plantillas
     */
    private function buildNotificationContent($templateType, $ticket) {
        $template = self::EMAIL_TEMPLATES[$templateType] ?? null;
        if (!$template) return null;
        
        // Mapear colores de riesgo
        $riskColors = [
            'Crítico' => '#dc3545',
            'Alto' => '#fd7e14',
            'Medio' => '#ffc107',
            'Bajo' => '#20c997',
            'Mínimo' => '#6c757d'
        ];
        
        // Variables para reemplazar en plantillas
        $variables = [
            'ticket_number' => $ticket['ticket_number'] ?? '',
            'title' => $ticket['title'] ?? '',
            'description' => $ticket['description'] ?? '',
            'risk_level' => $ticket['risk_level'] ?? '',
            'risk_color' => $riskColors[$ticket['risk_level']] ?? '#6c757d',
            'category' => str_replace('_', ' ', $ticket['category'] ?? ''),
            'status' => $ticket['status'] ?? '',
            'impact' => $ticket['impact'] ?? '',
            'probability' => $ticket['probability'] ?? '',
            'severity' => $ticket['severity'] ?? '',
            'gxp_classification' => $ticket['gxp_classification'] ?? '',
            'created_by' => $ticket['created_by_name'] ?? '',
            'created_at' => isset($ticket['created_at']) ? date('d/m/Y H:i', strtotime($ticket['created_at'])) : '',
            'estimated_time' => $ticket['estimated_resolution_time'] ?? '',
            'ticket_url' => "http://{$_SERVER['HTTP_HOST']}/public/apps/ticket-system/index.html#ticket={$ticket['id']}",
            'gxp_impact' => ($ticket['regulatory_impact'] || $ticket['validation_impact']),
            'old_status' => $ticket['old_status'] ?? '',
            'new_status' => $ticket['new_status'] ?? '',
            'comment' => $ticket['comment'] ?? '',
            'updated_by' => $ticket['updated_by'] ?? '',
            'updated_at' => date('d/m/Y H:i'),
            'resolution_description' => $ticket['resolution_description'] ?? '',
            'root_cause' => $ticket['root_cause'] ?? '',
            'corrective_actions' => $ticket['corrective_actions'] ?? '',
            'preventive_actions' => $ticket['preventive_actions'] ?? '',
            'resolution_time' => $ticket['actual_resolution_time'] ?? '',
            'resolved_at' => isset($ticket['resolved_at']) ? date('d/m/Y H:i', strtotime($ticket['resolved_at'])) : '',
            'resolved_by' => $ticket['assigned_to_name'] ?? ''
        ];
        
        $subject = $this->replaceVariables($template['subject'], $variables);
        $body = $this->replaceVariables($template['body'], $variables);
        
        return ['subject' => $subject, 'body' => $body];
    }
    
    /**
     * Reemplaza variables en plantillas
     */
    private function replaceVariables($template, $variables) {
        foreach ($variables as $key => $value) {
            $template = str_replace("{{$key}}", $value, $template);
        }
        
        // Procesar condicionales simples {{#if variable}}...{{/if}}
        $template = preg_replace_callback('/\{\{#if\s+(\w+)\}\}(.*?)\{\{\/if\}\}/s', function($matches) use ($variables) {
            $varName = $matches[1];
            $content = $matches[2];
            return !empty($variables[$varName]) ? $content : '';
        }, $template);
        
        return $template;
    }
    
    /**
     * Envía una notificación
     */
    private function sendNotification($ticketId, $userId, $type, $eventType, $recipient, $content) {
        if (!$content) return false;
        
        // Registrar en log de notificaciones
        $logSql = "INSERT INTO notification_log (ticket_id, user_id, notification_type, event_type, recipient, subject, message) 
                   VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($logSql);
        $stmt->bind_param("iisssss", $ticketId, $userId, $type, $eventType, $recipient, $content['subject'], $content['body']);
        $stmt->execute();
        $logId = $this->conn->insert_id;
        
        $success = false;
        
        try {
            switch ($type) {
                case 'email':
                    $success = $this->sendEmailNotification($recipient, $content['subject'], $content['body']);
                    break;
                case 'slack':
                    $success = $this->sendSlackNotification($content['subject'] . "\n" . strip_tags($content['body']));
                    break;
            }
            
            // Actualizar estado en log
            $updateSql = "UPDATE notification_log SET status = ?, sent_at = NOW() WHERE id = ?";
            $updateStmt = $this->conn->prepare($updateSql);
            $status = $success ? 'sent' : 'failed';
            $updateStmt->bind_param("si", $status, $logId);
            $updateStmt->execute();
            
        } catch (Exception $e) {
            // Registrar error
            $errorSql = "UPDATE notification_log SET status = 'failed', error_message = ? WHERE id = ?";
            $errorStmt = $this->conn->prepare($errorSql);
            $errorStmt->bind_param("si", $e->getMessage(), $logId);
            $errorStmt->execute();
            
            $this->logError("Error enviando notificación: " . $e->getMessage());
        }
        
        return $success;
    }
    
    /**
     * Envía notificación por email
     */
    private function sendEmailNotification($to, $subject, $body) {
        // Usar PHPMailer o mail() según configuración
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . self::NOTIFICATION_CONFIG['email']['from_name'] . ' <' . self::NOTIFICATION_CONFIG['email']['from_email'] . '>',
            'Reply-To: ' . self::NOTIFICATION_CONFIG['email']['from_email'],
            'X-Mailer: PHP/' . phpversion()
        ];
        
        return mail($to, $subject, $body, implode("\r\n", $headers));
    }
    
    /**
     * Envía notificación por Slack
     */
    private function sendSlackNotification($message) {
        if (!self::NOTIFICATION_CONFIG['slack']['enabled'] || !self::NOTIFICATION_CONFIG['slack']['webhook_url']) {
            return false;
        }
        
        $payload = json_encode([
            'channel' => self::NOTIFICATION_CONFIG['slack']['channel'],
            'text' => $message,
            'username' => 'Sistema Tickets GxP'
        ]);
        
        $ch = curl_init(self::NOTIFICATION_CONFIG['slack']['webhook_url']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 200;
    }
    
    /**
     * Envía advertencia de escalación
     */
    private function sendEscalationWarning($ticketId, $elapsedHours, $slaHours, $remainingHours) {
        $ticket = $this->getTicketDetails($ticketId);
        $ticket['elapsed_time'] = $elapsedHours;
        $ticket['sla_time'] = $slaHours;
        $ticket['remaining_time'] = $remainingHours;
        
        $recipients = $this->getNotificationRecipients('escalation_warning', $ticket);
        
        foreach ($recipients as $recipient) {
            $this->sendNotification(
                $ticketId,
                $recipient['user_id'],
                $recipient['type'],
                'escalation_warning',
                $recipient['address'],
                $this->buildNotificationContent('escalation_warning', $ticket)
            );
        }
    }
    
    /**
     * Envía alerta de escalación
     */
    private function sendEscalationAlert($ticketId, $elapsedHours, $slaHours) {
        // Implementar escalación automática del ticket
        $sql = "UPDATE tickets SET status = 'Escalado' WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $ticketId);
        $stmt->execute();
        
        $this->logActivity("Ticket ID $ticketId escalado automáticamente por exceder SLA ($elapsedHours horas)");
    }
    
    /**
     * Registra actividad en el log
     */
    private function logActivity($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
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
}

// Script para ejecutar verificaciones periódicas (usar con cron)
if (php_sapi_name() === 'cli') {
    echo "Iniciando verificación de escalaciones...\n";
    $notificationSystem = new TicketNotificationSystem();
    $notificationSystem->checkEscalationAlerts();
    echo "Verificación completada.\n";
}
?>