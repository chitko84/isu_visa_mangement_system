</main>

<script>
    // Sidebar elements
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarClose = document.getElementById('sidebarClose');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const menuToggleIcon = document.getElementById('menuToggleIcon');
    const body = document.body;

    function openSidebar() {
        sidebar.classList.add('active');
        sidebarOverlay.classList.add('active');
        body.style.overflow = 'hidden';
        if (menuToggleIcon) menuToggleIcon.className = 'bi bi-x';
        localStorage.setItem('sidebarState', 'open');
        document.addEventListener('keydown', handleEscapeKey);
    }

    function closeSidebar() {
        sidebar.classList.remove('active');
        sidebarOverlay.classList.remove('active');
        body.style.overflow = '';
        if (menuToggleIcon) menuToggleIcon.className = 'bi bi-list';
        localStorage.setItem('sidebarState', 'closed');
        document.removeEventListener('keydown', handleEscapeKey);
    }

    function toggleSidebar() {
        if (sidebar.classList.contains('active')) closeSidebar();
        else openSidebar();
    }

    function handleEscapeKey(event) {
        if (event.key === 'Escape' && sidebar.classList.contains('active')) {
            closeSidebar();
        }
    }

    if (sidebarToggle) sidebarToggle.addEventListener('click', toggleSidebar);
    if (sidebarClose) sidebarClose.addEventListener('click', closeSidebar);
    if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

    // Close sidebar when clicking a link on mobile
    document.querySelectorAll('.sidebar-menu .nav-link').forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 992 && sidebar.classList.contains('active')) {
                closeSidebar();
            }
        });
    });

    // Restore state
    document.addEventListener('DOMContentLoaded', function() {
        const savedState = localStorage.getItem('sidebarState');

        // Optional: open on desktop if saved open
        // if (window.innerWidth > 992 && savedState === 'open') sidebar.classList.add('active');

        // Scroll to top button
        const scrollToTopBtn = document.createElement('button');
        scrollToTopBtn.innerHTML = '<i class="bi bi-chevron-up"></i>';
        scrollToTopBtn.className = 'btn btn-primary btn-scroll-top';
        scrollToTopBtn.setAttribute('aria-label', 'Back to top');
        document.body.appendChild(scrollToTopBtn);

        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) scrollToTopBtn.style.display = 'flex';
            else scrollToTopBtn.style.display = 'none';
        });

        scrollToTopBtn.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });

    // Resize behavior
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 992) {
                sidebarOverlay.classList.remove('active');
                body.style.overflow = '';
                if (menuToggleIcon) menuToggleIcon.className = 'bi bi-list';
            } else {
                if (sidebar.classList.contains('active')) closeSidebar();
            }
        }, 200);
    });
</script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
