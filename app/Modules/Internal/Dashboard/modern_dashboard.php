<?php
require_once dirname(__DIR__, 3) . '/Core/auth.php';

$rol        = $_SESSION['rol'] ?? '';
$roleAlias  = session_role_alias() ?? '';
$allowedRol = ['superadministrador', 'administrador', 'supervisor', 'operador', 'lector', 'cliente'];
$portalScopes = $roleAlias === 'cliente' ? ['tenant'] : ['internal'];

ensure_portal_access($portalScopes);

if (!in_array($roleAlias, $allowedRol, true)) {
    http_response_code(403);
    echo 'Acceso denegado';
    exit;
}

$nombre = $_SESSION['nombre'];
$usuario_login = $_SESSION['usuario_login'] ?? '';

// Configuración de secciones disponibles por rol
$sectionsConfig = [
    'dashboard' => [
        'label' => 'Dashboard Principal',
        'url' => 'apps/internal/index.html',
        'icon' => 'fa-tachometer-alt',
        'description' => 'Panel principal del sistema',
        'roles' => ['superadministrador', 'administrador', 'supervisor', 'operador', 'lector', 'cliente']
    ],
    'instrumentos' => [
        'label' => 'INSTRUMENTOS',
        'url' => 'apps/internal/instrumentos/list_gages.html',
        'icon' => 'fa-boxes-stacked',
        'description' => 'Gestión de instrumentos de medición',
        'roles' => ['superadministrador', 'administrador', 'supervisor', 'operador']
    ],
    'planeacion' => [
        'label' => 'PLANEACIÓN',
        'url' => 'apps/internal/planeacion/list_planning.html',
        'icon' => 'fa-calendar-alt',
        'description' => 'Planificación de calibraciones',
        'roles' => ['superadministrador', 'administrador', 'supervisor']
    ],
    'calibraciones' => [
        'label' => 'CALIBRACIONES',
        'url' => 'apps/internal/calibraciones/list_calibrations.html',
        'icon' => 'fa-balance-scale',
        'description' => 'Gestión de calibraciones',
        'roles' => ['superadministrador', 'administrador', 'supervisor', 'operador', 'lector']
    ],
    'reportes' => [
        'label' => 'REPORTES',
        'url' => 'apps/internal/reportes/reports.html',
        'icon' => 'fa-file-alt',
        'description' => 'Generación de reportes',
        'roles' => ['superadministrador', 'administrador', 'supervisor', 'operador', 'lector']
    ],
    'usuarios' => [
        'label' => 'USUARIOS',
        'url' => 'apps/internal/usuarios/list_users.html',
        'icon' => 'fa-users',
        'description' => 'Gestión de usuarios del sistema',
        'roles' => ['superadministrador', 'administrador']
    ],
    'api_tokens' => [
        'label' => 'TOKENS API',
        'url' => 'apps/internal/configuracion/api_tokens.html',
        'icon' => 'fa-key',
        'description' => 'Gestión de tokens de API',
        'roles' => ['superadministrador', 'administrador']
    ],
    'calidad' => [
        'label' => 'CALIDAD',
        'url' => 'apps/internal/calidad/index.html',
        'icon' => 'fa-award',
        'description' => 'Sistema de gestión de calidad',
        'roles' => ['superadministrador', 'administrador', 'supervisor']
    ],
    'gamp5' => [
        'label' => 'GAMP5',
        'url' => 'gamp5_dashboard.html',
        'icon' => 'fa-shield-alt',
        'description' => 'Dashboard de validación GAMP5',
        'roles' => ['superadministrador', 'administrador']
    ],
    'developer' => [
        'label' => 'DEVELOPER',
        'url' => 'apps/internal/developer/selector.html',
        'icon' => 'fa-code',
        'description' => 'Herramientas de desarrollo',
        'roles' => ['superadministrador']
    ],

    'admin' => [
        'label' => 'ADMINISTRACIÓN',
        'url' => 'admin_dashboard.html',
        'icon' => 'fa-cogs',
        'description' => 'Panel de administración general',
        'roles' => ['superadministrador', 'administrador']
    ],
    'ayuda' => [
        'label' => 'CENTRO DE AYUDA',
        'url' => 'apps/internal/ayuda/help_center.html',
        'icon' => 'fa-circle-question',
        'description' => 'Documentación y soporte',
        'roles' => ['superadministrador', 'administrador', 'supervisor', 'operador', 'lector', 'cliente']
    ]
];

// Filtrar secciones por rol del usuario
$availableSections = [];
$rolNormalizado = strtolower($rol);

foreach ($sectionsConfig as $key => $section) {
    $rolesPermitidos = array_map('strtolower', $section['roles']);
    if (in_array($rolNormalizado, $rolesPermitidos)) {
        $availableSections[$key] = $section;
    }
}

// Información del rol
$roleInfo = [
    'superadministrador' => [
        'color' => '#dc3545',
        'badge' => 'SUPER ADMIN',
        'description' => 'Acceso completo al sistema'
    ],
    'administrador' => [
        'color' => '#fd7e14',
        'badge' => 'ADMINISTRADOR',
        'description' => 'Gestión administrativa completa'
    ],
    'supervisor' => [
        'color' => '#20c997',
        'badge' => 'SUPERVISOR',
        'description' => 'Supervisión y coordinación'
    ],
    'operador' => [
        'color' => '#0dcaf0',
        'badge' => 'OPERADOR',
        'description' => 'Operaciones de calibración'
    ],
    'lector' => [
        'color' => '#6f42c1',
        'badge' => 'LECTOR',
        'description' => 'Solo lectura de información'
    ],
    'cliente' => [
        'color' => '#198754',
        'badge' => 'CLIENTE',
        'description' => 'Acceso de cliente'
    ]
];

$currentRoleInfo = $roleInfo[$rolNormalizado] ?? $roleInfo['lector'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <script>
        (function() {
            var marker = '/public/';
            var path = window.location.pathname || '';
            var index = path.indexOf(marker);
            var base = index !== -1 ? path.slice(0, index + marker.length) : '/';
            if (!base.endsWith('/')) {
                base += '/';
            }
            window.BASE_URL = base === '/' ? '' : base.replace(/\/$/, '');
            document.write('<base href="' + base + '">');
        })();
    </script>
    
    <title>Selector de Sección | SBL Sistema Interno</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    
    <!-- Bootstrap, Montserrat, FontAwesome -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"/>
    <link rel="stylesheet" href="assets/styles/master-theme.css">
    
    <style>
        :root {
            --sbl-primary: #0d575a;
            --sbl-secondary: #217b9b;
            --sbl-success: #28a745;
            --sbl-warning: #ffc107;
            --sbl-danger: #dc3545;
            --sbl-info: #17a2b8;
            --sbl-light: #f8f9fa;
            --sbl-dark: #343a40;
        }
        
        body {
            background: linear-gradient(135deg, #e6f6fb 0%, #a3defd 55%, #0d575a 100%);
            font-family: 'Montserrat', Arial, sans-serif;
            margin: 0;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        .dashboard-container {
            min-height: 100vh;
            padding: 40px 20px;
        }
        
        .welcome-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 40px;
            text-align: center;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--sbl-primary) 0%, var(--sbl-secondary) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 2.5rem;
            box-shadow: 0 8px 25px rgba(13, 87, 90, 0.3);
        }
        
        .welcome-title {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--sbl-primary);
            margin-bottom: 10px;
        }
        
        .welcome-subtitle {
            font-size: 1.1rem;
            color: #6c757d;
            margin-bottom: 25px;
        }
        
        .role-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: white;
            margin-bottom: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .role-description {
            color: #495057;
            font-size: 1rem;
            font-style: italic;
        }
        
        .sections-grid {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .section-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            height: 100%;
            transition: all 0.4s ease;
            cursor: pointer;
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .section-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--sbl-primary) 0%, var(--sbl-secondary) 100%);
        }
        
        .section-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 20px 40px rgba(13, 87, 90, 0.2);
            border-color: var(--sbl-primary);
        }
        
        .section-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--sbl-primary) 0%, var(--sbl-secondary) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 2rem;
            transition: all 0.4s ease;
            box-shadow: 0 8px 20px rgba(13, 87, 90, 0.2);
        }
        
        .section-card:hover .section-icon {
            transform: scale(1.1) rotate(10deg);
            box-shadow: 0 12px 30px rgba(13, 87, 90, 0.4);
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--sbl-primary);
            margin-bottom: 12px;
            text-align: center;
        }
        
        .section-description {
            color: #6c757d;
            font-size: 0.95rem;
            text-align: center;
            line-height: 1.5;
        }
        
        .access-info {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border-left: 5px solid var(--sbl-info);
        }
        
        .logout-section {
            text-align: center;
            margin-top: 50px;
        }
        
        .logout-btn {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        
        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.4);
            color: white;
        }
        
        .stats-row {
            margin-top: 40px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            height: 100%;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--sbl-primary) 0%, var(--sbl-secondary) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: white;
            font-size: 1.5rem;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--sbl-primary);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        /* Responsive Design */
        @media (max-width: 992px) {
            .welcome-title {
                font-size: 1.8rem;
            }
            
            .section-card {
                margin-bottom: 20px;
            }
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 20px 15px;
            }
            
            .welcome-header {
                padding: 25px;
                margin-bottom: 25px;
            }
            
            .welcome-title {
                font-size: 1.6rem;
            }
            
            .user-avatar {
                width: 60px;
                height: 60px;
                font-size: 2rem;
            }
            
            .section-icon {
                width: 60px;
                height: 60px;
                font-size: 1.8rem;
            }
        }
        
        /* Animaciones */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .section-card {
            animation: fadeInUp 0.6s ease-out;
        }
        
        .section-card:nth-child(even) {
            animation-delay: 0.1s;
        }
        
        .section-card:nth-child(3n) {
            animation-delay: 0.2s;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header de Bienvenida -->
        <div class="welcome-header">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="welcome-title">¡Bienvenido, <?= htmlspecialchars($nombre) ?>!</div>
            <div class="welcome-subtitle">
                Usuario: <strong><?= htmlspecialchars($usuario_login) ?></strong>
            </div>
            <div class="role-badge" style="background-color: <?= $currentRoleInfo['color'] ?>;">
                <?= $currentRoleInfo['badge'] ?>
            </div>
            <div class="role-description">
                <?= $currentRoleInfo['description'] ?>
            </div>
        </div>

        <!-- Información de Acceso -->
        <div class="access-info">
            <h5><i class="fas fa-info-circle me-2"></i>Selecciona una Sección</h5>
            <p class="mb-0">
                Tienes acceso a <strong><?= count($availableSections) ?> secciones</strong> del sistema según tu rol. 
                Haz clic en cualquiera de las siguientes opciones para acceder.
            </p>
        </div>

        <!-- Grid de Secciones -->
        <div class="sections-grid">
            <div class="row g-4">
                <?php foreach ($availableSections as $key => $section): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="section-card" onclick="navigateToSection('<?= htmlspecialchars($section['url']) ?>', '<?= htmlspecialchars($section['label']) ?>')">
                        <div class="section-icon">
                            <i class="fas <?= htmlspecialchars($section['icon']) ?>"></i>
                        </div>
                        <div class="section-title"><?= htmlspecialchars($section['label']) ?></div>
                        <div class="section-description"><?= htmlspecialchars($section['description']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="stats-row">
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="stat-number"><?= count($availableSections) ?></div>
                        <div class="stat-label">Secciones Disponibles</div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-number" id="sessionTime">00:00</div>
                        <div class="stat-label">Tiempo de Sesión</div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="stat-number">ISO</div>
                        <div class="stat-label">Sistema Certificado</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botón de Logout -->
        <div class="logout-section">
            <a href="backend/usuarios/logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt me-2"></i>
                Cerrar Sesión
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para navegar a las secciones
        function navigateToSection(url, sectionName) {
            // Mostrar notificación de acceso
            showAccessNotification(sectionName);
            
            // Redirigir después de un breve delay para mostrar la notificación
            setTimeout(() => {
                if (url.startsWith('http')) {
                    window.location.href = url;
                } else {
                    window.location.href = BASE_URL + '/' + url;
                }
            }, 800);
        }

        // Mostrar notificación de acceso
        function showAccessNotification(sectionName) {
            const notification = document.createElement('div');
            notification.className = 'alert alert-success alert-dismissible fade show position-fixed';
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            notification.innerHTML = `
                <i class="fas fa-arrow-right me-2"></i>
                Accediendo a ${sectionName}...
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 3000);
        }

        // Contador de tiempo de sesión
        function updateSessionTime() {
            const startTime = sessionStorage.getItem('sessionStartTime');
            if (!startTime) {
                sessionStorage.setItem('sessionStartTime', Date.now());
                return;
            }
            
            const elapsed = Date.now() - parseInt(startTime);
            const minutes = Math.floor(elapsed / 60000);
            const seconds = Math.floor((elapsed % 60000) / 1000);
            
            document.getElementById('sessionTime').textContent = 
                `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }

        // Actualizar el tiempo cada segundo
        setInterval(updateSessionTime, 1000);
        updateSessionTime();

        // Efectos de hover mejorados
        document.querySelectorAll('.section-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-10px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            console.log('SBL Dashboard cargado correctamente');
            console.log('Secciones disponibles: <?= count($availableSections) ?>');
            console.log('Rol del usuario: <?= htmlspecialchars($rol) ?>');
        });
    </script>
</body>
</html>