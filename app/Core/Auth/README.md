# Sistema de Autenticación Centralizado - SBL Sistema Interno

## 🚀 Descripción

Sistema de autenticación y autorización centralizado desarrollado para el SBL Sistema Interno. Proporciona una arquitectura modular y extensible basada en interfaces, con soporte completo para Role-Based Access Control (RBAC), gestión de sesiones, auditoría y APIs REST.

## ✨ Características Principales

### 🔐 Autenticación
- Login/logout seguro con validación de credenciales
- Soporte para hashes bcrypt y compatibilidad con MD5 legacy
- Bloqueo automático de cuentas tras intentos fallidos
- Gestión de tokens de recuperación de contraseña
- Validación de políticas de contraseñas

### 👥 Gestión de Roles y Permisos (RBAC)
- Sistema de roles jerárquico
- Permisos granulares con soporte para wildcards
- Asignación temporal de roles y permisos
- Contexto específico para permisos (por empresa, proyecto, etc.)
- Cache de permisos para optimización

### 🛡️ Gestión de Sesiones
- Sesiones seguras con validación de IP opcional
- Límite de sesiones concurrentes por usuario
- Renovación automática de sesiones
- Limpieza automática de sesiones expiradas
- Metadatos de sesión personalizables

### 📊 Auditoría y Logging
- Logging completo de eventos de autenticación
- Registro de intentos no autorizados
- Seguimiento de cambios de roles y permisos
- Rotación automática de archivos de log
- Diferentes niveles de logging (DEBUG, INFO, WARNING, ERROR, CRITICAL)

### 🌐 API REST
- Endpoints RESTful para todas las operaciones
- Autenticación basada en sesiones
- Middleware de autorización integrado
- Respuestas JSON estructuradas
- Manejo de errores consistente

## 🏗️ Arquitectura

### Componentes Principales

```
AuthManager (Controlador principal)
├── AuthProviderInterface (Autenticación)
│   └── DatabaseAuthProvider
├── RoleManagerInterface (Gestión de roles)
│   └── DatabaseRoleManager
├── PermissionManagerInterface (Gestión de permisos)
│   └── DatabasePermissionManager
├── SessionManagerInterface (Gestión de sesiones)
│   └── DatabaseSessionManager
└── AuthLogger (Sistema de logs)
```

### Interfaces y Contratos

- **AuthProviderInterface**: Contratos para proveedores de autenticación
- **RoleManagerInterface**: Contratos para gestión de roles
- **PermissionManagerInterface**: Contratos para gestión de permisos
- **SessionManagerInterface**: Contratos para gestión de sesiones

## 📦 Instalación

### 1. Instalación de Base de Datos

```bash
# Ejecutar script de instalación
php app/Core/Auth/install_auth_system.php
```

Este script creará:
- Tablas necesarias para el sistema
- Roles y permisos por defecto
- Configuración inicial
- Índices optimizados

### 2. Configuración

```php
// Cargar configuración personalizada
$config = [
    'database' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'database' => 'sbl_sistema_interno',
        'username' => 'usuario',
        'password' => 'contraseña'
    ],
    'auth' => [
        'max_attempts' => 5,
        'lockout_time' => 30,
        'password_expiry_days' => 90
    ],
    'sessions' => [
        'lifetime' => 7200,
        'max_per_user' => 5
    ]
];
```

## 🔧 Uso Básico

### Inicialización

```php
use App\Core\Auth\AuthFactory;

// Crear sistema de autenticación
$authSystem = AuthFactory::createAuthSystem($config);
$auth = $authSystem['auth'];
$middleware = $authSystem['middleware'];

// O usar funciones helper globales
AuthFactory::createGlobalHelpers();
```

### Autenticación

```php
// Login
$result = $auth->login('usuario', 'contraseña');
if ($result['success']) {
    echo "Login exitoso: " . $result['user']['nombre'];
} else {
    echo "Error: " . $result['message'];
}

// Verificar autenticación
if ($auth->isAuthenticated()) {
    $user = $auth->getCurrentUser();
    echo "Usuario actual: " . $user['nombre'];
}

// Logout
$auth->logout();
```

### Verificación de Permisos

```php
// Verificar permiso específico
if ($auth->hasPermission('calibrations.create')) {
    echo "Puede crear calibraciones";
}

// Verificar con contexto
if ($auth->hasPermission('users.view', ['empresa_id' => 123])) {
    echo "Puede ver usuarios de la empresa 123";
}

// Verificar rol
if ($auth->hasRole('admin')) {
    echo "Es administrador";
}
```

### Gestión de Roles

```php
// Crear rol
$auth->roleManager->createRole('operador_laboratorio', 'Operador de laboratorio');

// Asignar rol a usuario
$auth->roleManager->assignRole($userId, 'operador_laboratorio');

// Obtener roles de usuario
$roles = $auth->roleManager->getUserRoles($userId);
```

### Gestión de Permisos

```php
// Crear permiso
$auth->permissionManager->createPermission(
    'equipment.calibrate',
    'Calibrar equipos',
    'equipment'
);

// Otorgar permiso directo
$auth->permissionManager->grantPermission($userId, 'equipment.calibrate');

// Otorgar permiso temporal
$auth->permissionManager->grantTemporaryPermission(
    $userId,
    'reports.export',
    new DateTime('+7 days')
);
```

## 🛡️ Middleware de Autorización

### Uso Básico

```php
// Verificar autenticación
$authMiddleware = $middleware->authenticate();

// Verificar permiso específico
$permissionMiddleware = $middleware->requirePermission('users.create');

// Verificar rol
$roleMiddleware = $middleware->requireRole('admin');

// Verificar múltiples permisos (cualquiera)
$anyPermissionMiddleware = $middleware->requireAnyPermission([
    'reports.view',
    'reports.create'
]);
```

### Middleware Avanzado

```php
// Limitación por IP
$ipMiddleware = $middleware->requireIpWhitelist(['192.168.1.0/24']);

// Rate limiting
$rateLimitMiddleware = $middleware->rateLimit(100, 3600); // 100 requests/hour

// Condición personalizada
$customMiddleware = $middleware->requireCondition(function($user, $request) {
    return $user['empresa_id'] === 123;
}, 'company_access_denied');
```

## 🌐 API REST

### Endpoints Disponibles

#### Autenticación
- `POST /api/auth/login` - Iniciar sesión
- `POST /api/auth/logout` - Cerrar sesión
- `GET /api/auth/user` - Obtener usuario actual
- `GET /api/auth/check` - Verificar autenticación

#### Roles
- `GET /api/auth/roles` - Listar todos los roles
- `POST /api/auth/roles` - Crear nuevo rol
- `GET /api/auth/roles/{id}` - Obtener detalles de rol
- `PUT /api/auth/roles/{id}` - Actualizar rol
- `DELETE /api/auth/roles/{id}` - Eliminar rol

#### Permisos
- `GET /api/auth/permissions` - Listar todos los permisos
- `POST /api/auth/permissions` - Crear nuevo permiso
- `GET /api/auth/permissions/{id}` - Obtener detalles de permiso

#### Usuarios
- `POST /api/auth/users/assign-role` - Asignar rol a usuario
- `POST /api/auth/users/grant-permission` - Otorgar permiso a usuario
- `GET /api/auth/users/{id}/roles` - Obtener roles de usuario
- `GET /api/auth/users/{id}/permissions` - Obtener permisos de usuario
- `GET /api/auth/users/{id}/sessions` - Obtener sesiones de usuario

### Ejemplos de Uso

```javascript
// Login
fetch('/api/auth/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        username: 'admin',
        password: 'contraseña'
    })
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        console.log('Login exitoso:', data.data.user);
    }
});

// Crear rol
fetch('/api/auth/roles', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        name: 'quality_manager',
        description: 'Gerente de Calidad',
        permissions: ['quality.view', 'quality.edit', 'reports.view']
    })
});
```

## 📊 Configuración Avanzada

### Configuración de Logging

```php
'logging' => [
    'enabled' => true,
    'level' => 'INFO', // DEBUG, INFO, WARNING, ERROR, CRITICAL
    'log_path' => '/path/to/logs/auth.log',
    'format' => 'structured', // simple, structured, json
    'rotate_logs' => true,
    'max_file_size' => 10485760, // 10MB
    'max_backup_files' => 5,
    'database_logging' => false,
    'alert_on_critical' => false
]
```

### Configuración de Sesiones

```php
'sessions' => [
    'lifetime' => 7200, // 2 horas
    'max_inactive_time' => 1800, // 30 minutos
    'check_ip' => false,
    'max_per_user' => 5,
    'cleanup_interval' => 3600,
    'cookie_secure' => true,
    'cookie_httponly' => true
]
```

### Configuración de Contraseñas

```php
'password' => [
    'min_length' => 8,
    'require_complexity' => true,
    'expiry_days' => 90,
    'history_count' => 5,
    'allow_legacy_hashes' => true
]
```

## 🔒 Seguridad

### Mejores Prácticas

1. **Contraseñas**:
   - Usar bcrypt para nuevas contraseñas
   - Migración gradual desde MD5
   - Políticas de complejidad configurables
   - Expiración automática

2. **Sesiones**:
   - IDs de sesión seguros (32 bytes aleatorios)
   - Validación de IP opcional
   - Límites de sesiones concurrentes
   - Limpieza automática de sesiones expiradas

3. **Permisos**:
   - Principio de menor privilegio
   - Contexto específico cuando sea necesario
   - Auditoría completa de cambios
   - Permisos temporales para casos especiales

4. **Logging**:
   - Registro de todos los eventos de seguridad
   - Rotación automática de logs
   - Niveles de logging apropiados
   - Protección de archivos de log

## 🛠️ Mantenimiento

### Tareas Programadas Recomendadas

```bash
# Limpiar sesiones expiradas (cada hora)
0 * * * * php -f /path/to/cleanup_sessions.php

# Limpiar roles y permisos expirados (diariamente)
0 2 * * * php -f /path/to/cleanup_expired_permissions.php

# Rotar logs (semanalmente)
0 3 * * 0 php -f /path/to/rotate_logs.php
```

### Monitoreo

```php
// Obtener estadísticas del sistema
$stats = [
    'users' => $auth->authProvider->getAuthStats(),
    'roles' => $auth->roleManager->getRoleStats(),
    'permissions' => $auth->permissionManager->getPermissionStats(),
    'sessions' => $auth->sessionManager->getSessionStats()
];
```

## 🧪 Testing

### Tests de Ejemplo

```php
// Test de autenticación
$result = $auth->login('testuser', 'testpass');
assert($result['success'] === true);

// Test de permisos
$auth->permissionManager->grantPermission($userId, 'test.permission');
assert($auth->hasPermission('test.permission') === true);

// Test de roles
$auth->roleManager->assignRole($userId, 'test_role');
assert($auth->hasRole('test_role') === true);
```

## 📚 Estructura de Archivos

```
app/Core/Auth/
├── AuthManager.php                 # Controlador principal
├── AuthLogger.php                  # Sistema de logging
├── AuthFactory.php                 # Factory pattern
├── AuthMiddleware.php              # Middleware de autorización
├── install_auth_system.php         # Script de instalación
├── auth_config.php                 # Archivo de configuración
├── Interfaces/
│   ├── AuthProviderInterface.php
│   ├── RoleManagerInterface.php
│   ├── PermissionManagerInterface.php
│   └── SessionManagerInterface.php
├── Providers/
│   └── DatabaseAuthProvider.php
├── Managers/
│   ├── DatabaseRoleManager.php
│   ├── DatabasePermissionManager.php
│   └── DatabaseSessionManager.php
└── Tests/
    ├── AuthManagerTest.php
    ├── RoleManagerTest.php
    └── PermissionManagerTest.php
```

## 🤝 Contribución

1. Fork el proyecto
2. Crear branch para la funcionalidad (`git checkout -b feature/nueva-funcionalidad`)
3. Commit los cambios (`git commit -am 'Agregar nueva funcionalidad'`)
4. Push al branch (`git push origin feature/nueva-funcionalidad`)
5. Crear Pull Request

## 📄 Licencia

Este proyecto está bajo la Licencia MIT. Ver el archivo `LICENSE` para más detalles.

## 📞 Soporte

Para soporte técnico o reportar bugs:
- Email: soporte@sbl.com
- Issues: GitHub Issues
- Documentación: Wiki del proyecto

## 🔄 Changelog

### v1.0.0 (2024-01-XX)
- Implementación inicial del sistema de autenticación
- Soporte completo para RBAC
- API REST completa
- Sistema de logging avanzado
- Middleware de autorización
- Documentación completa

---

**Desarrollado para SBL Sistema Interno** 🚀