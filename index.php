<?php
require_once __DIR__ . '/lib.php';
$user = current_user();
if ($user) { header('Location: dashboard.php'); exit; }

$courses = load_courses();
$stats = [
    'courses'  => count($courses),
    'students' => count(array_filter(load_users(), fn ($u) => ($u['role'] ?? '') === 'student')),
    'lessons'  => array_sum(array_map(fn ($c) => count($c['materials'] ?? []), $courses)),
];
$page_title = 'Welcome';
require __DIR__ . '/header.php';
?>

<!-- Hero: editorial composition -->
<section class="grid items-center gap-10 py-10 md:grid-cols-[1.15fr_1fr] md:py-14">
  <div>
    <p class="lh-kicker reveal">Simple learning management system</p>
    <h1 class="reveal mt-3 text-4xl sm:text-5xl">Learn anything.<br><span class="text-emerald-700">Teach everything.</span></h1>
    <p class="reveal mt-4 max-w-xl text-base leading-7 text-slate-600">Upload learning materials and video tutorials, invite students with a code, and watch progress add up — all in one calm, simple place.</p>
    <div class="reveal mt-8 flex flex-wrap gap-3">
      <a href="register.php" class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800">Create free account</a>
      <a href="login.php" class="rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Log in</a>
    </div>
  </div>

  <!-- Feature index: one composed card, hairline dividers -->
  <div class="reveal overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_1px_2px_rgba(17,33,26,.05),0_18px_40px_-28px_rgba(17,33,26,.25)]">
    <div class="border-b border-slate-100 px-5 py-4">
      <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400">What you get</p>
    </div>
    <div class="lh-feature-row flex items-start gap-4 border-b border-slate-100 px-5 py-4">
      <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-emerald-50 text-emerald-700">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2.5h8L19 7v14a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V3.5a1 1 0 0 1 1-1z"/><path d="M13.5 2.5V7H19M8 12h8M8 16h8"/></svg>
      </span>
      <div><h3 class="font-semibold text-slate-900">Upload learning materials</h3><p class="mt-0.5 text-sm leading-6 text-slate-500">PDF, Word, PowerPoint, images and more — students read them right in the browser, no downloads.</p></div>
    </div>
    <div class="lh-feature-row flex items-start gap-4 border-b border-slate-100 px-5 py-4">
      <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-emerald-50 text-emerald-700">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M10 9.2v5.6l4.8-2.8z"/></svg>
      </span>
      <div><h3 class="font-semibold text-slate-900">Video tutorials</h3><p class="mt-0.5 text-sm leading-6 text-slate-500">Upload MP4/WebM files or embed YouTube &amp; Vimeo links — with watch-time progress per lesson.</p></div>
    </div>
    <div class="lh-feature-row flex items-start gap-4 px-5 py-4">
      <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-emerald-50 text-emerald-700">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V10M10 20V4M16 20v-7"/></svg>
      </span>
      <div><h3 class="font-semibold text-slate-900">Track progress</h3><p class="mt-0.5 text-sm leading-6 text-slate-500">Reading depth, video watch-time and quizzes roll up into one simple percentage per course.</p></div>
    </div>
  </div>
</section><!-- Stats band: live -->
<section data-live-scope="index" class="reveal mt-4 grid grid-cols-3 overflow-hidden rounded-2xl border border-slate-200 bg-white">
  <div class="lh-band px-4 py-5 text-center">
    <p class="lh-num text-3xl font-semibold text-emerald-700" data-live-index="courses"><?= (int) $stats['courses'] ?></p>
    <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Courses</p>
  </div>
  <div class="lh-band px-4 py-5 text-center">
    <p class="lh-num text-3xl font-semibold text-emerald-700" data-live-index="lessons"><?= (int) $stats['lessons'] ?></p>
    <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Lessons</p>
  </div>
  <div class="lh-band px-4 py-5 text-center">
    <p class="lh-num text-3xl font-semibold text-emerald-700" data-live-index="students"><?= (int) $stats['students'] ?></p>
    <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Students</p>
  </div>
</section>

<!-- How it works: numbered editorial steps -->
<section class="py-12">
  <div class="text-center">
    <p class="lh-kicker reveal">Getting started</p>
    <h2 class="reveal mt-2 text-3xl">How it works</h2>
  </div>
  <div class="mt-10 grid gap-6 md:grid-cols-3">
    <div class="reveal border-t-2 border-emerald-700 pt-4">
      <p class="lh-num text-sm font-semibold text-emerald-700">01</p>
      <h3 class="mt-2 text-lg font-semibold text-slate-900">Create an account</h3>
      <p class="mt-1 text-sm leading-6 text-slate-500">Sign up as a <b>Teacher</b> to publish courses, or as a <b>Student</b> to learn — one clean workflow for both.</p>
    </div>
    <div class="reveal border-t-2 border-emerald-700 pt-4">
      <p class="lh-num text-sm font-semibold text-emerald-700">02</p>
      <h3 class="mt-2 text-lg font-semibold text-slate-900">Add your lessons</h3>
      <p class="mt-1 text-sm leading-6 text-slate-500">Upload documents and videos, paste a YouTube link, or assign a quiz. Organize everything by course.</p>
    </div>
    <div class="reveal border-t-2 border-emerald-700 pt-4">
      <p class="lh-num text-sm font-semibold text-emerald-700">03</p>
      <h3 class="mt-2 text-lg font-semibold text-slate-900">Share &amp; learn</h3>
      <p class="mt-1 text-sm leading-6 text-slate-500">Students enroll, watch, read and take quizzes — and teachers see attendance and progress live.</p>
    </div>
  </div>
</section>

<?php require __DIR__ . '/footer.php'; ?>
