<?php
/**
 * 404 Error Page
 * Displayed when a page is not found.
 */
require_once __DIR__ . '/../config/helpers.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found - JobHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        .btn-grad { background: linear-gradient(135deg, #2563eb, #0ea5e9); transition: opacity .25s, transform .2s; }
        .btn-grad:hover { opacity: .88; transform: translateY(-2px); }
        .float-1 { animation: float1 6s ease-in-out infinite; }
        .float-2 { animation: float2 8s ease-in-out infinite; }
        .float-3 { animation: float3 7s ease-in-out infinite; }
        @keyframes float1 { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-20px)} }
        @keyframes float2 { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-15px)} }
        @keyframes float3 { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-25px)} }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 via-blue-50/30 to-gray-50 min-h-screen flex items-center justify-center px-4">
    <div class="text-center max-w-lg">
        <!-- Floating decorative elements -->
        <div class="relative mb-8">
            <div class="float-1 absolute -top-4 left-1/4 w-16 h-16 rounded-2xl bg-blue-100 opacity-60"></div>
            <div class="float-2 absolute -top-2 right-1/4 w-12 h-12 rounded-xl bg-cyan-100 opacity-60"></div>
            <div class="float-3 absolute top-6 left-1/2 w-10 h-10 rounded-lg bg-violet-100 opacity-60"></div>
            
            <!-- 404 number -->
            <div class="relative z-10">
                <span class="text-[120px] sm:text-[160px] font-black bg-gradient-to-r from-blue-600 via-cyan-500 to-violet-600 bg-clip-text text-transparent leading-none select-none">404</span>
            </div>
        </div>

        <!-- Message -->
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-3">Page Not Found</h1>
        <p class="text-gray-500 text-sm sm:text-base mb-8 leading-relaxed">
            The page you're looking for doesn't exist or has been moved.<br>
            Let's get you back on track.
        </p>

        <!-- Actions -->
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
            <a href="javascript:history.back()" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-all">
                <i class="fas fa-arrow-left text-xs"></i> Go Back
            </a>
            <a href="/finalproject/index.php" class="btn-grad inline-flex items-center gap-2 px-6 py-3 rounded-xl text-white text-sm font-semibold shadow-lg shadow-blue-500/25">
                <i class="fas fa-home text-xs"></i> Go Home
            </a>
        </div>

        <!-- Help links -->
        <div class="mt-10 pt-6 border-t border-gray-200">
            <p class="text-xs text-gray-400 mb-3">Need help?</p>
            <div class="flex items-center justify-center gap-4 text-xs">
                <a href="/finalproject/auth/login.php" class="text-blue-500 hover:text-blue-600 font-medium transition-colors">Login</a>
                <span class="text-gray-300">|</span>
                <a href="/finalproject/auth/register.php" class="text-blue-500 hover:text-blue-600 font-medium transition-colors">Register</a>
                <span class="text-gray-300">|</span>
                <a href="/finalproject/index.php" class="text-blue-500 hover:text-blue-600 font-medium transition-colors">Home</a>
            </div>
        </div>
    </div>
</body>
</html>
