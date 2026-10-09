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
            <li><a class="text-slate-600 transition hover:text-emerald-700" href="schedule.php">Schedule</a></li>
            <?php else: ?>
            <li><a class="text-slate-600 transition hover:text-emerald-700" href="login.php">Log in</a></li>
            <li><a class="text-slate-600 transition hover:text-emerald-700" href="register.php">Create free account</a></li>
            <?php endif; ?>
          </ul>
        </div>
        <div>
          <p class="lh-kicker">Legal</p>
          <ul class="mt-3 space-y-2 text-sm">
            <?php /* one loop, one source of truth: LEGAL_PAGES in lib.php */ ?>
            <?php foreach (LEGAL_PAGES as $lh_legal): ?>
            <li><a class="text-slate-600 transition hover:text-emerald-700" href="<?= e((string) $lh_legal['file']) ?>"><?= e((string) $lh_legal['label']) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
      <?php /* The public pages, for every visitor — the SAME list the top bar
               reads (PUBLIC_PAGES in lib.php), so a re-labelled page is one
               edit. Signed-in users get them here too, next to the app links
               listed above. */ ?>
      <nav class="mt-6 flex flex-wrap items-center justify-center gap-3" aria-label="Public pages">
        <?php foreach (PUBLIC_PAGES as $lh_pub): ?>
        <a class="text-sm font-medium text-slate-600 transition hover:text-emerald-700"
          href="<?= e((string) $lh_pub['file']) ?>"><?= e((string) $lh_pub['label']) ?></a>
        <?php endforeach; ?>
      </nav>

      <p class="mt-8 border-t border-slate-100 pt-4 text-center text-xs text-slate-400">© <?= date('Y') ?> LearnHub LMS · v.1.0.0</p>
      <div class="mt-2 flex flex-col items-center gap-2 text-center" data-developer-credit>
        <p class="text-xs text-slate-400">Developed by Mark allan Latosa Mingao</p>
        <nav class="flex flex-row items-center justify-center gap-4" aria-label="Developer social accounts">
          <a href="https://www.facebook.com/markallanlatosa.mingao.3" target="_blank" rel="noopener noreferrer"
             aria-label="Facebook" title="Facebook" class="text-slate-500 transition hover:text-blue-700">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13.4 21v-8h2.7l.4-3.1h-3.1v-2c0-.9.3-1.5 1.6-1.5h1.7V3.6c-.3 0-1.3-.1-2.5-.1-2.5 0-4.2 1.5-4.2 4.3v2.1H7.2V13H10v8h3.4Z"/></svg>
          </a>
          <a href="https://www.instagram.com/just_m4rklando" target="_blank" rel="noopener noreferrer"
             aria-label="Instagram" title="Instagram" class="text-slate-500 transition hover:text-pink-700">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
              <rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.7" cy="6.6" r=".8" fill="currentColor" stroke="none"/>
            </svg>
          </a>
          <a href="https://wa.me/639302348700" target="_blank" rel="noopener noreferrer"
             aria-label="WhatsApp" title="WhatsApp" class="text-slate-500 transition hover:text-emerald-700">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
              <path d="M12 2.5a9.4 9.4 0 0 0-8 14.3L2.8 21l4.3-1.1A9.5 9.5 0 1 0 12 2.5Zm0 17.1a7.6 7.6 0 0 1-3.9-1.1l-.3-.2-2.5.7.7-2.4-.2-.4A7.6 7.6 0 1 1 12 19.6Zm4.2-5.7c-.2-.1-1.3-.7-1.5-.7s-.4-.1-.5.1-.6.7-.7.8-.3.2-.5.1a6.2 6.2 0 0 1-1.8-1.1 6.7 6.7 0 0 1-1.2-1.5c-.1-.2 0-.3.1-.4l.4-.5c.1-.1.2-.3.2-.4s0-.3 0-.4-.5-1.2-.7-1.6-.4-.3-.5-.3h-.4c-.2 0-.4.1-.6.3s-.8.8-.8 1.9.8 2.2.9 2.3a9.1 9.1 0 0 0 3.5 3.1c.5.2.8.3 1.1.4.5.2 1 .1 1.4.1.4-.1 1.3-.5 1.5-1s.2-.9.2-1-.1-.1-.3-.2Z"/>
            </svg>
          </a>
          <a href="https://www.linkedin.com/in/mark-allan-latosa-mingao-842b2a36a" target="_blank" rel="noopener noreferrer"
             aria-label="LinkedIn" title="LinkedIn" class="text-slate-500 transition hover:text-sky-700">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M5.2 8.3a1.9 1.9 0 1 0 0-3.8 1.9 1.9 0 0 0 0 3.8ZM3.6 9.7h3.2v10.7H3.6V9.7Zm5.2 0h3.1v1.5h.1a3.4 3.4 0 0 1 3.1-1.7c3.3 0 3.9 2.2 3.9 5v5.9h-3.2v-5.2c0-1.2 0-2.8-1.7-2.8s-2 1.3-2 2.7v5.3H8.8V9.7Z"/></svg>
          </a>
        </nav>
      </div>
    </div>
  </div>
</footer>

<?php /* One-line cookie notice. The app sets a single strictly necessary session
         cookie and nothing else, so this points at the full inventory in
         cookie_policy.php instead of throwing up a consent wall — and it can be
         dismissed for good. Dismissing stores one localStorage key
         (lh-cookie-notice), which that policy lists like every other key. */ ?>
<div id="lh-cookie-note" role="region" aria-label="Cookie notice" hidden>
  <p class="text-xs leading-5">
    We set <b>one cookie</b> — the login session — and nothing that tracks or profiles you.
    <a class="font-semibold hover:underline" href="<?= e(legal_url('cookies')) ?>">See the Cookie Policy</a>.
  </p>
  <button type="button" id="lh-cookie-note-ok"
    class="shrink-0 rounded-lg border px-3 py-1.5 text-xs font-semibold transition">Got it</button>
</div>
<style>
  /* Colours read the design-system tokens, with today's light palette as the
     fallback — the interface dark.css documents. The note then wears the active
     theme in light mode and flips with html.dark (paper surface, light ink,
     readable link and button) instead of staying a white box on a dark canvas. */
  #lh-cookie-note {
    position: fixed; left: 1rem; bottom: 1rem; z-index: 60;
    display: flex; align-items: center; gap: .75rem;
    width: min(30rem, calc(100vw - 2rem));
    padding: .7rem .85rem;
    border: 1px solid var(--lh-line, rgba(15, 23, 42, .08));
    border-radius: .9rem;
    background: var(--lh-paper, rgba(255, 255, 255, .97));
    color: var(--lh-body, #475569);
    box-shadow: var(--lh-shadow, 0 18px 40px -20px rgba(15, 23, 42, .45));
  }
  #lh-cookie-note b { color: var(--lh-ink, inherit); }
  #lh-cookie-note a { color: var(--lh-deep, #047857); }
  #lh-cookie-note button {
    border: 1px solid var(--lh-line-2, #e2e8f0);
    color: var(--lh-mut, #475569);
  }
  #lh-cookie-note button:hover { background: var(--lh-tint, #f1f5f9); }
  /* hidden until JS decides this visitor has not dismissed it yet — and out of the
     way at the end of the page, where the fixed footer bar (with its Legal column)
     takes over the bottom of the screen */
  #lh-cookie-note[hidden],
  body.lh-at-bottom #lh-cookie-note { display: none; }
</style>
<script>
(function () {
  var note = document.getElementById('lh-cookie-note');
  if (!note) return;
  var KEY = 'lh-cookie-notice';
  try { if (localStorage.getItem(KEY) === '1') return; } catch (e) { /* storage blocked — show it, never remember */ }
  note.hidden = false;
  var ok = document.getElementById('lh-cookie-note-ok');
  if (ok) ok.addEventListener('click', function () {
    try { localStorage.setItem(KEY, '1'); } catch (e) { /* ignore */ }
    note.hidden = true;
  });
})();
</script>

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

<script src="assets/app.js?v=47"></script>
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

<script>
/* Show/hide password — ONE delegated handler for every eye button on the page
   (login, register, …). The button ships aria-pressed="false" from
   password_toggle_btn(); this flips the input's type AND aria-pressed together,
   so the icon (which CSS keys off aria-pressed) always tells the truth. The
   button is type="button", so clicking it can never submit the form — the CSRF
   field and the inputs are left alone. Refocus the input afterwards: some
   browsers park the click on the button, and the visitor was mid-type. */
document.addEventListener('click', function (e) {
  var t = e.target;
  var btn = t && t.closest ? t.closest('.lh-pw-btn') : null;
  if (!btn) return;
  var inp = document.getElementById(btn.getAttribute('data-lh-pw-toggle') || '');
  if (!inp) return;
  var show = inp.type === 'password';
  inp.type = show ? 'text' : 'password';
  btn.setAttribute('aria-pressed', show ? 'true' : 'false');
  btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  btn.setAttribute('title', show ? 'Hide password' : 'Show password');
  try { inp.focus({ preventScroll: true }); } catch (err) { inp.focus(); }
});
</script>

</body>
</html>
