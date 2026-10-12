<?php
/**
 * POST /api/proctoring/session      — Create proctoring session
 * PUT  /api/proctoring/session/{id}/end — End session
 * GET  /api/proctoring/session/{id}/report — Get report
 * POST /api/proctoring/events       — Log an event (from browser or Python service)
 * GET  /api/proctoring/live         — Live dashboard data
 * GET  /api/proctoring/risk-score   — Risk score for attempt
 */
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/InputValidator.php';

$method = $_SERVER['REQUEST_METHOD'];

global $_ROUTE;
$routeAction = $_ROUTE['action'] ?? null;
$sessionId   = isset($_ROUTE['id']) ? (int)$_ROUTE['id'] : null;

// The proctoring service uses an API key, not a session token.
// Browser clients use the normal Bearer token.
$isProctoringService = false;
$apiKey = $_SERVER['HTTP_X_PROCTORING_KEY'] ?? '';
if ($apiKey && $apiKey === PROCTORING_API_KEY && PROCTORING_API_KEY !== '') {
    $isProctoringService = true;
}

// Authenticate: either normal user or proctoring service
$authUser = null;
if (!$isProctoringService) {
    $authUser = Auth::authenticate();
}

// ---------------------------------------------------------------
// GET /proctoring/live — Live dashboard (admin/examiner only)
// ---------------------------------------------------------------
if ($method === 'GET' && !$sessionId && ($routeAction === null || $routeAction === 'live')) {
    if (!$authUser || !in_array($authUser['role'], ['admin', 'examiner'])) {
        Response::error('Forbidden', 403);
    }

    $liveSessions = db()->fetchAll(
        "SELECT ps.id AS session_id, ps.student_id, ps.exam_id,
                ps.camera_status, ps.face_verified, ps.risk_score,
                ps.total_events, ps.high_severity_events, ps.status,
                ps.started_at,
                CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                s.student_id_number,
                e.title AS exam_title,
                ea.id AS attempt_id,
                (SELECT COUNT(sa.question_id) FROM student_answers sa WHERE sa.attempt_id = ea.id) AS answered_count,
                e.total_questions
         FROM proctoring_sessions ps
         JOIN students s ON s.user_id = ps.student_id
         JOIN exams e ON e.id = ps.exam_id
         JOIN exam_attempts ea ON ea.id = ps.attempt_id
         WHERE ps.status = 'active'
         ORDER BY ps.risk_score DESC",
        []
    );

    // Add risk level label
    foreach ($liveSessions as &$session) {
        $session['risk_level'] = getRiskLevel((int)$session['risk_score']);
    }
    unset($session);

    Response::success(['active_students' => $liveSessions, 'count' => count($liveSessions)]);
}

// ---------------------------------------------------------------
// GET /proctoring/risk-score?attempt_id=X
// ---------------------------------------------------------------
if ($method === 'GET' && isset($_GET['attempt_id'])) {
    $attemptId = (int)$_GET['attempt_id'];

    $session = db()->fetchOne(
        "SELECT ps.id, ps.risk_score, ps.total_events, ps.high_severity_events,
                ps.medium_severity_events, ps.low_severity_events
         FROM proctoring_sessions ps
         WHERE ps.attempt_id = ?",
        [$attemptId]
    );

    if (!$session) {
        Response::error('No proctoring session found for this attempt', 404);
    }

    Response::success([
        'risk_score'            => (int)$session['risk_score'],
        'risk_level'            => getRiskLevel((int)$session['risk_score']),
        'total_events'          => (int)$session['total_events'],
        'high_severity_events'  => (int)$session['high_severity_events'],
        'medium_severity_events'=> (int)$session['medium_severity_events'],
        'low_severity_events'   => (int)$session['low_severity_events'],
    ]);
}

// ---------------------------------------------------------------
// GET /proctoring/session/{id}/report
// ---------------------------------------------------------------
if ($method === 'GET' && $sessionId && $routeAction === 'report') {
    $session = db()->fetchOne(
        "SELECT ps.*, CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                s.student_id_number, e.title AS exam_title
         FROM proctoring_sessions ps
         JOIN students s ON s.user_id = ps.student_id
         JOIN exams e ON e.id = ps.exam_id
         WHERE ps.id = ?",
        [$sessionId]
    );
    if (!$session) Response::error('Session not found', 404);

    $events = db()->fetchAll(
        "SELECT id, event_type, severity, description, timestamp, review_status, snapshot_path
         FROM proctoring_events
         WHERE session_id = ?
         ORDER BY timestamp ASC",
        [$sessionId]
    );

    $session['risk_level'] = getRiskLevel((int)$session['risk_score']);

    Response::success([
        'session' => $session,
        'events'  => $events,
        'timeline'=> formatTimeline($events),
    ]);
}

// ---------------------------------------------------------------
// POST /proctoring/session — Create session
// ---------------------------------------------------------------
if ($method === 'POST' && !$sessionId) {
    $body      = InputValidator::getRequestBody();
    $attemptId = (int)($body['attempt_id'] ?? 0);
    $studentId = (int)($body['student_id'] ?? ($authUser ? $authUser['user_id'] : 0));
    $examId    = (int)($body['exam_id'] ?? 0);

    if (!$attemptId || !$studentId || !$examId) {
        Response::error('attempt_id, student_id and exam_id required', 422);
    }

    // Check no duplicate
    $existing = db()->fetchOne(
        "SELECT id FROM proctoring_sessions WHERE attempt_id = ?",
        [$attemptId]
    );
    if ($existing) {
        Response::success(['session_id' => (int)$existing['id']], 'Session already exists');
    }

    $sid = db()->insert(
        "INSERT INTO proctoring_sessions (attempt_id, student_id, exam_id, started_at, status)
         VALUES (?, ?, ?, NOW(), 'active')",
        [$attemptId, $studentId, $examId]
    );

    // Log exam_started event
    logProctoringEvent($sid, $studentId, $examId, 'exam_started', 'info', 'Examination session started');

    Response::created(['session_id' => $sid], 'Proctoring session created');
}

// ---------------------------------------------------------------
// PUT /proctoring/session/{id}/end
// ---------------------------------------------------------------
if ($method === 'PUT' && $sessionId && $routeAction === 'end') {
    $session = db()->fetchOne("SELECT * FROM proctoring_sessions WHERE id = ?", [$sessionId]);
    if (!$session) Response::error('Session not found', 404);

    // Recalculate risk score from events
    $riskScore = calculateRiskScore($sessionId);

    db()->execute(
        "UPDATE proctoring_sessions SET status = 'ended', ended_at = NOW(), risk_score = ? WHERE id = ?",
        [$riskScore, $sessionId]
    );

    logProctoringEvent($sessionId, (int)$session['student_id'], (int)$session['exam_id'],
        'exam_submitted', 'info', 'Examination session ended');

    Response::success(['risk_score' => $riskScore, 'risk_level' => getRiskLevel($riskScore)]);
}

// ---------------------------------------------------------------
// POST /proctoring/events — Log an event
// ---------------------------------------------------------------
if ($method === 'POST' && !$sessionId) {
    $body      = InputValidator::getRequestBody();
    $sid       = (int)($body['session_id'] ?? 0);
    $studentId = (int)($body['student_id'] ?? ($authUser ? $authUser['user_id'] : 0));
    $examId    = (int)($body['exam_id'] ?? 0);
    $eventType = InputValidator::sanitizeString($body['event_type'] ?? '', 50);
    $desc      = InputValidator::sanitizeString($body['description'] ?? '', 500);

    $allowedTypes = ['face_missing','face_detected','multiple_faces','looking_away',
        'camera_unavailable','camera_active','identity_mismatch','tab_switch',
        'fullscreen_exit','window_blur','copy_attempt','right_click_attempt',
        'exam_started','exam_submitted'];

    if (!$sid || !in_array($eventType, $allowedTypes)) {
        Response::error('Invalid session_id or event_type', 422);
    }

    $severityMap = [
        'exam_started' => 'info', 'exam_submitted' => 'info',
        'face_missing' => 'medium', 'multiple_faces' => 'high',
        'looking_away' => 'low', 'camera_unavailable' => 'high',
        'identity_mismatch' => 'high', 'tab_switch' => 'medium',
        'fullscreen_exit' => 'medium', 'window_blur' => 'low',
        'copy_attempt' => 'low', 'right_click_attempt' => 'low',
        'face_detected' => 'info', 'camera_active' => 'info',
    ];
    $severity = $severityMap[$eventType] ?? 'low';

    $session = db()->fetchOne("SELECT student_id, exam_id FROM proctoring_sessions WHERE id = ?", [$sid]);
    if ($session) {
        $studentId = (int)$session['student_id'];
        $examId    = (int)$session['exam_id'];
    }

    $eventId = logProctoringEvent($sid, $studentId, $examId, $eventType, $severity, $desc);

    // Update session counters and risk score
    $riskScore = calculateRiskScore($sid);
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

    Response::success(['event_id' => $eventId, 'risk_score' => $riskScore]);
}

// ---------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------
function logProctoringEvent(int $sessionId, int $studentId, int $examId, string $type, string $severity, string $desc): int {
    return db()->insert(
        "INSERT INTO proctoring_events (session_id, student_id, exam_id, event_type, severity, description, timestamp)
         VALUES (?, ?, ?, ?, ?, ?, NOW())",
        [$sessionId, $studentId, $examId, $type, $severity, $desc]
    );
}

function calculateRiskScore(int $sessionId): int {
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
         WHERE session_id = ? AND event_type NOT IN ('exam_started','exam_submitted','face_detected','camera_active')
         GROUP BY event_type",
        [$sessionId]
    );

    $score = 0;
    foreach ($events as $e) {
        $weight = $weights[$e['event_type']] ?? 5;
        // Diminishing returns for repeated events
        $score += $weight * min((int)$e['cnt'], 5);
    }

    return min(100, $score);
}

function getRiskLevel(int $score): string {
    if ($score >= 80) return 'critical';
    if ($score >= 60) return 'high';
    if ($score >= 30) return 'medium';
    return 'low';
}

function formatTimeline(array $events): array {
    return array_map(fn($e) => [
        'time'       => date('H:i:s', strtotime($e['timestamp'])),
        'event_type' => $e['event_type'],
        'severity'   => $e['severity'],
        'description'=> $e['description'],
    ], $events);
}
