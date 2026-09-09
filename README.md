# 🎓 LearnHub LMS

A simple Learning Management System built with **plain PHP, HTML, Tailwind CSS (CDN) and vanilla JavaScript**, backed by a **MySQL database** (XAMPP).

Teachers create courses and upload **learning materials** (PDF, DOCX, PPTX, images…), **paste whole materials as text**, and add **video tutorials** (MP4/WebM uploads or YouTube/Vimeo links). Students enroll, watch/read lessons, and track their progress.
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

Uploaded files themselves live in `uploads/` (filenames are stored in `materials.filename`).

> Upgrading from the old JSON version? If `data/users.json` and `data/courses.json` exist and the database is empty, they are imported automatically on the first page load.

## Features

- 🔐 Register/login with roles (Teacher/Student), hashed passwords, session hardening, CSRF tokens on every form
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

## Adding lessons

Open a course you teach and click **＋ Add lesson**. The modal has one tab per material type:

| Tab | What it does |
|---|---|
| 📄 **Document** | Upload one file (PDF, DOCX, PPTX, XLSX, TXT, images). The document **opens directly on the website** in its own format — no download, no text extraction. |
| ✍️ **Paste text** | Paste the whole material directly (Markdown supported). Saved as a reader page — no file needed. |
| 🎬 **Videos** | Upload **one or several video files at once** (hold Ctrl/Cmd to pick more). Each file becomes its own lesson, numbered automatically. |
| 🔗 **Links** | Paste one **YouTube / Vimeo link per line** — several video lessons are added in one go. |

## Upload size limits

The included `.htaccess` raises PHP limits to **512 MB** (works with XAMPP's default `mod_php`).
If pages return **HTTP 500** after this file was added, your PHP runs as CGI/FastCGI:

1. Delete the `php_value …` lines from `.htaccess`
2. Edit `C:\xampp\php\php.ini` instead: `upload_max_filesize`, `post_max_size`, `max_execution_time`, `max_input_time`
3. Restart Apache

## Folder structure

```
LMS/
├── index.php           landing page
├── login.php / register.php / logout.php
├── dashboard.php       role-based dashboard (teacher/student)
├── courses.php         browse + search + category filter
├── course.php          course page (players, materials, progress)
├── lessons_section.php / lesson_modal.php / course_modal.php   partials
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
