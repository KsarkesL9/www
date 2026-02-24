<?php
require_once __DIR__ . '/../config/db.php';

/**
 * Generuje unikalny login użytkownika
 * Format: pierwsza litera imienia + pierwsza litera nazwiska + 6 losowych cyfr
 * Przykład: jk482931
 */
function generateLogin(string $firstName, string $surname): string
{
    $pdo = getDB();

    // Pobierz inicjały i transliteruj polskie znaki na ASCII
    $initFirst = iconv('UTF-8', 'ASCII//TRANSLIT', mb_substr($firstName, 0, 1));
    $initSurname = iconv('UTF-8', 'ASCII//TRANSLIT', mb_substr($surname, 0, 1));

    // Zostaw tylko litery, zamień na małe
    $base = preg_replace('/[^a-z]/', '', mb_strtolower($initFirst));
    $base .= preg_replace('/[^a-z]/', '', mb_strtolower($initSurname));

    // Fallback – gdy imię/nazwisko jest puste lub nie zawiera liter
    if ($base === '') {
        $base = 'u';
    }

    do {
        // 6 losowych cyfr z kryptograficznie bezpiecznego generatora
        $suffix = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $login = $base . $suffix;

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE login = ?');
        $stmt->execute([$login]);
    } while ($stmt->fetchColumn() > 0);

    return $login;
}

/**
 * Zwraca status_id dla podanej nazwy statusu
 */
function getStatusIdByName(string $name): ?int
{
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT status_id FROM statuses WHERE name = ? LIMIT 1');
    $stmt->execute([$name]);
    $row = $stmt->fetch();
    return $row ? (int) $row['status_id'] : null;
}

/**
 * Zwraca role_id dla podanej nazwy roli
 */
function getRoleIdByName(string $roleName): ?int
{
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT role_id FROM roles WHERE role_name = ? LIMIT 1');
    $stmt->execute([$roleName]);
    $row = $stmt->fetch();
    return $row ? (int) $row['role_id'] : null;
}

/**
 * Generuje kryptograficznie bezpieczny token
 */
function generateToken(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

/**
 * Zwraca adres IP klienta
 */
function getClientIp(): string
{
    foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

/**
 * Zwraca User-Agent przeglądarki (obcięty do 255 znaków)
 */
function getUserAgent(): string
{
    return mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
}

/**
 * Tworzy nową sesję w bazie danych i ustawia ciasteczko
 */
function createSession(int $userId): string
{
    $pdo = getDB();
    $token = generateToken(32);
    $ip = getClientIp();
    $ua = getUserAgent();

    // expires_at obliczane po stronie MySQL – unika problemów ze strefą czasową
    $stmt = $pdo->prepare(
        'INSERT INTO user_sessions (user_id, token, ip_address, user_agent, expires_at)
         VALUES (?, ?, ?, ?, NOW() + INTERVAL 1 HOUR)'
    );
    $stmt->execute([$userId, $token, $ip, $ua]);

    setcookie('session_token', $token, [
        'expires' => time() + 3600,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Strict',
        // 'secure' => true, // Odkomentuj na HTTPS
    ]);

    return $token;
}

/**
 * Odświeża sesję (przesuwa czas wygaśnięcia)
 */
function refreshSession(string $token): void
{
    $pdo = getDB();
    // expires_at obliczane po stronie MySQL – unika problemów ze strefą czasową
    $stmt = $pdo->prepare(
        'UPDATE user_sessions SET expires_at = NOW() + INTERVAL 1 HOUR
         WHERE token = ? AND revoked_at IS NULL'
    );
    $stmt->execute([$token]);

    setcookie('session_token', $token, [
        'expires' => time() + 3600,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

/**
 * Unieważnia wszystkie sesje danego użytkownika
 */
function revokeAllUserSessions(int $userId): void
{
    $pdo = getDB();
    $stmt = $pdo->prepare(
        'UPDATE user_sessions SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL'
    );
    $stmt->execute([$userId]);
}

/**
 * Zwraca dane sesji na podstawie tokenu z ciasteczka
 */
function getSessionFromCookie(): ?array
{
    $token = $_COOKIE['session_token'] ?? null;
    if (!$token)
        return null;

    $pdo = getDB();
    $stmt = $pdo->prepare(
        'SELECT s.*, u.status_id, u.role_id, u.first_name, u.surname, r.role_name
         FROM user_sessions s
         JOIN users u ON u.user_id = s.user_id
         JOIN roles r ON r.role_id = u.role_id
         WHERE s.token = ?
           AND s.revoked_at IS NULL
           AND s.expires_at > NOW()
         LIMIT 1'
    );
    $stmt->execute([$token]);
    $session = $stmt->fetch();
    if (!$session)
        return null;

    // Sprawdź czy konto jest aktywne
    $activeStatusId = getStatusIdByName('aktywny');
    if ($session['status_id'] != $activeStatusId)
        return null;

    // Odśwież sesję
    refreshSession($token);

    return $session;
}

/**
 * Wysyła odpowiedź JSON
 */
function jsonResponse(bool $success, string $message, array $data = []): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

/**
 * Blokuje konto użytkownika (ustawia status na zablokowany)
 */
function blockUser(int $userId): void
{
    $pdo = getDB();
    $blockedStatusId = getStatusIdByName('zablokowany');
    if ($blockedStatusId === null)
        return;
    $stmt = $pdo->prepare('UPDATE users SET status_id = ? WHERE user_id = ?');
    $stmt->execute([$blockedStatusId, $userId]);
}
