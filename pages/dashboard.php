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

    /* ─────────────────────────────────────────────
     * NIEOBECNOŚCI
     * Tabela: attendance + attendance_statuses
     * Filtr: statusy inne niż 'obecny' (nieobecny, spóźniony, usprawiedliwiony)
     * ───────────────────────────────────────────── */
    $absences = [];
    try {
        $stmt = $pdo->prepare(
            'SELECT
                a.lesson_date,
                ast.name   AS status_name,
                a.excuse_note
             FROM attendance a
             JOIN attendance_statuses ast ON ast.status_id = a.status_id
             WHERE a.student_id = ?
               AND ast.name != \'obecny\'
             ORDER BY a.lesson_date DESC
             LIMIT 5'
        );
        $stmt->execute([$userId]);
        $absences = $stmt->fetchAll();
    } catch (Exception $e) {
        /* student może nie mieć wpisów lub nie mieć roli ucznia */
    }

    /* ─────────────────────────────────────────────
     * OCENY
     * Tabela: grades + subjects + grade_categories
     * Kolumny: grade (decimal), graded_at (data), created_at (timestamp)
     * "Nowa" ocena = dodana w ciągu ostatnich 7 dni
     * ───────────────────────────────────────────── */
    $grades = [];
    try {
        $stmt = $pdo->prepare(
            'SELECT
                g.grade_id,
                g.grade,
                g.description,
                g.graded_at,
                g.created_at,
                g.color,
                s.name          AS subject_name,
                gc.name         AS category_name,
                gc.weight,
                (g.created_at >= NOW() - INTERVAL 7 DAY) AS is_new
             FROM grades g
             JOIN subjects s      ON s.subject_id  = g.subject_id
             LEFT JOIN grade_categories gc ON gc.category_id = g.category_id
             WHERE g.student_id = ?
             ORDER BY g.created_at DESC
             LIMIT 15'
        );
        $stmt->execute([$userId]);
        $grades = $stmt->fetchAll();
    } catch (Exception $e) {
        /* brak ocen lub użytkownik nie jest uczniem */
    }

    /* Grupuj oceny po przedmiocie */
    $gradesBySubject = [];
    foreach ($grades as $g) {
        $gradesBySubject[$g['subject_name']][] = $g;
    }

    /* ─────────────────────────────────────────────
     * WIADOMOŚCI – liczba nieprzeczytanych
     * Model: message_threads → message_thread_participants → messages
     * Nieprzeczytane = wiadomości w wątkach użytkownika,
     *   wysłane przez kogoś innego,
     *   po last_read_at uczestnika (lub w ogóle nieodczytane)
     * ───────────────────────────────────────────── */
    $unreadCount = 0;
    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM messages m
             JOIN message_thread_participants mtp
                 ON mtp.thread_id = m.thread_id
                AND mtp.user_id   = ?
             WHERE m.sender_id != ?
               AND m.deleted_at  IS NULL
               AND (
                   mtp.last_read_at IS NULL
                   OR m.created_at > mtp.last_read_at
               )'
        );
        $stmt->execute([$userId, $userId]);
        $unreadCount = (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        /* brak wiadomości */
    }

    /* ─────────────────────────────────────────────
     * PLAN ZAJĘĆ
     * Widok: v_current_timetable (aktualny plan)
     * Łączymy z tabelą students, żeby pobrać class_id ucznia
     * Pobieramy plan na dziś i jutro (lub pon. jeśli weekend)
     * ───────────────────────────────────────────── */
    $todaySchedule   = [];
    $tomorrowSchedule = [];
    $studentClassId  = null;

    $todayDow    = (int) date('w');   // 0=Sun, 6=Sat
    $isWeekend   = ($todayDow === 0 || $todayDow === 6);

    // Mapowanie PHP date('w') → days_of_week.day_id (1=Pon … 7=Ndz)
    $phpToDayId = [0 => 7, 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6];

    // Wyznacz day_id na dziś i jutro (pomijamy weekend – szukamy pon.)
    if (!$isWeekend) {
        $todayDayId    = $phpToDayId[$todayDow];
        $tomorrowDow   = ($todayDow === 5) ? 1 : ($todayDow + 1); // po piątku → poniedziałek
        $tomorrowDayId = $phpToDayId[$tomorrowDow];
    } else {
        $todayDayId    = null;
        $tomorrowDayId = 1; // poniedziałek
    }

    try {
        /* Pobierz class_id ucznia */
        $stmtCls = $pdo->prepare(
            'SELECT class_id FROM students WHERE user_id = ? LIMIT 1'
        );
        $stmtCls->execute([$userId]);
        $row = $stmtCls->fetch();
        $studentClassId = $row ? (int) $row['class_id'] : null;

        if ($studentClassId) {
            /* Plan na dziś */
            if ($todayDayId) {
                $stmtT = $pdo->prepare(
                    'SELECT
                        vct.lesson_number,
                        vct.start_hour,
                        vct.end_hour,
                        vct.subject_name,
                        vct.teacher_name,
                        vct.classroom_name
                     FROM v_current_timetable vct
                     WHERE vct.class_id      = ?
                       AND vct.day_of_week_id = ?
                     ORDER BY vct.lesson_number ASC'
                );
                $stmtT->execute([$studentClassId, $todayDayId]);
                $todaySchedule = $stmtT->fetchAll();
            }

            /* Plan na jutro / poniedziałek */
            $stmtT2 = $pdo->prepare(
                'SELECT
                    vct.lesson_number,
                    vct.start_hour,
                    vct.end_hour,
                    vct.subject_name,
                    vct.teacher_name,
                    vct.classroom_name
                 FROM v_current_timetable vct
                 WHERE vct.class_id      = ?
                   AND vct.day_of_week_id = ?
                 ORDER BY vct.lesson_number ASC'
            );
            $stmtT2->execute([$studentClassId, $tomorrowDayId]);
            $tomorrowSchedule = $stmtT2->fetchAll();
        }
    } catch (Exception $e) {
        /* użytkownik nie jest uczniem lub brak planu */
    }

    /* ─────────────────────────────────────────────
     * CZAS SESJI
     * ───────────────────────────────────────────── */
    $sessionExpiry = null;
    try {
        $stmt = $pdo->prepare(
            'SELECT expires_at FROM user_sessions
             WHERE token = ? AND revoked_at IS NULL LIMIT 1'
        );
        $stmt->execute([$_COOKIE['session_token'] ?? '']);
        $row = $stmt->fetch();
        if ($row) $sessionExpiry = $row['expires_at'];
    } catch (Exception $e) { }

    /* ─────────────────────────────────────────────
     * POMOCNICZE – etykiety statusów nieobecności
     * ───────────────────────────────────────────── */
    $statusColors = [
        'nieobecny'        => 'var(--danger)',
        'spóźniony'        => '#fb923c',
        'usprawiedliwiony' => 'var(--gold)',
    ];

    $daysOfWeekPL = ['Niedziela','Poniedziałek','Wtorek','Środa','Czwartek','Piątek','Sobota'];
    $monthNames   = ['stycznia','lutego','marca','kwietnia','maja','czerwca',
                     'lipca','sierpnia','września','października','listopada','grudnia'];

    // Oblicz datę następnego poniedziałku
    $nextMondayDate = null;
    if ($isWeekend) {
        $daysUntilMon = ($todayDow === 6) ? 2 : 1;
        $nextMondayDate = date('d.m.Y', strtotime("+{$daysUntilMon} days"));
    }
    $tomorrowLabel = $isWeekend
        ? "PONIEDZIAŁEK ({$nextMondayDate})"
        : 'JUTRO (' . date('d.m.Y', strtotime('+1 day')) . ')';
    ?>

    <!-- ===== NAVIGATION BAR ===== -->
    <nav class="dashboard-nav">
        <a href="/" class="brand-logo" style="font-size:1.5rem; text-decoration:none;">Edu<span>x</span></a>

        <div class="dash-clock-bar">
            <div class="dash-clock" id="dashClock">--:--</div>
            <div class="dash-date-box">
                <div class="dash-date" id="dashDate"></div>
                <div class="dash-weekday" id="dashWeekday"></div>
            </div>
        </div>

        <div class="dash-user-info">
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

            <!-- ===== KOLUMNA 1 (LEWA) ===== -->
            <div style="display:flex; flex-direction:column; gap:1.5rem;">

                <!-- Wiadomości -->
                <div class="dash-card" style="animation-delay:0.05s">
                    <div class="dash-card-header">
                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
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
                            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
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
                            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            Napisz nową wiadomość
                        </a>
                        <a href="/pages/messages.php?action=all" class="dash-msg-item">
                            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h10" />
                            </svg>
                            Zobacz wszystkie wiadomości
                        </a>
                    </div>
                </div>

                <!-- Ostatnie nieobecności -->
                <div class="dash-card" style="animation-delay:0.1s">
                    <div class="dash-card-header">
                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        Ostatnie nieobecności
                    </div>
                    <div class="dash-card-body" style="padding:0.4rem 1.4rem;">
                        <?php if (empty($absences)): ?>
                            <div class="dash-empty">
                                Brak zarejestrowanych nieobecności
                            </div>
                        <?php else: ?>
                            <?php foreach ($absences as $a):
                                $dotColor = $statusColors[mb_strtolower($a['status_name'])] ?? 'var(--text-muted)';
                            ?>
                                <div class="dash-absence-item">
                                    <div class="dash-absence-dot" style="background:<?= $dotColor ?>;"></div>
                                    <div>
                                        <div style="font-weight:600; font-size:0.95rem;">
                                            <?= date('d.m.Y', strtotime($a['lesson_date'])) ?>
                                            (<?= $daysOfWeekPL[(int)date('w', strtotime($a['lesson_date']))] ?>)
                                        </div>
                                        <div style="font-size:0.82rem; color:var(--text-muted); display:flex; gap:0.5rem; align-items:center;">
                                            <span style="color:<?= $dotColor ?>; font-weight:600;">
                                                <?= htmlspecialchars(ucfirst($a['status_name'])) ?>
                                            </span>
                                            <?php if (!empty($a['excuse_note'])): ?>
                                                · <span><?= htmlspecialchars($a['excuse_note']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div><!-- /col 1 -->

            <!-- ===== KOLUMNA 2 (ŚRODKOWA) ===== -->
            <div style="display:flex; flex-direction:column; gap:1.5rem;">

                <!-- Szybkie akcje -->
                <div class="dash-card" style="animation-delay:0.08s">
                    <div class="dash-card-body">
                        <a href="/pages/student.php" class="dash-action-btn blue">
                            <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <span>Uczeń</span>
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
                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        Ostatnie oceny
                    </div>
                    <div class="dash-card-body">
                        <?php if (empty($gradesBySubject)): ?>
                            <div class="dash-empty">Brak wpisanych ocen</div>
                        <?php else: ?>
                            <div class="dash-student-name"><?= $fullName ?></div>
                            <?php foreach ($gradesBySubject as $subject => $subGrades): ?>
                                <div class="dash-grade-row">
                                    <div class="dash-grade-subject"><?= htmlspecialchars($subject) ?></div>
                                    <div style="display:flex; flex-wrap:wrap; gap:4px; margin-top:4px;">
                                        <?php foreach ($subGrades as $g):
                                            /* Użyj koloru z bazy jeśli ustawiony, wpp. domyślny styl */
                                            $gradeColor  = (!empty($g['color']) && $g['color'] !== '#000000')
                                                ? $g['color'] : null;
                                            $isNew = (bool) $g['is_new'];
                                            /* Formatuj ocenę – usuń .0 dla pełnych liczb */
                                            $gradeDisplay = (fmod((float)$g['grade'], 1) == 0)
                                                ? (int)$g['grade']
                                                : $g['grade'];
                                        ?>
                                            <span
                                                class="dash-grade-value <?= $isNew ? 'dash-grade-new' : 'dash-grade-old' ?>"
                                                title="<?= htmlspecialchars(
                                                    ($g['category_name'] ? $g['category_name'] . ' · ' : '') .
                                                    'waga: ' . ($g['weight'] ?? '1.00') .
                                                    ($g['description'] ? ' · ' . $g['description'] : '') .
                                                    ' · ' . date('d.m.Y', strtotime($g['graded_at']))
                                                ) ?>"
                                                <?= $gradeColor ? "style=\"border-color:{$gradeColor}; color:{$gradeColor};\"" : '' ?>>
                                                <?= $gradeDisplay ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div style="margin-top:0.75rem; font-size:0.8rem; color:var(--text-muted); display:flex; gap:1rem; flex-wrap:wrap;">
                                <span style="display:inline-flex; align-items:center; gap:4px;">
                                    <span class="dash-grade-value dash-grade-new" style="width:22px;height:22px;font-size:0.7rem;">N</span>
                                    nowa ocena (ostatnie 7 dni)
                                </span>
                                <span style="color:var(--text-muted); font-size:0.75rem;">
                                    Najedź na ocenę po szczegóły
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div><!-- /col 2 -->

            <!-- ===== KOLUMNA 3 (PRAWA) ===== -->
            <div style="display:flex; flex-direction:column; gap:1.5rem;">

                <!-- Plan zajęć -->
                <div class="dash-card" style="animation-delay:0.12s">
                    <div class="dash-card-header">
                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                        Plan zajęć
                    </div>
                    <div class="dash-card-body">

                        <?php if ($studentClassId === null): ?>
                            <!-- Użytkownik nie jest uczniem lub nie ma przypisanej klasy -->
                            <div class="dash-empty">
                                Brak przypisanej klasy
                            </div>

                        <?php elseif ($isWeekend): ?>
                            <!-- Weekend -->
                            <div class="dash-empty" style="padding:1rem 0 0.5rem;">
                                Dziś wolne – weekend!
                            </div>

                            <!-- Plan na poniedziałek -->
                            <div class="dash-schedule-day" style="margin-top:1rem;">
                                <div class="dash-schedule-label">
                                    PONIEDZIAŁEK (<?= $nextMondayDate ?>)
                                </div>
                                <?php if (empty($tomorrowSchedule)): ?>
                                    <div class="dash-empty" style="padding:0.5rem 0; font-size:0.88rem;">Brak lekcji w planie</div>
                                <?php else: ?>
                                    <?php foreach ($tomorrowSchedule as $l): ?>
                                        <div class="dash-lesson">
                                            <div class="dash-lesson-num"><?= (int)$l['lesson_number'] ?>.</div>
                                            <div class="dash-lesson-info">
                                                <div class="dash-lesson-subject"><?= htmlspecialchars($l['subject_name'] ?? '—') ?></div>
                                                <div class="dash-lesson-meta">
                                                    <?php if (!empty($l['classroom_name'])): ?>
                                                        sala <?= htmlspecialchars($l['classroom_name']) ?> ·
                                                    <?php endif; ?>
                                                    <?= substr($l['start_hour'], 0, 5) ?>–<?= substr($l['end_hour'], 0, 5) ?>
                                                    <?php if (!empty($l['teacher_name'])): ?>
                                                        · <?= htmlspecialchars($l['teacher_name']) ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                        <?php else: ?>
                            <!-- Dzień roboczy: dziś + jutro -->

                            <!-- DZIŚ -->
                            <div class="dash-schedule-day">
                                <div class="dash-schedule-label">
                                    DZIŚ (<?= $daysOfWeekPL[$todayDow] ?>, <?= date('d.m.Y') ?>)
                                </div>
                                <?php if (empty($todaySchedule)): ?>
                                    <div class="dash-empty" style="padding:0.5rem 0; font-size:0.88rem;">Brak lekcji dzisiaj</div>
                                <?php else: ?>
                                    <?php foreach ($todaySchedule as $l): ?>
                                        <div class="dash-lesson">
                                            <div class="dash-lesson-num"><?= (int)$l['lesson_number'] ?>.</div>
                                            <div class="dash-lesson-info">
                                                <div class="dash-lesson-subject"><?= htmlspecialchars($l['subject_name'] ?? '—') ?></div>
                                                <div class="dash-lesson-meta">
                                                    <?php if (!empty($l['classroom_name'])): ?>
                                                        sala <?= htmlspecialchars($l['classroom_name']) ?> ·
                                                    <?php endif; ?>
                                                    <?= substr($l['start_hour'], 0, 5) ?>–<?= substr($l['end_hour'], 0, 5) ?>
                                                    <?php if (!empty($l['teacher_name'])): ?>
                                                        · <?= htmlspecialchars($l['teacher_name']) ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <!-- JUTRO -->
                            <div class="dash-schedule-day">
                                <div class="dash-schedule-label"><?= $tomorrowLabel ?></div>
                                <?php if (empty($tomorrowSchedule)): ?>
                                    <div class="dash-empty" style="padding:0.5rem 0; font-size:0.88rem;">Brak lekcji jutro</div>
                                <?php else: ?>
                                    <?php foreach ($tomorrowSchedule as $l): ?>
                                        <div class="dash-lesson">
                                            <div class="dash-lesson-num"><?= (int)$l['lesson_number'] ?>.</div>
                                            <div class="dash-lesson-info">
                                                <div class="dash-lesson-subject"><?= htmlspecialchars($l['subject_name'] ?? '—') ?></div>
                                                <div class="dash-lesson-meta">
                                                    <?php if (!empty($l['classroom_name'])): ?>
                                                        sala <?= htmlspecialchars($l['classroom_name']) ?> ·
                                                    <?php endif; ?>
                                                    <?= substr($l['start_hour'], 0, 5) ?>–<?= substr($l['end_hour'], 0, 5) ?>
                                                    <?php if (!empty($l['teacher_name'])): ?>
                                                        · <?= htmlspecialchars($l['teacher_name']) ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                        <?php endif; ?>

                    </div>
                </div>

            </div><!-- /col 3 -->

        </div><!-- /dash-grid -->
    </div><!-- /dash-main -->

    <script>
        // ===== ZEGAR =====
        const DAYS_PL   = ['Niedziela','Poniedziałek','Wtorek','Środa','Czwartek','Piątek','Sobota'];
        const MONTHS_PL = ['stycznia','lutego','marca','kwietnia','maja','czerwca',
                           'lipca','sierpnia','września','października','listopada','grudnia'];

        function updateClock() {
            const now = new Date();
            const hh  = String(now.getHours()).padStart(2, '0');
            const mm  = String(now.getMinutes()).padStart(2, '0');
            document.getElementById('dashClock').textContent   = hh + ':' + mm;
            document.getElementById('dashDate').textContent    =
                now.getDate() + ' ' + MONTHS_PL[now.getMonth()] + ' ' + now.getFullYear();
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
            const diffMs  = sessionExp - Date.now();
            const diffMin = Math.max(0, Math.floor(diffMs / 60000));
            const badge   = document.getElementById('sessionBadge');
            badge.textContent = diffMin + 'm';
            badge.title       = 'Sesja wygaśnie za ' + diffMin + ' min';
            if (diffMin <= 5) {
                badge.style.borderColor = 'var(--danger)';
                badge.style.color       = 'var(--danger)';
                badge.style.background  = 'rgba(248,113,113,0.15)';
            }
            if (diffMin <= 0) {
                window.location.href = '/pages/login.php?msg=session_expired';
            }
        }
        updateSession();
        setInterval(updateSession, 30000);

        // ===== WYLOGOWANIE =====
        async function logout() {
            try {
                const res  = await fetch('/api/logout.php', { method: 'POST' });
                const data = await res.json();
                window.location.href = data.redirect || '/pages/login.php';
            } catch {
                window.location.href = '/pages/login.php';
            }
        }
    </script>
</body>
</html>