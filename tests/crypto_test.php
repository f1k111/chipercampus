<?php
/**
 * CipherCampus - Test Kriptografi (CLI)
 * Jalankan: php tests/crypto_test.php
 */

// Pastikan dijalankan dari CLI, bukan browser
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("Hanya bisa dijalankan dari CLI.\n");
}

// Bootstrap: muat config.php langsung (mendefinisikan semua konstanta termasuk OPENSSL_CNF_PATH)
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/crypto.php';

// Warna untuk output terminal
$GREEN  = "\033[32m";
$RED    = "\033[31m";
$YELLOW = "\033[33m";
$RESET  = "\033[0m";
$BOLD   = "\033[1m";

$passed = 0;
$failed = 0;

function pass(string $name): void {
    global $passed, $GREEN, $RESET;
    $passed++;
    echo "  {$GREEN}✓{$RESET} $name\n";
}

function fail(string $name, string $reason = ''): void {
    global $failed, $RED, $RESET;
    $failed++;
    echo "  {$RED}✗{$RESET} $name" . ($reason ? ": $reason" : '') . "\n";
}

function assert_true(bool $cond, string $name, string $reason = ''): void {
    if ($cond) pass($name); else fail($name, $reason);
}

function assert_false(bool $cond, string $name, string $reason = ''): void {
    if (!$cond) pass($name); else fail($name, $reason ?: 'seharusnya false');
}

function assert_eq(mixed $a, mixed $b, string $name): void {
    if ($a === $b) pass($name); else fail($name, "expected '$b', got '$a'");
}

echo "\n{$BOLD}CipherCampus - Crypto Test Suite{$RESET}\n";
echo str_repeat('─', 50) . "\n\n";

// ============================================================
echo "{$BOLD}1. AES-256-GCM Field Encryption{$RESET}\n";
// ============================================================

$dek = random_bytes(32);

// Test 1.1: enkripsi → dekripsi
$plain = 'Andi Firmansyah';
$enc   = CryptoService::encryptField($plain, $dek);
$dec   = CryptoService::decryptField($enc, $dek);
assert_eq($dec, $plain, 'enkripsi → dekripsi teks biasa');

// Test 1.2: Unicode / emoji
$plainUni = 'Universitas 测试 🔐 مرحبا';
$encUni   = CryptoService::encryptField($plainUni, $dek);
$decUni   = CryptoService::decryptField($encUni, $dek);
assert_eq($decUni, $plainUni, 'enkripsi → dekripsi teks Unicode & emoji');

// Test 1.3: IV selalu berbeda untuk plaintext yang sama
$enc1 = CryptoService::encryptField($plain, $dek);
$enc2 = CryptoService::encryptField($plain, $dek);
assert_false($enc1 === $enc2, 'IV berbeda untuk enkripsi ulang teks yang sama');

// Test 1.4: kunci salah → gagal (GCM tag)
$wrongDek = random_bytes(32);
$threw = false;
try { CryptoService::decryptField($enc, $wrongDek); } catch (Throwable) { $threw = true; }
assert_true($threw, 'K_sym/DEK salah → exception (tag GCM)');

// Test 1.5: ciphertext dimodifikasi → gagal
$parts    = explode(':', $enc, 3);
$badCt    = base64_encode(str_repeat('X', 32));
$tampered = $parts[0] . ':' . $parts[1] . ':' . $badCt;
$threw    = false;
try { CryptoService::decryptField($tampered, $dek); } catch (Throwable) { $threw = true; }
assert_true($threw, 'ciphertext dimodifikasi 1 byte → exception (tag GCM)');

echo "\n";

// ============================================================
echo "{$BOLD}2. RSA-2048 Key Pair Generation{$RESET}\n";
// ============================================================

echo "  Generating RSA-2048 key pair (mungkin 1-3 detik)…\n";
$t0   = microtime(true);
$keys = CryptoService::generateRsaKeyPair();
$t1   = microtime(true);
printf("  Waktu generate: %.2f detik\n", $t1 - $t0);

assert_true(str_contains($keys['private'], 'PRIVATE KEY'), 'kunci privat berformat PEM');
assert_true(str_contains($keys['public'],  'PUBLIC KEY'),  'kunci publik berformat PEM');

$privPem = $keys['private'];
$pubPem  = $keys['public'];

echo "\n";

// ============================================================
echo "{$BOLD}3. DEK Envelope (Hybrid Encryption){$RESET}\n";
// ============================================================

$kSym = CryptoService::generateKSym();
$dek2 = random_bytes(32);

$envelope = CryptoService::wrapDek($dek2, $pubPem, $kSym);
$dek2Rec  = CryptoService::unwrapDek($envelope, $privPem, $kSym);
assert_eq($dek2Rec, $dek2, 'wrapDek → unwrapDek mengembalikan DEK asli');

// Kunci simetris salah → gagal
$wrongKSym = random_bytes(32);
$threw     = false;
try { CryptoService::unwrapDek($envelope, $privPem, $wrongKSym); } catch (Throwable) { $threw = true; }
assert_true($threw, 'K_sym salah → unwrapDek gagal');

// Kunci privat salah → gagal
$wrongKeys = CryptoService::generateRsaKeyPair();
$threw     = false;
try { CryptoService::unwrapDek($envelope, $wrongKeys['private'], $kSym); } catch (Throwable) { $threw = true; }
assert_true($threw, 'kunci privat salah → unwrapDek gagal');

echo "\n";

// ============================================================
echo "{$BOLD}4. Enkripsi Baris Mahasiswa End-to-End{$RESET}\n";
// ============================================================

$mhsData = [
    'nim'   => '2201101001',
    'nama'  => 'Budi Santoso',
    'prodi' => 'Teknik Informatika',
    'email' => 'budi@example.com',
];

$encrypted = CryptoService::encryptMahasiswa($mhsData, $pubPem, $kSym);
assert_true(!empty($encrypted['dek_envelope']), 'baris mahasiswa: envelope tersimpan');
assert_true(!empty($encrypted['nim_enc']),      'baris mahasiswa: nim_enc tersimpan');

// Simulasi row DB
$dbRow = array_merge(['id' => 42], $encrypted);
$dec   = CryptoService::decryptMahasiswa($dbRow, $privPem, $kSym);
assert_eq($dec['nim'],   $mhsData['nim'],   'mahasiswa: NIM plain cocok');
assert_eq($dec['nama'],  $mhsData['nama'],  'mahasiswa: nama plain cocok');
assert_eq($dec['prodi'], $mhsData['prodi'], 'mahasiswa: prodi plain cocok');
assert_eq($dec['email'], $mhsData['email'], 'mahasiswa: email plain cocok');

echo "\n";

// ============================================================
echo "{$BOLD}5. Enkripsi Baris Nilai End-to-End{$RESET}\n";
// ============================================================

$nilaiData = [
    'mahasiswa_id' => 42,
    'mata_kuliah'  => 'Kriptografi',
    'sks'          => 3,
    'nilai_angka'  => 91,
    'nilai_huruf'  => 'A',
];

$encNilai = CryptoService::encryptNilai($nilaiData, $pubPem, $kSym);
$dbNilai  = array_merge(['id' => 1, 'mahasiswa_id' => 42], $encNilai);
$decNilai = CryptoService::decryptNilai($dbNilai, $privPem, $kSym);
assert_eq($decNilai['mata_kuliah'],  $nilaiData['mata_kuliah'],          'nilai: matkul plain cocok');
assert_eq((int)$decNilai['sks'],     $nilaiData['sks'],                  'nilai: SKS cocok');
assert_eq((float)$decNilai['nilai_angka'], (float)$nilaiData['nilai_angka'], 'nilai: nilai angka cocok');
assert_eq($decNilai['nilai_huruf'],  $nilaiData['nilai_huruf'],          'nilai: nilai huruf cocok');

echo "\n";

// ============================================================
echo "{$BOLD}6. Login Check — Simetris & Asimetris{$RESET}\n";
// ============================================================

// Sym check
$symCheck = CryptoService::makeSymCheck($kSym);
assert_true(CryptoService::verifySymCheck($symCheck, $kSym),         'verifySymCheck: kunci benar → true');
assert_false(CryptoService::verifySymCheck($symCheck, $wrongKSym),   'verifySymCheck: kunci salah → false');

// RSA challenge
$challenge = CryptoService::makeRsaChallenge($pubPem);
assert_true(
    CryptoService::verifyRsaChallenge($challenge['rsa_challenge_ct'], $challenge['rsa_challenge_hash'], $privPem),
    'verifyRsaChallenge: kunci privat benar → true'
);
assert_false(
    CryptoService::verifyRsaChallenge($challenge['rsa_challenge_ct'], $challenge['rsa_challenge_hash'], $wrongKeys['private']),
    'verifyRsaChallenge: kunci privat salah → false'
);

// privateMatchesPublic
assert_true(CryptoService::privateMatchesPublic($privPem, $pubPem),               'privateMatchesPublic: pasangan cocok → true');
assert_false(CryptoService::privateMatchesPublic($wrongKeys['private'], $pubPem), 'privateMatchesPublic: pasangan tidak cocok → false');

echo "\n";

// ============================================================
echo "{$BOLD}7. HMAC Blind Index{$RESET}\n";
// ============================================================

$nim1 = '2201101001';
$nim2 = '2201101002';
$h1a  = CryptoService::hmacNim($nim1, $kSym);
$h1b  = CryptoService::hmacNim($nim1, $kSym);
$h2   = CryptoService::hmacNim($nim2, $kSym);

assert_eq($h1a, $h1b, 'HMAC NIM deterministik (sama untuk input sama)');
assert_false($h1a === $h2, 'HMAC NIM berbeda untuk NIM berbeda');
assert_true(ctype_xdigit($h1a) && strlen($h1a) === 64, 'HMAC NIM menghasilkan 64 karakter hex');

echo "\n";

// ============================================================
// Ringkasan
// ============================================================
echo str_repeat('─', 50) . "\n";
$total = $passed + $failed;
echo "{$BOLD}Hasil: {$passed}/{$total} test passed";
if ($failed > 0) echo " ({$RED}{$failed} failed{$RESET}{$BOLD})";
echo "{$RESET}\n\n";

exit($failed > 0 ? 1 : 0);
