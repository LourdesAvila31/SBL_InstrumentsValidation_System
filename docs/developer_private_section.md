# Documentación - Sistema Developer Private Section

## Descripción General

El **Sistema Developer Private Section** es una implementación completa de una sección privada para desarrolladores que proporciona acceso controlado a información crítica del sistema, supervisión de incidencias, gestión de cambios, administración de configuraciones y validación de documentación operativa.

## Arquitectura del Sistema

### Componentes Principales

1. **Autenticación y Autorización (RBAC)**
   - Control de acceso basado en roles
   - Validación de permisos específicos para desarrolladores
   - Registro de actividades y auditoría

2. **Dashboard en Tiempo Real**
   - Monitoreo de KPIs del sistema
   - Visualizaciones interactivas con Chart.js
   - Métricas de rendimiento, seguridad y cumplimiento

3. **Gestión de Incidencias y Cambios**
   - Sistema completo de tracking de incidencias
   - Workflow de solicitudes de cambio con aprobación
   - Historial completo de actividades

4. **Gestión de Documentación**
   - Control de versiones para SOP, AppCare, Handover
   - Workflow de revisión y aprobación
   - Control de expiración y renovación

5. **Gestión de Proveedores**
   - Administración de contratos y SLAs
   - Monitoreo de cumplimiento de acuerdos
   - Auditorías y evaluaciones

6. **Sistema de Alertas Automáticas**
   - Reglas configurables de alertas
   - Múltiples canales de notificación
   - Escalamiento automático

## Estructura de Archivos

```
SBL_SISTEMA_INTERNO/
├── app/
│   └── Modules/
│       └── Internal/
│           └── Developer/
│               ├── DeveloperAuth.php
│               ├── DeveloperDashboard.php
│               ├── IncidentChangeManager.php
│               ├── DocumentationManager.php
│               ├── VendorManager.php
│               └── AlertManager.php
├── public/
│   ├── apps/
│   │   └── developer/
│   │       └── index.html
│   └── backend/
│       └── developer/
│           ├── dashboard.php
│           ├── incidents.php
│           ├── documents.php
│           ├── vendors.php
│           └── alerts.php
├── storage/
│   └── developer/
│       ├── config.json
│       ├── logs/
│       ├── documents/
│       ├── exports/
│       └── backups/
└── tools/
    └── scripts/
        ├── developer_private_section_schema.sql
        └── setup_developer_system.py
```

## Instalación y Configuración

### Prerequisitos

- PHP 7.4 o superior
- MySQL 5.7 o superior
- Servidor web (Apache/Nginx)
- Extensiones PHP: mysqli, json, curl
- Python 3.7+ (opcional, para scripts de configuración)

### Pasos de Instalación

1. **Ejecutar Script SQL**
   ```sql
   mysql -u root -p sbl_sistema_interno < tools/scripts/developer_private_section_schema.sql
   ```

2. **Configurar Permisos de Usuario**
   - Asignar rol "Developer" al usuario
   - Verificar permisos específicos (201-209)

3. **Ejecutar Script de Configuración**
   ```bash
   python tools/scripts/setup_developer_system.py install
   ```

4. **Verificar Instalación**
   ```bash
   python tools/scripts/setup_developer_system.py validate
   ```

### Configuración de Base de Datos

El script SQL crea las siguientes tablas principales:

- `incidents` - Gestión de incidencias
- `change_requests` - Solicitudes de cambio
- `incident_history` / `change_history` - Historial de actividades
- `documents` - Documentación técnica
- `document_history` - Control de versiones
- `vendors` - Proveedores
- `vendor_contracts` - Contratos
- `vendor_slas` - Acuerdos de nivel de servicio
- `sla_metrics` - Métricas de SLA
- `alert_rules` - Reglas de alertas
- `alerts` - Alertas generadas
- `system_health_checks` - Monitoreo del sistema
- `api_performance_logs` - Logs de rendimiento
- `system_resources` - Recursos del sistema
- `security_events` - Eventos de seguridad
- `compliance_audits` - Auditorías de cumplimiento

## Funcionalidades

### 1. Dashboard en Tiempo Real

**Características:**
- KPIs en tiempo real del sistema
- Gráficos interactivos de rendimiento
- Métricas de incidencias y alertas
- Monitoreo de recursos del sistema

**APIs:**
- `GET /backend/developer/dashboard.php` - Datos del dashboard
- Actualización automática cada 30 segundos

### 2. Gestión de Incidencias

**Características:**
- Creación y seguimiento de incidencias
- Clasificación por severidad y categoría
- Asignación de responsables
- Historial completo de cambios

**APIs:**
- `GET /backend/developer/incidents.php` - Listar incidencias
- `POST /backend/developer/incidents.php` - Crear incidencia
- `PUT /backend/developer/incidents.php` - Actualizar incidencia
- `DELETE /backend/developer/incidents.php` - Eliminar incidencia

### 3. Gestión de Documentación

**Características:**
- Control de versiones automático
- Workflow de revisión y aprobación
- Gestión de expiración de documentos
- Búsqueda de texto completo

**Tipos de Documento:**
- SOP (Standard Operating Procedures)
- AppCare (Documentación de aplicaciones)
- Handover (Documentos de transferencia)
- Manuales técnicos
- Políticas

**APIs:**
- `GET /backend/developer/documents.php` - Listar documentos
- `POST /backend/developer/documents.php` - Crear documento
- `PUT /backend/developer/documents.php` - Actualizar documento

### 4. Gestión de Proveedores

**Características:**
- Registro completo de proveedores
- Gestión de contratos y SLAs
- Monitoreo de cumplimiento
- Auditorías programadas

**APIs:**
- `GET /backend/developer/vendors.php` - Listar proveedores
- `POST /backend/developer/vendors.php` - Crear proveedor
- `PUT /backend/developer/vendors.php` - Actualizar proveedor

### 5. Sistema de Alertas

**Características:**
- Reglas configurables de alertas
- Múltiples canales de notificación
- Escalamiento automático
- Supresión de alertas duplicadas

**Canales Soportados:**
- Email
- SMS
- Slack
- Webhooks personalizados

## Seguridad

### Control de Acceso

- Autenticación RBAC integrada
- Verificación de permisos por endpoint
- Logging de todas las actividades
- Protección contra ataques comunes

### Permisos Específicos

| ID | Permiso | Descripción |
|----|---------|-------------|
| 201 | developer_dashboard_access | Acceso al dashboard privado |
| 202 | developer_incidents_manage | Gestionar incidencias |
| 203 | developer_changes_manage | Gestionar cambios |
| 204 | developer_documents_manage | Gestionar documentación |
| 205 | developer_vendors_manage | Gestionar proveedores |
| 206 | developer_alerts_manage | Gestionar alertas |
| 207 | developer_monitoring_access | Monitoreo avanzado |
| 208 | developer_audit_access | Acceso a auditoría |
| 209 | developer_security_events | Eventos de seguridad |

### Auditoría

- Registro completo de actividades
- Trazabilidad de cambios
- Logs de acceso y operaciones
- Retención configurable de logs

## API Reference

### Autenticación

Todas las APIs requieren autenticación y verifican permisos específicos:

```php
// Verificación automática en cada endpoint
$auth = new DeveloperAuth($db);
if (!$auth->isDeveloper($_SESSION['usuario_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}
```

### Estructura de Respuesta

```json
{
    "success": true,
    "data": { ... },
    "message": "Operation completed successfully",
    "timestamp": "2024-01-15T10:30:00Z"
}
```

### Manejo de Errores

```json
{
    "success": false,
    "error": "Error description",
    "code": "ERROR_CODE",
    "timestamp": "2024-01-15T10:30:00Z"
}
```

## Monitoreo y Métricas

### KPIs Principales

1. **Incidencias Abiertas** - Número de incidencias no resueltas
2. **Incidencias Críticas** - Incidencias de alta prioridad
3. **Alertas Activas** - Alertas no resueltas
4. **Documentos por Vencer** - Documentos próximos a expirar
5. **SLAs Incumplidos** - Acuerdos de servicio no cumplidos
6. **Auditorías Vencidas** - Auditorías pendientes

### Métricas de Sistema

- **CPU Usage** - Uso de procesador
- **Memory Usage** - Uso de memoria
- **Disk Usage** - Uso de disco
- **API Response Time** - Tiempo de respuesta de APIs
- **Active Connections** - Conexiones activas

### Alertas Automáticas

El sistema incluye reglas preconfiguradas para:

- Alto uso de recursos del sistema
- Incidencias críticas sin asignar
- Documentos vencidos
- SLAs incumplidos
- Eventos de seguridad
- Fallos de conexión a base de datos

## Mantenimiento

### Tareas Programadas

Se recomienda configurar las siguientes tareas programadas:

1. **Limpieza de Logs** (Semanal)
   ```bash
   php tools/scripts/cleanup_logs.php
   ```

2. **Verificación de Documentos** (Diaria)
   ```bash
   php tools/scripts/check_document_expiry.php
   ```

3. **Monitoreo de SLAs** (Horaria)
   ```bash
   php tools/scripts/monitor_slas.php
   ```

4. **Backup de Configuración** (Diaria)
   ```bash
   php tools/scripts/backup_config.php
   ```

### Actualizaciones

Para actualizar el sistema:

1. Hacer backup de la configuración actual
2. Ejecutar nuevos scripts SQL si es necesario
3. Actualizar archivos PHP
4. Validar funcionamiento con el script de setup
5. Revisar logs de errores

## Troubleshooting

### Problemas Comunes

1. **Error de Permisos**
   - Verificar que el usuario tenga rol "Developer"
   - Comprobar permisos específicos en la tabla `role_permissions`

2. **Conexión a Base de Datos**
   - Verificar configuración en `db_config.php`
   - Comprobar que las tablas existen
   - Revisar logs de MySQL

3. **APIs no Responden**
   - Verificar sintaxis PHP con `php -l`
   - Comprobar logs de Apache/PHP
   - Validar permisos de archivos

4. **Frontend no Carga**
   - Verificar rutas de recursos (CSS, JS)
   - Comprobar consola del navegador
   - Validar endpoints de API

### Logs de Sistema

Los logs se almacenan en:
- `storage/developer/logs/system.log` - Log general del sistema
- `storage/developer/logs/api.log` - Log de llamadas API
- `storage/developer/logs/security.log` - Log de eventos de seguridad
- `storage/developer/logs/alerts.log` - Log de alertas generadas

### Comandos de Diagnóstico

```bash
# Validar componentes
python tools/scripts/setup_developer_system.py validate

# Probar conexión DB
python tools/scripts/setup_developer_system.py dbtest

# Verificar permisos
python tools/scripts/setup_developer_system.py permissions

# Generar datos de prueba
python tools/scripts/setup_developer_system.py testdata
```

## Contacto y Soporte

Para soporte técnico o reportar issues:

1. Revisar logs del sistema
2. Ejecutar diagnósticos automáticos
3. Verificar configuración base
4. Contactar al equipo de desarrollo con información detallada del problema

---

**Versión:** 1.0.0  
**Última actualización:** Enero 2024  
**Compatibilidad:** PHP 7.4+, MySQL 5.7+