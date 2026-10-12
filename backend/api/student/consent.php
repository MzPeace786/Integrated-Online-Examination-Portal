<?php
/**
 * GET/POST/DELETE /api/student/consent
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
$consentId = isset($_ROUTE['id']) ? (int)$_ROUTE['id'] : null;

if ($method === 'GET') {
    $examId = InputValidator::validateInt($_GET['exam_id'] ?? null);
    $params = [$userId];
    $sql = "SELECT id, exam_id, consent_type, is_given, consent_version, given_at, withdrawn_at FROM consents WHERE user_id = ?";
    if ($examId) {
        $sql .= " AND exam_id = ?";
        $params[] = $examId;
    }
    $consents = db()->fetchAll($sql, $params);
    Response::success($consents);
}

if ($method === 'POST') {
    $body = InputValidator::getRequestBody();
    $examId = InputValidator::validateInt($body['exam_id'] ?? null);
    $consentType = InputValidator::sanitizeString($body['consent_type'] ?? '', 50);
    $isGiven = !empty($body['is_given']) ? 1 : 0;
    $version = PRIVACY_POLICY_VERSION;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

    $allowed = ['terms_conditions','privacy_policy','data_processing','webcam_proctoring','face_verification','email_notifications'];
    if (!in_array($consentType, $allowed)) {
        Response::error('Invalid consent type', 422);
    }

    // Upsert consent record
    db()->execute(
        "INSERT INTO consents (user_id, exam_id, consent_type, is_given, consent_version, ip_address, user_agent, given_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE is_given = VALUES(is_given), given_at = NOW(), withdrawn_at = NULL",
        [$userId, $examId ?: null, $consentType, $isGiven, $version, $ip, $ua]
    );

    Logger::audit($userId, 'consent_recorded', 'consents', null, null, ['type' => $consentType, 'given' => $isGiven, 'exam_id' => $examId]);
    Response::success(null, 'Consent recorded successfully');
}

if ($method === 'DELETE' && $consentId) {
    // Withdraw optional consent
    $consent = db()->fetchOne("SELECT * FROM consents WHERE id = ? AND user_id = ?", [$consentId, $userId]);
    if (!$consent) Response::error('Consent record not found', 404);

    if (in_array($consent['consent_type'], ['terms_conditions', 'privacy_policy', 'data_processing'])) {
        Response::error('Mandatory account consents cannot be withdrawn individually. You may request account deletion instead.', 400);
    }

    db()->execute(
        "UPDATE consents SET is_given = 0, withdrawn_at = NOW() WHERE id = ? AND user_id = ?",
        [$consentId, $userId]
    );

    Logger::audit($userId, 'consent_withdrawn', 'consents', $consentId, null, ['type' => $consent['consent_type']]);
    Response::success(null, 'Consent withdrawn successfully');
}
