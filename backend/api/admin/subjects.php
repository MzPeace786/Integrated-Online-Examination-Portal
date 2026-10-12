<?php
/**
 * GET/POST/PUT/DELETE /api/admin/subjects
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
$subjectId = isset($_ROUTE['id']) ? (int)$_ROUTE['id'] : null;

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($method === 'GET') {
    $subjects = db()->fetchAll(
        "SELECT s.*, c.name AS course_name, c.code AS course_code,
                (SELECT COUNT(*) FROM questions WHERE subject_id = s.id AND is_deleted = 0) AS questions_count,
                (SELECT COUNT(*) FROM exams WHERE subject_id = s.id) AS exams_count
         FROM subjects s
         LEFT JOIN courses c ON c.id = s.course_id
         ORDER BY s.name ASC"
    );
    Response::success($subjects);
}

if ($method === 'POST') {
    $body = InputValidator::getRequestBody();
    $name = InputValidator::sanitizeString($body['name'] ?? '', 200);
    $code = strtoupper(InputValidator::sanitizeString($body['code'] ?? '', 20));
    $courseId = InputValidator::validateInt($body['course_id'] ?? null);
    $desc = InputValidator::sanitizeString($body['description'] ?? '', 500);

    if (empty($name) || empty($code)) Response::error('Subject name and code are required', 422);

    $dup = db()->fetchOne("SELECT id FROM subjects WHERE code = ?", [$code]);
    if ($dup) {
        Response::error('Subject code already exists', 409);
    }

    $id = db()->insert(
        "INSERT INTO subjects (name, code, description, course_id, is_active, created_by, created_at)
         VALUES (?, ?, ?, ?, 1, ?, NOW())",
        [$name, $code, $desc ?: null, $courseId, $adminId]
    );
    Response::created(['id' => $id], 'Subject created successfully');
}

if ($method === 'PUT' && $subjectId) {
    $body = InputValidator::getRequestBody();
    $name = InputValidator::sanitizeString($body['name'] ?? '', 200);
    $code = strtoupper(InputValidator::sanitizeString($body['code'] ?? '', 20));
    $courseId = InputValidator::validateInt($body['course_id'] ?? null);
    $desc = InputValidator::sanitizeString($body['description'] ?? '', 500);
    $isActive = isset($body['is_active']) ? (int)(bool)$body['is_active'] : null;

    $fields = [];
    $params = [];
    if (!empty($name)) { $fields[] = 'name = ?'; $params[] = $name; }
    if (!empty($code)) { $fields[] = 'code = ?'; $params[] = $code; }
    if ($courseId !== null) { $fields[] = 'course_id = ?'; $params[] = $courseId; }
    if ($desc !== '')   { $fields[] = 'description = ?'; $params[] = $desc; }
    if ($isActive !== null) { $fields[] = 'is_active = ?'; $params[] = $isActive; }

    if (empty($fields)) {
        Response::error('No update parameters provided', 400);
    }

    $params[] = $subjectId;
    db()->execute("UPDATE subjects SET " . implode(', ', $fields) . " WHERE id = ?", $params);
    Response::success(null, 'Subject updated successfully');
}

if ($method === 'DELETE' && $subjectId) {
    // Check if questions or exams exist
    $qCount = (int)db()->fetchOne("SELECT COUNT(*) AS c FROM questions WHERE subject_id = ?", [$subjectId])['c'];
    $eCount = (int)db()->fetchOne("SELECT COUNT(*) AS c FROM exams WHERE subject_id = ?", [$subjectId])['c'];

    if ($qCount > 0 || $eCount > 0) {
        db()->execute("UPDATE subjects SET is_active = 0 WHERE id = ?", [$subjectId]);
        Response::success(null, 'Subject has linked questions/exams; deactivated instead of hard deleted');
    } else {
        db()->execute("DELETE FROM subjects WHERE id = ?", [$subjectId]);
        Response::success(null, 'Subject deleted successfully');
    }
}

Response::error('Method not allowed', 405);
