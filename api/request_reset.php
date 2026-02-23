<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jsonResponse(false, 'Niedozwolona metoda.');
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$login = trim($input['login'] ?? '');
$email = trim($input['email_address'] ?? '');

if (empty($login) || empty($email)) {
    jsonResponse(false, 'Podaj login i adres e-mail.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, 'Podaj prawidłowy adres e-mail.');
}

$pdo = getDB();

// Znajdź użytkownika
$stmt = $pdo->prepare(
    'SELECT user_id FROM users WHERE login = ? AND email_address = ? LIMIT 1'
);
$stmt->execute([$login, $email]);
$user = $stmt->fetch();

// Odpowiedź ogólna – nie ujawniamy, czy konto istnieje
if (!$user) {
    jsonResponse(true, 'Jeśli podane dane są prawidłowe, token resetowania hasła został wygenerowany. Zapisz go poniżej.');
}

$userId = (int) $user['user_id'];

// Unieważnij poprzednie tokeny
$pdo->prepare(
    'UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL'
)->execute([$userId]);

// Wygeneruj nowy token (ważny 30 minut)
// expires_at obliczane po stronie MySQL – unika problemów ze strefą czasową
$token = generateToken(32);

$stmt = $pdo->prepare(
    'INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, NOW() + INTERVAL 30 MINUTE)'
);
$stmt->execute([$userId, $token]);

// W prawdziwej aplikacji wysłalibyśmy e-mail. Tu zwracamy token do celów demonstracyjnych.
jsonResponse(true, 'Token wygenerowany pomyślnie. Użyj go do zresetowania hasła.', [
    'token' => $token,
    'expires_in' => '30 minut'
]);
