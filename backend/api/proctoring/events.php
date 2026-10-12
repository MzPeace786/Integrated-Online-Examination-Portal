<?php
/**
 * POST /api/proctoring/events — Log a Proctoring Violation / Telemetry Event
 * Used by candidate browser proctoring client and Python OpenCV service.
 */
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/InputValidator.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($method !== 'POST') {
    Response::error('Method not allowed', 405);
}

// Authentication check: Allow Python proctoring service API key or logged in user
$apiKey = $_SERVER['HTTP_X_PROCTORING_KEY'] ?? '';
$isProctoringService = ($apiKey && defined('PROCTORING_API_KEY') && $apiKey === PROCTORING_API_KEY && PROCTORING_API_KEY !== '');

$authUser = null;
if (!$isProctoringService) {
    $authUser = Auth::authenticate();
}

$body      = InputValidator::getRequestBody();
$sid       = (int)($body['session_id'] ?? 0);
$studentId = (int)($body['student_id'] ?? ($authUser ? $authUser['user_id'] : 0));
$examId    = (int)($body['exam_id'] ?? 0);
$eventType = InputValidator::sanitizeString($body['event_type'] ?? '', 50);
$desc      = InputValidator::sanitizeString($body['description'] ?? '', 500);

$allowedTypes = [
    'exam_started', 'exam_submitted', 'exam_paused',
    'face_missing', 'face_detected', 'multiple_faces', 'looking_away',
    'camera_unavailable', 'camera_active', 'identity_mismatch',
    'tab_switch', 'fullscreen_exit', 'window_blur', 'copy_attempt', 'right_click_attempt'
];

if (!$sid || !in_array($eventType, $allowedTypes)) {
    Response::error('Invalid session_id or event_type', 422);
}

$severityMap = [
    'exam_started'        => 'info',
    'exam_submitted'      => 'info',
    'exam_paused'         => 'info',
    'face_detected'       => 'info',
    'camera_active'       => 'info',
    'face_missing'        => 'medium',
    'multiple_faces'      => 'high',
    'looking_away'        => 'low',
    'camera_unavailable'  => 'high',
    'identity_mismatch'   => 'high',
    'tab_switch'          => 'medium',
    'fullscreen_exit'     => 'medium',
    'window_blur'         => 'low',
    'copy_attempt'        => 'low',
    'right_click_attempt' => 'low',
];
$severity = $severityMap[$eventType] ?? 'low';

$session = db()->fetchOne("SELECT student_id, exam_id FROM proctoring_sessions WHERE id = ?", [$sid]);
if ($session) {
    $studentId = (int)$session['student_id'];
    $examId    = (int)$session['exam_id'];
}

// Insert event
$eventId = db()->insert(
    "INSERT INTO proctoring_events (session_id, student_id, exam_id, event_type, severity, description, timestamp)
     VALUES (?, ?, ?, ?, ?, ?, NOW())",
    [$sid, $studentId, $examId, $eventType, $severity, $desc]
);

// Calculate risk score
$weights = [
    'face_missing'       => 10,
    'multiple_faces'     => 25,
    'looking_away'       => 5,
    'camera_unavailable' => 20,
    'identity_mismatch'  => 30,
    'tab_switch'         => 8,
    'fullscreen_exit'    => 10,
    'window_blur'        => 3,
    'copy_attempt'       => 5,
    'right_click_attempt'=> 2,
];

$events = db()->fetchAll(
    "SELECT event_type, COUNT(*) AS cnt FROM proctoring_events
     WHERE session_id = ? AND event_type NOT IN ('exam_started','exam_submitted','face_detected','camera_active','exam_paused')
     GROUP BY event_type",
    [$sid]
);

$score = 0;
foreach ($events as $e) {
    $weight = $weights[$e['event_type']] ?? 5;
    $score += $weight * min((int)$e['cnt'], 5);
}
$riskScore = min(100, $score);

// Update session counters
db()->execute(
    "UPDATE proctoring_sessions SET
         total_events = total_events + 1,
         high_severity_events   = high_severity_events   + IF(? = 'high', 1, 0),
         medium_severity_events = medium_severity_events + IF(? = 'medium', 1, 0),
         low_severity_events    = low_severity_events    + IF(? = 'low', 1, 0),
         risk_score = ?
     WHERE id = ?",
    [$severity, $severity, $severity, $riskScore, $sid]
);

Response::success([
    'event_id'   => $eventId,
    'risk_score' => $riskScore
], 'Proctoring event logged successfully');
