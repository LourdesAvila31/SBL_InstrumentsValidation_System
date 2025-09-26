# Guía Rápida - Sistema Developer Private Section

## 🚀 Inicio Rápido

### 1. Instalación Automática (Recomendado)

```bash
# Ejecutar configuración automática
python tools/scripts/setup_developer_system.py install
```

### 2. Instalación Manual

**Paso 1: Base de Datos**
```sql
mysql -u root -p sbl_sistema_interno < tools/scripts/developer_private_section_schema.sql
```

**Paso 2: Verificar Usuario Developer**
```sql
-- Verificar que el usuario tenga rol Developer
SELECT u.nombre, r.nombre as rol 
FROM usuarios u 
JOIN user_roles ur ON u.id = ur.user_id 
JOIN roles r ON r.id = ur.role_id 
WHERE r.nombre = 'Developer';
```

**Paso 3: Acceder al Sistema**
- URL: `http://localhost/SBL_SISTEMA_INTERNO/public/apps/developer/`
- Login con usuario que tenga rol "Developer"

## 📊 Primeros Pasos

### Acceso al Dashboard

1. **Login:** Usar credenciales con rol Developer
2. **Verificación:** El sistema validará automáticamente los permisos
3. **Dashboard:** Acceso inmediato a métricas en tiempo real

### Funciones Principales

| Función | Acceso Rápido | Descripción |
|---------|---------------|-------------|
| **Dashboard** | Pestaña principal | KPIs y métricas en tiempo real |
| **Incidencias** | Tab "Incidents" | Gestión completa de incidencias |
| **Documentos** | Tab "Documents" | SOP, AppCare, Handover |
| **Proveedores** | Tab "Vendors" | Gestión de contratos y SLAs |
| **Alertas** | Tab "Alerts" | Sistema de alertas automáticas |

## ⚡ Operaciones Comunes

### Crear Nueva Incidencia

```javascript
// Desde la interfaz web - Tab Incidents
1. Click "New Incident"
2. Llenar formulario:
   - Título descriptivo
   - Descripción detallada
   - Severidad (low/medium/high/critical)
   - Categoría
3. Submit → Auto-asignada y registrada
```

### Agregar Documento SOP

```javascript
// Desde la interfaz web - Tab Documents
1. Click "New Document"
2. Seleccionar tipo: "SOP"
3. Completar:
   - Título del procedimiento
   - Contenido detallado
   - Departamento
   - Ciclo de revisión
4. Save → Queda en estado "draft"
```

### Configurar Alerta Automática

```javascript
// Desde la interfaz web - Tab Alerts
1. Click "New Alert Rule"
2. Configurar:
   - Tipo de alerta
   - Condiciones de activación
   - Canales de notificación
   - Destinatarios
3. Enable → Alerta activa
```

## 🔧 Comandos Útiles

### Validación del Sistema

```bash
# Validar todos los componentes
python tools/scripts/setup_developer_system.py validate

# Solo base de datos
python tools/scripts/setup_developer_system.py dbtest

# Solo permisos de archivos
python tools/scripts/setup_developer_system.py permissions
```

### Datos de Prueba

```bash
# Generar datos de ejemplo
python tools/scripts/setup_developer_system.py testdata
```

### Verificación PHP

```bash
# Validar sintaxis de módulos
php -l app/Modules/Internal/Developer/DeveloperAuth.php
php -l app/Modules/Internal/Developer/DeveloperDashboard.php
# ... etc para cada módulo
```

## 📈 Métricas Clave

### KPIs del Dashboard

- **Incidencias Abiertas:** Número actual de incidencias sin resolver
- **Alertas Activas:** Alertas que requieren atención
- **Documentos por Vencer:** Documentos próximos a expirar (30 días)
- **SLAs Incumplidos:** Acuerdos de servicio no cumplidos
- **CPU/Memory/Disk:** Uso actual de recursos del sistema

### Gráficos en Tiempo Real

- **Performance:** CPU, Memoria, Disco (últimas 24h)
- **Incidencias:** Tendencia por severidad (última semana)
- **API Response:** Tiempo de respuesta promedio
- **Alertas:** Distribución por tipo y severidad

## 🔐 Permisos y Seguridad

### Permisos Requeridos

| Permiso | ID | Necesario Para |
|---------|----| ---------------|
| developer_dashboard_access | 201 | Acceder al dashboard |
| developer_incidents_manage | 202 | Gestionar incidencias |
| developer_documents_manage | 204 | Gestionar documentos |
| developer_vendors_manage | 205 | Gestionar proveedores |
| developer_alerts_manage | 206 | Configurar alertas |

### Verificar Permisos

```sql
-- Verificar permisos del usuario actual
SELECT p.nombre, p.descripcion 
FROM permissions p
JOIN role_permissions rp ON p.id = rp.permission_id
JOIN user_roles ur ON rp.role_id = ur.role_id
WHERE ur.user_id = [ID_USUARIO] 
AND p.nombre LIKE 'developer_%';
```

## 🚨 Solución de Problemas

### Error: "Access Denied"

**Causa:** Usuario sin permisos de Developer
**Solución:**
```sql
-- Asignar rol Developer
INSERT INTO user_roles (user_id, role_id) 
SELECT [USER_ID], id FROM roles WHERE nombre = 'Developer';
```

### Error: "Database Connection Failed"

**Causa:** Configuración de BD incorrecta
**Solución:**
1. Verificar `app/Core/db_config.php`
2. Comprobar credenciales MySQL
3. Verificar que la BD existe

### Error: "Module Not Found"

**Causa:** Archivos PHP faltantes
**Solución:**
```bash
# Verificar estructura
ls -la app/Modules/Internal/Developer/
# Debe mostrar todos los archivos .php
```

### Frontend No Carga

**Causa:** Rutas incorrectas o archivos faltantes
**Solución:**
1. Verificar que existe `public/apps/developer/index.html`
2. Comprobar consola del navegador (F12)
3. Validar permisos de archivos

## 📱 Interfaz de Usuario

### Navegación Principal

```
┌─ Dashboard (Inicio)
├─ Incidents (Gestión de Incidencias)
├─ Documents (Documentación)
│  ├─ SOP
│  ├─ AppCare  
│  └─ Handover
├─ Vendors (Proveedores)
├─ Alerts (Alertas)
└─ Settings (Configuración)
```

### Atajos de Teclado

| Atajo | Función |
|-------|---------|
| `Ctrl + D` | Ir a Dashboard |
| `Ctrl + I` | Nueva Incidencia |
| `Ctrl + N` | Nuevo Documento |
| `Ctrl + R` | Actualizar Datos |
| `F5` | Refrescar Dashboard |

## 🔄 Flujos de Trabajo

### Gestión de Incidencias

```
1. Crear Incidencia → 2. Asignar Responsable → 
3. Investigar → 4. Resolver → 5. Cerrar
```

### Documentación

```
1. Crear Documento (Draft) → 2. Revisar → 
3. Aprobar → 4. Activar → 5. Programar Renovación
```

### Proveedores

```
1. Registrar Proveedor → 2. Crear Contrato → 
3. Definir SLA → 4. Monitorear → 5. Auditar
```

## 📊 APIs Principales

### Endpoints Clave

```bash
# Dashboard
GET /backend/developer/dashboard.php

# Incidencias
GET|POST|PUT|DELETE /backend/developer/incidents.php

# Documentos
GET|POST|PUT /backend/developer/documents.php

# Proveedores
GET|POST|PUT /backend/developer/vendors.php

# Alertas
GET|POST|PUT /backend/developer/alerts.php
```

### Ejemplo de Uso API

```javascript
// Obtener datos del dashboard
fetch('/backend/developer/dashboard.php')
  .then(response => response.json())
  .then(data => {
    console.log('KPIs:', data.kpis);
    console.log('Charts:', data.charts);
  });
```

## 📞 Soporte

### Información de Contacto

- **Logs del Sistema:** `storage/developer/logs/`
- **Configuración:** `storage/developer/config.json`
- **Documentación Completa:** `docs/developer_private_section.md`

### Comandos de Diagnóstico

```bash
# Diagnóstico completo
python tools/scripts/setup_developer_system.py validate

# Estado del sistema
php -m | grep mysqli  # Verificar extensiones PHP
systemctl status apache2  # Estado del servidor web
systemctl status mysql    # Estado de MySQL
```

---

**¡Sistema listo para usar!** 🎉

Acceda a: `http://localhost/SBL_SISTEMA_INTERNO/public/apps/developer/`