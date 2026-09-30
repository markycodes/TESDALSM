/* LearnHub LMS — the colour scheme: light ⇄ dark, one click, from the top-right
   corner of the bar.

   What this file owns is small on purpose:

   • the switch itself — one class on <html> — and the remembering of it, in the
     same localStorage the sidebar fold already uses ('lh-scheme'). Nothing is
     saved on the server: which end of the day you are reading at is a property
     of the room you are in, not of your account, and two people sharing a staff
     computer may well want different ones.
   • the reveal. Applying a scheme is a single class toggle, so the whole job
     left here is doing it in a way the eye can follow.

   The bootstrap in header.php's <head> already applied the stored choice before
   the first stylesheet painted, so by the time this runs the page is dressed
   correctly and everything below is only about the *change*.

   Three routes, best available first:

   1. startViewTransition() — the incoming page is really unrolled over the
      outgoing one through a widening circle (dark.css does the clipping).
   2. no view transitions → the same circle cut out of one flat sheet of the
      incoming canvas colour, with the real page swapped in underneath it.
   3. prefers-reduced-motion → no animation at all. The page simply changes. */
(function () {
  const root = document.documentElement;
  const KEY = 'lh-scheme';
  const motion = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;

  function current() {
    return root.classList.contains('dark') ? 'dark' : 'light';
  }

  function sync() {
    /* the icon you see is decided in dark.css by the same class, so it can never
       disagree with the canvas; all that is left is what the button says it does */
    const label = current() === 'dark' ? 'Light mode' : 'Dark mode';
    document.querySelectorAll('[data-lh-scheme]').forEach((btn) => {
      btn.setAttribute('data-tip', label);   /* the shell's own tooltip */
      btn.setAttribute('aria-label', label);
      btn.setAttribute('aria-pressed', current() === 'dark' ? 'true' : 'false');
    });
  }

  function paint(next) {
    root.classList.toggle('dark', next === 'dark');
    try {
      localStorage.setItem(KEY, next);
    } catch (e) { /* private window or blocked storage: the page still changes
                     for this visit and simply cannot remember it next time */ }
    sync();
  }

  /* What colour will the incoming page paint its canvas with? Asked, not
     assumed: body carries the aurora, and a theme can have replaced it, so the
     answer is whatever this page's own background resolves to. It is measured by
     putting the class on, reading, and taking it off again — three statements
     inside one function, so nothing repaints between them. */
  function canvas(next) {
    const was = current() === 'dark';
    const want = next === 'dark';
    let color = '';
    if (was !== want) root.classList.toggle('dark', want);
    try { color = getComputedStyle(document.body).backgroundColor; } catch (e) { }
    if (was !== want) root.classList.toggle('dark', was);
    if (!color || color === 'transparent' || color === 'rgba(0, 0, 0, 0)') {
      color = want ? '#0b1512' : '#f2f8f4';   /* the two canvases, as dark.css paints them */
    }
    return color;
  }

  /* The circle belongs to the button, not to a hard-coded corner: measure the
     one that was pressed, and the radius is the distance from there to the
     farthest pixel of the viewport — so no corner can be left mid-change. The
     button sits at the right end of the top bar, which is the top-right corner
     the animation is meant to open from. */
  function geometry(origin) {
    const w = window.innerWidth || root.clientWidth || 0;
    const h = window.innerHeight || root.clientHeight || 0;
    let x = w;
    let y = 0;
    if (origin && origin.getBoundingClientRect) {
      const r = origin.getBoundingClientRect();
      if (r.width || r.height) {
        x = r.left + r.width / 2;
        y = r.top + r.height / 2;
      }
    }
    const far = Math.ceil(Math.sqrt(
      Math.pow(Math.max(x, w - x), 2) + Math.pow(Math.max(y, h - y), 2)
    ));
    root.style.setProperty('--lh-wipe-x', Math.round(x) + 'px');
    root.style.setProperty('--lh-wipe-y', Math.round(y) + 'px');
    root.style.setProperty('--lh-wipe-r', far + 'px');
  }

  /* route 2: the flat sheet */
  function wipe(next) {
    let sheet = null;
    try {
      sheet = document.createElement('div');
      sheet.className = 'lh-wipe';
      sheet.setAttribute('aria-hidden', 'true');
      sheet.style.setProperty('--lh-wipe-bg', canvas(next));
      document.body.appendChild(sheet);
    } catch (e) {
      paint(next);
      return;
    }
    void sheet.offsetWidth;   /* the closed circle has to be painted before it opens */
    sheet.classList.add('is-going');
    let settled = false;
    const settle = () => {
      if (settled) return;
      settled = true;
      paint(next);            /* the real page, at this moment hidden under a full circle */
      sheet.classList.add('is-out');
      setTimeout(() => { if (sheet && sheet.parentNode) sheet.parentNode.removeChild(sheet); }, 280);
    };
    sheet.addEventListener('transitionend', settle);
    /* transitionend is not promised: a backgrounded tab, an unsupported
       clip-path, or a browser that never started a transition at all. The scheme
       cannot be allowed to depend on it, so there is a second way out. */
    setTimeout(settle, 900);
  }

  function set(next, origin) {
    if (next !== 'dark' && next !== 'light') return;
    if (next === current()) { sync(); return; }
    geometry(origin || null);
    if (motion && motion.matches) { paint(next); return; }
    if (typeof document.startViewTransition === 'function') {
      let vt = null;
      try {
        root.classList.add('lh-reveal');   /* dark.css keys the clipping to this class */
        vt = document.startViewTransition(() => { paint(next); });
      } catch (e) {
        root.classList.remove('lh-reveal');
        paint(next);
        return;
      }
      const off = () => root.classList.remove('lh-reveal');
      if (vt && vt.finished) {
        /* .finished rejects when a transition is skipped or aborted — handled
           here, so a skipped transition is simply a plain change */
        Promise.resolve(vt.finished).then(off, off);
      } else {
        setTimeout(off, 900);
      }
      return;
    }
    wipe(next);
  }

  window.lhScheme = {
    get: current,
    set: set,
    toggle: (origin) => set(current() === 'dark' ? 'light' : 'dark', origin)
  };

  /* one delegated listener rather than a wiring pass: the bar is written once
     per page, but the button also appears in markup that app.js redraws, and a
     listener on the document reaches every copy of it with nothing to re-run. */
  document.addEventListener('click', (e) => {
    const btn = e.target && e.target.closest ? e.target.closest('[data-lh-scheme]') : null;
    if (!btn) return;
    set(current() === 'dark' ? 'light' : 'dark', btn);
  });

  sync();
})();

