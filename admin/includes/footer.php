<?php
// admin/includes/footer.php
// Admin Panel Footer with enhanced features
?>
                </div> <!-- .admin-content -->
            </div> <!-- .admin-content-wrapper -->
        </main> <!-- .admin-main -->
    </div> <!-- .admin-wrapper -->

    <!-- Mobile Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Quick Add Modal -->
    <div class="modal" id="quickAddModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="modalTitle">Quick Add</h3>
                    <button class="close-modal" onclick="closeModal()">&times;</button>
                </div>
                <div class="modal-body" id="modalBody">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

    <!-- Notification Toast Container -->
    <div class="notification-toast-container" id="notificationToast"></div>

    <!-- Global Search Results Template -->
    <div class="search-results-template" style="display: none;">
        <div class="search-category">
            <h4 class="category-title"></h4>
            <div class="category-items"></div>
        </div>
    </div>

    <!-- Keyboard Shortcuts Hint -->
    <div class="keyboard-hint" id="keyboardHint">
        <div class="hint-header">
            <i class="fas fa-keyboard"></i>
            <h4>Keyboard Shortcuts</h4>
            <button class="hint-close" onclick="closeKeyboardHint()">&times;</button>
        </div>
        <div class="hint-grid">
            <div class="hint-row">
                <span class="shortcut"><kbd>Ctrl</kbd> + <kbd>B</kbd></span>
                <span class="description">Toggle Sidebar</span>
            </div>
            <div class="hint-row">
                <span class="shortcut"><kbd>Ctrl</kbd> + <kbd>K</kbd></span>
                <span class="description">Focus Search</span>
            </div>
            <div class="hint-row">
                <span class="shortcut"><kbd>Ctrl</kbd> + <kbd>Shift</kbd> + <kbd>P</kbd></span>
                <span class="description">Add Project</span>
            </div>
            <div class="hint-row">
                <span class="shortcut"><kbd>Ctrl</kbd> + <kbd>Shift</kbd> + <kbd>C</kbd></span>
                <span class="description">Add Client</span>
            </div>
            <div class="hint-row">
                <span class="shortcut"><kbd>Ctrl</kbd> + <kbd>Shift</kbd> + <kbd>I</kbd></span>
                <span class="description">Create Invoice</span>
            </div>
            <div class="hint-row">
                <span class="shortcut"><kbd>Ctrl</kbd> + <kbd>Shift</kbd> + <kbd>B</kbd></span>
                <span class="description">Add Blog Post</span>
            </div>
            <div class="hint-row">
                <span class="shortcut"><kbd>ESC</kbd></span>
                <span class="description">Close Modal/Search</span>
            </div>
            <div class="hint-row">
                <span class="shortcut"><kbd>?</kbd></span>
                <span class="description">Show/Hide Shortcuts</span>
            </div>
        </div>
        <div class="hint-footer">
            <i class="fas fa-moon"></i>
            <span>Press <kbd>?</kbd> to toggle</span>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal" id="confirmModal">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Confirm Action</h3>
                    <button class="close-modal" onclick="closeConfirmModal()">&times;</button>
                </div>
                <div class="modal-body" id="confirmModalBody">
                    Are you sure you want to proceed?
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="closeConfirmModal()">Cancel</button>
                    <button class="btn btn-danger" id="confirmActionBtn">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Spinner -->
    <div class="loading-spinner-overlay" id="loadingSpinner">
        <div class="spinner"></div>
    </div>

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    
    <script>
    // ========================================
    // ENHANCED ADMIN PANEL JAVASCRIPT
    // ========================================
    document.addEventListener('DOMContentLoaded', function() {
        
        // ========================================
        // SIDEBAR TOGGLE WITH MOBILE SUPPORT
        // ========================================
        const sidebarToggle = document.getElementById('sidebarToggle');
        const adminSidebar = document.getElementById('adminSidebar');
        const adminMain = document.getElementById('adminMain');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        if (sidebarToggle && adminSidebar && adminMain) {
            // Check if we're on mobile
            const isMobile = () => window.innerWidth <= 767;
            
            // Initialize based on screen size
            if (!isMobile()) {
                // Desktop: Check local storage for sidebar state
                const sidebarState = localStorage.getItem('adminSidebarCollapsed');
                if (sidebarState === 'true') {
                    adminSidebar.classList.add('collapsed');
                    adminMain.classList.add('expanded');
                }
            } else {
                // Mobile: Ensure sidebar is hidden initially
                adminSidebar.classList.remove('show', 'collapsed');
                adminMain.classList.remove('expanded');
                if (sidebarOverlay) sidebarOverlay.classList.remove('show');
                document.body.style.overflow = '';
            }
            
            // Toggle sidebar
            sidebarToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                if (isMobile()) {
                    // Mobile behavior
                    adminSidebar.classList.toggle('show');
                    if (sidebarOverlay) sidebarOverlay.classList.toggle('show');
                    
                    // Prevent body scrolling when sidebar is open
                    if (adminSidebar.classList.contains('show')) {
                        document.body.style.overflow = 'hidden';
                    } else {
                        document.body.style.overflow = '';
                    }
                } else {
                    // Desktop behavior
                    adminSidebar.classList.toggle('collapsed');
                    adminMain.classList.toggle('expanded');
                    
                    // Save state to local storage
                    localStorage.setItem('adminSidebarCollapsed', adminSidebar.classList.contains('collapsed'));
                    
                    // Trigger resize event for charts
                    window.dispatchEvent(new Event('resize'));
                }
            });
            
            // Close sidebar when clicking overlay
            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', function() {
                    adminSidebar.classList.remove('show');
                    sidebarOverlay.classList.remove('show');
                    document.body.style.overflow = '';
                });
            }
            
            // Handle window resize
            window.addEventListener('resize', function() {
                if (isMobile()) {
                    // Switching to mobile
                    adminSidebar.classList.remove('collapsed', 'show');
                    adminMain.classList.remove('expanded');
                    if (sidebarOverlay) sidebarOverlay.classList.remove('show');
                    document.body.style.overflow = '';
                } else {
                    // Switching to desktop
                    adminSidebar.classList.remove('show');
                    if (sidebarOverlay) sidebarOverlay.classList.remove('show');
                    document.body.style.overflow = '';
                    
                    // Restore desktop state
                    const sidebarState = localStorage.getItem('adminSidebarCollapsed');
                    if (sidebarState === 'true') {
                        adminSidebar.classList.add('collapsed');
                        adminMain.classList.add('expanded');
                    } else {
                        adminSidebar.classList.remove('collapsed');
                        adminMain.classList.remove('expanded');
                    }
                }
            });
            
            // Close sidebar when clicking a link (for mobile)
            document.querySelectorAll('.nav-item a').forEach(link => {
                link.addEventListener('click', function() {
                    if (isMobile()) {
                        adminSidebar.classList.remove('show');
                        if (sidebarOverlay) sidebarOverlay.classList.remove('show');
                        document.body.style.overflow = '';
                    }
                });
            });
        }
        
        // ========================================
        // SUBMENU TOGGLE
        // ========================================
        document.querySelectorAll('.submenu-toggle').forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                
                const parent = this.closest('.nav-item');
                const submenu = parent.querySelector('.submenu');
                
                if (submenu) {
                    // Close other open submenus
                    document.querySelectorAll('.nav-item.has-submenu.active').forEach(item => {
                        if (item !== parent) {
                            item.classList.remove('active');
                            const otherSubmenu = item.querySelector('.submenu');
                            if (otherSubmenu) {
                                otherSubmenu.classList.remove('show');
                                otherSubmenu.style.maxHeight = '0';
                            }
                        }
                    });
                    
                    // Toggle current submenu
                    parent.classList.toggle('active');
                    submenu.classList.toggle('show');
                    
                    if (submenu.classList.contains('show')) {
                        submenu.style.maxHeight = submenu.scrollHeight + 'px';
                    } else {
                        submenu.style.maxHeight = '0';
                    }
                }
            });
        });
        
        // ========================================
        // DROPDOWNS (Quick Stats, Notifications, User)
        // ========================================
        const quickStatsBtn = document.getElementById('quickStatsBtn');
        const quickStatsDropdown = document.getElementById('quickStatsDropdown');
        const notificationBtn = document.getElementById('notificationBtn');
        const notificationDropdown = document.getElementById('notificationDropdown');
        const userMenuBtn = document.getElementById('userMenuBtn');
        const userDropdown = document.getElementById('userDropdown');
        
        // Helper to close all dropdowns
        function closeAllDropdowns() {
            if (quickStatsDropdown) quickStatsDropdown.classList.remove('show');
            if (notificationDropdown) notificationDropdown.classList.remove('show');
            if (userDropdown) userDropdown.classList.remove('show');
        }
        
        // Quick Stats Dropdown
        if (quickStatsBtn && quickStatsDropdown) {
            quickStatsBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                closeAllDropdowns();
                quickStatsDropdown.classList.toggle('show');
            });
        }
        
        // Notification Dropdown
        if (notificationBtn && notificationDropdown) {
            notificationBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                closeAllDropdowns();
                notificationDropdown.classList.toggle('show');
            });
        }
        
        // User Menu Dropdown
        if (userMenuBtn && userDropdown) {
            userMenuBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                closeAllDropdowns();
                userDropdown.classList.toggle('show');
            });
        }
        
        // Close dropdowns on click outside
        document.addEventListener('click', function(e) {
            if (quickStatsDropdown && !quickStatsBtn.contains(e.target) && !quickStatsDropdown.contains(e.target)) {
                quickStatsDropdown.classList.remove('show');
            }
            if (notificationDropdown && !notificationBtn.contains(e.target) && !notificationDropdown.contains(e.target)) {
                notificationDropdown.classList.remove('show');
            }
            if (userDropdown && !userMenuBtn.contains(e.target) && !userDropdown.contains(e.target)) {
                userDropdown.classList.remove('show');
            }
        });
        
        // ========================================
        // GLOBAL SEARCH
        // ========================================
        const searchInput = document.getElementById('globalSearch');
        const searchResults = document.getElementById('searchResults');
        let searchTimeout;
        let searchCache = {};
        
        if (searchInput && searchResults) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const query = this.value.trim();
                
                if (query.length < 2) {
                    searchResults.style.display = 'none';
                    return;
                }
                
                // Check cache first
                if (searchCache[query]) {
                    renderSearchResults(searchCache[query]);
                    return;
                }
                
                // Show loading indicator
                searchResults.innerHTML = '<div class="search-loading"><i class="fas fa-spinner fa-spin"></i> Searching...</div>';
                searchResults.style.display = 'block';
                
                searchTimeout = setTimeout(() => {
                    performSearch(query);
                }, 300);
            });
            
            // Focus search with Ctrl+K
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'k') {
                    e.preventDefault();
                    searchInput.focus();
                    searchInput.select();
                }
            });
            
            // Close search on escape
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && searchResults.style.display === 'block') {
                    searchResults.style.display = 'none';
                    searchInput.blur();
                }
            });
            
            // Click outside to close
            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                    searchResults.style.display = 'none';
                }
            });
            
            // Navigate search results with arrow keys
            searchInput.addEventListener('keydown', function(e) {
                if (searchResults.style.display !== 'block') return;
                
                const items = searchResults.querySelectorAll('.search-item');
                if (items.length === 0) return;
                
                const current = searchResults.querySelector('.search-item.selected');
                
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (!current) {
                        items[0].classList.add('selected');
                        items[0].scrollIntoView({ block: 'nearest' });
                    } else {
                        const next = current.nextElementSibling;
                        if (next && next.classList.contains('search-item')) {
                            current.classList.remove('selected');
                            next.classList.add('selected');
                            next.scrollIntoView({ block: 'nearest' });
                        }
                    }
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (!current) {
                        items[items.length - 1].classList.add('selected');
                        items[items.length - 1].scrollIntoView({ block: 'nearest' });
                    } else {
                        const prev = current.previousElementSibling;
                        if (prev && prev.classList.contains('search-item')) {
                            current.classList.remove('selected');
                            prev.classList.add('selected');
                            prev.scrollIntoView({ block: 'nearest' });
                        }
                    }
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (current) {
                        current.click();
                    } else if (items.length > 0) {
                        items[0].click();
                    }
                }
            });
        }
        
        function performSearch(query) {
            fetch(`ajax/search.php?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    searchCache[query] = data;
                    renderSearchResults(data);
                })
                .catch(error => {
                    console.error('Search error:', error);
                    searchResults.innerHTML = '<div class="search-error">Error performing search. Please try again.</div>';
                });
        }
        
        function renderSearchResults(data) {
            if (!data || (data.projects && data.projects.length === 0 && 
                data.clients && data.clients.length === 0 && 
                data.invoices && data.invoices.length === 0)) {
                searchResults.innerHTML = '<div class="no-results">No results found</div>';
                searchResults.style.display = 'block';
                return;
            }
            
            let html = '';
            
            if (data.projects && data.projects.length > 0) {
                html += '<div class="search-category"><h4><i class="fas fa-code-branch"></i> Projects</h4>';
                data.projects.forEach(p => {
                    html += `<a href="projects.php?action=edit&id=${p.id}" class="search-item">
                        <i class="fas fa-code-branch"></i>
                        <div class="search-item-content">
                            <strong>${escapeHtml(p.title)}</strong>
                            <small>${escapeHtml(p.category || 'Uncategorized')}</small>
                        </div>
                        ${p.status ? `<span class="search-status status-${p.status}">${p.status}</span>` : ''}
                    </a>`;
                });
                html += '</div>';
            }
            
            if (data.clients && data.clients.length > 0) {
                html += '<div class="search-category"><h4><i class="fas fa-building"></i> Clients</h4>';
                data.clients.forEach(c => {
                    html += `<a href="clients.php?action=view&id=${c.id}" class="search-item">
                        <i class="fas fa-building"></i>
                        <div class="search-item-content">
                            <strong>${escapeHtml(c.company_name)}</strong>
                            <small>${escapeHtml(c.contact_person || 'No contact')}</small>
                        </div>
                    </a>`;
                });
                html += '</div>';
            }
            
            if (data.invoices && data.invoices.length > 0) {
                html += '<div class="search-category"><h4><i class="fas fa-file-invoice"></i> Invoices</h4>';
                data.invoices.forEach(i => {
                    html += `<a href="invoices.php?action=view&id=${i.id}" class="search-item">
                        <i class="fas fa-file-invoice"></i>
                        <div class="search-item-content">
                            <strong>${escapeHtml(i.invoice_number)}</strong>
                            <small>$${i.total} - ${i.status}</small>
                        </div>
                        ${i.status ? `<span class="search-status status-${i.status}">${i.status}</span>` : ''}
                    </a>`;
                });
                html += '</div>';
            }
            
            searchResults.innerHTML = html;
            searchResults.style.display = 'block';
        }
        
        // ========================================
        // LOADING OVERLAY
        // ========================================
        const loadingOverlay = document.getElementById('adminLoading');
        if (loadingOverlay) {
            setTimeout(() => {
                loadingOverlay.style.opacity = '0';
                setTimeout(() => {
                    loadingOverlay.style.display = 'none';
                }, 500);
            }, 500);
        }
        
        // ========================================
        // AUTO-HIDE ALERTS
        // ========================================
        document.querySelectorAll('.alert:not(.alert-permanent)').forEach(alert => {
            setTimeout(() => {
                alert.style.transition = 'opacity 0.3s ease';
                alert.style.opacity = '0';
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.style.display = 'none';
                    }
                }, 300);
            }, 5000);
        });
        
        // ========================================
        // CONFIRM DELETE ACTIONS
        // ========================================
        document.querySelectorAll('.delete-btn, [data-confirm]').forEach(btn => {
            btn.addEventListener('click', function(e) {
                const message = this.getAttribute('data-confirm') || 'Are you sure you want to delete this item? This action cannot be undone.';
                if (!confirm(message)) {
                    e.preventDefault();
                }
            });
        });
        
        // ========================================
        // KEYBOARD SHORTCUTS
        // ========================================
        let hintTimeout;
        let hintVisible = false;
        
        document.addEventListener('keydown', function(e) {
            const isMobile = window.innerWidth <= 767;
            
            // Show/hide hint with ?
            if (e.key === '?' && !e.ctrlKey && !e.altKey && !e.shiftKey) {
                e.preventDefault();
                const hint = document.getElementById('keyboardHint');
                if (hint) {
                    if (hintVisible) {
                        hint.classList.remove('show');
                        hintVisible = false;
                    } else {
                        hint.classList.add('show');
                        hintVisible = true;
                        clearTimeout(hintTimeout);
                        hintTimeout = setTimeout(() => {
                            hint.classList.remove('show');
                            hintVisible = false;
                        }, 8000);
                    }
                }
            }
            
            // Ctrl+B for sidebar toggle
            if (e.ctrlKey && e.key === 'b') {
                e.preventDefault();
                if (sidebarToggle) {
                    sidebarToggle.click();
                }
            }
            
            // Quick add shortcuts
            if (e.ctrlKey && e.shiftKey) {
                if (e.key === 'P' || e.key === 'p') {
                    e.preventDefault();
                    quickAdd('project');
                } else if (e.key === 'C' || e.key === 'c') {
                    e.preventDefault();
                    quickAdd('client');
                } else if (e.key === 'I' || e.key === 'i') {
                    e.preventDefault();
                    quickAdd('invoice');
                } else if (e.key === 'B' || e.key === 'b') {
                    e.preventDefault();
                    quickAdd('post');
                }
            }
            
            // ESC to close modals and search
            if (e.key === 'Escape') {
                const modal = document.getElementById('quickAddModal');
                if (modal && modal.style.display === 'block') {
                    closeModal();
                }
                
                const confirmModal = document.getElementById('confirmModal');
                if (confirmModal && confirmModal.style.display === 'block') {
                    closeConfirmModal();
                }
                
                const searchResults = document.getElementById('searchResults');
                if (searchResults && searchResults.style.display === 'block') {
                    searchResults.style.display = 'none';
                    if (searchInput) searchInput.blur();
                }
                
                // Close mobile sidebar
                if (isMobile && adminSidebar && adminSidebar.classList.contains('show')) {
                    adminSidebar.classList.remove('show');
                    if (sidebarOverlay) sidebarOverlay.classList.remove('show');
                    document.body.style.overflow = '';
                }
            }
        });
        
        // Hide hint on scroll
        window.addEventListener('scroll', () => {
            if (hintVisible) {
                const hint = document.getElementById('keyboardHint');
                if (hint) {
                    hint.classList.remove('show');
                    hintVisible = false;
                }
            }
        });
        
        // ========================================
        // TOOLTIP INITIALIZATION
        // ========================================
        document.querySelectorAll('[title]').forEach(element => {
            element.addEventListener('mouseenter', function(e) {
                const title = this.getAttribute('title');
                if (title && !this.getAttribute('data-tooltip')) {
                    this.setAttribute('data-tooltip', title);
                    this.removeAttribute('title');
                    
                    // Create tooltip element
                    const tooltip = document.createElement('div');
                    tooltip.className = 'custom-tooltip';
                    tooltip.textContent = title;
                    document.body.appendChild(tooltip);
                    
                    const rect = this.getBoundingClientRect();
                    tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
                    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
                    
                    this.addEventListener('mouseleave', function() {
                        tooltip.remove();
                    });
                }
            });
        });
        
        // ========================================
        // AJAX FORM HANDLING
        // ========================================
        document.querySelectorAll('form[data-ajax]').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const url = this.action;
                const method = this.method;
                
                showLoading();
                
                fetch(url, {
                    method: method,
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        showNotification('Success', data.message, 'success');
                        if (data.redirect) {
                            setTimeout(() => {
                                window.location.href = data.redirect;
                            }, 1500);
                        }
                    } else {
                        showNotification('Error', data.message, 'error');
                    }
                })
                .catch(error => {
                    hideLoading();
                    showNotification('Error', 'An error occurred. Please try again.', 'error');
                    console.error('Error:', error);
                });
            });
        });
    });
    
    // ========================================
    // QUICK ADD FUNCTIONS
    // ========================================
    function quickAdd(type) {
        const modal = document.getElementById('quickAddModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalBody = document.getElementById('modalBody');
        
        if (!modal || !modalTitle || !modalBody) return;
        
        let title = '';
        let icon = '';
        switch(type) {
            case 'project': 
                title = 'Quick Add Project'; 
                icon = 'fa-code-branch';
                break;
            case 'client': 
                title = 'Quick Add Client'; 
                icon = 'fa-building';
                break;
            case 'invoice': 
                title = 'Quick Create Invoice'; 
                icon = 'fa-file-invoice';
                break;
            case 'post': 
                title = 'Quick Add Blog Post'; 
                icon = 'fa-feather-alt';
                break;
        }
        
        modalTitle.innerHTML = `<i class="fas ${icon}"></i> ${title}`;
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
        modalBody.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
        
        fetch(`ajax/quick-add-form.php?type=${type}&t=${Date.now()}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(html => {
                modalBody.innerHTML = html;
                
                // Initialize form validation
                const form = document.getElementById('quickAddForm');
                if (form) {
                    form.setAttribute('data-type', type);
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        saveQuickAdd(type);
                    });
                    
                    // Initialize any datepickers or select2
                    if (typeof initFormFields === 'function') {
                        initFormFields(form);
                    }
                }
            })
            .catch(error => {
                modalBody.innerHTML = '<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> Error loading form. Please try again.</div>';
                console.error('Error:', error);
            });
    }
    
    function saveQuickAdd(type) {
        const form = document.getElementById('quickAddForm');
        if (!form) return;
        
        const formData = new FormData(form);
        
        // Show loading state
        const submitBtn = form.querySelector('[type="submit"]');
        const originalText = submitBtn ? submitBtn.innerHTML : 'Save';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        }
        
        fetch(`ajax/quick-add-save.php?type=${type}`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
            
            if (data.success) {
                showNotification('Success', data.message, 'success');
                setTimeout(() => {
                    closeModal();
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    } else {
                        window.location.reload();
                    }
                }, 1500);
            } else {
                showNotification('Error', data.message, 'error');
                
                // Display validation errors
                if (data.errors) {
                    Object.keys(data.errors).forEach(field => {
                        const input = form.querySelector(`[name="${field}"]`);
                        if (input) {
                            input.classList.add('error');
                            const errorDiv = document.createElement('div');
                            errorDiv.className = 'error-message';
                            errorDiv.textContent = data.errors[field];
                            input.parentNode.appendChild(errorDiv);
                        }
                    });
                }
            }
        })
        .catch(error => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
            showNotification('Error', 'An error occurred. Please try again.', 'error');
            console.error('Error:', error);
        });
    }
    
    function closeModal() {
        const modal = document.getElementById('quickAddModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    }
    
    function closeConfirmModal() {
        const modal = document.getElementById('confirmModal');
        if (modal) {
            modal.style.display = 'none';
        }
    }
    
    function closeKeyboardHint() {
        const hint = document.getElementById('keyboardHint');
        if (hint) {
            hint.classList.remove('show');
        }
    }
    
    function showNotification(title, message, type = 'info') {
        const container = document.getElementById('notificationToast');
        if (!container) return;
        
        const notification = document.createElement('div');
        notification.className = `notification-toast ${type}`;
        
        let icon = 'info-circle';
        if (type === 'success') icon = 'check-circle';
        if (type === 'error') icon = 'exclamation-circle';
        if (type === 'warning') icon = 'exclamation-triangle';
        
        notification.innerHTML = `
            <i class="fas fa-${icon}"></i>
            <div class="notification-content">
                <strong>${title}</strong>
                <p>${message}</p>
            </div>
            <button class="notification-close" onclick="this.parentElement.remove()">&times;</button>
        `;
        
        container.appendChild(notification);
        
        // Show with animation
        setTimeout(() => {
            notification.classList.add('show');
        }, 10);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.classList.remove('show');
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 300);
            }
        }, 5000);
    }
    
    function showConfirm(message, onConfirm) {
        const modal = document.getElementById('confirmModal');
        const body = document.getElementById('confirmModalBody');
        const confirmBtn = document.getElementById('confirmActionBtn');
        
        if (!modal || !body || !confirmBtn) return;
        
        body.textContent = message;
        modal.style.display = 'block';
        
        // Remove previous event listeners
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        
        newConfirmBtn.addEventListener('click', function() {
            if (typeof onConfirm === 'function') {
                onConfirm();
            }
            closeConfirmModal();
        });
    }
    
    function markAllRead() {
        fetch('ajax/mark-all-read.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.querySelectorAll('.notification-item.unread').forEach(item => {
                    item.classList.remove('unread');
                });
                const badge = document.querySelector('.notification-badge');
                if (badge) {
                    badge.style.display = 'none';
                }
                showNotification('Success', 'All notifications marked as read', 'success');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error', 'Failed to mark notifications as read', 'error');
        });
    }
    
    function showLoading() {
        const spinner = document.getElementById('loadingSpinner');
        if (spinner) {
            spinner.style.display = 'flex';
        }
    }
    
    function hideLoading() {
        const spinner = document.getElementById('loadingSpinner');
        if (spinner) {
            spinner.style.display = 'none';
        }
    }
    
    // Helper function to escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Close modal when clicking outside
    window.addEventListener('click', function(e) {
        const modal = document.getElementById('quickAddModal');
        if (e.target === modal) {
            closeModal();
        }
        
        const confirmModal = document.getElementById('confirmModal');
        if (e.target === confirmModal) {
            closeConfirmModal();
        }
    });
    
    // Prevent modal close when clicking inside modal content
    document.querySelectorAll('.modal-dialog').forEach(dialog => {
        dialog.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
    
    // Handle browser back button
    window.addEventListener('popstate', function() {
        closeModal();
        closeConfirmModal();
    });
    </script>

    <style>
    /* ========================================
       SEARCH RESULTS STYLES
       ======================================== */
    .search-results {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        margin-top: 5px;
        max-height: 400px;
        overflow-y: auto;
        z-index: 1000;
        border: 1px solid var(--gray-200);
    }
    
    .search-category {
        padding: 10px;
        border-bottom: 1px solid var(--gray-200);
    }
    
    .search-category:last-child {
        border-bottom: none;
    }
    
    .search-category h4 {
        font-size: 0.8rem;
        color: var(--gray-500);
        text-transform: uppercase;
        margin-bottom: 8px;
        padding-left: 5px;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .search-category h4 i {
        color: var(--primary);
    }
    
    .search-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 10px;
        text-decoration: none;
        color: var(--gray-700);
        border-radius: 8px;
        transition: all 0.2s ease;
        position: relative;
    }
    
    .search-item:hover,
    .search-item.selected {
        background: var(--gray-100);
        transform: translateX(2px);
    }
    
    .search-item i {
        width: 32px;
        height: 32px;
        background: var(--gray-200);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary);
        flex-shrink: 0;
    }
    
    .search-item-content {
        flex: 1;
        min-width: 0;
    }
    
    .search-item-content strong {
        display: block;
        font-size: 0.9rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .search-item-content small {
        font-size: 0.75rem;
        color: var(--gray-500);
        display: block;
    }
    
    .search-status {
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        flex-shrink: 0;
    }
    
    .search-status.status-paid,
    .search-status.status-completed {
        background: rgba(16,185,129,0.1);
        color: #10b981;
    }
    
    .search-status.status-pending,
    .search-status.status-in_progress {
        background: rgba(37,99,235,0.1);
        color: #2563eb;
    }
    
    .search-status.status-overdue,
    .search-status.status-cancelled {
        background: rgba(239,68,68,0.1);
        color: #ef4444;
    }
    
    .search-loading,
    .no-results,
    .search-error {
        padding: 20px;
        text-align: center;
        color: var(--gray-500);
    }
    
    .search-loading i {
        margin-right: 5px;
    }
    
    .search-error {
        color: var(--danger);
    }
    
    /* ========================================
       SIDEBAR OVERLAY
       ======================================== */
    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 999;
        backdrop-filter: blur(2px);
        transition: opacity 0.3s ease;
    }
    
    .sidebar-overlay.show {
        display: block;
        animation: fadeIn 0.3s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    /* ========================================
       KEYBOARD HINT
       ======================================== */
    .keyboard-hint {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: #2d3748;
        color: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        z-index: 9999;
        transform: translateY(20px);
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        min-width: 320px;
        border: 1px solid #4a5568;
    }
    
    .keyboard-hint.show {
        transform: translateY(0);
        opacity: 1;
        visibility: visible;
    }
    
    .hint-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid #4a5568;
        position: relative;
    }
    
    .hint-header i {
        color: var(--primary-light);
        font-size: 1.2rem;
    }
    
    .hint-header h4 {
        margin: 0;
        color: white;
        font-size: 1rem;
        flex: 1;
    }
    
    .hint-close {
        background: none;
        border: none;
        color: #a0aec0;
        font-size: 1.2rem;
        cursor: pointer;
        padding: 0 5px;
    }
    
    .hint-close:hover {
        color: white;
    }
    
    .hint-grid {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .hint-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.9rem;
    }
    
    .shortcut kbd {
        background: #4a5568;
        border-radius: 4px;
        padding: 2px 8px;
        margin: 0 2px;
        font-family: 'Courier New', monospace;
        border: 1px solid #718096;
        color: white;
        font-size: 0.8rem;
    }
    
    .description {
        color: #cbd5e0;
    }
    
    .hint-footer {
        margin-top: 15px;
        padding-top: 10px;
        border-top: 1px solid #4a5568;
        text-align: center;
        font-size: 0.8rem;
        color: #a0aec0;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
    }
    
    .hint-footer kbd {
        background: #4a5568;
        border-radius: 4px;
        padding: 2px 6px;
        margin: 0 2px;
    }
    
    /* ========================================
       NOTIFICATION TOAST
       ======================================== */
    .notification-toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 10px;
        max-width: 350px;
    }
    
    .notification-toast {
        background: white;
        border-radius: 8px;
        padding: 15px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        display: flex;
        align-items: flex-start;
        gap: 12px;
        transform: translateX(120%);
        opacity: 0;
        transition: all 0.3s ease;
        border-left: 4px solid;
    }
    
    .notification-toast.show {
        transform: translateX(0);
        opacity: 1;
    }
    
    .notification-toast.success {
        border-left-color: #10b981;
    }
    
    .notification-toast.success i {
        color: #10b981;
    }
    
    .notification-toast.error {
        border-left-color: #ef4444;
    }
    
    .notification-toast.error i {
        color: #ef4444;
    }
    
    .notification-toast.warning {
        border-left-color: #f59e0b;
    }
    
    .notification-toast.warning i {
        color: #f59e0b;
    }
    
    .notification-toast.info {
        border-left-color: #3b82f6;
    }
    
    .notification-toast.info i {
        color: #3b82f6;
    }
    
    .notification-toast i {
        font-size: 1.2rem;
        flex-shrink: 0;
    }
    
    .notification-content {
        flex: 1;
    }
    
    .notification-content strong {
        display: block;
        margin-bottom: 3px;
        color: var(--dark);
    }
    
    .notification-content p {
        margin: 0;
        font-size: 0.9rem;
        color: var(--gray-600);
    }
    
    .notification-close {
        background: none;
        border: none;
        font-size: 1.2rem;
        cursor: pointer;
        color: var(--gray-400);
        padding: 0 5px;
        flex-shrink: 0;
    }
    
    .notification-close:hover {
        color: var(--gray-600);
    }
    
    /* ========================================
       LOADING SPINNER
       ======================================== */
    .loading-spinner-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 10000;
    }
    
    .spinner {
        width: 50px;
        height: 50px;
        border: 3px solid rgba(255,255,255,0.3);
        border-radius: 50%;
        border-top-color: white;
        animation: spin 1s ease-in-out infinite;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    /* ========================================
       MODAL STYLES
       ======================================== */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 10000;
        overflow-y: auto;
    }
    
    .modal-dialog {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    
    .modal-dialog.modal-sm {
        max-width: 400px;
        margin: 0 auto;
    }
    
    .modal-content {
        background: white;
        border-radius: 12px;
        width: 100%;
        max-width: 500px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        animation: modalSlideIn 0.3s ease;
    }
    
    @keyframes modalSlideIn {
        from {
            transform: translateY(-30px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
    
    .modal-header {
        padding: 20px;
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .modal-header h3 {
        margin: 0;
        font-size: 1.2rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .modal-header h3 i {
        color: var(--primary);
    }
    
    .close-modal {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--gray-500);
        line-height: 1;
        padding: 0 5px;
    }
    
    .close-modal:hover {
        color: var(--gray-700);
    }
    
    .modal-body {
        padding: 20px;
        max-height: 70vh;
        overflow-y: auto;
    }
    
    .modal-footer {
        padding: 15px 20px;
        border-top: 1px solid var(--gray-200);
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }
    
    /* ========================================
       FORM ERROR STYLES
       ======================================== */
    .error {
        border-color: var(--danger) !important;
    }
    
    .error-message {
        color: var(--danger);
        font-size: 0.8rem;
        margin-top: 5px;
    }
    
    /* ========================================
       CUSTOM TOOLTIP
       ======================================== */
    .custom-tooltip {
        position: fixed;
        background: #2d3748;
        color: white;
        padding: 5px 10px;
        border-radius: 4px;
        font-size: 0.8rem;
        pointer-events: none;
        z-index: 10000;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }
    
    .custom-tooltip::after {
        content: '';
        position: absolute;
        bottom: -5px;
        left: 50%;
        transform: translateX(-50%);
        border-width: 5px 5px 0;
        border-style: solid;
        border-color: #2d3748 transparent transparent;
    }
    
    /* ========================================
       BUTTON STYLES
       ======================================== */
    .btn {
        padding: 10px 20px;
        border-radius: 8px;
        border: none;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .btn-secondary {
        background: var(--gray-200);
        color: var(--gray-700);
    }
    
    .btn-secondary:hover {
        background: var(--gray-300);
    }
    
    .btn-danger {
        background: var(--danger);
        color: white;
    }
    
    .btn-danger:hover {
        background: #dc2626;
    }
    
    /* ========================================
       RESPONSIVE STYLES
       ======================================== */
    @media (max-width: 768px) {
        .keyboard-hint {
            left: 20px;
            right: 20px;
            min-width: auto;
            bottom: 10px;
        }
        
        .notification-toast-container {
            left: 20px;
            right: 20px;
            max-width: none;
        }
        
        .modal-dialog {
            padding: 10px;
        }
        
        .search-results {
            position: fixed;
            top: 60px;
            left: 10px;
            right: 10px;
            max-height: calc(100vh - 80px);
        }
        
        .hint-grid {
            max-height: 60vh;
            overflow-y: auto;
        }
    }
    
    @media (max-width: 480px) {
        .keyboard-hint {
            padding: 15px;
        }
        
        .hint-row {
            font-size: 0.8rem;
        }
        
        .shortcut kbd {
            padding: 1px 6px;
            font-size: 0.7rem;
        }
        
        .modal-header {
            padding: 15px;
        }
        
        .modal-body {
            padding: 15px;
        }
        
        .modal-footer {
            padding: 12px 15px;
        }
    }
    
    /* Touch device optimizations */
    @media (hover: none) and (pointer: coarse) {
        .search-item {
            padding: 12px 15px;
        }
        
        .close-modal {
            padding: 10px;
        }
        
        .btn {
            padding: 12px 24px;
        }
    }
    </style>
</body>
</html>