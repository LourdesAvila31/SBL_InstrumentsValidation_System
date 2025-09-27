-- 03_INSERT_USUARIOS_REALES_SBL.sql
-- USUARIOS COMPLETOS REALES de SBL Pharma para portal interno
-- Basado en insert_internal_portal_usuarios.sql con TODOS los datos reales
-- Contiene: 9 usuarios reales con firmas internas y roles completos

USE sbl_sistema_interno;
SET FOREIGN_KEY_CHECKS = 0;

START TRANSACTION;

-- Obtener IDs de roles y portal interno
SET @portal_internal := (SELECT id FROM portals WHERE slug = 'internal' LIMIT 1);
SET @role_superadmin := (SELECT id FROM roles WHERE nombre = 'Superadministrador' LIMIT 1);
SET @role_developer := (SELECT id FROM roles WHERE nombre = 'Developer' LIMIT 1);
SET @role_admin := (SELECT id FROM roles WHERE nombre = 'Administrador' LIMIT 1);
SET @role_lector := (SELECT id FROM roles WHERE nombre = 'Lector' LIMIT 1);
SET @role_sistemas := (SELECT id FROM roles WHERE nombre = 'Sistemas' LIMIT 1);

-- =============================================================================
-- USUARIOS REALES COMPLETOS DE SBL PHARMA (9 USUARIOS REALES)
-- =============================================================================
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
    sso
) VALUES
    ('lourdes.avila', 'lourdesmarienavila@gmail.com', 'Lourdes Marién', 'Ávila López', 'Superadministradora', '$2y$10$QFrroV/TUmhyJlX2LW7ILuAxxWHUvloWJtdBmWSKeZxh8WiP.sDnC', 1, @role_superadmin, @portal_internal, 1, 0),
    ('practicas.validacion', 'practicas.validacion@sblpharma.com', 'Lourdes Marién', 'Ávila López', 'Practicante de Validación de Sistemas Computarizados', '$2y$10$QFrroV/TUmhyJlX2LW7ILuAxxWHUvloWJtdBmWSKeZxh8WiP.sDnC', 1, @role_developer, @portal_internal, 1, 0),
    ('kenia.vazquez', 'validacion7@sblpharma.com', 'Kenia N.', 'Vázquez Pérez', 'Ingeniera en Equipos e Instrumentos', '$2y$10$QFrroV/TUmhyJlX2LW7ILuAxxWHUvloWJtdBmWSKeZxh8WiP.sDnC', 1, @role_admin, @portal_internal, 1, 0),
    ('vivian.rodriguez', 'validacion5@sblpharma.com', 'Vivian Denise', 'Rodríguez Martínez', 'Ingeniera en Equipos e Instrumentos', '$2y$10$QFrroV/TUmhyJlX2LW7ILuAxxWHUvloWJtdBmWSKeZxh8WiP.sDnC', 1, @role_admin, @portal_internal, 1, 0),
    ('laura.martinez', 'validacion@sblpharma.com', 'Laura Clarisa', 'Martínez Malagón', 'Supervisora de Validación', '$2y$10$QFrroV/TUmhyJlX2LW7ILuAxxWHUvloWJtdBmWSKeZxh8WiP.sDnC', 1, @role_admin, @portal_internal, 1, 0),
    ('israel.castillo', 'validacion8@sblpharma.com', 'Israel', 'Castillo Paco', 'Ingeniero de Procesos', '$2y$10$QFrroV/TUmhyJlX2LW7ILuAxxWHUvloWJtdBmWSKeZxh8WiP.sDnC', 1, @role_lector, @portal_internal, 1, 0),
    ('alexis.munoz', 'validacion3@sblpharma.com', 'Alexis Orlando', 'Muñoz Lara', 'Ingeniero de Procesos', '$2y$10$QFrroV/TUmhyJlX2LW7ILuAxxWHUvloWJtdBmWSKeZxh8WiP.sDnC', 1, @role_lector, @portal_internal, 1, 0),
    ('luis.guzman', 'sistemas@sblpharma.com', 'Luis David', 'Guzmán Flores', 'Especialista de Sistemas', '$2y$10$QFrroV/TUmhyJlX2LW7ILuAxxWHUvloWJtdBmWSKeZxh8WiP.sDnC', 1, @role_sistemas, @portal_internal, 1, 0),
    ('gilberto.garcia', 'sistemas2@sblpharma.com', 'Gilberto Eduardo', 'García Marquez', 'Especialista de Sistemas', '$2y$10$QFrroV/TUmhyJlX2LW7ILuAxxWHUvloWJtdBmWSKeZxh8WiP.sDnC', 1, @role_sistemas, @portal_internal, 1, 0)
ON DUPLICATE KEY UPDATE
    nombre = VALUES(nombre),
    apellidos = VALUES(apellidos),
    puesto = VALUES(puesto),
    role_id = VALUES(role_id),
    portal_id = VALUES(portal_id),
    empresa_id = VALUES(empresa_id),
    activo = VALUES(activo),
    sso = VALUES(sso),
    contrasena = VALUES(contrasena);

-- =============================================================================
-- FIRMAS INTERNAS REALES DE USUARIOS SBL
-- =============================================================================
-- Firma para Lourdes Avila (lourdes.avila)
INSERT INTO usuario_firmas_internas (empresa_id, usuario_id, correo, firma_interna, vigente_desde, vigente_hasta, creado_por)
SELECT 1, u.id, u.correo, 'lavila', NOW(), NULL, NULL
FROM usuarios u
WHERE u.correo = 'lourdesmarienavila@gmail.com'
  AND NOT EXISTS (
      SELECT 1 FROM usuario_firmas_internas fi WHERE fi.usuario_id = u.id AND fi.firma_interna = 'lavila'
  );

-- Firma para practicas.validacion (también usa lavila)
INSERT INTO usuario_firmas_internas (empresa_id, usuario_id, correo, firma_interna, vigente_desde, vigente_hasta, creado_por)
SELECT 1, u.id, u.correo, 'lavila', NOW(), NULL, NULL
FROM usuarios u
WHERE u.correo = 'practicas.validacion@sblpharma.com'
  AND NOT EXISTS (
      SELECT 1 FROM usuario_firmas_internas fi WHERE fi.usuario_id = u.id AND fi.firma_interna = 'lavila'
  );

-- Firma para Kenia Vázquez
INSERT INTO usuario_firmas_internas (empresa_id, usuario_id, correo, firma_interna, vigente_desde, vigente_hasta, creado_por)
SELECT 1, u.id, u.correo, 'kvazquez', NOW(), NULL, NULL
FROM usuarios u
WHERE u.correo = 'validacion7@sblpharma.com'
  AND NOT EXISTS (
      SELECT 1 FROM usuario_firmas_internas fi WHERE fi.usuario_id = u.id AND fi.firma_interna = 'kvazquez'
  );

-- Firma para Vivian Rodríguez
INSERT INTO usuario_firmas_internas (empresa_id, usuario_id, correo, firma_interna, vigente_desde, vigente_hasta, creado_por)
SELECT 1, u.id, u.correo, 'vrodriguez', NOW(), NULL, NULL
FROM usuarios u
WHERE u.correo = 'validacion5@sblpharma.com'
  AND NOT EXISTS (
      SELECT 1 FROM usuario_firmas_internas fi WHERE fi.usuario_id = u.id AND fi.firma_interna = 'vrodriguez'
  );

-- Firma para Laura Martínez
INSERT INTO usuario_firmas_internas (empresa_id, usuario_id, correo, firma_interna, vigente_desde, vigente_hasta, creado_por)
SELECT 1, u.id, u.correo, 'lmartinez', NOW(), NULL, NULL
FROM usuarios u
WHERE u.correo = 'validacion@sblpharma.com'
  AND NOT EXISTS (
      SELECT 1 FROM usuario_firmas_internas fi WHERE fi.usuario_id = u.id AND fi.firma_interna = 'lmartinez'
  );

-- Firma para Israel Castillo
INSERT INTO usuario_firmas_internas (empresa_id, usuario_id, correo, firma_interna, vigente_desde, vigente_hasta, creado_por)
SELECT 1, u.id, u.correo, 'icastillo', NOW(), NULL, NULL
FROM usuarios u
WHERE u.correo = 'validacion8@sblpharma.com'
  AND NOT EXISTS (
      SELECT 1 FROM usuario_firmas_internas fi WHERE fi.usuario_id = u.id AND fi.firma_interna = 'icastillo'
  );

-- Firma para Alexis Muñoz
INSERT INTO usuario_firmas_internas (empresa_id, usuario_id, correo, firma_interna, vigente_desde, vigente_hasta, creado_por)
SELECT 1, u.id, u.correo, 'amunoz', NOW(), NULL, NULL
FROM usuarios u
WHERE u.correo = 'validacion3@sblpharma.com'
  AND NOT EXISTS (
      SELECT 1 FROM usuario_firmas_internas fi WHERE fi.usuario_id = u.id AND fi.firma_interna = 'amunoz'
  );

-- Firma para Luis Guzmán
INSERT INTO usuario_firmas_internas (empresa_id, usuario_id, correo, firma_interna, vigente_desde, vigente_hasta, creado_por)
SELECT 1, u.id, u.correo, 'lguzman', NOW(), NULL, NULL
FROM usuarios u
WHERE u.correo = 'sistemas@sblpharma.com'
  AND NOT EXISTS (
      SELECT 1 FROM usuario_firmas_internas fi WHERE fi.usuario_id = u.id AND fi.firma_interna = 'lguzman'
  );

-- Firma para Gilberto García
INSERT INTO usuario_firmas_internas (empresa_id, usuario_id, correo, firma_interna, vigente_desde, vigente_hasta, creado_por)
SELECT 1, u.id, u.correo, 'ggarcia', NOW(), NULL, NULL
FROM usuarios u
WHERE u.correo = 'sistemas2@sblpharma.com'
  AND NOT EXISTS (
      SELECT 1 FROM usuario_firmas_internas fi WHERE fi.usuario_id = u.id AND fi.firma_interna = 'ggarcia'
  );

-- =============================================================================
-- ACTUALIZACIÓN DE FIRMAS PARA ASEGURAR VIGENCIA
-- =============================================================================
-- Asegurar vigencia y firma actualizada para Lourdes
UPDATE usuario_firmas_internas fi
JOIN usuarios u ON u.id = fi.usuario_id
SET fi.firma_interna = 'lavila', fi.correo = u.correo, fi.vigente_hasta = NULL
WHERE u.correo IN ('lourdesmarienavila@gmail.com', 'practicas.validacion@sblpharma.com');

-- Actualizar firmas para el resto de usuarios
UPDATE usuario_firmas_internas fi
JOIN usuarios u ON u.id = fi.usuario_id
SET fi.firma_interna = 'kvazquez', fi.correo = u.correo, fi.vigente_hasta = NULL
WHERE u.correo = 'validacion7@sblpharma.com';

UPDATE usuario_firmas_internas fi
JOIN usuarios u ON u.id = fi.usuario_id
SET fi.firma_interna = 'vrodriguez', fi.correo = u.correo, fi.vigente_hasta = NULL
WHERE u.correo = 'validacion5@sblpharma.com';

UPDATE usuario_firmas_internas fi
JOIN usuarios u ON u.id = fi.usuario_id
SET fi.firma_interna = 'lmartinez', fi.correo = u.correo, fi.vigente_hasta = NULL
WHERE u.correo = 'validacion@sblpharma.com';

UPDATE usuario_firmas_internas fi
JOIN usuarios u ON u.id = fi.usuario_id
SET fi.firma_interna = 'icastillo', fi.correo = u.correo, fi.vigente_hasta = NULL
WHERE u.correo = 'validacion8@sblpharma.com';

UPDATE usuario_firmas_internas fi
JOIN usuarios u ON u.id = fi.usuario_id
SET fi.firma_interna = 'amunoz', fi.correo = u.correo, fi.vigente_hasta = NULL
WHERE u.correo = 'validacion3@sblpharma.com';

UPDATE usuario_firmas_internas fi
JOIN usuarios u ON u.id = fi.usuario_id
SET fi.firma_interna = 'lguzman', fi.correo = u.correo, fi.vigente_hasta = NULL
WHERE u.correo = 'sistemas@sblpharma.com';

UPDATE usuario_firmas_internas fi
JOIN usuarios u ON u.id = fi.usuario_id
SET fi.firma_interna = 'ggarcia', fi.correo = u.correo, fi.vigente_hasta = NULL
WHERE u.correo = 'sistemas2@sblpharma.com';

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;