<?php
/**
 * GET/POST/PUT/DELETE /api/admin/courses
 */
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/InputValidator.php';

$admin = Auth::authorize(['admin', 'examiner']);
$adminId = (int)$admin['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

global $_ROUTE;
$courseId = isset($_ROUTE['id']) ? (int)$_ROUTE['id'] : null;

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($method === 'GET') {
    $courses = db()->fetchAll(
        "SELECT c.*, (SELECT COUNT(*) FROM subjects WHERE course_id = c.id) AS subjects_count 
         FROM courses c 
         ORDER BY c.name ASC"
    );
    Response::success($courses);
}

if ($method === 'POST') {
    $body = InputValidator::getRequestBody();
    $name = InputValidator::sanitizeString($body['name'] ?? '', 200);
    $code = strtoupper(InputValidator::sanitizeString($body['code'] ?? '', 20));
    $desc = InputValidator::sanitizeString($body['description'] ?? '', 500);
    $years = InputValidator::validateInt($body['duration_years'] ?? 4, 1, 6);

    if (empty($name) || empty($code)) Response::error('Name and code are required', 422);

    // Check duplicate code
    $dup = db()->fetchOne("SELECT id FROM courses WHERE code = ?", [$code]);
    if ($dup) {
        Response::error('Course code already exists', 409);
    }

    $id = db()->insert(
        "INSERT INTO courses (name, code, description, duration_years, is_active, created_by, created_at)
         VALUES (?, ?, ?, ?, 1, ?, NOW())",
        [$name, $code, $desc ?: null, $years, $adminId]
    );
    Response::created(['id' => $id], 'Course created successfully');
}

if ($method === 'PUT' && $courseId) {
    $body = InputValidator::getRequestBody();
    $name = InputValidator::sanitizeString($body['name'] ?? '', 200);
    $code = strtoupper(InputValidator::sanitizeString($body['code'] ?? '', 20));
    $desc = InputValidator::sanitizeString($body['description'] ?? '', 500);
    $isActive = isset($body['is_active']) ? (int)(bool)$body['is_active'] : null;

    $fields = [];
    $params = [];
    if (!empty($name)) { $fields[] = 'name = ?'; $params[] = $name; }
    if (!empty($code)) { $fields[] = 'code = ?'; $params[] = $code; }
    if ($desc !== '')   { $fields[] = 'description = ?'; $params[] = $desc; }
    if ($isActive !== null) { $fields[] = 'is_active = ?'; $params[] = $isActive; }

    if (empty($fields)) {
        Response::error('No update parameters provided', 400);
    }

    $params[] = $courseId;
    db()->execute("UPDATE courses SET " . implode(', ', $fields) . " WHERE id = ?", $params);
    Response::success(null, 'Course updated successfully');
}

if ($method === 'DELETE' && $courseId) {
    // Check if courses has subjects
    $subCount = (int)db()->fetchOne("SELECT COUNT(*) AS c FROM subjects WHERE course_id = ?", [$courseId])['c'];
    if ($subCount > 0) {
        // Soft delete / deactivate instead of hard delete
        db()->execute("UPDATE courses SET is_active = 0 WHERE id = ?", [$courseId]);
        Response::success(null, 'Course has linked subjects; deactivated instead of deleted');
    } else {
        db()->execute("DELETE FROM courses WHERE id = ?", [$courseId]);
        Response::success(null, 'Course deleted successfully');
    }
}

Response::error('Method not allowed', 405);
