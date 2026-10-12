<?php
/**
 * POST /api/auth/login
 * Authenticates user and creates a session.
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
require_once __DIR__ . '/../../middleware/Auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$ip        = RateLimit::getClientIP();
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

// Rate limit: 5 attempts per 5 minutes per IP
if (!RateLimit::check("login_ip_{$ip}", 20, 300)) {
    Logger::security(null, 'suspicious_activity', ['reason' => 'IP rate limit on login', 'ip' => $ip], $ip);
    Response::error('Too many login attempts from this IP. Please try again in 5 minutes.', 429);
}

$body = InputValidator::getRequestBody();

$email    = strtolower(trim($body['email'] ?? ''));
$password = $body['password'] ?? '';

if (!$email || !$password) {
    Response::error('Email and password are required', 422);
}

if (!InputValidator::validateEmail($email)) {
    Response::error('Invalid email format', 422);
}

// Look up user
$user = db()->fetchOne(
    "SELECT u.*, 
        COALESCE(s.first_name, e.first_name, a.first_name) AS first_name,
        COALESCE(s.last_name,  e.last_name,  a.last_name)  AS last_name,
        COALESCE(s.profile_photo_path, e.profile_photo_path) AS profile_photo_path
     FROM users u
     LEFT JOIN students  s ON s.user_id = u.id
     LEFT JOIN examiners e ON e.user_id = u.id
     LEFT JOIN admins    a ON a.user_id = u.id
     WHERE u.email = ?",
    [$email]
);

// Account not found — use same error message to prevent user enumeration
if (!$user) {
    Logger::security(null, 'login_failed', ['email' => $email, 'reason' => 'not_found'], $ip);
    Response::error('Invalid email or password', 401);
}

$userId = (int)$user['id'];

// Check account lock
if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
    $remainingMin = ceil((strtotime($user['locked_until']) - time()) / 60);
    Logger::security($userId, 'login_failed', ['reason' => 'account_locked'], $ip);
    Response::error("Account is locked. Please try again in {$remainingMin} minute(s).", 423);
}

// Check account active
if (!$user['is_active']) {
    Logger::security($userId, 'login_failed', ['reason' => 'account_inactive'], $ip);
    Response::error('Your account has been deactivated. Please contact support.', 403);
}

// Verify password
if (!PasswordHelper::verify($password, $user['password_hash'])) {
    $newAttempts = (int)$user['failed_login_attempts'] + 1;
    $lockUntil   = null;

    if ($newAttempts >= MAX_LOGIN_ATTEMPTS) {
        $lockUntil = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION);
        NotificationHelper::accountLocked($userId);
    }

    db()->execute(
        "UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE id = ?",
        [$newAttempts, $lockUntil, $userId]
    );

    Logger::security($userId, 'login_failed', [
        'reason'   => 'wrong_password',
        'attempts' => $newAttempts,
    ], $ip);

    $remaining = max(0, MAX_LOGIN_ATTEMPTS - $newAttempts);
    $message   = $remaining > 0
        ? "Invalid email or password. {$remaining} attempt(s) remaining before lockout."
        : 'Account locked due to too many failed attempts. Try again in ' . (LOCKOUT_DURATION / 60) . ' minutes.';

    Response::error($message, 401);
}

// Success — reset failed attempts and update last login
db()->execute(
    "UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_login_at = NOW(), last_login_ip = ? WHERE id = ?",
    [$ip, $userId]
);

// Create session
$token = Auth::createSession($userId, $ip, $userAgent);

// Rehash password if needed (cost upgrade)
if (PasswordHelper::needsRehash($user['password_hash'])) {
    $newHash = PasswordHelper::hash($password);
    db()->execute("UPDATE users SET password_hash = ? WHERE id = ?", [$newHash, $userId]);
}

Logger::audit($userId, 'login', 'user', $userId, null, null, 'success', $ip);
Logger::security($userId, 'login_success', ['method' => 'password'], $ip);

// Build safe user profile response (never include password_hash)
$profile = [
    'id'                 => $userId,
    'uuid'               => $user['uuid'],
    'email'              => $user['email'],
    'role'               => $user['role'],
    'first_name'         => $user['first_name'] ?? '',
    'last_name'          => $user['last_name'] ?? '',
    'full_name'          => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
    'profile_photo_path' => $user['profile_photo_path'] ?? null,
    'email_verified'     => (bool)$user['email_verified'],
    'last_login_at'      => $user['last_login_at'],
];

Response::success([
    'token'   => $token,
    'user'    => $profile,
    'expires' => date('Y-m-d H:i:s', time() + SESSION_TIMEOUT),
], 'Login successful');
