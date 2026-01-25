</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarClose = document.getElementById('sidebarClose');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const menuToggleIcon = document.getElementById('menuToggleIcon');
    const body = document.body;

    if (!sidebar || !sidebarToggle || !sidebarOverlay) return;

    function openSidebar() {
        sidebar.classList.add('active');
        sidebarOverlay.classList.add('active');
        body.style.overflow = 'hidden';
        if (menuToggleIcon) menuToggleIcon.className = 'bi bi-x';
        localStorage.setItem('sidebarState', 'open');
    }

    function closeSidebar() {
        sidebar.classList.remove('active');
        sidebarOverlay.classList.remove('active');
        body.style.overflow = '';
        if (menuToggleIcon) menuToggleIcon.className = 'bi bi-list';
        localStorage.setItem('sidebarState', 'closed');
    }

    function toggleSidebar() {
        if (sidebar.classList.contains('active')) closeSidebar();
        else openSidebar();
    }

    // Toggle button
    sidebarToggle.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        toggleSidebar();
    });

    // Close button
    if (sidebarClose) {
        sidebarClose.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            closeSidebar();
        });
    }

    // Overlay click closes
    sidebarOverlay.addEventListener('click', () => closeSidebar());

    // ESC closes
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && sidebar.classList.contains('active')) {
            closeSidebar();
        }
    });

    // Clicking outside sidebar closes (mobile only)
    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 992 && sidebar.classList.contains('active')) {
            const clickInsideSidebar = sidebar.contains(e.target);
            const clickOnToggle = sidebarToggle.contains(e.target);
            if (!clickInsideSidebar && !clickOnToggle) closeSidebar();
        }
    });

    // Close when clicking a link on mobile
    document.querySelectorAll('.sidebar-menu .nav-link').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 992) closeSidebar();
        });
    });

    // Optional: restore previous state on mobile/desktop
    const savedState = localStorage.getItem('sidebarState');
    if (window.innerWidth <= 992) {
        // On mobile, default closed
        closeSidebar();
    } else {
        // On desktop, sidebar is visible by CSS anyway
        if (savedState === 'closed') closeSidebar();
    }

    // Handle resize
    window.addEventListener('resize', () => {
        if (window.innerWidth > 992) {
            sidebarOverlay.classList.remove('active');
            body.style.overflow = '';
            if (menuToggleIcon) menuToggleIcon.className = 'bi bi-list';
        } else {
            // when going to mobile, keep it closed by default
            closeSidebar();
        }
    });
});
</script>


<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
