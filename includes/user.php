<?php
/**
 * Operacje na użytkownikach — wyszukiwanie, tworzenie, statusy, role.
 */

/**
 * Generuje unikalny login użytkownika
 * Format: pierwsza litera imienia + pierwsza litera nazwiska + 6 losowych cyfr
 */
function generateLogin(string $firstName, string $surname): string
{
    $pdo = getDB();

    $initFirst = iconv('UTF-8', 'ASCII//TRANSLIT', mb_substr($firstName, 0, 1));
    $initSurname = iconv('UTF-8', 'ASCII//TRANSLIT', mb_substr($surname, 0, 1));

    $base = preg_replace('/[^a-z]/', '', mb_strtolower($initFirst));
    $base .= preg_replace('/[^a-z]/', '', mb_strtolower($initSurname));

    if ($base === '') {
        $base = 'u';
    }

    do {
        $suffix = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $login = $base . $suffix;

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE login = ?');
        $stmt->execute([$login]);
    } while ($stmt->fetchColumn() > 0);

    return $login;
}

/**
 * Zwraca status_id dla podanej nazwy statusu
 */
function getStatusIdByName(string $name): ?int
{
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT status_id FROM statuses WHERE name = ? LIMIT 1');
    $stmt->execute([$name]);
    $row = $stmt->fetch();
    return $row ? (int) $row['status_id'] : null;
}

/**
 * Zwraca role_id dla podanej nazwy roli
 */
function getRoleIdByName(string $roleName): ?int
{
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT role_id FROM roles WHERE role_name = ? LIMIT 1');
    $stmt->execute([$roleName]);
    $row = $stmt->fetch();
    return $row ? (int) $row['role_id'] : null;
}

/**
 * Blokuje konto użytkownika (ustawia status na zablokowany)
 */
function blockUser(int $userId): void
{
    $pdo = getDB();
    $blockedStatusId = getStatusIdByName('zablokowany');
    if ($blockedStatusId === null)
        return;
    $stmt = $pdo->prepare('UPDATE users SET status_id = ? WHERE user_id = ?');
    $stmt->execute([$blockedStatusId, $userId]);
}

/**
 * Wyszukuje użytkownika po loginie — zwraca wiersz lub null
 */
function findUserByLogin(string $login): ?array
{
    $pdo = getDB();
    $stmt = $pdo->prepare(
        'SELECT u.user_id, u.password, u.status_id, u.failed_login_attempts,
                s.name AS status_name
         FROM users u
         JOIN statuses s ON s.status_id = u.status_id
         WHERE u.login = ?
         LIMIT 1'
    );
    $stmt->execute([$login]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Zwiększa licznik nieudanych prób logowania o 1
 */
function incrementFailedLogins(int $userId): int
{
    $pdo = getDB();
    $pdo->prepare(
        'UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE user_id = ?'
    )->execute([$userId]);

    // Zwróć aktualną wartość
    $stmt = $pdo->prepare('SELECT failed_login_attempts FROM users WHERE user_id = ?');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Zeruje licznik nieudanych prób logowania
 */
function resetFailedLogins(int $userId): void
{
    $pdo = getDB();
    $pdo->prepare(
        'UPDATE users SET failed_login_attempts = 0 WHERE user_id = ?'
    )->execute([$userId]);
}

/**
 * Aktywuje konto użytkownika (status → aktywny)
 */
function activateUser(int $userId): void
{
    $pdo = getDB();
    $activeStatusId = getStatusIdByName('aktywny');
    if ($activeStatusId === null)
        return;
    $pdo->prepare(
        'UPDATE users SET status_id = ? WHERE user_id = ?'
    )->execute([$activeStatusId, $userId]);
}

/**
 * Sprawdza czy adres e-mail jest już zajęty
 */
function isEmailTaken(string $email): bool
{
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email_address = ?');
    $stmt->execute([$email]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Sprawdza czy rola o podanym ID istnieje
 */
function roleExists(int $roleId): bool
{
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT role_id FROM roles WHERE role_id = ?');
    $stmt->execute([$roleId]);
    return (bool) $stmt->fetch();
}

/**
 * Sprawdza czy kraj o podanym ID istnieje
 */
function countryExists(int $countryId): bool
{
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT country_id FROM countries WHERE country_id = ?');
    $stmt->execute([$countryId]);
    return (bool) $stmt->fetch();
}

/**
 * Wstawia nowego użytkownika do bazy danych — zwraca user_id
 */
function insertUser(array $data): int
{
    $pdo = getDB();
    $stmt = $pdo->prepare(
        'INSERT INTO users
         (role_id, status_id, first_name, second_name, surname, email_address,
          login, password, date_of_birth, country_id, city, street,
          building_number, apartment_number, phone_number, last_password_change)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        $data['role_id'],
        $data['status_id'],
        $data['first_name'],
        $data['second_name'],
        $data['surname'],
        $data['email_address'],
        $data['login'],
        $data['password'],
        $data['date_of_birth'],
        $data['country_id'],
        $data['city'],
        $data['street'],
        $data['building_number'],
        $data['apartment_number'],
        $data['phone_number'],
    ]);
    return (int) $pdo->lastInsertId();
}
