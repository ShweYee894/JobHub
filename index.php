<!DOCTYPE html>
<html lang="en" class="scroll-smooth">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>JobHub – Hire Top Freelancers & Find Great Work</title>
  <meta name="description" content="JobHub connects talented freelancers with clients worldwide. Post a job, receive proposals, and get your project done by the best professionals." />
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/@flaticon/flaticon-uicons@3.3.1/css/all/all.min.css" rel="stylesheet">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/upload/logos/logo.png">
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
            surface: {
              DEFAULT: '#f8fafc',
              card: '#ffffff',
              border: '#e2e8f0'
            },
          },
          animation: {
            'fade-up': 'fadeUp 0.6s ease forwards',
            'float': 'float 3s ease-in-out infinite',
            'pulse-slow': 'pulse 3s cubic-bezier(0.4,0,0.6,1) infinite',
          },
          keyframes: {
            fadeUp: {
              '0%': {
                opacity: 0,
                transform: 'translateY(24px)'
              },
              '100%': {
                opacity: 1,
                transform: 'translateY(0)'
              }
            },
            float: {
              '0%,100%': {
                transform: 'translateY(0)'
              },
              '50%': {
                transform: 'translateY(-10px)'
              }
            },
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

    /* ── Gradient text ── */
    .grad-text {
      background: linear-gradient(135deg, #2563eb 0%, #0ea5e9 60%, #6366f1 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    /* ── Glass card ── */
    .glass {
      background: rgba(255, 255, 255, .85);
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
      border: 1px solid rgba(226, 232, 240, .8);
    }

    /* ── Nav link hover ── */
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

    /* ── Hero orb blobs ── */
    .orb {
      position: absolute;
      border-radius: 50%;
      filter: blur(80px);
      opacity: .15;
      animation: float 6s ease-in-out infinite;
    }

    /* ── Category card hover ── */
    .cat-card:hover {
      transform: translateY(-6px) scale(1.03);
      border-color: #2563eb;
      box-shadow: 0 10px 40px rgba(37, 99, 235, .15);
    }

    .cat-card {
      transition: all .3s ease;
    }

    /* ── Job card hover ── */
    .job-card:hover {
      transform: translateY(-4px);
      border-color: #0ea5e9;
      box-shadow: 0 10px 40px rgba(14, 165, 233, .12);
    }

    .job-card {
      transition: all .3s ease;
    }

    /* ── Freelancer card hover ── */
    .fl-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 10px 40px rgba(37, 99, 235, .15);
    }

    .fl-card {
      transition: all .3s ease;
    }

    /* ── Stars ── */
    .stars {
      color: #f59e0b;
    }

    /* ── Gradient border button ── */
    .btn-grad {
      background: linear-gradient(135deg, #2563eb, #0ea5e9);
      transition: opacity .25s, transform .2s;
    }

    .btn-grad:hover {
      opacity: .88;
      transform: translateY(-2px);
    }

    /* ── Stat counter ── */
    .stat-num {
      font-size: 3rem;
      font-weight: 900;
      background: linear-gradient(135deg, #2563eb, #0ea5e9);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    /* ── Scroll progress bar ── */
    #progress {
      position: fixed;
      top: 0;
      left: 0;
      height: 3px;
      background: linear-gradient(90deg, #2563eb, #0ea5e9, #6366f1);
      z-index: 9999;
      transition: width .1s;
    }

    /* ── Mobile menu ── */
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

    /* ── Tab active ── */
    .tab-btn.active {
      background: linear-gradient(135deg, #2563eb, #0ea5e9);
      color: #fff;
    }

    /* ── Testimonial card ── */
    .testi-card:hover {
      border-color: #6366f1;
      transform: translateY(-4px);
    }

    .testi-card {
      transition: all .3s ease;
    }

    /* ── Section reveal ── */
    .reveal {
      opacity: 0;
      transform: translateY(30px);
      transition: opacity .7s ease, transform .7s ease;
    }

    .reveal.visible {
      opacity: 1;
      transform: translateY(0);
    }

    /* ── Navbar scroll style ── */
    #navbar.scrolled {
      background: rgba(255, 255, 255, .95);
      backdrop-filter: blur(16px);
      box-shadow: 0 2px 20px rgba(0, 0, 0, .08);
    }

    /* ── White card styles ── */
    .white-card {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      box-shadow: 0 1px 3px rgba(0, 0, 0, .05);
    }

    /* ── Search dropdown styles ── */
    #searchBar {
      background: #ffffff;
    }

    #nav-search::placeholder,
    #hero-search::placeholder {
      color: #9ca3af;
    }
  </style>
</head>

<body>

  <!-- ─────────────────────── SCROLL PROGRESS ─────────────────────── -->
  <div id="progress"></div>

  <!-- ═══════════════════════ 1. NAVBAR ═══════════════════════════════ -->
  <nav id="navbar" class="fixed top-0 inset-x-0 z-50 transition-all duration-300 py-3 bg-white/80 backdrop-blur-md">
    <div class="w-full mx-auto px-4 sm:px-6 flex items-center justify-between">
      <div class="flex gap-16">
        <!-- Logo -->
        <a href="#" class="flex items-center gap-1.5 group">
          <img src="assets/upload/logos/logo.png" alt="Logo" class="w-[40px] h-[40px] rounded-2xl ">
          <span class="text-xl font-extrabold tracking-tight">
            <span class="text-gray-900">Job</span><span class="grad-text">Hub</span>
          </span>
        </a>

        <!-- Desktop links -->
        <div class="hidden lg:flex items-center gap-6 text-sm font-medium text-gray-500">
          <a href="#hero" class="nav-link hover:text-gray-900 transition-colors">Home</a>
          <a href="#jobs" class="nav-link hover:text-gray-900 transition-colors">Find Work</a>
          <a href="#freelancers" class="nav-link hover:text-gray-900 transition-colors">Find Freelancers</a>
          <a href="#howitworks" class="nav-link hover:text-gray-900 transition-colors">How It Works</a>
          <a href="#about" class="nav-link hover:text-gray-900 transition-colors">About Us</a>
          <a href="#pricing" class="nav-link hover:text-gray-900 transition-colors">Pricing</a>
        </div>
      </div>

      <div class="flex gap-3 items-center">
        <!--Search with Dropdown-->
        <div id="" class="relative flex items-center border border-gray-300 rounded-full bg-white overflow-visible">
          <div class="flex items-center gap-2 px-4 py-2">
            <i class="fas fa-search text-gray-400 text-sm"></i>
            <input id="nav-search" type="text" placeholder="Search"
              class="bg-transparent w-32 sm:w-48 text-gray-900 placeholder-gray-400 outline-none text-sm" />
          </div>

          <!-- Divider -->
          <div class="w-px h-6 bg-gray-300"></div>

          <!-- Dropdown Button -->
          <button id="navSearchDropdownBtn" type="button" class="flex items-center gap-2 px-4 py-2 cursor-pointer hover:bg-gray-50 transition-colors rounded-r-full">
            <span id="navSearchType" class="text-sm font-medium text-gray-700">Talent</span>
            <i class="fas fa-chevron-down text-gray-400 text-xs"></i>
          </button>

          <!-- Dropdown Menu -->
          <div id="navSearchDropdown" class="hidden absolute top-full left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-lg z-50 overflow-hidden">
            <button type="button" class="w-full text-left px-4 py-3 text-sm text-gray-700 hover:bg-gray-100 transition-colors font-medium" data-type="talent" data-placeholder="Search for talent...">
              Talent
            </button>
            <button type="button" class="w-full text-left px-4 py-3 text-sm text-gray-700 hover:bg-gray-100 transition-colors font-medium" data-type="jobs" data-placeholder="Search for jobs...">
              Jobs
            </button>
          </div>
        </div>


        <!-- Auth buttons -->
        <div class="hidden lg:flex items-center gap-3">
          <a href="auth/login.php" class="text-sm font-semibold text-gray-600 hover:text-gray-900 border border-gray-200 hover:border-primary px-4 py-2 rounded-lg transition-all">Log In</a>
          <a href="auth/register.php" class="btn-grad text-sm font-semibold text-white px-5 py-2 rounded-lg shadow-lg shadow-blue-500/25">Sign Up</a>
        </div>
      </div>
      <!-- Hamburger -->
      <button id="hamburger" class="lg:hidden text-gray-600 hover:text-gray-900 p-2" aria-label="Toggle menu">
        <i class="fas fa-bars text-xl"></i>
      </button>
    </div>

    <!-- Mobile Menu -->
    <div id="mobile-menu" class="lg:hidden bg-white mx-4 mt-2 rounded-2xl shadow-xl border border-gray-100">
      <div class="flex flex-col gap-1 p-4 text-sm font-medium text-gray-600">
        <a href="#hero" class="hover:text-gray-900 py-2 px-3 rounded-lg hover:bg-gray-50 transition-colors">🏠 Home</a>
        <a href="#jobs" class="hover:text-gray-900 py-2 px-3 rounded-lg hover:bg-gray-50 transition-colors">💼 Find Work</a>
        <a href="#freelancers" class="hover:text-gray-900 py-2 px-3 rounded-lg hover:bg-gray-50 transition-colors">👥 Find Freelancers</a>
        <a href="#howitworks" class="hover:text-gray-900 py-2 px-3 rounded-lg hover:bg-gray-50 transition-colors">⚙️ How It Works</a>
        <a href="#about" class="hover:text-gray-900 py-2 px-3 rounded-lg hover:bg-gray-50 transition-colors">ℹ️ About Us</a>
        <a href="#pricing" class="hover:text-gray-900 py-2 px-3 rounded-lg hover:bg-gray-50 transition-colors">💰 Pricing</a>
        <!-- <a href="#footer" class="hover:text-gray-900 py-2 px-3 rounded-lg hover:bg-gray-50 transition-colors">📬 Contact</a> -->
        <hr class="border-gray-100 my-1" />
        <a href="auth/login.php" class="hover:text-gray-900 py-2 px-3 rounded-lg hover:bg-gray-50 transition-colors">🔑 Log In</a>
        <a href="auth/register.php" class="btn-grad text-white text-center py-2 px-3 rounded-lg mt-1">🚀 Sign Up</a>
      </div>
    </div>
  </nav>


  <!-- ═══════════════════════ 2. HERO ══════════════════════════════════ -->
  <section id="hero" class="relative min-h-[85vh] flex items-center overflow-hidden pt-32 bg-gradient-to-br from-blue-50 via-white to-cyan-50">
    <!-- Background decorations -->
    <div class="orb w-[500px] h-[500px] bg-blue-400 top-0 -right-40" style="animation-delay:0s"></div>
    <div class="orb w-[400px] h-[400px] bg-cyan-300 bottom-0 -left-32" style="animation-delay:2s"></div>
    <div class="orb w-[300px] h-[300px] bg-indigo-300 top-1/3 left-1/2" style="animation-delay:4s"></div>

    <!-- Grid pattern overlay -->
    <div class="absolute inset-0 opacity-[0.03]" style="background-image:radial-gradient(circle,#2563eb 1px,transparent 1px);background-size:24px 24px;"></div>

    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 w-full">
      <div class="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">

        <!-- Left Column - Content -->
        <div class="text-center lg:text-left">

          <!-- Headline -->
          <h1 class="text-3xl sm:text-4xl lg:text-5xl font-black leading-relaxed mb-8 text-gray-900">
            Find the Perfect
            <span class="grad-text"> Freelancer</span>
            <br />for Your Project
          </h1>

          <!-- Subtitle -->
          <p class="text-md sm:text-lg text-gray-500 max-w-xl mb-10 leading-relaxed">
            Connect with top-tier professionals across 50+ skills. Post your project, receive proposals in hours, and hire with confidence.
          </p>

          <!-- Search Bar -->
          <div class="relative flex items-center max-w-2xl mx-auto lg:mx-0 mb-10 shadow-lg shadow-gray-200/50 border border-gray-200 rounded-full bg-white overflow-visible">
            <div class="flex items-center gap-2 px-4 py-3 flex-1">
              <i class="fas fa-search text-gray-400 text-sm"></i>
              <input id="hero-search" type="text" placeholder="Search for talent..."
                class="bg-transparent flex-1 text-gray-900 placeholder-gray-400 outline-none text-sm" />
            </div>

            <!-- Divider -->
            <div class="w-px h-6 bg-gray-300"></div>

            <!-- Dropdown Button -->
            <button id="heroSearchDropdownBtn" type="button" class="flex items-center gap-2 px-4 py-3 cursor-pointer hover:bg-gray-50 transition-colors rounded-r-full">
              <span id="heroSearchType" class="text-sm font-medium text-gray-700">Talent</span>
              <i class="fas fa-chevron-down text-gray-400 text-xs"></i>
            </button>

            <!-- Dropdown Menu -->
            <div id="heroSearchDropdown" class="hidden absolute top-full left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-lg z-50 overflow-hidden">
              <button type="button" class="w-full text-left px-4 py-3 text-sm text-gray-700 hover:bg-gray-100 transition-colors font-medium" data-type="talent" data-placeholder="Search for talent...">
                Talent
              </button>
              <button type="button" class="w-full text-left px-4 py-3 text-sm text-gray-700 hover:bg-gray-100 transition-colors font-medium" data-type="jobs" data-placeholder="Search for jobs...">
                Jobs
              </button>
            </div>
          </div>

          <!-- CTA Buttons -->
          <div class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start rounded-full">
            <a href="auth/register.php?role=client" class="bg-blue-600 backdrop-blur-md text-white font-semibold px-10 py-4 rounded-full text-base  hover:bg-blue-500 transition-all inline-flex items-center justify-center gap-2 ">
              Hire Freelancer
            </a>
            <a href="auth/register.php?role=freelancer" class="bg-blue-600 backdrop-blur-md text-white font-semibold px-10 py-4 rounded-full text-base border border-white/40 hover:bg-blue-500 transition-all inline-flex items-center justify-center gap-2 ">
              Find Work
            </a>
          </div>
        </div>

        <!-- Right Column - Visual -->
        <div class="hidden lg:block relative w-full max-w-xl mx-auto">

          <!-- Main image asset -->
          <img src="assets/upload/logos/download.png" alt="Freelancer" class="w-full h-auto object-contain block mx-auto">

          <!-- Left Side Badge ($4,500 Deposited) -->
          <!-- Shifted down to "top-[45%]" to sit right next to her arm/elbow exactly like your layout image -->
          <div class="absolute top-[45%] left-2 bg-white rounded-2xl p-3 shadow-xl border border-gray-100 flex items-center gap-3 max-w-[210px] transform -translate-y-1/2">
            <div class="w-8 h-8 rounded-full bg-blue-50 flex items-center justify-center shrink-0">
              <i class="fa-solid fa-credit-card text-blue-600 text-xs"></i>
            </div>
            <div>
              <p class="font-bold text-gray-900 text-xs tracking-tight leading-none mb-1">
                $4,500 Deposited
              </p>
              <p class="text-gray-400 text-[10px] leading-tight font-medium">
                Escrow secured for frontend build
              </p>
            </div>
          </div>

          <!-- Bottom Right Card (Find your next big contract) -->
          <!-- Locked precisely to the bottom right zone overlapping the lower legs/feet area -->
          <div class="absolute bottom-6 -right-6 bg-white rounded-2xl p-3.5 shadow-xl border border-gray-100 max-w-[240px]">
            <div class="flex flex-col gap-2">
              <!-- Top Row: Icon & Headline -->
              <div class="flex items-center gap-2">
                <div class="w-5 h-5 rounded-full bg-emerald-50 flex items-center justify-center shrink-0">
                  <i class="fas fa-briefcase text-emerald-600 text-[9px]"></i>
                </div>
                <h4 class="font-bold text-gray-900 text-xs tracking-tight leading-tight">
                  Find your next big contract
                </h4>
              </div>

              <!-- Middle Row: Sub-text -->
              <p class="text-gray-400 text-[10px] leading-normal pl-7">
                Connect with top global companies hiring now.
              </p>

              <!-- Bottom Row: Avatar Row + Milestone Tag Stacked -->
              <div class="pl-7 pt-1 flex flex-col gap-2 border-t border-gray-50 mt-1">

                <!-- milestone text badge -->
                <span class="inline-flex items-center gap-1 text-[9px] font-semibold text-emerald-600">
                  <span class="w-1 h-1 rounded-full bg-emerald-500 animate-pulse"></span>
                  Paid via milestones
                </span>
              </div>
            </div>
          </div>

        </div>

      </div>
    </div>
  </section>

  <section id="stats" class="py-10 px-4 reveal w-full flex justify-center items-center">
    <!-- Stats row -->
    <div class="flex items-center justify-center lg:justify-start gap-8 mt-8 pt-8 border-t border-gray-100">
      <div>
        <p class="text-2xl font-black text-gray-900">5,000+</p>
        <p class="text-xs text-gray-400 font-medium">Freelancers</p>
      </div>
      <div class="w-px h-10 bg-gray-200"></div>
      <div>
        <p class="text-2xl font-black text-gray-900">12,000+</p>
        <p class="text-xs text-gray-400 font-medium">Projects Done</p>
      </div>
      <div class="w-px h-10 bg-gray-200"></div>
      <div>
        <p class="text-2xl font-black text-gray-900">$8M+</p>
        <p class="text-xs text-gray-400 font-medium">Paid to Freelancers</p>
      </div>
      <div>
        <p class="text-2xl font-black text-gray-900">5,000+</p>
        <p class="text-xs text-gray-400 font-medium">Freelancers</p>
      </div>
      <div class="w-px h-10 bg-gray-200"></div>
      <div>
        <p class="text-2xl font-black text-gray-900">12,000+</p>
        <p class="text-xs text-gray-400 font-medium">Projects Done</p>
      </div>
      <div class="w-px h-10 bg-gray-200"></div>
      <div>
        <p class="text-2xl font-black text-gray-900">$8M+</p>
        <p class="text-xs text-gray-400 font-medium">Paid to Freelancers</p>
      </div>
    </div>

  </section>
  <!-- ═══════════════════════ 3. CATEGORIES ════════════════════════════ -->
  <section id="categories" class="py-20 px-4 reveal">
    <div class="max-w-7xl mx-auto">
      <div class="text-center mb-14">
        <span class="text-xs font-bold uppercase tracking-widest text-blue-600/70 bg-blue-50 px-3 py-1 rounded-full">Browse Skills</span>
        <h2 class="text-4xl font-extrabold mt-4 mb-3 text-gray-900">Popular <span class="grad-text">IT Categories</span></h2>
        <p class="text-gray-500 max-w-xl mx-auto">From web development to cutting-edge AI — find the expertise you need.</p>
      </div>

      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-5">
        <!-- Card template -->
        <div class="cat-card bg-white rounded-2xl p-6 cursor-pointer text-center group border border-gray-100 shadow-sm">
          <div class="w-14 h-14 mx-auto mb-4 rounded-2xl bg-blue-50 flex items-center justify-center group-hover:bg-blue-100 transition-colors">
            <i class="fas fa-code text-blue-600 text-2xl"></i>
          </div>
          <h3 class="font-bold text-gray-900 text-sm mb-1">Web Development</h3>
          <p class="text-gray-400 text-xs">1,240 jobs</p>
        </div>
        <div class="cat-card bg-white rounded-2xl p-6 cursor-pointer text-center group border border-gray-100 shadow-sm">
          <div class="w-14 h-14 mx-auto mb-4 rounded-2xl bg-cyan-50 flex items-center justify-center group-hover:bg-cyan-100 transition-colors">
            <i class="fas fa-mobile-alt text-cyan-600 text-2xl"></i>
          </div>
          <h3 class="font-bold text-gray-900 text-sm mb-1">Mobile Development</h3>
          <p class="text-gray-400 text-xs">820 jobs</p>
        </div>
        <div class="cat-card bg-white rounded-2xl p-6 cursor-pointer text-center group border border-gray-100 shadow-sm">
          <div class="w-14 h-14 mx-auto mb-4 rounded-2xl bg-pink-50 flex items-center justify-center group-hover:bg-pink-100 transition-colors">
            <i class="fas fa-palette text-pink-600 text-2xl"></i>
          </div>
          <h3 class="font-bold text-gray-900 text-sm mb-1">Graphic Design</h3>
          <p class="text-gray-400 text-xs">940 jobs</p>
        </div>
        <div class="cat-card bg-white rounded-2xl p-6 cursor-pointer text-center group border border-gray-100 shadow-sm">
          <div class="w-14 h-14 mx-auto mb-4 rounded-2xl bg-violet-50 flex items-center justify-center group-hover:bg-violet-100 transition-colors">
            <i class="fas fa-brain text-violet-600 text-2xl"></i>
          </div>
          <h3 class="font-bold text-gray-900 text-sm mb-1">AI / ML</h3>
          <p class="text-gray-400 text-xs">560 jobs</p>
        </div>
        <div class="cat-card bg-white rounded-2xl p-6 cursor-pointer text-center group border border-gray-100 shadow-sm">
          <div class="w-14 h-14 mx-auto mb-4 rounded-2xl bg-emerald-50 flex items-center justify-center group-hover:bg-emerald-100 transition-colors">
            <i class="fas fa-chart-pie text-emerald-600 text-2xl"></i>
          </div>
          <h3 class="font-bold text-gray-900 text-sm mb-1">Data Science</h3>
          <p class="text-gray-400 text-xs">430 jobs</p>
        </div>
        <div class="cat-card bg-white rounded-2xl p-6 cursor-pointer text-center group border border-gray-100 shadow-sm">
          <div class="w-14 h-14 mx-auto mb-4 rounded-2xl bg-red-50 flex items-center justify-center group-hover:bg-red-100 transition-colors">
            <i class="fas fa-shield-alt text-red-600 text-2xl"></i>
          </div>
          <h3 class="font-bold text-gray-900 text-sm mb-1">Cyber Security</h3>
          <p class="text-gray-400 text-xs">310 jobs</p>
        </div>
        <div class="cat-card bg-white rounded-2xl p-6 cursor-pointer text-center group border border-gray-100 shadow-sm">
          <div class="w-14 h-14 mx-auto mb-4 rounded-2xl bg-orange-50 flex items-center justify-center group-hover:bg-orange-100 transition-colors">
            <i class="fab fa-aws text-orange-600 text-2xl"></i>
          </div>
          <h3 class="font-bold text-gray-900 text-sm mb-1">DevOps & Cloud</h3>
          <p class="text-gray-400 text-xs">275 jobs</p>
        </div>
        <div class="cat-card bg-white rounded-2xl p-6 cursor-pointer text-center group border border-gray-100 shadow-sm">
          <div class="w-14 h-14 mx-auto mb-4 rounded-2xl bg-teal-50 flex items-center justify-center group-hover:bg-teal-100 transition-colors">
            <i class="fas fa-database text-teal-600 text-2xl"></i>
          </div>
          <h3 class="font-bold text-gray-900 text-sm mb-1">Database & SQL</h3>
          <p class="text-gray-400 text-xs">380 jobs</p>
        </div>
        <div class="cat-card bg-white rounded-2xl p-6 cursor-pointer text-center group border border-gray-100 shadow-sm">
          <div class="w-14 h-14 mx-auto mb-4 rounded-2xl bg-sky-50 flex items-center justify-center group-hover:bg-sky-100 transition-colors">
            <i class="fas fa-robot text-sky-600 text-2xl"></i>
          </div>
          <h3 class="font-bold text-gray-900 text-sm mb-1">Automation / RPA</h3>
          <p class="text-gray-400 text-xs">198 jobs</p>
        </div>
        <div class="cat-card bg-white rounded-2xl p-6 cursor-pointer text-center group border border-gray-100 shadow-sm">
          <div class="w-14 h-14 mx-auto mb-4 rounded-2xl bg-yellow-50 flex items-center justify-center group-hover:bg-yellow-100 transition-colors">
            <i class="fas fa-gamepad text-yellow-600 text-2xl"></i>
          </div>
          <h3 class="font-bold text-gray-900 text-sm mb-1">Game Development</h3>
          <p class="text-gray-400 text-xs">150 jobs</p>
        </div>
      </div>
    </div>
  </section>


  <!-- ═══════════════════════ 4. FEATURED JOBS ═════════════════════════ -->
  <section id="jobs" class="py-20 px-4 reveal bg-gray-50">
    <div class="max-w-7xl mx-auto">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-12 gap-4">
        <div>
          <span class="text-xs font-bold uppercase tracking-widest text-cyan-600/70 bg-cyan-50 px-3 py-1 rounded-full">Latest Listings</span>
          <h2 class="text-4xl font-extrabold mt-4 text-gray-900">Featured <span class="grad-text">Jobs</span></h2>
        </div>
        <a href="jobs/browse.php" class="btn-grad text-white font-semibold px-6 py-3 rounded-xl text-sm self-start sm:self-center shadow-lg shadow-blue-500/20">Browse All Jobs →</a>
      </div>

      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">

        <!-- Job Card 1 -->
        <div class="job-card bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
          <div class="flex items-start justify-between mb-4">
            <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center">
              <i class="fas fa-shopping-cart text-blue-600 text-lg"></i>
            </div>
            <span class="text-xs font-bold bg-green-50 text-green-600 px-3 py-1 rounded-full">Open</span>
          </div>
          <h3 class="font-bold text-gray-900 text-lg mb-2">Build E-Commerce Website</h3>
          <p class="text-gray-500 text-sm mb-4 leading-relaxed">Full-featured online store with product management, cart, payment gateway integration (Stripe/PayPal), and admin panel.</p>
          <div class="flex flex-wrap gap-2 mb-5">
            <span class="text-xs bg-blue-50 text-blue-600 px-2 py-1 rounded-md font-medium">PHP</span>
            <span class="text-xs bg-blue-50 text-blue-600 px-2 py-1 rounded-md font-medium">MySQL</span>
            <span class="text-xs bg-blue-50 text-blue-600 px-2 py-1 rounded-md font-medium">React</span>
            <span class="text-xs bg-blue-50 text-blue-600 px-2 py-1 rounded-md font-medium">Tailwind</span>
          </div>
          <div class="border-t border-gray-100 pt-4 flex items-center justify-between">
            <div>
              <p class="text-xs text-gray-400 mb-1">Budget</p>
              <p class="text-xl font-black text-gray-900">$500</p>
            </div>
            <div class="text-right">
              <p class="text-xs text-gray-400 mb-1">Type</p>
              <p class="text-sm font-semibold text-cyan-600">Fixed Price</p>
            </div>
            <a href="jobs/view.php?id=1" class="btn-grad text-white text-sm font-semibold px-4 py-2 rounded-lg">Apply</a>
          </div>
          <p class="text-xs text-gray-400 mt-3"><i class="fas fa-clock mr-1"></i>Posted 2 hours ago · 8 proposals</p>
        </div>

        <!-- Job Card 2 -->
        <div class="job-card bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
          <div class="flex items-start justify-between mb-4">
            <div class="w-12 h-12 rounded-xl bg-cyan-50 flex items-center justify-center">
              <i class="fab fa-android text-cyan-600 text-lg"></i>
            </div>
            <span class="text-xs font-bold bg-green-50 text-green-600 px-3 py-1 rounded-full">Open</span>
          </div>
          <h3 class="font-bold text-gray-900 text-lg mb-2">Android App Development</h3>
          <p class="text-gray-500 text-sm mb-4 leading-relaxed">Native Android delivery tracking app with real-time GPS, push notifications, Firebase backend, and driver/customer views.</p>
          <div class="flex flex-wrap gap-2 mb-5">
            <span class="text-xs bg-cyan-50 text-cyan-600 px-2 py-1 rounded-md font-medium">Kotlin</span>
            <span class="text-xs bg-cyan-50 text-cyan-600 px-2 py-1 rounded-md font-medium">Firebase</span>
            <span class="text-xs bg-cyan-50 text-cyan-600 px-2 py-1 rounded-md font-medium">GPS API</span>
          </div>
          <div class="border-t border-gray-100 pt-4 flex items-center justify-between">
            <div>
              <p class="text-xs text-gray-400 mb-1">Budget</p>
              <p class="text-xl font-black text-gray-900">$1,000</p>
            </div>
            <div class="text-right">
              <p class="text-xs text-gray-400 mb-1">Type</p>
              <p class="text-sm font-semibold text-cyan-600">Fixed Price</p>
            </div>
            <a href="jobs/view.php?id=2" class="btn-grad text-white text-sm font-semibold px-4 py-2 rounded-lg">Apply</a>
          </div>
          <p class="text-xs text-gray-400 mt-3"><i class="fas fa-clock mr-1"></i>Posted 5 hours ago · 14 proposals</p>
        </div>

        <!-- Job Card 3 -->
        <div class="job-card bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
          <div class="flex items-start justify-between mb-4">
            <div class="w-12 h-12 rounded-xl bg-violet-50 flex items-center justify-center">
              <i class="fas fa-brain text-violet-600 text-lg"></i>
            </div>
            <span class="text-xs font-bold bg-green-50 text-green-600 px-3 py-1 rounded-full">Open</span>
          </div>
          <h3 class="font-bold text-gray-900 text-lg mb-2">ML Chatbot Integration</h3>
          <p class="text-gray-500 text-sm mb-4 leading-relaxed">Train and integrate a customer-support chatbot using OpenAI API, with context memory and CRM data retrieval.</p>
          <div class="flex flex-wrap gap-2 mb-5">
            <span class="text-xs bg-violet-50 text-violet-600 px-2 py-1 rounded-md font-medium">Python</span>
            <span class="text-xs bg-violet-50 text-violet-600 px-2 py-1 rounded-md font-medium">OpenAI</span>
            <span class="text-xs bg-violet-50 text-violet-600 px-2 py-1 rounded-md font-medium">FastAPI</span>
          </div>
          <div class="border-t border-gray-100 pt-4 flex items-center justify-between">
            <div>
              <p class="text-xs text-gray-400 mb-1">Budget</p>
              <p class="text-xl font-black text-gray-900">$35/hr</p>
            </div>
            <div class="text-right">
              <p class="text-xs text-gray-400 mb-1">Type</p>
              <p class="text-sm font-semibold text-purple-600">Hourly</p>
            </div>
            <a href="jobs/view.php?id=3" class="btn-grad text-white text-sm font-semibold px-4 py-2 rounded-lg">Apply</a>
          </div>
          <p class="text-xs text-gray-400 mt-3"><i class="fas fa-clock mr-1"></i>Posted 1 day ago · 22 proposals</p>
        </div>

        <!-- Job Card 4 -->
        <div class="job-card bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
          <div class="flex items-start justify-between mb-4">
            <div class="w-12 h-12 rounded-xl bg-pink-50 flex items-center justify-center">
              <i class="fas fa-paint-brush text-pink-600 text-lg"></i>
            </div>
            <span class="text-xs font-bold bg-green-50 text-green-600 px-3 py-1 rounded-full">Open</span>
          </div>
          <h3 class="font-bold text-gray-900 text-lg mb-2">SaaS Dashboard UI Design</h3>
          <p class="text-gray-500 text-sm mb-4 leading-relaxed">Design a modern analytics dashboard with dark/light mode, data visualizations, and a complete Figma component library.</p>
          <div class="flex flex-wrap gap-2 mb-5">
            <span class="text-xs bg-pink-50 text-pink-600 px-2 py-1 rounded-md font-medium">Figma</span>
            <span class="text-xs bg-pink-50 text-pink-600 px-2 py-1 rounded-md font-medium">UI/UX</span>
            <span class="text-xs bg-pink-50 text-pink-600 px-2 py-1 rounded-md font-medium">Prototyping</span>
          </div>
          <div class="border-t border-gray-100 pt-4 flex items-center justify-between">
            <div>
              <p class="text-xs text-gray-400 mb-1">Budget</p>
              <p class="text-xl font-black text-gray-900">$750</p>
            </div>
            <div class="text-right">
              <p class="text-xs text-gray-400 mb-1">Type</p>
              <p class="text-sm font-semibold text-cyan-600">Fixed Price</p>
            </div>
            <a href="jobs/view.php?id=4" class="btn-grad text-white text-sm font-semibold px-4 py-2 rounded-lg">Apply</a>
          </div>
          <p class="text-xs text-gray-400 mt-3"><i class="fas fa-clock mr-1"></i>Posted 3 hours ago · 5 proposals</p>
        </div>

        <!-- Job Card 5 -->
        <div class="job-card bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
          <div class="flex items-start justify-between mb-4">
            <div class="w-12 h-12 rounded-xl bg-red-50 flex items-center justify-center">
              <i class="fas fa-shield-alt text-red-600 text-lg"></i>
            </div>
            <span class="text-xs font-bold bg-yellow-50 text-yellow-600 px-3 py-1 rounded-full">Urgent</span>
          </div>
          <h3 class="font-bold text-gray-900 text-lg mb-2">Penetration Testing Report</h3>
          <p class="text-gray-500 text-sm mb-4 leading-relaxed">Full security audit of a web application — OWASP Top 10 vulnerabilities, API security, and final penetration test report.</p>
          <div class="flex flex-wrap gap-2 mb-5">
            <span class="text-xs bg-red-50 text-red-600 px-2 py-1 rounded-md font-medium">Burp Suite</span>
            <span class="text-xs bg-red-50 text-red-600 px-2 py-1 rounded-md font-medium">OWASP</span>
            <span class="text-xs bg-red-50 text-red-600 px-2 py-1 rounded-md font-medium">Kali Linux</span>
          </div>
          <div class="border-t border-gray-100 pt-4 flex items-center justify-between">
            <div>
              <p class="text-xs text-gray-400 mb-1">Budget</p>
              <p class="text-xl font-black text-gray-900">$600</p>
            </div>
            <div class="text-right">
              <p class="text-xs text-gray-400 mb-1">Type</p>
              <p class="text-sm font-semibold text-cyan-600">Fixed Price</p>
            </div>
            <a href="jobs/view.php?id=5" class="btn-grad text-white text-sm font-semibold px-4 py-2 rounded-lg">Apply</a>
          </div>
          <p class="text-xs text-gray-400 mt-3"><i class="fas fa-clock mr-1"></i>Posted 6 hours ago · 3 proposals</p>
        </div>

        <!-- Job Card 6 -->
        <div class="job-card bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
          <div class="flex items-start justify-between mb-4">
            <div class="w-12 h-12 rounded-xl bg-orange-50 flex items-center justify-center">
              <i class="fab fa-aws text-orange-600 text-lg"></i>
            </div>
            <span class="text-xs font-bold bg-green-50 text-green-600 px-3 py-1 rounded-full">Open</span>
          </div>
          <h3 class="font-bold text-gray-900 text-lg mb-2">AWS Cloud Infrastructure Setup</h3>
          <p class="text-gray-500 text-sm mb-4 leading-relaxed">Set up scalable AWS infrastructure using ECS, RDS, CloudFront CDN, S3, and CI/CD pipelines with GitHub Actions.</p>
          <div class="flex flex-wrap gap-2 mb-5">
            <span class="text-xs bg-orange-50 text-orange-600 px-2 py-1 rounded-md font-medium">AWS</span>
            <span class="text-xs bg-orange-50 text-orange-600 px-2 py-1 rounded-md font-medium">Docker</span>
            <span class="text-xs bg-orange-50 text-orange-600 px-2 py-1 rounded-md font-medium">Terraform</span>
          </div>
          <div class="border-t border-gray-100 pt-4 flex items-center justify-between">
            <div>
              <p class="text-xs text-gray-400 mb-1">Budget</p>
              <p class="text-xl font-black text-gray-900">$45/hr</p>
            </div>
            <div class="text-right">
              <p class="text-xs text-gray-400 mb-1">Type</p>
              <p class="text-sm font-semibold text-purple-600">Hourly</p>
            </div>
            <a href="jobs/view.php?id=6" class="btn-grad text-white text-sm font-semibold px-4 py-2 rounded-lg">Apply</a>
          </div>
          <p class="text-xs text-gray-400 mt-3"><i class="fas fa-clock mr-1"></i>Posted 12 hours ago · 9 proposals</p>
        </div>

      </div>
    </div>
  </section>


  <!-- ═══════════════════════ 5. TOP FREELANCERS ═══════════════════════ -->
  <section id="freelancers" class="py-20 px-4 reveal">
    <div class="max-w-7xl mx-auto">
      <div class="text-center mb-14">
        <span class="text-xs font-bold uppercase tracking-widest text-purple-600/70 bg-purple-50 px-3 py-1 rounded-full">Expert Talent</span>
        <h2 class="text-4xl font-extrabold mt-4 mb-3 text-gray-900">Top <span class="grad-text">Freelancers</span></h2>
        <p class="text-gray-500 max-w-xl mx-auto">Handpicked professionals with proven track records and top client satisfaction.</p>
      </div>

      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">

        <!-- Freelancer 1 -->
        <div class="fl-card bg-white rounded-2xl p-6 text-center border border-gray-100 shadow-sm">
          <div class="relative inline-block mb-4">
            <div class="w-20 h-20 mx-auto rounded-full overflow-hidden ring-2 ring-blue-200 ring-offset-2 ring-offset-white">
              <svg viewBox="0 0 80 80" xmlns="http://www.w3.org/2000/svg" class="w-full h-full">
                <rect width="80" height="80" fill="#eff6ff" />
                <circle cx="40" cy="30" r="16" fill="#3b82f6" />
                <ellipse cx="40" cy="72" rx="26" ry="20" fill="#3b82f6" />
                <circle cx="40" cy="29" r="13" fill="#fde68a" />
                <rect x="27" y="27" width="26" height="14" rx="7" fill="#1e3a5f" />
                <circle cx="35" cy="33" r="3" fill="#fff" />
                <circle cx="45" cy="33" r="3" fill="#fff" />
              </svg>
            </div>
            <span class="absolute -bottom-1 -right-1 w-5 h-5 bg-green-500 rounded-full border-2 border-white"></span>
          </div>
          <h3 class="font-bold text-gray-900 text-base">Arjun Patel</h3>
          <p class="text-blue-600 text-xs font-medium mb-3">Full Stack Developer</p>
          <div class="flex flex-wrap gap-1 justify-center mb-4">
            <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-md">PHP</span>
            <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-md">React</span>
            <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-md">MySQL</span>
          </div>
          <div class="flex items-center justify-center gap-1 mb-1 stars text-sm">
            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i>
          </div>
          <p class="text-xs text-gray-400 mb-3">4.8 (126 reviews)</p>
          <p class="text-lg font-black text-gray-900">$45<span class="text-xs font-normal text-gray-400">/hr</span></p>
          <a href="freelancers/profile.php?id=1" class="mt-4 block btn-grad text-white text-sm font-semibold py-2 rounded-xl">View Profile</a>
        </div>

        <!-- Freelancer 2 -->
        <div class="fl-card bg-white rounded-2xl p-6 text-center border border-gray-100 shadow-sm relative">
          <div class="absolute top-3 right-3 text-xs font-bold bg-yellow-50 text-yellow-600 px-2 py-1 rounded-full"><i class="fas fa-crown mr-1"></i>Top Rated</div>
          <div class="relative inline-block mb-4">
            <div class="w-20 h-20 mx-auto rounded-full overflow-hidden ring-2 ring-yellow-200 ring-offset-2 ring-offset-white">
              <svg viewBox="0 0 80 80" xmlns="http://www.w3.org/2000/svg" class="w-full h-full">
                <rect width="80" height="80" fill="#fef3c7" />
                <circle cx="40" cy="30" r="16" fill="#2dd4bf" />
                <ellipse cx="40" cy="72" rx="26" ry="20" fill="#2dd4bf" />
                <circle cx="40" cy="29" r="13" fill="#f97316" />
                <circle cx="35" cy="31" r="3" fill="#1a3340" />
                <circle cx="45" cy="31" r="3" fill="#1a3340" />
                <path d="M34 37 Q40 42 46 37" stroke="#1a3340" stroke-width="2" fill="none" />
              </svg>
            </div>
            <span class="absolute -bottom-1 -right-1 w-5 h-5 bg-green-500 rounded-full border-2 border-white"></span>
          </div>
          <h3 class="font-bold text-gray-900 text-base">Amara Osei</h3>
          <p class="text-cyan-600 text-xs font-medium mb-3">UI/UX Designer</p>
          <div class="flex flex-wrap gap-1 justify-center mb-4">
            <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-md">Figma</span>
            <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-md">Adobe XD</span>
            <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-md">Webflow</span>
          </div>
          <div class="flex items-center justify-center gap-1 mb-1 stars text-sm">
            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
          </div>
          <p class="text-xs text-gray-400 mb-3">5.0 (204 reviews)</p>
          <p class="text-lg font-black text-gray-900">$60<span class="text-xs font-normal text-gray-400">/hr</span></p>
          <a href="freelancers/profile.php?id=2" class="mt-4 block btn-grad text-white text-sm font-semibold py-2 rounded-xl">View Profile</a>
        </div>

        <!-- Freelancer 3 -->
        <div class="fl-card bg-white rounded-2xl p-6 text-center border border-gray-100 shadow-sm">
          <div class="relative inline-block mb-4">
            <div class="w-20 h-20 mx-auto rounded-full overflow-hidden ring-2 ring-purple-200 ring-offset-2 ring-offset-white">
              <svg viewBox="0 0 80 80" xmlns="http://www.w3.org/2000/svg" class="w-full h-full">
                <rect width="80" height="80" fill="#f5f3ff" />
                <circle cx="40" cy="30" r="16" fill="#7c3aed" />
                <ellipse cx="40" cy="72" rx="26" ry="20" fill="#7c3aed" />
                <circle cx="40" cy="29" r="13" fill="#dbeafe" />
                <circle cx="35" cy="31" r="3" fill="#1e1b4b" />
                <circle cx="45" cy="31" r="3" fill="#1e1b4b" />
                <rect x="28" y="17" width="24" height="12" rx="6" fill="#7c3aed" />
              </svg>
            </div>
            <span class="absolute -bottom-1 -right-1 w-5 h-5 bg-yellow-400 rounded-full border-2 border-white"></span>
          </div>
          <h3 class="font-bold text-gray-900 text-base">Marcus Weber</h3>
          <p class="text-purple-600 text-xs font-medium mb-3">Data Scientist / ML</p>
          <div class="flex flex-wrap gap-1 justify-center mb-4">
            <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-md">Python</span>
            <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-md">TensorFlow</span>
            <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-md">SQL</span>
          </div>
          <div class="flex items-center justify-center gap-1 mb-1 stars text-sm">
            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
          </div>
          <p class="text-xs text-gray-400 mb-3">4.9 (88 reviews)</p>
          <p class="text-lg font-black text-gray-900">$80<span class="text-xs font-normal text-gray-400">/hr</span></p>
          <a href="freelancers/profile.php?id=3" class="mt-4 block btn-grad text-white text-sm font-semibold py-2 rounded-xl">View Profile</a>
        </div>

        <!-- Freelancer 4 -->
        <div class="fl-card bg-white rounded-2xl p-6 text-center border border-gray-100 shadow-sm">
          <div class="relative inline-block mb-4">
            <div class="w-20 h-20 mx-auto rounded-full overflow-hidden ring-2 ring-cyan-200 ring-offset-2 ring-offset-white">
              <svg viewBox="0 0 80 80" xmlns="http://www.w3.org/2000/svg" class="w-full h-full">
                <rect width="80" height="80" fill="#ecfeff" />
                <circle cx="40" cy="30" r="16" fill="#06b6d4" />
                <ellipse cx="40" cy="72" rx="26" ry="20" fill="#06b6d4" />
                <circle cx="40" cy="29" r="13" fill="#fca5a5" />
                <circle cx="35" cy="31" r="3" fill="#0c1a2e" />
                <circle cx="45" cy="31" r="3" fill="#0c1a2e" />
                <path d="M34 38 Q40 44 46 38" stroke="#0c1a2e" stroke-width="2" fill="none" />
                <path d="M28 20 Q40 14 52 20" stroke="#333" stroke-width="4" fill="#1a1a2e" />
              </svg>
            </div>
            <span class="absolute -bottom-1 -right-1 w-5 h-5 bg-green-500 rounded-full border-2 border-white"></span>
          </div>
          <h3 class="font-bold text-gray-900 text-base">Yuki Tanaka</h3>
          <p class="text-cyan-600 text-xs font-medium mb-3">Mobile Developer</p>
          <div class="flex flex-wrap gap-1 justify-center mb-4">
            <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-md">Flutter</span>
            <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-md">Swift</span>
            <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-md">Firebase</span>
          </div>
          <div class="flex items-center justify-center gap-1 mb-1 stars text-sm">
            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i>
          </div>
          <p class="text-xs text-gray-400 mb-3">4.7 (155 reviews)</p>
          <p class="text-lg font-black text-gray-900">$55<span class="text-xs font-normal text-gray-400">/hr</span></p>
          <a href="freelancers/profile.php?id=4" class="mt-4 block btn-grad text-white text-sm font-semibold py-2 rounded-xl">View Profile</a>
        </div>

      </div>

      <div class="text-center mt-10">
        <a href="freelancers/browse.php" class="inline-flex items-center gap-2 text-gray-500 hover:text-gray-900 font-semibold transition-colors border border-gray-200 hover:border-primary px-6 py-3 rounded-xl bg-white">
          Browse All Freelancers <i class="fas fa-arrow-right text-sm"></i>
        </a>
      </div>
    </div>
  </section>


  <!-- ═══════════════════════ 6. HOW IT WORKS ══════════════════════════ -->
  <section id="howitworks" class="py-20 px-4 reveal bg-gray-50">
    <div class="max-w-7xl mx-auto">
      <div class="text-center mb-14">
        <span class="text-xs font-bold uppercase tracking-widest text-green-600/70 bg-green-50 px-3 py-1 rounded-full">Process</span>
        <h2 class="text-4xl font-extrabold mt-4 mb-3 text-gray-900">How It <span class="grad-text">Works</span></h2>
        <p class="text-gray-500 max-w-xl mx-auto">A streamlined process for both clients and freelancers.</p>
      </div>

      <!-- Tabs -->
      <div class="flex justify-center mb-10">
        <div class="bg-white rounded-2xl p-1 flex gap-2 shadow-sm border border-gray-100">
          <button id="tab-client" onclick="switchTab('client')" class="tab-btn active text-sm font-bold px-6 py-3 rounded-xl transition-all"><i class="fas fa-user-tie mr-2"></i>For Clients</button>
          <button id="tab-freelancer" onclick="switchTab('freelancer')" class="tab-btn text-sm font-bold px-6 py-3 rounded-xl text-gray-400 hover:text-gray-900 transition-all"><i class="fas fa-laptop-code mr-2"></i>For Freelancers</button>
        </div>
      </div>

      <!-- Client Steps -->
      <div id="panel-client" class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white rounded-2xl p-7 text-center relative border border-gray-100 shadow-sm">
          <div class="absolute -top-3 left-1/2 -translate-x-1/2 w-7 h-7 rounded-full bg-blue-600 flex items-center justify-center text-xs font-black text-white">1</div>
          <div class="w-16 h-16 mx-auto mb-5 mt-3 rounded-2xl bg-blue-50 flex items-center justify-center">
            <i class="fas fa-plus-circle text-blue-600 text-2xl"></i>
          </div>
          <h3 class="font-bold text-gray-900 text-base mb-2">Post a Job</h3>
          <p class="text-gray-500 text-sm leading-relaxed">Describe your project, set your budget, and specify the skills you need. Posting is completely free.</p>
        </div>
        <div class="bg-white rounded-2xl p-7 text-center relative border border-gray-100 shadow-sm">
          <div class="absolute -top-3 left-1/2 -translate-x-1/2 w-7 h-7 rounded-full bg-cyan-500 flex items-center justify-center text-xs font-black text-white">2</div>
          <div class="w-16 h-16 mx-auto mb-5 mt-3 rounded-2xl bg-cyan-50 flex items-center justify-center">
            <i class="fas fa-inbox text-cyan-600 text-2xl"></i>
          </div>
          <h3 class="font-bold text-gray-900 text-base mb-2">Receive Proposals</h3>
          <p class="text-gray-500 text-sm leading-relaxed">Talented freelancers submit proposals with their approach and bid. Review portfolios and ratings.</p>
        </div>
        <div class="bg-white rounded-2xl p-7 text-center relative border border-gray-100 shadow-sm">
          <div class="absolute -top-3 left-1/2 -translate-x-1/2 w-7 h-7 rounded-full bg-purple-500 flex items-center justify-center text-xs font-black text-white">3</div>
          <div class="w-16 h-16 mx-auto mb-5 mt-3 rounded-2xl bg-purple-50 flex items-center justify-center">
            <i class="fas fa-handshake text-purple-600 text-2xl"></i>
          </div>
          <h3 class="font-bold text-gray-900 text-base mb-2">Hire Freelancer</h3>
          <p class="text-gray-500 text-sm leading-relaxed">Chat with candidates, compare bids, and hire your ideal freelancer with one click. Contract auto-generated.</p>
        </div>
        <div class="bg-white rounded-2xl p-7 text-center relative border border-gray-100 shadow-sm">
          <div class="absolute -top-3 left-1/2 -translate-x-1/2 w-7 h-7 rounded-full bg-green-500 flex items-center justify-center text-xs font-black text-white">4</div>
          <div class="w-16 h-16 mx-auto mb-5 mt-3 rounded-2xl bg-green-50 flex items-center justify-center">
            <i class="fas fa-paper-plane text-green-600 text-2xl"></i>
          </div>
          <h3 class="font-bold text-gray-900 text-base mb-2">Release Payment</h3>
          <p class="text-gray-500 text-sm leading-relaxed">Funds sit in secure escrow. Release payment only after you approve the completed deliverables.</p>
        </div>
      </div>

      <!-- Freelancer Steps -->
      <div id="panel-freelancer" class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6 hidden">
        <div class="bg-white rounded-2xl p-7 text-center relative border border-gray-100 shadow-sm">
          <div class="absolute -top-3 left-1/2 -translate-x-1/2 w-7 h-7 rounded-full bg-blue-600 flex items-center justify-center text-xs font-black text-white">1</div>
          <div class="w-16 h-16 mx-auto mb-5 mt-3 rounded-2xl bg-blue-50 flex items-center justify-center">
            <i class="fas fa-id-card text-blue-600 text-2xl"></i>
          </div>
          <h3 class="font-bold text-gray-900 text-base mb-2">Create Profile</h3>
          <p class="text-gray-500 text-sm leading-relaxed">Showcase your skills, portfolio, and hourly rate. A compelling profile attracts the best clients.</p>
        </div>
        <div class="bg-white rounded-2xl p-7 text-center relative border border-gray-100 shadow-sm">
          <div class="absolute -top-3 left-1/2 -translate-x-1/2 w-7 h-7 rounded-full bg-cyan-500 flex items-center justify-center text-xs font-black text-white">2</div>
          <div class="w-16 h-16 mx-auto mb-5 mt-3 rounded-2xl bg-cyan-50 flex items-center justify-center">
            <i class="fas fa-file-alt text-cyan-600 text-2xl"></i>
          </div>
          <h3 class="font-bold text-gray-900 text-base mb-2">Submit Proposal</h3>
          <p class="text-gray-500 text-sm leading-relaxed">Browse jobs that match your skills and submit a personalised proposal with your bid and timeline.</p>
        </div>
        <div class="bg-white rounded-2xl p-7 text-center relative border border-gray-100 shadow-sm">
          <div class="absolute -top-3 left-1/2 -translate-x-1/2 w-7 h-7 rounded-full bg-purple-500 flex items-center justify-center text-xs font-black text-white">3</div>
          <div class="w-16 h-16 mx-auto mb-5 mt-3 rounded-2xl bg-purple-50 flex items-center justify-center">
            <i class="fas fa-laptop-code text-purple-600 text-2xl"></i>
          </div>
          <h3 class="font-bold text-gray-900 text-base mb-2">Complete Project</h3>
          <p class="text-gray-500 text-sm leading-relaxed">Work with the client via our built-in chat and milestone system to deliver outstanding results.</p>
        </div>
        <div class="bg-white rounded-2xl p-7 text-center relative border border-gray-100 shadow-sm">
          <div class="absolute -top-3 left-1/2 -translate-x-1/2 w-7 h-7 rounded-full bg-green-500 flex items-center justify-center text-xs font-black text-white">4</div>
          <div class="w-16 h-16 mx-auto mb-5 mt-3 rounded-2xl bg-green-50 flex items-center justify-center">
            <i class="fas fa-wallet text-green-600 text-2xl"></i>
          </div>
          <h3 class="font-bold text-gray-900 text-base mb-2">Get Paid</h3>
          <p class="text-gray-500 text-sm leading-relaxed">Funds are released to your wallet instantly upon client approval. Withdraw anytime via bank or PayPal.</p>
        </div>
      </div>
    </div>
  </section>


  <!-- ═══════════════════════ 7. STATISTICS ════════════════════════════ -->
  <!-- <section id="stats" class="py-20 px-4 reveal">
    <div class="max-w-5xl mx-auto">
      <div class="bg-gradient-to-br from-blue-600 to-cyan-500 rounded-3xl p-10 sm:p-16 relative overflow-hidden">
        bg decoration
        <div class="absolute inset-0 opacity-10" style="background:radial-gradient(ellipse at 20% 50%,#fff 0%,transparent 60%),radial-gradient(ellipse at 80% 50%,#fff 0%,transparent 60%)"></div>
        <div class="relative grid grid-cols-2 lg:grid-cols-4 gap-8 text-center">
          <div>
            <p class="stat-num text-white !bg-none" id="cnt-freelancers" data-target="5000">0</p>
            <p class="text-blue-100 font-medium mt-1">Freelancers</p>
            <p class="text-blue-200/60 text-xs">Active Professionals</p>
          </div>
          <div>
            <p class="stat-num text-white !bg-none" id="cnt-clients" data-target="1000">0</p>
            <p class="text-blue-100 font-medium mt-1">Clients</p>
            <p class="text-blue-200/60 text-xs">Satisfied Partners</p>
          </div>
          <div>
            <p class="stat-num text-white !bg-none" id="cnt-projects" data-target="3000">0</p>
            <p class="text-blue-100 font-medium mt-1">Projects</p>
            <p class="text-blue-200/60 text-xs">Successfully Delivered</p>
          </div>
          <div>
            <p class="stat-num text-white !bg-none" id="cnt-paid" data-target="2">0</p>
            <p class="text-blue-100 font-medium mt-1">Million+ Paid</p>
            <p class="text-blue-200/60 text-xs">To Freelancers (USD)</p>
          </div>
        </div>
      </div>
    </div>
  </section> -->


  <!-- ═══════════════════════ 8. TESTIMONIALS ══════════════════════════ -->
  <section id="testimonials" class="py-20 px-4 reveal bg-gray-50">
    <div class="max-w-7xl mx-auto">
      <div class="text-center mb-14">
        <span class="text-xs font-bold uppercase tracking-widest text-yellow-600/70 bg-yellow-50 px-3 py-1 rounded-full">Reviews</span>
        <h2 class="text-4xl font-extrabold mt-4 mb-3 text-gray-900">What People <span class="grad-text">Say</span></h2>
        <p class="text-gray-500 max-w-xl mx-auto">Real reviews from verified clients and freelancers on our platform.</p>
      </div>

      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">

        <div class="testi-card bg-white rounded-2xl p-7 border border-gray-100 shadow-sm">
          <p class="text-gray-600 text-sm leading-relaxed mb-6">"JobHub transformed how we hire. We built our entire e-commerce platform in 3 weeks — from shortlisting to delivery. Absolutely incredible platform."</p>
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-blue-500 to-cyan-500 flex items-center justify-center text-white font-bold text-sm">JM</div>
            <div>
              <div class="flex justify-start items-center gap-2">
                <p class="font-semibold text-gray-900 text-sm">James Mitchell</p>
                <div class="flex items-center gap-1 stars text-xs">
                  <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                </div>
              </div>
              <p class="text-gray-400 text-xs">CEO, TechNova Inc. · Client</p>
            </div>
          </div>
        </div>

        <div class="testi-card bg-white rounded-2xl p-7 border border-gray-100 shadow-sm">
          <p class="text-gray-600 text-sm leading-relaxed mb-6">"As a freelance designer, I was sceptical at first. Within a week I landed a $2,000 contract. The escrow system gives me complete peace of mind. Highly recommend!"</p>
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-pink-500 to-purple-500 flex items-center justify-center text-white font-bold text-sm">SO</div>
            <div>
              <div class="flex justify-start items-center gap-2">
                <p class="font-semibold text-gray-900 text-sm">Sofia Ortega</p>
                <div class="flex items-center gap-1 stars text-xs">
                  <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                </div>
              </div>
              <p class="text-gray-400 text-xs">Graphic Designer · Freelancer</p>
            </div>
          </div>
        </div>

        <div class="testi-card bg-white rounded-2xl p-7 border border-gray-100 shadow-sm">
          <p class="text-gray-600 text-sm leading-relaxed mb-6">"The AI-powered matching found us a Python ML expert within hours. The milestone system kept everything on track. We'll never use another freelance platform."</p>
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-green-500 to-teal-500 flex items-center justify-center text-white font-bold text-sm">RK</div>
            <div>
              <div class="flex justify-start items-center gap-2">
                <p class="font-semibold text-gray-900 text-sm">Riya Kapoor</p>
                <div class="flex items-center gap-1 stars text-xs">
                  <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i>
                </div>
              </div>
              <p class="text-gray-400 text-xs">CTO, DataFlow Labs · Client</p>
            </div>
          </div>
        </div>

        <div class="testi-card bg-white rounded-2xl p-7 border border-gray-100 shadow-sm">
          <p class="text-gray-600 text-sm leading-relaxed mb-6">"I replaced my full-time office job with freelancing through JobHub. In 6 months I hit $8K/month — more than I ever earned in a 9-to-5."</p>
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-orange-500 to-red-500 flex items-center justify-center text-white font-bold text-sm">CH</div>
            <div>
              <div class="flex justify-start items-center gap-2">
                <p class="font-semibold text-gray-900 text-sm">Carlos Herrera</p>
                <div class="flex items-center gap-1 stars text-xs">
                  <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                </div>
              </div>
              <p class="text-gray-400 text-xs">Backend Developer · Freelancer</p>
            </div>
          </div>
        </div>

        <div class="testi-card bg-white rounded-2xl p-7 border border-gray-100 shadow-sm">
          <p class="text-gray-600 text-sm leading-relaxed mb-6">"The chat system and milestone tracking make remote collaboration feel seamless. I've completed 40+ contracts here with zero payment disputes."</p>
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-cyan-500 to-blue-500 flex items-center justify-center text-white font-bold text-sm">LN</div>
            <div>
              <div class="flex justify-start items-center gap-2">
                <p class="font-semibold text-gray-900 text-sm">Li Na</p>
                <div class="flex items-center gap-1 stars text-xs">
                  <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                </div>
              </div>
              <p class="text-gray-400 text-xs">Mobile Developer · Freelancer</p>
            </div>
          </div>
        </div>

        <div class="testi-card bg-white rounded-2xl p-7 border border-gray-100 shadow-sm">
          <p class="text-gray-600 text-sm leading-relaxed mb-6">"The fraud-detection badge on freelancers gave us confidence. We hired a verified expert and launched our Android app 2 weeks ahead of schedule!"</p>
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-violet-500 to-purple-700 flex items-center justify-center text-white font-bold text-sm">EB</div>
            <div>
              <div class="flex justify-start items-center gap-2">
                <p class="font-semibold text-gray-900 text-sm">Emma Bauer</p>
                <div class="flex items-center gap-1 stars text-xs">
                  <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                </div>
              </div>
              <p class="text-gray-400 text-xs">Product Manager, Qube · Client</p>
            </div>
          </div>
        </div>

      </div>
    </div>
  </section>


  <!-- ═══════════════════════ 9. ABOUT ═════════════════════════════════ -->
  <section id="about" class="py-20 px-4 reveal">
    <div class="max-w-7xl mx-auto">
      <div class="text-center mb-14">
        <span class="text-xs font-bold uppercase tracking-widest text-blue-600/70 bg-blue-50 px-3 py-1 rounded-full">Our Story</span>
        <h2 class="text-4xl font-extrabold mt-4 mb-3 text-gray-900">About <span class="grad-text">JobHub</span></h2>
      </div>

      <div class="grid lg:grid-cols-3 gap-6">
        <!-- Who We Are -->
        <div class="bg-white rounded-2xl p-8 border border-gray-100 shadow-sm relative overflow-hidden group">
          <div class="absolute inset-0 bg-gradient-to-br from-blue-50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
          <div class="w-14 h-14 rounded-2xl bg-blue-50 flex items-center justify-center mb-6">
            <i class="fas fa-users text-blue-600 text-xl"></i>
          </div>
          <h3 class="text-xl font-bold text-gray-900 mb-4">Who We Are</h3>
          <p class="text-gray-500 leading-relaxed text-sm">JobHub was founded in 2023 by a team of engineers and entrepreneurs who experienced the friction of freelancing first-hand. We set out to build the most transparent, secure, and talent-rich marketplace in the world — connecting skilled professionals with innovative businesses across every time zone.</p>
          <div class="mt-6 flex items-center gap-4">
            <div class="text-center">
              <p class="text-lg font-black text-gray-900">50+</p>
              <p class="text-xs text-gray-400">Team Members</p>
            </div>
            <div class="w-px h-8 bg-gray-200"></div>
            <div class="text-center">
              <p class="text-lg font-black text-gray-900">40+</p>
              <p class="text-xs text-gray-400">Countries</p>
            </div>
            <div class="w-px h-8 bg-gray-200"></div>
            <div class="text-center">
              <p class="text-lg font-black text-gray-900">2023</p>
              <p class="text-xs text-gray-400">Founded</p>
            </div>
          </div>
        </div>

        <!-- Our Mission -->
        <div class="bg-white rounded-2xl p-8 border border-gray-100 shadow-sm relative overflow-hidden group">
          <div class="absolute inset-0 bg-gradient-to-br from-cyan-50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
          <div class="w-14 h-14 rounded-2xl bg-cyan-50 flex items-center justify-center mb-6">
            <i class="fas fa-rocket text-cyan-600 text-xl"></i>
          </div>
          <h3 class="text-xl font-bold text-gray-900 mb-4">Our Mission</h3>
          <p class="text-gray-500 leading-relaxed text-sm">Our mission is to democratise work — giving every skilled individual on the planet access to great projects and fair pay, regardless of geography. We believe talent is equally distributed; opportunity is not. JobHub exists to fix that imbalance through technology, trust, and a community-first approach to freelancing.</p>
          <ul class="mt-6 space-y-2">
            <li class="flex items-center gap-2 text-sm text-gray-600"><i class="fas fa-check-circle text-cyan-600"></i> Zero barriers to entry</li>
            <li class="flex items-center gap-2 text-sm text-gray-600"><i class="fas fa-check-circle text-cyan-600"></i> Fair & transparent fees</li>
            <li class="flex items-center gap-2 text-sm text-gray-600"><i class="fas fa-check-circle text-cyan-600"></i> Global opportunity for all</li>
          </ul>
        </div>

        <!-- Why Choose Us -->
        <div class="bg-white rounded-2xl p-8 border border-gray-100 shadow-sm relative overflow-hidden group">
          <div class="absolute inset-0 bg-gradient-to-br from-purple-50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
          <div class="w-14 h-14 rounded-2xl bg-purple-50 flex items-center justify-center mb-6">
            <i class="fas fa-star text-purple-600 text-xl"></i>
          </div>
          <h3 class="text-xl font-bold text-gray-900 mb-4">Why Choose Us</h3>
          <div class="space-y-4">
            <div class="flex items-start gap-3">
              <div class="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center flex-shrink-0 mt-0.5"><i class="fas fa-shield-alt text-green-600 text-xs"></i></div>
              <div>
                <p class="text-sm font-semibold text-gray-900">Escrow Protection</p>
                <p class="text-xs text-gray-400">Funds secured until work approved</p>
              </div>
            </div>
            <div class="flex items-start gap-3">
              <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0 mt-0.5"><i class="fas fa-brain text-blue-600 text-xs"></i></div>
              <div>
                <p class="text-sm font-semibold text-gray-900">AI-Powered Matching</p>
                <p class="text-xs text-gray-400">Smart skill-to-job recommendations</p>
              </div>
            </div>
            <div class="flex items-start gap-3">
              <div class="w-8 h-8 rounded-lg bg-yellow-50 flex items-center justify-center flex-shrink-0 mt-0.5"><i class="fas fa-user-check text-yellow-600 text-xs"></i></div>
              <div>
                <p class="text-sm font-semibold text-gray-900">Verified Profiles</p>
                <p class="text-xs text-gray-400">ID & skill verification on all pros</p>
              </div>
            </div>
            <div class="flex items-start gap-3">
              <div class="w-8 h-8 rounded-lg bg-cyan-50 flex items-center justify-center flex-shrink-0 mt-0.5"><i class="fas fa-headset text-cyan-600 text-xs"></i></div>
              <div>
                <p class="text-sm font-semibold text-gray-900">24/7 Support</p>
                <p class="text-xs text-gray-400">Live dispute resolution team</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>


  <!-- ═══════════════════════ PRICING ═══════════════════════════════════ -->
  <section id="pricing" class="py-20 px-4 reveal bg-gray-50">
    <div class="max-w-5xl mx-auto">
      <div class="text-center mb-14">
        <span class="text-xs font-bold uppercase tracking-widest text-cyan-600/70 bg-cyan-50 px-3 py-1 rounded-full">Transparent</span>
        <h2 class="text-4xl font-extrabold mt-4 mb-3 text-gray-900">Simple <span class="grad-text">Pricing</span></h2>
        <p class="text-gray-500 max-w-xl mx-auto">No hidden fees. Post free, pay only when you hire.</p>
      </div>
      <div class="grid sm:grid-cols-3 gap-6">
        <!-- Starter -->
        <div class="bg-white rounded-2xl p-8 border border-gray-100 text-center hover:border-primary transition-all hover:-translate-y-1 duration-300 shadow-sm">
          <p class="text-xs font-bold uppercase tracking-widest text-gray-400 mb-3">Starter</p>
          <p class="text-5xl font-black text-gray-900 mb-1">$0<span class="text-lg text-gray-400 font-normal">/mo</span></p>
          <p class="text-gray-400 text-sm mb-6">For casual clients</p>
          <ul class="space-y-3 text-sm text-gray-500 text-left mb-8">
            <li class="flex items-center gap-2"><i class="fas fa-check text-green-500 text-xs"></i> 3 active job posts</li>
            <li class="flex items-center gap-2"><i class="fas fa-check text-green-500 text-xs"></i> 10% platform fee</li>
            <li class="flex items-center gap-2"><i class="fas fa-check text-green-500 text-xs"></i> Basic support</li>
            <li class="flex items-center gap-2"><i class="fas fa-times text-gray-300 text-xs"></i> <span class="text-gray-300">AI matching</span></li>
          </ul>
          <a href="auth/register.php" class="block border border-gray-200 hover:border-primary text-gray-700 font-semibold py-3 rounded-xl transition-all">Get Started</a>
        </div>
        <!-- Pro (highlight) -->
        <div class="bg-white rounded-2xl p-8 border-2 border-blue-600 text-center relative -translate-y-3 shadow-xl shadow-blue-500/15">
          <div class="absolute -top-4 left-1/2 -translate-x-1/2 btn-grad text-white text-xs font-black px-4 py-1 rounded-full">Most Popular</div>
          <p class="text-xs font-bold uppercase tracking-widest text-blue-600 mb-3">Pro</p>
          <p class="text-5xl font-black text-gray-900 mb-1">$29<span class="text-lg text-gray-400 font-normal">/mo</span></p>
          <p class="text-gray-400 text-sm mb-6">For growing teams</p>
          <ul class="space-y-3 text-sm text-gray-500 text-left mb-8">
            <li class="flex items-center gap-2"><i class="fas fa-check text-green-500 text-xs"></i> Unlimited job posts</li>
            <li class="flex items-center gap-2"><i class="fas fa-check text-green-500 text-xs"></i> 5% platform fee</li>
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
            <li class="flex items-center gap-2"><i class="fas fa-check text-green-500 text-xs"></i> 2% platform fee</li>
            <li class="flex items-center gap-2"><i class="fas fa-check text-green-500 text-xs"></i> Dedicated manager</li>
            <li class="flex items-center gap-2"><i class="fas fa-check text-green-500 text-xs"></i> Custom contracts & NDA</li>
          </ul>
          <a href="contact.php" class="block border border-purple-200 hover:bg-purple-50 text-gray-700 font-semibold py-3 rounded-xl transition-all">Contact Sales</a>
        </div>
      </div>
    </div>
  </section>


  <!-- ═══════════════════════ 10. FOOTER ════════════════════════════════ -->
  <footer id="footer" class="border-t border-gray-200 bg-white pt-16 pb-8 px-4">
    <div class="max-w-7xl mx-auto">
      <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-10 mb-14">

        <!-- Brand -->
        <div class="lg:col-span-2">
          <a href="#" class="flex items-center gap-2 mb-5">
            <div class="w-9 h-9 rounded-xl btn-grad flex items-center justify-center shadow-lg shadow-blue-500/25">
              <i class="fas fa-bolt text-white text-sm"></i>
            </div>
            <span class="text-xl font-extrabold"><span class="text-gray-900">Freelance</span><span class="grad-text">Hub</span></span>
          </a>
          <p class="text-gray-400 text-sm leading-relaxed mb-6 max-w-xs">The world's most trusted marketplace for top freelancers and innovative clients. Work smarter, together.</p>
          <!-- Social -->
          <div class="flex gap-3">
            <a href="#" class="w-9 h-9 bg-gray-50 rounded-lg flex items-center justify-center text-gray-400 hover:text-blue-500 hover:bg-blue-50 transition-all border border-gray-100"><i class="fab fa-twitter text-sm"></i></a>
            <a href="#" class="w-9 h-9 bg-gray-50 rounded-lg flex items-center justify-center text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-all border border-gray-100"><i class="fab fa-linkedin-in text-sm"></i></a>
            <a href="#" class="w-9 h-9 bg-gray-50 rounded-lg flex items-center justify-center text-gray-400 hover:text-pink-500 hover:bg-pink-50 transition-all border border-gray-100"><i class="fab fa-instagram text-sm"></i></a>
            <a href="#" class="w-9 h-9 bg-gray-50 rounded-lg flex items-center justify-center text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-all border border-gray-100"><i class="fab fa-github text-sm"></i></a>
            <a href="#" class="w-9 h-9 bg-gray-50 rounded-lg flex items-center justify-center text-gray-400 hover:text-red-500 hover:bg-red-50 transition-all border border-gray-100"><i class="fab fa-youtube text-sm"></i></a>
          </div>
        </div>

        <!-- Company -->
        <div>
          <h4 class="text-gray-900 font-bold text-sm uppercase tracking-widest mb-5">Company</h4>
          <ul class="space-y-3 text-gray-400 text-sm">
            <li><a href="#about" class="hover:text-gray-900 transition-colors">About Us</a></li>
            <li><a href="careers.php" class="hover:text-gray-900 transition-colors">Careers</a></li>
            <li><a href="blog.php" class="hover:text-gray-900 transition-colors">Blog</a></li>
            <li><a href="press.php" class="hover:text-gray-900 transition-colors">Press</a></li>
            <li><a href="#pricing" class="hover:text-gray-900 transition-colors">Pricing</a></li>
          </ul>
        </div>

        <!-- Support -->
        <div>
          <h4 class="text-gray-900 font-bold text-sm uppercase tracking-widest mb-5">Support</h4>
          <ul class="space-y-3 text-gray-400 text-sm">
            <li><a href="faq.php" class="hover:text-gray-900 transition-colors">FAQ</a></li>
            <li><a href="contact.php" class="hover:text-gray-900 transition-colors">Contact Us</a></li>
            <li><a href="privacy.php" class="hover:text-gray-900 transition-colors">Privacy Policy</a></li>
            <li><a href="terms.php" class="hover:text-gray-900 transition-colors">Terms &amp; Conditions</a></li>
            <li><a href="dispute.php" class="hover:text-gray-900 transition-colors">Dispute Resolution</a></li>
          </ul>
        </div>

        <!-- Contact -->
        <div>
          <h4 class="text-gray-900 font-bold text-sm uppercase tracking-widest mb-5">Contact</h4>
          <ul class="space-y-3 text-gray-400 text-sm">
            <li class="flex items-start gap-2"><i class="fas fa-envelope text-blue-500 mt-0.5"></i><a href="mailto:hello@freelancehub.io" class="hover:text-gray-900 transition-colors">hello@freelancehub.io</a></li>
            <li class="flex items-start gap-2"><i class="fas fa-phone text-cyan-500 mt-0.5"></i><span>+95 9 757 889806</span></li>
            <li class="flex items-start gap-2"><i class="fas fa-map-marker-alt text-purple-500 mt-0.5"></i><span>Myanmar, Yangon 11041</span></li>
          </ul>
          <!-- Newsletter -->
          <!-- <div class="mt-6">
            <p class="text-gray-500 text-xs font-medium mb-2">Stay in the loop:</p>
            <div class="flex gap-2">
              <input type="email" placeholder="your@email.com" class="flex-1 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs text-gray-900 placeholder-gray-400 outline-none focus:border-primary transition-colors" />
              <button class="btn-grad text-white text-xs font-bold px-3 py-2 rounded-lg"><i class="fas fa-paper-plane"></i></button>
            </div>
          </div> -->
          <div class="mt-6">
            <p class="text-gray-500 text-xs font-medium mb-2">Stay in the loop:</p>
            <!-- 1. Wrap inside a POST form pointing to your action handler -->
            <form action="../actions/newsletter_process.php" method="POST" class="flex gap-2">
              <!-- 2. Include your checklist mandatory CSRF protection token -->
              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? ''; ?>">

              <!-- 3. Add name="email" to the input -->
              <input type="email" name="email" placeholder="your@email.com" required
                class="flex-1 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs text-gray-900 placeholder-gray-400 outline-none focus:border-primary transition-colors" />

              <!-- 4. Explicitly make the button type="submit" -->
              <button type="submit" class="btn-grad text-white text-xs font-bold px-3 py-2 rounded-lg">
                <i class="fas fa-paper-plane"></i>
              </button>
            </form>
          </div>

        </div>
      </div>

      <div class="border-t border-gray-100 pt-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-gray-400 text-xs">
        <p>© 2026 JobHub. All rights reserved.</p>
        <div class="flex gap-4">
          <a href="privacy.php" class="hover:text-gray-600 transition-colors">Privacy</a>
          <a href="terms.php" class="hover:text-gray-600 transition-colors">Terms</a>
          <a href="faq.php" class="hover:text-gray-600 transition-colors">FAQ</a>
        </div>
      </div>
    </div>
  </footer>


  <!-- ═══════════════════════ BACK TO TOP ══════════════════════════════ -->
  <button id="back-top" class="fixed bottom-6 right-6 w-12 h-12 btn-grad text-white rounded-xl shadow-xl shadow-blue-500/25 hidden items-center justify-center hover:-translate-y-1 transition-all z-50" onclick="window.scrollTo({top:0,behavior:'smooth'})">
    <i class="fas fa-chevron-up text-sm"></i>
  </button>


  <!-- ═══════════════════════ JAVASCRIPT ═══════════════════════════════ -->
  <script>
    // ── Scroll progress & navbar ──
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

    // ── Mobile hamburger ──
    document.getElementById('hamburger').addEventListener('click', () => {
      const m = document.getElementById('mobile-menu');
      m.classList.toggle('open');
    });

    // ── Tab switcher ──
    function switchTab(tab) {
      ['client', 'freelancer'].forEach(t => {
        document.getElementById('panel-' + t).classList.add('hidden');
        document.getElementById('tab-' + t).classList.remove('active');
        document.getElementById('tab-' + t).classList.add('text-gray-400');
      });
      document.getElementById('panel-' + tab).classList.remove('hidden');
      document.getElementById('tab-' + tab).classList.add('active');
      document.getElementById('tab-' + tab).classList.remove('text-gray-400');
    }

    // ── Scroll reveal ──
    const reveals = document.querySelectorAll('.reveal');

    function checkReveal() {
      reveals.forEach(el => {
        const top = el.getBoundingClientRect().top;
        if (top < window.innerHeight - 80) el.classList.add('visible');
      });
    }
    checkReveal();

    // ── Animated stat counters ──
    function animateCounter(el, target, suffix = '+') {
      let current = 0;
      const step = Math.ceil(target / 60);
      const timer = setInterval(() => {
        current = Math.min(current + step, target);
        el.textContent = current.toLocaleString() + suffix;
        if (current >= target) clearInterval(timer);
      }, 24);
    }

    let statsAnimated = false;
    const statsSection = document.getElementById('stats');

    const statsObserver = new IntersectionObserver(entries => {
      if (entries[0].isIntersecting && !statsAnimated) {
        statsAnimated = true;
        animateCounter(document.getElementById('cnt-freelancers'), 5000);
        animateCounter(document.getElementById('cnt-clients'), 1000);
        animateCounter(document.getElementById('cnt-projects'), 3000);
        animateCounter(document.getElementById('cnt-paid'), 2, 'M+');
      }
    }, {
      threshold: 0.4
    });
    statsObserver.observe(statsSection);

    // ── Search Dropdown Logic ──
    function setupSearchDropdown(dropdownId, btnId, inputId, typeSpanId) {
      const dropdown = document.getElementById(dropdownId);
      const btn = document.getElementById(btnId);
      const input = document.getElementById(inputId);
      const typeSpan = document.getElementById(typeSpanId);

      // Toggle dropdown
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdown.classList.toggle('hidden');
      });

      // Handle option selection
      dropdown.querySelectorAll('button').forEach(option => {
        option.addEventListener('click', () => {
          const type = option.dataset.type;
          const placeholder = option.dataset.placeholder;
          typeSpan.textContent = type === 'talent' ? 'Talent' : 'Jobs';
          input.placeholder = placeholder;
          input.dataset.searchType = type;
          dropdown.classList.add('hidden');
          input.focus();
        });
      });

      // Close dropdown when clicking outside
      document.addEventListener('click', () => {
        dropdown.classList.add('hidden');
      });
    }

    setupSearchDropdown('navSearchDropdown', 'navSearchDropdownBtn', 'nav-search', 'navSearchType');
    setupSearchDropdown('heroSearchDropdown', 'heroSearchDropdownBtn', 'hero-search', 'heroSearchType');

    // ── Hero search ──
    document.getElementById('hero-search').addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        const q = this.value.trim();
        const type = this.dataset.searchType || 'talent';
        if (q) {
          if (type === 'talent') {
            window.location.href = 'freelancers/browse.php?q=' + encodeURIComponent(q);
          } else {
            window.location.href = 'jobs/browse.php?q=' + encodeURIComponent(q);
          }
        }
      }
    });

    // ── Nav search ──
    document.getElementById('nav-search').addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        const q = this.value.trim();
        const type = this.dataset.searchType || 'talent';
        if (q) {
          if (type === 'talent') {
            window.location.href = 'freelancers/browse.php?q=' + encodeURIComponent(q);
          } else {
            window.location.href = 'jobs/browse.php?q=' + encodeURIComponent(q);
          }
        }
      }
    });

    // ── Close mobile menu on link click ──
    document.querySelectorAll('#mobile-menu a').forEach(a => {
      a.addEventListener('click', () => document.getElementById('mobile-menu').classList.remove('open'));
    });


    // ── Nav search bar visibility on scroll ──

    const categories = document.getElementById("categories");
    const searchBar = document.getElementById("searchBar");

    window.addEventListener("scroll", () => {
      const categoryTop = categories.offsetTop;

      if (window.scrollY >= categoryTop) {
        searchBar.classList.remove("hidden");
      } else {
        searchBar.classList.add("hidden");
      }
    });
  </script>
</body>

</html>