<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Repository\InMemoryUserRepository;
use App\Service\RegistrationService;
use PHPUnit\Framework\TestCase;

/**
 * Testy jednostkowe serwisu rejestracji.
 * Używa InMemoryUserRepository — zero bazy danych.
 */
class RegistrationServiceTest extends TestCase
{
    private RegistrationService $service;
    private InMemoryUserRepository $userRepo;

    protected function setUp(): void
    {
        $this->userRepo = new InMemoryUserRepository();
        $lookupRepo = new InMemoryLookupStub();
        $this->service = new RegistrationService($this->userRepo, $lookupRepo);
    }

    public function testSuccessfulRegistration(): void
    {
        $result = $this->service->register($this->validInput());

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('login', $result);
        $this->assertNotEmpty($result['login']);
    }

    public function testMissingRequiredField(): void
    {
        $input = $this->validInput();
        unset($input['email_address']);

        $result = $this->service->register($input);

        $this->assertFalse($result['success']);
        $this->assertEquals('email_address', $result['field']);
    }

    public function testInvalidEmail(): void
    {
        $input = $this->validInput();
        $input['email_address'] = 'not-an-email';

        $result = $this->service->register($input);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('e-mail', $result['message']);
    }

    public function testPasswordTooShort(): void
    {
        $input = $this->validInput();
        $input['password'] = 'short';
        $input['password_confirm'] = 'short';

        $result = $this->service->register($input);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('8 znaków', $result['message']);
    }

    public function testPasswordMismatch(): void
    {
        $input = $this->validInput();
        $input['password_confirm'] = 'different_password';

        $result = $this->service->register($input);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('identyczne', $result['message']);
    }

    public function testDuplicateEmail(): void
    {
        // Register first user
        $this->service->register($this->validInput());

        // Try to register with same email
        $result = $this->service->register($this->validInput());

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('zarejestrowany', $result['message']);
    }

    // ─── Helpers ────────────────────────────────────────

    private function validInput(): array
    {
        return [
            'role_id' => 1,
            'first_name' => 'Jan',
            'surname' => 'Kowalski',
            'email_address' => 'jan@example.com',
            'password' => 'bezpieczne123',
            'password_confirm' => 'bezpieczne123',
            'date_of_birth' => '1990-01-15',
            'country_id' => 1,
            'city' => 'Warszawa',
            'street' => 'Marszałkowska',
            'building_number' => '10',
        ];
    }
}

/**
 * Stub repozytorium lookup do testów.
 */
class InMemoryLookupStub implements \App\Repository\LookupRepositoryInterface
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
