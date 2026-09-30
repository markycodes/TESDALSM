<?php
/**
 * Home — the public front page, and the document the folder itself serves
 * (DirectoryIndex in .htaccess).
 *
 * Guest-only on purpose: this is the welcome mat, so a signed-in visitor goes
 * straight to their dashboard, exactly as the old index.php did. The tour of
 * the platform lives in learnhub.php (linked as "How it works"), the school and
 * the people behind it in about.php, and the answers everyone needs in faq.php
 * — all four are listed once, in PUBLIC_PAGES (lib.php), which the top bar and
 * the footer both read.
 *
 * The numbers in the band below are the same live counts the tour shows:
 * app.js polls realtime.php and refills every [data-live-index] cell. "index"
 * is the name of that feed, not of any file — the page it used to live on is
 * now learnhub.php.
 */
require_once __DIR__ . '/lib.php';
$user = current_user();
if ($user) { header('Location: dashboard.php'); exit; }

$courses = load_courses();
$stats = [
    'courses'  => count($courses),
    'students' => count(array_filter(load_users(), fn ($u) => ($u['role'] ?? '') === 'student')),
    'lessons'  => array_sum(array_map(fn ($c) => count($c['materials'] ?? []), $courses)),
];
$page_title = 'Home';
require __DIR__ . '/header.php';
?>

<!-- Hero: what this is, and the three doors into it -->
<section class="grid items-center gap-10 py-10 md:grid-cols-[1.15fr_1fr] md:py-14">
  <div>
    <p class="lh-kicker reveal">Felices Technological Training Center, Inc.</p>
    <h1 class="reveal mt-3 text-4xl sm:text-5xl">Train with us.<br><span class="text-emerald-700">Learn from anywhere.</span></h1>
    <p class="reveal mt-4 max-w-xl text-base leading-7 text-slate-600">LearnHub is our own learning management system: your lessons and video
      tutorials, the quizzes your trainer sets, your attendance and class schedule, and the certificate you earn at the end — in one place
      you can open on a phone, at home or in the lab.</p>
    <div class="reveal mt-8 flex flex-wrap gap-3">
      <a href="register.php"
        class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800">Create free
        account</a>
      <a href="login.php"
        class="rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Log
        in</a>
    </div>
    <p class="reveal mt-4 text-xs leading-5 text-slate-400">Trainees join with the invitation code from their trainer; trainers open an account
      with the access code from the administrator. No payment is taken here — training fees, if any, are settled at the school office.</p>
  </div>

  <!-- Start here: the three doors, in one composed card (same hairline rows as the tour) -->
  <div class="reveal overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_1px_2px_rgba(17,33,26,.05),0_18px_40px_-28px_rgba(17,33,26,.25)]">
    <div class="border-b border-slate-100 px-5 py-4">
      <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400">Start here</p>
    </div>
    <a class="lh-feature-row flex items-start gap-4 border-b border-slate-100 px-5 py-4 transition hover:bg-slate-50" href="register.php">
      <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-emerald-50 text-emerald-700">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"
          stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
      </span>
      <div>
        <h3 class="font-semibold text-slate-900">I am new here</h3>
        <p class="mt-0.5 text-sm leading-6 text-slate-500">Open an account with the code from your trainer — redeeming it also enrols you in
          your course.</p>
      </div>
    </a>
    <a class="lh-feature-row flex items-start gap-4 border-b border-slate-100 px-5 py-4 transition hover:bg-slate-50" href="login.php">
      <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-emerald-50 text-emerald-700">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"
          stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg>
      </span>
      <div>
        <h3 class="font-semibold text-slate-900">I already have an account</h3>
        <p class="mt-0.5 text-sm leading-6 text-slate-500">Sign in and pick up where you stopped: lessons, quizzes, schedule and
          certificate.</p>
      </div>
    </a>
    <a class="lh-feature-row flex items-start gap-4 px-5 py-4 transition hover:bg-slate-50" href="<?= e(public_url('learnhub')) ?>">
      <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-emerald-50 text-emerald-700">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"
          stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M15.5 8.5l-2 5-5 2 2-5 5-2z"/></svg>
      </span>
      <div>
        <h3 class="font-semibold text-slate-900">I am just looking</h3>
        <p class="mt-0.5 text-sm leading-6 text-slate-500">See what the platform does, screen by screen, before you commit to anything.</p>
      </div>
    </a>
  </div>
</section>

<?php /* Live counts — the same feed (realtime.php?v=index) the tour polls. The
         cells are refilled by app.js for anyone, signed in or not. */ ?>
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
    <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Trainees</p>
  </div>
</section>

<!-- What a trainee actually does here: six plain cards, one array -->
<section class="py-12">
  <div class="text-center">
    <p class="lh-kicker reveal">Inside LearnHub</p>
    <h2 class="reveal mt-2 text-3xl">Six things you will do all the time</h2>
  </div>
  <div class="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
    <?php
    /* A title and a sentence each, so re-wording one card is a one-line edit and
       none of them can drift out of the grid. */
    $cards = [
        ['Read the materials', 'Documents open right in the page — no downloads and no printing — and the reader remembers how far you got.'],
        ['Watch the video lessons', 'Uploaded MP4 or WebM files, or YouTube and Vimeo links, with your watch progress kept lesson by lesson.'],
        ['Take the lesson quiz', 'One attempt, answers locked per question and a pass mark your trainer sets. The result lands in your records.'],
        ['Join the live class', 'Your trainer opens the room when class starts. Simply being in your course is what records your attendance for the day.'],
        ['Message your trainer', 'A private one-to-one thread for questions — no group chat, and no personal number to hand out.'],
        ['Earn the certificate', 'Finish every lesson of the course and your certificate is issued, with a code anybody can verify.'],
    ];
    foreach ($cards as $card): ?>
    <div class="reveal rounded-xl border border-slate-200 bg-white p-5">
      <h3 class="font-semibold text-slate-900"><?= e($card[0]) ?></h3>
      <p class="mt-1 text-sm leading-6 text-slate-500"><?= e($card[1]) ?></p>
    </div>
    <?php endforeach; ?>
  </div>
  <p class="reveal mt-6 text-center text-sm leading-6 text-slate-500">Trainers get more: they build the courses, upload the lessons, set the
    quizzes and the class schedule, and watch attendance and progress live.
    <a class="font-semibold text-emerald-700 hover:underline" href="<?= e(public_url('learnhub')) ?>">See how it works →</a>
  </p>
</section>

<!-- Where to go next -->
<section class="reveal rounded-2xl border border-slate-200 bg-white p-6">
  <div class="flex flex-wrap items-center justify-between gap-4">
    <div>
      <h2 class="text-lg font-semibold text-slate-900">Want to know more before you sign up?</h2>
      <p class="mt-1 text-sm leading-6 text-slate-500">Read about the school and the platform, or check the questions trainees ask most.</p>
    </div>
    <div class="flex flex-wrap gap-3">
      <a class="rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
        href="<?= e(public_url('about')) ?>">About us</a>
      <a class="rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
        href="<?= e(public_url('faq')) ?>">FAQ</a>
      <a class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800"
        href="<?= e(public_url('learnhub')) ?>">How it works</a>
    </div>
  </div>
</section>

<p class="mt-8 text-center text-sm leading-6 text-slate-500">
  Felices Technological Training Center, Inc. · 6th St. Brgy 12 Patag, Catbalogan City, Samar 6700 ·
  <a class="font-semibold text-emerald-700 hover:underline" href="mailto:fttccatbalogan@gmail.com">fttccatbalogan@gmail.com</a> · 0917 320 2508
</p>

<?php /* The cookie notice and the legal column travel with footer.php. */
require __DIR__ . '/footer.php'; ?>

