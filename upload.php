<?php
/**
 * Handles lesson uploads from the "Add lesson" modal.
 * lesson_type: document | text | video | link
 *  - document: one uploaded file (students open it directly in the browser — no extraction)
 *  - text:     pasted material content (saved as Markdown and read in-browser)
 *  - video:    one or several uploaded video files (each file becomes its own lesson)
 *  - link:     one or several YouTube/Vimeo URLs (one per line, each becomes a lesson)
 */
require_once __DIR__ . '/lib.php';
$user = require_teacher();
verify_csrf();

$courseId = (string) ($_POST['course_id'] ?? '');
$type     = (string) ($_POST['lesson_type'] ?? '');
$title    = trim((string) ($_POST['title'] ?? ''));
$desc     = trim((string) ($_POST['description'] ?? ''));
$back     = 'course.php?id=' . urlencode($courseId);

$ownerId = course_owner_id((int) $courseId);
if ($ownerId === null) {
    set_flash('error', 'Course not found.');
    header('Location: dashboard.php'); exit;
}
if ($ownerId !== (int) $user['id']) {
    set_flash('error', 'You can only add lessons to your own courses.');
    header('Location: dashboard.php'); exit;
}
if ($title === '') {
    set_flash('error', 'Please give the lesson a title.');
    header('Location: ' . $back); exit;
}

$courseIdInt = (int) $courseId;
try {
    if ($type === 'document') {
        $up = handle_upload('file', DOC_EXTS);
        add_material($courseIdInt, 'file', cut($title, 120), cut($desc, 200), [
            'stored' => $up['stored'], 'orig' => $up['orig'],
            'mime' => guess_mime($up['stored']), 'size' => $up['size'],
        ]);
        $ok = 'Material "' . $title . '" uploaded — students open it directly in the browser (no download).';
    } elseif ($type === 'text') {
        $content = (string) ($_POST['content'] ?? '');
        if (trim($content) === '') throw new RuntimeException('Paste the material text first — it becomes the lesson content.');
        $stored = 'pasted_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.md';
        @file_put_contents(UPLOAD_DIR . '/' . $stored, $content);
        add_material($courseIdInt, 'file', cut($title, 120), cut($desc, 200), [
            'stored' => $stored, 'orig' => 'pasted-material.md',
            'mime' => 'text/markdown', 'size' => strlen($content),
        ]);
        $ok = 'Pasted material "' . $title . '" added — ' . strlen($content) . ' characters of content.';
    } elseif ($type === 'video') {
        $ups = handle_uploads('file', VIDEO_EXTS);
        foreach ($ups as $i => $up) {
            $t = $i === 0 ? $title : cut($title, 115) . ' (' . ($i + 1) . ')';
            add_material($courseIdInt, 'video', cut($t, 120), cut($desc, 200), [
                'stored' => $up['stored'], 'orig' => $up['orig'],
                'mime' => guess_mime($up['stored']), 'size' => $up['size'],
            ]);
        }
        $ok = count($ups) > 1
            ? count($ups) . ' video lessons added ("' . $title . '" + ' . (count($ups) - 1) . ' more).'
            : 'Video lesson "' . $title . '" uploaded.';
    } elseif ($type === 'link') {
        $raw  = (string) ($_POST['urls'] ?? '');
        $urls = [];
        foreach (preg_split('~\r\n|\r|\n~', $raw) ?: [] as $u) {
            $u = trim($u);
            if ($u !== '') $urls[] = $u;
        }
        if (!$urls) throw new RuntimeException('Paste at least one YouTube or Vimeo link.');
        foreach ($urls as $u) {
            if (video_embed_url($u) === null) throw new RuntimeException('Not a valid YouTube or Vimeo link: ' . cut($u, 60));
        }
        foreach ($urls as $i => $u) {
            $t = $i === 0 ? $title : cut($title, 115) . ' (' . ($i + 1) . ')';
            add_material($courseIdInt, 'youtube', cut($t, 120), cut($desc, 200), null, $u);
        }
        $ok = count($urls) > 1
            ? count($urls) . ' video links added ("' . $title . '" + ' . (count($urls) - 1) . ' more).'
            : 'Video lesson "' . $title . '" added.';
    } else {
        throw new RuntimeException('Unknown lesson type.');
    }
    set_flash('success', $ok);
} catch (RuntimeException $e) {
    set_flash('error', $e->getMessage());
}
header('Location: ' . $back);
exit;
