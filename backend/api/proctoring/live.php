<?php
/**
 * GET /api/proctoring/live — Live Proctoring Monitor
 * Returns active live examination candidate sessions, webcam status, and risk ratings.
 * Accessible to Admin and Examiner roles.
 */
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../middleware/Auth.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($method !== 'GET') {
    Response::error('Method not allowed', 405);
}

// Authentication check: Allow Python proctoring service API key or logged in Admin/Examiner
$apiKey = $_SERVER['HTTP_X_PROCTORING_KEY'] ?? '';
$isProctoringService = ($apiKey && defined('PROCTORING_API_KEY') && $apiKey === PROCTORING_API_KEY && PROCTORING_API_KEY !== '');

if (!$isProctoringService) {
    $authUser = Auth::authenticate();
    if (!in_array($authUser['role'], ['admin', 'examiner'])) {
        Response::error('Forbidden: Access limited to administrators and examiners', 403);
    }
}

function computeRiskLevel(int $score): string {
    if ($score >= 80) return 'critical';
    if ($score >= 60) return 'high';
    if ($score >= 30) return 'medium';
    return 'normal';
}

try {
    // 1. Fetch active proctoring sessions
    $activeSessions = db()->fetchAll(
        "SELECT ps.id AS session_id, ps.id, ps.student_id, ps.exam_id,
                ps.camera_status, ps.face_verified, ps.risk_score,
                ps.total_events, ps.high_severity_events, ps.medium_severity_events, ps.low_severity_events,
                ps.status, ps.started_at,
                CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                s.student_id_number,
                e.title AS exam_title,
                ea.id AS attempt_id,
                (SELECT COUNT(sa.question_id) FROM student_answers sa WHERE sa.attempt_id = ea.id) AS answered_count,
                e.total_questions
         FROM proctoring_sessions ps
         JOIN students s ON s.user_id = ps.student_id
         JOIN exams e ON e.id = ps.exam_id
         LEFT JOIN exam_attempts ea ON ea.id = ps.attempt_id
         WHERE ps.status = 'active'
         ORDER BY ps.risk_score DESC, ps.started_at DESC"
    );

    foreach ($activeSessions as &$session) {
        $risk = (int)($session['risk_score'] ?? 0);
        $session['risk_level'] = computeRiskLevel($risk);
        $session['camera_status'] = $session['camera_status'] ?? 'active';
    }
    unset($session);

    // 2. Fetch recent completed sessions (for admin review context)
    $recentSessions = db()->fetchAll(
        "SELECT ps.id AS session_id, ps.id, ps.student_id, ps.exam_id,
                ps.camera_status, ps.face_verified, ps.risk_score,
                ps.total_events, ps.high_severity_events, ps.status,
                ps.started_at, ps.ended_at,
                CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                s.student_id_number,
                e.title AS exam_title,
                ea.id AS attempt_id
         FROM proctoring_sessions ps
         JOIN students s ON s.user_id = ps.student_id
         JOIN exams e ON e.id = ps.exam_id
         LEFT JOIN exam_attempts ea ON ea.id = ps.attempt_id
         WHERE ps.status != 'active'
         ORDER BY ps.ended_at DESC, ps.id DESC
         LIMIT 10"
    );

    foreach ($recentSessions as &$rs) {
        $rs['risk_level'] = computeRiskLevel((int)($rs['risk_score'] ?? 0));
    }
    unset($rs);

    Response::success([
        'active_sessions'  => $activeSessions,
        'active_students'  => $activeSessions,
        'recent_sessions'  => $recentSessions,
        'count'            => count($activeSessions),
        'server_time'      => date('Y-m-d H:i:s')
    ], 'Active proctoring sessions retrieved');

} catch (Throwable $e) {
    Logger::error('Error fetching live proctoring: ' . $e->getMessage());
    Response::error('Failed to query proctoring database: ' . $e->getMessage(), 500);
}
