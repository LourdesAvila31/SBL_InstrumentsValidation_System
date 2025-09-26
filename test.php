<?php
echo "<h1>Prueba PHP - SBL Sistema Interno</h1>";
echo "<p>Fecha y hora: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Este archivo está en: " . __FILE__ . "</p>";

// Probar conexión a MySQL
try {
    $pdo = new PDO("mysql:host=localhost", "root", "");
    echo "<p>✅ <strong>Conexión a MySQL: EXITOSA</strong></p>";
    
    // Listar bases de datos
    $stmt = $pdo->query("SHOW DATABASES");
    $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>Bases de datos disponibles:</p>";
    echo "<ul>";
    foreach ($databases as $db) {
        echo "<li>$db</li>";
    }
    echo "</ul>";
    
    // Verificar si existe nuestra base de datos
    if (in_array('sbl_sistema_interno', $databases)) {
        echo "<p>✅ <strong>Base de datos 'sbl_sistema_interno' encontrada</strong></p>";
        
        // Conectar a nuestra base de datos
        $pdo_db = new PDO("mysql:host=localhost;dbname=sbl_sistema_interno", "root", "");
        $stmt = $pdo_db->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<p>Tablas en sbl_sistema_interno:</p>";
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul>";
        
    } else {
        echo "<p>❌ <strong>Base de datos 'sbl_sistema_interno' NO encontrada</strong></p>";
        echo "<p>Creando base de datos...</p>";
        $pdo->exec("CREATE DATABASE IF NOT EXISTS sbl_sistema_interno");
        echo "<p>✅ Base de datos creada</p>";
    }
    
} catch (PDOException $e) {
    echo "<p>❌ <strong>Error de conexión a MySQL:</strong> " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h2>Enlaces de prueba:</h2>";
echo "<ul>";
echo "<li><a href='backend/instrumentos/setup_instrumentos_table.php'>Setup Base de Datos</a></li>";
echo "<li><a href='backend/instrumentos/gages/list_gages.php'>API - Lista Instrumentos</a></li>";
echo "<li><a href='public/apps/internal/instrumentos/list_gages.html'>Interfaz Instrumentos</a></li>";
echo "</ul>";
?>