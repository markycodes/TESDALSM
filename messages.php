<?php
/** Messages — private 1:1 chat between a student and their teacher(s). */
require_once __DIR__ . '/lib.php';
$user = require_login();
$userId = (int) $user['id'];
$role = (string) $user['role'];
$nav_active = 'messages';
$page_title = 'Messages';

$convos = conversations_for($userId, $role);
$contacts = message_contacts_for($userId, $role);

// peer selection: ?with=<peer id> (opens/creates that conversation) else first conversation
$withId = (int) ($_GET['with'] ?? 0);
$active = null;
$activeMessages = [];
foreach ($convos as $c) {
    if ($withId > 0 ? $c['peer_id'] === $withId : true) { $active = $c; break; }
}
if (!$active && $withId > 0) {
    // start a new conversation with a contact (validated against allowed contacts)
    $allowed = false;
    foreach ($contacts as $ct) { if ((int) $ct['id'] === $withId) { $allowed = true; break; } }
    if ($allowed) {
        $studentId = $role === 'student' ? $userId : $withId;
        $teacherId = $role === 'teacher' ? $userId : $withId;
        $cid = get_or_create_conversation($studentId, $teacherId);
        mark_conversation_read($cid, $userId);
        $active = ['id' => $cid, 'peer_id' => $withId, 'peer_name' => '', 'last_body' => '', 'last_ts' => 0, 'unread' => 0];
        foreach ($contacts as $ct) { if ((int) $ct['id'] === $withId) { $active['peer_name'] = (string) $ct['name']; break; } }
        $convos[] = $active;
    }
}
if ($active) {
    $activeMessages = messages_in_conversation((int) $active['id'], $userId) ?? [];
    mark_conversation_read((int) $active['id'], $userId);
}

require __DIR__ . '/header.php';
?>
<div class="flex items-center gap-2 text-sm text-slate-500">
  <a href="dashboard.php" class="hover:text-emerald-600">← Dashboard</a>
</div>

<section class="mt-3 overflow-hidden rounded-3xl bg-white shadow-[0_18px_44px_-24px_rgba(4,120,87,0.28)] ring-1 ring-slate-200">
  <div class="grid lg:grid-cols-[320px_1fr]">

    <!-- LEFT: conversation list -->
    <div class="border-b border-slate-200 lg:border-b-0 lg:border-r">
      <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-4 py-3.5">
        <h1 class="text-base font-extrabold tracking-tight text-slate-900">💬 Messages</h1>
        <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700"><?= count($convos) ?> conversation<?= count($convos) === 1 ? '' : 's' ?></span>
      </div>

      <?php if ($contacts): ?>
      <div class="border-b border-slate-100 px-4 py-3">
        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Start a new chat</p>
        <div class="mt-2 flex flex-wrap gap-1.5">
          <?php foreach ($contacts as $ct): ?>
          <a href="messages.php?with=<?= (int) $ct['id'] ?>" class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-emerald-50 hover:text-emerald-700"><?= $role === 'student' ? '👩‍🏫' : '👨‍🎓' ?> <?= e((string) $ct['name']) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <div id="convo-list" class="max-h-[560px] divide-y divide-slate-100 overflow-y-auto">
        <?php if (!$convos): ?>
        <p class="px-4 py-10 text-center text-sm text-slate-400">No conversations yet.<br>Start one with <?= $role === 'student' ? 'your teacher' : 'one of your students' ?> above.</p>
        <?php else: foreach ($convos as $c): $isAct = $active && (int) $c['id'] === (int) $active['id']; ?>
        <a href="messages.php?with=<?= (int) $c['peer_id'] ?>"
           class="convo-item flex items-center gap-3 px-4 py-3 transition <?= $isAct ? 'bg-emerald-50/80' : 'hover:bg-slate-50' ?>"
           data-peer="<?= (int) $c['peer_id'] ?>" data-convo="<?= (int) $c['id'] ?>">
          <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald-600 text-sm font-bold text-white"><?= e(strtoupper(substr((string) $c['peer_name'], 0, 1))) ?></span>
          <span class="min-w-0 flex-1">
            <span class="flex items-center justify-between gap-2">
              <span class="truncate text-sm font-bold text-slate-900"><?= e((string) $c['peer_name']) ?></span>
              <?php if ((int) $c['unread'] > 0): ?>
              <span class="shrink-0 rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-bold text-white"><?= (int) $c['unread'] ?></span>
              <?php endif; ?>
            </span>
            <span class="mt-0.5 block truncate text-xs text-slate-500"><?= $c['last_body'] !== '' ? e((string) $c['last_body']) : 'No messages yet — say hi!' ?></span>
          </span>
        </a>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <!-- RIGHT: active conversation -->
    <div class="flex min-h-[560px] flex-col">
      <?php if ($active): ?>
      <div class="flex items-center gap-3 border-b border-slate-100 bg-slate-50/60 px-4 py-3">
        <a href="dashboard.php" class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 lg:hidden" aria-label="Back">←</a>
        <span class="grid h-10 w-10 place-items-center rounded-full bg-emerald-600 text-sm font-bold text-white"><?= e(strtoupper(substr((string) $active['peer_name'], 0, 1))) ?></span>
        <div class="min-w-0 flex-1 leading-tight">
          <p class="truncate text-sm font-bold text-slate-900"><?= e((string) $active['peer_name']) ?></p>
          <p class="text-[11px] uppercase tracking-wide text-emerald-600">Private chat</p>
        </div>
        <span class="hidden rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-bold text-emerald-700 sm:block">🔒 Private</span>
      </div>

      <div id="chat-box" class="chat-scroll flex flex-1 flex-col gap-2 overflow-y-auto px-4 py-4" data-conversation="<?= (int) $active['id'] ?>" data-me="<?= $userId ?>" data-read-up-to="<?php
        $readUpTo = 0;
        foreach ($activeMessages as $m) { if ((int) $m['sender_id'] === $userId && (int) ($m['is_read'] ?? 0) === 1) { $readUpTo = max($readUpTo, (int) $m['id']); } }
        echo (int) $readUpTo; ?>">
        <?php if (!$activeMessages): ?>
        <p class="my-auto text-center text-sm text-slate-400">No messages yet — send the first one below 👇</p>
        <?php else: foreach ($activeMessages as $m): $mine = (int) $m['sender_id'] === $userId; ?>
        <div class="chat-msg flex <?= $mine ? 'justify-end' : 'justify-start' ?>" data-msg="<?= (int) $m['id'] ?>">
          <div class="max-w-[78%] rounded-2xl px-3.5 py-2 text-sm leading-6 shadow-sm <?= $mine ? 'rounded-br-md bg-emerald-600 text-white' : 'rounded-bl-md bg-slate-100 text-slate-700' ?>">
            <p class="whitespace-pre-wrap break-words"><?= e((string) $m['body']) ?></p>
            <p class="mt-1 text-right text-[10px] <?= $mine ? 'text-emerald-100/90' : 'text-slate-400' ?>"><?= e(date('M j, H:i', (int) $m['created_at'])) ?><?php if ($mine): ?><span class="chat-seen"<?= (int) ($m['is_read'] ?? 0) === 1 ? '' : ' style="display:none"' ?>> · ✓✓ Seen</span><?php endif; ?></p>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>

      <form id="chat-form" class="flex items-center gap-2 border-t border-slate-100 bg-slate-50/60 px-3 py-3">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="with" value="<?= (int) $active['peer_id'] ?>">
        <input type="hidden" name="conversation" value="<?= (int) $active['id'] ?>">
        <input id="chat-input" name="body" autocomplete="off" maxlength="2000" placeholder="Type a message…"
          class="min-w-0 flex-1 rounded-full border border-slate-200 bg-white px-4 py-2.5 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
        <button type="submit" class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald-600 text-white shadow-sm hover:bg-emerald-700" aria-label="Send">
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.27 3.13a.5.5 0 01.68-.59l16.34 7.7a.5.5 0 010 .9L3.95 18.46a.5.5 0 01-.68-.6L6 12zm0 0h7" /></svg>
        </button>
      </form>
      <?php else: ?>
      <div class="grid flex-1 place-items-center p-10 text-center">
        <div>
          <p class="text-4xl">💬</p>
          <p class="mt-3 text-sm font-medium text-slate-500">Select a conversation — or start a new one.</p>
          <p class="mt-1 text-xs text-slate-400"><?= $role === 'student' ? 'You can message the teacher of your course.' : 'You can message students enrolled in your courses.' ?></p>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<style>
.chat-scroll{max-height:min(560px,calc(100vh - 260px))}
.chat-msg{animation:msgIn .18s ease-out}
@keyframes msgIn{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:none}}
</style>


<?php require __DIR__ . '/footer.php'; ?>
