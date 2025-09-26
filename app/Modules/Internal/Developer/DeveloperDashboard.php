<?php
declare(strict_types=1);

require_once __DIR__ . '/DeveloperAuth.php';

/**
 * Dashboard de monitoreo en tiempo real para desarrolladores
 * Proporciona KPIs, métricas del sistema y visualización de datos críticos
 */
class DeveloperDashboard
{
    private mysqli $conn;
    private DeveloperAuth $auth;
    
    public function __construct(?mysqli $connection = null)
    {
        global $conn;
        $this->conn = $connection ?? $conn ?? DatabaseManager::getConnection();
        $this->auth = new DeveloperAuth($this->conn);
    }

    /**
     * Obtiene KPIs principales del sistema
     */
    public function getSystemKPIs(): array
    {
        if (!$this->auth->hasPrivateAccess('dashboard')) {
            throw new Exception('Acceso denegado al dashboard');
        }

        $kpis = [
            'incidents' => $this->getIncidentsKPI(),
            'performance' => $this->getPerformanceKPI(),
            'security' => $this->getSecurityKPI(),
            'compliance' => $this->getComplianceKPI(),
            'users' => $this->getUsersKPI()
        ];

        $this->auth->logDeveloperActivity('dashboard_access', ['kpis_requested' => array_keys($kpis)]);

        return $kpis;
    }

    /**
     * KPIs de incidencias
     */
    private function getIncidentsKPI(): array
    {
        // Incidencias abiertas
        $stmt = $this->conn->prepare(
            'SELECT COUNT(*) as total_open FROM incidents WHERE status IN ("open", "in_progress") AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
        );
        $stmt->execute();
        $openIncidents = $stmt->get_result()->fetch_assoc()['total_open'] ?? 0;
        $stmt->close();

        // Incidencias críticas
        $stmt = $this->conn->prepare(
            'SELECT COUNT(*) as total_critical FROM incidents WHERE severity = "critical" AND status != "closed" AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'
        );
        $stmt->execute();
        $criticalIncidents = $stmt->get_result()->fetch_assoc()['total_critical'] ?? 0;
        $stmt->close();

        // Tiempo promedio de resolución
        $stmt = $this->conn->prepare(
            'SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_resolution_hours 
             FROM incidents 
             WHERE status = "closed" AND resolved_at IS NOT NULL 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
        );
        $stmt->execute();
        $avgResolution = $stmt->get_result()->fetch_assoc()['avg_resolution_hours'] ?? 0;
        $stmt->close();

        return [
            'open_incidents' => (int)$openIncidents,
            'critical_incidents' => (int)$criticalIncidents,
            'avg_resolution_hours' => round((float)$avgResolution, 2),
            'status' => $criticalIncidents > 0 ? 'warning' : ($openIncidents > 10 ? 'attention' : 'good'),
            'trend' => $this->calculateIncidentTrend()
        ];
    }

    /**
     * KPIs de rendimiento del sistema
     */
    private function getPerformanceKPI(): array
    {
        // Disponibilidad del sistema (uptime)
        $stmt = $this->conn->prepare(
            'SELECT 
                COUNT(CASE WHEN status = "up" THEN 1 END) * 100.0 / COUNT(*) as uptime_percentage
             FROM system_health_checks 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)'
        );
        $stmt->execute();
        $uptime = $stmt->get_result()->fetch_assoc()['uptime_percentage'] ?? 100;
        $stmt->close();

        // Tiempo de respuesta promedio
        $stmt = $this->conn->prepare(
            'SELECT AVG(response_time_ms) as avg_response_time 
             FROM api_performance_logs 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)'
        );
        $stmt->execute();
        $avgResponseTime = $stmt->get_result()->fetch_assoc()['avg_response_time'] ?? 0;
        $stmt->close();

        // Uso de recursos
        $stmt = $this->conn->prepare(
            'SELECT cpu_usage, memory_usage, disk_usage 
             FROM system_resources 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) 
             ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute();
        $resources = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return [
            'uptime_percentage' => round((float)$uptime, 2),
            'avg_response_time_ms' => round((float)$avgResponseTime, 2),
            'cpu_usage' => $resources['cpu_usage'] ?? 0,
            'memory_usage' => $resources['memory_usage'] ?? 0,
            'disk_usage' => $resources['disk_usage'] ?? 0,
            'status' => $uptime < 99 ? 'critical' : ($uptime < 99.5 ? 'warning' : 'good')
        ];
    }

    /**
     * KPIs de seguridad
     */
    private function getSecurityKPI(): array
    {
        // Intentos de login fallidos
        $stmt = $this->conn->prepare(
            'SELECT COUNT(*) as failed_logins 
             FROM auditoria_logs 
             WHERE accion = "login_failed" AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)'
        );
        $stmt->execute();
        $failedLogins = $stmt->get_result()->fetch_assoc()['failed_logins'] ?? 0;
        $stmt->close();

        // Sesiones activas
        $stmt = $this->conn->prepare(
            'SELECT COUNT(DISTINCT usuario_id) as active_sessions 
             FROM user_sessions 
             WHERE last_activity >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)'
        );
        $stmt->execute();
        $activeSessions = $stmt->get_result()->fetch_assoc()['active_sessions'] ?? 0;
        $stmt->close();

        // Alertas de seguridad
        $stmt = $this->conn->prepare(
            'SELECT COUNT(*) as security_alerts 
             FROM security_events 
             WHERE severity IN ("high", "critical") AND status = "open" 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)'
        );
        $stmt->execute();
        $securityAlerts = $stmt->get_result()->fetch_assoc()['security_alerts'] ?? 0;
        $stmt->close();

        return [
            'failed_logins' => (int)$failedLogins,
            'active_sessions' => (int)$activeSessions,
            'security_alerts' => (int)$securityAlerts,
            'status' => $securityAlerts > 0 ? 'critical' : ($failedLogins > 50 ? 'warning' : 'good'),
            'risk_level' => $this->calculateSecurityRiskLevel($failedLogins, $securityAlerts)
        ];
    }

    /**
     * KPIs de cumplimiento (compliance)
     */
    private function getComplianceKPI(): array
    {
        // SOPs vencidos o próximos a vencer
        $stmt = $this->conn->prepare(
            'SELECT COUNT(*) as expired_sops 
             FROM sop_documents 
             WHERE expiry_date <= DATE_ADD(NOW(), INTERVAL 30 DAY) AND status = "active"'
        );
        $stmt->execute();
        $expiredSOPs = $stmt->get_result()->fetch_assoc()['expired_sops'] ?? 0;
        $stmt->close();

        // Auditorías pendientes
        $stmt = $this->conn->prepare(
            'SELECT COUNT(*) as pending_audits 
             FROM compliance_audits 
             WHERE status = "pending" AND due_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)'
        );
        $stmt->execute();
        $pendingAudits = $stmt->get_result()->fetch_assoc()['pending_audits'] ?? 0;
        $stmt->close();

        // Certificaciones próximas a vencer
        $stmt = $this->conn->prepare(
            'SELECT COUNT(*) as expiring_certifications 
             FROM certifications 
             WHERE expiry_date <= DATE_ADD(NOW(), INTERVAL 60 DAY) AND status = "active"'
        );
        $stmt->execute();
        $expiringCerts = $stmt->get_result()->fetch_assoc()['expiring_certifications'] ?? 0;
        $stmt->close();

        return [
            'expired_sops' => (int)$expiredSOPs,
            'pending_audits' => (int)$pendingAudits,
            'expiring_certifications' => (int)$expiringCerts,
            'compliance_score' => $this->calculateComplianceScore(),
            'status' => ($expiredSOPs > 0 || $pendingAudits > 0) ? 'warning' : 'good'
        ];
    }

    /**
     * KPIs de usuarios
     */
    private function getUsersKPI(): array
    {
        // Usuarios activos
        $stmt = $this->conn->prepare(
            'SELECT COUNT(DISTINCT u.id) as active_users 
             FROM usuarios u 
             JOIN user_sessions s ON s.usuario_id = u.id 
             WHERE s.last_activity >= DATE_SUB(NOW(), INTERVAL 24 HOUR)'
        );
        $stmt->execute();
        $activeUsers = $stmt->get_result()->fetch_assoc()['active_users'] ?? 0;
        $stmt->close();

        // Nuevos usuarios (último mes)
        $stmt = $this->conn->prepare(
            'SELECT COUNT(*) as new_users 
             FROM usuarios 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
        );
        $stmt->execute();
        $newUsers = $stmt->get_result()->fetch_assoc()['new_users'] ?? 0;
        $stmt->close();

        // Usuarios por rol
        $stmt = $this->conn->prepare(
            'SELECT r.nombre as role_name, COUNT(u.id) as user_count 
             FROM usuarios u 
             JOIN roles r ON r.id = u.role_id 
             WHERE u.activo = 1 
             GROUP BY r.id, r.nombre'
        );
        $stmt->execute();
        $result = $stmt->get_result();
        $usersByRole = [];
        while ($row = $result->fetch_assoc()) {
            $usersByRole[$row['role_name']] = (int)$row['user_count'];
        }
        $stmt->close();

        return [
            'active_users' => (int)$activeUsers,
            'new_users_month' => (int)$newUsers,
            'users_by_role' => $usersByRole,
            'growth_rate' => $this->calculateUserGrowthRate()
        ];
    }

    /**
     * Obtiene datos para gráficos en tiempo real
     */
    public function getRealTimeChartData(): array
    {
        if (!$this->auth->hasPrivateAccess('monitoring')) {
            throw new Exception('Acceso denegado al monitoreo');
        }

        return [
            'performance_trend' => $this->getPerformanceTrend(),
            'incident_timeline' => $this->getIncidentTimeline(),
            'user_activity' => $this->getUserActivityChart(),
            'system_health' => $this->getSystemHealthChart()
        ];
    }

    /**
     * Funciones auxiliares para cálculos
     */
    private function calculateIncidentTrend(): string
    {
        $stmt = $this->conn->prepare(
            'SELECT 
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as this_week,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as last_week
             FROM incidents'
        );
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $thisWeek = (int)$result['this_week'];
        $lastWeek = (int)$result['last_week'];

        if ($lastWeek === 0) return 'stable';
        
        $change = (($thisWeek - $lastWeek) / $lastWeek) * 100;
        
        if ($change > 10) return 'increasing';
        if ($change < -10) return 'decreasing';
        return 'stable';
    }

    private function calculateSecurityRiskLevel(int $failedLogins, int $alerts): string
    {
        if ($alerts > 0 || $failedLogins > 100) return 'high';
        if ($failedLogins > 50) return 'medium';
        return 'low';
    }

    private function calculateComplianceScore(): float
    {
        // Implementar lógica de cálculo de score de cumplimiento
        // Basado en SOPs actualizados, auditorías completadas, certificaciones vigentes
        return 95.5; // Placeholder
    }

    private function calculateUserGrowthRate(): float
    {
        // Implementar cálculo de tasa de crecimiento de usuarios
        return 2.3; // Placeholder - porcentaje mensual
    }

    private function getPerformanceTrend(): array
    {
        // Datos de rendimiento de las últimas 24 horas
        $stmt = $this->conn->prepare(
            'SELECT 
                DATE_FORMAT(created_at, "%H:%i") as time_label,
                AVG(response_time_ms) as avg_response_time,
                AVG(cpu_usage) as avg_cpu,
                AVG(memory_usage) as avg_memory
             FROM api_performance_logs 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             GROUP BY DATE_FORMAT(created_at, "%Y-%m-%d %H")
             ORDER BY created_at ASC'
        );
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'time' => $row['time_label'],
                'response_time' => round((float)$row['avg_response_time'], 2),
                'cpu' => round((float)$row['avg_cpu'], 1),
                'memory' => round((float)$row['avg_memory'], 1)
            ];
        }
        $stmt->close();

        return $data;
    }

    private function getIncidentTimeline(): array
    {
        $stmt = $this->conn->prepare(
            'SELECT 
                DATE_FORMAT(created_at, "%Y-%m-%d") as date_label,
                COUNT(*) as incident_count,
                COUNT(CASE WHEN severity = "critical" THEN 1 END) as critical_count
             FROM incidents 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE_FORMAT(created_at, "%Y-%m-%d")
             ORDER BY created_at ASC'
        );
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'date' => $row['date_label'],
                'total' => (int)$row['incident_count'],
                'critical' => (int)$row['critical_count']
            ];
        }
        $stmt->close();

        return $data;
    }

    private function getUserActivityChart(): array
    {
        $stmt = $this->conn->prepare(
            'SELECT 
                DATE_FORMAT(last_activity, "%H:00") as hour_label,
                COUNT(DISTINCT usuario_id) as active_users
             FROM user_sessions 
             WHERE last_activity >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             GROUP BY DATE_FORMAT(last_activity, "%H")
             ORDER BY hour_label ASC'
        );
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'hour' => $row['hour_label'],
                'users' => (int)$row['active_users']
            ];
        }
        $stmt->close();

        return $data;
    }

    private function getSystemHealthChart(): array
    {
        $stmt = $this->conn->prepare(
            'SELECT 
                created_at,
                cpu_usage,
                memory_usage,
                disk_usage,
                network_io
             FROM system_resources 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 4 HOUR)
             ORDER BY created_at ASC'
        );
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'timestamp' => $row['created_at'],
                'cpu' => (float)$row['cpu_usage'],
                'memory' => (float)$row['memory_usage'],
                'disk' => (float)$row['disk_usage'],
                'network' => (float)$row['network_io'] ?? 0
            ];
        }
        $stmt->close();

        return $data;
    }
}