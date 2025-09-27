<?php

/**
 * Dashboard de Monitoreo de Salud del Sistema SBL
 * Interfaz web para visualizar el estado del sistema, métricas y alertas
 * 
 * @version 1.0
 * @author Sistema SBL
 * @date 2025-09-26
 */

require_once __DIR__ . '/app/Core/Monitor/HealthMonitor.php';

// Inicializar monitor
$monitor = new HealthMonitor();

// Obtener acción
$action = $_GET['action'] ?? 'dashboard';

// Manejar peticiones AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    switch ($action) {
        case 'health_check':
            echo json_encode($monitor->runHealthCheck());
            exit;
            
        case 'quick_status':
            echo json_encode($monitor->getQuickStatus());
            exit;
            
        default:
            echo json_encode(['error' => 'Acción no válida']);
            exit;
    }
}

// Obtener datos para el dashboard
$healthData = $monitor->runHealthCheck();
$quickStatus = $monitor->getQuickStatus();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard de Monitoreo - Sistema SBL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .dashboard-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            backdrop-filter: blur(10px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            margin: 20px;
            padding: 30px;
        }
        
        .status-healthy { color: #28a745; }
        .status-warning { color: #ffc107; }
        .status-error { color: #dc3545; }
        
        .status-badge {
            font-size: 0.9rem;
            padding: 8px 16px;
            border-radius: 25px;
            font-weight: 600;
        }
        
        .status-badge.healthy {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
        }
        
        .status-badge.warning {
            background: linear-gradient(45deg, #ffc107, #fd7e14);
            color: #000;
        }
        
        .status-badge.error {
            background: linear-gradient(45deg, #dc3545, #e83e8c);
            color: white;
        }
        
        .metric-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
        }
        
        .metric-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        
        .metric-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .metric-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .metric-label {
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .alert-item {
            background: rgba(248, 249, 250, 0.8);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid;
        }
        
        .alert-critical { border-left-color: #dc3545; }
        .alert-warning { border-left-color: #ffc107; }
        .alert-info { border-left-color: #17a2b8; }
        
        .refresh-btn {
            background: linear-gradient(45deg, #007bff, #0056b3);
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .refresh-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,123,255,0.3);
        }
        
        .details-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .details-table th {
            background: linear-gradient(45deg, #f8f9fa, #e9ecef);
            font-weight: 600;
            color: #495057;
        }
        
        .auto-refresh {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        
        .loading-spinner {
            color: white;
            font-size: 3rem;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
    </style>
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <i class="fas fa-spinner fa-spin loading-spinner"></i>
    </div>

    <div class="container-fluid">
        <div class="dashboard-container">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-heartbeat text-primary"></i>
                        Dashboard de Monitoreo de Salud
                    </h1>
                    <p class="text-muted mb-0">Sistema SBL - Monitoreo en Tiempo Real</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="mb-2">
                        <span class="status-badge <?php echo $quickStatus['status']; ?>">
                            <i class="fas fa-circle pulse"></i>
                            <?php echo ucfirst($quickStatus['status']); ?>
                        </span>
                    </div>
                    <button class="btn refresh-btn" onclick="refreshData()">
                        <i class="fas fa-sync-alt"></i> Actualizar
                    </button>
                </div>
            </div>

            <!-- Métricas Principales -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="metric-card text-center">
                        <i class="fas fa-database metric-icon <?php echo $healthData['checks']['database']['status'] === 'healthy' ? 'text-success' : 'text-warning'; ?>"></i>
                        <div class="metric-value status-<?php echo $healthData['checks']['database']['status']; ?>">
                            <i class="fas fa-<?php echo $healthData['checks']['database']['status'] === 'healthy' ? 'check' : 'exclamation-triangle'; ?>"></i>
                        </div>
                        <div class="metric-label">Base de Datos</div>
                        <small class="text-muted"><?php echo $healthData['checks']['database']['response_time']; ?>ms</small>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="metric-card text-center">
                        <i class="fas fa-folder-open metric-icon <?php echo $healthData['checks']['filesystem']['status'] === 'healthy' ? 'text-success' : 'text-warning'; ?>"></i>
                        <div class="metric-value status-<?php echo $healthData['checks']['filesystem']['status']; ?>">
                            <i class="fas fa-<?php echo $healthData['checks']['filesystem']['status'] === 'healthy' ? 'check' : 'exclamation-triangle'; ?>"></i>
                        </div>
                        <div class="metric-label">Sistema de Archivos</div>
                        <small class="text-muted">
                            <?php echo $healthData['checks']['filesystem']['details']['disk_usage']['used_percent'] ?? 'N/A'; ?>% usado
                        </small>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="metric-card text-center">
                        <i class="fas fa-cogs metric-icon <?php echo $healthData['checks']['apis']['status'] === 'healthy' ? 'text-success' : 'text-warning'; ?>"></i>
                        <div class="metric-value status-<?php echo $healthData['checks']['apis']['status']; ?>">
                            <i class="fas fa-<?php echo $healthData['checks']['apis']['status'] === 'healthy' ? 'check' : 'exclamation-triangle'; ?>"></i>
                        </div>
                        <div class="metric-label">APIs</div>
                        <small class="text-muted">
                            <?php echo count($healthData['checks']['apis']['details']['working_apis'] ?? []); ?>/<?php echo count($healthData['checks']['apis']['details']['working_apis'] ?? []) + count($healthData['checks']['apis']['details']['failed_apis'] ?? []); ?> activas
                        </small>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="metric-card text-center">
                        <i class="fas fa-tachometer-alt metric-icon <?php echo $healthData['checks']['performance']['status'] === 'healthy' ? 'text-success' : 'text-warning'; ?>"></i>
                        <div class="metric-value status-<?php echo $healthData['checks']['performance']['status']; ?>">
                            <i class="fas fa-<?php echo $healthData['checks']['performance']['status'] === 'healthy' ? 'check' : 'exclamation-triangle'; ?>"></i>
                        </div>
                        <div class="metric-label">Rendimiento</div>
                        <small class="text-muted">
                            <?php echo $healthData['checks']['performance']['details']['memory']['usage_percent'] ?? 'N/A'; ?>% memoria
                        </small>
                    </div>
                </div>
            </div>

            <!-- Alertas y Estado Detallado -->
            <div class="row">
                <div class="col-md-6">
                    <div class="metric-card">
                        <h4 class="mb-3">
                            <i class="fas fa-bell text-warning"></i>
                            Alertas del Sistema
                        </h4>
                        
                        <?php if (!empty($healthData['alerts'])): ?>
                            <?php foreach ($healthData['alerts'] as $alert): ?>
                                <div class="alert-item alert-<?php echo $alert['severity']; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo htmlspecialchars($alert['title']); ?></strong>
                                            <p class="mb-0 text-muted"><?php echo htmlspecialchars($alert['message']); ?></p>
                                        </div>
                                        <small class="text-muted"><?php echo $alert['timestamp']; ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
                                <p>No hay alertas activas</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="metric-card">
                        <h4 class="mb-3">
                            <i class="fas fa-info-circle text-info"></i>
                            Información del Sistema
                        </h4>
                        
                        <table class="table table-sm">
                            <tr>
                                <td><strong>PHP Version:</strong></td>
                                <td><?php echo PHP_VERSION; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Servidor Web:</strong></td>
                                <td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Tiempo de Ejecución:</strong></td>
                                <td><?php echo $healthData['execution_time']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Última Verificación:</strong></td>
                                <td><?php echo $healthData['timestamp']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Uso de Memoria:</strong></td>
                                <td><?php echo $healthData['checks']['performance']['details']['memory']['current_usage'] ?? 'N/A'; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Estado General:</strong></td>
                                <td>
                                    <span class="status-badge <?php echo $healthData['overall_status']; ?>">
                                        <?php echo ucfirst($healthData['overall_status']); ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Detalles Técnicos -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="metric-card">
                        <h4 class="mb-3">
                            <i class="fas fa-cog text-secondary"></i>
                            Detalles Técnicos
                        </h4>
                        
                        <div class="accordion" id="technicalDetails">
                            <!-- Base de Datos -->
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#databaseDetails">
                                        <i class="fas fa-database me-2"></i>
                                        Base de Datos
                                        <span class="status-badge <?php echo $healthData['checks']['database']['status']; ?> ms-2">
                                            <?php echo ucfirst($healthData['checks']['database']['status']); ?>
                                        </span>
                                    </button>
                                </h2>
                                <div id="databaseDetails" class="accordion-collapse collapse">
                                    <div class="accordion-body">
                                        <pre><?php echo json_encode($healthData['checks']['database']['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
                                    </div>
                                </div>
                            </div>

                            <!-- APIs -->
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#apiDetails">
                                        <i class="fas fa-cogs me-2"></i>
                                        APIs del Sistema
                                        <span class="status-badge <?php echo $healthData['checks']['apis']['status']; ?> ms-2">
                                            <?php echo ucfirst($healthData['checks']['apis']['status']); ?>
                                        </span>
                                    </button>
                                </h2>
                                <div id="apiDetails" class="accordion-collapse collapse">
                                    <div class="accordion-body">
                                        <pre><?php echo json_encode($healthData['checks']['apis']['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
                                    </div>
                                </div>
                            </div>

                            <!-- Rendimiento -->
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#performanceDetails">
                                        <i class="fas fa-tachometer-alt me-2"></i>
                                        Métricas de Rendimiento
                                        <span class="status-badge <?php echo $healthData['checks']['performance']['status']; ?> ms-2">
                                            <?php echo ucfirst($healthData['checks']['performance']['status']); ?>
                                        </span>
                                    </button>
                                </h2>
                                <div id="performanceDetails" class="accordion-collapse collapse">
                                    <div class="accordion-body">
                                        <pre><?php echo json_encode($healthData['checks']['performance']['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enlaces Rápidos -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="metric-card">
                        <h4 class="mb-3">
                            <i class="fas fa-link text-primary"></i>
                            Enlaces Rápidos del Sistema
                        </h4>
                        
                        <div class="row">
                            <div class="col-md-3">
                                <a href="/" class="btn btn-outline-primary w-100 mb-2">
                                    <i class="fas fa-home"></i> Página Principal
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="/sistema-interno/public/apps/internal/instrumentos/list_gages.html" class="btn btn-outline-success w-100 mb-2">
                                    <i class="fas fa-wrench"></i> Instrumentos
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="/public/api/usuarios.php" class="btn btn-outline-info w-100 mb-2">
                                    <i class="fas fa-api"></i> API Usuarios
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="/fix_redirections.php" class="btn btn-outline-warning w-100 mb-2">
                                    <i class="fas fa-tools"></i> Corregir Rutas
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Auto Refresh Toggle -->
    <div class="auto-refresh">
        <div class="form-check form-switch bg-white p-3 rounded-pill shadow">
            <input class="form-check-input" type="checkbox" id="autoRefresh">
            <label class="form-check-label" for="autoRefresh">
                <i class="fas fa-sync-alt"></i> Auto-actualizar
            </label>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let refreshInterval;
        
        function refreshData() {
            document.getElementById('loadingOverlay').style.display = 'flex';
            
            fetch('?action=health_check&ajax=1')
                .then(response => response.json())
                .then(data => {
                    // Recargar la página con los nuevos datos
                    window.location.reload();
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('loadingOverlay').style.display = 'none';
                });
        }
        
        function toggleAutoRefresh() {
            const checkbox = document.getElementById('autoRefresh');
            
            if (checkbox.checked) {
                refreshInterval = setInterval(refreshData, 30000); // 30 segundos
                console.log('Auto-refresh activado (30s)');
            } else {
                clearInterval(refreshInterval);
                console.log('Auto-refresh desactivado');
            }
        }
        
        document.getElementById('autoRefresh').addEventListener('change', toggleAutoRefresh);
        
        // Actualizar timestamp cada segundo
        setInterval(() => {
            const timestamps = document.querySelectorAll('.timestamp');
            timestamps.forEach(ts => {
                // Actualizar timestamps relativos si los hubiera
            });
        }, 1000);
        
        // Animaciones
        document.addEventListener('DOMContentLoaded', function() {
            // Animar métricas al cargar
            const metrics = document.querySelectorAll('.metric-card');
            metrics.forEach((metric, index) => {
                setTimeout(() => {
                    metric.style.opacity = '0';
                    metric.style.transform = 'translateY(20px)';
                    metric.style.transition = 'all 0.5s ease';
                    
                    setTimeout(() => {
                        metric.style.opacity = '1';
                        metric.style.transform = 'translateY(0)';
                    }, 100);
                }, index * 100);
            });
        });
    </script>
</body>
</html>