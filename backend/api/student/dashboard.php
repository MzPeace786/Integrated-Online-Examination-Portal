<?php
/**
 * GET /api/student/dashboard
 * Returns aggregated dashboard data for the logged-in student.
 */
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

$user      = Auth::authorize(['student']);
$userId    = (int)$user['user_id'];

// Get student profile
$student = db()->fetchOne(
    "SELECT s.*, u.email FROM students s JOIN users u ON u.id = s.user_id WHERE s.user_id = ?",
    [$userId]
);

// Total exam attempts (submitted)
$totalExams = db()->fetchOne(
    "SELECT COUNT(*) AS cnt FROM exam_attempts WHERE student_id = ? AND status = 'submitted'",
    [$userId]
);

// Upcoming exams (enrolled or active/scheduled, not yet expired)
$upcomingExams = db()->fetchAll(
    "SELECT DISTINCT e.id, e.uuid, e.title, e.start_datetime, e.end_datetime, e.duration_minutes,
            e.total_marks, e.proctoring_enabled, s.name AS subject_name, e.status
     FROM exams e
     LEFT JOIN exam_enrollments ee ON ee.exam_id = e.id AND ee.student_id = ?
     LEFT JOIN subjects s ON s.id = e.subject_id
     WHERE (ee.student_id = ? OR e.status IN ('scheduled','active'))
       AND e.end_datetime > NOW()
       AND e.status IN ('scheduled','active')
     ORDER BY e.start_datetime ASC
     LIMIT 5",
    [$userId, $userId]
);

// Completed exams count
$completedExams = db()->fetchOne(
    "SELECT COUNT(DISTINCT ea.exam_id) AS cnt
     FROM exam_attempts ea
     WHERE ea.student_id = ? AND ea.status = 'submitted'",
    [$userId]
);

// Average score and pass percentage
$scoreStats = db()->fetchOne(
    "SELECT 
        ROUND(AVG(r.percentage), 2) AS avg_score,
        ROUND(SUM(r.is_passed) / COUNT(*) * 100, 2) AS pass_percentage,
        COUNT(*) AS total_results
     FROM results r
     WHERE r.student_id = ? AND r.is_published = 1",
    [$userId]
);

// Certificates earned
$certCount = db()->fetchOne(
    "SELECT COUNT(*) AS cnt FROM certificates WHERE student_id = ? AND revoked_at IS NULL",
    [$userId]
);

// Recent results (last 5)
$recentResults = db()->fetchAll(
    "SELECT r.id, r.obtained_marks, r.total_marks, r.percentage, r.is_passed,
            r.evaluated_at, e.title AS exam_title, s.name AS subject_name,
            r.time_taken_seconds
     FROM results r
     JOIN exam_attempts ea ON ea.id = r.attempt_id
     JOIN exams e ON e.id = r.exam_id
     LEFT JOIN subjects s ON s.id = e.subject_id
     WHERE r.student_id = ? AND r.is_published = 1
     ORDER BY r.evaluated_at DESC
     LIMIT 5",
    [$userId]
);

// Performance chart data (last 10 exam scores for line chart)
$performanceData = db()->fetchAll(
    "SELECT e.title AS exam_name, r.percentage, r.is_passed, r.evaluated_at
     FROM results r
     JOIN exams e ON e.id = r.exam_id
     WHERE r.student_id = ? AND r.is_published = 1
     ORDER BY r.evaluated_at DESC
     LIMIT 10",
    [$userId]
);
// Reverse for chronological order in chart
$performanceData = array_reverse($performanceData);

// Unread notifications (last 5)
$notifications = db()->fetchAll(
    "SELECT id, title, message, notification_type, is_read, created_at
     FROM notifications
     WHERE user_id = ? AND is_read = 0
     ORDER BY created_at DESC
     LIMIT 5",
    [$userId]
);

Response::success([
    'student'          => [
        'id'                => $student['id'] ?? null,
        'first_name'        => $student['first_name'] ?? '',
        'last_name'         => $student['last_name'] ?? '',
        'student_id_number' => $student['student_id_number'] ?? '',
        'department'        => $student['department'] ?? '',
        'profile_photo'     => $student['profile_photo_path'] ?? null,
    ],
    'stats'            => [
        'total_exams'      => (int)($totalExams['cnt'] ?? 0),
        'upcoming_exams'   => count($upcomingExams),
        'completed_exams'  => (int)($completedExams['cnt'] ?? 0),
        'avg_score'        => (float)($scoreStats['avg_score'] ?? 0),
        'pass_percentage'  => (float)($scoreStats['pass_percentage'] ?? 0),
        'certificates'     => (int)($certCount['cnt'] ?? 0),
    ],
    'upcoming_exams'   => $upcomingExams,
    'recent_results'   => $recentResults,
    'performance_data' => $performanceData,
    'notifications'    => $notifications,
]);
