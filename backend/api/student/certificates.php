<?php
/**
 * GET /api/student/certificates
 * GET /api/student/certificates/{id}/download
 */
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/CertificateGenerator.php';
require_once __DIR__ . '/../../middleware/Auth.php';

$user = Auth::authorize(['student']);
$userId = (int)$user['user_id'];

global $_ROUTE;
$certId = isset($_ROUTE['id']) ? (int)$_ROUTE['id'] : null;
$action = $_ROUTE['action'] ?? null;

if ($certId && $action === 'download') {
    $cert = db()->fetchOne(
        "SELECT c.*, e.title AS exam_name, e.total_marks, r.obtained_marks, r.percentage,
                CONCAT(s.first_name, ' ', s.last_name) AS student_name, s.institution
         FROM certificates c
         JOIN exams e ON e.id = c.exam_id
         JOIN results r ON r.id = c.result_id
         JOIN students s ON s.user_id = c.student_id
         WHERE c.id = ? AND c.student_id = ? AND c.revoked_at IS NULL",
        [$certId, $userId]
    );
    if (!$cert) Response::error('Certificate not found or revoked', 404);

    $html = CertificateGenerator::getCertificateHTML([
        'student_name' => $cert['student_name'],
        'exam_name' => $cert['exam_name'],
        'score' => $cert['obtained_marks'],
        'total_marks' => $cert['total_marks'],
        'percentage' => $cert['percentage'],
        'completion_date' => date('F d, Y', strtotime($cert['issued_at'])),
        'certificate_number' => $cert['certificate_number'],
        'institution_name' => $cert['institution'] ?: 'ExamPortal',
        'qr_data' => $cert['qr_code_data']
    ]);

    // Return raw HTML for rendering / print dialog
    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
    exit;
}

// List all certificates
$certs = db()->fetchAll(
    "SELECT c.id, c.uuid, c.certificate_number, c.issued_at, c.qr_code_data,
            e.title AS exam_name, r.obtained_marks, r.total_marks, r.percentage, s.name AS subject_name
     FROM certificates c
     JOIN exams e ON e.id = c.exam_id
     JOIN results r ON r.id = c.result_id
     LEFT JOIN subjects s ON s.id = e.subject_id
     WHERE c.student_id = ? AND c.revoked_at IS NULL
     ORDER BY c.issued_at DESC",
    [$userId]
);

Response::success($certs);
