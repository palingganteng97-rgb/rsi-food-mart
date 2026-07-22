/**
 * notifications.js
 * 
 * Real-time notification system for RSI FOOD & MART.
 * Features: Browser Notification API, sound, badge, dropdown, auto-polling.
 * 
 * Include this script after Bootstrap JS on pages that need notifications.
 * 
 * Usage:
 *   <script src="notifications.js"></script>
 */

(function() {
    'use strict';

    // ============================================================
    // CONFIGURATION
    // ============================================================
    const CONFIG = {
        POLL_INTERVAL: 5000,            // Poll every 5 seconds
        MAX_DROPDOWN_ITEMS: 10,
        DEBUG: false                     // Set true for console logging
    };

    // ============================================================
    // STATE
    // ============================================================
    let state = {
        lastMaxId: 0,
        unreadCount: 0,
        totalUnread: 0,
        notificationPermission: 'default',
        shownNotificationIds: new Set(),
        isPolling: false,
        pollTimer: null,
        dropdownOpen: false
    };

    // ============================================================
    // DOM REFS (populated on init)
    // ============================================================
    let DOM = {
        bellBtn: null,
        badgeEl: null,
        dropdown: null,
        dropdownMenu: null,
        dropdownList: null,
        dropdownFooter: null
    };

    // ============================================================
    // LOGGING
    // ============================================================
    function log(...args) {
        if (CONFIG.DEBUG) {
            console.log('[Notifications]', ...args);
        }
    }

    // ============================================================
    // NOTIFICATION PERMISSION
    // ============================================================
    function requestPermission() {
        if (!('Notification' in window)) {
            log('Browser Notification API not supported');
            return;
        }

        if (Notification.permission === 'granted') {
            state.notificationPermission = 'granted';
            log('Notification permission already granted');
            return;
        }

        if (Notification.permission === 'denied') {
            state.notificationPermission = 'denied';
            log('Notification permission denied by user');
            return;
        }

        // Request permission (only once)
        Notification.requestPermission().then(function(permission) {
            state.notificationPermission = permission;
            log('Notification permission:', permission);
        }).catch(function(err) {
            log('Error requesting permission:', err);
        });
    }

    // ============================================================
    // SHOW BROWSER NOTIFICATION
    // ============================================================
    function showBrowserNotification(title, message, link) {
        if (!('Notification' in window) || Notification.permission !== 'granted') {
            return;
        }

        try {
            const notif = new Notification(title, {
                body: message,
                icon: 'uploads/logo rsi.png',
                badge: 'uploads/logo rsi.png',
                tag: 'rsi-notification-' + Date.now(),
                requireInteraction: true
            });

            // Click handler to open link
            if (link) {
                notif.onclick = function() {
                    window.focus();
                    window.location.href = link;
                    this.close();
                };
            }

            // Auto close after 10 seconds
            setTimeout(function() {
                notif.close();
            }, 10000);

            return notif;
        } catch (e) {
            log('Error showing notification:', e);
        }
    }

    // ============================================================
    // UPDATE BADGE
    // ============================================================
    function updateBadge(count) {
        state.totalUnread = count;

        if (!DOM.badgeEl) return;

        if (count > 0) {
            DOM.badgeEl.textContent = count > 99 ? '99+' : count;
            DOM.badgeEl.style.display = 'flex';
            DOM.badgeEl.classList.remove('d-none');
        } else {
            DOM.badgeEl.textContent = '0';
            DOM.badgeEl.style.display = 'none';
            DOM.badgeEl.classList.add('d-none');
        }
    }

    // ============================================================
    // FORMAT RELATIVE TIME (Indonesian)
    // ============================================================
    function formatTimeAgo(timeAgoStr) {
        return timeAgoStr || 'baru saja';
    }

    // ============================================================
    // RENDER DROPDOWN NOTIFICATIONS
    // ============================================================
    function renderDropdown(notifications) {
        if (!DOM.dropdownList) return;

        if (!notifications || notifications.length === 0) {
            DOM.dropdownList.innerHTML = `
                <div class="text-center py-4 text-white-50">
                    <i class="bi bi-bell-slash d-block mb-2" style="font-size: 1.5rem;"></i>
                    <small>Tidak ada notifikasi</small>
                </div>
            `;
            return;
        }

        let html = '';
        const maxItems = Math.min(notifications.length, CONFIG.MAX_DROPDOWN_ITEMS);

        for (let i = 0; i < maxItems; i++) {
            const n = notifications[i];
            const isUnread = n.is_read === 0;
            const timeAgo = formatTimeAgo(n.time_ago);

            html += `
                <a href="${n.link || '#'}" class="notification-item dropdown-item d-flex gap-3 px-3 py-3 ${isUnread ? 'unread' : ''}" 
                   data-id="${n.id}" data-link="${n.link || ''}"
                   style="${isUnread ? 'background: rgba(34,197,94,0.08); border-left: 3px solid #22c55e;' : 'background: transparent; border-left: 3px solid transparent;'}
                   text-decoration: none; color: #e5e7eb; border-bottom: 1px solid rgba(148,163,184,0.1); cursor: pointer;
                   transition: background 0.15s ease;">
                    <div class="flex-shrink-0 mt-1">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" 
                             style="width: 36px; height: 36px; background: ${isUnread ? 'rgba(34,197,94,0.2)' : 'rgba(148,163,184,0.15)'};">
                            <i class="bi ${isUnread ? 'bi-bell-fill text-success' : 'bi-bell text-white-50'}" style="font-size: 0.9rem;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 min-width-0" style="max-width: calc(100% - 50px);">
                        <div class="d-flex justify-content-between align-items-start">
                            <strong class="text-truncate d-block" style="font-size: 0.85rem; ${isUnread ? 'color: #fff;' : 'color: #94a3b8;'}">
                                ${escapeHtml(n.title)}
                            </strong>
                            <small class="text-white-50 ms-2 flex-shrink-0" style="font-size: 0.7rem;">${timeAgo}</small>
                        </div>
                        <div class="text-truncate mt-1" style="font-size: 0.78rem; color: ${isUnread ? '#cbd5e1' : '#64748b'};">
                            ${escapeHtml(n.message)}
                        </div>
                    </div>
                </a>
            `;
        }

        DOM.dropdownList.innerHTML = html;

        // Add click handlers for each notification item
        DOM.dropdownList.querySelectorAll('.notification-item').forEach(function(item) {
            item.addEventListener('click', function(e) {
                const id = parseInt(this.getAttribute('data-id'));
                const link = this.getAttribute('data-link');
                
                // Don't prevent default if there's a link - let it navigate
                if (link && link !== '#') {
                    // Mark as read before redirecting
                    markAsRead(id);
                } else {
                    e.preventDefault();
                    markAsRead(id);
                }
            });
        });
    }

    // ============================================================
    // ESCAPE HTML
    // ============================================================
    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // ============================================================
    // MARK AS READ (AJAX)
    // ============================================================
    function markAsRead(notificationId, callback) {
        const formData = new FormData();
        formData.append('id', notificationId);

        fetch('mark_notification_read.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                log('Marked as read:', notificationId);

                // Update UI: remove unread styling
                const item = DOM.dropdownList.querySelector('.notification-item[data-id="' + notificationId + '"]');
                if (item) {
                    item.style.background = 'transparent';
                    item.style.borderLeft = '3px solid transparent';
                    item.classList.remove('unread');
                    
                    const icon = item.querySelector('.rounded-circle i');
                    if (icon) {
                        icon.className = 'bi bi-bell text-white-50';
                    }
                    const strong = item.querySelector('strong');
                    if (strong) {
                        strong.style.color = '#94a3b8';
                    }
                }

                // Decrement badge
                if (state.totalUnread > 0) {
                    updateBadge(state.totalUnread - 1);
                }

                if (typeof callback === 'function') {
                    callback(data);
                }
            }
        })
        .catch(function(err) {
            log('Error marking as read:', err);
        });
    }

    // ============================================================
    // MARK ALL AS READ
    // ============================================================
    function markAllAsRead() {
        const formData = new FormData();
        formData.append('all', 'true');

        fetch('mark_notification_read.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                log('All notifications marked as read');
                updateBadge(0);

                // Update all items in dropdown
                DOM.dropdownList.querySelectorAll('.notification-item').forEach(function(item) {
                    item.style.background = 'transparent';
                    item.style.borderLeft = '3px solid transparent';
                    item.classList.remove('unread');
                    
                    const icon = item.querySelector('.rounded-circle i');
                    if (icon) {
                        icon.className = 'bi bi-bell text-white-50';
                    }
                    const strong = item.querySelector('strong');
                    if (strong) {
                        strong.style.color = '#94a3b8';
                    }
                });
            }
        })
        .catch(function(err) {
            log('Error marking all as read:', err);
        });
    }

    // ============================================================
    // FETCH NOTIFICATIONS FROM SERVER
    // ============================================================
    function fetchNotifications(sinceId) {
        let url = 'get_notifications.php';
        if (sinceId > 0) {
            url += '?since_id=' + sinceId;
        }

        return fetch(url)
            .then(function(response) { return response.json(); })
            .catch(function(err) {
                log('Fetch error:', err);
                return null;
            });
    }

    // ============================================================
    // PROCESS NEW NOTIFICATIONS (polling callback)
    // ============================================================
    function processNewNotifications() {
        fetchNotifications(state.lastMaxId).then(function(data) {
            if (!data || !data.success) return;

            // Update badge with total unread
            updateBadge(data.total_unread);

            // Check for new notifications
            const notifications = data.notifications || [];

            if (notifications.length > 0) {
                // Update lastMaxId
                if (data.max_id > 0) {
                    state.lastMaxId = data.max_id;
                }

                // Process each new notification
                notifications.forEach(function(n) {
                    // Skip if we've already shown this notification
                    if (state.shownNotificationIds.has(n.id)) return;
                    state.shownNotificationIds.add(n.id);

                    // Show browser notification
                    showBrowserNotification(n.title, n.message, n.link);
                });

                // Refresh dropdown if open
                if (state.dropdownOpen) {
                    refreshDropdown();
                }

                log('New notifications processed:', notifications.length);
            }
        });
    }

    // ============================================================
    // REFRESH DROPDOWN LIST
    // ============================================================
    function refreshDropdown() {
        fetchNotifications(0).then(function(data) {
            if (data && data.success) {
                renderDropdown(data.notifications);
            }
        });
    }

    // ============================================================
    // START POLLING
    // ============================================================
    function startPolling() {
        if (state.isPolling) return;
        state.isPolling = true;

        log('Starting polling every ' + CONFIG.POLL_INTERVAL + 'ms');

        // Immediate first fetch
        processNewNotifications();

        // Set interval
        state.pollTimer = setInterval(processNewNotifications, CONFIG.POLL_INTERVAL);
    }

    // ============================================================
    // STOP POLLING
    // ============================================================
    function stopPolling() {
        if (state.pollTimer) {
            clearInterval(state.pollTimer);
            state.pollTimer = null;
        }
        state.isPolling = false;
        log('Polling stopped');
    }

    // ============================================================
    // BUILD DROPDOWN UI
    // ============================================================
    function buildDropdownUI(bellBtn) {
        // Create dropdown container
        const dropdownDiv = document.createElement('div');
        dropdownDiv.className = 'dropdown-menu dropdown-menu-end notification-dropdown';
        dropdownDiv.style.cssText = `
            background: #0b1223;
            border: 1px solid rgba(148,163,184,0.25);
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            padding: 0;
            margin-top: 12px !important;
            overflow: hidden;
        `;

        // Dropdown header
        const header = document.createElement('div');
        header.className = 'dropdown-header d-flex justify-content-between align-items-center px-3 py-3';
        header.style.cssText = 'border-bottom: 1px solid rgba(148,163,184,0.15); background: rgba(15,23,42,0.5);';
        header.innerHTML = `
            <strong class="text-white" style="font-size: 0.95rem;">Notifikasi</strong>
            <button class="btn btn-sm btn-link text-white-50 p-0 mark-all-read-btn" style="text-decoration: none; font-size: 0.8rem;">
                <i class="bi bi-check2-all me-1"></i>Baca Semua
            </button>
        `;
        dropdownDiv.appendChild(header);

        // Scrollable list container
        const listDiv = document.createElement('div');
        listDiv.className = 'notification-list';
        listDiv.style.cssText = `
            max-height: 400px;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: rgba(148,163,184,0.3) transparent;
        `;
        // Custom scrollbar styles
        const styleSheet = document.createElement('style');
        styleSheet.textContent = `
            .notification-list::-webkit-scrollbar { width: 4px; }
            .notification-list::-webkit-scrollbar-track { background: transparent; }
            .notification-list::-webkit-scrollbar-thumb { background: rgba(148,163,184,0.3); border-radius: 4px; }
            .notification-item:hover { background: rgba(148,163,184,0.08) !important; }
        `;
        document.head.appendChild(styleSheet);
        dropdownDiv.appendChild(listDiv);

        // Footer with "Lihat Semua" link
        const footer = document.createElement('div');
        footer.className = 'dropdown-footer text-center py-2';
        footer.style.cssText = 'border-top: 1px solid rgba(148,163,184,0.15); background: rgba(15,23,42,0.5);';
        footer.innerHTML = `
            <a href="notifications.php" class="btn btn-sm text-success w-100 py-2" 
               style="font-weight: 500; text-decoration: none;">
                <i class="bi bi-eye me-1"></i> Lihat Semua Notifikasi
            </a>
        `;
        dropdownDiv.appendChild(footer);

        // Store refs
        DOM.dropdown = dropdownDiv;
        DOM.dropdownMenu = dropdownDiv;
        DOM.dropdownList = listDiv;
        DOM.dropdownFooter = footer;

        // Append to bell button parent
        bellBtn.parentNode.appendChild(dropdownDiv);
        dropdownDiv.style.display = 'none';

        // Event: Mark all as read
        header.querySelector('.mark-all-read-btn').addEventListener('click', function(e) {
            e.stopPropagation();
            markAllAsRead();
        });

        return dropdownDiv;
    }

    // ============================================================
    // TOGGLE DROPDOWN
    // ============================================================
    function toggleDropdown(e) {
        e.preventDefault();
        e.stopPropagation();

        state.dropdownOpen = !state.dropdownOpen;

        if (state.dropdownOpen) {
            // Show dropdown
            DOM.dropdown.style.display = 'block';
            refreshDropdown();
            
            // Set bell as active
            DOM.bellBtn.classList.add('active');
        } else {
            hideDropdown();
        }
    }

    function hideDropdown() {
        state.dropdownOpen = false;
        if (DOM.dropdown) {
            DOM.dropdown.style.display = 'none';
        }
        if (DOM.bellBtn) {
            DOM.bellBtn.classList.remove('active');
        }
    }

    // ============================================================
    // CREATE BELL ICON WITH BADGE
    // ============================================================
    function createBellIcon() {
        // Container
        const container = document.createElement('div');
        container.className = 'notification-bell-container position-relative d-inline-block';
        container.style.cssText = 'cursor: pointer; user-select: none;';

        // Bell button
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-link text-white-50 position-relative p-0 border-0 notification-bell-btn';
        btn.style.cssText = `
            font-size: 1.35rem;
            line-height: 1;
            text-decoration: none;
            transition: color 0.15s ease;
            background: none;
            cursor: pointer;
        `;
        btn.setAttribute('aria-label', 'Notifikasi');
        btn.innerHTML = '<i class="bi bi-bell"></i>';

        // Badge
        const badge = document.createElement('span');
        badge.className = 'notification-badge position-absolute d-none';
        badge.style.cssText = `
            top: -6px;
            right: -8px;
            background: #ef4444;
            color: #fff;
            font-size: 0.6rem;
            font-weight: 700;
            min-width: 18px;
            height: 18px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
            box-shadow: 0 2px 6px rgba(239,68,68,0.4);
            border: 2px solid #0b1223;
            line-height: 1;
        `;
        badge.textContent = '0';

        btn.appendChild(badge);
        container.appendChild(btn);

        // Store refs
        DOM.bellBtn = btn;
        DOM.badgeEl = badge;

        // Build dropdown
        buildDropdownUI(btn);

        // Toggle dropdown on click
        btn.addEventListener('click', toggleDropdown);

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (state.dropdownOpen && !container.contains(e.target)) {
                hideDropdown();
            }
        });

        return container;
    }

    // ============================================================
    // INJECT INTO NAVBAR / SIDEBAR
    // ============================================================
    function injectIntoNavbar() {
        // Find the navbar right section - try multiple selectors
        const navbarRightSelectors = [
            '.navbar .ms-auto',
            '.navbar .d-flex.align-items-center.gap-2',
            '.navbar .d-flex.align-items-center',
            '.mobile-topbar .topbar-right',          // Patient mobile topbar
            '.mobile-topbar .d-flex.align-items-center.w-100.justify-content-between',
            '.navbar-nav.ms-auto',
            '.navbar-collapse'
        ];

        let target = null;
        for (const selector of navbarRightSelectors) {
            target = document.querySelector(selector);
            if (target) break;
        }

        // For desktop sidebar - find the sidebar notification area (patient) or sidebar footer (admin)
        if (!target) {
            const sidebarNotifArea = document.querySelector('.sidebar-notification-area');
            if (sidebarNotifArea) {
                // Insert bell into the dedicated notification area in patient sidebar
                const bellContainer = createBellIcon();
                bellContainer.style.marginRight = '0';
                bellContainer.style.marginLeft = '0';
                bellContainer.style.marginBottom = '0';
                bellContainer.style.width = '100%';
                bellContainer.style.display = 'flex';
                bellContainer.style.justifyContent = 'center';
                sidebarNotifArea.appendChild(bellContainer);
                log('Injected into sidebar notification area (patient desktop)');
                return true;
            }

            // Check if we're on an admin sidebar page
            const sidebarFooter = document.querySelector('.sidebar-footer');
            const appBrand = document.querySelector('.app-brand');
            
            if (sidebarFooter) {
                // Insert before logout button in sidebar
                const bellContainer = createBellIcon();
                bellContainer.style.marginRight = '16px';
                bellContainer.style.marginLeft = '16px';
                bellContainer.style.marginBottom = '8px';
                sidebarFooter.parentNode.insertBefore(bellContainer, sidebarFooter);
                log('Injected into sidebar footer');
                return true;
            }
            
            if (appBrand) {
                // Insert in app brand area
                const bellContainer = createBellIcon();
                bellContainer.style.marginLeft = 'auto';
                bellContainer.style.marginRight = '8px';
                appBrand.appendChild(bellContainer);
                log('Injected into app brand');
                return true;
            }

            log('Could not find navbar or sidebar target');
            return false;
        }

        // Create bell and inject into navbar
        const bellContainer = createBellIcon();
        bellContainer.style.marginLeft = '12px';
        target.appendChild(bellContainer);
        log('Injected into navbar');
        return true;
    }

    // ============================================================
    // ADD STYLES
    // ============================================================
    function addStyles() {
        const styleId = 'rsi-notification-styles';
        if (document.getElementById(styleId)) return;

        const style = document.createElement('style');
        style.id = styleId;
        style.textContent = `
            .notification-bell-btn:hover {
                color: #22c55e !important;
            }
            .notification-bell-btn.active {
                color: #22c55e !important;
            }
            .notification-bell-btn.active i {
                animation: bell-ring 0.5s ease;
            }
            @keyframes bell-ring {
                0%, 100% { transform: rotate(0deg); }
                25% { transform: rotate(15deg); }
                50% { transform: rotate(-15deg); }
                75% { transform: rotate(10deg); }
            }
            .notification-item {
                transition: all 0.15s ease;
            }
            .notification-item:hover {
                background: rgba(148,163,184,0.08) !important;
            }
            .notification-dropdown {
                animation: dropdown-fade 0.2s ease;
                position: fixed !important;
                z-index: 1080 !important;
                top: 60px;
                left: 0.5rem;
                right: 0.5rem;
                width: auto;
                max-width: 420px;
                min-width: 0;
                transform: none !important;
                margin: 0 auto !important;
                max-height: calc(100vh - 80px);
                overflow-y: auto;
                overflow-x: hidden;
            }
            /* Desktop: override position to absolute relative to bell button */
            @media (min-width: 576px) {
                .notification-dropdown {
                    position: absolute !important;
                    top: auto;
                    left: auto;
                    right: auto;
                    width: auto;
                    min-width: 360px;
                    max-width: 400px;
                    margin-top: 12px !important;
                }
            }
            /* Ensure bell container has proper positioning for desktop dropdown anchor */
            .notification-bell-container {
                position: relative !important;
            }
            @keyframes dropdown-fade {
                from {
                    opacity: 0;
                    transform: translateY(-8px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `;
        document.head.appendChild(style);
    }

    // ============================================================
    // INTEGRATION WITH SIDEBAR - Find and use existing bell if present
    // ============================================================
    function checkExistingBell() {
        // Check if someone already placed a bell icon element
        const existing = document.querySelector('.notification-bell-container');
        if (existing) {
            // Already initialized
            return true;
        }
        return false;
    }

    // ============================================================
    // INITIALIZATION
    // ============================================================
    function init() {
        log('Initializing notification system...');

        // Add styles
        addStyles();

        // Check if already initialized
        if (checkExistingBell()) {
            log('Already initialized, updating refs...');
            DOM.bellBtn = document.querySelector('.notification-bell-btn');
            DOM.badgeEl = document.querySelector('.notification-badge');
            DOM.dropdown = document.querySelector('.notification-dropdown');
            DOM.dropdownList = document.querySelector('.notification-list');
            
            if (DOM.bellBtn && DOM.dropdown && DOM.dropdownList) {
                // Re-bind events
                DOM.bellBtn.addEventListener('click', toggleDropdown);
                
                // Start polling
                requestPermission();
                startPolling();
                return;
            }
        }

        // Inject bell into UI
        const injected = injectIntoNavbar();

        if (!injected) {
            log('Will retry injection after DOM ready...');
            // Retry after a short delay
            setTimeout(function() {
                const retryInjected = injectIntoNavbar();
                if (retryInjected) {
                    requestPermission();
                    startPolling();
                } else {
                    log('Failed to inject notification bell - no suitable location found');
                }
            }, 1000);
            return;
        }

        // Start polling
        requestPermission();
        startPolling();
    }

    // ============================================================
    // START ON DOM READY
    // ============================================================
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose for debugging
    window.__notifications = {
        state: state,
        DOM: DOM,
        markAsRead: markAsRead,
        markAllAsRead: markAllAsRead,
        refreshDropdown: refreshDropdown,
        toggleDropdown: toggleDropdown,
        CONFIG: CONFIG
    };

})();

