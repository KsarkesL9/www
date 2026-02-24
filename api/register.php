<?php
require_once __DIR__ . '/../includes/bootstrap.php';

requireMethod('POST');

$input = getJsonInput();

// Walidacja wymaganych pól
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

$missingField = validateRequiredFields($input, $required);
if ($missingField !== null) {
    jsonResponse(false, 'Wypełnij wszystkie wymagane pola.', ['field' => $missingField]);
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

// Walidacja formatu
if (!validateEmail($email)) {
    jsonResponse(false, 'Podaj prawidłowy adres e-mail.');
}
if (!validatePassword($password)) {
    jsonResponse(false, 'Hasło musi mieć minimum 8 znaków.');
}
if (!validatePasswordMatch($password, $passwordConfirm)) {
    jsonResponse(false, 'Hasła nie są identyczne.');
}
if (!validateDateFormat($dateOfBirth)) {
    jsonResponse(false, 'Nieprawidłowy format daty urodzenia.');
}

// Walidacja danych w bazie
if (isEmailTaken($email)) {
    jsonResponse(false, 'Podany adres e-mail jest już zarejestrowany.');
}
if (!roleExists($roleId)) {
    jsonResponse(false, 'Nieprawidłowa rola.');
}
if (!countryExists($countryId)) {
    jsonResponse(false, 'Nieprawidłowe obywatelstwo.');
}

// Status oczekujący
$pendingStatusId = getStatusIdByName('oczekujący');
if ($pendingStatusId === null) {
    jsonResponse(false, 'Błąd konfiguracji systemu – brak statusu oczekujący.');
}

// Generuj login i hash hasła
$login = generateLogin($firstName, $surname);
$passwordHashed = password_hash($password, PASSWORD_BCRYPT);

try {
    insertUser([
        'role_id' => $roleId,
        'status_id' => $pendingStatusId,
        'first_name' => $firstName,
        'second_name' => $secondName ?: null,
        'surname' => $surname,
        'email_address' => $email,
        'login' => $login,
        'password' => $passwordHashed,
        'date_of_birth' => $dateOfBirth,
        'country_id' => $countryId,
        'city' => $city,
        'street' => $street,
        'building_number' => $buildingNumber,
        'apartment_number' => $apartmentNumber ?: null,
        'phone_number' => $phoneNumber ?: null,
    ]);

    jsonResponse(true, 'Konto zostało pomyślnie utworzone.', ['login' => $login]);
} catch (PDOException $e) {
    jsonResponse(false, 'Błąd podczas rejestracji. Spróbuj ponownie.');
}
