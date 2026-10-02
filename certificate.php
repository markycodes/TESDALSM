<?php
/** Certificate — printable e-certificate for a completed course (A4 landscape → Save as PDF).
 *  Auto-issues on the student's first visit after 100% lesson completion. */
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/skeleton.php';   /* the loading pane (printable page) */
$user = require_login();

$courseId = (int) ($_GET['course'] ?? 0);
$course = $courseId > 0 ? course_row($courseId) : null;
if (!$course) {
  header('Location: courses.php');
  exit;
}
if (!is_enrolled_id($courseId, (int) $user['id'])) {
  header('Location: ' . lh_enc_url('course.php?id=' . $courseId));
  exit;
}

$pct = course_progress_pct((int) $user['id'], $courseId);
$cert = $pct >= 100 ? certificate_ensure((int) $user['id'], $courseId) : null;

if (!$cert):
  /* ---- not earned yet: friendly progress screen ---- */
  $page_title = 'Certificate';
  require __DIR__ . '/header.php'; ?>
  <div class="mx-auto max-w-md">
    <div class="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">
      <p class="text-5xl">🎓</p>
      <h1 class="mt-3 text-xl font-bold text-slate-900">Certificate not unlocked yet</h1>
      <p class="mt-2 text-sm leading-6 text-slate-500">Finish <b>all lessons</b> of
        <b><?= e((string) $course['title']) ?></b> to earn your certificate<?php if ($pct >= 0): ?> — you are at
          <b><?= $pct ?>%</b> right now<?php endif; ?>.
      </p>
      <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-200">
        <div class="h-full rounded-full bg-emerald-600 transition-all" style="width: <?= max(0, $pct) ?>%"></div>
      </div>
      <a href="course.php?id=<?= $courseId ?>"
        class="mt-5 inline-block rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">←
        Back to the course</a>
    </div>
  </div>
  <?php require __DIR__ . '/footer.php';
  exit;
endif;

/* ---- earned: render the printable certificate ---- */
$issued = date('F j, Y', (int) $cert['issued_at']);
$verifyUrl = app_link('verify_certificate.php?code=' . urlencode((string) $cert['code']));
?><!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Certificate - <?= e((string) $user['name']) ?> - <?= e((string) $course['title']) ?></title>
  <link rel="icon" type="image/png" href="logo/logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <!-- The lettering is fetched without holding the document hostage. A stylesheet
       is a blocking resource: on a network that answers the font host with
       silence — a filtered school connection, a dead CDN — the certificate would
       sit unpainted and its button unclickable until the request gave up, which
       is a second way "I can't download the PDF" shows up. So it is preloaded for
       speed, named a stylesheet only once it has landed, and until then the plain
       faces in the stacks below carry the sheet. The noscript copy is for the
       visitor whose script never runs: they get the fonts the slow way rather
       than not at all. -->
  <link rel="preload" as="style"
    href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700;800&display=swap"
    onload="this.rel='stylesheet'">
  <noscript>
    <link rel="stylesheet"
      href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700;800&display=swap">
  </noscript>
  <style>
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      background: #eef1ee;
      font-family: Inter, ui-sans-serif, system-ui, sans-serif;
      color: #1f2937;
    }

    .toolbar {
      display: flex;
      justify-content: center;
      gap: 12px;
      padding: 18px 12px;
    }

    .toolbar a,
    .toolbar button {
      border: 0;
      cursor: pointer;
      border-radius: 10px;
      padding: 10px 20px;
      font: 600 14px Inter, sans-serif;
      text-decoration: none;
    }

    .btn-print {
      background: #047857;
      color: #fff;
    }

    .btn-print:hover {
      background: #059669;
    }

    .btn-back {
      background: #fff;
      color: #334155;
      border: 1px solid #cbd5e1 !important;
    }

    /* what to leave the print dialog on, said once on the screen and never on
       paper — the sheet is cut for one landscape page, and a dialog set to
       portrait or to wide margins is the usual reason a printout disagrees */
    .print-hint {
      max-width: 297mm;
      margin: -26px auto 30px;
      padding: 0 14px;
      text-align: center;
      font: 500 12px/1.6 Inter, sans-serif;
      color: #64748b;
    }

    .print-hint b {
      color: #047857;
    }

    .sheet {
      width: 297mm;
      margin: 0 auto 40px;
      aspect-ratio: 297 / 210;
      background: #fffdf8;
      position: relative;
      padding: 14mm;
      box-shadow: 0 24px 60px rgba(15, 23, 42, .25);
      border-radius: 4px;
    }

    .frame {
      position: absolute;
      inset: 7mm;
      border: 3px solid #047857;
    }

    .frame::after {
      content: '';
      position: absolute;
      inset: 2.2mm;
      border: 1px solid #d4af37;
    }

    .inner {
      position: relative;
      isolation: isolate;
      height: 100%;
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      padding: 6mm 16mm;
    }

    .inner img {
      height: 13mm;
    }

    .kicker {
      margin-top: 4mm;
      font: 700 13px Inter, sans-serif;
      letter-spacing: .42em;
      color: #047857;
      text-transform: uppercase;
    }

    .title {
      margin: 3mm 0 0;
      font: 600 40px Fraunces, Georgia, serif;
      color: #0f172a;
    }

    .presented {
      margin: 5mm 0 0;
      font: 500 14px Inter, sans-serif;
      color: #64748b;
    }

    .name {
      margin: 4mm 0 0;
      font: 600 46px Fraunces, Georgia, serif;
      color: #065f46;
      border-bottom: 2px solid #d4af37;
      padding: 0 18px 3mm;
    }

    .for {
      margin: 4mm 0 0;
      font: 500 14px Inter, sans-serif;
      color: #64748b;
    }

    .course {
      margin: 2.5mm 0 0;
      font: 600 27px Fraunces, Georgia, serif;
      color: #0f172a;
    }

    .by {
      margin: 2mm 0 0;
      font: 500 13px Inter, sans-serif;
      color: #64748b;
    }

    .cols {
      margin-top: auto;
      width: 100%;
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
    }

    .col {
      width: 30%;
      font: 500 12px Inter, sans-serif;
      color: #475569;
    }

    .col b {
      display: block;
      font: 600 14px Fraunces, Georgia, serif;
      color: #0f172a;
      margin-top: 2mm;
    }

    .col .line {
      border-top: 1px solid #94a3b8;
      margin-top: 2.5mm;
      padding-top: 2mm;
    }

    .seal {
      width: 24mm;
      height: 24mm;
      border-radius: 50%;
      background: radial-gradient(circle at 32% 28%, #10b981, #047857 68%);
      color: #fff;
      display: grid;
      place-items: center;
      font: 700 20px Fraunces, Georgia, serif;
      position: relative;
    }

    /* The seal's two rings — a white gap and a gold band — are drawn rather than
       cast. A box-shadow is painted on screen and quietly left off paper, which
       is exactly the sort of detail that made the printout disagree with the
       certificate on the monitor. Borders on two circles sized off the seal are
       the same picture in both places: 2.5px of white, then 4px of gold. */
    .seal::before,
    .seal::after {
      content: '';
      position: absolute;
      border-radius: 50%;
      pointer-events: none;
    }

    .seal::before {
      inset: -2.5px;
      border: 2.5px solid #fffdf8;
    }

    .seal::after {
      inset: -6.5px;
      border: 4px solid #d4af37;
    }

    .verify {
      margin-top: 5mm;
      font: 500 10.5px Inter, sans-serif;
      color: #94a3b8;
      word-break: normal;
      overflow-wrap: anywhere;
    }

    .verify b {
      color: #64748b;
    }

    /* Ask for exactly one full landscape sheet with no page margins of its own:
       the sheet draws its own frame 7mm in from the edge, which is past the band
       a printer refuses to reach anyway. The plain size after the keyword says
       the same thing in a way browsers read more literally — one that does not
       understand it simply keeps the keyword above it. */
    @page {
      size: A4 landscape;
      size: 297mm 210mm;
      margin: 0;
    }

    /* The cream paper, the emerald frame and the seal are the document, not
       decoration. Colours have to survive the trip to paper whether or not the
       print dialog was offered "background graphics" — and the property carries
       to everything below, so it is said once, here. */
    html,
    body {
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }

    @media print {

      html,
      body {
        margin: 0;
        padding: 0;
        background: #fff;
      }

      .toolbar,
      .print-hint {
        display: none;
      }

      /* The sheet is not given a size on paper — it is given the page. Whatever
         the dialog settles on (A4 landscape, Letter, margins none or default),
         the sheet fills exactly one of those and nothing of it can run over onto
         a second page: the slack between the course line and the signatures is
         .cols { margin-top: auto }, so that gap is what tightens or stretches.
         The height is said in vh because on paper a vh is a hundredth of the
         page box — the sheet can no longer be taller than the sheet of paper. */
      .sheet {
        width: 100%;
        height: 100vh;
        aspect-ratio: auto;
        min-height: 0;
        margin: 0;
        box-shadow: none;
        border-radius: 0;
        overflow: hidden;
      }
    }

    /* Screen only, and that is the whole point. A phone draws the sheet as a
       plain column with smaller type — but in print, a max-width is measured
       against the PAGE BOX, not the browser window, so a dialog left on portrait
       paper or on default margins would pick this column up too and print
       something that is not the certificate at all: no landscape sheet, a 30px
       title, a 32px name, and ink sized for a handset. */
    @media screen and (max-width: 900px) {
      .sheet {
        aspect-ratio: auto;
        height: auto;
        padding: 30px;
      }

      .title {
        font-size: 30px;
      }

      .name {
        font-size: 32px;
      }
    }

  .fttc-logo {
    margin-top: -7mm;
    border-radius: 50%;
  }

  /* the TESDA mark, centred behind everything. .inner isolates itself above, or
     a negative z-index would slip behind the sheet's own paper and never show. */
  .inner img.tesda-logo {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    height: 55%;
    width: auto;
    opacity: .10;
    z-index: -1;
    pointer-events: none;
  }

  /* the agency letterhead, centred at the very top */
  .head-block {
    font: 500 9px/1.4 Inter, sans-serif;
    color: #334155;
    margin-bottom: 1.5mm;
  }

  .head-block b {
    font-weight: 700;
    color: #0f172a;
  }

  .head-block .hb-school {
    font-size: 10.5px;
    letter-spacing: .03em;
  }

  /* the certificate id — top right corner */
  .cert-id {
    position: absolute;
    top: 0;
    right: 0;
    text-align: right;
    font: 500 9.5px Inter, sans-serif;
    color: #64748b;
    z-index: 2;
  }

  .cert-id b {
    display: block;
    font: 700 13px Fraunces, Georgia, serif;
    color: #0f172a;
    margin-top: 1px;
  }

  /* the two signatures, flanking the seal. The printed name owns a band of its
     own; the scanned ink is laid OVER that band, the way a hand signs across a
     typed name. The ink is position:absolute on purpose: it then costs the column
     no height at all, so both signature lines and both role captions stay on the
     same level however different the two scans are. */
  .sig-mark {
    position: relative;
    min-height: 32px;
    z-index: 1;
  }

  .sig-script {
    position: relative;
    z-index: 1;
    font: italic 600 16px/32px Fraunces, Georgia, serif;
    color: #1e293b;
  }

  .inner .sig-mark img.sig-img {
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    height: 46px;
    width: auto;
    z-index: 3;                 /* above the name, never below it */
    pointer-events: none;
    mix-blend-mode: multiply;   /* where the ink crosses the letters it darkens them */
  }

  /* The two scans are cropped differently — Richardson senior's ink fills almost
     all of its canvas, James's floats in a wide transparent margin. These two
     heights are the boxes that make both read as about the same signature. */
  .inner .sig-president img.sig-img {
    height: 100px;
  }

  .inner .sig-vice-president img.sig-img {
    height: 150px;
  }

  /* On paper the ink is laid down as plain opaque pixels. mix-blend-mode is a
     compositing effect, and a print pipeline is free to drop a compositing group
     it does not carry over — which is how a signature can be perfectly good on
     the monitor and simply not there on the sheet. The blend is a nicety anyway:
     it only darkens the typed letters very slightly where a stroke crosses them,
     and the ink is dark on light paper either way. Screen keeps the blend, paper
     does not ask for one. */
  @media print {
    .inner .sig-mark img.sig-img {
      mix-blend-mode: normal;
    }
  }

  .sig-line {
    border-top: 1px solid #64748b;
    margin-top: 4px;
  }

  .sig-role {
    margin-top: 4px;
    font: 600 11.5px Inter, sans-serif;
    color: #475569;
  }

  /* small screens draw the sheet as a plain column with smaller type, so the ink
     shrinks with it. This block has to come AFTER the rules above: the selectors
     are the same weight, and the last one written is the one that wins. Screen
     only, for the reason spelled out above the other one — paper must keep the
     landscape measurements it was drawn with. */
  @media screen and (max-width: 900px) {
    .inner .sig-mark img.sig-img {
      height: 34px;
    }

    .inner .sig-president img.sig-img {
      height: 34px;
    }

    .inner .sig-vice-president img.sig-img {
      height: 64px;
    }
  }
  </style>
  <?php lh_skeleton_css(); ?>
</head>

<body>
  <?php lh_skeleton_body(false, 'certificate'); /* the certificate sheet, no app bar */ ?>
  <div class="toolbar">
    <button class="btn-print" onclick="lhPrint()">🖨️ Download / Print PDF</button>
    <a class="btn-back" href="course.php?id=<?= $courseId ?>">← Back to course</a>
  </div>
  <p class="print-hint">In the window that opens, set <b>Destination: Save as PDF</b> (or your printer), then
    <b>A4 landscape · Margins: None · Scale: 100%</b> — the sheet is drawn for exactly one page of that shape,
    and its frame already leaves the strip a printer cannot reach.</p>
  <script>
    /* Whether the lettering has finished arriving. Fraunces (the title and the
       name) comes over the network from fonts.googleapis.com, so it is watched
       from the moment the page starts rather than from the click: by the time a
       reader has looked at the sheet and reached for the button it has nearly
       always landed, and the dialog is built from the same render they are
       looking at. */
    var lhFontsIn = !(document.fonts && document.fonts.ready);
    if (!lhFontsIn) {
      document.fonts.ready.then(function () { lhFontsIn = true; }, function () { lhFontsIn = true; });
    }

    /* The dialog has to open INSIDE the click. A print() that waits on a promise
       hands the browser back its event loop first, and a call that is no longer
       answering a click is one a browser is free to swallow — which is what "I
       press Download / Print PDF and nothing happens" usually is. It is also an
       open-ended wait on a school network: if fonts.googleapis.com is slow or
       blocked, the fonts never land and neither does the dialog.
       So: lettering in — print right now, in this click. Still in flight — print
       a short beat later, because a certificate in the plain fallback face is
       still a certificate, and the screen is showing that same fallback at that
       very moment, so paper and screen cannot disagree. Never a longer wait. */
    function lhPrint() {
      if (lhFontsIn) {
        window.print();
        return;
      }
      setTimeout(function () { window.print(); }, 250);
    }
  </script>
  <div class="sheet">
    <div class="frame"></div>
    <div class="inner">
      <div class="cert-id">Certificate ID<b><?= e((string) $cert['code']) ?></b></div>
      <img src="logo/fttc.png" alt="Felices Technological Training Center" class="fttc-logo">
      <div class="head-block">
        <b class="hb-school">FELICES TECHNOLOGICAL TRAINING CENTER, INC.</b><br>
        6th St. Brgy 12 Patag, Catbalogan City, Samar, 6700<br>
        Email address: fttccatbalogan@gmail.com<br>
        Mobile Number: 09173202508
      </div>
      <img class="tesda-logo" src="logo/logo.png" alt="TESDA">
      <div class="kicker">Certificate of Completion</div>
      <h1 class="title">Felices Technological Training Center Inc.</h1>
      <p class="presented">This certificate is proudly presented to</p>
      <div class="name"><?= e((string) $user['name']) ?></div>
      <p class="for">for successfully completing every lesson of the course</p>
      <div class="course"><?= e((string) $course['title']) ?></div>
      <p class="by">a course by
        <?= e((string) ($course['teacher_name'] ?? 'the instructor')) ?><?= e(($course['category'] ?? '') !== '' ? ' · ' . $course['category'] : '') ?>
      </p>
      <div class="cols">
        <div class="col sig sig-president">
          <div class="sig-mark">
            <img class="sig-img" src="signature/sorna-richardson.png" alt="Signature of Dr. Sorna C. Richardson">
            <div class="sig-script">Dr. Sorna C. Richardson</div>
          </div>
          <div class="sig-line"></div>
          <div class="sig-role">School President</div>
        </div>
        <div class="seal">LH</div>
        <div class="col sig sig-vice-president">
          <div class="sig-mark">
            <img class="sig-img" src="signature/james-richardson.png" alt="Signature of Ptr. James T. Richardson">
            <div class="sig-script">Ptr. James T. Richardson</div>
          </div>
          <div class="sig-line"></div>
          <div class="sig-role">School Vice-President</div>
        </div>
      </div>
      <p class="verify">Issued <b><?= e($issued) ?></b> · Authenticate this certificate at <b><?= e($verifyUrl) ?></b> —
        anyone can confirm its validity with the Certificate ID printed at the top right.</p>
    </div>
  </div>
</body>

</html>