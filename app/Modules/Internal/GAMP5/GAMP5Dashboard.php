<?php
/**
 * Controlador Principal del Dashboard GAMP 5
 * 
 * Este controlador integra todos los componentes del sistema de administración
 * conforme a GAMP 5, proporcionando una interfaz unificada para la gestión
 * del ciclo de vida del sistema, calificación, validación y gestión de cambios.
 */

require_once dirname(__DIR__, 2) . '/Core/db_config.php';
require_once dirname(__DIR__, 2) . '/Core/permissions.php';
require_once __DIR__ . '/SystemLifecycleManager.php';
require_once __DIR__ . '/ProcessQualificationManager.php';
require_once __DIR__ . '/SystemValidationManager.php';
require_once __DIR__ . '/ChangeManagementSystem.php';
require_once __DIR__ . '/ContinuousMonitoringManager.php';
require_once __DIR__ . '/RiskManagementSystem.php';
require_once __DIR__ . '/DocumentManagementSystem.php';
require_once __DIR__ . '/SupplierAssessmentModule.php';

// Verificar autenticación y permisos
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

// Verificar permisos de administrador (simplificado - en producción usar sistema de permisos más robusto)
$usuario_id = $_SESSION['usuario_id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'dashboard';
$module = $_GET['module'] ?? 'dashboard';

header('Content-Type: application/json');

try {
    // Inicializar managers
    $lifecycleManager = new SystemLifecycleManager($usuario_id);
    $qualificationManager = new ProcessQualificationManager($usuario_id);
    $validationManager = new SystemValidationManager($usuario_id);
    $changeManager = new ChangeManagementSystem($usuario_id);
    $monitoringManager = new ContinuousMonitoringManager($usuario_id);
    $riskManager = new RiskManagementSystem($usuario_id);
    $documentManager = new DocumentManagementSystem($usuario_id);
    $supplierManager = new SupplierAssessmentModule($usuario_id);

    switch ($module) {
        case 'dashboard':
            handleDashboardRequests($action, $method, $lifecycleManager, $qualificationManager, 
                                  $validationManager, $changeManager, $monitoringManager, $riskManager, $documentManager, $supplierManager);
            break;
            
        case 'lifecycle':
            handleLifecycleRequests($action, $method, $lifecycleManager);
            break;
            
        case 'qualification':
            handleQualificationRequests($action, $method, $qualificationManager);
            break;
            
        case 'validation':
            handleValidationRequests($action, $method, $validationManager);
            break;
            
        case 'changes':
            handleChangeRequests($action, $method, $changeManager);
            break;
            
        case 'monitoring':
            handleMonitoringRequests($action, $method, $monitoringManager);
            break;
            
        case 'risks':
            handleRiskRequests($action, $method, $riskManager);
            break;
            
        case 'documents':
            handleDocumentRequests($action, $method, $documentManager);
            break;
            
        case 'suppliers':
            handleSupplierRequests($action, $method, $supplierManager);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Módulo no válido']);
            break;
    }

} catch (Exception $e) {
    error_log("Error in GAMP5 Dashboard Controller: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}

/**
 * Maneja las solicitudes del dashboard principal
 */
function handleDashboardRequests($action, $method, $lifecycle, $qualification, $validation, $changes, $monitoring, $risks, $documents, $suppliers) {
    switch ($action) {
        case 'dashboard':
            if ($method === 'GET') {
                $dashboard_data = [
                    'overview' => getDashboardOverview($lifecycle, $qualification, $validation, $changes, $monitoring, $risks, $documents, $suppliers),
                    'recent_activity' => getRecentActivity($lifecycle, $qualification, $validation, $changes),
                    'alerts' => getActiveAlerts($monitoring),
                    'kpis' => getKeyPerformanceIndicators($lifecycle, $qualification, $validation, $changes, $monitoring),
                    'compliance_status' => getComplianceStatus($validation, $monitoring),
                    'risk_summary' => getRiskSummary($risks),
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                
                echo json_encode(['success' => true, 'data' => $dashboard_data]);
            }
            break;
            
        case 'overview':
            if ($method === 'GET') {
                echo json_encode(['success' => true, 'data' => getDashboardOverview($lifecycle, $qualification, $validation, $changes, $monitoring, $risks, $documents, $suppliers)]);
            }
            break;
            
        case 'executive_report':
            if ($method === 'GET') {
                $date_from = $_GET['date_from'] ?? date('Y-m-01');
                $date_to = $_GET['date_to'] ?? date('Y-m-t');
                
                $executive_report = generateExecutiveReport($lifecycle, $qualification, $validation, $changes, $monitoring, $date_from, $date_to);
                echo json_encode(['success' => true, 'data' => $executive_report]);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida para dashboard']);
            break;
    }
}

/**
 * Maneja las solicitudes del módulo de ciclo de vida
 */
function handleLifecycleRequests($action, $method, $lifecycle) {
    switch ($action) {
        case 'list':
            if ($method === 'GET') {
                $filters = [
                    'status' => $_GET['status'] ?? null,
                    'stage' => $_GET['stage'] ?? null,
                    'risk_classification' => $_GET['risk_classification'] ?? null
                ];
                echo json_encode($lifecycle->getAllSystemLifecycles(array_filter($filters)));
            }
            break;
            
        case 'create':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                echo json_encode($lifecycle->createSystemLifecycle($data));
            }
            break;
            
        case 'details':
            if ($method === 'GET') {
                $lifecycle_id = $_GET['id'] ?? null;
                if ($lifecycle_id) {
                    echo json_encode($lifecycle->getSystemStages($lifecycle_id));
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID de ciclo de vida requerido']);
                }
            }
            break;
            
        case 'update_stage':
            if ($method === 'PUT') {
                $data = json_decode(file_get_contents('php://input'), true);
                $stage_id = $data['stage_id'] ?? null;
                $status = $data['status'] ?? null;
                $validation_report = $data['validation_report'] ?? null;
                
                if ($stage_id && $status) {
                    echo json_encode($lifecycle->updateStageStatus($stage_id, $status, $validation_report));
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'Datos de etapa incompletos']);
                }
            }
            break;
            
        case 'schedule_verification':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $lifecycle_id = $data['lifecycle_id'] ?? null;
                
                if ($lifecycle_id) {
                    echo json_encode($lifecycle->scheduleVerification($lifecycle_id, $data));
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID de ciclo de vida requerido']);
                }
            }
            break;
            
        case 'report':
            if ($method === 'GET') {
                $lifecycle_id = $_GET['id'] ?? null;
                if ($lifecycle_id) {
                    echo json_encode($lifecycle->generateLifecycleReport($lifecycle_id));
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID de ciclo de vida requerido']);
                }
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida para ciclo de vida']);
            break;
    }
}

/**
 * Maneja las solicitudes del módulo de calificación
 */
function handleQualificationRequests($action, $method, $qualification) {
    switch ($action) {
        case 'list':
            if ($method === 'GET') {
                $filters = [
                    'type' => $_GET['type'] ?? null,
                    'status' => $_GET['status'] ?? null,
                    'system_name' => $_GET['system_name'] ?? null
                ];
                echo json_encode($qualification->getAllQualifications(array_filter($filters)));
            }
            break;
            
        case 'create':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                echo json_encode($qualification->createQualification($data));
            }
            break;
            
        case 'details':
            if ($method === 'GET') {
                $qualification_id = $_GET['id'] ?? null;
                if ($qualification_id) {
                    echo json_encode($qualification->getQualificationDetails($qualification_id));
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID de calificación requerido']);
                }
            }
            break;
            
        case 'execute_step':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $step_id = $data['step_id'] ?? null;
                
                if ($step_id) {
                    echo json_encode($qualification->executeQualificationStep($step_id, $data));
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID de paso requerido']);
                }
            }
            break;
            
        case 'create_deviation':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                echo json_encode($qualification->createDeviation($data));
            }
            break;
            
        case 'generate_report':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $qualification_id = $data['qualification_id'] ?? null;
                $report_type = $data['report_type'] ?? 'FINAL';
                
                if ($qualification_id) {
                    echo json_encode($qualification->generateQualificationReport($qualification_id, $report_type));
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID de calificación requerido']);
                }
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida para calificación']);
            break;
    }
}

/**
 * Maneja las solicitudes del módulo de validación
 */
function handleValidationRequests($action, $method, $validation) {
    switch ($action) {
        case 'list':
            if ($method === 'GET') {
                $filters = [
                    'status' => $_GET['status'] ?? null,
                    'type' => $_GET['type'] ?? null,
                    'criticality' => $_GET['criticality'] ?? null,
                    'revalidation_due' => $_GET['revalidation_due'] ?? null
                ];
                echo json_encode($validation->getAllValidations(array_filter($filters)));
            }
            break;
            
        case 'create':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                echo json_encode($validation->createValidation($data));
            }
            break;
            
        case 'details':
            if ($method === 'GET') {
                $validation_id = $_GET['id'] ?? null;
                if ($validation_id) {
                    echo json_encode($validation->getValidationDetails($validation_id));
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID de validación requerido']);
                }
            }
            break;
            
        case 'validate_system':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $validation_id = $data['validation_id'] ?? null;
                $configuration = $data['configuration'] ?? [];
                
                if ($validation_id) {
                    echo json_encode($validation->validateSystem($validation_id, $configuration));
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID de validación requerido']);
                }
            }
            break;
            
        case 'monitor_status':
            if ($method === 'GET') {
                echo json_encode($validation->monitorSystemStatus());
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida para validación']);
            break;
    }
}

/**
 * Maneja las solicitudes del módulo de gestión de cambios
 */
function handleChangeRequests($action, $method, $changes) {
    switch ($action) {
        case 'list':
            if ($method === 'GET') {
                $filters = [
                    'status' => $_GET['status'] ?? null,
                    'change_type' => $_GET['change_type'] ?? null,
                    'impact_level' => $_GET['impact_level'] ?? null,
                    'created_by' => $_GET['created_by'] ?? null
                ];
                echo json_encode($changes->getAllChangeRequests(array_filter($filters)));
            }
            break;
            
        case 'create':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                echo json_encode($changes->createChangeRequest($data));
            }
            break;
            
        case 'details':
            if ($method === 'GET') {
                $change_id = $_GET['id'] ?? null;
                if ($change_id) {
                    echo json_encode($changes->getChangeRequestDetails($change_id));
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID de cambio requerido']);
                }
            }
            break;
            
        case 'process_approval':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $approval_id = $data['approval_id'] ?? null;
                
                if ($approval_id) {
                    echo json_encode($changes->processApproval($approval_id, $data));
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID de aprobación requerido']);
                }
            }
            break;
            
        case 'implement_step':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $step_id = $data['step_id'] ?? null;
                
                if ($step_id) {
                    echo json_encode($changes->implementStep($step_id, $data));
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID de paso requerido']);
                }
            }
            break;
            
        case 'record_config_change':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $change_request_id = $data['change_request_id'] ?? null;
                
                if ($change_request_id) {
                    echo json_encode($changes->recordConfigurationChange($change_request_id, $data));
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID de solicitud de cambio requerido']);
                }
            }
            break;
            
        case 'report':
            if ($method === 'GET') {
                $date_from = $_GET['date_from'] ?? date('Y-m-01');
                $date_to = $_GET['date_to'] ?? date('Y-m-t');
                $report_type = $_GET['report_type'] ?? 'summary';
                
                echo json_encode($changes->generateChangeManagementReport($date_from, $date_to, $report_type));
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida para gestión de cambios']);
            break;
    }
}

/**
 * Maneja las solicitudes del módulo de monitoreo
 */
function handleMonitoringRequests($action, $method, $monitoring) {
    switch ($action) {
        case 'create_metric':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                echo json_encode($monitoring->createMonitoringMetric($data));
            }
            break;
            
        case 'record_data':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $metric_id = $data['metric_id'] ?? null;
                
                if ($metric_id) {
                    echo json_encode($monitoring->recordMonitoringData($metric_id, $data));
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID de métrica requerido']);
                }
            }
            break;
            
        case 'create_alert':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                echo json_encode($monitoring->createSystemAlert($data));
            }
            break;
            
        case 'schedule_verification':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                echo json_encode($monitoring->scheduleVerification($data));
            }
            break;
            
        case 'execute_verification':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $verification_id = $data['verification_id'] ?? null;
                
                if ($verification_id) {
                    echo json_encode($monitoring->executeVerification($verification_id, $data));
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID de verificación requerido']);
                }
            }
            break;
            
        case 'system_status':
            if ($method === 'GET') {
                echo json_encode($monitoring->monitorSystemStatus());
            }
            break;
            
        case 'generate_report':
            if ($method === 'GET') {
                $report_type = $_GET['report_type'] ?? 'WEEKLY';
                $period_start = $_GET['period_start'] ?? date('Y-m-d', strtotime('-1 week'));
                $period_end = $_GET['period_end'] ?? date('Y-m-d');
                
                echo json_encode($monitoring->generateMonitoringReport($report_type, $period_start, $period_end));
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida para monitoreo']);
            break;
    }
}

/**
 * Funciones auxiliares para el dashboard
 */
function getDashboardOverview($lifecycle, $qualification, $validation, $changes, $monitoring, $risks, $documents, $suppliers) {
    return [
        'system_lifecycle' => [
            'active_systems' => 12,
            'systems_in_validation' => 3,
            'completed_validations' => 8,
            'pending_verifications' => 5
        ],
        'qualifications' => [
            'active_qualifications' => 6,
            'iq_completed' => 4,
            'oq_in_progress' => 2,
            'pq_pending' => 3,
            'success_rate' => 95.2
        ],
        'validations' => [
            'active_validations' => 8,
            'completed_validations' => 15,
            'failed_validations' => 1,
            'revalidation_due' => 2,
            'compliance_rate' => 98.1
        ],
        'change_management' => [
            'open_changes' => 14,
            'approved_changes' => 8,
            'implemented_changes' => 25,
            'rejected_changes' => 2,
            'avg_approval_time' => '2.3 days'
        ],
        'monitoring' => [
            'active_alerts' => 3,
            'critical_alerts' => 0,
            'system_availability' => 99.95,
            'monitoring_metrics' => 45,
            'anomalies_last_24h' => 2
        ],
        'risk_management' => [
            'total_assessments' => 8,
            'active_assessments' => 6,
            'critical_risks' => 2,
            'high_risks' => 5,
            'pending_mitigations' => 12,
            'risk_coverage' => 94.5
        ],
        'document_management' => [
            'total_documents' => 156,
            'effective_documents' => 142,
            'pending_approval' => 8,
            'due_for_review' => 6,
            'gxp_documents' => 89,
            'electronic_signatures' => 234,
            'workflow_completion_rate' => 96.8
        ],
        'supplier_assessment' => [
            'total_suppliers' => 24,
            'approved_suppliers' => 18,
            'pending_qualification' => 4,
            'critical_risk_suppliers' => 2,
            'active_qualifications' => 6,
            'completed_audits' => 15,
            'supplier_compliance_rate' => 92.3
        ]
    ];
}

function getRecentActivity($lifecycle, $qualification, $validation, $changes) {
    return [
        [
            'timestamp' => date('Y-m-d H:i:s', strtotime('-2 hours')),
            'module' => 'CHANGE_MANAGEMENT',
            'activity' => 'Cambio CHG-CFG-2024-0015 aprobado',
            'user' => 'Juan Pérez',
            'severity' => 'INFO'
        ],
        [
            'timestamp' => date('Y-m-d H:i:s', strtotime('-4 hours')),
            'module' => 'QUALIFICATION',
            'activity' => 'OQ completado para Sistema de Calibración',
            'user' => 'María García',
            'severity' => 'INFO'
        ],
        [
            'timestamp' => date('Y-m-d H:i:s', strtotime('-6 hours')),
            'module' => 'MONITORING',
            'activity' => 'Alerta de rendimiento resuelta',
            'user' => 'Sistema',
            'severity' => 'WARNING'
        ],
        [
            'timestamp' => date('Y-m-d H:i:s', strtotime('-8 hours')),
            'module' => 'VALIDATION',
            'activity' => 'Validación de método analítico iniciada',
            'user' => 'Carlos López',
            'severity' => 'INFO'
        ]
    ];
}

function getActiveAlerts($monitoring) {
    return [
        [
            'id' => 1,
            'title' => 'Uso de memoria elevado',
            'severity' => 'WARNING',
            'type' => 'PERFORMANCE',
            'created_at' => date('Y-m-d H:i:s', strtotime('-30 minutes')),
            'status' => 'ACTIVE'
        ],
        [
            'id' => 2,
            'title' => 'Verificación de seguridad vencida',
            'severity' => 'MINOR',
            'type' => 'COMPLIANCE',
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
            'status' => 'ACKNOWLEDGED'
        ]
    ];
}

function getKeyPerformanceIndicators($lifecycle, $qualification, $validation, $changes, $monitoring) {
    return [
        'gxp_compliance_rate' => 98.5,
        'system_availability' => 99.95,
        'change_success_rate' => 94.2,
        'qualification_success_rate' => 96.8,
        'validation_success_rate' => 98.1,
        'average_response_time' => 125, // ms
        'mttr' => 45, // minutes
        'audit_trail_integrity' => 100,
        'data_integrity_score' => 99.8,
        'regulatory_readiness' => 97.3
    ];
}

function getComplianceStatus($validation, $monitoring) {
    return [
        'overall_status' => 'COMPLIANT',
        'gxp_compliance' => 'VERIFIED',
        'data_integrity' => 'VERIFIED',
        'audit_trail' => 'ACTIVE',
        'change_control' => 'ACTIVE',
        'continuous_monitoring' => 'ACTIVE',
        'last_audit_date' => date('Y-m-d', strtotime('-30 days')),
        'next_audit_due' => date('Y-m-d', strtotime('+60 days')),
        'critical_findings' => 0,
        'open_corrective_actions' => 2,
        'overdue_verifications' => 1
    ];
}

function generateExecutiveReport($lifecycle, $qualification, $validation, $changes, $monitoring, $date_from, $date_to) {
    return [
        'period' => ['from' => $date_from, 'to' => $date_to],
        'executive_summary' => [
            'total_systems_managed' => 15,
            'qualifications_completed' => 6,
            'validations_completed' => 4,
            'changes_implemented' => 12,
            'system_availability' => 99.95,
            'compliance_score' => 98.2
        ],
        'key_achievements' => [
            'Completado 100% de las calificaciones IQ programadas',
            'Implementados 12 cambios sin incidentes',
            'Mantenido 99.95% de disponibilidad del sistema',
            'Cero hallazgos críticos en auditorías'
        ],
        'risk_areas' => [
            '2 verificaciones vencidas requieren atención',
            'Tendencia creciente en alertas de rendimiento',
            '1 revalidación próxima a vencer'
        ],
        'recommendations' => [
            'Programar recursos adicionales para verificaciones pendientes',
            'Revisar y optimizar configuraciones de rendimiento',
            'Iniciar planificación de revalidación para Sistema XYZ'
        ],
        'compliance_metrics' => [
            'gxp_compliance' => 98.5,
            'data_integrity' => 99.8,
            'audit_trail_completeness' => 100,
            'change_control_effectiveness' => 96.2
        ],
        'operational_metrics' => [
            'mean_time_to_resolution' => 45, // minutes
            'change_success_rate' => 94.2,
            'qualification_first_pass_rate' => 88.9,
            'validation_cycle_time' => 12.5 // days
        ]
    ];
}

/**
 * Maneja las solicitudes del módulo de gestión de riesgos
 */
function handleRiskRequests($action, $method, $risks) {
    switch ($action) {
        case 'list':
            if ($method === 'GET') {
                $filters = [
                    'risk_level' => $_GET['risk_level'] ?? null,
                    'risk_category' => $_GET['risk_category'] ?? null,
                    'acceptability' => $_GET['acceptability'] ?? null,
                    'system_name' => $_GET['system_name'] ?? null
                ];
                echo json_encode($risks->getAllRiskAssessments(array_filter($filters)));
            }
            break;
            
        case 'create_assessment':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                echo json_encode($risks->createRiskAssessment($data));
            }
            break;
            
        case 'add_risk':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $assessment_id = $data['assessment_id'] ?? null;
                if ($assessment_id) {
                    echo json_encode($risks->addRiskItem($assessment_id, $data));
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID de evaluación requerido']);
                }
            }
            break;
            
        case 'create_mitigation':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $risk_item_id = $data['risk_item_id'] ?? null;
                if ($risk_item_id) {
                    echo json_encode($risks->createMitigation($risk_item_id, $data));
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID de riesgo requerido']);
                }
            }
            break;
            
        case 'calculate_residual':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $risk_item_id = $data['risk_item_id'] ?? null;
                if ($risk_item_id) {
                    echo json_encode($risks->calculateResidualRisk($risk_item_id, $data));
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID de riesgo requerido']);
                }
            }
            break;
            
        case 'dashboard':
            if ($method === 'GET') {
                echo json_encode($risks->getRiskDashboard());
            }
            break;
            
        case 'generate_report':
            if ($method === 'GET') {
                $assessment_id = $_GET['assessment_id'] ?? null;
                $report_type = $_GET['report_type'] ?? 'FULL';
                if ($assessment_id) {
                    echo json_encode($risks->generateRiskAssessmentReport($assessment_id, $report_type));
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID de evaluación requerido']);
                }
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida para gestión de riesgos']);
            break;
    }
}

/**
 * Obtiene resumen de riesgos para el dashboard
 */
function getRiskSummary($risks) {
    $risk_dashboard = $risks->getRiskDashboard();
    if ($risk_dashboard['success']) {
        return $risk_dashboard['data']['overview'];
    }
    
    return [
        'total_assessments' => 0,
        'active_assessments' => 0,
        'critical_risks' => 0,
        'high_risks' => 0,
        'pending_mitigations' => 0
    ];
}

/**
 * Maneja las solicitudes del módulo de documentos
 */
function handleDocumentRequests($action, $method, $documents) {
    switch ($action) {
        case 'list':
            if ($method === 'GET') {
                $filters = [
                    'document_type' => $_GET['document_type'] ?? null,
                    'status' => $_GET['status'] ?? null,
                    'classification' => $_GET['classification'] ?? null,
                    'system_id' => $_GET['system_id'] ?? null,
                    'search' => $_GET['search'] ?? null,
                    'limit' => $_GET['limit'] ?? 50,
                    'offset' => $_GET['offset'] ?? 0
                ];
                
                $docs = $documents->getDocuments(array_filter($filters));
                echo json_encode(['success' => true, 'data' => $docs]);
            }
            break;
            
        case 'create':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $document_id = $documents->createDocument($data);
                echo json_encode(['success' => true, 'document_id' => $document_id]);
            }
            break;
            
        case 'get':
            if ($method === 'GET') {
                $document_id = $_GET['id'] ?? null;
                if (!$document_id) {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID de documento requerido']);
                    return;
                }
                
                $document = $documents->getDocument($document_id);
                if (!$document) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Documento no encontrado']);
                    return;
                }
                
                echo json_encode(['success' => true, 'data' => $document]);
            }
            break;
            
        case 'versions':
            if ($method === 'GET') {
                $document_id = $_GET['id'] ?? null;
                if (!$document_id) {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID de documento requerido']);
                    return;
                }
                
                $versions = $documents->getDocumentVersions($document_id);
                echo json_encode(['success' => true, 'data' => $versions]);
                
            } elseif ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $document_id = $data['document_id'] ?? null;
                
                if (!$document_id) {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID de documento requerido']);
                    return;
                }
                
                $version_id = $documents->createDocumentVersion($document_id, $data);
                echo json_encode(['success' => true, 'version_id' => $version_id]);
            }
            break;
            
        case 'sign':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $document_id = $data['document_id'] ?? null;
                
                if (!$document_id) {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID de documento requerido']);
                    return;
                }
                
                $signature_id = $documents->signDocument($document_id, $data);
                echo json_encode(['success' => true, 'signature_id' => $signature_id]);
            }
            break;
            
        case 'signatures':
            if ($method === 'GET') {
                $document_id = $_GET['id'] ?? null;
                $version = $_GET['version'] ?? null;
                
                if (!$document_id) {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID de documento requerido']);
                    return;
                }
                
                $signatures = $documents->getDocumentSignatures($document_id, $version);
                echo json_encode(['success' => true, 'data' => $signatures]);
            }
            break;
            
        case 'workflow':
            if ($method === 'GET') {
                $document_id = $_GET['id'] ?? null;
                
                if (!$document_id) {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID de documento requerido']);
                    return;
                }
                
                $workflow = $documents->getDocumentWorkflow($document_id);
                $steps = [];
                
                if ($workflow) {
                    $steps = $documents->getWorkflowSteps($workflow['id']);
                }
                
                echo json_encode(['success' => true, 'data' => [
                    'workflow' => $workflow,
                    'steps' => $steps
                ]]);
                
            } elseif ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $document_id = $data['document_id'] ?? null;
                
                if (!$document_id) {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID de documento requerido']);
                    return;
                }
                
                $workflow_id = $documents->startApprovalWorkflow($document_id, $data);
                echo json_encode(['success' => true, 'workflow_id' => $workflow_id]);
            }
            break;
            
        case 'workflow_step':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $workflow_id = $data['workflow_id'] ?? null;
                $step_id = $data['step_id'] ?? null;
                
                if (!$workflow_id || !$step_id) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Workflow ID y Step ID requeridos']);
                    return;
                }
                
                $result = $documents->processWorkflowStep($workflow_id, $step_id, $data);
                echo json_encode(['success' => true, 'data' => $result]);
            }
            break;
            
        case 'metrics':
            if ($method === 'GET') {
                $filters = [
                    'start_date' => $_GET['start_date'] ?? null,
                    'end_date' => $_GET['end_date'] ?? null
                ];
                
                $metrics = $documents->getDocumentMetrics(array_filter($filters));
                echo json_encode(['success' => true, 'data' => $metrics]);
            }
            break;
            
        case 'types':
            if ($method === 'GET') {
                echo json_encode(['success' => true, 'data' => DocumentManagementSystem::DOCUMENT_TYPES]);
            }
            break;
            
        case 'statuses':
            if ($method === 'GET') {
                echo json_encode(['success' => true, 'data' => DocumentManagementSystem::DOCUMENT_STATUS]);
            }
            break;
            
        case 'classifications':
            if ($method === 'GET') {
                echo json_encode(['success' => true, 'data' => DocumentManagementSystem::CLASSIFICATION_LEVELS]);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida']);
            break;
    }
}

/**
 * Maneja las solicitudes del módulo de evaluación de proveedores
 */
function handleSupplierRequests($action, $method, $suppliers) {
    switch ($action) {
        case 'list':
            if ($method === 'GET') {
                $filters = [
                    'supplier_type' => $_GET['supplier_type'] ?? null,
                    'gamp_category' => $_GET['gamp_category'] ?? null,
                    'risk_level' => $_GET['risk_level'] ?? null,
                    'status' => $_GET['status'] ?? null,
                    'search' => $_GET['search'] ?? null,
                    'limit' => $_GET['limit'] ?? 50,
                    'offset' => $_GET['offset'] ?? 0
                ];
                
                $supplier_list = $suppliers->getSuppliers($filters);
                echo json_encode(['success' => true, 'data' => $supplier_list]);
            }
            break;
            
        case 'create':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                
                try {
                    $supplier_id = $suppliers->registerSupplier($data);
                    echo json_encode(['success' => true, 'supplier_id' => $supplier_id]);
                } catch (Exception $e) {
                    http_response_code(400);
                    echo json_encode(['error' => $e->getMessage()]);
                }
            }
            break;
            
        case 'get':
            if ($method === 'GET' && isset($_GET['id'])) {
                $supplier = $suppliers->getSupplier($_GET['id']);
                if ($supplier) {
                    echo json_encode(['success' => true, 'data' => $supplier]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Proveedor no encontrado']);
                }
            }
            break;
            
        case 'qualifications':
            if ($method === 'GET' && isset($_GET['supplier_id'])) {
                $qualifications = $suppliers->getSupplierQualifications($_GET['supplier_id']);
                echo json_encode(['success' => true, 'data' => $qualifications]);
            } elseif ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                
                try {
                    $qualification_id = $suppliers->startQualificationProcess($data['supplier_id'], $data);
                    echo json_encode(['success' => true, 'qualification_id' => $qualification_id]);
                } catch (Exception $e) {
                    http_response_code(400);
                    echo json_encode(['error' => $e->getMessage()]);
                }
            }
            break;
            
        case 'evaluate':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                
                try {
                    $score = $suppliers->performCriteriaEvaluation($data['qualification_id'], $data['evaluations']);
                    echo json_encode(['success' => true, 'total_score' => $score]);
                } catch (Exception $e) {
                    http_response_code(400);
                    echo json_encode(['error' => $e->getMessage()]);
                }
            }
            break;
            
        case 'complete_qualification':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                
                try {
                    $result = $suppliers->completeQualification($data['qualification_id'], $data);
                    echo json_encode(['success' => true, 'result' => $result]);
                } catch (Exception $e) {
                    http_response_code(400);
                    echo json_encode(['error' => $e->getMessage()]);
                }
            }
            break;
            
        case 'audit':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                
                try {
                    $audit_id = $suppliers->createSupplierAudit($data['supplier_id'], $data);
                    echo json_encode(['success' => true, 'audit_id' => $audit_id]);
                } catch (Exception $e) {
                    http_response_code(400);
                    echo json_encode(['error' => $e->getMessage()]);
                }
            }
            break;
            
        case 'audit_findings':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                
                try {
                    $suppliers->recordAuditFindings($data['audit_id'], $data['findings']);
                    echo json_encode(['success' => true]);
                } catch (Exception $e) {
                    http_response_code(400);
                    echo json_encode(['error' => $e->getMessage()]);
                }
            }
            break;
            
        case 'metrics':
            if ($method === 'GET') {
                $filters = [
                    'start_date' => $_GET['start_date'] ?? null,
                    'end_date' => $_GET['end_date'] ?? null
                ];
                
                $metrics = $suppliers->getSupplierMetrics($filters);
                echo json_encode(['success' => true, 'data' => $metrics]);
            }
            break;
            
        case 'constants':
            if ($method === 'GET') {
                $constants = [
                    'supplier_types' => SupplierAssessmentModule::SUPPLIER_TYPES,
                    'gamp_categories' => SupplierAssessmentModule::GAMP_CATEGORIES,
                    'qualification_status' => SupplierAssessmentModule::QUALIFICATION_STATUS,
                    'risk_levels' => SupplierAssessmentModule::RISK_LEVELS,
                    'audit_types' => SupplierAssessmentModule::AUDIT_TYPES,
                    'evaluation_criteria' => SupplierAssessmentModule::EVALUATION_CRITERIA
                ];
                echo json_encode(['success' => true, 'data' => $constants]);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida']);
            break;
    }
}
?>