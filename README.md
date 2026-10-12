# 🎓 Integrated Online Examination Portal with OpenCV-Based AI-Assisted Proctoring

[![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?logo=php)](https://php.net)
[![Python](https://img.shields.io/badge/Python-3.9+-3776AB?logo=python)](https://python.org)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?logo=mysql)](https://mysql.com)
[![OpenCV](https://img.shields.io/badge/OpenCV-4.8+-5C3EE8?logo=opencv)](https://opencv.org)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap)](https://getbootstrap.com)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

> A production-style full-stack web application for secure online examinations with automated AI-assisted proctoring. Built as a B.Tech 7th Semester Computer Science major project.

---

## 📋 Table of Contents

- [Project Overview](#project-overview)
- [Key Features](#key-features)
- [System Architecture](#system-architecture)
- [Technology Stack](#technology-stack)
- [Module Overview](#module-overview)
- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Configuration](#configuration)
- [Demo Credentials](#demo-credentials)
- [How to Run](#how-to-run)
- [API Reference](#api-reference)
- [OpenCV Proctoring](#opencv-proctoring)
- [Security Features](#security-features)
- [Privacy & Consent](#privacy--consent)
- [Project Structure](#project-structure)
- [Limitations](#limitations)
- [Future Scope](#future-scope)

---

## Project Overview

The **Integrated Online Examination Portal** is a complete web-based examination management system designed for educational institutions. It enables students to take scheduled online examinations while administrators and examiners manage question banks, examinations, and results.

The system integrates an **OpenCV-based AI-assisted proctoring service** that monitors students via webcam during proctored examinations. Proctoring generates suspicious-activity indicators (not definitive determinations) that examiners can review before taking any action.

This project demonstrates:
- Full-stack web development with PHP REST APIs
- Relational database design with MySQL
- Computer vision with OpenCV
- Microservice architecture (Python proctoring service)
- Role-based access control and security best practices
- Privacy-by-design principles

---

## Key Features

### 🎯 Student Features
- Self-registration with multi-step form
- Dashboard with performance analytics and charts
- View and take enrolled examinations
- Auto-save answers (survives refresh/reconnect)
- Countdown timer with auto-submit
- Mark questions for review
- Detailed result with answer analysis
- Performance analytics with Chart.js
- Certificate download and sharing
- Privacy dashboard and consent management

### 👨‍🏫 Examiner Features
- Create and schedule examinations
- Question bank management (6 question types)
- Random question and option ordering
- Live student attempt monitoring
- Detailed proctoring reports with event timeline
- Performance analytics per exam

### ⚙️ Admin Features
- Complete user management (students, examiners)
- Subject and course management
- System-wide exam and result management
- **Live proctoring dashboard** (auto-refresh)
- Audit logs and security event logs
- Data retention policy management
- Certificate management and revocation
- System settings configuration

### 🤖 AI-Assisted Proctoring
- Webcam-based face detection (OpenCV Haar cascades)
- Multiple face detection
- Face absence detection
- Basic gaze/head orientation estimation (heuristic)
- Browser-level anti-cheat detection (tab switch, fullscreen exit)
- Risk scoring (0–100)
- Event timeline with severity levels
- Proctoring disclosure and explicit consent

---

## System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                        BROWSER CLIENT                        │
│  HTML5 + Bootstrap 5 + Vanilla JS + Chart.js                │
└──────────────────────┬──────────────────────────────────────┘
                       │ HTTP/REST (JSON)
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                   PHP BACKEND API (Apache)                   │
│  /backend/api/index.php  →  Route handlers                  │
│  Auth | Student | Examiner | Admin | Proctoring             │
│  Certificate | Code Execution                               │
└──────────────────────┬──────────────────────────────────────┘
                       │ PDO (parameterized queries)
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                       MySQL 8.0+                            │
│  22 tables | Proper indexes | Foreign keys                  │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│                   PROCTORING SERVICE                         │
│  Python 3 + Flask + OpenCV                                  │
│  Runs on: http://localhost:5001                             │
│  Frame analysis → Event logging → Risk scoring              │
└──────────────────────┬──────────────────────────────────────┘
                       │ Writes events to MySQL
                       ▼
                  MySQL Database
```

---

## Technology Stack

| Layer | Technology | Version |
|-------|-----------|---------|
| Frontend | HTML5, CSS3, JavaScript (ES6+) | - |
| UI Framework | Bootstrap | 5.3 |
| Charts | Chart.js | 4.4 |
| Backend | PHP | 8.0+ |
| Database | MySQL | 8.0+ |
| Proctoring | Python + OpenCV | 3.9+ / 4.8+ |
| Web Server | Apache (XAMPP/WAMP) | 2.4+ |
| Proctoring API | Flask | 3.0 |

---

## Module Overview

| Module | Description |
|--------|-------------|
| Authentication | Register, login, logout, password reset, sessions |
| Student Portal | Dashboard, exams, attempts, results, certificates, analytics |
| Examiner Portal | Exam creation, question banks, attempt monitoring |
| Admin Portal | User management, system settings, live proctoring |
| Question Bank | MCQ, Multiple Correct, True/False, Fill Blank, Coding |
| Proctoring | OpenCV face detection, event logging, risk scoring |
| Certificates | Auto-generation, QR verification |
| Analytics | Charts for performance, subject-wise, trend analysis |
| Privacy | Consent management, data deletion requests, privacy dashboard |
| Security | Audit logs, security event logs, rate limiting |

---

## Prerequisites

- **PHP** 8.0 or higher with PDO, PDO_MySQL, mbstring, fileinfo extensions
- **MySQL** 8.0 or higher
- **Apache** web server (XAMPP or WAMP recommended on Windows)
- **Python** 3.9 or higher (for proctoring service)
- A modern web browser with camera access support

---

## Installation

### Step 1: Set Up XAMPP/WAMP

1. Download and install [XAMPP](https://www.apachefriends.org/) or [WAMP](https://www.wampserver.com/)
2. Start Apache and MySQL from the control panel

### Step 2: Place Project Files

Copy the entire `exam-portal` folder to your web server root:
- XAMPP: `C:\xampp\htdocs\exam-portal\`
- WAMP: `C:\wamp64\www\exam-portal\`

### Step 3: Set Up Database

Open phpMyAdmin (`http://localhost/phpmyadmin`) and:

```sql
-- Import the schema
SOURCE /path/to/exam-portal/backend/sql/schema.sql;

-- Import seed data
SOURCE /path/to/exam-portal/backend/sql/seed.sql;
```

Or use phpMyAdmin's Import feature to import both SQL files in order.

### Step 4: Configure Backend

```bash
# Copy and edit the environment file
cp backend/config/.env.example backend/config/.env
```

Edit `backend/config/.env`:
```env
APP_NAME=ExamPortal
APP_URL=http://localhost/exam-portal
APP_ENV=development
DB_HOST=localhost
DB_NAME=exam_portal
DB_USER=root
DB_PASSWORD=          # your MySQL password
```

### Step 5: Set Up Python Proctoring Service

```bash
cd exam-portal/proctoring

# Create virtual environment
python -m venv venv

# Activate (Windows)
venv\Scripts\activate

# Install dependencies
pip install -r requirements.txt

# Configure environment
cp .env.example .env
# Edit .env with your DB credentials
```

### Step 6: Create Upload Directories

The `uploads/` and `logs/` directories are created automatically on first request.
Ensure Apache has write permissions to the project folder.

---

## Configuration

### Backend (`backend/config/.env`)

| Key | Default | Description |
|-----|---------|-------------|
| `APP_URL` | `http://localhost/exam-portal` | Application base URL |
| `DB_HOST` | `localhost` | MySQL host |
| `DB_NAME` | `exam_portal` | Database name |
| `DB_USER` | `root` | Database user |
| `DB_PASSWORD` | *(empty)* | Database password |
| `SESSION_TIMEOUT` | `7200` | Session timeout in seconds |
| `MAX_LOGIN_ATTEMPTS` | `5` | Failed attempts before lockout |
| `LOCKOUT_DURATION` | `900` | Lockout duration in seconds |
| `PROCTORING_SERVICE_URL` | `http://localhost:5001` | Python proctoring service URL |
| `PROCTORING_API_KEY` | *(empty)* | Shared API key for proctoring service |

### Proctoring Service (`proctoring/.env`)

| Key | Default | Description |
|-----|---------|-------------|
| `PROCTORING_PORT` | `5001` | Port for Flask service |
| `SNAPSHOT_ENABLED` | `false` | Enable snapshot capture (disclose to students first) |
| `LOOKING_AWAY_THRESHOLD_SECONDS` | `10` | Seconds before "looking away" event |
| `FACE_MISSING_THRESHOLD_SECONDS` | `15` | Seconds before "face missing" event |

---

## Demo Credentials

> ⚠️ Change all passwords before deploying to any public server.

| Role | Email | Password |
|------|-------|----------|
| **Admin** | admin@examportal.com | Admin@123! |
| **Examiner** | examiner@examportal.com | Admin@123! |
| **Student** | student@examportal.com | Admin@123! |

---

## How to Run

### 1. Start Web Server
Start Apache and MySQL in XAMPP/WAMP Control Panel.

### 2. Access the Application
Open your browser and navigate to:
```
http://localhost/exam-portal/frontend/pages/index.html
```

### 3. Start Proctoring Service (for proctored exams)
```bash
cd exam-portal/proctoring
# Activate virtual environment
venv\Scripts\activate       # Windows
source venv/bin/activate    # Linux/Mac

# Start service
python app.py
```
The proctoring service will start at `http://localhost:5001`

### 4. Verify Everything Works
- Visit `http://localhost:5001/api/proctoring/health` — should return `{"status": "ok"}`
- Log in as admin at `http://localhost/exam-portal/frontend/pages/login.html`

---

## API Reference

### Base URL
```
http://localhost/exam-portal/backend/api/
```

### Authentication Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/auth/register` | Student registration |
| POST | `/auth/login` | Login (returns token) |
| POST | `/auth/logout` | Logout (invalidate session) |
| POST | `/auth/forgot-password` | Request password reset |
| POST | `/auth/reset-password` | Reset password with token |
| GET  | `/auth/me` | Get current user profile |

### Student Endpoints (Bearer token required)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/student/dashboard` | Dashboard stats and data |
| GET/PUT | `/student/profile` | View/update profile |
| GET | `/student/exams` | List enrolled exams |
| GET | `/student/exams/{id}` | Exam instructions |
| POST | `/student/exams/{id}/start` | Start exam attempt |
| POST | `/student/attempts/{id}/answer` | Auto-save answer |
| POST | `/student/attempts/{id}/submit` | Submit exam |
| GET | `/student/results` | List results |
| GET | `/student/results/{id}` | Detailed result + analysis |
| GET | `/student/analytics` | Performance analytics |
| GET | `/student/certificates` | List certificates |
| POST | `/student/consent` | Record consent |
| GET | `/student/privacy` | Privacy dashboard data |

### Proctoring Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/proctoring/session` | Create session |
| PUT | `/proctoring/session/{id}/end` | End session |
| POST | `/proctoring/events` | Log browser event |
| GET | `/proctoring/live` | Live dashboard (admin/examiner) |

### Authentication
All authenticated endpoints require:
```
Authorization: Bearer <session_token>
```

---

## OpenCV Proctoring

### How It Works

The proctoring service (`proctoring/app.py`) runs as a separate Python process. During a proctored exam, the browser captures webcam frames every 5 seconds and sends them to this service as base64-encoded JPEG images.

**Detection capabilities:**
1. **Face Detection** — Haar cascade classifier (`haarcascade_frontalface_default.xml`)
2. **Multiple Face Detection** — Counts faces; flags >1 as suspicious
3. **Face Absence** — Tracks time since face last seen
4. **Gaze Estimation** — Heuristic based on eye detection count and face aspect ratio
5. **Identity Comparison** — Histogram correlation (NOT biometric verification)

### Risk Scoring

| Event | Weight |
|-------|--------|
| Face Missing | 10 pts/occurrence |
| Multiple Faces | 25 pts/occurrence |
| Looking Away | 5 pts/occurrence |
| Camera Unavailable | 20 pts/occurrence |
| Identity Mismatch | 30 pts/occurrence |
| Tab Switch | 8 pts/occurrence |
| Fullscreen Exit | 10 pts/occurrence |
| Window Blur | 3 pts/occurrence |

Risk Level: Low (0-29) | Medium (30-59) | High (60-79) | Critical (80+)

### ⚠️ Important Ethical Disclaimer

> The proctoring system is an **AI-assisted suspicious-activity indicator**, NOT a system that definitively detects cheating. All proctoring alerts must be reviewed by a human examiner. Automated detection alone must not be used as the basis for disciplinary decisions. The system has significant limitations including false positives due to lighting conditions, camera quality, and the basic heuristic nature of the algorithms used.

---

## Security Features

- **Password hashing**: bcrypt with cost factor 12
- **Session management**: Cryptographically secure 64-char tokens, server-side invalidation
- **SQL injection prevention**: PDO prepared statements throughout
- **XSS prevention**: Input sanitization, `strip_tags()`
- **Rate limiting**: IP-based limits on login (20/5min) and registration (10/hr)
- **Account lockout**: Configurable failed attempt threshold
- **Role-based access control**: Student, Examiner, Admin roles enforced server-side
- **Audit logging**: All significant actions logged with user, IP, timestamp
- **Security logging**: Login attempts, lockouts, suspicious activity
- **Password reset**: Secure token with 1-hour expiry, one-time use

---

## Privacy & Consent

The system implements **Privacy by Design**:

- Explicit, granular consent before webcam access (not pre-checked)
- Separate consent for exam rules, proctoring, data processing, and optional email notifications
- Students can view their consent status in the Privacy Dashboard
- Configurable data retention policies
- Data deletion request mechanism
- Proctoring data access restricted to assigned examiner/admin
- No audio recording; no location tracking
- Snapshots disabled by default (must be explicitly enabled and disclosed)

See `docs/PRIVACY_POLICY.md` for full details.

---

## Project Structure

```
exam-portal/
├── backend/
│   ├── api/
│   │   ├── index.php               # Main router
│   │   ├── auth/                   # login, register, logout, reset
│   │   ├── student/                # dashboard, exams, attempts, results
│   │   ├── examiner/               # exams, questions, analytics
│   │   ├── admin/                  # users, settings, audit logs
│   │   ├── proctoring/             # session, events, live, risk
│   │   └── certificate/            # verify
│   ├── config/
│   │   ├── env.php                 # .env loader
│   │   ├── config.php              # App constants
│   │   ├── database.php            # PDO singleton
│   │   └── .env.example
│   ├── middleware/
│   │   ├── Auth.php                # Session-based authentication
│   │   ├── RateLimit.php           # IP rate limiting
│   │   └── InputValidator.php      # Input sanitization
│   ├── utils/
│   │   ├── Response.php            # JSON response helpers
│   │   ├── Logger.php              # Audit + security logging
│   │   ├── PasswordHelper.php      # bcrypt + token utilities
│   │   ├── CertificateGenerator.php # HTML certificate generation
│   │   └── NotificationHelper.php  # In-app + email notifications
│   └── sql/
│       ├── schema.sql              # Complete database schema (22 tables)
│       └── seed.sql                # Demo accounts + initial data
├── frontend/
│   ├── public/
│   │   ├── css/style.css           # Custom CSS design system
│   │   └── js/
│   │       ├── api.js              # API client
│   │       ├── auth.js             # Auth state management
│   │       ├── utils.js            # Utilities (toast, modal, timer)
│   │       ├── charts.js           # Chart.js wrappers
│   │       └── exam-proctor.js     # Client-side proctoring
│   ├── pages/
│   │   ├── index.html              # Landing page
│   │   ├── login.html              # Login
│   │   ├── register.html           # Student registration
│   │   ├── forgot-password.html
│   │   ├── reset-password.html
│   │   ├── privacy-policy.html
│   │   ├── terms.html
│   │   ├── verify-certificate.html
│   │   ├── student/
│   │   │   ├── dashboard.html
│   │   │   ├── exams.html
│   │   │   ├── exam-instructions.html
│   │   │   ├── exam.html           # Main examination interface
│   │   │   ├── result.html
│   │   │   ├── history.html
│   │   │   ├── analytics.html
│   │   │   ├── certificates.html
│   │   │   ├── notifications.html
│   │   │   ├── profile.html
│   │   │   └── privacy.html
│   │   ├── examiner/
│   │   │   ├── dashboard.html
│   │   │   ├── create-exam.html
│   │   │   ├── manage-exams.html
│   │   │   ├── question-bank.html
│   │   │   ├── question-list.html
│   │   │   ├── create-question.html
│   │   │   ├── exam-attempts.html
│   │   │   ├── results.html
│   │   │   ├── analytics.html
│   │   │   └── proctoring-reports.html
│   │   └── admin/
│   │       ├── dashboard.html
│   │       ├── user-management.html
│   │       ├── exam-management.html
│   │       ├── subject-management.html
│   │       ├── results.html
│   │       ├── certificates.html
│   │       ├── live-proctoring.html
│   │       ├── proctoring-reports.html
│   │       ├── audit-logs.html
│   │       ├── security-logs.html
│   │       └── settings.html
│   └── components/
│       ├── sidebar-student.html
│       ├── sidebar-examiner.html
│       └── sidebar-admin.html
├── proctoring/
│   ├── app.py                      # Flask application (main entry point)
│   ├── face_detector.py            # OpenCV detection module
│   ├── database.py                 # MySQL helper
│   ├── requirements.txt
│   └── .env.example
├── docs/
│   ├── PROJECT_DOCUMENTATION.md    # Full 28-section project report
│   ├── PRIVACY_POLICY.md
│   ├── PROCTORING_DISCLOSURE.md
│   ├── DATA_RETENTION_POLICY.md
│   ├── SECURITY_POLICY.md
│   ├── TERMS_AND_CONDITIONS.md
│   ├── API_REFERENCE.md
│   └── INSTALLATION_GUIDE.md
├── uploads/                        # File uploads (auto-created)
├── logs/                           # Application logs (auto-created)
└── README.md
```

---

## Limitations

1. **Proctoring accuracy**: Haar cascade face detection is sensitive to lighting and camera angle. Expect false positives.
2. **Gaze estimation**: The head pose estimation is a basic heuristic, not a precise eye-tracking system.
3. **Identity verification**: Histogram comparison is NOT biometric face recognition.
4. **Code execution**: Basic subprocess-based execution; production systems should use Docker containers.
5. **Email**: Uses PHP `mail()` — configure SMTP server for reliable delivery.
6. **Scalability**: Single-server architecture; Redis session storage and load balancing required for scale.
7. **No WebRTC**: Live video streaming not implemented; frame-by-frame analysis only.

---

## Future Scope

- Deep-learning-based face detection (MTCNN, RetinaFace)
- Real gaze tracking with dlib 68-point landmarks
- Voice activity detection for suspicious audio events
- Docker containerization for code execution sandbox
- Real-time video streaming with WebRTC
- Mobile application (React Native)
- AI-generated adaptive questions
- LMS integration (Moodle, Google Classroom)
- Multi-institution support with tenant isolation
- Redis-based session storage for scalability
- Cloud deployment with auto-scaling

---

## Authors

**Department of Computer Science and Engineering**  
7th Semester Major Project — B.Tech Computer Science

---

## License

This project is for educational purposes. See [LICENSE](LICENSE) for details.
