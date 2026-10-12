<?php
/**
 * POST /api/auth/register
 * Universal role registration endpoint (Student / Examiner / Admin).
 */
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../utils/PasswordHelper.php';
require_once __DIR__ . '/../../utils/NotificationHelper.php';
require_once __DIR__ . '/../../middleware/InputValidator.php';
require_once __DIR__ . '/../../middleware/RateLimit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

// Rate limit: 20 registrations per hour per IP
$ip = RateLimit::getClientIP();
if (!RateLimit::check("register_ip_{$ip}", 20, 3600)) {
    Response::error('Too many registration attempts. Please try again later.', 429);
}

$body = InputValidator::getRequestBody();

// Required fields validation
$required = ['first_name', 'last_name', 'email', 'password', 'confirm_password'];
$missing  = InputValidator::required($body, $required);
if (!empty($missing)) {
    Response::error('Missing required fields: ' . implode(', ', $missing), 422, ['missing' => $missing]);
}

$firstName       = InputValidator::sanitizeString($body['first_name'] ?? '', 100);
$lastName        = InputValidator::sanitizeString($body['last_name'] ?? '', 100);
$email           = strtolower(trim($body['email'] ?? ''));
$password        = $body['password'] ?? '';
$confirmPassword = $body['confirm_password'] ?? '';
$role            = in_array($body['role'] ?? '', ['student', 'examiner', 'admin'], true) ? $body['role'] : 'student';

$studentIdNum    = InputValidator::sanitizeString($body['student_id_number'] ?? '', 50);
$department      = InputValidator::sanitizeString($body['department'] ?? '', 200);
$designation     = InputValidator::sanitizeString($body['designation'] ?? '', 200);
$semester        = InputValidator::validateInt($body['semester'] ?? null, 1, 12);
$yearOfStudy     = InputValidator::validateInt($body['year_of_study'] ?? null, 1, 6);
$institution     = InputValidator::sanitizeString($body['institution'] ?? '', 300);
$phone           = InputValidator::sanitizeString($body['phone'] ?? '', 20);

// Validate email
if (!InputValidator::validateEmail($email)) {
    Response::error('Invalid email address format', 422);
}

// Validate password strength
$pwErrors = PasswordHelper::validateStrength($password);
if (!empty($pwErrors)) {
    Response::error('Password does not meet requirements', 422, $pwErrors);
}

// Confirm password match
if ($password !== $confirmPassword) {
    Response::error('Passwords do not match', 422);
}

// Validate name lengths
if (strlen($firstName) < 2) Response::error('First name must be at least 2 characters', 422);
if (strlen($lastName)  < 2) Response::error('Last name must be at least 2 characters', 422);

try {
    // Check email uniqueness
    $existing = db()->fetchOne('SELECT id FROM users WHERE email = ?', [$email]);
    if ($existing) {
        Response::error('An account with this email address already exists', 409);
    }

    $passwordHash = PasswordHelper::hash($password);
    $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );

    // Generate email verification token
    $verificationToken = bin2hex(random_bytes(32));

    db()->beginTransaction();

    // Create user account with chosen role
    $userId = db()->insert(
        "INSERT INTO users (uuid, email, password_hash, role, is_active, email_verified, email_verification_token)
         VALUES (?, ?, ?, ?, 1, 1, ?)",
        [$uuid, $email, $passwordHash, $role, hash('sha256', $verificationToken)]
    );

    // Create corresponding profile matching database columns
    if ($role === 'student') {
        db()->insert(
            "INSERT INTO students (user_id, student_id_number, first_name, last_name, phone, department, semester, year_of_study, institution)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$userId, $studentIdNum ?: null, $firstName, $lastName, $phone ?: null,
             $department ?: null, $semester, $yearOfStudy, $institution ?: null]
        );
    } elseif ($role === 'examiner') {
        db()->insert(
            "INSERT INTO examiners (user_id, first_name, last_name, phone, department, designation)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$userId, $firstName, $lastName, $phone ?: null,
             $department ?: 'Computer Science', $designation ?: 'Faculty Examiner']
        );
    } elseif ($role === 'admin') {
        db()->insert(
            "INSERT INTO admins (user_id, first_name, last_name, phone)
             VALUES (?, ?, ?, ?)",
            [$userId, $firstName, $lastName, $phone ?: null]
        );
    }

    // Record consents if provided
    $consentTypes = [
        'terms_conditions'   => (bool)($body['consent_terms'] ?? false),
        'privacy_policy'     => (bool)($body['consent_privacy'] ?? false),
        'data_processing'    => (bool)($body['consent_data'] ?? false),
        'email_notifications'=> (bool)($body['consent_email'] ?? false),
    ];

    $policyVersion = PRIVACY_POLICY_VERSION;
    foreach ($consentTypes as $type => $given) {
        if ($given) {
            db()->insert(
                "INSERT INTO consents (user_id, consent_type, is_given, consent_version, ip_address, user_agent, given_at)
                 VALUES (?, ?, 1, ?, ?, ?, NOW())",
                [$userId, $type, $policyVersion, $ip, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)]
            );
        }
    }

    db()->commit();

    // Log audit event
    Logger::audit($userId, 'user_registered', 'users', $userId, null, ['email' => $email, 'role' => $role]);

    // Send welcome notification
    NotificationHelper::create($userId, 'Welcome to ExamPortal!',
        "Hello {$firstName}! Your {$role} account has been created successfully. You can now log in and access your portal.",
        'general'
    );

    Response::created([
        'user_id' => $userId,
        'email'   => $email,
        'role'    => $role,
        'name'    => "{$firstName} {$lastName}",
    ], ucfirst($role) . ' account created successfully. Please sign in to continue.');

} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollback();
    Logger::error('Registration failed: ' . $e->getMessage(), ['email' => $email]);
    Response::error('Registration failed: ' . $e->getMessage(), 500);
}
