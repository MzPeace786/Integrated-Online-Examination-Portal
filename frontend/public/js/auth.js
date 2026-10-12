/**
 * Auth state management module
 */
const Auth = {
  USER_KEY:  'ep_user',
  TOKEN_KEY: 'ep_token',

  getUser()  { return JSON.parse(localStorage.getItem(this.USER_KEY) || 'null'); },
  setUser(u) { localStorage.setItem(this.USER_KEY, JSON.stringify(u)); },
  getToken() { return localStorage.getItem(this.TOKEN_KEY); },
  setToken(t){ localStorage.setItem(this.TOKEN_KEY, t); },

  clear() {
    localStorage.removeItem(this.USER_KEY);
    localStorage.removeItem(this.TOKEN_KEY);
  },

  isLoggedIn() { return !!(this.getToken() && this.getUser()); },

  requireAuth(allowedRoles = []) {
    if (!this.isLoggedIn()) {
      const returnUrl = encodeURIComponent(window.location.pathname + window.location.search);
      window.location.href = `/exam-portal/frontend/pages/login.html?return=${returnUrl}`;
      return null;
    }
    const user = this.getUser();
    if (allowedRoles.length > 0 && !allowedRoles.includes(user.role)) {
      this.redirectToRole();
      return null;
    }
    return user;
  },

  requireGuest() {
    if (this.isLoggedIn()) {
      this.redirectToRole();
    }
  },

  redirectToRole() {
    const user = this.getUser();
    if (!user) { window.location.href = '/exam-portal/frontend/pages/login.html'; return; }
    const map = {
      student:  '/exam-portal/frontend/pages/student/dashboard.html',
      examiner: '/exam-portal/frontend/pages/examiner/dashboard.html',
      admin:    '/exam-portal/frontend/pages/admin/dashboard.html',
    };
    window.location.href = map[user.role] || '/exam-portal/frontend/pages/login.html';
  },

  async logout() {
    try { await ExamPortalAPI.auth.logout(); } catch (_) {}
    this.clear();
    window.location.href = '/exam-portal/frontend/pages/login.html';
  },
};
