/* ============================================================
   Curtain reveal — the loader
   ------------------------------------------------------------
   showSkeletons([root])  lays a gray cover over the content blocks
                          inside root (default: <main>), one per
                          block, so they can reveal independently.
   hideSkeletons()        fades every cover that is up and then
                          removes it, leaving the real content —
                          which was never touched — in place.

   The sidebar is out of reach by construction: the walk only ever
   starts from the content root and <aside>/<nav> are refused on the
   way, so a cover can never land on the rail or the top bar.

   Nothing here is required for a page to work: with scripting off,
   or if this file fails to load, no cover is ever created and the
   server-rendered content simply shows.
   ============================================================ */
(function (w, d) {
  'use strict';

  if (w.LHCurtain) return;

  var DEFAULTS = {
    /* what counts as a page of content */
    root: 'main',
    /* a childless match is a block of text/media: cover it as it stands */
    blocks: 'h1,h2,h3,h4,h5,p,ul,ol,dl,table,figure,blockquote,pre,address,summary,iframe,video,canvas,svg,img',
    /* the repeated item inside a grid or a list — cover the items, not the grid */
    cards: '.card,[class*="rounded-"],[class*="border-b"]',
    /* never walk deeper looking for blocks — page content nests two, three levels */
    maxDepth: 5,
    /* a block has to be a real box before it is worth covering */
    minW: 24,
    minH: 12,
    /* an admin index with 200 rows should not mean 200 nodes either */
    maxCount: 160,
    /* shell chrome, overlays and anything that must stay reachable.
       [data-skeleton-skip] / [data-no-skeleton] are the escape hatch a page
       raises to keep one region clear — a live timer, a captcha, a widget it
       drives itself. It applies to that element and everything inside it. */
    skip: 'aside,nav,form,button,select,textarea,input,template,style,script,noscript,'
        + '[hidden],[aria-hidden="true"],[role="dialog"],[role="alert"],[aria-live],'
        + '[data-skeleton-skip],[data-no-skeleton],'
        + '#lh-sidebar,#lh-backdrop,#lh-progress,#lh-skel,.lh-toast,.lh-modal,.skeleton-cover'
  };

  var covered = new WeakSet();
  var active = new WeakMap();   /* block -> the cover currently owned by it, so a
                                   fade still in flight cannot undress a block that
                                   has meanwhile been covered again */
  var live = [];            /* [{ el, cover }] in cover order */

  function rootOf(root) {
    if (root instanceof Element) return root;
    if (typeof root === 'string') return d.querySelector(root);
    /* the content column, never the shell: every page of this app has one of
       these (header.php's <main> for both themes, .page-content for the
       shell-less pages). If none is found there is nothing safe to cover, and
       the page simply shows — falling back to <body> would put covers over
       the sidebar, which is the one thing this must never do. */
    var chain = ['main', '.app-main', '.page-content', '.content-area'];
    for (var i = 0; i < chain.length; i++) {
      var found = d.querySelector(chain[i]);
      if (found) return found;
    }
    return null;
  }

  function skippable(el) {
    return el.closest(DEFAULTS.skip) !== null;
  }

  function boxed(el) {
    if (getComputedStyle(el).display === 'none') return false;
    var r = el.getBoundingClientRect();
    return r.width >= DEFAULTS.minW && r.height >= DEFAULTS.minH;
  }

  function blockKids(el) {
    var out = [], kids = el.children;
    for (var i = 0; i < kids.length; i++) {
      if (getComputedStyle(kids[i]).display === 'none') continue;   /* tabs, collapsed filters */
      out.push(kids[i]);
    }
    return out;
  }

  function oneShape(kids) {
    /* the tell-tale of a card grid / a list of rows: several siblings, same tag,
       and they all look like items — then each item earns its own curtain */
    if (kids.length < 2) return false;
    var tag = kids[0].tagName;
    for (var i = 0; i < kids.length; i++) {
      if (kids[i].tagName !== tag) return false;
      if (!kids[i].matches(DEFAULTS.cards)) return false;
    }
    return true;
  }

  /** The content blocks of one root, at the deepest level that still makes sense. */
  function collect(root) {
    var found = [];

    function push(el) {
      if (found.length >= DEFAULTS.maxCount || covered.has(el)) return;
      if (skippable(el) || !boxed(el)) return;
      covered.add(el);
      found.push(el);
    }

    function walk(el, depth) {
      if (found.length >= DEFAULTS.maxCount) return;
      var kids = blockKids(el);
      if (!kids.length || depth >= DEFAULTS.maxDepth) { push(el); return; }
      if (oneShape(kids)) { kids.forEach(push); return; }
      for (var i = 0; i < kids.length; i++) {
        var k = kids[i];
        if (covered.has(k) || skippable(k) || !boxed(k)) continue;
        /* a known block of content gets its own cover; a plain layout div is
           descended through, so its cards and headings are the ones covered */
        if (k.matches(DEFAULTS.blocks) || k.matches(DEFAULTS.cards)) push(k);
        else walk(k, depth + 1);
      }
    }

    var kids = blockKids(root);
    if (!kids.length) { push(root); return found; }
    kids.forEach(function (k) {
      if (k.matches(DEFAULTS.blocks) || k.matches(DEFAULTS.cards) || oneShape(blockKids(k))) push(k);
      else walk(k, 1);
    });
    return found;
  }

  /** Put the covers up. Idempotent; call it again when new content arrives. */
  function show(root) {
    var host = rootOf(root);
    if (!host || !host.classList) return 0;
    var targets = collect(host);
    for (var i = 0; i < targets.length; i++) {
      var el = targets[i], c = d.createElement('div');
      el.classList.add('skeleton-wrap');
      el.classList.remove('skeleton-loaded');      /* a re-cover snaps back to gray, no ease-through */
      c.className = 'skeleton-cover';
      c.setAttribute('aria-hidden', 'true');
      c.style.setProperty('--lh-curtain-i', String(Math.min(i, 8)));
      el.appendChild(c);
      active.set(el, c);
      live.push({ el: el, cover: c });
    }
    return live.length;
  }

  /** Fade the covers out, then take them out of the DOM. */
  function hide() {
    var batch = live;
    live = [];
    batch.forEach(function (rec, i) {
      var el = rec.el, c = rec.cover;
      var drop = function () {
        if (c.parentNode) c.parentNode.removeChild(c);
        if (active.get(el) !== c) return;   /* a newer cover owns this block now */
        active.delete(el);
        el.classList.remove('skeleton-wrap', 'skeleton-loaded');
        covered.delete(el);                       /* free to cover again on the next trip */
      };
      c.addEventListener('transitionend', function (ev) {
        if (ev.propertyName === 'opacity') drop();
      });
      /* a slight cascade: the cover nearest the top lifts first */
      setTimeout(function () { el.classList.add('skeleton-loaded'); }, Math.min(i * 28, 240));
      setTimeout(drop, 1500);                     /* one that never transitioned must still go */
    });
    return batch.length;
  }

  w.LHCurtain = {
    show: show,
    hide: hide,
    up: function () { return live.length; },
    options: DEFAULTS
  };

  /* the names the page code calls */
  w.showSkeletons = show;
  w.hideSkeletons = hide;
})(window, document);
