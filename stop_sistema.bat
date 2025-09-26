@echo off
echo.
echo ============================================
echo   SISTEMA DE GESTION DE CAMBIOS ISO 17025
echo   Deteniendo Servicios
echo ============================================
echo.

echo [INFO] Deteniendo servicios del sistema...

REM Detener servidor Node.js (AlertSystem)
echo [INFO] Deteniendo servidor de alertas...
taskkill /f /im node.exe >nul 2>&1
if errorlevel 1 (
    echo [INFO] No se encontraron procesos de Node.js ejecutandose
) else (
    echo [OK] Servidor de alertas detenido
)

REM Detener servidor PHP
echo [INFO] Deteniendo servidor web PHP...
for /f "tokens=5" %%a in ('netstat -aon ^| findstr :8000') do (
    taskkill /f /pid %%a >nul 2>&1
)
echo [OK] Servidor web detenido

REM Limpiar procesos residuales
echo [INFO] Limpiando procesos residuales...
taskkill /f /fi "WINDOWTITLE eq AlertSystem*" >nul 2>&1
taskkill /f /fi "WINDOWTITLE eq WebServer*" >nul 2>&1

echo.
echo [OK] Todos los servicios han sido detenidos
echo.
echo ============================================
echo   SISTEMA DETENIDO EXITOSAMENTE
echo ============================================
echo.

pause