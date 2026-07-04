<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Newsletter – JobHub</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/@flaticon/flaticon-uicons@3.3.1/css/all/all.min.css" rel="stylesheet">
  <script>
    tailwind.config = {
      theme: { extend: {
        fontFamily: { inter: ['Inter', 'sans-serif'] },
        colors: {
          primary: { DEFAULT: '#2563eb', dark: '#1d4ed8', light: '#3b82f6' },
          accent: { DEFAULT: '#0ea5e9', dark: '#0284c7' },
        },
        animation: { 'float': 'float 3s ease-in-out infinite' },
        keyframes: { float: { '0%,100%': { transform: 'translateY(0)' }, '50%': { transform: 'translateY(-10px)' } } }
      }}
    }
  </script>
  <style>
    * { font-family: 'Inter', sans-serif; }
    body { background: #f8fafc; color: #1e293b; }
    .grad-text { background: linear-gradient(135deg, #2563eb 0%, #0ea5e9 60%, #6366f1 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
    .btn-grad { background: linear-gradient(135deg, #2563eb, #0ea5e9); transition: opacity .25s, transform .2s; }
    .btn-grad:hover { opacity: .88; transform: translateY(-2px); }
    .nav-link { position: relative; }
    .nav-link::after { content: ''; position: absolute; bottom: -2px; left: 0; width: 0; height: 2px; background: linear-gradient(90deg, #2563eb, #0ea5e9); transition: width .3s ease; }
    .nav-link:hover::after { width: 100%; }
    .orb { position: absolute; border-radius: 50%; filter: blur(80px); opacity: .15; animation: float 6s ease-in-out infinite; }
    #progress { position: fixed; top: 0; left: 0; height: 3px; background: linear-gradient(90deg, #2563eb, #0ea5e9, #6366f1); z-index: 9999; transition: width .1s; }
    #mobile-menu { transition: max-height .35s ease, opacity .3s ease; max-height: 0; opacity: 0; overflow: hidden; }
    #mobile-menu.open { max-height: 600px; opacity: 1; }
    .reveal { opacity: 0; transform: translateY(30px); transition: opacity .7s ease, transform .7s ease; }
    .reveal.visible { opacity: 1; transform: translateY(0); }
    #navbar.scrolled { background: rgba(255,255,255,.95); backdrop-filter: blur(16px); box-shadow: 0 2px 20px rgba(0,0,0,.08); }
    .pref-check:checked { background: linear-gradient(135deg, #2563eb, #0ea5e9); border-color: #2563eb; }
  </style>
</head>
<body>
  <div id="progress"></div>

  <!-- NAVBAR -->
  <nav id="navbar" class="fixed top-0 inset-x-0 z-50 transition-all duration-300 py-3 bg-white/80 backdrop-blur-md">
    <div class="w-full mx-auto px-4 sm:px-6 flex items-center justify-between">
      <a href="index.php" class="flex items-center gap-2 group">
        <span class="w-9 h-9 rounded-xl btn-grad flex items-center justify-center shadow-lg shadow-blue-500/25"><i class="fi fi-brands-artstation text-white text-sm"></i></span>
        <span class="text-xl font-extrabold tracking-tight"><span class="text-gray-900">Job</span><span class="grad-text">Hub</span></span>
      </a>
      <div class="hidden lg:flex items-center gap-6 text-sm font-medium text-gray-500">
        <a href="index.php" class="nav-link hover:text-gray-900 transition-colors">Home</a>
        <a href="index.php#jobs" class="nav-link hover:text-gray-900 transition-colors">Find Work</a>
        <a href="index.php#freelancers" class="nav-link hover:text-gray-900 transition-colors">Find Freelancers</a>
        <a href="pricing.php" class="nav-link hover:text-gray-900 transition-colors">Pricing</a>
        <a href="contact.php" class="nav-link hover:text-gray-900 transition-colors">Contact</a>
      </div>
      <div class="hidden lg:flex items-center gap-3">
        <a href="auth/login.php" class="text-sm font-semibold text-gray-600 hover:text-gray-900 border border-gray-200 hover:border-primary px-4 py-2 rounded-lg transition-all">Log In</a>
        <a href="auth/register.php" class="btn-grad text-sm font-semibold text-white px-5 py-2 rounded-lg shadow-lg shadow-blue-500/25">Sign Up</a>
      </div>
      <button id="hamburger" class="lg:hidden text-gray-600 hover:text-gray-900 p-2"><i class="fas fa-bars text-xl"></i></button>
    </div>
    <div id="mobile-menu" class="lg:hidden bg-white mx-4 mt-2 rounded-2xl shadow-xl border border-gray-100">
      <div class="flex flex-col gap-1 p-4 text-sm font-medium text-gray-600">
        <a href="index.php" class="hover:text-gray-900 py-2 px-3 rounded-lg hover:bg-gray-50">Home</a>
        <a href="index.php#jobs" class="hover:text-gray-900 py-2 px-3 rounded-lg hover:bg-gray-50">Find Work</a>
        <a href="pricing.php" class="hover:text-gray-900 py-2 px-3 rounded-lg hover:bg-gray-50">Pricing</a>
        <a href="contact.php" class="hover:text-gray-900 py-2 px-3 rounded-lg hover:bg-gray-50">Contact</a>
        <hr class="border-gray-100 my-1" />
        <a href="auth/login.php" class="hover:text-gray-900 py-2 px-3 rounded-lg hover:bg-gray-50">Log In</a>
        <a href="auth/register.php" class="btn-grad text-white text-center py-2 px-3 rounded-lg mt-1">Sign Up</a>
      </div>
    </div>
  </nav>

  <!-- HERO -->
  <section class="relative pt-32 pb-20 overflow-hidden bg-gradient-to-br from-blue-50 via-white to-cyan-50">
    <div class="orb w-[500px] h-[500px] bg-blue-400 top-0 -right-40"></div>
    <div class="orb w-[400px] h-[400px] bg-cyan-300 bottom-0 -left-32" style="animation-delay:2s"></div>
    <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 text-center">
      <span class="text-xs font-bold uppercase tracking-widest text-blue-600/70 bg-blue-50 px-3 py-1 rounded-full">Newsletter</span>
      <h1 class="text-4xl sm:text-5xl font-black mt-4 mb-4 text-gray-900">Stay <span class="grad-text">Updated</span></h1>
      <p class="text-gray-500 max-w-2xl mx-auto">Get the latest insights, tips, and opportunities delivered straight to your inbox. Join 10,000+ freelancers and clients who never miss an update.</p>
    </div>
  </section>

  <!-- SUBSCRIPTION FORM -->
  <section class="py-20 px-4 reveal">
    <div class="max-w-5xl mx-auto">
      <div class="grid lg:grid-cols-2 gap-12 items-center">

        <!-- Form -->
        <div class="bg-white rounded-3xl p-8 sm:p-10 border border-gray-100 shadow-sm">
          <h2 class="text-2xl font-bold text-gray-900 mb-2">Subscribe to Our Newsletter</h2>
          <p class="text-gray-400 text-sm mb-8">Choose what content you'd like to receive. You can update your preferences at any time.</p>
          <form action="actions/newsletter_subscribe.php" method="POST" class="space-y-6">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
              <input type="email" name="email" required placeholder="your@email.com" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-gray-900 placeholder-gray-400 outline-none focus:border-primary focus:bg-white transition-all text-sm" />
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-3">Newsletter Preferences</label>
              <div class="space-y-3">
                <label class="flex items-center gap-3 cursor-pointer">
                  <input type="checkbox" name="pref[]" value="weekly" class="pref-check w-5 h-5 rounded-lg border-gray-300 text-primary focus:ring-primary cursor-pointer" checked />
                  <div>
                    <p class="text-sm font-semibold text-gray-900">Weekly Digest</p>
                    <p class="text-xs text-gray-400">Top jobs, tips, and platform updates</p>
                  </div>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                  <input type="checkbox" name="pref[]" value="jobs" class="pref-check w-5 h-5 rounded-lg border-gray-300 text-primary focus:ring-primary cursor-pointer" />
                  <div>
                    <p class="text-sm font-semibold text-gray-900">New Job Alerts</p>
                    <p class="text-xs text-gray-400">Get notified about matching job opportunities</p>
                  </div>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                  <input type="checkbox" name="pref[]" value="industry" class="pref-check w-5 h-5 rounded-lg border-gray-300 text-primary focus:ring-primary cursor-pointer" />
                  <div>
                    <p class="text-sm font-semibold text-gray-900">Industry News</p>
                    <p class="text-xs text-gray-400">Latest trends and insights in freelancing</p>
                  </div>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                  <input type="checkbox" name="pref[]" value="tips" class="pref-check w-5 h-5 rounded-lg border-gray-300 text-primary focus:ring-primary cursor-pointer" />
                  <div>
                    <p class="text-sm font-semibold text-gray-900">Tips & Guides</p>
                    <p class="text-xs text-gray-400">Expert advice on growing your freelance career</p>
                  </div>
                </label>
              </div>
            </div>
            <button type="submit" class="btn-grad text-white font-semibold px-8 py-3 rounded-xl shadow-lg shadow-blue-500/25 w-full">Subscribe Now <i class="fas fa-paper-plane ml-2"></i></button>
            <p class="text-gray-400 text-xs text-center">No spam, ever. Unsubscribe anytime.</p>
          </form>
        </div>

        <!-- Benefits -->
        <div>
          <h3 class="text-2xl font-bold text-gray-900 mb-8">Why Subscribe?</h3>
          <div class="space-y-6">
            <div class="flex items-start gap-4">
              <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0"><i class="fas fa-briefcase text-blue-600"></i></div>
              <div>
                <h4 class="font-bold text-gray-900 text-sm mb-1">Exclusive Job Opportunities</h4>
                <p class="text-gray-400 text-sm">Be the first to know about premium job listings before they go public.</p>
              </div>
            </div>
            <div class="flex items-start gap-4">
              <div class="w-12 h-12 rounded-xl bg-cyan-50 flex items-center justify-center flex-shrink-0"><i class="fas fa-chart-line text-cyan-600"></i></div>
              <div>
                <h4 class="font-bold text-gray-900 text-sm mb-1">Market Insights</h4>
                <p class="text-gray-400 text-sm">Salary benchmarks, skill demand reports, and industry trend analysis.</p>
              </div>
            </div>
            <div class="flex items-start gap-4">
              <div class="w-12 h-12 rounded-xl bg-purple-50 flex items-center justify-center flex-shrink-0"><i class="fas fa-book-open text-purple-600"></i></div>
              <div>
                <h4 class="font-bold text-gray-900 text-sm mb-1">Expert Guides</h4>
                <p class="text-gray-400 text-sm">In-depth tutorials on pricing, proposals, client management, and more.</p>
              </div>
            </div>
            <div class="flex items-start gap-4">
              <div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center flex-shrink-0"><i class="fas fa-gift text-green-600"></i></div>
              <div>
                <h4 class="font-bold text-gray-900 text-sm mb-1">Subscriber-Only Perks</h4>
                <p class="text-gray-400 text-sm">Early access to new features, discounts, and special offers.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- RECENT PREVIEWS -->
  <section class="py-20 px-4 reveal bg-gray-50">
    <div class="max-w-7xl mx-auto">
      <div class="text-center mb-14">
        <span class="text-xs font-bold uppercase tracking-widest text-purple-600/70 bg-purple-50 px-3 py-1 rounded-full">Recent Issues</span>
        <h2 class="text-4xl font-extrabold mt-4 mb-3 text-gray-900">What You've <span class="grad-text">Been Missing</span></h2>
        <p class="text-gray-500 max-w-xl mx-auto">A sneak peek at what our subscribers receive every week.</p>
      </div>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">

        <!-- Issue 1 -->
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm hover:-translate-y-1 transition-all">
          <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center mb-4"><i class="fas fa-calendar text-blue-600"></i></div>
          <p class="text-xs text-gray-400 mb-2">June 20, 2026</p>
          <h3 class="font-bold text-gray-900 text-base mb-2">How to Price Your Freelance Services in 2026</h3>
          <p class="text-gray-500 text-sm leading-relaxed mb-4">The ultimate guide to setting competitive rates, negotiating with clients, and maximizing your earnings this year.</p>
          <span class="text-xs font-semibold text-blue-600 bg-blue-50 px-3 py-1 rounded-full">Pricing Strategy</span>
        </div>

        <!-- Issue 2 -->
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm hover:-translate-y-1 transition-all">
          <div class="w-12 h-12 rounded-xl bg-cyan-50 flex items-center justify-center mb-4"><i class="fas fa-robot text-cyan-600"></i></div>
          <p class="text-xs text-gray-400 mb-2">June 13, 2026</p>
          <h3 class="font-bold text-gray-900 text-base mb-2">AI Tools Every Freelancer Should Know About</h3>
          <p class="text-gray-500 text-sm leading-relaxed mb-4">From content generation to project management — the top AI tools boosting freelancer productivity right now.</p>
          <span class="text-xs font-semibold text-cyan-600 bg-cyan-50 px-3 py-1 rounded-full">Technology</span>
        </div>

        <!-- Issue 3 -->
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm hover:-translate-y-1 transition-all">
          <div class="w-12 h-12 rounded-xl bg-purple-50 flex items-center justify-center mb-4"><i class="fas fa-trophy text-purple-600"></i></div>
          <p class="text-xs text-gray-400 mb-2">June 6, 2026</p>
          <h3 class="font-bold text-gray-900 text-base mb-2">Top 10 Highest-Paying Freelance Skills This Quarter</h3>
          <p class="text-gray-500 text-sm leading-relaxed mb-4">Data-driven analysis of which skills command the highest rates on JobHub right now.</p>
          <span class="text-xs font-semibold text-purple-600 bg-purple-50 px-3 py-1 rounded-full">Career Growth</span>
        </div>

        <!-- Issue 4 -->
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm hover:-translate-y-1 transition-all">
          <div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center mb-4"><i class="fas fa-handshake text-green-600"></i></div>
          <p class="text-xs text-gray-400 mb-2">May 30, 2026</p>
          <h3 class="font-bold text-gray-900 text-base mb-2">Writing Proposals That Win: A Complete Template</h3>
          <p class="text-gray-500 text-sm leading-relaxed mb-4">Proven proposal templates and strategies that have helped freelancers win 3x more contracts.</p>
          <span class="text-xs font-semibold text-green-600 bg-green-50 px-3 py-1 rounded-full">Tips & Guides</span>
        </div>

        <!-- Issue 5 -->
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm hover:-translate-y-1 transition-all">
          <div class="w-12 h-12 rounded-xl bg-yellow-50 flex items-center justify-center mb-4"><i class="fas fa-shield-alt text-yellow-600"></i></div>
          <p class="text-xs text-gray-400 mb-2">May 23, 2026</p>
          <h3 class="font-bold text-gray-900 text-base mb-2">How JobHub's Escrow System Protects You</h3>
          <p class="text-gray-500 text-sm leading-relaxed mb-4">A deep dive into our payment protection system and how it keeps every transaction safe and fair.</p>
          <span class="text-xs font-semibold text-yellow-600 bg-yellow-50 px-3 py-1 rounded-full">Platform Update</span>
        </div>

        <!-- Issue 6 -->
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm hover:-translate-y-1 transition-all">
          <div class="w-12 h-12 rounded-xl bg-pink-50 flex items-center justify-center mb-4"><i class="fas fa-users text-pink-600"></i></div>
          <p class="text-xs text-gray-400 mb-2">May 16, 2026</p>
          <h3 class="font-bold text-gray-900 text-base mb-2">Freelancer Spotlight: Stories of Success</h3>
          <p class="text-gray-500 text-sm leading-relaxed mb-4">Inspiring stories from freelancers who grew their income by 200% using JobHub in just 6 months.</p>
          <span class="text-xs font-semibold text-pink-600 bg-pink-50 px-3 py-1 rounded-full">Community</span>
        </div>

      </div>
    </div>
  </section>

  <!-- FOOTER CTA -->
  <section class="py-20 px-4 reveal">
    <div class="max-w-4xl mx-auto">
      <div class="bg-gradient-to-br from-blue-600 to-cyan-500 rounded-3xl p-10 sm:p-16 text-center relative overflow-hidden">
        <div class="absolute inset-0 opacity-10" style="background:radial-gradient(ellipse at 20% 50%,#fff 0%,transparent 60%),radial-gradient(ellipse at 80% 50%,#fff 0%,transparent 60%)"></div>
        <div class="relative">
          <h2 class="text-3xl sm:text-4xl font-black text-white mb-4">Don't Miss Out</h2>
          <p class="text-blue-100 max-w-xl mx-auto mb-8">Join 10,000+ subscribers and get the best freelance insights delivered every week.</p>
          <div class="flex flex-col sm:flex-row gap-4 justify-center max-w-xl mx-auto">
            <input type="email" placeholder="Enter your email" class="flex-1 px-6 py-4 rounded-full bg-white/10 backdrop-blur-md text-white placeholder-blue-200 border border-white/30 outline-none focus:bg-white/20 transition-all text-sm" />
            <button class="bg-white text-blue-600 font-semibold px-8 py-4 rounded-full hover:bg-blue-50 transition-all flex-shrink-0">Subscribe</button>
          </div>
          <div class="flex items-center justify-center gap-6 mt-8">
            <div class="flex items-center gap-2 text-blue-100 text-xs"><i class="fab fa-twitter"></i><span>@JobHub</span></div>
            <div class="flex items-center gap-2 text-blue-100 text-xs"><i class="fab fa-linkedin-in"></i><span>JobHub</span></div>
            <div class="flex items-center gap-2 text-blue-100 text-xs"><i class="fab fa-instagram"></i><span>@jobhub</span></div>
            <div class="flex items-center gap-2 text-blue-100 text-xs"><i class="fab fa-youtube"></i><span>JobHub</span></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- FOOTER -->
  <footer id="footer" class="border-t border-gray-200 bg-white pt-16 pb-8 px-4">
    <div class="max-w-7xl mx-auto">
      <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-10 mb-14">
        <div class="lg:col-span-2">
          <a href="index.php" class="flex items-center gap-2 mb-5">
            <div class="w-9 h-9 rounded-xl btn-grad flex items-center justify-center shadow-lg shadow-blue-500/25"><i class="fi fi-brands-artstation text-white text-sm"></i></div>
            <span class="text-xl font-extrabold"><span class="text-gray-900">Job</span><span class="grad-text">Hub</span></span>
          </a>
          <p class="text-gray-400 text-sm leading-relaxed mb-6 max-w-xs">The world's most trusted marketplace for top freelancers and innovative clients. Work smarter, together.</p>
          <div class="flex gap-3">
            <a href="#" class="w-9 h-9 bg-gray-50 rounded-lg flex items-center justify-center text-gray-400 hover:text-blue-500 hover:bg-blue-50 transition-all border border-gray-100"><i class="fab fa-twitter text-sm"></i></a>
            <a href="#" class="w-9 h-9 bg-gray-50 rounded-lg flex items-center justify-center text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-all border border-gray-100"><i class="fab fa-linkedin-in text-sm"></i></a>
            <a href="#" class="w-9 h-9 bg-gray-50 rounded-lg flex items-center justify-center text-gray-400 hover:text-pink-500 hover:bg-pink-50 transition-all border border-gray-100"><i class="fab fa-instagram text-sm"></i></a>
            <a href="#" class="w-9 h-9 bg-gray-50 rounded-lg flex items-center justify-center text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-all border border-gray-100"><i class="fab fa-github text-sm"></i></a>
            <a href="#" class="w-9 h-9 bg-gray-50 rounded-lg flex items-center justify-center text-gray-400 hover:text-red-500 hover:bg-red-50 transition-all border border-gray-100"><i class="fab fa-youtube text-sm"></i></a>
          </div>
        </div>
        <div>
          <h4 class="text-gray-900 font-bold text-sm uppercase tracking-widest mb-5">Company</h4>
          <ul class="space-y-3 text-gray-400 text-sm">
            <li><a href="index.php#about" class="hover:text-gray-900 transition-colors">About Us</a></li>
            <li><a href="careers.php" class="hover:text-gray-900 transition-colors">Careers</a></li>
            <li><a href="pricing.php" class="hover:text-gray-900 transition-colors">Pricing</a></li>
            <li><a href="newsletter.php" class="hover:text-gray-900 transition-colors">Newsletter</a></li>
          </ul>
        </div>
        <div>
          <h4 class="text-gray-900 font-bold text-sm uppercase tracking-widest mb-5">Support</h4>
          <ul class="space-y-3 text-gray-400 text-sm">
            <li><a href="faq.php" class="hover:text-gray-900 transition-colors">FAQ</a></li>
            <li><a href="contact.php" class="hover:text-gray-900 transition-colors">Contact Us</a></li>
            <li><a href="privacy.php" class="hover:text-gray-900 transition-colors">Privacy Policy</a></li>
            <li><a href="terms.php" class="hover:text-gray-900 transition-colors">Terms & Conditions</a></li>
          </ul>
        </div>
        <div>
          <h4 class="text-gray-900 font-bold text-sm uppercase tracking-widest mb-5">Contact</h4>
          <ul class="space-y-3 text-gray-400 text-sm">
            <li class="flex items-start gap-2"><i class="fas fa-envelope text-blue-500 mt-0.5"></i><a href="mailto:hello@jobhub.io" class="hover:text-gray-900 transition-colors">hello@jobhub.io</a></li>
            <li class="flex items-start gap-2"><i class="fas fa-phone text-cyan-500 mt-0.5"></i><span>+95 9 757 889806</span></li>
            <li class="flex items-start gap-2"><i class="fas fa-map-marker-alt text-purple-500 mt-0.5"></i><span>Myanmar, Yangon 11041</span></li>
          </ul>
        </div>
      </div>
      <div class="border-t border-gray-100 pt-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-gray-400 text-xs">
        <p>&copy; 2026 JobHub. All rights reserved.</p>
        <div class="flex gap-4">
          <a href="privacy.php" class="hover:text-gray-600 transition-colors">Privacy</a>
          <a href="terms.php" class="hover:text-gray-600 transition-colors">Terms</a>
          <a href="faq.php" class="hover:text-gray-600 transition-colors">FAQ</a>
        </div>
      </div>
    </div>
  </footer>

  <button id="back-top" class="fixed bottom-6 right-6 w-12 h-12 btn-grad text-white rounded-xl shadow-xl shadow-blue-500/25 hidden items-center justify-center hover:-translate-y-1 transition-all z-50" onclick="window.scrollTo({top:0,behavior:'smooth'})"><i class="fas fa-chevron-up text-sm"></i></button>

  <script>
    const progress = document.getElementById('progress');
    const navbar = document.getElementById('navbar');
    const backTop = document.getElementById('back-top');
    window.addEventListener('scroll', () => {
      const scrolled = (window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100;
      progress.style.width = scrolled + '%';
      navbar.classList.toggle('scrolled', window.scrollY > 50);
      backTop.classList.toggle('hidden', window.scrollY < 400);
      backTop.classList.toggle('flex', window.scrollY >= 400);
      checkReveal();
    });
    document.getElementById('hamburger').addEventListener('click', () => { document.getElementById('mobile-menu').classList.toggle('open'); });
    document.querySelectorAll('#mobile-menu a').forEach(a => { a.addEventListener('click', () => document.getElementById('mobile-menu').classList.remove('open')); });
    const reveals = document.querySelectorAll('.reveal');
    function checkReveal() { reveals.forEach(el => { if (el.getBoundingClientRect().top < window.innerHeight - 80) el.classList.add('visible'); }); }
    checkReveal();
  </script>
</body>
</html>
