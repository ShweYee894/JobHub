<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Pricing – FreelanceHub</title>
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
    .faq-answer { max-height: 0; overflow: hidden; transition: max-height .35s ease; }
    .faq-answer.open { max-height: 300px; }
    .faq-item.active .faq-icon { transform: rotate(180deg); }
    .faq-icon { transition: transform .3s ease; }
  </style>
</head>
<body>
  <div id="progress"></div>

  <!-- NAVBAR -->
  <nav id="navbar" class="fixed top-0 inset-x-0 z-50 transition-all duration-300 py-3 bg-white/80 backdrop-blur-md">
    <div class="w-full mx-auto px-4 sm:px-6 flex items-center justify-between">
      <a href="index.php" class="flex items-center gap-2 group">
        <span class="w-9 h-9 rounded-xl btn-grad flex items-center justify-center shadow-lg shadow-blue-500/25"><i class="fi fi-brands-artstation text-white text-sm"></i></span>
        <span class="text-xl font-extrabold tracking-tight"><span class="text-gray-900">Freelance</span><span class="grad-text">Hub</span></span>
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
      <span class="text-xs font-bold uppercase tracking-widest text-cyan-600/70 bg-cyan-50 px-3 py-1 rounded-full">Pricing</span>
      <h1 class="text-4xl sm:text-5xl font-black mt-4 mb-4 text-gray-900">Simple, Transparent <span class="grad-text">Pricing</span></h1>
      <p class="text-gray-500 max-w-2xl mx-auto">No hidden fees. No surprises. Post jobs for free and only pay when you hire. Freelancers pay a small service fee on completed projects.</p>
    </div>
  </section>

  <!-- PLATFORM FEES -->
  <section class="py-20 px-4 reveal">
    <div class="max-w-7xl mx-auto">
      <div class="text-center mb-14">
        <span class="text-xs font-bold uppercase tracking-widest text-green-600/70 bg-green-50 px-3 py-1 rounded-full">How It Works</span>
        <h2 class="text-4xl font-extrabold mt-4 mb-3 text-gray-900">Platform <span class="grad-text">Fees</span></h2>
        <p class="text-gray-500 max-w-xl mx-auto">We keep our pricing simple so you can focus on what matters — building great things.</p>
      </div>
      <div class="grid sm:grid-cols-2 gap-8 max-w-4xl mx-auto">
        <div class="bg-white rounded-2xl p-8 border border-gray-100 shadow-sm">
          <div class="w-14 h-14 rounded-2xl bg-blue-50 flex items-center justify-center mb-6"><i class="fas fa-user-tie text-blue-600 text-xl"></i></div>
          <h3 class="text-xl font-bold text-gray-900 mb-3">For Clients</h3>
          <ul class="space-y-3 text-sm text-gray-500">
            <li class="flex items-start gap-2"><i class="fas fa-check text-green-500 mt-1"></i> Free to post unlimited job listings</li>
            <li class="flex items-start gap-2"><i class="fas fa-check text-green-500 mt-1"></i> Free to browse freelancer profiles</li>
            <li class="flex items-start gap-2"><i class="fas fa-check text-green-500 mt-1"></i> Free to receive proposals and chat</li>
            <li class="flex items-start gap-2"><i class="fas fa-check text-green-500 mt-1"></i> 2.5% payment processing fee per transaction</li>
          </ul>
        </div>
        <div class="bg-white rounded-2xl p-8 border border-gray-100 shadow-sm">
          <div class="w-14 h-14 rounded-2xl bg-cyan-50 flex items-center justify-center mb-6"><i class="fas fa-laptop-code text-cyan-600 text-xl"></i></div>
          <h3 class="text-xl font-bold text-gray-900 mb-3">For Freelancers</h3>
          <ul class="space-y-3 text-sm text-gray-500">
            <li class="flex items-start gap-2"><i class="fas fa-check text-green-500 mt-1"></i> Free to create profile and portfolio</li>
            <li class="flex items-start gap-2"><i class="fas fa-check text-green-500 mt-1"></i> Free to browse and apply to jobs</li>
            <li class="flex items-start gap-2"><i class="fas fa-check text-green-500 mt-1"></i> Free to use chat and collaboration tools</li>
            <li class="flex items-start gap-2"><i class="fas fa-check text-green-500 mt-1"></i> 10% service fee on earnings (5% for Pro)</li>
          </ul>
        </div>
      </div>
    </div>
  </section>

  <!-- CLIENT PRICING -->
  <section class="py-20 px-4 reveal bg-gray-50">
    <div class="max-w-7xl mx-auto">
      <div class="text-center mb-14">
        <span class="text-xs font-bold uppercase tracking-widest text-blue-600/70 bg-blue-50 px-3 py-1 rounded-full">Client Plans</span>
        <h2 class="text-4xl font-extrabold mt-4 mb-3 text-gray-900">Choose Your <span class="grad-text">Plan</span></h2>
      </div>
      <div class="grid sm:grid-cols-3 gap-6 max-w-5xl mx-auto">
        <!-- Starter -->
        <div class="bg-white rounded-2xl p-8 border border-gray-100 text-center hover:border-primary transition-all hover:-translate-y-1 duration-300 shadow-sm">
          <p class="text-xs font-bold uppercase tracking-widest text-gray-400 mb-3">Starter</p>
          <p class="text-5xl font-black text-gray-900 mb-1">$0<span class="text-lg text-gray-400 font-normal">/mo</span></p>
          <p class="text-gray-400 text-sm mb-6">For casual clients</p>
          <ul class="space-y-3 text-sm text-gray-500 text-left mb-8">
            <li class="flex items-center gap-2"><i class="fas fa-check text-green-500 text-xs"></i> 3 active job posts</li>
            <li class="flex items-center gap-2"><i class="fas fa-check text-green-500 text-xs"></i> 2.5% processing fee</li>
            <li class="flex items-center gap-2"><i class="fas fa-check text-green-500 text-xs"></i> Basic support</li>
            <li class="flex items-center gap-2"><i class="fas fa-times text-gray-300 text-xs"></i> <span class="text-gray-300">AI matching</span></li>
          </ul>
          <a href="auth/register.php" class="block border border-gray-200 hover:border-primary text-gray-700 font-semibold py-3 rounded-xl transition-all">Get Started</a>
        </div>
        <!-- Pro -->
        <div class="bg-white rounded-2xl p-8 border-2 border-blue-600 text-center relative -translate-y-3 shadow-xl shadow-blue-500/15">
          <div class="absolute -top-4 left-1/2 -translate-x-1/2 btn-grad text-white text-xs font-black px-4 py-1 rounded-full">Most Popular</div>
          <p class="text-xs font-bold uppercase tracking-widest text-blue-600 mb-3">Pro</p>
          <p class="text-5xl font-black text-gray-900 mb-1">$29<span class="text-lg text-gray-400 font-normal">/mo</span></p>
          <p class="text-gray-400 text-sm mb-6">For growing teams</p>
          <ul class="space-y-3 text-sm text-gray-500 text-left mb-8">
            <li class="flex items-center gap-2"><i class="fas fa-check text-green-500 text-xs"></i> Unlimited job posts</li>
            <li class="flex items-center gap-2"><i class="fas fa-check text-green-500 text-xs"></i> 2% processing fee</li>
            <li class="flex items-center gap-2"><i class="fas fa-check text-green-500 text-xs"></i> Priority support</li>
            <li class="flex items-center gap-2"><i class="fas fa-check text-green-500 text-xs"></i> AI job matching</li>
          </ul>
          <a href="auth/register.php?plan=pro" class="block btn-grad text-white font-semibold py-3 rounded-xl">Go Pro</a>
        </div>
        <!-- Enterprise -->
        <div class="bg-white rounded-2xl p-8 border border-gray-100 text-center hover:border-purple-400 transition-all hover:-translate-y-1 duration-300 shadow-sm">
          <p class="text-xs font-bold uppercase tracking-widest text-gray-400 mb-3">Enterprise</p>
          <p class="text-5xl font-black text-gray-900 mb-1">$99<span class="text-lg text-gray-400 font-normal">/mo</span></p>
          <p class="text-gray-400 text-sm mb-6">For large organisations</p>
          <ul class="space-y-3 text-sm text-gray-500 text-left mb-8">
            <li class="flex items-center gap-2"><i class="fas fa-check text-green-500 text-xs"></i> Unlimited everything</li>
            <li class="flex items-center gap-2"><i class="fas fa-check text-green-500 text-xs"></i> 1.5% processing fee</li>
            <li class="flex items-center gap-2"><i class="fas fa-check text-green-500 text-xs"></i> Dedicated manager</li>
            <li class="flex items-center gap-2"><i class="fas fa-check text-green-500 text-xs"></i> Custom contracts & NDA</li>
          </ul>
          <a href="contact.php" class="block border border-purple-200 hover:bg-purple-50 text-gray-700 font-semibold py-3 rounded-xl transition-all">Contact Sales</a>
        </div>
      </div>
    </div>
  </section>

  <!-- FREELANCER PRICING -->
  <section class="py-20 px-4 reveal">
    <div class="max-w-4xl mx-auto">
      <div class="text-center mb-14">
        <span class="text-xs font-bold uppercase tracking-widest text-cyan-600/70 bg-cyan-50 px-3 py-1 rounded-full">Freelancer Plans</span>
        <h2 class="text-4xl font-extrabold mt-4 mb-3 text-gray-900">Keep More of <span class="grad-text">What You Earn</span></h2>
      </div>
      <div class="grid sm:grid-cols-2 gap-6">
        <!-- Free -->
        <div class="bg-white rounded-2xl p-8 border border-gray-100 shadow-sm">
          <p class="text-xs font-bold uppercase tracking-widest text-gray-400 mb-3">Free Account</p>
          <p class="text-5xl font-black text-gray-900 mb-1">10%</p>
          <p class="text-gray-400 text-sm mb-6">Service fee on earnings</p>
          <ul class="space-y-3 text-sm text-gray-500 mb-8">
            <li class="flex items-center gap-2"><i class="fas fa-check text-green-500 text-xs"></i> Create profile & portfolio</li>
            <li class="flex items-center gap-2"><i class="fas fa-check text-green-500 text-xs"></i> Apply to unlimited jobs</li>
            <li class="flex items-center gap-2"><i class="fas fa-check text-green-500 text-xs"></i> Chat with clients</li>
            <li class="flex items-center gap-2"><i class="fas fa-check text-green-500 text-xs"></i> Basic analytics</li>
          </ul>
          <a href="auth/register.php?role=freelancer" class="block border border-gray-200 hover:border-primary text-gray-700 font-semibold py-3 rounded-xl transition-all text-center">Join Free</a>
        </div>
        <!-- Pro -->
        <div class="bg-white rounded-2xl p-8 border-2 border-blue-600 shadow-xl shadow-blue-500/15 relative">
          <div class="absolute -top-4 left-1/2 -translate-x-1/2 btn-grad text-white text-xs font-black px-4 py-1 rounded-full">Save 50%</div>
          <p class="text-xs font-bold uppercase tracking-widest text-blue-600 mb-3">Pro Account</p>
          <p class="text-5xl font-black text-gray-900 mb-1">5%</p>
          <p class="text-gray-400 text-sm mb-6">Service fee on earnings</p>
          <ul class="space-y-3 text-sm text-gray-500 mb-8">
            <li class="flex items-center gap-2"><i class="fas fa-check text-green-500 text-xs"></i> Everything in Free</li>
            <li class="flex items-center gap-2"><i class="fas fa-check text-green-500 text-xs"></i> Priority in search results</li>
            <li class="flex items-center gap-2"><i class="fas fa-check text-green-500 text-xs"></i> Advanced analytics</li>
            <li class="flex items-center gap-2"><i class="fas fa-check text-green-500 text-xs"></i> Pro badge on profile</li>
          </ul>
          <a href="auth/register.php?role=freelancer&plan=pro" class="block btn-grad text-white font-semibold py-3 rounded-xl text-center">Go Pro</a>
        </div>
      </div>
    </div>
  </section>

  <!-- COMPARISON TABLE -->
  <section class="py-20 px-4 reveal bg-gray-50">
    <div class="max-w-5xl mx-auto">
      <div class="text-center mb-14">
        <span class="text-xs font-bold uppercase tracking-widest text-primary/70 bg-blue-50 px-3 py-1 rounded-full">Compare</span>
        <h2 class="text-4xl font-extrabold mt-4 mb-3 text-gray-900">Free vs <span class="grad-text">Pro</span></h2>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-100">
                <th class="text-left px-6 py-4 font-bold text-gray-900">Feature</th>
                <th class="text-center px-6 py-4 font-bold text-gray-500">Free</th>
                <th class="text-center px-6 py-4 font-bold text-blue-600">Pro ($29/mo)</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr><td class="px-6 py-4 text-gray-600">Job Posts</td><td class="text-center px-6 py-4 text-gray-500">3</td><td class="text-center px-6 py-4 text-gray-900 font-semibold">Unlimited</td></tr>
              <tr><td class="px-6 py-4 text-gray-600">Platform Fee (Clients)</td><td class="text-center px-6 py-4 text-gray-500">2.5%</td><td class="text-center px-6 py-4 text-gray-900 font-semibold">2%</td></tr>
              <tr><td class="px-6 py-4 text-gray-600">AI Job Matching</td><td class="text-center px-6 py-4"><i class="fas fa-times text-gray-300"></i></td><td class="text-center px-6 py-4"><i class="fas fa-check text-green-500"></i></td></tr>
              <tr><td class="px-6 py-4 text-gray-600">Priority Support</td><td class="text-center px-6 py-4"><i class="fas fa-times text-gray-300"></i></td><td class="text-center px-6 py-4"><i class="fas fa-check text-green-500"></i></td></tr>
              <tr><td class="px-6 py-4 text-gray-600">Advanced Analytics</td><td class="text-center px-6 py-4"><i class="fas fa-times text-gray-300"></i></td><td class="text-center px-6 py-4"><i class="fas fa-check text-green-500"></i></td></tr>
              <tr><td class="px-6 py-4 text-gray-600">Dedicated Account Manager</td><td class="text-center px-6 py-4"><i class="fas fa-times text-gray-300"></i></td><td class="text-center px-6 py-4"><i class="fas fa-times text-gray-300"></i></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>

  <!-- PRICING FAQ -->
  <section class="py-20 px-4 reveal">
    <div class="max-w-3xl mx-auto">
      <div class="text-center mb-14">
        <span class="text-xs font-bold uppercase tracking-widest text-yellow-600/70 bg-yellow-50 px-3 py-1 rounded-full">FAQ</span>
        <h2 class="text-4xl font-extrabold mt-4 mb-3 text-gray-900">Pricing <span class="grad-text">Questions</span></h2>
      </div>
      <div class="space-y-4">
        <div class="faq-item bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <button onclick="toggleFaq(this)" class="w-full flex items-center justify-between px-6 py-5 text-left">
            <span class="font-semibold text-gray-900 text-sm pr-4">Are there any setup fees or hidden charges?</span>
            <i class="fas fa-chevron-down text-gray-400 faq-icon flex-shrink-0"></i>
          </button>
          <div class="faq-answer px-6 pb-5">
            <p class="text-gray-500 text-sm leading-relaxed">No. There are no setup fees, hidden charges, or surprise costs. You only pay the monthly subscription (if you choose Pro) and the processing/service fees outlined above.</p>
          </div>
        </div>
        <div class="faq-item bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <button onclick="toggleFaq(this)" class="w-full flex items-center justify-between px-6 py-5 text-left">
            <span class="font-semibold text-gray-900 text-sm pr-4">Can I upgrade or downgrade my plan at any time?</span>
            <i class="fas fa-chevron-down text-gray-400 faq-icon flex-shrink-0"></i>
          </button>
          <div class="faq-answer px-6 pb-5">
            <p class="text-gray-500 text-sm leading-relaxed">Yes. You can upgrade or downgrade your plan at any time from your account settings. Changes take effect at the start of your next billing cycle.</p>
          </div>
        </div>
        <div class="faq-item bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <button onclick="toggleFaq(this)" class="w-full flex items-center justify-between px-6 py-5 text-left">
            <span class="font-semibold text-gray-900 text-sm pr-4">Do freelancers pay anything to join?</span>
            <i class="fas fa-chevron-down text-gray-400 faq-icon flex-shrink-0"></i>
          </button>
          <div class="faq-answer px-6 pb-5">
            <p class="text-gray-500 text-sm leading-relaxed">No. It's completely free to create a profile, build a portfolio, and apply to jobs. Freelancers only pay a service fee (10% or 5% for Pro) when they receive payment for completed work.</p>
          </div>
        </div>
        <div class="faq-item bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <button onclick="toggleFaq(this)" class="w-full flex items-center justify-between px-6 py-5 text-left">
            <span class="font-semibold text-gray-900 text-sm pr-4">What payment methods do you accept?</span>
            <i class="fas fa-chevron-down text-gray-400 faq-icon flex-shrink-0"></i>
          </button>
          <div class="faq-answer px-6 pb-5">
            <p class="text-gray-500 text-sm leading-relaxed">We accept all major credit/debit cards (Visa, MasterCard, Amex), PayPal, and bank transfers. Enterprise clients can also pay via invoice.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- CTA -->
  <section class="py-20 px-4 reveal">
    <div class="max-w-4xl mx-auto">
      <div class="bg-gradient-to-br from-blue-600 to-cyan-500 rounded-3xl p-10 sm:p-16 text-center relative overflow-hidden">
        <div class="absolute inset-0 opacity-10" style="background:radial-gradient(ellipse at 20% 50%,#fff 0%,transparent 60%),radial-gradient(ellipse at 80% 50%,#fff 0%,transparent 60%)"></div>
        <div class="relative">
          <h2 class="text-3xl sm:text-4xl font-black text-white mb-4">Ready to Get Started?</h2>
          <p class="text-blue-100 max-w-xl mx-auto mb-8">Join thousands of clients and freelancers already using FreelanceHub. It's free to sign up and start.</p>
          <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="auth/register.php?role=client" class="bg-white text-blue-600 font-semibold px-8 py-4 rounded-full hover:bg-blue-50 transition-all inline-flex items-center justify-center gap-2">
              <i class="fas fa-user-tie"></i> I'm a Client
            </a>
            <a href="auth/register.php?role=freelancer" class="bg-white/10 backdrop-blur-md text-white font-semibold px-8 py-4 rounded-full border border-white/30 hover:bg-white/20 transition-all inline-flex items-center justify-center gap-2">
              <i class="fas fa-laptop-code"></i> I'm a Freelancer
            </a>
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
            <span class="text-xl font-extrabold"><span class="text-gray-900">Freelance</span><span class="grad-text">Hub</span></span>
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
            <li class="flex items-start gap-2"><i class="fas fa-envelope text-blue-500 mt-0.5"></i><a href="mailto:hello@freelancehub.io" class="hover:text-gray-900 transition-colors">hello@freelancehub.io</a></li>
            <li class="flex items-start gap-2"><i class="fas fa-phone text-cyan-500 mt-0.5"></i><span>+95 9 757 889806</span></li>
            <li class="flex items-start gap-2"><i class="fas fa-map-marker-alt text-purple-500 mt-0.5"></i><span>Myanmar, Yangon 11041</span></li>
          </ul>
        </div>
      </div>
      <div class="border-t border-gray-100 pt-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-gray-400 text-xs">
        <p>&copy; 2026 FreelanceHub. All rights reserved.</p>
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

    function toggleFaq(btn) {
      const item = btn.closest('.faq-item');
      const answer = item.querySelector('.faq-answer');
      const isOpen = answer.classList.contains('open');
      document.querySelectorAll('.faq-answer.open').forEach(a => { a.classList.remove('open'); });
      document.querySelectorAll('.faq-item.active').forEach(i => { i.classList.remove('active'); });
      if (!isOpen) { answer.classList.add('open'); item.classList.add('active'); }
    }
  </script>
</body>
</html>
