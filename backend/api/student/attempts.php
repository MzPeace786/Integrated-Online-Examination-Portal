<?php
/**
 * POST /api/student/attempts/{id}/answer  — Auto-save a student answer
 * POST /api/student/attempts/{id}/submit  — Submit the exam attempt
 * GET  /api/student/attempts/{id}         — Get attempt status
 */
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../utils/CertificateGenerator.php';
require_once __DIR__ . '/../../utils/NotificationHelper.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/InputValidator.php';

$user      = Auth::authorize(['student']);
$userId    = (int)$user['user_id'];
$method    = $_SERVER['REQUEST_METHOD'];

global $_ROUTE;
$attemptId = isset($_ROUTE['id']) ? (int)$_ROUTE['id'] : null;
$action    = $_ROUTE['action'] ?? null;

if (!$attemptId) {
    Response::error('Attempt ID required', 400);
}

// Verify attempt belongs to this student
$attempt = db()->fetchOne(
    "SELECT ea.*, e.duration_minutes, e.total_marks, e.passing_percentage,
            e.negative_marking_enabled, e.negative_marks_per_question,
            e.marks_per_question, e.proctoring_enabled
     FROM exam_attempts ea
     JOIN exams e ON e.id = ea.exam_id
     WHERE ea.id = ? AND ea.student_id = ?",
    [$attemptId, $userId]
);

if (!$attempt) {
    Response::error('Attempt not found or access denied', 404);
}

// ---------------------------------------------------------------
// POST /student/attempts/{id}/answer — Save answer (auto-save)
// ---------------------------------------------------------------
if ($method === 'POST' && $action === 'answer') {
    if ($attempt['status'] !== 'in_progress') {
        Response::error('This examination has already been submitted', 403);
    }

    // Check time hasn't expired
    $elapsed = time() - strtotime($attempt['started_at']);
    if ($elapsed > (int)$attempt['duration_minutes'] * 60 + 60) { // 60s grace period
        Response::error('Examination time has expired', 403);
    }

    $body = InputValidator::getRequestBody();
    $questionId  = (int)($body['question_id'] ?? 0);
    if (!$questionId) {
        Response::error('question_id is required', 422);
    }

    $selectedOptionIds  = $body['selected_option_ids'] ?? null;   // array or null
    $textAnswer         = $body['text_answer'] ?? null;
    $codeAnswer         = $body['code_answer'] ?? null;
    $language           = InputValidator::sanitizeString($body['language'] ?? '', 50);
    $isMarkedForReview  = !empty($body['is_marked_for_review']);
    $timeSpent          = InputValidator::validateInt($body['time_spent_seconds'] ?? null, 0, 86400);

    // Encode option IDs safely
    $optionIdsJson = null;
    if ($selectedOptionIds !== null) {
        if (is_array($selectedOptionIds)) {
            $optionIdsJson = json_encode(array_map('intval', $selectedOptionIds));
        } else {
            $optionIdsJson = json_encode([(int)$selectedOptionIds]);
        }
    }

    // Upsert answer record
    db()->execute(
        "INSERT INTO student_answers
             (attempt_id, question_id, selected_option_ids, text_answer, code_answer, language, is_marked_for_review, time_spent_seconds, saved_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
             selected_option_ids = VALUES(selected_option_ids),
             text_answer = VALUES(text_answer),
             code_answer = VALUES(code_answer),
             language = VALUES(language),
             is_marked_for_review = VALUES(is_marked_for_review),
             time_spent_seconds = VALUES(time_spent_seconds),
             saved_at = NOW()",
        [$attemptId, $questionId, $optionIdsJson, $textAnswer, $codeAnswer, $language ?: null,
         $isMarkedForReview ? 1 : 0, $timeSpent]
    );

    Response::success(['saved' => true, 'timestamp' => date('Y-m-d H:i:s')], 'Answer saved');
}

// ---------------------------------------------------------------
// POST /student/attempts/{id}/submit — Submit exam
// ---------------------------------------------------------------
if ($method === 'POST' && ($action === 'submit' || !$action)) {
    if ($attempt['status'] === 'submitted') {
        Response::error('This examination has already been submitted', 409);
    }

    $body         = InputValidator::getRequestBody();
    $autoSubmit   = !empty($body['auto_submit']);

    db()->beginTransaction();
    try {
        // Mark attempt as submitted
        $timeTaken = time() - strtotime($attempt['started_at']);
        db()->execute(
            "UPDATE exam_attempts SET status = 'submitted', submitted_at = NOW(),
             auto_submitted = ?, time_taken_seconds = ?
             WHERE id = ?",
            [$autoSubmit ? 1 : 0, $timeTaken, $attemptId]
        );

        $autoSubmit            = !empty($body['auto_submit']);
        $malpracticeTerminated = !empty($body['malpractice_terminated']);
        $malpracticeReason     = InputValidator::sanitizeString($body['malpractice_reason'] ?? '', 500);

        // Evaluate answers
        $result = evaluateAttempt($attempt, $attemptId);
        if ($malpracticeTerminated) {
            $result['is_passed'] = false;
        }

        // Store result
        $resultId = db()->insert(
            "INSERT INTO results
                 (attempt_id, student_id, exam_id, total_questions, attempted_questions,
                  correct_answers, incorrect_answers, unanswered_questions,
                  total_marks, obtained_marks, negative_marks_deducted, percentage,
                  is_passed, time_taken_seconds, answer_details, evaluated_at, is_published, published_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1, NOW())",
            [
                $attemptId, $userId, (int)$attempt['exam_id'],
                $result['total_questions'], $result['attempted_questions'],
                $result['correct'], $result['incorrect'], $result['unanswered'],
                $result['total_marks'], $result['obtained_marks'], $result['negative_deducted'],
                $result['percentage'], $result['is_passed'] ? 1 : 0,
                $timeTaken, json_encode($result['answer_details']),
            ]
        );

        // Generate certificate if passed and not disqualified
        $certNumber = null;
        if ($result['is_passed'] && !$malpracticeTerminated) {
            $studentInfo = db()->fetchOne(
                "SELECT CONCAT(s.first_name, ' ', s.last_name) AS name
                 FROM students s WHERE s.user_id = ?",
                [$userId]
            );
            $examInfo = db()->fetchOne("SELECT title, total_marks FROM exams WHERE id = ?", [(int)$attempt['exam_id']]);

            $certData = CertificateGenerator::generate([
                'student_name'    => $studentInfo['name'] ?? 'Student',
                'exam_name'       => $examInfo['title'] ?? 'Examination',
                'score'           => $result['obtained_marks'],
                'total_marks'     => $result['total_marks'],
                'percentage'      => $result['percentage'],
                'completion_date' => date('F d, Y'),
                'institution_name'=> 'ExamPortal',
            ]);

            $certUuid = generateUUID2();
            db()->insert(
                "INSERT INTO certificates (uuid, student_id, exam_id, result_id, certificate_number, qr_code_data)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$certUuid, $userId, (int)$attempt['exam_id'], $resultId,
                 $certData['certificate_number'], $certData['qr_data']]
            );
            $certNumber = $certData['certificate_number'];

            NotificationHelper::certificateGenerated($userId, $resultId, $examInfo['title'] ?? 'Examination');
        }

        // Send result notification
        $examTitle = db()->fetchOne("SELECT title FROM exams WHERE id = ?", [(int)$attempt['exam_id']]);
        NotificationHelper::resultPublished(
            $userId, (int)$attempt['exam_id'],
            $examTitle['title'] ?? 'Examination',
            $result['percentage'],
            $result['is_passed'] && !$malpracticeTerminated
        );

        // End proctoring session if active
        db()->execute(
            "UPDATE proctoring_sessions SET status = 'ended', ended_at = NOW(), risk_score = ?
             WHERE attempt_id = ?",
            [$malpracticeTerminated ? 100 : 0, $attemptId]
        );

        db()->commit();

        Logger::audit($userId, 'exam_submitted', 'exam_attempts', $attemptId, null, [
            'exam_id' => $attempt['exam_id'], 'auto' => $autoSubmit, 'score' => $result['percentage'],
        ]);

        Response::success([
            'result_id'          => $resultId,
            'obtained_marks'     => $result['obtained_marks'],
            'total_marks'        => $result['total_marks'],
            'percentage'         => $result['percentage'],
            'is_passed'          => $result['is_passed'],
            'correct_answers'    => $result['correct'],
            'incorrect_answers'  => $result['incorrect'],
            'unanswered'         => $result['unanswered'],
            'certificate_number' => $certNumber,
        ], 'Examination submitted successfully');

    } catch (Throwable $e) {
        db()->rollback();
        Logger::error('Exam submission failed: ' . $e->getMessage(), ['attempt_id' => $attemptId]);
        Response::error('Submission failed. Please try again.', 500);
    }
}

// ---------------------------------------------------------------
// GET /student/attempts/{id} — Get attempt status
// ---------------------------------------------------------------
if ($method === 'GET') {
    $savedAnswers = db()->fetchAll(
        "SELECT question_id, selected_option_ids, text_answer, is_marked_for_review FROM student_answers WHERE attempt_id = ?",
        [$attemptId]
    );
    $elapsed   = time() - strtotime($attempt['started_at']);
    $remaining = max(0, (int)$attempt['duration_minutes'] * 60 - $elapsed);

    Response::success([
        'attempt'        => $attempt,
        'saved_answers'  => $savedAnswers,
        'time_remaining' => $remaining,
    ]);
}

// ---------------------------------------------------------------
// Evaluation helper
// ---------------------------------------------------------------
function evaluateAttempt(array $attempt, int $attemptId): array {
    $examId = (int)$attempt['exam_id'];

    // Get all exam questions with correct answers
    $questions = db()->fetchAll(
        "SELECT q.id, q.question_type, q.marks, q.negative_marks,
                GROUP_CONCAT(CASE WHEN qo.is_correct = 1 THEN qo.id END) AS correct_option_ids
         FROM exam_questions eq
         JOIN questions q ON q.id = eq.question_id
         LEFT JOIN question_options qo ON qo.question_id = q.id
         WHERE eq.exam_id = ?
         GROUP BY q.id, q.question_type, q.marks, q.negative_marks",
        [$examId]
    );

    // Get student answers
    $answers = db()->fetchAll(
        "SELECT question_id, selected_option_ids, text_answer FROM student_answers WHERE attempt_id = ?",
        [$attemptId]
    );
    $answerMap = array_column($answers, null, 'question_id');

    $correct          = 0;
    $incorrect        = 0;
    $unanswered       = 0;
    $obtainedMarks    = 0.0;
    $negativeDeducted = 0.0;
    $answerDetails    = [];

    foreach ($questions as $q) {
        $qId       = (int)$q['id'];
        $marks     = (float)$q['marks'];
        $negMarks  = (float)$q['negative_marks'];
        $answer    = $answerMap[$qId] ?? null;
        $status    = 'unanswered';
        $earned    = 0.0;

        $correctIds = $q['correct_option_ids']
            ? array_map('intval', explode(',', $q['correct_option_ids']))
            : [];

        if (!$answer || (!$answer['selected_option_ids'] && !$answer['text_answer'])) {
            $unanswered++;
        } elseif (in_array($q['question_type'], ['mcq', 'multiple_correct', 'true_false'])) {
            $selected = json_decode($answer['selected_option_ids'], true) ?? [];
            sort($selected);
            sort($correctIds);
            if ($selected === $correctIds) {
                $correct++;
                $earned = $marks;
                $obtainedMarks += $marks;
                $status = 'correct';
            } else {
                $incorrect++;
                if ($attempt['negative_marking_enabled'] && $negMarks > 0) {
                    $negativeDeducted += $negMarks;
                    $obtainedMarks    -= $negMarks;
                }
                $status = 'incorrect';
            }
        } else {
            // Fill blank / short answer — mark as attempted, manual review needed
            $correct++;
            $earned = $marks;
            $obtainedMarks += $marks;
            $status = 'attempted';
        }

        $answerDetails[] = [
            'question_id'    => $qId,
            'status'         => $status,
            'marks_earned'   => $earned,
            'student_answer' => $answer['selected_option_ids'] ?? $answer['text_answer'] ?? null,
            'correct_ids'    => $correctIds,
        ];
    }

    $totalMarks  = (float)$attempt['total_marks'];
    $obtainedMarks = max(0, $obtainedMarks);
    $percentage  = $totalMarks > 0 ? round($obtainedMarks / $totalMarks * 100, 2) : 0;
    $isPassed    = $percentage >= (float)$attempt['passing_percentage'];

    return [
        'total_questions'  => count($questions),
        'attempted_questions' => $correct + $incorrect,
        'correct'          => $correct,
        'incorrect'        => $incorrect,
        'unanswered'       => $unanswered,
        'total_marks'      => $totalMarks,
        'obtained_marks'   => round($obtainedMarks, 2),
        'negative_deducted'=> round($negativeDeducted, 2),
        'percentage'       => $percentage,
        'is_passed'        => $isPassed,
        'answer_details'   => $answerDetails,
    ];
}

function generateUUID2(): string {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
