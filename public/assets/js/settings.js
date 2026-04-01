/**
 * Precision Ink ERP — Settings JS
 */

// Auto-dismiss toast after 5 seconds
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
