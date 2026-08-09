<?php

/**
 * Common Header Component
 * Included at the top of every page. Outputs <!DOCTYPE>, <head>, opening <body>, and navbar.
 */
if (session_status() === PHP_SESSION_NONE)
    session_start();
require_once __DIR__ . '/../config/db.php';

$_base = '/jobhub';
$_user_id = $_SESSION['user_id'] ?? null;
$_user_role = $_SESSION['user_role'] ?? null;
$_user_name = $_SESSION['user_name'] ?? '';
$_user_email = $_SESSION['user_email'] ?? '';
$_profile_img = $_SESSION['profile_image'] ?? '';
$_page_title = $page_title ?? 'JobHub';
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title><?= sanitize_string($_page_title) ?> – JobHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="/jobhub/assets/css/theme.css">
    <script src="https://unpkg.com/lucide@0.344.0/dist/umd/lucide.min.js"></script>
    <script>
    tailwind.config={
        theme:{extend:{fontFamily:{inter:['Inter','sans-serif']},colors:{primary:{DEFAULT:'#2563eb',dark:'#1d4ed8',light:'#3b82f6'},accent:{DEFAULT:'#0ea5e9',dark:'#0284c7'},surface:{DEFAULT:'#f8fafc',card:'#ffffff',border:'#e2e8f0'}}}}
    }
    </script>
    <style>
    *{font-family:'Inter',sans-serif}
    body{background:#f8fafc;color:#1e293b}
    .grad-text{background:linear-gradient(135deg,#2563eb 0%,#0ea5e9 60%,#6366f1 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
    .btn-grad{background:linear-gradient(135deg,#2563eb,#0ea5e9);transition:opacity .25s,transform .2s}
    .btn-grad:hover{opacity:.88;transform:translateY(-2px)}
    .glass{background:rgba(255,255,255,.85);backdrop-filter:blur(14px);border:1px solid rgba(226,232,240,.8)}
    .sidebar-link{transition:all .2s}
    .sidebar-link:hover,.sidebar-link.active{background:linear-gradient(135deg,rgba(37,99,235,.08),rgba(14,165,233,.08));color:#2563eb;border-left:3px solid #2563eb}
    </style>
    <?php if (isset($extra_head)) echo $extra_head; ?>
</head>
<body>
<script>
function fixIcons() {
    lucide.createIcons();
    document.querySelectorAll('svg[data-lucide]').forEach(function(svg) {
        svg.removeAttribute('width');svg.removeAttribute('height');
        svg.style.removeProperty('width');svg.style.removeProperty('height');
        var p = svg.parentElement;
        if (p && p.tagName === 'I') { var fs = window.getComputedStyle(p).fontSize; svg.style.width = fs; svg.style.height = fs; }
    });
}
fixIcons();
</script>
