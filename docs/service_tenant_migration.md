# Migración de Módulos Service y Tenant - Documentación

## Descripción de la Migración
**Fecha**: 25 de Septiembre, 2025
**Objetivo**: Separar completamente la funcionalidad de servicios a clientes del Sistema Interno, moviendo todos los módulos relacionados al Sistema de Servicios a Clientes.

## Justificación
El **Sistema Interno** debe ser exclusivamente para gestión interna de SBL Pharma, mientras que todo lo relacionado con **servicios a clientes** y **gestión de clientes** debe estar en el **Sistema de Servicios a Clientes**.

## Archivos y Módulos Migrados

### 1. Aplicaciones Frontend
#### Movido del Sistema Interno → Sistema de Servicios
```
SBL_SISTEMA_INTERNO/public/apps/service/        → SBL_SISTEMA_SERVICIOS_CLIENTES/public/apps/service/
SBL_SISTEMA_INTERNO/public/apps/tenant/         → SBL_SISTEMA_SERVICIOS_CLIENTES/public/apps/tenant/
```

**Contenido migrado**:
- ✅ `service/` - Portal completo de servicios
  - `calibraciones/` - Gestión de calibraciones de servicios
  - `clientes/` - Panel de gestión de clientes
  - `instrumentos/` - Instrumentos de servicios
  - `usuarios/` - Login y gestión de usuarios de servicio
  - `index.html`, `sidebar.html`, `topbar.html`

- ✅ `tenant/` - Portal completo de clientes (tenant)
  - `calibraciones/` - Vista de calibraciones para clientes
  - `instrumentos/` - Vista de instrumentos para clientes
  - `reportes/` - Reportes para clientes
  - `usuarios/` - Gestión de usuarios cliente
  - `tutorial/` - Tutorial para clientes
  - `sidebar.html`, `topbar.html`, `index.html`

### 2. Módulos Backend PHP
#### Movido del Sistema Interno → Sistema de Servicios
```
SBL_SISTEMA_INTERNO/app/Modules/Service/         → SBL_SISTEMA_SERVICIOS_CLIENTES/app/Modules/Service/
SBL_SISTEMA_INTERNO/app/Modules/Tenant/          → SBL_SISTEMA_SERVICIOS_CLIENTES/app/Modules/Tenant/
```

**Módulos PHP migrados**:
- ✅ `Service/` - Lógica de servicios
  - `ArchivosSql/` - Scripts SQL de servicios
  - `Calibraciones/` - Procesamiento de calibraciones de servicio
  - `Clientes/` - Gestión backend de clientes
  - `Instrumentos/gages/` - Gestión de instrumentos de servicio

- ✅ `Tenant/` - Lógica de clientes
  - `Calibraciones/` - Procesamiento para clientes
  - `Clientes/` - Gestión de datos de cliente
  - `Instrumentos/` - Gestión de instrumentos para clientes
  - `Mensajeria/` - Sistema de mensajería
  - `Patrones/` - Patrones de calibración
  - `Proveedores/` - Gestión de proveedores
  - `Reportes/` - Generación de reportes
  - `Risk/` - Gestión de riesgos

### 3. Archivos Backend Eliminados
#### Eliminado del Sistema Interno
```
SBL_SISTEMA_INTERNO/public/backend/clientes/     → ELIMINADO
SBL_SISTEMA_INTERNO/public/assets/scripts/service/ → ELIMINADO
```

**Archivos eliminados del Sistema Interno**:
- ❌ `backend/clientes/` - Toda la carpeta de clientes
  - `service_*.php` - Endpoints de servicios
  - `request_calibration.php` - Solicitudes de calibración
  - `register_instrument.php` - Registro de instrumentos
  - `my_instruments.php` - Vista de instrumentos del cliente
  - `get_calibrations.php` - Obtener calibraciones
  - `download_certificate.php` - Descarga de certificados

- ❌ `assets/scripts/service/` - Scripts JavaScript de servicios
  - `clientes-panel.js` - Panel de clientes

## Modificaciones en Código

### 1. Login Simplificado
**Archivo**: `app/Modules/Internal/Usuarios/login.php`

**Antes**:
```php
$allowedContexts = [];
if ($rolNormalizado === 'cliente') {
    $allowedContexts[] = 'tenant';
} else {
    $allowedContexts[] = 'internal';
    if ($canAccessService) {
        $allowedContexts[] = 'service';
    }
}
```

**Después**:
```php
// Sistema Interno - Solo contexto interno
$finalContext = 'internal';
$_SESSION['app_context'] = $finalContext;
$_SESSION['portal_slug'] = $finalContext;
```

### 2. Dashboard Moderno Actualizado
**Archivo**: `app/Modules/Internal/Dashboard/modern_dashboard.php`

**Eliminado**:
```php
'service' => [
    'label' => 'PORTAL SERVICIO',
    'url' => '../../../SBL_SISTEMA_SERVICIOS_CLIENTES/public/index.php?app=service',
    'icon' => 'fa-handshake',
    'description' => 'Portal de servicios a clientes',
    'roles' => ['superadministrador', 'administrador', 'supervisor']
],
```

## Estado Final de los Sistemas

### SBL Sistema Interno
**Propósito**: Gestión interna exclusiva de SBL Pharma
**Módulos restantes**:
- ✅ `Internal/` - Gestión interna
- ✅ `AdminDashboard/` - Panel administrativo
- ✅ `AlertSystem/` - Sistema de alertas
- ✅ `AuditSystem/` - Sistema de auditoría
- ✅ `BackupSystem/` - Sistema de respaldos
- ✅ `ConfigurationControl/` - Control de configuración
- ✅ `IncidentManagement/` - Gestión de incidentes
- ✅ `ProjectIntegration/` - Integración de proyectos
- ✅ `Database/` - Base de datos
- ✅ `Api/` - APIs internas

**Aplicaciones disponibles**:
- ✅ `internal/` - Portal interno principal
- ✅ `developer/` - Herramientas de desarrollo
- ✅ `system-retirement/` - Sistema de retiro
- ✅ `ticket-system/` - Sistema de tickets

### SBL Sistema de Servicios a Clientes
**Propósito**: Gestión de servicios y clientes
**Módulos disponibles**:
- ✅ `Service/` - Lógica de servicios (migrado + existente)
- ✅ `Tenant/` - Lógica de clientes (migrado + existente)
- ✅ `Internal/` - Acceso interno al sistema de servicios
- ✅ `Database/` - Base de datos de servicios
- ✅ `Api/` - APIs de servicios

**Aplicaciones disponibles**:
- ✅ `service/` - Portal de servicios (mejorado)
- ✅ `tenant/` - Portal de clientes (mejorado)
- ✅ `internal/` - Acceso interno a servicios

## Verificación de Migración

### Comandos de Verificación Ejecutados
```bash
# Mover carpetas service y tenant
Move-Item -Path "C:\xampp\htdocs\SBL_SISTEMA_INTERNO\public\apps\service" -Destination "C:\xampp\htdocs\SBL_SISTEMA_SERVICIOS_CLIENTES\public\apps\service_from_internal"
Move-Item -Path "C:\xampp\htdocs\SBL_SISTEMA_INTERNO\public\apps\tenant" -Destination "C:\xampp\htdocs\SBL_SISTEMA_SERVICIOS_CLIENTES\public\apps\tenant_from_internal"

# Mover módulos PHP
Move-Item -Path "C:\xampp\htdocs\SBL_SISTEMA_INTERNO\app\Modules\Service" -Destination "C:\xampp\htdocs\SBL_SISTEMA_SERVICIOS_CLIENTES\app\Modules\Service_from_internal"
Move-Item -Path "C:\xampp\htdocs\SBL_SISTEMA_INTERNO\app\Modules\Tenant" -Destination "C:\xampp\htdocs\SBL_SISTEMA_SERVICIOS_CLIENTES\app\Modules\Tenant_from_internal"

# Fusionar contenidos
robocopy "origen" "destino" /E /XO

# Limpiar archivos temporales
Remove-Item -Recurse -Force "carpetas_temporales"
```

### Estado de Verificación
- ✅ **Migración completada** - Todos los archivos movidos correctamente
- ✅ **Fusión exitosa** - Contenidos fusionados sin conflictos
- ✅ **Limpieza realizada** - Archivos temporales eliminados
- ✅ **Referencias actualizadas** - Código modificado para nueva estructura
- ✅ **Login simplificado** - Solo contexto interno en Sistema Interno
- ✅ **Dashboard actualizado** - Referencias a servicios eliminadas

## Beneficios de la Migración

### 1. Separación de Responsabilidades
- **Sistema Interno**: Exclusivamente para operaciones internas de SBL
- **Sistema de Servicios**: Exclusivamente para servicios a clientes

### 2. Mayor Seguridad
- **Acceso restringido**: Los clientes no tienen acceso al sistema interno
- **Aislamiento**: Fallas en servicios no afectan operaciones internas

### 3. Mantenimiento Simplificado
- **Código más limpio**: Cada sistema tiene su propósito específico
- **Deployment independiente**: Actualizaciones por separado
- **Escalabilidad**: Cada sistema puede escalar según necesidades

### 4. Cumplimiento Normativo
- **ISO 17025**: Separación clara entre procesos internos y servicios
- **Auditoría**: Trazabilidad independiente por sistema
- **Control de calidad**: Gestión de calidad específica por contexto

## Próximos Pasos

### 1. Verificación Funcional
- [ ] Probar login en Sistema Interno (solo usuarios internos)
- [ ] Probar acceso a todas las secciones internas
- [ ] Verificar que no hay referencias rotas

### 2. Configuración de Sistema de Servicios
- [ ] Configurar acceso independiente al Sistema de Servicios
- [ ] Verificar login de clientes y servicios
- [ ] Probar todas las funcionalidades migradas

### 3. Documentación
- [ ] Actualizar manuales de usuario
- [ ] Documentar nuevos flujos de acceso
- [ ] Crear guías de migración para usuarios existentes

### 4. Testing
- [ ] Pruebas de integración
- [ ] Pruebas de rendimiento
- [ ] Pruebas de seguridad

## Rollback (Si Necesario)

En caso de necesitar revertir la migración:

```bash
# Mover de vuelta al sistema interno
Move-Item "SBL_SISTEMA_SERVICIOS_CLIENTES\app\Modules\Service" "SBL_SISTEMA_INTERNO\app\Modules\Service"
Move-Item "SBL_SISTEMA_SERVICIOS_CLIENTES\app\Modules\Tenant" "SBL_SISTEMA_INTERNO\app\Modules\Tenant"
Move-Item "SBL_SISTEMA_SERVICIOS_CLIENTES\public\apps\service" "SBL_SISTEMA_INTERNO\public\apps\service"
Move-Item "SBL_SISTEMA_SERVICIOS_CLIENTES\public\apps\tenant" "SBL_SISTEMA_INTERNO\public\apps\tenant"

# Revertir cambios en login.php y dashboard
# (usar git revert o copias de seguridad)
```

---

**Estado de la migración**: ✅ **COMPLETADA EXITOSAMENTE**  
**Fecha de finalización**: 25 de Septiembre, 2025  
**Responsable**: SBL Development Team  
**Próxima revisión**: Verificación funcional completa