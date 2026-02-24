<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

/**
 * @file PdoLookupRepository.php
 * @brief PDO-based implementation of LookupRepositoryInterface.
 *
 * @details Reads data from reference tables: statuses, roles, and countries.
 *          Used by services to validate IDs and to look up status and role IDs
 *          by their name. All methods are read-only queries.
 *          No business logic is included here.
 */
class PdoLookupRepository implements LookupRepositoryInterface
{
    /**
     * @brief Creates a new PdoLookupRepository with a PDO connection.
     *
     * @param PDO $pdo An active PDO database connection.
     */
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @brief Returns the ID of a status record by its name.
     *
     * @details Queries the statuses table for a row where name equals the
     *          given string. Returns the integer status_id if found, or null
     *          if no matching row exists.
     *
     * @param string $name The status name to look up (e.g. 'aktywny', 'oczekujący').
     *
     * @return int|null The status ID, or null if the name is not found.
     */
    public function getStatusIdByName(string $name): ?int
    {
        $stmt = $this->pdo->prepare('SELECT status_id FROM statuses WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);
        $row = $stmt->fetch();
        return $row ? (int) $row['status_id'] : null;
    }

    /**
     * @brief Returns the ID of a role record by its name.
     *
     * @details Queries the roles table for a row where role_name equals the
     *          given string. Returns the integer role_id if found, or null
     *          if no matching row exists.
     *
     * @param string $roleName The role name to look up (e.g. 'uczen', 'nauczyciel').
     *
     * @return int|null The role ID, or null if the name is not found.
     */
    public function getRoleIdByName(string $roleName): ?int
    {
        $stmt = $this->pdo->prepare('SELECT role_id FROM roles WHERE role_name = ? LIMIT 1');
        $stmt->execute([$roleName]);
        $row = $stmt->fetch();
        return $row ? (int) $row['role_id'] : null;
    }

    /**
     * @brief Checks if a role with the given ID exists in the database.
     *
     * @details Queries the roles table for a row with the given role_id.
     *          Returns true if any row is found, false otherwise.
     *
     * @param int $roleId The role ID to check.
     *
     * @return bool True if the role exists, false otherwise.
     */
    public function roleExists(int $roleId): bool
    {
        $stmt = $this->pdo->prepare('SELECT role_id FROM roles WHERE role_id = ?');
        $stmt->execute([$roleId]);
        return (bool) $stmt->fetch();
    }

    /**
     * @brief Checks if a country with the given ID exists in the database.
     *
     * @details Queries the countries table for a row with the given country_id.
     *          Returns true if any row is found, false otherwise.
     *
     * @param int $countryId The country ID to check.
     *
     * @return bool True if the country exists, false otherwise.
     */
    public function countryExists(int $countryId): bool
    {
        $stmt = $this->pdo->prepare('SELECT country_id FROM countries WHERE country_id = ?');
        $stmt->execute([$countryId]);
        return (bool) $stmt->fetch();
    }

    /**
     * @brief Returns all countries ordered alphabetically by name.
     *
     * @details Runs a SELECT query on the countries table and returns
     *          all rows ordered by name ASC. Each row contains country_id
     *          and name. Used to populate the country drop-down in forms.
     *
     * @return array A list of country rows, each with keys: country_id, name.
     */
    public function getAllCountries(): array
    {
        $stmt = $this->pdo->query('SELECT country_id, name FROM countries ORDER BY name ASC');
        return $stmt->fetchAll();
    }
}
