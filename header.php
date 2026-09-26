<?php
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/skeleton.php';   /* the loading pane: lh_skeleton_css() + lh_skeleton_body() */
$user = current_user();
$page_title = $page_title ?? 'LearnHub';
$nav_active = $nav_active ?? '';
$flashes = take_flashes();
if ($user) {
  touch_presence((int) $user['id']);
} // keep the heartbeat fresh on every page view
?><!DOCTYPE html>
<?php /* data-theme / data-density / data-layout mirror the three Appearance
         picks. They are read-only hooks: a layout stylesheet can key off a
         theme whose shell geometry it must respect (material's floating rail
         and inner-scroll frame, say) without knowing which files were saved,
         and they make "which look is this page actually using?" one glance in
         DevTools instead of three settings lookups. */ ?>
<html lang="en" data-theme="<?= e(ui_theme()) ?>" data-density="<?= e(ui_density()) ?>"
  data-layout="<?= e(ui_layout()) ?>">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf" content="<?= e(csrf_token()) ?>">
  <?php if (!empty($attendance_course)): ?>
    <meta name="attendance-course" content="<?= (int) $attendance_course ?>"><?php endif; ?>
  <title><?= e($page_title) ?> · LearnHub LMS</title>
  <link rel="stylesheet" href="assets/tailwind.min.css">
  <link rel="icon" href="logo/logo.png" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700;800&display=swap"
    rel="stylesheet">
  <style>
    /* ============================================================
   LearnHub v2 — advanced design system (part 1)
   Utility-scoped overrides keep every page's logic intact.
   ============================================================ */
    :root {
      --lh-grad: linear-gradient(135deg, #047857 0%, #059669 50%, #10b981 100%)
    }

    html {
      scroll-behavior: smooth
    }

    ::selection {
      background: #d1fae5;
      color: #064e3b
    }

    /* aurora page background */
    body {
      background:
        radial-gradient(1100px 560px at 88% -8%, rgba(5, 150, 105, .16), transparent 60%),
        radial-gradient(950px 520px at -8% 18%, rgba(4, 120, 87, .13), transparent 55%),
        radial-gradient(900px 620px at 50% 112%, rgba(6, 182, 212, .10), transparent 60%),
        #f2f8f4
    }

    /* thin gradient scrollbars */
    * {
      scrollbar-width: thin;
      scrollbar-color: #6ee7b7 transparent
    }

    *::-webkit-scrollbar {
      height: 8px;
      width: 8px
    }

    *::-webkit-scrollbar-track {
      background: transparent
    }

    *::-webkit-scrollbar-thumb {
      background: linear-gradient(#34d399, #6ee7b7);
      border-radius: 99px
    }

    /* page enter animation — OPACITY ONLY on purpose: keyframing transform here
       keeps `main` a containing block for position:fixed modals (fill-mode: both)
       and knocks every modal off the center of the screen */
    main {
      animation: pageIn .45s cubic-bezier(.22, .61, .36, 1) both
    }

    @keyframes pageIn {
      from {
        opacity: 0
      }

      to {
        opacity: 1
      }
    }

    /* glass nav */
    nav.glass {
      background: rgba(255, 255, 255, .72);
      backdrop-filter: blur(16px) saturate(1.5);
      -webkit-backdrop-filter: blur(16px) saturate(1.5);
      box-shadow: 0 8px 28px -14px rgba(4, 120, 87, .22)
    }

    nav.glass::after {
      content: '';
      position: absolute;
      left: 0;
      right: 0;
      bottom: 0;
      height: 2px;
      background: var(--lh-grad);
      opacity: .45
    }

    /* scroll progress bar */
    #lh-progress {
      position: fixed;
      top: 0;
      left: 0;
      height: 3px;
      width: 0;
      background: var(--lh-grad);
      z-index: 60;
      box-shadow: 0 0 14px rgba(5, 150, 105, .55)
    }

    /* gradient logo with pulse glow */
    .lh-logo {
      background: var(--lh-grad);
      box-shadow: 0 8px 20px -8px rgba(5, 150, 105, .6);
      position: relative;
      transition: transform .25s
    }

    .lh-logo:hover {
      transform: scale(1.06) rotate(-3deg)
    }

    .lh-logo::before {
      content: '';
      position: absolute;
      inset: -5px;
      border-radius: inherit;
      background: var(--lh-grad);
      opacity: .35;
      filter: blur(12px);
      z-index: -1;
      animation: logoPulse 3.2s ease-in-out infinite
    }

    @keyframes logoPulse {

      0%,
      100% {
        opacity: .22
      }

      50% {
        opacity: .5
      }
    }

    /* desktop nav pills */
    .lh-pill {
      position: relative;
      border-radius: 12px;
      transition: color .2s, background-color .2s, box-shadow .2s, transform .2s
    }

    .lh-pill:hover {
      color: #047857 !important;
      background: #ecfdf5 !important
    }

    /* active state: original header colors preserved (no gradient) */

    /* mobile icon buttons */
    .lh-ico {
      position: relative;
      border-radius: 12px;
      transition: all .2s
    }

    .lh-ico:hover {
      transform: translateY(-1px);
      color: #047857 !important;
      background: #ecfdf5 !important
    }

    /* notification / chat badges: red circle pinned to the top-right of the icon */
    .lh-badge {
      position: absolute;
      top: -5px;
      right: -7px;
      min-width: 18px;
      height: 18px;
      padding: 0 5px;
      display: grid;
      place-items: center;
      border-radius: 9999px;
      background: #dc2626;
      color: #fff;
      font-size: 10px;
      font-weight: 800;
      line-height: 1;
      letter-spacing: .02em;
      box-shadow: 0 0 0 2px #fff;
      pointer-events: none;
      z-index: 5;
    }

    /* the bell is a torn-paper button — the badge must not be clipped by its tear */
    #lh-notif-btn {
      clip-path: none;
    }

    /* avatar with gradient ring + online dot */
    .lh-avatar {
      background: var(--lh-grad);
      box-shadow: 0 0 0 2px #fff, 0 0 0 4px rgba(5, 150, 105, .30);
      position: relative
    }

    .lh-avatar::after {
      content: '';
      position: absolute;
      right: -2px;
      bottom: -2px;
      width: 11px;
      height: 11px;
      border-radius: 99px;
      background: #10b981;
      border: 2px solid #fff
    }

    /* guest CTA */
    .lh-cta {
      background-image: var(--lh-grad);
      background-color: #047857;
      box-shadow: 0 10px 22px -10px rgba(5, 150, 105, .65);
      transition: all .25s
    }

    .lh-cta:hover {
      transform: translateY(-2px);
      filter: brightness(1.07)
    }

    /* ---------- nav components via existing utility classes (no markup change) ---------- */
    /* desktop pills */
    nav .sm\:flex a {
      position: relative;
      border-radius: 12px;
      transition: color .2s, background-color .2s, box-shadow .2s, transform .2s
    }

    nav .sm\:flex a:not(.bg-indigo-50):hover {
      color: #047857 !important;
      background: #ecfdf5 !important
    }

    /* active state: original header colors preserved (bg-indigo-50 text-indigo-700, no gradient) */
    /* mobile icon buttons */
    nav .sm\:hidden a {
      position: relative;
      border-radius: 12px;
      transition: color .2s, background-color .2s, box-shadow .2s, transform .2s
    }

    nav .sm\:hidden a:not(.bg-indigo-600):hover {
      color: #047857 !important;
      background: #ecfdf5 !important;
      transform: translateY(-1px)
    }

    /* active state: original header colors preserved (bg-indigo-600 text-white, no gradient) */
    /* gradient logo */
    nav a.grid.rounded-xl.bg-indigo-600 {
      background-image: var(--lh-grad);
      background-color: #047857;
      box-shadow: 0 8px 20px -8px rgba(5, 150, 105, .6);
      position: relative;
      transition: transform .25s
    }

    nav a.grid.rounded-xl.bg-indigo-600:hover {
      transform: scale(1.06) rotate(-3deg)
    }

    /* avatar: gradient ring + online dot */
    nav span.rounded-full.bg-indigo-600 {
      background-image: var(--lh-grad);
      background-color: #047857;
      box-shadow: 0 0 0 2px #fff, 0 0 0 4px rgba(5, 150, 105, .3);
      position: relative
    }

    nav span.rounded-full.bg-indigo-600::after {
      content: '';
      position: absolute;
      right: -2px;
      bottom: -2px;
      width: 11px;
      height: 11px;
      border-radius: 99px;
      background: #10b981;
      border: 2px solid #fff
    }

    /* guest CTA */
    nav a.px-4.bg-indigo-600 {
      background-image: var(--lh-grad);
      background-color: #047857;
      box-shadow: 0 10px 22px -10px rgba(5, 150, 105, .65);
      transition: all .25s
    }

    nav a.px-4.bg-indigo-600:hover {
      transform: translateY(-2px);
      filter: brightness(1.07)
    }

    /* ---------- site-wide card upgrade (glass + soft depth) ---------- */
    main .bg-white.ring-1 {
      background: rgba(255, 255, 255, .85);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      box-shadow: 0 12px 32px -14px rgba(15, 23, 42, .14), inset 0 0 0 1px rgba(255, 255, 255, .65)
    }

    main .rounded-2xl.bg-white.ring-1,
    main .rounded-3xl.bg-white.ring-1 {
      transition: box-shadow .3s, transform .3s
    }

    main .rounded-2xl.bg-white.ring-1:hover,
    main .rounded-3xl.bg-white.ring-1:hover {
      box-shadow: 0 22px 44px -18px rgba(4, 120, 87, .26)
    }

    /* gradient primary buttons site-wide */
    main a.bg-indigo-600,
    main button.bg-indigo-600 {
      background-image: var(--lh-grad);
      background-color: #047857;
      box-shadow: 0 12px 24px -12px rgba(4, 120, 87, .7);
      transition: all .25s
    }

    main a.bg-indigo-600:hover,
    main button.bg-indigo-600:hover {
      transform: translateY(-2px);
      filter: brightness(1.08);
      box-shadow: 0 18px 30px -12px rgba(4, 120, 87, .75)
    }

    /* progress bars: gradient + shimmer */
    main .h-2>.h-full,
    main .h-2\.5>.h-full,
    main .h-3>.h-full {
      background-image: var(--lh-grad);
      background-color: #059669;
      position: relative;
      overflow: hidden
    }

    main .h-2>.h-full::after,
    main .h-2\.5>.h-full::after,
    main .h-3>.h-full::after {
      content: '';
      position: absolute;
      inset: 0;
      background: repeating-linear-gradient(45deg, rgba(255, 255, 255, .28) 0 8px, transparent 8px 18px);
      animation: shimmer 1.4s linear infinite
    }

    @keyframes shimmer {
      from {
        transform: translateX(-18px)
      }

      to {
        transform: translateX(18px)
      }
    }

    /* hero sections: animated aurora */
    main>section.bg-gradient-to-r {
      position: relative;
      isolation: isolate;
      background-size: 180% 180% !important;
      animation: heroShift 14s ease-in-out infinite
    }

    main>section.bg-gradient-to-r::before:;

      {
      content: '';
      position: absolute;
      inset: 0;
      z-index: -1;
      pointer-events: none;
      background: radial-gradient(420px 220px at 15% 15%, rgba(255, 255, 255, .4), transparent 60%), radial-gradient(520px 280px at 85% 85%, rgba(255, 255, 255, .25), transparent 60%);
      mix-blend-mode: overlay;
      animation: blobFloat 10s ease-in-out infinite alternate
    }

    @keyframes heroShift {

      0%,
      100% {
        background-position: 0% 50%
      }

      50% {
        background-position: 100% 50%
      }
    }

    @keyframes blobFloat {
      from {
        transform: translate(0, 0) scale(1)
      }

      to {
        transform: translate(34px, -22px) scale(1.1)
      }
    }

    /* forms */
    main input:focus,
    main select:focus,
    main textarea:focus {
      outline: none;
      border-color: #10b981;
      box-shadow: 0 0 0 4px rgba(5, 150, 105, .14)
    }

    /* floating back-to-top */
    #lh-top {
      position: fixed;
      right: 20px;
      bottom: 20px;
      z-index: 50;
      opacity: 0;
      pointer-events: none;
      transform: translateY(10px);
      transition: all .3s
    }

    #lh-top.show {
      opacity: 1;
      pointer-events: auto;
      transform: none
    }

    #lh-top:hover {
      filter: brightness(1.08)
    }

    /* ---------- preserved function styles (toast / reveal / swipe hint) ---------- */
    .toast-in {
      animation: toastIn .25s ease-out
    }

    @keyframes toastIn {
      from {
        opacity: 0;
        transform: translateY(-8px)
      }

      to {
        opacity: 1;
        transform: none
      }
    }

    .reveal {
      opacity: 0;
      transform: translateY(18px);
      transition: opacity .55s cubic-bezier(.22, .61, .36, 1), transform .55s cubic-bezier(.22, .61, .36, 1)
    }

    .reveal.in {
      opacity: 1;
      transform: none
    }

    .no-scrollbar::-webkit-scrollbar {
      display: none
    }

    .no-scrollbar {
      -ms-overflow-style: none;
      scrollbar-width: none
    }

    /* Mobile swipe hint: right-edge fade + nudge chevron; hidden on desktop or after first swipe */
    .swipe-hint::after {
      content: '';
      position: absolute;
      top: 0;
      bottom: 0;
      right: 0;
      width: 16px;
      pointer-events: none;
      background: linear-gradient(to left, rgba(255, 255, 255, .95), rgba(255, 255, 255, 0))
    }

    .swipe-hint .swipe-chevron {
      position: absolute;
      right: 3px;
      top: 50%;
      transform: translate(0, -50%);
      z-index: 10;
      display: grid;
      place-items: center;
      width: 26px;
      height: 26px;
      border-radius: 9999px;
      background: #1e293b;
      color: #fff;
      font-size: 14px;
      line-height: 1;
      box-shadow: 0 4px 10px rgba(15, 23, 42, .25);
      animation: nudge 1.4s ease-in-out infinite;
      cursor: pointer
    }

    .swipe-hint .swipe-chevron:active {
      background: #059669
    }

    @keyframes nudge {

      0%,
      100% {
        margin-right: 0
      }

      50% {
        margin-right: 4px
      }
    }

    .swipe-hint.hint-off::after {
      display: none
    }

    .swipe-hint.hint-off .swipe-chevron {
      display: none
    }

    @media (min-width:640px) {
      .swipe-hint::after {
        display: none
      }

      .swipe-hint .swipe-chevron {
        display: none
      }
    }

    @media (prefers-reduced-motion:reduce) {
      .reveal {
        opacity: 1;
        transform: none;
        transition: none
      }

      main {
        animation: none
      }

      nav a.grid.rounded-xl.bg-indigo-600::before {
        animation: none
      }

      main>section.bg-gradient-to-r {
        animation: none;
        background-size: 100% 100% !important
      }

      main>section.bg-gradient-to-r::before {
        animation: none
      }

      main .h-2>.h-full::after,
      main .h-2\.5>.h-full::after,
      main .h-3>.h-full::after {
        animation: none
      }

      .swipe-hint .swipe-chevron {
        animation: none
      }

      #lh-progress {
        transition: none
      }
    }


    /* ============================================================
   Redesign shell: fixed sidebar + top bar + notification panel
   ============================================================ */
    .lh-app {
      background: #eef3ee
    }

    .lh-sidebar {
      position: fixed;
      top: 0;
      left: 0;
      bottom: 0;
      width: 264px;
      z-index: 35;
      background: rgba(255, 255, 255, .97);
      border-right: 1px solid rgba(226, 232, 240, .92);
      box-shadow: 0 24px 48px -24px rgba(4, 120, 87, .18);
      transform: translateX(-280px);
      transition: transform .25s ease;
      display: flex;
      flex-direction: column
    }

    .lh-sidebar.open {
      transform: none
    }

    @media(min-width:1024px) {
      .lh-sidebar {
        transform: none;
        box-shadow: none
      }
    }

    .lh-backdrop {
      position: fixed;
      inset: 0;
      z-index: 34;
      background: rgba(15, 23, 42, .35);
      display: none
    }

    body.lh-side-open .lh-backdrop {
      display: block
    }

    @media(min-width:1024px) {
      .lh-backdrop {
        display: none !important
      }
    }

    body.lh-app {
      padding-left: 0
    }

    @media(min-width:1024px) {
      body.lh-app {
        padding-left: 264px
      }
    }

    .lh-main {
      transition: padding-left .25s ease
    }

    .lh-side-head {
      display: flex;
      align-items: center;
      gap: .6rem;
      padding: .9rem 1rem .8rem;
      border-bottom: 1px solid rgba(226, 232, 240, .9)
    }

    .lh-side-sec {
      padding: .9rem 1rem .35rem;
      font-size: 10px;
      font-weight: 800;
      letter-spacing: .12em;
      text-transform: uppercase;
      color: #94a3b8
    }

    .lh-side-nav {
      display: flex;
      flex-direction: column;
      gap: 2px;
      padding: 0 .65rem
    }

    .lh-side-link {
      display: flex;
      align-items: center;
      gap: .65rem;
      padding: .55rem .7rem;
      font-size: .875rem;
      font-weight: 600;
      color: #475569;
      border-radius: 12px;
      transition: all .15s;
      position: relative
    }

    .lh-side-link:hover {
      background: #ecfdf5;
      color: #047857
    }

    .lh-side-link.active {
      background: linear-gradient(135deg, rgba(4, 120, 87, .14), rgba(16, 185, 129, .10));
      color: #047857;
      box-shadow: inset 0 0 0 1.5px rgba(16, 185, 129, .28)
    }

    .lh-side-link.active::before {
      content: '';
      position: absolute;
      left: -.65rem;
      top: 20%;
      bottom: 20%;
      width: 3px;
      border-radius: 99px;
      background: linear-gradient(#047857, #10b981)
    }

    .lh-side-ico {
      width: 1.25rem;
      text-align: center;
      font-size: 15px;
      line-height: 1
    }

    .lh-side-label {
      flex: 1;
      min-width: 0
    }

    .lh-side-foot {
      margin-top: auto;
      display: flex;
      align-items: center;
      gap: .6rem;
      padding: 1rem;
      border-top: 1px solid rgba(226, 232, 240, .9)
    }

    .lh-topbar .lh-top-inner {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: .75rem;
      padding: 0 1rem;
      height: 60px
    }

    .lh-badge {
      position: absolute;
      top: -3px;
      right: -4px;
      min-width: 17px;
      height: 17px;
      padding: 0 3px;
      border-radius: 9999px;
      background: #dc2626;
      color: #fff;
      font-size: 10px;
      font-weight: 800;
      line-height: 17px;
      text-align: center;
      box-shadow: 0 0 0 2px #fff
    }

    /* ---- notifications panel: responsive on every screen ----------------------
   Phones  : full-width sheet pinned 8px inside the viewport (never overflows)
   Desktop : dropdown anchored under the bell
   Both    : dvh (not vh) so mobile URL bars can never push it off-screen,
             long words wrap, and the list scrolls with momentum containment.
   ------------------------------------------------------------------------- */
    .lh-panel {
      position: absolute;
      top: calc(100% + 10px);
      right: 0;
      z-index: 60;
      width: min(380px, calc(100vw - 2rem));
      max-height: min(480px, calc(100dvh - 84px));
      overflow: hidden;
      border-radius: 18px;
      border: 1px solid rgba(226, 232, 240, .9);
      background: #fff;
      box-shadow: 0 28px 60px -24px rgba(4, 120, 87, .28);
      display: flex;
      flex-direction: column;
      overscroll-behavior: contain
    }

    /* app.js pins the "closed" state of these components by toggling Tailwind's
   `.hidden` (a single class = 0-1-0 specificity). `.lh-panel` and `.lh-badge`
   both declare their own `display` (flex / grid) further up in THIS stylesheet,
   so they collide with `.hidden` at identical specificity — and the winning
   rule is simply the one that comes last. This inline <style> is emitted after
   the linked assets/tailwind.min.css, so without the combined-selector rules
   below the notification panel could never close and the badges stayed
   permanently visible showing an empty "0". Re-assert the closed state with a
   higher-specificity selector so the toggle always works, whatever the
   stylesheet order. */
    .lh-panel.hidden {
      display: none !important
    }

    .lh-badge.hidden {
      display: none !important
    }

    /* Mobile list "peek" — the dashboard panels (Live now / Attendance today /
   Needs attention / Recent activity) arrive with every row the server sent,
   which on a phone is a wall of text. app.js keeps the newest five rows
   visible and puts the rest behind a Show-all control: opening a panel caps
   it at five rows (the --lh-peek-h height app.js measures) and scrolling
   inside streams the next batch of rows in.
   `.lh-peek-btn` is hidden with the `hidden` attribute, so re-assert the
   closed state here — same reason as .lh-panel.hidden above. */
    .lh-peek-btn[hidden] {
      display: none !important
    }

    .lh-peek-caret {
      display: inline-block;
      margin-left: .3rem;
      line-height: 1;
      transition: transform .18s
    }

    .lh-peek-btn[aria-expanded="true"] .lh-peek-caret {
      transform: rotate(180deg)
    }

    @media (max-width: 1023.98px) {
      /* a row the reader has not "loaded" yet */
      .lh-peek-off {
        display: none !important
      }

      /* the opened panel: a five-row window whose remaining rows stream in
     as the list is scrolled (see app.js) */
      .lh-peek-box {
        max-height: var(--lh-peek-h, 15rem);
        overflow-y: auto;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
        padding-right: .25rem;
        scrollbar-width: thin
      }

      /* a row that just streamed in: a short rise, so it reads as "loaded" */
      .lh-peek-in {
        animation: lhPeekIn .22s ease-out both
      }
    }

    @keyframes lhPeekIn {
      from {
        opacity: 0;
        transform: translateY(4px)
      }

      to {
        opacity: 1;
        transform: none
      }
    }

    @media (prefers-reduced-motion: reduce) {
      .lh-peek-in {
        animation: none
      }
    }

    /* Rolling live stats: when the realtime poll changes a number (e.g. the
   "Total time" card on the attendance day page), the old value slides up and
   out while the new value slides in from the bottom. app.js adds .lh-rolling
   to the stat element only while the animation runs, so layout at rest is
   untouched. */
    .lh-rolling {
      position: relative;
      overflow: hidden;
      display: inline-block
    }

    .lh-roll-in {
      display: inline-block;
      animation: lhRollIn .42s cubic-bezier(.2, .7, .3, 1) both
    }

    .lh-roll-out {
      position: absolute;
      left: 0;
      top: 0;
      display: inline-block;
      pointer-events: none;
      animation: lhRollOut .42s cubic-bezier(.2, .7, .3, 1) both
    }

    @keyframes lhRollIn {
      from {
        transform: translateY(110%);
        opacity: 0
      }

      to {
        transform: translateY(0);
        opacity: 1
      }
    }

    @keyframes lhRollOut {
      from {
        transform: translateY(0);
        opacity: 1
      }

      to {
        transform: translateY(-110%);
        opacity: 0
      }
    }

    @media (prefers-reduced-motion: reduce) {

      .lh-roll-in,
      .lh-roll-out {
        animation: none
      }

      .lh-roll-out {
        display: none
      }
    }

    /* Dashboard graph hover: lib.php embeds each day's label + values on invisible
   strip rects; app.js moves the guide line / highlight dots and fills a shared
   tooltip. The hover layer stays hidden until a strip is under the cursor. */
    .lh-chart .js-chart-col {
      cursor: crosshair
    }

    .lh-chart .js-chart-hover {
      visibility: hidden
    }

    .lh-chart.lh-chart-on .js-chart-hover {
      visibility: visible
    }

    .lh-chart-tip {
      position: fixed;
      z-index: 70;
      pointer-events: none;
      min-width: 132px;
      padding: 8px 11px;
      border-radius: 10px;
      background: rgba(15, 23, 42, .95);
      color: #cbd5e1;
      font-size: 11px;
      line-height: 1.4;
      box-shadow: 0 10px 28px rgba(15, 23, 42, .28);
      opacity: 0;
      transform: translateY(3px);
      transition: opacity .12s ease, transform .12s ease
    }

    .lh-chart-tip.lh-chart-tip-on {
      opacity: 1;
      transform: translateY(0)
    }

    .lh-chart-tip-day {
      margin: 0 0 4px;
      font-weight: 700;
      color: #fff
    }

    .lh-chart-tip-row {
      margin: 2px 0 0;
      display: flex;
      align-items: center;
      gap: 6px
    }

    .lh-chart-tip-row i {
      flex: none;
      width: 8px;
      height: 8px;
      border-radius: 999px
    }

    .lh-chart-tip-row span {
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap
    }

    .lh-chart-tip-row b {
      margin-left: auto;
      padding-left: 12px;
      font-weight: 700;
      color: #fff
    }

    /* ============================================================
   Live class room (class_room.php) + its course-page banner.
   WHY PLAIN CSS: assets/tailwind.min.css is a PRECOMPILED Tailwind
   subset — it contains min-h-[560px] / text-[10px] but NOT
   h-[calc(100vh-230px)] or min-h-[460px]. The room asked for exactly
   those, so #lr-meeting had no height at all (auto = 0), the Jitsi
   iframe inside it collapsed to nothing and the meeting looked broken
   with no mic/camera bar. These rules always exist.
   ============================================================ */
    .lh-stage {
      position: relative;
      width: 100%;
      /* 100dvh (not vh) so the bottom toolbar never hides behind mobile
     browser chrome; the extra 245px leaves room for the page header,
     the room toolbar and the help strip above/below. */
      height: calc(100dvh - 245px);
      min-height: 460px;
      overflow: hidden;
      border-radius: .75rem;
      background: #0f172a
    }

    @supports not (height: 100dvh) {
      .lh-stage {
        height: calc(100vh - 245px)
      }
    }

    @media (max-width: 900px) {
      .lh-stage {
        height: 64vh;
        min-height: 340px
      }
    }

    /* keep the embedded meeting filling its box no matter what Jitsi sets */
    .lh-stage>iframe {
      position: absolute;
      inset: 0;
      width: 100% !important;
      height: 100% !important;
      border: 0
    }

    /* "Own window" mode: meet.jit.si refuses to be embedded (it hangs the call up
   after 5 minutes), so the room shows this launch panel instead of an iframe
   and the video runs in its own browser window (a fresh hour per unsigned
   meeting — the teacher signing in lifts that cap). */
    .lh-launch {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 14px;
      width: 100%;
      min-height: 420px;
      padding: 30px 20px;
      border-radius: .75rem;
      background: linear-gradient(160deg, #0f172a, #1e293b);
      color: #e2e8f0;
      text-align: center
    }

    .lh-launch-icon {
      font-size: 38px;
      line-height: 1
    }

    .lh-launch h2 {
      margin: 0;
      max-width: 620px;
      font-size: 18px;
      font-weight: 700;
      color: #fff
    }

    .lh-launch p {
      max-width: 660px;
      margin: 0;
      font-size: 13px;
      line-height: 1.75;
      color: #cbd5e1
    }

    a.lh-launch-go {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      min-height: 48px;
      padding: 12px 28px;
      border-radius: .75rem;
      background: #059669;
      color: #fff;
      font-size: 15px;
      font-weight: 700;
      text-decoration: none;
      box-shadow: 0 10px 24px rgba(5, 150, 105, .35)
    }

    a.lh-launch-go:hover {
      background: #047857
    }

    .lh-launch .lh-launch-alt {
      font-size: 11px;
      color: #94a3b8
    }

    .lh-launch .lh-launch-alt button {
      border: 0;
      padding: 0;
      background: none;
      color: #7dd3fc;
      font-size: 11px;
      font-weight: 600;
      text-decoration: underline;
      cursor: pointer
    }

    /* Shown when an embedded call drops (e.g. the 5-minute cut on meet.jit.si). */
    .lh-drop {
      margin-top: 12px;
      padding: 12px 16px;
      border-radius: .75rem;
      background: #fff7ed;
      box-shadow: 0 0 0 1px #fed7aa;
      font-size: 12px;
      line-height: 1.75;
      color: #9a3412
    }

    .lh-drop b {
      color: #7c2d12
    }

    .lh-drop a {
      color: #c2410c;
      font-weight: 700
    }

    /* Device notices (in-app-browser warning, phone "use the full page" hint). */
    .lh-inapp {
      margin-top: 12px;
      padding: 12px 16px;
      border-radius: .75rem;
      background: #fef2f2;
      box-shadow: 0 0 0 1px #fecaca;
      font-size: 12px;
      line-height: 1.75;
      color: #b91c1c
    }

    .lh-inapp b {
      color: #991b1b
    }

    .lh-inapp a,
    .lh-inapp button {
      border: 0;
      padding: 0;
      background: none;
      color: #dc2626;
      font-size: 12px;
      font-weight: 700;
      text-decoration: underline;
      cursor: pointer
    }

    /* Fingers need bigger targets than a mouse: bump every button in the room
   toolbar on touch devices (phones, tablets — not touch-screen laptops). */
    @media (pointer: coarse) {
      .lh-roombar>button {
        min-height: 40px;
        padding-top: 8px;
        padding-bottom: 8px
      }
    }

    @media (max-width: 900px) {
      .lh-launch {
        min-height: 340px;
        padding: 22px 14px
      }

      .lh-launch h2 {
        font-size: 16px
      }
    }

    /* pulsing red "live" dot (replaces bg-rose-500 + animate-ping, which are
   not in the compiled CSS either) */
    .lh-live-dot {
      position: relative;
      display: inline-flex;
      flex: none;
      height: .75rem;
      width: .75rem
    }

    .lh-live-dot>span:first-child {
      position: absolute;
      inset: 0;
      border-radius: 9999px;
      background: #fb7185;
      opacity: .75;
      animation: lh-ping 1.5s cubic-bezier(0, 0, .2, 1) infinite
    }

    .lh-live-dot>span:last-child {
      display: block;
      position: relative;
      height: .75rem;
      width: .75rem;
      border-radius: 9999px;
      background: #f43f5e
    }

    @keyframes lh-ping {

      75%,
      100% {
        transform: scale(2.1);
        opacity: 0
      }
    }

    /* roster avatar: `.h-6 .w-6` were missing from the compiled CSS, so the
   circles had no size and the initials rendered unstyled */
    .lh-avatar {
      display: grid;
      flex: none;
      place-items: center;
      height: 1.5rem;
      width: 1.5rem;
      border-radius: 9999px;
      background: #4f46e5;
      color: #fff;
      font-size: 10px;
      font-weight: 700
    }

    .lh-hand {
      margin-left: auto;
      color: #f59e0b
    }

    .lh-soft {
      font-weight: 400
    }

    .lh-chip-amber:hover {
      background: #fef3c7
    }

    /* browsers without dvh support keep the old viewport maths */
    @supports not (height: 100dvh) {
      .lh-panel {
        max-height: min(480px, calc(100vh - 84px))
      }

      .lx-panel-list {
        max-height: min(400px, calc(100vh - 148px))
      }
    }

    /* NOTE: the list/head classes really are `lx-…` in the markup (header.php and
   app.js) — the old rules said `.lh-panel-list`, which matched nothing, so the
   list had no overflow rule at all and long lists were simply clipped. */
    .lx-panel-list {
      flex: 1 1 auto;
      min-height: 0;
      max-height: min(400px, calc(100dvh - 148px));
      overflow-y: auto;
      overscroll-behavior: contain;
      -webkit-overflow-scrolling: touch
    }

    .lx-panel-head {
      flex: 0 0 auto
    }

    .lx-panel-item {
      display: flex;
      flex-direction: column;
      gap: 2px;
      padding: .65rem .85rem;
      border-bottom: 1px solid rgba(241, 245, 249, .8);
      cursor: pointer;
      text-decoration: none
    }

    .lx-panel-item:hover {
      background: #f6fbf8
    }

    /* An e-mail address or URL in a notification is one long unbreakable word —
       let it wrap instead of overflowing the sheet (the panel clips with
       overflow:hidden, so overflow would silently cut the text off). */
    .lx-panel-item,
    .lx-panel-item-title {
      min-width: 0;
      overflow-wrap: anywhere;
      word-break: break-word
    }

    /* ==== responsive rules BELOW every base panel rule ======================
       These must come last: a media query does not add specificity, so a base
       rule placed after the query would silently win by source order (that is
       why `.lx-panel-item`'s padding used to beat the phone override).
       Phones  : full-width sheet pinned 8px inside the viewport (never overflows)
       Desktop : dropdown anchored under the bell
       Both    : dvh (not vh) so mobile URL bars can never push it off-screen.
       ===================================================================== */

    @media (max-width: 639.98px) {

      /* `backdrop-filter` makes its element a containing block for position:fixed
     children, so drop the blur on phones — the sheet below then pins to the
     viewport itself instead of to the 60px topbar. Also cheaper to render.
     `!important` is required: `nav.glass` (line ~1161) and `.lh-topbar`
     (line ~1174) both set the background with `!important`, and an important
     declaration beats a non-important one no matter the specificity. */
      .lh-topbar.glass {
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
        background: rgba(255, 255, 255, .97) !important
      }

      .lh-panel {
        position: fixed;
        top: 66px;
        left: 8px;
        right: 8px;
        width: auto;
        max-height: calc(100dvh - 82px);
        border-radius: 16px
      }

      .lx-panel-list {
        max-height: calc(100dvh - 170px);
        padding-bottom: calc(.5rem + env(safe-area-inset-bottom, 0px))
      }

      /* comfy touch targets, and the sheet spans edge to edge */
      .lx-panel-item {
        padding: .8rem 1rem;
        min-height: 44px
      }

      /* Ultra-narrow phones (iPhone SE portrait, 320-360px): "Notifications" and
     "Mark all as read" sit on one row and would push each other out of the
     sheet. Let them wrap only when they actually run out of room. */
      .lx-panel-head {
        flex-wrap: wrap;
        row-gap: .25rem
      }
    }

    /* Landscape phones / very short windows: reclaim vertical space.
   NOTE: no `top` here. On desktop the panel is `position: absolute` and hangs
   from `top: calc(100% + 10px)` under the bell — overriding that to a viewport
   value would drop it out of the topbar. Only the heights need shrinking. */
    @media (max-height: 480px) {
      .lh-panel {
        max-height: calc(100dvh - 84px)
      }

      .lx-panel-list {
        max-height: calc(100dvh - 132px)
      }

      .lx-panel-item {
        padding: .55rem .85rem
      }
    }

    /* big desktop / large fonts: keep it comfortable, still never off-screen */
    @media (min-width: 1280px) {
      .lh-panel {
        width: min(400px, calc(100vw - 4rem))
      }
    }


    .lh-notif-unread {
      background: #ecfdf5
    }

    .lh-notif-unread:hover {
      background: #d1fae5
    }

    .lh-notif-unread::before {
      content: '';
      align-self: flex-start;
      width: 7px;
      height: 7px;
      border-radius: 99px;
      background: #059669;
      margin-bottom: 3px
    }

    .lh-notif-item {
      display: flex;
      flex-direction: column;
      gap: 1px
    }



    /* ============================================================
   

/* ---------- collapsible sidebar (icon rail + hover labels) ---------- */
    #lh-side-collapse {
      margin-top: 0;
      display: grid;
      place-items: center;
      width: 36px;
      height: 36px;
      border-radius: 10px;
      border: 1px solid rgba(226, 232, 240, .9);
      color: #64748b;
      background: #fff;
      cursor: pointer;
      transition: all .2s
    }

    #lh-side-collapse:hover {
      color: #047857;
      background: #ecfdf5;
      border-color: #a7f3d0
    }

    .lh-side-collapse-wrap {
      display: flex;
      justify-content: center;
      padding: .8rem 0;
      border-top: 1px solid rgba(226, 232, 240, .9)
    }

    @media(min-width:1024px) {
      #lh-rail-toggle {
        display: grid;
        visibility: visible
      }
    }

    @media(max-width:1023.98px) {
      #lh-rail-toggle {
        display: none
      }
    }

    @media(max-width:1023.98px) {
      .lh-side-collapse-wrap {
        display: none
      }
    }

    /* collapsed rail on desktop only */
    @media(min-width:1024px) {

      /* `html … !important` so the fold outranks BOTH a theme's own width
         pins AND density-compact.css' `.lh-sidebar{width:232px!important}`.
         Without it the rail keeps the full panel width while the label rules
         below already hide the text — an icon-only, 232px-wide sidebar that
         looks like it never closed (Console/Bright/Calm/Minimal + Compact).
         Themes that really want another rail width (material: 64px/88px,
         paper: 76px) still win from their own later sheet. */
      html body.lh-rail .lh-sidebar {
        width: 76px !important;
        overflow: visible
      }

      html body.lh-rail {
        padding-left: 76px !important
      }

      body.lh-rail .lh-side-head {
        justify-content: center;
        padding: .9rem .5rem .8rem
      }

      body.lh-rail .lh-side-head>div {
        display: none
      }

      /* wordmark (learn.png) hides on the collapsed rail — icon only */
      body.lh-rail .lh-side-head .lh-side-wordmark {
        display: none
      }

      body.lh-rail .lh-side-sec {
        display: none
      }

      body.lh-rail .lh-side-nav {
        padding: 0 .55rem
      }

      body.lh-rail .lh-side-link {
        justify-content: center;
        padding: .62rem .4rem;
        gap: 0
      }

      body.lh-rail .lh-side-link .lh-side-label {
        display: none
      }

      body.lh-rail .lh-side-foot {
        justify-content: center;
        padding: .8rem .4rem
      }

      body.lh-rail .lh-side-foot>span:not(:first-child) {
        display: none
      }

      body.lh-rail .lh-side-foot>a {
        display: none
      }

      body.lh-rail .lh-side-collapse-wrap {
        padding: 0 .4rem .8rem
      }

      body.lh-rail .lh-side-link .lh-badge {
        position: absolute;
        top: -2px;
        right: 2px
      }
    }

    /* hover tooltip on collapsed rail */
    .lh-side-link::after {
      content: attr(data-tip);
      position: absolute;
      left: calc(100% + 10px);
      top: 50%;
      transform: translateY(-50%);
      white-space: nowrap;
      background: #0f172a;
      color: #fff;
      font-size: 11px;
      font-weight: 700;
      padding: 5px 9px;
      border-radius: 8px;
      opacity: 0;
      pointer-events: none;
      transition: opacity .15s;
      box-shadow: 0 8px 20px -8px rgba(15, 23, 42, .4);
      z-index: 80
    }

    @media(min-width:1024px) {
      body.lh-rail .lh-side-link:hover::after {
        opacity: 1
      }
    }

    /* ============================================================
       Styled hover chip for icon-only controls — the same chip the
       folded rail uses. Text lives in data-tip; native title is
       omitted on purpose so the two never stack.
       ============================================================ */
    .lh-tip,
    .lh-side-tip {
      position: relative
    }

    .lh-tip::after,
    .lh-side-tip::after {
      content: attr(data-tip);
      position: absolute;
      white-space: nowrap;
      background: #0f172a;
      color: #fff;
      font-size: 11px;
      font-weight: 700;
      padding: 5px 9px;
      border-radius: 8px;
      opacity: 0;
      pointer-events: none;
      transition: opacity .15s;
      box-shadow: 0 8px 20px -8px rgba(15, 23, 42, .4);
      z-index: 80
    }

    /* default: under the icon, centered (top bar / page controls) */
    .lh-tip::after {
      left: 50%;
      top: calc(100% + 8px);
      transform: translateX(-50%)
    }

    /* right-edge icons: keep the chip inside the viewport */
    .lh-tip-end::after {
      left: auto;
      right: 0;
      transform: none
    }

    /* bottom-fixed icons (back-to-top): open upward */
    .lh-tip-up::after {
      top: auto;
      bottom: calc(100% + 8px)
    }

    /* inside the sidebar: open to the right, like the folded rail */
    .lh-side-tip::after {
      left: calc(100% + 10px);
      top: 50%;
      transform: translateY(-50%)
    }

    .lh-tip:hover::after,
    .lh-side-tip:hover::after {
      opacity: 1
    }
  </style>
  <?php $lh_theme_css = ui_theme_url(); ?>
  <?php if ($lh_theme_css !== ''): ?>
    <link rel="stylesheet" href="<?= e($lh_theme_css) ?>">
  <?php endif; ?>
  <?php $lh_density_css = $user ? ui_density_url() : ''; ?>
  <?php if ($lh_density_css !== ''): ?>
    <link rel="stylesheet" href="<?= e($lh_density_css) ?>">
  <?php endif; ?>
  <?php /* shell arrangement (Settings → Appearance → Layout) — loaded last so a
           layout's geometry outranks the theme's and the density layer's ties */ ?>
  <?php $lh_layout_css = $user ? ui_layout_url() : ''; ?>
  <?php if ($lh_layout_css !== ''): ?>
    <link rel="stylesheet" href="<?= e($lh_layout_css) ?>">
  <?php endif; ?>
  <?php lh_skeleton_css(); ?>
</head>

<body class="flex min-h-screen flex-col font-sans text-slate-800<?= $user ? ' lh-app' : '' ?>" <?= $user ? ' data-heartbeat="1"' : '' ?>>
  <?php lh_skeleton_body(); ?>
  <div id="lh-progress"></div>

  <?php if ($user): ?>
    <aside id="lh-sidebar" class="lh-sidebar" aria-label="Main navigation">
      <div class="lh-side-head">
        <a href="dashboard.php" title="LearnHub home" class="flex flex-row items-center justify-center">
          <img src="logo/logo.png" alt="LearnHub LMS" class="h-14 w-14 object-contain mb-5" />
          <img src="logo/learn.png" alt="" class="lh-side-wordmark w-[10rem] object-contain mb-5">
        </a>
      </div>

      <div class="lh-side-sec">Menu</div>
      <nav class="lh-side-nav">
        <?php if (($user['role'] ?? '') !== 'admin'): ?>
          <a href="dashboard.php" class="lh-side-link <?= $nav_active === 'dashboard' ? 'active' : '' ?>"
            data-tip="Dashboard"><span class="lh-side-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7.5" height="10" rx="1.6" />
                <rect x="13.5" y="3" width="7.5" height="6" rx="1.6" />
                <rect x="3" y="16" width="7.5" height="5" rx="1.6" />
                <rect x="13.5" y="12" width="7.5" height="9" rx="1.6" />
              </svg></span><span class="lh-side-label">Dashboard</span></a>
          <a href="courses.php" class="lh-side-link <?= $nav_active === 'courses' ? 'active' : '' ?>"
            data-tip="My Courses"><span class="lh-side-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <path
                  d="M12 6.5c-1.6-1.3-3.7-1.75-5.5-1.75H3.75v14h2.75c1.8 0 3.9.45 5.5 1.75 1.6-1.3 3.7-1.75 5.5-1.75h2.75v-14H17.5c-1.8 0-3.9.45-5.5 1.75zM12 6.5v14" />
              </svg></span><span class="lh-side-label">My Courses</span></a>
          <a href="enrollments.php" class="lh-side-link <?= $nav_active === 'enrollments' ? 'active' : '' ?>"
            data-tip="Enrollments"><span class="lh-side-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <path d="M16 21v-1.5a3.75 3.75 0 0 0-3.75-3.75h-4.5A3.75 3.75 0 0 0 4 19.5V21" />
                <circle cx="8.25" cy="8.25" r="3" />
                <path d="M16 11.25a3 3 0 1 0-1.35 5.64" />
              </svg></span><span class="lh-side-label">Enrollments</span></a>
          <a href="attendance_day.php" class="lh-side-link <?= $nav_active === 'attendance' ? 'active' : '' ?>"
            data-tip="Attendance"><span class="lh-side-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4.5" width="18" height="16.5" rx="2" />
                <path d="M3 9.5h18M8 3v4M16 3v4" />
                <path d="M9 14.25l2 2 4-3.75" />
              </svg></span><span class="lh-side-label">Attendance</span></a>
        <?php endif; ?>
        <?php if (($user['role'] ?? '') === 'teacher'): ?>
          <a href="codes.php" class="lh-side-link <?= $nav_active === 'codes' ? 'active' : '' ?>"
            data-tip="Invite codes"><span class="lh-side-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14.5 3.5a7 7 0 0 0-6.7 9.3L3.5 17v3.5H7L15 12.5a7 7 0 1 0-.5-9z" />
                <circle cx="16.5" cy="7.5" r="1.8" />
              </svg></span><span class="lh-side-label">Invite codes</span></a>
        <?php endif; ?>
        <?php if (($user['role'] ?? '') === 'admin'): ?>
          <a href="admin.php" class="lh-side-link <?= $nav_active === 'admin' ? 'active' : '' ?>" data-tip="Admin"><span
              class="lh-side-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 3l7.5 3v5.4c0 4.6-3.1 8.2-7.5 9.6-4.4-1.4-7.5-5-7.5-9.6V6z" />
                <path d="M9.2 12.1l2 2 3.6-3.9" />
              </svg></span><span class="lh-side-label">Admin</span></a>
          <a href="settings.php" class="lh-side-link <?= $nav_active === 'settings' ? 'active' : '' ?>"
            data-tip="Settings"><span class="lh-side-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="3.2" />
                <path
                  d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1.03 1.56V21a2 2 0 1 1-4 0v-.06A1.7 1.7 0 0 0 8.9 19.3a1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.56-1.03H3a2 2 0 1 1 0-4h.06A1.7 1.7 0 0 0 4.6 8.9a1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 9 4.6h.08A1.7 1.7 0 0 0 10.1 3.04V3a2 2 0 1 1 4 0v.06a1.7 1.7 0 0 0 1.03 1.56 1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.7 1.7 0 0 0-.34 1.87v.08a1.7 1.7 0 0 0 1.56 1.03H21a2 2 0 1 1 0 4h-.06A1.7 1.7 0 0 0 19.4 15z" />
              </svg></span><span class="lh-side-label">Settings</span></a>
        <?php endif; ?>
        <?php if (($user['role'] ?? '') !== 'admin'): ?>
          <a href="<?= ($user['role'] ?? '') === 'teacher' ? 'quiz_records.php' : 'my_records.php' ?>"
            class="lh-side-link <?= $nav_active === 'records' ? 'active' : '' ?>" data-tip="Quiz Records"><span
              class="lh-side-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 20V10M10 20V4M16 20v-7" />
              </svg></span><span
              class="lh-side-label"><?= ($user['role'] ?? '') === 'teacher' ? 'Student Records' : 'My Progress' ?></span></a>
        <?php endif; ?>
      </nav>

      <?php if (($user['role'] ?? '') !== 'admin'): ?>
        <div class="lh-side-sec">Communication</div>
        <nav class="lh-side-nav">
          <a href="messages.php" class="lh-side-link <?= $nav_active === 'messages' ? 'active' : '' ?>" data-tip="Messages"
            id="lh-side-chat">
            <span class="lh-side-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 11.5a8.5 8.5 0 0 1-12.4 7.5L3 21l2-5.6A8.5 8.5 0 1 1 21 11.5z" />
              </svg></span><span class="lh-side-label flex-1">Messages</span>
            <span id="lh-chat-badge-side" class="lh-badge hidden">0</span>
          </a>
        </nav>
      <?php endif; ?>

      <div class="lh-side-collapse-wrap"><button id="lh-side-collapse" type="button" class="lh-side-tip"
          data-tip="Close sidebar" aria-label="Close sidebar">«</button></div>
      <div class="lh-side-foot">
        <span
          class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-emerald-600 text-sm font-bold text-white"><?= e(strtoupper(substr((string) $user['name'], 0, 1))) ?></span>
        <span class="min-w-0 flex-1 text-left leading-tight">
          <span class="block truncate text-sm font-semibold text-slate-800"><?= e((string) $user['name']) ?></span>
          <span
            class="block truncate text-[10px] uppercase tracking-wide text-slate-400"><?= ($user['role'] ?? '') === 'admin' ? 'Main Admin' : (($user['role'] ?? '') === 'teacher' ? 'Teacher' : 'Student') ?></span>
        </span>
        <a href="logout.php" class="lh-ico lh-side-tip grid h-9 w-9 place-items-center rounded-lg border border-slate-200 text-slate-500"
          data-tip="Log out" aria-label="Log out">
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9" />
          </svg>
        </a>
      </div>
    </aside>
    <div id="lh-side-backdrop" class="lh-backdrop" aria-hidden="true"></div>
  <?php endif; ?>
  <nav class="glass lh-topbar sticky top-0 z-40 border-b border-white/60" aria-label="Primary">
    <div class="lh-top-inner">
      <div class="flex min-w-0 items-center gap-1.5">
        <?php if ($user): ?>
          <button id="lh-rail-toggle" type="button" data-tip="Close sidebar" aria-label="Close sidebar"
            class="lh-ico lh-tip grid h-7 w-7 place-items-center rounded-lg text-slate-600"><svg width="16" height="16"
              viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" data-safe-chroma="true">
              <path fill-rule="evenodd" clip-rule="evenodd"
                d="M9.67272 0.522841C10.8339 0.522841 11.76 0.522714 12.4963 0.602493C13.2453 0.683657 13.8789 0.854248 14.4264 1.25197C14.7504 1.48739 15.0355 1.77247 15.2709 2.0965C15.6686 2.64394 15.8392 3.27758 15.9204 4.02655C16.0002 4.7629 16 5.68895 16 6.85014V9.14986C16 10.3111 16.0002 11.2371 15.9204 11.9735C15.8392 12.7224 15.6686 13.3561 15.2709 13.9035C15.0355 14.2275 14.7504 14.5126 14.4264 14.748C13.8789 15.1458 13.2453 15.3163 12.4963 15.3975C11.76 15.4773 10.8339 15.4772 9.67272 15.4772H6.3273C5.16611 15.4772 4.24006 15.4773 3.50371 15.3975C2.75474 15.3163 2.1211 15.1458 1.57366 14.748C1.24963 14.5126 0.964549 14.2275 0.729131 13.9035C0.331407 13.3561 0.160817 12.7224 0.0796529 11.9735C-0.000126137 11.2371 1.25338e-09 10.3111 1.25338e-09 9.14986V6.85014C1.25329e-09 5.68895 -0.000126137 4.7629 0.0796529 4.02655C0.160817 3.27758 0.331407 2.64394 0.729131 2.0965C0.964549 1.77247 1.24963 1.48739 1.57366 1.25197C2.1211 0.854248 2.75474 0.683657 3.50371 0.602493C4.24006 0.522714 5.16611 0.522841 6.3273 0.522841H9.67272ZM5.54303 1.88715V14.1118C5.78636 14.1128 6.04709 14.1169 6.3273 14.1169H9.67272C10.8639 14.1169 11.7032 14.1164 12.3493 14.0465C12.9824 13.9779 13.3497 13.8494 13.6268 13.6482C13.8354 13.4966 14.0195 13.3125 14.1711 13.1039C14.3723 12.8268 14.5007 12.4595 14.5693 11.8264C14.6393 11.1803 14.6398 10.341 14.6398 9.14986V6.85014C14.6398 5.65896 14.6393 4.81967 14.5693 4.1736C14.5007 3.54048 14.3723 3.17318 14.1711 2.89609C14.0195 2.68747 13.8354 2.50337 13.6268 2.35179C13.3497 2.1506 12.9824 2.02212 12.3493 1.95353C11.7032 1.88358 10.8639 1.88307 9.67272 1.88307H6.3273C6.04709 1.88307 5.78636 1.8862 5.54303 1.88715ZM4.1828 1.91166C3.99125 1.9216 3.8148 1.93577 3.65076 1.95353C3.01764 2.02212 2.65034 2.1506 2.37325 2.35179C2.16463 2.50337 1.98052 2.68747 1.82895 2.89609C1.62776 3.17318 1.49928 3.54048 1.43069 4.1736C1.36074 4.81967 1.36023 5.65896 1.36023 6.85014V9.14986C1.36023 10.341 1.36074 11.1803 1.43069 11.8264C1.49928 12.4595 1.62776 12.8268 1.82895 13.1039C1.98052 13.3125 2.16463 13.4966 2.37325 13.6482C2.65034 13.8494 3.01764 13.9779 3.65076 14.0465C3.81478 14.0642 3.99127 14.0774 4.1828 14.0873V1.91166Z"
                fill="currentColor"></path>
            </svg></button>
          <button id="lh-side-toggle" class="lh-ico lh-tip grid h-7 w-7 place-items-center rounded-lg text-slate-600 lg:hidden"
            data-tip="Open menu" aria-label="Open menu">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"
              data-safe-chroma="true">
              <path fill-rule="evenodd" clip-rule="evenodd"
                d="M9.67272 0.522841C10.8339 0.522841 11.76 0.522714 12.4963 0.602493C13.2453 0.683657 13.8789 0.854248 14.4264 1.25197C14.7504 1.48739 15.0355 1.77247 15.2709 2.0965C15.6686 2.64394 15.8392 3.27758 15.9204 4.02655C16.0002 4.7629 16 5.68895 16 6.85014V9.14986C16 10.3111 16.0002 11.2371 15.9204 11.9735C15.8392 12.7224 15.6686 13.3561 15.2709 13.9035C15.0355 14.2275 14.7504 14.5126 14.4264 14.748C13.8789 15.1458 13.2453 15.3163 12.4963 15.3975C11.76 15.4773 10.8339 15.4772 9.67272 15.4772H6.3273C5.16611 15.4772 4.24006 15.4773 3.50371 15.3975C2.75474 15.3163 2.1211 15.1458 1.57366 14.748C1.24963 14.5126 0.964549 14.2275 0.729131 13.9035C0.331407 13.3561 0.160817 12.7224 0.0796529 11.9735C-0.000126137 11.2371 1.25338e-09 10.3111 1.25338e-09 9.14986V6.85014C1.25329e-09 5.68895 -0.000126137 4.7629 0.0796529 4.02655C0.160817 3.27758 0.331407 2.64394 0.729131 2.0965C0.964549 1.77247 1.24963 1.48739 1.57366 1.25197C2.1211 0.854248 2.75474 0.683657 3.50371 0.602493C4.24006 0.522714 5.16611 0.522841 6.3273 0.522841H9.67272ZM5.54303 1.88715V14.1118C5.78636 14.1128 6.04709 14.1169 6.3273 14.1169H9.67272C10.8639 14.1169 11.7032 14.1164 12.3493 14.0465C12.9824 13.9779 13.3497 13.8494 13.6268 13.6482C13.8354 13.4966 14.0195 13.3125 14.1711 13.1039C14.3723 12.8268 14.5007 12.4595 14.5693 11.8264C14.6393 11.1803 14.6398 10.341 14.6398 9.14986V6.85014C14.6398 5.65896 14.6393 4.81967 14.5693 4.1736C14.5007 3.54048 14.3723 3.17318 14.1711 2.89609C14.0195 2.68747 13.8354 2.50337 13.6268 2.35179C13.3497 2.1506 12.9824 2.02212 12.3493 1.95353C11.7032 1.88358 10.8639 1.88307 9.67272 1.88307H6.3273C6.04709 1.88307 5.78636 1.8862 5.54303 1.88715ZM4.1828 1.91166C3.99125 1.9216 3.8148 1.93577 3.65076 1.95353C3.01764 2.02212 2.65034 2.1506 2.37325 2.35179C2.16463 2.50337 1.98052 2.68747 1.82895 2.89609C1.62776 3.17318 1.49928 3.54048 1.43069 4.1736C1.36074 4.81967 1.36023 5.65896 1.36023 6.85014V9.14986C1.36023 10.341 1.36074 11.1803 1.43069 11.8264C1.49928 12.4595 1.62776 12.8268 1.82895 13.1039C1.98052 13.3125 2.16463 13.4966 2.37325 13.6482C2.65034 13.8494 3.01764 13.9779 3.65076 14.0465C3.81478 14.0642 3.99127 14.0774 4.1828 14.0873V1.91166Z"
                fill="currentColor"></path>
            </svg>
          </button>
        <?php endif; ?>
        <a href="<?= $user ? 'dashboard.php' : 'index.php' ?>" class="lh-topword  items-center gap-2 lg:flex">
          <img src="logo/2ndlogo.png" alt="LearnHub LMS" class="h-14 w-14 object-contain" />
        </a>

      </div>


      <div class="flex items-center gap-1.5">
        <?php if ($user): ?>
          <div class="relative">
            <button id="lh-notif-btn" class="lh-ico lh-tip grid h-9 w-9 place-items-center rounded-lg text-slate-600"
              data-tip="Notifications" aria-label="Notifications">
              <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                  d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
              </svg>
              <?php $lh_unread = unread_notification_count((int) $user['id']); /* true unread count at first paint */ ?>
              <span id="lh-notif-badge"
                class="lh-badge <?= $lh_unread > 0 ? '' : 'hidden' ?>"><?= $lh_unread > 99 ? '99+' : $lh_unread ?></span>
            </button>
            <div id="lh-notif-panel" class="lh-panel hidden" aria-label="Notifications">
              <div class="lx-panel-head flex items-center justify-between gap-2 border-b border-slate-200 px-3.5 py-2.5">
                <span class="text-sm font-bold text-slate-800">🔔 Notifications</span>
                <button id="lh-notif-read-all" type="button"
                  class="text-xs font-semibold text-emerald-600 hover:underline">Mark
                  all as read</button>
              </div>
              <div id="lh-notif-list" class="lx-panel-list">
                <?php $lh_notifs = notifications_for((int) $user['id'], 8); ?>
                <?php if (!$lh_notifs): ?>
                  <p class="px-3.5 py-6 text-center text-xs text-slate-400">No notifications yet.<br>New lessons, quizzes,
                    results
                    and messages will show up here.</p>
                <?php else:
                  foreach ($lh_notifs as $n):
                    $lh_ago = time() - (int) $n['created_at'];
                    $lh_agoTxt = $lh_ago < 60 ? 'just now' : ($lh_ago < 3600 ? (int) floor($lh_ago / 60) . 'm ago' : ($lh_ago < 86400 ? (int) floor($lh_ago / 3600) . 'h ago' : (int) floor($lh_ago / 86400) . 'd ago')); ?>
                    <a href="<?= e((string) $n['link']) ?>" data-notif-id="<?= (int) $n['id'] ?>"
                      data-notif-link="<?= e((string) $n['link']) ?>"
                      class="lx-panel-item lh-notif-item <?= $n['is_read'] ? '' : 'lh-notif-unread' ?>">
                      <span
                        class="lx-panel-item-title text-[13px] font-semibold text-slate-800"><?= e((string) $n['title']) ?></span>
                      <?php if ($n['body']): ?><span
                          class="text-xs text-slate-500"><?= e((string) $n['body']) ?></span><?php endif; ?>
                      <span class="text-[10px] text-slate-400"><?= e($lh_agoTxt) ?></span>
                    </a>
                  <?php endforeach; endif; ?>
              </div>
            </div>
          </div>
          <a href="messages.php" id="lh-chat-link" data-tip="Messages"
            class="lh-ico lh-tip grid h-9 w-9 place-items-center rounded-lg text-slate-600" aria-label="Messages">
            <span class="lh-side-ico"><svg viewBox="0 0 22 22" fill="none" stroke="currentColor" stroke-width="1.7"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 11.5a8.5 8.5 0 0 1-12.4 7.5L3 21l2-5.6A8.5 8.5 0 1 1 21 11.5z" />
              </svg></span>
            <span id="lh-chat-badge" class="lh-badge hidden">0</span>
          </a>
          <span class="hidden text-right sm:flex sm:flex-col sm:items-start leading-tight">
            <span class="text-sm font-semibold leading-4 text-slate-800"><?= e((string) $user['name']) ?></span>
            <span
              class="text-[10px] font-semibold uppercase tracking-wide text-slate-400"><?= ($user['role'] ?? '') === 'admin' ? 'Admin' : (($user['role'] ?? '') === 'teacher' ? 'Teacher' : 'Student') ?></span>
          </span>
          <span
            class="grid h-9 w-9 place-items-center rounded-full bg-emerald-600 text-sm font-bold text-white"><?= e(strtoupper(substr((string) $user['name'], 0, 1))) ?></span>
          <a href="logout.php" title="Log out" aria-label="Log out"
            class="hidden rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 sm:inline-block">Log
            out</a>
          <a href="logout.php" data-tip="Log out" aria-label="Log out"
            class="lh-tip lh-tip-end grid h-9 w-9 place-items-center rounded-lg border border-slate-200 text-slate-500 sm:hidden">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9" />
            </svg>
          </a>
        <?php else: ?>
          <a href="login.php" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Log
            in</a>
          <a href="register.php"
            class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Get
            started</a>
        <?php endif; ?>
      </div>
    </div>
  </nav>

  <?php foreach ($flashes as $f): ?>
    <div data-toast
      class="toast-in fixed right-4 top-20 z-50 flex max-w-sm items-start gap-3 rounded-xl border p-4 shadow-lg <?= ($f['type'] ?? '') === 'error' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' ?>">
      <span><?= ($f['type'] ?? '') === 'error' ? '⚠️' : '✅' ?></span>
      <p class="text-sm font-medium"><?= e((string) ($f['msg'] ?? '')) ?></p>
      <button data-toast-close data-tip="Dismiss" aria-label="Dismiss" class="lh-tip lh-tip-end ml-2 text-slate-400 hover:text-slate-600">✕</button>
    </div>
  <?php endforeach; ?>

  <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8<?= $user ? ' lh-main' : '' ?>">