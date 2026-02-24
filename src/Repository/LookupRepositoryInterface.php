<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * @brief Interface for lookup repository.
 * 
 * Provides methods to retrieve lookup data such as statuses, roles, and countries.
 */
interface LookupRepositoryInterface
{
    /**
     * @brief Gets the status ID by its name.
     * 
     * This function retrieves the unique identifier for a specific status name.
     * 
     * @param string $name The name of the status.
     * @return int|null The status ID, or null if not found.
     */
    public function getStatusIdByName(string $name): ?int;

    /**
     * @brief Gets the role ID by its name.
     * 
     * This function retrieves the unique identifier for a specific user role name.
     * 
     * @param string $roleName The name of the role.
     * @return int|null The role ID, or null if not found.
     */
    public function getRoleIdByName(string $roleName): ?int;

    /**
     * @brief Checks if a role exists.
     * 
     * This function checks whether a specific role ID exists in the database.
     * 
     * @param int $roleId The ID of the role to check.
     * @return bool True if the role exists, false otherwise.
     */
    public function roleExists(int $roleId): bool;

    /**
     * @brief Checks if a country exists.
     * 
     * This function checks whether a specific country ID exists in the database.
     * 
     * @param int $countryId The ID of the country to check.
     * @return bool True if the country exists, false otherwise.
     */
    public function countryExists(int $countryId): bool;

    /**
     * @brief Gets all countries.
     * 
     * This function retrieves a list of all available countries, sorted alphabetically.
     * 
     * @return array An array containing the list of countries.
     */
    public function getAllCountries(): array;
}
