<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>FAQ – JobHub</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/@flaticon/flaticon-uicons@3.3.1/css/all/all.min.css" rel="stylesheet">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { inter: ['Inter', 'sans-serif'] },
          colors: {
            primary: { DEFAULT: '#2563eb', dark: '#1d4ed8', light: '#3b82f6' },
            accent: { DEFAULT: '#0ea5e9', dark: '#0284c7' },
            surface: { DEFAULT: '#f8fafc', card: '#ffffff', border: '#e2e8f0' },
          },
          animation: {
            'fade-up': 'fadeUp 0.6s ease forwards',
            'float': 'float 3s ease-in-out infinite',
          },
          keyframes: {
            fadeUp: { '0%': { opacity: 0, transform: 'translateY(24px)' }, '100%': { opacity: 1, transform: 'translateY(0)' } },
            float: { '0%,100%': { transform: 'translateY(0)' }, '50%': { transform: 'translateY(-10px)' } },
          }
        }
      }
    }
  </script>
  <style>
    * { font-family: 'Inter', sans-serif; }
    body { background: #f8fafc; color: #1e293b; }
    .grad-text { background: linear-gradient(135deg, #2563eb 0%, #0ea5e9 60%, #6366f1 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
    .glass { background: rgba(255,255,255,.85); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); border: 1px solid rgba(226,232,240,.8); }
    .nav-link { position: relative; }
    .nav-link::after { content: ''; position: absolute; bottom: -2px; left: 0; width: 0; height: 2px; background: linear-gradient(90deg, #2563eb, #0ea5e9); transition: width .3s ease; }
    .nav-link:hover::after { width: 100%; }
    .orb { position: absolute; border-radius: 50%; filter: blur(80px); opacity: .15; animation: float 6s ease-in-out infinite; }
    .btn-grad { background: linear-gradient(135deg, #2563eb, #0ea5e9); transition: opacity .25s, transform .2s; }
    .btn-grad:hover { opacity: .88; transform: translateY(-2px); }
    #progress { position: fixed; top: 0; left: 0; height: 3px; background: linear-gradient(90deg, #2563eb, #0ea5e9, #6366f1); z-index: 9999; transition: width .1s; }
    #mobile-menu { transition: max-height .35s ease, opacity .3s ease; max-height: 0; opacity: 0; overflow: hidden; }
    #mobile-menu.open { max-height: 600px; opacity: 1; }
    .reveal { opacity: 0; transform: translateY(30px); transition: opacity .7s ease, transform .7s ease; }
    .reveal.visible { opacity: 1; transform: translateY(0); }
    #navbar.scrolled { background: rgba(255,255,255,.95); backdrop-filter: blur(16px); box-shadow: 0 2px 20px rgba(0,0,0,.08); }
    .white-card { background: #ffffff; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,.05); }
    .faq-answer { max-height: 0; overflow: hidden; transition: max-height .35s ease, padding .35s ease; }
    .faq-answer.open { max-height: 500px; }
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
        <span class="w-9 h-9 rounded-xl btn-grad flex items-center justify-center shadow-lg shadow-blue-500/25">
          <i class="fi fi-brands-artstation text-white text-sm"></i>
        </span>
        <span class="text-xl font-extrabold tracking-tight">
          <span class="text-gray-900">Job</span><span class="grad-text">Hub</span>
        </span>
      </a>
      <div class="hidden lg:flex items-center gap-6 text-sm font-medium text-gray-500">
        <a href="index.php" class="nav-link hover:text-gray-900 transition-colors">Home</a>
        <a href="index.php#jobs" class="nav-link hover:text-gray-900 transition-colors">Find Work</a>
        <a href="index.php#freelancers" class="nav-link hover:text-gray-900 transition-colors">Find Freelancers</a>
        <a href="index.php#howitworks" class="nav-link hover:text-gray-900 transition-colors">How It Works</a>
        <a href="pricing.php" class="nav-link hover:text-gray-900 transition-colors">Pricing</a>
        <a href="contact.php" class="nav-link hover:text-gray-900 transition-colors">Contact</a>
      </div>
      <div class="hidden lg:flex items-center gap-3">
        <a href="auth/login.php" class="text-sm font-semibold text-gray-600 hover:text-gray-900 border border-gray-200 hover:border-primary px-4 py-2 rounded-lg transition-all">Log In</a>
        <a href="auth/register.php" class="btn-grad text-sm font-semibold text-white px-5 py-2 rounded-lg shadow-lg shadow-blue-500/25">Sign Up</a>
      </div>
      <button id="hamburger" class="lg:hidden text-gray-600 hover:text-gray-900 p-2" aria-label="Toggle menu">
        <i class="fas fa-bars text-xl"></i>
      </button>
    </div>
    <div id="mobile-menu" class="lg:hidden bg-white mx-4 mt-2 rounded-2xl shadow-xl border border-gray-100">
      <div class="flex flex-col gap-1 p-4 text-sm font-medium text-gray-600">
        <a href="index.php" class="hover:text-gray-900 py-2 px-3 rounded-lg hover:bg-gray-50 transition-colors">Home</a>
        <a href="index.php#jobs" class="hover:text-gray-900 py-2 px-3 rounded-lg hover:bg-gray-50 transition-colors">Find Work</a>
        <a href="index.php#freelancers" class="hover:text-gray-900 py-2 px-3 rounded-lg hover:bg-gray-50 transition-colors">Find Freelancers</a>
        <a href="pricing.php" class="hover:text-gray-900 py-2 px-3 rounded-lg hover:bg-gray-50 transition-colors">Pricing</a>
        <a href="contact.php" class="hover:text-gray-900 py-2 px-3 rounded-lg hover:bg-gray-50 transition-colors">Contact</a>
        <hr class="border-gray-100 my-1" />
        <a href="auth/login.php" class="hover:text-gray-900 py-2 px-3 rounded-lg hover:bg-gray-50 transition-colors">Log In</a>
        <a href="auth/register.php" class="btn-grad text-white text-center py-2 px-3 rounded-lg mt-1">Sign Up</a>
      </div>
    </div>
  </nav>

  <!-- HERO -->
  <section class="relative pt-32 pb-20 overflow-hidden bg-gradient-to-br from-blue-50 via-white to-cyan-50">
    <div class="orb w-[500px] h-[500px] bg-blue-400 top-0 -right-40" style="animation-delay:0s"></div>
    <div class="orb w-[400px] h-[400px] bg-cyan-300 bottom-0 -left-32" style="animation-delay:2s"></div>
    <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 text-center">
      <span class="text-xs font-bold uppercase tracking-widest text-blue-600/70 bg-blue-50 px-3 py-1 rounded-full">Help Center</span>
      <h1 class="text-4xl sm:text-5xl font-black mt-4 mb-4 text-gray-900">Frequently Asked <span class="grad-text">Questions</span></h1>
      <p class="text-gray-500 max-w-2xl mx-auto mb-8">Find answers to common questions about JobHub. Can't find what you're looking for? <a href="contact.php" class="text-blue-600 hover:underline">Contact our support team.</a></p>
      <div class="max-w-2xl mx-auto relative">
        <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
        <input id="faq-search" type="text" placeholder="Search questions..." class="w-full pl-12 pr-4 py-4 rounded-2xl border border-gray-200 bg-white shadow-lg shadow-gray-200/50 text-gray-900 placeholder-gray-400 outline-none focus:border-primary transition-colors text-sm" />
      </div>
    </div>
  </section>

  <!-- FAQ CATEGORIES -->
  <section class="py-16 px-4 reveal">
    <div class="max-w-5xl mx-auto">
      <div class="flex flex-wrap justify-center gap-3 mb-12">
        <button onclick="filterCategory('all')" class="faq-cat active bg-blue-600 text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition-all">All</button>
        <button onclick="filterCategory('general')" class="faq-cat bg-white text-gray-600 text-sm font-semibold px-5 py-2.5 rounded-xl border border-gray-200 hover:border-primary transition-all">General</button>
        <button onclick="filterCategory('clients')" class="faq-cat bg-white text-gray-600 text-sm font-semibold px-5 py-2.5 rounded-xl border border-gray-200 hover:border-primary transition-all">For Clients</button>
        <button onclick="filterCategory('freelancers')" class="faq-cat bg-white text-gray-600 text-sm font-semibold px-5 py-2.5 rounded-xl border border-gray-200 hover:border-primary transition-all">For Freelancers</button>
        <button onclick="filterCategory('payments')" class="faq-cat bg-white text-gray-600 text-sm font-semibold px-5 py-2.5 rounded-xl border border-gray-200 hover:border-primary transition-all">Payments</button>
        <button onclick="filterCategory('security')" class="faq-cat bg-white text-gray-600 text-sm font-semibold px-5 py-2.5 rounded-xl border border-gray-200 hover:border-primary transition-all">Security</button>
      </div>

      <div id="faq-list" class="space-y-4">

        <!-- GENERAL -->
        <div class="faq-item bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden" data-category="general">
          <button onclick="toggleFaq(this)" class="w-full flex items-center justify-between px-6 py-5 text-left">
            <span class="font-semibold text-gray-900 text-sm pr-4">What is JobHub?</span>
            <i class="fas fa-chevron-down text-gray-400 faq-icon flex-shrink-0"></i>
          </button>
          <div class="faq-answer px-6 pb-5">
            <p class="text-gray-500 text-sm leading-relaxed">JobHub is an online marketplace connecting talented freelancers with clients worldwide. We provide a secure platform for posting jobs, submitting proposals, collaborating on projects, and processing payments — all with built-in escrow protection.</p>
          </div>
        </div>

        <div class="faq-item bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden" data-category="general">
          <button onclick="toggleFaq(this)" class="w-full flex items-center justify-between px-6 py-5 text-left">
            <span class="font-semibold text-gray-900 text-sm pr-4">How do I create an account?</span>
            <i class="fas fa-chevron-down text-gray-400 faq-icon flex-shrink-0"></i>
          </button>
          <div class="faq-answer px-6 pb-5">
            <p class="text-gray-500 text-sm leading-relaxed">Click the "Sign Up" button in the top right corner, choose whether you want to register as a client or freelancer, fill in your details, and verify your email address. The process takes less than 2 minutes.</p>
          </div>
        </div>

        <div class="faq-item bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden" data-category="general">
          <button onclick="toggleFaq(this)" class="w-full flex items-center justify-between px-6 py-5 text-left">
            <span class="font-semibold text-gray-900 text-sm pr-4">Is JobHub available worldwide?</span>
            <i class="fas fa-chevron-down text-gray-400 faq-icon flex-shrink-0"></i>
          </button>
          <div class="faq-answer px-6 pb-5">
            <p class="text-gray-500 text-sm leading-relaxed">Yes! JobHub operates in over 180 countries. Freelancers and clients can connect across time zones. We support multiple currencies and local payment methods to make transactions seamless globally.</p>
          </div>
        </div>

        <div class="faq-item bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden" data-category="general">
          <button onclick="toggleFaq(this)" class="w-full flex items-center justify-between px-6 py-5 text-left">
            <span class="font-semibold text-gray-900 text-sm pr-4">What skills are available on the platform?</span>
            <i class="fas fa-chevron-down text-gray-400 faq-icon flex-shrink-0"></i>
          </button>
          <div class="faq-answer px-6 pb-5">
            <p class="text-gray-500 text-sm leading-relaxed">JobHub covers 50+ skill categories including Web Development, Mobile Development, UI/UX Design, Data Science, AI/ML, Cybersecurity, DevOps, Game Development, Content Writing, Digital Marketing, and more.</p>
          </div>
        </div>

        <!-- FOR CLIENTS -->
        <div class="faq-item bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden" data-category="clients">
          <button onclick="toggleFaq(this)" class="w-full flex items-center justify-between px-6 py-5 text-left">
            <span class="font-semibold text-gray-900 text-sm pr-4">How do I post a job?</span>
            <i class="fas fa-chevron-down text-gray-400 faq-icon flex-shrink-0"></i>
          </button>
          <div class="faq-answer px-6 pb-5">
            <p class="text-gray-500 text-sm leading-relaxed">Log in to your client account, click "Post a Job" in the dashboard, describe your project requirements, set your budget (fixed price or hourly), add required skills, and publish. You'll start receiving proposals within hours.</p>
          </div>
        </div>

        <div class="faq-item bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden" data-category="clients">
          <button onclick="toggleFaq(this)" class="w-full flex items-center justify-between px-6 py-5 text-left">
            <span class="font-semibold text-gray-900 text-sm pr-4">How do I choose the right freelancer?</span>
            <i class="fas fa-chevron-down text-gray-400 faq-icon flex-shrink-0"></i>
          </button>
          <div class="faq-answer px-6 pb-5">
            <p class="text-gray-500 text-sm leading-relaxed">Review freelancer profiles, portfolios, ratings, and reviews. Use our AI-powered matching to find the best fit. You can also chat with candidates before making a decision. We recommend interviewing 2-3 top candidates.</p>
          </div>
        </div>

        <div class="faq-item bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden" data-category="clients">
          <button onclick="toggleFaq(this)" class="w-full flex items-center justify-between px-6 py-5 text-left">
            <span class="font-semibold text-gray-900 text-sm pr-4">What if I'm not satisfied with the work?</span>
            <i class="fas fa-chevron-down text-gray-400 faq-icon flex-shrink-0"></i>
          </button>
          <div class="faq-answer px-6 pb-5">
            <p class="text-gray-500 text-sm leading-relaxed">We have a milestone-based payment system. You can request revisions at each milestone. If you're still unsatisfied, our dispute resolution team will mediate. Funds in escrow are protected until you approve the deliverables.</p>
          </div>
        </div>

        <!-- FOR FREELANCERS -->
        <div class="faq-item bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden" data-category="freelancers">
          <button onclick="toggleFaq(this)" class="w-full flex items-center justify-between px-6 py-5 text-left">
            <span class="font-semibold text-gray-900 text-sm pr-4">How do I find work as a freelancer?</span>
            <i class="fas fa-chevron-down text-gray-400 faq-icon flex-shrink-0"></i>
          </button>
          <div class="faq-answer px-6 pb-5">
            <p class="text-gray-500 text-sm leading-relaxed">Browse the job listings, filter by your skills and budget preferences, and submit a personalized proposal. Our AI also recommends jobs matching your profile. A compelling proposal with relevant portfolio samples increases your chances.</p>
          </div>
        </div>

        <div class="faq-item bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden" data-category="freelancers">
          <button onclick="toggleFaq(this)" class="w-full flex items-center justify-between px-6 py-5 text-left">
            <span class="font-semibold text-gray-900 text-sm pr-4">How do I set my hourly rate?</span>
            <i class="fas fa-chevron-down text-gray-400 faq-icon flex-shrink-0"></i>
          </button>
          <div class="faq-answer px-6 pb-5">
            <p class="text-gray-500 text-sm leading-relaxed">Research market rates for your skill level and specialization. Consider your experience, portfolio, and demand. You can change your rate anytime. New freelancers often start competitively and increase rates as they build reviews.</p>
          </div>
        </div>

        <div class="faq-item bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden" data-category="freelancers">
          <button onclick="toggleFaq(this)" class="w-full flex items-center justify-between px-6 py-5 text-left">
            <span class="font-semibold text-gray-900 text-sm pr-4">How do I get verified on JobHub?</span>
            <i class="fas fa-chevron-down text-gray-400 faq-icon flex-shrink-0"></i>
          </button>
          <div class="faq-answer px-6 pb-5">
            <p class="text-gray-500 text-sm leading-relaxed">Go to your profile settings and click "Get Verified." You'll need to upload a government-issued ID and complete a skill assessment test. Verification typically takes 24-48 hours and adds a trust badge to your profile.</p>
          </div>
        </div>

        <!-- PAYMENTS -->
        <div class="faq-item bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden" data-category="payments">
          <button onclick="toggleFaq(this)" class="w-full flex items-center justify-between px-6 py-5 text-left">
            <span class="font-semibold text-gray-900 text-sm pr-4">What payment methods are accepted?</span>
            <i class="fas fa-chevron-down text-gray-400 faq-icon flex-shrink-0"></i>
          </button>
          <div class="faq-answer px-6 pb-5">
            <p class="text-gray-500 text-sm leading-relaxed">We accept all major credit/debit cards (Visa, MasterCard, Amex), PayPal, bank transfers (ACH/SEPA), and cryptocurrency (Bitcoin, Ethereum). Clients can choose their preferred method at checkout.</p>
          </div>
        </div>

        <div class="faq-item bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden" data-category="payments">
          <button onclick="toggleFaq(this)" class="w-full flex items-center justify-between px-6 py-5 text-left">
            <span class="font-semibold text-gray-900 text-sm pr-4">How does escrow payment work?</span>
            <i class="fas fa-chevron-down text-gray-400 faq-icon flex-shrink-0"></i>
          </button>
          <div class="faq-answer px-6 pb-5">
            <p class="text-gray-500 text-sm leading-relaxed">When you hire a freelancer, funds are deposited into a secure escrow account. The freelancer then works on the project. Once you approve the delivered work, the funds are released to the freelancer. This protects both parties.</p>
          </div>
        </div>

        <div class="faq-item bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden" data-category="payments">
          <button onclick="toggleFaq(this)" class="w-full flex items-center justify-between px-6 py-5 text-left">
            <span class="font-semibold text-gray-900 text-sm pr-4">How and when can I withdraw my earnings?</span>
            <i class="fas fa-chevron-down text-gray-400 faq-icon flex-shrink-0"></i>
          </button>
          <div class="faq-answer px-6 pb-5">
            <p class="text-gray-500 text-sm leading-relaxed">Freelancers can withdraw earnings via bank transfer, PayPal, or Payoneer. Minimum withdrawal is $20. Processing takes 2-5 business days depending on your chosen method. You can set up automatic weekly withdrawals.</p>
          </div>
        </div>

        <div class="faq-item bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden" data-category="payments">
          <button onclick="toggleFaq(this)" class="w-full flex items-center justify-between px-6 py-5 text-left">
            <span class="font-semibold text-gray-900 text-sm pr-4">Are there any hidden fees?</span>
            <i class="fas fa-chevron-down text-gray-400 faq-icon flex-shrink-0"></i>
          </button>
          <div class="faq-answer px-6 pb-5">
            <p class="text-gray-500 text-sm leading-relaxed">No hidden fees. JobHub charges a transparent service fee: 10% for free accounts and 5% for Pro members on the freelancer side. Clients pay a small payment processing fee (2.5%) per transaction. All fees are clearly displayed before you confirm any payment.</p>
          </div>
        </div>

        <!-- SECURITY -->
        <div class="faq-item bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden" data-category="security">
          <button onclick="toggleFaq(this)" class="w-full flex items-center justify-between px-6 py-5 text-left">
            <span class="font-semibold text-gray-900 text-sm pr-4">How is my personal data protected?</span>
            <i class="fas fa-chevron-down text-gray-400 faq-icon flex-shrink-0"></i>
          </button>
          <div class="faq-answer px-6 pb-5">
            <p class="text-gray-500 text-sm leading-relaxed">We use AES-256 encryption for data at rest, TLS 1.3 for data in transit, and comply with GDPR and CCPA regulations. Your personal and financial information is never shared with third parties without your explicit consent.</p>
          </div>
        </div>

        <div class="faq-item bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden" data-category="security">
          <button onclick="toggleFaq(this)" class="w-full flex items-center justify-between px-6 py-5 text-left">
            <span class="font-semibold text-gray-900 text-sm pr-4">What is JobHub's fraud prevention system?</span>
            <i class="fas fa-chevron-down text-gray-400 faq-icon flex-shrink-0"></i>
          </button>
          <div class="faq-answer px-6 pb-5">
            <p class="text-gray-500 text-sm leading-relaxed">Our AI-powered fraud detection monitors all transactions in real-time. We verify user identities, detect suspicious activity, and flag potential scams. Both clients and freelancers can report issues, and our team investigates within 24 hours.</p>
          </div>
        </div>

        <div class="faq-item bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden" data-category="security">
          <button onclick="toggleFaq(this)" class="w-full flex items-center justify-between px-6 py-5 text-left">
            <span class="font-semibold text-gray-900 text-sm pr-4">What happens if there's a dispute?</span>
            <i class="fas fa-chevron-down text-gray-400 faq-icon flex-shrink-0"></i>
          </button>
          <div class="faq-answer px-6 pb-5">
            <p class="text-gray-500 text-sm leading-relaxed">Either party can open a dispute from the project page. Our resolution team reviews evidence from both sides, including chat logs, deliverables, and milestones. Most disputes are resolved within 5 business days. Escrowed funds remain frozen until resolution.</p>
          </div>
        </div>

      </div>
    </div>
  </section>

  <!-- CONTACT CTA -->
  <section class="py-20 px-4 reveal">
    <div class="max-w-4xl mx-auto">
      <div class="bg-gradient-to-br from-blue-600 to-cyan-500 rounded-3xl p-10 sm:p-16 text-center relative overflow-hidden">
        <div class="absolute inset-0 opacity-10" style="background:radial-gradient(ellipse at 20% 50%,#fff 0%,transparent 60%),radial-gradient(ellipse at 80% 50%,#fff 0%,transparent 60%)"></div>
        <div class="relative">
          <i class="fas fa-headset text-white/80 text-4xl mb-6"></i>
          <h2 class="text-3xl sm:text-4xl font-black text-white mb-4">Still Have Questions?</h2>
          <p class="text-blue-100 max-w-xl mx-auto mb-8">Our support team is here to help. Reach out anytime and we'll get back to you within 24 hours.</p>
          <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="contact.php" class="bg-white text-blue-600 font-semibold px-8 py-4 rounded-full hover:bg-blue-50 transition-all inline-flex items-center justify-center gap-2">
              <i class="fas fa-envelope"></i> Contact Support
            </a>
            <a href="mailto:hello@jobhub.io" class="bg-white/10 backdrop-blur-md text-white font-semibold px-8 py-4 rounded-full border border-white/30 hover:bg-white/20 transition-all inline-flex items-center justify-center gap-2">
              <i class="fas fa-paper-plane"></i> Email Us
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
            <div class="w-9 h-9 rounded-xl btn-grad flex items-center justify-center shadow-lg shadow-blue-500/25">
              <i class="fi fi-brands-artstation text-white text-sm"></i>
            </div>
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

  <!-- BACK TO TOP -->
  <button id="back-top" class="fixed bottom-6 right-6 w-12 h-12 btn-grad text-white rounded-xl shadow-xl shadow-blue-500/25 hidden items-center justify-center hover:-translate-y-1 transition-all z-50" onclick="window.scrollTo({top:0,behavior:'smooth'})">
    <i class="fas fa-chevron-up text-sm"></i>
  </button>

  <script>
    // Scroll progress & navbar
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

    // Mobile hamburger
    document.getElementById('hamburger').addEventListener('click', () => {
      document.getElementById('mobile-menu').classList.toggle('open');
    });
    document.querySelectorAll('#mobile-menu a').forEach(a => {
      a.addEventListener('click', () => document.getElementById('mobile-menu').classList.remove('open'));
    });

    // FAQ toggle
    function toggleFaq(btn) {
      const item = btn.closest('.faq-item');
      const answer = item.querySelector('.faq-answer');
      const isOpen = answer.classList.contains('open');
      document.querySelectorAll('.faq-answer.open').forEach(a => { a.classList.remove('open'); });
      document.querySelectorAll('.faq-item.active').forEach(i => { i.classList.remove('active'); });
      if (!isOpen) { answer.classList.add('open'); item.classList.add('active'); }
    }

    // Category filter
    function filterCategory(cat) {
      document.querySelectorAll('.faq-cat').forEach(b => {
        b.classList.remove('bg-blue-600', 'text-white', 'active');
        b.classList.add('bg-white', 'text-gray-600');
      });
      event.target.classList.add('bg-blue-600', 'text-white', 'active');
      event.target.classList.remove('bg-white', 'text-gray-600');
      document.querySelectorAll('.faq-item').forEach(item => {
        if (cat === 'all' || item.dataset.category === cat) {
          item.style.display = 'block';
        } else {
          item.style.display = 'none';
        }
      });
    }

    // Search FAQs
    document.getElementById('faq-search').addEventListener('input', function() {
      const q = this.value.toLowerCase();
      document.querySelectorAll('.faq-item').forEach(item => {
        const text = item.textContent.toLowerCase();
        item.style.display = text.includes(q) ? 'block' : 'none';
      });
    });

    // Scroll reveal
    const reveals = document.querySelectorAll('.reveal');
    function checkReveal() {
      reveals.forEach(el => {
        const top = el.getBoundingClientRect().top;
        if (top < window.innerHeight - 80) el.classList.add('visible');
      });
    }
    checkReveal();
  </script>
</body>
</html>
