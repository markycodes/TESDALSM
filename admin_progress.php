<?php
/** Site-wide student course and lesson completion tracker. */
require_once __DIR__ . '/lib.php';
$user = require_admin();
$nav_active = 'admin_progress';

$courses = db()->query("SELECT c.id, c.title, t.name AS teacher_name
                        FROM courses c
                        JOIN users t ON t.id = c.teacher_id
                        ORDER BY c.title, c.id")->fetchAll();
$students = db()->query("SELECT id, name, email FROM users WHERE role = 'student' ORDER BY name, id")->fetchAll();
$courseIds = array_fill_keys(array_map(static fn($c) => (int) $c['id'], $courses), true);
$studentIds = array_fill_keys(array_map(static fn($s) => (int) $s['id'], $students), true);

$selectedCourse = (int) ($_GET['course'] ?? 0);
$selectedStudent = (int) ($_GET['student'] ?? 0);
if ($selectedCourse && !isset($courseIds[$selectedCourse])) $selectedCourse = 0;
if ($selectedStudent && !isset($studentIds[$selectedStudent])) $selectedStudent = 0;

$sql = "SELECT u.id AS student_id, u.name AS student_name, u.email,
               c.id AS course_id, c.title AS course_title, t.name AS teacher_name,
               e.user_id AS enrolled_user_id,
               m.id AS material_id, m.title AS material_title, m.type AS material_type,
               p.completed_at
        FROM (
            SELECT user_id, course_id FROM enrollments
            UNION
            SELECT p.user_id, m.course_id
            FROM progress p JOIN materials m ON m.id = p.material_id
        ) tracked
        JOIN users u ON u.id = tracked.user_id AND u.role = 'student'
        JOIN courses c ON c.id = tracked.course_id
        JOIN users t ON t.id = c.teacher_id
        LEFT JOIN enrollments e ON e.user_id = u.id AND e.course_id = c.id
        LEFT JOIN materials m ON m.course_id = c.id
        LEFT JOIN progress p ON p.user_id = u.id AND p.material_id = m.id";
$where = [];
$params = [];
if ($selectedCourse) {
    $where[] = 'c.id = ?';
    $params[] = $selectedCourse;
}
if ($selectedStudent) {
    $where[] = 'u.id = ?';
    $params[] = $selectedStudent;
}
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY u.name, c.title, m.sort_order, m.id';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$groups = [];
foreach ($stmt->fetchAll() as $row) {
    $key = (int) $row['student_id'] . ':' . (int) $row['course_id'];
    if (!isset($groups[$key])) {
        $groups[$key] = [
            'student_id' => (int) $row['student_id'],
            'student_name' => (string) $row['student_name'],
            'email' => (string) $row['email'],
            'course_id' => (int) $row['course_id'],
            'course_title' => (string) $row['course_title'],
            'teacher_name' => (string) $row['teacher_name'],
            'enrolled' => $row['enrolled_user_id'] !== null,
            'total' => 0,
            'done' => 0,
            'lessons' => [],
        ];
    }
    if ($row['material_id'] === null) continue;
    $completedAt = $row['completed_at'] !== null ? (int) $row['completed_at'] : null;
    $groups[$key]['total']++;
    if ($completedAt !== null) $groups[$key]['done']++;
    $groups[$key]['lessons'][] = [
        'title' => (string) $row['material_title'],
        'type' => (string) $row['material_type'],
        'completed_at' => $completedAt,
    ];
}

$courseCompleteCount = 0;
$lessonCompleteCount = 0;
foreach ($groups as $group) {
    $lessonCompleteCount += $group['done'];
    if ($group['total'] > 0 && $group['done'] === $group['total']) $courseCompleteCount++;
}

$page_title = 'Student completion tracker';
require __DIR__ . '/header.php';
?>
<div class="lh-admin-page mx-auto max-w-7xl">
  <a href="admin.php" class="lh-admin-back">← Admin control</a>
  <div class="lh-admin-hero mt-3 flex flex-wrap items-end justify-between gap-4">
    <div>
      <p class="lh-admin-eyebrow">Learning outcomes</p>
      <h1 class="lh-admin-title">Student completion tracker</h1>
      <p class="lh-admin-description">Review lesson completion across enrolled courses, including progress and completion dates.</p>
    </div>
    <div class="flex flex-wrap gap-2">
      <span class="rounded-xl border border-indigo-100 bg-white/80 px-3 py-2 text-xs font-bold text-indigo-700"><?= count($groups) ?> records</span>
      <span class="rounded-xl border border-emerald-100 bg-white/80 px-3 py-2 text-xs font-bold text-emerald-700"><?= $lessonCompleteCount ?> lessons complete</span>
      <span class="rounded-xl border border-slate-200 bg-white/80 px-3 py-2 text-xs font-bold text-slate-700"><?= $courseCompleteCount ?> courses complete</span>
    </div>
  </div>

  <form method="get" class="lh-admin-card mt-5 flex flex-wrap items-end gap-3 p-4">
    <label class="grid gap-1 text-xs font-semibold text-slate-600">
      Student
      <select name="student" class="min-w-52 rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal">
        <option value="0">All students</option>
        <?php foreach ($students as $student): ?>
          <option value="<?= (int) $student['id'] ?>"<?= $selectedStudent === (int) $student['id'] ? ' selected' : '' ?>><?= e((string) $student['name']) ?> · <?= e((string) $student['email']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="grid gap-1 text-xs font-semibold text-slate-600">
      Course
      <select name="course" class="min-w-52 rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal">
        <option value="0">All courses</option>
        <?php foreach ($courses as $course): ?>
          <option value="<?= (int) $course['id'] ?>"<?= $selectedCourse === (int) $course['id'] ? ' selected' : '' ?>><?= e((string) $course['title']) ?> · <?= e((string) $course['teacher_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Filter</button>
    <a href="admin_progress.php" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Clear</a>
  </form>

  <?php if (!$groups): ?>
    <p class="mt-5 rounded-2xl border-2 border-dashed border-slate-300 p-10 text-center text-sm text-slate-500">No student enrollments or completion records match these filters.</p>
  <?php else: ?>
    <div class="lh-admin-table-wrap mt-5">
      <table class="lh-admin-table w-full min-w-[760px] text-left text-sm">
        <thead class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
          <tr>
            <th class="px-4 py-3 font-semibold">Student</th>
            <th class="px-4 py-3 font-semibold">Course · Teacher</th>
            <th class="px-4 py-3 font-semibold">Lessons complete</th>
            <th class="px-4 py-3 font-semibold">Course status</th>
            <th class="px-4 py-3 font-semibold">Lesson details</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        <?php foreach ($groups as $group):
          $pct = $group['total'] > 0 ? (int) round($group['done'] * 100 / $group['total']) : 0;
          $complete = $group['total'] > 0 && $group['done'] === $group['total'];
        ?>
          <tr>
            <td class="px-4 py-3">
              <span class="block font-semibold text-slate-900"><?= e($group['student_name']) ?><?= $group['enrolled'] ? '' : ' <span class="text-xs font-normal text-amber-700">(unenrolled)</span>' ?></span>
              <span class="block text-xs text-slate-400"><?= e($group['email']) ?></span>
            </td>
            <td class="px-4 py-3">
              <a href="course.php?id=<?= $group['course_id'] ?>" class="font-semibold text-indigo-700 hover:underline"><?= e($group['course_title']) ?></a>
              <span class="block text-xs text-slate-400">Teacher: <?= e($group['teacher_name']) ?></span>
            </td>
            <td class="px-4 py-3">
              <span class="font-semibold text-slate-800"><?= $group['done'] ?> / <?= $group['total'] ?></span>
              <div class="mt-1 h-1.5 w-32 overflow-hidden rounded-full bg-slate-200">
                <span class="block h-full rounded-full bg-emerald-500" style="width: <?= $pct ?>%"></span>
              </div>
              <span class="mt-1 block text-xs text-slate-500"><?= $pct ?>%</span>
            </td>
            <td class="px-4 py-3">
              <?php if ($complete): ?>
                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Complete</span>
              <?php elseif ($group['total'] === 0): ?>
                <span class="text-xs text-slate-400">No lessons</span>
              <?php else: ?>
                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">In progress</span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3">
              <?php if ($group['lessons']): ?>
                <details>
                  <summary class="cursor-pointer text-xs font-semibold text-indigo-700"><?= $group['total'] ?> lesson<?= $group['total'] === 1 ? '' : 's' ?> · view statuses</summary>
                  <ul class="mt-2 space-y-1.5">
                    <?php foreach ($group['lessons'] as $lesson): ?>
                      <li class="flex flex-wrap items-center justify-between gap-2 text-xs">
                        <span class="text-slate-700"><?= e($lesson['title']) ?></span>
                        <?php if ($lesson['completed_at'] !== null): ?>
                          <span class="font-semibold text-emerald-700">✓ <?= date('M j, Y', $lesson['completed_at']) ?></span>
                        <?php else: ?>
                          <span class="text-slate-400">Not complete</span>
                        <?php endif; ?>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </details>
              <?php else: ?>
                <span class="text-xs text-slate-400">No lesson records</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/footer.php'; ?>
