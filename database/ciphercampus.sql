-- =============================================================
-- CipherCampus - Database Schema
-- Import lewat phpMyAdmin: Databases > Import > pilih file ini
-- =============================================================

CREATE DATABASE IF NOT EXISTS `ciphercampus`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `ciphercampus`;

-- -----------------------------------------------------------
-- app_keys: Kunci publik RSA + verifikasi login
-- (satu baris saja; setup.php menolak jika sudah ada isi)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `app_keys` (
    `id`                   INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `rsa_public_key`       TEXT            NOT NULL COMMENT 'Kunci publik RSA-2048 (PEM)',
    `sym_check`            TEXT            NOT NULL COMMENT 'AES-GCM(K_sym, "CIPHERCAMPUS-SYM-OK") untuk verifikasi login',
    `rsa_challenge_ct`     TEXT            NOT NULL COMMENT 'RSA-OAEP(pub, nonce) untuk verifikasi kunci privat',
    `rsa_challenge_hash`   VARCHAR(64)     NOT NULL COMMENT 'SHA-256(nonce) sebagai referensi',
    `created_at`           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- mahasiswa: Semua field sensitif dienkripsi
-- nim_hash: HMAC blind index untuk cek duplikat NIM
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mahasiswa` (
    `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `nim_hash`     VARCHAR(64)     NOT NULL COMMENT 'HMAC-SHA256(NIM, derived_key) - blind index',
    `nim_enc`      TEXT            NOT NULL COMMENT 'AES-256-GCM(DEK, NIM)',
    `nama_enc`     TEXT            NOT NULL COMMENT 'AES-256-GCM(DEK, nama)',
    `prodi_enc`    TEXT            NOT NULL COMMENT 'AES-256-GCM(DEK, prodi)',
    `email_enc`    TEXT            NOT NULL COMMENT 'AES-256-GCM(DEK, email)',
    `dek_envelope` MEDIUMTEXT      NOT NULL COMMENT 'AES-GCM(K_sym, RSA-OAEP(pub, DEK))',
    `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_nim_hash` (`nim_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- nilai: Nilai mahasiswa, semua field sensitif dienkripsi
-- mahasiswa_id: FK plaintext (relasi struktural, bukan data pribadi)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `nilai` (
    `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `mahasiswa_id`    INT UNSIGNED    NOT NULL,
    `matkul_enc`      TEXT            NOT NULL COMMENT 'AES-256-GCM(DEK, nama_mata_kuliah)',
    `sks_enc`         TEXT            NOT NULL COMMENT 'AES-256-GCM(DEK, sks)',
    `nilai_angka_enc` TEXT            NOT NULL COMMENT 'AES-256-GCM(DEK, nilai_angka)',
    `nilai_huruf_enc` TEXT            NOT NULL COMMENT 'AES-256-GCM(DEK, nilai_huruf)',
    `dek_envelope`    MEDIUMTEXT      NOT NULL,
    `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_mahasiswa_id` (`mahasiswa_id`),
    CONSTRAINT `fk_nilai_mahasiswa`
        FOREIGN KEY (`mahasiswa_id`) REFERENCES `mahasiswa` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- login_log: Riwayat percobaan login untuk rate-limiting
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_log` (
    `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `ip`         VARCHAR(45)     NOT NULL COMMENT 'IPv4 atau IPv6',
    `status`     ENUM('success','failed') NOT NULL,
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ip_status_time` (`ip`, `status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
