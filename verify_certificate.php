<?php
/** Verify a certificate — public page: anyone with a Certificate ID can confirm it. */
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/skeleton.php';   /* the loading pane (public page) */

$code = trim((string) ($_GET['code'] ?? ''));
$row = $code !== '' ? certificate_by_code($code) : null;
$checkUrl = app_link('verify_certificate.php');
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verify certificate · LearnHub LMS</title>
  <link rel="icon" type="image/png" href="logo/logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700;800&display=swap"
    rel="stylesheet">
  <style>
    * { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px;
           background: #eef1ee radial-gradient(circle at 20% 0%, rgba(16, 185, 129, .12), transparent 55%);
           font-family: Inter, ui-sans-serif, system-ui, sans-serif; color: #1f2937; }
    .card { width: min(560px, 94vw); background: #fff; border-radius: 18px; padding: 36px 34px;
            box-shadow: 0 18px 44px rgba(15, 23, 42, .14); border: 1px solid #e2e8f0; }
    .brand { display: flex; align-items: center; gap: 10px; justify-content: center; }
    .brand img { height: 44px; }
    .brand span { font: 600 19px Fraunces, Georgia, serif; color: #0f172a; }
    h1 { margin: 22px 0 0; text-align: center; font: 600 24px Fraunces, Georgia, serif; color: #0f172a; }
    p.sub { margin: 6px 0 0; text-align: center; font-size: 13px; color: #64748b; }
    form { display: flex; gap: 8px; margin-top: 20px; }
    input { flex: 1; border: 1px solid #cbd5e1; border-radius: 10px; padding: 11px 14px; font: 500 14px Inter, sans-serif;
            outline: none; text-transform: uppercase; }
    input:focus { border-color: #047857; box-shadow: 0 0 0 3px rgba(16, 185, 129, .18); }
    button { border: 0; cursor: pointer; border-radius: 10px; background: #047857; color: #fff;
             font: 600 14px Inter, sans-serif; padding: 11px 20px; }
    button:hover { background: #059669; }
    .result { margin-top: 20px; border-radius: 14px; padding: 20px 22px; }
    .ok { background: #ecfdf5; border: 1px solid #a7f3d0; }
    .bad { background: #fef2f2; border: 1px solid #fecaca; }
    .badge { font: 700 13px Inter, sans-serif; letter-spacing: .06em; text-transform: uppercase; }
    .ok .badge { color: #047857; } .bad .badge { color: #b91c1c; }
    .holder { margin: 10px 0 0; font: 600 26px Fraunces, Georgia, serif; color: #0f172a; }
    .grid { margin-top: 12px; display: grid; grid-template-columns: auto 1fr; gap: 6px 14px; font-size: 13.5px; }
    .grid dt { color: #64748b; } .grid dd { margin: 0; font-weight: 600; color: #0f172a; }
    .msg { margin: 8px 0 0; font-size: 14px; line-height: 1.6; color: #475569; }
    .foot { margin-top: 22px; text-align: center; font-size: 12px; color: #94a3b8; }
    .foot a { color: #047857; font-weight: 600; text-decoration: none; }
  </style>
  <?php lh_skeleton_css(); ?>
</head>
<body>
  <?php lh_skeleton_body(false); /* one centred card, no app bar */ ?>
  <div class="card">
    <div class="brand"><img src="logo/logo.png" alt="LearnHub"><span>LearnHub <em style="color:#059669;font-style:normal">LMS</em></span></div>
    <h1>Certificate verification</h1>
    <p class="sub">Enter the Certificate ID printed on the certificate (e.g. LH-A1B2C3-D4E5F6).</p>
    <form method="get" action="verify_certificate.php">
      <input name="code" value="<?= e($code) ?>" placeholder="LH-XXXXXX-XXXXXX" autofocus>
      <button type="submit">Verify</button>
    </form>
    <?php if ($code !== '' && $row): ?>
    <div class="result ok">
      <span class="badge">✅ Valid certificate</span>
      <p class="holder"><?= e((string) $row['student_name']) ?></p>
      <dl class="grid">
        <dt>Course</dt><dd><?= e((string) $row['course_title']) ?></dd>
        <dt>Teacher</dt><dd><?= e((string) $row['teacher_name']) ?></dd>
        <dt>Issued</dt><dd><?= e(date('F j, Y', (int) $row['issued_at'])) ?></dd>
        <dt>Certificate ID</dt><dd><?= e((string) $row['code']) ?></dd>
      </dl>
    </div>
    <?php elseif ($code !== ''): ?>
    <div class="result bad">
      <span class="badge">⚠️ Not found</span>
      <p class="msg">No certificate matches <b><?= e($code) ?></b>. Check the ID exactly — it is case-insensitive but
        must include the dashes. If you just received it, allow a moment and try again.</p>
    </div>
    <?php endif; ?>
    <p class="foot">Powered by <a href="<?= e($checkUrl) ?>">LearnHub LMS</a> · Certificates are issued automatically when a student completes 100% of a course.</p>
  </div>
</body>
</html>
