<?php
/**
 * Database Management Section
 * SBL Sistema Interno - Developer Portal
 */

// Simulated database connection (you would use real connection here)
$db_status = "Conectada";
$db_tables = [
    ['name' => 'usuarios', 'rows' => 156, 'size' => '2.3 MB'],
    ['name' => 'instrumentos', 'rows' => 89, 'size' => '1.8 MB'],
    ['name' => 'calibraciones', 'rows' => 2341, 'size' => '15.7 MB'],
    ['name' => 'certificados', 'rows' => 1823, 'size' => '45.2 MB'],
    ['name' => 'empresas', 'rows' => 23, 'size' => '0.5 MB'],
    ['name' => 'auditoria', 'rows' => 8932, 'size' => '12.4 MB']
];
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom" style="border-color: var(--dark-border) !important;">
    <h1 class="h2"><i class="bi bi-database text-primary me-2"></i>Gestión de Base de Datos</h1>
    <div class="btn-group">
        <button class="btn btn-outline-primary btn-sm">
            <i class="bi bi-arrow-clockwise me-1"></i>Actualizar
        </button>
        <button class="btn btn-outline-success btn-sm">
            <i class="bi bi-download me-1"></i>Backup
        </button>
        <button class="btn btn-outline-warning btn-sm">
            <i class="bi bi-gear me-1"></i>Optimizar
        </button>
    </div>
</div>

<!-- Database Status -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card card-dark">
            <div class="card-body text-center">
                <div class="metric-label text-success mb-1">Estado</div>
                <div class="metric-number text-success">Online</div>
                <i class="bi bi-database-check text-success fs-1 mt-2"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card card-dark">
            <div class="card-body text-center">
                <div class="metric-label text-primary mb-1">Tablas</div>
                <div class="metric-number text-primary"><?php echo count($db_tables); ?></div>
                <i class="bi bi-table text-primary fs-1 mt-2"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card card-dark">
            <div class="card-body text-center">
                <div class="metric-label text-warning mb-1">Tamaño Total</div>
                <div class="metric-number text-warning">77.9 MB</div>
                <i class="bi bi-hdd text-warning fs-1 mt-2"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card card-dark">
            <div class="card-body text-center">
                <div class="metric-label text-info mb-1">Conexiones</div>
                <div class="metric-number text-info">12</div>
                <i class="bi bi-plug text-info fs-1 mt-2"></i>
            </div>
        </div>
    </div>
</div>

<!-- Tables Information -->
<div class="row">
    <div class="col-lg-8">
        <div class="card card-dark">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-table me-2"></i>Tablas de la Base de Datos</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-dark table-hover">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Registros</th>
                                <th>Tamaño</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($db_tables as $table): ?>
                            <tr>
                                <td>
                                    <i class="bi bi-table me-2 text-primary"></i>
                                    <strong><?php echo $table['name']; ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo number_format($table['rows']); ?></span>
                                </td>
                                <td><?php echo $table['size']; ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" title="Ver estructura">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-outline-success" title="Exportar">
                                            <i class="bi bi-download"></i>
                                        </button>
                                        <button class="btn btn-outline-warning" title="Optimizar">
                                            <i class="bi bi-gear"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card card-dark mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-activity me-2"></i>Actividad Reciente</h6>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <div class="list-group-item bg-transparent border-secondary">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">Backup automático</h6>
                            <small class="text-success">✓ Completado</small>
                        </div>
                        <p class="mb-1 text-muted">Backup diario ejecutado correctamente</p>
                        <small class="text-muted">hace 2 horas</small>
                    </div>
                    <div class="list-group-item bg-transparent border-secondary">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">Optimización de índices</h6>
                            <small class="text-success">✓ Completado</small>
                        </div>
                        <p class="mb-1 text-muted">Tabla calibraciones optimizada</p>
                        <small class="text-muted">hace 6 horas</small>
                    </div>
                    <div class="list-group-item bg-transparent border-secondary">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">Nuevo registro</h6>
                            <small class="text-info">ℹ️ Info</small>
                        </div>
                        <p class="mb-1 text-muted">156 registros añadidos a usuarios</p>
                        <small class="text-muted">hace 1 día</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-dark">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-terminal me-2"></i>Query Rápida</h6>
            </div>
            <div class="card-body">
                <form>
                    <div class="mb-3">
                        <label class="form-label">Consulta SQL</label>
                        <textarea class="form-control" rows="4" placeholder="SELECT * FROM usuarios LIMIT 10;"></textarea>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary">
                            <i class="bi bi-play-fill me-1"></i>Ejecutar
                        </button>
                        <button type="button" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle me-1"></i>Limpiar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>