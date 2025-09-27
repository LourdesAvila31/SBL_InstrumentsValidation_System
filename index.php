<?php
// Página principal del Sistema SBL
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SBL Sistema Interno - Página Principal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .main-container { background: white; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); margin-top: 50px; }
        .header { background: linear-gradient(45deg, #007bff, #0056b3); color: white; padding: 30px; border-radius: 15px 15px 0 0; text-align: center; }
        .status-ok { color: #28a745; }
        .card { border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s; }
        .card:hover { transform: translateY(-2px); }
        .btn-custom { background: linear-gradient(45deg, #007bff, #0056b3); border: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="main-container">
            <div class="header">
                <h1><i class="fas fa-cogs"></i> SBL Sistema Interno</h1>
                <p class="mb-0">Sistema de Gestión de Instrumentos y Calibraciones</p>
                <div class="mt-3">
                    <span class="badge bg-success fs-6"><i class="fas fa-check-circle"></i> Sistema Operativo</span>
                </div>
            </div>
            
            <div class="p-4">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-info-circle text-primary"></i> Información del Sistema</h5>
                                <p><strong>Servidor:</strong> <?php echo $_SERVER['SERVER_SOFTWARE']; ?></p>
                                <p><strong>PHP:</strong> <?php echo PHP_VERSION; ?></p>
                                <p><strong>Ruta:</strong> <?php echo __DIR__; ?></p>
                                <p class="status-ok"><strong>Estado:</strong> <i class="fas fa-check-circle"></i> Funcionando correctamente</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-tools text-warning"></i> Diagnóstico</h5>
                                <p>Para resolver problemas de acceso o configuración:</p>
                                <a href="diagnostico_sistema.php" class="btn btn-warning">
                                    <i class="fas fa-search"></i> Ejecutar Diagnóstico Completo
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <h3><i class="fas fa-link"></i> Accesos Principales del Sistema</h3>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-wrench text-primary"></i> Gestión de Instrumentos</h5>
                                <p class="card-text">Acceso al sistema principal de instrumentos y calibraciones</p>
                                <a href="sistema-interno/public/apps/internal/instrumentos/list_gages.html" class="btn btn-primary btn-custom">
                                    <i class="fas fa-list"></i> Lista de Instrumentos
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-api text-success"></i> APIs del Sistema</h5>
                                <p class="card-text">Interfaces de programación y servicios web</p>
                                <div class="btn-group-vertical w-100">
                                    <a href="public/api/usuarios.php" class="btn btn-outline-success mb-1">API Usuarios</a>
                                    <a href="public/api/calibraciones.php" class="btn btn-outline-success mb-1">API Calibraciones</a>
                                    <a href="public/api/proveedores.php" class="btn btn-outline-success">API Proveedores</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-database text-info"></i> Base de Datos</h5>
                                <p class="card-text">Configuración y gestión de la base de datos</p>
                                <a href="backend/instrumentos/setup_instrumentos_table.php" class="btn btn-info">
                                    <i class="fas fa-database"></i> Setup de BD
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-vial text-secondary"></i> Pruebas del Sistema</h5>
                                <p class="card-text">Herramientas de testing y verificación</p>
                                <div class="btn-group-vertical w-100">
                                    <a href="test.php" class="btn btn-outline-secondary mb-1">Test Básico</a>
                                    <a href="backend/instrumentos/list_gages.php" class="btn btn-outline-secondary">API Test</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info mt-4">
                    <h5><i class="fas fa-lightbulb"></i> Solución al Error 404</h5>
                    <p><strong>La URL correcta para los instrumentos es:</strong></p>
                    <code>http://localhost/SBL_SISTEMA_INTERNO/sistema-interno/public/apps/internal/instrumentos/list_gages.html</code>
                    <p class="mt-2"><strong>No:</strong> <del>/public/apps/internal/instrumentos/list_gages.html</del></p>
                </div>
                
                <div class="text-center mt-4">
                    <p class="text-muted">
                        <i class="fas fa-calendar"></i> <?php echo date('Y-m-d H:i:s'); ?> | 
                        <i class="fas fa-server"></i> XAMPP | 
                        <i class="fas fa-code"></i> PHP <?php echo PHP_VERSION; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>