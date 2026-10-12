-- ============================================================
-- Seed Data for Integrated Online Examination Portal
-- Run AFTER schema.sql
-- ============================================================

USE exam_portal;

-- ============================================================
-- DEFAULT ADMIN USER
-- Password: Admin@123! (bcrypt hash, cost=12)
-- ============================================================
INSERT INTO users (uuid, email, password_hash, role, is_active, email_verified) VALUES
(UUID(), 'admin@examportal.com', '$2y$12$LnWspyCr5VGx0Ie.6FDFROGlqJNjLaS5z7P5GKjIzYBjcJkPJ4l3u', 'admin', 1, 1),
(UUID(), 'examiner@examportal.com', '$2y$12$LnWspyCr5VGx0Ie.6FDFROGlqJNjLaS5z7P5GKjIzYBjcJkPJ4l3u', 'examiner', 1, 1),
(UUID(), 'student@examportal.com', '$2y$12$LnWspyCr5VGx0Ie.6FDFROGlqJNjLaS5z7P5GKjIzYBjcJkPJ4l3u', 'student', 1, 1);

-- NOTE: The hash above is for 'Admin@123!' — regenerate before production!
-- Use: password_hash('Admin@123!', PASSWORD_BCRYPT, ['cost' => 12])

INSERT INTO admins (user_id, first_name, last_name, phone)
SELECT id, 'System', 'Administrator', '+91-9999999999' FROM users WHERE email = 'admin@examportal.com';

INSERT INTO examiners (user_id, first_name, last_name, department, qualification, designation)
SELECT id, 'Dr. Rajesh', 'Kumar', 'Computer Science', 'Ph.D. Computer Science', 'Associate Professor'
FROM users WHERE email = 'examiner@examportal.com';

INSERT INTO students (user_id, student_id_number, first_name, last_name, department, semester, year_of_study, institution)
SELECT id, 'CS2024001', 'Demo', 'Student', 'Computer Science & Engineering', 7, 4, 'Demo Technical University'
FROM users WHERE email = 'student@examportal.com';

-- ============================================================
-- COURSES
-- ============================================================
INSERT INTO courses (name, code, description, duration_years, is_active, created_by) VALUES
('Bachelor of Technology - Computer Science', 'BTECH-CS', 'Four-year undergraduate engineering programme in Computer Science and Engineering', 4, 1, 1),
('Bachelor of Technology - Information Technology', 'BTECH-IT', 'Four-year undergraduate engineering programme in Information Technology', 4, 1, 1),
('Master of Computer Applications', 'MCA', 'Three-year postgraduate programme in Computer Applications', 3, 1, 1),
('Master of Technology - Computer Science', 'MTECH-CS', 'Two-year postgraduate engineering programme in Computer Science', 2, 1, 1);

-- ============================================================
-- SUBJECTS
-- ============================================================
INSERT INTO subjects (name, code, description, course_id, is_active, created_by) VALUES
('Data Structures and Algorithms', 'CS301', 'Fundamental data structures and algorithm design techniques', 1, 1, 1),
('Database Management Systems', 'CS302', 'Relational databases, SQL, normalization and transaction management', 1, 1, 1),
('Operating Systems', 'CS303', 'Process management, memory management, file systems', 1, 1, 1),
('Computer Networks', 'CS401', 'Network protocols, TCP/IP, routing and network security', 1, 1, 1),
('Software Engineering', 'CS402', 'Software development lifecycle, design patterns, testing', 1, 1, 1),
('Web Technologies', 'CS501', 'HTML, CSS, JavaScript, PHP, RESTful APIs', 1, 1, 1),
('Artificial Intelligence', 'CS601', 'Search algorithms, machine learning fundamentals, neural networks', 1, 1, 1),
('Compiler Design', 'CS502', 'Lexical analysis, parsing, code generation', 1, 1, 1),
('Computer Architecture', 'CS201', 'CPU design, memory hierarchy, instruction sets', 1, 1, 1),
('Discrete Mathematics', 'MA201', 'Logic, set theory, graph theory, combinatorics', 1, 1, 1);

-- ============================================================
-- SYSTEM SETTINGS
-- ============================================================
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('app_name', 'ExamPortal', 'string', 'Application display name'),
('app_maintenance_mode', '0', 'boolean', 'Enable maintenance mode'),
('default_passing_percentage', '40', 'integer', 'Default exam passing percentage'),
('max_exam_attempts', '3', 'integer', 'Maximum allowed attempts per exam'),
('session_timeout_minutes', '120', 'integer', 'Session expiration time in minutes'),
('max_login_attempts', '5', 'integer', 'Maximum failed login attempts before lockout'),
('lockout_duration_minutes', '15', 'integer', 'Account lockout duration after max failed attempts'),
('proctoring_enabled_default', '0', 'boolean', 'Enable proctoring by default for new exams'),
('proctoring_service_url', 'http://localhost:5001', 'string', 'URL of the Python OpenCV proctoring service'),
('risk_score_threshold_medium', '30', 'integer', 'Risk score threshold for Medium risk level'),
('risk_score_threshold_high', '60', 'integer', 'Risk score threshold for High risk level'),
('risk_score_threshold_critical', '80', 'integer', 'Risk score threshold for Critical risk level'),
('snapshot_enabled', '0', 'boolean', 'Enable snapshot capture during proctoring events'),
('max_upload_size_mb', '5', 'integer', 'Maximum file upload size in megabytes'),
('certificate_template_version', '1.0', 'string', 'Current certificate template version'),
('privacy_policy_version', '1.0', 'string', 'Current privacy policy version'),
('terms_version', '1.0', 'string', 'Current terms and conditions version'),
('email_enabled', '0', 'boolean', 'Enable email notification sending'),
('smtp_host', '', 'string', 'SMTP server hostname'),
('smtp_port', '587', 'integer', 'SMTP server port'),
('smtp_from_email', 'noreply@examportal.com', 'string', 'From email address for notifications'),
('smtp_from_name', 'ExamPortal', 'string', 'From name for email notifications');

-- ============================================================
-- DATA RETENTION POLICIES
-- ============================================================
INSERT INTO data_retention_policies (data_type, description, retention_days, auto_delete) VALUES
('exam_results', 'Student examination results and scores', 1825, 0),
('audit_logs', 'System audit trail logs', 730, 0),
('security_logs', 'Security event logs', 365, 0),
('proctoring_events', 'Proctoring event records for completed exams', 180, 0),
('proctoring_snapshots', 'Optional proctoring snapshot images', 90, 1),
('session_data', 'Expired user sessions', 7, 1),
('reset_tokens', 'Expired password reset tokens', 1, 1),
('rate_limit_log', 'Rate limiting records', 1, 1),
('account_data', 'User account and profile data (active accounts)', 0, 0),
('notifications', 'Read notification records', 90, 1);
