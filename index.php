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

<!-- Hero -->
<section class="grid items-center gap-10 py-10 md:grid-cols-2 md:py-14">
  <div>
    <span class="inline-flex items-center gap-2 rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 ring-1 ring-indigo-200">🎓 Simple Learning Management System</span>
    <h1 class="mt-4 text-4xl font-extrabold tracking-tight text-slate-900 sm:text-5xl">Learn anything.<br><span class="text-indigo-600">Teach everything.</span></h1>
    <p class="mt-4 text-lg leading-8 text-slate-600">Upload learning materials and video tutorials, enroll students, and track progress — all in one simple place. No database setup needed.</p>
    <div class="mt-8 flex flex-wrap gap-3">
      <a href="register.php" class="rounded-xl bg-indigo-600 px-6 py-3 font-semibold text-white shadow-sm hover:bg-indigo-700">Create free account</a>
      <a href="login.php" class="rounded-xl border border-slate-300 bg-white px-6 py-3 font-semibold text-slate-700 hover:bg-slate-100">Log in</a>
    </div>
    <p class="mt-4 text-sm text-slate-500">Demo: <code class="rounded bg-slate-100 px-1.5 py-0.5">teacher@demo.com</code> or <code class="rounded bg-slate-100 px-1.5 py-0.5">student@demo.com</code> · password <code class="rounded bg-slate-100 px-1.5 py-0.5">demo123</code></p>
  </div>
  <div class="grid gap-4">
    <div class="flex items-start gap-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
      <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-indigo-100 text-xl">📄</span>
      <div><h3 class="font-semibold text-slate-900">Upload learning materials</h3><p class="mt-1 text-sm text-slate-600">PDF, Word, PowerPoint, images, ZIP files and more — one click per lesson.</p></div>
    </div>
    <div class="flex items-start gap-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
      <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-violet-100 text-xl">🎬</span>
      <div><h3 class="font-semibold text-slate-900">Video tutorials</h3><p class="mt-1 text-sm text-slate-600">Upload MP4/WebM videos or embed YouTube &amp; Vimeo links. Uploaded videos stream with seeking support.</p></div>
    </div>
    <div class="flex items-start gap-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
      <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-emerald-100 text-xl">📈</span>
      <div><h3 class="font-semibold text-slate-900">Track progress</h3><p class="mt-1 text-sm text-slate-600">Students mark lessons complete and watch their progress bar fill up.</p></div>
    </div>
  </div>
</section>

<!-- Stats band -->
<section data-live-scope="index" class="grid grid-cols-3 gap-4 rounded-2xl bg-white p-6 text-center shadow-sm ring-1 ring-slate-200">
  <div><p class="text-3xl font-extrabold text-indigo-600" data-live-index="courses"><?= (int) $stats['courses'] ?></p><p class="mt-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Courses</p></div>
  <div><p class="text-3xl font-extrabold text-indigo-600" data-live-index="lessons"><?= (int) $stats['lessons'] ?></p><p class="mt-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Lessons</p></div>
  <div><p class="text-3xl font-extrabold text-indigo-600" data-live-index="students"><?= (int) $stats['students'] ?></p><p class="mt-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Students</p></div>
</section>

<!-- How it works -->
<section class="py-12">
  <h2 class="text-center text-2xl font-bold text-slate-900">How it works</h2>
  <div class="mt-8 grid gap-4 md:grid-cols-3">
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 transition duration-200 hover:-translate-y-0.5 hover:shadow-md"><span class="text-2xl">1️⃣</span><h3 class="mt-2 font-semibold">Create an account</h3><p class="mt-1 text-sm text-slate-600">Sign up as a <b>Teacher</b> to publish courses, or as a <b>Student</b> to learn.</p></div>
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 transition duration-200 hover:-translate-y-0.5 hover:shadow-md"><span class="text-2xl">2️⃣</span><h3 class="mt-2 font-semibold">Add your lessons</h3><p class="mt-1 text-sm text-slate-600">Upload documents and videos, or paste a YouTube link. Organize everything by course.</p></div>
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 transition duration-200 hover:-translate-y-0.5 hover:shadow-md"><span class="text-2xl">3️⃣</span><h3 class="mt-2 font-semibold">Share &amp; learn</h3><p class="mt-1 text-sm text-slate-600">Students enroll, watch, download materials and track their progress.</p></div>
  </div>
</section>

<?php require __DIR__ . '/footer.php'; ?>
