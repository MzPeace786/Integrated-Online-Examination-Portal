<?php
/**
 * GET/PUT /api/admin/certificates
 */
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/InputValidator.php';

$admin = Auth::authorize(['admin']);
$adminId = (int)$admin['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

global $_ROUTE;
$certId = isset($_ROUTE['id']) ? (int)$_ROUTE['id'] : null;
$action = $_ROUTE['action'] ?? null;

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($method === 'GET') {
    $certs = db()->fetchAll(
        "SELECT c.*, e.title AS exam_name,
                CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                s.student_id_number, r.percentage, r.obtained_marks, r.total_marks
         FROM certificates c
         JOIN exams e ON e.id = c.exam_id
         JOIN results r ON r.id = c.result_id
         JOIN students s ON s.user_id = c.student_id
         ORDER BY c.issued_at DESC"
    );
    Response::success($certs);
}

if ($method === 'PUT' && $certId) {
    if ($action === 'revoke') {
        $body = InputValidator::getRequestBody();
        $reason = InputValidator::sanitizeString($body['reason'] ?? 'Revoked by system administrator', 500);

        db()->execute(
            "UPDATE certificates 
             SET revoked_at = NOW(), revoke_reason = ?, revoked_by = ? 
             WHERE id = ?",
            [$reason, $adminId, $certId]
        );
        Response::success(null, 'Certificate revoked successfully');
    }

    if ($action === 'restore') {
        db()->execute(
            "UPDATE certificates 
             SET revoked_at = NULL, revoke_reason = NULL, revoked_by = NULL 
             WHERE id = ?",
            [$certId]
        );
        Response::success(null, 'Certificate reinstated successfully');
    }
}

Response::error('Method not allowed', 405);
