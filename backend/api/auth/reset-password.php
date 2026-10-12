<?php
/**
 * POST /api/auth/reset-password
 * Validates reset token and sets a new password.
 */
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../utils/PasswordHelper.php';
require_once __DIR__ . '/../../utils/NotificationHelper.php';
require_once __DIR__ . '/../../middleware/InputValidator.php';
require_once __DIR__ . '/../../middleware/RateLimit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$ip   = RateLimit::getClientIP();
$body = InputValidator::getRequestBody();

$token           = trim($body['token'] ?? '');
$password        = $body['password'] ?? '';
$confirmPassword = $body['confirm_password'] ?? '';

if (!$token || !$password || !$confirmPassword) {
    Response::error('Token, password and confirm_password are required', 422);
}

if ($password !== $confirmPassword) {
    Response::error('Passwords do not match', 422);
}

$pwErrors = PasswordHelper::validateStrength($password);
if (!empty($pwErrors)) {
    Response::error('Password does not meet requirements', 422, $pwErrors);
}

$tokenHash = PasswordHelper::hashToken($token);
$now       = date('Y-m-d H:i:s');

$resetRecord = db()->fetchOne(
    "SELECT prt.*, u.id as user_id, u.email
     FROM password_reset_tokens prt
     JOIN users u ON u.id = prt.user_id
     WHERE prt.token_hash = ? AND prt.used_at IS NULL AND prt.expires_at > ?",
    [$tokenHash, $now]
);

if (!$resetRecord) {
    Response::error('Invalid or expired password reset link. Please request a new one.', 400);
}

$userId = (int)$resetRecord['user_id'];
$newHash = PasswordHelper::hash($password);

db()->beginTransaction();
try {
    // Update password
    db()->execute("UPDATE users SET password_hash = ?, failed_login_attempts = 0, locked_until = NULL WHERE id = ?",
        [$newHash, $userId]);

    // Mark token as used
    db()->execute("UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?",
        [(int)$resetRecord['id']]);

    // Invalidate all sessions for security
    db()->execute("UPDATE user_sessions SET is_active = 0 WHERE user_id = ?", [$userId]);

    db()->commit();

    Logger::security($userId, 'password_reset_success', ['ip' => $ip], $ip);
    Logger::audit($userId, 'password_reset', 'user', $userId, null, null, 'success', $ip);
    NotificationHelper::passwordReset($userId);

    Response::success(null, 'Password reset successfully. Please log in with your new password.');

} catch (Throwable $e) {
    db()->rollback();
    Logger::error('Password reset failed: ' . $e->getMessage());
    Response::error('Password reset failed. Please try again.', 500);
}
