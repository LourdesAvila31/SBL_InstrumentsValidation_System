<?php
/**
 * Sistema de Reportes y Análisis para Tickets
 * Conforme a GAMP 5 y normativas GxP
 * 
 * Genera reportes detallados para auditorías y análisis de tendencias  
 * del sistema de gestión de tickets.
 */

require_once dirname(__DIR__, 2) . '/Core/db.php';
require_once __DIR__ . '/TicketManager.php';

class TicketReportingSystem {
    private $conn;
    private $ticketManager;
    
    public function __construct() {
        $this->conn = DatabaseManager::getConnection();
        $this->ticketManager = new TicketManager();
    }
    
    /**
     * Genera reporte ejecutivo de tickets
     */
    public function generateExecutiveReport($dateFrom = null, $dateTo = null) {
        $dateFrom = $dateFrom ?: date('Y-m-01'); // Primer día del mes actual
        $dateTo = $dateTo ?: date('Y-m-t'); // Último día del mes actual
        
        $report = [
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'summary' => $this->getExecutiveSummary($dateFrom, $dateTo),
            'risk_analysis' => $this->getRiskAnalysis($dateFrom, $dateTo),
            'performance_metrics' => $this->getPerformanceMetrics($dateFrom, $dateTo),
            'trend_analysis' => $this->getTrendAnalysis($dateFrom, $dateTo),
            'compliance_status' => $this->getComplianceStatus($dateFrom, $dateTo),
            'recommendations' => $this->generateRecommendations($dateFrom, $dateTo)
        ];
        
        return $report;
    }
    
    /**
     * Obtiene resumen ejecutivo
     */
    private function getExecutiveSummary($dateFrom, $dateTo) {
        $sql = "SELECT 
                    COUNT(*) as total_tickets,
                    COUNT(CASE WHEN status = 'Resuelto' THEN 1 END) as resolved_tickets,
                    COUNT(CASE WHEN status = 'Cerrado' THEN 1 END) as closed_tickets,
                    COUNT(CASE WHEN risk_level = 'Crítico' THEN 1 END) as critical_tickets,
                    COUNT(CASE WHEN risk_level = 'Alto' THEN 1 END) as high_risk_tickets,
                    COUNT(CASE WHEN regulatory_impact = TRUE THEN 1 END) as regulatory_impact_tickets,
                    COUNT(CASE WHEN validation_impact = TRUE THEN 1 END) as validation_impact_tickets,
                    AVG(actual_resolution_time) as avg_resolution_time,
                    MIN(created_at) as first_ticket_date,
                    MAX(created_at) as last_ticket_date
                FROM tickets 
                WHERE created_at BETWEEN ? AND ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $dateFrom, $dateTo);
        $stmt->execute();
        $summary = $stmt->get_result()->fetch_assoc();
        
        // Calcular KPIs adicionales
        $summary['resolution_rate'] = $summary['total_tickets'] > 0 ? 
            round(($summary['resolved_tickets'] / $summary['total_tickets']) * 100, 2) : 0;
        
        $summary['critical_ratio'] = $summary['total_tickets'] > 0 ? 
            round(($summary['critical_tickets'] / $summary['total_tickets']) * 100, 2) : 0;
        
        return $summary;
    }
    
    /**
     * Análisis de riesgo
     */
    private function getRiskAnalysis($dateFrom, $dateTo) {
        // Distribución por nivel de riesgo
        $riskDistributionSql = "SELECT risk_level, COUNT(*) as count,
                                       ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM tickets WHERE created_at BETWEEN ? AND ?)), 2) as percentage
                                FROM tickets 
                                WHERE created_at BETWEEN ? AND ?
                                GROUP BY risk_level
                                ORDER BY FIELD(risk_level, 'Crítico', 'Alto', 'Medio', 'Bajo', 'Mínimo')";
        
        $stmt = $this->conn->prepare($riskDistributionSql);
        $stmt->bind_param("ssss", $dateFrom, $dateTo, $dateFrom, $dateTo);
        $stmt->execute();
        $riskDistribution = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Análisis por categoría de riesgo
        $categoryRiskSql = "SELECT category, risk_level, COUNT(*) as count
                           FROM tickets 
                           WHERE created_at BETWEEN ? AND ?
                           GROUP BY category, risk_level
                           ORDER BY category, FIELD(risk_level, 'Crítico', 'Alto', 'Medio', 'Bajo', 'Mínimo')";
        
        $stmt = $this->conn->prepare($categoryRiskSql);
        $stmt->bind_param("ss", $dateFrom, $dateTo);
        $stmt->execute();
        $categoryRisk = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Tiempo de resolución por riesgo
        $resolutionByRiskSql = "SELECT risk_level, 
                                       AVG(actual_resolution_time) as avg_resolution_time,
                                       MIN(actual_resolution_time) as min_resolution_time,
                                       MAX(actual_resolution_time) as max_resolution_time,
                                       COUNT(CASE WHEN actual_resolution_time <= estimated_resolution_time THEN 1 END) as within_sla,
                                       COUNT(*) as total_resolved
                                FROM tickets 
                                WHERE created_at BETWEEN ? AND ? AND status IN ('Resuelto', 'Cerrado')
                                GROUP BY risk_level";
        
        $stmt = $this->conn->prepare($resolutionByRiskSql);
        $stmt->bind_param("ss", $dateFrom, $dateTo);
        $stmt->execute();
        $resolutionByRisk = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        return [
            'distribution' => $riskDistribution,
            'by_category' => $categoryRisk,
            'resolution_times' => $resolutionByRisk
        ];
    }
    
    /**
     * Métricas de rendimiento
     */
    private function getPerformanceMetrics($dateFrom, $dateTo) {
        // SLA compliance
        $slaComplianceSql = "SELECT 
                                COUNT(*) as total_resolved,
                                COUNT(CASE WHEN actual_resolution_time <= estimated_resolution_time THEN 1 END) as within_sla,
                                AVG(actual_resolution_time) as avg_actual_time,
                                AVG(estimated_resolution_time) as avg_estimated_time
                             FROM tickets 
                             WHERE created_at BETWEEN ? AND ? AND status IN ('Resuelto', 'Cerrado')";
        
        $stmt = $this->conn->prepare($slaComplianceSql);
        $stmt->bind_param("ss", $dateFrom, $dateTo);
        $stmt->execute();
        $slaMetrics = $stmt->get_result()->fetch_assoc();
        
        $slaMetrics['compliance_rate'] = $slaMetrics['total_resolved'] > 0 ? 
            round(($slaMetrics['within_sla'] / $slaMetrics['total_resolved']) * 100, 2) : 0;
        
        // Análisis de escalaciones
        $escalationsSql = "SELECT 
                              COUNT(*) as total_escalated,
                              AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_time_to_escalation
                           FROM tickets 
                           WHERE created_at BETWEEN ? AND ? AND status = 'Escalado'";
        
        $stmt = $this->conn->prepare($escalationsSql);
        $stmt->bind_param("ss", $dateFrom, $dateTo);
        $stmt->execute();
        $escalationMetrics = $stmt->get_result()->fetch_assoc();
        
        // Análisis de reopenings (reaperturas)
        $reopeningSql = "SELECT COUNT(DISTINCT th.ticket_id) as tickets_reopened
                        FROM ticket_history th
                        JOIN tickets t ON th.ticket_id = t.id
                        WHERE th.action_type = 'REOPENED' 
                        AND t.created_at BETWEEN ? AND ?";
        
        $stmt = $this->conn->prepare($reopeningSql);
        $stmt->bind_param("ss", $dateFrom, $dateTo);
        $stmt->execute();
        $reopeningMetrics = $stmt->get_result()->fetch_assoc();
        
        return [
            'sla_compliance' => $slaMetrics,
            'escalations' => $escalationMetrics,
            'reopenings' => $reopeningMetrics
        ];
    }
    
    /**
     * Análisis de tendencias
     */
    private function getTrendAnalysis($dateFrom, $dateTo) {
        // Tendencia de creación de tickets por semana
        $weeklyTrendSql = "SELECT 
                              YEARWEEK(created_at) as week,
                              COUNT(*) as tickets_created,
                              COUNT(CASE WHEN risk_level IN ('Crítico', 'Alto') THEN 1 END) as high_risk_tickets
                           FROM tickets 
                           WHERE created_at BETWEEN ? AND ?
                           GROUP BY YEARWEEK(created_at)
                           ORDER BY week";
        
        $stmt = $this->conn->prepare($weeklyTrendSql);
        $stmt->bind_param("ss", $dateFrom, $dateTo);
        $stmt->execute();
        $weeklyTrend = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Tendencia de resolución
        $resolutionTrendSql = "SELECT 
                                  YEARWEEK(resolved_at) as week,
                                  COUNT(*) as tickets_resolved,
                                  AVG(actual_resolution_time) as avg_resolution_time
                               FROM tickets 
                               WHERE resolved_at BETWEEN ? AND ?
                               GROUP BY YEARWEEK(resolved_at)
                               ORDER BY week";
        
        $stmt = $this->conn->prepare($resolutionTrendSql);
        $stmt->bind_param("ss", $dateFrom, $dateTo);
        $stmt->execute();
        $resolutionTrend = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Top categorías problemáticas
        $topCategoriesSql = "SELECT 
                                category,
                                COUNT(*) as ticket_count,
                                COUNT(CASE WHEN risk_level IN ('Crítico', 'Alto') THEN 1 END) as high_risk_count,
                                AVG(actual_resolution_time) as avg_resolution_time
                             FROM tickets 
                             WHERE created_at BETWEEN ? AND ?
                             GROUP BY category
                             ORDER BY high_risk_count DESC, ticket_count DESC
                             LIMIT 10";
        
        $stmt = $this->conn->prepare($topCategoriesSql);
        $stmt->bind_param("ss", $dateFrom, $dateTo);
        $stmt->execute();
        $topCategories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        return [
            'weekly_creation' => $weeklyTrend,
            'weekly_resolution' => $resolutionTrend,
            'top_categories' => $topCategories
        ];
    }
    
    /**
     * Estado de cumplimiento GxP
     */
    private function getComplianceStatus($dateFrom, $dateTo) {
        // Tickets con impacto regulatorio
        $regulatorySql = "SELECT 
                             gxp_classification,
                             COUNT(*) as count,
                             COUNT(CASE WHEN status IN ('Resuelto', 'Cerrado') THEN 1 END) as resolved_count,
                             AVG(actual_resolution_time) as avg_resolution_time
                          FROM tickets 
                          WHERE created_at BETWEEN ? AND ? 
                          AND (regulatory_impact = TRUE OR validation_impact = TRUE)
                          GROUP BY gxp_classification";
        
        $stmt = $this->conn->prepare($regulatorySql);
        $stmt->bind_param("ss", $dateFrom, $dateTo);
        $stmt->execute();
        $regulatoryCompliance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Documentación de resoluciones
        $documentationSql = "SELECT 
                                COUNT(*) as total_resolved,
                                COUNT(CASE WHEN root_cause IS NOT NULL AND root_cause != '' THEN 1 END) as with_root_cause,
                                COUNT(CASE WHEN corrective_actions IS NOT NULL AND corrective_actions != '' THEN 1 END) as with_corrective_actions,
                                COUNT(CASE WHEN preventive_actions IS NOT NULL AND preventive_actions != '' THEN 1 END) as with_preventive_actions
                             FROM tickets 
                             WHERE created_at BETWEEN ? AND ? 
                             AND status IN ('Resuelto', 'Cerrado')";
        
        $stmt = $this->conn->prepare($documentationSql);
        $stmt->bind_param("ss", $dateFrom, $dateTo);
        $stmt->execute();
        $documentationStats = $stmt->get_result()->fetch_assoc();
        
        // Calcular porcentajes de documentación
        if ($documentationStats['total_resolved'] > 0) {
            $documentationStats['root_cause_percentage'] = round(($documentationStats['with_root_cause'] / $documentationStats['total_resolved']) * 100, 2);
            $documentationStats['corrective_actions_percentage'] = round(($documentationStats['with_corrective_actions'] / $documentationStats['total_resolved']) * 100, 2);
            $documentationStats['preventive_actions_percentage'] = round(($documentationStats['with_preventive_actions'] / $documentationStats['total_resolved']) * 100, 2);
        }
        
        return [
            'regulatory_compliance' => $regulatoryCompliance,
            'documentation_completeness' => $documentationStats
        ];
    }
    
    /**
     * Genera recomendaciones basadas en análisis
     */
    private function generateRecommendations($dateFrom, $dateTo) {
        $recommendations = [];
        
        // Obtener métricas clave para recomendaciones
        $summary = $this->getExecutiveSummary($dateFrom, $dateTo);
        $performance = $this->getPerformanceMetrics($dateFrom, $dateTo);
        $compliance = $this->getComplianceStatus($dateFrom, $dateTo);
        
        // Recomendaciones basadas en ratio de tickets críticos
        if ($summary['critical_ratio'] > 15) {
            $recommendations[] = [
                'type' => 'high_priority',
                'category' => 'Gestión de Riesgos',
                'description' => 'El {$summary[\'critical_ratio\']}% de tickets son críticos, superando el umbral recomendado del 15%.',
                'action' => 'Implementar medidas preventivas adicionales y revisar procesos de validación.',
                'gxp_impact' => 'Alto - Riesgo de impacto en cumplimiento regulatorio'
            ];
        }
        
        // Recomendaciones basadas en SLA compliance
        if ($performance['sla_compliance']['compliance_rate'] < 85) {
            $recommendations[] = [
                'type' => 'performance',
                'category' => 'Cumplimiento SLA',
                'description' => 'La tasa de cumplimiento SLA es del {$performance[\'sla_compliance\'][\'compliance_rate\']}%, por debajo del objetivo del 85%.',
                'action' => 'Revisar asignación de recursos y procesos de escalación.',
                'gxp_impact' => 'Medio - Puede afectar tiempos de respuesta regulatoria'
            ];
        }
        
        // Recomendaciones basadas en documentación
        if (isset($compliance['documentation_completeness']['root_cause_percentage']) && 
            $compliance['documentation_completeness']['root_cause_percentage'] < 90) {
            $recommendations[] = [
                'type' => 'compliance',
                'category' => 'Documentación GxP',
                'description' => 'Solo el {$compliance[\'documentation_completeness\'][\'root_cause_percentage\']}% de tickets resueltos incluyen análisis de causa raíz.',
                'action' => 'Reforzar capacitación en metodologías de análisis de causa raíz y hacer obligatorio el campo.',
                'gxp_impact' => 'Alto - Requerimiento crítico para auditorías GxP'
            ];
        }
        
        // Recomendaciones basadas en escalaciones
        if ($performance['escalations']['total_escalated'] > ($summary['total_tickets'] * 0.1)) {
            $recommendations[] = [
                'type' => 'process',
                'category' => 'Gestión de Escalaciones',
                'description' => 'Tasa de escalación elevada: {$performance[\'escalations\'][\'total_escalated\']} tickets escalados.',
                'action' => 'Revisar criterios de asignación inicial y capacidad del equipo de primera línea.',
                'gxp_impact' => 'Medio - Puede indicar problemas en procesos de primera línea'
            ];
        }
        
        // Recomendación siempre presente para mejora continua
        $recommendations[] = [
            'type' => 'continuous_improvement',
            'category' => 'GAMP 5 - Mejora Continua',
            'description' => 'Mantener monitoreo continuo de métricas de calidad y rendimiento.',
            'action' => 'Realizar revisión mensual de indicadores y ajustar procesos según tendencias identificadas.',
            'gxp_impact' => 'Bajo - Buena práctica para mantenimiento del sistema de calidad'
        ];
        
        return $recommendations;
    }
    
    /**
     * Genera reporte de auditoría
     */
    public function generateAuditReport($dateFrom, $dateTo, $includeDetails = false) {
        $auditReport = [
            'report_info' => [
                'generated_at' => date('Y-m-d H:i:s'),
                'generated_by' => $_SESSION['usuario_id'] ?? 'Sistema',
                'period' => ['from' => $dateFrom, 'to' => $dateTo],
                'scope' => 'Auditoría completa del sistema de gestión de tickets'
            ],
            'compliance_summary' => $this->getComplianceStatus($dateFrom, $dateTo),
            'critical_tickets' => $this->getCriticalTicketsForAudit($dateFrom, $dateTo),
            'regulatory_impact_tickets' => $this->getRegulatoryImpactTickets($dateFrom, $dateTo),
            'sla_performance' => $this->getPerformanceMetrics($dateFrom, $dateTo),
            'change_control' => $this->getChangeControlData($dateFrom, $dateTo),
            'user_activity' => $this->getUserActivityForAudit($dateFrom, $dateTo)
        ];
        
        if ($includeDetails) {
            $auditReport['detailed_tickets'] = $this->getDetailedTicketsForAudit($dateFrom, $dateTo);
        }
        
        return $auditReport;
    }
    
    /**
     * Obtiene tickets críticos para auditoría
     */
    private function getCriticalTicketsForAudit($dateFrom, $dateTo) {
        $sql = "SELECT t.*, u1.nombre as created_by_name, u2.nombre as assigned_to_name
                FROM tickets t
                LEFT JOIN usuarios u1 ON t.created_by = u1.id
                LEFT JOIN usuarios u2 ON t.assigned_to = u2.id
                WHERE t.created_at BETWEEN ? AND ?
                AND t.risk_level = 'Crítico'
                ORDER BY t.created_at DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $dateFrom, $dateTo);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Obtiene tickets con impacto regulatorio
     */
    private function getRegulatoryImpactTickets($dateFrom, $dateTo) {
        $sql = "SELECT t.*, u1.nombre as created_by_name, u2.nombre as assigned_to_name
                FROM tickets t
                LEFT JOIN usuarios u1 ON t.created_by = u1.id
                LEFT JOIN usuarios u2 ON t.assigned_to = u2.id
                WHERE t.created_at BETWEEN ? AND ?
                AND (t.regulatory_impact = TRUE OR t.validation_impact = TRUE)
                ORDER BY t.risk_level DESC, t.created_at DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $dateFrom, $dateTo);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Obtiene datos de control de cambios
     */
    private function getChangeControlData($dateFrom, $dateTo) {
        $sql = "SELECT 
                    th.ticket_id,
                    th.action_type,
                    th.field_name,
                    th.old_value,
                    th.new_value,
                    th.comment,
                    th.created_at,
                    u.nombre as performed_by_name,
                    t.ticket_number
                FROM ticket_history th
                JOIN tickets t ON th.ticket_id = t.id
                LEFT JOIN usuarios u ON th.performed_by = u.id
                WHERE th.created_at BETWEEN ? AND ?
                AND th.action_type IN ('STATUS_CHANGED', 'ASSIGNED', 'ESCALATED')
                ORDER BY th.created_at DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $dateFrom, $dateTo);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Obtiene actividad de usuarios para auditoría
     */
    private function getUserActivityForAudit($dateFrom, $dateTo) {
        $sql = "SELECT 
                    u.nombre,
                    u.email,
                    COUNT(th.id) as total_actions,
                    COUNT(CASE WHEN th.action_type = 'CREATED' THEN 1 END) as tickets_created,
                    COUNT(CASE WHEN th.action_type = 'STATUS_CHANGED' THEN 1 END) as status_changes,
                    COUNT(CASE WHEN th.action_type = 'ASSIGNED' THEN 1 END) as assignments,
                    MIN(th.created_at) as first_activity,
                    MAX(th.created_at) as last_activity
                FROM ticket_history th
                JOIN usuarios u ON th.performed_by = u.id
                WHERE th.created_at BETWEEN ? AND ?
                GROUP BY u.id, u.nombre, u.email
                ORDER BY total_actions DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $dateFrom, $dateTo);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Obtiene tickets detallados para auditoría
     */
    private function getDetailedTicketsForAudit($dateFrom, $dateTo) {
        $sql = "SELECT t.*, 
                       u1.nombre as created_by_name, u1.email as created_by_email,
                       u2.nombre as assigned_to_name, u2.email as assigned_to_email
                FROM tickets t
                LEFT JOIN usuarios u1 ON t.created_by = u1.id
                LEFT JOIN usuarios u2 ON t.assigned_to = u2.id
                WHERE t.created_at BETWEEN ? AND ?
                ORDER BY t.risk_level DESC, t.created_at DESC
                LIMIT 1000"; // Limitar para evitar reportes muy grandes
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $dateFrom, $dateTo);
        $stmt->execute();
        $tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Agregar historial para cada ticket
        foreach ($tickets as &$ticket) {
            $ticket['history'] = $this->ticketManager->getTicketHistory($ticket['id']);
        }
        
        return $tickets;
    }
    
    /**
     * Exporta reporte a PDF
     */
    public function exportToPDF($reportData, $reportType = 'executive') {
        // Implementar generación de PDF usando TCPDF o similar
        // Por ahora retornamos HTML que puede ser convertido a PDF
        
        $html = $this->generateHTMLReport($reportData, $reportType);
        
        // Aquí se implementaría la conversión a PDF
        // require_once 'tcpdf/tcpdf.php';
        // $pdf = new TCPDF();
        // ...
        
        return $html; // Temporal: retornar HTML
    }
    
    /**
     * Genera reporte en formato HTML
     */
    public function generateHTMLReport($reportData, $reportType) {
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Reporte de Tickets - {$reportType}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .section { margin-bottom: 25px; }
                .metric-box { border: 1px solid #ddd; padding: 15px; margin: 10px 0; }
                .critical { color: #dc3545; font-weight: bold; }
                .high { color: #fd7e14; font-weight: bold; }
                table { width: 100%; border-collapse: collapse; margin: 10px 0; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .gxp-compliance { background-color: #e8f5e8; padding: 15px; border-left: 4px solid #28a745; }
                .recommendation { background-color: #fff3cd; padding: 10px; margin: 5px 0; border-left: 4px solid #ffc107; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>Sistema de Gestión de Tickets</h1>
                <h2>Reporte " . ucfirst($reportType) . "</h2>
                <p>Período: {$reportData['period']['from']} a {$reportData['period']['to']}</p>
                <p>Generado: " . date('d/m/Y H:i:s') . "</p>
            </div>
        ";
        
        if ($reportType === 'executive') {
            $html .= $this->generateExecutiveHTMLSection($reportData);
        } elseif ($reportType === 'audit') {
            $html .= $this->generateAuditHTMLSection($reportData);
        }
        
        $html .= "</body></html>";
        
        return $html;
    }
    
    /**
     * Genera sección HTML para reporte ejecutivo
     */
    private function generateExecutiveHTMLSection($reportData) {
        $summary = $reportData['summary'];
        $compliance = $reportData['compliance_status'];
        
        $html = "
            <div class='section'>
                <h3>Resumen Ejecutivo</h3>
                <div class='metric-box'>
                    <p><strong>Total de Tickets:</strong> {$summary['total_tickets']}</p>
                    <p><strong>Tickets Resueltos:</strong> {$summary['resolved_tickets']} ({$summary['resolution_rate']}%)</p>
                    <p><strong>Tickets Críticos:</strong> <span class='critical'>{$summary['critical_tickets']} ({$summary['critical_ratio']}%)</span></p>
                    <p><strong>Tiempo Promedio de Resolución:</strong> " . round($summary['avg_resolution_time'], 2) . " horas</p>
                </div>
            </div>
            
            <div class='section gxp-compliance'>
                <h3>Estado de Cumplimiento GxP</h3>
                <p><strong>Tickets con Impacto Regulatorio:</strong> {$summary['regulatory_impact_tickets']}</p>
                <p><strong>Tickets que Requieren Revalidación:</strong> {$summary['validation_impact_tickets']}</p>
            </div>
        ";
        
        if (!empty($reportData['recommendations'])) {
            $html .= "<div class='section'><h3>Recomendaciones</h3>";
            foreach ($reportData['recommendations'] as $rec) {
                $html .= "<div class='recommendation'>
                    <h4>{$rec['category']}</h4>
                    <p>{$rec['description']}</p>
                    <p><strong>Acción recomendada:</strong> {$rec['action']}</p>
                    <p><strong>Impacto GxP:</strong> {$rec['gxp_impact']}</p>
                </div>";
            }
            $html .= "</div>";
        }
        
        return $html;
    }
    
    /**
     * Genera sección HTML para reporte de auditoría
     */
    private function generateAuditHTMLSection($reportData) {
        $html = "
            <div class='section'>
                <h3>Información del Reporte de Auditoría</h3>
                <p><strong>Alcance:</strong> {$reportData['report_info']['scope']}</p>
                <p><strong>Generado por:</strong> Usuario ID {$reportData['report_info']['generated_by']}</p>
            </div>
            
            <div class='section'>
                <h3>Tickets Críticos</h3>
                <table>
                    <tr>
                        <th>Número</th>
                        <th>Título</th>
                        <th>Estado</th>
                        <th>Creado</th>
                        <th>Clasificación GxP</th>
                    </tr>
        ";
        
        foreach ($reportData['critical_tickets'] as $ticket) {
            $html .= "<tr>
                <td>{$ticket['ticket_number']}</td>
                <td>{$ticket['title']}</td>
                <td>{$ticket['status']}</td>
                <td>" . date('d/m/Y', strtotime($ticket['created_at'])) . "</td>
                <td>{$ticket['gxp_classification']}</td>
            </tr>";
        }
        
        $html .= "</table></div>";
        
        return $html;
    }
}
?>