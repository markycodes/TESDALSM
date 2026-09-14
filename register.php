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
                send_welcome_email($newUserId, $courseId); // greeting e-mail to the new student
                header('Location: dashboard.php');
                exit;
            }
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $newUserId;
            set_flash('success', 'Account created! You can now create courses and invite students with enrollment codes.');
            header('Location: dashboard.php');
            exit;
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
        <label class="block text-sm font-medium text-slate-700" for="code">Invitation code <?php if ($role === 'student'): ?><span class="text-rose-500">(required — from your teacher)</span><?php endif; ?></label>
        <input id="code" name="code" value="<?= e($code) ?>" placeholder="e.g. DEMO-7K3P — from your teacher"
               class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
        <p class="mt-1 text-xs text-slate-400">Students: the teacher of the course sends you a unique code — registering with it enrolls you in that exact course. Teachers: leave this blank.</p>
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

<script>
(function () {
  var radios = document.querySelectorAll('input[name="role"]');
  var inp = document.getElementById('code');
  if (!radios.length || !inp) return;
  function sync() {
    var student = document.querySelector('input[name="role"]:checked').value === 'student';
    inp.required = student;
    inp.placeholder = student ? 'e.g. DEMO-7K3P — from your teacher' : 'Leave blank for teacher accounts';
  }
  radios.forEach(function (r) { r.addEventListener('change', sync); });
  sync();
})();
</script>

<?php require __DIR__ . '/footer.php'; ?>
