<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Careers – JobHub</title>
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
          <div class="w-14 h-14 rounded-2xl bg-blue-50 flex items-center justify-center mb-6"><i class="fas fa-rocket text-blue-600 text-xl"></i></div>
          <h3 class="text-lg font-bold text-gray-900 mb-3">Move Fast, Build Smart</h3>
          <p class="text-gray-500 text-sm leading-relaxed">We ship early and iterate often. Every team member has the autonomy to make decisions and drive impact from day one.</p>
        </div>
        <div class="bg-white rounded-2xl p-8 border border-gray-100 shadow-sm hover:-translate-y-1 transition-all">
          <div class="w-14 h-14 rounded-2xl bg-cyan-50 flex items-center justify-center mb-6"><i class="fas fa-users text-cyan-600 text-xl"></i></div>
          <h3 class="text-lg font-bold text-gray-900 mb-3">People First</h3>
          <p class="text-gray-500 text-sm leading-relaxed">Flexible hours, remote-friendly, generous PTO, and a culture that respects work-life balance. Your well-being is our priority.</p>
        </div>
        <div class="bg-white rounded-2xl p-8 border border-gray-100 shadow-sm hover:-translate-y-1 transition-all">
          <div class="w-14 h-14 rounded-2xl bg-purple-50 flex items-center justify-center mb-6"><i class="fas fa-lightbulb text-purple-600 text-xl"></i></div>
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
          <div class="w-12 h-12 mx-auto mb-4 rounded-xl bg-green-50 flex items-center justify-center"><i class="fas fa-heart text-green-600"></i></div>
          <h4 class="font-bold text-gray-900 text-sm mb-1">Health Insurance</h4>
          <p class="text-gray-400 text-xs">Full medical, dental, and vision coverage for you and your family</p>
        </div>
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm text-center">
          <div class="w-12 h-12 mx-auto mb-4 rounded-xl bg-blue-50 flex items-center justify-center"><i class="fas fa-home text-blue-600"></i></div>
          <h4 class="font-bold text-gray-900 text-sm mb-1">Remote Flexible</h4>
          <p class="text-gray-400 text-xs">Work from anywhere in the world with flexible scheduling</p>
        </div>
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm text-center">
          <div class="w-12 h-12 mx-auto mb-4 rounded-xl bg-purple-50 flex items-center justify-center"><i class="fas fa-graduation-cap text-purple-600"></i></div>
          <h4 class="font-bold text-gray-900 text-sm mb-1">Learning Budget</h4>
          <p class="text-gray-400 text-xs">$2,000/year for courses, conferences, and professional development</p>
        </div>
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm text-center">
          <div class="w-12 h-12 mx-auto mb-4 rounded-xl bg-yellow-50 flex items-center justify-center"><i class="fas fa-umbrella-beach text-yellow-600"></i></div>
          <h4 class="font-bold text-gray-900 text-sm mb-1">Unlimited PTO</h4>
          <p class="text-gray-400 text-xs">Take the time you need to recharge and stay productive</p>
        </div>
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm text-center">
          <div class="w-12 h-12 mx-auto mb-4 rounded-xl bg-pink-50 flex items-center justify-center"><i class="fas fa-chart-line text-pink-600"></i></div>
          <h4 class="font-bold text-gray-900 text-sm mb-1">Equity Options</h4>
          <p class="text-gray-400 text-xs">Stock options so you share in the company's success</p>
        </div>
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm text-center">
          <div class="w-12 h-12 mx-auto mb-4 rounded-xl bg-orange-50 flex items-center justify-center"><i class="fas fa-laptop text-orange-600"></i></div>
          <h4 class="font-bold text-gray-900 text-sm mb-1">Top-Tier Equipment</h4>
          <p class="text-gray-400 text-xs">MacBook Pro, monitors, and ergonomic setup on your first day</p>
        </div>
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm text-center">
          <div class="w-12 h-12 mx-auto mb-4 rounded-xl bg-cyan-50 flex items-center justify-center"><i class="fas fa-coffee text-cyan-600"></i></div>
          <h4 class="font-bold text-gray-900 text-sm mb-1">Team Events</h4>
          <p class="text-gray-400 text-xs">Quarterly team retreats, happy hours, and social activities</p>
        </div>
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm text-center">
          <div class="w-12 h-12 mx-auto mb-4 rounded-xl bg-indigo-50 flex items-center justify-center"><i class="fas fa-globe text-indigo-600"></i></div>
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
          <button type="submit" class="btn-grad text-white font-semibold px-8 py-3 rounded-xl shadow-lg shadow-blue-500/25 w-full sm:w-auto">Submit Application <i class="fas fa-paper-plane ml-2"></i></button>
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
              <i class="fas fa-envelope"></i> Get In Touch
            </a>
            <a href="#positions" class="bg-white/10 backdrop-blur-md text-white font-semibold px-8 py-4 rounded-full border border-white/30 hover:bg-white/20 transition-all inline-flex items-center justify-center gap-2">
              <i class="fas fa-briefcase"></i> View Openings
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
