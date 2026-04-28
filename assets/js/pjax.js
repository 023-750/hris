/**
 * Raquel Pawnshop HRIS - Navigation & Sidebar Persistence
 * This script maintains sidebar scroll position across page navigations
 * and provides smooth page transition effects.
 * 
 * Note: Full page navigation is used instead of PJAX to ensure all page
 * components (modals, scripts, styles, Bootstrap components) initialize correctly.
 */

document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');

    // --- Sidebar Scroll Persistence ---
    // Restore sidebar scroll position from localStorage
    const savedScrollTop = localStorage.getItem('sidebar_scroll_top');
    if (savedScrollTop && sidebar) {
        sidebar.scrollTop = parseInt(savedScrollTop, 10);
    }

    // Save sidebar scroll position on scroll
    if (sidebar) {
        sidebar.addEventListener('scroll', function () {
            localStorage.setItem('sidebar_scroll_top', sidebar.scrollTop);
        });
    }

    // --- Smooth Page Entry Animation ---
    if (mainContent) {
        mainContent.style.opacity = '0';
        mainContent.style.transform = 'translateY(8px)';
        mainContent.style.transition = 'opacity 0.3s ease, transform 0.3s ease';

        requestAnimationFrame(() => {
            mainContent.style.opacity = '1';
            mainContent.style.transform = 'translateY(0)';
        });
    }

    // --- Sidebar Link Click Handler ---
    // Save scroll position before navigating so it persists on next page
    const sidebarLinks = document.querySelectorAll('.sidebar-nav a');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function () {
            if (sidebar) {
                localStorage.setItem('sidebar_scroll_top', sidebar.scrollTop);
            }
        });
    });
});
