@echo off
echo.
echo ============================================
echo   SISTEMA DE GESTION DE CAMBIOS ISO 17025
echo   Iniciador Rapido del Sistema
echo ============================================
echo.

REM Configurar variables
set BASEPATH=%~dp0
set NODEPATH=%BASEPATH%app\Modules\AlertSystem
set PHPPATH=%BASEPATH%

REM Verificar si Node.js está instalado
node --version >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Node.js no esta instalado o no esta en el PATH
    echo Por favor instale Node.js desde https://nodejs.org/
    pause
    exit /b 1
)

REM Verificar si PHP está instalado
php --version >nul 2>&1
if errorlevel 1 (
    echo [ERROR] PHP no esta instalado o no esta en el PATH
    echo Por favor instale PHP o agregue al PATH del sistema
    pause
    exit /b 1
)

echo [INFO] Verificando requisitos del sistema...
echo [OK] Node.js disponible
echo [OK] PHP disponible
echo.

REM Verificar si existe la configuración
if not exist "%BASEPATH%app\config.php" (
    echo [WARNING] Archivo de configuracion no encontrado
    echo Ejecutando instalador automatico...
    php "%BASEPATH%install_change_management_system.php"
    if errorlevel 1 (
        echo [ERROR] Error durante la instalacion
        pause
        exit /b 1
    )
)

REM Verificar dependencias de Node.js
if not exist "%NODEPATH%node_modules" (
    echo [INFO] Instalando dependencias de Node.js...
    cd /d "%NODEPATH%"
    call npm install
    if errorlevel 1 (
        echo [ERROR] Error instalando dependencias de Node.js
        pause
        exit /b 1
    )
    cd /d "%BASEPATH%"
)

REM Verificar base de datos
echo [INFO] Verificando conexion a base de datos...
php -r "
$config = require '%BASEPATH%app/config.php';
$conn = new mysqli($config['database']['host'], $config['database']['user'], $config['database']['password'], $config['database']['name']);
if ($conn->connect_error) {
    echo '[ERROR] Error conectando a la base de datos: ' . $conn->connect_error . PHP_EOL;
    exit(1);
} else {
    echo '[OK] Conexion a base de datos exitosa' . PHP_EOL;
}
$conn->close();
"
if errorlevel 1 (
    echo.
    echo [ERROR] No se pudo conectar a la base de datos
    echo Verifique la configuracion en app\config.php
    pause
    exit /b 1
)

echo.
echo =============================================
echo   INICIANDO SERVICIOS DEL SISTEMA
echo =============================================
echo.

REM Iniciar servidor de alertas Node.js
echo [INFO] Iniciando servidor de alertas en segundo plano...
cd /d "%NODEPATH%"
start /b "AlertSystem" cmd /c "npm start > logs\server.log 2>&1"
timeout /t 3 /nobreak >nul

REM Verificar que el servidor Node.js esté ejecutándose
echo [INFO] Verificando servidor de Node.js...
php -r "
$ch = curl_init('http://localhost:3001/api/system/status');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo '[OK] Servidor de alertas ejecutandose en puerto 3001' . PHP_EOL;
} else {
    echo '[WARNING] Servidor de alertas no responde (puede tardar unos segundos)' . PHP_EOL;
}
"

cd /d "%BASEPATH%"

REM Iniciar servidor web PHP (desarrollo)
echo [INFO] Iniciando servidor web PHP en puerto 8000...
start /b "WebServer" cmd /c "php -S localhost:8000 -t public > storage\logs\webserver.log 2>&1"
timeout /t 2 /nobreak >nul

echo.
echo =============================================
echo   SISTEMA INICIADO EXITOSAMENTE
echo =============================================
echo.
echo [INFO] Servicios activos:
echo   - Servidor Web: http://localhost:8000
echo   - Dashboard Admin: http://localhost:8000/admin_dashboard.html
echo   - Servidor Alertas: http://localhost:3001
echo   - API de Salud: http://localhost:3001/api/system/status
echo.
echo [INFO] Credenciales por defecto:
echo   - Usuario: developer
echo   - Password: developer123
echo   - IMPORTANTE: Cambiar password despues del primer acceso
echo.
echo [INFO] Logs del sistema:
echo   - Aplicacion: %BASEPATH%storage\logs\
echo   - Servidor Web: %BASEPATH%storage\logs\webserver.log
echo   - Alertas: %NODEPATH%\logs\server.log
echo.

REM Preguntar si abrir el dashboard
set /p OPEN_DASHBOARD="¿Desea abrir el dashboard en el navegador? (S/N): "
if /i "%OPEN_DASHBOARD%"=="S" (
    echo [INFO] Abriendo dashboard en el navegador...
    start http://localhost:8000/admin_dashboard.html
)

echo.
echo [INFO] Para detener el sistema, cierre esta ventana o ejecute stop_sistema.bat
echo [INFO] Para ver el estado del sistema: http://localhost:3001/api/system/status
echo.

REM Mantener la ventana abierta y mostrar logs en tiempo real
echo =============================================
echo   MONITOREANDO SISTEMA - Ctrl+C para salir
echo =============================================
echo.

:monitor_loop
timeout /t 10 /nobreak >nul
php -r "
echo '[' . date('Y-m-d H:i:s') . '] Sistema activo - Verificando estado...' . PHP_EOL;

// Verificar servidor web
$ch = curl_init('http://localhost:8000');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 2);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
$response = curl_exec($ch);
$webStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Verificar servidor de alertas
$ch = curl_init('http://localhost:3001/api/system/status');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 2);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
$response = curl_exec($ch);
$alertStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo '  - Servidor Web: ' . ($webStatus === 200 ? 'OK' : 'ERROR') . PHP_EOL;
echo '  - Sistema Alertas: ' . ($alertStatus === 200 ? 'OK' : 'ERROR') . PHP_EOL;

if ($webStatus !== 200 || $alertStatus !== 200) {
    echo '  [WARNING] Algunos servicios no estan respondiendo' . PHP_EOL;
}
echo PHP_EOL;
"
goto monitor_loop