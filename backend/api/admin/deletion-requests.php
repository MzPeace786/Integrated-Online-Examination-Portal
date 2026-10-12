<?php
/**
 * GET/PUT /api/admin/deletion-requests
 */
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/InputValidator.php';

$admin = Auth::authorize(['admin']);
$adminId = (int)$admin['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

global $_ROUTE;
$reqId = isset($_ROUTE['id']) ? (int)$_ROUTE['id'] : null;

if ($method === 'PUT' && $reqId) {
    $body = InputValidator::getRequestBody();
    $status = in_array($body['status'] ?? '', ['completed', 'rejected', 'processing']) ? $body['status'] : 'completed';
    $notes = InputValidator::sanitizeString($body['notes'] ?? '', 500);

    db()->execute(
        "UPDATE data_deletion_requests SET status = ?, notes = ?, processed_by = ?, processed_at = NOW() WHERE id = ?",
        [$status, $notes ?: null, $adminId, $reqId]
    );
    Logger::audit($adminId, 'deletion_request_processed', 'data_deletion_requests', $reqId, null, ['status' => $status]);
    Response::success(null, 'Deletion request updated');
}

$requests = db()->fetchAll(
    "SELECT ddr.*, u.email, CONCAT(s.first_name, ' ', s.last_name) AS student_name
     FROM data_deletion_requests ddr
     JOIN users u ON u.id = ddr.user_id
     LEFT JOIN students s ON s.user_id = u.id
     ORDER BY ddr.requested_at DESC"
);
Response::success($requests);
