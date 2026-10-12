<?php
/**
 * GET /api/examiner/dashboard
 */
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';

$user = Auth::authorize(['examiner', 'admin']);
$userId = (int)$user['user_id'];

$totalExams = db()->fetchOne("SELECT COUNT(*) AS cnt FROM exams WHERE examiner_id = ?", [$userId]);
$activeExams = db()->fetchOne("SELECT COUNT(*) AS cnt FROM exams WHERE examiner_id = ? AND status = 'active'", [$userId]);
$totalQuestions = db()->fetchOne("SELECT COUNT(*) AS cnt FROM questions WHERE created_by = ? AND is_deleted = 0", [$userId]);
$totalAttempts = db()->fetchOne(
    "SELECT COUNT(ea.id) AS cnt FROM exam_attempts ea
     JOIN exams e ON e.id = ea.exam_id
     WHERE e.examiner_id = ? AND ea.status = 'submitted'",
    [$userId]
);

$recentExams = db()->fetchAll(
    "SELECT e.*, s.name AS subject_name,
            (SELECT COUNT(*) FROM exam_enrollments WHERE exam_id = e.id) AS enrolled_students,
            (SELECT COUNT(*) FROM exam_attempts WHERE exam_id = e.id AND status = 'submitted') AS completed_attempts
     FROM exams e
     LEFT JOIN subjects s ON s.id = e.subject_id
     WHERE e.examiner_id = ?
     ORDER BY e.created_at DESC
     LIMIT 5",
    [$userId]
);

// High risk proctoring alerts
$suspiciousAlerts = db()->fetchAll(
    "SELECT pe.*, CONCAT(s.first_name, ' ', s.last_name) AS student_name, e.title AS exam_title
     FROM proctoring_events pe
     JOIN exams e ON e.id = pe.exam_id
     JOIN students s ON s.user_id = pe.student_id
     WHERE e.examiner_id = ? AND pe.severity IN ('high', 'critical')
     ORDER BY pe.timestamp DESC
     LIMIT 6",
    [$userId]
);

Response::success([
    'stats' => [
        'total_exams' => (int)($totalExams['cnt'] ?? 0),
        'active_exams' => (int)($activeExams['cnt'] ?? 0),
        'total_questions' => (int)($totalQuestions['cnt'] ?? 0),
        'total_attempts' => (int)($totalAttempts['cnt'] ?? 0),
    ],
    'recent_exams' => $recentExams,
    'suspicious_alerts' => $suspiciousAlerts,
]);
