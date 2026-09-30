<?php
/**
 * Privacy Policy — the public page every form links to (see consent_field() /
 * consent_notice() in lib.php).
 *
 * The controller is the school that runs this LMS, and the text is written
 * against the Philippine Data Privacy Act of 2012 (RA 10173) with its IRR and
 * NPC issuances — plus GDPR wording, because a trainee or visitor can be in the
 * EU. Keep the facts in step with the code: the data list below mirrors the
 * tables in lib.php (users, enrollments, progress, video_progress, read_progress,
 * quiz_results, attendance, live_class_state, messages, certificates, user_meta),
 * the mailer in lib.php, and the third parties the pages really load.
 *
 * A material change here must also bump LEGAL_VERSION in lib.php: the version an
 * account accepted is recorded per account when it registers.
 */
require_once __DIR__ . '/lib.php';

/* One entry per numbered heading — small markup, and a re-worded clause becomes
   a one-line edit. Bodies are authored here, never user input. */
$sections = [
    [
        'title' => 'Who we are, and what this policy covers',
        'body'  => '<p><b>Felices Technological Training Center, Inc. (FTTC)</b> owns and runs this learning '
            . 'management system ("LearnHub"), so we are the <b>personal information controller</b> for the data '
            . 'below — we decide why it is collected and how it is used.</p>'
            . '<p class="mt-3">6th St. Brgy 12 Patag, Catbalogan City, Samar, 6700 · '
            . '<a class="font-semibold text-emerald-700 hover:underline" href="mailto:fttccatbalogan@gmail.com">fttccatbalogan@gmail.com</a> · '
            . '0917 320 2508</p>'
            . '<p class="mt-3">It covers every page and form here: browsing, creating an account, joining a course, '
            . 'attending a live class, taking a quiz, messaging a teacher, and the certificate we issue. Read it with '
            . 'our <a class="font-semibold text-emerald-700 hover:underline" href="' . e(legal_url('terms')) . '">Terms '
            . '&amp; Conditions</a> and <a class="font-semibold text-emerald-700 hover:underline" href="'
            . e(legal_url('cookies')) . '">Cookie Policy</a>.</p>',
    ],
    [
        'title' => 'What we collect',
        'body'  => '<ul class="list-disc space-y-2 pl-5">'
            . '<li><b>Account data</b> — your full name, e-mail address, a <i>hashed</i> password (nobody, us '
            . 'included, can read the original), your role (student, teacher or administrator), the date the account '
            . 'was created, and the one-time invitation or access code redeemed to open it.</li>'
            . '<li><b>Course activity</b> — the courses you joined, how far you have read each material, how many '
            . 'seconds of each video you watched, and the lessons you completed.</li>'
            . '<li><b>Assessments</b> — the quizzes on your lessons, the answers you saved, your score and whether '
            . 'you passed.</li>'
            . '<li><b>Attendance and live-class data</b> — when you entered and left a live class, whether your hand '
            . 'was raised, the last time your browser checked in, and the <b>IP address</b> stored with an attendance '
            . 'entry (so an attendance record can be shown to be genuine).</li>'
            . '<li><b>Messages</b> — the text you send to a teacher or student, and when it was read.</li>'
            . '<li><b>Certificates</b> — the certificate code, the course, the issue date and the name printed on it. '
            . 'Certificates are <b>publicly verifiable</b> at <a class="font-semibold text-emerald-700 hover:underline" '
            . 'href="verify_certificate.php">verify_certificate.php</a> by anyone holding the code — that is what a '
            . 'certificate is for.</li>'
            . '<li><b>Content uploaded by teachers</b> — the materials, files and video links added to a course, '
            . 'shared with the students enrolled in it.</li>'
            . '<li><b>Technical data</b> — the login session cookie (see the Cookie Policy) and the log entry written '
            . 'when something goes wrong, which can contain an IP address and a file name. We run no advertising, '
            . 'analytics or tracking scripts.</li></ul>',
    ],
    [
        'title' => 'Why we use it, and the lawful basis',
        'body'  => '<div class="overflow-x-auto"><table class="w-full text-left text-sm">'
            . '<thead class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">'
            . '<tr><th class="px-3 py-2 font-semibold">Purpose</th><th class="px-3 py-2 font-semibold">Data</th>'
            . '<th class="px-3 py-2 font-semibold">Basis (RA 10173 / GDPR Art. 6)</th></tr></thead>'
            . '<tbody class="divide-y divide-slate-100">'
            . '<tr><td class="px-3 py-2">Creating your account and signing you in</td><td class="px-3 py-2">Name, '
            . 'e-mail, hashed password, role</td><td class="px-3 py-2">Performance of our agreement with you</td></tr>'
            . '<tr><td class="px-3 py-2">Running your training: materials, progress, quizzes, certificates</td>'
            . '<td class="px-3 py-2">Course activity, assessment and certificate data</td><td class="px-3 py-2">'
            . 'Performance of our agreement; our legitimate interest in delivering accredited training</td></tr>'
            . '<tr><td class="px-3 py-2">Attendance records and training transcripts</td><td class="px-3 py-2">'
            . 'Attendance entries, IP address, times</td><td class="px-3 py-2">Legal obligation; legitimate interest '
            . 'in verifiable training records</td></tr>'
            . '<tr><td class="px-3 py-2">Messages between students and teachers</td><td class="px-3 py-2">Message '
            . 'text, read state</td><td class="px-3 py-2">Performance of our agreement</td></tr>'
            . '<tr><td class="px-3 py-2">E-mails we send you (welcome, quiz result, new message, password reset, an '
            . 'optional daily reminder)</td><td class="px-3 py-2">E-mail address, your name</td><td class="px-3 py-2">'
            . 'Performance of our agreement; legitimate interest in a working service</td></tr>'
            . '<tr><td class="px-3 py-2">Keeping the site safe: bot and spam checks, lockouts after repeated failed '
            . 'logins</td><td class="px-3 py-2">IP address, session data, form timings</td><td class="px-3 py-2">Our '
            . 'legitimate interest in security, fraud prevention and availability</td></tr>'
            . '<tr><td class="px-3 py-2">Accepting these policies at registration</td><td class="px-3 py-2">Which '
            . 'version you accepted, and when</td><td class="px-3 py-2">Consent — and our duty to be able to prove it</td></tr>'
            . '</tbody></table></div>'
            . '<p class="mt-3">We never sell, rent or trade personal data, and we make no automated decisions about '
            . 'you — no profiling, no scoring, no data brokers.</p>',
    ],
    [
        'title' => 'Who else processes it',
        'body'  => '<p>The list of outside services is as short as the feature list allows, and each one receives only '
            . 'what its job needs:</p>'
            . '<ul class="mt-3 list-disc space-y-2 pl-5">'
            . '<li><b>Our web host</b> stores this site and its MySQL database, so all of the data above lives on their '
            . 'servers under our account. The <code>data/</code> folder (error log, generated admin credentials) is '
            . 'blocked from the web.</li>'
            . '<li><b>An e-mail delivery service</b> (Brevo, SendGrid or Resend — whichever our administrator '
            . 'configured) receives the recipient address and the message so it can be delivered, and may keep '
            . 'delivery statistics. Mail can also go out through the hosting server\'s own mail service.</li>'
            . '<li><b>Cloudflare Turnstile</b> — only when the administrator has switched the human check on. It sees '
            . 'your IP address and browser signals and answers "human or robot"; the cookies it may set are listed in '
            . 'the Cookie Policy.</li>'
            . '<li><b>Google Fonts</b> — the page fonts come from Google\'s CDN, so your browser contacts Google and '
            . 'your IP address is visible to them. No cookies are involved.</li>'
            . '<li><b>YouTube (privacy-enhanced mode) and Vimeo</b> — a lesson that uses a video link embeds their '
            . 'player; only then do they see your request, and the player may set its own cookies once you press play.</li>'
            . '<li><b>Jitsi / 8x8</b> — live classes run on the Jitsi server our administrator configured, so your '
            . 'display name and connection data pass through that service while a class is open.</li>'
            . '<li><b>Authorities and auditors</b> — only where the law, TESDA rules or a valid order require it, and '
            . 'only the records actually asked for.</li></ul>'
            . '<p class="mt-3">Where a provider is outside the Philippines we rely on that provider\'s lawful transfer '
            . 'safeguards, such as the standard contractual clauses in its data-processing terms. Ask us for the '
            . 'details of the services in use on this installation.</p>',
    ],
    [
        'title' => 'Where it lives, and how it is protected',
        'body'  => '<ul class="list-disc space-y-2 pl-5">'
            . '<li>Passwords are stored only as one-way hashes, so a database copy does not reveal them.</li>'
            . '<li>The session cookie is <code>HttpOnly</code> and <code>SameSite=Lax</code>, and it turns '
            . '<code>Secure</code> on automatically over HTTPS — scripts cannot read it and a cross-site form cannot '
            . 'ride on it.</li>'
            . '<li>Every form is protected against cross-site request forgery, and each page checks that the signed-in '
            . 'account is allowed to see it (a student cannot open a teacher\'s or another student\'s records).</li>'
            . '<li>In lists shown to teachers, students\' e-mail addresses are displayed partially masked.</li>'
            . '<li>Error details are written to a web-blocked file instead of being printed to visitors, and uploads '
            . 'are stored on the server, never on a public file-sharing site.</li></ul>',
    ],
    [
        'title' => 'How long we keep it',
        'body'  => '<div class="overflow-x-auto"><table class="w-full text-left text-sm">'
            . '<thead class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">'
            . '<tr><th class="px-3 py-2 font-semibold">Record</th><th class="px-3 py-2 font-semibold">Kept for</th></tr></thead>'
            . '<tbody class="divide-y divide-slate-100">'
            . '<tr><td class="px-3 py-2">Account, course activity, quizzes, messages</td><td class="px-3 py-2">While '
            . 'your account exists. Delete it (ask us) and the linked activity goes with it.</td></tr>'
            . '<tr><td class="px-3 py-2">Attendance and assessment records</td><td class="px-3 py-2">For the duration '
            . 'of your training and afterwards only as long as we must be able to confirm attendance and results. We '
            . 'review these records each year and delete what is no longer needed.</td></tr>'
            . '<tr><td class="px-3 py-2">Issued certificates (name, course, code)</td><td class="px-3 py-2">Kept, '
            . 'because the certificate must stay verifiable for as long as it is presented. A code can be revoked if '
            . 'it was issued in error or obtained by fraud.</td></tr>'
            . '<tr><td class="px-3 py-2">Server error log</td><td class="px-3 py-2">A rolling file, cleared as new '
            . 'entries replace old ones (weeks, not years).</td></tr>'
            . '<tr><td class="px-3 py-2">Login session</td><td class="px-3 py-2">Until you close the browser, log out, '
            . 'or the session expires on the server.</td></tr>'
            . '</tbody></table></div>',
    ],
    [
        'title' => 'Your rights',
        'body'  => '<p>As a data subject under RA 10173 you may:</p>'
            . '<ul class="mt-3 list-disc space-y-2 pl-5">'
            . '<li><b>be informed</b> that your data is being processed (this page is part of that),</li>'
            . '<li><b>object</b> to processing based on legitimate interest, and to direct marketing — we send none,</li>'
            . '<li><b>access</b> the personal data we hold about you,</li>'
            . '<li>have it <b>corrected</b> if it is wrong — most of it you can see, and a teacher or the '
            . 'administrator can fix the rest,</li>'
            . '<li>ask us to <b>erase or block</b> it, subject to records we must keep (a certificate, a training '
            . 'record required by law),</li>'
            . '<li>claim <b>damages</b> for a violation of your rights,</li>'
            . '<li>obtain a copy in a <b>portable</b> form where the data was given electronically, and</li>'
            . '<li><b>complain</b> to the National Privacy Commission ('
            . '<a class="font-semibold text-emerald-700 hover:underline" href="https://www.privacy.gov.ph" target="_blank" rel="noopener">privacy.gov.ph</a>).</li></ul>'
            . '<p class="mt-3">If you are in the EU, EEA or UK you have the equivalent GDPR rights, including '
            . 'restriction of processing, withdrawal of consent at any time (it does not affect what was already '
            . 'done lawfully), and a complaint to your local supervisory authority.</p>'
            . '<p class="mt-3"><b>How to ask:</b> e-mail <a class="font-semibold text-emerald-700 hover:underline" '
            . 'href="mailto:fttccatbalogan@gmail.com?subject=Data%20privacy%20request">fttccatbalogan@gmail.com</a> with '
            . 'the subject "Data privacy request", or ask at the school office. Tell us which right you are using and '
            . 'from which e-mail address; we may ask one or two questions to satisfy ourselves that the request really '
            . 'comes from you. We answer within <b>30 days</b>, and tell you if we need longer.</p>',
    ],
    [
        'title' => 'Trainees under 18',
        'body'  => '<p>Training programmes often include minors. A guardian\'s consent is relied on for a trainee under '
            . '18 — the enrolment form at the school office is where that is given, and this page tells the guardian '
            . 'exactly what the system stores. A guardian can ask us to correct or remove a trainee\'s data at any '
            . 'time using the contact details above; we may need to keep the assessment and attendance records of a '
            . 'training programme that has already been completed.</p>',
    ],
    [
        'title' => 'Changes to this policy',
        'body'  => '<p>The "Last updated" date at the top of this page always shows the current version. If a change '
            . 'is material — a new purpose, a new recipient — we will say so here and, where the law requires it, ask '
            . 'for your consent again the next time you sign in. Every account keeps the version it actually accepted, '
            . 'so we can always tell which text applied to you.</p>',
    ],
];

$page_title = 'Privacy Policy';
require __DIR__ . '/header.php';
?>
<div class="mx-auto max-w-3xl">
  <p class="lh-kicker reveal">Legal</p>
  <h1 class="reveal mt-3 text-3xl font-bold text-slate-900">Privacy Policy</h1>
  <p class="reveal mt-2 text-sm text-slate-500">
    Last updated <b><?= e(LEGAL_VERSION) ?></b> · applies to the LearnHub learning management system run by
    Felices Technological Training Center, Inc.<?php if ($user): ?> · signed in as
      <b><?= e((string) $user['name']) ?></b><?php endif; ?>
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
    Read next: <?= legal_links() ?>.
  </p>
</div>

<?php require __DIR__ . '/footer.php'; ?>
