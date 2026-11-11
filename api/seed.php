<?php
/**
 * CipherCampus - Seed Data Contoh
 * POST (dengan CSRF) → isi ±8 mahasiswa dan ±15 nilai dummy
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/crypto.php';

header('Content-Type: application/json; charset=utf-8');
security_headers();
init_session();

if (!is_logged_in()) {
    echo json_encode(['ok' => false, 'msg' => 'Tidak terautentikasi.']);
    exit;
}

require_csrf();

$kSym   = session_k_sym();
$pubKey = session_pub_key();
$pdo    = db();

// Data dummy fiktif (bukan data orang asli)
$mahasiswaDummy = [
    ['nim' => '2201101001', 'nama' => 'Andi Firmansyah',   'prodi' => 'Teknik Informatika',  'email' => 'andi.f@example.com'],
    ['nim' => '2201101002', 'nama' => 'Budi Santoso',       'prodi' => 'Sistem Informasi',     'email' => 'budi.s@example.com'],
    ['nim' => '2201101003', 'nama' => 'Citra Dewi',         'prodi' => 'Teknik Informatika',  'email' => 'citra.d@example.com'],
    ['nim' => '2201101004', 'nama' => 'Dani Hermawan',      'prodi' => 'Ilmu Komputer',        'email' => 'dani.h@example.com'],
    ['nim' => '2201101005', 'nama' => 'Eka Putriani',       'prodi' => 'Data Science',         'email' => 'eka.p@example.com'],
    ['nim' => '2201101006', 'nama' => 'Fandi Kurniawan',    'prodi' => 'Teknik Komputer',      'email' => 'fandi.k@example.com'],
    ['nim' => '2201101007', 'nama' => 'Gita Rahayu',        'prodi' => 'Sistem Informasi',     'email' => 'gita.r@example.com'],
    ['nim' => '2201101008', 'nama' => 'Hendra Wijaya',      'prodi' => 'Teknik Informatika',  'email' => 'hendra.w@example.com'],
];

$nilaiDummy = [
    ['matkul' => 'Algoritma & Pemrograman', 'sks' => 3, 'angka' => 88],
    ['matkul' => 'Basis Data',              'sks' => 3, 'angka' => 75],
    ['matkul' => 'Jaringan Komputer',       'sks' => 3, 'angka' => 82],
    ['matkul' => 'Kriptografi',             'sks' => 3, 'angka' => 91],
    ['matkul' => 'Pemrograman Web',         'sks' => 3, 'angka' => 78],
    ['matkul' => 'Sistem Operasi',          'sks' => 2, 'angka' => 70],
    ['matkul' => 'Kalkulus',                'sks' => 3, 'angka' => 65],
    ['matkul' => 'Statistika',              'sks' => 2, 'angka' => 85],
    ['matkul' => 'Struktur Data',           'sks' => 3, 'angka' => 72],
    ['matkul' => 'Pemrograman Lanjut',      'sks' => 3, 'angka' => 90],
    ['matkul' => 'Kecerdasan Buatan',       'sks' => 3, 'angka' => 68],
    ['matkul' => 'Machine Learning',        'sks' => 3, 'angka' => 95],
    ['matkul' => 'Cloud Computing',         'sks' => 2, 'angka' => 77],
    ['matkul' => 'Ethical Hacking',         'sks' => 3, 'angka' => 86],
    ['matkul' => 'Mobile Programming',      'sks' => 3, 'angka' => 73],
];

function hitungHuruf(int $angka): string {
    if ($angka >= 85) return 'A';
    if ($angka >= 75) return 'B+';
    if ($angka >= 70) return 'B';
    if ($angka >= 65) return 'C+';
    if ($angka >= 55) return 'C';
    if ($angka >= 45) return 'D';
    return 'E';
}

try {
    $countMhs  = 0;
    $countNil  = 0;
    $insertedIds = [];

    foreach ($mahasiswaDummy as $m) {
        $nimHash = CryptoService::hmacNim($m['nim'], $kSym);

        // Skip jika NIM sudah ada
        $exists = $pdo->prepare("SELECT id FROM mahasiswa WHERE nim_hash = ?");
        $exists->execute([$nimHash]);
        if ($row = $exists->fetch()) {
            $insertedIds[] = $row['id'];
            continue;
        }

        $enc = CryptoService::encryptMahasiswa($m, $pubKey, $kSym);
        $pdo->prepare(
            "INSERT INTO mahasiswa (nim_hash, nim_enc, nama_enc, prodi_enc, email_enc, dek_envelope) VALUES (?,?,?,?,?,?)"
        )->execute([
            $enc['nim_hash'], $enc['nim_enc'], $enc['nama_enc'],
            $enc['prodi_enc'], $enc['email_enc'], $enc['dek_envelope']
        ]);
        $insertedIds[] = (int)$pdo->lastInsertId();
        $countMhs++;
    }

    // Ambil semua ID mahasiswa yang ada (termasuk yang sudah ada sebelumnya)
    $allMhsIds = $pdo->query("SELECT id FROM mahasiswa ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);

    // Tambahkan nilai: distribusikan ke mahasiswa yang ada
    shuffle($nilaiDummy);
    foreach ($nilaiDummy as $i => $n) {
        $mhsId = $allMhsIds[$i % count($allMhsIds)];
        $huruf = hitungHuruf($n['angka']);

        // Cek apakah nilai ini sudah ada (berdasarkan ciphertext matkul tidak bisa dicek langsung; skip jika countNil cukup)
        $enc = CryptoService::encryptNilai([
            'mahasiswa_id' => $mhsId,
            'mata_kuliah'  => $n['matkul'],
            'sks'          => $n['sks'],
            'nilai_angka'  => $n['angka'],
            'nilai_huruf'  => $huruf,
        ], $pubKey, $kSym);

        $pdo->prepare(
            "INSERT INTO nilai (mahasiswa_id, matkul_enc, sks_enc, nilai_angka_enc, nilai_huruf_enc, dek_envelope) VALUES (?,?,?,?,?,?)"
        )->execute([
            $mhsId, $enc['matkul_enc'], $enc['sks_enc'],
            $enc['nilai_angka_enc'], $enc['nilai_huruf_enc'], $enc['dek_envelope']
        ]);
        $countNil++;
    }

    echo json_encode([
        'ok'  => true,
        'msg' => "Ditambahkan: $countMhs mahasiswa baru dan $countNil nilai.",
    ]);

} catch (Throwable $e) {
    error_log('Seed error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'msg' => htmlspecialchars($e->getMessage(), ENT_QUOTES)]);
}
