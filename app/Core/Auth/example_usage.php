<?php

/**
 * Ejemplo de implementación del sistema de autenticación centralizado
 */

require_once __DIR__ . '/AuthFactory.php';

use App\Core\Auth\AuthFactory;

// ========================================
// 1. CONFIGURACIÓN E INICIALIZACIÓN
// ========================================

echo "🚀 Inicializando sistema de autenticación...\n";

try {
    // Configuración personalizada
    $config = [
        'database' => [
            'driver' => 'mysql',
            'host' => 'localhost',
            'database' => 'sbl_sistema_interno',
            'username' => 'root',
            'password' => ''
        ],
        'auth' => [
            'max_attempts' => 5,
            'lockout_time' => 30,
            'password_expiry_days' => 90
        ],
        'sessions' => [
            'lifetime' => 7200, // 2 horas
            'max_per_user' => 3
        ],
        'logging' => [
            'level' => 'INFO',
            'log_path' => __DIR__ . '/../../../storage/logs/auth_example.log'
        ]
    ];

    // Crear sistema de autenticación
    $authSystem = AuthFactory::createAuthSystem($config);
    $auth = $authSystem['auth'];
    $middleware = $authSystem['middleware'];

    echo "✅ Sistema inicializado correctamente\n\n";

} catch (Exception $e) {
    echo "❌ Error al inicializar: " . $e->getMessage() . "\n";
    exit(1);
}

// ========================================
// 2. EJEMPLO DE AUTENTICACIÓN
// ========================================

echo "🔐 Ejemplo de autenticación...\n";

// Simular login (asumiendo que existe un usuario 'admin')
$loginResult = $auth->login('admin', 'admin123');

if ($loginResult['success']) {
    echo "✅ Login exitoso para: " . $loginResult['user']['nombre'] . "\n";
    
    // Verificar autenticación
    if ($auth->isAuthenticated()) {
        $currentUser = $auth->getCurrentUser();
        echo "   Usuario actual: " . $currentUser['nombre'] . " (ID: " . $currentUser['id'] . ")\n";
    }
} else {
    echo "❌ Login fallido: " . $loginResult['message'] . "\n";
}

echo "\n";

// ========================================
// 3. EJEMPLO DE GESTIÓN DE ROLES
// ========================================

echo "👥 Ejemplo de gestión de roles...\n";

try {
    // Crear un rol de ejemplo
    $roleCreated = $auth->roleManager->createRole(
        'calibration_specialist',
        'Especialista en Calibraciones',
        ['calibrations.view', 'calibrations.create', 'calibrations.edit']
    );

    if ($roleCreated) {
        echo "✅ Rol 'calibration_specialist' creado\n";
    }

    // Listar todos los roles
    $allRoles = $auth->roleManager->getAllRoles();
    echo "📋 Roles disponibles: " . count($allRoles) . "\n";
    foreach (array_slice($allRoles, 0, 3) as $role) { // Mostrar solo los primeros 3
        echo "   - " . $role['name'] . ": " . $role['description'] . "\n";
    }

    // Asignar rol a usuario actual (si está autenticado)
    if ($auth->isAuthenticated()) {
        $currentUser = $auth->getCurrentUser();
        $roleAssigned = $auth->roleManager->assignRole($currentUser['id'], 'calibration_specialist');
        
        if ($roleAssigned) {
            echo "✅ Rol asignado al usuario: " . $currentUser['nombre'] . "\n";
        }

        // Verificar roles del usuario
        $userRoles = $auth->roleManager->getUserRoles($currentUser['id']);
        echo "🏷️  Roles del usuario: " . implode(', ', $userRoles) . "\n";
    }

} catch (Exception $e) {
    echo "❌ Error en gestión de roles: " . $e->getMessage() . "\n";
}

echo "\n";

// ========================================
// 4. EJEMPLO DE GESTIÓN DE PERMISOS
// ========================================

echo "🔐 Ejemplo de gestión de permisos...\n";

try {
    // Crear permisos de ejemplo
    $permissions = [
        ['name' => 'equipment.view', 'description' => 'Ver equipos', 'category' => 'equipment'],
        ['name' => 'equipment.calibrate', 'description' => 'Calibrar equipos', 'category' => 'equipment'],
        ['name' => 'reports.advanced', 'description' => 'Reportes avanzados', 'category' => 'reports']
    ];

    foreach ($permissions as $perm) {
        $created = $auth->permissionManager->createPermission($perm['name'], $perm['description'], $perm['category']);
        if ($created) {
            echo "✅ Permiso creado: " . $perm['name'] . "\n";
        }
    }

    // Verificar permisos del usuario actual
    if ($auth->isAuthenticated()) {
        $currentUser = $auth->getCurrentUser();
        $userPermissions = $auth->permissionManager->getUserPermissions($currentUser['id']);
        echo "🔑 Permisos del usuario: " . count($userPermissions) . " permisos\n";
        
        // Mostrar algunos permisos
        foreach (array_slice($userPermissions, 0, 5) as $permission) {
            echo "   - " . $permission . "\n";
        }

        // Verificar permiso específico
        if ($auth->hasPermission('calibrations.view')) {
            echo "✅ Usuario puede ver calibraciones\n";
        } else {
            echo "❌ Usuario NO puede ver calibraciones\n";
        }

        // Otorgar permiso directo temporal
        $tempPermissionGranted = $auth->permissionManager->grantTemporaryPermission(
            $currentUser['id'],
            'reports.advanced',
            new DateTime('+7 days')
        );

        if ($tempPermissionGranted) {
            echo "✅ Permiso temporal otorgado: reports.advanced (7 días)\n";
        }
    }

} catch (Exception $e) {
    echo "❌ Error en gestión de permisos: " . $e->getMessage() . "\n";
}

echo "\n";

// ========================================
// 5. EJEMPLO DE GESTIÓN DE SESIONES
// ========================================

echo "🖥️  Ejemplo de gestión de sesiones...\n";

try {
    if ($auth->isAuthenticated()) {
        $currentUser = $auth->getCurrentUser();
        
        // Obtener sesiones del usuario
        $userSessions = $auth->sessionManager->getUserSessions($currentUser['id']);
        echo "📱 Sesiones activas del usuario: " . count($userSessions) . "\n";
        
        foreach ($userSessions as $session) {
            echo "   - ID: " . substr($session['session_id'], 0, 8) . "... IP: " . $session['ip_address'] . 
                 " (Creada: " . $session['created_at'] . ")\n";
        }

        // Renovar sesión actual
        $sessionRenewed = $auth->renewCurrentSession();
        if ($sessionRenewed) {
            echo "✅ Sesión actual renovada\n";
        }

        // Obtener información de la sesión actual
        $currentSessionInfo = $auth->sessionManager->getSessionInfo(session_id());
        if ($currentSessionInfo) {
            echo "ℹ️  Sesión actual: IP " . $currentSessionInfo['ip_address'] . 
                 ", última actividad: " . $currentSessionInfo['updated_at'] . "\n";
        }
    }

    // Limpiar sesiones expiradas
    $cleanedSessions = $auth->sessionManager->cleanupExpiredSessions();
    if ($cleanedSessions > 0) {
        echo "🧹 Sesiones expiradas limpiadas: " . $cleanedSessions . "\n";
    }

} catch (Exception $e) {
    echo "❌ Error en gestión de sesiones: " . $e->getMessage() . "\n";
}

echo "\n";

// ========================================
// 6. EJEMPLO DE MIDDLEWARE
// ========================================

echo "🛡️  Ejemplo de middleware de autorización...\n";

try {
    // Simulación de middleware para diferentes escenarios
    echo "📝 Simulando verificaciones de middleware...\n";

    // Verificar autenticación
    if ($auth->isAuthenticated()) {
        echo "✅ Middleware de autenticación: PASÓ\n";
    } else {
        echo "❌ Middleware de autenticación: FALLÓ\n";
    }

    // Verificar permiso específico
    if ($auth->hasPermission('calibrations.view')) {
        echo "✅ Middleware de permiso 'calibrations.view': PASÓ\n";
    } else {
        echo "❌ Middleware de permiso 'calibrations.view': FALLÓ\n";
    }

    // Verificar rol
    if ($auth->hasRole('admin') || $auth->hasRole('calibration_specialist')) {
        echo "✅ Middleware de rol (admin/specialist): PASÓ\n";
    } else {
        echo "❌ Middleware de rol (admin/specialist): FALLÓ\n";
    }

    // Ejemplo de uso real del middleware
    $authMiddleware = $middleware->authenticate();
    $permissionMiddleware = $middleware->requirePermission('calibrations.create');
    
    echo "🔧 Middleware creado y listo para usar\n";

} catch (Exception $e) {
    echo "❌ Error en middleware: " . $e->getMessage() . "\n";
}

echo "\n";

// ========================================
// 7. EJEMPLO DE LOGGING Y AUDITORÍA
// ========================================

echo "📊 Ejemplo de logging y auditoría...\n";

try {
    $logger = $auth->getLogger();
    
    // Registrar evento personalizado
    $logger->logSecurityEvent('EXAMPLE_SECURITY_CHECK', [
        'action' => 'system_test',
        'result' => 'success',
        'details' => 'Sistema de autenticación probado exitosamente'
    ]);

    echo "✅ Evento de seguridad registrado\n";

    // Obtener estadísticas del sistema
    $stats = [
        'auth' => $auth->authProvider->getAuthStats(),
        'roles' => $auth->roleManager->getRoleStats(),
        'permissions' => $auth->permissionManager->getPermissionStats(),
        'sessions' => $auth->sessionManager->getSessionStats()
    ];

    echo "📈 Estadísticas del sistema:\n";
    echo "   - Usuarios activos: " . ($stats['auth']['active_users'] ?? 'N/A') . "\n";
    echo "   - Total roles: " . ($stats['roles']['total_roles'] ?? 'N/A') . "\n";  
    echo "   - Total permisos: " . ($stats['permissions']['total_permissions'] ?? 'N/A') . "\n";
    echo "   - Sesiones activas: " . ($stats['sessions']['active_sessions'] ?? 'N/A') . "\n";

} catch (Exception $e) {
    echo "❌ Error en logging: " . $e->getMessage() . "\n";
}

echo "\n";

// ========================================
// 8. EJEMPLO DE FUNCIONES HELPER
// ========================================

echo "🔧 Ejemplo de funciones helper globales...\n";

try {
    // Crear helpers globales
    AuthFactory::createGlobalHelpers();

    echo "✅ Funciones helper creadas\n";
    echo "💡 Ahora puedes usar:\n";
    echo "   - auth() -> obtener instancia de AuthManager\n";
    echo "   - user() -> obtener usuario actual\n";
    echo "   - can('permission') -> verificar permiso\n";
    echo "   - hasRole('role') -> verificar rol\n";

    // Ejemplo de uso de helpers (si están disponibles)
    if (function_exists('user')) {
        $helperUser = user();
        if ($helperUser) {
            echo "🤖 Helper user(): " . $helperUser['nombre'] . "\n";
        }
    }

} catch (Exception $e) {
    echo "❌ Error creando helpers: " . $e->getMessage() . "\n";
}

echo "\n";

// ========================================
// 9. LIMPIEZA Y LOGOUT
// ========================================

echo "🧹 Limpieza y logout...\n";

try {
    // Limpiar permisos y roles expirados
    $expiredRoles = $auth->roleManager->cleanupExpiredRoles();
    $expiredPermissions = $auth->permissionManager->cleanupExpiredPermissions();
    
    if ($expiredRoles > 0 || $expiredPermissions > 0) {
        echo "🧹 Limpieza realizada: $expiredRoles roles, $expiredPermissions permisos\n";
    }

    // Logout del usuario actual
    if ($auth->isAuthenticated()) {
        $logoutResult = $auth->logout();
        if ($logoutResult['success']) {
            echo "✅ Logout exitoso\n";
        } else {
            echo "❌ Error en logout: " . $logoutResult['message'] . "\n";
        }
    }

} catch (Exception $e) {
    echo "❌ Error en limpieza: " . $e->getMessage() . "\n";
}

// ========================================
// 10. INFORMACIÓN DEL SISTEMA
// ========================================

echo "\n📋 Información del sistema:\n";

try {
    $systemInfo = AuthFactory::getSystemInfo();
    
    echo "🔢 Versión: " . $systemInfo['version'] . "\n";
    echo "🧩 Componentes: " . count($systemInfo['components']) . " disponibles\n";
    echo "✨ Características: " . count($systemInfo['features']) . " implementadas\n";
    
    echo "\n🎯 Características principales:\n";
    foreach (array_slice($systemInfo['features'], 0, 5) as $feature) {
        echo "   • " . $feature . "\n";
    }

} catch (Exception $e) {
    echo "❌ Error obteniendo información: " . $e->getMessage() . "\n";
}

echo "\n🎉 ¡Ejemplo completado exitosamente!\n";
echo "📝 Revisa los logs en: " . ($config['logging']['log_path'] ?? 'storage/logs/auth.log') . "\n";
echo "🔗 Para más información, consulta el README.md\n\n";

?>