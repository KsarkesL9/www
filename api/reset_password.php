<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jsonResponse(false, 'Niedozwolona metoda.');
}

$input           = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$token           = trim($input['token'] ?? '');
$newPassword     = $input['new_password'] ?? '';
$confirmPassword = $input['confirm_password'] ?? '';

if (empty($token) || empty($newPassword) || empty($confirmPassword)) {
    jsonResponse(false, 'Wypełnij wszystkie pola.');
}

if (strlen($newPassword) < 8) {
    jsonResponse(false, 'Hasło musi mieć minimum 8 znaków.');
}

if ($newPassword !== $confirmPassword) {
    jsonResponse(false, 'Hasła nie są identyczne.');
}

$pdo = getDB();

// Weryfikuj token
$stmt = $pdo->prepare(
    'SELECT prt.user_id, prt.token_id
     FROM password_reset_tokens prt
     WHERE prt.token = ?
       AND prt.expires_at > NOW()
       AND prt.used_at IS NULL
     LIMIT 1'
);
$stmt->execute([$token]);
$tokenRow = $stmt->fetch();

if (!$tokenRow) {
    jsonResponse(false, 'Token jest nieprawidłowy lub wygasł.');
}

$userId  = (int)$tokenRow['user_id'];
$tokenId = (int)$tokenRow['token_id'];

$hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

try {
    $pdo->beginTransaction();

    // Zaktualizuj hasło i zresetuj licznik nieudanych logowań
    $pdo->prepare(
        'UPDATE users SET password = ?, last_password_change = NOW(), failed_login_attempts = 0 WHERE user_id = ?'
    )->execute([$hashedPassword, $userId]);

    // Oznacz token jako użyty
    $pdo->prepare(
        'UPDATE password_reset_tokens SET used_at = NOW() WHERE token_id = ?'
    )->execute([$tokenId]);

    // Unieważnij wszystkie aktywne sesje użytkownika
    revokeAllUserSessions($userId);

    // Aktywuj konto (odblokuj jeśli zablokowane)
    $activeStatusId = getStatusIdByName('aktywny');
    if ($activeStatusId) {
        $pdo->prepare(
            'UPDATE users SET status_id = ? WHERE user_id = ?'
        )->execute([$activeStatusId, $userId]);
    }

    $pdo->commit();

    // Usuń ciasteczko sesji bieżącego użytkownika
    setcookie('session_token', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    jsonResponse(true, 'Hasło zostało zmienione. Zaloguj się nowym hasłem.');
} catch (Exception $e) {
    $pdo->rollBack();
    jsonResponse(false, 'Błąd podczas zmiany hasła. Spróbuj ponownie.');
}
