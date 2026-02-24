<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\LookupRepositoryInterface;
use App\Repository\UserRepositoryInterface;

/**
 * Serwis rejestracji użytkowników.
 *
 * Zero SQL, zero PDO, zero HTTP.
 */
class RegistrationService
{
    public function __construct(
        private UserRepositoryInterface $userRepo,
        private LookupRepositoryInterface $lookupRepo,
    ) {
    }

    /**
     * Rejestracja nowego użytkownika.
     *
     * @return array{success: bool, message: string, login?: string, field?: string}
     */
    public function register(array $input): array
    {
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

        // Walidacja formatu
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

        // Walidacja danych w bazie
        if ($this->userRepo->isEmailTaken($email)) {
            return ['success' => false, 'message' => 'Podany adres e-mail jest już zarejestrowany.'];
        }
        if (!$this->lookupRepo->roleExists($roleId)) {
            return ['success' => false, 'message' => 'Nieprawidłowa rola.'];
        }
        if (!$this->lookupRepo->countryExists($countryId)) {
            return ['success' => false, 'message' => 'Nieprawidłowe obywatelstwo.'];
        }

        // Status oczekujący
        $pendingStatusId = $this->lookupRepo->getStatusIdByName('oczekujący');
        if ($pendingStatusId === null) {
            return ['success' => false, 'message' => 'Błąd konfiguracji systemu – brak statusu oczekujący.'];
        }

        // Generuj login
        $login = $this->generateLogin($firstName, $surname);
        $passwordHashed = password_hash($password, PASSWORD_BCRYPT);

        $this->userRepo->insert([
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

        return ['success' => true, 'message' => 'Konto zostało pomyślnie utworzone.', 'login' => $login];
    }

    /**
     * Generuje unikalny login: inicjały + 6 losowych cyfr.
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
