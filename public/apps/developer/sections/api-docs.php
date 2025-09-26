<?php
/**
 * API Documentation Section
 * SBL Sistema Interno - Developer Portal
 */

$api_endpoints = [
    [
        'method' => 'GET',
        'endpoint' => '/api/v1/calibraciones',
        'description' => 'Obtener lista de calibraciones',
        'status' => 'active',
        'auth' => 'Bearer Token'
    ],
    [
        'method' => 'POST',
        'endpoint' => '/api/v1/calibraciones',
        'description' => 'Crear nueva calibración',
        'status' => 'active',
        'auth' => 'Bearer Token'
    ],
    [
        'method' => 'GET',
        'endpoint' => '/api/v1/instrumentos',
        'description' => 'Obtener instrumentos registrados',
        'status' => 'active',
        'auth' => 'API Key'
    ],
    [
        'method' => 'PUT',
        'endpoint' => '/api/v1/instrumentos/{id}',
        'description' => 'Actualizar información de instrumento',
        'status' => 'active',
        'auth' => 'Bearer Token'
    ],
    [
        'method' => 'GET',
        'endpoint' => '/api/v1/usuarios',
        'description' => 'Obtener lista de usuarios',
        'status' => 'deprecated',
        'auth' => 'Bearer Token'
    ],
    [
        'method' => 'POST',
        'endpoint' => '/api/v1/auth/login',
        'description' => 'Autenticación de usuario',
        'status' => 'active',
        'auth' => 'None'
    ]
];
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom" style="border-color: var(--dark-border) !important;">
    <h1 class="h2"><i class="bi bi-book text-primary me-2"></i>Documentación de APIs</h1>
    <div class="btn-group">
        <button class="btn btn-outline-primary btn-sm">
            <i class="bi bi-arrow-clockwise me-1"></i>Actualizar
        </button>
        <button class="btn btn-outline-success btn-sm">
            <i class="bi bi-download me-1"></i>Exportar OpenAPI
        </button>
        <button class="btn btn-outline-info btn-sm">
            <i class="bi bi-play-circle me-1"></i>API Tester
        </button>
    </div>
</div>

<!-- API Statistics -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card card-dark">
            <div class="card-body text-center">
                <div class="metric-label text-success mb-1">Endpoints Activos</div>
                <div class="metric-number text-success">
                    <?php echo count(array_filter($api_endpoints, fn($api) => $api['status'] === 'active')); ?>
                </div>
                <i class="bi bi-check-circle text-success fs-1 mt-2"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card card-dark">
            <div class="card-body text-center">
                <div class="metric-label text-warning mb-1">Deprecated</div>
                <div class="metric-number text-warning">
                    <?php echo count(array_filter($api_endpoints, fn($api) => $api['status'] === 'deprecated')); ?>
                </div>
                <i class="bi bi-exclamation-triangle text-warning fs-1 mt-2"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card card-dark">
            <div class="card-body text-center">
                <div class="metric-label text-info mb-1">Requests/min</div>
                <div class="metric-number text-info">245</div>
                <i class="bi bi-graph-up text-info fs-1 mt-2"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card card-dark">
            <div class="card-body text-center">
                <div class="metric-label text-primary mb-1">Uptime</div>
                <div class="metric-number text-primary">99.8%</div>
                <i class="bi bi-activity text-primary fs-1 mt-2"></i>
            </div>
        </div>
    </div>
</div>

<!-- API Endpoints -->
<div class="row">
    <div class="col-lg-8">
        <div class="card card-dark">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-list me-2"></i>Endpoints Disponibles</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-dark table-hover">
                        <thead>
                            <tr>
                                <th>Método</th>
                                <th>Endpoint</th>
                                <th>Descripción</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($api_endpoints as $api): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo match($api['method']) {
                                            'GET' => 'success',
                                            'POST' => 'primary',
                                            'PUT' => 'warning',
                                            'DELETE' => 'danger',
                                            default => 'secondary'
                                        }; 
                                    ?>">
                                        <?php echo $api['method']; ?>
                                    </span>
                                </td>
                                <td>
                                    <code class="text-light"><?php echo $api['endpoint']; ?></code>
                                </td>
                                <td><?php echo $api['description']; ?></td>
                                <td>
                                    <?php if ($api['status'] === 'active'): ?>
                                        <span class="badge bg-success">
                                            <i class="bi bi-check-circle me-1"></i>Activo
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">
                                            <i class="bi bi-exclamation-triangle me-1"></i>Deprecated
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" title="Ver documentación" onclick="showApiDocs('<?php echo $api['endpoint']; ?>')">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-outline-success" title="Probar endpoint" onclick="testEndpoint('<?php echo $api['endpoint']; ?>')">
                                            <i class="bi bi-play"></i>
                                        </button>
                                        <button class="btn btn-outline-info" title="Copiar cURL" onclick="copyCurl('<?php echo $api['endpoint']; ?>')">
                                            <i class="bi bi-clipboard"></i>
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
                <h6 class="mb-0"><i class="bi bi-tools me-2"></i>API Tester</h6>
            </div>
            <div class="card-body">
                <form id="api-tester-form">
                    <div class="mb-3">
                        <label class="form-label">Método</label>
                        <select class="form-select" id="api-method">
                            <option value="GET">GET</option>
                            <option value="POST">POST</option>
                            <option value="PUT">PUT</option>
                            <option value="DELETE">DELETE</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Endpoint</label>
                        <input type="text" class="form-control" id="api-endpoint" placeholder="/api/v1/calibraciones">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Headers</label>
                        <textarea class="form-control" id="api-headers" rows="3" placeholder='{"Authorization": "Bearer token", "Content-Type": "application/json"}'></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Body (JSON)</label>
                        <textarea class="form-control" id="api-body" rows="4" placeholder='{"key": "value"}'></textarea>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary" onclick="executeApiTest()">
                            <i class="bi bi-send me-1"></i>Enviar Request
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="clearApiTest()">
                            <i class="bi bi-x-circle me-1"></i>Limpiar
                        </button>
                    </div>
                </form>
                
                <hr class="my-4">
                
                <div>
                    <h6><i class="bi bi-terminal me-2"></i>Respuesta</h6>
                    <div id="api-response" class="code-block" style="min-height: 100px;">
                        <span class="text-muted">La respuesta aparecerá aquí...</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-dark">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-shield-check me-2"></i>Autenticación</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Tipo de Autenticación</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="auth-type" id="auth-bearer" checked>
                        <label class="form-check-label" for="auth-bearer">
                            Bearer Token
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="auth-type" id="auth-apikey">
                        <label class="form-check-label" for="auth-apikey">
                            API Key
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="auth-type" id="auth-basic">
                        <label class="form-check-label" for="auth-basic">
                            Basic Auth
                        </label>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Token/Key</label>
                    <input type="password" class="form-control" placeholder="Ingresa tu token o key">
                </div>
                <button class="btn btn-success btn-sm w-100">
                    <i class="bi bi-key me-1"></i>Validar Credenciales
                </button>
            </div>
        </div>
    </div>
</div>

<!-- API Documentation Modal -->
<div class="modal fade" id="apiDocsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="background: var(--dark-bg-secondary); border: 1px solid var(--dark-border);">
            <div class="modal-header" style="border-bottom: 1px solid var(--dark-border);">
                <h5 class="modal-title text-light">Documentación de API</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="api-docs-content">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<script>
function showApiDocs(endpoint) {
    const modal = new bootstrap.Modal(document.getElementById('apiDocsModal'));
    const content = document.getElementById('api-docs-content');
    
    content.innerHTML = `
        <div class="text-center py-3">
            <div class="spinner-border text-primary"></div>
            <p class="mt-2">Cargando documentación...</p>
        </div>
    `;
    
    modal.show();
    
    // Simulate loading documentation
    setTimeout(() => {
        content.innerHTML = \`
            <h6 class="text-primary">Endpoint: \${endpoint}</h6>
            <div class="code-block mb-3">
                <strong>URL:</strong> https://api.sbl-system.com\${endpoint}<br>
                <strong>Método:</strong> GET<br>
                <strong>Autenticación:</strong> Bearer Token requerido
            </div>
            
            <h6 class="text-success mt-4">Parámetros de Consulta</h6>
            <div class="table-responsive">
                <table class="table table-dark table-sm">
                    <thead>
                        <tr><th>Parámetro</th><th>Tipo</th><th>Requerido</th><th>Descripción</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>page</td><td>integer</td><td>No</td><td>Número de página (default: 1)</td></tr>
                        <tr><td>limit</td><td>integer</td><td>No</td><td>Elementos por página (default: 10)</td></tr>
                        <tr><td>status</td><td>string</td><td>No</td><td>Filtrar por estado</td></tr>
                    </tbody>
                </table>
            </div>
            
            <h6 class="text-warning mt-4">Ejemplo de Respuesta</h6>
            <div class="code-block">
{
  "success": true,
  "data": [
    {
      "id": 1,
      "nombre": "Calibración Báscula",
      "fecha": "2025-09-26",
      "estado": "completada"
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 10,
    "total": 156
  }
}
            </div>
        \`;
    }, 1000);
}

function testEndpoint(endpoint) {
    document.getElementById('api-endpoint').value = endpoint;
    document.getElementById('api-method').value = 'GET';
    
    // Scroll to tester
    document.querySelector('#api-tester-form').scrollIntoView({ behavior: 'smooth' });
}

function copyCurl(endpoint) {
    const curlCommand = \`curl -X GET "https://api.sbl-system.com\${endpoint}" \\
  -H "Authorization: Bearer YOUR_TOKEN" \\
  -H "Content-Type: application/json"\`;
    
    navigator.clipboard.writeText(curlCommand).then(() => {
        // Show notification
        const toast = document.createElement('div');
        toast.className = 'alert alert-success position-fixed';
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
        toast.textContent = 'Comando cURL copiado al portapapeles';
        document.body.appendChild(toast);
        
        setTimeout(() => toast.remove(), 3000);
    });
}

function executeApiTest() {
    const method = document.getElementById('api-method').value;
    const endpoint = document.getElementById('api-endpoint').value;
    const headers = document.getElementById('api-headers').value;
    const body = document.getElementById('api-body').value;
    const responseDiv = document.getElementById('api-response');
    
    responseDiv.innerHTML = '<div class="text-center"><div class="spinner-border spinner-border-sm text-primary"></div> Ejecutando...</div>';
    
    // Simulate API call
    setTimeout(() => {
        const mockResponse = {
            status: 200,
            statusText: 'OK',
            headers: {
                'Content-Type': 'application/json',
                'X-Response-Time': '45ms'
            },
            data: {
                success: true,
                message: 'Respuesta simulada del endpoint',
                timestamp: new Date().toISOString()
            }
        };
        
        responseDiv.innerHTML = \`
            <div style="color: #00ff88; margin-bottom: 10px;">
                <strong>Status:</strong> \${mockResponse.status} \${mockResponse.statusText}
            </div>
            <div style="color: #4a9eff; margin-bottom: 10px;">
                <strong>Headers:</strong>
            </div>
            <pre style="color: #8b949e; font-size: 0.8em; margin-bottom: 10px;">\${JSON.stringify(mockResponse.headers, null, 2)}</pre>
            <div style="color: #4a9eff; margin-bottom: 10px;">
                <strong>Response Body:</strong>
            </div>
            <pre style="color: #e6edf3;">\${JSON.stringify(mockResponse.data, null, 2)}</pre>
        \`;
    }, 1500);
}

function clearApiTest() {
    document.getElementById('api-endpoint').value = '';
    document.getElementById('api-headers').value = '';
    document.getElementById('api-body').value = '';
    document.getElementById('api-response').innerHTML = '<span class="text-muted">La respuesta aparecerá aquí...</span>';
}
</script>