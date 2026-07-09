<?php
/**
 * Client Footer – Site-wide footer for all client pages.
 */
?>
<!-- ═══════════════════════ FOOTER ══════════════════════════════ -->
<footer class="border-t border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 pt-16 pb-8 px-4 mt-12">
    <div class="max-w-7xl mx-auto">
        <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-10 mb-14">
            <!-- Brand -->
            <div class="lg:col-span-2">
                <a href="../index.php" class="flex items-center gap-2 mb-5">
                    <div class="w-9 h-9 rounded-xl btn-grad flex items-center justify-center shadow-lg shadow-blue-500/25">
                        <i class="fas fa-bolt text-white text-sm"></i>
                    </div>
                    <span class="text-xl font-extrabold"><span class="text-gray-900 dark:text-white">Job</span><span class="grad-text">Hub</span></span>
                </a>
                <p class="text-gray-400 dark:text-slate-500 text-sm leading-relaxed mb-6 max-w-xs">The world's most trusted marketplace for top freelancers and innovative clients. Work smarter, together.</p>
                <div class="flex gap-3">
                    <a href="#" class="w-9 h-9 bg-gray-50 dark:bg-slate-700 rounded-lg flex items-center justify-center text-gray-400 hover:text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all border border-gray-100 dark:border-slate-600"><i class="fab fa-twitter text-sm"></i></a>
                    <a href="#" class="w-9 h-9 bg-gray-50 dark:bg-slate-700 rounded-lg flex items-center justify-center text-gray-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all border border-gray-100 dark:border-slate-600"><i class="fab fa-linkedin-in text-sm"></i></a>
                    <a href="#" class="w-9 h-9 bg-gray-50 dark:bg-slate-700 rounded-lg flex items-center justify-center text-gray-400 hover:text-pink-500 hover:bg-pink-50 dark:hover:bg-pink-900/20 transition-all border border-gray-100 dark:border-slate-600"><i class="fab fa-instagram text-sm"></i></a>
                    <a href="#" class="w-9 h-9 bg-gray-50 dark:bg-slate-700 rounded-lg flex items-center justify-center text-gray-400 hover:text-gray-700 hover:bg-gray-100 dark:hover:bg-slate-600 transition-all border border-gray-100 dark:border-slate-600"><i class="fab fa-github text-sm"></i></a>
                    <a href="#" class="w-9 h-9 bg-gray-50 dark:bg-slate-700 rounded-lg flex items-center justify-center text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-all border border-gray-100 dark:border-slate-600"><i class="fab fa-youtube text-sm"></i></a>
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
                    <li class="flex items-start gap-2"><i class="fas fa-envelope text-blue-500 mt-0.5"></i><a href="mailto:hello@jobhub.io" class="hover:text-gray-900 dark:hover:text-white transition-colors">hello@jobhub.io</a></li>
                    <li class="flex items-start gap-2"><i class="fas fa-phone text-cyan-500 mt-0.5"></i><span>+95 9 757 889806</span></li>
                    <li class="flex items-start gap-2"><i class="fas fa-map-marker-alt text-purple-500 mt-0.5"></i><span>Myanmar, Yangon 11041</span></li>
                </ul>
                <div class="mt-6">
                    <p class="text-gray-500 dark:text-slate-500 text-xs font-medium mb-2">Stay in the loop:</p>
                    <form action="../newsletter.php" method="POST" class="flex gap-2">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? ''; ?>">
                        <input type="email" name="email" placeholder="your@email.com" required class="flex-1 bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg px-3 py-2 text-xs text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 outline-none focus:border-primary transition-colors" />
                        <button type="submit" class="btn-grad text-white text-xs font-bold px-3 py-2 rounded-lg"><i class="fas fa-paper-plane"></i></button>
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
<button id="back-top" class="fixed bottom-6 right-6 w-12 h-12 btn-grad text-white rounded-xl shadow-xl shadow-blue-500/25 hidden items-center justify-center hover:-translate-y-1 transition-all z-50" onclick="window.scrollTo({top:0,behavior:'smooth'})">
    <i class="fas fa-chevron-up text-sm"></i>
</button>
<script>
window.addEventListener('scroll', () => {
    const btn = document.getElementById('back-top');
    if (btn) btn.classList.toggle('hidden', window.scrollY < 400);
    if (btn) btn.classList.toggle('flex', window.scrollY >= 400);
});
</script>
</body>
</html>
