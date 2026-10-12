<?php
/**
 * GET /api/auth/me
 * Returns the currently authenticated user's profile.
 */
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

$session = Auth::authenticate();
$userId  = (int)$session['user_id'];

$user = db()->fetchOne(
    "SELECT u.id, u.uuid, u.email, u.role, u.is_active, u.email_verified, u.last_login_at,
        COALESCE(s.first_name, e.first_name, a.first_name) AS first_name,
        COALESCE(s.last_name,  e.last_name,  a.last_name)  AS last_name,
        COALESCE(s.profile_photo_path, e.profile_photo_path) AS profile_photo_path,
        s.student_id_number, s.department, s.semester, s.institution,
        e.designation, e.qualification
     FROM users u
     LEFT JOIN students  s ON s.user_id = u.id
     LEFT JOIN examiners e ON e.user_id = u.id
     LEFT JOIN admins    a ON a.user_id = u.id
     WHERE u.id = ?",
    [$userId]
);

if (!$user) {
    Response::error('User not found', 404);
}

// Unread notification count
$unreadCount = db()->fetchOne(
    "SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0",
    [$userId]
);

Response::success([
    'id'                  => (int)$user['id'],
    'uuid'                => $user['uuid'],
    'email'               => $user['email'],
    'role'                => $user['role'],
    'first_name'          => $user['first_name'] ?? '',
    'last_name'           => $user['last_name'] ?? '',
    'full_name'           => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
    'profile_photo_path'  => $user['profile_photo_path'] ?? null,
    'email_verified'      => (bool)$user['email_verified'],
    'last_login_at'       => $user['last_login_at'],
    'student_id_number'   => $user['student_id_number'] ?? null,
    'department'          => $user['department'] ?? null,
    'semester'            => $user['semester'] ?? null,
    'institution'         => $user['institution'] ?? null,
    'designation'         => $user['designation'] ?? null,
    'unread_notifications'=> (int)($unreadCount['cnt'] ?? 0),
]);
