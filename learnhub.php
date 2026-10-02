<?php
/**
 * How it works — the public tour of the platform.
 *
 * This file was index.php until the public site grew a front page: home.php is
 * now what /LMS/ serves, and the top bar and the footer link here under the
 * label "How it works" (PUBLIC_PAGES in lib.php). The old address still works
 * too — /index and /index.php are 302'd to /home by .htaccess.
 *
 * Unlike the front door this page is deliberately NOT guest-only: a signed-in
 * visitor who follows the footer link should get the tour instead of a bounce
 * to the dashboard, so the calls to action at the top differ by role.
 *
 * The study stack in the hero is real CSS 3D (assets/book3d.css + book3d.js —
 * no WebGL library and no model file): its solids are the $lh3d_solids rows
 * further down, and assets/book3d-fixture.html reports what the browser
 * computed for them.
 */
require_once __DIR__ . '/lib.php';
$user = current_user();

$courses = load_courses();
$stats = [
    'courses'  => count($courses),
    'students' => count(array_filter(load_users(), fn ($u) => ($u['role'] ?? '') === 'student')),
    'lessons'  => array_sum(array_map(fn ($c) => count($c['materials'] ?? []), $courses)),
];
$page_title = 'How it works';
require __DIR__ . '/header.php';
?>

<!-- Hero: editorial composition -->
<section class="grid items-center gap-10 py-10 md:grid-cols-[1.15fr_1fr] md:py-14">
  <div>
    <p class="lh-kicker reveal">Simple learning management system</p>
    <h1 class="reveal mt-3 text-4xl sm:text-5xl">Learn anything.<br><span class="text-emerald-700">Teach everything.</span></h1>
    <p class="reveal mt-4 max-w-xl text-base leading-7 text-slate-600">Upload learning materials and video tutorials, invite students with a code, and watch progress add up — all in one calm, simple place.</p>
    <div class="reveal mt-8 flex flex-wrap gap-3">
      <?php if ($user): ?>
      <a href="dashboard.php" class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800">Go to my dashboard</a>
      <a href="courses.php" class="rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Browse courses</a>
      <?php else: ?>
      <a href="register.php" class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800">Create free account</a>
      <a href="login.php" class="rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Log in</a>
      <?php endif; ?>
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
<!-- 3D study stack: books + mortarboard, CSS 3D, drag to look round it.
     Styles in assets/book3d.css, the looking-around in assets/book3d.js.
     Every solid is one row of data below and six faces drawn by CSS from
     the custom properties on it, so adding a prop to the pile is a line
     here and nothing else. Sizes are px because the camera is px too. -->
<link rel="stylesheet" href="assets/book3d.css?v=<?= (int) @filemtime(lh_path('assets/book3d.css')) ?>">
<section class="lh3d-wrap reveal py-10 md:py-14">
  <div class="lh3d-scene" id="lh3d-scene" tabindex="0" role="img" aria-describedby="lh3d-hint"
       aria-label="A floating stack of three books with a graduation cap on top, turning slowly">
    <div class="lh3d-bob lh3d-keep">
      <div class="lh3d-tilt lh3d-keep">
        <div class="lh3d-turn lh3d-keep">
          <div class="lh3d-obj">
            <?php
            // w = length across, d = depth, h = thickness, y = height of
            // its centre (negative is up), ry = its own turn.
            $lh3d_solids = [
              ['cls' => '', 'w' => 240, 'd' => 164, 'h' => 26, 'x' => 0, 'y' => 30, 'z' => 0, 'ry' => -6, 'cover' => '#065f46'],
              ['cls' => '', 'w' => 222, 'd' => 156, 'h' => 24, 'x' => 0, 'y' => 5, 'z' => 0, 'ry' => 5, 'cover' => '#b45309'],
              ['cls' => '', 'w' => 200, 'd' => 148, 'h' => 22, 'x' => 0, 'y' => -18, 'z' => 0, 'ry' => -11, 'cover' => '#334155'],
              ['cls' => ' lh3d-cap', 'w' => 92, 'd' => 92, 'h' => 24, 'x' => 0, 'y' => -42, 'z' => 0, 'ry' => 45, 'cover' => '#1f2937'],
              ['cls' => ' lh3d-cap', 'w' => 150, 'd' => 150, 'h' => 8, 'x' => 0, 'y' => -58, 'z' => 0, 'ry' => 45, 'cover' => '#243447'],
              ['cls' => ' lh3d-btn', 'w' => 12, 'd' => 12, 'h' => 8, 'x' => 0, 'y' => -66, 'z' => 0, 'ry' => 45, 'cover' => '#fbbf24'],
              ['cls' => ' lh3d-cord', 'w' => 4, 'd' => 4, 'h' => 52, 'x' => 100, 'y' => -30, 'z' => 100, 'ry' => 45, 'cover' => '#f59e0b'],
            ];
            foreach ($lh3d_solids as $s): ?>
              <i class="lh3d-solid<?= $s['cls'] ?>" style="--w:<?= (int) $s['w'] ?>px;--d:<?= (int) $s['d'] ?>px;--h:<?= (int) $s['h'] ?>px;--x:<?= (int) $s['x'] ?>px;--y:<?= (int) $s['y'] ?>px;--z:<?= (int) $s['z'] ?>px;--ry:<?= (int) $s['ry'] ?>deg;--cover:<?= $s['cover'] ?>"><i class="f"></i><i class="b"></i><i class="r"></i><i class="l"></i><i class="t"></i><i class="u"></i></i>
            <?php endforeach; ?>
            <?php
            // Sparks on a slow orbit. a = starting angle, y = height,
            // s = size, c = colour, t = how long one lap takes.
            $lh3d_sparks = [
              ['a' => -18, 'y' => -46, 's' => 16, 'c' => '#fbbf24', 't' => '19s'],
              ['a' => 112, 'y' => -104, 's' => 12, 'c' => '#38bdf8', 't' => '27s'],
              ['a' => 236, 'y' => -70, 's' => 14, 'c' => '#34d399', 't' => '23s'],
            ];
            foreach ($lh3d_sparks as $p): ?>
              <i class="lh3d-spark" style="--a:<?= (int) $p['a'] ?>deg;--y:<?= (int) $p['y'] ?>px;--s:<?= (int) $p['s'] ?>px;--c:<?= $p['c'] ?>;--t:<?= $p['t'] ?>"><i></i><i></i></i>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="lh3d-shadow" aria-hidden="true"></div>
</section>
<script src="assets/book3d.js?v=<?= (int) @filemtime(lh_path('assets/book3d.js')) ?>" defer></script>

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

<?php /* Read next — the rest of the public site, from the same list the top bar
         and the footer read (PUBLIC_PAGES in lib.php). */ ?>
<section class="reveal mt-10 flex flex-wrap items-center justify-center gap-3 border-t border-slate-100 pt-4 text-sm">
  <span class="text-slate-400">Read next:</span>
  <a class="font-semibold text-emerald-700 hover:underline" href="<?= e(public_url('home')) ?>">Home</a>
  <a class="font-semibold text-emerald-700 hover:underline" href="<?= e(public_url('about')) ?>">About LearnHub</a>
  <a class="font-semibold text-emerald-700 hover:underline" href="<?= e(public_url('faq')) ?>">Questions (FAQ)</a>
  <?php foreach (LEGAL_PAGES as $lh_legal): ?>
  <a class="font-semibold text-slate-500 hover:underline" href="<?= e((string) $lh_legal['file']) ?>"><?= e((string) $lh_legal['label']) ?></a>
  <?php endforeach; ?>
</section>

<?php require __DIR__ . '/footer.php'; ?>
