<?php

/**
 * Sistema de Alertas Automáticas
 * Gestiona notificaciones, alertas por email y logging de eventos críticos
 * 
 * @version 1.0
 * @author Sistema SBL
 * @date 2025-09-26  
 */

class AlertManager
{
    private $config;
    private $logFile;
    private $alertsFile;
    private $emailConfig;

    public function __construct($config = null)
    {
        $this->config = $config ?? $this->getDefaultConfig();
        $this->logFile = __DIR__ . '/../../../storage/logs/alerts.log';
        $this->alertsFile = __DIR__ . '/../../../storage/alerts/active_alerts.json';
        $this->emailConfig = $this->config['email'] ?? [];
        $this->ensureDirectories();
    }

    /**
     * Configuración por defecto
     */
    private function getDefaultConfig(): array
    {
        return [
            'email' => [
                'enabled' => true,
                'smtp_host' => 'localhost',
                'smtp_port' => 587,
                'username' => '',
                'password' => '',
                'from_email' => 'alerts@sbl-sistema.local',
                'from_name' => 'Sistema SBL - Alertas',
                'to_emails' => ['admin@empresa.com']
            ],
            'thresholds' => [
                'critical_response_time' => 5000, // ms
                'critical_memory_usage' => 90,    // %
                'critical_disk_usage' => 95,      // %
                'warning_response_time' => 2000,  // ms
                'warning_memory_usage' => 80,     // %
                'warning_disk_usage' => 85        // %
            ],
            'cooldown' => [
                'critical' => 300,  // 5 minutos
                'warning' => 900,   // 15 minutos
                'info' => 1800      // 30 minutos
            ],
            'channels' => [
                'email' => true,
                'log' => true,
                'dashboard' => true,
                'webhook' => false
            ]
        ];
    }

    /**
     * Procesar alerta
     */
    public function processAlert(string $type, string $title, string $message, array $context = [], string $severity = 'warning'): bool
    {
        $alert = [
            'id' => $this->generateAlertId($type, $context),
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'severity' => $severity,
            'context' => $context,
            'timestamp' => date('Y-m-d H:i:s'),
            'unix_timestamp' => time(),
            'status' => 'active',
            'acknowledgment' => null
        ];

        // Verificar si la alerta ya está en cooldown
        if ($this->isInCooldown($alert)) {
            $this->log('DEBUG', "Alerta en cooldown, no se enviará: {$alert['id']}");
            return false;
        }

        // Registrar alerta
        $this->storeAlert($alert);
        
        // Enviar por todos los canales configurados
        $sent = false;
        
        if ($this->config['channels']['log']) {
            $this->logAlert($alert);
            $sent = true;
        }
        
        if ($this->config['channels']['email'] && $this->emailConfig['enabled']) {
            $emailSent = $this->sendEmailAlert($alert);
            $sent = $sent || $emailSent;
        }
        
        if ($this->config['channels']['dashboard']) {
            $this->storeDashboardAlert($alert);
            $sent = true;
        }
        
        if ($this->config['channels']['webhook']) {
            $webhookSent = $this->sendWebhookAlert($alert);
            $sent = $sent || $webhookSent;
        }

        $this->log('INFO', "Alerta procesada: {$alert['id']}", [
            'severity' => $severity,
            'channels_sent' => $this->getActivatedChannels(),
            'success' => $sent
        ]);

        return $sent;
    }

    /**
     * Generar ID único para alerta
     */
    private function generateAlertId(string $type, array $context): string
    {
        $contextHash = md5(json_encode($context));
        return $type . '_' . substr($contextHash, 0, 8) . '_' . date('YmdH');
    }

    /**
     * Verificar si alerta está en cooldown
     */
    private function isInCooldown(array $alert): bool
    {
        $activeAlerts = $this->getActiveAlerts();
        $cooldownTime = $this->config['cooldown'][$alert['severity']] ?? 900;
        
        foreach ($activeAlerts as $activeAlert) {
            if ($activeAlert['id'] === $alert['id']) {
                $timeDiff = time() - $activeAlert['unix_timestamp'];
                return $timeDiff < $cooldownTime;
            }
        }
        
        return false;
    }

    /**
     * Almacenar alerta
     */
    private function storeAlert(array $alert): void
    {
        $activeAlerts = $this->getActiveAlerts();
        
        // Remover alertas duplicadas o expiradas
        $activeAlerts = array_filter($activeAlerts, function($existingAlert) use ($alert) {
            if ($existingAlert['id'] === $alert['id']) {
                return false; // Remover duplicado
            }
            
            // Remover alertas expiradas (más de 24 horas)
            $timeDiff = time() - $existingAlert['unix_timestamp'];
            return $timeDiff < 86400;
        });
        
        // Agregar nueva alerta
        $activeAlerts[] = $alert;
        
        // Mantener solo las últimas 100 alertas
        if (count($activeAlerts) > 100) {
            usort($activeAlerts, function($a, $b) {
                return $b['unix_timestamp'] - $a['unix_timestamp'];
            });
            $activeAlerts = array_slice($activeAlerts, 0, 100);
        }
        
        file_put_contents($this->alertsFile, json_encode($activeAlerts, JSON_PRETTY_PRINT));
    }

    /**
     * Obtener alertas activas
     */
    public function getActiveAlerts(): array
    {
        if (!file_exists($this->alertsFile)) {
            return [];
        }
        
        $content = file_get_contents($this->alertsFile);
        $alerts = json_decode($content, true);
        
        return is_array($alerts) ? $alerts : [];
    }

    /**
     * Enviar alerta por email
     */
    private function sendEmailAlert(array $alert): bool
    {
        // Simulación de envío de email (en producción usar PHPMailer o similar)
        try {
            $subject = "[Sistema SBL] {$alert['severity']}: {$alert['title']}";
            $body = $this->buildEmailBody($alert);
            
            // En un entorno real, aquí iría la configuración de PHPMailer
            $this->log('INFO', "Email de alerta preparado", [
                'to' => $this->emailConfig['to_emails'],
                'subject' => $subject,
                'alert_id' => $alert['id']
            ]);
            
            // Por ahora, solo simular el envío
            return true;
            
        } catch (Exception $e) {
            $this->log('ERROR', "Error enviando email de alerta", [
                'alert_id' => $alert['id'],
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Construir cuerpo del email
     */
    private function buildEmailBody(array $alert): string
    {
        $severityEmoji = [
            'critical' => '🚨',
            'warning' => '⚠️',
            'info' => 'ℹ️'
        ];

        $emoji = $severityEmoji[$alert['severity']] ?? '📢';
        
        $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .header { background: #f8f9fa; padding: 20px; border-radius: 5px; }
                .alert-critical { border-left: 5px solid #dc3545; }
                .alert-warning { border-left: 5px solid #ffc107; }
                .alert-info { border-left: 5px solid #17a2b8; }
                .details { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; }
                .footer { color: #6c757d; font-size: 0.9em; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='header alert-{$alert['severity']}'>
                <h2>{$emoji} Alerta del Sistema SBL</h2>
                <p><strong>Severidad:</strong> " . ucfirst($alert['severity']) . "</p>
                <p><strong>Fecha:</strong> {$alert['timestamp']}</p>
            </div>
            
            <h3>{$alert['title']}</h3>
            <p>{$alert['message']}</p>
            
            <div class='details'>
                <h4>Detalles Técnicos:</h4>
                <pre>" . json_encode($alert['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>
            </div>
            
            <div class='footer'>
                <p>Este es un mensaje automático del Sistema de Monitoreo SBL.</p>
                <p>ID de Alerta: {$alert['id']}</p>
                <p>Para más información, accede al dashboard: <a href='http://localhost/health_dashboard.php'>Dashboard de Monitoreo</a></p>
            </div>
        </body>
        </html>";
        
        return $body;
    }

    /**
     * Enviar alerta por webhook
     */
    private function sendWebhookAlert(array $alert): bool
    {
        $webhookUrl = $this->config['webhook_url'] ?? null;
        
        if (!$webhookUrl) {
            return false;
        }
        
        try {
            $payload = json_encode([
                'text' => "🚨 Alerta SBL: {$alert['title']}",
                'alert' => $alert,
                'system' => 'SBL Sistema Interno',
                'timestamp' => $alert['timestamp']
            ]);
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $webhookUrl,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $success = $httpCode >= 200 && $httpCode < 300;
            
            $this->log($success ? 'INFO' : 'WARNING', 
                $success ? 'Webhook enviado exitosamente' : 'Error enviando webhook', 
                [
                    'alert_id' => $alert['id'],
                    'http_code' => $httpCode,
                    'response' => $response
                ]
            );
            
            return $success;
            
        } catch (Exception $e) {
            $this->log('ERROR', "Error enviando webhook", [
                'alert_id' => $alert['id'],
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Almacenar alerta para dashboard
     */
    private function storeDashboardAlert(array $alert): void
    {
        // Las alertas ya se almacenan en el archivo activo
        // Esta función puede expandirse para notificaciones en tiempo real
        $this->log('DEBUG', "Alerta almacenada para dashboard", ['alert_id' => $alert['id']]);
    }

    /**
     * Registrar alerta en log
     */
    private function logAlert(array $alert): void
    {
        $logEntry = [
            'timestamp' => $alert['timestamp'],
            'level' => strtoupper($alert['severity']),
            'alert_id' => $alert['id'],
            'type' => $alert['type'],
            'title' => $alert['title'],
            'message' => $alert['message'],
            'context' => $alert['context']
        ];
        
        $logLine = json_encode($logEntry, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
    }

    /**
     * Reconocer alerta (marcar como vista)
     */
    public function acknowledgeAlert(string $alertId, string $acknowledgedBy = 'system'): bool
    {
        $activeAlerts = $this->getActiveAlerts();
        $found = false;
        
        foreach ($activeAlerts as &$alert) {
            if ($alert['id'] === $alertId) {
                $alert['acknowledgment'] = [
                    'acknowledged_by' => $acknowledgedBy,
                    'acknowledged_at' => date('Y-m-d H:i:s'),
                    'acknowledged_timestamp' => time()
                ];
                $alert['status'] = 'acknowledged';
                $found = true;
                break;
            }
        }
        
        if ($found) {
            file_put_contents($this->alertsFile, json_encode($activeAlerts, JSON_PRETTY_PRINT));
            $this->log('INFO', "Alerta reconocida: $alertId", ['acknowledged_by' => $acknowledgedBy]);
        }
        
        return $found;
    }

    /**
     * Obtener estadísticas de alertas
     */
    public function getAlertStats(): array
    {
        $activeAlerts = $this->getActiveAlerts();
        
        $stats = [
            'total' => count($activeAlerts),
            'by_severity' => [
                'critical' => 0,
                'warning' => 0,
                'info' => 0
            ],
            'by_status' => [
                'active' => 0,
                'acknowledged' => 0
            ],
            'latest' => null,
            'oldest_unacknowledged' => null
        ];
        
        foreach ($activeAlerts as $alert) {
            // Contar por severidad
            if (isset($stats['by_severity'][$alert['severity']])) {
                $stats['by_severity'][$alert['severity']]++;
            }
            
            // Contar por estado
            if (isset($stats['by_status'][$alert['status']])) {
                $stats['by_status'][$alert['status']]++;
            }
            
            // Encontrar más reciente
            if (!$stats['latest'] || $alert['unix_timestamp'] > $stats['latest']['unix_timestamp']) {
                $stats['latest'] = $alert;
            }
            
            // Encontrar más antigua sin reconocer
            if ($alert['status'] === 'active') {
                if (!$stats['oldest_unacknowledged'] || $alert['unix_timestamp'] < $stats['oldest_unacknowledged']['unix_timestamp']) {
                    $stats['oldest_unacknowledged'] = $alert;
                }
            }
        }
        
        return $stats;
    }

    /**
     * Limpiar alertas antiguas
     */
    public function cleanupOldAlerts(int $maxAgeDays = 7): int
    {
        $activeAlerts = $this->getActiveAlerts();
        $cutoffTime = time() - ($maxAgeDays * 86400);
        $initialCount = count($activeAlerts);
        
        $activeAlerts = array_filter($activeAlerts, function($alert) use ($cutoffTime) {
            return $alert['unix_timestamp'] > $cutoffTime;
        });
        
        file_put_contents($this->alertsFile, json_encode(array_values($activeAlerts), JSON_PRETTY_PRINT));
        
        $removedCount = $initialCount - count($activeAlerts);
        
        if ($removedCount > 0) {
            $this->log('INFO', "Limpieza de alertas antiguas completada", [
                'removed_count' => $removedCount,
                'remaining_count' => count($activeAlerts),
                'max_age_days' => $maxAgeDays
            ]);
        }
        
        return $removedCount;
    }

    /**
     * Obtener canales activados
     */
    private function getActivatedChannels(): array
    {
        return array_keys(array_filter($this->config['channels']));
    }

    /**
     * Logging interno
     */
    private function log(string $level, string $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        
        $logEntry = "[$timestamp] [$level] [AlertManager] $message";
        if ($contextStr) {
            $logEntry .= " Context: $contextStr";
        }
        $logEntry .= PHP_EOL;

        $generalLogFile = __DIR__ . '/../../../storage/logs/system.log';
        file_put_contents($generalLogFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Asegurar directorios
     */
    private function ensureDirectories(): void
    {
        $directories = [
            dirname($this->logFile),
            dirname($this->alertsFile)
        ];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * Procesar alertas desde datos de monitoreo
     */
    public function processHealthAlerts(array $healthData): void
    {
        // Procesar alertas de base de datos
        if ($healthData['checks']['database']['status'] === 'error') {
            $this->processAlert(
                'database_error',
                'Error Crítico de Base de Datos',
                $healthData['checks']['database']['message'],
                $healthData['checks']['database']['details'],
                'critical'
            );
        }

        // Procesar alertas de rendimiento
        if (isset($healthData['checks']['performance']['details']['memory']['usage_percent'])) {
            $memoryUsage = $healthData['checks']['performance']['details']['memory']['usage_percent'];
            
            if ($memoryUsage > $this->config['thresholds']['critical_memory_usage']) {
                $this->processAlert(
                    'memory_critical',
                    'Uso Crítico de Memoria',
                    "El uso de memoria ha alcanzado {$memoryUsage}%",
                    ['memory_usage' => $memoryUsage, 'threshold' => $this->config['thresholds']['critical_memory_usage']],
                    'critical'
                );
            } elseif ($memoryUsage > $this->config['thresholds']['warning_memory_usage']) {
                $this->processAlert(
                    'memory_warning',
                    'Uso Alto de Memoria',
                    "El uso de memoria ha alcanzado {$memoryUsage}%",
                    ['memory_usage' => $memoryUsage, 'threshold' => $this->config['thresholds']['warning_memory_usage']],
                    'warning'
                );
            }
        }

        // Procesar alertas de disco
        if (isset($healthData['checks']['filesystem']['details']['disk_usage']['used_percent'])) {
            $diskUsage = $healthData['checks']['filesystem']['details']['disk_usage']['used_percent'];
            
            if ($diskUsage > $this->config['thresholds']['critical_disk_usage']) {
                $this->processAlert(
                    'disk_critical',
                    'Espacio en Disco Crítico',
                    "El uso de disco ha alcanzado {$diskUsage}%",
                    ['disk_usage' => $diskUsage, 'threshold' => $this->config['thresholds']['critical_disk_usage']],
                    'critical'
                );
            } elseif ($diskUsage > $this->config['thresholds']['warning_disk_usage']) {
                $this->processAlert(
                    'disk_warning',
                    'Espacio en Disco Bajo',
                    "El uso de disco ha alcanzado {$diskUsage}%",
                    ['disk_usage' => $diskUsage, 'threshold' => $this->config['thresholds']['warning_disk_usage']],
                    'warning'
                );
            }
        }

        // Procesar alertas de APIs
        if (!empty($healthData['checks']['apis']['details']['failed_apis'])) {
            $failedAPIs = $healthData['checks']['apis']['details']['failed_apis'];
            $this->processAlert(
                'apis_failed',
                'APIs No Disponibles',
                'Algunas APIs del sistema no están funcionando: ' . implode(', ', $failedAPIs),
                ['failed_apis' => $failedAPIs],
                'warning'
            );
        }
    }
}