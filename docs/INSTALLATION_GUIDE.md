# Step-by-Step Installation & Deployment Guide

## Integrated Online Examination Portal with OpenCV-Based AI-Assisted Proctoring

---

### System Prerequisites
- **Operating System:** Windows 10/11, macOS, or Linux (Ubuntu 20.04+)
- **PHP:** Version 8.0 or higher with PDO, PDO_MySQL, mbstring, and fileinfo extensions enabled
- **MySQL Database Server:** Version 8.0 or higher (or MariaDB 10.4+)
- **Web Server:** Apache 2.4+ (XAMPP / WAMP / MAMP recommended for local setup)
- **Python:** Version 3.9, 3.10, or 3.11 with `pip`
- **Webcam:** Functional camera device for proctored examination validation

---

### Step 1: Web Server and Project Directory Setup

1. Launch XAMPP / WAMP and start the **Apache** and **MySQL** services.
2. Ensure the project directory is located in your web root:
   - For XAMPP: `C:\xampp\htdocs\exam-portal\`
   - For WAMP: `C:\wamp64\www\exam-portal\`

---

### Step 2: Database Initialization

1. Open your browser and navigate to phpMyAdmin:
   ```
   http://localhost/phpmyadmin/
   ```
2. Open the **SQL** query tab and execute the schema initialization script:
   ```sql
   SOURCE C:/Users/amank/.gemini/antigravity/scratch/exam-portal/backend/sql/schema.sql;
   ```
3. After tables are created, execute the default seed dataset:
   ```sql
   SOURCE C:/Users/amank/.gemini/antigravity/scratch/exam-portal/backend/sql/seed.sql;
   ```

---

### Step 3: Configure Backend Environment

1. Navigate to `backend/config/`
2. Copy `.env.example` to `.env`:
   ```bash
   cp backend/config/.env.example backend/config/.env
   ```
3. Update your database connection parameters if your MySQL root has a password.

---

### Step 4: Configure and Run the Python OpenCV Proctoring Service

1. Open a new terminal in the `proctoring/` directory:
   ```bash
   cd proctoring
   ```
2. Create and activate a dedicated Python virtual environment:
   ```bash
   # Windows
   python -m venv venv
   venv\Scripts\activate

   # Linux / macOS
   python3 -m venv venv
   source venv/bin/activate
   ```
3. Install required computer vision and web microservice dependencies:
   ```bash
   pip install -r requirements.txt
   ```
4. Start the Flask proctoring microservice:
   ```bash
   python app.py
   ```
   The proctoring service will bind to `http://localhost:5001`. You can test service health at `http://localhost:5001/api/proctoring/health`.

---

### Step 5: Verify Portal Access

Open your browser and navigate to:
```
http://localhost/exam-portal/frontend/pages/index.html
```

#### Pre-configured Demo Accounts:
| Role | Email | Password |
|---|---|---|
| **Admin** | `admin@examportal.com` | `Admin@123!` |
| **Examiner** | `examiner@examportal.com` | `Admin@123!` |
| **Student** | `student@examportal.com` | `Admin@123!` |
