# Sistema de Gestión de Cambios ISO 17025
# Manual del Administrador

## 📋 Índice

1. [Introducción](#introducción)
2. [Arquitectura del Sistema](#arquitectura-del-sistema)
3. [Instalación](#instalación)
4. [Configuración](#configuración)
5. [Módulos del Sistema](#módulos-del-sistema)
6. [Panel de Administración](#panel-de-administración)
7. [API y Integraciones](#api-y-integraciones)
8. [Mantenimiento](#mantenimiento)
9. [Seguridad](#seguridad)
10. [Solución de Problemas](#solución-de-problemas)

---

## 🎯 Introducción

El **Sistema de Gestión de Cambios ISO 17025** es una solución integral diseñada para cumplir con los estándares de calidad ISO 17025, proporcionando control total sobre configuraciones, gestión de usuarios, auditoría completa y administración de incidentes en sistemas computarizados.

### Características Principales

- ✅ **Sistema de Auditoría Completo**: Registro detallado de todas las acciones
- ✅ **Gestión Automática de Backups**: Estrategias calientes y frías con programación
- ✅ **Administración de Incidentes**: Integración con JIRA, ServiceNow y más
- ✅ **Alertas en Tiempo Real**: Monitoreo continuo con Node.js y Socket.io
- ✅ **Control de Versiones**: Gestión de configuraciones con Git
- ✅ **Dashboard Interactivo**: Panel de control con métricas en tiempo real
- ✅ **Integraciones Múltiples**: Trello, Asana, Monday.com y más

---

## 🏗️ Arquitectura del Sistema

### Componentes Principales

```
┌─────────────────────────────────────────────────────────────┐
│                    FRONTEND LAYER                           │
├─────────────────────────────────────────────────────────────┤
│  • Dashboard HTML5 + Bootstrap 5 + Chart.js               │
│  • Socket.io Client para tiempo real                       │
│  • API REST para comunicación                              │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│                 APPLICATION LAYER (PHP 8+)                 │
├─────────────────────────────────────────────────────────────┤
│  • AuditManager: Sistema de auditoría                      │
│  • BackupManager: Gestión de respaldos                     │
│  • IncidentManager: Administración de incidentes           │
│  • ConfigurationVersionControl: Control de versiones       │
│  • ProjectManagementIntegration: Integraciones externas    │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│              REAL-TIME LAYER (Node.js + Express)           │
├─────────────────────────────────────────────────────────────┤
│  • Alert System: Monitoreo continuo                        │
│  • Socket.io Server: Comunicación en tiempo real           │
│  • Email Notifications: Alertas por correo                 │
│  • System Health Monitoring: Verificación de estado        │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│                     DATA LAYER                             │
├─────────────────────────────────────────────────────────────┤
│  • MySQL Database: Almacenamiento principal                │
│  • File System: Backups y logs                            │
│  • Git Repository: Control de versiones de configuración   │
└─────────────────────────────────────────────────────────────┘
```

### Tecnologías Utilizadas

- **Backend**: PHP 8+ con MySQLi
- **Frontend**: HTML5, CSS3, JavaScript ES6+, Bootstrap 5
- **Real-time**: Node.js, Express, Socket.io
- **Base de Datos**: MySQL 8.0+
- **Control de Versiones**: Git
- **Gráficos**: Chart.js
- **Notificaciones**: Nodemailer
- **Integraciones**: APIs REST de terceros

---

## 🚀 Instalación

### Requisitos del Sistema

- **PHP 8.0+** con extensiones: mysqli, json, curl, zip
- **Node.js 16+** y npm
- **MySQL 8.0+**
- **Git**
- **Servidor Web** (Apache/Nginx)

### Instalación Automática

```bash
# 1. Clonar o descargar el sistema
cd /path/to/your/project

# 2. Ejecutar instalador automático
php install_change_management_system.php

# 3. Seguir las instrucciones en pantalla
```

### Instalación Manual

1. **Configurar Base de Datos**:
   ```sql
   CREATE DATABASE iso17025 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Importar Esquema**:
   ```bash
   mysql -u root -p iso17025 < app/Modules/Database/change_management_schema.sql
   ```

3. **Instalar Dependencias de Node.js**:
   ```bash
   cd app/Modules/AlertSystem
   npm install
   ```

4. **Configurar Variables de Entorno**:
   - Copiar `.env.example` a `.env`
   - Configurar credenciales de base de datos
   - Configurar credenciales SMTP

5. **Configurar Servidor Web**:
   - Document Root: `/public`
   - Habilitar mod_rewrite (Apache)

---

## ⚙️ Configuración

### Archivo de Configuración Principal

```php
// app/config.php
return [
    'database' => [
        'host' => 'localhost',
        'user' => 'root',
        'password' => '',
        'name' => 'iso17025'
    ],
    
    'app' => [
        'url' => 'http://localhost:8000',
        'name' => 'Sistema ISO 17025',
        'version' => '1.0.0'
    ],
    
    'alerts' => [
        'node_server' => 'http://localhost:3001',
        'enabled' => true
    ]
];
```

### Variables de Entorno Node.js

```bash
# app/Modules/AlertSystem/.env
NODE_ENV=production
PORT=3001

DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=iso17025

EMAIL_SERVICE=gmail
EMAIL_USER=tu-email@gmail.com
EMAIL_PASS=tu-password-app

JIRA_ENABLED=false
TRELLO_ENABLED=false
ASANA_ENABLED=false
```

### Configuración de Cron Jobs

```bash
# Ejecutar cada noche a las 2 AM
0 2 * * * php /path/to/project/app/Modules/BackupSystem/backup_scheduler.php --job=daily_backup

# Limpieza de logs cada día a las 3 AM
0 3 * * * php /path/to/project/maintenance/cleanup_old_logs.php

# Verificación de salud cada 5 minutos
*/5 * * * * curl -s http://localhost:3001/api/system/health > /dev/null
```

---

## 🧩 Módulos del Sistema

### 1. Sistema de Auditoría (AuditManager)

**Ubicación**: `app/Modules/AuditSystem/AuditManager.php`

**Funcionalidades**:
- Registro automático de todas las acciones de usuario
- Tracking de cambios en configuraciones
- Generación de reportes de auditoría
- Retención configurable de logs

**Uso**:
```php
$auditManager = new AuditManager();
$auditManager->logChange(
    userId: $userId,
    action: 'configuration_update',
    details: 'Updated database connection settings',
    affectedResource: 'config/database.php'
);
```

### 2. Sistema de Backup (BackupManager)

**Ubicación**: `app/Modules/BackupSystem/BackupManager.php`

**Funcionalidades**:
- Backups automáticos programados
- Estrategias calientes y frías
- Compresión y cifrado
- Restauración automática

**Uso**:
```php
$backupManager = new BackupManager();
$backupManager->performFullBackup('monthly');
```

### 3. Gestión de Incidentes (IncidentManager)

**Ubicación**: `app/Modules/IncidentManagement/IncidentManager.php`

**Funcionalidades**:
- Creación automática de tickets
- Integración con JIRA, ServiceNow
- Monitoreo de KPIs
- Escalamiento automático

**Uso**:
```php
$incidentManager = new IncidentManager();
$incidentManager->createIncident([
    'title' => 'Database Connection Timeout',
    'severity' => 'high',
    'category' => 'infrastructure'
]);
```

### 4. Sistema de Alertas (Node.js)

**Ubicación**: `app/Modules/AlertSystem/alert_server.js`

**Funcionalidades**:
- Monitoreo en tiempo real
- Alertas por email
- Notificaciones push
- Métricas de sistema

**Iniciar Servidor**:
```bash
cd app/Modules/AlertSystem
npm start
```

### 5. Control de Versiones (ConfigurationVersionControl)

**Ubicación**: `app/Modules/ConfigurationControl/ConfigurationVersionControl.php`

**Funcionalidades**:
- Versionado automático con Git
- Rollback de configuraciones
- Ramas experimentales
- Historial completo

**Uso**:
```php
$versionControl = new ConfigurationVersionControl();
$versionControl->saveConfiguration(
    'database_config', 
    $newConfig, 
    'Updated connection pool settings'
);
```

---

## 📊 Panel de Administración

### Acceso al Dashboard

- **URL**: `http://tu-dominio.com/admin_dashboard.html`
- **Usuario por defecto**: `developer`
- **Contraseña por defecto**: `developer123`

### Características del Dashboard

1. **Métricas en Tiempo Real**:
   - Estado del sistema
   - Usuarios activos
   - Alertas pendientes
   - Performance metrics

2. **Gestión de Backups**:
   - Programar backups
   - Ver historial
   - Restaurar desde backup

3. **Administración de Incidentes**:
   - Crear incidentes
   - Seguimiento de estado
   - Reportes de KPI

4. **Control de Configuraciones**:
   - Ver historial de cambios
   - Rollback de configuraciones
   - Aprobación de cambios

5. **Auditoría y Reportes**:
   - Búsqueda de logs
   - Generación de reportes
   - Exportación de datos

### API Endpoints

```
GET  /api/dashboard/data           - Datos del dashboard
POST /api/dashboard/backup/manual  - Ejecutar backup manual
GET  /api/dashboard/audit/search   - Buscar logs de auditoría
POST /api/dashboard/incidents      - Crear incidente
GET  /api/dashboard/system/health  - Estado del sistema
```

---

## 🔌 API y Integraciones

### Integraciones Disponibles

#### JIRA
```php
// Configuración
$jiraConfig = [
    'url' => 'https://tu-empresa.atlassian.net',
    'username' => 'tu-usuario',
    'password' => 'tu-api-token',
    'project_key' => 'PROJ'
];
```

#### Trello
```php
// Configuración
$trelloConfig = [
    'api_key' => 'tu-api-key',
    'token' => 'tu-token',
    'board_id' => 'id-del-board'
];
```

#### ServiceNow
```php
// Configuración
$serviceNowConfig = [
    'url' => 'https://tu-instancia.service-now.com',
    'username' => 'tu-usuario',
    'password' => 'tu-password'
];
```

### Webhooks

El sistema puede recibir webhooks en:
- `POST /webhooks/trello` - Actualizaciones de Trello
- `POST /webhooks/jira` - Actualizaciones de JIRA
- `POST /webhooks/servicenow` - Actualizaciones de ServiceNow

---

## 🛠️ Mantenimiento

### Limpieza Automática

```bash
# Ejecutar limpieza manual
php maintenance/cleanup_old_logs.php

# Programar en cron (diario a las 3 AM)
0 3 * * * php /path/to/project/maintenance/cleanup_old_logs.php
```

### Optimización de Base de Datos

```sql
-- Optimizar tablas principales
OPTIMIZE TABLE audit_logs, backup_history, incidents, system_alerts;

-- Actualizar estadísticas
ANALYZE TABLE audit_logs, backup_history, incidents, system_alerts;
```

### Monitoreo de Logs

```bash
# Ver logs en tiempo real
tail -f storage/logs/system.log
tail -f app/Modules/AlertSystem/logs/alerts.log

# Buscar errores
grep -i error storage/logs/*.log
```

### Backup Manual

```bash
# Backup completo
php app/Modules/BackupSystem/backup_scheduler.php --job=manual_backup

# Backup solo base de datos
php app/Modules/BackupSystem/backup_scheduler.php --job=database_only
```

---

## 🔒 Seguridad

### Configuraciones de Seguridad

1. **Cambiar Credenciales por Defecto**:
   ```sql
   UPDATE usuarios SET password_hash = PASSWORD('nueva-password') 
   WHERE usuario = 'developer';
   ```

2. **Configurar HTTPS**:
   - Obtener certificado SSL
   - Configurar redirect HTTP → HTTPS
   - Actualizar URLs en configuración

3. **Configurar Firewall**:
   ```bash
   # Permitir solo puertos necesarios
   ufw allow 80/tcp
   ufw allow 443/tcp
   ufw allow 3001/tcp  # Solo desde localhost
   ```

4. **Configurar Headers de Seguridad**:
   ```apache
   Header always set X-Content-Type-Options nosniff
   Header always set X-Frame-Options DENY
   Header always set X-XSS-Protection "1; mode=block"
   Header always set Strict-Transport-Security "max-age=31536000"
   ```

### Roles y Permisos

- **developer**: Acceso completo al sistema
- **admin**: Administración general sin desarrollo
- **auditor**: Solo lectura de logs y reportes
- **operator**: Operaciones básicas del sistema

---

## 🚨 Solución de Problemas

### Problemas Comunes

#### 1. Node.js Server No Inicia

**Síntomas**: Dashboard no muestra datos en tiempo real

**Solución**:
```bash
cd app/Modules/AlertSystem
npm install
npm start

# Verificar logs
cat logs/alerts.log
```

#### 2. Error de Conexión a Base de Datos

**Síntomas**: Error 500 en páginas PHP

**Solución**:
```php
// Verificar configuración en app/config.php
// Probar conexión manual
$conn = new mysqli('localhost', 'user', 'pass', 'db');
if ($conn->connect_error) {
    echo "Error: " . $conn->connect_error;
}
```

#### 3. Backups No se Ejecutan

**Síntomas**: No hay nuevos archivos en storage/backups

**Solución**:
```bash
# Verificar cron jobs
crontab -l

# Ejecutar backup manual
php app/Modules/BackupSystem/backup_scheduler.php --job=test_backup

# Verificar permisos
chmod +x app/Modules/BackupSystem/backup_scheduler.php
```

#### 4. Alertas No se Envían

**Síntomas**: No llegan emails de alerta

**Solución**:
```bash
# Verificar configuración SMTP en .env
# Probar envío manual
node app/Modules/AlertSystem/test_email.js
```

### Logs de Debug

```bash
# Habilitar debug en PHP
echo "error_reporting(E_ALL); ini_set('display_errors', 1);" > debug.php

# Ver logs de Apache/Nginx
tail -f /var/log/apache2/error.log
tail -f /var/log/nginx/error.log

# Logs del sistema
tail -f storage/logs/system.log
tail -f storage/logs/audit.log
```

### Comandos de Diagnóstico

```bash
# Estado de servicios
systemctl status apache2
systemctl status mysql
systemctl status nodejs

# Verificar puertos
netstat -tlnp | grep :3001
netstat -tlnp | grep :80

# Verificar espacio en disco
df -h
du -sh storage/

# Verificar memoria
free -h
```

---

## 📞 Soporte

### Información de Contacto

- **Sistema**: ISO 17025 Change Management System
- **Versión**: 1.0.0
- **Documentación**: [Ubicación de docs]
- **Logs**: `storage/logs/`

### Antes de Contactar Soporte

1. Revisar este manual
2. Verificar logs de error
3. Probar en modo debug
4. Documentar pasos para reproducir el problema

### Información a Incluir

- Versión del sistema
- Mensaje de error completo
- Logs relevantes
- Pasos para reproducir
- Configuración del entorno

---

**© 2024 Sistema de Gestión de Cambios ISO 17025**
*Versión 1.0.0 - Manual del Administrador*