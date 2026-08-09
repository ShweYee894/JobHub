<!DOCTYPE html>
<html lang="en" class="scroll-smooth">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Careers – JobHub</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <script src="https://unpkg.com/lucide@0.344.0/dist/umd/lucide.min.js"></script>
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
      <button id="hamburger" class="lg:hidden text-gray-600 hover:text-gray-900 p-2"><i data-lucide="menu" class="w-5 h-5"></i></button>
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
      <span class="text-xs font-bold uppercase tracking-widest text-blue-600/70 bg-blue-50 px-3 py-1 rounded-full">Join Us</span>
      <h1 class="text-4xl sm:text-5xl font-black mt-4 mb-4 text-gray-900">Join Our <span class="grad-text">Team</span></h1>
      <p class="text-gray-500 max-w-2xl mx-auto">Help us build the world's most trusted freelance marketplace. We're looking for passionate people who want to make a real impact.</p>
    </div>
  </section>

  <!-- CULTURE -->
  <section class="py-20 px-4 reveal">
    <div class="max-w-7xl mx-auto">
      <div class="text-center mb-14">
        <span class="text-xs font-bold uppercase tracking-widest text-cyan-600/70 bg-cyan-50 px-3 py-1 rounded-full">Our Culture</span>
        <h2 class="text-4xl font-extrabold mt-4 mb-3 text-gray-900">How We <span class="grad-text">Work</span></h2>
      </div>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-2xl p-8 border border-gray-100 shadow-sm hover:-translate-y-1 transition-all">
          <div class="w-14 h-14 rounded-2xl bg-blue-50 flex items-center justify-center mb-6"><i data-lucide="rocket" class="w-5 h-5 text-blue-600"></i></div>
          <h3 class="text-lg font-bold text-gray-900 mb-3">Move Fast, Build Smart</h3>
          <p class="text-gray-500 text-sm leading-relaxed">We ship early and iterate often. Every team member has the autonomy to make decisions and drive impact from day one.</p>
        </div>
        <div class="bg-white rounded-2xl p-8 border border-gray-100 shadow-sm hover:-translate-y-1 transition-all">
          <div class="w-14 h-14 rounded-2xl bg-cyan-50 flex items-center justify-center mb-6"><i data-lucide="users" class="w-5 h-5 text-cyan-600"></i></div>
          <h3 class="text-lg font-bold text-gray-900 mb-3">People First</h3>
          <p class="text-gray-500 text-sm leading-relaxed">Flexible hours, remote-friendly, generous PTO, and a culture that respects work-life balance. Your well-being is our priority.</p>
        </div>
        <div class="bg-white rounded-2xl p-8 border border-gray-100 shadow-sm hover:-translate-y-1 transition-all">
          <div class="w-14 h-14 rounded-2xl bg-purple-50 flex items-center justify-center mb-6"><i data-lucide="lightbulb" class="w-5 h-5 text-purple-600"></i></div>
          <h3 class="text-lg font-bold text-gray-900 mb-3">Innovation Driven</h3>
          <p class="text-gray-500 text-sm leading-relaxed">We encourage experimentation and creative problem-solving. Hackathons, learning budgets, and 10% time for personal projects.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- BENEFITS -->
  <section class="py-20 px-4 reveal bg-gray-50">
    <div class="max-w-7xl mx-auto">
      <div class="text-center mb-14">
        <span class="text-xs font-bold uppercase tracking-widest text-green-600/70 bg-green-50 px-3 py-1 rounded-full">Perks & Benefits</span>
        <h2 class="text-4xl font-extrabold mt-4 mb-3 text-gray-900">Why You'll <span class="grad-text">Love It Here</span></h2>
      </div>
      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm text-center">
          <div class="w-12 h-12 mx-auto mb-4 rounded-xl bg-green-50 flex items-center justify-center"><i data-lucide="heart" class="w-5 h-5 text-green-600"></i></div>
          <h4 class="font-bold text-gray-900 text-sm mb-1">Health Insurance</h4>
          <p class="text-gray-400 text-xs">Full medical, dental, and vision coverage for you and your family</p>
        </div>
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm text-center">
          <div class="w-12 h-12 mx-auto mb-4 rounded-xl bg-blue-50 flex items-center justify-center"><i data-lucide="house" class="w-5 h-5 text-blue-600"></i></div>
          <h4 class="font-bold text-gray-900 text-sm mb-1">Remote Flexible</h4>
          <p class="text-gray-400 text-xs">Work from anywhere in the world with flexible scheduling</p>
        </div>
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm text-center">
          <div class="w-12 h-12 mx-auto mb-4 rounded-xl bg-purple-50 flex items-center justify-center"><i data-lucide="graduation-cap" class="w-5 h-5 text-purple-600"></i></div>
          <h4 class="font-bold text-gray-900 text-sm mb-1">Learning Budget</h4>
          <p class="text-gray-400 text-xs">$2,000/year for courses, conferences, and professional development</p>
        </div>
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm text-center">
          <div class="w-12 h-12 mx-auto mb-4 rounded-xl bg-yellow-50 flex items-center justify-center"><i data-lucide="umbrella" class="w-5 h-5 text-yellow-600"></i></div>
          <h4 class="font-bold text-gray-900 text-sm mb-1">Unlimited PTO</h4>
          <p class="text-gray-400 text-xs">Take the time you need to recharge and stay productive</p>
        </div>
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm text-center">
          <div class="w-12 h-12 mx-auto mb-4 rounded-xl bg-pink-50 flex items-center justify-center"><i data-lucide="trending-up" class="w-5 h-5 text-pink-600"></i></div>
          <h4 class="font-bold text-gray-900 text-sm mb-1">Equity Options</h4>
          <p class="text-gray-400 text-xs">Stock options so you share in the company's success</p>
        </div>
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm text-center">
          <div class="w-12 h-12 mx-auto mb-4 rounded-xl bg-orange-50 flex items-center justify-center"><i data-lucide="laptop" class="w-5 h-5 text-orange-600"></i></div>
          <h4 class="font-bold text-gray-900 text-sm mb-1">Top-Tier Equipment</h4>
          <p class="text-gray-400 text-xs">MacBook Pro, monitors, and ergonomic setup on your first day</p>
        </div>
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm text-center">
          <div class="w-12 h-12 mx-auto mb-4 rounded-xl bg-cyan-50 flex items-center justify-center"><i data-lucide="coffee" class="w-5 h-5 text-cyan-600"></i></div>
          <h4 class="font-bold text-gray-900 text-sm mb-1">Team Events</h4>
          <p class="text-gray-400 text-xs">Quarterly team retreats, happy hours, and social activities</p>
        </div>
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm text-center">
          <div class="w-12 h-12 mx-auto mb-4 rounded-xl bg-indigo-50 flex items-center justify-center"><i data-lucide="globe" class="w-5 h-5 text-indigo-600"></i></div>
          <h4 class="font-bold text-gray-900 text-sm mb-1">Global Impact</h4>
          <p class="text-gray-400 text-xs">Work that connects talent with opportunity across 180+ countries</p>
        </div>
      </div>
    </div>
  </section>

  <!-- OPEN POSITIONS -->
  <section class="py-20 px-4 reveal">
    <div class="max-w-5xl mx-auto">
      <div class="text-center mb-14">
        <span class="text-xs font-bold uppercase tracking-widest text-primary/70 bg-blue-50 px-3 py-1 rounded-full">Open Roles</span>
        <h2 class="text-4xl font-extrabold mt-4 mb-3 text-gray-900">Current <span class="grad-text">Openings</span></h2>
      </div>
      <div class="space-y-4">

        <div class="bg-white rounded-2xl p-6 sm:p-8 border border-gray-100 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:border-primary transition-all">
          <div>
            <div class="flex items-center gap-2 mb-2">
              <h3 class="font-bold text-gray-900 text-lg">Senior Full-Stack Developer</h3>
              <span class="text-xs font-bold bg-green-50 text-green-600 px-2 py-0.5 rounded-full">Remote</span>
            </div>
            <p class="text-gray-400 text-sm">Engineering · Full-time · $120K-$160K</p>
          </div>
          <a href="contact.php?position=Senior+Full-Stack+Developer" class="btn-grad text-white font-semibold px-6 py-2.5 rounded-xl text-sm flex-shrink-0">Apply Now</a>
        </div>

        <div class="bg-white rounded-2xl p-6 sm:p-8 border border-gray-100 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:border-primary transition-all">
          <div>
            <div class="flex items-center gap-2 mb-2">
              <h3 class="font-bold text-gray-900 text-lg">Product Designer</h3>
              <span class="text-xs font-bold bg-blue-50 text-blue-600 px-2 py-0.5 rounded-full">Hybrid</span>
            </div>
            <p class="text-gray-400 text-sm">Design · Full-time · $90K-$130K</p>
          </div>
          <a href="contact.php?position=Product+Designer" class="btn-grad text-white font-semibold px-6 py-2.5 rounded-xl text-sm flex-shrink-0">Apply Now</a>
        </div>

        <div class="bg-white rounded-2xl p-6 sm:p-8 border border-gray-100 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:border-primary transition-all">
          <div>
            <div class="flex items-center gap-2 mb-2">
              <h3 class="font-bold text-gray-900 text-lg">DevOps Engineer</h3>
              <span class="text-xs font-bold bg-green-50 text-green-600 px-2 py-0.5 rounded-full">Remote</span>
            </div>
            <p class="text-gray-400 text-sm">Infrastructure · Full-time · $110K-$150K</p>
          </div>
          <a href="contact.php?position=DevOps+Engineer" class="btn-grad text-white font-semibold px-6 py-2.5 rounded-xl text-sm flex-shrink-0">Apply Now</a>
        </div>

        <div class="bg-white rounded-2xl p-6 sm:p-8 border border-gray-100 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:border-primary transition-all">
          <div>
            <div class="flex items-center gap-2 mb-2">
              <h3 class="font-bold text-gray-900 text-lg">Marketing Manager</h3>
              <span class="text-xs font-bold bg-yellow-50 text-yellow-600 px-2 py-0.5 rounded-full">On-site</span>
            </div>
            <p class="text-gray-400 text-sm">Marketing · Full-time · $80K-$110K</p>
          </div>
          <a href="contact.php?position=Marketing+Manager" class="btn-grad text-white font-semibold px-6 py-2.5 rounded-xl text-sm flex-shrink-0">Apply Now</a>
        </div>

        <div class="bg-white rounded-2xl p-6 sm:p-8 border border-gray-100 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:border-primary transition-all">
          <div>
            <div class="flex items-center gap-2 mb-2">
              <h3 class="font-bold text-gray-900 text-lg">Customer Success Specialist</h3>
              <span class="text-xs font-bold bg-green-50 text-green-600 px-2 py-0.5 rounded-full">Remote</span>
            </div>
            <p class="text-gray-400 text-sm">Support · Full-time · $55K-$75K</p>
          </div>
          <a href="contact.php?position=Customer+Success+Specialist" class="btn-grad text-white font-semibold px-6 py-2.5 rounded-xl text-sm flex-shrink-0">Apply Now</a>
        </div>

      </div>
    </div>
  </section>

  <!-- APPLICATION FORM -->
  <section class="py-20 px-4 reveal bg-gray-50">
    <div class="max-w-3xl mx-auto">
      <div class="text-center mb-14">
        <span class="text-xs font-bold uppercase tracking-widest text-purple-600/70 bg-purple-50 px-3 py-1 rounded-full">Apply Now</span>
        <h2 class="text-4xl font-extrabold mt-4 mb-3 text-gray-900">Submit Your <span class="grad-text">Application</span></h2>
      </div>
      <div class="bg-white rounded-3xl p-8 sm:p-10 border border-gray-100 shadow-sm">
        <form action="actions/careers_process.php" method="POST" enctype="multipart/form-data" class="space-y-6">
          <div class="grid sm:grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Full Name</label>
              <input type="text" name="name" required placeholder="John Doe" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-gray-900 placeholder-gray-400 outline-none focus:border-primary focus:bg-white transition-all text-sm" />
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
              <input type="email" name="email" required placeholder="john@example.com" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-gray-900 placeholder-gray-400 outline-none focus:border-primary focus:bg-white transition-all text-sm" />
            </div>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Position</label>
            <select name="position" required class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-gray-900 outline-none focus:border-primary focus:bg-white transition-all text-sm">
              <option value="">Select a position...</option>
              <option value="Senior Full-Stack Developer">Senior Full-Stack Developer</option>
              <option value="Product Designer">Product Designer</option>
              <option value="DevOps Engineer">DevOps Engineer</option>
              <option value="Marketing Manager">Marketing Manager</option>
              <option value="Customer Success Specialist">Customer Success Specialist</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Resume / CV</label>
            <div class="relative">
              <input type="file" name="resume" required accept=".pdf,.doc,.docx" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-gray-900 outline-none focus:border-primary focus:bg-white transition-all text-sm file:mr-4 file:py-1 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-600 hover:file:bg-blue-100" />
            </div>
            <p class="text-gray-400 text-xs mt-1">PDF, DOC, or DOCX (max 5MB)</p>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Cover Letter</label>
            <textarea name="cover_letter" rows="5" placeholder="Tell us why you'd be a great fit..." class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-gray-900 placeholder-gray-400 outline-none focus:border-primary focus:bg-white transition-all text-sm resize-none"></textarea>
          </div>
          <button type="submit" class="btn-grad text-white font-semibold px-8 py-3 rounded-xl shadow-lg shadow-blue-500/25 w-full sm:w-auto">Submit Application <i data-lucide="send" class="w-4 h-4 ml-2 inline"></i></button>
        </form>
      </div>
    </div>
  </section>

  <!-- WHY WORK WITH US -->
  <section class="py-20 px-4 reveal">
    <div class="max-w-4xl mx-auto">
      <div class="bg-gradient-to-br from-blue-600 to-cyan-500 rounded-3xl p-10 sm:p-16 text-center relative overflow-hidden">
        <div class="absolute inset-0 opacity-10" style="background:radial-gradient(ellipse at 20% 50%,#fff 0%,transparent 60%),radial-gradient(ellipse at 80% 50%,#fff 0%,transparent 60%)"></div>
        <div class="relative">
          <h2 class="text-3xl sm:text-4xl font-black text-white mb-4">Why Work With Us?</h2>
          <p class="text-blue-100 max-w-xl mx-auto mb-8">Join a team of 50+ passionate people across 40+ countries. We're not just building a platform — we're building the future of work.</p>
          <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="contact.php" class="bg-white text-blue-600 font-semibold px-8 py-4 rounded-full hover:bg-blue-50 transition-all inline-flex items-center justify-center gap-2">
              <i data-lucide="mail" class="w-4 h-4"></i> Get In Touch
            </a>
            <a href="#positions" class="bg-white/10 backdrop-blur-md text-white font-semibold px-8 py-4 rounded-full border border-white/30 hover:bg-white/20 transition-all inline-flex items-center justify-center gap-2">
              <i data-lucide="briefcase" class="w-4 h-4"></i> View Openings
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
          <a href="index.php" class="flex items-center gap-1.5 group shrink-0">
            <img src="assets/upload/logos/logo.png" alt="Logo" class="w-[36px] h-[36px] rounded-xl">
            <span class="text-lg font-extrabold tracking-tight">
              <span class="text-gray-900">Job</span><span class="grad-text">Hub</span>
            </span>
          </a>
          <p class="text-gray-400 text-sm leading-relaxed mb-6 max-w-xs">The world's most trusted marketplace for top freelancers and innovative clients. Work smarter, together.</p>
          <div class="flex gap-3">
            <a href="#" class="w-9 h-9 bg-gray-50 rounded-lg flex items-center justify-center text-gray-400 hover:text-blue-500 hover:bg-blue-50 transition-all border border-gray-100"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></a>
            <a href="#" class="w-9 h-9 bg-gray-50 rounded-lg flex items-center justify-center text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-all border border-gray-100"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg></a>
            <a href="#" class="w-9 h-9 bg-gray-50 rounded-lg flex items-center justify-center text-gray-400 hover:text-pink-500 hover:bg-pink-50 transition-all border border-gray-100"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg></a>
            <a href="#" class="w-9 h-9 bg-gray-50 rounded-lg flex items-center justify-center text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-all border border-gray-100"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg></a>
            <a href="#" class="w-9 h-9 bg-gray-50 rounded-lg flex items-center justify-center text-gray-400 hover:text-red-500 hover:bg-red-50 transition-all border border-gray-100"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg></a>
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
            <li class="flex items-start gap-2"><i data-lucide="mail" class="w-4 h-4 text-blue-500 mt-0.5"></i><a href="mailto:hello@jobhub.io" class="hover:text-gray-900 transition-colors">hello@jobhub.io</a></li>
            <li class="flex items-start gap-2"><i data-lucide="phone" class="w-4 h-4 text-cyan-500 mt-0.5"></i><span>+95 9 757 889806</span></li>
            <li class="flex items-start gap-2"><i data-lucide="map-pin" class="w-4 h-4 text-purple-500 mt-0.5"></i><span>Myanmar, Yangon 11041</span></li>
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

  <button id="back-top" class="fixed bottom-6 right-6 w-12 h-12 btn-grad text-white rounded-xl shadow-xl shadow-blue-500/25 hidden items-center justify-center hover:-translate-y-1 transition-all z-50" onclick="window.scrollTo({top:0,behavior:'smooth'})"><i data-lucide="chevron-up" class="w-4 h-4"></i></button>

  <script>
    lucide.createIcons();
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
  </script>
</body>

</html>
