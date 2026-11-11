<?php
/**
 * CipherCampus - Layanan Kriptografi (CryptoService)
 *
 * Skema Hybrid: AES-256-GCM (enkripsi data) + RSA-2048 OAEP (enkripsi DEK)
 *
 * Catatan teknis:
 *   - openssl_private_decrypt() bawaan PHP memakai RSA-OAEP dengan SHA-1.
 *     Ini bukan kelemahan fatal untuk tujuan edukasi, namun SHA-256 lebih dianjurkan
 *     di implementasi produksi (butuh OpenSSL ≥ 1.0.2 + PHP patch khusus atau ekstensi).
 *   - GCM tag 16 byte menjamin integritas: data yang dimodifikasi manual di DB
 *     akan gagal didekripsi dengan pesan jelas.
 */

require_once __DIR__ . '/config.php';

class CryptoService
{
    // --- Enkripsi simetris field tunggal (AES-256-GCM) ---

    /**
     * Enkripsi satu field teks.
     * Return format: "base64(iv):base64(tag):base64(ciphertext)"
     */
    public static function encryptField(string $plaintext, string $dek): string
    {
        // IV 12 byte acak per field (wajib acak, jangan pernah statis)
        $iv  = random_bytes(12);
        $tag = '';

        $ct = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $dek,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,   // tag diisi otomatis oleh OpenSSL
            '',     // additional data (tidak dipakai di sini)
            16      // panjang tag: 16 byte
        );

        if ($ct === false) {
            throw new RuntimeException('Enkripsi field gagal: ' . openssl_error_string());
        }

        return base64_encode($iv) . ':' . base64_encode($tag) . ':' . base64_encode($ct);
    }

    /**
     * Dekripsi satu field.
     * Lempar Exception jika tag tidak cocok (data rusak/dimodifikasi).
     */
    public static function decryptField(string $encoded, string $dek): string
    {
        $parts = explode(':', $encoded, 3);
        if (count($parts) !== 3) {
            throw new RuntimeException('Format ciphertext tidak valid.');
        }

        $iv  = base64_decode($parts[0], true);
        $tag = base64_decode($parts[1], true);
        $ct  = base64_decode($parts[2], true);

        if ($iv === false || $tag === false || $ct === false) {
            throw new RuntimeException('Base64 ciphertext tidak valid.');
        }

        $plain = openssl_decrypt(
            $ct,
            'aes-256-gcm',
            $dek,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plain === false) {
            // Tag GCM gagal = data rusak atau kunci salah
            throw new RuntimeException('Data rusak / telah dimodifikasi (tag GCM tidak cocok).');
        }

        return $plain;
    }

    // --- Pembungkus DEK (Envelope Encryption) ---

    /**
     * Bungkus DEK dua lapis:
     *   Lapis 1: RSA-OAEP(kunci publik, DEK)
     *   Lapis 2: AES-256-GCM(K_sym, RSA_output)
     *
     * Return: base64 dari envelope lengkap
     */
    public static function wrapDek(string $dek, string $rsaPublicKeyPem, string $kSym): string
    {
        // Lapis 1: RSA-OAEP enkripsi DEK
        $rsaEncrypted = '';
        $ok = openssl_public_encrypt($dek, $rsaEncrypted, $rsaPublicKeyPem, OPENSSL_PKCS1_OAEP_PADDING);
        if (!$ok) {
            throw new RuntimeException('RSA enkripsi DEK gagal: ' . openssl_error_string());
        }

        // Lapis 2: AES-256-GCM enkripsi hasil RSA dengan K_sym
        $iv  = random_bytes(12);
        $tag = '';
        $ct  = openssl_encrypt(
            $rsaEncrypted,
            'aes-256-gcm',
            $kSym,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );
        if ($ct === false) {
            throw new RuntimeException('AES enkripsi envelope gagal: ' . openssl_error_string());
        }

        // Simpan sebagai JSON terstruktur (mudah dibaca di inspector)
        $envelope = [
            'iv'  => base64_encode($iv),
            'tag' => base64_encode($tag),
            'ct'  => base64_encode($ct),
        ];
        return base64_encode(json_encode($envelope));
    }

    /**
     * Buka envelope DEK:
     *   Buka AES-256-GCM dengan K_sym → dapat RSA output
     *   Buka RSA-OAEP dengan kunci privat → dapat DEK
     */
    public static function unwrapDek(string $envelopeB64, string $rsaPrivKeyPem, string $kSym): string
    {
        $envelopeJson = base64_decode($envelopeB64, true);
        if ($envelopeJson === false) {
            throw new RuntimeException('Envelope base64 tidak valid.');
        }

        $envelope = json_decode($envelopeJson, true);
        if (!isset($envelope['iv'], $envelope['tag'], $envelope['ct'])) {
            throw new RuntimeException('Format envelope tidak valid.');
        }

        $iv  = base64_decode($envelope['iv'],  true);
        $tag = base64_decode($envelope['tag'], true);
        $ct  = base64_decode($envelope['ct'],  true);

        // Buka lapis AES dengan K_sym
        $rsaEncrypted = openssl_decrypt($ct, 'aes-256-gcm', $kSym, OPENSSL_RAW_DATA, $iv, $tag);
        if ($rsaEncrypted === false) {
            throw new RuntimeException('Dekripsi envelope AES gagal (K_sym salah atau data rusak).');
        }

        // Buka lapis RSA dengan kunci privat
        $dek = '';
        $privKey = openssl_pkey_get_private($rsaPrivKeyPem);
        if ($privKey === false) {
            throw new RuntimeException('Kunci privat RSA tidak valid: ' . openssl_error_string());
        }

        $ok = openssl_private_decrypt($rsaEncrypted, $dek, $privKey, OPENSSL_PKCS1_OAEP_PADDING);
        if (!$ok) {
            throw new RuntimeException('RSA dekripsi DEK gagal: ' . openssl_error_string());
        }

        return $dek;
    }

    // --- Enkripsi seluruh baris mahasiswa ---

    /**
     * Enkripsi data mahasiswa. Return array kolom siap simpan ke DB.
     */
    public static function encryptMahasiswa(array $data, string $rsaPublicKeyPem, string $kSym): array
    {
        $dek = random_bytes(32); // DEK unik per baris

        return [
            'nim_hash'     => self::hmacNim($data['nim'], $kSym),
            'nim_enc'      => self::encryptField($data['nim'],   $dek),
            'nama_enc'     => self::encryptField($data['nama'],  $dek),
            'prodi_enc'    => self::encryptField($data['prodi'], $dek),
            'email_enc'    => self::encryptField($data['email'], $dek),
            'dek_envelope' => self::wrapDek($dek, $rsaPublicKeyPem, $kSym),
        ];
    }

    /**
     * Dekripsi baris mahasiswa.
     */
    public static function decryptMahasiswa(array $row, string $rsaPrivKeyPem, string $kSym): array
    {
        $dek = self::unwrapDek($row['dek_envelope'], $rsaPrivKeyPem, $kSym);

        return [
            'id'    => $row['id'],
            'nim'   => self::decryptField($row['nim_enc'],   $dek),
            'nama'  => self::decryptField($row['nama_enc'],  $dek),
            'prodi' => self::decryptField($row['prodi_enc'], $dek),
            'email' => self::decryptField($row['email_enc'], $dek),
        ];
    }

    // --- Enkripsi seluruh baris nilai ---

    public static function encryptNilai(array $data, string $rsaPublicKeyPem, string $kSym): array
    {
        $dek = random_bytes(32);

        return [
            'mahasiswa_id'    => (int) $data['mahasiswa_id'],
            'matkul_enc'      => self::encryptField($data['mata_kuliah'],  $dek),
            'sks_enc'         => self::encryptField((string)$data['sks'],  $dek),
            'nilai_angka_enc' => self::encryptField((string)$data['nilai_angka'], $dek),
            'nilai_huruf_enc' => self::encryptField($data['nilai_huruf'],  $dek),
            'dek_envelope'    => self::wrapDek($dek, $rsaPublicKeyPem, $kSym),
        ];
    }

    public static function decryptNilai(array $row, string $rsaPrivKeyPem, string $kSym): array
    {
        $dek = self::unwrapDek($row['dek_envelope'], $rsaPrivKeyPem, $kSym);

        return [
            'id'           => $row['id'],
            'mahasiswa_id' => $row['mahasiswa_id'],
            'mata_kuliah'  => self::decryptField($row['matkul_enc'],      $dek),
            'sks'          => self::decryptField($row['sks_enc'],          $dek),
            'nilai_angka'  => self::decryptField($row['nilai_angka_enc'],  $dek),
            'nilai_huruf'  => self::decryptField($row['nilai_huruf_enc'],  $dek),
        ];
    }

    // --- HMAC Blind Index untuk NIM ---

    /**
     * Buat blind index NIM menggunakan HMAC-SHA256.
     * Kunci turunan: K_sym + konteks agar tidak dipakai ulang di tempat lain.
     */
    public static function hmacNim(string $nim, string $kSym): string
    {
        // Turunkan kunci khusus dengan HKDF sederhana (HMAC dengan info berbeda)
        $derivedKey = hash_hmac('sha256', 'nim-index-v1', $kSym, true);
        return hash_hmac('sha256', $nim, $derivedKey);
    }

    // --- Generate RSA Key Pair ---

    public static function generateRsaKeyPair(): array
    {
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        // Sertakan path openssl.cnf jika tersedia (atasi masalah Windows/Laragon)
        if (OPENSSL_CNF_PATH !== '') {
            $config['config'] = OPENSSL_CNF_PATH;
        }

        $res = openssl_pkey_new($config);
        if ($res === false) {
            $err = openssl_error_string();
            throw new RuntimeException(
                "Generate RSA key gagal: $err\n" .
                "Pastikan path openssl.cnf benar di OPENSSL_CNF_PATH di config.php.\n" .
                "Path terdeteksi: " . OPENSSL_CNF_PATH
            );
        }

        // Ekspor kunci privat
        $privPem = '';
        openssl_pkey_export($res, $privPem, null, OPENSSL_CNF_PATH !== '' ? ['config' => OPENSSL_CNF_PATH] : []);

        // Ekspor kunci publik
        $details = openssl_pkey_get_details($res);
        $pubPem  = $details['key'];

        return ['private' => $privPem, 'public' => $pubPem];
    }

    // --- Generate K_sym ---

    public static function generateKSym(): string
    {
        return random_bytes(32); // 32 byte = 256 bit
    }

    public static function kSymToHex(string $kSym): string
    {
        return bin2hex($kSym);
    }

    public static function hexToKSym(string $hex): string
    {
        if (strlen($hex) !== 64 || !ctype_xdigit($hex)) {
            throw new InvalidArgumentException('Kunci simetris harus 64 karakter hex.');
        }
        return hex2bin($hex);
    }

    // --- Enkripsi sym_check untuk verifikasi login ---

    public static function makeSymCheck(string $kSym): string
    {
        return self::encryptField('CIPHERCAMPUS-SYM-OK', $kSym);
    }

    public static function verifySymCheck(string $symCheck, string $kSym): bool
    {
        try {
            $plain = self::decryptField($symCheck, $kSym);
            return hash_equals('CIPHERCAMPUS-SYM-OK', $plain);
        } catch (Throwable) {
            return false;
        }
    }

    // --- RSA Challenge untuk verifikasi login asimetris ---

    public static function makeRsaChallenge(string $rsaPublicKeyPem): array
    {
        $nonce = random_bytes(32);
        $ct    = '';
        $ok    = openssl_public_encrypt($nonce, $ct, $rsaPublicKeyPem, OPENSSL_PKCS1_OAEP_PADDING);
        if (!$ok) {
            throw new RuntimeException('Buat RSA challenge gagal: ' . openssl_error_string());
        }

        return [
            'rsa_challenge_ct'   => base64_encode($ct),
            'rsa_challenge_hash' => hash('sha256', $nonce),
        ];
    }

    public static function verifyRsaChallenge(
        string $ctB64,
        string $expectedHash,
        string $rsaPrivKeyPem
    ): bool {
        try {
            $ct      = base64_decode($ctB64, true);
            $privKey = openssl_pkey_get_private($rsaPrivKeyPem);
            if ($ct === false || $privKey === false) return false;

            $nonce = '';
            $ok    = openssl_private_decrypt($ct, $nonce, $privKey, OPENSSL_PKCS1_OAEP_PADDING);
            if (!$ok) return false;

            return hash_equals($expectedHash, hash('sha256', $nonce));
        } catch (Throwable) {
            return false;
        }
    }

    // --- Verifikasi bahwa kunci privat cocok dengan kunci publik tersimpan ---

    public static function privateMatchesPublic(string $privPem, string $pubPem): bool
    {
        try {
            $privKey = openssl_pkey_get_private($privPem);
            if ($privKey === false) return false;

            $details = openssl_pkey_get_details($privKey);
            if ($details === false) return false;

            // Bandingkan modulus RSA (n)
            $pubKey     = openssl_pkey_get_public($pubPem);
            $pubDetails = openssl_pkey_get_details($pubKey);

            return hash_equals($details['rsa']['n'], $pubDetails['rsa']['n']);
        } catch (Throwable) {
            return false;
        }
    }

    // --- Inspector: bongkar detail envelope untuk tampilan ---

    public static function inspectEnvelope(string $envelopeB64): array
    {
        $envelopeJson = base64_decode($envelopeB64, true);
        $envelope     = json_decode($envelopeJson, true);

        $iv  = base64_decode($envelope['iv']);
        $tag = base64_decode($envelope['tag']);
        $ct  = base64_decode($envelope['ct']);

        return [
            'iv_hex'           => bin2hex($iv),
            'iv_len'           => strlen($iv),
            'tag_hex'          => bin2hex($tag),
            'tag_len'          => strlen($tag),
            'ct_b64_truncated' => substr($envelope['ct'], 0, 40) . '...',
            'ct_len'           => strlen($ct),
            'total_len'        => strlen(base64_decode($envelopeB64)),
        ];
    }
}
