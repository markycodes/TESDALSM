<?php
require_once __DIR__ . '/lib.php';
$user = current_user();
$page_title = $page_title ?? 'LearnHub';
$nav_active = $nav_active ?? '';
$flashes = take_flashes();
if ($user) {
  touch_presence((int) $user['id']);
} // keep the heartbeat fresh on every page view
?><!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf" content="<?= e(csrf_token()) ?>">
  <?php if (!empty($attendance_course)): ?>
    <meta name="attendance-course" content="<?= (int) $attendance_course ?>"><?php endif; ?>
  <title><?= e($page_title) ?> · LearnHub LMS</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
            display: ['Fraunces', 'Georgia', 'Times New Roman', 'serif']
          }
        }
      }
    };
  </script>
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

    main>section.bg-gradient-to-r::before: ; {
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

    .lh-panel {
      position: absolute;
      top: calc(100% + 10px);
      right: 0;
      z-index: 60;
      width: min(360px, calc(100vw - 16px));
      max-height: min(480px, calc(100vh - 70px));
      overflow: hidden;
      border-radius: 18px;
      border: 1px solid rgba(226, 232, 240, .9);
      background: #fff;
      box-shadow: 0 28px 60px -24px rgba(4, 120, 87, .28)
    }

    .lh-panel-list {
      max-height: min(400px, calc(100vh - 130px));
      overflow-y: auto
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
      body.lh-rail .lh-sidebar {
        width: 76px;
        overflow: visible
      }

      body.lh-rail {
        padding-left: 76px
      }

      body.lh-rail .lh-side-head {
        justify-content: center;
        padding: .9rem .5rem .8rem
      }

      body.lh-rail .lh-side-head>div {
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
   LearnHub v3 — editorial studio · deep forest green
   Appended overrides. Functional rules above are preserved;
   these only re-skin surfaces, type and motion.
   ============================================================ */
    :root {
      --lh-ink: #12211a;
      /* primary editorial ink */
      --lh-body: #42534a;
      /* body text */
      --lh-mut: #7d8a82;
      /* muted text */
      --lh-line: #e3e9e4;
      /* hairline */
      --lh-paper: #f4f7f4;
      /* page canvas */
      --lh-deep: #065f46;
      /* deep forest green */
      --lh-brand: #047857;
      /* emerald-700 */
      --lh-mint: #10b981;
      /* emerald-500 */
      --lh-tint: #ecfdf5;
      /* soft green tint */
      --lh-grad: linear-gradient(135deg, #065f46 0%, #047857 52%, #10b981 100%);
      --lh-display: 'Fraunces', Georgia, 'Times New Roman', serif;
      --lh-shadow: 0 1px 2px rgba(17, 33, 26, .05), 0 14px 30px -22px rgba(17, 33, 26, .18);
    }

    /* ---- type with personality ---- */
    main h1 {
      font-family: var(--lh-display);
      font-weight: 600;
      letter-spacing: -.02em;
      line-height: 1.12;
      color: var(--lh-ink)
    }

    .lh-display {
      font-family: var(--lh-display) !important;
      font-weight: 600;
      letter-spacing: -.02em;
      color: var(--lh-ink)
    }

    .lh-kicker {
      font-size: 11px;
      font-weight: 700;
      letter-spacing: .16em;
      text-transform: uppercase;
      color: var(--lh-brand)
    }

    .lh-num {
      font-family: var(--lh-display);
      font-weight: 650;
      letter-spacing: -.02em
    }

    /* ---- canvas: calm paper, one restrained top glow ---- */
    body {
      background: radial-gradient(900px 420px at 82% -120px, rgba(4, 120, 87, .09), transparent 62%), var(--lh-paper) !important;
    }

    .lh-app {
      background: var(--lh-paper)
    }

    /* ---- topbar: clean hairline, soft blur ---- */
    nav.glass {
      background: rgba(255, 255, 255, .82) !important;
      backdrop-filter: blur(10px) saturate(1.2);
      -webkit-backdrop-filter: blur(10px) saturate(1.2);
      box-shadow: 0 1px 0 var(--lh-line), 0 12px 30px -24px rgba(17, 33, 26, .22) !important
    }

    nav.glass::after {
      height: 1px;
      background: linear-gradient(90deg, transparent, rgba(4, 120, 87, .35), transparent);
      opacity: .7
    }

    .lh-topbar {
      background: rgba(255, 255, 255, .82) !important;
      border-bottom: 1px solid var(--lh-line)
    }

    /* ---- logo: keep brand mark, calm pulse ---- */
    .lh-logo {
      background: var(--lh-grad);
      box-shadow: 0 6px 16px -8px rgba(4, 120, 87, .5);
      transition: transform .2s
    }

    .lh-logo::before {
      filter: blur(12px);
      opacity: .28;
      animation: logoPulse 4s ease-in-out infinite
    }

    /* ---- sidebar: warm white, editorial spacing ---- */
    .lh-sidebar {
      background: #fcfdfb !important;
      border-right: 1px solid var(--lh-line);
      box-shadow: none
    }

    .lh-side-head {
      border-bottom: 1px solid var(--lh-line);
      padding: 1rem 1.1rem .95rem
    }

    .lh-side-sec {
      color: var(--lh-mut)
    }

    .lh-side-link {
      color: var(--lh-body);
      padding: .56rem .8rem;
      border-radius: 10px
    }

    .lh-side-link:hover {
      background: var(--lh-tint);
      color: var(--lh-deep)
    }

    .lh-side-link.active {
      background: var(--lh-tint);
      color: var(--lh-deep);
      box-shadow: inset 0 0 0 1px rgba(4, 120, 87, .14)
    }

    .lh-side-link.active::before {
      left: -.65rem;
      top: 18%;
      bottom: 18%;
      width: 3px;
      border-radius: 99px;
      background: var(--lh-deep)
    }

    .lh-side-ico {
      width: 22px;
      height: 20px;
      display: grid;
      place-items: center
    }

    .lh-side-ico svg {
      width: 20px;
      height: 20px
    }

    .lh-side-foot {
      border-top: 1px solid var(--lh-line)
    }

    /* ---- buttons: solid deep green, 1px ring, quiet depth ---- */
    main a.bg-indigo-600,
    main button.bg-indigo-600,
    nav a.px-4.bg-indigo-600 {
      background: var(--lh-deep) !important;
      background-image: none !important;
      box-shadow: 0 0 0 1px rgba(4, 120, 87, .55), 0 10px 22px -16px rgba(4, 120, 87, .55) !important;
      border-radius: 10px;
    }

    main a.bg-indigo-600:hover,
    main button.bg-indigo-600:hover,
    nav a.px-4.bg-indigo-600:hover {
      background: #074f3c !important;
      filter: none !important;
      transform: translateY(-1px);
      box-shadow: 0 0 0 1px rgba(4, 120, 87, .5), 0 14px 26px -16px rgba(4, 120, 87, .6) !important;
    }

    /* ---- cards: flat paper + hairline, not glassy rings ---- */
    main .bg-white.ring-1,
    main .rounded-2xl.bg-white,
    main .rounded-3xl.bg-white {
      background: #fff !important;
      backdrop-filter: none !important;
      -webkit-backdrop-filter: none !important;
      box-shadow: var(--lh-shadow) !important;
    }

    main .rounded-2xl.bg-white.ring-1:hover,
    main .rounded-3xl.bg-white.ring-1:hover {
      box-shadow: 0 2px 3px rgba(17, 33, 26, .05), 0 20px 38px -24px rgba(4, 120, 87, .28) !important
    }

    /* ---- progress bars: calm gradient, no shimmer ---- */
    main .h-2>.h-full,
    main .h-2\.5>.h-full,
    main .h-3>.h-full {
      background: linear-gradient(90deg, var(--lh-deep), var(--lh-mint)) !important
    }

    main .h-2>.h-full::after,
    main .h-2\.5>.h-full::after,
    main .h-3>.h-full::after {
      content: '';
      background: linear-gradient(180deg, rgba(255, 255, 255, .35), transparent 60%);
      animation: none
    }

    /* ---- hero panels: static layered green, no floating blobs ---- */
    main>section.bg-gradient-to-r {
      background-size: 100% 100% !important;
      animation: none !important
    }

    main>section.bg-gradient-to-r::before {
      display: none !important
    }

    /* ---- tables: hairline rows, small-caps headers ---- */
    main table {
      width: 100%;
      border-collapse: collapse
    }

    main table thead th {
      font-size: 10px;
      font-weight: 700;
      letter-spacing: .13em;
      text-transform: uppercase;
      color: var(--lh-mut);
      text-align: left
    }

    main table tbody tr {
      border-bottom: 1px solid #edf1ee
    }

    main table tbody tr:last-child {
      border-bottom: none
    }

    main table tbody tr:hover {
      background: #f7faf7
    }

    /* ---- form controls: calmer focus ---- */
    main input:focus,
    main select:focus,
    main textarea:focus {
      border-color: var(--lh-mint);
      box-shadow: 0 0 0 3px rgba(16, 185, 129, .16)
    }

    @media (prefers-reduced-motion:reduce) {
      main>section.bg-gradient-to-r {
        animation: none !important
      }

      main>section.bg-gradient-to-r::before {
        display: none !important
      }

      .lh-logo::before {
        animation: none
      }
    }

    /* ============================================================
   LearnHub v3 addendum — indigo→green remap + hero texture
   ============================================================ */
    main .bg-indigo-50 {
      background: #ecfdf5 !important
    }

    main .bg-indigo-100 {
      background: #d1fae5 !important
    }

    main .bg-indigo-200,
    main .bg-indigo-200\/20 {
      background: #a7f3d0 !important
    }

    main .bg-indigo-600,
    main .bg-indigo-700 {
      background: var(--lh-deep) !important;
      background-image: none !important
    }

    main .text-indigo-400,
    main .text-indigo-500,
    main .text-indigo-600,
    main .text-indigo-700,
    main .text-indigo-800 {
      color: var(--lh-brand) !important
    }

    main .ring-indigo-200,
    main .ring-indigo-300 {
      border-color: #a7f3d0 !important
    }

    /* Hero panel: a paper sheet taped to the wall (green kept on tape + CTA) */
    .lh-hero {
      position: relative;
      background:
        radial-gradient(130% 100% at 18% 0%, rgba(255, 255, 255, .55), transparent 55%),
        repeating-linear-gradient(0deg, rgba(23, 52, 40, .035) 0 1px, transparent 1px 3px),
        repeating-linear-gradient(90deg, rgba(23, 52, 40, .026) 0 1px, transparent 1px 4px),
        linear-gradient(180deg, #eef3ee 0%, #e2ebe3 100%);
      border: 1px solid #d3e0d4;
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, .65), 0 20px 44px -26px rgba(23, 46, 36, .4);
    }

    main>section.lh-hero {
      animation: none !important
    }

    main>section.lh-hero::before {
      display: none !important
    }

    /* the paper sheet: cream, faint rules, slight tilt, floats off the wall */
    .lh-hero-paper {
      background:
        linear-gradient(rgba(4, 63, 46, .045) 1px, transparent 1px) 0 0/100% 30px,
        linear-gradient(180deg, #fdfbf5 0%, #f7f3e8 100%);
      border: 1px solid #e6dfcd;
      border-radius: 3px;
      box-shadow:
        inset 0 1px 0 #fff,
        0 1px 2px rgba(31, 41, 33, .1),
        0 3px 6px rgba(31, 41, 33, .08),
        0 24px 40px -18px rgba(31, 41, 33, .38);
      transform: rotate(-1.1deg);
      transition: transform .25s ease;
    }

    .lh-hero-paper::after {
      content: '';
      position: absolute;
      inset: 0;
      pointer-events: none;
      background: repeating-linear-gradient(45deg, rgba(31, 61, 48, .02) 0 2px, transparent 2px 6px);
    }

    .lh-hero:hover .lh-hero-paper {
      transform: rotate(-.35deg)
    }

    /* tape strips: torn-ended washi tape in the brand green */
    .lh-tape {
      position: absolute;
      width: 104px;
      height: 27px;
      z-index: 2;
      background: linear-gradient(180deg, rgba(209, 244, 227, .85), rgba(154, 222, 190, .68) 55%, rgba(129, 209, 175, .72));
      box-shadow: 0 1px 3px rgba(31, 41, 33, .22), inset 0 0 0 1px rgba(255, 255, 255, .25);
      clip-path: polygon(3% 8%, 97% 0, 100% 92%, 1% 100%);
      opacity: .92;
    }

    .lh-tape-l {
      top: -14px;
      left: 30px;
      transform: rotate(-7deg)
    }

    .lh-tape-r {
      top: -12px;
      right: 34px;
      transform: rotate(5deg)
    }

    .lh-tape-b {
      bottom: -14px;
      left: 50%;
      margin-left: -52px;
      transform: rotate(1.6deg)
    }

    .lh-hero-cta {
      box-shadow: 0 10px 22px -10px rgba(4, 120, 87, .55)
    }

    @media (max-width:640px) {
      .lh-hero-paper {
        transform: rotate(-.7deg)
      }

      .lh-tape {
        width: 78px;
        height: 22px
      }

      .lh-tape-l {
        top: -12px;
        left: 18px
      }

      .lh-tape-r {
        top: -10px;
        right: 20px
      }

      .lh-tape-b {
        bottom: -12px;
        margin-left: -39px
      }
    }

    @media (prefers-reduced-motion:reduce) {

      .lh-hero-paper,
      .lh-hero:hover .lh-hero-paper {
        transition: none;
        transform: rotate(-.7deg)
      }
    }

    /* Landing feature rows: hairline dividers, quiet hover */
    .lh-feature-row {
      transition: background-color .18s
    }

    .lh-feature-row:hover {
      background: #f7faf7
    }

    /* Stats band: hairline separators */
    .lh-band {
      border-left: 1px solid #edf1ee
    }

    .lh-band:first-child {
      border-left: none
    }

    /* ============================================================
   Paper-on-wall theme — SITE-WIDE, uniform (green brand kept)
   ============================================================ */
    body.lh-app {
      background:
        radial-gradient(120% 90% at 15% 0%, rgba(255, 255, 255, .5), transparent 55%),
        repeating-linear-gradient(0deg, rgba(23, 52, 40, .03) 0 1px, transparent 1px 3px),
        repeating-linear-gradient(90deg, rgba(23, 52, 40, .022) 0 1px, transparent 1px 4px),
        linear-gradient(180deg, #eef3ee 0%, #e3ece4 100%) fixed;
    }

    /* every white card becomes a paper sheet with a green tape strip */
    main .rounded-2xl.bg-white,
    footer .rounded-2xl.bg-white {
      position: relative;
      background:
        linear-gradient(rgba(4, 63, 46, .04) 1px, transparent 1px) 0 0/100% 28px,
        linear-gradient(180deg, #fdfbf5 0%, #f8f4e9 100%) !important;
      border-color: #e6dfcd !important;
      border-radius: 3px !important;
      box-shadow: inset 0 1px 0 #fff, 0 1px 2px rgba(31, 41, 33, .1), 0 3px 6px rgba(31, 41, 33, .08), 0 20px 34px -18px rgba(31, 41, 33, .34) !important;
      rotate: -.45deg;
      transition: rotate .25s ease;
    }

    main .rounded-2xl.bg-white::before {
      content: '';
      position: absolute;
      top: -11px;
      left: 50%;
      margin-left: -44px;
      width: 88px;
      height: 20px;
      z-index: 3;
      background: linear-gradient(180deg, rgba(209, 244, 227, .9), rgba(154, 222, 190, .72) 55%, rgba(129, 209, 175, .75));
      box-shadow: 0 1px 3px rgba(31, 41, 33, .2), inset 0 0 0 1px rgba(255, 255, 255, .3);
      clip-path: polygon(3% 8%, 97% 0, 100% 92%, 1% 100%);
      opacity: .92;
      pointer-events: none;
    }

    main .rounded-2xl.bg-white:hover {
      rotate: -.15deg
    }

    main .rounded-2xl.bg-white .text-slate-400 {
      color: #8b8672 !important
    }

    main .rounded-2xl.bg-white .text-slate-500 {
      color: #6f6a58 !important
    }

    /* modals: paper but straight + centered, no tape */
    .modal-backdrop .bg-white {
      rotate: 0deg !important;
      border-radius: 10px !important;
    }

    .modal-backdrop .bg-white::before {
      display: none
    }

    @media (max-width:640px) {
      main .rounded-2xl.bg-white {
        rotate: -.25deg
      }

      main .rounded-2xl.bg-white::before {
        width: 66px;
        height: 18px;
        margin-left: -33px
      }
    }

    @media (prefers-reduced-motion:reduce) {

      main .rounded-2xl.bg-white,
      main .rounded-2xl.bg-white:hover {
        transition: none
      }
    }

    /* plain-white opt-out (lh-plain): no paper tint, no tape, no card or row hover */
    main .rounded-2xl.bg-white.lh-plain {
      background: #fff !important;
      border-color: #e2e8f0 !important;
      border-radius: 1rem !important;
      box-shadow: 0 1px 2px rgba(15, 23, 42, .05), 0 0 0 1px #e2e8f0 !important;
      rotate: none;
      transition: none;
    }

    main .rounded-2xl.bg-white.lh-plain::before {
      display: none;
    }

    main .rounded-2xl.bg-white.lh-plain:hover,
    main .rounded-2xl.bg-white.lh-plain.ring-1:hover {
      rotate: none;
      box-shadow: 0 1px 2px rgba(15, 23, 42, .05), 0 0 0 1px #e2e8f0 !important;
    }

    main .rounded-2xl.bg-white.lh-plain .text-slate-400 {
      color: #94a3b8 !important
    }

    main .rounded-2xl.bg-white.lh-plain .text-slate-500 {
      color: #64748b !important
    }

    main .rounded-2xl.bg-white.lh-plain table tbody tr:hover {
      background: transparent;
    }
  
        /* ============================================================
           torn paper buttons (brand colors kept, edges hand-torn)
           ============================================================ */
        button,
        a[class*="bg-indigo-600"],
        a[class*="bg-emerald-"] {
          clip-path: polygon(0% 5%,7% 2%,14% 8%,21% 3%,29% 9%,36% 2%,43% 7%,50% 1%,57% 8%,64% 4%,71% 9%,79% 3%,86% 7%,93% 2%,100% 4%,calc(100% - 1px) 20%,calc(100% - 3px) 40%,calc(100% - 1px) 60%,calc(100% - 2px) 80%,99% 100%,93% 95%,86% 99%,79% 93%,71% 98%,64% 92%,57% 97%,50% 91%,43% 98%,36% 93%,29% 99%,21% 92%,14% 97%,7% 94%,0% 96%,1px 80%,3px 60%,1px 40%,2px 20%);
          transition: rotate .18s ease, transform .18s ease;
        }
    
        button:hover,
        a[class*="bg-indigo-600"]:hover,
        a[class*="bg-emerald-"]:hover {
          rotate: .4deg;
        }
    
        button:active,
        a[class*="bg-indigo-600"]:active,
        a[class*="bg-emerald-"]:active {
          scale: .97;
        }
    
        button:focus-visible,
        a[class*="bg-indigo-600"]:focus-visible,
        a[class*="bg-emerald-"]:focus-visible {
          box-shadow: inset 0 0 0 2px rgba(4, 63, 46, .55) !important;
        }

        button[class*="bg-indigo-600"]:focus-visible,
        a[class*="bg-indigo-600"]:focus-visible,
        button[class*="bg-emerald-"]:focus-visible,
        a[class*="bg-emerald-"]:focus-visible {
          box-shadow: inset 0 0 0 2px rgba(255, 255, 255, .85) !important;
        }
    
        /* primary: indigo with paper grain + resting tilt */
        button[class*="bg-indigo-600"],
        a[class*="bg-indigo-600"] {
          background-image:
            repeating-linear-gradient(0deg, rgba(255, 255, 255, .07) 0 1px, transparent 1px 3px),
            repeating-linear-gradient(90deg, rgba(255, 255, 255, .05) 0 1px, transparent 1px 4px),
            linear-gradient(180deg, #4f46e5, #4338ca) !important;
          box-shadow: 0 1px 2px rgba(31, 41, 33, .18) !important;
          rotate: -.35deg;
        }
    
        button[class*="bg-indigo-600"]:hover,
        a[class*="bg-indigo-600"]:hover {
          rotate: .15deg;
        }

        /* primary (emerald, landing CTAs): paper grain + resting tilt */
        button[class*="bg-emerald-7"],
        a[class*="bg-emerald-7"] {
          background-image:
            repeating-linear-gradient(0deg, rgba(255, 255, 255, .07) 0 1px, transparent 1px 3px),
            repeating-linear-gradient(90deg, rgba(255, 255, 255, .05) 0 1px, transparent 1px 4px),
            linear-gradient(180deg, #047857, #065f46) !important;
          box-shadow: 0 1px 2px rgba(31, 41, 33, .18) !important;
          rotate: -.35deg;
        }

        button[class*="bg-emerald-7"]:hover,
        a[class*="bg-emerald-7"]:hover {
          rotate: .15deg;
        }

        /* primary (emerald-600 shade): paper grain + resting tilt */
        button[class*="bg-emerald-6"],
        a[class*="bg-emerald-6"] {
          background-image:
            repeating-linear-gradient(0deg, rgba(255, 255, 255, .07) 0 1px, transparent 1px 3px),
            repeating-linear-gradient(90deg, rgba(255, 255, 255, .05) 0 1px, transparent 1px 4px),
            linear-gradient(180deg, #059669, #047857) !important;
          box-shadow: 0 1px 2px rgba(31, 41, 33, .18) !important;
          rotate: -.35deg;
        }

        button[class*="bg-emerald-6"]:hover,
        a[class*="bg-emerald-6"]:hover {
          rotate: .15deg;
        }
    
        /* ghost: small cream paper scrap */
        main button[class*="border-slate-200"],
        a[class*="border-slate-300"] {
          background: linear-gradient(180deg, #fdfbf5, #f8f4e9) !important;
          border-color: #e6dfcd !important;
          box-shadow: 0 1px 2px rgba(31, 41, 33, .12);
        }
    
        @media (prefers-reduced-motion:reduce) {
    
          button,
          a[class*="bg-indigo-600"],
          a[class*="bg-emerald-"] {
            transition: none
          }
        }

</style>
</head>

<body class="flex min-h-screen flex-col font-sans text-slate-800<?= $user ? ' lh-app' : '' ?>" <?= $user ? ' data-heartbeat="1"' : '' ?>>
  <div id="lh-progress"></div>

  <?php if ($user): ?>
    <aside id="lh-sidebar" class="lh-sidebar" aria-label="Main navigation">
      <div class="lh-side-head">
        <a href="dashboard.php" title="LearnHub home"
          class="lh-logo grid h-10 w-10 place-items-center rounded-xl text-white text-lg">🎓</a>
        <div class="min-w-0 leading-tight">
          <a href="dashboard.php" class="block text-sm font-extrabold tracking-tight text-slate-900">LearnHub <span
              class="text-emerald-600">LMS</span></a>
          <p class="text-[11px] text-slate-400">
            <?= ($user['role'] ?? '') === 'teacher' ? '👩‍🏫 Teacher' : '👨‍🎓 Student' ?></p>
        </div>
      </div>

      <div class="lh-side-sec">Menu</div>
      <nav class="lh-side-nav">
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
        <?php if (($user['role'] ?? '') === 'teacher'): ?>
          <a href="codes.php" class="lh-side-link <?= $nav_active === 'codes' ? 'active' : '' ?>"
            data-tip="Invite codes"><span class="lh-side-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14.5 3.5a7 7 0 0 0-6.7 9.3L3.5 17v3.5H7L15 12.5a7 7 0 1 0-.5-9z" />
                <circle cx="16.5" cy="7.5" r="1.8" />
              </svg></span><span class="lh-side-label">Invite codes</span></a>
        <?php endif; ?>
        <a href="<?= ($user['role'] ?? '') === 'teacher' ? 'quiz_records.php' : 'my_records.php' ?>"
          class="lh-side-link <?= $nav_active === 'records' ? 'active' : '' ?>" data-tip="Quiz Records"><span
            class="lh-side-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
              stroke-linecap="round" stroke-linejoin="round">
              <path d="M4 20V10M10 20V4M16 20v-7" />
            </svg></span><span
            class="lh-side-label"><?= ($user['role'] ?? '') === 'teacher' ? 'Student Records' : 'My Progress' ?></span></a>
      </nav>

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

      <div class="lh-side-collapse-wrap"><button id="lh-side-collapse" type="button" title="Collapse sidebar"
          aria-label="Collapse sidebar">«</button></div>
      <div class="lh-side-foot">
        <span
          class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-emerald-600 text-sm font-bold text-white"><?= e(strtoupper(substr((string) $user['name'], 0, 1))) ?></span>
        <span class="min-w-0 flex-1 text-left leading-tight">
          <span class="block truncate text-sm font-semibold text-slate-800"><?= e((string) $user['name']) ?></span>
          <span
            class="block truncate text-[10px] uppercase tracking-wide text-slate-400"><?= ($user['role'] ?? '') === 'teacher' ? 'Teacher' : 'Student' ?></span>
        </span>
        <a href="logout.php" title="Log out"
          class="lh-ico grid h-9 w-9 place-items-center rounded-lg border border-slate-200 text-slate-500">⏻</a>
      </div>
    </aside>
    <div id="lh-side-backdrop" class="lh-backdrop" aria-hidden="true"></div>
  <?php endif; ?>
  <nav class="glass lh-topbar sticky top-0 z-40 border-b border-white/60" aria-label="Primary">
    <div class="lh-top-inner">
      <div class="flex min-w-0 items-center gap-1.5">
        <?php if ($user): ?>
          <button id="lh-rail-toggle" type="button" title="Collapse sidebar" aria-label="Collapse sidebar"
            class="lh-ico grid h-9 w-9 place-items-center rounded-lg text-slate-600"><svg class="h-5 w-5" fill="none"
              viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg></button>
          <button id="lh-side-toggle" class="lh-ico grid h-9 w-9 place-items-center rounded-lg text-slate-600 lg:hidden"
            aria-label="Open menu">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M3 12h18M3 18h18" />
            </svg>
          </button>
        <?php endif; ?>
        <a href="<?= $user ? 'dashboard.php' : 'index.php' ?>" title="LearnHub home"
          class="lh-logo grid h-9 w-9 place-items-center rounded-xl text-white lg:hidden">🎓</a>
        <a href="<?= $user ? 'dashboard.php' : 'index.php' ?>" class="lh-topword hidden items-center gap-2 lg:flex">
          <span class="lh-logo grid h-8 w-8 place-items-center rounded-xl text-white">🎓</span>
          <span class="text-sm font-extrabold tracking-tight text-slate-900">LearnHub <span
              class="text-emerald-600">LMS</span></span>
        </a>

      </div>


      <div class="flex items-center gap-1.5">
        <?php if ($user): ?>
          <div class="relative">
            <button id="lh-notif-btn" class="lh-ico grid h-9 w-9 place-items-center rounded-lg text-slate-600"
              aria-label="Notifications">
              <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                  d="M18.37 4.6L12.9 5.8a4.6 4.6 0 002.6-2.6M15.5 3.5l1.2 9m.5 0v3.6M15.5 15.5h6M9.75 4.75V21m6-6.75M9.75 9.5h6M9.75 15.5h6M9.75 21h6" />
              </svg>
              <span id="lh-notif-badge" class="lh-badge hidden">0</span>
            </button>
          </div>
          <a href="messages.php" id="lh-chat-link"
            class="lh-ico grid h-9 w-9 place-items-center rounded-lg text-slate-600" aria-label="Messages">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M4.75 3l7.5-4.75a4.75 4.75 0 003.78-3.78l-3.78-6.57M6.3 6.3h2.85M13.2 6.3h2.4M12.75 6.3h.9M3.75 13.05l1.8 4.5M4.75 16.05h7.5m3 2.25h.9M4.75 18.3h7.5m-.9 1.05l3.6 2.1M8.5 20.25h5.25m4.5 0l-5.25 2.1M17.35 9.3h3.6M16.9 12.15h3.6M4.75 6.3h2.7v3M9.6 9.3h1.05M8.05 6.3h2.7M4.75 6.9l-1.05 2.4M9.6 15.3h4.5M14.85 15.3h3.75" />
            </svg>
            <span id="lh-chat-badge" class="lh-badge hidden">0</span>
          </a>
          <span class="hidden text-right sm:flex sm:flex-col sm:items-start leading-tight">
            <span class="text-sm font-semibold leading-4 text-slate-800"><?= e((string) $user['name']) ?></span>
            <span
              class="text-[10px] font-semibold uppercase tracking-wide text-slate-400"><?= ($user['role'] ?? '') === 'teacher' ? 'Teacher' : 'Student' ?></span>
          </span>
          <span
            class="grid h-9 w-9 place-items-center rounded-full bg-emerald-600 text-sm font-bold text-white"><?= e(strtoupper(substr((string) $user['name'], 0, 1))) ?></span>
          <a href="logout.php" title="Log out" aria-label="Log out"
            class="hidden rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 sm:inline-block">Log
            out</a>
          <a href="logout.php" title="Log out" aria-label="Log out"
            class="grid h-9 w-9 place-items-center rounded-lg border border-slate-200 text-slate-500 sm:hidden">⏻</a>
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
  <?php if ($user): ?>
    <div id="lh-notif-panel" class="lh-panel hidden" aria-label="Notifications">
      <div class="lx-panel-head flex items-center justify-between gap-2 border-b border-slate-200 px-3.5 py-2.5">
        <span class="text-sm font-bold text-slate-800">🔔 Notifications</span>
        <button id="lh-notif-read-all" type="button" class="text-xs font-semibold text-emerald-600 hover:underline">Mark
          all as read</button>
      </div>
      <div id="lh-notif-list" class="lx-panel-list">
        <?php $lh_notifs = notifications_for((int) $user['id'], 8); ?>
        <?php if (!$lh_notifs): ?>
          <p class="px-3.5 py-6 text-center text-xs text-slate-400">No notifications yet.<br>New lessons, quizzes, results
            and messages will show up here.</p>
        <?php else:
          foreach ($lh_notifs as $n):
            $lh_ago = time() - (int) $n['created_at'];
            $lh_agoTxt = $lh_ago < 60 ? 'just now' : ($lh_ago < 3600 ? (int) floor($lh_ago / 60) . 'm ago' : ($lh_ago < 86400 ? (int) floor($lh_ago / 3600) . 'h ago' : (int) floor($lh_ago / 86400) . 'd ago')); ?>
            <a href="<?= e((string) $n['link']) ?>" data-notif-id="<?= (int) $n['id'] ?>"
              data-notif-link="<?= e((string) $n['link']) ?>"
              class="lx-panel-item lh-notif-item <?= $n['is_read'] ? '' : 'lh-notif-unread' ?>">
              <span class="lx-panel-item-title text-[13px] font-semibold text-slate-800"><?= e((string) $n['title']) ?></span>
              <?php if ($n['body']): ?><span
                  class="text-xs text-slate-500"><?= e((string) $n['body']) ?></span><?php endif; ?>
              <span class="text-[10px] text-slate-400"><?= e($lh_agoTxt) ?></span>
            </a>
          <?php endforeach; endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php foreach ($flashes as $f): ?>
    <div data-toast
      class="toast-in fixed right-4 top-20 z-50 flex max-w-sm items-start gap-3 rounded-xl border p-4 shadow-lg <?= ($f['type'] ?? '') === 'error' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' ?>">
      <span><?= ($f['type'] ?? '') === 'error' ? '⚠️' : '✅' ?></span>
      <p class="text-sm font-medium"><?= e((string) ($f['msg'] ?? '')) ?></p>
      <button data-toast-close class="ml-2 text-slate-400 hover:text-slate-600">✕</button>
    </div>
  <?php endforeach; ?>

  <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8<?= $user ? ' lh-main' : '' ?>">