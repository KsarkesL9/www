<?php

declare(strict_types=1);

namespace App\Tests\Stub;

use App\Repository\LookupRepositoryInterface;

/**
 * Stub repozytorium lookup do testów.
 */
class InMemoryLookupStub implements LookupRepositoryInterface
{
    public function getStatusIdByName(string $name): ?int
    {
        return match ($name) {
            'aktywny' => 1,
            'zablokowany' => 2,
            'oczekujący' => 3,
            default => null,
        };
    }

    public function getRoleIdByName(string $roleName): ?int
    {
        return match ($roleName) {
            'student' => 1,
            'teacher' => 2,
            default => null,
        };
    }

    public function roleExists(int $roleId): bool
    {
        return in_array($roleId, [1, 2, 3]);
    }

    public function countryExists(int $countryId): bool
    {
        return $countryId >= 1 && $countryId <= 200;
    }

    public function getAllCountries(): array
    {
        return [
            ['country_id' => 1, 'name' => 'Polska'],
            ['country_id' => 2, 'name' => 'Niemcy'],
        ];
    }
}
