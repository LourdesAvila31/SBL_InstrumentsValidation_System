-- Archivo: 03_INSERT_USUARIOS_REALES_SBL.sql
-- Descripción: Usuarios reales de SBL Pharma (adaptado para sbl_sistema_interno)
-- Base de datos: sbl_sistema_interno  
-- Fuente: SBL_inserts/insert_internal_portal_usuarios.sql (adaptado)
-- Fecha: 26/09/2025

USE sbl_sistema_interno;

START TRANSACTION;

SET @portal_internal := (SELECT id FROM portals WHERE slug = 'internal' LIMIT 1);
SET @role_superadmin := (SELECT id FROM roles WHERE nombre = 'Superadministrador' LIMIT 1);
SET @role_developer := (SELECT id FROM roles WHERE nombre = 'Developer' LIMIT 1);
SET @role_admin := (SELECT id FROM roles WHERE nombre = 'Administrador' LIMIT 1);
SET @role_lector := (SELECT id FROM roles WHERE nombre = 'Lector' LIMIT 1);
SET @role_sistemas := (SELECT id FROM roles WHERE nombre = 'Sistemas' LIMIT 1);

INSERT INTO usuarios (
    usuario,
    correo,
    nombre,
    apellidos,
    puesto,
    contrasena,
    empresa_id,
    role_id,
    portal_id,
    activo,
    fecha_creacion
) VALUES
    ('lourdes.avila', 'lourdesmarienavila@gmail.com', 'Lourdes Marién', 'Ávila López', 'Superadministradora', '$2y$10$QFrroV/TUmhyJlX2LW7ILuAxxWHUvloWJtdBmWSKeZxh8WiP.sDnC', 1, @role_superadmin, @portal_internal, 1, NOW()),
    ('practicas.validacion', 'practicas.validacion@sblpharma.com', 'Lourdes Marién', 'Ávila López', 'Practicante de Validación de Sistemas Computarizados', '$2y$10$QFrroV/TUmhyJlX2LW7ILuAxxWHUvloWJtdBmWSKeZxh8WiP.sDnC', 1, @role_developer, @portal_internal, 1, NOW()),
    ('kenia.vazquez', 'validacion7@sblpharma.com', 'Kenia N.', 'Vázquez Pérez', 'Ingeniera en Equipos e Instrumentos', '$2y$10$QFrroV/TUmhyJlX2LW7ILuAxxWHUvloWJtdBmWSKeZxh8WiP.sDnC', 1, @role_admin, @portal_internal, 1, NOW()),
    ('vivian.rodriguez', 'validacion5@sblpharma.com', 'Vivian Denise', 'Rodríguez Martínez', 'Ingeniera en Equipos e Instrumentos', '$2y$10$QFrroV/TUmhyJlX2LW7ILuAxxWHUvloWJtdBmWSKeZxh8WiP.sDnC', 1, @role_admin, @portal_internal, 1, NOW()),
    ('laura.martinez', 'validacion@sblpharma.com', 'Laura Clarisa', 'Martínez Malagón', 'Supervisora de Validación', '$2y$10$QFrroV/TUmhyJlX2LW7ILuAxxWHUvloWJtdBmWSKeZxh8WiP.sDnC', 1, @role_admin, @portal_internal, 1, NOW()),
    ('israel.castillo', 'validacion8@sblpharma.com', 'Israel', 'Castillo Paco', 'Ingeniero de Procesos', '$2y$10$QFrroV/TUmhyJlX2LW7ILuAxxWHUvloWJtdBmWSKeZxh8WiP.sDnC', 1, @role_lector, @portal_internal, 1, NOW()),
    ('alexis.munoz', 'validacion6@sblpharma.com', 'Alexis', 'Muñoz López', 'Ingeniero en Equipos e Instrumentos', '$2y$10$QFrroV/TUmhyJlX2LW7ILuAxxWHUvloWJtdBmWSKeZxh8WiP.sDnC', 1, @role_lector, @portal_internal, 1, NOW()),
    ('luis.guzman', 'validacion2@sblpharma.com', 'Luis Alberto', 'Guzmán Hernández', 'Ingeniero en Equipos e Instrumentos', '$2y$10$QFrroV/TUmhyJlX2LW7ILuAxxWHUvloWJtdBmWSKeZxh8WiP.sDnC', 1, @role_lector, @portal_internal, 1, NOW()),
    ('gilberto.garcia', 'validacion4@sblpharma.com', 'Gilberto', 'García Viveros', 'Ingeniero de Procesos', '$2y$10$QFrroV/TUmhyJlX2LW7ILuAxxWHUvloWJtdBmWSKeZxh8WiP.sDnC', 1, @role_lector, @portal_internal, 1, NOW())
ON DUPLICATE KEY UPDATE
    nombre = VALUES(nombre),
    apellidos = VALUES(apellidos),
    puesto = VALUES(puesto),
    activo = VALUES(activo);

-- Actualizar responsable de calidad en la empresa
UPDATE empresas 
SET responsable_calidad_id = (SELECT id FROM usuarios WHERE usuario = 'laura.martinez' LIMIT 1)
WHERE id = 1;

COMMIT;