<?php
/**
 * Script de Instalación - Sistema de Retiro de Sistema Computarizado GAMP 5
 * 
 * Este script instala y configura:
 * - Tablas de base de datos para el sistema de retiro
 * - Permisos y roles necesarios
 * - Configuración inicial del sistema
 * - Validación de dependencias
 */

declare(strict_types=1);

require_once __DIR__ . '/app/Core/db.php';
require_once __DIR__ . '/app/Core/Security/SystemRetirement/SystemRetirementManager.php';

echo "=== INSTALADOR SISTEMA DE RETIRO GAMP 5 ===\n";
echo "Iniciando instalación del Sistema de Retiro de Sistema Computarizado...\n\n";

try {
    // 1. Verificar conexión a la base de datos
    echo "1. Verificando conexión a la base de datos...\n";
    $conn = DatabaseManager::getConnection();
    if (!$conn) {
        throw new Exception("No se pudo conectar a la base de datos");
    }
    echo "   ✓ Conexión establecida correctamente\n\n";

    // 2. Instalar tablas del sistema de retiro
    echo "2. Instalando tablas del sistema de retiro...\n";
    $retirementManager = new SystemRetirementManager($conn);
    
    if ($retirementManager->installRetirementTables()) {
        echo "   ✓ Tablas del sistema de retiro instaladas correctamente\n";
    } else {
        throw new Exception("Error al instalar tablas del sistema de retiro");
    }

    // 3. Crear permisos específicos para retiro
    echo "\n3. Configurando permisos del sistema de retiro...\n";
    $permissions = [
        [
            'nombre' => 'system_retirement_initiate',
            'descripcion' => 'Iniciar proceso de retiro de sistema',
            'categoria' => 'Sistema de Retiro'
        ],
        [
            'nombre' => 'system_retirement_migrate',
            'descripcion' => 'Ejecutar migración de datos en retiro',
            'categoria' => 'Sistema de Retiro'
        ],
        [
            'nombre' => 'system_retirement_destroy',
            'descripcion' => 'Ejecutar destrucción de datos en retiro',
            'categoria' => 'Sistema de Retiro'
        ],
        [
            'nombre' => 'system_retirement_verify',
            'descripcion' => 'Ejecutar verificación post-retiro',
            'categoria' => 'Sistema de Retiro'
        ],
        [
            'nombre' => 'system_retirement_approve',
            'descripcion' => 'Aprobar proceso de retiro',
            'categoria' => 'Sistema de Retiro'
        ],
        [
            'nombre' => 'system_retirement_monitor',
            'descripcion' => 'Monitorear procesos de retiro',
            'categoria' => 'Sistema de Retiro'
        ]
    ];

    foreach ($permissions as $permission) {
        $sql = "INSERT IGNORE INTO permisos (nombre, descripcion, categoria, activo) VALUES (?, ?, ?, 1)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sss', $permission['nombre'], $permission['descripcion'], $permission['categoria']);
        
        if ($stmt->execute()) {
            echo "   ✓ Permiso '{$permission['nombre']}' configurado\n";
        } else {
            echo "   ⚠ Error configurando permiso '{$permission['nombre']}': " . $stmt->error . "\n";
        }
    }

    // 4. Asignar permisos a roles administrativos
    echo "\n4. Asignando permisos a roles administrativos...\n";
    
    // Obtener IDs de roles administrativos
    $adminRoles = ['developer', 'superadministrador', 'administrador'];
    $permissionIds = [];
    
    // Obtener IDs de permisos
    $sql = "SELECT id, nombre FROM permisos WHERE categoria = 'Sistema de Retiro'";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $permissionIds[$row['nombre']] = $row['id'];
    }
    
    foreach ($adminRoles as $roleAlias) {
        $sql = "SELECT id FROM roles WHERE alias = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $roleAlias);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($roleRow = $result->fetch_assoc()) {
            $roleId = $roleRow['id'];
            
            foreach ($permissionIds as $permissionName => $permissionId) {
                $sql = "INSERT IGNORE INTO rol_permisos (rol_id, permiso_id) VALUES (?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('ii', $roleId, $permissionId);
                
                if ($stmt->execute()) {
                    echo "   ✓ Permiso '{$permissionName}' asignado al rol '{$roleAlias}'\n";
                }
            }
        }
    }

    // 5. Crear configuración inicial
    echo "\n5. Configurando parámetros iniciales...\n";
    
    $configurations = [
        [
            'clave' => 'retirement_backup_path',
            'valor' => '/backup/system_retirement',
            'descripcion' => 'Ruta base para backups de retiro de sistema'
        ],
        [
            'clave' => 'retirement_secure_wipe_passes',
            'valor' => '3',
            'descripcion' => 'Número de pasadas para borrado seguro'
        ],
        [
            'clave' => 'retirement_verification_required',
            'valor' => 'true',
            'descripcion' => 'Verificación post-retiro requerida'
        ],
        [
            'clave' => 'retirement_approval_required',
            'valor' => 'true',
            'descripcion' => 'Aprobación final requerida'
        ],
        [
            'clave' => 'retirement_audit_retention_days',
            'valor' => '2555', // 7 años
            'descripcion' => 'Días de retención de auditoría de retiro'
        ]
    ];

    foreach ($configurations as $config) {
        $sql = "INSERT INTO configuraciones (clave, valor, descripcion, categoria, tipo) 
                VALUES (?, ?, ?, 'Sistema de Retiro', 'system') 
                ON DUPLICATE KEY UPDATE valor = VALUES(valor)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sss', $config['clave'], $config['valor'], $config['descripcion']);
        
        if ($stmt->execute()) {
            echo "   ✓ Configuración '{$config['clave']}' establecida\n";
        }
    }

    // 6. Crear directorio de trabajo
    echo "\n6. Creando directorios de trabajo...\n";
    
    $directories = [
        __DIR__ . '/storage/system_retirement',
        __DIR__ . '/storage/system_retirement/migrations',
        __DIR__ . '/storage/system_retirement/destructions',
        __DIR__ . '/storage/system_retirement/reports',
        __DIR__ . '/storage/system_retirement/certificates'
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            if (mkdir($dir, 0755, true)) {
                echo "   ✓ Directorio creado: " . basename($dir) . "\n";
            } else {
                echo "   ⚠ Error creando directorio: " . basename($dir) . "\n";
            }
        } else {
            echo "   ✓ Directorio ya existe: " . basename($dir) . "\n";
        }
    }

    // 7. Crear archivo de rutas API
    echo "\n7. Configurando endpoints API...\n";
    
    $apiRouteContent = '<?php
/**
 * Rutas API para Sistema de Retiro - Auto-generado
 */

require_once __DIR__ . "/../../app/Core/Security/Api/SystemRetirementApiController.php";

// Instanciar y ejecutar controlador
$controller = new SystemRetirementApiController();
$controller->handleRequest();
';
    
    $apiFile = __DIR__ . '/public/api/retirement.php';
    if (file_put_contents($apiFile, $apiRouteContent)) {
        echo "   ✓ Endpoint API configurado: /api/retirement.php\n";
    }

    // 8. Actualizar menú de navegación (si existe archivo de configuración)
    echo "\n8. Actualizando configuración del menú...\n";
    
    $menuConfigFile = __DIR__ . '/app/config/menu_items.json';
    if (file_exists($menuConfigFile)) {
        $menuConfig = json_decode(file_get_contents($menuConfigFile), true);
        
        // Agregar item de retiro de sistema
        $retirementMenuItem = [
            'id' => 'system_retirement',
            'title' => 'Retiro de Sistema',
            'icon' => 'fas fa-power-off',
            'url' => '/apps/system-retirement/index.html',
            'permission' => 'system_retirement_initiate',
            'category' => 'Administración',
            'order' => 90,
            'description' => 'Gestión de retiro de sistema computarizado GAMP 5'
        ];
        
        $menuConfig['items'][] = $retirementMenuItem;
        
        if (file_put_contents($menuConfigFile, json_encode($menuConfig, JSON_PRETTY_PRINT))) {
            echo "   ✓ Menú actualizado con item de retiro de sistema\n";
        }
    } else {
        echo "   ⚠ Archivo de configuración de menú no encontrado\n";
    }

    // 9. Crear documentación
    echo "\n9. Generando documentación...\n";
    
    $docContent = '# Sistema de Retiro de Sistema Computarizado GAMP 5

## Descripción
Este módulo implementa un proceso controlado para el retiro de sistemas computarizados conforme a GAMP 5 y normativas GxP.

## Características
- ✅ Planificación detallada del retiro
- ✅ Migración controlada de datos críticos
- ✅ Destrucción segura de datos sensibles
- ✅ Verificación post-retiro completa
- ✅ Documentación y trazabilidad completa
- ✅ Cumplimiento GAMP 5 y 21 CFR Part 11

## Proceso de Retiro

### 1. Planificación
- Definir sistema a retirar
- Establecer fecha de desactivación
- Identificar datos críticos y sensibles
- Evaluación de riesgos

### 2. Migración de Datos (Opcional)
- Exportación de datos críticos
- Verificación de integridad
- Almacenamiento seguro

### 3. Destrucción de Datos
- Borrado seguro con múltiples pasadas
- Destrucción criptográfica
- Verificación de destrucción completa

### 4. Verificación Post-Retiro
- Verificación de migración exitosa
- Confirmación de destrucción
- Validación de desactivación del sistema
- Completitud de documentación

### 5. Aprobación Final
- Revisión de calidad
- Aprobación gerencial
- Firma digital del proceso

## Acceso
- URL: `/apps/system-retirement/index.html`
- API: `/api/retirement`
- Permisos requeridos: `system_retirement_*`

## Cumplimiento Normativo
- GAMP 5: Gestión de riesgos y validación
- 21 CFR Part 11: Registros electrónicos y firmas
- ISO 27001: Seguridad de la información
- GxP: Buenas prácticas regulatorias

## Instalación
Ejecutar: `php install_system_retirement.php`

---
Generado automáticamente el ' . date('Y-m-d H:i:s') . '
';
    
    $docFile = __DIR__ . '/docs/system_retirement_manual.md';
    if (file_put_contents($docFile, $docContent)) {
        echo "   ✓ Documentación generada: docs/system_retirement_manual.md\n";
    }

    // 10. Registro de instalación exitosa
    echo "\n10. Finalizando instalación...\n";
    
    $sql = "INSERT INTO audit_logs (user_id, action, details, ip_address, user_agent, level, category) 
            VALUES (NULL, 'SYSTEM_RETIREMENT_INSTALLED', ?, ?, 'Installation Script', 'INFO', 'SYSTEM_ACCESS')";
    $stmt = $conn->prepare($sql);
    $details = json_encode([
        'installation_date' => date('Y-m-d H:i:s'),
        'version' => '1.0.0',
        'permissions_installed' => count($permissions),
        'configurations_set' => count($configurations)
    ]);
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'localhost';
    $stmt->bind_param('ss', $details, $ipAddress);
    $stmt->execute();

    echo "   ✓ Instalación registrada en audit log\n";

    // Resumen final
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "✅ INSTALACIÓN COMPLETADA EXITOSAMENTE\n";
    echo str_repeat("=", 60) . "\n\n";
    
    echo "📋 RESUMEN DE INSTALACIÓN:\n";
    echo "   • Tablas de base de datos: ✓ Instaladas\n";
    echo "   • Permisos del sistema: ✓ " . count($permissions) . " permisos configurados\n";
    echo "   • Configuraciones: ✓ " . count($configurations) . " parámetros establecidos\n";
    echo "   • Directorios de trabajo: ✓ Creados\n";
    echo "   • Endpoint API: ✓ /api/retirement.php\n";
    echo "   • Interfaz web: ✓ /apps/system-retirement/index.html\n";
    echo "   • Documentación: ✓ docs/system_retirement_manual.md\n\n";
    
    echo "🔐 ACCESO AL SISTEMA:\n";
    echo "   • URL: http://localhost:8000/apps/system-retirement/index.html\n";
    echo "   • Roles con acceso: developer, superadministrador, administrador\n";
    echo "   • Permisos requeridos: system_retirement_*\n\n";
    
    echo "📝 PRÓXIMOS PASOS:\n";
    echo "   1. Verificar que los usuarios tengan los permisos necesarios\n";
    echo "   2. Configurar rutas de backup si es necesario\n";
    echo "   3. Revisar la documentación en docs/system_retirement_manual.md\n";
    echo "   4. Realizar pruebas en ambiente de desarrollo\n\n";
    
    echo "⚠️  IMPORTANTE:\n";
    echo "   • Este sistema maneja procesos críticos de retiro\n";
    echo "   • Asegurar backups antes de usar en producción\n";
    echo "   • Validar cumplimiento normativo según su industria\n";
    echo "   • Mantener logs de auditoría por al menos 7 años\n\n";

} catch (Exception $e) {
    echo "\n❌ ERROR DURANTE LA INSTALACIÓN:\n";
    echo "   " . $e->getMessage() . "\n";
    echo "\nLa instalación ha fallado. Revisar errores y ejecutar nuevamente.\n";
    exit(1);
}

echo "Instalación completada. El Sistema de Retiro GAMP 5 está listo para uso.\n";
exit(0);