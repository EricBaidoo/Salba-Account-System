/**
 * app-confirm.js
 * Global custom confirmation modal — replaces all browser confirm() dialogs.
 *
 * AUTO-INTERCEPTED (no file changes needed):
 *   - <form onsubmit="return confirm('...')">
 *   - <a onclick="return confirm('...')"> / <button onclick="return confirm('...')">
 *
 * MANUAL API for inline JS:
 *   appConfirm('Message here', { onConfirm: function() { ... } });
 *   appConfirm('Message', { onConfirm: fn, title: 'Custom Title', danger: false, confirmText: 'OK' });
 */
(function () {
    const MODAL_HTML = `
<div id="appConfirmModal" style="display:none;position:fixed;inset:0;z-index:99999;align-items:center;justify-content:center;padding:1rem;background:rgba(15,23,42,0.6);backdrop-filter:blur(4px);">
  <div style="background:#fff;border-radius:1.25rem;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);border:1px solid #e2e8f0;width:100%;max-width:22rem;overflow:hidden;animation:acm-pop 0.18s cubic-bezier(.34,1.56,.64,1) both;">
    <div style="padding:1.75rem 1.5rem 1rem;text-align:center;">
      <div id="acm-icon-wrap" style="width:3.5rem;height:3.5rem;border-radius:9999px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;margin:0 auto 1rem;">
        <i id="acm-icon" class="fas fa-triangle-exclamation"></i>
      </div>
      <h3 id="acm-title" style="font-size:1.0625rem;font-weight:700;color:#0f172a;margin:0 0 0.375rem;"></h3>
      <p id="acm-message" style="font-size:0.875rem;color:#64748b;margin:0;line-height:1.5;"></p>
    </div>
    <div style="padding:1rem 1.5rem 1.5rem;display:flex;gap:0.75rem;">
      <button id="acm-cancel" type="button" style="flex:1;padding:0.625rem 1rem;background:#fff;border:1px solid #cbd5e1;color:#334155;font-size:0.875rem;font-weight:600;border-radius:0.625rem;cursor:pointer;transition:background 0.15s;">
        Cancel
      </button>
      <button id="acm-confirm" type="button" style="flex:1;padding:0.625rem 1rem;color:#fff;font-size:0.875rem;font-weight:600;border-radius:0.625rem;cursor:pointer;border:none;transition:opacity 0.15s;">
        Confirm
      </button>
    </div>
  </div>
</div>
<style>
@keyframes acm-pop {
  from { opacity:0; transform:scale(0.92); }
  to   { opacity:1; transform:scale(1); }
}
#appConfirmModal [id="acm-cancel"]:hover { background:#f1f5f9; }
#appConfirmModal [id="acm-confirm"]:hover { opacity:0.88; }
</style>`;

    let _callback = null;

    function injectModal() {
        if (document.getElementById('appConfirmModal')) return;
        const div = document.createElement('div');
        div.innerHTML = MODAL_HTML;
        while (div.firstChild) document.body.appendChild(div.firstChild);

        document.getElementById('acm-cancel').addEventListener('click', closeModal);
        document.getElementById('acm-confirm').addEventListener('click', function () {
            const cb = _callback;
            closeModal();
            if (typeof cb === 'function') cb();
        });
        document.getElementById('appConfirmModal').addEventListener('click', function (e) {
            if (e.target === this) closeModal();
        });
    }

    function closeModal() {
        const m = document.getElementById('appConfirmModal');
        if (m) m.style.display = 'none';
        _callback = null;
    }

    function extractMsg(str) {
        if (!str) return null;
        const m = str.match(/confirm\s*\(\s*[`'"]([\s\S]+?)[`'"]\s*\)/);
        return m ? m[1] : null;
    }

    /* ── Public API ── */
    window.appConfirm = function (message, options) {
        options = options || {};
        injectModal();

        const isDanger = options.danger !== false;
        const iconWrap = document.getElementById('acm-icon-wrap');
        const icon     = document.getElementById('acm-icon');
        const title    = document.getElementById('acm-title');
        const msgEl    = document.getElementById('acm-message');
        const btn      = document.getElementById('acm-confirm');

        title.textContent = options.title || 'Are you sure?';
        msgEl.textContent = message;
        btn.textContent   = options.confirmText || 'Yes, Proceed';

        if (isDanger) {
            iconWrap.style.background = '#fee2e2';
            iconWrap.style.color = '#dc2626';
            icon.className = 'fas fa-triangle-exclamation';
            btn.style.background = '#dc2626';
        } else {
            iconWrap.style.background = '#dbeafe';
            iconWrap.style.color = '#2563eb';
            icon.className = 'fas fa-circle-question';
            btn.style.background = '#2563eb';
        }

        _callback = options.onConfirm || null;

        const m = document.getElementById('appConfirmModal');
        m.style.display = 'flex';
    };

    /* ── Auto-interceptors ── */
    document.addEventListener('DOMContentLoaded', function () {
        injectModal();

        // Intercept form onsubmit with confirm()
        document.addEventListener('submit', function (e) {
            const form = e.target;
            const onsubmit = form.getAttribute('onsubmit') || '';
            const msg = extractMsg(onsubmit);
            if (!msg) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            form.removeAttribute('onsubmit');
            appConfirm(msg, {
                onConfirm: function () { form.submit(); }
            });
        }, true);

        // Intercept link/button onclick with confirm()
        document.addEventListener('click', function (e) {
            const el = e.target.closest('[onclick]');
            if (!el) return;
            const onclick = el.getAttribute('onclick') || '';
            const msg = extractMsg(onclick);
            if (!msg) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            el.removeAttribute('onclick');
            appConfirm(msg, {
                onConfirm: function () { el.click(); }
            });
        }, true);
    });
})();
