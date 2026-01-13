    </main>
    
    <!-- Main Scripts -->
    <script>
        // Enhanced Sidebar Management - FIXED VERSION
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarClose = document.getElementById('sidebarClose');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const menuToggleIcon = document.getElementById('menuToggleIcon');
        const body = document.body;
        const mainContent = document.getElementById('mainContent');

        // Function to open sidebar
        function openSidebar() {
            sidebar.classList.add('active');
            sidebarOverlay.classList.add('active');
            body.style.overflow = 'hidden';
            
            // Update toggle icon
            if (menuToggleIcon) {
                menuToggleIcon.className = 'bi bi-x';
            }
            
            // Save state
            localStorage.setItem('sidebarState', 'open');
            document.addEventListener('keydown', handleEscapeKey);
        }

        // Function to close sidebar
        function closeSidebar() {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
            body.style.overflow = '';
            
            // Update toggle icon
            if (menuToggleIcon) {
                menuToggleIcon.className = 'bi bi-list';
            }
            
            // Save state
            localStorage.setItem('sidebarState', 'closed');
            document.removeEventListener('keydown', handleEscapeKey);
        }

        // Function to toggle sidebar
        function toggleSidebar() {
            if (sidebar.classList.contains('active')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        }

        // Function to handle escape key
        function handleEscapeKey(event) {
            if (event.key === 'Escape' && sidebar.classList.contains('active')) {
                closeSidebar();
            }
        }

        // Event Listeners - Fixed to ensure all are properly attached
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', toggleSidebar);
        }

        if (sidebarClose) {
            sidebarClose.addEventListener('click', closeSidebar);
        }

        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', closeSidebar);
        }

        // Close sidebar when clicking a link on mobile
        document.querySelectorAll('.sidebar-menu .nav-link').forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 992 && sidebar.classList.contains('active')) {
                    closeSidebar();
                }
            });
        });

        // Restore sidebar state from localStorage
        document.addEventListener('DOMContentLoaded', function() {
            const savedState = localStorage.getItem('sidebarState');
            
            // Only restore open state on desktop
            if (window.innerWidth > 992 && savedState === 'open') {
                sidebar.classList.add('active');
            }
            
            // Highlight active page in sidebar
            const currentPage = window.location.pathname.split('/').pop();
            const navLinks = document.querySelectorAll('.sidebar-menu .nav-link');
            
            navLinks.forEach(link => {
                const href = link.getAttribute('href');
                if (href === currentPage || (currentPage === '' && href === 'dashboard.php')) {
                    link.classList.add('active');
                }
            });
        });

        // Handle window resize
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (window.innerWidth > 992) {
                    // On desktop, remove overlay and restore body scroll
                    sidebarOverlay.classList.remove('active');
                    body.style.overflow = '';
                    
                    // Update toggle icon
                    if (menuToggleIcon) {
                        menuToggleIcon.className = 'bi bi-list';
                    }
                } else {
                    // On mobile, close sidebar if open
                    if (sidebar.classList.contains('active')) {
                        closeSidebar();
                    }
                }
            }, 250);
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Scroll to top button (existing in DOM)
        const scrollToTopBtn = document.createElement('button');
        scrollToTopBtn.innerHTML = '<i class="bi bi-chevron-up"></i>';
        scrollToTopBtn.className = 'btn btn-primary btn-scroll-top';
        scrollToTopBtn.setAttribute('aria-label', 'Back to top');
        document.body.appendChild(scrollToTopBtn);

        // Show/hide scroll to top button
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                scrollToTopBtn.style.display = 'flex';
            } else {
                scrollToTopBtn.style.display = 'none';
            }
        });

        // Scroll to top functionality
        scrollToTopBtn.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Add hover effect to cards
        document.querySelectorAll('.stat-card, .card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px)';
                this.style.transition = 'transform 0.3s ease';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Toast notifications function
        window.showToast = function(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type} border-0`;
            toast.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                z-index: 1100;
                min-width: 300px;
            `;
            
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            
            document.body.appendChild(toast);
            const bsToast = new bootstrap.Toast(toast, { autohide: true, delay: 3000 });
            bsToast.show();
            
            // Remove toast after it's hidden
            toast.addEventListener('hidden.bs.toast', function () {
                toast.remove();
            });
        }
    </script>
    
    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
