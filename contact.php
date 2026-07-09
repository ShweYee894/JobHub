<!DOCTYPE html>
<html lang="en" class="scroll-smooth">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Contact Us – JobHub</title>
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
            surface: {
              DEFAULT: '#f8fafc',
              card: '#ffffff',
              border: '#e2e8f0'
            },
          },
          animation: {
            'fade-up': 'fadeUp 0.6s ease forwards',
            'float': 'float 3s ease-in-out infinite'
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
        <a href="index.php#howitworks" class="nav-link hover:text-gray-900 transition-colors">How It Works</a>
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
    <div class="orb w-[500px] h-[500px] bg-blue-400 top-0 -right-40"></div>
    <div class="orb w-[400px] h-[400px] bg-cyan-300 bottom-0 -left-32" style="animation-delay:2s"></div>
    <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 text-center">
      <span class="text-xs font-bold uppercase tracking-widest text-blue-600/70 bg-blue-50 px-3 py-1 rounded-full">Reach Out</span>
      <h1 class="text-4xl sm:text-5xl font-black mt-4 mb-4 text-gray-900">Get In <span class="grad-text">Touch</span></h1>
      <p class="text-gray-500 max-w-2xl mx-auto">Have a question, feedback, or need assistance? We'd love to hear from you. Our team typically responds within 24 hours.</p>
    </div>
  </section>

  <!-- CONTACT SECTION -->
  <section class="py-20 px-4 reveal">
    <div class="max-w-7xl mx-auto">
      <div class="grid lg:grid-cols-5 gap-12">

        <!-- Contact Form -->
        <div class="lg:col-span-3">
          <div class="bg-white rounded-3xl p-8 sm:p-10 border border-gray-100 shadow-sm">
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Send Us a Message</h2>
            <p class="text-gray-400 text-sm mb-8">Fill out the form below and we'll get back to you as soon as possible.</p>
            <form action="actions/contact_process.php" method="POST" class="space-y-6">
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
                <label class="block text-sm font-semibold text-gray-700 mb-2">Subject</label>
                <select name="subject" required class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-gray-900 outline-none focus:border-primary focus:bg-white transition-all text-sm">
                  <option value="">Select a topic...</option>
                  <option value="general">General Inquiry</option>
                  <option value="support">Technical Support</option>
                  <option value="billing">Billing & Payments</option>
                  <option value="partnership">Partnership Opportunities</option>
                  <option value="feedback">Feedback & Suggestions</option>
                  <option value="other">Other</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Message</label>
                <textarea name="message" required rows="5" placeholder="Tell us how we can help you..." class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-gray-900 placeholder-gray-400 outline-none focus:border-primary focus:bg-white transition-all text-sm resize-none"></textarea>
              </div>
              <button type="submit" class="btn-grad text-white font-semibold px-8 py-3 rounded-xl shadow-lg shadow-blue-500/25 w-full sm:w-auto">Send Message <i class="fas fa-paper-plane ml-2"></i></button>
            </form>
          </div>
        </div>

        <!-- Sidebar Info -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Company Info -->
          <div class="bg-white rounded-3xl p-8 border border-gray-100 shadow-sm">
            <h3 class="text-lg font-bold text-gray-900 mb-6">Company Information</h3>
            <div class="space-y-5">
              <div class="flex items-start gap-4">
                <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0"><i class="fas fa-map-marker-alt text-blue-600 text-sm"></i></div>
                <div>
                  <p class="text-sm font-semibold text-gray-900">Address</p>
                  <p class="text-gray-400 text-sm">42 Tech Street, Innovation Tower<br />Yangon 11041, Myanmar</p>
                </div>
              </div>
              <div class="flex items-start gap-4">
                <div class="w-10 h-10 rounded-xl bg-cyan-50 flex items-center justify-center flex-shrink-0"><i class="fas fa-phone text-cyan-600 text-sm"></i></div>
                <div>
                  <p class="text-sm font-semibold text-gray-900">Phone</p>
                  <p class="text-gray-400 text-sm">+95 9 757 889806<br />+1 (555) 123-4567</p>
                </div>
              </div>
              <div class="flex items-start gap-4">
                <div class="w-10 h-10 rounded-xl bg-purple-50 flex items-center justify-center flex-shrink-0"><i class="fas fa-envelope text-purple-600 text-sm"></i></div>
                <div>
                  <p class="text-sm font-semibold text-gray-900">Email</p>
                  <p class="text-gray-400 text-sm">hello@jobhub.io<br />support@jobhub.io</p>
                </div>
              </div>
              <div class="flex items-start gap-4">
                <div class="w-10 h-10 rounded-xl bg-green-50 flex items-center justify-center flex-shrink-0"><i class="fas fa-clock text-green-600 text-sm"></i></div>
                <div>
                  <p class="text-sm font-semibold text-gray-900">Business Hours</p>
                  <p class="text-gray-400 text-sm">Mon - Fri: 9:00 AM - 6:00 PM (GMT+6)<br />Sat: 10:00 AM - 2:00 PM</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Social Media -->
          <div class="bg-white rounded-3xl p-8 border border-gray-100 shadow-sm">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Follow Us</h3>
            <p class="text-gray-400 text-sm mb-5">Stay connected and follow us on social media for updates.</p>
            <div class="flex gap-3">
              <a href="#" class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center text-blue-500 hover:bg-blue-100 transition-all"><i class="fab fa-twitter"></i></a>
              <a href="#" class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center text-blue-600 hover:bg-blue-100 transition-all"><i class="fab fa-linkedin-in"></i></a>
              <a href="#" class="w-10 h-10 bg-pink-50 rounded-xl flex items-center justify-center text-pink-500 hover:bg-pink-100 transition-all"><i class="fab fa-instagram"></i></a>
              <a href="#" class="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center text-gray-600 hover:bg-gray-200 transition-all"><i class="fab fa-github"></i></a>
              <a href="#" class="w-10 h-10 bg-red-50 rounded-xl flex items-center justify-center text-red-500 hover:bg-red-100 transition-all"><i class="fab fa-youtube"></i></a>
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