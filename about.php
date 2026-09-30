<?php
/**
 * About — who runs LearnHub, what it is for, and how to reach us.
 *
 * Written for a trainee, a guardian and a visiting TESDA officer at once: the
 * school is named (and is the personal information controller in the Privacy
 * Policy), the platform is explained in plain words, and the contact details
 * here are the same ones the three policy pages print — keep the four in step.
 *
 * Only facts the rest of the app already asserts belong on this page: the two
 * names are the signatures certificate.php prints, the address and numbers are
 * the ones in privacy_policy.php, and the feature list mirrors what the pages
 * really do (materials, video, quizzes, attendance, live classes, messages,
 * schedule, certificates).
 */
require_once __DIR__ . '/lib.php';

/* One entry per numbered heading, exactly like the policy pages: a re-worded
   clause is a one-line edit, and bodies are authored here, never user input. */
$sections = [
    [
        'title' => 'The school behind it',
        'body'  => '<p><b>Felices Technological Training Center, Inc. (FTTC)</b> is a training center in '
            . 'Catbalogan City, Samar. Its programmes run from the school office at 6th St. Brgy 12 Patag, and '
            . 'LearnHub is the school\'s own system for the lessons, assessments and records of those programmes.</p>'
            . '<p class="mt-3">A certificate issued here carries the signatures of <b>Dr. Sorna C. Richardson</b>, '
            . 'School President, and <b>Ptr. James T. Richardson</b>, School Vice-President.</p>'
            . '<p class="mt-3">Because the school decides why your data is collected and how it is used, it is the '
            . '<b>personal information controller</b> for everything stored here — the '
            . '<a class="font-semibold text-emerald-700 hover:underline" href="' . e(legal_url('privacy'))
            . '">Privacy Policy</a> says exactly what that means for you.</p>',
    ],
    [
        'title' => 'Why the school built its own',
        'body'  => '<p>Training at FTTC happens in the lab and outside it: a lesson taught in the morning that has to '
            . 'be watched again at home, a quiz taken on a phone, a class that has to be joined from somewhere else, '
            . 'and an attendance record that may be asked about years later when a certificate is presented.</p>'
            . '<p class="mt-3">Rather than a shoebox of printouts and a photograph of the whiteboard, the school '
            . 'keeps all of it in one place that both the trainee and the trainer can open — and it hides nothing '
            . 'about what is kept. There is no advertising, no analytics and no tracking script anywhere in it, and '
            . 'the platform sets a single cookie: the login session.</p>',
    ],
    [
        'title' => 'What is inside',
        'body'  => '<ul class="list-disc space-y-2 pl-5">'
            . '<li><b>Courses and lessons</b> — documents, pasted text, images, uploaded video and video links, '
            . 'with the material each lesson needs.</li>'
            . '<li><b>Reading and watching progress</b> — how far through a document you got, and how much of a '
            . 'video you watched.</li>'
            . '<li><b>Quizzes</b> — a single attempt per trainee, answers locked as they go, a pass mark the trainer '
            . 'sets, and the result kept in the records.</li>'
            . '<li><b>Attendance</b> — taken while the trainee is genuinely in a course, with the entry and exit '
            . 'times kept per day.</li>'
            . '<li><b>Live classes</b> — a room the trainer opens, joined in the browser.</li>'
            . '<li><b>Messages</b> — a private thread between a trainee and their trainer.</li>'
            . '<li><b>Class schedule</b> — the weeks and dates of a course, so nobody has to remember them, with a '
            . 'note on the day something is set or moved.</li>'
            . '<li><b>Certificates</b> — issued once every lesson of a course is complete, each carrying a code that '
            . '<a class="font-semibold text-emerald-700 hover:underline" href="verify_certificate.php">anybody can '
            . 'check</a>.</li></ul>',
    ],
    [
        'title' => 'How it is built, and how it is kept',
        'body'  => '<ul class="list-disc space-y-2 pl-5">'
            . '<li>Hand-written PHP and a MySQL database on the school\'s own hosting — no framework, and nothing '
            . 'sent to a service we do not control beyond the short list in the Privacy Policy.</li>'
            . '<li>Uploaded lessons stay on our server and are streamed to the trainees entitled to them, instead of '
            . 'being parked on a public file host.</li>'
            . '<li>Passwords are stored only as one-way hashes, every form is protected against cross-site request '
            . 'forgery, and each page checks that the signed-in account is allowed to see it.</li>'
            . '<li>Error details go to a log file the web server refuses to serve, so a visitor never sees a path or '
            . 'a stack trace.</li>'
            . '<li>The <a class="font-semibold text-emerald-700 hover:underline" href="' . e(legal_url('terms'))
            . '">Terms &amp; Conditions</a> and the <a class="font-semibold text-emerald-700 hover:underline" href="'
            . e(legal_url('cookies')) . '">Cookie Policy</a> set the rest out in full.</li></ul>',
    ],
    [
        'title' => 'Who can use it, and how to get in',
        'body'  => '<p>There are three kinds of account: <b>trainee</b> (student), <b>trainer</b> (teacher) and the '
            . 'school\'s <b>administrator</b>. Student and trainer accounts are opened with a one-time code — a '
            . 'trainee uses the invitation code from their trainer, which also enrols them in the course that code '
            . 'belongs to, and a trainer uses the access code from the administrator.</p>'
            . '<p class="mt-3">Nothing is paid inside LearnHub. Training fees and enrolment papers are handled at the '
            . 'school office, as they always were.</p>'
            . '<p class="mt-3">Code lost, or already used by someone else? Ask your trainer or the school office for '
            . 'a fresh one, and see the <a class="font-semibold text-emerald-700 hover:underline" href="'
            . e(public_url('faq')) . '">FAQ</a> for the questions trainees ask most.</p>',
    ],
    [
        'title' => 'Reaching us',
        'body'  => '<p>School office: 6th St. Brgy 12 Patag, Catbalogan City, Samar 6700<br>E-mail: '
            . '<a class="font-semibold text-emerald-700 hover:underline" href="mailto:fttccatbalogan@gmail.com">'
            . 'fttccatbalogan@gmail.com</a><br>Mobile: 0917 320 2508</p>'
            . '<p class="mt-3">A question about a lesson, a quiz or a grade goes to your trainer — message them '
            . 'inside LearnHub. Anything about your data (a copy, a correction, deletion) goes to the e-mail address '
            . 'above with <b>Data privacy request</b> in the subject line, and the Privacy Policy tells you exactly '
            . 'what happens next.</p>',
    ],
];

$page_title = 'About';
require __DIR__ . '/header.php';
?>
<div class="mx-auto max-w-3xl">
  <p class="lh-kicker reveal">About</p>
  <h1 class="reveal mt-3 text-3xl font-bold text-slate-900">About LearnHub</h1>
  <p class="reveal mt-3 text-base leading-7 text-slate-600">
    LearnHub is the learning management system of <b>Felices Technological Training Center, Inc.</b> in Catbalogan
    City — where our training programmes keep their lessons, video tutorials, quizzes, attendance and certificates,
    so a trainee can carry on working between sessions and a trainer can see where the class really is.
  </p>

  <div class="reveal mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_1px_2px_rgba(17,33,26,.05),0_18px_40px_-28px_rgba(17,33,26,.25)]">
    <?php foreach ($sections as $i => $s): ?>
      <section class="<?= $i > 0 ? 'border-t border-slate-100 ' : '' ?>px-6 py-6">
        <h2 class="text-base font-bold text-slate-900"><?= $i + 1 ?>. <?= e((string) $s['title']) ?></h2>
        <div class="mt-3 space-y-3 text-sm leading-6 text-slate-600"><?= $s['body'] ?></div>
      </section>
    <?php endforeach; ?>
  </div>

  <p class="mt-4 text-xs leading-5 text-slate-400">
    Read next:
    <a class="font-semibold text-slate-500 hover:underline" href="<?= e(public_url('home')) ?>">Home</a> ·
    <a class="font-semibold text-slate-500 hover:underline" href="<?= e(public_url('learnhub')) ?>">How it works</a> ·
    <a class="font-semibold text-slate-500 hover:underline" href="<?= e(public_url('faq')) ?>">FAQ</a> ·
    <?= legal_links() ?>.
  </p>
</div>

<?php require __DIR__ . '/footer.php'; ?>

