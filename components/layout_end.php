    </main>
</div><!-- /app-main -->

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('show');
}
function toggleDarkMode() {
    document.documentElement.classList.toggle('dark');
    const icon = document.getElementById('darkModeIcon');
    if (icon) icon.className = document.documentElement.classList.contains('dark') ? 'fas fa-sun text-sm' : 'fas fa-moon text-sm';
}
</script>
<script src="/finalproject/shared/dark-toggle.js"></script>
</body>
</html>
