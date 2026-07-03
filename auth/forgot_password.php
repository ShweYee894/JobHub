<?php
session_start();

require_once __DIR__ . '/../config/helpers.php';

generate_csrf_token();

$success = '';
$error   = '';

if (isset($_SESSION['flash']['success'])) {
    $success = $_SESSION['flash']['success'];
    unset($_SESSION['flash']['success']);
}
if (isset($_SESSION['flash']['error'])) {
    $error = $_SESSION['flash']['error'];
    unset($_SESSION['flash']['error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Forgot Password – FreelanceHub</title>
  <meta name="description" content="Reset your FreelanceHub account password." />
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { inter: ['Inter','sans-serif'] },
          colors: {
            primary: { DEFAULT:'#2563eb', dark:'#1d4ed8', light:'#3b82f6' },
            accent: { DEFAULT:'#0ea5e9', dark:'#0284c7' },
            surface: { DEFAULT:'#f8fafc', card:'#ffffff', border:'#e2e8f0' },
          },
          animation: {
            'fade-up': 'fadeUp 0.5s ease forwards',
          },
          keyframes: {
            fadeUp: {
              '0%': { opacity: 0, transform: 'translateY(16px)' },
              '100%': { opacity: 1, transform: 'translateY(0)' }
            }
          }
        }
      }
    }
  </script>
  <style>
    * { font-family: 'Inter', sans-serif; }
    body { background: #f8fafc; color: #1e293b; }

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
    @keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-12px)} }

    .fld { transition: border-color .2s, box-shadow .2s; }
    .fld:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.15); }

    @keyframes shake { 0%,100%{transform:translateX(0)} 20%,60%{transform:translateX(-5px)} 40%,80%{transform:translateX(5px)} }
    .shake { animation: shake .4s ease; }
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

    <div class="w-full max-w-[500px] fade-up">

      <!-- Header -->
      <div class="text-center mb-8">
        <div class="w-14 h-14 rounded-2xl btn-grad flex items-center justify-center mx-auto mb-4 shadow-lg shadow-blue-500/25">
          <i class="fas fa-key text-white text-lg"></i>
        </div>
        <h1 class="text-3xl font-black text-gray-900 mb-2">Forgot Password?</h1>
        <p class="text-gray-500 text-sm">Enter your email and we'll send you a reset link</p>
      </div>

      <!-- Flash: Success -->
      <?php if ($success): ?>
      <div class="mb-5 rounded-xl p-4 bg-emerald-50 border border-emerald-200">
        <div class="flex items-start gap-3">
          <i class="fas fa-circle-check text-emerald-500 text-base mt-0.5"></i>
          <div>
            <p class="text-emerald-700 text-sm font-medium"><?= sanitize_string($success) ?></p>
            <p class="text-emerald-600 text-xs mt-1">Check your inbox and follow the instructions to reset your password.</p>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Flash: Error -->
      <?php if ($error): ?>
      <div class="mb-5 rounded-xl p-3 bg-red-50 border border-red-200 shake">
        <div class="flex items-center gap-2.5">
          <i class="fas fa-exclamation-triangle text-red-500 text-sm"></i>
          <p class="text-red-600 text-xs"><?= sanitize_string($error) ?></p>
        </div>
      </div>
      <?php endif; ?>

      <!-- Form Card -->
      <div class="bg-white rounded-2xl p-6 sm:p-8 border border-gray-100 shadow-sm">

        <form id="forgot-form" action="forgot_password_process.php" method="POST" novalidate>
          <?= csrf_field() ?>

          <!-- Email -->
          <div class="mb-6">
            <label for="email" class="block text-xs font-semibold text-gray-700 mb-1.5">Email Address</label>
            <div class="relative">
              <i class="fas fa-envelope absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
              <input type="email" id="email" name="email"
                placeholder="you@example.com"
                autocomplete="email"
                class="fld w-full bg-gray-50 border border-gray-200 rounded-xl pl-9 pr-4 py-2.5 text-gray-900 placeholder-gray-400 text-sm"
                required/>
            </div>
            <p id="email-err" class="text-red-500 text-[11px] mt-1 hidden"></p>
          </div>

          <!-- Submit -->
          <button type="submit" id="submit-btn"
            class="btn-grad w-full text-white font-bold py-3 rounded-xl text-sm shadow-lg shadow-blue-500/25 flex items-center justify-center gap-2">
            <i class="fas fa-paper-plane" id="submit-icon"></i>
            <span id="submit-text">Send Reset Link</span>
          </button>
        </form>
      </div>

      <!-- Back to Login -->
      <p class="text-center text-gray-500 text-sm mt-6">
        Remember your password?
        <a href="login.php" class="text-blue-600 hover:text-blue-700 font-semibold transition-colors">Sign In</a>
      </p>

      <p class="text-center text-gray-400 text-[11px] mt-4">
        &copy; 2026 FreelanceHub. All rights reserved.
      </p>
    </div>
  </main>

<script>
document.getElementById('forgot-form').addEventListener('submit', function(e) {
  const email = document.getElementById('email').value.trim();
  const emailErr = document.getElementById('email-err');
  const emailReg = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  if (!email || !emailReg.test(email)) {
    e.preventDefault();
    emailErr.textContent = 'Please enter a valid email address.';
    emailErr.classList.remove('hidden');
    document.getElementById('email').focus();
    return;
  }

  const btn  = document.getElementById('submit-btn');
  const icon = document.getElementById('submit-icon');
  const txt  = document.getElementById('submit-text');
  btn.disabled = true;
  icon.className = 'fas fa-spinner fa-spin';
  txt.textContent = 'Sending\u2026';
});
</script>
</body>
</html>
