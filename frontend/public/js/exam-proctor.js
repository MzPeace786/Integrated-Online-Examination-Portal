/**
 * Client-side Proctoring Module (TCS iON / Professional Exam Standard)
 * - YuNet Deep AI Face & Landmark Tracking HUD
 * - Progressive 5-Strike Real-Time Warning System
 * - Audio Alert Beeper & On-Screen Visual Feedback
 * - Guaranteed Automated Exam Lockdown & Malpractice Termination on Strike 5
 */
const ExamProctor = {
  sessionId:             null,
  attemptId:             null,
  studentId:             null,
  examId:                null,
  webcamStream:          null,
  frameInterval:         null,
  isActive:              false,
  isTerminating:         false,
  
  // 5-Strike System Counters
  totalWarnings:         0,
  MAX_WARNINGS:          5,
  lastStrikeTime:        {},
  STRIKE_COOLDOWN_MS:    3000, // 3s cooldown between strikes of same category
  FRAME_INTERVAL_MS:     900,  // Process frame every 900ms
  proctoringServiceUrl:  'http://localhost:5001',
  audioCtx:              null,

  _boundHandlers: {},

  /**
   * Initialize proctoring for an exam attempt.
   */
  async init(attemptId, studentId, examId, sessionId = null) {
    this.attemptId      = attemptId;
    this.studentId      = studentId;
    this.examId         = examId;
    this.totalWarnings  = 0;
    this.lastStrikeTime = {};
    this.isTerminating  = false;

    // Create or use existing session
    if (sessionId) {
      this.sessionId = sessionId;
    } else {
      try {
        const res = await fetch(`${this.proctoringServiceUrl}/api/proctoring/session/create`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ attempt_id: attemptId, student_id: studentId, exam_id: examId }),
        });
        const data = await res.json();
        this.sessionId = data.session_id;
      } catch (e) {
        try {
          const phpRes = await ExamPortalAPI.proctoring.createSession({ attempt_id: attemptId, student_id: studentId, exam_id: examId });
          this.sessionId = phpRes.data?.session_id;
        } catch (_) {}
      }
    }

    this.isActive = true;
    this.updateCameraStatus('inactive');

    // Create live warning HUD & strike meter
    this.createWarningBannerElement();
    this.createWebcamStatusOverlay();
    this.updateStrikeBadgeDisplay();

    // Request webcam access
    await this.requestWebcam();

    // Set up browser anti-cheat
    this.setupAntiCheat();

    console.log('[Proctor] TCS-grade AI Proctoring Active. Session:', this.sessionId);
  },

  playWarningBeep() {
    try {
      if (!this.audioCtx) {
        this.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
      }
      if (this.audioCtx.state === 'suspended') {
        this.audioCtx.resume();
      }
      const osc = this.audioCtx.createOscillator();
      const gain = this.audioCtx.createGain();
      osc.type = 'sawtooth';
      osc.frequency.setValueAtTime(650, this.audioCtx.currentTime);
      osc.frequency.exponentialRampToValueAtTime(320, this.audioCtx.currentTime + 0.35);
      gain.gain.setValueAtTime(0.3, this.audioCtx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.01, this.audioCtx.currentTime + 0.35);
      osc.connect(gain);
      gain.connect(this.audioCtx.destination);
      osc.start();
      osc.stop(this.audioCtx.currentTime + 0.35);
    } catch (_) {}
  },

  createWarningBannerElement() {
    let banner = document.getElementById('proctorWarningBanner');
    if (!banner) {
      banner = document.createElement('div');
      banner.id = 'proctorWarningBanner';
      banner.style.cssText = `
        position: fixed; top: 65px; left: 50%; transform: translateX(-50%);
        z-index: 99999; width: 92%; max-width: 820px; padding: 14px 22px;
        background: linear-gradient(135deg, #DC2626, #B91C1C); color: #FFFFFF;
        font-weight: 700; font-size: 15px; text-align: center; border-radius: 10px;
        box-shadow: 0 10px 30px rgba(220, 38, 38, 0.45); border: 2px solid #F87171;
        display: none; transition: all 0.25s ease; animation: pulseBanner 1s infinite alternate;
      `;
      document.body.appendChild(banner);
    }
  },

  createWebcamStatusOverlay() {
    const box = document.getElementById('proctorWebcamBox');
    if (box && !document.getElementById('webcamHudOverlay')) {
      const hud = document.createElement('div');
      hud.id = 'webcamHudOverlay';
      hud.style.cssText = `
        position: absolute; bottom: 0; left: 0; right: 0;
        background: rgba(15, 23, 42, 0.85); color: #10B981;
        font-size: 11px; font-weight: 700; padding: 4px 8px;
        display: flex; align-items: center; justify-content: space-between;
        border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;
        z-index: 10;
      `;
      hud.innerHTML = `
        <span id="webcamStatusText"><i class="bi bi-shield-check me-1"></i> AI Active</span>
        <span id="webcamGazeText" style="color:#94A3B8;">Centered</span>
      `;
      box.appendChild(hud);
    }
  },

  updateWebcamHud(status, message, color = '#10B981') {
    const statusText = document.getElementById('webcamStatusText');
    const gazeText = document.getElementById('webcamGazeText');
    const box = document.getElementById('proctorWebcamBox');
    if (statusText && gazeText) {
      statusText.style.color = color;
      statusText.innerHTML = status;
      gazeText.textContent = message;
    }
    if (box) {
      box.style.borderColor = color;
    }
  },

  updateStrikeBadgeDisplay() {
    let badge = document.getElementById('proctorStrikeBadge');
    if (!badge) {
      const topBar = document.querySelector('.exam-topbar-right') || document.querySelector('.exam-topbar .d-flex') || document.querySelector('.exam-topbar');
      if (topBar) {
        badge = document.createElement('div');
        badge.id = 'proctorStrikeBadge';
        badge.className = 'badge px-3 py-2 fw-bold';
        badge.style.fontSize = '13px';
        topBar.prepend(badge);
      }
    }
    if (badge) {
      const strikes = this.totalWarnings;
      if (strikes === 0) {
        badge.className = 'badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fw-semibold';
        badge.innerHTML = `<i class="bi bi-shield-check me-1"></i> Strikes: 0 / ${this.MAX_WARNINGS}`;
      } else if (strikes < 3) {
        badge.className = 'badge bg-warning-subtle text-warning-emphasis border border-warning px-3 py-2 fw-bold';
        badge.innerHTML = `<i class="bi bi-exclamation-triangle me-1"></i> Strikes: ${strikes} / ${this.MAX_WARNINGS}`;
      } else if (strikes < this.MAX_WARNINGS) {
        badge.className = 'badge bg-danger text-white border border-danger px-3 py-2 fw-bold';
        badge.innerHTML = `<i class="bi bi-shield-x me-1"></i> Strikes: ${strikes} / ${this.MAX_WARNINGS} (CRITICAL)`;
      } else {
        badge.className = 'badge bg-dark text-danger border border-danger px-3 py-2 fw-bold';
        badge.innerHTML = `<i class="bi bi-slash-circle me-1"></i> DISQUALIFIED (5 / 5)`;
      }
    }
  },

  showLiveWarningBanner(message, durationMs = 3500) {
    const banner = document.getElementById('proctorWarningBanner');
    if (banner) {
      banner.innerHTML = `<i class="bi bi-exclamation-octagon-fill me-2 fs-5"></i> ${message}`;
      banner.style.display = 'block';
      setTimeout(() => {
        if (!this.isTerminating) {
          banner.style.display = 'none';
        }
      }, durationMs);
    }
  },

  async requestWebcam() {
    try {
      this.webcamStream = await navigator.mediaDevices.getUserMedia({
        video: { width: { ideal: 320 }, height: { ideal: 240 }, facingMode: 'user' },
        audio: false,
      });

      const video = document.getElementById('webcamPreview');
      if (video) {
        video.srcObject = this.webcamStream;
        video.play();
      }

      this.updateCameraStatus('active');

      // AI Frame Loop every 900ms
      this.frameInterval = setInterval(() => this.sendFrameToProctor(), this.FRAME_INTERVAL_MS);

    } catch (err) {
      console.warn('[Proctor] Camera access error:', err.name);
      this.updateCameraStatus('error');
      this.logBrowserEvent('camera_unavailable', `Camera access error: ${err.message}`);
    }
  },

  captureFrame() {
    const video = document.getElementById('webcamPreview');
    if (!video || !this.webcamStream || video.readyState < 2) return null;

    const canvas = document.createElement('canvas');
    canvas.width  = 240;
    canvas.height = 180;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    return canvas.toDataURL('image/jpeg', 0.55);
  },

  registerStrike(category, message, directTermination = false) {
    if (this.isTerminating) return;

    const now = Date.now();
    const last = this.lastStrikeTime[category] || 0;
    if (!directTermination && (now - last < this.STRIKE_COOLDOWN_MS)) {
      return; // Cooldown to ensure progressive, non-spam strikes
    }
    this.lastStrikeTime[category] = now;

    this.totalWarnings++;
    this.updateStrikeBadgeDisplay();
    this.playWarningBeep();

    let prefix = `WARNING (${this.totalWarnings}/${this.MAX_WARNINGS})`;
    if (this.totalWarnings === this.MAX_WARNINGS - 1) {
      prefix = `FINAL WARNING (${this.totalWarnings}/${this.MAX_WARNINGS})`;
    } else if (this.totalWarnings >= this.MAX_WARNINGS) {
      prefix = `DISQUALIFIED (${this.MAX_WARNINGS}/${this.MAX_WARNINGS})`;
    }

    const bannerMsg = `⚠️ ${prefix}: ${message}`;
    this.showLiveWarningBanner(bannerMsg, 3500);
    Utils.showToast(bannerMsg, 'error', 3500);

    if (this.totalWarnings >= this.MAX_WARNINGS || directTermination) {
      this.terminateExamDueToMalpractice(message);
    }
  },

  async sendFrameToProctor() {
    if (!this.isActive || !this.webcamStream || !this.sessionId || this.isTerminating) return;

    const frameBase64 = this.captureFrame();
    if (!frameBase64) return;

    try {
      const res = await fetch(`${this.proctoringServiceUrl}/api/proctoring/frame`, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({
          session_id:    this.sessionId,
          frame_base64:  frameBase64,
          timestamp:     new Date().toISOString(),
        }),
      });

      if (!res.ok) return;
      const data = await res.json();
      const a = data.analysis || {};
      const events = data.events_generated || [];

      // Update HUD & indicator
      this.updateRiskDisplay(data.risk_score);

      // --- 1. Multiple faces / Second person
      if (events.includes('multiple_faces') || a.multiple_faces) {
        this.updateWebcamHud('<i class="bi bi-people-fill"></i> MULTI-PERSON', 'Alert', '#EF4444');
        this.registerStrike('multiple_faces', 'Multiple persons detected in camera view! Only the registered candidate is permitted.');
        return;
      }

      // --- 2. Mobile Device Detected
      if (events.includes('device_detected') || a.device_detected) {
        this.updateWebcamHud('<i class="bi bi-phone"></i> DEVICE DETECTED', 'Prohibited', '#EF4444');
        this.registerStrike('device_detected', 'Unauthorized smartphone / electronic device detected in camera frame!');
        return;
      }

      // --- 3. Gaze Departure / Looking Away
      if (events.includes('looking_away') || a.looking_away) {
        const dir = (a.looking_direction || 'away').toUpperCase();
        this.updateWebcamHud(`<i class="bi bi-eye-slash"></i> LOOKING ${dir}`, 'Deviation', '#F59E0B');
        this.registerStrike('looking_away', `Looking away from exam screen detected (Direction: ${dir}). Keep your focus on screen.`);
        return;
      }

      // --- 4. Face Missing / Left Camera
      if (events.includes('face_missing') || !a.face_present) {
        this.updateWebcamHud('<i class="bi bi-person-x"></i> NO FACE', 'Missing', '#EF4444');
        this.registerStrike('face_missing', 'Face not detected in camera view! Stay positioned directly in front of webcam.');
        return;
      }

      // --- Normal / Centered candidate state
      this.updateWebcamHud('<i class="bi bi-shield-check"></i> VERIFIED', 'Centered', '#10B981');

    } catch (e) {
      // transient connection error
    }
  },

  setupAntiCheat() {
    this._boundHandlers.visibility = () => {
      if (document.visibilityState === 'hidden' && !this.isTerminating) {
        this.logBrowserEvent('tab_switch', `Tab switch / minimized detected`);
        this.registerStrike('tab_switch', 'Tab switch or window minimized detected!');
      }
    };
    document.addEventListener('visibilitychange', this._boundHandlers.visibility);

    this._boundHandlers.fullscreen = () => {
      if (!document.fullscreenElement && !this.isTerminating) {
        this.logBrowserEvent('fullscreen_exit', 'Exam fullscreen mode exited');
        this.registerStrike('fullscreen_exit', 'Fullscreen mode exited! Fullscreen is mandatory during the exam.');
        setTimeout(() => this.enterFullscreen(), 1500);
      }
    };
    document.addEventListener('fullscreenchange', this._boundHandlers.fullscreen);

    this._boundHandlers.blur = () => {
      if (!this.isTerminating) {
        this.logBrowserEvent('window_blur', 'Exam window lost focus');
      }
    };
    window.addEventListener('blur', this._boundHandlers.blur);

    this._boundHandlers.contextmenu = (e) => {
      e.preventDefault();
      this.logBrowserEvent('right_click_attempt', 'Right-click attempted');
      Utils.showToast('Right-click is disabled during examination.', 'warning', 2000);
    };
    document.addEventListener('contextmenu', this._boundHandlers.contextmenu);

    this._boundHandlers.copy = (e) => {
      e.preventDefault();
      this.logBrowserEvent('copy_attempt', 'Copy/cut attempted');
      Utils.showToast('Copying content is prohibited.', 'warning', 2000);
    };
    document.addEventListener('copy', this._boundHandlers.copy);
    document.addEventListener('cut', this._boundHandlers.copy);
  },

  /**
   * Guaranteed Exam Auto-Closure on 5 Strikes / Malpractice.
   */
  async terminateExamDueToMalpractice(reason) {
    if (this.isTerminating) return;
    this.isTerminating = true;
    this.isActive = false;

    console.warn(`[Proctor] 5 STRIKES REACHED — CLOSING & TERMINATING EXAM: ${reason}`);

    // Full-screen unclosable lockdown modal
    let overlay = document.getElementById('malpracticeLockdownModal');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.id = 'malpracticeLockdownModal';
      overlay.style.cssText = `
        position: fixed; inset: 0; z-index: 99999999;
        background: rgba(15, 23, 42, 0.98); backdrop-filter: blur(10px);
        display: flex; align-items: center; justify-content: center;
        padding: 24px; color: #fff; text-align: center;
      `;
      overlay.innerHTML = `
        <div style="max-width: 620px; background: #1E293B; border: 3px solid #EF4444; border-radius: 16px; padding: 40px; box-shadow: 0 25px 50px -12px rgba(239, 68, 68, 0.7);">
          <div style="font-size: 64px; color: #EF4444; margin-bottom: 14px;"><i class="bi bi-shield-x"></i></div>
          <h2 style="font-weight: 800; color: #F87171; margin-bottom: 16px; font-size: 26px;">EXAMINATION TERMINATED & DISQUALIFIED</h2>
          <div style="background: rgba(239, 68, 68, 0.15); border-left: 4px solid #EF4444; padding: 14px 18px; margin-bottom: 20px; border-radius: 6px; text-align: left;">
            <div style="font-size: 13px; text-transform: uppercase; letter-spacing: 1px; color: #FCA5A5; font-weight: 700;">Disqualification Trigger (5 Warning Strikes Exceeded):</div>
            <div style="font-size: 16px; font-weight: 600; color: #FFFFFF; margin-top: 4px;">${reason}</div>
          </div>
          <p style="font-size: 14px; color: #94A3B8; line-height: 1.6; margin-bottom: 24px;">
            The AI OpenCV Proctoring system has recorded 5 proctoring violations. Your examination has been automatically locked, finalized, and marked as Disqualified (Score: 0). An incident audit record has been logged for academic review.
          </p>
          <div class="spinner-border text-danger" role="status" style="width: 2.4rem; height: 2.4rem;"></div>
          <div style="font-size: 14px; color: #CBD5E1; margin-top: 14px; font-weight: 600;">Submitting attempt to server & redirecting to results scorecard...</div>
        </div>
      `;
      document.body.appendChild(overlay);
    }

    // Stop streams
    if (this.frameInterval) { clearInterval(this.frameInterval); this.frameInterval = null; }
    if (this.webcamStream) {
      this.webcamStream.getTracks().forEach(t => t.stop());
      this.webcamStream = null;
    }

    // Log high severity termination event
    try {
      await fetch(`${this.proctoringServiceUrl}/api/proctoring/event`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          session_id:  this.sessionId,
          student_id:  this.studentId,
          exam_id:     this.examId,
          event_type:  'fullscreen_exit',
          description: `EXAMINATION_TERMINATED (5 Strikes Exceeded): ${reason}`
        })
      });
    } catch (_) {}

    // Submit and redirect
    setTimeout(() => {
      if (typeof window.submitFinalExam === 'function') {
        window.submitFinalExam(true, reason);
      } else {
        window.location.href = `/exam-portal/frontend/pages/student/result.html?malpractice=${encodeURIComponent(reason)}`;
      }
    }, 1200);

    // Hard fallback redirect after 3.5s
    setTimeout(() => {
      window.location.href = `/exam-portal/frontend/pages/student/result.html?malpractice=${encodeURIComponent(reason)}`;
    }, 3500);
  },

  logBrowserEvent(eventType, description) {
    ExamPortalAPI.proctoring.logEvent({
      session_id:  this.sessionId,
      student_id:  this.studentId,
      exam_id:     this.examId,
      event_type:  eventType,
      description: description,
    }).catch(() => {});
  },

  enterFullscreen() {
    if (!document.fullscreenElement) {
      document.documentElement.requestFullscreen().catch(() => {});
    }
  },

  updateCameraStatus(status) {
    const el = document.getElementById('cameraStatusIndicator');
    if (!el) return;
    const map = {
      active:   { cls: 'cam-active', text: '🟢 Camera: Active (AI Monitored)' },
      inactive: { cls: '',           text: '⚫ Camera: Inactive' },
      error:    { cls: 'cam-error',  text: '🔴 Camera: Unavailable' },
    };
    const s = map[status] || map.inactive;
    el.className = `camera-indicator ${s.cls}`;
    el.innerHTML = `<span class="cam-dot"></span>${s.text}`;
  },

  updateRiskDisplay(score) {
    const el = document.getElementById('riskScoreDisplay');
    if (!el) return;
    let level = score >= 80 ? 'critical' : score >= 60 ? 'high' : score >= 30 ? 'medium' : 'normal';
    el.innerHTML = Utils.getRiskBadge(level);
    el.title     = `Risk Score: ${score}/100`;
  },

  async stop() {
    this.isActive = false;

    if (this.frameInterval) { clearInterval(this.frameInterval); this.frameInterval = null; }
    if (this.webcamStream) {
      this.webcamStream.getTracks().forEach(t => t.stop());
      this.webcamStream = null;
    }

    const video = document.getElementById('webcamPreview');
    if (video) { video.srcObject = null; }

    document.removeEventListener('visibilitychange', this._boundHandlers.visibility);
    document.removeEventListener('fullscreenchange', this._boundHandlers.fullscreen);
    window.removeEventListener('blur', this._boundHandlers.blur);
    document.removeEventListener('contextmenu', this._boundHandlers.contextmenu);
    document.removeEventListener('copy', this._boundHandlers.copy);
    document.removeEventListener('cut',  this._boundHandlers.copy);

    this.updateCameraStatus('inactive');

    if (this.sessionId) {
      try {
        await fetch(`${this.proctoringServiceUrl}/api/proctoring/session/${this.sessionId}/end`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
        });
      } catch (_) {
        await ExamPortalAPI.proctoring.endSession(this.sessionId).catch(() => {});
      }
    }

    console.log('[Proctor] Stopped');
  },
};
