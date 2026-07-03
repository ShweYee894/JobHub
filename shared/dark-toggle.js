/**
 * FreelanceHub – Dark Mode Toggle
 * Include this script in every page. It reads/writes localStorage
 * and toggles the 'dark' class on <html>.
 */
(function () {
    const STORAGE_KEY = 'fh-dark-mode';

    // Apply saved preference immediately (before paint)
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    }

    window.toggleDarkMode = function () {
        const isDark = document.documentElement.classList.toggle('dark');
        localStorage.setItem(STORAGE_KEY, isDark ? 'dark' : 'light');
    };
})();
