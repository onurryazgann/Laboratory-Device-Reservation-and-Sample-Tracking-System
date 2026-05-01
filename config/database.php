<?php

require_once __DIR__ . '/config.php';

/*
|--------------------------------------------------------------------------
| Database Configuration
|--------------------------------------------------------------------------
| Localde XAMPP veritabanını kullanır.
| InfinityFree'de canlı veritabanını kullanır.
*/

if (IS_LOCAL) {
    $host = 'localhost';
    $dbname = 'lab_reservation_early';
    $username = 'root';
    $password = '';
} else {
    $host = 'sql107.infinityfree.com';
    $dbname = 'if0_41797269_lab_reservation_early';
    $username = 'if0_41797269';
    $password = 'yazgaN06onuR';
}

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        die('Database connection failed: ' . $e->getMessage());
    }

    die('Database connection failed.');
}