<?php
session_start();

require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../auth/auth.php';

if (is_logged_in()) {
  redirect(get_dashboard_url($_SESSION['user_role']));
}

generate_csrf_token();

$error = '';
if (isset($_SESSION['flash']['error'])) {
  $error = $_SESSION['flash']['error'];
  unset($_SESSION['flash']['error']);
}
if (isset($_SESSION['errors']) && !empty($_SESSION['errors'])) {
  $error = implode(' ', $_SESSION['errors']);
  unset($_SESSION['errors']);
}

$old_email = htmlspecialchars($_SESSION['form_data']['email'] ?? '');
unset($_SESSION['form_data']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login – JobHub</title>
  <meta name="description" content="Sign in to your JobHub account to manage jobs, contracts, and payments." />
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
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
            'fade-up': 'fadeUp 0.5s ease forwards',
          },
          keyframes: {
            fadeUp: {
              '0%': {
                opacity: 0,
                transform: 'translateY(16px)'
              },
              '100%': {
                opacity: 1,
                transform: 'translateY(0)'
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
      transform: translateY(-1px);
    }

    .orb {
      position: absolute;
      border-radius: 50%;
      filter: blur(80px);
      opacity: .12;
      animation: float 6s ease-in-out infinite;
    }

    @keyframes float {

      0%,
      100% {
        transform: translateY(0)
      }

      50% {
        transform: translateY(-12px)
      }
    }

    .fld {
      transition: border-color .2s, box-shadow .2s;
    }

    .fld:focus {
      outline: none;
      border-color: #2563eb;
      box-shadow: 0 0 0 3px rgba(37, 99, 235, .15);
    }

    @keyframes shake {

      0%,
      100% {
        transform: translateX(0)
      }

      20%,
      60% {
        transform: translateX(-5px)
      }

      40%,
      80% {
        transform: translateX(5px)
      }
    }

    .shake {
      animation: shake .4s ease;
    }
  </style>
</head>

<body class="min-h-screen relative overflow-x-hidden">

  <!-- Background decorations -->
  <div class="orb w-[500px] h-[500px] bg-blue-400 -top-40 -right-40" style="animation-delay:0s"></div>
  <div class="orb w-[400px] h-[400px] bg-cyan-300 -bottom-32 -left-32" style="animation-delay:3s"></div>

  <!-- Grid pattern -->
  <div class="absolute inset-0 pointer-events-none opacity-[0.03]" style="background-image:radial-gradient(circle,#2563eb 1px,transparent 1px);background-size:24px 24px;"></div>

  <!-- NAVBAR -->
  <nav class="relative z-20 bg-white/80 backdrop-blur-md border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between">
      <a href="../index.php" class="flex items-center gap-2.5">
        <div class="w-9 h-9 rounded-xl btn-grad flex items-center justify-center shadow-lg shadow-blue-500/25">
          <i class="fas fa-bolt text-white text-sm"></i>
        </div>
        <span class="text-xl font-extrabold tracking-tight">
          <span class="text-gray-900">Freelance</span><span class="grad-text">Hub</span>
        </span>
      </a>
      <div class="flex items-center gap-3">
        <a href="login.php" class="text-sm font-semibold text-gray-600 hover:text-gray-900 border border-gray-200 hover:border-primary px-4 py-2 rounded-lg transition-all">Log In</a>
        <a href="register.php" class="btn-grad text-sm font-semibold text-white px-5 py-2 rounded-lg shadow-lg shadow-blue-500/25">Sign Up</a>
      </div>
    </div>
  </nav>

  <!-- MAIN CONTENT -->
  <main class="relative z-10 flex items-center justify-center px-4 py-12 min-h-[calc(100vh-57px)]">

    <div class="w-full max-w-[550px] fade-up">

      <!-- Header -->
      <div class="text-center mb-8">
        <div class="w-14 h-14 rounded-2xl btn-grad flex items-center justify-center mx-auto mb-4 shadow-lg shadow-blue-500/25">
          <i class="fas fa-sign-in-alt text-white text-lg"></i>
        </div>
        <h1 class="text-3xl font-black text-gray-900 mb-2">Welcome Back</h1>
        <p class="text-gray-500 text-sm">Sign in to your JobHub account</p>
      </div>

      <!-- Flash: Error -->
      <?php if (!empty($error)): ?>
        <div class="mb-5 rounded-xl p-3 bg-red-50 border border-red-200 shake">
          <div class="flex items-center gap-2.5">
            <i class="fas fa-exclamation-triangle text-red-500 text-sm"></i>
            <p class="text-red-600 text-xs"><?= sanitize_string($error) ?></p>
          </div>
        </div>
      <?php endif; ?>

      <!-- Form Card -->
      <div class="bg-white rounded-2xl p-6 sm:p-8 border border-gray-100 shadow-sm">

        <form id="login-form" action="login_process.php" method="POST" novalidate>
          <?= csrf_field() ?>

          <!-- Email -->
          <div class="mb-4">
            <label for="email" class="block text-xs font-semibold text-gray-700 mb-1.5">Email Address</label>
            <div class="relative">
              <i class="fas fa-envelope absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
              <input type="email" id="email" name="email"
                value="<?= $old_email ?>"
                placeholder="you@example.com"
                autocomplete="email"
                class="fld w-full bg-gray-50 border border-gray-200 rounded-xl pl-9 pr-4 py-2.5 text-gray-900 placeholder-gray-400 text-sm"
                required />
            </div>
            <p id="email-err" class="text-red-500 text-[11px] mt-1 hidden"></p>
          </div>

          <!-- Password -->
          <div class="mb-5">
            <div class="flex items-center justify-between mb-1.5">
              <label for="password" class="block text-xs font-semibold text-gray-700">Password</label>
            </div>
            <div class="relative">
              <i class="fas fa-lock absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
              <input type="password" id="password" name="password"
                placeholder="Enter your password"
                autocomplete="current-password"
                class="fld w-full bg-gray-50 border border-gray-200 rounded-xl pl-9 pr-10 py-2.5 text-gray-900 placeholder-gray-400 text-sm"
                required />
              <button type="button" onclick="togglePwd('password','eye1')"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                <i id="eye1" class="fas fa-eye text-xs"></i>
              </button>
            </div>
            <p id="password-err" class="text-red-500 text-[11px] mt-1 hidden"></p>
          </div>

          <!-- Remember Me -->
          <div class="flex items-center justify-between mb-6">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" id="remember" name="remember" class="w-4 h-4 rounded accent-blue-600" />
              <span class="text-sm text-gray-500">Remember me</span>
            </label>
            <div>
              <a href="forgot_password.php" class="text-xs text-blue-600 hover:text-blue-700 transition-colors font-medium">
                Forgot password?
              </a>
            </div>
          </div>

          <!-- Submit -->
          <button type="submit" id="submit-btn"
            class="btn-grad w-full text-white font-bold py-3 rounded-xl text-sm shadow-lg shadow-blue-500/25 flex items-center justify-center gap-2 disabled:opacity-50">
            <i class="fas fa-sign-in-alt" id="submit-icon"></i>
            <span id="submit-text">Sign In</span>
          </button>
        </form>
      </div>

      <!-- Register Link -->
      <p class="text-center text-gray-500 text-sm mt-6">
        Don't have an account?
        <a href="register.php" class="text-blue-600 hover:text-blue-700 font-semibold transition-colors">Sign Up</a>
      </p>

      <p class="text-center text-gray-400 text-[11px] mt-4">
        &copy; 2026 JobHub. All rights reserved.
      </p>
    </div>
  </main>

  <script>
    function togglePwd(fieldId, eyeId) {
      const f = document.getElementById(fieldId);
      const e = document.getElementById(eyeId);
      if (f.type === 'password') {
        f.type = 'text';
        e.classList.replace('fa-eye', 'fa-eye-slash');
      } else {
        f.type = 'password';
        e.classList.replace('fa-eye-slash', 'fa-eye');
      }
    }

    document.getElementById('login-form').addEventListener('submit', function(e) {
      let valid = true;

      const email = document.getElementById('email').value.trim();
      const emailErr = document.getElementById('email-err');
      const emailReg = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!email || !emailReg.test(email)) {
        emailErr.textContent = 'Please enter a valid email address.';
        emailErr.classList.remove('hidden');
        valid = false;
      } else {
        emailErr.classList.add('hidden');
      }

      const pwd = document.getElementById('password').value;
      const pwdErr = document.getElementById('password-err');
      if (!pwd || pwd.length < 1) {
        pwdErr.textContent = 'Password is required.';
        pwdErr.classList.remove('hidden');
        valid = false;
      } else {
        pwdErr.classList.add('hidden');
      }

      if (!valid) {
        e.preventDefault();
        const firstErr = document.querySelector('[id$="-err"]:not(.hidden)');
        if (firstErr) firstErr.scrollIntoView({
          behavior: 'smooth',
          block: 'center'
        });
        return;
      }

      const btn = document.getElementById('submit-btn');
      const icon = document.getElementById('submit-icon');
      const txt = document.getElementById('submit-text');
      btn.disabled = true;
      icon.className = 'fas fa-spinner fa-spin';
      txt.textContent = 'Signing in\u2026';
    });
  </script>
</body>

</html>