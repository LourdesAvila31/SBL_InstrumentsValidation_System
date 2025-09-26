<?php
/**
 * Configuración de Base de Datos Simplificada
 * Para el módulo de Instrumentos
 */

class Database {
    private $pdo;
    
    public function __construct() {
        $host = 'localhost';
        $dbname = 'sbl_sistema_interno';
        $username = 'root';
        $password = '';
        
        try {
            // Primero conectar sin especificar base de datos
            $pdo_temp = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
            $pdo_temp->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Crear la base de datos si no existe
            $pdo_temp->exec("CREATE DATABASE IF NOT EXISTS $dbname");
            
            // Ahora conectar a la base de datos específica
            $this->pdo = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4", 
                $username, 
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            throw new Exception("Error de conexión a base de datos: " . $e->getMessage());
        }
    }
    
    public function prepare($sql) {
        return $this->pdo->prepare($sql);
    }
    
    public function query($sql) {
        return $this->pdo->query($sql);
    }
    
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    public function exec($sql) {
        return $this->pdo->exec($sql);
    }
}

class Auth {
    public function __construct() {
        // Iniciar sesión si no está iniciada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public function isLoggedIn() {
        // Para desarrollo, siempre devolver true
        // En producción, verificar sesión real
        return true;
        
        // Implementación real:
        // return isset($_SESSION['usuario_id']) && !empty($_SESSION['usuario_id']);
    }
    
    public function hasPermission($permission) {
        // Para desarrollo, siempre devolver true
        // En producción, verificar permisos reales
        return true;
        
        // Implementación real:
        // return $this->isLoggedIn() && 
        //        isset($_SESSION['permisos']) && 
        //        in_array($permission, $_SESSION['permisos']);
    }
}

// Simular sesión para desarrollo
if (!isset($_SESSION)) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    $_SESSION['usuario_id'] = 1; // Usuario de desarrollo
    $_SESSION['usuario_nombre'] = 'Desarrollador';
    $_SESSION['permisos'] = [
        'instrumentos_crear',
        'instrumentos_editar',
        'instrumentos_eliminar',
        'instrumentos_ver'
    ];
}
?>