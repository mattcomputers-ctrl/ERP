/**
 * Precision Ink ERP — Global JS
 */

// ── Auto-dismiss toast ──────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    var toast = document.getElementById('toast');
    if (toast) {
        setTimeout(function () {
            toast.style.transition = 'opacity 0.3s ease';
            toast.style.opacity = '0';
            setTimeout(function () { toast.remove(); }, 300);
        }, 5000);
    }
});

// ── Dark Mode ───────────────────────────────────────────────────
function toggleDarkMode() {
    var isDark = document.documentElement.classList.toggle('dark');
    localStorage.setItem('darkMode', isDark ? '1' : '0');
    var moon = document.getElementById('icon-moon');
    var sun = document.getElementById('icon-sun');
    if (moon) moon.style.display = isDark ? 'none' : 'inline';
    if (sun) sun.style.display = isDark ? 'inline' : 'none';
}
// Apply on load
if (localStorage.getItem('darkMode') === '1') {
    document.documentElement.classList.add('dark');
}
document.addEventListener('DOMContentLoaded', function() {
    var isDark = document.documentElement.classList.contains('dark');
    var moon = document.getElementById('icon-moon');
    var sun = document.getElementById('icon-sun');
    if (moon) moon.style.display = isDark ? 'none' : 'inline';
    if (sun) sun.style.display = isDark ? 'inline' : 'none';
});

// ── Keyboard Shortcuts ──────────────────────────────────────────
document.addEventListener('keydown', function(e) {
    var inInput = ['INPUT','TEXTAREA','SELECT'].includes(e.target.tagName);

    // ? = show shortcuts modal
    if (e.key === '?' && !inInput) {
        var modal = document.getElementById('shortcuts-modal');
        if (modal) modal.classList.toggle('visible');
        return;
    }

    // Escape = close modals, go back
    if (e.key === 'Escape') {
        var modal = document.getElementById('shortcuts-modal');
        if (modal) modal.classList.remove('visible');
        document.querySelectorAll('.modal-overlay.active').forEach(function(m) { m.classList.remove('active'); });
        return;
    }

    // / = focus search
    if (e.key === '/' && !inInput) {
        e.preventDefault();
        var search = document.getElementById('global-search') || document.querySelector('[name="q"]');
        if (search) search.focus();
        return;
    }

    // Ctrl+K = focus search
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        var search = document.getElementById('global-search') || document.querySelector('[name="q"]');
        if (search) search.focus();
        return;
    }

    // Ctrl+S = submit form
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        var form = document.querySelector('form[method="POST"]');
        if (form) { e.preventDefault(); form.submit(); }
        return;
    }

    if (inInput) return;

    // Page-specific shortcuts
    if (window.pageShortcuts) {
        for (var key in window.pageShortcuts) {
            if (e.key.toLowerCase() === key.toLowerCase() && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                window.pageShortcuts[key]();
                break;
            }
        }
    }
});

// ── Form Submit Loading State ───────────────────────────────────
document.addEventListener('submit', function(e) {
    var btn = e.target.querySelector('button[type="submit"]:not([formtarget])');
    if (btn && !btn.disabled) {
        btn.disabled = true;
        btn.dataset.originalText = btn.innerHTML;
        btn.innerHTML = 'Processing...';
        setTimeout(function() { btn.disabled = false; btn.innerHTML = btn.dataset.originalText; }, 10000);
    }
});
