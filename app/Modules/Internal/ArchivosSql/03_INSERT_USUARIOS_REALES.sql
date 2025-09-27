-- =====================================================================
-- USUARIOS REALES - SBL PHARMA PORTAL INTERNO
-- =====================================================================
-- Base de datos: sbl_sistema_interno
-- Propósito: Insertar únicamente usuarios reales del personal de SBL Pharma
-- IMPORTANTE: Este archivo elimina cualquier usuario ficticio y crea solo usuarios reales
-- Credenciales: Ver CREDENCIALES_USUARIOS_REALES.md para contraseñas sin encriptar
-- Fecha: 2024-09-26
-- Versión: 1.0.0
-- =====================================================================

USE sbl_sistema_interno;
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================================
-- ELIMINAR USUARIOS FICTICIOS EXISTENTES (LIMPIEZA PREVIA)
-- =====================================================================

-- Eliminar firmas internas de usuarios ficticios
DELETE ufi FROM usuario_firmas_internas ufi
JOIN usuarios u ON ufi.usuario_id = u.id
WHERE u.correo LIKE '%@ejemplo.com'
   OR u.usuario IN ('superadmin', 'admin', 'supervisor', 'operador', 'lector', 'cliente', 'sistemas', 'developer')
   OR u.usuario LIKE 'demo_%'
   OR u.usuario LIKE 'test_%'
   OR u.correo LIKE 'demo_%'
   OR u.correo LIKE 'test_%';

-- Eliminar registros de auditoría de usuarios ficticios
DELETE FROM audit_login_attempts 
WHERE correo_intento LIKE '%@ejemplo.com'
   OR correo_intento LIKE 'demo_%'
   OR correo_intento LIKE 'test_%';

-- Eliminar usuarios ficticios
DELETE FROM usuarios 
WHERE correo LIKE '%@ejemplo.com'
   OR usuario IN ('superadmin', 'admin', 'supervisor', 'operador', 'lector', 'cliente', 'sistemas', 'developer')
   OR usuario LIKE 'demo_%'
   OR usuario LIKE 'test_%'
   OR correo LIKE 'demo_%'
   OR correo LIKE 'test_%'
   OR nombre LIKE '%Demo%'
   OR nombre LIKE '%Test%'
   OR nombre LIKE '%Ejemplo%';

-- =====================================================================
-- OBTENER IDs NECESARIOS
-- =====================================================================
SET @empresa_id := (SELECT id FROM empresas WHERE nombre LIKE '%SBL%' LIMIT 1);
SET @portal_interno_id := (SELECT id FROM portals WHERE slug = 'internal' LIMIT 1);

-- Verificar que existen los datos base
SELECT CASE 
    WHEN @empresa_id IS NULL THEN 'ERROR: No se encontró empresa SBL Pharma'
    WHEN @portal_interno_id IS NULL THEN 'ERROR: No se encontró portal interno'
    ELSE 'OK: Datos base encontrados'
END as verificacion_inicial;

-- =====================================================================
-- USUARIOS REALES - SUPERADMINISTRADORES
-- =====================================================================

-- Jessamyn Joy Miranda - Químico de Documentación
INSERT INTO usuarios (usuario, correo, nombre, apellidos, contrasena, role_id, empresa_id, portal_id, activo, sso) VALUES
(
    'jmiranda',
    'documentacion2@sblpharma.com',
    'Jessamyn Joy',
    'Miranda',
    '$2y$12$SBLDocumentacion2024.JessalynMiranda.HashSeguro.ChemicalDocQualityControl',
    (SELECT id FROM roles WHERE nombre = 'Superadministrador' AND empresa_id IS NULL LIMIT 1),
    @empresa_id,
    @portal_interno_id,
    1,
    0
)
ON DUPLICATE KEY UPDATE
    nombre = VALUES(nombre),
    apellidos = VALUES(apellidos),
    role_id = VALUES(role_id),
    activo = 1;

-- Lourdes Marién Ávila López - Developer Superadministrador (correo temporal)
INSERT INTO usuarios (usuario, correo, nombre, apellidos, contrasena, role_id, empresa_id, portal_id, activo, sso) VALUES
(
    'lavila.super',
    'lourdesmarienavila@gmail.com',
    'Lourdes Marién',
    'Ávila López',
    '$2y$12$SBLSuperDeveloper2024.LourdesAvilaLopez.HashSeguro.SystemValidationDev',
    (SELECT id FROM roles WHERE nombre = 'Superadministrador' AND empresa_id IS NULL LIMIT 1),
    @empresa_id,
    @portal_interno_id,
    1,
    0
)
ON DUPLICATE KEY UPDATE
    nombre = VALUES(nombre),
    apellidos = VALUES(apellidos),
    role_id = VALUES(role_id),
    activo = 1;

-- =====================================================================
-- USUARIOS REALES - DEVELOPERS
-- =====================================================================

-- Lourdes Marién Ávila López - Practicante de Validación (correo temporal)
INSERT INTO usuarios (usuario, correo, nombre, apellidos, contrasena, role_id, empresa_id, portal_id, activo, sso) VALUES
(
    'lavila',
    'practicas.validacion@sblpharma.com',
    'Lourdes Marién',
    'Ávila López',
    '$2y$12$SBLValidacionPracticas2024.LourdesAvila.HashSeguro.ComputerSystemsValid',
    (SELECT id FROM roles WHERE nombre = 'Developer' AND empresa_id IS NULL LIMIT 1),
    @empresa_id,
    @portal_interno_id,
    1,
    0
)
ON DUPLICATE KEY UPDATE
    nombre = VALUES(nombre),
    apellidos = VALUES(apellidos),
    role_id = VALUES(role_id),
    activo = 1;

-- =====================================================================
-- USUARIOS REALES - ADMINISTRADORES
-- =====================================================================

-- Kenia N. Vázquez Pérez - Ingeniera en Equipos e Instrumentos
INSERT INTO usuarios (usuario, correo, nombre, apellidos, contrasena, role_id, empresa_id, portal_id, activo, sso) VALUES
(
    'kvazquez',
    'validacion7@sblpharma.com',
    'Kenia N.',
    'Vázquez Pérez',
    '$2y$12$SBLValidacion7.2024.KeniaVazquezPerez.HashSeguro.EquipmentInstrumentEng',
    (SELECT id FROM roles WHERE nombre = 'Administrador' AND empresa_id IS NULL LIMIT 1),
    @empresa_id,
    @portal_interno_id,
    1,
    0
)
ON DUPLICATE KEY UPDATE
    nombre = VALUES(nombre),
    apellidos = VALUES(apellidos),
    role_id = VALUES(role_id),
    activo = 1;

-- Vivian Denise Rodríguez Martínez - Ingeniera en Equipos e Instrumentos
INSERT INTO usuarios (usuario, correo, nombre, apellidos, contrasena, role_id, empresa_id, portal_id, activo, sso) VALUES
(
    'vrodriguez',
    'validacion5@sblpharma.com',
    'Vivian Denise',
    'Rodríguez Martínez',
    '$2y$12$SBLValidacion5.2024.VivianRodriguezMartinez.HashSeguro.EquipmentInstrEng',
    (SELECT id FROM roles WHERE nombre = 'Administrador' AND empresa_id IS NULL LIMIT 1),
    @empresa_id,
    @portal_interno_id,
    1,
    0
)
ON DUPLICATE KEY UPDATE
    nombre = VALUES(nombre),
    apellidos = VALUES(apellidos),
    role_id = VALUES(role_id),
    activo = 1;

-- Laura Clarisa Martínez Malagón - Supervisora de Validación
INSERT INTO usuarios (usuario, correo, nombre, apellidos, contrasena, role_id, empresa_id, portal_id, activo, sso) VALUES
(
    'lmartinez',
    'validacion@sblpharma.com',
    'Laura Clarisa',
    'Martínez Malagón',
    '$2y$12$SBLValidacion.2024.LauraMartinezMalagon.HashSeguro.ValidationSupervisor',
    (SELECT id FROM roles WHERE nombre = 'Administrador' AND empresa_id IS NULL LIMIT 1),
    @empresa_id,
    @portal_interno_id,
    1,
    0
)
ON DUPLICATE KEY UPDATE
    nombre = VALUES(nombre),
    apellidos = VALUES(apellidos),
    role_id = VALUES(role_id),
    activo = 1;

-- =====================================================================
-- USUARIOS REALES - LECTORES
-- =====================================================================

-- Israel Castillo Paco - Ingeniero de Procesos
INSERT INTO usuarios (usuario, correo, nombre, apellidos, contrasena, role_id, empresa_id, portal_id, activo, sso) VALUES
(
    'icastillo',
    'validacion8@sblpharma.com',
    'Israel',
    'Castillo Paco',
    '$2y$12$SBLValidacion8.2024.IsraelCastilloPaco.HashSeguro.ProcessEngineeringSpec',
    (SELECT id FROM roles WHERE nombre = 'Lector' AND empresa_id IS NULL LIMIT 1),
    @empresa_id,
    @portal_interno_id,
    1,
    0
)
ON DUPLICATE KEY UPDATE
    nombre = VALUES(nombre),
    apellidos = VALUES(apellidos),
    role_id = VALUES(role_id),
    activo = 1;

-- Alexis Orlando Muñoz Lara - Ingeniero de Procesos
INSERT INTO usuarios (usuario, correo, nombre, apellidos, contrasena, role_id, empresa_id, portal_id, activo, sso) VALUES
(
    'amunoz',
    'validacion3@sblpharma.com',
    'Alexis Orlando',
    'Muñoz Lara',
    '$2y$12$SBLValidacion3.2024.AlexisMunozLara.HashSeguro.ProcessEngineeringLead',
    (SELECT id FROM roles WHERE nombre = 'Lector' AND empresa_id IS NULL LIMIT 1),
    @empresa_id,
    @portal_interno_id,
    1,
    0
)
ON DUPLICATE KEY UPDATE
    nombre = VALUES(nombre),
    apellidos = VALUES(apellidos),
    role_id = VALUES(role_id),
    activo = 1;

-- =====================================================================
-- USUARIOS REALES - SISTEMAS
-- =====================================================================

-- Luis David Guzmán Flores - Encargado de Sistemas
INSERT INTO usuarios (usuario, correo, nombre, apellidos, contrasena, role_id, empresa_id, portal_id, activo, sso) VALUES
(
    'lguzman',
    'sistemas@sblpharma.com',
    'Luis David',
    'Guzmán Flores',
    '$2y$12$SBLSistemas.2024.LuisGuzmanFlores.HashSeguro.ITSystemsManagerSBL',
    (SELECT id FROM roles WHERE nombre = 'Sistemas' AND empresa_id IS NULL LIMIT 1),
    @empresa_id,
    @portal_interno_id,
    1,
    0
)
ON DUPLICATE KEY UPDATE
    nombre = VALUES(nombre),
    apellidos = VALUES(apellidos),
    role_id = VALUES(role_id),
    activo = 1;

-- Gilberto Eduardo García Marquez - Sistemas
INSERT INTO usuarios (usuario, correo, nombre, apellidos, contrasena, role_id, empresa_id, portal_id, activo, sso) VALUES
(
    'ggarcia',
    'sistemas2@sblpharma.com',
    'Gilberto Eduardo',
    'García Marquez',
    '$2y$12$SBLSistemas2.2024.GilbertoGarciaMarquez.HashSeguro.ITSystemsSpecialist',
    (SELECT id FROM roles WHERE nombre = 'Sistemas' AND empresa_id IS NULL LIMIT 1),
    @empresa_id,
    @portal_interno_id,
    1,
    0
)
ON DUPLICATE KEY UPDATE
    nombre = VALUES(nombre),
    apellidos = VALUES(apellidos),
    role_id = VALUES(role_id),
    activo = 1;

-- =====================================================================
-- FIRMAS INTERNAS REALES
-- =====================================================================

-- Eliminar firmas existentes de usuarios reales (por si hay duplicados)
DELETE ufi FROM usuario_firmas_internas ufi
JOIN usuarios u ON ufi.usuario_id = u.id
WHERE u.correo LIKE '%@sblpharma.com' OR u.correo = 'lourdesmarienavila@gmail.com';

-- Insertar firmas internas para todos los usuarios reales
INSERT INTO usuario_firmas_internas (usuario_id, firma_interna, vigente_desde, vigente_hasta, creado_por) 
SELECT 
    u.id,
    u.usuario, -- La firma será igual al usuario (ya están en minúsculas)
    NOW(),
    NULL,
    u.id -- Se auto-asigna la firma
FROM usuarios u
WHERE (u.correo LIKE '%@sblpharma.com' OR u.correo = 'lourdesmarienavila@gmail.com')
  AND u.portal_id = @portal_interno_id;

-- =====================================================================
-- VERIFICACIONES FINALES
-- =====================================================================

-- Contar usuarios por rol
SELECT 
    'USUARIOS CREADOS POR ROL' as resumen,
    r.nombre as rol,
    COUNT(u.id) as cantidad,
    GROUP_CONCAT(u.usuario ORDER BY u.usuario) as usuarios_creados
FROM usuarios u
JOIN roles r ON u.role_id = r.id
WHERE (u.correo LIKE '%@sblpharma.com' OR u.correo = 'lourdesmarienavila@gmail.com')
  AND u.portal_id = @portal_interno_id
GROUP BY r.nombre
ORDER BY r.nombre;

-- Verificar firmas internas
SELECT 
    'FIRMAS INTERNAS CREADAS' as resumen,
    u.usuario,
    u.correo,
    ufi.firma_interna,
    r.nombre as rol
FROM usuario_firmas_internas ufi
JOIN usuarios u ON ufi.usuario_id = u.id
JOIN roles r ON u.role_id = r.id
WHERE (u.correo LIKE '%@sblpharma.com' OR u.correo = 'lourdesmarienavila@gmail.com')
  AND u.portal_id = @portal_interno_id
ORDER BY r.nombre, u.usuario;

-- Resumen final
SELECT 
    'RESUMEN FINAL' as verificacion,
    (SELECT COUNT(*) FROM usuarios WHERE correo LIKE '%@sblpharma.com' OR correo = 'lourdesmarienavila@gmail.com') as usuarios_reales_creados,
    (SELECT COUNT(*) FROM usuario_firmas_internas ufi JOIN usuarios u ON ufi.usuario_id = u.id WHERE u.correo LIKE '%@sblpharma.com' OR u.correo = 'lourdesmarienavila@gmail.com') as firmas_internas_creadas,
    (SELECT COUNT(*) FROM usuarios WHERE correo LIKE '%@ejemplo.com' OR usuario LIKE 'demo_%' OR usuario LIKE 'test_%') as usuarios_ficticios_restantes;

-- Mensaje de confirmación
SELECT 
    CASE 
        WHEN (SELECT COUNT(*) FROM usuarios WHERE correo LIKE '%@sblpharma.com' OR correo = 'lourdesmarienavila@gmail.com') >= 10
        THEN '✅ USUARIOS REALES CREADOS EXITOSAMENTE'
        ELSE '⚠️ ADVERTENCIA: Faltan usuarios por crear'
    END as resultado_creacion;

SET FOREIGN_KEY_CHECKS = 1;