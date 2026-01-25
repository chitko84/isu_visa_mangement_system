</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarClose = document.getElementById('sidebarClose');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const menuToggleIcon = document.getElementById('menuToggleIcon');
    const body = document.body;

    function openSidebar() {
        if (!sidebar) return;
        sidebar.classList.add('active');
        sidebarOverlay?.classList.add('active');
        body.style.overflow = 'hidden';
        if (menuToggleIcon) menuToggleIcon.className = 'bi bi-x';
    }

    function closeSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('active');
        sidebarOverlay?.classList.remove('active');
        body.style.overflow = '';
        if (menuToggleIcon) menuToggleIcon.className = 'bi bi-list';
    }

    function toggleSidebar() {
        if (!sidebar) return;
        if (sidebar.classList.contains('active')) closeSidebar();
        else openSidebar();
    }

    // Toggle events
    sidebarToggle?.addEventListener('click', toggleSidebar);
    sidebarClose?.addEventListener('click', closeSidebar);
    sidebarOverlay?.addEventListener('click', closeSidebar);

    // Close sidebar when clicking a menu link on mobile
    document.querySelectorAll('.sidebar-menu .nav-link').forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 992 && sidebar?.classList.contains('active')) {
                closeSidebar();
            }
        });
    });

    // ESC closes
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && sidebar?.classList.contains('active')) {
            closeSidebar();
        }
    });

    // Back to top button
    const scrollToTopBtn = document.createElement('button');
    scrollToTopBtn.innerHTML = '<i class="bi bi-chevron-up"></i>';
    scrollToTopBtn.className = 'btn btn-primary btn-scroll-top';
    scrollToTopBtn.setAttribute('aria-label', 'Back to top');
    document.body.appendChild(scrollToTopBtn);

    scrollToTopBtn.style.position = 'fixed';
    scrollToTopBtn.style.right = '18px';
    scrollToTopBtn.style.bottom = '18px';
    scrollToTopBtn.style.width = '42px';
    scrollToTopBtn.style.height = '42px';
    scrollToTopBtn.style.borderRadius = '50%';
    scrollToTopBtn.style.display = 'none';
    scrollToTopBtn.style.alignItems = 'center';
    scrollToTopBtn.style.justifyContent = 'center';
    scrollToTopBtn.style.zIndex = '999';

    window.addEventListener('scroll', function() {
        scrollToTopBtn.style.display = (window.pageYOffset > 300) ? 'flex' : 'none';
    });

    scrollToTopBtn.addEventListener('click', function() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    // On resize: reset overlay/scroll
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 992) {
                sidebarOverlay?.classList.remove('active');
                body.style.overflow = '';
            } else {
                if (sidebar?.classList.contains('active')) closeSidebar();
            }
        }, 200);
    });
});
</script>

<!-- Bootstrap JS (bundle includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
