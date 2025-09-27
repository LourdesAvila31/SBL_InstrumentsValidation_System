<?php
// Archivo de prueba ultra simple para verificar XAMPP
echo "XAMPP FUNCIONA CORRECTAMENTE";
echo "<br>";
echo "Fecha y hora: " . date('Y-m-d H:i:s');
echo "<br>";
echo "PHP Version: " . PHP_VERSION;
echo "<br>";
echo "Servidor: " . $_SERVER['HTTP_HOST'];
echo "<br>";
echo "Ruta actual: " . __FILE__;
?>