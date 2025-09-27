# 🗂️ ORDEN DE EJECUCIÓN SQL PARA phpMyAdmin
**Base de datos:** `sbl_sistema_interno`  
**Fecha:** 26/09/2025  
**Sistema:** SBL ISO 17025 - Solo funcionalidad interna con proveedores

---

## 🎯 PASO 1: CREAR LA BASE DE DATOS
```sql
CREATE DATABASE IF NOT EXISTS sbl_sistema_interno 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sbl_sistema_interno;
```

---

## 📋 PASO 2: EJECUTAR ARCHIVOS EN ESTE ORDEN EXACTO:

### ✅ 1. ESTRUCTURA DE TABLAS (OBLIGATORIO PRIMERO)
**📁 Archivo:** `01_CREATE_TABLES_COMPLETE.sql`  
**📄 Descripción:** Crea todas las tablas del sistema (20+ tablas)  
**🟢 Estado:** LISTO - Adaptado para `sbl_sistema_interno`  
**⚠️ Importante:** Sin referencias a clientes/servicios, solo proveedores

### ✅ 2. DATOS BÁSICOS DEL SISTEMA  
**📁 Archivo:** `02_INSERT_BASIC_DATA.sql`  
**📄 Descripción:** Empresa SBL, roles, permisos, departamentos, marcas básicas  
**🟢 Estado:** CREADO - Listo para usar  

### ✅ 3. USUARIOS REALES DE SBL PHARMA
**📁 Archivo:** `03_INSERT_USUARIOS_REALES_SBL.sql`  
**📄 Descripción:** 9 usuarios reales de SBL Pharma (adaptado)  
**🟢 Estado:** CREADO - Adaptado para `sbl_sistema_interno`  
**👥 Usuarios:** lourdes.avila, kenia.vazquez, laura.martinez, etc.

### ✅ 4. INSTRUMENTOS COMPLETOS SBL PHARMA
**📁 Archivo:** `04_INSERT_INSTRUMENTOS_COMPLETO.sql`  
**📄 Descripción:** **CATÁLOGO COMPLETO** de instrumentos reales de SBL Pharma  
**🟢 Estado:** LISTO - Adaptado para `sbl_sistema_interno`  
**📊 Contenido REAL:**
  - **🔬 Catálogo:** 80+ tipos de instrumentos diferentes
  - **🏭 Marcas:** 170+ marcas reales (Mettler Toledo, Sartorius, etc.)
  - **🏢 Departamentos:** 12 departamentos reales de SBL
  - **� Instrumentos:** 1200+ instrumentos reales con códigos, series, ubicaciones
  - **� Ubicaciones:** Reales de SBL Pharma (Laboratorio Central, etc.)
  - **📅 Fechas:** Reales de alta, baja y próximas calibraciones

---

## ⚡ EJECUCIÓN RÁPIDA EN phpMyAdmin:

### Para SISTEMA COMPLETO REAL:
```
1. 01_CREATE_TABLES_COMPLETE.sql
2. 02_INSERT_BASIC_DATA.sql  
3. 03_INSERT_USUARIOS_REALES_SBL.sql
4. 04_INSERT_INSTRUMENTOS_COMPLETO.sql
```

**⚠️ IMPORTANTE:** Todos los archivos contienen **DATOS REALES** de SBL Pharma

---

## 🎯 RESULTADO ESPERADO:
- ✅ Base de datos funcional con estructura completa
- ✅ **9 usuarios reales** de SBL Pharma con credenciales reales
- ✅ Sistema de roles y permisos configurado
- ✅ **1200+ instrumentos reales** con datos históricos de SBL
- ✅ **12 departamentos reales** de SBL Pharma
- ✅ **170+ marcas reales** de equipos utilizados
- ✅ **80+ tipos de instrumentos** del catálogo real
- ✅ Solo funcionalidad interna + proveedores
- ❌ Sin referencias a clientes o servicios externos

---

## 🚀 PRÓXIMOS PASOS DESPUÉS DE LA INSTALACIÓN:
1. Probar login con usuarios reales
2. Verificar funcionalidad de instrumentos  
3. Configurar proveedores para calibraciones
4. Añadir más instrumentos si es necesario