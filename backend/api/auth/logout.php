<?php
/**
 * POST /api/auth/logout
 * Invalidates the current session.
 */
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../middleware/Auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$user = Auth::authenticate();
Auth::destroySession(Auth::currentToken());

Logger::audit($user['user_id'], 'logout', 'user', $user['user_id'], null, null, 'success', RateLimit::getClientIP() ?? $_SERVER['REMOTE_ADDR'] ?? null);

Response::success(null, 'Logged out successfully');
