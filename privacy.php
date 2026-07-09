<!DOCTYPE html>
<html lang="en" class="scroll-smooth">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Privacy Policy – JobHub</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/@flaticon/flaticon-uicons@3.3.1/css/all/all.min.css" rel="stylesheet">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            inter: ['Inter', 'sans-serif']
          },
          colors: {
            primary: {
              DEFAULT: '#2563eb',
              dark: '#1d4ed8',
              light: '#3b82f6'
            },
            accent: {
              DEFAULT: '#0ea5e9',
              dark: '#0284c7'
            },
          },
          animation: {
            'float': 'float 3s ease-in-out infinite'
          },
          keyframes: {
            float: {
              '0%,100%': {
                transform: 'translateY(0)'
              },
              '50%': {
                transform: 'translateY(-10px)'
              }
            }
          }
        }
      }
    }
  </script>
  <style>
    * {
      font-family: 'Inter', sans-serif;
    }

    body {
      background: #f8fafc;
      color: #1e293b;
    }

    .grad-text {
      background: linear-gradient(135deg, #2563eb 0%, #0ea5e9 60%, #6366f1 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .btn-grad {
      background: linear-gradient(135deg, #2563eb, #0ea5e9);
      transition: opacity .25s, transform .2s;
    }

    .btn-grad:hover {
      opacity: .88;
      transform: translateY(-2px);
    }

    .nav-link {
      position: relative;
    }

    .nav-link::after {
      content: '';
      position: absolute;
      bottom: -2px;
      left: 0;
      width: 0;
      height: 2px;
      background: linear-gradient(90deg, #2563eb, #0ea5e9);
      transition: width .3s ease;
    }

    .nav-link:hover::after {
      width: 100%;
    }

    .orb {
      position: absolute;
      border-radius: 50%;
      filter: blur(80px);
      opacity: .15;
      animation: float 6s ease-in-out infinite;
    }

    #progress {
      position: fixed;
      top: 0;
      left: 0;
      height: 3px;
      background: linear-gradient(90deg, #2563eb, #0ea5e9, #6366f1);
      z-index: 9999;
      transition: width .1s;
    }

    #mobile-menu {
      transition: max-height .35s ease, opacity .3s ease;
      max-height: 0;
      opacity: 0;
      overflow: hidden;
    }

    #mobile-menu.open {
      max-height: 600px;
      opacity: 1;
    }

    .reveal {
      opacity: 0;
      transform: translateY(30px);
      transition: opacity .7s ease, transform .7s ease;
    }

    .reveal.visible {
      opacity: 1;
      transform: translateY(0);
    }

    #navbar.scrolled {
      background: rgba(255, 255, 255, .95);
      backdrop-filter: blur(16px);
      box-shadow: 0 2px 20px rgba(0, 0, 0, .08);
    }

    .toc-link.active {
      color: #2563eb;
      border-color: #2563eb;
      background: #eff6ff;
    }
  </style>
</head>

<body>
  <div id="progress"></div>

  <!-- NAVBAR -->
  <nav id="navbar" class="fixed top-0 inset-x-0 z-50 transition-all duration-300 py-3 bg-white/80 backdrop-blur-md">
    <div class="w-full mx-auto px-4 sm:px-6 flex items-center justify-between">
      <a href="index.php" class="flex items-center gap-1.5 group shrink-0">
        <img src="assets/upload/logos/logo.png" alt="Logo" class="w-[36px] h-[36px] rounded-xl">
        <span class="text-lg font-extrabold tracking-tight">
          <span class="text-gray-900">Job</span><span class="grad-text">Hub</span>
        </span>
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
      <h1 class="text-4xl sm:text-5xl font-black mt-4 mb-4 text-gray-900">Privacy <span class="grad-text">Policy</span></h1>
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
                <a href="#collection" class="toc-link block text-sm text-gray-500 hover:text-primary py-1.5 px-3 rounded-lg transition-all border-l-2 border-transparent">Information We Collect</a>
                <a href="#usage" class="toc-link block text-sm text-gray-500 hover:text-primary py-1.5 px-3 rounded-lg transition-all border-l-2 border-transparent">How We Use Your Information</a>
                <a href="#sharing" class="toc-link block text-sm text-gray-500 hover:text-primary py-1.5 px-3 rounded-lg transition-all border-l-2 border-transparent">Data Sharing</a>
                <a href="#cookies" class="toc-link block text-sm text-gray-500 hover:text-primary py-1.5 px-3 rounded-lg transition-all border-l-2 border-transparent">Cookies</a>
                <a href="#security" class="toc-link block text-sm text-gray-500 hover:text-primary py-1.5 px-3 rounded-lg transition-all border-l-2 border-transparent">Data Security</a>
                <a href="#rights" class="toc-link block text-sm text-gray-500 hover:text-primary py-1.5 px-3 rounded-lg transition-all border-l-2 border-transparent">Your Rights</a>
                <a href="#contact" class="toc-link block text-sm text-gray-500 hover:text-primary py-1.5 px-3 rounded-lg transition-all border-l-2 border-transparent">Contact Us</a>
              </nav>
            </div>
          </div>
        </div>

        <!-- Main Content -->
        <div class="lg:col-span-3">
          <div class="bg-white rounded-3xl p-8 sm:p-12 border border-gray-100 shadow-sm prose prose-sm max-w-none">
            <p class="text-gray-500 leading-relaxed mb-8">This Privacy Policy describes how JobHub ("we", "our", or "us") collects, uses, and protects your personal information when you use our platform and services. By using JobHub, you agree to the collection and use of information in accordance with this policy.</p>

            <div id="collection" class="mb-10">
              <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center gap-3"><span class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0"><i class="fas fa-database text-blue-600 text-sm"></i></span>Information We Collect</h2>
              <p class="text-gray-500 leading-relaxed mb-4">We collect information to provide and improve our services. The types of information we collect include:</p>
              <div class="space-y-3">
                <div class="flex items-start gap-3"><i class="fas fa-check text-green-500 mt-1 flex-shrink-0"></i>
                  <div><strong class="text-gray-900">Personal Information:</strong> <span class="text-gray-500">Name, email address, phone number, postal address, date of birth, and government-issued ID for verification purposes.</span></div>
                </div>
                <div class="flex items-start gap-3"><i class="fas fa-check text-green-500 mt-1 flex-shrink-0"></i>
                  <div><strong class="text-gray-900">Profile Information:</strong> <span class="text-gray-500">Professional skills, portfolio items, work history, education, hourly rate, and profile photograph.</span></div>
                </div>
                <div class="flex items-start gap-3"><i class="fas fa-check text-green-500 mt-1 flex-shrink-0"></i>
                  <div><strong class="text-gray-900">Financial Information:</strong> <span class="text-gray-500">Payment method details (credit card numbers, bank account information), billing address, and transaction history. Financial data is processed through our PCI-DSS compliant payment processors.</span></div>
                </div>
                <div class="flex items-start gap-3"><i class="fas fa-check text-green-500 mt-1 flex-shrink-0"></i>
                  <div><strong class="text-gray-900">Usage Data:</strong> <span class="text-gray-500">IP address, browser type, device information, pages visited, time spent on pages, click patterns, and referral URLs.</span></div>
                </div>
                <div class="flex items-start gap-3"><i class="fas fa-check text-green-500 mt-1 flex-shrink-0"></i>
                  <div><strong class="text-gray-900">Communications:</strong> <span class="text-gray-500">Messages sent through the platform, customer support inquiries, and feedback you provide.</span></div>
                </div>
              </div>
            </div>

            <div id="usage" class="mb-10">
              <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center gap-3"><span class="w-8 h-8 rounded-lg bg-cyan-50 flex items-center justify-center flex-shrink-0"><i class="fas fa-cogs text-cyan-600 text-sm"></i></span>How We Use Your Information</h2>
              <p class="text-gray-500 leading-relaxed mb-4">We use the information we collect for the following purposes:</p>
              <ul class="space-y-2 text-gray-500">
                <li class="flex items-start gap-2"><i class="fas fa-arrow-right text-primary mt-1 flex-shrink-0"></i>To provide, maintain, and improve our platform and services</li>
                <li class="flex items-start gap-2"><i class="fas fa-arrow-right text-primary mt-1 flex-shrink-0"></i>To process transactions and send related information (receipts, confirmations)</li>
                <li class="flex items-start gap-2"><i class="fas fa-arrow-right text-primary mt-1 flex-shrink-0"></i>To match freelancers with suitable job opportunities using AI algorithms</li>
                <li class="flex items-start gap-2"><i class="fas fa-arrow-right text-primary mt-1 flex-shrink-0"></i>To send administrative notifications (service updates, security alerts)</li>
                <li class="flex items-start gap-2"><i class="fas fa-arrow-right text-primary mt-1 flex-shrink-0"></i>To respond to your inquiries and provide customer support</li>
                <li class="flex items-start gap-2"><i class="fas fa-arrow-right text-primary mt-1 flex-shrink-0"></i>To detect and prevent fraud, abuse, and security incidents</li>
                <li class="flex items-start gap-2"><i class="fas fa-arrow-right text-primary mt-1 flex-shrink-0"></i>To comply with legal obligations and enforce our terms</li>
                <li class="flex items-start gap-2"><i class="fas fa-arrow-right text-primary mt-1 flex-shrink-0"></i>To send marketing communications (with your consent)</li>
              </ul>
            </div>

            <div id="sharing" class="mb-10">
              <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center gap-3"><span class="w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center flex-shrink-0"><i class="fas fa-share-alt text-purple-600 text-sm"></i></span>Data Sharing</h2>
              <p class="text-gray-500 leading-relaxed mb-4">We do not sell your personal information. We share your data only in the following circumstances:</p>
              <ul class="space-y-2 text-gray-500">
                <li class="flex items-start gap-2"><i class="fas fa-users text-gray-400 mt-1 flex-shrink-0"></i><strong class="text-gray-900">With Other Users:</strong> When you hire or are hired, certain profile information is shared to facilitate the working relationship.</li>
                <li class="flex items-start gap-2"><i class="fas fa-building text-gray-400 mt-1 flex-shrink-0"></i><strong class="text-gray-900">Service Providers:</strong> We share data with trusted third-party vendors who assist in operating our platform (payment processors, cloud hosting, analytics).</li>
                <li class="flex items-start gap-2"><i class="fas fa-gavel text-gray-400 mt-1 flex-shrink-0"></i><strong class="text-gray-900">Legal Requirements:</strong> When required by law, court order, or governmental authority.</li>
                <li class="flex items-start gap-2"><i class="fas fa-exchange-alt text-gray-400 mt-1 flex-shrink-0"></i><strong class="text-gray-900">Business Transfers:</strong> In connection with a merger, acquisition, or sale of assets, with appropriate notice to users.</li>
              </ul>
            </div>

            <div id="cookies" class="mb-10">
              <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center gap-3"><span class="w-8 h-8 rounded-lg bg-yellow-50 flex items-center justify-center flex-shrink-0"><i class="fas fa-cookie text-yellow-600 text-sm"></i></span>Cookies</h2>
              <p class="text-gray-500 leading-relaxed mb-4">We use cookies and similar technologies to enhance your experience on JobHub:</p>
              <div class="space-y-3">
                <div class="bg-gray-50 rounded-xl p-4"><strong class="text-gray-900 text-sm">Essential Cookies</strong>
                  <p class="text-gray-400 text-xs mt-1">Required for the platform to function (authentication, security, session management).</p>
                </div>
                <div class="bg-gray-50 rounded-xl p-4"><strong class="text-gray-900 text-sm">Analytics Cookies</strong>
                  <p class="text-gray-400 text-xs mt-1">Help us understand how users interact with the platform (Google Analytics, Mixpanel).</p>
                </div>
                <div class="bg-gray-50 rounded-xl p-4"><strong class="text-gray-900 text-sm">Marketing Cookies</strong>
                  <p class="text-gray-400 text-xs mt-1">Used to deliver relevant advertisements and track campaign performance.</p>
                </div>
                <div class="bg-gray-50 rounded-xl p-4"><strong class="text-gray-900 text-sm">Preference Cookies</strong>
                  <p class="text-gray-400 text-xs mt-1">Remember your settings and preferences (language, currency, theme).</p>
                </div>
              </div>
              <p class="text-gray-500 leading-relaxed mt-4">You can manage cookie preferences through your browser settings. Disabling essential cookies may affect platform functionality.</p>
            </div>

            <div id="security" class="mb-10">
              <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center gap-3"><span class="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center flex-shrink-0"><i class="fas fa-shield-alt text-green-600 text-sm"></i></span>Data Security</h2>
              <p class="text-gray-500 leading-relaxed mb-4">We implement industry-standard security measures to protect your personal information:</p>
              <div class="grid sm:grid-cols-2 gap-4">
                <div class="flex items-start gap-3 bg-gray-50 rounded-xl p-4"><i class="fas fa-lock text-green-600 mt-0.5 flex-shrink-0"></i>
                  <div>
                    <p class="text-sm font-semibold text-gray-900">Encryption</p>
                    <p class="text-xs text-gray-400">AES-256 at rest, TLS 1.3 in transit</p>
                  </div>
                </div>
                <div class="flex items-start gap-3 bg-gray-50 rounded-xl p-4"><i class="fas fa-server text-green-600 mt-0.5 flex-shrink-0"></i>
                  <div>
                    <p class="text-sm font-semibold text-gray-900">Infrastructure</p>
                    <p class="text-xs text-gray-400">SOC 2 certified data centers</p>
                  </div>
                </div>
                <div class="flex items-start gap-3 bg-gray-50 rounded-xl p-4"><i class="fas fa-user-shield text-green-600 mt-0.5 flex-shrink-0"></i>
                  <div>
                    <p class="text-sm font-semibold text-gray-900">Access Controls</p>
                    <p class="text-xs text-gray-400">Role-based access, MFA for employees</p>
                  </div>
                </div>
                <div class="flex items-start gap-3 bg-gray-50 rounded-xl p-4"><i class="fas fa-bug text-green-600 mt-0.5 flex-shrink-0"></i>
                  <div>
                    <p class="text-sm font-semibold text-gray-900">Monitoring</p>
                    <p class="text-xs text-gray-400">24/7 intrusion detection systems</p>
                  </div>
                </div>
              </div>
            </div>

            <div id="rights" class="mb-10">
              <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center gap-3"><span class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center flex-shrink-0"><i class="fas fa-balance-scale text-indigo-600 text-sm"></i></span>Your Rights</h2>
              <p class="text-gray-500 leading-relaxed mb-4">You have the following rights regarding your personal data under GDPR and CCPA:</p>
              <div class="space-y-3">
                <div class="flex items-start gap-3"><i class="fas fa-eye text-indigo-500 mt-1 flex-shrink-0"></i>
                  <div><strong class="text-gray-900">Right to Access:</strong> <span class="text-gray-500">Request a copy of all personal data we hold about you.</span></div>
                </div>
                <div class="flex items-start gap-3"><i class="fas fa-edit text-indigo-500 mt-1 flex-shrink-0"></i>
                  <div><strong class="text-gray-900">Right to Rectification:</strong> <span class="text-gray-500">Request correction of inaccurate or incomplete data.</span></div>
                </div>
                <div class="flex items-start gap-3"><i class="fas fa-trash text-indigo-500 mt-1 flex-shrink-0"></i>
                  <div><strong class="text-gray-900">Right to Erasure:</strong> <span class="text-gray-500">Request deletion of your personal data ("right to be forgotten").</span></div>
                </div>
                <div class="flex items-start gap-3"><i class="fas fa-download text-indigo-500 mt-1 flex-shrink-0"></i>
                  <div><strong class="text-gray-900">Right to Portability:</strong> <span class="text-gray-500">Receive your data in a structured, machine-readable format.</span></div>
                </div>
                <div class="flex items-start gap-3"><i class="fas fa-ban text-indigo-500 mt-1 flex-shrink-0"></i>
                  <div><strong class="text-gray-900">Right to Opt-Out:</strong> <span class="text-gray-500">Opt out of marketing communications at any time.</span></div>
                </div>
              </div>
            </div>

            <div id="contact">
              <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center gap-3"><span class="w-8 h-8 rounded-lg bg-red-50 flex items-center justify-center flex-shrink-0"><i class="fas fa-envelope text-red-600 text-sm"></i></span>Contact Us</h2>
              <p class="text-gray-500 leading-relaxed mb-4">If you have any questions about this Privacy Policy or wish to exercise your data rights, please contact our Data Protection Officer:</p>
              <div class="bg-gray-50 rounded-2xl p-6">
                <div class="space-y-2 text-sm">
                  <p class="text-gray-900"><i class="fas fa-envelope text-primary mr-2"></i>privacy@jobhub.io</p>
                  <p class="text-gray-900"><i class="fas fa-phone text-cyan-500 mr-2"></i>+95 9 757 889806</p>
                  <p class="text-gray-900"><i class="fas fa-map-marker-alt text-purple-500 mr-2"></i>42 Tech Street, Innovation Tower, Yangon 11041, Myanmar</p>
                </div>
              </div>
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
          <a href="index.php" class="flex items-center gap-1.5 group shrink-0">
            <img src="assets/upload/logos/logo.png" alt="Logo" class="w-[36px] h-[36px] rounded-xl">
            <span class="text-lg font-extrabold tracking-tight">
              <span class="text-gray-900">Job</span><span class="grad-text">Hub</span>
            </span>
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
    document.getElementById('hamburger').addEventListener('click', () => {
      document.getElementById('mobile-menu').classList.toggle('open');
    });
    document.querySelectorAll('#mobile-menu a').forEach(a => {
      a.addEventListener('click', () => document.getElementById('mobile-menu').classList.remove('open'));
    });
    const reveals = document.querySelectorAll('.reveal');

    function checkReveal() {
      reveals.forEach(el => {
        if (el.getBoundingClientRect().top < window.innerHeight - 80) el.classList.add('visible');
      });
    }
    checkReveal();

    // TOC active state
    const tocLinks = document.querySelectorAll('.toc-link');
    const sections = ['collection', 'usage', 'sharing', 'cookies', 'security', 'rights', 'contact'];

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
        if (target) target.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });
      });
    });
  </script>
</body>

</html>