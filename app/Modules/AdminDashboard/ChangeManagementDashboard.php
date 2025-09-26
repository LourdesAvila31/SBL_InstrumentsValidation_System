<?php
/**
 * Panel de Administración del Sistema de Gestión de Cambios
 * 
 * Panel completo para superadministradores y developers para revisar,
 * aprobar y auditar todos los cambios realizados en el sistema.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../Core/developer_permissions.php';
require_once __DIR__ . '/../AuditSystem/AuditManager.php';
require_once __DIR__ . '/../BackupSystem/BackupManager.php';
require_once __DIR__ . '/../IncidentManagement/IncidentManager.php';
require_once __DIR__ . '/../ProjectIntegration/ProjectManagementIntegration.php';
require_once __DIR__ . '/../ConfigurationControl/ConfigurationVersionControl.php';

/**
 * Controlador del panel de administración
 */
class ChangeManagementDashboard
{
    private mysqli $conn;
    private DeveloperSuperadminPermissions $permissions;
    private AuditManager $auditManager;
    private BackupManager $backupManager;
    private IncidentManager $incidentManager;
    private ProjectManagementIntegration $projectIntegration;
    private ConfigurationVersionControl $configControl;
    
    public function __construct()
    {
        global $conn;
        $this->conn = $conn ?? DatabaseManager::getConnection();
        $this->permissions = new DeveloperSuperadminPermissions();
        
        // Verificar permisos
        if (!$this->permissions->isDeveloperSuperadmin()) {
            throw new Exception("Acceso denegado. Se requieren permisos de superadministrador.");
        }
        
        $this->auditManager = new AuditManager();
        $this->backupManager = new BackupManager();
        $this->incidentManager = new IncidentManager();
        $this->projectIntegration = new ProjectManagementIntegration();
        $this->configControl = new ConfigurationVersionControl();
    }

    /**
     * Obtiene datos del dashboard principal
     */
    public function getDashboardData(): array
    {
        return [
            'system_overview' => $this->getSystemOverview(),
            'recent_activity' => $this->getRecentActivity(),
            'critical_alerts' => $this->getCriticalAlerts(),
            'pending_approvals' => $this->getPendingApprovals(),
            'backup_status' => $this->getBackupStatus(),
            'incident_summary' => $this->getIncidentSummary(),
            'configuration_changes' => $this->getRecentConfigurationChanges(),
            'user_activity' => $this->getUserActivitySummary()
        ];
    }

    /**
     * Obtiene resumen general del sistema
     */
    private function getSystemOverview(): array
    {
        $stmt = $this->conn->prepare("
            SELECT 
                (SELECT COUNT(*) FROM audit_logs WHERE DATE(timestamp) = CURDATE()) as today_audit_events,
                (SELECT COUNT(*) FROM incidents WHERE status = 'open') as open_incidents,
                (SELECT COUNT(*) FROM system_alerts WHERE dismissed = 0) as active_alerts,
                (SELECT COUNT(*) FROM backup_logs WHERE status = 'completed' AND DATE(completed_at) = CURDATE()) as today_backups,
                (SELECT COUNT(*) FROM configuration_changes WHERE DATE(created_at) = CURDATE()) as today_config_changes,
                (SELECT COUNT(*) FROM usuarios WHERE estado = 'activo') as active_users
        ");
        
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Obtiene actividad reciente del sistema
     */
    private function getRecentActivity(): array
    {
        $stmt = $this->conn->prepare("
            SELECT 
                'audit' as activity_type,
                CONCAT(u.nombre, ' realizó ', al.action_type, ' en ', al.table_name) as description,
                al.timestamp as occurred_at,
                u.nombre as user_name
            FROM audit_logs al
            JOIN usuarios u ON al.user_id = u.id
            WHERE al.timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            
            UNION ALL
            
            SELECT 
                'incident' as activity_type,
                CONCAT('Incidente creado: ', i.title) as description,
                i.created_at as occurred_at,
                u.nombre as user_name
            FROM incidents i
            JOIN usuarios u ON i.reported_by = u.id
            WHERE i.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            
            UNION ALL
            
            SELECT 
                'backup' as activity_type,
                CONCAT('Backup ', bl.type, ' ', bl.status) as description,
                bl.started_at as occurred_at,
                'Sistema' as user_name
            FROM backup_logs bl
            WHERE bl.started_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            
            UNION ALL
            
            SELECT 
                'config' as activity_type,
                CONCAT('Configuración modificada: ', cc.config_name) as description,
                cc.created_at as occurred_at,
                u.nombre as user_name
            FROM configuration_changes cc
            LEFT JOIN usuarios u ON cc.changed_by = u.id
            WHERE cc.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            
            ORDER BY occurred_at DESC
            LIMIT 50
        ");
        
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Obtiene alertas críticas activas
     */
    private function getCriticalAlerts(): array
    {
        $stmt = $this->conn->prepare("
            SELECT * FROM system_alerts 
            WHERE level = 'critical' AND dismissed = 0 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Obtiene solicitudes pendientes de aprobación
     */
    private function getPendingApprovals(): array
    {
        $stmt = $this->conn->prepare("
            SELECT 
                cr.*,
                u.nombre as requested_by_name
            FROM change_requests cr
            JOIN usuarios u ON cr.requested_by = u.id
            WHERE cr.status = 'submitted'
            ORDER BY cr.priority DESC, cr.created_at ASC
            LIMIT 20
        ");
        
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Obtiene estado de backups
     */
    private function getBackupStatus(): array
    {
        $stmt = $this->conn->prepare("
            SELECT 
                type,
                scope,
                status,
                COUNT(*) as count,
                MAX(completed_at) as last_backup,
                AVG(TIMESTAMPDIFF(MINUTE, started_at, completed_at)) as avg_duration_minutes
            FROM backup_logs 
            WHERE started_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY type, scope, status
            ORDER BY last_backup DESC
        ");
        
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Obtiene resumen de incidentes
     */
    private function getIncidentSummary(): array
    {
        $stmt = $this->conn->prepare("
            SELECT 
                severity,
                status,
                COUNT(*) as count,
                AVG(TIMESTAMPDIFF(HOUR, created_at, COALESCE(resolved_at, NOW()))) as avg_resolution_hours
            FROM incidents 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY severity, status
            ORDER BY 
                FIELD(severity, 'critical', 'high', 'medium', 'low'),
                FIELD(status, 'open', 'in_progress', 'resolved', 'closed')
        ");
        
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Obtiene cambios de configuración recientes
     */
    private function getRecentConfigurationChanges(): array
    {
        $stmt = $this->conn->prepare("
            SELECT 
                cc.*,
                u.nombre as changed_by_name
            FROM configuration_changes cc
            LEFT JOIN usuarios u ON cc.changed_by = u.id
            ORDER BY cc.created_at DESC
            LIMIT 20
        ");
        
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Obtiene resumen de actividad de usuarios
     */
    private function getUserActivitySummary(): array
    {
        $stmt = $this->conn->prepare("
            SELECT 
                u.nombre,
                u.usuario,
                u.rol,
                COUNT(al.id) as total_actions,
                MAX(al.timestamp) as last_activity,
                COUNT(CASE WHEN al.timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as actions_today
            FROM usuarios u
            LEFT JOIN audit_logs al ON u.id = al.user_id
            WHERE u.estado = 'activo'
            GROUP BY u.id, u.nombre, u.usuario, u.rol
            HAVING total_actions > 0
            ORDER BY actions_today DESC, last_activity DESC
            LIMIT 20
        ");
        
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Aprueba una solicitud de cambio
     */
    public function approveChangeRequest(int $requestId, string $approvalNotes = ''): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $userId = $_SESSION['usuario_id'];
        
        $stmt = $this->conn->prepare("
            UPDATE change_requests 
            SET status = 'approved', approved_by = ?, approval_notes = ?, updated_at = NOW()
            WHERE id = ? AND status = 'submitted'
        ");
        
        $stmt->bind_param("isi", $userId, $approvalNotes, $requestId);
        $success = $stmt->execute();
        
        if ($success && $stmt->affected_rows > 0) {
            // Registrar en auditoría
            $this->auditManager->logChange(
                $userId,
                'APPROVE',
                'change_requests',
                $requestId,
                ['status' => 'submitted'],
                ['status' => 'approved', 'approved_by' => $userId],
                "Solicitud de cambio aprobada: {$approvalNotes}"
            );
            
            return ['success' => true, 'message' => 'Solicitud de cambio aprobada exitosamente'];
        }
        
        return ['success' => false, 'message' => 'Error al aprobar la solicitud de cambio'];
    }

    /**
     * Rechaza una solicitud de cambio
     */
    public function rejectChangeRequest(int $requestId, string $rejectionReason): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $userId = $_SESSION['usuario_id'];
        
        $stmt = $this->conn->prepare("
            UPDATE change_requests 
            SET status = 'rejected', approved_by = ?, approval_notes = ?, updated_at = NOW()
            WHERE id = ? AND status = 'submitted'
        ");
        
        $stmt->bind_param("isi", $userId, $rejectionReason, $requestId);
        $success = $stmt->execute();
        
        if ($success && $stmt->affected_rows > 0) {
            // Registrar en auditoría
            $this->auditManager->logChange(
                $userId,
                'REJECT',
                'change_requests',
                $requestId,
                ['status' => 'submitted'],
                ['status' => 'rejected', 'approved_by' => $userId],
                "Solicitud de cambio rechazada: {$rejectionReason}"
            );
            
            return ['success' => true, 'message' => 'Solicitud de cambio rechazada'];
        }
        
        return ['success' => false, 'message' => 'Error al rechazar la solicitud de cambio'];
    }

    /**
     * Ejecuta backup manual
     */
    public function executeManualBackup(string $type = 'hot'): array
    {
        try {
            $result = $this->backupManager->performFullBackup($type);
            
            // Registrar en auditoría
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $userId = $_SESSION['usuario_id'] ?? null;
            if ($userId) {
                $this->auditManager->logChange(
                    $userId,
                    'CREATE',
                    'backup_logs',
                    $result['backup_id'],
                    [],
                    ['type' => $type, 'backup_id' => $result['backup_id']],
                    "Backup manual ejecutado"
                );
            }
            
            return $result;
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Genera reporte completo del sistema
     */
    public function generateSystemReport(string $dateFrom, string $dateTo): array
    {
        $report = [
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'audit_summary' => $this->auditManager->generateAuditReport($dateFrom, $dateTo),
            'incident_metrics' => $this->getIncidentMetrics($dateFrom, $dateTo),
            'backup_metrics' => $this->getBackupMetrics($dateFrom, $dateTo),
            'configuration_changes' => $this->getConfigurationChangeMetrics($dateFrom, $dateTo),
            'user_productivity' => $this->getUserProductivityMetrics($dateFrom, $dateTo),
            'system_health' => $this->getSystemHealthMetrics($dateFrom, $dateTo)
        ];
        
        // Registrar generación del reporte
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $userId = $_SESSION['usuario_id'] ?? null;
        if ($userId) {
            $this->auditManager->logChange(
                $userId,
                'EXPORT',
                'system_reports',
                null,
                [],
                ['period' => "{$dateFrom} to {$dateTo}"],
                "Reporte del sistema generado"
            );
        }
        
        return $report;
    }

    /**
     * Obtiene métricas de incidentes para el período
     */
    private function getIncidentMetrics(string $dateFrom, string $dateTo): array
    {
        $stmt = $this->conn->prepare("
            SELECT 
                severity,
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved,
                AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_resolution_hours,
                MIN(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as min_resolution_hours,
                MAX(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as max_resolution_hours
            FROM incidents 
            WHERE created_at BETWEEN ? AND ?
            GROUP BY severity
        ");
        
        $stmt->bind_param("ss", $dateFrom, $dateTo);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Obtiene métricas de backup para el período
     */
    private function getBackupMetrics(string $dateFrom, string $dateTo): array
    {
        $stmt = $this->conn->prepare("
            SELECT 
                type,
                scope,
                COUNT(*) as total_attempts,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed,
                AVG(file_size) as avg_size_bytes,
                AVG(TIMESTAMPDIFF(MINUTE, started_at, completed_at)) as avg_duration_minutes
            FROM backup_logs 
            WHERE started_at BETWEEN ? AND ?
            GROUP BY type, scope
        ");
        
        $stmt->bind_param("ss", $dateFrom, $dateTo);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Obtiene métricas de cambios de configuración
     */
    private function getConfigurationChangeMetrics(string $dateFrom, string $dateTo): array
    {
        $stmt = $this->conn->prepare("
            SELECT 
                config_name,
                COUNT(*) as total_changes,
                COUNT(CASE WHEN is_revert = 1 THEN 1 END) as reverts,
                u.nombre as most_active_user,
                MAX(cc.created_at) as last_change
            FROM configuration_changes cc
            LEFT JOIN usuarios u ON cc.changed_by = u.id
            WHERE cc.created_at BETWEEN ? AND ?
            GROUP BY config_name, u.id, u.nombre
            ORDER BY total_changes DESC
        ");
        
        $stmt->bind_param("ss", $dateFrom, $dateTo);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Obtiene métricas de productividad de usuarios
     */
    private function getUserProductivityMetrics(string $dateFrom, string $dateTo): array
    {
        $stmt = $this->conn->prepare("
            SELECT 
                u.nombre,
                u.rol,
                COUNT(al.id) as total_actions,
                COUNT(DISTINCT DATE(al.timestamp)) as active_days,
                COUNT(CASE WHEN al.action_type = 'CREATE' THEN 1 END) as creates,
                COUNT(CASE WHEN al.action_type = 'UPDATE' THEN 1 END) as updates,
                COUNT(CASE WHEN al.action_type = 'DELETE' THEN 1 END) as deletes
            FROM usuarios u
            LEFT JOIN audit_logs al ON u.id = al.user_id AND al.timestamp BETWEEN ? AND ?
            WHERE u.estado = 'activo'
            GROUP BY u.id, u.nombre, u.rol
            HAVING total_actions > 0
            ORDER BY total_actions DESC
        ");
        
        $stmt->bind_param("ss", $dateFrom, $dateTo);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Obtiene métricas de salud del sistema
     */
    private function getSystemHealthMetrics(string $dateFrom, string $dateTo): array
    {
        $stmt = $this->conn->prepare("
            SELECT 
                metric_name,
                AVG(metric_value) as avg_value,
                MIN(metric_value) as min_value,
                MAX(metric_value) as max_value,
                COUNT(*) as data_points
            FROM system_metrics 
            WHERE recorded_at BETWEEN ? AND ?
            GROUP BY metric_name
            ORDER BY metric_name
        ");
        
        $stmt->bind_param("ss", $dateFrom, $dateTo);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Exporta reporte a diferentes formatos
     */
    public function exportReport(array $reportData, string $format = 'json'): string
    {
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "system_report_{$timestamp}";
        
        switch ($format) {
            case 'json':
                $filename .= '.json';
                $content = json_encode($reportData, JSON_PRETTY_PRINT);
                break;
                
            case 'csv':
                $filename .= '.csv';
                $content = $this->convertReportToCSV($reportData);
                break;
                
            case 'html':
                $filename .= '.html';
                $content = $this->convertReportToHTML($reportData);
                break;
                
            default:
                throw new Exception("Formato de exportación no soportado: {$format}");
        }
        
        $exportPath = dirname(__DIR__, 3) . "/storage/reports/{$filename}";
        
        if (!is_dir(dirname($exportPath))) {
            mkdir(dirname($exportPath), 0755, true);
        }
        
        file_put_contents($exportPath, $content);
        
        return $exportPath;
    }

    /**
     * Convierte reporte a formato CSV
     */
    private function convertReportToCSV(array $reportData): string
    {
        $csv = "Reporte del Sistema\n";
        $csv .= "Período: {$reportData['period']['from']} - {$reportData['period']['to']}\n\n";
        
        foreach ($reportData as $section => $data) {
            if ($section === 'period') continue;
            
            $csv .= strtoupper(str_replace('_', ' ', $section)) . "\n";
            
            if (is_array($data) && !empty($data)) {
                $headers = array_keys($data[0]);
                $csv .= implode(',', $headers) . "\n";
                
                foreach ($data as $row) {
                    $csv .= implode(',', array_map(function($value) {
                        return is_string($value) ? '"' . str_replace('"', '""', $value) . '"' : $value;
                    }, $row)) . "\n";
                }
            }
            
            $csv .= "\n";
        }
        
        return $csv;
    }

    /**
     * Convierte reporte a formato HTML
     */
    private function convertReportToHTML(array $reportData): string
    {
        $html = "<!DOCTYPE html>\n<html>\n<head>\n";
        $html .= "<title>Reporte del Sistema</title>\n";
        $html .= "<style>table { border-collapse: collapse; width: 100%; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background-color: #f2f2f2; }</style>\n";
        $html .= "</head>\n<body>\n";
        $html .= "<h1>Reporte del Sistema</h1>\n";
        $html .= "<p><strong>Período:</strong> {$reportData['period']['from']} - {$reportData['period']['to']}</p>\n";
        
        foreach ($reportData as $section => $data) {
            if ($section === 'period') continue;
            
            $html .= "<h2>" . ucwords(str_replace('_', ' ', $section)) . "</h2>\n";
            
            if (is_array($data) && !empty($data)) {
                $html .= "<table>\n<thead>\n<tr>\n";
                
                $headers = array_keys($data[0]);
                foreach ($headers as $header) {
                    $html .= "<th>" . ucwords(str_replace('_', ' ', $header)) . "</th>\n";
                }
                
                $html .= "</tr>\n</thead>\n<tbody>\n";
                
                foreach ($data as $row) {
                    $html .= "<tr>\n";
                    foreach ($row as $value) {
                        $html .= "<td>" . htmlspecialchars($value ?? '') . "</td>\n";
                    }
                    $html .= "</tr>\n";
                }
                
                $html .= "</tbody>\n</table>\n";
            }
            
            $html .= "\n";
        }
        
        $html .= "</body>\n</html>";
        
        return $html;
    }
}