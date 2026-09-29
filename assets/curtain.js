/* ============================================================
   Curtain reveal — the loader
   ------------------------------------------------------------
   showSkeletons([root])  lays a gray cover over the data inside root
                          (default: <main>) — the headings, the
                          paragraphs, the values, the cells, the
                          charts — one per piece, so they can reveal
                          independently.
   hideSkeletons()        fades every cover that is up and then
                          removes it, leaving the real content —
                          which was never touched — in place.

   What is covered is the CONTENT, never the box around it. A card
   keeps its background, its border and its radius, a table keeps
   its frame and its row lines, and what turns gray is the words and
   the media inside them. The sidebar is out of reach by
   construction: the walk only ever starts from the content root and
   <aside>/<nav> are refused on the way, so a cover can never land
   on the rail or the top bar.

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
    /* What a cover is FOR: the words and the pictures. A heading, a
       paragraph, a list item, a table cell, a chart, an image — each one is
       covered as it stands. The box it sits in (the card, the panel, the
       table, the table's rows) is walked THROUGH instead of covered, so the
       page keeps its own shape while it loads and only the data turns gray.
       A card of bare words is a leaf with no children: there the card is
       what holds the text, and it is the one covered (down to its content
       box — see padOf()). */
    data: 'h1,h2,h3,h4,h5,h6,p,li,dt,dd,figcaption,address,summary,td,th,'
        + 'img,svg,canvas,video,iframe,object,embed',
    /* Never walk deeper looking for data — page content nests three, four
       levels (main > section > grid > card > body). Past this a wrapper is
       covered as one box rather than followed forever, which is a last
       resort: it only happens to something buried ten levels deep, so the
       box that ends up gray is a small one inside a card, never the card. */
    maxDepth: 10,
    /* a cover has to be a real box before it is worth covering */
    minW: 24,
    minH: 12,
    /* One cover per card used to be the rule; now it is one per value, so a
       page of cards means more of them. Still bounded: a 200-row table must
       not mean a thousand shimmering gradients. Past this the rest of the
       page shows its real content — it is below the fold anyway. */
    maxCount: 240,
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
    /* The children that are boxes of their own. An inline child — <a>,
       <strong>, <br>, <svg>, a chip — is part of a line of text, not a block
       on its own: covering one of those alone would leave the rest of the
       line readable, so the element holding the line is covered instead. */
    var out = [], kids = el.children;
    for (var i = 0; i < kids.length; i++) {
      var display = getComputedStyle(kids[i]).display;
      if (display === 'none') continue;    /* tabs, collapsed filters */
      if (display === 'inline') continue;  /* part of a text line */
      out.push(kids[i]);
    }
    return out;
  }

  function padOf(el) {
    /* The padding of whatever is about to be covered, so its cover can shrink
       to the CONTENT box: a card that is only words keeps its padding, a table
       cell keeps its gutters, and what turns gray is the data rather than the
       box around it. Null when there is no padding to give back — the cover is
       then the block itself, exactly as .skeleton-cover says. */
    var cs = getComputedStyle(el);
    var out = [cs.paddingTop, cs.paddingRight, cs.paddingBottom, cs.paddingLeft];
    for (var i = 0; i < 4; i++) if (parseFloat(out[i]) > 0) return out;
    return null;
  }

  /** The data inside one root — the words, the values, the media. */
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
      /* a holder of text, or a wrapper too deep to be worth following: what is
         in hand is the data, so it is the thing covered */
      if (!kids.length || depth >= DEFAULTS.maxDepth) { push(el); return; }
      for (var i = 0; i < kids.length; i++) {
        var k = kids[i];
        if (covered.has(k) || skippable(k) || !boxed(k)) continue;
        /* a known piece of data gets its own cover; a card, a panel, a table
           or a plain layout div is descended through, so it is the words
           inside it that turn gray — the box keeps its background, its border
           and its radius, and the page goes on looking like itself */
        if (k.matches(DEFAULTS.data)) push(k);
        else walk(k, depth + 1);
      }
    }

    var kids = blockKids(root);
    if (!kids.length) { push(root); return found; }
    kids.forEach(function (k) {
      if (k.matches(DEFAULTS.data)) push(k);
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
      var pad = padOf(el);
      if (pad) {
        /* it has padding of its own — a cell's gutter, a card's breathing
           room — so the cover is measured to the content box and the box is
           left standing instead of being painted over */
        c.className += ' skeleton-inset';
        c.style.setProperty('--lh-curtain-pt', pad[0]);
        c.style.setProperty('--lh-curtain-pr', pad[1]);
        c.style.setProperty('--lh-curtain-pb', pad[2]);
        c.style.setProperty('--lh-curtain-pl', pad[3]);
      }
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
