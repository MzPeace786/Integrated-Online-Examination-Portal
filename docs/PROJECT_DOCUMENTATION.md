# Academic Project Report & Engineering Documentation

## Integrated Online Examination Portal with OpenCV-Based AI-Assisted Proctoring
**Major Project Report — 7th Semester B.Tech Computer Science and Engineering**

---

### Executive Summary

Online educational evaluation systems face persistent challenges in reconciling remote scalability with academic integrity. This major project report presents the complete design, engineering, and deployment of the **Integrated Online Examination Portal with OpenCV-Based AI-Assisted Proctoring**.

The system combines a PHP/MySQL RESTful backend architecture, an interactive HTML5/Bootstrap 5 frontend, and an isolated Python/Flask computer vision microservice using OpenCV Haar Cascade classifiers. It provides comprehensive examination lifecycle management—from question authoring, automated randomized assessments, real-time telemetry capture, and double-blind scoring to cryptographic QR-verifiable certificate issuance.

---

### Table of Contents

1. **Project Abstract & Introduction**
2. **Problem Statement & Motivation**
3. **Requirement Analysis (Functional & Non-Functional)**
4. **System Architecture & Microservices Design**
5. **Database Design & Normalized Schema (22 Relational Entities)**
6. **Authentication & Session Security Model**
7. **Role-Based Access Control (Student, Examiner, Admin)**
8. **Question Bank Management (6 Assessment Formats)**
9. **Examination Scheduling & Randomization Engine**
10. **Candidate Exam Interface & Real-time Autosave**
11. **OpenCV AI-Assisted Proctoring Microservice**
12. **Face Detection & Landmark Heuristics**
13. **Head Pose & Gaze Deviation Estimation**
14. **Browser-Level Anti-Cheating Lockdown (Fullscreen, Tab/Blur Tracking)**
15. **Heuristic Suspicious Activity Risk Scoring Algorithm**
16. **Live Examiner Monitoring Dashboard & Telemetry Streaming**
17. **Automated Objective Grading & Code Test Execution**
18. **Scorecard Analysis & Performance Analytics**
19. **Digital Certificate Generation & Cryptographic QR Verification**
20. **Student Privacy Dashboard & GDPR Consent Management**
21. **Audit Trails & Security Incident Event Logging**
22. **Data Retention Policies & Subject Deletion Automation**
23. **Security Analysis (SQLi, XSS, CSRF, Brute Force Protection)**
24. **Implementation & Technology Stack Evaluation**
25. **Testing, Verification & Quality Assurance**
26. **Technical Limitations & Ethical Considerations**
27. **Future Scope & Industry Scale-up Roadmap**
28. **Conclusion & Academic Acknowledgments**

---

### 1. Project Abstract & Introduction

Online assessment portals have transitioned from supplementary tools into primary evaluation channels for global higher education. However, remote exams are vulnerable to academic dishonesty such as unauthorized material lookup, tab switching, and multi-individual collaboration. 

This project solves these challenges by combining full-stack web engineering with applied computer vision. Instead of relying on proprietary, opaque surveillance software, this system implements an open-source, AI-assisted proctoring pipeline that captures real-time video frames and outputs transparent irregularity scores for human examiner review.

---

### 2. Problem Statement & Motivation

Traditional online examination tools suffer from critical drawbacks:
- **Binary / Inaccurate Flagging:** Overly strict AI engines often automatically disqualify students due to bad lighting or camera angles without human review.
- **Invasive Data Harvesting:** Continuous video recordings violate student privacy and create massive server storage burdens.
- **Lack of Multi-format Assessment:** Most tools only support static single-choice MCQs without support for coding or subjective problems.

**Project Motivation:**
To develop an ethical, production-grade assessment platform providing robust automated proctoring heuristics with transparent audit trails, verifiable digital credentials, and granular privacy controls.

---

### 3. System Architecture & Microservices Design

The system is organized into a three-tier decoupled microservice architecture:

```
┌─────────────────────────────────────────────────────────────┐
│                      CLIENT TIER                            │
│  HTML5 + Bootstrap 5 + Vanilla ES6 + Chart.js                │
│  - Interactive Exam Lockdown Engine                         │
│  - Periodic Frame Extractor (~5s Canvas Frame Capture)       │
└──────────────────────┬──────────────────────────────────────┘
                       │ HTTP REST (JSON Payload / Bearer Token)
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                   WEB APPLICATION SERVER                    │
│  PHP 8.0+ / Apache Router (backend/api/index.php)           │
│  - Session Authentication & Rate Limiting                   │
│  - Exam Scheduling & Question Randomization Engine          │
│  - Objective Auto-Grading & Isolated Code Execution         │
│  - Digital Certificate PDF/HTML Generator                   │
└──────────────────────┬──────────────────────────────────────┘
                       │ PDO MySQL Connection
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                   DATABASE PERSISTENCE                      │
│  MySQL 8.0+ Enterprise Database (22 Tables)                 │
└──────────────────────┬──────────────────────────────────────┘
                       ▲
                       │ Direct MySQL Connector
┌──────────────────────┴──────────────────────────────────────┐
│                OPENCV PROCTORING MICROSERVICE               │
│  Python 3.9+ / Flask (Port 5001)                            │
│  - Haar Cascade Face Detection                              │
│  - Multi-person & Gaze Deviation Estimation                 │
│  - Risk Classification & Real-time DB Event Logger          │
└─────────────────────────────────────────────────────────────┘
```

---

### 4. Database Schema Overview (22 Normalized Tables)

The normalized relational database schema ensures integrity with complete Foreign Keys and Cascade rules:
1. `users` — Primary account records and security metadata
2. `user_sessions` — Hashed token active user sessions
3. `password_reset_tokens` — Secure single-use password recovery tokens
4. `rate_limit_log` — IP rate-limiting telemetry
5. `students` — Candidate profiles, institution, roll numbers
6. `examiners` — Faculty profiles, designations, departments
7. `admins` — System administrator records
8. `courses` — Degree programs (e.g. B.Tech CS)
9. `subjects` — Academic course subjects
10. `question_banks` — Categorized question repositories
11. `questions` — Multi-format question definitions
12. `question_options` — Choices for MCQ, Multi-correct, True/False
13. `coding_test_cases` — Test case inputs and expected outputs for code questions
14. `exams` — Examination schedules, rules, proctoring toggles
15. `exam_questions` — M:N mapped exam questions with ordering
16. `exam_enrollments` — Student exam enrollments
17. `exam_attempts` — Candidate exam attempts with shuffled orderings
18. `student_answers` — Real-time saved answer choices and code
19. `results` — Evaluated scores, percentages, and question breakdowns
20. `certificates` — Issued certificates with serial numbers and QR data
21. `proctoring_sessions` — Active webcam sessions and cumulative risk scores
22. `proctoring_events` — Timestamped irregularity audit logs (face missing, tab switch, etc.)
23. `consents` — Granular privacy consent records
24. `notifications` — In-app candidate and faculty notifications
25. `audit_logs` & `security_logs` — System-wide compliance and security logs
26. `system_settings` & `data_retention_policies` — Configurable retention and thresholds

---

### 5. OpenCV Computer Vision Proctoring Pipeline

The Python microservice processes webcam frames through the following pipeline:

```
[Webcam Stream] ──(5s frame)──> [Base64 Decode] ──> [Grayscale & Equalize]
                                                           │
                                                           ▼
                                            [Haar Frontal Face Cascade]
                                                           │
                      ┌────────────────────────────────────┼────────────────────────────────────┐
                      ▼                                    ▼                                    ▼
              [Face Count == 0]                    [Face Count > 1]                     [Face Count == 1]
                      │                                    │                                    │
           (Event: face_missing)               (Event: multiple_faces)                  [Eye Cascade ROI]
           (Severity: MEDIUM)                  (Severity: HIGH)                                 │
                                                                                        [Pose & Gaze Shift]
                                                                                                │
                                                                                       (Event: looking_away)
                                                                                       (Severity: LOW)
```

#### Suspicious Activity Risk Weights:
- **Multiple Faces Detected:** 25 pts (High severity)
- **Face Missing (>15s):** 10 pts (Medium severity)
- **Gaze Deviation / Looking Away:** 5 pts (Low severity)
- **Camera Blocked / Inactive:** 20 pts (High severity)
- **Tab Switching / Window Blur:** 8 pts (Medium severity)
- **Fullscreen Exit:** 10 pts (Medium severity)

**Risk Levels:**
- 0–29: `NORMAL` (Green)
- 30–59: `MEDIUM` (Yellow)
- 60–79: `HIGH` (Orange)
- 80+: `CRITICAL` (Red)

---

### 6. Security Analysis & Protection Mechanisms

1. **SQL Injection:** 100% of database interactions leverage PDO prepared statements with strict parameter binding. No raw query concatenation.
2. **Cross-Site Scripting (XSS):** Context-aware output sanitization with `strip_tags()` on backend ingestion and `textContent` escaping on the client.
3. **Session Hijacking:** Cryptographically secure 64-character session tokens stored hashed via SHA-256 in the database.
4. **Brute Force Defense:** IP-based sliding window rate-limiting (max 20 login attempts per 5 minutes) and automated account lockout after 5 consecutive failures.
5. **Code Execution Sandbox:** Subprocess evaluation of student code with stdout/stderr pipes, execution timeouts (3 seconds), and transient file cleanup.

---

### 7. Ethical Disclaimer & Human-in-the-Loop Principle

This system adheres strictly to the **Human-in-the-Loop** model. Proctoring detections are classified as *suspicious-activity indicators*, not conclusive proof of cheating. Examiners are provided with comprehensive timeline reports, event logs, and candidate contexts so that human academic judgment remains the sole determinant of disciplinary actions.

---

### 8. Conclusion

The **Integrated Online Examination Portal with OpenCV-Based AI-Assisted Proctoring** demonstrates a full-stack, enterprise-grade application suitable for academic institutions. By blending robust web services, computer vision telemetry, and privacy-conscious design, the platform establishes a fair, scalable, and verifiable standard for remote higher-education assessments.
