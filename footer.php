</main>

<footer class="mt-16">
  <div class="mx-auto max-w-6xl px-4">
    <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-[0_1px_2px_rgba(17,33,26,.05),0_18px_40px_-28px_rgba(17,33,26,.25)]">
      <div class="grid gap-8 sm:grid-cols-3">
        <div>
          <div class="flex items-center gap-2 lg:w-[100px] w-[70px]">
            <img src="logo/2ndlogo.png" alt="LearnHub LMS">
          </div>
          <p class="mt-3 text-sm leading-6 text-slate-500">Upload learning materials &amp; video tutorials — built for easy learning.</p>
        </div>
        <div>
          <p class="lh-kicker">Explore</p>
          <ul class="mt-3 space-y-2 text-sm">
            <?php if ($user): ?>
            <li><a class="text-slate-600 transition hover:text-emerald-700" href="dashboard.php">Dashboard</a></li>
            <li><a class="text-slate-600 transition hover:text-emerald-700" href="courses.php">Courses</a></li>
            <li><a class="text-slate-600 transition hover:text-emerald-700" href="enrollments.php">Enrollments</a></li>
            <li><a class="text-slate-600 transition hover:text-emerald-700" href="attendance_day.php">Attendance</a></li>
            <?php else: ?>
            <li><a class="text-slate-600 transition hover:text-emerald-700" href="login.php">Log in</a></li>
            <li><a class="text-slate-600 transition hover:text-emerald-700" href="register.php">Create free account</a></li>
            <li><a class="text-slate-600 transition hover:text-emerald-700" href="index.php">How it works</a></li>
            <?php endif; ?>
          </ul>
        </div>
        <div>
          <p class="lh-kicker">Account</p>
          <ul class="mt-3 space-y-2 text-sm">
            <?php if ($user): ?>
            <li><span class="text-slate-600">Signed in as <b><?= e((string) $user['name']) ?></b></span></li>
            <li><a class="text-slate-600 transition hover:text-rose-600" href="logout.php">Log out</a></li>
            <?php else: ?>
            <li><span class="text-slate-600">Demo: <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">teacher@demo.com</code> · <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">student@demo.com</code></span></li>
            <li><span class="text-slate-600">Password: <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">demo123</code></span></li>
            <?php endif; ?>
          </ul>
        </div>
      </div>
      <p class="mt-8 border-t border-slate-100 pt-4 text-center text-xs text-slate-400">© <?= date('Y') ?> LearnHub LMS · editorial studio edition</p>
    </div>
  </div>
</footer>

<button id="lh-top" class="grid h-11 w-11 place-items-center rounded-full bg-[linear-gradient(135deg,#047857,#059669,#10b981)] text-white shadow-[0_14px_30px_-12px_rgba(5,150,105,0.8)]" title="Back to top" aria-label="Back to top">
  <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" /></svg>
</button>

<script>
(function () {
  var doc = document.documentElement;
  var bar = document.getElementById('lh-progress');
  var top = document.getElementById('lh-top');
  function update() {
    var max = doc.scrollHeight - doc.clientHeight;
    if (bar) { bar.style.width = (max > 0 ? ((doc.scrollTop || document.body.scrollTop) / max) * 100 : 0) + '%'; }
    if (top) { top.classList.toggle('show', (doc.scrollTop || document.body.scrollTop) > 400); }
  }
  window.addEventListener('scroll', update, { passive: true });
  window.addEventListener('resize', update);
  update();
  if (top) { top.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); }); }
})();
</script>

<script src="assets/app.js?v=15"></script>
</body>
</html>
