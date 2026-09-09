<?php
require_once __DIR__ . '/lib.php';
if (current_user()) { header('Location: dashboard.php'); exit; }

$errors = [];
$name = ''; $email = ''; $role = 'student';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name  = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $role = ($_POST['role'] ?? '') === 'teacher' ? 'teacher' : 'student';

    if (strlen($name) < 2) $errors[] = 'Please enter your full name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if (!$errors && email_exists($email)) {
        $errors[] = 'That email is already registered — try logging in.';
    }
    if (!$errors) {
        $newUserId = create_user($name, $email, password_hash($password, PASSWORD_DEFAULT), $role);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $newUserId;
        set_flash('success', $role === 'teacher'
            ? 'Account created! You can now create courses and upload lessons.'
            : 'Account created! Browse courses and enroll to start learning.');
        header('Location: dashboard.php');
        exit;
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
        <label class="block text-sm font-medium text-slate-700" for="password">Password</label>
        <input id="password" name="password" type="password" required minlength="6" placeholder="At least 6 characters"
               class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
      </div>
      <button class="w-full rounded-xl bg-indigo-600 py-2.5 font-semibold text-white shadow-sm hover:bg-indigo-700">Create account</button>
    </form>
    <p class="mt-6 text-center text-sm text-slate-500">Already have an account? <a href="login.php" class="font-semibold text-indigo-600 hover:underline">Log in</a></p>
  </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
