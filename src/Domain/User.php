<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * @brief Domain entity that represents a user account.
 *
 * @details This class holds all the data for one row from the
 *          'users' database table, optionally joined with the
 *          user's status name and role name. All properties are
 *          read-only after construction. The class provides getter
 *          methods, business logic methods to check account status,
 *          and a static factory method to build from a PDO row array.
 */
class User
{
    /**
     * @brief Creates a new User object.
     *
     * @param int         $userId               The unique ID of the user.
     * @param int         $roleId               The numeric ID of the user's role.
     * @param int         $statusId             The numeric ID of the user's account status.
     * @param string      $firstName            The user's first name.
     * @param string|null $secondName           The user's second name, or null if not given.
     * @param string      $surname              The user's surname.
     * @param string      $emailAddress         The user's email address.
     * @param string      $login                The user's login name.
     * @param string      $password             The hashed password string.
     * @param string      $dateOfBirth          The date of birth in YYYY-MM-DD format.
     * @param int         $countryId            The ID of the user's country.
     * @param string      $city                 The city where the user lives.
     * @param string      $street               The street name of the user's address.
     * @param string      $buildingNumber       The building number of the user's address.
     * @param string|null $apartmentNumber      The apartment number, or null if not given.
     * @param string|null $phoneNumber          The phone number, or null if not given.
     * @param int         $failedLoginAttempts  The number of failed login attempts so far.
     * @param string|null $statusName           The name of the account status (e.g. 'aktywny').
     * @param string|null $roleName             The name of the role (e.g. 'student').
     */
    public function __construct(
        private readonly int $userId,
        private readonly int $roleId,
        private readonly int $statusId,
        private readonly string $firstName,
        private readonly ?string $secondName,
        private readonly string $surname,
        private readonly string $emailAddress,
        private readonly string $login,
        private readonly string $password,
        private readonly string $dateOfBirth,
        private readonly int $countryId,
        private readonly string $city,
        private readonly string $street,
        private readonly string $buildingNumber,
        private readonly ?string $apartmentNumber,
        private readonly ?string $phoneNumber,
        private readonly int $failedLoginAttempts = 0,
        private readonly ?string $statusName = null,
        private readonly ?string $roleName = null,
    ) {
    }

    // ─── Getters ────────────────────────────────────────

    /**
     * @brief Returns the unique ID of this user.
     * @return int The user ID from the database.
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * @brief Returns the numeric role ID of this user.
     * @return int The role ID.
     */
    public function getRoleId(): int
    {
        return $this->roleId;
    }

    /**
     * @brief Returns the numeric status ID of this user's account.
     * @return int The status ID.
     */
    public function getStatusId(): int
    {
        return $this->statusId;
    }

    /**
     * @brief Returns the first name of this user.
     * @return string The first name.
     */
    public function getFirstName(): string
    {
        return $this->firstName;
    }

    /**
     * @brief Returns the second name of this user, or null if not set.
     * @return string|null The second name, or null.
     */
    public function getSecondName(): ?string
    {
        return $this->secondName;
    }

    /**
     * @brief Returns the surname of this user.
     * @return string The surname.
     */
    public function getSurname(): string
    {
        return $this->surname;
    }

    /**
     * @brief Returns the email address of this user.
     * @return string The email address string.
     */
    public function getEmailAddress(): string
    {
        return $this->emailAddress;
    }

    /**
     * @brief Returns the login name of this user.
     * @return string The login string.
     */
    public function getLogin(): string
    {
        return $this->login;
    }

    /**
     * @brief Returns the hashed password string of this user.
     * @return string The bcrypt password hash.
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @brief Returns the date of birth of this user in YYYY-MM-DD format.
     * @return string The date of birth string.
     */
    public function getDateOfBirth(): string
    {
        return $this->dateOfBirth;
    }

    /**
     * @brief Returns the ID of the country where this user lives.
     * @return int The country ID from the 'countries' table.
     */
    public function getCountryId(): int
    {
        return $this->countryId;
    }

    /**
     * @brief Returns the city where this user lives.
     * @return string The city name.
     */
    public function getCity(): string
    {
        return $this->city;
    }

    /**
     * @brief Returns the street name from this user's address.
     * @return string The street name.
     */
    public function getStreet(): string
    {
        return $this->street;
    }

    /**
     * @brief Returns the building number from this user's address.
     * @return string The building number as a string.
     */
    public function getBuildingNumber(): string
    {
        return $this->buildingNumber;
    }

    /**
     * @brief Returns the apartment number from this user's address, or null.
     * @return string|null The apartment number, or null if not provided.
     */
    public function getApartmentNumber(): ?string
    {
        return $this->apartmentNumber;
    }

    /**
     * @brief Returns the phone number of this user, or null if not given.
     * @return string|null The phone number string, or null.
     */
    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    /**
     * @brief Returns the number of failed login attempts for this user.
     * @return int The count of failed attempts. Resets to 0 on successful login.
     */
    public function getFailedLoginAttempts(): int
    {
        return $this->failedLoginAttempts;
    }

    /**
     * @brief Returns the name of this user's account status, or null.
     * @return string|null The status name (e.g. 'aktywny', 'zablokowany'), or null.
     */
    public function getStatusName(): ?string
    {
        return $this->statusName;
    }

    /**
     * @brief Returns the name of this user's role, or null.
     * @return string|null The role name (e.g. 'student', 'teacher'), or null.
     */
    public function getRoleName(): ?string
    {
        return $this->roleName;
    }

    // ─── Business methods ───────────────────────────────

    /**
     * @brief Checks if this user's account is active.
     *
     * @details Compares the statusName field to the string 'aktywny'.
     *          Returns true only if they are equal.
     *
     * @return bool True if the account is active, false otherwise.
     */
    public function isActive(): bool
    {
        return $this->statusName === 'aktywny';
    }

    /**
     * @brief Checks if this user's account is blocked.
     *
     * @details Compares the statusName field to the string 'zablokowany'.
     *          An account can be blocked automatically after too many
     *          failed login attempts.
     *
     * @return bool True if the account is blocked, false otherwise.
     */
    public function isBlocked(): bool
    {
        return $this->statusName === 'zablokowany';
    }

    /**
     * @brief Checks if this user's account is waiting for activation.
     *
     * @details Compares the statusName field to the string 'oczekujący'.
     *          New accounts start with this status until an administrator
     *          activates them.
     *
     * @return bool True if the account is pending activation, false otherwise.
     */
    public function isPending(): bool
    {
        return $this->statusName === 'oczekujący';
    }

    /**
     * @brief Checks if this user's status ID matches the given value.
     *
     * @details Compares the numeric statusId field to the given integer.
     *          This method is used when you have a status ID but not the
     *          status name string.
     *
     * @param int $statusId The status ID to compare against.
     *
     * @return bool True if the user's status ID equals the given value.
     */
    public function hasStatusId(int $statusId): bool
    {
        return $this->statusId === $statusId;
    }

    // ─── Factory from array (PDO row) ───────────────────

    /**
     * @brief Creates a User object from a database row array.
     *
     * @details This static factory method takes an associative array
     *          as returned by PDO and maps each key to the correct
     *          constructor parameter. Integer fields are cast to int.
     *          Optional fields default to null if not present in the row.
     *
     * @param array $row An associative array from a PDO fetch call.
     *                   Expected keys: user_id, role_id, status_id,
     *                   first_name, second_name, surname, email_address,
     *                   login, password, date_of_birth, country_id,
     *                   city, street, building_number, apartment_number,
     *                   phone_number, failed_login_attempts, status_name,
     *                   role_name.
     *
     * @return self A new User instance filled with data from the row.
     */
    public static function fromRow(array $row): self
    {
        return new self(
            userId: (int) $row['user_id'],
            roleId: (int) $row['role_id'],
            statusId: (int) $row['status_id'],
            firstName: $row['first_name'],
            secondName: $row['second_name'] ?? null,
            surname: $row['surname'],
            emailAddress: $row['email_address'],
            login: $row['login'],
            password: $row['password'],
            dateOfBirth: $row['date_of_birth'],
            countryId: (int) $row['country_id'],
            city: $row['city'],
            street: $row['street'],
            buildingNumber: $row['building_number'],
            apartmentNumber: $row['apartment_number'] ?? null,
            phoneNumber: $row['phone_number'] ?? null,
            failedLoginAttempts: (int) ($row['failed_login_attempts'] ?? 0),
            statusName: $row['status_name'] ?? null,
            roleName: $row['role_name'] ?? null,
        );
    }
}
