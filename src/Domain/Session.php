<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Encja sesji użytkownika — reprezentuje wiersz z tabeli `user_sessions`
 * wzbogacony o dane użytkownika i roli.
 */
class Session
{
    public function __construct(
        private int $sessionId,
        private int $userId,
        private string $token,
        private string $ipAddress,
        private string $userAgent,
        private string $expiresAt,
        private ?string $revokedAt,
        private int $statusId,
        private int $roleId,
        private string $firstName,
        private string $surname,
        private string $roleName,
    ) {
    }

    public function getSessionId(): int
    {
        return $this->sessionId;
    }
    public function getUserId(): int
    {
        return $this->userId;
    }
    public function getToken(): string
    {
        return $this->token;
    }
    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }
    public function getUserAgent(): string
    {
        return $this->userAgent;
    }
    public function getExpiresAt(): string
    {
        return $this->expiresAt;
    }
    public function getRevokedAt(): ?string
    {
        return $this->revokedAt;
    }
    public function getStatusId(): int
    {
        return $this->statusId;
    }
    public function getRoleId(): int
    {
        return $this->roleId;
    }
    public function getFirstName(): string
    {
        return $this->firstName;
    }
    public function getSurname(): string
    {
        return $this->surname;
    }
    public function getRoleName(): string
    {
        return $this->roleName;
    }

    public static function fromRow(array $row): self
    {
        return new self(
            sessionId: (int) $row['session_id'],
            userId: (int) $row['user_id'],
            token: $row['token'],
            ipAddress: $row['ip_address'],
            userAgent: $row['user_agent'],
            expiresAt: $row['expires_at'],
            revokedAt: $row['revoked_at'] ?? null,
            statusId: (int) $row['status_id'],
            roleId: (int) $row['role_id'],
            firstName: $row['first_name'],
            surname: $row['surname'],
            roleName: $row['role_name'],
        );
    }

    /**
     * Konwertuje do tablicy (kompatybilność wsteczna z kodem stron).
     */
    public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId,
            'user_id' => $this->userId,
            'token' => $this->token,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'expires_at' => $this->expiresAt,
            'revoked_at' => $this->revokedAt,
            'status_id' => $this->statusId,
            'role_id' => $this->roleId,
            'first_name' => $this->firstName,
            'surname' => $this->surname,
            'role_name' => $this->roleName,
        ];
    }
}
