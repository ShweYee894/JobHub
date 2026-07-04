<?php
session_start();

require_once __DIR__ . '/../config/helpers.php';

generate_csrf_token();

// ── Check if reset session is valid ────────────────────────────────────
$valid = isset($_SESSION['reset_token'])
      && isset($_SESSION['reset_expires'])
      && time() < $_SESSION['reset_expires'];

if (!$valid) {
    set_flash('error', 'Your password reset link has expired. Please request a new one.');
    header('Location: forgot_password.php');
    exit;
}

$error = '';
if (isset($_SESSION['flash']['error'])) {
    $error = $_SESSION['flash']['error'];
    unset($_SESSION['flash']['error']);
}

// Get the plain token to pass as a hidden field
$plain_token = $_SESSION['reset_token_plain'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reset Password – JobHub</title>
  <meta name="description" content="Reset your JobHub account password." />
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

    .strength-seg { height: 3px; border-radius: 2px; flex: 1; background: #e2e8f0; transition: background .3s; }

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
          <i class="fas fa-lock text-white text-lg"></i>
        </div>
        <h1 class="text-3xl font-black text-gray-900 mb-2">Reset Password</h1>
        <p class="text-gray-500 text-sm">Create a new password for your account</p>
      </div>

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

        <form id="reset-form" action="reset_password_process.php" method="POST" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="token" value="<?= sanitize_string($plain_token) ?>"/>

          <!-- New Password -->
          <div class="mb-4">
            <label for="password" class="block text-xs font-semibold text-gray-700 mb-1.5">New Password</label>
            <div class="relative">
              <i class="fas fa-lock absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
              <input type="password" id="password" name="password"
                placeholder="Min. 8 characters"
                autocomplete="new-password"
                class="fld w-full bg-gray-50 border border-gray-200 rounded-xl pl-9 pr-10 py-2.5 text-gray-900 placeholder-gray-400 text-sm"
                required/>
              <button type="button" onclick="togglePwd('password','eye1')"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                <i id="eye1" class="fas fa-eye text-xs"></i>
              </button>
            </div>
            <!-- Strength -->
            <div class="flex gap-1 mt-2">
              <div class="strength-seg" id="s1"></div>
              <div class="strength-seg" id="s2"></div>
              <div class="strength-seg" id="s3"></div>
              <div class="strength-seg" id="s4"></div>
              <div class="strength-seg" id="s5"></div>
            </div>
            <p id="strength-label" class="text-[10px] mt-1 text-gray-400"></p>
            <ul class="mt-1.5 grid grid-cols-2 gap-x-2 gap-y-0.5">
              <li id="req-len"   class="req-item text-[10px] text-gray-400 flex items-center gap-1"><i class="fas fa-circle text-[5px]"></i> 8+ characters</li>
              <li id="req-upper" class="req-item text-[10px] text-gray-400 flex items-center gap-1"><i class="fas fa-circle text-[5px]"></i> Uppercase</li>
              <li id="req-lower" class="req-item text-[10px] text-gray-400 flex items-center gap-1"><i class="fas fa-circle text-[5px]"></i> Lowercase</li>
              <li id="req-digit" class="req-item text-[10px] text-gray-400 flex items-center gap-1"><i class="fas fa-circle text-[5px]"></i> Number</li>
              <li id="req-spec"  class="req-item text-[10px] text-gray-400 flex items-center gap-1"><i class="fas fa-circle text-[5px]"></i> Special char</li>
            </ul>
            <p id="password-err" class="text-red-500 text-[11px] mt-1 hidden"></p>
          </div>

          <!-- Confirm Password -->
          <div class="mb-6">
            <label for="confirm_password" class="block text-xs font-semibold text-gray-700 mb-1.5">Confirm New Password</label>
            <div class="relative">
              <i class="fas fa-shield-halved absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
              <input type="password" id="confirm_password" name="confirm_password"
                placeholder="Repeat your password"
                autocomplete="new-password"
                class="fld w-full bg-gray-50 border border-gray-200 rounded-xl pl-9 pr-10 py-2.5 text-gray-900 placeholder-gray-400 text-sm"
                required/>
              <button type="button" onclick="togglePwd('confirm_password','eye2')"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                <i id="eye2" class="fas fa-eye text-xs"></i>
              </button>
            </div>
            <p id="confirm-err" class="text-red-500 text-[11px] mt-1 hidden"></p>
          </div>

          <!-- Submit -->
          <button type="submit" id="submit-btn"
            class="btn-grad w-full text-white font-bold py-3 rounded-xl text-sm shadow-lg shadow-blue-500/25 flex items-center justify-center gap-2">
            <i class="fas fa-check-circle" id="submit-icon"></i>
            <span id="submit-text">Reset Password</span>
          </button>
        </form>
      </div>

      <!-- Back to Login -->
      <p class="text-center text-gray-500 text-sm mt-6">
        <a href="login.php" class="text-blue-600 hover:text-blue-700 font-semibold transition-colors">
          <i class="fas fa-arrow-left mr-1 text-xs"></i>Back to Sign In
        </a>
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
    e.classList.replace('fa-eye','fa-eye-slash');
  } else {
    f.type = 'password';
    e.classList.replace('fa-eye-slash','fa-eye');
  }
}

const COLORS = { 0:'#e2e8f0', 1:'#ef4444', 2:'#f97316', 3:'#eab308', 4:'#22c55e', 5:'#06b6d4' };
const LABELS = ['','Very Weak','Weak','Fair','Strong','Very Strong'];

function evalPassword(pwd) {
  let score = 0;
  const rules = {
    len:   pwd.length >= 8,
    upper: /[A-Z]/.test(pwd),
    lower: /[a-z]/.test(pwd),
    digit: /[0-9]/.test(pwd),
    spec:  /[\W_]/.test(pwd),
  };
  Object.values(rules).forEach(v => { if(v) score++; });
  return { score, rules };
}

function updateStrength(pwd) {
  const { score, rules } = evalPassword(pwd);
  const color = COLORS[score];
  for (let i = 1; i <= 5; i++) {
    document.getElementById('s' + i).style.background = i <= score ? color : COLORS[0];
  }
  const lbl = document.getElementById('strength-label');
  lbl.textContent = pwd.length ? LABELS[score] : '';
  lbl.style.color = color;

  const map = { len:rules.len, upper:rules.upper, lower:rules.lower, digit:rules.digit, spec:rules.spec };
  Object.entries(map).forEach(([k,v]) => {
    const li = document.getElementById('req-' + k);
    if (!li) return;
    const ico = li.querySelector('i');
    if (v) {
      li.classList.replace('text-gray-400','text-emerald-500');
      ico.classList.replace('fa-circle','fa-check-circle');
    } else {
      li.classList.replace('text-emerald-500','text-gray-400');
      ico.classList.replace('fa-check-circle','fa-circle');
    }
  });
  return score;
}

document.getElementById('password').addEventListener('input', function(){
  updateStrength(this.value);
  checkMatch();
});

document.getElementById('confirm_password').addEventListener('input', checkMatch);

function checkMatch() {
  const p = document.getElementById('password').value;
  const c = document.getElementById('confirm_password').value;
  const el = document.getElementById('confirm-err');
  if (!c) { el.classList.add('hidden'); return; }
  if (p !== c) {
    el.textContent = '\u2717 Passwords do not match.';
    el.classList.remove('hidden');
    el.className = 'text-red-500 text-[11px] mt-1';
  } else {
    el.textContent = '\u2713 Passwords match!';
    el.className = 'text-emerald-500 text-[11px] mt-1';
  }
}

document.getElementById('reset-form').addEventListener('submit', function(e) {
  let valid = true;

  const pwd = document.getElementById('password').value;
  const pwdErr = document.getElementById('password-err');
  const score = updateStrength(pwd);
  if (score < 5) {
    pwdErr.textContent = 'Password must meet all requirements above.';
    pwdErr.classList.remove('hidden');
    valid = false;
  } else { pwdErr.classList.add('hidden'); }

  const cpwd = document.getElementById('confirm_password').value;
  const confirmErr = document.getElementById('confirm-err');
  if (pwd !== cpwd) {
    confirmErr.textContent = '\u2717 Passwords do not match.';
    confirmErr.className = 'text-red-500 text-[11px] mt-1';
    confirmErr.classList.remove('hidden');
    valid = false;
  }

  if (!valid) {
    e.preventDefault();
    const firstErr = document.querySelector('[id$="-err"]:not(.hidden)');
    if (firstErr) firstErr.scrollIntoView({ behavior:'smooth', block:'center' });
    return;
  }

  const btn  = document.getElementById('submit-btn');
  const icon = document.getElementById('submit-icon');
  const txt  = document.getElementById('submit-text');
  btn.disabled = true;
  icon.className = 'fas fa-spinner fa-spin';
  txt.textContent = 'Resetting\u2026';
});
</script>
</body>
</html>
