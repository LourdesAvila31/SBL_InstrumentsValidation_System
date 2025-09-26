<?php
/**
 * Sistema de Auditoría de Cambios de Usuarios
 * 
 * Este módulo implementa un sistema completo de logs de auditoría para registrar
 * todos los cambios realizados por los usuarios en el sistema.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../Core/db.php';

/**
 * Clase para gestionar auditoría de cambios de usuarios
 */
class AuditManager
{
    private mysqli $conn;
    
    public function __construct(?mysqli $connection = null)
    {
        global $conn;
        $this->conn = $connection ?? $conn ?? DatabaseManager::getConnection();
    }

    /**
     * Registra un cambio en el sistema de auditoría
     */
    public function logChange(int $userId, string $action, string $tableName, ?int $recordId, array $oldData = [], array $newData = [], string $description = ''): bool
    {
        $stmt = $this->conn->prepare("
            INSERT INTO audit_logs (
                user_id, action_type, table_name, record_id, 
                old_data, new_data, description, ip_address, 
                user_agent, session_id, timestamp
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $sessionId = session_id();
        $oldDataJson = json_encode($oldData);
        $newDataJson = json_encode($newData);

        $stmt->bind_param(
            "isssisssss",
            $userId, $action, $tableName, $recordId,
            $oldDataJson, $newDataJson, $description,
            $ipAddress, $userAgent, $sessionId
        );

        return $stmt->execute();
    }

    /**
     * Obtiene el historial de auditoría con filtros
     */
    public function getAuditHistory(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $whereConditions = [];
        $params = [];
        $types = '';

        if (!empty($filters['user_id'])) {
            $whereConditions[] = "al.user_id = ?";
            $params[] = $filters['user_id'];
            $types .= 'i';
        }

        if (!empty($filters['action_type'])) {
            $whereConditions[] = "al.action_type = ?";
            $params[] = $filters['action_type'];
            $types .= 's';
        }

        if (!empty($filters['table_name'])) {
            $whereConditions[] = "al.table_name = ?";
            $params[] = $filters['table_name'];
            $types .= 's';
        }

        if (!empty($filters['date_from'])) {
            $whereConditions[] = "al.timestamp >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }

        if (!empty($filters['date_to'])) {
            $whereConditions[] = "al.timestamp <= ?";
            $params[] = $filters['date_to'];
            $types .= 's';
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $sql = "
            SELECT 
                al.id, al.user_id, u.nombre as user_name, u.usuario as username,
                al.action_type, al.table_name, al.record_id, al.old_data, 
                al.new_data, al.description, al.ip_address, al.user_agent,
                al.timestamp
            FROM audit_logs al
            LEFT JOIN usuarios u ON al.user_id = u.id
            {$whereClause}
            ORDER BY al.timestamp DESC
            LIMIT ? OFFSET ?
        ";

        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';

        $stmt = $this->conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Genera reporte detallado de auditoría
     */
    public function generateAuditReport(string $dateFrom, string $dateTo, ?int $userId = null): array
    {
        $whereCondition = "al.timestamp BETWEEN ? AND ?";
        $params = [$dateFrom, $dateTo];
        $types = 'ss';

        if ($userId) {
            $whereCondition .= " AND al.user_id = ?";
            $params[] = $userId;
            $types .= 'i';
        }

        $sql = "
            SELECT 
                al.action_type,
                al.table_name,
                COUNT(*) as action_count,
                u.nombre as user_name,
                DATE(al.timestamp) as action_date
            FROM audit_logs al
            LEFT JOIN usuarios u ON al.user_id = u.id
            WHERE {$whereCondition}
            GROUP BY al.action_type, al.table_name, u.id, DATE(al.timestamp)
            ORDER BY al.timestamp DESC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Middleware para auditar automáticamente cambios en modelos
     */
    public static function auditModelChange(string $action, string $tableName, int $recordId, array $oldData = [], array $newData = []): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = $_SESSION['usuario_id'] ?? null;
        if (!$userId) {
            return;
        }

        $auditManager = new self();
        $auditManager->logChange($userId, $action, $tableName, $recordId, $oldData, $newData);
    }

    /**
     * Obtiene estadísticas de actividad por usuario
     */
    public function getUserActivityStats(int $userId, int $days = 30): array
    {
        $sql = "
            SELECT 
                DATE(timestamp) as date,
                action_type,
                COUNT(*) as count
            FROM audit_logs 
            WHERE user_id = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(timestamp), action_type
            ORDER BY date DESC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $userId, $days);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Limpia logs antiguos (mantenimiento)
     */
    public function cleanOldLogs(int $daysToKeep = 365): int
    {
        $sql = "DELETE FROM audit_logs WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $daysToKeep);
        $stmt->execute();
        
        return $stmt->affected_rows;
    }
}