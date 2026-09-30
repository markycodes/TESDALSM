<?php
/**
 * Terms & Conditions — the public page the registration checkbox points at
 * (see consent_field() / consent_check() in lib.php).
 *
 * Written to describe this app as it actually behaves: invitation/access codes
 * for registration (register.php), progress-based certificates issued at 100%
 * (certificate.php), live classes on the configured Jitsi server, and the
 * upload/quiz/message features in between. A material change here must also
 * bump LEGAL_VERSION in lib.php.
 */
require_once __DIR__ . '/lib.php';

/* One entry per numbered heading — small markup, one-line edits later. */
$sections = [
    [
        'title' => 'These terms, and the school that runs this site',
        'body'  => '<p>This learning management system ("LearnHub", "the site") is operated by <b>Felices '
            . 'Technological Training Center, Inc.</b> of 6th St. Brgy 12 Patag, Catbalogan City, Samar, 6700 '
            . '("the school", "we", "us"). By opening an account, signing in, or using any part of the site you accept '
            . 'these Terms &amp; Conditions. If you do not accept them, do not use the site.</p>'
            . '<p class="mt-3">They apply alongside our <a class="font-semibold text-emerald-700 hover:underline" href="'
            . e(legal_url('privacy')) . '">Privacy Policy</a> (what we do with your data) and <a class="font-semibold '
            . 'text-emerald-700 hover:underline" href="' . e(legal_url('cookies')) . '">Cookie Policy</a> (the one '
            . 'cookie we set). Where a training programme has its own enrolment form or student handbook, those school '
            . 'rules also apply; if they conflict with these terms on an academic matter (attendance, assessment, '
            . 'fees), the school\'s own rules prevail.</p>',
    ],
    [
        'title' => 'Who may use the site, and how accounts are opened',
        'body'  => '<ul class="list-disc space-y-2 pl-5">'
            . '<li><b>Invitation only.</b> Accounts are not open to the public. A student registers with a one-time '
            . 'invitation code from a teacher, and that code both creates the account and enrols the student in the '
            . 'course it belongs to; a teacher registers with an access code issued by the school administrator. Codes '
            . 'are single-use and must not be shared, sold or posted anywhere.</li>'
            . '<li><b>Accurate details.</b> The name and e-mail address you register with must be your own and correct '
            . '— they appear on your certificate and in e-mails we send you.</li>'
            . '<li><b>One account each.</b> Do not create an account for someone else, or a second account to evade a '
            . 'suspension or a lockout.</li>'
            . '<li><b>Minors.</b> A trainee under 18 may use the site with the consent of a parent or guardian, which '
            . 'is given through the school\'s enrolment process. A guardian may contact us at any time about that '
            . 'account (see the Privacy Policy).</li>'
            . '<li><b>Your password is yours.</b> Keep it secret, use a password you do not use anywhere else, and tell '
            . 'the school immediately if you think someone else knows it. You are responsible for activity under your '
            . 'account, so do not let anyone else use it while you are signed in.</li></ul>',
    ],
    [
        'title' => 'Fair use of the site',
        'body'  => '<p>The site exists for training. You agree <b>not</b> to:</p>'
            . '<ul class="mt-3 list-disc space-y-2 pl-5">'
            . '<li>upload, post or link to anything unlawful, threatening, hateful, obscene, defamatory, or that '
            . 'infringes someone else\'s copyright, trademark or privacy;</li>'
            . '<li>harass, bully or impersonate another person, or use the messaging feature to send spam or '
            . 'advertising;</li>'
            . '<li>copy, record, download or redistribute course materials, videos or other students\' data without '
            . 'permission — they are for enrolled learners only;</li>'
            . '<li>probe, scan, overload or attack the site, attempt to reach data or pages that are not yours, or '
            . 'automate requests in a way that degrades the service for others;</li>'
            . '<li>upload malware, or any file you do not have the right to distribute;</li>'
            . '<li>misrepresent your identity, your qualifications or your results, or falsify attendance.</li></ul>'
            . '<p class="mt-3">The school may remove content, revoke a code or suspend access for a breach of this '
            . 'section, and will say why unless doing so would expose another person\'s data.</p>',
    ],
    [
        'title' => 'Assessments, progress and academic honesty',
        'body'  => '<ul class="list-disc space-y-2 pl-5">'
            . '<li>Progress is measured by the site: how far you read a material, how much of a video you watched, and '
            . 'the quizzes your teacher assigned. Leaving a video playing unattended, or scripting your way through a '
            . 'lesson, is not "completing" it and can void the certificate (see section 7).</li>'
            . '<li>Quizzes are your own work. Helping another student during a quiz, or having someone answer for you, '
            . 'is a breach of these terms and of the school\'s academic rules.</li>'
            . '<li>Attendance is recorded when you take part in a live class or when your teacher marks it. Give your '
            . 'teacher the same name you are registered with, so attendance and certificates match.</li>'
            . '<li>A quiz result, score and pass/fail decision are shared with the teachers of the course you are '
            . 'enrolled in, and with the school administration.</li></ul>',
    ],
    [
        'title' => 'Materials: who owns what',
        'body'  => '<p><b>Your content stays yours.</b> A teacher who uploads a document, a video or a link keeps all '
            . 'rights in it. By uploading, the teacher grants the school a non-exclusive, royalty-free licence to '
            . 'store, format and show that material to the students enrolled in that course, and to keep a copy for '
            . 'as long as the course exists (removing it removes it from student view). Messages you send, and quiz '
            . 'answers you give, remain yours — we use them only to run the course.</p>'
            . '<p class="mt-3"><b>You must have the right to upload it.</b> Do not upload a textbook scan, a film, or '
            . 'any file that licence does not allow. Where a lesson uses a YouTube or Vimeo link, that video stays on '
            . 'the platform and its own terms and copyright rules apply instead.</p>'
            . '<p class="mt-3">The site itself — its code, design, logo and the school\'s own name and marks — belongs '
            . 'to the school. Your account gives you the right to use it for your training, nothing more: no copying, '
            . 'reselling or reverse engineering of the site.</p>',
    ],
    [
        'title' => 'Live classes',
        'body'  => '<ul class="list-disc space-y-2 pl-5">'
            . '<li>A live class opens on the video server the school configured (Jitsi, or Jitsi as a Service). Your '
            . 'display name, connection data and join/leave times pass through that service while the room is open, '
            . 'and the site records when you joined and left.</li>'
            . '<li>Behave in a class as you would in a classroom: one speaker at a time, no screen sharing or '
            . 'recording of other participants without their agreement, no broadcasting the room link to people who '
            . 'are not enrolled.</li>'
            . '<li>The school does not record classes by default. If a teacher ever intends to record, they will say so '
            . 'first.</li>'
            . '<li>A dropped connection, a full room or a Jitsi server outage is outside our control; your teacher will '
            . 'decide how that affects attendance for the session.</li></ul>',
    ],
    [
        'title' => 'Certificates',
        'body'  => '<p>A certificate of completion is generated automatically when every lesson of a course has been '
            . 'completed, and it carries a unique code that anyone can check at <a class="font-semibold '
            . 'text-emerald-700 hover:underline" href="verify_certificate.php">verify_certificate.php</a>. The code, '
            . 'the course name and the holder\'s name are <b>displayed to whoever holds the code</b> — that is how '
            . 'verification works, and it is described in the Privacy Policy.</p>'
            . '<p class="mt-3">The school may <b>revoke</b> a certificate if it was issued in error, if progress was '
            . 'obtained by cheating or scripting, or if the account was found to be fraudulent; a revoked code will no '
            . 'longer verify. A certificate records what this site recorded you completed — it is not a licence, a '
            . 'national certification or a TESDA assessment result, and it does not replace any assessment the school '
            . 'runs separately.</p>',
    ],
    [
        'title' => 'Availability, and changes to the service',
        'body'  => '<ul class="list-disc space-y-2 pl-5">'
            . '<li>The site is provided as it is. We work to keep it available, but we do not promise uninterrupted or '
            . 'error-free service: hosting maintenance, an internet outage, a video-server outage or a security fix can '
            . 'interrupt access. Planned work is announced to teachers when we can.</li>'
            . '<li>We may add, change or retire a feature — and may correct a course, an attendance record or a result '
            . 'that was recorded wrongly.</li>'
            . '<li>Keep your own copy of anything you must not lose. A file you uploaded, or a message you sent, should '
            . 'not be your only copy of something important.</li></ul>',
    ],
    [
        'title' => 'Suspension and closing an account',
        'body'  => '<p><b>By you:</b> ask your teacher or the school office to close your account, or e-mail us (see '
            . 'the contact details in the Privacy Policy). What happens to your data afterwards is set out there.</p>'
            . '<p class="mt-3"><b>By us:</b> the school may suspend or close an account that breaches these terms, that '
            . 'was opened fraudulently, that is used to harm another person or the site, or when a training programme '
            . 'ends and the enrolment with it. Where it is reasonable to do so, we tell you and give you a chance to '
            . 'put things right first.</p>'
            . '<p class="mt-3"><b>Effect:</b> access ends and the account can no longer sign in, be enrolled, or take a '
            . 'quiz. Records the school must keep (attendance, results, an issued certificate) are kept as described in '
            . 'the Privacy Policy.</p>',
    ],
    [
        'title' => 'Disclaimers and limits',
        'body'  => '<ul class="list-disc space-y-2 pl-5">'
            . '<li>Course content is teaching material, not professional, legal or medical advice. Follow your '
            . 'instructor\'s guidance and the school\'s own safety rules in practical training.</li>'
            . '<li>A mark, a progress percentage or a certificate on this site is a record of what the site measured. '
            . 'It is not a guarantee of competence, employment or of any licence or national certificate.</li>'
            . '<li>To the extent the law allows, the school is not liable for indirect or consequential loss, lost data, '
            . 'lost profit or lost opportunity arising from your use of the site, or from a service being unavailable. '
            . 'Nothing in these terms limits a liability that Philippine law does not allow to be limited, or your '
            . 'rights as a data subject or a consumer.</li></ul>',
    ],
    [
        'title' => 'Third-party services and links',
        'body'  => '<p>The site uses the services named in the Privacy and Cookie Policies (a web host, an e-mail '
            . 'delivery provider, Google Fonts, Cloudflare Turnstile when enabled, YouTube/Vimeo for video lessons, and '
            . 'Jitsi for live classes). Those services have their own terms, and following a link to another website '
            . 'leaves our rules behind — check that site\'s own terms and privacy notice before you use it.</p>',
    ],
    [
        'title' => 'Privacy and cookies',
        'body'  => '<p>What the site stores about you, why, for how long, and who else processes it is set out in the '
            . '<a class="font-semibold text-emerald-700 hover:underline" href="' . e(legal_url('privacy')) . '">Privacy '
            . 'Policy</a>; the one cookie the site sets is set out in the <a class="font-semibold text-emerald-700 '
            . 'hover:underline" href="' . e(legal_url('cookies')) . '">Cookie Policy</a>. Both form part of these terms. '
            . 'By registering you confirm that you have read and accept all three, and we record which version you '
            . 'accepted and when.</p>',
    ],
    [
        'title' => 'If we change these terms',
        'body'  => '<p>The "Last updated" date at the top of this page always shows the current version. A change that '
            . 'affects your rights or the way you use the site is announced here and, where the law requires it, you '
            . 'will be asked to accept the new version the next time you sign in. Continuing to use the site after a '
            . 'change means you accept it; if you do not, stop using the site and ask us to close your account.</p>',
    ],
    [
        'title' => 'Governing law, and how to reach us',
        'body'  => '<p>These terms are governed by the laws of the Republic of the Philippines, and the courts of '
            . 'Catbalogan City, Samar have jurisdiction over any dispute that cannot be settled by talking to us first. '
            . 'If any provision is found unenforceable, the rest stays in force.</p>'
            . '<p class="mt-3">Questions, complaints or a request to close an account: e-mail <a class="font-semibold '
            . 'text-emerald-700 hover:underline" href="mailto:fttccatbalogan@gmail.com?subject=Terms%20question">'
            . 'fttccatbalogan@gmail.com</a>, or write to Felices Technological Training Center, Inc., 6th St. Brgy 12 '
            . 'Patag, Catbalogan City, Samar, 6700 · mobile 0917 320 2508. For personal-data requests, please use the '
            . 'route described in the Privacy Policy so it reaches the right person straight away.</p>',
    ],
];

$page_title = 'Terms & Conditions';
require __DIR__ . '/header.php';
?>
<div class="mx-auto max-w-3xl">
  <p class="lh-kicker reveal">Legal</p>
  <h1 class="reveal mt-3 text-3xl font-bold text-slate-900">Terms &amp; Conditions</h1>
  <p class="reveal mt-2 text-sm text-slate-500">
    Last updated <b><?= e(LEGAL_VERSION) ?></b> · the agreement between you and
    Felices Technological Training Center, Inc.
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
