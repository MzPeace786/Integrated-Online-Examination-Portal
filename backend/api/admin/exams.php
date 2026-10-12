<?php
/**
 * GET/PUT/DELETE /api/admin/exams
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
$examId = isset($_ROUTE['id']) ? (int)$_ROUTE['id'] : null;

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($method === 'GET') {
    $exams = db()->fetchAll(
        "SELECT e.*, s.name AS subject_name, s.code AS subject_code,
                CONCAT(u.email) AS examiner_email,
                CONCAT(ex.first_name, ' ', ex.last_name) AS examiner_name,
                (SELECT COUNT(*) FROM exam_enrollments WHERE exam_id = e.id) AS enrolled_count,
                (SELECT COUNT(*) FROM exam_attempts WHERE exam_id = e.id AND status = 'submitted') AS submitted_count,
                (SELECT ROUND(AVG(percentage), 1) FROM results WHERE exam_id = e.id) AS avg_percentage
         FROM exams e
         LEFT JOIN subjects s ON s.id = e.subject_id
         LEFT JOIN users u ON u.id = e.examiner_id
         LEFT JOIN examiners ex ON ex.user_id = e.examiner_id
         ORDER BY e.created_at DESC"
    );
    Response::success($exams);
}

if ($method === 'PUT' && $examId) {
    $body = InputValidator::getRequestBody();
    $status = InputValidator::sanitizeString($body['status'] ?? '', 20);
    $proctoring = isset($body['proctoring_enabled']) ? (int)(bool)$body['proctoring_enabled'] : null;

    $fields = [];
    $params = [];
    if (!empty($status) && in_array($status, ['draft', 'scheduled', 'active', 'completed', 'cancelled'])) {
        $fields[] = 'status = ?';
        $params[] = $status;
    }
    if ($proctoring !== null) {
        $fields[] = 'proctoring_enabled = ?';
        $params[] = $proctoring;
    }

    if (empty($fields)) {
        Response::error('No update parameters provided', 400);
    }

    $params[] = $examId;
    db()->execute("UPDATE exams SET " . implode(', ', $fields) . " WHERE id = ?", $params);
    Response::success(null, 'Exam updated successfully');
}

if ($method === 'DELETE' && $examId) {
    // Check if attempts exist
    $attemptsCount = (int)db()->fetchOne("SELECT COUNT(*) AS c FROM exam_attempts WHERE exam_id = ?", [$examId])['c'];
    if ($attemptsCount > 0) {
        db()->execute("UPDATE exams SET status = 'cancelled' WHERE id = ?", [$examId]);
        Response::success(null, 'Exam has candidate attempts; marked as cancelled');
    } else {
        db()->execute("DELETE FROM exams WHERE id = ?", [$examId]);
        Response::success(null, 'Exam deleted successfully');
    }
}

Response::error('Method not allowed', 405);
