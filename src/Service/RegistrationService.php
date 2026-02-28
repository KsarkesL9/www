<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\LookupRepositoryInterface;
use App\Repository\UserRepositoryInterface;

/**
 * @brief Service class for registering new user accounts.
 *
 * @details This service validates all registration form fields,
 *          checks that the email is not already taken, verifies
 *          that the role and country IDs exist in the database,
 *          generates a unique login name, hashes the password,
 *          and inserts the new user with a 'pending' status.
 *          The class contains no SQL, no PDO, and no HTTP code.
 */
class RegistrationService
{
    /**
     * @brief Creates a new RegistrationService with the required repositories.
     *
     * @param UserRepositoryInterface   $userRepo   Repository for inserting users and checking uniqueness.
     * @param LookupRepositoryInterface $lookupRepo Repository for checking roles, countries, and statuses.
     */
    public function __construct(
        private UserRepositoryInterface $userRepo,
        private LookupRepositoryInterface $lookupRepo,
    ) {
    }

    /**
     * @brief Validates the registration input and creates a new user account.
     *
     * @details Checks that all required fields are present and not empty.
     *          Validates the email format using PHP's filter_var().
     *          Checks that the password is at least 8 characters and that
     *          both password fields match. Checks the date of birth format
     *          (must be YYYY-MM-DD). Verifies that the email is not already
     *          registered, that the role ID exists, and that the country ID
     *          exists. Gets the 'pending' status ID for the new account.
     *          Generates a unique login with generateLogin() and hashes the
     *          password with bcrypt. Inserts the new user via the repository.
     *          Returns success with the generated login on success, or an error
     *          array with a 'field' key pointing to the first invalid field.
     *
     * @param array $input An associative array of registration form values.
     *                     Required keys: role_id, first_name, surname,
     *                     email_address, password, password_confirm,
     *                     date_of_birth, country_id, city, street,
     *                     building_number.
     *                     Optional keys: second_name, apartment_number,
     *                     phone_number.
     *
     * @return array{success: bool, message: string, login?: string, field?: string}
     *         On success: success=true with the 'login' key.
     *         On failure: success=false with a 'message' and optionally 'field'.
     */
    public function register(array $input): array
    {
        // Validate required fields
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
            'building_number',
        ];

        foreach ($required as $field) {
            if (empty($input[$field])) {
                return ['success' => false, 'message' => 'Wypełnij wszystkie wymagane pola.', 'field' => $field];
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

        // Validate format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Podaj prawidłowy adres e-mail.'];
        }
        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Hasło musi mieć minimum 8 znaków.'];
        }
        if ($password !== $passwordConfirm) {
            return ['success' => false, 'message' => 'Hasła nie są identyczne.'];
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateOfBirth)) {
            return ['success' => false, 'message' => 'Nieprawidłowy format daty urodzenia.'];
        }

        // Validate database data
        if ($this->userRepo->isEmailTaken($email)) {
            return ['success' => false, 'message' => 'Podany adres e-mail jest już zarejestrowany.'];
        }
        if (!$this->lookupRepo->roleExists($roleId)) {
            return ['success' => false, 'message' => 'Nieprawidłowa rola.'];
        }
        if (!$this->lookupRepo->countryExists($countryId)) {
            return ['success' => false, 'message' => 'Nieprawidłowe obywatelstwo.'];
        }

        // Get pending status ID
        $pendingStatusId = $this->lookupRepo->getStatusIdByName('oczekujący');
        if ($pendingStatusId === null) {
            return ['success' => false, 'message' => 'Błąd konfiguracji systemu – brak statusu oczekujący.'];
        }

        // Generate login and hash password
        $login = $this->generateLogin($firstName, $surname);
        $passwordHashed = password_hash($password, PASSWORD_BCRYPT);

        $userId = $this->userRepo->insert([
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

        $this->userRepo->assignRoleToUser($userId, $roleId);

        return ['success' => true, 'message' => 'Konto zostało pomyślnie utworzone.', 'login' => $login];
    }

    /**
     * @brief Generates a unique login name from the user's initials and random digits.
     *
     * @details Takes the first character of the first name and the first character
     *          of the surname, converts them to ASCII (removes accents), and makes
     *          them lowercase. If both characters are empty after conversion, uses
     *          the letter 'u' as a base. Appends a zero-padded 6-digit random
     *          number (000000–999999) to the base. Repeats until a login that
     *          does not already exist in the database is found.
     *
     * @param string $firstName  The user's first name.
     * @param string $surname    The user's surname.
     *
     * @return string A unique login string in the format: {initials}{6 digits},
     *                for example 'jk042731'.
     */
    private function generateLogin(string $firstName, string $surname): string
    {
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
        } while ($this->userRepo->loginExists($login));

        return $login;
    }
}
