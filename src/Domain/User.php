<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Encja użytkownika — reprezentuje wiersz z tabeli `users`.
 */
class User
{
    public function __construct(
        private int $userId,
        private int $roleId,
        private int $statusId,
        private string $firstName,
        private ?string $secondName,
        private string $surname,
        private string $emailAddress,
        private string $login,
        private string $password,
        private string $dateOfBirth,
        private int $countryId,
        private string $city,
        private string $street,
        private string $buildingNumber,
        private ?string $apartmentNumber,
        private ?string $phoneNumber,
        private int $failedLoginAttempts = 0,
        private ?string $statusName = null,
        private ?string $roleName = null,
    ) {
    }

    // ─── Gettery ────────────────────────────────────────

    public function getUserId(): int
    {
        return $this->userId;
    }
    public function getRoleId(): int
    {
        return $this->roleId;
    }
    public function getStatusId(): int
    {
        return $this->statusId;
    }
    public function getFirstName(): string
    {
        return $this->firstName;
    }
    public function getSecondName(): ?string
    {
        return $this->secondName;
    }
    public function getSurname(): string
    {
        return $this->surname;
    }
    public function getEmailAddress(): string
    {
        return $this->emailAddress;
    }
    public function getLogin(): string
    {
        return $this->login;
    }
    public function getPassword(): string
    {
        return $this->password;
    }
    public function getDateOfBirth(): string
    {
        return $this->dateOfBirth;
    }
    public function getCountryId(): int
    {
        return $this->countryId;
    }
    public function getCity(): string
    {
        return $this->city;
    }
    public function getStreet(): string
    {
        return $this->street;
    }
    public function getBuildingNumber(): string
    {
        return $this->buildingNumber;
    }
    public function getApartmentNumber(): ?string
    {
        return $this->apartmentNumber;
    }
    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }
    public function getFailedLoginAttempts(): int
    {
        return $this->failedLoginAttempts;
    }
    public function getStatusName(): ?string
    {
        return $this->statusName;
    }
    public function getRoleName(): ?string
    {
        return $this->roleName;
    }

    // ─── Fabryka z tablicy (wiersz PDO) ─────────────────

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
