<?php
require_once __DIR__ . '/../includes/bootstrap.php';

requireMethod('POST');

$input = getJsonInput();
$token = trim($input['token'] ?? '');
$newPassword = $input['new_password'] ?? '';
$confirmPassword = $input['confirm_password'] ?? '';

if (empty($token) || empty($newPassword) || empty($confirmPassword)) {
    jsonResponse(false, 'Wypełnij wszystkie pola.');
}

if (!validatePassword($newPassword)) {
    jsonResponse(false, 'Hasło musi mieć minimum 8 znaków.');
}

if (!validatePasswordMatch($newPassword, $confirmPassword)) {
    jsonResponse(false, 'Hasła nie są identyczne.');
}

// Weryfikuj token
$tokenRow = findValidResetToken($token);
if (!$tokenRow) {
    jsonResponse(false, 'Token jest nieprawidłowy lub wygasł.');
}

$userId = (int) $tokenRow['user_id'];
$tokenId = (int) $tokenRow['token_id'];
$hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

try {
    $pdo = getDB();
    $pdo->beginTransaction();

    updatePassword($userId, $hashedPassword);
    markTokenUsed($tokenId);
    revokeAllUserSessions($userId);
    activateUser($userId);

    $pdo->commit();

    deleteSessionCookie();

    jsonResponse(true, 'Hasło zostało zmienione. Zaloguj się nowym hasłem.');
} catch (Exception $e) {
    $pdo->rollBack();
    jsonResponse(false, 'Błąd podczas zmiany hasła. Spróbuj ponownie.');
}
