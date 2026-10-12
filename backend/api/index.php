<?php
// Security headers
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Logger.php';

// CORS — adjust ALLOWED_ORIGINS for production
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = ['http://localhost', 'http://127.0.0.1', 'http://localhost:80', 'http://localhost:8080'];
if (in_array($origin, $allowedOrigins, true) || APP_ENV === 'development') {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Auth-Token, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

set_exception_handler(function(Throwable $e) {
    Logger::error('Unhandled exception: ' . $e->getMessage(), [
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);
    $message = APP_DEBUG ? $e->getMessage() : 'Internal server error';
    Response::error($message, 500);
});

// Parse request path
$uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri  = preg_replace('|^.*/backend/api|', '', $uri);
$parts = array_values(array_filter(explode('/', trim($uri, '/'))));

if (empty($parts)) {
    Response::success(['name' => APP_NAME . ' API', 'version' => '1.0'], 'API is running');
}

// Route map: [segment => file or [segment => file]]
// Dynamic segments (IDs, UUIDs) are caught by the resolve function.
$routes = [
    'auth'       => [
        'register'          => 'auth/register.php',
        'login'             => 'auth/login.php',
        'logout'            => 'auth/logout.php',
        'forgot-password'   => 'auth/forgot-password.php',
        'reset-password'    => 'auth/reset-password.php',
        'verify-email'      => 'auth/verify-email.php',
        'me'                => 'auth/me.php',
    ],
    'student'    => [
        'dashboard'         => 'student/dashboard.php',
        'profile'           => 'student/profile.php',
        'exams'             => 'student/exams.php',
        'attempts'          => 'student/attempts.php',
        'results'           => 'student/results.php',
        'certificates'      => 'student/certificates.php',
        'analytics'         => 'student/analytics.php',
        'notifications'     => 'student/notifications.php',
        'consent'           => 'student/consent.php',
        'privacy'           => 'student/privacy.php',
    ],
    'examiner'   => [
        'dashboard'         => 'examiner/dashboard.php',
        'exams'             => 'examiner/exams.php',
        'question-banks'    => 'examiner/question-banks.php',
        'questions'         => 'examiner/questions.php',
        'results'           => 'examiner/results.php',
        'analytics'         => 'examiner/analytics.php',
        'proctoring-reports'=> 'examiner/proctoring-reports.php',
    ],
    'admin'      => [
        'dashboard'         => 'admin/dashboard.php',
        'users'             => 'admin/users.php',
        'subjects'          => 'admin/subjects.php',
        'courses'           => 'admin/courses.php',
        'exams'             => 'admin/exams.php',
        'results'           => 'admin/results.php',
        'certificates'      => 'admin/certificates.php',
        'audit-logs'        => 'admin/audit-logs.php',
        'security-logs'     => 'admin/security-logs.php',
        'settings'          => 'admin/settings.php',
        'retention'         => 'admin/retention.php',
        'deletion-requests' => 'admin/deletion-requests.php',
    ],
    'proctoring' => [
        'session'           => 'proctoring/session.php',
        'events'            => 'proctoring/events.php',
        'live'              => 'proctoring/live.php',
        'risk-score'        => 'proctoring/risk-score.php',
        'frame'             => 'proctoring/frame.php',
    ],
    'certificate'=> [
        'verify'            => 'certificate/verify.php',
    ],
    'code'       => [
        'execute'           => 'code-execution/execute.php',
    ],
];

// Resolve route with dynamic path segments
// Store dynamic IDs in $_ROUTE global for handlers to use
$_ROUTE = [];
function resolveFile(array $parts, array $routes, string $dir): ?string {
    global $_ROUTE;
    if (empty($parts)) return null;

    $section = $parts[0];
    if (!isset($routes[$section])) return null;

    $sub = $routes[$section];
    if (is_string($sub)) return $dir . '/' . $sub; // direct file

    if (!isset($parts[1])) return null;
    $endpoint = $parts[1];

    // Store IDs from further segments
    if (isset($parts[2]) && is_numeric($parts[2])) {
        $_ROUTE['id'] = (int)$parts[2];
        if (isset($parts[3])) $_ROUTE['action'] = $parts[3];
        if (isset($parts[4]) && is_numeric($parts[4])) $_ROUTE['sub_id'] = (int)$parts[4];
        if (isset($parts[5])) $_ROUTE['sub_action'] = $parts[5];
    }

    if (isset($sub[$endpoint])) {
        return $dir . '/' . $sub[$endpoint];
    }

    // Try if endpoint is actually an ID (e.g., /student/results/42)
    if (is_numeric($endpoint)) {
        $_ROUTE['id'] = (int)$endpoint;
        // Use the resource-level handler
        $resource = array_key_first($sub);
        if (isset($parts[2])) $_ROUTE['action'] = $parts[2];
        // Default to first matching handler
        foreach ($sub as $key => $file) {
            return $dir . '/' . $file; // Will use $_ROUTE['id'] to differentiate
        }
    }

    return null;
}

$apiDir = __DIR__;
$file = resolveFile($parts, $routes, $apiDir);

if (!$file || !file_exists($file)) {
    Response::error('API endpoint not found: ' . implode('/', $parts), 404);
}

require $file;
