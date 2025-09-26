# Dashboard Moderno con Control de Acceso por Roles - Documentación

## Descripción General
Se ha implementado un **Dashboard Moderno** que reemplaza el menú básico después del login, proporcionando una interfaz visual moderna con control de acceso granular basado en roles de usuario.

## Implementación Realizada

### 1. Archivos Creados/Modificados

#### Dashboard Principal
- **`app/Modules/Internal/Dashboard/modern_dashboard.php`**: Lógica principal del dashboard moderno
- **`public/backend/dashboard/modern_dashboard.php`**: Punto de acceso público
- **`app/Modules/Internal/Usuarios/login.php`**: Modificado para redirigir al dashboard moderno

#### Archivos de Verificación y Test
- **`tools/verify_dashboard_routes.php`**: Script de verificación de rutas
- **`public/test_modern_dashboard.html`**: Página de prueba y documentación

### 2. Características Implementadas

#### Control de Acceso por Roles
```php
// Configuración de secciones por rol
$sectionsConfig = [
    'dashboard' => [..., 'roles' => ['superadministrador', 'administrador', 'supervisor', 'operador', 'lector', 'cliente']],
    'instrumentos' => [..., 'roles' => ['superadministrador', 'administrador', 'supervisor', 'operador']],
    'planeacion' => [..., 'roles' => ['superadministrador', 'administrador', 'supervisor']],
    // ... más configuraciones
];
```

#### Niveles de Acceso por Rol

| Rol | Secciones Disponibles | Total |
|-----|----------------------|-------|
| **Superadministrador** | Todas las secciones + Developer | 12 |
| **Administrador** | Gestión completa sin Developer | 10 |
| **Supervisor** | Supervisión y planificación | 7 |
| **Operador** | Operaciones de calibración | 5 |
| **Lector** | Solo lectura | 4 |
| **Cliente** | Acceso básico | 3 |

### 3. Secciones Disponibles

#### Para Superadministrador
- ✅ Dashboard Principal
- ✅ Instrumentos
- ✅ Planeación  
- ✅ Calibraciones
- ✅ Reportes
- ✅ Usuarios
- ✅ Tokens API
- ✅ Calidad
- ✅ GAMP5
- ✅ Developer
- ✅ Portal Servicio
- ✅ Administración
- ✅ Centro de Ayuda

#### Para Administrador
- ✅ Dashboard Principal
- ✅ Instrumentos
- ✅ Planeación
- ✅ Calibraciones
- ✅ Reportes
- ✅ Usuarios
- ✅ Tokens API
- ✅ Calidad
- ✅ GAMP5
- ✅ Portal Servicio
- ✅ Administración
- ✅ Centro de Ayuda

#### Para Supervisor
- ✅ Dashboard Principal
- ✅ Instrumentos (solo lectura)
- ✅ Planeación
- ✅ Calibraciones
- ✅ Reportes
- ✅ Calidad
- ✅ Portal Servicio
- ✅ Centro de Ayuda

#### Para Operador
- ✅ Dashboard Principal
- ✅ Instrumentos (solo lectura)
- ✅ Calibraciones (mis calibraciones)
- ✅ Reportes (mis reportes)
- ✅ Centro de Ayuda

#### Para Lector
- ✅ Dashboard Principal
- ✅ Calibraciones (solo lectura)
- ✅ Reportes (solo lectura)
- ✅ Centro de Ayuda

#### Para Cliente
- ✅ Dashboard Principal
- ✅ Calibraciones (mis calibraciones)
- ✅ Centro de Ayuda

## Interfaz Visual

### Diseño Moderno
- **Fondo degradado**: Linear gradient del tema SBL
- **Tarjetas de secciones**: Cards con efectos hover y animaciones
- **Iconografía Font Awesome**: Iconos específicos para cada sección
- **Tipografía Montserrat**: Fuente corporativa consistente
- **Colores corporativos**: Palette SBL Pharma

### Características Visuales
- **Header personalizado**: Avatar de usuario, rol badge, información de sesión
- **Grid responsivo**: Bootstrap 5.3.0 con adaptación móvil
- **Efectos de hover**: Elevación y escalado de tarjetas
- **Animaciones**: fadeInUp staggered para elementos
- **Contador de sesión**: Tiempo real de sesión activa
- **Notificaciones**: Feedback visual al acceder a secciones

### Responsividad
- **Desktop** (>992px): Grid de 3 columnas
- **Tablet** (768-992px): Grid de 2 columnas  
- **Mobile** (<768px): Grid de 1 columna con adaptaciones específicas

## Configuración de Rutas

### Rutas Principales
```php
'dashboard' => 'apps/internal/index.html',
'instrumentos' => 'apps/internal/instrumentos/list_gages.html',
'planeacion' => 'apps/internal/planeacion/list_planning.html',
'calibraciones' => 'apps/internal/calibraciones/list_calibrations.html',
'reportes' => 'apps/internal/reportes/reports.html',
'usuarios' => 'apps/internal/usuarios/list_users.html',
'api_tokens' => 'apps/internal/configuracion/api_tokens.html',
'calidad' => 'apps/internal/calidad/index.html',
'gamp5' => 'gamp5_dashboard.html',
'developer' => 'apps/internal/developer/selector.html',
'admin' => 'admin_dashboard.html',
'ayuda' => 'apps/internal/ayuda/help_center.html'
```

### Rutas Especiales
- **Portal Servicio**: Redirection al sistema de servicios
- **Logout**: `backend/usuarios/logout.php`

## Flujo de Usuario

### 1. Proceso de Login
```
Usuario ingresa credenciales
    ↓
Validación en login.php
    ↓  
Redirect a modern_dashboard.php
    ↓
Carga dashboard según rol
    ↓
Usuario selecciona sección
    ↓
Redirect a sección específica
```

### 2. Control de Acceso
```php
// Filtrado automático por rol
foreach ($sectionsConfig as $key => $section) {
    $rolesPermitidos = array_map('strtolower', $section['roles']);
    if (in_array($rolNormalizado, $rolesPermitidos)) {
        $availableSections[$key] = $section;
    }
}
```

## JavaScript Funcionalidades

### Navegación Inteligente
```javascript
function navigateToSection(url, sectionName) {
    showAccessNotification(sectionName);
    setTimeout(() => {
        window.location.href = BASE_URL + '/' + url;
    }, 800);
}
```

### Características Interactivas
- **Notificaciones de acceso**: Toast messages al cambiar de sección
- **Contador de sesión**: Tiempo real desde el login
- **Efectos hover**: Interacciones visuales avanzadas
- **Responsive sidebar**: (cuando aplicable)

## Seguridad Implementada

### Validación de Sesión
- ✅ Verificación de sesión activa
- ✅ Control de roles mediante `ensure_portal_access()`
- ✅ Validación de permisos por sección
- ✅ Logout seguro

### Control de Acceso
- ✅ Filtrado de secciones por rol
- ✅ URLs validadas antes de mostrar
- ✅ Redirecciones seguras con BASE_URL
- ✅ Protección contra acceso directo no autorizado

## Testing y Verificación

### Script de Verificación
```bash
php tools/verify_dashboard_routes.php
```

### Página de Test
- **URL**: `/test_modern_dashboard.html`
- **Funcionalidad**: Verificación visual de implementación
- **Tests incluidos**: Enlaces, responsive, roles, características

## Personalización

### Colores Corporativos
```css
:root {
    --sbl-primary: #0d575a;
    --sbl-secondary: #217b9b;
    --sbl-success: #28a745;
    --sbl-warning: #ffc107;
    --sbl-danger: #dc3545;
}
```

### Modificación de Roles
Para agregar/modificar roles, editar:
```php
// En modern_dashboard.php
$roleInfo = [
    'nuevo_rol' => [
        'color' => '#color_hex',
        'badge' => 'ETIQUETA',
        'description' => 'Descripción del rol'
    ]
];
```

### Agregar Nuevas Secciones
```php
'nueva_seccion' => [
    'label' => 'ETIQUETA',
    'url' => 'ruta/archivo.html',
    'icon' => 'fa-icon-name',
    'description' => 'Descripción',
    'roles' => ['rol1', 'rol2']
]
```

## Mantenimiento

### Archivos a Monitorear
1. **modern_dashboard.php**: Lógica principal
2. **login.php**: Redirecciones
3. **auth.php**: Sistema de autenticación
4. **permissions.php**: Control de permisos

### Logs y Debugging
- **Error logs**: Revisar logs de PHP para errores de redirección
- **Session debugging**: Variables `$_SESSION` para debugging
- **Route verification**: Usar script de verificación regularmente

## Próximas Mejoras

### Fase 2 - Funcionalidades Avanzadas
- [ ] Dashboard personalizable por usuario
- [ ] Favoritos y accesos rápidos
- [ ] Historial de navegación
- [ ] Búsqueda de secciones
- [ ] Notificaciones push
- [ ] Métricas de uso

### Fase 3 - Analytics y Reporting
- [ ] Tracking de uso por sección
- [ ] Reportes de acceso por rol
- [ ] Optimización basada en patrones de uso
- [ ] Dashboard de administración de usuarios

---

**Fecha de implementación**: 25 de Septiembre, 2025  
**Versión**: 1.0  
**Estado**: ✅ COMPLETADO Y FUNCIONAL  
**Desarrollador**: SBL Development Team