<?php
/**
 * Zarządzanie sesjami użytkowników — tworzenie, odświeżanie, unieważnianie.
 */

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

    $stmt = $pdo->prepare(
        'INSERT INTO user_sessions (user_id, token, ip_address, user_agent, expires_at)
         VALUES (?, ?, ?, ?, NOW() + INTERVAL 1 HOUR)'
    );
    $stmt->execute([$userId, $token, $ip, $ua]);

    setSessionCookie($token);

    return $token;
}

/**
 * Odświeża sesję (przesuwa czas wygaśnięcia)
 */
function refreshSession(string $token): void
{
    $pdo = getDB();
    $stmt = $pdo->prepare(
        'UPDATE user_sessions SET expires_at = NOW() + INTERVAL 1 HOUR
         WHERE token = ? AND revoked_at IS NULL'
    );
    $stmt->execute([$token]);

    setSessionCookie($token);
}

/**
 * Unieważnia pojedynczą sesję na podstawie tokenu
 */
function revokeSession(string $token): void
{
    $pdo = getDB();
    $stmt = $pdo->prepare(
        'UPDATE user_sessions SET revoked_at = NOW() WHERE token = ?'
    );
    $stmt->execute([$token]);
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
 * Ustawia ciasteczko sesji (1 godzina)
 */
function setSessionCookie(string $token): void
{
    setcookie('session_token', $token, [
        'expires' => time() + 3600,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

/**
 * Usuwa ciasteczko sesji
 */
function deleteSessionCookie(): void
{
    setcookie('session_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}
