<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\User;

/**
 * @brief In-memory implementation of the user repository.
 * 
 * Used primarily for testing purposes. It stores user data in memory, simulating a database connection.
 */
class InMemoryUserRepository implements UserRepositoryInterface
{
    /** @var User[] */
    private array $users = [];
    private int $nextId = 1;

    /**
     * @brief Finds a user by their login.
     * 
     * Iterates over the in-memory array to find a user matching the login.
     * 
     * @param string $login The login name.
     * @return User|null The user object if found, or null otherwise.
     */
    public function findByLogin(string $login): ?User
    {
        foreach ($this->users as $user) {
            if ($user->getLogin() === $login) {
                return $user;
            }
        }
        return null;
    }

    /**
     * @brief Finds a user by their login and email address.
     * 
     * Searches the local list for an account having the exact same login and email.
     * 
     * @param string $login The login name.
     * @param string $email The email address.
     * @return User|null The matching user object, or null.
     */
    public function findByLoginAndEmail(string $login, string $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->getLogin() === $login && $user->getEmailAddress() === $email) {
                return $user;
            }
        }
        return null;
    }

    /**
     * @brief Checks if a specific email exists.
     * 
     * Loops through the users to see if any of them own the provided email.
     * 
     * @param string $email The email address.
     * @return bool True if a matching email is found, false otherwise.
     */
    public function isEmailTaken(string $email): bool
    {
        foreach ($this->users as $user) {
            if ($user->getEmailAddress() === $email) {
                return true;
            }
        }
        return false;
    }

    /**
     * @brief Creates a new user entry.
     * 
     * Adds the generated user data into the memory array with a new incremental ID.
     * 
     * @param array $data Assorted user properties.
     * @return int The ID assigned to the new user.
     */
    public function insert(array $data): int
    {
        $id = $this->nextId++;
        $data['user_id'] = $id;
        $data['failed_login_attempts'] = 0;
        $this->users[$id] = User::fromRow($data);
        return $id;
    }

    /**
     * @brief Verifies if a user login is occupied.
     * 
     * Shorthand to check if a specific login already exists via findByLogin().
     * 
     * @param string $login The login to query.
     * @return bool True if occupied, false otherwise.
     */
    public function loginExists(string $login): bool
    {
        return $this->findByLogin($login) !== null;
    }

    /**
     * @brief Increases the failed login attempts.
     * 
     * Adds 1 to the failed attempts tracking field of a user.
     * 
     * @param int $userId The ID of the targeted user.
     * @return int The overall failed attempt total.
     */
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

    /**
     * @brief Restores the failed login count.
     * 
     * Flushes the failed login counter for a specific user to 0, usually after successful authentication.
     * 
     * @param int $userId The ID of the specific user.
     */
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

    /**
     * @brief Alters an account's overall status.
     * 
     * Replaces the current status ID mapped to the user data structure with a new one.
     * 
     * @param int $userId The ID to alter.
     * @param int $statusId The numerical ID matching an application status.
     */
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

    /**
     * @brief Mutates a user's login password.
     * 
     * Attaches a new hashed password to the user array and resets the failed login metric.
     * 
     * @param int $userId The ID to mutate.
     * @param string $hashedPassword The hash output.
     */
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

    /**
     * @brief Looks for active users.
     * 
     * Scans through the user arrays searching for an 'aktywny' status and keyword matching parts of their names or logins.
     * 
     * @param string $query Text query to perform the evaluation against.
     * @param int $limit Max size of resulting user dataset.
     * @return array The queried list of data arrays matching the parameters.
     */
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

    /**
     * @brief Retains only active IDs.
     * 
     * Discards any ID given from the argument if the corresponding mapping holds an inactive status name.
     * 
     * @param array $userIds Valid and potential identifiers.
     * @return array Subset containing only active people.
     */
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

    /**
     * @brief Converts User to row mapping.
     * 
     * Extracts values internally from a User entity backwards to its array schema layout.
     * 
     * @param User $user The entity object.
     * @return array Formatted hash map with primitive values.
     */
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
