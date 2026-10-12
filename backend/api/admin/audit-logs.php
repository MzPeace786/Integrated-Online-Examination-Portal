<?php
/**
 * GET /api/admin/audit-logs
 * GET /api/admin/security-logs
 * GET/PUT /api/admin/settings
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

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

if (str_contains($uri, 'security-logs')) {
    $logs = db()->fetchAll(
        "SELECT sl.*, u.email FROM security_logs sl
         LEFT JOIN users u ON u.id = sl.user_id
         ORDER BY sl.created_at DESC LIMIT 100"
    );
    Response::success($logs);
} elseif (str_contains($uri, 'settings')) {
    if ($method === 'GET') {
        $settings = db()->fetchAll("SELECT * FROM system_settings ORDER BY id ASC");
        Response::success($settings);
    }
    if ($method === 'PUT' || $method === 'POST') {
        $body = InputValidator::getRequestBody();
        $key = $body['key'] ?? '';
        $value = $body['value'] ?? '';
        if ($key) {
            db()->execute("UPDATE system_settings SET setting_value = ?, updated_by = ?, updated_at = NOW() WHERE setting_key = ?", [$value, $adminId, $key]);
            Logger::audit($adminId, 'setting_updated', 'system_settings', null, null, ['key' => $key, 'val' => $value]);
            Response::success(null, 'Setting updated successfully');
        }
    }
} elseif (str_contains($uri, 'deletion-requests')) {
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
} else {
    // Audit logs
    $action = InputValidator::sanitizeString($_GET['action'] ?? '', 50);
    $where = ['1=1'];
    $params = [];
    if ($action) {
        $where[] = 'al.action = ?';
        $params[] = $action;
    }
    $logs = db()->fetchAll(
        "SELECT al.*, u.email FROM audit_logs al
         LEFT JOIN users u ON u.id = al.user_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY al.created_at DESC LIMIT 100",
        $params
    );
    Response::success($logs);
}
