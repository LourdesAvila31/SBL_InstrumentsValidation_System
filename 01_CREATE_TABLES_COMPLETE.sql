-- Archivo: 01_CREATE_TABLES_COMPLETE.sql
-- Descripción: Sistema SBL ISO 17025 - Solo funcionalidad interna con proveedores
-- Base de datos: sbl_sistema_interno
-- Fecha actualización: 26/09/2025
-- NOTA: Eliminadas todas las referencias a clientes y servicios externos

USE sbl_sistema_interno;

SET FOREIGN_KEY_CHECKS = 0;

-- Portal único interno
CREATE TABLE IF NOT EXISTS portals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(60) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    descripcion VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO portals (id, slug, nombre, descripcion)
VALUES (1, 'internal', 'Portal interno', 'Panel operativo para personal interno')
ON DUPLICATE KEY UPDATE
    nombre = VALUES(nombre),
    descripcion = VALUES(descripcion);

-- Roles internos únicamente
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    empresa_id INT DEFAULT NULL,
    portal_id INT DEFAULT 1,
    UNIQUE KEY uniq_roles_empresa_nombre (empresa_id, nombre),
    KEY idx_roles_portal (portal_id),
    FOREIGN KEY (portal_id) REFERENCES portals(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(250)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT,
    permission_id INT,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id),
    FOREIGN KEY (permission_id) REFERENCES permissions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Empresas internas únicamente
CREATE TABLE IF NOT EXISTS empresas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    contacto VARCHAR(100),
    direccion VARCHAR(250),
    telefono VARCHAR(50),
    email VARCHAR(150),
    responsable_calidad_id INT NULL,
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Usuarios internos únicamente
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(100) NOT NULL,
    correo VARCHAR(100) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100),
    puesto VARCHAR(150) NOT NULL DEFAULT 'Sin especificar',
    contrasena VARCHAR(255) NOT NULL,
    empresa_id INT NOT NULL,
    role_id INT NOT NULL,
    portal_id INT DEFAULT 1,
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_usuarios_usuario (usuario),
    UNIQUE KEY uq_usuarios_correo (correo),
    KEY idx_usuarios_portal (portal_id),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id),
    FOREIGN KEY (role_id) REFERENCES roles(id),
    FOREIGN KEY (portal_id) REFERENCES portals(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tokens de restablecimiento de contraseña
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expira_en DATETIME NOT NULL,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_password_resets_token (token_hash),
    INDEX idx_password_resets_usuario (usuario_id),
    INDEX idx_password_resets_expira (expira_en),
    CONSTRAINT fk_password_resets_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Departamentos por empresa
CREATE TABLE IF NOT EXISTS departamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    empresa_id INT NOT NULL,
    UNIQUE KEY uq_departamentos_empresa (nombre, empresa_id),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Marcas por empresa
CREATE TABLE IF NOT EXISTS marcas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    empresa_id INT NOT NULL,
    UNIQUE KEY uq_marcas_empresa (nombre, empresa_id),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Modelos por empresa
CREATE TABLE IF NOT EXISTS modelos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    marca_id INT,
    empresa_id INT NOT NULL,
    UNIQUE KEY uq_modelos_marca (nombre, marca_id, empresa_id),
    FOREIGN KEY (marca_id) REFERENCES marcas(id),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Catálogo de instrumentos por empresa
CREATE TABLE IF NOT EXISTS catalogo_instrumentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    empresa_id INT NOT NULL,
    UNIQUE KEY uq_catalogo_empresa (nombre, empresa_id),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Instrumentos
CREATE TABLE IF NOT EXISTS instrumentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    catalogo_id INT,
    marca_id INT,
    modelo_id INT,
    serie VARCHAR(100),
    codigo VARCHAR(100),
    departamento_id INT,
    ubicacion VARCHAR(150),
    fecha_alta DATE,
    fecha_baja DATE,
    proxima_calibracion DATE,
    estado VARCHAR(20),
    programado TINYINT(1) DEFAULT 0,
    empresa_id INT NOT NULL,
    FOREIGN KEY (catalogo_id) REFERENCES catalogo_instrumentos(id),
    FOREIGN KEY (marca_id) REFERENCES marcas(id),
    FOREIGN KEY (modelo_id) REFERENCES modelos(id),
    FOREIGN KEY (departamento_id) REFERENCES departamentos(id),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id),
    UNIQUE KEY uq_instrumentos_codigo_empresa (codigo, empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Proveedores (ÚNICA funcionalidad externa permitida)
CREATE TABLE IF NOT EXISTS proveedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    direccion VARCHAR(250),
    telefono VARCHAR(50),
    email VARCHAR(150),
    empresa_id INT NOT NULL,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS proveedor_contactos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proveedor_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    puesto VARCHAR(100),
    correo VARCHAR(100),
    telefono VARCHAR(50),
    empresa_id INT NOT NULL,
    FOREIGN KEY (proveedor_id) REFERENCES proveedores(id),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Catálogo de patrones de referencia
CREATE TABLE IF NOT EXISTS patrones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    codigo VARCHAR(100) DEFAULT NULL,
    descripcion TEXT,
    certificado_numero VARCHAR(150) DEFAULT NULL,
    certificado_archivo VARCHAR(255) DEFAULT NULL,
    fecha_emision DATE DEFAULT NULL,
    fecha_vencimiento DATE DEFAULT NULL,
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Calibraciones
CREATE TABLE IF NOT EXISTS calibraciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    instrumento_id INT,
    empresa_id INT NOT NULL,
    patron_id INT NULL,
    patron_certificado VARCHAR(255) NULL,
    tipo VARCHAR(20) NOT NULL DEFAULT 'Interna',
    fecha_calibracion DATE NOT NULL,
    duracion_horas DECIMAL(8,2) NULL,
    costo_total DECIMAL(12,2) NULL,
    periodo ENUM('P1','P2','EXTRA') NOT NULL DEFAULT 'P1',
    fecha_proxima DATE,
    proveedor_id INT,
    usuario_id INT,
    estado ENUM('Pendiente','Aprobado','Rechazado') NOT NULL DEFAULT 'Pendiente',
    resultado_preliminar VARCHAR(50) DEFAULT NULL,
    resultado VARCHAR(50) DEFAULT NULL,
    liberado_por INT,
    fecha_liberacion DATETIME,
    observaciones TEXT,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (instrumento_id) REFERENCES instrumentos(id),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id),
    FOREIGN KEY (proveedor_id) REFERENCES proveedores(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (liberado_por) REFERENCES usuarios(id),
    FOREIGN KEY (patron_id) REFERENCES patrones(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Historiales
CREATE TABLE IF NOT EXISTS historial_departamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    instrumento_id INT,
    departamento_id INT,
    empresa_id INT NOT NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (instrumento_id) REFERENCES instrumentos(id),
    FOREIGN KEY (departamento_id) REFERENCES departamentos(id),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS historial_ubicaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    instrumento_id INT,
    ubicacion VARCHAR(150),
    empresa_id INT NOT NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (instrumento_id) REFERENCES instrumentos(id),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Requerimientos y planes de riesgo
CREATE TABLE IF NOT EXISTS requerimientos_calibracion (
    instrumento_id INT PRIMARY KEY,
    empresa_id INT NOT NULL,
    requerimiento TEXT,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (instrumento_id) REFERENCES instrumentos(id),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS plan_riesgos (
    instrumento_id INT PRIMARY KEY,
    empresa_id INT NOT NULL,
    requerimiento VARCHAR(100) DEFAULT 'NA',
    impacto_falla VARCHAR(100) DEFAULT 'NA',
    consideraciones_falla VARCHAR(100) DEFAULT 'NA',
    clase_riesgo VARCHAR(100) DEFAULT 'NA',
    capacidad_deteccion VARCHAR(100) DEFAULT 'NA',
    frecuencia VARCHAR(100) DEFAULT 'NA',
    observaciones TEXT,
    tipo_calibracion VARCHAR(50) DEFAULT 'NA',
    especificaciones TEXT,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (instrumento_id) REFERENCES instrumentos(id),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Planes
CREATE TABLE IF NOT EXISTS planes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    instrumento_id INT,
    fecha_programada DATE,
    responsable_id INT,
    estado VARCHAR(20),
    empresa_id INT NOT NULL,
    FOREIGN KEY (instrumento_id) REFERENCES instrumentos(id),
    FOREIGN KEY (responsable_id) REFERENCES usuarios(id),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Reportes personalizados
CREATE TABLE IF NOT EXISTS reportes_personalizados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    instrumento_id INT,
    fecha_creacion DATE NOT NULL,
    usuario_id INT,
    empresa_id INT NOT NULL,
    configuracion JSON NULL,
    formato_preferido VARCHAR(50) DEFAULT 'excel',
    filtros JSON NULL,
    FOREIGN KEY (instrumento_id) REFERENCES instrumentos(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla relacional de calibraciones y planes
CREATE TABLE IF NOT EXISTS calibraciones_planes (
    calibracion_id INT,
    plan_id INT,
    PRIMARY KEY (calibracion_id, plan_id),
    FOREIGN KEY (calibracion_id) REFERENCES calibraciones(id),
    FOREIGN KEY (plan_id) REFERENCES planes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Certificados
CREATE TABLE IF NOT EXISTS certificados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    calibracion_id INT,
    archivo VARCHAR(255),
    tipo VARCHAR(20),
    hash VARCHAR(64),
    fecha_subida DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (calibracion_id) REFERENCES calibraciones(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Backups
CREATE TABLE IF NOT EXISTS backups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    archivo VARCHAR(255),
    estado VARCHAR(20)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Audit trail para auditoria interna
CREATE TABLE IF NOT EXISTS audit_trail (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL DEFAULT 1,
    fecha_evento DATETIME DEFAULT CURRENT_TIMESTAMP,
    seccion VARCHAR(150) NOT NULL,
    valor_anterior TEXT DEFAULT NULL,
    valor_nuevo TEXT DEFAULT NULL,
    usuario_id INT DEFAULT NULL,
    usuario_correo VARCHAR(150) NOT NULL,
    usuario_nombre VARCHAR(150) NOT NULL DEFAULT 'Administrador interno',
    instrumento_codigo VARCHAR(50) DEFAULT NULL,
    INDEX idx_audit_fecha_evento (fecha_evento),
    INDEX idx_audit_seccion (seccion),
    INDEX idx_audit_usuario_correo (usuario_correo),
    INDEX idx_audit_empresa (empresa_id),
    INDEX idx_audit_instrumento (instrumento_codigo),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Establecer relaciones FK adicionales
ALTER TABLE empresas 
    ADD CONSTRAINT fk_empresas_responsable 
    FOREIGN KEY (responsable_calidad_id) REFERENCES usuarios(id);

ALTER TABLE roles 
    ADD CONSTRAINT fk_roles_empresas 
    FOREIGN KEY (empresa_id) REFERENCES empresas(id);

-- Actualizar todos los roles para usar solo portal interno
UPDATE roles SET portal_id = 1 WHERE portal_id IS NULL OR portal_id != 1;

SET FOREIGN_KEY_CHECKS = 1;