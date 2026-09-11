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


</style>
</head>
<body class="flex min-h-screen flex-col font-sans text-slate-800"<?= $user ? ' data-heartbeat="1"' : '' ?>>
<div id="lh-progress"></div>

<nav class="glass sticky top-0 z-40 border-b border-white/60">
  <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-2 px-2 sm:gap-4 sm:px-4">
    <a href="<?= $user ? 'dashboard.php' : 'index.php' ?>" class="grid h-9 w-9 place-items-center rounded-xl bg-indigo-600 text-white" title="LearnHub" aria-label="LearnHub home">🎓</a>
    <?php if ($user): ?>
    <!-- desktop: text links -->
    <div class="hidden items-center gap-1 sm:flex">
      <a href="dashboard.php" class="rounded-lg px-3 py-2 text-sm font-medium <?= $nav_active === 'dashboard' ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100' ?>">Dashboard</a>
      <a href="courses.php" class="rounded-lg px-3 py-2 text-sm font-medium <?= $nav_active === 'courses' ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100' ?>">Courses</a>
      <a href="enrollments.php" class="rounded-lg px-3 py-2 text-sm font-medium <?= $nav_active === 'enrollments' ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100' ?>">Enrollments</a>
      <a href="attendance_day.php" class="rounded-lg px-3 py-2 text-sm font-medium <?= $nav_active === 'attendance' ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100' ?>">Attendance</a>
      <?php if (($user['role'] ?? '') === 'teacher'): ?>
      <a href="codes.php" class="rounded-lg px-3 py-2 text-sm font-medium <?= $nav_active === 'codes' ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100' ?>">Invite codes</a>
      <?php endif; ?>
    </div>
    <!-- mobile: minimal icons only -->
    <div class="flex items-center gap-1 sm:hidden">
      <a href="dashboard.php" title="Dashboard" aria-label="Dashboard" class="grid h-9 w-9 place-items-center rounded-lg <?= $nav_active === 'dashboard' ? 'bg-indigo-600 text-white' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700' ?>">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" /></svg>
      </a>
      <a href="courses.php" title="Courses" aria-label="Courses" class="grid h-9 w-9 place-items-center rounded-lg <?= $nav_active === 'courses' ? 'bg-indigo-600 text-white' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700' ?>">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5" /></svg>
      </a>
      <a href="enrollments.php" title="Enrollments" aria-label="Enrollments" class="grid h-9 w-9 place-items-center rounded-lg <?= $nav_active === 'enrollments' ? 'bg-indigo-600 text-white' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700' ?>">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
      </a>
      <a href="attendance_day.php" title="Attendance" aria-label="Attendance" class="grid h-9 w-9 place-items-center rounded-lg <?= $nav_active === 'attendance' ? 'bg-indigo-600 text-white' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700' ?>">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008H14.25v-.008zm0 2.25h.008v.008H14.25V15zm0 2.25h.008v.008H14.25v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z" /></svg>
      </a>
      <?php if (($user['role'] ?? '') === 'teacher'): ?>
      <a href="codes.php" title="Invite codes" aria-label="Invite codes" class="grid h-9 w-9 place-items-center rounded-lg <?= $nav_active === 'codes' ? 'bg-indigo-600 text-white' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700' ?>">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" /></svg>
      </a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="flex items-center gap-2">
      <?php if ($user): ?>
        <span class="hidden text-right sm:block">
          <span class="block text-sm font-semibold leading-4"><?= e((string) $user['name']) ?></span>
          <span class="block text-xs text-slate-500"><?= ($user['role'] ?? '') === 'teacher' ? '👩‍🏫 Teacher' : '👨‍🎓 Student' ?></span>
        </span>
        <span class="grid h-9 w-9 place-items-center rounded-full bg-indigo-600 text-sm font-bold text-white"><?= e(strtoupper(substr((string) $user['name'], 0, 1))) ?></span>
        <a href="logout.php" title="Logout" aria-label="Logout" class="grid h-9 w-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100 hover:text-slate-700 sm:hidden">
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" /></svg>
        </a>
        <a href="logout.php" class="hidden rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 sm:block">Logout</a>
      <?php else: ?>
        <a href="login.php" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Log in</a>
        <a href="register.php" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">Get started</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<?php foreach ($flashes as $f): ?>
<div data-toast class="toast-in fixed right-4 top-20 z-50 flex max-w-sm items-start gap-3 rounded-xl border p-4 shadow-lg <?= ($f['type'] ?? '') === 'error' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' ?>">
  <span><?= ($f['type'] ?? '') === 'error' ? '⚠️' : '✅' ?></span>
  <p class="text-sm font-medium"><?= e((string) ($f['msg'] ?? '')) ?></p>
  <button data-toast-close class="ml-2 text-slate-400 hover:text-slate-600">✕</button>
</div>
<?php endforeach; ?>

<main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">

