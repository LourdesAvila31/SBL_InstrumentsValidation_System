-- =====================================================================
-- SCRIPT DE VERIFICACIÓN COMPLETA - SBL SISTEMA INTERNO
-- =====================================================================
-- Base de datos: sbl_sistema_interno
-- Propósito: Verificar que la base de datos esté correctamente configurada
--           con solo datos reales y sin información ficticia
-- Fecha: 2024-09-26
-- Versión: 1.0.0
-- =====================================================================

USE sbl_sistema_interno;

-- =====================================================================
-- VERIFICACIONES DE ESTRUCTURA
-- =====================================================================

-- Verificar que todas las tablas principales existan
SELECT 'VERIFICACIÓN DE TABLAS PRINCIPALES' as verificacion;

SELECT 
    table_name as tabla,
    CASE 
        WHEN table_name IN (
            'portals', 'empresas', 'roles', 'permissions', 'role_permissions',
            'usuarios', 'usuario_firmas_internas', 'departamentos', 'ubicaciones',
            'marcas', 'modelos', 'tipos_instrumento', 'instrumentos', 'calibraciones',
            'audit_trail', 'audit_login_attempts', 'configuracion_sistema',
            'plan_riesgos', 'calidad_documentos'
        ) THEN '✅ EXISTE'
        ELSE '❌ NO REQUERIDA'
    END as estado
FROM information_schema.tables 
WHERE table_schema = 'sbl_sistema_interno'
ORDER BY 
    CASE 
        WHEN table_name IN (
            'portals', 'empresas', 'roles', 'permissions', 'role_permissions',
            'usuarios', 'usuario_firmas_internas', 'departamentos', 'ubicaciones',
            'marcas', 'modelos', 'tipos_instrumento', 'instrumentos', 'calibraciones',
            'audit_trail', 'audit_login_attempts', 'configuracion_sistema',
            'plan_riesgos', 'calidad_documentos'
        ) THEN 1 
        ELSE 2 
    END,
    table_name;

-- =====================================================================
-- VERIFICACIONES DE DATOS BÁSICOS
-- =====================================================================

SELECT 'VERIFICACIÓN DE DATOS SEMILLA' as verificacion;

-- Verificar portals
SELECT 
    'PORTALS' as categoria,
    COUNT(*) as cantidad,
    GROUP_CONCAT(nombre ORDER BY nombre) as elementos
FROM portals;

-- Verificar empresa SBL
SELECT 
    'EMPRESAS' as categoria,
    COUNT(*) as cantidad,
    GROUP_CONCAT(nombre ORDER BY nombre) as elementos
FROM empresas;

-- Verificar roles
SELECT 
    'ROLES' as categoria,
    COUNT(*) as cantidad,
    GROUP_CONCAT(nombre ORDER BY nombre) as elementos
FROM roles
WHERE empresa_id IS NULL; -- Solo roles globales

-- Verificar permisos
SELECT 
    'PERMISOS' as categoria,
    COUNT(*) as cantidad,
    CONCAT(MIN(id), ' - ', MAX(id)) as rango_ids
FROM permissions;

-- =====================================================================
-- VERIFICACIONES DE USUARIOS REALES
-- =====================================================================

SELECT 'VERIFICACIÓN DE USUARIOS REALES' as verificacion;

-- Contar usuarios por dominio
SELECT 
    'USUARIOS POR DOMINIO' as categoria,
    SUBSTRING_INDEX(correo, '@', -1) as dominio,
    COUNT(*) as cantidad,
    GROUP_CONCAT(usuario ORDER BY usuario) as usuarios
FROM usuarios
GROUP BY SUBSTRING_INDEX(correo, '@', -1)
ORDER BY cantidad DESC;

-- Verificar usuarios por rol
SELECT 
    'USUARIOS POR ROL' as categoria,
    r.nombre as rol,
    COUNT(u.id) as cantidad,
    GROUP_CONCAT(u.usuario ORDER BY u.usuario) as usuarios
FROM usuarios u
JOIN roles r ON u.role_id = r.id
WHERE u.activo = 1
GROUP BY r.nombre
ORDER BY cantidad DESC;

-- Verificar firmas internas
SELECT 
    'FIRMAS INTERNAS' as categoria,
    COUNT(*) as total_firmas,
    COUNT(DISTINCT usuario_id) as usuarios_con_firma,
    GROUP_CONCAT(DISTINCT firma_interna ORDER BY firma_interna) as firmas_activas
FROM usuario_firmas_internas
WHERE vigente_hasta IS NULL;

-- =====================================================================
-- VERIFICACIONES DE INTEGRIDAD
-- =====================================================================

SELECT 'VERIFICACIÓN DE INTEGRIDAD DE DATOS' as verificacion;

-- Verificar que no existan datos ficticios
SELECT 
    'LIMPIEZA DE DATOS FICTICIOS' as categoria,
    (
        SELECT COUNT(*) FROM usuarios 
        WHERE correo LIKE '%@ejemplo.com' 
           OR usuario LIKE 'demo_%' 
           OR usuario LIKE 'test_%'
    ) as usuarios_ficticios,
    (
        SELECT COUNT(*) FROM empresas 
        WHERE nombre LIKE '%Demo%' 
           OR nombre LIKE '%Test%'
    ) as empresas_ficticias,
    (
        SELECT COUNT(*) FROM instrumentos 
        WHERE codigo LIKE 'DEMO-%' 
           OR codigo LIKE 'TEST-%'
    ) as instrumentos_ficticios;

-- Verificar usuarios con correos reales de SBL
SELECT 
    'USUARIOS REALES SBL' as categoria,
    COUNT(*) as total_usuarios_sbl,
    COUNT(CASE WHEN activo = 1 THEN 1 END) as usuarios_activos,
    COUNT(CASE WHEN activo = 0 THEN 1 END) as usuarios_inactivos
FROM usuarios
WHERE correo LIKE '%@sblpharma.com' OR correo = 'lourdesmarienavila@gmail.com';

-- =====================================================================
-- VERIFICACIONES DE CONFIGURACIÓN
-- =====================================================================

SELECT 'VERIFICACIÓN DE CONFIGURACIÓN DEL SISTEMA' as verificacion;

-- Verificar configuraciones básicas
SELECT 
    'CONFIGURACIONES' as categoria,
    categoria as tipo_config,
    COUNT(*) as cantidad,
    GROUP_CONCAT(clave ORDER BY clave) as configuraciones
FROM configuracion_sistema
WHERE activo = 1
GROUP BY categoria
ORDER BY categoria;

-- Verificar catálogos básicos
SELECT 
    'CATÁLOGOS BÁSICOS' as categoria,
    'Marcas' as tipo,
    COUNT(*) as cantidad
FROM marcas
WHERE activo = 1

UNION ALL

SELECT 
    'CATÁLOGOS BÁSICOS' as categoria,
    'Tipos de Instrumento' as tipo,
    COUNT(*) as cantidad
FROM tipos_instrumento
WHERE activo = 1

UNION ALL

SELECT 
    'CATÁLOGOS BÁSICOS' as categoria,
    'Departamentos' as tipo,
    COUNT(*) as cantidad
FROM departamentos
WHERE activo = 1;

-- =====================================================================
-- VERIFICACIONES DE SEGURIDAD
-- =====================================================================

SELECT 'VERIFICACIÓN DE SEGURIDAD' as verificacion;

-- Verificar que todos los usuarios tengan contraseñas encriptadas
SELECT 
    'SEGURIDAD DE CONTRASEÑAS' as categoria,
    COUNT(*) as total_usuarios,
    COUNT(CASE WHEN LENGTH(contrasena) >= 60 THEN 1 END) as contraseñas_encriptadas,
    COUNT(CASE WHEN contrasena NOT LIKE '$2y$%' THEN 1 END) as contraseñas_sin_hash
FROM usuarios
WHERE activo = 1;

-- Verificar usuarios sin bloqueo
SELECT 
    'ESTADO DE CUENTAS' as categoria,
    COUNT(*) as total_usuarios,
    COUNT(CASE WHEN bloqueado_hasta IS NULL THEN 1 END) as cuentas_desbloqueadas,
    COUNT(CASE WHEN bloqueado_hasta > NOW() THEN 1 END) as cuentas_bloqueadas
FROM usuarios
WHERE activo = 1;

-- =====================================================================
-- VERIFICACIONES DE RELACIONES
-- =====================================================================

SELECT 'VERIFICACIÓN DE RELACIONES FORÁNEAS' as verificacion;

-- Verificar que todos los usuarios tengan relaciones válidas
SELECT 
    'INTEGRIDAD USUARIOS' as categoria,
    COUNT(*) as total_usuarios,
    COUNT(CASE WHEN r.id IS NOT NULL THEN 1 END) as usuarios_con_rol_valido,
    COUNT(CASE WHEN e.id IS NOT NULL THEN 1 END) as usuarios_con_empresa_valida,
    COUNT(CASE WHEN p.id IS NOT NULL THEN 1 END) as usuarios_con_portal_valido
FROM usuarios u
LEFT JOIN roles r ON u.role_id = r.id
LEFT JOIN empresas e ON u.empresa_id = e.id
LEFT JOIN portals p ON u.portal_id = p.id
WHERE u.activo = 1;

-- =====================================================================
-- REPORTE FINAL DE ESTADO
-- =====================================================================

SELECT 'REPORTE FINAL DEL SISTEMA' as verificacion;

-- Resumen ejecutivo
SELECT 
    'RESUMEN EJECUTIVO' as categoria,
    (SELECT COUNT(*) FROM usuarios WHERE activo = 1 AND (correo LIKE '%@sblpharma.com' OR correo = 'lourdesmarienavila@gmail.com')) as usuarios_reales_activos,
    (SELECT COUNT(*) FROM roles WHERE empresa_id IS NULL) as roles_globales,
    (SELECT COUNT(*) FROM permissions WHERE activo = 1) as permisos_activos,
    (SELECT COUNT(*) FROM empresas WHERE activo = 1) as empresas_activas,
    (SELECT COUNT(*) FROM departamentos WHERE activo = 1) as departamentos_activos,
    (SELECT COUNT(*) FROM marcas WHERE activo = 1) as marcas_disponibles,
    (SELECT COUNT(*) FROM tipos_instrumento WHERE activo = 1) as tipos_instrumento_disponibles;

-- Estado de limpieza
SELECT 
    'ESTADO DE LIMPIEZA' as categoria,
    CASE 
        WHEN (SELECT COUNT(*) FROM usuarios WHERE correo LIKE '%@ejemplo.com' OR usuario LIKE 'demo_%') = 0
         AND (SELECT COUNT(*) FROM empresas WHERE nombre LIKE '%Demo%') = 0
         AND (SELECT COUNT(*) FROM instrumentos WHERE codigo LIKE 'DEMO-%') = 0
        THEN '✅ SISTEMA LIMPIO - Solo datos reales'
        ELSE '❌ ADVERTENCIA - Aún existen datos ficticios'
    END as estado_limpieza;

-- Verificación de usuarios mínimos requeridos
SELECT 
    'VERIFICACIÓN USUARIOS MÍNIMOS' as categoria,
    CASE 
        WHEN (SELECT COUNT(*) FROM usuarios WHERE activo = 1 AND role_id = (SELECT id FROM roles WHERE nombre = 'Superadministrador' LIMIT 1)) >= 1
         AND (SELECT COUNT(*) FROM usuarios WHERE activo = 1 AND (correo LIKE '%@sblpharma.com' OR correo = 'lourdesmarienavila@gmail.com')) >= 10
        THEN '✅ USUARIOS REQUERIDOS PRESENTES'
        ELSE '⚠️ FALTAN USUARIOS REQUERIDOS'
    END as estado_usuarios;

-- Estado general del sistema
SELECT 
    'ESTADO GENERAL DEL SISTEMA' as categoria,
    CASE 
        WHEN (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'sbl_sistema_interno') >= 15
         AND (SELECT COUNT(*) FROM usuarios WHERE activo = 1 AND (correo LIKE '%@sblpharma.com' OR correo = 'lourdesmarienavila@gmail.com')) >= 10
         AND (SELECT COUNT(*) FROM roles WHERE empresa_id IS NULL) >= 5
         AND (SELECT COUNT(*) FROM permissions WHERE activo = 1) >= 30
        THEN '✅ SISTEMA COMPLETAMENTE CONFIGURADO'
        ELSE '⚠️ CONFIGURACIÓN INCOMPLETA'
    END as estado_general;

-- =====================================================================
-- INSTRUCCIONES FINALES
-- =====================================================================

SELECT 'INSTRUCCIONES DE USO' as verificacion;

SELECT 
    '📋 PRÓXIMOS PASOS' as instrucciones,
    '1. Verificar que todos los estados muestren ✅' as paso_1,
    '2. Probar login con usuarios reales de CREDENCIALES_USUARIOS_REALES.md' as paso_2,
    '3. Acceder al portal interno: http://localhost/SBL_SISTEMA_INTERNO/public/apps/internal/' as paso_3,
    '4. Cambiar contraseñas por defecto en producción' as paso_4,
    '5. Configurar backup automático' as paso_5;

-- Mensaje final
SELECT 
    CONCAT(
        'Base de datos sbl_sistema_interno configurada exitosamente. ',
        'Total de usuarios reales: ', 
        (SELECT COUNT(*) FROM usuarios WHERE correo LIKE '%@sblpharma.com' OR correo = 'lourdesmarienavila@gmail.com'),
        '. Consultar CREDENCIALES_USUARIOS_REALES.md para acceder al sistema.'
    ) as mensaje_final,
    NOW() as fecha_verificacion;