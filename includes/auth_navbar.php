<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<header class="fixed top-0 inset-x-0 z-50 transition-all duration-300 py-2 bg-white border-b border-gray-100">
  <nav class="max-w-[1320px] mx-auto px-6 flex items-center justify-between" aria-label="Authentication navigation">
    <a href="../index.php" class="flex items-center gap-2 shrink-0" aria-label="JobHub Home">
      <img src="../assets/upload/logos/logo.png" alt="JobHub Logo" class="w-[34px] h-[34px] rounded-lg object-cover">
      <span class="text-lg font-bold tracking-tight" style="font-family:'Playfair Display',Georgia,serif">
        <span class="text-[#1A1D23]">Job</span><span class="text-[#4338CA]">Hub</span>
      </span>
    </a>
    <div class="flex items-center">
      <?php if ($currentPage === 'login.php'): ?>
        <a href="register.php"
           class="text-[13px] font-semibold px-5 py-2 text-white transition-all"
           style="background:#4338CA;border-radius:4px">
          Sign Up
        </a>
      <?php else: ?>
        <a href="login.php"
           class="text-[13px] font-medium text-gray-500 hover:text-[#1A1D23] transition-colors px-4 py-2">
          Log In
        </a>
      <?php endif; ?>
    </div>
  </nav>
</header>
