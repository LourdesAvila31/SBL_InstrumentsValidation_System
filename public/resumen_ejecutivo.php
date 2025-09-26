<?php
/**
 * Resumen Ejecutivo - Sistema SBL Interno
 * Reporte consolidado del estado de todos los módulos
 */

// Configuración de reporte
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Obtener datos de la API
$apiUrl = 'http://localhost/api_verificacion.php?action=full_report';
$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'user_agent' => 'SBL-Executive-Report/1.0'
    ]
]);

$reportData = @file_get_contents($apiUrl, false, $context);
$data = $reportData ? json_decode($reportData, true) : null;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resumen Ejecutivo - Sistema SBL</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; line-height: 1.6; color: #333; background: #f4f4f4; }
        .container { max-width: 1000px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #2c3e50, #3498db); color: white; padding: 40px 20px; text-align: center; border-radius: 10px; margin-bottom: 30px; }
        .header h1 { font-size: 2.5em; margin-bottom: 10px; }
        .header .subtitle { font-size: 1.2em; opacity: 0.9; }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .summary-card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center; }
        .summary-card h3 { color: #2c3e50; margin-bottom: 15px; }
        .metric { font-size: 2.5em; font-weight: bold; margin: 10px 0; }
        .metric.good { color: #27ae60; }
        .metric.warning { color: #f39c12; }
        .metric.critical { color: #e74c3c; }
        .section { background: white; margin: 20px 0; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .section h2 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; margin-bottom: 20px; }
        .module-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .module-table th, .module-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .module-table th { background: #f8f9fa; font-weight: bold; color: #2c3e50; }
        .status-indicator { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
        .status-ok { background: #d4edda; color: #155724; }
        .status-warning { background: #fff3cd; color: #856404; }
        .status-error { background: #f8d7da; color: #721c24; }
        .recommendations { background: #e8f4fd; border-left: 4px solid #3498db; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .issue-list { background: #fff5f5; border-left: 4px solid #e74c3c; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .footer { text-align: center; padding: 30px; color: #7f8c8d; }
        .print-button { background: #3498db; color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; margin: 20px 0; }
        .print-button:hover { background: #2980b9; }
        @media print {
            .print-button { display: none; }
            .header { background: #2c3e50 !important; color: white !important; }
        }
        .chart-container { text-align: center; margin: 20px 0; }
        .progress-bar { background: #ecf0f1; border-radius: 10px; height: 20px; margin: 10px 0; overflow: hidden; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #27ae60, #2ecc71); transition: width 0.3s ease; }
        .timestamp { color: #7f8c8d; font-size: 0.9em; text-align: center; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Resumen Ejecutivo</h1>
            <div class="subtitle">Sistema SBL - Estado de Módulos Internos</div>
            <?php if ($data): ?>
                <div class="timestamp">Generado el: <?php echo date('d/m/Y H:i:s', strtotime($data['timestamp'])); ?></div>
            <?php endif; ?>
        </div>

        <?php if (!$data): ?>
            <div class="section">
                <h2>❌ Error de Conexión</h2>
                <p>No se pudo obtener el reporte del sistema. Verifique que:</p>
                <ul>
                    <li>El servidor web esté funcionando</li>
                    <li>La API de verificación esté disponible</li>
                    <li>No hay problemas de conectividad</li>
                </ul>
                <button class="print-button" onclick="location.reload()">🔄 Reintentar</button>
            </div>
        <?php else: ?>

        <!-- Métricas Principales -->
        <div class="summary-grid">
            <div class="summary-card">
                <h3>🏗️ Estado General</h3>
                <?php 
                $overallStatus = $data['summary']['overall_status'] ?? 'unknown';
                $statusClass = $overallStatus === 'ok' ? 'good' : ($overallStatus === 'warning' ? 'warning' : 'critical');
                $statusText = $overallStatus === 'ok' ? 'OPERATIVO' : ($overallStatus === 'warning' ? 'ATENCIÓN' : 'CRÍTICO');
                ?>
                <div class="metric <?php echo $statusClass; ?>"><?php echo $statusText; ?></div>
                <p>Sistema <?php echo $overallStatus === 'ok' ? 'funcionando correctamente' : 'requiere ' . ($overallStatus === 'warning' ? 'atención' : 'intervención inmediata'); ?></p>
            </div>

            <div class="summary-card">
                <h3>📦 Módulos</h3>
                <?php 
                $modulesOk = $data['summary']['statistics']['modules_ok'] ?? 0;
                $modulesTotal = $data['summary']['statistics']['modules_total'] ?? 0;
                $modulesPercentage = $modulesTotal > 0 ? round(($modulesOk / $modulesTotal) * 100) : 0;
                $moduleClass = $modulesPercentage >= 90 ? 'good' : ($modulesPercentage >= 70 ? 'warning' : 'critical');
                ?>
                <div class="metric <?php echo $moduleClass; ?>"><?php echo $modulesOk; ?>/<?php echo $modulesTotal; ?></div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $modulesPercentage; ?>%"></div>
                </div>
                <p><?php echo $modulesPercentage; ?>% operativos</p>
            </div>

            <div class="summary-card">
                <h3>🗄️ Base de Datos</h3>
                <?php 
                $dbStatus = $data['database']['success'] ?? false;
                $dbClass = $dbStatus ? 'good' : 'critical';
                $dbText = $dbStatus ? 'CONECTADA' : 'ERROR';
                ?>
                <div class="metric <?php echo $dbClass; ?>"><?php echo $dbText; ?></div>
                <?php if ($dbStatus && isset($data['database']['data']['tables']['count'])): ?>
                    <p><?php echo $data['database']['data']['tables']['count']; ?> tablas disponibles</p>
                <?php else: ?>
                    <p>Verificar conectividad</p>
                <?php endif; ?>
            </div>

            <div class="summary-card">
                <h3>⚙️ Infraestructura</h3>
                <?php 
                $phpOk = $data['infrastructure']['success'] ?? false;
                $infraClass = $phpOk ? 'good' : 'critical';
                $infraText = $phpOk ? 'ESTABLE' : 'PROBLEMAS';
                ?>
                <div class="metric <?php echo $infraClass; ?>"><?php echo $infraText; ?></div>
                <?php if (isset($data['system_info']['php_version'])): ?>
                    <p>PHP <?php echo $data['system_info']['php_version']; ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Estado Detallado de Módulos -->
        <div class="section">
            <h2>📋 Estado Detallado de Módulos</h2>
            <?php if (isset($data['modules']['data'])): ?>
                <table class="module-table">
                    <thead>
                        <tr>
                            <th>Módulo</th>
                            <th>Estado</th>
                            <th>Frontend</th>
                            <th>Backend</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['modules']['data'] as $key => $module): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($module['name']); ?></strong></td>
                                <td>
                                    <?php 
                                    $status = $module['overall_status'];
                                    $statusText = $status === 'ok' ? 'Operativo' : ($status === 'warning' ? 'Advertencia' : 'Error');
                                    $statusClass = 'status-' . ($status === 'ok' ? 'ok' : ($status === 'warning' ? 'warning' : 'error'));
                                    ?>
                                    <span class="status-indicator <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                </td>
                                <td>
                                    <?php 
                                    $frontendStatus = $module['web_access']['frontend']['accessible'] ?? null;
                                    if ($frontendStatus !== null) {
                                        echo $frontendStatus ? '✅ Accesible' : '❌ Error';
                                    } else {
                                        echo '➖ N/A';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $backendStatus = $module['web_access']['backend']['accessible'] ?? null;
                                    if ($backendStatus !== null) {
                                        echo $backendStatus ? '✅ Funcional' : '❌ Error';
                                    } else {
                                        echo '➖ No implementado';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $fileCount = count($module['files']);
                                    $webCount = count($module['web_access']);
                                    echo "Archivos: {$fileCount}, Enlaces: {$webCount}";
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No se pudieron obtener datos de módulos.</p>
            <?php endif; ?>
        </div>

        <!-- Problemas Identificados -->
        <?php if (!empty($data['summary']['issues'])): ?>
            <div class="issue-list">
                <h3>🚨 Problemas Identificados</h3>
                <ul>
                    <?php foreach ($data['summary']['issues'] as $issue): ?>
                        <li><?php echo htmlspecialchars($issue); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Recomendaciones -->
        <?php if (!empty($data['summary']['recommendations'])): ?>
            <div class="recommendations">
                <h3>💡 Recomendaciones</h3>
                <ul>
                    <?php foreach ($data['summary']['recommendations'] as $recommendation): ?>
                        <li><?php echo htmlspecialchars($recommendation); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php else: ?>
            <div class="recommendations">
                <h3>💡 Recomendaciones Generales</h3>
                <ul>
                    <li>Realizar verificaciones periódicas del sistema</li>
                    <li>Mantener backups actualizados de la base de datos</li>
                    <li>Monitorear el uso de recursos del servidor</li>
                    <li>Implementar los módulos faltantes según prioridad de negocio</li>
                    <li>Establecer alertas automáticas para componentes críticos</li>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Información Técnica -->
        <div class="section">
            <h2>🔧 Información Técnica</h2>
            <div class="summary-grid">
                <div class="summary-card">
                    <h3>Servidor</h3>
                    <p><strong>Sistema:</strong> <?php echo $data['system_info']['os'] ?? 'N/A'; ?></p>
                    <p><strong>Servidor Web:</strong> <?php echo $data['system_info']['server'] ?? 'N/A'; ?></p>
                    <p><strong>PHP:</strong> <?php echo $data['system_info']['php_version'] ?? 'N/A'; ?></p>
                </div>
                <div class="summary-card">
                    <h3>Base de Datos</h3>
                    <?php if ($data['database']['success']): ?>
                        <p><strong>Estado:</strong> ✅ Conectada</p>
                        <p><strong>Base de datos:</strong> <?php echo $data['database']['data']['database']['name'] ?? 'sbl_sistema_interno'; ?></p>
                        <p><strong>Tablas:</strong> <?php echo $data['database']['data']['tables']['count'] ?? 'N/A'; ?></p>
                    <?php else: ?>
                        <p><strong>Estado:</strong> ❌ Error de conexión</p>
                    <?php endif; ?>
                </div>
                <div class="summary-card">
                    <h3>Archivos del Sistema</h3>
                    <p><strong>Directorio raíz:</strong> <?php echo $data['system_info']['document_root'] ?? 'N/A'; ?></p>
                    <p><strong>Última verificación:</strong> <?php echo date('H:i:s', strtotime($data['timestamp'])); ?></p>
                </div>
            </div>
        </div>

        <div class="footer">
            <button class="print-button" onclick="window.print()">🖨️ Imprimir Reporte</button>
            <button class="print-button" onclick="location.reload()">🔄 Actualizar</button>
            <button class="print-button" onclick="window.open('/panel_diagnostico.html', '_blank')">📊 Panel Completo</button>
            <p>Sistema SBL - Resumen Ejecutivo | Generado automáticamente</p>
        </div>

        <?php endif; ?>
    </div>

    <script>
        // Auto-refresh cada 5 minutos si la página está visible
        let refreshInterval;
        
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                clearInterval(refreshInterval);
            } else {
                refreshInterval = setInterval(() => {
                    location.reload();
                }, 300000); // 5 minutos
            }
        });
        
        // Iniciar auto-refresh
        if (!document.hidden) {
            refreshInterval = setInterval(() => {
                location.reload();
            }, 300000);
        }
    </script>
</body>
</html>