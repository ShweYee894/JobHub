    </main>
</div><!-- /app-main -->

<script>
function fixIcons() {
    lucide.createIcons();
    document.querySelectorAll('svg[data-lucide]').forEach(function(svg) {
        svg.removeAttribute('width');
        svg.removeAttribute('height');
        svg.style.removeProperty('width');
        svg.style.removeProperty('height');
        var p = svg.parentElement;
        if (p && p.tagName === 'I') {
            var fs = window.getComputedStyle(p).fontSize;
            svg.style.width = fs;
            svg.style.height = fs;
        }
    });
}

/* ═══ SIDEBAR COLLAPSE ═══ */
function toggleSidebarCollapse() {
    var sidebar = document.getElementById('sidebar');
    var main = document.getElementById('appMain');
    sidebar.classList.toggle('collapsed');
    main.classList.toggle('sidebar-collapsed');
    localStorage.setItem('fh-sidebar-collapsed', sidebar.classList.contains('collapsed') ? '1' : '0');
}

/* ═══ SIDEBAR MOBILE TOGGLE ═══ */
function toggleSidebar() {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('mobile-open');
    overlay.classList.toggle('show');
}

/* ═══ ACCORDION GROUPS ═══ */
function toggleGroup(groupEl) {
    var wasOpen = groupEl.classList.contains('open');
    /* Close all groups */
    var groups = document.querySelectorAll('.sidebar-group');
    for (var i = 0; i < groups.length; i++) {
        groups[i].classList.remove('open');
    }
    /* Open clicked one (if it wasn't already open) */
    if (!wasOpen) {
        groupEl.classList.add('open');
    }
    /* Save state */
    var openKeys = [];
    var openGroups = document.querySelectorAll('.sidebar-group.open');
    for (var j = 0; j < openGroups.length; j++) {
        openKeys.push(openGroups[j].getAttribute('data-group'));
    }
    localStorage.setItem('fh-sidebar-groups', JSON.stringify(openKeys));
}

/* ═══ DARK MODE ═══ */
function toggleDarkMode() {
    var html = document.documentElement;
    html.classList.toggle('dark');
    var isDark = html.classList.contains('dark');
    localStorage.setItem('fh-dark-mode', isDark ? '1' : '0');
    updateDarkIcon(isDark);
}

function updateDarkIcon(isDark) {
    var icon = document.getElementById('darkModeIcon');
    if (!icon) return;
    icon.setAttribute('data-lucide', isDark ? 'sun' : 'moon');
    fixIcons();
}

/* ═══ INIT ON LOAD ═══ */
document.addEventListener('DOMContentLoaded', function() {
    /* Sidebar collapse */
    if (localStorage.getItem('fh-sidebar-collapsed') === '1') {
        document.getElementById('sidebar').classList.add('collapsed');
        document.getElementById('appMain').classList.add('sidebar-collapsed');
    }

    /* Restore accordion state */
    var saved = localStorage.getItem('fh-sidebar-groups');
    if (saved) {
        try {
            var openKeys = JSON.parse(saved);
            for (var i = 0; i < openKeys.length; i++) {
                var g = document.querySelector('.sidebar-group[data-group="' + openKeys[i] + '"]');
                if (g) g.classList.add('open');
            }
        } catch(e) {}
    }

    /* Dark mode icon */
    var isDark = document.documentElement.classList.contains('dark');
    updateDarkIcon(isDark);

    /* Render Lucide icons */
    if (typeof lucide !== 'undefined') fixIcons();
});
</script>
</body>
</html>
