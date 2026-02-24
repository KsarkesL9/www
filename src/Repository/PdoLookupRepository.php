<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

/**
 * Implementacja repozytorium lookup oparta na PDO.
 */
class PdoLookupRepository implements LookupRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getStatusIdByName(string $name): ?int
    {
        $stmt = $this->pdo->prepare('SELECT status_id FROM statuses WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);
        $row = $stmt->fetch();
        return $row ? (int) $row['status_id'] : null;
    }

    public function getRoleIdByName(string $roleName): ?int
    {
        $stmt = $this->pdo->prepare('SELECT role_id FROM roles WHERE role_name = ? LIMIT 1');
        $stmt->execute([$roleName]);
        $row = $stmt->fetch();
        return $row ? (int) $row['role_id'] : null;
    }

    public function roleExists(int $roleId): bool
    {
        $stmt = $this->pdo->prepare('SELECT role_id FROM roles WHERE role_id = ?');
        $stmt->execute([$roleId]);
        return (bool) $stmt->fetch();
    }

    public function countryExists(int $countryId): bool
    {
        $stmt = $this->pdo->prepare('SELECT country_id FROM countries WHERE country_id = ?');
        $stmt->execute([$countryId]);
        return (bool) $stmt->fetch();
    }

    public function getAllCountries(): array
    {
        $stmt = $this->pdo->query('SELECT country_id, name FROM countries ORDER BY name ASC');
        return $stmt->fetchAll();
    }
}
