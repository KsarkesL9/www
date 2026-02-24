<?php
require_once __DIR__ . '/../includes/bootstrap.php';

requireMethod('POST');

$input = getJsonInput();
$login = trim($input['login'] ?? '');
$password = $input['password'] ?? '';

if (empty($login) || empty($password)) {
    jsonResponse(false, 'Podaj login i hasło.');
}

// Pobierz użytkownika
$user = findUserByLogin($login);

if (!$user) {
    jsonResponse(false, 'Nieprawidłowy login lub hasło.');
}

$userId = (int) $user['user_id'];
$failedAttempts = (int) $user['failed_login_attempts'];

// Sprawdź status konta
$activeStatusId = getStatusIdByName('aktywny');
$blockedStatusId = getStatusIdByName('zablokowany');
$pendingStatusId = getStatusIdByName('oczekujący');

if ((int) $user['status_id'] === $blockedStatusId) {
    jsonResponse(false, 'Konto zostało zablokowane z powodu zbyt wielu nieudanych prób logowania. Skontaktuj się z administratorem lub zresetuj hasło.');
}

if ((int) $user['status_id'] === $pendingStatusId) {
    jsonResponse(false, 'Twoje konto oczekuje na aktywację przez administratora. Skontaktuj się z działem obsługi.');
}

if ((int) $user['status_id'] !== $activeStatusId) {
    jsonResponse(false, 'Twoje konto jest nieaktywne. Skontaktuj się z administratorem.');
}

// Weryfikacja hasła
if (!password_verify($password, $user['password'])) {
    $newAttempts = incrementFailedLogins($userId);

    if ($newAttempts >= 5) {
        blockUser($userId);
        jsonResponse(false, 'Konto zostało zablokowane po 5 nieudanych próbach logowania. Zresetuj hasło, aby je odblokować.');
    }

    $remaining = 5 - $newAttempts;
    jsonResponse(false, "Nieprawidłowy login lub hasło. Pozostało prób: $remaining.");
}

// Udane logowanie
resetFailedLogins($userId);
$token = createSession($userId);

jsonResponse(true, 'Zalogowano pomyślnie.', ['redirect' => '/pages/dashboard.php']);
