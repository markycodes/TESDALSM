<?php
require_once __DIR__ . '/lib.php';
$user = current_user();
$page_title = $page_title ?? 'LearnHub';
$nav_active = $nav_active ?? '';
$flashes = take_flashes();
if ($user) { touch_presence((int) $user['id']); } // keep the heartbeat fresh on every page view
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<?php if (!empty($attendance_course)): ?><meta name="attendance-course" content="<?= (int) $attendance_course ?>"><?php endif; ?>
<title><?= e($page_title) ?> · LearnHub LMS</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: { extend: { fontFamily: { sans: ['Inter','ui-sans-serif','system-ui','sans-serif'] } } }
  };
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ============================================================
   LearnHub v2 — advanced design system (part 1)
   Utility-scoped overrides keep every page's logic intact.
   ============================================================ */
:root{--lh-grad:linear-gradient(135deg,#047857 0%,#059669 50%,#10b981 100%)}

html{scroll-behavior:smooth}
::selection{background:#d1fae5;color:#064e3b}

/* aurora page background */
body{background:
  radial-gradient(1100px 560px at 88% -8%,rgba(5,150,105,.16),transparent 60%),
  radial-gradient(950px 520px at -8% 18%,rgba(4,120,87,.13),transparent 55%),
  radial-gradient(900px 620px at 50% 112%,rgba(6,182,212,.10),transparent 60%),
  #f2f8f4}

/* thin gradient scrollbars */
*{scrollbar-width:thin;scrollbar-color:#6ee7b7 transparent}
*::-webkit-scrollbar{height:8px;width:8px}
*::-webkit-scrollbar-track{background:transparent}
*::-webkit-scrollbar-thumb{background:linear-gradient(#34d399,#6ee7b7);border-radius:99px}

/* page enter animation */
main{animation:pageIn .45s cubic-bezier(.22,.61,.36,1) both}
@keyframes pageIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}

/* glass nav */
nav.glass{background:rgba(255,255,255,.72);backdrop-filter:blur(16px) saturate(1.5);-webkit-backdrop-filter:blur(16px) saturate(1.5);box-shadow:0 8px 28px -14px rgba(4,120,87,.22)}
nav.glass::after{content:'';position:absolute;left:0;right:0;bottom:0;height:2px;background:var(--lh-grad);opacity:.45}

/* scroll progress bar */
#lh-progress{position:fixed;top:0;left:0;height:3px;width:0;background:var(--lh-grad);z-index:60;box-shadow:0 0 14px rgba(5,150,105,.55)}

/* gradient logo with pulse glow */
.lh-logo{background:var(--lh-grad);box-shadow:0 8px 20px -8px rgba(5,150,105,.6);position:relative;transition:transform .25s}
.lh-logo:hover{transform:scale(1.06) rotate(-3deg)}
.lh-logo::before{content:'';position:absolute;inset:-5px;border-radius:inherit;background:var(--lh-grad);opacity:.35;filter:blur(12px);z-index:-1;animation:logoPulse 3.2s ease-in-out infinite}
@keyframes logoPulse{0%,100%{opacity:.22}50%{opacity:.5}}

/* desktop nav pills */
.lh-pill{position:relative;border-radius:12px;transition:color .2s,background-color .2s,box-shadow .2s,transform .2s}
.lh-pill:hover{color:#047857!important;background:#ecfdf5!important}
/* active state: original header colors preserved (no gradient) */

/* mobile icon buttons */
.lh-ico{position:relative;border-radius:12px;transition:all .2s}
.lh-ico:hover{transform:translateY(-1px);color:#047857!important;background:#ecfdf5!important}

/* avatar with gradient ring + online dot */
.lh-avatar{background:var(--lh-grad);box-shadow:0 0 0 2px #fff,0 0 0 4px rgba(5,150,105,.30);position:relative}
.lh-avatar::after{content:'';position:absolute;right:-2px;bottom:-2px;width:11px;height:11px;border-radius:99px;background:#10b981;border:2px solid #fff}

/* guest CTA */
.lh-cta{background-image:var(--lh-grad);background-color:#047857;box-shadow:0 10px 22px -10px rgba(5,150,105,.65);transition:all .25s}
.lh-cta:hover{transform:translateY(-2px);filter:brightness(1.07)}

/* ---------- nav components via existing utility classes (no markup change) ---------- */
/* desktop pills */
nav .sm\:flex a{position:relative;border-radius:12px;transition:color .2s,background-color .2s,box-shadow .2s,transform .2s}
nav .sm\:flex a:not(.bg-indigo-50):hover{color:#047857!important;background:#ecfdf5!important}
/* active state: original header colors preserved (bg-indigo-50 text-indigo-700, no gradient) */
/* mobile icon buttons */
nav .sm\:hidden a{position:relative;border-radius:12px;transition:color .2s,background-color .2s,box-shadow .2s,transform .2s}
nav .sm\:hidden a:not(.bg-indigo-600):hover{color:#047857!important;background:#ecfdf5!important;transform:translateY(-1px)}
/* active state: original header colors preserved (bg-indigo-600 text-white, no gradient) */
/* gradient logo */
nav a.grid.rounded-xl.bg-indigo-600{background-image:var(--lh-grad);background-color:#047857;box-shadow:0 8px 20px -8px rgba(5,150,105,.6);position:relative;transition:transform .25s}
nav a.grid.rounded-xl.bg-indigo-600:hover{transform:scale(1.06) rotate(-3deg)}
/* avatar: gradient ring + online dot */
nav span.rounded-full.bg-indigo-600{background-image:var(--lh-grad);background-color:#047857;box-shadow:0 0 0 2px #fff,0 0 0 4px rgba(5,150,105,.3);position:relative}
nav span.rounded-full.bg-indigo-600::after{content:'';position:absolute;right:-2px;bottom:-2px;width:11px;height:11px;border-radius:99px;background:#10b981;border:2px solid #fff}
/* guest CTA */
nav a.px-4.bg-indigo-600{background-image:var(--lh-grad);background-color:#047857;box-shadow:0 10px 22px -10px rgba(5,150,105,.65);transition:all .25s}
nav a.px-4.bg-indigo-600:hover{transform:translateY(-2px);filter:brightness(1.07)}

/* ---------- site-wide card upgrade (glass + soft depth) ---------- */
main .bg-white.ring-1{background:rgba(255,255,255,.85);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);box-shadow:0 12px 32px -14px rgba(15,23,42,.14),inset 0 0 0 1px rgba(255,255,255,.65)}
main .rounded-2xl.bg-white.ring-1,main .rounded-3xl.bg-white.ring-1{transition:box-shadow .3s,transform .3s}
main .rounded-2xl.bg-white.ring-1:hover,main .rounded-3xl.bg-white.ring-1:hover{box-shadow:0 22px 44px -18px rgba(4,120,87,.26)}

/* gradient primary buttons site-wide */
main a.bg-indigo-600,main button.bg-indigo-600{background-image:var(--lh-grad);background-color:#047857;box-shadow:0 12px 24px -12px rgba(4,120,87,.7);transition:all .25s}
main a.bg-indigo-600:hover,main button.bg-indigo-600:hover{transform:translateY(-2px);filter:brightness(1.08);box-shadow:0 18px 30px -12px rgba(4,120,87,.75)}

/* progress bars: gradient + shimmer */
main .h-2>.h-full,main .h-2\.5>.h-full,main .h-3>.h-full{background-image:var(--lh-grad);background-color:#059669;position:relative;overflow:hidden}
main .h-2>.h-full::after,main .h-2\.5>.h-full::after,main .h-3>.h-full::after{content:'';position:absolute;inset:0;background:repeating-linear-gradient(45deg,rgba(255,255,255,.28) 0 8px,transparent 8px 18px);animation:shimmer 1.4s linear infinite}
@keyframes shimmer{from{transform:translateX(-18px)}to{transform:translateX(18px)}}

/* hero sections: animated aurora */
main>section.bg-gradient-to-r{position:relative;isolation:isolate;background-size:180% 180%!important;animation:heroShift 14s ease-in-out infinite}
main>section.bg-gradient-to-r::before{content:'';position:absolute;inset:0;z-index:-1;pointer-events:none;background:radial-gradient(420px 220px at 15% 15%,rgba(255,255,255,.4),transparent 60%),radial-gradient(520px 280px at 85% 85%,rgba(255,255,255,.25),transparent 60%);mix-blend-mode:overlay;animation:blobFloat 10s ease-in-out infinite alternate}
@keyframes heroShift{0%,100%{background-position:0% 50%}50%{background-position:100% 50%}}
@keyframes blobFloat{from{transform:translate(0,0) scale(1)}to{transform:translate(34px,-22px) scale(1.1)}}

/* forms */
main input:focus,main select:focus,main textarea:focus{outline:none;border-color:#10b981;box-shadow:0 0 0 4px rgba(5,150,105,.14)}

/* floating back-to-top */
#lh-top{position:fixed;right:20px;bottom:20px;z-index:50;opacity:0;pointer-events:none;transform:translateY(10px);transition:all .3s}
#lh-top.show{opacity:1;pointer-events:auto;transform:none}
#lh-top:hover{filter:brightness(1.08)}

/* ---------- preserved function styles (toast / reveal / swipe hint) ---------- */
.toast-in{animation:toastIn .25s ease-out}@keyframes toastIn{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:none}}
.reveal{opacity:0;transform:translateY(18px);transition:opacity .55s cubic-bezier(.22,.61,.36,1),transform .55s cubic-bezier(.22,.61,.36,1)}
.reveal.in{opacity:1;transform:none}
.no-scrollbar::-webkit-scrollbar{display:none}
.no-scrollbar{-ms-overflow-style:none;scrollbar-width:none}
/* Mobile swipe hint: right-edge fade + nudge chevron; hidden on desktop or after first swipe */
.swipe-hint::after{content:'';position:absolute;top:0;bottom:0;right:0;width:16px;pointer-events:none;background:linear-gradient(to left,rgba(255,255,255,.95),rgba(255,255,255,0))}
.swipe-hint .swipe-chevron{position:absolute;right:3px;top:50%;transform:translate(0,-50%);z-index:10;display:grid;place-items:center;width:26px;height:26px;border-radius:9999px;background:#1e293b;color:#fff;font-size:14px;line-height:1;box-shadow:0 4px 10px rgba(15,23,42,.25);animation:nudge 1.4s ease-in-out infinite;cursor:pointer}
.swipe-hint .swipe-chevron:active{background:#059669}
@keyframes nudge{0%,100%{margin-right:0}50%{margin-right:4px}}
.swipe-hint.hint-off::after{display:none}
.swipe-hint.hint-off .swipe-chevron{display:none}
@media (min-width:640px){.swipe-hint::after{display:none}.swipe-hint .swipe-chevron{display:none}}

@media (prefers-reduced-motion:reduce){
  .reveal{opacity:1;transform:none;transition:none}
  main{animation:none}
  nav a.grid.rounded-xl.bg-indigo-600::before{animation:none}
  main>section.bg-gradient-to-r{animation:none;background-size:100% 100%!important}
  main>section.bg-gradient-to-r::before{animation:none}
  main .h-2>.h-full::after,main .h-2\.5>.h-full::after,main .h-3>.h-full::after{animation:none}
  .swipe-hint .swipe-chevron{animation:none}
  #lh-progress{transition:none}
}


/* ============================================================
   Redesign shell: fixed sidebar + top bar + notification panel
   ============================================================ */
.lh-app{background:#eef3ee}
.lh-sidebar{position:fixed;top:0;left:0;bottom:0;width:264px;z-index:35;background:rgba(255,255,255,.97);border-right:1px solid rgba(226,232,240,.92);box-shadow:0 24px 48px -24px rgba(4,120,87,.18);transform:translateX(-280px);transition:transform .25s ease;display:flex;flex-direction:column}
.lh-sidebar.open{transform:none}
@media(min-width:1024px){.lh-sidebar{transform:none;box-shadow:none}}
.lh-backdrop{position:fixed;inset:0;z-index:34;background:rgba(15,23,42,.35);display:none}
body.lh-side-open .lh-backdrop{display:block}
@media(min-width:1024px){.lh-backdrop{display:none!important}}
body.lh-app{padding-left:0}
@media(min-width:1024px){body.lh-app{padding-left:264px}}
.lh-main{transition:padding-left .25s ease}
.lh-side-head{display:flex;align-items:center;gap:.6rem;padding:.9rem 1rem .8rem;border-bottom:1px solid rgba(226,232,240,.9)}
.lh-side-sec{padding:.9rem 1rem .35rem;font-size:10px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#94a3b8}
.lh-side-nav{display:flex;flex-direction:column;gap:2px;padding:0 .65rem}
.lh-side-link{display:flex;align-items:center;gap:.65rem;padding:.55rem .7rem;font-size:.875rem;font-weight:600;color:#475569;border-radius:12px;transition:all .15s;position:relative}
.lh-side-link:hover{background:#ecfdf5;color:#047857}
.lh-side-link.active{background:linear-gradient(135deg,rgba(4,120,87,.14),rgba(16,185,129,.10));color:#047857;box-shadow:inset 0 0 0 1.5px rgba(16,185,129,.28)}
.lh-side-link.active::before{content:'';position:absolute;left:-.65rem;top:20%;bottom:20%;width:3px;border-radius:99px;background:linear-gradient(#047857,#10b981)}
.lh-side-ico{width:1.25rem;text-align:center;font-size:15px;line-height:1}
.lh-side-label{flex:1;min-width:0}
.lh-side-foot{margin-top:auto;display:flex;align-items:center;gap:.6rem;padding:1rem;border-top:1px solid rgba(226,232,240,.9)}
.lh-topbar .lh-top-inner{display:flex;align-items:center;gap:.5rem;padding:0 .85rem;height:60px}
.lh-badge{position:absolute;top:-3px;right:-4px;min-width:17px;height:17px;padding:0 3px;border-radius:9999px;background:linear-gradient(135deg,#047857,#10b981);color:#fff;font-size:10px;font-weight:800;line-height:17px;text-align:center;box-shadow:0 0 0 2px #fff}
.lh-panel{position:absolute;top:calc(100% + 10px);right:0;z-index:60;width:min(360px,calc(100vw - 16px));max-height:min(480px,calc(100vh - 70px));overflow:hidden;border-radius:18px;border:1px solid rgba(226,232,240,.9);background:#fff;box-shadow:0 28px 60px -24px rgba(4,120,87,.28)}
.lh-panel-list{max-height:min(400px,calc(100vh - 130px));overflow-y:auto}
.lx-panel-item{display:flex;flex-direction:column;gap:2px;padding:.65rem .85rem;border-bottom:1px solid rgba(241,245,249,.8);cursor:pointer;text-decoration:none}
.lx-panel-item:hover{background:#f6fbf8}
.lh-notif-unread{background:#ecfdf5}
.lh-notif-unread:hover{background:#d1fae5}
.lh-notif-unread::before{content:'';align-self:flex-start;width:7px;height:7px;border-radius:99px;background:#059669;margin-bottom:3px}
.lh-notif-item{display:flex;flex-direction:column;gap:1px}



/* ============================================================
   Redesign shell: fixed sidebar + top bar + notification panel
   ============================================================ */
.lh-app{background:#eef3ee}
.lh-sidebar{position:fixed;top:0;left:0;bottom:0;width:264px;z-index:35;background:rgba(255,255,255,.97);border-right:1px solid rgba(226,232,240,.92);box-shadow:0 24px 48px -24px rgba(4,120,87,.18);transform:translateX(-280px);transition:transform .25s ease;display:flex;flex-direction:column}
.lh-sidebar.open{transform:none}
@media(min-width:1024px){.lh-sidebar{transform:none;box-shadow:none}}
.lh-backdrop{position:fixed;inset:0;z-index:34;background:rgba(15,23,42,.35);display:none}
body.lh-side-open .lh-backdrop{display:block}
@media(min-width:1024px){.lh-backdrop{display:none!important}}
body.lh-app{padding-left:0}
@media(min-width:1024px){body.lh-app{padding-left:264px}}
.lh-main{transition:padding-left .25s ease}
.lh-side-head{display:flex;align-items:center;gap:.6rem;padding:.9rem 1rem .8rem;border-bottom:1px solid rgba(226,232,240,.9)}
.lh-side-sec{padding:.9rem 1rem .35rem;font-size:10px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#94a3b8}
.lh-side-nav{display:flex;flex-direction:column;gap:2px;padding:0 .65rem}
.lh-side-link{display:flex;align-items:center;gap:.65rem;padding:.55rem .7rem;font-size:.875rem;font-weight:600;color:#475569;border-radius:12px;transition:all .15s;position:relative}
.lh-side-link:hover{background:#ecfdf5;color:#047857}
.lh-side-link.active{background:linear-gradient(135deg,rgba(4,120,87,.14),rgba(16,185,129,.10));color:#047857;box-shadow:inset 0 0 0 1.5px rgba(16,185,129,.28)}
.lh-side-link.active::before{content:'';position:absolute;left:-.65rem;top:20%;bottom:20%;width:3px;border-radius:99px;background:linear-gradient(#047857,#10b981)}
.lh-side-ico{width:1.25rem;text-align:center;font-size:15px;line-height:1}
.lh-side-label{flex:1;min-width:0}
.lh-side-foot{margin-top:auto;display:flex;align-items:center;gap:.6rem;padding:1rem;border-top:1px solid rgba(226,232,240,.9)}
.lh-topbar .lh-top-inner{display:flex;align-items:center;gap:.5rem;padding:0 .85rem;height:60px}
.lh-badge{position:absolute;top:-3px;right:-4px;min-width:17px;height:17px;padding:0 3px;border-radius:9999px;background:linear-gradient(135deg,#047857,#10b981);color:#fff;font-size:10px;font-weight:800;line-height:17px;text-align:center;box-shadow:0 0 0 2px #fff}
.lh-panel{position:absolute;top:calc(100% + 10px);right:0;z-index:60;width:min(360px,calc(100vw - 16px));max-height:min(480px,calc(100vh - 70px));overflow:hidden;border-radius:18px;border:1px solid rgba(226,232,240,.9);background:#fff;box-shadow:0 28px 60px -24px rgba(4,120,87,.28)}
.lh-panel-list{max-height:min(400px,calc(100vh - 130px));overflow-y:auto}
.lx-panel-item{display:flex;flex-direction:column;gap:2px;padding:.65rem .85rem;border-bottom:1px solid rgba(241,245,249,.8);cursor:pointer;text-decoration:none}
.lx-panel-item:hover{background:#f6fbf8}
.lh-notif-unread{background:#ecfdf5}
.lh-notif-unread:hover{background:#d1fae5}
.lh-notif-unread::before{content:'';align-self:flex-start;width:7px;height:7px;border-radius:99px;background:#059669;margin-bottom:3px}
.lh-notif-item{display:flex;flex-direction:column;gap:1px}

</style>
</style>
</head>
<body class="flex min-h-screen flex-col font-sans text-slate-800<?= $user ? ' lh-app' : '' ?>"<?= $user ? ' data-heartbeat="1"' : '' ?>>
<div id="lh-progress"></div>

<?php if ($user): ?>
<aside id="lh-sidebar" class="lh-sidebar" aria-label="Main navigation">
  <div class="lh-side-head">
    <a href="dashboard.php" title="LearnHub home" class="lh-logo grid h-10 w-10 place-items-center rounded-xl text-white text-lg">🎓</a>
    <div class="min-w-0 leading-tight">
      <a href="dashboard.php" class="block text-sm font-extrabold tracking-tight text-slate-900">LearnHub <span class="text-emerald-600">LMS</span></a>
      <p class="text-[11px] text-slate-400"><?= ($user['role'] ?? '') === 'teacher' ? '👩‍🏫 Teacher' : '👨‍🎓 Student' ?></p>
    </div>
  </div>

  <div class="lh-side-sec">Menu</div>
  <nav class="lh-side-nav">
    <a href="dashboard.php" class="lh-side-link <?= $nav_active === 'dashboard' ? 'active' : '' ?>"><span class="lh-side-ico">🏠</span><span class="lh-side-label">Dashboard</span></a>
    <a href="courses.php" class="lh-side-link <?= $nav_active === 'courses' ? 'active' : '' ?>"><span class="lh-side-ico">📚</span><span class="lh-side-label">My Courses</span></a>
    <a href="enrollments.php" class="lh-side-link <?= $nav_active === 'enrollments' ? 'active' : '' ?>"><span class="lh-side-ico">👥</span><span class="lh-side-label">Enrollments</span></a>
    <a href="attendance_day.php" class="lh-side-link <?= $nav_active === 'attendance' ? 'active' : '' ?>"><span class="lh-side-ico">🕒</span><span class="lh-side-label">Attendance</span></a>
    <?php if (($user['role'] ?? '') === 'teacher'): ?>
    <a href="codes.php" class="lh-side-link <?= $nav_active === 'codes' ? 'active' : '' ?>"><span class="lh-side-ico">🔑</span><span class="lh-side-label">Invite codes</span></a>
    <?php endif; ?>
    <a href="<?= ($user['role'] ?? '') === 'teacher' ? 'quiz_records.php' : 'my_records.php' ?>" class="lh-side-link <?= $nav_active === 'records' ? 'active' : '' ?>"><span class="lh-side-ico">📊</span><span class="lh-side-label"><?= ($user['role'] ?? '') === 'teacher' ? 'Student Records' : 'My Progress' ?></span></a>
  </nav>

  <div class="lh-side-sec">Communication</div>
  <nav class="lh-side-nav">
    <a href="messages.php" class="lh-side-link <?= $nav_active === 'messages' ? 'active' : '' ?>" id="lh-side-chat">
      <span class="lh-side-ico">💬</span><span class="lh-side-label flex-1">Messages</span>
      <span id="lh-chat-badge-side" class="lh-badge hidden">0</span>
    </a>
  </nav>

  <div class="lh-side-foot">
    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-emerald-600 text-sm font-bold text-white"><?= e(strtoupper(substr((string) $user['name'], 0, 1))) ?></span>
    <span class="min-w-0 flex-1 text-left leading-tight">
      <span class="block truncate text-sm font-semibold text-slate-800"><?= e((string) $user['name']) ?></span>
      <span class="block truncate text-[10px] uppercase tracking-wide text-slate-400"><?= ($user['role'] ?? '') === 'teacher' ? 'Teacher' : 'Student' ?></span>
    </span>
    <a href="logout.php" title="Log out" class="lh-ico grid h-9 w-9 place-items-center rounded-lg border border-slate-200 text-slate-500">⏻</a>
  </div>
</aside>
<div id="lh-side-backdrop" class="lh-backdrop" aria-hidden="true"></div>
<?php endif; ?>
<nav class="glass lh-topbar sticky top-0 z-40 border-b border-white/60" aria-label="Primary">
  <div class="lh-top-inner">
    <?php if ($user): ?>
    <button id="lh-side-toggle" class="lh-ico grid h-9 w-9 place-items-center rounded-lg text-slate-600 lg:hidden" aria-label="Open menu">
      <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M3 12h18M3 18h18" /></svg>
    </button>
    <?php endif; ?>
    <a href="<?= $user ? 'dashboard.php' : 'index.php' ?>" title="LearnHub home" class="lh-logo grid h-9 w-9 place-items-center rounded-xl text-white lg:hidden">🎓</a>
    <a href="<?= $user ? 'dashboard.php' : 'index.php' ?>" class="lh-topword hidden items-center gap-2 lg:flex">
      <span class="lh-logo grid h-8 w-8 place-items-center rounded-xl text-white">🎓</span>
      <span class="text-sm font-extrabold tracking-tight text-slate-900">LearnHub <span class="text-emerald-600">LMS</span></span>
    </a>

    <form id="lh-global-search" action="courses.php" class="hidden min-w-0 flex-1 items-center md:flex md:max-w-sm" role="search">
      <div class="relative w-full">
        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6.1L21 17M17 15.5v-9M15.5 7.5H6M7.5 6V19h8M7 17h11" /></svg>
        </span>
        <input name="q" type="search" id="lh-search-input" placeholder="Search courses…"
          class="h-9.5 w-full rounded-full border border-slate-200 bg-slate-100/70 py-2 pl-9 pr-3 text-sm outline-none focus:border-emerald-400 focus:bg-white focus:ring-2 focus:ring-emerald-100">
      </div>
    </form>

    <div class="flex items-center gap-1.5">
      <?php if ($user): ?>
      <div class="relative">
        <button id="lh-notif-btn" class="lh-ico grid h-9 w-9 place-items-center rounded-lg text-slate-600" aria-label="Notifications">
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18.37 4.6L12.9 5.8a4.6 4.6 0 002.6-2.6M15.5 3.5l1.2 9m.5 0v3.6M15.5 15.5h6M9.75 4.75V21m6-6.75M9.75 9.5h6M9.75 15.5h6M9.75 21h6" /></svg>
          <span id="lh-notif-badge" class="lh-badge hidden">0</span>
        </button>
      </div>
      <a href="messages.php" id="lh-chat-link" class="lh-ico grid h-9 w-9 place-items-center rounded-lg text-slate-600" aria-label="Messages">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.75 3l7.5-4.75a4.75 4.75 0 003.78-3.78l-3.78-6.57M6.3 6.3h2.85M13.2 6.3h2.4M12.75 6.3h.9M3.75 13.05l1.8 4.5M4.75 16.05h7.5m3 2.25h.9M4.75 18.3h7.5m-.9 1.05l3.6 2.1M8.5 20.25h5.25m4.5 0l-5.25 2.1M17.35 9.3h3.6M16.9 12.15h3.6M4.75 6.3h2.7v3M9.6 9.3h1.05M8.05 6.3h2.7M4.75 6.9l-1.05 2.4M9.6 15.3h4.5M14.85 15.3h3.75" /></svg>
        <span id="lh-chat-badge" class="lh-badge hidden">0</span>
      </a>
      <span class="hidden text-right sm:flex sm:flex-col sm:items-start leading-tight">
        <span class="text-sm font-semibold leading-4 text-slate-800"><?= e((string) $user['name']) ?></span>
        <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-400"><?= ($user['role'] ?? '') === 'teacher' ? 'Teacher' : 'Student' ?></span>
      </span>
      <span class="grid h-9 w-9 place-items-center rounded-full bg-emerald-600 text-sm font-bold text-white"><?= e(strtoupper(substr((string) $user['name'], 0, 1))) ?></span>
      <a href="logout.php" title="Log out" aria-label="Log out" class="hidden rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 sm:inline-block">Log out</a>
      <a href="logout.php" title="Log out" aria-label="Log out" class="grid h-9 w-9 place-items-center rounded-lg border border-slate-200 text-slate-500 sm:hidden">⏻</a>
      <?php else: ?>
      <a href="login.php" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Log in</a>
      <a href="register.php" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Get started</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
<?php if ($user): ?>
<div id="lh-notif-panel" class="lh-panel hidden" aria-label="Notifications">
  <div class="lx-panel-head flex items-center justify-between gap-2 border-b border-slate-200 px-3.5 py-2.5">
    <span class="text-sm font-bold text-slate-800">🔔 Notifications</span>
    <button id="lh-notif-read-all" type="button" class="text-xs font-semibold text-emerald-600 hover:underline">Mark all as read</button>
  </div>
  <div id="lh-notif-list" class="lx-panel-list">
    <?php $lh_notifs = notifications_for((int) $user['id'], 8); ?>
    <?php if (!$lh_notifs): ?>
    <p class="px-3.5 py-6 text-center text-xs text-slate-400">No notifications yet.<br>New lessons, quizzes, results and messages will show up here.</p>
    <?php else: foreach ($lh_notifs as $n): $lh_ago = time() - (int) $n['created_at']; $lh_agoTxt = $lh_ago < 60 ? 'just now' : ($lh_ago < 3600 ? (int) floor($lh_ago / 60) . 'm ago' : ($lh_ago < 86400 ? (int) floor($lh_ago / 3600) . 'h ago' : (int) floor($lh_ago / 86400) . 'd ago')); ?>
    <a href="<?= e((string) $n['link']) ?>" data-notif-id="<?= (int) $n['id'] ?>" data-notif-link="<?= e((string) $n['link']) ?>" class="lx-panel-item lh-notif-item <?= $n['is_read'] ? '' : 'lh-notif-unread' ?>">
      <span class="lx-panel-item-title text-[13px] font-semibold text-slate-800"><?= e((string) $n['title']) ?></span>
      <?php if ($n['body']): ?><span class="text-xs text-slate-500"><?= e((string) $n['body']) ?></span><?php endif; ?>
      <span class="text-[10px] text-slate-400"><?= e($lh_agoTxt) ?></span>
    </a>
    <?php endforeach; endif; ?>
  </div>
</div>
<?php endif; ?>

<?php foreach ($flashes as $f): ?>
<div data-toast class="toast-in fixed right-4 top-20 z-50 flex max-w-sm items-start gap-3 rounded-xl border p-4 shadow-lg <?= ($f['type'] ?? '') === 'error' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' ?>">
  <span><?= ($f['type'] ?? '') === 'error' ? '⚠️' : '✅' ?></span>
  <p class="text-sm font-medium"><?= e((string) ($f['msg'] ?? '')) ?></p>
  <button data-toast-close class="ml-2 text-slate-400 hover:text-slate-600">✕</button>
</div>
<?php endforeach; ?>

<main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8<?= $user ? ' lh-main' : '' ?>">
