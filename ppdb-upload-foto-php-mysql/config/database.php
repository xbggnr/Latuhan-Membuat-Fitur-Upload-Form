<?php
declare(strict_types=1);

/*
 * Sesuaikan kredensial database dengan konfigurasi MySQL lokal Anda.
 * XAMPP umumnya: host=127.0.0.1, user=root, password kosong.
 */
const DB_HOST = 'localhost';
const DB_NAME = 'db_ppdb_smk';
const DB_USER = 'root';
const DB_PASS = '';
const DB_PORT = '3307';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}
