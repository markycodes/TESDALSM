</main>

<footer class="lh-footer mt-16">
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
      <p class="mt-8 border-t border-slate-100 pt-4 text-center text-xs text-slate-400">© <?= date('Y') ?> LearnHub LMS · v.1.0.0</p>
    </div>
  </div>
</footer>

<button id="lh-top" class="lh-tip lh-tip-up lh-tip-end grid h-11 w-11 place-items-center rounded-full bg-[linear-gradient(135deg,#047857,#059669,#10b981)] text-white shadow-[0_14px_30px_-12px_rgba(5,150,105,0.8)]" data-tip="Back to top" aria-label="Back to top">
  <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" /></svg>
</button>

<script>
(function () {
  var doc = document.documentElement;
  var body = document.body;
  var bar = document.getElementById('lh-progress');
  var top = document.getElementById('lh-top');
  /* room the bottom bar needs — measured below, used as the reveal threshold */
  var footGap = 0;
  /* On desktop the app shell scrolls inside <main>, not the window, so the
     progress bar / back-to-top / footer reveal all read the real scroller. */
  function scroller() {
    var m = document.querySelector('main.lh-main');
    if (m && (m.scrollHeight - m.clientHeight) > 4) { return m; }
    return null;
  }
  /* The footer belongs at the end of the scrolling content: move it inside
     <main> so it can never overlap the last card — it simply appears when the
     reader reaches the bottom of the page. */
  (function placeFooter() {
    var f = document.querySelector('.lh-footer');
    var m = document.querySelector('main.lh-main');
    if (f && m && !m.contains(f)) {
      m.appendChild(f);
      f.classList.add('lh-footer-inline');
    }
  })();
  /* The footer bar is fixed to the bottom, so it takes no room in the flow;
     reserve exactly its height at the end of the content. The room is handed
     over as a CSS variable read by an !important rule in the theme — an inline
     style here would lose to that rule. */
  (function reserveFooterSpace() {
    var f = document.querySelector('.lh-footer.lh-footer-inline');
    var m = document.querySelector('main.lh-main');
    if (!f || !m) { return; }
    function sync() {
      if (!window.matchMedia('(min-width:1024px)').matches) {
        /* small screens keep the footer in the normal flow */
        footGap = 0;
        doc.style.removeProperty('--lh-foot-gap');
        return;
      }
      var barH = f.offsetHeight || 0;
      /* Reserve the bar's height plus a 40px cushion, and reveal the bar only
         when 40px or less of scrolling remains. The bar lands inside that
         cushion, so it can never overlap the content above it. */
      footGap = 40;
      doc.style.setProperty('--lh-foot-gap', (barH + 40) + 'px');
    }
    sync();
    window.addEventListener('resize', sync);
    window.addEventListener('load', sync);
    setTimeout(sync, 350);
    setTimeout(sync, 1200);
    if (window.ResizeObserver) {
      try { new ResizeObserver(sync).observe(f); } catch (e) { /* ignore */ }
    }
  })();
  function update() {
    var s = scroller(), max, pos;
    if (s) {
      max = s.scrollHeight - s.clientHeight;
      pos = s.scrollTop;
    } else {
      max = doc.scrollHeight - doc.clientHeight;
      pos = window.pageYOffset || doc.scrollTop || 0;
    }
    if (bar) { bar.style.width = (max > 0 ? (pos / max) * 100 : 100) + '%'; }
    if (top) { top.classList.toggle('show', pos > 400); }
    /* Reveal the bottom bar only once the whole reserved gap is in view, so it
       can never sit on top of the last cards. Short pages (nothing to scroll)
       always show it. */
    var margin = footGap > 0 ? footGap : 72;
    if (body) { body.classList.toggle('lh-at-bottom', max <= 0 || (max - pos) <= margin); }
  }
  window.addEventListener('scroll', update, { passive: true });
  document.addEventListener('scroll', update, { passive: true, capture: true });
  window.addEventListener('resize', update);
  update();
  if (top) {
    top.addEventListener('click', function () {
      var s = scroller();
      if (s) { s.scrollTo({ top: 0, behavior: 'smooth' }); }
      else { window.scrollTo({ top: 0, behavior: 'smooth' }); }
    });
  }
})();
</script>

<script src="assets/app.js?v=21"></script>
<script>
/* Self-healing fallback for the invite-link buttons. If the browser served a
   stale (or the host a missing) app.js — i.e. window.lhWireShare never appeared
   — wire "🔗 Copy invite link" / "📤 Share…" right here, so the button always
   works regardless of what app.js the visitor has. */
(function () {
  if (window.lhWireShare) return;
  function fallbackCopy(text) {
    try {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.setAttribute('readonly', '');
      ta.style.position = 'fixed';
      ta.style.top = '-1000px';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      ta.setSelectionRange(0, ta.value.length);
      var ok = document.execCommand('copy');
      ta.remove();
      if (ok) return true;
    } catch (e) { /* fall through to the manual route */ }
    window.prompt('Copy this link (long-press to select):', text);
    return false;
  }
  function wire(root) {
    var scope = root || document;
    scope.querySelectorAll('[data-lh-copy]').forEach(function (btn) {
      if (btn.dataset.lhWired) return;
      btn.dataset.lhWired = '1';
      btn.addEventListener('click', function () {
        var text = btn.dataset.lhCopy || '';
        var label = btn.dataset.lhLabel || btn.textContent;
        function done(ok) {
          btn.textContent = ok ? 'copied ✓' : 'select & copy';
          setTimeout(function () { btn.textContent = label; }, 2200);
        }
        if (navigator.clipboard && navigator.clipboard.writeText && window.isSecureContext) {
          navigator.clipboard.writeText(text).then(function () { done(true); }, function () { done(fallbackCopy(text)); });
        } else {
          done(fallbackCopy(text));
        }
      });
    });
    scope.querySelectorAll('[data-lh-share]').forEach(function (btn) {
      if (btn.dataset.lhWired) return;
      btn.dataset.lhWired = '1';
      if (!navigator.share) { btn.style.display = 'none'; return; }
      btn.addEventListener('click', function () {
        navigator.share({ title: btn.dataset.lhShareTitle || document.title, text: btn.dataset.lhShareText || '', url: btn.dataset.lhShare }).catch(function () { });
      });
    });
  }
  window.lhWireShare = wire;
  wire();
})();
</script>

</body>
</html>
