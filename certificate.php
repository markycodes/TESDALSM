<?php
/** Certificate — printable e-certificate for a completed course (A4 landscape → Save as PDF).
 *  Auto-issues on the student's first visit after 100% lesson completion. */
require_once __DIR__ . '/lib.php';
$user = require_login();

$courseId = (int) ($_GET['course'] ?? 0);
$course = $courseId > 0 ? course_row($courseId) : null;
if (!$course) { header('Location: courses.php'); exit; }
if (!is_enrolled_id($courseId, (int) $user['id'])) { header('Location: course.php?id=' . $courseId); exit; }

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
      <b><?= $pct ?>%</b> right now<?php endif; ?>.</p>
    <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-200">
      <div class="h-full rounded-full bg-emerald-600 transition-all" style="width: <?= max(0, $pct) ?>%"></div>
    </div>
    <a href="course.php?id=<?= $courseId ?>"
       class="mt-5 inline-block rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">← Back to the course</a>
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
  <title>Certificate · <?= e((string) $course['title']) ?> · LearnHub LMS</title>
  <link rel="icon" type="image/png" href="logo/logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700;800&display=swap"
    rel="stylesheet">
  <style>
    * { box-sizing: border-box; }
    body { margin: 0; background: #eef1ee; font-family: Inter, ui-sans-serif, system-ui, sans-serif; color: #1f2937; }
    .toolbar { display: flex; justify-content: center; gap: 12px; padding: 18px 12px; }
    .toolbar a, .toolbar button {
      border: 0; cursor: pointer; border-radius: 10px; padding: 10px 20px; font: 600 14px Inter, sans-serif;
      text-decoration: none; }
    .btn-print { background: #047857; color: #fff; }
    .btn-print:hover { background: #059669; }
    .btn-back { background: #fff; color: #334155; border: 1px solid #cbd5e1 !important; }
    .sheet {
      width: 297mm; max-width: 100%; margin: 0 auto 40px; aspect-ratio: 297 / 210;
      background: #fffdf8; position: relative; padding: 14mm;
      box-shadow: 0 24px 60px rgba(15, 23, 42, .25); border-radius: 4px; }
    .frame { position: absolute; inset: 7mm; border: 3px solid #047857; }
    .frame::after { content: ''; position: absolute; inset: 2.2mm; border: 1px solid #d4af37; }
    .inner { position: relative; height: 100%; display: flex; flex-direction: column; align-items: center;
             text-align: center; padding: 10mm 16mm; }
    .inner img { height: 17mm; }
    .kicker { margin-top: 6mm; font: 700 13px Inter, sans-serif; letter-spacing: .42em; color: #047857;
              text-transform: uppercase; }
    .title { margin: 3mm 0 0; font: 600 44px Fraunces, Georgia, serif; color: #0f172a; }
    .presented { margin: 7mm 0 0; font: 500 14px Inter, sans-serif; color: #64748b; }
    .name { margin: 5mm 0 0; font: 600 46px Fraunces, Georgia, serif; color: #065f46;
            border-bottom: 2px solid #d4af37; padding: 0 18px 3mm; }
    .for { margin: 6mm 0 0; font: 500 14px Inter, sans-serif; color: #64748b; }
    .course { margin: 2.5mm 0 0; font: 600 27px Fraunces, Georgia, serif; color: #0f172a; }
    .by { margin: 3mm 0 0; font: 500 13px Inter, sans-serif; color: #64748b; }
    .cols { margin-top: auto; width: 100%; display: flex; justify-content: space-between; align-items: flex-end; }
    .col { width: 30%; font: 500 12px Inter, sans-serif; color: #475569; }
    .col b { display: block; font: 600 14px Fraunces, Georgia, serif; color: #0f172a; margin-top: 2mm; }
    .col .line { border-top: 1px solid #94a3b8; margin-top: 2.5mm; padding-top: 2mm; }
    .seal { width: 24mm; height: 24mm; border-radius: 50%; background: radial-gradient(circle at 32% 28%, #10b981, #047857 68%);
            color: #fff; display: grid; place-items: center; font: 700 20px Fraunces, Georgia, serif;
            box-shadow: 0 0 0 2.5px #fffdf8, 0 0 0 4px #d4af37; }
    .verify { margin-top: 5mm; font: 500 10.5px Inter, sans-serif; color: #94a3b8; word-break: break-all; }
    .verify b { color: #64748b; }
    @page { size: A4 landscape; margin: 0; }
    @media print {
      body { background: #fff; }
      .toolbar { display: none; }
      .sheet { margin: 0; box-shadow: none; border-radius: 0; width: 100%; }
    }
    @media (max-width: 900px) { .sheet { aspect-ratio: auto; height: auto; padding: 24px; } .title { font-size: 30px; } .name { font-size: 32px; } }
  </style>
</head>
<body>
  <div class="toolbar">
    <button class="btn-print" onclick="window.print()">🖨️ Download / Print PDF</button>
    <a class="btn-back" href="course.php?id=<?= $courseId ?>">← Back to course</a>
  </div>
  <div class="sheet">
    <div class="frame"></div>
    <div class="inner">
      <img src="logo/logo.png" alt="LearnHub LMS">
      <div class="kicker">Certificate of Completion</div>
      <h1 class="title">LearnHub LMS</h1>
      <p class="presented">This certificate is proudly presented to</p>
      <div class="name"><?= e((string) $user['name']) ?></div>
      <p class="for">for successfully completing every lesson of the course</p>
      <div class="course"><?= e((string) $course['title']) ?></div>
      <p class="by">a course by <?= e((string) ($course['teacher_name'] ?? 'the instructor')) ?><?= e(($course['category'] ?? '') !== '' ? ' · ' . $course['category'] : '') ?></p>
      <div class="cols">
        <div class="col">Date issued<b><?= e($issued) ?></b>
          <div class="line">Issued <?= e(date('Y', (int) $cert['issued_at'])) ?></div>
        </div>
        <div class="seal">LH</div>
        <div class="col">Certificate ID<b><?= e((string) $cert['code']) ?></b>
          <div class="line">Verified at <?= e(preg_replace('#^https?://#', '', $verifyUrl)) ?></div>
        </div>
      </div>
      <p class="verify">Authenticate this certificate at <b><?= e($verifyUrl) ?></b> — anyone can confirm its validity with the Certificate ID.</p>
    </div>
  </div>
</body>
</html>
