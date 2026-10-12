<?php
require_once __DIR__ . '/../config/database.php';

class NotificationHelper {
    public static function create(
        int $userId,
        string $title,
        string $message,
        string $type = 'general',
        ?int $relatedId = null
    ): int {
        return db()->insert(
            "INSERT INTO notifications (user_id, title, message, notification_type, related_id)
             VALUES (?, ?, ?, ?, ?)",
            [$userId, $title, $message, $type, $relatedId]
        );
    }

    public static function examScheduled(int $studentId, int $examId, string $examTitle, string $startTime): void {
        self::create(
            $studentId,
            'New Exam Scheduled',
            "You have been enrolled in '{$examTitle}'. The exam is scheduled for {$startTime}.",
            'exam_scheduled',
            $examId
        );
    }

    public static function resultPublished(int $studentId, int $examId, string $examTitle, float $percentage, bool $passed): void {
        $status = $passed ? 'PASSED' : 'FAILED';
        self::create(
            $studentId,
            'Exam Result Published',
            "Your result for '{$examTitle}' has been published. Score: {$percentage}% — {$status}",
            'result_published',
            $examId
        );
    }

    public static function certificateGenerated(int $studentId, int $certId, string $examTitle): void {
        self::create(
            $studentId,
            'Certificate Generated',
            "Congratulations! Your certificate for '{$examTitle}' is ready. Download it from your Certificates page.",
            'certificate_generated',
            $certId
        );
    }

    public static function passwordReset(int $userId): void {
        self::create(
            $userId,
            'Password Reset Successful',
            'Your password has been reset successfully. If you did not request this, contact support immediately.',
            'password_reset'
        );
    }

    public static function accountLocked(int $userId): void {
        self::create(
            $userId,
            'Account Temporarily Locked',
            'Your account has been temporarily locked due to multiple failed login attempts. Please try again in 15 minutes.',
            'general'
        );
    }

    public static function sendEmail(string $to, string $subject, string $htmlBody): bool {
        $fromEmail = defined('MAIL_FROM') ? MAIL_FROM : (getenv('MAIL_FROM') ?: 'noreply@examportal.com');
        $fromName  = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : (getenv('MAIL_FROM_NAME') ?: 'ExamPortal');

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
        $headers .= "Reply-To: {$fromEmail}\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        return @mail($to, $subject, $htmlBody, $headers);
    }
}
