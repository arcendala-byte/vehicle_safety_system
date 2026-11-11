/* ============================================================
   VEHISAFE — MAIN JS
   Dark mode, toasts, password toggle, Lucide init
   ============================================================ */

(function () {
    'use strict';

    // ---- THEME ----
    var THEME_KEY = 'vehisafe_theme';

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        try { localStorage.setItem(THEME_KEY, theme); } catch (e) {}
    }

    function getStoredTheme() {
        try {
            var stored = localStorage.getItem(THEME_KEY);
            if (stored === 'dark' || stored === 'light') return stored;
            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                return 'dark';
            }
        } catch (e) {}
        return 'light';
    }

    function initTheme() {
        applyTheme(getStoredTheme());
    }

    window.toggleTheme = function () {
        var current = document.documentElement.getAttribute('data-theme') || 'light';
        applyTheme(current === 'dark' ? 'light' : 'dark');
    };

    // Theme toggle buttons (data-theme-toggle attribute)
    function initThemeButtons() {
        var buttons = document.querySelectorAll('[data-theme-toggle]');
        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                window.toggleTheme();
                // Update icon
                var icon = btn.querySelector('[data-lucide]');
                if (icon) {
                    var current = document.documentElement.getAttribute('data-theme');
                    icon.setAttribute('data-lucide', current === 'dark' ? 'sun' : 'moon');
                    if (window.lucide) lucide.createIcons();
                }
            });
        });
    }

    // ---- PASSWORD TOGGLE ----
    function initPasswordToggles() {
        var buttons = document.querySelectorAll('[data-toggle-password]');
        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var wrap = btn.closest('.input-wrap');
                if (!wrap) return;
                var input = wrap.querySelector('input');
                if (!input) return;

                var isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';

                var icon = btn.querySelector('[data-lucide]');
                if (icon) {
                    icon.setAttribute('data-lucide', isPassword ? 'eye-off' : 'eye');
                    if (window.lucide) lucide.createIcons();
                }
                btn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
            });
        });
    }

    // ---- SIDEBAR TOGGLE (mobile) ----
    function initSidebarToggle() {
        var toggles = document.querySelectorAll('[data-sidebar-toggle]');
        var sidebar = document.getElementById('appSidebar');
        if (!sidebar) return;

        toggles.forEach(function (btn) {
            btn.addEventListener('click', function () {
                sidebar.classList.toggle('is-open');
            });
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function (e) {
            if (window.innerWidth <= 900 && sidebar.classList.contains('is-open')) {
                if (!sidebar.contains(e.target) && !e.target.closest('[data-sidebar-toggle]')) {
                    sidebar.classList.remove('is-open');
                }
            }
        });
    }

    // ---- TOASTS ----
    window.showToast = function (message, type) {
        type = type || 'info';
        var toast = document.createElement('div');
        toast.className = 'toast toast--' + type;
        toast.textContent = message;
        toast.style.cssText =
            'position:fixed;bottom:24px;right:24px;background:#0f172a;color:#fff;' +
            'padding:12px 16px;border-radius:6px;font-size:14px;z-index:9999;' +
            'box-shadow:0 8px 24px rgba(0,0,0,0.2);opacity:0;transition:opacity .2s;' +
            'animation:toastSlideIn .3s ease-out;';
        document.body.appendChild(toast);
        requestAnimationFrame(function () { toast.style.opacity = '1'; });
        setTimeout(function () {
            toast.style.opacity = '0';
            setTimeout(function () { toast.remove(); }, 250);
        }, 3200);
    };

    // ---- CONFIRMATION LINKS ----
    function initConfirmations() {
        var links = document.querySelectorAll('[data-confirm]');
        links.forEach(function (link) {
            link.addEventListener('click', function (e) {
                var msg = link.getAttribute('data-confirm') || 'Are you sure?';
                if (!window.confirm(msg)) {
                    e.preventDefault();
                }
            });
        });
    }

    // ---- FORM SUBMIT LOADING ----
    function initFormLoading() {
        var forms = document.querySelectorAll('form');
        forms.forEach(function (form) {
            form.addEventListener('submit', function () {
                var btn = form.querySelector('button[type="submit"]');
                if (!btn || btn.disabled) return;
                btn.classList.add('is-loading');
                var originalText = btn.innerHTML;
                btn.innerHTML = '<span class="spinner"></span>' + originalText;
                // Re-enable after 10s as safety
                setTimeout(function () {
                    btn.classList.remove('is-loading');
                    btn.innerHTML = originalText;
                }, 10000);
            });
        });
    }

    // ---- BOOT ----
    function boot() {
        initTheme();
        initThemeButtons();
        initPasswordToggles();
        initSidebarToggle();
        initConfirmations();
        initFormLoading();

        if (window.lucide && typeof lucide.createIcons === 'function') {
            lucide.createIcons();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

})();