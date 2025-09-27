-- Archivo: 02_INSERT_BASIC_DATA.sql
-- Descripción: Datos básicos para el sistema SBL ISO 17025
-- Base de datos: sbl_sistema_interno
-- Fecha: 26/09/2025

USE sbl_sistema_interno;

START TRANSACTION;

-- Insertar empresa principal SBL Pharma
INSERT INTO empresas (id, nombre, contacto, direccion, telefono, email, activo) 
VALUES (1, 'SBL Pharma', 'Administración', 'Dirección SBL Pharma', '555-0000', 'info@sblpharma.com', 1)
ON DUPLICATE KEY UPDATE 
    nombre = VALUES(nombre),
    contacto = VALUES(contacto);

-- Insertar roles básicos para portal interno
INSERT INTO roles (nombre, empresa_id, portal_id) VALUES
('Superadministrador', 1, 1),
('Developer', 1, 1),
('Administrador', 1, 1),
('Lector', 1, 1),
('Sistemas', 1, 1)
ON DUPLICATE KEY UPDATE 
    empresa_id = VALUES(empresa_id),
    portal_id = VALUES(portal_id);

-- Insertar permisos básicos
INSERT INTO permissions (nombre, descripcion) VALUES
('admin.full', 'Acceso completo de administración'),
('instruments.read', 'Leer instrumentos'),
('instruments.write', 'Escribir instrumentos'),
('calibrations.read', 'Leer calibraciones'),
('calibrations.write', 'Escribir calibraciones'),
('reports.generate', 'Generar reportes'),
('users.manage', 'Gestionar usuarios'),
('system.config', 'Configurar sistema')
ON DUPLICATE KEY UPDATE 
    descripcion = VALUES(descripcion);

-- Asignar permisos a roles
SET @role_superadmin = (SELECT id FROM roles WHERE nombre = 'Superadministrador' LIMIT 1);
SET @role_admin = (SELECT id FROM roles WHERE nombre = 'Administrador' LIMIT 1);
SET @role_lector = (SELECT id FROM roles WHERE nombre = 'Lector' LIMIT 1);

-- Superadministrador: todos los permisos
INSERT INTO role_permissions (role_id, permission_id)
SELECT @role_superadmin, id FROM permissions
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);

-- Administrador: permisos básicos
INSERT INTO role_permissions (role_id, permission_id)
SELECT @role_admin, id FROM permissions 
WHERE nombre IN ('instruments.read', 'instruments.write', 'calibrations.read', 'calibrations.write', 'reports.generate')
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);

-- Lector: solo lectura
INSERT INTO role_permissions (role_id, permission_id)
SELECT @role_lector, id FROM permissions 
WHERE nombre IN ('instruments.read', 'calibrations.read')
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);

COMMIT;

-- NOTA: Los departamentos, marcas e instrumentos están en archivos separados
-- para mantener la integridad de los datos reales completos de SBL.