<?php
/**
 * Skeleton loading screen — one shared partial, used by every page.
 *
 * WHAT IT IS
 *   A frosted pane shaped like the app shell (nav rail, top bar, stat cards)
 *   that sits over the page from the first paint until the page has really
 *   loaded — stylesheets, webfonts, scripts and images in. The page's content
 *   is already rendered server-side underneath it, so this is only about the
 *   wait reading as "this page is coming" instead of as an empty canvas.
 *
 * HOW A PAGE USES IT — two calls:
 *   <head> : lh_skeleton_css();      the styles (inline, so no extra request)
 *   <body> : lh_skeleton_body();     the pane + its small boot script
 *   header.php already calls both, so every header/footer page has it. The
 *   shell-less pages (read.php, quiz.php, verify_certificate.php and the
 *   printable branch of certificate.php) require this file once and make the
 *   same two calls — except that the two public card pages pass false, having
 *   no bar of their own for the ghost to stand in for.
 *
 * OFF SWITCHES — any one of these, for a page that should not show it:
 *   define('LH_SKELETON', false);   config.php, whole site
 *   $lh_skeleton = false;           before header.php is required
 *   ?noskeleton=1                   quick check while working on a page
 *
 * IT CAN NEVER GET IN THE WAY: the pane ignores pointer events, with scripting
 * off it never appears at all (its CSS needs html.lh-skel-on, which only its
 * own script adds), it fades out and is then taken out of the layout for good,
 * and a hard cap dismisses it even if "load" never fires.
 */

if (!function_exists('lh_skeleton_off')) {
    /** Should this response skip the skeleton? */
    function lh_skeleton_off(): bool
    {
        global $lh_skeleton, $lh_no_skeleton;
        if (defined('LH_SKELETON') && !LH_SKELETON) return true;
        if (isset($lh_skeleton) && !$lh_skeleton) return true;
        if (!empty($lh_no_skeleton)) return true;
        return isset($_GET['noskeleton']) && (string) $_GET['noskeleton'] !== '0';
    }
}

if (!function_exists('lh_skeleton_css')) {
    /** The pane's styles — call inside <head>, after the base stylesheet. */
    function lh_skeleton_css(): void
    {
        if (lh_skeleton_off()) return;
        ?>
        <style>
          /* ============================================================
             Skeleton loading screen
             The pane is frosted rather than opaque, so whatever the active
             theme paints behind it — light canvas, paper, a dark rail — still
             reads through, and the bars are neutral enough to sit on any of
             them. Markup + boot script come from lh_skeleton_body().
             ============================================================ */
          #lh-skel {
            position: fixed;
            inset: 0;
            z-index: 95;
            display: none;
            opacity: 0;
            pointer-events: none;
            background: rgba(248, 250, 249, .94);
            -webkit-backdrop-filter: blur(8px);
            backdrop-filter: blur(8px)
          }

          /* only the boot script adds .lh-skel-on — no script, no pane */
          html.lh-skel-on #lh-skel {
            display: block;
            opacity: 1
          }

          /* the fade-down, then the script drops both classes: display:none */
          html.lh-skel-out #lh-skel {
            display: block;
            opacity: 0;
            transition: opacity .24s ease
          }

          /* The browser paints scrollbars ABOVE every overlay, so the app's
             emerald thumb would otherwise ride along the pane's right edge
             (header.php paints it on * and each theme repaints it). Quiet the
             thumbs for as long as .lh-skel-on is on <html>. Only the colour
             changes, so the gutter keeps its width and nothing underneath
             moves; the thumb is back the moment the script drops the class.
             The body-descendant selectors outrank the themes' inner-scroll
             rules (main.lh-main::-webkit-scrollbar-thumb). */
          html.lh-skel-on,
          html.lh-skel-on body,
          html.lh-skel-on body * {
            scrollbar-color: transparent transparent
          }

          html.lh-skel-on::-webkit-scrollbar-thumb,
          html.lh-skel-on body::-webkit-scrollbar-thumb,
          html.lh-skel-on body ::-webkit-scrollbar-thumb {
            background: transparent
          }

          #lh-skel .lh-skel-b {
            display: block;
            border-radius: 9px;
            background: linear-gradient(90deg, rgba(100, 116, 139, .20) 25%, rgba(100, 116, 139, .09) 37%, rgba(100, 116, 139, .20) 63%);
            background-size: 400% 100%;
            animation: lhSkelSweep 1.4s ease-in-out infinite
          }

          @keyframes lhSkelSweep {
            from {
              background-position: 100% 50%
            }

            to {
              background-position: 0 50%
            }
          }

          /* ---- ghost of the top bar (every page has one) ---- */
          #lh-skel .lh-skel-top {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0 1rem;
            background: rgba(255, 255, 255, .55);
            border-bottom: 1px solid rgba(15, 23, 42, .05)
          }

          #lh-skel .lh-skel-top-l,
          #lh-skel .lh-skel-top-r {
            display: flex;
            min-width: 0;
            align-items: center;
            gap: .55rem
          }

          #lh-skel .lh-skel-top-l {
            flex: 1
          }

          #lh-skel .lh-skel-sq {
            width: 28px;
            height: 28px;
            border-radius: 9px
          }

          #lh-skel .lh-skel-word {
            width: 104px;
            height: 22px
          }

          #lh-skel .lh-skel-ico {
            width: 36px;
            height: 36px;
            border-radius: 10px
          }

          #lh-skel .lh-skel-pill {
            width: 104px;
            height: 36px;
            border-radius: 10px
          }

          /* ---- ghost of the nav rail (signed-in pages, >=1024px) ---- */
          #lh-skel .lh-skel-rail {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 264px;
            display: none;
            padding: 1.1rem .85rem;
            background: rgba(255, 255, 255, .5);
            border-right: 1px solid rgba(15, 23, 42, .05)
          }

          #lh-skel .lh-skel-mark {
            width: 58px;
            height: 58px;
            margin: 0 auto 1.1rem;
            border-radius: 16px
          }

          #lh-skel .lh-skel-side {
            height: 34px;
            margin-bottom: .35rem;
            border-radius: 12px
          }

          @media (min-width: 1024px) {
            html.lh-skel-app #lh-skel .lh-skel-rail {
              display: block
            }

            /* the shell is padded past the fixed rail, top bar included */
            html.lh-skel-app #lh-skel .lh-skel-top {
              left: 264px
            }

            html.lh-skel-app #lh-skel .lh-skel-page {
              padding-left: 264px
            }
          }

          /* ---- the page body: heading + cards ---- */
          #lh-skel .lh-skel-page {
            padding-top: 60px
          }

          /* Pages that render no app shell of their own (the public certificate
             pages: one card, centred, no bar) say so through lh_skeleton_body
             (false) — a ghost must not promise chrome that is not coming. */
          #lh-skel.lh-skel-bare .lh-skel-top {
            display: none
          }

          #lh-skel.lh-skel-bare .lh-skel-page {
            padding-top: 0
          }

          #lh-skel .lh-skel-inner {
            max-width: 72rem;
            margin: 0 auto;
            padding: 1.75rem 1rem 3rem
          }

          #lh-skel .lh-skel-kicker {
            width: 92px;
            height: 12px;
            border-radius: 6px
          }

          #lh-skel .lh-skel-title {
            width: min(420px, 70%);
            height: 30px;
            margin-top: .85rem
          }

          #lh-skel .lh-skel-sub {
            width: min(560px, 88%);
            height: 14px;
            margin-top: .7rem
          }

          #lh-skel .lh-skel-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem
          }

          #lh-skel .lh-skel-card {
            min-height: 104px;
            padding: 1rem;
            border-radius: 16px;
            border: 1px solid rgba(15, 23, 42, .05);
            background: rgba(255, 255, 255, .72)
          }

          #lh-skel .lh-skel-tile {
            width: 38px;
            height: 38px;
            border-radius: 11px
          }

          #lh-skel .lh-skel-num {
            width: 58%;
            height: 22px;
            margin-top: .7rem
          }

          #lh-skel .lh-skel-line {
            height: 12px;
            margin-top: .55rem
          }

          #lh-skel .lh-skel-line.short {
            width: 62%
          }

          #lh-skel .lh-skel-wide {
            height: 168px;
            margin-top: 1rem;
            padding: 1.1rem;
            border-radius: 16px;
            border: 1px solid rgba(15, 23, 42, .05);
            background: rgba(255, 255, 255, .66)
          }

          #lh-skel .lh-skel-wide .lh-skel-line:first-child {
            width: 30%;
            margin-top: 0
          }

          /* on a phone the cards would run past the fold — keep it short */
          @media (max-width: 639px) {
            #lh-skel .lh-skel-card:nth-child(n+4),
            #lh-skel .lh-skel-wide,
            #lh-skel .lh-skel-pill {
              display: none
            }

            #lh-skel .lh-skel-inner {
              padding: 1.25rem 1rem 2rem
            }
          }

          @media (prefers-reduced-motion: reduce) {
            #lh-skel .lh-skel-b {
              animation: none
            }

            html.lh-skel-out #lh-skel {
              transition: none
            }
          }

          /* never on paper: the certificate page is meant to be printed */
          @media print {
            #lh-skel {
              display: none !important
            }
          }
        </style>
        <?php
    }
}

if (!function_exists('lh_skeleton_body')) {
    /** The pane + its boot script — call immediately after <body>.
     *  $shell = false on a page that renders no app shell of its own: the
     *  top-bar ghost would promise chrome that is not coming. */
    function lh_skeleton_body(bool $shell = true): void
    {
        if (lh_skeleton_off()) return;
        ?>
        <div id="lh-skel"<?= $shell ? '' : ' class="lh-skel-bare"' ?> aria-hidden="true">
          <div class="lh-skel-rail">
            <span class="lh-skel-b lh-skel-mark"></span>
            <span class="lh-skel-b lh-skel-side"></span>
            <span class="lh-skel-b lh-skel-side"></span>
            <span class="lh-skel-b lh-skel-side"></span>
            <span class="lh-skel-b lh-skel-side"></span>
            <span class="lh-skel-b lh-skel-side"></span>
            <span class="lh-skel-b lh-skel-side"></span>
          </div>
          <div class="lh-skel-top">
            <div class="lh-skel-top-l">
              <span class="lh-skel-b lh-skel-sq"></span>
              <span class="lh-skel-b lh-skel-word"></span>
            </div>
            <div class="lh-skel-top-r">
              <span class="lh-skel-b lh-skel-ico"></span>
              <span class="lh-skel-b lh-skel-ico"></span>
              <span class="lh-skel-b lh-skel-pill"></span>
            </div>
          </div>
          <div class="lh-skel-page">
            <div class="lh-skel-inner">
              <span class="lh-skel-b lh-skel-kicker"></span>
              <span class="lh-skel-b lh-skel-title"></span>
              <span class="lh-skel-b lh-skel-sub"></span>
              <div class="lh-skel-grid">
                <?php for ($lh_i = 0; $lh_i < 4; $lh_i++): ?>
                  <div class="lh-skel-card">
                    <span class="lh-skel-b lh-skel-tile"></span>
                    <span class="lh-skel-b lh-skel-num"></span>
                    <span class="lh-skel-b lh-skel-line short"></span>
                  </div>
                <?php endfor; ?>
              </div>
              <div class="lh-skel-wide">
                <span class="lh-skel-b lh-skel-line"></span>
                <span class="lh-skel-b lh-skel-line"></span>
                <span class="lh-skel-b lh-skel-line short"></span>
              </div>
              <div class="lh-skel-wide">
                <span class="lh-skel-b lh-skel-line"></span>
                <span class="lh-skel-b lh-skel-line short"></span>
              </div>
            </div>
          </div>
        </div>
        <?php
        lh_skeleton_boot();
    }
}

if (!function_exists('lh_skeleton_boot')) {
    /** Drives the pane: up at first paint, down when the page is ready, and
     *  up again while a real in-site navigation is under way. */
    function lh_skeleton_boot(): void
    {
        if (lh_skeleton_off()) return;
        ?>
        <script>
          /* Runs during the parse, right after the pane, so the pane is on
             screen for the first paint. Everything here is a no-op if the pane
             is missing, and the state lives on <html> alone — one class to be
             on, one class to fade out, nothing else to unwind. */
          (function () {
            var doc = document.documentElement;
            var pane = document.getElementById('lh-skel');
            if (!pane || !doc || !doc.classList || !document.body) return;

            var MIN = 300;      /* once painted, never flash for less than this */
            var CAP = 3000;     /* the content is already there — never hold it back */
            var NAV_CAP = 6000; /* a click that turns out not to navigate falls back */
            var FADE = 260;     /* must outlast the CSS transition (.24s) */

            var t0 = Date.now();
            var navTimer = 0;
            var done = false;
            var loaded = document.readyState === 'complete';
            var fonts = !(document.fonts && document.fonts.ready && document.fonts.ready.then);

            function show() {
              if (navTimer) { clearTimeout(navTimer); navTimer = 0; }
              doc.classList.remove('lh-skel-out');
              doc.classList.add('lh-skel-on');
            }

            function hide() {
              if (navTimer) { clearTimeout(navTimer); navTimer = 0; }
              if (!doc.classList.contains('lh-skel-on') && !doc.classList.contains('lh-skel-out')) return;
              doc.classList.add('lh-skel-out');
              setTimeout(function () {
                doc.classList.remove('lh-skel-on');
                doc.classList.remove('lh-skel-out');
              }, FADE);
            }

            function finish() {
              if (done) return;
              done = true;
              var wait = MIN - (Date.now() - t0);
              setTimeout(hide, wait > 0 ? wait : 0);
            }

            function maybe() { if (loaded && fonts) finish(); }

            /* the rail and the top bar only step aside on the signed-in shell */
            if (document.body.classList.contains('lh-app')) doc.classList.add('lh-skel-app');

            show();
            if (!loaded) window.addEventListener('load', function () { loaded = true; maybe(); });
            if (!fonts) document.fonts.ready.then(function () { fonts = true; maybe(); }, function () { fonts = true; maybe(); });
            maybe();
            setTimeout(finish, CAP);

            /* back/forward cache: a restored page is ready by definition */
            window.addEventListener('pageshow', function (e) {
              if (!e.persisted) return;
              doc.classList.remove('lh-skel-on');
              doc.classList.remove('lh-skel-out');
            });
            /* ---------- the trip to the next page ---------- */
            function preview() {
              show();
              if (navTimer) clearTimeout(navTimer);
              navTimer = setTimeout(hide, NAV_CAP);
            }

            /* Both handlers wait a task before believing the navigation really
               happens: a handler that calls preventDefault — a modal opener, an
               AJAX form — has finished by then, so anything that stays on the
               page never puts the pane up. */
            document.addEventListener('click', function (e) {
              if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
              var t = e.target;
              var a = t && t.closest ? t.closest('a[href]') : null;
              if (!a) return;
              setTimeout(function () {
                if (e.defaultPrevented || !a.isConnected) return;
                if (a.hasAttribute('download') || a.hasAttribute('data-no-skeleton')) return;
                var target = a.getAttribute('target');
                if (target && target !== '_self') return;
                var raw = a.getAttribute('href') || '';
                if (!raw || raw.charAt(0) === '#' || /^(mailto:|tel:|javascript:|data:|blob:)/i.test(raw)) return;
                var url;
                try { url = new URL(a.href, location.href); } catch (err) { return; }
                if (url.origin !== location.origin) return;
                if (/download\.php$/i.test(url.pathname)) return;   /* a file stream, not a page */
                if (url.pathname === location.pathname && url.search === location.search) return;   /* same view: a filter reloading in place */
                preview();
              }, 0);
            }, true);

            document.addEventListener('submit', function (e) {
              var f = e.target;
              if (!f || f.nodeName !== 'FORM') return;
              setTimeout(function () {
                if (e.defaultPrevented || !f.isConnected) return;
                if (f.hasAttribute('data-no-skeleton')) return;
                var target = f.getAttribute('target');
                if (target && target !== '_self') return;
                var url;
                try { url = new URL(f.getAttribute('action') || location.href, location.href); } catch (err) { return; }
                if (url.origin !== location.origin) return;
                preview();
              }, 0);
            }, true);

            /* back on the tab and the trip never happened: put it away */
            document.addEventListener('visibilitychange', function () {
              if (document.visibilityState === 'visible' && navTimer) hide();
            });

            /* anything else may drive it (app.js, inline handlers) */
            window.lhSkeleton = { show: preview, hide: hide, pane: pane };
          })();
        </script>
        <?php
    }
}
