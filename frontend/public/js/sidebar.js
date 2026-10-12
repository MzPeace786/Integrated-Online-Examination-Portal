/**
 * Dynamic Sidebar Loader & Navigation Handler
 */
const Sidebar = {
  async load(role, activePage) {
    const container = document.getElementById('sidebar-container');
    if (!container) return;

    try {
      const resp = await fetch(`/exam-portal/frontend/components/sidebar-${role}.html`);
      if (!resp.ok) throw new Error('Could not load sidebar template');
      const html = await resp.text();
      container.innerHTML = html;

      this.init(activePage);
    } catch (err) {
      console.error('Sidebar error:', err);
    }
  },

  init(activePage) {
    const user = Auth.getUser();
    if (user) {
      const nameEl = document.getElementById('sidebarUserName');
      const roleEl = document.getElementById('sidebarUserRole');
      const avatarEl = document.getElementById('sidebarAvatar');

      if (nameEl) nameEl.textContent = user.full_name || `${user.first_name || ''} ${user.last_name || ''}`.trim() || user.email;
      if (roleEl) roleEl.textContent = user.role.toUpperCase();
      if (avatarEl && user.profile_photo_path) {
        avatarEl.src = user.profile_photo_path;
      }
    }

    // Set active link
    if (activePage) {
      const link = document.querySelector(`.sidebar-nav a[data-page="${activePage}"]`);
      if (link) link.classList.add('active');
    }

    // Bind logout button
    const logoutBtn = document.getElementById('sidebarLogoutBtn');
    if (logoutBtn) {
      logoutBtn.addEventListener('click', (e) => {
        e.preventDefault();
        Utils.showConfirm('Sign Out', 'Are you sure you want to log out of your session?', () => {
          Auth.logout();
        });
      });
    }

    // Mobile & Half-Screen toggle handlers
    const sidebar = document.getElementById('mainSidebar');
    const overlay = document.getElementById('sidebarOverlay');

    const toggleSidebar = () => {
      if (!sidebar) return;
      sidebar.classList.toggle('open');
      if (overlay) overlay.classList.toggle('active');
    };

    const closeSidebar = () => {
      if (!sidebar) return;
      sidebar.classList.remove('open');
      if (overlay) overlay.classList.remove('active');
    };

    // Bind all possible toggle buttons (header hamburger, sidebar close button, etc.)
    document.querySelectorAll('.sidebar-toggle-btn, #mobileMenuBtn, #sidebarToggleBtn').forEach(btn => {
      btn.removeEventListener('click', toggleSidebar);
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        toggleSidebar();
      });
    });

    if (overlay) {
      overlay.addEventListener('click', closeSidebar);
    }

    // Close on ESC key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeSidebar();
    });
  }
};
