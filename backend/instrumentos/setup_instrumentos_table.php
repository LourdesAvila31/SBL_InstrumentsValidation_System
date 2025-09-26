<?php
/**
 * Script de configuración de Base de Datos - Módulo Instrumentos
 * 
 * Este script crea las tablas necesarias para el módulo de instrumentos
 * según estándares ISO 17025 y genera datos de muestra para pruebas
 */

// Configuración de base de datos
$host = 'localhost';
$dbname = 'sbl_sistema_interno';
$username = 'root';
$password = '';

try {
    // Primero conectar sin especificar base de datos
    $pdo = new PDO("mysql:host=$host;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Crear la base de datos si no existe
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname");
    echo "<p>✅ Base de datos '$dbname' verificada/creada</p>";
    
    // Ahora conectar a la base de datos específica
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>🔧 Configuración del Módulo de Instrumentos</h2>";
    echo "<p>Iniciando configuración de base de datos...</p>";
    
    // 1. Crear tabla de instrumentos
    echo "<h3>📋 Creando tabla de instrumentos...</h3>";
    $sql_instrumentos = "
    CREATE TABLE IF NOT EXISTS instrumentos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        codigo_identificacion VARCHAR(50) NOT NULL UNIQUE,
        descripcion TEXT NOT NULL,
        marca VARCHAR(100),
        modelo VARCHAR(100),
        numero_serie VARCHAR(100),
        rango_medicion VARCHAR(200),
        resolucion VARCHAR(100),
        incertidumbre VARCHAR(100),
        ubicacion VARCHAR(200),
        estado ENUM('ACTIVO', 'INACTIVO', 'MANTENIMIENTO', 'CALIBRACION', 'BAJA') DEFAULT 'ACTIVO',
        fecha_ultima_calibracion DATE,
        fecha_proxima_calibracion DATE,
        proveedor_calibracion VARCHAR(200),
        certificado_calibracion VARCHAR(200),
        observaciones TEXT,
        fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
        usuario_registro INT,
        fecha_modificacion DATETIME,
        usuario_modificacion INT,
        activo BOOLEAN DEFAULT TRUE,
        INDEX idx_codigo (codigo_identificacion),
        INDEX idx_estado (estado),
        INDEX idx_fecha_proxima (fecha_proxima_calibracion),
        INDEX idx_activo (activo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql_instrumentos);
    echo "<p>✅ Tabla 'instrumentos' creada correctamente</p>";
    
    // 2. Crear tabla de auditoría
    echo "<h3>📋 Creando tabla de auditoría...</h3>";
    $sql_auditoria = "
    CREATE TABLE IF NOT EXISTS auditoria_instrumentos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        instrumento_id INT,
        usuario_id INT,
        accion ENUM('CREATE', 'UPDATE', 'DELETE', 'VIEW') NOT NULL,
        descripcion TEXT,
        datos_anteriores JSON,
        datos_nuevos JSON,
        fecha_auditoria DATETIME DEFAULT CURRENT_TIMESTAMP,
        ip_address VARCHAR(45),
        INDEX idx_instrumento (instrumento_id),
        INDEX idx_usuario (usuario_id),
        INDEX idx_fecha (fecha_auditoria),
        FOREIGN KEY (instrumento_id) REFERENCES instrumentos(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql_auditoria);
    echo "<p>✅ Tabla 'auditoria_instrumentos' creada correctamente</p>";
    
    // 3. Insertar datos de muestra
    echo "<h3>📋 Insertando datos de muestra...</h3>";
    
    // Verificar si ya existen datos
    $stmt = $pdo->query("SELECT COUNT(*) FROM instrumentos WHERE activo = 1");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        $instrumentos_muestra = [
            [
                'codigo' => 'BAL-001',
                'descripcion' => 'Balanza Analítica Digital',
                'marca' => 'Mettler Toledo',
                'modelo' => 'MS204S',
                'serie' => 'B123456789',
                'rango' => '0-220g',
                'resolucion' => '0.1mg',
                'incertidumbre' => '±0.2mg',
                'ubicacion' => 'Laboratorio de Química - Mesa 1',
                'estado' => 'ACTIVO',
                'fecha_ultima' => '2025-06-15',
                'fecha_proxima' => '2025-12-15',
                'proveedor' => 'Metrología Industrial SA',
                'certificado' => 'CERT-BAL-001-2025',
                'observaciones' => 'Calibración según procedimiento PI-CAL-001'
            ],
            [
                'codigo' => 'PIP-002',
                'descripcion' => 'Pipeta Automática Variable 100-1000μL',
                'marca' => 'Eppendorf',
                'modelo' => 'Research Plus',
                'serie' => 'EP987654321',
                'rango' => '100-1000μL',
                'resolucion' => '1μL',
                'incertidumbre' => '±0.8%',
                'ubicacion' => 'Laboratorio de Microbiología - Estación 2',
                'estado' => 'ACTIVO',
                'fecha_ultima' => '2025-03-20',
                'fecha_proxima' => '2025-09-20',
                'proveedor' => 'Calibraciones Exactas SRL',
                'certificado' => 'CERT-PIP-002-2025',
                'observaciones' => 'Requiere calibración cada 6 meses'
            ],
            [
                'codigo' => 'TER-003',
                'descripcion' => 'Termómetro Digital de Precisión',
                'marca' => 'Fluke',
                'modelo' => '1524',
                'serie' => 'FL456789123',
                'rango' => '-200°C a +660°C',
                'resolucion' => '0.01°C',
                'incertidumbre' => '±0.05°C',
                'ubicacion' => 'Laboratorio de Física - Cabina 3',
                'estado' => 'ACTIVO',
                'fecha_ultima' => '2025-01-10',
                'fecha_proxima' => '2026-01-10',
                'proveedor' => 'Termocalibra México',
                'certificado' => 'CERT-TER-003-2025',
                'observaciones' => 'Calibración anual - Instrumento crítico'
            ],
            [
                'codigo' => 'PH-004',
                'descripcion' => 'pHmetro de Mesa con Electrodo',
                'marca' => 'Hanna Instruments',
                'modelo' => 'HI-2020',
                'serie' => 'HI123987456',
                'rango' => 'pH 0.00-14.00',
                'resolucion' => '0.01 pH',
                'incertidumbre' => '±0.02 pH',
                'ubicacion' => 'Laboratorio de Análisis Químico',
                'estado' => 'MANTENIMIENTO',
                'fecha_ultima' => '2024-11-15',
                'fecha_proxima' => '2025-11-15',
                'proveedor' => 'Instrumentos de Medición SA',
                'certificado' => 'CERT-PH-004-2024',
                'observaciones' => 'En mantenimiento preventivo - electrodo dañado'
            ],
            [
                'codigo' => 'MIC-005',
                'descripcion' => 'Micrómetro Exterior Digital',
                'marca' => 'Mitutoyo',
                'modelo' => '293-340-30',
                'serie' => 'MIT789456123',
                'rango' => '0-25mm',
                'resolucion' => '0.001mm',
                'incertidumbre' => '±0.002mm',
                'ubicacion' => 'Taller de Metrología Dimensional',
                'estado' => 'ACTIVO',
                'fecha_ultima' => '2025-08-01',
                'fecha_proxima' => '2026-08-01',
                'proveedor' => 'Centro Nacional de Metrología',
                'certificado' => 'CERT-MIC-005-2025',
                'observaciones' => 'Patrón de trabajo - máxima precisión'
            ]
        ];
        
        $sql_insert = "INSERT INTO instrumentos (
            codigo_identificacion, descripcion, marca, modelo, numero_serie,
            rango_medicion, resolucion, incertidumbre, ubicacion, estado,
            fecha_ultima_calibracion, fecha_proxima_calibracion,
            proveedor_calibracion, certificado_calibracion, observaciones,
            usuario_registro
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
        
        $stmt = $pdo->prepare($sql_insert);
        
        foreach ($instrumentos_muestra as $instrumento) {
            $stmt->execute([
                $instrumento['codigo'],
                $instrumento['descripcion'],
                $instrumento['marca'],
                $instrumento['modelo'],
                $instrumento['serie'],
                $instrumento['rango'],
                $instrumento['resolucion'],
                $instrumento['incertidumbre'],
                $instrumento['ubicacion'],
                $instrumento['estado'],
                $instrumento['fecha_ultima'],
                $instrumento['fecha_proxima'],
                $instrumento['proveedor'],
                $instrumento['certificado'],
                $instrumento['observaciones']
            ]);
        }
        
        echo "<p>✅ Insertados " . count($instrumentos_muestra) . " instrumentos de muestra</p>";
    } else {
        echo "<p>ℹ️ Ya existen $count instrumentos en la base de datos</p>";
    }
    
    // 4. Verificar estructura
    echo "<h3>🔍 Verificando estructura de tablas...</h3>";
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'instrumentos'");
    if ($stmt->rowCount() > 0) {
        echo "<p>✅ Tabla 'instrumentos' verificada</p>";
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM instrumentos WHERE activo = 1");
        $total = $stmt->fetchColumn();
        echo "<p>📊 Total de instrumentos activos: <strong>$total</strong></p>";
    }
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'auditoria_instrumentos'");
    if ($stmt->rowCount() > 0) {
        echo "<p>✅ Tabla 'auditoria_instrumentos' verificada</p>";
    }
    
    // 5. Mostrar resumen
    echo "<h3>📋 Resumen de configuración</h3>";
    echo "<div style='background:#e8f5e8; padding:15px; border-radius:5px; margin:10px 0;'>";
    echo "<p><strong>✅ Configuración completada exitosamente</strong></p>";
    echo "<ul>";
    echo "<li>✅ Tablas creadas: instrumentos, auditoria_instrumentos</li>";
    echo "<li>✅ Índices optimizados para consultas</li>";
    echo "<li>✅ Datos de muestra insertados</li>";
    echo "<li>✅ Estructura ISO 17025 implementada</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<h3>🚀 Próximos pasos</h3>";
    echo "<ol>";
    echo "<li>Acceder a la interfaz: <a href='../public/apps/internal/instrumentos/list_gages.html' target='_blank'>Gestión de Instrumentos</a></li>";
    echo "<li>Probar API REST: <a href='gages/list_gages.php' target='_blank'>Lista de Instrumentos (JSON)</a></li>";
    echo "<li>Verificar funciones CRUD desde la interfaz web</li>";
    echo "</ol>";
    
} catch (PDOException $e) {
    echo "<h3 style='color:red;'>❌ Error de Base de Datos</h3>";
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
    echo "<p>Verifique que:</p>";
    echo "<ul>";
    echo "<li>MySQL esté ejecutándose</li>";
    echo "<li>La base de datos 'sbl_sistema_interno' exista</li>";
    echo "<li>Las credenciales sean correctas</li>";
    echo "</ul>";
} catch (Exception $e) {
    echo "<h3 style='color:red;'>❌ Error General</h3>";
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}
?>