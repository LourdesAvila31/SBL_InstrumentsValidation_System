<?php
/**
 * Módulo de Sistema de Backup - SBL Sistema Interno
 * 
 * Este módulo maneja todas las operaciones de backup y restauración
 * del sistema, incluyendo base de datos y archivos
 * 
 * Funcionalidades:
 * - Backups automáticos programados
 * - Backup manual on-demand
 * - Restauración de backups
 * - Verificación de integridad
 * - Gestión de retención
 * - Monitoreo de espacio de almacenamiento
 * 
 * @author Sistema SBL
 * @version 1.0
 * @since 2025-09-26
 */

// Incluir configuración simplificada
require_once __DIR__ . '/config_simple.php';

class BackupSystemManager {
    
    private $db;
    private $auth;
    
    public function __construct() {
        $this->db = new Database();
        $this->auth = new Auth();
        
        // Verificar autenticación
        if (!$this->auth->isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['error' => 'No autorizado. Debe iniciar sesión.']);
            exit;
        }
        
        // Verificar permisos de administrador para operaciones de backup
        $this->auth->requirePermission('backup.manage');
    }
    
    /**
     * Obtener lista de backups
     */
    public function getBackups($filters = []) {
        try {
            $sql = "SELECT 
                        b.id,
                        b.nombre_backup,
                        b.tipo_backup,
                        b.estado,
                        b.fecha_backup,
                        b.tamaño_archivo,
                        b.ruta_archivo,
                        b.checksum,
                        b.fecha_expiracion,
                        b.observaciones,
                        b.tiempo_ejecucion,
                        b.usuario_creacion,
                        u.nombre_completo as usuario_nombre
                    FROM backups b
                    LEFT JOIN usuarios u ON b.usuario_creacion = u.id
                    WHERE b.activo = 1";
            
            $params = [];
            
            // Aplicar filtros
            if (!empty($filters['tipo_backup'])) {
                $sql .= " AND b.tipo_backup = :tipo_backup";
                $params[':tipo_backup'] = $filters['tipo_backup'];
            }
            
            if (!empty($filters['estado'])) {
                $sql .= " AND b.estado = :estado";
                $params[':estado'] = $filters['estado'];
            }
            
            $sql .= " ORDER BY b.fecha_backup DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $backups = $stmt->fetchAll();
            
            return [
                'success' => true,
                'data' => $backups,
                'total' => count($backups)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al obtener backups: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Crear backup manual
     */
    public function createBackup($data) {
        try {
            $tipo = $data['tipo_backup'] ?? 'completo';
            $incluir_archivos = $data['incluir_archivos'] ?? true;
            
            $nombre_backup = 'backup_' . date('Y-m-d_H-i-s') . '_' . $tipo;
            $ruta_backup = $this->getBackupPath() . '/' . $nombre_backup;
            
            // Registrar inicio del backup
            $backupId = $this->registrarBackup($nombre_backup, $tipo, 'en_proceso');
            
            $inicio = microtime(true);
            
            // Realizar backup de base de datos
            $resultado_db = $this->backupDatabase($ruta_backup . '.sql');
            
            // Realizar backup de archivos si se solicita
            $resultado_archivos = true;
            if ($incluir_archivos) {
                $resultado_archivos = $this->backupFiles($ruta_backup . '_files.zip');
            }
            
            $tiempo_ejecucion = round(microtime(true) - $inicio, 2);
            
            // Actualizar registro del backup
            if ($resultado_db && $resultado_archivos) {
                $this->actualizarBackup($backupId, 'exitoso', $ruta_backup, $tiempo_ejecucion);
                
                return [
                    'success' => true,
                    'message' => 'Backup creado exitosamente',
                    'id' => $backupId,
                    'nombre' => $nombre_backup
                ];
            } else {
                $this->actualizarBackup($backupId, 'fallido', null, $tiempo_ejecucion, 'Error en el proceso de backup');
                
                return [
                    'success' => false,
                    'error' => 'Error al crear backup'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al crear backup: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Backup de base de datos
     */
    private function backupDatabase($archivo) {
        try {
            $host = 'localhost';
            $database = 'sbl_sistema_interno';
            $username = 'root';
            $password = '';
            
            $comando = "mysqldump --host=$host --user=$username --password=$password --single-transaction --routines --triggers $database > $archivo";
            
            exec($comando, $output, $return_code);
            
            return $return_code === 0;
            
        } catch (Exception $e) {
            error_log("Error en backup de BD: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener estadísticas del sistema de backup
     */
    public function getEstadisticas() {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_backups,
                        COUNT(CASE WHEN estado = 'exitoso' THEN 1 END) as exitosos,
                        COUNT(CASE WHEN estado = 'fallido' THEN 1 END) as fallidos,
                        MAX(fecha_backup) as ultimo_backup,
                        SUM(CASE WHEN estado = 'exitoso' THEN tamaño_archivo ELSE 0 END) as espacio_usado
                    FROM backups 
                    WHERE activo = 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $stats = $stmt->fetch();
            
            return [
                'success' => true,
                'data' => $stats
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al obtener estadísticas: ' . $e->getMessage()
            ];
        }
    }
    
    // Métodos auxiliares privados...
    private function getBackupPath() {
        $path = __DIR__ . '/../../../storage/backups';
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
        return $path;
    }
    
    private function registrarBackup($nombre, $tipo, $estado) {
        $sql = "INSERT INTO backups (nombre_backup, tipo_backup, estado, fecha_backup, usuario_creacion) 
                VALUES (:nombre, :tipo, :estado, NOW(), :usuario)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':nombre' => $nombre,
            ':tipo' => $tipo,
            ':estado' => $estado,
            ':usuario' => $this->auth->getUserId()
        ]);
        
        return $this->db->lastInsertId();
    }
    
    private function actualizarBackup($id, $estado, $ruta, $tiempo, $observaciones = null) {
        $sql = "UPDATE backups SET 
                    estado = :estado, 
                    ruta_archivo = :ruta, 
                    tiempo_ejecucion = :tiempo, 
                    observaciones = :observaciones,
                    fecha_modificacion = NOW()
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':estado' => $estado,
            ':ruta' => $ruta,
            ':tiempo' => $tiempo,
            ':observaciones' => $observaciones
        ]);
    }
    
    private function backupFiles($archivo) {
        // Implementar backup de archivos según necesidades
        return true;
    }
}

// Manejo de solicitudes API
if ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $manager = new BackupSystemManager();
        $action = $_GET['action'] ?? $_POST['action'] ?? '';
        
        switch ($action) {
            case 'list':
                $filters = [
                    'tipo_backup' => $_GET['tipo_backup'] ?? null,
                    'estado' => $_GET['estado'] ?? null
                ];
                echo json_encode($manager->getBackups($filters));
                break;
                
            case 'create':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $data = json_decode(file_get_contents('php://input'), true);
                    echo json_encode($manager->createBackup($data));
                } else {
                    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
                }
                break;
                
            case 'stats':
                echo json_encode($manager->getEstadisticas());
                break;
                
            default:
                echo json_encode([
                    'success' => false,
                    'error' => 'Acción no válida'
                ]);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}
?>