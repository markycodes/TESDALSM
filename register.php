<?php
require_once __DIR__ . '/lib.php';
if (current_user()) { header('Location: dashboard.php'); exit; }

$errors = [];
$name = ''; $email = ''; $role = 'student'; $code = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name  = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $password2 = (string) ($_POST['password2'] ?? '');
    $role = ($_POST['role'] ?? '') === 'teacher' ? 'teacher' : 'student';
    $code = strtoupper(trim((string) ($_POST['code'] ?? '')));

    if (strlen($name) < 2) $errors[] = 'Please enter your full name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    $pwError = password_strength_error($password);
    if ($pwError !== null) {
        $errors[] = $pwError;
    } elseif ($password2 === '') {
        $errors[] = 'Please confirm your password.';
    } elseif ($password2 !== $password) {
        $errors[] = 'The passwords do not match — please retype them.';
    }
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
$page_title = 'Create account';
require __DIR__ . '/header.php';
?>

<div class="mx-auto max-w-md">
  <div class="rounded-2xl bg-white p-8 shadow-sm ring-1 ring-slate-200">
    <h1 class="text-2xl font-bold text-slate-900">Create your account</h1>
    <p class="mt-1 text-sm text-slate-500">Join LearnHub — it takes less than a minute.</p>
    <?php if ($errors): ?>
      <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">
        <?php foreach ($errors as $er): ?>⚠️ <?= e($er) ?><br><?php endforeach; ?>
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
        <input id="password" name="password" type="password" required minlength="8" placeholder="Min 8 characters — UPPER + lowercase + number"
               class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
        <p class="mt-1 text-xs text-slate-400">Strong passwords: at least 8 characters with an uppercase letter, a lowercase letter and a number.</p>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700" for="password2">Confirm password</label>
        <input id="password2" name="password2" type="password" required minlength="8" placeholder="Retype your password"
               class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
        <p id="password2-note" class="mt-1 text-xs text-slate-400">Type the same password again to be sure.</p>
      </div>
      <button class="w-full rounded-xl bg-indigo-600 py-2.5 font-semibold text-white shadow-sm hover:bg-indigo-700">Create account</button>
    </form>
    <p class="mt-6 text-center text-sm text-slate-500">Already have an account? <a href="login.php" class="font-semibold text-indigo-600 hover:underline">Log in</a></p>
  </div>
</div>

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

/* live "do the passwords match?" feedback — the server re-checks this anyway */
(function () {
  var p1 = document.getElementById('password');
  var p2 = document.getElementById('password2');
  var note = document.getElementById('password2-note');
  if (!p1 || !p2 || !note) return;
  var base = note.textContent;
  function check() {
    if (!p2.value) {
      note.textContent = base;
      note.className = 'mt-1 text-xs text-slate-400';
      p2.setCustomValidity('');
      return;
    }
    var match = p1.value === p2.value;
    note.textContent = match ? '✓ Passwords match' : '✗ Passwords do not match yet';
    note.className = 'mt-1 text-xs ' + (match ? 'text-emerald-600' : 'text-rose-500');
    p2.setCustomValidity(match ? '' : 'Passwords do not match.');
  }
  p1.addEventListener('input', check);
  p2.addEventListener('input', check);
  var form = p2.closest('form');
  if (form) form.addEventListener('submit', check);
  check();
})();
</script>

<?php require __DIR__ . '/footer.php'; ?>
