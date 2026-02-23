<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jsonResponse(false, 'Niedozwolona metoda.');
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$login = trim($input['login'] ?? '');
$password = $input['password'] ?? '';

if (empty($login) || empty($password)) {
    jsonResponse(false, 'Podaj login i hasło.');
}

$pdo = getDB();

// Pobierz użytkownika
$stmt = $pdo->prepare(
    'SELECT u.user_id, u.password, u.status_id, u.failed_login_attempts,
            s.name AS status_name
     FROM users u
     JOIN statuses s ON s.status_id = u.status_id
     WHERE u.login = ?
     LIMIT 1'
);
$stmt->execute([$login]);
$user = $stmt->fetch();

// Użytkownik nie istnieje – ogólny komunikat
if (!$user) {
    jsonResponse(false, 'Nieprawidłowy login lub hasło.');
}

$userId = (int) $user['user_id'];
$statusName = $user['status_name'];
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
    $newAttempts = $failedAttempts + 1;

    if ($newAttempts >= 5) {
        // Zablokuj konto
        blockUser($userId);
        $updateStmt = $pdo->prepare(
            'UPDATE users SET failed_login_attempts = ? WHERE user_id = ?'
        );
        $updateStmt->execute([$newAttempts, $userId]);
        jsonResponse(false, 'Konto zostało zablokowane po 5 nieudanych próbach logowania. Zresetuj hasło, aby je odblokować.');
    }

    $updateStmt = $pdo->prepare(
        'UPDATE users SET failed_login_attempts = ? WHERE user_id = ?'
    );
    $updateStmt->execute([$newAttempts, $userId]);

    $remaining = 5 - $newAttempts;
    jsonResponse(false, "Nieprawidłowy login lub hasło. Pozostało prób: $remaining.");
}

// Udane logowanie – zeruj licznik nieudanych prób
$resetStmt = $pdo->prepare(
    'UPDATE users SET failed_login_attempts = 0 WHERE user_id = ?'
);
$resetStmt->execute([$userId]);

// Utwórz sesję
$token = createSession($userId);

jsonResponse(true, 'Zalogowano pomyślnie.', ['redirect' => '/pages/dashboard.php']);
