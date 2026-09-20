<?php
/**
 * Live-class API (teacher starts/ends, everyone heartbeats).
 * All actions are POST + CSRF. `v=state` is GET and returns the room snapshot.
 * Access: the owning teacher + enrolled students only (same rule as lessons).
 */
require_once __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function lj(array $d): void
{
    echo json_encode($d);
    exit;
}

$user = require_login();
$me = (int) $user['id'];
$courseId = (int) ($_POST['course'] ?? $_GET['course'] ?? 0);
if ($courseId <= 0) lj(['ok' => false, 'error' => 'Course required']);

$ownerId = course_owner_id($courseId);
if ($ownerId === null) lj(['ok' => false, 'error' => 'Course not found']);
$isHost = ($ownerId === $me);
if (!$isHost && !is_enrolled_id($courseId, $me)) lj(['ok' => false, 'error' => 'Enroll in this course first.']);

$v = (string) ($_POST['v'] ?? $_GET['v'] ?? '');

/* ---------------- teacher: start the class ---------------- */
if ($v === 'start') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') lj(['ok' => false, 'error' => 'POST required']);
    verify_csrf();
    if (!$isHost) lj(['ok' => false, 'error' => 'Only the course teacher can start the live class.']);
    $existing = live_class_active($courseId);
    if ($existing) lj(['ok' => true, 'class' => $existing, 'already' => true]);
    $title = trim((string) ($_POST['title'] ?? ''));
    $class = live_class_start($courseId, $me, $title);
    live_class_notify_students($courseId, $me, (string) ($_POST['course_title'] ?? ''), (int) $class['id']);
    lj(['ok' => true, 'class' => $class]);
}

/* ---------------- teacher: end the class ---------------- */
if ($v === 'end') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') lj(['ok' => false, 'error' => 'POST required']);
    verify_csrf();
    if (!$isHost) lj(['ok' => false, 'error' => 'Only the course teacher can end the live class.']);
    $active = live_class_active($courseId);
    if (!$active) lj(['ok' => true, 'ended' => true]);
    live_class_end((int) $active['id'], $me);
    lj(['ok' => true, 'ended' => true]);
}

/* ---------------- everyone: heartbeat while in the room ---------------- */
if ($v === 'beat') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') lj(['ok' => false, 'error' => 'POST required']);
    verify_csrf();
    $active = live_class_active($courseId);
    if (!$active) lj(['ok' => false, 'ended' => true]);
    /* attendance: a student sitting in the live class counts as a course visit
       — the same attendance rows the Attendance pages show for course visits */
    live_class_attendance($me, $courseId, (($user['role'] ?? '') === 'student'));
    if (isset($_POST['hand'])) live_class_set_hand((int) $active['id'], $me, ($_POST['hand'] ?? '') === '1');
    live_class_heartbeat((int) $active['id'], $me);
    lj(['ok' => true]);
}

/* ---------------- everyone: room snapshot (who is in, hands, host) ---------------- */
if ($v === 'state') {
    touch_presence($me);
    $active = live_class_active($courseId);
    if (!$active) {
        lj(['ok' => true, 'live' => false, 'ended' => isset($_GET['was_live'])]);
    }
    $participants = live_class_participants((int) $active['id']);
    $inRoom = false;
    $myHand = false;
    foreach ($participants as $p) {
        if ($p['id'] === $me) {
            $inRoom = true;
            $myHand = $p['hand'];
        }
    }
    $hn = db()->prepare('SELECT name FROM users WHERE id = ?');
    $hn->execute([(int) $active['host_id']]);
    lj([
        'ok' => true,
        'live' => true,
        'class' => $active,
        'is_host' => $isHost,
        'host_name' => (string) $hn->fetchColumn(),
        'participants' => $participants,
        'me_in' => $inRoom,
        'my_hand' => $myHand,
        'now' => time(),
    ]);
}

lj(['ok' => false, 'error' => 'unknown action']);