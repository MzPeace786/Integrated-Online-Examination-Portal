/**
 * ExamPortal API Client
 * Wraps all backend REST endpoints with auth token support.
 */
const ExamPortalAPI = (function () {
  const BASE_URL = '/exam-portal/backend/api';

  async function request(method, endpoint, data = null, isFormData = false) {
    const token = localStorage.getItem('ep_token');
    const headers = {};

    if (token) headers['Authorization'] = `Bearer ${token}`;
    if (!isFormData) headers['Content-Type'] = 'application/json';

    const options = { method, headers, credentials: 'same-origin' };

    if (data) {
      if (isFormData) {
        options.body = data instanceof FormData ? data : (() => {
          const fd = new FormData();
          Object.entries(data).forEach(([k, v]) => fd.append(k, v));
          return fd;
        })();
      } else if (method !== 'GET') {
        options.body = JSON.stringify(data);
      }
    }

    try {
      const response = await fetch(`${BASE_URL}${endpoint}`, options);

      // 401 → redirect to login
      if (response.status === 401) {
        localStorage.removeItem('ep_token');
        localStorage.removeItem('ep_user');
        const returnUrl = encodeURIComponent(window.location.pathname + window.location.search);
        window.location.href = `/exam-portal/frontend/pages/login.html?return=${returnUrl}`;
        return;
      }

      const json = await response.json();

      if (!response.ok) {
        const err = new Error(json.message || `HTTP ${response.status}`);
        err.status = response.status;
        err.data   = json;
        throw err;
      }

      return json;
    } catch (err) {
      if (err.name === 'TypeError') {
        throw new Error('Network error — please check your connection');
      }
      throw err;
    }
  }

  const get    = (endpoint, params) => {
    const qs = params ? '?' + new URLSearchParams(params).toString() : '';
    return request('GET', endpoint + qs);
  };
  const post   = (endpoint, data) => request('POST', endpoint, data);
  const put    = (endpoint, data) => request('PUT', endpoint, data);
  const del    = (endpoint)       => request('DELETE', endpoint);
  const postForm = (endpoint, data) => request('POST', endpoint, data, true);

  return {
    auth: {
      register:      (d) => post('/auth/register', d),
      login:         (d) => post('/auth/login', d),
      logout:        ()  => post('/auth/logout'),
      forgotPassword:(e) => post('/auth/forgot-password', { email: e }),
      resetPassword: (t, p, cp) => post('/auth/reset-password', { token: t, password: p, confirm_password: cp }),
      me:            ()  => get('/auth/me'),
    },

    student: {
      dashboard:          ()        => get('/student/dashboard'),
      getProfile:         ()        => get('/student/profile'),
      updateProfile:      (d)       => postForm('/student/profile', d),
      getExams:           (p)       => get('/student/exams', p),
      getExamInstructions:(id)      => get(`/student/exams/${id}`),
      startExam:          (id)      => post(`/student/exams/${id}/start`),
      saveAnswer:         (id, d)   => post(`/student/attempts/${id}/answer`, d),
      submitExam:         (id, d)   => post(`/student/attempts/${id}/submit`, d),
      getResults:         (p)       => get('/student/results', p),
      getResult:          (id)      => get(`/student/results/${id}`),
      getCertificates:    ()        => get('/student/certificates'),
      downloadCertificate:(id)      => get(`/student/certificates/${id}/download`),
      getAnalytics:       ()        => get('/student/analytics'),
      getNotifications:   (p)       => get('/student/notifications', p),
      markRead:           (id)      => put(`/student/notifications/${id}/read`),
      markAllRead:        ()        => put('/student/notifications/read-all'),
      recordConsent:      (d)       => post('/student/consent', d),
      getConsent:         (examId)  => get('/student/consent', { exam_id: examId }),
      withdrawConsent:    (id)      => del(`/student/consent/${id}`),
      getPrivacyData:     ()        => get('/student/privacy'),
      requestDeletion:    (d)       => post('/student/privacy/request-deletion', d),
    },

    examiner: {
      dashboard:          ()          => get('/examiner/dashboard'),
      getExams:           (p)         => get('/examiner/exams', p),
      createExam:         (d)         => post('/examiner/exams', d),
      updateExam:         (id, d)     => put(`/examiner/exams/${id}`, d),
      deleteExam:         (id)        => del(`/examiner/exams/${id}`),
      getExamQuestions:   (examId)    => get(`/examiner/exams/${examId}/questions`),
      addQuestion:        (examId, d) => post(`/examiner/exams/${examId}/questions`, d),
      removeQuestion:     (examId, qId) => del(`/examiner/exams/${examId}/questions/${qId}`),
      enrollStudents:     (examId, d) => post(`/examiner/exams/${examId}/enroll`, d),
      getAttempts:        (examId)    => get(`/examiner/exams/${examId}/attempts`),
      getQuestionBanks:   ()          => get('/examiner/question-banks'),
      createQuestionBank: (d)         => post('/examiner/question-banks', d),
      getQuestions:       (bankId, p) => get(`/examiner/question-banks/${bankId}/questions`, p),
      createQuestion:     (d)         => post('/examiner/questions', d),
      suggestQuestions:   (d)         => post('/examiner/questions/suggest', d),
      bulkImportQuestions:(d)         => post('/examiner/questions/bulk-import', d),
      updateQuestion:     (id, d)     => put(`/examiner/questions/${id}`, d),
      deleteQuestion:     (id)        => del(`/examiner/questions/${id}`),
      getResults:         (p)         => get('/examiner/results', p),
      getAnalytics:       (p)         => get('/examiner/analytics', p),
      getProctoringReports:(p)        => get('/examiner/proctoring-reports', p),
    },

    admin: {
      dashboard:            ()        => get('/admin/dashboard'),
      getUsers:             (p)       => get('/admin/users', p),
      createUser:           (d)       => post('/admin/users', d),
      updateUser:           (id, d)   => put(`/admin/users/${id}`, d),
      deleteUser:           (id)      => del(`/admin/users/${id}`),
      getSubjects:          ()        => get('/admin/subjects'),
      createSubject:        (d)       => post('/admin/subjects', d),
      updateSubject:        (id, d)   => put(`/admin/subjects/${id}`, d),
      deleteSubject:        (id)      => del(`/admin/subjects/${id}`),
      getCourses:           ()        => get('/admin/courses'),
      createCourse:         (d)       => post('/admin/courses', d),
      updateCourse:         (id, d)   => put(`/admin/courses/${id}`, d),
      deleteCourse:         (id)      => del(`/admin/courses/${id}`),
      getExams:             (p)       => get('/admin/exams', p),
      updateExam:           (id, d)   => put(`/admin/exams/${id}`, d),
      getResults:           (p)       => get('/admin/results', p),
      updateResult:         (id, d)   => put(`/admin/results/${id}`, d),
      getCertificates:      (p)       => get('/admin/certificates', p),
      revokeCertificate:    (id, d)   => put(`/admin/certificates/${id}/revoke`, d),
      getAuditLogs:         (p)       => get('/admin/audit-logs', p),
      getSecurityLogs:      (p)       => get('/admin/security-logs', p),
      getSettings:          ()        => get('/admin/settings'),
      updateSetting:        (k, v)    => put('/admin/settings', { key: k, value: v }),
      getRetentionPolicies: ()        => get('/admin/retention'),
      updateRetentionPolicy:(id, d)   => put(`/admin/retention/${id}`, d),
      runCleanup:           ()        => post('/admin/retention/run'),
      getDeletionRequests:  ()        => get('/admin/deletion-requests'),
      processDeletion:      (id, d)   => put(`/admin/deletion-requests/${id}`, d),
      getProctoringReports: (p)       => get('/examiner/proctoring-reports', p),
    },

    proctoring: {
      createSession: (d)       => post('/proctoring/session', d),
      endSession:    (id)      => put(`/proctoring/session/${id}/end`),
      logEvent:      (d)       => post('/proctoring/events', d),
      getLive:       ()        => get('/proctoring/live'),
      getReport:     (id)      => get(`/proctoring/session/${id}/report`),
      getRiskScore:  (atmpId)  => get('/proctoring/risk-score', { attempt_id: atmpId }),
    },

    certificate: {
      verify: (certNum) => get('/certificate/verify', { cert_number: certNum }),
    },
  };
})();
