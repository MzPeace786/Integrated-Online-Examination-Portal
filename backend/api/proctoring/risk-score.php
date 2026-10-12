<?php
/**
 * GET /api/proctoring/risk-score?attempt_id=X
 * Returns AI risk metrics, event breakdown, and risk severity rating for an exam attempt.
 */
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($method !== 'GET') {
    Response::error('Method not allowed', 405);
}

Auth::authenticate();

$attemptId = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;
if (!$attemptId) {
    Response::error('attempt_id is required', 400);
}

$session = db()->fetchOne(
    "SELECT ps.id, ps.risk_score, ps.total_events, ps.high_severity_events,
            ps.medium_severity_events, ps.low_severity_events, ps.status
     FROM proctoring_sessions ps
     WHERE ps.attempt_id = ?",
    [$attemptId]
);

if (!$session) {
    Response::error('No proctoring session found for this attempt', 404);
}

$score = (int)$session['risk_score'];
$riskLevel = $score >= 80 ? 'critical' : ($score >= 60 ? 'high' : ($score >= 30 ? 'medium' : 'normal'));

Response::success([
    'risk_score'            => $score,
    'risk_level'            => $riskLevel,
    'total_events'          => (int)$session['total_events'],
    'high_severity_events'  => (int)$session['high_severity_events'],
    'medium_severity_events'=> (int)$session['medium_severity_events'],
    'low_severity_events'   => (int)$session['low_severity_events'],
    'session_status'        => $session['status']
]);
