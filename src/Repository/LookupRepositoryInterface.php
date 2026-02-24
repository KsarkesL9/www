<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Interfejs repozytorium lookup (statusy, role, kraje).
 */
interface LookupRepositoryInterface
{
    /** Zwraca status_id dla podanej nazwy statusu */
    public function getStatusIdByName(string $name): ?int;

    /** Zwraca role_id dla podanej nazwy roli */
    public function getRoleIdByName(string $roleName): ?int;

    /** Sprawdza czy rola o podanym ID istnieje */
    public function roleExists(int $roleId): bool;

    /** Sprawdza czy kraj o podanym ID istnieje */
    public function countryExists(int $countryId): bool;

    /** Zwraca listę krajów posortowanych alfabetycznie */
    public function getAllCountries(): array;
}
