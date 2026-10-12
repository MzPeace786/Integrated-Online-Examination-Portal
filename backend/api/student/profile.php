<?php
/**
 * GET/POST /api/student/profile
 */
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/InputValidator.php';

$user = Auth::authorize(['student']);
$userId = (int)$user['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $student = db()->fetchOne(
        "SELECT s.*, u.email, u.created_at AS member_since FROM students s
         JOIN users u ON u.id = s.user_id
         WHERE s.user_id = ?",
        [$userId]
    );
    if (!$student) Response::error('Student record not found', 404);
    Response::success($student);
}

if ($method === 'POST' || $method === 'PUT') {
    $body = InputValidator::getRequestBody();
    $firstName = InputValidator::sanitizeString($body['first_name'] ?? '', 100);
    $lastName  = InputValidator::sanitizeString($body['last_name'] ?? '', 100);
    $phone     = InputValidator::sanitizeString($body['phone'] ?? '', 20);
    $dept      = InputValidator::sanitizeString($body['department'] ?? '', 200);
    $semester  = InputValidator::validateInt($body['semester'] ?? null, 1, 12);
    $bio       = InputValidator::sanitizeString($body['bio'] ?? '', 1000);
    $address   = InputValidator::sanitizeString($body['address'] ?? '', 500);

    if (empty($firstName) || empty($lastName)) {
        Response::error('First name and last name are required', 422);
    }

    $photoPath = null;
    if (!empty($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_photo'];
        if ($file['size'] <= MAX_UPLOAD_SIZE && in_array(mime_content_type($file['tmp_name']), ALLOWED_IMAGE_TYPES)) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = 'student_' . $userId . '_' . time() . '.' . $ext;
            $dest = UPLOAD_PROFILES . $fileName;
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $photoPath = '/exam-portal/uploads/profiles/' . $fileName;
            }
        }
    }

    $params = [$firstName, $lastName, $phone ?: null, $dept ?: null, $semester, $bio ?: null, $address ?: null];
    $sql = "UPDATE students SET first_name=?, last_name=?, phone=?, department=?, semester=?, bio=?, address=?";
    if ($photoPath) {
        $sql .= ", profile_photo_path=?";
        $params[] = $photoPath;
    }
    $sql .= " WHERE user_id=?";
    $params[] = $userId;

    db()->execute($sql, $params);
    Logger::audit($userId, 'profile_updated', 'students', $userId);

    Response::success(null, 'Profile updated successfully');
}
