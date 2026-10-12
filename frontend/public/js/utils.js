/**
 * Utility functions for ExamPortal
 */
const Utils = {

  // --- Date/Time ---
  formatDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
  },

  formatDateTime(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
      + ', ' + d.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
  },

  formatTime(totalSeconds) {
    const h = Math.floor(totalSeconds / 3600);
    const m = Math.floor((totalSeconds % 3600) / 60);
    const s = totalSeconds % 60;
    return [h, m, s].map(v => String(v).padStart(2, '0')).join(':');
  },

  timeAgo(dateStr) {
    const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
    if (diff < 60)   return `${diff}s ago`;
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400)return `${Math.floor(diff / 3600)}h ago`;
    return `${Math.floor(diff / 86400)}d ago`;
  },

  startCountdown(targetDateStr, onTick, onComplete) {
    const target = new Date(targetDateStr).getTime();
    const tick = () => {
      const remaining = Math.max(0, Math.floor((target - Date.now()) / 1000));
      const h = Math.floor(remaining / 3600);
      const m = Math.floor((remaining % 3600) / 60);
      const s = remaining % 60;
      onTick({ hours: h, minutes: m, seconds: s, total_seconds: remaining,
        formatted: [h, m, s].map(v => String(v).padStart(2, '0')).join(':') });
      if (remaining <= 0) { clearInterval(id); onComplete && onComplete(); }
    };
    tick();
    const id = setInterval(tick, 1000);
    return id;
  },

  // --- Toasts ---
  initToastContainer() {
    if (!document.getElementById('toast-container')) {
      const div = document.createElement('div');
      div.id = 'toast-container';
      document.body.appendChild(div);
    }
  },

  showToast(message, type = 'info', duration = 4000) {
    this.initToastContainer();
    const icons = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill',
                    warning: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };
    const toast = document.createElement('div');
    toast.className = `ep-toast ${type}`;
    toast.innerHTML = `
      <i class="bi ${icons[type] || icons.info}"></i>
      <span>${this.sanitizeHTML(message)}</span>
      <span class="ep-toast-close" onclick="this.parentElement.remove()"><i class="bi bi-x"></i></span>`;
    document.getElementById('toast-container').appendChild(toast);
    if (duration > 0) setTimeout(() => toast.remove(), duration);
    return toast;
  },

  // --- Confirm Modal ---
  showConfirm(title, message, onConfirm, onCancel) {
    let modal = document.getElementById('ep-confirm-modal');
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'ep-confirm-modal';
      modal.innerHTML = `
        <div class="modal fade" id="epConfirmModal" tabindex="-1">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="epConfirmTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body" id="epConfirmBody"></div>
              <div class="modal-footer">
                <button class="btn btn-secondary" id="epConfirmCancel" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-danger" id="epConfirmOk">Confirm</button>
              </div>
            </div>
          </div>
        </div>`;
      document.body.appendChild(modal);
    }
    document.getElementById('epConfirmTitle').textContent = title;
    document.getElementById('epConfirmBody').textContent  = message;
    const bsModal = new bootstrap.Modal(document.getElementById('epConfirmModal'));
    document.getElementById('epConfirmOk').onclick = () => { bsModal.hide(); onConfirm && onConfirm(); };
    document.getElementById('epConfirmCancel').onclick = () => { onCancel && onCancel(); };
    bsModal.show();
  },

  // --- Loading ---
  showLoading(message = 'Loading…') {
    let el = document.getElementById('loading-overlay');
    if (!el) {
      el = document.createElement('div');
      el.id = 'loading-overlay';
      el.innerHTML = `<div class="loading-spinner"></div><div style="color:#4F46E5;font-weight:600">${message}</div>`;
      document.body.appendChild(el);
    }
    el.classList.add('active');
  },
  hideLoading() {
    document.getElementById('loading-overlay')?.classList.remove('active');
  },

  // --- Score Helpers ---
  formatScore(obtained, total) {
    const pct = total > 0 ? Math.round(obtained / total * 100) : 0;
    let grade, color;
    if (pct >= 95) { grade = 'A+'; color = '#059669'; }
    else if (pct >= 85) { grade = 'A';  color = '#10B981'; }
    else if (pct >= 75) { grade = 'B+'; color = '#F59E0B'; }
    else if (pct >= 65) { grade = 'B';  color = '#D97706'; }
    else if (pct >= 50) { grade = 'C';  color = '#6B7280'; }
    else { grade = 'F'; color = '#DC2626'; }
    return { percentage: pct, grade, color };
  },

  getStatusBadge(status) {
    const map = {
      draft:     'badge-draft',     scheduled: 'badge-scheduled',
      active:    'badge-active',    completed:  'badge-completed',
      cancelled: 'badge-cancelled', passed:     'badge-passed',
      failed:    'badge-failed',
    };
    const cls = map[status] || 'badge-draft';
    return `<span class="badge-status ${cls}">${status}</span>`;
  },

  getRiskBadge(level) {
    const map = {
      normal: 'risk-normal', low: 'risk-low',
      medium: 'risk-medium', high: 'risk-high', critical: 'risk-critical',
    };
    const cls = map[level] || 'risk-low';
    const label = (level || 'normal').toUpperCase();
    return `<span class="badge-status ${cls}">${label}</span>`;
  },

  // --- Misc ---
  debounce(fn, delay) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), delay); };
  },

  sanitizeHTML(str) {
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
  },

  async copyToClipboard(text) {
    try {
      await navigator.clipboard.writeText(text);
      this.showToast('Copied to clipboard!', 'success', 2000);
    } catch (_) {
      this.showToast('Could not copy — please copy manually.', 'warning');
    }
  },

  renderPagination(containerId, total, page, perPage, onPageChange) {
    const lastPage = Math.ceil(total / perPage);
    const el = document.getElementById(containerId);
    if (!el || lastPage <= 1) { if (el) el.innerHTML = ''; return; }

    let html = '<nav><ul class="pagination pagination-sm mb-0">';
    html += `<li class="page-item ${page === 1 ? 'disabled' : ''}">
      <a class="page-link" href="#" data-page="${page - 1}">‹</a></li>`;
    for (let i = Math.max(1, page - 2); i <= Math.min(lastPage, page + 2); i++) {
      html += `<li class="page-item ${i === page ? 'active' : ''}">
        <a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
    }
    html += `<li class="page-item ${page === lastPage ? 'disabled' : ''}">
      <a class="page-link" href="#" data-page="${page + 1}">›</a></li>`;
    html += '</ul></nav>';

    el.innerHTML = html;
    el.querySelectorAll('[data-page]').forEach(a => {
      a.addEventListener('click', e => {
        e.preventDefault();
        const p = parseInt(a.dataset.page);
        if (p >= 1 && p <= lastPage) onPageChange(p);
      });
    });
  },

  getQueryParam(name) {
    return new URLSearchParams(window.location.search).get(name);
  },
};
