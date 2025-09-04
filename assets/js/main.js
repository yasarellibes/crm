/**
 * HVAC System - Main JavaScript functionality
 * TailAdmin Compatible Version
 */

document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
    
    // Force phone formatting after DOM is ready
    setTimeout(() => {
        initializePhoneFormatting();
    }, 500);
});

function initializeApp() {
    // Initialize common functionality
    initializeSidebar();
    initializeAlerts();
    initializePhoneFormatting();
    initializeDateFilters();
    
    // Initialize tooltips
    initializeTooltips();
    
    // Initialize modals
    initializeModals();
    
    console.log('Modern Hybrid HVAC System initialized - Sidebar ready');
}

/**
 * Modern Sidebar functionality
 */
function initializeSidebar() {
    // Make toggleSidebar globally available
    window.toggleSidebar = toggleSidebar;
    
    // Initialize sidebar toggle buttons
    const toggleButtons = document.querySelectorAll('.sidebar-toggle, .sidebar-toggle-btn, #sidebarToggle');
    toggleButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleSidebar();
        });
    });
    
    // Close sidebar when clicking overlay
    const overlay = document.getElementById('sidebarOverlay');
    if (overlay) {
        overlay.addEventListener('click', function() {
            closeSidebar();
        });
    }
    
    // Auto-close sidebar on mobile when clicking a nav link
    const navLinks = document.querySelectorAll('.sidebar .nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 1024) {
                setTimeout(() => closeSidebar(), 150);
            }
        });
    });
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 1024) {
            closeSidebar();
        }
    });
    
    console.log('Modern sidebar initialized');
}

/**
 * Toggle sidebar function
 */
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (!sidebar) return;
    
    // Desktop behavior (992px+)
    if (window.innerWidth >= 992) {
        const isHidden = sidebar.classList.contains('sidebar-collapsed');
        
        if (isHidden) {
            // Show sidebar
            sidebar.classList.remove('sidebar-collapsed');
            document.querySelector('.content-wrapper')?.classList.remove('full-width');
            document.querySelector('.main-header')?.classList.remove('full-width');
        } else {
            // Hide sidebar
            sidebar.classList.add('sidebar-collapsed');
            document.querySelector('.content-wrapper')?.classList.add('full-width');
            document.querySelector('.main-header')?.classList.add('full-width');
        }
    } else {
        // Mobile behavior (below 992px)
        const isVisible = sidebar.classList.contains('show');
        
        if (isVisible) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }
}

/**
 * Open sidebar
 */
function openSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar) sidebar.classList.add('show');
    if (overlay) overlay.classList.add('show');
    
    // Prevent body scroll on mobile
    if (window.innerWidth <= 1024) {
        document.body.style.overflow = 'hidden';
    }
}

/**
 * Close sidebar
 */
function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar) sidebar.classList.remove('show');
    if (overlay) overlay.classList.remove('show');
    
    // Restore body scroll
    document.body.style.overflow = '';
}

/**
 * Alert system
 */
function initializeAlerts() {
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(alert => {
        setTimeout(() => {
            if (alert.parentNode) {
                alert.style.opacity = '0';
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.parentNode.removeChild(alert);
                    }
                }, 300);
            }
        }, 5000);
    });
}

/**
 * Phone formatting
 */
function initializePhoneFormatting() {
    const phoneInputs = document.querySelectorAll('input[type="tel"], input[name*="phone"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 0) {
                if (value.length <= 3) {
                    value = `(${value}`;
                } else if (value.length <= 6) {
                    value = `(${value.slice(0, 3)}) ${value.slice(3)}`;
                } else if (value.length <= 10) {
                    value = `(${value.slice(0, 3)}) ${value.slice(3, 6)}-${value.slice(6)}`;
                } else {
                    value = `(${value.slice(0, 3)}) ${value.slice(3, 6)}-${value.slice(6, 10)}`;
                }
            }
            e.target.value = value;
        });
    });
}

/**
 * Date filters
 */
function initializeDateFilters() {
    // Quick date filter buttons
    const quickDateButtons = document.querySelectorAll('.quick-date-btn');
    quickDateButtons.forEach(button => {
        button.addEventListener('click', function() {
            const dateType = this.getAttribute('data-date');
            const quickDateInput = document.getElementById('quickDateInput');
            if (quickDateInput) {
                quickDateInput.value = dateType;
                document.getElementById('filterForm').submit();
            }
        });
    });
}

/**
 * Tooltips
 */
function initializeTooltips() {
    // Initialize Bootstrap tooltips if available
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
}

/**
 * Modals
 */
function initializeModals() {
    // Modal backdrop click handler
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                const modalInstance = bootstrap.Modal.getInstance(modal);
                if (modalInstance) {
                    modalInstance.hide();
                }
            }
        });
    });
}

/**
 * Services page functionality
 */
function initializeServicesPage() {
    // Initialize form synchronization between desktop and mobile
    initializeFormSync();
    
    // Quick date filter buttons (desktop)
    const quickDateButtons = document.querySelectorAll('.date-btn');
    quickDateButtons.forEach(button => {
        button.addEventListener('click', function() {
            const dateType = this.getAttribute('data-date');
            document.getElementById('quickDateInput').value = dateType;
            document.getElementById('filterForm').submit();
        });
    });
    
    // Mobile date filter buttons
    const mobileDateButtons = document.querySelectorAll('.mobile-date-btn');
    mobileDateButtons.forEach(button => {
        button.addEventListener('click', function() {
            const dateType = this.getAttribute('data-date');
            document.getElementById('quickDateInput').value = dateType;
            document.getElementById('filterForm').submit();
        });
    });
    
    // Search functionality with debounce
    initializeSearchWithDebounce();
    
    // Filter change handlers
    initializeFilterHandlers();
}

function initializeFormSync() {
    // Sync search inputs between desktop and mobile
    const searchInput = document.getElementById('searchInput');
    const searchInputMobile = document.getElementById('searchInputMobile');
    
    if (searchInput && searchInputMobile) {
        searchInput.addEventListener('input', function() {
            searchInputMobile.value = this.value;
        });
        
        searchInputMobile.addEventListener('input', function() {
            searchInput.value = this.value;
        });
    }
    
    // Sync status filters
    const statusFilter = document.getElementById('statusFilter');
    const statusFilterMobile = document.getElementById('statusFilterMobile');
    
    if (statusFilter && statusFilterMobile) {
        statusFilter.addEventListener('change', function() {
            statusFilterMobile.value = this.value;
            performFilteredSearch();
        });
        
        statusFilterMobile.addEventListener('change', function() {
            statusFilter.value = this.value;
            performFilteredSearch();
        });
    }
    
    // Sync personnel filters
    const personnelFilter = document.getElementById('personnelFilter');
    const personnelFilterMobile = document.getElementById('personnelFilterMobile');
    
    if (personnelFilter && personnelFilterMobile) {
        personnelFilter.addEventListener('change', function() {
            personnelFilterMobile.value = this.value;
            performFilteredSearch();
        });
        
        personnelFilterMobile.addEventListener('change', function() {
            personnelFilter.value = this.value;
            performFilteredSearch();
        });
    }
}

function initializeSearchWithDebounce() {
    const searchInput = document.getElementById('searchInput');
    const searchInputMobile = document.getElementById('searchInputMobile');
    
    const debouncedSearch = debounce(performFilteredSearch, 300);
    
    if (searchInput) {
        searchInput.addEventListener('input', debouncedSearch);
    }
    
    if (searchInputMobile) {
        searchInputMobile.addEventListener('input', debouncedSearch);
    }
}

function initializeFilterHandlers() {
    // Status and personnel filters already handled in initializeFormSync
    // This function can be expanded for additional filter logic
}

function performFilteredSearch() {
    // Get current form values
    const searchValue = document.getElementById('searchInput') ? 
        document.getElementById('searchInput').value : '';
    const statusValue = document.getElementById('statusFilter') ? 
        document.getElementById('statusFilter').value : '';
    const personnelValue = document.getElementById('personnelFilter') ? 
        document.getElementById('personnelFilter').value : '';
    
    // Perform AJAX search if the function exists
    if (typeof performSearch === 'function') {
        performSearch();
    }
}

// Date range toggle
function toggleDateRange() {
    const section = document.getElementById('dateRangeSection');
    if (section) {
        section.style.display = section.style.display === 'none' ? 'block' : 'none';
    }
}

// Clear date range
function clearDateRange() {
    const form = document.querySelector('#dateRangeSection form');
    if (form) {
        form.querySelector('input[name="start_date"]').value = '';
        form.querySelector('input[name="end_date"]').value = '';
        document.getElementById('dateRangeSection').style.display = 'none';
        
        // Submit form to clear filters
        const baseUrl = window.location.pathname;
        const currentParams = new URLSearchParams(window.location.search);
        currentParams.delete('start_date');
        currentParams.delete('end_date');
        window.location.href = baseUrl + '?' + currentParams.toString();
    }
}

/**
 * Utility functions
 */
function showAlert(message, type = 'info') {
    const alertContainer = document.getElementById('alert-container');
    if (!alertContainer) return;
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    alertContainer.appendChild(alertDiv);
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.style.opacity = '0';
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.parentNode.removeChild(alertDiv);
                }
            }, 300);
        }
    }, 5000);
}

// Make utility functions globally available
window.showAlert = showAlert;
window.toggleDateRange = toggleDateRange;
window.clearDateRange = clearDateRange;