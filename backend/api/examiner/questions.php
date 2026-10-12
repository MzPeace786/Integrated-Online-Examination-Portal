<?php
/**
 * GET/POST/PUT/DELETE /api/examiner/questions
 * Includes:
 * - POST /api/examiner/questions/suggest      — Automated question generator / suggester
 * - POST /api/examiner/questions/bulk-import  — Bulk import suggested questions to bank/exam
 */
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../utils/QuestionGenerator.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/InputValidator.php';

$user = Auth::authorize(['examiner', 'admin']);
$userId = (int)$user['user_id'];
$isAdmin = ($user['role'] === 'admin');
$method = $_SERVER['REQUEST_METHOD'];

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

global $_ROUTE;
$qId = isset($_ROUTE['id']) ? (int)$_ROUTE['id'] : null;

// -------------------------------------------------------------------
// 1. POST /api/examiner/questions/suggest — AI / Automated Suggestion
// -------------------------------------------------------------------
if ($method === 'POST' && str_contains($uri, '/suggest')) {
    $body = InputValidator::getRequestBody();
    $subjectId = InputValidator::validateInt($body['subject_id'] ?? null);
    $subjectName = InputValidator::sanitizeString($body['subject_name'] ?? '', 200);

    if ($subjectId && empty($subjectName)) {
        $sub = db()->fetchOne("SELECT name FROM subjects WHERE id = ?", [$subjectId]);
        if ($sub) $subjectName = $sub['name'];
    }
    if (empty($subjectName)) {
        $subjectName = 'Computer Science & Engineering';
    }

    $topicDetails = InputValidator::sanitizeString($body['topic'] ?? $body['topic_details'] ?? '', 500);
    $difficulty = in_array($body['difficulty'] ?? '', ['easy', 'medium', 'hard', 'mixed']) ? $body['difficulty'] : 'mixed';
    $qType = in_array($body['question_type'] ?? '', ['mcq', 'multiple_correct', 'true_false', 'short_answer', 'coding', 'all']) ? $body['question_type'] : 'all';
    $count = InputValidator::validateInt($body['count'] ?? 5, 1, 30) ?? 5;

    $suggested = QuestionGenerator::suggestQuestions($subjectName, $topicDetails, $difficulty, $qType, $count);
    Response::success($suggested, 'Questions generated successfully based on ' . htmlspecialchars($subjectName));
}

// -------------------------------------------------------------------
// 2. POST /api/examiner/questions/bulk-import — Bulk Import Questions
// -------------------------------------------------------------------
if ($method === 'POST' && str_contains($uri, '/bulk-import')) {
    $body = InputValidator::getRequestBody();
    $questions = $body['questions'] ?? [];

    if (empty($questions) || !is_array($questions)) {
        Response::error('No questions provided for import', 422);
    }

    $bankId = InputValidator::validateInt($body['question_bank_id'] ?? null);
    $subjectId = InputValidator::validateInt($body['subject_id'] ?? null);
    $examId = InputValidator::validateInt($body['exam_id'] ?? null);

    // If no bank specified, create or assign default bank
    if (!$bankId) {
        $bankName = InputValidator::sanitizeString($body['bank_name'] ?? ('Question Bank ' . date('M d, Y')), 200);
        $bankId = db()->insert(
            "INSERT INTO question_banks (name, description, subject_id, examiner_id, is_active, created_at)
             VALUES (?, 'Generated via Automation Question Bank', ?, ?, 1, NOW())",
            [$bankName, $subjectId, $userId]
        );
    }

    db()->beginTransaction();
    $importedIds = [];

    try {
        $displayOrder = 1;
        if ($examId) {
            $lastOrder = db()->fetchOne("SELECT MAX(display_order) AS max_o FROM exam_questions WHERE exam_id = ?", [$examId]);
            $displayOrder = ($lastOrder['max_o'] ?? 0) + 1;
        }

        foreach ($questions as $q) {
            $topic = InputValidator::sanitizeString($q['topic'] ?? '', 200);
            $qType = $q['question_type'] ?? 'mcq';
            $qText = InputValidator::sanitizeHTML($q['question_text'] ?? '');
            $diff = in_array($q['difficulty'] ?? '', ['easy', 'medium', 'hard']) ? $q['difficulty'] : 'medium';
            $marks = (float)($q['marks'] ?? 1.0);
            $explanation = InputValidator::sanitizeHTML($q['explanation'] ?? '');

            if (empty($qText)) continue;

            $newQId = db()->insert(
                "INSERT INTO questions (question_bank_id, subject_id, topic, question_type, question_text,
                    difficulty, marks, negative_marks, explanation, is_active, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 0.00, ?, 1, ?, NOW())",
                [$bankId, $subjectId, $topic ?: null, $qType, $qText, $diff, $marks, $explanation ?: null, $userId]
            );

            // Save Options
            if (!empty($q['options']) && is_array($q['options'])) {
                $optOrd = 1;
                foreach ($q['options'] as $opt) {
                    $optText = is_array($opt) ? ($opt['text'] ?? $opt['option_text'] ?? '') : (string)$opt;
                    $isCorr = is_array($opt) ? (!empty($opt['is_correct']) ? 1 : 0) : 0;
                    if (!empty($optText)) {
                        db()->insert(
                            "INSERT INTO question_options (question_id, option_text, is_correct, display_order)
                             VALUES (?, ?, ?, ?)",
                            [$newQId, trim($optText), $isCorr, $optOrd++]
                        );
                    }
                }
            }

            // Save Test Cases
            if ($qType === 'coding' && !empty($q['test_cases']) && is_array($q['test_cases'])) {
                foreach ($q['test_cases'] as $tc) {
                    $in = $tc['input'] ?? $tc['input_data'] ?? '';
                    $out = $tc['output'] ?? $tc['expected_output'] ?? '';
                    $isH = !empty($tc['is_hidden']) ? 1 : 0;
                    if ($out !== '') {
                        db()->insert(
                            "INSERT INTO coding_test_cases (question_id, input_data, expected_output, is_hidden, marks, created_at)
                             VALUES (?, ?, ?, ?, 1.00, NOW())",
                            [$newQId, $in, $out, $isH]
                        );
                    }
                }
            }

            // If exam_id provided, link directly to exam
            if ($examId) {
                db()->execute(
                    "INSERT IGNORE INTO exam_questions (exam_id, question_id, display_order) VALUES (?, ?, ?)",
                    [$examId, $newQId, $displayOrder++]
                );
            }

            $importedIds[] = $newQId;
        }

        db()->commit();
        Logger::audit($userId, 'questions_bulk_imported', 'questions', null, null, [
            'count' => count($importedIds),
            'bank_id' => $bankId,
            'exam_id' => $examId
        ]);

        Response::created([
            'imported_count' => count($importedIds),
            'question_ids' => $importedIds,
            'question_bank_id' => $bankId,
            'exam_id' => $examId
        ], 'Successfully saved ' . count($importedIds) . ' questions to Question Bank!');

    } catch (Exception $e) {
        db()->rollback();
        Response::error('Failed to import questions: ' . $e->getMessage(), 500);
    }
}

// -------------------------------------------------------------------
// 3. GET single question
// -------------------------------------------------------------------
if ($method === 'GET' && $qId) {
    $q = db()->fetchOne("SELECT * FROM questions WHERE id = ? AND is_deleted = 0", [$qId]);
    if (!$q) Response::error('Question not found', 404);
    $q['options'] = db()->fetchAll("SELECT * FROM question_options WHERE question_id = ? ORDER BY display_order ASC", [$qId]);
    $q['test_cases'] = db()->fetchAll("SELECT * FROM coding_test_cases WHERE question_id = ?", [$qId]);
    Response::success($q);
}

// -------------------------------------------------------------------
// 4. POST /examiner/questions — Create single question
// -------------------------------------------------------------------
if ($method === 'POST') {
    $body = InputValidator::getRequestBody();
    $bankId = InputValidator::validateInt($body['question_bank_id'] ?? null);
    $subjectId = InputValidator::validateInt($body['subject_id'] ?? null);
    $topic = InputValidator::sanitizeString($body['topic'] ?? '', 200);
    $qType = $body['question_type'] ?? 'mcq';
    $qText = InputValidator::sanitizeHTML($body['question_text'] ?? '');
    $difficulty = in_array($body['difficulty'] ?? '', ['easy', 'medium', 'hard']) ? $body['difficulty'] : 'medium';
    $marks = InputValidator::validateFloat($body['marks'] ?? 1.0, 0.1);
    $negMarks = InputValidator::validateFloat($body['negative_marks'] ?? 0.0, 0.0);
    $explanation = InputValidator::sanitizeHTML($body['explanation'] ?? '');
    $tags = !empty($body['tags']) ? (is_array($body['tags']) ? json_encode($body['tags']) : json_encode(explode(',', $body['tags']))) : null;

    $allowedTypes = ['mcq', 'multiple_correct', 'true_false', 'fill_blank', 'short_answer', 'coding'];
    if (!in_array($qType, $allowedTypes)) {
        Response::error('Invalid question type', 422);
    }
    if (empty($qText)) {
        Response::error('Question text is required', 422);
    }

    db()->beginTransaction();
    try {
        $id = db()->insert(
            "INSERT INTO questions (question_bank_id, subject_id, topic, question_type, question_text,
                difficulty, marks, negative_marks, explanation, tags, is_active, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())",
            [$bankId, $subjectId, $topic ?: null, $qType, $qText, $difficulty, $marks, $negMarks, $explanation ?: null, $tags, $userId]
        );

        if (in_array($qType, ['mcq', 'multiple_correct', 'true_false']) && !empty($body['options']) && is_array($body['options'])) {
            $order = 1;
            foreach ($body['options'] as $opt) {
                $optText = is_array($opt) ? ($opt['text'] ?? $opt['option_text'] ?? '') : (string)$opt;
                $isCorrect = is_array($opt) ? (!empty($opt['is_correct']) ? 1 : 0) : 0;
                if (!empty($optText)) {
                    db()->insert(
                        "INSERT INTO question_options (question_id, option_text, is_correct, display_order)
                         VALUES (?, ?, ?, ?)",
                        [$id, trim($optText), $isCorrect, $order++]
                    );
                }
            }
        }

        if ($qType === 'coding' && !empty($body['test_cases']) && is_array($body['test_cases'])) {
            foreach ($body['test_cases'] as $tc) {
                $inData = $tc['input_data'] ?? $tc['input'] ?? '';
                $expOut = $tc['expected_output'] ?? $tc['output'] ?? '';
                $isHidden = !empty($tc['is_hidden']) ? 1 : 0;
                $tcMarks = (float)($tc['marks'] ?? 1.0);
                if ($expOut !== '') {
                    db()->insert(
                        "INSERT INTO coding_test_cases (question_id, input_data, expected_output, is_hidden, marks, created_at)
                         VALUES (?, ?, ?, ?, ?, NOW())",
                        [$id, $inData, $expOut, $isHidden, $tcMarks]
                    );
                }
            }
        }

        // Check if an exam_id was provided
        $examId = InputValidator::validateInt($body['exam_id'] ?? null);
        if ($examId) {
            $lastOrder = db()->fetchOne("SELECT MAX(display_order) AS max_o FROM exam_questions WHERE exam_id = ?", [$examId]);
            $ord = ($lastOrder['max_o'] ?? 0) + 1;
            db()->execute(
                "INSERT IGNORE INTO exam_questions (exam_id, question_id, display_order) VALUES (?, ?, ?)",
                [$examId, $id, $ord]
            );
        }

        db()->commit();
        Logger::audit($userId, 'question_created', 'questions', $id, null, ['type' => $qType]);
        Response::created(['question_id' => $id], 'Question created successfully');

    } catch (Exception $e) {
        db()->rollback();
        Response::error('Failed to create question: ' . $e->getMessage(), 500);
    }
}

// -------------------------------------------------------------------
// 5. DELETE /examiner/questions/{id}
// -------------------------------------------------------------------
if ($method === 'DELETE' && $qId) {
    db()->execute("UPDATE questions SET is_deleted = 1 WHERE id = ?", [$qId]);
    Logger::audit($userId, 'question_deleted', 'questions', $qId);
    Response::success(null, 'Question deleted successfully');
}
