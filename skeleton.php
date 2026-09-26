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
 *   no bar of their own for the ghost to stand in for. Those four also pass an
 *   explicit layout name as the second argument (they know what they are).
 *
 * LAYOUT PER ARCHETYPE — the page body of the ghost is not one shape for every
 * page. lh_skeleton_body() asks lh_skeleton_detect_layout() which archetype the
 * response is and renders that page's ghost: auth, dashboard, courses, course,
 * admin_table, messages, reader, quiz, classroom, certificate, verify.
 * Detection resolves basename($_SERVER['SCRIPT_NAME']) plus the sign-in state;
 * a page can override it with the $lh_skeleton_layout global or by passing the
 * layout as lh_skeleton_body()'s second argument. Unknown pages fall back to
 * the dashboard shape (signed-in) or the centred card (guest).
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

if (!function_exists('lh_skeleton_detect_layout')) {
    /**
     * Which archetype's ghost this response should show.
     * Precedence: the second argument to lh_skeleton_body(), then the
     * $lh_skeleton_layout global a page may set before including header.php,
     * then basename(SCRIPT_NAME) + the sign-in state. The map covers every
     * view page; AJAX/JSON endpoints never build the pane, and anything not on
     * the map falls back (dashboard for signed-in users, centred card for
     * guests) — the shape header pages always showed before this split.
     */
    function lh_skeleton_detect_layout(?string $explicit = null): string
    {
        global $lh_skeleton_layout;

        if ($explicit !== null && $explicit !== '') {
            return $explicit;
        }
        if (!empty($lh_skeleton_layout)) {
            return (string) $lh_skeleton_layout;
        }

        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? ''));
        $page = strtolower(basename($script, '.php'));
        $logged = function_exists('current_user') ? current_user() !== null : false;

        return match ($page) {
            'certificate'                                 => 'certificate',
            'verify_certificate'                          => 'verify',
            'login', 'register', 'reset_password'         => 'auth',
            'read'                                        => 'reader',
            'quiz'                                        => 'quiz',
            'messages'                                    => 'messages',
            'courses'                                     => 'courses',
            'course', 'course_create'                     => 'course',
            'index', 'dashboard'                          => $logged ? 'dashboard' : 'auth',
            'admin', 'attendance', 'attendance_day',
            'enrollments', 'my_records', 'quiz_records',
            'settings', 'codes'                           => 'admin_table',
            'class_room', 'live_class', 'live_join',
            'course_modal', 'quiz_modal', 'lesson_modal'  => 'classroom',
            default                                       => $logged ? 'dashboard' : 'auth',
        };
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
            overflow: hidden;
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

            /* Layouts that move the shell redraw the ghost with it, so the pane
               never promises chrome the page will not show: the icon dock keeps
               a 76px rail, top navigation has none at all (the numbers mirror
               assets/layout-dock.css / layout-topnav.css). The other layouts
               only nudge the rail width, which the frosted pane hides. */
            html[data-layout="dock"] #lh-skel .lh-skel-rail {
              width: 76px
            }

            html[data-layout="dock"] #lh-skel .lh-skel-top {
              left: 76px
            }

            html[data-layout="dock"] #lh-skel .lh-skel-page {
              padding-left: 76px
            }

            html[data-layout="topnav"] #lh-skel .lh-skel-rail {
              display: none
            }

            html[data-layout="topnav"] #lh-skel .lh-skel-top {
              left: 0
            }

            html[data-layout="topnav"] #lh-skel .lh-skel-page {
              padding-left: 0;
              padding-top: 98px /* 52px top bar + 46px nav strip */
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

          /* ---- archetype: auth + verify — one centred column ---- */
          #lh-skel .lh-skel-auth {
            max-width: 28rem;
            margin: 3rem auto 0
          }

          #lh-skel .lh-skel-auth-title {
            width: min(240px, 70%);
            height: 26px;
            margin: 0 auto
          }

          #lh-skel .lh-skel-auth-sub {
            width: min(300px, 90%);
            height: 13px;
            margin: .7rem auto 0
          }

          #lh-skel .lh-skel-form {
            margin-top: 1.4rem;
            padding: 1.75rem;
            border-radius: 16px;
            border: 1px solid rgba(15, 23, 42, .05);
            background: rgba(255, 255, 255, .74)
          }

          #lh-skel .lh-skel-label {
            width: 84px;
            height: 10px;
            margin-top: 1.05rem;
            border-radius: 5px
          }

          #lh-skel .lh-skel-field {
            height: 40px;
            margin-top: .5rem;
            border-radius: 12px
          }

          #lh-skel .lh-skel-btn {
            height: 42px;
            margin-top: 1.3rem;
            border-radius: 12px
          }

          #lh-skel .lh-skel-brandrow {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .6rem
          }

          #lh-skel .lh-skel-brand {
            width: 34px;
            height: 34px;
            border-radius: 10px
          }

          #lh-skel .lh-skel-result {
            margin-top: 1.2rem;
            padding: 1.1rem;
            border-radius: 14px;
            border: 1px solid rgba(15, 23, 42, .06);
            background: rgba(255, 255, 255, .66)
          }

          #lh-skel .lh-skel-kv {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: .6rem 1rem;
            margin-top: .9rem
          }

          #lh-skel .lh-skel-k {
            width: 78px;
            height: 11px;
            border-radius: 5px
          }

          #lh-skel .lh-skel-v {
            width: min(190px, 100%);
            height: 11px;
            border-radius: 5px
          }

          /* ---- archetype: courses — search, chips, card grid ---- */
          #lh-skel .lh-skel-search {
            height: 42px;
            margin-top: 1.5rem;
            border-radius: 12px
          }

          #lh-skel .lh-skel-chips {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            margin-top: .9rem
          }

          #lh-skel .lh-skel-chip {
            width: 78px;
            height: 27px;
            border-radius: 9999px
          }

          #lh-skel .lh-skel-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1rem
          }

          #lh-skel .lh-skel-course {
            min-height: 176px
          }

          #lh-skel .lh-skel-course .lh-skel-num {
            margin-top: 0
          }

          #lh-skel .lh-skel-chiprow {
            display: flex;
            gap: .5rem
          }

          #lh-skel .lh-skel-chip-sm {
            width: 72px;
            height: 20px;
            border-radius: 9999px
          }

          #lh-skel .lh-skel-chip-sm + .lh-skel-chip-sm {
            width: 52px
          }

          /* ---- archetype: course — hero header + lesson rows ---- */
          #lh-skel .lh-skel-back {
            width: 84px;
            height: 12px;
            border-radius: 6px
          }

          #lh-skel .lh-skel-hero {
            margin-top: .9rem;
            padding: 1.5rem;
            border-radius: 16px;
            border: 1px solid rgba(15, 23, 42, .05);
            background: rgba(255, 255, 255, .74)
          }

          #lh-skel .lh-skel-hero-title {
            width: min(520px, 90%);
            height: 30px;
            margin-top: .9rem
          }

          #lh-skel .lh-skel-metarow {
            display: flex;
            flex-wrap: wrap;
            gap: .6rem;
            margin-top: 1.05rem
          }

          #lh-skel .lh-skel-meta {
            width: 118px;
            height: 26px;
            border-radius: 9999px
          }

          #lh-skel .lh-skel-lessons {
            margin-top: 1rem;
            padding: .5rem 1.1rem;
            border-radius: 16px;
            border: 1px solid rgba(15, 23, 42, .05);
            background: rgba(255, 255, 255, .66)
          }

          #lh-skel .lh-skel-lesson {
            display: flex;
            align-items: center;
            gap: .9rem;
            padding: .85rem 0;
            border-bottom: 1px solid rgba(15, 23, 42, .05)
          }

          #lh-skel .lh-skel-lesson:last-child {
            border-bottom: 0
          }

          #lh-skel .lh-skel-lesson .lh-skel-line {
            flex: 1;
            margin-top: 0
          }

          #lh-skel .lh-skel-lesson .lh-skel-chip {
            flex: none;
            width: 74px;
            margin-top: 0
          }

          /* ---- archetype: admin_table — stat cards + a wide data table ---- */
          #lh-skel .lh-skel-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem
          }

          #lh-skel .lh-skel-stat {
            padding: 1rem;
            border-radius: 16px;
            border: 1px solid rgba(15, 23, 42, .05);
            background: rgba(255, 255, 255, .72)
          }

          #lh-skel .lh-skel-stat .lh-skel-num {
            margin-top: 0
          }

          #lh-skel .lh-skel-stat .lh-skel-line {
            width: 70%
          }

          #lh-skel .lh-skel-table {
            margin-top: 1rem;
            border-radius: 16px;
            border: 1px solid rgba(15, 23, 42, .05);
            background: rgba(255, 255, 255, .7);
            overflow: hidden
          }

          #lh-skel .lh-skel-thead {
            display: grid;
            grid-template-columns: 2.2fr 1.2fr 1fr .8fr;
            gap: 1rem;
            padding: .9rem 1.1rem;
            background: rgba(15, 23, 42, .03);
            border-bottom: 1px solid rgba(15, 23, 42, .06)
          }

          #lh-skel .lh-skel-trow {
            display: grid;
            grid-template-columns: 2.2fr 1.2fr 1fr .8fr;
            gap: 1rem;
            padding: .95rem 1.1rem;
            border-bottom: 1px solid rgba(15, 23, 42, .04)
          }

          #lh-skel .lh-skel-trow:last-child {
            border-bottom: 0
          }

          #lh-skel .lh-skel-th {
            width: 55%;
            height: 10px;
            border-radius: 5px
          }

          #lh-skel .lh-skel-td {
            width: 80%;
            height: 12px;
            border-radius: 6px
          }

          /* ---- archetype: messages — thread list beside the conversation ---- */
          #lh-skel .lh-skel-chat {
            display: grid;
            grid-template-columns: 300px 1fr;
            margin-top: .9rem;
            border-radius: 16px;
            border: 1px solid rgba(15, 23, 42, .05);
            background: rgba(255, 255, 255, .72);
            overflow: hidden;
            min-height: 400px
          }

          #lh-skel .lh-skel-chatlist {
            padding: 1rem;
            border-right: 1px solid rgba(15, 23, 42, .06)
          }

          #lh-skel .lh-skel-chatlist .lh-skel-line {
            margin-top: 0
          }

          #lh-skel .lh-skel-chatlist .lh-skel-line:first-child {
            width: 45%
          }

          #lh-skel .lh-skel-convo {
            display: flex;
            align-items: center;
            gap: .7rem;
            padding: .7rem 0;
            border-bottom: 1px solid rgba(15, 23, 42, .05)
          }

          #lh-skel .lh-skel-avatar {
            flex: none;
            width: 34px;
            height: 34px;
            border-radius: 9999px
          }

          #lh-skel .lh-skel-convo-lines {
            flex: 1;
            min-width: 0
          }

          #lh-skel .lh-skel-convo-lines .lh-skel-line {
            margin-top: .4rem
          }

          #lh-skel .lh-skel-convo-lines .lh-skel-line:first-child {
            width: 70%;
            margin-top: 0
          }

          #lh-skel .lh-skel-thread {
            display: flex;
            flex-direction: column;
            gap: .7rem;
            padding: 1.1rem
          }

          #lh-skel .lh-skel-msgrow {
            display: flex
          }

          #lh-skel .lh-skel-bubble {
            width: 62%;
            height: 44px;
            border-radius: 14px
          }

          #lh-skel .lh-skel-msgrow.right {
            justify-content: flex-end
          }

          #lh-skel .lh-skel-msgrow.right .lh-skel-bubble {
            width: 50%
          }

          #lh-skel .lh-skel-composer {
            display: flex;
            gap: .6rem;
            margin-top: auto;
            padding-top: .9rem
          }

          #lh-skel .lh-skel-input {
            flex: 1;
            height: 42px;
            border-radius: 12px
          }

          #lh-skel .lh-skel-send {
            flex: none;
            width: 42px;
            height: 42px;
            border-radius: 12px
          }

          /* ---- archetype: reader — a hint bar over the reading column ---- */
          #lh-skel .lh-skel-hint {
            height: 44px;
            margin-top: 1.2rem;
            border-radius: 12px
          }

          #lh-skel .lh-skel-doc {
            margin-top: .9rem;
            padding: 1.5rem;
            border-radius: 16px;
            border: 1px solid rgba(15, 23, 42, .05);
            background: rgba(255, 255, 255, .75)
          }

          #lh-skel .lh-skel-doc .lh-skel-line {
            height: 14px
          }

          #lh-skel .lh-skel-doc .lh-skel-line:first-child {
            width: 46%;
            height: 18px;
            margin-top: 0
          }

          /* ---- archetype: quiz — a header card over stacked questions ---- */
          #lh-skel .lh-skel-qhead {
            margin-top: 1.2rem;
            padding: 1.5rem;
            border-radius: 16px;
            border: 1px solid rgba(15, 23, 42, .05);
            background: rgba(255, 255, 255, .74)
          }

          #lh-skel .lh-skel-qhead .lh-skel-kicker {
            margin-top: 0
          }

          #lh-skel .lh-skel-qhead .lh-skel-line {
            width: 76%;
            height: 14px;
            margin-top: 1.1rem
          }

          #lh-skel .lh-skel-qhead .lh-skel-line.short {
            width: 52%
          }

          #lh-skel .lh-skel-q {
            margin-top: 1rem;
            padding: 1.25rem;
            border-radius: 16px;
            border: 1px solid rgba(15, 23, 42, .05);
            background: rgba(255, 255, 255, .72)
          }

          #lh-skel .lh-skel-q .lh-skel-line {
            margin-top: 0
          }

          #lh-skel .lh-skel-opt {
            height: 38px;
            margin-top: .6rem;
            border-radius: 11px;
            border: 1px solid rgba(15, 23, 42, .08)
          }

          /* ---- archetype: classroom — a dark stage over the control bar ---- */
          #lh-skel .lh-skel-roombar {
            display: flex;
            align-items: center;
            gap: .9rem;
            margin-top: 1.2rem
          }

          #lh-skel .lh-skel-roombar .lh-skel-back {
            flex: none
          }

          #lh-skel .lh-skel-roombar .lh-skel-line {
            flex: 1;
            max-width: 320px;
            margin-top: 0
          }

          #lh-skel .lh-skel-roombar .lh-skel-pill {
            flex: none;
            margin-top: 0
          }

          #lh-skel .lh-skel-stage {
            height: calc(100dvh - 300px);
            min-height: 300px;
            margin-top: 1rem;
            border-radius: 14px;
            background: rgba(15, 23, 42, .88);
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .05)
          }

          #lh-skel .lh-skel-roomtools {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .6rem;
            margin-top: 1rem
          }

          /* ---- archetype: certificate — a landscape sheet with a seal ---- */
          #lh-skel .lh-skel-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .8rem;
            margin-bottom: 1.2rem
          }

          #lh-skel .lh-skel-toolbar .lh-skel-pill,
          #lh-skel .lh-skel-toolbar .lh-skel-back {
            margin-top: 0
          }

          #lh-skel .lh-skel-sheet {
            position: relative;
            display: grid;
            place-items: center;
            padding: 2.4rem 2rem;
            border-radius: 16px;
            border: 1px solid rgba(212, 175, 55, .4);
            background: rgba(255, 253, 248, .96);
            box-shadow: 0 18px 44px -24px rgba(15, 23, 42, .35);
            aspect-ratio: 297 / 210
          }

          #lh-skel .lh-skel-sheet-in {
            width: min(46rem, 100%)
          }

          #lh-skel .lh-skel-sheet .lh-skel-brand {
            display: block;
            width: 46px;
            height: 46px;
            margin: 0 auto
          }

          #lh-skel .lh-skel-cert-kicker {
            width: 170px;
            height: 11px;
            margin: 1rem auto 0;
            border-radius: 5px
          }

          #lh-skel .lh-skel-cert-title {
            width: min(430px, 90%);
            height: 26px;
            margin: .8rem auto 0
          }

          #lh-skel .lh-skel-cert-line {
            width: min(340px, 80%);
            height: 12px;
            margin: .8rem auto 0;
            border-radius: 6px
          }

          #lh-skel .lh-skel-cert-name {
            width: min(300px, 75%);
            height: 34px;
            margin: .9rem auto 0
          }

          #lh-skel .lh-skel-cert-course {
            width: min(360px, 85%);
            height: 18px;
            margin: .9rem auto 0
          }

          #lh-skel .lh-skel-cert-foot {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: end;
            gap: 1.4rem;
            margin-top: 1.7rem
          }

          #lh-skel .lh-skel-cert-sig {
            display: block;
            width: 88%;
            height: 12px;
            margin-top: 0;
            border-radius: 6px
          }

          #lh-skel .lh-skel-cert-foot .lh-skel-line {
            width: 60%
          }

          #lh-skel .lh-skel-seal {
            width: 58px;
            height: 58px;
            border-radius: 9999px
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

          /* archetype trims: the pane never scrolls — keep each ghost to a screen */
          @media (max-width: 899px) {
            #lh-skel .lh-skel-chat {
              grid-template-columns: 1fr;
              min-height: 340px
            }

            #lh-skel .lh-skel-chatlist {
              display: none
            }

            #lh-skel .lh-skel-stage {
              height: 56vh;
              min-height: 240px
            }
          }

          @media (max-width: 639px) {
            #lh-skel .lh-skel-auth {
              margin-top: 1.5rem
            }

            #lh-skel .lh-skel-thead,
            #lh-skel .lh-skel-trow {
              grid-template-columns: 1.7fr 1fr
            }

            #lh-skel .lh-skel-thead > *:nth-child(n+3),
            #lh-skel .lh-skel-trow > *:nth-child(n+3) {
              display: none
            }

            #lh-skel .lh-skel-stats {
              grid-template-columns: repeat(2, 1fr)
            }

            #lh-skel .lh-skel-qlist .lh-skel-q:nth-child(n+3) {
              display: none
            }

            #lh-skel .lh-skel-sheet {
              aspect-ratio: auto;
              padding: 1.6rem 1.1rem
            }

            #lh-skel .lh-skel-cert-foot {
              gap: .8rem
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
     *  top-bar ghost would promise chrome that is not coming.
     *  $layout names the archetype to ghost (see lh_skeleton_detect_layout());
     *  leave it null and the pane works the page out from the URL. */
    function lh_skeleton_body(bool $shell = true, ?string $layout = null): void
    {
        if (lh_skeleton_off()) return;
        $layout = lh_skeleton_detect_layout($layout);
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
              <?php lh_skeleton_tpl($layout); ?>
            </div>
          </div>
        </div>
        <?php
        lh_skeleton_boot();
    }
}

if (!function_exists('lh_skeleton_tpl')) {
    /**
     * The page body of the ghost: one renderer per archetype. Anything unknown
     * lands on the dashboard shape — every signed-in header page used to get
     * exactly that, so the worst case of a bad guess is a generic ghost, never
     * a missing one.
     */
    function lh_skeleton_tpl(string $layout): void
    {
        switch ($layout) {
            case 'auth':        lh_skeleton_tpl_auth();        break;
            case 'courses':     lh_skeleton_tpl_courses();     break;
            case 'course':      lh_skeleton_tpl_course();      break;
            case 'admin_table': lh_skeleton_tpl_admin_table(); break;
            case 'messages':    lh_skeleton_tpl_messages();    break;
            case 'reader':      lh_skeleton_tpl_reader();      break;
            case 'quiz':        lh_skeleton_tpl_quiz();        break;
            case 'classroom':   lh_skeleton_tpl_classroom();   break;
            case 'certificate': lh_skeleton_tpl_certificate(); break;
            case 'verify':      lh_skeleton_tpl_verify();      break;
            case 'dashboard':
            default:            lh_skeleton_tpl_dashboard();   break;
        }
    }

    /** kicker + title + sub — how every list/overview page opens. */
    function lh_skeleton_tpl_head(): void
    {
        ?><span class="lh-skel-b lh-skel-kicker"></span>
        <span class="lh-skel-b lh-skel-title"></span>
        <span class="lh-skel-b lh-skel-sub"></span><?php
    }

    /** dashboard: the stat-card grid over two wide panels (the original). */
    function lh_skeleton_tpl_dashboard(): void
    {
        lh_skeleton_tpl_head();
        ?>
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
        <?php
    }

    /** auth: login / register / reset — one centred column, one card. */
    function lh_skeleton_tpl_auth(): void
    {
        ?>
        <div class="lh-skel-auth">
          <span class="lh-skel-b lh-skel-auth-title"></span>
          <span class="lh-skel-b lh-skel-auth-sub"></span>
          <div class="lh-skel-form">
            <span class="lh-skel-b lh-skel-label"></span>
            <span class="lh-skel-b lh-skel-field"></span>
            <span class="lh-skel-b lh-skel-label"></span>
            <span class="lh-skel-b lh-skel-field"></span>
            <span class="lh-skel-b lh-skel-btn"></span>
          </div>
        </div>
        <?php
    }

    /** verify: the public certificate check — brand, code field, result. */
    function lh_skeleton_tpl_verify(): void
    {
        ?>
        <div class="lh-skel-auth">
          <div class="lh-skel-brandrow">
            <span class="lh-skel-b lh-skel-brand"></span>
            <span class="lh-skel-b lh-skel-word"></span>
          </div>
          <span class="lh-skel-b lh-skel-auth-title"></span>
          <span class="lh-skel-b lh-skel-auth-sub"></span>
          <div class="lh-skel-form">
            <span class="lh-skel-b lh-skel-field"></span>
            <span class="lh-skel-b lh-skel-btn"></span>
            <div class="lh-skel-result">
              <span class="lh-skel-b lh-skel-line short"></span>
              <span class="lh-skel-b lh-skel-title"></span>
              <div class="lh-skel-kv">
                <?php for ($lh_i = 0; $lh_i < 3; $lh_i++): ?>
                  <span class="lh-skel-b lh-skel-k"></span>
                  <span class="lh-skel-b lh-skel-v"></span>
                <?php endfor; ?>
              </div>
            </div>
          </div>
        </div>
        <?php
    }

    /** courses: search bar, filter chips, a grid of course cards. */
    function lh_skeleton_tpl_courses(): void
    {
        lh_skeleton_tpl_head();
        ?>
        <div class="lh-skel-b lh-skel-search"></div>
        <div class="lh-skel-chips">
          <?php for ($lh_i = 0; $lh_i < 4; $lh_i++): ?>
            <span class="lh-skel-b lh-skel-chip"></span>
          <?php endfor; ?>
        </div>
        <div class="lh-skel-cards">
          <?php for ($lh_i = 0; $lh_i < 6; $lh_i++): ?>
            <div class="lh-skel-card lh-skel-course">
              <div class="lh-skel-chiprow">
                <span class="lh-skel-b lh-skel-chip-sm"></span>
                <span class="lh-skel-b lh-skel-chip-sm"></span>
              </div>
              <span class="lh-skel-b lh-skel-num"></span>
              <span class="lh-skel-b lh-skel-line"></span>
              <span class="lh-skel-b lh-skel-line short"></span>
              <span class="lh-skel-b lh-skel-line short"></span>
            </div>
          <?php endfor; ?>
        </div>
        <?php
    }

    /** course: back link, hero header card, the lesson list under it. */
    function lh_skeleton_tpl_course(): void
    {
        ?>
        <span class="lh-skel-b lh-skel-back"></span>
        <div class="lh-skel-hero">
          <div class="lh-skel-chiprow">
            <span class="lh-skel-b lh-skel-chip-sm"></span>
            <span class="lh-skel-b lh-skel-chip-sm"></span>
          </div>
          <span class="lh-skel-b lh-skel-hero-title"></span>
          <span class="lh-skel-b lh-skel-line"></span>
          <span class="lh-skel-b lh-skel-line"></span>
          <span class="lh-skel-b lh-skel-line short"></span>
          <div class="lh-skel-metarow">
            <?php for ($lh_i = 0; $lh_i < 3; $lh_i++): ?>
              <span class="lh-skel-b lh-skel-meta"></span>
            <?php endfor; ?>
          </div>
        </div>
        <div class="lh-skel-lessons">
          <?php for ($lh_i = 0; $lh_i < 5; $lh_i++): ?>
            <div class="lh-skel-lesson">
              <span class="lh-skel-b lh-skel-tile"></span>
              <span class="lh-skel-b lh-skel-line"></span>
              <span class="lh-skel-b lh-skel-chip"></span>
            </div>
          <?php endfor; ?>
        </div>
        <?php
    }

    /** admin_table: stat cards over a wide four-column data table. */
    function lh_skeleton_tpl_admin_table(): void
    {
        lh_skeleton_tpl_head();
        ?>
        <div class="lh-skel-stats">
          <?php for ($lh_i = 0; $lh_i < 4; $lh_i++): ?>
            <div class="lh-skel-stat">
              <span class="lh-skel-b lh-skel-num"></span>
              <span class="lh-skel-b lh-skel-line"></span>
            </div>
          <?php endfor; ?>
        </div>
        <div class="lh-skel-table">
          <div class="lh-skel-thead">
            <?php for ($lh_i = 0; $lh_i < 4; $lh_i++): ?>
              <span class="lh-skel-b lh-skel-th"></span>
            <?php endfor; ?>
          </div>
          <?php for ($lh_i = 0; $lh_i < 5; $lh_i++): ?>
            <div class="lh-skel-trow">
              <?php for ($lh_j = 0; $lh_j < 4; $lh_j++): ?>
                <span class="lh-skel-b lh-skel-td"></span>
              <?php endfor; ?>
            </div>
          <?php endfor; ?>
        </div>
        <?php
    }

    /** messages: the thread list beside a conversation with its composer. */
    function lh_skeleton_tpl_messages(): void
    {
        ?>
        <span class="lh-skel-b lh-skel-back"></span>
        <div class="lh-skel-chat">
          <div class="lh-skel-chatlist">
            <span class="lh-skel-b lh-skel-line"></span>
            <?php for ($lh_i = 0; $lh_i < 5; $lh_i++): ?>
              <div class="lh-skel-convo">
                <span class="lh-skel-b lh-skel-avatar"></span>
                <div class="lh-skel-convo-lines">
                  <span class="lh-skel-b lh-skel-line"></span>
                  <span class="lh-skel-b lh-skel-line short"></span>
                </div>
              </div>
            <?php endfor; ?>
          </div>
          <div class="lh-skel-thread">
            <?php for ($lh_i = 0; $lh_i < 6; $lh_i++): ?>
              <div class="lh-skel-msgrow<?= ($lh_i % 2) ? ' right' : '' ?>">
                <span class="lh-skel-b lh-skel-bubble"></span>
              </div>
            <?php endfor; ?>
            <div class="lh-skel-composer">
              <span class="lh-skel-b lh-skel-input"></span>
              <span class="lh-skel-b lh-skel-send"></span>
            </div>
          </div>
        </div>
        <?php
    }

    /** reader: the back link and progress hint over the reading column. */
    function lh_skeleton_tpl_reader(): void
    {
        ?>
        <span class="lh-skel-b lh-skel-back"></span>
        <div class="lh-skel-b lh-skel-hint"></div>
        <div class="lh-skel-doc">
          <span class="lh-skel-b lh-skel-line"></span>
          <?php for ($lh_i = 0; $lh_i < 5; $lh_i++): ?>
            <span class="lh-skel-b lh-skel-line<?= ($lh_i % 3) === 2 ? ' short' : '' ?>"></span>
          <?php endfor; ?>
        </div>
        <?php
    }

    /** quiz: the quiz header card over a run of question cards. */
    function lh_skeleton_tpl_quiz(): void
    {
        ?>
        <span class="lh-skel-b lh-skel-back"></span>
        <div class="lh-skel-qhead">
          <span class="lh-skel-b lh-skel-kicker"></span>
          <span class="lh-skel-b lh-skel-hero-title"></span>
          <span class="lh-skel-b lh-skel-line"></span>
          <span class="lh-skel-b lh-skel-line short"></span>
        </div>
        <div class="lh-skel-qlist">
          <?php for ($lh_i = 0; $lh_i < 3; $lh_i++): ?>
            <div class="lh-skel-q">
              <span class="lh-skel-b lh-skel-line"></span>
              <?php for ($lh_j = 0; $lh_j < 4; $lh_j++): ?>
                <span class="lh-skel-b lh-skel-opt"></span>
              <?php endfor; ?>
            </div>
          <?php endfor; ?>
        </div>
        <?php
    }

    /** classroom: the stage a live lesson sits in, over its control bar. */
    function lh_skeleton_tpl_classroom(): void
    {
        ?>
        <div class="lh-skel-roombar">
          <span class="lh-skel-b lh-skel-back"></span>
          <span class="lh-skel-b lh-skel-line"></span>
          <span class="lh-skel-b lh-skel-pill"></span>
        </div>
        <div class="lh-skel-stage"></div>
        <div class="lh-skel-roomtools">
          <?php for ($lh_i = 0; $lh_i < 5; $lh_i++): ?>
            <span class="lh-skel-b lh-skel-ico"></span>
          <?php endfor; ?>
          <span class="lh-skel-b lh-skel-pill"></span>
        </div>
        <?php
    }

    /** certificate: the printable sheet — toolbar, frame, seal. */
    function lh_skeleton_tpl_certificate(): void
    {
        ?>
        <div class="lh-skel-toolbar">
          <span class="lh-skel-b lh-skel-pill"></span>
          <span class="lh-skel-b lh-skel-back"></span>
        </div>
        <div class="lh-skel-sheet">
          <div class="lh-skel-sheet-in">
            <span class="lh-skel-b lh-skel-brand"></span>
            <span class="lh-skel-b lh-skel-cert-kicker"></span>
            <span class="lh-skel-b lh-skel-cert-title"></span>
            <span class="lh-skel-b lh-skel-cert-line"></span>
            <span class="lh-skel-b lh-skel-cert-name"></span>
            <span class="lh-skel-b lh-skel-cert-line"></span>
            <span class="lh-skel-b lh-skel-cert-course"></span>
            <div class="lh-skel-cert-foot">
              <div>
                <span class="lh-skel-b lh-skel-cert-sig"></span>
                <span class="lh-skel-b lh-skel-line short"></span>
              </div>
              <span class="lh-skel-b lh-skel-seal"></span>
              <div>
                <span class="lh-skel-b lh-skel-cert-sig"></span>
                <span class="lh-skel-b lh-skel-line short"></span>
              </div>
            </div>
          </div>
        </div>
        <?php
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
            var fadeTimer = 0;   /* the class-drop that finishes a fade-out — show() must cancel it */
            var done = false;
            var loaded = document.readyState === 'complete';
            var fonts = !(document.fonts && document.fonts.ready && document.fonts.ready.then);

            function show() {
              if (navTimer) { clearTimeout(navTimer); navTimer = 0; }
              if (fadeTimer) { clearTimeout(fadeTimer); fadeTimer = 0; }
              doc.classList.remove('lh-skel-out');
              doc.classList.add('lh-skel-on');
            }

            function hide() {
              if (navTimer) { clearTimeout(navTimer); navTimer = 0; }
              if (!doc.classList.contains('lh-skel-on') && !doc.classList.contains('lh-skel-out')) return;
              doc.classList.add('lh-skel-out');
              if (fadeTimer) clearTimeout(fadeTimer);
              fadeTimer = setTimeout(function () {
                fadeTimer = 0;
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
