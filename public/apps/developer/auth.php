<?php
/**
 * Developer Portal Authentication Guard
 * SBL Sistema Interno - Developer Section
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include core authentication
require_once __DIR__ . '/../../../app/Core/auth.php';
require_once __DIR__ . '/../../../app/Core/permissions.php';

/**
 * Check if user has developer access
 */
function checkDeveloperAccess() {
    // Check if user is logged in
    if (!isset($_SESSION['usuario'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Authentication required',
            'redirect' => '/login'
        ]);
        exit;
    }
    
    // Check if user has developer role
    $userRole = $_SESSION['rol'] ?? '';
    $allowedRoles = ['superadmin', 'developer', 'admin'];
    
    if (!in_array($userRole, $allowedRoles)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Insufficient privileges. Developer access required.',
            'code' => 'DEVELOPER_ACCESS_DENIED'
        ]);
        exit;
    }
    
    return true;
}

/**
 * Get current developer user info
 */
function getDeveloperUser() {
    return [
        'id' => $_SESSION['usuario_id'] ?? null,
        'username' => $_SESSION['usuario'] ?? null,
        'name' => $_SESSION['nombre_completo'] ?? 'Developer',
        'role' => $_SESSION['rol'] ?? 'developer',
        'empresa_id' => $_SESSION['empresa_id'] ?? null,
        'last_activity' => $_SESSION['last_activity'] ?? time()
    ];
}

/**
 * Log developer activity
 */
function logDeveloperActivity($action, $details = []) {
    $user = getDeveloperUser();
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'user_id' => $user['id'],
        'username' => $user['username'],
        'action' => $action,
        'details' => $details,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    // Log to developer activity file
    $logFile = __DIR__ . '/../../../storage/logs/developer_activity.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Check API rate limiting for developer endpoints
 */
function checkRateLimit($endpoint = '') {
    $user = getDeveloperUser();
    $key = 'dev_rate_limit_' . $user['id'] . '_' . md5($endpoint);
    
    // Simple file-based rate limiting (in production, use Redis or similar)
    $rateLimitFile = __DIR__ . '/../../../storage/cache/rate_limits.json';
    $rateLimitDir = dirname($rateLimitFile);
    
    if (!is_dir($rateLimitDir)) {
        mkdir($rateLimitDir, 0755, true);
    }
    
    $rateLimits = [];
    if (file_exists($rateLimitFile)) {
        $rateLimits = json_decode(file_get_contents($rateLimitFile), true) ?: [];
    }
    
    $now = time();
    $windowSize = 300; // 5 minutes
    $maxRequests = 100; // 100 requests per 5 minutes
    
    // Clean old entries
    foreach ($rateLimits as $k => $data) {
        if ($now - $data['first_request'] > $windowSize) {
            unset($rateLimits[$k]);
        }
    }
    
    // Check current user's rate limit
    if (!isset($rateLimits[$key])) {
        $rateLimits[$key] = [
            'count' => 1,
            'first_request' => $now,
            'last_request' => $now
        ];
    } else {
        $rateLimits[$key]['count']++;
        $rateLimits[$key]['last_request'] = $now;
        
        if ($rateLimits[$key]['count'] > $maxRequests) {
            http_response_code(429);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Rate limit exceeded. Please try again later.',
                'code' => 'RATE_LIMIT_EXCEEDED',
                'retry_after' => $windowSize - ($now - $rateLimits[$key]['first_request'])
            ]);
            exit;
        }
    }
    
    // Save rate limits
    file_put_contents($rateLimitFile, json_encode($rateLimits), LOCK_EX);
    
    return true;
}

/**
 * Validate developer API request
 */
function validateDeveloperRequest() {
    checkDeveloperAccess();
    checkRateLimit($_SERVER['REQUEST_URI'] ?? '');
    
    // Log the access
    logDeveloperActivity('api_access', [
        'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET'
    ]);
    
    return true;
}

// Auto-validate if this file is included directly
if (basename($_SERVER['SCRIPT_NAME']) !== 'auth.php') {
    validateDeveloperRequest();
}
?>