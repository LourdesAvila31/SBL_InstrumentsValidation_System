@echo off
echo ========================================
echo INSTALACION SISTEMA DEVELOPER SUPERADMIN
echo ========================================
echo.

echo Iniciando instalacion del sistema Developer con privilegios de Superadministrador...
echo.

REM Cambiar al directorio del proyecto
cd /d "C:\xampp\htdocs\SBL_SISTEMA_INTERNO"

echo 1. Ejecutando script de instalacion...
php tools\scripts\install_developer_superadmin.php

echo.
echo 2. Ejecutando script de esquema de base de datos...
mysql -u root -p -e "SOURCE tools/scripts/developer_superadmin_schema.sql" sbl_sistema_interno

echo.
echo ========================================
echo INSTALACION COMPLETADA
echo ========================================
echo.
echo Credenciales de acceso:
echo Usuario: developer
echo Contraseña: Developer!2024
echo URL: http://localhost/SBL_SISTEMA_INTERNO/public/apps/internal/developer/dashboard.html
echo.
echo NOTA: El usuario 'developer' tiene privilegios de Superadministrador completos.
echo Todas las acciones quedan registradas en los logs de auditoria.
echo.
pause