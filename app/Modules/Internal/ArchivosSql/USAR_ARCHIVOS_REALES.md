# 🚀 IMPLEMENTACIÓN CON DATOS REALES SBL - INSTRUCCIONES CORREGIDAS

## ❌ ERROR IDENTIFICADO
Disculpa, creé archivos con datos ficticios cuando ya tienes **LOS DATOS REALES** en las carpetas existentes.

## ✅ SOLUCIÓN CORRECTA - USAR TUS ARCHIVOS REALES

### 📁 Archivos Reales Existentes:
- `SBL_adds/add_tables.sql` - ✅ **Tablas reales**
- `SBL_adds/add_seed_data.sql` - ✅ **Datos semilla reales**  
- `SBL_inserts/insert_instrumentos.sql` - ✅ **Instrumentos reales**
- `SBL_inserts/insert_internal_portal_usuarios.sql` - ✅ **Usuarios reales**
- `SBL_inserts/insert_calibraciones_certificados.sql` - ✅ **Calibraciones reales**
- `SBL_inserts/insert_plan_riesgos.sql` - ✅ **Plan de riesgos real**

### 🔄 ÚNICO CAMBIO NECESARIO:
Los archivos usan `iso17025` pero necesitas `sbl_sistema_interno`.

## 📋 ORDEN CORRECTO DE EJECUCIÓN

### ⚡ Implementación Rápida:

1. **En phpMyAdmin**, ejecuta este comando primero:
```sql
-- Cambiar el nombre de base de datos en todos los archivos
-- O ejecutar esto antes de cada archivo:
CREATE DATABASE IF NOT EXISTS sbl_sistema_interno CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sbl_sistema_interno;
```

2. **Ejecutar archivos en este orden:**

#### 1️⃣ Crear Tablas (REAL)
- **Archivo:** `SBL_adds/add_tables.sql`
- **Cambio:** Reemplazar `USE iso17025;` por `USE sbl_sistema_interno;`

#### 2️⃣ Datos Semilla (REAL)  
- **Archivo:** `SBL_adds/add_seed_data.sql`
- **Cambio:** Reemplazar `USE iso17025;` por `USE sbl_sistema_interno;`

#### 3️⃣ Usuarios Reales (REAL)
- **Archivo:** `SBL_inserts/insert_internal_portal_usuarios.sql`
- **Cambio:** Reemplazar `USE iso17025;` por `USE sbl_sistema_interno;`

#### 4️⃣ Instrumentos Reales (REAL)
- **Archivo:** `SBL_inserts/insert_instrumentos.sql`
- **Cambio:** Reemplazar `USE iso17025;` por `USE sbl_sistema_interno;`

#### 5️⃣ Calibraciones Reales (REAL)
- **Archivo:** `SBL_inserts/insert_calibraciones_certificados.sql`
- **Cambio:** Reemplazar `USE iso17025;` por `USE sbl_sistema_interno;`

#### 6️⃣ Plan de Riesgos (REAL)
- **Archivo:** `SBL_inserts/insert_plan_riesgos.sql`
- **Cambio:** Reemplazar `USE iso17025;` por `USE sbl_sistema_interno;`

## 🔐 USUARIOS REALES INCLUIDOS

Según tu archivo `insert_internal_portal_usuarios.sql`:

### ✅ Usuarios Reales de SBL:
1. **lourdes.avila** - lourdesmarienavila@gmail.com (Superadministradora)
2. **practicas.validacion** - practicas.validacion@sblpharma.com (Developer)
3. **kenia.vazquez** - validacion7@sblpharma.com (Administradora)
4. **vivian.rodriguez** - validacion5@sblpharma.com (Administradora)
5. **laura.martinez** - validacion@sblpharma.com (Administradora)
6. **israel.castillo** - validacion8@sblpharma.com (Lector)
7. **alexis.munoz** - validacion3@sblpharma.com (Lector)
8. **luis.guzman** - sistemas@sblpharma.com (Sistemas)
9. **gilberto.garcia** - sistemas2@sblpharma.com (Sistemas)

### 🔑 Contraseña Común:
**Todos usan la misma contraseña hasheada:** `$2y$10$QFrroV/TUmhyJlX2LW7ILuAxxWHUvloWJtdBmWSKeZxh8WiP.sDnC`

**¿Cuál es la contraseña sin encriptar?** Necesitas decírmela para documentarla.

## 🚨 ACCIÓN INMEDIATA

1. **Elimina los archivos que creé** (01, 02, 03, 04) 
2. **Usa TUS archivos reales** de las carpetas SBL_*
3. **Solo cambia** `USE iso17025;` por `USE sbl_sistema_interno;` en cada archivo antes de ejecutarlo

## 🔧 SCRIPT DE CAMBIO RÁPIDO

Para facilitar el cambio, puedes usar este comando en cada archivo antes de ejecutarlo en phpMyAdmin:

```sql
-- Agregar al inicio de cada archivo SQL:
DROP DATABASE IF EXISTS sbl_sistema_interno;
CREATE DATABASE sbl_sistema_interno CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sbl_sistema_interno;
```

**¿Prefieres que modifique tus archivos reales para cambiar la base de datos, o quieres hacerlo manualmente?**