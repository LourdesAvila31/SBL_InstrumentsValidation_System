# Sistema de Base de Datos - SBL Sistema Interno

## Descripción General

Este conjunto de archivos SQL proporciona una instalación completa del Sistema Interno de Validación de Instrumentos de SBL Pharma, evitando completamente el uso de `LOAD DATA INFILE` y utilizando únicamente sentencias `INSERT` para la carga de datos.

## Estructura de Archivos

### 📁 SBL_adds/

#### Archivos Principales de Instalación

1. **`00_complete_database_setup.sql`**
   - Configuración básica de base de datos
   - Tablas de portales y dominios
   - Sistema de empresas
   - Sistema completo de roles y permisos
   - Configuración de portales (interno, cliente, servicio)

2. **`01_complete_users_setup.sql`**
   - Tabla completa de usuarios con todos los campos necesarios
   - Usuarios reales del sistema SBL con datos actualizados
   - Sistema de firmas internas
   - Competencias de usuarios
   - Configuración de sesiones y seguridad
   - Tablas de intentos de login y restablecimiento de contraseñas

3. **`02_complete_catalogs_setup.sql`**
   - Departamentos completos de SBL Pharma
   - Catálogo extenso de marcas de instrumentos
   - Modelos organizados por marca
   - Catálogo completo de instrumentos (84 tipos diferentes)
   - Sistema de proveedores con contactos
   - Estados de calidad para instrumentos
   - Patrones de referencia para calibración

4. **`03_complete_instruments_calibration_setup.sql`**
   - Tabla principal de instrumentos con todos los campos
   - Sistema completo de calibraciones
   - Calibradores y patrones de trabajo
   - Lecturas de calibración detalladas
   - Sistema de no conformidades
   - Certificados y trazabilidad
   - Plan de riesgos por instrumento
   - Sistema de órdenes de calibración
   - Planes de calibración anuales
   - Sistema de notificaciones automáticas

5. **`04_complete_audit_api_systems_setup.sql`**
   - Sistema completo de auditoría (audit_trail)
   - Sistema de API con clientes y tokens
   - Sistema de reportes personalizados
   - Plantillas de reportes
   - Sistema de backups
   - Configuraciones del sistema
   - Logs del sistema
   - Gestión de archivos
   - Sistema de notificaciones
   - Tareas programadas

6. **`05_sample_data_insert.sql`**
   - Instrumentos reales con códigos SBL
   - Calibraciones de muestra con datos técnicos
   - Lecturas de calibración realistas
   - Certificados de ejemplo
   - Requerimientos de calibración
   - Plan de riesgos con medidas de control
   - Datos de auditoría representativos
   - Notificaciones de ejemplo

7. **`MASTER_INSTALL.sql`**
   - Script maestro que ejecuta todos los archivos en orden
   - Verificaciones de integridad
   - Creación de índices optimizados
   - Configuraciones finales
   - Resumen completo de la instalación

## Características Principales

### ✅ Totalmente Compatible con phpMyAdmin
- **No utiliza `LOAD DATA INFILE`** en ningún archivo
- Todos los datos se insertan mediante sentencias `INSERT` estándar
- Compatible con restricciones de seguridad de phpMyAdmin
- Funciona en cualquier entorno MySQL/MariaDB

### ✅ Datos Reales y Completos
- **Usuarios reales** del sistema SBL con roles apropiados
- **Instrumentos reales** con códigos y especificaciones técnicas
- **Departamentos reales** de SBL Pharma
- **Proveedores reales** de calibración y mantenimiento
- **Datos de auditoría** representativos del sistema actual

### ✅ Sistema Completo de Permisos
- **81 permisos específicos** organizados por categorías
- **8 roles base** del sistema (Superadministrador, Developer, Administrador, etc.)
- **Asignación automática** de permisos por rol
- **Roles específicos** por empresa

### ✅ Trazabilidad Completa
- **Sistema de auditoría** completo con audit_trail
- **Historial de cambios** en instrumentos y calibraciones
- **Firmas electrónicas** internas
- **Registro de actividades** de usuarios

### ✅ Sistema de Calibración Avanzado
- **84 tipos diferentes** de instrumentos
- **Calibradores y patrones** organizados
- **Lecturas detalladas** de calibración
- **Sistema de no conformidades**
- **Plan de riesgos** por instrumento
- **Certificados** con trazabilidad

## Instrucciones de Instalación

### Opción 1: Instalación Completa (Recomendada)
```sql
-- En phpMyAdmin, ejecutar únicamente:
SOURCE MASTER_INSTALL.sql;
```

### Opción 2: Instalación Manual (Paso a Paso)
```sql
-- Ejecutar en orden:
SOURCE 00_complete_database_setup.sql;
SOURCE 01_complete_users_setup.sql;
SOURCE 02_complete_catalogs_setup.sql;
SOURCE 03_complete_instruments_calibration_setup.sql;
SOURCE 04_complete_audit_api_systems_setup.sql;
SOURCE 05_sample_data_insert.sql;
```

## Usuarios de Prueba

### Usuarios Principales Incluidos:

| Usuario | Email | Rol | Descripción |
|---------|-------|-----|-------------|
| `lourdes.avila` | lourdesmarienavila@gmail.com | Superadministrador | Acceso completo |
| `practicas.validacion` | practicas.validacion@sblpharma.com | Developer | Desarrollo del sistema |
| `vivian.rodriguez` | validacion5@sblpharma.com | Administrador | Ingeniera de instrumentos |
| `kenia.vazquez` | validacion7@sblpharma.com | Administrador | Ingeniera de instrumentos |
| `laura.martinez` | validacion@sblpharma.com | Supervisor | Supervisora de validación |
| `luis.guzman` | sistemas@sblpharma.com | Sistemas | Especialista de sistemas |

**Contraseña por defecto:** `SBL2024`

## Estructura de Base de Datos

### Tablas Principales (50+ tablas):

#### Configuración del Sistema
- `portals` - Configuración de portales
- `portal_domains` - Dominios por portal
- `empresas` - Empresas del sistema
- `system_settings` - Configuraciones del sistema

#### Usuarios y Seguridad
- `usuarios` - Usuarios del sistema
- `roles` - Roles del sistema
- `permissions` - Permisos disponibles
- `role_permissions` - Asignación roles-permisos
- `usuario_firmas_internas` - Firmas electrónicas
- `password_resets` - Restablecimiento de contraseñas

#### Catálogos Maestros
- `departamentos` - Departamentos de la empresa
- `marcas` - Marcas de instrumentos
- `modelos` - Modelos por marca
- `catalogo_instrumentos` - Tipos de instrumentos
- `proveedores` - Proveedores de servicios
- `catalogo_estados_calidad` - Estados de instrumentos

#### Sistema de Instrumentos
- `instrumentos` - Instrumentos registrados
- `calibradores` - Instrumentos patrón
- `requerimientos_calibracion` - Especificaciones técnicas
- `plan_riesgos` - Análisis de riesgos

#### Sistema de Calibración
- `solicitudes_calibracion` - Solicitudes de servicio
- `calibraciones` - Registros de calibración
- `calibraciones_lecturas` - Datos técnicos detallados
- `calibration_referencias` - Patrones utilizados
- `certificados` - Certificados emitidos
- `calibration_nonconformities` - No conformidades

#### Auditoría y Trazabilidad
- `audit_trail` - Registro completo de auditoría
- `historial_instrumentos` - Cambios en instrumentos
- `historial_calibraciones` - Eventos de calibración

#### Sistemas Auxiliares
- `api_clients` - Clientes de API
- `api_tokens` - Tokens de acceso
- `reportes_personalizados` - Reportes del usuario
- `notifications` - Sistema de notificaciones
- `backups` - Registro de respaldos

## Datos Incluidos

### Instrumentos de Muestra
- **12 instrumentos** con códigos reales SBL
- **Balanzas analíticas** (Mettler Toledo, Sartorius, Ohaus)
- **Termohigrómetros** (Vaisala) para áreas críticas
- **pHmetros** (Mettler Toledo) para laboratorios
- **Manómetros** (Fluke) para sistemas de presión
- **Micropipetas** (Gilson, Eppendorf) para volumetría

### Calibraciones Reales
- **4 calibraciones completadas** con datos técnicos
- **Lecturas de calibración** detalladas por punto
- **Certificados** con trazabilidad
- **No conformidades** de ejemplo

### Datos de Auditoría
- **Registros de cambios** en instrumentos
- **Actividad de usuarios** del sistema
- **Eventos de calibración** registrados
- **Accesos al sistema** monitoreados

## Características Técnicas

### Optimizaciones de Rendimiento
- **Índices compuestos** para consultas complejas
- **Particionado lógico** por empresa
- **Configuración optimizada** de MySQL
- **Análisis de estadísticas** de tablas

### Seguridad
- **Contraseñas encriptadas** con bcrypt
- **Tokens de API** seguros
- **Control de acceso** por roles
- **Registro de intentos** de acceso

### Escalabilidad
- **Diseño multi-empresa** (multi-tenant)
- **Sistema de archivos** para documentos
- **API REST** para integraciones
- **Sistema de notificaciones** escalable

## Mantenimiento

### Tareas Programadas Incluidas
- **Backup diario** automático
- **Alertas de calibración** automáticas  
- **Limpieza de logs** semanal
- **Verificación de salud** cada 15 minutos

### Monitoreo
- **Logs del sistema** categorizados
- **Métricas de rendimiento**
- **Alertas automáticas**
- **Reportes de estado**

## Soporte y Documentación

Este sistema ha sido diseñado específicamente para el entorno de SBL Pharma, incluyendo:

- ✅ **Cumplimiento regulatorio** (ISO 17025, GMP)
- ✅ **Trazabilidad completa** de cambios
- ✅ **Integridad de datos** garantizada
- ✅ **Auditoría completa** de actividades
- ✅ **Escalabilidad** para crecimiento futuro

Para soporte técnico o consultas sobre la implementación, contactar al equipo de desarrollo del sistema.

---

**Versión:** 2.0.0  
**Última actualización:** Septiembre 2025  
**Compatibilidad:** MySQL 5.7+, MariaDB 10.3+, phpMyAdmin 5.0+