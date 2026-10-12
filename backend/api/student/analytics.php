<?php
/**
 * GET /api/student/analytics
 */
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';

$user = Auth::authorize(['student']);
$userId = (int)$user['user_id'];

// Exam-wise performance history
$examScores = db()->fetchAll(
    "SELECT e.title AS exam_name, r.percentage, r.obtained_marks, r.total_marks, r.is_passed, r.evaluated_at
     FROM results r
     JOIN exams e ON e.id = r.exam_id
     WHERE r.student_id = ? AND r.is_published = 1
     ORDER BY r.evaluated_at ASC",
    [$userId]
);

// Subject-wise performance average
$subjectStats = db()->fetchAll(
    "SELECT s.name AS subject_name, ROUND(AVG(r.percentage), 1) AS avg_percentage, COUNT(r.id) AS exams_count
     FROM results r
     JOIN exams e ON e.id = r.exam_id
     JOIN subjects s ON s.id = e.subject_id
     WHERE r.student_id = ? AND r.is_published = 1
     GROUP BY s.id, s.name",
    [$userId]
);

// Overall question stats
$totals = db()->fetchOne(
    "SELECT 
        SUM(r.correct_answers) AS total_correct,
        SUM(r.incorrect_answers) AS total_incorrect,
        SUM(r.unanswered_questions) AS total_unanswered,
        COUNT(r.id) AS total_exams,
        MAX(r.percentage) AS best_percentage,
        MIN(r.percentage) AS lowest_percentage,
        ROUND(AVG(r.percentage), 1) AS average_percentage,
        SUM(CASE WHEN r.is_passed = 1 THEN 1 ELSE 0 END) AS passed_count,
        SUM(CASE WHEN r.is_passed = 0 THEN 1 ELSE 0 END) AS failed_count
     FROM results r
     WHERE r.student_id = ? AND r.is_published = 1",
    [$userId]
);

Response::success([
    'exam_scores' => $examScores,
    'subject_stats' => $subjectStats,
    'overall' => [
        'total_correct' => (int)($totals['total_correct'] ?? 0),
        'total_incorrect' => (int)($totals['total_incorrect'] ?? 0),
        'total_unanswered' => (int)($totals['total_unanswered'] ?? 0),
        'total_exams' => (int)($totals['total_exams'] ?? 0),
        'best_percentage' => (float)($totals['best_percentage'] ?? 0),
        'lowest_percentage' => (float)($totals['lowest_percentage'] ?? 0),
        'average_percentage' => (float)($totals['average_percentage'] ?? 0),
        'passed_count' => (int)($totals['passed_count'] ?? 0),
        'failed_count' => (int)($totals['failed_count'] ?? 0),
    ]
]);
