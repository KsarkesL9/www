<?php
/**
 * Atomowe walidatory danych wejściowych.
 */

/**
 * Sprawdza czy podany adres e-mail ma poprawny format
 */
function validateEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Sprawdza czy hasło spełnia wymagania minimalne (min 8 znaków)
 */
function validatePassword(string $password): bool
{
    return strlen($password) >= 8;
}

/**
 * Sprawdza czy oba hasła są identyczne
 */
function validatePasswordMatch(string $password, string $confirm): bool
{
    return $password === $confirm;
}

/**
 * Sprawdza format daty (YYYY-MM-DD)
 */
function validateDateFormat(string $date): bool
{
    return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
}

/**
 * Sprawdza wymagane pola w tablicy inputu — zwraca nazwę pierwszego pustego pola lub null
 */
function validateRequiredFields(array $input, array $requiredFields): ?string
{
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            return $field;
        }
    }
    return null;
}
