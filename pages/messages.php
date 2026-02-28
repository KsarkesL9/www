<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$session = requireAuth();
$userId = (int) $session['user_id'];
$fullName = htmlspecialchars(trim($session['first_name'] . ' ' . $session['surname']));
$roleName = htmlspecialchars(ucfirst($session['role_name']));
$initials = mb_strtoupper(mb_substr($session['first_name'], 0, 1))
    . mb_strtoupper(mb_substr($session['surname'], 0, 1));

/* ─────────────────────────────────────────────
 * Retrieve thread data via the service
 * (zero SQL in the view — everything goes through repositories)
 * ───────────────────────────────────────────── */
$threadData = container()->threadView->getThreadList($userId);
$threads = $threadData['threads'];
$threadParticipants = $threadData['threadParticipants'];

/* Active thread */
$activeThreadId = isset($_GET['thread']) ? (int) $_GET['thread'] : 0;

$activeData = container()->threadView->getActiveThread($activeThreadId, $userId);
$thread = $activeData['thread'];
$messages = $activeData['messages'];
$participants = $activeData['participants'];

/* Session time */
$sessionExpiry = container()->dashboard->getDashboardData($userId, $_COOKIE['session_token'] ?? '')['sessionExpiry'];
?>
<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wiadomości – Edux</title>
    <link rel="stylesheet" href="/assets/css/style.css?v=<?= time() ?>">
    <script src="/assets/js/theme.js?v=<?= time() ?>"></script>
    <style>
        /* ===== MESSAGES MODULE ===== */
        .msg-layout {
            display: grid;
            grid-template-columns: 320px 1fr;
            height: calc(100vh - 64px);
            overflow: hidden;
        }

        /* ---- Sidebar ---- */
        .msg-sidebar {
            border-right: 1px solid var(--navy-border);
            display: flex;
            flex-direction: column;
            background: var(--navy-card);
            overflow: hidden;
        }

        .msg-sidebar-header {
            padding: 1.2rem 1.2rem 0.8rem;
            border-bottom: 1px solid var(--navy-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.6rem;
            flex-shrink: 0;
        }

        .msg-sidebar-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text);
        }

        .msg-new-btn {
            background: var(--gold);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.45rem 0.9rem;
            font-family: 'Outfit', sans-serif;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            transition: background 0.2s;
            white-space: nowrap;
        }

        .msg-new-btn:hover {
            background: var(--gold-light);
        }

        .msg-search {
            padding: 0.6rem 1rem;
            border-bottom: 1px solid var(--navy-border);
            flex-shrink: 0;
        }

        .msg-search input {
            width: 100%;
            background: var(--navy);
            border: 1px solid var(--navy-border);
            border-radius: 8px;
            padding: 0.5rem 0.8rem;
            color: var(--text);
            font-size: 0.88rem;
            outline: none;
            transition: border-color 0.2s;
        }

        .msg-search input:focus {
            border-color: var(--gold);
        }

        .msg-thread-list {
            overflow-y: auto;
            flex: 1;
        }

        .msg-thread-item {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid rgba(71, 85, 105, 0.35);
            cursor: pointer;
            transition: background 0.15s;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            position: relative;
        }

        .msg-thread-item:hover {
            background: rgba(255, 255, 255, 0.03);
        }

        .msg-thread-item.active {
            background: var(--gold-dim);
            border-left: 3px solid var(--gold);
            padding-left: calc(1rem - 3px);
        }

        .msg-thread-item.unread .msg-thread-subject {
            color: var(--text);
            font-weight: 700;
        }

        .msg-thread-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.4rem;
        }

        .msg-thread-subject {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex: 1;
        }

        .msg-thread-time {
            font-size: 0.72rem;
            color: var(--text-muted);
            flex-shrink: 0;
        }

        .msg-thread-preview {
            font-size: 0.78rem;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .msg-thread-participants {
            font-size: 0.72rem;
            color: var(--gold);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .msg-unread-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--gold);
            flex-shrink: 0;
        }

        /* ---- Main area ---- */
        .msg-main {
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: var(--navy);
        }

        .msg-main-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--navy-border);
            background: var(--navy-card);
            flex-shrink: 0;
        }

        .msg-main-subject {
            font-family: 'Playfair Display', serif;
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 0.25rem;
        }

        .msg-main-meta {
            font-size: 0.78rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 0.8rem;
            flex-wrap: wrap;
        }

        .msg-main-meta svg {
            color: var(--gold);
        }

        /* ---- Messages list ---- */
        .msg-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1.2rem 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .msg-bubble-wrap {
            display: flex;
            flex-direction: column;
            max-width: 72%;
        }

        .msg-bubble-wrap.mine {
            align-self: flex-end;
            align-items: flex-end;
        }

        .msg-bubble-wrap.theirs {
            align-self: flex-start;
            align-items: flex-start;
        }

        .msg-bubble-sender {
            font-size: 0.72rem;
            color: var(--text-muted);
            margin-bottom: 0.3rem;
            padding: 0 0.5rem;
        }

        .msg-bubble {
            background: var(--navy-card);
            border: 1px solid var(--navy-border);
            border-radius: 16px;
            padding: 0.75rem 1rem;
            font-size: 0.92rem;
            color: var(--text);
            line-height: 1.55;
            word-break: break-word;
        }

        .msg-bubble-wrap.mine .msg-bubble {
            background: var(--gold-dim);
            border-color: rgba(233, 184, 74, 0.3);
        }

        .msg-bubble-time {
            font-size: 0.68rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
            padding: 0 0.5rem;
        }

        /* ---- Reply box ---- */
        .msg-reply {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--navy-border);
            background: var(--navy-card);
            display: flex;
            gap: 0.75rem;
            align-items: flex-end;
            flex-shrink: 0;
        }

        .msg-reply textarea {
            flex: 1;
            background: var(--navy);
            border: 1px solid var(--navy-border);
            border-radius: 12px;
            padding: 0.75rem 1rem;
            color: var(--text);
            font-family: 'Outfit', sans-serif;
            font-size: 0.92rem;
            resize: none;
            outline: none;
            min-height: 52px;
            max-height: 160px;
            transition: border-color 0.2s;
            line-height: 1.5;
        }

        .msg-reply textarea:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px var(--gold-dim);
        }

        .msg-reply textarea::placeholder {
            color: rgba(148, 163, 184, 0.4);
        }

        .msg-send-btn {
            background: var(--gold);
            border: none;
            border-radius: 12px;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #fff;
            flex-shrink: 0;
            transition: background 0.2s, transform 0.15s;
        }

        .msg-send-btn:hover {
            background: var(--gold-light);
            transform: translateY(-1px);
        }

        .msg-send-btn:disabled {
            opacity: 0.45;
            cursor: not-allowed;
            transform: none;
        }

        /* ---- Empty state ---- */
        .msg-empty {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            gap: 0.75rem;
        }

        .msg-empty svg {
            color: var(--navy-border);
        }

        .msg-empty-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-muted);
        }

        .msg-empty-sub {
            font-size: 0.82rem;
            text-align: center;
            max-width: 260px;
        }

        /* ---- Compose modal ---- */
        .compose-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            backdrop-filter: blur(6px);
            z-index: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s;
        }

        .compose-overlay.open {
            opacity: 1;
            pointer-events: all;
        }

        .compose-modal {
            background: var(--navy-card);
            border: 1px solid var(--navy-border);
            border-radius: 20px;
            padding: 2rem;
            width: 1000px;
            max-width: 95vw;
            box-shadow: 0 24px 64px rgba(0, 0, 0, 0.4);
            transform: translateY(20px) scale(0.97);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .compose-overlay.open .compose-modal {
            transform: translateY(0) scale(1);
        }

        .compose-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text);
        }

        .compose-modal label {
            display: block;
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 0.35rem;
        }

        .compose-modal input,
        .compose-modal select,
        .compose-modal textarea {
            width: 100%;
            background: var(--navy);
            border: 1px solid var(--navy-border);
            border-radius: 10px;
            padding: 0.7rem 1rem;
            color: var(--text);
            font-family: 'Outfit', sans-serif;
            font-size: 0.92rem;
            outline: none;
            transition: border-color 0.2s;
        }

        .compose-modal input:focus,
        .compose-modal select:focus,
        .compose-modal textarea:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px var(--gold-dim);
        }

        .compose-modal textarea {
            resize: vertical;
            min-height: 400px;
        }

        .compose-footer {
            display: flex;
            justify-content: flex-end;
            gap: 0.6rem;
            margin-top: 0.5rem;
        }

        .compose-footer button {
            margin: 0;
        }

        /* Recipient picker dual-pane UI */
        .recipient-picker {
            display: flex;
            gap: 1rem;
            height: 320px;
            margin-bottom: 0.5rem;
        }

        .picker-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            min-width: 0;
        }

        .picker-controls {
            display: flex;
            gap: 0.4rem;
        }

        .picker-controls select,
        .picker-controls input {
            padding: 0.4rem 0.6rem !important;
            font-size: 0.82rem !important;
            border-radius: 6px !important;
            margin: 0 !important;
        }

        .picker-list {
            flex: 1;
            background: var(--navy);
            border: 1px solid var(--navy-border);
            border-radius: 8px;
            overflow-y: auto;
            padding: 0.3rem;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .picker-item {
            padding: 0.4rem 0.6rem;
            font-size: 0.85rem;
            cursor: pointer;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            user-select: none;
            transition: background 0.15s, color 0.15s;
            color: var(--text);
        }

        .picker-item:hover {
            background: var(--navy-light);
        }

        .picker-item.active {
            background: var(--gold-dim);
            color: var(--gold);
            font-weight: 500;
        }

        .picker-item small {
            color: var(--text-muted);
            font-size: 0.72rem;
            font-weight: 400;
        }

        .picker-actions {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 0.8rem;
            padding: 0 0.5rem;
        }

        .picker-actions button {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            background: var(--navy-light);
            border: 1px solid var(--navy-border);
            color: var(--text);
            font-size: 1.2rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .picker-actions button:hover {
            background: var(--gold-dim);
            color: var(--gold);
            border-color: var(--gold);
        }

        /* Loading state */
        .msg-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            color: var(--text-muted);
            font-size: 0.88rem;
            gap: 0.6rem;
        }

        /* Scrollbar */
        .msg-thread-list::-webkit-scrollbar,
        .msg-messages::-webkit-scrollbar {
            width: 4px;
        }

        .msg-thread-list::-webkit-scrollbar-track,
        .msg-messages::-webkit-scrollbar-track {
            background: transparent;
        }

        .msg-thread-list::-webkit-scrollbar-thumb,
        .msg-messages::-webkit-scrollbar-thumb {
            background: var(--navy-border);
            border-radius: 2px;
        }

        /* Deleted message */
        .msg-bubble.deleted {
            font-style: italic;
            color: var(--text-muted);
            opacity: 0.6;
        }

        /* Delete button on hover */
        .msg-bubble-wrap.mine:hover .msg-delete-btn {
            opacity: 1;
        }

        .msg-delete-btn {
            background: none;
            border: none;
            color: var(--danger);
            cursor: pointer;
            font-size: 0.7rem;
            opacity: 0;
            transition: opacity 0.2s;
            padding: 0.1rem 0.3rem;
            margin-top: 0.15rem;
        }

        @media (max-width: 768px) {
            .msg-layout {
                grid-template-columns: 1fr;
            }

            .msg-sidebar {
                display: none;
            }

            .msg-sidebar.mobile-show {
                display: flex;
                position: fixed;
                inset: 0;
                top: 64px;
                z-index: 200;
            }
        }
    </style>
</head>

<body>
    <nav class="dashboard-nav">
        <a href="/pages/dashboard.php" class="brand-logo"
            style="font-size:1.5rem; text-decoration:none;">Edu<span>x</span></a>

        <div style="display:flex; align-items:center; gap:0.6rem;">
            <a href="/pages/dashboard.php" class="btn-ghost" style="padding:0.4rem 0.9rem; font-size:0.82rem;">
                Panel główny
            </a>
            <span style="font-size:1rem; font-weight:600; color:var(--text);">Wiadomości</span>
        </div>

        <div class="dash-user-info">
            <div class="dash-session-badge" id="sessionBadge" title="Pozostały czas sesji">–</div>
            <div class="dash-avatar"><?= $initials ?></div>
            <div style="line-height:1.25;">
                <div style="font-size:0.95rem; font-weight:600; color:var(--text);"><?= $fullName ?></div>
                <div style="font-size:0.78rem; color:var(--gold);"><?= $roleName ?></div>
            </div>
            <button onclick="logout()" class="btn-ghost"
                style="padding:0.45rem 1rem; font-size:0.88rem;">Wyloguj</button>
        </div>
    </nav>

    <!-- ===== MESSAGES LAYOUT ===== -->
    <div class="msg-layout">

        <!-- SIDEBAR -->
        <aside class="msg-sidebar" id="sidebar">
            <div class="msg-sidebar-header">
                <span class="msg-sidebar-title">Skrzynka</span>
                <button class="msg-new-btn" onclick="openCompose()">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Nowa
                </button>
            </div>

            <div class="msg-search">
                <input type="text" id="threadSearch" placeholder="Szukaj wątków…" oninput="filterThreads(this.value)">
            </div>

            <div class="msg-thread-list" id="threadList">
                <?php if (empty($threads)): ?>
                    <div class="msg-loading"
                        style="padding:2rem 1rem; text-align:center; flex-direction:column; gap:0.4rem;">
                        <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"
                            style="color:var(--navy-border); margin:0 auto;">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <span style="font-size:0.82rem;">Brak wiadomości</span>
                    </div>
                <?php else: ?>
                    <?php foreach ($threads as $t):
                        $isUnread = (int) $t['unread_count'] > 0;
                        $isActive = ($t['thread_id'] === $activeThreadId);
                        $parts = $threadParticipants[$t['thread_id']] ?? [];
                        $partNames = implode(', ', array_map(fn($p) => $p['name'], $parts));
                        $preview = $t['last_content']
                            ? mb_substr(strip_tags($t['last_content']), 0, 60) . (mb_strlen($t['last_content']) > 60 ? '…' : '')
                            : '(brak wiadomości)';
                        $timeLabel = $t['last_at']
                            ? (date('Y-m-d', strtotime($t['last_at'])) === date('Y-m-d')
                                ? date('H:i', strtotime($t['last_at']))
                                : date('d.m', strtotime($t['last_at'])))
                            : '';
                        ?>
                        <div class="msg-thread-item <?= $isActive ? 'active' : '' ?> <?= $isUnread ? 'unread' : '' ?>"
                            data-thread="<?= $t['thread_id'] ?>"
                            data-search="<?= htmlspecialchars(strtolower($t['subject'] . ' ' . $partNames)) ?>"
                            onclick="openThread(<?= $t['thread_id'] ?>)">
                            <div class="msg-thread-top">
                                <span class="msg-thread-subject"><?= htmlspecialchars($t['subject'] ?: '(bez tematu)') ?></span>
                                <div style="display:flex; align-items:center; gap:0.35rem;">
                                    <?php if ($isUnread): ?>
                                        <span class="msg-unread-dot"></span>
                                    <?php endif; ?>
                                    <span class="msg-thread-time"><?= $timeLabel ?></span>
                                </div>
                            </div>
                            <?php if ($partNames): ?>
                                <div class="msg-thread-participants"><?= htmlspecialchars($partNames) ?></div>
                            <?php endif; ?>
                            <div class="msg-thread-preview"><?= htmlspecialchars($preview) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>

        <!-- MAIN PANEL -->
        <main class="msg-main" id="msgMain">
            <?php if ($activeThreadId > 0): ?>

                <?php if ($thread): ?>
                    <!-- Thread header -->
                    <div class="msg-main-header">
                        <div class="msg-main-subject"><?= htmlspecialchars($thread['subject'] ?: '(bez tematu)') ?></div>
                        <div class="msg-main-meta">
                            <span style="display:flex; align-items:center; gap:0.3rem;">
                                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <?php foreach ($participants as $i => $p): ?>
                                    <span style="<?= $p['user_id'] == $userId ? 'color:var(--gold);' : '' ?>">
                                        <?= htmlspecialchars($p['name']) ?>
                                        <span
                                            style="color:var(--text-muted); font-size:0.68rem;">(<?= htmlspecialchars($p['role_name']) ?>)</span>
                                    </span>
                                    <?= ($i < count($participants) - 1) ? '<span style="color:var(--navy-border);">Â·</span>' : '' ?>
                                <?php endforeach; ?>
                            </span>
                            <span style="color:var(--navy-border);">|</span>
                            <span>Wątek od <?= date('d.m.Y', strtotime($thread['created_at'])) ?></span>
                            <span style="color:var(--navy-border);">|</span>
                            <span><?= count($messages) ?> wiad.</span>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div class="msg-messages" id="messagesList">
                        <?php if (empty($messages)): ?>
                            <div class="msg-empty" style="flex:1;">
                                <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                                <span>Brak wiadomości w tym wątku</span>
                            </div>
                        <?php else: ?>
                            <?php foreach ($messages as $m):
                                $isMine = ($m['sender_id'] == $userId);
                                $isDeleted = !empty($m['deleted_at']);
                                $timeStr = date('d.m.Y H:i', strtotime($m['created_at']));
                                ?>
                                <div class="msg-bubble-wrap <?= $isMine ? 'mine' : 'theirs' ?>"
                                    data-message-id="<?= $m['message_id'] ?>">
                                    <?php if (!$isMine && !$isDeleted): ?>
                                        <div class="msg-bubble-sender">
                                            <?= htmlspecialchars($m['sender_name'] ?? 'Nieznany') ?>
                                            <span
                                                style="color:var(--gold); font-size:0.68rem;">(<?= htmlspecialchars($m['sender_role'] ?? '') ?>)</span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="msg-bubble <?= $isDeleted ? 'deleted' : '' ?>">
                                        <?= $isDeleted ? 'Wiadomość została usunięta' : nl2br(htmlspecialchars($m['content'])) ?>
                                    </div>
                                    <div class="msg-bubble-time"><?= $timeStr ?></div>
                                    <?php if ($isMine && !$isDeleted): ?>
                                        <button class="msg-delete-btn" onclick="deleteMessage(<?= $m['message_id'] ?>, this)"
                                            title="Usuń wiadomość">
                                            Usuń
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Reply box -->
                    <div class="msg-reply">
                        <textarea id="replyContent" placeholder="Napisz odpowiedź…" rows="2"
                            onkeydown="replyKeydown(event)"></textarea>
                        <button class="msg-send-btn" onclick="sendReply()" title="Wyślij (Ctrl+Enter)">
                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                            </svg>
                        </button>
                    </div>

                <?php else: ?>
                    <div class="msg-empty">
                        <svg width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                        <span class="msg-empty-title">Brak dostępu do wątku</span>
                        <span class="msg-empty-sub">Nie jesteś uczestnikiem tego wątku.</span>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- No thread selected -->
                <div class="msg-empty">
                    <svg width="56" height="56" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <span class="msg-empty-title">Wybierz wątek</span>
                    <span class="msg-empty-sub">Kliknij wątek na liście lub napisz nową wiadomość.</span>
                    <button class="msg-new-btn" onclick="openCompose()"
                        style="margin-top:0.5rem; padding:0.6rem 1.2rem; font-size:0.88rem;">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Nowa wiadomość
                    </button>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- ===== COMPOSE MODAL ===== -->
    <div class="compose-overlay" id="composeOverlay" onclick="overlayClick(event)">
        <div class="compose-modal">
            <div class="compose-title">Nowa wiadomość</div>

            <div id="composeAlert" class="alert" style="margin-bottom:0;"></div>

            <div>
                <label>Temat</label>
                <input type="text" id="composeSubject" placeholder="Temat wiadomości…" maxlength="255">
            </div>

            <div>
                <label>Odbiorcy (wyszukiwanie i dodawanie) <span style="color:var(--gold);">*</span></label>
                <div class="recipient-picker" id="recipientPickerWrap" style="display:none;">
                    <div class="picker-panel">
                        <div class="picker-controls">
                            <select id="pickerRoleSelect" onchange="pickerFilter()" style="flex:1;">
                                <option value="ALL">Wszystkie grupy</option>
                            </select>
                        </div>
                        <div class="picker-controls">
                            <input type="text" id="pickerSearch" placeholder="wyszukaj..." oninput="pickerFilter()" style="width:100%;">
                        </div>
                        <div class="picker-list" id="pickerAvailableList">
                        </div>
                    </div>
                    
                    <div class="picker-actions">
                        <button type="button" onclick="pickerAddSelected()" title="Dodaj do odbiorców">&#8594;</button>
                        <button type="button" onclick="pickerRemoveSelected()" title="Usuń z odbiorców">&#8592;</button>
                    </div>

                    <div class="picker-panel">
                        <div style="font-size: 0.78rem; font-weight:600; color:var(--text-muted); margin-bottom: 0.1rem; text-transform:uppercase;">
                            Wybrani adresaci
                        </div>
                        <div class="picker-list" id="pickerSelectedList">
                        </div>
                    </div>
                </div>
                <!-- fallback text when loading -->
                <div id="pickerLoading" style="color:var(--text-muted); padding:1rem; text-align:center;">
                    <span class="spinner"></span> Ładowanie dostępnych odbiorców…
                </div>
            </div>

            <div>
                <label>Wiadomość <span style="color:var(--gold);">*</span></label>
                <textarea id="composeContent" placeholder="Treść wiadomości…" rows="4"></textarea>
            </div>

            <div class="compose-footer">
                <button class="btn-ghost" style="width:auto; padding:0.65rem 1.8rem;"
                    onclick="closeCompose()">Anuluj</button>
                <button class="btn-primary" style="width:auto; padding:0.65rem 1.8rem;" onclick="sendCompose()"
                    id="composeSendBtn">
                    Wyślij
                </button>
            </div>
        </div>
    </div>

    <script src="/assets/js/alerts.js?v=<?= time() ?>"></script>
    <script src="/assets/js/forms.js?v=<?= time() ?>"></script>
    <script src="/assets/js/api.js?v=<?= time() ?>"></script>
    <script src="/assets/js/session.js?v=<?= time() ?>"></script>
    <script src="/assets/js/messages.js?v=<?= time() ?>"></script>
    <script>
        const CURRENT_USER_ID = <?= $userId ?>;
        const ACTIVE_THREAD_ID = <?= $activeThreadId ?>;
        initSession(<?= $sessionExpiry ? "'" . str_replace(' ', 'T', $sessionExpiry) . "'" : 'null' ?>);
        <?php if (($_GET['action'] ?? '') === 'new'): ?>
            document.addEventListener('DOMContentLoaded', openCompose);
        <?php endif; ?>
    </script>
</body>

</html>