<?php
/**
 * GET  /api/student/exams          — List available/enrolled exams
 * GET  /api/student/exams/{id}     — Exam instructions detail
 * POST /api/student/exams/{id}/start — Start exam attempt
 */
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/InputValidator.php';

$user   = Auth::authorize(['student']);
$userId = (int)$user['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

global $_ROUTE;
$examId = isset($_ROUTE['id']) ? (int)$_ROUTE['id'] : null;
$action = $_ROUTE['action'] ?? null;

// ---------------------------------------------------------------
// POST /student/exams/{id}/start
// ---------------------------------------------------------------
if ($method === 'POST' && $examId && $action === 'start') {
    // Verify enrollment or auto-enroll
    $enrollment = db()->fetchOne(
        "SELECT * FROM exam_enrollments WHERE exam_id = ? AND student_id = ?",
        [$examId, $userId]
    );
    if (!$enrollment) {
        db()->execute(
            "INSERT IGNORE INTO exam_enrollments (exam_id, student_id, enrolled_at) VALUES (?, ?, NOW())",
            [$examId, $userId]
        );
        $enrollment = ['exam_id' => $examId, 'student_id' => $userId];
    }

    // Get exam details
    $exam = db()->fetchOne(
        "SELECT e.*, s.name AS subject_name FROM exams e
         LEFT JOIN subjects s ON s.id = e.subject_id
         WHERE e.id = ? AND e.status IN ('active','scheduled')",
        [$examId]
    );
    if (!$exam) {
        Response::error('Examination not found or not currently available', 404);
    }

    // Time window check
    $now = time();
    if ($now < strtotime($exam['start_datetime'])) {
        Response::error('This examination has not started yet', 403);
    }
    if ($now > strtotime($exam['end_datetime'])) {
        Response::error('This examination has ended', 403);
    }

    // Check attempts remaining
    $attemptCount = db()->fetchOne(
        "SELECT COUNT(*) AS cnt FROM exam_attempts WHERE exam_id = ? AND student_id = ? AND status = 'submitted'",
        [$examId, $userId]
    );
    if ((int)$attemptCount['cnt'] >= (int)$exam['allowed_attempts']) {
        Response::error('You have used all allowed attempts for this examination', 403);
    }

    // Check for in-progress attempt (resume)
    $existingAttempt = db()->fetchOne(
        "SELECT * FROM exam_attempts WHERE exam_id = ? AND student_id = ? AND status = 'in_progress'",
        [$examId, $userId]
    );
    if ($existingAttempt) {
        // Return existing attempt data for resumption
        $savedAnswers = db()->fetchAll(
            "SELECT question_id, selected_option_ids, text_answer, code_answer, language, is_marked_for_review
             FROM student_answers WHERE attempt_id = ?",
            [(int)$existingAttempt['id']]
        );
        $questionOrder = json_decode($existingAttempt['question_order'], true) ?? [];
        $questions     = buildExamQuestions($examId, $questionOrder, $exam['random_options']);
        $elapsed       = time() - strtotime($existingAttempt['started_at']);
        $remaining     = max(0, (int)$exam['duration_minutes'] * 60 - $elapsed);

        Response::success([
            'attempt_id'     => (int)$existingAttempt['id'],
            'attempt_uuid'   => $existingAttempt['uuid'],
            'exam'           => $exam,
            'questions'      => $questions,
            'saved_answers'  => $savedAnswers,
            'time_remaining' => $remaining,
            'resumed'        => true,
        ], 'Examination resumed');
    }

    // Check and record proctoring consent if proctoring is enabled
    if ($exam['proctoring_enabled']) {
        db()->execute(
            "INSERT INTO consents (user_id, exam_id, consent_type, is_given, ip_address, user_agent)
             VALUES (?, ?, 'webcam_proctoring', 1, ?, ?)
             ON DUPLICATE KEY UPDATE is_given = 1",
            [$userId, $examId, $_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)]
        );
    }

    // Get questions for this exam
    $allQIds = db()->fetchAll(
        "SELECT question_id FROM exam_questions WHERE exam_id = ? ORDER BY " . ($exam['random_questions'] ? "RAND()" : "display_order ASC"),
        [$examId]
    );

    // If no questions explicitly linked, auto-populate from subject questions or question bank
    if (empty($allQIds)) {
        $subId = (int)$exam['subject_id'];
        $bankQs = db()->fetchAll(
            "SELECT id FROM questions WHERE subject_id = ? OR subject_id IS NULL ORDER BY id ASC",
            [$subId]
        );
        if (empty($bankQs)) {
            $bankQs = db()->fetchAll("SELECT id FROM questions ORDER BY id ASC LIMIT 20");
        }
        $ord = 1;
        foreach ($bankQs as $bq) {
            db()->execute(
                "INSERT IGNORE INTO exam_questions (exam_id, question_id, display_order) VALUES (?, ?, ?)",
                [$examId, (int)$bq['id'], $ord++]
            );
        }
        $allQIds = db()->fetchAll(
            "SELECT question_id FROM exam_questions WHERE exam_id = ? ORDER BY display_order ASC",
            [$examId]
        );
    }

    $questionOrder = array_column($allQIds, 'question_id');
    if ($exam['random_questions'] && !empty($exam['total_questions'])) {
        $questionOrder = array_slice($questionOrder, 0, (int)$exam['total_questions']);
    }

    $uuid = generateUUID();

    // Create attempt record
    $attemptId = db()->insert(
        "INSERT INTO exam_attempts (uuid, exam_id, student_id, attempt_number, question_order, started_at, status, ip_address, browser_info)
         VALUES (?, ?, ?, ?, ?, NOW(), 'in_progress', ?, ?)",
        [
            $uuid, $examId, $userId,
            (int)$attemptCount['cnt'] + 1,
            json_encode($questionOrder),
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]
    );

    $questions = buildExamQuestions($examId, $questionOrder, $exam['random_options']);

    Logger::audit($userId, 'exam_started', 'exam_attempts', $attemptId, null, ['exam_id' => $examId]);

    Response::success([
        'attempt_id'     => $attemptId,
        'attempt_uuid'   => $uuid,
        'exam'           => $exam,
        'questions'      => $questions,
        'saved_answers'  => [],
        'time_remaining' => (int)$exam['duration_minutes'] * 60,
        'resumed'        => false,
    ], 'Examination started');
}

// ---------------------------------------------------------------
// GET /student/exams/{id} — Exam instructions
// ---------------------------------------------------------------
if ($method === 'GET' && $examId) {
    $exam = db()->fetchOne(
        "SELECT e.*, s.name AS subject_name, s.code AS subject_code,
                u.id AS examiner_user_id,
                CONCAT(ex.first_name, ' ', ex.last_name) AS examiner_name
         FROM exams e
         LEFT JOIN subjects s ON s.id = e.subject_id
         LEFT JOIN examiners ex ON ex.user_id = e.examiner_id
         LEFT JOIN users u ON u.id = e.examiner_id
         WHERE e.id = ?",
        [$examId]
    );
    if (!$exam) {
        Response::error('Examination not found', 404);
    }

    $enrolled = db()->fetchOne(
        "SELECT id FROM exam_enrollments WHERE exam_id = ? AND student_id = ?",
        [$examId, $userId]
    );
    if (!$enrolled && in_array($exam['status'], ['active', 'scheduled'])) {
        db()->execute(
            "INSERT IGNORE INTO exam_enrollments (exam_id, student_id, enrolled_at) VALUES (?, ?, NOW())",
            [$examId, $userId]
        );
        $enrolled = true;
    }

    $attemptsUsed = db()->fetchOne(
        "SELECT COUNT(*) AS cnt FROM exam_attempts WHERE exam_id = ? AND student_id = ? AND status = 'submitted'",
        [$examId, $userId]
    );

    $consentStatus = [];
    if ($exam['proctoring_enabled']) {
        $consents = db()->fetchAll(
            "SELECT consent_type, is_given FROM consents WHERE user_id = ? AND exam_id = ?",
            [$userId, $examId]
        );
        foreach ($consents as $c) {
            $consentStatus[$c['consent_type']] = (bool)$c['is_given'];
        }
    }

    Response::success([
        'exam'           => $exam,
        'is_enrolled'    => (bool)$enrolled,
        'attempts_used'  => (int)$attemptsUsed['cnt'],
        'attempts_left'  => max(0, (int)$exam['allowed_attempts'] - (int)$attemptsUsed['cnt']),
        'consent_status' => $consentStatus,
        'can_start'      => (
            $enrolled &&
            (int)$attemptsUsed['cnt'] < (int)$exam['allowed_attempts'] &&
            time() >= strtotime($exam['start_datetime']) &&
            time() <= strtotime($exam['end_datetime']) &&
            in_array($exam['status'], ['active', 'scheduled'])
        ),
    ]);
}

// ---------------------------------------------------------------
// GET /student/exams — List enrolled & available exams
// ---------------------------------------------------------------
if ($method === 'GET' && !$examId) {
    $search    = InputValidator::sanitizeString($_GET['search'] ?? '', 100);
    $status    = $_GET['status'] ?? '';
    $subjectId = InputValidator::validateInt($_GET['subject_id'] ?? null);
    $page      = max(1, (int)($_GET['page'] ?? 1));
    $perPage   = min(50, max(1, (int)($_GET['per_page'] ?? 12)));
    $offset    = ($page - 1) * $perPage;

    $where  = ["(ee.student_id = ? OR e.status IN ('active', 'scheduled'))"];
    $params = [$userId];

    if ($search) {
        $where[]  = '(e.title LIKE ? OR s.name LIKE ?)';
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    if ($status && in_array($status, ['scheduled','active','completed','cancelled'])) {
        $where[]  = 'e.status = ?';
        $params[] = $status;
    }
    if ($subjectId) {
        $where[]  = 'e.subject_id = ?';
        $params[] = $subjectId;
    }

    $whereSQL = 'WHERE ' . implode(' AND ', $where);

    $total = db()->fetchOne(
        "SELECT COUNT(DISTINCT e.id) AS cnt FROM exams e
         LEFT JOIN exam_enrollments ee ON ee.exam_id = e.id AND ee.student_id = ?
         LEFT JOIN subjects s ON s.id = e.subject_id
         {$whereSQL}",
        array_merge([$userId], $params)
    );

    $exams = db()->fetchAll(
        "SELECT DISTINCT e.id, e.uuid, e.title, e.description, e.start_datetime, e.end_datetime,
                e.duration_minutes, e.total_marks, e.passing_percentage, e.status,
                e.proctoring_enabled, e.allowed_attempts,
                s.name AS subject_name,
                (SELECT COUNT(*) FROM exam_attempts ea
                 WHERE ea.exam_id = e.id AND ea.student_id = ? AND ea.status = 'submitted') AS attempts_used
         FROM exams e
         LEFT JOIN exam_enrollments ee ON ee.exam_id = e.id AND ee.student_id = ?
         LEFT JOIN subjects s ON s.id = e.subject_id
         {$whereSQL}
         ORDER BY e.start_datetime DESC
         LIMIT ? OFFSET ?",
        array_merge([$userId, $userId], $params, [$perPage, $offset])
    );

    Response::paginated($exams, (int)$total['cnt'], $page, $perPage);
}

// ---------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------
function buildExamQuestions(int $examId, array $questionOrder, bool $randomOptions): array {
    if (empty($questionOrder)) {
        $rows = db()->fetchAll(
            "SELECT q.id, q.question_type, q.question_text, q.question_image_path,
                    q.marks, q.negative_marks, q.difficulty
             FROM exam_questions eq
             JOIN questions q ON q.id = eq.question_id
             WHERE eq.exam_id = ?
             ORDER BY eq.display_order ASC",
            [$examId]
        );
    } else {
        $placeholders = implode(',', array_fill(0, count($questionOrder), '?'));
        $rows = db()->fetchAll(
            "SELECT q.id, q.question_type, q.question_text, q.question_image_path,
                    q.marks, q.negative_marks, q.difficulty
             FROM questions q
             WHERE q.id IN ({$placeholders})",
            $questionOrder
        );
        // Sort by $questionOrder
        $indexed = array_column($rows, null, 'id');
        $rows = array_map(fn($id) => $indexed[$id] ?? null, $questionOrder);
        $rows = array_filter($rows);
        $rows = array_values($rows);
    }

    // Attach options (without revealing correct answer to student)
    foreach ($rows as &$q) {
        if (in_array($q['question_type'], ['mcq', 'multiple_correct', 'true_false'])) {
            $options = db()->fetchAll(
                "SELECT id, option_text, option_image_path, display_order FROM question_options
                 WHERE question_id = ? ORDER BY display_order ASC",
                [(int)$q['id']]
            );
            if ($randomOptions) {
                shuffle($options);
            }
            $q['options'] = $options;
        }
    }
    unset($q);

    return $rows;
}

function generateUUID(): string {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
