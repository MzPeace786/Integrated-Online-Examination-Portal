<?php
/**
 * GET/POST/PUT/DELETE /api/examiner/question-banks
 */
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/InputValidator.php';

$user = Auth::authorize(['examiner', 'admin']);
$userId = (int)$user['user_id'];
$isAdmin = ($user['role'] === 'admin');
$method = $_SERVER['REQUEST_METHOD'];

global $_ROUTE;
$bankId = isset($_ROUTE['id']) ? (int)$_ROUTE['id'] : null;
$action = $_ROUTE['action'] ?? null;

// GET /examiner/question-banks/{id}/questions
if ($method === 'GET' && $bankId && $action === 'questions') {
    $questions = db()->fetchAll(
        "SELECT q.*, 
            (SELECT COUNT(*) FROM question_options WHERE question_id = q.id) AS options_count
         FROM questions q
         WHERE q.question_bank_id = ? AND q.is_deleted = 0
         ORDER BY q.created_at DESC",
        [$bankId]
    );

    foreach ($questions as &$q) {
        $q['options'] = db()->fetchAll(
            "SELECT * FROM question_options WHERE question_id = ? ORDER BY display_order ASC",
            [$q['id']]
        );
    }
    unset($q);

    Response::success($questions);
}

// GET /examiner/question-banks
if ($method === 'GET' && !$bankId) {
    $where = $isAdmin ? ['1=1'] : ['qb.examiner_id = ?'];
    $params = $isAdmin ? [] : [$userId];

    $sql = "SELECT qb.*, s.name AS subject_name,
                   (SELECT COUNT(*) FROM questions WHERE question_bank_id = qb.id AND is_deleted = 0) AS questions_count
            FROM question_banks qb
            LEFT JOIN subjects s ON s.id = qb.subject_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY qb.created_at DESC";

    $banks = db()->fetchAll($sql, $params);
    Response::success($banks);
}

// POST /examiner/question-banks
if ($method === 'POST') {
    $body = InputValidator::getRequestBody();
    $name = InputValidator::sanitizeString($body['name'] ?? '', 200);
    $desc = InputValidator::sanitizeString($body['description'] ?? '', 1000);
    $subjectId = InputValidator::validateInt($body['subject_id'] ?? null);

    if (empty($name)) Response::error('Bank name is required', 422);

    $id = db()->insert(
        "INSERT INTO question_banks (name, description, subject_id, examiner_id, is_active, created_at)
         VALUES (?, ?, ?, ?, 1, NOW())",
        [$name, $desc ?: null, $subjectId, $userId]
    );

    Logger::audit($userId, 'question_bank_created', 'question_banks', $id, null, ['name' => $name]);
    Response::created(['id' => $id, 'name' => $name], 'Question bank created successfully');
}

// DELETE /examiner/question-banks/{id}
if ($method === 'DELETE' && $bankId) {
    db()->execute("UPDATE questions SET is_deleted = 1 WHERE question_bank_id = ?", [$bankId]);
    db()->execute("DELETE FROM question_banks WHERE id = ?" . ($isAdmin ? "" : " AND examiner_id = {$userId}"), [$bankId]);
    Logger::audit($userId, 'question_bank_deleted', 'question_banks', $bankId);
    Response::success(null, 'Question bank and associated questions removed');
}
