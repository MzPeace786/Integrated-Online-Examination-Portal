-- ============================================================
-- Integrated Online Examination Portal
-- Database Schema — MySQL 8.0+
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO';

CREATE DATABASE IF NOT EXISTS exam_portal
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE exam_portal;

-- ============================================================
-- USERS & AUTHENTICATION
-- ============================================================

CREATE TABLE IF NOT EXISTS users (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid        CHAR(36) NOT NULL,
    email       VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role        ENUM('student','examiner','admin') NOT NULL DEFAULT 'student',
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    email_verified TINYINT(1) NOT NULL DEFAULT 0,
    email_verification_token VARCHAR(128) NULL,
    failed_login_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_login_at DATETIME NULL,
    last_login_ip VARCHAR(45) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_uuid (uuid),
    UNIQUE KEY uq_users_email (email),
    INDEX idx_users_role (role),
    INDEX idx_users_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_sessions (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    session_token_hash VARCHAR(128) NOT NULL,
    ip_address      VARCHAR(45) NULL,
    user_agent      TEXT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at      DATETIME NOT NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_session_token (session_token_hash),
    INDEX idx_sessions_user (user_id),
    INDEX idx_sessions_expires (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    token_hash  VARCHAR(128) NOT NULL,
    expires_at  DATETIME NOT NULL,
    used_at     DATETIME NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_prt_user (user_id),
    INDEX idx_prt_token (token_hash),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limit_log (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    limit_key   VARCHAR(128) NOT NULL,
    request_count INT UNSIGNED NOT NULL DEFAULT 1,
    window_start DATETIME NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_rll_key_window (limit_key, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- USER PROFILES
-- ============================================================

CREATE TABLE IF NOT EXISTS students (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    student_id_number VARCHAR(50) NULL,
    first_name      VARCHAR(100) NOT NULL,
    last_name       VARCHAR(100) NOT NULL,
    date_of_birth   DATE NULL,
    gender          ENUM('male','female','other','prefer_not_to_say') NULL,
    phone           VARCHAR(20) NULL,
    profile_photo_path VARCHAR(500) NULL,
    department      VARCHAR(200) NULL,
    semester        TINYINT UNSIGNED NULL,
    year_of_study   TINYINT UNSIGNED NULL,
    institution     VARCHAR(300) NULL,
    address         TEXT NULL,
    bio             TEXT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_students_user (user_id),
    INDEX idx_students_student_id (student_id_number),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS examiners (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    first_name      VARCHAR(100) NOT NULL,
    last_name       VARCHAR(100) NOT NULL,
    phone           VARCHAR(20) NULL,
    department      VARCHAR(200) NULL,
    qualification   VARCHAR(300) NULL,
    designation     VARCHAR(200) NULL,
    profile_photo_path VARCHAR(500) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_examiners_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    first_name      VARCHAR(100) NOT NULL,
    last_name       VARCHAR(100) NOT NULL,
    phone           VARCHAR(20) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admins_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ACADEMIC STRUCTURE
-- ============================================================

CREATE TABLE IF NOT EXISTS courses (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(200) NOT NULL,
    code            VARCHAR(20) NOT NULL,
    description     TEXT NULL,
    duration_years  TINYINT UNSIGNED NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_by      INT UNSIGNED NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_courses_code (code),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subjects (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(200) NOT NULL,
    code            VARCHAR(20) NOT NULL,
    description     TEXT NULL,
    course_id       INT UNSIGNED NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_by      INT UNSIGNED NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_subjects_code (code),
    INDEX idx_subjects_course (course_id),
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- QUESTION BANK
-- ============================================================

CREATE TABLE IF NOT EXISTS question_banks (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(200) NOT NULL,
    description     TEXT NULL,
    subject_id      INT UNSIGNED NULL,
    examiner_id     INT UNSIGNED NOT NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_qb_examiner (examiner_id),
    INDEX idx_qb_subject (subject_id),
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    FOREIGN KEY (examiner_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS questions (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    question_bank_id INT UNSIGNED NULL,
    subject_id      INT UNSIGNED NULL,
    topic           VARCHAR(200) NULL,
    question_type   ENUM('mcq','multiple_correct','true_false','fill_blank','short_answer','coding') NOT NULL,
    question_text   TEXT NOT NULL,
    question_image_path VARCHAR(500) NULL,
    difficulty      ENUM('easy','medium','hard') NOT NULL DEFAULT 'medium',
    marks           DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    negative_marks  DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    explanation     TEXT NULL,
    tags            JSON NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    is_deleted      TINYINT(1) NOT NULL DEFAULT 0,
    created_by      INT UNSIGNED NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_q_bank (question_bank_id),
    INDEX idx_q_subject (subject_id),
    INDEX idx_q_type (question_type),
    INDEX idx_q_difficulty (difficulty),
    INDEX idx_q_active (is_active, is_deleted),
    FOREIGN KEY (question_bank_id) REFERENCES question_banks(id) ON DELETE SET NULL,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS question_options (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    question_id     INT UNSIGNED NOT NULL,
    option_text     TEXT NOT NULL,
    option_image_path VARCHAR(500) NULL,
    is_correct      TINYINT(1) NOT NULL DEFAULT 0,
    display_order   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    INDEX idx_qo_question (question_id),
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coding_test_cases (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    question_id     INT UNSIGNED NOT NULL,
    input_data      TEXT NULL,
    expected_output TEXT NOT NULL,
    is_hidden       TINYINT(1) NOT NULL DEFAULT 0,
    marks           DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    time_limit_ms   INT UNSIGNED NOT NULL DEFAULT 5000,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_ctc_question (question_id),
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- EXAMINATIONS
-- ============================================================

CREATE TABLE IF NOT EXISTS exams (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid            CHAR(36) NOT NULL,
    title           VARCHAR(300) NOT NULL,
    description     TEXT NULL,
    instructions    TEXT NULL,
    subject_id      INT UNSIGNED NULL,
    examiner_id     INT UNSIGNED NOT NULL,
    exam_type       ENUM('objective','coding','mixed') NOT NULL DEFAULT 'objective',
    start_datetime  DATETIME NOT NULL,
    end_datetime    DATETIME NOT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    total_questions SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    total_marks     DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    marks_per_question DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    negative_marking_enabled TINYINT(1) NOT NULL DEFAULT 0,
    negative_marks_per_question DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    passing_percentage DECIMAL(5,2) NOT NULL DEFAULT 40.00,
    allowed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 1,
    random_questions TINYINT(1) NOT NULL DEFAULT 0,
    random_options  TINYINT(1) NOT NULL DEFAULT 0,
    question_difficulty_filter ENUM('all','easy','medium','hard') NOT NULL DEFAULT 'all',
    proctoring_enabled TINYINT(1) NOT NULL DEFAULT 0,
    fullscreen_required TINYINT(1) NOT NULL DEFAULT 1,
    allow_copy_paste TINYINT(1) NOT NULL DEFAULT 0,
    allow_right_click TINYINT(1) NOT NULL DEFAULT 0,
    status          ENUM('draft','scheduled','active','completed','cancelled') NOT NULL DEFAULT 'draft',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_exams_uuid (uuid),
    INDEX idx_exams_examiner (examiner_id),
    INDEX idx_exams_subject (subject_id),
    INDEX idx_exams_status (status),
    INDEX idx_exams_start (start_datetime),
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    FOREIGN KEY (examiner_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_questions (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    exam_id         INT UNSIGNED NOT NULL,
    question_id     INT UNSIGNED NOT NULL,
    display_order   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    marks_override  DECIMAL(5,2) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_eq_exam_question (exam_id, question_id),
    INDEX idx_eq_exam (exam_id),
    INDEX idx_eq_question (question_id),
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_enrollments (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    exam_id         INT UNSIGNED NOT NULL,
    student_id      INT UNSIGNED NOT NULL,
    enrolled_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    enrolled_by     INT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_enrollment (exam_id, student_id),
    INDEX idx_enroll_exam (exam_id),
    INDEX idx_enroll_student (student_id),
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- EXAM ATTEMPTS & ANSWERS
-- ============================================================

CREATE TABLE IF NOT EXISTS exam_attempts (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid            CHAR(36) NOT NULL,
    exam_id         INT UNSIGNED NOT NULL,
    student_id      INT UNSIGNED NOT NULL,
    attempt_number  TINYINT UNSIGNED NOT NULL DEFAULT 1,
    question_order  JSON NULL,
    option_orders   JSON NULL,
    started_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    submitted_at    DATETIME NULL,
    auto_submitted  TINYINT(1) NOT NULL DEFAULT 0,
    time_taken_seconds INT UNSIGNED NULL,
    status          ENUM('in_progress','submitted','abandoned') NOT NULL DEFAULT 'in_progress',
    ip_address      VARCHAR(45) NULL,
    browser_info    VARCHAR(500) NULL,
    tab_switch_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    fullscreen_exit_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_attempts_uuid (uuid),
    INDEX idx_attempts_exam (exam_id),
    INDEX idx_attempts_student (student_id),
    INDEX idx_attempts_status (status),
    FOREIGN KEY (exam_id) REFERENCES exams(id),
    FOREIGN KEY (student_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_answers (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    attempt_id      INT UNSIGNED NOT NULL,
    question_id     INT UNSIGNED NOT NULL,
    selected_option_ids JSON NULL,
    text_answer     TEXT NULL,
    code_answer     MEDIUMTEXT NULL,
    language        VARCHAR(50) NULL,
    is_marked_for_review TINYINT(1) NOT NULL DEFAULT 0,
    time_spent_seconds INT UNSIGNED NULL,
    saved_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sa_attempt_question (attempt_id, question_id),
    INDEX idx_sa_attempt (attempt_id),
    INDEX idx_sa_question (question_id),
    FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coding_submissions (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    attempt_id      INT UNSIGNED NOT NULL,
    question_id     INT UNSIGNED NOT NULL,
    language        VARCHAR(50) NOT NULL,
    code            MEDIUMTEXT NOT NULL,
    execution_status ENUM('pending','running','completed','timeout','error') NOT NULL DEFAULT 'pending',
    stdout          TEXT NULL,
    stderr          TEXT NULL,
    test_cases_passed TINYINT UNSIGNED NOT NULL DEFAULT 0,
    test_cases_total TINYINT UNSIGNED NOT NULL DEFAULT 0,
    score           DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    execution_time_ms INT UNSIGNED NULL,
    executed_at     DATETIME NULL,
    PRIMARY KEY (id),
    INDEX idx_cs_attempt (attempt_id),
    INDEX idx_cs_question (question_id),
    FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- RESULTS & CERTIFICATES
-- ============================================================

CREATE TABLE IF NOT EXISTS results (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    attempt_id      INT UNSIGNED NOT NULL,
    student_id      INT UNSIGNED NOT NULL,
    exam_id         INT UNSIGNED NOT NULL,
    total_questions SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    attempted_questions SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    correct_answers SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    incorrect_answers SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    unanswered_questions SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    total_marks     DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    obtained_marks  DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    negative_marks_deducted DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    percentage      DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    is_passed       TINYINT(1) NOT NULL DEFAULT 0,
    rank            SMALLINT UNSIGNED NULL,
    time_taken_seconds INT UNSIGNED NULL,
    answer_details  JSON NULL,
    evaluated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_published    TINYINT(1) NOT NULL DEFAULT 1,
    published_at    DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_results_attempt (attempt_id),
    INDEX idx_results_student (student_id),
    INDEX idx_results_exam (exam_id),
    INDEX idx_results_passed (is_passed),
    FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id),
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (exam_id) REFERENCES exams(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS certificates (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid            CHAR(36) NOT NULL,
    student_id      INT UNSIGNED NOT NULL,
    exam_id         INT UNSIGNED NOT NULL,
    result_id       INT UNSIGNED NOT NULL,
    certificate_number VARCHAR(50) NOT NULL,
    issued_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    qr_code_data    VARCHAR(500) NULL,
    template_version VARCHAR(10) NOT NULL DEFAULT '1.0',
    revoked_at      DATETIME NULL,
    revoke_reason   TEXT NULL,
    revoked_by      INT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_certs_uuid (uuid),
    UNIQUE KEY uq_certs_number (certificate_number),
    UNIQUE KEY uq_certs_result (result_id),
    INDEX idx_certs_student (student_id),
    INDEX idx_certs_exam (exam_id),
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (exam_id) REFERENCES exams(id),
    FOREIGN KEY (result_id) REFERENCES results(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PROCTORING
-- ============================================================

CREATE TABLE IF NOT EXISTS proctoring_sessions (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    attempt_id      INT UNSIGNED NOT NULL,
    student_id      INT UNSIGNED NOT NULL,
    exam_id         INT UNSIGNED NOT NULL,
    started_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at        DATETIME NULL,
    camera_status   ENUM('active','inactive','denied','unavailable') NOT NULL DEFAULT 'inactive',
    face_verified   TINYINT(1) NOT NULL DEFAULT 0,
    face_verification_confidence DECIMAL(5,4) NULL,
    risk_score      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    total_events    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    high_severity_events SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    medium_severity_events SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    low_severity_events SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    status          ENUM('active','ended','error') NOT NULL DEFAULT 'active',
    PRIMARY KEY (id),
    UNIQUE KEY uq_ps_attempt (attempt_id),
    INDEX idx_ps_student (student_id),
    INDEX idx_ps_exam (exam_id),
    INDEX idx_ps_status (status),
    FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (exam_id) REFERENCES exams(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proctoring_events (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id      INT UNSIGNED NOT NULL,
    student_id      INT UNSIGNED NOT NULL,
    exam_id         INT UNSIGNED NOT NULL,
    event_type      ENUM(
        'exam_started','exam_submitted','exam_paused',
        'face_missing','face_detected',
        'multiple_faces','looking_away',
        'camera_unavailable','camera_active',
        'identity_mismatch',
        'tab_switch','fullscreen_exit','window_blur',
        'copy_attempt','right_click_attempt'
    ) NOT NULL,
    severity        ENUM('info','low','medium','high','critical') NOT NULL DEFAULT 'low',
    description     TEXT NULL,
    timestamp       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    review_status   ENUM('pending','reviewed','dismissed') NOT NULL DEFAULT 'pending',
    reviewed_by     INT UNSIGNED NULL,
    reviewed_at     DATETIME NULL,
    reviewer_notes  TEXT NULL,
    snapshot_path   VARCHAR(500) NULL,
    PRIMARY KEY (id),
    INDEX idx_pe_session (session_id),
    INDEX idx_pe_student (student_id),
    INDEX idx_pe_exam (exam_id),
    INDEX idx_pe_type (event_type),
    INDEX idx_pe_severity (severity),
    INDEX idx_pe_timestamp (timestamp),
    FOREIGN KEY (session_id) REFERENCES proctoring_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (exam_id) REFERENCES exams(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CONSENT
-- ============================================================

CREATE TABLE IF NOT EXISTS consents (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    exam_id         INT UNSIGNED NULL,
    consent_type    ENUM('terms_conditions','privacy_policy','data_processing','webcam_proctoring','face_verification','email_notifications') NOT NULL,
    is_given        TINYINT(1) NOT NULL DEFAULT 0,
    consent_version VARCHAR(10) NOT NULL DEFAULT '1.0',
    ip_address      VARCHAR(45) NULL,
    user_agent      TEXT NULL,
    given_at        DATETIME NULL,
    withdrawn_at    DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_consent_user (user_id),
    INDEX idx_consent_exam (exam_id),
    INDEX idx_consent_type (consent_type),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- NOTIFICATIONS
-- ============================================================

CREATE TABLE IF NOT EXISTS notifications (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    title           VARCHAR(255) NOT NULL,
    message         TEXT NOT NULL,
    notification_type ENUM('exam_scheduled','exam_reminder','exam_started','exam_completed','result_published','certificate_generated','password_reset','account_locked','general') NOT NULL DEFAULT 'general',
    related_id      INT UNSIGNED NULL,
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    read_at         DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_notif_user (user_id),
    INDEX idx_notif_read (is_read),
    INDEX idx_notif_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- AUDIT & SECURITY LOGS
-- ============================================================

CREATE TABLE IF NOT EXISTS audit_logs (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NULL,
    action          VARCHAR(100) NOT NULL,
    entity_type     VARCHAR(50) NULL,
    entity_id       INT UNSIGNED NULL,
    old_values      JSON NULL,
    new_values      JSON NULL,
    ip_address      VARCHAR(45) NULL,
    user_agent      VARCHAR(500) NULL,
    status          ENUM('success','failure') NOT NULL DEFAULT 'success',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_al_user (user_id),
    INDEX idx_al_action (action),
    INDEX idx_al_entity (entity_type, entity_id),
    INDEX idx_al_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS security_logs (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NULL,
    event_type      ENUM('login_success','login_failed','logout','password_reset_request','password_reset_success','account_locked','account_unlocked','email_verified','suspicious_activity','unauthorized_access') NOT NULL,
    ip_address      VARCHAR(45) NOT NULL,
    user_agent      VARCHAR(500) NULL,
    details         JSON NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_sl_user (user_id),
    INDEX idx_sl_type (event_type),
    INDEX idx_sl_ip (ip_address),
    INDEX idx_sl_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SYSTEM SETTINGS & DATA MANAGEMENT
-- ============================================================

CREATE TABLE IF NOT EXISTS system_settings (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key     VARCHAR(100) NOT NULL,
    setting_value   TEXT NULL,
    setting_type    ENUM('string','integer','boolean','json') NOT NULL DEFAULT 'string',
    description     TEXT NULL,
    updated_by      INT UNSIGNED NULL,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_settings_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS data_retention_policies (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    data_type       VARCHAR(100) NOT NULL,
    description     TEXT NULL,
    retention_days  INT UNSIGNED NOT NULL DEFAULT 365,
    auto_delete     TINYINT(1) NOT NULL DEFAULT 0,
    last_run_at     DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_drp_type (data_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS data_deletion_requests (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    request_type    ENUM('account_deletion','data_export','data_correction','proctoring_data','exam_data') NOT NULL,
    reason          TEXT NULL,
    status          ENUM('pending','processing','completed','rejected') NOT NULL DEFAULT 'pending',
    requested_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at    DATETIME NULL,
    processed_by    INT UNSIGNED NULL,
    notes           TEXT NULL,
    PRIMARY KEY (id),
    INDEX idx_ddr_user (user_id),
    INDEX idx_ddr_status (status),
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
