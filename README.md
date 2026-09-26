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
  - **Shut down / reopen the website** (maintenance mode): visitors see a "temporarily closed" notice (HTTP 503); only the admin can browse.
  - **Teacher access codes** (`T-XXXXXX`, one-time): a person can only register as a **teacher** with one of these codes. Student invite codes remain a teacher tool (`codes.php`).
  - **Site settings** (e-mail delivery/provider) — moved here from the teacher account; `settings.php` is admin-only.
  - **Change the admin password.**
- **Live overview**: per-course **enrolled-student counts** (with totals) and which **teachers are online right now** (3-minute presence window).
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
| `users` | id PK, name, email UNIQUE, password (hash), role ENUM(teacher/student), created_at |
| `courses` | id PK, teacher_id FK→users, title, category, description, created_at |
| `materials` | id PK, course_id FK→courses, type ENUM(file/video/youtube), title, description, filename, orig_name, mime, size, url, created_at |
| `enrollments` | course_id FK→courses + user_id FK→users (composite PK), created_at |
| `progress` | user_id FK→users + material_id FK→materials (composite PK), completed_at |
| `video_progress` | user_id + material_id (composite PK), watched_seconds, duration_seconds, position_seconds, percent, updated_at |
| `read_progress` | user_id + material_id (composite PK), depth, seconds, updated_at |
| `presence` | user_id PK, last_seen (who is online) |
| `attendance` | id PK, user_id, course_id, entered_at, left_at, ip (every visit to a course) |
| `quizzes` | id PK, material_id FK→materials UNIQUE (one quiz per lesson), title, pass_score, created_at |
| `quiz_questions` | id PK, quiz_id FK→quizzes, prompt, options (JSON array), correct (option index), sort_order |
| `quiz_results` | id PK, quiz_id FK→quizzes + user_id FK→users (UNIQUE — one attempt per student), lesson_id, quiz_title, lesson_title, correct, total, percentage DECIMAL(5,2), status ENUM(PASSED/FAILED), answers JSON, created_at |
| `quiz_progress` | quiz_id + user_id (UNIQUE), answers JSON (in-progress, saved per question — survives a closed browser) |

Uploaded files themselves live in `uploads/` (filenames are stored in `materials.filename`).

> Upgrading from the old JSON version? If `data/users.json` and `data/courses.json` exist and the database is empty, they are imported automatically on the first page load.

## Features

- 🔐 Register/login with roles (Teacher/Student), hashed passwords, session hardening, CSRF tokens on every form, and a **strong password policy** (min 8 characters with an uppercase letter, a lowercase letter and a number) enforced on registration, e-mail reset and the admin panel
- 🔑 **Forgot password** (`reset_password.php`, linked from the login page): e-mailed single-use reset link — 32 random bytes, only its SHA-256 is stored (in `user_meta`), 30-minute expiry, throttled to one e-mail per account per minute, no user enumeration (unknown addresses get the same confirmation), confirmation e-mail on change. Delivery uses the configured provider (Brevo API key in admin Settings / `config.php`); every attempt is logged to `data/mail.log`
- 📚 Teachers: create/delete courses, upload documents &amp; videos, add YouTube/Vimeo links, paste material text, delete lessons
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
- 👥 **Enrollments & attendance page** (`enrollments.php`): see every student enrolled with you, filter **by category and by course**, see who is **online** (3-minute presence window, refreshed automatically), and a **complete attendance log** per course (student, entered/left time, time spent, IP address)
- 🟢 **Live presence**: heartbeats keep `presence.last_seen` fresh; every visit to a course page is recorded in `attendance`
- 🗄️ All entities stored in MySQL via prepared statements
- ⏳ **Per-page skeleton loading screens** (`skeleton.php`): from the very first paint every page shows a frosted ghost of **its own** layout — 11 archetypes (auth card, certificate check, dashboard, course cards, course page, data table, chat, reader, quiz, live-classroom stage, certificate sheet) picked automatically from the script name + sign-in state, overridable per page. The content is already rendered server-side underneath, so the pane only makes the wait read as "this page is coming": it fades out once the page (fonts included) has loaded, and lifts early when a link or form hands over to the next page. Off switches: `?noskeleton=1` on any URL, `$lh_skeleton = false;` per page, `define('LH_SKELETON', false);` site-wide.

## Adding lessons

Open a course you teach and click **＋ Add lesson**. The modal has one tab per material type:

| Tab | What it does |
|---|---|
| 📄 **Document** | Upload one file (PDF, DOCX, PPTX, XLSX, TXT, images). The document **opens directly on the website** in its own format — no download, no text extraction. |
| ✍️ **Paste text** | Paste the whole material directly (Markdown supported). Saved as a reader page — no file needed. |
| 🎬 **Videos** | Upload **one or several video files at once** (hold Ctrl/Cmd to pick more). Each file becomes its own lesson, numbered automatically. |
| 🔗 **Links** | Paste one **YouTube / Vimeo link per line** — several video lessons are added in one go. |

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
├── index.php           landing page
├── login.php / register.php / logout.php
├── dashboard.php       role-based dashboard, hero heading greets the user by name (teacher/student)
├── courses.php         browse + search + category filter
├── course.php          course page (players, materials, progress)
├── lessons_section.php / lesson_modal.php / course_modal.php   partials
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
├── ping.php            presence heartbeat (keeps users "online")
├── presence.php        online-status lookup (JSON)
├── attendance.php      close-attendance endpoint (pagehide beacon)
├── enrollments.php     classmates by category/course + online + attendance log
├── enroll.php          enroll / leave a course
├── course_create.php / course_delete.php / delete_material.php
├── lib.php             core library (PDO connection, schema, queries, auth, uploads)
├── header.php / footer.php
├── skeleton.php        skeleton loading screens, one per page layout (11 archetypes)
├── assets/app.js       toasts, modals, tabs, search, progress
├── data/               legacy JSON storage (auto-imported once; kept web-blocked)
└── uploads/            uploaded files (web-blocked; streamed via download.php)
```

## Security notes

- Passwords hashed with `password_hash()`; session ID regenerated on login
- CSRF tokens on all forms (including the AJAX progress endpoint)
- All SQL uses **prepared statements**; all output is HTML-escaped
- Uploads validated by extension + size and stored under random names
- `data/` and `uploads/` deny direct web access — files stream through `download.php` with permission checks
- Demo/teaching project: for production add HTTPS, rate limiting, and a dedicated DB user with limited privileges

## Resetting

To start over: stop Apache, drop the `learnhub` database (e.g. in phpMyAdmin), optionally delete `uploads/` contents and `data/`, then reload — the app re-creates and re-seeds everything.
