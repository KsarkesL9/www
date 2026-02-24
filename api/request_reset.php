<?php
require_once __DIR__ . '/../includes/bootstrap.php';

requireMethod('POST');

$input = getJsonInput();
$login = trim($input['login'] ?? '');
$email = trim($input['email_address'] ?? '');

if (empty($login) || empty($email)) {
    jsonResponse(false, 'Podaj login i adres e-mail.');
}

if (!validateEmail($email)) {
    jsonResponse(false, 'Podaj prawidłowy adres e-mail.');
}

$pdo = getDB();

// Znajdź użytkownika
$stmt = $pdo->prepare(
    'SELECT user_id FROM users WHERE login = ? AND email_address = ? LIMIT 1'
);
$stmt->execute([$login, $email]);
$user = $stmt->fetch();

// Odpowiedź ogólna — nie ujawniamy czy konto istnieje
if (!$user) {
    jsonResponse(true, 'Jeśli podane dane są prawidłowe, token resetowania hasła został wygenerowany. Zapisz go poniżej.');
}

$userId = (int) $user['user_id'];

// Unieważnij poprzednie tokeny i wygeneruj nowy
revokeUserResetTokens($userId);
$token = createResetToken($userId);

jsonResponse(true, 'Token wygenerowany pomyślnie. Użyj go do zresetowania hasła.', [
    'token' => $token,
    'expires_in' => '30 minut'
]);
