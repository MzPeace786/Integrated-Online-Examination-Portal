<?php
/**
 * GET/POST /api/student/privacy
 */
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/InputValidator.php';

$user = Auth::authorize(['student']);
$userId = (int)$user['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

global $_ROUTE;
$action = $_ROUTE['action'] ?? null;

if ($method === 'GET') {
    // Privacy Overview: user's personal data summary
    $student = db()->fetchOne(
        "SELECT s.*, u.email, u.created_at AS registered_at, u.last_login_at
         FROM students s JOIN users u ON u.id = s.user_id WHERE s.user_id = ?",
        [$userId]
    );

    $consents = db()->fetchAll("SELECT * FROM consents WHERE user_id = ? ORDER BY created_at DESC", [$userId]);
    $requests = db()->fetchAll("SELECT * FROM data_deletion_requests WHERE user_id = ? ORDER BY requested_at DESC", [$userId]);
    $retention = db()->fetchAll("SELECT data_type, description, retention_days FROM data_retention_policies");

    $examDataCount = db()->fetchOne("SELECT COUNT(*) AS cnt FROM exam_attempts WHERE student_id = ?", [$userId]);
    $proctoringEventsCount = db()->fetchOne("SELECT COUNT(*) AS cnt FROM proctoring_events WHERE student_id = ?", [$userId]);

    Response::success([
        'personal_data' => $student,
        'consents' => $consents,
        'deletion_requests' => $requests,
        'retention_policies' => $retention,
        'summary' => [
            'total_exam_attempts' => (int)($examDataCount['cnt'] ?? 0),
            'total_proctoring_records' => (int)($proctoringEventsCount['cnt'] ?? 0),
        ]
    ]);
}

if ($method === 'POST' && $action === 'request-deletion') {
    $body = InputValidator::getRequestBody();
    $requestType = InputValidator::sanitizeString($body['request_type'] ?? 'account_deletion', 50);
    $reason = InputValidator::sanitizeString($body['reason'] ?? '', 1000);

    $allowed = ['account_deletion', 'data_export', 'data_correction', 'proctoring_data', 'exam_data'];
    if (!in_array($requestType, $allowed)) {
        Response::error('Invalid request type', 422);
    }

    $reqId = db()->insert(
        "INSERT INTO data_deletion_requests (user_id, request_type, reason, status, requested_at)
         VALUES (?, ?, ?, 'pending', NOW())",
        [$userId, $requestType, $reason ?: null]
    );

    Logger::audit($userId, 'data_deletion_requested', 'data_deletion_requests', $reqId, null, ['type' => $requestType]);
    Response::created(['request_id' => $reqId], 'Your privacy request has been submitted to the administrator.');
}
