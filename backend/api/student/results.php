<?php
/**
 * GET /api/student/results        — List all results for student
 * GET /api/student/results/{id}   — Detailed result with answer analysis
 */
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/InputValidator.php';

$user   = Auth::authorize(['student']);
$userId = (int)$user['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    Response::error('Method not allowed', 405);
}

global $_ROUTE;
$resultId = isset($_ROUTE['id']) ? (int)$_ROUTE['id'] : null;

// ---------------------------------------------------------------
// GET /student/results/{id} — Detailed result
// ---------------------------------------------------------------
if ($resultId) {
    $result = db()->fetchOne(
        "SELECT r.*, e.title AS exam_title, e.instructions, e.passing_percentage,
                e.negative_marking_enabled, s.name AS subject_name,
                ea.started_at AS attempt_started, ea.submitted_at,
                CONCAT(ex.first_name, ' ', ex.last_name) AS examiner_name
         FROM results r
         JOIN exam_attempts ea ON ea.id = r.attempt_id
         JOIN exams e ON e.id = r.exam_id
         LEFT JOIN subjects s ON s.id = e.subject_id
         LEFT JOIN examiners ex ON ex.user_id = e.examiner_id
         WHERE r.id = ? AND r.student_id = ? AND r.is_published = 1",
        [$resultId, $userId]
    );

    if (!$result) {
        Response::error('Result not found or not yet published', 404);
    }

    // Parse stored answer details
    $answerDetails = json_decode($result['answer_details'] ?? '[]', true) ?? [];

    // Enrich with question text and option details
    $enriched = [];
    foreach ($answerDetails as $detail) {
        $qId = (int)$detail['question_id'];

        $question = db()->fetchOne(
            "SELECT id, question_text, question_type, marks, explanation FROM questions WHERE id = ?",
            [$qId]
        );
        if (!$question) continue;

        // Get all options with correct flag
        $options = db()->fetchAll(
            "SELECT id, option_text, is_correct FROM question_options WHERE question_id = ? ORDER BY display_order ASC",
            [$qId]
        );

        $studentSelected = json_decode($detail['student_answer'] ?? '[]', true) ?? [];
        if (!is_array($studentSelected)) $studentSelected = [$studentSelected];

        $enriched[] = [
            'question_id'       => $qId,
            'question_text'     => $question['question_text'],
            'question_type'     => $question['question_type'],
            'marks'             => $question['marks'],
            'explanation'       => $question['explanation'],
            'options'           => $options,
            'student_selected'  => array_map('intval', $studentSelected),
            'correct_ids'       => array_map('intval', $detail['correct_ids'] ?? []),
            'status'            => $detail['status'],
            'marks_earned'      => $detail['marks_earned'],
        ];
    }

    // Certificate info
    $certificate = db()->fetchOne(
        "SELECT certificate_number, issued_at, qr_code_data FROM certificates
         WHERE result_id = ? AND revoked_at IS NULL",
        [$resultId]
    );

    // Rank among all students for this exam
    $rank = db()->fetchOne(
        "SELECT COUNT(*) + 1 AS rank FROM results
         WHERE exam_id = ? AND obtained_marks > ? AND is_published = 1",
        [(int)$result['exam_id'], (float)$result['obtained_marks']]
    );

    Response::success([
        'result'         => array_merge($result, [
            'answer_details' => null,  // remove raw JSON field
            'rank'           => (int)$rank['rank'],
        ]),
        'answer_analysis'=> $enriched,
        'certificate'    => $certificate,
    ]);
}

// ---------------------------------------------------------------
// GET /student/results — List all results
// ---------------------------------------------------------------
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(50, max(1, (int)($_GET['per_page'] ?? 10)));
$offset  = ($page - 1) * $perPage;

$total = db()->fetchOne(
    "SELECT COUNT(*) AS cnt FROM results WHERE student_id = ? AND is_published = 1",
    [$userId]
);

$results = db()->fetchAll(
    "SELECT r.id, r.obtained_marks, r.total_marks, r.percentage, r.is_passed,
            r.correct_answers, r.incorrect_answers, r.unanswered_questions,
            r.time_taken_seconds, r.evaluated_at, r.rank,
            e.id AS exam_id, e.title AS exam_title, e.total_questions,
            s.name AS subject_name
     FROM results r
     JOIN exams e ON e.id = r.exam_id
     LEFT JOIN subjects s ON s.id = e.subject_id
     WHERE r.student_id = ? AND r.is_published = 1
     ORDER BY r.evaluated_at DESC
     LIMIT ? OFFSET ?",
    [$userId, $perPage, $offset]
);

Response::paginated($results, (int)$total['cnt'], $page, $perPage);
