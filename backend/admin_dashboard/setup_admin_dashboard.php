<?php
/**
 * Script de Configuración de Tablas para AdminDashboard
 * Sistema de Gestión de Instrumentos SBL
 */

require_once 'config_simple.php';

try {
    $db = new Database();
    echo "Iniciando configuración de tablas para AdminDashboard...\n";
    
    // Tabla de usuarios
    $sql_usuarios = "
    CREATE TABLE IF NOT EXISTS usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        nombre_completo VARCHAR(150) NOT NULL,
        role ENUM('admin', 'supervisor', 'tecnico', 'usuario') DEFAULT 'usuario',
        activo TINYINT(1) DEFAULT 1,
        ultimo_login DATETIME NULL,
        fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
        fecha_modificacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        usuario_registro VARCHAR(50) NULL,
        usuario_modificacion VARCHAR(50) NULL,
        INDEX idx_username (username),
        INDEX idx_email (email),
        INDEX idx_activo (activo),
        INDEX idx_role (role)
    ) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->query($sql_usuarios);
    echo "✓ Tabla 'usuarios' creada exitosamente\n";
    
    // Tabla de logs del sistema
    $sql_logs = "
    CREATE TABLE IF NOT EXISTS system_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nivel ENUM('debug', 'info', 'warning', 'error', 'critical') DEFAULT 'info',
        mensaje TEXT NOT NULL,
        contexto JSON NULL,
        usuario VARCHAR(50) NULL,
        ip_address VARCHAR(45) NULL,
        user_agent TEXT NULL,
        modulo VARCHAR(50) NULL,
        accion VARCHAR(100) NULL,
        fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_nivel (nivel),
        INDEX idx_usuario (usuario),
        INDEX idx_modulo (modulo),
        INDEX idx_fecha (fecha_registro)
    ) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->query($sql_logs);
    echo "✓ Tabla 'system_logs' creada exitosamente\n";
    
    // Tabla de configuraciones del sistema
    $sql_configuraciones = "
    CREATE TABLE IF NOT EXISTS configuraciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        clave VARCHAR(100) UNIQUE NOT NULL,
        valor TEXT NULL,
        descripcion TEXT NULL,
        tipo ENUM('string', 'integer', 'boolean', 'json', 'text') DEFAULT 'string',
        categoria VARCHAR(50) DEFAULT 'general',
        es_editable TINYINT(1) DEFAULT 1,
        fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
        fecha_modificacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        usuario_modificacion VARCHAR(50) NULL,
        INDEX idx_clave (clave),
        INDEX idx_categoria (categoria)
    ) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->query($sql_configuraciones);
    echo "✓ Tabla 'configuraciones' creada exitosamente\n";
    
    // Tabla de sesiones de usuario
    $sql_sesiones = "
    CREATE TABLE IF NOT EXISTS user_sessions (
        id VARCHAR(128) PRIMARY KEY,
        user_id INT NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        user_agent TEXT NULL,
        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_active TINYINT(1) DEFAULT 1,
        FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_last_activity (last_activity),
        INDEX idx_is_active (is_active)
    ) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->query($sql_sesiones);
    echo "✓ Tabla 'user_sessions' creada exitosamente\n";
    
    // Tabla de permisos
    $sql_permisos = "
    CREATE TABLE IF NOT EXISTS permisos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) UNIQUE NOT NULL,
        descripcion TEXT NULL,
        modulo VARCHAR(50) NOT NULL,
        activo TINYINT(1) DEFAULT 1,
        fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_nombre (nombre),
        INDEX idx_modulo (modulo)
    ) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->query($sql_permisos);
    echo "✓ Tabla 'permisos' creada exitosamente\n";
    
    // Tabla de roles y permisos
    $sql_role_permisos = "
    CREATE TABLE IF NOT EXISTS role_permisos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        role ENUM('admin', 'supervisor', 'tecnico', 'usuario') NOT NULL,
        permiso_id INT NOT NULL,
        fecha_asignacion DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE CASCADE,
        UNIQUE KEY unique_role_permiso (role, permiso_id),
        INDEX idx_role (role)
    ) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->query($sql_role_permisos);
    echo "✓ Tabla 'role_permisos' creada exitosamente\n";
    
    // Insertar usuario administrador por defecto si no existe
    $sql_check_admin = "SELECT COUNT(*) as count FROM usuarios WHERE role = 'admin'";
    $stmt = $db->prepare($sql_check_admin);
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        $sql_insert_admin = "
        INSERT INTO usuarios (
            username, 
            email, 
            password, 
            nombre_completo, 
            role, 
            activo, 
            usuario_registro
        ) VALUES (
            'admin', 
            'admin@sbl.com', 
            :password, 
            'Administrador del Sistema', 
            'admin', 
            1, 
            'sistema'
        )";
        
        $stmt = $db->prepare($sql_insert_admin);
        $stmt->execute([':password' => password_hash('admin123', PASSWORD_DEFAULT)]);
        echo "✓ Usuario administrador creado (usuario: admin, contraseña: admin123)\n";
    } else {
        echo "✓ Usuario administrador ya existe\n";
    }
    
    // Insertar configuraciones por defecto
    $configuraciones_default = [
        ['sistema.nombre', 'SBL Sistema Interno', 'Nombre del sistema', 'string', 'general'],
        ['sistema.version', '1.0', 'Versión del sistema', 'string', 'general'],
        ['sistema.mantenimiento', '0', 'Modo de mantenimiento', 'boolean', 'general'],
        ['dashboard.refresh_interval', '30', 'Intervalo de actualización del dashboard (segundos)', 'integer', 'dashboard'],
        ['logs.retention_days', '90', 'Días de retención de logs', 'integer', 'logs'],
        ['backup.auto_backup', '1', 'Backup automático habilitado', 'boolean', 'backup'],
        ['backup.retention_days', '30', 'Días de retención de backups', 'integer', 'backup']
    ];
    
    foreach ($configuraciones_default as $config) {
        $sql_check_config = "SELECT COUNT(*) as count FROM configuraciones WHERE clave = :clave";
        $stmt = $db->prepare($sql_check_config);
        $stmt->execute([':clave' => $config[0]]);
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            $sql_insert_config = "
            INSERT INTO configuraciones (clave, valor, descripcion, tipo, categoria, usuario_modificacion) 
            VALUES (:clave, :valor, :descripcion, :tipo, :categoria, 'sistema')";
            
            $stmt = $db->prepare($sql_insert_config);
            $stmt->execute([
                ':clave' => $config[0],
                ':valor' => $config[1],
                ':descripcion' => $config[2],
                ':tipo' => $config[3],
                ':categoria' => $config[4]
            ]);
        }
    }
    echo "✓ Configuraciones por defecto insertadas\n";
    
    // Insertar permisos por defecto
    $permisos_default = [
        ['admin.dashboard.view', 'Ver dashboard administrativo', 'AdminDashboard'],
        ['admin.users.view', 'Ver usuarios', 'AdminDashboard'],
        ['admin.users.create', 'Crear usuarios', 'AdminDashboard'],
        ['admin.users.edit', 'Editar usuarios', 'AdminDashboard'],
        ['admin.users.delete', 'Eliminar usuarios', 'AdminDashboard'],
        ['admin.logs.view', 'Ver logs del sistema', 'AdminDashboard'],
        ['admin.config.view', 'Ver configuraciones', 'AdminDashboard'],
        ['admin.config.edit', 'Editar configuraciones', 'AdminDashboard'],
        ['instrumentos.view', 'Ver instrumentos', 'Instrumentos'],
        ['instrumentos.create', 'Crear instrumentos', 'Instrumentos'],
        ['instrumentos.edit', 'Editar instrumentos', 'Instrumentos'],
        ['instrumentos.delete', 'Eliminar instrumentos', 'Instrumentos'],
        ['calibraciones.view', 'Ver calibraciones', 'Calibraciones'],
        ['calibraciones.create', 'Crear calibraciones', 'Calibraciones'],
        ['reportes.view', 'Ver reportes', 'Reportes'],
        ['reportes.export', 'Exportar reportes', 'Reportes']
    ];
    
    foreach ($permisos_default as $permiso) {
        $sql_check_permiso = "SELECT COUNT(*) as count FROM permisos WHERE nombre = :nombre";
        $stmt = $db->prepare($sql_check_permiso);
        $stmt->execute([':nombre' => $permiso[0]]);
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            $sql_insert_permiso = "
            INSERT INTO permisos (nombre, descripcion, modulo) 
            VALUES (:nombre, :descripcion, :modulo)";
            
            $stmt = $db->prepare($sql_insert_permiso);
            $stmt->execute([
                ':nombre' => $permiso[0],
                ':descripcion' => $permiso[1],
                ':modulo' => $permiso[2]
            ]);
        }
    }
    echo "✓ Permisos por defecto insertados\n";
    
    // Asignar todos los permisos al role admin
    $sql_admin_permisos = "
    INSERT IGNORE INTO role_permisos (role, permiso_id)
    SELECT 'admin', id FROM permisos WHERE activo = 1";
    
    $db->query($sql_admin_permisos);
    echo "✓ Permisos asignados al rol administrador\n";
    
    echo "\n¡Configuración de AdminDashboard completada exitosamente!\n\n";
    echo "Credenciales de acceso:\n";
    echo "Usuario: admin\n";
    echo "Contraseña: admin123\n\n";
    echo "IMPORTANTE: Cambiar la contraseña del administrador en producción.\n";
    
} catch (Exception $e) {
    echo "Error durante la configuración: " . $e->getMessage() . "\n";
    exit(1);
}
?>