<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jsonResponse(false, 'Niedozwolona metoda.');
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// --- Walidacja pól ---
$required = [
    'role_id',
    'first_name',
    'surname',
    'email_address',
    'password',
    'password_confirm',
    'date_of_birth',
    'country_id',
    'city',
    'street',
    'building_number'
];

foreach ($required as $field) {
    if (empty($input[$field])) {
        jsonResponse(false, 'Wypełnij wszystkie wymagane pola.', ['field' => $field]);
    }
}

$firstName = trim($input['first_name']);
$secondName = trim($input['second_name'] ?? '');
$surname = trim($input['surname']);
$email = trim($input['email_address']);
$password = $input['password'];
$passwordConfirm = $input['password_confirm'];
$dateOfBirth = $input['date_of_birth'];
$countryId = (int) $input['country_id'];
$roleId = (int) $input['role_id'];
$city = trim($input['city']);
$street = trim($input['street']);
$buildingNumber = trim($input['building_number']);
$apartmentNumber = trim($input['apartment_number'] ?? '');
$phoneNumber = trim($input['phone_number'] ?? '');

// Walidacja e-mail
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, 'Podaj prawidłowy adres e-mail.');
}

// Walidacja hasła
if (strlen($password) < 8) {
    jsonResponse(false, 'Hasło musi mieć minimum 8 znaków.');
}
if ($password !== $passwordConfirm) {
    jsonResponse(false, 'Hasła nie są identyczne.');
}

// Walidacja daty urodzenia
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateOfBirth)) {
    jsonResponse(false, 'Nieprawidłowy format daty urodzenia.');
}

$pdo = getDB();

// Sprawdź unikalność e-maila
$stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email_address = ?');
$stmt->execute([$email]);
if ($stmt->fetchColumn() > 0) {
    jsonResponse(false, 'Podany adres e-mail jest już zarejestrowany.');
}

// Sprawdź poprawność role_id
$stmt = $pdo->prepare('SELECT role_id FROM roles WHERE role_id = ?');
$stmt->execute([$roleId]);
if (!$stmt->fetch()) {
    jsonResponse(false, 'Nieprawidłowa rola.');
}

// Sprawdź poprawność country_id
$stmt = $pdo->prepare('SELECT country_id FROM countries WHERE country_id = ?');
$stmt->execute([$countryId]);
if (!$stmt->fetch()) {
    jsonResponse(false, 'Nieprawidłowe obywatelstwo.');
}

// Pobierz status_id dla 'oczekujący'
$pendingStatusId = getStatusIdByName('oczekujący');
if ($pendingStatusId === null) {
    jsonResponse(false, 'Błąd konfiguracji systemu – brak statusu oczekujący.');
}

// Generuj login
$login = generateLogin($firstName, $surname);
$passwordHashed = password_hash($password, PASSWORD_BCRYPT);

try {
    $stmt = $pdo->prepare(
        'INSERT INTO users
         (role_id, status_id, first_name, second_name, surname, email_address,
          login, password, date_of_birth, country_id, city, street,
          building_number, apartment_number, phone_number, last_password_change)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        $roleId,
        $pendingStatusId,
        $firstName,
        $secondName ?: null,
        $surname,
        $email,
        $login,
        $passwordHashed,
        $dateOfBirth,
        $countryId,
        $city,
        $street,
        $buildingNumber,
        $apartmentNumber ?: null,
        $phoneNumber ?: null,
    ]);

    jsonResponse(true, 'Konto zostało pomyślnie utworzone.', ['login' => $login]);
} catch (PDOException $e) {
    jsonResponse(false, 'Błąd podczas rejestracji. Spróbuj ponownie.');
}
