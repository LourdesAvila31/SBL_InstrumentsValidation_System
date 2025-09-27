-- =====================================================================
-- DATOS SEMILLA - SBL SISTEMA INTERNO
-- =====================================================================
-- Base de datos: sbl_sistema_interno
-- Propósito: Insertar datos básicos necesarios para el funcionamiento
-- Incluye: Portals, Empresas, Roles, Permisos, Configuraciones básicas
-- IMPORTANTE: Solo datos reales, sin información ficticia
-- Fecha: 2024-09-26
-- Versión: 1.0.0
-- =====================================================================

USE sbl_sistema_interno;
SET FOREIGN_KEY_CHECKS = 0;

START TRANSACTION;

-- =====================================================================
-- PORTALS DEL SISTEMA
-- =====================================================================
INSERT INTO portals (id, slug, nombre, descripcion, activo) VALUES
(1, 'internal', 'Portal Interno SBL', 'Portal de gestión interna para personal de SBL Pharma', 1),
(2, 'client', 'Portal de Clientes', 'Portal para clientes externos de SBL Pharma', 1)
ON DUPLICATE KEY UPDATE 
    nombre = VALUES(nombre),
    descripcion = VALUES(descripcion),
    activo = VALUES(activo);

-- =====================================================================
-- DOMINIOS DE PORTALS
-- =====================================================================
INSERT INTO portal_domains (domain, portal_id, is_active, is_primary) VALUES
('localhost', 1, 1, 1),
('127.0.0.1', 1, 1, 0),
('sbl-interno.local', 1, 1, 0)
ON DUPLICATE KEY UPDATE 
    is_active = VALUES(is_active);

-- =====================================================================
-- EMPRESA PRINCIPAL - SBL PHARMA
-- =====================================================================
INSERT INTO empresas (id, nombre, contacto, direccion, telefono, email, activo) VALUES
(1, 'SBL Pharma', 'Administración General', 'Corporativo SBL Pharma', '555-0100', 'contacto@sblpharma.com', 1)
ON DUPLICATE KEY UPDATE 
    nombre = VALUES(nombre),
    contacto = VALUES(contacto),
    direccion = VALUES(direccion),
    telefono = VALUES(telefono),
    email = VALUES(email),
    activo = VALUES(activo);

-- =====================================================================
-- DEPARTAMENTOS REALES DE SBL PHARMA
-- =====================================================================
INSERT INTO departamentos (nombre, descripcion, empresa_id, activo) VALUES
('Validación', 'Departamento de Validación de Sistemas Computarizados', 1, 1),
('Documentación', 'Departamento de Documentación y Control de Calidad', 1, 1),
('Sistemas', 'Departamento de Tecnologías de la Información', 1, 1),
('Equipos e Instrumentos', 'Departamento de Equipos e Instrumentos de Medición', 1, 1),
('Procesos', 'Departamento de Ingeniería de Procesos', 1, 1),
('Calidad', 'Departamento de Aseguramiento de Calidad', 1, 1),
('Laboratorio', 'Laboratorio de Análisis', 1, 1)
ON DUPLICATE KEY UPDATE 
    descripcion = VALUES(descripcion),
    activo = VALUES(activo);

-- =====================================================================
-- UBICACIONES REALES
-- =====================================================================
INSERT INTO ubicaciones (nombre, descripcion, departamento_id, empresa_id, activo) VALUES
('Laboratorio Principal', 'Área principal de laboratorio', (SELECT id FROM departamentos WHERE nombre = 'Laboratorio' AND empresa_id = 1), 1, 1),
('Área de Validación', 'Zona dedicada a validación de equipos', (SELECT id FROM departamentos WHERE nombre = 'Validación' AND empresa_id = 1), 1, 1),
('Sala de Equipos', 'Almacén de instrumentos de medición', (SELECT id FROM departamentos WHERE nombre = 'Equipos e Instrumentos' AND empresa_id = 1), 1, 1),
('Oficinas Técnicas', 'Área administrativa técnica', (SELECT id FROM departamentos WHERE nombre = 'Sistemas' AND empresa_id = 1), 1, 1),
('Centro de Documentación', 'Archivo y control documental', (SELECT id FROM departamentos WHERE nombre = 'Documentación' AND empresa_id = 1), 1, 1)
ON DUPLICATE KEY UPDATE 
    descripcion = VALUES(descripcion),
    activo = VALUES(activo);

-- =====================================================================
-- ROLES DEL SISTEMA - GLOBALES (SIN EMPRESA)
-- =====================================================================
INSERT INTO roles (id, nombre, empresa_id, delegated, portal_id, descripcion, activo) VALUES
(1, 'Superadministrador', NULL, 0, 1, 'Acceso completo al sistema, configuración y administración', 1),
(2, 'Administrador', NULL, 0, 1, 'Administración operativa del sistema', 1),
(3, 'Supervisor', NULL, 0, 1, 'Supervisión de procesos y validación', 1),
(4, 'Analista', NULL, 0, 1, 'Análisis de datos y reportes', 1),
(5, 'Lector', NULL, 0, 1, 'Solo lectura y consulta de información', 1),
(6, 'Developer', NULL, 0, 1, 'Desarrollo y mantenimiento del sistema', 1),
(7, 'Sistemas', NULL, 0, 1, 'Administración técnica de infraestructura', 1)
ON DUPLICATE KEY UPDATE 
    descripcion = VALUES(descripcion),
    activo = VALUES(activo);

-- =====================================================================
-- PERMISOS DEL SISTEMA
-- =====================================================================
INSERT INTO permissions (id, nombre, descripcion, categoria, activo) VALUES
-- Administración de usuarios
(1, 'admin_usuarios', 'Administrar usuarios del sistema', 'usuarios', 1),
(2, 'ver_usuarios', 'Ver listado de usuarios', 'usuarios', 1),
(3, 'crear_usuarios', 'Crear nuevos usuarios', 'usuarios', 1),
(4, 'editar_usuarios', 'Editar usuarios existentes', 'usuarios', 1),
(5, 'eliminar_usuarios', 'Eliminar usuarios', 'usuarios', 1),

-- Administración de roles y permisos
(6, 'admin_roles', 'Administrar roles y permisos', 'roles', 1),
(7, 'ver_roles', 'Ver roles del sistema', 'roles', 1),
(8, 'asignar_permisos', 'Asignar permisos a roles', 'roles', 1),

-- Administración de empresas
(9, 'admin_empresas', 'Administrar empresas', 'empresas', 1),
(10, 'ver_empresas', 'Ver información de empresas', 'empresas', 1),

-- Dashboard y reportes
(11, 'ver_dashboard', 'Ver panel de control principal', 'dashboard', 1),
(12, 'ver_reportes', 'Ver reportes del sistema', 'reportes', 1),
(13, 'exportar_datos', 'Exportar datos del sistema', 'reportes', 1),

-- Instrumentos
(14, 'admin_instrumentos', 'Administración completa de instrumentos', 'instrumentos', 1),
(15, 'ver_instrumentos', 'Ver listado de instrumentos', 'instrumentos', 1),
(16, 'crear_instrumentos', 'Crear nuevos instrumentos', 'instrumentos', 1),
(17, 'editar_instrumentos', 'Editar instrumentos existentes', 'instrumentos', 1),
(18, 'eliminar_instrumentos', 'Eliminar instrumentos', 'instrumentos', 1),

-- Calibraciones
(19, 'admin_calibraciones', 'Administración completa de calibraciones', 'calibraciones', 1),
(20, 'ver_calibraciones', 'Ver listado de calibraciones', 'calibraciones', 1),
(21, 'crear_calibraciones', 'Crear nuevas calibraciones', 'calibraciones', 1),
(22, 'editar_calibraciones', 'Editar calibraciones', 'calibraciones', 1),
(23, 'eliminar_calibraciones', 'Eliminar calibraciones', 'calibraciones', 1),
(24, 'aprobar_calibraciones', 'Aprobar calibraciones realizadas', 'calibraciones', 1),

-- Certificados
(25, 'admin_certificados', 'Administrar certificados', 'certificados', 1),
(26, 'ver_certificados', 'Ver certificados', 'certificados', 1),
(27, 'subir_certificados', 'Subir archivos de certificados', 'certificados', 1),

-- Configuración del sistema
(28, 'admin_configuracion', 'Administrar configuración del sistema', 'configuracion', 1),
(29, 'ver_configuracion', 'Ver configuración del sistema', 'configuracion', 1),

-- Auditoría
(30, 'ver_auditoria', 'Ver registros de auditoría', 'auditoria', 1),
(31, 'admin_auditoria', 'Administrar sistema de auditoría', 'auditoria', 1),

-- Catálogos
(32, 'admin_catalogos', 'Administrar catálogos (marcas, modelos, tipos)', 'catalogos', 1),
(33, 'ver_catalogos', 'Ver catálogos del sistema', 'catalogos', 1),

-- Calidad
(34, 'admin_calidad', 'Administrar documentos de calidad', 'calidad', 1),
(35, 'ver_calidad', 'Ver documentos de calidad', 'calidad', 1),

-- Plan de riesgos
(36, 'admin_riesgos', 'Administrar plan de riesgos', 'riesgos', 1),
(37, 'ver_riesgos', 'Ver plan de riesgos', 'riesgos', 1)

ON DUPLICATE KEY UPDATE 
    descripcion = VALUES(descripcion),
    categoria = VALUES(categoria),
    activo = VALUES(activo);

-- =====================================================================
-- ASIGNACIÓN DE PERMISOS A ROLES
-- =====================================================================

-- SUPERADMINISTRADOR - TODOS LOS PERMISOS
INSERT INTO role_permissions (role_id, permission_id) 
SELECT 1, id FROM permissions WHERE activo = 1
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);

-- ADMINISTRADOR - PERMISOS OPERATIVOS
INSERT INTO role_permissions (role_id, permission_id) VALUES
(2, 1), (2, 2), (2, 3), (2, 4),        -- usuarios (sin eliminar)
(2, 7),                                  -- ver_roles
(2, 10),                                 -- ver_empresas
(2, 11), (2, 12), (2, 13),              -- dashboard, reportes, exportar
(2, 14), (2, 15), (2, 16), (2, 17), (2, 18), -- instrumentos completo
(2, 19), (2, 20), (2, 21), (2, 22), (2, 24), -- calibraciones (sin eliminar)
(2, 25), (2, 26), (2, 27),              -- certificados
(2, 29),                                 -- ver_configuracion
(2, 30),                                 -- ver_auditoria
(2, 32), (2, 33),                       -- catalogos
(2, 34), (2, 35),                       -- calidad
(2, 36), (2, 37)                        -- riesgos
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);

-- SUPERVISOR - PERMISOS DE SUPERVISIÓN
INSERT INTO role_permissions (role_id, permission_id) VALUES
(3, 2),                                  -- ver_usuarios
(3, 7),                                  -- ver_roles
(3, 11), (3, 12), (3, 13),              -- dashboard, reportes, exportar
(3, 15), (3, 17),                       -- ver/editar instrumentos
(3, 20), (3, 22), (3, 24),              -- ver/editar/aprobar calibraciones
(3, 26), (3, 27),                       -- ver/subir certificados
(3, 30),                                 -- ver_auditoria
(3, 33),                                 -- ver_catalogos
(3, 35),                                 -- ver_calidad
(3, 37)                                  -- ver_riesgos
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);

-- ANALISTA - PERMISOS DE ANÁLISIS
INSERT INTO role_permissions (role_id, permission_id) VALUES
(4, 2),                                  -- ver_usuarios
(4, 11), (4, 12), (4, 13),              -- dashboard, reportes, exportar
(4, 15), (4, 16), (4, 17),              -- ver/crear/editar instrumentos
(4, 20), (4, 21), (4, 22),              -- ver/crear/editar calibraciones
(4, 26), (4, 27),                       -- ver/subir certificados
(4, 33),                                 -- ver_catalogos
(4, 35),                                 -- ver_calidad
(4, 37)                                  -- ver_riesgos
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);

-- LECTOR - SOLO LECTURA
INSERT INTO role_permissions (role_id, permission_id) VALUES
(5, 2),                                  -- ver_usuarios
(5, 11), (5, 12),                       -- dashboard, reportes
(5, 15),                                 -- ver_instrumentos
(5, 20),                                 -- ver_calibraciones
(5, 26),                                 -- ver_certificados
(5, 33),                                 -- ver_catalogos
(5, 35),                                 -- ver_calidad
(5, 37)                                  -- ver_riesgos
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);

-- DEVELOPER - PERMISOS DE DESARROLLO
INSERT INTO role_permissions (role_id, permission_id) VALUES
(6, 1), (6, 2), (6, 3), (6, 4), (6, 5), -- usuarios completo
(6, 6), (6, 7), (6, 8),                 -- roles completo
(6, 9), (6, 10),                        -- empresas
(6, 28), (6, 29),                       -- configuracion completa
(6, 30), (6, 31),                       -- auditoria completa
(6, 32), (6, 33)                        -- catalogos
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);

-- SISTEMAS - PERMISOS TÉCNICOS
INSERT INTO role_permissions (role_id, permission_id) VALUES
(7, 2),                                  -- ver_usuarios
(7, 7),                                  -- ver_roles
(7, 10),                                 -- ver_empresas
(7, 11), (7, 12), (7, 13),              -- dashboard, reportes, exportar
(7, 28), (7, 29),                       -- configuracion completa
(7, 30), (7, 31),                       -- auditoria completa
(7, 32), (7, 33)                        -- catalogos
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);

-- =====================================================================
-- MARCAS COMUNES DE INSTRUMENTOS
-- =====================================================================
INSERT INTO marcas (nombre, descripcion, activo) VALUES
('Fluke', 'Instrumentos de medición eléctrica y electrónica', 1),
('Keysight', 'Equipos de medición y pruebas electrónicas', 1),
('Tektronix', 'Osciloscopios y equipos de medición', 1),
('Agilent', 'Instrumentos de medición científica', 1),
('Mettler Toledo', 'Balanzas y equipos de pesaje de precisión', 1),
('Sartorius', 'Instrumentos de laboratorio y pesaje', 1),
('Thermo Fisher', 'Equipos de laboratorio científico', 1),
('Waters', 'Sistemas de cromatografía', 1),
('Shimadzu', 'Instrumentos analíticos', 1),
('PerkinElmer', 'Instrumentos de análisis químico', 1)
ON DUPLICATE KEY UPDATE 
    descripcion = VALUES(descripcion),
    activo = VALUES(activo);

-- =====================================================================
-- TIPOS DE INSTRUMENTOS
-- =====================================================================
INSERT INTO tipos_instrumento (nombre, descripcion, categoria, activo) VALUES
('Balanza Analítica', 'Balanzas de alta precisión para análisis', 'Pesaje', 1),
('Balanza Granataria', 'Balanzas de precisión media', 'Pesaje', 1),
('pH Metro', 'Medidor de pH y acidez', 'Análisis Químico', 1),
('Conductímetro', 'Medidor de conductividad eléctrica', 'Análisis Químico', 1),
('Termómetro Digital', 'Medición de temperatura digital', 'Temperatura', 1),
('Termocupla', 'Sensor de temperatura por termocupla', 'Temperatura', 1),
('Manómetro', 'Medidor de presión', 'Presión', 1),
('Multímetro', 'Medidor eléctrico multifunción', 'Eléctrico', 1),
('Cronómetro', 'Medición de tiempo', 'Tiempo', 1),
('Vernier', 'Medición de dimensiones lineales', 'Dimensional', 1),
('Micrómetro', 'Medición de precisión dimensional', 'Dimensional', 1),
('Pipeta Automática', 'Dosificación de volúmenes precisos', 'Volumétrico', 1),
('Buretas', 'Medición de volúmenes en titulaciones', 'Volumétrico', 1),
('Espectrofotómetro', 'Análisis espectrofotométrico', 'Análisis Óptico', 1),
('HPLC', 'Cromatografía líquida de alta resolución', 'Cromatografía', 1)
ON DUPLICATE KEY UPDATE 
    descripcion = VALUES(descripcion),
    categoria = VALUES(categoria),
    activo = VALUES(activo);

-- =====================================================================
-- CONFIGURACIONES BÁSICAS DEL SISTEMA
-- =====================================================================
INSERT INTO configuracion_sistema (clave, valor, descripcion, categoria, tipo_dato, empresa_id, activo) VALUES
('sistema_nombre', 'SBL Sistema Interno ISO 17025', 'Nombre del sistema', 'general', 'string', 1, 1),
('sistema_version', '1.0.0', 'Versión actual del sistema', 'general', 'string', 1, 1),
('notificaciones_vencimiento_dias', '30', 'Días de anticipación para notificaciones de vencimiento', 'calibraciones', 'number', 1, 1),
('frecuencia_calibracion_default', '12', 'Frecuencia por defecto de calibración en meses', 'calibraciones', 'number', 1, 1),
('backup_automatico', 'true', 'Activar backup automático', 'sistema', 'boolean', 1, 1),
('audit_trail_activo', 'true', 'Activar registro de auditoría', 'auditoria', 'boolean', 1, 1),
('max_intentos_login', '3', 'Máximo número de intentos de login fallidos', 'seguridad', 'number', 1, 1),
('tiempo_bloqueo_minutos', '15', 'Tiempo de bloqueo tras intentos fallidos en minutos', 'seguridad', 'number', 1, 1),
('formatos_archivo_permitidos', '["pdf","jpg","jpeg","png","doc","docx","xls","xlsx"]', 'Formatos de archivo permitidos para subir', 'archivos', 'json', 1, 1),
('tamaño_maximo_archivo_mb', '10', 'Tamaño máximo de archivo en MB', 'archivos', 'number', 1, 1)
ON DUPLICATE KEY UPDATE 
    valor = VALUES(valor),
    descripcion = VALUES(descripcion);

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- VERIFICACIONES FINALES
-- =====================================================================
SELECT 'Datos semilla insertados correctamente' as status,
       (SELECT COUNT(*) FROM portals) as portals_creados,
       (SELECT COUNT(*) FROM empresas) as empresas_creadas,
       (SELECT COUNT(*) FROM roles) as roles_creados,
       (SELECT COUNT(*) FROM permissions) as permisos_creados,
       (SELECT COUNT(*) FROM role_permissions) as asignaciones_permisos,
       (SELECT COUNT(*) FROM departamentos) as departamentos_creados,
       (SELECT COUNT(*) FROM ubicaciones) as ubicaciones_creadas,
       (SELECT COUNT(*) FROM marcas) as marcas_creadas,
       (SELECT COUNT(*) FROM tipos_instrumento) as tipos_instrumento_creados,
       (SELECT COUNT(*) FROM configuracion_sistema) as configuraciones_creadas;