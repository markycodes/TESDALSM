<?php
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/skeleton.php';   /* the loading screen: lh_skeleton_css() + lh_skeleton_body() */
$user = current_user();
$page_title = $page_title ?? 'LearnHub';
$nav_active = $nav_active ?? '';
$flashes = take_flashes();
if ($user) {
  touch_presence((int) $user['id']);
} // keep the heartbeat fresh on every page view
?><!DOCTYPE html>
<?php /* data-theme / data-density / data-layout mirror the three Appearance
         picks. They are read-only hooks: a layout stylesheet can key off a
         theme whose shell geometry it must respect (material's floating rail
         and inner-scroll frame, say) without knowing which files were saved,
         and they make "which look is this page actually using?" one glance in
         DevTools instead of three settings lookups. */ ?>
<html lang="en" data-theme="<?= e(ui_theme()) ?>" data-density="<?= e(ui_density()) ?>"
  data-layout="<?= e(ui_layout()) ?>">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf" content="<?= e(csrf_token()) ?>">
  <?php if (!empty($attendance_course)): ?>
    <meta name="attendance-course" content="<?= (int) $attendance_course ?>"><?php endif; ?>
  <title><?= e($page_title) ?> · LearnHub LMS</title>
  <?php /* The colour scheme is applied here, from the same localStorage key
         assets/dark.js writes, BEFORE the first stylesheet is fetched: a visitor
         who chose dark must never be shown the light page and then handed the
         dark one. Silent about a browser that refuses storage — no key read, no
         class, the light page as it always was. */ ?>
  <script>try { if (localStorage.getItem('lh-scheme') === 'dark') document.documentElement.classList.add('dark'); } catch (e) { }</script>
  <link rel="stylesheet" href="assets/tailwind.min.css">
  <link rel="icon" href="logo/logo.png" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <?php /* The lettering is fetched without holding the page hostage — the same
         medicine certificate.php needed. A stylesheet blocks not only the paint
         but every plain script behind it, and app.js is one of them: on a
         network that answers the font host with silence (a filtered school
         connection, a dead CDN) the page would sit unpainted AND its own
         buttons — the copy buttons, the invite links — un-wired. As a preload
         it holds nothing up and becomes a stylesheet the moment it lands, and
         until then the plain faces in shell.css's stacks carry the page. The
         noscript copy is for the visitor whose script never runs: they get the
         fonts the slow way rather than not at all. */ ?>
  <link rel="preload" as="style"
    href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700;800&display=swap"
    onload="this.rel='stylesheet'">
  <noscript>
    <link rel="stylesheet"
      href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700;800&display=swap">
  </noscript>
  <?php /* The design system lives in assets/shell.css: identical bytes on
           every page, so as a file it is downloaded once and cached instead of
           travelling inside every page and being re-parsed before first paint. */ ?>
  <link rel="stylesheet" href="assets/shell.css?v=<?= (int) @filemtime(lh_path('assets/shell.css')) ?>">
  <?php $lh_theme_css = ui_theme_url(); ?>
  <?php if ($lh_theme_css !== ''): ?>
    <link rel="stylesheet" href="<?= e($lh_theme_css) ?>">
  <?php endif; ?>
  <?php $lh_density_css = $user ? ui_density_url() : ''; ?>
  <?php if ($lh_density_css !== ''): ?>
    <link rel="stylesheet" href="<?= e($lh_density_css) ?>">
  <?php endif; ?>
  <?php /* shell arrangement (Settings → Appearance → Layout) — loaded last so a
           layout's geometry outranks the theme's and the density layer's ties */ ?>
  <?php $lh_layout_css = $user ? ui_layout_url() : ''; ?>
  <?php if ($lh_layout_css !== ''): ?>
    <link rel="stylesheet" href="<?= e($lh_layout_css) ?>">
  <?php endif; ?>
  <?php /* The dark scheme, last on purpose: its rules are prefixed html.dark and
         still have to survive a theme, a density and a layout sheet that all
         promote themselves with !important. Its script does nothing but animate
         the class toggle, so it is deferred and cannot hold up the paint. */ ?>
  <link rel="stylesheet" href="assets/dark.css?v=<?= (int) @filemtime(lh_path('assets/dark.css')) ?>">
  <?php if (($user['role'] ?? '') === 'admin'): ?>
    <link rel="stylesheet" href="assets/admin.css?v=<?= (int) @filemtime(lh_path('assets/admin.css')) ?>">
  <?php endif; ?>
  <?php if ($nav_active === 'settings'): ?>
    <link rel="stylesheet" href="assets/appearance-picker.css?v=<?= (int) @filemtime(lh_path('assets/appearance-picker.css')) ?>">
  <?php endif; ?>
  <script src="assets/dark.js?v=<?= (int) @filemtime(lh_path('assets/dark.js')) ?>" defer></script>
  <?php lh_skeleton_css(); ?>
</head>

<body class="flex min-h-screen flex-col font-sans text-slate-800<?= $user ? ' lh-app' : '' ?>" <?= $user ? ' data-heartbeat="1"' : '' ?>>
  <?php lh_skeleton_body(); ?>
  <div id="lh-progress"></div>

  <?php if ($user): ?>
    <aside id="lh-sidebar" class="lh-sidebar" aria-label="Main navigation">
      <div class="lh-side-head">
        <a href="dashboard.php" title="LearnHub home" class="flex flex-row items-center justify-center">
          <img src="logo/logo.png" alt="LearnHub LMS" class="h-14 w-14 object-contain mb-5" />
          <img src="logo/learn.png" alt="" class="lh-side-wordmark w-[10rem] object-contain mb-0">
        </a>
      </div>

      <div class="lh-side-sec">Menu</div>
      <nav class="lh-side-nav">
        <?php if (($user['role'] ?? '') !== 'admin'): ?>
          <a href="dashboard.php" class="lh-side-link <?= $nav_active === 'dashboard' ? 'active' : '' ?>"
            data-tip="Dashboard"><span class="lh-side-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7.5" height="10" rx="1.6" />
                <rect x="13.5" y="3" width="7.5" height="6" rx="1.6" />
                <rect x="3" y="16" width="7.5" height="5" rx="1.6" />
                <rect x="13.5" y="12" width="7.5" height="9" rx="1.6" />
              </svg></span><span class="lh-side-label">Dashboard</span></a>
          <a href="courses.php" class="lh-side-link <?= $nav_active === 'courses' ? 'active' : '' ?>"
            data-tip="My Courses"><span class="lh-side-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <path
                  d="M12 6.5c-1.6-1.3-3.7-1.75-5.5-1.75H3.75v14h2.75c1.8 0 3.9.45 5.5 1.75 1.6-1.3 3.7-1.75 5.5-1.75h2.75v-14H17.5c-1.8 0-3.9.45-5.5 1.75zM12 6.5v14" />
              </svg></span><span class="lh-side-label">Courses</span></a>
          <a href="enrollments.php" class="lh-side-link <?= $nav_active === 'enrollments' ? 'active' : '' ?>"
            data-tip="Enrollments"><span class="lh-side-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <path d="M16 21v-1.5a3.75 3.75 0 0 0-3.75-3.75h-4.5A3.75 3.75 0 0 0 4 19.5V21" />
                <circle cx="8.25" cy="8.25" r="3" />
                <path d="M16 11.25a3 3 0 1 0-1.35 5.64" />
              </svg></span><span class="lh-side-label">Enrollments</span></a>
          <a href="attendance_day.php" class="lh-side-link <?= $nav_active === 'attendance' ? 'active' : '' ?>"
            data-tip="Attendance"><span class="lh-side-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4.5" width="18" height="16.5" rx="2" />
                <path d="M3 9.5h18M8 3v4M16 3v4" />
                <path d="M9 14.25l2 2 4-3.75" />
              </svg></span><span class="lh-side-label">Attendance</span></a>
          <a href="schedule.php" class="lh-side-link <?= $nav_active === 'schedule' ? 'active' : '' ?>"
            data-tip="Schedule"><span class="lh-side-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="9" />
                <path d="M12 7.25V12l3.25 2" />
              </svg></span><span class="lh-side-label">Schedule</span></a>
        <?php endif; ?>
        <?php if (($user['role'] ?? '') === 'teacher'): ?>
          <a href="codes.php" class="lh-side-link <?= $nav_active === 'codes' ? 'active' : '' ?>"
            data-tip="Invite codes"><span class="lh-side-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14.5 3.5a7 7 0 0 0-6.7 9.3L3.5 17v3.5H7L15 12.5a7 7 0 1 0-.5-9z" />
                <circle cx="16.5" cy="7.5" r="1.8" />
              </svg></span><span class="lh-side-label">Invite codes</span></a>
        <?php endif; ?>
        <?php if (($user['role'] ?? '') === 'admin'): ?>
          <a href="admin.php" class="lh-side-link <?= $nav_active === 'admin' ? 'active' : '' ?>" data-tip="Admin"><span
              class="lh-side-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 3l7.5 3v5.4c0 4.6-3.1 8.2-7.5 9.6-4.4-1.4-7.5-5-7.5-9.6V6z" />
                <path d="M9.2 12.1l2 2 3.6-3.9" />
              </svg></span><span class="lh-side-label">Admin</span></a>
          <a href="admin_lessons.php" class="lh-side-link <?= $nav_active === 'admin_lessons' ? 'active' : '' ?>"
            data-tip="Lessons by teacher"><span class="lh-side-ico"><svg viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" />
                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" />
              </svg></span><span class="lh-side-label">Lessons</span></a>
          <a href="admin_progress.php" class="lh-side-link <?= $nav_active === 'admin_progress' ? 'active' : '' ?>"
            data-tip="Student progress"><span class="lh-side-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 19V5m0 14h16" />
                <path d="m7 15 4-4 3 2 5-6" />
                <path d="M16 7h3v3" />
              </svg></span><span class="lh-side-label">Student progress</span></a>
          <a href="codes.php" class="lh-side-link <?= $nav_active === 'codes' ? 'active' : '' ?>"
            data-tip="Invite codes"><span class="lh-side-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14.5 3.5a7 7 0 0 0-6.7 9.3L3.5 17v3.5H7L15 12.5a7 7 0 1 0-.5-9z" />
                <circle cx="16.5" cy="7.5" r="1.8" />
              </svg></span><span class="lh-side-label">Invite codes</span></a>
          <a href="settings.php" class="lh-side-link <?= $nav_active === 'settings' ? 'active' : '' ?>"
            data-tip="Settings"><span class="lh-side-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="3.2" />
                <path
                  d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1.03 1.56V21a2 2 0 1 1-4 0v-.06A1.7 1.7 0 0 0 8.9 19.3a1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.56-1.03H3a2 2 0 1 1 0-4h.06A1.7 1.7 0 0 0 4.6 8.9a1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 9 4.6h.08A1.7 1.7 0 0 0 10.1 3.04V3a2 2 0 1 1 4 0v.06a1.7 1.7 0 0 0 1.03 1.56 1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.7 1.7 0 0 0-.34 1.87v.08a1.7 1.7 0 0 0 1.56 1.03H21a2 2 0 1 1 0 4h-.06A1.7 1.7 0 0 0 19.4 15z" />
              </svg></span><span class="lh-side-label">Settings</span></a>
          <a href="schedule.php" class="lh-side-link <?= $nav_active === 'schedule' ? 'active' : '' ?>"
            data-tip="Schedule"><span class="lh-side-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="9" />
                <path d="M12 7.25V12l3.25 2" />
              </svg></span><span class="lh-side-label">Schedule</span></a>
        <?php endif; ?>
        <?php if (($user['role'] ?? '') !== 'admin'): ?>
          <a href="<?= ($user['role'] ?? '') === 'teacher' ? 'quiz_records.php' : 'my_records.php' ?>"
            class="lh-side-link <?= $nav_active === 'records' ? 'active' : '' ?>" data-tip="Quiz Records"><span
              class="lh-side-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 20V10M10 20V4M16 20v-7" />
              </svg></span><span
              class="lh-side-label"><?= ($user['role'] ?? '') === 'teacher' ? 'Student Records' : 'My Progress' ?></span></a>
          <a href="search.php" class="lh-side-link <?= $nav_active === 'search' ? 'active' : '' ?>"
            data-tip="Search"><span class="lh-side-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="6.5" />
                <path d="M15.8 15.8L20 20" />
              </svg></span><span class="lh-side-label">Search</span></a>
          <a href="offline.php" class="lh-side-link <?= $nav_active === 'offline' ? 'active' : '' ?>"
            data-tip="Offline copy"><span class="lh-side-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 3v11m0 0l-4-4m4 4l4-4" />
                <path d="M4 16v2.5A2.5 2.5 0 006.5 21h11a2.5 2.5 0 002.5-2.5V16" />
              </svg></span><span class="lh-side-label">Offline copy</span></a>
        <?php endif; ?>
      </nav>

      <?php if (($user['role'] ?? '') !== 'admin'): ?>
        <div class="lh-side-sec">Communication</div>
        <nav class="lh-side-nav">
          <a href="messages.php" class="lh-side-link <?= $nav_active === 'messages' ? 'active' : '' ?>" data-tip="Messages"
            id="lh-side-chat">
            <span class="lh-side-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 11.5a8.5 8.5 0 0 1-12.4 7.5L3 21l2-5.6A8.5 8.5 0 1 1 21 11.5z" />
              </svg></span><span class="lh-side-label flex-1">Messages</span>
            <span id="lh-chat-badge-side" class="lh-badge hidden">0</span>
          </a>
        </nav>
      <?php endif; ?>

      <?php /* Every role has one, including the main admin, whose Menu above is
                deliberately student/teacher-only — this is the account a person
                lives in, not a place to work. */ ?>
      <div class="lh-side-sec">Account</div>
      <nav class="lh-side-nav">
        <a href="profile.php" class="lh-side-link <?= $nav_active === 'profile' ? 'active' : '' ?>"
          data-tip="My profile"><span class="lh-side-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="8.25" r="3.25" />
            <path d="M5 20v-.75A4.25 4.25 0 0 1 9.25 15h5.5A4.25 4.25 0 0 1 19 19.25V20" />
          </svg></span><span class="lh-side-label">My profile</span></a>
      </nav>

      <div class="lh-side-collapse-wrap"><button id="lh-side-collapse" type="button" class="lh-side-tip"
          data-tip="Close sidebar" aria-label="Close sidebar">«</button></div>
      <div class="lh-side-foot">
        <a href="profile.php" class="flex min-w-0 items-center gap-2 text-left" title="My profile">
          <?= user_avatar_html($user) ?>
          <span class="min-w-0 flex-1 leading-tight">
            <span class="block truncate text-sm font-semibold text-slate-800"><?= e((string) $user['name']) ?></span>
            <span
              class="block truncate text-[10px] uppercase tracking-wide text-slate-400"><?= ($user['role'] ?? '') === 'admin' ? 'Main Admin' : (($user['role'] ?? '') === 'teacher' ? 'Teacher' : 'Student') ?></span>
          </span>
        </a>
        <a href="logout.php" class="lh-ico lh-side-tip grid h-9 w-9 place-items-center rounded-lg border border-slate-200 text-slate-500"
          data-tip="Log out" aria-label="Log out">
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9" />
          </svg>
        </a>
      </div>
    </aside>
    <div id="lh-side-backdrop" class="lh-backdrop" aria-hidden="true"></div>
  <?php endif; ?>
  <nav class="glass lh-topbar sticky top-0 z-40 border-b border-white/60" aria-label="Primary">
    <div class="lh-top-inner">
      <div class="flex min-w-0 items-center gap-1.5">
        <?php if ($user): ?>
          <button id="lh-rail-toggle" type="button" data-tip="Close sidebar" aria-label="Close sidebar"
            class="lh-ico lh-tip grid h-7 w-7 place-items-center rounded-lg text-slate-600"><svg width="16" height="16"
              viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" data-safe-chroma="true">
              <path fill-rule="evenodd" clip-rule="evenodd"
                d="M9.67272 0.522841C10.8339 0.522841 11.76 0.522714 12.4963 0.602493C13.2453 0.683657 13.8789 0.854248 14.4264 1.25197C14.7504 1.48739 15.0355 1.77247 15.2709 2.0965C15.6686 2.64394 15.8392 3.27758 15.9204 4.02655C16.0002 4.7629 16 5.68895 16 6.85014V9.14986C16 10.3111 16.0002 11.2371 15.9204 11.9735C15.8392 12.7224 15.6686 13.3561 15.2709 13.9035C15.0355 14.2275 14.7504 14.5126 14.4264 14.748C13.8789 15.1458 13.2453 15.3163 12.4963 15.3975C11.76 15.4773 10.8339 15.4772 9.67272 15.4772H6.3273C5.16611 15.4772 4.24006 15.4773 3.50371 15.3975C2.75474 15.3163 2.1211 15.1458 1.57366 14.748C1.24963 14.5126 0.964549 14.2275 0.729131 13.9035C0.331407 13.3561 0.160817 12.7224 0.0796529 11.9735C-0.000126137 11.2371 1.25338e-09 10.3111 1.25338e-09 9.14986V6.85014C1.25329e-09 5.68895 -0.000126137 4.7629 0.0796529 4.02655C0.160817 3.27758 0.331407 2.64394 0.729131 2.0965C0.964549 1.77247 1.24963 1.48739 1.57366 1.25197C2.1211 0.854248 2.75474 0.683657 3.50371 0.602493C4.24006 0.522714 5.16611 0.522841 6.3273 0.522841H9.67272ZM5.54303 1.88715V14.1118C5.78636 14.1128 6.04709 14.1169 6.3273 14.1169H9.67272C10.8639 14.1169 11.7032 14.1164 12.3493 14.0465C12.9824 13.9779 13.3497 13.8494 13.6268 13.6482C13.8354 13.4966 14.0195 13.3125 14.1711 13.1039C14.3723 12.8268 14.5007 12.4595 14.5693 11.8264C14.6393 11.1803 14.6398 10.341 14.6398 9.14986V6.85014C14.6398 5.65896 14.6393 4.81967 14.5693 4.1736C14.5007 3.54048 14.3723 3.17318 14.1711 2.89609C14.0195 2.68747 13.8354 2.50337 13.6268 2.35179C13.3497 2.1506 12.9824 2.02212 12.3493 1.95353C11.7032 1.88358 10.8639 1.88307 9.67272 1.88307H6.3273C6.04709 1.88307 5.78636 1.8862 5.54303 1.88715ZM4.1828 1.91166C3.99125 1.9216 3.8148 1.93577 3.65076 1.95353C3.01764 2.02212 2.65034 2.1506 2.37325 2.35179C2.16463 2.50337 1.98052 2.68747 1.82895 2.89609C1.62776 3.17318 1.49928 3.54048 1.43069 4.1736C1.36074 4.81967 1.36023 5.65896 1.36023 6.85014V9.14986C1.36023 10.341 1.36074 11.1803 1.43069 11.8264C1.49928 12.4595 1.62776 12.8268 1.82895 13.1039C1.98052 13.3125 2.16463 13.4966 2.37325 13.6482C2.65034 13.8494 3.01764 13.9779 3.65076 14.0465C3.81478 14.0642 3.99127 14.0774 4.1828 14.0873V1.91166Z"
                fill="currentColor"></path>
            </svg></button>
          <button id="lh-side-toggle" class="lh-ico lh-tip grid h-7 w-7 place-items-center rounded-lg text-slate-600 lg:hidden"
            data-tip="Open menu" aria-label="Open menu">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"
              data-safe-chroma="true">
              <path fill-rule="evenodd" clip-rule="evenodd"
                d="M9.67272 0.522841C10.8339 0.522841 11.76 0.522714 12.4963 0.602493C13.2453 0.683657 13.8789 0.854248 14.4264 1.25197C14.7504 1.48739 15.0355 1.77247 15.2709 2.0965C15.6686 2.64394 15.8392 3.27758 15.9204 4.02655C16.0002 4.7629 16 5.68895 16 6.85014V9.14986C16 10.3111 16.0002 11.2371 15.9204 11.9735C15.8392 12.7224 15.6686 13.3561 15.2709 13.9035C15.0355 14.2275 14.7504 14.5126 14.4264 14.748C13.8789 15.1458 13.2453 15.3163 12.4963 15.3975C11.76 15.4773 10.8339 15.4772 9.67272 15.4772H6.3273C5.16611 15.4772 4.24006 15.4773 3.50371 15.3975C2.75474 15.3163 2.1211 15.1458 1.57366 14.748C1.24963 14.5126 0.964549 14.2275 0.729131 13.9035C0.331407 13.3561 0.160817 12.7224 0.0796529 11.9735C-0.000126137 11.2371 1.25338e-09 10.3111 1.25338e-09 9.14986V6.85014C1.25329e-09 5.68895 -0.000126137 4.7629 0.0796529 4.02655C0.160817 3.27758 0.331407 2.64394 0.729131 2.0965C0.964549 1.77247 1.24963 1.48739 1.57366 1.25197C2.1211 0.854248 2.75474 0.683657 3.50371 0.602493C4.24006 0.522714 5.16611 0.522841 6.3273 0.522841H9.67272ZM5.54303 1.88715V14.1118C5.78636 14.1128 6.04709 14.1169 6.3273 14.1169H9.67272C10.8639 14.1169 11.7032 14.1164 12.3493 14.0465C12.9824 13.9779 13.3497 13.8494 13.6268 13.6482C13.8354 13.4966 14.0195 13.3125 14.1711 13.1039C14.3723 12.8268 14.5007 12.4595 14.5693 11.8264C14.6393 11.1803 14.6398 10.341 14.6398 9.14986V6.85014C14.6398 5.65896 14.6393 4.81967 14.5693 4.1736C14.5007 3.54048 14.3723 3.17318 14.1711 2.89609C14.0195 2.68747 13.8354 2.50337 13.6268 2.35179C13.3497 2.1506 12.9824 2.02212 12.3493 1.95353C11.7032 1.88358 10.8639 1.88307 9.67272 1.88307H6.3273C6.04709 1.88307 5.78636 1.8862 5.54303 1.88715ZM4.1828 1.91166C3.99125 1.9216 3.8148 1.93577 3.65076 1.95353C3.01764 2.02212 2.65034 2.1506 2.37325 2.35179C2.16463 2.50337 1.98052 2.68747 1.82895 2.89609C1.62776 3.17318 1.49928 3.54048 1.43069 4.1736C1.36074 4.81967 1.36023 5.65896 1.36023 6.85014V9.14986C1.36023 10.341 1.36074 11.1803 1.43069 11.8264C1.49928 12.4595 1.62776 12.8268 1.82895 13.1039C1.98052 13.3125 2.16463 13.4966 2.37325 13.6482C2.65034 13.8494 3.01764 13.9779 3.65076 14.0465C3.81478 14.0642 3.99127 14.0774 4.1828 14.0873V1.91166Z"
                fill="currentColor"></path>
            </svg>
          </button>
        <?php endif; ?>
        <a href="<?= $user ? 'dashboard.php' : public_url('home') ?>" class="lh-topword  items-center gap-2 lg:flex">
          <img src="logo/2ndlogo.png" alt="LearnHub LMS" class="h-14 w-14 object-contain" />
        </a>

      </div>


      <div class="flex items-center gap-1.5">
        <?php if ($user): ?>
          <div class="relative">
            <button id="lh-notif-btn" class="lh-ico lh-tip grid h-9 w-9 place-items-center rounded-lg text-slate-600"
              data-tip="Notifications" aria-label="Notifications">
              <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                  d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
              </svg>
              <?php $lh_unread = unread_notification_count((int) $user['id']); /* true unread count at first paint */ ?>
              <span id="lh-notif-badge"
                class="lh-badge <?= $lh_unread > 0 ? '' : 'hidden' ?>"><?= $lh_unread > 99 ? '99+' : $lh_unread ?></span>
            </button>
            <div id="lh-notif-panel" class="lh-panel hidden" aria-label="Notifications">
              <div class="lx-panel-head flex items-center justify-between gap-2 border-b border-slate-200 px-3.5 py-2.5">
                <span class="text-sm font-bold text-slate-800">🔔 Notifications</span>
                <button id="lh-notif-read-all" type="button"
                  class="text-xs font-semibold text-emerald-600 hover:underline">Mark
                  all as read</button>
              </div>
              <div id="lh-notif-list" class="lx-panel-list">
                <?php $lh_notifs = notifications_for((int) $user['id'], 8); ?>
                <?php if (!$lh_notifs): ?>
                  <p class="px-3.5 py-6 text-center text-xs text-slate-400">No notifications yet.<br>New lessons, quizzes,
                    results
                    and messages will show up here.</p>
                <?php else:
                  foreach ($lh_notifs as $n):
                    $lh_ago = time() - (int) $n['created_at'];
                    $lh_agoTxt = $lh_ago < 60 ? 'just now' : ($lh_ago < 3600 ? (int) floor($lh_ago / 60) . 'm ago' : ($lh_ago < 86400 ? (int) floor($lh_ago / 3600) . 'h ago' : (int) floor($lh_ago / 86400) . 'd ago')); ?>
                    <a href="<?= e((string) $n['link']) ?>" data-notif-id="<?= (int) $n['id'] ?>"
                      data-notif-link="<?= e((string) $n['link']) ?>"
                      class="lx-panel-item lh-notif-item <?= $n['is_read'] ? '' : 'lh-notif-unread' ?>">
                      <span
                        class="lx-panel-item-title text-[13px] font-semibold text-slate-800"><?= e((string) $n['title']) ?></span>
                      <?php if ($n['body']): ?><span
                          class="text-xs text-slate-500"><?= e((string) $n['body']) ?></span><?php endif; ?>
                      <span class="text-[10px] text-slate-400"><?= e($lh_agoTxt) ?></span>
                    </a>
                  <?php endforeach; endif; ?>
              </div>
            </div>
          </div>
          <a href="messages.php" id="lh-chat-link" data-tip="Messages"
            class="lh-ico lh-tip grid h-9 w-9 place-items-center rounded-lg text-slate-600" aria-label="Messages">
            <span class="lh-side-ico"><svg viewBox="0 0 22 22" fill="none" stroke="currentColor" stroke-width="1.7"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 11.5a8.5 8.5 0 0 1-12.4 7.5L3 21l2-5.6A8.5 8.5 0 1 1 21 11.5z" />
              </svg></span>
            <span id="lh-chat-badge" class="lh-badge hidden">0</span>
          </a>
          <?php /* Light or dark, for this browser only: one class on <html> and
                   one localStorage key, nothing sent to the server — the room you
                   are reading in is not a property of your account, and a staff
                   computer is often shared by two people who like different ones.
                   Which half of the icon shows is decided by dark.css from that
                   same class (so the button can never disagree with the page it
                   sits on), what the button promises is kept in step by dark.js,
                   and the change itself arrives as a circle opening from this
                   corner of the bar. */ ?>
          <button type="button" data-lh-scheme data-tip="Dark mode" aria-pressed="false"
            aria-label="Dark mode"
            class="lh-ico lh-tip grid h-9 w-9 place-items-center rounded-lg text-slate-600">
            <svg class="lh-scheme-moon h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
              stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
            </svg>
            <svg class="lh-scheme-sun h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
              stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <circle cx="12" cy="12" r="4" />
              <path
                d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41" />
            </svg>
          </button>
          <span class="hidden text-right sm:flex sm:flex-col sm:items-start leading-tight">
            <span class="text-sm font-semibold leading-4 text-slate-800"><?= e((string) $user['name']) ?></span>
            <span
              class="text-[10px] font-semibold uppercase tracking-wide text-slate-400"><?= ($user['role'] ?? '') === 'admin' ? 'Admin' : (($user['role'] ?? '') === 'teacher' ? 'Teacher' : 'Student') ?></span>
          </span>
          <a href="profile.php" class="flex items-center" title="My profile" aria-label="My profile">
            <?= user_avatar_html($user) ?>
          </a>
          <a href="logout.php" title="Log out" aria-label="Log out"
            class="hidden rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 sm:inline-block">Log
            out</a>
          <a href="logout.php" data-tip="Log out" aria-label="Log out"
            class="lh-tip lh-tip-end grid h-9 w-9 place-items-center rounded-lg border border-slate-200 text-slate-500 sm:hidden">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9" />
            </svg>
          </a>
        <?php else: ?>
          <?php /* The public pages, for a visitor who is not signed in — the SAME
                   list the footer reads and the wordmark above links into
                   (PUBLIC_PAGES in lib.php). Signed-in users already have the
                   sidebar, and reach these pages from the footer. */ ?>
          <nav class="hidden items-center sm:flex" aria-label="Public pages">
            <?php foreach (PUBLIC_PAGES as $lh_pub): ?>
              <a href="<?= e((string) $lh_pub['file']) ?>"
                class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100"><?= e((string) $lh_pub['label']) ?></a>
            <?php endforeach; ?>
          </nav>
          <?php /* the same switch the signed-in bar carries, for a visitor
                 reading the public pages — see the note above that button */ ?>
          <button type="button" data-lh-scheme data-tip="Dark mode" aria-pressed="false"
            aria-label="Dark mode"
            class="lh-ico lh-tip grid h-9 w-9 place-items-center rounded-lg text-slate-600">
            <svg class="lh-scheme-moon h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
              stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
            </svg>
            <svg class="lh-scheme-sun h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
              stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <circle cx="12" cy="12" r="4" />
              <path
                d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41" />
            </svg>
          </button>
          <a href="login.php" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Log
            in</a>
          <a href="register.php"
            class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Get
            started</a>
        <?php endif; ?>
      </div>
    </div>
  </nav>

  <?php foreach ($flashes as $f): ?>
    <div data-toast
      class="toast-in fixed right-4 top-20 z-50 flex max-w-sm items-start gap-3 rounded-xl border p-4 shadow-lg <?= ($f['type'] ?? '') === 'error' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' ?>">
      <span><?= ($f['type'] ?? '') === 'error' ? '⚠️' : '✅' ?></span>
      <p class="text-sm font-medium"><?= e((string) ($f['msg'] ?? '')) ?></p>
      <button data-toast-close data-tip="Dismiss" aria-label="Dismiss" class="lh-tip lh-tip-end ml-2 text-slate-400 hover:text-slate-600">✕</button>
    </div>
  <?php endforeach; ?>

  <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8<?= $user ? ' lh-main' : '' ?>">