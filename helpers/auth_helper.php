<?php

function generateSalt(int $byteLength = 16): string
{
    return bin2hex(random_bytes($byteLength));
}

function hashPasswordWithSalt(string $password, string $salt): string
{
    return hash('sha256', $salt . $password);
}

function hashPassword(string $password): array
{
    $salt = generateSalt();

    return [
        'hash' => hashPasswordWithSalt($password, $salt),
        'salt' => $salt
    ];
}

function verifyPassword(string $password, string $salt, string $storedHash): bool
{
    return hashPasswordWithSalt($password, $salt) === $storedHash;
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function isAdmin(): bool
{
    return isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'admin';
}

function getCurrentUserId(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function getCurrentUserName(): string
{
    return $_SESSION['full_name'] ?? 'User';
}