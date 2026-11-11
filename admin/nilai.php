<?php
/**
 * CipherCampus - CRUD Nilai
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/crypto.php';
require_once __DIR__ . '/../includes/layout.php';

security_headers();
require_login();

$pdo     = db();
$kSym    = session_k_sym();
$privPem = session_priv_pem();
$pubKey  = session_pub_key();

$action = $_GET['action'] ?? 'list';
$error  = '';

// Hitung nilai huruf dari angka
function hitungNilaiHuruf(float $angka): string {
    if ($angka >= 85) return 'A';
    if ($angka >= 75) return 'B+';
    if ($angka >= 70) return 'B';
    if ($angka >= 65) return 'C+';
    if ($angka >= 55) return 'C';
    if ($angka >= 45) return 'D';
    return 'E';
}

// --- Hapus ---
if ($action === 'delete' && isset($_GET['id'])) {
    if (!verify_csrf()) { $error = 'Token CSRF tidak valid.'; $action = 'list'; }
    else {
        $pdo->prepare("DELETE FROM nilai WHERE id = ?")->execute([(int)$_GET['id']]);
        header('Location: ' . BASE_URL . '/admin/nilai.php?msg=deleted');
        exit;
    }
}

// --- Simpan ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Token CSRF tidak valid.';
    } else {
        $mahasiswaId = (int)($_POST['mahasiswa_id'] ?? 0);
        $matkul      = trim($_POST['mata_kuliah'] ?? '');
        $sks         = (int)($_POST['sks'] ?? 0);
        $nilaiAngka  = (float)($_POST['nilai_angka'] ?? 0);
        $nilaiHuruf  = hitungNilaiHuruf($nilaiAngka);
        $editId      = (int)($_POST['edit_id'] ?? 0);

        // Validasi
        if ($mahasiswaId <= 0) {
            $error = 'Pilih mahasiswa.';
        } elseif (strlen($matkul) < 3) {
            $error = 'Nama mata kuliah minimal 3 karakter.';
        } elseif ($sks < 1 || $sks > 6) {
            $error = 'SKS harus 1-6.';
        } elseif ($nilaiAngka < 0 || $nilaiAngka > 100) {
            $error = 'Nilai angka harus 0-100.';
        } else {
            // Pastikan mahasiswa ada
            $mhs = $pdo->prepare("SELECT id FROM mahasiswa WHERE id = ?");
            $mhs->execute([$mahasiswaId]);
            if (!$mhs->fetch()) {
                $error = 'Mahasiswa tidak ditemukan.';
            } else {
                try {
                    $enc = CryptoService::encryptNilai([
                        'mahasiswa_id' => $mahasiswaId,
                        'mata_kuliah'  => $matkul,
                        'sks'          => $sks,
                        'nilai_angka'  => $nilaiAngka,
                        'nilai_huruf'  => $nilaiHuruf,
                    ], $pubKey, $kSym);

                    if ($editId > 0) {
                        $pdo->prepare(
                            "UPDATE nilai SET mahasiswa_id=?, matkul_enc=?, sks_enc=?, nilai_angka_enc=?, nilai_huruf_enc=?, dek_envelope=? WHERE id=?"
                        )->execute([
                            $mahasiswaId, $enc['matkul_enc'], $enc['sks_enc'],
                            $enc['nilai_angka_enc'], $enc['nilai_huruf_enc'], $enc['dek_envelope'], $editId
                        ]);
                        header('Location: ' . BASE_URL . '/admin/nilai.php?msg=updated');
                    } else {
                        $pdo->prepare(
                            "INSERT INTO nilai (mahasiswa_id, matkul_enc, sks_enc, nilai_angka_enc, nilai_huruf_enc, dek_envelope) VALUES (?,?,?,?,?,?)"
                        )->execute([
                            $mahasiswaId, $enc['matkul_enc'], $enc['sks_enc'],
                            $enc['nilai_angka_enc'], $enc['nilai_huruf_enc'], $enc['dek_envelope']
                        ]);
                        header('Location: ' . BASE_URL . '/admin/nilai.php?msg=added');
                    }
                    exit;
                } catch (Throwable $e) {
                    $error = 'Enkripsi gagal: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES);
                }
            }
        }
        $action = $editId > 0 ? 'edit' : 'add';
    }
}

// Data mahasiswa untuk dropdown
$mahasiswaList = $pdo->query("SELECT id, nim_hash FROM mahasiswa ORDER BY created_at DESC")->fetchAll();

// Paginasi
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;
$total   = (int)$pdo->query("SELECT COUNT(*) FROM nilai")->fetchColumn();
$pages   = (int)ceil($total / $perPage);
$rows    = $pdo->prepare("SELECT n.*, m.id as mhs_id FROM nilai n JOIN mahasiswa m ON n.mahasiswa_id=m.id ORDER BY n.created_at DESC LIMIT ? OFFSET ?");
$rows->execute([$perPage, $offset]);
$rows = $rows->fetchAll();

$msgMap = [
    'added'   => ['Nilai berhasil ditambahkan!', 'success'],
    'updated' => ['Nilai berhasil diperbarui!', 'success'],
    'deleted' => ['Nilai berhasil dihapus.', 'info'],
];
$msg = $msgMap[$_GET['msg'] ?? ''] ?? null;

render_head('Nilai');
render_admin_layout_start('Data Nilai', 'nilai');
?>
<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<?php if ($msg): ?>
<div id="pageMsg" data-msg="<?= htmlspecialchars($msg[0], ENT_QUOTES) ?>" data-type="<?= $msg[1] ?>"></div>
<?php endif; ?>

<div class="flex items-center justify-between mb-6">
    <p style="color:var(--text-dim);font-size:13px"><?= $total ?> record nilai</p>
    <div style="display:flex;gap:8px;align-items:center">
        <label class="toggle-wrap">
            <div class="toggle-switch">
                <input type="checkbox" id="decryptToggle">
                <span class="toggle-track"></span>
            </div>
            <span style="font-size:13px;color:var(--text-dim)">Plaintext</span>
        </label>
        <button class="btn btn-primary btn-sm" onclick="CC.openModal('modalTambah')">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M7 2v10M2 7h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            Tambah Nilai
        </button>
    </div>
</div>

<div class="table-wrap" id="nilaiTable" data-table="nilai">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Mahasiswa ID</th>
                <th>Mata Kuliah <span class="badge badge-cipher" style="vertical-align:middle;font-size:10px">enc</span></th>
                <th>SKS <span class="badge badge-cipher" style="vertical-align:middle;font-size:10px">enc</span></th>
                <th>Nilai Angka <span class="badge badge-cipher" style="vertical-align:middle;font-size:10px">enc</span></th>
                <th>Nilai Huruf <span class="badge badge-cipher" style="vertical-align:middle;font-size:10px">enc</span></th>
                <th>Dibuat</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $i => $row): ?>
            <tr data-id="<?= $row['id'] ?>">
                <td style="color:var(--text-muted);font-family:var(--font-mono);font-size:12px"><?= $offset + $i + 1 ?></td>
                <td style="font-family:var(--font-mono);font-size:12px">
                    <a href="<?= BASE_URL ?>/admin/mahasiswa.php" style="color:var(--accent2)">#<?= $row['mahasiswa_id'] ?></a>
                </td>
                <td data-plain-field="mata_kuliah">
                    <span class="cipher-orig td-cipher"><?= htmlspecialchars(substr($row['matkul_enc'], 0, 28) . '…', ENT_QUOTES) ?></span>
                </td>
                <td data-plain-field="sks">
                    <span class="cipher-orig td-cipher"><?= htmlspecialchars(substr($row['sks_enc'], 0, 20) . '…', ENT_QUOTES) ?></span>
                </td>
                <td data-plain-field="nilai_angka">
                    <span class="cipher-orig td-cipher"><?= htmlspecialchars(substr($row['nilai_angka_enc'], 0, 24) . '…', ENT_QUOTES) ?></span>
                </td>
                <td data-plain-field="nilai_huruf">
                    <span class="cipher-orig td-cipher"><?= htmlspecialchars(substr($row['nilai_huruf_enc'], 0, 24) . '…', ENT_QUOTES) ?></span>
                </td>
                <td style="font-size:12px;color:var(--text-dim)"><?= htmlspecialchars(date('d/m/y', strtotime($row['created_at'])), ENT_QUOTES) ?></td>
                <td class="td-actions">
                    <div style="display:flex;gap:6px">
                        <a href="?action=edit&id=<?= $row['id'] ?>" class="btn btn-ghost btn-sm" title="Edit">
                            <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><path d="M9.5 1.5l2 2L4 11H2V9l7.5-7.5z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/></svg>
                        </a>
                        <a href="?action=delete&id=<?= $row['id'] ?>&csrf_token=<?= urlencode(csrf_token()) ?>"
                           class="btn btn-danger btn-sm" title="Hapus"
                           onclick="return handleDelete(event)">
                            <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><path d="M2 3.5h9M5 3.5V2h3v1.5M4 3.5v7h5v-7" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
            <tr><td colspan="8" style="text-align:center;padding:32px;color:var(--text-muted)">Belum ada data nilai.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($pages > 1): ?>
<div class="pagination mt-4">
    <?php for ($p = 1; $p <= $pages; $p++): ?>
    <a href="?page=<?= $p ?>" class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<!-- Modal Tambah -->
<div class="modal-backdrop" id="modalTambah">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Tambah Nilai</h3>
            <button class="modal-close" data-close-modal="modalTambah">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M5 5l8 8M13 5l-8 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            </button>
        </div>
        <?php if ($action === 'add' && $error): ?>
        <div class="auth-error" style="margin-bottom:16px"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
        <?php endif; ?>
        <form method="POST" class="auth-form">
            <?= csrf_field() ?>
            <div class="form-group">
                <label>Mahasiswa</label>
                <select name="mahasiswa_id" required>
                    <option value="">Pilih Mahasiswa (ID)</option>
                    <?php foreach ($mahasiswaList as $m): ?>
                    <option value="<?= $m['id'] ?>">#<?= $m['id'] ?> — HMAC: <?= substr($m['nim_hash'], 0, 12) ?>…</option>
                    <?php endforeach; ?>
                </select>
                <span class="hint">ID mahasiswa (gunakan Inspector untuk lihat plaintext NIM)</span>
            </div>
            <div class="form-group">
                <label>Mata Kuliah</label>
                <input type="text" name="mata_kuliah" placeholder="Algoritma & Pemrograman" required>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div class="form-group">
                    <label>SKS</label>
                    <input type="number" name="sks" min="1" max="6" value="3" required>
                </div>
                <div class="form-group">
                    <label>Nilai Angka (0–100)</label>
                    <input type="number" name="nilai_angka" min="0" max="100" step="0.5" placeholder="85" required id="nilaiAngkaInput">
                </div>
            </div>
            <div class="form-group">
                <label>Nilai Huruf (otomatis)</label>
                <input type="text" id="nilaiHurufPreview" readonly value="—" style="background:var(--panel);cursor:default">
            </div>
            <button type="submit" class="btn btn-primary w-full">Simpan & Enkripsi</button>
        </form>
    </div>
</div>

<?php render_admin_layout_end(); ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const msgEl = document.getElementById('pageMsg');
    if (msgEl) window.CC.showToast(msgEl.dataset.msg, msgEl.dataset.type);

    // Hitung nilai huruf otomatis
    const angkaInp = document.getElementById('nilaiAngkaInput');
    const hurufPrev = document.getElementById('nilaiHurufPreview');

    function updateHuruf() {
        const v = parseFloat(angkaInp.value);
        if (isNaN(v)) { hurufPrev.value = '—'; return; }
        if (v >= 85) hurufPrev.value = 'A';
        else if (v >= 75) hurufPrev.value = 'B+';
        else if (v >= 70) hurufPrev.value = 'B';
        else if (v >= 65) hurufPrev.value = 'C+';
        else if (v >= 55) hurufPrev.value = 'C';
        else if (v >= 45) hurufPrev.value = 'D';
        else hurufPrev.value = 'E';
    }
    angkaInp?.addEventListener('input', updateHuruf);

    // Toggle dekripsi
    document.getElementById('decryptToggle')?.addEventListener('change', function() {
        if (this.checked) decryptTable('nilai');
        else {
            document.querySelectorAll('#nilaiTable [data-plain-field]').forEach(cell => {
                const orig = cell.querySelector('.cipher-orig');
                const plain = cell.querySelector('.plain-value');
                if (orig) orig.style.display = '';
                if (plain) plain.remove();
            });
        }
    });

    <?php if ($action === 'add'): ?>
    window.CC.openModal('modalTambah');
    <?php endif; ?>
});

async function decryptTable(tableName) {
    const table = document.getElementById('nilaiTable');
    const rows  = table.querySelectorAll('tr[data-id]');
    const ids   = Array.from(rows).map(r => r.dataset.id);

    rows.forEach(row => {
        row.querySelectorAll('[data-plain-field]').forEach(cell => {
            const orig = cell.querySelector('.cipher-orig');
            if (orig) orig.style.display = 'none';
            if (!cell.querySelector('.plain-value')) {
                const sk = document.createElement('span');
                sk.className = 'plain-value skeleton';
                sk.style.cssText = 'width:60px;height:1em;display:inline-block;border-radius:4px';
                cell.appendChild(sk);
            }
        });
    });

    try {
        const res = await window.CC.apiPost(`${window.BASE_URL}/api/decrypt.php`, { table: tableName, ids });
        if (!res.ok) { window.CC.showToast('Dekripsi gagal: ' + res.msg, 'error'); return; }

        rows.forEach(row => {
            const id = row.dataset.id;
            const data = res.data[id];
            if (!data) return;
            row.querySelectorAll('[data-plain-field]').forEach(cell => {
                const field = cell.dataset.plainField;
                const val = data[field] ?? '—';
                let el = cell.querySelector('.plain-value');
                if (el) {
                    el.className = 'plain-value td-plain';
                    el.textContent = '';
                    window.CC.scrambleText(el, val, 400);
                }
            });
        });
    } catch(e) { window.CC.showToast('Error jaringan', 'error'); }
}

async function handleDelete(e) {
    e.preventDefault();
    const ok = await window.CC.confirmDialog('Hapus data nilai ini?', 'Hapus Nilai');
    if (ok) window.location.href = e.currentTarget.href;
    return false;
}
</script>
