<?php
/**
 * Script de Configuración de Tablas para AlertSystem
 * Sistema de Gestión de Instrumentos SBL
 */

require_once 'config_simple.php';

try {
    $db = new Database();
    echo "Iniciando configuración de tablas para AlertSystem...\n";
    
    // Tabla principal de alertas
    $sql_alertas = "
    CREATE TABLE IF NOT EXISTS alertas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tipo ENUM('sistema', 'calibracion', 'instrumento', 'usuario', 'backup', 'seguridad') NOT NULL,
        prioridad ENUM('baja', 'media', 'alta', 'critica') NOT NULL DEFAULT 'media',
        titulo VARCHAR(200) NOT NULL,
        mensaje TEXT NOT NULL,
        origen ENUM('manual', 'automatico', 'sistema') DEFAULT 'manual',
        referencia_id INT NULL COMMENT 'ID del objeto relacionado (instrumento, usuario, etc.)',
        estado ENUM('pendiente', 'en_proceso', 'resuelta', 'cerrada', 'ignorada') DEFAULT 'pendiente',
        fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
        fecha_leida DATETIME NULL,
        fecha_resuelta DATETIME NULL,
        fecha_modificacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        fecha_eliminacion DATETIME NULL,
        usuario_asignado INT NULL,
        usuario_creacion INT NULL,
        usuario_lectura INT NULL,
        usuario_resolucion INT NULL,
        usuario_modificacion INT NULL,
        usuario_eliminacion INT NULL,
        notas_resolucion TEXT NULL,
        datos_adicionales JSON NULL COMMENT 'Datos específicos según el tipo de alerta',
        activo TINYINT(1) DEFAULT 1,
        INDEX idx_tipo (tipo),
        INDEX idx_prioridad (prioridad),
        INDEX idx_estado (estado),
        INDEX idx_origen (origen),
        INDEX idx_fecha_creacion (fecha_creacion),
        INDEX idx_usuario_asignado (usuario_asignado),
        INDEX idx_activo (activo),
        INDEX idx_referencia (referencia_id),
        FOREIGN KEY (usuario_asignado) REFERENCES usuarios(id) ON DELETE SET NULL,
        FOREIGN KEY (usuario_creacion) REFERENCES usuarios(id) ON DELETE SET NULL,
        FOREIGN KEY (usuario_lectura) REFERENCES usuarios(id) ON DELETE SET NULL,
        FOREIGN KEY (usuario_resolucion) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->query($sql_alertas);
    echo "✓ Tabla 'alertas' creada exitosamente\n";
    
    // Tabla de configuración de reglas de alertas
    $sql_reglas_alertas = "
    CREATE TABLE IF NOT EXISTS reglas_alertas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        descripcion TEXT NULL,
        tipo_alerta ENUM('sistema', 'calibracion', 'instrumento', 'usuario', 'backup', 'seguridad') NOT NULL,
        condicion JSON NOT NULL COMMENT 'Condiciones para activar la alerta',
        prioridad ENUM('baja', 'media', 'alta', 'critica') NOT NULL DEFAULT 'media',
        activo TINYINT(1) DEFAULT 1,
        frecuencia_verificacion INT DEFAULT 60 COMMENT 'Minutos entre verificaciones',
        ultima_verificacion DATETIME NULL,
        usuario_asignado_default INT NULL,
        enviar_email TINYINT(1) DEFAULT 0,
        enviar_sms TINYINT(1) DEFAULT 0,
        fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
        fecha_modificacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        usuario_creacion INT NULL,
        usuario_modificacion INT NULL,
        INDEX idx_tipo_alerta (tipo_alerta),
        INDEX idx_activo (activo),
        INDEX idx_frecuencia (frecuencia_verificacion),
        FOREIGN KEY (usuario_asignado_default) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->query($sql_reglas_alertas);
    echo "✓ Tabla 'reglas_alertas' creada exitosamente\n";
    
    // Tabla de destinatarios de notificaciones
    $sql_destinatarios = "
    CREATE TABLE IF NOT EXISTS alert_destinatarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        tipo_alerta ENUM('sistema', 'calibracion', 'instrumento', 'usuario', 'backup', 'seguridad') NOT NULL,
        prioridad_minima ENUM('baja', 'media', 'alta', 'critica') DEFAULT 'media',
        recibir_email TINYINT(1) DEFAULT 1,
        recibir_sms TINYINT(1) DEFAULT 0,
        recibir_push TINYINT(1) DEFAULT 1,
        horario_inicio TIME DEFAULT '08:00:00',
        horario_fin TIME DEFAULT '18:00:00',
        dias_semana SET('lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo') 
            DEFAULT 'lunes,martes,miercoles,jueves,viernes',
        activo TINYINT(1) DEFAULT 1,
        fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
        fecha_modificacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        UNIQUE KEY unique_usuario_tipo (usuario_id, tipo_alerta),
        INDEX idx_tipo_alerta (tipo_alerta),
        INDEX idx_activo (activo)
    ) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->query($sql_destinatarios);
    echo "✓ Tabla 'alert_destinatarios' creada exitosamente\n";
    
    // Tabla de historial de notificaciones enviadas
    $sql_notificaciones = "
    CREATE TABLE IF NOT EXISTS alert_notificaciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        alerta_id INT NOT NULL,
        usuario_id INT NOT NULL,
        tipo_notificacion ENUM('email', 'sms', 'push', 'slack') NOT NULL,
        estado ENUM('pendiente', 'enviado', 'fallido', 'leido') DEFAULT 'pendiente',
        contenido TEXT NULL,
        destinatario VARCHAR(255) NOT NULL COMMENT 'Email, teléfono, etc.',
        fecha_envio DATETIME DEFAULT CURRENT_TIMESTAMP,
        fecha_lectura DATETIME NULL,
        error_mensaje TEXT NULL,
        intentos INT DEFAULT 0,
        FOREIGN KEY (alerta_id) REFERENCES alertas(id) ON DELETE CASCADE,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        INDEX idx_alerta_id (alerta_id),
        INDEX idx_usuario_id (usuario_id),
        INDEX idx_estado (estado),
        INDEX idx_fecha_envio (fecha_envio)
    ) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->query($sql_notificaciones);
    echo "✓ Tabla 'alert_notificaciones' creada exitosamente\n";
    
    // Tabla de plantillas de alertas
    $sql_plantillas = "
    CREATE TABLE IF NOT EXISTS alert_plantillas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        tipo_alerta ENUM('sistema', 'calibracion', 'instrumento', 'usuario', 'backup', 'seguridad') NOT NULL,
        canal ENUM('email', 'sms', 'push', 'slack') NOT NULL,
        asunto VARCHAR(200) NULL COMMENT 'Para email',
        plantilla_titulo VARCHAR(200) NOT NULL,
        plantilla_mensaje TEXT NOT NULL,
        variables_disponibles JSON NULL COMMENT 'Variables que se pueden usar en la plantilla',
        activo TINYINT(1) DEFAULT 1,
        fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
        fecha_modificacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        usuario_creacion INT NULL,
        usuario_modificacion INT NULL,
        INDEX idx_tipo_alerta (tipo_alerta),
        INDEX idx_canal (canal),
        INDEX idx_activo (activo)
    ) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->query($sql_plantillas);
    echo "✓ Tabla 'alert_plantillas' creada exitosamente\n";
    
    // Insertar reglas de alertas por defecto
    $reglas_default = [
        [
            'nombre' => 'Instrumentos próximos a vencer',
            'descripcion' => 'Alerta cuando un instrumento está próximo a su fecha de calibración',
            'tipo_alerta' => 'calibracion',
            'condicion' => json_encode([
                'tabla' => 'instrumentos',
                'campo' => 'fecha_proxima_calibracion',
                'operador' => '<=',
                'valor' => 'DATE_ADD(CURDATE(), INTERVAL 30 DAY)',
                'adicional' => 'AND activo = 1'
            ]),
            'prioridad' => 'media',
            'frecuencia_verificacion' => 1440 // 24 horas
        ],
        [
            'nombre' => 'Instrumentos vencidos',
            'descripcion' => 'Alerta cuando un instrumento ha pasado su fecha de calibración',
            'tipo_alerta' => 'calibracion',
            'condicion' => json_encode([
                'tabla' => 'instrumentos',
                'campo' => 'fecha_proxima_calibracion',
                'operador' => '<',
                'valor' => 'CURDATE()',
                'adicional' => 'AND activo = 1 AND estado != "fuera_servicio"'
            ]),
            'prioridad' => 'alta',
            'frecuencia_verificacion' => 360 // 6 horas
        ],
        [
            'nombre' => 'Backups fallidos',
            'descripcion' => 'Alerta cuando fallan los backups del sistema',
            'tipo_alerta' => 'backup',
            'condicion' => json_encode([
                'tabla' => 'backups',
                'campo' => 'estado',
                'operador' => '=',
                'valor' => "'fallido'",
                'adicional' => 'AND fecha_backup >= DATE_SUB(NOW(), INTERVAL 24 HOUR)'
            ]),
            'prioridad' => 'alta',
            'frecuencia_verificacion' => 60 // 1 hora
        ],
        [
            'nombre' => 'Espacio en disco bajo',
            'descripcion' => 'Alerta cuando el espacio en disco está por agotarse',
            'tipo_alerta' => 'sistema',
            'condicion' => json_encode([
                'tipo' => 'custom',
                'funcion' => 'check_disk_space',
                'umbral' => 90
            ]),
            'prioridad' => 'alta',
            'frecuencia_verificacion' => 30 // 30 minutos
        ]
    ];
    
    foreach ($reglas_default as $regla) {
        $sql_check_regla = "SELECT COUNT(*) as count FROM reglas_alertas WHERE nombre = :nombre";
        $stmt = $db->prepare($sql_check_regla);
        $stmt->execute([':nombre' => $regla['nombre']]);
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            $sql_insert_regla = "
            INSERT INTO reglas_alertas (
                nombre, descripcion, tipo_alerta, condicion, prioridad, 
                frecuencia_verificacion, usuario_creacion
            ) VALUES (
                :nombre, :descripcion, :tipo_alerta, :condicion, :prioridad, 
                :frecuencia_verificacion, 1
            )";
            
            $stmt = $db->prepare($sql_insert_regla);
            $stmt->execute([
                ':nombre' => $regla['nombre'],
                ':descripcion' => $regla['descripcion'],
                ':tipo_alerta' => $regla['tipo_alerta'],
                ':condicion' => $regla['condicion'],
                ':prioridad' => $regla['prioridad'],
                ':frecuencia_verificacion' => $regla['frecuencia_verificacion']
            ]);
        }
    }
    echo "✓ Reglas de alertas por defecto insertadas\n";
    
    // Insertar plantillas por defecto
    $plantillas_default = [
        [
            'nombre' => 'Calibración próxima - Email',
            'tipo_alerta' => 'calibracion',
            'canal' => 'email',
            'asunto' => 'Calibración próxima a vencer - {{codigo_instrumento}}',
            'plantilla_titulo' => 'Calibración próxima a vencer',
            'plantilla_mensaje' => 'El instrumento {{codigo_instrumento}} - {{descripcion}} requiere calibración el {{fecha_vencimiento}}. Días restantes: {{dias_restantes}}.',
            'variables_disponibles' => json_encode(['codigo_instrumento', 'descripcion', 'fecha_vencimiento', 'dias_restantes'])
        ],
        [
            'nombre' => 'Backup fallido - Email',
            'tipo_alerta' => 'backup',
            'canal' => 'email',
            'asunto' => 'Error en backup del sistema',
            'plantilla_titulo' => 'Backup fallido',
            'plantilla_mensaje' => 'Se ha detectado un error en el backup del sistema. Fecha: {{fecha_backup}}. Error: {{mensaje_error}}.',
            'variables_disponibles' => json_encode(['fecha_backup', 'mensaje_error', 'tipo_backup'])
        ],
        [
            'nombre' => 'Espacio disco bajo - Email',
            'tipo_alerta' => 'sistema',
            'canal' => 'email',
            'asunto' => 'Espacio en disco bajo',
            'plantilla_titulo' => 'Espacio en disco bajo',
            'plantilla_mensaje' => 'El espacio en disco está al {{porcentaje_usado}}%. Espacio libre: {{espacio_libre}}.',
            'variables_disponibles' => json_encode(['porcentaje_usado', 'espacio_libre', 'espacio_total'])
        ]
    ];
    
    foreach ($plantillas_default as $plantilla) {
        $sql_check_plantilla = "SELECT COUNT(*) as count FROM alert_plantillas WHERE nombre = :nombre";
        $stmt = $db->prepare($sql_check_plantilla);
        $stmt->execute([':nombre' => $plantilla['nombre']]);
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            $sql_insert_plantilla = "
            INSERT INTO alert_plantillas (
                nombre, tipo_alerta, canal, asunto, plantilla_titulo, 
                plantilla_mensaje, variables_disponibles, usuario_creacion
            ) VALUES (
                :nombre, :tipo_alerta, :canal, :asunto, :plantilla_titulo, 
                :plantilla_mensaje, :variables_disponibles, 1
            )";
            
            $stmt = $db->prepare($sql_insert_plantilla);
            $stmt->execute([
                ':nombre' => $plantilla['nombre'],
                ':tipo_alerta' => $plantilla['tipo_alerta'],
                ':canal' => $plantilla['canal'],
                ':asunto' => $plantilla['asunto'],
                ':plantilla_titulo' => $plantilla['plantilla_titulo'],
                ':plantilla_mensaje' => $plantilla['plantilla_mensaje'],
                ':variables_disponibles' => $plantilla['variables_disponibles']
            ]);
        }
    }
    echo "✓ Plantillas de alertas por defecto insertadas\n";
    
    // Crear tabla de métricas de alertas para reportes
    $sql_metricas = "
    CREATE TABLE IF NOT EXISTS alert_metricas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fecha DATE NOT NULL,
        tipo_alerta ENUM('sistema', 'calibracion', 'instrumento', 'usuario', 'backup', 'seguridad') NOT NULL,
        prioridad ENUM('baja', 'media', 'alta', 'critica') NOT NULL,
        total_generadas INT DEFAULT 0,
        total_resueltas INT DEFAULT 0,
        tiempo_promedio_resolucion INT NULL COMMENT 'Minutos',
        fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_fecha_tipo_prioridad (fecha, tipo_alerta, prioridad),
        INDEX idx_fecha (fecha),
        INDEX idx_tipo_alerta (tipo_alerta)
    ) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->query($sql_metricas);
    echo "✓ Tabla 'alert_metricas' creada exitosamente\n";
    
    echo "\n¡Configuración de AlertSystem completada exitosamente!\n\n";
    echo "Tablas creadas:\n";
    echo "- alertas: Almacena todas las alertas del sistema\n";
    echo "- reglas_alertas: Configuración de reglas automáticas\n";
    echo "- alert_destinatarios: Configuración de notificaciones por usuario\n";
    echo "- alert_notificaciones: Historial de notificaciones enviadas\n";
    echo "- alert_plantillas: Plantillas para diferentes tipos de alertas\n";
    echo "- alert_metricas: Métricas y estadísticas de alertas\n\n";
    echo "El sistema está listo para generar y gestionar alertas automáticas.\n";
    
} catch (Exception $e) {
    echo "Error durante la configuración: " . $e->getMessage() . "\n";
    exit(1);
}
?>