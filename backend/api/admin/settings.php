<?php
/**
 * GET/PUT /api/admin/settings
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
