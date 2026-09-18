<?php
require_once __DIR__ . '/lib.php';
if (current_user()) { header('Location: dashboard.php'); exit; }

$errors = [];
$name = ''; $email = ''; $role = 'student'; $code = '';
$googleMode = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['mode'] ?? '') === 'google') {
        /* finishing a "Continue with Google" sign-up: name + code are required */
        $googleMode = true;
        $pending = $_SESSION['google_pending'] ?? null;
        if (!$pending) { header('Location: login.php'); exit; }
        $res = google_complete_signup((string) ($_POST['name'] ?? ''), (string) ($_POST['code'] ?? ''), $pending);
        if ($res['ok']) {
            unset($_SESSION['google_pending']);
            if ($res['role'] === 'student') {
                set_flash('success', '🎉 Google account created — you are enrolled in your course. Happy learning!');
                header('Location: ' . lh_enc_url('course.php?id=' . $res['course_id']));
            } else {
                set_flash('success', 'Teacher account created with Google! You can now create courses and invite students.');
                header('Location: dashboard.php');
            }
            exit;
        }
        $errors = $res['errors'];
        $name = trim((string) ($_POST['name'] ?? ''));
        $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
    } else {
    $name  = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $role = ($_POST['role'] ?? '') === 'teacher' ? 'teacher' : 'student';
    $code = strtoupper(trim((string) ($_POST['code'] ?? '')));

    if (strlen($name) < 2) $errors[] = 'Please enter your full name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($role === 'student') {
        if (strlen($code) < 4) {
            $errors[] = 'Students need an invitation code — ask your teacher for one.';
        } else {
            $codeRow = enroll_code_lookup($code);
            if (!$codeRow) $errors[] = 'That invitation code is invalid.';
            elseif (!empty($codeRow['used_by'])) $errors[] = 'That invitation code has already been used — ask your teacher for a fresh one.';
        }
    }
    if ($role === 'teacher') {
        if (strlen($code) < 4) {
            $errors[] = 'Teachers need an access code — ask the main administrator.';
        } else {
            $tRow = teacher_code_lookup($code);
            if (!$tRow) $errors[] = 'That access code is invalid.';
            elseif (!empty($tRow['used_by'])) $errors[] = 'That access code has already been used — ask the administrator for a fresh one.';
        }
    }
    if (!$errors && email_exists($email)) {
        $errors[] = 'That email is already registered — try logging in.';
    }
    if (!$errors) {
        $newUserId = create_user($name, $email, password_hash($password, PASSWORD_DEFAULT), $role);
        if ($role === 'student') {
            $courseId = redeem_enroll_code($code, $newUserId);
            if ($courseId === null) {
                // code was just claimed by someone else — undo the account
                db()->prepare('DELETE FROM users WHERE id = ?')->execute([$newUserId]);
                $errors[] = 'That invitation code was just claimed by someone else — please ask your teacher for a fresh one.';
            } else {
                $c = course_row($courseId);
                session_regenerate_id(true);
                $_SESSION['user_id'] = $newUserId;
                set_flash('success', 'Account created! You are enrolled in "'
                    . (string) ($c['title'] ?? 'the course') . '". Happy learning! 🎉');
                try { send_welcome_email($newUserId, $courseId); } catch (Throwable $e) { /* mail must never break registration */ }
                header('Location: dashboard.php');
                exit;
            }
        } else {
            if (!redeem_teacher_code($code, $newUserId)) {
                // code was just claimed by someone else — undo the account
                db()->prepare('DELETE FROM users WHERE id = ?')->execute([$newUserId]);
                $errors[] = 'That access code was just claimed by someone else — ask the administrator for a fresh one.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $newUserId;
                set_flash('success', 'Teacher account created! You can now create courses and invite students with enrollment codes.');
                try { send_welcome_email($newUserId); } catch (Throwable $e) { /* mail must never break registration */ } // greeting e-mail to the new teacher too
                header('Location: dashboard.php');
                exit;
            }
        }
    }
    }
}
$page_title = 'Create account';
require __DIR__ . '/header.php';
?>

<?php
$gPending = $_SESSION['google_pending'] ?? null;
$gName    = $name !== '' ? $name : (string) ($gPending['name'] ?? '');
$gEmail   = (string) ($gPending['email'] ?? '');
?>
<?php if ($googleMode): ?>
<div class="mx-auto max-w-md">
  <div class="rounded-2xl bg-white p-8 shadow-sm ring-1 ring-slate-200">
    <h1 class="text-2xl font-bold text-slate-900">Finish signing up</h1>
    <p class="mt-1 text-sm text-slate-500">Your Google account was verified. Two last things and you're in:</p>
    <div class="mt-3 flex items-center gap-2 rounded-xl border border-sky-200 bg-sky-50 p-3 text-sm text-sky-800">
      <span>📧</span><span class="min-w-0 truncate font-semibold"><?= e($gEmail ?: 'your Google e-mail') ?></span>
      <span class="ml-auto shrink-0 rounded-lg bg-sky-100 px-2 py-0.5 text-xs font-semibold">via Google</span>
    </div>
    <?php if ($errors): ?>
      <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">
        <?php foreach ($errors as $er): ?>⚠️ <?= e($er) ?><br><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <form method="post" class="mt-6 space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="mode" value="google">
      <div>
        <label class="block text-sm font-medium text-slate-700" for="name">Full name <span class="text-rose-500">(required)</span></label>
        <input id="name" name="name" required value="<?= e($gName) ?>" placeholder="e.g. Maria Garcia"
               class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700" for="code">Enrollment code <span class="text-rose-500">(required)</span></label>
        <input id="code" name="code" required value="<?= e($code) ?>" placeholder="e.g. DEMO-7K3P"
               class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
        <p class="mt-1 text-xs text-slate-400"><b>Students:</b> the invitation code from your teacher enrolls you in that exact course.
           <b>Teachers:</b> the access code from the main administrator creates a teacher account.</p>
      </div>
      <button class="w-full rounded-xl bg-indigo-600 py-2.5 font-semibold text-white shadow-sm hover:bg-indigo-700">Create my account</button>
    </form>
    <p class="mt-6 text-center text-sm text-slate-500">Prefer a password? <a href="register.php" class="font-semibold text-indigo-600 hover:underline">Register with e-mail instead</a></p>
  </div>
</div>
<?php else: ?>
<div class="mx-auto max-w-md">
  <div class="rounded-2xl bg-white p-8 shadow-sm ring-1 ring-slate-200">
    <h1 class="text-2xl font-bold text-slate-900">Create your account</h1>
    <p class="mt-1 text-sm text-slate-500">Join LearnHub — it takes less than a minute.</p>
    <?php if ($errors): ?>
      <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">
        <?php foreach ($errors as $er): ?>⚠️ <?= e($er) ?><br><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if (google_configured()): ?>
    <a href="google_login.php" class="mt-6 flex w-full items-center justify-center gap-3 rounded-xl border border-slate-300 bg-white py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
      <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
      Continue with Google
    </a>
    <div class="mt-4 flex items-center gap-3 text-xs text-slate-400">
      <span class="h-px flex-1 bg-slate-200"></span>or register with e-mail<span class="h-px flex-1 bg-slate-200"></span>
    </div>
    <?php endif; ?>
    <form method="post" class="mt-6 space-y-4">
      <?= csrf_field() ?>
      <div>
        <label class="block text-sm font-medium text-slate-700" for="name">Full name</label>
        <input id="name" name="name" required value="<?= e($name) ?>" placeholder="e.g. Maria Garcia"
               class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700" for="email">Email</label>
        <input id="email" name="email" type="email" required value="<?= e($email) ?>" placeholder="you@example.com"
               class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
      </div>
      <fieldset>
        <legend class="block text-sm font-medium text-slate-700">I am a…</legend>
        <div class="mt-2 grid grid-cols-2 gap-3">
          <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-300 p-3 has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50">
            <input type="radio" name="role" value="student" <?= $role === 'student' ? 'checked' : '' ?> class="accent-indigo-600">
            <span class="text-sm"><b>👨‍🎓 Student</b><span class="block text-xs text-slate-500">Enroll &amp; learn</span></span>
          </label>
          <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-300 p-3 has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50">
            <input type="radio" name="role" value="teacher" <?= $role === 'teacher' ? 'checked' : '' ?> class="accent-indigo-600">
            <span class="text-sm"><b>👩‍🏫 Teacher</b><span class="block text-xs text-slate-500">Create &amp; upload</span></span>
          </label>
        </div>
      </fieldset>
      <div>
        <label class="block text-sm font-medium text-slate-700" for="code">Access code <span class="text-rose-500">(required)</span></label>
        <input id="code" name="code" value="<?= e($code) ?>" placeholder="e.g. DEMO-7K3P"
               class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
        <p class="mt-1 text-xs text-slate-400">Students: your teacher sends you a unique code — registering with it enrolls you in that exact course. Teachers: use the access code from the main administrator.</p>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700" for="password">Password</label>
        <input id="password" name="password" type="password" required minlength="6" placeholder="At least 6 characters"
               class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
      </div>
      <button class="w-full rounded-xl bg-indigo-600 py-2.5 font-semibold text-white shadow-sm hover:bg-indigo-700">Create account</button>
    </form>
    <p class="mt-6 text-center text-sm text-slate-500">Already have an account? <a href="login.php" class="font-semibold text-indigo-600 hover:underline">Log in</a></p>
  </div>
</div>
<?php endif; ?>

<script>
(function () {
  var radios = document.querySelectorAll('input[name="role"]');
  var inp = document.getElementById('code');
  if (!radios.length || !inp) return;
  function sync() {
    var student = document.querySelector('input[name="role"]:checked').value === 'student';
    inp.required = true;
    inp.placeholder = student ? 'e.g. DEMO-7K3P — from your teacher' : 'e.g. T-1A2B3C — from the main administrator';
  }
  radios.forEach(function (r) { r.addEventListener('change', sync); });
  sync();
})();
</script>

<?php require __DIR__ . '/footer.php'; ?>
