<?php
/**
 * Script de Creación/Verificación de Tabla de Instrumentos
 * 
 * Este script verifica si existe la tabla de instrumentos y la crea si no existe
 * Compatible con los estándares ISO 17025 y NOM-059
 */

require_once __DIR__ . '/../app/Core/db.php';

try {
    $db = new Database();
    
    echo "🔧 VERIFICACIÓN Y CREACIÓN DE TABLA DE INSTRUMENTOS\n";
    echo "===================================================\n\n";
    
    // Verificar si la tabla existe
    $checkTable = $db->query("SHOW TABLES LIKE 'instrumentos'");
    $tableExists = $checkTable->rowCount() > 0;
    
    if ($tableExists) {
        echo "✅ La tabla 'instrumentos' ya existe.\n";
        
        // Verificar estructura
        $columns = $db->query("DESCRIBE instrumentos")->fetchAll(PDO::FETCH_ASSOC);
        echo "📋 Estructura actual:\n";
        foreach ($columns as $column) {
            echo "   - {$column['Field']} ({$column['Type']})\n";
        }
        
    } else {
        echo "⚠️  La tabla 'instrumentos' no existe. Creando...\n\n";
        
        // Crear tabla de instrumentos
        $createTableSQL = "
        CREATE TABLE `instrumentos` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `codigo_identificacion` varchar(50) NOT NULL UNIQUE COMMENT 'Código único del instrumento',
            `descripcion` text NOT NULL COMMENT 'Descripción del instrumento',
            `marca` varchar(100) DEFAULT NULL COMMENT 'Marca del instrumento',
            `modelo` varchar(100) DEFAULT NULL COMMENT 'Modelo del instrumento',
            `numero_serie` varchar(100) DEFAULT NULL COMMENT 'Número de serie',
            `rango_medicion` varchar(200) DEFAULT NULL COMMENT 'Rango de medición del instrumento',
            `resolucion` varchar(100) DEFAULT NULL COMMENT 'Resolución del instrumento',
            `incertidumbre` varchar(100) DEFAULT NULL COMMENT 'Incertidumbre de medición',
            `ubicacion` varchar(200) DEFAULT NULL COMMENT 'Ubicación física del instrumento',
            `estado` enum('ACTIVO','INACTIVO','MANTENIMIENTO','BAJA') DEFAULT 'ACTIVO' COMMENT 'Estado operativo',
            `fecha_ultima_calibracion` date DEFAULT NULL COMMENT 'Fecha de la última calibración',
            `fecha_proxima_calibracion` date DEFAULT NULL COMMENT 'Fecha programada para próxima calibración',
            `proveedor_calibracion` varchar(200) DEFAULT NULL COMMENT 'Proveedor de calibración',
            `certificado_calibracion` varchar(100) DEFAULT NULL COMMENT 'Número de certificado de calibración',
            `observaciones` text DEFAULT NULL COMMENT 'Observaciones adicionales',
            `fecha_registro` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro en el sistema',
            `usuario_registro` int(11) NOT NULL COMMENT 'Usuario que registró el instrumento',
            `fecha_modificacion` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última modificación',
            `usuario_modificacion` int(11) DEFAULT NULL COMMENT 'Usuario que modificó por última vez',
            `activo` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Registro activo (soft delete)',
            PRIMARY KEY (`id`),
            UNIQUE KEY `codigo_identificacion` (`codigo_identificacion`),
            KEY `idx_estado` (`estado`),
            KEY `idx_fecha_proxima_calibracion` (`fecha_proxima_calibracion`),
            KEY `idx_activo` (`activo`),
            KEY `idx_ubicacion` (`ubicacion`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla de instrumentos de medición - ISO 17025'";
        
        $db->exec($createTableSQL);
        echo "✅ Tabla 'instrumentos' creada exitosamente.\n\n";
    }
    
    // Verificar/crear tabla de auditoría
    $checkAuditTable = $db->query("SHOW TABLES LIKE 'auditoria_instrumentos'");
    $auditTableExists = $checkAuditTable->rowCount() > 0;
    
    if (!$auditTableExists) {
        echo "⚠️  Creando tabla de auditoría para instrumentos...\n";
        
        $createAuditTableSQL = "
        CREATE TABLE `auditoria_instrumentos` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `instrumento_id` int(11) NOT NULL COMMENT 'ID del instrumento afectado',
            `usuario_id` int(11) NOT NULL COMMENT 'Usuario que realizó la acción',
            `accion` enum('CREATE','UPDATE','DELETE','VIEW') NOT NULL COMMENT 'Tipo de acción realizada',
            `descripcion` text NOT NULL COMMENT 'Descripción de la acción',
            `datos_anteriores` json DEFAULT NULL COMMENT 'Datos antes del cambio',
            `datos_nuevos` json DEFAULT NULL COMMENT 'Datos después del cambio',
            `fecha_auditoria` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de la acción',
            `ip_address` varchar(45) DEFAULT NULL COMMENT 'Dirección IP del usuario',
            PRIMARY KEY (`id`),
            KEY `idx_instrumento_id` (`instrumento_id`),
            KEY `idx_usuario_id` (`usuario_id`),
            KEY `idx_fecha_auditoria` (`fecha_auditoria`),
            KEY `idx_accion` (`accion`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Auditoría de cambios en instrumentos'";
        
        $db->exec($createAuditTableSQL);
        echo "✅ Tabla 'auditoria_instrumentos' creada exitosamente.\n\n";
    } else {
        echo "✅ La tabla de auditoría 'auditoria_instrumentos' ya existe.\n\n";
    }
    
    // Insertar datos de prueba si la tabla está vacía
    $countInstruments = $db->query("SELECT COUNT(*) as total FROM instrumentos WHERE activo = 1")->fetch();
    
    if ($countInstruments['total'] == 0) {
        echo "📋 Insertando datos de prueba...\n";
        
        $sampleData = [
            [
                'codigo_identificacion' => 'BAL-001',
                'descripcion' => 'Balanza Analítica de Precisión',
                'marca' => 'Mettler Toledo',
                'modelo' => 'XP205',
                'numero_serie' => 'B123456789',
                'rango_medicion' => '0.01 mg - 220 g',
                'resolucion' => '0.01 mg',
                'incertidumbre' => '±0.02 mg',
                'ubicacion' => 'Laboratorio de Análisis - Mesa 1',
                'estado' => 'ACTIVO',
                'fecha_ultima_calibracion' => '2025-08-01',
                'fecha_proxima_calibracion' => '2026-02-01',
                'proveedor_calibracion' => 'Metrología Especializada S.A.',
                'certificado_calibracion' => 'CAL-2025-001',
                'observaciones' => 'Calibración semestral requerida'
            ],
            [
                'codigo_identificacion' => 'PH-001',
                'descripcion' => 'Medidor de pH Digital',
                'marca' => 'Hanna Instruments',
                'modelo' => 'HI-2020',
                'numero_serie' => 'PH987654321',
                'rango_medicion' => '0.00 - 14.00 pH',
                'resolucion' => '0.01 pH',
                'incertidumbre' => '±0.02 pH',
                'ubicacion' => 'Laboratorio Fisicoquímico - Estación 2',
                'estado' => 'ACTIVO',
                'fecha_ultima_calibracion' => '2025-09-01',
                'fecha_proxima_calibracion' => '2025-12-01',
                'proveedor_calibracion' => 'Laboratorio Nacional de Metrología',
                'certificado_calibracion' => 'CAL-2025-078',
                'observaciones' => 'Requiere buffer de calibración mensual'
            ],
            [
                'codigo_identificacion' => 'TERM-001',
                'descripcion' => 'Termómetro Digital de Precisión',
                'marca' => 'Fluke',
                'modelo' => '1524',
                'numero_serie' => 'T456789123',
                'rango_medicion' => '-200°C a +660°C',
                'resolucion' => '0.001°C',
                'incertidumbre' => '±0.05°C',
                'ubicacion' => 'Laboratorio de Temperatura',
                'estado' => 'ACTIVO',
                'fecha_ultima_calibracion' => '2025-07-15',
                'fecha_proxima_calibracion' => '2026-01-15',
                'proveedor_calibracion' => 'CENAM',
                'certificado_calibracion' => 'T-2025-0156',
                'observaciones' => 'Certificado con trazabilidad NIST'
            ]
        ];
        
        $insertSQL = "
        INSERT INTO instrumentos (
            codigo_identificacion, descripcion, marca, modelo, numero_serie,
            rango_medicion, resolucion, incertidumbre, ubicacion, estado,
            fecha_ultima_calibracion, fecha_proxima_calibracion, 
            proveedor_calibracion, certificado_calibracion, observaciones,
            usuario_registro
        ) VALUES (
            :codigo_identificacion, :descripcion, :marca, :modelo, :numero_serie,
            :rango_medicion, :resolucion, :incertidumbre, :ubicacion, :estado,
            :fecha_ultima_calibracion, :fecha_proxima_calibracion,
            :proveedor_calibracion, :certificado_calibracion, :observaciones,
            1
        )";
        
        $stmt = $db->prepare($insertSQL);
        
        foreach ($sampleData as $instrument) {
            $stmt->execute($instrument);
            echo "   ✓ Insertado: {$instrument['codigo_identificacion']} - {$instrument['descripcion']}\n";
        }
        
        echo "\n✅ Datos de prueba insertados exitosamente.\n\n";
    } else {
        echo "✅ La tabla ya contiene {$countInstruments['total']} instrumentos.\n\n";
    }
    
    // Resumen final
    echo "📊 RESUMEN FINAL\n";
    echo "================\n";
    echo "✅ Tabla 'instrumentos': Verificada/Creada\n";
    echo "✅ Tabla 'auditoria_instrumentos': Verificada/Creada\n";
    echo "✅ Índices de rendimiento: Configurados\n";
    echo "✅ Datos de prueba: Disponibles\n";
    echo "\n🎯 Sistema de instrumentos listo para usar!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>