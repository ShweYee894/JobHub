<?php
/**
 * Shared Top Navigation Header
 * 
 * Expects these variables to be set BEFORE including:
 *   $pageTitle    (string) Page title text
 *   $pageSubtitle (string) Subtitle text (e.g., "Welcome back, John")
 *   $userName     (string) User display name
 *   $userAvatar   (string) Profile image URL
 *   $userRole     (string) Role label (e.g., "Admin", "Client", "Freelancer")
 *   $roleColor    (string) Tailwind color for role badge (e.g., "blue", "emerald", "purple")
 *   $unreadCount  (int)    Notification count (optional, default 0)
 *   $profileLink  (string) Profile page URL (optional, default "profile.php")
 */
$_thTitle    = $pageTitle ?? 'Dashboard';
$_thSubtitle = $pageSubtitle ?? 'Welcome back';
$_thName     = $userName ?? 'User';
$_thAvatar   = $userAvatar ?? '/finalproject/assets/upload/profile.png';
$_thRole     = $userRole ?? 'User';
$_thRoleColor = $roleColor ?? 'blue';
$_thUnread   = $unreadCount ?? 0;
$_thProfile  = $profileLink ?? 'profile.php';

$_roleBadgeClasses = [
    'blue'    => 'background:#EEF5FF; color:#2563EB;',
    'emerald' => 'background:#ECFDF5; color:#059669;',
    'purple'  => 'background:#F5F3FF; color:#7C3AED;',
];
$_badgeStyle = $_roleBadgeClasses[$_thRoleColor] ?? $_roleBadgeClasses['blue'];
?>
<header class="top-header px-6 py-4" style="display:flex; align-items:center; justify-content:space-between;">
    <div style="display:flex; align-items:center; gap:16px;">
        <button onclick="toggleSidebar()" class="lg:hidden header-icon-btn">
            <i class="fas fa-bars" style="font-size:18px;"></i>
        </button>
        <div>
            <h1 style="font-size:18px; font-weight:700; color:#0f172a; margin:0;"><?= htmlspecialchars($_thTitle) ?></h1>
            <p style="font-size:12px; color:#94a3b8; margin:2px 0 0 0;"><?= htmlspecialchars($_thSubtitle) ?></p>
        </div>
    </div>
    <div style="display:flex; align-items:center; gap:8px;">
        <!-- Search -->
        <div style="position:relative; display:flex; align-items:center;" class="hidden sm:flex">
            <i class="fas fa-search" style="position:absolute; left:14px; color:#94a3b8; font-size:13px; pointer-events:none;"></i>
            <input type="text" id="globalSearch" placeholder="Search anything..." class="search-input" style="width:280px;" oninput="handleGlobalSearch(this.value)">
            <div id="searchResults" style="display:none; position:absolute; top:calc(100% + 6px); left:0; right:0; background:#fff; border:1px solid #E5EDF6; border-radius:14px; box-shadow:0 12px 35px rgba(15,23,42,0.12); max-height:360px; overflow-y:auto; z-index:50;"></div>
        </div>

        <!-- Notifications -->
        <a href="../shared/notifications_page.php" class="header-icon-btn" title="Notifications" style="position:relative;">
            <i class="fas fa-bell" style="font-size:16px;"></i>
            <?php if ($_thUnread > 0): ?>
                <span class="notification-dot"></span>
            <?php endif; ?>
        </a>

        <!-- Quick Actions -->
        <div style="position:relative;" class="quick-actions-dropdown">
            <button onclick="toggleQuickActions()" class="header-icon-btn" title="Quick Actions">
                <i class="fas fa-plus" style="font-size:16px;"></i>
            </button>
            <div id="quickActionsMenu" style="display:none; position:absolute; right:0; top:calc(100% + 8px); background:#fff; border:1px solid #E5EDF6; border-radius:16px; box-shadow:0 12px 35px rgba(15,23,42,0.12); min-width:200px; padding:8px; z-index:50;">
                <p style="font-size:11px; font-weight:600; color:#94a3b8; text-transform:uppercase; letter-spacing:0.05em; padding:8px 12px 4px; margin:0;">Quick Actions</p>
                <?php if ($_thRole === 'Admin'): ?>
                    <a href="/finalproject/admin/users.php" style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:10px; font-size:13px; color:#475569; text-decoration:none; transition:background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                        <i class="fas fa-users" style="width:16px; color:#2563EB;"></i> Manage Users
                    </a>
                    <a href="/finalproject/admin/jobs.php" style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:10px; font-size:13px; color:#475569; text-decoration:none; transition:background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                        <i class="fas fa-briefcase" style="width:16px; color:#059669;"></i> Manage Jobs
                    </a>
                    <a href="/finalproject/admin/settings.php" style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:10px; font-size:13px; color:#475569; text-decoration:none; transition:background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                        <i class="fas fa-cog" style="width:16px; color:#7C3AED;"></i> Platform Settings
                    </a>
                <?php elseif ($_thRole === 'Client'): ?>
                    <a href="/finalproject/client/post_job.php" style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:10px; font-size:13px; color:#475569; text-decoration:none; transition:background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                        <i class="fas fa-plus-circle" style="width:16px; color:#2563EB;"></i> Post a Job
                    </a>
                    <a href="/finalproject/client/recommended_freelancers.php" style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:10px; font-size:13px; color:#475569; text-decoration:none; transition:background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                        <i class="fas fa-user-plus" style="width:16px; color:#059669;"></i> Find Freelancers
                    </a>
                    <a href="/finalproject/client/contracts.php" style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:10px; font-size:13px; color:#475569; text-decoration:none; transition:background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                        <i class="fas fa-handshake" style="width:16px; color:#7C3AED;"></i> View Contracts
                    </a>
                <?php else: ?>
                    <a href="/finalproject/freelancer/browse_jobs.php" style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:10px; font-size:13px; color:#475569; text-decoration:none; transition:background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                        <i class="fas fa-search" style="width:16px; color:#2563EB;"></i> Browse Jobs
                    </a>
                    <a href="/finalproject/freelancer/proposals.php" style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:10px; font-size:13px; color:#475569; text-decoration:none; transition:background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                        <i class="fas fa-file-alt" style="width:16px; color:#059669;"></i> My Proposals
                    </a>
                    <a href="/finalproject/freelancer/earnings.php" style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:10px; font-size:13px; color:#475569; text-decoration:none; transition:background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                        <i class="fas fa-wallet" style="width:16px; color:#7C3AED;"></i> Withdraw Earnings
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Dark Mode Toggle -->
        <button onclick="toggleDarkMode()" class="header-icon-btn" title="Toggle dark mode">
            <i class="fas fa-moon" style="font-size:15px;" id="darkModeIcon"></i>
        </button>

        <!-- Profile -->
        <a href="<?= htmlspecialchars($_thProfile) ?>" style="display:flex; align-items:center; gap:10px; padding:6px 12px 6px 6px; border-radius:14px; text-decoration:none; transition:background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
            <img src="<?= htmlspecialchars($_thAvatar) ?>" style="width:36px; height:36px; border-radius:50%; object-fit:cover; border:2px solid #E5EDF6;" alt="Avatar">
            <div style="min-width:0;">
                <p style="font-size:13px; font-weight:600; color:#0f172a; margin:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:120px;"><?= htmlspecialchars($_thName) ?></p>
                <span style="display:inline-block; font-size:10px; font-weight:600; padding:2px 8px; border-radius:6px; margin-top:2px; <?= $_badgeStyle ?>"><?= htmlspecialchars($_thRole) ?></span>
            </div>
        </a>
    </div>
</header>

<script>
function toggleQuickActions() {
    const menu = document.getElementById('quickActionsMenu');
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}
document.addEventListener('click', function(e) {
    const dropdown = document.querySelector('.quick-actions-dropdown');
    if (dropdown && !dropdown.contains(e.target)) {
        document.getElementById('quickActionsMenu').style.display = 'none';
    }
    const searchBox = document.getElementById('globalSearch');
    const results = document.getElementById('searchResults');
    if (searchBox && results && !searchBox.contains(e.target) && !results.contains(e.target)) {
        results.style.display = 'none';
    }
});

function handleGlobalSearch(query) {
    const resultsDiv = document.getElementById('searchResults');
    if (!resultsDiv) return;
    
    query = query.trim().toLowerCase();
    if (query.length < 2) {
        resultsDiv.style.display = 'none';
        // Remove highlights
        document.querySelectorAll('.search-highlight').forEach(el => {
            el.style.background = '';
            el.style.outline = '';
            el.classList.remove('search-highlight');
        });
        return;
    }

    // Collect searchable items from the page
    const items = [];
    
    // Search stat cards
    document.querySelectorAll('.stat-card-compact').forEach(card => {
        const text = card.textContent.toLowerCase();
        if (text.includes(query)) {
            const title = card.querySelector('p')?.textContent?.trim() || '';
            const value = card.querySelector('span[style*="font-size:24px"]')?.textContent?.trim() || '';
            items.push({ type: 'Stat', label: title + ' - ' + value, el: card });
        }
    });

    // Search table rows
    document.querySelectorAll('.data-table tbody tr').forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(query)) {
            const firstCell = row.querySelector('td')?.textContent?.trim()?.substring(0, 50) || '';
            items.push({ type: 'Table', label: firstCell, el: row });
        }
    });

    // Search dashboard cards
    document.querySelectorAll('.dashboard-card').forEach(card => {
        const heading = card.querySelector('h4, h3');
        if (heading) {
            const headingText = heading.textContent.toLowerCase();
            if (headingText.includes(query)) {
                items.push({ type: 'Section', label: heading.textContent.trim(), el: card });
            }
        }
        // Search within card content
        const cardText = card.textContent.toLowerCase();
        if (cardText.includes(query) && !items.find(i => i.el === card)) {
            const headingText = heading?.textContent?.trim()?.substring(0, 50) || 'Card';
            items.push({ type: 'Content', label: headingText, el: card });
        }
    });

    // Search quick action cards
    document.querySelectorAll('.quick-action-card').forEach(card => {
        const text = card.textContent.toLowerCase();
        if (text.includes(query)) {
            const label = card.querySelector('h5')?.textContent?.trim() || '';
            items.push({ type: 'Action', label: label, el: card });
        }
    });

    // Search timeline items
    document.querySelectorAll('.timeline-item').forEach(item => {
        const text = item.textContent.toLowerCase();
        if (text.includes(query)) {
            const label = item.querySelector('p')?.textContent?.trim()?.substring(0, 50) || '';
            items.push({ type: 'Activity', label: label, el: item });
        }
    });

    // Search user cards (admin)
    document.querySelectorAll('a[href*="user_detail"]').forEach(card => {
        const text = card.textContent.toLowerCase();
        if (text.includes(query)) {
            const name = card.querySelector('p')?.textContent?.trim() || '';
            items.push({ type: 'User', label: name, el: card });
        }
    });

    // Search freelancer cards
    document.querySelectorAll('.dashboard-card .btn-primary').forEach(btn => {
        const card = btn.closest('.dashboard-card');
        if (card) {
            const text = card.textContent.toLowerCase();
            if (text.includes(query)) {
                const name = card.querySelector('p[style*="font-weight:700"]')?.textContent?.trim() || '';
                if (name && !items.find(i => i.el === card)) {
                    items.push({ type: 'Freelancer', label: name, el: card });
                }
            }
        }
    });

    // Deduplicate by element
    const seen = new Set();
    const unique = items.filter(i => {
        if (seen.has(i.el)) return false;
        seen.add(i.el);
        return true;
    });

    // Render results
    if (unique.length === 0) {
        resultsDiv.innerHTML = '<div style="padding:20px; text-align:center; color:#94a3b8; font-size:13px;"><i class="fas fa-search" style="font-size:20px; display:block; margin-bottom:8px; color:#cbd5e1;"></i>No results found for "' + escapeHtml(query) + '"</div>';
    } else {
        let html = '<div style="padding:8px;">';
        html += '<p style="font-size:11px; font-weight:600; color:#94a3b8; padding:4px 12px; margin:0;">' + unique.length + ' result' + (unique.length !== 1 ? 's' : '') + ' found</p>';
        unique.slice(0, 10).forEach(item => {
            html += '<button onclick="scrollToAndHighlight(this)" data-target-id="' + getItemId(item.el) + '" style="width:100%; text-align:left; display:flex; align-items:center; gap:10px; padding:10px 12px; border:none; background:transparent; border-radius:10px; cursor:pointer; transition:background 0.15s;" onmouseover="this.style.background=\'#f8fafc\'" onmouseout="this.style.background=\'transparent\'">';
            html += '<span style="display:inline-block; padding:2px 8px; border-radius:6px; font-size:10px; font-weight:600; background:#EEF5FF; color:#2563EB; flex-shrink:0;">' + item.type + '</span>';
            html += '<span style="font-size:13px; color:#334155; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">' + escapeHtml(item.label) + '</span>';
            html += '</button>';
        });
        html += '</div>';
        resultsDiv.innerHTML = html;
    }
    resultsDiv.style.display = 'block';
}

function getItemId(el) {
    if (!el.id) {
        el.id = 'search-item-' + Math.random().toString(36).substr(2, 9);
    }
    return el.id;
}

function scrollToAndHighlight(btn) {
    const targetId = btn.getAttribute('data-target-id');
    const el = document.getElementById(targetId);
    if (!el) return;

    // Remove old highlights
    document.querySelectorAll('.search-highlight').forEach(h => {
        h.style.background = '';
        h.style.outline = '';
        h.style.boxShadow = '';
        h.classList.remove('search-highlight');
    });

    // Scroll into view
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });

    // Highlight
    setTimeout(() => {
        el.style.background = '#EEF5FF';
        el.style.outline = '2px solid #2563EB';
        el.style.boxShadow = '0 0 0 4px rgba(37,99,235,0.1)';
        el.classList.add('search-highlight');

        // Remove highlight after 3s
        setTimeout(() => {
            el.style.background = '';
            el.style.outline = '';
            el.style.boxShadow = '';
            el.classList.remove('search-highlight');
        }, 3000);
    }, 300);

    // Close search dropdown
    document.getElementById('searchResults').style.display = 'none';
    document.getElementById('globalSearch').value = '';
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
</script>
