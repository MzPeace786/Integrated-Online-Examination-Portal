<?php
/**
 * GET /api/examiner/results
 * GET /api/examiner/analytics
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

// Check script name to dispatch appropriately
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

if (str_contains($uri, 'analytics')) {
    $examId = InputValidator::validateInt($_GET['exam_id'] ?? null);

    $sqlWhere = $isAdmin ? "1=1" : "e.examiner_id = {$userId}";
    if ($examId) $sqlWhere .= " AND e.id = {$examId}";

    // Average score per exam
    $examAverages = db()->fetchAll(
        "SELECT e.title AS exam_name, ROUND(AVG(r.percentage), 1) AS avg_percentage,
                COUNT(r.id) AS total_attempts,
                SUM(CASE WHEN r.is_passed = 1 THEN 1 ELSE 0 END) AS passed_count,
                SUM(CASE WHEN r.is_passed = 0 THEN 1 ELSE 0 END) AS failed_count
         FROM exams e
         LEFT JOIN results r ON r.exam_id = e.id
         WHERE {$sqlWhere}
         GROUP BY e.id, e.title"
    );

    // Score distribution buckets (0-40, 41-60, 61-80, 81-100)
    $distribution = db()->fetchOne(
        "SELECT 
            SUM(CASE WHEN r.percentage < 40 THEN 1 ELSE 0 END) AS bucket_0_40,
            SUM(CASE WHEN r.percentage >= 40 AND r.percentage < 60 THEN 1 ELSE 0 END) AS bucket_40_60,
            SUM(CASE WHEN r.percentage >= 60 AND r.percentage < 80 THEN 1 ELSE 0 END) AS bucket_60_80,
            SUM(CASE WHEN r.percentage >= 80 THEN 1 ELSE 0 END) AS bucket_80_100
         FROM results r
         JOIN exams e ON e.id = r.exam_id
         WHERE {$sqlWhere}"
    );

    Response::success([
        'exam_averages' => $examAverages,
        'distribution'  => $distribution
    ]);
} elseif (str_contains($uri, 'proctoring-reports')) {
    $examId = InputValidator::validateInt($_GET['exam_id'] ?? null);
    $where = $isAdmin ? ['1=1'] : ['e.examiner_id = ?'];
    $params = $isAdmin ? [] : [$userId];

    if ($examId) {
        $where[] = 'e.id = ?';
        $params[] = $examId;
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
} else {
    // Standard results endpoint
    $examId = InputValidator::validateInt($_GET['exam_id'] ?? null);
    $where = $isAdmin ? ['1=1'] : ['e.examiner_id = ?'];
    $params = $isAdmin ? [] : [$userId];

    if ($examId) {
        $where[] = 'r.exam_id = ?';
        $params[] = $examId;
    }

    $results = db()->fetchAll(
        "SELECT r.*, e.title AS exam_title,
                CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                s.student_id_number
         FROM results r
         JOIN exams e ON e.id = r.exam_id
         JOIN students s ON s.user_id = r.student_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY r.evaluated_at DESC",
        $params
    );

    Response::success($results);
}
