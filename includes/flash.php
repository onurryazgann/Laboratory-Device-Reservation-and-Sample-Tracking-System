<?php

declare(strict_types=1);

/**
 * Flash message helper functions.
 */

function flashEnsureSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function setFlashMessage(string $type, string $message): void
{
    flashEnsureSession();

    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function getFlashMessage(): ?array
{
    flashEnsureSession();

    if (empty($_SESSION['flash_message'])) {
        return null;
    }

    $flash = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);

    return $flash;
}

function hasFlashMessage(): bool
{
    flashEnsureSession();

    return !empty($_SESSION['flash_message']);
}