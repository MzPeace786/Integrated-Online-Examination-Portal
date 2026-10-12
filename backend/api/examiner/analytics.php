<?php
/**
 * GET /api/examiner/analytics
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
