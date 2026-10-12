<?php
/**
 * GET /api/certificate/verify?cert_number=CERT-2026-XXXXXXXX
 * Public verification endpoint
 */
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/InputValidator.php';

$certNumber = InputValidator::sanitizeString($_GET['cert_number'] ?? $_GET['cert'] ?? '', 50);

if (empty($certNumber)) {
    Response::error('Certificate number is required', 422);
}

$cert = db()->fetchOne(
    "SELECT c.certificate_number, c.issued_at, c.revoked_at, c.revoke_reason,
            e.title AS exam_name, e.total_marks, r.obtained_marks, r.percentage,
            CONCAT(s.first_name, ' ', s.last_name) AS student_name, s.institution, s.department
     FROM certificates c
     JOIN exams e ON e.id = c.exam_id
     JOIN results r ON r.id = c.result_id
     JOIN students s ON s.user_id = c.student_id
     WHERE c.certificate_number = ?",
    [$certNumber]
);

if (!$cert) {
    Response::error('Certificate not found. The certificate number provided is invalid or unrecorded.', 404);
}

if (!empty($cert['revoked_at'])) {
    Response::error("This certificate was revoked on {$cert['revoked_at']}. Reason: {$cert['revoke_reason']}", 410, [
        'status' => 'revoked',
        'certificate' => $cert
    ]);
}

Response::success([
    'status' => 'valid',
    'certificate' => [
        'certificate_number' => $cert['certificate_number'],
        'student_name'       => $cert['student_name'],
        'institution'        => $cert['institution'] ?: 'ExamPortal Academic Network',
        'department'         => $cert['department'] ?: 'Computer Science',
        'exam_name'          => $cert['exam_name'],
        'obtained_marks'     => (float)$cert['obtained_marks'],
        'total_marks'        => (float)$cert['total_marks'],
        'percentage'         => (float)$cert['percentage'],
        'issued_date'        => date('F d, Y', strtotime($cert['issued_at'])),
        'is_authentic'       => true,
    ]
], 'Certificate verified successfully');
