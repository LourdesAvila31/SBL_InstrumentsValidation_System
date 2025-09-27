-- =====================================================================
-- ARCHIVO ADAPTADO DEL REAL: SBL_adds/add_tables.sql
-- =====================================================================
-- Base de datos: sbl_sistema_interno (cambio de iso17025 -> sbl_sistema_interno)
-- Fuente: app/Modules/Internal/ArchivosSql/Archivos_BD_SBL/SBL_adds/add_tables.sql
-- Propósito: Sistema de gestión de calibraciones ISO 17025 con datos reales
-- Fecha adaptación: 2024-09-26
-- 
-- IMPORTANTE: Este archivo es una copia exacta de add_tables.sql
-- Solo se cambió iso17025 por sbl_sistema_interno
-- =====================================================================

CREATE DATABASE IF NOT EXISTS sbl_sistema_interno CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sbl_sistema_interno;

-- Este script puede ejecutarse tanto en instalaciones nuevas como en bases existentes.
-- Crea todas las tablas necesarias y alinea esquemas legados con la estructura multiempresa.
-- Script incremental para alinear bases existentes con la estructura multiempresa
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================================
-- TABLA: PORTALS
-- =====================================================================
CREATE TABLE IF NOT EXISTS portals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(60) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    descripcion VARCHAR(255) DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_portals_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TABLA: PORTAL_DOMAINS
-- =====================================================================
CREATE TABLE IF NOT EXISTS portal_domains (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain VARCHAR(255) NOT NULL,
    portal_id INT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_portal_domains_domain (domain),
    KEY idx_portal_domains_portal (portal_id, is_active),
    CONSTRAINT fk_portal_domains_portal FOREIGN KEY (portal_id) REFERENCES portals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TABLA: EMPRESAS
-- =====================================================================
CREATE TABLE IF NOT EXISTS empresas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    contacto VARCHAR(100) DEFAULT NULL,
    direccion TEXT DEFAULT NULL,
    telefono VARCHAR(20) DEFAULT NULL,
    email VARCHAR(100) DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_empresas_activo (activo),
    INDEX idx_empresas_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TABLA: ROLES
-- =====================================================================
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    empresa_id INT DEFAULT NULL,
    delegated TINYINT(1) NOT NULL DEFAULT 0,
    portal_id INT NOT NULL,
    descripcion TEXT DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_roles_empresa (empresa_id),
    KEY idx_roles_portal (portal_id),
    KEY idx_roles_activo (activo),
    CONSTRAINT fk_roles_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    CONSTRAINT fk_roles_portal FOREIGN KEY (portal_id) REFERENCES portals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TABLA: PERMISSIONS
-- =====================================================================
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT DEFAULT NULL,
    categoria VARCHAR(50) DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_permissions_categoria (categoria),
    INDEX idx_permissions_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TABLA: ROLE_PERMISSIONS
-- =====================================================================
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    fecha_asignacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_role_permission (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TABLA: USUARIOS
-- =====================================================================
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    correo VARCHAR(100) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    contrasena VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    empresa_id INT NOT NULL,
    portal_id INT NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    sso TINYINT(1) NOT NULL DEFAULT 0,
    fecha_ultimo_acceso DATETIME DEFAULT NULL,
    intentos_fallidos INT NOT NULL DEFAULT 0,
    bloqueado_hasta DATETIME DEFAULT NULL,
    token_reset VARCHAR(255) DEFAULT NULL,
    token_reset_expira DATETIME DEFAULT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_usuarios_correo (correo),
    INDEX idx_usuarios_empresa (empresa_id),
    INDEX idx_usuarios_portal (portal_id),
    INDEX idx_usuarios_rol (role_id),
    INDEX idx_usuarios_activo (activo),
    CONSTRAINT fk_usuarios_rol FOREIGN KEY (role_id) REFERENCES roles(id) ON UPDATE CASCADE,
    CONSTRAINT fk_usuarios_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON UPDATE CASCADE,
    CONSTRAINT fk_usuarios_portal FOREIGN KEY (portal_id) REFERENCES portals(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TABLA: USUARIO_FIRMAS_INTERNAS
-- =====================================================================
CREATE TABLE IF NOT EXISTS usuario_firmas_internas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    firma_interna VARCHAR(20) NOT NULL,
    vigente_desde DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    vigente_hasta DATETIME DEFAULT NULL,
    creado_por INT NOT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_firmas_usuario (usuario_id),
    INDEX idx_firmas_vigencia (vigente_desde, vigente_hasta),
    UNIQUE KEY uniq_firma_usuario_vigente (usuario_id, firma_interna, vigente_desde),
    CONSTRAINT fk_firmas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_firmas_creador FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TABLA: DEPARTAMENTOS
-- =====================================================================
CREATE TABLE IF NOT EXISTS departamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT DEFAULT NULL,
    empresa_id INT NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_departamentos_empresa (empresa_id),
    INDEX idx_departamentos_activo (activo),
    CONSTRAINT fk_departamentos_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TABLA: UBICACIONES
-- =====================================================================
CREATE TABLE IF NOT EXISTS ubicaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT DEFAULT NULL,
    departamento_id INT DEFAULT NULL,
    empresa_id INT NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ubicaciones_departamento (departamento_id),
    INDEX idx_ubicaciones_empresa (empresa_id),
    INDEX idx_ubicaciones_activo (activo),
    CONSTRAINT fk_ubicaciones_departamento FOREIGN KEY (departamento_id) REFERENCES departamentos(id) ON DELETE SET NULL,
    CONSTRAINT fk_ubicaciones_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TABLA: MARCAS
-- =====================================================================
CREATE TABLE IF NOT EXISTS marcas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_marcas_activo (activo),
    INDEX idx_marcas_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TABLA: MODELOS
-- =====================================================================
CREATE TABLE IF NOT EXISTS modelos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    marca_id INT NOT NULL,
    descripcion TEXT DEFAULT NULL,
    especificaciones JSON DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_modelos_marca (marca_id),
    INDEX idx_modelos_activo (activo),
    CONSTRAINT fk_modelos_marca FOREIGN KEY (marca_id) REFERENCES marcas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TABLA: TIPOS_INSTRUMENTO
-- =====================================================================
CREATE TABLE IF NOT EXISTS tipos_instrumento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT DEFAULT NULL,
    categoria VARCHAR(50) DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tipos_categoria (categoria),
    INDEX idx_tipos_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TABLA: INSTRUMENTOS
-- =====================================================================
CREATE TABLE IF NOT EXISTS instrumentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    nombre VARCHAR(200) NOT NULL,
    descripcion TEXT DEFAULT NULL,
    tipo_instrumento_id INT DEFAULT NULL,
    marca_id INT DEFAULT NULL,
    modelo_id INT DEFAULT NULL,
    numero_serie VARCHAR(100) DEFAULT NULL,
    ubicacion_id INT DEFAULT NULL,
    departamento_id INT DEFAULT NULL,
    empresa_id INT NOT NULL,
    estado ENUM('activo', 'inactivo', 'mantenimiento', 'calibracion', 'baja') NOT NULL DEFAULT 'activo',
    fecha_adquisicion DATE DEFAULT NULL,
    fecha_puesta_servicio DATE DEFAULT NULL,
    fecha_calibracion_vencimiento DATE DEFAULT NULL,
    frecuencia_calibracion_meses INT DEFAULT 12,
    criticidad ENUM('alta', 'media', 'baja') NOT NULL DEFAULT 'media',
    proveedor_calibracion VARCHAR(200) DEFAULT NULL,
    costo_calibracion DECIMAL(10,2) DEFAULT NULL,
    observaciones TEXT DEFAULT NULL,
    especificaciones JSON DEFAULT NULL,
    documentos JSON DEFAULT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_instrumentos_codigo (codigo),
    INDEX idx_instrumentos_tipo (tipo_instrumento_id),
    INDEX idx_instrumentos_marca (marca_id),
    INDEX idx_instrumentos_modelo (modelo_id),
    INDEX idx_instrumentos_ubicacion (ubicacion_id),
    INDEX idx_instrumentos_departamento (departamento_id),
    INDEX idx_instrumentos_empresa (empresa_id),
    INDEX idx_instrumentos_estado (estado),
    INDEX idx_instrumentos_vencimiento (fecha_calibracion_vencimiento),
    INDEX idx_instrumentos_criticidad (criticidad),
    CONSTRAINT fk_instrumentos_tipo FOREIGN KEY (tipo_instrumento_id) REFERENCES tipos_instrumento(id) ON DELETE SET NULL,
    CONSTRAINT fk_instrumentos_marca FOREIGN KEY (marca_id) REFERENCES marcas(id) ON DELETE SET NULL,
    CONSTRAINT fk_instrumentos_modelo FOREIGN KEY (modelo_id) REFERENCES modelos(id) ON DELETE SET NULL,
    CONSTRAINT fk_instrumentos_ubicacion FOREIGN KEY (ubicacion_id) REFERENCES ubicaciones(id) ON DELETE SET NULL,
    CONSTRAINT fk_instrumentos_departamento FOREIGN KEY (departamento_id) REFERENCES departamentos(id) ON DELETE SET NULL,
    CONSTRAINT fk_instrumentos_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TABLA: CALIBRACIONES
-- =====================================================================
CREATE TABLE IF NOT EXISTS calibraciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    instrumento_id INT NOT NULL,
    tipo_calibracion ENUM('inicial', 'periodica', 'extraordinaria', 'verificacion') NOT NULL DEFAULT 'periodica',
    fecha_calibracion DATE NOT NULL,
    fecha_vencimiento DATE NOT NULL,
    proveedor VARCHAR(200) NOT NULL,
    certificado_numero VARCHAR(100) DEFAULT NULL,
    costo DECIMAL(10,2) DEFAULT NULL,
    resultado ENUM('satisfactorio', 'no_satisfactorio', 'condicional') DEFAULT NULL,
    observaciones TEXT DEFAULT NULL,
    archivo_certificado VARCHAR(500) DEFAULT NULL,
    realizada_por INT DEFAULT NULL,
    aprobada_por INT DEFAULT NULL,
    fecha_aprobacion DATETIME DEFAULT NULL,
    estado ENUM('programada', 'en_proceso', 'completada', 'vencida', 'cancelada') NOT NULL DEFAULT 'programada',
    datos_calibracion JSON DEFAULT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_calibraciones_instrumento (instrumento_id),
    INDEX idx_calibraciones_fecha (fecha_calibracion),
    INDEX idx_calibraciones_vencimiento (fecha_vencimiento),
    INDEX idx_calibraciones_estado (estado),
    INDEX idx_calibraciones_realizada (realizada_por),
    INDEX idx_calibraciones_aprobada (aprobada_por),
    CONSTRAINT fk_calibraciones_instrumento FOREIGN KEY (instrumento_id) REFERENCES instrumentos(id) ON DELETE CASCADE,
    CONSTRAINT fk_calibraciones_realizada FOREIGN KEY (realizada_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_calibraciones_aprobada FOREIGN KEY (aprobada_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TABLA: AUDIT_TRAIL
-- =====================================================================
CREATE TABLE IF NOT EXISTS audit_trail (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tabla_afectada VARCHAR(100) NOT NULL,
    id_registro INT NOT NULL,
    accion ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
    datos_anteriores JSON DEFAULT NULL,
    datos_nuevos JSON DEFAULT NULL,
    usuario_id INT DEFAULT NULL,
    usuario_nombre VARCHAR(200) DEFAULT NULL,
    usuario_correo VARCHAR(100) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    fecha_accion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    observaciones TEXT DEFAULT NULL,
    INDEX idx_audit_tabla (tabla_afectada),
    INDEX idx_audit_registro (id_registro),
    INDEX idx_audit_usuario (usuario_id),
    INDEX idx_audit_fecha (fecha_accion),
    INDEX idx_audit_accion (accion),
    CONSTRAINT fk_audit_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TABLA: AUDIT_LOGIN_ATTEMPTS
-- =====================================================================
CREATE TABLE IF NOT EXISTS audit_login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT DEFAULT NULL,
    correo_intento VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    resultado ENUM('exitoso', 'fallido', 'bloqueado') NOT NULL,
    motivo_fallo VARCHAR(200) DEFAULT NULL,
    fecha_intento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_usuario (usuario_id),
    INDEX idx_login_correo (correo_intento),
    INDEX idx_login_fecha (fecha_intento),
    INDEX idx_login_resultado (resultado),
    CONSTRAINT fk_login_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TABLA: CONFIGURACION_SISTEMA
-- =====================================================================
CREATE TABLE IF NOT EXISTS configuracion_sistema (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(100) NOT NULL UNIQUE,
    valor TEXT DEFAULT NULL,
    descripcion TEXT DEFAULT NULL,
    categoria VARCHAR(50) DEFAULT NULL,
    tipo_dato ENUM('string', 'number', 'boolean', 'json') NOT NULL DEFAULT 'string',
    empresa_id INT DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_config_categoria (categoria),
    INDEX idx_config_empresa (empresa_id),
    INDEX idx_config_activo (activo),
    CONSTRAINT fk_config_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TABLA: PLAN_RIESGOS
-- =====================================================================
CREATE TABLE IF NOT EXISTS plan_riesgos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    instrumento_id INT NOT NULL,
    descripcion_riesgo TEXT NOT NULL,
    probabilidad ENUM('muy_baja', 'baja', 'media', 'alta', 'muy_alta') NOT NULL,
    impacto ENUM('muy_bajo', 'bajo', 'medio', 'alto', 'muy_alto') NOT NULL,
    nivel_riesgo ENUM('bajo', 'medio', 'alto', 'critico') NOT NULL,
    medidas_control TEXT DEFAULT NULL,
    responsable_id INT DEFAULT NULL,
    fecha_identificacion DATE NOT NULL,
    fecha_revision DATE DEFAULT NULL,
    estado ENUM('identificado', 'en_tratamiento', 'controlado', 'cerrado') NOT NULL DEFAULT 'identificado',
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_riesgos_instrumento (instrumento_id),
    INDEX idx_riesgos_nivel (nivel_riesgo),
    INDEX idx_riesgos_responsable (responsable_id),
    INDEX idx_riesgos_estado (estado),
    CONSTRAINT fk_riesgos_instrumento FOREIGN KEY (instrumento_id) REFERENCES instrumentos(id) ON DELETE CASCADE,
    CONSTRAINT fk_riesgos_responsable FOREIGN KEY (responsable_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TABLA: CALIDAD_DOCUMENTOS
-- =====================================================================
CREATE TABLE IF NOT EXISTS calidad_documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_documento VARCHAR(50) NOT NULL UNIQUE,
    titulo VARCHAR(200) NOT NULL,
    tipo_documento ENUM('procedimiento', 'instructivo', 'formato', 'manual', 'politica') NOT NULL,
    version VARCHAR(20) NOT NULL DEFAULT '1.0',
    estado ENUM('borrador', 'revision', 'aprobado', 'obsoleto') NOT NULL DEFAULT 'borrador',
    fecha_creacion_doc DATE NOT NULL,
    fecha_revision DATE DEFAULT NULL,
    fecha_aprobacion DATE DEFAULT NULL,
    fecha_vigencia DATE DEFAULT NULL,
    creado_por INT NOT NULL,
    revisado_por INT DEFAULT NULL,
    aprobado_por INT DEFAULT NULL,
    empresa_id INT NOT NULL,
    archivo_path VARCHAR(500) DEFAULT NULL,
    observaciones TEXT DEFAULT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_calidad_codigo (codigo_documento),
    INDEX idx_calidad_tipo (tipo_documento),
    INDEX idx_calidad_estado (estado),
    INDEX idx_calidad_empresa (empresa_id),
    INDEX idx_calidad_creador (creado_por),
    CONSTRAINT fk_calidad_creador FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON UPDATE CASCADE,
    CONSTRAINT fk_calidad_revisor FOREIGN KEY (revisado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_calidad_aprobador FOREIGN KEY (aprobado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_calidad_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reactivar verificaciones
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- MENSAJE DE CONFIRMACIÓN
-- =====================================================================
SELECT 'Estructura de base de datos creada exitosamente' as status,
       'sbl_sistema_interno' as base_datos,
       NOW() as fecha_creacion;