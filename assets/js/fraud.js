/**
 * fraud.js
 * Fraud Detection Dashboard — AJAX score refresh, risk detail drawer, auto-refresh, and UI helpers.
 */

const BASE_URL = '/jobhub';
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
    icon.classList.add('animate-spin');

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
        icon.classList.remove('animate-spin');
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
                    <i data-lucide="shield" class="text-4xl mb-3"></i>
                    <p class="font-medium">No suspicious users found</p>
                    <p class="text-sm">All users appear to be operating normally.</p>
                </td>
            </tr>`;
        lucide.createIcons();
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
                   <i data-lucide="circle-check" class="text-sm"></i>
                 </button>
               </form>`
            : '';
        const suspendBtn = user.status !== 'suspended'
            ? `<form method="POST" action="${BASE_URL}/admin/fraud_action.php" class="inline" onsubmit="return confirm('SUSPEND this user? This will prevent them from using the platform.')">
                 <input type="hidden" name="csrf_token" value="${document.getElementById('csrfToken')?.value || ''}">
                 <input type="hidden" name="action" value="suspend_user">
                 <input type="hidden" name="user_id" value="${user.id}">
                 <button type="submit" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition" title="Suspend User">
                   <i data-lucide="ban" class="text-sm"></i>
                 </button>
               </form>`
            : '';
        const flagBtn = user.status !== 'flagged'
            ? `<form method="POST" action="${BASE_URL}/admin/fraud_action.php" class="inline" onsubmit="return confirm('Flag this user?')">
                 <input type="hidden" name="csrf_token" value="${document.getElementById('csrfToken')?.value || ''}">
                 <input type="hidden" name="action" value="flag_user">
                 <input type="hidden" name="user_id" value="${user.id}">
                 <button type="submit" class="p-2 text-yellow-600 hover:bg-yellow-50 rounded-lg transition" title="Flag User">
                   <i data-lucide="flag" class="text-sm"></i>
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
                    <i data-lucide="eye" class="text-sm"></i>
                  </button>
                  ${flagBtn}${unflagBtn}${suspendBtn}
                </div>
              </td>
            </tr>`;
    }).join('');
    lucide.createIcons();
}

// ── View User Activity (Risk Drawer) ─────────────────────
async function viewUserActivity(userId) {
    const backdrop = document.getElementById('riskDrawerBackdrop');
    const drawer   = document.getElementById('riskDrawer');
    const skeleton = document.getElementById('drawerSkeleton');
    const content  = document.getElementById('drawerContent');
    if (!backdrop || !drawer) return;

    // Store userId for action buttons
    document.getElementById('drawerUserId').value = userId;

    // Reset to loading state
    skeleton.classList.remove('hidden');
    content.classList.add('hidden');
    backdrop.classList.add('open');
    drawer.classList.add('open');
    document.body.style.overflow = 'hidden';

    // Fetch score + reasons, and activity logs in parallel
    try {
        const [scoreRes, logRes] = await Promise.all([
            fetch(`${BASE_URL}/api/fraud_api.php?action=calculate_score&user_id=${userId}`, { credentials: 'same-origin' }),
            fetch(`${BASE_URL}/api/fraud_api.php?action=activity_log&user_id=${userId}`, { credentials: 'same-origin' })
        ]);
        const scoreData = await scoreRes.json();
        const logData   = await logRes.json();

        // Wait a beat so the skeleton shimmer is visible (UX polish)
        await new Promise(r => setTimeout(r, 280));

        populateDrawer(scoreData, logData, userId);

        skeleton.classList.add('hidden');
        content.classList.remove('hidden');
        lucide.createIcons();
    } catch (err) {
        console.error('Drawer fetch error:', err);
        skeleton.innerHTML = `
            <div class="text-center py-10">
                <div class="w-12 h-12 rounded-full bg-red-50 dark:bg-red-900/20 flex items-center justify-center mx-auto mb-3">
                    <i data-lucide="alert-triangle" class="text-red-500"></i>
                </div>
                <p class="text-sm font-semibold text-gray-700 dark:text-slate-300">Failed to load risk data</p>
                <p class="text-xs text-gray-400 dark:text-slate-500 mt-1">Please try again or check your connection.</p>
            </div>`;
    lucide.createIcons();
    }
}

// ── Populate Drawer with Fetched Data ─────────────────────────────────
function populateDrawer(scoreData, logData, userId) {
    const logs = logData.logs || [];
    const score = scoreData.score ?? 0;
    const reasons = scoreData.reasons || [];

    // --- Header: Avatar & Identity ---
    const firstLog = logs[0] || {};
    const userName  = firstLog.user_name || 'Unknown User';
    const userEmail = firstLog.user_email || '—';
    const profileImg = firstLog.profile_image
        ? `${BASE_URL}/assets/upload/profiles/${firstLog.profile_image}`
        : `${BASE_URL}/assets/upload/profile.png`;

    document.getElementById('drawerAvatar').src = profileImg;
    document.getElementById('drawerUserName').textContent = userName;
    document.getElementById('drawerUserEmail').textContent = userEmail;

    // Role badge from the suspicious users table row (if available)
    const userRow = document.querySelector(`tr[data-user-id="${userId}"]`);
    let roleText = 'freelancer';
    if (userRow) {
        const roleCell = userRow.querySelector('td:nth-child(2) span');
        if (roleCell) roleText = roleCell.textContent.trim().toLowerCase();
    }
    const roleBadge = document.getElementById('drawerRoleBadge');
    roleBadge.textContent = capitalize(roleText);
    const roleColors = {
        admin:      'bg-purple-50 dark:bg-purple-900/20 text-purple-700 dark:text-purple-400',
        client:     'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400',
        freelancer: 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400',
    };
    roleBadge.className = `inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider ${roleColors[roleText] || roleColors.freelancer}`;

    // Status dot
    const statusDot = document.getElementById('drawerStatusDot');
    const userStatus = userRow ? (userRow.querySelector('td:nth-child(4) span')?.textContent.trim().toLowerCase() || 'active') : 'active';
    const dotColors = { active: 'bg-emerald-400', flagged: 'bg-red-500', suspended: 'bg-gray-400' };
    statusDot.className = `absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 rounded-full border-2 border-white dark:border-slate-800 ${dotColors[userStatus] || dotColors.active}`;

    // --- Header: Score Pill ---
    const pill = document.getElementById('drawerScorePill');
    const pillVal = document.getElementById('drawerScoreValue');
    const pillLabel = document.getElementById('drawerScoreLabel');
    const bar = document.getElementById('drawerScoreBar');

    pillVal.textContent = `${score} / 100`;

    if (score >= 70) {
        pill.style.background = '#FEE2E2'; pill.style.color = '#991B1B';
        pillLabel.textContent = 'High Risk';
        bar.style.background = '#EF4444';
    } else if (score >= 40) {
        pill.style.background = '#FEF3C7'; pill.style.color = '#92400E';
        pillLabel.textContent = 'Medium Risk';
        bar.style.background = '#F59E0B';
    } else {
        pill.style.background = '#ECFDF5'; pill.style.color = '#065F46';
        pillLabel.textContent = 'Low Risk';
        bar.style.background = '#10B981';
    }
    bar.style.width = `${score}%`;

    // --- Risk Factor Breakdown ---
    const riskContainer = document.getElementById('drawerRiskFactors');
    if (reasons.length > 0) {
        riskContainer.innerHTML = reasons.map(r => {
            const isHigh = /\+\d{2,}/.test(r) || r.toLowerCase().includes('auto-flagged');
            const icon = isHigh ? 'alert-triangle text-amber-500' : 'info text-blue-500';
            const bg = isHigh ? 'bg-amber-50/50 dark:bg-amber-900/10' : 'bg-blue-50/50 dark:bg-blue-900/10';
            return `
                <div class="risk-factor-item flex items-start gap-2.5 px-3 py-2.5 ${bg}">
                    <i data-lucide="${icon.split(' ')[0]}" class="text-[11px] mt-0.5 flex-shrink-0"></i>
                    <span class="text-xs text-gray-700 dark:text-slate-300 leading-relaxed">${escapeHtml(r)}</span>
                </div>`;
        }).join('');
    } else {
    riskContainer.innerHTML = `
            <div class="flex items-center gap-2.5 px-3 py-4 bg-emerald-50/50 dark:bg-emerald-900/10">
                <i data-lucide="circle-check" class="text-emerald-500 text-[11px]"></i>
                <span class="text-xs text-gray-600 dark:text-slate-400">No risk factors detected. User appears clean.</span>
            </div>`;
    lucide.createIcons();
    }

    // --- Device & Network Intelligence ---
    const latestLog = logs[0] || {};
    const ip = latestLog.ip_address || '—';
    document.getElementById('drawerIP').textContent = ip;

    // Parse user agent from payload
    let deviceStr = '—';
    let locationStr = '—';
    let associatedStr = '1 account';
    try {
        const payload = latestLog.payload ? (typeof latestLog.payload === 'string' ? JSON.parse(latestLog.payload) : latestLog.payload) : {};
        if (payload.user_agent) {
            deviceStr = parseUserAgent(payload.user_agent);
        }
        if (payload.location) {
            locationStr = payload.location;
        } else if (payload.city || payload.country) {
            locationStr = [payload.city, payload.country].filter(Boolean).join(', ');
        }
        if (payload.associated_accounts) {
            associatedStr = `${payload.associated_accounts} accounts sharing this IP`;
        }
    } catch(e) { /* fallback to defaults */ }

    document.getElementById('drawerDevice').textContent = deviceStr;
    document.getElementById('drawerDevice').title = deviceStr;
    document.getElementById('drawerLocation').textContent = locationStr;
    document.getElementById('drawerAssociated').textContent = associatedStr;

    // Count unique IPs for associated accounts hint
    const uniqueIps = new Set(logs.map(l => l.ip_address).filter(Boolean));
    if (uniqueIps.size > 1) {
        document.getElementById('drawerAssociated').textContent = `${uniqueIps.size} unique IPs in recent logs`;
    }

    // --- Recent Activity Timeline (last 5) ---
    const timeline = document.getElementById('drawerTimeline');
    const recentLogs = logs.slice(0, 5);
    if (recentLogs.length > 0) {
        timeline.innerHTML = recentLogs.map(log => {
            const actionBadge = getActionColor(log.action_type);
            return `
                <div class="flex items-center gap-3 px-3 py-2.5">
                    <div class="flex-shrink-0 w-1.5 h-1.5 rounded-full bg-gray-300 dark:bg-slate-600"></div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold font-mono ${actionBadge}">${escapeHtml(log.action_type)}</span>
                            <span class="text-[10px] text-gray-400 dark:text-slate-500 font-mono">${escapeHtml(log.ip_address || '—')}</span>
                        </div>
                        <p class="text-[10px] text-gray-400 dark:text-slate-500 mt-0.5">${formatDate(log.created_at)}</p>
                    </div>
                </div>`;
        }).join('');
    } else {
        timeline.innerHTML = `
            <div class="px-3 py-4 text-center text-xs text-gray-400 dark:text-slate-500">
                No recent activity logs.
            </div>`;
    }
}

// ── Close Risk Drawer ─────────────────────────────────────────────────
function closeRiskDrawer() {
    const backdrop = document.getElementById('riskDrawerBackdrop');
    const drawer   = document.getElementById('riskDrawer');
    if (backdrop) backdrop.classList.remove('open');
    if (drawer) drawer.classList.remove('open');
    document.body.style.overflow = '';
}

// Close on Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeRiskDrawer();
});

// ── Drawer Security Actions (POST to fraud_action.php) ────────────────
async function drawerAction(action) {
    const userId = document.getElementById('drawerUserId').value;
    const csrf   = document.getElementById('drawerCsrfToken')?.value || '';
    if (!userId) return;

    const confirmMessages = {
        suspend_user:        'SUSPEND this user? They will be unable to use the platform.',
        unflag_user:         'DISMISS this flag and mark the user as safe? Their fraud score will reset to 0.',
    };

    // Actions not yet supported by backend
    const comingSoon = ['freeze_wallet', 'require_reverification'];
    if (comingSoon.includes(action)) {
        showToast(action.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) + ' — coming soon!', 'info');
        return;
    }
    if (!confirm(confirmMessages[action] || 'Are you sure?')) return;

    const btn = event.currentTarget;
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader" class="animate-spin text-[11px]"></i> Processing...';
    lucide.createIcons();

    try {
        const formData = new FormData();
        formData.append('csrf_token', csrf);
        formData.append('action', action);
        formData.append('user_id', userId);

        const response = await fetch(`${BASE_URL}/admin/fraud_action.php`, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
        });

        // fraud_action.php redirects, so a 200/302 means success
        if (response.ok || response.redirected) {
            showToast(`${action.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())} completed.`, 'success');
            closeRiskDrawer();
            // Refresh scores after action
            setTimeout(() => refreshScores(), 600);
        } else {
            showToast('Action failed. Please try again.', 'error');
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    } catch (err) {
        console.error('Drawer action error:', err);
        showToast('Network error. Please try again.', 'error');
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    }
}

// ── Copy IP to Clipboard from Drawer ──────────────────────────────────
function copyDrawerIP() {
    const ip = document.getElementById('drawerIP')?.textContent;
    if (!ip || ip === '—') return;
    navigator.clipboard.writeText(ip).then(() => {
        const btn = document.getElementById('drawerIPCopy');
        if (!btn) return;
        btn.innerHTML = '<i data-lucide="check" class="text-[10px] text-emerald-500"></i>';
        lucide.createIcons();
        setTimeout(() => { btn.innerHTML = '<i data-lucide="copy" class="text-[10px] text-gray-400 dark:text-slate-500"></i>'; lucide.createIcons(); }, 1200);
    });
}

// ── Parse User Agent String to Friendly Format ────────────────────────
function parseUserAgent(ua) {
    if (!ua) return '—';
    // Chrome
    let m = ua.match(/Chrome\/([\d.]+)/);
    if (m && !ua.match(/Edg\//)) return `Chrome ${m[1].split('.')[0]}`;
    // Firefox
    m = ua.match(/Firefox\/([\d.]+)/);
    if (m) return `Firefox ${m[1].split('.')[0]}`;
    // Safari
    m = ua.match(/Version\/([\d.]+).*Safari/);
    if (m) return `Safari ${m[1].split('.')[0]}`;
    // Edge
    m = ua.match(/Edg\/([\d.]+)/);
    if (m) return `Edge ${m[1].split('.')[0]}`;
    // Fallback: first 60 chars
    return ua.length > 60 ? ua.substring(0, 60) + '...' : ua;
}

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
        success: 'circle-check',
        error: 'alert-circle',
        warning: 'alert-triangle',
        info: 'info'
    };
    toast.className = `fixed top-4 right-4 z-50 px-5 py-3 rounded-xl text-white text-sm font-medium shadow-lg flex items-center gap-2 ${colors[type] || colors.info} fade-in`;
    toast.innerHTML = `<i data-lucide="${icons[type] || icons.info}"></i> ${escapeHtml(message)}`;
    document.body.appendChild(toast);
    lucide.createIcons();
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
