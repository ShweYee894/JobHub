/**
 * fraud.js
 * Fraud Detection Dashboard — AJAX score refresh, user activity modal, auto-refresh, and UI helpers.
 */

const BASE_URL = '/finalproject';
let autoRefreshInterval = null;

// ── Initialize on DOM Ready ───────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    startAutoRefresh();
});

// ── Refresh All Fraud Scores via AJAX ─────────────────────────────────
async function refreshScores() {
    const btn = document.querySelector('[onclick="refreshScores()"]');
    const icon = document.getElementById('refreshIcon');
    if (!btn || !icon) return;

    btn.disabled = true;
    icon.classList.add('fa-spin');

    try {
        // Step 1: Recalculate all scores
        const calcResponse = await fetch(`${BASE_URL}/api/fraud_api.php?action=recalculate_all`, {
            credentials: 'same-origin'
        });
        const calcData = await calcResponse.json();

        if (!calcData.success) {
            showToast('Failed to recalculate scores.', 'error');
            return;
        }

        // Step 2: Fetch updated suspicious users list
        const response = await fetch(`${BASE_URL}/api/fraud_api.php?action=suspicious`, {
            credentials: 'same-origin'
        });
        const data = await response.json();

        if (data.success && data.users) {
            updateSuspiciousTable(data.users);

            // Step 3: Update overview card counts
            let flagged = 0, highRisk = 0, mediumRisk = 0;
            data.users.forEach(u => {
                if (u.status === 'flagged') flagged++;
                if (u.fraud_score >= 70) highRisk++;
                if (u.fraud_score >= 40 && u.fraud_score <= 69) mediumRisk++;
            });
            const elFlagged = document.getElementById('countFlagged');
            const elHigh = document.getElementById('countHigh');
            const elMedium = document.getElementById('countMedium');
            if (elFlagged) elFlagged.textContent = flagged;
            if (elHigh) elHigh.textContent = highRisk;
            if (elMedium) elMedium.textContent = mediumRisk;

            showToast(calcData.message || 'Scores recalculated successfully.', 'success');
        } else {
            showToast('Failed to load suspicious users.', 'error');
        }
    } catch (err) {
        console.error('Score refresh error:', err);
        showToast('Network error during refresh.', 'error');
    } finally {
        btn.disabled = false;
        icon.classList.remove('fa-spin');
    }
}

// ── Update the Suspicious Users Table in-place ────────────────────────
function updateSuspiciousTable(users) {
    const tbody = document.getElementById('suspiciousTableBody');
    if (!tbody) return;

    if (users.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="px-6 py-12 text-center text-gray-400">
                    <i class="fas fa-shield-alt text-4xl mb-3"></i>
                    <p class="font-medium">No suspicious users found</p>
                    <p class="text-sm">All users appear to be operating normally.</p>
                </td>
            </tr>`;
        return;
    }

    tbody.innerHTML = users.map(user => {
        const profileImg = user.profile_image
            ? `${BASE_URL}/assets/upload/profiles/${user.profile_image}`
            : `${BASE_URL}/assets/upload/profile.png`;
        const roleBadge = getRoleBadge(user.role);
        const scoreColor = getScoreColor(user.fraud_score);
        const scoreBarClass = getScoreBarClass(user.fraud_score);
        const statusBadge = getStatusBadge(user.status);
        const lastActionHtml = user.last_action
            ? `<p class="text-gray-900 font-medium text-xs">${escapeHtml(user.last_action)}</p>
               <p class="text-xs text-gray-400">${timeAgo(user.last_action_time)}</p>`
            : '<span class="text-gray-400 text-xs">No activity</span>';
        const ipsClass = (user.unique_ips_24h || 0) > 3 ? 'text-red-600' : 'text-gray-700';
        const unflagBtn = user.status !== 'flagged'
            ? `<form method="POST" action="${BASE_URL}/admin/fraud_action.php" class="inline" onsubmit="return confirm('Unflag this user and reset score?')">
                 <input type="hidden" name="csrf_token" value="${document.getElementById('csrfToken')?.value || ''}">
                 <input type="hidden" name="action" value="unflag_user">
                 <input type="hidden" name="user_id" value="${user.id}">
                 <button type="submit" class="p-2 text-green-600 hover:bg-green-50 rounded-lg transition" title="Unflag User">
                   <i class="fas fa-check-circle text-sm"></i>
                 </button>
               </form>`
            : '';
        const suspendBtn = user.status !== 'suspended'
            ? `<form method="POST" action="${BASE_URL}/admin/fraud_action.php" class="inline" onsubmit="return confirm('SUSPEND this user? This will prevent them from using the platform.')">
                 <input type="hidden" name="csrf_token" value="${document.getElementById('csrfToken')?.value || ''}">
                 <input type="hidden" name="action" value="suspend_user">
                 <input type="hidden" name="user_id" value="${user.id}">
                 <button type="submit" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition" title="Suspend User">
                   <i class="fas fa-ban text-sm"></i>
                 </button>
               </form>`
            : '';
        const flagBtn = user.status !== 'flagged'
            ? `<form method="POST" action="${BASE_URL}/admin/fraud_action.php" class="inline" onsubmit="return confirm('Flag this user?')">
                 <input type="hidden" name="csrf_token" value="${document.getElementById('csrfToken')?.value || ''}">
                 <input type="hidden" name="action" value="flag_user">
                 <input type="hidden" name="user_id" value="${user.id}">
                 <button type="submit" class="p-2 text-yellow-600 hover:bg-yellow-50 rounded-lg transition" title="Flag User">
                   <i class="fas fa-flag text-sm"></i>
                 </button>
               </form>`
            : '';

        return `
            <tr class="hover:bg-gray-50 transition" data-user-id="${user.id}">
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <img src="${profileImg}" alt="" class="w-9 h-9 rounded-full object-cover ring-2 ring-gray-200">
                  <div>
                    <p class="font-medium text-gray-900">${escapeHtml(user.name)}</p>
                    <p class="text-xs text-gray-500">${escapeHtml(user.email)}</p>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4">${roleBadge}</td>
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <div class="w-32 bg-gray-200 rounded-full h-2.5">
                    <div class="${scoreBarClass} h-2.5 rounded-full score-bar" style="width: ${user.fraud_score}%"></div>
                  </div>
                  <span class="font-bold text-sm ${scoreColor}">${user.fraud_score}</span>
                </div>
              </td>
              <td class="px-6 py-4">${statusBadge}</td>
              <td class="px-6 py-4">${lastActionHtml}</td>
              <td class="px-6 py-4">
                <span class="font-semibold ${ipsClass}">${user.unique_ips_24h || 0}</span>
              </td>
              <td class="px-6 py-4">
                <div class="flex items-center gap-2">
                  <button onclick="viewUserActivity(${user.id})" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="View Activity">
                    <i class="fas fa-eye text-sm"></i>
                  </button>
                  ${flagBtn}${unflagBtn}${suspendBtn}
                </div>
              </td>
            </tr>`;
    }).join('');
}

// ── View User Activity in Modal ───────────────────────────────────────
async function viewUserActivity(userId) {
    const modal = document.getElementById('activityModal');
    const content = document.getElementById('activityModalContent');
    if (!modal || !content) return;

    modal.classList.remove('hidden');
    content.innerHTML = `
        <div class="text-center py-8 text-gray-400">
            <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
            <p>Loading activity...</p>
        </div>`;

    try {
        const response = await fetch(`${BASE_URL}/api/fraud_api.php?action=activity_log&user_id=${userId}`, {
            credentials: 'same-origin'
        });
        const data = await response.json();

        if (data.success && data.logs && data.logs.length > 0) {
            const rows = data.logs.map(log => {
                const actionColor = getActionColor(log.action_type);
                const payload = log.payload ? tryParseJson(log.payload) : null;
                const detailsHtml = payload
                    ? `<button onclick="this.nextElementSibling.classList.toggle('hidden')" class="text-blue-600 text-xs hover:underline"><i class="fas fa-code"></i> View</button>
                       <pre class="hidden mt-1 bg-gray-50 border border-gray-200 rounded-lg p-2 text-xs text-gray-600 max-w-xs overflow-auto">${escapeHtml(JSON.stringify(payload, null, 2))}</pre>`
                    : '<span class="text-gray-400 text-xs">-</span>';

                return `
                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                      <td class="py-3 text-xs text-gray-500 whitespace-nowrap">${formatDate(log.created_at)}</td>
                      <td class="py-3"><span class="px-2.5 py-1 rounded-full text-xs font-semibold ${actionColor}">${escapeHtml(log.action_type)}</span></td>
                      <td class="py-3 font-mono text-xs text-gray-600">${escapeHtml(log.ip_address)}</td>
                      <td class="py-3">${detailsHtml}</td>
                    </tr>`;
            }).join('');

            content.innerHTML = `
                <div class="mb-4 flex items-center justify-between">
                    <h4 class="font-semibold text-gray-900">${escapeHtml(data.logs[0]?.user_name || '')}</h4>
                    <span class="text-sm text-gray-500">${data.pagination.total_items} entries</span>
                </div>
                <div class="overflow-x-auto">
                  <table class="w-full text-sm">
                    <thead class="border-b border-gray-200">
                      <tr class="text-left text-gray-500 text-xs uppercase">
                        <th class="pb-2 pr-4">Time</th>
                        <th class="pb-2 pr-4">Action</th>
                        <th class="pb-2 pr-4">IP</th>
                        <th class="pb-2">Details</th>
                      </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                  </table>
                </div>`;
        } else {
            content.innerHTML = `
                <div class="text-center py-8 text-gray-400">
                    <i class="fas fa-inbox text-3xl mb-2"></i>
                    <p class="font-medium">No activity logs found for this user.</p>
                </div>`;
        }
    } catch (err) {
        console.error('Activity fetch error:', err);
        content.innerHTML = `
            <div class="text-center py-8 text-red-400">
                <i class="fas fa-exclamation-circle text-3xl mb-2"></i>
                <p class="font-medium">Failed to load activity.</p>
            </div>`;
    }
}

// ── Close Activity Modal ──────────────────────────────────────────────
function closeActivityModal() {
    const modal = document.getElementById('activityModal');
    if (modal) modal.classList.add('hidden');
}

// Close modal on outside click
document.addEventListener('click', (e) => {
    const modal = document.getElementById('activityModal');
    if (modal && e.target === modal) {
        closeActivityModal();
    }
});

// Close modal on Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeActivityModal();
});

// ── Auto-Refresh Every 30 Seconds ─────────────────────────────────────
function startAutoRefresh() {
    autoRefreshInterval = setInterval(async () => {
        try {
            const response = await fetch(`${BASE_URL}/api/fraud_api.php?action=suspicious`, {
                credentials: 'same-origin'
            });
            const data = await response.json();
            if (data.success && data.users) {
                updateSuspiciousTable(data.users);
            }
        } catch (err) {
            // Silently fail on auto-refresh
            console.warn('Auto-refresh failed:', err);
        }
    }, 30000);
}

function stopAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
    }
}

// ── Toast Notification ────────────────────────────────────────────────
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    const colors = {
        success: 'bg-green-600',
        error: 'bg-red-600',
        warning: 'bg-yellow-600',
        info: 'bg-blue-600'
    };
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    toast.className = `fixed top-4 right-4 z-50 px-5 py-3 rounded-xl text-white text-sm font-medium shadow-lg flex items-center gap-2 ${colors[type] || colors.info} fade-in`;
    toast.innerHTML = `<i class="fas ${icons[type] || icons.info}"></i> ${escapeHtml(message)}`;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ── Helper: Score Color Class ─────────────────────────────────────────
function getScoreColor(score) {
    if (score > 60) return 'text-red-600';
    if (score > 30) return 'text-yellow-600';
    return 'text-green-600';
}

function getScoreBarClass(score) {
    if (score > 60) return 'bg-red-500';
    if (score > 30) return 'bg-yellow-500';
    return 'bg-green-500';
}

// ── Helper: Role Badge ────────────────────────────────────────────────
function getRoleBadge(role) {
    const classes = {
        admin: 'bg-purple-100 text-purple-700',
        client: 'bg-blue-100 text-blue-700',
        freelancer: 'bg-green-100 text-green-700'
    };
    const cls = classes[role] || 'bg-gray-100 text-gray-700';
    return `<span class="px-2.5 py-1 rounded-full text-xs font-medium ${cls}">${escapeHtml(capitalize(role))}</span>`;
}

// ── Helper: Status Badge ──────────────────────────────────────────────
function getStatusBadge(status) {
    const classes = {
        active: 'bg-green-100 text-green-700',
        flagged: 'bg-red-100 text-red-700',
        suspended: 'bg-gray-100 text-gray-700'
    };
    const cls = classes[status] || 'bg-gray-100 text-gray-700';
    return `<span class="px-2.5 py-1 rounded-full text-xs font-semibold ${cls}">${escapeHtml(capitalize(status))}</span>`;
}

// ── Helper: Action Type Color ─────────────────────────────────────────
function getActionColor(action) {
    const colors = {
        login_failed: 'bg-red-100 text-red-700',
        spam: 'bg-orange-100 text-orange-700',
        phishing: 'bg-red-100 text-red-700',
        fake_review: 'bg-yellow-100 text-yellow-700',
        payment_fraud: 'bg-red-100 text-red-700',
        account_takeover: 'bg-red-100 text-red-700',
        suspicious_download: 'bg-purple-100 text-purple-700',
        proposal_submit: 'bg-blue-100 text-blue-700',
        admin_flag_user: 'bg-amber-100 text-amber-700',
        admin_unflag_user: 'bg-green-100 text-green-700',
        admin_suspend_user: 'bg-red-100 text-red-700',
        admin_reset_score: 'bg-teal-100 text-teal-700'
    };
    return colors[action] || 'bg-gray-100 text-gray-700';
}

// ── Utility Functions ─────────────────────────────────────────────────
function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function capitalize(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
        + ' ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
}

function timeAgo(dateStr) {
    if (!dateStr) return '';
    const now = new Date();
    const then = new Date(dateStr);
    const diff = Math.floor((now - then) / 1000);

    if (diff < 60) return 'just now';
    if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
    return then.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function tryParseJson(str) {
    try {
        return JSON.parse(str);
    } catch {
        return null;
    }
}
