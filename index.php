<?php
echo "<h1>SBL Sistema Interno - Índice</h1>";
echo "<p>Servidor funcionando correctamente</p>";
echo "<p>Ruta actual: " . __DIR__ . "</p>";
echo "<p>Archivo: " . __FILE__ . "</p>";
echo "<hr>";
echo "<h2>Enlaces disponibles:</h2>";
echo "<ul>";
echo "<li><a href='test.php'>Test de sistema</a></li>";
echo "<li><a href='backend/instrumentos/setup_instrumentos_table.php'>Setup de base de datos</a></li>";
echo "<li><a href='backend/instrumentos/gages/list_gages.php'>API - Lista de instrumentos</a></li>";
echo "<li><a href='public/apps/internal/instrumentos/list_gages.html'>Interfaz de instrumentos</a></li>";
echo "</ul>";
?>