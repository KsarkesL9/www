<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\User;

/**
 * Implementacja in-memory repozytorium użytkowników — do testów.
 * Przechowuje dane w pamięci, bez bazy danych.
 */
class InMemoryUserRepository implements UserRepositoryInterface
{
    /** @var User[] */
    private array $users = [];
    private int $nextId = 1;

    public function findByLogin(string $login): ?User
    {
        foreach ($this->users as $user) {
            if ($user->getLogin() === $login) {
                return $user;
            }
        }
        return null;
    }

    public function findByLoginAndEmail(string $login, string $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->getLogin() === $login && $user->getEmailAddress() === $email) {
                return $user;
            }
        }
        return null;
    }

    public function isEmailTaken(string $email): bool
    {
        foreach ($this->users as $user) {
            if ($user->getEmailAddress() === $email) {
                return true;
            }
        }
        return false;
    }

    public function insert(array $data): int
    {
        $id = $this->nextId++;
        $data['user_id'] = $id;
        $data['failed_login_attempts'] = 0;
        $this->users[$id] = User::fromRow($data);
        return $id;
    }

    public function loginExists(string $login): bool
    {
        return $this->findByLogin($login) !== null;
    }

    public function incrementFailedLogins(int $userId): int
    {
        if (!isset($this->users[$userId])) {
            return 0;
        }
        $user = $this->users[$userId];
        $newAttempts = $user->getFailedLoginAttempts() + 1;

        // Recreate user with updated attempts
        $this->users[$userId] = User::fromRow(array_merge(
            $this->userToRow($user),
            ['failed_login_attempts' => $newAttempts]
        ));

        return $newAttempts;
    }

    public function resetFailedLogins(int $userId): void
    {
        if (!isset($this->users[$userId])) {
            return;
        }
        $user = $this->users[$userId];
        $this->users[$userId] = User::fromRow(array_merge(
            $this->userToRow($user),
            ['failed_login_attempts' => 0]
        ));
    }

    public function updateStatus(int $userId, int $statusId): void
    {
        if (!isset($this->users[$userId])) {
            return;
        }
        $user = $this->users[$userId];
        $this->users[$userId] = User::fromRow(array_merge(
            $this->userToRow($user),
            ['status_id' => $statusId]
        ));
    }

    public function updatePassword(int $userId, string $hashedPassword): void
    {
        if (!isset($this->users[$userId])) {
            return;
        }
        $user = $this->users[$userId];
        $this->users[$userId] = User::fromRow(array_merge(
            $this->userToRow($user),
            ['password' => $hashedPassword, 'failed_login_attempts' => 0]
        ));
    }

    public function searchActive(string $query, int $limit = 15): array
    {
        $results = [];
        $q = mb_strtolower($query);

        foreach ($this->users as $user) {
            if ($user->getStatusName() !== 'aktywny') {
                continue;
            }
            $fullName = $user->getFirstName() . ' ' . $user->getSurname();
            if (
                str_contains(mb_strtolower($user->getFirstName()), $q) ||
                str_contains(mb_strtolower($user->getSurname()), $q) ||
                str_contains(mb_strtolower($fullName), $q) ||
                str_contains(mb_strtolower($user->getLogin()), $q)
            ) {
                $results[] = [
                    'user_id' => $user->getUserId(),
                    'full_name' => $fullName,
                    'login' => $user->getLogin(),
                    'role_name' => $user->getRoleName(),
                ];
            }
            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    public function filterActiveUserIds(array $userIds): array
    {
        $result = [];
        foreach ($userIds as $id) {
            if (isset($this->users[$id]) && $this->users[$id]->getStatusName() === 'aktywny') {
                $result[] = $id;
            }
        }
        return $result;
    }

    // ─── Helper ─────────────────────────────────────────

    private function userToRow(User $user): array
    {
        return [
            'user_id' => $user->getUserId(),
            'role_id' => $user->getRoleId(),
            'status_id' => $user->getStatusId(),
            'first_name' => $user->getFirstName(),
            'second_name' => $user->getSecondName(),
            'surname' => $user->getSurname(),
            'email_address' => $user->getEmailAddress(),
            'login' => $user->getLogin(),
            'password' => $user->getPassword(),
            'date_of_birth' => $user->getDateOfBirth(),
            'country_id' => $user->getCountryId(),
            'city' => $user->getCity(),
            'street' => $user->getStreet(),
            'building_number' => $user->getBuildingNumber(),
            'apartment_number' => $user->getApartmentNumber(),
            'phone_number' => $user->getPhoneNumber(),
            'failed_login_attempts' => $user->getFailedLoginAttempts(),
            'status_name' => $user->getStatusName(),
            'role_name' => $user->getRoleName(),
        ];
    }
}
