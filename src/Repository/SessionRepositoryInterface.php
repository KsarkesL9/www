<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Session;

/**
 * Interfejs repozytorium sesji.
 */
interface SessionRepositoryInterface
{
    /** Tworzy nową sesję — zwraca token */
    public function create(int $userId, string $token, string $ip, string $userAgent): void;

    /** Odświeża sesję (przesuwa expires_at) */
    public function refresh(string $token): void;

    /** Unieważnia sesję */
    public function revoke(string $token): void;

    /** Unieważnia wszystkie sesje danego użytkownika */
    public function revokeAllForUser(int $userId): void;

    /** Zwraca aktywną sesję po tokenie (z danymi użytkownika) */
    public function findActiveByToken(string $token): ?Session;
}
