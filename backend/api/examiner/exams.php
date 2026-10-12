<?php
/**
 * GET/POST/PUT/DELETE /api/examiner/exams
 */
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../utils/NotificationHelper.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/InputValidator.php';

$user = Auth::authorize(['examiner', 'admin']);
$userId = (int)$user['user_id'];
$isAdmin = ($user['role'] === 'admin');
$method = $_SERVER['REQUEST_METHOD'];

global $_ROUTE;
$examId = isset($_ROUTE['id']) ? (int)$_ROUTE['id'] : null;
$action = $_ROUTE['action'] ?? null;
$subId  = isset($_ROUTE['sub_id']) ? (int)$_ROUTE['sub_id'] : null;

// 1. GET /examiner/exams/{id}/attempts
if ($method === 'GET' && $examId && $action === 'attempts') {
    $attempts = db()->fetchAll(
        "SELECT ea.*, CONCAT(s.first_name, ' ', s.last_name) AS student_name, s.student_id_number,
                r.obtained_marks, r.percentage, r.is_passed, ps.risk_score, ps.total_events
         FROM exam_attempts ea
         JOIN students s ON s.user_id = ea.student_id
         LEFT JOIN results r ON r.attempt_id = ea.id
         LEFT JOIN proctoring_sessions ps ON ps.attempt_id = ea.id
         WHERE ea.exam_id = ?
         ORDER BY ea.started_at DESC",
        [$examId]
    );
    Response::success($attempts);
}

// 2. GET /examiner/exams/{id}/questions
if ($method === 'GET' && $examId && $action === 'questions') {
    $questions = db()->fetchAll(
        "SELECT q.*, eq.display_order, eq.marks_override
         FROM exam_questions eq
         JOIN questions q ON q.id = eq.question_id
         WHERE eq.exam_id = ?
         ORDER BY eq.display_order ASC",
        [$examId]
    );
    Response::success($questions);
}

// 3. POST /examiner/exams/{id}/enroll — Enroll students
if ($method === 'POST' && $examId && $action === 'enroll') {
    $body = InputValidator::getRequestBody();
    $studentIds = $body['student_ids'] ?? [];
    if (empty($studentIds) || !is_array($studentIds)) {
        Response::error('student_ids must be a non-empty array of user IDs', 422);
    }

    $exam = db()->fetchOne("SELECT title, start_datetime FROM exams WHERE id = ?", [$examId]);
    $count = 0;
    foreach ($studentIds as $sId) {
        $sId = (int)$sId;
        try {
            db()->execute(
                "INSERT IGNORE INTO exam_enrollments (exam_id, student_id, enrolled_at, enrolled_by)
                 VALUES (?, ?, NOW(), ?)",
                [$examId, $sId, $userId]
            );
            NotificationHelper::examScheduled($sId, $examId, $exam['title'], $exam['start_datetime']);
            $count++;
        } catch (Exception $e) {}
    }
    Response::success(['enrolled_count' => $count], "Successfully enrolled {$count} student(s)");
}

// 4. GET /examiner/exams/{id} — Single Exam Detail
if ($method === 'GET' && $examId && !$action) {
    $exam = db()->fetchOne(
        "SELECT e.*, s.name AS subject_name, c.name AS course_name
         FROM exams e
         LEFT JOIN subjects s ON s.id = e.subject_id
         LEFT JOIN courses c ON c.id = s.course_id
         WHERE e.id = ?" . (!$isAdmin ? " AND e.examiner_id = ?" : ""),
        $isAdmin ? [$examId] : [$examId, $userId]
    );
    if (!$exam) Response::error('Exam not found', 404);
    Response::success($exam);
}

// 5. GET /examiner/exams — List Exams
if ($method === 'GET' && !$examId) {
    $search = InputValidator::sanitizeString($_GET['search'] ?? '', 100);
    $status = $_GET['status'] ?? '';
    $where = $isAdmin ? ['1=1'] : ['e.examiner_id = ?'];
    $params = $isAdmin ? [] : [$userId];

    if ($search) {
        $where[] = '(e.title LIKE ? OR s.name LIKE ?)';
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    if ($status) {
        $where[] = 'e.status = ?';
        $params[] = $status;
    }

    $sql = "SELECT e.*, s.name AS subject_name,
                   (SELECT COUNT(*) FROM exam_questions WHERE exam_id = e.id) AS assigned_questions,
                   (SELECT COUNT(*) FROM exam_enrollments WHERE exam_id = e.id) AS enrolled_count,
                   (SELECT COUNT(*) FROM exam_attempts WHERE exam_id = e.id AND status = 'submitted') AS submitted_count
            FROM exams e
            LEFT JOIN subjects s ON s.id = e.subject_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY e.created_at DESC";

    $exams = db()->fetchAll($sql, $params);
    Response::success($exams);
}

// 6. POST /examiner/exams — Create Exam
if ($method === 'POST' && !$examId) {
    $body = InputValidator::getRequestBody();
    $title = InputValidator::sanitizeString($body['title'] ?? '', 300);
    $desc = InputValidator::sanitizeString($body['description'] ?? '', 1000);
    $instructions = InputValidator::sanitizeString($body['instructions'] ?? '', 2000);
    $subjectId = InputValidator::validateInt($body['subject_id'] ?? null);
    $examType = $body['exam_type'] ?? 'objective';
    $start = $body['start_datetime'] ?? null;
    $end = $body['end_datetime'] ?? null;
    $duration = InputValidator::validateInt($body['duration_minutes'] ?? 60, 1, 720);
    $totalMarks = InputValidator::validateFloat($body['total_marks'] ?? 100, 0);
    $marksPerQ = InputValidator::validateFloat($body['marks_per_question'] ?? 1, 0.1);
    $negEnabled = !empty($body['negative_marking_enabled']) ? 1 : 0;
    $negMarks = InputValidator::validateFloat($body['negative_marks_per_question'] ?? 0, 0);
    $passPct = InputValidator::validateFloat($body['passing_percentage'] ?? 40, 1, 100);
    $attempts = InputValidator::validateInt($body['allowed_attempts'] ?? 1, 1, 10);
    $randQ = !empty($body['random_questions']) ? 1 : 0;
    $randOpt = !empty($body['random_options']) ? 1 : 0;
    $proctoring = !empty($body['proctoring_enabled']) ? 1 : 0;
    $fullscreen = !empty($body['fullscreen_required']) ? 1 : 0;
    $status = in_array($body['status'] ?? '', ['draft', 'scheduled', 'active']) ? $body['status'] : 'draft';

    if (empty($title) || !$start || !$end) {
        Response::error('Title, start date/time and end date/time are required', 422);
    }

    $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );

    $id = db()->insert(
        "INSERT INTO exams (uuid, title, description, instructions, subject_id, examiner_id, exam_type,
            start_datetime, end_datetime, duration_minutes, total_marks, marks_per_question,
            negative_marking_enabled, negative_marks_per_question, passing_percentage, allowed_attempts,
            random_questions, random_options, proctoring_enabled, fullscreen_required, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$uuid, $title, $desc, $instructions, $subjectId, $userId, $examType, $start, $end, $duration,
         $totalMarks, $marksPerQ, $negEnabled, $negMarks, $passPct, $attempts, $randQ, $randOpt, $proctoring, $fullscreen, $status]
    );

    // Auto-enroll active students (default enabled)
    $autoEnroll = !isset($body['auto_enroll_all']) || !empty($body['auto_enroll_all']);
    if ($autoEnroll) {
        $allStudents = db()->fetchAll("SELECT id FROM users WHERE role = 'student' AND is_active = 1");
        foreach ($allStudents as $st) {
            db()->execute(
                "INSERT IGNORE INTO exam_enrollments (exam_id, student_id, enrolled_at, enrolled_by) VALUES (?, ?, NOW(), ?)",
                [$id, (int)$st['id'], $userId]
            );
            NotificationHelper::examScheduled((int)$st['id'], $id, $title, $start);
        }
    } elseif (!empty($body['student_ids']) && is_array($body['student_ids'])) {
        foreach ($body['student_ids'] as $sId) {
            db()->execute(
                "INSERT IGNORE INTO exam_enrollments (exam_id, student_id, enrolled_at, enrolled_by) VALUES (?, ?, NOW(), ?)",
                [$id, (int)$sId, $userId]
            );
            NotificationHelper::examScheduled((int)$sId, $id, $title, $start);
        }
    }

    Logger::audit($userId, 'exam_created', 'exams', $id, null, ['title' => $title]);
    Response::created(['exam_id' => $id, 'uuid' => $uuid], 'Examination created and enrolled for candidates successfully');
}

// 7. PUT /examiner/exams/{id} — Update Exam
if ($method === 'PUT' && $examId) {
    $body = InputValidator::getRequestBody();
    $exam = db()->fetchOne("SELECT * FROM exams WHERE id = ?" . (!$isAdmin ? " AND examiner_id = ?" : ""),
        $isAdmin ? [$examId] : [$examId, $userId]);
    if (!$exam) Response::error('Exam not found or cannot be edited', 404);

    $title = InputValidator::sanitizeString($body['title'] ?? $exam['title'], 300);
    $desc = InputValidator::sanitizeString($body['description'] ?? $exam['description'], 1000);
    $instructions = InputValidator::sanitizeString($body['instructions'] ?? $exam['instructions'], 2000);
    $start = $body['start_datetime'] ?? $exam['start_datetime'];
    $end = $body['end_datetime'] ?? $exam['end_datetime'];
    $duration = InputValidator::validateInt($body['duration_minutes'] ?? $exam['duration_minutes'], 1, 720);
    $status = in_array($body['status'] ?? '', ['draft', 'scheduled', 'active', 'completed', 'cancelled']) ? $body['status'] : $exam['status'];
    $passPct = InputValidator::validateFloat($body['passing_percentage'] ?? $exam['passing_percentage'], 1, 100);
    $proctoring = isset($body['proctoring_enabled']) ? (!empty($body['proctoring_enabled']) ? 1 : 0) : $exam['proctoring_enabled'];

    db()->execute(
        "UPDATE exams SET title=?, description=?, instructions=?, start_datetime=?, end_datetime=?,
            duration_minutes=?, status=?, passing_percentage=?, proctoring_enabled=? WHERE id=?",
        [$title, $desc, $instructions, $start, $end, $duration, $status, $passPct, $proctoring, $examId]
    );

    Logger::audit($userId, 'exam_updated', 'exams', $examId, $exam, ['title' => $title, 'status' => $status]);
    Response::success(null, 'Exam updated successfully');
}

// 8. DELETE /examiner/exams/{id}
if ($method === 'DELETE' && $examId) {
    $exam = db()->fetchOne("SELECT * FROM exams WHERE id = ?" . (!$isAdmin ? " AND examiner_id = ?" : ""),
        $isAdmin ? [$examId] : [$examId, $userId]);
    if (!$exam) Response::error('Exam not found', 404);

    db()->execute("DELETE FROM exams WHERE id = ?", [$examId]);
    Logger::audit($userId, 'exam_deleted', 'exams', $examId);
    Response::success(null, 'Exam deleted successfully');
}
