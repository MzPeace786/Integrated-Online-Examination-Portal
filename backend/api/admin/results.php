<?php
/**
 * GET/PUT /api/admin/results
 */
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/InputValidator.php';

Auth::authorize(['admin']);

$method = $_SERVER['REQUEST_METHOD'];
global $_ROUTE;
$resultId = isset($_ROUTE['id']) ? (int)$_ROUTE['id'] : null;

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($method === 'GET') {
    $results = db()->fetchAll(
        "SELECT r.*, e.title AS exam_title,
                CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                s.student_id_number, sub.name AS subject_name,
                ps.risk_score, ps.camera_status
         FROM results r
         JOIN exams e ON e.id = r.exam_id
         JOIN students s ON s.user_id = r.student_id
         LEFT JOIN subjects sub ON sub.id = e.subject_id
         LEFT JOIN proctoring_sessions ps ON ps.attempt_id = r.attempt_id
         ORDER BY r.evaluated_at DESC"
    );
    Response::success($results);
}

if ($method === 'PUT' && $resultId) {
    $body = InputValidator::getRequestBody();
    $isPublished = isset($body['is_published']) ? (int)(bool)$body['is_published'] : 1;

    db()->execute(
        "UPDATE results SET is_published = ?, published_at = IF(? = 1, NOW(), NULL) WHERE id = ?",
        [$isPublished, $isPublished, $resultId]
    );
    Response::success(null, 'Result publication status updated');
}

Response::error('Method not allowed', 405);
