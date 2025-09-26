# 📋 INSTRUCCIONES PENDIENTES - SBL Sistema Interno

## 🎯 Estado Actual
**✅ MIGRACIÓN COMPLETADA** - Service y Tenant movidos al Sistema de Servicios  
**✅ DASHBOARD MODERNO** - Implementado con control de roles  
**🔄 PENDIENTE** - Integración backend y funcionalidades faltantes

---

## 🚨 TAREAS CRÍTICAS PENDIENTES

### 1. **INTEGRACIÓN BACKEND PHP** 
#### 🔴 **PRIORIDAD ALTA**

**Problema**: Las secciones del dashboard moderno apuntan a archivos HTML frontend, pero necesitan integración con módulos PHP backend.

**Archivos que necesitan backend**:
```
✅ Dashboard Principal → Ya funciona
🔴 Instrumentos       → Necesita backend PHP
🔴 Planeación         → Necesita backend PHP  
🔴 Calibraciones      → Necesita backend PHP
🔴 Reportes           → Necesita backend PHP
🔴 Usuarios           → Necesita backend PHP
🔴 Tokens API         → Necesita backend PHP
🔴 Calidad            → Necesita backend PHP
✅ GAMP5              → Ya funciona
✅ Developer          → Ya funciona
✅ Administración     → Ya funciona
🔴 Centro de Ayuda    → Necesita backend PHP
```

**Acciones requeridas**:
```bash
# Crear archivos backend faltantes
public/backend/instrumentos/     → CREAR
public/backend/planeacion/       → CREAR
public/backend/calibraciones/    → CREAR
public/backend/reportes/         → CREAR
public/backend/usuarios/         → VERIFICAR/COMPLETAR
public/backend/configuracion/    → CREAR
public/backend/calidad/          → CREAR
public/backend/ayuda/            → CREAR
```

### 2. **MÓDULOS PHP BACKEND**
#### 🔴 **PRIORIDAD ALTA**

**Ubicación**: `app/Modules/Internal/`

**Estado actual**:
```
✅ Dashboard/        → Completo
✅ Usuarios/         → Completo  
🔴 Instrumentos/     → FALTA CREAR
🔴 Planeacion/       → FALTA CREAR
🔴 Calibraciones/    → FALTA CREAR
🔴 Reportes/         → FALTA CREAR
🔴 Configuracion/    → FALTA CREAR
🔴 Calidad/          → FALTA CREAR
🔴 Ayuda/            → FALTA CREAR
```

### 3. **BASE DE DATOS**
#### 🟡 **PRIORIDAD MEDIA**

**Verificaciones necesarias**:
- [ ] **Tablas de instrumentos** para el sistema interno
- [ ] **Tablas de calibraciones** internas
- [ ] **Tablas de planeación** de calibraciones
- [ ] **Tablas de reportes** internos
- [ ] **Tablas de configuración** del sistema
- [ ] **Tablas de calidad** y auditorías

**Script de verificación**:
```bash
# Crear script para verificar estructura de BD
tools/verify_internal_database.php
```

---

## 📝 PLAN DE IMPLEMENTACIÓN DETALLADO

### **FASE 1: BACKEND CRÍTICO** (1-2 días)

#### 1.1 Crear Módulo de Instrumentos
```
📁 app/Modules/Internal/Instrumentos/
├── list_gages.php
├── add_gage.php
├── edit_gage.php
├── delete_gage.php
├── import_gages.php
└── gage_history.php

📁 public/backend/instrumentos/
├── list_gages.php
├── add_gage.php
├── edit_gage.php
├── get_gages.php
└── import_gages.php
```

#### 1.2 Crear Módulo de Calibraciones
```
📁 app/Modules/Internal/Calibraciones/
├── list_calibrations.php
├── add_calibration.php
├── edit_calibration.php
├── calibration_workflow.php
└── calibration_reports.php

📁 public/backend/calibraciones/
├── list_calibrations.php
├── add_calibration.php
├── edit_calibration.php
└── get_calibrations.php
```

#### 1.3 Crear Módulo de Usuarios Completo
```
📁 app/Modules/Internal/Usuarios/
✅ login.php              → Ya existe
🔴 list_users.php         → CREAR
🔴 add_user.php           → CREAR
🔴 edit_user.php          → VERIFICAR
🔴 delete_user.php        → CREAR
🔴 user_permissions.php   → CREAR

📁 public/backend/usuarios/
✅ login.php              → Ya existe
🔴 list_users.php         → CREAR
🔴 add_user.php           → CREAR
🔴 edit_user.php          → CREAR
🔴 get_users.php          → CREAR
```

### **FASE 2: FUNCIONALIDADES AVANZADAS** (2-3 días)

#### 2.1 Módulo de Planeación
```
📁 app/Modules/Internal/Planeacion/
├── calendar_planning.php
├── schedule_calibrations.php
├── planning_reports.php
└── resource_allocation.php
```

#### 2.2 Módulo de Reportes
```
📁 app/Modules/Internal/Reportes/
├── calibration_reports.php
├── instrument_reports.php
├── planning_reports.php
├── audit_reports.php
└── export_reports.php
```

#### 2.3 Módulo de Configuración
```
📁 app/Modules/Internal/Configuracion/
├── api_tokens.php
├── system_settings.php
├── user_permissions.php
└── backup_settings.php
```

### **FASE 3: INTEGRACIÓN Y TESTING** (1 día)

#### 3.1 Verificación de Rutas
```bash
# Actualizar dashboard moderno con rutas backend correctas
app/Modules/Internal/Dashboard/modern_dashboard.php
```

#### 3.2 Testing Completo
```bash
# Scripts de verificación
tools/test_backend_integration.php
tools/test_dashboard_navigation.php
```

---

## 🛠️ COMANDOS PARA IMPLEMENTAR

### **Crear Estructura Backend**
```bash
# Crear directorios backend faltantes
mkdir public/backend/instrumentos
mkdir public/backend/planeacion  
mkdir public/backend/calibraciones
mkdir public/backend/reportes
mkdir public/backend/configuracion
mkdir public/backend/calidad
mkdir public/backend/ayuda

# Crear directorios de módulos
mkdir app/Modules/Internal/Instrumentos
mkdir app/Modules/Internal/Planeacion
mkdir app/Modules/Internal/Calibraciones  
mkdir app/Modules/Internal/Reportes
mkdir app/Modules/Internal/Configuracion
mkdir app/Modules/Internal/Calidad
mkdir app/Modules/Internal/Ayuda
```

### **Verificar Estado Actual**
```bash
# Ejecutar verificaciones
php tools/verify_migration.php
php tools/verify_design_homologation.php
php tools/verify_dashboard_routes.php
```

---

## 📋 CHECKLIST DE IMPLEMENTACIÓN

### **Backend PHP**
- [ ] **Instrumentos**: Crear módulo completo con CRUD
- [ ] **Calibraciones**: Crear módulo completo con workflow  
- [ ] **Planeación**: Crear módulo de planificación
- [ ] **Reportes**: Crear módulo de reportes internos
- [ ] **Usuarios**: Completar módulo de gestión de usuarios
- [ ] **Configuración**: Crear módulo de configuración
- [ ] **Calidad**: Crear módulo de gestión de calidad
- [ ] **API Tokens**: Crear gestión de tokens
- [ ] **Centro de Ayuda**: Crear sistema de ayuda

### **Base de Datos**
- [ ] **Verificar tablas** necesarias para sistema interno
- [ ] **Crear migraciones** si faltan tablas
- [ ] **Scripts de inicialización** de datos
- [ ] **Índices y optimizaciones** de rendimiento

### **Frontend-Backend Integration**
- [ ] **Actualizar rutas** en dashboard moderno
- [ ] **Conectar formularios** con endpoints PHP
- [ ] **Implementar validaciones** frontend y backend
- [ ] **Manejo de errores** consistente

### **Seguridad y Permisos**
- [ ] **Verificar autenticación** en todos los módulos
- [ ] **Implementar control de acceso** por roles
- [ ] **Validar permisos** en cada endpoint
- [ ] **Sanitización de datos** de entrada

### **Testing y Validación**
- [ ] **Pruebas de navegación** desde dashboard
- [ ] **Pruebas de CRUD** en cada módulo
- [ ] **Pruebas de permisos** por rol
- [ ] **Pruebas de rendimiento** básicas

---

## 🎯 OBJETIVOS FINALES

### **Funcionalidades Completas**
1. **Login** → Dashboard Moderno → **Secciones Funcionales**
2. **Control de acceso** granular por rol de usuario
3. **CRUD completo** para todas las entidades internas
4. **Reportes** y **analytics** para operaciones internas
5. **Gestión de usuarios** y **permisos** del sistema interno
6. **Configuración** y **mantenimiento** del sistema

### **Arquitectura Final**
```
SBL Sistema Interno (Completo)
├── Frontend (HTML/JS/CSS) ✅
├── Backend PHP (CRUD)     🔴 PENDIENTE
├── Base de Datos         🟡 VERIFICAR
├── Autenticación         ✅
├── Control de Acceso     ✅
└── APIs Internas         🔴 PENDIENTE
```

---

## 📞 SIGUIENTE PASO

**¿Por dónde empezar?**

**RECOMENDACIÓN**: Comenzar con el **Módulo de Instrumentos** ya que es fundamental para el sistema de calibraciones.

```bash
# Comando para comenzar
1. Crear app/Modules/Internal/Instrumentos/list_gages.php
2. Crear public/backend/instrumentos/list_gages.php  
3. Probar integración con apps/internal/instrumentos/list_gages.html
4. Continuar con add_gage.php, edit_gage.php, etc.
```

**¿Te gustaría que implemente algún módulo específico primero o prefieres que comience con el módulo de Instrumentos?**