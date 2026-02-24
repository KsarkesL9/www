<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Encja użytkownika — reprezentuje wiersz z tabeli `users`.
 */
class User
{
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

    // ─── Metody biznesowe ───────────────────────────────

    /** Sprawdza czy konto jest aktywne */
    public function isActive(): bool
    {
        return $this->statusName === 'aktywny';
    }

    /** Sprawdza czy konto jest zablokowane */
    public function isBlocked(): bool
    {
        return $this->statusName === 'zablokowany';
    }

    /** Sprawdza czy konto oczekuje na aktywację */
    public function isPending(): bool
    {
        return $this->statusName === 'oczekujący';
    }

    /** Sprawdza czy konto jest zablokowane na podstawie status_id */
    public function hasStatusId(int $statusId): bool
    {
        return $this->statusId === $statusId;
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
