<?php
/**
 * Realtime JSON endpoint — lets the pages refresh themselves without a reload.
 * Scopes:
 *   v=dash           -> teacher dashboard snapshot (stats, live students, today's visits,
 *                       recent activity, needs-attention, 14-day chart + students sparkline)
 *   v=dash-student   -> student dashboard snapshot (stats + personal 14-day chart)
 *   v=roster         -> enrollments page snapshot (classmates, online state, course-visit
 *                       summary, and full attendance log when a course filter is set)
 *   v=day            -> attendance-day page snapshot (stats + visit rows for the selected day)
 *   v=course&id=     -> how many students are online in a course right now
 */
require_once __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

close_stale_attendance();

$v = (string) ($_GET['v'] ?? '');

/* ---------------- landing page stats (guests allowed) ---------------- */
if ($v === 'index') {
    echo json_encode([
        'ok' => true,
        'stats' => [
            'courses'  => (int) db()->query('SELECT COUNT(*) FROM courses')->fetchColumn(),
            'lessons'  => (int) db()->query('SELECT COUNT(*) FROM materials')->fetchColumn(),
            'students' => (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn(),
        ],
    ]);
    exit;
}

$user = require_login();
$me = (int) $user['id'];
$isTeacher = ($user['role'] ?? '') === 'teacher';


/* ---------------- teacher dashboard snapshot ---------------- */
if ($v === 'dash' && $isTeacher) {
    $tc = teacher_counts($me);
    $ts = teacher_daily_series($me);
    $online = array_map(fn ($r) => ['id' => (int) $r['id'], 'name' => (string) $r['name'], 'last_seen' => (int) $r['last_seen']], teacher_online_students($me));
    $visits = array_map(fn ($r) => [
        'id' => (int) $r['id'], 'user_id' => (int) $r['user_id'], 'name' => (string) $r['student_name'],
        'course' => (string) $r['course_title'], 'entered_at' => (int) $r['entered_at'],
        'left_at' => $r['left_at'] ? (int) $r['left_at'] : null,
    ], teacher_today_visits($me));
    $activity = array_map(fn ($r) => [
        'ts' => $r['ts'], 'kind' => $r['kind'], 'who' => (string) $r['who'],
        'course' => (string) $r['course'], 'lesson' => (string) $r['lesson'],
    ], teacher_recent_activity($me));
    $attention = array_map(fn ($r) => [
        'uid' => (int) $r['uid'], 'name' => (string) $r['name'], 'course' => (string) $r['course'],
        'course_id' => (int) $r['course_id'], 'done' => (int) $r['done'], 'total' => (int) $r['total'], 'pct' => (int) $r['pct'],
    ], teacher_attention_students($me));
    echo json_encode([
        'ok' => true, 'role' => 'teacher',
        'counts' => [
            'students' => $tc['students'], 'completions' => $tc['completions'],
            'visits_today' => $tc['visits_today'], 'lessons' => $tc['lessons'],
        ],
        'online' => $online,
        'visits' => $visits,
        'activity' => $activity,
        'attention' => $attention,
        'activity_svg' => activity_chart_svg($ts['visits'], $ts['completions']),
        'students_svg' => zigzag_svg($ts['enrollments'], '#4f46e5', 'rgba(79,70,229,0.16)'),
        'now' => time(),
    ]);
    exit;
}

/* ---------------- student dashboard snapshot ---------------- */
if ($v === 'dash-student' && !$isTeacher) {
    $courses = load_courses();
    $enrolled = array_values(array_filter($courses, fn ($c) => is_enrolled($c, $me)));
    $totDone = 0; $totLessons = 0;
    foreach ($enrolled as $c) { $p = course_progress($c, (string) $me); $totDone += $p['done']; $totLessons += $p['total']; }
    $avg = $totLessons > 0 ? (int) round($totDone * 100 / $totLessons) : 0;
    $ss = student_daily_series($me);
    echo json_encode([
        'ok' => true, 'role' => 'student',
        'counts' => [
            'enrolled' => count($enrolled), 'done' => $totDone, 'avg' => $avg, 'total' => $totLessons,
        ],
        'activity_svg' => activity_chart_svg($ss['visits'], $ss['completions']),
        'now' => time(),
    ]);
    exit;
}
/* ---------------- roster snapshot (enrollments page) ---------------- */
if ($v === 'roster') {
    $courses = load_courses();
    $myCourses = array_values(array_filter($courses, $isTeacher
        ? fn ($c) => (int) ($c['teacher_id'] ?? 0) === $me
        : fn ($c) => is_enrolled($c, $me)));

    $allCategories = [];
    foreach ($myCourses as $c) { $cat = trim((string) ($c['category'] ?? '')); if ($cat !== '' && !in_array($cat, $allCategories, true)) $allCategories[] = $cat; }
    sort($allCategories);

    $selCourse   = (int) ($_GET['course'] ?? 0);
    $selCategory = (string) ($_GET['category'] ?? '');

    $scopeCourses = [];
    if ($selCourse > 0) {
        $hit = array_values(array_filter($myCourses, fn ($c) => (int) $c['id'] === $selCourse));
        if ($hit) $scopeCourses = $hit; else $selCourse = 0;
    }
    if (!$scopeCourses && $selCategory !== '' && in_array($selCategory, $allCategories, true)) {
        $scopeCourses = array_values(array_filter($myCourses, fn ($c) => trim((string) ($c['category'] ?? '')) === $selCategory));
    }
    if (!$scopeCourses) $scopeCourses = $myCourses;
    $scopeIds = array_values(array_map('intval', array_column($scopeCourses, 'id')));

    $students = [];
    $shareCourses = [];
    if ($scopeIds) {
        $in = implode(',', array_fill(0, count($scopeIds), '?'));
        $sql = "SELECT u.id, u.name, u.email FROM users u JOIN enrollments e ON e.user_id = u.id
                WHERE u.role = 'student' AND e.course_id IN ($in)";
        $params = array_values($scopeIds);
        if (!$isTeacher) { $sql .= ' AND u.id <> ?'; $params[] = $me; }
        $sql .= ' GROUP BY u.id ORDER BY u.name';
        $st = db()->prepare($sql);
        $st->execute($params);
        $students = $st->fetchAll();

        $st2 = db()->prepare("SELECT e.user_id, e.course_id, c.title FROM enrollments e JOIN courses c ON c.id = e.course_id WHERE e.course_id IN ($in)");
        $st2->execute(array_values($scopeIds));
        foreach ($st2->fetchAll() as $row) $shareCourses[(int) $row['user_id']][] = $row['title'];
    }
    $studentIds = array_values(array_map('intval', array_column($students, 'id')));

    $attSummary = [];
    if ($studentIds) {
        $in2 = implode(',', array_fill(0, count($studentIds), '?'));
        $st = db()->prepare("SELECT user_id, COUNT(*) AS visits, MAX(entered_at) AS last_at
                             FROM attendance WHERE user_id IN ($in2) AND course_id IN ($in)
                             GROUP BY user_id");
        $st->execute(array_merge(array_values($studentIds), array_values($scopeIds)));
        foreach ($st->fetchAll() as $r) {
            $attSummary[(int) $r['user_id']] = ['visits' => (int) $r['visits'], 'last_at' => (int) $r['last_at']];
        }
    }
    $online = $studentIds ? array_fill_keys(online_user_ids($studentIds), true) : [];
    $onlineNow = count($online);
    $totalVisits = 0;
    foreach ($attSummary as $s) $totalVisits += $s['visits'];

    $studentsOut = [];
    foreach ($students as $u) {
        $sid = (int) $u['id'];
        $coursesFor = $shareCourses[$sid] ?? [];
        $sum = $attSummary[$sid] ?? ['visits' => 0, 'last_at' => 0];
        $studentsOut[] = [
            /* the address travels masked too, so the live payload can never leak it */
            'id' => $sid, 'name' => (string) $u['name'], 'email' => mask_email((string) ($u['email'] ?? '')),
            'online' => isset($online[$sid]),
            'courses' => array_slice(array_map('strval', $coursesFor), 0, 3),
            'more_courses' => max(0, count($coursesFor) - 3),
            'visits' => (int) $sum['visits'], 'last_at' => (int) $sum['last_at'],
        ];
    }

    $attLogArr = [];
    if ($selCourse > 0) {
        $st = db()->prepare("SELECT a.id, a.user_id, a.entered_at, a.left_at, a.ip, u.name
                             FROM attendance a JOIN users u ON u.id = a.user_id
                             WHERE a.course_id = ? AND u.id <> ? ORDER BY a.entered_at DESC LIMIT 200");
        $st->execute([$selCourse, $me]);
        foreach ($st->fetchAll() as $r) {
            $attLogArr[] = [
                'name' => (string) $r['name'], 'entered_at' => (int) $r['entered_at'],
                'left_at' => $r['left_at'] ? (int) $r['left_at'] : null, 'ip' => (string) ($r['ip'] ?? ''),
                'online' => isset($online[(int) $r['user_id']]),
            ];
        }
    }

    echo json_encode([
        'ok' => true,
        'summary' => ['classmates' => count($students), 'online' => $onlineNow, 'visits' => $totalVisits],
        'students' => $studentsOut,
        'attLog' => $attLogArr, 'attCourse' => $selCourse,
        'now' => time(),
    ]);
    exit;
}
/* ---------------- attendance-day snapshot ---------------- */
if ($v === 'day') {
    $courses = load_courses();
    $myCourses = array_values(array_filter($courses, $isTeacher
        ? fn ($c) => (int) ($c['teacher_id'] ?? 0) === $me
        : fn ($c) => is_enrolled($c, $me)));
    $allCategories = [];
    foreach ($myCourses as $c) { $cat = trim((string) ($c['category'] ?? '')); if ($cat !== '' && !in_array($cat, $allCategories, true)) $allCategories[] = $cat; }
    sort($allCategories);

    $date = (string) ($_GET['date'] ?? date('Y-m-d'));
    if (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $date) || strtotime($date) === false) $date = date('Y-m-d');
    $dayStart = (int) strtotime($date . ' 00:00:00');
    $isToday = ($date === date('Y-m-d'));

    $selCourse   = (int) ($_GET['course'] ?? 0);
    $selCategory = (string) ($_GET['category'] ?? '');
    $scopeCourses = [];
    if ($selCourse > 0) {
        $hit = array_values(array_filter($myCourses, fn ($c) => (int) $c['id'] === $selCourse));
        if ($hit) $scopeCourses = $hit; else $selCourse = 0;
    }
    if (!$scopeCourses && $selCategory !== '' && in_array($selCategory, $allCategories, true)) {
        $scopeCourses = array_values(array_filter($myCourses, fn ($c) => trim((string) ($c['category'] ?? '')) === $selCategory));
    }
    if (!$scopeCourses) $scopeCourses = $myCourses;
    $scopeIds = array_values(array_map('intval', array_column($scopeCourses, 'id')));

    $records = [];
    if ($scopeIds) {
        $in = implode(',', array_fill(0, count($scopeIds), '?'));
        $st = db()->prepare("SELECT a.id, a.user_id, a.entered_at, a.left_at, a.ip, u.name,
                                    c.title AS course_title, c.category AS course_category
                             FROM attendance a
                             JOIN users u ON u.id = a.user_id
                             JOIN courses c ON c.id = a.course_id
                             WHERE a.entered_at >= ? AND a.entered_at < ? AND a.course_id IN ($in)
                             ORDER BY a.entered_at DESC");
        $st->execute(array_merge([$dayStart, $dayStart + 86400], $scopeIds));
        $records = $st->fetchAll();
    }
    $recIds = array_values(array_unique(array_map('intval', array_column($records, 'user_id'))));
    $online = $recIds ? array_fill_keys(online_user_ids($recIds), true) : [];
    $now = time();
    $nowCount = 0; $totalTime = 0; $studentsSeen = [];
    $rows = [];
    foreach ($records as $r) {
        $open = empty($r['left_at']) && $isToday;
        $endTs = !empty($r['left_at']) ? (int) $r['left_at'] : ($isToday ? $now : (int) $r['entered_at']);
        $dur = max(0, $endTs - (int) $r['entered_at']);
        if ($open) $nowCount++;
        $totalTime += $dur;
        $uid = (int) $r['user_id'];
        if (!isset($studentsSeen[$uid])) $studentsSeen[$uid] = true;
        $rows[] = [
            'id' => (int) $r['id'], 'user_id' => $uid, 'name' => (string) $r['name'],
            'course_title' => (string) $r['course_title'], 'course_category' => (string) ($r['course_category'] ?? ''),
            'entered_at' => (int) $r['entered_at'], 'left_at' => $r['left_at'] ? (int) $r['left_at'] : null,
            'open' => $open, 'duration' => $dur, 'online' => isset($online[$uid]), 'ip' => (string) ($r['ip'] ?? ''),
        ];
    }
    echo json_encode([
        'ok' => true, 'isToday' => $isToday,
        'stats' => ['total' => count($rows), 'students' => count($studentsSeen), 'now' => $nowCount, 'time' => $totalTime],
        'records' => $rows, 'now' => $now,
    ]);
    exit;
}

/* ---------------- course online count ---------------- */
if ($v === 'course') {
    $courseId = (int) ($_GET['id'] ?? 0);
    $course = $courseId ? course_row($courseId) : null;
    $online = [];
    if ($course) {
        $canView = ($isTeacher && (int) $course['teacher_id'] === $me) || (!$isTeacher && is_enrolled_id($courseId, $me));
        if ($canView) {
            $st = db()->prepare("SELECT u.id, u.name FROM enrollments e
                                 JOIN users u ON u.id = e.user_id
                                 JOIN presence p ON p.user_id = u.id
                                 WHERE e.course_id = ? AND p.last_seen >= ? AND u.role = 'student' AND u.id <> ?");
            $st->execute([$courseId, time() - PRESENCE_TIMEOUT, $me]);
            $online = $st->fetchAll();
        }
    }
    echo json_encode(['ok' => true, 'count' => count($online), 'students' => array_map(fn ($r) => (string) $r['name'], $online)]);
    exit;
}

/* ---------------- courses browse snapshot (live lesson/student counts) ---------------- */
if ($v === 'courses') {
    $lessons = [];
    $st = db()->query('SELECT course_id cid, COUNT(*) n FROM materials GROUP BY course_id');
    foreach ($st->fetchAll() as $r) $lessons[(int) $r['cid']] = (int) $r['n'];
    $enr = [];
    $st = db()->query('SELECT course_id cid, COUNT(*) n FROM enrollments GROUP BY course_id');
    foreach ($st->fetchAll() as $r) $enr[(int) $r['cid']] = (int) $r['n'];
    $out = [];
    foreach (load_courses() as $c) {
        $cid = (int) $c['id'];
        /* lesson counts are private to the owning teacher: other teachers get 0
           (their cards render "lessons private" without the live hook anyway) */
        $private = $isTeacher && (int) $c['teacher_id'] !== $me;
        $out[] = ['id' => $cid, 'lessons' => $private ? 0 : ($lessons[$cid] ?? 0), 'students' => $enr[$cid] ?? 0];
    }
    echo json_encode(['ok' => true, 'courses' => $out]);
    exit;
}

/* ---------------- notifications (header bell) ---------------- */
if ($v === 'notifications') {
    $items = array_slice(notifications_for($me, 8), 0, 8);
    echo json_encode([
        'ok' => true,
        'unread' => unread_notification_count($me),
        'total' => total_notification_count($me),
        'chat_unread' => unread_message_total($me),
        'items' => $items,
        'now' => time(),
    ]);
    exit;
}

/* ---------------- private message conversation list ---------------- */
if ($v === 'chatlist') {
    echo json_encode([
        'ok' => true,
        'conversations' => conversations_for($me, $isTeacher ? 'teacher' : 'student'),
        'unread_total' => unread_message_total($me),
        'now' => time(),
    ]);
    exit;
}

/* ---------------- private messages of one conversation (since = last seen id) ---------------- */
if ($v === 'chat') {
    $cId = (int) ($_GET['c'] ?? 0);
    $since = (int) ($_GET['since'] ?? 0);
    if (!$cId || !user_owns_conversation($cId, $me)) {
        echo json_encode(['ok' => false, 'error' => 'No access']);
        exit;
    }
    $st = db()->prepare('SELECT m.id, m.sender_id, m.body, m.is_read, m.created_at FROM messages m WHERE m.conversation_id = ? AND m.id > ? ORDER BY m.id ASC LIMIT 100');
    $st->execute([$cId, $since]);
    $msgs = array_map(fn ($r) => [
        'id' => (int) $r['id'],
        'sender_id' => (int) $r['sender_id'],
        'body' => (string) $r['body'],
        'is_read' => (int) $r['is_read'],
        'created_at' => (int) $r['created_at'],
    ], $st->fetchAll());
    $st2 = db()->prepare('SELECT MAX(id) FROM messages WHERE conversation_id = ?');
    $st2->execute([$cId]);
    /* read receipt: the newest of MY messages the peer has already seen
     * (is_read flips to 1 when the peer opens this conversation) */
    $st3 = db()->prepare('SELECT MAX(id) FROM messages WHERE conversation_id = ? AND sender_id = ? AND is_read = 1');
    $st3->execute([$cId, $me]);
    echo json_encode([
        'ok' => true,
        'conversation' => $cId,
        'messages' => $msgs,
        'last_id' => (int) $st2->fetchColumn(),
        'read_up_to' => (int) $st3->fetchColumn(),
        'now' => time(),
    ]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'unknown scope']);
exit;