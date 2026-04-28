// ============================================
// Raquel Pawnshop HRIS - Main JavaScript
// ============================================

/**
 * Toggle sidebar visibility/collapse
 */
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const isMobile = window.matchMedia('(max-width: 992px)').matches;

    if (isMobile) {
        // Mobile behavior: slide-in/slide-out
        sidebar.classList.toggle('show');
        if (overlay) overlay.classList.toggle('show');
    } else {
        // Desktop behavior: collapse to icon-only
        document.documentElement.classList.toggle('sidebar-collapsed');
        const isCollapsed = document.documentElement.classList.contains('sidebar-collapsed');
        localStorage.setItem('sidebar_collapsed', isCollapsed);
    }
}

/**
 * Mark all notifications as read (AJAX)
 */
function markAllRead() {
    const baseUrl = (typeof window !== 'undefined' && window.APP_BASE_URL) ? window.APP_BASE_URL : '';
    const context = (typeof window !== 'undefined' && window.NOTIF_CONTEXT) ? window.NOTIF_CONTEXT : 'hr';
    const fd = new FormData();
    fd.append('action', 'mark_all_read');
    fd.append('context', context);

    fetch(baseUrl + '/includes/ajax/notification-action.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove badge
                const badge = document.querySelector('.notification-badge');
                if (badge) badge.style.display = 'none';
                // Remove unread styling from dropdown items
                document.querySelectorAll('.notification-item.unread').forEach(el => {
                    el.classList.remove('unread');
                });
                // If we are on a notifications page, reload to refresh stats/list
                if (window.location.pathname.includes('notifications.php')) {
                    window.location.reload();
                }
            }
        })
        .catch(err => console.error('Error marking notifications:', err));
}

/**
 * Client-side table search filter
 */
function filterTable(inputId, tableId) {
    const filter = document.getElementById(inputId).value.toLowerCase();
    const table = document.getElementById(tableId);
    const rows = table.getElementsByTagName('tr');

    for (let i = 1; i < rows.length; i++) {
        const cells = rows[i].getElementsByTagName('td');
        let match = false;
        for (let j = 0; j < cells.length; j++) {
            if (cells[j].textContent.toLowerCase().includes(filter)) {
                match = true;
                break;
            }
        }
        rows[i].style.display = match ? '' : 'none';
    }
}

/**
 * Confirm delete action
 */
function confirmDelete(message) {
    return confirm(message || 'Are you sure you want to delete this item?');
}

/**
 * Initialize components that need re-binding after PJAX load
 */
function initDynamicComponents() {
    // 1. Close alert after 5 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function (alert) {
        if (alert.dataset.init) return;
        alert.dataset.init = 'true';
        setTimeout(function () {
            const closeBtn = alert.querySelector('.btn-close');
            if (closeBtn) closeBtn.click();
        }, 5000);
    });
}

document.addEventListener('DOMContentLoaded', initDynamicComponents);

/**
 * Common Main JS
 */

document.addEventListener("DOMContentLoaded", function () {
    // Prevent FOUC from sidebar collapsed class
    if (localStorage.getItem('sidebar_collapsed') === 'true') {
        document.documentElement.classList.add('sidebar-collapsed');
        const sb = document.getElementById('sidebar');
        if (sb) {
            sb.classList.add('collapsed');
        }
    }

    // Global Fix: Move all modals to body to prevent Bootstrap z-index layer issues (black shadow bug)
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        if (modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
    });

    // Clear Employee Draft if success alert exists
    const successAlert = document.querySelector('.alert-success');
    if (successAlert) {
        const text = successAlert.innerText.toLowerCase();
        if (text.includes('successfully') || text.includes('added') || text.includes('saved')) {
            localStorage.removeItem('hris_add_employee_draft');
        }
    }
});

// Export for PJAX use
if (typeof window !== 'undefined') {
    window.initDynamicComponents = initDynamicComponents;
}
