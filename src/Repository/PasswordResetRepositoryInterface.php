<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\ResetToken;

/**
 * Interfejs repozytorium tokenów resetowania hasła.
 */
interface PasswordResetRepositoryInterface
{
    /** Unieważnia wszystkie aktywne tokeny użytkownika */
    public function revokeAllForUser(int $userId): void;

    /** Tworzy nowy token — zwraca token string */
    public function create(int $userId, string $token): void;

    /** Wyszukuje ważny, nieużyty token */
    public function findValid(string $token): ?ResetToken;

    /** Oznacza token jako użyty */
    public function markUsed(int $tokenId): void;
}
