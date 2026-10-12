<?php
require_once __DIR__ . '/env.php';

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Kolkata');

define('APP_NAME', getenv('APP_NAME') ?: 'ExamPortal');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost');
define('APP_ENV', getenv('APP_ENV') ?: 'production');
define('APP_DEBUG', APP_ENV === 'development');

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'exam_portal');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASSWORD') ?: '');

define('SESSION_TIMEOUT', (int)(getenv('SESSION_TIMEOUT') ?: 7200));
define('MAX_LOGIN_ATTEMPTS', (int)(getenv('MAX_LOGIN_ATTEMPTS') ?: 5));
define('LOCKOUT_DURATION', (int)(getenv('LOCKOUT_DURATION') ?: 900));

define('PROCTORING_SERVICE_URL', getenv('PROCTORING_SERVICE_URL') ?: 'http://localhost:5001');
define('PROCTORING_API_KEY', getenv('PROCTORING_API_KEY') ?: '');

define('UPLOAD_BASE', dirname(__DIR__) . '/uploads/');
define('UPLOAD_PROFILES', UPLOAD_BASE . 'profiles/');
define('UPLOAD_QUESTIONS', UPLOAD_BASE . 'questions/');
define('UPLOAD_SNAPSHOTS', UPLOAD_BASE . 'snapshots/');

define('LOG_PATH', dirname(__DIR__) . '/logs/');
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024);
define('CERT_VERIFY_URL', APP_URL . '/frontend/pages/verify-certificate.html');
define('PRIVACY_POLICY_VERSION', '1.0');
define('TERMS_VERSION', '1.0');

// Ensure directories exist
foreach ([UPLOAD_PROFILES, UPLOAD_QUESTIONS, UPLOAD_SNAPSHOTS, LOG_PATH] as $dir) {
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
}
