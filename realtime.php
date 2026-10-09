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
    $onlineRows = teacher_online_students($me);
    $visitRows = teacher_today_visits($me);
    $activityRows = teacher_recent_activity($me);
    $attentionRows = teacher_attention_students($me);
    /* one query primes every hover card the rows below may carry */
    profile_cards_preload(array_merge(
        array_map(fn ($r) => (int) $r['id'], $onlineRows),
        array_map(fn ($r) => (int) $r['user_id'], $visitRows),
        array_map(fn ($r) => (int) ($r['uid'] ?? 0), $activityRows),
        array_map(fn ($r) => (int) $r['uid'], $attentionRows)
    ));
    /* Each row carries the circle and card trigger the dashboard first paint
       drew — gated by the same can_view_profile_of() that paint went through,
       so a live refresh can never show a face the page itself would hide. */
    $peer = static function (int $uid, string $avatarFile): array {
        return [
            'avatar' => can_view_profile_of($uid) ? user_avatar_url(['id' => $uid, 'avatar' => $avatarFile]) : '',
            'hover' => profile_hover_attrs($uid, ['self' => false]),
        ];
    };
    $online = array_map(static function ($r) use ($peer): array {
        $p = $peer((int) $r['id'], (string) ($r['avatar'] ?? ''));
        return ['id' => (int) $r['id'], 'name' => (string) $r['name'], 'last_seen' => (int) $r['last_seen'], 'avatar' => $p['avatar'], 'hover' => $p['hover']];
    }, $onlineRows);
    $visits = array_map(static function ($r) use ($peer): array {
        $p = $peer((int) $r['user_id'], (string) ($r['avatar'] ?? ''));
        return [
            'id' => (int) $r['id'], 'user_id' => (int) $r['user_id'], 'name' => (string) $r['student_name'],
            'course' => (string) $r['course_title'], 'entered_at' => (int) $r['entered_at'],
            'left_at' => $r['left_at'] ? (int) $r['left_at'] : null,
            'avatar' => $p['avatar'], 'hover' => $p['hover'],
        ];
    }, $visitRows);
    $activity = array_map(static function ($r) use ($peer): array {
        $p = $peer((int) ($r['uid'] ?? 0), (string) ($r['avatar'] ?? ''));
        return [
            'ts' => $r['ts'], 'kind' => $r['kind'], 'who' => (string) $r['who'],
            'course' => (string) $r['course'], 'lesson' => (string) $r['lesson'],
            'avatar' => $p['avatar'], 'hover' => $p['hover'],
        ];
    }, $activityRows);
    $attention = array_map(static function ($r) use ($peer): array {
        $p = $peer((int) $r['uid'], (string) ($r['avatar'] ?? ''));
        return [
            'uid' => (int) $r['uid'], 'name' => (string) $r['name'], 'course' => (string) $r['course'],
            'course_id' => (int) $r['course_id'], 'done' => (int) $r['done'], 'total' => (int) $r['total'], 'pct' => (int) $r['pct'],
            'avatar' => $p['avatar'], 'hover' => $p['hover'],
        ];
    }, $attentionRows);
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
        'activity_svg' => activity_chart_svg($ts['visits'], $ts['completions'], $ts['labels']),
        'students_svg' => zigzag_svg($ts['enrollments'], '#4f46e5', 'rgba(79,70,229,0.16)', $ts['labels'], 'New enrollments'),
        /* Tala's cloud: the same words the banner printed, recounted for now */
        'owl' => owl_news($user),
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
        'activity_svg' => activity_chart_svg($ss['visits'], $ss['completions'], $ss['labels']),
        /* Tala's cloud: the same words the banner printed, recounted for now */
        'owl' => owl_news($user),
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
        /* the page's roster query again, byte for byte: enrolled students plus
           (for staff) students with records here but no enrolment — flagged
           'Removed' — so the live refresh never drops a row first paint drew */
        $sql = "SELECT u.id, u.name, u.email,
                       MAX(CASE WHEN e.user_id IS NULL THEN 1 ELSE 0 END) AS removed
                FROM users u
                LEFT JOIN enrollments e ON e.user_id = u.id AND e.course_id IN ($in)
                WHERE u.role = 'student' AND (e.user_id IS NOT NULL";
        $scopeParams = array_values($scopeIds);
        $params = $scopeParams;
        if ($isTeacher || ($user['role'] ?? '') === 'admin') {
            $sql .= " OR EXISTS (SELECT 1 FROM attendance a WHERE a.user_id = u.id AND a.course_id IN ($in))"
                  . " OR EXISTS (SELECT 1 FROM progress p JOIN materials m ON m.id = p.material_id WHERE p.user_id = u.id AND m.course_id IN ($in))"
                  . " OR EXISTS (SELECT 1 FROM quiz_results qr JOIN quizzes q ON q.id = qr.quiz_id LEFT JOIN materials m2 ON m2.id = q.material_id LEFT JOIN course_folders qf ON qf.id = q.folder_id WHERE qr.user_id = u.id AND COALESCE(m2.course_id, qf.course_id) IN ($in))"
                  . " OR EXISTS (SELECT 1 FROM submissions s JOIN assignments a2 ON a2.id = s.assignment_id WHERE s.user_id = u.id AND a2.course_id IN ($in))";
            $params = array_merge($params, $scopeParams, $scopeParams, $scopeParams, $scopeParams);
        }
        $sql .= ')';
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

    /* ---- whose attendance may be read here ----
       The teacher of these courses (and the main admin, who owns the site) read
       every student's record. A student reads their OWN rows and nothing else:
       how often another student turned up, and when, is not their data — the
       classmate list above stays (names, shared courses, online dot) because
       that is what a class is, but the numbers go. */
    $mayReadAttendance = $isTeacher || ($user['role'] ?? '') === 'admin';
    $attSummary = [];
    $totalVisits = 0;
    if ($scopeIds && $mayReadAttendance) {
        $st = db()->prepare("SELECT user_id, COUNT(*) AS visits, MAX(entered_at) AS last_at
                             FROM attendance WHERE course_id IN ($in)
                             GROUP BY user_id");
        $st->execute(array_values($scopeIds));
        foreach ($st->fetchAll() as $r) {
            $attSummary[(int) $r['user_id']] = ['visits' => (int) $r['visits'], 'last_at' => (int) $r['last_at']];
        }
    } elseif ($scopeIds) {
        $st = db()->prepare("SELECT user_id, COUNT(*) AS visits, MAX(entered_at) AS last_at
                             FROM attendance WHERE course_id IN ($in) AND user_id = ?
                             GROUP BY user_id");
        $st->execute(array_merge(array_values($scopeIds), [$me]));
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
        $row = [
            /* the address travels masked too, so the live payload can never leak it */
            'id' => $sid, 'name' => (string) $u['name'], 'email' => mask_email((string) ($u['email'] ?? '')),
            'online' => isset($online[$sid]),
            /* true for a row the page drew with a 'Removed' badge */
            'removed' => !empty($u['removed']),
            'courses' => array_slice(array_map('strval', $coursesFor), 0, 3),
            'more_courses' => max(0, count($coursesFor) - 3),
        ];
        /* The attendance column carries only figures this viewer may read. For a
           student the keys are absent altogether for everyone but themselves —
           so there is nothing in the payload for the page to print by accident. */
        if ($mayReadAttendance || $sid === $me) {
            $sum = $attSummary[$sid] ?? ['visits' => 0, 'last_at' => 0];
            $row['visits'] = (int) $sum['visits'];
            $row['last_at'] = (int) $sum['last_at'];
        }
        $studentsOut[] = $row;
    }

    /* The attendance log of one course: everybody's rows for the teacher of that
       course, the viewer's own rows for a student. The IP address goes no
       further than the teacher. */
    $attLogArr = [];
    if ($selCourse > 0) {
        $st = db()->prepare("SELECT a.id, a.user_id, a.entered_at, a.left_at, a.ip, u.name,
                                     CASE WHEN u.role = 'student' AND e.user_id IS NULL THEN 1 ELSE 0 END AS removed
                              FROM attendance a JOIN users u ON u.id = a.user_id
                              LEFT JOIN enrollments e ON e.course_id = a.course_id AND e.user_id = a.user_id
                              WHERE a.course_id = ?" . ($mayReadAttendance
                                  ? ' AND a.id = (SELECT MAX(a2.id) FROM attendance a2 WHERE a2.course_id = a.course_id AND a2.user_id = a.user_id)'
                                  : ' AND a.user_id = ?') .
                              ' ORDER BY a.entered_at DESC LIMIT 200');
        $st->execute($mayReadAttendance ? [$selCourse] : [$selCourse, $me]);
        foreach ($st->fetchAll() as $r) {
            $attLogArr[] = [
                'id' => (int) $r['id'],
                'name' => (string) $r['name'], 'entered_at' => (int) $r['entered_at'],
                'left_at' => $r['left_at'] ? (int) $r['left_at'] : null,
                /* the badge the page's first paint drew beside the name */
                'removed' => (bool) ((int) ($r['removed'] ?? 0)),
                'ip' => $mayReadAttendance ? (string) ($r['ip'] ?? '') : '',
                'online' => isset($online[(int) $r['user_id']]),
            ];
        }
    }

    echo json_encode([
        'ok' => true,
        'summary' => ['classmates' => count($students), 'online' => $onlineNow, 'visits' => $totalVisits],
        'students' => $studentsOut,
        'attLog' => $attLogArr, 'attCourse' => $selCourse,
        /* so the redrawn table leaves the IP column out for a student */
        'ownOnly' => !$mayReadAttendance,
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

    /* A student reads this page for their OWN visits; the teacher of the courses
       in scope (and the main admin) see the whole day. $mayReadAttendance also
       keeps the IP addresses out of the payload — attendance_day.php draws the
       same rule, and the page drops the column when 'ownOnly' comes back set. */
    $mayReadAttendance = $isTeacher || ($user['role'] ?? '') === 'admin';
    $records = [];
    if ($scopeIds) {
        $in = implode(',', array_fill(0, count($scopeIds), '?'));
        $st = db()->prepare("SELECT a.id, a.user_id, a.entered_at, a.left_at, a.ip, u.name, u.avatar,
                                    CASE WHEN u.role = 'student' AND e.user_id IS NULL THEN 1 ELSE 0 END AS removed,
                                    c.title AS course_title, c.category AS course_category
                             FROM attendance a
                             JOIN users u ON u.id = a.user_id
                             JOIN courses c ON c.id = a.course_id
                             LEFT JOIN enrollments e ON e.course_id = a.course_id AND e.user_id = a.user_id
                             WHERE a.entered_at >= ? AND a.entered_at < ? AND a.course_id IN ($in)"
                             . ($mayReadAttendance
                                ? ' AND a.id = (SELECT MAX(a2.id) FROM attendance a2 WHERE a2.user_id = a.user_id AND a2.course_id = a.course_id AND a2.entered_at >= ? AND a2.entered_at < ?)'
                                : ' AND a.user_id = ?') .
                             ' ORDER BY a.entered_at DESC');
        $st->execute(array_merge([$dayStart, $dayStart + 86400], $scopeIds, $mayReadAttendance ? [$dayStart, $dayStart + 86400] : [$me]));
        $records = $st->fetchAll();
    }
    $recIds = array_values(array_unique(array_map('intval', array_column($records, 'user_id'))));
    /* the picture and the hover card the sheet prints ride along with the redraw —
       one query for every distinct student (skipped outright when a student looks,
       whose pages carry no cards at all) */
    profile_cards_preload($recIds);
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
            /* the circle and the card are the page's own first-paint rule again: the
               picture blank for a viewer can_view_profile_of() turns away (or a row
               naming no file behind it — never link a picture that would 404), the
               hover attrs from profile_hover_attrs, '' for that same viewer and for
               the viewer's own row (self => false) */
            'avatar' => can_view_profile_of($uid) ? user_avatar_url(['id' => $uid, 'avatar' => (string) ($r['avatar'] ?? '')]) : '',
            'hover' => profile_hover_attrs($uid, ['self' => false]),
            'course_title' => (string) $r['course_title'], 'course_category' => (string) ($r['course_category'] ?? ''),
            'entered_at' => (int) $r['entered_at'], 'left_at' => $r['left_at'] ? (int) $r['left_at'] : null,
            'open' => $open, 'duration' => $dur, 'online' => isset($online[$uid]),
            /* the 'Removed' badge the page drew beside the name, redrawn too */
            'removed' => (bool) ((int) ($r['removed'] ?? 0)),
            /* the address belongs to the teacher of the course, and nobody else */
            'ip' => $mayReadAttendance ? (string) ($r['ip'] ?? '') : '',
        ];
    }
    echo json_encode([
        'ok' => true, 'isToday' => $isToday,
        /* the second figure is the teacher's headcount of students; for a student
           the page labels that chip 'Courses visited', so this sends the count
           that matches the label they see */
        'stats' => [
            'total' => count($rows),
            'students' => $mayReadAttendance ? count($studentsSeen) : count(array_unique(array_column($rows, 'course_title'))),
            'now' => $nowCount, 'time' => $totalTime,
        ],
        'records' => $rows,
        /* tells the page whose day this is: it relabels nothing (PHP already did)
           and leaves the IP column out of the rows it redraws */
        'ownOnly' => !$mayReadAttendance,
        'now' => $now,
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
    /* a student may watch the numbers move only on a course they are in */
    $mine = [];
    if (!$isTeacher) {
        $st = db()->prepare('SELECT course_id FROM enrollments WHERE user_id = ?');
        $st->execute([$me]);
        foreach ($st->fetchAll() as $r) $mine[(int) $r['course_id']] = true;
    }
    $out = [];
    foreach (load_courses() as $c) {
        $cid = (int) $c['id'];
        /* lesson counts are private to the owning teacher: other teachers get 0
           (their cards render "lessons private" without the live hook anyway) */
        $private = $isTeacher && (int) $c['teacher_id'] !== $me;
        /* for a student, a course they are not in is private in the same way —
           how many people are in a class they never joined is not theirs to
           watch, and courses.php renders the card without the live hook */
        $unknown = !$isTeacher && !isset($mine[$cid]);
        $out[] = [
            'id' => $cid,
            'lessons' => ($private || $unknown) ? 0 : ($lessons[$cid] ?? 0),
            'students' => $unknown ? 0 : ($enr[$cid] ?? 0),
        ];
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