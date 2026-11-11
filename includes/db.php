<?php
/**
 * CipherCampus - Koneksi Database (PDO Singleton)
 */

require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST, DB_NAME, DB_CHAR
    );
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // Jangan tampilkan password / detail di pesan publik
        error_log('DB Connection failed: ' . $e->getMessage());
        http_response_code(503);
        die('<h1>Layanan tidak tersedia</h1><p>Gagal terhubung ke database. Periksa konfigurasi.</p>');
    }
    return $pdo;
}
