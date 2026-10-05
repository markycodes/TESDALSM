# 🎓 LearnHub LMS

A simple Learning Management System built with **plain PHP, HTML, Tailwind CSS (CDN) and vanilla JavaScript**, backed by a **MySQL database** (XAMPP).

Teachers create courses and upload **learning materials** (PDF, DOCX, PPTX, images…), **paste whole materials as text**, and add **video tutorials** (MP4/WebM uploads or YouTube/Vimeo links). Students enroll, watch/read lessons, and track their progress.
## Private messaging & notifications

- **Private 1:1 chat** (`messages.php`): students message the teachers of their courses; teachers message their enrolled students. Two-pane responsive layout (conversation list + chat bubbles), live updates via 4s polling of `realtime.php?v=chat` (since last message id), AJAX send, timestamps, unread badges. Conversations are strictly `student_id ↔ teacher_id` pairs — `send_message.php` / `realtime.php?v=chat` enforce server-side ownership, so one student can never read another student's chat.
- **Real-time notifications** (header bell): green unread badge, dropdown panel, per-item "mark as read" (click-through to the linked page) and "Mark all as read". Generated **only from real events**: 💬 new private message, 📚 new lesson posted, 🧪 quiz assigned, 🏆/📝 quiz result. Polled every 8s via `realtime.php?v=notifications` (also carries the chat unread total). The bell badge counts **unread only**: it clears when the panel is opened (seen) or items are read, and re-appears with the fresh count as soon as a new notification arrives.
- Tables (auto-created): `conversations` (UNIQUE student↔teacher pair), `messages` (sender, body, is_read, created_at), `notifications` (user, type, title, body, link, is_read). Demo data includes one teacher→student welcome message.

## 🛡️ Main administrator

- On first run the app auto-creates the main admin account — **`admin@learnhub.local`** with a random password written to **`data/admin-credentials.txt`** (that folder is blocked from the web and git). Log in and change the password on the Admin page.
- **`admin.php` — Admin control panel** (main admin only):
  - **Shut down / reopen the website** (maintenance mode): visitors get a plain, generic
    "Website has an error" page (HTTP 503, a different error on every reload — none of the
    site's own design is shown); only the admin can browse.
  - **Teacher access codes** (`T-XXXXXX`, one-time): a person can only register as a **teacher** with one of these codes. Student invite codes remain a teacher tool (`codes.php`).
  - **Site settings** (e-mail delivery/provider) — moved here from the teacher account; `settings.php` is admin-only.
  - **Change the admin password.**
  - **"Teachers online now"** and **"Students online now"** show each person's real **profile picture** beside their name (the coloured initial when they have not uploaded one), with the same hover card everywhere else — an admin may see any account's profile, so no shared course is needed here. Both read the same `admin_online_users()` helper with a role filter, and the students list shows "N online · M total" so a quiet moment is not mistaken for an empty school.
- **🔴 Live classes now** on the admin page: every class running anywhere in the school at that moment, each row wearing its teacher's picture, with a **Join** button. The main admin may sit in on **any** live class — that is the one place the admin is deliberately admitted beyond their own courses — but **starting and ending a class stays with the teacher who owns the course**, so an observer can never close a teacher's room. That split is the whole point of the two helpers behind it: `live_class_can_join()` says who may be *in* the room (owner teacher, enrolled students, admin), and `live_class_is_host()` says who may *run* it (the owner teacher only). The admin page reads `live_classes_now()`, a read-only school-wide query — it reveals who is teaching, never a way to take a room over.
- **Live overview**: per-course **enrolled-student counts** (with totals) and which **teachers and students are online right now** (3-minute presence window).
- **🗓️ Timetable today** on the admin page: every teacher's slots for today across **all** courses at once, each row wearing that teacher's picture, with a link onward to the full calendar. It is the one screen that is not course-scoped, which is the point — it answers "who is teaching whom today" — and it reuses `schedule_teacher_course_ids(0, true)`, so the rows still come only from the ordinary course-scoped query.
- The admin can open every teacher page as well (admin passes all teacher gates).

## Requirements

- XAMPP with **Apache** and **MySQL** running
- PHP 8.0+ with the `pdo_mysql` extension (enabled by default in XAMPP)

## Quick start

1. Put this folder in `C:\xampp\htdocs\LMS` (it already is).
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.
3. Open <http://localhost/LMS/>
4. On first load the app automatically:
   - creates a MySQL database named **`learnhub`**
   - creates the 5 tables (see schema below)
   - imports any old `data/*.json` storage (one-time migration) or seeds demo data
5. Log in with a demo account:
   - 👩‍🏫 Teacher — `teacher@demo.com` / `demo123`
   - 👨‍🎓 Student — `student@demo.com` / `demo123`

Or register your own account (choose **Teacher** to upload, **Student** to learn).

## Database

Connection settings are constants at the top of **`lib.php`**:

```php
const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'learnhub';
const DB_USER = 'root';
const DB_PASS = '';   // XAMPP default is empty
```

Schema (auto-created, InnoDB, utf8mb4, foreign keys with `ON DELETE CASCADE`):

| Table | Columns |
|---|---|
| `users` | id PK, name, email UNIQUE, password (hash), role ENUM(teacher/student), bio (one "about me" line, 280 chars), avatar (filename inside `uploads/avatars/`), created_at |
| `courses` | id PK, teacher_id FK→users, title, category, description, created_at |
| `materials` | id PK, course_id FK→courses, type ENUM(file/video/youtube), title, description, filename, orig_name, mime, size, url, sort_order (lesson order; NULL = "never reordered"), created_at |
| `enrollments` | course_id FK→courses + user_id FK→users (composite PK), created_at |
| `schedules` | id PK, course_id FK→courses, teacher_id FK→users, title, kind ENUM(class/exam/activity/deadline), repeat_mode ENUM(weekly/once), weekday (0 = Sunday, weekly slots), sched_date DATE (one-off entries), start_time TIME, end_time TIME, place, notes, created_at, updated_at |
| `progress` | user_id FK→users + material_id FK→materials (composite PK), completed_at |
| `video_progress` | user_id + material_id (composite PK), watched_seconds, duration_seconds, position_seconds, percent, updated_at |
| `read_progress` | user_id + material_id (composite PK), depth, seconds, updated_at |
| `presence` | user_id PK, last_seen (who is online) |
| `attendance` | id PK, user_id, course_id, entered_at, left_at, ip (every visit to a course) |
| `quizzes` | id PK, material_id FK→materials UNIQUE (one quiz per lesson), title, pass_score, created_at |
| `quiz_questions` | id PK, quiz_id FK→quizzes, prompt, options (JSON array), correct (option index), sort_order |
| `quiz_results` | id PK, quiz_id FK→quizzes + user_id FK→users (UNIQUE — one attempt per student), lesson_id, quiz_title, lesson_title, correct, total, percentage DECIMAL(5,2), status ENUM(PASSED/FAILED), answers JSON, created_at |
| `quiz_progress` | quiz_id + user_id (UNIQUE), answers JSON (in-progress, saved per question — survives a closed browser) |
| `assignments` | id PK, course_id FK→courses, teacher_id FK→users, title, instructions, due_at (unix ts, NULL = no deadline), max_points, allow_late, created_at |
| `submissions` | id PK, assignment_id FK→assignments + user_id FK→users (UNIQUE — one hand-in per student), body, filename, orig_name, mime, size, submitted_at, grade (NULL = not yet graded), feedback, graded_at |
| `announcements` | id PK, course_id FK→courses, teacher_id FK→users, title, body, pinned, created_at |
| `lesson_posts` | id PK, material_id FK→materials, user_id FK→users, parent_id (0 = a top-level post; a reply points at its parent), body, created_at |

Uploaded files themselves live in `uploads/` (filenames are stored in `materials.filename`).
Profile pictures live in `uploads/avatars/` — the same web-blocked tree, one file per account, named after it
(`avatar7_20260101_121314_a1b2c3.jpg`); the row keeps the name, `avatar.php` streams the bytes.

> Upgrading from the old JSON version? If `data/users.json` and `data/courses.json` exist and the database is empty, they are imported automatically on the first page load.

## Features

- 🗓️ **Class schedule** (`schedule.php`): a teacher builds a timetable out of two kinds of slot — a **weekly class** (a weekday, repeating every week) or a **one-off entry** on a date (an exam, an activity, a deadline) — each tied to **one of their own courses**, with start/end time, room and an optional note. The page opens as a **month calendar**: seven columns, whole weeks only, the neighbouring months' days greyed in, today ringed, and every day that holds something showing compact chips (a dot per slot on a phone). **Every day that a teacher has something on now also carries that teacher's own profile picture** in its top corner — one face per teacher, de-duplicated (a teacher with three classes that morning wears one face, not three), capped at three, and shrinking to 14px on a phone so it never sits on top of a two-digit date. **Point at a day — or focus it with the keyboard, or tap it — and one card opens beside it with that day's own details**: each slot's time, type, title, course, room and note, a live-class link for one still to come, and "Open this day →" to jump into the week list. That card is a single element the page positions (clamped to the viewport, flipped above the day when there is no room below) rather than one per cell, so it can never be clipped by the grid; each day keeps only a `<template>` copy of its own details. The **Week** view (`?view=week`) is the same data as a list — what to read with scripting off — and the print stylesheet drops the card. Also there: previous/next month (or week), a Today button and a course filter. Students see **only the courses they are enrolled in** (every query is course-scoped, so another course's timetable can never be read), and the same list is echoed on their dashboard by `schedule_section.php`. Saving notifies that course's students (bell + e-mail) exactly like posting a new lesson; deleting is silent. One row per slot in `schedules` (`repeat_mode` = weekly/once), sidebar → **Schedule**.

- 👤 **My profile** (`profile.php`, sidebar → **My profile**, every role including the admin): one page for what a person may change about themselves — display name, e-mail address (which **is** the login name, so moving it asks for the current password and refuses an address another account already holds), a one-line "about you" (280 characters, flattened to a single line, counter while typing), their **profile picture**, and their **password** (current + new + repeat, the same strength policy as registration, session ID regenerated afterwards). No `?id=` anywhere: the page only ever reads and writes the account in the session. **The picture** is the app's only image upload: the browser squares it off from the centre and shrinks it to 512 × 512 JPEG before uploading (about 60 KB from a 12 MB phone photo — courtesy to a shared host's request cap, never a check), and the server then decides everything for itself: `getimagesize()` on the bytes, JPG/PNG/WEBP/GIF only (SVG is refused because it can carry script), 64 px minimum, 8000 px maximum, 4 MB cap, the extension taken from the content and not the filename, and re-encode + EXIF strip when the server has GD (plain XAMPP builds don't — then the validated original is stored as it is, and the picture is simply larger). `uploads/` is web-blocked, so a picture cannot be linked directly: `avatar.php?u=u7&t=<mtime>` streams it back to signed-in sessions only, with an ETag, `nosniff`, and a year of cache — `u` and `t` are deliberately not numeric `id` parameters, because the URL encryptor in `lib.php` rewrites those on every rendered page and the circle would never be cached. It shows in the top bar, the sidebar footer and the profile page; with no picture, the coloured initial is drawn as it always was (`.lh-avatar-img` / `.lh-avatar-initial` in `shell.css`, with a rim in dark mode so a photo separates from a dark bar without being tinted). The old file is unlinked only after the new row is saved, so a failed upload can never leave an account pointing at a missing picture. Scratch checks: `php _profile_check.php` (pipeline) and `php _profile_render.php plain|photo` (renders the page as a signed-in account without needing the login form)
- 🔐 Register/login with roles (Teacher/Student), hashed passwords, session hardening, CSRF tokens on every form, and a **strong password policy** (min 8 characters with an uppercase letter, a lowercase letter and a number) enforced on registration, e-mail reset and the admin panel
- 🔑 **Forgot password** (`reset_password.php`, linked from the login page): e-mailed single-use reset link — 32 random bytes, only its SHA-256 is stored (in `user_meta`), 30-minute expiry, throttled to one e-mail per account per minute, no user enumeration (unknown addresses get the same confirmation), confirmation e-mail on change. Delivery uses the configured provider (Brevo API key in admin Settings / `config.php`); every attempt is logged to `data/mail.log`
- 📚 Teachers: create/delete courses, upload documents &amp; videos, add YouTube/Vimeo links, paste material text, delete lessons, set each course's class schedule (`schedule.php`)
- ✍️ **Paste a whole material**: no file needed — the "✍️ Paste text" tab saves pasted content (Markdown supported) as a reader page
- 🎬 **Several videos in one go**: the video tab accepts **multiple files** (and the links tab accepts **one URL per line**); each video becomes its own numbered lesson
- 📄 **Documents open directly on the website**: PDF and images render natively, TXT inline, DOCX with formatting (Mammoth.js), spreadsheets as tables (SheetJS), slides as slide cards (JSZip) — students never download anything
- 🎬 Video lessons: HTML5 player for uploaded MP4/WebM with **HTTP Range support** (seeking works), embedded YouTube via the IFrame API
- 📄 **Material reader**: clicking a material opens it as a pure web page (`read.php`) — PDFs embed inline, DOCX/PPTX/XLSX/TXT/MD are converted to HTML, images display — **no downloads for students**
- 📈 **Automatic progress detection**:
  - videos → watched seconds vs. video duration; a lesson completes at **≥ 90% watched** (resume position remembered)
  - materials → scroll depth in the reader + minimum reading time (scaled to the document length); completes at **≥ 95% depth** — scrolling too fast doesn't count
  - every course's progress bar counts to exactly **100%**, no matter how many lessons it has
- 🔎 Course search + category filter (vanilla JS)
- 👥 **Enrollments & attendance page** (`enrollments.php`): a teacher sees every student enrolled with them, filter **by category and by course**, sees who is **online** (3-minute presence window, refreshed automatically), and a **complete attendance log** per course (student, entered/left time, time spent, **IP address**) — a student on the same page keeps the class list, the courses and the online dots, but only ever their **own** visits, and no IP column at all (🔐 below)
- 🔐 **Attendance is private to the person it belongs to**: the rule is drawn in the SQL and not in the markup, so figures a viewer may not read never leave the server. A **teacher** reads every visit and IP address of the courses they teach (and of the students who enrolled in them); the **main admin** reads the whole site's; a **student** reads their **own** records only — classmates stay visible as names, courses and online status, with no visit count, no last-visit time, no address. `realtime.php` (`v=roster`, `v=day`) applies the same rule to the live refresh — it answers `ownOnly: true` and omits `ip` for a student, and `assets/app.js` leaves the IP column out of the table it redraws, guided by the `data-show-ip` flag `attendance_day.php` prints, so a redrawn table can't grow back a column the page never had. The catalogue (`courses.php`) keeps its aggregate counts but hands out its live counters only on courses this account is actually in, and `attendance.php` closes a session only in a course the caller belongs to
- 🦉 **Tala, the dashboard owl** (`assets/hero.css` + `owl_news()` in `lib.php`): the banner owl introduces herself out of a cloud at her head — who she is, the greeting for the part of the day, and a QUEUE of one-line thoughts about **this** account — `owl_news()` returns `thoughts`, the ones worth acting on first (a message unread, a bell unread, the next scheduled class), then what is happening right now (who is online, visits so far today, the student falling behind, the plain tallies), up to `OWL_THOUGHTS` of them. One is on screen at a time and the next replaces it every `OWL_ROTATE_MS` = 20 seconds: no line is ever two facts glued together with a "·" any more — what used to read "0 visits today · 3 students enrolled" is now two thoughts, each in its own turn — and each one is wiped away and typed out letter by letter (`lhOwlCloud` in `assets/app.js`), opening with one small nod (`lh-owl-beat`). Between two thoughts she closes the whole cloud for a breath (`OWL_AWAY_MS` = 900 ms) and reopens it before the next line is typed — nothing is ever typed into a cloud you cannot see — and every 10 seconds she blinks: `logo/owl-eyes-close.*` is laid over her body (`.lh-hero-owl-eyes`, masked to the body's own silhouette) for a quarter of a second. The same function feeds the first render (the queue arrives as JSON in `data-live-owl-thoughts`, the visible line as `news`) and the 10-second poll (`data-live-owl-say` / `data-live-owl-news`), so the cloud and the tiles under it can never tell two different stories — and the two owners stay in their lanes: the poll refills the queue, only the 20-second timer decides which thought you are reading, so a refresh never rewrites a sentence you are halfway through. While she types, the cloud holds the size of the finished sentence — and keeps the widest it has ever held, so a shorter thought cannot slide the owl sideways either — the letters are left-aligned inside that reserved space, and screen readers are handed each whole sentence once (`data-live-owl-aria` / `.lh-owl-aria`) instead of a copy per keystroke. Under `prefers-reduced-motion` nothing is typed, nothing is held back, nothing rotates and nothing blinks — the words simply appear and stay — and a background tab spends none of her thoughts. Her name is `OWL_NAME`, written once
- 🟢 **Live presence — and a lost connection is a logout**: heartbeats keep `presence.last_seen` fresh; every visit to a course page is recorded in `attendance`. A lost internet connection counts as going offline at once, and ends the session too: `assets/app.js` hears the browser's `offline` event (or a heartbeat whose fetch fails while `navigator.onLine` is false) and flips the page offline immediately, showing a sticky 📵 pill ("you show as offline to others until the connection is back"), firing a best-effort `sendBeacon` at `ping.php` with `v=offline` — whose `offline_user()` in `lib.php` backdates `presence.last_seen` past `PRESENCE_TIMEOUT`, so everyone else's next refresh shows the user Offline the moment the link died and `close_stale_attendance()` closes the open visit at that same instant — and, on a signed-in page, POSTing `logout.php` (which closes attendance, clears presence and destroys the session). If the server can hear that request (a local server can, even with the internet off) the tab goes straight to the login page; if it cannot (a full outage) the intent is parked in localStorage under `lh-pending-logout` and a signed-out screen covers the page, and the logout finishes at the first of the `online` event, the next heartbeat (also covering browsers that miss the event), or the next load of any signed-in tab. The flag is cleared only by a signed-out page — one the server rendered with no session left — so yesterday's outage can never sign out tomorrow's login, and while the flag stands no heartbeat leaves the page at all. When the beacon cannot be delivered nothing is lost: an untouched `last_seen` ages out of the 180-second timeout by itself, which remains the fallback. While `navigator.onLine` still reports no internet the heartbeat keeps its own presence POST back — a local server answers with the Wi-Fi off, and that touch would re-mark the user green — releasing the moment `onLine` recovers; and the live polls are never paused for being offline, so a roster on the same machine keeps refreshing through the drop. For anything still standing the reconnect is unchanged: the pill drops and heartbeats fire immediately, then once more 2.5 seconds later so an offline beacon that straggles in after the reconnect cannot pin a back-online user to offline — and a guest has no session to end, so they get the pill and nothing else.
- 🗄️ All entities stored in MySQL via prepared statements
- ⏳ **Per-page loading screens** (`skeleton.php` + `assets/curtain.css` / `curtain.js`): the shipped style is a **curtain**. Every page renders its real content normally, and the data inside it — every heading, paragraph, list item, table cell, chart and image — is laid under a plain gray cover that fades away once the page has settled (fonts included), one cover after the next in a slight cascade. The boxes are never covered themselves: a card keeps its background, border and radius, a table keeps its frame and its row lines, and a cover is measured to the content box so padding and cell gutters stay — the page goes on looking like itself while it loads, and only the words turn gray. Nothing fakes a line of text, and the app shell is never part of it: the sidebar and the top bar stay painted and clickable the whole time, and `<nav>`, `<form>`, buttons and inputs are always left live. Page code can drive it directly — `showSkeletons(root)` re-covers, `hideSkeletons()` reveals — so an AJAX refresh curtains just the data it refilled (a write into a covered element takes that element's own cover with it, which is the point: the data has arrived), and `data-skeleton-skip` keeps any region clear. It lifts early when a link or form hands over to the next page, and a hard cap puts it away even if `load` never fires. With scripting off, or if the script fails to load, no cover is ever created and the page simply shows. Off switches: `?noskeleton=1` on any URL, `$lh_skeleton = false;` per page, `define('LH_SKELETON', false);` site-wide. `define('LH_SKELETON_STYLE', 'pane');` brings back the earlier full-screen ghost (11 per-layout archetypes, still in `skeleton.php`) if you want to compare them. On a local server the covers are up for well under a second — correct, but hard to catch: add `?curtainhold=1500` to any URL to keep them up that long past "ready", or `define('LH_SKELETON_MIN', 600);` to lift the floor everywhere. `assets/curtain-fixture.html` shows the same thing on a page of its own.
- 🧊 **3D study stack on the landing page** (`assets/book3d.css` + `assets/book3d.js`): the pile of books with a mortarboard under the hero is **real CSS 3D** — a camera, `preserve-3d`, six faces per solid — so it needs no WebGL library, no model file and no CDN, costs the page no extra requests beyond those two files, and stays sharp at any pixel density. Drag it (or use the arrow keys) to look around; vertical swipes still scroll the page (`touch-action: pan-y`). It shrinks for phones, **parks itself when scrolled out of view**, and stands still under `prefers-reduced-motion`. The turn is a CSS animation, so the stack keeps moving with scripting off, and the script only pauses that animation and moves its `currentTime` — which is why letting go continues from the angle you stopped at instead of jumping back. Add a solid to the pile with one row of the `$lh3d_solids` array in `learnhub.php`; the six faces come from its custom properties. `assets/book3d-fixture.html` reports what the browser computed (face matrices, animation state, a simulated drag) for checking it without eyeballing pixels.
- 🪶 **A light page shell** (`assets/shell.css`): the design system — every `.lh-*` rule, the sidebar, the cards,
  the buttons — is one external stylesheet that `header.php` links, instead of a 40 KB `<style>` block inside every
  page. The bytes are identical on every page, so as a file the browser downloads it once and reuses it on every
  navigation; as inline CSS it travelled in every single response and was re-parsed before each first paint. A page
  is now ~18 KB of HTML where it used to be ~78 KB. Like the theme, density and layout files, its URL carries the
  file's own mtime (`assets/shell.css?v=…`), so an edit shows up at once with no hard refresh and no stale CSS.
  Load order is deliberate: `tailwind.min.css` → `shell.css` → theme → density → layout, so the picks in
  **Settings → Appearance** keep winning the cascade exactly as before.

## Appearance

Three independent picks live in **Settings → Appearance** (admin only), each one a small stylesheet that is
loaded after the base design — `assets/shell.css`, the design system every page shares. They stack — any design
works with any density and any layout — and switching back is instant, because nothing about the content or the
logic changes.

| Pick | Who decides | Options |
|---|---|---|
| **Design** | `ui_theme_choices()` in `lib.php` → `assets/theme-<key>.css` | Paper, Material (default), Console, Fresh & friendly, Calm studio, Minimal |
| **Density** | `ui_density_choices()` → `assets/density-<key>.css` | Compact (default), Comfortable |
| **Layout** | `ui_layout_choices()` → `assets/layout-<key>.css` | Classic shell (default), Wide canvas, Focused column, Icon dock, Top navigation, Data first, Floating panels |

**Layouts** arrange the logged-in shell: where the navigation sits, how wide the content column is, how much
chrome surrounds it, how tables read.

| Layout | What it changes |
|---|---|
| **Classic shell** | What LearnHub ships — fixed sidebar, sticky top bar, 1152px column. Loads no extra file. |
| **Wide canvas** | 248px rail, content up to **1800px**, fluid gutters, stat tiles reflow into as many columns as fit. For big monitors. |
| **Focused column** | **1088px** centred column, roomier leading, more air between cards, quieter top bar. For reading a lesson or taking a quiz. |
| **Icon dock** | The sidebar is permanently the **76px icon rail** with hover labels; the fold buttons stand down. For laptops. |
| **Top navigation** | No sidebar — the nav links become one horizontal strip under the top bar, content starts at the left edge. |
| **Data first** | Slim 52px chrome plus edge-to-edge tables: zebra rows, tinted uppercase column heads, tabular figures, hover row, taller list panels. |
| **Floating panels** | Rail, top bar and content become rounded panels inset 12px over the theme's canvas. |

Layouts are **desktop only (≥1024px)** on purpose: phones keep the drawer, the swipe rows and normal document
scrolling, so an admin can never lock a phone out of the menu. `<html>` carries `data-theme`, `data-density` and
`data-layout` so a layout can respect a theme whose shell it must not fight — Material's floating rail and
inner-scroll frame, for example.

To add another one:

1. Create `assets/layout-<key>.css`. Scope it to `.lh-app` and, for shell geometry, to `@media (min-width:1024px)`.
   Use `html body.lh-app … { … !important }` for anything a theme or the density layer also sets — layouts load
   last, so on equal specificity the layout wins.
2. Add a `'<key>' => ['Name', 'One or two sentences: what changes and when to pick it.']` entry to
   `ui_layout_choices()` in `lib.php`.

That is the whole change: the picker, the `<link>`, the cache-busting stamp and the wireframe thumbnail slot
appear automatically (a key with no thumbnail simply falls back to the classic frame). A missing file is never an
error — the built-in shell still styles every page — and `config.php` can pin a choice for a machine with
`define('UI_LAYOUT', 'dock');` (`UI_THEME`, `UI_DENSITY` work the same way).

### Dark mode

The sun/moon button at the right end of the top bar switches the whole app between a light and a dark **scheme**.
It is deliberately the fourth *independent* axis rather than a seventh design, an eighth density or a ninth layout,
and it is deliberately **not** in Settings:

* **A scheme is about the room, an account is about the person.** Someone marking coursework at 23:00 and the same
  person at a sunlit desk at 09:00 want different canvases, and a shared staff machine wants both. So the choice is
  one class on `<html>` plus one `localStorage` key (`lh-scheme`) — no column, no migration, nothing to leak if
  someone else sits down.
* **It composes with all three picks.** Any design, density and layout can be dark, which is why it is a sheet of
  overrides rather than another theme: `assets/dark.css` re-declares the design tokens (`--lh-ink`, `--lh-body`,
  `--lh-mut`, `--lh-line`, `--lh-paper`, `--lh-deep` …) that the theme sheets already read, then mirrors the
  surfaces each theme pins with `!important` — the same selector, prefixed `html.dark`, one step above it.

| File | Job |
|---|---|
| `assets/dark.css` | The whole scheme: palette, shell chrome, theme mirrors, the ~60 Tailwind utilities the markup actually uses, the reveal's keyframes, and the print reset. Loaded **last** in `header.php`, every rule scoped `html.dark`. |
| `assets/dark.js` | Applies the change and animates it. Also on every page, deferred. `window.lhScheme.get() / .set('dark') / .toggle(button)` if anything else ever wants to drive it. |
| the `<script>` in `header.php`'s `<head>` | Reads `lh-scheme` before the first stylesheet paints, so a dark page never arrives light and changes afterwards. |

The change is a **circular reveal opening from the corner the button sits in**. `dark.js` measures the button that
was pressed and writes `--lh-wipe-x / -y / -r` on `<html>` — the radius is the distance to the farthest pixel, so
no corner is ever caught mid-change — and `dark.css` clips the incoming page into it:

1. **`document.startViewTransition()`** where the browser has it: the new page is genuinely unrolled over the old
   one through the widening circle, with the cross-fade the user agent would prefer switched off.
2. No view transitions → the same circle cut out of one flat sheet of the incoming canvas colour (measured from the
   page's own background, not hard-coded), with the real page swapped in underneath once it is covered.
3. `prefers-reduced-motion: reduce` → no animation at all. The page simply changes.

Two things are worth knowing. **Print stays white**: nearly every rule in `dark.css` reads a token, so `@media print`
hands the light values back in one place — plus a short list of hand-painted surfaces that could not be reached that
way, and a certificate printed from a dark session is unchanged. **Brand colours are not touched** — emerald buttons,
their white labels and the indigo form accents read as well on a dark canvas as on a light one; a scheme that
recoloured the brand would be a theme.

Some designs do more than read the tokens. material paints the canvas behind the content a flat `#f5f6f8` and pins a
near-black footer bar to the bottom of the window, console squares its hero sheet and its "plain" wells to `#fff`,
paper rules a cream seam under the wordmark and writes its kicker in green. Section **4b** of `dark.css` answers each
of those selector-for-selector, in the theme's own shape with `html.dark` in front of it — which wins on specificity
instead of on a longer shout. One of them has to *agree* rather than overwrite: material draws no hero card at all,
so `html.dark[data-theme="material"] .lh-hero-paper` stays transparent. Darkening it there would be the scheme
designing, which is the one thing a scheme may not do.

To extend it (a new page with a surface the scheme has not met): find the rule that paints it — `Ctrl+Shift+C` names
both the class and the sheet it came from — and reproduce it with `html.dark` in front of it, `!important` if the
original used it, putting the colour in a token if it is a surface rather than a one-off. The utilities section
explains why a variant (`hover:bg-slate-100`) needs its own line, why a ring is fixed by setting `--tw-ring-color`,
and why a divider needs the utility's own sibling selector. `assets/dark-fixture.html` is the page to eyeball it on:
one of everything including the hand-painted pieces above, a design picker that swaps sheets without a reload, a
readout of what the page thinks is happening, `?sheet=1` to see the reveal's fallback route, and no database, no
login. It is dev-only and safe to delete.

## Adding lessons

Open a course you teach and click **＋ Add lesson**. The modal has one tab per material type:

| Tab | What it does |
|---|---|
| 📄 **Document** | Upload one file (PDF, DOCX, PPTX, XLSX, TXT, images). The document **opens directly on the website** in its own format — no download, no text extraction. |
| ✍️ **Paste text** | Paste the whole material directly (Markdown supported). Saved as a reader page — no file needed. |
| 🎬 **Videos** | Upload **one or several video files at once** (hold Ctrl/Cmd to pick more). Each file becomes its own lesson, numbered automatically. |
| 🔗 **Links** | Paste one **YouTube / Vimeo link per line** — several video lessons are added in one go. |

## Assignments, submissions & grading

Every course page now carries **📝 Assignments**, **📣 Announcements**, **📊 Gradebook** and **💬 Discussions** beside its lessons.

- **Assignments** (`assignment.php`) — the owning teacher writes a brief (instructions, marks out of, optional deadline, late work on or off) and publishes it; the same form edits it later, so a corrected brief never leaves a stale copy in students' hands. Enrolled students read the brief and hand in **written work and/or a file**. A hand-in can be replaced until it is graded, then it is locked.
- **Attachments** — a hand-in file is never taken on the client's word: the real type is sniffed from the **bytes** (`getimagesize()`, then `finfo`), and only allow-listed types are kept. The stored name is generated at random and its extension is taken **from the sniffed type, not from the uploaded name**, so a renamed `.php` cannot survive as one. Hand-ins are capped at 25 MB, and the bytes come back only through `download_submission.php` — which serves **the student who handed it in, and the teacher of that course** (plus the main admin, as everywhere else). A student cannot open a classmate's hand-in even inside the same course, and nothing is ever rendered inline, so an upload can neither be executed nor used to serve a stored page.
- **Grading** — the teacher's queue lists every hand-in with its text and attachment. A grade plus written feedback is saved, the student is notified, and the mark shows on both the assignment and the gradebook.
- **Announcements** (`announcements.php`) — a course notice that reaches everyone: the teacher posts a title and message, optionally pins it to the top, or deletes it. Publishing writes a notification row per enrolled student, so the bell in the top bar lights up.
- **Lesson discussions** (`discussion.php`) — a thread per lesson: a question from a student, replies from anyone enrolled or from the course's own teacher. The course's teacher (or the main admin) moderates; **a teacher from another course cannot**, so no one can walk into a class they do not teach and clear its thread. Deleting a question removes the replies under it; deleting one reply removes only that reply.
- **Gradebook** (`gradebook.php`) — quizzes and graded assignments, each counting half, with a pass at 75%. A half with nothing in it is left out of the average rather than counted as zero, so a course with no assignments is never punished. **The teacher sees the class; a student sees exactly one row — their own.**
- **Search** (`search.php`) — courses and lessons, scoped to what the account may actually see: students to their enrolments, teachers to their own courses, the main admin to everything. An account whose role is anything unexpected falls back to the enrolment scope, never to "see it all".
- **Offline copy** (`offline.php`) — one ZIP per course with its lessons and documents, so a class can be taken offline. Written by a small built-in ZIP writer, so it needs no `php-zip` extension, and large lessons stream rather than being held in memory.
- **Organise lessons** (`bulk.php`) — tick any number of lessons and **move them to the top**, **move them to the end**, or **delete** them (which also removes their quiz and their discussion). Reordering renumbers the whole course rather than poking one row, so there is never a lesson left sitting at "unsorted" next to numbered ones.

> Lessons that were never reordered keep their original order: `sort_order` stays `NULL` and the old id order applies, so existing courses are untouched until a teacher reorders them.

## Lesson quizzes

Every lesson can carry a **multiple-choice quiz** taken **once** per student:

- **Teachers** click **🧪 Assign quiz / Quiz (N)** on any lesson card (videos and materials) to open the quiz editor: title, pass score (50–100%, default **70%**), and up to 20 questions with 2–4 options each and a marked correct answer. Saving replaces the quiz (and any in-progress attempt); **🗑 Remove this quiz** deletes it.
- **Students** see a 🔒 *“Quiz — complete the lesson to unlock”* chip while the lesson is unfinished. As soon as the lesson is completed, the chip flips to a green **🧪 Take the lesson quiz** button.
- **One attempt, locked per question**: the quiz is a one-question-at-a-time wizard. Each answer is **saved server-side immediately** (closing the browser keeps progress) and **locked** — no back button, no editing, and the whole quiz can only be completed once. `quiz_answer.php` rejects re-answered questions and post-completion submissions; the DB enforces it with a UNIQUE(quiz, student) key.
- **Score & status**: on the final answer the quiz is graded — score `x/y`, percentage (2 decimals), and status **PASSED/FAILED** against the quiz's pass score — and stored in `quiz_results` (the shared records source).
- **Server-side gate**: `quiz.php` and `quiz_answer.php` both run through `require_quiz_access()` — 403 “Quiz locked” until the lesson is completed; correct answers never reach the browser before submission.
- **📋 My Records** (`my_records.php`, student nav): greeting, summary (taken / passed / failed / average %), All/Passed/Failed filters, and the full history (quiz title, lesson, score, %, status badge, date) — always filtered to the signed-in student's own rows.
- **👤 Student Records** (`quiz_records.php`, teacher nav): the same data grouped alphabetically by student with collapsible cards, name search, summary chips, and the per-quiz table. Teacher-only route.
- The owning teacher can **preview** the quiz any time with correct answers highlighted (never recorded). The demo course ships with a quiz on Lesson 1.

## Upload size limits

The included `.htaccess` raises PHP limits to **4 GB** (works with XAMPP's default `mod_php`; the matching `.user.ini` covers CGI/FastCGI setups). A 1 GB fallback applies on 32-bit PHP.
If pages return **HTTP 500** after this file was added, your PHP runs as CGI/FastCGI:

1. Delete the `php_value …` lines from `.htaccess`
2. Edit `C:\xampp\php\php.ini` instead: `upload_max_filesize`, `post_max_size`, `max_execution_time`, `max_input_time`
3. Restart Apache

## Folder structure

```
LMS/
├── home.php            public front page — what /LMS/ itself serves (DirectoryIndex)
├── learnhub.php        the "How it works" tour (was index.php; /index and /index.php 302 to /home)
├── about.php           the school, the platform and how to reach it
├── faq.php             the questions in groups — <details> accordions + FAQPage structured data
├── login.php / register.php / logout.php
├── dashboard.php       role-based dashboard, hero heading greets the user by name (teacher/student)
├── courses.php         browse + search + category filter
├── course.php          course page (players, materials, progress)
├── lessons_section.php / lesson_modal.php / course_modal.php   partials
├── schedule_section.php  partial: the dashboard card that echoes the schedule (today + next up)
├── quiz_modal.php        quiz editor modal (teacher)
├── quiz_save.php / quiz_delete.php   assign / replace / remove a lesson quiz
├── quiz.php              take a lesson quiz (gated; one attempt, locked per question)
├── quiz_answer.php       per-question lock endpoint (same gate, rejects re-answers)
├── my_records.php        student "My Quiz Records" (own results only)
├── quiz_records.php      teacher "Student Quiz Records" (grouped, searchable)
├── upload.php          handles document / pasted-text / multiple video(s) / multiple link(s) uploads
├── download.php        secure, range-aware file streaming
├── read.php            material reader (in-browser, scroll-tracked)
├── watch.php           video watch-time endpoint (auto-complete at 90%)
├── read_progress.php   reading-progress endpoint (auto-complete at 95% depth)
├── ping.php            presence heartbeat (keeps users "online"); POST v=offline = "I lost the internet, drop me from presence now"
├── presence.php        online-status lookup (JSON)
├── attendance.php      close-attendance endpoint (pagehide beacon)
├── enrollments.php     classmates by category/course + online + attendance log
├── enroll.php          enroll / leave a course
├── schedule.php        the class timetable — a month calendar (hover a day for its details) plus a week list
├── course_create.php / course_delete.php / delete_material.php
├── lib.php             core library (PDO connection, schema, queries, auth, uploads, profile)
├── profile.php         My profile — name, e-mail, about line, profile picture, password
├── avatar.php          streams a profile picture to signed-in sessions (uploads/ is web-blocked)
├── header.php / footer.php
├── skeleton.php        loading screens: the curtain (shipped) + the legacy pane
├── assets/app.js       toasts, modals, tabs, search, progress
├── assets/curtain.css / curtain.js   the loading curtain (covers + reveal)
├── assets/curtain-fixture.html       dev-only harness for the curtain (open it in a browser)
├── assets/book3d.css / book3d.js     the landing page's 3D study stack (CSS 3D + drag to look)
├── assets/book3d-fixture.html        dev-only harness for that scene (open it in a browser)
├── assets/hero.css       the banner owl on the dashboard (Tala): body and wing on one canvas, the wing's wave, and the three-line cloud she greets you from — name, greeting, and a line of news the dashboard's own poll keeps fresh
├── assets/shell.css      the design system every page uses (loaded first of the CSS files)
├── assets/theme-*.css   the design (Settings → Appearance → Design)
├── assets/density-*.css the spacing rhythm (Settings → Appearance → Density)
├── assets/layout-*.css  the shell arrangement (Settings → Appearance → Layout)
├── data/               legacy JSON storage (auto-imported once; kept web-blocked)
└── uploads/            uploaded files (web-blocked; streamed via download.php)
```

## Deploying with the folders outside `public_html`

Shared hosts (Hostinger and its like) hand you a `public_html` and expect everything web-facing to
live in it. Half of this app's folders want the opposite: `data/` and `uploads/` are storage, not
pages, and should never be web-reachable at all, while `assets/`, `logo/` and `signature/` sit
happily above the document root too — out of reach of directory listings and of anything that would
serve them as PHP. The layout on the server is this:

```
/home/uXXXXXXXX/domains/example.com/      the storage root — Apache serves nothing here
├── assets/
├── data/                written by PHP; blocked from the web the moment it is created
├── logo/
├── signature/
├── uploads/             ditto
├── config.php           optional, above the docroot: the MySQL password,
│                        API keys and Turnstile secret never enter public_html
└── public_html/         ONLY the *.php files, .htaccess and .user.ini
```

That is the whole deploy — plus **one line** in `config.php` telling the app where the folders
went (whichever of the two places the config file itself is in — `paths.php` looks for it in both):

```php
define('LH_STORAGE_DIR', dirname(__DIR__));   // config.php inside public_html
define('LH_STORAGE_DIR', __DIR__);            // config.php above public_html
```

An absolute path to the storage root works as well, for a host that keeps the folders somewhere
else (a second disk, a subfolder such as `private/`). With no config at all the storage root is the
PHP folder itself, so the classic XAMPP layout is unchanged.

How one line is enough:

- **`paths.php`** is the only file that knows where anything lives; `lib.php` and `asset.php` both
  start with it and never build a path by hand. It loads `config.php` first — beside the PHP, then
  one level up — and then resolves each folder: a pin from config (`LH_ASSET_DIR`, `LH_LOGO_DIR`,
  `LH_SIGNATURE_DIR`, `LH_DATA_DIR`, `LH_UPLOAD_DIR`) wins, otherwise the storage root when it
  really holds a folder of that name, otherwise the folder beside the PHP.
- **Clean URLs**: the address bar shows `/courses`, never `/courses.php`. Two halves, and they
  cover each other. **The pages print the clean form themselves** — `lh_url_encrypt_html()` in `lib.php`
  is an output filter that runs over every rendered page and rewrites `href`/`src`/`action` (and the
  same URLs inside inline script) so no link ever costs a redirect. **`.htaccess` catches the rest** —
  rule 1 maps an extensionless path to the real script internally, and rule 2 302s a typed or
  bookmarked `.php` address back to the clean one, so old links and shared URLs keep working. The few
  `Location:` headers cannot be reached by an output filter (headers are not part of the buffered
  body), so they call `lh_url_clean()` directly — `logout.php` needs that, because `.htaccess`
  deliberately does not redirect `logout.php` and a `.php` header there would bounce back to itself.
  The scripts on the **no-redirect list** (`avatar.php`, `realtime.php`, `ping.php`, `presence.php`,
  `watch.php`, `read_progress.php`, `live_class.php`, the readers and the uploaders) keep their
  extension on purpose: they are fetched constantly, and doubling polling traffic with a redirect
  pair is waste that can also trip a shared host's rate limiting or security challenge.
- **`asset.php`** is what keeps every existing URL working while the folders are above the
  document root. A rule in `.htaccess` hands `assets/`, `logo/` and `signature/` to it **only when
  the file is not under the docroot** (`RewriteCond %{REQUEST_FILENAME} !-f`), as an internal
  rewrite — the address bar never changes, so a stylesheet's relative `url(../logo/owl-body.png)`
  still resolves. When the folders are beside the PHP, the condition fails and Apache serves the
  bytes itself: no PHP runs, exactly as before.
- The gateway is deliberately tiny: it includes `paths.php` and nothing else — no session, no
  database — so a CSS request can never be turned away by the boot-time maintenance gate. It
  accepts only those three folder names, only extensions it knows (never `.php`), only files that
  resolve inside those folders after `realpath()`, and it does the caching work the filesystem and
  `mod_deflate` used to give away: `ETag` + `Last-Modified`, `304` on `If-None-Match`, gzip for
  text, a week of `Cache-Control`, `HEAD`.
- **`data/` and `uploads/` never touch HTTP.** They are not in the rewrite rule, not in the
  gateway's allowlist, and `ensure_storage()` creates them (with their `.htaccess` + `index.html`
  blockers) in the storage root — never inside `public_html`. Files reach the user through
  `download.php`, `avatar.php` and `read.php`, which read them from the same constants and check
  permissions first.
- **Nothing on a page changed**: the markup, templates and stylesheets are untouched and the URLs
  are relative and identical in both layouts. The only edits were the four places that used to
  build a path from `__DIR__` (`header.php`, `dashboard.php`, `learnhub.php`, `skeleton.php`), and
  they now ask `lh_path()` for what `__DIR__` gave them — so the same URLs keep working from
  either folder.

Uploading:

1. Copy the `*.php` files, `.htaccess` and `.user.ini` into `public_html/`.
2. Copy `assets/`, `logo/`, `signature/`, `data/` and `uploads/` into the storage root — the folder
   that holds `public_html/`. `data/` and `uploads/` only need their blocker files — the app
   creates both folders there itself on the first run if they are missing.
3. Copy `config.sample.php` to `config.php` (above `public_html/` if you want it out of the
   docroot), fill in the database values from the hosting panel, and add the `LH_STORAGE_DIR` line.
   On **Hostinger** the database host is **`localhost`** (hPanel says so itself) — `srvNNN.hstgr.io`
   is only for connecting from your own computer, and even then your IP must first be allowed under
   Websites → Dashboard → *Remote MySQL*. A wrong host or password shows the grey
   *🗄️ Database not reachable* page; the exact reason is always appended to `data/error.log`
   (read it in hPanel → File Manager, it is not reachable over HTTP).
4. Make sure the storage root's `data/` and `uploads/` are writable by PHP (755, or 775 when the
   panel lets you choose).

## Security notes

- Passwords hashed with `password_hash()`; session ID regenerated on login
- CSRF tokens on all forms (including the AJAX progress endpoint)
- All SQL uses **prepared statements**; all output is HTML-escaped
- Uploads validated by extension + size and stored under random names
- **Profile pictures** are validated by their **content** (`getimagesize()`), not their filename: SVG and renamed
  scripts never get in, the stored extension comes from the bytes, and the file is re-encoded (EXIF stripped) when
  the server has GD. `avatar.php` serves a picture to the account it belongs to, to an admin, and to the teacher of a
  course that account joined — never to a guest, never as a listing, and never on the strength of a user number alone:
  `can_view_profile_of()` in `lib.php` is the one rule, and the pages that print an `<img>` consult it too, so a
  picture that would be refused is never even linked
- **Profile hover card**: the rule runs **both ways along one shared course**. Where a teacher already sees a student's
  name — the class roster (`enrollments.php`), a day's attendance (`attendance_day.php`), their chat list
  (`messages.php`) — pointing at the name or the circle floats a small card: the picture, the name, the role, the
  about line, the month they joined. The same goes the other way: a **student** gets the card for **the teacher of a
  course they are enrolled in**, which is how a course card, a course page, the dashboard's "Continue learning" or a
  timetable slot can show a face instead of a bare name (`course_teacher_html()` / `course_teacher_chip()` in
  `lib.php`, drawn wherever a course names its teacher). The words arrive with the page (`profile_hover_attrs()` /
  `profile_hover_html()`); `app.js` paints ONE floating card and moves it, and builds it with `textContent`, so an
  about line stays words. Beyond that shared course there is nothing: **no card for a person you share no course
  with** — a student never sees a classmate, an unrelated teacher is still only an initial, and there is no directory
  or listing of people anywhere. `can_view_profile_of()` is the single decision, and `avatar.php`, the circle and the
  card all consult it, so a picture that would be refused is never even linked.
- `data/` and `uploads/` deny direct web access — files stream through `download.php` with permission checks,
  and pictures through `avatar.php`
- Demo/teaching project: for production add HTTPS, rate limiting, and a dedicated DB user with limited privileges

## Resetting

To start over: stop Apache, drop the `learnhub` database (e.g. in phpMyAdmin), optionally delete `uploads/` contents and `data/`, then reload — the app re-creates and re-seeds everything.
