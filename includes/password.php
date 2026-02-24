<?php
/**
 * Logika resetowania haseł — tokeny, zmiana hasła.
 */

/**
 * Unieważnia wszystkie aktywne tokeny resetowania hasła dla użytkownika
 */
function revokeUserResetTokens(int $userId): void
{
    $pdo = getDB();
    $pdo->prepare(
        'UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL'
    )->execute([$userId]);
}

/**
 * Tworzy nowy token resetowania hasła (ważny 30 minut) — zwraca token
 */
function createResetToken(int $userId): string
{
    $pdo = getDB();
    $token = generateToken(32);

    $stmt = $pdo->prepare(
        'INSERT INTO password_reset_tokens (user_id, token, expires_at)
         VALUES (?, ?, NOW() + INTERVAL 30 MINUTE)'
    );
    $stmt->execute([$userId, $token]);

    return $token;
}

/**
 * Wyszukuje ważny, nieużyty token resetowania — zwraca wiersz lub null
 */
function findValidResetToken(string $token): ?array
{
    $pdo = getDB();
    $stmt = $pdo->prepare(
        'SELECT prt.user_id, prt.token_id
         FROM password_reset_tokens prt
         WHERE prt.token = ?
           AND prt.expires_at > NOW()
           AND prt.used_at IS NULL
         LIMIT 1'
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Oznacza token resetowania jako użyty
 */
function markTokenUsed(int $tokenId): void
{
    $pdo = getDB();
    $pdo->prepare(
        'UPDATE password_reset_tokens SET used_at = NOW() WHERE token_id = ?'
    )->execute([$tokenId]);
}

/**
 * Aktualizuje hasło użytkownika i zeruje licznik nieudanych logowań
 */
function updatePassword(int $userId, string $hashedPassword): void
{
    $pdo = getDB();
    $pdo->prepare(
        'UPDATE users SET password = ?, last_password_change = NOW(), failed_login_attempts = 0 WHERE user_id = ?'
    )->execute([$hashedPassword, $userId]);
}
