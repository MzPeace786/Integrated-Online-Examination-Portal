<?php
/**
 * GET/PUT/DELETE /api/student/notifications
 */
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';

$user = Auth::authenticate();
$userId = (int)$user['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

global $_ROUTE;
$notifId = isset($_ROUTE['id']) ? (int)$_ROUTE['id'] : null;
$action  = $_ROUTE['action'] ?? null;

if ($method === 'GET') {
    $notifications = db()->fetchAll(
        "SELECT id, title, message, notification_type, is_read, read_at, created_at
         FROM notifications
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT 50",
        [$userId]
    );
    Response::success($notifications);
}

if ($method === 'PUT') {
    if ($notifId && $action === 'read') {
        db()->execute(
            "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?",
            [$notifId, $userId]
        );
        Response::success(null, 'Notification marked as read');
    } elseif ($action === 'read-all') {
        db()->execute(
            "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0",
            [$userId]
        );
        Response::success(null, 'All notifications marked as read');
    }
}

if ($method === 'DELETE' && $notifId) {
    db()->execute("DELETE FROM notifications WHERE id = ? AND user_id = ?", [$notifId, $userId]);
    Response::success(null, 'Notification deleted');
}
