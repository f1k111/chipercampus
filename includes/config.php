<?php
/**
 * CipherCampus - Konfigurasi Aplikasi
 */

// --- Identitas Tugas (ubah sesuai kebutuhan) ---
define('APP_NAME',    'CipherCampus');
define('APP_AUTHOR',  'Nama Mahasiswa');
define('APP_NIM',     'XXXXXXXXXX');
define('APP_COURSE',  'Sistem Informasi');

// --- Database ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'ciphercampus');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHAR', 'utf8mb4');

// --- Aplikasi ---
define('APP_ENV',  'development'); // 'production' untuk matikan error display
define('BASE_URL', 'http://localhost/ciphercampus');
define('SESSION_IDLE_TIMEOUT', 15 * 60); // 15 menit

// --- Path OpenSSL (Laragon Windows) ---
// Dicoba otomatis; isi manual jika gagal.
define('OPENSSL_CNF_PATH', _detect_openssl_cnf());

function _detect_openssl_cnf(): string {
    // Cek environment variable dulu
    if (!empty($_SERVER['OPENSSL_CONF'])) return $_SERVER['OPENSSL_CONF'];

    // Cari di path umum Laragon
    $patterns = [
        'C:/laragon/bin/php/php-*/extras/ssl/openssl.cnf',
        'C:/laragon/etc/ssl/openssl.cnf',
        'C:/xampp/apache/conf/openssl.cnf',
    ];
    foreach ($patterns as $p) {
        $found = glob($p);
        if (!empty($found)) return str_replace('\\', '/', $found[0]);
    }
    return ''; // kosong = pakai default PHP (mungkin gagal di Windows)
}

// --- Error display ---
if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    error_reporting(0);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}
