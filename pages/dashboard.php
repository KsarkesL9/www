<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel główny – Edux</title>
    <link rel="stylesheet" href="/assets/css/style.css?v=<?= time() ?>">
    <script src="/assets/js/theme.js?v=<?= time() ?>"></script>
</head>

<body>
    <?php
    require_once __DIR__ . '/../includes/auth.php';
    require_once __DIR__ . '/../config/db.php';
    $session = requireAuth();
    $fullName = htmlspecialchars(trim($session['first_name'] . ' ' . $session['surname']));
    $roleName = htmlspecialchars(ucfirst($session['role_name']));
    $initials = mb_strtoupper(mb_substr($session['first_name'], 0, 1))
        . mb_strtoupper(mb_substr($session['surname'], 0, 1));
    $userId = (int) $session['user_id'];

    $pdo = getDB();

    /* ── Nieobecności ── */
    $absences = [];
    try {
        $stmt = $pdo->prepare(
            'SELECT a.absence_date, a.absence_type, a.is_full_day,
                u.first_name, u.surname
         FROM absences a
         JOIN users u ON u.user_id = a.student_id
         WHERE a.student_id = ?
         ORDER BY a.absence_date DESC
         LIMIT 5'
        );
        $stmt->execute([$userId]);
        $absences = $stmt->fetchAll();
    } catch (Exception $e) { /* tabela jeszcze nie istnieje */
    }

    /* ── Oceny ── */
    $grades = [];
    try {
        $stmt = $pdo->prepare(
            'SELECT g.grade_value, g.is_new, g.added_at,
                s.subject_name,
                u.first_name, u.surname
         FROM grades g
         JOIN subjects s ON s.subject_id = g.subject_id
         JOIN users u    ON u.user_id    = g.student_id
         WHERE g.student_id = ?
         ORDER BY g.added_at DESC
         LIMIT 10'
        );
        $stmt->execute([$userId]);
        $grades = $stmt->fetchAll();
    } catch (Exception $e) { /* tabela jeszcze nie istnieje */
    }

    /* Grupuj oceny po przedmiocie */
    $gradesBySubject = [];
    foreach ($grades as $g) {
        $gradesBySubject[$g['subject_name']][] = $g;
    }

    /* ── Wiadomości – liczba nieprzeczytanych ── */
    $unreadCount = 0;
    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM messages
         WHERE recipient_id = ? AND read_at IS NULL'
        );
        $stmt->execute([$userId]);
        $unreadCount = (int) $stmt->fetchColumn();
    } catch (Exception $e) {
    }

    /* ── Plan zajęć – zaślepka demonstracyjna ── */
    $daysOfWeek = ['Niedziela', 'Poniedziałek', 'Wtorek', 'Środa', 'Czwartek', 'Piątek', 'Sobota'];
    $monthNames = [
        'stycznia',
        'lutego',
        'marca',
        'kwietnia',
        'maja',
        'czerwca',
        'lipca',
        'sierpnia',
        'września',
        'października',
        'listopada',
        'grudnia'
    ];

    $todayDow = (int) date('w');   // 0=Sun, 6=Sat
    $isWeekend = ($todayDow === 0 || $todayDow === 6);
    $nextSchool = null;
    if ($isWeekend) {
        $daysUntilMon = ($todayDow === 6) ? 2 : 1;
        $nextSchool = date('Y-m-d', strtotime("+{$daysUntilMon} days"));
    }

    // Demo plan
    $demoSchedule = [
        ['num' => 1, 'subject' => 'Matematyka', 'room' => 'sala 12', 'time' => '8:00–8:45'],
        ['num' => 2, 'subject' => 'Język polski', 'room' => 'sala 7', 'time' => '8:55–9:40'],
        ['num' => 3, 'subject' => 'Fizyka', 'room' => 'sala 3', 'time' => '9:50–10:35'],
        ['num' => 4, 'subject' => 'Historia', 'room' => 'sala 2', 'time' => '10:45–11:30'],
        ['num' => 5, 'subject' => 'Informatyka', 'room' => 'sala 15', 'time' => '11:40–12:25'],
        ['num' => 6, 'subject' => 'Wychowanie fizyczne', 'room' => 'sala gym', 'time' => '12:45–13:30'],
    ];
    $demoSchedule2 = [
        ['num' => 1, 'subject' => 'Chemia', 'room' => 'sala 9', 'time' => '8:00–8:45'],
        ['num' => 2, 'subject' => 'Biologia', 'room' => 'sala 6', 'time' => '8:55–9:40'],
        ['num' => 3, 'subject' => 'Język angielski', 'room' => 'sala 4', 'time' => '9:50–10:35'],
        ['num' => 4, 'subject' => 'Matematyka', 'room' => 'sala 12', 'time' => '10:45–11:30'],
        ['num' => 5, 'subject' => 'Muzyka', 'room' => 'sala 5', 'time' => '11:40–12:25'],
    ];

    // Oznacz token sesji żeby policzyć czas
    $sessionExpiry = null;
    try {
        $stmt = $pdo->prepare(
            'SELECT expires_at FROM user_sessions
         WHERE token = ? AND revoked_at IS NULL LIMIT 1'
        );
        $stmt->execute([$_COOKIE['session_token'] ?? '']);
        $row = $stmt->fetch();
        if ($row)
            $sessionExpiry = $row['expires_at'];
    } catch (Exception $e) {
    }
    ?>

    <!-- ===== NAVIGATION BAR ===== -->
    <nav class="dashboard-nav">
        <!-- Logo -->
        <a href="/" class="brand-logo" style="font-size:1.5rem; text-decoration:none;">Edu<span>x</span></a>

        <!-- Clock -->
        <div class="dash-clock-bar">
            <div class="dash-clock" id="dashClock">--:--</div>
            <div class="dash-date-box">
                <div class="dash-date" id="dashDate"></div>
                <div class="dash-weekday" id="dashWeekday"></div>
            </div>
        </div>

        <!-- User info -->
        <div class="dash-user-info">
            <!-- Session timer badge -->
            <div class="dash-session-badge" id="sessionBadge" title="Pozostały czas sesji (minuty)">–</div>

            <div class="dash-avatar"><?= $initials ?></div>

            <div style="line-height:1.25;">
                <div style="font-size:0.95rem; font-weight:600; color:var(--text);"><?= $fullName ?></div>
                <div style="font-size:0.78rem; color:var(--gold);"><?= $roleName ?></div>
            </div>

            <button onclick="logout()" class="btn-ghost"
                style="padding:0.45rem 1rem; font-size:0.88rem; white-space:nowrap;">
                Wyloguj
            </button>
        </div>
    </nav>

    <!-- ===== MAIN CONTENT ===== -->
    <div class="dash-main">
        <div class="dash-grid">

            <!-- ===== COLUMN 1 (LEFT) ===== -->
            <div style="display:flex; flex-direction:column; gap:1.5rem;">

                <!-- Wiadomości -->
                <div class="dash-card" style="animation-delay:0.05s">
                    <div class="dash-card-header">
                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        Wiadomości
                        <?php if ($unreadCount > 0): ?>
                            <span class="dash-badge" style="margin-left:auto;"><?= $unreadCount ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="dash-card-body" style="padding:0.4rem 1.4rem;">
                        <a href="/pages/messages.php" class="dash-msg-item">
                            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0H4" />
                            </svg>
                            <?php if ($unreadCount > 0): ?>
                                <span>Masz <strong><?= $unreadCount ?></strong> nowych wiadomości</span>
                                <span class="dash-badge"><?= $unreadCount ?></span>
                            <?php else: ?>
                                Nie masz nowych wiadomości
                            <?php endif; ?>
                        </a>
                        <a href="/pages/messages.php?action=new" class="dash-msg-item">
                            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            Napisz nową wiadomość
                        </a>
                        <a href="/pages/messages.php?action=all" class="dash-msg-item">
                            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h10" />
                            </svg>
                            Zobacz wszystkie wiadomości
                        </a>
                    </div>
                </div>

                <!-- Ostatnie nieobecności -->
                <div class="dash-card" style="animation-delay:0.1s">
                    <div class="dash-card-header">
                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        Ostatnie nieobecności
                    </div>
                    <div class="dash-card-body" style="padding:0.4rem 1.4rem;">
                        <?php if (empty($absences)): ?>
                            <div class="dash-empty">
                                <div style="font-size:1.5rem; margin-bottom:0.4rem;">✅</div>
                                Brak zarejestrowanych nieobecności
                            </div>
                        <?php else: ?>
                            <?php foreach ($absences as $a): ?>
                                <div class="dash-absence-item">
                                    <div class="dash-absence-dot"></div>
                                    <div>
                                        <div style="font-weight:600; font-size:0.95rem;">
                                            <?= date('d.m.Y (l)', strtotime($a['absence_date'])) ?>
                                        </div>
                                        <div style="font-size:0.82rem; color:var(--text-muted);">
                                            <?= $a['is_full_day'] ? 'cały dzień' : htmlspecialchars($a['absence_type']) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div><!-- /col 1 -->

            <!-- ===== COLUMN 2 (MIDDLE) ===== -->
            <div style="display:flex; flex-direction:column; gap:1.5rem;">

                <!-- Uczeń – wielki przycisk -->
                <div class="dash-card" style="animation-delay:0.08s">
                    <div class="dash-card-body">
                        <a href="/pages/student.php" class="dash-action-btn blue">
                            <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <span>Uczeń</span>
                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2.5" style="margin-left:auto;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                        <a href="/pages/student.php?tab=new" class="dash-action-btn green">
                            <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                            </svg>
                            <span>Uczeń — NOWOŚCI</span>
                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2.5" style="margin-left:auto;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    </div>
                </div>

                <!-- Ostatnie oceny -->
                <div class="dash-card" style="animation-delay:0.14s">
                    <div class="dash-card-header">
                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        Ostatnie oceny
                    </div>
                    <div class="dash-card-body">
                        <?php if (empty($gradesBySubject)): ?>
                            <div class="dash-empty">
                                Brak wpisanych ocen
                            </div>
                        <?php else: ?>
                            <div class="dash-student-name"><?= $fullName ?></div>
                            <?php foreach ($gradesBySubject as $subject => $subGrades): ?>
                                <div class="dash-grade-row">
                                    <div class="dash-grade-subject"><?= htmlspecialchars($subject) ?></div>
                                    <div style="display:flex; flex-wrap:wrap; gap:4px; margin-top:4px;">
                                        <?php foreach ($subGrades as $g): ?>
                                            <span
                                                class="dash-grade-value <?= $g['is_new'] ? 'dash-grade-new' : 'dash-grade-old' ?>">
                                                <?= htmlspecialchars($g['grade_value']) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div style="margin-top:0.75rem; font-size:0.8rem; color:var(--text-muted);">
                                <span style="display:inline-flex;align-items:center;gap:4px;">
                                    <span class="dash-grade-value dash-grade-new"
                                        style="width:22px;height:22px;font-size:0.7rem;">N</span>
                                    — nowa ocena (niewidziana)
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div><!-- /col 2 -->

            <!-- ===== COLUMN 3 (RIGHT) ===== -->
            <div style="display:flex; flex-direction:column; gap:1.5rem;">

                <!-- Plan zajęć -->
                <div class="dash-card" style="animation-delay:0.12s">
                    <div class="dash-card-header">
                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                        Plan zajęć
                    </div>
                    <div class="dash-card-body">

                        <!-- DZIŚ / NASTĘPNY DZIEŃ SZKOLNY -->
                        <?php if (!$isWeekend): ?>
                            <div class="dash-schedule-day">
                                <div class="dash-schedule-label">
                                    <?= date('d.m.Y') ?> – DZIŚ (<?= $daysOfWeek[$todayDow] ?>)
                                </div>
                                <?php foreach ($demoSchedule as $l): ?>
                                    <div class="dash-lesson">
                                        <div class="dash-lesson-num"><?= $l['num'] ?>.</div>
                                        <div class="dash-lesson-info">
                                            <div class="dash-lesson-subject"><?= $l['subject'] ?></div>
                                            <div class="dash-lesson-meta"><?= $l['room'] ?> · <?= $l['time'] ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="dash-schedule-day">
                                <div class="dash-schedule-label">
                                    <?= date('d.m.Y', strtotime('+1 day')) ?> – JUTRO
                                </div>
                                <?php foreach ($demoSchedule2 as $l): ?>
                                    <div class="dash-lesson">
                                        <div class="dash-lesson-num"><?= $l['num'] ?>.</div>
                                        <div class="dash-lesson-info">
                                            <div class="dash-lesson-subject"><?= $l['subject'] ?></div>
                                            <div class="dash-lesson-meta"><?= $l['room'] ?> · <?= $l['time'] ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                        <?php else: ?>
                            <div class="dash-empty" style="padding:1rem 0 0.5rem;">
                                <div style="font-size:1.5rem; margin-bottom:0.4rem;">🏖️</div>
                                Dziś wolne – weekend!
                            </div>

                            <div class="dash-schedule-day" style="margin-top:1rem;">
                                <div class="dash-schedule-label">
                                    <?= date('d.m.Y', strtotime($nextSchool)) ?> – PONIEDZIAŁEK (pierwsza dniówka)
                                </div>
                                <?php foreach ($demoSchedule as $l): ?>
                                    <div class="dash-lesson">
                                        <div class="dash-lesson-num"><?= $l['num'] ?>.</div>
                                        <div class="dash-lesson-info">
                                            <div class="dash-lesson-subject"><?= $l['subject'] ?></div>
                                            <div class="dash-lesson-meta"><?= $l['room'] ?> · <?= $l['time'] ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>

            </div><!-- /col 3 -->

        </div><!-- /dash-grid -->
    </div><!-- /dash-main -->

    <script>
        // ===== ZEGAR =====
        const DAYS_PL = ['Niedziela', 'Poniedziałek', 'Wtorek', 'Środa', 'Czwartek', 'Piątek', 'Sobota'];
        const MONTHS_PL2 = ['stycznia', 'lutego', 'marca', 'kwietnia', 'maja', 'czerwca',
            'lipca', 'sierpnia', 'września', 'października', 'listopada', 'grudnia'];

        function updateClock() {
            const now = new Date();
            const hh = String(now.getHours()).padStart(2, '0');
            const mm = String(now.getMinutes()).padStart(2, '0');
            document.getElementById('dashClock').textContent = hh + ':' + mm;
            document.getElementById('dashDate').textContent =
                now.getDate() + ' ' + MONTHS_PL2[now.getMonth()] + ' ' + now.getFullYear();
            document.getElementById('dashWeekday').textContent = DAYS_PL[now.getDay()];
        }
        updateClock();
        setInterval(updateClock, 1000);

        // ===== SESJA – minuty w kółku =====
        <?php if ($sessionExpiry): ?>
            const sessionExp = new Date('<?= str_replace(' ', 'T', $sessionExpiry) ?>');
        <?php else: ?>
            const sessionExp = new Date(Date.now() + 60 * 60 * 1000);
        <?php endif; ?>

        function updateSession() {
            const diffMs = sessionExp - Date.now();
            const diffMin = Math.max(0, Math.floor(diffMs / 60000));
            const badge = document.getElementById('sessionBadge');
            badge.textContent = diffMin + 'm';
            badge.title = 'Sesja wygaśnie za ' + diffMin + ' min';
            if (diffMin <= 5) {
                badge.style.borderColor = 'var(--danger)';
                badge.style.color = 'var(--danger)';
                badge.style.background = 'rgba(248,113,113,0.15)';
            }
            if (diffMin <= 0) {
                window.location.href = '/pages/login.php?msg=session_expired';
            }
        }
        updateSession();
        setInterval(updateSession, 30000); // co 30 sekund

        // ===== WYLOGOWANIE =====
        async function logout() {
            try {
                const res = await fetch('/api/logout.php', { method: 'POST' });
                const data = await res.json();
                window.location.href = data.redirect || '/pages/login.php';
            } catch {
                window.location.href = '/pages/login.php';
            }
        }
    </script>
</body>

</html>