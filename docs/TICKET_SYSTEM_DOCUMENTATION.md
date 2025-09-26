# Sistema de Gestión de Tickets - GAMP 5 & GxP

## Descripción

Sistema completo de gestión de tickets conforme a las normativas GAMP 5 y GxP, diseñado para sistemas computarizados en ambientes regulados. Incluye matriz de riesgo, notificaciones automáticas, reportes de auditoría y funcionalidades de cumplimiento regulatorio.

## Características Principales

### 1. Gestión de Tickets con Matriz de Riesgo
- **Clasificación automática** basada en impacto y probabilidad
- **Niveles de riesgo**: Crítico, Alto, Medio, Bajo, Mínimo
- **Priorización automática** según nivel de riesgo
- **Escalación automática** para tickets de alto riesgo

### 2. Conformidad GAMP 5 y GxP
- **Trazabilidad completa** de todas las acciones
- **Documentación obligatoria** de causa raíz y acciones correctivas
- **Clasificación GxP** (GCP, GLP, GMP, GDP, GVP)
- **Impacto regulatorio** y de validación
- **Retención de registros** por 7 años (configurable)

### 3. Sistema de Notificaciones
- **Alertas automáticas** para tickets de alto riesgo
- **Notificaciones de escalación** basadas en SLA
- **Múltiples canales**: Email, Slack (configurable)
- **Plantillas personalizables** para diferentes tipos de eventos

### 4. Reportes y Análisis
- **Reportes ejecutivos** con KPIs clave
- **Reportes de auditoría** para inspecciones regulatorias
- **Análisis de tendencias** y métricas de rendimiento
- **Exportación a PDF/HTML** para documentación

### 5. Monitoreo SLA
- **Seguimiento automático** de tiempos de resolución
- **Alertas de vencimiento** de SLA
- **Métricas de cumplimiento** en tiempo real
- **Escalación automática** por vencimiento

## Arquitectura del Sistema

```
app/Modules/Internal/TicketSystem/
├── TicketManager.php              # Gestor principal de tickets
├── TicketController.php           # API REST para tickets
├── NotificationSystem.php         # Sistema de notificaciones
├── ReportingSystem.php           # Generación de reportes
└── TicketSystemInitializer.php   # Inicializador del sistema
```

### Base de Datos

#### Tablas Principales:
- **tickets**: Información principal de tickets
- **ticket_history**: Historial de cambios y auditoría
- **ticket_attachments**: Archivos adjuntos
- **risk_assessment_criteria**: Criterios de evaluación de riesgo
- **notification_settings**: Configuración de notificaciones
- **notification_log**: Log de notificaciones enviadas
- **sla_monitoring**: Monitoreo de SLA

## Instalación

### 1. Inicialización Automática

```bash
# Desde línea de comandos
php app/Modules/Internal/TicketSystem/TicketSystemInitializer.php install
```

### 2. Inicialización desde Dashboard

```php
// Desde el SuperAdmin Dashboard
POST /app/Modules/Internal/Developer/SuperadminDashboard.php?action=initialize_ticket_system
```

### 3. Verificación de Instalación

```php
// Generar reporte de instalación
php app/Modules/Internal/TicketSystem/TicketSystemInitializer.php report
```

## Configuración

### Configuraciones Principales

| Configuración | Valor por Defecto | Descripción |
|---------------|-------------------|-------------|
| `ticket_auto_assignment` | true | Asignación automática de tickets |
| `sla_monitoring_enabled` | true | Monitoreo de SLA habilitado |
| `notification_email_enabled` | true | Notificaciones por email |
| `critical_ticket_escalation_hours` | 1 | Horas para escalación crítica |
| `audit_log_retention_days` | 2555 | Retención de logs (7 años) |
| `gxp_compliance_checks` | true | Verificaciones de cumplimiento GxP |

### Matriz de Riesgo

| Impacto/Probabilidad | Mínimo | Bajo | Medio | Alto | Crítico |
|---------------------|--------|------|-------|------|---------|
| **Crítico**         | Alto   | Crítico | Crítico | Crítico | Crítico |
| **Alto**            | Medio  | Alto    | Alto    | Crítico | Crítico |
| **Medio**           | Bajo   | Medio   | Medio   | Alto    | Alto    |
| **Bajo**            | Mínimo | Bajo    | Bajo    | Medio   | Medio   |
| **Mínimo**          | Mínimo | Mínimo  | Bajo    | Bajo    | Medio   |

### Tiempos SLA por Nivel de Riesgo

- **Crítico**: 1 hora
- **Alto**: 4 horas  
- **Medio**: 24 horas
- **Bajo**: 72 horas
- **Mínimo**: 168 horas (1 semana)

## API REST

### Endpoints Principales

#### Crear Ticket
```http
POST /app/Modules/Internal/TicketSystem/TicketController.php?action=create
Content-Type: application/json

{
    "title": "Error crítico en validación",
    "description": "Descripción detallada del problema",
    "category": "CRITICAL_SYSTEM_FAILURE",
    "impact": "Crítico",
    "probability": "Alto", 
    "severity": "Crítico",
    "gxp_classification": "GMP",
    "regulatory_impact": true,
    "validation_impact": false
}
```

#### Listar Tickets
```http
GET /app/Modules/Internal/TicketSystem/TicketController.php?action=list&risk_level=Crítico&status=Abierto
```

#### Actualizar Estado
```http
PUT /app/Modules/Internal/TicketSystem/TicketController.php?action=update&id=123
Content-Type: application/json

{
    "status": "En Progreso",
    "comment": "Iniciando investigación de causa raíz"
}
```

#### Obtener Estadísticas
```http
GET /app/Modules/Internal/TicketSystem/TicketController.php?action=stats
```

## Interfaz de Usuario

### Dashboard Principal
- Métricas clave en tiempo real
- Gráficos de distribución por riesgo
- Lista de tickets críticos
- Estado de cumplimiento SLA

### Formulario de Creación
- Campos obligatorios para trazabilidad GxP
- Calculadora de riesgo en tiempo real
- Validación de datos conforme a normativas
- Carga de archivos adjuntos

### Panel de Gestión
- Filtros avanzados por riesgo, estado, categoría
- Vista de tickets organizados por prioridad
- Funciones de asignación y escalación
- Historial completo de cambios

### Matriz de Riesgo Interactiva
- Visualización de criterios GAMP 5
- Explicación de niveles de riesgo
- Tiempos SLA asociados

## Cumplimiento Regulatorio

### Documentación GxP Requerida

Para tickets con impacto regulatorio:

1. **Causa Raíz**: Análisis detallado del problema
2. **Acciones Correctivas**: Medidas para resolver el problema
3. **Acciones Preventivas**: Medidas para prevenir recurrencia
4. **Clasificación GxP**: Identificación de normativa aplicable
5. **Impacto en Validación**: Evaluación de revalidación necesaria

### Trazabilidad y Auditoría

- **Historial completo** de todos los cambios
- **Identificación de usuarios** para todas las acciones
- **Timestamps precisos** con zona horaria
- **Integridad de datos** garantizada
- **Retención de registros** por período regulatorio

### Reportes de Auditoría

```php
// Generar reporte de auditoría
$reportingSystem = new TicketReportingSystem();
$auditReport = $reportingSystem->generateAuditReport('2024-01-01', '2024-12-31', true);
```

## Notificaciones Automáticas

### Eventos que Generan Notificaciones

1. **Ticket de Alto Riesgo Creado**
2. **Advertencia de Escalación SLA**
3. **Cambio de Estado Importante**
4. **Resolución de Ticket**
5. **Escalación Automática**

### Configuración de Notificaciones

```php
// Configurar notificaciones por usuario
INSERT INTO notification_settings (user_id, notification_type, event_type, enabled) 
VALUES (1, 'email', 'high_priority_created', TRUE);
```

## Monitoreo y Mantenimiento

### Tareas Programadas (Cron)

```bash
# Verificación de escalaciones (cada hora)
0 * * * * php /path/to/tools/scripts/ticket_cron.php

# Limpieza de logs (diario a las 2 AM)
0 2 * * * php /path/to/tools/scripts/ticket_cleanup.php
```

### Verificaciones de Salud

El sistema incluye verificaciones automáticas de:
- Integridad de tablas de base de datos
- Estado del sistema de notificaciones
- Funcionamiento del monitoreo SLA
- Cumplimiento de normativas GxP

### Logs del Sistema

- **ticket_system.log**: Actividades generales
- **notifications.log**: Log de notificaciones
- **system_initialization.log**: Log de instalación

## Métricas y KPIs

### Métricas de Rendimiento
- Tiempo promedio de resolución por nivel de riesgo
- Tasa de cumplimiento SLA
- Número de escalaciones automáticas
- Tickets reabiertos (indicador de calidad)

### Métricas de Cumplimiento GxP
- Porcentaje de tickets con documentación completa
- Tickets con impacto regulatorio resueltos
- Tiempo de respuesta para tickets críticos
- Conformidad con retención de registros

## Seguridad

### Control de Acceso
- Autenticación obligatoria para todas las acciones
- Permisos granulares por tipo de operación
- Registro de todas las actividades de usuario

### Protección de Datos
- Encriptación de datos sensibles
- Protección de directorios con .htaccess
- Validación de entrada para prevenir inyecciones

### Integridad de Auditoría
- Inmutabilidad del historial de tickets
- Checksums para verificar integridad
- Respaldos automáticos de datos críticos

## Solución de Problemas

### Problemas Comunes

1. **Tablas no creadas**: Ejecutar inicializador del sistema
2. **Notificaciones no funcionan**: Verificar configuración SMTP
3. **SLA no monitoreado**: Configurar tareas cron
4. **Permisos insuficientes**: Verificar roles de usuario

### Logs de Diagnóstico

```php
// Verificar salud del sistema
GET /app/Modules/Internal/Developer/SuperadminDashboard.php?action=ticket_system_health
```

### Herramientas de Diagnóstico

```bash
# Verificar instalación
php app/Modules/Internal/TicketSystem/TicketSystemInitializer.php report

# Verificar escalaciones pendientes
php app/Modules/Internal/TicketSystem/NotificationSystem.php
```

## Migración y Actualización

### Respaldo de Datos

Antes de cualquier actualización:

```sql
-- Respaldar datos de tickets
CREATE TABLE tickets_backup AS SELECT * FROM tickets;
CREATE TABLE ticket_history_backup AS SELECT * FROM ticket_history;
```

### Proceso de Actualización

1. Respaldar base de datos actual
2. Ejecutar scripts de migración
3. Verificar integridad de datos
4. Probar funcionalidades críticas

## Soporte y Mantenimiento

### Contacto de Soporte
- **Documentación técnica**: Consultar este archivo
- **Reportes de errores**: Sistema interno de tickets
- **Actualizaciones**: Verificar repositorio del proyecto

### Mantenimiento Preventivo
- **Semanal**: Revisar logs de error
- **Mensual**: Análisis de métricas de rendimiento  
- **Trimestral**: Revisión de cumplimiento GxP
- **Anual**: Auditoría completa del sistema

---

## Información de Versión

- **Versión**: 1.0.0
- **Fecha de Creación**: Septiembre 2025
- **Compatibilidad**: PHP 7.4+, MySQL 5.7+
- **Normativas**: GAMP 5, GxP (GCP, GLP, GMP, GDP, GVP)
- **Estándares**: ISO 27001, 21 CFR Part 11

## Licencia y Cumplimiento

Este sistema ha sido diseñado para cumplir con:
- **GAMP 5**: Good Automated Manufacturing Practice
- **21 CFR Part 11**: Electronic Records and Signatures
- **ICH Q7**: Good Manufacturing Practice Guide
- **ISO 14971**: Risk Management for Medical Devices
- **Data Integrity Guidelines**: FDA, EMA, MHRA

---

*Documento generado automáticamente por el Sistema de Gestión de Tickets v1.0.0*