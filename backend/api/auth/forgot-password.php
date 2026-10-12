<?php
/**
 * POST /api/auth/forgot-password
 * Generates a password reset token and sends reset instructions.
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

$ip = RateLimit::getClientIP();
if (!RateLimit::check("forgot_pw_{$ip}", 5, 3600)) {
    Response::error('Too many password reset requests. Please try again later.', 429);
}

$body  = InputValidator::getRequestBody();
$email = strtolower(trim($body['email'] ?? ''));

if (!$email || !InputValidator::validateEmail($email)) {
    Response::error('Please provide a valid email address', 422);
}

// Always return the same success message to prevent email enumeration
$successMessage = 'If an account with that email exists, a password reset link has been sent.';

$user = db()->fetchOne("SELECT id, email, is_active FROM users WHERE email = ?", [$email]);

if (!$user || !$user['is_active']) {
    // Return success even for non-existent accounts (security best practice)
    Response::success(null, $successMessage);
}

$userId = (int)$user['id'];

// Invalidate any existing unexpired tokens
db()->execute(
    "UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL AND expires_at > NOW()",
    [$userId]
);

// Generate new reset token (expires in 1 hour)
$token     = PasswordHelper::generateResetToken();
$tokenHash = PasswordHelper::hashToken($token);
$expiresAt = date('Y-m-d H:i:s', time() + 3600);

db()->insert(
    "INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)",
    [$userId, $tokenHash, $expiresAt]
);

// Build reset URL
$resetUrl = APP_URL . '/frontend/pages/reset-password.html?token=' . urlencode($token);

// Send email
$subject = APP_NAME . ' — Password Reset Request';
$body    = "
<html><body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
<h2 style='color: #4F46E5;'>Password Reset Request</h2>
<p>We received a request to reset your password for your " . APP_NAME . " account.</p>
<p>Click the button below to reset your password. This link expires in 1 hour.</p>
<p style='text-align:center; margin: 30px 0;'>
  <a href='{$resetUrl}' style='background: #4F46E5; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; display: inline-block;'>Reset Password</a>
</p>
<p>If you did not request a password reset, please ignore this email. Your account remains secure.</p>
<hr style='border: 1px solid #e5e7eb;'>
<p style='color: #9CA3AF; font-size: 12px;'>This is an automated email from " . APP_NAME . ". Do not reply to this email.</p>
</body></html>";

NotificationHelper::sendEmail($email, $subject, $body);

Logger::security($userId, 'password_reset_request', ['ip' => $ip], $ip);

Response::success(null, $successMessage);
