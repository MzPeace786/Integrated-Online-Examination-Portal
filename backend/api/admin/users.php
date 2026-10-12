<?php
/**
 * GET/POST/PUT/DELETE /api/admin/users
 */
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../utils/PasswordHelper.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/InputValidator.php';

$admin = Auth::authorize(['admin']);
$adminId = (int)$admin['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

global $_ROUTE;
$targetUserId = isset($_ROUTE['id']) ? (int)$_ROUTE['id'] : null;

// 1. GET users
if ($method === 'GET') {
    $role = $_GET['role'] ?? '';
    $search = InputValidator::sanitizeString($_GET['search'] ?? '', 100);

    $where = ['1=1'];
    $params = [];

    if ($role) {
        $where[] = 'u.role = ?';
        $params[] = $role;
    }
    if ($search) {
        $where[] = '(u.email LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ?)';
        $term = "%{$search}%";
        array_push($params, $term, $term, $term, $term, $term);
    }

    $sql = "SELECT u.id, u.uuid, u.email, u.role, u.is_active, u.email_verified, u.created_at, u.last_login_at,
                   COALESCE(s.first_name, e.first_name, a.first_name) AS first_name,
                   COALESCE(s.last_name, e.last_name, a.last_name) AS last_name,
                   s.student_id_number, s.department, e.designation
            FROM users u
            LEFT JOIN students s ON s.user_id = u.id
            LEFT JOIN examiners e ON e.user_id = u.id
            LEFT JOIN admins a ON a.user_id = u.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY u.created_at DESC";

    $users = db()->fetchAll($sql, $params);
    Response::success($users);
}

// 2. POST user (Admin creates user)
if ($method === 'POST') {
    $body = InputValidator::getRequestBody();
    $email = strtolower(trim($body['email'] ?? ''));
    $password = $body['password'] ?? 'Pass@123!';
    $role = in_array($body['role'] ?? '', ['student', 'examiner', 'admin']) ? $body['role'] : 'student';
    $firstName = InputValidator::sanitizeString($body['first_name'] ?? '', 100);
    $lastName = InputValidator::sanitizeString($body['last_name'] ?? '', 100);

    if (!InputValidator::validateEmail($email)) Response::error('Valid email is required', 422);

    $exists = db()->fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
    if ($exists) Response::error('Email already in use', 409);

    $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );

    db()->beginTransaction();
    try {
        $uId = db()->insert(
            "INSERT INTO users (uuid, email, password_hash, role, is_active, email_verified)
             VALUES (?, ?, ?, ?, 1, 1)",
            [$uuid, $email, PasswordHelper::hash($password), $role]
        );

        if ($role === 'student') {
            db()->insert("INSERT INTO students (user_id, first_name, last_name) VALUES (?, ?, ?)", [$uId, $firstName ?: 'Student', $lastName ?: 'User']);
        } elseif ($role === 'examiner') {
            db()->insert("INSERT INTO examiners (user_id, first_name, last_name) VALUES (?, ?, ?)", [$uId, $firstName ?: 'Examiner', $lastName ?: 'User']);
        } elseif ($role === 'admin') {
            db()->insert("INSERT INTO admins (user_id, first_name, last_name) VALUES (?, ?, ?)", [$uId, $firstName ?: 'Admin', $lastName ?: 'User']);
        }

        db()->commit();
        Logger::audit($adminId, 'user_created_by_admin', 'users', $uId, null, ['email' => $email, 'role' => $role]);
        Response::created(['user_id' => $uId], 'User created successfully');
    } catch (Exception $e) {
        db()->rollback();
        Response::error('Failed to create user: ' . $e->getMessage(), 500);
    }
}

// 3. PUT user (Toggle active or reset)
if ($method === 'PUT' && $targetUserId) {
    $body = InputValidator::getRequestBody();
    if (isset($body['is_active'])) {
        $isActive = !empty($body['is_active']) ? 1 : 0;
        db()->execute("UPDATE users SET is_active = ? WHERE id = ?", [$isActive, $targetUserId]);
        Logger::audit($adminId, 'user_status_toggled', 'users', $targetUserId, null, ['is_active' => $isActive]);
        Response::success(null, 'User status updated');
    }

    if (!empty($body['password'])) {
        $hash = PasswordHelper::hash($body['password']);
        db()->execute("UPDATE users SET password_hash = ? WHERE id = ?", [$hash, $targetUserId]);
        Logger::audit($adminId, 'user_password_reset_by_admin', 'users', $targetUserId);
        Response::success(null, 'Password updated');
    }
}

// 4. DELETE user
if ($method === 'DELETE' && $targetUserId) {
    db()->execute("UPDATE users SET is_active = 0 WHERE id = ?", [$targetUserId]);
    Logger::audit($adminId, 'user_deactivated', 'users', $targetUserId);
    Response::success(null, 'User deactivated');
}
