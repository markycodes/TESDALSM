<?php
/**
 * FAQ — the questions trainees, trainers and guardians actually ask.
 *
 * Every answer describes what the code really does, so keep them true when the
 * pages change: invitation codes are single-use and enrol in one course
 * (register.php, enroll.php), a quiz unlocks after its lesson and allows one
 * attempt with the answers locked per question (quiz.php, quiz_answer.php),
 * attendance is taken from the course visits the browser reports
 * (attendance.php, attendance_day.php), a certificate is issued once every
 * lesson of a course is done and is publicly verifiable (certificate.php,
 * verify_certificate.php), a reset link works once for 30 minutes
 * (reset_password.php), and the only cookie set is the login session
 * (cookie_policy.php).
 *
 * $faq_groups holds the whole page: the accordion, the jump-list at the top and
 * the FAQPage structured data at the bottom all read it, so one answer cannot
 * drift out of step with another. Questions and answers are authored here,
 * never user input.
 */
require_once __DIR__ . '/lib.php';

/** Anchors for the jump-list: "Getting an account" -> "getting-an-account". */
function faq_slug(string $s): string
{
    return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($s)), '-');
}

/* group => [ [question, answer], ... ] — the answer may carry markup. */
$faq_groups = [
    'Getting an account' => [
        ['How do I get an account?',
            '<p>A <b>trainee</b> is given an <b>invitation code</b> by their trainer and registers with it — '
            . 'redeeming the code opens the account <i>and</i> enrols you in the course it belongs to. A <b>trainer</b> '
            . 'opens an account with the <b>access code</b> the school administrator gives them, and the '
            . 'administrator decides who becomes a trainer.</p>'
            . '<p class="mt-3"><a class="font-semibold text-emerald-700 hover:underline" href="register.php">Create '
            . 'your account</a> with your full name, an e-mail address you really read, a password and that code.</p>'],
        ['I have no code. Can I just sign up?',
            '<p>No — accounts are invitation-only, which is what keeps a training class to its own trainees. Ask your '
            . 'trainer for an invitation code (trainees) or the school administrator for an access code (trainers); '
            . 'the school office can always issue a fresh one.</p>'],
        ['My code is rejected, or says it was already used.',
            '<p>Codes are <b>single-use</b>: the moment one is redeemed it belongs to that account. Check the '
            . 'spelling first — letters and digits, no spaces — and ask for a new code if it has really been '
            . 'spent.</p>'
            . '<p class="mt-3">A code also belongs to exactly one course. If you are already signed in and the code '
            . 'is for a different course, open that course and unlock it there; a code for the course you are in is '
            . 'accepted on the spot.</p>'],
        ['Can I be a trainee and a trainer at once?',
            '<p>An account holds one role: trainee, trainer, or the school administrator. If you need more than one, '
            . 'ask the administrator — how the school lays out its accounts is their decision.</p>'],
        ['What does it cost?',
            '<p>Nothing inside LearnHub. No payment is taken anywhere in the platform, and your lessons, quiz '
            . 'results, attendance and certificate all come with your training. Training fees, if any, are settled at '
            . 'the school office as they always were.</p>'],
        ['Does it work on a phone?',
            '<p>Yes. The pages are built to work on a phone, a tablet and a computer, in any current browser '
            . '(Chrome, Edge, Firefox or Safari), and video plays in the page. Uploading a long video is the one job '
            . 'better done on the lab computer.</p>'],
    ],
    'Lessons, reading and video' => [
        ['How do I open a lesson?',
            '<p>Open <b>Courses</b>, pick your course, then the lesson. A document opens in the reader right in the '
            . 'page, an uploaded video plays there too, and a lesson that points at YouTube or Vimeo plays in a '
            . 'privacy-enhanced frame.</p>'],
        ['When does a lesson count as complete?',
            '<p>A reading lesson completes when you have read through to the end of the material — the reader keeps '
            . 'how deep you got, and asks for a sensible minimum time on the page so that a flick to the bottom does '
            . 'not count. A video lesson completes when the video has been watched to the end.</p>'
            . '<p class="mt-3">Finish every lesson in a course and the course itself is complete, which is what '
            . 'unlocks your certificate.</p>'],
        ['Can I re-watch or re-read something?',
            '<p>As often as you like. The lessons stay in your course for as long as you are enrolled, and going back '
            . 'over a lesson never takes away the progress you already made.</p>'],
        ['Can I download the materials?',
            '<p>Lessons are meant to be read and watched in the page, and files are streamed only to the trainees '
            . 'enrolled in the course instead of sitting on a public link — the same reason nobody else can see your '
            . 'classroom. Ask your trainer which materials you may keep for your own notes.</p>'],
        ['The video does not play.',
            '<p>An embedded lesson needs a connection to YouTube or Vimeo; an uploaded video needs a connection to '
            . 'the school server. Try a different browser or a different network first, then tell your trainer if it '
            . 'still refuses — the school can check the file itself.</p>'],
    ],
    'Quizzes and records' => [
        ['How many attempts do I get?',
            '<p><b>One.</b> A quiz unlocks once its lesson is complete, and from then on each answer locks as you '
            . 'choose it — the reading before you start says so, and the pass mark and question count are shown '
            . 'there too.</p>'],
        ['How is my score worked out?',
            '<p>The score is the share of questions you answered correctly, as a percentage, against the pass mark '
            . 'your trainer set for that quiz. Pass it and the lesson shows as done; the result is kept in your '
            . 'records either way.</p>'],
        ['Can I go over my answers afterwards?',
            '<p>Yes. Once you submit, the result screen walks through the questions with your answer next to the '
            . 'correct one, and <b>My Quiz Records</b> keeps every result you have ever had, so you can look back '
            . 'later.</p>'],
        ['Who can see my score?',
            '<p>You, the trainer of that course, and the school administrator. Other trainees cannot see your '
            . 'results, your reading progress or your attendance — a trainer sees the class, not the other way '
            . 'round.</p>'],
        ['Can I take a quiz again if something goes wrong?',
            '<p>Not by yourself — one attempt is the design, so that everyone is measured the same way. If the '
            . 'connection dropped or you pressed the wrong thing, tell your trainer straight away: a trainer (or the '
            . 'administrator) decides what happens next.</p>'],
    ],
    'Attendance and live classes' => [
        ['How is my attendance recorded?',
            '<p>The platform records a visit while you are actually in a course: entering opens a session and leaving '
            . 'closes it, and the times are kept against the day, together with the IP address the visit came from — '
            . 'that is what makes an attendance record stand up later. Your trainer sees the day and the class list; '
            . 'your own record is on the <b>Enrollments</b> page, next to your classmates.</p>'],
        ['I attended but nothing shows.',
            '<p>Attendance follows the browser session, so a visit from a device that never opened the course, or a '
            . 'connection that dropped, can leave a gap. Tell your trainer the day and the time — the school can '
            . 'correct an attendance entry.</p>'],
        ['How do I join a live class?',
            '<p>When your trainer opens the class, it appears on your course with a join button. It runs in the '
            . 'browser — no software to install — and your trainer controls when the room is open.</p>'],
        ['Does a live class use my camera?',
            '<p>The room is a Jitsi meeting, and your browser asks before anything is shared: you choose whether to '
            . 'send camera or microphone, and you can join to listen and type instead. Nothing in LearnHub records a '
            . 'live class.</p>'],
    ],
    'Certificates' => [
        ['When do I get my certificate?',
            '<p>As soon as every lesson of a course is complete. The certificate carries your name, the course and '
            . 'the date. Open the course again and use <b>View your certificate</b> whenever you need a fresh copy — '
            . '"Print" or "Save as PDF" from the browser gives you one to keep or hand in.</p>'],
        ['How can somebody check that my certificate is real?',
            '<p>Every certificate has a code. Anyone can enter it on the '
            . '<a class="font-semibold text-emerald-700 hover:underline" href="verify_certificate.php">certificate '
            . 'verification page</a> and see the trainee, the course and the issue date. That page is public on '
            . 'purpose — being checkable is what a certificate is for — and it shows nothing beyond the certificate '
            . 'itself.</p>'],
        ['My name is spelled wrong on it.',
            '<p>The name printed comes from your account, so say it as early as you can: tell your trainer or the '
            . 'school office and have the account corrected before you present the certificate anywhere.</p>'],
        ['I lost my copy.',
            '<p>No need to ask for a new one: open the course again, use <b>View your certificate</b> and print it. '
            . 'The code stays the same, so a copy you already handed in is still verifiable.</p>'],
    ],
    'Your account, e-mail and data' => [
        ['How do I change my password?',
            '<p>Open <b>Settings</b> from the sidebar — that is where your password, your appearance choices and the '
            . 'daily reminder live.</p>'],
        ['I forgot my password.',
            '<p>On the login page ask for a reset link and we e-mail you one. The link works <b>once</b> and expires '
            . 'after 30 minutes, so use it straight away; if it has gone stale, just request another. Nobody at the '
            . 'school can read your password — it is stored only as a one-way hash — so a reset, not a reminder, is '
            . 'how you get back in.</p>'],
        ['What e-mail will you send me?',
            '<p>A welcome note when your account opens, a notice when a quiz result is ready, one when a trainer '
            . 'messages you, a reset link only when you ask for it, and — optionally — a single daily reminder that '
            . 'you have work waiting, which you can switch off in <b>Settings</b>. No newsletters, no advertising, '
            . 'nothing else.</p>'],
        ['How do I stop the daily reminder?',
            '<p><b>Settings → Notifications</b> has the switch. Turning it off stops the mail immediately; nothing '
            . 'else about your account changes.</p>'],
        ['What do you know about me?',
            '<p>Your name, e-mail address, a hashed password and your role; what you have read and watched; your '
            . 'quiz results; your attendance times and the IP address they came from; the messages you send; and the '
            . 'certificates you have earned. The '
            . '<a class="font-semibold text-emerald-700 hover:underline" href="' . e(legal_url('privacy'))
            . '">Privacy Policy</a> lists each one, why it is kept and for how long.</p>'],
        ['Do you track me with cookies?',
            '<p>No. The platform sets <b>one cookie</b> — the login session — and runs no analytics, advertising or '
            . 'tracking scripts. A few buttons can bring in somebody else\'s cookie when you use them (the video '
            . 'players, and the human check when the administrator switches it on); the '
            . '<a class="font-semibold text-emerald-700 hover:underline" href="' . e(legal_url('cookies'))
            . '">Cookie Policy</a> lists every one, and how to block them.</p>'],
        ['Can I see or delete what you hold?',
            '<p>Most of it you can see for yourself: your results under <b>My Quiz Records</b>, your lessons and '
            . 'progress on the course page, your attendance on <b>Enrollments</b>. To get a copy, have something '
            . 'corrected, or close your account, ask the school office (subject line <b>Data privacy request</b>). '
            . 'We answer within 30 days. Erasing an account takes its activity with it, except the records the school '
            . 'must keep — a completed training record or an issued certificate.</p>'],
        ['I am under 18.',
            '<p>A guardian consents on your behalf — that happens on the enrolment form at the school office, and '
            . 'the Privacy Policy tells the guardian exactly what the system stores. A guardian can also ask us at '
            . 'any time to correct or remove a trainee\'s data.</p>'],
    ],
    'For trainers' => [
        ['How do I create a course and fill it with lessons?',
            '<p><b>Courses → create a course</b>, giving it a title, a category and a description, then open it and '
            . 'add the material: upload a document, paste text or add images, upload video files, or paste a YouTube '
            . 'or Vimeo link. Build the lessons from that material — and set a quiz on the lesson that needs '
            . 'one.</p>'],
        ['How do I get my trainees in?',
            '<p>Generate an <b>invitation code</b> for the course and hand it out; redeeming it opens the trainee\'s '
            . 'account and enrols them in that course. Codes are single-use, so give one per trainee — the course '
            . 'page shows who has redeemed what, and you can print or share a fresh code whenever you need one.</p>'],
        ['How do I build a quiz?',
            '<p>On a lesson, open the quiz editor: add the questions, mark the correct answers, set the pass mark and '
            . 'save. Trainees see the quiz once they have completed the lesson, and each of them gets a single '
            . 'attempt — so the marks are comparable across the class.</p>'],
        ['Can I see who is actually reading and watching?',
            '<p>Yes. The course page and the records pages show each trainee\'s reading depth and watch time, their '
            . 'quiz scores on <b>Student Quiz Records</b>, and <b>Enrollments</b> gathers the class, who is online '
            . 'right now, and the attendance log. <b>Attendance</b> gives you one day at a time.</p>'],
        ['How do I set the class schedule?',
            '<p><b>Schedule → pick the course</b>, then add the weeks or the one-off dates. Saving notifies the '
            . 'enrolled trainees — the bell in their top bar and an e-mail — and the schedule shows up on their '
            . 'dashboard.</p>'],
        ['How do I open a live class?',
            '<p>From the course or the schedule, open the live class for that course and share the join link; '
            . 'trainees join from their own course page while it is open. Attendance is recorded the same way it '
            . 'always is — from the visits their browser reports.</p>'],
    ],
];

$page_title = 'Frequently asked questions';
require __DIR__ . '/header.php';
?>
<style>
  /* The accordion marker: a + that turns into an × when the answer is open, and
     no operating-system triangle in the way (Safari needs the -webkit- rule).
     Scoped to this page's own class so it cannot touch the rest of the shell,
     and it still stands still under prefers-reduced-motion. */
  .lh-faq > summary { list-style: none; cursor: pointer; }
  .lh-faq > summary::-webkit-details-marker { display: none; }
  .lh-faq-mark { transition: transform .18s ease; }
  .lh-faq[open] .lh-faq-mark { transform: rotate(45deg); }
  @media (prefers-reduced-motion: reduce) { .lh-faq-mark { transition: none; } }
</style>

<div class="mx-auto max-w-3xl">
  <p class="lh-kicker reveal">Help</p>
  <h1 class="reveal mt-3 text-3xl font-bold text-slate-900">Frequently asked questions</h1>
  <p class="reveal mt-3 text-base leading-7 text-slate-600">
    The questions trainees, trainers and guardians ask us most: what an invitation code is for, how a quiz works, how
    attendance is recorded, and what happens to what you type here. If yours is not below, the school office details
    are at the end of the page.
  </p>

  <nav class="reveal mt-6 flex flex-wrap gap-2" aria-label="FAQ sections">
    <?php foreach (array_keys($faq_groups) as $group): ?>
      <a class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50"
        href="#<?= e(faq_slug((string) $group)) ?>"><?= e((string) $group) ?></a>
    <?php endforeach; ?>
  </nav>

  <?php foreach ($faq_groups as $group => $rows): ?>
    <section id="<?= e(faq_slug((string) $group)) ?>" class="mt-8">
      <h2 class="text-lg font-bold text-slate-900"><?= e((string) $group) ?></h2>
      <div class="reveal mt-3 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_1px_2px_rgba(17,33,26,.05),0_18px_40px_-28px_rgba(17,33,26,.25)]">
        <?php foreach ($rows as $i => $row): ?>
        <?php /* A native <details>: it folds with scripting off, the keyboard
                 drives it, and a browser search can find a closed answer. */ ?>
        <details class="lh-faq <?= $i > 0 ? 'border-t border-slate-100' : '' ?>">
          <summary class="flex items-start justify-between gap-3 px-5 py-4 hover:bg-slate-50">
            <span class="text-sm font-semibold text-slate-900"><?= e((string) $row[0]) ?></span>
            <span class="lh-faq-mark text-slate-400" aria-hidden="true">+</span>
          </summary>
          <div class="px-5 pb-4 text-sm leading-6 text-slate-600"><?= $row[1] ?></div>
        </details>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>

  <section class="reveal mt-8 rounded-2xl border border-slate-200 bg-white p-6">
    <h2 class="text-lg font-semibold text-slate-900">Still stuck?</h2>
    <p class="mt-1 text-sm leading-6 text-slate-500">Ask your trainer first — they know your class, and most things
      (a code that will not redeem, a mark you want explained) are theirs to settle. For anything about the platform
      itself, the school office is here:</p>
    <p class="mt-3 text-sm leading-6 text-slate-600">
      6th St. Brgy 12 Patag, Catbalogan City, Samar 6700<br>
      <a class="font-semibold text-emerald-700 hover:underline" href="mailto:fttccatbalogan@gmail.com">fttccatbalogan@gmail.com</a>
      · 0917 320 2508
    </p>
  </section>

  <p class="mt-4 text-xs leading-5 text-slate-400">
    Read next:
    <a class="font-semibold text-slate-500 hover:underline" href="<?= e(public_url('home')) ?>">Home</a> ·
    <a class="font-semibold text-slate-500 hover:underline" href="<?= e(public_url('learnhub')) ?>">How it works</a> ·
    <a class="font-semibold text-slate-500 hover:underline" href="<?= e(public_url('about')) ?>">About</a> ·
    <?= legal_links() ?>.
  </p>
</div>

<?php
/* FAQPage structured data — the same questions and answers as the accordion
   above, read from $faq_groups so a search engine can never be told something
   this page does not say. Markup is flattened to plain text, and JSON_HEX_TAG
   keeps a "<" inside an answer from ever ending the script element early. */
$lh_faq_ld = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => []];
foreach ($faq_groups as $rows) {
    foreach ($rows as $row) {
        $lh_plain = html_entity_decode(preg_replace('/<[^>]+>/', ' ', (string) $row[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lh_faq_ld['mainEntity'][] = [
            '@type' => 'Question',
            'name' => trim((string) preg_replace('/\s+/u', ' ', (string) $row[0])),
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => trim((string) preg_replace('/\s+/u', ' ', $lh_plain)),
            ],
        ];
    }
}
?>
<script type="application/ld+json"><?= json_encode($lh_faq_ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

<?php require __DIR__ . '/footer.php'; ?>
