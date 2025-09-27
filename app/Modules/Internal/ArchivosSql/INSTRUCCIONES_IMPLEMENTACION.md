# 🚀 INSTRUCCIONES DE IMPLEMENTACIÓN - BASE DE DATOS SBL SISTEMA INTERNO

## 📋 ARCHIVOS CREADOS

Los siguientes archivos han sido creados para configurar completamente la base de datos desde cero:

1. **`01_CREATE_TABLES_COMPLETE.sql`** - Creación de todas las tablas
2. **`02_INSERT_SEED_DATA.sql`** - Datos semilla básicos del sistema
3. **`03_INSERT_USUARIOS_REALES.sql`** - Usuarios reales de SBL Pharma
4. **`04_VERIFICACION_COMPLETA.sql`** - Script de verificación
5. **`CREDENCIALES_USUARIOS_REALES.md`** - Credenciales sin encriptar

---

## ⚡ IMPLEMENTACIÓN RÁPIDA

### Paso 1: Preparar phpMyAdmin
1. Abre **phpMyAdmin** en: `http://localhost/phpmyadmin`
2. Asegúrate de que la base de datos `sbl_sistema_interno` esté **vacía** o **no exista**

### Paso 2: Ejecutar Scripts en Orden
Ejecuta los archivos SQL en **este orden exacto**:

#### 1️⃣ Crear Estructura
```sql
-- Archivo: 01_CREATE_TABLES_COMPLETE.sql
-- Acción: Crear todas las tablas desde cero
-- Tiempo estimado: 2-3 minutos
```

#### 2️⃣ Insertar Datos Básicos
```sql
-- Archivo: 02_INSERT_SEED_DATA.sql
-- Acción: Insertar portals, roles, permisos, configuraciones
-- Tiempo estimado: 1-2 minutos
```

#### 3️⃣ Crear Usuarios Reales
```sql
-- Archivo: 03_INSERT_USUARIOS_REALES.sql
-- Acción: Insertar todos los usuarios reales de SBL Pharma
-- Tiempo estimado: 1 minuto
```

#### 4️⃣ Verificar Configuración
```sql
-- Archivo: 04_VERIFICACION_COMPLETA.sql
-- Acción: Verificar que todo esté correctamente configurado
-- Tiempo estimado: 30 segundos
```

---

## 🔐 CREDENCIALES DE ACCESO

### Consultar Credenciales
Todas las credenciales sin encriptar están en: **`CREDENCIALES_USUARIOS_REALES.md`**

### Usuarios de Prueba Rápida

#### Superadministrador
- **Usuario:** `jmiranda`
- **Email:** `documentacion2@sblpharma.com`
- **Contraseña:** `DocuSBL2024*`

#### Developer
- **Usuario:** `lavila`
- **Email:** `practicas.validacion@sblpharma.com`
- **Contraseña:** `DevSBL2024*`

#### Administrador
- **Usuario:** `lmartinez`
- **Email:** `validacion@sblpharma.com`
- **Contraseña:** `SupSBL2024*`

---

## 🌐 ACCESO AL SISTEMA

### URLs de Acceso
- **Portal Interno:** `http://localhost/SBL_SISTEMA_INTERNO/public/apps/internal/`
- **Login:** `http://localhost/SBL_SISTEMA_INTERNO/public/apps/internal/usuarios/login.html`

### Verificación de Funcionamiento
1. Abrir la URL del portal interno
2. Usar cualquiera de las credenciales de **`CREDENCIALES_USUARIOS_REALES.md`**
3. Verificar que el dashboard cargue correctamente
4. Probar navegación entre módulos

---

## ✅ VERIFICACIONES OBLIGATORIAS

### Después de Ejecutar Scripts
Ejecuta estas consultas en phpMyAdmin para verificar:

#### Verificar Usuarios Reales
```sql
SELECT COUNT(*) as total_usuarios_reales 
FROM usuarios 
WHERE correo LIKE '%@sblpharma.com' OR correo = 'lourdesmarienavila@gmail.com';
-- Resultado esperado: 10 usuarios
```

#### Verificar Roles
```sql
SELECT nombre, COUNT(*) as usuarios_asignados 
FROM roles r
JOIN usuarios u ON r.id = u.role_id
GROUP BY r.nombre;
-- Debe mostrar usuarios en cada rol
```

#### Verificar Firmas Internas
```sql
SELECT COUNT(*) as total_firmas 
FROM usuario_firmas_internas;
-- Resultado esperado: 10 firmas
```

### Estados Esperados
Todos estos deben mostrar ✅:
- ✅ Estructura de base de datos creada exitosamente
- ✅ 10 usuarios reales creados
- ✅ Todas las firmas internas asignadas
- ✅ Sistema limpio - Solo datos reales
- ✅ Usuarios requeridos presentes
- ✅ Sistema completamente configurado

---

## 🛠️ SOLUCIÓN DE PROBLEMAS

### Error: "Tabla ya existe"
```sql
-- Eliminar base de datos y empezar de nuevo
DROP DATABASE IF EXISTS sbl_sistema_interno;
-- Luego ejecutar 01_CREATE_TABLES_COMPLETE.sql
```

### Error: "Foreign key constraint fails"
```sql
-- Verificar orden de ejecución de scripts
-- Asegurar que se ejecuten en el orden: 01 → 02 → 03 → 04
```

### Error de Login: "Usuario no encontrado"
1. Verificar que el script `03_INSERT_USUARIOS_REALES.sql` se ejecutó correctamente
2. Consultar **`CREDENCIALES_USUARIOS_REALES.md`** para credenciales exactas
3. Verificar que la base de datos sea `sbl_sistema_interno`

### Dashboard vacío o sin datos
1. Ejecutar el script `02_INSERT_SEED_DATA.sql` nuevamente
2. Verificar configuraciones del sistema
3. Comprobar permisos de usuario

---

## 📊 DATOS INCLUIDOS

### Usuarios por Rol
- **Superadministradores:** 2 usuarios
- **Developer:** 1 usuario  
- **Administradores:** 3 usuarios
- **Lectores:** 2 usuarios
- **Sistemas:** 2 usuarios
- **Total:** 10 usuarios reales

### Datos Base Incluidos
- ✅ 2 Portals (interno y cliente)
- ✅ 1 Empresa (SBL Pharma)
- ✅ 7 Departamentos reales
- ✅ 5 Ubicaciones
- ✅ 7 Roles globales
- ✅ 37 Permisos del sistema
- ✅ 10 Marcas de instrumentos
- ✅ 15 Tipos de instrumentos
- ✅ 10 Configuraciones básicas

---

## 🔒 SEGURIDAD Y CUMPLIMIENTO

### Características Implementadas
- ✅ **Encriptación BCrypt** para todas las contraseñas
- ✅ **Firmas internas** según normativa ISO 17025
- ✅ **Segregación de funciones** por rol
- ✅ **Trazabilidad completa** con audit_trail
- ✅ **Control de acceso** basado en permisos
- ✅ **Validación de integridad** de datos

### Recomendaciones Post-Implementación
1. **Cambiar contraseñas** por defecto
2. **Configurar backup automático**
3. **Activar logs de auditoría**
4. **Revisar permisos** periódicamente
5. **Implementar 2FA** en producción

---

## 📞 SOPORTE

### Contacto Técnico
- **Desarrollador:** Lourdes Marién Ávila López
- **Email:** `practicas.validacion@sblpharma.com`
- **Sistemas:** `sistemas@sblpharma.com`

### Archivos de Referencia
- **Credenciales:** `CREDENCIALES_USUARIOS_REALES.md`
- **Verificación:** `04_VERIFICACION_COMPLETA.sql`
- **Documentación:** Este archivo

---

**🚀 ¡La base de datos está lista para usar con datos reales de SBL Pharma!**

---

*Fecha de creación: 26 de septiembre de 2024*  
*Versión: 1.0.0*  
*Sistema: ISO 17025 - Gestión de Calibraciones*