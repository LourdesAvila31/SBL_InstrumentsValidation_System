<?php
/**
 * Gestor de Calificación de Procesos conforme a GAMP 5
 * 
 * Esta clase gestiona los procesos de calificación IQ/OQ/PQ 
 * (Installation/Operational/Performance Qualification)
 * según los estándares GAMP 5 y regulaciones GxP
 */

require_once dirname(__DIR__, 2) . '/Core/db_config.php';
require_once dirname(__DIR__, 2) . '/Core/permissions.php';

class ProcessQualificationManager
{
    private $db;
    private $usuario_id;
    
    // Tipos de calificación según GAMP 5
    const QUALIFICATION_TYPES = [
        'IQ' => 'Installation Qualification',
        'OQ' => 'Operational Qualification', 
        'PQ' => 'Performance Qualification',
        'DQ' => 'Design Qualification'
    ];
    
    // Estados de calificación
    const QUALIFICATION_STATUS = [
        'PLANNED' => 'Planificado',
        'IN_PROGRESS' => 'En Progreso',
        'COMPLETED' => 'Completado',
        'APPROVED' => 'Aprobado',
        'REJECTED' => 'Rechazado',
        'ON_HOLD' => 'En Espera'
    ];
    
    // Criterios de aceptación por tipo
    const ACCEPTANCE_CRITERIA = [
        'IQ' => [
            'Equipment properly installed according to specifications',
            'Environmental conditions meet requirements',
            'Utilities properly connected and functional',
            'Documentation package complete and approved',
            'Safety systems operational',
            'Calibration certificates available and current'
        ],
        'OQ' => [
            'All operational parameters within specified ranges',
            'Control systems respond correctly to inputs',
            'Alarm systems functional and properly configured',
            'User interfaces operate as designed',
            'Data integrity controls functioning',
            'Security features operational'
        ],
        'PQ' => [
            'Process performs consistently within specifications',
            'Product quality meets all requirements',
            'Process capability demonstrated',
            'Statistical analysis confirms performance',
            'Worst-case scenarios successfully handled',
            'Long-term stability demonstrated'
        ]
    ];

    public function __construct($usuario_id = null)
    {
        $this->db = DatabaseManager::getConnection();
        $this->usuario_id = $usuario_id ?? $_SESSION['usuario_id'] ?? null;
        $this->initializeTables();
    }

    /**
     * Inicializa las tablas necesarias para la gestión de calificaciones
     */
    private function initializeTables()
    {
        $queries = [
            // Tabla principal de calificaciones
            "CREATE TABLE IF NOT EXISTS gamp5_qualifications (
                id INT PRIMARY KEY AUTO_INCREMENT,
                system_name VARCHAR(255) NOT NULL,
                qualification_type ENUM('IQ', 'OQ', 'PQ', 'DQ') NOT NULL,
                protocol_number VARCHAR(100) NOT NULL UNIQUE,
                protocol_version VARCHAR(20) NOT NULL DEFAULT '1.0',
                description TEXT,
                scope TEXT,
                acceptance_criteria JSON,
                prerequisites JSON,
                status ENUM('PLANNED', 'IN_PROGRESS', 'COMPLETED', 'APPROVED', 'REJECTED', 'ON_HOLD') NOT NULL DEFAULT 'PLANNED',
                planned_start_date DATE,
                actual_start_date DATE NULL,
                planned_end_date DATE,
                actual_end_date DATE NULL,
                created_by INT NOT NULL,
                performed_by INT NULL,
                reviewed_by INT NULL,
                approved_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_qualification_type (qualification_type),
                INDEX idx_status (status),
                INDEX idx_protocol_number (protocol_number)
            )",
            
            // Tabla de pasos de calificación
            "CREATE TABLE IF NOT EXISTS gamp5_qualification_steps (
                id INT PRIMARY KEY AUTO_INCREMENT,
                qualification_id INT NOT NULL,
                step_number INT NOT NULL,
                step_title VARCHAR(255) NOT NULL,
                description TEXT,
                expected_result TEXT,
                actual_result TEXT,
                pass_fail ENUM('PASS', 'FAIL', 'N/A', 'PENDING') DEFAULT 'PENDING',
                comments TEXT,
                evidence_path VARCHAR(500),
                performed_by INT NULL,
                performed_at TIMESTAMP NULL,
                reviewed_by INT NULL,
                reviewed_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (qualification_id) REFERENCES gamp5_qualifications(id) ON DELETE CASCADE,
                INDEX idx_qualification_step (qualification_id, step_number),
                INDEX idx_pass_fail (pass_fail)
            )",
            
            // Tabla de evidencias y documentos
            "CREATE TABLE IF NOT EXISTS gamp5_qualification_evidence (
                id INT PRIMARY KEY AUTO_INCREMENT,
                qualification_id INT NOT NULL,
                step_id INT NULL,
                evidence_type ENUM('SCREENSHOT', 'DOCUMENT', 'TEST_DATA', 'CALIBRATION_CERT', 'PHOTO', 'VIDEO', 'OTHER') NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                file_path VARCHAR(500) NOT NULL,
                file_size INT,
                description TEXT,
                uploaded_by INT NOT NULL,
                uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (qualification_id) REFERENCES gamp5_qualifications(id) ON DELETE CASCADE,
                FOREIGN KEY (step_id) REFERENCES gamp5_qualification_steps(id) ON DELETE SET NULL,
                INDEX idx_qualification_evidence (qualification_id),
                INDEX idx_evidence_type (evidence_type)
            )",
            
            // Tabla de desvíos y acciones correctivas
            "CREATE TABLE IF NOT EXISTS gamp5_qualification_deviations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                qualification_id INT NOT NULL,
                step_id INT NULL,
                deviation_number VARCHAR(50) NOT NULL,
                description TEXT NOT NULL,
                impact_assessment TEXT,
                severity ENUM('MINOR', 'MAJOR', 'CRITICAL') NOT NULL,
                corrective_action TEXT,
                preventive_action TEXT,
                status ENUM('OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED') NOT NULL DEFAULT 'OPEN',
                identified_by INT NOT NULL,
                assigned_to INT NULL,
                due_date DATE,
                resolved_date DATE NULL,
                resolution_description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (qualification_id) REFERENCES gamp5_qualifications(id) ON DELETE CASCADE,
                FOREIGN KEY (step_id) REFERENCES gamp5_qualification_steps(id) ON DELETE SET NULL,
                INDEX idx_qualification_deviation (qualification_id),
                INDEX idx_deviation_status (status),
                INDEX idx_severity (severity)
            )",
            
            // Tabla de reportes de calificación
            "CREATE TABLE IF NOT EXISTS gamp5_qualification_reports (
                id INT PRIMARY KEY AUTO_INCREMENT,
                qualification_id INT NOT NULL,
                report_type ENUM('INTERIM', 'FINAL', 'SUMMARY') NOT NULL,
                report_title VARCHAR(255) NOT NULL,
                executive_summary TEXT,
                conclusions TEXT,
                recommendations TEXT,
                overall_result ENUM('PASS', 'FAIL', 'CONDITIONAL_PASS') NOT NULL,
                generated_by INT NOT NULL,
                generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                approved_by INT NULL,
                approved_at TIMESTAMP NULL,
                report_path VARCHAR(500),
                FOREIGN KEY (qualification_id) REFERENCES gamp5_qualifications(id) ON DELETE CASCADE,
                INDEX idx_qualification_report (qualification_id),
                INDEX idx_report_type (report_type)
            )"
        ];

        foreach ($queries as $query) {
            if (!$this->db->query($query)) {
                error_log("Error creating qualification table: " . $this->db->error);
            }
        }
    }

    /**
     * Crea una nueva calificación
     */
    public function createQualification($data)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO gamp5_qualifications 
                (system_name, qualification_type, protocol_number, protocol_version, description, 
                 scope, acceptance_criteria, prerequisites, planned_start_date, planned_end_date, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $acceptance_criteria = json_encode($data['acceptance_criteria'] ?? self::ACCEPTANCE_CRITERIA[$data['qualification_type']] ?? []);
            $prerequisites = json_encode($data['prerequisites'] ?? []);
            
            $stmt->bind_param("ssssssssssi", 
                $data['system_name'],
                $data['qualification_type'],
                $data['protocol_number'],
                $data['protocol_version'] ?? '1.0',
                $data['description'],
                $data['scope'],
                $acceptance_criteria,
                $prerequisites,
                $data['planned_start_date'],
                $data['planned_end_date'],
                $this->usuario_id
            );
            
            if ($stmt->execute()) {
                $qualification_id = $this->db->insert_id;
                
                // Crear pasos predeterminados según el tipo de calificación
                $this->createDefaultSteps($qualification_id, $data['qualification_type']);
                
                $this->logAuditEvent('QUALIFICATION_CREATED', [
                    'qualification_id' => $qualification_id,
                    'type' => $data['qualification_type'],
                    'protocol_number' => $data['protocol_number']
                ]);
                
                return ['success' => true, 'qualification_id' => $qualification_id];
            }
            
            return ['success' => false, 'error' => 'Error al crear la calificación'];
            
        } catch (Exception $e) {
            error_log("Error in createQualification: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error interno del servidor'];
        }
    }

    /**
     * Crea pasos predeterminados según el tipo de calificación
     */
    private function createDefaultSteps($qualification_id, $type)
    {
        $default_steps = [
            'IQ' => [
                ['step_title' => 'Verificar Documentación de Diseño', 'description' => 'Verificar que toda la documentación de diseño esté completa y aprobada'],
                ['step_title' => 'Inspección Visual del Equipo', 'description' => 'Realizar inspección visual completa del equipo instalado'],
                ['step_title' => 'Verificar Conexiones de Utilidades', 'description' => 'Verificar todas las conexiones eléctricas, neumáticas, hidráulicas'],
                ['step_title' => 'Verificar Condiciones Ambientales', 'description' => 'Confirmar que las condiciones ambientales cumplan especificaciones'],
                ['step_title' => 'Verificar Sistemas de Seguridad', 'description' => 'Probar todos los sistemas de seguridad instalados'],
                ['step_title' => 'Verificar Calibraciones', 'description' => 'Confirmar que todos los instrumentos estén calibrados y certificados']
            ],
            'OQ' => [
                ['step_title' => 'Verificar Parámetros Operacionales', 'description' => 'Verificar que todos los parámetros operacionales estén dentro de rango'],
                ['step_title' => 'Probar Sistemas de Control', 'description' => 'Verificar respuesta correcta de todos los sistemas de control'],
                ['step_title' => 'Probar Sistemas de Alarma', 'description' => 'Verificar funcionamiento de todos los sistemas de alarma'],
                ['step_title' => 'Verificar Interfaces de Usuario', 'description' => 'Probar todas las interfaces de usuario y pantallas'],
                ['step_title' => 'Verificar Integridad de Datos', 'description' => 'Probar controles de integridad de datos y trazabilidad'],
                ['step_title' => 'Verificar Funciones de Seguridad', 'description' => 'Probar todas las funciones de seguridad operacional']
            ],
            'PQ' => [
                ['step_title' => 'Demostrar Consistencia del Proceso', 'description' => 'Ejecutar múltiples ciclos para demostrar consistencia'],
                ['step_title' => 'Verificar Calidad del Producto', 'description' => 'Confirmar que el producto cumple todas las especificaciones'],
                ['step_title' => 'Análisis de Capacidad de Proceso', 'description' => 'Realizar análisis estadístico de capacidad del proceso'],
                ['step_title' => 'Pruebas de Peor Caso', 'description' => 'Ejecutar escenarios de peor caso y condiciones límite'],
                ['step_title' => 'Estudios de Estabilidad', 'description' => 'Demostrar estabilidad del proceso en el tiempo'],
                ['step_title' => 'Validación de Métodos Analíticos', 'description' => 'Validar métodos analíticos utilizados en el proceso']
            ]
        ];
        
        $steps = $default_steps[$type] ?? [];
        
        foreach ($steps as $index => $step) {
            $stmt = $this->db->prepare("
                INSERT INTO gamp5_qualification_steps 
                (qualification_id, step_number, step_title, description)
                VALUES (?, ?, ?, ?)
            ");
            
            $step_number = $index + 1;
            $stmt->bind_param("iiss", $qualification_id, $step_number, $step['step_title'], $step['description']);
            $stmt->execute();
        }
    }

    /**
     * Ejecuta un paso de calificación
     */
    public function executeQualificationStep($step_id, $execution_data)
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE gamp5_qualification_steps 
                SET actual_result = ?, pass_fail = ?, comments = ?, 
                    performed_by = ?, performed_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->bind_param("sssii", 
                $execution_data['actual_result'],
                $execution_data['pass_fail'],
                $execution_data['comments'] ?? '',
                $this->usuario_id,
                $step_id
            );
            
            if ($stmt->execute()) {
                // Si el paso falló, crear una desviación automáticamente
                if ($execution_data['pass_fail'] === 'FAIL') {
                    $this->createAutoDeviation($step_id, $execution_data);
                }
                
                // Verificar si la calificación está completada
                $this->checkQualificationCompletion($step_id);
                
                $this->logAuditEvent('QUALIFICATION_STEP_EXECUTED', [
                    'step_id' => $step_id,
                    'result' => $execution_data['pass_fail']
                ]);
                
                return ['success' => true];
            }
            
            return ['success' => false, 'error' => 'Error al ejecutar el paso'];
            
        } catch (Exception $e) {
            error_log("Error in executeQualificationStep: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error interno del servidor'];
        }
    }

    /**
     * Crea una desviación automática cuando un paso falla
     */
    private function createAutoDeviation($step_id, $execution_data)
    {
        // Obtener información del paso y calificación
        $stmt = $this->db->prepare("
            SELECT qs.qualification_id, qs.step_title, q.protocol_number
            FROM gamp5_qualification_steps qs
            JOIN gamp5_qualifications q ON qs.qualification_id = q.id
            WHERE qs.id = ?
        ");
        $stmt->bind_param("i", $step_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result) {
            $deviation_number = $result['protocol_number'] . '-DEV-' . date('YmdHis');
            $description = "Fallo en paso: " . $result['step_title'] . "\nResultado: " . $execution_data['actual_result'];
            
            $this->createDeviation([
                'qualification_id' => $result['qualification_id'],
                'step_id' => $step_id,
                'deviation_number' => $deviation_number,
                'description' => $description,
                'severity' => 'MAJOR'
            ]);
        }
    }

    /**
     * Crea una desviación
     */
    public function createDeviation($data)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO gamp5_qualification_deviations 
                (qualification_id, step_id, deviation_number, description, impact_assessment, 
                 severity, corrective_action, preventive_action, identified_by, assigned_to, due_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param("iissssssiis", 
                $data['qualification_id'],
                $data['step_id'],
                $data['deviation_number'],
                $data['description'],
                $data['impact_assessment'] ?? '',
                $data['severity'],
                $data['corrective_action'] ?? '',
                $data['preventive_action'] ?? '',
                $this->usuario_id,
                $data['assigned_to'] ?? null,
                $data['due_date'] ?? null
            );
            
            if ($stmt->execute()) {
                $deviation_id = $this->db->insert_id;
                
                $this->logAuditEvent('DEVIATION_CREATED', [
                    'deviation_id' => $deviation_id,
                    'qualification_id' => $data['qualification_id'],
                    'severity' => $data['severity']
                ]);
                
                return ['success' => true, 'deviation_id' => $deviation_id];
            }
            
            return ['success' => false, 'error' => 'Error al crear la desviación'];
            
        } catch (Exception $e) {
            error_log("Error in createDeviation: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error interno del servidor'];
        }
    }

    /**
     * Verifica si una calificación está completada
     */
    private function checkQualificationCompletion($step_id)
    {
        // Obtener ID de calificación
        $stmt = $this->db->prepare("SELECT qualification_id FROM gamp5_qualification_steps WHERE id = ?");
        $stmt->bind_param("i", $step_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if (!$result) return;
        
        $qualification_id = $result['qualification_id'];
        
        // Verificar si todos los pasos están completados
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_steps,
                COUNT(CASE WHEN pass_fail IN ('PASS', 'FAIL', 'N/A') THEN 1 END) as completed_steps,
                COUNT(CASE WHEN pass_fail = 'FAIL' THEN 1 END) as failed_steps
            FROM gamp5_qualification_steps 
            WHERE qualification_id = ?
        ");
        $stmt->bind_param("i", $qualification_id);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();
        
        if ($stats['total_steps'] == $stats['completed_steps']) {
            // Todos los pasos completados
            $new_status = ($stats['failed_steps'] > 0) ? 'COMPLETED' : 'COMPLETED';
            
            $update_stmt = $this->db->prepare("
                UPDATE gamp5_qualifications 
                SET status = ?, actual_end_date = CURDATE(), updated_at = NOW()
                WHERE id = ?
            ");
            $update_stmt->bind_param("si", $new_status, $qualification_id);
            $update_stmt->execute();
            
            // Generar reporte automático
            $this->generateQualificationReport($qualification_id, 'FINAL');
        }
    }

    /**
     * Genera un reporte de calificación
     */
    public function generateQualificationReport($qualification_id, $report_type = 'FINAL')
    {
        try {
            // Obtener datos de la calificación
            $qualification = $this->getQualificationDetails($qualification_id);
            if (!$qualification['success']) {
                return $qualification;
            }
            
            $data = $qualification['data'];
            
            // Calcular estadísticas
            $stats = $this->getQualificationStats($qualification_id);
            
            // Determinar resultado general
            $overall_result = 'PASS';
            if ($stats['failed_steps'] > 0) {
                // Verificar si todas las desviaciones están cerradas
                $open_deviations = $this->getOpenDeviations($qualification_id);
                if (count($open_deviations) > 0) {
                    $overall_result = 'FAIL';
                } else {
                    $overall_result = 'CONDITIONAL_PASS';
                }
            }
            
            // Generar contenido del reporte
            $executive_summary = $this->generateExecutiveSummary($data, $stats, $overall_result);
            $conclusions = $this->generateConclusions($data, $stats, $overall_result);
            $recommendations = $this->generateRecommendations($data, $stats);
            
            // Insertar reporte en base de datos
            $stmt = $this->db->prepare("
                INSERT INTO gamp5_qualification_reports 
                (qualification_id, report_type, report_title, executive_summary, 
                 conclusions, recommendations, overall_result, generated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $report_title = "{$data['qualification_type']} Report - {$data['system_name']} - {$data['protocol_number']}";
            
            $stmt->bind_param("issssssi", 
                $qualification_id,
                $report_type,
                $report_title,
                $executive_summary,
                $conclusions,
                $recommendations,
                $overall_result,
                $this->usuario_id
            );
            
            if ($stmt->execute()) {
                $report_id = $this->db->insert_id;
                
                $this->logAuditEvent('QUALIFICATION_REPORT_GENERATED', [
                    'report_id' => $report_id,
                    'qualification_id' => $qualification_id,
                    'type' => $report_type,
                    'result' => $overall_result
                ]);
                
                return ['success' => true, 'report_id' => $report_id, 'overall_result' => $overall_result];
            }
            
            return ['success' => false, 'error' => 'Error al generar el reporte'];
            
        } catch (Exception $e) {
            error_log("Error in generateQualificationReport: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error interno del servidor'];
        }
    }

    /**
     * Obtiene estadísticas de una calificación
     */
    private function getQualificationStats($qualification_id)
    {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_steps,
                COUNT(CASE WHEN pass_fail = 'PASS' THEN 1 END) as passed_steps,
                COUNT(CASE WHEN pass_fail = 'FAIL' THEN 1 END) as failed_steps,
                COUNT(CASE WHEN pass_fail = 'N/A' THEN 1 END) as na_steps,
                COUNT(CASE WHEN pass_fail = 'PENDING' THEN 1 END) as pending_steps
            FROM gamp5_qualification_steps 
            WHERE qualification_id = ?
        ");
        $stmt->bind_param("i", $qualification_id);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Obtiene desviaciones abiertas
     */
    private function getOpenDeviations($qualification_id)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM gamp5_qualification_deviations 
            WHERE qualification_id = ? AND status IN ('OPEN', 'IN_PROGRESS')
        ");
        $stmt->bind_param("i", $qualification_id);
        $stmt->execute();
        
        $deviations = [];
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $deviations[] = $row;
        }
        
        return $deviations;
    }

    /**
     * Genera resumen ejecutivo del reporte
     */
    private function generateExecutiveSummary($data, $stats, $overall_result)
    {
        $result_text = [
            'PASS' => 'APROBADA',
            'FAIL' => 'NO APROBADA', 
            'CONDITIONAL_PASS' => 'APROBADA CONDICIONALMENTE'
        ];
        
        return "Este reporte presenta los resultados de la {$data['qualification_type']} para el sistema {$data['system_name']}. " .
               "De un total de {$stats['total_steps']} pasos ejecutados, {$stats['passed_steps']} fueron exitosos, " .
               "{$stats['failed_steps']} fallaron y {$stats['na_steps']} no aplicaron. " .
               "La calificación ha sido {$result_text[$overall_result]}.";
    }

    /**
     * Genera conclusiones del reporte
     */
    private function generateConclusions($data, $stats, $overall_result)
    {
        $conclusions = [];
        
        if ($overall_result === 'PASS') {
            $conclusions[] = "El sistema cumple con todos los criterios de aceptación definidos.";
            $conclusions[] = "No se identificaron desviaciones críticas.";
            $conclusions[] = "El sistema está listo para la siguiente fase.";
        } elseif ($overall_result === 'CONDITIONAL_PASS') {
            $conclusions[] = "El sistema cumple con los criterios principales de aceptación.";
            $conclusions[] = "Las desviaciones identificadas han sido evaluadas y resueltas.";
            $conclusions[] = "Se requiere seguimiento continuo de las acciones correctivas.";
        } else {
            $conclusions[] = "El sistema no cumple con todos los criterios de aceptación.";
            $conclusions[] = "Existen desviaciones pendientes de resolución.";
            $conclusions[] = "Se requieren acciones correctivas antes de proceder.";
        }
        
        return implode("\n", $conclusions);
    }

    /**
     * Genera recomendaciones del reporte
     */
    private function generateRecommendations($data, $stats)
    {
        $recommendations = [];
        
        if ($stats['failed_steps'] > 0) {
            $recommendations[] = "Revisar y cerrar todas las desviaciones abiertas.";
            $recommendations[] = "Implementar acciones correctivas y preventivas.";
        }
        
        $recommendations[] = "Mantener documentación actualizada.";
        $recommendations[] = "Establecer programa de verificación continua.";
        $recommendations[] = "Capacitar al personal en procedimientos operativos.";
        
        return implode("\n", $recommendations);
    }

    /**
     * Obtiene detalles de una calificación
     */
    public function getQualificationDetails($qualification_id)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    q.*,
                    u1.nombre as created_by_name,
                    u2.nombre as performed_by_name,
                    u3.nombre as reviewed_by_name,
                    u4.nombre as approved_by_name
                FROM gamp5_qualifications q
                LEFT JOIN usuarios u1 ON q.created_by = u1.id
                LEFT JOIN usuarios u2 ON q.performed_by = u2.id
                LEFT JOIN usuarios u3 ON q.reviewed_by = u3.id
                LEFT JOIN usuarios u4 ON q.approved_by = u4.id
                WHERE q.id = ?
            ");
            
            $stmt->bind_param("i", $qualification_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if ($result) {
                $result['acceptance_criteria'] = json_decode($result['acceptance_criteria'], true);
                $result['prerequisites'] = json_decode($result['prerequisites'], true);
                return ['success' => true, 'data' => $result];
            }
            
            return ['success' => false, 'error' => 'Calificación no encontrada'];
            
        } catch (Exception $e) {
            error_log("Error in getQualificationDetails: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error interno del servidor'];
        }
    }

    /**
     * Obtiene todas las calificaciones con filtros
     */
    public function getAllQualifications($filters = [])
    {
        try {
            $where_conditions = ['1=1'];
            $params = [];
            $types = '';
            
            if (!empty($filters['type'])) {
                $where_conditions[] = 'q.qualification_type = ?';
                $params[] = $filters['type'];
                $types .= 's';
            }
            
            if (!empty($filters['status'])) {
                $where_conditions[] = 'q.status = ?';
                $params[] = $filters['status'];
                $types .= 's';
            }
            
            if (!empty($filters['system_name'])) {
                $where_conditions[] = 'q.system_name LIKE ?';
                $params[] = '%' . $filters['system_name'] . '%';
                $types .= 's';
            }
            
            $sql = "
                SELECT 
                    q.*,
                    u1.nombre as created_by_name,
                    COUNT(qs.id) as total_steps,
                    COUNT(CASE WHEN qs.pass_fail = 'PASS' THEN 1 END) as passed_steps,
                    COUNT(CASE WHEN qs.pass_fail = 'FAIL' THEN 1 END) as failed_steps,
                    COUNT(CASE WHEN qs.pass_fail IN ('PASS', 'FAIL', 'N/A') THEN 1 END) as completed_steps
                FROM gamp5_qualifications q
                LEFT JOIN usuarios u1 ON q.created_by = u1.id
                LEFT JOIN gamp5_qualification_steps qs ON q.id = qs.qualification_id
                WHERE " . implode(' AND ', $where_conditions) . "
                GROUP BY q.id
                ORDER BY q.created_at DESC
            ";
            
            $stmt = $this->db->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $qualifications = [];
            while ($row = $result->fetch_assoc()) {
                $row['acceptance_criteria'] = json_decode($row['acceptance_criteria'], true);
                $row['prerequisites'] = json_decode($row['prerequisites'], true);
                $row['completion_percentage'] = $row['total_steps'] > 0 ? 
                    round(($row['completed_steps'] / $row['total_steps']) * 100, 2) : 0;
                $qualifications[] = $row;
            }
            
            return ['success' => true, 'data' => $qualifications];
            
        } catch (Exception $e) {
            error_log("Error in getAllQualifications: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al obtener las calificaciones'];
        }
    }

    /**
     * Registra un evento de auditoría
     */
    private function logAuditEvent($event_type, $details)
    {
        try {
            $audit_log = [
                'usuario_id' => $this->usuario_id,
                'evento' => $event_type,
                'detalles' => json_encode($details),
                'timestamp' => date('Y-m-d H:i:s'),
                'modulo' => 'GAMP5_QUALIFICATION'
            ];
            
            error_log("GAMP5 Qualification Audit Event: " . json_encode($audit_log));
            
        } catch (Exception $e) {
            error_log("Error logging qualification audit event: " . $e->getMessage());
        }
    }
}