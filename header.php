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
.toast-in{animation:toastIn .25s ease-out}@keyframes toastIn{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:none}}
.reveal{opacity:0;transform:translateY(18px);transition:opacity .55s cubic-bezier(.22,.61,.36,1),transform .55s cubic-bezier(.22,.61,.36,1)}
.reveal.in{opacity:1;transform:none}
.no-scrollbar::-webkit-scrollbar{display:none}
.no-scrollbar{-ms-overflow-style:none;scrollbar-width:none}
/* Mobile swipe hint: right-edge fade + nudge chevron; hidden on desktop or after first swipe */
.swipe-hint::after{content:'';position:absolute;top:0;bottom:0;right:0;width:16px;pointer-events:none;background:linear-gradient(to left,rgba(255,255,255,.95),rgba(255,255,255,0))}
.swipe-hint .swipe-chevron{position:absolute;right:3px;top:50%;transform:translate(0,-50%);z-index:10;display:grid;place-items:center;width:26px;height:26px;border-radius:9999px;background:#1e293b;color:#fff;font-size:14px;line-height:1;box-shadow:0 4px 10px rgba(15,23,42,.25);animation:nudge 1.4s ease-in-out infinite;cursor:pointer}
.swipe-hint .swipe-chevron:active{background:#4f46e5}
@keyframes nudge{0%,100%{margin-right:0}50%{margin-right:4px}}
.swipe-hint.hint-off::after{display:none}
.swipe-hint.hint-off .swipe-chevron{display:none}
@media (min-width:640px){.swipe-hint::after{display:none}.swipe-hint .swipe-chevron{display:none}}
@media (prefers-reduced-motion:reduce){.swipe-hint .swipe-chevron{animation:none}}
@media (prefers-reduced-motion:reduce){.reveal{opacity:1;transform:none;transition:none}}
</style>
</head>
<body class="flex min-h-screen flex-col bg-slate-50 font-sans text-slate-800"<?= $user ? ' data-heartbeat="1"' : '' ?>>

<nav class="sticky top-0 z-40 border-b border-slate-200 bg-white/80 backdrop-blur">
  <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-2 px-2 sm:gap-4 sm:px-4">
    <a href="<?= $user ? 'dashboard.php' : 'index.php' ?>" class="grid h-9 w-9 place-items-center rounded-xl bg-indigo-600 text-white" title="LearnHub" aria-label="LearnHub home">🎓</a>
    <?php if ($user): ?>
    <!-- desktop: text links -->
    <div class="hidden items-center gap-1 sm:flex">
      <a href="dashboard.php" class="rounded-lg px-3 py-2 text-sm font-medium <?= $nav_active === 'dashboard' ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100' ?>">Dashboard</a>
      <a href="courses.php" class="rounded-lg px-3 py-2 text-sm font-medium <?= $nav_active === 'courses' ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100' ?>">Courses</a>
      <a href="enrollments.php" class="rounded-lg px-3 py-2 text-sm font-medium <?= $nav_active === 'enrollments' ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100' ?>">Enrollments</a>
      <a href="attendance_day.php" class="rounded-lg px-3 py-2 text-sm font-medium <?= $nav_active === 'attendance' ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100' ?>">Attendance</a>
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

<main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">

<?php foreach ($flashes as $f): ?>
<div data-toast class="toast-in fixed right-4 top-20 z-50 flex max-w-sm items-start gap-3 rounded-xl border p-4 shadow-lg <?= ($f['type'] ?? '') === 'error' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' ?>">
  <span><?= ($f['type'] ?? '') === 'error' ? '⚠️' : '✅' ?></span>
  <p class="text-sm font-medium"><?= e((string) ($f['msg'] ?? '')) ?></p>
  <button data-toast-close class="ml-2 text-slate-400 hover:text-slate-600">✕</button>
</div>
<?php endforeach; ?>
