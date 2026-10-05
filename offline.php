<?php
/**
 * Offline copy — every lesson of every course this person may read, packed into
 * one ZIP they can keep on a phone or laptop for a commute or a spotty connection.
 *
 * What goes in is decided by exactly the same rules as the pages: a student gets
 * their enrolled courses, a teacher their own, the admin everything. Anything the
 * viewer could not open online is not in the bundle either — this endpoint only
 * re-reads materials it has already proved they may see.
 *
 * Only real lesson files are packed (documents and uploaded videos). A YouTube
 * or Vimeo lesson is a link, so it goes into the manifest as an address rather
 * than as a pretend local file — a bundle full of dead embeds would be worse than
 * a bundle that says plainly which lessons need a connection.
 */
require_once __DIR__ . '/lib.php';
$user = require_login();
$userId = (int) $user['id'];
$role = (string) ($user['role'] ?? '');

/* Which courses may go in this bundle? can_view_lessons() is deliberately
   permissive for the LESSON LIST (an unenrolled student may still see a course
   card and its lock screen), so it is too wide a gate for a download that hands
   over the actual files. Narrow it here, or any student could pull down every
   course on the site by guessing an id. */
$courses = array_values(array_filter(load_courses(), function (array $c) use ($user, $role, $userId): bool {
    if (!can_view_lessons($c, $user)) return false;
    if ($role === 'admin') return true;                       /* the main admin sees all */
    if ($role === 'teacher') return (int) ($c['teacher_id'] ?? 0) === $userId;
    /* students: enrolled only. is_enrolled_id() is the same check the lessons
       themselves apply before showing any material. */
    return $c['id'] > 0 && is_enrolled_id((int) $c['id'], $userId);
}));
if (!$courses) {
    set_flash('error', $role === 'student'
        ? 'You are not enrolled in a course yet, so there is nothing to download.'
        : 'You have no courses to download yet.');
    header('Location: courses.php');
    exit;
}

/* Which courses did they ask for? The choice is the viewer's: a phone that
   cannot hold four courses should not be handed all four. Ids are cast to int
   and matched against the list already filtered by can_view_lessons(), so a
   crafted ?c=9 can never reach a course this account may not read — an unknown
   id is dropped, not fetched. */
$available = [];
foreach ($courses as $c) $available[(int) $c['id']] = $c;

$asked = $_POST['courses'] ?? ($_GET['c'] ?? []);
if (!is_array($asked)) $asked = [$asked];
$picked = [];
foreach ($asked as $raw) {
    $id = (int) $raw;
    if ($id > 0 && isset($available[$id])) $picked[$id] = $available[$id];
}
/* A ticked box is a decision. Anything not ticked — or an id this account may
   not read, which is dropped above — stays out of the bundle. This used to fall
   back to "everything", so a typo, a stale form or a crafted request quietly
   produced a full-site download instead of what was asked for. */
$step = (string) ($_POST['step'] ?? $_GET['step'] ?? 'choose');
$post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

/* The notice is the whole point, so it cannot be walked past. Without this a
   plain GET of ?step=download would skip it — and worse, a third-party page
   could trigger a large download in someone's browser. Both steps that can move
   data are POST + this session's CSRF token, so the notice AND the user's own
   click stand between them and a surprise download. */
if ($post && ($step === 'download' || $step === 'plan')) {
    verify_csrf();
}
if ($step === 'download' && !$post) {
    header('Location: ' . lh_url_clean('offline.php'));
    exit;
}

/* Moving past the chooser with nothing valid chosen is a mistake, not an
   instruction to download the whole site — send them back to tick something.
   This applies to the download step too: an empty or wholly-unauthorised set
   would otherwise stream an archive of nothing at best, and a fallback to
   "everything" would stream the entire catalogue. */
if ($post && !$picked) {
    set_flash('error', 'Tick at least one course to download.');
    header('Location: ' . lh_url_clean('offline.php'));
    exit;
}

/* ---------- step 1: choose the course(s) ---------- */
if ($step === 'choose') {
    $page_title = 'Offline copy';
    $nav_active = 'offline';
    require __DIR__ . '/header.php';
    ?>
    <div class="mx-auto max-w-3xl">
      <div class="reveal">
        <h1 class="text-2xl font-bold text-slate-900">📥 Offline copy</h1>
        <p class="mt-2 text-sm leading-6 text-slate-600">Take your lessons with you — a phone on a commute, a spotty
          connection, a flight. Tick the <?= count($courses) === 1 ? 'course' : 'courses' ?> you want and they are packed
          into one ZIP you can keep on your device.</p>
      </div>

      <form method="post" action="<?= e(lh_url_clean('offline.php')) ?>" class="reveal mt-6">
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="plan">
        <ul class="space-y-2">
          <?php foreach ($courses as $c):
              $cid = (int) $c['id'];
              $n = count($c['materials'] ?? []);
          ?>
            <li>
              <label class="flex cursor-pointer items-start gap-3 rounded-2xl bg-white p-4 ring-1 ring-slate-200 hover:ring-emerald-300">
                <input type="checkbox" name="courses[]" value="<?= $cid ?>" <?= isset($picked[$cid]) ? 'checked' : '' ?>
                  class="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600">
                <span class="min-w-0 flex-1">
                  <span class="block font-semibold text-slate-800"><?= e((string) $c['title']) ?></span>
                  <span class="mt-0.5 block text-xs text-slate-400">📦 <?= $n ?> lesson<?= $n === 1 ? '' : 's' ?></span>
                </span>
              </label>
            </li>
          <?php endforeach; ?>
        </ul>
        <div class="mt-5 flex flex-wrap items-center gap-3">
          <button class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Continue
            →</button>
          <a href="<?= e(lh_url_clean('courses.php')) ?>" class="text-xs font-semibold text-slate-400 hover:text-emerald-700">Cancel</a>
        </div>
        <p class="mt-3 text-[11px] text-slate-400">Videos hosted on YouTube or Vimeo are only saved as a web address —
          they still need a connection to watch. Everything else is a real file you keep.</p>
      </form>
    </div>
    <?php
    require __DIR__ . '/footer.php';
    exit;
}

/* ---------- step 2: work out what is in the bundle ---------- */
/* Collect first, build second: a course with no local file should not fail the
   whole download, so nothing is written until the plan is known to be sound. */
$files = [];      /* [storedPath, nameInZip] */
$manifest = [];   /* what went in, and what did not */
$missing = 0;
$videoLinks = 0;

foreach ($picked as $cid => $c) {
    $cid = (int) $c['id'];
    $entry = ['title' => (string) $c['title'], 'lessons' => []];
    foreach (($c['materials'] ?? []) as $m) {
        $type = (string) ($m['type'] ?? '');
        $title = (string) ($m['title'] ?? 'Lesson');

        if ($type === 'youtube' || $type === 'video') {
            $url = (string) ($m['url'] ?? '');
            if ($type === 'video' && $url === '') {
                /* an uploaded video: the real file, if it is still on disk */
                $path = UPLOAD_DIR . '/' . basename((string) ($m['filename'] ?? ''));
                if (is_file($path)) {
                    $files[] = [$path, 'courses/' . $cid . '/' . basename($path)];
                    $entry['lessons'][] = ['title' => $title, 'file' => 'courses/' . $cid . '/' . basename($path)];
                } else {
                    $missing++;
                    $entry['lessons'][] = ['title' => $title, 'note' => 'video not on the server'];
                }
            } else {
                $videoLinks++;
                $entry['lessons'][] = ['title' => $title, 'url' => $url];
            }
            continue;
        }

        /* a pasted-text lesson is already in the DB, not on disk */
        if ($type === 'text') {
            $entry['lessons'][] = ['title' => $title, 'text' => (string) ($m['body'] ?? $m['description'] ?? '')];
            continue;
        }

        $path = UPLOAD_DIR . '/' . basename((string) ($m['filename'] ?? ''));
        if (is_file($path)) {
            $files[] = [$path, 'courses/' . $cid . '/' . basename($path)];
            $entry['lessons'][] = ['title' => $title, 'file' => 'courses/' . $cid . '/' . basename($path)];
        } else {
            $missing++;
            $entry['lessons'][] = ['title' => $title, 'note' => 'file not on the server'];
        }
    }
    $manifest[] = $entry;
}

/* ---------- step 3: the notice, and only then the download ---------- */
/* Ask before spending someone's bandwidth. A whole course can take a while on a
   phone, and the honest reason to read online — the certificate — is worth
   saying out loud rather than burying in a README inside the ZIP. */
if ($step === 'plan') {
    $page_title = 'Before you download';
    $nav_active = 'offline';
    require __DIR__ . '/header.php';
    $lessons = 0;
    foreach ($picked as $c) $lessons += count($c['materials'] ?? []);
    ?>
    <div class="mx-auto max-w-2xl">
      <div class="reveal rounded-2xl border-2 border-amber-200 bg-amber-50 p-6">
        <p class="text-sm font-bold uppercase tracking-wide text-amber-700">Please read this first</p>
        <p class="mt-3 text-lg font-bold leading-7 text-amber-900">IT'S BETTER TO READ AND WATCH ONLINE TO ACHIEVE THE
          CERTIFICATE.</p>
        <p class="mt-3 text-sm leading-6 text-amber-800">A certificate is earned here, on the website: each lesson has to
          be opened and read, and each video watched, and only that progress is recorded. Reading a downloaded copy on your
          phone does not count towards it — the site cannot see that you opened the file.</p>
        <p class="mt-2 text-sm leading-6 text-amber-800">So take this copy for the commute and the days the connection is
          poor, and come back online to finish the course.</p>
      </div>

      <div class="reveal mt-5 rounded-2xl bg-white p-5 ring-1 ring-slate-200">
        <h2 class="text-base font-bold text-slate-900">What will be downloaded</h2>
        <ul class="mt-3 space-y-1 text-sm text-slate-600">
          <?php foreach ($picked as $c): ?>
            <li class="flex items-center justify-between gap-3">
              <span class="font-semibold text-slate-800"><?= e((string) $c['title']) ?></span>
              <span class="text-xs text-slate-400"><?= count($c['materials'] ?? []) ?> lessons</span>
            </li>
          <?php endforeach; ?>
        </ul>
        <p class="mt-3 text-xs text-slate-400"><?= $lessons ?> lesson<?= $lessons === 1 ? '' : 's' ?> in total<?php
          if ($videoLinks): ?> · <?= $videoLinks ?> hosted video<?= $videoLinks === 1 ? '' : 's' ?> saved as a web address only<?php endif; ?><?php
          if ($missing): ?> · <?= $missing ?> file<?= $missing === 1 ? '' : 's' ?> no longer on the server<?php endif; ?>.</p>
      </div>

      <form method="post" action="<?= e(lh_url_clean('offline.php')) ?>" class="reveal mt-5 flex flex-wrap gap-3">
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="download">
        <?php foreach ($picked as $cid => $c): ?>
          <input type="hidden" name="courses[]" value="<?= (int) $cid ?>">
        <?php endforeach; ?>
        <button class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Proceed with
          download</button>
        <button type="button" data-lh-cancel-offline
          class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</button>
      </form>
    </div>
    <script>
      /* Cancel must never start a download. It sits INSIDE the POST form, so it
         is a type="button" that submits nothing on its own — this listener is
         the only thing that moves the viewer off this notice. */
      (function () {
        var b = document.querySelector('[data-lh-cancel-offline]');
        if (!b) return;
        b.addEventListener('click', function () {
          window.location.href = <?= json_encode(lh_url_clean('offline.php')) ?>;
        });
      })();
    </script>
    <?php
    require __DIR__ . '/footer.php';
    exit;
}

/* ---------- step 4: build it ---------- */

/* php-zip is frequently OFF on plain XAMPP builds and on shared hosts, so the
   archive is written by lh_zip_writer (lib.php) instead — no extension needed.
   It streams each lesson straight to a temp file as it is copied, so a course of
   videos never has to sit in memory, and the file is then handed to the browser
   from disk. */
$tmp = tempnam(sys_get_temp_dir(), 'lh-offline-');
$out = $tmp === false ? false : fopen($tmp, 'w+b');
if ($out === false) {
    if ($tmp !== false) @unlink($tmp);
    set_flash('error', 'The offline copy could not be prepared.');
    header('Location: ' . lh_url_clean('courses.php'));
    exit;
}

$zip = new lh_zip_writer($out);
$readme = "LearnHub — offline copy\n";
$readme .= 'Taken ' . date('F j, Y g:i A') . ' for ' . (string) $user['name'] . "\n\n";
$readme .= "Lessons marked with a web address in manifest.txt are videos hosted\n";
$readme .= "elsewhere (YouTube/Vimeo) and need a connection to watch.\n\n";

$zip->addString('README.txt', $readme);
foreach ($files as [$path, $nameInZip]) $zip->addFile($path, $nameInZip);
$zip->addString('manifest.txt', (string) json_encode([
    'taken' => date('c'),
    'student' => (string) $user['name'],
    'courses' => $manifest,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
$zip->finish();
fflush($out);

$size = (int) filesize($tmp);
while (ob_get_level() > 0) { @ob_end_clean(); }
$name = 'learnhub-offline-' . date('Y-m-d') . '.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Content-Length: ' . (string) $size);
header('Cache-Control: no-store');
/* stream the finished archive back out of the temp file, then remove it */
rewind($out);
while (!feof($out)) {
    $chunk = fread($out, 262144);
    if ($chunk === false || $chunk === '') break;
    echo $chunk;
    flush();
}
fclose($out);
@unlink($tmp);

