/**
 * NotificationBell
 * Real-time notification system with SSE, dropdown panel, badge count,
 * auto-refresh, and optional sound.
 */
class NotificationBell {
    constructor(options = {}) {
        this.userId = options.userId || null;
        this.baseUrl = options.baseUrl || '/finalproject';
        this.pollInterval = options.pollInterval || 30000;
        this.soundEnabled = options.soundEnabled !== false;
        this.container = null;
        this.panel = null;
        this.badge = null;
        this.eventSource = null;
        this.pollTimer = null;
        this.isOpen = false;
        this.unreadCount = 0;
        this.notifications = [];
        this.soundUrl = options.soundUrl || `${this.baseUrl}/assets/sounds/notification.mp3`;

        this._init();
    }

    _init() {
        this._createHTML();
        this._bindEvents();
        this._loadNotifications();
        this._connectSSE();
        this._startPolling();
    }

    _createHTML() {
        // Bell button
        this.container = document.createElement('div');
        this.container.className = 'relative';
        this.container.innerHTML = `
            <button id="notification-bell-btn"
                class="relative p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-full transition-colors"
                title="Notifications">
                <i class="fas fa-bell text-lg"></i>
                <span id="notification-badge"
                    class="absolute -top-0.5 -right-0.5 hidden bg-red-500 text-white text-[10px] font-bold
                           min-w-[18px] h-[18px] flex items-center justify-center rounded-full px-1
                           shadow-sm ring-2 ring-white">
                    0
                </span>
            </button>
        `;

        // Panel
        this.panel = document.createElement('div');
        this.panel.id = 'notification-panel';
        this.panel.className = 'hidden absolute right-0 mt-2 w-80 sm:w-96 bg-white rounded-2xl shadow-2xl border border-gray-100 z-50 overflow-hidden';
        this.panel.innerHTML = `
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-gray-50">
                <h3 class="text-sm font-semibold text-gray-800">Notifications</h3>
                <button id="notification-mark-all"
                    class="text-xs text-blue-600 hover:text-blue-800 font-medium transition-colors">
                    Mark all read
                </button>
            </div>
            <div id="notification-list" class="max-h-80 overflow-y-auto divide-y divide-gray-50">
                <div class="p-6 text-center text-gray-400 text-sm">
                    <i class="fas fa-bell-slash text-2xl mb-2 block opacity-40"></i>
                    No notifications yet
                </div>
            </div>
            <a href="${this.baseUrl}/shared/notifications_page.php"
               class="block text-center text-xs font-medium text-blue-600 hover:text-blue-800 py-2.5 border-t border-gray-100 bg-gray-50 transition-colors">
                View all notifications
            </a>
        `;

        // Insert into navbar (assumes a #navbar-actions or similar exists, or appends to body)
        const navbarActions = document.getElementById('navbar-actions')
            || document.querySelector('.navbar-actions')
            || document.querySelector('nav .flex.items-center');

        if (navbarActions) {
            navbarActions.appendChild(this.container);
            this.container.appendChild(this.panel);
        } else {
            document.body.appendChild(this.container);
            this.container.style.position = 'fixed';
            this.container.style.top = '16px';
            this.container.style.right = '80px';
            this.container.style.zIndex = '9999';
        }

        this.badge = document.getElementById('notification-badge');
    }

    _bindEvents() {
        const bellBtn = document.getElementById('notification-bell-btn');
        bellBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            this._togglePanel();
        });

        document.addEventListener('click', (e) => {
            if (!this.panel.contains(e.target) && e.target !== bellBtn && !bellBtn.contains(e.target)) {
                this._closePanel();
            }
        });

        document.getElementById('notification-mark-all').addEventListener('click', () => {
            this._markAllRead();
        });

        // Keyboard accessibility
        bellBtn.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') this._closePanel();
        });
    }

    _togglePanel() {
        this.isOpen = !this.isOpen;
        this.panel.classList.toggle('hidden', !this.isOpen);
        if (this.isOpen) {
            this._loadNotifications();
        }
    }

    _closePanel() {
        this.isOpen = false;
        this.panel.classList.add('hidden');
    }

    async _loadNotifications() {
        try {
            const res = await fetch(`${this.baseUrl}/shared/notifications_api.php?action=list&limit=15`);
            if (!res.ok) throw new Error('Failed to load');
            const data = await res.json();

            this.notifications = data.notifications || [];
            this.unreadCount = data.unread_count || 0;
            this._renderList();
            this._updateBadge();
        } catch (err) {
            console.error('Notification load error:', err);
        }
    }

    _renderList() {
        const list = document.getElementById('notification-list');

        if (this.notifications.length === 0) {
            list.innerHTML = `
                <div class="p-6 text-center text-gray-400 text-sm">
                    <i class="fas fa-bell-slash text-2xl mb-2 block opacity-40"></i>
                    No notifications yet
                </div>
            `;
            return;
        }

        list.innerHTML = this.notifications.map(n => {
            const unread = n.is_read == 0 ? 'bg-blue-50/60' : '';
            const dot = n.is_read == 0
                ? '<span class="mt-1.5 w-2 h-2 rounded-full bg-blue-500 flex-shrink-0"></span>'
                : '';
            const icon = this._getIcon(n.type);
            const link = n.link || '#';

            return `
                <a href="${link}" data-id="${n.id}"
                   class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 transition-colors notification-item ${unread}">
                    <div class="flex-shrink-0 mt-0.5 w-8 h-8 rounded-full flex items-center justify-center ${this._getIconBg(n.type)}">
                        <i class="fas ${icon} text-xs ${this._getIconColor(n.type)}"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800 leading-snug">${this._esc(n.title)}</p>
                        <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">${this._esc(n.message)}</p>
                        <p class="text-[10px] text-gray-400 mt-1">${this._timeAgo(n.created_at)}</p>
                    </div>
                    ${dot}
                </a>
            `;
        }).join('');
    }

    _getIcon(type) {
        const icons = {
            new_proposal: 'fa-file-alt',
            proposal_accepted: 'fa-check-circle',
            new_contract: 'fa-handshake',
            milestone_submitted: 'fa-flag-checkered',
            payment_released: 'fa-dollar-sign',
            new_message: 'fa-comment-dots',
            review_received: 'fa-star',
        };
        return icons[type] || 'fa-bell';
    }

    _getIconBg(type) {
        const bgs = {
            new_proposal: 'bg-blue-100',
            proposal_accepted: 'bg-green-100',
            new_contract: 'bg-purple-100',
            milestone_submitted: 'bg-yellow-100',
            payment_released: 'bg-emerald-100',
            new_message: 'bg-cyan-100',
            review_received: 'bg-orange-100',
        };
        return bgs[type] || 'bg-gray-100';
    }

    _getIconColor(type) {
        const colors = {
            new_proposal: 'text-blue-600',
            proposal_accepted: 'text-green-600',
            new_contract: 'text-purple-600',
            milestone_submitted: 'text-yellow-600',
            payment_released: 'text-emerald-600',
            new_message: 'text-cyan-600',
            review_received: 'text-orange-600',
        };
        return colors[type] || 'text-gray-600';
    }

    _updateBadge() {
        if (this.unreadCount > 0) {
            this.badge.textContent = this.unreadCount > 99 ? '99+' : this.unreadCount;
            this.badge.classList.remove('hidden');
            document.title = `(${this.unreadCount}) JobHub`;
        } else {
            this.badge.classList.add('hidden');
            document.title = document.title.replace(/^\(\d+\)\s*/, '');
        }
    }

    async _markAllRead() {
        try {
            const res = await fetch(`${this.baseUrl}/shared/notifications_api.php?action=mark_all_read`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            });
            if (res.ok) {
                this.notifications.forEach(n => n.is_read = 1);
                this.unreadCount = 0;
                this._renderList();
                this._updateBadge();
            }
        } catch (err) {
            console.error('Mark all read error:', err);
        }
    }

    async _markSingleRead(id) {
        try {
            await fetch(`${this.baseUrl}/shared/notifications_api.php?action=mark_read&id=${id}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            });
            const n = this.notifications.find(x => x.id == id);
            if (n && n.is_read == 0) {
                n.is_read = 1;
                this.unreadCount = Math.max(0, this.unreadCount - 1);
                this._renderList();
                this._updateBadge();
            }
        } catch (err) {
            console.error('Mark read error:', err);
        }
    }

    _connectSSE() {
        if (this.eventSource) {
            this.eventSource.close();
        }

        this.eventSource = new EventSource(`${this.baseUrl}/shared/notification_stream.php`);

        this.eventSource.addEventListener('notification', (e) => {
            try {
                const data = JSON.parse(e.data);
                this.notifications.unshift(data);
                if (this.notifications.length > 50) {
                    this.notifications.pop();
                }
                this.unreadCount++;
                this._renderList();
                this._updateBadge();
                if (this.isOpen) {
                    this._markSingleRead(data.id);
                } else if (this.soundEnabled) {
                    this._playSound();
                }
                this._showToast(data);
            } catch (err) {
                console.error('SSE parse error:', err);
            }
        });

        this.eventSource.addEventListener('heartbeat', (e) => {
            try {
                const data = JSON.parse(e.data);
                this.unreadCount = data.unread_count;
                this._updateBadge();
            } catch (err) {
                // ignore
            }
        });

        this.eventSource.onerror = () => {
            this.eventSource.close();
            setTimeout(() => this._connectSSE(), 5000);
        };
    }

    _startPolling() {
        this.pollTimer = setInterval(() => {
            if (!this.eventSource || this.eventSource.readyState === EventSource.CLOSED) {
                this._loadNotifications();
            }
        }, this.pollInterval);
    }

    _playSound() {
        try {
            const audio = new Audio(this.soundUrl);
            audio.volume = 0.3;
            audio.play().catch(() => {});
        } catch (e) {
            // Silently fail if sound file not available
        }
    }

    _showToast(data) {
        const existing = document.getElementById('notification-toast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.id = 'notification-toast';
        toast.className = 'fixed top-4 right-4 z-[10000] max-w-sm w-full bg-white rounded-xl shadow-2xl border border-gray-100 p-4 transform translate-x-full transition-transform duration-300';
        toast.innerHTML = `
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                    <i class="fas ${this._getIcon(data.type)} text-blue-600 text-xs"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-800">${this._esc(data.title)}</p>
                    <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">${this._esc(data.message)}</p>
                </div>
                <button onclick="this.closest('#notification-toast').remove()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
        `;
        document.body.appendChild(toast);

        requestAnimationFrame(() => {
            toast.classList.remove('translate-x-full');
            toast.classList.add('translate-x-0');
        });

        setTimeout(() => {
            toast.classList.add('translate-x-full');
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }

    _timeAgo(datetime) {
        const diff = Math.floor(Date.now() / 1000) - Math.floor(new Date(datetime).getTime() / 1000);
        if (diff < 60) return 'Just now';
        if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
        if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
        if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
        return new Date(datetime).toLocaleDateString();
    }

    _esc(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    destroy() {
        if (this.eventSource) this.eventSource.close();
        if (this.pollTimer) clearInterval(this.pollTimer);
        if (this.container) this.container.remove();
    }
}

// Auto-initialize if a notification bell mount point exists
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('notification-bell-mount')) {
        window.notificationBell = new NotificationBell({
            baseUrl: '/finalproject',
            soundEnabled: true,
        });
    }
});
