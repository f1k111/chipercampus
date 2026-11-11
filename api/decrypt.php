<?php
/**
 * CipherCampus - API Dekripsi On-Demand
 * POST JSON { table, ids, [verify] }
 * Response: { ok, data: { id: { field: value } } }
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/crypto.php';

header('Content-Type: application/json; charset=utf-8');
security_headers();
init_session();

// Wajib login
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Tidak terautentikasi.']);
    exit;
}

// Wajib CSRF
require_csrf();

// Ambil body JSON
$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Request tidak valid.']);
    exit;
}

$table  = $body['table']  ?? '';
$ids    = $body['ids']    ?? [];
$verify = $body['verify'] ?? false;

// Validasi tabel
if (!in_array($table, ['mahasiswa', 'nilai'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Tabel tidak valid.']);
    exit;
}

// Validasi IDs
if (!is_array($ids) || count($ids) > 50) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'IDs tidak valid.']);
    exit;
}

// Sanitasi IDs: hanya angka
$ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
if (empty($ids)) {
    echo json_encode(['ok' => true, 'data' => []]);
    exit;
}

$kSym    = session_k_sym();
$privPem = session_priv_pem();

$pdo = db();
$placeholders = implode(',', array_fill(0, count($ids), '?'));

try {
    if ($table === 'mahasiswa') {
        $stmt = $pdo->prepare("SELECT * FROM mahasiswa WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $rows = $stmt->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $plain = CryptoService::decryptMahasiswa($row, $privPem, $kSym);
            $result[$row['id']] = [
                'nim'   => $plain['nim'],
                'nama'  => $plain['nama'],
                'prodi' => $plain['prodi'],
                'email' => $plain['email'],
            ];
        }
    } else {
        $stmt = $pdo->prepare("SELECT * FROM nilai WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $rows = $stmt->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $plain = CryptoService::decryptNilai($row, $privPem, $kSym);
            $result[$row['id']] = [
                'mata_kuliah' => $plain['mata_kuliah'],
                'sks'         => $plain['sks'],
                'nilai_angka' => $plain['nilai_angka'],
                'nilai_huruf' => $plain['nilai_huruf'],
            ];
        }
    }

    echo json_encode(['ok' => true, 'data' => $result]);

} catch (Throwable $e) {
    error_log('Decrypt API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Dekripsi gagal: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES)]);
}
