# Security & Compliance Architecture Policy

## Threat Modeling & Mitigation Matrix

| Threat Category | Potential Vector | Engineering Mitigation Implemented |
|---|---|---|
| **SQL Injection (SQLi)** | Malicious string concatenation in user inputs, IDs, or search queries. | 100% Prepared Statements via PHP PDO. Strict typed parameter binding and input validation. |
| **Cross-Site Scripting (XSS)** | Injection of scripts in question text, student submissions, or user profiles. | `strip_tags()` sanitization on input ingestion and safe text DOM insertion (`textContent` escaping) on client output. |
| **Brute Force Attacks** | Password guessing on authentication endpoints. | Sliding window rate limiting (max 20 attempts/5 min per IP) and automatic account lockout after 5 consecutive failures for 15 minutes. |
| **Session Hijacking** | Stealing session tokens over the network or cross-site. | 64-character cryptographically secure hex tokens hashed with SHA-256 in the database. Tokens refreshed upon legitimate activity. |
| **Arbitrary Code Execution** | Malicious code submitted in coding evaluation assessments. | Subprocess execution with standard I/O pipes, restricted memory limits, 3-second hard timeout limits, and temporary workspace directories. |
| **Impersonation & Cheating** | Candidate leaving room, multiple individuals providing assistance. | OpenCV Haar cascade face detection microservice logging `face_missing`, `multiple_faces`, and `looking_away` with human examiner review. |
| **Unauthorized Data Access** | Students viewing other candidates' scorecards or questions in advance. | Strict role-based middleware (`Auth::authorize(['role'])`) verifying user IDs on every single resource request. |
