<?php
/**
 * Configuración de Base de Datos Simplificada
 * Para el módulo de AdminDashboard
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
    
    public function getPdo() {
        return $this->pdo;
    }
}

class Auth {
    public function isLoggedIn() {
        session_start();
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    public function getUserId() {
        session_start();
        return $_SESSION['user_id'] ?? null;
    }
    
    public function getUsername() {
        session_start();
        return $_SESSION['username'] ?? null;
    }
    
    public function hasPermission($permission) {
        session_start();
        return isset($_SESSION['permissions']) && 
               is_array($_SESSION['permissions']) && 
               in_array($permission, $_SESSION['permissions']);
    }
    
    public function requirePermission($permission) {
        if (!$this->hasPermission($permission)) {
            http_response_code(403);
            echo json_encode(['error' => 'Acceso denegado. Permisos insuficientes.']);
            exit;
        }
    }
    
    public function isAdmin() {
        session_start();
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
}
?>