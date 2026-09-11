</main>

<footer class="mt-16">
  <div class="mx-auto max-w-6xl px-4">
    <div class="relative overflow-hidden rounded-3xl border border-white/60 bg-white/70 p-8 shadow-[0_20px_50px_-24px_rgba(4,120,87,0.28)] backdrop-blur-xl">
      <div class="pointer-events-none absolute inset-x-0 top-0 h-1 bg-[linear-gradient(90deg,#047857,#059669,#10b981)]"></div>
      <div class="grid gap-8 sm:grid-cols-3">
        <div>
          <div class="flex items-center gap-2">
            <span class="grid h-9 w-9 place-items-center rounded-xl bg-[linear-gradient(135deg,#047857,#059669,#10b981)] text-white shadow-lg">🎓</span>
            <span class="font-extrabold tracking-tight text-slate-900">LearnHub <span class="text-emerald-600">LMS</span></span>
          </div>
          <p class="mt-3 text-sm leading-6 text-slate-500">Upload learning materials &amp; video tutorials — built for easy learning.</p>
        </div>
        <div>
          <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Explore</p>
          <ul class="mt-3 space-y-2 text-sm">
            <?php if ($user): ?>
            <li><a class="text-slate-600 transition hover:text-emerald-600" href="dashboard.php">Dashboard</a></li>
            <li><a class="text-slate-600 transition hover:text-emerald-600" href="courses.php">Courses</a></li>
            <li><a class="text-slate-600 transition hover:text-emerald-600" href="enrollments.php">Enrollments</a></li>
            <li><a class="text-slate-600 transition hover:text-emerald-600" href="attendance_day.php">Attendance</a></li>
            <?php else: ?>
            <li><a class="text-slate-600 transition hover:text-emerald-600" href="login.php">Log in</a></li>
            <li><a class="text-slate-600 transition hover:text-emerald-600" href="register.php">Create free account</a></li>
            <li><a class="text-slate-600 transition hover:text-emerald-600" href="index.php">How it works</a></li>
            <?php endif; ?>
          </ul>
        </div>
        <div>
          <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Account</p>
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
      <p class="mt-8 border-t border-slate-200/70 pt-4 text-center text-xs text-slate-400">© <?= date('Y') ?> LearnHub LMS · v2</p>
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

<script src="assets/app.js?v=11"></script>
</body>
</html>
