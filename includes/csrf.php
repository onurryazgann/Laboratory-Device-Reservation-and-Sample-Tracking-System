<?php

declare(strict_types=1);

/**
 * CSRF helper functions.
 * Currently optional, but available for forms that need token protection.
 */

function csrfEnsureSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function csrfToken(string $key = 'csrf_token'): string
{
    csrfEnsureSession();

    if (empty($_SESSION[$key])) {
        $_SESSION[$key] = bin2hex(random_bytes(32));
    }

    return $_SESSION[$key];
}

function csrfInput(string $key = 'csrf_token'): string
{
    $token = csrfToken($key);

    return '<input type="hidden" name="' .
        htmlspecialchars($key, ENT_QUOTES, 'UTF-8') .
        '" value="' .
        htmlspecialchars($token, ENT_QUOTES, 'UTF-8') .
        '">';
}

function csrfVerify(?string $token, string $key = 'csrf_token'): bool
{
    csrfEnsureSession();

    if ($token === null || empty($_SESSION[$key])) {
        return false;
    }

    return hash_equals((string) $_SESSION[$key], (string) $token);
}

function csrfClear(string $key = 'csrf_token'): void
{
    csrfEnsureSession();

    unset($_SESSION[$key]);
}