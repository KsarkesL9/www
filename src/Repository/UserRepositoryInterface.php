<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\User;

/**
 * Interfejs repozytorium użytkowników.
 */
interface UserRepositoryInterface
{
    /** Wyszukuje użytkownika po loginie */
    public function findByLogin(string $login): ?User;

    /** Wyszukuje użytkownika po loginie i adresie e-mail */
    public function findByLoginAndEmail(string $login, string $email): ?User;

    /** Sprawdza czy adres e-mail jest już zajęty */
    public function isEmailTaken(string $email): bool;

    /** Wstawia nowego użytkownika — zwraca user_id */
    public function insert(array $data): int;

    /** Sprawdza unikatowość loginu */
    public function loginExists(string $login): bool;

    /** Zwiększa licznik nieudanych logowań — zwraca nową wartość */
    public function incrementFailedLogins(int $userId): int;

    /** Zeruje licznik nieudanych logowań */
    public function resetFailedLogins(int $userId): void;

    /** Ustawia status użytkownika */
    public function updateStatus(int $userId, int $statusId): void;

    /** Aktualizuje hasło i zeruje failed_login_attempts */
    public function updatePassword(int $userId, string $hashedPassword): void;

    /** Wyszukuje aktywnych użytkowników pasujących do zapytania (limit 15) */
    public function searchActive(string $query, int $limit = 15): array;

    /** Zwraca listę aktywnych user_id spośród podanych */
    public function filterActiveUserIds(array $userIds): array;
}
