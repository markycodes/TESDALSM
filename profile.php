<?php
/**
 * My profile — the one page where a person edits what the site says about them:
 * display name, e-mail address, a short "about me", their picture, and the
 * password they log in with.
 *
 * There is deliberately no ?id= on this page: it only ever reads and writes the
 * account sitting in the session (require_login()), so nobody reaches someone
 * else's account by editing the address bar. All three roles use it — a
 * teacher's name shows on their courses, a student's on the class roster.
 *
 * Writes follow the house pattern: POST + CSRF token, then either a flash and a
 * redirect (a change that landed) or this page again with the typed values still
 * in the boxes and the reason beside them (a change that was refused).
 */
require_once __DIR__ . '/lib.php';
$user = require_login();
$userId = (int) $user['id'];
$nav_active = 'profile';
$page_title = 'My profile';

/* What the boxes show: the typed values when a POST was refused, the stored ones
   every other time (same shape as register.php). */
$errors = ['details' => [], 'photo' => [], 'password' => []];
$in = [
    'name'  => (string) $user['name'],
    'email' => (string) $user['email'],
    'bio'   => (string) ($user['bio'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'details') {
        $in['name']  = cut(trim((string) ($_POST['name'] ?? '')), 120);
        $in['email'] = strtolower(cut(trim((string) ($_POST['email'] ?? '')), 190));
        $in['bio']   = profile_one_line((string) ($_POST['bio'] ?? ''), PROFILE_BIO_MAX);
        $emailChanged = $in['email'] !== strtolower((string) $user['email']);

        if (trim($in['name']) === '') $errors['details'][] = 'Please fill in the name people should see.';
        if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL)) $errors['details'][] = 'Please enter a valid e-mail address.';
        /* the e-mail IS the login name, so moving it needs the old key */
        if ($emailChanged && !password_verify((string) ($_POST['current_password'] ?? ''), (string) $user['password'])) {
            $errors['details'][] = 'Changing your e-mail address needs your current password — it is how we know it is really you.';
        }
        if (!$errors['details']) {
            $taken = find_user_by_email($in['email']);
            if ($taken && (int) $taken['id'] !== $userId) {
                $errors['details'][] = 'That e-mail address already belongs to another account — one address per account.';
            }
        }
        if (!$errors['details']) {
            try {
                update_profile_details($userId, $in['name'], $in['email'], $in['bio']);
                set_flash('success', $emailChanged
                    ? 'Profile saved — log in with your new e-mail address from now on.'
                    : 'Profile saved.');
                header('Location: profile.php'); exit;
            } catch (PDOException $e) {
                lms_error_log('profile details save failed: ' . $e->getMessage());
                $errors['details'][] = 'Your details could not be saved because of a database problem. Please try again.';
            }
        }
    } elseif ($action === 'photo' || $action === 'remove_photo') {
        /* both branches write the row FIRST and touch files afterwards, so a
           failed write can never leave the account pointing at a missing picture */
        try {
            $fresh = null;
            if ($action === 'photo') {
                $fresh = save_profile_picture($userId, 'avatar');
                $old = set_profile_picture($userId, (string) $fresh['stored']);
            } else {
                $old = clear_profile_picture($userId);
            }
            if ($old !== '' && $old !== (string) ($fresh['stored'] ?? '')) delete_profile_picture_file($old);
            set_flash('success', $action === 'photo'
                ? 'Profile picture updated — it now shows beside your name (' . format_size((int) $fresh['size']) . ' stored).'
                : 'Profile picture removed — your initial shows again.');
            header('Location: profile.php'); exit;
        } catch (PDOException $e) {
            lms_error_log('profile picture save failed: ' . $e->getMessage());
            if (!empty($fresh['stored'])) delete_profile_picture_file((string) $fresh['stored']);   /* nothing points at it */
            $errors['photo'][] = 'The picture could not be saved because of a database problem. Please try again.';
        } catch (RuntimeException $e) {
            $errors['photo'][] = $e->getMessage();       /* every rejection the upload pipeline explains */
        }
    } elseif ($action === 'password') {
        $cur  = (string) ($_POST['current_password'] ?? '');
        $new  = (string) ($_POST['password'] ?? '');
        $new2 = (string) ($_POST['password2'] ?? '');
        if (!password_verify($cur, (string) $user['password'])) $errors['password'][] = 'That is not your current password.';
        $weak = password_strength_error($new);
        if ($weak !== null) {
            $errors['password'][] = $weak;
        } elseif ($new2 !== $new) {
            $errors['password'][] = 'The two new passwords do not match — please retype them.';
        } elseif ($cur !== '' && password_verify($new, (string) $user['password'])) {
            $errors['password'][] = 'That is the password you already use — please choose a different one.';
        }
        if (!$errors['password']) {
            try {
                update_profile_password($userId, password_hash($new, PASSWORD_DEFAULT));
                session_regenerate_id(true);   /* the id that arrived with the old key is retired */
                set_flash('success', 'Password changed — use it the next time you log in.');
                header('Location: profile.php'); exit;
            } catch (PDOException $e) {
                lms_error_log('profile password change failed: ' . $e->getMessage());
                $errors['password'][] = 'Your password could not be changed because of a database problem. Please try again.';
            }
        }
    }
}

require __DIR__ . '/header.php';
$roleLabel = (($user['role'] ?? '') === 'admin') ? 'Main administrator'
    : ((($user['role'] ?? '') === 'teacher') ? 'Teacher' : 'Student');
$avatarUrl = user_avatar_url($user);
$joined    = (int) ($user['created_at'] ?? 0);

?>

<a href="dashboard.php" class="text-sm font-medium text-slate-500 hover:text-indigo-600">← Dashboard</a>

<div class="mt-3 grid gap-6 lg:grid-cols-[320px_1fr] items-start">

  <!-- ── Who you are: the picture and the name people actually see ─────────── -->
  <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <p class="lh-kicker">Your profile</p>

    <div class="mt-4 flex items-center gap-4">
      <?php if ($avatarUrl !== ''): ?>
        <img id="avatar-preview" src="<?= e($avatarUrl) ?>" alt="Your profile picture"
             class="lh-avatar-img lh-avatar-hero">
      <?php else: ?>
        <img id="avatar-preview" src="" alt="Your profile picture" class="lh-avatar-img lh-avatar-hero" hidden>
        <span id="avatar-initial" class="lh-avatar-initial lh-avatar-hero"
              aria-hidden="true"><?= e(strtoupper(substr((string) $user['name'], 0, 1))) ?></span>
      <?php endif; ?>
      <div class="min-w-0 leading-tight">
        <p class="truncate text-lg font-extrabold tracking-tight text-slate-900"><?= e((string) $user['name']) ?></p>
        <p class="mt-0.5 truncate text-xs text-slate-500"><?= e((string) $user['email']) ?></p>
        <span class="mt-2 inline-block rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-700"><?= e($roleLabel) ?></span>
        <?php if ($joined > 0): ?>
          <p class="mt-2 text-[11px] text-slate-400">Member since <?= e(date('M Y', $joined)) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($in['bio'] !== ''): ?>
      <p class="mt-4 rounded-xl bg-slate-50 p-3 text-sm leading-6 text-slate-600"><?= e($in['bio']) ?></p>
    <?php endif; ?>
    <hr class="mt-5 mb-5 border-slate-100">

    <h2 class="text-sm font-bold text-slate-900">Profile picture</h2>
    <?php if ($errors['photo']): ?>
      <div class="mt-2 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">
        <?php foreach ($errors['photo'] as $er): ?>⚠️ <?= e($er) ?><br><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" action="profile.php" enctype="multipart/form-data" class="mt-3 space-y-3">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="photo">
      <div>
        <label class="block text-sm font-medium text-slate-700" for="avatar-file">
          JPG, PNG, WEBP or GIF · up to <?= e(format_size(AVATAR_MAX_BYTES)) ?></label>
        <input id="avatar-file" name="avatar" type="file" accept="<?= e('.' . implode(',.', AVATAR_EXTS)) ?>"
               class="mt-1 w-full rounded-xl border border-slate-300 p-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
        <p id="avatar-note" class="mt-1 text-xs text-slate-400">Choose a picture and a round frame opens so you can
          put your face in it — drag to move, the slider to zoom. What you keep is stored as a
          <?= (int) AVATAR_SIDE ?> × <?= (int) AVATAR_SIDE ?> square (a phone photo then costs about 60 KB).
          Either way the server checks the file itself.</p>
      </div>
      <button class="w-full rounded-xl bg-indigo-600 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">Upload picture</button>
    </form>

    <?php if ($avatarUrl !== ''): ?>
      <form method="post" action="profile.php" data-confirm="Remove your profile picture? Your initial shows again until you upload a new one."
            class="mt-2">
        <?= csrf_field() ?><input type="hidden" name="action" value="remove_photo">
        <button class="w-full rounded-xl border border-slate-200 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Remove picture</button>
      </form>
    <?php endif; ?>
  </div>


  <div class="space-y-6">

    <!-- ── Account details ─────────────────────────────────────────────────── -->
    <form method="post" action="profile.php" id="details-form" autocomplete="on"
          class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 md:p-8">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="details">
      <p class="lh-kicker">Account</p>
      <h2 class="mt-1 text-2xl font-extrabold tracking-tight text-slate-900">Your details</h2>
      <p class="mt-1 text-sm text-slate-500">Your name is what teachers and classmates see. Your e-mail address is how you log in.</p>
      <?php if ($errors['details']): ?>
        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">
          <?php foreach ($errors['details'] as $er): ?>⚠️ <?= e($er) ?><br><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="mt-5 space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700" for="name">Display name *</label>
          <input id="name" name="name" required maxlength="120" value="<?= e($in['name']) ?>" placeholder="e.g. Maria Garcia"
                 class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700" for="email">E-mail address *</label>
          <input id="email" name="email" type="email" required maxlength="190" value="<?= e($in['email']) ?>"
                 data-original="<?= e(strtolower((string) $user['email'])) ?>" placeholder="you@example.com"
                 class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
        </div>



        <!-- Only appears while the address in the box differs from the stored
             one: moving a login name to a new address needs the old key. -->
        <div id="email-confirm" class="hidden">
          <label class="block text-sm font-medium text-slate-700" for="current_password">Confirm your current password *</label>
          <input id="current_password" name="current_password" type="password" autocomplete="current-password"
                 placeholder="Needed because you changed the e-mail address"
                 class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700" for="bio">About you <span class="text-slate-400">(optional)</span></label>
          <textarea id="bio" name="bio" rows="3" maxlength="<?= (int) PROFILE_BIO_MAX ?>" placeholder="One line about what you teach or study — your teachers see it when they hover your name."
                    class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"><?= e($in['bio']) ?></textarea>
          <p class="mt-1 text-xs text-slate-400"><span id="bio-count">0</span>/<?= (int) PROFILE_BIO_MAX ?> characters — a single line, no links.</p>
        </div>
      </div>

      <button class="mt-5 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">Save changes</button>
      <?= consent_notice('Nothing here leaves this site. Your picture and about line stay inside LearnHub: they show in your own corner of the app, and the teachers of a course you joined see them on a small card when they hover your name on their own roster, attendance sheet or message list. Students are not shown anyone\'s card, there is no public profile page, and there is no list of pictures.') ?>
    </form>

    <!-- ── Password ───────────────────────────────────────────────────────── -->
    <form method="post" action="profile.php" class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 md:p-8">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="password">
      <p class="lh-kicker">Security</p>
      <h2 class="mt-1 text-2xl font-extrabold tracking-tight text-slate-900">Password</h2>
      <p class="mt-1 text-sm text-slate-500">At least 8 characters with an uppercase letter, a lowercase letter and a number. Only a one-way hash is stored — nobody can read it back.</p>
      <?php if ($errors['password']): ?>
        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">
          <?php foreach ($errors['password'] as $er): ?>⚠️ <?= e($er) ?><br><?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="mt-5 space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700" for="current_password2">Current password *</label>
          <input id="current_password2" name="current_password" type="password" required autocomplete="current-password" placeholder="••••••••"
                 class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="block text-sm font-medium text-slate-700" for="password">New password *</label>
            <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" placeholder="Min 8 characters"
                   class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700" for="password2">Repeat new password *</label>
            <input id="password2" name="password2" type="password" required minlength="8" autocomplete="new-password" placeholder="••••••••"
                   class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
          </div>
        </div>
      </div>
      <button class="mt-5 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">Change password</button>
    </form>
  </div>
</div>

<?php /* ── Crop before upload ───────────────────────────────────────────────────
   The picture a person picks is framed here first: the window is the circle every
   avatar on this site is cut into, so what they line up is what people will see.
   The page script below opens it through the shared modal system (assets/app.js:
   the ✕, Cancel, a click past the card and Escape all close it), and the crop is
   plain canvas — nothing to install, nothing to fetch. The drawing lives in
   assets/shell.css as .lh-crop-* because the compiled Tailwind build has none of
   the helpers this needs. */ ?>
<div id="crop-modal" class="modal-backdrop fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4"
     role="dialog" aria-modal="true" aria-labelledby="crop-title">
  <div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-xl">
    <div class="flex items-start justify-between gap-3">
      <div>
        <p class="lh-kicker">Profile picture</p>
        <h3 id="crop-title" class="mt-1 text-base font-extrabold tracking-tight text-slate-900">Centre your face</h3>
      </div>
      <button type="button" data-modal-close aria-label="Cancel and keep your current picture"
              class="rounded-lg px-2 py-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">✕</button>
    </div>
    <p id="crop-hint" class="mt-1 text-xs leading-5 text-slate-500">
      Drag the picture until your face sits inside the circle, then press <b>Use picture</b>.</p>

    <div id="crop-stage" class="lh-crop-stage" tabindex="0"
         aria-label="Crop window: drag the picture, or nudge it with the arrow keys">
      <img id="crop-img" class="lh-crop-img" src="" alt="">
      <div class="lh-crop-win"></div>
      <div class="lh-crop-face"></div>
    </div>

    <div class="mt-4 flex items-center gap-3">
      <label for="crop-zoom" class="text-xs font-semibold text-slate-600">Zoom</label>
      <input id="crop-zoom" type="range" min="1" max="4" step="0.01" value="1" class="flex-1 accent-indigo-600">
      <button id="crop-reset" type="button"
              class="rounded-lg border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50">Reset</button>
    </div>

    <div class="mt-4 flex gap-2">
      <button type="button" data-modal-close
              class="flex-1 rounded-xl border border-slate-200 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</button>
      <button id="crop-apply" type="button"
              class="flex-1 rounded-xl bg-indigo-600 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">Use picture</button>
    </div>
    <p class="mt-3 text-xs leading-5 text-slate-400">The picture is framed and shrunk in your own browser before it
      is sent. Cancelling uploads nothing, and the server re-checks the file either way.</p>
  </div>
</div>
<?php /* ── Page script ────────────────────────────────────────────────────────
   Three small jobs, all of them optional: a character count, the password box
   that only appears when the e-mail address actually changed, and the picture
   pipeline — the browser lets the person frame their face in a round window and
   shrinks that frame to a square before anything is uploaded. That is a courtesy
   to the host (a 12 MB phone photo would otherwise ride straight into a shared
   host's per-request cap) and to the person (they choose where the face sits),
   never a check: the server re-decides everything from the bytes
   (prepare_profile_image() in lib.php). So if canvas or FileList rewriting is
   missing, the file simply uploads as it is and the server squares it off. */ ?>
<script>
(function () {
  var SIDE = <?= (int) AVATAR_SIDE ?>;

  /* the "about you" counter */
  var bio = document.getElementById('bio'), count = document.getElementById('bio-count');
  function recount() { if (bio && count) count.textContent = bio.value.length; }
  if (bio) { bio.addEventListener('input', recount); recount(); }

  /* the confirm box only exists while the typed address differs from the stored one */
  var email = document.getElementById('email'), box = document.getElementById('email-confirm'),
      confirmInput = document.getElementById('current_password');
  function checkEmail() {
    if (!email || !box || !confirmInput) return;
    var changed = email.value.trim().toLowerCase() !== (email.getAttribute('data-original') || '').toLowerCase();
    box.classList.toggle('hidden', !changed);
    confirmInput.required = changed;      /* a hidden box must never block the save */
  }
  if (email) { email.addEventListener('input', checkEmail); email.addEventListener('blur', checkEmail); checkEmail(); }

  function showPreview(src) {
    var prev = document.getElementById('avatar-preview'), initial = document.getElementById('avatar-initial');
    if (prev) { prev.src = src; prev.hidden = false; }
    if (initial) initial.hidden = true;   /* the picture replaces the initial circle */
  }

  /* ── the picture: frame it first, then upload the square you framed ──────────
     Choosing a file only opens the crop dialog. Nothing reaches the server until
     "Use picture" turns the round window into a SIDE × SIDE JPEG and puts it back
     into the input. Every step here is a courtesy, never a check: no canvas, no
     DataTransfer, or a dialog that was cancelled all leave the file for the
     server, which re-decides everything from the bytes (prepare_profile_image()
     in lib.php). */
  var file = document.getElementById('avatar-file'), note = document.getElementById('avatar-note');
  function say(msg) { if (note) note.textContent = msg; }

  var modal = document.getElementById('crop-modal'),
      stage = document.getElementById('crop-stage'),
      pic = document.getElementById('crop-img'),
      zoom = document.getElementById('crop-zoom'),
      hint = document.getElementById('crop-hint'),
      apply = document.getElementById('crop-apply'),
      reset = document.getElementById('crop-reset');
  var shot = null;                    /* { w, h } natural size of the picked picture, null while idle */
  var shotUrl = null;                 /* the blob address the window is showing, kept alive until the crop is drawn */
  var kept = false;                   /* true while "Use picture" is handing the crop over */
  var view = { z: 1, x: 0, y: 0 };    /* zoom, and where the picture's top-left sits in window px */
  var hintAtFirst = hint ? hint.textContent : '';

  function frame() { return (stage && stage.clientWidth) || 288; }   /* the window is a square */
  function step() { return shot ? Math.max(frame() / shot.w, frame() / shot.h) * view.z : 1; }  /* picture px → window px */

  function paint() {
    if (!shot) return;
    var s = frame(), k = step();
    /* the window always stays inside the picture, so there is never a bare edge to crop */
    view.x = Math.min(0, Math.max(s - shot.w * k, view.x));
    view.y = Math.min(0, Math.max(s - shot.h * k, view.y));
    pic.style.width = (shot.w * k) + 'px';
    pic.style.height = (shot.h * k) + 'px';
    pic.style.left = view.x + 'px';
    pic.style.top = view.y + 'px';
  }

  /* Bring the point (fx, fy) of the picture — in picture px — under the face spot of
     the window, at a zoom that makes a subject `span` px tall fill most of it. */
  function aim(fx, fy, span) {
    if (!shot) return;
    var s = frame(), cover = Math.max(s / shot.w, s / shot.h);
    view.z = span > 0 ? Math.min(4, Math.max(1, (s * 0.62) / (span * cover))) : 1;
    view.x = s * 0.5 - fx * step();
    view.y = s * 0.42 - fy * step();      /* a face reads best a little above the middle */
    if (zoom) zoom.value = view.z.toFixed(2);
    paint();
  }

  /* Nothing detected (or nothing to detect with): the upper third of a portrait is
     where a face lives, and zoom 1 keeps the whole height of the picture in frame. */
  function aimDefault() {
    if (!shot) return;
    aim(shot.w / 2, shot.h * 0.38, 0);
    if (hint) hint.textContent = hintAtFirst;
  }

  /* zoom around whatever is under the face spot right now, so the face stays put */
  function zoomTo(z) {
    if (!shot) return;
    var s = frame(), k = step();
    var fx = (s * 0.5 - view.x) / k, fy = (s * 0.42 - view.y) / k;
    view.z = Math.min(parseFloat(zoom ? zoom.max : 0) || 4, Math.max(1, z));
    view.x = s * 0.5 - fx * step();
    view.y = s * 0.42 - fy * step();
    if (zoom) zoom.value = view.z.toFixed(2);
    paint();
  }

  /* where the face is, when this browser can say so — Chrome and Edge ship
     FaceDetector on supported hardware. Silent otherwise: aimDefault() already
     leans the window upwards, which is where portraits keep their faces. */
  function aimFace() {
    if (!shot || typeof window.FaceDetector !== 'function') return;
    try {
      new window.FaceDetector({ maxDetectedFaces: 1, fastMode: true }).detect(pic).then(function (faces) {
        if (!shot || !faces || !faces.length) return;
        var b = faces[0].boundingBox;
        aim(b.left + b.width / 2, b.top + b.height * 0.45, b.height * 1.9);   /* hair and chin included */
        if (hint) hint.textContent = 'Centred on the face this browser found — drag to fine-tune, then press Use picture.';
      }, function () { /* refused or unavailable: the framing above stands */ });
    } catch (e) { /* the same */ }
  }

  function openCrop() {
    kept = false;
    if (typeof openModal === 'function') openModal(modal);
    else { modal.classList.remove('hidden'); modal.classList.add('flex'); }
    setTimeout(function () { if (stage && stage.focus) stage.focus(); }, 40);
  }
  function closeCrop() {
    if (typeof closeModal === 'function') closeModal(modal);
    else { modal.classList.add('hidden'); modal.classList.remove('flex'); }
    /* every way out of the dialog goes through here, once the pixels are read */
    if (shotUrl) { URL.revokeObjectURL(shotUrl); shotUrl = null; }
  }

  if (file && modal && stage && pic) {

    /* choosing a file only loads it into the window — nothing is sent yet */
    file.addEventListener('change', function () {
      var picked = file.files && file.files[0];
      if (!picked) return;
      if (!/^image\//.test(picked.type || '')) {
        say('That file does not look like a picture — the server will refuse it unless it really is one.');
        return;
      }
      var url = URL.createObjectURL(picked);
      if (shotUrl) URL.revokeObjectURL(shotUrl);        /* a second pick replaces the first */
      shotUrl = url;
      openCrop();
      pic.onload = function () {
        shot = { w: pic.naturalWidth || frame(), h: pic.naturalHeight || frame() };
        view.z = 1;
        /* framed only once the dialog is on screen: a hidden window has no width */
        requestAnimationFrame(function () { aimDefault(); aimFace(); });
      };
      pic.onerror = function () {
        if (shotUrl) { URL.revokeObjectURL(shotUrl); shotUrl = null; }
        shot = null;
        closeCrop();
        say('That file could not be read as a picture, so nothing was framed — it uploads as it is.');
      };
      pic.src = url;
    });

    /* dragging the window across the picture — mouse, pen or finger */
    var drag = null;
    stage.addEventListener('pointerdown', function (ev) {
      if (!shot) return;
      drag = { x: ev.clientX, y: ev.clientY };
      if (stage.setPointerCapture) { try { stage.setPointerCapture(ev.pointerId); } catch (e) {} }
      ev.preventDefault();
    });
    stage.addEventListener('pointermove', function (ev) {
      if (!drag || !shot) return;
      view.x += ev.clientX - drag.x;
      view.y += ev.clientY - drag.y;
      drag = { x: ev.clientX, y: ev.clientY };
      paint();
    });
    ['pointerup', 'pointercancel'].forEach(function (ends) {
      stage.addEventListener(ends, function () { drag = null; });
    });

    stage.addEventListener('keydown', function (ev) {          /* the keyboard route */
      var d = { ArrowLeft: [1, 0], ArrowRight: [-1, 0], ArrowUp: [0, 1], ArrowDown: [0, -1] }[ev.key];
      if (!d || !shot) return;
      ev.preventDefault();
      view.x += d[0] * (ev.shiftKey ? 24 : 8);
      view.y += d[1] * (ev.shiftKey ? 24 : 8);
      paint();
    });

    stage.addEventListener('wheel', function (ev) {            /* the wheel is the zoom people reach for */
      if (!shot) return;
      ev.preventDefault();
      zoomTo(view.z * (ev.deltaY < 0 ? 1.12 : 0.89));
    }, { passive: false });

    if (zoom) zoom.addEventListener('input', function () { zoomTo(parseFloat(zoom.value) || 1); });
    if (reset) reset.addEventListener('click', function () { view.z = 1; aimDefault(); });
    window.addEventListener('resize', function () { paint(); });

    /* the crop itself: the window is a SIDE × SIDE piece of the picture */
    if (apply) apply.addEventListener('click', function () {
      if (!shot) { closeCrop(); return; }
      var s = frame(), k = step();
      var sx = -view.x / k, sy = -view.y / k, span = s / k;    /* that window, in picture px */
      var cv = document.createElement('canvas');
      cv.width = SIDE;
      cv.height = SIDE;
      try {
        var ctx = cv.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, SIDE, SIDE);                        /* JPEG carries no alpha */
        ctx.imageSmoothingQuality = 'high';
        ctx.drawImage(pic, sx, sy, span, span, 0, 0, SIDE, SIDE);
      } catch (e) {   /* no canvas in this browser: the file they chose rides along */
        kept = true;
        closeCrop();
        say('This browser could not crop the picture — it uploads as it is, and the server squares it off.');
        return;
      }
      kept = true;                       /* the crop is writing the input: not a cancellation */
      closeCrop();
      showPreview(cv.toDataURL('image/jpeg', 0.92));
      cv.toBlob(function (blob) {
        if (!blob) { say('The picture uploads as it is — the server still checks and resizes it.'); return; }
        try {
          var dt = new DataTransfer();
          dt.items.add(new File([blob], 'profile-picture.jpg', { type: 'image/jpeg' }));
          file.files = dt.files;
          say('Ready: your face framed at ' + SIDE + ' × ' + SIDE + ' · '
              + Math.round(blob.size / 1024) + ' KB — press Upload picture.');
        } catch (e) {   /* the browser refuses the rewritten pick: what was chosen stays */
          say('The crop could not be handed back to the form, so the picture uploads as it is.');
        }
      }, 'image/jpeg', 0.88);
    });

    /* One place catches every way out of the dialog — the ✕, Cancel, a click past the
       card, Escape — because the shared modal system only ever toggles this one
       class. A picture nobody framed must not be left waiting in the form. */
    if (window.MutationObserver) {
      new MutationObserver(function () {
        if (!modal.classList.contains('hidden') || !shot || kept) return;
        shot = null;
        if (shotUrl) { URL.revokeObjectURL(shotUrl); shotUrl = null; }   /* nobody is drawing from it any more */
        try { file.value = ''; } catch (e) {}                  /* nothing half-framed is left to upload */
        pic.removeAttribute('src');
        say('Cropping stopped, so nothing was chosen. Pick the picture again whenever you are ready.');
      }).observe(modal, { attributes: true, attributeFilter: ['class'] });
    }
  }
})();
</script>

<?php require __DIR__ . '/footer.php'; ?>

