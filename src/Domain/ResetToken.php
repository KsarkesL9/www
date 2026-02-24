<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * @brief Domain entity that represents a password reset token.
 *
 * @details This class holds all the data for one row from the
 *          'password_reset_tokens' database table. A reset token
 *          is a random string that is sent to the user so they can
 *          prove their identity and change their password. Each token
 *          has an expiry time and a flag that shows whether it was used.
 */
class ResetToken
{
    /**
     * @brief Creates a new ResetToken object.
     *
     * @param int         $tokenId   The unique ID of this token record.
     * @param int         $userId    The ID of the user who requested the reset.
     * @param string      $token     The random hex string used to identify the reset request.
     * @param string      $expiresAt The date and time when this token stops working.
     * @param string|null $usedAt    The date the token was used, or null if not yet used.
     */
    public function __construct(
        private int $tokenId,
        private int $userId,
        private string $token,
        private string $expiresAt,
        private ?string $usedAt = null,
    ) {
    }

    /**
     * @brief Returns the unique ID of this token record.
     *
     * @return int The token record ID from the database.
     */
    public function getTokenId(): int
    {
        return $this->tokenId;
    }

    /**
     * @brief Returns the ID of the user who requested the password reset.
     *
     * @return int The user ID linked to this token.
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * @brief Returns the random hex string that is the actual reset token.
     *
     * @return string The token string value.
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @brief Returns the date and time when this token stops being valid.
     *
     * @return string The expiry timestamp as a MySQL datetime string.
     */
    public function getExpiresAt(): string
    {
        return $this->expiresAt;
    }

    /**
     * @brief Returns the date and time when this token was used.
     *
     * @details Returns null if the token has not been used yet.
     *          A non-null value means the token was already consumed
     *          and cannot be used again to reset the password.
     *
     * @return string|null The usage timestamp, or null if not yet used.
     */
    public function getUsedAt(): ?string
    {
        return $this->usedAt;
    }

    /**
     * @brief Creates a ResetToken object from a database row array.
     *
     * @details This static factory method takes an associative array
     *          as returned by PDO and maps each key to the correct
     *          constructor parameter. Integer fields are cast to int.
     *          Missing optional fields fall back to empty string or null.
     *
     * @param array $row An associative array from a PDO fetch call.
     *                   Expected keys: token_id, user_id, token,
     *                   expires_at, used_at (optional).
     *
     * @return self A new ResetToken instance filled with data from the row.
     */
    public static function fromRow(array $row): self
    {
        return new self(
            tokenId: (int) $row['token_id'],
            userId: (int) $row['user_id'],
            token: $row['token'] ?? '',
            expiresAt: $row['expires_at'] ?? '',
            usedAt: $row['used_at'] ?? null,
        );
    }
}
