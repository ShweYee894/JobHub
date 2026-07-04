<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Terms of Service – JobHub</title>
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
    .toc-link.active { color: #2563eb; border-color: #2563eb; background: #eff6ff; }
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
  <section class="relative pt-32 pb-16 overflow-hidden bg-gradient-to-br from-blue-50 via-white to-cyan-50">
    <div class="orb w-[500px] h-[500px] bg-blue-400 top-0 -right-40"></div>
    <div class="orb w-[400px] h-[400px] bg-cyan-300 bottom-0 -left-32" style="animation-delay:2s"></div>
    <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 text-center">
      <span class="text-xs font-bold uppercase tracking-widest text-blue-600/70 bg-blue-50 px-3 py-1 rounded-full">Legal</span>
      <h1 class="text-4xl sm:text-5xl font-black mt-4 mb-4 text-gray-900">Terms of <span class="grad-text">Service</span></h1>
      <p class="text-gray-500">Last updated: January 15, 2026</p>
    </div>
  </section>

  <!-- CONTENT -->
  <section class="py-16 px-4 reveal">
    <div class="max-w-7xl mx-auto">
      <div class="grid lg:grid-cols-4 gap-12">

        <!-- Table of Contents -->
        <div class="lg:col-span-1">
          <div class="sticky top-28">
            <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
              <h3 class="font-bold text-gray-900 text-sm mb-4">Table of Contents</h3>
              <nav class="space-y-1">
                <a href="#acceptance" class="toc-link block text-sm text-gray-500 hover:text-primary py-1.5 px-3 rounded-lg transition-all border-l-2 border-transparent">Acceptance of Terms</a>
                <a href="#accounts" class="toc-link block text-sm text-gray-500 hover:text-primary py-1.5 px-3 rounded-lg transition-all border-l-2 border-transparent">User Accounts</a>
                <a href="#rules" class="toc-link block text-sm text-gray-500 hover:text-primary py-1.5 px-3 rounded-lg transition-all border-l-2 border-transparent">Platform Rules</a>
                <a href="#client-obligations" class="toc-link block text-sm text-gray-500 hover:text-primary py-1.5 px-3 rounded-lg transition-all border-l-2 border-transparent">Client Obligations</a>
                <a href="#freelancer-obligations" class="toc-link block text-sm text-gray-500 hover:text-primary py-1.5 px-3 rounded-lg transition-all border-l-2 border-transparent">Freelancer Obligations</a>
                <a href="#payments" class="toc-link block text-sm text-gray-500 hover:text-primary py-1.5 px-3 rounded-lg transition-all border-l-2 border-transparent">Payments & Fees</a>
                <a href="#disputes" class="toc-link block text-sm text-gray-500 hover:text-primary py-1.5 px-3 rounded-lg transition-all border-l-2 border-transparent">Disputes & Resolution</a>
                <a href="#ip" class="toc-link block text-sm text-gray-500 hover:text-primary py-1.5 px-3 rounded-lg transition-all border-l-2 border-transparent">Intellectual Property</a>
                <a href="#liability" class="toc-link block text-sm text-gray-500 hover:text-primary py-1.5 px-3 rounded-lg transition-all border-l-2 border-transparent">Limitation of Liability</a>
                <a href="#termination" class="toc-link block text-sm text-gray-500 hover:text-primary py-1.5 px-3 rounded-lg transition-all border-l-2 border-transparent">Termination</a>
              </nav>
            </div>
          </div>
        </div>

        <!-- Main Content -->
        <div class="lg:col-span-3">
          <div class="bg-white rounded-3xl p-8 sm:p-12 border border-gray-100 shadow-sm max-w-none">
            <p class="text-gray-500 leading-relaxed mb-8">Welcome to JobHub. These Terms of Service ("Terms") govern your access to and use of the JobHub platform, website, and services. By creating an account or using our services, you agree to be bound by these Terms.</p>

            <div id="acceptance" class="mb-10">
              <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center gap-3"><span class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0"><i class="fas fa-handshake text-blue-600 text-sm"></i></span>1. Acceptance of Terms</h2>
              <p class="text-gray-500 leading-relaxed mb-3">By accessing or using JobHub, you acknowledge that you have read, understood, and agree to be bound by these Terms and our <a href="privacy.php" class="text-primary hover:underline">Privacy Policy</a>.</p>
              <p class="text-gray-500 leading-relaxed mb-3">If you are using JobHub on behalf of an organization, you represent that you have the authority to bind that organization to these Terms.</p>
              <p class="text-gray-500 leading-relaxed">You must be at least 18 years old to create an account and use our services.</p>
            </div>

            <div id="accounts" class="mb-10">
              <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center gap-3"><span class="w-8 h-8 rounded-lg bg-cyan-50 flex items-center justify-center flex-shrink-0"><i class="fas fa-user-circle text-cyan-600 text-sm"></i></span>2. User Accounts</h2>
              <ul class="space-y-2 text-gray-500">
                <li class="flex items-start gap-2"><i class="fas fa-check text-green-500 mt-1 flex-shrink-0"></i>You must provide accurate and complete information when creating an account.</li>
                <li class="flex items-start gap-2"><i class="fas fa-check text-green-500 mt-1 flex-shrink-0"></i>You are responsible for maintaining the security of your account credentials.</li>
                <li class="flex items-start gap-2"><i class="fas fa-check text-green-500 mt-1 flex-shrink-0"></i>You must not share your account with others or create multiple accounts.</li>
                <li class="flex items-start gap-2"><i class="fas fa-check text-green-500 mt-1 flex-shrink-0"></i>You must promptly notify us of any unauthorized use of your account.</li>
                <li class="flex items-start gap-2"><i class="fas fa-check text-green-500 mt-1 flex-shrink-0"></i>We reserve the right to suspend or terminate accounts that violate these Terms.</li>
              </ul>
            </div>

            <div id="rules" class="mb-10">
              <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center gap-3"><span class="w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center flex-shrink-0"><i class="fas fa-gavel text-purple-600 text-sm"></i></span>3. Platform Rules</h2>
              <p class="text-gray-500 leading-relaxed mb-3">All users must comply with the following rules:</p>
              <ul class="space-y-2 text-gray-500">
                <li class="flex items-start gap-2"><i class="fas fa-times text-red-500 mt-1 flex-shrink-0"></i>No fraud, misrepresentation, or deceptive practices.</li>
                <li class="flex items-start gap-2"><i class="fas fa-times text-red-500 mt-1 flex-shrink-0"></i>No harassment, discrimination, or abusive behavior toward other users.</li>
                <li class="flex items-start gap-2"><i class="fas fa-times text-red-500 mt-1 flex-shrink-0"></i>No posting of illegal, harmful, or infringing content.</li>
                <li class="flex items-start gap-2"><i class="fas fa-times text-red-500 mt-1 flex-shrink-0"></i>No attempts to circumvent platform fees by conducting transactions outside the platform.</li>
                <li class="flex items-start gap-2"><i class="fas fa-times text-red-500 mt-1 flex-shrink-0"></i>No spamming, phishing, or distributing malware.</li>
                <li class="flex items-start gap-2"><i class="fas fa-times text-red-500 mt-1 flex-shrink-0"></i>No unauthorized access to other users' accounts or platform systems.</li>
              </ul>
            </div>

            <div id="client-obligations" class="mb-10">
              <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center gap-3"><span class="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center flex-shrink-0"><i class="fas fa-user-tie text-green-600 text-sm"></i></span>4. Client Obligations</h2>
              <ul class="space-y-2 text-gray-500">
                <li class="flex items-start gap-2"><i class="fas fa-arrow-right text-primary mt-1 flex-shrink-0"></i>Provide clear and accurate project descriptions when posting jobs.</li>
                <li class="flex items-start gap-2"><i class="fas fa-arrow-right text-primary mt-1 flex-shrink-0"></i>Fund escrow before work begins on milestone-based projects.</li>
                <li class="flex items-start gap-2"><i class="fas fa-arrow-right text-primary mt-1 flex-shrink-0"></i>Review and approve (or request revisions of) completed work in a timely manner.</li>
                <li class="flex items-start gap-2"><i class="fas fa-arrow-right text-primary mt-1 flex-shrink-0"></i>Pay for services as agreed upon in the contract.</li>
                <li class="flex items-start gap-2"><i class="fas fa-arrow-right text-primary mt-1 flex-shrink-0"></i>Treat freelancers with respect and professionalism.</li>
              </ul>
            </div>

            <div id="freelancer-obligations" class="mb-10">
              <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center gap-3"><span class="w-8 h-8 rounded-lg bg-violet-50 flex items-center justify-center flex-shrink-0"><i class="fas fa-laptop-code text-violet-600 text-sm"></i></span>5. Freelancer Obligations</h2>
              <ul class="space-y-2 text-gray-500">
                <li class="flex items-start gap-2"><i class="fas fa-arrow-right text-primary mt-1 flex-shrink-0"></i>Deliver work of professional quality that meets the agreed requirements.</li>
                <li class="flex items-start gap-2"><i class="fas fa-arrow-right text-primary mt-1 flex-shrink-0"></i>Meet agreed deadlines or communicate delays proactively.</li>
                <li class="flex items-start gap-2"><i class="fas fa-arrow-right text-primary mt-1 flex-shrink-0"></i>Maintain accurate profile information and portfolio.</li>
                <li class="flex items-start gap-2"><i class="fas fa-arrow-right text-primary mt-1 flex-shrink-0"></i>Respond to client messages within 24 hours during active projects.</li>
                <li class="flex items-start gap-2"><i class="fas fa-arrow-right text-primary mt-1 flex-shrink-0"></i>Comply with all applicable laws and regulations in your jurisdiction.</li>
              </ul>
            </div>

            <div id="payments" class="mb-10">
              <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center gap-3"><span class="w-8 h-8 rounded-lg bg-yellow-50 flex items-center justify-center flex-shrink-0"><i class="fas fa-credit-card text-yellow-600 text-sm"></i></span>6. Payments & Fees</h2>
              <p class="text-gray-500 leading-relaxed mb-3">JobHub uses an escrow payment system to protect both clients and freelancers:</p>
              <div class="space-y-2 text-gray-500">
                <div class="flex items-start gap-2"><i class="fas fa-dollar-sign text-green-500 mt-1 flex-shrink-0"></i><strong class="text-gray-900">Service Fee:</strong> Freelancers pay a 10% service fee on earnings (5% for Pro members).</div>
                <div class="flex items-start gap-2"><i class="fas fa-dollar-sign text-green-500 mt-1 flex-shrink-0"></i><strong class="text-gray-900">Processing Fee:</strong> Clients pay a 2.5% payment processing fee per transaction.</div>
                <div class="flex items-start gap-2"><i class="fas fa-dollar-sign text-green-500 mt-1 flex-shrink-0"></i><strong class="text-gray-900">Escrow:</strong> Funds are held in escrow until work is approved by the client.</div>
                <div class="flex items-start gap-2"><i class="fas fa-dollar-sign text-green-500 mt-1 flex-shrink-0"></i><strong class="text-gray-900">Withdrawals:</strong> Minimum withdrawal is $20. Processing takes 2-5 business days.</div>
              </div>
            </div>

            <div id="disputes" class="mb-10">
              <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center gap-3"><span class="w-8 h-8 rounded-lg bg-red-50 flex items-center justify-center flex-shrink-0"><i class="fas fa-exclamation-triangle text-red-600 text-sm"></i></span>7. Disputes & Resolution</h2>
              <p class="text-gray-500 leading-relaxed mb-3">If a dispute arises between a client and freelancer:</p>
              <ol class="space-y-2 text-gray-500 list-decimal pl-5">
                <li>Both parties should first attempt to resolve the issue through direct communication on the platform.</li>
                <li>If unresolved, either party may open a formal dispute through the project dashboard.</li>
                <li>JobHub's resolution team will review evidence from both sides within 5 business days.</li>
                <li>A binding decision will be made based on the evidence provided, project requirements, and platform Terms.</li>
                <li>Funds in escrow remain frozen during the dispute process and will be released based on the resolution outcome.</li>
              </ol>
            </div>

            <div id="ip" class="mb-10">
              <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center gap-3"><span class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center flex-shrink-0"><i class="fas fa-lightbulb text-indigo-600 text-sm"></i></span>8. Intellectual Property</h2>
              <p class="text-gray-500 leading-relaxed mb-3">Unless otherwise agreed in the contract:</p>
              <ul class="space-y-2 text-gray-500">
                <li class="flex items-start gap-2"><i class="fas fa-arrow-right text-primary mt-1 flex-shrink-0"></i><strong class="text-gray-900">Work Product:</strong> The freelancer retains ownership of the work until full payment is received, at which point ownership transfers to the client.</li>
                <li class="flex items-start gap-2"><i class="fas fa-arrow-right text-primary mt-1 flex-shrink-0"></i><strong class="text-gray-900">Portfolio Rights:</strong> Freelancers may display completed work in their portfolio unless the contract specifies otherwise.</li>
                <li class="flex items-start gap-2"><i class="fas fa-arrow-right text-primary mt-1 flex-shrink-0"></i><strong class="text-gray-900">Pre-existing IP:</strong> Each party retains ownership of their pre-existing intellectual property.</li>
              </ul>
            </div>

            <div id="liability" class="mb-10">
              <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center gap-3"><span class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0"><i class="fas fa-shield-alt text-gray-600 text-sm"></i></span>9. Limitation of Liability</h2>
              <p class="text-gray-500 leading-relaxed">JobHub acts as a marketplace connecting clients and freelancers. We are not a party to contracts between users. Our liability is limited to the maximum extent permitted by law. We do not guarantee the quality, safety, or legality of services offered on the platform. Users are responsible for their own compliance with applicable laws and regulations.</p>
            </div>

            <div id="termination">
              <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center gap-3"><span class="w-8 h-8 rounded-lg bg-orange-50 flex items-center justify-center flex-shrink-0"><i class="fas fa-power-off text-orange-600 text-sm"></i></span>10. Termination</h2>
              <p class="text-gray-500 leading-relaxed mb-3">Either party may terminate their account at any time through the account settings. JobHub may suspend or terminate accounts for:</p>
              <ul class="space-y-2 text-gray-500">
                <li class="flex items-start gap-2"><i class="fas fa-arrow-right text-primary mt-1 flex-shrink-0"></i>Violation of these Terms of Service</li>
                <li class="flex items-start gap-2"><i class="fas fa-arrow-right text-primary mt-1 flex-shrink-0"></i>Fraudulent or illegal activity</li>
                <li class="flex items-start gap-2"><i class="fas fa-arrow-right text-primary mt-1 flex-shrink-0"></i>Non-payment of fees</li>
                <li class="flex items-start gap-2"><i class="fas fa-arrow-right text-primary mt-1 flex-shrink-0"></i>Requests by law enforcement</li>
              </ul>
            </div>
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
      updateToc();
    });
    document.getElementById('hamburger').addEventListener('click', () => { document.getElementById('mobile-menu').classList.toggle('open'); });
    document.querySelectorAll('#mobile-menu a').forEach(a => { a.addEventListener('click', () => document.getElementById('mobile-menu').classList.remove('open')); });
    const reveals = document.querySelectorAll('.reveal');
    function checkReveal() { reveals.forEach(el => { if (el.getBoundingClientRect().top < window.innerHeight - 80) el.classList.add('visible'); }); }
    checkReveal();

    const tocLinks = document.querySelectorAll('.toc-link');
    const sections = ['acceptance', 'accounts', 'rules', 'client-obligations', 'freelancer-obligations', 'payments', 'disputes', 'ip', 'liability', 'termination'];
    function updateToc() {
      let current = '';
      sections.forEach(id => {
        const el = document.getElementById(id);
        if (el && el.getBoundingClientRect().top <= 120) current = id;
      });
      tocLinks.forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('href') === '#' + current) link.classList.add('active');
      });
    }
    tocLinks.forEach(link => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        const target = document.querySelector(link.getAttribute('href'));
        if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });
  </script>
</body>
</html>
