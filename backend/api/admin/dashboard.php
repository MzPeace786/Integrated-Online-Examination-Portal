<?php
/**
 * GET /api/admin/dashboard
 */
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';

Auth::authorize(['admin']);

$usersCount = db()->fetchOne("SELECT COUNT(*) AS cnt FROM users");
$studentsCount = db()->fetchOne("SELECT COUNT(*) AS cnt FROM users WHERE role = 'student'");
$examinersCount = db()->fetchOne("SELECT COUNT(*) AS cnt FROM users WHERE role = 'examiner'");
$examsCount = db()->fetchOne("SELECT COUNT(*) AS cnt FROM exams");
$resultsCount = db()->fetchOne("SELECT COUNT(*) AS cnt FROM results");
$certsCount = db()->fetchOne("SELECT COUNT(*) AS cnt FROM certificates WHERE revoked_at IS NULL");
$activeProctoring = db()->fetchOne("SELECT COUNT(*) AS cnt FROM proctoring_sessions WHERE status = 'active'");

$recentAudit = db()->fetchAll(
    "SELECT al.*, u.email FROM audit_logs al
     LEFT JOIN users u ON u.id = al.user_id
     ORDER BY al.created_at DESC LIMIT 6"
);

$recentSecurity = db()->fetchAll(
    "SELECT sl.*, u.email FROM security_logs sl
     LEFT JOIN users u ON u.id = sl.user_id
     ORDER BY sl.created_at DESC LIMIT 6"
);

Response::success([
    'stats' => [
        'total_users' => (int)($usersCount['cnt'] ?? 0),
        'total_students' => (int)($studentsCount['cnt'] ?? 0),
        'total_examiners' => (int)($examinersCount['cnt'] ?? 0),
        'total_exams' => (int)($examsCount['cnt'] ?? 0),
        'total_results' => (int)($resultsCount['cnt'] ?? 0),
        'total_certificates' => (int)($certsCount['cnt'] ?? 0),
        'active_proctoring' => (int)($activeProctoring['cnt'] ?? 0),
    ],
    'recent_audit' => $recentAudit,
    'recent_security' => $recentSecurity,
]);
