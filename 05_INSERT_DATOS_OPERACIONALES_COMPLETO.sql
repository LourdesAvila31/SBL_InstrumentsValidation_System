-- 05_INSERT_DATOS_OPERACIONALES_COMPLETO.sql
-- DATOS OPERACIONALES COMPLETOS REALES de SBL Pharma
-- Este archivo contiene calibraciones, certificados, historiales y plan de riesgos REALES
-- Total de más de 57,000 líneas de datos operacionales reales

USE sbl_sistema_interno;
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
-- CALIBRACIONES Y CERTIFICADOS COMPLETOS (5,968 líneas)
-- =============================================================================
-- Datos de insert_calibraciones_certificados.sql
-- Contiene todas las calibraciones históricas y programadas de instrumentos SBL

START TRANSACTION;

-- Importar datos de calibraciones desde archivo fuente
-- EJECUTAR MANUALMENTE: INSERT datos de calibraciones_certificados.sql aquí
-- Este archivo contiene 5,968 líneas de calibraciones reales desde CERT_instrumentos_original_v2.csv

SOURCE ./app/Modules/Internal/ArchivosSql/Archivos_BD_SBL/SBL_inserts/insert_calibraciones_certificados.sql;

COMMIT;

-- =============================================================================
-- PLAN DE RIESGOS COMPLETO (943 líneas)
-- =============================================================================
-- Datos de insert_plan_riesgos.sql
-- Contiene evaluación de riesgos para todos los instrumentos

START TRANSACTION;

-- Importar datos de plan de riesgos desde archivo fuente
-- EJECUTAR MANUALMENTE: INSERT datos de plan_riesgos.sql aquí
-- Este archivo contiene 943 líneas de evaluación de riesgos real

SOURCE ./app/Modules/Internal/ArchivosSql/Archivos_BD_SBL/SBL_inserts/insert_plan_riesgos.sql;

COMMIT;

-- =============================================================================
-- AUDIT TRAIL COMPLETO (50,886 líneas)
-- =============================================================================
-- Datos de insert_audit_trail.sql
-- Contiene historial completo de auditoría del sistema

START TRANSACTION;

-- Importar datos de audit trail desde archivo fuente
-- EJECUTAR MANUALMENTE: INSERT datos de audit_trail.sql aquí
-- Este archivo contiene 50,886 líneas de rastro de auditoría real

SOURCE ./app/Modules/Internal/ArchivosSql/Archivos_BD_SBL/SBL_inserts/insert_audit_trail.sql;

COMMIT;

-- =============================================================================
-- HISTORIALES DE INSTRUMENTOS (fechas e historiales)
-- =============================================================================
-- Datos de insert_fechas_instrumentos.sql e insert_historial_instrumentos.sql

START TRANSACTION;

-- Importar fechas de instrumentos
SOURCE ./app/Modules/Internal/ArchivosSql/Archivos_BD_SBL/SBL_inserts/insert_fechas_instrumentos.sql;

-- Importar historiales de instrumentos
SOURCE ./app/Modules/Internal/ArchivosSql/Archivos_BD_SBL/SBL_inserts/insert_historial_instrumentos.sql;

COMMIT;

-- =============================================================================
-- HISTORIALES ADICIONALES DE CAMBIOS
-- =============================================================================
-- Datos de archivos de historiales de cambios específicos

START TRANSACTION;

-- Importar historiales de ubicaciones
SOURCE ./app/Modules/Internal/ArchivosSql/Archivos_BD_SBL/SBL_inserts_historial/insert_historial_ubicaciones.sql;

-- Importar historiales de tipos de instrumento
SOURCE ./app/Modules/Internal/ArchivosSql/Archivos_BD_SBL/SBL_inserts_historial/insert_historial_tipos_instrumento.sql;

-- Importar historiales de fechas de alta
SOURCE ./app/Modules/Internal/ArchivosSql/Archivos_BD_SBL/SBL_inserts_historial/insert_historial_fecha_alta.sql;

-- Importar historiales de fechas de baja
SOURCE ./app/Modules/Internal/ArchivosSql/Archivos_BD_SBL/SBL_inserts_historial/insert_historial_fecha_baja.sql;

-- Importar historiales de departamentos
SOURCE ./app/Modules/Internal/ArchivosSql/Archivos_BD_SBL/SBL_inserts_historial/insert_historial_departamentos.sql;

COMMIT;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- RESUMEN DE DATOS OPERACIONALES INSERTADOS
-- =============================================================================
-- Calibraciones y certificados: 5,968 registros
-- Plan de riesgos: 943 registros  
-- Audit trail: 50,886 registros
-- Fechas de instrumentos: 9 registros
-- Historiales de instrumentos: 9 registros
-- Historiales de cambios: Multiple archivos con historiales específicos
-- 
-- TOTAL APROXIMADO: Más de 57,000 registros de datos operacionales reales
-- =============================================================================