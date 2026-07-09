<?php
session_start();

if (isset($_SESSION['user_id'])) {
  $role = $_SESSION['user_role'] ?? '';
  $dest = match ($role) {
    'admin' => '../admin/dashboard.php',
    'client' => '../client/dashboard.php',
    'freelancer' => '../freelancer/dashboard.php',
    default => '../index.php',
  };
  header('Location: ' . $dest);
  exit;
}

require_once __DIR__ . '/../config/helpers.php';
generate_csrf_token();

$errors = $_SESSION['errors'] ?? [];
unset($_SESSION['errors']);

$old_name = htmlspecialchars($_SESSION['form_data']['name'] ?? '');
$old_email = htmlspecialchars($_SESSION['form_data']['email'] ?? '');
$old_role = $_SESSION['form_data']['role'] ?? 'freelancer';
$old_company = htmlspecialchars($_SESSION['form_data']['company_name'] ?? '');
$old_industry = htmlspecialchars($_SESSION['form_data']['industry'] ?? '');
$old_title = htmlspecialchars($_SESSION['form_data']['professional_title'] ?? '');
$old_rate = htmlspecialchars($_SESSION['form_data']['hourly_rate'] ?? '');
unset($_SESSION['form_data']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Create Account – JobHub</title>
  <meta name="description" content="Join JobHub — create your account as a freelancer or client and start collaborating on projects worldwide." />
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

    .role-btn { transition: all .2s; }
    .role-btn.active {
      background: linear-gradient(135deg, #2563eb, #0ea5e9);
      color: #fff;
      border-color: transparent;
      box-shadow: 0 4px 20px rgba(37,99,235,.3);
    }
    .role-btn:not(.active):hover { border-color: #2563eb; }

    .strength-seg { height: 3px; border-radius: 2px; flex: 1; background: #e2e8f0; transition: background .3s; }

    @keyframes shake { 0%,100%{transform:translateX(0)} 20%,60%{transform:translateX(-5px)} 40%,80%{transform:translateX(5px)} }
    .shake { animation: shake .4s ease; }

    input[type=checkbox] { accent-color: #2563eb; }
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
          <i class="fas fa-user-plus text-white text-lg"></i>
        </div>
        <h1 class="text-3xl font-black text-gray-900 mb-2">Join JobHub</h1>
        <p class="text-gray-500 text-sm">Create your account and start your journey</p>
      </div>

      <!-- Flash: Errors -->
      <?php if (!empty($errors)): ?>
      <div class="mb-5 rounded-xl p-3 bg-red-50 border border-red-200 shake">
        <div class="flex items-start gap-2.5">
          <i class="fas fa-circle-exclamation text-red-500 text-sm mt-0.5"></i>
          <div>
            <?php foreach ($errors as $e): ?>
            <p class="text-red-600 text-xs"><?= sanitize_string($e) ?></p>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Form Card -->
      <div class="bg-white rounded-2xl p-6 sm:p-8 border border-gray-100 shadow-sm">

        <form id="reg-form" action="register_process.php" method="POST" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="role" id="role-input" value="<?= sanitize_string($old_role) ?>"/>

          <!-- Role Toggle -->
          <div class="mb-5">
            <p class="text-gray-500 text-xs font-medium mb-2.5">I want to join as</p>
            <div class="grid grid-cols-2 gap-2.5">
              <button type="button" id="btn-freelancer"
                onclick="setRole('freelancer')"
                class="role-btn <?= $old_role === 'freelancer' ? 'active' : '' ?> border border-gray-200 text-gray-600 rounded-xl py-3 flex flex-col items-center gap-1 cursor-pointer bg-gray-50">
                <i class="fas fa-laptop-code text-lg <?= $old_role === 'freelancer' ? 'text-white' : 'text-blue-500' ?>" id="icon-freelancer"></i>
                <span class="font-bold text-xs">Freelancer</span>
                <span class="text-[10px] opacity-60">Find work</span>
              </button>
              <button type="button" id="btn-client"
                onclick="setRole('client')"
                class="role-btn <?= $old_role === 'client' ? 'active' : '' ?> border border-gray-200 text-gray-600 rounded-xl py-3 flex flex-col items-center gap-1 cursor-pointer bg-gray-50">
                <i class="fas fa-user-tie text-lg <?= $old_role === 'client' ? 'text-white' : 'text-blue-500' ?>" id="icon-client"></i>
                <span class="font-bold text-xs">Client</span>
                <span class="text-[10px] opacity-60">Hire talent</span>
              </button>
            </div>
          </div>

          <!-- Name -->
          <div class="mb-4">
            <label for="name" class="block text-xs font-semibold text-gray-700 mb-1.5">Full Name</label>
            <div class="relative">
              <i class="fas fa-user absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
              <input type="text" id="name" name="name"
                value="<?= $old_name ?>"
                placeholder="John Doe"
                autocomplete="name"
                class="fld w-full bg-gray-50 border border-gray-200 rounded-xl pl-9 pr-4 py-2.5 text-gray-900 placeholder-gray-400 text-sm"
                required/>
            </div>
            <p id="name-err" class="text-red-500 text-[11px] mt-1 hidden"></p>
          </div>

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
                required/>
            </div>
            <p id="email-err" class="text-red-500 text-[11px] mt-1 hidden"></p>
          </div>

         

          <!-- Password -->
          <div class="mb-4">
            <label for="password" class="block text-xs font-semibold text-gray-700 mb-1.5">Password</label>
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
          <div class="mb-5">
            <label for="confirm_password" class="block text-xs font-semibold text-gray-700 mb-1.5">Confirm Password</label>
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

          <!-- Terms -->
          <div class="flex items-start gap-2.5 mb-6">
            <input type="checkbox" id="terms" name="terms" class="mt-0.5 w-4 h-4 rounded" required/>
            <label for="terms" class="text-xs text-gray-500 leading-relaxed">
              I agree to the
              <a href="../terms.php" class="text-blue-600 hover:text-blue-700 underline">Terms of Service</a>
              and
              <a href="../privacy.php" class="text-blue-600 hover:text-blue-700 underline">Privacy Policy</a>
            </label>
          </div>

          <!-- Submit -->
          <button type="submit" id="submit-btn"
            class="btn-grad w-full text-white font-bold py-3 rounded-xl text-sm shadow-lg shadow-blue-500/25 flex items-center justify-center gap-2">
            <i class="fas fa-arrow-right" id="submit-icon"></i>
            <span id="submit-text">Create Account</span>
          </button>
        </form>
      </div>

      <!-- Login Link -->
      <p class="text-center text-gray-500 text-sm mt-6">
        Already have an account?
        <a href="login.php" class="text-blue-600 hover:text-blue-700 font-semibold transition-colors">Sign In</a>
      </p>

      <p class="text-center text-gray-400 text-[11px] mt-4">
        &copy; 2026 JobHub. All rights reserved.
      </p>
    </div>
  </main>

<script>
let currentRole = '<?= sanitize_string($old_role) ?>';

function setRole(role) {
  currentRole = role;
  document.getElementById('role-input').value = role;

  ['freelancer','client'].forEach(r => {
    const btn  = document.getElementById('btn-' + r);
    const icon = document.getElementById('icon-' + r);
    if (r === role) {
      btn.classList.add('active');
      icon.classList.replace('text-blue-500','text-white');
    } else {
      btn.classList.remove('active');
      icon.classList.replace('text-white','text-blue-500');
    }
  });

  document.getElementById('client-fields').classList.toggle('hidden', role !== 'client');
  document.getElementById('freelancer-fields').classList.toggle('hidden', role !== 'freelancer');

  // Clear error messages for hidden fields
  ['title-err','rate-err'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.classList.add('hidden');
  });
}

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

['name','email'].forEach(id => {
  document.getElementById(id).addEventListener('blur', function() {
    const v = this.value.trim();
    const errEl = document.getElementById(id + '-err');
    if (id === 'name' && v && v.length < 2) {
      errEl.textContent = 'Name must be at least 2 characters.';
      errEl.classList.remove('hidden');
    } else if (id === 'email' && v && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) {
      errEl.textContent = 'Please enter a valid email address.';
      errEl.classList.remove('hidden');
    } else {
      errEl.classList.add('hidden');
    }
  });
  document.getElementById(id).addEventListener('input', function() {
    document.getElementById(id + '-err').classList.add('hidden');
  });
});

document.getElementById('reg-form').addEventListener('submit', function(e) {
  let valid = true;

  // Name
  const name = document.getElementById('name').value.trim();
  const nameErr = document.getElementById('name-err');
  if (!name || name.length < 2) {
    nameErr.textContent = 'Please enter your full name (min. 2 characters).';
    nameErr.classList.remove('hidden');
    valid = false;
  } else { nameErr.classList.add('hidden'); }

  // Email
  const email = document.getElementById('email').value.trim();
  const emailErr = document.getElementById('email-err');
  if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    emailErr.textContent = 'Please enter a valid email address.';
    emailErr.classList.remove('hidden');
    valid = false;
  } else { emailErr.classList.add('hidden'); }

  // Freelancer fields
  if (currentRole === 'freelancer') {
    const title = document.getElementById('professional_title').value.trim();
    const titleErr = document.getElementById('title-err');
    if (!title || title.length < 3) {
      titleErr.textContent = 'Professional title is required (min. 3 characters).';
      titleErr.classList.remove('hidden');
      valid = false;
    } else { titleErr.classList.add('hidden'); }

    const rate = document.getElementById('hourly_rate').value;
    const rateErr = document.getElementById('rate-err');
    if (!rate || rate < 1 || rate > 5000) {
      rateErr.textContent = 'Hourly rate must be between $1 and $5,000.';
      rateErr.classList.remove('hidden');
      valid = false;
    } else { rateErr.classList.add('hidden'); }
  }

  // Password
  const pwd = document.getElementById('password').value;
  const pwdErr = document.getElementById('password-err');
  const score = updateStrength(pwd);
  if (score < 5) {
    pwdErr.textContent = 'Password must meet all requirements above.';
    pwdErr.classList.remove('hidden');
    valid = false;
  } else { pwdErr.classList.add('hidden'); }

  // Confirm Password
  const cpwd = document.getElementById('confirm_password').value;
  const confirmErr = document.getElementById('confirm-err');
  if (pwd !== cpwd) {
    confirmErr.textContent = '\u2717 Passwords do not match.';
    confirmErr.className = 'text-red-500 text-[11px] mt-1';
    confirmErr.classList.remove('hidden');
    valid = false;
  }

  // Terms
  if (!document.getElementById('terms').checked) {
    alert('Please accept the Terms & Conditions to continue.');
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
  txt.textContent = 'Creating account\u2026';
});
</script>
</body>
</html>
