# REST API Specification & Endpoint Documentation

## Base Endpoint URL
```
http://localhost/exam-portal/backend/api
```

All protected requests must supply the session token in the authorization header:
```http
Authorization: Bearer <SESSION_TOKEN_HASH_HEX>
```

---

### 1. Authentication Endpoints

#### `POST /auth/register`
Creates a candidate student account.
- **Body:** `{ first_name, last_name, email, password, confirm_password, department, semester, ... }`
- **Response 201:** `{ success: true, message: "Account created successfully" }`

#### `POST /auth/login`
Authenticates a user and generates a 64-character session token.
- **Body:** `{ email, password }`
- **Response 200:** `{ success: true, data: { token: "...", user: { id, email, role, ... } } }`

#### `POST /auth/logout`
Terminates active session token.
- **Response 200:** `{ success: true, message: "Logged out successfully" }`

#### `GET /auth/me`
Fetches current authenticated profile and notification count.

---

### 2. Student Endpoints

| Method | Route | Description |
|---|---|---|
| `GET` | `/student/dashboard` | Dashboard metrics, scheduled exams, recent results, line charts |
| `GET` | `/student/profile` | Student profile details |
| `POST` | `/student/profile` | Update profile attributes and avatar upload |
| `GET` | `/student/exams` | Paginated enrolled exams |
| `GET` | `/student/exams/{id}` | Exam instructions, proctoring requirements, attempt status |
| `POST` | `/student/exams/{id}/start` | Initialize examination session attempt and question array |
| `POST` | `/student/attempts/{id}/answer` | Real-time answer autosave |
| `POST` | `/student/attempts/{id}/submit` | Final assessment submission, auto-evaluation, certificate generation |
| `GET` | `/student/results` | Paginated candidate results ledger |
| `GET` | `/student/results/{id}` | Detailed scorecard, question analysis, solution explanations |
| `GET` | `/student/certificates` | Earned certificates gallery |
| `GET` | `/student/certificates/{id}/download` | Print-ready HTML certificate template |
| `GET` | `/student/analytics` | Trend analytics, subject comparisons, accuracy ratio |
| `GET` | `/student/privacy` | Privacy overview, active consents, subject deletion requests |
| `POST` | `/student/privacy/request-deletion` | File account or proctoring data deletion request |

---

### 3. Examiner Endpoints

| Method | Route | Description |
|---|---|---|
| `GET` | `/examiner/dashboard` | Faculty overview, exam counts, high-risk alert ticker |
| `GET` | `/examiner/exams` | Managed examinations list |
| `POST` | `/examiner/exams` | Create examination with proctoring & security toggles |
| `PUT` | `/examiner/exams/{id}` | Update examination parameters |
| `DELETE` | `/examiner/exams/{id}` | Delete examination |
| `GET` | `/examiner/exams/{id}/attempts` | Candidate attempts ledger with AI risk ratings |
| `GET` | `/examiner/question-banks` | Question repositories list |
| `POST` | `/examiner/question-banks` | Create question bank |
| `GET` | `/examiner/questions/{id}` | Single question detail with options & test cases |
| `POST` | `/examiner/questions` | Create question (MCQ, Multi, TF, Fill, Coding) |
| `DELETE` | `/examiner/questions/{id}` | Soft delete question |
| `GET` | `/examiner/proctoring-reports` | Full candidate proctoring audit reports |

---

### 4. Admin Endpoints

| Method | Route | Description |
|---|---|---|
| `GET` | `/admin/dashboard` | System statistics and real-time audit logs |
| `GET` | `/admin/users` | Full user directory with role filters |
| `POST` | `/admin/users` | Administrative user account provisioning |
| `DELETE` | `/admin/users/{id}` | Deactivate user account |
| `GET` | `/admin/audit-logs` | Comprehensive compliance audit trail |
| `GET` | `/admin/security-logs` | Brute force attempts and security incident logs |
| `GET` | `/admin/settings` | System-wide configuration parameters |
| `PUT` | `/admin/settings` | Update system setting |
| `GET` | `/admin/deletion-requests` | Candidate privacy data deletion queue |
| `PUT` | `/admin/deletion-requests/{id}` | Approve or reject data deletion request |

---

### 5. Computer Vision Proctoring Service Endpoints (Port 5001)

| Method | Route | Description |
|---|---|---|
| `GET` | `/api/proctoring/health` | Service health status and OpenCV availability |
| `POST` | `/api/proctoring/session/create` | Open webcam proctoring session |
| `POST` | `/api/proctoring/session/{id}/end` | Close session and finalize risk calculation |
| `POST` | `/api/proctoring/frame` | Ingest base64 webcam frame (~5s), return face count & gaze deviation |
| `POST` | `/api/proctoring/event` | Log browser anti-cheat events (tab switch, blur, fullscreen) |
| `GET` | `/api/proctoring/live` | Stream telemetry of active online candidates |
| `GET` | `/api/proctoring/session/{id}/report` | Session irregularity timeline & human-readable event audit |
