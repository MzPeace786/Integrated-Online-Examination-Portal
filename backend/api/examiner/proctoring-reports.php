<?php
/**
 * GET /api/examiner/proctoring-reports
 */
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/InputValidator.php';

$user = Auth::authorize(['examiner', 'admin']);
$userId = (int)$user['user_id'];
$isAdmin = ($user['role'] === 'admin');

$examId = InputValidator::validateInt($_GET['exam_id'] ?? null);
$attemptId = InputValidator::validateInt($_GET['attempt_id'] ?? null);

$where = $isAdmin ? ['1=1'] : ['e.examiner_id = ?'];
$params = $isAdmin ? [] : [$userId];

if ($examId) {
    $where[] = 'e.id = ?';
    $params[] = $examId;
}
if ($attemptId) {
    $where[] = 'ps.attempt_id = ?';
    $params[] = $attemptId;
}

$reports = db()->fetchAll(
    "SELECT ps.*, e.title AS exam_title,
            CONCAT(s.first_name, ' ', s.last_name) AS student_name,
            s.student_id_number,
            r.percentage, r.is_passed
     FROM proctoring_sessions ps
     JOIN exams e ON e.id = ps.exam_id
     JOIN students s ON s.user_id = ps.student_id
     LEFT JOIN results r ON r.attempt_id = ps.attempt_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY ps.risk_score DESC, ps.started_at DESC",
    $params
);

Response::success($reports);
