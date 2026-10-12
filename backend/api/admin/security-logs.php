<?php
/**
 * GET /api/admin/security-logs
 */
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';

Auth::authorize(['admin']);

$logs = db()->fetchAll(
    "SELECT sl.*, u.email FROM security_logs sl
     LEFT JOIN users u ON u.id = sl.user_id
     ORDER BY sl.created_at DESC LIMIT 100"
);
Response::success($logs);
