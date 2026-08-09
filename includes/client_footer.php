<?php
/** Client Footer – Site-wide footer for all client pages. */
?>
<!-- ═══════════════════════ FOOTER ══════════════════════════════ -->
<footer class="border-t border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 pt-16 pb-8 px-4 mt-12">
    <div class="max-w-7xl mx-auto">
        <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-10 mb-14">
            <!-- Brand -->
            <div class="lg:col-span-2">
                <!-- <a href="../index.php" class="flex items-center gap-2 mb-5">
                    <div class="w-9 h-9 rounded-xl btn-grad flex items-center justify-center shadow-lg shadow-blue-500/25">
                        <i class="fas fa-bolt text-white text-sm"></i>
                    </div>
                    <span class="text-xl font-extrabold"><span class="text-gray-900 dark:text-white">Job</span><span class="grad-text">Hub</span></span>
                </a> -->
                <a href="../index.php" class="flex items-center gap-1.5 group shrink-0">
                    <img src="../assets/upload/logos/logo.png" alt="Logo" class="w-[36px] h-[36px] rounded-xl">
                    <span class="text-lg font-extrabold tracking-tight">
                        <span class="text-gray-900 dark:text-white">Job</span><span class="grad-text">Hub</span>
                    </span>
                </a>
                <p class="text-gray-400 dark:text-slate-500 text-sm leading-relaxed mb-6 max-w-xs">The world's most trusted marketplace for top freelancers and innovative clients. Work smarter, together.</p>
                <div class="flex gap-3">
                    <a href="#" class="w-9 h-9 bg-gray-50 dark:bg-slate-700 rounded-xl flex items-center justify-center text-gray-400 hover:text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all border border-gray-100 dark:border-slate-600"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 4s-.7 2.1-2 3.4c1.6 10-9.4 17.3-18 11.6 2.2.1 4.4-.6 6-2C3 15.5.5 9.6 3 5c2.2 2.6 5.6 4.1 9 4-.9-4.2 4-6.6 7-3.8 1.1 0 3-1.2 3-1.2z"></path></svg></a>
                    <a href="#" class="w-9 h-9 bg-gray-50 dark:bg-slate-700 rounded-xl flex items-center justify-center text-gray-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all border border-gray-100 dark:border-slate-600"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path><rect width="4" height="12" x="2" y="9"></rect><circle cx="4" cy="4" r="2"></circle></svg></a>
                    <a href="#" class="w-9 h-9 bg-gray-50 dark:bg-slate-700 rounded-xl flex items-center justify-center text-gray-400 hover:text-pink-500 hover:bg-pink-50 dark:hover:bg-pink-900/20 transition-all border border-gray-100 dark:border-slate-600"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="20" x="2" y="2" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" x2="17.51" y1="6.5" y2="6.5"></line></svg></a>
                    <a href="#" class="w-9 h-9 bg-gray-50 dark:bg-slate-700 rounded-xl flex items-center justify-center text-gray-400 hover:text-gray-700 hover:bg-gray-100 dark:hover:bg-slate-600 transition-all border border-gray-100 dark:border-slate-600"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 22v-4a4.8 4.8 0 0 0-1-3.5c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.403 5.403 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4"></path><path d="M9 18c-4.51 2-5-2-7-2"></path></svg></a>
                    <a href="#" class="w-9 h-9 bg-gray-50 dark:bg-slate-700 rounded-xl flex items-center justify-center text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-all border border-gray-100 dark:border-slate-600"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 17a24.12 24.12 0 0 1 0-10 2 2 0 0 1 1.4-1.4 49.56 49.56 0 0 1 16.2 0A2 2 0 0 1 21.5 7a24.12 24.12 0 0 1 0 10 2 2 0 0 1-1.4 1.4 49.55 49.55 0 0 1-16.2 0A2 2 0 0 1 2.5 17"></path><path d="m10 15 5-3-5-3z"></path></svg></a>
                </div>
            </div>
            <!-- Company -->
            <div>
                <h4 class="text-gray-900 dark:text-white font-bold text-sm uppercase tracking-widest mb-5">Company</h4>
                <ul class="space-y-3 text-gray-400 dark:text-slate-500 text-sm">
                    <li><a href="../index.php" class="hover:text-gray-900 dark:hover:text-white transition-colors">Home</a></li>
                    <li><a href="../index.php#about" class="hover:text-gray-900 dark:hover:text-white transition-colors">About Us</a></li>
                    <li><a href="../careers.php" class="hover:text-gray-900 dark:hover:text-white transition-colors">Careers</a></li>
                    <li><a href="../pricing.php" class="hover:text-gray-900 dark:hover:text-white transition-colors">Pricing</a></li>
                </ul>
            </div>
            <!-- Support -->
            <div>
                <h4 class="text-gray-900 dark:text-white font-bold text-sm uppercase tracking-widest mb-5">Support</h4>
                <ul class="space-y-3 text-gray-400 dark:text-slate-500 text-sm">
                    <li><a href="../faq.php" class="hover:text-gray-900 dark:hover:text-white transition-colors">FAQ</a></li>
                    <li><a href="../contact.php" class="hover:text-gray-900 dark:hover:text-white transition-colors">Contact Us</a></li>
                    <li><a href="../privacy.php" class="hover:text-gray-900 dark:hover:text-white transition-colors">Privacy Policy</a></li>
                    <li><a href="../terms.php" class="hover:text-gray-900 dark:hover:text-white transition-colors">Terms &amp; Conditions</a></li>
                </ul>
            </div>
            <!-- Contact -->
            <div>
                <h4 class="text-gray-900 dark:text-white font-bold text-sm uppercase tracking-widest mb-5">Contact</h4>
                <ul class="space-y-3 text-gray-400 dark:text-slate-500 text-sm">
                    <li class="flex items-start gap-2"><i data-lucide="mail" class="w-4 h-4 text-blue-500 mt-0.5"></i><a href="mailto:hello@jobhub.io" class="hover:text-gray-900 dark:hover:text-white transition-colors">hello@jobhub.io</a></li>
                    <li class="flex items-start gap-2"><i data-lucide="phone" class="w-4 h-4 text-cyan-500 mt-0.5"></i><span>+95 9 757 889806</span></li>
                    <li class="flex items-start gap-2"><i data-lucide="map-pin" class="w-4 h-4 text-purple-500 mt-0.5"></i><span>Myanmar, Yangon 11041</span></li>
                </ul>
                <div class="mt-6">
                    <p class="text-gray-500 dark:text-slate-500 text-xs font-medium mb-2">Stay in the loop:</p>
                    <form action="../newsletter.php" method="POST" class="flex gap-2">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? ''; ?>">
                        <input type="email" name="email" placeholder="your@email.com" required class="flex-1 bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-xl px-3 py-2 text-xs text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 outline-none focus:border-primary transition-colors" />
                        <button type="submit" class="bg-indigo-600 text-white text-xs font-bold px-3 py-2 rounded-md"><i data-lucide="send" class="w-4 h-4"></i></button>
                    </form>
                </div>
            </div>
        </div>
        <div class="border-t border-gray-100 dark:border-slate-700 pt-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-gray-400 dark:text-slate-500 text-xs">
            <p>&copy; 2026 JobHub. All rights reserved.</p>
            <div class="flex gap-4">
                <a href="../privacy.php" class="hover:text-gray-600 dark:hover:text-slate-300 transition-colors">Privacy</a>
                <a href="../terms.php" class="hover:text-gray-600 dark:hover:text-slate-300 transition-colors">Terms</a>
                <a href="../faq.php" class="hover:text-gray-600 dark:hover:text-slate-300 transition-colors">FAQ</a>
            </div>
        </div>
    </div>
</footer>

<!-- Back to Top -->
<button id="back-top" class="fixed bottom-6 right-6 w-8 h-8 bg-indigo-600 text-white rounded-xl shadow-xl shadow-blue-500/25 hidden items-center justify-center hover:-translate-y-1 transition-all z-50" onclick="window.scrollTo({top:0,behavior:'smooth'})">
    <i data-lucide="chevron-up" class="w-5 h-5"></i>
</button>
<script>
window.addEventListener('scroll', () => {
    const btn = document.getElementById('back-top');
    if (btn) btn.classList.toggle('hidden', window.scrollY < 400);
    if (btn) btn.classList.toggle('flex', window.scrollY >= 400);
});
</script>
<script src="https://unpkg.com/lucide@0.344.0/dist/umd/lucide.min.js"></script>
<script>
function fixIcons() {
    lucide.createIcons();
    document.querySelectorAll('svg[data-lucide]').forEach(function(svg) {
        svg.removeAttribute('width');svg.removeAttribute('height');
        svg.style.removeProperty('width');svg.style.removeProperty('height');
        var p = svg.parentElement;
        if (p && p.tagName === 'I') { var fs = window.getComputedStyle(p).fontSize; svg.style.width = fs; svg.style.height = fs; }
    });
}
fixIcons();
</script>
</body>
</html>
